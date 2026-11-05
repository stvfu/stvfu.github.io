<?php
declare(strict_types=1);

session_name('img_upload');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

require '/var/www/admin_credentials.php';

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

// 登入
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && !isset($_POST['imageData'])) {
    if ((string)$_POST['password'] === ADMIN_PASS) {
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
    } else {
        sleep(1);
        $error = "密碼錯誤";
    }
}

// 登出
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header("Location: index.php");
    exit();
}

$ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

// 處理圖片上傳
if (isset($_POST['imageData']) && ($_SESSION['authenticated'] ?? false) === true) {
    header('Content-Type: text/plain; charset=utf-8');
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        http_response_code(403);
        echo "CSRF 驗證失敗，請重新整理頁面";
        exit();
    }
    $filename = basename((string)($_POST['filename'] ?? ''));
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($filename === '' || !in_array($ext, $ALLOWED_EXT, true)) {
        http_response_code(400);
        echo "不允許的檔案類型，僅接受 jpg/png/gif/webp";
        exit();
    }

    if (!is_dir('upload_images')) {
        mkdir('upload_images', 0755, true);
    }

    $imageData = (string)$_POST['imageData'];
    $imageData = preg_replace('#^data:image/\w+;base64,#', '', $imageData);
    $imageData = str_replace(' ', '+', $imageData);
    $data = base64_decode($imageData, true);
    if ($data === false) {
        http_response_code(400);
        echo "無效的圖片資料";
        exit();
    }

    file_put_contents("upload_images/" . $filename, $data);
    echo "成功儲存為 " . htmlspecialchars($filename);
    exit();
}
?>

<?php if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true): ?>
<!-- 登入頁面 -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>登入</title>
</head>
<body>
    <h2>請輸入密碼</h2>
    <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
    <form method="post">
        <input type="password" name="password" autofocus>
        <button type="submit">登入</button>
    </form>
</body>
</html>

<?php else: ?>
<!-- 上傳頁面 -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>圖片上傳工具</title>
    <style>
        #dropArea {
            width: 600px;
            height: 400px;
            border: 2px dashed #aaa;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 20px;
            position: relative;
            background-color: #f9f9f9;
        }
        #dropArea img {
            max-width: 100%;
            max-height: 100%;
        }
        #uploadBtn, #selectBtn {
            margin: 5px;
        }
    </style>
</head>
<body>

<a href="show_images.php">前往圖片瀏覽</a>

<h2>圖片上傳工具</h2>

<div id="dropArea">拖曳圖片到這裡 或 Ctrl+V 貼上</div>
<button id="selectBtn">選擇圖片上傳</button>
<button id="uploadBtn">儲存圖片</button>

<p><a href="?logout=1">登出</a></p>
<input type="hidden" id="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

<input type="file" id="fileInput" accept="image/*" style="display:none;">

<script>
const dropArea = document.getElementById('dropArea');
const fileInput = document.getElementById('fileInput');
let imageBase64 = "";

// 阻止預設行為
['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropArea.addEventListener(eventName, e => e.preventDefault());
});

dropArea.addEventListener('drop', e => {
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) {
        readImage(file);
    }
});

// 貼上圖片
document.addEventListener('paste', e => {
    const items = e.clipboardData.items;
    for (let item of items) {
        if (item.type.startsWith('image/')) {
            const file = item.getAsFile();
            readImage(file);
        }
    }
});

// 選擇圖片上傳
document.getElementById('selectBtn').addEventListener('click', () => {
    fileInput.click();
});

fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    if (file && file.type.startsWith('image/')) {
        readImage(file);
    }
});

// 讀取圖片
function readImage(file) {
    const reader = new FileReader();
    reader.onload = function(evt) {
        dropArea.innerHTML = `<img src="${evt.target.result}">`;
        imageBase64 = evt.target.result;
    }
    reader.readAsDataURL(file);
}

// 儲存圖片
document.getElementById('uploadBtn').addEventListener('click', () => {
    if (!imageBase64) {
        alert("請先選擇、拖曳或貼上圖片");
        return;
    }

    const now = new Date();
    const timestamp = now.getFullYear().toString() +
        String(now.getMonth() + 1).padStart(2, '0') +
        String(now.getDate()).padStart(2, '0') + "_" +
        String(now.getHours()).padStart(2, '0') +
        String(now.getMinutes()).padStart(2, '0') +
        String(now.getSeconds()).padStart(2, '0');

    const suffix = prompt("請輸入檔名後綴 (XXXX):", "image");
    if (suffix === null) return;

    const filename = `${timestamp}_${suffix}.jpg`;

    const formData = new FormData();
    formData.append("imageData", imageBase64);
    formData.append("filename", filename);
    formData.append("csrf", document.getElementById('csrf').value);

    fetch("index.php", {
        method: "POST",
        body: formData
    })
    .then(res => res.text())
    .then(msg => {
        alert(msg);
    })
    .catch(err => {
        alert("上傳失敗");
    });
});
</script>

</body>
</html>
<?php endif; ?>
