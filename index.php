<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';

$pageTitle = 'Dashboard';

function scalar(PDO $db, string $sql): int|float
{
    $value = $db->query($sql)->fetchColumn();
    return is_numeric($value) ? (float)$value : 0;
}

$totalProducts = (int)scalar($conn, "SELECT COUNT(*) FROM products WHERE status = 'Active'");
$totalCustomers = (int)scalar($conn, "SELECT COUNT(*) FROM customers WHERE status = 'Active'");
$lowStock = (int)scalar($conn, "SELECT COUNT(*) FROM products WHERE status = 'Active' AND stock_quantity <= reorder_level");
$monthSales = (float)scalar($conn, "SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE sale_status = 'Completed' AND YEAR(sale_date)=YEAR(CURDATE()) AND MONTH(sale_date)=MONTH(CURDATE())");
$monthExpenses = (float)scalar($conn, "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE YEAR(expense_date)=YEAR(CURDATE()) AND MONTH(expense_date)=MONTH(CURDATE())");
$creditBalance = (float)scalar($conn, "SELECT COALESCE(SUM(balance),0) FROM customers WHERE status='Active' AND balance > 0");

$recentSales = $conn->query(
    "SELECT s.invoice_number, s.sale_date, s.total_amount, c.customer_name
     FROM sales s
     LEFT JOIN customers c ON c.id=s.customer_id
     WHERE s.sale_status='Completed'
     ORDER BY s.id DESC LIMIT 8"
)->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="content">
    <div class="content-header" style="display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:25px">
        <div>
            <h1 style="margin:0">Dashboard Overview</h1>
            <p style="color:#64748b;margin:7px 0 0">Welcome back, <?= e($_SESSION['full_name'] ?? 'User') ?>.</p>
        </div>
        <div style="color:#64748b;font-size:13px"><?= date('l, d M Y') ?></div>
    </div>

    <div class="cards-grid">
        <div class="card"><div class="card-header"><h3>Sales This Month</h3><span class="card-icon">💵</span></div><p class="card-value">KES <?= number_format($monthSales,2) ?></p><p class="card-subtitle">Completed sales</p></div>
        <div class="card"><div class="card-header"><h3>Products</h3><span class="card-icon">📦</span></div><p class="card-value"><?= number_format($totalProducts) ?></p><p class="card-subtitle">Active products</p></div>
        <div class="card"><div class="card-header"><h3>Customers</h3><span class="card-icon">👥</span></div><p class="card-value"><?= number_format($totalCustomers) ?></p><p class="card-subtitle">Active customers</p></div>
        <div class="card card-alert"><div class="card-header"><h3>Low Stock</h3><span class="card-icon">⚠️</span></div><p class="card-value"><?= number_format($lowStock) ?></p><p class="card-subtitle">Items requiring reorder</p></div>
    </div>

    <div class="stats-section">
        <div class="stat-card">
            <h4>Recent Sales</h4>
            <?php if (!$recentSales): ?>
                <p class="text-muted">No sales recorded yet.</p>
            <?php else: ?>
                <div style="overflow:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead><tr><th style="text-align:left;padding:10px">Invoice</th><th style="text-align:left;padding:10px">Customer</th><th style="text-align:left;padding:10px">Date</th><th style="text-align:right;padding:10px">Amount</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentSales as $sale): ?>
                            <tr style="border-top:1px solid #e2e8f0">
                                <td style="padding:10px"><?= e($sale['invoice_number']) ?></td>
                                <td style="padding:10px"><?= e($sale['customer_name'] ?: 'Walk-in Customer') ?></td>
                                <td style="padding:10px"><?= e(date('d M Y H:i', strtotime($sale['sale_date']))) ?></td>
                                <td style="padding:10px;text-align:right">KES <?= number_format((float)$sale['total_amount'],2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <div class="stat-card">
            <h4>Financial Snapshot</h4>
            <div style="display:grid;gap:12px">
                <div><span class="text-muted">Expenses this month</span><strong style="display:block">KES <?= number_format($monthExpenses,2) ?></strong></div>
                <div><span class="text-muted">Customer credit outstanding</span><strong style="display:block">KES <?= number_format($creditBalance,2) ?></strong></div>
            </div>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
