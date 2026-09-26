<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../config/db.php';

$flash=$_SESSION['price_list_flash']??null;unset($_SESSION['price_list_flash']);
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  verify_csrf();
  $action=(string)($_POST['action']??'');
  if($action==='create'){
   $name=trim((string)($_POST['name']??''));$code=strtolower(trim((string)($_POST['code']??'')));$description=trim((string)($_POST['description']??''));
   if($name===''||$code==='')throw new RuntimeException('Name and code are required.');
   $st=$conn->prepare("INSERT INTO price_lists(name,code,description,status) VALUES(?,?,?,'Active')");$st->execute([$name,$code,$description?:null]);
   $_SESSION['price_list_flash']='Price list created successfully.';
  }elseif($action==='tier'){
   $list=(int)($_POST['price_list_id']??0);$product=(int)($_POST['product_id']??0);$min=max(1,(int)($_POST['min_quantity']??1));$price=(float)($_POST['price']??0);
   if($list<1||$product<1||$price<=0)throw new RuntimeException('Select a price list and product and enter a valid price.');
   $st=$conn->prepare("INSERT INTO product_prices(price_list_id,product_id,min_quantity,price) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE price=VALUES(price)");
   $st->execute([$list,$product,$min,$price]);$_SESSION['price_list_flash']='Price tier saved successfully.';
  }elseif($action==='delete'){
   $id=(int)($_POST['id']??0);$conn->prepare("DELETE FROM product_prices WHERE id=?")->execute([$id]);$_SESSION['price_list_flash']='Price tier removed.';
  }else throw new RuntimeException('Invalid action.');
 }catch(Throwable $e){$_SESSION['price_list_flash']='Price list action failed: '.$e->getMessage();}
 header('Location:index.php');exit;
}
$lists=$conn->query("SELECT pl.*,COUNT(pp.id) tier_count FROM price_lists pl LEFT JOIN product_prices pp ON pp.price_list_id=pl.id GROUP BY pl.id ORDER BY pl.is_default DESC,pl.name")->fetchAll();
$products=$conn->query("SELECT id,product_name,sku,selling_price,wholesale_price FROM products WHERE status='Active' ORDER BY product_name")->fetchAll();
$tiers=$conn->query("SELECT pp.*,pl.name list_name,p.product_name,p.sku FROM product_prices pp JOIN price_lists pl ON pl.id=pp.price_list_id JOIN products p ON p.id=pp.product_id ORDER BY pl.name,p.product_name,pp.min_quantity")->fetchAll();
require_once __DIR__.'/../../includes/header.php';require_once __DIR__.'/../../includes/sidebar.php';
?>
<main class="content"><style>
.head{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;gap:15px}.muted{color:#64748b}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.card{background:#fff;padding:20px;border-radius:12px;box-shadow:0 1px 4px #0001}.card h2{margin-top:0}.form{display:grid;gap:10px}.form label{font-size:12px;font-weight:800;color:#475569}.form input,.form select,.form textarea{width:100%;box-sizing:border-box;margin-top:5px;padding:10px;border:1px solid #dbe3ec;border-radius:8px}.btn{border:0;border-radius:8px;padding:10px 14px;font-weight:800;cursor:pointer;text-decoration:none;display:inline-block}.primary{background:#004a99;color:#fff}.light{background:#eef2f7;color:#334155}.danger{background:#fee2e2;color:#991b1b}.flash{padding:12px;background:#ecfdf5;color:#166534;border-radius:8px;margin-bottom:15px}.table{width:100%;border-collapse:collapse}.table th{background:#004a99;color:#fff;text-align:left;padding:10px;font-size:12px}.table td{padding:10px;border-bottom:1px solid #e5e7eb;font-size:12px}.tag{padding:4px 8px;border-radius:20px;background:#e0f2fe;color:#075985;font-weight:700;font-size:11px}@media(max-width:850px){.grid{grid-template-columns:1fr}}
</style>
<div class="head"><div><h1>Price Lists</h1><p class="muted">Manage normal, wholesale and quantity-based customer pricing.</p></div></div>
<?php if($flash):?><div class="flash"><?=e($flash)?></div><?php endif;?>
<div class="grid">
<div class="card"><h2>Create Price List</h2><form method="post" class="form"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="create"><label>Name<input name="name" placeholder="e.g. Distributor Price" required></label><label>Code<input name="code" placeholder="e.g. distributor" required></label><label>Description<textarea name="description"></textarea></label><button class="btn primary">Create Price List</button></form></div>
<div class="card"><h2>Add / Update Price Tier</h2><form method="post" class="form"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="tier"><label>Price List<select name="price_list_id" required><?php foreach($lists as $l):?><option value="<?=$l['id']?>"><?=e($l['name'])?></option><?php endforeach;?></select></label><label>Product<select name="product_id" required><?php foreach($products as $p):?><option value="<?=$p['id']?>"><?=e($p['product_name'])?><?= $p['sku']?' — '.e($p['sku']):''?></option><?php endforeach;?></select></label><label>Minimum Quantity<input type="number" name="min_quantity" min="1" value="1" required></label><label>Price (KES)<input type="number" name="price" min="0.01" step=".01" required></label><button class="btn primary">Save Price Tier</button></form></div>
</div>
<div class="card" style="margin-top:18px"><h2>Configured Price Tiers</h2><div style="overflow:auto"><table class="table"><thead><tr><th>Price List</th><th>Product</th><th>Minimum Qty</th><th>Price</th><th>Action</th></tr></thead><tbody><?php if(!$tiers):?><tr><td colspan="5" style="text-align:center;padding:30px">No pricing tiers configured.</td></tr><?php else:foreach($tiers as $t):?><tr><td><strong><?=e($t['list_name'])?></strong></td><td><?=e($t['product_name'])?><?= $t['sku']?' <small class="muted">('.e($t['sku']).')</small>':''?></td><td><span class="tag"><?=number_format((int)$t['min_quantity'])?>+</span></td><td><strong>KES <?=number_format((float)$t['price'],2)?></strong></td><td><form method="post" onsubmit="return confirm('Remove this price tier?')"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$t['id']?>"><button class="btn danger">Remove</button></form></td></tr><?php endforeach;endif;?></tbody></table></div></div>
</main>
<?php require_once __DIR__.'/../../includes/footer.php'; ?>