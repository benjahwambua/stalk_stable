<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');
$raw=file_get_contents('php://input')?:'';
$data=json_decode($raw,true);
try{
 $cb=$data['Body']['stkCallback']??null;
 if(!$cb)throw new RuntimeException('Invalid callback payload.');
 $merchant=(string)($cb['MerchantRequestID']??'');$checkout=(string)($cb['CheckoutRequestID']??'');$code=(int)($cb['ResultCode']??-1);$desc=(string)($cb['ResultDesc']??'');
 $status=$code===0?'Success':'Failed';$amount=0;$phone='';$receipt='';$date=null;
 foreach(($cb['CallbackMetadata']['Item']??[]) as $item){$name=$item['Name']??'';$value=$item['Value']??null;if($name==='Amount')$amount=(float)$value;elseif($name==='MpesaReceiptNumber')$receipt=(string)$value;elseif($name==='PhoneNumber')$phone=(string)$value;elseif($name==='TransactionDate'&&$value)$date=DateTime::createFromFormat('YmdHis',(string)$value)?->format('Y-m-d H:i:s');}
 $st=$conn->prepare("INSERT INTO mpesa_transactions(transaction_type,direction,phone_number,amount,transaction_reference,merchant_request_id,checkout_request_id,result_code,result_description,result_status,raw_response,transaction_date) VALUES('STK Push','Incoming',?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE result_code=VALUES(result_code),result_description=VALUES(result_description),result_status=VALUES(result_status),transaction_reference=VALUES(transaction_reference),phone_number=VALUES(phone_number),amount=VALUES(amount),raw_response=VALUES(raw_response),transaction_date=VALUES(transaction_date)");
 $st->execute([$phone,$amount,$receipt,$merchant,$checkout,(string)$code,$desc,$status,$raw,$date]);
 echo json_encode(['ResultCode'=>0,'ResultDesc'=>'Accepted']);
}catch(Throwable $e){error_log('M-PESA callback: '.$e->getMessage());echo json_encode(['ResultCode'=>0,'ResultDesc'=>'Accepted']);}