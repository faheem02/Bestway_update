<?php
$page_title = "Add Operating Expense";
require_once __DIR__ . '/../../includes/header.php';

$message = "";
$msg_type = "";

// 1. Fetch system cash and bank accounts
$cash_accounts = [];
$bank_accounts = [];
if ($db_connected && $pdo) {
    try {
        $cash_accounts = $pdo->query("SELECT id, account_name, balance FROM cash_accounts ORDER BY id ASC")->fetchAll();
        $bank_accounts = $pdo->query("SELECT id, bank_name, account_title, account_number FROM bank_accounts WHERE status = 'Active' ORDER BY bank_name ASC")->fetchAll();
    } catch (Exception $e) {}
}

// 2. Generate Expense Invoice Number (starting from Exp-0001)
$expense_invoice_no = "Exp-0001";
if ($db_connected && $pdo) {
    try {
        $stmt_seq = $pdo->query("SELECT voucher_no FROM expenses WHERE voucher_no REGEXP '^Exp-[0-9]+$' ORDER BY id DESC LIMIT 1");
        $last_rec = $stmt_seq->fetchColumn();
        if ($last_rec && preg_match('/Exp-(\d+)/i', $last_rec, $matches)) {
            $next_num = (int)$matches[1] + 1;
        } else {
            $stmt_last = $pdo->query("SELECT id FROM expenses ORDER BY id DESC LIMIT 1");
            $last_id = (int)$stmt_last->fetchColumn();
            $next_num = $last_id + 1;
        }
        $expense_invoice_no = "Exp-" . str_pad($next_num, 4, '0', STR_PAD_LEFT);
    } catch (Exception $e) {}
}

// 3. Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $invoice_no   = trim($_POST['invoice_no'] ?? $expense_invoice_no);
    $date         = trim($_POST['date'] ?? date('Y-m-d'));
    $title        = trim($_POST['title'] ?? '');
    $amount       = (float)($_POST['amount'] ?? 0);
    $category     = trim($_POST['category'] ?? '');
    $payment_mode = trim($_POST['payment_mode'] ?? 'Cash');
    $description  = trim($_POST['description'] ?? '');

    if (empty($invoice_no)) {
        $invoice_no = $expense_invoice_no;
    }

    if (empty($title) || $amount <= 0 || empty($category) || empty($date)) {
        $message = "Zaroori fields fill karna lazmi hain!";
        $msg_type = "danger";
    } else {
        if ($db_connected && $pdo) {
            try {
                $pdo->beginTransaction();

                // 1. Resolve or Create Category (Open Field)
                $cat_chk = $pdo->prepare("SELECT id FROM expense_categories WHERE LOWER(TRIM(name)) = LOWER(TRIM(:cname)) LIMIT 1");
                $cat_chk->execute(['cname' => $category]);
                $category_id = $cat_chk->fetchColumn();

                if (!$category_id) {
                    $ins_cat = $pdo->prepare("INSERT INTO expense_categories (name, status) VALUES (:cname, 'Active')");
                    $ins_cat->execute(['cname' => $category]);
                    $category_id = $pdo->lastInsertId();
                }

                // 2. Resolve Payment Mode and Account
                $payment_method  = 'Cash';
                $cash_account_id = null;
                $bank_account_id = null;

                if (stripos($payment_mode, 'Bank') !== false || str_starts_with($payment_mode, 'Bank_')) {
                    $payment_method = 'Bank';
                    if (str_starts_with($payment_mode, 'Bank_')) {
                        $bank_account_id = (int)str_replace('Bank_', '', $payment_mode);
                    } elseif (!empty($bank_accounts)) {
                        $bank_account_id = $bank_accounts[0]['id'];
                    }
                } else {
                    $payment_method = 'Cash';
                    if (str_starts_with($payment_mode, 'Cash_')) {
                        $cash_account_id = (int)str_replace('Cash_', '', $payment_mode);
                    } elseif (!empty($cash_accounts)) {
                        $cash_account_id = $cash_accounts[0]['id'];
                    }
                }

                // 3. Combine title and description for notes
                $full_desc = $title;
                if (!empty($description)) {
                    $full_desc .= " (" . $description . ")";
                }

                // 4. Insert into expenses table
                $ins_exp = $pdo->prepare("
                    INSERT INTO expenses (
                        voucher_no, expense_date, category_id, amount, payment_method,
                        cash_account_id, bank_account_id, description, receipt_no, created_by
                    ) VALUES (
                        :voucher_no, :expense_date, :category_id, :amount, :payment_method,
                        :cash_account_id, :bank_account_id, :description, :receipt_no, :created_by
                    )
                ");

                $created_by = $_SESSION['user_id'] ?? 1;

                $ins_exp->execute([
                    'voucher_no'      => $invoice_no,
                    'expense_date'    => $date,
                    'category_id'     => $category_id,
                    'amount'          => $amount,
                    'payment_method'  => $payment_method,
                    'cash_account_id' => $cash_account_id,
                    'bank_account_id' => $bank_account_id,
                    'description'     => $full_desc,
                    'receipt_no'      => $invoice_no,
                    'created_by'      => $created_by
                ]);
                $expense_id = (int)$pdo->lastInsertId();

                // 5. Post into account_ledgers
                try {
                    $stmt_ledg = $pdo->prepare("
                        INSERT INTO account_ledgers (
                            transaction_date, account_type, account_id, reference_no,
                            transaction_type, description, debit_amount, credit_amount
                        ) VALUES (
                            :tx_date, :acc_type, :acc_id, :ref_no,
                            'Expense', :desc, 0.00, :amount
                        )
                    ");

                    $stmt_ledg->execute([
                        'tx_date'  => $date,
                        'acc_type' => $payment_method,
                        'acc_id'   => ($payment_method === 'Bank' ? ($bank_account_id ?: 1) : ($cash_account_id ?: 1)),
                        'ref_no'   => $invoice_no,
                        'desc'     => "Expense: {$category} - {$full_desc}",
                        'amount'   => $amount
                    ]);
                } catch (Exception $le) {}

                // Record in Cashbook or Bankbook (balance update is handled inside record functions)
                $exp_book_desc = "Expense: {$category} - {$full_desc} (#{$invoice_no})";
                if ($payment_method === 'Bank' && $bank_account_id) {
                    // recordBankOutflow internally updates bank_accounts.current_balance
                    recordBankOutflow($pdo, $date, $amount, $exp_book_desc, 'expense', $expense_id, $_SESSION['user_id'] ?? 1, $bank_account_id);
                } else {
                    // recordCashOutflow writes to cash_book; also deduct cash_accounts.balance
                    recordCashOutflow($pdo, $date, $amount, $exp_book_desc, 'expense', $expense_id, $_SESSION['user_id'] ?? 1);
                    if ($cash_account_id) {
                        $pdo->prepare("UPDATE cash_accounts SET balance = balance - ? WHERE id = ?")->execute([$amount, $cash_account_id]);
                    }
                }

                $pdo->commit();

                $message = "Expense kamyabi se save ho gaya! (Invoice #: {$invoice_no})";
                $msg_type = "success";

                // Generate next sequence for immediate display
                if (preg_match('/Exp-(\d+)/i', $invoice_no, $m)) {
                    $expense_invoice_no = "Exp-" . str_pad((int)$m[1] + 1, 4, '0', STR_PAD_LEFT);
                }

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $message = "Error: " . $e->getMessage();
                $msg_type = "danger";
            }
        } else {
            $message = "Database connection fail: System database offline.";
            $msg_type = "danger";
        }
    }
}

?>

<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
  .main-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
  }
  .pharma-badge {
    background: #e0f2fe;
    color: #0369a1;
    font-weight: 600;
    border-radius: 6px;
    padding: 4px 8px;
  }
  .form-control, .form-select {
    border-radius: 10px;
    border-color: #cbd5e1;
    padding: 0.65rem 0.9rem;
  }
  .form-control:focus, .form-select:focus {
    border-color: #0284c7;
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
  }
</style>

<div class="container py-2">
  <div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">

      <!-- Notification Alert -->
      <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $msg_type ?> border-0 shadow-sm rounded-3 d-flex align-items-center mb-3" role="alert">
          <i class="bi <?= $msg_type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> fs-5 me-2"></i>
          <div><?= htmlspecialchars($message) ?></div>
          <button type="button" class="close ml-auto" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
      <?php endif; ?>

      <div class="card main-card p-4 p-md-5">
        <div class="d-flex justify-content-between align-items-start mb-4 border-bottom pb-3">
          <div>
            <span class="pharma-badge small mb-2 d-inline-block"><i class="bi bi-capsule me-1"></i> Pharma Distribution</span>
            <h4 class="fw-bold mb-0">Add Expense</h4>
            <p class="text-muted small mb-0">Record business operating and logistics expenses</p>
          </div>
          <a href="<?php echo BASE_URL; ?>modules/expense/expenses.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="bi bi-card-list me-1"></i> View Expenses
          </a>
        </div>

        <form action="" method="POST" id="expenseForm" class="needs-validation" novalidate>
          <div class="row g-3">

            <!-- Expense Invoice Number (Top & Auto-filled starting from EXP-00001) -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Expense Invoice Number</label>
              <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-receipt"></i></span>
                <input 
                  type="text" 
                  name="invoice_no" 
                  class="form-control fw-bold font-monospace bg-light" 
                  value="<?php echo htmlspecialchars($expense_invoice_no); ?>" 
                  readonly
                >
              </div>
              <small class="text-muted" style="font-size: 0.75rem;">Auto-generated sequential number (Exp-00001)</small>
            </div>

            <!-- Expense Date -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Expense Date <span class="text-danger">*</span></label>
              <input type="date" name="date" id="expenseDate" class="form-control" required value="<?php echo htmlspecialchars($_POST['date'] ?? date('Y-m-d')); ?>">
              <div class="invalid-feedback">Please select a date.</div>
            </div>

            <!-- Title -->
            <div class="col-md-7">
              <label class="form-label fw-semibold">Expense Title <span class="text-danger">*</span></label>
              <input type="text" name="title" class="form-control" placeholder="e.g. Delivery Van Fuel, Storage Bill" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
              <div class="invalid-feedback">Please enter an expense title.</div>
            </div>

            <!-- Amount -->
            <div class="col-md-5">
              <label class="form-label fw-semibold">Amount (PKR) <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text bg-light text-muted">Rs.</span>
                <input type="number" step="0.01" min="1" name="amount" class="form-control fw-bold" placeholder="0.00" required value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>">
              </div>
              <div class="invalid-feedback">Please enter the amount.</div>
            </div>

            <!-- Category (Open Field) -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-tag"></i></span>
                <input 
                  type="text" 
                  name="category" 
                  class="form-control" 
                  placeholder="(e.g. Rent, Ice, Fuel)" 
                  required
                  value="<?php echo htmlspecialchars($_POST['category'] ?? ''); ?>"
                >
              </div>
              <div class="invalid-feedback">Category likhna zaroori hai.</div>
            </div>

            <!-- Payment Mode (Shows system accounts) -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Payment Mode</label>
              <select name="payment_mode" class="form-select">
                <?php if (!empty($cash_accounts)): ?>
                  <?php foreach ($cash_accounts as $ca): ?>
                    <option value="Cash_<?php echo $ca['id']; ?>" selected>
                      Cash in Hand (<?php echo htmlspecialchars($ca['account_name']); ?>)
                    </option>
                  <?php endforeach; ?>
                <?php else: ?>
                  <option value="Cash" selected>Cash in Hand (Petty Cash)</option>
                <?php endif; ?>

                <?php if (!empty($bank_accounts)): ?>
                  <?php foreach ($bank_accounts as $ba): ?>
                    <option value="Bank_<?php echo $ba['id']; ?>">
                      Bank - <?php echo htmlspecialchars($ba['bank_name']); ?> (<?php echo htmlspecialchars($ba['account_title']); ?>)
                    </option>
                  <?php endforeach; ?>
                <?php else: ?>
                  <option value="Bank Transfer">Company Bank Account</option>
                <?php endif; ?>
              </select>
            </div>

            <!-- Notes / Remarks -->
            <div class="col-12">
              <label class="form-label fw-semibold">Remarks / Description (Optional)</label>
              <textarea name="description" class="form-control" rows="2" placeholder="Koi mazeed tafseelat likhein..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>

            <!-- Buttons -->
            <div class="col-12 mt-4 pt-3 border-top d-flex justify-content-end gap-2">
              <button type="reset" class="btn btn-light px-4">Reset</button>
              <button type="submit" class="btn btn-primary px-4 fw-semibold" style="background-color: #0284c7; border-color: #0284c7;">
                <i class="bi bi-check2-circle me-1"></i> Save Expense
              </button>
            </div>

          </div>
        </form>

      </div>
    </div>
  </div>
</div>

<script>
  // Client validation
  const form = document.getElementById('expenseForm');
  form.addEventListener('submit', function (event) {
    if (!form.checkValidity()) {
      event.preventDefault();
      event.stopPropagation();
    }
    form.classList.add('was-validated');
  }, false);
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
