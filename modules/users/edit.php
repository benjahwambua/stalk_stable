<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../config/db.php';

if (!isSuperUser()) { http_response_code(403); exit('Access denied.'); }

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT id, full_name, username, role, status, is_super FROM users WHERE id=?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user || (int)$user['is_super'] === 1) {
    header('Location: index.php');
    exit;
}

require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/sidebar.php';
?>
<main class="content">
<style>
.panel{background:#fff;padding:22px;border-radius:12px;box-shadow:0 1px 4px #0001;max-width:700px}
.field{margin:14px 0}.field label{display:block;font-size:12px;font-weight:700;margin-bottom:5px}
.field input,.field select{width:100%;box-sizing:border-box;padding:10px;border:1px solid #dbe3ec;border-radius:7px}
.btn{background:#004a99;color:#fff;border:0;padding:10px 15px;border-radius:7px;font-weight:700}
</style>
<h1>Manage User</h1>
<p>Update this user's name, role, status or password.</p>
<div class="panel">
<form method="post" action="store.php">
<input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
<input type="hidden" name="action" value="update">
<input type="hidden" name="id" value="<?=e($user['id'])?>">
<div class="field"><label>Full Name</label><input name="full_name" value="<?=e($user['full_name'])?>" required></div>
<div class="field"><label>Username</label><input value="<?=e($user['username'])?>" readonly></div>
<div class="field"><label>Role</label><select name="role"><?php foreach(['Admin','Manager','Sales','Cashier','Storekeeper','Driver','Staff'] as $r):?><option value="<?=e($r)?>" <?=$user['role']===$r?'selected':''?>><?=e($r)?></option><?php endforeach;?></select></div>
<div class="field"><label>Status</label><select name="status"><option value="Active" <?=$user['status']==='Active'?'selected':''?>>Active</option><option value="Inactive" <?=$user['status']==='Inactive'?'selected':''?>>Inactive</option></select></div>
<div class="field"><label>New Password <small>(leave blank to keep current password)</small></label><input type="password" name="password" minlength="8" autocomplete="new-password"></div>
<button class="btn">Save Changes</button>
<a href="index.php" style="margin-left:10px">Cancel</a>
</form>
</div>
</main>
<?php require_once __DIR__.'/../../includes/footer.php'; ?>