<?php
date_default_timezone_set('Asia/Taipei');

$config = require '/var/www/data/claude/config.php';

const DB_PATH = '/var/www/database/sleep.db';

session_name('SLEEPLOG');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/page/web_app/sleep/',
    'httponly'  => true,
    'samesite'  => 'Lax',
    'secure'    => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user']);
}

function csrf_ok(?string $token): bool
{
    return is_string($token) && hash_equals($_SESSION['csrf'], $token);
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE IF NOT EXISTS sleep_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            recorded_at TEXT NOT NULL,
            action TEXT NOT NULL
        )");
    }
    return $pdo;
}

function get_last_action(): string
{
    $row = get_last_record();
    return $row ? $row['action'] : '';
}

function get_last_record(): ?array
{
    $db = get_db();
    $row = $db->query("SELECT action, recorded_at FROM sleep_log ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function add_action(string $action): void
{
    $db = get_db();
    $now = date('Ymd_His');
    $stmt = $db->prepare("INSERT INTO sleep_log (recorded_at, action) VALUES (?, ?)");
    $stmt->execute([$now, $action]);
}

function delete_last_action(): bool
{
    $db = get_db();
    $row = $db->query("SELECT id FROM sleep_log ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$row) return false;
    $db->prepare("DELETE FROM sleep_log WHERE id = ?")->execute([$row['id']]);
    return true;
}
