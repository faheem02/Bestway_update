<?php
$page_title = "Bank Book";
require_once __DIR__ . '/../../includes/header.php';

$success_msg = "";
$error_msg = "";

$bank_id = (int)($_GET['bank_id'] ?? ($_POST['bank_id'] ?? 0));
$from_date = trim($_GET['from_date'] ?? date('Y-m-01'));
$to_date = trim($_GET['to_date'] ?? date('Y-m-d'));

// Handle Add/Edit/Delete Bank
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_bank') {
        $id = (int)($_POST['id'] ?? 0);
        $bank_name = trim($_POST['bank_name'] ?? '');
        $account_title = trim($_POST['account_title'] ?? '');
        $account_number = trim($_POST['account_number'] ?? '');
        $branch_code = trim($_POST['branch_code'] ?? '');
        $opening_balance = (float)($_POST['opening_balance'] ?? 0.00);
        
        if (empty($bank_name) || empty($account_title) || empty($account_number)) {
            $error_msg = "Please fill all required fields (*).";
        } else {
            if ($db_connected && $pdo) {
                try {
                    $pdo->beginTransaction();

                    if ($id > 0) {
                        // Update existing
                        $stmt = $pdo->prepare("UPDATE bank_accounts SET bank_name=:bn, account_title=:at, account_number=:an, branch_name=:br WHERE id=:id");
                        $stmt->execute([
                            'bn' => $bank_name,
                            'at' => $account_title,
                            'an' => $account_number,
                            'br' => $branch_code,
                            'id' => $id
                        ]);
                        $success_msg = "Bank account updated successfully.";
                        $bank_id = $id;
                    } else {
                        // Insert new
                        $stmt = $pdo->prepare("INSERT INTO bank_accounts (bank_name, account_title, account_number, branch_name, opening_balance, current_balance) VALUES (:bn, :at, :an, :br, :ob, :cb)");
                        $stmt->execute([
                            'bn' => $bank_name,
                            'at' => $account_title,
                            'an' => $account_number,
                            'br' => $branch_code,
                            'ob' => $opening_balance,
                            'cb' => $opening_balance
                        ]);
                        $bank_id = (int)$pdo->lastInsertId();

                        if ($opening_balance != 0) {
                            $stmt_led = $pdo->prepare("INSERT INTO account_ledgers (account_type, account_id, transaction_date, transaction_type, reference_no, debit_amount, credit_amount, running_balance, description) VALUES ('Bank', :id, :dt, 'Opening Balance', 'OPEN-BAL', :debit, :credit, :rb, 'Initial opening balance')");
                            $debit = $opening_balance > 0 ? $opening_balance : 0;
                            $credit = $opening_balance < 0 ? abs($opening_balance) : 0;
                            $stmt_led->execute([
                                'id' => $bank_id,
                                'dt' => date('Y-m-d'),
                                'debit' => $debit,
                                'credit' => $credit,
                                'rb' => $opening_balance
                            ]);
                        }
                        $success_msg = "Bank account added successfully.";
                    }
                    $pdo->commit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error_msg = "Error saving bank account: " . $e->getMessage();
                }
            }
        }
    } elseif ($_POST['action'] === 'delete_bank') {
        $id = (int)($_POST['delete_id'] ?? 0);
        if ($id > 0 && $db_connected && $pdo) {
            try {
                $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM account_ledgers WHERE account_type = 'Bank' AND account_id = :id AND transaction_type != 'Opening Balance'");
                $stmt_chk->execute(['id' => $id]);
                if ($stmt_chk->fetchColumn() > 0) {
                    throw new Exception("Cannot delete bank account. Transactions exist.");
                }

                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM account_ledgers WHERE account_type='Bank' AND account_id = :id")->execute(['id' => $id]);
                $pdo->prepare("DELETE FROM bank_accounts WHERE id = :id")->execute(['id' => $id]);
                $pdo->commit();
                $success_msg = "Bank account deleted.";
                $bank_id = 0;
            } catch (Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error_msg = $e->getMessage();
            }
        }
    }
}

// Fetch all banks
$all_banks = [];
if ($db_connected && $pdo) {
    try {
        $all_banks = $pdo->query("SELECT * FROM bank_accounts ORDER BY bank_name ASC")->fetchAll();
        // If no bank selected but banks exist, select the first one
        if ($bank_id == 0 && count($all_banks) > 0 && empty($_GET['bank_id'])) {
            $bank_id = $all_banks[0]['id'];
        }
    } catch (Exception $e) {}
}

// Fetch selected bank details & ledger
$selected_bank = null;
$opening_balance = 0.00;
$ledger_entries = [];

if ($bank_id > 0 && $db_connected && $pdo) {
    try {
        $stmt_sel = $pdo->prepare("SELECT * FROM bank_accounts WHERE id = :id");
        $stmt_sel->execute(['id' => $bank_id]);
        $selected_bank = $stmt_sel->fetch();

        if ($selected_bank) {
            // Opening balance before from_date
            $stmt_ob = $pdo->prepare("SELECT COALESCE(SUM(debit_amount - credit_amount), 0) FROM account_ledgers WHERE account_type = 'Bank' AND account_id = :id AND transaction_date < :fdate");
            $stmt_ob->execute(['id' => $bank_id, 'fdate' => $from_date]);
            $opening_balance = (float)$stmt_ob->fetchColumn();

            // Fetch transactions
            $stmt_trans = $pdo->prepare("SELECT * FROM account_ledgers WHERE account_type = 'Bank' AND account_id = :id AND transaction_date >= :fdate AND transaction_date <= :tdate ORDER BY transaction_date ASC, id ASC");
            $stmt_trans->execute(['id' => $bank_id, 'fdate' => $from_date, 'tdate' => $to_date]);
            $ledger_entries = $stmt_trans->fetchAll();
        }
    } catch (Exception $e) {}
}

?>

<!-- Custom Styling matching the screenshot -->
<style>
    body { background-color: #f4f6f9; }
    .card-panel { background: #fff; border-radius: 8px; border: 1px solid #e3e6f0; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.02); }
    .panel-title { font-size: 1.1rem; font-weight: 700; color: #2e384d; margin-bottom: 1rem; display: flex; align-items: center; gap: 8px; }
    .bank-bal-box { background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 15px; margin-top: 15px; }
    .btn-edit-bank { background-color: #f39c12; color: #fff; border: none; font-weight: 600; }
    .btn-edit-bank:hover { background-color: #d68910; color: #fff; }
    .btn-delete-bank { background-color: #e74c3c; color: #fff; border: none; font-weight: 600; }
    .btn-delete-bank:hover { background-color: #c0392b; color: #fff; }
    .btn-add-bank { background-color: #3b82f6; color: #fff; border: none; font-weight: 600; padding: 10px; width: 100%; border-radius: 6px; }
    .btn-add-bank:hover { background-color: #2563eb; color: #fff; }
    .form-control-custom { border-radius: 6px; border: 1px solid #ced4da; padding: 8px 12px; }
    .table-ledger th { background-color: #f8f9fa; color: #495057; font-weight: 600; font-size: 13px; text-transform: uppercase; border-bottom: 2px solid #dee2e6; }
    .table-ledger td { vertical-align: middle; font-size: 14px; }
    .text-deposit { color: #10b981 !important; font-weight: 600; }
    .text-withdraw { color: #ef4444 !important; font-weight: 600; }
</style>

<!-- Header Row -->
<div class="d-flex align-items-center justify-content-between mb-4 no-print">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-light border bg-white shadow-sm fw-semibold" onclick="history.back()"><i class="fa-solid fa-arrow-left me-2"></i>Back</button>
        <h4 class="fw-bolder mb-0 text-dark fs-3">Bank Book <?php echo $selected_bank ? '- ' . htmlspecialchars($selected_bank['account_title'] . ' ' . $selected_bank['bank_name']) : ''; ?></h4>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-light border bg-white shadow-sm fw-semibold" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print</button>
        <button class="btn btn-danger fw-semibold shadow-sm"><i class="fa-solid fa-file-pdf me-2"></i>Download PDF</button>
    </div>
</div>

<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success alert-dismissible fade show no-print"><i class="fa-solid fa-check-circle me-2"></i><?php echo $success_msg; ?><button type="button" class="close ml-auto" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
<?php endif; ?>
<?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger alert-dismissible fade show no-print"><i class="fa-solid fa-exclamation-triangle me-2"></i><?php echo $error_msg; ?><button type="button" class="close ml-auto" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
<?php endif; ?>

<div class="row g-4 mb-4 no-print">
    <!-- Select Bank & Details Column -->
    <div class="col-12 col-lg-7">
        <div class="card-panel p-4 h-100">
            <h5 class="panel-title">Select Bank Account</h5>
            
            <form method="GET" action="" id="bankSelectForm">
                <input type="hidden" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>">
                <input type="hidden" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>">
                <select name="bank_id" class="form-select form-control-custom fw-semibold text-dark fs-6" onchange="document.getElementById('bankSelectForm').submit();">
                    <option value="">-- Select Bank Account --</option>
                    <?php foreach ($all_banks as $b): ?>
                        <option value="<?php echo $b['id']; ?>" <?php echo ($bank_id == $b['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($b['account_title'] . ' ' . $b['bank_name']); ?> (Rs. <?php echo format_currency($b['current_balance']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <?php if ($selected_bank): ?>
            <div class="bank-bal-box mt-4">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-3">
                    <span class="text-muted fw-semibold">Current Balance for <?php echo htmlspecialchars($selected_bank['account_title'] . ' ' . $selected_bank['bank_name']); ?></span>
                    <h3 class="text-deposit mb-0">Rs. <?php echo format_currency($selected_bank['current_balance']); ?></h3>
                </div>
                
                <div class="row g-2 mb-4 text-dark fs-6 fw-semibold">
                    <div class="col-4">
                        <span class="text-muted small d-block">A/C Title:</span>
                        <?php echo htmlspecialchars($selected_bank['account_title']); ?>
                    </div>
                    <div class="col-4">
                        <span class="text-muted small d-block">A/C Number:</span>
                        <?php echo htmlspecialchars($selected_bank['account_number']); ?>
                    </div>
                    <div class="col-4">
                        <span class="text-muted small d-block">Branch Code:</span>
                        <?php echo htmlspecialchars($selected_bank['branch_name'] ?? '-'); ?>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button class="btn btn-edit-bank px-3 shadow-sm" onclick="editBank(<?php echo htmlspecialchars(json_encode($selected_bank)); ?>)">
                        <i class="fa-solid fa-edit me-1"></i> Edit Bank / Opening Balance
                    </button>
                    <form method="POST" action="" onsubmit="return confirm('Delete this bank account?');" class="m-0">
                        <input type="hidden" name="action" value="delete_bank">
                        <input type="hidden" name="delete_id" value="<?php echo $selected_bank['id']; ?>">
                        <button type="submit" class="btn btn-delete-bank px-3 shadow-sm">
                            <i class="fa-solid fa-trash me-1"></i> Delete
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add New Bank Column -->
    <div class="col-12 col-lg-5">
        <div class="card-panel p-4 h-100">
            <h5 class="panel-title text-primary"><i class="fa-solid fa-circle-plus"></i> <span id="formTitle">Add New Bank</span></h5>
            
            <form method="POST" action="" id="bankForm">
                <input type="hidden" name="action" value="save_bank">
                <input type="hidden" name="id" id="f_id" value="0">

                <div class="mb-3">
                    <label class="form-label small text-muted mb-1">Bank Name *</label>
                    <input type="text" name="bank_name" id="f_bank_name" class="form-control form-control-custom" placeholder="e.g. Meezan Bank" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label small text-muted mb-1">Account Title *</label>
                    <input type="text" name="account_title" id="f_account_title" class="form-control form-control-custom" placeholder="e.g. Aetmad Sanitary" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-muted mb-1">Account Number *</label>
                    <input type="text" name="account_number" id="f_account_number" class="form-control form-control-custom" placeholder="e.g. 1234-5678-90" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-muted mb-1">Branch Code *</label>
                    <input type="text" name="branch_code" id="f_branch_code" class="form-control form-control-custom" placeholder="e.g. 0012">
                </div>

                <div class="mb-4" id="f_opening_group">
                    <label class="form-label small text-muted mb-1">Opening Balance (Rs)</label>
                    <input type="number" step="0.01" name="opening_balance" id="f_opening_balance" class="form-control form-control-custom" value="0">
                </div>

                <button type="submit" class="btn btn-add-bank shadow-sm" id="btnSubmitForm">Add Bank</button>
                <button type="button" class="btn btn-light w-100 mt-2 d-none" id="btnCancelEdit" onclick="cancelEdit()">Cancel Edit</button>
            </form>
        </div>
    </div>
</div>

<?php if ($selected_bank): ?>
<!-- Filters & Search -->
<div class="card-panel p-3 mb-4 d-flex flex-wrap align-items-end justify-content-between gap-3 no-print">
    <form method="GET" action="" class="d-flex align-items-end gap-3 flex-grow-1" style="max-width: 500px;">
        <input type="hidden" name="bank_id" value="<?php echo $bank_id; ?>">
        <div>
            <label class="form-label small fw-semibold text-muted mb-1">From Date</label>
            <input type="date" name="from_date" class="form-control form-control-custom" value="<?php echo htmlspecialchars($from_date); ?>">
        </div>
        <div>
            <label class="form-label small fw-semibold text-muted mb-1">To Date</label>
            <input type="date" name="to_date" class="form-control form-control-custom" value="<?php echo htmlspecialchars($to_date); ?>">
        </div>
        <button type="submit" class="btn btn-primary px-4 shadow-sm" style="height: 38px;"><i class="fa-solid fa-filter me-1"></i> Filter</button>
    </form>

    <div class="position-relative flex-grow-1" style="max-width: 400px;">
        <i class="fa-solid fa-search position-absolute text-muted" style="top: 12px; left: 15px;"></i>
        <input type="text" class="form-control form-control-custom ps-5" placeholder="Search transactions...">
    </div>
</div>

<!-- Ledger Statement Table -->
<div class="card-panel p-0 overflow-hidden">
    <!-- Table Header Info -->
    <div class="bg-white p-4 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold text-dark mb-2"><?php echo htmlspecialchars($selected_bank['account_title'] . ' ' . $selected_bank['bank_name']); ?> Ledger Statement</h4>
            <div class="text-muted small fw-semibold">
                A/C Title: <span class="text-dark me-2"><?php echo htmlspecialchars($selected_bank['account_title']); ?></span> | 
                A/C Number: <span class="text-dark me-2"><?php echo htmlspecialchars($selected_bank['account_number']); ?></span> | 
                Branch Code: <span class="text-dark"><?php echo htmlspecialchars($selected_bank['branch_name'] ?? '-'); ?></span>
            </div>
        </div>
        <div class="text-end text-uppercase">
            <span class="text-muted fw-bold fs-8">Current Balance</span>
            <h3 class="text-deposit mb-0 fw-bold">Rs. <?php echo format_currency($selected_bank['current_balance']); ?></h3>
        </div>
    </div>
    
    <div class="p-4 pt-3">
        <h6 class="fw-bold text-dark mb-3">Transaction Ledger</h6>
        
        <div class="table-responsive">
            <table class="table table-ledger mb-0">
                <thead>
                    <tr>
                        <th style="width: 10%;">Date</th>
                        <th style="width: 30%;">Details</th>
                        <th style="width: 15%;">Reference</th>
                        <th class="text-end" style="width: 15%;">Deposit (IN)</th>
                        <th class="text-end" style="width: 15%;">Withdrawal (OUT)</th>
                        <th class="text-end" style="width: 15%;">Running Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-light">
                        <td class="fw-bold"><?php echo date('d-m-Y', strtotime($from_date)); ?></td>
                        <td class="fw-bold">Brought Forward</td>
                        <td>-</td>
                        <td class="text-end">-</td>
                        <td class="text-end">-</td>
                        <td class="text-end fw-bold text-dark fs-6"><?php echo format_currency($opening_balance); ?></td>
                    </tr>
                    
                    <?php 
                    $run_bal = $opening_balance;
                    $tot_in = 0;
                    $tot_out = 0;
                    if (!empty($ledger_entries)): 
                        foreach($ledger_entries as $t): 
                            $in = (float)$t['debit_amount']; // Deposit
                            $out = (float)$t['credit_amount']; // Withdrawal
                            $run_bal = $run_bal + $in - $out;
                            $tot_in += $in;
                            $tot_out += $out;
                    ?>
                    <tr>
                        <td><?php echo date('d-m-Y', strtotime($t['transaction_date'])); ?></td>
                        <td class="text-dark fw-semibold"><?php echo htmlspecialchars($t['description']); ?></td>
                        <td><span class="text-muted small"><?php echo htmlspecialchars($t['reference_no']); ?></span></td>
                        <td class="text-end text-deposit"><?php echo ($in > 0) ? format_currency($in) : ''; ?></td>
                        <td class="text-end text-withdraw"><?php echo ($out > 0) ? format_currency($out) : ''; ?></td>
                        <td class="text-end fw-bold text-dark fs-6"><?php echo format_currency($run_bal); ?></td>
                    </tr>
                    <?php 
                        endforeach; 
                    else:
                    ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted">No transactions found for this period.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-light border-top">
                    <tr class="fw-bold text-dark">
                        <td colspan="3" class="text-end">Period Totals:</td>
                        <td class="text-end text-deposit"><?php echo format_currency($tot_in); ?></td>
                        <td class="text-end text-withdraw"><?php echo format_currency($tot_out); ?></td>
                        <td class="text-end fs-6"><?php echo format_currency($run_bal); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function editBank(bank) {
    document.getElementById('formTitle').innerHTML = '<i class="fa-solid fa-edit"></i> Edit Bank Account';
    document.getElementById('f_id').value = bank.id;
    document.getElementById('f_bank_name').value = bank.bank_name;
    document.getElementById('f_account_title').value = bank.account_title;
    document.getElementById('f_account_number').value = bank.account_number;
    document.getElementById('f_branch_code').value = bank.branch_name;
    
    // Hide opening balance when editing
    document.getElementById('f_opening_group').style.display = 'none';
    
    const btnSubmit = document.getElementById('btnSubmitForm');
    btnSubmit.textContent = 'Update Bank';
    btnSubmit.classList.replace('btn-add-bank', 'btn-edit-bank');
    btnSubmit.classList.replace('bg-primary', 'bg-warning');
    
    document.getElementById('btnCancelEdit').classList.remove('d-none');
    
    // Scroll to form
    document.getElementById('bankForm').scrollIntoView({behavior: 'smooth', block: 'center'});
}

function cancelEdit() {
    document.getElementById('formTitle').innerHTML = '<i class="fa-solid fa-circle-plus"></i> Add New Bank';
    document.getElementById('f_id').value = '0';
    document.getElementById('bankForm').reset();
    
    document.getElementById('f_opening_group').style.display = 'block';
    
    const btnSubmit = document.getElementById('btnSubmitForm');
    btnSubmit.textContent = 'Add Bank';
    btnSubmit.classList.replace('btn-edit-bank', 'btn-add-bank');
    
    document.getElementById('btnCancelEdit').classList.add('d-none');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
