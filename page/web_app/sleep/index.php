<?php
require __DIR__ . '/auth.php';

const LOGIN_MAX_FAILS = 5;
const LOGIN_LOCK_SECONDS = 600;

function throttle_file(): string
{
    return sys_get_temp_dir() . '/sleep_login_' . md5($_SERVER['REMOTE_ADDR'] ?? '') . '.json';
}

function login_fails(): array
{
    $d = json_decode((string) @file_get_contents(throttle_file()), true);
    if (!is_array($d) || time() - ($d['since'] ?? 0) > LOGIN_LOCK_SECONDS) {
        return ['count' => 0, 'since' => time()];
    }
    return $d;
}

$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'login') {
        if (!csrf_ok($_POST['csrf'] ?? null)) {
            $loginError = '頁面已過期，請再試一次。';
        } else {
            $fails = login_fails();
            if ($fails['count'] >= LOGIN_MAX_FAILS) {
                $loginError = '登入失敗次數過多，請 10 分鐘後再試。';
            } elseif (
                hash_equals($config['username'], (string) ($_POST['username'] ?? ''))
                && password_verify((string) ($_POST['password'] ?? ''), $config['password_hash'])
            ) {
                @unlink(throttle_file());
                session_regenerate_id(true);
                $_SESSION['user'] = $config['username'];
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $fails['count']++;
                @file_put_contents(throttle_file(), json_encode($fails), LOCK_EX);
                sleep(1);
                $loginError = '帳號或密碼錯誤。';
            }
        }
    } elseif ($postAction === 'logout' && csrf_ok($_POST['csrf'] ?? null)) {
        $_SESSION = [];
        session_destroy();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } elseif (is_logged_in() && csrf_ok($_POST['csrf'] ?? null)) {
        $actions = ['bed' => '躺床', 'sleep' => '睡眠', 'lazy' => '賴床', 'wake' => '清醒'];
        if (isset($actions[$postAction])) {
            add_action($actions[$postAction]);
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

$loggedIn = is_logged_in();
$last_action = '';
$current_action_key = '';

if ($loggedIn) {
    $last_record = get_last_record();
    $last_action = $last_record ? $last_record['action'] : '';
    $last_time = '';
    $elapsed = '';
    if ($last_record) {
        $dt = $last_record['recorded_at'];
        $y = substr($dt,0,4); $m = substr($dt,4,2); $d = substr($dt,6,2);
        $h = substr($dt,9,2); $i = substr($dt,11,2);
        $last_time = "$m/$d $h:$i";
        $ts = mktime((int)$h, (int)$i, (int)substr($dt,13,2), (int)$m, (int)$d, (int)$y);
        $diff = time() - $ts;
        if ($diff < 60) {
            $elapsed = '剛剛';
        } elseif ($diff < 3600) {
            $elapsed = floor($diff / 60) . ' 分鐘前';
        } else {
            $hrs = floor($diff / 3600);
            $mins = floor(($diff % 3600) / 60);
            $elapsed = $hrs . ' 小時 ' . $mins . ' 分鐘前';
        }
    }

    $status_color = ['清醒' => '#fff', '躺床' => '#27ae60', '睡眠' => '#8e44ad', '賴床' => '#27ae60'];
    $status_icon = ['清醒' => '&#9728;', '躺床' => '&#128716;', '睡眠' => '&#128164;', '賴床' => '&#128564;'];

    $action_map = ['bed' => '躺床', 'sleep' => '睡眠', 'lazy' => '賴床', 'wake' => '清醒'];
    foreach ($action_map as $key => $val) {
        if ($last_action === $val) {
            $current_action_key = $key;
            break;
        }
    }

    $enable = ['bed' => false, 'sleep' => false, 'lazy' => false, 'wake' => false];
    if ($last_action === '清醒') {
        $enable['bed'] = true;
        $enable['sleep'] = true;
    } elseif ($last_action === '躺床') {
        $enable['sleep'] = true;
        $enable['wake'] = true;
    } elseif ($last_action === '睡眠') {
        $enable['lazy'] = true;
        $enable['wake'] = true;
    } elseif ($last_action === '賴床') {
        $enable['sleep'] = true;
        $enable['wake'] = true;
    } else {
        $enable['bed'] = true;
        $enable['sleep'] = true;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>睡眠紀錄</title>
    <style>
        :root {
            --bg: #111; --panel: #1a1a1a; --border: #333; --text: #eee; --muted: #888;
            --accent: #c96442; --accent-text: #fff; --error: #e74c3c;
        }
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, "Segoe UI", "Noto Sans TC", "Microsoft JhengHei", sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        button, input { font: inherit; color: inherit; }

        /* login */
        .login { width: 100%; max-width: 340px; padding: 16px; }
        .login form { background: var(--panel); border: 1px solid var(--border); border-radius: 14px; padding: 28px; }
        .login h1 { margin: 0 0 18px; font-size: 22px; }
        .login label { display: block; font-size: 14px; color: var(--muted); margin: 12px 0 4px; }
        .login input { width: 100%; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 8px 10px; }
        .login input:focus { outline: 2px solid var(--accent); outline-offset: 1px; }
        .login button { width: 100%; margin-top: 20px; padding: 10px; background: var(--accent); color: var(--accent-text); border: none; border-radius: 8px; cursor: pointer; }
        .login .err { color: var(--error); font-size: 14px; margin-top: 12px; }

        /* app */
        .top-bar { display: flex; gap: 16px; align-items: center; margin-bottom: 24px; }
        .top-bar a { color: var(--accent); text-decoration: none; font-size: 1.1rem; }
        .top-bar a:hover { text-decoration: underline; }
        .top-bar form { margin: 0; }
        .top-bar button { background: none; border: 1px solid var(--border); color: var(--muted); border-radius: 8px; padding: 6px 14px; font-size: 0.9rem; cursor: pointer; }
        .top-bar button:hover { border-color: var(--muted); color: var(--text); }

        form.actions { width: 100%; max-width: 400px; }
        .btn {
            width: 100%;
            padding: 40px 0;
            margin: 20px 0;
            font-size: 2.5rem;
            border: none;
            border-radius: 16px;
            color: #fff;
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            -webkit-user-select: none;
            user-select: none;
            -webkit-touch-callout: none;
        }
        .btn .fill {
            position: absolute;
            top: 0; left: 0; bottom: 0;
            width: 0;
            background: rgba(255,255,255,0.25);
            border-radius: 16px;
            transition: none;
            pointer-events: none;
        }
        .btn.holding .fill {
            width: 100%;
            transition: width 1.5s linear;
        }
        .btn .btn-label {
            position: relative;
            z-index: 1;
            pointer-events: none;
        }
        .btn-hint {
            text-align: center;
            color: var(--muted);
            font-size: 0.85rem;
            margin-top: -12px;
            margin-bottom: 8px;
        }
        .btn-bed { background: #27ae60; }
        .btn-sleep { background: #8e44ad; }
        .btn-lazy { background: #27ae60; }
        .btn-wake { background: #e74c3c; }
        .active-btn { border: 4px solid var(--error) !important; box-shadow: 0 0 12px rgba(231,76,60,0.3); }

        .status-card {
            width: 100%; max-width: 400px; margin-bottom: 20px;
            background: var(--panel); border: 1px solid var(--border); border-radius: 14px;
            padding: 20px 24px; text-align: center;
        }
        .status-icon { font-size: 2.5rem; line-height: 1; }
        .status-label { font-size: 1.6rem; font-weight: 600; margin-top: 8px; }
        .status-time { color: var(--muted); font-size: 0.95rem; margin-top: 8px; }



    </style>
</head>
<body>
<?php if (!$loggedIn): ?>
<main class="login">
    <form method="post" autocomplete="on">
        <h1>睡眠紀錄</h1>
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
        <label for="username">帳號</label>
        <input type="text" id="username" name="username" autocomplete="username" required autofocus>
        <label for="password">密碼</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
        <button type="submit">登入</button>
        <?php if ($loginError !== ''): ?><div class="err"><?= h($loginError) ?></div><?php endif; ?>
    </form>
</main>
<?php else: ?>
    <div class="top-bar">
        <a href="sleep_view.php">睡眠狀態一覽</a>
        <a href="admin.php">管理</a>
        <form method="post">
            <input type="hidden" name="action" value="logout">
            <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
            <button type="submit">登出</button>
        </form>
    </div>
    <div class="status-card">
        <div class="status-icon"><?= $last_action ? $status_icon[$last_action] : '&#9728;' ?></div>
        <div class="status-label" style="color: <?= h($status_color[$last_action] ?? '#fff') ?>">
            目前狀態：<?= h($last_action ?: '無紀錄') ?>
        </div>
        <?php if ($last_time): ?>
        <div class="status-time">自 <?= h($last_time) ?>（<?= h($elapsed) ?>）</div>
        <?php endif; ?>
    </div>
    <form class="actions" method="post">
        <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
        <?php if($enable['bed']): ?><button class="btn btn-bed" type="button" data-action="bed"><span class="fill"></span><span class="btn-label">躺　床</span></button><?php endif; ?>
        <?php if($enable['sleep']): ?><button class="btn btn-sleep" type="button" data-action="sleep"><span class="fill"></span><span class="btn-label">睡　眠</span></button><?php endif; ?>
        <?php if($enable['lazy']): ?><button class="btn btn-lazy" type="button" data-action="lazy"><span class="fill"></span><span class="btn-label">賴　床</span></button><?php endif; ?>
        <?php if($enable['wake']): ?><button class="btn btn-wake" type="button" data-action="wake"><span class="fill"></span><span class="btn-label">清　醒</span></button><?php endif; ?>
        <div class="btn-hint">長按 1.5 秒以打卡</div>
    </form>
<script>
(function() {
    var HOLD_MS = 1500;
    var form = document.querySelector('form.actions');
    if (!form) return;
    var timer = null;
    var active = null;

    function start(btn) {
        if (active) return;
        active = btn;
        btn.classList.add('holding');
        timer = setTimeout(function() {
            btn.classList.remove('holding');
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'action';
            input.value = btn.dataset.action;
            form.appendChild(input);
            form.submit();
        }, HOLD_MS);
    }

    function cancel() {
        if (timer) { clearTimeout(timer); timer = null; }
        if (active) { active.classList.remove('holding'); active = null; }
    }

    form.querySelectorAll('.btn').forEach(function(btn) {
        btn.addEventListener('mousedown', function(e) { if (e.button === 0) start(btn); });
        btn.addEventListener('touchstart', function(e) { e.preventDefault(); start(btn); }, {passive: false});
    });

    document.addEventListener('mouseup', cancel);
    document.addEventListener('mouseleave', cancel);
    document.addEventListener('touchend', cancel);
    document.addEventListener('touchcancel', cancel);
    window.addEventListener('contextmenu', function(e) {
        if (active) e.preventDefault();
    });
})();
</script>
<?php endif; ?>
</body>
</html>
