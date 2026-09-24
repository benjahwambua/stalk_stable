<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';

$pageTitle = 'Purchases';

$search = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? '');

$sql = "SELECT pu.*, s.supplier_name, u.full_name
        FROM purchases pu
        LEFT JOIN suppliers s ON s.id = pu.supplier_id
        LEFT JOIN users u ON u.id = pu.user_id
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (pu.purchase_number LIKE :q OR s.supplier_name LIKE :q)";
    $params[':q'] = "%{$search}%";
}
if (in_array($status, ['Draft','Completed','Cancelled'], true)) {
    $sql .= " AND pu.status = :status";
    $params[':status'] = $status;
}
$sql .= " ORDER BY pu.id DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$purchases = $stmt->fetchAll();

$stats = [
    'count' => (int)$conn->query("SELECT COUNT(*) FROM purchases WHERE status='Completed'")->fetchColumn(),
    'value' => (float)$conn->query("SELECT COALESCE(SUM(total_amount),0) FROM purchases WHERE status='Completed'")->fetchColumn(),
    'credit' => (float)$conn->query("SELECT COALESCE(SUM(balance),0) FROM purchases WHERE status='Completed'")->fetchColumn(),
    'suppliers' => (int)$conn->query("SELECT COUNT(*) FROM suppliers WHERE status='Active'")->fetchColumn()
];

$suppliers = $conn->query("SELECT id, supplier_name, credit_limit, balance FROM suppliers WHERE status='Active' ORDER BY supplier_name")->fetchAll();
$products = $conn->query("SELECT id, product_name, sku, buying_price, stock_quantity, unit FROM products WHERE status='Active' ORDER BY product_name")->fetchAll();

$flash = $_SESSION['purchase_flash'] ?? null;
unset($_SESSION['purchase_flash']);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
<style>
.page-head{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:22px;flex-wrap:wrap}.page-head h1{margin:0}.muted{color:#64748b}
.btn{display:inline-flex;align-items:center;gap:7px;padding:10px 15px;border-radius:8px;text-decoration:none;border:0;cursor:pointer;font-weight:700;font-size:13px}.btn-primary{background:#004a99;color:#fff}.btn-light{background:#eef2f7;color:#334155}.btn-danger{background:#fee2e2;color:#b91c1c}.btn-success{background:#047857;color:#fff}
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:20px}.stat{background:#fff;border-radius:12px;padding:18px;box-shadow:0 1px 4px #00000012}.stat strong{display:block;font-size:24px}.stat span{color:#64748b;font-size:13px}
.toolbar,.table-card{background:#fff;padding:15px;border-radius:12px;margin-bottom:15px;box-shadow:0 1px 4px #00000012}.toolbar{display:flex;gap:10px;flex-wrap:wrap}.toolbar input,.toolbar select{padding:10px 12px;border:1px solid #dbe3ec;border-radius:8px}.toolbar input{min-width:280px}.table-wrap{overflow:auto}.table{width:100%;border-collapse:collapse;min-width:1050px}.table th{background:#004a99;color:#fff;text-align:left;padding:12px;font-size:12px}.table td{padding:12px;border-bottom:1px solid #e5e7eb;font-size:13px}.status{padding:5px 9px;border-radius:20px;font-size:11px;font-weight:700}.status.completed{background:#dcfce7;color:#166534}.status.draft{background:#fef3c7;color:#92400e}.status.cancelled{background:#fee2e2;color:#991b1b}.flash{padding:12px 15px;border-radius:9px;margin-bottom:18px;background:#ecfdf5;color:#166534;border:1px solid #bbf7d0}
.modal{display:none;position:fixed;inset:0;background:#0008;z-index:2000;align-items:center;justify-content:center;padding:20px}.modal.show{display:flex}.modal-box{background:#fff;border-radius:15px;padding:24px;width:min(1100px,100%);max-height:92vh;overflow:auto;position:relative}.modal-close{position:absolute;right:18px;top:12px;border:0;background:none;font-size:28px;cursor:pointer}.form-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.form-grid label{font-size:12px;font-weight:700;color:#475569}.form-grid input,.form-grid select{display:block;width:100%;box-sizing:border-box;margin-top:6px;padding:10px;border:1px solid #dbe3ec;border-radius:8px}.purchase-items{margin-top:20px}.purchase-items h3{margin-bottom:10px}.items-table{width:100%;border-collapse:collapse;min-width:850px}.items-table th,.items-table td{padding:9px;border-bottom:1px solid #e5e7eb;text-align:left}.items-table th{font-size:11px;background:#f8fafc}.items-table input,.items-table select{width:100%;box-sizing:border-box;padding:8px;border:1px solid #dbe3ec;border-radius:6px}.items-table .qty{width:90px}.items-table .price{width:120px}.items-table .line-total{font-weight:700;white-space:nowrap}.remove-row{border:0;background:#fee2e2;color:#b91c1c;border-radius:6px;padding:7px;cursor:pointer}.purchase-footer{display:flex;justify-content:space-between;gap:20px;align-items:flex-end;margin-top:18px}.totals{min-width:280px}.totals div{display:flex;justify-content:space-between;padding:6px 0}.totals .grand{font-size:18px;font-weight:800;border-top:1px solid #ddd;margin-top:5px;padding-top:10px}.form-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:20px}
@media(max-width:900px){.stats-grid{grid-template-columns:repeat(2,1fr)}.form-grid{grid-template-columns:1fr 1fr}}@media(max-width:600px){.stats-grid{grid-template-columns:1fr}.form-grid{grid-template-columns:1fr}.purchase-footer{flex-direction:column}.totals{width:100%}}
</style>

<div class="page-head">
    <div><h1>Purchases</h1><p class="muted">Receive stock, record supplier invoices and manage supplier credit.</p></div>
    <button class="btn btn-primary" onclick="document.getElementById('purchaseModal').classList.add('show')"><i class="fas fa-plus"></i> New Purchase</button>
</div>

<?php if ($flash): ?><div class="flash"><?= e($flash) ?></div><?php endif; ?>

<div class="stats-grid">
    <div class="stat"><strong><?= number_format($stats['count']) ?></strong><span>Completed Purchases</span></div>
    <div class="stat"><strong>KES <?= number_format($stats['value'], 2) ?></strong><span>Purchase Value</span></div>
    <div class="stat"><strong>KES <?= number_format($stats['credit'], 2) ?></strong><span>Supplier Credit</span></div>
    <div class="stat"><strong><?= number_format($stats['suppliers']) ?></strong><span>Active Suppliers</span></div>
</div>

<form class="toolbar" method="get">
    <input name="q" value="<?= e($search) ?>" placeholder="Search purchase number or supplier...">
    <select name="status">
        <option value="">All statuses</option>
        <option value="Completed" <?= $status === 'Completed' ? 'selected' : '' ?>>Completed</option>
        <option value="Draft" <?= $status === 'Draft' ? 'selected' : '' ?>>Draft</option>
        <option value="Cancelled" <?= $status === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
    </select>
    <button class="btn btn-light"><i class="fas fa-search"></i> Filter</button>
    <a class="btn btn-light" href="<?= BASE_URL ?>/modules/purchases/index.php">Reset</a>
</form>

<div class="table-card">
<div class="table-wrap">
<table class="table">
<thead><tr><th>Purchase No.</th><th>Date</th><th>Supplier</th><th>Total</th><th>Paid</th><th>Balance</th><th>Payment</th><th>Created By</th><th>Status</th></tr></thead>
<tbody>
<?php if (!$purchases): ?>
<tr><td colspan="9" style="text-align:center;padding:45px;color:#64748b">No purchases found.</td></tr>
<?php else: foreach ($purchases as $purchase): ?>
<tr>
<td><strong><?= e($purchase['purchase_number']) ?></strong></td>
<td><?= e(date('d M Y H:i', strtotime($purchase['purchase_date']))) ?></td>
<td><?= e($purchase['supplier_name'] ?: '—') ?></td>
<td>KES <?= number_format((float)$purchase['total_amount'], 2) ?></td>
<td>KES <?= number_format((float)$purchase['paid_amount'], 2) ?></td>
<td>KES <?= number_format((float)$purchase['balance'], 2) ?></td>
<td><?= e($purchase['payment_method']) ?></td>
<td><?= e($purchase['full_name'] ?: '—') ?></td>
<td><span class="status <?= strtolower($purchase['status']) ?>"><?= e($purchase['status']) ?></span></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
</div>
</main>

<div id="purchaseModal" class="modal">
<div class="modal-box">
<button class="modal-close" type="button" onclick="document.getElementById('purchaseModal').classList.remove('show')">&times;</button>
<h2>New Purchase</h2>
<p class="muted">Completing this purchase will immediately increase stock and create stock movement records.</p>

<form method="post" action="store.php" id="purchaseForm">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

<div class="form-grid">
<label>Supplier *
<select name="supplier_id" required>
<option value="">Select supplier</option>
<?php foreach ($suppliers as $supplier): ?>
<option value="<?= (int)$supplier['id'] ?>"><?= e($supplier['supplier_name']) ?> — Balance KES <?= number_format((float)$supplier['balance'],2) ?></option>
<?php endforeach; ?>
</select></label>
<label>Purchase Date *<input type="datetime-local" name="purchase_date" required value="<?= date('Y-m-dTH:i') ?>"></label>
<label>Payment Method *
<select name="payment_method">
<option>Credit</option><option>Cash</option><option>M-Pesa</option><option>Bank</option><option>Cheque</option><option>Other</option>
</select></label>
<label>Paid Amount *<input type="number" name="paid_amount" id="paidAmount" min="0" step="0.01" value="0"></label>
<label>Discount<input type="number" name="discount" id="discount" min="0" step="0.01" value="0"></label>
<label>Tax<input type="number" name="tax" id="tax" min="0" step="0.01" value="0"></label>
<label style="grid-column:1/-1">Notes<input name="notes" maxlength="500" placeholder="Supplier invoice number, delivery note, etc."></label>
</div>

<div class="purchase-items">
<h3>Items</h3>
<div class="table-wrap">
<table class="items-table">
<thead><tr><th style="width:40%">Product *</th><th>Quantity *</th><th>Buying Price *</th><th>Line Total</th><th></th></tr></thead>
<tbody id="purchaseItems"></tbody>
</table>
</div>
<button type="button" class="btn btn-light" onclick="addPurchaseRow()" style="margin-top:12px"><i class="fas fa-plus"></i> Add Item</button>
</div>

<div class="purchase-footer">
<div>
<strong>Supplier credit is calculated from this purchase.</strong><br>
<small class="muted">Paid amount cannot exceed the purchase total.</small>
</div>
<div class="totals">
<div><span>Items Subtotal</span><strong id="subtotalDisplay">KES 0.00</strong></div>
<div><span>Discount</span><strong id="discountDisplay">KES 0.00</strong></div>
<div><span>Tax</span><strong id="taxDisplay">KES 0.00</strong></div>
<div class="grand"><span>Total</span><strong id="totalDisplay">KES 0.00</strong></div>
</div>
</div>

<div class="form-actions"><button type="button" class="btn btn-light" onclick="document.getElementById('purchaseModal').classList.remove('show')">Cancel</button><button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Complete Purchase</button></div>
</form>
</div>
</div>

<script>
const products = <?= json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
let rowNumber = 0;

function productOptions() {
    return '<option value="">Select product</option>' + products.map(p =>
        '<option value="' + p.id + '">' + escapeHtml(p.product_name) + (p.sku ? ' — ' + escapeHtml(p.sku) : '') + '</option>'
    ).join('');
}
function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
}
function addPurchaseRow() {
    rowNumber++;
    const row = document.createElement('tr');
    row.dataset.row = rowNumber;
    row.innerHTML = '<td><select name="items['+rowNumber+'][product_id]" required onchange="updateRow('+rowNumber+')">' + productOptions() + '</select></td>' +
        '<td><input class="qty" type="number" name="items['+rowNumber+'][quantity]" min="1" step="1" value="1" required oninput="updateRow('+rowNumber+')"></td>' +
        '<td><input class="price" type="number" name="items['+rowNumber+'][buying_price]" min="0" step="0.01" value="0" required oninput="updateRow('+rowNumber+')"></td>' +
        '<td class="line-total">KES <span id="line_'+rowNumber+'">0.00</span></td>' +
        '<td><button type="button" class="remove-row" onclick="removePurchaseRow('+rowNumber+')"><i class="fas fa-trash"></i></button></td>';
    document.getElementById('purchaseItems').appendChild(row);
}
function updateRow(id) {
    const row = document.querySelector('[data-row="'+id+'"]');
    if (!row) return;
    const qty = parseFloat(row.querySelector('[name$="[quantity]"]').value) || 0;
    const price = parseFloat(row.querySelector('[name$="[buying_price]"]').value) || 0;
    document.getElementById('line_'+id).textContent = (qty * price).toFixed(2);
    updateTotals();
}
function removePurchaseRow(id) {
    const row = document.querySelector('[data-row="'+id+'"]');
    if (row) row.remove();
    updateTotals();
}
function updateTotals() {
    let subtotal = 0;
    document.querySelectorAll('#purchaseItems tr').forEach(row => {
        const qty = parseFloat(row.querySelector('[name$="[quantity]"]').value) || 0;
        const price = parseFloat(row.querySelector('[name$="[buying_price]"]').value) || 0;
        subtotal += qty * price;
    });
    const discount = Math.max(0, parseFloat(document.getElementById('discount').value) || 0);
    const tax = Math.max(0, parseFloat(document.getElementById('tax').value) || 0);
    const total = Math.max(0, subtotal - discount + tax);
    document.getElementById('subtotalDisplay').textContent = 'KES ' + subtotal.toFixed(2);
    document.getElementById('discountDisplay').textContent = 'KES ' + discount.toFixed(2);
    document.getElementById('taxDisplay').textContent = 'KES ' + tax.toFixed(2);
    document.getElementById('totalDisplay').textContent = 'KES ' + total.toFixed(2);
    document.getElementById('paidAmount').max = total.toFixed(2);
}
document.getElementById('discount').addEventListener('input', updateTotals);
document.getElementById('tax').addEventListener('input', updateTotals);
document.getElementById('paidAmount').addEventListener('input', function(){ updateTotals(); });
addPurchaseRow();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>