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
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ============================================================================
           Authentication Pages - Auth Styles
           ============================================================================ */

        .auth-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #004a99 0%, #002d5c 50%, #315c2b 100%);
            padding: var(--space-lg);
            overflow-y: auto;
        }

        .auth-shell {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-2xl);
            max-width: 1200px;
            width: 100%;
            align-items: center;
        }

        /* ============================================================================
           Auth Intro Section
           ============================================================================ */
        .auth-intro {
            color: #ffffff;
            display: flex;
            flex-direction: column;
            gap: var(--space-xl);
        }

        .brand-block {
            display: flex;
            align-items: center;
            gap: var(--space-lg);
            margin-bottom: var(--space-lg);
        }

        .brand-mark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            background: rgba(255, 255, 255, 0.15);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: var(--radius-lg);
            font-size: 1.5rem;
            font-weight: var(--font-weight-extrabold);
            color: #00d4ff;
            backdrop-filter: blur(10px);
        }

        .brand-block h1 {
            color: #ffffff;
            margin: 0;
            font-size: 2rem;
            font-weight: var(--font-weight-extrabold);
        }

        .brand-block .eyebrow {
            opacity: 0.8;
            font-size: 0.875rem;
            font-weight: var(--font-weight-semibold);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0;
        }

        .auth-intro .eyebrow {
            opacity: 0.8;
            font-size: 0.875rem;
            font-weight: var(--font-weight-semibold);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0;
        }

        .auth-intro h2 {
            color: #ffffff;
            font-size: 1.5rem;
            margin: 0;
            line-height: 1.4;
        }

        .auth-intro p {
            opacity: 0.9;
            font-size: 1rem;
            line-height: 1.6;
            margin: 0;
        }

        /* ============================================================================
           Auth Login Card
           ============================================================================ */
        .login-card {
            background: var(--panel);
            border-radius: var(--radius-2xl);
            padding: var(--space-2xl);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
            display: flex;
            flex-direction: column;
            gap: var(--space-lg);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .login-card .eyebrow {
            color: var(--muted);
            font-size: 0.875rem;
            font-weight: var(--font-weight-semibold);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0;
        }

        .login-card h2 {
            color: var(--ink);
            font-size: 1.5rem;
            margin: 0;
        }

        .login-card form {
            display: flex;
            flex-direction: column;
            gap: var(--space-lg);
        }

        .login-card label {
            display: block;
            font-weight: var(--font-weight-semibold);
            color: var(--ink);
            font-size: 0.95rem;
            margin-bottom: var(--space-sm);
        }

        .login-card input[type="text"],
        .login-card input[type="password"],
        .login-card input[type="email"] {
            width: 100%;
            padding: var(--space-md) var(--space-lg);
            border: 2px solid var(--border);
            border-radius: var(--radius-lg);
            font-size: 1rem;
            font-family: inherit;
            background: var(--bg-light);
            color: var(--ink);
            transition: all var(--transition-base);
        }

        .login-card input[type="text"]:focus,
        .login-card input[type="password"]:focus,
        .login-card input[type="email"]:focus {
            outline: none;
            border-color: #004a99;
            background: var(--panel);
            box-shadow: 0 0 0 3px rgba(0, 74, 153, 0.1);
        }

        .login-card input::placeholder {
            color: var(--muted-light);
        }

        .login-card input:disabled {
            background: var(--bg);
            color: var(--muted);
            cursor: not-allowed;
        }

        /* ============================================================================
           Auth Buttons
           ============================================================================ */
        .auth-submit {
            padding: var(--space-md) var(--space-lg);
            background: linear-gradient(135deg, #004a99, #075fc2);
            color: #ffffff;
            border: none;
            border-radius: var(--radius-lg);
            font-size: 1rem;
            font-weight: var(--font-weight-semibold);
            cursor: pointer;
            transition: all var(--transition-base);
            text-transform: none;
            letter-spacing: 0;
            min-height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: var(--space-md);
        }

        .auth-submit:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(0, 74, 153, 0.3);
        }

        .auth-submit:active:not(:disabled) {
            transform: translateY(0);
        }

        .auth-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .auth-submit:focus-visible {
            outline: 2px solid #00d4ff;
            outline-offset: 2px;
        }

        /* ============================================================================
           Form Alerts
           ============================================================================ */
        .form-alert {
            padding: var(--space-md) var(--space-lg);
            border-radius: var(--radius-lg);
            font-size: 0.95rem;
            line-height: 1.5;
            border-left: 4px solid #dc2626;
            background: rgba(220, 38, 38, 0.05);
            color: #991b1b;
        }

        .form-alert.warning {
            border-left-color: #b45309;
            background: rgba(180, 83, 9, 0.05);
            color: #78350f;
        }

        .form-alert p {
            margin: 0;
        }

        .form-alert p:not(:last-child) {
            margin-bottom: var(--space-sm);
        }

        /* ============================================================================
           Auth Help & Support Text
           ============================================================================ */
        .auth-help {
            text-align: center;
            color: var(--muted);
            font-size: 0.9rem;
            margin: var(--space-md) 0 0;
            padding-top: var(--space-md);
            border-top: 1px solid var(--border-light);
        }

        /* ============================================================================
           Responsive Design - Tablet
           ============================================================================ */
        @media (max-width: 1024px) {
            .auth-shell {
                grid-template-columns: 1fr;
                gap: var(--space-xl);
            }

            .auth-intro {
                text-align: center;
                margin-bottom: var(--space-lg);
            }

            .brand-block {
                justify-content: center;
                margin-bottom: 0;
            }
        }

        /* ============================================================================
           Responsive Design - Mobile
           ============================================================================ */
        @media (max-width: 640px) {
            .auth-page {
                padding: var(--space-md);
                min-height: auto;
            }

            .auth-shell {
                grid-template-columns: 1fr;
                gap: var(--space-lg);
                max-width: 100%;
            }

            .login-card {
                padding: var(--space-lg);
                gap: var(--space-md);
            }

            .login-card h2 {
                font-size: 1.25rem;
            }

            .auth-intro {
                display: none;
            }

            .brand-block {
                display: none;
            }

            .login-card label {
                font-size: 0.9rem;
            }

            .login-card input[type="text"],
            .login-card input[type="password"],
            .login-card input[type="email"] {
                padding: var(--space-md);
                font-size: 16px;
            }

            .auth-submit {
                min-height: 44px;
                padding: var(--space-md);
            }

            .form-alert {
                padding: var(--space-md);
                font-size: 0.9rem;
            }

            .auth-help {
                font-size: 0.85rem;
            }
        }

        /* ============================================================================
           Loading & Transitions
           ============================================================================ */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-card {
            animation: fadeIn 0.4s ease-out;
        }

        /* ============================================================================
           High contrast mode support
           ============================================================================ */
        @media (prefers-contrast: more) {
            .login-card input[type="text"],
            .login-card input[type="password"],
            .login-card input[type="email"] {
                border-width: 3px;
            }

            .form-alert {
                border-left-width: 6px;
            }
        }

        /* ============================================================================
           Reduced motion support
           ============================================================================ */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }

            .auth-submit:hover:not(:disabled) {
                transform: none;
            }
        }
    </style>
</head>
<body class="auth-page">
    <main class="auth-shell" aria-labelledby="login-title">
        <!-- Left Section: Intro -->
        <section class="auth-intro" aria-label="Stalk and Stable overview">
            <div class="brand-block">
                <span class="brand-mark" aria-hidden="true">S&amp;S</span>
                <div>
                    <p class="eyebrow">Inventory Suite</p>
                    <h1>Stalk &amp; Stable</h1>
                </div>
            </div>
            <div>
                <p class="eyebrow">Secure workspace</p>
                <h2>Manage inventory, sales, and stock alerts from one dashboard.</h2>
                <p>Sign in with your assigned staff account to access products, POS, customers, suppliers, expenses, and reports.</p>
            </div>
        </section>

        <!-- Right Section: Login Form -->
        <section class="login-card">
            <p class="eyebrow">Welcome back</p>
            <h2 id="login-title">Sign in to your account</h2>

            <?php if ($notice !== ''): ?>
                <div class="form-alert warning" role="status">
                    <p><?php echo e($notice); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($errors !== []): ?>
                <div class="form-alert" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo e($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="login.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">

                <div class="form-group">
                    <label for="login">Email or username</label>
                    <input
                        id="login"
                        name="login"
                        type="text"
                        value="<?php echo e($loginValue); ?>"
                        placeholder="you@example.com or username"
                        autocomplete="username"
                        required
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <button class="auth-submit" type="submit" <?php echo $db === null || login_is_locked() ? 'disabled' : ''; ?>>
                    Sign in
                </button>
            </form>

            <p class="auth-help">
                <?php if (login_is_locked()): ?>
                    🔒 This browser is temporarily locked after repeated failed attempts. Please try again in 15 minutes.
                <?php else: ?>
                    <?php echo e((string) $remainingAttempts); ?> sign-in attempts remaining before a temporary lockout.
                <?php endif; ?>
            </p>
        </section>
    </main>
</body>
</html>
