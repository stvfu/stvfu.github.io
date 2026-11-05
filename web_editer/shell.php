<?php
declare(strict_types=1);
require __DIR__ . '/common.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

// AJAX: generate one-time WebSocket auth token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (!csrf_valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
    $token = bin2hex(random_bytes(32));
    $tokenDir = '/tmp/web_editer_shell_tokens';
    if (!is_dir($tokenDir)) {
        mkdir($tokenDir, 0700, true);
    }
    file_put_contents("$tokenDir/$token", json_encode(['created' => time()]));
    echo json_encode(['token' => $token]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terminal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@xterm/xterm@5.5.0/css/xterm.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            height: 100vh;
            display: flex;
            flex-direction: column;
            background: #1a1a1a;
            color: #e0e0e0;
            font-family: 'Menlo', 'Consolas', 'DejaVu Sans Mono', monospace;
            overflow: hidden;
        }
        #toolbar {
            background: #2d2d2d;
            padding: 6px 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
            border-bottom: 1px solid #444;
        }
        #toolbar a, #toolbar button {
            color: #f8f8f2;
            background: #555;
            border: none;
            padding: 4px 10px;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
            border-radius: 3px;
        }
        #toolbar a:hover, #toolbar button:hover { background: #666; }
        #toolbar .title {
            color: #a6e22e;
            font-weight: bold;
            font-size: 14px;
        }
        #toolbar .spacer { flex: 1; }
        .status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 4px;
        }
        .status-dot.connected { background: #a6e22e; }
        .status-dot.disconnected { background: #f92672; }
        .status-dot.connecting { background: #e6db74; animation: pulse 1s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
        #status-text { font-size: 12px; color: #999; }
        #terminal-container {
            flex: 1;
            overflow: hidden;
        }
    </style>
</head>
<body>
    <div id="toolbar">
        <span class="title">Terminal</span>
        <span id="status-dot" class="status-dot connecting"></span>
        <span id="status-text">Connecting...</span>
        <span class="spacer"></span>
        <a href="index.php">Editor</a>
        <button onclick="doReconnect()">Reconnect</button>
        <form method="post" action="logout.php" style="display:inline">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <button type="submit">Logout</button>
        </form>
    </div>
    <div id="terminal-container"></div>

    <script src="https://cdn.jsdelivr.net/npm/@xterm/xterm@5.5.0/lib/xterm.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@xterm/addon-fit@0.10.0/lib/addon-fit.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@xterm/addon-web-links@0.11.0/lib/addon-web-links.min.js"></script>
    <script>
    (function() {
        const CSRF = <?= json_encode(csrf_token()) ?>;
        const WS_URL = (location.protocol === 'https:' ? 'wss://' : 'ws://') +
                        location.host + '/ws/shell';

        const dotEl = document.getElementById('status-dot');
        const textEl = document.getElementById('status-text');
        let term, fitAddon, ws;
        let reconnecting = false;

        function setStatus(state, text) {
            dotEl.className = 'status-dot ' + state;
            textEl.textContent = text;
        }

        function initTerminal() {
            term = new Terminal({
                cursorBlink: true,
                fontSize: 14,
                fontFamily: "'Menlo', 'Consolas', 'DejaVu Sans Mono', monospace",
                theme: {
                    background: '#1a1a1a',
                    foreground: '#e0e0e0',
                    cursor: '#a6e22e',
                    selectionBackground: 'rgba(166, 226, 46, 0.3)',
                    black: '#272822',
                    red: '#f92672',
                    green: '#a6e22e',
                    yellow: '#f4bf75',
                    blue: '#66d9ef',
                    magenta: '#ae81ff',
                    cyan: '#a1efe4',
                    white: '#f8f8f2',
                    brightBlack: '#75715e',
                    brightRed: '#f92672',
                    brightGreen: '#a6e22e',
                    brightYellow: '#f4bf75',
                    brightBlue: '#66d9ef',
                    brightMagenta: '#ae81ff',
                    brightCyan: '#a1efe4',
                    brightWhite: '#f9f8f5'
                },
                scrollback: 10000
            });

            fitAddon = new FitAddon.FitAddon();
            term.loadAddon(fitAddon);
            term.loadAddon(new WebLinksAddon.WebLinksAddon());

            term.open(document.getElementById('terminal-container'));
            fitAddon.fit();

            term.onData(data => {
                if (ws && ws.readyState === WebSocket.OPEN) {
                    ws.send(data);
                }
            });

            term.onResize(({ cols, rows }) => {
                if (ws && ws.readyState === WebSocket.OPEN) {
                    ws.send(JSON.stringify({ type: 'resize', cols, rows }));
                }
            });

            window.addEventListener('resize', () => fitAddon.fit());

            new ResizeObserver(() => fitAddon.fit())
                .observe(document.getElementById('terminal-container'));
        }

        async function getToken() {
            const res = await fetch('shell.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': CSRF },
                body: new URLSearchParams({})
            });
            if (!res.ok) throw new Error('Token request failed');
            const data = await res.json();
            return data.token;
        }

        async function connect() {
            setStatus('connecting', 'Connecting...');

            let token;
            try {
                token = await getToken();
            } catch (e) {
                setStatus('disconnected', 'Auth failed');
                term.write('\r\n\x1b[31m[Failed to get auth token]\x1b[0m\r\n');
                return;
            }

            ws = new WebSocket(WS_URL);
            ws.binaryType = 'arraybuffer';

            ws.onopen = () => {
                ws.send(JSON.stringify({ token }));
                setStatus('connected', 'Connected');
                term.focus();
                const { cols, rows } = term;
                ws.send(JSON.stringify({ type: 'resize', cols, rows }));
            };

            ws.onmessage = (event) => {
                if (event.data instanceof ArrayBuffer) {
                    term.write(new Uint8Array(event.data));
                } else {
                    term.write(event.data);
                }
            };

            ws.onclose = () => {
                setStatus('disconnected', 'Disconnected');
                term.write('\r\n\x1b[90m[Session ended — click Reconnect]\x1b[0m\r\n');
            };

            ws.onerror = () => {
                setStatus('disconnected', 'Connection error');
            };
        }

        window.doReconnect = async function() {
            if (reconnecting) return;
            reconnecting = true;
            if (ws) {
                ws.onclose = null;
                ws.close();
            }
            term.write('\r\n\x1b[33m[Reconnecting...]\x1b[0m\r\n');
            await connect();
            reconnecting = false;
        };

        initTerminal();
        connect();
    })();
    </script>
</body>
</html>
