<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
$page_title = 'Pay Salary';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . 'login.php'); exit;
}
requireRole(['admin']);

try { $bank_accounts = $pdo->query("SELECT id, account_title AS account_name, bank_name FROM bank_accounts WHERE status='Active' ORDER BY id")->fetchAll(); }
catch (Exception $e) { $bank_accounts = []; }

try { $employees = $pdo->query("SELECT id, emp_code, full_name, employee_type, commission_rate, status FROM employees WHERE status = 1 ORDER BY employee_type, full_name")->fetchAll(); }
catch (Exception $e) { $employees = []; }

// AJAX endpoint: live employee search (open field suggestions)
if (isset($_GET['action']) && $_GET['action'] === 'search_employee') {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    $rows = [];
    try {
        if ($q === '') {
            $stmt = $pdo->query("SELECT id, emp_code, full_name, employee_type, commission_rate FROM employees WHERE status = 1 ORDER BY employee_type, full_name LIMIT 15");
        } else {
            $term = "%{$q}%";
            $stmt = $pdo->prepare("SELECT id, emp_code, full_name, employee_type, commission_rate FROM employees WHERE status = 1 AND (full_name LIKE :q1 OR emp_code LIKE :q2) ORDER BY CASE WHEN full_name LIKE :exact THEN 1 ELSE 2 END, full_name ASC LIMIT 15");
            $stmt->execute([':q1' => $term, ':q2' => $term, ':exact' => "{$q}%"]);
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $rows = []; }
    echo json_encode($rows);
    exit;
}

// AJAX endpoint: get live monthly commission & salary stats for an employee
if (isset($_GET['action']) && $_GET['action'] === 'get_salesman_stats') {
    header('Content-Type: application/json');
    $emp_id = (int)($_GET['employee_id'] ?? 0);
    $m = trim($_GET['month'] ?? date('Y-m'));
    if (!preg_match('/^\d{4}-\d{2}$/', $m)) $m = date('Y-m');
    $from = $m . '-01';
    $to = date('Y-m-t', strtotime($from));

    $data = [
        'success' => false,
        'employee_id' => $emp_id,
        'full_name' => '',
        'emp_code' => '',
        'employee_type' => 'salesman',
        'commission_rate' => 0.0,
        'invoice_count' => 0,
        'sales_amount' => 0.0,
        'returned_amount' => 0.0,
        'commission_earned' => 0.0,
        'paid_amount' => 0.0,
        'balance_due' => 0.0,
        'month_label' => date('F Y', strtotime($from))
    ];

    if ($emp_id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT id, emp_code, full_name, employee_type, commission_rate FROM employees WHERE id = ?");
            $stmt->execute([$emp_id]);
            $emp = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($emp) {
                $data['success'] = true;
                $data['full_name'] = $emp['full_name'];
                $data['emp_code'] = $emp['emp_code'];
                $data['employee_type'] = $emp['employee_type'];
                $data['commission_rate'] = (float)$emp['commission_rate'];

                // Month sales for this salesman (net of Good/Resalable returns)
                $stmt_sales = $pdo->prepare("SELECT COUNT(*) as inv_count, COALESCE(SUM(si.grand_total), 0) as total_sales, COALESCE(SUM(ri.returned), 0) as returned_amount, COALESCE(SUM(GREATEST(0, si.grand_total - COALESCE(ri.returned, 0))), 0) as net_sales FROM sales_invoices si " . getReturnNetJoinSql('si') . " WHERE si.booker_id = ? AND si.invoice_date BETWEEN ? AND ?");
                $stmt_sales->execute([$emp_id, $from, $to]);
                $srow = $stmt_sales->fetch(PDO::FETCH_ASSOC);
                $data['invoice_count'] = (int)($srow['inv_count'] ?? 0);
                $data['sales_amount'] = (float)($srow['net_sales'] ?? 0.0);
                $data['returned_amount'] = (float)($srow['returned_amount'] ?? 0.0);

                if ($emp['employee_type'] === 'salesman') {
                    $data['commission_earned'] = round($data['sales_amount'] * $data['commission_rate'] / 100, 2);
                } else {
                    $data['commission_earned'] = 0.0;
                }

                // Month already paid
                $stmt_paid = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid FROM employee_salaries WHERE employee_id = ? AND salary_month = ?");
                $stmt_paid->execute([$emp_id, $m]);
                $prow = $stmt_paid->fetch(PDO::FETCH_ASSOC);
                $data['paid_amount'] = (float)($prow['total_paid'] ?? 0.0);

                $data['balance_due'] = round($data['commission_earned'] - $data['paid_amount'], 2);
            }
        } catch (Exception $e) {}
    }
    echo json_encode($data);
    exit;
}

try { $payments = $pdo->query("SELECT r.*, e.full_name AS emp_name, e.emp_code AS emp_code, e.employee_type AS emp_type, ba.account_title AS account_name FROM employee_salaries r JOIN employees e ON e.id = r.employee_id LEFT JOIN bank_accounts ba ON ba.id = r.bank_account_id ORDER BY r.payment_date DESC, r.id DESC")->fetchAll(); }
catch (Exception $e) { $payments = []; }

$employee_id = (int)($_GET['employee_id'] ?? 0);
$month = (string)($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');

$selected_emp = null;
$selected_stats = null;
if ($employee_id > 0) {
    try {
        $st = $pdo->prepare("SELECT id, emp_code, full_name, employee_type, commission_rate FROM employees WHERE id = ?");
        $st->execute([$employee_id]);
        $selected_emp = $st->fetch(PDO::FETCH_ASSOC);

        if ($selected_emp) {
            $from = $month . '-01';
            $to = date('Y-m-t', strtotime($from));
            $s_stmt = $pdo->prepare("SELECT COUNT(*) as inv_count, COALESCE(SUM(si.grand_total), 0) as total_sales, COALESCE(SUM(ri.returned), 0) as returned_amount, COALESCE(SUM(GREATEST(0, si.grand_total - COALESCE(ri.returned, 0))), 0) as net_sales FROM sales_invoices si " . getReturnNetJoinSql('si') . " WHERE si.booker_id = ? AND si.invoice_date BETWEEN ? AND ?");
            $s_stmt->execute([$employee_id, $from, $to]);
            $s_row = $s_stmt->fetch(PDO::FETCH_ASSOC);

            $p_stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid FROM employee_salaries WHERE employee_id = ? AND salary_month = ?");
            $p_stmt->execute([$employee_id, $month]);
            $p_row = $p_stmt->fetch(PDO::FETCH_ASSOC);

            $inv_cnt = (int)($s_row['inv_count'] ?? 0);
            $s_amt = (float)($s_row['net_sales'] ?? 0.0);
            $ret_amt = (float)($s_row['returned_amount'] ?? 0.0);
            $crate = (float)($selected_emp['commission_rate'] ?? 0.0);
            $c_earned = ($selected_emp['employee_type'] === 'salesman') ? round($s_amt * $crate / 100, 2) : 0.0;
            $p_amt = (float)($p_row['total_paid'] ?? 0.0);
            $b_due = round($c_earned - $p_amt, 2);

            $selected_stats = [
                'invoice_count' => $inv_cnt,
                'sales_amount' => $s_amt,
                'returned_amount' => $ret_amt,
                'commission_rate' => $crate,
                'commission_earned' => $c_earned,
                'paid_amount' => $p_amt,
                'balance_due' => $b_due,
                'month_label' => date('F Y', strtotime($from))
            ];
        }
    } catch (Exception $e) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = (int)($_POST['employee_id'] ?? 0);
    $salary_month = trim($_POST['salary_month'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}$/', $salary_month)) { redirect('pay_salary.php', 'Select a valid salary month.', 'error'); }
    $amount = (float)($_POST['amount'] ?? 0);
    $pdate = $_POST['payment_date'] ?: date('Y-m-d');
    $method = ($_POST['payment_method'] ?? 'cash') === 'bank' ? 'bank' : 'cash';
    $bank_id = null;
    if ($method === 'bank') {
        $bank_id = (int)($_POST['bank_account_id'] ?? 0) ?: null;
        if (!$bank_id) { redirect('pay_salary.php', 'Select a bank account for Bank payment.', 'error'); }
    }
    $notes = trim($_POST['notes'] ?? '') ?: 'Employee salary payment';

    if (!$employee_id) { redirect('pay_salary.php', 'Select an employee.', 'error'); }
    if ($amount <= 0) { redirect('pay_salary.php', 'Enter a valid payment amount.', 'error'); }

    $emp = getById('employees', $employee_id);
    if (!$emp) { redirect('pay_salary.php', 'Employee not found.', 'error'); }

    $slip_no = 'SLP-' . str_pad((countRows('employee_salaries') + 1), 4, '0', STR_PAD_LEFT);

    $pdo->beginTransaction();
    try {
        $pid = insert('employee_salaries', [
            'slip_no'       => $slip_no,
            'employee_id'   => $employee_id,
            'salary_month'  => $salary_month,
            'amount'        => $amount,
            'payment_method'=> $method,
            'bank_account_id' => $bank_id,
            'payment_date'  => $pdate,
            'notes'         => $notes,
            'created_by'    => $_SESSION['user_id'] ?? 1,
            'created_at'    => date('Y-m-d'),
        ]);

        $desc = 'Salary payment: ' . $emp['full_name'] . ' (' . $salary_month . ') - PKR ' . formatCurrency($amount);
        if ($method === 'bank') {
            recordBankOutflow($pdo, $pdate, $amount, $desc, 'employee_salary', $pid, $_SESSION['user_id'] ?? 1, $bank_id);
        } else {
            recordCashOutflow($pdo, $pdate, $amount, $desc, 'employee_salary', $pid, $_SESSION['user_id'] ?? 1);
        }

        $pdo->commit();
        redirect('pay_salary.php?employee_id=' . $employee_id . '&month=' . urlencode($salary_month), 'PKR ' . formatCurrency($amount) . ' salary paid to ' . $emp['full_name'] . ' (' . $slip_no . ')');
    } catch (Exception $e) {
        $pdo->rollBack();
        redirect('pay_salary.php', 'Error: ' . $e->getMessage(), 'error');
    }
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 d-print-none">
  <div>
    <h4 class="mb-1"><i class="fas fa-money-check-alt text-success mr-2"></i> Pay Salary</h4>
    <span class="text-muted small">Employees ko commission / salary ki payment dein. Salesman ki commission ledger se automatically ban jati hai.</span>
  </div>
  <div>
    <a href="ledger.php?month=<?= htmlspecialchars($month) ?>" class="btn btn-outline-secondary btn-sm shadow-sm"><i class="fas fa-book mr-1"></i> Employee Ledger</a>
    <button type="button" class="btn btn-outline-secondary btn-sm shadow-sm" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
  </div>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success alert-dismissible fade show py-2" role="alert"><i class="fas fa-check-circle mr-1"></i> <?= htmlspecialchars($_SESSION['success']) ?><button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>
<?php unset($_SESSION['success']); endif; ?>
<?php if (!empty($_SESSION['error'])): ?>
  <div class="alert alert-danger alert-dismissible fade show py-2" role="alert"><i class="fas fa-exclamation-triangle mr-1"></i> <?= htmlspecialchars($_SESSION['error']) ?><button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>
<?php unset($_SESSION['error']); endif; ?>

<form method="post" id="salaryForm" class="mb-4">
  <div class="card shadow">
    <div class="card-header py-3">
      <h6 class="mb-0"><i class="fas fa-user-plus text-primary mr-1"></i> New Salary Payment</h6>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-3 mb-3">
          <label class="form-label">Select Employee *</label>
          <input type="text" id="employeeSearchInput" class="form-control" placeholder="Employee ka naam ya code likh kar search karein..." autocomplete="off" value="<?= $selected_emp ? htmlspecialchars($selected_emp['full_name'] . ($selected_emp['emp_code'] ? ' (' . $selected_emp['emp_code'] . ')' : '')) : '' ?>" required>
          <input type="hidden" name="employee_id" id="employeeIdInput" value="<?= $employee_id ?>">
          <input type="hidden" name="employee_rate" id="employeeRateInput" value="<?= (float)($selected_emp['commission_rate'] ?? 0) ?>">
          <div class="position-relative">
            <div id="employeeResults" class="list-group position-absolute w-100 shadow d-none" style="z-index:1050;max-height:260px;overflow-y:auto;"></div>
          </div>
        </div>
        <div class="col-md-2 mb-3">
          <label class="form-label">Salary Month *</label>
          <input type="month" name="salary_month" id="salaryMonthInput" class="form-control" value="<?= htmlspecialchars($month) ?>" required>
        </div>
        <div class="col-md-2 mb-3">
          <label class="form-label">Amount (PKR) *</label>
          <input type="number" name="amount" id="salaryAmountInput" step="0.01" min="0.01" class="form-control font-weight-bold" required placeholder="0.00" value="<?= (!empty($selected_stats) && $selected_stats['balance_due'] > 0) ? $selected_stats['balance_due'] : '' ?>">
        </div>
        <div class="col-md-2 mb-3">
          <label class="form-label">Payment Date *</label>
          <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label">Method</label>
          <select name="payment_method" id="payMethod" class="form-control">
            <option value="cash">Cash</option>
            <option value="bank">Bank</option>
          </select>
        </div>
        <div class="col-md-4 mb-3 d-none" id="bankDiv">
          <label class="form-label">Bank Account</label>
          <select name="bank_account_id" class="form-control">
            <?php foreach ($bank_accounts as $ba): ?>
              <option value="<?= $ba['id'] ?>"><?= htmlspecialchars($ba['account_name']) ?> (<?= htmlspecialchars($ba['bank_name']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-8 mb-3">
          <label class="form-label">Notes</label>
          <input type="text" name="notes" id="salaryNotesInput" class="form-control" placeholder="e.g. September commission / salary" value="<?= $selected_emp ? htmlspecialchars(($selected_stats['month_label'] ?? '') . ' commission salary') : '' ?>">
        </div>

        <!-- Salesman Commission & Monthly Salary Live Box -->
        <div id="salesmanStatsBox" class="col-12 mb-3 <?= empty($selected_stats) ? 'd-none' : '' ?>">
          <div class="card border-primary" style="background: linear-gradient(to right, #f8faff, #f0f7ff); border-width: 1.5px;">
            <div class="card-body py-3">
              <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 pb-2 border-bottom">
                <div>
                  <span class="badge badge-primary px-2 py-1"><i class="fas fa-calculator mr-1"></i> Salesman Monthly Salary Calculation</span>
                  <span class="font-weight-bold ml-2 text-dark" id="statEmpName"><?= htmlspecialchars($selected_emp['full_name'] ?? '') ?></span>
                  <span class="text-muted small" id="statMonthLabel">(<?= $selected_stats['month_label'] ?? '' ?>)</span>
                </div>
                <div>
                  <a href="<?= !empty($selected_emp) ? 'ledger.php?emp_id=' . $selected_emp['id'] . '&month=' . urlencode($month) : '#' ?>" id="viewLedgerLink" target="_blank" class="btn btn-xs btn-outline-primary">
                    <i class="fas fa-file-invoice mr-1"></i> View Sales Invoices / Ledger
                  </a>
                </div>
              </div>
              
              <div class="row text-center mt-2 align-items-center">
                <div class="col-6 col-md-2 mb-2">
                  <small class="text-muted text-uppercase d-block" style="font-size: 0.72rem;">Invoices Booked</small>
                  <span class="font-weight-bold text-dark h5 mb-0" id="statInvoices"><?= $selected_stats['invoice_count'] ?? 0 ?></span>
                </div>
                <div class="col-6 col-md-3 mb-2">
                  <small class="text-muted text-uppercase d-block" style="font-size: 0.72rem;">Net Sales (This Month)</small>
                  <span class="font-weight-bold text-dark h5 mb-0" id="statSales">Rs. <?= number_format($selected_stats['sales_amount'] ?? 0, 2) ?></span>
                  <?php $pret = (float)($selected_stats['returned_amount'] ?? 0); ?>
                  <small class="d-block text-danger" id="statReturned"<?= $pret > 0 ? '' : ' style="display:none"' ?>>Returns: -Rs. <?= number_format($pret, 2) ?></small>
                </div>
                <div class="col-6 col-md-2 mb-2">
                  <small class="text-muted text-uppercase d-block" style="font-size: 0.72rem;">Commission Rate</small>
                  <span class="font-weight-bold text-info h5 mb-0" id="statRate"><?= number_format($selected_stats['commission_rate'] ?? 0, 2) ?>%</span>
                </div>
                <div class="col-6 col-md-3 mb-2">
                  <small class="text-muted text-uppercase d-block" style="font-size: 0.72rem;">Monthly Salary (Comm. Earned)</small>
                  <span class="font-weight-bold text-success h5 mb-0" id="statEarned">Rs. <?= number_format($selected_stats['commission_earned'] ?? 0, 2) ?></span>
                </div>
                <div class="col-12 col-md-2 mb-2 bg-white rounded p-2 shadow-sm border">
                  <small class="text-muted text-uppercase d-block font-weight-bold" style="font-size: 0.72rem;">Net Payable Balance</small>
                  <span class="font-weight-bold text-primary h5 mb-0 d-block" id="statBalance">Rs. <?= number_format($selected_stats['balance_due'] ?? 0, 2) ?></span>
                  <button type="button" class="btn btn-primary btn-xs mt-1 w-100" id="btnAutoFill">
                    <i class="fas fa-magic mr-1"></i> Auto-fill Amount
                  </button>
                </div>
              </div>
              
              <div class="mt-2 small text-muted">
                <i class="fas fa-info-circle text-primary mr-1"></i>
                <span>Already paid this month: <strong class="text-danger" id="statPaid">Rs. <?= number_format($selected_stats['paid_amount'] ?? 0, 2) ?></strong>. Jitni bhi sales salesman karta hai, unka commission per sale add hota rehta hai or mahine ki salary banti rehti hai.</span>
              </div>
            </div>
          </div>
        </div>

      </div>
      <div class="d-flex justify-content-end mt-2">
        <button type="submit" class="btn btn-success px-5"><i class="fas fa-hand-holding-usd mr-1"></i> Save Payment</button>
      </div>
    </div>
  </div>
</form>

<!-- Payments list -->
<div class="card shadow">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center d-print-none">
    <h6 class="mb-0"><i class="fas fa-history text-primary mr-1"></i> All Salary Payments (<?= count($payments) ?>)</h6>
    <input type="text" id="paySearch" class="form-control form-control-sm" style="max-width:260px;" placeholder="Search employee / slip / month">
  </div>
  <div class="table-responsive">
    <table class="table table-bordered mb-0" id="payTable">
      <thead class="thead-light">
        <tr><th>#</th><th>Slip No</th><th>Date</th><th>Employee</th><th>Month</th><th>Method</th><th>Notes</th><th class="text-right">Amount</th></tr>
      </thead>
      <tbody>
        <?php if (empty($payments)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Abhi koi salary payment nahi hui.</td></tr>
        <?php else: $i = 0; foreach ($payments as $p): $i++; ?>
          <tr>
            <td><?= $i ?></td>
            <td><code><?= htmlspecialchars($p['slip_no']) ?></code></td>
            <td><?= formatDate($p['payment_date']) ?></td>
            <td class="font-weight-bold"><?= htmlspecialchars($p['emp_name']) ?> <small class="text-muted">(<?= htmlspecialchars($p['emp_code'] ?? '') ?>)</small></td>
            <td><?= date('M Y', strtotime($p['salary_month'] . '-01')) ?></td>
            <td>
              <?php if (strtolower((string)$p['payment_method']) === 'bank'): ?>
                <span class="badge badge-info">Bank</span> <?= htmlspecialchars($p['account_name'] ?? '') ?>
              <?php else: ?>
                <span class="badge badge-warning">Cash</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($p['notes'] ?: '-') ?></td>
            <td class="text-right text-danger font-weight-bold"><?= formatCurrency($p['amount']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
$(document).ready(function(){
  $('#payMethod').change(function(){ $('#bankDiv').toggleClass('d-none', this.value !== 'bank'); });

  var empTimer = null;
  var isSelected = false;
  var currentSearchQuery = '';
  var currentBalanceDue = <?= !empty($selected_stats) ? (float)$selected_stats['balance_due'] : 0 ?>;

  function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, function(m){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
    });
  }

  function formatMoney(num) {
    return (parseFloat(num) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function fetchSalesmanStats(empId, month) {
    if (!empId) {
      $('#salesmanStatsBox').addClass('d-none');
      return;
    }
    month = month || $('#salaryMonthInput').val() || '';
    fetch('pay_salary.php?action=get_salesman_stats&employee_id=' + empId + '&month=' + encodeURIComponent(month), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r){ return r.json(); })
    .then(function(res){
      if (res && res.success) {
        currentBalanceDue = parseFloat(res.balance_due) || 0;
        $('#salesmanStatsBox').removeClass('d-none');
        $('#statEmpName').text(res.full_name + (res.emp_code ? ' (' + res.emp_code + ')' : ''));
        $('#statMonthLabel').text('(' + res.month_label + ')');
        $('#statInvoices').text(res.invoice_count);
        $('#statSales').text('Rs. ' + formatMoney(res.sales_amount));
        var retAmt = parseFloat(res.returned_amount) || 0;
        if (retAmt > 0) {
          $('#statReturned').text('Returns: -Rs. ' + formatMoney(retAmt)).show();
        } else {
          $('#statReturned').hide();
        }
        $('#statRate').text(parseFloat(res.commission_rate).toFixed(2) + '%');
        $('#statEarned').text('Rs. ' + formatMoney(res.commission_earned));
        $('#statPaid').text('Rs. ' + formatMoney(res.paid_amount));
        $('#statBalance').text('Rs. ' + formatMoney(res.balance_due));
        $('#viewLedgerLink').attr('href', 'ledger.php?emp_id=' + empId + '&month=' + encodeURIComponent(month));

        // Auto-populate amount if empty or 0
        var currentAmt = parseFloat($('#salaryAmountInput').val()) || 0;
        if (currentAmt === 0 && currentBalanceDue > 0) {
          $('#salaryAmountInput').val(currentBalanceDue.toFixed(2));
        }

        // Auto-populate notes if empty
        if (!$('#salaryNotesInput').val()) {
          $('#salaryNotesInput').val(res.month_label + ' commission salary');
        }
      } else {
        $('#salesmanStatsBox').addClass('d-none');
      }
    })
    .catch(function(){});
  }

  function renderEmployees(rows){
    if (isSelected) return;
    var c = $('#employeeResults');
    if (!rows || !rows.length) { 
      c.empty().removeClass('show').addClass('d-none'); 
      return; 
    }
    c.empty().removeClass('d-none');
    rows.forEach(function(r){
      var label = escapeHtml(r.full_name) + (r.emp_code ? ' (' + escapeHtml(r.emp_code) + ')' : '');
      var tag = r.employee_type === 'salesman' ? '<span class="badge badge-primary ml-1">Commission ' + (parseFloat(r.commission_rate)||0).toFixed(2) + '%</span>' : '<span class="badge badge-secondary ml-1">General</span>';
      c.append('<a href="#" class="list-group-item list-group-item-action px-3 py-2 emp-item" data-id="' + r.id + '" data-name="' + escapeHtml(r.full_name) + '" data-code="' + escapeHtml(r.emp_code || '') + '" data-rate="' + (parseFloat(r.commission_rate)||0).toFixed(2) + '" data-type="' + r.employee_type + '">' + label + tag + '</a>');
    });
    c.addClass('show');
  }

  function emptyResults(){ 
    $('#employeeResults').empty().removeClass('show').addClass('d-none'); 
  }

  $('#employeeSearchInput').on('input', function(){
    isSelected = false;
    var q = $(this).val().trim();
    if (!q) { 
      $('#employeeIdInput').val(''); 
      $('#employeeRateInput').val(0); 
      $('#salesmanStatsBox').addClass('d-none');
      emptyResults(); 
      return; 
    }
    clearTimeout(empTimer);
    currentSearchQuery = q;
    empTimer = setTimeout(function(){
      fetch('pay_salary.php?action=search_employee&q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function(r){ return r.json(); })
        .then(function(data){
          if (!isSelected && currentSearchQuery === q) {
            renderEmployees(data);
          }
        })
        .catch(function(){ emptyResults(); });
    }, 180);
  });

  $(document).on('click', '.emp-item', function(e){
    e.preventDefault();
    e.stopPropagation();
    isSelected = true;
    clearTimeout(empTimer);

    var empId = $(this).data('id');
    var empName = $(this).data('name') || '';
    var empCode = $(this).data('code') || '';
    var displayVal = empName + (empCode ? ' (' + empCode + ')' : '');

    $('#employeeSearchInput').val(displayVal);
    $('#employeeIdInput').val(empId);
    $('#employeeRateInput').val($(this).data('rate') || 0);

    emptyResults();
    fetchSalesmanStats(empId, $('#salaryMonthInput').val());
  });

  // Re-fetch when month changes
  $('#salaryMonthInput').on('change', function(){
    var empId = $('#employeeIdInput').val();
    if (empId) {
      fetchSalesmanStats(empId, this.value);
    }
  });

  // Auto-fill button click
  $('#btnAutoFill').on('click', function(){
    if (currentBalanceDue > 0) {
      $('#salaryAmountInput').val(currentBalanceDue.toFixed(2));
    }
  });

  // Close dropdown when clicking anywhere outside the search input and results container
  $(document).on('click', function(e){
    if (!$(e.target).closest('#employeeSearchInput, #employeeResults').length) {
      emptyResults();
    }
  });

  // Close dropdown on Escape key
  $('#employeeSearchInput').on('keydown', function(e){
    if (e.key === 'Escape') {
      emptyResults();
    }
  });

  $('#paySearch').on('keyup', function(){
    var q = $(this).val().toLowerCase();
    $('#payTable tbody tr').each(function(){
      $(this).toggle($(this).text().toLowerCase().indexOf(q) > -1);
    });
  });
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>