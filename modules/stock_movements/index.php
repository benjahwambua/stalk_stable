<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';

$pageTitle = 'Stock Movements';

$search = trim((string)($_GET['q'] ?? ''));
$type = (string)($_GET['type'] ?? '');

$sql = "SELECT sm.*, p.product_name, p.sku, u.full_name
        FROM stock_movements sm
        INNER JOIN products p ON p.id=sm.product_id
        LEFT JOIN users u ON u.id=sm.user_id
        WHERE 1=1";
$params=[];
if($search!==''){
    $sql.=" AND (p.product_name LIKE :q OR p.sku LIKE :q OR sm.notes LIKE :q OR CAST(sm.reference_id AS CHAR) LIKE :q)";
    $params[':q']="%{$search}%";
}
$types=['Purchase','Sale','Return','Adjustment','Damage','Opening Stock'];
if(in_array($type,$types,true)){ $sql.=" AND sm.movement_type=:type"; $params[':type']=$type; }
$sql.=" ORDER BY sm.id DESC";
$stmt=$conn->prepare($sql);$stmt->execute($params);$movements=$stmt->fetchAll();

$stats=[
'entries'=>(int)$conn->query("SELECT COUNT(*) FROM stock_movements")->fetchColumn(),
'purchases'=>(int)$conn->query("SELECT COUNT(*) FROM stock_movements WHERE movement_type='Purchase'")->fetchColumn(),
'adjustments'=>(int)$conn->query("SELECT COUNT(*) FROM stock_movements WHERE movement_type='Adjustment'")->fetchColumn(),
'damages'=>(int)$conn->query("SELECT COUNT(*) FROM stock_movements WHERE movement_type='Damage'")->fetchColumn()
];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
<style>
.page-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:22px}.page-head h1{margin:0}.muted{color:#64748b}
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:20px}.stat{background:#fff;border-radius:12px;padding:18px;box-shadow:0 1px 4px #00000012}.stat strong{display:block;font-size:24px}.stat span{color:#64748b;font-size:13px}
.toolbar,.table-card{background:#fff;padding:15px;border-radius:12px;margin-bottom:15px;box-shadow:0 1px 4px #00000012}.toolbar{display:flex;gap:10px;flex-wrap:wrap}.toolbar input,.toolbar select{padding:10px 12px;border:1px solid #dbe3ec;border-radius:8px}.toolbar input{min-width:300px}.btn{display:inline-flex;align-items:center;gap:7px;padding:10px 15px;border-radius:8px;text-decoration:none;border:0;cursor:pointer;font-weight:700;font-size:13px}.btn-light{background:#eef2f7;color:#334155}.table-wrap{overflow:auto}.table{width:100%;border-collapse:collapse;min-width:1000px}.table th{background:#004a99;color:#fff;text-align:left;padding:12px;font-size:12px}.table td{padding:12px;border-bottom:1px solid #e5e7eb;font-size:13px}.type{padding:5px 9px;border-radius:20px;font-size:11px;font-weight:700}.purchase{background:#dcfce7;color:#166534}.sale{background:#fee2e2;color:#991b1b}.adjustment{background:#dbeafe;color:#1d4ed8}.damage{background:#fef3c7;color:#92400e}.return{background:#ede9fe;color:#6d28d9}.opening.stock{background:#e0f2fe;color:#075985}
@media(max-width:900px){.stats-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:600px){.stats-grid{grid-template-columns:1fr}}
</style>
<div class="page-head"><div><h1>Stock Movements</h1><p class="muted">A complete audit trail of inventory entering and leaving the business.</p></div></div>
<div class="stats-grid"><div class="stat"><strong><?=number_format($stats['entries'])?></strong><span>Total Entries</span></div><div class="stat"><strong><?=number_format($stats['purchases'])?></strong><span>Purchase Entries</span></div><div class="stat"><strong><?=number_format($stats['adjustments'])?></strong><span>Adjustments</span></div><div class="stat"><strong><?=number_format($stats['damages'])?></strong><span>Damage Entries</span></div></div>
<form class="toolbar" method="get"><input name="q" value="<?=e($search)?>" placeholder="Search product, SKU, reference or notes..."><select name="type"><option value="">All movement types</option><?php foreach($types as $movementType): ?><option value="<?=e($movementType)?>" <?=$type===$movementType?'selected':''?>><?=e($movementType)?></option><?php endforeach; ?></select><button class="btn btn-light"><i class="fas fa-search"></i> Filter</button><a class="btn btn-light" href="<?=BASE_URL?>/modules/stock_movements/index.php">Reset</a></form>
<div class="table-card"><div class="table-wrap"><table class="table"><thead><tr><th>Date</th><th>Product</th><th>Type</th><th>Quantity</th><th>Stock Before</th><th>Stock After</th><th>Reference</th><th>User</th><th>Notes</th></tr></thead><tbody>
<?php if(!$movements): ?><tr><td colspan="9" style="text-align:center;padding:45px;color:#64748b">No stock movements found.</td></tr><?php else: foreach($movements as $m): ?>
<tr><td><?=e(date('d M Y H:i',strtotime($m['movement_date'])))?></td><td><strong><?=e($m['product_name'])?></strong><br><small class="muted"><?=e($m['sku']?:'No SKU')?></small></td><td><span class="type <?=strtolower(str_replace(' ','-',$m['movement_type']))?>"><?=e($m['movement_type'])?></span></td><td><strong><?=number_format((int)$m['quantity'])?></strong></td><td><?=number_format((int)$m['stock_before'])?></td><td><?=number_format((int)$m['stock_after'])?></td><td><?=e($m['reference_type']?:'—')?> <?= $m['reference_id'] ? '#'.(int)$m['reference_id'] : '' ?></td><td><?=e($m['full_name']?:'—')?></td><td><?=e($m['notes']?:'—')?></td></tr>
<?php endforeach; endif; ?></tbody></table></div></div>
</main>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>