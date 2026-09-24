<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';

$pageTitle = 'Brands';
$errors = [];
$editBrand = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create' || $action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['brand_name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $status = ($_POST['status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active';

            if ($name === '') {
                throw new RuntimeException('Brand name is required.');
            }

            $sql = "SELECT id FROM brands WHERE brand_name = ?" . ($action === 'update' ? " AND id <> ?" : "") . " LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->execute($action === 'update' ? [$name, $id] : [$name]);
            if ($stmt->fetch()) {
                throw new RuntimeException('A brand with this name already exists.');
            }

            if ($action === 'create') {
                $stmt = $conn->prepare("INSERT INTO brands (brand_name, description, status) VALUES (?, ?, ?)");
                $stmt->execute([$name, $description ?: null, $status]);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Brand created successfully.'];
            } else {
                $stmt = $conn->prepare("UPDATE brands SET brand_name = ?, description = ?, status = ? WHERE id = ?");
                $stmt->execute([$name, $description ?: null, $status, $id]);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Brand updated successfully.'];
            }

            header('Location: ' . BASE_URL . '/modules/brands/index.php');
            exit;
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE brand_id = ?");
            $stmt->execute([$id]);
            if ((int)$stmt->fetchColumn() > 0) {
                throw new RuntimeException('This brand cannot be deleted because products are linked to it. Set it to Inactive instead.');
            }

            $stmt = $conn->prepare("DELETE FROM brands WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Brand deleted successfully.'];
            header('Location: ' . BASE_URL . '/modules/brands/index.php');
            exit;
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM brands WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editBrand = $stmt->fetch() ?: null;
}

$search = trim((string)($_GET['search'] ?? ''));
$statusFilter = $_GET['status'] ?? '';
$sql = "SELECT b.*, (SELECT COUNT(*) FROM products p WHERE p.brand_id = b.id) AS product_count FROM brands b WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= " AND (b.brand_name LIKE ? OR b.description LIKE ?)";
    $params[] = "%{$search}%"; $params[] = "%{$search}%";
}
if (in_array($statusFilter, ['Active','Inactive'], true)) {
    $sql .= " AND b.status = ?"; $params[] = $statusFilter;
}
$sql .= " ORDER BY b.brand_name ASC";
$stmt = $conn->prepare($sql); $stmt->execute($params); $brands = $stmt->fetchAll();

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
<div class="master-page">
<div class="page-heading"><div><h1>Brands</h1><p>Maintain the brands available for your product catalogue.</p></div><button class="btn btn-primary" onclick="document.getElementById('brandModal').classList.add('show')"><i class="fas fa-plus"></i> Add Brand</button></div>
<?php if ($flash): ?><div class="alert success"><?= e($flash['message']) ?></div><?php endif; ?>
<?php foreach ($errors as $error): ?><div class="alert danger"><?= e($error) ?></div><?php endforeach; ?>
<div class="toolbar"><form method="get" class="filter-form"><input type="search" name="search" value="<?= e($search) ?>" placeholder="Search brands..."><select name="status"><option value="">All statuses</option><option value="Active" <?= $statusFilter==='Active'?'selected':'' ?>>Active</option><option value="Inactive" <?= $statusFilter==='Inactive'?'selected':'' ?>>Inactive</option></select><button class="btn btn-secondary">Search</button><?php if($search!==''||$statusFilter!==''): ?><a class="btn btn-light" href="<?= BASE_URL ?>/modules/brands/index.php">Clear</a><?php endif; ?></form></div>
<div class="table-card"><div class="table-wrap"><table><thead><tr><th>Brand</th><th>Description</th><th>Products</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php if(!$brands): ?><tr><td colspan="5" class="empty">No brands found.</td></tr><?php else: foreach($brands as $brand): ?>
<tr><td><strong><?= e($brand['brand_name']) ?></strong></td><td><?= e($brand['description'] ?: '—') ?></td><td><span class="count-pill"><?= (int)$brand['product_count'] ?></span></td><td><span class="status <?= strtolower($brand['status']) ?>"><?= e($brand['status']) ?></span></td><td class="actions"><a class="icon-btn" href="?edit=<?= (int)$brand['id'] ?>"><i class="fas fa-edit"></i></a><form method="post" onsubmit="return confirm('Delete this brand?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$brand['id'] ?>"><button class="icon-btn danger-btn" type="submit"><i class="fas fa-trash"></i></button></form></td></tr>
<?php endforeach; endif; ?></tbody></table></div></div></div></main>
<div class="modal <?= $editBrand?'show':'' ?>" id="brandModal"><div class="modal-box"><div class="modal-head"><h2><?= $editBrand?'Edit Brand':'Add Brand' ?></h2><a href="<?= BASE_URL ?>/modules/brands/index.php">&times;</a></div><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="<?= $editBrand?'update':'create' ?>"><?php if($editBrand): ?><input type="hidden" name="id" value="<?= (int)$editBrand['id'] ?>"><?php endif; ?><label>Brand Name *</label><input name="brand_name" required value="<?= e($editBrand['brand_name']??'') ?>" placeholder="e.g. Johnnie Walker"><label>Description</label><textarea name="description" rows="3"><?= e($editBrand['description']??'') ?></textarea><label>Status</label><select name="status"><option value="Active" <?= ($editBrand['status']??'Active')==='Active'?'selected':'' ?>>Active</option><option value="Inactive" <?= ($editBrand['status']??'')==='Inactive'?'selected':'' ?>>Inactive</option></select><div class="modal-actions"><a class="btn btn-light" href="<?= BASE_URL ?>/modules/brands/index.php">Cancel</a><button class="btn btn-primary"><?= $editBrand?'Update Brand':'Save Brand' ?></button></div></form></div></div>
<style>
.master-page{max-width:1400px;margin:0 auto}.page-heading{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:22px}.page-heading h1{font-size:28px}.page-heading p{color:#64748b;margin-top:6px}.toolbar,.table-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 4px 15px rgba(15,23,42,.05)}.toolbar{padding:16px;margin-bottom:18px}.filter-form{display:flex;gap:10px;flex-wrap:wrap}.filter-form input,.filter-form select,.modal-box input,.modal-box select,.modal-box textarea{border:1px solid #cbd5e1;border-radius:8px;padding:11px 12px;background:#fff;font:inherit}.filter-form input{min-width:260px}.btn{border:0;border-radius:8px;padding:10px 15px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:7px}.btn-primary{background:#004a99;color:#fff}.btn-secondary{background:#0f766e;color:#fff}.btn-light{background:#f1f5f9;color:#334155}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse}th,td{padding:14px 16px;border-bottom:1px solid #eef2f7;text-align:left;white-space:nowrap}th{background:#f8fafc;color:#475569;font-size:12px;text-transform:uppercase;letter-spacing:.5px}td{font-size:14px}.status{padding:5px 9px;border-radius:20px;font-size:12px;font-weight:700}.status.active{background:#dcfce7;color:#166534}.status.inactive{background:#fee2e2;color:#991b1b}.count-pill{background:#e0f2fe;color:#075985;padding:5px 9px;border-radius:20px;font-weight:700}.actions{display:flex;gap:6px}.icon-btn{width:34px;height:34px;border:0;border-radius:7px;background:#eff6ff;color:#075985;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}.danger-btn{background:#fef2f2;color:#b91c1c}.alert{padding:12px 15px;border-radius:9px;margin-bottom:15px;font-weight:600}.alert.success{background:#dcfce7;color:#166534}.alert.danger{background:#fee2e2;color:#991b1b}.empty{text-align:center;padding:35px;color:#94a3b8}.modal{display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:2000;align-items:center;justify-content:center;padding:20px}.modal.show{display:flex}.modal-box{background:#fff;width:min(520px,100%);border-radius:16px;padding:24px;box-shadow:0 25px 60px rgba(0,0,0,.2)}.modal-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}.modal-head h2{font-size:20px}.modal-head a{font-size:28px;color:#64748b}.modal-box label{display:block;font-size:13px;font-weight:700;margin:13px 0 6px}.modal-box input,.modal-box select,.modal-box textarea{width:100%}.modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:22px}
@media(max-width:700px){.page-heading{align-items:flex-start;flex-direction:column}.filter-form>*{width:100%}.filter-form input{min-width:0}}
</style>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>