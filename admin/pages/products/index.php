<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
    exit();
}

require_once __DIR__ . '/../../config/db.php';

$BASE_URL = '../..';
$ASSETS_PATH = '../../assets';
$PAGE_TITLE = 'Products Inventory | Stalk & Stable';
$HEADER_TITLE = 'Products Inventory';
$HEADER_SUBTITLE = 'Stock, pricing, suppliers, and portfolio control';
$companyName = 'Stalk & Stable';

$lowStockThreshold = 10;
$products = [];
$brands = [];
$suppliers = [];
$error = null;
$successMessage = null;

// Check for success/error messages from redirect
if (!empty($_SESSION['success_message'])) {
    $successMessage = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (!empty($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function money($value): string
{
    return 'KES ' . number_format((float)($value ?? 0), 2);
}

function product_number($value): float
{
    return is_numeric($value) ? (float)$value : 0.0;
}

function db_fetch_all($conn, string $sql): array
{
    if ($conn instanceof PDO) {
        $stmt = $conn->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    if (class_exists('mysqli') && $conn instanceof mysqli) {
        $result = $conn->query($sql);
        if ($result === false) {
            throw new RuntimeException($conn->error);
        }
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    throw new RuntimeException('Unsupported database connection type.');
}

function product_icon(array $product): string
{
    if (!empty($product['icon'])) {
        return (string)$product['icon'];
    }

    $name = strtolower((string)($product['product_name'] ?? ''));
    $category = strtolower((string)($product['category'] ?? ''));
    $haystack = $name . ' ' . $category;

    return match (true) {
        str_contains($haystack, 'beer') || str_contains($haystack, 'lager') || str_contains($haystack, 'stout') => '🍺',
        str_contains($haystack, 'wine') || str_contains($haystack, 'merlot') || str_contains($haystack, 'cabernet') => '🍷',
        str_contains($haystack, 'champagne') || str_contains($haystack, 'sparkling') => '🍾',
        str_contains($haystack, 'cider') => '🍎',
        str_contains($haystack, 'gin') => '🌿',
        str_contains($haystack, 'vodka') => '❄️',
        str_contains($haystack, 'rum') => '🏴‍☠️',
        default => '🥃',
    };
}

function product_category(array $product): string
{
    $category = trim((string)($product['category'] ?? $product['category_name'] ?? ''));
    return $category !== '' ? $category : 'Uncategorized';
}

try {
    if (!isset($conn)) {
        throw new RuntimeException('Database connection was not initialized.');
    }

    $products = db_fetch_all($conn, "
        SELECT p.*, b.brand_name, s.supplier_name
        FROM products p
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        ORDER BY p.id DESC
    ");

    try {
        $brands = db_fetch_all($conn, 'SELECT id, brand_name FROM brands ORDER BY brand_name ASC');
    } catch (Throwable $brandError) {
        error_log('Brands lookup failed: ' . $brandError->getMessage());
        $brands = [];
    }

    try {
        $suppliers = db_fetch_all($conn, 'SELECT id, supplier_name FROM suppliers ORDER BY supplier_name ASC');
    } catch (Throwable $supplierError) {
        error_log('Suppliers lookup failed: ' . $supplierError->getMessage());
        $suppliers = [];
    }
} catch (Throwable $exception) {
    error_log('Products inventory error: ' . $exception->getMessage());
    $error = 'We could not load the products inventory right now. Please try again or contact your administrator.';
}

$totalProducts = count($products);
$totalStock = 0;
$totalInventoryValue = 0.0;
$totalCostValue = 0.0;
$lowStockCount = 0;
$outOfStockCount = 0;
$uniqueBrands = [];
$uniqueSuppliers = [];
$uniqueCategories = [];

foreach ($products as $product) {
    $stock = (int)product_number($product['stock_quantity'] ?? 0);
    $buyingPrice = product_number($product['buying_price'] ?? 0);
    $sellingPrice = product_number($product['selling_price'] ?? 0);
    $brandName = trim((string)($product['brand_name'] ?? ''));
    $supplierName = trim((string)($product['supplier_name'] ?? ''));
    $category = product_category($product);

    $totalStock += $stock;
    $totalInventoryValue += $sellingPrice * $stock;
    $totalCostValue += $buyingPrice * $stock;

    if ($stock === 0) {
        $outOfStockCount++;
    } elseif ($stock < $lowStockThreshold) {
        $lowStockCount++;
    }

    if ($brandName !== '') $uniqueBrands[$brandName] = true;
    if ($supplierName !== '') $uniqueSuppliers[$supplierName] = true;
    if ($category !== '') $uniqueCategories[$category] = true;
}

$marginValue = $totalInventoryValue - $totalCostValue;
$categoryOptions = array_keys($uniqueCategories);
sort($categoryOptions, SORT_NATURAL | SORT_FLAG_CASE);

$defaultAlcohols = [
    ['name' => 'Tusker Lager', 'icon' => '🍺', 'category' => 'Beer & Malt'],
    ['name' => 'White Cap', 'icon' => '🍺', 'category' => 'Beer & Malt'],
    ['name' => 'Whiskey', 'icon' => '🥃', 'category' => 'Spirits'],
    ['name' => 'Vodka', 'icon' => '❄️', 'category' => 'Spirits'],
    ['name' => 'Gin', 'icon' => '🌿', 'category' => 'Spirits'],
    ['name' => 'Rum', 'icon' => '🏴‍☠️', 'category' => 'Spirits'],
    ['name' => 'Wine', 'icon' => '🍷', 'category' => 'Wine'],
    ['name' => 'Champagne', 'icon' => '🍾', 'category' => 'Champagne & Sparkling'],
    ['name' => 'Brandy', 'icon' => '🥃', 'category' => 'Spirits'],
    ['name' => 'Liqueur', 'icon' => '🍸', 'category' => 'Liqueurs'],
    ['name' => 'Tequila', 'icon' => '🌵', 'category' => 'Spirits'],
    ['name' => 'Cider', 'icon' => '🍎', 'category' => 'Cider'],
];

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($PAGE_TITLE); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f8fafc;
            color: #0f172a;
        }

        body {
            overflow-x: hidden;
        }

        .page-wrapper {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        .page-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            width: 100%;
            overflow: hidden;
        }

        .page-main {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 24px;
            width: 100%;
        }

        .container {
            max-width: 1600px;
            width: 100%;
            margin: 0 auto;
        }

        /* Alert Messages */
        .alert {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 24px;
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

        .alert-icon {
            font-size: 18px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .alert-content {
            flex: 1;
        }

        .alert-error {
            background: #fff1f2;
            border: 1px solid #fecdd3;
            color: #9f1239;
        }

        .alert-error strong {
            color: #9f1239;
        }

        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .alert-success strong {
            color: #166534;
        }

        .alert p {
            margin: 0;
            font-size: 13px;
        }

        .alert small {
            display: block;
            margin-top: 4px;
            font-size: 12px;
            opacity: 0.8;
        }

        /* Hero Section */
        .product-hero {
            background: #ffffff;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            flex-wrap: wrap;
        }

        .hero-content h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #0f172a;
        }

        .hero-content p {
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
            max-width: 500px;
        }

        .eyebrow {
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #0b67d9;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .hero-actions {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        /* Stats Section */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-box {
            background: #ffffff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            border-left: 4px solid #0b67d9;
        }

        .stat-box strong {
            display: block;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 6px;
            color: #0f172a;
        }

        .stat-box small {
            color: #64748b;
            font-size: 13px;
        }

        .stat-box.warning {
            border-left-color: #f59e0b;
            background: #fffbeb;
        }

        .stat-box.warning strong {
            color: #d97706;
        }

        /* Insights Grid */
        .insights-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .insight-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .insight-icon {
            font-size: 28px;
            flex-shrink: 0;
        }

        .insight-card strong {
            display: block;
            font-size: 18px;
            color: #0f172a;
        }

        .insight-card small {
            display: block;
            color: #64748b;
            font-size: 12px;
            margin-top: 2px;
        }

        /* Toolbar */
        .inventory-toolbar {
            background: #ffffff;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            margin-bottom: 16px;
            display: grid;
            grid-template-columns: 1fr auto auto auto;
            gap: 12px;
            align-items: center;
        }

        .search-bar {
            display: flex;
            align-items: center;
        }

        .search-bar input {
            width: 100%;
            border: 1px solid #d8e0ea;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }

        .search-bar input:focus {
            border-color: #0b67d9;
            box-shadow: 0 0 0 3px rgba(11, 103, 217, 0.1);
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .filter-select {
            border: 1px solid #d8e0ea;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 14px;
            background: #ffffff;
            cursor: pointer;
            outline: none;
            transition: border-color 0.2s;
            min-width: 150px;
        }

        .filter-select:focus {
            border-color: #0b67d9;
            box-shadow: 0 0 0 3px rgba(11, 103, 217, 0.1);
        }

        .view-toggle {
            display: flex;
            gap: 6px;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 8px;
        }

        .view-toggle button {
            border: none;
            background: transparent;
            border-radius: 6px;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
            color: #64748b;
        }

        .view-toggle button:hover {
            background: rgba(0, 0, 0, 0.05);
        }

        .view-toggle button.active {
            background: #0b67d9;
            color: #ffffff;
        }

        /* Category Cloud */
        .category-cloud {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .category-chip {
            border: 1px solid #d8e0ea;
            background: #ffffff;
            border-radius: 20px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
            font-weight: 500;
            color: #0f172a;
        }

        .category-chip:hover {
            border-color: #0b67d9;
            background: #eff6ff;
            color: #0b67d9;
        }

        .category-chip.active {
            border-color: #0b67d9;
            background: #0b67d9;
            color: #ffffff;
        }

        /* Result Summary */
        .result-summary {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 16px;
            padding: 0 4px;
        }

        /* Table View */
        .products-table-wrapper {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            overflow-x: auto;
        }

        .products-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .products-table thead {
            background: #0b67d9;
            color: #ffffff;
        }

        .products-table th {
            padding: 14px;
            text-align: left;
            font-weight: 600;
            vertical-align: top;
        }

        .products-table td {
            padding: 14px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .products-table tbody tr:hover {
            background: #f9fafb;
        }

        .product-name-cell {
            display: flex;
            gap: 10px;
            align-items: center;
            min-width: 200px;
        }

        .product-icon {
            font-size: 24px;
            flex-shrink: 0;
        }

        .product-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .product-info strong {
            color: #0f172a;
            font-weight: 600;
        }

        .product-info small {
            color: #64748b;
            font-size: 12px;
        }

        .price-cell {
            font-weight: 600;
            color: #0f172a;
        }

        .badge {
            display: inline-block;
            font-size: 12px;
            background: #eff6ff;
            color: #1e3a8a;
            border-radius: 6px;
            padding: 4px 10px;
            font-weight: 500;
        }

        .stock-ok {
            color: #15803d;
            font-weight: 600;
        }

        .stock-low {
            color: #b45309;
            font-weight: 600;
        }

        .stock-out {
            color: #dc2626;
            font-weight: 600;
        }

        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        /* Buttons */
        .btn {
            border: none;
            border-radius: 8px;
            padding: 10px 16px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-primary {
            background: #0b67d9;
            color: #ffffff;
        }

        .btn-primary:hover:not(:disabled) {
            background: #0a4fa8;
            box-shadow: 0 4px 12px rgba(11, 103, 217, 0.3);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #0f172a;
        }

        .btn-secondary:hover:not(:disabled) {
            background: #cbd5e1;
        }

        .btn-edit {
            background: #1d4ed8;
            color: #ffffff;
            font-size: 13px;
            padding: 8px 12px;
        }

        .btn-edit:hover:not(:disabled) {
            background: #1e40af;
        }

        .btn-delete {
            background: #dc2626;
            color: #ffffff;
            font-size: 13px;
            padding: 8px 12px;
        }

        .btn-delete:hover:not(:disabled) {
            background: #b91c1c;
        }

        /* Grid View */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 16px;
        }

        .product-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            display: flex;
            flex-direction: column;
            gap: 12px;
            transition: all 0.2s;
        }

        .product-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }

        .card-icon-large {
            font-size: 40px;
            line-height: 1;
        }

        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
            margin: 0;
        }

        .card-badge {
            display: inline-block;
            font-size: 12px;
            background: #eff6ff;
            color: #1e3a8a;
            border-radius: 6px;
            padding: 4px 10px;
            font-weight: 500;
            width: fit-content;
        }

        .card-details {
            display: grid;
            gap: 8px;
            font-size: 13px;
            margin: 8px 0;
            flex: 1;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
        }

        .detail-row strong {
            color: #64748b;
            font-weight: 500;
        }

        .detail-row span {
            color: #0f172a;
            font-weight: 600;
            text-align: right;
        }

        .card-actions {
            display: flex;
            gap: 8px;
            margin-top: auto;
        }

        .card-actions .btn {
            flex: 1;
            justify-content: center;
            font-size: 12px;
            padding: 8px 10px;
        }

        /* Empty State */
        .empty-state {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            padding: 40px 24px;
            text-align: center;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 12px;
        }

        .empty-state h3 {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 16px;
        }

        /* Modal */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 16px;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: #ffffff;
            width: 100%;
            max-width: 600px;
            border-radius: 12px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            background: #ffffff;
            z-index: 10;
        }

        .modal-header h2 {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }

        .close-btn {
            border: none;
            background: transparent;
            font-size: 24px;
            cursor: pointer;
            color: #64748b;
            line-height: 1;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .close-btn:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        /* Form */
        .product-form {
            padding: 20px;
        }

        .form-grid {
            display: grid;
            gap: 16px;
        }

        .form-grid.two-columns {
            grid-template-columns: repeat(2, 1fr);
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 14px;
            color: #0f172a;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            border: 1px solid #d8e0ea;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #0b67d9;
            box-shadow: 0 0 0 3px rgba(11, 103, 217, 0.1);
        }

        .icon-picker {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 8px;
        }

        .icon-option {
            border: 2px solid #d8e0ea;
            background: #ffffff;
            border-radius: 8px;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 24px;
            line-height: 1;
            transition: all 0.2s;
        }

        .icon-option:hover {
            border-color: #0b67d9;
            background: #eff6ff;
        }

        .icon-option.selected {
            border-color: #0b67d9;
            background: #0b67d9;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 20px;
            padding: 0 20px 20px;
        }

        .hidden {
            display: none !important;
        }

        .is-filtered-out {
            display: none !important;
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            }

            .insights-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .inventory-toolbar {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 900px) {
            .page-main {
                padding: 16px;
            }

            .product-hero {
                padding: 16px;
                flex-direction: column;
            }

            .hero-actions {
                width: 100%;
                justify-content: flex-start;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .insights-grid {
                grid-template-columns: 1fr;
            }

            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            }

            .inventory-toolbar {
                grid-template-columns: 1fr 1fr;
            }

            .view-toggle {
                grid-column: 1 / -1;
            }

            .form-grid.two-columns {
                grid-template-columns: 1fr;
            }

            .product-name-cell {
                min-width: auto;
            }

            .products-table {
                font-size: 13px;
            }

            .products-table th,
            .products-table td {
                padding: 10px;
            }

            .filter-select {
                min-width: auto;
            }
        }

        @media (max-width: 640px) {
            .page-main {
                padding: 12px;
            }

            .product-hero {
                padding: 12px;
                gap: 12px;
            }

            .hero-content h1 {
                font-size: 22px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .stat-box {
                padding: 16px;
            }

            .insights-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .hero-actions {
                flex-direction: column;
                width: 100%;
            }

            .hero-actions .btn {
                width: 100%;
                justify-content: center;
            }

            .products-table {
                font-size: 12px;
                min-width: 100%;
            }

            .products-table th,
            .products-table td {
                padding: 8px;
            }

            .product-name-cell {
                flex-direction: column;
                gap: 4px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-buttons .btn {
                width: 100%;
                justify-content: center;
                font-size: 12px;
                padding: 8px;
            }

            .modal-content {
                width: 100%;
                max-height: 95vh;
            }

            .products-grid {
                grid-template-columns: 1fr;
            }

            .inventory-toolbar {
                grid-template-columns: 1fr;
            }

            .view-toggle {
                width: 100%;
            }

            .view-toggle button {
                flex: 1;
                justify-content: center;
            }

            .category-cloud {
                gap: 6px;
            }

            .category-chip {
                font-size: 12px;
                padding: 4px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <div class="page-content">
            <main class="page-main">
                <div class="container">
                    <?php if ($successMessage): ?>
                        <div class="alert alert-success" role="alert">
                            <span class="alert-icon" aria-hidden="true">✅</span>
                            <div class="alert-content">
                                <strong>Success</strong>
                                <p><?= e($successMessage); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-error" role="alert">
                            <span class="alert-icon" aria-hidden="true">⚠️</span>
                            <div class="alert-content">
                                <strong>Error</strong>
                                <p><?= e($error); ?></p>
                                <small>If this persists, please contact your administrator.</small>
                            </div>
                        </div>
                    <?php endif; ?>

                    <section class="product-hero" aria-labelledby="products-title">
                        <div class="hero-content">
                            <span class="eyebrow">Inventory Control</span>
                            <h1 id="products-title">Products Inventory</h1>
                            <p>Manage stock levels, buying prices, selling prices, supplier relationships, and product mix from one production-ready dashboard.</p>
                        </div>
                        <div class="hero-actions">
                            <a class="btn btn-secondary" href="../../index.php">← Dashboard</a>
                            <button class="btn btn-primary" type="button" data-open-modal>
                                <span aria-hidden="true">➕</span>
                                <span>Add Product</span>
                            </button>
                        </div>
                    </section>

                    <section class="stats-grid" aria-label="Inventory summary">
                        <article class="stat-box">
                            <strong><?= number_format($totalProducts); ?></strong>
                            <small>Total Products</small>
                        </article>
                        <article class="stat-box">
                            <strong><?= number_format($totalStock); ?></strong>
                            <small>Total Units</small>
                        </article>
                        <article class="stat-box">
                            <strong><?= money($totalInventoryValue); ?></strong>
                            <small>Retail Stock Value</small>
                        </article>
                        <article class="stat-box <?= ($lowStockCount + $outOfStockCount) > 0 ? 'warning' : ''; ?>">
                            <strong><?= number_format($lowStockCount + $outOfStockCount); ?></strong>
                            <small>Needs Attention</small>
                        </article>
                    </section>

                    <section class="insights-grid" aria-label="Portfolio insights">
                        <article class="insight-card">
                            <span class="insight-icon">🏷️</span>
                            <div>
                                <strong><?= number_format(count($uniqueBrands)); ?></strong>
                                <small>Brands represented</small>
                            </div>
                        </article>
                        <article class="insight-card">
                            <span class="insight-icon">🚚</span>
                            <div>
                                <strong><?= number_format(count($uniqueSuppliers)); ?></strong>
                                <small>Suppliers connected</small>
                            </div>
                        </article>
                        <article class="insight-card">
                            <span class="insight-icon">📚</span>
                            <div>
                                <strong><?= number_format(count($uniqueCategories)); ?></strong>
                                <small>Categories tracked</small>
                            </div>
                        </article>
                        <article class="insight-card">
                            <span class="insight-icon">📈</span>
                            <div>
                                <strong><?= money($marginValue); ?></strong>
                                <small>Potential gross margin</small>
                            </div>
                        </article>
                    </section>

                    <section class="inventory-toolbar" aria-label="Product search and filters">
                        <div class="search-bar">
                            <label class="sr-only" for="searchInput">Search products</label>
                            <input type="search" id="searchInput" placeholder="Search product, brand, supplier, category..." autocomplete="off">
                        </div>

                        <div class="filter-group">
                            <label class="sr-only" for="categoryFilter">Filter by category</label>
                            <select id="categoryFilter" class="filter-select">
                                <option value="all">All Categories</option>
                                <?php foreach ($categoryOptions as $category): ?>
                                    <option value="<?= e(strtolower($category)); ?>"><?= e($category); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="sr-only" for="stockFilter">Filter by stock status</label>
                            <select id="stockFilter" class="filter-select">
                                <option value="all">All Stock</option>
                                <option value="healthy">Healthy Stock</option>
                                <option value="low">Low Stock</option>
                                <option value="out">Out of Stock</option>
                            </select>
                        </div>

                        <div class="view-toggle" role="group" aria-label="Switch product view">
                            <button class="active" type="button" data-view-button="table" aria-pressed="true">📊 Table</button>
                            <button type="button" data-view-button="grid" aria-pressed="false">🎴 Grid</button>
                        </div>
                    </section>

                    <?php if (!empty($categoryOptions)): ?>
                        <section class="category-cloud" aria-label="Available product categories">
                            <?php foreach ($categoryOptions as $category): ?>
                                <button type="button" class="category-chip" data-category-chip="<?= e(strtolower($category)); ?>"><?= e($category); ?></button>
                            <?php endforeach; ?>
                        </section>
                    <?php endif; ?>

                    <p id="resultSummary" class="result-summary" aria-live="polite">
                        Showing <?= number_format($totalProducts); ?> of <?= number_format($totalProducts); ?> products.
                    </p>

                    <section id="tableView" class="view-container" aria-label="Products table view">
                        <?php if ($totalProducts > 0): ?>
                            <div class="products-table-wrapper">
                                <table class="products-table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Category</th>
                                            <th>Brand</th>
                                            <th>Supplier</th>
                                            <th>Buying Price</th>
                                            <th>Selling Price</th>
                                            <th>Stock</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="productTableBody">
                                        <?php foreach ($products as $product): ?>
                                            <?php
                                                $stock = (int)product_number($product['stock_quantity'] ?? 0);
                                                $stockStatus = $stock === 0 ? 'out' : ($stock < $lowStockThreshold ? 'low' : 'healthy');
                                                $category = product_category($product);
                                                $searchText = strtolower(trim(implode(' ', [
                                                    $product['product_name'] ?? '',
                                                    $product['brand_name'] ?? '',
                                                    $product['supplier_name'] ?? '',
                                                    $category,
                                                    $product['id'] ?? '',
                                                ])));
                                            ?>
                                            <tr class="product-row" data-name="<?= e($searchText); ?>" data-category="<?= e(strtolower($category)); ?>" data-stock="<?= e($stockStatus); ?>">
                                                <td>
                                                    <div class="product-name-cell">
                                                        <span class="product-icon"><?= e(product_icon($product)); ?></span>
                                                        <div class="product-info">
                                                            <strong><?= e($product['product_name'] ?? 'Unnamed Product'); ?></strong>
                                                            <small>SKU #<?= e($product['id'] ?? 'N/A'); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><span class="badge"><?= e($category); ?></span></td>
                                                <td><?= e($product['brand_name'] ?? 'No Brand'); ?></td>
                                                <td><?= e($product['supplier_name'] ?? 'No Supplier'); ?></td>
                                                <td class="price-cell"><?= money($product['buying_price'] ?? 0); ?></td>
                                                <td class="price-cell"><?= money($product['selling_price'] ?? 0); ?></td>
                                                <td>
                                                    <?php if ($stockStatus === 'out'): ?>
                                                        <span class="stock-out">⛔ Out</span>
                                                    <?php elseif ($stockStatus === 'low'): ?>
                                                        <span class="stock-low">⚠️ <?= number_format($stock); ?></span>
                                                    <?php else: ?>
                                                        <span class="stock-ok">✓ <?= number_format($stock); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="edit.php?id=<?= urlencode((string)($product['id'] ?? '')); ?>" class="btn btn-edit">✏️ Edit</a>
                                                        <a href="delete.php?id=<?= urlencode((string)($product['id'] ?? '')); ?>" class="btn btn-delete" data-confirm-delete="<?= e($product['product_name'] ?? 'this product'); ?>">🗑️ Delete</a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">📦</div>
                                <h3>No Products Found</h3>
                                <p>Start by adding your first product to your inventory.</p>
                                <button class="btn btn-primary" type="button" data-open-modal>Add First Product</button>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section id="gridView" class="view-container hidden" aria-label="Products grid view">
                        <?php if ($totalProducts > 0): ?>
                            <div class="products-grid" id="productGrid">
                                <?php foreach ($products as $product): ?>
                                    <?php
                                        $stock = (int)product_number($product['stock_quantity'] ?? 0);
                                        $stockStatus = $stock === 0 ? 'out' : ($stock < $lowStockThreshold ? 'low' : 'healthy');
                                        $category = product_category($product);
                                        $searchText = strtolower(trim(implode(' ', [
                                            $product['product_name'] ?? '',
                                            $product['brand_name'] ?? '',
                                            $product['supplier_name'] ?? '',
                                            $category,
                                            $product['id'] ?? '',
                                        ])));
                                    ?>
                                    <article class="product-card product-row" data-name="<?= e($searchText); ?>" data-category="<?= e(strtolower($category)); ?>" data-stock="<?= e($stockStatus); ?>">
                                        <div class="card-icon-large"><?= e(product_icon($product)); ?></div>
                                        <h3 class="card-title"><?= e($product['product_name'] ?? 'Unnamed Product'); ?></h3>
                                        <span class="card-badge"><?= e($category); ?></span>

                                        <div class="card-details">
                                            <div class="detail-row">
                                                <strong>Brand:</strong>
                                                <span><?= e($product['brand_name'] ?? 'No Brand'); ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <strong>Supplier:</strong>
                                                <span><?= e($product['supplier_name'] ?? 'No Supplier'); ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <strong>Buy:</strong>
                                                <span><?= money($product['buying_price'] ?? 0); ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <strong>Sell:</strong>
                                                <span class="price-cell"><?= money($product['selling_price'] ?? 0); ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <strong>Stock:</strong>
                                                <span>
                                                    <?php if ($stockStatus === 'out'): ?>
                                                        <span class="stock-out">⛔ Out</span>
                                                    <?php elseif ($stockStatus === 'low'): ?>
                                                        <span class="stock-low">⚠️ <?= number_format($stock); ?></span>
                                                    <?php else: ?>
                                                        <span class="stock-ok">✓ <?= number_format($stock); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="card-actions">
                                            <a href="edit.php?id=<?= urlencode((string)($product['id'] ?? '')); ?>" class="btn btn-edit">✏️ Edit</a>
                                            <a href="delete.php?id=<?= urlencode((string)($product['id'] ?? '')); ?>" class="btn btn-delete" data-confirm-delete="<?= e($product['product_name'] ?? 'this product'); ?>">🗑️ Delete</a>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <div id="noFilterResults" class="empty-state hidden">
                        <div class="empty-state-icon">🔎</div>
                        <h3>No Matching Products</h3>
                        <p>Try changing your search, category, or stock filter.</p>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div id="productModal" class="modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Product</h2>
                <button class="close-btn" type="button" data-close-modal aria-label="Close add product form">&times;</button>
            </div>

            <form action="store.php" method="post" class="product-form">
                <div class="form-grid two-columns">
                    <div class="form-group full-width">
                        <label for="productName">Product Name <span style="color: #dc2626;">*</span></label>
                        <input type="text" id="productName" name="product_name" required placeholder="e.g. Tusker Lager 500ml" maxlength="160">
                    </div>

                    <div class="form-group full-width">
                        <label>Select Icon</label>
                        <div class="icon-picker" id="iconPicker">
                            <button type="button" class="icon-option selected" data-icon="🍾" title="Champagne">🍾</button>
                            <button type="button" class="icon-option" data-icon="🍺" title="Beer">🍺</button>
                            <button type="button" class="icon-option" data-icon="🍷" title="Wine">🍷</button>
                            <button type="button" class="icon-option" data-icon="🍎" title="Cider">🍎</button>
                            <button type="button" class="icon-option" data-icon="🌿" title="Gin">🌿</button>
                            <button type="button" class="icon-option" data-icon="❄️" title="Vodka">❄️</button>
                            <button type="button" class="icon-option" data-icon="🏴‍☠️" title="Rum">🏴‍☠️</button>
                            <button type="button" class="icon-option" data-icon="🥃" title="Whiskey">🥃</button>
                            <button type="button" class="icon-option" data-icon="🍸" title="Liqueur">🍸</button>
                            <button type="button" class="icon-option" data-icon="🌵" title="Tequila">🌵</button>
                        </div>
                        <input type="hidden" id="selectedIcon" name="icon" value="🍾">
                    </div>

                    <div class="form-group">
                        <label for="categoryName">Category</label>
                        <input type="text" id="categoryName" name="category" placeholder="e.g. Beer & Malt">
                    </div>

                    <div class="form-group">
                        <label for="brandId">Brand</label>
                        <select id="brandId" name="brand_id">
                            <option value="">Select a brand...</option>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?= e($brand['id'] ?? ''); ?>"><?= e($brand['brand_name'] ?? 'Unnamed Brand'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label for="supplierId">Supplier</label>
                        <select id="supplierId" name="supplier_id">
                            <option value="">Select a supplier...</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?= e($supplier['id'] ?? ''); ?>"><?= e($supplier['supplier_name'] ?? 'Unnamed Supplier'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="buyingPrice">Buying Price (KES) <span style="color: #dc2626;">*</span></label>
                        <input type="number" id="buyingPrice" name="buying_price" min="0" step="0.01" required placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label for="sellingPrice">Selling Price (KES) <span style="color: #dc2626;">*</span></label>
                        <input type="number" id="sellingPrice" name="selling_price" min="0" step="0.01" required placeholder="0.00">
                    </div>

                    <div class="form-group full-width">
                        <label for="stockQuantity">Stock Quantity <span style="color: #dc2626;">*</span></label>
                        <input type="number" id="stockQuantity" name="stock_quantity" min="0" step="1" required placeholder="0">
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('searchInput');
            const categoryFilter = document.getElementById('categoryFilter');
            const stockFilter = document.getElementById('stockFilter');
            const rows = Array.from(document.querySelectorAll('.product-row'));
            const resultSummary = document.getElementById('resultSummary');
            const noFilterResults = document.getElementById('noFilterResults');

            const tableRows = rows.filter((row) => row.tagName === 'TR');
            const gridCards = rows.filter((row) => row.classList.contains('product-card'));
            const totalProducts = tableRows.length || gridCards.length;

            function applyFilters() {
                const query = (searchInput?.value || '').trim().toLowerCase();
                const category = categoryFilter?.value || 'all';
                const stock = stockFilter?.value || 'all';
                let visible = 0;

                rows.forEach((row) => {
                    const matchesSearch = !query || (row.dataset.name || '').includes(query);
                    const matchesCategory = category === 'all' || row.dataset.category === category;
                    const matchesStock = stock === 'all' || row.dataset.stock === stock;
                    const isVisible = matchesSearch && matchesCategory && matchesStock;

                    row.classList.toggle('is-filtered-out', !isVisible);
                    if (isVisible) visible++;
                });

                const isTableVisible = !document.getElementById('tableView')?.classList.contains('hidden');
                const displayVisible = isTableVisible
                    ? tableRows.filter((r) => !r.classList.contains('is-filtered-out')).length
                    : gridCards.filter((r) => !r.classList.contains('is-filtered-out')).length;

                if (resultSummary) {
                    resultSummary.textContent = `Showing ${displayVisible.toLocaleString()} of ${totalProducts.toLocaleString()} products.`;
                }

                noFilterResults?.classList.toggle('hidden', displayVisible !== 0 || totalProducts === 0);
            }

            searchInput?.addEventListener('input', applyFilters);
            categoryFilter?.addEventListener('change', applyFilters);
            stockFilter?.addEventListener('change', applyFilters);

            document.querySelectorAll('[data-category-chip]').forEach((chip) => {
                chip.addEventListener('click', () => {
                    if (categoryFilter) {
                        categoryFilter.value = chip.dataset.categoryChip || 'all';
                        applyFilters();
                    }
                });
            });

            document.querySelectorAll('[data-view-button]').forEach((button) => {
                button.addEventListener('click', () => {
                    const view = button.dataset.viewButton;
                    document.querySelectorAll('[data-view-button]').forEach((btn) => {
                        const active = btn === button;
                        btn.classList.toggle('active', active);
                        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
                    });
                    document.getElementById('tableView')?.classList.toggle('hidden', view !== 'table');
                    document.getElementById('gridView')?.classList.toggle('hidden', view !== 'grid');
                    applyFilters();
                });
            });

            const modal = document.getElementById('productModal');
            const openModal = () => {
                modal?.classList.add('active');
                modal?.setAttribute('aria-hidden', 'false');
                document.getElementById('productName')?.focus();
            };
            const closeModal = () => {
                modal?.classList.remove('active');
                modal?.setAttribute('aria-hidden', 'true');
            };

            document.querySelectorAll('[data-open-modal]').forEach((button) => button.addEventListener('click', openModal));
            document.querySelectorAll('[data-close-modal]').forEach((button) => button.addEventListener('click', closeModal));

            modal?.addEventListener('click', (event) => {
                if (event.target === modal) closeModal();
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') closeModal();
            });

            document.querySelectorAll('.icon-option').forEach((button) => {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    document.querySelectorAll('.icon-option').forEach((item) => item.classList.remove('selected'));
                    button.classList.add('selected');
                    const selectedIcon = document.getElementById('selectedIcon');
                    if (selectedIcon) selectedIcon.value = button.dataset.icon || '🍾';
                });
            });

            document.querySelectorAll('[data-confirm-delete]').forEach((link) => {
                link.addEventListener('click', (event) => {
                    const name = link.dataset.confirmDelete || 'this product';
                    if (!confirm(`Delete ${name}? This action cannot be undone.`)) {
                        event.preventDefault();
                    }
                });
            });

            applyFilters();
        });
    </script>
</body>
</html>
