<?php
require_once __DIR__ . '/db.php';

$results = $db->query("SELECT * FROM updates ORDER BY created_at DESC LIMIT 50");
$updates = [];
while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
    $updates[] = $row;
}

$categoryLabels = [
    'update' => '作品更新',
    'event' => '場次情報',
    'news' => '公告',
];
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>猛少女之拳</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, 'Noto Sans TC', sans-serif; background: #fafafa; color: #333; }
.header { background: linear-gradient(135deg, #E91E63, #FF4081); color: #fff; padding: 20px 16px; }
.header h1 { font-size: 20px; }
.header p { font-size: 13px; opacity: 0.9; margin-top: 4px; }
.empty { text-align: center; padding: 60px 20px; color: #999; }
.card { background: #fff; margin: 12px 16px; border-radius: 10px; padding: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.card .tag { display: inline-block; font-size: 11px; padding: 2px 8px; border-radius: 10px;
             color: #fff; margin-bottom: 8px; }
.tag-update { background: #E91E63; }
.tag-event { background: #FF9800; }
.tag-news { background: #2196F3; }
.card h2 { font-size: 16px; margin-bottom: 6px; }
.card .body { font-size: 14px; line-height: 1.6; color: #555; white-space: pre-wrap; }
.card .time { font-size: 12px; color: #aaa; margin-top: 8px; }
</style>
</head>
<body>
<div class="header">
    <h1>猛少女之拳</h1>
    <p>忽田各 — 最新情報</p>
</div>

<?php if (empty($updates)): ?>
    <div class="empty">目前還沒有更新，敬請期待！</div>
<?php else: ?>
    <?php foreach ($updates as $u): ?>
    <div class="card">
        <span class="tag tag-<?= htmlspecialchars($u['category']) ?>">
            <?= htmlspecialchars($categoryLabels[$u['category']] ?? $u['category']) ?>
        </span>
        <h2><?= htmlspecialchars($u['title']) ?></h2>
        <div class="body"><?= htmlspecialchars($u['body']) ?></div>
        <div class="time"><?= htmlspecialchars($u['created_at']) ?></div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
