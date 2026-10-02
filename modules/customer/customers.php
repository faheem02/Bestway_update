<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
$page_title = 'Customers';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . 'login.php'); exit;
}

if (($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_customer') && !canAddCustomer()) {
    redirect('customers.php', 'Access denied. You do not have permission to add customers.', 'error');
}

// Add customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_customer') {
    $name  = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    if ($name === '') { redirect('customers.php', 'Customer name is required.', 'error'); }
    if ($phone === '') { redirect('customers.php', 'Phone number is required.', 'error'); }

    $opening      = (float)($_POST['opening_balance'] ?? 0);
    $shop_name    = trim($_POST['shop_name'] ?? '') ?: $name;
    $customer_code = generateCustomerCode();
    $invoice_type = ($_POST['invoice_type'] ?? 'sale') === 'warranty' ? 'warranty' : 'sale';
    $license_number = ($invoice_type === 'warranty') ? trim($_POST['license_number'] ?? '') : null;

    $id = insert('customers', [
        'customer_code'   => $customer_code,
        'name'            => $name,
        'shop_name'       => $shop_name,
        'phone'           => $phone,
        'address'         => trim($_POST['address'] ?? ''),
        'area'            => trim($_POST['area'] ?? ''),
        'opening_balance' => $opening,
        'current_balance' => $opening,
        'status'          => 'Active',
        'invoice_type'    => $invoice_type,
        'license_number'  => $license_number ?: null,
    ]);
    redirect('customers.php', 'Customer "' . $name . '" added — ' . $customer_code . '.');
}

// Refresh balances
try {
    foreach ($pdo->query("SELECT id FROM customers")->fetchAll() as $c) {
        updateCustomerBalance($pdo, $c['id']);
    }
} catch (Exception $e) {}

try { $all_areas = $pdo->query("SELECT name, city FROM areas WHERE status = 1 ORDER BY name")->fetchAll(); }
catch (Exception $e) { $all_areas = []; }

// AJAX endpoint: live area search (DB se sirf saved areas — Google/autofill nahi)
if (isset($_GET['action']) && $_GET['action'] === 'search_area') {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    $rows = [];
    try {
        if ($q === '') {
            $stmt = $pdo->query("SELECT id, name, city FROM areas WHERE status = 1 ORDER BY name LIMIT 15");
        } else {
            $term = "%{$q}%";
            $stmt = $pdo->prepare("SELECT id, name, city FROM areas WHERE status = 1 AND (name LIKE :q1 OR city LIKE :q2) ORDER BY CASE WHEN name LIKE :exact THEN 1 ELSE 2 END, name ASC LIMIT 15");
            $stmt->execute([':q1' => $term, ':q2' => $term, ':exact' => "{$q}%"]);
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $rows = []; }
    echo json_encode($rows);
    exit;
}

$q = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM customers WHERE 1=1";
$params = [];
if ($q) {
    $sql .= " AND (name LIKE ? OR phone LIKE ? OR customer_code LIKE ?)";
    $params = ["%$q%", "%$q%", "%$q%"];
}
$sql .= " ORDER BY name ASC";
try {
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    $customers = $stmt->fetchAll();
} catch (Exception $e) { $customers = []; }

$total_due = 0; $total_advance = 0;
foreach ($customers as $c) {
    $b = (float)$c['current_balance'];
    if ($b > 0) $total_due += $b;
    elseif ($b < 0) $total_advance += abs($b);
}
$next_customer_code = generateCustomerCode();

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="row mb-3 d-print-none">
  <div class="col-md-6 mb-2">
    <div class="card border-left-danger shadow stat-card"><div class="card-body py-2">
      <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Receivable</div>
      <div class="h5 mb-0 font-weight-bold text-gray-800">PKR <?= formatCurrency($total_due) ?></div>
    </div></div>
  </div>
  <div class="col-md-6 mb-2">
    <div class="card border-left-success shadow stat-card"><div class="card-body py-2">
      <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Advance (We Owe)</div>
      <div class="h5 mb-0 font-weight-bold text-gray-800">PKR <?= formatCurrency($total_advance) ?></div>
    </div></div>
  </div>
</div>

<div class="card shadow">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
    <h6 class="mb-0"><i class="fas fa-users mr-1"></i> Customers (<?= count($customers) ?>)</h6>
    <div class="d-flex flex-wrap align-items-center mt-2 mt-md-0">
      <?php if (canAddCustomer()): ?>
      <button type="button" class="btn btn-sm btn-success mr-2" data-toggle="modal" data-target="#addCustomerModal">
        <i class="fas fa-plus mr-1"></i> Add Customer
      </button>
      <?php endif; ?>
      <button type="button" class="btn btn-sm btn-primary" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
    </div>
  </div>
  <div class="card-body">
    <form method="get" class="form-row mb-3 d-print-none">
      <div class="col-md-4"><input type="text" name="q" class="form-control" placeholder="Search name / phone / customer no" value="<?= htmlspecialchars($q) ?>"></div>
      <div class="col-md-2"><button class="btn btn-outline-primary btn-block"><i class="fas fa-search mr-1"></i>Search</button></div>
      <?php if ($q): ?><div class="col-md-2"><a href="customers.php" class="btn btn-outline-secondary btn-block">Clear</a></div><?php endif; ?>
    </form>

    <div class="table-responsive">
      <table class="table table-bordered table-hover mb-0">
        <thead>
          <tr>
            <th>Customer No</th><th>Name</th><th>Phone</th><th>Area</th>
            <th class="text-center">Invoice Type</th>
            <th class="text-right">Balance</th>
            <?php if (isAdmin()): ?><th class="text-center d-print-none">Action</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($customers as $c):
            $bal = (float)$c['current_balance'];
            $cls = $bal > 0 ? 'balance-negative' : ($bal < 0 ? 'balance-positive' : 'balance-zero');
            $lbl = $bal > 0 ? 'Receivable PKR '.formatCurrency($bal) : ($bal < 0 ? 'Advance PKR '.formatCurrency(abs($bal)) : 'PKR 0.00');
          ?>
          <tr>
            <td class="text-muted"><?= htmlspecialchars($c['customer_code'] ?? '-') ?></td>
            <td class="font-weight-bold"><?= htmlspecialchars($c['name']) ?></td>
            <td><?= htmlspecialchars($c['phone']) ?></td>
            <td><?= htmlspecialchars($c['area'] ?? '-') ?></td>
            <td class="text-center">
              <?php if (($c['invoice_type'] ?? 'sale') === 'warranty'): ?>
                <span class="badge badge-success"><i class="fas fa-shield-alt mr-1"></i>Warranty</span>
                <?php if (!empty($c['license_number'])): ?>
                  <div class="small text-muted mt-1 font-weight-bold" style="font-size:11px;" title="Drug / Trade License Number">
                    <i class="fas fa-id-card text-secondary mr-1"></i><?= htmlspecialchars($c['license_number']) ?>
                  </div>
                <?php endif; ?>
              <?php else: ?>
                <span class="badge badge-secondary">Sale</span>
              <?php endif; ?>
            </td>
            <td class="<?= $cls ?> font-weight-bold text-right"><?= $lbl ?></td>
            <?php if (isAdmin()): ?>
            <td class="text-center d-print-none" nowrap>
              <a href="edit_customer.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-warning" title="Edit"><i class="fas fa-edit"></i></a>
              <a href="customer_ledger.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="Ledger"><i class="fas fa-book"></i></a>
              <a href="receive_amount.php?customer_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-success" title="Receive Amount"><i class="fas fa-hand-holding-usd"></i></a>
              <a href="delete_customer.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete customer <?= htmlspecialchars($c['name']) ?>?');" title="Delete"><i class="fas fa-trash"></i></a>
            </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($customers)): ?>
            <tr><td colspan="<?= isAdmin() ? 7 : 6 ?>" class="text-center text-muted py-4">No customers yet.<?php if (canAddCustomer()): ?> <a href="#" data-toggle="modal" data-target="#addCustomerModal">Add your first customer</a>.<?php endif; ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="post">
      <input type="hidden" name="action" value="add_customer">
      <div class="modal-header">
        <h5 class="modal-title font-weight-bold"><i class="fas fa-user-plus text-primary mr-2"></i> Add New Customer</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label font-weight-bold">Customer No (Auto)</label>
            <input type="text" class="form-control bg-light font-weight-bold text-success" value="<?= htmlspecialchars($next_customer_code) ?>" readonly>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label font-weight-bold">Full Name *</label>
            <input type="text" name="name" class="form-control" required placeholder="Customer name">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label font-weight-bold">Shop Name</label>
            <input type="text" name="shop_name" class="form-control" placeholder="Business / shop name">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label font-weight-bold">Phone *</label>
            <input type="text" name="phone" class="form-control" required placeholder="0300-1234567">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label font-weight-bold">Area / Town</label>
            <div class="position-relative">
              <input type="text" name="area" id="areaSearchInput" class="form-control" placeholder="Area / town likhna shuro karein..." autocomplete="off" required>
              <div id="areaResults" class="list-group position-absolute w-100 shadow d-none" style="z-index:1060;max-height:220px;overflow-y:auto;"></div>
            </div>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label font-weight-bold">Opening Balance (PKR)</label>
            <input type="number" name="opening_balance" step="0.01" class="form-control" value="0">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label font-weight-bold">Invoice Type *</label>
            <select name="invoice_type" id="addInvoiceType" class="form-control" required onchange="toggleLicenseField(this.value)">
              <option value="sale">Sale Invoice (Simple, no warranty)</option>
              <option value="warranty">Warranty Invoice (with warranty section)</option>
            </select>
          </div>
          <div class="col-md-6 mb-3 d-none" id="licenseNumberBox">
            <label class="form-label font-weight-bold text-success"><i class="fas fa-id-card mr-1"></i> Customer License Number *</label>
            <input type="text" name="license_number" id="licenseNumberInput" class="form-control border-success" placeholder="e.g. 05-A/1234/2024">
          </div>
          <div class="col-md-12 mb-3">
            <label class="form-label font-weight-bold">Address</label>
            <input type="text" name="address" class="form-control" placeholder="Optional">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save mr-1"></i> Save Customer</button>
      </div>
    </form>
  </div></div>
</div>

<script>
function toggleLicenseField(val) {
  var box = document.getElementById('licenseNumberBox');
  var input = document.getElementById('licenseNumberInput');
  if (!box || !input) return;
  if (val === 'warranty') {
    box.classList.remove('d-none');
    input.focus();
  } else {
    box.classList.add('d-none');
    input.value = '';
  }
}

document.addEventListener('DOMContentLoaded', function() {
  var sel = document.getElementById('addInvoiceType');
  if (sel) { toggleLicenseField(sel.value); }

  var urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('add') === '1' || urlParams.get('action') === 'add') {
    $('#addCustomerModal').modal('show');
  }

  // ==== Area live search (DB suggestions) — Chrome/Google autofill band ====
  var areaTimer = null;
  function renderAreas(rows){
    var c = $('#areaResults');
    if (!rows.length) { c.empty().addClass('d-none'); return; }
    c.empty().removeClass('d-none');
    rows.forEach(function(r){
      c.append('<a href="#" class="area-option list-group-item list-group-item-action px-3 py-1 shadow-sm" data-area="' + r.name.replace(/"/g,'&quot;') + '">' + r.name + ' <small class="text-muted">' + (r.city ? ' — ' + r.city : '') + '</small></a>');
    });
  }
  function hideAreas(){ $('#areaResults').empty().addClass('d-none'); }
  var areaInput = document.getElementById('areaSearchInput');
  if (areaInput) {
    areaInput.addEventListener('input', function(){
      var q = this.value.trim();
      if (!q) { hideAreas(); return; }
      clearTimeout(areaTimer);
      areaTimer = setTimeout(function(){
        fetch('customers.php?action=search_area&q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function(r){ return r.json(); })
          .then(renderAreas)
          .catch(hideAreas);
      }, 160);
    });
    areaInput.addEventListener('blur', function(){ setTimeout(hideAreas, 150); });
  }
  $(document).on('click', '.area-option', function(e){
    e.preventDefault();
    $('#areaSearchInput').val($(this).data('area'));
    hideAreas();
  });
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
