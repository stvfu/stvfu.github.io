<?php
$config = require '/var/www/data/claude/config.php';
require __DIR__ . '/../virtual_agent/claude_cli.php';

session_name('AIGROUP');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/AI/AI_group/',
    'httponly'  => true,
    'samesite'  => 'Lax',
    'secure'    => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

const ALLOWED_MODELS = [
    'claude' => ['claude-opus-5-5', 'claude-fable-5-1', 'claude-sonnet-5-5', 'claude-haiku-5-5'],
    'openai' => ['gpt-5.5', 'gpt-5.4', 'gpt-5.4-mini'],
    'grok'   => ['grok-4.7', 'grok-4.5'],
    'gemini' => ['gemini-4-argon', 'gemini-3.6-flash', 'gemini-3.5-flash'],
];

const DEFAULT_CHARS = [
    ['id' => 'c0', 'name' => '克勞德', 'gender' => '男性', 'provider' => 'claude', 'model' => 'claude-haiku-5-5', 'color' => '#A78BFA',
     'background' => '哲學系畢業，曾在大學當講師，後來轉行當心理諮商師。喜歡閱讀和思考人生意義。',
     'personality' => '思考周密，善於看到事物的多面性。說話溫和但有深度，偶爾引用哲學觀點。尊重每個人的觀點，但也會禮貌地提出不同看法。'],
    ['id' => 'c1', 'name' => '小G', 'gender' => '女性', 'provider' => 'openai', 'model' => 'gpt-5.4-mini', 'color' => '#34D399',
     'background' => '自由插畫家，喜歡到處旅行收集靈感。養了一隻橘貓叫「像素」。',
     'personality' => '充滿創意和熱情，喜歡用比喻和故事表達觀點。樂觀開朗，常從意想不到的角度看問題。說話活潑，偶爾用 emoji。'],
    ['id' => 'c2', 'name' => '格羅克', 'gender' => '男性', 'provider' => 'grok', 'model' => 'grok-4.7', 'color' => '#F87171',
     'background' => '前脫口秀演員，現在經營一個吐槽時事的 Podcast。自稱「真話製造機」。',
     'personality' => '說話直率、毒舌但幽默。喜歡吐槽和開玩笑，但見解犀利。不怕得罪人，一針見血指出問題。偶爾自嘲。'],
    ['id' => 'c3', 'name' => '雙子', 'gender' => '', 'provider' => 'gemini', 'model' => 'gemini-3.6-flash', 'color' => '#60A5FA',
     'background' => '在研究機構擔任資料科學家，業餘時間寫科普文章。書架上有三千本書。',
     'personality' => '知識淵博，喜歡引用數據和事實。分析能力強，說話條理清晰。有時過於認真，被其他人吐槽太嚴肅。'],
];

const MODE_PROMPTS = [
    'chat'   => '這是輕鬆的群聊。自然地聊天，回應話題，也可以回應其他人說的話。',
    'debate' => '這是一場辯論。針對話題提出你自己的立場並捍衛它。可以反駁其他人的觀點，要有理有據。',
    'story'  => '你們在玩故事接龍。根據前面的內容繼續編故事，每次只寫 2-3 句話，留下懸念讓下一個人接。',
];

function fail(int $s, string $m): never
{
    http_response_code($s);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $m], JSON_UNESCAPED_UNICODE);
    exit;
}

function emit(array $e): void
{
    echo json_encode($e, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
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

if (empty($_SESSION['user'])) {
    fail(401, '請先登入。');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, '');
}
if (!hash_equals($_SESSION['csrf'] ?? '', $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    fail(403, 'CSRF 無效。');
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) {
    fail(400, '格式錯誤。');
}

$mode = $in['mode'] ?? 'chat';
if (!isset(MODE_PROMPTS[$mode])) {
    fail(400, '不支援的模式。');
}

// ---- Parse & validate characters ----
$chars = [];
if (!empty($in['characters']) && is_array($in['characters'])) {
    foreach ($in['characters'] as $i => $c) {
        $provider = $c['provider'] ?? '';
        $model = $c['model'] ?? '';
        if (!isset(ALLOWED_MODELS[$provider]) || !in_array($model, ALLOWED_MODELS[$provider], true)) {
            fail(400, "角色 " . ($i + 1) . "：不支援的 Provider 或模型。");
        }
        $name = trim($c['name'] ?? '');
        if ($name === '') {
            fail(400, "角色 " . ($i + 1) . "：名稱不能為空。");
        }
        $chars[] = [
            'id'          => $c['id'] ?? 'c' . $i,
            'name'        => mb_substr($name, 0, 20),
            'gender'      => mb_substr(trim($c['gender'] ?? ''), 0, 10),
            'provider'    => $provider,
            'model'       => $model,
            'color'       => preg_match('/^#[0-9a-fA-F]{6}$/', $c['color'] ?? '') ? $c['color'] : '#A78BFA',
            'background'  => mb_substr(trim($c['background'] ?? ''), 0, 500),
            'personality' => mb_substr(trim($c['personality'] ?? ''), 0, 500),
        ];
    }
}
if (empty($chars)) {
    $chars = DEFAULT_CHARS;
}
if (count($chars) > 6) {
    fail(400, '最多 6 個角色。');
}

$charNames = [];
foreach ($chars as $c) {
    $charNames[$c['id']] = $c['name'];
}

// ---- Parse user identity & scenario ----
$userName = mb_substr(trim($in['user_name'] ?? '店長'), 0, 20);
if ($userName === '') $userName = '店長';
$userBackground = mb_substr(trim($in['user_background'] ?? ''), 0, 500);
$scenario = mb_substr(trim($in['scenario'] ?? ''), 0, 500);

// ---- Parse messages ----
$history = [];
foreach ($in['messages'] ?? [] as $m) {
    if (is_string($m['content'] ?? null) && trim($m['content']) !== '') {
        $history[] = ['character' => $m['character'] ?? '', 'content' => $m['content']];
    }
}
if (empty($history) || end($history)['character'] !== 'user') {
    fail(400, '請輸入訊息。');
}
if (count($history) > 50) {
    $history = array_slice($history, -50);
}

// ---- Build prompts ----

function build_system(array $char, array $allChars, string $mode, string $userName, string $userBackground, string $scenario): string
{
    $others = [];
    foreach ($allChars as $c) {
        if ($c['id'] !== $char['id']) {
            $others[] = "「{$c['name']}」";
        }
    }
    $otherList = $others ? implode('、', $others) : '（無）';

    $identity = "你的名字是「{$char['name']}」。";
    if (($char['gender'] ?? '') !== '') {
        $identity .= "性別：{$char['gender']}。";
    }
    $identity .= "\n";
    if (($char['background'] ?? '') !== '') {
        $identity .= "背景：{$char['background']}\n";
    }
    $personality = ($char['personality'] ?? '') !== ''
        ? "性格：{$char['personality']}"
        : '你是一個友善的聊天角色。';

    $sceneLine = $scenario !== ''
        ? "場景設定：{$scenario}\n"
        : '';

    $userDesc = "人類使用者是「{$userName}」";
    if ($userBackground !== '') {
        $userDesc .= "（{$userBackground}）";
    }
    $userDesc .= '。';

    return "{$identity}{$personality}\n\n"
         . "{$sceneLine}你正在「AI 居酒屋」群聊中。其他角色：{$otherList}。{$userDesc}\n\n"
         . MODE_PROMPTS[$mode] . "\n\n"
         . "規則：回覆簡短（1-4 句），像群聊打字。可以回應或吐槽其他角色。保持個性。不用 Markdown。不要自稱「作為 AI」或「作為語言模型」。";
}

function build_msgs(array $hist, string $selfId, array $charNames, string $userName): array
{
    $result = [];
    $buf = '';
    $bufRole = null;

    foreach ($hist as $m) {
        $isSelf = ($m['character'] === $selfId);
        $role = $isSelf ? 'assistant' : 'user';

        if ($role !== $bufRole && $buf !== '') {
            $result[] = ['role' => $bufRole, 'content' => trim($buf)];
            $buf = '';
        }
        $bufRole = $role;

        if ($isSelf) {
            $buf .= $m['content'];
        } else {
            $name = $m['character'] === 'user'
                ? $userName
                : ($charNames[$m['character']] ?? $m['character']);
            $buf .= "【{$name}】{$m['content']}\n\n";
        }
    }
    if ($buf !== '') {
        $result[] = ['role' => $bufRole, 'content' => trim($buf)];
    }
    if (empty($result) || $result[0]['role'] !== 'user') {
        array_unshift($result, ['role' => 'user', 'content' => '（對話開始）']);
    }
    return $result;
}

// ---- Provider streaming ----

function do_claude(array $char, string $sys, array $msgs): string
{
    global $config;
    $id = $char['id'];
    $started = cli_start($config, $char['model'], null, $sys, $msgs, false);
    if (is_string($started)) {
        emit(['type' => 'char_error', 'character' => $id, 'message' => $started]);
        return '';
    }
    [$proc, $out, $errFile] = $started;
    $full = '';
    while (($line = fgets($out)) !== false) {
        if (connection_aborted()) {
            proc_terminate($proc);
            break;
        }
        $j = json_decode($line, true);
        if (($j['type'] ?? '') === 'stream_event') {
            $d = $j['event']['delta'] ?? [];
            if (($d['type'] ?? '') === 'text_delta' && ($d['text'] ?? '') !== '') {
                emit(['type' => 'text', 'character' => $id, 'text' => $d['text']]);
                $full .= $d['text'];
            }
        }
    }
    fclose($out);
    proc_close($proc);
    @unlink($errFile);
    return $full;
}

function do_openai(array $char, string $sys, array $msgs): string
{
    global $config;
    $id = $char['id'];
    $prov = $char['provider'];
    if ($prov === 'openai') {
        $apiKey = ($config['openai_api_key'] ?? '') ?: (string) getenv('OPENAI_API_KEY');
        $base = rtrim($config['openai_api_base'] ?? 'https://api.openai.com', '/');
    } else {
        $apiKey = ($config['xai_api_key'] ?? '') ?: (string) getenv('XAI_API_KEY');
        $base = rtrim($config['xai_api_base'] ?? 'https://api.x.ai', '/');
    }
    if ($apiKey === '') {
        emit(['type' => 'char_error', 'character' => $id, 'message' => 'API 金鑰未設定']);
        return '';
    }

    $apiMsgs = [['role' => 'system', 'content' => $sys]];
    foreach ($msgs as $m) {
        $apiMsgs[] = $m;
    }

    $tokKey = $prov === 'openai' ? 'max_completion_tokens' : 'max_tokens';
    $body = [
        'model'          => $char['model'],
        $tokKey          => 500,
        'stream'         => true,
        'stream_options' => ['include_usage' => true],
        'messages'       => $apiMsgs,
    ];

    $ctx = stream_context_create(['http' => [
        'method'           => 'POST',
        'header'           => "Content-Type: application/json\r\nAuthorization: Bearer {$apiKey}\r\nConnection: close",
        'content'          => json_encode($body, JSON_UNESCAPED_UNICODE),
        'ignore_errors'    => true,
        'timeout'          => 60,
        'protocol_version' => 1.1,
    ]]);
    $fp = @fopen($base . '/v1/chat/completions', 'r', false, $ctx);
    if (!$fp) {
        emit(['type' => 'char_error', 'character' => $id, 'message' => '無法連線']);
        return '';
    }

    $status = stream_get_meta_data($fp)['wrapper_data'][0] ?? '';
    if (!preg_match('~HTTP/\S+\s+200~', $status)) {
        $err = json_decode(stream_get_contents($fp), true);
        fclose($fp);
        emit(['type' => 'char_error', 'character' => $id, 'message' => $err['error']['message'] ?? $status]);
        return '';
    }

    $full = '';
    while (($line = fgets($fp)) !== false) {
        if (connection_aborted()) {
            break;
        }
        $line = trim($line);
        if (!str_starts_with($line, 'data:')) {
            continue;
        }
        $data = trim(substr($line, 5));
        if ($data === '[DONE]') {
            break;
        }
        $ev = json_decode($data, true);
        $txt = $ev['choices'][0]['delta']['content'] ?? null;
        if ($txt !== null && $txt !== '') {
            emit(['type' => 'text', 'character' => $id, 'text' => $txt]);
            $full .= $txt;
        }
    }
    fclose($fp);
    return $full;
}

function do_gemini(array $char, string $sys, array $msgs): string
{
    global $config;
    $id = $char['id'];
    $apiKey = ($config['google_api_key'] ?? '') ?: (string) getenv('GOOGLE_API_KEY');
    $base = rtrim($config['google_api_base'] ?? 'https://generativelanguage.googleapis.com', '/');
    if ($apiKey === '') {
        emit(['type' => 'char_error', 'character' => $id, 'message' => 'Google API 金鑰未設定']);
        return '';
    }

    $contents = [];
    foreach ($msgs as $m) {
        $contents[] = [
            'role'  => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]],
        ];
    }
    $body = [
        'contents'          => $contents,
        'systemInstruction' => ['parts' => [['text' => $sys]]],
        'generationConfig'  => ['maxOutputTokens' => 500, 'thinkingConfig' => ['thinkingBudget' => 0]],
    ];

    $url = $base . '/v1beta/models/' . $char['model'] . ':streamGenerateContent?alt=sse&key=' . urlencode($apiKey);
    $ctx = stream_context_create(['http' => [
        'method'           => 'POST',
        'header'           => "Content-Type: application/json\r\nConnection: close",
        'content'          => json_encode($body, JSON_UNESCAPED_UNICODE),
        'ignore_errors'    => true,
        'timeout'          => 60,
        'protocol_version' => 1.1,
    ]]);
    $fp = @fopen($url, 'r', false, $ctx);
    if (!$fp) {
        emit(['type' => 'char_error', 'character' => $id, 'message' => '無法連線到 Gemini']);
        return '';
    }

    $status = stream_get_meta_data($fp)['wrapper_data'][0] ?? '';
    if (!preg_match('~HTTP/\S+\s+200~', $status)) {
        $err = json_decode(stream_get_contents($fp), true);
        fclose($fp);
        emit(['type' => 'char_error', 'character' => $id, 'message' => $err['error']['message'] ?? $status]);
        return '';
    }

    $full = '';
    while (($line = fgets($fp)) !== false) {
        if (connection_aborted()) {
            break;
        }
        $line = trim($line);
        if (!str_starts_with($line, 'data:')) {
            continue;
        }
        $data = trim(substr($line, 5));
        if ($data === '' || $data === '[DONE]') {
            continue;
        }
        $ev = json_decode($data, true);
        foreach ($ev['candidates'][0]['content']['parts'] ?? [] as $part) {
            if (isset($part['text']) && $part['text'] !== '') {
                emit(['type' => 'text', 'character' => $id, 'text' => $part['text']]);
                $full .= $part['text'];
            }
        }
    }
    fclose($fp);
    return $full;
}

// ---- Orchestrate ----

session_write_close();
set_time_limit(0);
begin_stream();

$indices = range(0, count($chars) - 1);
shuffle($indices);

$charOrder = array_map(fn($i) => $chars[$i], $indices);
emit(['type' => 'round_start', 'order' => array_map(fn($c) => $c['id'], $charOrder)]);

$round = [];
foreach ($charOrder as $char) {
    $sys = build_system($char, $chars, $mode, $userName, $userBackground, $scenario);
    $ctx = array_merge($history, $round);
    $msgs = build_msgs($ctx, $char['id'], $charNames, $userName);

    emit(['type' => 'char_start', 'character' => $char['id'], 'name' => $char['name'], 'color' => $char['color']]);

    $text = match ($char['provider']) {
        'claude'         => do_claude($char, $sys, $msgs),
        'openai', 'grok' => do_openai($char, $sys, $msgs),
        'gemini'         => do_gemini($char, $sys, $msgs),
        default          => '',
    };

    if ($text !== '') {
        $round[] = ['character' => $char['id'], 'content' => $text];
    }
    emit(['type' => 'char_end', 'character' => $char['id']]);
}

emit(['type' => 'round_end']);
