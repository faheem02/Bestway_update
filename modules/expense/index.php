<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
$page_title = 'Expenses';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . 'login.php'); exit;
}
requireRole(['admin']);

try {
    $categories   = $pdo->query("SELECT id, name FROM expense_categories WHERE status = 1 OR status = 'Active' ORDER BY name")->fetchAll();
    $bank_accounts = $pdo->query("SELECT id, account_title AS account_name, bank_name FROM bank_accounts WHERE status = 'Active' ORDER BY id")->fetchAll();
} catch (Exception $e) {
    $categories = []; $bank_accounts = [];
}

// Handle Delete Expense
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    if ($del_id > 0) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT * FROM expenses WHERE id = ?");
            $stmt->execute([$del_id]);
            $exp = $stmt->fetch();

            if ($exp) {
                // If bank payment, restore bank balance and delete bank_transactions
                if ((strtolower($exp['payment_method'] ?? '') === 'bank') && !empty($exp['bank_account_id'])) {
                    $pdo->prepare("UPDATE bank_accounts SET current_balance = current_balance + ? WHERE id = ?")
                        ->execute([$exp['amount'], $exp['bank_account_id']]);
                    $pdo->prepare("DELETE FROM bank_transactions WHERE reference_type = 'expense' AND reference_id = ?")
                        ->execute([$del_id]);
                } else {
                    // Cash payment: delete from cash_book and recompute
                    $cb = $pdo->prepare("SELECT daily_id FROM cash_book WHERE reference_type = 'expense' AND reference_id = ?");
                    $cb->execute([$del_id]);
                    $daily_id = $cb->fetchColumn();
                    $pdo->prepare("DELETE FROM cash_book WHERE reference_type = 'expense' AND reference_id = ?")
                        ->execute([$del_id]);
                    if ($daily_id) {
                        recomputeCashDayTotals($pdo, (int)$daily_id);
                    }
                }

                // Delete account_ledgers if exists
                if (!empty($exp['voucher_no'])) {
                    try {
                        $pdo->prepare("DELETE FROM account_ledgers WHERE reference_no = ? AND transaction_type = 'Expense'")
                            ->execute([$exp['voucher_no']]);
                    } catch (Exception $le) {}
                }

                // Delete from expenses
                $pdo->prepare("DELETE FROM expenses WHERE id = ?")->execute([$del_id]);

                $pdo->commit();
                redirect('index.php', 'Expense voucher ' . htmlspecialchars($exp['voucher_no'] ?? '') . ' deleted successfully.');
            } else {
                $pdo->rollBack();
                redirect('index.php', 'Expense record not found.', 'error');
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            redirect('index.php', 'Error deleting expense: ' . $e->getMessage(), 'error');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action       = $_POST['action'] ?? 'add';
    $amount       = (float)($_POST['amount'] ?? 0);
    $expense_date = $_POST['expense_date'] ?: date('Y-m-d');
    $category_id  = $_POST['category_id'] ?: null;
    $method       = strtolower($_POST['payment_method'] ?? 'cash');
    $bank_id      = $method == 'bank' ? ($_POST['bank_account_id'] ?: null) : null;
    $description  = trim($_POST['description'] ?? '');
    $vendor       = trim($_POST['vendor_name'] ?? '');
    $bill_no      = trim($_POST['bill_no'] ?? '');

    if ($amount <= 0) { redirect('index.php', 'Enter a valid expense amount.', 'error'); }

    if ($action === 'edit') {
        $edit_id = (int)($_POST['edit_id'] ?? 0);
        if ($edit_id <= 0) { redirect('index.php', 'Invalid expense record.', 'error'); }

        $pdo->beginTransaction();
        try {
            $old_stmt = $pdo->prepare("SELECT * FROM expenses WHERE id = ?");
            $old_stmt->execute([$edit_id]);
            $old_exp = $old_stmt->fetch();

            if (!$old_exp) {
                $pdo->rollBack();
                redirect('index.php', 'Expense record not found.', 'error');
            }

            // Revert previous financial deduction
            if (strtolower($old_exp['payment_method'] ?? '') === 'bank' && !empty($old_exp['bank_account_id'])) {
                $pdo->prepare("UPDATE bank_accounts SET current_balance = current_balance + ? WHERE id = ?")
                    ->execute([$old_exp['amount'], $old_exp['bank_account_id']]);
                $pdo->prepare("DELETE FROM bank_transactions WHERE reference_type = 'expense' AND reference_id = ?")
                    ->execute([$edit_id]);
            } else {
                $cb = $pdo->prepare("SELECT daily_id FROM cash_book WHERE reference_type = 'expense' AND reference_id = ?");
                $cb->execute([$edit_id]);
                $old_daily_id = $cb->fetchColumn();
                $pdo->prepare("DELETE FROM cash_book WHERE reference_type = 'expense' AND reference_id = ?")
                    ->execute([$edit_id]);
                if ($old_daily_id) {
                    recomputeCashDayTotals($pdo, (int)$old_daily_id);
                }
            }

            // Update expenses record
            $upd = $pdo->prepare("
                UPDATE expenses SET
                    category_id = :cat,
                    expense_date = :edate,
                    amount = :amt,
                    description = :desc,
                    vendor_name = :vendor,
                    bill_no = :bill,
                    payment_method = :pmethod,
                    bank_account_id = :bank_id
                WHERE id = :id
            ");
            $upd->execute([
                'cat'     => $category_id,
                'edate'   => $expense_date,
                'amt'     => $amount,
                'desc'    => $description,
                'vendor'  => $vendor,
                'bill'    => $bill_no,
                'pmethod' => ($method === 'bank' ? 'Bank' : 'Cash'),
                'bank_id' => $bank_id,
                'id'      => $edit_id
            ]);

            // Apply new financial deduction
            $desc = 'Expense: ' . ($description ?: 'General Expense');
            if ($method === 'bank' && $bank_id) {
                recordBankOutflow($pdo, $expense_date, $amount, $desc, 'expense', $edit_id, $_SESSION['user_id'], $bank_id);
            } else {
                recordCashOutflow($pdo, $expense_date, $amount, $desc, 'expense', $edit_id, $_SESSION['user_id']);
            }

            // Update account_ledgers if exists
            if (!empty($old_exp['voucher_no'])) {
                try {
                    $pdo->prepare("
                        UPDATE account_ledgers SET
                            transaction_date = :tx_date,
                            account_type = :acc_type,
                            account_id = :acc_id,
                            description = :desc,
                            credit_amount = :amt
                        WHERE reference_no = :vno AND transaction_type = 'Expense'
                    ")->execute([
                        'tx_date'  => $expense_date,
                        'acc_type' => ($method === 'bank' ? 'Bank' : 'Cash'),
                        'acc_id'   => ($method === 'bank' ? $bank_id : null),
                        'desc'     => $desc,
                        'amt'      => $amount,
                        'vno'      => $old_exp['voucher_no']
                    ]);
                } catch (Exception $le) {}
            }

            $pdo->commit();
            redirect('index.php', 'Expense voucher ' . htmlspecialchars($old_exp['voucher_no'] ?? '') . ' updated successfully.');
        } catch (Exception $e) {
            $pdo->rollBack();
            redirect('index.php', 'Error updating expense: ' . $e->getMessage(), 'error');
        }
    } else {
        // Add new expense
        $voucher_no = generateExpenseNo();

        $pdo->beginTransaction();
        try {
            $eid = insert('expenses', [
                'voucher_no'     => $voucher_no,
                'category_id'    => $category_id,
                'expense_date'   => $expense_date,
                'amount'         => $amount,
                'description'    => $description,
                'vendor_name'    => $vendor,
                'bill_no'        => $bill_no,
                'payment_method' => ($method === 'bank' ? 'Bank' : 'Cash'),
                'bank_account_id'=> $bank_id,
                'created_by'     => $_SESSION['user_id'],
                'created_at'     => date('Y-m-d'),
            ]);
            $desc = 'Expense: ' . ($description ?: 'General Expense');
            if ($method == 'bank' && $bank_id) {
                recordBankOutflow($pdo, $expense_date, $amount, $desc, 'expense', $eid, $_SESSION['user_id'], $bank_id);
            } else {
                recordCashOutflow($pdo, $expense_date, $amount, $desc, 'expense', $eid, $_SESSION['user_id']);
            }
            $pdo->commit();
            redirect('index.php', 'Expense of PKR ' . formatCurrency($amount) . ' recorded.');
        } catch (Exception $e) {
            $pdo->rollBack();
            redirect('index.php', 'Error: ' . $e->getMessage(), 'error');
        }
    }
}

$from = $_GET['from'] ?? '';
$to   = $_GET['to']   ?? '';
$cat  = $_GET['category_id'] ?? '';

$sql = "SELECT e.*, ec.name AS cat_name FROM expenses e LEFT JOIN expense_categories ec ON e.category_id = ec.id WHERE 1=1";
$params = [];
if ($from) { $sql .= " AND e.expense_date >= ?"; $params[] = $from; }
if ($to)   { $sql .= " AND e.expense_date <= ?"; $params[] = $to; }
if ($cat !== '') { $sql .= " AND e.category_id = ?"; $params[] = $cat; }
$sql .= " ORDER BY e.id DESC";
try {
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    $expenses = $stmt->fetchAll();
} catch (Exception $e) { $expenses = []; }

$total_expense = array_sum(array_column($expenses, 'amount'));
$next_voucher  = generateExpenseNo();

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="row mb-3 d-print-none">
  <div class="col-md-8">
    <div class="alert alert-danger alert-dismissible fade show py-2 mb-0" role="alert">
      <i class="fas fa-wallet mr-1"></i> <strong>Expenses</strong> &mdash; Record shop expenses (cash or bank) and track them here.
      <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
  </div>
  <div class="col-md-4 text-md-right">
    <button type="button" class="btn btn-outline-secondary shadow-sm mr-2" onclick="window.print()"><i class="fas fa-print mr-1"></i>Print</button>
    <button type="button" class="btn btn-danger shadow-sm" data-toggle="modal" data-target="#expenseModal"><i class="fas fa-plus-circle mr-1"></i>New Expense</button>
  </div>
</div>

<!-- New Expense Modal -->
<div class="modal fade" id="expenseModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="post">
      <input type="hidden" name="voucher_no" value="<?= htmlspecialchars($next_voucher) ?>">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-plus-circle text-danger mr-1"></i> New Expense</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Voucher No (Auto)</label>
            <input type="text" class="form-control bg-light font-weight-bold text-danger" value="<?= htmlspecialchars($next_voucher) ?>" readonly>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Amount (PKR) *</label>
            <input type="number" name="amount" step="0.01" min="0.01" class="form-control" required placeholder="0.00">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Date *</label>
            <input type="date" name="expense_date" class="form-control datepicker" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Category</label>
            <select name="category_id" class="form-control">
              <option value="">— Select —</option>
              <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Payment Method</label>
            <select name="payment_method" id="payMethod" class="form-control">
              <option value="cash">Cash</option>
              <option value="bank">Bank</option>
            </select>
          </div>
          <div class="col-md-6 mb-3" id="bankDiv" style="display:none;">
            <label class="form-label">Bank Account</label>
            <select name="bank_account_id" class="form-control">
              <?php foreach ($bank_accounts as $ba): ?>
              <option value="<?= $ba['id'] ?>"><?= htmlspecialchars($ba['account_name']) ?> — <?= htmlspecialchars($ba['bank_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Vendor / Payee</label>
            <input type="text" name="vendor_name" class="form-control" placeholder="Optional">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Bill No</label>
            <input type="text" name="bill_no" class="form-control" placeholder="Optional">
          </div>
          <div class="col-md-12 mb-3">
            <label class="form-label">Description</label>
            <input type="text" name="description" class="form-control" placeholder="e.g. Shop rent, Electricity bill">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-danger"><i class="fas fa-check mr-1"></i> Save Expense</button>
      </div>
    </form>
  </div></div>
</div>

<div class="card shadow">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
    <h6 class="mb-0"><i class="fas fa-file-invoice-dollar mr-1"></i> Expense List (<?= count($expenses) ?>)</h6>
    <input type="text" id="expSearch" class="form-control form-control-sm d-print-none mt-2 mt-md-0" placeholder="Search date / category / vendor…" style="max-width:260px;">
  </div>
  <div class="card-body">
    <form method="get" class="form-row mb-3 d-print-none">
      <div class="col-md-3 mb-2"><input type="date" name="from" class="form-control form-control-sm datepicker" value="<?= htmlspecialchars($from) ?>" placeholder="From"></div>
      <div class="col-md-3 mb-2"><input type="date" name="to" class="form-control form-control-sm datepicker" value="<?= htmlspecialchars($to) ?>" placeholder="To"></div>
      <div class="col-md-4 mb-2">
        <select name="category_id" class="form-control form-control-sm">
          <option value="">All Categories</option>
          <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $cat == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 mb-2 d-flex">
        <button class="btn btn-outline-primary btn-sm btn-block mr-1"><i class="fas fa-filter"></i> Go</button>
        <?php if ($from || $to || $cat !== ''): ?>
        <a href="index.php" class="btn btn-outline-secondary btn-sm btn-block"><i class="fas fa-times"></i></a>
        <?php endif; ?>
      </div>
    </form>

    <!-- Print header -->
    <div class="d-none d-print-block mb-3 text-center">
      <h4 class="font-weight-bold mb-0" style="color:#0f172a;">Bestway Distribution</h4>
      <h5 class="font-weight-bold text-danger mt-2 mb-0">EXPENSE LIST</h5>
      <small>Printed on <?= formatDate(date('Y-m-d')) ?></small>
    </div>

    <div class="alert alert-danger py-2 text-center d-print-none">
      <strong>Filtered Total: PKR <?= formatCurrency($total_expense) ?></strong>
    </div>

    <div class="table-responsive">
      <table class="table table-bordered table-hover mb-0" id="expTable">
        <thead>
          <tr>
            <th>Voucher No</th>
            <th>Date</th>
            <th>Category</th>
            <th>Description</th>
            <th>Vendor</th>
            <th>Method</th>
            <th class="text-right">Amount</th>
            <th class="text-center d-print-none" style="width: 120px;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($expenses as $e): ?>
          <tr>
            <td><code class="text-danger"><?= htmlspecialchars($e['voucher_no'] ?? '-') ?></code></td>
            <td><?= formatDate($e['expense_date']) ?></td>
            <td><?= htmlspecialchars($e['cat_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($e['description'] ?? '-') ?></td>
            <td><?= htmlspecialchars($e['vendor_name'] ?? '-') ?></td>
            <td>
              <?= strtolower($e['payment_method'] ?? '') == 'bank'
                ? '<span class="badge badge-info">Bank</span>'
                : '<span class="badge badge-secondary">Cash</span>' ?>
            </td>
            <td class="text-right text-danger font-weight-bold">PKR <?= formatCurrency($e['amount']) ?></td>
            <td class="text-center d-print-none text-nowrap">
              <button type="button" class="btn btn-sm btn-outline-primary btn-edit-exp"
                data-id="<?= $e['id'] ?>"
                data-voucher="<?= htmlspecialchars($e['voucher_no'] ?? '') ?>"
                data-amount="<?= htmlspecialchars($e['amount'] ?? '') ?>"
                data-date="<?= htmlspecialchars($e['expense_date'] ?? '') ?>"
                data-cat="<?= htmlspecialchars($e['category_id'] ?? '') ?>"
                data-method="<?= htmlspecialchars(strtolower($e['payment_method'] ?? 'cash')) ?>"
                data-bank="<?= htmlspecialchars($e['bank_account_id'] ?? '') ?>"
                data-vendor="<?= htmlspecialchars($e['vendor_name'] ?? '') ?>"
                data-bill="<?= htmlspecialchars($e['bill_no'] ?? '') ?>"
                data-desc="<?= htmlspecialchars($e['description'] ?? '') ?>"
                title="Edit Expense">
                <i class="fas fa-edit"></i>
              </button>
              <a href="print_expense.php?id=<?= $e['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print Voucher">
                <i class="fas fa-print"></i>
              </a>
              <a href="index.php?delete_id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete Expense" onclick="return confirm('Are you sure you want to delete expense voucher <?= htmlspecialchars($e['voucher_no'] ?? '') ?>?');">
                <i class="fas fa-trash-alt"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($expenses)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No expenses yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Edit Expense Modal -->
<div class="modal fade" id="editExpenseModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="post" id="editExpenseForm">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="edit_id" id="editExpId">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-edit text-primary mr-1"></i> Edit Expense</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Voucher No</label>
            <input type="text" id="editVoucherNo" class="form-control bg-light font-weight-bold text-danger" readonly>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Amount (PKR) *</label>
            <input type="number" name="amount" id="editAmount" step="0.01" min="0.01" class="form-control" required placeholder="0.00">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Date *</label>
            <input type="date" name="expense_date" id="editDate" class="form-control datepicker" required>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Category</label>
            <select name="category_id" id="editCategory" class="form-control">
              <option value="">— Select —</option>
              <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Payment Method</label>
            <select name="payment_method" id="editPayMethod" class="form-control">
              <option value="cash">Cash</option>
              <option value="bank">Bank</option>
            </select>
          </div>
          <div class="col-md-6 mb-3" id="editBankDiv" style="display:none;">
            <label class="form-label">Bank Account</label>
            <select name="bank_account_id" id="editBankAccount" class="form-control">
              <?php foreach ($bank_accounts as $ba): ?>
              <option value="<?= $ba['id'] ?>"><?= htmlspecialchars($ba['account_name']) ?> — <?= htmlspecialchars($ba['bank_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Vendor / Payee</label>
            <input type="text" name="vendor_name" id="editVendor" class="form-control" placeholder="Optional">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Bill No</label>
            <input type="text" name="bill_no" id="editBillNo" class="form-control" placeholder="Optional">
          </div>
          <div class="col-md-12 mb-3">
            <label class="form-label">Description</label>
            <input type="text" name="description" id="editDesc" class="form-control" placeholder="e.g. Shop rent, Electricity bill">
          </div>
        </div>
      </div>
      <div class="modal-footer d-flex justify-content-between">
        <a href="#" id="editPageDirectLink" class="btn btn-outline-secondary btn-sm"><i class="fas fa-external-link-alt mr-1"></i> Full Page Edit</a>
        <div>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Update Expense</button>
        </div>
      </div>
    </form>
  </div></div>
</div>

<script>
$(document).ready(function(){
  $('#payMethod').change(function(){ $('#bankDiv').toggle(this.value === 'bank'); });
  $('#editPayMethod').change(function(){ $('#editBankDiv').toggle(this.value === 'bank'); });

  $('.btn-edit-exp').on('click', function(){
    var id = $(this).data('id');
    $('#editExpId').val(id);
    $('#editVoucherNo').val($(this).data('voucher'));
    $('#editAmount').val($(this).data('amount'));
    $('#editDate').val($(this).data('date'));
    $('#editCategory').val($(this).data('cat'));
    var method = $(this).data('method');
    $('#editPayMethod').val(method);
    $('#editBankDiv').toggle(method === 'bank');
    $('#editBankAccount').val($(this).data('bank'));
    $('#editVendor').val($(this).data('vendor'));
    $('#editBillNo').val($(this).data('bill'));
    $('#editDesc').val($(this).data('desc'));
    $('#editPageDirectLink').attr('href', 'edit_expense.php?id=' + id);
    $('#editExpenseModal').modal('show');
  });

  $('#expSearch').on('keyup', function(){
    var q = $(this).val().toLowerCase();
    $('#expTable tbody tr').each(function(){ $(this).toggle($(this).text().toLowerCase().indexOf(q) > -1); });
  });
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
