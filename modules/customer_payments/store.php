<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/auth.php';require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../config/mpesa.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: index.php');exit;}verify_csrf();
try{$cid=(int)($_POST['customer_id']??0);$amount=(float)($_POST['amount']??0);$method=(string)($_POST['payment_method']??'Cash');$ref=trim((string)($_POST['transaction_reference']??''));$phone=trim((string)($_POST['mpesa_phone']??''));$notes=trim((string)($_POST['notes']??''));if($cid<=0||$amount<=0)throw new RuntimeException('Select a customer and enter a valid amount.');if(!in_array($method,['Cash','M-Pesa','Bank','Cheque','Other'],true))throw new RuntimeException('Invalid payment method.');
$conn->beginTransaction();$s=$conn->prepare("SELECT * FROM customers WHERE id=? AND status='Active' FOR UPDATE");$s->execute([$cid]);$c=$s->fetch();if(!$c)throw new RuntimeException('Customer not found.');$balance=(float)$c['balance'];if($balance<=0)throw new RuntimeException('This customer has no outstanding balance.');if($amount>$balance+.001)throw new RuntimeException('Payment cannot exceed outstanding balance of KES '.number_format($balance,2));
$prefix=(string)($conn->query("SELECT setting_value FROM settings WHERE setting_key='receipt_prefix' LIMIT 1")->fetchColumn()?:'SS-RCP');$receipt=$prefix.'-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(2)));$uid=(int)$_SESSION['user_id'];
if($method==='M-Pesa'){
 $conn->commit();
 if($phone==='')throw new RuntimeException('Enter the customer M-PESA phone number.');
 try{$resp=mpesaStkPush($amount,$phone,$receipt,'Credit payment '.$receipt);
   $tx=$conn->prepare("INSERT INTO mpesa_transactions(transaction_type,direction,phone_number,amount,account_reference,merchant_request_id,checkout_request_id,result_code,result_description,result_status,reference_type,reference_id,raw_response) VALUES('STK Push','Incoming',?,?,?,?,?,?,?,?,?,?,?)");
   $tx->execute([$phone,$amount,$receipt,$resp['MerchantRequestID']??null,$resp['CheckoutRequestID']??null,(string)($resp['ResponseCode']??''),(string)($resp['ResponseDescription']??''),!empty($resp['CheckoutRequestID'])?'Pending':'Failed','customer_payment',$cid,json_encode($resp)]);
   $_SESSION['payment_flash']='M-PESA STK Push sent to '.$phone.'. The customer payment will be posted after confirmation.';
 }catch(Throwable $mp){$_SESSION['payment_flash']='M-PESA request failed: '.$mp->getMessage();}
 header('Location: index.php');exit;
}
$s=$conn->prepare("INSERT INTO customer_payments (receipt_number,customer_id,sale_id,user_id,amount,payment_method,transaction_reference,payment_date,notes) VALUES (?,?,?,?,?,?,?,?,?)");$s->execute([$receipt,$cid,null,$uid,$amount,$method,$ref?:null,date('Y-m-d H:i:s'),$notes?:null]);$s=$conn->prepare("UPDATE customers SET balance=balance-? WHERE id=?");$s->execute([$amount,$cid]);$s=$conn->prepare("INSERT INTO activity_logs (user_id,action,module,description,ip_address) VALUES (?,?,?,?,?)");$s->execute([$uid,'Receive','Customer Payments','Received '.$receipt.' of KES '.number_format($amount,2),$_SERVER['REMOTE_ADDR']??null]);$conn->commit();$_SESSION['payment_flash']='Payment '.$receipt.' recorded successfully.';}catch(Throwable $e){if($conn->inTransaction())$conn->rollBack();$_SESSION['payment_flash']='Payment failed: '.$e->getMessage();}header('Location: index.php');exit;