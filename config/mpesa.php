<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/db.php';

function mpesaSetting(string $key, string $default=''): string {
    global $conn;
    $stmt=$conn->prepare("SELECT setting_value FROM settings WHERE setting_key=? LIMIT 1");
    $stmt->execute([$key]);
    $value=$stmt->fetchColumn();
    return $value===false?$default:(string)$value;
}

function mpesaBaseUrl(): string {
    return mpesaSetting('mpesa_environment','sandbox')==='live'
        ? 'https://api.safaricom.co.ke'
        : 'https://sandbox.safaricom.co.ke';
}

function mpesaAccessToken(): string {
    $key=mpesaSetting('mpesa_consumer_key');$secret=mpesaSetting('mpesa_consumer_secret');
    if($key===''||$secret==='')throw new RuntimeException('M-PESA consumer key and consumer secret are not configured.');
    $ch=curl_init(mpesaBaseUrl().'/oauth/v1/generate?grant_type=client_credentials');
    curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>['Authorization: Basic '.base64_encode($key.':'.$secret)],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30]);
    $body=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
    if($body===false||$code>=400)throw new RuntimeException('M-PESA OAuth failed: '.($err?:$body));
    $data=json_decode($body,true);if(empty($data['access_token']))throw new RuntimeException('M-PESA did not return an access token.');
    return (string)$data['access_token'];
}

function mpesaNormalizePhone(string $phone): string {
    $phone=preg_replace('/\D+/','',$phone)??'';
    if(str_starts_with($phone,'0'))$phone='254'.substr($phone,1);
    if(str_starts_with($phone,'7'))$phone='254'.$phone;
    return $phone;
}

function mpesaStkPush(float $amount,string $phone,string $accountReference,string $description='Stalk & Stable Payment'): array {
    global $conn;
    $shortcode=mpesaSetting('mpesa_shortcode');$passkey=mpesaSetting('mpesa_passkey');$callback=mpesaSetting('mpesa_callback_url');
    if($shortcode===''||$passkey===''||$callback==='')throw new RuntimeException('Configure M-PESA shortcode, passkey and callback URL first.');
    $phone=mpesaNormalizePhone($phone);if(!preg_match('/^2547\d{8}$/',$phone))throw new RuntimeException('Enter a valid Kenyan M-PESA phone number.');
    $timestamp=date('YmdHis');$password=base64_encode($shortcode.$passkey.$timestamp);
    $payload=['BusinessShortCode'=>$shortcode,'Password'=>$password,'Timestamp'=>$timestamp,'TransactionType'=>'CustomerPayBillOnline','Amount'=>(int)round($amount),'PartyA'=>$phone,'PartyB'=>$shortcode,'PhoneNumber'=>$phone,'CallBackURL'=>$callback,'AccountReference'=>substr($accountReference,0,12),'TransactionDesc'=>substr($description,0,20)];
    $ch=curl_init(mpesaBaseUrl().'/mpesa/stkpush/v1/processrequest');curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>['Authorization: Bearer '.mpesaAccessToken(),'Content-Type: application/json'],CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30]);
    $body=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
    if($body===false||$code>=400)throw new RuntimeException('M-PESA STK Push failed: '.($err?:$body));
    $data=json_decode($body,true);if(!is_array($data))throw new RuntimeException('Invalid M-PESA response.');
    return $data;
}