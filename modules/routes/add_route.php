<?php
$page_title = "Add Area / Route";
require_once __DIR__ . '/../../includes/header.php';

$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $route_code = trim($_POST['route_code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $area_description = trim($_POST['area_description'] ?? '');
    $delivery_day = trim($_POST['delivery_day'] ?? '');
    $assigned_booker_id = (int)($_POST['assigned_booker_id'] ?? 0);
    $status = 'Active';

    if (empty($name)) {
        $error_msg = "Area / Route Name is required.";
    } else {
        if ($db_connected && $pdo) {
            try {
                if (empty($route_code)) {
                    $stmt_last = $pdo->query("SELECT MAX(id) FROM routes");
                    $last_id = (int)$stmt_last->fetchColumn();
                    $route_code = 'RT-' . str_pad($last_id + 1, 4, '0', STR_PAD_LEFT);
                }

                $sql = "INSERT INTO routes (route_code, name, area_description, delivery_day, assigned_booker_id, status) 
                        VALUES (:route_code, :name, :area_description, :delivery_day, :assigned_booker_id, :status)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'route_code' => $route_code,
                    'name' => $name,
                    'area_description' => $area_description,
                    'delivery_day' => $delivery_day,
                    'assigned_booker_id' => $assigned_booker_id > 0 ? $assigned_booker_id : null,
                    'status' => $status
                ]);
                $success_msg = "Area / Route added successfully!";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error_msg = "Route Code already exists.";
                } else {
                    $error_msg = "Error adding route: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch bookers
$bookers = [];
if ($db_connected && $pdo) {
    try {
        $stmt_b = $pdo->query("SELECT id, name FROM bookers WHERE status = 'Active' ORDER BY name ASC");
        $bookers = $stmt_b->fetchAll();
    } catch (Exception $e) {}
}

?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-bold mb-0"><i class="fa-solid fa-map-marked-alt text-primary me-2"></i>Add New Area / Route</h4>
    <a href="<?php echo BASE_URL; ?>modules/routes/routes.php" class="btn btn-outline-secondary">
        <i class="fa-solid fa-list me-1"></i> View Areas
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
        <h5 class="custom-card-title mb-0 fs-6"><i class="fa-solid fa-plus text-primary"></i> Area Information</h5>
    </div>
    <div class="p-4">
        <form method="POST" action="">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold small text-dark">Area / Route Code</label>
                    <input type="text" name="route_code" class="form-control form-control-sm font-monospace" placeholder="Leave empty for auto-generate">
                    <small class="text-muted fs-8">Auto-generated if left blank (e.g., RT-0001).</small>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold small text-dark">Area / Route Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control form-control-sm" placeholder="e.g. Shahdara, Gulberg..." required>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold small text-muted">Delivery Day (Optional)</label>
                    <select name="delivery_day" class="form-select form-select-sm">
                        <option value="">-- Select Day --</option>
                        <option value="Monday">Monday</option>
                        <option value="Tuesday">Tuesday</option>
                        <option value="Wednesday">Wednesday</option>
                        <option value="Thursday">Thursday</option>
                        <option value="Friday">Friday</option>
                        <option value="Saturday">Saturday</option>
                        <option value="Sunday">Sunday</option>
                        <option value="Daily">Daily</option>
                    </select>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold small text-muted">Assign Booker (Optional)</label>
                    <select name="assigned_booker_id" class="form-select form-select-sm">
                        <option value="">-- Select Booker --</option>
                        <?php foreach ($bookers as $b): ?>
                            <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold small text-muted">Area Description / Landmarks</label>
                    <textarea name="area_description" rows="2" class="form-control form-control-sm" placeholder="Any specific details about this area..."></textarea>
                </div>

            </div>

            <hr class="my-4">
            
            <div class="d-flex justify-content-end gap-2">
                <button type="reset" class="btn btn-light border">Reset</button>
                <button type="submit" class="btn btn-primary fw-bold">
                    <i class="fa-solid fa-save me-1"></i> Save Area
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
