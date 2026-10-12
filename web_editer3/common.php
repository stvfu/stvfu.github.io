<?php
declare(strict_types=1);

define('BASE_DIR', realpath(__DIR__ . '/..'));
define('MAX_FILE_SIZE', 2 * 1024 * 1024);

session_name('web_editer3');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

function is_logged_in(): bool
{
    return ($_SESSION['loggedin'] ?? false) === true;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

// 把相對路徑轉成 BASE_DIR 內的絕對路徑;跑出 BASE_DIR 或不合法時回傳 null。
// $mustExist 為 false 時允許檔案本身不存在 (但上層資料夾必須存在)。
function resolve_path(string $rel, bool $mustExist = true): ?string
{
    if (str_contains($rel, "\0")) {
        return null;
    }
    $target = BASE_DIR . '/' . ltrim($rel, '/');
    $real = realpath($target);
    if ($real === false) {
        if ($mustExist || is_link($target)) {
            return null;
        }
        $parent = realpath(dirname($target));
        $name = basename($target);
        if ($parent === false || $name === '' || $name === '.' || $name === '..') {
            return null;
        }
        $real = $parent . '/' . $name;
    }
    if ($real !== BASE_DIR && !str_starts_with($real, BASE_DIR . '/')) {
        return null;
    }
    return $real;
}

function relative_path(string $abs): string
{
    return $abs === BASE_DIR ? '' : substr($abs, strlen(BASE_DIR) + 1);
}
