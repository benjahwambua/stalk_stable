<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';

if($_SERVER['REQUEST_METHOD']==='GET'){
 header('Content-Type: application/json');
 try{
  if(isset($_GET['grn_id'])){
   $id=(int)$_GET['grn_id'];
   $q=$conn->prepare("SELECT gr.*,po.po_number,s.supplier_name,u.full_name FROM goods_receipts gr JOIN purchase_orders po ON po.id=gr.purchase_order_id JOIN suppliers s ON s.id=gr.supplier_id JOIN users u ON u.id=gr.user_id WHERE gr.id=?");$q->execute([$id]);$gr=$q->fetch();
   if(!$gr)throw new RuntimeException('GRN not found.');
   $q=$conn->prepare("SELECT gri.*,p.product_name,p.sku FROM goods_receipt_items gri JOIN products p ON p.id=gri.product_id WHERE gri.goods_receipt_id=? ORDER BY gri.id");$q->execute([$id]);
   echo json_encode(['ok'=>true,'grn'=>$gr,'items'=>$q->fetchAll()]);
  }else{
   $id=(int)($_GET['id']??0);
   $st=$conn->prepare("SELECT poi.id,poi.product_id,p.product_name,p.sku,poi.ordered_quantity,poi.received_quantity,poi.unit_cost,(poi.ordered_quantity-poi.received_quantity) outstanding FROM purchase_order_items poi JOIN products p ON p.id=poi.product_id WHERE poi.purchase_order_id=? AND poi.ordered_quantity>poi.received_quantity ORDER BY poi.id");$st->execute([$id]);
   echo json_encode(['ok'=>true,'items'=>$st->fetchAll()]);
  }
 }catch(Throwable $e){http_response_code(404);echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);}exit;
}
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location:index.php');exit;}verify_csrf();
try{
 $poId=(int)($_POST['purchase_order_id']??0);$receivedDate=trim((string)($_POST['received_date']??''));$invoice=trim((string)($_POST['supplier_invoice']??''));$delivery=trim((string)($_POST['delivery_note']??''));$notes=trim((string)($_POST['notes']??''));$items=$_POST['items']??[];
 if($poId<=0||!is_array($items))throw new RuntimeException('Invalid goods receipt.');
 $receivedDate=$receivedDate?date('Y-m-d H:i:s',strtotime($receivedDate)):date('Y-m-d H:i:s');
 $conn->beginTransaction();
 $poSt=$conn->prepare("SELECT po.*,s.supplier_name FROM purchase_orders po JOIN suppliers s ON s.id=po.supplier_id WHERE po.id=? FOR UPDATE");$poSt->execute([$poId]);$po=$poSt->fetch();
 if(!$po||!in_array($po['status'],['Approved','Partially Received'],true))throw new RuntimeException('This PO is not available for receiving.');
 $clean=[];$total=0;
 foreach($items as $x){$poiId=(int)($x['poi_id']??0);$qty=(int)($x['quantity']??0);$cost=(float)($x['unit_cost']??0);if($qty<=0)continue;$s=$conn->prepare("SELECT poi.*,p.product_name,p.stock_quantity FROM purchase_order_items poi JOIN products p ON p.id=poi.product_id WHERE poi.id=? AND poi.purchase_order_id=? FOR UPDATE");$s->execute([$poiId,$poId]);$poi=$s->fetch();if(!$poi)throw new RuntimeException('Invalid PO item.');$out=(int)$poi['ordered_quantity']-(int)$poi['received_quantity'];if($qty>$out)throw new RuntimeException('Cannot receive more than the outstanding quantity for '.$poi['product_name'].'.');if($cost<0)$cost=(float)$poi['unit_cost'];$clean[]=['poi_id'=>$poiId,'product_id'=>(int)$poi['product_id'],'product_name'=>$poi['product_name'],'qty'=>$qty,'cost'=>$cost,'stock_before'=>(int)$poi['stock_quantity']];$total+=round($qty*$cost,2);}
 if(!$clean)throw new RuntimeException('Enter at least one quantity to receive.');
 $prefix='SS-GRN';$q=$conn->query("SELECT setting_value FROM settings WHERE setting_key='grn_prefix' LIMIT 1");$v=$q->fetchColumn();if($v)$prefix=$v;$grn=$prefix.'-'.date('YmdHis').'-'.random_int(100,999);
 $st=$conn->prepare("INSERT INTO goods_receipts(grn_number,purchase_order_id,supplier_id,user_id,received_date,supplier_invoice,delivery_note,subtotal,total_amount,notes) VALUES(?,?,?,?,?,?,?,?,?,?)");$st->execute([$grn,$poId,$po['supplier_id'],$_SESSION['user_id'],$receivedDate,$invoice?:null,$delivery?:null,$total,$total,$notes?:null]);$grnId=(int)$conn->lastInsertId();
 $it=$conn->prepare("INSERT INTO goods_receipt_items(goods_receipt_id,purchase_order_item_id,product_id,quantity_received,unit_cost,line_total) VALUES(?,?,?,?,?,?)");
 $upPoi=$conn->prepare("UPDATE purchase_order_items SET received_quantity=received_quantity+? WHERE id=?");
 $upProd=$conn->prepare("UPDATE products SET stock_quantity=stock_quantity+?,buying_price=? WHERE id=?");
 $mov=$conn->prepare("INSERT INTO stock_movements(product_id,user_id,movement_type,quantity,stock_before,stock_after,reference_type,reference_id,notes,movement_date) VALUES(?,?,?,?,?,?,?,?,?,?)");
 foreach($clean as $x){$after=$x['stock_before']+$x['qty'];$line=round($x['qty']*$x['cost'],2);$it->execute([$grnId,$x['poi_id'],$x['product_id'],$x['qty'],$x['cost'],$line]);$upPoi->execute([$x['qty'],$x['poi_id']]);$upProd->execute([$x['qty'],$x['cost'],$x['product_id']]);$mov->execute([$x['product_id'],$_SESSION['user_id'],'Purchase',$x['qty'],$x['stock_before'],$after,'goods_receipt',$grnId,'GRN '.$grn.' against PO '.$po['po_number'].' - '.$x['product_name'],$receivedDate]);}
 $remain=(int)$conn->query("SELECT COALESCE(SUM(ordered_quantity-received_quantity),0) FROM purchase_order_items WHERE purchase_order_id=".$poId)->fetchColumn();
 $newStatus=$remain===0?'Fully Received':'Partially Received';$conn->prepare("UPDATE purchase_orders SET status=? WHERE id=?")->execute([$newStatus,$poId]);
 $conn->prepare("UPDATE suppliers SET balance=balance+? WHERE id=?")->execute([$total,$po['supplier_id']]);
 $conn->commit();$_SESSION['supply_flash']="Goods Receipt {$grn} posted. {$total} KES added to supplier payable and stock has been updated.";
}catch(Throwable $e){if($conn->inTransaction())$conn->rollBack();error_log('GRN error: '.$e->getMessage());$_SESSION['supply_flash']='Goods receipt failed: '.$e->getMessage();}
header('Location:index.php');exit;