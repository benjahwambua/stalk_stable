<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$error = '';
$loginValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginValue = trim((string)($_POST['login'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($loginValue === '' || $password === '') {
        $error = 'Please enter your username/email and password.';
    } else {
        $stmt = $conn->prepare(
            "SELECT id, full_name, username, email, password, role, is_super, status
             FROM users
             WHERE (username = :login OR email = :login)
             LIMIT 1"
        );
        $stmt->execute([':login' => $loginValue]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, (string)$user['password'])) {
            $error = 'Invalid username/email or password.';
        } elseif (strcasecmp((string)$user['status'], 'Active') !== 0) {
            $error = 'Your account is inactive. Please contact the administrator.';
        } else {
            session_regenerate_id(true);

            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['is_super'] = (int)$user['is_super'];

            $update = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
            $update->execute([':id' => $user['id']]);

            header('Location: ' . BASE_URL . '/index.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Login | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        body{margin:0;min-height:100vh;background:linear-gradient(135deg,#002d5c,#004a99);font-family:Inter,Segoe UI,Arial,sans-serif;display:flex;align-items:center;justify-content:center;padding:20px}
        .login-card{width:100%;max-width:430px;background:#fff;border-radius:22px;padding:42px;box-shadow:0 25px 70px rgba(0,0,0,.25)}
        .login-brand{text-align:center;margin-bottom:30px}.login-logo{font-size:48px}.login-brand h1{margin:8px 0 4px;color:#004a99}.login-brand p{margin:0;color:#64748b}
        .login-card label{display:block;font-size:13px;font-weight:700;color:#334155;margin:18px 0 7px}
        .login-card input{width:100%;padding:14px 15px;border:1px solid #dbe3ec;border-radius:10px;box-sizing:border-box;font-size:15px}
        .login-card input:focus{outline:none;border-color:#004a99;box-shadow:0 0 0 3px rgba(0,74,153,.12)}
        .login-btn{width:100%;margin-top:24px;border:0;border-radius:10px;padding:15px;background:#004a99;color:#fff;font-weight:700;font-size:15px;cursor:pointer}
        .login-btn:hover{background:#003b7a}.alert{padding:13px 15px;border-radius:10px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;margin-bottom:20px;font-size:14px}
        .login-footer{text-align:center;color:#94a3b8;font-size:12px;margin-top:25px}
    </style>
</head>
<body>
<div class="login-card">
    <div class="login-brand">
        <div class="login-logo">🍾</div>
        <h1>Stalk &amp; Stable</h1>
        <p>Alcohol Distribution Management System</p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="on">
        <label for="login">Username or Email</label>
        <input id="login" name="login" type="text" value="<?= e($loginValue) ?>" required autofocus>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>

        <button class="login-btn" type="submit">
            <i class="fas fa-sign-in-alt"></i> Sign In
        </button>
    </form>

    <div class="login-footer">&copy; <?= date('Y') ?> Stalk &amp; Stable. All rights reserved.</div>
</div>
</body>
</html>
