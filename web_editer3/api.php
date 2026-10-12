<?php
declare(strict_types=1);
require __DIR__ . '/common.php';

function respond(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(string $message, int $status = 400): never
{
    respond(['error' => $message], $status);
}

if (!is_logged_in()) {
    fail('Not logged in.', 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('POST only.', 405);
}
if (!$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    fail('Request too large (see post_max_size).', 413);
}
if (!csrf_valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    fail('Invalid CSRF token, please reload the page.', 403);
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        $dir = resolve_path((string)($_POST['dir'] ?? ''));
        if ($dir === null || !is_dir($dir)) {
            fail('Invalid directory.', 404);
        }
        $names = @scandir($dir);
        if ($names === false) {
            fail('Cannot read directory.', 403);
        }
        $entries = [];
        foreach ($names as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $entries[] = [
                'name'  => $name,
                'isDir' => is_dir($dir . '/' . $name),
                'path'  => ltrim(relative_path($dir) . '/' . $name, '/'),
            ];
        }
        // 資料夾在前,其餘依名稱自然排序
        usort($entries, fn($a, $b) => ($b['isDir'] <=> $a['isDir']) ?:strnatcasecmp($a['name'], $b['name']));
        respond(['dir' => relative_path($dir), 'entries' => $entries]);

    case 'load':
        $path = resolve_path((string)($_POST['path'] ?? ''));
        if ($path === null || !is_file($path)) {
            fail('File not found.', 404);
        }
        if (filesize($path) > MAX_FILE_SIZE) {
            fail('File is too large to edit.', 413);
        }
        $content = @file_get_contents($path);
        if ($content === false) {
            fail('Cannot read file.', 403);
        }
        // 非 UTF-8 (含二進位檔) 經過編輯器存回去會壞掉,直接拒絕
        if (str_contains($content, "\0") || preg_match('//u', $content) !== 1) {
            fail('Not a UTF-8 text file.', 415);
        }
        respond([
            'path'     => relative_path($path),
            'content'  => $content,
            'hash'     => md5($content),
            'writable' => is_writable($path),
        ]);

    case 'save':
        $path = resolve_path((string)($_POST['path'] ?? ''), false);
        if ($path === null || $path === BASE_DIR || is_dir($path)) {
            fail('Invalid file name.');
        }
        $content = (string)($_POST['content'] ?? '');
        if (strlen($content) > MAX_FILE_SIZE) {
            fail('Content is too large.', 413);
        }
        // 既有檔案必須帶著載入時的 hash,避免蓋掉別處的修改或誤蓋同名檔案
        if (file_exists($path) && empty($_POST['force'])
            && !hash_equals((string)md5_file($path), (string)($_POST['hash'] ?? ''))) {
            fail('File already exists or was changed on disk.', 409);
        }
        if (@file_put_contents($path, $content, LOCK_EX) === false) {
            fail('Failed to save the file (permission denied?).', 403);
        }
        respond(['path' => relative_path($path), 'hash' => md5($content)]);

    default:
        fail('Unknown action.');
}
