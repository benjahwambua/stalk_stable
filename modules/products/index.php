<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}

require_once("../../config/db.php");

// Fetch products with brand + supplier
$stmt = $conn->query("
    SELECT p.*, b.brand_name, s.supplier_name 
    FROM products p
    LEFT JOIN brands b ON p.brand_id = b.id
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    ORDER BY p.id DESC
");

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Default alcohol list for Kenya with icons
$default_alcohols = [
    ['name' => 'Beer', 'icon' => '🍺', 'category' => 'Beer & Malt'],
    ['name' => 'Whiskey', 'icon' => '🥃', 'category' => 'Spirits'],
    ['name' => 'Vodka', 'icon' => '🥃', 'category' => 'Spirits'],
    ['name' => 'Gin', 'icon' => '🥃', 'category' => 'Spirits'],
    ['name' => 'Rum', 'icon' => '🥃', 'category' => 'Spirits'],
    ['name' => 'Wine', 'icon' => '🍷', 'category' => 'Wine'],
    ['name' => 'Champagne', 'icon' => '🍾', 'category' => 'Champagne & Sparkling'],
    ['name' => 'Brandy', 'icon' => '🥃', 'category' => 'Spirits'],
    ['name' => 'Liqueur', 'icon' => '🥃', 'category' => 'Liqueurs'],
    ['name' => 'Tequila', 'icon' => '🥃', 'category' => 'Spirits'],
    ['name' => 'Brandy', 'icon' => '🥃', 'category' => 'Spirits'],
    ['name' => 'Cider', 'icon' => '🍎', 'category' => 'Cider'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Inventory - Stalk & Stable</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .products-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-xl);
            gap: var(--space-lg);
            flex-wrap: wrap;
        }

        .products-header h1 {
            margin: 0;
        }

        .header-actions {
            display: flex;
            gap: var(--space-md);
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-sm) var(--space-lg);
            border: none;
            border-radius: var(--radius-lg);
            font-weight: var(--font-weight-semibold);
            cursor: pointer;
            transition: all var(--transition-base);
            text-decoration: none;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: var(--brand);
            color: #fff;
            box-shadow: var(--shadow-sm);
        }

        .btn-primary:hover {
            background: var(--brand-dark);
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--brand-soft);
            color: var(--brand-dark);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: #dce3d4;
            box-shadow: var(--shadow-sm);
        }

        .btn-sm {
            padding: var(--space-xs) var(--space-md);
            font-size: 0.85rem;
        }

        .btn-edit {
            background: #3b82f6;
            color: #fff;
        }

        .btn-edit:hover {
            background: #2563eb;
        }

        .btn-delete {
            background: #ef4444;
            color: #fff;
        }

        .btn-delete:hover {
            background: #dc2626;
        }

        .search-bar {
            flex: 1;
            min-width: 250px;
            display: flex;
            gap: var(--space-sm);
        }

        .search-bar input {
            flex: 1;
            padding: var(--space-sm) var(--space-md);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            font-size: 0.95rem;
            font-family: var(--font-family);
            transition: border-color var(--transition-base);
        }

        .search-bar input:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(49, 92, 43, 0.1);
        }

        .view-toggle {
            display: flex;
            gap: var(--space-sm);
            background: var(--border-light);
            padding: var(--space-xs);
            border-radius: var(--radius-lg);
        }

        .view-toggle button {
            padding: var(--space-xs) var(--space-md);
            border: none;
            background: transparent;
            cursor: pointer;
            border-radius: var(--radius-md);
            font-weight: var(--font-weight-semibold);
            transition: all var(--transition-base);
            font-size: 0.9rem;
        }

        .view-toggle button.active {
            background: var(--panel);
            box-shadow: var(--shadow-sm);
            color: var(--brand);
        }

        /* Table View */
        .products-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--panel);
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .products-table thead {
            background: var(--brand-soft);
            border-bottom: 2px solid var(--border);
        }

        .products-table th {
            padding: var(--space-md) var(--space-lg);
            text-align: left;
            font-weight: var(--font-weight-bold);
            color: var(--brand-dark);
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .products-table td {
            padding: var(--space-md) var(--space-lg);
            border-bottom: 1px solid var(--border);
            color: var(--ink);
        }

        .products-table tbody tr {
            transition: all var(--transition-base);
        }

        .products-table tbody tr:hover {
            background: var(--bg-light);
            box-shadow: inset 0 0 0 2px var(--brand-soft);
        }

        .product-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: var(--brand-soft);
            border-radius: var(--radius-md);
            font-size: 1.5rem;
            margin-right: var(--space-sm);
        }

        .product-name-cell {
            display: flex;
            align-items: center;
            gap: var(--space-md);
        }

        .product-info {
            display: flex;
            flex-direction: column;
            gap: var(--space-xs);
        }

        .product-info strong {
            color: var(--ink);
            font-size: 0.95rem;
        }

        .product-info small {
            color: var(--muted);
            font-size: 0.8rem;
        }

        .price-cell {
            font-weight: var(--font-weight-semibold);
            color: var(--brand);
        }

        .stock-cell {
            font-weight: var(--font-weight-bold);
            text-align: center;
            padding: var(--space-md) var(--space-lg);
        }

        .stock-low {
            color: var(--danger);
            background: rgba(220, 38, 38, 0.1);
            padding: var(--space-xs) var(--space-md);
            border-radius: var(--radius-md);
        }

        .stock-ok {
            color: var(--success);
            background: rgba(16, 185, 129, 0.1);
            padding: var(--space-xs) var(--space-md);
            border-radius: var(--radius-md);
        }

        .action-buttons {
            display: flex;
            gap: var(--space-sm);
            align-items: center;
            flex-wrap: wrap;
        }

        /* Grid View */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: var(--space-lg);
        }

        .product-card {
            display: flex;
            flex-direction: column;
            padding: var(--space-lg);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            background: var(--panel);
            box-shadow: var(--shadow-sm);
            transition: all var(--transition-base);
            position: relative;
            overflow: hidden;
        }

        .product-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--brand), var(--brand-light));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform var(--transition-base);
        }

        .product-card:hover {
            border-color: var(--brand-soft);
            box-shadow: var(--shadow-md);
            transform: translateY(-4px);
        }

        .product-card:hover::before {
            transform: scaleX(1);
        }

        .card-icon-large {
            font-size: 3rem;
            text-align: center;
            margin-bottom: var(--space-md);
        }

        .card-title {
            font-weight: var(--font-weight-bold);
            font-size: 1.1rem;
            color: var(--ink);
            margin-bottom: var(--space-sm);
        }

        .card-badge {
            display: inline-block;
            background: var(--brand-soft);
            color: var(--brand-dark);
            padding: var(--space-xs) var(--space-md);
            border-radius: var(--radius-md);
            font-size: 0.8rem;
            font-weight: var(--font-weight-semibold);
            margin-bottom: var(--space-md);
        }

        .card-details {
            display: grid;
            gap: var(--space-sm);
            margin-bottom: var(--space-lg);
            flex: 1;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
        }

        .detail-row strong {
            color: var(--muted);
        }

        .card-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-sm);
        }

        .card-actions .btn {
            justify-content: center;
            margin: 0;
        }

        .empty-state {
            text-align: center;
            padding: var(--space-2xl);
            background: var(--bg-light);
            border: 2px dashed var(--border);
            border-radius: var(--radius-xl);
            margin: var(--space-xl) 0;
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: var(--space-md);
        }

        .empty-state h3 {
            color: var(--muted);
            margin-bottom: var(--space-md);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 300;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: var(--space-2xl);
            max-width: 500px;
            width: 90%;
            box-shadow: var(--shadow-lg);
            animation: slideUp var(--transition-base);
        }

        @keyframes slideUp {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-lg);
        }

        .modal-header h2 {
            margin: 0;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--muted);
            padding: 0;
        }

        .close-btn:hover {
            color: var(--ink);
        }

        .form-group {
            margin-bottom: var(--space-lg);
        }

        .form-group label {
            display: block;
            font-weight: var(--font-weight-semibold);
            margin-bottom: var(--space-sm);
            color: var(--ink);
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: var(--space-sm) var(--space-md);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            font-size: 0.95rem;
            font-family: var(--font-family);
            transition: border-color var(--transition-base);
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(49, 92, 43, 0.1);
        }

        .icon-picker {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: var(--space-md);
            margin-bottom: var(--space-lg);
        }

        .icon-option {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-md);
            border: 2px solid var(--border);
            border-radius: var(--radius-lg);
            cursor: pointer;
            font-size: 2rem;
            transition: all var(--transition-base);
        }

        .icon-option:hover {
            border-color: var(--brand);
            background: var(--brand-soft);
        }

        .icon-option.selected {
            border-color: var(--brand);
            background: var(--brand);
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: var(--space-lg);
            margin-bottom: var(--space-xl);
        }

        .stat-box {
            padding: var(--space-lg);
            background: var(--brand-soft);
            border-radius: var(--radius-lg);
            text-align: center;
            border: 1px solid var(--border);
        }

        .stat-box strong {
            display: block;
            font-size: 1.8rem;
            color: var(--brand-dark);
            margin-bottom: var(--space-sm);
        }

        .stat-box small {
            color: var(--muted);
            font-size: 0.85rem;
        }

        @media (max-width: 768px) {
            .products-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .search-bar {
                width: 100%;
            }

            .header-actions {
                width: 100%;
                justify-content: flex-start;
            }

            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }

            .action-buttons {
                flex-direction: column;
            }

            .products-table {
                font-size: 0.85rem;
            }

            .products-table th,
            .products-table td {
                padding: var(--space-sm);
            }
        }
    </style>
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
                    <li><a href="../../index.php" class="nav-link">
                        <span class="nav-icon">📊</span>
                        <span>Dashboard</span>
                    </a></li>
                    <li><a href="index.php" class="nav-link active">
                        <span class="nav-icon">📦</span>
                        <span>Products</span>
                    </a></li>
                    <li><a href="../categories/" class="nav-link">
                        <span class="nav-icon">🏷️</span>
                        <span>Categories</span>
                    </a></li>
                    <li><a href="../brands/" class="nav-link">
                        <span class="nav-icon">⭐</span>
                        <span>Brands</span>
                    </a></li>
                    <li><a href="../customers/" class="nav-link">
                        <span class="nav-icon">👥</span>
                        <span>Customers</span>
                    </a></li>
                    <li><a href="../suppliers/" class="nav-link">
                        <span class="nav-icon">🚚</span>
                        <span>Suppliers</span>
                    </a></li>
                    <li><a href="../sales/" class="nav-link">
                        <span class="nav-icon">💳</span>
                        <span>Sales (POS)</span>
                    </a></li>
                    <li><a href="../expenses/" class="nav-link">
                        <span class="nav-icon">💰</span>
                        <span>Expenses</span>
                    </a></li>
                    <li><a href="../reports/" class="nav-link">
                        <span class="nav-icon">📈</span>
                        <span>Reports</span>
                    </a></li>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <a href="../../auth/logout.php" class="nav-link logout-link">
                    <span class="nav-icon">🚪</span>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <!-- MAIN WRAPPER -->
        <div class="main-wrapper">
            <!-- TOPBAR -->
            <header class="topbar">
                <div class="topbar-left">
                    <h2>Products</h2>
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
                <!-- Stats Row -->
                <div class="stats-row">
                    <div class="stat-box">
                        <strong><?php echo count($products); ?></strong>
                        <small>Total Products</small>
                    </div>
                    <div class="stat-box">
                        <strong><?php echo array_sum(array_column($products, 'stock_quantity')); ?></strong>
                        <small>Total Stock</small>
                    </div>
                    <div class="stat-box">
                        <strong><?php echo number_format(array_sum(array_column($products, 'selling_price')), 2); ?></strong>
                        <small>Total Value</small>
                    </div>
                </div>

                <!-- Header Section -->
                <div class="products-header">
                    <div>
                        <h1>Products Inventory</h1>
                        <p class="text-muted">Manage your alcohol distribution inventory</p>
                    </div>
                    <div class="header-actions">
                        <div class="search-bar">
                            <input type="text" id="searchInput" placeholder="Search products...">
                        </div>
                        <button class="btn btn-primary" onclick="openAddProductModal()">
                            <span>➕</span>
                            <span>Add Product</span>
                        </button>
                    </div>
                </div>

                <!-- View Toggle -->
                <div style="display: flex; gap: var(--space-lg); margin-bottom: var(--space-lg);">
                    <div class="view-toggle">
                        <button class="active" onclick="switchView('table')">📊 Table</button>
                        <button onclick="switchView('grid')">🎴 Grid</button>
                    </div>
                </div>

                <!-- Table View -->
                <div id="tableView" class="view-container">
                    <?php if (count($products) > 0): ?>
                    <table class="products-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Brand</th>
                                <th>Supplier</th>
                                <th>Buying Price</th>
                                <th>Selling Price</th>
                                <th>Stock</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="productTableBody">
                            <?php foreach ($products as $p): ?>
                            <tr class="product-row" data-name="<?php echo strtolower($p['product_name']); ?>">
                                <td>
                                    <div class="product-name-cell">
                                        <div class="product-icon"><?php echo isset($p['icon']) ? $p['icon'] : '🍾'; ?></div>
                                        <div class="product-info">
                                            <strong><?php echo htmlspecialchars($p['product_name']); ?></strong>
                                            <small>ID: <?php echo $p['id']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($p['brand_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($p['supplier_name'] ?? 'N/A'); ?></td>
                                <td class="price-cell">KES <?php echo number_format($p['buying_price'], 2); ?></td>
                                <td class="price-cell">KES <?php echo number_format($p['selling_price'], 2); ?></td>
                                <td class="stock-cell">
                                    <?php if ($p['stock_quantity'] < 10): ?>
                                        <span class="stock-low">⚠️ <?php echo $p['stock_quantity']; ?></span>
                                    <?php else: ?>
                                        <span class="stock-ok">✓ <?php echo $p['stock_quantity']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="edit.php?id=<?php echo $p['id']; ?>" class="btn btn-edit btn-sm">✏️ Edit</a>
                                        <a href="delete.php?id=<?php echo $p['id']; ?>" class="btn btn-delete btn-sm" onclick="return confirm('Are you sure?')">🗑️ Delete</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📦</div>
                        <h3>No Products Found</h3>
                        <p class="text-muted">Start by adding your first product to your inventory.</p>
                        <button class="btn btn-primary" onclick="openAddProductModal()" style="margin-top: var(--space-lg);">
                            <span>➕</span>
                            <span>Add First Product</span>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Grid View -->
                <div id="gridView" class="view-container" style="display: none;">
                    <?php if (count($products) > 0): ?>
                    <div class="products-grid" id="productGrid">
                        <?php foreach ($products as $p): ?>
                        <div class="product-card product-row" data-name="<?php echo strtolower($p['product_name']); ?>">
                            <div class="card-icon-large"><?php echo isset($p['icon']) ? $p['icon'] : '🍾'; ?></div>
                            <div class="card-title"><?php echo htmlspecialchars($p['product_name']); ?></div>
                            <span class="card-badge"><?php echo htmlspecialchars($p['brand_name'] ?? 'No Brand'); ?></span>
                            
                            <div class="card-details">
                                <div class="detail-row">
                                    <strong>Supplier:</strong>
                                    <span><?php echo htmlspecialchars($p['supplier_name'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <strong>Buy Price:</strong>
                                    <span>KES <?php echo number_format($p['buying_price'], 2); ?></span>
                                </div>
                                <div class="detail-row">
                                    <strong>Sell Price:</strong>
                                    <span class="price-cell">KES <?php echo number_format($p['selling_price'], 2); ?></span>
                                </div>
                                <div class="detail-row">
                                    <strong>Stock:</strong>
                                    <span <?php echo $p['stock_quantity'] < 10 ? 'class="stock-low"' : 'class="stock-ok"'; ?>>
                                        <?php echo $p['stock_quantity']; ?> units
                                    </span>
                                </div>
                            </div>

                            <div class="card-actions">
                                <a href="edit.php?id=<?php echo $p['id']; ?>" class="btn btn-edit btn-sm">✏️ Edit</a>
                                <a href="delete.php?id=<?php echo $p['id']; ?>" class="btn btn-delete btn-sm" onclick="return confirm('Are you sure?')">🗑️ Delete</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
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

    <!-- Modal for Adding/Editing Products -->
    <div id="productModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Product</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>

            <form onsubmit="handleProductSubmit(event)">
                <div class="form-group">
                    <label for="productName">Product Name</label>
                    <input type="text" id="productName" required placeholder="Enter product name">
                </div>

                <div class="form-group">
                    <label>Select Icon</label>
                    <div class="icon-picker" id="iconPicker">
                        <?php foreach ($default_alcohols as $alcohol): ?>
                        <div class="icon-option" onclick="selectIcon(this, '<?php echo $alcohol['icon']; ?>')">
                            <?php echo $alcohol['icon']; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="selectedIcon" value="🍾">
                </div>

                <div class="form-group">
                    <label for="brandId">Brand</label>
                    <select id="brandId" required>
                        <option value="">Select a brand...</option>
                        <!-- Populate from database -->
                    </select>
                </div>

                <div class="form-group">
                    <label for="supplierId">Supplier</label>
                    <select id="supplierId" required>
                        <option value="">Select a supplier...</option>
                        <!-- Populate from database -->
                    </select>
                </div>

                <div class="form-group">
                    <label for="buyingPrice">Buying Price (KES)</label>
                    <input type="number" id="buyingPrice" step="0.01" required placeholder="0.00">
                </div>

                <div class="form-group">
                    <label for="sellingPrice">Selling Price (KES)</label>
                    <input type="number" id="sellingPrice" step="0.01" required placeholder="0.00">
                </div>

                <div class="form-group">
                    <label for="stockQuantity">Stock Quantity</label>
                    <input type="number" id="stockQuantity" min="0" required placeholder="0">
                </div>

                <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-md); margin-top: var(--space-xl);">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()" style="width: 100%;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.product-row');
            
            rows.forEach(row => {
                const name = row.getAttribute('data-name');
                if (name.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // View switching
        function switchView(view) {
            document.querySelectorAll('.view-toggle button').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            if (view === 'table') {
                document.getElementById('tableView').style.display = '';
                document.getElementById('gridView').style.display = 'none';
            } else {
                document.getElementById('tableView').style.display = 'none';
                document.getElementById('gridView').style.display = '';
            }
        }

        // Modal functions
        function openAddProductModal() {
            document.getElementById('productModal').classList.add('active');
            document.getElementById('modalTitle').textContent = 'Add New Product';
        }

        function closeModal() {
            document.getElementById('productModal').classList.remove('active');
        }

        function selectIcon(element, icon) {
            document.querySelectorAll('.icon-option').forEach(el => el.classList.remove('selected'));
            element.classList.add('selected');
            document.getElementById('selectedIcon').value = icon;
        }

        function handleProductSubmit(event) {
            event.preventDefault();
            alert('Form submission would be handled by create.php');
            // Actual form submission logic here
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('productModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
