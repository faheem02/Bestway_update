<?php
$page_title = "Receive Cash from Booker";
require_once __DIR__ . '/../../includes/header.php';

$booker_id = (int)($_GET['booker_id'] ?? 0);

$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $b_id = (int)($_POST['booker_id'] ?? 0);
    $transaction_date = trim($_POST['transaction_date'] ?? date('Y-m-d'));
    $credit_amount = (float)($_POST['amount'] ?? 0.00); // Deposit amount
    $reference_no = trim($_POST['reference_no'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($b_id <= 0 || $credit_amount <= 0) {
        $error_msg = "Please select a valid booker and enter an amount greater than 0.";
    } else {
        if ($db_connected && $pdo) {
            try {
                $pdo->beginTransaction();

                // Get current balance of the booker
                $stmt_bal = $pdo->prepare("SELECT current_balance FROM bookers WHERE id = :id FOR UPDATE");
                $stmt_bal->execute(['id' => $b_id]);
                $curr_bal = (float)$stmt_bal->fetchColumn();

                // booker balance = amount owed. When they deposit, balance decreases.
                $new_bal = $curr_bal - $credit_amount;

                if (empty($reference_no)) {
                    $reference_no = 'REC-' . date('Ymd') . '-' . rand(1000, 9999);
                }

                // Insert ledger entry (Credit)
                $sql_led = "INSERT INTO booker_ledgers (booker_id, transaction_date, transaction_type, reference_no, debit_amount, credit_amount, running_balance, description) 
                            VALUES (:bid, :tdate, 'Cash Deposited', :ref, 0.00, :credit, :rbalance, :desc)";
                $stmt_led = $pdo->prepare($sql_led);
                $stmt_led->execute([
                    'bid' => $b_id,
                    'tdate' => $transaction_date,
                    'ref' => $reference_no,
                    'credit' => $credit_amount,
                    'rbalance' => $new_bal,
                    'desc' => $description
                ]);

                // Update booker balance
                $stmt_upd = $pdo->prepare("UPDATE bookers SET current_balance = :cb WHERE id = :bid");
                $stmt_upd->execute(['cb' => $new_bal, 'bid' => $b_id]);

                $pdo->commit();
                $success_msg = "Cash deposited successfully! Account balance updated.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_msg = "Error processing transaction: " . $e->getMessage();
            }
        }
    }
}

// Fetch all bookers for dropdown
$bookers = [];
if ($db_connected && $pdo) {
    try {
        $stmt_b = $pdo->query("SELECT id, name, booker_code, current_balance FROM bookers WHERE status='Active' ORDER BY name ASC");
        $bookers = $stmt_b->fetchAll();
    } catch (Exception $e) {}
}

?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-bold mb-0"><i class="fa-solid fa-hand-holding-usd text-success me-2"></i>Receive Cash from Booker</h4>
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

<div class="row">
    <div class="col-12 col-lg-8">
        <div class="custom-card">
            <div class="custom-card-header bg-white">
                <h5 class="custom-card-title mb-0 fs-6"><i class="fa-solid fa-money-bill-alt text-success"></i> Cash Deposit Details</h5>
            </div>
            <div class="p-4">
                <form method="POST" action="">
                    <div class="row g-3">
                        
                        <div class="col-12">
                            <label class="form-label fw-semibold small text-dark">Select Booker <span class="text-danger">*</span></label>
                            <select name="booker_id" id="booker_id" class="form-select" required onchange="updateBalance()">
                                <option value="">-- Choose Booker --</option>
                                <?php foreach ($bookers as $b): ?>
                                    <option value="<?php echo $b['id']; ?>" data-balance="<?php echo (float)$b['current_balance']; ?>" <?php echo ($booker_id == $b['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($b['name']); ?> [<?php echo htmlspecialchars($b['booker_code']); ?>]
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small text-dark">Current Due Balance</label>
                            <div class="input-group">
                                <span class="input-group-text">Rs.</span>
                                <input type="text" id="display_balance" class="form-control fw-bold text-danger" value="0.00" readonly disabled>
                            </div>
                            <small class="text-muted fs-8">Amount booker owes to the company.</small>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small text-dark">Transaction Date <span class="text-danger">*</span></label>
                            <input type="date" name="transaction_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small text-dark">Deposit Amount (PKR) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-success text-white">Rs.</span>
                                <input type="number" step="0.01" name="amount" class="form-control fw-bold" placeholder="0.00" required>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small text-dark">Receipt / Ref No.</label>
                            <input type="text" name="reference_no" class="form-control" placeholder="Leave blank to auto-generate">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small text-muted">Description / Remarks</label>
                            <textarea name="description" rows="2" class="form-control" placeholder="e.g. Cash recovery from market..."></textarea>
                        </div>

                    </div>

                    <hr class="my-4">
                    
                    <div class="d-flex justify-content-end gap-2">
                        <button type="reset" class="btn btn-light border">Reset</button>
                        <button type="submit" class="btn btn-success fw-bold">
                            <i class="fa-solid fa-check-circle me-1"></i> Submit Deposit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-lg-4">
        <div class="custom-card bg-light border-0">
            <div class="p-4">
                <h6 class="fw-bold mb-3"><i class="fa-solid fa-circle-info text-info me-2"></i>How it works</h6>
                <p class="small text-muted mb-2">
                    When order bookers collect cash from the market, they deposit it to the company using this form.
                </p>
                <ul class="small text-muted ps-3 mb-0">
                    <li class="mb-1"><strong>Debit (Charge):</strong> Bookers are charged when invoices are assigned to them.</li>
                    <li class="mb-1"><strong>Credit (Deposit):</strong> This form credits their account, reducing their due balance.</li>
                    <li>The system automatically recalculates their running balance in their ledger.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
function updateBalance() {
    const select = document.getElementById('booker_id');
    const display = document.getElementById('display_balance');
    
    if (select.selectedIndex > 0) {
        const option = select.options[select.selectedIndex];
        const balance = parseFloat(option.getAttribute('data-balance') || 0);
        
        display.value = balance.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        
        if (balance > 0) {
            display.classList.remove('text-success');
            display.classList.add('text-danger');
        } else {
            display.classList.remove('text-danger');
            display.classList.add('text-success');
        }
    } else {
        display.value = "0.00";
        display.classList.remove('text-success');
        display.classList.add('text-danger');
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', updateBalance);
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
