<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';

$pageTitle = 'Suppliers';
$errors = [];
$editSupplier = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create' || $action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['supplier_name'] ?? ''));
            $contact = trim((string)($_POST['contact_person'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $altPhone = trim((string)($_POST['alternative_phone'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $address = trim((string)($_POST['address'] ?? ''));
            $taxPin = trim((string)($_POST['tax_pin'] ?? ''));
            $creditLimit = max(0, (float)($_POST['credit_limit'] ?? 0));
            $status = ($_POST['status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active';
            $notes = trim((string)($_POST['notes'] ?? ''));

            if ($name === '') {
                throw new RuntimeException('Supplier name is required.');
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Please enter a valid supplier email.');
            }

            $sql = "SELECT id FROM suppliers WHERE supplier_name = ?" . ($action === 'update' ? " AND id <> ?" : "") . " LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->execute($action === 'update' ? [$name, $id] : [$name]);
            if ($stmt->fetch()) {
                throw new RuntimeException('A supplier with this name already exists.');
            }

            if ($action === 'create') {
                $stmt = $conn->prepare("INSERT INTO suppliers (supplier_name, contact_person, phone, email, address, tax_pin, credit_limit, balance, status, notes) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)");
                $stmt->execute([$name,$contact,$phone,$email ?: null,$address,$taxPin,$creditLimit,$status,$notes]);
                $_SESSION['flash'] = ['type'=>'success','message'=>'Supplier created successfully.'];
            } else {
                $stmt = $conn->prepare("UPDATE suppliers SET supplier_name=?, contact_person=?, phone=?, email=?, address=?, tax_pin=?, credit_limit=?, status=?, notes=? WHERE id=?");
                $stmt->execute([$name,$contact,$phone,$altPhone,$email ?: null,$address,$taxPin,$creditLimit,$status,$notes,$id]);
                $_SESSION['flash'] = ['type'=>'success','message'=>'Supplier updated successfully.'];
            }

            header('Location: ' . BASE_URL . '/modules/suppliers/index.php');
            exit;
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);

            $stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE supplier_id = ?");
            $stmt->execute([$id]);
            $productLinks = (int)$stmt->fetchColumn();

            $stmt = $conn->prepare("SELECT COUNT(*) FROM purchases WHERE supplier_id = ?");
            $stmt->execute([$id]);
            $purchaseLinks = (int)$stmt->fetchColumn();

            if ($productLinks > 0 || $purchaseLinks > 0) {
                throw new RuntimeException('This supplier cannot be deleted because it has linked products or purchase records. Set it to Inactive instead.');
            }

            $stmt = $conn->prepare("DELETE FROM suppliers WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['flash'] = ['type'=>'success','message'=>'Supplier deleted successfully.'];
            header('Location: ' . BASE_URL . '/modules/suppliers/index.php');
            exit;
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editSupplier = $stmt->fetch() ?: null;
}

$search = trim((string)($_GET['search'] ?? ''));
$statusFilter = $_GET['status'] ?? '';

$sql = "SELECT s.*, (SELECT COUNT(*) FROM products p WHERE p.supplier_id=s.id) AS product_count, (SELECT COUNT(*) FROM purchases pu WHERE pu.supplier_id=s.id) AS purchase_count FROM suppliers s WHERE 1=1";
$params=[];
if($search!==''){
    $sql.=" AND (s.supplier_name LIKE ? OR s.contact_person LIKE ? OR s.phone LIKE ? OR s.email LIKE ?)";
    foreach([$search,$search,$search,$search] as $v){$params[]="%{$v}%";}
}
if(in_array($statusFilter,['Active','Inactive'],true)){ $sql.=" AND s.status=?"; $params[]=$statusFilter; }
$sql.=" ORDER BY s.supplier_name ASC";
$stmt=$conn->prepare($sql);$stmt->execute($params);$suppliers=$stmt->fetchAll();

$flash=$_SESSION['flash']??null;unset($_SESSION['flash']);
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
<div class="master-page">
<div class="page-heading"><div><h1>Suppliers</h1><p>Manage suppliers, contacts, credit limits and purchasing relationships.</p></div><button class="btn btn-primary" onclick="document.getElementById('supplierModal').classList.add('show')"><i class="fas fa-plus"></i> Add Supplier</button></div>
<?php if($flash): ?><div class="alert success"><?=e($flash['message'])?></div><?php endif; ?>
<?php foreach($errors as $error): ?><div class="alert danger"><?=e($error)?></div><?php endforeach; ?>
<div class="toolbar"><form method="get" class="filter-form"><input type="search" name="search" value="<?=e($search)?>" placeholder="Search supplier, contact, phone..."><select name="status"><option value="">All statuses</option><option value="Active" <?=$statusFilter==='Active'?'selected':''?>>Active</option><option value="Inactive" <?=$statusFilter==='Inactive'?'selected':''?>>Inactive</option></select><button class="btn btn-secondary">Search</button><?php if($search!==''||$statusFilter!==''): ?><a class="btn btn-light" href="<?=BASE_URL?>/modules/suppliers/index.php">Clear</a><?php endif; ?></form></div>
<div class="table-card"><div class="table-wrap"><table><thead><tr><th>Supplier</th><th>Contact</th><th>Phone</th><th>Credit Limit</th><th>Balance</th><th>Linked Products</th><th>Purchases</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php if(!$suppliers): ?><tr><td colspan="9" class="empty">No suppliers found.</td></tr><?php else: foreach($suppliers as $s): ?>
<tr><td><strong><?=e($s['supplier_name'])?></strong><br><small><?=e($s['email']?:'')?></small></td><td><?=e($s['contact_person']?:'—')?></td><td><?=e($s['phone']?:'—')?></td><td>KES <?=number_format((float)$s['credit_limit'],2)?></td><td>KES <?=number_format((float)$s['balance'],2)?></td><td><span class="count-pill"><?= (int)$s['product_count']?></span></td><td><span class="count-pill"><?= (int)$s['purchase_count']?></span></td><td><span class="status <?=strtolower($s['status'])?>"><?=e($s['status'])?></span></td><td class="actions"><a class="icon-btn" href="?edit=<?= (int)$s['id']?>"><i class="fas fa-edit"></i></a><form method="post" onsubmit="return confirm('Delete this supplier?');"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id']?>"><button class="icon-btn danger-btn" type="submit"><i class="fas fa-trash"></i></button></form></td></tr>
<?php endforeach; endif; ?></tbody></table></div></div></div></main>
<div class="modal <?= $editSupplier?'show':'' ?>" id="supplierModal"><div class="modal-box wide"><div class="modal-head"><h2><?=$editSupplier?'Edit Supplier':'Add Supplier'?></h2><a href="<?=BASE_URL?>/modules/suppliers/index.php">&times;</a></div><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="<?=$editSupplier?'update':'create'?>"><?php if($editSupplier): ?><input type="hidden" name="id" value="<?= (int)$editSupplier['id']?>"><?php endif; ?><div class="form-grid"><div><label>Supplier Name *</label><input name="supplier_name" required value="<?=e($editSupplier['supplier_name']??'')?>"></div><div><label>Contact Person</label><input name="contact_person" value="<?=e($editSupplier['contact_person']??'')?>"></div><div><label>Phone</label><input name="phone" value="<?=e($editSupplier['phone']??'')?>"></div><div><label>Email</label><input type="email" name="email" value="<?=e($editSupplier['email']??'')?>"></div><div><label>Tax PIN</label><input name="tax_pin" value="<?=e($editSupplier['tax_pin']??'')?>"></div><div><label>Credit Limit (KES)</label><input type="number" min="0" step="0.01" name="credit_limit" value="<?=e($editSupplier['credit_limit']??'0')?>"></div><div><label>Status</label><select name="status"><option value="Active" <?=($editSupplier['status']??'Active')==='Active'?'selected':''?>>Active</option><option value="Inactive" <?=($editSupplier['status']??'')==='Inactive'?'selected':''?>>Inactive</option></select></div><div class="full"><label>Address</label><textarea name="address" rows="2"><?=e($editSupplier['address']??'')?></textarea></div><div class="full"><label>Notes</label><textarea name="notes" rows="3"><?=e($editSupplier['notes']??'')?></textarea></div></div><div class="modal-actions"><a class="btn btn-light" href="<?=BASE_URL?>/modules/suppliers/index.php">Cancel</a><button class="btn btn-primary">Save Supplier</button></div></form></div></div>
<style>
.master-page{max-width:1500px;margin:0 auto}.page-heading{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:22px}.page-heading h1{font-size:28px}.page-heading p{color:#64748b;margin-top:6px}.toolbar,.table-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 4px 15px rgba(15,23,42,.05)}.toolbar{padding:16px;margin-bottom:18px}.filter-form{display:flex;gap:10px;flex-wrap:wrap}.filter-form input,.filter-form select,.modal-box input,.modal-box select,.modal-box textarea{border:1px solid #cbd5e1;border-radius:8px;padding:11px 12px;background:#fff;font:inherit}.filter-form input{min-width:300px}.btn{border:0;border-radius:8px;padding:10px 15px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:7px}.btn-primary{background:#004a99;color:#fff}.btn-secondary{background:#0f766e;color:#fff}.btn-light{background:#f1f5f9;color:#334155}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse}th,td{padding:13px 14px;border-bottom:1px solid #eef2f7;text-align:left;white-space:nowrap}th{background:#f8fafc;color:#475569;font-size:12px;text-transform:uppercase}td{font-size:13px}.status{padding:5px 9px;border-radius:20px;font-size:12px;font-weight:700}.status.active{background:#dcfce7;color:#166534}.status.inactive{background:#fee2e2;color:#991b1b}.count-pill{background:#e0f2fe;color:#075985;padding:5px 9px;border-radius:20px;font-weight:700}.actions{display:flex;gap:6px}.icon-btn{width:34px;height:34px;border:0;border-radius:7px;background:#eff6ff;color:#075985;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}.danger-btn{background:#fef2f2;color:#b91c1c}.alert{padding:12px 15px;border-radius:9px;margin-bottom:15px;font-weight:600}.alert.success{background:#dcfce7;color:#166534}.alert.danger{background:#fee2e2;color:#991b1b}.empty{text-align:center;padding:35px;color:#94a3b8}.modal{display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:2000;align-items:center;justify-content:center;padding:20px}.modal.show{display:flex}.modal-box{background:#fff;width:min(560px,100%);max-height:90vh;overflow:auto;border-radius:16px;padding:24px;box-shadow:0 25px 60px rgba(0,0,0,.2)}.modal-box.wide{width:min(850px,100%)}.modal-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}.modal-head h2{font-size:20px}.modal-head a{font-size:28px;color:#64748b}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px 16px}.form-grid .full{grid-column:1/-1}.modal-box label{display:block;font-size:13px;font-weight:700;margin:10px 0 6px}.modal-box input,.modal-box select,.modal-box textarea{width:100%}.modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:22px}
@media(max-width:700px){.page-heading{align-items:flex-start;flex-direction:column}.filter-form>*{width:100%}.filter-form input{min-width:0}.form-grid{grid-template-columns:1fr}}
</style>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>