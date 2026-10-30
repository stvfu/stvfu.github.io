<?php
$config = require '/var/www/data/claude/config.php';

session_name('AIGROUP');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/AI/AI_group/',
    'httponly'  => true,
    'samesite'  => 'Lax',
    'secure'    => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$loginError = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    if ($_POST['username'] === ($config['username'] ?? '')
        && password_verify($_POST['password'] ?? '', $config['password_hash'] ?? '')) {
        $_SESSION['user'] = $_POST['username'];
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
    $loginError = true;
}

$providers = [
    'claude' => ['label' => 'Claude', 'models' => [
        'claude-haiku-5-5'  => 'Haiku 5.5（最快）',
        'claude-sonnet-5-5' => 'Sonnet 5.5（較快）',
        'claude-fable-5-1'  => 'Fable 5.1（最強）',
        'claude-opus-5-5'   => 'Opus 5.5',
    ]],
    'openai' => ['label' => 'OpenAI', 'models' => [
        'gpt-5.4-mini' => 'GPT-5.4 Mini（較快）',
        'gpt-5.4'      => 'GPT-5.4',
        'gpt-5.5'      => 'GPT-5.5（最新）',
    ]],
    'grok' => ['label' => 'Grok', 'models' => [
        'grok-4.7' => 'Grok 4.7（最新）',
        'grok-4.5' => 'Grok 4.5',
    ]],
    'gemini' => ['label' => 'Gemini', 'models' => [
        'gemini-3.6-flash' => '3.6 Flash（較快）',
        'gemini-3.5-flash' => '3.5 Flash',
        'gemini-4-argon'   => '4 Argon（最新）',
    ]],
];
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>AI 居酒屋</title>
<style>
:root{--bg:#0f0d15;--surface:#1a1726;--card:#231f30;--border:#2d2640;--text:#E5E7EB;--muted:#9CA3AF;--accent:#F59E0B;--accent2:#B45309}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text);height:100dvh;overflow:hidden}

/* Login */
.login-page{display:flex;align-items:center;justify-content:center;height:100dvh}
.login-box{background:var(--surface);padding:40px;border-radius:16px;border:1px solid var(--border);width:320px;text-align:center}
.login-box h1{font-size:2.2rem;margin-bottom:4px}
.login-box .sub{color:var(--muted);margin-bottom:24px;font-size:.9rem}
.login-box input{display:block;width:100%;padding:10px 14px;margin-bottom:12px;background:var(--card);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:.95rem;outline:none}
.login-box input:focus{border-color:var(--accent)}
.login-box button{width:100%;padding:10px;background:var(--accent);color:#000;border:none;border-radius:8px;font-size:1rem;font-weight:600;cursor:pointer}
.login-error{color:#F87171;font-size:.85rem;margin-bottom:12px}

/* App */
.app{display:flex;flex-direction:column;height:100dvh;max-width:800px;margin:0 auto}
.header{display:flex;align-items:center;padding:10px 16px;background:var(--surface);border-bottom:1px solid var(--border);gap:10px;flex-shrink:0}
.title{font-size:1.15rem;font-weight:700;white-space:nowrap}
.modes{display:flex;gap:4px;flex:1;justify-content:center}
.mode-btn{background:transparent;border:1px solid var(--border);color:var(--muted);padding:5px 12px;border-radius:16px;cursor:pointer;font-size:.82rem;transition:all .2s;white-space:nowrap}
.mode-btn.active{background:var(--accent);color:#000;border-color:var(--accent);font-weight:600}
.mode-btn:not(.active):hover{border-color:var(--muted)}
.hdr-btn{background:transparent;border:none;color:var(--muted);cursor:pointer;font-size:1.05rem;padding:4px 6px;border-radius:6px}
.hdr-btn:hover{background:var(--card)}

/* Characters bar */
.chars-bar{display:flex;justify-content:center;gap:14px;padding:7px 16px;background:var(--surface);border-bottom:1px solid var(--border);flex-shrink:0;flex-wrap:wrap}
.char-badge{display:flex;align-items:center;gap:5px;font-size:.78rem;color:var(--muted)}
.char-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0}

/* Chat */
.chat{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:14px}
.chat::-webkit-scrollbar{width:6px}
.chat::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px}
.welcome{text-align:center;padding:48px 20px;color:var(--muted)}
.welcome-icon{font-size:3.5rem;margin-bottom:14px}
.welcome-text{line-height:1.9;font-size:.95rem}
.msg{display:flex;gap:10px;max-width:82%;animation:fadeIn .3s}
.msg-user{align-self:flex-end;flex-direction:row-reverse}
.msg-ai{align-self:flex-start}
.msg-avatar{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0;color:#fff;font-weight:700}
.msg-body{display:flex;flex-direction:column;gap:2px;min-width:0}
.msg-name{font-size:.72rem;font-weight:600;padding:0 4px}
.msg-bubble{padding:10px 14px;border-radius:16px;font-size:.93rem;line-height:1.55;white-space:pre-wrap;word-break:break-word}
.msg-ai .msg-bubble{background:var(--card);border-top-left-radius:4px}
.msg-user .msg-bubble{background:var(--accent2);color:#fff;border-top-right-radius:4px}
.system-msg{text-align:center;color:var(--muted);font-size:.8rem;padding:4px 0}
@keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}

/* Context menu */
.ctx-menu{position:fixed;z-index:200;background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:4px 0;box-shadow:0 4px 16px rgba(0,0,0,.35);min-width:130px}
.ctx-menu button{display:block;width:100%;text-align:left;border:none;background:none;color:var(--text);padding:10px 16px;font-size:.88rem;cursor:pointer}
.ctx-menu button:hover{background:var(--card)}
.ctx-menu[hidden]{display:none}

/* Editing state */
.msg.editing{max-width:88%}
.msg.editing .msg-bubble{padding:0;background:transparent}
.edit-input{width:100%;min-height:56px;max-height:40vh;resize:vertical;font:inherit;font-size:.93rem;background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:10px;padding:10px 12px;line-height:1.5}
.edit-input:focus{border-color:var(--accent);outline:none}
.edit-actions{display:flex;gap:8px;margin-top:6px;justify-content:flex-end}
.edit-actions button{padding:6px 14px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--muted);font-size:.82rem;cursor:pointer}
.edit-actions button:hover{border-color:var(--muted)}
.edit-actions button.primary{background:var(--accent);color:#000;border-color:var(--accent);font-weight:600}
.edit-actions button.primary:hover{opacity:.85}

/* Auto-chat toggle */
.auto-toggle{display:flex;align-items:center;gap:5px;cursor:pointer;white-space:nowrap;font-size:.82rem;color:var(--muted);user-select:none;flex-shrink:0}
.auto-toggle input{accent-color:var(--accent);margin:0;width:15px;height:15px}
.auto-toggle.on{color:var(--accent)}
#send.stop-btn{background:#F87171}

/* Status & Input */
.status-bar{text-align:center;font-size:.8rem;color:var(--muted);padding:4px 16px;min-height:24px;flex-shrink:0}
.typing-dot{display:inline-block;width:6px;height:6px;border-radius:50%;margin-right:6px;vertical-align:middle;animation:pulse 1.2s infinite}
@keyframes pulse{0%,100%{opacity:.3}50%{opacity:1}}
.input-area{display:flex;padding:10px 16px 14px;gap:8px;background:var(--surface);border-top:1px solid var(--border);flex-shrink:0}
#input{flex:1;background:var(--card);border:1px solid var(--border);color:var(--text);padding:10px 14px;border-radius:20px;font-size:.93rem;resize:none;outline:none;max-height:120px;font-family:inherit;line-height:1.4}
#input:focus{border-color:var(--accent)}
#input::placeholder{color:var(--muted)}
#send{background:var(--accent);color:#000;border:none;padding:10px 20px;border-radius:20px;font-size:.93rem;font-weight:600;cursor:pointer;transition:opacity .2s;white-space:nowrap}
#send:disabled{opacity:.35;cursor:default}

/* ---- Settings Panel ---- */
.settings-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100}
.settings-overlay.open{display:block}
.settings-panel{position:fixed;top:0;right:-420px;width:400px;max-width:100vw;height:100dvh;background:var(--surface);border-left:1px solid var(--border);z-index:101;display:flex;flex-direction:column;transition:right .3s}
.settings-panel.open{right:0}
.sp-header{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid var(--border)}
.sp-header h2{font-size:1.05rem;font-weight:700}
.sp-close{background:transparent;border:none;color:var(--muted);font-size:1.2rem;cursor:pointer;padding:4px 8px}
.sp-body{flex:1;overflow-y:auto;padding:12px 16px;display:flex;flex-direction:column;gap:14px}
.sp-footer{padding:12px 16px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:space-between}

/* Character card */
.cc{background:var(--card);border-radius:12px;padding:14px;border:1px solid var(--border)}
.cc-head{display:flex;align-items:center;gap:8px;margin-bottom:10px}
.cc-dot{width:14px;height:14px;border-radius:50%;cursor:pointer;border:2px solid transparent;flex-shrink:0}
.cc-dot:hover{border-color:var(--text)}
.cc-name{flex:1;background:transparent;border:1px solid var(--border);border-radius:6px;padding:5px 8px;color:var(--text);font-size:.9rem;font-weight:600;outline:none}
.cc-name:focus{border-color:var(--accent)}
.cc-del{background:transparent;border:none;color:var(--muted);cursor:pointer;font-size:.9rem;padding:2px 6px;border-radius:4px}
.cc-del:hover{color:#F87171;background:rgba(248,113,113,.1)}
.cc-row{display:flex;gap:8px;margin-bottom:8px}
.cc-select{flex:1;background:var(--bg);border:1px solid var(--border);border-radius:6px;padding:5px 8px;color:var(--text);font-size:.82rem;outline:none}
.cc-select:focus{border-color:var(--accent)}
.cc-textarea{width:100%;background:var(--bg);border:1px solid var(--border);border-radius:6px;padding:6px 8px;color:var(--text);font-size:.82rem;resize:vertical;outline:none;min-height:48px;font-family:inherit;line-height:1.4}
.cc-textarea:focus{border-color:var(--accent)}
.cc-label{font-size:.75rem;color:var(--muted);margin:6px 0 3px 2px;font-weight:600}
.cc-colors{display:flex;gap:6px;margin-top:8px}
.cc-color{width:20px;height:20px;border-radius:50%;cursor:pointer;border:2px solid transparent;transition:border-color .15s}
.cc-color:hover,.cc-color.sel{border-color:#fff}

.sp-add{width:100%;padding:10px;background:transparent;border:1px dashed var(--border);border-radius:12px;color:var(--muted);cursor:pointer;font-size:.9rem}
.sp-add:hover{border-color:var(--accent);color:var(--accent)}
.sp-btn{padding:8px 18px;border:none;border-radius:8px;font-size:.9rem;font-weight:600;cursor:pointer}
.sp-save{background:var(--accent);color:#000}
.sp-reset{background:transparent;border:1px solid var(--border)!important;color:var(--muted)}
.sp-reset:hover{border-color:var(--muted)!important}

@media(max-width:600px){
    .title{font-size:1rem}
    .modes{gap:2px}
    .mode-btn{padding:4px 8px;font-size:.75rem}
    .chars-bar{gap:8px;padding:6px 10px}
    .msg{max-width:90%}
    #send{padding:10px 14px}
    .settings-panel{width:100vw}
    .sidebar{width:100vw;max-width:100vw}
}

/* Sidebar */
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100}
.sidebar-overlay.open{display:block}
.sidebar{position:fixed;top:0;left:-320px;width:300px;max-width:85vw;height:100dvh;background:var(--surface);border-right:1px solid var(--border);z-index:101;display:flex;flex-direction:column;transition:left .3s}
.sidebar.open{left:0}
.sb-header{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid var(--border)}
.sb-header h2{font-size:1.05rem;font-weight:700}
.sb-new{background:var(--accent);color:#000;border:none;padding:6px 14px;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer}
.sb-list{flex:1;overflow-y:auto;padding:8px}
.sb-empty{padding:30px 16px;text-align:center;color:var(--muted);font-size:.85rem}
.conv-item{padding:10px 12px;border-radius:8px;cursor:pointer;position:relative;margin-bottom:2px;transition:background .15s}
.conv-item:hover{background:var(--card)}
.conv-item.active{background:var(--card);border-left:3px solid var(--accent)}
.conv-title{font-size:.86rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;padding-right:24px}
.conv-meta{display:flex;gap:6px;font-size:.72rem;color:var(--muted);margin-top:3px}
.conv-del{display:none;position:absolute;right:8px;top:50%;transform:translateY(-50%);background:transparent;border:none;color:var(--muted);cursor:pointer;font-size:.8rem;padding:2px 6px;border-radius:4px}
.conv-item:hover .conv-del{display:block}
.conv-del:hover{color:#F87171;background:rgba(248,113,113,.1)}

/* Preset row in settings */
.sp-preset-row{display:flex;gap:6px;align-items:center;padding:10px 16px;border-bottom:1px solid var(--border)}
.sp-preset-btn{background:var(--card);border:1px solid var(--border);color:var(--text);padding:5px 10px;border-radius:6px;font-size:.78rem;cursor:pointer;white-space:nowrap}
.sp-preset-btn:hover{border-color:var(--accent);color:var(--accent)}
</style>
</head>
<body>

<?php if (empty($_SESSION['user'])): ?>
<div class="login-page">
    <form class="login-box" method="post">
        <h1>🏮</h1>
        <div class="sub">AI 居酒屋</div>
        <?php if ($loginError): ?><div class="login-error">帳號或密碼錯誤</div><?php endif; ?>
        <input name="username" placeholder="帳號" autocomplete="username" required autofocus>
        <input name="password" type="password" placeholder="密碼" autocomplete="current-password" required>
        <button type="submit">進入</button>
    </form>
</div>

<?php else: ?>
<div class="app">
    <header class="header">
        <button class="hdr-btn" title="對話紀錄" onclick="toggleSidebar()">☰</button>
        <div class="title">🏮 AI 居酒屋</div>
        <div class="modes">
            <button class="mode-btn active" data-mode="chat">💬 聊天</button>
            <button class="mode-btn" data-mode="debate">⚔️ 辯論</button>
            <button class="mode-btn" data-mode="story">📖 接龍</button>
        </div>
        <button class="hdr-btn" title="角色設定" onclick="toggleSettings()">⚙️</button>
        <button class="hdr-btn" title="清除對話" onclick="clearChat()">🗑️</button>
    </header>

    <div class="chars-bar" id="charsBar"></div>

    <div class="chat" id="chat">
        <div class="welcome">
            <div class="welcome-icon">🏮</div>
            <div class="welcome-text">歡迎來到 AI 居酒屋！<br>設定角色後，說點什麼開始聊天吧。</div>
        </div>
    </div>

    <div id="ctxMenu" class="ctx-menu" hidden></div>
    <div class="status-bar" id="status"></div>

    <div class="input-area">
        <label class="auto-toggle" id="autoLabel" title="開啟後 AI 會自動接力聊天">
            <input type="checkbox" id="autoChat">
            <span>掛機</span>
        </label>
        <textarea id="input" placeholder="跟大家說點什麼..." rows="1"></textarea>
        <button id="send">送出</button>
    </div>
</div>

<!-- Sidebar -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<div class="sidebar" id="sidebar">
    <div class="sb-header">
        <h2>📋 對話紀錄</h2>
        <button class="sb-new" onclick="newConv()">+ 新對話</button>
    </div>
    <div class="sb-list" id="convList"></div>
</div>

<!-- Settings panel -->
<div class="settings-overlay" id="settingsOverlay" onclick="toggleSettings()"></div>
<div class="settings-panel" id="settingsPanel">
    <div class="sp-header">
        <h2>⚙️ 角色設定</h2>
        <button class="sp-close" onclick="toggleSettings()">✕</button>
    </div>
    <div class="sp-preset-row">
        <select id="presetSelect" class="cc-select" style="flex:1"><option value="">— 身分預設組合 —</option></select>
        <button class="sp-preset-btn" onclick="loadSelectedPreset()">📂 載入</button>
        <button class="sp-preset-btn" onclick="saveAsPreset()">💾 儲存</button>
        <button class="sp-preset-btn" onclick="deleteSelectedPreset()">🗑️</button>
    </div>
    <div class="sp-body" id="settingsBody"></div>
    <div class="sp-footer">
        <button class="sp-btn sp-reset" onclick="resetDefaults()">重置預設</button>
        <div style="display:flex;gap:8px">
            <button class="sp-btn sp-save" onclick="saveAndClose()">儲存</button>
        </div>
    </div>
</div>

<script>
const CSRF      = <?= json_encode($_SESSION['csrf']) ?>;
const PROVIDERS = <?= json_encode($providers, JSON_UNESCAPED_UNICODE) ?>;
const PALETTE   = ['#A78BFA','#34D399','#F87171','#60A5FA','#FBBF24','#F472B6','#2DD4BF','#A3E635'];
const MAX_CHARS = 6;

const GENDERS = ['','男性','女性','其他'];

const DEFAULT_CHARS = [
    {id:'c0',name:'克勞德',gender:'男性',provider:'claude',model:'claude-haiku-5-5',color:'#A78BFA',
     background:'哲學系畢業，曾在大學當講師，後來轉行當心理諮商師。喜歡閱讀和思考人生意義。',
     personality:'思考周密，善於看到事物的多面性。說話溫和但有深度，偶爾引用哲學觀點。尊重每個人的觀點，但也會禮貌地提出不同看法。'},
    {id:'c1',name:'小G',gender:'女性',provider:'openai',model:'gpt-5.4-mini',color:'#34D399',
     background:'自由插畫家，喜歡到處旅行收集靈感。養了一隻橘貓叫「像素」。',
     personality:'充滿創意和熱情，喜歡用比喻和故事表達觀點。樂觀開朗，常從意想不到的角度看問題。說話活潑，偶爾用 emoji。'},
    {id:'c2',name:'格羅克',gender:'男性',provider:'grok',model:'grok-4.7',color:'#F87171',
     background:'前脫口秀演員，現在經營一個吐槽時事的 Podcast。自稱「真話製造機」。',
     personality:'說話直率、毒舌但幽默。喜歡吐槽和開玩笑，但見解犀利。不怕得罪人，一針見血指出問題。偶爾自嘲。'},
    {id:'c3',name:'雙子',gender:'',provider:'gemini',model:'gemini-3.6-flash',color:'#60A5FA',
     background:'在研究機構擔任資料科學家，業餘時間寫科普文章。書架上有三千本書。',
     personality:'知識淵博，喜歡引用數據和事實。分析能力強，說話條理清晰。有時過於認真，被其他人吐槽太嚴肅。'},
];

const MODE_DESC = {
    chat:  '💬 自由聊天模式 — 隨便聊什麼都行',
    debate:'⚔️ 辯論模式 — 提出一個議題，大家來辯論！',
    story: '📖 故事接龍模式 — 起一個頭，大家輪流編故事',
};

let characters = loadChars();
let userIdentity = loadUserIdentity();
let messages   = [];
let mode       = 'chat';
let streaming  = false;
let autoChat   = false;
let abortCtrl  = null;
let autoTimer  = null;
const AUTO_DELAY = 3000;
let currentConvId = null;
let nextId     = characters.reduce((m,c) => Math.max(m, parseInt(c.id.slice(1))||0), 0) + 1;

const $chat   = document.getElementById('chat');
const $input  = document.getElementById('input');
const $send   = document.getElementById('send');
const $status = document.getElementById('status');

// ---- LocalStorage ----
function loadChars() {
    try {
        const d = JSON.parse(localStorage.getItem('aigroup_chars'));
        if (Array.isArray(d) && d.length > 0) return d;
    } catch {}
    return structuredClone(DEFAULT_CHARS);
}
function saveChars() {
    localStorage.setItem('aigroup_chars', JSON.stringify(characters));
}
function loadUserIdentity() {
    try {
        const d = JSON.parse(localStorage.getItem('aigroup_user'));
        if (d && d.name) return d;
    } catch {}
    return { name: '店長', background: '', scenario: '' };
}
function saveUserIdentity() {
    localStorage.setItem('aigroup_user', JSON.stringify(userIdentity));
}

// ---- Characters bar ----
function renderCharsBar() {
    const bar = document.getElementById('charsBar');
    bar.innerHTML = characters.map(c =>
        `<div class="char-badge"><span class="char-dot" style="background:${c.color}"></span><span>${esc(c.name)}</span></div>`
    ).join('');
}

// ---- Settings panel ----
function toggleSettings() {
    document.getElementById('sidebarOverlay').classList.remove('open');
    document.getElementById('sidebar').classList.remove('open');
    const o = document.getElementById('settingsOverlay');
    const p = document.getElementById('settingsPanel');
    const open = !p.classList.contains('open');
    o.classList.toggle('open', open);
    p.classList.toggle('open', open);
    if (open) { renderSettings(); loadPresets(); }
}

function renderSettings() {
    const body = document.getElementById('settingsBody');
    let html = `<div class="cc" style="border-color:var(--accent);border-style:dashed">
        <div class="cc-head" style="margin-bottom:8px">
            <span style="font-size:1rem">🎭</span>
            <span style="font-weight:700;font-size:.9rem;color:var(--accent)">場景與你的身分</span>
        </div>
        <div class="cc-label">場景描述</div>
        <textarea class="cc-textarea" id="set-scenario" rows="2" placeholder="例：深夜的居酒屋，窗外下著小雨，吧台上擺著半瓶清酒...">${esc(userIdentity.scenario||'')}</textarea>
        <div class="cc-label">你的名字</div>
        <input class="cc-name" id="set-username" value="${esc(userIdentity.name)}" maxlength="20" style="width:100%;margin-bottom:0" placeholder="店長">
        <div class="cc-label">你的背景</div>
        <textarea class="cc-textarea" id="set-userbg" rows="2" placeholder="例：經營這家居酒屋十年的老闆，最愛聊棒球和政治...">${esc(userIdentity.background||'')}</textarea>
    </div>
    <div style="text-align:center;color:var(--muted);font-size:.75rem;font-weight:600;letter-spacing:2px">— AI 角色 —</div>`;
    characters.forEach((c, i) => {
        html += `<div class="cc" data-idx="${i}">
            <div class="cc-head">
                <span class="cc-dot" style="background:${c.color}"></span>
                <input class="cc-name" value="${esc(c.name)}" data-field="name" maxlength="20" placeholder="角色名稱">
                ${characters.length > 1 ? `<button class="cc-del" onclick="removeChar(${i})" title="移除">✕</button>` : ''}
            </div>
            <div class="cc-row">
                <select class="cc-select" data-field="gender">
                    ${GENDERS.map(g =>
                        `<option value="${g}" ${g===(c.gender||'')?'selected':''}>${g||'性別（不設定）'}</option>`
                    ).join('')}
                </select>
                <select class="cc-select" data-field="provider" onchange="providerChanged(${i},this.value)">
                    ${Object.entries(PROVIDERS).map(([k,v]) =>
                        `<option value="${k}" ${k===c.provider?'selected':''}>${v.label}</option>`
                    ).join('')}
                </select>
                <select class="cc-select" data-field="model" id="model-${i}">
                    ${Object.entries(PROVIDERS[c.provider].models).map(([k,v]) =>
                        `<option value="${k}" ${k===c.model?'selected':''}>${v}</option>`
                    ).join('')}
                </select>
            </div>
            <div class="cc-label">背景</div>
            <textarea class="cc-textarea" data-field="background" rows="2" placeholder="職業、來歷、年齡等角色設定...">${esc(c.background||'')}</textarea>
            <div class="cc-label">性格</div>
            <textarea class="cc-textarea" data-field="personality" rows="2" placeholder="說話方式、性格特徵...">${esc(c.personality||'')}</textarea>
            <div class="cc-colors">
                ${PALETTE.map(col =>
                    `<span class="cc-color ${col===c.color?'sel':''}" style="background:${col}" onclick="pickColor(${i},'${col}')"></span>`
                ).join('')}
            </div>
        </div>`;
    });
    if (characters.length < MAX_CHARS) {
        html += `<button class="sp-add" onclick="addChar()">+ 新增角色</button>`;
    }
    body.innerHTML = html;
}

function providerChanged(idx, prov) {
    const models = PROVIDERS[prov]?.models || {};
    const sel = document.getElementById('model-' + idx);
    sel.innerHTML = Object.entries(models).map(([k,v]) =>
        `<option value="${k}">${v}</option>`
    ).join('');
    characters[idx].provider = prov;
    characters[idx].model = Object.keys(models)[0] || '';
}

function pickColor(idx, col) {
    characters[idx].color = col;
    renderSettings();
}

function addChar() {
    const id = 'c' + (nextId++);
    characters.push({
        id, name: '新角色', gender: '', provider: 'openai', model: 'gpt-5.4-mini',
        color: PALETTE[characters.length % PALETTE.length], background: '', personality: '',
    });
    renderSettings();
}

function removeChar(idx) {
    if (characters.length <= 1) return;
    characters.splice(idx, 1);
    renderSettings();
}

function resetDefaults() {
    characters = structuredClone(DEFAULT_CHARS);
    nextId = 4;
    renderSettings();
}

function collectFromUI() {
    document.querySelectorAll('.cc').forEach((card, i) => {
        if (i >= characters.length) return;
        const name = card.querySelector('[data-field="name"]')?.value.trim();
        const gender = card.querySelector('[data-field="gender"]')?.value || '';
        const prov = card.querySelector('[data-field="provider"]')?.value;
        const model = card.querySelector('[data-field="model"]')?.value;
        const bg   = card.querySelector('[data-field="background"]')?.value.trim() || '';
        const pers = card.querySelector('[data-field="personality"]')?.value.trim() || '';
        if (name) characters[i].name = name;
        characters[i].gender = gender;
        if (prov) characters[i].provider = prov;
        if (model) characters[i].model = model;
        characters[i].background = bg;
        characters[i].personality = pers;
    });
}

function collectUserIdentity() {
    const n = (document.getElementById('set-username')?.value || '').trim();
    userIdentity.name = n || '店長';
    userIdentity.background = (document.getElementById('set-userbg')?.value || '').trim();
    userIdentity.scenario = (document.getElementById('set-scenario')?.value || '').trim();
}

function saveAndClose() {
    collectFromUI();
    collectUserIdentity();
    saveChars();
    saveUserIdentity();
    renderCharsBar();
    toggleSettings();
    messages = [];
    currentConvId = null;
    $chat.innerHTML = '<div class="welcome"><div class="welcome-icon">🏮</div>'
        + '<div class="welcome-text">角色已更新！說點什麼開始聊天吧。</div></div>';
}

// ---- Modes ----
document.querySelectorAll('.mode-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        if (streaming) return;
        const m = btn.dataset.mode;
        if (m === mode) return;
        document.querySelector('.mode-btn.active').classList.remove('active');
        btn.classList.add('active');
        mode = m;
        addSystem(MODE_DESC[m]);
    });
});

function clearChat() {
    if (streaming) stopAll();
    if (autoTimer) { clearTimeout(autoTimer); autoTimer = null; }
    autoChat = false;
    document.getElementById('autoChat').checked = false;
    document.getElementById('autoLabel').classList.remove('on');
    messages = [];
    currentConvId = null;
    $status.textContent = '';
    $chat.innerHTML = '<div class="welcome"><div class="welcome-icon">🏮</div>'
        + '<div class="welcome-text">歡迎來到 AI 居酒屋！<br>說點什麼開始聊天吧。</div></div>';
}

// ---- Send ----
async function send(auto) {
    const isAuto = auto === true;
    let userText = '';

    if (!isAuto) {
        userText = $input.value.trim();
        if (!userText || streaming) return;
        $input.value = '';
        $input.style.height = 'auto';

        const w = $chat.querySelector('.welcome');
        if (w) w.remove();

        messages.push({ character: 'user', content: userText });
        addMsg('user', userIdentity.name, userText, 'var(--accent2)', messages.length - 1);
    } else {
        if (streaming || messages.length === 0) return;
    }

    streaming = true;
    $send.textContent = '停止';
    $send.classList.add('stop-btn');
    $send.disabled = false;

    const charPayload = characters.map(c => ({
        id: c.id, name: c.name, gender: c.gender || '',
        provider: c.provider, model: c.model, color: c.color,
        background: c.background || '', personality: c.personality || '',
    }));

    if (!isAuto) {
        if (!currentConvId) {
            try {
                const r = await dbPost({ action: 'create', mode, characters: charPayload });
                currentConvId = r.id;
                loadConvList();
            } catch {}
        }
        dbPost({ action: 'append', id: currentConvId, character: 'user', content: userText }).catch(() => {});
    }

    abortCtrl = new AbortController();
    let curEl = null, curChar = null, curText = '';

    try {
        const res = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            signal: abortCtrl.signal,
            body: JSON.stringify({ mode, messages, characters: charPayload, user_name: userIdentity.name, user_background: userIdentity.background, scenario: userIdentity.scenario, auto_continue: isAuto }),
        });

        if (!res.ok) {
            const e = await res.json().catch(() => ({}));
            addSystem(e.error || '發生錯誤');
            return;
        }

        const reader = res.body.getReader();
        const dec = new TextDecoder();
        let buf = '';

        while (true) {
            const { done: eof, value } = await reader.read();
            if (eof) break;
            buf += dec.decode(value, { stream: true });
            const lines = buf.split('\n');
            buf = lines.pop();

            for (const line of lines) {
                if (!line.trim()) continue;
                let ev;
                try { ev = JSON.parse(line); } catch { continue; }

                switch (ev.type) {
                    case 'char_start':
                        curChar = ev.character;
                        curText = '';
                        $status.innerHTML = `<span class="typing-dot" style="background:${ev.color}"></span>${esc(ev.name)} 正在打字...`;
                        curEl = addMsg('ai', ev.name, '', ev.color);
                        break;
                    case 'text':
                        if (curEl) {
                            curText += ev.text;
                            curEl.querySelector('.msg-text').textContent = curText;
                            scrollEnd();
                        }
                        break;
                    case 'char_end':
                        if (curText && curChar) {
                            messages.push({ character: curChar, content: curText });
                            if (curEl) curEl.dataset.idx = messages.length - 1;
                            if (currentConvId) dbPost({ action:'append', id:currentConvId, character:curChar, content:curText }).catch(()=>{});
                        }
                        curEl = null;
                        curChar = null;
                        $status.textContent = '';
                        break;
                    case 'char_error':
                        if (curEl) {
                            const t = curEl.querySelector('.msg-text');
                            t.textContent = `（${ev.message}）`;
                            t.style.color = '#F87171';
                        }
                        curEl = null;
                        curChar = null;
                        curText = '';
                        $status.textContent = '';
                        break;
                }
            }
        }
    } catch (err) {
        if (err.name === 'AbortError') {
            if (curText && curChar) {
                messages.push({ character: curChar, content: curText });
                if (curEl) curEl.dataset.idx = messages.length - 1;
                if (currentConvId) dbPost({ action:'append', id:currentConvId, character:curChar, content:curText }).catch(()=>{});
            } else if (curEl) {
                curEl.remove();
            }
        } else {
            addSystem('連線失敗：' + err.message);
        }
    } finally {
        done();
    }
}

function stopAll() {
    autoChat = false;
    document.getElementById('autoChat').checked = false;
    document.getElementById('autoLabel').classList.remove('on');
    if (autoTimer) { clearTimeout(autoTimer); autoTimer = null; }
    if (abortCtrl) { abortCtrl.abort(); abortCtrl = null; }
    $status.textContent = '';
}

function done() {
    if (!streaming) return;
    streaming = false;
    abortCtrl = null;
    $send.textContent = '送出';
    $send.classList.remove('stop-btn');
    $send.disabled = false;

    if (autoChat && messages.length > 0) {
        $status.textContent = '掛機中，3 秒後繼續...';
        autoTimer = setTimeout(() => {
            autoTimer = null;
            $status.textContent = '';
            if (autoChat && !streaming) send(true);
        }, AUTO_DELAY);
    } else {
        $status.textContent = '';
    }
    $input.focus();
}

// ---- DOM helpers ----
function esc(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

function addMsg(type, name, text, color, idx) {
    const isUser = (type === 'user');
    const div = document.createElement('div');
    div.className = `msg msg-${isUser ? 'user' : 'ai'}`;
    if (idx !== undefined) div.dataset.idx = idx;

    const avatar = document.createElement('div');
    avatar.className = 'msg-avatar';
    avatar.style.background = color;
    avatar.textContent = name[0];

    const body = document.createElement('div');
    body.className = 'msg-body';

    const nm = document.createElement('div');
    nm.className = 'msg-name';
    nm.style.color = color;
    nm.textContent = name;

    const bubble = document.createElement('div');
    bubble.className = 'msg-bubble';
    const span = document.createElement('span');
    span.className = 'msg-text';
    span.textContent = text;
    bubble.appendChild(span);

    body.appendChild(nm);
    body.appendChild(bubble);

    if (isUser) { div.append(body, avatar); }
    else        { div.append(avatar, body); }

    $chat.appendChild(div);
    scrollEnd();
    return div;
}

function addSystem(text) {
    const div = document.createElement('div');
    div.className = 'system-msg';
    div.textContent = text;
    $chat.appendChild(div);
    scrollEnd();
}

function scrollEnd() {
    $chat.scrollTop = $chat.scrollHeight;
}

// ---- DB helpers ----
async function dbPost(data) {
    const r = await fetch('conversations_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify(data),
    });
    return r.json();
}

function timeAgo(ts) {
    const s = Math.floor(Date.now() / 1000) - ts;
    if (s < 60) return '剛剛';
    if (s < 3600) return Math.floor(s / 60) + ' 分鐘前';
    if (s < 86400) return Math.floor(s / 3600) + ' 小時前';
    if (s < 604800) return Math.floor(s / 86400) + ' 天前';
    return new Date(ts * 1000).toLocaleDateString('zh-TW');
}

// ---- Sidebar ----
function toggleSidebar() {
    document.getElementById('settingsOverlay').classList.remove('open');
    document.getElementById('settingsPanel').classList.remove('open');
    const o = document.getElementById('sidebarOverlay');
    const p = document.getElementById('sidebar');
    const open = !p.classList.contains('open');
    o.classList.toggle('open', open);
    p.classList.toggle('open', open);
    if (open) loadConvList();
}

async function loadConvList() {
    const list = await fetch('conversations_api.php?action=list').then(r => r.json()).catch(() => []);
    const el = document.getElementById('convList');
    if (!list.length) {
        el.innerHTML = '<div class="sb-empty">還沒有對話紀錄</div>';
        return;
    }
    const icons = { chat: '💬', debate: '⚔️', story: '📖' };
    el.innerHTML = list.map(c => `
        <div class="conv-item${c.id === currentConvId ? ' active' : ''}" onclick="switchConv('${c.id}')">
            <div class="conv-title">${esc(c.title)}</div>
            <div class="conv-meta"><span>${icons[c.mode] || '💬'}</span><span>${timeAgo(c.updated)}</span></div>
            <button class="conv-del" onclick="event.stopPropagation();deleteConv('${c.id}')" title="刪除">✕</button>
        </div>
    `).join('');
}

function newConv() {
    currentConvId = null;
    messages = [];
    $chat.innerHTML = '<div class="welcome"><div class="welcome-icon">🏮</div>'
        + '<div class="welcome-text">歡迎來到 AI 居酒屋！<br>說點什麼開始聊天吧。</div></div>';
    toggleSidebar();
}

async function switchConv(id) {
    if (streaming) return;
    const res = await dbPost({ action: 'get', id });
    if (res.error) return;
    currentConvId = id;
    mode = res.mode || 'chat';
    document.querySelectorAll('.mode-btn').forEach(b => b.classList.toggle('active', b.dataset.mode === mode));
    if (res.characters && res.characters.length) {
        characters = res.characters.map(c => ({
            id: c.char_id, name: c.name, gender: c.gender || '',
            provider: c.provider, model: c.model, color: c.color,
            background: c.background || '', personality: c.personality || '',
        }));
        saveChars();
        renderCharsBar();
    }
    messages = (res.messages || []).map(m => ({ character: m.character, content: m.content }));
    renderMessages();
    toggleSidebar();
}

async function deleteConv(id) {
    if (!confirm('確定要刪除這個對話嗎？')) return;
    await dbPost({ action: 'delete', id });
    if (currentConvId === id) {
        currentConvId = null;
        messages = [];
        $chat.innerHTML = '<div class="welcome"><div class="welcome-icon">🏮</div>'
            + '<div class="welcome-text">歡迎來到 AI 居酒屋！<br>說點什麼開始聊天吧。</div></div>';
    }
    loadConvList();
}

function renderMessages() {
    $chat.innerHTML = '';
    if (!messages.length) {
        $chat.innerHTML = '<div class="welcome"><div class="welcome-icon">🏮</div>'
            + '<div class="welcome-text">歡迎來到 AI 居酒屋！<br>說點什麼開始聊天吧。</div></div>';
        return;
    }
    const cm = {};
    characters.forEach(c => { cm[c.id] = c; });
    messages.forEach((m, i) => {
        if (m.character === 'user') {
            addMsg('user', userIdentity.name, m.content, 'var(--accent2)', i);
        } else {
            const c = cm[m.character];
            addMsg('ai', c ? c.name : '?', m.content, c ? c.color : '#9CA3AF', i);
        }
    });
}

// ---- Presets ----
async function loadPresets() {
    const list = await fetch('conversations_api.php?action=preset_list').then(r => r.json()).catch(() => []);
    const sel = document.getElementById('presetSelect');
    sel.innerHTML = '<option value="">— 身分預設組合 —</option>'
        + list.map(p => `<option value="${p.id}">${esc(p.name)}</option>`).join('');
}

async function saveAsPreset() {
    collectFromUI();
    const name = prompt('預設名稱：');
    if (!name || !name.trim()) return;
    const chars = characters.map(c => ({
        id: c.id, name: c.name, gender: c.gender || '', provider: c.provider,
        model: c.model, color: c.color, background: c.background || '', personality: c.personality || '',
    }));
    await dbPost({ action: 'preset_save', name: name.trim(), characters: chars });
    loadPresets();
}

async function loadSelectedPreset() {
    const id = document.getElementById('presetSelect').value;
    if (!id) return;
    const res = await dbPost({ action: 'preset_get', id });
    if (res.error || !res.characters) return;
    characters = res.characters.map((c, i) => ({
        id: c.id || 'c' + i, name: c.name, gender: c.gender || '',
        provider: c.provider, model: c.model, color: c.color || PALETTE[i % PALETTE.length],
        background: c.background || '', personality: c.personality || '',
    }));
    nextId = characters.reduce((m, c) => Math.max(m, parseInt(c.id.slice(1)) || 0), 0) + 1;
    renderSettings();
}

async function deleteSelectedPreset() {
    const sel = document.getElementById('presetSelect');
    if (!sel.value) return;
    if (!confirm('確定刪除此預設？')) return;
    await dbPost({ action: 'preset_delete', id: sel.value });
    loadPresets();
}

// ---- Context menu & message actions ----
const $ctx = document.getElementById('ctxMenu');

function getMsgEl(el) { return el.closest('.msg.msg-user, .msg.msg-ai'); }

function showCtxMenu(msgEl, x, y) {
    if (streaming) return;
    const idx = parseInt(msgEl.dataset.idx, 10);
    if (isNaN(idx)) return;
    const isUser = msgEl.classList.contains('msg-user');

    $ctx.innerHTML = '';
    const mkBtn = (label, fn) => {
        const b = document.createElement('button');
        b.textContent = label;
        b.addEventListener('click', () => { hideCtxMenu(); fn(); });
        $ctx.appendChild(b);
    };
    mkBtn('複製', () => {
        navigator.clipboard.writeText(messages[idx]?.content || '').catch(() => {});
    });
    mkBtn('編輯', () => editMessage(idx, isUser));
    if (isUser) {
        mkBtn('重新送出', () => resendMessage(idx));
    } else {
        mkBtn('重新產生', () => regenerateRound(idx));
    }
    mkBtn('刪除至底', () => deleteFromMessage(idx));

    $ctx.hidden = false;
    const rect = $ctx.getBoundingClientRect();
    $ctx.style.left = Math.max(8, Math.min(x, window.innerWidth - rect.width - 8)) + 'px';
    $ctx.style.top = Math.max(8, Math.min(y, window.innerHeight - rect.height - 8)) + 'px';
}

function hideCtxMenu() { $ctx.hidden = true; }

function findRoundStart(idx) {
    let i = idx;
    while (i > 0 && messages[i - 1]?.character !== 'user') i--;
    if (i > 0 && messages[i - 1]?.character === 'user') return i - 1;
    return i;
}

async function resendMessage(idx) {
    if (streaming) return;
    const content = messages[idx].content;
    messages = messages.slice(0, idx);
    if (currentConvId) await dbPost({ action: 'truncate', id: currentConvId, keep: idx });
    renderMessages();
    $input.value = content;
    $input.style.height = 'auto';
    $input.style.height = Math.min($input.scrollHeight, 120) + 'px';
    send();
}

async function regenerateRound(idx) {
    if (streaming) return;
    const userIdx = findRoundStart(idx);
    if (messages[userIdx]?.character !== 'user') return;
    const content = messages[userIdx].content;
    messages = messages.slice(0, userIdx);
    if (currentConvId) await dbPost({ action: 'truncate', id: currentConvId, keep: userIdx });
    renderMessages();
    $input.value = content;
    $input.style.height = 'auto';
    $input.style.height = Math.min($input.scrollHeight, 120) + 'px';
    send();
}

async function deleteFromMessage(idx) {
    if (streaming) return;
    const count = messages.length - idx;
    if (!confirm(`確定要刪除${count > 1 ? '這 ' + count + ' 則訊息' : '這則訊息'}嗎？`)) return;
    messages = messages.slice(0, idx);
    if (currentConvId) await dbPost({ action: 'truncate', id: currentConvId, keep: idx });
    renderMessages();
}

async function editMessage(idx, isUser) {
    if (streaming) return;
    const content = messages[idx].content;
    const msgEl = document.querySelector(`.msg[data-idx="${idx}"]`);
    if (!msgEl) return;

    const origHTML = msgEl.innerHTML;
    const origClass = msgEl.className;
    msgEl.className = (isUser ? 'msg msg-user' : 'msg msg-ai') + ' editing';
    msgEl.innerHTML = '';

    const textarea = document.createElement('textarea');
    textarea.className = 'edit-input';
    textarea.value = content;
    msgEl.appendChild(textarea);

    const actions = document.createElement('div');
    actions.className = 'edit-actions';

    const cancelBtn = document.createElement('button');
    cancelBtn.textContent = '取消';
    cancelBtn.addEventListener('click', () => {
        msgEl.className = origClass;
        msgEl.innerHTML = origHTML;
    });

    const confirmBtn = document.createElement('button');
    confirmBtn.className = 'primary';
    confirmBtn.textContent = isUser ? '送出' : '儲存';
    confirmBtn.addEventListener('click', async () => {
        const val = textarea.value.trim();
        if (!val) return;
        if (isUser) {
            messages = messages.slice(0, idx);
            if (currentConvId) await dbPost({ action: 'truncate', id: currentConvId, keep: idx });
            renderMessages();
            $input.value = val;
            $input.style.height = 'auto';
            $input.style.height = Math.min($input.scrollHeight, 120) + 'px';
            send();
        } else {
            messages[idx].content = val;
            messages = messages.slice(0, idx + 1);
            if (currentConvId) {
                await dbPost({ action: 'truncate', id: currentConvId, keep: idx });
                await dbPost({ action: 'append', id: currentConvId, character: messages[idx].character, content: val });
            }
            renderMessages();
        }
    });

    actions.append(cancelBtn, confirmBtn);
    msgEl.appendChild(actions);
    textarea.focus();
    textarea.style.height = 'auto';
    textarea.style.height = textarea.scrollHeight + 2 + 'px';
}

// Long-press & right-click
let pressTimer = null, pressPos = null;
const LONG_PRESS_MS = 500, MOVE_THRESHOLD = 10;

function startPress(e, x, y) {
    const msgEl = getMsgEl(e.target);
    if (!msgEl || msgEl.dataset.idx === undefined) return;
    pressPos = { x, y };
    pressTimer = setTimeout(() => {
        pressTimer = null;
        window.getSelection()?.removeAllRanges();
        showCtxMenu(msgEl, x, y);
    }, LONG_PRESS_MS);
}
function movePress(x, y) {
    if (!pressTimer || !pressPos) return;
    if (Math.abs(x - pressPos.x) > MOVE_THRESHOLD || Math.abs(y - pressPos.y) > MOVE_THRESHOLD) {
        clearTimeout(pressTimer); pressTimer = null;
    }
}
function endPress() { if (pressTimer) { clearTimeout(pressTimer); pressTimer = null; } }

$chat.addEventListener('touchstart', e => { startPress(e, e.touches[0].clientX, e.touches[0].clientY); }, { passive: true });
$chat.addEventListener('touchmove', e => { movePress(e.touches[0].clientX, e.touches[0].clientY); }, { passive: true });
$chat.addEventListener('touchend', endPress);
$chat.addEventListener('touchcancel', endPress);
$chat.addEventListener('mousedown', e => { if (e.button === 0) startPress(e, e.clientX, e.clientY); });
document.addEventListener('mousemove', e => movePress(e.clientX, e.clientY));
document.addEventListener('mouseup', endPress);

$chat.addEventListener('contextmenu', e => {
    const msgEl = getMsgEl(e.target);
    if (!msgEl || msgEl.dataset.idx === undefined) return;
    e.preventDefault();
    endPress();
    showCtxMenu(msgEl, e.clientX, e.clientY);
});

document.addEventListener('click', e => { if (!$ctx.contains(e.target)) hideCtxMenu(); });
document.addEventListener('touchstart', e => { if (!$ctx.hidden && !$ctx.contains(e.target)) hideCtxMenu(); }, { passive: true });

// ---- Events ----
$send.addEventListener('click', () => {
    if (streaming) stopAll();
    else send();
});
$input.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
});
$input.addEventListener('input', () => {
    $input.style.height = 'auto';
    $input.style.height = Math.min($input.scrollHeight, 120) + 'px';
});

document.getElementById('autoChat').addEventListener('change', function() {
    autoChat = this.checked;
    document.getElementById('autoLabel').classList.toggle('on', autoChat);
    if (autoChat && !streaming && messages.length > 0) {
        send(true);
    }
    if (!autoChat) {
        if (autoTimer) { clearTimeout(autoTimer); autoTimer = null; }
        if (!streaming) $status.textContent = '';
    }
});

// ---- Init ----
renderCharsBar();
loadPresets();
</script>
<?php endif; ?>
</body>
</html>
