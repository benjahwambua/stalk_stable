<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';

$id=(int)($_GET['id']??0); if($id<1){header('Location:index.php');exit;}
$stmt=$conn->prepare("SELECT * FROM products WHERE id=:id");$stmt->execute([':id'=>$id]);$p=$stmt->fetch();
if(!$p){$_SESSION['product_flash']='Product not found.';header('Location:index.php');exit;}

if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  $name=trim((string)($_POST['product_name']??'')); if($name==='') throw new RuntimeException('Product name is required.');
  $categoryId=(int)($_POST['category_id']??0);$brandId=(int)($_POST['brand_id']??0);$supplierId=(int)($_POST['supplier_id']??0);
  $buy=(float)$_POST['buying_price'];$sell=(float)$_POST['selling_price'];$wholesale=(float)($_POST['wholesale_price']??0);$stock=(int)$_POST['stock_quantity'];$reorder=(int)$_POST['reorder_level'];
  $sku=trim((string)($_POST['sku']??''))?:null;$barcode=trim((string)($_POST['barcode']??''))?:null;$unit=trim((string)($_POST['unit']??'Piece'))?:'Piece';$icon=trim((string)($_POST['icon']??'🍾'))?:'🍾';$status=$_POST['status']==='Inactive'?'Inactive':'Active';
  $categoryName='';if($categoryId){$s=$conn->prepare("SELECT category_name FROM categories WHERE id=:id");$s->execute([':id'=>$categoryId]);$categoryName=(string)($s->fetchColumn()?:'');}
  $u=$conn->prepare("UPDATE products SET product_name=:name,category=:category,category_id=:category_id,brand_id=:brand,supplier_id=:supplier,sku=:sku,barcode=:barcode,unit=:unit,buying_price=:buy,selling_price=:sell,wholesale_price=:wholesale,stock_quantity=:stock,reorder_level=:reorder,icon=:icon,status=:status WHERE id=:id");
  $u->execute([':name'=>$name,':category'=>$categoryName,':category_id'=>$categoryId?:null,':brand'=>$brandId?:null,':supplier'=>$supplierId?:null,':sku'=>$sku,':barcode'=>$barcode,':unit'=>$unit,':buy'=>$buy,':sell'=>$sell,':wholesale'=>$wholesale,':stock'=>$stock,':reorder'=>$reorder,':icon'=>$icon,':status'=>$status,':id'=>$id]);
$sync=$conn->prepare("INSERT INTO product_prices(price_list_id,product_id,min_quantity,price) SELECT id,?,1,? FROM price_lists WHERE code=? ON DUPLICATE KEY UPDATE price=VALUES(price)");
$sync->execute([id,$sell,'normal']);if($wholesale>0){$sync->execute([id,$wholesale,'wholesale']);}else{$del=$conn->prepare("DELETE FROM product_prices WHERE product_id=? AND min_quantity=1 AND price_list_id=(SELECT id FROM price_lists WHERE code='wholesale' LIMIT 1)");$del->execute([id]);}
  $_SESSION['product_flash']='Product updated successfully.';header('Location:index.php');exit;
 }catch(Throwable $e){$error=$e->getMessage();}
}
$categories=$conn->query("SELECT id,category_name FROM categories WHERE status='Active' ORDER BY category_name")->fetchAll();
$brands=$conn->query("SELECT id,brand_name FROM brands WHERE status='Active' ORDER BY brand_name")->fetchAll();
$suppliers=$conn->query("SELECT id,supplier_name FROM suppliers WHERE status='Active' ORDER BY supplier_name")->fetchAll();
$pageTitle='Edit Product';
require_once __DIR__.'/../../includes/header.php';require_once __DIR__.'/../../includes/sidebar.php';
?>
<main class="content">
<div style="max-width:900px;margin:auto"><div style="margin-bottom:20px"><h1>Edit Product</h1><p class="muted">Update product information and inventory settings.</p></div>
<?php if(!empty($error)):?><div style="padding:12px;background:#fee2e2;color:#991b1b;border-radius:8px;margin-bottom:15px"><?=e($error)?></div><?php endif;?>
<form method="post" style="background:#fff;padding:25px;border-radius:14px;box-shadow:0 1px 4px #0001">
<div class="grid"><label>Product Name *<input name="product_name" required value="<?=e($p['product_name'])?>"></label><label>SKU<input name="sku" value="<?=e($p['sku'])?>"></label><label>Barcode<input name="barcode" value="<?=e($p['barcode'])?>"></label>
<label>Category<select name="category_id"><option value="">Select</option><?php foreach($categories as $c):?><option value="<?=$c['id']?>" <?=$p['category_id']==$c['id']?'selected':''?>><?=e($c['category_name'])?></option><?php endforeach;?></select></label>
<label>Brand<select name="brand_id"><option value="">Select</option><?php foreach($brands as $b):?><option value="<?=$b['id']?>" <?=$p['brand_id']==$b['id']?'selected':''?>><?=e($b['brand_name'])?></option><?php endforeach;?></select></label>
<label>Supplier<select name="supplier_id"><option value="">Select</option><?php foreach($suppliers as $s):?><option value="<?=$s['id']?>" <?=$p['supplier_id']==$s['id']?'selected':''?>><?=e($s['supplier_name'])?></option><?php endforeach;?></select></label>
<label>Buying Price<input type="number" step=".01" min="0" name="buying_price" value="<?=e($p['buying_price'])?>" required></label><label>Selling Price<input type="number" step=".01" min="0" name="selling_price" value="<?=e($p['selling_price'])?>" required></label><label>Wholesale Price<input type="number" step=".01" min="0" name="wholesale_price" value="<?=e($p['wholesale_price'])?>"></label><label>Stock Quantity<input type="number" min="0" name="stock_quantity" value="<?=e($p['stock_quantity'])?>" required></label><label>Reorder Level<input type="number" min="0" name="reorder_level" value="<?=e($p['reorder_level'])?>" required></label><label>Unit<input name="unit" value="<?=e($p['unit'])?>"></label><label>Icon<input name="icon" value="<?=e($p['icon'])?>"></label><label>Status<select name="status"><option <?=$p['status']==='Active'?'selected':''?>>Active</option><option <?=$p['status']==='Inactive'?'selected':''?>>Inactive</option></select></label></div>
<div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px"><a class="btn" href="index.php">Cancel</a><button class="btn btn-primary" type="submit">Save Changes</button></div>
</form></div></main>
<style>.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.grid label{font-size:12px;font-weight:700;color:#475569}.grid input,.grid select{display:block;width:100%;box-sizing:border-box;margin-top:6px;padding:11px;border:1px solid #dbe3ec;border-radius:8px}@media(max-width:650px){.grid{grid-template-columns:1fr}}</style>
<?php require_once __DIR__.'/../../includes/footer.php'; ?>