<?php
$page_title = "Booker Ledger";
require_once __DIR__ . '/../../includes/header.php';

$booker_id = (int)($_GET['booker_id'] ?? ($_POST['booker_id'] ?? 0));
$from_date = trim($_GET['from_date'] ?? ($_POST['from_date'] ?? date('Y-m-01')));
$to_date = trim($_GET['to_date'] ?? ($_POST['to_date'] ?? date('Y-m-d')));

$success_msg = "";
$error_msg = "";

// Handle Edit/Delete POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'edit_entry') {
        $entry_id = (int)($_POST['entry_id'] ?? 0);
        $t_date = trim($_POST['transaction_date'] ?? '');
        $t_ref = trim($_POST['reference_no'] ?? '');
        $t_type = trim($_POST['transaction_type'] ?? '');
        $t_debit = (float)($_POST['debit_amount'] ?? 0.00);
        $t_credit = (float)($_POST['credit_amount'] ?? 0.00);
        $t_desc = trim($_POST['description'] ?? '');

        if ($entry_id > 0 && $booker_id > 0 && $db_connected && $pdo) {
            try {
                $pdo->beginTransaction();

                $stmt_upd = $pdo->prepare("UPDATE booker_ledgers SET 
                    transaction_date = :t_date,
                    reference_no = :t_ref,
                    transaction_type = :t_type,
                    debit_amount = :debit,
                    credit_amount = :credit,
                    description = :t_desc
                    WHERE id = :id AND booker_id = :bid");
                $stmt_upd->execute([
                    't_date' => $t_date,
                    't_ref' => $t_ref,
                    't_type' => $t_type,
                    'debit' => $t_debit,
                    'credit' => $t_credit,
                    't_desc' => $t_desc,
                    'id' => $entry_id,
                    'bid' => $booker_id
                ]);

                // Recalculate running balance
                $stmt_rows = $pdo->prepare("SELECT id, debit_amount, credit_amount FROM booker_ledgers WHERE booker_id = :bid ORDER BY transaction_date ASC, id ASC");
                $stmt_rows->execute(['bid' => $booker_id]);
                $all_rows = $stmt_rows->fetchAll();
                $cum_bal = 0.00;
                $stmt_upd_rb = $pdo->prepare("UPDATE booker_ledgers SET running_balance = :rb WHERE id = :id");
                foreach ($all_rows as $r) {
                    $cum_bal += ((float)$r['debit_amount'] - (float)$r['credit_amount']);
                    $stmt_upd_rb->execute(['rb' => $cum_bal, 'id' => $r['id']]);
                }

                $stmt_cust_bal = $pdo->prepare("UPDATE bookers SET current_balance = :bal WHERE id = :bid");
                $stmt_cust_bal->execute(['bal' => $cum_bal, 'bid' => $booker_id]);

                $pdo->commit();
                $success_msg = "Ledger transaction <strong>" . htmlspecialchars($t_ref) . "</strong> updated successfully!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_msg = "Error updating entry: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'delete_entry') {
        $entry_id = (int)($_POST['entry_id'] ?? 0);
        if ($entry_id > 0 && $booker_id > 0 && $db_connected && $pdo) {
            try {
                $pdo->beginTransaction();
                
                $stmt_chk = $pdo->prepare("SELECT * FROM booker_ledgers WHERE id = :id AND booker_id = :bid");
                $stmt_chk->execute(['id' => $entry_id, 'bid' => $booker_id]);
                $entry = $stmt_chk->fetch();

                if ($entry) {
                    if ($entry['transaction_type'] === 'Opening Balance') {
                        throw new Exception("Cannot delete Opening Balance entry.");
                    }

                    $stmt_del = $pdo->prepare("DELETE FROM booker_ledgers WHERE id = :id");
                    $stmt_del->execute(['id' => $entry_id]);

                    // Recalculate
                    $stmt_rows = $pdo->prepare("SELECT id, debit_amount, credit_amount FROM booker_ledgers WHERE booker_id = :bid ORDER BY transaction_date ASC, id ASC");
                    $stmt_rows->execute(['bid' => $booker_id]);
                    $all_rows = $stmt_rows->fetchAll();
                    $cum_bal = 0.00;
                    $stmt_upd_rb = $pdo->prepare("UPDATE booker_ledgers SET running_balance = :rb WHERE id = :id");
                    foreach ($all_rows as $r) {
                        $cum_bal += ((float)$r['debit_amount'] - (float)$r['credit_amount']);
                        $stmt_upd_rb->execute(['rb' => $cum_bal, 'id' => $r['id']]);
                    }

                    $stmt_cust_bal = $pdo->prepare("UPDATE bookers SET current_balance = :bal WHERE id = :bid");
                    $stmt_cust_bal->execute(['bal' => $cum_bal, 'bid' => $booker_id]);

                    $pdo->commit();
                    $success_msg = "Ledger entry deleted successfully!";
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_msg = $e->getMessage();
            }
        }
    }
}

// Fetch bookers for dropdown
$bookers = [];
$selected_booker = null;
$ledger_entries = [];
$total_debit = 0.00;
$total_credit = 0.00;
$opening_balance = 0.00;

if ($db_connected && $pdo) {
    try {
        $stmt_b = $pdo->query("SELECT id, booker_code, name, phone, current_balance FROM bookers ORDER BY name ASC");
        $bookers = $stmt_b->fetchAll();

        if ($booker_id > 0) {
            $stmt_sel = $pdo->prepare("SELECT * FROM bookers WHERE id = :id");
            $stmt_sel->execute(['id' => $booker_id]);
            $selected_booker = $stmt_sel->fetch();

            if ($selected_booker) {
                // Calculate opening balance prior to from_date
                $stmt_open = $pdo->prepare("SELECT COALESCE(SUM(debit_amount - credit_amount), 0) as prior_bal 
                                            FROM booker_ledgers 
                                            WHERE booker_id = :id AND transaction_date < :from_date");
                $stmt_open->execute([
                    'id' => $booker_id,
                    'from_date' => $from_date
                ]);
                $opening_balance = (float)$stmt_open->fetchColumn();

                // Fetch ledger records within date range
                $stmt_led = $pdo->prepare("SELECT * FROM booker_ledgers 
                                           WHERE booker_id = :id AND transaction_date >= :from_date AND transaction_date <= :to_date 
                                           ORDER BY transaction_date ASC, id ASC");
                $stmt_led->execute([
                    'id' => $booker_id,
                    'from_date' => $from_date,
                    'to_date' => $to_date
                ]);
                $ledger_entries = $stmt_led->fetchAll();
            }
        }
    } catch (Exception $e) {}
}

?>

<div class="d-flex align-items-center justify-content-between mb-4 no-print">
    <h4 class="fw-bold mb-0"><i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i>Booker Ledger</h4>
    <div class="d-flex align-items-center gap-2">
        <?php if ($selected_booker): ?>
            <a href="print_booker_ledger.php?booker_id=<?php echo $selected_booker['id']; ?>&from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>" target="_blank" class="btn btn-outline-secondary">
                <i class="fa-solid fa-print me-1"></i> Print Ledger
            </a>
            <a href="<?php echo BASE_URL; ?>modules/booker/receive_amount.php?booker_id=<?php echo $selected_booker['id']; ?>" class="btn btn-success">
                <i class="fa-solid fa-hand-holding-usd me-1"></i> Receive Cash
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
        <i class="fa-solid fa-check-circle me-2"></i><?php echo $success_msg; ?>
        <button type="button" class="close ml-auto" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
<?php endif; ?>
<?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
        <i class="fa-solid fa-exclamation-triangle me-2"></i><?php echo $error_msg; ?>
        <button type="button" class="close ml-auto" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
<?php endif; ?>

<!-- Filter Section -->
<div class="custom-card mb-4 no-print">
    <div class="p-3">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold small text-muted">Select Booker</label>
                <select name="booker_id" class="form-select form-select-sm" required>
                    <option value="">-- Choose Booker --</option>
                    <?php foreach ($bookers as $b): ?>
                        <option value="<?php echo $b['id']; ?>" <?php echo ($booker_id == $b['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($b['name']); ?> (Bal: <?php echo format_currency($b['current_balance']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label fw-semibold small text-muted">From Date</label>
                <input type="date" name="from_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($from_date); ?>" required>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label fw-semibold small text-muted">To Date</label>
                <input type="date" name="to_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($to_date); ?>" required>
            </div>
            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">
                    <i class="fa-solid fa-filter me-1"></i> View Ledger
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($selected_booker): ?>
    
    <!-- Screen Profile Strip -->
    <div class="custom-card mb-4 no-print bg-light">
        <div class="p-3 d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h5 class="mb-1 fw-bold text-dark"><?php echo htmlspecialchars($selected_booker['name']); ?></h5>
                <p class="mb-0 small text-muted">
                    <i class="fa-solid fa-phone me-1"></i> <?php echo htmlspecialchars($selected_booker['phone']); ?> | 
                    <span class="font-monospace text-primary fw-bold">Code: <?php echo htmlspecialchars($selected_booker['booker_code'] ?? '-'); ?></span>
                </p>
            </div>
            <div class="text-end mt-2 mt-md-0 border-start ps-4">
                <span class="d-block small text-muted fw-semibold mb-1">Total Due Balance (PKR)</span>
                <h3 class="mb-0 <?php echo ((float)$selected_booker['current_balance'] > 0) ? 'text-danger' : 'text-success'; ?> fw-bold">
                    <?php echo format_currency($selected_booker['current_balance']); ?>
                </h3>
            </div>
        </div>
    </div>

    <!-- Ledger Table -->
    <div class="custom-card">
        <div class="custom-card-header bg-white d-flex justify-content-between align-items-center border-bottom">
            <h5 class="custom-card-title mb-0 fs-6"><i class="fa-solid fa-list text-primary"></i> Statement of Account</h5>
            <span class="small text-muted fw-bold">Period: <?php echo date('d M Y', strtotime($from_date)); ?> to <?php echo date('d M Y', strtotime($to_date)); ?></span>
        </div>
        <div class="table-responsive">
            <table class="table table-custom mb-0">
                <thead>
                    <tr>
                        <th style="width: 10%;">Date</th>
                        <th style="width: 15%;">Ref / Receipt #</th>
                        <th style="width: 15%;">Type</th>
                        <th style="width: 20%;">Description</th>
                        <th class="text-end" style="width: 12%;">Debit (Charge)</th>
                        <th class="text-end" style="width: 12%;">Credit (Deposit)</th>
                        <th class="text-end" style="width: 12%;">Balance</th>
                        <th class="text-center no-print" style="width: 8%;">Act</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-light">
                        <td><strong><?php echo date('d-m-Y', strtotime($from_date)); ?></strong></td>
                        <td><span class="badge bg-secondary font-monospace">B/F</span></td>
                        <td><strong>Brought Forward</strong></td>
                        <td><em>Opening Balance prior to <?php echo date('d-m-Y', strtotime($from_date)); ?></em></td>
                        <td class="text-end">-</td>
                        <td class="text-end">-</td>
                        <td class="text-end fw-bold">
                            <?php echo format_currency($opening_balance); ?>
                        </td>
                        <td class="no-print"></td>
                    </tr>

                    <?php 
                    $running = $opening_balance;
                    if (!empty($ledger_entries)): 
                        foreach ($ledger_entries as $entry): 
                            $debit = (float)$entry['debit_amount'];
                            $credit = (float)$entry['credit_amount'];
                            $running = $running + $debit - $credit;
                            $total_debit += $debit;
                            $total_credit += $credit;
                    ?>
                        <tr>
                            <td><?php echo date('d-m-Y', strtotime($entry['transaction_date'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($entry['reference_no']); ?></strong></td>
                            <td>
                                <?php if ($entry['transaction_type'] == 'Recovery Allocation'): ?>
                                    <span class="badge bg-danger rounded-pill px-2"><i class="fa-solid fa-file-invoice me-1"></i><?php echo htmlspecialchars($entry['transaction_type']); ?></span>
                                <?php elseif ($entry['transaction_type'] == 'Cash Deposited'): ?>
                                    <span class="badge bg-success rounded-pill px-2"><i class="fa-solid fa-money-bill-alt me-1"></i><?php echo htmlspecialchars($entry['transaction_type']); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary rounded-pill px-2"><?php echo htmlspecialchars($entry['transaction_type']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($entry['description'] ?? '-'); ?></td>
                            <td class="text-end text-danger fw-semibold">
                                <?php echo ($debit > 0) ? format_currency($debit) : '-'; ?>
                            </td>
                            <td class="text-end text-success fw-semibold">
                                <?php echo ($credit > 0) ? format_currency($credit) : '-'; ?>
                            </td>
                            <td class="text-end fw-bold">
                                <?php echo format_currency($running); ?>
                            </td>
                            <td class="text-center no-print">
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-outline-info py-0 px-2 btn-view-entry" 
                                        title="View Details"
                                        data-date="<?php echo date('d-m-Y', strtotime($entry['transaction_date'])); ?>"
                                        data-ref="<?php echo htmlspecialchars($entry['reference_no']); ?>"
                                        data-type="<?php echo htmlspecialchars($entry['transaction_type']); ?>"
                                        data-desc="<?php echo htmlspecialchars($entry['description'] ?? '-'); ?>"
                                        data-debit="<?php echo format_currency($debit); ?>"
                                        data-credit="<?php echo format_currency($credit); ?>"
                                        data-bal="<?php echo format_currency($running); ?>">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-primary py-0 px-2 btn-edit-entry" 
                                        title="Edit Entry"
                                        data-id="<?php echo $entry['id']; ?>"
                                        data-date="<?php echo $entry['transaction_date']; ?>"
                                        data-ref="<?php echo htmlspecialchars($entry['reference_no']); ?>"
                                        data-type="<?php echo htmlspecialchars($entry['transaction_type']); ?>"
                                        data-desc="<?php echo htmlspecialchars($entry['description'] ?? ''); ?>"
                                        data-debit="<?php echo number_format($debit, 2, '.', ''); ?>"
                                        data-credit="<?php echo number_format($credit, 2, '.', ''); ?>">
                                        <i class="fa-solid fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger py-0 px-2 btn-delete-entry" 
                                        title="Delete Entry"
                                        data-id="<?php echo $entry['id']; ?>"
                                        data-ref="<?php echo htmlspecialchars($entry['reference_no']); ?>"
                                        data-type="<?php echo htmlspecialchars($entry['transaction_type']); ?>"
                                        data-amount="<?php echo ($debit > 0) ? format_currency($debit) : format_currency($credit); ?>">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php 
                        endforeach; 
                    else: 
                    ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-file-invoice-dollar fs-1 text-light mb-2 d-block"></i>
                                No transaction records found for this period.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="fw-bold table-light">
                        <td colspan="4" class="text-end">Period Totals:</td>
                        <td class="text-end text-danger"><?php echo format_currency($total_debit); ?></td>
                        <td class="text-end text-success"><?php echo format_currency($total_credit); ?></td>
                        <td class="text-end">
                            <?php echo format_currency($running); ?>
                        </td>
                        <td class="no-print"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Modals for View, Edit, Delete -->

    <!-- View Entry Modal -->
    <div class="modal fade" id="viewEntryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-file-lines text-info me-2"></i> Transaction Details</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body px-4 py-4">
                    <div class="row mb-3 border-bottom pb-2">
                        <div class="col-5 text-muted fw-semibold small">Date</div>
                        <div class="col-7 fw-bold" id="v_date"></div>
                    </div>
                    <div class="row mb-3 border-bottom pb-2">
                        <div class="col-5 text-muted fw-semibold small">Reference #</div>
                        <div class="col-7 fw-bold font-monospace" id="v_ref"></div>
                    </div>
                    <div class="row mb-3 border-bottom pb-2">
                        <div class="col-5 text-muted fw-semibold small">Transaction Type</div>
                        <div class="col-7 fw-bold" id="v_type"></div>
                    </div>
                    <div class="row mb-3 border-bottom pb-2">
                        <div class="col-5 text-muted fw-semibold small">Description</div>
                        <div class="col-7" id="v_desc"></div>
                    </div>
                    <div class="row mb-3 border-bottom pb-2">
                        <div class="col-5 text-danger fw-semibold small">Debit (Charge)</div>
                        <div class="col-7 text-danger fw-bold" id="v_debit"></div>
                    </div>
                    <div class="row mb-3 border-bottom pb-2">
                        <div class="col-5 text-success fw-semibold small">Credit (Deposit)</div>
                        <div class="col-7 text-success fw-bold" id="v_credit"></div>
                    </div>
                    <div class="row">
                        <div class="col-5 text-dark fw-bold">Running Balance</div>
                        <div class="col-7 text-dark fw-bold" id="v_bal"></div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Entry Modal -->
    <div class="modal fade" id="editEntryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="edit_entry">
                    <input type="hidden" name="entry_id" id="edit_entry_id">
                    <input type="hidden" name="booker_id" value="<?php echo $booker_id; ?>">
                    <input type="hidden" name="from_date" value="<?php echo $from_date; ?>">
                    <input type="hidden" name="to_date" value="<?php echo $to_date; ?>">
                    
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fa-solid fa-edit text-primary me-2"></i> Edit Ledger Entry</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Date</label>
                                <input type="date" name="transaction_date" id="e_date" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Reference #</label>
                                <input type="text" name="reference_no" id="e_ref" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Transaction Type</label>
                                <select name="transaction_type" id="e_type" class="form-select form-select-sm" required>
                                    <option value="Opening Balance">Opening Balance</option>
                                    <option value="Recovery Allocation">Recovery Allocation</option>
                                    <option value="Cash Deposited">Cash Deposited</option>
                                    <option value="Discount / Adjustment">Discount / Adjustment</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold text-danger">Debit (Charge)</label>
                                <input type="number" step="0.01" name="debit_amount" id="e_debit" class="form-control form-control-sm">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold text-success">Credit (Deposit)</label>
                                <input type="number" step="0.01" name="credit_amount" id="e_credit" class="form-control form-control-sm">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Description</label>
                                <textarea name="description" id="e_desc" rows="2" class="form-control form-control-sm"></textarea>
                            </div>
                        </div>
                        <div class="alert alert-warning mt-3 mb-0 small">
                            <i class="fa-solid fa-exclamation-triangle me-1"></i> Modifying amounts will automatically recalculate the entire ledger balance.
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-light border" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Entry Modal -->
    <div class="modal fade" id="deleteEntryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content border-danger">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete_entry">
                    <input type="hidden" name="entry_id" id="delete_entry_id">
                    <input type="hidden" name="booker_id" value="<?php echo $booker_id; ?>">
                    <input type="hidden" name="from_date" value="<?php echo $from_date; ?>">
                    <input type="hidden" name="to_date" value="<?php echo $to_date; ?>">

                    <div class="modal-body text-center p-4">
                        <i class="fa-solid fa-exclamation-triangle text-danger display-4 mb-3"></i>
                        <h5 class="fw-bold mb-3">Delete Transaction?</h5>
                        <p class="small text-muted mb-1">Ref: <strong id="d_ref" class="text-dark"></strong></p>
                        <p class="small text-muted mb-1">Type: <strong id="d_type" class="text-dark"></strong></p>
                        <p class="small text-muted mb-4">Amount: <strong id="d_amount" class="text-danger"></strong></p>
                        <p class="small text-danger fw-semibold mb-0">This will recalculate the entire ledger balance. This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer bg-light p-2 d-flex justify-content-center">
                        <button type="button" class="btn btn-light btn-sm px-4" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm px-4 fw-bold">Yes, Delete It</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // View Modal Logic
        const viewBtns = document.querySelectorAll('.btn-view-entry');
        const viewModal = { show: () => jQuery('#viewEntryModal').modal('show'), hide: () => jQuery('#viewEntryModal').modal('hide'), toggle: () => jQuery('#viewEntryModal').modal('toggle') };
        
        viewBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('v_date').textContent = this.getAttribute('data-date');
                document.getElementById('v_ref').textContent = this.getAttribute('data-ref');
                document.getElementById('v_type').textContent = this.getAttribute('data-type');
                document.getElementById('v_desc').textContent = this.getAttribute('data-desc');
                document.getElementById('v_debit').textContent = this.getAttribute('data-debit');
                document.getElementById('v_credit').textContent = this.getAttribute('data-credit');
                document.getElementById('v_bal').textContent = this.getAttribute('data-bal');
                viewModal.show();
            });
        });

        // Edit Modal Logic
        const editBtns = document.querySelectorAll('.btn-edit-entry');
        const editModal = { show: () => jQuery('#editEntryModal').modal('show'), hide: () => jQuery('#editEntryModal').modal('hide'), toggle: () => jQuery('#editEntryModal').modal('toggle') };
        
        editBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('edit_entry_id').value = this.getAttribute('data-id');
                document.getElementById('e_date').value = this.getAttribute('data-date');
                document.getElementById('e_ref').value = this.getAttribute('data-ref');
                document.getElementById('e_type').value = this.getAttribute('data-type');
                document.getElementById('e_debit').value = this.getAttribute('data-debit');
                document.getElementById('e_credit').value = this.getAttribute('data-credit');
                document.getElementById('e_desc').value = this.getAttribute('data-desc');
                
                // Protect Opening Balance
                if (this.getAttribute('data-type') === 'Opening Balance') {
                    document.getElementById('e_type').disabled = true;
                } else {
                    document.getElementById('e_type').disabled = false;
                }
                
                editModal.show();
            });
        });

        // Delete Modal Logic
        const deleteBtns = document.querySelectorAll('.btn-delete-entry');
        const deleteModal = { show: () => jQuery('#deleteEntryModal').modal('show'), hide: () => jQuery('#deleteEntryModal').modal('hide'), toggle: () => jQuery('#deleteEntryModal').modal('toggle') };
        
        deleteBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('delete_entry_id').value = this.getAttribute('data-id');
                document.getElementById('d_ref').textContent = this.getAttribute('data-ref');
                document.getElementById('d_type').textContent = this.getAttribute('data-type');
                document.getElementById('d_amount').textContent = this.getAttribute('data-amount');
                deleteModal.show();
            });
        });
    });
    </script>

<?php elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($booker_id)): ?>
    <div class="alert alert-info border-info d-flex align-items-center">
        <i class="fa-solid fa-circle-info fs-4 me-3"></i>
        <div>
            Please select a Booker from the dropdown above and click <strong>"View Ledger"</strong> to generate the statement of account.
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
