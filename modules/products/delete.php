<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';

$id=(int)($_GET['id']??0);
if($id>0){
 try{
  $check=$conn->prepare("SELECT COUNT(*) FROM sale_items WHERE product_id=:id");$check->execute([':id'=>$id]);
  if((int)$check->fetchColumn()>0) throw new RuntimeException('This product has sales history and cannot be deleted. Set it to Inactive instead.');
  $check=$conn->prepare("SELECT COUNT(*) FROM purchase_items WHERE product_id=:id");$check->execute([':id'=>$id]);
  if((int)$check->fetchColumn()>0) throw new RuntimeException('This product has purchase history and cannot be deleted. Set it to Inactive instead.');
  $d=$conn->prepare("DELETE FROM products WHERE id=:id");$d->execute([':id'=>$id]);
  $_SESSION['product_flash']='Product deleted successfully.';
 }catch(Throwable $e){$_SESSION['product_flash']=$e->getMessage();}
}
header('Location:index.php');exit;