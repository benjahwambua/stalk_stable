<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';

function navActive(string $needle): string
{
    global $currentPath;
    return strpos($currentPath, $needle) !== false ? 'active' : '';
}

$isSuperUser = (int)($_SESSION['is_super'] ?? 0) === 1;
$fullName = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'Staff';
?>
<style>
.sidebar{width:260px;background:#004a99;color:#e0f2ff;position:fixed;left:0;top:75px;height:calc(100vh - 75px);overflow-y:auto;z-index:1000;box-shadow:4px 0 15px rgba(0,0,0,.15)}
.sidebar::-webkit-scrollbar{width:6px}.sidebar::-webkit-scrollbar-thumb{background:rgba(255,255,255,.2);border-radius:10px}
.sidebar-brand{padding:19px;text-align:center;font-weight:800;letter-spacing:1.5px;border-bottom:1px solid rgba(255,255,255,.1);color:#fff}
.user-profile{padding:18px;display:flex;align-items:center;background:rgba(255,255,255,.05);border-bottom:1px solid rgba(255,255,255,.1)}
.user-avatar{width:40px;height:40px;border-radius:50%;background:#00d4ff;color:#004a99;display:flex;align-items:center;justify-content:center;font-weight:800;margin-right:11px}.user-info .name{display:block;color:#fff;font-size:13px;font-weight:700}.user-info .role{font-size:11px;opacity:.8}
.sidebar nav{padding:16px 0 30px}.sidebar nav a{margin:3px 12px;padding:11px 15px;border-radius:8px;display:flex;align-items:center;color:#e0f2ff;text-decoration:none;font-size:14px}.sidebar nav a:hover{background:#005bc1;color:#fff}.sidebar nav a.active{background:#fff;color:#004a99;font-weight:700}.icon-main{width:25px;margin-right:10px;text-align:center}
.menu-title{padding:19px 24px 7px;color:#00d4ff;font-size:10px;font-weight:800;letter-spacing:2px}.logout-link{margin-top:20px!important;background:rgba(255,77,77,.1);color:#ffb0b0!important}
@media(max-width:768px){.sidebar{left:-260px;transition:left .25s}.sidebar.active{left:0}}
</style>

<aside class="sidebar">
    <div class="sidebar-brand">🍾 STALK &amp; STABLE</div>
    <div class="user-profile">
        <div class="user-avatar"><?= e(strtoupper(substr($fullName,0,1))) ?></div>
        <div class="user-info"><span class="name"><?= e($fullName) ?></span><span class="role"><?= e($role) ?></span></div>
    </div>
    <nav>
        <a href="<?= BASE_URL ?>/index.php" class="<?= navActive('/index.php') ?>"><i class="fas fa-th-large icon-main"></i>Dashboard</a>

        <?php if (canAccessModule('products') || canAccessModule('categories') || canAccessModule('brands')): ?>
        <div class="menu-title">Inventory</div>
        <?php if (canAccessModule('products')): ?>
        <a href="<?= BASE_URL ?>/modules/products/index.php" class="<?= navActive('/products/') ?>"><i class="fas fa-box icon-main"></i>Products</a>
        <?php endif; ?>
        <?php if (canAccessModule('categories')): ?>
        <a href="<?= BASE_URL ?>/modules/categories/index.php" class="<?= navActive('/categories/') ?>"><i class="fas fa-tags icon-main"></i>Categories</a>
        <?php endif; ?>
        <?php if (canAccessModule('brands')): ?>
        <a href="<?= BASE_URL ?>/modules/brands/index.php" class="<?= navActive('/brands/') ?>"><i class="fas fa-star icon-main"></i>Brands</a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (canAccessModule('customers') || canAccessModule('suppliers') || canAccessModule('purchases') || canAccessModule('sales') || canAccessModule('customer_payments') || canAccessModule('deliveries') || canAccessModule('stock_movements') || canAccessModule('expenses')): ?>
        <div class="menu-title">Operations</div>
        <?php if (canAccessModule('customers')): ?>
        <a href="<?= BASE_URL ?>/modules/customers/index.php" class="<?= navActive('/customers/') ?>"><i class="fas fa-users icon-main"></i>Customers</a>
        <?php endif; ?>
        <?php if (canAccessModule('suppliers')): ?>
        <a href="<?= BASE_URL ?>/modules/suppliers/index.php" class="<?= navActive('/suppliers/') ?>"><i class="fas fa-truck icon-main"></i>Suppliers</a>
        <?php endif; ?>
        <?php if (canAccessModule('purchases')): ?>
        <a href="<?= BASE_URL ?>/modules/purchases/index.php" class="<?= navActive('/purchases/') ?>"><i class="fas fa-cart-plus icon-main"></i>Purchases</a>
        <?php if (canAccessModule('supply_chain')): ?>
        <a href="<?= BASE_URL ?>/modules/supply_chain/index.php" class="<?= navActive('/supply_chain/') ?>"><i class="fas fa-project-diagram icon-main"></i>Supply Chain</a>
        <?php endif; ?>
        <?php endif; ?>
        <?php if (canAccessModule('sales')): ?>
        <a href="<?= BASE_URL ?>/modules/sales/index.php" class="<?= navActive('/sales/') ?>"><i class="fas fa-cash-register icon-main"></i>Sales / POS</a>
        <?php endif; ?>
        <?php if (canAccessModule('customer_payments')): ?>
        <a href="<?= BASE_URL ?>/modules/customer_payments/index.php" class="<?= navActive('/customer_payments/') ?>"><i class="fas fa-money-check-alt icon-main"></i>Customer Payments</a>
        <?php endif; ?>
        <?php if (canAccessModule('deliveries')): ?>
        <a href="<?= BASE_URL ?>/modules/deliveries/index.php" class="<?= navActive('/deliveries/') ?>"><i class="fas fa-shipping-fast icon-main"></i>Deliveries</a>
        <?php endif; ?>
        <?php if (canAccessModule('stock_movements')): ?>
        <a href="<?= BASE_URL ?>/modules/stock_movements/index.php" class="<?= navActive('/stock_movements/') ?>"><i class="fas fa-exchange-alt icon-main"></i>Stock Movements</a>
        <?php endif; ?>
        <?php if (canAccessModule('expenses')): ?>
        <a href="<?= BASE_URL ?>/modules/expenses/index.php" class="<?= navActive('/expenses/') ?>"><i class="fas fa-money-bill-wave icon-main"></i>Expenses</a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (canAccessModule('reports') || canAccessModule('analytics')): ?>
        <div class="menu-title">Reports &amp; Analytics</div>
        <?php if (canAccessModule('reports')): ?>
        <a href="<?= BASE_URL ?>/modules/reports/index.php" class="<?= navActive('/reports/') ?>"><i class="fas fa-chart-bar icon-main"></i>Reports</a>
        <?php endif; ?>
        <?php if (canAccessModule('analytics')): ?>
        <a href="<?= BASE_URL ?>/modules/analytics/index.php" class="<?= navActive('/analytics/') ?>"><i class="fas fa-chart-line icon-main"></i>Analytics</a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (isSuperUser()): ?>
        <div class="menu-title">Administration</div>
        <a href="<?= BASE_URL ?>/modules/users/index.php" class="<?= navActive('/users/') ?>"><i class="fas fa-users-cog icon-main"></i>Users</a>
        <a href="<?= BASE_URL ?>/modules/settings/index.php" class="<?= navActive('/settings/') ?>"><i class="fas fa-cogs icon-main"></i>Settings</a>
        <?php endif; ?>
        <?php if (canAccessModule('mpesa')): ?>
        <a href="<?= BASE_URL ?>/modules/mpesa/index.php" class="<?= navActive('/mpesa/') ?>"><i class="fas fa-mobile-alt icon-main"></i>M-PESA</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/auth/logout.php" class="logout-link"><i class="fas fa-power-off icon-main"></i>Logout</a>
    </nav>
</aside>
