<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
$page_title = 'Edit Customer';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . 'login.php'); exit;
}
requireRole(['admin']);
$id = (int)($_GET['id'] ?? 0);
$customer = getById('customers', $id);
if (!$customer) { redirect('customers.php', 'Customer not found.', 'error'); }

try { $all_areas = $pdo->query("SELECT name, city FROM areas WHERE status = 1 ORDER BY name")->fetchAll(); }
catch (Exception $e) { $all_areas = []; }

// AJAX: DB-database saved areas search (Google/Chrome autofill nahi — sirf hamari DB)
if (isset($_GET['action']) && $_GET['action'] === 'search_area') {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    try {
        if ($q === '') { $stmt = $pdo->query("SELECT id, name, city FROM areas WHERE status = 1 ORDER BY name LIMIT 15"); }
        else {
            $term = "%{$q}%";
            $stmt = $pdo->prepare("SELECT id, name, city FROM areas WHERE status = 1 AND (name LIKE :q1 OR city LIKE :q2) ORDER BY CASE WHEN name LIKE :exact THEN 1 ELSE 2 END, name LIMIT 15");
            $stmt->execute([':q1' => $term, ':q2' => $term, ':exact' => "{$q}%"]);
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $rows = []; }
    echo json_encode($rows);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    if ($name === '') { redirect('edit_customer.php?id='.$id, 'Name is required.', 'error'); }
    $invoice_type = ($_POST['invoice_type'] ?? 'sale') === 'warranty' ? 'warranty' : 'sale';
    $license_number = ($invoice_type === 'warranty') ? trim($_POST['license_number'] ?? '') : null;
    update('customers', [
        'name'           => $name,
        'shop_name'      => trim($_POST['shop_name'] ?? '') ?: $name,
        'phone'          => $phone,
        'area'           => trim($_POST['area'] ?? ''),
        'address'        => trim($_POST['address'] ?? ''),
        'invoice_type'   => $invoice_type,
        'license_number' => $license_number ?: null,
    ], $id);
    redirect('customers.php', 'Customer "' . $name . '" updated.');
}
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="card shadow">
  <div class="card-header"><h6 class="mb-0"><i class="fas fa-user-edit mr-1"></i> Edit Customer</h6></div>
  <div class="card-body">
    <form method="post">
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label font-weight-bold">Customer No</label>
          <input type="text" class="form-control bg-light font-weight-bold text-success" value="<?= htmlspecialchars($customer['customer_code'] ?? $customer['id']) ?>" readonly>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label font-weight-bold">Full Name *</label>
          <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($customer['name']) ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label font-weight-bold">Shop Name</label>
          <input type="text" name="shop_name" class="form-control" value="<?= htmlspecialchars($customer['shop_name'] ?? '') ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label font-weight-bold">Phone *</label>
          <input type="text" name="phone" class="form-control" required value="<?= htmlspecialchars($customer['phone']) ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label font-weight-bold">Area / Town</label>
          <div class="position-relative">
            <input type="text" name="area" id="editAreaSearchInput" class="form-control" placeholder="Area / town likhna shuro karein..." autocomplete="off" value="<?= htmlspecialchars($customer['area'] ?? '') ?>">
            <div id="editAreaResults" class="list-group position-absolute w-100 shadow d-none" style="z-index:1060;max-height:220px;overflow-y:auto;"></div>
          </div>
          <small class="text-muted d-block mt-1">Database ke saved areas yahan suggestion ke taur par aayenge. Naya area bhi type kar sakte hain.</small>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label font-weight-bold">Invoice Type *</label>
          <?php $cur_inv_type = ($customer['invoice_type'] ?? 'sale') === 'warranty' ? 'warranty' : 'sale'; ?>
          <select name="invoice_type" id="editInvoiceType" class="form-control" required onchange="toggleEditLicenseField(this.value)">
            <option value="sale" <?= $cur_inv_type === 'sale' ? 'selected' : '' ?>>Sale Invoice (Simple, no warranty)</option>
            <option value="warranty" <?= $cur_inv_type === 'warranty' ? 'selected' : '' ?>>Warranty Invoice (with warranty section)</option>
          </select>
          <small class="text-muted">Is customer ka print sales history page se hamesha isi type ka nikle ga</small>
        </div>
        <div class="col-md-6 mb-3 <?= $cur_inv_type === 'warranty' ? '' : 'd-none' ?>" id="editLicenseNumberBox">
          <label class="form-label font-weight-bold text-success"><i class="fas fa-id-card mr-1"></i> Customer License Number *</label>
          <input type="text" name="license_number" id="editLicenseNumberInput" class="form-control border-success" value="<?= htmlspecialchars($customer['license_number'] ?? '') ?>" placeholder="e.g. 05-A/1234/2024">
          <small class="text-muted">Warranty invoice ke liye customer ka drug / trade license number</small>
        </div>
        <div class="col-md-12 mb-3">
          <label class="form-label font-weight-bold">Address</label>
          <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($customer['address'] ?? '') ?>">
        </div>
        <div class="col-12 d-flex justify-content-between mt-2">
          <a href="customers.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
          <button type="submit" class="btn btn-primary px-5"><i class="fas fa-save mr-1"></i> Update Customer</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function toggleEditLicenseField(val) {
  var box = document.getElementById('editLicenseNumberBox');
  var input = document.getElementById('editLicenseNumberInput');
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
  // ===== Live DB area search (Google/Chrome autofill NOT yahan — sirf DB suggestions) =====
  var editAreaTimer = null;
  function editRenderAreas(rows){
    var c = $('#editAreaResults');
    if (!rows.length) { editHideAreas(); return; }
    c.empty().removeClass('d-none');
    rows.forEach(function(r){
      c.append('<a href="#" class="list-group-item list-group-item-action edit-area-opt px-3 py-1" data-area="' + r.name.replace(/"/g, '&quot;') + '" style="cursor:pointer;">' + r.name
        + (r.city ? ' <small class="text-muted"><i class="fas fa-map-marker-alt mr-1"></i>' + r.city + '</small>' : '') + '</a>');
    });
  }
  function editHideAreas(){ $('#editAreaResults').empty().addClass('d-none'); }
  var editAreaInput = document.getElementById('editAreaSearchInput');
  if (editAreaInput) {
    editAreaInput.addEventListener('input', function(){
      var q = this.value.trim();
      if (!q) { editHideAreas(); return; }
      clearTimeout(editAreaTimer);
      editAreaTimer = setTimeout(function(){
        fetch('edit_customer.php?action=search_area&q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function(r){ return r.json(); })
          .then(editRenderAreas)
          .catch(editHideAreas);
      }, 170);
    });
    editAreaInput.addEventListener('blur', function(){ setTimeout(editHideAreas, 160); });
  }
  $(document).on('click', '.edit-area-opt', function(e){
    e.preventDefault();
    $('#editAreaSearchInput').val($(this).data('area'));
    editHideAreas();
  });
  $(document).on('click', function(e){ if (!$(e.target).closest('.position-relative, .col-md-6').length) editHideAreas(); });
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
