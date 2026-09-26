<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../config/db.php';
$pageTitle='Supermarket POS';

$products=$conn->query("SELECT p.id,p.product_name,p.category,p.sku,p.barcode,p.selling_price,p.wholesale_price,p.stock_quantity,p.unit,b.brand_name FROM products p LEFT JOIN brands b ON b.id=p.brand_id WHERE p.status='Active' ORDER BY p.product_name")->fetchAll();
$customers=$conn->query("SELECT id,customer_name,phone,credit_limit,balance,price_list_id FROM customers WHERE status='Active' ORDER BY customer_name")->fetchAll();
$priceLists=$conn->query("SELECT id,name,code FROM price_lists WHERE status='Active' ORDER BY is_default DESC,name")->fetchAll();
$productPrices=$conn->query("SELECT price_list_id,product_id,min_quantity,price FROM product_prices ORDER BY price_list_id,product_id,min_quantity")->fetchAll();
$categories=$conn->query("SELECT DISTINCT category FROM products WHERE status='Active' AND category IS NOT NULL AND category<>'' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$stats=[
'today'=>(float)$conn->query("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE sale_status='Completed' AND DATE(sale_date)=CURDATE()")->fetchColumn(),
'month'=>(float)$conn->query("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE sale_status='Completed' AND YEAR(sale_date)=YEAR(CURDATE()) AND MONTH(sale_date)=MONTH(CURDATE())")->fetchColumn(),
'credit'=>(float)$conn->query("SELECT COALESCE(SUM(balance),0) FROM customers")->fetchColumn()
];
$recent=$conn->query("SELECT s.id,s.invoice_number,s.sale_date,s.total_amount,s.paid_amount,s.balance,s.payment_method,COALESCE(c.customer_name,'Walk-in Customer') customer_name FROM sales s LEFT JOIN customers c ON c.id=s.customer_id WHERE s.sale_status='Completed' ORDER BY s.id DESC LIMIT 10")->fetchAll();
$flash=$_SESSION['sale_flash']??null; unset($_SESSION['sale_flash']);
require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/sidebar.php';
?>
<main class="content">
<style>
.pos-wrap{--blue:#004a99;--green:#047857;--border:#e2e8f0;max-width:1500px;margin:auto}
.pos-title{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}.pos-title h1{margin:0}.muted{color:#64748b}
.kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:14px}.kpi{background:#fff;border:1px solid var(--border);border-radius:10px;padding:13px 16px}.kpi span{display:block;color:#64748b;font-size:11px;text-transform:uppercase}.kpi strong{font-size:21px}
.cashier{display:grid;grid-template-columns:minmax(0,1.65fr) 390px;gap:14px;align-items:start}
.catalog,.checkout,.recent{background:#fff;border:1px solid var(--border);border-radius:10px}.catalog{padding:14px}.checkout{padding:16px;position:sticky;top:75px}
.toolbar{display:grid;grid-template-columns:1fr auto;gap:8px;margin-bottom:10px}.toolbar input,.field input,.field select{width:100%;box-sizing:border-box;padding:10px;border:1px solid #cbd5e1;border-radius:7px;background:#fff}.toolbar input{font-size:15px;padding:12px}
.categories{display:flex;gap:7px;overflow:auto;padding-bottom:10px}.cat{border:1px solid #cbd5e1;background:#f8fafc;border-radius:20px;padding:7px 12px;cursor:pointer;white-space:nowrap;font-size:12px}.cat.active{background:var(--blue);color:#fff;border-color:var(--blue)}
.products{display:grid;grid-template-columns:repeat(4,1fr);gap:9px;max-height:570px;overflow:auto;padding-right:2px}.product{min-height:105px;border:1px solid var(--border);border-radius:9px;padding:11px;background:#fff;cursor:pointer;position:relative}.product:hover{border-color:var(--blue);box-shadow:0 2px 8px #0001}.product.out{opacity:.45;cursor:not-allowed}.product .name{font-weight:800;font-size:13px;line-height:1.25}.product small{display:block;color:#64748b;margin-top:5px}.product .price{font-weight:800;color:var(--blue);margin-top:8px}.badge{position:absolute;right:7px;top:7px;font-size:10px;background:#f1f5f9;padding:3px 6px;border-radius:10px;color:#475569}
.checkout h3{margin:0 0 12px}.field{margin:9px 0}.field label{display:block;font-size:11px;font-weight:800;color:#475569;margin-bottom:4px;text-transform:uppercase}.cart{max-height:330px;overflow:auto;border-top:1px solid var(--border);border-bottom:1px solid var(--border)}.empty{text-align:center;color:#94a3b8;padding:35px 10px;font-size:13px}.cart-row{display:grid;grid-template-columns:1fr 54px 76px 22px;gap:5px;align-items:center;padding:9px 0;border-bottom:1px solid #f1f5f9;font-size:12px}.cart-row input{width:50px;padding:5px;border:1px solid #cbd5e1;border-radius:5px}.remove{border:0;background:none;color:#dc2626;font-size:18px;cursor:pointer}.totals{padding:9px 0}.line{display:flex;justify-content:space-between;margin:6px 0;color:#475569;font-size:13px}.grand{font-size:19px;font-weight:900;color:#0f172a;border-top:1px dashed #cbd5e1;padding-top:9px}.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;border:0;border-radius:7px;padding:10px 13px;font-weight:800;cursor:pointer;text-decoration:none}.primary{background:var(--blue);color:#fff}.success{background:var(--green);color:#fff;width:100%;font-size:15px;padding:13px}.light{background:#eef2f7;color:#334155}.flash{padding:11px;background:#ecfdf5;color:#166534;border-radius:7px;margin-bottom:12px}.recent{margin-top:14px;overflow:auto}.recent h3{padding:0 14px}.table{width:100%;border-collapse:collapse;min-width:750px}.table th{background:var(--blue);color:#fff;text-align:left;padding:9px;font-size:11px}.table td{padding:9px;border-bottom:1px solid #edf2f7;font-size:12px}.print-link{color:var(--blue);font-weight:700;text-decoration:none}
@media(max-width:1150px){.products{grid-template-columns:repeat(3,1fr)}.cashier{grid-template-columns:minmax(0,1fr) 360px}}
@media(max-width:900px){.cashier{grid-template-columns:1fr}.checkout{position:static}.products{grid-template-columns:repeat(3,1fr)}}
@media(max-width:650px){.kpis{grid-template-columns:1fr}.products{grid-template-columns:repeat(2,1fr)}.pos-title{align-items:flex-start;gap:10px;flex-direction:column}}
</style>

<div class="pos-wrap">
<div class="pos-title"><div><h1><i class="fas fa-cash-register"></i> Supermarket POS</h1><div class="muted">Fast checkout • scan/search products • print customer receipt</div></div><a class="btn light" href="<?=BASE_URL?>/modules/customers/index.php"><i class="fas fa-users"></i> Customers</a></div>
<?php if($flash): ?><div class="flash"><?=e($flash)?></div><?php endif; ?>
<div class="kpis"><div class="kpi"><span>Today's Sales</span><strong>KES <?=number_format($stats['today'],2)?></strong></div><div class="kpi"><span>This Month</span><strong>KES <?=number_format($stats['month'],2)?></strong></div><div class="kpi"><span>Customer Credit</span><strong>KES <?=number_format($stats['credit'],2)?></strong></div></div>

<form method="post" action="store.php" id="saleForm">
<input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
<div class="cashier">
<section class="catalog">
<div class="toolbar"><input id="productSearch" autofocus autocomplete="off" placeholder="Scan barcode or search product / SKU..."><button type="button" class="btn light" onclick="clearSearch()"><i class="fas fa-times"></i></button></div>
<div class="categories"><button type="button" class="cat active" data-category="all" onclick="filterCategory('all',this)">All Products</button><?php foreach($categories as $cat): ?><button type="button" class="cat" data-category="<?=e(strtolower($cat))?>" onclick="filterCategory(<?=json_encode(strtolower($cat))?>,this)"><?=e($cat)?></button><?php endforeach; ?></div>
<div id="productGrid" class="products">
<?php foreach($products as $p): $category=strtolower((string)$p['category']); ?>
<div class="product <?=$p['stock_quantity']<=0?'out':''?>" data-id="<?=$p['id']?>" data-category="<?=e($category)?>" data-search="<?=e(strtolower($p['product_name'].' '.$p['sku'].' '.$p['barcode'].' '.$p['brand_name']))?>" onclick="<?=$p['stock_quantity']>0?'addProduct('.(int)$p['id'].')':'void(0)'?>">
<span class="badge"><?=number_format((float)$p['stock_quantity'],0).' '.e($p['unit']?:'units')?></span><div class="name"><?=e($p['product_name'])?></div><small><?=e($p['brand_name']?:'Generic')?> · <?=e($p['sku']?:'No SKU')?></small><div class="price">KES <span class="price-value"><?=number_format((float)$p['selling_price'],2)?></span></div>
</div>
<?php endforeach; ?>
</div>
</section>

<section class="checkout">
<h3><i class="fas fa-shopping-cart"></i> Current Sale</h3>
<div class="field"><label>Price List</label><select name="price_list_id" id="price_list_id"><?php foreach($priceLists as $pl):?><option value="<?=$pl['id']?>" data-code="<?=e($pl['code'])?>"><?=e($pl['name'])?></option><?php endforeach;?></select></div>
<div class="field"><label>Customer</label><select name="customer_id" id="customer_id"><option value="">Walk-in Customer</option><?php foreach($customers as $c): ?><option value="<?=$c['id']?>" data-limit="<?=$c['credit_limit']?>" data-balance="<?=$c['balance']?>" data-price-list="<?=$c['price_list_id']??''?>"><?=e($c['customer_name'])?><?= $c['phone']?' — '.e($c['phone']):'' ?></option><?php endforeach; ?></select></div>
<div id="cart" class="cart"><div class="empty">Add products from the catalog.</div></div>
<input type="hidden" name="cart_json" id="cart_json">
<div class="field"><label>Discount (KES)</label><input type="number" name="discount" id="discount" min="0" step=".01" value="0"></div>
<div class="field"><label>Tax (KES)</label><input type="number" name="tax" id="tax" min="0" step=".01" value="0"></div>
<div class="field"><label>Payment</label><select name="payment_method" id="payment_method"><option>Cash</option><option>M-Pesa</option><option>Bank</option><option>Credit</option><option>Mixed</option></select></div><div class="field" id="mpesaPhoneField" style="display:none"><label>M-PESA Phone</label><input name="mpesa_phone" id="mpesa_phone" placeholder="07XXXXXXXX" maxlength="15"><small class="muted">For M-PESA STK Push. The customer will receive the payment prompt.</small></div>
<div class="field"><label>Amount Paid (KES)</label><input type="number" name="paid_amount" id="paid_amount" min="0" step=".01" value="0"></div>
<div class="totals"><div class="line"><span>Subtotal</span><strong id="subtotal">KES 0.00</strong></div><div class="line"><span>Discount</span><strong id="discountView">KES 0.00</strong></div><div class="line"><span>Tax</span><strong id="taxView">KES 0.00</strong></div><div class="line grand"><span>Total</span><strong id="total">KES 0.00</strong></div><div class="line"><span>Balance</span><strong id="balance">KES 0.00</strong></div></div>
<div class="field"><label>Notes</label><input name="notes" maxlength="500" placeholder="Optional note"></div>
<button class="btn success" type="submit"><i class="fas fa-check-circle"></i> Complete Sale &amp; Print Receipt</button>
</section>
</div>
</form>

<div class="recent"><h3>Recent Sales</h3><table class="table"><thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th>Total</th><th>Paid</th><th>Balance</th><th>Method</th><th>Receipt</th></tr></thead><tbody><?php foreach($recent as $r): ?><tr><td><strong><?=e($r['invoice_number'])?></strong></td><td><?=e($r['sale_date'])?></td><td><?=e($r['customer_name'])?></td><td>KES <?=number_format((float)$r['total_amount'],2)?></td><td>KES <?=number_format((float)$r['paid_amount'],2)?></td><td>KES <?=number_format((float)$r['balance'],2)?></td><td><?=e($r['payment_method'])?></td><td><a class="print-link" target="_blank" href="receipt.php?id=<?=$r['id']?>"><i class="fas fa-print"></i> Print</a></td></tr><?php endforeach; ?></tbody></table></div>
</div>
</main>
<script>
const products=<?=json_encode(array_map(fn($p)=>['id'=>(int)$p['id'],'name'=>$p['product_name'],'sku'=>$p['sku'],'barcode'=>$p['barcode'],'price'=>(float)$p['selling_price'],'normalPrice'=>(float)$p['selling_price'],'wholesalePrice'=>(float)$p['wholesale_price'],'stock'=>(float)$p['stock_quantity'],'unit'=>$p['unit']],$products),JSON_UNESCAPED_UNICODE)?>;
const priceTiers=<?=json_encode(array_map(fn($x)=>['list'=>(int)$x['price_list_id'],'product'=>(int)$x['product_id'],'min'=>(int)$x['min_quantity'],'price'=>(float)$x['price']],$productPrices),JSON_UNESCAPED_UNICODE)?>;
let cart=[];
function selectedPrice(p,qty=1){const listId=Number(document.getElementById('price_list_id').value||0);const tiers=priceTiers.filter(t=>t.list===listId&&t.product===p.id&&t.min<=qty).sort((a,b)=>b.min-a.min);if(tiers.length)return tiers[0].price;return listId===2&&Number(p.wholesalePrice||0)>0?Number(p.wholesalePrice):Number(p.normalPrice||p.price||0);}
function addProduct(id){const p=products.find(x=>x.id===id);if(!p||p.stock<=0)return;const row=cart.find(x=>x.id===id);if(row){if(row.qty<p.stock)row.qty++;row.price=selectedPrice(p,row.qty);}else cart.push({...p,price:selectedPrice(p,1),qty:1});render();}
function render(){const box=document.getElementById('cart');box.innerHTML=cart.length?cart.map((x,i)=>'<div class="cart-row"><div><strong>'+esc(x.name)+'</strong><br><small>KES '+money(x.price)+'</small></div><input type="number" min="1" max="'+x.stock+'" value="'+x.qty+'" onchange="setQty('+i+',this.value)"><span>KES '+money(x.price*x.qty)+'</span><button type="button" class="remove" onclick="removeItem('+i+')">&times;</button></div>').join(''):'<div class="empty">Add products from the catalog.</div>';calc();}
function setQty(i,v){cart[i].qty=Math.max(1,Math.min(Number(v)||1,cart[i].stock));const p=products.find(x=>x.id===cart[i].id);if(p)cart[i].price=selectedPrice(p,cart[i].qty);render()}function removeItem(i){cart.splice(i,1);render()}
function calc(){let sub=cart.reduce((a,x)=>a+x.qty*x.price,0),d=Math.max(0,Number(document.getElementById('discount').value)||0),t=Math.max(0,Number(document.getElementById('tax').value)||0),total=Math.max(0,sub-d+t),paid=Math.max(0,Number(document.getElementById('paid_amount').value)||0);document.getElementById('subtotal').textContent='KES '+money(sub);document.getElementById('discountView').textContent='KES '+money(d);document.getElementById('taxView').textContent='KES '+money(t);document.getElementById('total').textContent='KES '+money(total);document.getElementById('balance').textContent='KES '+money(Math.max(0,total-paid));document.getElementById('cart_json').value=JSON.stringify(cart.map(x=>({id:x.id,qty:x.qty,price:x.price})));}
function filterCategory(cat,btn){document.querySelectorAll('.cat').forEach(x=>x.classList.remove('active'));btn.classList.add('active');document.querySelectorAll('.product').forEach(x=>x.style.display=(cat==='all'||x.dataset.category===cat)?'block':'none');}
function updateCatalogPrices(){document.querySelectorAll('.product').forEach(card=>{const p=products.find(x=>x.id===Number(card.dataset.id));if(p)card.querySelector('.price-value').textContent=money(selectedPrice(p,1));});cart.forEach(x=>{const p=products.find(y=>y.id===x.id);if(p)x.price=selectedPrice(p,x.qty);});render();}
function filterProducts(){const q=document.getElementById('productSearch').value.toLowerCase();document.querySelectorAll('.product').forEach(x=>x.style.display=x.dataset.search.includes(q)?'block':'none');}
function clearSearch(){document.getElementById('productSearch').value='';filterProducts();document.getElementById('productSearch').focus();}
function money(n){return Number(n).toLocaleString('en-KE',{minimumFractionDigits:2,maximumFractionDigits:2})}function esc(s){return String(s).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]||m))}
document.getElementById('customer_id').addEventListener('change',()=>{const o=document.querySelector('#customer_id option:checked');const pl=o?.dataset.priceList||'';if(pl&&document.querySelector('#price_list_id option[value="'+pl+'"]')){document.getElementById('price_list_id').value=pl;updateCatalogPrices();}});document.getElementById('productSearch').addEventListener('input',filterProducts);document.getElementById('productSearch').addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();const q=e.target.value.toLowerCase();const p=products.find(x=>x.barcode&&String(x.barcode).toLowerCase()===q)||products.find(x=>x.sku&&String(x.sku).toLowerCase()===q);if(p){addProduct(p.id);clearSearch();}}});document.getElementById('price_list_id').addEventListener('change',updateCatalogPrices);document.getElementById('discount').addEventListener('input',calc);document.getElementById('tax').addEventListener('input',calc);document.getElementById('paid_amount').addEventListener('input',calc);document.getElementById('payment_method').addEventListener('change',()=>{const m=document.getElementById('payment_method').value;document.getElementById('mpesaPhoneField').style.display=m==='M-Pesa'?'block':'none';if(m==='Credit'||m==='M-Pesa')document.getElementById('paid_amount').value=0;calc()});
document.getElementById('saleForm').addEventListener('submit',e=>{if(!cart.length){e.preventDefault();alert('Add at least one product.');return}let total=cart.reduce((a,x)=>a+x.qty*x.price,0)-Number(document.getElementById('discount').value||0)+Number(document.getElementById('tax').value||0),paid=Number(document.getElementById('paid_amount').value||0),method=document.getElementById('payment_method').value;if(paid>total){e.preventDefault();alert('Amount paid cannot exceed the sale total.');return}if((method==='Credit'||method==='M-Pesa')&&!document.getElementById('customer_id').value){e.preventDefault();alert('Select a customer for '+method+' sales.');return}if(method==='M-Pesa'&&!document.getElementById('mpesa_phone').value.trim()){e.preventDefault();alert('Enter the customer M-PESA phone number.');return}});render();
</script>
<?php require_once __DIR__.'/../../includes/footer.php'; ?>