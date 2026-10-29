<?php
// 對話端點：驗證登入後把請求轉給 Claude（訂閱模式走 claude 指令，API 模式走 Claude API），
// 並以 NDJSON 串流回瀏覽器
require __DIR__ . '/auth.php';
require __DIR__ . '/claude_cli.php';

function fail(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function emit(array $event): void
{
    echo json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    flush();
}

function begin_stream(): void
{
    header('Content-Type: application/x-ndjson; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    if (function_exists('apache_setenv')) {
        apache_setenv('no-gzip', '1');
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

// 處理一個 Messages API 串流事件（兩種模式共用）。回傳 false 表示遇到錯誤、應停止。
function handle_event(array $ev, array &$st): bool
{
    switch ($ev['type'] ?? '') {
        case 'message_start':
            $st['model'] = $ev['message']['model'] ?? $st['model'];
            $st['usage']['input_tokens'] = $ev['message']['usage']['input_tokens'] ?? 0;
            break;
        case 'content_block_delta':
            $d = $ev['delta'] ?? [];
            if (($d['type'] ?? '') === 'text_delta') {
                emit(['type' => 'text', 'text' => $d['text']]);
            } elseif (($d['type'] ?? '') === 'thinking_delta' && ($d['thinking'] ?? '') !== '') {
                emit(['type' => 'thinking', 'text' => $d['thinking']]);
            }
            break;
        case 'message_delta':
            $st['stop_reason'] = $ev['delta']['stop_reason'] ?? $st['stop_reason'];
            $st['usage']['output_tokens'] = $ev['usage']['output_tokens'] ?? $st['usage']['output_tokens'];
            break;
        case 'message_stop':
            $st['finished'] = true;
            break;
        case 'error':
            emit(['type' => 'error', 'message' => $ev['error']['message'] ?? '串流發生錯誤。']);
            $st['finished'] = true;
            return false;
    }
    return true;
}

if (!is_logged_in()) {
    fail(401, '登入已過期，請重新整理頁面後再登入。');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    fail(403, '請求無效，請重新整理頁面。');
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) {
    fail(400, '請求格式錯誤。');
}

$mode = $in['mode'] ?? 'cli';
if (!in_array($mode, ['cli', 'api'], true)) {
    fail(400, '不支援的模式。');
}
$model = $in['model'] ?? DEFAULT_MODEL;
if (!isset(MODELS[$model])) {
    fail(400, '不支援的模型。');
}
$provider = MODELS[$model]['provider'] ?? 'claude';
if (!in_array($provider, ['claude', 'openai', 'grok', 'gemini'], true)) {
    fail(501, MODELS[$model]['label'] . '：此模型尚未啟用，敬請期待！');
}
if ($provider !== 'claude') {
    $mode = 'api';
}
$effort = $in['effort'] ?? DEFAULT_EFFORT;
if (!isset(EFFORTS[$effort])) {
    fail(400, '不支援的思考強度。');
}
$system = is_string($in['system'] ?? null) ? $in['system'] : '';
$admin = !empty($in['admin']);

$messages = [];
foreach ($in['messages'] ?? [] as $m) {
    $role = $m['role'] ?? '';
    $content = $m['content'] ?? '';
    if (!in_array($role, ['user', 'assistant'], true) || !is_string($content) || trim($content) === '') {
        fail(400, '訊息格式錯誤。');
    }
    $messages[] = ['role' => $role, 'content' => $content];
}
if (!$messages || end($messages)['role'] !== 'user') {
    fail(400, '請輸入訊息。');
}

// 串流期間不佔住 session 鎖
session_write_close();
set_time_limit(0);

$st = [
    'model'       => $model,
    'usage'       => ['input_tokens' => 0, 'output_tokens' => 0],
    'stop_reason' => null,
    'finished'    => false,
    'plan_usage'  => null,
];

if ($mode === 'cli') {
    // ---- 訂閱模式：server 上的 claude 指令 ----
    $started = cli_start($config, $model, MODELS[$model]['adaptive'] ? $effort : null, $system, $messages, $admin);
    if (is_string($started)) {
        fail(500, $started);
    }
    [$proc, $out, $errFile] = $started;
    begin_stream();

    $cliError = null;
    while (($line = fgets($out)) !== false) {
        if (connection_aborted()) {
            proc_terminate($proc);
            break;
        }
        $j = json_decode($line, true);
        if (!is_array($j)) {
            continue;
        }
        switch ($j['type'] ?? '') {
            case 'stream_event':
                handle_event($j['event'] ?? [], $st);
                break;
            case 'rate_limit_event':
                $st['plan_usage'] = $j['rate_limit_info']['unifiedWindows']['five_hour']['utilization'] ?? null;
                break;
            case 'result':
                $st['finished'] = true;
                $st['stop_reason'] ??= $j['stop_reason'] ?? null;
                if (!empty($j['is_error'])) {
                    $cliError = (string) ($j['result'] ?? '');
                }
                break;
        }
    }
    fclose($out);
    proc_close($proc);
    $stderr = (string) @file_get_contents($errFile);
    @unlink($errFile);

    if ($cliError !== null || (!$st['finished'] && !connection_aborted())) {
        emit(['type' => 'error', 'message' => cli_error_message($cliError ?? substr($stderr, -500))]);
        exit;
    }
} elseif ($provider === 'gemini') {
    // ---- Google Gemini API ----
    $key = google_api_key($config);
    $base = rtrim($config['google_api_base'] ?? 'https://generativelanguage.googleapis.com', '/');
    if ($key === '') {
        fail(500, '尚未設定 Google API 金鑰：請在 config.php 填入 google_api_key。');
    }

    $contents = [];
    foreach ($messages as $m) {
        $contents[] = [
            'role' => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]],
        ];
    }

    $body = [
        'contents' => $contents,
        'generationConfig' => ['maxOutputTokens' => 16384],
    ];
    if (trim($system) !== '') {
        $body['systemInstruction'] = ['parts' => [['text' => $system]]];
    }

    $url = $base . '/v1beta/models/' . $model . ':streamGenerateContent?alt=sse&key=' . urlencode($key);
    $headers = [
        'Content-Type: application/json',
        'Connection: close',
    ];

    $ctx = stream_context_create(['http' => [
        'method'           => 'POST',
        'header'           => implode("\r\n", $headers),
        'content'          => json_encode($body, JSON_UNESCAPED_UNICODE),
        'ignore_errors'    => true,
        'timeout'          => 600,
        'protocol_version' => 1.1,
    ]]);
    $fp = @fopen($url, 'r', false, $ctx);
    if (!$fp) {
        fail(502, '無法連線到 Google Gemini API，請稍後再試。');
    }

    $statusLine = stream_get_meta_data($fp)['wrapper_data'][0] ?? '';
    $httpStatus = preg_match('~^HTTP/\S+\s+(\d{3})~', $statusLine, $mm) ? (int) $mm[1] : 0;
    if ($httpStatus !== 200) {
        $err = json_decode(stream_get_contents($fp), true);
        fclose($fp);
        $detail = $err['error']['message'] ?? $statusLine;
        $hint = match (true) {
            $httpStatus === 400 => 'Google API 請求格式錯誤',
            $httpStatus === 403 => 'Google API 金鑰無權限或已停用',
            $httpStatus === 429 => '已達用量或速率上限',
            $httpStatus >= 500  => 'Google Gemini API 發生錯誤',
            default             => 'Google Gemini API 拒絕了這個請求',
        };
        fail(502, "{$hint}（{$httpStatus}）：{$detail}");
    }

    begin_stream();
    $geminiInputTokens = 0;
    $geminiOutputTokens = 0;
    while (($line = fgets($fp)) !== false) {
        if (connection_aborted()) break;
        $line = trim($line);
        if (!str_starts_with($line, 'data:')) continue;
        $data = trim(substr($line, 5));
        if ($data === '' || $data === '[DONE]') continue;
        $ev = json_decode($data, true);
        if (!is_array($ev)) continue;

        $candidate = $ev['candidates'][0] ?? null;
        if ($candidate) {
            $parts = $candidate['content']['parts'] ?? [];
            foreach ($parts as $part) {
                if (isset($part['text']) && $part['text'] !== '') {
                    emit(['type' => 'text', 'text' => $part['text']]);
                }
            }
            $finishReason = $candidate['finishReason'] ?? null;
            if ($finishReason !== null) {
                $st['stop_reason'] = match ($finishReason) {
                    'STOP'          => 'end_turn',
                    'MAX_TOKENS'    => 'max_tokens',
                    'SAFETY'        => 'refusal',
                    'RECITATION'    => 'refusal',
                    default         => $finishReason,
                };
            }
        }
        if (isset($ev['usageMetadata'])) {
            $geminiInputTokens = $ev['usageMetadata']['promptTokenCount'] ?? 0;
            $geminiOutputTokens = $ev['usageMetadata']['candidatesTokenCount'] ?? 0;
        }
        if (isset($ev['modelVersion'])) {
            $st['model'] = $ev['modelVersion'];
        }
    }
    fclose($fp);
    $st['finished'] = true;
    $st['usage']['input_tokens'] = $geminiInputTokens;
    $st['usage']['output_tokens'] = $geminiOutputTokens;
    $st['stop_reason'] ??= 'end_turn';
} elseif ($provider === 'openai' || $provider === 'grok') {
    // ---- OpenAI 相容 API（OpenAI / xAI）----
    if ($provider === 'openai') {
        $key = openai_api_key($config);
        $base = rtrim($config['openai_api_base'] ?? 'https://api.openai.com', '/');
        $apiName = 'OpenAI';
    } else {
        $key = xai_api_key($config);
        $base = rtrim($config['xai_api_base'] ?? 'https://api.x.ai', '/');
        $apiName = 'xAI';
    }
    if ($key === '') {
        fail(500, "尚未設定 {$apiName} API 金鑰：請在 config.php 填入對應的 api_key。");
    }

    $apiMessages = [];
    if (trim($system) !== '') {
        $apiMessages[] = ['role' => 'system', 'content' => $system];
    }
    foreach ($messages as $m) {
        $apiMessages[] = $m;
    }

    $tokenLimit = $provider === 'openai'
        ? ['max_completion_tokens' => 16384]
        : ['max_tokens' => 16384];
    $body = [
        'model'          => $model,
        'stream'         => true,
        'stream_options' => ['include_usage' => true],
        'messages'       => $apiMessages,
    ] + $tokenLimit;
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $key,
        'Connection: close',
    ];

    $ctx = stream_context_create(['http' => [
        'method'           => 'POST',
        'header'           => implode("\r\n", $headers),
        'content'          => json_encode($body, JSON_UNESCAPED_UNICODE),
        'ignore_errors'    => true,
        'timeout'          => 600,
        'protocol_version' => 1.1,
    ]]);
    $fp = @fopen($base . '/v1/chat/completions', 'r', false, $ctx);
    if (!$fp) {
        fail(502, "無法連線到 {$apiName} API，請稍後再試。");
    }

    $statusLine = stream_get_meta_data($fp)['wrapper_data'][0] ?? '';
    $httpStatus = preg_match('~^HTTP/\S+\s+(\d{3})~', $statusLine, $mm) ? (int) $mm[1] : 0;
    if ($httpStatus !== 200) {
        $err = json_decode(stream_get_contents($fp), true);
        fclose($fp);
        $detail = $err['error']['message'] ?? $statusLine;
        $hint = match (true) {
            $httpStatus === 401 => 'API 金鑰無效',
            $httpStatus === 429 => '已達用量或速率上限',
            $httpStatus >= 500  => "{$apiName} API 發生錯誤",
            default             => "{$apiName} API 拒絕了這個請求",
        };
        fail(502, "{$hint}（{$httpStatus}）：{$detail}");
    }

    begin_stream();
    while (($line = fgets($fp)) !== false) {
        if (connection_aborted()) break;
        $line = trim($line);
        if (!str_starts_with($line, 'data:')) continue;
        $data = trim(substr($line, 5));
        if ($data === '[DONE]') {
            $st['finished'] = true;
            break;
        }
        $ev = json_decode($data, true);
        if (!is_array($ev)) continue;

        $choice = $ev['choices'][0] ?? null;
        if ($choice) {
            $delta = $choice['delta'] ?? [];
            if (isset($delta['content']) && $delta['content'] !== '') {
                emit(['type' => 'text', 'text' => $delta['content']]);
            }
            if (($choice['finish_reason'] ?? null) !== null) {
                $st['stop_reason'] = match ($choice['finish_reason']) {
                    'stop'           => 'end_turn',
                    'length'         => 'max_tokens',
                    'content_filter' => 'refusal',
                    default          => $choice['finish_reason'],
                };
            }
        }
        if (isset($ev['usage'])) {
            $st['usage']['input_tokens'] = $ev['usage']['prompt_tokens'] ?? 0;
            $st['usage']['output_tokens'] = $ev['usage']['completion_tokens'] ?? 0;
        }
        if (isset($ev['model'])) {
            $st['model'] = $ev['model'];
        }
    }
    fclose($fp);
} else {
    // ---- Claude API 模式 ----
    $key = api_key($config);
    if ($key === '') {
        fail(500, '尚未設定 API 金鑰：請在 config.php 的 api_key 填入 Anthropic API 金鑰，或取消勾選「API 模式」。');
    }

    $body = [
        'model'      => $model,
        'max_tokens' => 64000,
        'stream'     => true,
        'messages'   => $messages,
    ];
    if (trim($system) !== '') {
        $body['system'] = $system;
    }
    $headers = [
        'Content-Type: application/json',
        'x-api-key: ' . $key,
        'anthropic-version: 2023-06-01',
        'Connection: close',
    ];
    if (MODELS[$model]['adaptive']) {
        $body['thinking'] = ['type' => 'adaptive', 'display' => 'summarized'];
        $body['output_config'] = ['effort' => $effort];
    }
    if (MODELS[$model]['fallbacks']) {
        $body['fallbacks'] = 'default';
        $headers[] = 'anthropic-beta: server-side-fallback-2026-07-01';
    }

    $ctx = stream_context_create(['http' => [
        'method'           => 'POST',
        'header'           => implode("\r\n", $headers),
        'content'          => json_encode($body, JSON_UNESCAPED_UNICODE),
        'ignore_errors'    => true,
        'timeout'          => 600,
        'protocol_version' => 1.1,
    ]]);
    $fp = @fopen(rtrim($config['api_base'], '/') . '/v1/messages', 'r', false, $ctx);
    if (!$fp) {
        fail(502, '無法連線到 Claude API，請稍後再試。');
    }

    $statusLine = stream_get_meta_data($fp)['wrapper_data'][0] ?? '';
    $status = preg_match('~^HTTP/\S+\s+(\d{3})~', $statusLine, $mm) ? (int) $mm[1] : 0;
    if ($status !== 200) {
        $err = json_decode(stream_get_contents($fp), true);
        fclose($fp);
        $detail = $err['error']['message'] ?? $statusLine;
        $hint = match (true) {
            $status === 401 => 'API 金鑰無效',
            $status === 429 => '已達用量或速率上限',
            $status === 529 => 'Claude API 目前負載過高',
            $status >= 500  => 'Claude API 發生錯誤',
            default         => 'Claude API 拒絕了這個請求',
        };
        fail(502, "{$hint}（{$status}）：{$detail}");
    }

    begin_stream();
    while (($line = fgets($fp)) !== false) {
        if (connection_aborted()) {
            break;
        }
        if (!str_starts_with($line, 'data:')) {
            continue;
        }
        $ev = json_decode(substr($line, 5), true);
        if (is_array($ev) && !handle_event($ev, $st)) {
            break;
        }
    }
    fclose($fp);
}

if ($st['stop_reason'] !== null) {
    emit([
        'type' => 'done', 'mode' => $mode, 'stop_reason' => $st['stop_reason'],
        'model' => $st['model'], 'usage' => $st['usage'], 'plan_usage' => $st['plan_usage'],
    ]);
} elseif (!$st['finished'] && !connection_aborted()) {
    emit(['type' => 'error', 'message' => '與 AI 的連線中斷，回覆可能不完整。']);
}
