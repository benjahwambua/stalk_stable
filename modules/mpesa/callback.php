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
 if($code===0){
   $lookup=$conn->prepare("SELECT reference_type,reference_id FROM mpesa_transactions WHERE checkout_request_id=? LIMIT 1");
   $lookup->execute([$checkout]);$tx=$lookup->fetch();
   if($tx && $tx['reference_type']==='sale' && (int)$tx['reference_id']>0){
      $saleId=(int)$tx['reference_id'];
      $conn->beginTransaction();
      $saleQ=$conn->prepare("SELECT * FROM sales WHERE id=? FOR UPDATE");$saleQ->execute([$saleId]);$sale=$saleQ->fetch();
      if($sale && (float)$sale['paid_amount'] < (float)$sale['total_amount']){
         $payAmount=min($amount,(float)$sale['total_amount']-(float)$sale['paid_amount']);
         $upd=$conn->prepare("UPDATE sales SET paid_amount=paid_amount+?, balance=GREATEST(0,total_amount-paid_amount-?) WHERE id=?");
         $upd->execute([$payAmount,$payAmount,$saleId]);
         if($sale['customer_id'] && $payAmount>0){
            $cp=$conn->prepare("UPDATE customers SET balance=GREATEST(0,balance-?) WHERE id=?");$cp->execute([$payAmount,(int)$sale['customer_id']]);
            $exists=$conn->prepare("SELECT COUNT(*) FROM customer_payments WHERE sale_id=? AND transaction_reference=?");$exists->execute([$saleId,$receipt]); 
            if((int)$exists->fetchColumn()===0){
               $prefix=(string)($conn->query("SELECT setting_value FROM settings WHERE setting_key='receipt_prefix' LIMIT 1")->fetchColumn()?:'SS-RCP');
               $rc=$prefix.'-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(2)));
               $ins=$conn->prepare("INSERT INTO customer_payments(receipt_number,customer_id,sale_id,user_id,amount,payment_method,transaction_reference,payment_date,notes) VALUES(?,?,?,?,?,?,?,?,?)");
               $ins->execute([$rc,(int)$sale['customer_id'],$saleId,(int)$sale['user_id'],$payAmount,'M-Pesa',$receipt,date('Y-m-d H:i:s'),'Automatic M-PESA payment for '.$sale['invoice_number']]);
            }
         }
      }
      $conn->commit();
   }
 }
 echo json_encode(['ResultCode'=>0,'ResultDesc'=>'Accepted']);
}catch(Throwable $e){error_log('M-PESA callback: '.$e->getMessage());echo json_encode(['ResultCode'=>0,'ResultDesc'=>'Accepted']);}