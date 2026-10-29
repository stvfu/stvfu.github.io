<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/claude_cli.php';

const LOGIN_MAX_FAILS = 5;      // 同一 IP 連續失敗次數上限
const LOGIN_LOCK_SECONDS = 600; // 超過後鎖定時間

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function throttle_file(): string
{
    return sys_get_temp_dir() . '/claude_chat_login_' . md5($_SERVER['REMOTE_ADDR'] ?? '') . '.json';
}

function login_fails(): array
{
    $d = json_decode((string) @file_get_contents(throttle_file()), true);
    if (!is_array($d) || time() - ($d['since'] ?? 0) > LOGIN_LOCK_SECONDS) {
        return ['count' => 0, 'since' => time()];
    }
    return $d;
}

$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok($_POST['csrf'] ?? null)) {
        $loginError = '頁面已過期，請再試一次。';
    } elseif (($_POST['action'] ?? '') === 'logout') {
        $_SESSION = [];
        session_destroy();
        header('Location: index.php');
        exit;
    } elseif (($_POST['action'] ?? '') === 'login') {
        $fails = login_fails();
        if ($fails['count'] >= LOGIN_MAX_FAILS) {
            $loginError = '登入失敗次數過多，請 10 分鐘後再試。';
        } elseif (
            hash_equals($config['username'], (string) ($_POST['username'] ?? ''))
            && password_verify((string) ($_POST['password'] ?? ''), $config['password_hash'])
        ) {
            @unlink(throttle_file());
            session_regenerate_id(true);
            $_SESSION['user'] = $config['username'];
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            header('Location: index.php');
            exit;
        } else {
            $fails['count']++;
            @file_put_contents(throttle_file(), json_encode($fails), LOCK_EX);
            sleep(1);
            $loginError = '帳號或密碼錯誤。';
        }
    }
}

$loggedIn = is_logged_in();
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>AI 對話</title>
<style>
:root {
    --bg: #f5f4ef; --panel: #ffffff; --border: #dedcd3; --text: #1f1e1c; --muted: #77746b;
    --accent: #c96442; --accent-text: #ffffff; --user: #e9e6dc; --code: #f0eee6; --error: #b3261e;
}
@media (prefers-color-scheme: dark) {
    :root {
        --bg: #1f1e1c; --panel: #2a2926; --border: #3d3b36; --text: #ecebe6; --muted: #9c998f;
        --accent: #d97757; --accent-text: #1f1e1c; --user: #3a3833; --code: #191816; --error: #f2998f;
    }
}
* { box-sizing: border-box; }
html, body { height: 100%; margin: 0; }
body {
    background: var(--bg); color: var(--text);
    font: 16px/1.65 -apple-system, "Segoe UI", "Noto Sans TC", "PingFang TC", "Microsoft JhengHei", sans-serif;
}
button, select, textarea, input { font: inherit; color: inherit; }
button { cursor: pointer; border: 1px solid var(--border); background: var(--panel); border-radius: 8px; padding: 6px 12px; }
button:hover { border-color: var(--muted); }
button.primary { background: var(--accent); color: var(--accent-text); border-color: var(--accent); transition: background-color .3s, border-color .3s, color .3s; }
button:disabled { opacity: .5; cursor: default; }
select, textarea, input[type=text], input[type=password] {
    background: var(--panel); border: 1px solid var(--border); border-radius: 8px; padding: 8px 10px;
}
select:focus, textarea:focus, input:focus, button:focus-visible { outline: 2px solid var(--accent); outline-offset: 1px; transition: outline-color .3s; }
.msg a { transition: color .3s; }
.cursor::after { transition: color .3s; }

/* 登入 */
.login { min-height: 100%; display: flex; align-items: center; justify-content: center; padding: 16px; }
.login form { width: 100%; max-width: 340px; background: var(--panel); border: 1px solid var(--border); border-radius: 14px; padding: 28px; }
.login h1 { margin: 0 0 18px; font-size: 22px; }
.login label { display: block; font-size: 14px; color: var(--muted); margin: 12px 0 4px; }
.login input { width: 100%; }
.login button { width: 100%; margin-top: 20px; padding: 10px; }
.login .err { color: var(--error); font-size: 14px; margin-top: 12px; }

/* 對話 */
.app { height: 100%; display: flex; flex-direction: row; }
header { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 10px 16px; border-bottom: 1px solid var(--border); background: var(--panel); }
header h1 { font-size: 17px; margin: 0 8px 0 0; }
header label { font-size: 13px; color: var(--muted); display: flex; align-items: center; gap: 6px; }
header select { padding: 5px 8px; font-size: 14px; }
header .spacer { flex: 1; }
header form { margin: 0; }
#systemWrap { padding: 10px 16px; border-bottom: 1px solid var(--border); background: var(--panel); }
#systemWrap textarea { width: 100%; min-height: 70px; resize: vertical; font-size: 14px; }
#systemWrap[hidden] { display: none; }
.banner { padding: 10px 16px; background: var(--user); color: var(--error); font-size: 14px; border-bottom: 1px solid var(--border); }
#chat { flex: 1; overflow-y: auto; padding: 20px 16px; }
#chat .inner { max-width: 800px; margin: 0 auto; display: flex; flex-direction: column; gap: 18px; }
.empty { color: var(--muted); text-align: center; margin-top: 18vh; }
.msg { min-width: 0; overflow-wrap: anywhere; -webkit-touch-callout: none; }
.msg.user { align-self: flex-end; max-width: 85%; background: var(--user); border-radius: 14px; padding: 10px 14px; white-space: pre-wrap; }
.msg.assistant > .body > :first-child { margin-top: 0; }
.msg.assistant > .body > :last-child { margin-bottom: 0; }
.msg.assistant .body.plain { white-space: pre-wrap; }
.msg pre { background: var(--code); border: 1px solid var(--border); border-radius: 8px; padding: 12px; overflow-x: auto; font-size: 14px; line-height: 1.5; }
.msg code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .9em; background: var(--code); padding: 1px 5px; border-radius: 4px; }
.msg pre code { background: none; padding: 0; font-size: inherit; }
.msg table { border-collapse: collapse; display: block; overflow-x: auto; }
.msg th, .msg td { border: 1px solid var(--border); padding: 4px 10px; }
.msg blockquote { margin: 0; padding-left: 12px; border-left: 3px solid var(--border); color: var(--muted); }
.msg img { max-width: 100%; }
.msg a { color: var(--accent); }
details.thinking { color: var(--muted); font-size: 14px; margin-bottom: 8px; }
details.thinking summary { cursor: pointer; }
details.thinking div { white-space: pre-wrap; border-left: 2px solid var(--border); padding-left: 10px; margin-top: 6px; }
.meta { color: var(--muted); font-size: 12px; margin-top: 6px; }
.notice { color: var(--error); font-size: 14px; margin-top: 6px; }
.ctx-menu {
    position: fixed; z-index: 200; background: var(--panel); border: 1px solid var(--border);
    border-radius: 10px; padding: 4px 0; box-shadow: 0 4px 16px rgba(0,0,0,.18);
    min-width: 130px;
}
.ctx-menu button {
    display: block; width: 100%; text-align: left; border: none; background: none;
    padding: 10px 16px; font-size: 15px; border-radius: 0;
}
.ctx-menu button:hover { background: var(--user); }
.ctx-menu[hidden] { display: none; }
.msg.editing { max-width: 85%; }
.edit-input {
    width: 100%; min-height: 60px; max-height: 40vh; resize: vertical;
    font: inherit; background: var(--bg); border: 1px solid var(--border);
    border-radius: 8px; padding: 8px 10px; line-height: 1.5;
}
.edit-input:focus { outline: 2px solid var(--accent); outline-offset: 1px; }
.edit-actions { display: flex; gap: 8px; margin-top: 8px; justify-content: flex-end; }
.cursor::after { content: "▍"; color: var(--accent); animation: blink 1s steps(2) infinite; }
@keyframes blink { 50% { opacity: 0; } }
footer { border-top: 1px solid var(--border); background: var(--panel); padding: 12px 16px; }
footer .inner { max-width: 800px; margin: 0 auto; display: flex; gap: 8px; align-items: flex-end; }
#input { flex: 1; resize: none; max-height: 40vh; min-height: 44px; }
footer button { height: 44px; min-width: 72px; }

/* Sidebar */
.sidebar {
    width: 260px; background: var(--panel); border-right: 1px solid var(--border);
    display: flex; flex-direction: column; flex-shrink: 0;
    transition: margin-left .3s, transform .3s;
}
.sidebar.collapsed { margin-left: -260px; width: 260px; }
.sidebar-header { padding: 10px; border-bottom: 1px solid var(--border); display: flex; gap: 6px; }
.sidebar-header button { flex: 1; }
.sidebar-list { flex: 1; overflow-y: auto; padding: 6px; }
.conv-item {
    padding: 10px 12px; border-radius: 8px; cursor: pointer; font-size: 14px;
    display: flex; align-items: center; gap: 8px; margin-bottom: 2px;
}
.conv-item:hover { background: var(--user); }
.conv-item.active { background: var(--user); }
.conv-item .conv-title {
    flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; line-height: 1.4;
}
.conv-item .conv-title small { display: block; font-size: 11px; color: var(--muted); font-weight: normal; }
.conv-item.active .conv-title { font-weight: 600; }
.conv-item .conv-del {
    opacity: 0; border: none; background: none; padding: 2px 6px;
    font-size: 18px; color: var(--muted); flex-shrink: 0; cursor: pointer; border-radius: 4px;
}
.conv-item:hover .conv-del { opacity: .6; }
.conv-item .conv-del:hover { opacity: 1; background: var(--border); }
.main-area { flex: 1; display: flex; flex-direction: column; min-width: 0; overflow: hidden; }
#menuToggle { background: none; border: none; font-size: 20px; padding: 4px 8px; line-height: 1; }
#sidebarOverlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 99; }
#sidebarOverlay.open { display: block; }
@media (max-width: 768px) {
    .sidebar {
        position: fixed; top: 0; left: 0; bottom: 0; z-index: 100;
        width: 280px; transform: translateX(-100%); margin-left: 0;
        transition: transform .3s;
    }
    .sidebar.collapsed { margin-left: 0; transform: translateX(-100%); }
    .sidebar:not(.collapsed) { transform: translateX(0); }
    .main-area { width: 100%; }
}
</style>
</head>
<body>
<?php if (!$loggedIn): ?>
<main class="login">
    <form method="post" action="index.php" autocomplete="on">
        <h1>AI 對話</h1>
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
        <label for="username">帳號</label>
        <input type="text" id="username" name="username" autocomplete="username" required autofocus>
        <label for="password">密碼</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
        <button type="submit" class="primary">登入</button>
        <?php if ($loginError !== ''): ?><div class="err"><?= h($loginError) ?></div><?php endif; ?>
    </form>
</main>
<?php else: ?>
<div class="app">
    <aside class="sidebar collapsed" id="sidebar">
        <div class="sidebar-header">
            <button type="button" id="newChatSidebar" class="primary">+ 新對話</button>
        </div>
        <div class="sidebar-list" id="convList"></div>
    </aside>
    <div id="sidebarOverlay"></div>
    <div class="main-area">
    <header>
        <button type="button" id="menuToggle" title="對話列表">☰</button>
        <h1 id="appTitle">AI 對話</h1>
        <label>模型
            <select id="provider">
                <?php foreach (PROVIDERS as $pid => $pname): ?>
                <option value="<?= h($pid) ?>"><?= h($pname) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>版本
            <select id="model"></select>
        </label>
        <label>思考強度
            <select id="effort">
                <?php foreach (EFFORTS as $id => $label): ?>
                <option value="<?= h($id) ?>"<?= $id === DEFAULT_EFFORT ? ' selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="button" id="systemToggle">系統提示</button>
        <label title="不勾選：使用 server 上的 claude（訂閱額度）。勾選：使用 API 金鑰（另外計費）。">
            <input type="checkbox" id="apiMode"> API 模式
        </label>
        <label title="開啟後 Claude 可操作 server 檔案與執行指令（僅限訂閱模式）">
            <input type="checkbox" id="adminMode"> 管理員
        </label>
        <span class="spacer"></span>
        <button type="button" onclick="location.href='multi.php'">Multi-Agent</button>
        <button type="button" id="newChat">新對話</button>
        <form method="post" action="index.php">
            <input type="hidden" name="action" value="logout">
            <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
            <button type="submit">登出</button>
        </form>
    </header>
    <div id="systemWrap" hidden>
        <textarea id="system" placeholder="系統提示（選填）：例如「你是一位日文翻譯，請用繁體中文回答」"></textarea>
    </div>
    <div class="banner" id="banner" hidden></div>
    <main id="chat"><div class="inner" id="messages"></div></main>
    <footer>
        <div class="inner">
            <textarea id="input" rows="1" placeholder="輸入訊息… Enter 送出，Shift+Enter 換行" autofocus></textarea>
            <button type="button" id="send" class="primary">送出</button>
        </div>
    </footer>
    </div><!-- .main-area -->
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/marked/12.0.2/marked.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.1.6/purify.min.js"></script>
<script>
(() => {
    const CSRF = <?= json_encode($_SESSION['csrf']) ?>;
    const STATUS = <?= json_encode([
        'apiKey'     => api_key($config) !== '',
        'cliBin'     => cli_ready($config),
        'cliToken'   => ($config['claude_oauth_token'] ?? '') !== '',
        'openaiKey'  => openai_api_key($config) !== '',
        'xaiKey'     => xai_api_key($config) !== '',
        'geminiKey'  => google_api_key($config) !== '',
    ]) ?>;
    const ALL_MODELS = <?= json_encode(array_map(fn($m) => [
        'version' => $m['version'], 'adaptive' => $m['adaptive'], 'provider' => $m['provider'],
    ], MODELS), JSON_UNESCAPED_UNICODE) ?>;
    const DEFAULT_MODEL_ID = <?= json_encode(DEFAULT_MODEL) ?>;
    const SETTINGS_KEY = 'virtual_agent_settings';
    const STOP_NOTICES = {
        max_tokens: '回覆已達長度上限而中斷，可以輸入「繼續」請它接著寫。',
        refusal: 'AI 拒絕了這個請求，這則訊息不會保留在對話紀錄中。',
    };

    const PROVIDER_THEMES = {
        claude:  { name: 'Claude',  light: { accent: '#c96442', accentText: '#ffffff' }, dark: { accent: '#d97757', accentText: '#1f1e1c' } },
        openai:  { name: 'ChatGPT', light: { accent: '#10a37f', accentText: '#ffffff' }, dark: { accent: '#1bc494', accentText: '#1f1e1c' } },
        grok:    { name: 'Grok',    light: { accent: '#1d9bf0', accentText: '#ffffff' }, dark: { accent: '#4ab3f4', accentText: '#1f1e1c' } },
        gemini:  { name: 'Gemini',  light: { accent: '#4285f4', accentText: '#ffffff' }, dark: { accent: '#669df6', accentText: '#1f1e1c' } },
        default: { name: 'AI',      light: { accent: '#6366f1', accentText: '#ffffff' }, dark: { accent: '#818cf8', accentText: '#1f1e1c' } },
    };

    function getProvider(modelId) {
        if (modelId.startsWith('claude-')) return 'claude';
        if (modelId.startsWith('gpt-') || modelId.startsWith('o1') || modelId.startsWith('o3') || modelId.startsWith('o4')) return 'openai';
        if (modelId.startsWith('grok-')) return 'grok';
        if (modelId.startsWith('gemini-')) return 'gemini';
        return 'default';
    }

    function applyTheme(modelId) {
        const provider = getProvider(modelId);
        const theme = PROVIDER_THEMES[provider] || PROVIDER_THEMES.default;
        const dark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const colors = dark ? theme.dark : theme.light;
        document.documentElement.style.setProperty('--accent', colors.accent);
        document.documentElement.style.setProperty('--accent-text', colors.accentText);
        const title = document.getElementById('appTitle');
        if (title) title.textContent = theme.name + ' 對話';
        document.title = theme.name + ' 對話';
    }

    const $ = id => document.getElementById(id);
    const els = {
        provider: $('provider'), model: $('model'), effort: $('effort'), system: $('system'), systemWrap: $('systemWrap'),
        systemToggle: $('systemToggle'), newChat: $('newChat'), chat: $('chat'),
        apiMode: $('apiMode'), adminMode: $('adminMode'), banner: $('banner'),
        messages: $('messages'), input: $('input'), send: $('send'),
        sidebar: $('sidebar'), convList: $('convList'),
        menuToggle: $('menuToggle'), overlay: $('sidebarOverlay'),
        newChatSidebar: $('newChatSidebar'),
    };

    let convList = [];
    let activeId = null;
    let messages = [];
    let controller = null;

    function isMobile() { return window.innerWidth <= 768; }

    // ---- Server API ----
    async function convApi(action, data = {}) {
        const res = await fetch('conversations_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({ action, ...data }),
        });
        const json = await res.json();
        if (!res.ok) console.error('convApi', action, res.status, json);
        return json;
    }

    // ---- Settings (localStorage — only preferences, no conversation data) ----
    function saveSettings() {
        try {
            localStorage.setItem(SETTINGS_KEY, JSON.stringify({
                model: els.model.value, effort: els.effort.value, system: els.system.value,
                apiMode: els.apiMode.checked, adminMode: els.adminMode.checked, activeId,
            }));
        } catch (e) {}
    }
    function loadSettings() {
        try {
            const s = JSON.parse(localStorage.getItem(SETTINGS_KEY) || 'null');
            if (!s) return;
            const modelInfo = ALL_MODELS[s.model];
            if (modelInfo) {
                els.provider.value = modelInfo.provider;
                populateModels(modelInfo.provider, s.model);
            }
            if ([...els.effort.options].some(o => o.value === s.effort)) els.effort.value = s.effort;
            els.system.value = s.system || '';
            els.apiMode.checked = !!s.apiMode;
            els.adminMode.checked = !!s.adminMode;
            if (s.activeId) activeId = s.activeId;
        } catch (e) {}
    }

    // ---- Sidebar ----
    function toggleSidebar() {
        els.sidebar.classList.toggle('collapsed');
        els.overlay.classList.toggle('open', !els.sidebar.classList.contains('collapsed'));
    }
    function closeSidebar() {
        els.sidebar.classList.add('collapsed');
        els.overlay.classList.remove('open');
    }

    function formatDate(ts) {
        const ms = ts * 1000;
        const diff = Date.now() - ms;
        if (diff < 60000) return '剛剛';
        if (diff < 3600000) return Math.floor(diff / 60000) + ' 分鐘前';
        if (diff < 86400000) return Math.floor(diff / 3600000) + ' 小時前';
        if (diff < 604800000) return Math.floor(diff / 86400000) + ' 天前';
        const d = new Date(ms);
        return (d.getMonth() + 1) + '/' + d.getDate();
    }

    function renderSidebar() {
        els.convList.innerHTML = '';
        for (const conv of convList) {
            const item = document.createElement('div');
            item.className = 'conv-item' + (conv.id === activeId ? ' active' : '');

            const titleDiv = document.createElement('div');
            titleDiv.className = 'conv-title';
            titleDiv.textContent = conv.title || '新對話';
            const small = document.createElement('small');
            if (conv.provider) {
                const theme = PROVIDER_THEMES[conv.provider] || PROVIDER_THEMES.default;
                const dark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const badge = document.createElement('span');
                badge.textContent = theme.name;
                badge.style.cssText = 'font-weight:600;color:' + (dark ? theme.dark.accent : theme.light.accent);
                small.appendChild(badge);
                small.appendChild(document.createTextNode(' · '));
            }
            small.appendChild(document.createTextNode(formatDate(conv.updated || conv.created)));
            titleDiv.appendChild(small);

            const del = document.createElement('button');
            del.className = 'conv-del';
            del.textContent = '×';
            del.title = '刪除對話';
            del.addEventListener('click', e => {
                e.stopPropagation();
                if (confirm('確定要刪除這段對話嗎？')) deleteConversation(conv.id);
            });

            item.appendChild(titleDiv);
            item.appendChild(del);
            item.addEventListener('click', () => {
                switchConversation(conv.id);
                if (isMobile()) closeSidebar();
            });
            els.convList.appendChild(item);
        }
    }

    // ---- Conversation operations ----
    async function newConversation() {
        const conv = await convApi('create');
        convList.unshift(conv);
        activeId = conv.id;
        messages = [];
        saveSettings();
        renderSidebar();
        renderAll();
        els.input.focus();
        if (isMobile()) closeSidebar();
    }

    async function switchConversation(id) {
        if (id === activeId) return;
        activeId = id;
        const data = await convApi('get', { id });
        messages = data.messages || [];
        saveSettings();
        renderSidebar();
        renderAll();
        els.input.focus();
    }

    async function deleteConversation(id) {
        await convApi('delete', { id });
        convList = convList.filter(c => c.id !== id);
        if (activeId === id) {
            if (convList.length) {
                activeId = convList[0].id;
                const data = await convApi('get', { id: activeId });
                messages = data.messages || [];
            } else {
                await newConversation();
                return;
            }
        }
        saveSettings();
        renderSidebar();
        renderAll();
    }

    // ---- 顯示 ----
    function renderMarkdown(el, text) {
        if (window.marked && window.DOMPurify) {
            el.classList.remove('plain');
            el.innerHTML = DOMPurify.sanitize(marked.parse(text, { breaks: true }));
            el.querySelectorAll('a').forEach(a => { a.target = '_blank'; a.rel = 'noopener noreferrer'; });
        } else {
            el.classList.add('plain');
            el.textContent = text;
        }
    }
    function nearBottom() {
        return els.chat.scrollHeight - els.chat.scrollTop - els.chat.clientHeight < 80;
    }
    function scrollDown() { els.chat.scrollTop = els.chat.scrollHeight; }

    function addUser(text, idx) {
        const div = document.createElement('div');
        div.className = 'msg user';
        if (idx !== undefined) div.dataset.idx = idx;
        div.textContent = text;
        els.messages.appendChild(div);
        return div;
    }
    function addAssistant(text, idx) {
        const div = document.createElement('div');
        div.className = 'msg assistant';
        if (idx !== undefined) div.dataset.idx = idx;
        const body = document.createElement('div');
        body.className = 'body';
        div.appendChild(body);
        if (text) renderMarkdown(body, text);
        els.messages.appendChild(div);
        return { div, body };
    }
    function addLine(parent, cls, text) {
        const d = document.createElement('div');
        d.className = cls;
        d.textContent = text;
        parent.appendChild(d);
    }
    function renderAll() {
        els.messages.innerHTML = '';
        if (!messages.length) {
            addLine(els.messages, 'empty', '選擇模型後，在下方輸入訊息開始對話。');
            return;
        }
        for (let i = 0; i < messages.length; i++) {
            const m = messages[i];
            m.role === 'user' ? addUser(m.content, i) : addAssistant(m.content, i);
        }
        scrollDown();
    }

    function populateModels(provider, selectModelId) {
        els.model.innerHTML = '';
        let first = null;
        for (const [id, m] of Object.entries(ALL_MODELS)) {
            if (m.provider !== provider) continue;
            if (!first) first = id;
            const opt = document.createElement('option');
            opt.value = id;
            opt.textContent = m.version;
            els.model.appendChild(opt);
        }
        if (selectModelId && [...els.model.options].some(o => o.value === selectModelId)) {
            els.model.value = selectModelId;
        } else if (first) {
            els.model.value = first;
        }
    }
    function syncEffort() {
        const modelInfo = ALL_MODELS[els.model.value];
        const adaptive = modelInfo ? modelInfo.adaptive : false;
        els.effort.disabled = !adaptive;
        els.effort.title = adaptive ? '' : '此模型不支援調整思考強度';
    }
    function syncAdmin() {
        const provider = getProvider(els.model.value);
        const disabled = els.apiMode.checked || provider !== 'claude';
        els.adminMode.disabled = disabled;
        if (disabled) els.adminMode.checked = false;
        els.adminMode.parentElement.title = disabled
            ? '管理員模式僅限 Claude 訂閱模式使用' : '開啟後 Claude 可操作 server 檔案與執行指令';
    }
    function syncMode() {
        const provider = getProvider(els.model.value);
        const nonClaude = provider !== 'claude';
        els.apiMode.disabled = nonClaude;
        if (nonClaude) els.apiMode.checked = false;
        els.apiMode.parentElement.title = nonClaude ? '非 Claude 模型固定使用 API 模式' : '';

        let msg = '';
        if (nonClaude) {
            const keyMap = { openai: STATUS.openaiKey, grok: STATUS.xaiKey, gemini: STATUS.geminiKey };
            if (!(keyMap[provider] ?? false)) {
                const nameMap = { openai: 'OpenAI', grok: 'xAI', gemini: 'Google' };
                msg = (nameMap[provider] || provider) + ' API 金鑰尚未設定：請在伺服器上的 config.php 填入對應的 api_key。';
            }
        } else if (els.apiMode.checked) {
            if (!STATUS.apiKey) msg = 'API 模式尚未設定金鑰：請在伺服器上的 config.php 填入 api_key，或取消勾選「API 模式」。';
        } else if (!STATUS.cliBin) {
            msg = '訂閱模式尚未就緒：網頁服務無法執行 server 上的 claude（請確認 config.php 的 claude_bin）。';
        } else if (!STATUS.cliToken) {
            msg = '訂閱模式尚未登入：請在 server 執行 claude setup-token，把產生的 token 填入 config.php 的 claude_oauth_token。';
        }
        els.banner.textContent = msg;
        els.banner.hidden = !msg;
    }
    function setBusy(busy) {
        els.send.textContent = busy ? '停止' : '送出';
        els.send.classList.toggle('primary', !busy);
        els.newChat.disabled = busy;
    }
    function autoGrow() {
        els.input.style.height = 'auto';
        els.input.style.height = els.input.scrollHeight + 2 + 'px';
    }

    // ---- 送出 ----
    async function send() {
        const text = els.input.value.trim();
        if (!text || controller) return;

        if (!activeId) await newConversation();

        if (!messages.length) els.messages.innerHTML = '';
        messages.push({ role: 'user', content: text });
        const userDiv = addUser(text, messages.length - 1);
        els.input.value = '';
        autoGrow();

        const { div, body } = addAssistant('');
        body.classList.add('cursor');
        scrollDown();

        let answer = '', thinking = '', thinkingEl = null, pending = false;
        let done = null, failure = null;
        const paint = () => {
            if (pending) return;
            pending = true;
            requestAnimationFrame(() => {
                pending = false;
                const stick = nearBottom();
                if (thinkingEl) thinkingEl.textContent = thinking;
                if (answer) renderMarkdown(body, answer);
                if (stick) scrollDown();
            });
        };
        const handle = ev => {
            if (ev.type === 'text') {
                answer += ev.text;
            } else if (ev.type === 'thinking') {
                if (!thinkingEl) {
                    const det = document.createElement('details');
                    det.className = 'thinking';
                    det.innerHTML = '<summary>思考過程</summary><div></div>';
                    div.insertBefore(det, body);
                    thinkingEl = det.lastChild;
                }
                thinking += ev.text;
            } else if (ev.type === 'done') {
                done = ev;
            } else if (ev.type === 'error') {
                failure = ev.message;
            }
            paint();
        };

        controller = new AbortController();
        setBusy(true);
        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                body: JSON.stringify({
                    mode: (getProvider(els.model.value) !== 'claude' || els.apiMode.checked) ? 'api' : 'cli',
                    model: els.model.value, effort: els.effort.value,
                    system: els.system.value, messages,
                    admin: els.adminMode.checked,
                }),
                signal: controller.signal,
            });
            if (!res.ok) {
                const j = await res.json().catch(() => null);
                throw new Error((j && j.error) || '伺服器錯誤（HTTP ' + res.status + '）');
            }
            const reader = res.body.getReader();
            const decoder = new TextDecoder();
            let buf = '';
            for (;;) {
                const { done: eof, value } = await reader.read();
                if (eof) break;
                buf += decoder.decode(value, { stream: true });
                let i;
                while ((i = buf.indexOf('\n')) >= 0) {
                    const line = buf.slice(0, i).trim();
                    buf = buf.slice(i + 1);
                    if (line) handle(JSON.parse(line));
                }
            }
            if (!done && !failure) failure = '連線中斷，回覆可能不完整。';
        } catch (e) {
            failure = e.name === 'AbortError' ? '已停止。' : (e.message || '發生錯誤。');
        }
        controller = null;
        setBusy(false);
        body.classList.remove('cursor');

        const refused = done && done.stop_reason === 'refusal';
        if (refused) answer = '';
        if (answer) {
            renderMarkdown(body, answer);
            messages.push({ role: 'assistant', content: answer });
            div.dataset.idx = messages.length - 1;
            try {
                const r1 = await convApi('append', { id: activeId, role: 'user', content: text, provider: getProvider(els.model.value) });
                const r2 = await convApi('append', { id: activeId, role: 'assistant', content: answer });
                const updated = r2.error ? null : r2;
                if (updated) {
                    const conv = convList.find(c => c.id === activeId);
                    if (conv) {
                        conv.title = updated.title;
                        conv.updated = updated.updated;
                        if (updated.provider) conv.provider = updated.provider;
                        const idx = convList.indexOf(conv);
                        if (idx > 0) { convList.splice(idx, 1); convList.unshift(conv); }
                    }
                }
            } catch (e) { console.error('Save failed:', e); }
            renderSidebar();
        } else {
            messages.pop();
            delete userDiv.dataset.idx;
            body.remove();
            userDiv.style.opacity = '.55';
            if (!els.input.value) { els.input.value = text; autoGrow(); }
        }
        if (failure) addLine(div, 'notice', failure);
        if (done) {
            if (STOP_NOTICES[done.stop_reason]) addLine(div, 'notice', STOP_NOTICES[done.stop_reason]);
            const u = done.usage || {};
            let meta = (done.mode === 'api' ? 'API' : '訂閱') + ' · ' + done.model
                + ' · 輸入 ' + (u.input_tokens || 0) + ' / 輸出 ' + (u.output_tokens || 0) + ' tokens';
            if (typeof done.plan_usage === 'number') meta += ' · 5 小時用量 ' + Math.round(done.plan_usage * 100) + '%';
            addLine(div, 'meta', meta);
        }
        saveSettings();
        scrollDown();
        els.input.focus();
    }

    // ---- 事件 ----
    els.send.addEventListener('click', () => controller ? controller.abort() : send());
    els.input.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing && e.keyCode !== 229) {
            e.preventDefault();
            send();
        }
    });
    els.input.addEventListener('input', autoGrow);
    els.provider.addEventListener('change', () => {
        populateModels(els.provider.value);
        syncEffort(); syncMode(); syncAdmin(); applyTheme(els.model.value); saveSettings();
    });
    els.model.addEventListener('change', () => { syncEffort(); syncMode(); syncAdmin(); applyTheme(els.model.value); saveSettings(); });
    els.effort.addEventListener('change', saveSettings);
    els.apiMode.addEventListener('change', () => { syncMode(); syncAdmin(); saveSettings(); });
    els.adminMode.addEventListener('change', () => { syncAdmin(); saveSettings(); });
    els.system.addEventListener('input', saveSettings);
    els.systemToggle.addEventListener('click', () => {
        els.systemWrap.hidden = !els.systemWrap.hidden;
        if (!els.systemWrap.hidden) els.system.focus();
    });
    els.newChat.addEventListener('click', () => newConversation());
    els.newChatSidebar.addEventListener('click', () => newConversation());
    els.menuToggle.addEventListener('click', toggleSidebar);
    els.overlay.addEventListener('click', closeSidebar);

    // ---- 長按選單 ----
    const ctxMenu = document.createElement('div');
    ctxMenu.className = 'ctx-menu';
    ctxMenu.hidden = true;
    document.body.appendChild(ctxMenu);

    function getMsgEl(el) { return el.closest('.msg.user, .msg.assistant'); }

    function showCtxMenu(msgEl, x, y) {
        if (controller) return;
        const isUser = msgEl.classList.contains('user');
        const idx = parseInt(msgEl.dataset.idx, 10);
        if (isNaN(idx)) return;

        ctxMenu.innerHTML = '';
        const mkBtn = (label, fn) => {
            const b = document.createElement('button');
            b.textContent = label;
            b.addEventListener('click', () => { hideCtxMenu(); fn(); });
            ctxMenu.appendChild(b);
        };
        mkBtn('複製', () => {
            const content = messages[idx]?.content || msgEl.textContent;
            navigator.clipboard.writeText(content).catch(() => {});
        });
        mkBtn('編輯', () => editMessage(idx, isUser));
        if (isUser) {
            mkBtn('重新送出', () => resendMessage(idx));
        } else {
            mkBtn('重新產生', () => regenerateMessage(idx));
        }
        mkBtn('刪除', () => deleteFromMessage(idx, isUser));

        ctxMenu.hidden = false;
        const rect = ctxMenu.getBoundingClientRect();
        ctxMenu.style.left = Math.max(8, Math.min(x, window.innerWidth - rect.width - 8)) + 'px';
        ctxMenu.style.top = Math.max(8, Math.min(y, window.innerHeight - rect.height - 8)) + 'px';
    }

    function hideCtxMenu() { ctxMenu.hidden = true; }

    async function resendMessage(idx) {
        if (controller) return;
        const content = messages[idx].content;
        messages = messages.slice(0, idx);
        if (activeId) await convApi('truncate', { id: activeId, keep: idx });
        renderAll();
        els.input.value = content;
        autoGrow();
        send();
    }

    async function regenerateMessage(idx) {
        if (controller) return;
        const userIdx = idx - 1;
        if (userIdx < 0 || messages[userIdx]?.role !== 'user') return;
        const content = messages[userIdx].content;
        messages = messages.slice(0, userIdx);
        if (activeId) await convApi('truncate', { id: activeId, keep: userIdx });
        renderAll();
        els.input.value = content;
        autoGrow();
        send();
    }

    async function deleteFromMessage(idx, isUser) {
        if (controller) return;
        const keep = isUser ? idx : idx - 1 >= 0 && messages[idx - 1]?.role === 'user' ? idx - 1 : idx;
        const count = messages.length - keep;
        if (!confirm(`確定要刪除${count > 1 ? '這 ' + count + ' 則訊息' : '這則訊息'}嗎？`)) return;
        messages = messages.slice(0, keep);
        if (activeId) {
            await convApi('truncate', { id: activeId, keep });
            if (!messages.length) {
                const conv = convList.find(c => c.id === activeId);
                if (conv) conv.title = '新對話';
                renderSidebar();
            }
        }
        renderAll();
        saveSettings();
    }

    async function editMessage(idx, isUser) {
        if (controller) return;
        const content = messages[idx].content;
        const msgEl = document.querySelector(`.msg[data-idx="${idx}"]`);
        if (!msgEl) return;

        const originalHTML = msgEl.innerHTML;
        const originalClass = msgEl.className;
        msgEl.className = (isUser ? 'msg user' : 'msg assistant') + ' editing';
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
            msgEl.className = originalClass;
            msgEl.innerHTML = originalHTML;
        });
        const confirmBtn = document.createElement('button');
        confirmBtn.className = 'primary';
        confirmBtn.textContent = isUser ? '送出' : '儲存';
        confirmBtn.addEventListener('click', async () => {
            const val = textarea.value.trim();
            if (!val) return;
            if (isUser) {
                messages = messages.slice(0, idx);
                if (activeId) await convApi('truncate', { id: activeId, keep: idx });
                renderAll();
                els.input.value = val;
                autoGrow();
                send();
            } else {
                messages[idx].content = val;
                messages = messages.slice(0, idx + 1);
                if (activeId) {
                    await convApi('truncate', { id: activeId, keep: idx });
                    await convApi('append', { id: activeId, role: 'assistant', content: val });
                }
                renderAll();
                saveSettings();
            }
        });
        actions.appendChild(cancelBtn);
        actions.appendChild(confirmBtn);
        msgEl.appendChild(actions);

        textarea.focus();
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 2 + 'px';
    }

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

    els.messages.addEventListener('touchstart', e => { startPress(e, e.touches[0].clientX, e.touches[0].clientY); }, { passive: true });
    els.messages.addEventListener('touchmove', e => { movePress(e.touches[0].clientX, e.touches[0].clientY); }, { passive: true });
    els.messages.addEventListener('touchend', endPress);
    els.messages.addEventListener('touchcancel', endPress);
    els.messages.addEventListener('mousedown', e => { if (e.button === 0) startPress(e, e.clientX, e.clientY); });
    document.addEventListener('mousemove', e => movePress(e.clientX, e.clientY));
    document.addEventListener('mouseup', endPress);

    els.messages.addEventListener('contextmenu', e => {
        const msgEl = getMsgEl(e.target);
        if (!msgEl || msgEl.dataset.idx === undefined) return;
        e.preventDefault();
        endPress();
        showCtxMenu(msgEl, e.clientX, e.clientY);
    });

    document.addEventListener('click', e => { if (!ctxMenu.contains(e.target)) hideCtxMenu(); });
    document.addEventListener('touchstart', e => { if (!ctxMenu.hidden && !ctxMenu.contains(e.target)) hideCtxMenu(); }, { passive: true });

    // ---- Init ----
    (async () => {
        populateModels(els.provider.value, DEFAULT_MODEL_ID);
        loadSettings();

        // Migrate old localStorage data to DB
        try {
            const v2 = JSON.parse(localStorage.getItem('claude_chat_v2') || 'null');
            if (v2 && Array.isArray(v2.conversations) && v2.conversations.length) {
                await convApi('migrate', { conversations: v2.conversations });
                localStorage.removeItem('claude_chat_v2');
            }
            const v1 = JSON.parse(localStorage.getItem('claude_chat_v1') || 'null');
            if (v1 && Array.isArray(v1.history) && v1.history.length) {
                await convApi('migrate', { conversations: [{ id: 'v1_migrated', created: Date.now(), updated: Date.now(), messages: v1.history }] });
                localStorage.removeItem('claude_chat_v1');
            }
        } catch (e) { console.warn('Migration:', e); }

        convList = await convApi('list');

        if (!convList.length) {
            await newConversation();
        } else {
            if (!activeId || !convList.find(c => c.id === activeId)) {
                activeId = convList[0].id;
            }
            const data = await convApi('get', { id: activeId });
            messages = data.messages || [];
        }

        syncEffort();
        syncMode();
        syncAdmin();
        applyTheme(els.model.value);
        renderSidebar();
        renderAll();
        saveSettings();
        if (!isMobile()) els.sidebar.classList.remove('collapsed');
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => applyTheme(els.model.value));
    })();
})();
</script>
<?php endif; ?>
</body>
</html>
