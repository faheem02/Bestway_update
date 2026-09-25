<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
$page_title = 'Suppliers';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . 'login.php'); exit;
}
requireRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_supplier') {
    $name  = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    if ($name === '') { redirect('suppliers.php', 'Supplier name is required.', 'error'); }
    if ($phone === '') { redirect('suppliers.php', 'Phone number is required.', 'error'); }
    $opening      = (float)($_POST['opening_balance'] ?? 0);
    $company_name = trim($_POST['company_name'] ?? '') ?: $name;
    $supplier_code = 'SUP-' . str_pad((countRows('suppliers') + 1), 4, '0', STR_PAD_LEFT);
    insert('suppliers', [
        'supplier_code'   => $supplier_code,
        'name'            => $name,
        'company_name'    => $company_name,
        'phone'           => $phone,
        'email'           => trim($_POST['email'] ?? ''),
        'address'         => trim($_POST['address'] ?? ''),
        'opening_balance' => $opening,
        'current_balance' => $opening,
        'credit_days'     => (int)($_POST['credit_days'] ?? 0),
        'status'          => 'Active',
    ]);
    redirect('suppliers.php', 'Supplier "' . $name . '" added.');
}

$q = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM suppliers WHERE 1=1";
$params = [];
if ($q) { $sql .= " AND (name LIKE ? OR phone LIKE ? OR supplier_code LIKE ?)"; $params = ["%$q%","%$q%","%$q%"]; }
$sql .= " ORDER BY name ASC";
try { $stmt=$pdo->prepare($sql); $stmt->execute($params); $suppliers=$stmt->fetchAll(); }
catch (Exception $e) { $suppliers=[]; }

$total_payable = array_sum(array_map(fn($s)=>(float)$s['current_balance']>0?(float)$s['current_balance']:0, $suppliers));

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="row mb-3">
  <div class="col-md-6 mb-2">
    <div class="card border-left-danger shadow stat-card"><div class="card-body py-2">
      <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Payable</div>
      <div class="h5 mb-0 font-weight-bold text-gray-800">PKR <?= formatCurrency($total_payable) ?></div>
    </div></div>
  </div>
  <div class="col-md-6 mb-2">
    <div class="card border-left-info shadow stat-card"><div class="card-body py-2">
      <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Suppliers</div>
      <div class="h5 mb-0 font-weight-bold text-gray-800"><?= count($suppliers) ?></div>
    </div></div>
  </div>
</div>

<div class="card shadow">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
    <h6 class="mb-0"><i class="fas fa-truck-loading mr-1"></i> Suppliers (<?= count($suppliers) ?>)</h6>
    <div class="d-flex flex-wrap align-items-center mt-2 mt-md-0">
      <button type="button" class="btn btn-sm btn-success mr-2" data-toggle="modal" data-target="#addSupplierModal">
        <i class="fas fa-plus mr-1"></i> Add Supplier
      </button>
      <button class="btn btn-sm btn-primary" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
    </div>
  </div>
  <div class="card-body">
    <form method="get" class="form-row mb-3 d-print-none">
      <div class="col-md-4"><input type="text" name="q" class="form-control" placeholder="Search name / phone / no" value="<?= htmlspecialchars($q) ?>"></div>
      <div class="col-md-2"><button class="btn btn-outline-primary btn-block"><i class="fas fa-search mr-1"></i>Search</button></div>
      <?php if ($q): ?><div class="col-md-2"><a href="suppliers.php" class="btn btn-outline-secondary btn-block">Clear</a></div><?php endif; ?>
    </form>
    <div class="table-responsive">
      <table class="table table-bordered table-hover mb-0">
        <thead>
          <tr><th>Supplier No</th><th>Name</th><th>Phone</th><th>Company</th><th class="text-right">Balance</th><th class="text-center d-print-none">Action</th></tr>
        </thead>
        <tbody>
          <?php foreach ($suppliers as $s):
            $bal=(float)$s['current_balance'];
            $cls=$bal>0?'text-danger':($bal<0?'text-success':'text-muted');
            $lbl=$bal>0?'Payable PKR '.formatCurrency($bal):($bal<0?'Advance PKR '.formatCurrency(abs($bal)):'PKR 0.00');
          ?>
          <tr>
            <td class="text-muted"><?= htmlspecialchars($s['supplier_code'] ?? '-') ?></td>
            <td class="font-weight-bold"><?= htmlspecialchars($s['name']) ?></td>
            <td><?= htmlspecialchars($s['phone'] ?? '-') ?></td>
            <td><?= htmlspecialchars($s['company_name'] ?? '-') ?></td>
            <td class="<?= $cls ?> font-weight-bold text-right"><?= $lbl ?></td>
            <td class="text-center d-print-none" nowrap>
              <a href="edit_supplier.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-warning" title="Edit"><i class="fas fa-edit"></i></a>
              <a href="supplier_ledger.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary" title="Ledger"><i class="fas fa-book"></i></a>
              <a href="pay_amount.php?supplier_id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-success" title="Pay Amount"><i class="fas fa-money-bill-alt"></i></a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($suppliers)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No suppliers yet. <a href="#" data-toggle="modal" data-target="#addSupplierModal">Add first supplier</a>.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="post">
      <input type="hidden" name="action" value="add_supplier">
      <div class="modal-header">
        <h5 class="modal-title font-weight-bold"><i class="fas fa-truck-loading text-primary mr-2"></i> Add New Supplier</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label font-weight-bold">Supplier Name *</label>
            <input type="text" name="name" class="form-control" required placeholder="Contact person / supplier name">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label font-weight-bold">Company Name</label>
            <input type="text" name="company_name" class="form-control" placeholder="Company / distributor name">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label font-weight-bold">Phone *</label>
            <input type="text" name="phone" class="form-control" required placeholder="0300-1234567">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label font-weight-bold">Email</label>
            <input type="email" name="email" class="form-control" placeholder="Optional">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label font-weight-bold">Opening Balance (PKR)</label>
            <input type="number" name="opening_balance" step="0.01" class="form-control" value="0">
            <small class="text-muted">+ = amount you owe supplier</small>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label font-weight-bold">Credit Days</label>
            <input type="number" name="credit_days" class="form-control" value="0">
          </div>
          <div class="col-md-12 mb-3">
            <label class="form-label font-weight-bold">Address</label>
            <input type="text" name="address" class="form-control" placeholder="Optional">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save mr-1"></i> Save Supplier</button>
      </div>
    </form>
  </div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('add') === '1' || urlParams.get('action') === 'add') {
    $('#addSupplierModal').modal('show');
  }
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
