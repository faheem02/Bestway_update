<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
$page_title = 'Bank Book';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . 'login.php'); exit;
}
requireRole(['admin']);

$from       = $_GET['from'] ?? '';
$to         = $_GET['to']   ?? '';
$account_id = (int)($_GET['account_id'] ?? 0);
$today       = date('Y-m-d');
$monday      = date('Y-m-d', strtotime('monday this week'));
$sunday      = date('Y-m-d', strtotime('sunday this week'));
$month_first = date('Y-m-01');
$month_last  = date('Y-m-t');

$conds = []; $params = [];
if ($from !== '') { $conds[] = "bt.transaction_date >= ?"; $params[] = $from; }
if ($to   !== '') { $conds[] = "bt.transaction_date <= ?"; $params[] = $to; }
if ($account_id > 0) { $conds[] = "bt.bank_account_id = ?"; $params[] = $account_id; }
$where = $conds ? (' WHERE ' . implode(' AND ', $conds)) : '';

// Unqualified conditions for totals queries (no alias needed)
$conds2 = []; $params2 = [];
if ($from !== '') { $conds2[] = "transaction_date >= ?"; $params2[] = $from; }
if ($to   !== '') { $conds2[] = "transaction_date <= ?"; $params2[] = $to; }
if ($account_id > 0) { $conds2[] = "bank_account_id = ?"; $params2[] = $account_id; }
$where2 = $conds2 ? (' AND ' . implode(' AND ', $conds2)) : '';

try {
    $accounts = $pdo->query("SELECT *, account_title AS account_name, account_number AS account_no FROM bank_accounts ORDER BY id")->fetchAll();

    $stmt = $pdo->prepare("SELECT bt.*, ba.account_title AS account_name, ba.bank_name FROM bank_transactions bt LEFT JOIN bank_accounts ba ON bt.bank_account_id = ba.id" . $where . " ORDER BY bt.id DESC");
    $stmt->execute($params); $transactions = $stmt->fetchAll();

    $si = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM bank_transactions WHERE transaction_type IN ('deposit','transfer_in')" . $where2);
    $si->execute($params2); $total_deposits = (float)$si->fetchColumn();

    $so = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM bank_transactions WHERE transaction_type IN ('withdrawal','transfer_out')" . $where2);
    $so->execute($params2); $total_withdrawals = (float)$so->fetchColumn();

    if ($account_id > 0) {
        $bacc = $pdo->prepare("SELECT COALESCE(current_balance,0) FROM bank_accounts WHERE id = ?");
        $bacc->execute([$account_id]);
        $total_bank = (float)$bacc->fetchColumn();

        $bacc_op = $pdo->prepare("SELECT COALESCE(opening_balance,0) FROM bank_accounts WHERE id = ?");
        $bacc_op->execute([$account_id]);
        $total_opening = (float)$bacc_op->fetchColumn();
    } else {
        $total_bank    = (float)$pdo->query("SELECT COALESCE(SUM(current_balance),0) FROM bank_accounts")->fetchColumn();
        $total_opening = (float)$pdo->query("SELECT COALESCE(SUM(opening_balance),0) FROM bank_accounts")->fetchColumn();
    }
    $latest_opening_date = null;
    try {
        $latest_opening_date = null; // opening_date column not in bank_accounts table
    } catch (Exception $e) {}
} catch (Exception $e) {
    $accounts = []; $transactions = []; $total_deposits = $total_withdrawals = $total_bank = $total_opening = 0; $latest_opening_date = null;
}

// Handle opening balance POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['save_bank_opening'])) {
    $pdo->beginTransaction();
    try {
        $input        = $_POST['opening'] ?? [];
        $opening_date = $_POST['opening_date'] ?: null;
        foreach ($accounts as $a) {
            $val = array_key_exists($a['id'], $input) ? max(0, (float)$input[$a['id']]) : (float)$a['opening_balance'];
            $q = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN transaction_type IN ('deposit','transfer_in') THEN amount ELSE 0 END),0) - COALESCE(SUM(CASE WHEN transaction_type IN ('withdrawal','transfer_out') THEN amount ELSE 0 END),0) FROM bank_transactions WHERE bank_account_id = ?");
            $q->execute([$a['id']]); $net = (float)$q->fetchColumn();
            $pdo->prepare("UPDATE bank_accounts SET opening_balance=?, current_balance=?, opening_date=? WHERE id=?")
                ->execute([$val, $val + $net, $opening_date, $a['id']]);
        }
        $pdo->commit();
        redirect('index.php', 'Bank opening balance updated.');
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
      <label class="mr-1 small text-muted">Account</label>
      <select name="account_id" class="form-control form-control-sm mr-2">
        <option value="0">-- All Accounts --</option>
        <?php foreach ($accounts as $a): ?>
          <option value="<?= $a['id'] ?>" <?= ($account_id == $a['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($a['bank_name'] . ' - ' . $a['account_title'] . ' (' . ($a['account_no'] ?? '') . ')') ?>
          </option>
        <?php endforeach; ?>
      </select>
      <label class="mr-1 small text-muted">From</label>
      <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="form-control form-control-sm mr-2">
      <label class="mr-1 small text-muted">To</label>
      <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="form-control form-control-sm mr-2">
      <button type="submit" class="btn btn-sm btn-primary mr-1"><i class="fas fa-filter mr-1"></i>Filter</button>
      <a href="index.php" class="btn btn-sm btn-outline-secondary">All</a>
    </form>
    <div class="small mt-1">
      <a href="index.php?from=<?= $today ?>&to=<?= $today ?><?= $account_id > 0 ? '&account_id='.$account_id : '' ?>" class="mr-2">Today</a>
      <a href="index.php?from=<?= $monday ?>&to=<?= $sunday ?><?= $account_id > 0 ? '&account_id='.$account_id : '' ?>" class="mr-2">This Week</a>
      <a href="index.php?from=<?= $month_first ?>&to=<?= $month_last ?><?= $account_id > 0 ? '&account_id='.$account_id : '' ?>" class="mr-2">This Month</a>
    </div>
  </div>
  <div class="col-md-4 text-md-right mt-2 mt-md-0">
    <button type="button" class="btn btn-outline-secondary shadow-sm mr-2" onclick="window.print()"><i class="fas fa-print mr-1"></i>Print</button>
    <button type="button" class="btn btn-success shadow-sm" data-toggle="modal" data-target="#addBankModal"><i class="fas fa-plus mr-1"></i>Add Bank Account</button>
  </div>
</div>

<!-- Print header -->
<div class="d-none d-print-block mb-3 text-center">
  <h4 class="font-weight-bold mb-0" style="color:#0f172a;">Bestway Distribution</h4>
  <h5 class="font-weight-bold text-primary mt-2 mb-0">BANK BOOK</h5>
  <?php if ($account_id > 0): ?>
    <?php foreach ($accounts as $a): if ($a['id'] == $account_id): ?>
      <div class="mt-1 font-weight-bold">Account: <?= htmlspecialchars($a['bank_name'] . ' - ' . $a['account_title'] . ' (' . ($a['account_no'] ?? '') . ')') ?></div>
    <?php endif; endforeach; ?>
  <?php endif; ?>
  <?php if ($from || $to): ?>
    <div class="mt-1 font-weight-bold"><?= $from ? 'From: '.formatDate($from) : '' ?> <?= $to ? '— To: '.formatDate($to) : '' ?></div>
  <?php endif; ?>
  <small>Printed on <?= formatDate(date('Y-m-d')) ?></small>
</div>

<?php if (empty($accounts)): ?>
<div class="alert alert-warning d-print-none">
  <i class="fas fa-exclamation-triangle mr-2"></i>
  <strong>Koi Bank Account Nahi Mila!</strong>
  Bank payment use karne ke liye pehle ek bank account add karein.
  <button class="btn btn-sm btn-warning ml-3 font-weight-bold" data-toggle="modal" data-target="#addBankModal">
    <i class="fas fa-plus mr-1"></i> Abhi Add Karen
  </button>
</div>
<?php else: ?>
<!-- Bank Account Cards -->
<div class="row mb-3 d-print-none">
  <?php foreach ($accounts as $acc): ?>
  <div class="col-md-4 mb-3">
    <div class="card border-left-primary shadow h-100">
      <div class="card-body py-3">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
              <i class="fas fa-university mr-1"></i><?= htmlspecialchars($acc['bank_name']) ?>
            </div>
            <div class="font-weight-bold text-dark"><?= htmlspecialchars($acc['account_title']) ?></div>
            <div class="small text-muted">A/C: <?= htmlspecialchars($acc['account_number']) ?></div>
            <?php if (!empty($acc['branch_name'])): ?>
              <div class="small text-muted">Branch: <?= htmlspecialchars($acc['branch_name']) ?></div>
            <?php endif; ?>
          </div>
          <div class="text-right">
            <div class="small text-muted mb-1">Balance</div>
            <div class="h5 mb-0 font-weight-bold <?= (float)$acc['current_balance'] >= 0 ? 'text-success' : 'text-danger' ?>">
              PKR <?= formatCurrency($acc['current_balance']) ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="row mb-3">
  <div class="col-md-4 mb-2">
    <div class="card border-left-success shadow stat-card"><div class="card-body py-2">
      <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Deposits</div>
      <div class="h5 mb-0 font-weight-bold text-gray-800">PKR <?= formatCurrency($total_deposits) ?></div>
    </div></div>
  </div>
  <div class="col-md-4 mb-2">
    <div class="card border-left-danger shadow stat-card"><div class="card-body py-2">
      <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Withdrawals</div>
      <div class="h5 mb-0 font-weight-bold text-gray-800">PKR <?= formatCurrency($total_withdrawals) ?></div>
    </div></div>
  </div>
  <div class="col-md-4 mb-2">
    <div class="card border-left-info shadow stat-card"><div class="card-body py-2">
      <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Bank Balance</div>
      <div class="h5 mb-0 font-weight-bold text-gray-800">PKR <?= formatCurrency($total_bank) ?></div>
    </div></div>
  </div>
</div>

<div class="card shadow">
  <div class="card-header"><h6 class="mb-0"><i class="fas fa-university mr-1"></i> Bank Transactions</h6></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-bordered table-hover mb-0">
        <thead>
          <tr><th>Date</th><th>Account</th><th>Type</th><th>Description</th><th>Reference</th><th>Amount</th></tr>
        </thead>
        <tbody>
          <?php if ($latest_opening_date && $account_id == 0): ?>
          <tr class="table-light">
            <td><?= formatDate($latest_opening_date) ?></td>
            <td><span class="text-muted">All Accounts</span></td>
            <td><span class="badge badge-secondary">Opening Balance</span></td>
            <td>Opening balance (total)</td>
            <td><span class="text-muted">opening_balance</span></td>
            <td class="font-weight-bold">PKR <?= formatCurrency($total_opening) ?></td>
          </tr>
          <?php endif; ?>
          <?php foreach ($transactions as $t): ?>
          <tr>
            <td><?= formatDate($t['transaction_date']) ?></td>
            <td><?= htmlspecialchars($t['account_name'] ?? '-') ?></td>
            <td>
              <?php
                $type = $t['transaction_type'];
                if (in_array($type, ['deposit','transfer_in'])) echo '<span class="badge badge-success">'.ucfirst(str_replace('_',' ',$type)).'</span>';
                elseif (in_array($type, ['withdrawal','transfer_out'])) echo '<span class="badge badge-danger">'.ucfirst(str_replace('_',' ',$type)).'</span>';
                else echo '<span class="badge badge-secondary">'.ucfirst($type).'</span>';
              ?>
            </td>
            <td><?= htmlspecialchars($t['description'] ?? '-') ?></td>
            <td><span class="text-muted"><?= htmlspecialchars($t['reference_type'] ?? '-') ?></span></td>
            <td class="font-weight-bold <?= in_array($t['transaction_type'],['deposit','transfer_in']) ? 'text-success' : 'text-danger' ?>">
              <?= in_array($t['transaction_type'],['deposit','transfer_in']) ? '+' : '-' ?> PKR <?= formatCurrency($t['amount']) ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($transactions)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No bank transactions yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Opening Balance Modal -->
<div class="modal fade" id="openingModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="post">
      <input type="hidden" name="save_bank_opening" value="1">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-coins mr-1"></i> Bank Opening Balance</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Date *</label>
          <input type="date" name="opening_date" class="form-control datepicker" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="table-responsive">
          <table class="table table-bordered">
            <thead><tr><th>Account</th><th style="width:240px;">Opening Balance (PKR)</th></tr></thead>
            <tbody>
              <?php foreach ($accounts as $a): ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($a['account_name']) ?></strong><br>
                  <small class="text-muted"><?= htmlspecialchars($a['bank_name']) ?> &middot; <?= htmlspecialchars($a['account_no'] ?? '') ?></small>
                </td>
                <td><input type="number" step="0.01" min="0" class="form-control" name="opening[<?= $a['id'] ?>]" value="<?= (float)$a['opening_balance'] ?>"></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($accounts)): ?>
                <tr><td colspan="2" class="text-center text-muted py-3">No bank accounts added yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <small class="text-muted">Current balance = opening + deposits - withdrawals (auto-recalculated).</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-check mr-1"></i> Save</button>
      </div>
    </form>
  </div></div>
</div>

<!-- Add Bank Account Modal -->
<div class="modal fade" id="addBankModal" tabindex="-1" role="dialog" aria-labelledby="addBankModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="post" action="<?= BASE_URL ?>modules/bankbook/add_bank_account.php">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="addBankModalLabel"><i class="fas fa-university mr-2"></i>Add Bank Account</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="font-weight-bold">Bank Name <span class="text-danger">*</span></label>
              <input type="text" name="bank_name" class="form-control" placeholder="e.g. Meezan Bank, HBL, UBL" required>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="font-weight-bold">Account Title <span class="text-danger">*</span></label>
              <input type="text" name="account_title" class="form-control" placeholder="e.g. Bestway Wholesale" required>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="font-weight-bold">Account Number <span class="text-danger">*</span></label>
              <input type="text" name="account_number" class="form-control" placeholder="e.g. 1234-5678901-2" required>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="font-weight-bold">Branch Code / Name</label>
              <input type="text" name="branch_name" class="form-control" placeholder="e.g. 0012 / Main Branch">
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="font-weight-bold">Opening Balance (Rs.)</label>
              <input type="number" step="0.01" min="0" name="opening_balance" class="form-control" value="0" placeholder="0.00">
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-success font-weight-bold"><i class="fas fa-plus mr-1"></i>Add Bank Account</button>
      </div>
    </form>
  </div></div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
