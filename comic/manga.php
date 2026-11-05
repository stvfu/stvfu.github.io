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
        <div class="chapter-header">
            <h2>章節列表</h2>
            <button class="sort-btn" id="sortBtn" onclick="toggleSort()" title="切換排序">新→舊 ▼</button>
        </div>
        <?php
            $categories = $manga['categories'] ?? [];
            $has_cats = !empty($categories);
        ?>
        <?php if (empty($chapters)): ?>
            <div class="empty-state"><p>尚無章節</p></div>
        <?php else: ?>

        <?php if ($has_cats): ?>
        <div class="chapter-cat-tabs" id="chapterCatTabs">
            <button class="chapter-cat-tab active" data-cat="">全部</button>
            <?php foreach ($categories as $cat): ?>
                <button class="chapter-cat-tab" data-cat="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <ul class="chapter-list" id="chapterList">
            <?php foreach (array_reverse($chapters) as $ch): ?>
            <li data-category="<?= htmlspecialchars($ch['category'] ?? '') ?>"
                data-has-cover="<?= !empty($ch['cover']) ? '1' : '0' ?>"
                data-link="<?= htmlspecialchars($ch['link'] ?? '') ?>"
                data-manga-id="<?= htmlspecialchars($id) ?>"
                data-chapter-id="<?= htmlspecialchars($ch['id']) ?>">
                <a href="reader.php?id=<?= urlencode($id) ?>&ch=<?= urlencode($ch['id']) ?>">
                    <?php if (!empty($ch['cover'])): ?>
                        <img src="<?= htmlspecialchars($ch['cover']) ?>" class="ch-cover-thumb" alt="">
                    <?php endif; ?>
                    <span class="ch-title"><?= htmlspecialchars($ch['title']) ?></span>
                    <span class="ch-date"><?= htmlspecialchars($ch['date'] ?? '') ?></span>
                </a>
                <?php if (!empty($ch['link'])): ?>
                    <a href="<?= htmlspecialchars($ch['link']) ?>" target="_blank" rel="noopener" class="ch-ext-link">購買連結</a>
                <?php endif; ?>
                <button class="ch-dl-btn" style="display:none" title="離線下載"></button>
            </li>
            <?php endforeach; ?>
        </ul>

        <script>
        let sortNewest = true;
        function toggleSort() {
            sortNewest = !sortNewest;
            const list = document.getElementById('chapterList');
            const items = [...list.children];
            items.reverse();
            items.forEach(li => list.appendChild(li));
            document.getElementById('sortBtn').textContent = sortNewest ? '新→舊 ▼' : '舊→新 ▲';
        }
        </script>

        <?php if ($has_cats): ?>
        <script>
        function updateChapterView(cat) {
            const list = document.getElementById('chapterList');
            const isAll = (cat === '');
            list.classList.toggle('hide-covers', isAll);
            list.classList.toggle('card-mode', !isAll);
            document.querySelectorAll('#chapterList li').forEach(li => {
                const show = isAll || li.dataset.category === cat;
                li.style.display = show ? '' : 'none';
                const extLink = li.querySelector('.ch-ext-link');
                if (extLink) extLink.style.display = isAll ? 'none' : '';
            });
        }
        updateChapterView('');
        document.getElementById('chapterCatTabs').addEventListener('click', function(e) {
            const btn = e.target.closest('.chapter-cat-tab');
            if (!btn) return;
            this.querySelectorAll('.chapter-cat-tab').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            updateChapterView(btn.dataset.cat);
        });
        </script>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    if (typeof ComicOffline === 'undefined') return;

    document.getElementById('chapterList').classList.add('has-dl');
    var mangaId = <?= json_encode($id) ?>;
    var dlBtns = document.querySelectorAll('.ch-dl-btn');

    dlBtns.forEach(function(btn) {
        btn.style.display = '';
        var li = btn.closest('li');
        var chId = li.dataset.chapterId;
        updateBtn(btn, mangaId, chId);

        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var progress = ComicOffline.getProgress(mangaId, chId);
            if (progress === 100 || ComicOffline.isDownloaded(mangaId, chId)) {
                if (confirm('刪除此章節的離線資料？')) {
                    ComicOffline.deleteChapter(mangaId, chId);
                    updateBtn(btn, mangaId, chId);
                }
            } else if (progress >= 0 && progress < 100) {
                return;
            } else {
                ComicOffline.download(mangaId, chId);
                btn.textContent = '0%';
                btn.className = 'ch-dl-btn downloading';
                pollProgress(btn, mangaId, chId);
            }
        });
    });

    function updateBtn(btn, mid, cid) {
        if (ComicOffline.isDownloaded(mid, cid)) {
            btn.textContent = '✓';
            btn.className = 'ch-dl-btn downloaded';
            btn.title = '已下載（點擊刪除）';
        } else {
            btn.textContent = '↓';
            btn.className = 'ch-dl-btn';
            btn.title = '離線下載';
        }
    }

    function pollProgress(btn, mid, cid) {
        var timer = setInterval(function() {
            var p = ComicOffline.getProgress(mid, cid);
            if (p >= 100) {
                clearInterval(timer);
                updateBtn(btn, mid, cid);
            } else if (p < 0) {
                clearInterval(timer);
                btn.textContent = '✗';
                btn.className = 'ch-dl-btn failed';
                btn.title = '下載失敗，點擊重試';
                setTimeout(function() { updateBtn(btn, mid, cid); }, 3000);
            } else {
                btn.textContent = p + '%';
            }
        }, 500);
    }
})();
</script>

<footer class="site-footer"><?= SITE_NAME ?></footer>
</body>
</html>
