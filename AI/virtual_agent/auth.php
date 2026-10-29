<?php
// 共用：設定、模型清單、session

$config = require '/var/www/data/claude/config.php';

// adaptive: 支援 adaptive thinking 與 effort；fallbacks: 被安全分類器拒絕時由伺服器端自動改用備援模型
const MODELS = [
    'claude-opus-5-5'   => ['label' => 'Claude Opus 5.5',            'version' => 'Opus 5.5',            'adaptive' => true,  'fallbacks' => true,  'provider' => 'claude'],
    'claude-fable-5-1'  => ['label' => 'Claude Fable 5.1（最強）',   'version' => 'Fable 5.1（最強）',   'adaptive' => true,  'fallbacks' => true,  'provider' => 'claude'],
    'claude-sonnet-5-5' => ['label' => 'Claude Sonnet 5.5（較快）',  'version' => 'Sonnet 5.5（較快）',  'adaptive' => true,  'fallbacks' => true,  'provider' => 'claude'],
    'claude-haiku-5-5'  => ['label' => 'Claude Haiku 5.5（最快）',   'version' => 'Haiku 5.5（最快）',   'adaptive' => false, 'fallbacks' => false, 'provider' => 'claude'],
    'gpt-5.5'           => ['label' => 'GPT-5.5（最新）',            'version' => '5.5（最新）',          'adaptive' => false, 'fallbacks' => false, 'provider' => 'openai'],
    'gpt-5.4'           => ['label' => 'GPT-5.4',                    'version' => '5.4',                  'adaptive' => false, 'fallbacks' => false, 'provider' => 'openai'],
    'gpt-5.4-mini'      => ['label' => 'GPT-5.4 Mini（較快）',       'version' => '5.4 Mini（較快）',     'adaptive' => false, 'fallbacks' => false, 'provider' => 'openai'],
    'grok-4.7'          => ['label' => 'Grok 4.7（最新）',           'version' => '4.7（最新）',          'adaptive' => false, 'fallbacks' => false, 'provider' => 'grok'],
    'grok-4.5'          => ['label' => 'Grok 4.5',                   'version' => '4.5',                  'adaptive' => false, 'fallbacks' => false, 'provider' => 'grok'],
    'gemini-4-argon'    => ['label' => 'Gemini 4 Argon（最新）',     'version' => '4 Argon（最新）',      'adaptive' => false, 'fallbacks' => false, 'provider' => 'gemini'],
    'gemini-3.8-flash'  => ['label' => 'Gemini 3.8 Flash（較快）',   'version' => '3.8 Flash（較快）',    'adaptive' => false, 'fallbacks' => false, 'provider' => 'gemini'],
    'gemini-3.6-flash'  => ['label' => 'Gemini 3.6 Flash（快）',     'version' => '3.6 Flash（快）',      'adaptive' => false, 'fallbacks' => false, 'provider' => 'gemini'],
];
const PROVIDERS = ['claude' => 'Claude', 'openai' => 'GPT', 'grok' => 'Grok', 'gemini' => 'Gemini'];
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

function openai_api_key(array $config): string
{
    return ($config['openai_api_key'] ?? '') !== '' ? $config['openai_api_key'] : (string) getenv('OPENAI_API_KEY');
}

function xai_api_key(array $config): string
{
    return ($config['xai_api_key'] ?? '') !== '' ? $config['xai_api_key'] : (string) getenv('XAI_API_KEY');
}

function google_api_key(array $config): string
{
    return ($config['google_api_key'] ?? '') !== '' ? $config['google_api_key'] : (string) getenv('GOOGLE_API_KEY');
}
