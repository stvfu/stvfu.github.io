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

    case 'mkdir':
        $path = resolve_path((string)($_POST['path'] ?? ''), false);
        if ($path === null || $path === BASE_DIR) {
            fail('Invalid directory name.');
        }
        if (file_exists($path)) {
            fail('Already exists.', 409);
        }
        if (!@mkdir($path)) {
            fail('Failed to create directory (permission denied?).', 403);
        }
        respond(['path' => relative_path($path)]);

    case 'delete':
        $path = resolve_path((string)($_POST['path'] ?? ''));
        if ($path === null || $path === BASE_DIR) {
            fail('Invalid path.');
        }
        if (is_dir($path)) {
            $contents = @scandir($path);
            if ($contents !== false && count(array_diff($contents, ['.', '..'])) > 0) {
                if (empty($_POST['force'])) {
                    fail('Directory is not empty. Send force=1 to delete recursively.', 409);
                }
                deleteRecursive($path);
            } else {
                if (!@rmdir($path)) {
                    fail('Failed to delete directory (permission denied?).', 403);
                }
            }
        } elseif (is_file($path) || is_link($path)) {
            if (!@unlink($path)) {
                fail('Failed to delete file (permission denied?).', 403);
            }
        } else {
            fail('Not found.', 404);
        }
        respond(['deleted' => relative_path($path)]);

    case 'upload':
        $dir = resolve_path((string)($_POST['dir'] ?? ''));
        if ($dir === null || !is_dir($dir)) {
            fail('Invalid directory.', 404);
        }
        if (empty($_FILES['files'])) {
            fail('No files uploaded.');
        }
        $files = $_FILES['files'];
        $saved = [];
        $errors = [];
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $errors[] = $files['name'][$i] . ': upload error ' . $files['error'][$i];
                continue;
            }
            $name = basename($files['name'][$i]);
            if ($name === '' || $name === '.' || $name === '..') {
                $errors[] = $files['name'][$i] . ': invalid name';
                continue;
            }
            $dest = $dir . '/' . $name;
            if (file_exists($dest) && empty($_POST['overwrite'])) {
                $errors[] = $name . ': already exists';
                continue;
            }
            if (!move_uploaded_file($files['tmp_name'][$i], $dest)) {
                $errors[] = $name . ': failed to save (permission denied?)';
                continue;
            }
            $saved[] = relative_path($dest);
        }
        respond(['saved' => $saved, 'errors' => $errors]);

    case 'rename':
        $oldPath = resolve_path((string)($_POST['path'] ?? ''));
        if ($oldPath === null || $oldPath === BASE_DIR) {
            fail('Invalid source path.');
        }
        $newName = basename((string)($_POST['name'] ?? ''));
        if ($newName === '' || $newName === '.' || $newName === '..') {
            fail('Invalid new name.');
        }
        $newPath = dirname($oldPath) . '/' . $newName;
        if (!str_starts_with($newPath, BASE_DIR . '/')) {
            fail('Invalid new name.');
        }
        if (file_exists($newPath)) {
            fail('A file or directory with that name already exists.', 409);
        }
        if (!@rename($oldPath, $newPath)) {
            fail('Failed to rename (permission denied?).', 403);
        }
        respond(['old' => relative_path($oldPath), 'new' => relative_path($newPath)]);

    default:
        fail('Unknown action.');
}

function deleteRecursive(string $dir): void
{
    $items = @scandir($dir);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path) && !is_link($path)) {
            deleteRecursive($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}
