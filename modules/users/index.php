<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../config/db.php';

if (!isSuperUser()) { http_response_code(403); exit('Access denied.'); }

$users = $conn->query("SELECT id, full_name, username, role, is_super, status, last_login FROM users ORDER BY id DESC")->fetchAll();
$flash = $_SESSION['user_flash'] ?? null;
unset($_SESSION['user_flash']);

require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/sidebar.php';
?>
<main class="content">
<style>
.panel{background:#fff;padding:20px;border-radius:12px;box-shadow:0 1px 4px #0001;margin-bottom:18px}
.form{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
.field label{display:block;font-size:12px;font-weight:700;margin-bottom:5px}
.field input,.field select{width:100%;box-sizing:border-box;padding:10px;border:1px solid #dbe3ec;border-radius:7px}
.btn{background:#004a99;color:#fff;border:0;padding:10px 15px;border-radius:7px;font-weight:700;cursor:pointer}
.btn-danger{background:#b42318}.btn-secondary{background:#64748b}.actions{display:flex;gap:6px;flex-wrap:wrap}
.flash{padding:12px;border-radius:8px;margin:12px 0}.success{background:#ecfdf5;color:#166534}.error{background:#fef2f2;color:#991b1b}
.table{width:100%;border-collapse:collapse}.table th{background:#004a99;color:#fff;padding:10px;text-align:left}.table td{padding:10px;border-bottom:1px solid #e5e7eb}
.badge{display:inline-block;padding:4px 8px;border-radius:999px;font-size:11px;font-weight:700}.active{background:#dcfce7;color:#166534}.inactive{background:#fee2e2;color:#991b1b}
@media(max-width:900px){.form{grid-template-columns:1fr}}
</style>

<h1>User Management</h1>
<p>Create and manage staff accounts. Only Super Users can access this module.</p>

<?php if ($flash): ?>
<div class="flash <?=($flash['type'] ?? 'success') === 'error' ? 'error' : 'success'?>"><?=e($flash['message'] ?? '')?></div>
<?php endif; ?>

<div class="panel">
    <h3 style="margin-top:0">Create New User</h3>
    <form method="post" action="store.php" class="form">
        <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
        <input type="hidden" name="action" value="create">

        <div class="field">
            <label>Full Name</label>
            <input name="full_name" required maxlength="150" placeholder="e.g. John Kamau">
        </div>
        <div class="field">
            <label>Password</label>
            <input type="password" name="password" required minlength="8" autocomplete="new-password">
        </div>
        <div class="field">
            <label>Role</label>
            <select name="role">
                <?php foreach (['Admin','Manager','Sales','Cashier','Storekeeper','Driver','Staff'] as $r): ?>
                    <option value="<?=e($r)?>"><?=e($r)?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button class="btn"><i class="fas fa-user-plus"></i> Create User</button>
        </div>
    </form>
    <small style="display:block;margin-top:10px;color:#64748b">
        Username is generated automatically from the user's name. Email and phone are not required.
    </small>
</div>

<div class="panel" style="overflow:auto">
    <h3 style="margin-top:0">Existing Users</h3>
    <table class="table">
        <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Super</th><th>Status</th><th>Last Login</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?=e($u['full_name'])?></td>
                <td><strong><?=e($u['username'])?></strong></td>
                <td><?=e($u['role'])?></td>
                <td><?=!empty($u['is_super']) ? 'Yes' : 'No'?></td>
                <td><span class="badge <?=strtolower($u['status']) === 'active' ? 'active' : 'inactive'?>"><?=e($u['status'])?></span></td>
                <td><?=e($u['last_login'] ?? 'Never')?></td>
                <td>
                    <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                    <div class="actions">
                        <a class="btn btn-secondary" href="edit.php?id=<?=e($u['id'])?>">Manage</a>
                    </div>
                    <?php else: ?>
                        <span style="color:#64748b">Current user</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</main>
<?php require_once __DIR__.'/../../includes/footer.php'; ?>