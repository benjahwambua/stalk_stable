<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';

if($_SERVER['REQUEST_METHOD']!=='POST'){ header('Location: index.php'); exit; }

try{
 $name=trim((string)($_POST['product_name']??''));
 if($name==='') throw new RuntimeException('Product name is required.');

 $categoryId=(int)($_POST['category_id']??0);
 $brandId=(int)($_POST['brand_id']??0);
 $supplierId=(int)($_POST['supplier_id']??0);
 $buy=(float)($_POST['buying_price']??0);
 $sell=(float)($_POST['selling_price']??0);
 $wholesale=(float)($_POST['wholesale_price']??0);
 $stock=(int)($_POST['stock_quantity']??0);
 $reorder=(int)($_POST['reorder_level']??10);
 $sku=trim((string)($_POST['sku']??'')) ?: null;
 $barcode=trim((string)($_POST['barcode']??'')) ?: null;
 $unit=trim((string)($_POST['unit']??'Piece')) ?: 'Piece';
 $icon=trim((string)($_POST['icon']??'🍾')) ?: '🍾';

 if($buy<0||$sell<0||$wholesale<0||$stock<0||$reorder<0) throw new RuntimeException('Prices and quantities cannot be negative.');

 $categoryName='';
 if($categoryId){$s=$conn->prepare("SELECT category_name FROM categories WHERE id=:id");$s->execute([':id'=>$categoryId]);$categoryName=(string)($s->fetchColumn()?:'');}

 $stmt=$conn->prepare("INSERT INTO products(product_name,category,category_id,brand_id,supplier_id,sku,barcode,unit,buying_price,selling_price,wholesale_price,stock_quantity,reorder_level,icon,status) VALUES(:name,:category,:category_id,:brand,:supplier,:sku,:barcode,:unit,:buy,:sell,:wholesale,:stock,:reorder,:icon,'Active')");
 $stmt->execute([':name'=>$name,':category'=>$categoryName,':category_id'=>$categoryId?:null,':brand'=>$brandId?:null,':supplier'=>$supplierId?:null,':sku'=>$sku,':barcode'=>$barcode,':unit'=>$unit,':buy'=>$buy,':sell'=>$sell,':wholesale'=>$wholesale,':stock'=>$stock,':reorder'=>$reorder,':icon'=>$icon]);
$productId=(int)$conn->lastInsertId();
$sync=$conn->prepare("INSERT INTO product_prices(price_list_id,product_id,min_quantity,price) SELECT id,?,1,? FROM price_lists WHERE code=? ON DUPLICATE KEY UPDATE price=VALUES(price)");
$sync->execute([productId,$sell,'normal']);if($wholesale>0){$sync->execute([productId,$wholesale,'wholesale']);}else{$del=$conn->prepare("DELETE FROM product_prices WHERE product_id=? AND min_quantity=1 AND price_list_id=(SELECT id FROM price_lists WHERE code='wholesale' LIMIT 1)");$del->execute([productId]);}
 $_SESSION['product_flash']='Product created successfully.';
}catch(Throwable $e){error_log($e->getMessage());$_SESSION['product_flash']='Product could not be saved: '.$e->getMessage();}
header('Location: index.php'); exit;