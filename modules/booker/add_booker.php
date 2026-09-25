<?php
$page_title = "Add Booker";
require_once __DIR__ . '/../../includes/header.php';

$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booker_code = trim($_POST['booker_code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $opening_balance = (float)($_POST['opening_balance'] ?? 0.00);
    $status = 'Active';

    if (empty($name)) {
        $error_msg = "Booker Name is required.";
    } elseif (empty($phone)) {
        $error_msg = "Phone number is required.";
    } else {
        if ($db_connected && $pdo) {
            try {
                $pdo->beginTransaction();

                if (empty($booker_code)) {
                    $stmt_last = $pdo->query("SELECT MAX(id) FROM bookers");
                    $last_id = (int)$stmt_last->fetchColumn();
                    $booker_code = 'BKR-' . str_pad($last_id + 1, 4, '0', STR_PAD_LEFT);
                }

                $sql = "INSERT INTO bookers (booker_code, name, phone, address, opening_balance, current_balance, status) 
                        VALUES (:booker_code, :name, :phone, :address, :opening_balance, :current_balance, :status)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'booker_code' => $booker_code,
                    'name' => $name,
                    'phone' => $phone,
                    'address' => $address,
                    'opening_balance' => $opening_balance,
                    'current_balance' => $opening_balance,
                    'status' => $status
                ]);
                $booker_id = $pdo->lastInsertId();

                if ($opening_balance != 0) {
                    $sql_led = "INSERT INTO booker_ledgers (booker_id, transaction_date, transaction_type, reference_no, debit_amount, credit_amount, running_balance, description) 
                                VALUES (:bid, :tdate, 'Opening Balance', 'OB', :debit, :credit, :rbalance, 'Account Opening Balance')";
                    
                    $stmt_led = $pdo->prepare($sql_led);
                    $debit = ($opening_balance > 0) ? $opening_balance : 0;
                    $credit = ($opening_balance < 0) ? abs($opening_balance) : 0;
                    
                    $stmt_led->execute([
                        'bid' => $booker_id,
                        'tdate' => date('Y-m-d'),
                        'debit' => $debit,
                        'credit' => $credit,
                        'rbalance' => $opening_balance
                    ]);
                }

                $pdo->commit();
                $success_msg = "Booker <strong>" . htmlspecialchars($name) . "</strong> added successfully!";
            } catch (PDOException $e) {
                $pdo->rollBack();
                if ($e->getCode() == 23000) {
                    $error_msg = "Booker Code already exists.";
                } else {
                    $error_msg = "Error adding booker: " . $e->getMessage();
                }
            }
        }
    }
}

?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-bold mb-0"><i class="fa-solid fa-user-plus text-primary me-2"></i>Add New Booker</h4>
    <a href="<?php echo BASE_URL; ?>modules/booker/bookers.php" class="btn btn-outline-secondary">
        <i class="fa-solid fa-list me-1"></i> View Bookers
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
                    <input type="text" name="booker_code" class="form-control form-control-sm font-monospace" placeholder="Leave empty for auto-generate">
                    <small class="text-muted fs-8">Auto-generated if left blank (e.g., BKR-0001).</small>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold small text-dark">Booker Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control form-control-sm" placeholder="e.g. Asif Ali" required>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold small text-dark">Phone Number <span class="text-danger">*</span></label>
                    <input type="text" name="phone" class="form-control form-control-sm" placeholder="e.g. 0300-1234567" required>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold small text-dark">Opening Balance (PKR)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">Rs.</span>
                        <input type="number" step="0.01" name="opening_balance" class="form-control" value="0.00">
                    </div>
                    <small class="text-muted fs-8">Previous balance if any.</small>
                </div>

                <div class="col-12 col-md-12">
                    <label class="form-label fw-semibold small text-muted">Address</label>
                    <textarea name="address" rows="2" class="form-control form-control-sm" placeholder="Street, Sector, City..."></textarea>
                </div>

            </div>

            <hr class="my-4">
            
            <div class="d-flex justify-content-end gap-2">
                <button type="reset" class="btn btn-light border">Reset</button>
                <button type="submit" class="btn btn-primary fw-bold">
                    <i class="fa-solid fa-save me-1"></i> Save Booker
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
