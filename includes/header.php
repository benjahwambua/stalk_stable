<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($pageTitle ?? APP_NAME) ?> | Stalk &amp; Stable</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{--sidebar-blue:#004a99;--header-height:75px;--sidebar-width:260px}
        body{margin:0;font-family:Inter,Segoe UI,Arial,sans-serif;background:#f0f4f8;color:#1e293b}
        .top-header{height:var(--header-height);background:var(--sidebar-blue);display:flex;align-items:center;justify-content:space-between;padding:0 28px;position:fixed;top:0;left:0;right:0;z-index:1001;box-shadow:0 4px 12px rgba(0,0,0,.15)}
        .header-left-group,.header-right,.user-pill{display:flex;align-items:center}.header-left-group{gap:15px}
        .logo-container{background:#fff;border-radius:9px;padding:7px 11px;font-size:25px;line-height:1}
        .header-title{margin:0;color:#fff;font-size:1.35rem;font-weight:800}.subtitle{color:#dcefff;font-size:.78rem;margin-top:2px}
        .header-right{gap:14px}.user-pill{padding:7px 13px;border-radius:30px;background:rgba(255,255,255,.1);color:#fff}
        .user-avatar-circle{width:28px;height:28px;border-radius:50%;background:#00d4ff;color:#004a99;display:flex;align-items:center;justify-content:center;font-size:12px}
        .user-name{margin-left:8px;font-size:13px;font-weight:600}.btn-logout{background:#fff;color:#004a99;text-decoration:none;padding:9px 14px;border-radius:7px;font-weight:800;font-size:12px}
        .layout{margin-top:var(--header-height);display:flex;min-height:calc(100vh - var(--header-height))}
        .content{margin-left:var(--sidebar-width);flex:1;min-width:0;padding:30px}
        @media(max-width:768px){.header-title-wrapper{display:none}.top-header{padding:0 15px}.user-name{display:none}.content{margin-left:0;padding:20px}.sidebar-toggle{display:block}}
    </style>
</head>
<body>
<header class="top-header">
    <div class="header-left-group">
        <div class="logo-container">🍾</div>
        <div class="header-title-wrapper">
            <h1 class="header-title">Stalk &amp; Stable</h1>
            <div class="subtitle">Alcohol Distribution Management System</div>
        </div>
    </div>
    <?php if (!empty($_SESSION['user_id'])): ?>
    <div class="header-right">
        <div class="user-pill">
            <div class="user-avatar-circle"><i class="fas fa-user"></i></div>
            <span class="user-name">Hi, <?= e($_SESSION['full_name'] ?? 'User') ?></span>
        </div>
        <a class="btn-logout" href="<?= BASE_URL ?>/auth/logout.php"><i class="fas fa-power-off"></i> Logout</a>
    </div>
    <?php endif; ?>
</header>
<div class="layout">
