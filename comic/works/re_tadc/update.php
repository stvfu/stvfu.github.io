<?php
session_start();
require '/var/www/admin_credentials.php';

const IMAGE_DIR = __DIR__ . '/images/';
const ORDER_FILE = __DIR__ . '/page_order.json';
const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB per file

// --- Auth ---
if (isset($_POST['logout'])) {
    unset($_SESSION['comic_admin']);
    header('Location: update.php');
    exit;
}

if (isset($_POST['username'], $_POST['password'])) {
    if ($_POST['username'] === ADMIN_USER && $_POST['password'] === ADMIN_PASS) {
        $_SESSION['comic_admin'] = true;
    } else {
        $login_error = '帳號或密碼錯誤';
    }
}

if (empty($_SESSION['comic_admin'])) {
    show_login($login_error ?? null);
    exit;
}

// --- Handle AJAX actions ---
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $results = [];
    $order = load_order();

    if (!empty($_FILES['images'])) {
        $count = count($_FILES['images']['name']);
        for ($i = 0; $i < $count; $i++) {
            $name = $_FILES['images']['name'][$i];
            $tmp = $_FILES['images']['tmp_name'][$i];
            $error = $_FILES['images']['error'][$i];
            $size = $_FILES['images']['size'][$i];

            if ($error !== UPLOAD_ERR_OK) {
                $results[] = ['name' => $name, 'ok' => false, 'msg' => 'Upload error code: ' . $error];
                continue;
            }
            if ($size > MAX_FILE_SIZE) {
                $results[] = ['name' => $name, 'ok' => false, 'msg' => '檔案超過 50MB'];
                continue;
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ALLOWED_EXT)) {
                $results[] = ['name' => $name, 'ok' => false, 'msg' => '不支援的格式'];
                continue;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $tmp);
            finfo_close($finfo);
            if (!str_starts_with($mime, 'image/')) {
                $results[] = ['name' => $name, 'ok' => false, 'msg' => '非圖片檔案'];
                continue;
            }

            $safe_name = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $name);
            $dest = IMAGE_DIR . $safe_name;

            // avoid overwrite
            if (file_exists($dest)) {
                $base = pathinfo($safe_name, PATHINFO_FILENAME);
                $safe_name = $base . '_' . time() . '.' . $ext;
                $dest = IMAGE_DIR . $safe_name;
            }

            if (move_uploaded_file($tmp, $dest)) {
                chmod($dest, 0644);
                if (!in_array($safe_name, $order)) {
                    $order[] = $safe_name;
                }
                $results[] = ['name' => $safe_name, 'ok' => true];
            } else {
                $results[] = ['name' => $name, 'ok' => false, 'msg' => '儲存失敗'];
            }
        }
    }

    save_order($order);
    echo json_encode(['results' => $results, 'order' => $order]);
    exit;
}

if ($action === 'reorder' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    if (!empty($input['order']) && is_array($input['order'])) {
        $sanitized = array_filter($input['order'], function ($f) {
            return file_exists(IMAGE_DIR . basename($f));
        });
        save_order(array_values($sanitized));
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Invalid data']);
    }
    exit;
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $file = basename($input['file'] ?? '');
    $path = IMAGE_DIR . $file;
    if ($file && file_exists($path)) {
        unlink($path);
        $order = load_order();
        $order = array_values(array_filter($order, fn($f) => $f !== $file));
        save_order($order);
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'msg' => '檔案不存在']);
    }
    exit;
}

if ($action === 'list') {
    header('Content-Type: application/json');
    echo json_encode(['order' => load_order()]);
    exit;
}

if ($action === 'thumb' && isset($_GET['f'])) {
    $file = basename($_GET['f']);
    $path = IMAGE_DIR . $file;
    if (!file_exists($path)) {
        http_response_code(404);
        exit;
    }
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mime_map = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    $mime = $mime_map[$ext] ?? 'image/jpeg';

    $thumb_dir = IMAGE_DIR . '.thumbs/';
    if (!is_dir($thumb_dir)) {
        mkdir($thumb_dir, 0755, true);
    }
    $thumb_path = $thumb_dir . $file;

    if (!file_exists($thumb_path) || filemtime($thumb_path) < filemtime($path)) {
        $src = match ($ext) {
            'png' => imagecreatefrompng($path),
            'gif' => imagecreatefromgif($path),
            'webp' => imagecreatefromwebp($path),
            default => imagecreatefromjpeg($path),
        };
        if (!$src) {
            readfile($path);
            exit;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $max = 200;
        $ratio = min($max / $w, $max / $h, 1);
        $nw = (int)($w * $ratio);
        $nh = (int)($h * $ratio);
        $dst = imagecreatetruecolor($nw, $nh);

        if ($ext === 'png' || $ext === 'webp') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagejpeg($dst, $thumb_path, 80);
        imagedestroy($src);
        imagedestroy($dst);
    }

    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=86400');
    readfile($thumb_path);
    exit;
}

// --- Helper functions ---
function load_order(): array {
    if (!file_exists(ORDER_FILE)) return scan_images();
    $data = json_decode(file_get_contents(ORDER_FILE), true);
    if (!is_array($data)) return scan_images();
    // remove entries whose file no longer exists, add new files
    $existing = scan_images();
    $data = array_values(array_filter($data, fn($f) => in_array($f, $existing)));
    $missing = array_diff($existing, $data);
    return array_merge($data, $missing);
}

function scan_images(): array {
    $files = [];
    foreach (glob(IMAGE_DIR . '*') as $path) {
        $name = basename($path);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext, ALLOWED_EXT)) {
            $files[] = $name;
        }
    }
    sort($files, SORT_NATURAL);
    return $files;
}

function save_order(array $order): void {
    file_put_contents(ORDER_FILE, json_encode($order, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function show_login(?string $error): void {
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>漫畫後台登入</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #1a1a2e; color: #eee; font-family: sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
.login-box { background: #16213e; padding: 40px; border-radius: 12px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,.5); }
.login-box h1 { margin-bottom: 20px; font-size: 1.4em; }
.login-box input[type=text], .login-box input[type=password] { padding: 10px 16px; font-size: 1em; border: 1px solid #444; border-radius: 6px; background: #0f3460; color: #eee; width: 220px; margin-bottom: 8px; }
.login-box button { margin-top: 12px; padding: 10px 32px; border: none; border-radius: 6px; background: #e94560; color: #fff; font-size: 1em; cursor: pointer; }
.login-box button:hover { background: #c73e54; }
.error { color: #e94560; margin-top: 10px; }
</style>
</head>
<body>
<form class="login-box" method="post">
<h1>漫畫管理後台</h1>
<input type="text" name="username" placeholder="帳號" autofocus required>
<br><input type="password" name="password" placeholder="密碼" required>
<br><button type="submit">登入</button>
<?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
</form>
</body>
</html>
<?php } ?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>漫畫管理後台</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #1a1a2e; color: #eee; font-family: sans-serif; padding: 20px; }
h1 { text-align: center; margin-bottom: 8px; }

.toolbar { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; align-items: center; margin-bottom: 20px; padding: 16px; background: #16213e; border-radius: 10px; }
.toolbar a, .toolbar button { padding: 8px 20px; border-radius: 6px; border: none; cursor: pointer; font-size: .9em; text-decoration: none; color: #fff; }
.btn-upload { background: #0f3460; }
.btn-upload:hover { background: #1a4a8a; }
.btn-logout { background: #555; }
.btn-logout:hover { background: #777; }
.btn-danger { background: #e94560; }
.btn-danger:hover { background: #c73e54; }
.btn-preview { background: #2d6a4f; }
.btn-preview:hover { background: #40916c; }

.upload-zone { border: 3px dashed #0f3460; border-radius: 12px; padding: 40px; text-align: center; margin-bottom: 20px; cursor: pointer; transition: border-color .2s, background .2s; }
.upload-zone.dragover { border-color: #e94560; background: rgba(233,69,96,.1); }
.upload-zone p { font-size: 1.1em; margin-bottom: 8px; }
.upload-zone small { color: #888; }

.progress-bar { display: none; margin-bottom: 20px; background: #16213e; border-radius: 8px; overflow: hidden; height: 28px; position: relative; }
.progress-bar .fill { height: 100%; background: linear-gradient(90deg, #0f3460, #e94560); transition: width .3s; }
.progress-bar .label { position: absolute; top: 0; left: 0; right: 0; bottom: 0; display: flex; align-items: center; justify-content: center; font-size: .85em; }

.page-count { text-align: center; margin-bottom: 12px; color: #aaa; }

.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 14px; }
.card { background: #16213e; border-radius: 8px; padding: 8px; text-align: center; cursor: grab; position: relative; user-select: none; transition: transform .15s, box-shadow .15s; }
.card:active { cursor: grabbing; }
.card.dragging { opacity: .4; transform: scale(.95); }
.card.drag-over { box-shadow: 0 0 0 3px #e94560; }
.card img { width: 100%; height: 160px; object-fit: contain; border-radius: 4px; background: #111; }
.card .page-num { display: inline-block; background: #e94560; color: #fff; border-radius: 50%; width: 28px; height: 28px; line-height: 28px; font-size: .8em; font-weight: bold; margin-top: 6px; }
.card .filename { font-size: .7em; color: #888; margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.card .del-btn { position: absolute; top: 4px; right: 4px; background: rgba(233,69,96,.8); color: #fff; border: none; border-radius: 50%; width: 24px; height: 24px; font-size: .8em; cursor: pointer; display: none; line-height: 24px; }
.card:hover .del-btn { display: block; }

.toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); background: #2d6a4f; color: #fff; padding: 10px 28px; border-radius: 8px; font-size: .95em; display: none; z-index: 999; }
.toast.error { background: #e94560; }

#fileInput { display: none; }
</style>
</head>
<body>

<h1>漫畫管理後台</h1>

<div class="toolbar">
    <button class="btn-upload" onclick="document.getElementById('fileInput').click()">選擇圖片上傳</button>
    <a class="btn-preview" href="index.php" target="_blank">前台預覽</a>
    <form method="post" style="display:inline"><button name="logout" value="1" class="btn-logout">登出</button></form>
</div>

<div class="upload-zone" id="dropZone">
    <p>將圖片拖曳到此處上傳</p>
    <small>支援 JPG / PNG / GIF / WebP，單檔最大 50MB，可多選</small>
</div>
<input type="file" id="fileInput" multiple accept="image/*">

<div class="progress-bar" id="progressBar">
    <div class="fill" id="progressFill"></div>
    <div class="label" id="progressLabel">0%</div>
</div>

<p class="page-count" id="pageCount"></p>

<div class="grid" id="grid"></div>

<div class="toast" id="toast"></div>

<script>
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

async function loadList() {
    const res = await fetch('update.php?action=list');
    const data = await res.json();
    order = data.order;
    renderGrid();
}

function renderGrid() {
    pageCount.textContent = `共 ${order.length} 頁`;
    grid.innerHTML = '';
    order.forEach((file, i) => {
        const card = document.createElement('div');
        card.className = 'card';
        card.draggable = true;
        card.dataset.index = i;
        card.innerHTML = `
            <button class="del-btn" title="刪除">&times;</button>
            <img src="update.php?action=thumb&f=${encodeURIComponent(file)}" loading="lazy" alt="page ${i+1}">
            <div class="page-num">${i + 1}</div>
            <div class="filename" title="${file}">${file}</div>
        `;

        card.querySelector('.del-btn').addEventListener('click', (e) => {
            e.stopPropagation();
            if (!confirm(`確定刪除第 ${i+1} 頁？\n${file}`)) return;
            deleteFile(file);
        });

        // Drag events
        card.addEventListener('dragstart', (e) => {
            dragSrcIndex = i;
            card.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
            document.querySelectorAll('.card').forEach(c => c.classList.remove('drag-over'));
        });
        card.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            card.classList.add('drag-over');
        });
        card.addEventListener('dragleave', () => {
            card.classList.remove('drag-over');
        });
        card.addEventListener('drop', (e) => {
            e.preventDefault();
            card.classList.remove('drag-over');
            const targetIndex = parseInt(card.dataset.index);
            if (dragSrcIndex === null || dragSrcIndex === targetIndex) return;
            const item = order.splice(dragSrcIndex, 1)[0];
            order.splice(targetIndex, 0, item);
            dragSrcIndex = null;
            renderGrid();
            saveOrder();
        });

        grid.appendChild(card);
    });
}

async function saveOrder() {
    const res = await fetch('update.php?action=reorder', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order })
    });
    const data = await res.json();
    if (data.ok) showToast('排序已儲存');
    else showToast('排序儲存失敗', true);
}

async function deleteFile(file) {
    const res = await fetch('update.php?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ file })
    });
    const data = await res.json();
    if (data.ok) {
        showToast('已刪除');
        loadList();
    } else {
        showToast(data.msg || '刪除失敗', true);
    }
}

function uploadFiles(files) {
    if (!files.length) return;
    const formData = new FormData();
    for (const f of files) formData.append('images[]', f);

    const xhr = new XMLHttpRequest();
    progressBar.style.display = 'block';
    progressFill.style.width = '0%';
    progressLabel.textContent = '上傳中...';

    xhr.upload.addEventListener('progress', (e) => {
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
            let msg = `上傳完成：${ok} 張成功`;
            if (fail) msg += `，${fail} 張失敗`;
            showToast(msg, fail > 0);
            order = data.order;
            renderGrid();
        } catch (e) {
            showToast('上傳回應解析失敗', true);
        }
    });

    xhr.addEventListener('error', () => {
        progressBar.style.display = 'none';
        showToast('上傳失敗，請檢查網路', true);
    });

    xhr.open('POST', 'update.php?action=upload');
    xhr.send(formData);
}

// Drop zone events
dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('dragover'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    uploadFiles(e.dataTransfer.files);
});
fileInput.addEventListener('change', () => { uploadFiles(fileInput.files); fileInput.value = ''; });

loadList();
</script>
</body>
</html>
