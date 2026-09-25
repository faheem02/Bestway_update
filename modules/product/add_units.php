<?php
/**
 * Bestway Distribution - Units of Measurement (UOM) Management
 */
$page_title = "Units of Measurement (UOM)";
require_once __DIR__ . '/../../includes/header.php';

$message = "";
$msg_type = "";
$edit_data = null;

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id'])) {
    $del_id = intval($_GET['id']);
    if ($db_connected && $pdo) {
        try {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM products WHERE unit_id = ?");
            $chk->execute([$del_id]);
            if ($chk->fetchColumn() > 0) {
                $message = "This unit has linked products and cannot be deleted.";
                $msg_type = "warning";
            } else {
                $pdo->prepare("DELETE FROM units WHERE id = ?")->execute([$del_id]);
                $message = "Unit deleted successfully.";
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
        $stmt = $pdo->prepare("SELECT * FROM units WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_data = $stmt->fetch();
    }
}

// Handle Form Submission (Add or Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_unit'])) {
    $unit_id    = !empty($_POST['unit_id']) ? intval($_POST['unit_id']) : null;
    $name       = trim($_POST['name'] ?? '');
    $short_name = trim($_POST['short_name'] ?? '');

    if (empty($name) || empty($short_name)) {
        $message = "Unit Name aur Short Name dono likhna lazmi hain.";
        $msg_type = "danger";
    } else {
        if ($db_connected && $pdo) {
            try {
                if ($unit_id) {
                    $stmt = $pdo->prepare("UPDATE units SET name = ?, short_name = ? WHERE id = ?");
                    $stmt->execute([$name, $short_name, $unit_id]);
                    $message = "Unit '{$name}' kamiyabi se update ho gaya.";
                    $msg_type = "success";
                    $edit_data = null;
                } else {
                    $chk = $pdo->prepare("SELECT id FROM units WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
                    $chk->execute([$name]);
                    if ($chk->fetch()) {
                        throw new Exception("Yeh unit pehle se mojood hai.");
                    }

                    $stmt = $pdo->prepare("INSERT INTO units (name, short_name) VALUES (?, ?)");
                    $stmt->execute([$name, $short_name]);
                    $message = "Unit '{$name}' added successfully.";
                    $msg_type = "success";
                }
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $msg_type = "danger";
            }
        }
    }
}


// Fetch all units
$units_list = [];
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->query("
            SELECT u.*, COUNT(p.id) as product_count 
            FROM units u
            LEFT JOIN products p ON p.unit_id = u.id
            GROUP BY u.id
            ORDER BY u.name ASC
        ");
        $units_list = $stmt->fetchAll();
    } catch (Exception $e) {}
}
?>

<style>
    .page-title-badge {
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
        color: #ffffff;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);
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
        background: #0284c7;
        color: #ffffff;
        border-color: #0284c7;
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
            <i class="fa-solid fa-box"></i>
        </div>
        <div>
            <h4 class="fw-bold mb-0 text-dark">Units of Measurement (UOM)</h4>
            <p class="text-muted small mb-0">Packaging measurement standards (Pack, Box, Strip, Bottle, Ampoule, Tube)</p>
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
    <!-- Left: Add / Edit Unit Form (5 Cols) -->
    <div class="col-lg-5">
        <div class="form-panel-card">
            <div class="panel-header d-flex align-items-center justify-content-between">
                <div class="fw-bold text-dark fs-6">
                    <i class="fa-solid <?= $edit_data ? 'fa-edit text-warning' : 'fa-plus text-primary' ?> me-1"></i>
                    <?= $edit_data ? "Edit Measurement Unit" : "Add New Unit" ?>
                </div>
                <?php if ($edit_data): ?>
                    <a href="add_units.php" class="btn btn-sm btn-outline-secondary py-0 px-2 rounded">Cancel Edit</a>
                <?php endif; ?>
            </div>
            <div class="p-3 p-md-4">
                <form method="POST" action="" class="needs-validation" novalidate>
                    <?php if ($edit_data): ?>
                        <input type="hidden" name="unit_id" value="<?= $edit_data['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Full Unit Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control fw-bold" placeholder="e.g. Pack, Box, Bottle, Strip, Tube" value="<?= htmlspecialchars($edit_data['name'] ?? '') ?>" required autofocus>
                        <div class="invalid-feedback">Unit name likhna zaroori hai.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-dark">Short Code / Abbreviation <span class="text-danger">*</span></label>
                        <input type="text" name="short_name" class="form-control text-uppercase font-monospace" placeholder="e.g. Pac, Box, Btl, Str, Tub" value="<?= htmlspecialchars($edit_data['short_name'] ?? '') ?>" required>
                        <div class="form-text" style="font-size: 0.72rem;">Bills aur invoices par printed short name</div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" name="save_unit" class="btn btn-primary fw-bold py-2 shadow-sm rounded-3">
                            <i class="fa-solid fa-save me-1"></i> <?= $edit_data ? "Update Unit" : "Save Unit" ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right: Units Table (7 Cols) -->
    <div class="col-lg-7">
        <div class="table-panel-card">
            <div class="panel-header d-flex align-items-center justify-content-between">
                <div class="fw-bold text-dark fs-6">
                    <i class="fa-solid fa-boxes text-primary me-1"></i> Active Units
                </div>
                <span class="badge bg-light text-dark border px-2 py-1"><?= count($units_list) ?> Total</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-uppercase text-muted small" style="font-size: 0.76rem;">
                        <tr>
                            <th>Unit Name</th>
                            <th>Short Code</th>
                            <th class="text-center">Products</th>
                            <th class="text-center" style="width: 80px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($units_list)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-box fs-2 text-secondary opacity-50 mb-2 d-block"></i>
                                    <span>No units registered yet.</span>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($units_list as $u): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($u['name']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-primary border font-monospace"><?= htmlspecialchars($u['short_name']) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary-subtle text-primary border border-primary">
                                            <?= $u['product_count'] ?> Products
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            <a href="add_units.php?edit=<?= $u['id'] ?>" class="action-btn-sm" title="Edit Unit">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                            <a href="add_units.php?action=delete&id=<?= $u['id'] ?>" class="action-btn-sm btn-del" title="Delete Unit" onclick="return confirm('Are you sure you want to delete this unit?');">
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
