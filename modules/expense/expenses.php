<?php
$page_title = "Expenses List";
require_once __DIR__ . '/../../includes/header.php';

$success_msg = "";
$error_msg = "";

// 1. Handle Delete Expense
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    if ($del_id > 0 && $db_connected && $pdo) {
        try {
            $pdo->beginTransaction();

            // Fetch expense details for ledger cleanup
            $stmt_info = $pdo->prepare("SELECT voucher_no FROM expenses WHERE id = :id");
            $stmt_info->execute(['id' => $del_id]);
            $v_no = $stmt_info->fetchColumn();

            // Delete ledger entry if exists
            if (!empty($v_no)) {
                try {
                    $pdo->prepare("DELETE FROM account_ledgers WHERE reference_no = :vno AND transaction_type = 'Expense'")->execute(['vno' => $v_no]);
                } catch (Exception $le) {}
            }

            // Delete expense
            $stmt_del = $pdo->prepare("DELETE FROM expenses WHERE id = :id");
            $stmt_del->execute(['id' => $del_id]);

            $pdo->commit();
            $success_msg = "Kharcha kamyabi se delete ho gaya!";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error_msg = "Delete fail ho gaya: " . $e->getMessage();
        }
    }
}

// 2. Filters
$from_date   = trim($_GET['from_date'] ?? date('Y-m-01'));
$to_date     = trim($_GET['to_date'] ?? date('Y-m-d'));
$cat_filter  = trim($_GET['category'] ?? '');
$search      = trim($_GET['search'] ?? '');

$expenses = [];
$total_expense_amount = 0.00;
$today_expense_amount = 0.00;
$categories_list = [];

if ($db_connected && $pdo) {
    try {
        $where = ["1=1"];
        $params = [];

        if (!empty($from_date)) {
            $where[] = "e.expense_date >= :from_date";
            $params['from_date'] = $from_date;
        }
        if (!empty($to_date)) {
            $where[] = "e.expense_date <= :to_date";
            $params['to_date'] = $to_date;
        }
        if (!empty($cat_filter)) {
            $where[] = "c.name = :cat_filter";
            $params['cat_filter'] = $cat_filter;
        }
        if (!empty($search)) {
            $where[] = "(e.voucher_no LIKE :s OR e.description LIKE :s OR e.payee_name LIKE :s OR e.receipt_no LIKE :s OR c.name LIKE :s)";
            $params['s'] = "%{$search}%";
        }

        $where_sql = implode(' AND ', $where);

        $sql = "
            SELECT e.*, 
                   COALESCE(c.name, 'General') as category_name,
                   ca.account_name as cash_acc_name,
                   ba.bank_name, ba.account_title as bank_acc_title
            FROM expenses e
            LEFT JOIN expense_categories c ON e.category_id = c.id
            LEFT JOIN cash_accounts ca ON e.cash_account_id = ca.id
            LEFT JOIN bank_accounts ba ON e.bank_account_id = ba.id
            WHERE {$where_sql}
            ORDER BY e.expense_date DESC, e.id DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $expenses = $stmt->fetchAll();

        foreach ($expenses as $exp) {
            $total_expense_amount += (float)$exp['amount'];
            if ($exp['expense_date'] === date('Y-m-d')) {
                $today_expense_amount += (float)$exp['amount'];
            }
        }

        // Fetch categories for dropdown filter
        $categories_list = $pdo->query("SELECT DISTINCT name FROM expense_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);

    } catch (Exception $e) {
        $error_msg = "Database Error: " . $e->getMessage();
    }
}

?>

<!-- Header & Actions -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 no-print">
    <div>
        <h4 class="fw-bold mb-0 text-dark">
            <i class="fa-solid fa-receipt text-primary me-2"></i>View Expenses 
        </h4>
        <p class="text-muted small mb-0">Manage, review, and filter all wholesale business expenses</p>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-light border bg-white shadow-sm fw-semibold">
            <i class="fa-solid fa-print me-1"></i> Print
        </button>
        <a href="<?php echo BASE_URL; ?>modules/expense/add_expense.php" class="btn btn-primary fw-semibold shadow-sm">
            <i class="fa-solid fa-plus me-1"></i> Add New Expense
        </a>
    </div>
</div>

<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" style="border-radius: 12px;">
        <i class="fa-solid fa-check-circle me-2"></i><?php echo htmlspecialchars($success_msg); ?>
        <button type="button" class="close ml-auto" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
<?php endif; ?>

<?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" style="border-radius: 12px;">
        <i class="fa-solid fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_msg); ?>
        <button type="button" class="close ml-auto" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
<?php endif; ?>

<!-- Summary Stat Cards -->
<div class="row g-3 mb-4 no-print">
    <div class="col-12 col-sm-6 col-xl-4">
        <div class="card border-0 shadow-sm p-3 h-100" style="border-radius: 16px;">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold">Filtered Total</span>
                    <h3 class="fw-bolder text-danger mb-0 mt-1"><?php echo format_currency($total_expense_amount); ?></h3>
                </div>
                <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                    <i class="fa-solid fa-exchange-alt fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-4">
        <div class="card border-0 shadow-sm p-3 h-100" style="border-radius: 16px;">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold">Today's Expenses</span>
                    <h3 class="fw-bolder text-primary mb-0 mt-1"><?php echo format_currency($today_expense_amount); ?></h3>
                </div>
                <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                    <i class="fa-regular fa-calendar-check fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-4">
        <div class="card border-0 shadow-sm p-3 h-100" style="border-radius: 16px;">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold">Total Records</span>
                    <h3 class="fw-bolder text-dark mb-0 mt-1"><?php echo count($expenses); ?></h3>
                </div>
                <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                    <i class="fa-solid fa-file-lines fs-4"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4 no-print" style="border-radius: 16px;">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">From Date</label>
                <input type="date" name="from_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($from_date); ?>">
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">To Date</label>
                <input type="date" name="to_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($to_date); ?>">
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">Category</label>
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <?php foreach ($categories_list as $cl): ?>
                        <option value="<?php echo htmlspecialchars($cl); ?>" <?php echo ($cat_filter === $cl) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cl); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Voucher, Note..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary w-100 fw-bold">
                    <i class="fa-solid fa-filter me-1"></i> Filter
                </button>
                <a href="<?php echo BASE_URL; ?>modules/expense/expenses.php" class="btn btn-sm btn-light border" title="Reset Filters">
                    <i class="fa-solid fa-undo"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Table Card -->
<div class="card border-0 shadow-sm" style="border-radius: 18px; overflow: hidden;">
    <div class="card-header bg-white py-3 px-4 d-flex align-items-center justify-content-between border-bottom">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="fa-solid fa-list-check text-muted me-2"></i>Expense Vouchers
        </h6>
        <span class="badge bg-light text-dark border px-3 py-1"><?php echo count($expenses); ?> Vouchers</span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 12%;">Voucher #</th>
                    <th style="width: 10%;">Date</th>
                    <th style="width: 16%;">Category</th>
                    <th style="width: 24%;">Description / Title</th>
                    <th style="width: 16%;">Paid From (System Account)</th>
                    <th class="text-end" style="width: 12%;">Amount</th>
                    <th class="text-center no-print" style="width: 10%;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($expenses)): ?>
                    <?php foreach ($expenses as $row): ?>
                        <tr>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace fw-bold">
                                    <?php echo htmlspecialchars($row['voucher_no']); ?>
                                </span>
                            </td>
                            <td class="text-nowrap small text-muted">
                                <?php echo date('d-M-Y', strtotime($row['expense_date'])); ?>
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary fw-semibold px-2 py-1 rounded-pill">
                                    <i class="fa-solid fa-tag me-1"></i>
                                    <?php echo htmlspecialchars($row['category_name']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?php echo htmlspecialchars($row['description']); ?></div>
                                <?php if (!empty($row['receipt_no'])): ?>
                                    <small class="text-muted"><i class="fa-solid fa-receipt me-1"></i>Ref: <?php echo htmlspecialchars($row['receipt_no']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['payment_method'] === 'Bank'): ?>
                                    <div class="small fw-semibold text-dark">
                                        <i class="fa-solid fa-building-columns text-info me-1"></i>
                                        <?php echo htmlspecialchars($row['bank_name'] ?? 'Bank'); ?>
                                    </div>
                                    <small class="text-muted"><?php echo htmlspecialchars($row['bank_acc_title'] ?? ''); ?></small>
                                <?php else: ?>
                                    <div class="small fw-semibold text-dark">
                                        <i class="fa-solid fa-money-bill-alt text-success me-1"></i>
                                        <?php echo htmlspecialchars($row['cash_acc_name'] ?? 'Main Cash Drawer'); ?>
                                    </div>
                                    <small class="text-muted">Cash Account</small>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-bolder text-danger fs-6">
                                <?php echo format_currency($row['amount']); ?>
                            </td>
                            <td class="text-center no-print">
                                <div class="btn-group btn-group-sm">
                                    <!-- View Button -->
                                    <button type="button" 
                                            class="btn btn-outline-secondary" 
                                            title="View Expense Details" 
                                            onclick='openViewModal(<?php echo htmlspecialchars(json_encode([
                                                "id" => $row["id"],
                                                "voucher_no" => $row["voucher_no"],
                                                "date" => date("d-M-Y", strtotime($row["expense_date"])),
                                                "category" => $row["category_name"],
                                                "description" => $row["description"],
                                                "amount" => format_currency($row["amount"]),
                                                "raw_amount" => (float)$row["amount"],
                                                "payment_method" => $row["payment_method"],
                                                "account" => ($row["payment_method"] === "Bank" ? ($row["bank_name"] ?? "Bank") . " (" . ($row["bank_acc_title"] ?? "") . ")" : ($row["cash_acc_name"] ?? "Main Cash Drawer")),
                                                "payee" => $row["payee_name"] ?: "-",
                                                "receipt_no" => $row["receipt_no"] ?: "-"
                                            ], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, "UTF-8"); ?>)'>
                                        <i class="fa-solid fa-eye text-info"></i>
                                    </button>

                                    <!-- Print Button -->
                                    <a href="<?php echo BASE_URL; ?>modules/expense/print_expense.php?id=<?php echo $row['id']; ?>&autoprint=1" 
                                       target="_blank" 
                                       class="btn btn-outline-secondary" 
                                       title="Print Voucher">
                                        <i class="fa-solid fa-print text-dark"></i>
                                    </a>

                                    <!-- Edit Button -->
                                    <a href="<?php echo BASE_URL; ?>modules/expense/edit_expense.php?id=<?php echo $row['id']; ?>" 
                                       class="btn btn-outline-secondary" 
                                       title="Edit Expense">
                                        <i class="fa-solid fa-edit text-primary"></i>
                                    </a>

                                    <!-- Delete Button -->
                                    <a href="<?php echo BASE_URL; ?>modules/expense/expenses.php?delete_id=<?php echo $row['id']; ?>" 
                                       class="btn btn-outline-secondary" 
                                       title="Delete Expense" 
                                       onclick="return confirm('Are you sure you want to delete this expense?');">
                                        <i class="fa-solid fa-trash text-danger"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-box-open fs-1 text-light mb-2 d-block"></i>
                            No expense records found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($expenses)): ?>
            <tfoot class="table-light">
                <tr class="fw-bold">
                    <td colspan="5" class="text-end fs-6">Total Expenses:</td>
                    <td class="text-end text-danger fs-5"><?php echo format_currency($total_expense_amount); ?></td>
                    <td class="no-print"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- View Expense Details Modal -->
<div class="modal fade" id="viewExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 18px; overflow: hidden;">
            <div class="modal-header bg-light border-bottom py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary text-white fw-bold px-3 py-2 rounded-pill font-monospace" id="modalVoucher">EXP-...</span>
                    <span class="badge bg-white text-dark border px-2 py-1" id="modalDate"></span>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <div class="text-muted small fw-bold text-uppercase">Total Expense Amount</div>
                    <h2 class="fw-bolder text-danger font-monospace mt-1 mb-0" id="modalAmount">Rs. 0.00</h2>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <tbody>
                            <tr>
                                <th class="bg-light text-muted small" style="width: 38%;">Category</th>
                                <td id="modalCategory" class="fw-bold text-primary"></td>
                            </tr>
                            <tr>
                                <th class="bg-light text-muted small">Description / Title</th>
                                <td id="modalDescription" class="fw-semibold text-dark"></td>
                            </tr>
                            <tr>
                                <th class="bg-light text-muted small">Payment Source</th>
                                <td id="modalAccount" class="fw-semibold"></td>
                            </tr>
                            <tr>
                                <th class="bg-light text-muted small">Bill / Ref #</th>
                                <td id="modalRef" class="font-monospace"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light border-top py-2 px-4 d-flex justify-content-between">
                <button type="button" class="btn btn-light border" data-dismiss="modal">Close</button>
                <div class="d-flex gap-2">
                    <a href="#" id="modalPrintBtn" target="_blank" class="btn btn-primary fw-semibold px-3" style="background-color: #0284c7; border-color: #0284c7;">
                        <i class="fa-solid fa-print me-1"></i> Print Voucher
                    </a>
                    <a href="#" id="modalEditBtn" class="btn btn-outline-primary fw-semibold px-3">
                        <i class="fa-solid fa-edit me-1"></i> Edit
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let expenseModalInstance = null;
function openViewModal(data) {
    document.getElementById('modalVoucher').textContent = data.voucher_no;
    document.getElementById('modalDate').textContent = data.date;
    document.getElementById('modalAmount').textContent = data.amount;
    document.getElementById('modalCategory').textContent = data.category;
    document.getElementById('modalDescription').textContent = data.description;
    document.getElementById('modalAccount').textContent = data.account;
    document.getElementById('modalRef').textContent = data.receipt_no;
    
    document.getElementById('modalPrintBtn').href = '<?php echo BASE_URL; ?>modules/expense/print_expense.php?id=' + data.id + '&autoprint=1';
    document.getElementById('modalEditBtn').href = '<?php echo BASE_URL; ?>modules/expense/edit_expense.php?id=' + data.id;
    
    if (!expenseModalInstance) {
        expenseModalInstance = { show: () => jQuery('#viewExpenseModal').modal('show'), hide: () => jQuery('#viewExpenseModal').modal('hide'), toggle: () => jQuery('#viewExpenseModal').modal('toggle') };
    }
    expenseModalInstance.show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
