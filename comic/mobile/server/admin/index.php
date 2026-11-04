<?php
session_start();
require_once __DIR__ . '/../config.php';
$ADMIN_PASS = ADMIN_PASS;

if (!isset($_SESSION['admin_ok'])) {
    if (($_POST['pass'] ?? '') === $ADMIN_PASS) {
        $_SESSION['admin_ok'] = true;
    } else {
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
        <title>管理登入</title><style>body{font-family:sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;background:#f5f5f5;}
        form{background:#fff;padding:30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.1);}
        input{display:block;margin:10px 0;padding:10px;width:200px;border:1px solid #ddd;border-radius:5px;}
        button{background:#E91E63;color:#fff;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;}</style></head>
        <body><form method="post"><h3>管理後台登入</h3><input type="password" name="pass" placeholder="密碼"><button>登入</button></form></body></html>';
        exit;
    }
}

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/fcm_send.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'post_update') {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $category = $_POST['category'] ?? 'update';
        $push = isset($_POST['push']);

        if ($title && $body) {
            $stmt = $db->prepare("INSERT INTO updates (title, body, category) VALUES (:t, :b, :c)");
            $stmt->bindValue(':t', $title, SQLITE3_TEXT);
            $stmt->bindValue(':b', $body, SQLITE3_TEXT);
            $stmt->bindValue(':c', $category, SQLITE3_TEXT);
            $stmt->execute();

            $msg = '✅ 已發佈';

            if ($push) {
                $result = sendToAllDevices($title, $body);
                $msg .= "，推播已送出（成功 {$result['sent']}，失敗 {$result['failed']}）";
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->exec("DELETE FROM updates WHERE id = $id");
            $msg = '已刪除';
        }
    }
}

$tokenCount = $db->querySingle("SELECT COUNT(*) FROM fcm_tokens");
$updates = $db->query("SELECT * FROM updates ORDER BY created_at DESC LIMIT 50");
$updateList = [];
while ($row = $updates->fetchArray(SQLITE3_ASSOC)) {
    $updateList[] = $row;
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>猛少女之拳 — 管理後台</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, 'Noto Sans TC', sans-serif; background: #f5f5f5; }
.nav { background: #333; color: #fff; padding: 14px 20px; font-size: 16px; }
.container { max-width: 700px; margin: 20px auto; padding: 0 16px; }
.stat { background: #fff; padding: 16px; border-radius: 8px; margin-bottom: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,.1); }
.stat span { font-size: 28px; font-weight: bold; color: #E91E63; }
.form-box { background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,.1); }
.form-box h3 { margin-bottom: 12px; }
label { display: block; margin: 10px 0 4px; font-size: 14px; color: #555; }
input[type=text], textarea, select { width: 100%; padding: 10px; border: 1px solid #ddd;
    border-radius: 6px; font-size: 14px; }
textarea { height: 100px; resize: vertical; }
.checkbox-row { margin: 12px 0; }
.checkbox-row label { display: inline; margin-left: 6px; }
.btn { background: #E91E63; color: #fff; border: none; padding: 12px 24px; border-radius: 6px;
       cursor: pointer; font-size: 15px; margin-top: 10px; }
.btn:hover { background: #C2185B; }
.msg { background: #E8F5E9; color: #2E7D32; padding: 12px; border-radius: 6px; margin-bottom: 16px; }
.update-item { background: #fff; padding: 14px; border-radius: 8px; margin-bottom: 10px;
               box-shadow: 0 1px 3px rgba(0,0,0,.1); display: flex; justify-content: space-between; align-items: start; }
.update-item .content { flex: 1; }
.update-item h4 { font-size: 15px; margin-bottom: 4px; }
.update-item .meta { font-size: 12px; color: #999; }
.update-item .body-text { font-size: 13px; color: #666; margin-top: 4px; }
.del-btn { background: #eee; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; }
.del-btn:hover { background: #ffcdd2; }
</style>
</head>
<body>
<div class="nav">猛少女之拳 — 管理後台</div>
<div class="container">

<?php if ($msg): ?>
    <div class="msg"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="stat">
    已註冊裝置：<span><?= $tokenCount ?></span> 台
</div>

<div class="form-box">
    <h3>發佈新消息</h3>
    <form method="post">
        <input type="hidden" name="action" value="post_update">
        <label>分類</label>
        <select name="category">
            <option value="update">作品更新</option>
            <option value="event">場次情報</option>
            <option value="news">公告</option>
        </select>
        <label>標題</label>
        <input type="text" name="title" required placeholder="例：FF45 新刊情報">
        <label>內容</label>
        <textarea name="body" required placeholder="詳細內容..."></textarea>
        <div class="checkbox-row">
            <input type="checkbox" name="push" id="push" checked>
            <label for="push">同時推播通知給所有用戶</label>
        </div>
        <button class="btn" type="submit">發佈</button>
    </form>
</div>

<h3 style="margin-bottom:12px;">歷史消息</h3>
<?php foreach ($updateList as $u): ?>
<div class="update-item">
    <div class="content">
        <h4><?= htmlspecialchars($u['title']) ?></h4>
        <div class="meta"><?= htmlspecialchars($u['category']) ?> · <?= htmlspecialchars($u['created_at']) ?></div>
        <div class="body-text"><?= htmlspecialchars(mb_strimwidth($u['body'], 0, 100, '...')) ?></div>
    </div>
    <form method="post" style="margin-left:10px;" onsubmit="return confirm('確定刪除？')">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= $u['id'] ?>">
        <button class="del-btn">刪除</button>
    </form>
</div>
<?php endforeach; ?>

</div>
</body>
</html>
