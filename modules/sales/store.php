<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/auth.php'; require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../config/mpesa.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: index.php');exit;} verify_csrf();
try{
$cart=json_decode((string)($_POST['cart_json']??''),true); if(!is_array($cart)||!$cart)throw new RuntimeException('Add at least one product.');
$customerId=(int)($_POST['customer_id']??0);$customerId=$customerId>0?$customerId:null;
$method=(string)($_POST['payment_method']??'Cash');$priceListId=(int)($_POST['price_list_id']??0);if($priceListId<=0)$priceListId=(int)($conn->query("SELECT id FROM price_lists WHERE is_default=1 AND status='Active' LIMIT 1")->fetchColumn()?:0);$pl=$conn->prepare("SELECT id FROM price_lists WHERE id=? AND status='Active'");$pl->execute([$priceListId]);if(!$pl->fetchColumn())throw new RuntimeException('Selected price list is not active.');
$mpesaPhone=trim((string)($_POST['mpesa_phone']??''));if(!in_array($method,['Cash','M-Pesa','Bank','Credit','Mixed'],true))throw new RuntimeException('Invalid payment method.');
$discount=max(0,(float)($_POST['discount']??0));$tax=max(0,(float)($_POST['tax']??0));$paid=max(0,(float)($_POST['paid_amount']??0));$notes=trim((string)($_POST['notes']??''));
$conn->beginTransaction();
$customer=null;if($customerId){$s=$conn->prepare("SELECT * FROM customers WHERE id=? AND status='Active' FOR UPDATE");$s->execute([$customerId]);$customer=$s->fetch();if(!$customer)throw new RuntimeException('Customer not found or inactive.');}
$lines=[];$subtotal=0.0;
foreach($cart as $item){$pid=(int)($item['id']??0);$qty=(float)($item['qty']??0);if($pid<=0||$qty<=0||$qty!=round($qty))throw new RuntimeException('Invalid product quantity.');$s=$conn->prepare("SELECT * FROM products WHERE id=? AND status='Active' FOR UPDATE");$s->execute([$pid]);$p=$s->fetch();if(!$p)throw new RuntimeException('A selected product is unavailable.');if((float)$p['stock_quantity']<$qty)throw new RuntimeException('Insufficient stock for '.$p['product_name'].'. Available: '.$p['stock_quantity']);$priceStmt=$conn->prepare("SELECT price FROM product_prices WHERE price_list_id=? AND product_id=? AND min_quantity<=? ORDER BY min_quantity DESC LIMIT 1");$priceStmt->execute([$priceListId,$pid,(int)$qty]);$price=$priceStmt->fetchColumn();if($price===false){$price=$priceListId>0?(float)$p['selling_price']:(float)$p['selling_price'];}else{$price=(float)$price;}if($price<=0)throw new RuntimeException('No valid price is configured for '.$p['product_name'].' in the selected price list.');$line=$price*$qty;$subtotal+=$line;$lines[]=['p'=>$p,'qty'=>$qty,'price'=>$price,'subtotal'=>$line];}
$total=max(0,$subtotal-$discount+$tax);if($paid>$total+0.001)throw new RuntimeException('Amount paid cannot exceed the sale total.');$balance=round($total-$paid,2);
if($method==='Credit'&&$balance<=0)throw new RuntimeException('A credit sale must have an outstanding balance.');
if($method==='M-Pesa'){if(!$customerId)throw new RuntimeException('Select a customer for an M-PESA sale.');if($mpesaPhone==='')throw new RuntimeException('Enter the customer M-PESA phone number.');$paid=0;$balance=$total;}if($balance>0&&!$customer)throw new RuntimeException('Select a customer for any sale with an outstanding balance.');
if($customer&&$balance>0){$available=(float)$customer['credit_limit']-(float)$customer['balance'];if($balance>$available+0.001)throw new RuntimeException('Credit limit exceeded. Available credit: KES '.number_format(max(0,$available),2));}
$prefix=(string)($conn->query("SELECT setting_value FROM settings WHERE setting_key='invoice_prefix' LIMIT 1")->fetchColumn()?:'SS-INV');$invoice=$prefix.'-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(2)));
$uid=(int)$_SESSION['user_id'];$s=$conn->prepare("INSERT INTO sales (invoice_number,customer_id,user_id,sale_date,subtotal,discount,tax,total_amount,paid_amount,balance,payment_method,sale_status,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");$s->execute([$invoice,$customerId,$uid,date('Y-m-d H:i:s'),$subtotal,$discount,$tax,$total,$paid,$balance,$method,'Completed',$notes?:null]);$saleId=(int)$conn->lastInsertId();
$si=$conn->prepare("INSERT INTO sale_items (sale_id,product_id,quantity,price,discount,subtotal,buying_price) VALUES (?,?,?,?,?,?,?)");$up=$conn->prepare("UPDATE products SET stock_quantity=stock_quantity-? WHERE id=?");$sm=$conn->prepare("INSERT INTO stock_movements (product_id,user_id,movement_type,quantity,stock_before,stock_after,reference_type,reference_id,notes,movement_date) VALUES (?,?,?,?,?,?,?,?,?,?)");
foreach($lines as $l){$before=(float)$l['p']['stock_quantity'];$after=$before-$l['qty'];$si->execute([$saleId,$l['p']['id'],$l['qty'],$l['price'],0,$l['subtotal'],$l['p']['buying_price']]);$up->execute([$l['qty'],$l['p']['id']]);$sm->execute([$l['p']['id'],$uid,'Sale',$l['qty'],$before,$after,'sale',$saleId,'Sale '.$invoice,date('Y-m-d H:i:s')]);}
if($customer&&$balance>0){$s=$conn->prepare("UPDATE customers SET balance=balance+? WHERE id=?");$s->execute([$balance,$customerId]);}
if($paid>0){$rp=(string)($conn->query("SELECT setting_value FROM settings WHERE setting_key='receipt_prefix' LIMIT 1")->fetchColumn()?:'SS-RCP');$receipt=$rp.'-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(2)));$s=$conn->prepare("INSERT INTO customer_payments (receipt_number,customer_id,sale_id,user_id,amount,payment_method,payment_date,notes) VALUES (?,?,?,?,?,?,?,?)");if($customerId)$paymentMethod = $method === 'Mixed' ? 'Other' : $method;
$s->execute([$receipt,$customerId,$saleId,$uid,$paid,$paymentMethod,date('Y-m-d H:i:s'),'Payment for '.$invoice.' (Sale method: '.$method.')']);}
if($customerId&&$paid>0&&$balance<=0){$s=$conn->prepare("UPDATE customers SET balance=GREATEST(0,balance) WHERE id=?");$s->execute([$customerId]);}
$log=$conn->prepare("INSERT INTO activity_logs (user_id,action,module,description,ip_address) VALUES (?,?,?,?,?)");$log->execute([$uid,'Create','Sales','Created sale '.$invoice.' for KES '.number_format($total,2),$_SERVER['REMOTE_ADDR']??null]);
$conn->commit();
if($method==='M-Pesa'){
    try{
        $response=mpesaStkPush($total,$mpesaPhone,$invoice,'Payment '.$invoice);
        $tx=$conn->prepare("INSERT INTO mpesa_transactions(transaction_type,direction,phone_number,amount,account_reference,merchant_request_id,checkout_request_id,result_code,result_description,result_status,reference_type,reference_id,raw_response) VALUES('STK Push','Incoming',?,?,?,?,?,?,?,?,?,?,?)");
        $tx->execute([$mpesaPhone,$total,$invoice,$response['MerchantRequestID']??null,$response['CheckoutRequestID']??null,(string)($response['ResponseCode']??''),(string)($response['ResponseDescription']??''),!empty($response['CheckoutRequestID'])?'Pending':'Failed','sale',$saleId,json_encode($response)]);
        $_SESSION['sale_flash']='Sale '.$invoice.' created. M-PESA STK Push sent to '.$mpesaPhone.'. Awaiting payment confirmation.';
    }catch(Throwable $mpesaError){
        $_SESSION['sale_flash']='Sale '.$invoice.' was recorded but the M-PESA request could not be sent: '.$mpesaError->getMessage();
    }
}else{
    $_SESSION['sale_flash']='Sale '.$invoice.' completed successfully. Total KES '.number_format($total,2).'.';
}
header('Location: receipt.php?id='.$saleId);exit;
}catch(Throwable $e){if($conn->inTransaction())$conn->rollBack();$_SESSION['sale_flash']='Sale failed: '.$e->getMessage();header('Location: index.php');exit;}