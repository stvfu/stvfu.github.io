<?php
declare(strict_types=1);
require __DIR__ . '/common.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$cdn = 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Web Editor</title>
    <link rel="stylesheet" href="<?= $cdn ?>/codemirror.min.css">
    <link rel="stylesheet" href="<?= $cdn ?>/theme/monokai.min.css">
    <link rel="stylesheet" href="<?= $cdn ?>/addon/dialog/dialog.min.css">
    <script src="<?= $cdn ?>/codemirror.min.js"></script>
    <script src="<?= $cdn ?>/mode/meta.min.js"></script>
    <script src="<?= $cdn ?>/addon/mode/loadmode.min.js"></script>
    <script src="<?= $cdn ?>/addon/dialog/dialog.min.js"></script>
    <script src="<?= $cdn ?>/addon/search/searchcursor.min.js"></script>
    <script src="<?= $cdn ?>/addon/search/search.min.js"></script>
    <script src="<?= $cdn ?>/addon/edit/matchbrackets.min.js"></script>
    <script src="<?= $cdn ?>/addon/selection/active-line.min.js"></script>
    <style>
        /* ===== Modern (default) ===== */
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            background: #272822;
            color: #f8f8f2;
            font-family: sans-serif;
            font-size: 14px;
        }
        #control-panel {
            padding: 8px 10px;
            background: #333;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
        }
        #textbox_file {
            flex: 1;
            min-width: 180px;
        }
        #control-panel form {
            margin: 0;
        }
        #main-container {
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: row;
        }
        #editor-container {
            flex: 1;
            min-width: 0;
        }
        #file-browser {
            width: 30%;
            max-width: 360px;
            overflow-y: auto;
            border-left: 1px solid #555;
            transition: width 0.2s, max-width 0.2s;
        }
        #file-browser.collapsed {
            width: 0;
            max-width: 0;
            overflow: hidden;
            border-left: none;
        }
        #toggle-browser {
            background: #555;
            color: #f8f8f2;
            border: none;
            cursor: pointer;
            padding: 0 4px;
            font-size: 16px;
            width: 20px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #toggle-browser:hover {
            background: #666;
        }
        #browser-dir {
            padding: 6px 8px;
            background: #333;
            position: sticky;
            top: 0;
            word-break: break-all;
        }
        .CodeMirror {
            height: 100%;
            width: 100%;
        }
        .file-entry {
            cursor: pointer;
            padding: 4px 8px;
            word-break: break-all;
        }
        .file-entry.dir {
            color: #66d9ef;
        }
        .file-entry.active {
            background-color: #49483e;
        }
        .file-entry:hover {
            background-color: #555;
        }
        #status-bar {
            padding: 4px 10px;
            background: #333;
            display: flex;
            justify-content: space-between;
            gap: 10px;
            font-size: 12px;
        }
        #status-bar.error #status-msg {
            color: #f92672;
        }

        /* ===== Classic (web_editer2 nostalgia) ===== */
        body.classic {
            background: #fff;
            color: #000;
            font-family: initial;
            font-size: initial;
        }
        body.classic #control-panel {
            height: 40px;
            padding: 10px;
            background: #333;
            color: #fff;
            flex-wrap: nowrap;
            gap: 0;
        }
        body.classic #main-container {
            flex: none;
            height: calc(100vh - 100px);
        }
        body.classic #editor-container {
            width: 70%;
            flex: none;
            padding: 10px;
            overflow-y: auto;
        }
        body.classic #file-browser {
            width: 30%;
            max-width: none;
            padding: 10px;
            border-left: 1px solid #ccc;
            transition: none;
        }
        body.classic #file-browser.collapsed {
            width: 30%;
            max-width: none;
            overflow-y: auto;
            border-left: 1px solid #ccc;
        }
        body.classic #toggle-browser {
            display: none;
        }
        body.classic #browser-dir {
            display: none;
        }
        body.classic .file-entry {
            padding: 5px;
            color: #000;
        }
        body.classic .file-entry.dir {
            color: #000;
        }
        body.classic .file-entry:hover {
            background-color: #ddd;
        }
        body.classic .file-entry.active {
            background-color: #bde;
        }
        body.classic #status-bar {
            display: none;
        }
        body.classic #btn-classic {
            background: #666;
            color: #fff;
        }
    </style>
</head>
<body>
    <div id="control-panel">
        <input type="text" id="textbox_file" placeholder="Enter file name...">
        <button onclick="loadFile()">Load</button>
        <button onclick="saveFile()" title="Ctrl+S">Save</button>
        <button onclick="newFile()">New</button>
        <button onclick="convertTabsToSpaces()">Tab to Space</button>
        <button onclick="removeTrailingSpaces()">No tail Space</button>
        <button onclick="openExternalLink()">Open Link</button>
        <button id="btn-classic" onclick="toggleClassic()" title="Toggle classic v2 style">Classic</button>
        <form method="post" action="logout.php">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <button type="submit">Logout</button>
        </form>
    </div>
    <div id="main-container">
        <div id="editor-container">
            <textarea id="textbox_code"></textarea>
        </div>
        <div id="toggle-browser" onclick="toggleFileBrowser()" title="Toggle file browser">&#9654;</div>
        <div id="file-browser">
            <div id="browser-dir"></div>
            <div id="browser-list"></div>
        </div>
    </div>
    <div id="status-bar">
        <span id="status-msg"></span>
        <span id="status-info"></span>
    </div>
    <script>
        const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
        const TAB_SPACES = '    ';
        CodeMirror.modeURL = <?= json_encode($cdn) ?> + '/mode/%N/%N.min.js';

        const fileInput = document.getElementById('textbox_file');
        const editor = CodeMirror.fromTextArea(document.getElementById('textbox_code'), {
            lineNumbers: true,
            mode: 'text/x-csrc',
            theme: 'monokai',
            indentUnit: 4,
            tabSize: 4,
            matchBrackets: true,
            styleActiveLine: true
        });

        let current = { path: '', hash: '', eol: '\n' };
        let currentDir = '';
        let loadSeq = 0;
        let listSeq = 0;

        // Classic mode toggle
        function toggleClassic() {
            document.body.classList.toggle('classic');
            try { localStorage.setItem('web_editer_classic', document.body.classList.contains('classic') ? '1' : ''); } catch(e) {}
            setTimeout(() => editor.refresh(), 50);
        }
        try { if (localStorage.getItem('web_editer_classic') === '1') document.body.classList.add('classic'); } catch(e) {}

        function setStatus(message, isError = false) {
            document.getElementById('status-msg').textContent = message;
            document.getElementById('status-bar').classList.toggle('error', isError);
        }

        function updateInfo() {
            const dirty = !editor.isClean();
            const mode = editor.getOption('mode');
            document.getElementById('status-info').textContent =
                [dirty ? 'modified' : '', current.eol === '\r\n' ? 'CRLF' : 'LF', mode].filter(Boolean).join(' | ');
            document.title = (dirty ? '* ' : '') + (current.path || 'Web Editor');
        }

        async function api(action, params) {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': CSRF_TOKEN },
                body: new URLSearchParams({ action, ...params })
            });
            if (response.status === 401) {
                window.location.href = 'login.php';
            }
            let data;
            try {
                data = await response.json();
            } catch (e) {
                throw new Error('Server error (' + response.status + ')');
            }
            if (!response.ok) {
                const error = new Error(data.error || 'Request failed');
                error.status = response.status;
                throw error;
            }
            return data;
        }

        function confirmDiscard() {
            return editor.isClean() || confirm('You have unsaved changes. Discard them?');
        }

        function setModeForFile(path) {
            const info = CodeMirror.findModeByFileName(path.split('/').pop());
            editor.setOption('mode', info ? info.mime : 'text/plain');
            if (info) {
                CodeMirror.autoLoadMode(editor, info.mode);
            }
        }

        async function loadFile(path = fileInput.value.trim()) {
            if (!path || !confirmDiscard()) {
                fileInput.value = current.path;
                return;
            }
            const seq = ++loadSeq;
            setStatus('Loading ' + path + ' ...');
            try {
                const data = await api('load', { path });
                if (seq !== loadSeq) {
                    return;
                }
                current = {
                    path: data.path,
                    hash: data.hash,
                    eol: data.content.includes('\r\n') ? '\r\n' : '\n'
                };
                fileInput.value = data.path;
                setModeForFile(data.path);
                editor.setValue(data.content);
                editor.clearHistory();
                editor.markClean();
                editor.focus();
                setStatus('Loaded ' + data.path + (data.writable ? '' : ' (read-only on server)'), !data.writable);
                markActiveEntry();
            } catch (error) {
                if (seq === loadSeq) {
                    fileInput.value = current.path;
                    setStatus(error.message, true);
                }
            }
            updateInfo();
        }

        async function saveFile() {
            const path = fileInput.value.trim();
            if (!path) {
                setStatus('Please enter a file name.', true);
                return;
            }
            const params = {
                path,
                content: editor.getValue(current.eol),
                hash: path === current.path ? current.hash : ''
            };
            setStatus('Saving ' + path + ' ...');
            try {
                let data;
                try {
                    data = await api('save', params);
                } catch (error) {
                    if (error.status !== 409 || !confirm(path + '\n' + error.message + '\nOverwrite it?')) {
                        throw error;
                    }
                    data = await api('save', { ...params, force: '1' });
                }
                const isNewPath = data.path !== current.path;
                current.path = data.path;
                current.hash = data.hash;
                fileInput.value = data.path;
                editor.markClean();
                setStatus('Saved ' + data.path + ' at ' + new Date().toLocaleTimeString());
                if (isNewPath) {
                    setModeForFile(data.path);
                    listFiles(currentDir);
                }
            } catch (error) {
                setStatus(error.message, true);
            }
            updateInfo();
        }

        async function newFile() {
            const name = prompt('New file name (in /' + currentDir + '):');
            if (!name || !name.trim()) {
                return;
            }
            const path = (currentDir ? currentDir + '/' : '') + name.trim();
            try {
                await api('save', { path, content: '', hash: '' });
            } catch (error) {
                setStatus(error.message, true);
                return;
            }
            await listFiles(currentDir);
            loadFile(path);
        }

        function markActiveEntry() {
            document.querySelectorAll('#browser-list .file-entry').forEach(entry => {
                entry.classList.toggle('active', entry.dataset.path === current.path);
            });
        }

        function addEntry(list, label, onclick, className = '', path = '') {
            const entry = document.createElement('div');
            entry.textContent = label;
            entry.className = 'file-entry ' + className;
            entry.dataset.path = path;
            entry.onclick = onclick;
            list.appendChild(entry);
        }

        async function listFiles(dir = '') {
            const seq = ++listSeq;
            try {
                const data = await api('list', { dir });
                if (seq !== listSeq) {
                    return;
                }
                currentDir = data.dir;
                document.getElementById('browser-dir').textContent = '/' + data.dir;
                const list = document.getElementById('browser-list');
                list.innerHTML = '';
                if (data.dir !== '') {
                    const parentDir = data.dir.split('/').slice(0, -1).join('/');
                    addEntry(list, '../', () => listFiles(parentDir), 'dir');
                }
                data.entries.forEach(item => {
                    if (item.isDir) {
                        addEntry(list, item.name + '/', () => listFiles(item.path), 'dir');
                    } else {
                        addEntry(list, item.name, () => loadFile(item.path), '', item.path);
                    }
                });
                markActiveEntry();
            } catch (error) {
                setStatus(error.message, true);
            }
        }

        function replaceInLines(pattern, replacement) {
            editor.operation(() => {
                for (let i = 0; i < editor.lineCount(); i++) {
                    const text = editor.getLine(i);
                    const newText = text.replace(pattern, replacement);
                    if (newText !== text) {
                        editor.replaceRange(newText, { line: i, ch: 0 }, { line: i, ch: text.length });
                    }
                }
            });
        }

        function convertTabsToSpaces() {
            replaceInLines(/\t/g, TAB_SPACES);
        }

        function removeTrailingSpaces() {
            replaceInLines(/[ \t]+$/, '');
        }

        function openExternalLink() {
            const fileName = fileInput.value.trim();
            if (!fileName) {
                setStatus('Please enter a file name.', true);
                return;
            }
            const safeFileName = fileName.split('/').map(encodeURIComponent).join('/');
            window.open(new URL('../' + safeFileName, window.location.href), '_blank');
        }

        fileInput.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                loadFile();
            }
        });
        document.addEventListener('keydown', event => {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
                event.preventDefault();
                saveFile();
            }
        });
        editor.on('changes', updateInfo);
        window.addEventListener('beforeunload', event => {
            if (!editor.isClean()) {
                event.preventDefault();
                event.returnValue = '';
            }
        });

        function toggleFileBrowser() {
            const fb = document.getElementById('file-browser');
            const btn = document.getElementById('toggle-browser');
            fb.classList.toggle('collapsed');
            btn.innerHTML = fb.classList.contains('collapsed') ? '&#9664;' : '&#9654;';
            setTimeout(() => editor.refresh(), 250);
        }

        updateInfo();
        listFiles();
    </script>
</body>
</html>
