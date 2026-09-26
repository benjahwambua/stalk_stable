<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/auth.php'; require_once __DIR__.'/../../config/db.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: index.php');exit;} verify_csrf();
try{
$name=trim((string)($_POST['customer_name']??''));$priceListId=(int)($_POST['price_list_id']??0);$priceListId=$priceListId>0?$priceListId:null;$phone=trim((string)($_POST['phone']??''));$email=trim((string)($_POST['email']??''));$address=trim((string)($_POST['address']??''));$notes=trim((string)($_POST['notes']??''));$limit=(float)($_POST['credit_limit']??0);
if($name===''||$phone==='')throw new RuntimeException('Customer name and phone are required.'); if($limit<0)throw new RuntimeException('Credit limit cannot be negative.');
$st=$conn->prepare("INSERT INTO customers (customer_name,phone,email,address,credit_limit,price_list_id,balance,status,notes) VALUES (?,?,?,?,?,?,0,'Active',?)");$st->execute([$name,$phone,$email?:null,$address?:null,$limit,$priceListId,$notes?:null]);
$_SESSION['customer_flash']='Customer '.$name.' added successfully.';
}catch(Throwable $e){$_SESSION['customer_flash']='Customer could not be added: '.$e->getMessage();}
header('Location: index.php');exit;