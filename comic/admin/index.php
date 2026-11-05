<?php
session_start();
require '/var/www/admin_credentials.php';

define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', '/comic');
define('DATA_FILE', BASE_PATH . '/data/manga.json');
define('WORKS_DIR', BASE_PATH . '/works/');
define('ALLOWED_IMG', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('MAX_UPLOAD', 50 * 1024 * 1024);

// ── Auth ──
if (isset($_POST['logout'])) { unset($_SESSION['comic_admin']); header('Location: .'); exit; }
if (isset($_POST['username'], $_POST['password'])) {
    if ($_POST['username'] === ADMIN_USER && $_POST['password'] === ADMIN_PASS) {
        $_SESSION['comic_admin'] = true;
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    $login_error = '帳號或密碼錯誤';
}
if (empty($_SESSION['comic_admin'])) { show_login($login_error ?? null); exit; }

// ── Data helpers ──
function load_data(): array {
    if (!file_exists(DATA_FILE)) return [];
    $d = json_decode(file_get_contents(DATA_FILE), true);
    return is_array($d) ? $d : [];
}

function save_data(array $data): void {
    $dir = dirname(DATA_FILE);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents(DATA_FILE, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function load_page_order(string $order_file, string $img_dir): array {
    $abs_order = BASE_PATH . '/' . $order_file;
    $abs_dir = BASE_PATH . '/' . $img_dir;
    if (file_exists($abs_order)) {
        $d = json_decode(file_get_contents($abs_order), true);
        if (is_array($d)) {
            return array_values(array_filter($d, fn($f) => file_exists($abs_dir . '/' . $f)));
        }
    }
    return scan_images($abs_dir);
}

function save_page_order(string $order_file, array $order): void {
    $abs = BASE_PATH . '/' . $order_file;
    $dir = dirname($abs);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($abs, json_encode(array_values($order), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function scan_images(string $abs_dir): array {
    $files = [];
    if (!is_dir($abs_dir)) return $files;
    foreach (glob($abs_dir . '/*') as $path) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ALLOWED_IMG)) $files[] = basename($path);
    }
    sort($files, SORT_NATURAL);
    return $files;
}

function validate_work_id(string $id): bool {
    return preg_match('/^[a-z0-9_]+$/', $id) === 1;
}

function json_out(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function get_chapter(array $work, string $ch_id): ?array {
    foreach ($work['chapters'] ?? [] as $ch) {
        if ($ch['id'] === $ch_id) return $ch;
    }
    return null;
}

// ── API ──
$api = $_GET['api'] ?? '';
if ($api !== '') {
    $work_id = $_GET['work'] ?? '';

    if ($api === 'thumb') {
        serve_thumb();
        exit;
    }

    if ($api === 'save_unlock') {
        $input = json_decode(file_get_contents('php://input'), true);
        $codes = array_values(array_filter(array_map('trim', (array)($input['codes'] ?? []))));
        $enabled = !empty($input['enabled']);
        $unlock_path = BASE_PATH . '/data/unlock.json';
        file_put_contents($unlock_path, json_encode([
            'codes' => $codes,
            'enabled' => $enabled,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        json_out(['ok' => true]);
    }

    if ($api === 'save_app_version') {
        $input = json_decode(file_get_contents('php://input'), true);
        $version_path = BASE_PATH . '/data/app_version.json';
        $ver = [
            'version_code' => (int)($input['version_code'] ?? 0),
            'version_name' => trim($input['version_name'] ?? ''),
            'download_url' => trim($input['download_url'] ?? ''),
            'changelog' => trim($input['changelog'] ?? ''),
            'required' => !empty($input['required']),
        ];
        file_put_contents($version_path, json_encode($ver, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        json_out(['ok' => true]);
    }

    if ($api === 'create_work') {
        $input = json_decode(file_get_contents('php://input'), true);
        $title = trim($input['title'] ?? '');
        $id = trim($input['id'] ?? '');
        if (!$title) json_out(['error' => '請輸入作品名稱'], 400);
        if (!$id || !validate_work_id($id)) json_out(['error' => 'ID 只能使用小寫英文、數字和底線'], 400);
        $data = load_data();
        if (isset($data[$id])) json_out(['error' => '此 ID 已存在'], 400);
        $wdir = WORKS_DIR . $id . '/';
        if (!is_dir($wdir)) mkdir($wdir, 0755, true);
        $type = in_array($input['type'] ?? '', ['original', 'doujin']) ? $input['type'] : 'doujin';
        $data[$id] = [
            'title' => $title,
            'author' => $input['author'] ?? '忽田各',
            'description' => trim($input['description'] ?? ''),
            'cover' => '',
            'type' => $type,
            'status' => $input['status'] ?? '連載中',
            'genres' => array_map('trim', (array)($input['genres'] ?? ['同人'])),
            'updated' => date('Y-m-d'),
            'chapters' => [],
        ];
        save_data($data);
        json_out(['ok' => true, 'id' => $id]);
    }

    if ($api === 'reorder_works') {
        $input = json_decode(file_get_contents('php://input'), true);
        $ids = $input['order'] ?? [];
        if (!is_array($ids) || empty($ids)) json_out(['error' => 'Invalid order'], 400);
        $data = load_data();
        $reordered = [];
        foreach ($ids as $id) {
            if (isset($data[$id])) $reordered[$id] = $data[$id];
        }
        foreach ($data as $id => $v) {
            if (!isset($reordered[$id])) $reordered[$id] = $v;
        }
        save_data($reordered);
        json_out(['ok' => true]);
    }

    if ($api === 'delete_work') {
        $input = json_decode(file_get_contents('php://input'), true);
        $del_id = trim($input['id'] ?? '');
        if (!$del_id || !validate_work_id($del_id)) json_out(['error' => 'Invalid work'], 400);
        $data = load_data();
        if (!isset($data[$del_id])) json_out(['error' => '作品不存在'], 404);
        $wdir = WORKS_DIR . $del_id;
        if (is_dir($wdir)) {
            $it = new RecursiveDirectoryIterator($wdir, RecursiveDirectoryIterator::SKIP_DOTS);
            foreach (new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST) as $f) {
                $f->isDir() ? rmdir($f->getRealPath()) : unlink($f->getRealPath());
            }
            rmdir($wdir);
        }
        unset($data[$del_id]);
        save_data($data);
        json_out(['ok' => true]);
    }

    if (!$work_id || !validate_work_id($work_id)) json_out(['error' => 'Invalid work'], 400);
    $data = load_data();
    if (!isset($data[$work_id])) json_out(['error' => 'Work not found'], 404);

    switch ($api) {
        case 'upload_cover':
            if (empty($_FILES['cover'])) json_out(['error' => '未選擇檔案'], 400);
            $file = $_FILES['cover'];
            if ($file['error'] !== UPLOAD_ERR_OK) json_out(['error' => '上傳錯誤: ' . $file['error']], 400);
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ALLOWED_IMG)) json_out(['error' => '不支援的格式'], 400);
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if (!str_starts_with(finfo_file($finfo, $file['tmp_name']), 'image/')) json_out(['error' => '非圖片'], 400);
            finfo_close($finfo);
            $wdir = WORKS_DIR . $work_id . '/';
            if (!is_dir($wdir)) mkdir($wdir, 0755, true);
            foreach (glob($wdir . 'cover.*') as $old) unlink($old);
            $dest = $wdir . 'cover.' . $ext;
            move_uploaded_file($file['tmp_name'], $dest);
            chmod($dest, 0644);
            $rel = 'works/' . $work_id . '/cover.' . $ext;
            $data[$work_id]['cover'] = $rel;
            save_data($data);
            json_out(['ok' => true, 'cover' => $rel]);
            break;

        case 'set_page_as_cover':
            $input = json_decode(file_get_contents('php://input'), true);
            $img_path = $input['path'] ?? '';
            $abs = BASE_PATH . '/' . $img_path;
            if (!$img_path || !file_exists($abs) || !str_starts_with(realpath($abs), realpath(WORKS_DIR))) {
                json_out(['error' => '無效的圖片路徑'], 400);
            }
            $data[$work_id]['cover'] = $img_path;
            save_data($data);
            json_out(['ok' => true, 'cover' => $img_path]);
            break;

        case 'add_chapter':
            $input = json_decode(file_get_contents('php://input'), true);
            $title = trim($input['title'] ?? '');
            if (!$title) json_out(['error' => '請輸入章節名稱'], 400);
            $chapters = $data[$work_id]['chapters'] ?? [];
            $num = count($chapters) + 1;
            $ch_id = 'ch' . str_pad($num, 2, '0', STR_PAD_LEFT);
            while (get_chapter($data[$work_id], $ch_id)) {
                $num++;
                $ch_id = 'ch' . str_pad($num, 2, '0', STR_PAD_LEFT);
            }
            $ch_dir = "works/{$work_id}/chapters/{$ch_id}";
            $abs_dir = BASE_PATH . '/' . $ch_dir;
            if (!is_dir($abs_dir)) mkdir($abs_dir, 0755, true);
            $order_path = "{$ch_dir}/page_order.json";
            file_put_contents(BASE_PATH . '/' . $order_path, '[]');
            $category = trim($input['category'] ?? '');
            $link = trim($input['link'] ?? '');
            $new_ch = [
                'id' => $ch_id,
                'title' => $title,
                'dir' => $ch_dir,
                'order' => $order_path,
                'date' => date('Y-m-d'),
                'category' => $category,
                'link' => $link,
            ];
            $data[$work_id]['chapters'][] = $new_ch;
            $data[$work_id]['updated'] = date('Y-m-d');
            save_data($data);
            json_out(['ok' => true, 'chapter' => $new_ch]);
            break;

        case 'delete_chapter':
            $ch_id = $_GET['ch'] ?? '';
            $ch = get_chapter($data[$work_id], $ch_id);
            if (!$ch) json_out(['error' => '章節不存在'], 404);
            $abs_dir = BASE_PATH . '/' . $ch['dir'];
            if (is_dir($abs_dir)) {
                $it = new RecursiveDirectoryIterator($abs_dir, RecursiveDirectoryIterator::SKIP_DOTS);
                foreach (new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST) as $f) {
                    $f->isDir() ? rmdir($f->getRealPath()) : unlink($f->getRealPath());
                }
                rmdir($abs_dir);
            }
            $order_abs = BASE_PATH . '/' . $ch['order'];
            if (file_exists($order_abs)) unlink($order_abs);
            $data[$work_id]['chapters'] = array_values(
                array_filter($data[$work_id]['chapters'], fn($c) => $c['id'] !== $ch_id)
            );
            save_data($data);
            json_out(['ok' => true]);
            break;

        case 'page_list':
            $ch_id = $_GET['ch'] ?? '';
            $ch = get_chapter($data[$work_id], $ch_id);
            if (!$ch) json_out(['error' => '章節不存在'], 404);
            $pages = load_page_order($ch['order'], $ch['dir']);
            json_out(['order' => $pages, 'dir' => $ch['dir']]);
            break;

        case 'upload_pages':
            $ch_id = $_GET['ch'] ?? '';
            $ch = get_chapter($data[$work_id], $ch_id);
            if (!$ch) json_out(['error' => '章節不存在'], 404);
            $abs_dir = BASE_PATH . '/' . $ch['dir'];
            if (!is_dir($abs_dir)) mkdir($abs_dir, 0755, true);
            $order = load_page_order($ch['order'], $ch['dir']);
            $results = [];
            if (!empty($_FILES['images'])) {
                $count = count($_FILES['images']['name']);
                for ($i = 0; $i < $count; $i++) {
                    $name = $_FILES['images']['name'][$i];
                    $tmp = $_FILES['images']['tmp_name'][$i];
                    $err = $_FILES['images']['error'][$i];
                    $size = $_FILES['images']['size'][$i];
                    if ($err !== UPLOAD_ERR_OK) { $results[] = ['name' => $name, 'ok' => false, 'msg' => "Error: $err"]; continue; }
                    if ($size > MAX_UPLOAD) { $results[] = ['name' => $name, 'ok' => false, 'msg' => '超過 50MB']; continue; }
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    if (!in_array($ext, ALLOWED_IMG)) { $results[] = ['name' => $name, 'ok' => false, 'msg' => '不支援的格式']; continue; }
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $tmp);
                    finfo_close($finfo);
                    if (!str_starts_with($mime, 'image/')) { $results[] = ['name' => $name, 'ok' => false, 'msg' => '非圖片']; continue; }
                    $safe = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $name);
                    $dest = $abs_dir . '/' . $safe;
                    if (file_exists($dest)) {
                        $safe = pathinfo($safe, PATHINFO_FILENAME) . '_' . time() . '.' . $ext;
                        $dest = $abs_dir . '/' . $safe;
                    }
                    if (move_uploaded_file($tmp, $dest)) {
                        chmod($dest, 0644);
                        if (!in_array($safe, $order)) $order[] = $safe;
                        $results[] = ['name' => $safe, 'ok' => true];
                    } else {
                        $results[] = ['name' => $name, 'ok' => false, 'msg' => '儲存失敗'];
                    }
                }
            }
            save_page_order($ch['order'], $order);
            $data[$work_id]['updated'] = date('Y-m-d');
            save_data($data);
            json_out(['results' => $results, 'order' => $order]);
            break;

        case 'reorder':
            $ch_id = $_GET['ch'] ?? '';
            $ch = get_chapter($data[$work_id], $ch_id);
            if (!$ch) json_out(['error' => '章節不存在'], 404);
            $input = json_decode(file_get_contents('php://input'), true);
            $abs_dir = BASE_PATH . '/' . $ch['dir'];
            if (!empty($input['order']) && is_array($input['order'])) {
                $sanitized = array_filter($input['order'], fn($f) => file_exists($abs_dir . '/' . basename($f)));
                save_page_order($ch['order'], array_values($sanitized));
                json_out(['ok' => true]);
            }
            json_out(['error' => 'Invalid data'], 400);
            break;

        case 'delete_page':
            $ch_id = $_GET['ch'] ?? '';
            $ch = get_chapter($data[$work_id], $ch_id);
            if (!$ch) json_out(['error' => '章節不存在'], 404);
            $input = json_decode(file_get_contents('php://input'), true);
            $file = basename($input['file'] ?? '');
            $abs_dir = BASE_PATH . '/' . $ch['dir'];
            $path = $abs_dir . '/' . $file;
            if ($file && file_exists($path)) {
                unlink($path);
                $thumbs = $abs_dir . '/.thumbs/' . $file;
                if (file_exists($thumbs)) unlink($thumbs);
                $order = load_page_order($ch['order'], $ch['dir']);
                $order = array_values(array_filter($order, fn($f) => $f !== $file));
                save_page_order($ch['order'], $order);
                json_out(['ok' => true]);
            }
            json_out(['error' => '檔案不存在'], 404);
            break;

        case 'delete_pages':
            $ch_id = $_GET['ch'] ?? '';
            $ch = get_chapter($data[$work_id], $ch_id);
            if (!$ch) json_out(['error' => '章節不存在'], 404);
            $input = json_decode(file_get_contents('php://input'), true);
            $files = $input['files'] ?? [];
            if (!is_array($files) || empty($files)) json_out(['error' => '未選擇檔案'], 400);
            $abs_dir = BASE_PATH . '/' . $ch['dir'];
            $deleted = 0;
            foreach ($files as $f) {
                $f = basename($f);
                $path = $abs_dir . '/' . $f;
                if ($f && file_exists($path)) {
                    unlink($path);
                    $thumb = $abs_dir . '/.thumbs/' . $f;
                    if (file_exists($thumb)) unlink($thumb);
                    $deleted++;
                }
            }
            $order = load_page_order($ch['order'], $ch['dir']);
            $del_set = array_map('basename', $files);
            $order = array_values(array_filter($order, fn($f) => !in_array($f, $del_set)));
            save_page_order($ch['order'], $order);
            json_out(['ok' => true, 'deleted' => $deleted]);
            break;

        case 'save_info':
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['title'])) $data[$work_id]['title'] = trim($input['title']);
            if (isset($input['description'])) $data[$work_id]['description'] = trim($input['description']);
            if (isset($input['type']) && in_array($input['type'], ['original', 'doujin'])) $data[$work_id]['type'] = $input['type'];
            if (isset($input['status'])) $data[$work_id]['status'] = trim($input['status']);
            if (isset($input['updated'])) $data[$work_id]['updated'] = trim($input['updated']);
            if (isset($input['genres'])) $data[$work_id]['genres'] = array_map('trim', (array)$input['genres']);
            if (isset($input['categories'])) {
                $cats = array_values(array_filter(array_map('trim', (array)$input['categories'])));
                $data[$work_id]['categories'] = $cats;
            }
            save_data($data);
            json_out(['ok' => true]);
            break;

        case 'rename_chapter':
            $ch_id = $_GET['ch'] ?? '';
            $input = json_decode(file_get_contents('php://input'), true);
            $new_title = trim($input['title'] ?? '');
            if (!$new_title) json_out(['error' => '標題不能為空'], 400);
            $found = false;
            foreach ($data[$work_id]['chapters'] as &$ch) {
                if ($ch['id'] === $ch_id) {
                    $ch['title'] = $new_title;
                    if (isset($input['category'])) $ch['category'] = trim($input['category']);
                    if (isset($input['link'])) $ch['link'] = trim($input['link']);
                    $found = true;
                    break;
                }
            }
            unset($ch);
            if (!$found) json_out(['error' => '章節不存在'], 404);
            save_data($data);
            json_out(['ok' => true]);
            break;

        case 'upload_ch_cover':
            $ch_id = $_GET['ch'] ?? '';
            if (empty($_FILES['cover'])) json_out(['error' => '未選擇檔案'], 400);
            $file = $_FILES['cover'];
            if ($file['error'] !== UPLOAD_ERR_OK) json_out(['error' => '上傳錯誤'], 400);
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ALLOWED_IMG)) json_out(['error' => '不支援的格式'], 400);
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if (!str_starts_with(finfo_file($finfo, $file['tmp_name']), 'image/')) json_out(['error' => '非圖片'], 400);
            finfo_close($finfo);
            $found = false;
            $cover_path = '';
            foreach ($data[$work_id]['chapters'] as &$ch) {
                if ($ch['id'] === $ch_id) {
                    $ch_dir = BASE_PATH . '/' . $ch['dir'];
                    if (!is_dir($ch_dir)) mkdir($ch_dir, 0755, true);
                    foreach (glob($ch_dir . '/ch_cover.*') as $old) unlink($old);
                    $dest = $ch_dir . '/ch_cover.' . $ext;
                    move_uploaded_file($file['tmp_name'], $dest);
                    chmod($dest, 0644);
                    $ch['cover'] = $ch['dir'] . '/ch_cover.' . $ext;
                    $cover_path = $ch['cover'];
                    $found = true;
                    break;
                }
            }
            unset($ch);
            if (!$found) json_out(['error' => '章節不存在'], 404);
            save_data($data);
            json_out(['ok' => true, 'cover' => $cover_path]);
            break;

        default:
            json_out(['error' => 'Unknown API'], 400);
    }
    exit;
}

function serve_thumb(): void {
    $rel = $_GET['path'] ?? '';
    $abs = BASE_PATH . '/' . $rel;
    if (!$rel || !file_exists($abs) || !str_starts_with(realpath($abs), realpath(WORKS_DIR))) {
        http_response_code(404);
        exit;
    }
    $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_IMG)) { http_response_code(404); exit; }

    $dir = dirname($abs);
    $thumb_dir = $dir . '/.thumbs/';
    if (!is_dir($thumb_dir)) mkdir($thumb_dir, 0755, true);
    $thumb_path = $thumb_dir . basename($abs);

    if (!file_exists($thumb_path) || filemtime($thumb_path) < filemtime($abs)) {
        $src = match ($ext) {
            'png' => @imagecreatefrompng($abs),
            'gif' => @imagecreatefromgif($abs),
            'webp' => @imagecreatefromwebp($abs),
            default => @imagecreatefromjpeg($abs),
        };
        if (!$src) { header('Content-Type: image/jpeg'); readfile($abs); exit; }
        $w = imagesx($src); $h = imagesy($src);
        $max = 200; $ratio = min($max / $w, $max / $h, 1);
        $nw = (int)($w * $ratio); $nh = (int)($h * $ratio);
        $dst = imagecreatetruecolor($nw, $nh);
        if ($ext === 'png' || $ext === 'webp') { imagealphablending($dst, false); imagesavealpha($dst, true); }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagejpeg($dst, $thumb_path, 80);
        imagedestroy($src); imagedestroy($dst);
    }
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=86400');
    readfile($thumb_path);
    exit;
}

// ── Login page ──
function show_login(?string $error): void {
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>漫畫後台登入</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#1a1a2e;color:#eee;font-family:sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh}
.login-box{background:#16213e;padding:40px;border-radius:12px;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,.5)}
.login-box h1{margin-bottom:20px;font-size:1.4em}
.login-box input{padding:10px 16px;font-size:1em;border:1px solid #444;border-radius:6px;background:#0f3460;color:#eee;width:220px;margin-bottom:8px;display:block;margin-left:auto;margin-right:auto}
.login-box button{margin-top:12px;padding:10px 32px;border:none;border-radius:6px;background:#e94560;color:#fff;font-size:1em;cursor:pointer}
.login-box button:hover{background:#c73e54}
.error{color:#e94560;margin-top:10px}
</style>
</head>
<body>
<form class="login-box" method="post">
<h1>漫畫管理後台</h1>
<input type="text" name="username" placeholder="帳號" autofocus required>
<input type="password" name="password" placeholder="密碼" required>
<button type="submit">登入</button>
<?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
</form>
</body>
</html>
<?php }

// ── Render ──
$data = load_data();
$work_id = $_GET['work'] ?? '';

if ($work_id && isset($data[$work_id])):
    $work = $data[$work_id];
    $chapters = $work['chapters'] ?? [];
    $active_ch = $_GET['ch'] ?? ($chapters[0]['id'] ?? '');
    $active_chapter = get_chapter($work, $active_ch);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($work['title']) ?> - 後台管理</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#1a1a2e;color:#eee;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;padding:20px;max-width:1200px;margin:0 auto}

.top-bar{display:flex;align-items:center;gap:12px;margin-bottom:24px;flex-wrap:wrap}
.top-bar a,.top-bar button{padding:8px 18px;border-radius:6px;border:none;cursor:pointer;font-size:.88em;text-decoration:none;color:#fff}
.btn-back{background:#333}
.btn-back:hover{background:#555}
.btn-preview{background:#2d6a4f}
.btn-preview:hover{background:#40916c}
.btn-logout{background:#555}
.btn-logout:hover{background:#777}
.top-bar h2{flex:1;font-size:1.3em}

.section{background:#16213e;border-radius:10px;padding:20px;margin-bottom:20px}
.section h3{margin-bottom:14px;font-size:1.05em;color:#ccc;border-bottom:1px solid #333;padding-bottom:8px}

.cover-area{display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap}
.cover-preview{width:180px;min-height:240px;background:#0a0a1a;border-radius:8px;overflow:hidden;display:flex;align-items:center;justify-content:center;border:2px dashed #333}
.cover-preview img{width:100%;height:auto;display:block}
.cover-preview .placeholder{color:#555;font-size:3em}
.cover-actions{display:flex;flex-direction:column;gap:10px}
.cover-actions label,.cover-actions button{padding:10px 20px;border-radius:6px;border:none;cursor:pointer;font-size:.9em;color:#fff;text-align:center}
.cover-actions label{background:#0f3460}
.cover-actions label:hover{background:#1a4a8a}
.cover-actions input[type=file]{display:none}

.ch-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}
.ch-tab{padding:8px 18px;border-radius:6px;border:1px solid #333;background:transparent;color:#aaa;cursor:pointer;font-size:.88em;text-decoration:none}
.ch-tab:hover{border-color:#e94560;color:#eee}
.ch-tab.active{background:#e94560;border-color:#e94560;color:#fff}
.ch-tab.add{border-style:dashed;color:#666}
.ch-tab.add:hover{color:#e94560;border-color:#e94560}
.ch-del{margin-left:6px;opacity:.5;font-size:.8em}
.ch-del:hover{opacity:1}

.upload-zone{border:3px dashed #0f3460;border-radius:12px;padding:36px;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;margin-bottom:16px}
.upload-zone.dragover{border-color:#e94560;background:rgba(233,69,96,.08)}
.upload-zone p{font-size:1em;margin-bottom:4px}
.upload-zone small{color:#666}

.progress-bar{display:none;margin-bottom:16px;background:#0a0a1a;border-radius:8px;overflow:hidden;height:28px;position:relative}
.progress-bar .fill{height:100%;background:linear-gradient(90deg,#0f3460,#e94560);transition:width .3s}
.progress-bar .label{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:.85em}

.page-count{text-align:center;margin-bottom:12px;color:#888;font-size:.9em}

.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px}
.card{background:#0f1a30;border-radius:8px;padding:6px;text-align:center;cursor:grab;position:relative;user-select:none;transition:transform .15s,box-shadow .15s}
.card:active{cursor:grabbing}
.card.dragging{opacity:.4;transform:scale(.95)}
.card.drag-over{box-shadow:0 0 0 3px #e94560}
.card img{width:100%;height:140px;object-fit:contain;border-radius:4px;background:#080810}
.card .page-num{display:inline-block;background:#e94560;color:#fff;border-radius:50%;width:26px;height:26px;line-height:26px;font-size:.75em;font-weight:bold;margin-top:4px}
.card .fname{font-size:.65em;color:#666;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.card .actions{position:absolute;top:4px;right:4px;display:none;flex-direction:column;gap:2px}
.card:hover .actions{display:flex}
.card .actions button{width:26px;height:26px;border:none;border-radius:50%;cursor:pointer;font-size:.7em;line-height:26px;padding:0;color:#fff}
.card.selected{box-shadow:0 0 0 3px #4ea8de;background:#0f2a4a}
.card .check{position:absolute;top:4px;left:4px;width:22px;height:22px;border:2px solid #555;border-radius:4px;background:rgba(0,0,0,.4);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;color:transparent;transition:all .15s}
.card.selected .check{border-color:#4ea8de;background:#4ea8de;color:#fff}
.card .btn-del{background:rgba(233,69,96,.85)}
.card .btn-cover{background:rgba(45,106,79,.85);font-size:.6em}

.batch-bar{display:none;align-items:center;gap:10px;margin-bottom:12px;padding:10px 14px;background:#0f2a4a;border:1px solid #4ea8de;border-radius:8px;flex-wrap:wrap}
.batch-bar.show{display:flex}
.batch-bar .sel-info{font-size:.88em;color:#4ea8de;margin-right:auto}
.batch-bar button{padding:6px 16px;border:none;border-radius:6px;cursor:pointer;font-size:.84em;color:#fff}
.batch-bar .btn-sel-all{background:#0f3460}
.batch-bar .btn-sel-none{background:#333}
.batch-bar .btn-batch-del{background:#e94560}

.toast{position:fixed;bottom:30px;left:50%;transform:translateX(-50%);background:#2d6a4f;color:#fff;padding:10px 28px;border-radius:8px;font-size:.92em;display:none;z-index:999}
.toast.error{background:#e94560}

.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:1000;justify-content:center;align-items:center}
.modal-overlay.show{display:flex}
.modal{background:#16213e;padding:24px;border-radius:12px;min-width:300px}
.modal h3{margin-bottom:16px}
.modal input{width:100%;padding:10px;border:1px solid #444;border-radius:6px;background:#0f3460;color:#eee;font-size:1em;margin-bottom:12px}
.modal .modal-btns{display:flex;gap:10px;justify-content:flex-end}
.modal .modal-btns button{padding:8px 20px;border:none;border-radius:6px;cursor:pointer;font-size:.9em;color:#fff}
.modal .btn-ok{background:#e94560}
.modal .btn-cancel{background:#555}

.info-form{display:flex;flex-direction:column;gap:12px}
.info-row{display:flex;flex-direction:column;gap:4px}
.info-row label{font-size:.85em;color:#aaa}
.info-row input,.info-row textarea,.info-row select{padding:10px;border:1px solid #444;border-radius:6px;background:#0f3460;color:#eee;font-size:.95em;font-family:inherit;resize:vertical}
.info-row input:focus,.info-row textarea:focus,.info-row select:focus{border-color:#e94560;outline:none}
.info-row-inline{display:flex;gap:16px}
.info-row-inline .info-row{flex:1}
.btn-save-info{align-self:flex-start;padding:10px 28px;border:none;border-radius:6px;background:#e94560;color:#fff;cursor:pointer;font-size:.92em;margin-top:4px}
.btn-save-info:hover{background:#c73e54}

.cat-list{display:flex;flex-direction:column;gap:6px}
.cat-item{display:flex;align-items:center;gap:8px}
.cat-drag{cursor:grab;color:#555;font-size:1em;user-select:none}
.cat-input{flex:1;padding:8px 12px;border:1px solid #444;border-radius:6px;background:#0f3460;color:#eee;font-size:.9em}
.cat-input:focus{border-color:#e94560;outline:none}
.cat-del{width:26px;height:26px;border:none;border-radius:50%;background:rgba(233,69,96,.6);color:#fff;cursor:pointer;font-size:.75em;flex-shrink:0}

.cat-tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid #333}
.cat-filter-tab{padding:6px 16px;border-radius:20px;border:1px solid #444;color:#aaa;font-size:.82em;text-decoration:none;transition:all .15s}
.cat-filter-tab:hover{border-color:#e94560;color:#eee}
.cat-filter-tab.active{background:#e94560;border-color:#e94560;color:#fff}

.ch-tab{position:relative;display:flex;align-items:center;gap:6px}
.ch-tab-cover{width:28px;height:38px;object-fit:cover;border-radius:3px;flex-shrink:0}
.ch-tab-text{display:flex;flex-direction:column;line-height:1.2}
.ch-cat-label{font-size:.65em;color:#888;font-weight:normal}
.ch-rename{margin-left:4px;opacity:.4;font-size:.75em;cursor:pointer}
.ch-rename:hover{opacity:1}
</style>
</head>
<body>

<div class="top-bar">
    <a href="." class="btn-back">← 返回</a>
    <h2><?= htmlspecialchars($work['title']) ?></h2>
    <a href="<?= BASE_URL ?>/manga.php?id=<?= urlencode($work_id) ?>" target="_blank" class="btn-preview">前台預覽</a>
    <form method="post" style="display:inline"><button name="logout" value="1" class="btn-logout">登出</button></form>
</div>

<!-- Cover -->
<div class="section">
    <h3>封面設置</h3>
    <div class="cover-area">
        <div class="cover-preview" id="coverPreview">
            <?php if (!empty($work['cover']) && file_exists(BASE_PATH . '/' . $work['cover'])): ?>
                <img src="<?= BASE_URL . '/' . htmlspecialchars($work['cover']) ?>" alt="cover">
            <?php else: ?>
                <span class="placeholder">?</span>
            <?php endif; ?>
        </div>
        <div class="cover-actions">
            <label>上傳封面<input type="file" id="coverInput" accept="image/*"></label>
            <small style="color:#666">或從下方頁面點擊 ★ 設為封面</small>
        </div>
    </div>
</div>

<!-- Info Edit -->
<div class="section">
    <h3>作品資訊</h3>
    <div class="info-form">
        <div class="info-row">
            <label>標題</label>
            <input type="text" id="infoTitle" value="<?= htmlspecialchars($work['title']) ?>">
        </div>
        <div class="info-row">
            <label>簡介</label>
            <textarea id="infoDesc" rows="3"><?= htmlspecialchars($work['description'] ?? '') ?></textarea>
        </div>
        <div class="info-row-inline">
            <div class="info-row">
                <label>類型</label>
                <select id="infoType">
                    <option value="original" <?= ($work['type'] ?? 'doujin') === 'original' ? 'selected' : '' ?>>原創</option>
                    <option value="doujin" <?= ($work['type'] ?? 'doujin') === 'doujin' ? 'selected' : '' ?>>同人</option>
                </select>
            </div>
            <div class="info-row">
                <label>狀態</label>
                <select id="infoStatus">
                    <?php foreach (['連載中','已完結','休刊中'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($work['status'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="info-row">
                <label>更新日期</label>
                <input type="date" id="infoDate" value="<?= htmlspecialchars($work['updated'] ?? date('Y-m-d')) ?>">
            </div>
        </div>
        <p style="font-size:.78em;color:#888;margin-top:-4px">「原創」作品會顯示於 App 版；「同人」作品僅顯示於網頁版（避免 Google Play 版權問題）</p>
        <button class="btn-save-info" onclick="saveInfo()">儲存變更</button>
    </div>
</div>

<!-- Categories -->
<div class="section">
    <h3>分類設定</h3>
    <p style="font-size:.82em;color:#888;margin-bottom:10px">設定章節分類頁籤（前台會顯示為切換標籤），例如：網路連載、卷、番外</p>
    <div id="catList" class="cat-list">
        <?php $categories = $work['categories'] ?? []; ?>
        <?php foreach ($categories as $i => $cat): ?>
        <div class="cat-item" data-index="<?= $i ?>">
            <span class="cat-drag" title="拖曳排序">☰</span>
            <input type="text" class="cat-input" value="<?= htmlspecialchars($cat) ?>">
            <button class="cat-del" onclick="this.parentElement.remove()" title="移除">✕</button>
        </div>
        <?php endforeach; ?>
    </div>
    <div style="display:flex;gap:8px;margin-top:10px">
        <button onclick="addCategory()" style="padding:6px 16px;border:none;border-radius:6px;background:#0f3460;color:#eee;cursor:pointer;font-size:.85em">+ 新增分類</button>
        <button onclick="saveCategories()" style="padding:6px 16px;border:none;border-radius:6px;background:#e94560;color:#fff;cursor:pointer;font-size:.85em">儲存分類</button>
    </div>
</div>

<!-- Chapters -->
<?php
    $categories = $work['categories'] ?? [];
    $active_cat = $_GET['cat'] ?? '';
    $filtered_chapters = $chapters;
    if ($active_cat !== '' && !empty($categories)) {
        $filtered_chapters = array_values(array_filter($chapters, fn($c) => ($c['category'] ?? '') === $active_cat));
    }
?>
<div class="section">
    <h3>章節管理</h3>

    <?php if (!empty($categories)): ?>
    <div class="cat-tabs">
        <a href="?work=<?= urlencode($work_id) ?>&cat=" class="cat-filter-tab <?= $active_cat === '' ? 'active' : '' ?>">全部</a>
        <?php foreach ($categories as $cat): ?>
            <a href="?work=<?= urlencode($work_id) ?>&cat=<?= urlencode($cat) ?>" class="cat-filter-tab <?= $active_cat === $cat ? 'active' : '' ?>"><?= htmlspecialchars($cat) ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="ch-tabs">
        <?php foreach ($filtered_chapters as $ch): ?>
            <a href="?work=<?= urlencode($work_id) ?>&ch=<?= urlencode($ch['id']) ?><?= $active_cat !== '' ? '&cat=' . urlencode($active_cat) : '' ?>"
               class="ch-tab <?= $ch['id'] === $active_ch ? 'active' : '' ?>">
                <?php if (!empty($ch['cover'])): ?>
                    <img src="<?= BASE_URL . '/' . htmlspecialchars($ch['cover']) ?>" class="ch-tab-cover" alt="">
                <?php endif; ?>
                <span class="ch-tab-text">
                    <?= htmlspecialchars($ch['title']) ?>
                    <?php if (!empty($ch['category'])): ?>
                        <small class="ch-cat-label"><?= htmlspecialchars($ch['category']) ?></small>
                    <?php endif; ?>
                </span>
                <span class="ch-rename" onclick="event.preventDefault();showRenameChapter('<?= htmlspecialchars($ch['id']) ?>','<?= htmlspecialchars(addslashes($ch['title'])) ?>','<?= htmlspecialchars(addslashes($ch['category'] ?? '')) ?>','<?= htmlspecialchars(addslashes($ch['link'] ?? '')) ?>')" title="編輯">✎</span>
                <span class="ch-del" onclick="event.preventDefault();deleteChapter('<?= htmlspecialchars($ch['id']) ?>','<?= htmlspecialchars($ch['title']) ?>')" title="刪除章節">✕</span>
            </a>
        <?php endforeach; ?>
        <button class="ch-tab add" onclick="showAddChapter()">+ 新增章節</button>
    </div>

    <?php if ($active_chapter): ?>
    <!-- Pages -->
    <div class="upload-zone" id="dropZone">
        <p>將圖片拖曳到此處上傳</p>
        <small>支援 JPG / PNG / GIF / WebP，單檔最大 50MB，可多選</small>
    </div>
    <input type="file" id="fileInput" multiple accept="image/*" style="display:none">

    <div class="progress-bar" id="progressBar">
        <div class="fill" id="progressFill"></div>
        <div class="label" id="progressLabel">0%</div>
    </div>

    <div class="batch-bar" id="batchBar">
        <span class="sel-info" id="selInfo">已選 0 頁</span>
        <button class="btn-sel-all" onclick="selectAll()">全選</button>
        <button class="btn-sel-none" onclick="selectNone()">取消全選</button>
        <button class="btn-batch-del" onclick="batchDelete()">刪除選取</button>
    </div>

    <p class="page-count" id="pageCount"></p>
    <div class="grid" id="grid"></div>
    <?php else: ?>
    <p style="color:#666;text-align:center;padding:30px">請先新增章節</p>
    <?php endif; ?>
</div>

<div class="toast" id="toast"></div>

<!-- Add Chapter Modal -->
<div class="modal-overlay" id="chModal">
    <div class="modal">
        <h3>新增章節</h3>
        <input type="text" id="chTitle" placeholder="章節名稱（例：第1話）" autofocus>
        <?php if (!empty($categories)): ?>
        <label style="font-size:.82em;color:#aaa;display:block;margin-bottom:4px">分類</label>
        <select id="chCategory" style="width:100%;padding:10px;border:1px solid #444;border-radius:6px;background:#0f3460;color:#eee;font-size:1em;margin-bottom:12px">
            <option value="">（無分類）</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <label style="font-size:.82em;color:#aaa;display:block;margin-bottom:4px">超連結（選填，例如通販頁面）</label>
        <input type="url" id="chLink" placeholder="https://...">
        <div class="modal-btns">
            <button class="btn-cancel" onclick="document.getElementById('chModal').classList.remove('show')">取消</button>
            <button class="btn-ok" onclick="addChapter()">新增</button>
        </div>
    </div>
</div>

<!-- Rename Chapter Modal -->
<div class="modal-overlay" id="renameModal">
    <div class="modal">
        <h3>編輯章節</h3>
        <label style="font-size:.82em;color:#aaa;display:block;margin-bottom:4px">章節名稱</label>
        <input type="text" id="renameTitle" placeholder="章節名稱">
        <input type="hidden" id="renameChId">
        <?php if (!empty($categories)): ?>
        <label style="font-size:.82em;color:#aaa;display:block;margin-bottom:4px">分類</label>
        <select id="renameCategory" style="width:100%;padding:10px;border:1px solid #444;border-radius:6px;background:#0f3460;color:#eee;font-size:1em;margin-bottom:12px">
            <option value="">（無分類）</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <label style="font-size:.82em;color:#aaa;display:block;margin-bottom:4px">超連結（選填，例如通販頁面）</label>
        <input type="url" id="renameLink" placeholder="https://...">
        <label style="font-size:.82em;color:#aaa;display:block;margin-bottom:4px">章節封面（選填，適用於實體本）</label>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
            <div id="chCoverPreview" style="width:48px;height:64px;background:#0a0a1a;border-radius:4px;overflow:hidden;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <span style="color:#555;font-size:.8em">無</span>
            </div>
            <label style="padding:6px 14px;border-radius:6px;background:#0f3460;color:#eee;cursor:pointer;font-size:.85em">
                上傳封面<input type="file" id="chCoverInput" accept="image/*" style="display:none">
            </label>
        </div>
        <div class="modal-btns">
            <button class="btn-cancel" onclick="document.getElementById('renameModal').classList.remove('show')">取消</button>
            <button class="btn-ok" onclick="renameChapter()">儲存</button>
        </div>
    </div>
</div>

<script>
const workId = <?= json_encode($work_id) ?>;
const activeChId = <?= json_encode($active_ch) ?>;
const activeChDir = <?= json_encode($active_chapter['dir'] ?? '') ?>;
const apiBase = './?work=' + encodeURIComponent(workId);

// ── Save Info ──
function saveInfo() {
    const title = document.getElementById('infoTitle').value.trim();
    const description = document.getElementById('infoDesc').value.trim();
    const type = document.getElementById('infoType').value;
    const status = document.getElementById('infoStatus').value;
    const updated = document.getElementById('infoDate').value;
    if (!title) { showToast('標題不能為空', true); return; }
    fetch(apiBase + '&api=save_info', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title, description, type, status, updated })
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) {
            showToast('作品資訊已儲存');
            document.querySelector('.top-bar h2').textContent = title;
        } else {
            showToast(d.error || '儲存失敗', true);
        }
    })
    .catch(() => showToast('網路錯誤', true));
}

// ── Categories ──
function addCategory() {
    const list = document.getElementById('catList');
    const div = document.createElement('div');
    div.className = 'cat-item';
    div.innerHTML = '<span class="cat-drag">☰</span><input type="text" class="cat-input" placeholder="分類名稱"><button class="cat-del" onclick="this.parentElement.remove()" title="移除">✕</button>';
    list.appendChild(div);
    div.querySelector('input').focus();
}

function saveCategories() {
    const inputs = document.querySelectorAll('#catList .cat-input');
    const categories = [...inputs].map(el => el.value.trim()).filter(Boolean);
    fetch(apiBase + '&api=save_info', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ categories })
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) { showToast('分類已儲存'); setTimeout(() => location.reload(), 500); }
        else showToast(d.error || '儲存失敗', true);
    })
    .catch(() => showToast('網路錯誤', true));
}

// ── Rename Chapter ──
function showRenameChapter(chId, currentTitle, currentCat, currentLink) {
    document.getElementById('renameChId').value = chId;
    document.getElementById('renameTitle').value = currentTitle;
    document.getElementById('renameLink').value = currentLink || '';
    const catSel = document.getElementById('renameCategory');
    if (catSel) catSel.value = currentCat || '';
    document.getElementById('chCoverPreview').innerHTML = '<span style="color:#555;font-size:.8em">無</span>';
    document.getElementById('renameModal').classList.add('show');
    document.getElementById('renameTitle').focus();
    document.getElementById('renameTitle').select();
}
document.getElementById('renameTitle').addEventListener('keydown', e => { if (e.key === 'Enter') renameChapter(); });

// Chapter cover upload
document.getElementById('chCoverInput').addEventListener('change', function() {
    if (!this.files.length) return;
    const chId = document.getElementById('renameChId').value;
    const fd = new FormData();
    fd.append('cover', this.files[0]);
    fetch(apiBase + '&api=upload_ch_cover&ch=' + encodeURIComponent(chId), { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                document.getElementById('chCoverPreview').innerHTML = '<img src="<?= BASE_URL ?>/' + d.cover + '?t=' + Date.now() + '" style="width:100%;height:100%;object-fit:cover">';
                showToast('章節封面已更新');
            } else {
                showToast(d.error || '上傳失敗', true);
            }
        })
        .catch(() => showToast('網路錯誤', true));
    this.value = '';
});

function renameChapter() {
    const chId = document.getElementById('renameChId').value;
    const title = document.getElementById('renameTitle').value.trim();
    const catSel = document.getElementById('renameCategory');
    const category = catSel ? catSel.value : '';
    const link = document.getElementById('renameLink').value.trim();
    if (!title) { showToast('標題不能為空', true); return; }
    fetch(apiBase + '&api=rename_chapter&ch=' + encodeURIComponent(chId), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title, category, link })
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) {
            showToast('章節已更新');
            document.getElementById('renameModal').classList.remove('show');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(d.error || '儲存失敗', true);
        }
    })
    .catch(() => showToast('網路錯誤', true));
}

const grid = document.getElementById('grid');
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const progressBar = document.getElementById('progressBar');
const progressFill = document.getElementById('progressFill');
const progressLabel = document.getElementById('progressLabel');
const pageCount = document.getElementById('pageCount');
const toast = document.getElementById('toast');
let order = [];
let dragSrcIndex = null;
let selected = new Set();

function showToast(msg, isError) {
    toast.textContent = msg;
    toast.className = 'toast' + (isError ? ' error' : '');
    toast.style.display = 'block';
    setTimeout(() => toast.style.display = 'none', 3000);
}

// ── Cover ──
document.getElementById('coverInput').addEventListener('change', function() {
    if (!this.files.length) return;
    const fd = new FormData();
    fd.append('cover', this.files[0]);
    fetch(apiBase + '&api=upload_cover', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                document.getElementById('coverPreview').innerHTML =
                    '<img src="<?= BASE_URL ?>/' + d.cover + '?t=' + Date.now() + '">';
                showToast('封面已更新');
            } else {
                showToast(d.error || '上傳失敗', true);
            }
        })
        .catch(() => showToast('網路錯誤', true));
    this.value = '';
});

function setPageAsCover(imgPath) {
    fetch(apiBase + '&api=set_page_as_cover', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ path: imgPath })
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) {
            document.getElementById('coverPreview').innerHTML =
                '<img src="<?= BASE_URL ?>/' + d.cover + '?t=' + Date.now() + '">';
            showToast('已設為封面');
        }
    });
}

// ── Chapters ──
function showAddChapter() {
    document.getElementById('chModal').classList.add('show');
    document.getElementById('chTitle').value = '';
    document.getElementById('chTitle').focus();
}
document.getElementById('chTitle').addEventListener('keydown', e => { if (e.key === 'Enter') addChapter(); });

function addChapter() {
    const title = document.getElementById('chTitle').value.trim();
    if (!title) return;
    const catSel = document.getElementById('chCategory');
    const category = catSel ? catSel.value : '';
    const link = document.getElementById('chLink').value.trim();
    fetch(apiBase + '&api=add_chapter', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title, category, link })
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) {
            window.location.href = '?work=' + encodeURIComponent(workId) + '&ch=' + encodeURIComponent(d.chapter.id);
        } else {
            showToast(d.error || '新增失敗', true);
        }
    });
}

function deleteChapter(chId, chTitle) {
    if (!confirm('確定刪除「' + chTitle + '」？\n所有頁面將一併刪除！')) return;
    fetch(apiBase + '&api=delete_chapter&ch=' + encodeURIComponent(chId), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: '{}'
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) window.location.href = '?work=' + encodeURIComponent(workId);
        else showToast(d.error || '刪除失敗', true);
    });
}

// ── Pages ──
if (activeChId && dropZone) {

async function loadList() {
    const res = await fetch(apiBase + '&api=page_list&ch=' + encodeURIComponent(activeChId));
    const data = await res.json();
    order = data.order;
    renderGrid();
}

function thumbUrl(file) {
    return './?api=thumb&path=' + encodeURIComponent(activeChDir + '/' + file);
}

function updateBatchBar() {
    const bar = document.getElementById('batchBar');
    const info = document.getElementById('selInfo');
    if (selected.size > 0) {
        bar.classList.add('show');
        info.textContent = '已選 ' + selected.size + ' 頁';
    } else {
        bar.classList.remove('show');
    }
}

function toggleSelect(file, card) {
    if (selected.has(file)) { selected.delete(file); card.classList.remove('selected'); }
    else { selected.add(file); card.classList.add('selected'); }
    updateBatchBar();
}

function selectAll() {
    selected = new Set(order);
    document.querySelectorAll('.card').forEach(c => c.classList.add('selected'));
    updateBatchBar();
}

function selectNone() {
    selected.clear();
    document.querySelectorAll('.card').forEach(c => c.classList.remove('selected'));
    updateBatchBar();
}

async function batchDelete() {
    if (selected.size === 0) return;
    if (!confirm('確定刪除選取的 ' + selected.size + ' 頁？')) return;
    const files = [...selected];
    const res = await fetch(apiBase + '&api=delete_pages&ch=' + encodeURIComponent(activeChId), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ files })
    });
    const d = await res.json();
    if (d.ok) {
        showToast('已刪除 ' + d.deleted + ' 頁');
        selected.clear();
        updateBatchBar();
        loadList();
    } else {
        showToast(d.error || '刪除失敗', true);
    }
}

function renderGrid() {
    pageCount.textContent = '共 ' + order.length + ' 頁';
    grid.innerHTML = '';
    order.forEach((file, i) => {
        const card = document.createElement('div');
        card.className = 'card' + (selected.has(file) ? ' selected' : '');
        card.draggable = true;
        card.dataset.index = i;
        card.dataset.file = file;
        const imgPath = activeChDir + '/' + file;
        card.innerHTML =
            '<div class="check">✓</div>' +
            '<div class="actions">' +
                '<button class="btn-del" title="刪除">&times;</button>' +
                '<button class="btn-cover" title="設為封面">★</button>' +
            '</div>' +
            '<img src="' + thumbUrl(file) + '" loading="lazy" alt="p' + (i+1) + '">' +
            '<div class="page-num">' + (i+1) + '</div>' +
            '<div class="fname" title="' + file + '">' + file + '</div>';

        card.querySelector('.check').addEventListener('click', e => {
            e.stopPropagation();
            toggleSelect(file, card);
        });
        card.querySelector('.btn-del').addEventListener('click', e => {
            e.stopPropagation();
            if (!confirm('刪除第 ' + (i+1) + ' 頁？\n' + file)) return;
            deletePage(file);
        });
        card.querySelector('.btn-cover').addEventListener('click', e => {
            e.stopPropagation();
            setPageAsCover(imgPath);
        });

        card.addEventListener('click', e => {
            if (e.target.closest('.actions') || e.target.closest('.check')) return;
            if (selected.size > 0) { toggleSelect(file, card); return; }
        });

        card.addEventListener('dragstart', e => {
            if (selected.size > 0) { e.preventDefault(); return; }
            dragSrcIndex = i; card.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move';
        });
        card.addEventListener('dragend', () => { card.classList.remove('dragging'); document.querySelectorAll('.card').forEach(c => c.classList.remove('drag-over')); });
        card.addEventListener('dragover', e => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; card.classList.add('drag-over'); });
        card.addEventListener('dragleave', () => card.classList.remove('drag-over'));
        card.addEventListener('drop', e => {
            e.preventDefault();
            card.classList.remove('drag-over');
            const ti = parseInt(card.dataset.index);
            if (dragSrcIndex === null || dragSrcIndex === ti) return;
            const item = order.splice(dragSrcIndex, 1)[0];
            order.splice(ti, 0, item);
            dragSrcIndex = null;
            renderGrid();
            saveOrder();
        });

        grid.appendChild(card);
    });
    updateBatchBar();
}

async function saveOrder() {
    const res = await fetch(apiBase + '&api=reorder&ch=' + encodeURIComponent(activeChId), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order })
    });
    const d = await res.json();
    if (d.ok) showToast('排序已儲存');
    else showToast('排序儲存失敗', true);
}

async function deletePage(file) {
    const res = await fetch(apiBase + '&api=delete_page&ch=' + encodeURIComponent(activeChId), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ file })
    });
    const d = await res.json();
    if (d.ok) { showToast('已刪除'); loadList(); }
    else showToast(d.error || '刪除失敗', true);
}

function uploadFiles(files) {
    if (!files.length) return;
    const fd = new FormData();
    for (const f of files) fd.append('images[]', f);
    const xhr = new XMLHttpRequest();
    progressBar.style.display = 'block';
    progressFill.style.width = '0%';
    progressLabel.textContent = '上傳中...';
    xhr.upload.addEventListener('progress', e => {
        if (e.lengthComputable) {
            const pct = Math.round(e.loaded / e.total * 100);
            progressFill.style.width = pct + '%';
            progressLabel.textContent = pct + '%';
        }
    });
    xhr.addEventListener('load', () => {
        progressBar.style.display = 'none';
        try {
            const data = JSON.parse(xhr.responseText);
            const ok = data.results.filter(r => r.ok).length;
            const fail = data.results.filter(r => !r.ok).length;
            let msg = '上傳完成：' + ok + ' 張成功';
            if (fail) msg += '，' + fail + ' 張失敗';
            showToast(msg, fail > 0);
            order = data.order;
            renderGrid();
        } catch(e) { showToast('上傳回應錯誤', true); }
    });
    xhr.addEventListener('error', () => { progressBar.style.display = 'none'; showToast('上傳失敗', true); });
    xhr.open('POST', apiBase + '&api=upload_pages&ch=' + encodeURIComponent(activeChId));
    xhr.send(fd);
}

dropZone.addEventListener('click', () => fileInput.click());
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop', e => { e.preventDefault(); dropZone.classList.remove('dragover'); uploadFiles(e.dataTransfer.files); });
fileInput.addEventListener('change', () => { uploadFiles(fileInput.files); fileInput.value = ''; });

loadList();

} // end if activeChId
</script>
</body>
</html>

<?php else:
// ── Dashboard ──
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>漫畫後台管理</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#1a1a2e;color:#eee;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;padding:20px;max-width:1200px;margin:0 auto}

.top-bar{display:flex;align-items:center;gap:12px;margin-bottom:24px;flex-wrap:wrap}
.top-bar h1{flex:1;font-size:1.4em}
.top-bar a,.top-bar button{padding:8px 18px;border-radius:6px;border:none;cursor:pointer;font-size:.88em;text-decoration:none;color:#fff}
.btn-home{background:#2d6a4f}
.btn-home:hover{background:#40916c}
.btn-logout{background:#555}
.btn-logout:hover{background:#777}

.work-list{display:flex;flex-direction:column;gap:10px}
.work-row{display:flex;align-items:center;gap:14px;background:#16213e;border-radius:10px;padding:10px 14px;transition:box-shadow .15s,background .15s;position:relative}
.work-row:hover{background:#1a2744;box-shadow:0 4px 16px rgba(0,0,0,.25)}
.work-row.dragging{opacity:.4}
.work-row.drag-over{box-shadow:0 0 0 2px #e94560}
.drag-handle{cursor:grab;color:#555;font-size:1.2em;padding:4px 2px;flex-shrink:0;user-select:none;touch-action:none}
.drag-handle:active{cursor:grabbing}
.work-thumb{width:56px;height:75px;border-radius:6px;overflow:hidden;background:#0a0a1a;flex-shrink:0;display:flex;align-items:center;justify-content:center}
.work-thumb img{width:100%;height:100%;object-fit:cover}
.work-thumb .placeholder{font-size:1.4em;color:#333}
.work-body{flex:1;min-width:0}
.work-body h3{font-size:.95em;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.work-body .meta{font-size:.78em;color:#888}
.work-body .status{display:inline-block;padding:1px 8px;border-radius:4px;font-size:.7em;background:#333;margin-top:3px}
.work-row-link{display:flex;align-items:center;gap:14px;text-decoration:none;color:inherit;flex:1;min-width:0}
.work-actions{display:flex;gap:6px;flex-shrink:0}
.work-del-btn{width:30px;height:30px;border:none;border-radius:50%;background:rgba(233,69,96,.7);color:#fff;cursor:pointer;font-size:.82em;display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .15s}
.work-row:hover .work-del-btn{opacity:1}
@media(max-width:600px){
    .work-row{padding:8px 10px;gap:10px}
    .work-thumb{width:48px;height:64px}
    .work-body h3{font-size:.88em}
    .work-del-btn{opacity:.7}
}
.toast{position:fixed;bottom:30px;left:50%;transform:translateX(-50%);background:#2d6a4f;color:#fff;padding:10px 28px;border-radius:8px;font-size:.92em;display:none;z-index:999}
.toast.error{background:#e94560}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:1000;justify-content:center;align-items:center}
.modal-overlay.show{display:flex}
.modal{background:#16213e;padding:24px;border-radius:12px;min-width:300px}
.modal h3{margin-bottom:16px}
.modal input{width:100%;padding:10px;border:1px solid #444;border-radius:6px;background:#0f3460;color:#eee;font-size:1em;margin-bottom:12px}
.modal .modal-btns{display:flex;gap:10px;justify-content:flex-end}
.modal .modal-btns button{padding:8px 20px;border:none;border-radius:6px;cursor:pointer;font-size:.9em;color:#fff}
.modal .btn-ok{background:#e94560}
.modal .btn-cancel{background:#555}
</style>
</head>
<body>

<div class="top-bar">
    <h1>漫畫後台管理</h1>
    <button onclick="showCreateWork()" style="padding:8px 18px;border-radius:6px;border:none;cursor:pointer;font-size:.88em;color:#fff;background:#e94560">+ 新增漫畫</button>
    <a href="<?= BASE_URL ?>/" target="_blank" class="btn-home">前台首頁</a>
    <form method="post" style="display:inline"><button name="logout" value="1" class="btn-logout">登出</button></form>
</div>

<?php
    $unlock_file = BASE_PATH . '/data/unlock.json';
    $unlock_data = file_exists($unlock_file) ? (json_decode(file_get_contents($unlock_file), true) ?: []) : [];
    $unlock_codes = $unlock_data['codes'] ?? (isset($unlock_data['code']) ? [$unlock_data['code']] : []);
    $unlock_enabled = !empty($unlock_data['enabled']);
?>
<div style="background:#16213e;border-radius:10px;padding:16px 20px;margin-bottom:20px">
    <h3 style="font-size:.95em;color:#ccc;margin-bottom:10px;border-bottom:1px solid #333;padding-bottom:8px">場次解鎖密語</h3>
    <p style="font-size:.78em;color:#888;margin-bottom:12px">設定通關密語（每行一組，不分大小寫），到場次攤位的讀者輸入任一組即可在 App 中解鎖同人作品</p>
    <div style="display:flex;flex-direction:column;gap:10px">
        <textarea id="unlockCodesInput" rows="3" placeholder="每行一組密語" style="width:100%;padding:10px;border:1px solid #444;border-radius:6px;background:#0f3460;color:#eee;font-size:.95em;font-family:inherit;resize:vertical"><?= htmlspecialchars(implode("\n", $unlock_codes)) ?></textarea>
        <div style="display:flex;gap:10px;align-items:center">
            <label style="display:flex;align-items:center;gap:6px;font-size:.88em;color:#aaa;cursor:pointer;white-space:nowrap">
                <input type="checkbox" id="unlockEnabledCb" <?= $unlock_enabled ? 'checked' : '' ?> style="accent-color:#e94560"> 啟用
            </label>
            <button onclick="saveUnlockCode()" style="padding:8px 20px;border:none;border-radius:6px;background:#e94560;color:#fff;cursor:pointer;font-size:.88em;white-space:nowrap">儲存密語</button>
        </div>
    </div>
</div>

<?php
    $ver_file = BASE_PATH . '/data/app_version.json';
    $ver_data = file_exists($ver_file) ? (json_decode(file_get_contents($ver_file), true) ?: []) : [];
?>
<div style="background:#16213e;border-radius:10px;padding:16px 20px;margin-bottom:20px">
    <h3 style="font-size:.95em;color:#ccc;margin-bottom:10px;border-bottom:1px solid #333;padding-bottom:8px">App 版本管理</h3>
    <p style="font-size:.78em;color:#888;margin-bottom:12px">設定最新 App 版本資訊，使用者開啟舊版 App 時會收到更新提示</p>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
        <div>
            <label style="font-size:.78em;color:#888">版本號碼 (version_code)</label>
            <input type="number" id="verCode" value="<?= (int)($ver_data['version_code'] ?? 0) ?>" min="1" style="width:100%;padding:8px 10px;border:1px solid #444;border-radius:6px;background:#0f3460;color:#eee;font-size:.95em">
        </div>
        <div>
            <label style="font-size:.78em;color:#888">版本名稱</label>
            <input type="text" id="verName" value="<?= htmlspecialchars($ver_data['version_name'] ?? '') ?>" placeholder="例：3.0" style="width:100%;padding:8px 10px;border:1px solid #444;border-radius:6px;background:#0f3460;color:#eee;font-size:.95em">
        </div>
    </div>
    <div style="margin-bottom:10px">
        <label style="font-size:.78em;color:#888">下載連結</label>
        <input type="text" id="verUrl" value="<?= htmlspecialchars($ver_data['download_url'] ?? '') ?>" placeholder="https://stvfu.com/comic/mobile/download/xxx.apk" style="width:100%;padding:8px 10px;border:1px solid #444;border-radius:6px;background:#0f3460;color:#eee;font-size:.95em">
    </div>
    <div style="margin-bottom:10px">
        <label style="font-size:.78em;color:#888">更新說明</label>
        <input type="text" id="verChangelog" value="<?= htmlspecialchars($ver_data['changelog'] ?? '') ?>" placeholder="這個版本做了什麼改動" style="width:100%;padding:8px 10px;border:1px solid #444;border-radius:6px;background:#0f3460;color:#eee;font-size:.95em">
    </div>
    <div style="display:flex;gap:10px;align-items:center">
        <label style="display:flex;align-items:center;gap:6px;font-size:.88em;color:#aaa;cursor:pointer;white-space:nowrap">
            <input type="checkbox" id="verRequired" <?= !empty($ver_data['required']) ? 'checked' : '' ?> style="accent-color:#e94560"> 強制更新
        </label>
        <button onclick="saveAppVersion()" style="padding:8px 20px;border:none;border-radius:6px;background:#e94560;color:#fff;cursor:pointer;font-size:.88em;white-space:nowrap">儲存版本</button>
        <span id="verMsg" style="font-size:.82em;color:#6a6"></span>
    </div>
</div>

<div class="work-list" id="workList">
<?php foreach ($data as $id => $m): ?>
    <div class="work-row" draggable="true" data-id="<?= htmlspecialchars($id) ?>">
        <span class="drag-handle" title="拖曳排序">☰</span>
        <a href="?work=<?= urlencode($id) ?>" class="work-row-link">
            <div class="work-thumb">
                <?php if (!empty($m['cover']) && file_exists(BASE_PATH . '/' . $m['cover'])): ?>
                    <img src="<?= BASE_URL . '/' . htmlspecialchars($m['cover']) ?>" alt="<?= htmlspecialchars($m['title']) ?>">
                <?php else: ?>
                    <span class="placeholder"><?= mb_substr($m['title'], 0, 1) ?></span>
                <?php endif; ?>
            </div>
            <div class="work-body">
                <h3><?= htmlspecialchars($m['title']) ?></h3>
                <div class="meta">
                    <?= count($m['chapters'] ?? []) ?> 章節
                    <?php if (!empty($m['updated'])): ?> · <?= htmlspecialchars($m['updated']) ?><?php endif; ?>
                </div>
                <span class="status"><?= htmlspecialchars($m['status'] ?? '連載中') ?></span>
                <span class="status" style="background:<?= ($m['type'] ?? 'doujin') === 'original' ? '#2d6a4f' : '#6a4c93' ?>"><?= ($m['type'] ?? 'doujin') === 'original' ? '原創' : '同人' ?></span>
            </div>
        </a>
        <div class="work-actions">
            <button class="work-del-btn" onclick="event.stopPropagation();deleteWork('<?= htmlspecialchars($id) ?>','<?= htmlspecialchars($m['title']) ?>')" title="刪除作品">✕</button>
        </div>
    </div>
<?php endforeach; ?>
</div>

<div class="toast" id="toast"></div>

<!-- Create Work Modal -->
<div class="modal-overlay" id="createModal">
    <div class="modal" style="min-width:360px">
        <h3>新增漫畫</h3>
        <label style="font-size:.85em;color:#aaa;display:block;margin-bottom:4px">作品 ID（英文小寫/數字/底線）</label>
        <input type="text" id="newWorkId" placeholder="例：my_comic" pattern="[a-z0-9_]+" style="margin-bottom:8px">
        <label style="font-size:.85em;color:#aaa;display:block;margin-bottom:4px">作品名稱</label>
        <input type="text" id="newWorkTitle" placeholder="例：我的漫畫">
        <label style="font-size:.85em;color:#aaa;display:block;margin-bottom:4px">簡介（選填）</label>
        <input type="text" id="newWorkDesc" placeholder="作品簡介">
        <label style="font-size:.85em;color:#aaa;display:block;margin-bottom:4px">類型</label>
        <select id="newWorkType" style="width:100%;padding:10px;border:1px solid #444;border-radius:6px;background:#0f3460;color:#eee;font-size:1em;margin-bottom:12px">
            <option value="original">原創（App 可見）</option>
            <option value="doujin" selected>同人（僅網頁版）</option>
        </select>
        <label style="font-size:.85em;color:#aaa;display:block;margin-bottom:4px">狀態</label>
        <select id="newWorkStatus" style="width:100%;padding:10px;border:1px solid #444;border-radius:6px;background:#0f3460;color:#eee;font-size:1em;margin-bottom:12px">
            <option value="連載中">連載中</option>
            <option value="已完結">已完結</option>
            <option value="休刊中">休刊中</option>
        </select>
        <div class="modal-btns">
            <button class="btn-cancel" onclick="document.getElementById('createModal').classList.remove('show')">取消</button>
            <button class="btn-ok" onclick="createWork()">新增</button>
        </div>
    </div>
</div>

<script>
const toast = document.getElementById('toast');
function showToast(msg, isError) {
    toast.textContent = msg;
    toast.className = 'toast' + (isError ? ' error' : '');
    toast.style.display = 'block';
    setTimeout(() => toast.style.display = 'none', 3000);
}

function saveUnlockCode() {
    const raw = document.getElementById('unlockCodesInput').value;
    const codes = raw.split('\n').map(s => s.trim()).filter(Boolean);
    const enabled = document.getElementById('unlockEnabledCb').checked;
    fetch('./?api=save_unlock', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ codes, enabled })
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) showToast('密語已儲存');
        else showToast(d.error || '儲存失敗', true);
    })
    .catch(() => showToast('網路錯誤', true));
}

function saveAppVersion() {
    const data = {
        version_code: parseInt(document.getElementById('verCode').value) || 0,
        version_name: document.getElementById('verName').value.trim(),
        download_url: document.getElementById('verUrl').value.trim(),
        changelog: document.getElementById('verChangelog').value.trim(),
        required: document.getElementById('verRequired').checked
    };
    const msg = document.getElementById('verMsg');
    fetch('./?api=save_app_version', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) { msg.textContent = '已儲存'; msg.style.color = '#6a6'; }
        else { msg.textContent = d.error || '儲存失敗'; msg.style.color = '#e94560'; }
        setTimeout(() => { msg.textContent = ''; }, 3000);
    })
    .catch(() => { msg.textContent = '網路錯誤'; msg.style.color = '#e94560'; });
}

function showCreateWork() {
    document.getElementById('createModal').classList.add('show');
    document.getElementById('newWorkId').value = '';
    document.getElementById('newWorkTitle').value = '';
    document.getElementById('newWorkDesc').value = '';
    document.getElementById('newWorkId').focus();
}

function createWork() {
    const id = document.getElementById('newWorkId').value.trim();
    const title = document.getElementById('newWorkTitle').value.trim();
    const description = document.getElementById('newWorkDesc').value.trim();
    const type = document.getElementById('newWorkType').value;
    const status = document.getElementById('newWorkStatus').value;
    if (!id || !title) { showToast('請填寫 ID 和名稱', true); return; }
    if (!/^[a-z0-9_]+$/.test(id)) { showToast('ID 只能使用小寫英文、數字和底線', true); return; }
    fetch('./?api=create_work', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, title, description, type, status })
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) window.location.href = '?work=' + encodeURIComponent(d.id);
        else showToast(d.error || '新增失敗', true);
    })
    .catch(() => showToast('網路錯誤', true));
}

function deleteWork(id, title) {
    if (!confirm('確定要刪除「' + title + '」？\n所有章節和頁面將一併刪除，此操作無法復原！')) return;
    if (!confirm('再次確認：真的要刪除「' + title + '」嗎？')) return;
    fetch('./?api=delete_work', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) { showToast('已刪除'); setTimeout(() => location.reload(), 500); }
        else showToast(d.error || '刪除失敗', true);
    })
    .catch(() => showToast('網路錯誤', true));
}

document.getElementById('newWorkId').addEventListener('keydown', e => { if (e.key === 'Enter') document.getElementById('newWorkTitle').focus(); });
document.getElementById('newWorkTitle').addEventListener('keydown', e => { if (e.key === 'Enter') createWork(); });

// ── Drag reorder works ──
(function() {
    const list = document.getElementById('workList');
    if (!list) return;
    let dragEl = null;
    let touchY = 0;
    let touchClone = null;

    // Desktop drag
    list.addEventListener('dragstart', e => {
        const row = e.target.closest('.work-row');
        if (!row || !e.target.closest('.drag-handle')) { e.preventDefault(); return; }
        dragEl = row;
        row.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
    });
    list.addEventListener('dragend', e => {
        if (dragEl) dragEl.classList.remove('dragging');
        list.querySelectorAll('.work-row').forEach(r => r.classList.remove('drag-over'));
        dragEl = null;
    });
    list.addEventListener('dragover', e => {
        e.preventDefault();
        const row = e.target.closest('.work-row');
        if (!row || row === dragEl) return;
        list.querySelectorAll('.work-row').forEach(r => r.classList.remove('drag-over'));
        row.classList.add('drag-over');
    });
    list.addEventListener('dragleave', e => {
        const row = e.target.closest('.work-row');
        if (row) row.classList.remove('drag-over');
    });
    list.addEventListener('drop', e => {
        e.preventDefault();
        const target = e.target.closest('.work-row');
        if (!target || !dragEl || target === dragEl) return;
        target.classList.remove('drag-over');
        const rows = [...list.querySelectorAll('.work-row')];
        const fi = rows.indexOf(dragEl), ti = rows.indexOf(target);
        if (fi < ti) target.after(dragEl);
        else target.before(dragEl);
        saveWorkOrder();
    });

    // Touch drag (mobile)
    list.addEventListener('touchstart', e => {
        const handle = e.target.closest('.drag-handle');
        if (!handle) return;
        const row = handle.closest('.work-row');
        if (!row) return;
        e.preventDefault();
        dragEl = row;
        touchY = e.touches[0].clientY;
        row.style.opacity = '0.5';
    }, { passive: false });

    list.addEventListener('touchmove', e => {
        if (!dragEl) return;
        e.preventDefault();
        const y = e.touches[0].clientY;
        const el = document.elementFromPoint(e.touches[0].clientX, y);
        const target = el ? el.closest('.work-row') : null;
        list.querySelectorAll('.work-row').forEach(r => r.classList.remove('drag-over'));
        if (target && target !== dragEl) target.classList.add('drag-over');
    }, { passive: false });

    list.addEventListener('touchend', e => {
        if (!dragEl) return;
        dragEl.style.opacity = '';
        const overRow = list.querySelector('.work-row.drag-over');
        if (overRow && overRow !== dragEl) {
            const rows = [...list.querySelectorAll('.work-row')];
            const fi = rows.indexOf(dragEl), ti = rows.indexOf(overRow);
            if (fi < ti) overRow.after(dragEl);
            else overRow.before(dragEl);
            saveWorkOrder();
        }
        list.querySelectorAll('.work-row').forEach(r => r.classList.remove('drag-over'));
        dragEl = null;
    });

    function saveWorkOrder() {
        const ids = [...list.querySelectorAll('.work-row')].map(r => r.dataset.id);
        fetch('./?api=reorder_works', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order: ids })
        })
        .then(r => r.json())
        .then(d => {
            if (d.ok) showToast('排序已儲存');
            else showToast('排序儲存失敗', true);
        })
        .catch(() => showToast('網路錯誤', true));
    }
})();
</script>

</body>
</html>
<?php endif; ?>
