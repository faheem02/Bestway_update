<?php
$page_title = "Edit Expense";
require_once __DIR__ . '/../../includes/header.php';

$success_msg = "";
$error_msg = "";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: ' . BASE_URL . 'modules/expense/expenses.php');
    exit;
}

// 1. Fetch Expense Record
$expense = null;
if ($db_connected && $pdo) {
    try {
        $stmt_exp = $pdo->prepare("
            SELECT e.*, c.name as category_name
            FROM expenses e
            LEFT JOIN expense_categories c ON e.category_id = c.id
            WHERE e.id = :id
        ");
        $stmt_exp->execute(['id' => $id]);
        $expense = $stmt_exp->fetch();
    } catch (Exception $e) {
        $error_msg = "Database Error: " . $e->getMessage();
    }
}

if (!$expense) {
    die("<div class='container mt-5 alert alert-danger'>Expense record not found! <a href='expenses.php'>Go Back</a></div>");
}

// 2. Fetch system payment accounts only
$cash_accounts = [];
$bank_accounts = [];
if ($db_connected && $pdo) {
    try {
        $cash_accounts = $pdo->query("SELECT id, account_name, balance FROM cash_accounts ORDER BY id ASC")->fetchAll();
        $bank_accounts = $pdo->query("SELECT id, bank_name, account_title, account_number FROM bank_accounts WHERE status = 'Active' ORDER BY bank_name ASC")->fetchAll();
    } catch (Exception $e) {}
}

// 3. Fetch existing categories for open field suggestions
$existing_categories = [];
if ($db_connected && $pdo) {
    try {
        $existing_categories = $pdo->query("SELECT id, name FROM expense_categories ORDER BY name ASC")->fetchAll();
    } catch (Exception $e) {}
}

// 4. Handle Form Update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expense_date    = trim($_POST['expense_date'] ?? date('Y-m-d'));
    $title           = trim($_POST['title'] ?? '');
    $category_input  = trim($_POST['category'] ?? '');
    $amount          = (float)($_POST['amount'] ?? 0);
    $payment_account = trim($_POST['payment_account'] ?? '');
    $notes           = trim($_POST['notes'] ?? '');

    $full_description = $title;
    if (!empty($notes)) {
        $full_description .= (!empty($full_description) ? ' - ' : '') . $notes;
    }

    $payment_method  = 'Cash';
    $cash_account_id = null;
    $bank_account_id = null;

    if (str_starts_with($payment_account, 'bank_')) {
        $payment_method  = 'Bank';
        $bank_account_id = (int)str_replace('bank_', '', $payment_account);
    } elseif (str_starts_with($payment_account, 'cash_')) {
        $payment_method  = 'Cash';
        $cash_account_id = (int)str_replace('cash_', '', $payment_account);
    }

    if (empty($title)) {
        $error_msg = "Please enter an expense title / description.";
    } elseif (empty($category_input)) {
        $error_msg = "Please enter or select a category.";
    } elseif ($amount <= 0) {
        $error_msg = "Please enter a valid expense amount greater than 0.";
    } elseif (empty($payment_account)) {
        $error_msg = "Please select a valid payment account added in the system.";
    } else {
        if ($db_connected && $pdo) {
            try {
                $pdo->beginTransaction();

                // Check or Create Category dynamically (Open Field)
                $cat_chk = $pdo->prepare("SELECT id FROM expense_categories WHERE LOWER(TRIM(name)) = LOWER(TRIM(:cname)) LIMIT 1");
                $cat_chk->execute(['cname' => $category_input]);
                $category_id = $cat_chk->fetchColumn();

                if (!$category_id) {
                    $ins_cat = $pdo->prepare("INSERT INTO expense_categories (name, status) VALUES (:cname, 'Active')");
                    $ins_cat->execute(['cname' => $category_input]);
                    $category_id = $pdo->lastInsertId();
                }

                // Update expense record (without payee and receipt_no)
                $upd_stmt = $pdo->prepare("
                    UPDATE expenses SET
                        expense_date = :expense_date,
                        category_id = :category_id,
                        amount = :amount,
                        payment_method = :payment_method,
                        cash_account_id = :cash_account_id,
                        bank_account_id = :bank_account_id,
                        description = :description
                    WHERE id = :id
                ");

                $upd_stmt->execute([
                    'expense_date'    => $expense_date,
                    'category_id'     => $category_id,
                    'amount'          => $amount,
                    'payment_method'  => $payment_method,
                    'cash_account_id' => $cash_account_id,
                    'bank_account_id' => $bank_account_id,
                    'description'     => $full_description,
                    'id'              => $id
                ]);

                // Update ledger
                try {
                    $upd_ledg = $pdo->prepare("
                        UPDATE account_ledgers SET
                            transaction_date = :tx_date,
                            account_type = :acc_type,
                            account_id = :acc_id,
                            description = :desc,
                            credit_amount = :amount
                        WHERE reference_no = :ref_no AND transaction_type = 'Expense'
                    ");
                    $upd_ledg->execute([
                        'tx_date'  => $expense_date,
                        'acc_type' => $payment_method,
                        'acc_id'   => ($payment_method === 'Bank' ? $bank_account_id : $cash_account_id),
                        'desc'     => "Expense: {$category_input} - {$full_description}",
                        'amount'   => $amount,
                        'ref_no'   => $expense['voucher_no']
                    ]);
                } catch (Exception $le) {}

                $pdo->commit();
                $success_msg = "Kharcha kamyabi se update ho gaya!";

                // Re-fetch updated record
                $stmt_exp->execute(['id' => $id]);
                $expense = $stmt_exp->fetch();

            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error_msg = "Update fail ho gaya: " . $e->getMessage();
            }
        }
    }
}

// Current account selection
$current_selected_account = "";
if ($expense['payment_method'] === 'Bank' && !empty($expense['bank_account_id'])) {
    $current_selected_account = 'bank_' . $expense['bank_account_id'];
} elseif (!empty($expense['cash_account_id'])) {
    $current_selected_account = 'cash_' . $expense['cash_account_id'];
}

?>

<div class="row justify-content-center">
    <div class="col-12 col-xl-10">
        
        <!-- Header -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <a href="<?php echo BASE_URL; ?>modules/expense/expenses.php" class="btn btn-light border bg-white shadow-sm fw-semibold">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back
                </a>
                <div>
                    <h4 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-edit text-primary me-2"></i>Edit Expense
                    </h4>
                    <p class="text-muted small mb-0">Update expense details, amount, category, or payment account</p>
                </div>
            </div>
            <div>
                <a href="<?php echo BASE_URL; ?>modules/expense/expenses.php" class="btn btn-outline-primary shadow-sm fw-semibold">
                    <i class="fa-solid fa-list me-1"></i> View All Expenses
                </a>
            </div>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 d-flex align-items-center gap-3 py-3 px-4 mb-4" style="border-radius: 14px;" role="alert">
                <i class="fa-solid fa-check-circle fs-4 text-success flex-shrink-0"></i>
                <div class="flex-grow-1">
                    <?php echo $success_msg; ?>
                </div>
                <a href="expenses.php" class="btn btn-sm btn-success fw-bold px-3">View List</a>
                <button type="button" class="close ml-auto" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 d-flex align-items-center gap-3 py-3 px-4 mb-4" style="border-radius: 14px;" role="alert">
                <i class="fa-solid fa-exclamation-triangle fs-4 text-danger flex-shrink-0"></i>
                <div class="flex-grow-1">
                    <?php echo htmlspecialchars($error_msg); ?>
                </div>
                <button type="button" class="close ml-auto" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
        <?php endif; ?>

        <!-- Form Card -->
        <div class="card border-0 shadow-sm" style="border-radius: 18px; overflow: hidden;">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
                <span class="badge bg-primary-subtle text-primary fw-bold px-3 py-2 rounded-pill font-monospace fs-6">
                    <i class="fa-solid fa-receipt me-1"></i> Voucher #<?php echo htmlspecialchars($expense['voucher_no']); ?>
                </span>
                <span class="text-muted small">ID: #<?php echo $expense['id']; ?></span>
            </div>

            <div class="card-body p-4 p-md-5">
                <form method="POST" action="edit_expense.php?id=<?php echo $expense['id']; ?>" id="editExpenseForm">

                    <div class="row g-4">

                        <!-- Expense Invoice Number (Top Readonly) -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold text-dark">Expense Invoice Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-receipt"></i></span>
                                <input type="text" class="form-control font-monospace fw-bold bg-light" value="<?php echo htmlspecialchars($expense['voucher_no']); ?>" readonly>
                            </div>
                        </div>

                        <!-- Date -->
                        <div class="col-12 col-md-6">
                            <label for="expense_date" class="form-label fw-bold text-dark">
                                Expense Date <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-regular fa-calendar-days"></i></span>
                                <input type="date" class="form-control border-start-0 ps-0" id="expense_date" name="expense_date" value="<?php echo htmlspecialchars($expense['expense_date']); ?>" required>
                            </div>
                        </div>

                        <!-- Title -->
                        <div class="col-12 col-md-7">
                            <label for="title" class="form-label fw-bold text-dark">
                                Expense Title / Description <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-pen-nib"></i></span>
                                <input type="text" class="form-control border-start-0 ps-0" id="title" name="title" required value="<?php echo htmlspecialchars($expense['description']); ?>">
                            </div>
                        </div>

                        <!-- Amount -->
                        <div class="col-12 col-md-5">
                            <label for="amount" class="form-label fw-bold text-dark">
                                Amount (PKR) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 fw-bold text-success">Rs.</span>
                                <input type="number" step="0.01" min="0.01" class="form-control border-start-0 ps-0 fw-bold fs-5 text-dark" id="amount" name="amount" required value="<?php echo htmlspecialchars($expense['amount']); ?>">
                            </div>
                        </div>

                        <!-- OPEN CATEGORY FIELD -->
                        <div class="col-12 col-md-6">
                            <label for="category" class="form-label fw-bold text-dark d-flex align-items-center justify-content-between">
                                <span>Expense Category (Open Field) <span class="text-danger">*</span></span>
                                <span class="badge bg-success-subtle text-success small fw-normal">Type any category</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-tags"></i></span>
                                <input type="text" class="form-control border-start-0 ps-0" id="category" name="category" list="categoryOptions" autocomplete="off" required value="<?php echo htmlspecialchars($expense['category_name']); ?>">
                                <datalist id="categoryOptions">
                                    <?php foreach ($existing_categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat['name']); ?>"></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                        </div>

                        <!-- PAYMENT METHOD / SYSTEM ACCOUNT ONLY -->
                        <div class="col-12 col-md-6">
                            <label for="payment_account" class="form-label fw-bold text-dark d-flex align-items-center justify-content-between">
                                <span>Payment Method / Account <span class="text-danger">*</span></span>
                                <span class="badge bg-primary-subtle text-primary small fw-normal">System Accounts Only</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-exchange-alt"></i></span>
                                <select class="form-select border-start-0 ps-0" id="payment_account" name="payment_account" required>
                                    <?php if (!empty($cash_accounts)): ?>
                                        <optgroup label="💵 Cash Accounts (System)">
                                            <?php foreach ($cash_accounts as $ca): ?>
                                                <option value="cash_<?php echo $ca['id']; ?>" <?php echo ($current_selected_account === 'cash_' . $ca['id']) ? 'selected' : ''; ?>>
                                                    Cash: <?php echo htmlspecialchars($ca['account_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>

                                    <?php if (!empty($bank_accounts)): ?>
                                        <optgroup label="🏦 Bank Accounts (System)">
                                            <?php foreach ($bank_accounts as $ba): ?>
                                                <option value="bank_<?php echo $ba['id']; ?>" <?php echo ($current_selected_account === 'bank_' . $ba['id']) ? 'selected' : ''; ?>>
                                                    Bank: <?php echo htmlspecialchars($ba['bank_name']); ?> — <?php echo htmlspecialchars($ba['account_title']); ?><?php if (!empty($ba['account_number'])) echo " (" . htmlspecialchars($ba['account_number']) . ")"; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="col-12">
                            <label for="notes" class="form-label fw-bold text-dark">
                                Additional Remarks <span class="text-muted fw-normal small">(Optional)</span>
                            </label>
                            <input type="text" class="form-control" id="notes" name="notes" placeholder="Koi mazeed tafseelat likhein...">
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Actions -->
                    <div class="d-flex align-items-center justify-content-end gap-3">
                        <a href="<?php echo BASE_URL; ?>modules/expense/expenses.php" class="btn btn-light border px-4 py-2 fw-semibold">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary btn-lg px-5 py-2 fw-bold shadow" style="background-color: #0284c7; border-color: #0284c7;">
                            <i class="fa-solid fa-check me-2"></i> Update Expense
                        </button>
                    </div>

                </form>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
