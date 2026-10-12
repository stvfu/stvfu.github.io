<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/claude_cli.php';

if (!is_logged_in()) {
    header('Location: index.php');
    exit;
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Multi-Agent 對話</title>
<style>
:root {
    --bg: #f5f4ef; --panel: #ffffff; --border: #dedcd3; --text: #1f1e1c; --muted: #77746b;
    --accent: #6366f1; --accent-text: #ffffff; --user: #e9e6dc; --code: #f0eee6; --error: #b3261e;
    --color-a: #c96442; --color-b: #10a37f;
}
@media (prefers-color-scheme: dark) {
    :root {
        --bg: #1f1e1c; --panel: #2a2926; --border: #3d3b36; --text: #ecebe6; --muted: #9c998f;
        --accent: #818cf8; --accent-text: #1f1e1c; --user: #3a3833; --code: #191816; --error: #f2998f;
        --color-a: #d97757; --color-b: #1bc494;
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
button:disabled { opacity: .5; cursor: default; }
button.primary { background: var(--accent); color: var(--accent-text); border-color: var(--accent); }
select, textarea, input[type=text] {
    background: var(--panel); border: 1px solid var(--border); border-radius: 8px; padding: 8px 10px;
}
select:focus, textarea:focus, input:focus, button:focus-visible { outline: 2px solid var(--accent); outline-offset: 1px; }

.app { height: 100%; display: flex; flex-direction: column; }

header {
    display: flex; align-items: center; gap: 8px; padding: 10px 16px;
    border-bottom: 1px solid var(--border); background: var(--panel); flex-shrink: 0;
}
header h1 { font-size: 17px; margin: 0; }
header .spacer { flex: 1; }
#setupToggle { background: none; border: none; font-size: 20px; padding: 4px 8px; line-height: 1; }

.setup {
    border-bottom: 1px solid var(--border); background: var(--panel); overflow: hidden;
    max-height: 800px; transition: max-height .35s ease, border-color .35s;
}
.setup.collapsed { max-height: 0; border-bottom-color: transparent; }
.setup-inner { padding: 12px 16px 16px; max-width: 840px; margin: 0 auto; }
.agents-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
.agent-card {
    border: 1px solid var(--border); border-radius: 12px; padding: 10px 14px; margin: 0;
    display: flex; flex-direction: column; gap: 8px;
}
.agent-card legend { font-weight: 700; font-size: 15px; padding: 0 6px; }
.card-a { border-color: color-mix(in srgb, var(--color-a) 40%, var(--border)); }
.card-b { border-color: color-mix(in srgb, var(--color-b) 40%, var(--border)); }
.card-a legend { color: var(--color-a); }
.card-b legend { color: var(--color-b); }
.agent-card label { font-size: 13px; color: var(--muted); display: flex; align-items: center; gap: 6px; }
.agent-card select { flex: 1; padding: 5px 8px; font-size: 14px; }
.agent-card textarea { width: 100%; font-size: 14px; resize: vertical; min-height: 48px; }
#topic { width: 100%; font-size: 14px; resize: vertical; min-height: 56px; margin-bottom: 12px; }
.options-row {
    display: flex; flex-wrap: wrap; gap: 10px; align-items: center;
}
.options-row label { font-size: 13px; color: var(--muted); display: flex; align-items: center; gap: 4px; white-space: nowrap; }
.options-row select { padding: 4px 8px; font-size: 14px; }
.options-row .spacer { flex: 1; }
.start-btn { padding: 8px 20px; font-size: 15px; white-space: nowrap; }

#chat { flex: 1; overflow-y: auto; padding: 20px 16px; }
.chat-inner { max-width: 800px; margin: 0 auto; display: flex; flex-direction: column; gap: 16px; }
.empty { color: var(--muted); text-align: center; margin-top: 16vh; line-height: 2; }

.msg {
    max-width: 82%; padding: 10px 14px; border-radius: 12px;
    background: var(--panel); border: 1px solid var(--border);
    overflow-wrap: anywhere; min-width: 0;
}
.msg.topic {
    align-self: center; max-width: 90%; background: var(--user);
    text-align: center; font-size: 15px; white-space: pre-wrap;
    border-radius: 20px; padding: 12px 20px;
}
.msg.topic .badge { font-weight: 700; color: var(--muted); margin-bottom: 4px; font-size: 12px; }
.msg.ai-a { align-self: flex-start; border-left: 4px solid var(--color-a); }
.msg.ai-b { align-self: flex-end; border-right: 4px solid var(--color-b); }
.msg.user-intervention {
    align-self: center; max-width: 80%; background: var(--user);
    border-radius: 14px; padding: 10px 14px; white-space: pre-wrap;
    border: 1px dashed var(--accent);
}
.msg.user-intervention .badge { font-weight: 700; color: var(--accent); margin-bottom: 4px; font-size: 12px; }
.msg.system-msg {
    align-self: center; background: none; border: none; color: var(--muted);
    font-size: 13px; text-align: center; padding: 8px 0;
}
.msg .badge { font-size: 12px; font-weight: 600; margin-bottom: 6px; }
.msg.ai-a .badge { color: var(--color-a); }
.msg.ai-b .badge { color: var(--color-b); }
.msg .body > :first-child { margin-top: 0; }
.msg .body > :last-child { margin-bottom: 0; }
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
.cursor::after { content: "\25CD"; color: var(--accent); animation: blink 1s steps(2) infinite; }
@keyframes blink { 50% { opacity: 0; } }

footer { border-top: 1px solid var(--border); background: var(--panel); flex-shrink: 0; }
.footer-status { padding: 6px 16px; font-size: 13px; color: var(--muted); }
.footer-controls {
    max-width: 800px; margin: 0 auto; padding: 0 16px 10px;
    display: flex; gap: 8px; align-items: center;
}
.footer-controls input[type=text] { flex: 1; min-width: 0; height: 38px; }
.footer-controls button { height: 38px; white-space: nowrap; }
.stop-btn:not(:disabled) { background: var(--error); color: #fff; border-color: var(--error); }

@media (max-width: 600px) {
    header { padding: 8px 12px; gap: 6px; }
    header h1 { font-size: 15px; }

    .setup { max-height: 70vh; }
    .setup:not(.collapsed) { overflow-y: auto; -webkit-overflow-scrolling: touch; }
    .setup.collapsed { overflow: hidden; }
    .setup-inner { padding: 10px 12px 14px; }
    .agents-row { grid-template-columns: 1fr; gap: 10px; }
    .agent-card { padding: 8px 12px; gap: 6px; }
    .agent-card select { padding: 8px; font-size: 16px; }
    .agent-card textarea { font-size: 16px; min-height: 40px; }
    #topic { font-size: 16px; min-height: 48px; margin-bottom: 10px; }

    .options-row {
        display: grid; grid-template-columns: 1fr 1fr; gap: 8px;
    }
    .options-row label { font-size: 14px; }
    .options-row select { padding: 8px; font-size: 16px; }
    .options-row .spacer { display: none; }
    .start-btn {
        grid-column: 1 / -1; width: 100%;
        padding: 14px; font-size: 17px; font-weight: 600;
    }

    #chat { padding: 14px 12px; }
    .chat-inner { gap: 12px; }
    .msg { max-width: 90%; padding: 10px 12px; }
    .msg.topic { padding: 10px 16px; font-size: 14px; }

    .footer-status { padding: 6px 12px; font-size: 12px; }
    .footer-controls {
        padding: 0 12px 12px;
        flex-wrap: wrap; gap: 6px;
    }
    .footer-controls input[type=text] {
        flex-basis: 100%; height: 44px; font-size: 16px;
    }
    .footer-controls button {
        height: 44px; font-size: 15px; flex: 1; min-width: 0;
    }
    .stop-btn { flex: 1.6 !important; font-weight: 700; font-size: 16px !important; }

    /* hide intervention controls when not running */
    #intervention, #intervenBtn { display: none; }
    body.is-running #intervention { display: block; flex-basis: 100%; }
    body.is-running #intervenBtn { display: block; }
}
</style>
</head>
<body>
<div class="app">
    <header>
        <button type="button" id="setupToggle" title="展開/收合設定">⚙</button>
        <h1>Multi-Agent 對話</h1>
        <span class="spacer"></span>
        <button type="button" onclick="location.href='index.php'">← AI 對話</button>
    </header>

    <div class="setup" id="setup">
        <div class="setup-inner">
            <div class="agents-row">
                <fieldset class="agent-card card-a">
                    <legend>AI A</legend>
                    <label>提供者
                        <select id="providerA">
                            <?php foreach (PROVIDERS as $pid => $pname): ?>
                            <option value="<?= h($pid) ?>"><?= h($pname) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>模型 <select id="modelA"></select></label>
                    <textarea id="systemA" rows="2" placeholder="系統提示（選填）：留空將使用預設提示"></textarea>
                </fieldset>
                <fieldset class="agent-card card-b">
                    <legend>AI B</legend>
                    <label>提供者
                        <select id="providerB">
                            <?php foreach (PROVIDERS as $pid => $pname): ?>
                            <option value="<?= h($pid) ?>"<?= $pid === 'openai' ? ' selected' : '' ?>><?= h($pname) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>模型 <select id="modelB"></select></label>
                    <textarea id="systemB" rows="2" placeholder="系統提示（選填）：留空將使用預設提示"></textarea>
                </fieldset>
            </div>
            <textarea id="topic" rows="2" placeholder="輸入對話主題或起始訊息…"></textarea>
            <div class="options-row">
                <label title="兩個 AI 總共回覆次數">回合
                    <select id="maxRounds">
                        <option value="6">6</option>
                        <option value="10" selected>10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                    </select>
                </label>
                <label title="每次回覆之間的等待時間">間隔
                    <select id="delay">
                        <option value="1000">1 秒</option>
                        <option value="2000" selected>2 秒</option>
                        <option value="3000">3 秒</option>
                        <option value="5000">5 秒</option>
                    </select>
                </label>
                <label>思考強度
                    <select id="effort">
                        <?php foreach (EFFORTS as $id => $label): ?>
                        <option value="<?= h($id) ?>"<?= $id === DEFAULT_EFFORT ? ' selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label title="使用 API 金鑰（另外計費）">
                    <input type="checkbox" id="apiMode"> API
                </label>
                <span class="spacer"></span>
                <button type="button" id="startBtn" class="primary start-btn">▶ 開始對話</button>
            </div>
        </div>
    </div>

    <main id="chat">
        <div class="chat-inner" id="messages">
            <div class="empty">設定兩個 AI 並輸入主題<br>按「▶ 開始對話」讓它們開始討論</div>
        </div>
    </main>

    <footer>
        <div class="footer-status"><span id="statusText">就緒</span></div>
        <div class="footer-controls">
            <input type="text" id="intervention" placeholder="輸入介入訊息… Enter 送出" disabled>
            <button type="button" id="intervenBtn" disabled>介入</button>
            <button type="button" id="stopBtn" class="stop-btn" disabled>⏹ 停止</button>
            <button type="button" id="clearBtn">清除</button>
        </div>
    </footer>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/marked/12.0.2/marked.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.1.6/purify.min.js"></script>
<script>
(() => {
    const CSRF = <?= json_encode($_SESSION['csrf']) ?>;
    const ALL_MODELS = <?= json_encode(array_map(fn($m) => [
        'label' => $m['label'], 'version' => $m['version'],
        'adaptive' => $m['adaptive'], 'provider' => $m['provider'],
    ], MODELS), JSON_UNESCAPED_UNICODE) ?>;
    const PROVIDER_THEMES = {
        claude:  { name: 'Claude',  light: '#c96442', dark: '#d97757' },
        openai:  { name: 'ChatGPT', light: '#10a37f', dark: '#1bc494' },
        grok:    { name: 'Grok',    light: '#1d9bf0', dark: '#4ab3f4' },
        gemini:  { name: 'Gemini',  light: '#4285f4', dark: '#669df6' },
        default: { name: 'AI',      light: '#6366f1', dark: '#818cf8' },
    };
    const SETTINGS_KEY = 'multi_agent_settings';

    const $ = id => document.getElementById(id);
    const els = {
        setup: $('setup'), setupToggle: $('setupToggle'),
        providerA: $('providerA'), modelA: $('modelA'), systemA: $('systemA'),
        providerB: $('providerB'), modelB: $('modelB'), systemB: $('systemB'),
        topic: $('topic'), maxRounds: $('maxRounds'), delay: $('delay'),
        effort: $('effort'), apiMode: $('apiMode'),
        startBtn: $('startBtn'), stopBtn: $('stopBtn'), clearBtn: $('clearBtn'),
        intervention: $('intervention'), intervenBtn: $('intervenBtn'),
        chat: $('chat'), messages: $('messages'), statusText: $('statusText'),
    };

    let running = false;
    let abortCtrl = null;
    let turn = 0;
    let historyA = [];
    let historyB = [];
    let interventionMsg = null;
    let wakeupResolve = null;

    function getProvider(modelId) {
        if (modelId.startsWith('claude-')) return 'claude';
        if (modelId.startsWith('gpt-') || modelId.startsWith('o1') || modelId.startsWith('o3') || modelId.startsWith('o4')) return 'openai';
        if (modelId.startsWith('grok-')) return 'grok';
        if (modelId.startsWith('gemini-')) return 'gemini';
        return 'default';
    }
    function themeColor(provider) {
        const t = PROVIDER_THEMES[provider] || PROVIDER_THEMES.default;
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? t.dark : t.light;
    }
    function providerName(provider) {
        return (PROVIDER_THEMES[provider] || PROVIDER_THEMES.default).name;
    }
    function defaultSystem(otherLabel) {
        return `你正在與 ${otherLabel} 進行一場深度對話。請認真回應對方的觀點，表達你的獨立見解，並推動討論持續深入。回覆請簡潔有力，控制在合理長度。`;
    }

    function populateModels(providerEl, modelEl, selectId) {
        const pv = providerEl.value;
        modelEl.innerHTML = '';
        let first = null;
        for (const [id, m] of Object.entries(ALL_MODELS)) {
            if (m.provider !== pv) continue;
            if (!first) first = id;
            const opt = document.createElement('option');
            opt.value = id;
            opt.textContent = m.version;
            modelEl.appendChild(opt);
        }
        if (selectId && [...modelEl.options].some(o => o.value === selectId)) {
            modelEl.value = selectId;
        } else if (first) {
            modelEl.value = first;
        }
    }
    function updateColors() {
        const ca = themeColor(getProvider(els.modelA.value));
        const cb = themeColor(getProvider(els.modelB.value));
        document.documentElement.style.setProperty('--color-a', ca);
        document.documentElement.style.setProperty('--color-b', cb);
    }
    function syncEffort() {
        const aa = ALL_MODELS[els.modelA.value]?.adaptive || false;
        const ab = ALL_MODELS[els.modelB.value]?.adaptive || false;
        els.effort.disabled = !aa && !ab;
    }

    function renderMarkdown(el, text) {
        if (window.marked && window.DOMPurify) {
            el.innerHTML = DOMPurify.sanitize(marked.parse(text, { breaks: true }));
            el.querySelectorAll('a').forEach(a => { a.target = '_blank'; a.rel = 'noopener noreferrer'; });
        } else {
            el.textContent = text;
        }
    }
    function nearBottom() {
        return els.chat.scrollHeight - els.chat.scrollTop - els.chat.clientHeight < 100;
    }
    function scrollDown() { els.chat.scrollTop = els.chat.scrollHeight; }

    function addTopicMsg(text) {
        const div = document.createElement('div');
        div.className = 'msg topic';
        const badge = document.createElement('div');
        badge.className = 'badge';
        badge.textContent = '對話主題';
        div.appendChild(badge);
        div.appendChild(document.createTextNode(text));
        els.messages.appendChild(div);
        scrollDown();
    }
    function addAIMsg(side, modelId) {
        const m = ALL_MODELS[modelId];
        const div = document.createElement('div');
        div.className = 'msg ai-' + side.toLowerCase();
        const badge = document.createElement('div');
        badge.className = 'badge';
        badge.textContent = (m?.label || modelId);
        div.appendChild(badge);
        const body = document.createElement('div');
        body.className = 'body';
        div.appendChild(body);
        els.messages.appendChild(div);
        scrollDown();
        return { msgEl: div, bodyEl: body };
    }
    function addUserInterventionMsg(text) {
        const div = document.createElement('div');
        div.className = 'msg user-intervention';
        const badge = document.createElement('div');
        badge.className = 'badge';
        badge.textContent = '👤 介入';
        div.appendChild(badge);
        div.appendChild(document.createTextNode(text));
        els.messages.appendChild(div);
        scrollDown();
    }
    function addSystemMsg(text) {
        const div = document.createElement('div');
        div.className = 'msg system-msg';
        div.textContent = text;
        els.messages.appendChild(div);
        scrollDown();
    }
    function addMeta(msgEl, text) {
        const d = document.createElement('div');
        d.className = 'meta';
        d.textContent = text;
        msgEl.appendChild(d);
    }
    function addNotice(msgEl, text) {
        const d = document.createElement('div');
        d.className = 'notice';
        d.textContent = text;
        msgEl.appendChild(d);
    }

    function setStatus(text) { els.statusText.textContent = text; }
    function setRunning(active) {
        running = active;
        document.body.classList.toggle('is-running', active);
        els.startBtn.disabled = active;
        els.stopBtn.disabled = !active;
        els.clearBtn.disabled = active;
        els.intervention.disabled = !active;
        els.intervenBtn.disabled = !active;
        if (active) {
            els.setup.classList.add('collapsed');
        }
        const inputs = els.setup.querySelectorAll('select, textarea, input');
        inputs.forEach(el => { if (el !== els.setupToggle) el.disabled = active; });
    }
    function clearChat() {
        els.messages.innerHTML = '<div class="empty">設定兩個 AI 並輸入主題<br>按「▶ 開始對話」讓它們開始討論</div>';
        turn = 0;
        historyA = [];
        historyB = [];
        interventionMsg = null;
        setStatus('就緒');
        document.title = 'Multi-Agent 對話';
    }

    function saveSettings() {
        try {
            localStorage.setItem(SETTINGS_KEY, JSON.stringify({
                providerA: els.providerA.value, modelA: els.modelA.value,
                providerB: els.providerB.value, modelB: els.modelB.value,
                systemA: els.systemA.value, systemB: els.systemB.value,
                effort: els.effort.value, apiMode: els.apiMode.checked,
                maxRounds: els.maxRounds.value, delay: els.delay.value,
            }));
        } catch (e) {}
    }
    function loadSettings() {
        try {
            const s = JSON.parse(localStorage.getItem(SETTINGS_KEY) || 'null');
            if (!s) return;
            if (s.providerA && [...els.providerA.options].some(o => o.value === s.providerA)) {
                els.providerA.value = s.providerA;
            }
            populateModels(els.providerA, els.modelA, s.modelA);
            if (s.providerB && [...els.providerB.options].some(o => o.value === s.providerB)) {
                els.providerB.value = s.providerB;
            }
            populateModels(els.providerB, els.modelB, s.modelB);
            if (s.systemA) els.systemA.value = s.systemA;
            if (s.systemB) els.systemB.value = s.systemB;
            if (s.effort && [...els.effort.options].some(o => o.value === s.effort)) els.effort.value = s.effort;
            els.apiMode.checked = !!s.apiMode;
            if (s.maxRounds && [...els.maxRounds.options].some(o => o.value === s.maxRounds)) els.maxRounds.value = s.maxRounds;
            if (s.delay && [...els.delay.options].some(o => o.value === s.delay)) els.delay.value = s.delay;
        } catch (e) {}
    }

    function interruptibleSleep(ms) {
        return new Promise(resolve => {
            const timer = setTimeout(() => { wakeupResolve = null; resolve('timeout'); }, ms);
            wakeupResolve = (reason) => { clearTimeout(timer); wakeupResolve = null; resolve(reason); };
        });
    }

    async function streamAI(model, system, messages, bodyEl, msgEl) {
        const provider = getProvider(model);
        const mode = (provider !== 'claude' || els.apiMode.checked) ? 'api' : 'cli';

        abortCtrl = new AbortController();

        const res = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({
                mode, model,
                effort: els.effort.value,
                system, messages,
                admin: false,
            }),
            signal: abortCtrl.signal,
        });

        if (!res.ok) {
            const j = await res.json().catch(() => null);
            throw new Error((j && j.error) || '伺服器錯誤（HTTP ' + res.status + '）');
        }

        const reader = res.body.getReader();
        const decoder = new TextDecoder();
        let buf = '', fullText = '', thinking = '', thinkingEl = null;
        let doneEv = null;
        let pending = false;

        const paint = () => {
            if (pending) return;
            pending = true;
            requestAnimationFrame(() => {
                pending = false;
                const stick = nearBottom();
                if (thinkingEl) thinkingEl.textContent = thinking;
                if (fullText) renderMarkdown(bodyEl, fullText);
                if (stick) scrollDown();
            });
        };

        for (;;) {
            const { done: eof, value } = await reader.read();
            if (eof) break;
            buf += decoder.decode(value, { stream: true });
            let i;
            while ((i = buf.indexOf('\n')) >= 0) {
                const line = buf.slice(0, i).trim();
                buf = buf.slice(i + 1);
                if (!line) continue;
                let ev;
                try { ev = JSON.parse(line); } catch { continue; }

                if (ev.type === 'text') {
                    fullText += ev.text;
                    paint();
                } else if (ev.type === 'thinking') {
                    if (!thinkingEl) {
                        const det = document.createElement('details');
                        det.className = 'thinking';
                        det.innerHTML = '<summary>思考過程</summary><div></div>';
                        msgEl.insertBefore(det, bodyEl);
                        thinkingEl = det.lastChild;
                    }
                    thinking += ev.text;
                    paint();
                } else if (ev.type === 'done') {
                    doneEv = ev;
                } else if (ev.type === 'error') {
                    throw new Error(ev.message);
                }
            }
        }

        abortCtrl = null;
        if (!fullText && !doneEv) throw new Error('AI 沒有回應。');
        return { text: fullText, done: doneEv };
    }

    async function startConversation() {
        if (running) return;
        const topic = els.topic.value.trim();
        if (!topic) { els.topic.focus(); return; }

        const modelA = els.modelA.value;
        const modelB = els.modelB.value;
        const labelA = ALL_MODELS[modelA]?.label || modelA;
        const labelB = ALL_MODELS[modelB]?.label || modelB;
        const systemA = els.systemA.value.trim() || defaultSystem(labelB);
        const systemB = els.systemB.value.trim() || defaultSystem(labelA);
        const maxR = parseInt(els.maxRounds.value);
        const delayMs = parseInt(els.delay.value);

        turn = 0;
        historyA = [];
        historyB = [];
        interventionMsg = null;
        setRunning(true);
        els.messages.innerHTML = '';
        addTopicMsg(topic);
        updateColors();
        saveSettings();

        let nextSide = 'A';
        let lastMsg = topic;

        try {
            while (running && turn < maxR) {
                if (turn > 0 && !interventionMsg) {
                    setStatus(`回合 ${turn}/${maxR} · 等待中…`);
                    await interruptibleSleep(delayMs);
                    if (!running) break;
                }

                if (interventionMsg) {
                    addUserInterventionMsg(interventionMsg);
                    lastMsg = interventionMsg;
                    interventionMsg = null;
                }

                const model = nextSide === 'A' ? modelA : modelB;
                const system = nextSide === 'A' ? systemA : systemB;
                const history = nextSide === 'A' ? historyA : historyB;
                const label = nextSide === 'A' ? labelA : labelB;

                setStatus(`回合 ${turn + 1}/${maxR} · ${label} 回覆中…`);
                document.title = `(${turn + 1}/${maxR}) Multi-Agent`;

                history.push({ role: 'user', content: lastMsg });
                const { msgEl, bodyEl } = addAIMsg(nextSide, model);
                bodyEl.classList.add('cursor');
                scrollDown();

                const result = await streamAI(model, system, history, bodyEl, msgEl);
                bodyEl.classList.remove('cursor');

                if (!result.text) {
                    if (result.done?.stop_reason === 'refusal') {
                        addNotice(msgEl, 'AI 拒絕了這個請求。');
                        break;
                    }
                }

                history.push({ role: 'assistant', content: result.text });

                if (result.done) {
                    const u = result.done.usage || {};
                    const modeName = result.done.mode === 'api' ? 'API' : '訂閱';
                    addMeta(msgEl, `${modeName} · ${result.done.model} · 輸入 ${u.input_tokens || 0} / 輸出 ${u.output_tokens || 0} tokens`);
                    if (result.done.stop_reason === 'max_tokens') {
                        addNotice(msgEl, '回覆已達長度上限而中斷。');
                    }
                }

                lastMsg = result.text;
                turn++;
                nextSide = nextSide === 'A' ? 'B' : 'A';
            }

            if (!running) {
                setStatus(`已停止 · 共 ${turn} 回合`);
                addSystemMsg(`對話已停止，共 ${turn} 回合。`);
            } else if (turn >= maxR) {
                setStatus(`對話完成 · 共 ${turn} 回合`);
                addSystemMsg(`對話已完成，共 ${turn} 回合。`);
            }
        } catch (e) {
            if (e.name === 'AbortError') {
                setStatus(`已停止 · 共 ${turn} 回合`);
                addSystemMsg(`對話已停止，共 ${turn} 回合。`);
            } else {
                setStatus('發生錯誤');
                addSystemMsg('錯誤：' + e.message);
            }
        }

        running = false;
        setRunning(false);
        document.title = 'Multi-Agent 對話';
    }

    function stopConversation() {
        running = false;
        if (abortCtrl) abortCtrl.abort();
        if (wakeupResolve) wakeupResolve('stop');
    }

    function handleIntervene() {
        const msg = els.intervention.value.trim();
        if (!msg || !running) return;
        els.intervention.value = '';
        interventionMsg = msg;
        if (wakeupResolve) wakeupResolve('intervene');
    }

    // ---- Events ----
    els.setupToggle.addEventListener('click', () => {
        els.setup.classList.toggle('collapsed');
    });
    els.providerA.addEventListener('change', () => {
        populateModels(els.providerA, els.modelA);
        updateColors(); syncEffort(); saveSettings();
    });
    els.modelA.addEventListener('change', () => { updateColors(); syncEffort(); saveSettings(); });
    els.providerB.addEventListener('change', () => {
        populateModels(els.providerB, els.modelB);
        updateColors(); syncEffort(); saveSettings();
    });
    els.modelB.addEventListener('change', () => { updateColors(); syncEffort(); saveSettings(); });
    els.effort.addEventListener('change', saveSettings);
    els.apiMode.addEventListener('change', saveSettings);
    els.maxRounds.addEventListener('change', saveSettings);
    els.delay.addEventListener('change', saveSettings);
    els.systemA.addEventListener('input', saveSettings);
    els.systemB.addEventListener('input', saveSettings);

    els.startBtn.addEventListener('click', startConversation);
    els.stopBtn.addEventListener('click', stopConversation);
    els.clearBtn.addEventListener('click', () => {
        clearChat();
        els.setup.classList.remove('collapsed');
    });
    els.intervenBtn.addEventListener('click', handleIntervene);
    els.intervention.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.isComposing && e.keyCode !== 229) {
            e.preventDefault();
            handleIntervene();
        }
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && running) stopConversation();
    });
    window.addEventListener('beforeunload', e => {
        if (running) { e.preventDefault(); e.returnValue = ''; }
    });
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', updateColors);

    // ---- Init ----
    populateModels(els.providerA, els.modelA);
    populateModels(els.providerB, els.modelB);
    loadSettings();
    updateColors();
    syncEffort();
})();
</script>
</body>
</html>
