<?php
// includes/sidebar.php
if (session_status() === PHP_SESSION_NONE) session_start();

$isSuperUser = (isset($_SESSION['is_super']) && $_SESSION['is_super'] === 1);
$fullName = $_SESSION['full_name'] ?? 'Guest User';
$userRole = $_SESSION['role'] ?? 'Staff';

// Highlights the exact link
function isActive($path) {
    return strpos($_SERVER['REQUEST_URI'], $path) !== false ? 'active' : '';
}

// Keeps the parent dropdown open if a child link is active
function isParentActive($paths) {
    foreach ($paths as $path) {
        if (strpos($_SERVER['REQUEST_URI'], $path) !== false) return 'open';
    }
    return '';
}
?>

<style>
    :root {
        --sidebar-blue: #004a99;
        --hover-bg: #005bc1;
        --active-bg: #ffffff;
        --text-light: #e0f2ff;
        --accent-glow: #00d4ff;
        --icon-glow: rgba(0, 212, 255, 0.4);
    }

    .sidebar {
        width: 260px;
        background: var(--sidebar-blue);
        color: var(--text-light);
        transition: all 0.3s;
        height: calc(100vh - 75px);
        position: fixed;
        top: 75px;
        left: 0;
        overflow-y: auto;
        box-shadow: 4px 0 15px rgba(0,0,0,0.15);
        z-index: 1000;
    }

    /* Scrollbar styling for sidebar */
    .sidebar::-webkit-scrollbar { width: 6px; }
    .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 10px; }
    .sidebar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.3); }

    .brand {
        background: rgba(0, 0, 0, 0.2);
        color: #ffffff;
        padding: 20px;
        text-align: center;
        font-weight: 800;
        font-size: 1.1em;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }

    .user-profile {
        padding: 20px;
        background: rgba(255, 255, 255, 0.05);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
    }
    
    .user-avatar {
        width: 40px;
        height: 40px;
        background: var(--accent-glow);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        color: var(--sidebar-blue);
        margin-right: 12px;
        flex-shrink: 0;
    }
    
    .user-info .name { 
        font-size: 0.9em; 
        font-weight: 700; 
        display: block; 
        color: #fff; 
    }
    
    .user-info .role { 
        font-size: 0.75em; 
        opacity: 0.8; 
    }

    .sidebar nav { 
        padding: 20px 0 30px 0; 
    }

    .sidebar nav a {
        padding: 12px 18px;
        display: flex;
        align-items: center;
        color: var(--text-light);
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        margin: 4px 12px;
        border-radius: 8px;
    }

    .sidebar nav a i.icon-main {
        margin-right: 14px;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        transition: all 0.3s ease;
        text-shadow: 0 0 8px var(--icon-glow);
    }

    .sidebar nav a:hover {
        background: var(--hover-bg);
        color: #ffffff;
        transform: translateX(4px);
    }

    .sidebar nav a:hover i.icon-main {
        transform: scale(1.2);
        color: var(--accent-glow);
    }

    .sidebar nav a.active {
        background: var(--active-bg);
        color: var(--sidebar-blue);
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        font-weight: 700;
    }

    .sidebar nav a.active i.icon-main {
        color: var(--sidebar-blue);
        text-shadow: none;
    }

    .menu-title {
        padding: 24px 25px 8px;
        font-size: 10px;
        text-transform: uppercase;
        color: var(--accent-glow);
        font-weight: 800;
        letter-spacing: 2px;
        display: flex;
        align-items: center;
    }
    
    .menu-title::after {
        content: "";
        height: 1px;
        flex-grow: 1;
        background: rgba(0, 212, 255, 0.2);
        margin-left: 10px;
    }

    /* Submenu Styles */
    .has-submenu > a { 
        position: relative; 
    }
    
    .caret {
        position: absolute;
        right: 15px;
        font-size: 12px;
        transition: transform 0.3s ease;
    }
    
    .has-submenu.open > a .caret { 
        transform: rotate(180deg); 
    }
    
    .has-submenu.open > a { 
        background: rgba(0,0,0,0.15); 
        border-left: 3px solid var(--accent-glow); 
    }
    
    .submenu {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.4s ease-out;
        background: rgba(0,0,0,0.08);
        margin: 0 12px;
        border-radius: 0 0 8px 8px;
    }
    
    .has-submenu.open .submenu {
        max-height: 500px; 
        padding: 5px 0;
        margin-bottom: 10px;
    }
    
    .submenu a {
        padding: 10px 15px 10px 45px;
        margin: 2px 0;
        font-size: 13px;
        border-radius: 0;
    }
    
    .submenu a:hover { 
        transform: translateX(2px); 
        background: rgba(255,255,255,0.05); 
    }
    
    .submenu a i { 
        margin-right: 10px; 
        font-size: 14px; 
        opacity: 0.7; 
    }

    .logout-link {
        background: rgba(255, 77, 77, 0.1) !important;
        color: #ff9e9e !important;
        margin-top: 25px !important;
        border: 1px dashed rgba(255, 77, 77, 0.4);
    }
    
    .logout-link:hover {
        background: #ff4d4d !important;
        color: #fff !important;
        border-style: solid;
    }

    /* Content adjustment for sidebar */
    .content {
        margin-left: 260px;
        flex: 1;
    }

    @media (max-width: 768px) {
        .sidebar {
            position: fixed;
            left: -260px;
            top: 75px;
            height: calc(100vh - 75px);
            z-index: 999;
            transition: left 0.3s;
        }

        .sidebar.active {
            left: 0;
        }

        .content {
            margin-left: 0;
        }

        .sidebar-toggle {
            display: block;
        }
    }
</style>

<aside class="sidebar" role="navigation">
    <div class="brand">
        🍾 Stalk & Stable
    </div>

    <div class="user-profile">
        <div class="user-avatar"><?= htmlspecialchars(substr($fullName, 0, 1)); ?></div>
        <div class="user-info">
            <span class="name"><?= htmlspecialchars($fullName); ?></span>
            <span class="role"><?= htmlspecialchars($userRole); ?></span>
        </div>
    </div>

    <nav>
        <a href="../../index.php" class="<?= isActive('index.php') ?>">
            <i class="fas fa-th-large icon-main"></i> Dashboard
        </a>
        <a href="../../modules/products/index.php" class="<?= isActive('products') ?>">
            <i class="fas fa-box icon-main"></i> Products
        </a>
        <a href="../../modules/categories/index.php" class="<?= isActive('categories') ?>">
            <i class="fas fa-tags icon-main"></i> Categories
        </a>
        <a href="../../modules/brands/index.php" class="<?= isActive('brands') ?>">
            <i class="fas fa-star icon-main"></i> Brands
        </a>

        <div class="menu-title">Operations</div>

        <a href="../../modules/customers/index.php" class="<?= isActive('customers') ?>">
            <i class="fas fa-users icon-main"></i> Customers
        </a>
        <a href="../../modules/suppliers/index.php" class="<?= isActive('suppliers') ?>">
            <i class="fas fa-truck icon-main"></i> Suppliers
        </a>
        <a href="../../modules/sales/index.php" class="<?= isActive('sales') ?>">
            <i class="fas fa-credit-card icon-main"></i> Sales (POS)
        </a>
        <a href="../../modules/expenses/index.php" class="<?= isActive('expenses') ?>">
            <i class="fas fa-money-bill-wave icon-main"></i> Expenses
        </a>

        <div class="menu-title">Reports & Admin</div>

        <a href="../../modules/reports/index.php" class="<?= isActive('reports') ?>">
            <i class="fas fa-chart-bar icon-main"></i> Reports
        </a>

        <?php if ($isSuperUser): ?>
            <div class="has-submenu <?= isParentActive(['users', 'settings']) ?>">
                <a href="#" class="menu-toggle">
                    <i class="fas fa-cogs icon-main"></i> Administration
                    <i class="fas fa-chevron-down caret"></i>
                </a>
                <div class="submenu">
                    <a href="../../modules/users/index.php" class="<?= isActive('users') ?>"><i class="fas fa-users-cog"></i> Manage Users</a>
                    <a href="../../modules/settings/index.php" class="<?= isActive('settings') ?>"><i class="fas fa-sliders-h"></i> Settings</a>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="menu-title">Exit</div>
        <a href="../../auth/logout.php" class="logout-link">
            <i class="fas fa-power-off icon-main"></i> Logout
        </a>
    </nav>
</aside>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const toggles = document.querySelectorAll(".menu-toggle");

        toggles.forEach(toggle => {
            toggle.addEventListener("click", function(e) {
                e.preventDefault();
                const parent = this.parentElement;
                
                document.querySelectorAll(".has-submenu").forEach(item => {
                    if (item !== parent) {
                        item.classList.remove("open");
                    }
                });

                parent.classList.toggle("open");
            });
        });
    });
</script>
