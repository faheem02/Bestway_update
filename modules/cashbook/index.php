<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
$page_title = 'Cash Book';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . 'login.php'); exit;
}
requireRole(['admin']);

$from = $_GET['from'] ?? '';
$to   = $_GET['to']   ?? '';
$today       = date('Y-m-d');
$monday      = date('Y-m-d', strtotime('monday this week'));
$sunday      = date('Y-m-d', strtotime('sunday this week'));
$month_first = date('Y-m-01');
$month_last  = date('Y-m-t');

$conds = []; $params = [];
if ($from !== '') { $conds[] = "transaction_date >= ?"; $params[] = $from; }
if ($to   !== '') { $conds[] = "transaction_date <= ?"; $params[] = $to; }
$where = $conds ? (' WHERE ' . implode(' AND ', $conds)) : '';

try {
    $stmt = $pdo->prepare("SELECT * FROM cash_book" . $where . " ORDER BY id DESC");
    $stmt->execute($params); $entries = $stmt->fetchAll();

    $sin = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM cash_book WHERE transaction_type = 'inflow'" . ($where ? ' AND ' . substr($where,7) : ''));
    $sin->execute($params); $total_in = (float)$sin->fetchColumn();

    $sout = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM cash_book WHERE transaction_type = 'outflow'" . ($where ? ' AND ' . substr($where,7) : ''));
    $sout->execute($params); $total_out = (float)$sout->fetchColumn();

    $cash_in_hand = (float)$pdo->query("SELECT COALESCE(closing_balance,0) FROM cash_book_daily ORDER BY date DESC LIMIT 1")->fetchColumn();
} catch (Exception $e) {
    $entries = []; $total_in = $total_out = $cash_in_hand = 0;
}

// Handle opening balance POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tdate  = $_POST['opening_date'] ?: date('Y-m-d');
    $amount = (float)($_POST['opening_amount'] ?? 0);
    if ($amount < 0) { redirect('index.php', 'Enter a valid opening balance.', 'error'); }
    $pdo->beginTransaction();
    try {
        $day = $pdo->prepare("SELECT id FROM cash_book_daily WHERE date = ?");
        $day->execute([$tdate]); $daily_id = $day->fetchColumn();
        if ($daily_id) {
            $pdo->prepare("UPDATE cash_book_daily SET opening_balance = ?, updated_at = ? WHERE id = ?")
                ->execute([$amount, date('Y-m-d'), $daily_id]);
        } else {
            $daily_id = insert('cash_book_daily', [
                'date'=>$tdate,'opening_balance'=>$amount,'total_inflow'=>0,
                'total_outflow'=>0,'closing_balance'=>$amount,'status'=>'open',
                'created_by'=>$_SESSION['user_id'],'created_at'=>date('Y-m-d'),
            ]);
        }
        recomputeCashDayTotals($pdo, (int)$daily_id);
        recomputeCashDailyFrom($pdo, $tdate);
        $pdo->commit();
        redirect('index.php', 'Opening balance saved.');
    } catch (Exception $e) {
        $pdo->rollBack();
        redirect('index.php', 'Error: ' . $e->getMessage(), 'error');
    }
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="row mb-3 align-items-center d-print-none">
  <div class="col-md-8">
    <form method="get" class="form-inline mb-1">
      <label class="mr-1 small text-muted">From</label>
      <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="form-control form-control-sm mr-2">
      <label class="mr-1 small text-muted">To</label>
      <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="form-control form-control-sm mr-2">
      <button type="submit" class="btn btn-sm btn-primary mr-1"><i class="fas fa-filter mr-1"></i>Filter</button>
      <a href="index.php" class="btn btn-sm btn-outline-secondary">All</a>
    </form>
    <div class="small mt-1">
      <a href="index.php?from=<?= $today ?>&to=<?= $today ?>" class="mr-2">Today</a>
      <a href="index.php?from=<?= $monday ?>&to=<?= $sunday ?>" class="mr-2">This Week</a>
      <a href="index.php?from=<?= $month_first ?>&to=<?= $month_last ?>" class="mr-2">This Month</a>
    </div>
  </div>
  <div class="col-md-4 text-md-right mt-2 mt-md-0">
    <button type="button" class="btn btn-outline-secondary shadow-sm mr-2" onclick="window.print()"><i class="fas fa-print mr-1"></i>Print</button>
    <button type="button" class="btn btn-primary shadow-sm" data-toggle="modal" data-target="#openingModal"><i class="fas fa-coins mr-1"></i>Opening Balance</button>
  </div>
</div>

<!-- Print header -->
<div class="d-none d-print-block mb-3 text-center">
  <h4 class="font-weight-bold mb-0" style="color:#0f172a;">Bestway Distribution</h4>
  <h5 class="font-weight-bold text-primary mt-2 mb-0">CASH BOOK</h5>
  <?php if ($from || $to): ?>
    <div class="mt-1 font-weight-bold"><?= $from ? 'From: '.formatDate($from) : '' ?> <?= $to ? '— To: '.formatDate($to) : '' ?></div>
  <?php endif; ?>
  <small>Printed on <?= formatDate(date('Y-m-d')) ?></small>
</div>

<div class="row mb-3">
  <div class="col-md-4 mb-2">
    <div class="card border-left-success shadow stat-card"><div class="card-body py-2">
      <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Inflow</div>
      <div class="h5 mb-0 font-weight-bold text-gray-800">PKR <?= formatCurrency($total_in) ?></div>
    </div></div>
  </div>
  <div class="col-md-4 mb-2">
    <div class="card border-left-danger shadow stat-card"><div class="card-body py-2">
      <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Outflow</div>
      <div class="h5 mb-0 font-weight-bold text-gray-800">PKR <?= formatCurrency($total_out) ?></div>
    </div></div>
  </div>
  <div class="col-md-4 mb-2">
    <div class="card border-left-primary shadow stat-card"><div class="card-body py-2">
      <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Cash in Hand</div>
      <div class="h5 mb-0 font-weight-bold text-gray-800">PKR <?= formatCurrency($cash_in_hand) ?></div>
    </div></div>
  </div>
</div>

<div class="card shadow">
  <div class="card-header"><h6 class="mb-0"><i class="fas fa-money-bill-alt mr-1"></i> Cash Book Entries</h6></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-bordered table-hover mb-0">
        <thead>
          <tr><th>Date</th><th>Type</th><th>Description</th><th>Reference</th><th>Amount</th></tr>
        </thead>
        <tbody>
          <?php foreach ($entries as $e): ?>
          <tr>
            <td><?= formatDate($e['transaction_date']) ?></td>
            <td>
              <?php
                $t = $e['transaction_type'];
                if ($t === 'inflow') echo '<span class="badge badge-success">Inflow</span>';
                elseif ($t === 'outflow') echo '<span class="badge badge-danger">Outflow</span>';
                else echo '<span class="badge badge-secondary">' . htmlspecialchars(ucfirst(str_replace('_',' ',$t))) . '</span>';
              ?>
            </td>
            <td><?= htmlspecialchars($e['description'] ?? '-') ?></td>
            <td><span class="text-muted"><?= htmlspecialchars($e['reference_type'] ?? '-') ?></span></td>
            <td class="font-weight-bold <?= $t==='inflow' ? 'text-success' : ($t==='outflow' ? 'text-danger' : '') ?>">
              <?= $t==='inflow' ? '+' : ($t==='outflow' ? '-' : '') ?> PKR <?= formatCurrency($e['amount']) ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($entries)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">No cash book entries yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Opening Balance Modal -->
<div class="modal fade" id="openingModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-coins mr-1"></i> Cash Opening Balance</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Date *</label>
          <input type="date" name="opening_date" class="form-control datepicker" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="form-group mb-0">
          <label class="form-label">Opening Balance (PKR) *</label>
          <input type="number" name="opening_amount" step="0.01" min="0" class="form-control" placeholder="0.00" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-check mr-1"></i> Save</button>
      </div>
    </form>
  </div></div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
