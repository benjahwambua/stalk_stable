<?php
session_start();

// Protect page (must be logged in)
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

require_once("config/db.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stalk & Stable Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-shell">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="brand-block">
                    <div class="brand-mark">SS</div>
                    <div>
                        <h1>Stalk & Stable</h1>
                        <p class="brand-subtitle">Alcohol Distribution</p>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <li><a href="index.php" class="nav-link active">
                        <span class="nav-icon">📊</span>
                        <span>Dashboard</span>
                    </a></li>
                    <li><a href="modules/products/" class="nav-link">
                        <span class="nav-icon">📦</span>
                        <span>Products</span>
                    </a></li>
                    <li><a href="modules/categories/" class="nav-link">
                        <span class="nav-icon">🏷️</span>
                        <span>Categories</span>
                    </a></li>
                    <li><a href="modules/brands/" class="nav-link">
                        <span class="nav-icon">⭐</span>
                        <span>Brands</span>
                    </a></li>
                    <li><a href="modules/customers/" class="nav-link">
                        <span class="nav-icon">👥</span>
                        <span>Customers</span>
                    </a></li>
                    <li><a href="modules/suppliers/" class="nav-link">
                        <span class="nav-icon">🚚</span>
                        <span>Suppliers</span>
                    </a></li>
                    <li><a href="modules/sales/" class="nav-link">
                        <span class="nav-icon">💳</span>
                        <span>Sales (POS)</span>
                    </a></li>
                    <li><a href="modules/expenses/" class="nav-link">
                        <span class="nav-icon">💰</span>
                        <span>Expenses</span>
                    </a></li>
                    <li><a href="modules/reports/" class="nav-link">
                        <span class="nav-icon">📈</span>
                        <span>Reports</span>
                    </a></li>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <a href="auth/logout.php" class="nav-link logout-link">
                    <span class="nav-icon">🚪</span>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <div class="main-wrapper">
            <!-- TOP BAR -->
            <header class="topbar">
                <div class="topbar-left">
                    <h2>Dashboard</h2>
                </div>
                <div class="topbar-right">
                    <div class="user-section">
                        <div class="user-info">
                            <p class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                            <p class="user-role"><?php echo htmlspecialchars($_SESSION['role']); ?></p>
                        </div>
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                        </div>
                    </div>
                </div>
            </header>

            <!-- MAIN CONTENT -->
            <main class="main-content">
                <div class="content-header">
                    <div>
                        <h1>Dashboard Overview</h1>
                        <p class="text-muted">Welcome back! Here's your business summary.</p>
                    </div>
                </div>

                <!-- DASHBOARD CARDS -->
                <div class="cards-grid">
                    <div class="card">
                        <div class="card-header">
                            <h3>Total Sales</h3>
                            <span class="card-icon">💵</span>
                        </div>
                        <p class="card-value">KES 0</p>
                        <p class="card-subtitle">This month</p>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3>Total Products</h3>
                            <span class="card-icon">📦</span>
                        </div>
                        <p class="card-value">0</p>
                        <p class="card-subtitle">In inventory</p>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3>Total Customers</h3>
                            <span class="card-icon">👥</span>
                        </div>
                        <p class="card-value">0</p>
                        <p class="card-subtitle">Active customers</p>
                    </div>

                    <div class="card card-alert">
                        <div class="card-header">
                            <h3>Low Stock Alerts</h3>
                            <span class="card-icon">⚠️</span>
                        </div>
                        <p class="card-value">0</p>
                        <p class="card-subtitle">Items to reorder</p>
                    </div>
                </div>

                <!-- QUICK STATS -->
                <div class="stats-section">
                    <div class="stat-card">
                        <h4>Recent Transactions</h4>
                        <p class="text-muted">No recent transactions</p>
                    </div>
                    <div class="stat-card">
                        <h4>Top Brands</h4>
                        <p class="text-muted">No data available</p>
                    </div>
                </div>
            </main>

            <!-- FOOTER -->
            <footer class="footer">
                <div class="footer-content">
                    <p>&copy; 2026 Stalk & Stable. All rights reserved.</p>
                    <div class="footer-links">
                        <a href="#">Privacy Policy</a>
                        <a href="#">Terms of Service</a>
                        <a href="#">Support</a>
                    </div>
                </div>
            </footer>
        </div>
    </div>
</body>
</html>
