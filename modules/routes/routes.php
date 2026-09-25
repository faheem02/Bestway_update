<?php
$page_title = "Areas & Routes";
require_once __DIR__ . '/../../includes/header.php';

$success_msg = "";
$error_msg = "";

if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    if ($db_connected && $pdo) {
        try {
            // Ensure no customers exist in this area
            $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE route_id = :id");
            $stmt_chk->execute(['id' => $del_id]);
            if ($stmt_chk->fetchColumn() > 0) {
                $error_msg = "Cannot delete Area! There are customers associated with it.";
            } else {
                $stmt_del = $pdo->prepare("DELETE FROM routes WHERE id = :id");
                $stmt_del->execute(['id' => $del_id]);
                $success_msg = "Area / Route deleted successfully!";
            }
        } catch (Exception $e) {
            $error_msg = "Error deleting Area: " . $e->getMessage();
        }
    }
}

$routes = [];
if ($db_connected && $pdo) {
    try {
        $sql = "SELECT r.*, b.name as booker_name, 
                       (SELECT COUNT(*) FROM customers c WHERE c.route_id = r.id) as customer_count 
                FROM routes r 
                LEFT JOIN bookers b ON r.assigned_booker_id = b.id 
                ORDER BY r.name ASC";
        $stmt = $pdo->query($sql);
        $routes = $stmt->fetchAll();
    } catch (Exception $e) {}
}

?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-map-marked-alt text-primary me-2"></i>Areas & Routes</h4>
        <p class="text-muted small mb-0">Manage delivery areas and assign order bookers.</p>
    </div>
    <a href="<?php echo BASE_URL; ?>modules/routes/add_route.php" class="btn btn-primary">
        <i class="fa-solid fa-plus me-1"></i> Add New Area
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
    <div class="table-responsive">
        <table class="table table-custom mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th style="width: 120px;">Code</th>
                    <th>Area Name</th>
                    <th>Assigned Booker</th>
                    <th>Delivery Day</th>
                    <th class="text-center" style="width: 100px;">Customers</th>
                    <th class="text-center" style="width: 100px;">Status</th>
                    <th class="text-end" style="width: 120px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($routes)): ?>
                    <?php foreach ($routes as $index => $r): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><span class="badge bg-light text-dark border font-monospace"><?php echo htmlspecialchars($r['route_code'] ?? 'N/A'); ?></span></td>
                            <td>
                                <strong class="text-dark"><?php echo htmlspecialchars($r['name']); ?></strong>
                                <?php if (!empty($r['area_description'])): ?>
                                    <br><small class="text-muted fs-8"><?php echo htmlspecialchars($r['area_description']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['booker_name']): ?>
                                    <span class="badge badge-soft-info"><i class="fa-solid fa-user-tag me-1"></i><?php echo htmlspecialchars($r['booker_name']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">- Not Assigned -</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($r['delivery_day'] ?? '-'); ?></td>
                            <td class="text-center">
                                <span class="badge bg-secondary"><?php echo $r['customer_count']; ?></span>
                            </td>
                            <td class="text-center">
                                <?php if ($r['status'] === 'Active'): ?>
                                    <span class="badge bg-success rounded-pill px-3">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger rounded-pill px-3">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="edit_route.php?id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-primary py-0 px-2" title="Edit">
                                    <i class="fa-solid fa-edit"></i>
                                </a>
                                <a href="routes.php?delete_id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="return confirm('Are you sure you want to delete this Area/Route?');" title="Delete">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="fa-solid fa-map-marked-alt fs-1 text-light mb-2 d-block"></i>
                            No areas/routes found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
