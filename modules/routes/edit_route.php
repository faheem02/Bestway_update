<?php
$page_title = "Edit Area / Route";
require_once __DIR__ . '/../../includes/header.php';

$route_id = (int)($_GET['id'] ?? 0);
if ($route_id <= 0) {
    echo "<div class='container mt-5'><h3>Invalid Area ID.</h3></div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $route_code = trim($_POST['route_code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $area_description = trim($_POST['area_description'] ?? '');
    $delivery_day = trim($_POST['delivery_day'] ?? '');
    $assigned_booker_id = (int)($_POST['assigned_booker_id'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['Active', 'Inactive']) ? $_POST['status'] : 'Active';

    if (empty($name)) {
        $error_msg = "Area / Route Name is required.";
    } else {
        if ($db_connected && $pdo) {
            try {
                $sql = "UPDATE routes SET 
                            route_code = :route_code, 
                            name = :name, 
                            area_description = :area_description, 
                            delivery_day = :delivery_day, 
                            assigned_booker_id = :assigned_booker_id, 
                            status = :status 
                        WHERE id = :id";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'route_code' => $route_code,
                    'name' => $name,
                    'area_description' => $area_description,
                    'delivery_day' => $delivery_day,
                    'assigned_booker_id' => $assigned_booker_id > 0 ? $assigned_booker_id : null,
                    'status' => $status,
                    'id' => $route_id
                ]);
                $success_msg = "Area / Route updated successfully!";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error_msg = "Route Code already exists.";
                } else {
                    $error_msg = "Error updating route: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch current route data
$route = null;
$bookers = [];
if ($db_connected && $pdo) {
    try {
        $stmt_sel = $pdo->prepare("SELECT * FROM routes WHERE id = :id");
        $stmt_sel->execute(['id' => $route_id]);
        $route = $stmt_sel->fetch();

        if (!$route) {
            echo "<div class='container mt-5'><h3>Area / Route not found.</h3></div>";
            require_once __DIR__ . '/../../includes/footer.php';
            exit;
        }

        // Fetch bookers
        $stmt_b = $pdo->query("SELECT id, name FROM bookers WHERE status = 'Active' ORDER BY name ASC");
        $bookers = $stmt_b->fetchAll();
    } catch (Exception $e) {}
}

?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-bold mb-0"><i class="fa-solid fa-edit text-primary me-2"></i>Edit Area / Route</h4>
    <a href="<?php echo BASE_URL; ?>modules/routes/routes.php" class="btn btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i> Back to Areas
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
        <h5 class="custom-card-title mb-0 fs-6"><i class="fa-solid fa-map text-primary"></i> Area Information</h5>
    </div>
    <div class="p-4">
        <form method="POST" action="">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold small text-dark">Area / Route Code</label>
                    <input type="text" name="route_code" class="form-control form-control-sm font-monospace" value="<?php echo htmlspecialchars($route['route_code'] ?? ''); ?>">
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold small text-dark">Area / Route Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($route['name']); ?>" required>
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold small text-muted">Delivery Day</label>
                    <select name="delivery_day" class="form-select form-select-sm">
                        <option value="">-- Select Day --</option>
                        <?php 
                        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday', 'Daily'];
                        foreach ($days as $d): 
                        ?>
                            <option value="<?php echo $d; ?>" <?php echo ($route['delivery_day'] == $d) ? 'selected' : ''; ?>><?php echo $d; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold small text-muted">Assign Booker</label>
                    <select name="assigned_booker_id" class="form-select form-select-sm">
                        <option value="">-- Select Booker --</option>
                        <?php foreach ($bookers as $b): ?>
                            <option value="<?php echo $b['id']; ?>" <?php echo ($route['assigned_booker_id'] == $b['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold small text-muted">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="Active" <?php echo ($route['status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo ($route['status'] === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold small text-muted">Area Description / Landmarks</label>
                    <textarea name="area_description" rows="2" class="form-control form-control-sm"><?php echo htmlspecialchars($route['area_description'] ?? ''); ?></textarea>
                </div>

            </div>

            <hr class="my-4">
            
            <div class="d-flex justify-content-end gap-2">
                <a href="<?php echo BASE_URL; ?>modules/routes/routes.php" class="btn btn-light border">Cancel</a>
                <button type="submit" class="btn btn-primary fw-bold">
                    <i class="fa-solid fa-save me-1"></i> Update Area
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
