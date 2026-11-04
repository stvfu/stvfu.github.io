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
            $new_ch = [
                'id' => $ch_id,
                'title' => $title,
                'dir' => $ch_dir,
                'order' => $order_path,
                'date' => date('Y-m-d'),
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

        case 'save_info':
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['title'])) $data[$work_id]['title'] = trim($input['title']);
            if (isset($input['description'])) $data[$work_id]['description'] = trim($input['description']);
            if (isset($input['status'])) $data[$work_id]['status'] = trim($input['status']);
            if (isset($input['genres'])) $data[$work_id]['genres'] = array_map('trim', (array)$input['genres']);
            save_data($data);
            json_out(['ok' => true]);
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
.card .btn-del{background:rgba(233,69,96,.85)}
.card .btn-cover{background:rgba(45,106,79,.85);font-size:.6em}

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

<!-- Chapters -->
<div class="section">
    <h3>章節管理</h3>
    <div class="ch-tabs">
        <?php foreach ($chapters as $ch): ?>
            <a href="?work=<?= urlencode($work_id) ?>&ch=<?= urlencode($ch['id']) ?>"
               class="ch-tab <?= $ch['id'] === $active_ch ? 'active' : '' ?>">
                <?= htmlspecialchars($ch['title']) ?>
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
        <div class="modal-btns">
            <button class="btn-cancel" onclick="document.getElementById('chModal').classList.remove('show')">取消</button>
            <button class="btn-ok" onclick="addChapter()">新增</button>
        </div>
    </div>
</div>

<script>
const workId = <?= json_encode($work_id) ?>;
const activeChId = <?= json_encode($active_ch) ?>;
const activeChDir = <?= json_encode($active_chapter['dir'] ?? '') ?>;
const apiBase = './?work=' + encodeURIComponent(workId);

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
    fetch(apiBase + '&api=add_chapter', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title })
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

function renderGrid() {
    pageCount.textContent = '共 ' + order.length + ' 頁';
    grid.innerHTML = '';
    order.forEach((file, i) => {
        const card = document.createElement('div');
        card.className = 'card';
        card.draggable = true;
        card.dataset.index = i;
        const imgPath = activeChDir + '/' + file;
        card.innerHTML =
            '<div class="actions">' +
                '<button class="btn-del" title="刪除">&times;</button>' +
                '<button class="btn-cover" title="設為封面">★</button>' +
            '</div>' +
            '<img src="' + thumbUrl(file) + '" loading="lazy" alt="p' + (i+1) + '">' +
            '<div class="page-num">' + (i+1) + '</div>' +
            '<div class="fname" title="' + file + '">' + file + '</div>';

        card.querySelector('.btn-del').addEventListener('click', e => {
            e.stopPropagation();
            if (!confirm('刪除第 ' + (i+1) + ' 頁？\n' + file)) return;
            deletePage(file);
        });
        card.querySelector('.btn-cover').addEventListener('click', e => {
            e.stopPropagation();
            setPageAsCover(imgPath);
        });

        card.addEventListener('dragstart', e => { dragSrcIndex = i; card.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; });
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

.work-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:20px}
.work-card{background:#16213e;border-radius:10px;overflow:hidden;transition:transform .15s,box-shadow .15s;cursor:pointer;text-decoration:none;color:inherit;display:block}
.work-card:hover{transform:translateY(-3px);box-shadow:0 6px 20px rgba(0,0,0,.3)}
.work-cover{width:100%;height:260px;background:#0a0a1a;display:flex;align-items:center;justify-content:center;overflow:hidden}
.work-cover img{width:100%;height:100%;object-fit:cover}
.work-cover .placeholder{font-size:3em;color:#333}
.work-info{padding:14px}
.work-info h3{font-size:1.05em;margin-bottom:4px}
.work-info .meta{font-size:.8em;color:#888}
.work-info .status{display:inline-block;padding:2px 8px;border-radius:4px;font-size:.75em;background:#333;margin-top:6px}
</style>
</head>
<body>

<div class="top-bar">
    <h1>漫畫後台管理</h1>
    <a href="<?= BASE_URL ?>/" target="_blank" class="btn-home">前台首頁</a>
    <form method="post" style="display:inline"><button name="logout" value="1" class="btn-logout">登出</button></form>
</div>

<div class="work-grid">
<?php foreach ($data as $id => $m): ?>
    <a href="?work=<?= urlencode($id) ?>" class="work-card">
        <div class="work-cover">
            <?php if (!empty($m['cover']) && file_exists(BASE_PATH . '/' . $m['cover'])): ?>
                <img src="<?= BASE_URL . '/' . htmlspecialchars($m['cover']) ?>" alt="<?= htmlspecialchars($m['title']) ?>">
            <?php else: ?>
                <span class="placeholder"><?= mb_substr($m['title'], 0, 1) ?></span>
            <?php endif; ?>
        </div>
        <div class="work-info">
            <h3><?= htmlspecialchars($m['title']) ?></h3>
            <div class="meta">
                <?= count($m['chapters'] ?? []) ?> 章節
                <?php if (!empty($m['updated'])): ?> · <?= htmlspecialchars($m['updated']) ?><?php endif; ?>
            </div>
            <span class="status"><?= htmlspecialchars($m['status'] ?? '連載中') ?></span>
        </div>
    </a>
<?php endforeach; ?>
</div>

</body>
</html>
<?php endif; ?>
