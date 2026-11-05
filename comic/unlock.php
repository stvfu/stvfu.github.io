<?php
header('Content-Type: application/json; charset=utf-8');

define('UNLOCK_FILE', __DIR__ . '/data/unlock.json');
define('COOKIE_NAME', 'comic_unlock');
define('COOKIE_SALT', 'komicpunch_unlock_2026');

function load_unlock(): array {
    if (!file_exists(UNLOCK_FILE)) return ['codes' => [], 'enabled' => false];
    $d = json_decode(file_get_contents(UNLOCK_FILE), true);
    if (!is_array($d)) return ['codes' => [], 'enabled' => false];
    if (isset($d['code']) && !isset($d['codes'])) {
        $d['codes'] = [$d['code']];
    }
    return $d;
}

function get_codes(array $unlock): array {
    $codes = $unlock['codes'] ?? [];
    return is_array($codes) ? array_filter(array_map('trim', $codes)) : [];
}

function make_token(string $code): string {
    return hash('sha256', mb_strtolower($code) . COOKIE_SALT);
}

function check_cookie(array $unlock): bool {
    if (empty($_COOKIE[COOKIE_NAME])) return false;
    foreach (get_codes($unlock) as $code) {
        if (hash_equals(make_token($code), $_COOKIE[COOKIE_NAME])) return true;
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $unlock = load_unlock();
    $unlocked = $unlock['enabled'] && check_cookie($unlock);
    echo json_encode(['unlocked' => $unlocked, 'enabled' => !empty($unlock['enabled'])]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'unlock';

if ($action === 'lock') {
    setcookie(COOKIE_NAME, '', [
        'expires' => time() - 3600,
        'path' => '/comic',
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
    echo json_encode(['ok' => true, 'unlocked' => false]);
    exit;
}

$code = trim($input['code'] ?? '');
if ($code === '') {
    http_response_code(400);
    echo json_encode(['error' => '請輸入通關密語']);
    exit;
}

$unlock = load_unlock();
if (!$unlock['enabled'] || empty(get_codes($unlock))) {
    http_response_code(403);
    echo json_encode(['error' => '目前未開放解鎖']);
    exit;
}

$matched = null;
foreach (get_codes($unlock) as $valid) {
    if (mb_strtolower($code) === mb_strtolower($valid)) {
        $matched = $valid;
        break;
    }
}

if (!$matched) {
    http_response_code(403);
    echo json_encode(['error' => '密語不正確']);
    exit;
}

setcookie(COOKIE_NAME, make_token($matched), [
    'expires' => time() + 90 * 86400,
    'path' => '/comic',
    'httponly' => false,
    'samesite' => 'Lax',
]);

echo json_encode(['ok' => true, 'unlocked' => true]);
