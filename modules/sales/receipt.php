<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../config/db.php';
$id=(int)($_GET['id']??0);
if($id<=0){header('Location: index.php');exit;}
$s=$conn->prepare("SELECT s.*,pl.name price_list_name,COALESCE(c.customer_name,'Walk-in Customer') customer_name,c.phone,c.address,u.full_name cashier FROM sales s LEFT JOIN price_lists pl ON pl.id=s.price_list_id LEFT JOIN customers c ON c.id=s.customer_id LEFT JOIN users u ON u.id=s.user_id WHERE s.id=? AND s.sale_status='Completed'");
$s->execute([$id]);$sale=$s->fetch();
if(!$sale){http_response_code(404);exit('Sale not found.');}
$i=$conn->prepare("SELECT si.quantity,si.price,si.subtotal,p.product_name,p.sku,p.unit FROM sale_items si JOIN products p ON p.id=si.product_id WHERE si.sale_id=? ORDER BY si.id");$i->execute([$id]);$items=$i->fetchAll();
$flash=$_SESSION['sale_flash']??null;unset($_SESSION['sale_flash']);
$company=$conn->query("SELECT setting_value FROM settings WHERE setting_key='company_name' LIMIT 1")->fetchColumn()?:'Stalk & Stable';
$currency=$conn->query("SELECT setting_value FROM settings WHERE setting_key='currency' LIMIT 1")->fetchColumn()?:'KES';
?>
<!doctype html><html><head><meta charset="utf-8"><title>Receipt <?=e($sale['invoice_number'])?></title><style>
body{margin:0;background:#eef2f7;font-family:Arial,sans-serif;color:#111827}.actions{padding:15px;text-align:center}.actions button,.actions a{border:0;background:#004a99;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;cursor:pointer;margin:0 4px;font-weight:700}.actions a{background:#64748b}.receipt{width:80mm;margin:10px auto;background:#fff;padding:6mm;box-sizing:border-box}.center{text-align:center}.company{font-size:19px;font-weight:900}.small{font-size:11px;color:#475569}.line{border-top:1px dashed #111827;margin:9px 0}.meta{font-size:11px;line-height:1.5}.items{width:100%;border-collapse:collapse;font-size:11px}.items th{text-align:left;border-bottom:1px solid #111827;padding-bottom:5px}.items td{padding:4px 0;vertical-align:top}.num{text-align:right}.total{font-size:15px;font-weight:900}.thanks{margin-top:12px;font-size:11px}.flash{background:#ecfdf5;color:#166534;padding:8px;font-size:11px;text-align:center;margin-bottom:8px}@media print{body{background:#fff}.actions{display:none}.receipt{width:80mm;margin:0;padding:4mm;box-shadow:none}@page{size:80mm auto;margin:0}}
</style></head><body>
<div class="actions"><button onclick="window.print()"><i>🖨</i> Print Receipt</button><a href="index.php">Back to POS</a></div>
<div class="receipt">
<?php if($flash): ?><div class="flash"><?=e($flash)?></div><?php endif; ?>
<div class="center"><div class="company"><?=e($company)?></div><div class="small">Alcohol Distribution Management System</div></div>
<div class="line"></div><div class="meta"><strong>Receipt:</strong> <?=e($sale['invoice_number'])?><br><strong>Date:</strong> <?=e($sale['sale_date'])?><br><strong>Cashier:</strong> <?=e($sale['cashier']?:'Staff')?><br><strong>Price List:</strong> <?=e($sale['price_list_name']?:'Standard price')?><br><strong>Customer:</strong> <?=e($sale['customer_name'])?><?php if($sale['phone']): ?> · <?=e($sale['phone'])?><?php endif; ?></div>
<div class="line"></div>
<table class="items"><thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Amount</th></tr></thead><tbody><?php foreach($items as $item): ?><tr><td><?=e($item['product_name'])?></td><td class="num"><?=e($item['quantity'])?></td><td class="num"><?=e($currency)?> <?=number_format((float)$item['subtotal'],2)?></td></tr><?php endforeach; ?></tbody></table>
<div class="line"><div class="meta"><div><span>Subtotal</span><span style="float:right"><?=e($currency)?> <?=number_format((float)$sale['subtotal'],2)?></span></div><div><span>Discount</span><span style="float:right"><?=e($currency)?> <?=number_format((float)$sale['discount'],2)?></span></div><div><span>Tax</span><span style="float:right"><?=e($currency)?> <?=number_format((float)$sale['tax'],2)?></span></div><div class="total"><span>TOTAL</span><span style="float:right"><?=e($currency)?> <?=number_format((float)$sale['total_amount'],2)?></span></div><div><span>Paid</span><span style="float:right"><?=e($currency)?> <?=number_format((float)$sale['paid_amount'],2)?></span></div><div><span>Balance</span><span style="float:right"><?=e($currency)?> <?=number_format((float)$sale['balance'],2)?></span></div><div><span>Payment</span><span style="float:right"><?=e($sale['payment_method'])?></span></div></div></div>
<div class="center thanks">Thank you for your business.<br>Goods sold are subject to company policy.</div>
</div><script>window.addEventListener('load',()=>{if(new URLSearchParams(location.search).get('autoprint')==='1')setTimeout(()=>window.print(),300)});</script></body></html>