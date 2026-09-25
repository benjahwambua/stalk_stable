<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../config/db.php';

$poId=(int)($_GET['po_id']??0);
$q=$conn->prepare("SELECT po.*,s.supplier_name FROM purchase_orders po JOIN suppliers s ON s.id=po.supplier_id WHERE po.id=?");
$q->execute([$poId]); $po=$q->fetch();
if(!$po){http_response_code(404);exit('Purchase order not found.');}

$q=$conn->prepare("SELECT gr.*,u.full_name FROM goods_receipts gr JOIN users u ON u.id=gr.user_id WHERE gr.purchase_order_id=? ORDER BY gr.id DESC");
$q->execute([$poId]); $grns=$q->fetchAll();

require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/sidebar.php';
?>
<main class="content">
<style>
.wrap{max-width:1200px;margin:auto}.head{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:20px}.head h1{margin:0}.muted{color:#64748b}.card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 5px #0001;margin-bottom:18px}.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 13px;border-radius:7px;text-decoration:none;border:0;font-weight:700}.light{background:#eef2f7;color:#334155}.primary{background:#004a99;color:#fff}.table{width:100%;border-collapse:collapse}.table th{background:#004a99;color:#fff;padding:11px;text-align:left;font-size:12px}.table td{padding:11px;border-bottom:1px solid #e5e7eb;font-size:13px}
</style>
<div class="wrap">
<div class="head"><div><h1>Goods Receipts</h1><p class="muted">GRNs posted against <?=e($po['po_number'])?> — <?=e($po['supplier_name'])?></p></div><a class="btn light" href="po.php?id=<?=$poId?>">View PO</a></div>
<div class="card"><table class="table"><thead><tr><th>GRN Number</th><th>Received Date</th><th>Supplier Invoice</th><th>Delivery Note</th><th>Total</th><th>Received By</th><th>Action</th></tr></thead><tbody>
<?php if(!$grns): ?><tr><td colspan="7" style="text-align:center;padding:35px;color:#64748b">No goods receipts have been posted for this PO.</td></tr>
<?php else: foreach($grns as $g): ?><tr><td><strong><?=e($g['grn_number'])?></strong></td><td><?=e(date('d M Y H:i',strtotime($g['received_date'])))?></td><td><?=e($g['supplier_invoice']?:'—')?></td><td><?=e($g['delivery_note']?:'—')?></td><td>KES <?=number_format((float)$g['total_amount'],2)?></td><td><?=e($g['full_name'])?></td><td><a class="btn primary" target="_blank" href="grn.php?id=<?=$g['id']?>">View / Print</a></td></tr><?php endforeach; endif; ?>
</tbody></table></div>
</div>
</main>
<?php require_once __DIR__.'/../../includes/footer.php';