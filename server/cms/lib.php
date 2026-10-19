<?php
declare(strict_types=1);

final class CmsError extends RuntimeException {
    public function __construct(public int $status, string $message) { parent::__construct($message); }
}
function fail(int $status, string $message): never { throw new CmsError($status, $message); }
function normalize_user(string $value): string {
    return strtolower(preg_replace('/@tarleton\.edu$/i', '', trim($value)));
}
function allowed_user(string $user, string $label, array $acl): bool {
    $users = array_merge($acl['all'] ?? [], $acl[$label] ?? []);
    return in_array(normalize_user($user), array_map('normalize_user', $users), true);
}
function b64url(string $value): string { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); }
function opaque(): string { return b64url(random_bytes(32)); }
function send_json(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
}
function configuration(): array {
    $path = getenv('CMS_CONFIG_FILE');
    if (!$path || !is_file($path)) fail(503, 'The editing service is not configured.');
    $config = require $path;
    if (!is_array($config) || !isset($config['sites'], $config['database'], $config['acl_file'])) fail(503, 'Invalid editing-service configuration.');
    if (!is_file($config['acl_file'])) fail(503, 'The access list is unavailable.');
    require_once $config['acl_file'];
    if (!defined('MASTER_ACL') || !is_array(MASTER_ACL)) fail(503, 'The access list is unavailable.');
    return $config;
}
function site_config(array $config, string $id): array {
    $site = $config['sites'][$id] ?? null;
    if (!is_array($site)) fail(404, 'Unknown website.');
    if (!preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~D', $site['repo'] ?? '')) fail(503, 'Invalid repository configuration.');
    if (($site['branch'] ?? '') !== 'main') fail(503, 'This service currently supports the main publishing branch.');
    $origin = $site['origin'] ?? '';
    if (!preg_match('~^https://[A-Za-z0-9.-]+(?::[0-9]{1,5})?$~D', $origin)) fail(503, 'Invalid editor origin.');
    $site['id'] = $id;
    return $site;
}
function cors(array $site): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    header('Vary: Origin');
    if ($origin !== $site['origin']) fail(403, 'This editor origin is not permitted.');
    header('Access-Control-Allow-Origin: ' . $site['origin']);
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    header('Access-Control-Max-Age: 600');
}
function db(array $config): PDO {
    $directory = realpath(dirname($config['database']));
    $docroot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: null;
    if (!$directory || ($docroot && ($directory === $docroot || str_starts_with($directory, $docroot . DIRECTORY_SEPARATOR)))) {
        fail(503, 'Private session storage must be outside the document root.');
    }
    $pdo = new PDO('sqlite:' . $config['database'], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    @chmod($config['database'], 0600);
    $pdo->exec('PRAGMA busy_timeout=5000');
    $pdo->exec('CREATE TABLE IF NOT EXISTS codes (hash TEXT PRIMARY KEY, user TEXT NOT NULL, site TEXT NOT NULL, origin TEXT NOT NULL, challenge TEXT NOT NULL, expires INTEGER NOT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS sessions (hash TEXT PRIMARY KEY, user TEXT NOT NULL, site TEXT NOT NULL, origin TEXT NOT NULL, expires INTEGER NOT NULL)');
    $now = time();
    $pdo->prepare('DELETE FROM codes WHERE expires < ?')->execute([$now]);
    $pdo->prepare('DELETE FROM sessions WHERE expires < ?')->execute([$now]);
    return $pdo;
}
function body(): array {
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') fail(415, 'Send application/json.');
    $limit = 23 * 1024 * 1024;
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $limit) fail(413, 'The upload is too large.');
    $raw = file_get_contents('php://input', false, null, 0, $limit + 1);
    if ($raw === false || strlen($raw) > $limit) fail(413, 'The request is too large.');
    try { $value = json_decode($raw, true, 32, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { fail(400, 'Invalid JSON request.'); }
    if (!is_array($value) || array_is_list($value) && $value !== []) fail(400, 'Expected a JSON object.');
    return $value;
}
function exchange_code(PDO $pdo, array $site, array $input, array $acl): array {
    $code = $input['code'] ?? '';
    $verifier = $input['verifier'] ?? '';
    if (!is_string($code) || !is_string($verifier) || !preg_match('/^[A-Za-z0-9_-]{43}$/D', $code) || !preg_match('/^[A-Za-z0-9._~-]{43,128}$/D', $verifier)) fail(400, 'Invalid login exchange.');
    $pdo->beginTransaction();
    try {
        $query = $pdo->prepare('SELECT * FROM codes WHERE hash = ?');
        $query->execute([hash('sha256', $code)]);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['expires'] <= time() || $row['site'] !== $site['id'] || $row['origin'] !== $site['origin']
            || !hash_equals($row['challenge'], b64url(hash('sha256', $verifier, true)))) fail(401, 'Login expired or could not be verified. Sign in again.');
        if (!allowed_user($row['user'], $site['acl'], $acl)) fail(403, 'You do not have access to this website.');
        $pdo->prepare('DELETE FROM codes WHERE hash = ?')->execute([$row['hash']]);
        $token = opaque();
        $expires = time() + 1800;
        $pdo->prepare('INSERT INTO sessions VALUES (?, ?, ?, ?, ?)')->execute([hash('sha256', $token), $row['user'], $site['id'], $site['origin'], $expires]);
        $pdo->commit();
        return ['approved' => true, 'token' => $token, 'user' => $row['user'], 'expires' => $expires];
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
function session_user(PDO $pdo, array $site): array {
    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer ([A-Za-z0-9_-]{43})$/D', $authorization, $matches)) fail(401, 'Sign in to edit this website.');
    $query = $pdo->prepare('SELECT * FROM sessions WHERE hash = ?');
    $query->execute([hash('sha256', $matches[1])]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['expires'] <= time() || $row['site'] !== $site['id'] || $row['origin'] !== $site['origin']) fail(401, 'Your editing session expired. Sign in again.');
    if (!allowed_user($row['user'], $site['acl'], MASTER_ACL)) fail(403, 'You no longer have access to this website.');
    return $row;
}
function schema(array $site): array {
    if (!is_file($site['schema_file'] ?? '')) fail(503, 'The content schema is unavailable.');
    $value = json_decode(file_get_contents($site['schema_file']), true, 32, JSON_THROW_ON_ERROR);
    return $value['sections'];
}
function section(array $site, string $name): array {
    foreach (schema($site) as $entry) if ($entry['name'] === $name) return $entry;
    fail(400, 'Unknown content section.');
}
function content_path(array $section, string $id): string {
    if ($section['kind'] === 'file') return $section['path'];
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $id) || strlen($id) > 100) fail(400, 'Use an issue identifier such as volume-2.');
    return $section['path'] . '/' . $id . '.json';
}
function validate_values(array $section, array $values): array {
    $result = [];
    $allowed = array_column($section['fields'], 'name');
    if (array_diff(array_keys($values), $allowed)) fail(400, 'Unexpected content fields.');
    foreach ($section['fields'] as $field) {
        $key = $field['name']; $type = $field['widget']; $value = $values[$key] ?? null;
        if ($type === 'boolean') {
            if (!is_bool($value)) fail(400, $field['label'] . ' must be true or false.');
        } elseif ($type === 'number') {
            if (!is_int($value) || $value < 1) fail(400, $field['label'] . ' must be a positive whole number.');
        } else {
            if (!is_string($value) || strlen($value) > 100000) fail(400, 'Invalid ' . $field['label'] . '.');
            $value = trim($value);
            if (($field['required'] ?? true) && $value === '') fail(400, $field['label'] . ' is required.');
            if (($type === 'image' || $type === 'file') && $value !== '') {
                $folder = $type === 'file' ? 'issues' : 'images';
                $decoded = rawurldecode($value);
                $parts = explode('/', ltrim($decoded, '/'));
                if (($parts[0] ?? '') !== $folder || in_array('..', $parts, true) || str_contains($decoded, '\\') || str_contains($decoded, '?') || str_contains($decoded, '#') || preg_match('/[\x00-\x1F]/', $decoded)) fail(400, 'Select a file from the ' . $folder . ' folder.');
                $extension = strtolower(pathinfo($decoded, PATHINFO_EXTENSION));
                $extensions = $type === 'file' ? ['pdf'] : ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif'];
                if (!in_array($extension, $extensions, true)) fail(400, 'Unsupported media type.');
            }
        }
        $result[$key] = $value;
    }
    if (($result['published'] ?? false) && empty($result['pdf'])) fail(400, 'Select a PDF before showing this issue on the website.');
    if (!empty($result['cover']) && empty($result['cover_alt'])) fail(400, 'Enter a description for the cover.');
    return $result;
}
function github(array $site, string $method, string $path, ?array $payload = null): array {
    $secret = getenv($site['github_token_env']);
    if (!$secret) fail(503, 'The server GitHub connection is not configured.');
    $url = 'https://api.github.com/repos/' . $site['repo'] . '/' . $path;
    $curl = curl_init($url);
    $headers = ['Authorization: Bearer ' . $secret, 'Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28', 'User-Agent: Tarleton-WebOps-CMS'];
    $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30, CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2];
    if ($payload !== null) { $headers[] = 'Content-Type: application/json'; $options[CURLOPT_HTTPHEADER] = $headers; $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR); }
    curl_setopt_array($curl, $options);
    $raw = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($raw === false || !$status) fail(502, 'The server could not reach GitHub. Your edits have not been discarded.');
    $data = json_decode($raw, true);
    if ($status === 409 || $status === 422) fail(409, 'GitHub could not accept this version. Another edit, branch rule, or duplicate entry may conflict. Keep a copy of your changes and reload the entry.');
    if ($status === 401 || $status === 403) fail(502, 'The server GitHub credential lacks access or approval. Contact Web Operations.');
    if ($status === 404) fail(404, 'The content was not found, or the server lacks repository access.');
    if ($status < 200 || $status >= 300 || !is_array($data)) fail(502, 'GitHub could not complete this request.');
    return $data;
}
function encoded_path(string $path): string { return implode('/', array_map('rawurlencode', explode('/', $path))); }
function audit(string $user, array $site, string $action, string $path): void {
    error_log(json_encode(['cms_user' => $user, 'site' => $site['id'], 'action' => $action, 'path' => $path], JSON_THROW_ON_ERROR));
}
