<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
$page_title = 'Employee Ledger';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . 'login.php'); exit;
}
requireRole(['admin']);

$month = (string)($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');
$from = $month . '-01';
$to   = date('Y-m-t', strtotime($from));

$emp_id = (int)($_GET['emp_id'] ?? 0);

$rows = [];
try {
    $net_join = getReturnNetJoinSql('si');

    // Net sales per salesman: each invoice clamped at 0 after deducting its returns
    $sales_stmt = $pdo->prepare("
        SELECT si.booker_id AS emp_id,
               COUNT(*) AS invoice_count,
               COALESCE(SUM(si.grand_total), 0) AS gross_sales,
               COALESCE(SUM(ri.returned), 0) AS returned_amount,
               COALESCE(SUM(GREATEST(0, si.grand_total - COALESCE(ri.returned, 0))), 0) AS net_sales
        FROM sales_invoices si
        $net_join
        WHERE si.booker_id IS NOT NULL AND si.invoice_date BETWEEN ? AND ?
        GROUP BY si.booker_id
    ");
    $sales_stmt->execute([$from, $to]);
    $sales_by_emp = [];
    foreach ($sales_stmt->fetchAll(PDO::FETCH_ASSOC) as $sr) {
        $sales_by_emp[(int)$sr['emp_id']] = $sr;
    }

    $stmt = $pdo->prepare("
        SELECT e.id, e.emp_code, e.full_name, e.employee_type, e.commission_rate, e.phone, e.status,
            0 AS invoice_count,
            0 AS sales_amount,
            0 AS returned_amount,
            (SELECT COALESCE(SUM(es.amount),0) FROM employee_salaries es WHERE es.employee_id = e.id AND es.salary_month = ?) AS paid_amount
        FROM employees e
        WHERE e.status = 1
        ORDER BY e.employee_type, e.full_name
    ");
    $stmt->execute([$month]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $agg = $sales_by_emp[(int)$r['id']] ?? null;
        $r['invoice_count']  = (int)($agg['invoice_count'] ?? 0);
        $r['sales_amount']   = (float)($agg['net_sales'] ?? 0.0);
        $r['returned_amount']= (float)($agg['returned_amount'] ?? 0.0);
    }
    unset($r);
} catch (Exception $e) {
    $rows = [];
}

foreach ($rows as &$r) {
    $r['sales_amount'] = (float)$r['sales_amount'];
    $r['paid_amount']  = (float)$r['paid_amount'];
    $r['commission_earned'] = ($r['employee_type'] === 'salesman')
        ? $r['sales_amount'] * (float)$r['commission_rate'] / 100
        : 0.0;
    $r['balance'] = $r['commission_earned'] - $r['paid_amount'];
}
unset($r);

$total_sales   = array_sum(array_column($rows, 'sales_amount'));
$total_returned= array_sum(array_column($rows, 'returned_amount'));
$total_earned  = array_sum(array_column($rows, 'commission_earned'));
$total_paid    = array_sum(array_column($rows, 'paid_amount'));
$total_balance = array_sum(array_column($rows, 'balance'));

$detail = null;
$invoices = []; $payments = [];
if ($emp_id > 0) {
    foreach ($rows as $r) {
        if ((int)$r['id'] === $emp_id) { $detail = $r; break; }
    }
    if ($detail) {
        try {
            $si = $pdo->prepare("SELECT si.invoice_no, si.invoice_date, si.customer_name, si.route_name, si.grand_total, si.paid_amount, si.balance_due, COALESCE(ri.returned, 0) AS returned_amount
                FROM sales_invoices si
                " . getReturnNetJoinSql('si') . "
                WHERE si.booker_id = ? AND si.invoice_date BETWEEN ? AND ? ORDER BY si.invoice_date, si.id");
            $si->execute([$emp_id, $from, $to]);
            $invoices = $si->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { $invoices = []; }
        try {
            $sp = $pdo->prepare("SELECT slip_no, payment_date, payment_method, bank_account_id, amount, notes FROM employee_salaries WHERE employee_id = ? AND salary_month = ? ORDER BY payment_date, id");
            $sp->execute([$emp_id, $month]);
            $payments = $sp->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { $payments = []; }
    }
}

$printed_by = '';
if (!empty($_SESSION['user_id'])) {
    try {
        $pu = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $pu->execute([(int)$_SESSION['user_id']]);
        $printed_by = (string)$pu->fetchColumn();
    } catch (Exception $e) {}
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 d-print-none">
  <div>
    <h4 class="mb-1"><i class="fas fa-book text-primary mr-2"></i> Employee Ledger</h4>
    <span class="text-muted small">Salesman commission based salary — month-wise summary aur payments.</span>
  </div>
  <button type="button" class="btn btn-outline-secondary btn-sm shadow-sm" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
</div>

<!-- Print header -->
<div class="d-none d-print-block mb-3 text-center">
  <h4 class="font-weight-bold mb-0" style="color:#0f172a;">Bestway Distribution</h4>
  <small class="text-muted">Wholesale Medicine &amp; Pharma Distribution</small>
  <h5 class="font-weight-bold text-primary mt-2 mb-1">EMPLOYEE COMMISSION &amp; SALARY LEDGER</h5>
  <div class="small text-muted">Month: <strong><?= date('M Y', strtotime($from)) ?></strong> &middot; Printed on <?= date('d-m-Y H:i') ?><?= $printed_by ? ' by ' . htmlspecialchars($printed_by) : '' ?></div>
</div>

<!-- Month filter -->
<div class="card shadow mb-3 d-print-none">
  <div class="card-body py-2">
    <form method="get" class="form-inline">
      <label class="mr-2 font-weight-bold small text-muted text-uppercase">Month</label>
      <input type="month" name="month" class="form-control form-control-sm mr-2" value="<?= htmlspecialchars($month) ?>">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter mr-1"></i> Show</button>
    </form>
  </div>
</div>

<?php if ($detail): ?>
  <!-- Detail view -->
  <div class="card shadow mb-3 d-print-none">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
      <h6 class="mb-0"><i class="fas fa-user-tie text-primary mr-1"></i> <?= htmlspecialchars($detail['full_name']) ?>
        (<?= $detail['employee_type'] === 'salesman' ? 'Salesman' : 'General' ?> — <?= htmlspecialchars($detail['emp_code'] ?? '') ?>)
      </h6>
      <div>
        <a href="pay_salary.php?employee_id=<?= (int)$detail['id'] ?>&month=<?= urlencode($month) ?>" class="btn btn-sm btn-success mr-2"><i class="fas fa-hand-holding-usd mr-1"></i> Pay Month Salary (<?= formatCurrency($detail['balance']) ?>)</a>
        <a href="ledger.php?month=<?= htmlspecialchars($month) ?>" class="btn btn-sm btn-light border"><i class="fas fa-times"></i> Close Detail</a>
      </div>
    </div>
  </div>

  <div class="row mb-3 d-print-none">
    <div class="col-md-3 col-6 mb-2"><div class="small-box bg-info"><div class="inner"><h3><?= number_format($detail['commission_rate'], 2) ?>%</h3><p>Commission Rate</p></div><div class="icon"><i class="fas fa-percent"></i></div></div></div>
    <div class="col-md-3 col-6 mb-2"><div class="small-box bg-emerald"><div class="inner"><h3><?= formatCurrency($detail['sales_amount']) ?></h3><p>Net Sales (<?= $month ?>)</p></div><div class="icon"><i class="fas fa-file-invoice"></i></div></div></div>
    <div class="col-md-3 col-6 mb-2"><div class="small-box bg-primary"><div class="inner"><h3><?= formatCurrency($detail['commission_earned']) ?></h3><p>Commission Earned</p></div><div class="icon"><i class="fas fa-hand-holding-usd"></i></div></div></div>
    <div class="col-md-3 col-6 mb-2"><div class="small-box bg-orange"><div class="inner"><h3><?= formatCurrency($detail['balance']) ?></h3><p>Balance (Earned - Paid)</p></div><div class="icon"><i class="fas fa-balance-scale"></i></div></div></div>
  </div>

  <div class="card shadow mb-3">
    <div class="card-header py-3 d-print-none">
      <h6 class="mb-0"><i class="fas fa-file-invoice mr-1"></i> Sales Invoices — Commission Breakdown (<?= date('M Y', strtotime($from)) ?>)</h6>
    </div>
    <div class="table-responsive">
      <table class="table table-bordered table-hover mb-0">
        <thead class="thead-light">
          <tr><th>#</th><th>Invoice No</th><th>Date</th><th>Customer</th><th>Route</th><th class="text-right">Sale Amount</th><th class="text-right">Returned</th><th class="text-right">Net Sale</th><th class="text-right">Commission (<?= number_format($detail['commission_rate'], 2) ?>%)</th></tr>
        </thead>
        <tbody>
          <?php if (empty($invoices)): ?>
            <tr><td colspan="9" class="text-center text-muted py-3"><?= $month ?> month mein koi sales invoice nahi milli.</td></tr>
          <?php else: $i = 0; $inv_total = 0; $ret_total = 0; $comm_total = 0; foreach ($invoices as $iv): $i++;
            $iv_ret     = floatval($iv['returned_amount'] ?? 0);
            $iv_net     = netSaleAmount($iv['grand_total'], $iv_ret);
            $comm       = $iv_net * $detail['commission_rate'] / 100;
            $inv_total += $iv_net; $ret_total += $iv_ret; $comm_total += $comm; ?>
            <tr>
              <td><?= $i ?></td>
              <td class="font-weight-bold text-primary"><?= htmlspecialchars($iv['invoice_no']) ?></td>
              <td><?= formatDate($iv['invoice_date']) ?></td>
              <td><?= htmlspecialchars($iv['customer_name']) ?></td>
              <td><?= htmlspecialchars($iv['route_name'] ?: '-') ?></td>
              <td class="text-right font-weight-bold"><?= formatCurrency($iv['grand_total']) ?></td>
              <td class="text-right text-danger"><?= $iv_ret > 0 ? '-' . formatCurrency($iv_ret) : '—' ?></td>
              <td class="text-right font-weight-bold"><?= formatCurrency($iv_net) ?></td>
              <td class="text-right text-success font-weight-bold"><?= formatCurrency($comm) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
        <?php if (!empty($invoices)): ?>
        <tfoot class="font-weight-bold bg-light">
          <tr>
            <td colspan="5" class="text-right">Total (<?= count($invoices) ?> invoices):</td>
            <td class="text-right">—</td>
            <td class="text-right text-danger"><?= $ret_total > 0 ? '-' . formatCurrency($ret_total) : '—' ?></td>
            <td class="text-right"><?= formatCurrency($inv_total) ?></td>
            <td class="text-right text-success"><?= formatCurrency($comm_total) ?></td>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>

  <div class="card shadow mb-3">
    <div class="card-header py-3">
      <h6 class="mb-0"><i class="fas fa-money-check-alt mr-1"></i> Salary / Commission Payments (<?= date('M Y', strtotime($from)) ?>)</h6>
    </div>
    <div class="table-responsive">
      <table class="table table-bordered mb-0">
        <thead class="thead-light">
          <tr><th>#</th><th>Slip No</th><th>Date</th><th>Method</th><th>Notes</th><th class="text-right">Amount</th></tr>
        </thead>
        <tbody>
          <?php if (empty($payments)): ?>
            <tr><td colspan="6" class="text-center text-muted py-3">Is month koi payment nahi hui.</td></tr>
          <?php else: $i = 0; $pay_total = 0; foreach ($payments as $pay): $i++; $pay_total += $pay['amount']; ?>
            <tr>
              <td><?= $i ?></td>
              <td><code><?= htmlspecialchars($pay['slip_no']) ?></code></td>
              <td><?= formatDate($pay['payment_date']) ?></td>
              <td><?php if (strtolower((string)$pay['payment_method']) === 'bank'): ?><span class="badge badge-info">Bank</span><?php else: ?><span class="badge badge-warning">Cash</span><?php endif; ?></td>
              <td><?= htmlspecialchars($pay['notes'] ?: '-') ?></td>
              <td class="text-right text-danger font-weight-bold"><?= formatCurrency($pay['amount']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
        <?php if (!empty($payments)): ?>
        <tfoot class="font-weight-bold bg-light">
          <tr><td colspan="5" class="text-right">Total Paid:</td><td class="text-right text-danger"><?= formatCurrency($pay_total) ?></td></tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>

<?php else: ?>

  <!-- Summary cards -->
  <div class="row mb-3 row-cols-2 row-cols-md-6 d-print-none">
    <div class="col mb-2"><div class="small-box bg-slate"><div class="inner"><h3><?= count($rows) ?></h3><p>Active Employees</p></div><div class="icon"><i class="fas fa-users"></i></div></div></div>
    <div class="col mb-2"><div class="small-box bg-emerald"><div class="inner"><h3><?= formatCurrency($total_sales) ?></h3><p>Net Sales (Returns Adjusted)</p></div><div class="icon"><i class="fas fa-file-invoice"></i></div></div></div>
    <div class="col mb-2"><div class="small-box bg-danger"><div class="inner"><h3><?= formatCurrency($total_returned) ?></h3><p>Good / Resalable Returned</p></div><div class="icon"><i class="fas fa-rotate-left"></i></div></div></div>
    <div class="col mb-2"><div class="small-box bg-primary"><div class="inner"><h3><?= formatCurrency($total_earned) ?></h3><p>Commission Earned</p></div><div class="icon"><i class="fas fa-hand-holding-usd"></i></div></div></div>
    <div class="col mb-2"><div class="small-box bg-orange"><div class="inner"><h3><?= formatCurrency($total_paid) ?></h3><p>Paid</p></div><div class="icon"><i class="fas fa-money-check-alt"></i></div></div></div>
    <div class="col mb-2"><div class="small-box <?= $total_balance > 0 ? 'bg-danger' : 'bg-success' ?>"><div class="inner"><h3><?= formatCurrency($total_balance) ?></h3><p>Outstanding</p></div><div class="icon"><i class="fas fa-balance-scale"></i></div></div></div>
  </div>

  <div class="card shadow">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center d-print-none">
      <h6 class="mb-0"><i class="fas fa-list mr-1"></i> Employees — <?= date('M Y', strtotime($from)) ?></h6>
      <a href="pay_salary.php?month=<?= htmlspecialchars($month) ?>" class="btn btn-sm btn-success"><i class="fas fa-money-check-alt mr-1"></i> Pay Salary</a>
    </div>
    <div class="table-responsive">
      <table class="table table-bordered table-hover mb-0">
        <thead class="thead-light">
          <tr>
            <th>#</th><th>Emp ID</th><th>Name</th><th>Type</th><th class="text-center">Commission %</th>
            <th class="text-center">Invoices</th><th class="text-right">Net Sales</th><th class="text-right text-danger">Returned</th><th class="text-right">Commission Earned</th>
            <th class="text-right">Paid</th><th class="text-right">Balance</th>
            <th class="text-center d-print-none">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="12" class="text-center text-muted py-4">Koi employee nahi. <a href="create.php">Add employee</a>.</td></tr>
          <?php else: $i = 0; foreach ($rows as $r): $i++; ?>
            <tr>
              <td><?= $i ?></td>
              <td><code><?= htmlspecialchars($r['emp_code'] ?? '-') ?></code></td>
              <td class="font-weight-bold">
                <a href="ledger.php?month=<?= urlencode($month) ?>&emp_id=<?= (int)$r['id'] ?>" class="text-decoration-none"><?= htmlspecialchars($r['full_name']) ?></a>
              </td>
              <td><?= $r['employee_type'] === 'salesman' ? '<span class="badge badge-success">Salesman</span>' : '<span class="badge badge-secondary">General</span>' ?></td>
              <td class="text-center font-weight-bold"><?= number_format((float)$r['commission_rate'], 2) ?>%</td>
              <td class="text-center"><?= (int)$r['invoice_count'] ?></td>
              <td class="text-right"><?= formatCurrency($r['sales_amount']) ?></td>
              <td class="text-right text-danger"><?= (float)$r['returned_amount'] > 0 ? '-' . formatCurrency($r['returned_amount']) : '—' ?></td>
              <td class="text-right font-weight-bold text-success"><?= formatCurrency($r['commission_earned']) ?></td>
              <td class="text-right text-danger"><?= formatCurrency($r['paid_amount']) ?></td>
              <td class="text-right font-weight-bold <?= $r['balance'] > 0 ? 'text-warning' : 'text-muted' ?>"><?= formatCurrency($r['balance']) ?></td>
              <td class="text-center d-print-none">
                <a href="pay_salary.php?employee_id=<?= (int)$r['id'] ?>&month=<?= urlencode($month) ?>" class="btn btn-xs btn-outline-success" title="Pay Salary"><i class="fas fa-hand-holding-usd"></i></a>
                <a href="ledger.php?month=<?= urlencode($month) ?>&emp_id=<?= (int)$r['id'] ?>" class="btn btn-xs btn-outline-primary" title="View Detail"><i class="fas fa-eye"></i></a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
        <?php if (!empty($rows)): ?>
        <tfoot class="font-weight-bold bg-light">
          <tr>
            <td colspan="6" class="text-right">Grand Total (<?= count($rows) ?> employees):</td>
            <td class="text-right"><?= formatCurrency($total_sales) ?></td>
            <td class="text-right text-danger"><?= $total_returned > 0 ? '-' . formatCurrency($total_returned) : '—' ?></td>
            <td class="text-right text-success"><?= formatCurrency($total_earned) ?></td>
            <td class="text-right text-danger"><?= formatCurrency($total_paid) ?></td>
            <td class="text-right <?= $total_balance > 0 ? 'text-warning' : 'text-muted' ?>"><?= formatCurrency($total_balance) ?></td>
            <td class="d-print-none"></td>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>

  <div class="alert alert-info small mt-3 d-print-none">
    <i class="fas fa-info-circle mr-1"></i>
    Salesman commission <strong>rate &times; net sales</strong> par calculate hoti hai, jahan se <strong>Good / Resalable returns</strong> minus hote hain — yaani returned maal par commission nahi milti. Damaged/defective returns commission se deduct nahi hote.
    General employees ki commission nahi banti; unhe Pay Salary page se manual payment di ja sakti hai.
  </div>

<?php endif; ?>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>