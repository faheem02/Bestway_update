<?php
/**
 * Bestway Distribution - Pharmaceutical Companies & Manufacturers Management
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['admin']);
$page_title = "Pharma Companies / Manufacturers";
require_once __DIR__ . '/../../includes/header.php';

$message = "";
$msg_type = "";
$edit_data = null;

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id'])) {
    $del_id = intval($_GET['id']);
    if ($db_connected && $pdo) {
        try {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM products WHERE company_id = ?");
            $chk->execute([$del_id]);
            if ($chk->fetchColumn() > 0) {
                $message = "This company has linked products in the system and cannot be deleted.";
                $msg_type = "warning";
            } else {
                $pdo->prepare("DELETE FROM companies WHERE id = ?")->execute([$del_id]);
                $message = "Company deleted successfully.";
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
        $stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_data = $stmt->fetch();
    }
}

// Handle Form Submission (Add or Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_company'])) {
    $company_id     = !empty($_POST['company_id']) ? intval($_POST['company_id']) : null;
    $name           = trim($_POST['name'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $address        = trim($_POST['address'] ?? '');

    if (empty($name)) {
        $message = "Company ka naam likhna lazmi hai.";
        $msg_type = "danger";
    } else {
        if ($db_connected && $pdo) {
            try {
                if ($company_id) {
                    // Update
                    $stmt = $pdo->prepare("
                        UPDATE companies 
                        SET name = ?, contact_person = ?, phone = ?, email = ?, address = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $contact_person ?: null, $phone ?: null, $email ?: null, $address ?: null, $company_id]);
                    $message = "Company '{$name}' kamiyabi se update ho gayi.";
                    $msg_type = "success";
                    $edit_data = null;
                } else {
                    // Check duplicate name
                    $chk = $pdo->prepare("SELECT id FROM companies WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
                    $chk->execute([$name]);
                    if ($chk->fetch()) {
                        throw new Exception("Yeh company name pehle se registered hai.");
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO companies (name, contact_person, phone, email, address, status)
                        VALUES (?, ?, ?, ?, ?, 'Active')
                    ");
                    $stmt->execute([$name, $contact_person ?: null, $phone ?: null, $email ?: null, $address ?: null]);
                    $message = "Company '{$name}' added successfully.";
                    $msg_type = "success";
                }
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $msg_type = "danger";
            }
        }
    }
}


// Fetch all companies with product count
$companies_list = [];
$total_companies = 0;
$active_suppliers = 0;

if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->query("
            SELECT c.*, COUNT(p.id) as product_count 
            FROM companies c
            LEFT JOIN products p ON p.company_id = c.id
            GROUP BY c.id
            ORDER BY c.name ASC
        ");
        $companies_list = $stmt->fetchAll();
        $total_companies = count($companies_list);
        foreach ($companies_list as $c) {
            if ($c['product_count'] > 0) $active_suppliers++;
        }
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
    .kpi-card-mini {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 14px 18px;
        display: flex;
        align-items: center;
        gap: 14px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    }
    .kpi-icon-sm {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }
    .form-panel-card {
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
    .table-panel-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        overflow: hidden;
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
            <i class="fa-solid fa-industry"></i>
        </div>
        <div>
            <h4 class="fw-bold mb-0 text-dark">Add Companies </h4>
            <p class="text-muted small mb-0">Register medicine manufacturers, representatives, contact info & trade brands</p>
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

<!-- Summary KPIs -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="kpi-card-mini">
            <div class="kpi-icon-sm" style="background:#e0f2fe; color:#0284c7;">
                <i class="fa-solid fa-industry"></i>
            </div>
            <div>
                <span class="text-muted small fw-semibold">Total Manufacturers</span>
                <h4 class="fw-bold mb-0 text-dark"><?= number_format($total_companies) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="kpi-card-mini">
            <div class="kpi-icon-sm" style="background:#dcfce7; color:#16a34a;">
                <i class="fa-solid fa-boxes"></i>
            </div>
            <div>
                <span class="text-muted small fw-semibold">Suppliers with Products</span>
                <h4 class="fw-bold mb-0 text-success"><?= number_format($active_suppliers) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="kpi-card-mini">
            <div class="kpi-icon-sm" style="background:#fef3c7; color:#d97706;">
                <i class="fa-solid fa-pills"></i>
            </div>
            <div>
                <span class="text-muted small fw-semibold">Products Linked</span>
                <h4 class="fw-bold mb-0 text-warning"><?= number_format(array_sum(array_column($companies_list, 'product_count'))) ?></h4>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Left: Add / Edit Form (5 Cols) -->
    <div class="col-lg-5">
        <div class="form-panel-card">
            <div class="panel-header d-flex align-items-center justify-content-between">
                <div class="fw-bold text-dark fs-6">
                    <i class="fa-solid <?= $edit_data ? 'fa-edit text-warning' : 'fa-plus text-primary' ?> me-1"></i>
                    <?= $edit_data ? "Edit Company Partner" : "Register New Company" ?>
                </div>
                <?php if ($edit_data): ?>
                    <a href="add_company.php" class="btn btn-sm btn-outline-secondary py-0 px-2 rounded">Cancel Edit</a>
                <?php endif; ?>
            </div>
            <div class="p-3 p-md-4">
                <form method="POST" action="" class="needs-validation" novalidate>
                    <?php if ($edit_data): ?>
                        <input type="hidden" name="company_id" value="<?= $edit_data['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Company / Manufacturer Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control fw-bold" placeholder="e.g. Getz Pharma, GSK, Abbott, Searle" value="<?= htmlspecialchars($edit_data['name'] ?? '') ?>" required autofocus>
                        <div class="invalid-feedback">Company name likhna zaroori hai.</div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-dark">Representative / Contact</label>
                            <input type="text" name="contact_person" class="form-control" placeholder="e.g. Tariq Mehmood" value="<?= htmlspecialchars($edit_data['contact_person'] ?? '') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-dark">Phone / WhatsApp</label>
                            <input type="text" name="phone" class="form-control" placeholder="0300-1234567" value="<?= htmlspecialchars($edit_data['phone'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="info@company.com" value="<?= htmlspecialchars($edit_data['email'] ?? '') ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-dark">Office / Warehouse Address</label>
                        <textarea name="address" rows="2" class="form-control" placeholder="Complete address or location"><?= htmlspecialchars($edit_data['address'] ?? '') ?></textarea>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" name="save_company" class="btn btn-primary fw-bold py-2 shadow-sm rounded-3">
                            <i class="fa-solid fa-save me-1"></i> <?= $edit_data ? "Update Company" : "Save Company" ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right: Registered Companies Table (7 Cols) -->
    <div class="col-lg-7">
        <div class="table-panel-card">
            <div class="panel-header d-flex align-items-center justify-content-between">
                <div class="fw-bold text-dark fs-6">
                    <i class="fa-solid fa-list text-teal me-1" style="color: #0d9488;"></i> Registered Manufacturers
                </div>
                <span class="badge bg-light text-dark border px-2 py-1"><?= count($companies_list) ?> Total</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-uppercase text-muted small" style="font-size: 0.76rem;">
                        <tr>
                            <th>Company / Manufacturer</th>
                            <th>Representative / Contact</th>
                            <th>Address / Location</th>
                            <th class="text-center">Products</th>
                            <th class="text-center" style="width: 80px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($companies_list)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-industry fs-2 text-secondary opacity-50 mb-2 d-block"></i>
                                    <span>No companies registered yet. Add one using the form on the left.</span>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($companies_list as $comp): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($comp['name']) ?></div>
                                        <?php if (!empty($comp['email'])): ?>
                                            <div class="text-muted small"><i class="fa-regular fa-envelope me-1"></i><?= htmlspecialchars($comp['email']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-medium text-dark small"><?= htmlspecialchars($comp['contact_person'] ?? 'N/A') ?></div>
                                        <?php if (!empty($comp['phone'])): ?>
                                            <div class="text-primary small"><i class="fa-solid fa-phone me-1"></i><?= htmlspecialchars($comp['phone']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($comp['address'])): ?>
                                            <div class="text-muted small" title="<?= htmlspecialchars($comp['address']) ?>">
                                                <?= htmlspecialchars(mb_strimwidth($comp['address'], 0, 32, '...')) ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="view_product_list.php?company_id=<?= $comp['id'] ?>" class="badge bg-primary-subtle text-primary border border-primary text-decoration-none">
                                            <?= $comp['product_count'] ?> Products
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            <a href="add_company.php?edit=<?= $comp['id'] ?>" class="action-btn-sm" title="Edit Company">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                            <a href="add_company.php?action=delete&id=<?= $comp['id'] ?>" class="action-btn-sm btn-del" title="Delete Company" onclick="return confirm('Are you sure you want to delete company [<?= htmlspecialchars(addslashes($comp['name'])) ?>]?');">
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
