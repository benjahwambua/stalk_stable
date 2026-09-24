<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
$pageTitle='M-PESA Integration';
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$keys=['mpesa_environment','mpesa_shortcode','mpesa_passkey','mpesa_consumer_key','mpesa_consumer_secret','mpesa_callback_url','mpesa_account_reference'];foreach($keys as $k){$v=trim((string)($_POST[$k]??''));$st=$conn->prepare("INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");$st->execute([$k,$v]);}$_SESSION['mpesa_flash']='M-PESA settings saved.';header('Location:index.php');exit;}
function s(string $k):string{global $conn;$q=$conn->prepare("SELECT setting_value FROM settings WHERE setting_key=?");$q->execute([$k]);return (string)($q->fetchColumn()??'');}
$flash=$_SESSION['mpesa_flash']??null;unset($_SESSION['mpesa_flash']);
$tx=$conn->query("SELECT * FROM mpesa_transactions ORDER BY id DESC LIMIT 30")->fetchAll();
require_once __DIR__ . '/../../includes/header.php';require_once __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content"><div style="max-width:1200px;margin:auto"><div class="head"><div><h1>M-PESA Integration</h1><p>Central M-PESA configuration and transaction monitoring for sales, customer payments and future supplier/expense payments.</p></div></div>
<?php if($flash):?><div class="flash"><?=e($flash)?></div><?php endif;?>
<div class="card"><h2>Daraja Configuration</h2><p class="muted">Use sandbox while testing. Keep production credentials private and use an HTTPS callback URL.</p><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><div class="grid">
<label>Environment<select name="mpesa_environment"><option value="sandbox" <?=s('mpesa_environment')==='sandbox'?'selected':''?>>Sandbox</option><option value="live" <?=s('mpesa_environment')==='live'?'selected':''?>>Live</option></select></label>
<label>Shortcode<input name="mpesa_shortcode" value="<?=e(s('mpesa_shortcode'))?>"></label>
<label>Passkey<input type="password" name="mpesa_passkey" value="<?=e(s('mpesa_passkey'))?>"></label>
<label>Consumer Key<input name="mpesa_consumer_key" value="<?=e(s('mpesa_consumer_key'))?>"></label>
<label>Consumer Secret<input type="password" name="mpesa_consumer_secret" value="<?=e(s('mpesa_consumer_secret'))?>"></label>
<label>Account Reference<input name="mpesa_account_reference" value="<?=e(s('mpesa_account_reference')?:'STALKSTABLE')?>"></label>
<label class="full">Callback URL<input name="mpesa_callback_url" value="<?=e(s('mpesa_callback_url'))?>" placeholder="https://your-domain.com/stalk_stable/modules/mpesa/callback.php"></label>
</div><button class="btn primary">Save M-PESA Settings</button></form></div>
<div class="card"><h2>Recent M-PESA Transactions</h2><div class="table-wrap"><table><thead><tr><th>Date</th><th>Type</th><th>Phone</th><th>Amount</th><th>Reference</th><th>Status</th></tr></thead><tbody><?php foreach($tx as $t):?><tr><td><?=e($t['created_at'])?></td><td><?=e($t['transaction_type'])?></td><td><?=e($t['phone_number']?:'—')?></td><td>KES <?=number_format((float)$t['amount'],2)?></td><td><?=e($t['transaction_reference']?:$t['checkout_request_id']?:'—')?></td><td><?=e($t['result_status'])?></td></tr><?php endforeach;?></tbody></table></div></div></div></main>
<style>.head{margin-bottom:20px}.muted{color:#64748b}.card{background:#fff;padding:22px;border-radius:14px;box-shadow:0 1px 5px #0001;margin-bottom:18px}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:15px;margin:18px 0}.grid label{font-size:12px;font-weight:700}.grid input,.grid select{display:block;width:100%;box-sizing:border-box;margin-top:6px;padding:11px;border:1px solid #dbe3ec;border-radius:8px}.full{grid-column:1/-1}.btn{padding:11px 16px;border:0;border-radius:8px;font-weight:700;cursor:pointer}.primary{background:#004a99;color:#fff}.flash{padding:12px;background:#ecfdf5;color:#166534;border-radius:8px;margin-bottom:18px}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse}th,td{padding:11px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:13px}th{background:#f8fafc}@media(max-width:850px){.grid{grid-template-columns:1fr}.full{grid-column:auto}}</style>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>