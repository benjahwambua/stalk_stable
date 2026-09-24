<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/mpesa.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Method not allowed');}verify_csrf();
try{
 $amount=(float)($_POST['amount']??0);$phone=trim((string)($_POST['phone']??''));$account=trim((string)($_POST['account_reference']??mpesaSetting('mpesa_account_reference','STALKSTABLE')));$type=trim((string)($_POST['reference_type']??'general'));$id=(int)($_POST['reference_id']??0);
 if($amount<=0)throw new RuntimeException('Amount must be greater than zero.');
 $response=mpesaStkPush($amount,$phone,$account);
 $st=$conn->prepare("INSERT INTO mpesa_transactions(transaction_type,direction,phone_number,amount,account_reference,merchant_request_id,checkout_request_id,result_code,result_description,result_status,reference_type,reference_id,raw_response) VALUES('STK Push','Incoming',?,?,?,?,?,?,?,?,?,?,?)");
 $st->execute([$phone,$amount,$account,$response['MerchantRequestID']??null,$response['CheckoutRequestID']??null,(string)($response['ResponseCode']??''),(string)($response['ResponseDescription']??''),!empty($response['CheckoutRequestID'])?'Pending':'Failed',$type,$id?:null,json_encode($response)]);
 $_SESSION['mpesa_flash']=$response['ResponseDescription']??'STK Push sent.';
}catch(Throwable $e){$_SESSION['mpesa_flash']='M-PESA request failed: '.$e->getMessage();}
header('Location:'.BASE_URL.'/modules/mpesa/index.php');exit;