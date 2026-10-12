<?php
// 共用：設定、模型清單、session

$config = require '/var/www/data/claude/config.php';

// adaptive: 支援 adaptive thinking 與 effort；fallbacks: 被安全分類器拒絕時由伺服器端自動改用備援模型
const MODELS = [
    'claude-opus-5-5'   => ['label' => 'Claude Opus 5.5',            'adaptive' => true,  'fallbacks' => true,  'provider' => 'claude'],
    'claude-fable-5-1'  => ['label' => 'Claude Fable 5.1（最強）',   'adaptive' => true,  'fallbacks' => true,  'provider' => 'claude'],
    'claude-sonnet-5-5' => ['label' => 'Claude Sonnet 5.5（較快）',  'adaptive' => true,  'fallbacks' => true,  'provider' => 'claude'],
    'claude-haiku-4-5'  => ['label' => 'Claude Haiku 4.5（最快）',   'adaptive' => false, 'fallbacks' => false, 'provider' => 'claude'],
    'gpt-4o'            => ['label' => 'GPT-4o（尚未啟用）',         'adaptive' => false, 'fallbacks' => false, 'provider' => 'openai'],
    'grok-3'            => ['label' => 'Grok 3（尚未啟用）',         'adaptive' => false, 'fallbacks' => false, 'provider' => 'grok'],
    'gemini-2.5-pro'    => ['label' => 'Gemini 2.5 Pro（尚未啟用）', 'adaptive' => false, 'fallbacks' => false, 'provider' => 'gemini'],
];
const DEFAULT_MODEL = 'claude-opus-5-5';
const EFFORTS = ['low' => '低', 'medium' => '中', 'high' => '高', 'xhigh' => '極高', 'max' => '最高'];
const DEFAULT_EFFORT = 'medium';

session_name('CLAUDECHAT');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user']);
}

function csrf_ok(?string $token): bool
{
    return is_string($token) && hash_equals($_SESSION['csrf'], $token);
}

function api_key(array $config): string
{
    return $config['api_key'] !== '' ? $config['api_key'] : (string) getenv('ANTHROPIC_API_KEY');
}
