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
        /* action dialog (desktop: context menu, mobile: bottom sheet) */
        #action-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 999;
        }
        #action-dialog {
            display: none;
            position: fixed;
            z-index: 1000;
            background: #3e3d32;
            border: 1px solid #555;
            min-width: 160px;
            box-shadow: 2px 2px 8px rgba(0,0,0,.5);
        }
        #action-dialog .ad-title {
            padding: 8px 14px;
            font-size: 12px;
            color: #999;
            border-bottom: 1px solid #555;
            word-break: break-all;
        }
        #action-dialog .ad-item {
            padding: 10px 14px;
            cursor: pointer;
        }
        #action-dialog .ad-item:hover {
            background: #555;
        }
        @media (pointer: coarse) {
            #action-overlay { background: rgba(0,0,0,.4); }
            #action-dialog {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                top: auto;
                border: none;
                border-radius: 12px 12px 0 0;
                min-width: auto;
                padding-bottom: env(safe-area-inset-bottom, 8px);
            }
            #action-dialog .ad-item {
                padding: 14px 20px;
                font-size: 16px;
            }
            #action-dialog .ad-title {
                padding: 12px 20px;
                font-size: 14px;
                text-align: center;
            }
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
        body.classic #action-dialog {
            background: #fff;
            border-color: #ccc;
            color: #000;
        }
        body.classic #action-dialog .ad-title {
            color: #666;
            border-color: #ccc;
        }
        body.classic #action-dialog .ad-item:hover {
            background: #eee;
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
        <button onclick="newFolder()">Mkdir</button>
        <button onclick="deleteCurrentFile()">Delete</button>
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
    <div id="action-overlay"></div>
    <div id="action-dialog">
        <div class="ad-title" id="ad-title"></div>
        <div class="ad-item" data-action="open">Open</div>
        <div class="ad-item" data-action="rename">Rename</div>
        <div class="ad-item" data-action="delete">Delete</div>
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

        async function newFolder() {
            const name = prompt('New folder name (in /' + currentDir + '):');
            if (!name || !name.trim()) {
                return;
            }
            const path = (currentDir ? currentDir + '/' : '') + name.trim();
            try {
                await api('mkdir', { path });
                setStatus('Created folder /' + path);
            } catch (error) {
                setStatus(error.message, true);
                return;
            }
            await listFiles(currentDir);
        }

        async function deleteItem(path, isDir) {
            const label = isDir ? 'folder' : 'file';
            if (!confirm('Delete ' + label + ' "' + path + '"?')) {
                return;
            }
            try {
                try {
                    await api('delete', { path });
                } catch (error) {
                    if (error.status !== 409 || !confirm(path + ' is not empty.\nDelete it and all its contents?')) {
                        throw error;
                    }
                    await api('delete', { path, force: '1' });
                }
                setStatus('Deleted ' + path);
                if (path === current.path) {
                    current = { path: '', hash: '', eol: '\n' };
                    fileInput.value = '';
                    editor.setValue('');
                    editor.markClean();
                    updateInfo();
                }
            } catch (error) {
                setStatus(error.message, true);
                return;
            }
            await listFiles(currentDir);
        }

        async function deleteCurrentFile() {
            const path = fileInput.value.trim();
            if (!path) {
                setStatus('No file selected.', true);
                return;
            }
            await deleteItem(path, false);
        }

        async function renameItem(path) {
            const oldName = path.split('/').pop();
            const newName = prompt('Rename "' + oldName + '" to:', oldName);
            if (!newName || !newName.trim() || newName.trim() === oldName) {
                return;
            }
            try {
                const data = await api('rename', { path, name: newName.trim() });
                setStatus('Renamed to ' + data.new);
                if (path === current.path) {
                    current.path = data.new;
                    fileInput.value = data.new;
                    setModeForFile(data.new);
                    updateInfo();
                }
            } catch (error) {
                setStatus(error.message, true);
                return;
            }
            await listFiles(currentDir);
        }

        function markActiveEntry() {
            document.querySelectorAll('#browser-list .file-entry').forEach(entry => {
                entry.classList.toggle('active', entry.dataset.path === current.path);
            });
        }

        function addEntry(list, label, onclick, className = '', path = '', isDir = false) {
            const entry = document.createElement('div');
            entry.textContent = label;
            entry.className = 'file-entry ' + className;
            entry.dataset.path = path;
            entry.onclick = onclick;
            if (path) {
                entry.addEventListener('contextmenu', e => {
                    e.preventDefault();
                    showActionDialog(e.clientX, e.clientY, path, isDir);
                });
                let lpTimer = 0;
                let lpFired = false;
                entry.addEventListener('touchstart', () => {
                    lpFired = false;
                    lpTimer = setTimeout(() => { lpFired = true; }, 400);
                }, { passive: true });
                entry.addEventListener('touchend', e => {
                    clearTimeout(lpTimer);
                    if (lpFired) {
                        e.preventDefault();
                        showActionDialog(0, 0, path, isDir);
                        lpFired = false;
                    }
                });
                entry.addEventListener('touchmove', () => { clearTimeout(lpTimer); lpFired = false; });
                entry.addEventListener('click', e => {
                    if (lpFired) { e.preventDefault(); e.stopPropagation(); lpFired = false; }
                }, true);
            }
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
                        addEntry(list, item.name + '/', () => listFiles(item.path), 'dir', item.path, true);
                    } else {
                        addEntry(list, item.name, () => loadFile(item.path), '', item.path, false);
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

        // Action dialog
        let adTarget = { path: '', isDir: false };
        const adOverlay = document.getElementById('action-overlay');
        const adDialog = document.getElementById('action-dialog');
        const isTouch = matchMedia('(pointer: coarse)').matches;

        function showActionDialog(x, y, path, isDir) {
            adTarget = { path, isDir };
            document.getElementById('ad-title').textContent = path.split('/').pop();
            adDialog.querySelector('[data-action="open"]').textContent = isDir ? 'Open folder' : 'Open in editor';
            if (isTouch) {
                adDialog.style.left = '';
                adDialog.style.top = '';
            } else {
                adDialog.style.left = Math.min(x, window.innerWidth - 180) + 'px';
                adDialog.style.top = Math.min(y, window.innerHeight - 140) + 'px';
            }
            adOverlay.style.display = 'block';
            adDialog.style.display = 'block';
        }

        function hideActionDialog() {
            adOverlay.style.display = 'none';
            adDialog.style.display = 'none';
        }

        adOverlay.addEventListener('click', hideActionDialog);
        document.addEventListener('click', e => {
            if (!e.target.closest('#action-dialog') && !e.target.closest('.file-entry')) {
                hideActionDialog();
            }
        });

        adDialog.addEventListener('click', e => {
            const action = e.target.dataset.action;
            if (!action) return;
            hideActionDialog();
            if (action === 'open') {
                adTarget.isDir ? listFiles(adTarget.path) : loadFile(adTarget.path);
            } else if (action === 'rename') {
                renameItem(adTarget.path);
            } else if (action === 'delete') {
                deleteItem(adTarget.path, adTarget.isDir);
            }
        });

        updateInfo();
        listFiles();
    </script>
</body>
</html>
