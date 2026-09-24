<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';

$pageTitle='Supply Chain';
$search=trim((string)($_GET['q']??''));
$status=(string)($_GET['status']??'');

$sql="SELECT po.*,s.supplier_name,u.full_name,
       (SELECT COUNT(*) FROM purchase_order_items i WHERE i.purchase_order_id=po.id) item_count,
       (SELECT COALESCE(SUM(i.ordered_quantity-i.received_quantity),0) FROM purchase_order_items i WHERE i.purchase_order_id=po.id) outstanding_qty
       FROM purchase_orders po
       JOIN suppliers s ON s.id=po.supplier_id
       JOIN users u ON u.id=po.user_id WHERE 1=1";
$params=[];
if($search!==''){ $sql.=" AND (po.po_number LIKE :q OR s.supplier_name LIKE :q)"; $params[':q']="%{$search}%"; }
if(in_array($status,['Draft','Approved','Partially Received','Fully Received','Cancelled'],true)){ $sql.=" AND po.status=:status"; $params[':status']=$status; }
$sql.=" ORDER BY po.id DESC";
$stmt=$conn->prepare($sql);$stmt->execute($params);$orders=$stmt->fetchAll();

$suppliers=$conn->query("SELECT id,supplier_name FROM suppliers WHERE status='Active' ORDER BY supplier_name")->fetchAll();
$products=$conn->query("SELECT id,product_name,sku,buying_price,unit FROM products WHERE status='Active' ORDER BY product_name")->fetchAll();
$flash=$_SESSION['supply_flash']??null;unset($_SESSION['supply_flash']);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
<style>
.wrap{max-width:1500px;margin:auto}.head{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:20px}.head h1{margin:0}.muted{color:#64748b}.btn{display:inline-flex;align-items:center;gap:7px;padding:10px 14px;border:0;border-radius:8px;font-weight:700;text-decoration:none;cursor:pointer}.primary{background:#004a99;color:#fff}.light{background:#eef2f7;color:#334155}.success{background:#047857;color:#fff}.warning{background:#d97706;color:#fff}.danger{background:#b91c1c;color:#fff}.flash{padding:12px 15px;border-radius:9px;background:#ecfdf5;color:#166534;margin-bottom:18px}.toolbar,.card{background:#fff;border-radius:12px;padding:15px;box-shadow:0 1px 5px #0001;margin-bottom:16px}.toolbar{display:flex;gap:10px;flex-wrap:wrap}.toolbar input,.toolbar select,.form input,.form select,.form textarea{padding:10px;border:1px solid #dbe3ec;border-radius:8px}.toolbar input{min-width:280px}.table-wrap{overflow:auto}.table{width:100%;border-collapse:collapse;min-width:1100px}.table th{background:#004a99;color:#fff;padding:12px;text-align:left;font-size:12px}.table td{padding:12px;border-bottom:1px solid #e5e7eb;font-size:13px}.badge{padding:5px 9px;border-radius:20px;font-size:11px;font-weight:800}.draft{background:#fef3c7;color:#92400e}.approved{background:#dbeafe;color:#1d4ed8}.partially-received{background:#fef3c7;color:#92400e}.fully-received{background:#dcfce7;color:#166534}.cancelled{background:#fee2e2;color:#991b1b}.actions{display:flex;gap:6px;flex-wrap:wrap}.modal{display:none;position:fixed;inset:0;background:#0008;z-index:3000;align-items:center;justify-content:center;padding:20px}.modal.show{display:flex}.box{background:#fff;border-radius:15px;padding:24px;width:min(1100px,100%);max-height:92vh;overflow:auto}.form-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.form label{display:block;font-size:12px;font-weight:700;color:#475569}.form input,.form select,.form textarea{width:100%;box-sizing:border-box;margin-top:6px}.full{grid-column:1/-1}.items{margin-top:18px}.items table{width:100%;border-collapse:collapse;min-width:800px}.items th,.items td{padding:8px;border-bottom:1px solid #e5e7eb}.items input,.items select{width:100%;box-sizing:border-box;padding:8px;border:1px solid #dbe3ec;border-radius:6px}.modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:18px}
@media(max-width:800px){.form-grid{grid-template-columns:1fr}.head{align-items:flex-start;flex-direction:column}}
</style>
<div class="wrap">
<div class="head"><div><h1>Supply Chain</h1><p class="muted">Create purchase orders, approve them and receive goods against the exact quantities ordered.</p></div><button class="btn primary" onclick="openCreate()"><i class="fas fa-file-invoice"></i> New Purchase Order</button></div>
<?php if($flash):?><div class="flash"><?=e($flash)?></div><?php endif;?>
<form class="toolbar" method="get"><input name="q" value="<?=e($search)?>" placeholder="Search PO number or supplier..."><select name="status"><option value="">All statuses</option><?php foreach(['Draft','Approved','Partially Received','Fully Received','Cancelled'] as $s):?><option <?=$status===$s?'selected':''?>><?=e($s)?></option><?php endforeach;?></select><button class="btn light">Filter</button><a class="btn light" href="index.php">Reset</a></form>
<div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>PO</th><th>Date</th><th>Supplier</th><th>Total</th><th>Outstanding Qty</th><th>Created By</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php if(!$orders):?><tr><td colspan="8" style="text-align:center;padding:40px;color:#64748b">No purchase orders found.</td></tr><?php else:foreach($orders as $po):$cls=strtolower(str_replace(' ','-',$po['status']));?>
<tr><td><strong><?=e($po['po_number'])?></strong></td><td><?=e(date('d M Y',strtotime($po['po_date'])))?></td><td><?=e($po['supplier_name'])?></td><td>KES <?=number_format((float)$po['total_amount'],2)?></td><td><?=number_format((int)$po['outstanding_qty'])?></td><td><?=e($po['full_name'])?></td><td><span class="badge <?=$cls?>"><?=e($po['status'])?></span></td><td class="actions">
<?php if($po['status']==='Draft'):?><form method="post" action="approve.php"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=$po['id']?>"><button class="btn success">Approve</button></form><?php endif;?>
<?php if(in_array($po['status'],['Approved','Partially Received'],true)):?><button class="btn warning" onclick="openReceive(<?= (int)$po['id']?>)">Receive Goods</button><?php endif;?>
</td></tr>
<?php endforeach;endif;?>
</tbody></table></div></div>
</div>
</main>

<div class="modal" id="createModal"><div class="box"><h2>Create Purchase Order</h2><form class="form" method="post" action="store.php"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><div class="form-grid">
<label>Supplier *<select name="supplier_id" required><option value="">Select supplier</option><?php foreach($suppliers as $s):?><option value="<?=$s['id']?>"><?=e($s['supplier_name'])?></option><?php endforeach;?></select></label>
<label>PO Date *<input type="date" name="po_date" value="<?=date('Y-m-d')?>" required></label>
<label>Expected Date<input type="date" name="expected_date"></label>
<label class="full">Notes<textarea name="notes" rows="2"></textarea></label></div>
<div class="items"><table><thead><tr><th>Product</th><th>Qty</th><th>Unit Cost</th><th>Total</th><th></th></tr></thead><tbody id="poRows"></tbody></table><button type="button" class="btn light" onclick="addRow()">Add Item</button></div>
<div class="modal-actions"><button type="button" class="btn light" onclick="closeModal('createModal')">Cancel</button><button class="btn primary">Create PO</button></div></form></div></div>

<div class="modal" id="receiveModal"><div class="box"><h2>Receive Goods</h2><form class="form" method="post" action="receive.php"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="purchase_order_id" id="receivePoId"><div class="form-grid"><label>Received Date *<input type="datetime-local" name="received_date" value="<?=date('Y-m-d\TH:i')?>" required></label><label>Supplier Invoice<input name="supplier_invoice"></label><label>Delivery Note<input name="delivery_note"></label><label class="full">Notes<textarea name="notes" rows="2"></textarea></label></div><div id="receiveItems" class="items"></div><div class="modal-actions"><button type="button" class="btn light" onclick="closeModal('receiveModal')">Cancel</button><button class="btn warning">Post Goods Receipt</button></div></form></div></div>

<script>
const products=<?=json_encode($products,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
let rn=0;
function esc(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));}
function openCreate(){document.getElementById('createModal').classList.add('show');if(!document.querySelector('#poRows tr'))addRow();}
function closeModal(id){document.getElementById(id).classList.remove('show');}
function addRow(){rn++;let tr=document.createElement('tr');tr.innerHTML='<td><select name="items['+rn+'][product_id]" required>'+('<option value="">Select product</option>'+products.map(p=>'<option value="'+p.id+'">'+esc(p.product_name)+(p.sku?' — '+esc(p.sku):'')+'</option>').join(''))+'</select></td><td><input type="number" min="1" step="1" name="items['+rn+'][quantity]" value="1" required></td><td><input type="number" min="0" step=".01" name="items['+rn+'][unit_cost]" value="0" required></td><td class="line">0.00</td><td><button type="button" class="btn light" onclick="this.closest(\'tr\').remove()">×</button></td>';tr.querySelectorAll('input').forEach(i=>i.addEventListener('input',()=>{let q=+tr.querySelector('[name$="[quantity]"]').value||0,p=+tr.querySelector('[name$="[unit_cost]"]').value||0;tr.querySelector('.line').textContent=(q*p).toFixed(2)}));document.getElementById('poRows').appendChild(tr);}
async function openReceive(id){const box=document.getElementById('receiveItems');box.innerHTML='<p class="muted">Loading PO...</p>';document.getElementById('receivePoId').value=id;document.getElementById('receiveModal').classList.add('show');try{const r=await fetch('receive.php?id='+id);const d=await r.json();if(!d.ok)throw new Error(d.message);let html='<table><thead><tr><th>Product</th><th>Ordered</th><th>Received</th><th>Outstanding</th><th>Receive Now</th><th>Unit Cost</th></tr></thead><tbody>';d.items.forEach((x,i)=>{html+='<tr><td>'+esc(x.product_name)+'</td><td>'+x.ordered_quantity+'</td><td>'+x.received_quantity+'</td><td>'+x.outstanding+'</td><td><input type="number" min="0" max="'+x.outstanding+'" value="'+x.outstanding+'" name="items['+i+'][quantity]"></td><td><input type="number" min="0" step=".01" name="items['+i+'][unit_cost]" value="'+x.unit_cost+'"><input type="hidden" name="items['+i+'][poi_id]" value="'+x.id+'"></td></tr>';});html+='</tbody></table>';box.innerHTML=html;}catch(e){box.innerHTML='<p style="color:#b91c1c">'+esc(e.message)+'</p>';}}
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>