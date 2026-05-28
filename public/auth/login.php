<?php
declare(strict_types=1);

$secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secureCookie,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

require_once __DIR__ . '/../config/db.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; img-src 'self' data:; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");

const MAX_LOGIN_ATTEMPTS = 5;
const LOGIN_LOCKOUT_SECONDS = 900;

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_db(): ?mysqli
{
    return isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli ? $GLOBALS['conn'] : null;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function table_exists(mysqli $db, string $table): bool
{
    $statement = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    if (!$statement) {
        return false;
    }

    $statement->bind_param('s', $table);
    $statement->execute();
    $statement->bind_result($count);
    $statement->fetch();
    $statement->close();

    return (int) $count > 0;
}

function table_columns(mysqli $db, string $table): array
{
    $statement = $db->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?');
    if (!$statement) {
        return [];
    }

    $statement->bind_param('s', $table);
    $statement->execute();
    $result = $statement->get_result();
    $columns = [];

    while ($row = $result->fetch_assoc()) {
        $columns[] = (string) $row['column_name'];
    }

    $statement->close();

    return $columns;
}

function first_matching_column(array $columns, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $columns, true)) {
            return $candidate;
        }
    }

    return null;
}

function quote_identifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function verify_user(mysqli $db, string $login, string $password): ?array
{
    if (!table_exists($db, 'users')) {
        error_log('Login failed because the users table does not exist.');
        return null;
    }

    $columns = table_columns($db, 'users');
    $idColumn = first_matching_column($columns, ['id', 'user_id']);
    $nameColumn = first_matching_column($columns, ['full_name', 'name', 'display_name']);
    $roleColumn = first_matching_column($columns, ['role', 'user_role']);
    $emailColumn = first_matching_column($columns, ['email', 'email_address']);
    $usernameColumn = first_matching_column($columns, ['username', 'user_name']);
    $passwordColumn = first_matching_column($columns, ['password_hash', 'password']);
    $statusColumn = first_matching_column($columns, ['status', 'is_active', 'active']);

    if ($idColumn === null || $passwordColumn === null || ($emailColumn === null && $usernameColumn === null)) {
        error_log('Login failed because the users table is missing required columns.');
        return null;
    }

    $selectColumns = array_unique(array_filter([$idColumn, $nameColumn, $roleColumn, $emailColumn, $usernameColumn, $passwordColumn, $statusColumn]));
    $where = [];
    $types = '';
    $params = [];

    foreach (array_filter([$emailColumn, $usernameColumn]) as $column) {
        $where[] = quote_identifier($column) . ' = ?';
        $types .= 's';
        $params[] = $login;
    }

    $sql = 'SELECT ' . implode(', ', array_map('quote_identifier', $selectColumns)) . ' FROM `users` WHERE (' . implode(' OR ', $where) . ') LIMIT 1';
    $statement = $db->prepare($sql);
    if (!$statement) {
        return null;
    }

    $statement->bind_param($types, ...$params);
    $statement->execute();
    $result = $statement->get_result();
    $user = $result->fetch_assoc();
    $statement->close();

    if (!$user || !password_verify($password, (string) $user[$passwordColumn])) {
        return null;
    }

    if ($statusColumn !== null) {
        $status = strtolower((string) $user[$statusColumn]);
        if (in_array($status, ['0', 'false', 'inactive', 'disabled', 'suspended'], true)) {
            return null;
        }
    }

    if (password_needs_rehash((string) $user[$passwordColumn], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $updateSql = 'UPDATE `users` SET ' . quote_identifier($passwordColumn) . ' = ? WHERE ' . quote_identifier($idColumn) . ' = ?';
        $update = $db->prepare($updateSql);
        if ($update) {
            $userId = (string) $user[$idColumn];
            $update->bind_param('ss', $newHash, $userId);
            $update->execute();
            $update->close();
        }
    }

    return [
        'id' => $user[$idColumn],
        'name' => $nameColumn !== null ? ($user[$nameColumn] ?: $login) : $login,
        'role' => $roleColumn !== null ? ($user[$roleColumn] ?: 'User') : 'User',
    ];
}

function login_is_locked(): bool
{
    $attempts = (int) ($_SESSION['login_attempts'] ?? 0);
    $lastAttempt = (int) ($_SESSION['last_login_attempt'] ?? 0);

    if ($attempts < MAX_LOGIN_ATTEMPTS) {
        return false;
    }

    if ((time() - $lastAttempt) >= LOGIN_LOCKOUT_SECONDS) {
        unset($_SESSION['login_attempts'], $_SESSION['last_login_attempt']);
        return false;
    }

    return true;
}

if (!empty($_SESSION['user_id'])) {
    header('Location: ../index.php', true, 302);
    exit();
}

$db = app_db();
$errors = [];
$notice = $db === null ? 'Database connection is unavailable. Please try again later.' : '';
$loginValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginValue = trim((string) ($_POST['login'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals(csrf_token(), $submittedToken)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } elseif (login_is_locked()) {
        $errors[] = 'Too many failed attempts. Please wait 15 minutes before trying again.';
    } elseif ($loginValue === '' || $password === '') {
        $errors[] = 'Enter both your username/email and password.';
    } elseif ($db === null) {
        $errors[] = 'Sign in is temporarily unavailable. Please contact your administrator.';
    } else {
        $user = verify_user($db, $loginValue, $password);

        if ($user === null) {
            $_SESSION['login_attempts'] = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
            $_SESSION['last_login_attempt'] = time();
            $errors[] = 'Invalid username/email or password.';
        } else {
            session_regenerate_id(true);
            unset($_SESSION['login_attempts'], $_SESSION['last_login_attempt']);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header('Location: ../index.php', true, 302);
            exit();
        }
    }
}

$remainingAttempts = max(0, MAX_LOGIN_ATTEMPTS - (int) ($_SESSION['login_attempts'] ?? 0));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sign in to Stalk & Stable - Alcohol distributor management system">
    <meta name="theme-color" content="#004a99">
    <title>Sign in | Stalk &amp; Stable</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #004a99;
            --primary-dark: #002d5c;
            --accent-color: #00d4ff;
            --accent-glow: rgba(0, 212, 255, 0.42);
            --brand: #315c2b;
            --brand-dark: #203d1d;
            --brand-light: #4a7d3f;
            --danger: #dc2626;
            --warning: #b45309;
            --soft-white: #f8fbff;
            --border: #dbe7f3;
            --muted: #64748b;
            --muted-light: #94a3b8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--primary-dark);
            background-image: radial-gradient(circle at 50% 50%, #2c3e50 0%, #1a2a3a 100%);
            font-family: 'Segoe UI', 'Inter', Roboto, -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
        }

        .login-box {
            background: #ffffff;
            display: flex;
            width: 1050px;
            max-width: 100%;
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.4);
            animation: slideIn 0.6s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Brand Side */
        .login-brand {
            background: linear-gradient(135deg, var(--primary-color), #0075d9);
            width: 50%;
            padding: 70px 40px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .logo-placeholder {
            background: white;
            padding: 35px;
            border-radius: 50%;
            margin-bottom: 30px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            animation: fadeInDown 0.8s ease-out;
            width: 160px;
            height: 160px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-img {
            width: 140px;
            height: auto;
        }

        .brand-name {
            font-size: 3rem;
            letter-spacing: 3px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .brand-sub {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 300;
        }

        .brand-divider {
            border-top: 2px solid rgba(255, 255, 255, 0.2);
            width: 60%;
            margin: 25px 0;
        }

        .brand-tagline {
            font-size: 1.2rem;
            font-style: italic;
            font-weight: 300;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Form Side */
        .login-form-section {
            width: 50%;
            padding: 60px;
            background-color: var(--soft-white);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header {
            margin-bottom: 30px;
        }

        .form-header h2 {
            font-size: 1.75rem;
            color: var(--primary-dark);
            font-weight: 700;
            margin-bottom: 8px;
        }

        .form-header p {
            color: var(--muted);
            font-size: 0.95rem;
        }

        .alert {
            border-radius: 15px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-danger {
            background: rgba(220, 38, 38, 0.05);
            border: 1px solid rgba(220, 38, 38, 0.2);
            color: #991b1b;
        }

        .alert-warning {
            background: rgba(180, 83, 9, 0.05);
            border: 1px solid rgba(180, 83, 9, 0.2);
            color: #78350f;
        }

        .alert i {
            font-size: 1.1rem;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--muted);
            margin-bottom: 8px;
        }

        .input-group {
            position: relative;
            display: flex;
        }

        .input-group-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            pointer-events: none;
            z-index: 2;
        }

        .form-control {
            width: 100%;
            background: #ffffff;
            border: 1px solid var(--border);
            padding: 16px 18px 16px 48px;
            border-radius: 15px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control::placeholder {
            color: var(--muted-light);
        }

        .form-control:focus {
            outline: none;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(0, 74, 153, 0.15);
            border-color: var(--primary-color);
        }

        .form-control:disabled {
            background: #f0f4f8;
            color: var(--muted);
            cursor: not-allowed;
        }

        .password-toggle {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--muted);
            cursor: pointer;
            font-size: 1rem;
            padding: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 3;
            transition: color 0.2s;
        }

        .password-toggle:hover {
            color: var(--primary-color);
        }

        #password {
            padding-right: 48px;
        }

        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, var(--primary-color), #0075d9);
            border: none;
            border-radius: 15px;
            padding: 18px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: white;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
            box-shadow: 0 10px 20px rgba(0, 74, 153, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-login:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--primary-dark), #004a99);
            transform: translateY(-2px);
            box-shadow: 0 15px 25px rgba(0, 74, 153, 0.3);
        }

        .btn-login:active:not(:disabled) {
            transform: translateY(0);
        }

        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-login:focus-visible {
            outline: 2px solid var(--accent-color);
            outline-offset: 2px;
        }

        .form-footer {
            margin-top: 32px;
            text-align: center;
            border-top: 1px solid var(--border);
            padding-top: 20px;
        }

        .auth-help {
            color: var(--muted);
            font-size: 0.9rem;
            margin: 0;
        }

        .copyright {
            color: var(--muted);
            font-size: 0.85rem;
            margin-top: 16px;
        }

        .copyright span {
            display: block;
            opacity: 0.7;
            font-size: 0.78rem;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .login-box {
                flex-direction: column;
                width: 100%;
                max-width: 500px;
            }

            .login-brand,
            .login-form-section {
                width: 100%;
            }

            .login-brand {
                padding: 50px 40px;
            }

            .logo-placeholder {
                width: 120px;
                height: 120px;
                padding: 25px;
                margin-bottom: 20px;
            }

            .logo-img {
                width: 100px;
            }

            .brand-name {
                font-size: 2.2rem;
            }

            .login-form-section {
                padding: 40px;
            }
        }

        @media (max-width: 640px) {
            body {
                padding: 16px;
            }

            .login-box {
                border-radius: 20px;
                box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
            }

            .login-brand {
                display: none;
            }

            .login-form-section {
                width: 100%;
                padding: 32px 24px;
            }

            .form-header h2 {
                font-size: 1.5rem;
            }

            .form-control {
                padding: 14px 16px 14px 44px;
                font-size: 16px;
            }

            .btn-login {
                padding: 16px;
                margin-top: 16px;
            }

            .form-group {
                margin-bottom: 20px;
            }

            .alert {
                padding: 14px 16px;
            }

            .form-footer {
                margin-top: 24px;
                padding-top: 16px;
            }

            .copyright {
                font-size: 0.8rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }

            .btn-login:hover:not(:disabled) {
                transform: none;
            }
        }

        @media (prefers-contrast: more) {
            .form-control {
                border-width: 2px;
            }

            .alert {
                border-width: 2px;
            }
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-box">
        <!-- Brand Side -->
        <div class="login-brand">
            <div class="logo-placeholder">
                <img src="../assets/img/logo.png" alt="Stalk & Stable Logo" class="logo-img" onerror="this.style.display='none'">
            </div>
            <h1 class="brand-name">STALK &amp; STABLE</h1>
            <p class="brand-sub">Distributor Management System</p>
            <div class="brand-divider"></div>
            <p class="brand-tagline">
                Premium Inventory <br> Management Solution
            </p>
        </div>

        <!-- Form Side -->
        <div class="login-form-section">
            <div class="form-header">
                <h2>System Login</h2>
                <p>Enter your credentials to access the portal.</p>
            </div>

            <?php if ($notice !== ''): ?>
                <div class="alert alert-warning" role="status">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo e($notice); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($errors !== []): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-circle-exclamation"></i>
                    <div>
                        <?php foreach ($errors as $error): ?>
                            <p style="margin: 0;"><?php echo e($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form method="post" action="login.php" novalidate autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">

                <div class="form-group">
                    <label for="login" class="form-label">Staff Username or Email</label>
                    <div class="input-group">
                        <i class="fas fa-user-shield input-group-icon"></i>
                        <input
                            id="login"
                            name="login"
                            type="text"
                            class="form-control"
                            value="<?php echo e($loginValue); ?>"
                            placeholder="e.g. b.wambua or staff@stalk.local"
                            autocomplete="username"
                            required
                            autofocus
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Secure Password</label>
                    <div class="input-group" style="position: relative;">
                        <i class="fas fa-key input-group-icon"></i>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            class="form-control"
                            placeholder="••••••••"
                            autocomplete="current-password"
                            required
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword()" aria-label="Toggle password visibility">
                            <i class="fas fa-eye" id="eye_icon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login" <?php echo $db === null || login_is_locked() ? 'disabled' : ''; ?>>
                    <i class="fas fa-sign-in-alt"></i> ACCESS SYSTEM
                </button>
            </form>

            <div class="form-footer">
                <p class="auth-help">
                    <?php if (login_is_locked()): ?>
                        <i class="fas fa-lock"></i> This browser is temporarily locked. Please wait 15 minutes before trying again.
                    <?php else: ?>
                        <?php echo e((string) $remainingAttempts); ?> login attempt(s) remaining before temporary lockout.
                    <?php endif; ?>
                </p>
                <p class="copyright">
                    &copy; 2026 Stalk &amp; Stable
                    <span>Secure Management Environment</span>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const passInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eye_icon');
    
    if (passInput.type === 'password') {
        passInput.type = 'text';
        eyeIcon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        passInput.type = 'password';
        eyeIcon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

// Prevent form resubmission
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>

</body>
</html>
