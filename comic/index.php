<?php
require_once __DIR__ . '/includes/helpers.php';

$type_filter = $_GET['type'] ?? '';
$is_app = ($type_filter === 'original');

$app_ver_file = __DIR__ . '/data/app_version.json';
$app_ver = file_exists($app_ver_file) ? (json_decode(file_get_contents($app_ver_file), true) ?: []) : [];
$app_version_name = $app_ver['version_name'] ?? '';
$app_download_url = $app_ver['download_url'] ?? '';

$unlock_data = [];
$unlock_file = __DIR__ . '/data/unlock.json';
if (file_exists($unlock_file)) {
    $unlock_data = json_decode(file_get_contents($unlock_file), true) ?: [];
}
$unlock_codes = $unlock_data['codes'] ?? (isset($unlock_data['code']) ? [$unlock_data['code']] : []);
$unlock_codes = array_filter(array_map('trim', $unlock_codes));
$unlock_enabled = $is_app && !empty($unlock_data['enabled']) && !empty($unlock_codes);
$is_unlocked = false;
if ($unlock_enabled && !empty($_COOKIE['comic_unlock'])) {
    foreach ($unlock_codes as $uc) {
        if (hash_equals(hash('sha256', mb_strtolower($uc) . 'komicpunch_unlock_2026'), $_COOKIE['comic_unlock'])) {
            $is_unlocked = true;
            break;
        }
    }
}

$all_manga = get_all_manga();
$original_count = count(array_filter($all_manga, fn($m) => ($m['type'] ?? 'doujin') === 'original'));
$doujin_count = count(array_filter($all_manga, fn($m) => ($m['type'] ?? 'doujin') === 'doujin'));

$manga_list = $all_manga;

if ($is_app) {
    if ($is_unlocked) {
        // App unlocked: show all
    } else {
        $manga_list = array_filter($manga_list, fn($m) => ($m['type'] ?? 'doujin') === 'original');
    }
}

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

<?php if (!$is_app): ?>
<!-- ===== WEB MODE: show everything, no lock UI ===== -->
<div class="app-banner" id="appBanner">
    <span class="app-banner-text">&#x1F4F1; 下載 App，獲得推播通知與離線閱讀功能</span>
    <a href="<?= htmlspecialchars($app_download_url) ?>" class="app-banner-btn">下載 APK v<?= htmlspecialchars($app_version_name) ?></a>
    <button class="app-banner-close" onclick="closeAppBanner()" title="關閉">&times;</button>
</div>
<div class="search-bar">
    <input type="text" id="searchInput" placeholder="搜尋漫畫名稱..." oninput="filterManga()">
</div>

<div class="section-title">全部漫畫 (<?= count($manga_list) ?>)</div>

<?php if (empty($manga_list)): ?>
<div class="empty-state"><p>尚未新增任何漫畫</p></div>
<?php else: ?>
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
                <span class="type-badge <?= ($manga['type'] ?? 'doujin') === 'original' ? 'type-original' : 'type-doujin' ?>">
                    <?= ($manga['type'] ?? 'doujin') === 'original' ? '原創' : '同人' ?>
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
function closeAppBanner() {
    document.getElementById('appBanner').style.display = 'none';
    localStorage.setItem('app_banner_closed', '1');
}
if (localStorage.getItem('app_banner_closed')) {
    var b = document.getElementById('appBanner');
    if (b) b.style.display = 'none';
}
</script>
<?php endif; ?>

<?php elseif ($is_unlocked): ?>
<!-- ===== APP UNLOCKED: tabs + full grid ===== -->
<div class="type-tabs">
    <a href="<?= BASE_URL ?>/?type=original" class="type-tab active">全部 (<?= count($all_manga) ?>)</a>
    <button class="type-tab unlock-tab unlocked" onclick="showUnlockModal()" title="已解鎖">&#x1F513;</button>
</div>

<div class="search-bar">
    <input type="text" id="searchInput" placeholder="搜尋漫畫名稱..." oninput="filterManga()">
</div>

<div class="section-title">全部漫畫 (<?= count($manga_list) ?>)</div>

<?php if (empty($manga_list)): ?>
<div class="empty-state"><p>此分類尚無作品</p></div>
<?php else: ?>
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
                <span class="type-badge <?= ($manga['type'] ?? 'doujin') === 'original' ? 'type-original' : 'type-doujin' ?>">
                    <?= ($manga['type'] ?? 'doujin') === 'original' ? '原創' : '同人' ?>
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
<?php endif; ?>

<!-- Unlock Modal (unlocked state) -->
<div class="unlock-overlay" id="unlockOverlay">
    <div class="unlock-modal">
        <h3>&#x1F513; 已解鎖</h3>
        <p class="unlock-hint">同人作品已開放瀏覽</p>
        <div class="unlock-btns">
            <button class="unlock-btn cancel" onclick="closeUnlock()">關閉</button>
            <button class="unlock-btn danger" onclick="doLock()">重新上鎖</button>
        </div>
    </div>
</div>
<script>
function showUnlockModal() {
    document.getElementById('unlockOverlay').classList.add('show');
}
function closeUnlock() {
    document.getElementById('unlockOverlay').classList.remove('show');
}
function doLock() {
    fetch('unlock.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'lock' })
    }).then(function() { location.href = '<?= BASE_URL ?>/?type=original'; });
}
document.getElementById('unlockOverlay').addEventListener('click', function(e) { if (e.target === this) closeUnlock(); });
</script>

<?php else: ?>
<!-- ===== APP LOCKED: lock hero + original works only ===== -->

<?php if ($unlock_enabled && $doujin_count > 0): ?>
<div class="lock-hero" id="lockHero">
    <div class="lock-icon" onclick="showUnlockModal()">&#x1F512;</div>
    <div class="lock-count">+<?= $doujin_count ?> 部隱藏作品</div>
    <p class="lock-desc">到場次攤位取得<strong>通關密語</strong>，解鎖全部作品</p>
    <button class="lock-btn" onclick="showUnlockModal()">輸入密語解鎖</button>
</div>
<?php endif; ?>

<?php if (!empty($manga_list)): ?>
<div class="section-title">原創作品 (<?= $original_count ?>)</div>
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
<?php elseif (!$unlock_enabled || $doujin_count === 0): ?>
<div class="empty-state"><p>尚未新增任何漫畫</p></div>
<?php endif; ?>

<?php if ($unlock_enabled): ?>
<!-- Unlock Modal (locked state) -->
<div class="unlock-overlay" id="unlockOverlay">
    <div class="unlock-modal">
        <h3>&#x1F512; 通關密語</h3>
        <p class="unlock-hint">到場次攤位取得密語，解鎖隱藏作品</p>
        <input type="text" id="unlockCode" placeholder="輸入密語..." maxlength="30" autocomplete="off">
        <div class="unlock-msg" id="unlockMsg"></div>
        <div class="unlock-btns">
            <button class="unlock-btn cancel" onclick="closeUnlock()">取消</button>
            <button class="unlock-btn primary" onclick="doUnlock()">解鎖</button>
        </div>
    </div>
</div>
<script>
function showUnlockModal() {
    document.getElementById('unlockOverlay').classList.add('show');
    var input = document.getElementById('unlockCode');
    if (input) setTimeout(function() { input.focus(); }, 100);
}
function closeUnlock() {
    document.getElementById('unlockOverlay').classList.remove('show');
}
function doUnlock() {
    var code = document.getElementById('unlockCode').value.trim();
    var msg = document.getElementById('unlockMsg');
    if (!code) { msg.textContent = '請輸入密語'; msg.className = 'unlock-msg error'; return; }
    msg.textContent = '驗證中...'; msg.className = 'unlock-msg';
    fetch('unlock.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ code: code })
    })
    .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
    .then(function(res) {
        if (res.data.unlocked) {
            msg.textContent = '解鎖成功！';
            msg.className = 'unlock-msg success';
            setTimeout(function() { location.href = '<?= BASE_URL ?>/?type=original'; }, 600);
        } else {
            msg.textContent = res.data.error || '密語不正確';
            msg.className = 'unlock-msg error';
        }
    })
    .catch(function() { msg.textContent = '網路錯誤'; msg.className = 'unlock-msg error'; });
}
var codeInput = document.getElementById('unlockCode');
if (codeInput) codeInput.addEventListener('keydown', function(e) { if (e.key === 'Enter') doUnlock(); });
document.getElementById('unlockOverlay').addEventListener('click', function(e) { if (e.target === this) closeUnlock(); });
</script>
<?php endif; ?>

<?php endif; ?>

<footer class="site-footer"><?= SITE_NAME ?></footer>
</body>
</html>
