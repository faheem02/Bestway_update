<?php
$page_title = "Edit Supplier / Company";
require_once __DIR__ . '/../../includes/header.php';

$supplier_id = (int)($_GET['id'] ?? 0);
$success_msg = "";
$error_msg = "";

if ($supplier_id <= 0) {
    header("Location: " . BASE_URL . "modules/supplier/suppliers.php");
    exit;
}

// Fetch current supplier details
$supplier = null;
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = :id");
        $stmt->execute(['id' => $supplier_id]);
        $supplier = $stmt->fetch();
    } catch (Exception $e) {}
}

if (!$supplier) {
    header("Location: " . BASE_URL . "modules/supplier/suppliers.php");
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_supplier') {
    $company_name = trim($_POST['company_name'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['Active', 'Inactive']) ? $_POST['status'] : 'Active';

    if (empty($company_name)) {
        $error_msg = "Company / Supplier name is required.";
    } elseif (empty($name)) {
        $error_msg = "Contact person name is required.";
    } elseif (empty($phone)) {
        $error_msg = "Contact phone number is required.";
    } else {
        if ($db_connected && $pdo) {
            try {
                $sql = "UPDATE suppliers SET 
                            name = :name,
                            company_name = :company_name,
                            phone = :phone,
                            address = :address,
                            status = :status
                        WHERE id = :id";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'name' => $name,
                    'company_name' => $company_name,
                    'phone' => $phone,
                    'address' => $address,
                    'status' => $status,
                    'id' => $supplier_id
                ]);

                $success_msg = "Supplier details updated successfully!";
                
                // Re-fetch supplier
                $stmt_ref = $pdo->prepare("SELECT * FROM suppliers WHERE id = :id");
                $stmt_ref->execute(['id' => $supplier_id]);
                $supplier = $stmt_ref->fetch();
            } catch (Exception $e) {
                $error_msg = "Error updating supplier: " . $e->getMessage();
            }
        }
    }
}

?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-edit text-primary me-2"></i>Edit Supplier Details</h4>
        <p class="text-muted small mb-0">Update company contact, address, and credit settings.</p>
    </div>
    <div>
        <a href="<?php echo BASE_URL; ?>modules/supplier/suppliers.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to List
        </a>
    </div>
</div>

<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-check-circle me-2"></i><?php echo $success_msg; ?>
        <a href="<?php echo BASE_URL; ?>modules/supplier/suppliers.php" class="btn btn-sm btn-success ms-3">View All Suppliers</a>
        <button type="button" class="close ml-auto" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
<?php endif; ?>

<?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-exclamation-triangle me-2"></i><?php echo $error_msg; ?>
        <button type="button" class="close ml-auto" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
<?php endif; ?>

<form action="" method="POST">
    <input type="hidden" name="action" value="update_supplier">

    <div class="row justify-content-center">
        <div class="col-12 col-lg-10 col-xl-9">
            <div class="custom-card shadow-sm border-0 mb-4">
                <div class="custom-card-header bg-light border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="custom-card-title mb-0 fs-6 fw-bold text-dark">
                        <i class="fa-solid fa-building text-primary me-2"></i> Company Information
                    </h5>
                    <span class="badge bg-secondary font-monospace"><?php echo htmlspecialchars($supplier['supplier_code'] ?? 'SUP-'.$supplier['id']); ?></span>
                </div>
                <div class="p-4">
                    <div class="row g-3">
                        <div class="col-12 col-md-8">
                            <label class="form-label fw-semibold small text-dark">Company / Distributor Name <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($supplier['company_name']); ?>" required>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold small text-muted">Current Ledger Balance</label>
                            <div class="form-control form-control-sm fw-bold bg-light <?php echo ((float)$supplier['current_balance'] > 0) ? 'text-danger' : 'text-success'; ?>" readonly>
                                Rs. <?php echo number_format((float)$supplier['current_balance'], 2); ?>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small text-dark">Contact Person Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($supplier['name'] ?? ''); ?>" required>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small text-dark">Phone Number (PTCL / Office) <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control form-control-sm" value="<?php echo htmlspecialchars($supplier['phone']); ?>" required>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold small text-muted">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="Active" <?php echo ($supplier['status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo ($supplier['status'] === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-12">
                            <label class="form-label fw-semibold small text-muted">Office / Warehouse Address</label>
                            <textarea name="address" rows="2" class="form-control form-control-sm"><?php echo htmlspecialchars($supplier['address'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?php echo BASE_URL; ?>modules/supplier/suppliers.php" class="btn btn-light border px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                            <i class="fa-solid fa-save me-1"></i> Update Supplier
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
