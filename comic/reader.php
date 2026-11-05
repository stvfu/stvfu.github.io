<?php
require_once __DIR__ . '/includes/helpers.php';

$manga_id = $_GET['id'] ?? '';
$chapter_id = $_GET['ch'] ?? '';
$manga = get_manga($manga_id);
if (!$manga) { http_response_code(404); echo '找不到此漫畫'; exit; }

$chapter = get_chapter($manga_id, $chapter_id);
if (!$chapter) { http_response_code(404); echo '找不到此章節'; exit; }

$pages = get_chapter_pages($chapter);
$total = count($pages);
$adj = get_adjacent_chapters($manga_id, $chapter_id);
$all_chapters = $manga['chapters'];
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($manga['title']) ?> - <?= htmlspecialchars($chapter['title']) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="reader-header">
    <div class="left">
        <a href="<?= BASE_URL ?>/" class="back-btn" title="回首頁">⌂</a>
        <a href="manga.php?id=<?= urlencode($manga_id) ?>" class="back-btn" title="返回目錄">&larr;</a>
        <span class="title"><?= htmlspecialchars($manga['title']) ?></span>
    </div>
    <div class="right">
        <select class="chapter-select" id="chapterSelect" onchange="changeChapter(this.value)">
            <?php foreach ($all_chapters as $ch): ?>
                <option value="<?= htmlspecialchars($ch['id']) ?>" <?= $ch['id'] === $chapter_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ch['title']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <span class="page-count"><?= $total ?> 頁</span>
        <span class="page-count" id="viewCount"></span>
        <span class="offline-badge" id="offlineBadge" style="display:none">離線</span>
    </div>
</div>

<?php if ($total > 0): ?>

<div class="reader-mode-bar">
    <button id="btnScroll" class="active" onclick="setMode('scroll')">捲動</button>
    <button id="btnSingle" onclick="setMode('single')">單頁</button>
    <span class="mode-sep">|</span>
    <button id="btnLTR" class="active" onclick="setDir('ltr')">左翻</button>
    <button id="btnRTL" onclick="setDir('rtl')">右翻</button>
</div>

<div class="scroll-view" id="scrollView">
    <?php foreach ($pages as $i => $file): ?>
    <div class="page" id="sp<?= $i ?>">
        <img src="<?= htmlspecialchars($chapter['dir'] . '/' . rawurlencode($file)) ?>" alt="<?= $i + 1 ?>" loading="lazy">
    </div>
    <?php endforeach; ?>
</div>

<div class="single-view" id="singleView">
    <div class="nav-area left" id="navLeft"></div>
    <img id="singleImg" src="" alt="">
    <div class="nav-area right" id="navRight"></div>
    <div class="page-info" id="singleInfo"></div>
</div>

<div class="page-nav" id="pageNav">
    <div class="slider-row">
        <span class="pg-label" id="pgLabel">1 / <?= $total ?></span>
        <input type="range" id="pageSlider" min="0" max="<?= $total - 1 ?>" value="0">
    </div>
    <div class="btn-row">
        <?php if ($adj['prev']): ?>
            <a href="reader.php?id=<?= urlencode($manga_id) ?>&ch=<?= urlencode($adj['prev']['id']) ?>" class="btn btn-secondary" style="padding:7px 14px;font-size:.82em">上一話</a>
        <?php endif; ?>
        <button onclick="prevPage()">&#9664;</button>
        <button onclick="nextPage()">&#9654;</button>
        <?php if ($adj['next']): ?>
            <a href="reader.php?id=<?= urlencode($manga_id) ?>&ch=<?= urlencode($adj['next']['id']) ?>" class="btn btn-secondary" style="padding:7px 14px;font-size:.82em">下一話</a>
        <?php endif; ?>
    </div>
</div>

<div class="chapter-end-nav" id="chapterEndNav">
    <h3>-- <?= htmlspecialchars($chapter['title']) ?> 完 --</h3>
    <div class="nav-btns">
        <?php if ($adj['prev']): ?>
            <a href="reader.php?id=<?= urlencode($manga_id) ?>&ch=<?= urlencode($adj['prev']['id']) ?>" class="btn btn-secondary">&larr; <?= htmlspecialchars($adj['prev']['title']) ?></a>
        <?php endif; ?>
        <a href="manga.php?id=<?= urlencode($manga_id) ?>" class="btn btn-secondary">目錄</a>
        <?php if ($adj['next']): ?>
            <a href="reader.php?id=<?= urlencode($manga_id) ?>&ch=<?= urlencode($adj['next']['id']) ?>" class="btn btn-primary"><?= htmlspecialchars($adj['next']['title']) ?> &rarr;</a>
        <?php endif; ?>
    </div>
</div>

<!-- Comment Section -->
<div class="comment-section" id="commentSection">
    <h3>留言板</h3>
    <form class="comment-form" id="commentForm" onsubmit="return submitComment(event)">
        <div class="comment-form-row">
            <input type="text" id="cmtNickname" placeholder="暱稱" maxlength="30" required>
            <input type="text" name="website" style="display:none" tabindex="-1" autocomplete="off">
        </div>
        <textarea id="cmtContent" placeholder="說點什麼..." maxlength="1000" rows="3" required></textarea>
        <div class="comment-form-actions">
            <span class="comment-hint" id="cmtHint"></span>
            <button type="submit" class="btn btn-primary" style="padding:8px 24px;font-size:.88em">送出</button>
        </div>
    </form>
    <div class="comment-list" id="commentList"></div>
</div>

<?php if ($adj['next']): ?>
    <input type="hidden" id="nextChapterUrl" value="reader.php?id=<?= urlencode($manga_id) ?>&ch=<?= urlencode($adj['next']['id']) ?>">
<?php endif; ?>
<?php if ($adj['prev']): ?>
    <input type="hidden" id="prevChapterUrl" value="reader.php?id=<?= urlencode($manga_id) ?>&ch=<?= urlencode($adj['prev']['id']) ?>">
<?php endif; ?>

<script>
const pages = <?= json_encode(array_map(fn($f) => $chapter['dir'] . '/' . rawurlencode($f), $pages)) ?>;
const total = pages.length;
const mangaId = <?= json_encode($manga_id) ?>;
const chapterId = <?= json_encode($chapter_id) ?>;

let mode = localStorage.getItem('reader_mode') || 'scroll';
let dir = localStorage.getItem('reader_dir') || 'ltr';
let current = 0;

const scrollView = document.getElementById('scrollView');
const singleView = document.getElementById('singleView');
const singleImg = document.getElementById('singleImg');
const singleInfo = document.getElementById('singleInfo');
const pageNav = document.getElementById('pageNav');
const pageSlider = document.getElementById('pageSlider');
const pgLabel = document.getElementById('pgLabel');
const chapterEndNav = document.getElementById('chapterEndNav');
const navLeft = document.getElementById('navLeft');
const navRight = document.getElementById('navRight');

function changeChapter(chId) {
    window.location.href = 'reader.php?id=' + encodeURIComponent(mangaId) + '&ch=' + encodeURIComponent(chId);
}

function setDir(d) {
    dir = d;
    localStorage.setItem('reader_dir', d);
    document.getElementById('btnLTR').classList.toggle('active', d === 'ltr');
    document.getElementById('btnRTL').classList.toggle('active', d === 'rtl');
    pageNav.classList.toggle('rtl', d === 'rtl');
}

function setMode(m) {
    mode = m;
    localStorage.setItem('reader_mode', m);
    document.getElementById('btnScroll').classList.toggle('active', m === 'scroll');
    document.getElementById('btnSingle').classList.toggle('active', m === 'single');
    scrollView.style.display = m === 'scroll' ? 'block' : 'none';
    singleView.style.display = m === 'single' ? 'block' : 'none';
    pageNav.style.display = m === 'single' ? 'block' : 'none';
    chapterEndNav.style.display = m === 'scroll' ? 'block' : 'none';
    if (m === 'single') showPage(current);
}

function showPage(i) {
    current = Math.max(0, Math.min(total - 1, i));
    singleImg.src = pages[current];
    const label = (current + 1) + ' / ' + total;
    singleInfo.textContent = label;
    pgLabel.textContent = label;
    pageSlider.value = current;
    localStorage.setItem('progress_' + mangaId + '_' + chapterId, current);
    if (current + 1 < total) { const p = new Image(); p.src = pages[current + 1]; }
}

function nextPage() {
    if (current < total - 1) {
        showPage(current + 1);
    } else {
        const url = document.getElementById('nextChapterUrl');
        if (url) window.location.href = url.value;
    }
}
function prevPage() {
    if (current > 0) {
        showPage(current - 1);
    } else {
        const url = document.getElementById('prevChapterUrl');
        if (url) window.location.href = url.value;
    }
}

navLeft.addEventListener('click', function() { dir === 'rtl' ? nextPage() : prevPage(); });
navRight.addEventListener('click', function() { dir === 'rtl' ? prevPage() : nextPage(); });

pageSlider.addEventListener('input', function() { showPage(parseInt(pageSlider.value)); });

document.addEventListener('keydown', function(e) {
    if (mode !== 'single') return;
    if (e.key === 'ArrowRight' || e.key === 'ArrowDown' || e.key === ' ') {
        e.preventDefault();
        dir === 'rtl' ? prevPage() : nextPage();
    }
    if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
        e.preventDefault();
        dir === 'rtl' ? nextPage() : prevPage();
    }
});

(function() {
    let startX = 0, startY = 0;
    singleView.addEventListener('touchstart', function(e) {
        startX = e.changedTouches[0].clientX;
        startY = e.changedTouches[0].clientY;
    }, { passive: true });
    singleView.addEventListener('touchend', function(e) {
        if (mode !== 'single') return;
        const dx = e.changedTouches[0].clientX - startX;
        const dy = e.changedTouches[0].clientY - startY;
        if (Math.abs(dx) < 40 || Math.abs(dy) > Math.abs(dx)) return;
        if (dir === 'ltr') { dx < 0 ? nextPage() : prevPage(); }
        else { dx > 0 ? nextPage() : prevPage(); }
    }, { passive: true });
})();

setDir(dir);
setMode(mode);

var saved = parseInt(localStorage.getItem('progress_' + mangaId + '_' + chapterId));
if (saved > 0 && saved < total) showPage(saved);

const cmtApi = 'comments.php?mid=' + encodeURIComponent(mangaId) + '&cid=' + encodeURIComponent(chapterId);

// === View count ===
(function() {
    var viewKey = 'viewed_' + mangaId + '_' + chapterId;
    if (!sessionStorage.getItem(viewKey)) {
        fetch(cmtApi + '&action=view', { method: 'POST' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.views) document.getElementById('viewCount').textContent = d.views + ' 次閱覽';
                sessionStorage.setItem(viewKey, '1');
            });
    }
})();

// === Comments ===
const cmtHint = document.getElementById('cmtHint');
const cmtList = document.getElementById('commentList');
const cmtNickname = document.getElementById('cmtNickname');
const cmtContent = document.getElementById('cmtContent');

var savedNick = localStorage.getItem('comment_nickname');
if (savedNick) cmtNickname.value = savedNick;

function loadComments() {
    fetch(cmtApi + '&action=list')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.views) document.getElementById('viewCount').textContent = data.views + ' 次閱覽';
            if (!data.comments || data.comments.length === 0) {
                cmtList.innerHTML = '<p class="no-comments">還沒有留言，來當第一個吧！</p>';
                return;
            }
            cmtList.innerHTML = data.comments.map(function(c) {
                var name = c.nickname.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                var text = c.content.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');
                return '<div class="comment-item">'
                    + '<div class="comment-meta"><span class="comment-nick">' + name + '</span><span class="comment-time">' + c.created_at + '</span></div>'
                    + '<div class="comment-text">' + text + '</div>'
                    + '</div>';
            }).join('');
        });
}

function submitComment(e) {
    e.preventDefault();
    var nick = cmtNickname.value.trim();
    var content = cmtContent.value.trim();
    var website = document.querySelector('[name="website"]').value;
    if (!nick || !content) return false;

    cmtHint.textContent = '送出中...';
    cmtHint.style.color = 'var(--text-muted)';

    fetch(cmtApi + '&action=post', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ nickname: nick, content: content, website: website })
    })
    .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
    .then(function(res) {
        if (res.ok && res.data.ok) {
            cmtHint.textContent = '留言成功！';
            cmtHint.style.color = '#6a6';
            cmtContent.value = '';
            localStorage.setItem('comment_nickname', nick);
            loadComments();
            setTimeout(function() { cmtHint.textContent = ''; }, 3000);
        } else {
            cmtHint.textContent = res.data.error || '送出失敗';
            cmtHint.style.color = '#c66';
        }
    })
    .catch(function() {
        cmtHint.textContent = '網路錯誤';
        cmtHint.style.color = '#c66';
    });
    return false;
}

loadComments();

if (typeof ComicOffline !== 'undefined' && ComicOffline.isDownloaded(mangaId, chapterId)) {
    document.getElementById('offlineBadge').style.display = '';
}
</script>

<?php else: ?>
<div class="empty-state"><p>此章節尚無頁面</p></div>
<?php endif; ?>

</body>
</html>
