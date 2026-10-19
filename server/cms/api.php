<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
try {
    $config = configuration();
    $site = site_config($config, (string)($_GET['site'] ?? ''));
    cors($site);
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if ($method === 'OPTIONS') { http_response_code(204); exit; }
    $action = (string)($_GET['action'] ?? '');
    $expected = in_array($action, ['read', 'list', 'session'], true) ? 'GET' : 'POST';
    if ($method !== $expected) fail(405, 'Unsupported request method.');
    $pdo = db($config);
    $input = $method === 'POST' ? body() : $_GET;
    if ($action === 'exchange') send_json(exchange_code($pdo, $site, $input, MASTER_ACL));
    $session = session_user($pdo, $site);
    $user = $session['user'];
    if ($action === 'logout') {
        $pdo->prepare('DELETE FROM sessions WHERE hash = ?')->execute([$session['hash']]);
        send_json(['approved' => false, 'signedOut' => true]);
    }
    if ($action === 'session') send_json(['approved' => true, 'user' => $user, 'expires' => (int)$session['expires']]);
    if ($action === 'list') {
        $section = section($site, (string)($input['section'] ?? ''));
        if ($section['kind'] !== 'collection') fail(400, 'Choose a collection.');
        $listing = github($site, 'GET', 'contents/' . encoded_path($section['path']) . '?ref=' . rawurlencode($site['branch']));
        $entries = [];
        foreach ($listing as $entry) {
            if (($entry['type'] ?? '') !== 'file' || !preg_match('/^([a-z0-9]+(?:-[a-z0-9]+)*)\.json$/D', $entry['name'] ?? '', $matches)) continue;
            $entries[] = ['id' => $matches[1]];
        }
        usort($entries, fn($a, $b) => strnatcasecmp($a['id'], $b['id']));
        send_json(['entries' => $entries]);
    }
    if ($action === 'read' || $action === 'save') {
        $section = section($site, (string)($input['section'] ?? ''));
        $path = content_path($section, (string)($input['id'] ?? ''));
        if ($action === 'read') {
            $file = github($site, 'GET', 'contents/' . encoded_path($path) . '?ref=' . rawurlencode($site['branch']));
            if (($file['encoding'] ?? '') !== 'base64') fail(502, 'Unsupported content encoding.');
            $decoded = base64_decode($file['content'], true);
            if ($decoded === false) fail(502, 'Invalid repository content.');
            $values = json_decode($decoded, true, 32, JSON_THROW_ON_ERROR);
            send_json(['values' => $values, 'sha' => $file['sha']]);
        }
        if (!is_array($input['values'] ?? null)) fail(400, 'Content fields are missing.');
        $values = validate_values($section, $input['values']);
        $payload = ['message' => 'CMS: ' . $user . ' updated ' . $path,
                    'content' => base64_encode(json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"),
                    'branch' => $site['branch']];
        $sha = $input['sha'] ?? null;
        if ($sha !== null) {
            if (!is_string($sha) || !preg_match('/^[a-f0-9]{40}$/D', $sha)) fail(400, 'Invalid content version.');
            $payload['sha'] = $sha;
        } elseif ($section['kind'] !== 'collection') fail(400, 'Reload this page before saving.');
        $result = github($site, 'PUT', 'contents/' . encoded_path($path), $payload);
        audit($user, $site, 'save', $path);
        send_json(['saved' => true, 'sha' => $result['content']['sha']]);
    }
    if ($action === 'upload') {
        $kind = $input['kind'] ?? '';
        if (!in_array($kind, ['pdf', 'image'], true) || !is_string($input['name'] ?? null) || !is_string($input['content'] ?? null)) fail(400, 'Invalid upload.');
        $bytes = base64_decode($input['content'], true);
        if ($bytes === false || strlen($bytes) > 15 * 1024 * 1024 || strlen($bytes) === 0) fail(413, 'Choose a file smaller than 15 MB.');
        $extension = strtolower(pathinfo($input['name'], PATHINFO_EXTENSION));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $types = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
        if (!isset($types[$extension]) || $types[$extension] !== $mime || ($kind === 'pdf') !== ($extension === 'pdf')) fail(400, 'The selected file is not a supported PDF or image.');
        $name = preg_replace('/[^a-z0-9-]+/', '-', strtolower(pathinfo($input['name'], PATHINFO_FILENAME)));
        $name = substr(trim($name, '-'), 0, 60) ?: 'upload';
        $path = ($kind === 'pdf' ? 'issues/' : 'images/') . 'cms-' . bin2hex(random_bytes(8)) . '-' . $name . '.' . $extension;
        github($site, 'PUT', 'contents/' . encoded_path($path), ['message' => 'CMS: ' . $user . ' uploaded ' . $path, 'branch' => $site['branch'], 'content' => base64_encode($bytes)]);
        audit($user, $site, 'upload', $path);
        send_json(['uploaded' => true, 'path' => '/' . $path]);
    }
    fail(400, 'Unknown editor action.');
} catch (CmsError $error) {
    send_json(['approved' => false, 'error' => $error->getMessage()], $error->status);
} catch (Throwable $error) {
    error_log('CMS internal failure: ' . get_class($error));
    send_json(['approved' => false, 'error' => 'The editing service could not complete the request. Contact Web Operations.'], 500);
}
