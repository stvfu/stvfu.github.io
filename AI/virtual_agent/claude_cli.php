<?php
// 訂閱模式：以 headless 方式呼叫 server 上的 claude 指令（不使用 API 金鑰）

const CLI_DEFAULT_SYSTEM = 'You are Claude, an AI assistant made by Anthropic, chatting with a user in a web chat window.';

function cli_ready(array $config): bool
{
    return is_executable($config['claude_bin']);
}

// claude -p 一次只吃一則提示，所以把先前的對話整理成一段文字一起送
function cli_prompt(array $messages): string
{
    $last = array_pop($messages);
    if (!$messages) {
        return $last['content'];
    }
    $out = "Below is the conversation so far, followed by the user's newest message. "
         . "Reply to the newest message, continuing the conversation naturally.\n\n<conversation_history>\n";
    foreach ($messages as $m) {
        $out .= "<{$m['role']}>\n{$m['content']}\n</{$m['role']}>\n";
    }
    return $out . "</conversation_history>\n\n<newest_user_message>\n{$last['content']}\n</newest_user_message>";
}

/**
 * 啟動 claude 並送出提示。成功回傳 [process, stdout, stderr 檔路徑]，失敗回傳錯誤訊息字串。
 */
function cli_start(array $config, string $model, ?string $effort, string $system, array $messages, bool $admin = false): array|string
{
    $bin = $config['claude_bin'];
    $home = rtrim($config['claude_home'], '/');
    if (!is_executable($bin)) {
        return "訂閱模式尚未就緒：網頁服務無法執行 {$bin}，請確認 config.php 的 claude_bin。";
    }
    $cwd = $home . '/work';
    if (!is_dir($cwd) && !@mkdir($cwd, 0700, true)) {
        return "訂閱模式尚未就緒：網頁服務無法寫入 {$home}，請確認 config.php 的 claude_home。";
    }

    $cmd = [
        $bin, '-p',
        '--output-format', 'stream-json', '--verbose', '--include-partial-messages',
        '--model', $model,
        '--system-prompt', trim($system) !== '' ? $system : CLI_DEFAULT_SYSTEM,
        '--strict-mcp-config', '--disable-slash-commands', '--no-session-persistence',
    ];
    if ($admin) {
        array_push($cmd, '--dangerously-skip-permissions');
    } else {
        array_push($cmd, '--tools', '');
    }
    if ($effort !== null) {
        array_push($cmd, '--effort', $effort);
    }

    $env = [
        'HOME' => $home,
        'PATH' => dirname($bin) . ':/usr/local/bin:/usr/bin:/bin',
        'LANG' => 'C.UTF-8',
    ];
    if (($config['claude_oauth_token'] ?? '') !== '') {
        $env['CLAUDE_CODE_OAUTH_TOKEN'] = $config['claude_oauth_token'];
    }

    $errFile = tempnam(sys_get_temp_dir(), 'claude_chat_err_');
    $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $errFile, 'w']], $pipes, $cwd, $env);
    if (!is_resource($proc)) {
        @unlink($errFile);
        return '無法啟動 claude。';
    }
    fwrite($pipes[0], cli_prompt($messages));
    fclose($pipes[0]);

    return [$proc, $pipes[1], $errFile];
}

function cli_error_message(string $detail): string
{
    $detail = trim($detail);
    if (preg_match('/not logged in|\/login|authenticat|oauth|invalid api key|401/i', $detail)) {
        return '訂閱模式尚未登入：請在 server 執行 claude setup-token，把產生的 token 填入 config.php 的 claude_oauth_token。'
             . ($detail !== '' ? "（{$detail}）" : '');
    }
    return $detail !== '' ? "claude 回報錯誤：{$detail}" : 'claude 沒有回應。';
}
