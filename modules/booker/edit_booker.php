<?php
$page_title = "Edit Booker";
require_once __DIR__ . '/../../includes/header.php';

$booker_id = (int)($_GET['id'] ?? 0);
if ($booker_id <= 0) {
    echo "<div class='container mt-5'><h3>Invalid Booker ID.</h3></div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booker_code = trim($_POST['booker_code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['Active', 'Inactive']) ? $_POST['status'] : 'Active';

    if (empty($name)) {
        $error_msg = "Booker Name is required.";
    } elseif (empty($phone)) {
        $error_msg = "Phone number is required.";
    } else {
        if ($db_connected && $pdo) {
            try {
                $sql = "UPDATE bookers SET 
                            booker_code = :booker_code, 
                            name = :name, 
                            phone = :phone, 
                            address = :address, 
                            status = :status 
                        WHERE id = :id";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'booker_code' => $booker_code,
                    'name' => $name,
                    'phone' => $phone,
                    'address' => $address,
                    'status' => $status,
                    'id' => $booker_id
                ]);
                $success_msg = "Booker profile updated successfully!";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error_msg = "Booker Code already exists.";
                } else {
                    $error_msg = "Error updating booker: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch current booker data
$booker = null;
if ($db_connected && $pdo) {
    $stmt_sel = $pdo->prepare("SELECT * FROM bookers WHERE id = :id");
    $stmt_sel->execute(['id' => $booker_id]);
    $booker = $stmt_sel->fetch();
}

if (!$booker) {
    echo "<div class='container mt-5'><h3>Booker not found.</h3></div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-bold mb-0"><i class="fa-solid fa-edit text-primary me-2"></i>Edit Booker</h4>
    <a href="<?php echo BASE_URL; ?>modules/booker/bookers.php" class="btn btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i> Back to Bookers
    </a>
</div>

<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-check-circle me-2"></i><?php echo $success_msg; ?>
        <button type="button" class="close ml-auto" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
<?php endif; ?>
<?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-exclamation-triangle me-2"></i><?php echo $error_msg; ?>
        <button type="button" class="close ml-auto" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
<?php endif; ?>

<div class="custom-card">
    <div class="custom-card-header bg-white">
        <h5 class="custom-card-title mb-0 fs-6"><i class="fa-solid fa-id-card text-primary"></i> Booker Profile</h5>
    </div>
    <div class="p-4">
        <form method="POST" action="">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold small text-dark">Booker Code</label>
                    <input type="text" name="booker_code" class="form-control form-control-sm font-monospace" value="<?php echo htmlspecialchars($booker['booker_code'] ?? ''); ?>">
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold small text-dark">Booker Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($booker['name']); ?>" required>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold small text-dark">Phone Number <span class="text-danger">*</span></label>
                    <input type="text" name="phone" class="form-control form-control-sm" value="<?php echo htmlspecialchars($booker['phone']); ?>" required>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold small text-muted">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="Active" <?php echo ($booker['status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo ($booker['status'] === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div class="col-12 col-md-12">
                    <label class="form-label fw-semibold small text-muted">Address</label>
                    <textarea name="address" rows="2" class="form-control form-control-sm"><?php echo htmlspecialchars($booker['address'] ?? ''); ?></textarea>
                </div>

            </div>

            <hr class="my-4">
            
            <div class="d-flex justify-content-end gap-2">
                <a href="<?php echo BASE_URL; ?>modules/booker/bookers.php" class="btn btn-light border">Cancel</a>
                <button type="submit" class="btn btn-primary fw-bold">
                    <i class="fa-solid fa-save me-1"></i> Update Booker
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
