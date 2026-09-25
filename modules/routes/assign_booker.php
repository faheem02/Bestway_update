<?php
$page_title = "Assign Booker";
require_once __DIR__ . '/../../includes/header.php';

$success_msg = "";
$error_msg = "";

// Handle form submission to assign booker
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_btn'])) {
    $route_id = (int)($_POST['route_id'] ?? 0);
    $booker_id = (int)($_POST['booker_id'] ?? 0);

    if ($route_id > 0) {
        if ($db_connected && $pdo) {
            try {
                $sql = "UPDATE routes SET assigned_booker_id = :booker_id WHERE id = :route_id";
                $stmt = $pdo->prepare($sql);
                // If booker_id is 0 (Unassign), set to NULL
                $stmt->execute([
                    'booker_id' => $booker_id > 0 ? $booker_id : null,
                    'route_id' => $route_id
                ]);
                $success_msg = "Booker assigned successfully!";
            } catch (Exception $e) {
                $error_msg = "Error assigning booker: " . $e->getMessage();
            }
        }
    } else {
        $error_msg = "Please select an Area/Route.";
    }
}

// Fetch all routes and bookers for the form
$all_routes = [];
$all_bookers = [];
$assigned_list = [];

if ($db_connected && $pdo) {
    try {
        $all_routes = $pdo->query("SELECT id, name FROM routes ORDER BY name ASC")->fetchAll();
        $all_bookers = $pdo->query("SELECT id, name FROM bookers WHERE status = 'Active' ORDER BY name ASC")->fetchAll();

        // Fetch bookers who have been assigned areas
        $sql_list = "SELECT b.id, b.booker_code, b.name as booker_name, b.phone, 
                            GROUP_CONCAT(r.name ORDER BY r.name ASC SEPARATOR '<br>') as assigned_areas,
                            COUNT(r.id) as area_count
                     FROM bookers b
                     JOIN routes r ON b.id = r.assigned_booker_id
                     GROUP BY b.id, b.booker_code, b.name, b.phone
                     ORDER BY b.name ASC";
        $assigned_list = $pdo->query($sql_list)->fetchAll();
    } catch (Exception $e) {}
}

?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-bold mb-0"><i class="fa-solid fa-user-tag text-primary me-2"></i>Assign Booker to Area</h4>
    <a href="<?php echo BASE_URL; ?>modules/routes/routes.php" class="btn btn-outline-secondary">
        <i class="fa-solid fa-map-marked-alt me-1"></i> View Areas
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

<div class="row">
    <!-- Quick Assign Form -->
    <div class="col-12 col-lg-4 mb-4 mb-lg-0">
        <div class="custom-card h-100">
            <div class="custom-card-header bg-white border-bottom">
                <h5 class="custom-card-title mb-0 fs-6"><i class="fa-solid fa-link text-primary"></i> Quick Assign</h5>
            </div>
            <div class="p-4">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark">Select Area / Route <span class="text-danger">*</span></label>
                        <select name="route_id" class="form-select form-select-sm" required>
                            <option value="">-- Choose Area --</option>
                            <?php foreach ($all_routes as $r): ?>
                                <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold small text-dark">Assign Booker <span class="text-danger">*</span></label>
                        <select name="booker_id" class="form-select form-select-sm" required>
                            <option value="0">-- Unassign / Remove Booker --</option>
                            <?php foreach ($all_bookers as $b): ?>
                                <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" name="assign_btn" class="btn btn-primary fw-bold w-100">
                        <i class="fa-solid fa-check-circle me-1"></i> Update Assignment
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- List of Assigned Bookers -->
    <div class="col-12 col-lg-8">
        <div class="custom-card h-100">
            <div class="custom-card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h5 class="custom-card-title mb-0 fs-6"><i class="fa-solid fa-users text-primary"></i> Bookers with Assigned Areas</h5>
                <span class="badge bg-primary rounded-pill"><?php echo count($assigned_list); ?> Bookers</span>
            </div>
            <div class="table-responsive p-0">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th style="width: 120px;">Code</th>
                            <th>Booker Name</th>
                            <th>Contact</th>
                            <th>Assigned Areas (Routes)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($assigned_list)): ?>
                            <?php foreach ($assigned_list as $index => $row): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><span class="badge bg-light text-dark border font-monospace"><?php echo htmlspecialchars($row['booker_code']); ?></span></td>
                                    <td><strong class="text-dark"><?php echo htmlspecialchars($row['booker_name']); ?></strong></td>
                                    <td><i class="fa-solid fa-phone text-muted me-1 fs-8"></i><?php echo htmlspecialchars($row['phone']); ?></td>
                                    <td>
                                        <div class="text-dark fw-semibold lh-sm">
                                            <?php echo $row['assigned_areas']; ?>
                                        </div>
                                        <div class="mt-1">
                                            <span class="badge bg-info text-white" style="font-size: 10px;"><?php echo $row['area_count']; ?> Area(s)</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-map-marked-alt fs-1 text-light mb-2 d-block"></i>
                                    No areas have been assigned to any booker yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
