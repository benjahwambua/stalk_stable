<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location:index.php');exit;} verify_csrf();
try{$id=(int)($_POST['id']??0);$st=$conn->prepare("UPDATE purchase_orders SET status='Approved' WHERE id=? AND status='Draft'");$st->execute([$id]);if($st->rowCount()!==1)throw new RuntimeException('Only Draft purchase orders can be approved.');$_SESSION['supply_flash']='Purchase Order approved. Goods can now be received against it.';}catch(Throwable $e){$_SESSION['supply_flash']=$e->getMessage();}header('Location:index.php');exit;