<?php
require_once __DIR__ . '/includes/helpers.php';

$id = $_GET['id'] ?? '';
$manga = get_manga($id);
if (!$manga) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>404</title></head><body style="background:#0f0f0f;color:#aaa;text-align:center;padding:80px;font-family:sans-serif"><h1>找不到此漫畫</h1><p><a href="' . BASE_URL . '/" style="color:#e94560">返回首頁</a></p></body></html>';
    exit;
}

$chapters = $manga['chapters'] ?? [];
$first_ch = !empty($chapters) ? $chapters[0] : null;
$last_ch = !empty($chapters) ? $chapters[count($chapters) - 1] : null;
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($manga['title']) ?> - <?= SITE_NAME ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header class="site-header compact">
    <a href="<?= BASE_URL ?>/" class="home-btn">← 首頁</a>
    <h1><a href="<?= BASE_URL ?>/"><?= SITE_NAME ?></a></h1>
</header>

<div class="manga-detail">
    <div class="manga-hero">
        <div class="cover">
            <?php if (!empty($manga['cover'])): ?>
                <img src="<?= htmlspecialchars($manga['cover']) ?>" alt="<?= htmlspecialchars($manga['title']) ?>">
            <?php else: ?>
                <div class="cover-placeholder" style="aspect-ratio:3/4"><?= mb_substr($manga['title'], 0, 1) ?></div>
            <?php endif; ?>
        </div>
        <div class="info">
            <h1><?= htmlspecialchars($manga['title']) ?></h1>

            <div class="meta-table">
                <?php if (!empty($manga['author'])): ?>
                    <span class="label">作者</span>
                    <span class="value"><?= htmlspecialchars($manga['author']) ?></span>
                <?php endif; ?>
                <span class="label">狀態</span>
                <span class="value"><?= htmlspecialchars($manga['status'] ?? '連載中') ?></span>
                <span class="label">話數</span>
                <span class="value"><?= count($chapters) ?> 話</span>
                <?php if (!empty($manga['updated'])): ?>
                    <span class="label">更新</span>
                    <span class="value"><?= htmlspecialchars($manga['updated']) ?></span>
                <?php endif; ?>
            </div>

            <?php if (!empty($manga['genres'])): ?>
            <div class="genres">
                <?php foreach ($manga['genres'] as $g): ?>
                    <span class="genre-tag"><?= htmlspecialchars($g) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($manga['description'])): ?>
            <div class="description"><?= nl2br(htmlspecialchars($manga['description'])) ?></div>
            <?php endif; ?>

            <div class="action-btns">
                <?php if ($first_ch): ?>
                    <a href="reader.php?id=<?= urlencode($id) ?>&ch=<?= urlencode($first_ch['id']) ?>" class="btn btn-primary">開始閱讀</a>
                <?php endif; ?>
                <?php if ($last_ch && count($chapters) > 1): ?>
                    <a href="reader.php?id=<?= urlencode($id) ?>&ch=<?= urlencode($last_ch['id']) ?>" class="btn btn-secondary">最新一話</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="chapter-section">
        <h2>章節列表</h2>
        <?php if (empty($chapters)): ?>
            <div class="empty-state"><p>尚無章節</p></div>
        <?php else: ?>
        <ul class="chapter-list">
            <?php foreach (array_reverse($chapters) as $ch): ?>
            <li>
                <a href="reader.php?id=<?= urlencode($id) ?>&ch=<?= urlencode($ch['id']) ?>">
                    <span class="ch-title"><?= htmlspecialchars($ch['title']) ?></span>
                    <span class="ch-date"><?= htmlspecialchars($ch['date'] ?? '') ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>

<footer class="site-footer"><?= SITE_NAME ?></footer>
</body>
</html>
