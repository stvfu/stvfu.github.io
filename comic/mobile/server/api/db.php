<?php
require_once __DIR__ . '/../config.php';
$db_path = DB_PATH;
$db_dir = dirname($db_path);

if (!is_dir($db_dir)) {
    mkdir($db_dir, 0700, true);
}

$db = new SQLite3($db_path);
$db->busyTimeout(5000);

$db->exec("CREATE TABLE IF NOT EXISTS fcm_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token TEXT UNIQUE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$db->exec("CREATE TABLE IF NOT EXISTS updates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    body TEXT NOT NULL,
    category TEXT DEFAULT 'update',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
