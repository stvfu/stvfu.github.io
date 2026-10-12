<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
require '/var/www/admin_credentials.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = (string)($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $error = 'Session expired, please try again.';
    } elseif ($username === ADMIN_USER && $password === ADMIN_PASS) {
        session_regenerate_id(true);
        $_SESSION['loggedin'] = true;
        header('Location: index.php');
        exit;
    } else {
        sleep(1);
        $error = 'Invalid username or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <style>
        body {
            margin: 0;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #272822;
            color: #f8f8f2;
            font-family: sans-serif;
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 260px;
        }
        input, button {
            padding: 8px;
            font-size: 14px;
        }
        .error {
            color: #f92672;
            margin: 0;
        }
    </style>
</head>
<body>
    <form method="post" action="">
        <h2>Login</h2>
        <?php if ($error !== null): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="text" name="username" placeholder="Username" autocomplete="username" required autofocus>
        <input type="password" name="password" placeholder="Password" autocomplete="current-password" required>
        <button type="submit">Login</button>
    </form>
</body>
</html>
