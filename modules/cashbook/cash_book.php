<?php
$page_title = "Daily Cash Book";
require_once __DIR__ . '/../../includes/header.php';

$filter_date = trim($_GET['date'] ?? date('Y-m-d'));
$cash_account_id = 1; // Assuming 1 is the main cash account

$success_msg = "";
$error_msg = "";

$opening_balance = 0.00;
$total_in = 0.00;
$total_out = 0.00;
$closing_balance = 0.00;
$transactions = [];

if ($db_connected && $pdo) {
    try {
        // Calculate Opening Balance (Prior to filter_date)
        $stmt_open = $pdo->prepare("
            SELECT COALESCE(SUM(debit_amount - credit_amount), 0) 
            FROM account_ledgers 
            WHERE account_type = 'Cash' AND account_id = :id AND transaction_date < :fdate
        ");
        $stmt_open->execute([
            'id' => $cash_account_id,
            'fdate' => $filter_date
        ]);
        $opening_balance = (float)$stmt_open->fetchColumn();

        // Fetch transactions for the selected date
        $stmt_trans = $pdo->prepare("
            SELECT * FROM account_ledgers 
            WHERE account_type = 'Cash' AND account_id = :id AND transaction_date = :fdate
            ORDER BY id ASC
        ");
        $stmt_trans->execute([
            'id' => $cash_account_id,
            'fdate' => $filter_date
        ]);
        $transactions = $stmt_trans->fetchAll();

    } catch (Exception $e) {
        $error_msg = "Database Error: " . $e->getMessage();
    }
}

?>

<div class="d-flex align-items-center justify-content-between mb-4 no-print">
    <h4 class="fw-bold mb-0"><i class="fa-solid fa-book text-primary me-2"></i>Daily Cash Book</h4>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary">
            <i class="fa-solid fa-print me-1"></i> Print Cash Book
        </button>
        <a href="<?php echo BASE_URL; ?>modules/accounts/fund_transfer.php" class="btn btn-primary">
            <i class="fa-solid fa-right-left me-1"></i> Transfer Cash
        </a>
    </div>
</div>

<!-- Date Filter -->
<div class="custom-card mb-4 no-print">
    <div class="p-3">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold small text-muted">Select Date</label>
                <input type="date" name="date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_date); ?>" required>
            </div>
            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">
                    <i class="fa-solid fa-filter me-1"></i> View Cash Book
                </button>
            </div>
        </form>
    </div>
</div>

<div class="custom-card print-friendly">
    <div class="custom-card-header bg-white d-flex justify-content-between align-items-center border-bottom">
        <h5 class="custom-card-title mb-0 fs-6"><i class="fa-solid fa-money-bill-alt text-success"></i> Cash Transactions</h5>
        <span class="fw-bold fs-6 text-dark"><?php echo date('l, d F Y', strtotime($filter_date)); ?></span>
    </div>
    
    <div class="table-responsive">
        <table class="table table-custom table-bordered mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 15%;">Time / Ref</th>
                    <th style="width: 15%;">Type</th>
                    <th style="width: 34%;">Description / Narration</th>
                    <th class="text-end text-success" style="width: 12%;">Cash In (+)</th>
                    <th class="text-end text-danger" style="width: 12%;">Cash Out (-)</th>
                    <th class="text-end" style="width: 12%;">Balance</th>
                </tr>
            </thead>
            <tbody>
                <!-- Opening Balance -->
                <tr class="table-light">
                    <td colspan="3" class="text-end fw-bold">Opening Balance Brought Forward:</td>
                    <td class="text-end">-</td>
                    <td class="text-end">-</td>
                    <td class="text-end fw-bold fs-6 text-dark">
                        <?php echo format_currency($opening_balance); ?>
                    </td>
                </tr>

                <?php 
                $running = $opening_balance;
                if (!empty($transactions)): 
                    foreach ($transactions as $t): 
                        $in = (float)$t['debit_amount'];  // Debit to Cash = Increase
                        $out = (float)$t['credit_amount']; // Credit to Cash = Decrease
                        $running = $running + $in - $out;
                        $total_in += $in;
                        $total_out += $out;
                ?>
                <tr>
                    <td><span class="font-monospace small fw-bold"><?php echo htmlspecialchars($t['reference_no']); ?></span></td>
                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($t['transaction_type']); ?></span></td>
                    <td><?php echo htmlspecialchars($t['description']); ?></td>
                    <td class="text-end text-success fw-semibold"><?php echo ($in > 0) ? format_currency($in) : '-'; ?></td>
                    <td class="text-end text-danger fw-semibold"><?php echo ($out > 0) ? format_currency($out) : '-'; ?></td>
                    <td class="text-end fw-bold"><?php echo format_currency($running); ?></td>
                </tr>
                <?php 
                    endforeach; 
                else: 
                ?>
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
                        <i class="fa-solid fa-box-open fs-1 text-light mb-2 d-block"></i>
                        No cash transactions recorded for this date.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
            <tfoot class="table-light">
                <tr class="fw-bold">
                    <td colspan="3" class="text-end">Total for the Day:</td>
                    <td class="text-end text-success"><?php echo format_currency($total_in); ?></td>
                    <td class="text-end text-danger"><?php echo format_currency($total_out); ?></td>
                    <td class="text-end">-</td>
                </tr>
                <tr class="fw-bold border-top border-dark">
                    <td colspan="5" class="text-end fs-5">Closing Balance Carried Forward:</td>
                    <td class="text-end fs-5 <?php echo ($running < 0) ? 'text-danger' : 'text-primary'; ?>">
                        <?php echo format_currency($running); ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<style>
@media print {
    .print-friendly { border: none !important; box-shadow: none !important; }
    .table-custom th, .table-custom td { padding: 4px 8px !important; font-size: 12px; }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
