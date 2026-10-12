<?php
require __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    exit(json_encode(['error' => '未登入']));
}

$db = new PDO('sqlite:/var/www/data/claude/chat.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA journal_mode=WAL');
$db->exec('PRAGMA foreign_keys=ON');

$db->exec('CREATE TABLE IF NOT EXISTS conversations (
    id TEXT PRIMARY KEY,
    title TEXT NOT NULL DEFAULT \'新對話\',
    created INTEGER NOT NULL,
    updated INTEGER NOT NULL
)');
$db->exec('CREATE TABLE IF NOT EXISTS messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    conv_id TEXT NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
    role TEXT NOT NULL,
    content TEXT NOT NULL,
    created INTEGER NOT NULL
)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_msg_conv ON messages(conv_id, id)');

$input = [];
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        http_response_code(403);
        exit(json_encode(['error' => '請求無效']));
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $input['action'] ?? $action;
}

switch ($action) {
    case 'list':
        $rows = $db->query('SELECT id, title, created, updated FROM conversations ORDER BY updated DESC')
                    ->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows);
        break;

    case 'get':
        $id = $input['id'] ?? $_GET['id'] ?? '';
        $stmt = $db->prepare('SELECT id, title, created, updated FROM conversations WHERE id = ?');
        $stmt->execute([$id]);
        $conv = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$conv) {
            http_response_code(404);
            echo json_encode(['error' => '對話不存在']);
            break;
        }
        $stmt = $db->prepare('SELECT role, content FROM messages WHERE conv_id = ? ORDER BY id');
        $stmt->execute([$id]);
        $conv['messages'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($conv);
        break;

    case 'create':
        $id = bin2hex(random_bytes(8));
        $now = time();
        $db->prepare('INSERT INTO conversations (id, title, created, updated) VALUES (?, ?, ?, ?)')
           ->execute([$id, '新對話', $now, $now]);
        echo json_encode(['id' => $id, 'title' => '新對話', 'created' => $now, 'updated' => $now]);
        break;

    case 'append':
        $id = $input['id'] ?? '';
        $role = $input['role'] ?? '';
        $content = $input['content'] ?? '';
        if (!in_array($role, ['user', 'assistant'], true) || trim($content) === '') {
            http_response_code(400);
            echo json_encode(['error' => '參數錯誤']);
            break;
        }
        $now = time();
        $db->beginTransaction();
        $db->prepare('INSERT INTO messages (conv_id, role, content, created) VALUES (?, ?, ?, ?)')
           ->execute([$id, $role, $content, $now]);
        if ($role === 'user') {
            $count = $db->prepare('SELECT COUNT(*) FROM messages WHERE conv_id = ? AND role = \'user\'');
            $count->execute([$id]);
            if ((int) $count->fetchColumn() === 1) {
                $t = str_replace("\n", ' ', trim($content));
                $title = strlen($t) > 90 ? substr($t, 0, 90) . '…' : $t;
                $db->prepare('UPDATE conversations SET title = ?, updated = ? WHERE id = ?')
                   ->execute([$title, $now, $id]);
            } else {
                $db->prepare('UPDATE conversations SET updated = ? WHERE id = ?')->execute([$now, $id]);
            }
        } else {
            $db->prepare('UPDATE conversations SET updated = ? WHERE id = ?')->execute([$now, $id]);
        }
        $db->commit();
        $stmt = $db->prepare('SELECT id, title, created, updated FROM conversations WHERE id = ?');
        $stmt->execute([$id]);
        echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        break;

    case 'delete':
        $id = $input['id'] ?? '';
        $db->prepare('DELETE FROM conversations WHERE id = ?')->execute([$id]);
        echo json_encode(['ok' => true]);
        break;

    case 'migrate':
        $convs = $input['conversations'] ?? [];
        if (!is_array($convs)) {
            echo json_encode(['ok' => true, 'count' => 0]);
            break;
        }
        $db->beginTransaction();
        foreach ($convs as $c) {
            $cid = $c['id'] ?? bin2hex(random_bytes(8));
            $check = $db->prepare('SELECT 1 FROM conversations WHERE id = ?');
            $check->execute([$cid]);
            if ($check->fetch()) continue;

            $msgs = $c['messages'] ?? [];
            $title = '新對話';
            foreach ($msgs as $m) {
                if (($m['role'] ?? '') === 'user' && trim($m['content'] ?? '') !== '') {
                    $t = str_replace("\n", ' ', trim($m['content']));
                    $title = strlen($t) > 90 ? substr($t, 0, 90) . '…' : $t;
                    break;
                }
            }
            $created = isset($c['created']) ? intval($c['created'] / 1000) : time();
            $updated = isset($c['updated']) ? intval($c['updated'] / 1000) : time();
            $db->prepare('INSERT INTO conversations (id, title, created, updated) VALUES (?, ?, ?, ?)')
               ->execute([$cid, $title, $created, $updated]);
            foreach ($msgs as $m) {
                if (!in_array($m['role'] ?? '', ['user', 'assistant'], true)) continue;
                $db->prepare('INSERT INTO messages (conv_id, role, content, created) VALUES (?, ?, ?, ?)')
                   ->execute([$cid, $m['role'], $m['content'] ?? '', $updated]);
            }
        }
        $db->commit();
        echo json_encode(['ok' => true, 'count' => count($convs)]);
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

    default:
        http_response_code(400);
        echo json_encode(['error' => '未知操作']);
}
