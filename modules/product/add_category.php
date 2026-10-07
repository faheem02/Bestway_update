<?php
/**
 * Bestway Distribution - Medicine Categories & Dosage Forms Management
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['admin']);
$page_title = "Product Categories / Dosage Forms";
require_once __DIR__ . '/../../includes/header.php';

$message = "";
$msg_type = "";
$edit_data = null;

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id'])) {
    $del_id = intval($_GET['id']);
    if ($db_connected && $pdo) {
        try {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
            $chk->execute([$del_id]);
            if ($chk->fetchColumn() > 0) {
                $message = "This category has linked products in the system and cannot be deleted.";
                $msg_type = "warning";
            } else {
                $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$del_id]);
                $message = "Category deleted successfully.";
                $msg_type = "success";
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $msg_type = "danger";
        }
    }
}

// Handle Edit Fetch
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    if ($db_connected && $pdo) {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_data = $stmt->fetch();
    }
}

// Handle Form Submission (Add or Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
    $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $name        = trim($_POST['name'] ?? '');
    $code        = trim($_POST['code'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status      = in_array($_POST['status'] ?? '', ['Active', 'Inactive']) ? $_POST['status'] : 'Active';

    if (empty($name)) {
        $message = "Category name likhna zaroori hai.";
        $msg_type = "danger";
    } else {
        if ($db_connected && $pdo) {
            try {
                if ($category_id) {
                    $stmt = $pdo->prepare("UPDATE categories SET name = ?, code = ?, description = ?, status = ? WHERE id = ?");
                    $stmt->execute([$name, $code ?: null, $description ?: null, $status, $category_id]);
                    $message = "Category '{$name}' kamiyabi se update ho gayi.";
                    $msg_type = "success";
                    $edit_data = null;
                } else {
                    $chk = $pdo->prepare("SELECT id FROM categories WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
                    $chk->execute([$name]);
                    if ($chk->fetch()) {
                        throw new Exception("Yeh category pehle se registered hai.");
                    }

                    $stmt = $pdo->prepare("INSERT INTO categories (name, code, description, status) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $code ?: null, $description ?: null, $status]);
                    $message = "Category '{$name}' added successfully.";
                    $msg_type = "success";
                }
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $msg_type = "danger";
            }
        }
    }
}


// Fetch all categories
$categories_list = [];
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->query("
            SELECT cat.*, COUNT(p.id) as product_count 
            FROM categories cat
            LEFT JOIN products p ON p.category_id = cat.id
            GROUP BY cat.id
            ORDER BY cat.name ASC
        ");
        $categories_list = $stmt->fetchAll();
    } catch (Exception $e) {}
}
?>

<style>
    .page-title-badge {
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, #0d9488 0%, #0284c7 100%);
        color: #ffffff;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        box-shadow: 0 4px 12px rgba(13, 148, 136, 0.25);
    }
    .form-panel-card, .table-panel-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        overflow: hidden;
    }
    .panel-header {
        background: #ffffff;
        border-bottom: 1px solid #f1f5f9;
        padding: 16px 20px;
    }
    .action-btn-sm {
        width: 30px;
        height: 30px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.82rem;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        color: #475569;
        text-decoration: none;
        transition: all 0.15s ease;
    }
    .action-btn-sm:hover {
        background: #0d9488;
        color: #ffffff;
        border-color: #0d9488;
    }
    .action-btn-sm.btn-del:hover {
        background: #dc2626;
        color: #ffffff;
        border-color: #dc2626;
    }
</style>

<!-- Top Title & Navigation -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <div class="page-title-badge">
            <i class="fa-solid fa-tags"></i>
        </div>
        <div>
            <h4 class="fw-bold mb-0 text-dark">Add Categories </h4>
            <p class="text-muted small mb-0">Manage dosage forms (Tablets, Syrups, Capsules, Injections, Drops) & categories</p>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="add_product.php" class="btn btn-primary fw-bold px-3 py-2 shadow-sm rounded-3">
            <i class="fa-solid fa-plus me-1"></i> Add Product
        </a>
        <a href="view_product_list.php" class="btn btn-outline-secondary fw-semibold bg-white shadow-sm px-3 py-2 rounded-3">
            <i class="fa-solid fa-list me-1 text-primary"></i> Product Catalog
        </a>
    </div>
</div>

<!-- Alert Notifications -->
<?php if (!empty($message)): ?>
    <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show border-0 shadow-sm rounded-3 d-flex align-items-center mb-4" role="alert">
        <i class="fa-solid <?= $msg_type === 'success' ? 'fa-check-circle text-success' : 'fa-exclamation-triangle text-danger' ?> fs-4 me-3"></i>
        <div class="fw-semibold"><?= htmlspecialchars($message) ?></div>
        <button type="button" class="close ml-auto" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left: Add / Edit Category Form (5 Cols) -->
    <div class="col-lg-5">
        <div class="form-panel-card">
            <div class="panel-header d-flex align-items-center justify-content-between">
                <div class="fw-bold text-dark fs-6">
                    <i class="fa-solid <?= $edit_data ? 'fa-edit text-warning' : 'fa-plus text-teal' ?> me-1" style="color: #0d9488;"></i>
                    <?= $edit_data ? "Edit Category" : "Add New Dosage Category" ?>
                </div>
                <?php if ($edit_data): ?>
                    <a href="add_category.php" class="btn btn-sm btn-outline-secondary py-0 px-2 rounded">Cancel Edit</a>
                <?php endif; ?>
            </div>
            <div class="p-3 p-md-4">
                <form method="POST" action="" class="needs-validation" novalidate>
                    <?php if ($edit_data): ?>
                        <input type="hidden" name="category_id" value="<?= $edit_data['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Category / Dosage Form Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control fw-bold" placeholder="e.g. Tablets, Inhalers, Infusions" value="<?= htmlspecialchars($edit_data['name'] ?? '') ?>" required autofocus>
                        <div class="invalid-feedback">Category name likhna zaroori hai.</div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-dark">Category Short Code</label>
                            <input type="text" name="code" class="form-control text-uppercase font-monospace" placeholder="e.g. TAB, INH" value="<?= htmlspecialchars($edit_data['code'] ?? '') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-dark">Status</label>
                            <select name="status" class="form-select fw-semibold">
                                <option value="Active" <?= (($edit_data['status'] ?? 'Active') === 'Active') ? 'selected' : '' ?>>Active</option>
                                <option value="Inactive" <?= (($edit_data['status'] ?? '') === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-dark">Description / Remarks</label>
                        <textarea name="description" rows="3" class="form-control" placeholder="Optional details about dosage classification"><?= htmlspecialchars($edit_data['description'] ?? '') ?></textarea>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" name="save_category" class="btn btn-primary fw-bold py-2 shadow-sm rounded-3">
                            <i class="fa-solid fa-save me-1"></i> <?= $edit_data ? "Update Category" : "Save Category" ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right: Categories List Table (7 Cols) -->
    <div class="col-lg-7">
        <div class="table-panel-card">
            <div class="panel-header d-flex align-items-center justify-content-between">
                <div class="fw-bold text-dark fs-6">
                    <i class="fa-solid fa-list-check text-primary me-1"></i> Active Categories
                </div>
                <span class="badge bg-light text-dark border px-2 py-1"><?= count($categories_list) ?> Total</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-uppercase text-muted small" style="font-size: 0.76rem;">
                        <tr>
                            <th>Category Name</th>
                            <th>Code</th>
                            <th class="text-center">Products</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="width: 80px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories_list)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-tags fs-2 text-secondary opacity-50 mb-2 d-block"></i>
                                    <span>No categories registered yet.</span>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categories_list as $cat): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($cat['name']) ?></div>
                                        <?php if (!empty($cat['description'])): ?>
                                            <div class="text-muted small"><?= htmlspecialchars($cat['description']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($cat['code'])): ?>
                                            <span class="badge bg-light text-teal border font-monospace" style="color: #0d9488;"><?= htmlspecialchars($cat['code']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="view_product_list.php?category_id=<?= $cat['id'] ?>" class="badge bg-primary-subtle text-primary border border-primary text-decoration-none">
                                            <?= $cat['product_count'] ?> Products
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= ($cat['status'] === 'Active') ? 'bg-success-subtle text-success border border-success' : 'bg-secondary' ?>">
                                            <?= htmlspecialchars($cat['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            <a href="add_category.php?edit=<?= $cat['id'] ?>" class="action-btn-sm" title="Edit Category">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                            <a href="add_category.php?action=delete&id=<?= $cat['id'] ?>" class="action-btn-sm btn-del" title="Delete Category" onclick="return confirm('Are you sure you want to delete this category?');">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
