<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location:index.php');exit;}
verify_csrf();
try{
 $supplierId=(int)($_POST['supplier_id']??0);$poDate=(string)($_POST['po_date']??date('Y-m-d'));$expected=trim((string)($_POST['expected_date']??''));$notes=trim((string)($_POST['notes']??''));$items=$_POST['items']??[];
 if($supplierId<=0||!is_array($items)||!$items)throw new RuntimeException('Select a supplier and add at least one item.');
 $clean=[];$subtotal=0;
 foreach($items as $x){$pid=(int)($x['product_id']??0);$qty=(int)($x['quantity']??0);$cost=(float)($x['unit_cost']??0);if($pid<=0||$qty<=0||$cost<0)throw new RuntimeException('Every PO item must have a valid product, quantity and cost.');$clean[]=['product_id'=>$pid,'quantity'=>$qty,'unit_cost'=>$cost];$subtotal+=round($qty*$cost,2);}
 $conn->beginTransaction();
 $s=$conn->prepare("SELECT id FROM suppliers WHERE id=? AND status='Active' FOR UPDATE");$s->execute([$supplierId]);if(!$s->fetch())throw new RuntimeException('Supplier not found or inactive.');
 foreach($clean as $x){$p=$conn->prepare("SELECT id FROM products WHERE id=? AND status='Active' FOR UPDATE");$p->execute([$x['product_id']]);if(!$p->fetch())throw new RuntimeException('A selected product is missing or inactive.');}
 $prefix='SS-PO';$q=$conn->query("SELECT setting_value FROM settings WHERE setting_key='po_prefix' LIMIT 1");$v=$q->fetchColumn();if($v)$prefix=$v;
 $po=$prefix.'-'.date('YmdHis').'-'.random_int(100,999);
 $st=$conn->prepare("INSERT INTO purchase_orders(po_number,supplier_id,user_id,po_date,expected_date,subtotal,total_amount,status,notes) VALUES(?,?,?,?,?,?,?,'Draft',?)");$st->execute([$po,$supplierId,$_SESSION['user_id'],$poDate,$expected?:null,$subtotal,$subtotal,$notes?:null]);$id=(int)$conn->lastInsertId();
 $it=$conn->prepare("INSERT INTO purchase_order_items(purchase_order_id,product_id,ordered_quantity,unit_cost,line_total) VALUES(?,?,?,?,?)");foreach($clean as $x)$it->execute([$id,$x['product_id'],$x['quantity'],$x['unit_cost'],round($x['quantity']*$x['unit_cost'],2)]);
 $conn->commit();$_SESSION['supply_flash']="Purchase Order {$po} created as Draft. Approve it before receiving goods.";
}catch(Throwable $e){if($conn->inTransaction())$conn->rollBack();error_log('PO error: '.$e->getMessage());$_SESSION['supply_flash']='PO could not be created: '.$e->getMessage();}
header('Location:index.php');exit;