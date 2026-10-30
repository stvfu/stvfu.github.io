<?php
$config = require '/var/www/data/claude/config.php';

session_name('AIGROUP');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/AI/AI_group/',
    'httponly'  => true,
    'samesite'  => 'Lax',
    'secure'    => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user'])) {
    http_response_code(401);
    exit(json_encode(['error' => '請先登入']));
}

$input = [];
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        http_response_code(403);
        exit(json_encode(['error' => 'CSRF 無效']));
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $input['action'] ?? $action;
}

$db = new PDO('sqlite:/var/www/data/claude/aigroup.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA journal_mode=WAL');
$db->exec('PRAGMA foreign_keys=ON');

$db->exec('CREATE TABLE IF NOT EXISTS conversations (
    id TEXT PRIMARY KEY,
    title TEXT NOT NULL DEFAULT \'新對話\',
    mode TEXT NOT NULL DEFAULT \'chat\',
    created INTEGER NOT NULL,
    updated INTEGER NOT NULL
)');

$db->exec('CREATE TABLE IF NOT EXISTS conv_characters (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    conv_id TEXT NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
    char_id TEXT NOT NULL,
    name TEXT NOT NULL,
    gender TEXT NOT NULL DEFAULT \'\',
    provider TEXT NOT NULL,
    model TEXT NOT NULL,
    color TEXT NOT NULL,
    background TEXT NOT NULL DEFAULT \'\',
    personality TEXT NOT NULL DEFAULT \'\'
)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_cc_conv ON conv_characters(conv_id)');

$db->exec('CREATE TABLE IF NOT EXISTS messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    conv_id TEXT NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
    character TEXT NOT NULL,
    content TEXT NOT NULL,
    created INTEGER NOT NULL
)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_msg_conv ON messages(conv_id, id)');

$db->exec('CREATE TABLE IF NOT EXISTS presets (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    chars_json TEXT NOT NULL,
    created INTEGER NOT NULL,
    updated INTEGER NOT NULL
)');

switch ($action) {
    // ---- Conversations ----
    case 'list':
        $rows = $db->query('SELECT id, title, mode, created, updated FROM conversations ORDER BY updated DESC LIMIT 50')
                    ->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows);
        break;

    case 'get':
        $id = $input['id'] ?? $_GET['id'] ?? '';
        $stmt = $db->prepare('SELECT id, title, mode, created, updated FROM conversations WHERE id = ?');
        $stmt->execute([$id]);
        $conv = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$conv) {
            http_response_code(404);
            echo json_encode(['error' => '對話不存在']);
            break;
        }
        $stmt = $db->prepare('SELECT char_id, name, gender, provider, model, color, background, personality FROM conv_characters WHERE conv_id = ? ORDER BY id');
        $stmt->execute([$id]);
        $conv['characters'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $db->prepare('SELECT character, content, created FROM messages WHERE conv_id = ? ORDER BY id');
        $stmt->execute([$id]);
        $conv['messages'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($conv, JSON_UNESCAPED_UNICODE);
        break;

    case 'create':
        $id = bin2hex(random_bytes(8));
        $now = time();
        $m = $input['mode'] ?? 'chat';
        $db->beginTransaction();
        $db->prepare('INSERT INTO conversations (id, title, mode, created, updated) VALUES (?, ?, ?, ?, ?)')
           ->execute([$id, '新對話', $m, $now, $now]);
        foreach ($input['characters'] ?? [] as $c) {
            $db->prepare('INSERT INTO conv_characters (conv_id, char_id, name, gender, provider, model, color, background, personality) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
               ->execute([$id, $c['id'] ?? '', $c['name'] ?? '', $c['gender'] ?? '', $c['provider'] ?? '', $c['model'] ?? '', $c['color'] ?? '', $c['background'] ?? '', $c['personality'] ?? '']);
        }
        $db->commit();
        echo json_encode(['id' => $id, 'title' => '新對話', 'mode' => $m, 'created' => $now, 'updated' => $now]);
        break;

    case 'append':
        $id = $input['id'] ?? '';
        $character = $input['character'] ?? '';
        $content = $input['content'] ?? '';
        if (trim($content) === '' || trim($character) === '') {
            http_response_code(400);
            echo json_encode(['error' => '參數錯誤']);
            break;
        }
        $now = time();
        $db->beginTransaction();
        $db->prepare('INSERT INTO messages (conv_id, character, content, created) VALUES (?, ?, ?, ?)')
           ->execute([$id, $character, $content, $now]);
        if ($character === 'user') {
            $count = $db->prepare('SELECT COUNT(*) FROM messages WHERE conv_id = ? AND character = \'user\'');
            $count->execute([$id]);
            if ((int) $count->fetchColumn() === 1) {
                $t = str_replace("\n", ' ', trim($content));
                $title = mb_strlen($t) > 40 ? mb_substr($t, 0, 40) . '…' : $t;
                $db->prepare('UPDATE conversations SET title = ?, updated = ? WHERE id = ?')
                   ->execute([$title, $now, $id]);
            } else {
                $db->prepare('UPDATE conversations SET updated = ? WHERE id = ?')->execute([$now, $id]);
            }
        } else {
            $db->prepare('UPDATE conversations SET updated = ? WHERE id = ?')->execute([$now, $id]);
        }
        $db->commit();
        echo json_encode(['ok' => true]);
        break;

    case 'truncate':
        $id = $input['id'] ?? '';
        $keep = (int) ($input['keep'] ?? 0);
        if ($keep < 0) {
            http_response_code(400);
            echo json_encode(['error' => '參數錯誤']);
            break;
        }
        $db->beginTransaction();
        if ($keep === 0) {
            $db->prepare('DELETE FROM messages WHERE conv_id = ?')->execute([$id]);
        } else {
            $stmt = $db->prepare('SELECT id FROM messages WHERE conv_id = ? ORDER BY id LIMIT 1 OFFSET ?');
            $stmt->execute([$id, $keep]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $db->prepare('DELETE FROM messages WHERE conv_id = ? AND id >= ?')->execute([$id, $row['id']]);
            }
        }
        $now = time();
        $db->prepare('UPDATE conversations SET updated = ? WHERE id = ?')->execute([$now, $id]);
        $db->commit();
        echo json_encode(['ok' => true]);
        break;

    case 'delete':
        $id = $input['id'] ?? '';
        $db->prepare('DELETE FROM conversations WHERE id = ?')->execute([$id]);
        echo json_encode(['ok' => true]);
        break;

    // ---- Presets (身分預設組合) ----
    case 'preset_list':
        $rows = $db->query('SELECT id, name, created, updated FROM presets ORDER BY updated DESC')
                    ->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows);
        break;

    case 'preset_save':
        $name = trim($input['name'] ?? '');
        if ($name === '') {
            http_response_code(400);
            echo json_encode(['error' => '名稱不能為空']);
            break;
        }
        $chars = $input['characters'] ?? [];
        if (empty($chars)) {
            http_response_code(400);
            echo json_encode(['error' => '至少需要一個角色']);
            break;
        }
        $id = $input['id'] ?? bin2hex(random_bytes(8));
        $now = time();
        $exists = $db->prepare('SELECT 1 FROM presets WHERE id = ?');
        $exists->execute([$id]);
        if ($exists->fetch()) {
            $db->prepare('UPDATE presets SET name = ?, chars_json = ?, updated = ? WHERE id = ?')
               ->execute([$name, json_encode($chars, JSON_UNESCAPED_UNICODE), $now, $id]);
        } else {
            $db->prepare('INSERT INTO presets (id, name, chars_json, created, updated) VALUES (?, ?, ?, ?, ?)')
               ->execute([$id, $name, json_encode($chars, JSON_UNESCAPED_UNICODE), $now, $now]);
        }
        echo json_encode(['id' => $id, 'name' => $name]);
        break;

    case 'preset_get':
        $id = $input['id'] ?? $_GET['id'] ?? '';
        $stmt = $db->prepare('SELECT id, name, chars_json FROM presets WHERE id = ?');
        $stmt->execute([$id]);
        $preset = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$preset) {
            http_response_code(404);
            echo json_encode(['error' => '預設不存在']);
            break;
        }
        $preset['characters'] = json_decode($preset['chars_json'], true);
        unset($preset['chars_json']);
        echo json_encode($preset, JSON_UNESCAPED_UNICODE);
        break;

    case 'preset_delete':
        $id = $input['id'] ?? '';
        $db->prepare('DELETE FROM presets WHERE id = ?')->execute([$id]);
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => '未知操作']);
}
