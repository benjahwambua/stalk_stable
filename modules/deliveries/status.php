<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/auth.php';require_once __DIR__.'/../../config/db.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: index.php');exit;}verify_csrf();
$id=(int)($_POST['id']??0);$status=(string)($_POST['status']??'');if(!in_array($status,['Pending','Dispatched','Out for Delivery','Delivered','Failed','Cancelled'],true)){$_SESSION['delivery_flash']='Invalid delivery status.';header('Location: index.php');exit;}$s=$conn->prepare("UPDATE deliveries SET status=? WHERE id=?");$s->execute([$status,$id]);$_SESSION['delivery_flash']='Delivery status updated.';header('Location: index.php');exit;