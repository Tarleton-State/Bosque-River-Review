<?php
declare(strict_types=1);
require_once __DIR__ . '/../cms/lib.php';
define('MASTER_ACL', ['all' => ['ADMIN'], 'cms_bosque' => ['Editor@tarleton.edu']]);
$count = 0;
function check(bool $condition, string $message): void {
    global $count;
    if (!$condition) throw new RuntimeException('FAILED: ' . $message);
    $count++;
}
function rejected(callable $callback, int $status): void {
    try { $callback(); }
    catch (CmsError $error) { check($error->status === $status, 'Expected status ' . $status); return; }
    throw new RuntimeException('Expected request rejection');
}
foreach (['lib.php', 'api.php', 'login.php', 'config.example.php'] as $source) {
    token_get_all(file_get_contents(__DIR__ . '/../cms/' . $source), TOKEN_PARSE);
    check(true, 'PHP syntax: ' . $source);
}
check(normalize_user(' Editor@TARLETON.EDU ') === 'editor', 'NTNET normalization');
check(allowed_user('editor', 'cms_bosque', MASTER_ACL), 'Site editor allowed');
check(allowed_user('admin', 'other', MASTER_ACL), 'Master administrator allowed');
check(!allowed_user('editor', 'other', MASTER_ACL), 'Cross-site access denied');
check(!allowed_user('outsider', 'cms_bosque', MASTER_ACL), 'Unlisted user denied');
$collection = ['kind' => 'collection', 'path' => 'content/issues'];
check(content_path($collection, 'volume-2') === 'content/issues/volume-2.json', 'Fixed collection path');
rejected(fn() => content_path($collection, '../admin'), 400);
rejected(fn() => content_path($collection, 'x/y'), 400);
$fields = ['fields' => [
    ['name' => 'title', 'widget' => 'string', 'label' => 'Title'],
    ['name' => 'published', 'widget' => 'boolean', 'label' => 'Published'],
    ['name' => 'order', 'widget' => 'number', 'label' => 'Order'],
    ['name' => 'pdf', 'widget' => 'file', 'label' => 'PDF', 'required' => false],
    ['name' => 'cover', 'widget' => 'image', 'label' => 'Cover', 'required' => false],
    ['name' => 'cover_alt', 'widget' => 'string', 'label' => 'Cover description', 'required' => false],
]];
$values = ['title' => 'Volume 2', 'published' => true, 'order' => 2, 'pdf' => '/issues/volume-2.pdf', 'cover' => '', 'cover_alt' => ''];
check(validate_values($fields, $values)['order'] === 2, 'Valid fields accepted');
rejected(fn() => validate_values($fields, $values + ['unexpected' => 'x']), 400);
rejected(fn() => validate_values($fields, array_replace($values, ['order' => '2'])), 400);
rejected(fn() => validate_values($fields, array_replace($values, ['published' => 'true'])), 400);
rejected(fn() => validate_values($fields, array_replace($values, ['pdf' => 'https://example.org/x.pdf'])), 400);
rejected(fn() => validate_values($fields, array_replace($values, ['pdf' => '/issues/%2e%2e/admin/file.pdf'])), 400);
rejected(fn() => validate_values($fields, array_replace($values, ['pdf' => ''])), 400);
rejected(fn() => validate_values($fields, array_replace($values, ['cover' => '/images/x.jpg'])), 400);
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE codes (hash TEXT PRIMARY KEY, user TEXT, site TEXT, origin TEXT, challenge TEXT, expires INTEGER)');
$pdo->exec('CREATE TABLE sessions (hash TEXT PRIMARY KEY, user TEXT, site TEXT, origin TEXT, expires INTEGER)');
$site = ['id' => 'bosque', 'origin' => 'https://editor.example.edu', 'acl' => 'cms_bosque'];
$code = opaque(); $verifier = str_repeat('x', 64);
$pdo->prepare('INSERT INTO codes VALUES (?, ?, ?, ?, ?, ?)')->execute([hash('sha256', $code), 'editor', 'bosque', $site['origin'], b64url(hash('sha256', $verifier, true)), time() + 60]);
rejected(fn() => exchange_code($pdo, $site, ['code' => $code, 'verifier' => str_repeat('y', 64)], MASTER_ACL), 401);
rejected(fn() => exchange_code($pdo, array_replace($site, ['id' => 'another']), ['code' => $code, 'verifier' => $verifier], MASTER_ACL), 401);
$result = exchange_code($pdo, $site, ['code' => $code, 'verifier' => $verifier], MASTER_ACL);
check($result['approved'] === true && strlen($result['token']) === 43, 'Verified login exchanged');
check($pdo->query('SELECT count(*) FROM codes')->fetchColumn() == 0, 'Login code consumed');
check($pdo->query('SELECT hash FROM sessions')->fetchColumn() !== $result['token'], 'Session stored hashed');
rejected(fn() => exchange_code($pdo, $site, ['code' => $code, 'verifier' => $verifier], MASTER_ACL), 401);
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $result['token'];
check(session_user($pdo, $site)['user'] === 'editor', 'Bearer session resolves identity');
rejected(fn() => session_user($pdo, array_replace($site, ['id' => 'another'])), 401);
$pdo->exec("UPDATE sessions SET user='outsider'");
rejected(fn() => session_user($pdo, $site), 403);
$pdo->exec("UPDATE sessions SET expires=0");
rejected(fn() => session_user($pdo, $site), 401);
echo $count . " backend security checks passed.\n";
