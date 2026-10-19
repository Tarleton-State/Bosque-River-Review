<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
try {
    $config = configuration();
    $site = site_config($config, (string)($_GET['site'] ?? ''));
    $state = (string)($_GET['state'] ?? '');
    $challenge = (string)($_GET['challenge'] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/D', $state) || !preg_match('/^[A-Za-z0-9_-]{43}$/D', $challenge)) fail(400, 'Invalid login request.');
    // Refuse to rely on the hardcoded fallback in the existing helper.
    if (strlen(getenv('LDAP_AUTH_SECRET') ?: '') < 32) fail(503, 'The university login signing secret must be configured.');
    if (!is_file($config['auth_helper_file'] ?? '')) fail(503, 'The university login helper is unavailable.');
    require_once $config['auth_helper_file'];
    $user = normalize_user(require_auth($site['acl']));
    if (!allowed_user($user, $site['acl'], MASTER_ACL)) fail(403, 'You are not approved for this website.');
    $pdo = db($config);
    $code = opaque();
    $pdo->prepare('INSERT INTO codes VALUES (?, ?, ?, ?, ?, ?)')->execute([hash('sha256', $code), $user, $site['id'], $site['origin'], $challenge, time() + 60]);
    $nonce = b64url(random_bytes(18));
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer');
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; script-src 'nonce-" . $nonce . "'; frame-ancestors 'none'; base-uri 'none'");
    $message = json_encode(['type' => 'ntnet-cms-login', 'site' => $site['id'], 'state' => $state, 'code' => $code], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $origin = json_encode($site['origin'], JSON_THROW_ON_ERROR);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Signed in</title><p>Signed in. Returning to the website editor…</p><script nonce="' . $nonce . '">if(window.opener){window.opener.postMessage(' . $message . ',' . $origin . ')}else{document.querySelector("p").textContent="Open the website editor and sign in again."}</script></html>';
} catch (Throwable $error) {
    http_response_code($error instanceof CmsError ? $error->status : 500);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Sign-in unavailable</title><p>' . htmlspecialchars($error instanceof CmsError ? $error->getMessage() : 'Sign-in could not be completed. Contact Web Operations.', ENT_QUOTES, 'UTF-8') . '</p></html>';
}
