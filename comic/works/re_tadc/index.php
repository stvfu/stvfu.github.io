<?php
const IMAGE_DIR = __DIR__ . '/images/';
const ORDER_FILE = __DIR__ . '/page_order.json';
const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

function load_order(): array {
    if (file_exists(ORDER_FILE)) {
        $data = json_decode(file_get_contents(ORDER_FILE), true);
        if (is_array($data)) {
            return array_values(array_filter($data, fn($f) => file_exists(IMAGE_DIR . $f)));
        }
    }
    $files = [];
    foreach (glob(IMAGE_DIR . '*') as $path) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ALLOWED_EXT)) $files[] = basename($path);
    }
    sort($files, SORT_NATURAL);
    return $files;
}

$pages = load_order();
$total = count($pages);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>RE:TADC 漫畫閱讀</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { background: #111; color: #eee; font-family: sans-serif; }

/* Header */
.header { position: sticky; top: 0; z-index: 100; background: rgba(17,17,17,.92); backdrop-filter: blur(8px); padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #333; }
.header h1 { font-size: 1.1em; }
.header .info { font-size: .85em; color: #aaa; }

/* Mode toggle */
.mode-bar { position: sticky; top: 48px; z-index: 99; background: rgba(17,17,17,.9); backdrop-filter: blur(6px); display: flex; justify-content: center; gap: 10px; padding: 8px; border-bottom: 1px solid #222; }
.mode-bar button { padding: 6px 18px; border: 1px solid #444; border-radius: 6px; background: transparent; color: #ccc; cursor: pointer; font-size: .85em; }
.mode-bar button.active { background: #e94560; border-color: #e94560; color: #fff; }
.dir-sep { color: #444; margin: 0 2px; user-select: none; }

/* Scroll mode */
.scroll-view { max-width: 900px; margin: 0 auto; padding: 0 0 60px; }
.scroll-view .page { width: 100%; display: flex; justify-content: center; }
.scroll-view .page img { max-width: 100%; height: auto; display: block; }

/* Single page mode */
.single-view { display: none; max-width: 900px; margin: 0 auto; padding: 20px 0 80px; text-align: center; position: relative; min-height: calc(100vh - 120px); }
.single-view img { max-width: 100%; max-height: calc(100vh - 160px); object-fit: contain; }
.single-view .nav-area { position: absolute; top: 0; bottom: 0; width: 40%; cursor: pointer; }
.single-view .nav-area.left { left: 0; }
.single-view .nav-area.right { right: 0; }
.single-view .page-info { margin-top: 12px; color: #aaa; font-size: .9em; }

/* Bottom nav for single mode */
.page-nav { display: none; position: fixed; bottom: 0; left: 0; right: 0; background: rgba(17,17,17,.95); backdrop-filter: blur(8px); padding: 10px 20px; border-top: 1px solid #333; z-index: 100; }
.page-nav .slider-row { display: flex; align-items: center; gap: 12px; }
.page-nav input[type=range] { flex: 1; accent-color: #e94560; }
.page-nav.rtl .slider-row { direction: rtl; }
.page-nav .pg-label { font-size: .85em; min-width: 60px; text-align: center; }
.page-nav .btn-row { display: flex; justify-content: center; gap: 8px; margin-top: 6px; }
.page-nav button { padding: 6px 20px; border: 1px solid #444; border-radius: 6px; background: transparent; color: #ccc; cursor: pointer; }
.page-nav button:hover { background: #333; }

/* Thumbnail strip */
.thumb-strip { display: none; position: fixed; bottom: 90px; left: 0; right: 0; background: rgba(17,17,17,.95); backdrop-filter: blur(8px); padding: 8px 12px; z-index: 99; overflow-x: auto; white-space: nowrap; border-top: 1px solid #333; }
.thumb-strip .thumb { display: inline-block; width: 50px; height: 70px; object-fit: cover; border: 2px solid transparent; border-radius: 4px; margin: 0 3px; cursor: pointer; opacity: .6; transition: opacity .2s, border-color .2s; }
.thumb-strip .thumb.active { border-color: #e94560; opacity: 1; }
.thumb-strip .thumb:hover { opacity: 1; }

.no-pages { text-align: center; padding: 100px 20px; color: #888; font-size: 1.2em; }
</style>
</head>
<body>

<div class="header">
    <h1>RE:TADC</h1>
    <span class="info" id="headerInfo"><?= $total ?> 頁</span>
</div>

<?php if ($total === 0): ?>
<div class="no-pages">尚未上傳任何頁面</div>
<?php else: ?>

<div class="mode-bar">
    <button id="btnScroll" class="active" onclick="setMode('scroll')">捲動模式</button>
    <button id="btnSingle" onclick="setMode('single')">單頁模式</button>
    <span class="dir-sep">|</span>
    <button id="btnLTR" class="active" onclick="setDir('ltr')">左翻 ←</button>
    <button id="btnRTL" onclick="setDir('rtl')">右翻 →</button>
</div>

<!-- Scroll mode -->
<div class="scroll-view" id="scrollView">
<?php foreach ($pages as $i => $file): ?>
    <div class="page" id="sp<?= $i ?>">
        <img src="images/<?= htmlspecialchars(rawurlencode($file)) ?>" alt="第 <?= $i+1 ?> 頁" loading="lazy">
    </div>
<?php endforeach; ?>
</div>

<!-- Single page mode -->
<div class="single-view" id="singleView">
    <div class="nav-area left" onclick="tapLeft()"></div>
    <img id="singleImg" src="" alt="">
    <div class="nav-area right" onclick="tapRight()"></div>
    <div class="page-info" id="singleInfo"></div>
</div>

<!-- Bottom controls for single mode -->
<div class="page-nav" id="pageNav">
    <div class="slider-row">
        <span class="pg-label" id="pgLabel">1 / <?= $total ?></span>
        <input type="range" id="pageSlider" min="0" max="<?= $total - 1 ?>" value="0">
    </div>
    <div class="btn-row">
        <button onclick="prevPage()">&#9664; 上一頁</button>
        <button onclick="toggleThumbs()">縮圖</button>
        <button onclick="nextPage()">下一頁 &#9654;</button>
    </div>
</div>

<!-- Thumbnail strip -->
<div class="thumb-strip" id="thumbStrip">
<?php foreach ($pages as $i => $file): ?>
    <img class="thumb" src="update.php?action=thumb&f=<?= rawurlencode($file) ?>" data-index="<?= $i ?>" onclick="goPage(<?= $i ?>)" alt="<?= $i+1 ?>">
<?php endforeach; ?>
</div>

<script>
const pages = <?= json_encode(array_map(fn($f) => 'images/' . rawurlencode($f), $pages)) ?>;
const total = pages.length;
let mode = 'scroll';
let current = 0;
let thumbsVisible = false;
let dir = localStorage.getItem('reader_dir') || 'ltr';

const scrollView = document.getElementById('scrollView');
const singleView = document.getElementById('singleView');
const singleImg = document.getElementById('singleImg');
const singleInfo = document.getElementById('singleInfo');
const pageNav = document.getElementById('pageNav');
const pageSlider = document.getElementById('pageSlider');
const pgLabel = document.getElementById('pgLabel');
const thumbStrip = document.getElementById('thumbStrip');

function setDir(d) {
    dir = d;
    localStorage.setItem('reader_dir', d);
    document.getElementById('btnLTR').classList.toggle('active', d === 'ltr');
    document.getElementById('btnRTL').classList.toggle('active', d === 'rtl');
    pageNav.classList.toggle('rtl', d === 'rtl');
    if (mode === 'single') showPage(current);
}

function setMode(m) {
    mode = m;
    document.getElementById('btnScroll').classList.toggle('active', m === 'scroll');
    document.getElementById('btnSingle').classList.toggle('active', m === 'single');
    scrollView.style.display = m === 'scroll' ? 'block' : 'none';
    singleView.style.display = m === 'single' ? 'block' : 'none';
    pageNav.style.display = m === 'single' ? 'block' : 'none';
    if (m === 'single') {
        showPage(current);
    } else {
        thumbStrip.style.display = 'none';
        thumbsVisible = false;
    }
}

function showPage(i) {
    current = Math.max(0, Math.min(total - 1, i));
    singleImg.src = pages[current];
    singleInfo.textContent = `${current + 1} / ${total}`;
    pgLabel.textContent = `${current + 1} / ${total}`;
    pageSlider.value = current;
    document.querySelectorAll('.thumb-strip .thumb').forEach((t, idx) => {
        t.classList.toggle('active', idx === current);
    });
    const activeThumb = thumbStrip.querySelector('.thumb.active');
    if (activeThumb) activeThumb.scrollIntoView({ inline: 'center', behavior: 'smooth' });
}

function nextPage() { if (current < total - 1) showPage(current + 1); }
function prevPage() { if (current > 0) showPage(current - 1); }
function goPage(i) { showPage(i); }

function tapLeft() { dir === 'rtl' ? nextPage() : prevPage(); }
function tapRight() { dir === 'rtl' ? prevPage() : nextPage(); }

function toggleThumbs() {
    thumbsVisible = !thumbsVisible;
    thumbStrip.style.display = thumbsVisible ? 'block' : 'none';
}

pageSlider.addEventListener('input', () => showPage(parseInt(pageSlider.value)));

document.addEventListener('keydown', (e) => {
    if (mode !== 'single') return;
    if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
        e.preventDefault();
        dir === 'rtl' ? prevPage() : nextPage();
    }
    if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
        e.preventDefault();
        dir === 'rtl' ? nextPage() : prevPage();
    }
});

// Swipe support
let touchStartX = 0, touchStartY = 0;
singleView.addEventListener('touchstart', (e) => {
    touchStartX = e.changedTouches[0].clientX;
    touchStartY = e.changedTouches[0].clientY;
}, { passive: true });
singleView.addEventListener('touchend', (e) => {
    if (mode !== 'single') return;
    const dx = e.changedTouches[0].clientX - touchStartX;
    const dy = e.changedTouches[0].clientY - touchStartY;
    if (Math.abs(dx) < 40 || Math.abs(dy) > Math.abs(dx)) return;
    if (dir === 'ltr') {
        dx < 0 ? nextPage() : prevPage();
    } else {
        dx > 0 ? nextPage() : prevPage();
    }
}, { passive: true });

// Preload adjacent pages in single mode
const preloadImg = new Image();
const observer = new IntersectionObserver(() => {
    if (mode === 'single' && current + 1 < total) preloadImg.src = pages[current + 1];
});

// Restore saved direction on load
setDir(dir);
</script>

<?php endif; ?>
</body>
</html>
