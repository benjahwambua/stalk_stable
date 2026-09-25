<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../config/db.php';

$id=(int)($_GET['id']??0);
$q=$conn->prepare("SELECT po.*,s.supplier_name,s.phone,s.email,s.address,u.full_name FROM purchase_orders po JOIN suppliers s ON s.id=po.supplier_id JOIN users u ON u.id=po.user_id WHERE po.id=?");
$q->execute([$id]);$po=$q->fetch();
if(!$po){http_response_code(404);exit('Purchase order not found.');}
$q=$conn->prepare("SELECT poi.*,p.product_name,p.sku,p.unit FROM purchase_order_items poi JOIN products p ON p.id=poi.product_id WHERE poi.purchase_order_id=? ORDER BY poi.id");
$q->execute([$id]);$items=$q->fetchAll();

require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/sidebar.php';
?>
<main class="content"><style>
.sheet{max-width:1050px;margin:auto;background:#fff;padding:35px;box-shadow:0 1px 5px #0001}.top{display:flex;justify-content:space-between;gap:20px;border-bottom:2px solid #004a99;padding-bottom:20px}.title{color:#004a99;font-size:28px;font-weight:900}.meta{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin:22px 0}.table{width:100%;border-collapse:collapse}.table th{background:#004a99;color:#fff;padding:10px;text-align:left}.table td{padding:10px;border-bottom:1px solid #ddd}.total{margin:20px 0 0 auto;width:320px;border-top:2px solid #004a99;padding-top:12px;display:flex;justify-content:space-between;font-weight:900}.print{float:right;margin-bottom:15px;padding:10px 15px;background:#004a99;color:#fff;border:0;border-radius:7px}@media print{.sidebar,.app-footer,.app-header,.print{display:none!important}.content{margin:0!important}.sheet{box-shadow:none;padding:0}}@media(max-width:700px){.meta{grid-template-columns:1fr}}
</style>
<div class="sheet">
<button class="print" onclick="window.print()">Print PO</button>
<div class="top"><div><div class="title">STALK &amp; STABLE</div><div>PURCHASE ORDER</div></div><div><strong>PO #: <?=e($po['po_number'])?></strong><br>Date: <?=e(date('d M Y',strtotime($po['po_date'])))?><br>Status: <?=e($po['status'])?></div></div>
<div class="meta"><div><strong>Supplier</strong><br><?=e($po['supplier_name'])?><br><?=e($po['phone']??'')?> <?=e($po['email']??'')?><br><?=e($po['address']??'')?></div><div><strong>Prepared By</strong><br><?=e($po['full_name'])?><br>Expected Date: <?=e($po['expected_date']?date('d M Y',strtotime($po['expected_date'])):'—')?></div></div>
<table class="table"><thead><tr><th>#</th><th>Product</th><th>SKU</th><th>Qty</th><th>Unit Cost</th><th>Total</th></tr></thead><tbody>
<?php foreach($items as $i=>$x):?><tr><td><?=$i+1?></td><td><?=e($x['product_name'])?></td><td><?=e($x['sku']??'')?></td><td><?=number_format((int)$x['ordered_quantity'])?> <?=e($x['unit']??'')?></td><td>KES <?=number_format((float)$x['unit_cost'],2)?></td><td>KES <?=number_format((float)$x['line_total'],2)?></td></tr><?php endforeach;?>
</tbody></table>
<div class="total"><span>PO Total</span><span>KES <?=number_format((float)$po['total_amount'],2)?></span></div>
<?php if($po['notes']):?><p style="margin-top:28px"><strong>Notes:</strong><br><?=nl2br(e($po['notes']))?></p><?php endif;?>
</div></main>
<?php require_once __DIR__.'/../../includes/footer.php';