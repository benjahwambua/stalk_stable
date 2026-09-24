<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';

$pageTitle = 'Categories';
$errors = [];
$editCategory = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $name = trim((string)($_POST['category_name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $status = ($_POST['status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active';

            if ($name === '') {
                throw new RuntimeException('Category name is required.');
            }

            $stmt = $conn->prepare("SELECT id FROM categories WHERE category_name = ? LIMIT 1");
            $stmt->execute([$name]);
            if ($stmt->fetch()) {
                throw new RuntimeException('A category with this name already exists.');
            }

            $stmt = $conn->prepare("INSERT INTO categories (category_name, description, status) VALUES (?, ?, ?)");
            $stmt->execute([$name, $description ?: null, $status]);

            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Category created successfully.'];
            header('Location: ' . BASE_URL . '/modules/categories/index.php');
            exit;
        }

        if ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['category_name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $status = ($_POST['status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active';

            if ($id <= 0 || $name === '') {
                throw new RuntimeException('Valid category and category name are required.');
            }

            $stmt = $conn->prepare("SELECT id FROM categories WHERE category_name = ? AND id <> ? LIMIT 1");
            $stmt->execute([$name, $id]);
            if ($stmt->fetch()) {
                throw new RuntimeException('Another category already uses this name.');
            }

            $conn->beginTransaction();

            $stmt = $conn->prepare("SELECT category_name FROM categories WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $old = $stmt->fetch();
            if (!$old) {
                throw new RuntimeException('Category not found.');
            }

            $stmt = $conn->prepare("UPDATE categories SET category_name = ?, description = ?, status = ? WHERE id = ?");
            $stmt->execute([$name, $description ?: null, $status, $id]);

            // Keep the legacy products.category column synchronized with category_id.
            if ($old['category_name'] !== $name) {
                $stmt = $conn->prepare("UPDATE products SET category = ? WHERE category_id = ?");
                $stmt->execute([$name, $id]);
            }

            $conn->commit();

            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Category updated successfully.'];
            header('Location: ' . BASE_URL . '/modules/categories/index.php');
            exit;
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Invalid category.');
            }

            $stmt = $conn->prepare("SELECT category_name FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            $category = $stmt->fetch();
            if (!$category) {
                throw new RuntimeException('Category not found.');
            }

            $stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
            $stmt->execute([$id]);
            if ((int)$stmt->fetchColumn() > 0) {
                throw new RuntimeException('This category cannot be deleted because products are linked to it. Set it to Inactive instead.');
            }

            $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);

            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Category deleted successfully.'];
            header('Location: ' . BASE_URL . '/modules/categories/index.php');
            exit;
        }
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $errors[] = $e->getMessage();
    }
}

if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editCategory = $stmt->fetch() ?: null;
}

$search = trim((string)($_GET['search'] ?? ''));
$statusFilter = $_GET['status'] ?? '';

$sql = "SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
        FROM categories c WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (c.category_name LIKE ? OR c.description LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if (in_array($statusFilter, ['Active', 'Inactive'], true)) {
    $sql .= " AND c.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY c.category_name ASC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
    <div class="master-page">
        <div class="page-heading">
            <div>
                <h1>Categories</h1>
                <p>Manage product categories used throughout inventory and reporting.</p>
            </div>
            <button class="btn btn-primary" onclick="openCategoryModal()"><i class="fas fa-plus"></i> Add Category</button>
        </div>

        <?php if ($flash): ?><div class="alert success"><?= e($flash['message']) ?></div><?php endif; ?>
        <?php foreach ($errors as $error): ?><div class="alert danger"><?= e($error) ?></div><?php endforeach; ?>

        <div class="toolbar">
            <form method="get" class="filter-form">
                <input type="search" name="search" value="<?= e($search) ?>" placeholder="Search categories...">
                <select name="status">
                    <option value="">All statuses</option>
                    <option value="Active" <?= $statusFilter === 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Inactive" <?= $statusFilter === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
                <button class="btn btn-secondary" type="submit"><i class="fas fa-search"></i> Search</button>
                <?php if ($search !== '' || $statusFilter !== ''): ?><a class="btn btn-light" href="<?= BASE_URL ?>/modules/categories/index.php">Clear</a><?php endif; ?>
            </form>
        </div>

        <div class="table-card">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Category</th><th>Description</th><th>Products</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if (!$categories): ?>
                        <tr><td colspan="5" class="empty">No categories found.</td></tr>
                    <?php else: foreach ($categories as $category): ?>
                        <tr>
                            <td><strong><?= e($category['category_name']) ?></strong></td>
                            <td><?= e($category['description'] ?: '—') ?></td>
                            <td><span class="count-pill"><?= (int)$category['product_count'] ?></span></td>
                            <td><span class="status <?= strtolower($category['status']) ?>"><?= e($category['status']) ?></span></td>
                            <td class="actions">
                                <a class="icon-btn" title="Edit" href="?edit=<?= (int)$category['id'] ?>"><i class="fas fa-edit"></i></a>
                                <form method="post" onsubmit="return confirm('Delete this category?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                                    <button class="icon-btn danger-btn" title="Delete" type="submit"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<div class="modal <?= $editCategory ? 'show' : '' ?>" id="categoryModal">
    <div class="modal-box">
        <div class="modal-head"><h2><?= $editCategory ? 'Edit Category' : 'Add Category' ?></h2><a href="<?= BASE_URL ?>/modules/categories/index.php">&times;</a></div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="<?= $editCategory ? 'update' : 'create' ?>">
            <?php if ($editCategory): ?><input type="hidden" name="id" value="<?= (int)$editCategory['id'] ?>"><?php endif; ?>
            <label>Category Name *</label>
            <input type="text" name="category_name" required value="<?= e($editCategory['category_name'] ?? '') ?>" placeholder="e.g. Beer & Malt">
            <label>Description</label>
            <textarea name="description" rows="3" placeholder="Optional description"><?= e($editCategory['description'] ?? '') ?></textarea>
            <label>Status</label>
            <select name="status">
                <option value="Active" <?= ($editCategory['status'] ?? 'Active') === 'Active' ? 'selected' : '' ?>>Active</option>
                <option value="Inactive" <?= ($editCategory['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            <div class="modal-actions"><a class="btn btn-light" href="<?= BASE_URL ?>/modules/categories/index.php">Cancel</a><button class="btn btn-primary" type="submit"><?= $editCategory ? 'Update Category' : 'Save Category' ?></button></div>
        </form>
    </div>
</div>

<style>
.master-page{max-width:1400px;margin:0 auto}.page-heading{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:22px}.page-heading h1{font-size:28px}.page-heading p{color:#64748b;margin-top:6px}.toolbar,.table-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 4px 15px rgba(15,23,42,.05)}.toolbar{padding:16px;margin-bottom:18px}.filter-form{display:flex;gap:10px;flex-wrap:wrap}.filter-form input,.filter-form select,.modal-box input,.modal-box select,.modal-box textarea{border:1px solid #cbd5e1;border-radius:8px;padding:11px 12px;background:#fff;font:inherit}.filter-form input{min-width:260px}.btn{border:0;border-radius:8px;padding:10px 15px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:7px}.btn-primary{background:#004a99;color:#fff}.btn-secondary{background:#0f766e;color:#fff}.btn-light{background:#f1f5f9;color:#334155}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse}th,td{padding:14px 16px;border-bottom:1px solid #eef2f7;text-align:left;white-space:nowrap}th{background:#f8fafc;color:#475569;font-size:12px;text-transform:uppercase;letter-spacing:.5px}td{font-size:14px}.status{padding:5px 9px;border-radius:20px;font-size:12px;font-weight:700}.status.active{background:#dcfce7;color:#166534}.status.inactive{background:#fee2e2;color:#991b1b}.count-pill{background:#e0f2fe;color:#075985;padding:5px 9px;border-radius:20px;font-weight:700}.actions{display:flex;gap:6px}.icon-btn{width:34px;height:34px;border:0;border-radius:7px;background:#eff6ff;color:#075985;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}.danger-btn{background:#fef2f2;color:#b91c1c}.alert{padding:12px 15px;border-radius:9px;margin-bottom:15px;font-weight:600}.alert.success{background:#dcfce7;color:#166534}.alert.danger{background:#fee2e2;color:#991b1b}.empty{text-align:center;padding:35px;color:#94a3b8}.modal{display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:2000;align-items:center;justify-content:center;padding:20px}.modal.show{display:flex}.modal-box{background:#fff;width:min(520px,100%);border-radius:16px;padding:24px;box-shadow:0 25px 60px rgba(0,0,0,.2)}.modal-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}.modal-head h2{font-size:20px}.modal-head a{font-size:28px;color:#64748b}.modal-box label{display:block;font-size:13px;font-weight:700;margin:13px 0 6px}.modal-box input,.modal-box select,.modal-box textarea{width:100%}.modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:22px}
@media(max-width:700px){.page-heading{align-items:flex-start;flex-direction:column}.filter-form>*{width:100%}.filter-form input{min-width:0}}
</style>
<script>
function openCategoryModal(){document.getElementById('categoryModal').classList.add('show');}
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>