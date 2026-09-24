<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../config/db.php';

if (!isSuperUser()) { http_response_code(403); exit('Access denied.'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
verify_csrf();

function userFlash(string $type, string $message): never {
    $_SESSION['user_flash'] = ['type'=>$type, 'message'=>$message];
    header('Location: index.php');
    exit;
}

try {
    $action = (string)($_POST['action'] ?? 'create');
    $name = trim((string)($_POST['full_name'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $role = (string)($_POST['role'] ?? 'Staff');

    if ($name === '') throw new RuntimeException('Full name is required.');
    if (!in_array($role, ['Admin','Manager','Sales','Cashier','Storekeeper','Driver','Staff'], true)) {
        throw new RuntimeException('Invalid role.');
    }

    if ($action === 'create') {
        if (strlen($password) < 8) throw new RuntimeException('Password must be at least 8 characters.');

        $base = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '.', $name), '.'));
        if ($base === '') $base = 'user';
        $username = $base;
        $n = 2;
        $check = $conn->prepare("SELECT COUNT(*) FROM users WHERE username=?");
        while (true) {
            $check->execute([$username]);
            if ((int)$check->fetchColumn() === 0) break;
            $username = $base . $n;
            $n++;
        }

        $stmt = $conn->prepare("INSERT INTO users (full_name, username, email, phone, password, role, is_super, status) VALUES (?, ?, NULL, NULL, ?, ?, 0, 'Active')");
        $stmt->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), $role]);

        userFlash('success', "User created successfully. Username: {$username}");
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new RuntimeException('Invalid user.');

        $stmt = $conn->prepare("SELECT id, is_super FROM users WHERE id=?");
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        if (!$existing) throw new RuntimeException('User not found.');
        if ((int)$existing['is_super'] === 1) throw new RuntimeException('Super User accounts cannot be modified from this screen.');

        $status = (string)($_POST['status'] ?? 'Active');
        if (!in_array($status, ['Active','Inactive'], true)) throw new RuntimeException('Invalid status.');

        if ($password !== '') {
            if (strlen($password) < 8) throw new RuntimeException('Password must be at least 8 characters.');
            $stmt = $conn->prepare("UPDATE users SET full_name=?, role=?, status=?, password=? WHERE id=?");
            $stmt->execute([$name, $role, $status, password_hash($password, PASSWORD_DEFAULT), $id]);
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name=?, role=?, status=? WHERE id=?");
            $stmt->execute([$name, $role, $status, $id]);
        }

        userFlash('success', 'User details updated successfully.');
    }

    userFlash('error', 'Invalid action.');
} catch (Throwable $e) {
    userFlash('error', 'User management failed: ' . $e->getMessage());
}