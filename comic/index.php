<?php
require_once __DIR__ . '/includes/helpers.php';

$manga_list = get_all_manga();
uasort($manga_list, fn($a, $b) => strcmp($b['updated'] ?? '', $a['updated'] ?? ''));
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= SITE_NAME ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header class="site-header">
    <h1><a href="<?= BASE_URL ?>/"><?= SITE_NAME ?></a></h1>
    <p class="subtitle"><?= SITE_DESC ?></p>
</header>

<?php if (!empty($manga_list)): ?>
<div class="search-bar">
    <input type="text" id="searchInput" placeholder="搜尋漫畫名稱..." oninput="filterManga()">
</div>

<div class="section-title">全部漫畫 (<?= count($manga_list) ?>)</div>

<div class="manga-grid" id="mangaGrid">
<?php foreach ($manga_list as $id => $manga): ?>
    <div class="manga-card" data-title="<?= htmlspecialchars($manga['title']) ?>" data-genres="<?= htmlspecialchars(implode(',', $manga['genres'] ?? [])) ?>">
        <a href="manga.php?id=<?= urlencode($id) ?>">
            <div class="cover-wrap">
                <?php if (!empty($manga['cover'])): ?>
                    <img src="<?= htmlspecialchars($manga['cover']) ?>" alt="<?= htmlspecialchars($manga['title']) ?>" loading="lazy">
                <?php else: ?>
                    <div class="cover-placeholder"><?= mb_substr($manga['title'], 0, 1) ?></div>
                <?php endif; ?>
                <span class="status-badge <?= ($manga['status'] ?? '') === '已完結' ? 'completed' : '' ?>">
                    <?= htmlspecialchars($manga['status'] ?? '連載中') ?>
                </span>
            </div>
            <div class="card-body">
                <h3><?= htmlspecialchars($manga['title']) ?></h3>
                <div class="meta">
                    <?php $chapters = $manga['chapters'] ?? []; ?>
                    <?php if (!empty($chapters)): ?>
                        <?= htmlspecialchars(end($chapters)['title']) ?>
                    <?php endif; ?>
                </div>
            </div>
        </a>
    </div>
<?php endforeach; ?>
</div>

<script>
function filterManga() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('.manga-card').forEach(card => {
        const title = card.dataset.title.toLowerCase();
        const genres = card.dataset.genres.toLowerCase();
        card.style.display = (title.includes(q) || genres.includes(q)) ? '' : 'none';
    });
}
</script>

<?php else: ?>
<div class="empty-state"><p>尚未新增任何漫畫</p></div>
<?php endif; ?>

<footer class="site-footer"><?= SITE_NAME ?></footer>
</body>
</html>
