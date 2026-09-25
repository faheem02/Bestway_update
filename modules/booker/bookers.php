<?php
$page_title = "Bookers List";
require_once __DIR__ . '/../../includes/header.php';

$success_msg = "";
$error_msg = "";

if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    if ($db_connected && $pdo) {
        try {
            // Ensure no ledger entries exist before deleting
            $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM booker_ledgers WHERE booker_id = :id");
            $stmt_chk->execute(['id' => $del_id]);
            if ($stmt_chk->fetchColumn() > 0) {
                $error_msg = "Cannot delete booker! Ledger transactions exist.";
            } else {
                $stmt_del = $pdo->prepare("DELETE FROM bookers WHERE id = :id");
                $stmt_del->execute(['id' => $del_id]);
                $success_msg = "Booker deleted successfully!";
            }
        } catch (Exception $e) {
            $error_msg = "Error deleting booker: " . $e->getMessage();
        }
    }
}

$bookers = [];
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM bookers ORDER BY name ASC");
        $bookers = $stmt->fetchAll();
    } catch (Exception $e) {}
}

?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-users text-primary me-2"></i>Bookers</h4>
        <p class="text-muted small mb-0">Manage your order bookers and view their current balances.</p>
    </div>
    <a href="<?php echo BASE_URL; ?>modules/booker/add_booker.php" class="btn btn-primary">
        <i class="fa-solid fa-plus me-1"></i> Add New Booker
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
                    <th style="width: 120px;">Booker Code</th>
                    <th>Booker Name</th>
                    <th>Contact Info</th>
                    <th class="text-end">Current Balance</th>
                    <th class="text-center" style="width: 100px;">Status</th>
                    <th class="text-end" style="width: 150px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($bookers)): ?>
                    <?php foreach ($bookers as $index => $b): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><span class="badge bg-light text-dark border font-monospace"><?php echo htmlspecialchars($b['booker_code'] ?? 'N/A'); ?></span></td>
                            <td>
                                <strong class="text-dark"><?php echo htmlspecialchars($b['name']); ?></strong>
                            </td>
                            <td>
                                <a href="tel:<?php echo htmlspecialchars($b['phone']); ?>" class="text-decoration-none text-dark fw-semibold">
                                    <i class="fa-solid fa-phone text-primary me-1 fs-8"></i><?php echo htmlspecialchars($b['phone']); ?>
                                </a>
                                <?php if (!empty($b['address'])): ?>
                                    <br><small class="text-muted fs-8"><i class="fa-solid fa-location-dot me-1"></i><?php echo htmlspecialchars($b['address']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ((float)$b['current_balance'] > 0): ?>
                                    <strong class="text-danger fs-6"><?php echo format_currency($b['current_balance']); ?></strong>
                                <?php else: ?>
                                    <strong class="text-success fs-6"><?php echo format_currency($b['current_balance']); ?></strong>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($b['status'] === 'Active'): ?>
                                    <span class="badge bg-success rounded-pill px-3">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger rounded-pill px-3">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="booker_ledger.php?booker_id=<?php echo $b['id']; ?>" class="btn btn-sm btn-outline-info py-0 px-2" title="View Ledger">
                                    <i class="fa-solid fa-file-invoice-dollar"></i>
                                </a>
                                <a href="edit_booker.php?id=<?php echo $b['id']; ?>" class="btn btn-sm btn-outline-primary py-0 px-2" title="Edit">
                                    <i class="fa-solid fa-edit"></i>
                                </a>
                                <a href="bookers.php?delete_id=<?php echo $b['id']; ?>" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="return confirm('Are you sure you want to delete this Booker?');" title="Delete">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="fa-solid fa-users fs-1 text-light mb-2 d-block"></i>
                            No bookers found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
