<?php
require_once __DIR__ . '/includes/helpers.php';

const DB_PATH = '/var/www/data/comic/comments.db';

function get_db(): PDO {
    $init = !file_exists(DB_PATH);
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($init) {
        $db->exec('CREATE TABLE comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            manga_id TEXT NOT NULL,
            chapter_id TEXT NOT NULL,
            nickname TEXT NOT NULL,
            content TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime(\'now\', \'localtime\')),
            ip TEXT,
            hidden INTEGER NOT NULL DEFAULT 0
        )');
        $db->exec('CREATE INDEX idx_chapter ON comments (manga_id, chapter_id, hidden)');
        $db->exec('CREATE TABLE views (
            manga_id TEXT NOT NULL,
            chapter_id TEXT NOT NULL,
            count INTEGER NOT NULL DEFAULT 0,
            PRIMARY KEY (manga_id, chapter_id)
        )');
    }
    $db->exec('CREATE TABLE IF NOT EXISTS views (
        manga_id TEXT NOT NULL,
        chapter_id TEXT NOT NULL,
        count INTEGER NOT NULL DEFAULT 0,
        PRIMARY KEY (manga_id, chapter_id)
    )');
    return $db;
    return $db;
}

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$manga_id = $_GET['mid'] ?? '';
$chapter_id = $_GET['cid'] ?? '';

if (!$manga_id || !$chapter_id || !get_manga($manga_id) || !get_chapter($manga_id, $chapter_id)) {
    http_response_code(400);
    echo json_encode(['error' => '參數錯誤']);
    exit;
}

if ($action === 'view' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = get_db();
    $db->prepare('INSERT INTO views (manga_id, chapter_id, count) VALUES (?, ?, 1) ON CONFLICT(manga_id, chapter_id) DO UPDATE SET count = count + 1')->execute([$manga_id, $chapter_id]);
    $stmt = $db->prepare('SELECT count FROM views WHERE manga_id = ? AND chapter_id = ?');
    $stmt->execute([$manga_id, $chapter_id]);
    echo json_encode(['views' => (int)$stmt->fetchColumn()]);
    exit;
}

if ($action === 'list') {
    $db = get_db();
    $stmt = $db->prepare('SELECT id, nickname, content, created_at FROM comments WHERE manga_id = ? AND chapter_id = ? AND hidden = 0 ORDER BY created_at ASC');
    $stmt->execute([$manga_id, $chapter_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $db->prepare('SELECT count FROM views WHERE manga_id = ? AND chapter_id = ?');
    $stmt->execute([$manga_id, $chapter_id]);
    $views = (int)($stmt->fetchColumn() ?: 0);
    echo json_encode(['comments' => $comments, 'views' => $views]);
    exit;
}

if ($action === 'post' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $nickname = trim($input['nickname'] ?? '');
    $content = trim($input['content'] ?? '');
    $honeypot = $input['website'] ?? '';

    if ($honeypot !== '') {
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($nickname === '' || $content === '') {
        http_response_code(400);
        echo json_encode(['error' => '暱稱和內容不能為空']);
        exit;
    }
    if (mb_strlen($nickname) > 30) {
        http_response_code(400);
        echo json_encode(['error' => '暱稱過長']);
        exit;
    }
    if (mb_strlen($content) > 1000) {
        http_response_code(400);
        echo json_encode(['error' => '內容不能超過 1000 字']);
        exit;
    }
    if (preg_match('#https?://|www\.|\.com|\.net|\.org|\.tw|\.cn|\.io#i', $content) || preg_match('#https?://|www\.|\.com|\.net|\.org|\.tw|\.cn|\.io#i', $nickname)) {
        http_response_code(400);
        echo json_encode(['error' => '留言不能包含網址連結']);
        exit;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    $db = get_db();

    $stmt = $db->prepare('SELECT COUNT(*) FROM comments WHERE ip = ? AND created_at > datetime(\'now\', \'localtime\', \'-1 minutes\')');
    $stmt->execute([$ip]);
    if ($stmt->fetchColumn() >= 3) {
        http_response_code(429);
        echo json_encode(['error' => '留言太頻繁，請稍後再試']);
        exit;
    }

    $stmt = $db->prepare('INSERT INTO comments (manga_id, chapter_id, nickname, content, ip) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$manga_id, $chapter_id, $nickname, $content, $ip]);

    echo json_encode(['ok' => true, 'id' => $db->lastInsertId()]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => '未知操作']);
