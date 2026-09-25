<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
$page_title = 'Areas & Territories';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . 'login.php'); exit;
}
requireRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name        = trim($_POST['name'] ?? '');
        $city        = trim($_POST['city'] ?? 'Lahore');
        $description = trim($_POST['description'] ?? '');
        $status      = (int)($_POST['status'] ?? 1);
        if ($name === '') { redirect('index.php', 'Area name is required.', 'error'); }
        $chk = $pdo->prepare("SELECT id FROM areas WHERE LOWER(name) = LOWER(?)");
        $chk->execute([$name]);
        if ($chk->fetchColumn()) { redirect('index.php', 'An area with this name already exists.', 'error'); }
        $id = insert('areas', ['name'=>$name,'city'=>($city ?: 'Lahore'),'description'=>$description,'status'=>$status,'created_at'=>date('Y-m-d')]);
        redirect('index.php', 'Area "' . $name . '" added successfully.');
    }

    if ($action === 'edit') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = (int)($_POST['status'] ?? 1);
        if (!$id || $name === '') { redirect('index.php', 'Invalid area data.', 'error'); }
        $old = getById('areas', $id);
        if (!$old) { redirect('index.php', 'Area not found.', 'error'); }
        $chk = $pdo->prepare("SELECT id FROM areas WHERE LOWER(name) = LOWER(?) AND id <> ?");
        $chk->execute([$name, $id]);
        if ($chk->fetchColumn()) { redirect('index.php', 'Another area with this name already exists.', 'error'); }
        update('areas', ['name'=>$name,'city'=>$city,'description'=>$description,'status'=>$status,'updated_at'=>date('Y-m-d')], $id);
        if (strcasecmp($old['name'], $name) !== 0) {
            $pdo->prepare("UPDATE customers SET area = ? WHERE LOWER(area) = LOWER(?)")->execute([$name, $old['name']]);
        }
        redirect('index.php', 'Area "' . $name . '" updated.');
    }

    if ($action === 'delete') {
        $id   = (int)($_POST['id'] ?? 0);
        $area = getById('areas', $id);
        if (!$area) { redirect('index.php', 'Area not found.', 'error'); }
        $c = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE LOWER(area) = LOWER(?)");
        $c->execute([$area['name']]); $c_count = (int)$c->fetchColumn();
        if ($c_count > 0) { redirect('index.php', 'Cannot delete: ' . $c_count . ' customer(s) assigned. Deactivate instead.', 'error'); }
        $pdo->prepare("DELETE FROM areas WHERE id = ?")->execute([$id]);
        redirect('index.php', 'Area deleted.');
    }
}

$areas = $pdo->query("SELECT a.* FROM areas a ORDER BY a.name ASC")->fetchAll();
$all_customers = $pdo->query("SELECT area FROM customers WHERE area IS NOT NULL AND area <> ''")->fetchAll(PDO::FETCH_COLUMN);

$total_areas  = count($areas);
$active_areas = 0;
foreach ($areas as &$a) {
    if ($a['status'] == 1) $active_areas++;
    $a_lower = strtolower(trim($a['name']));
    $a['cust_count'] = 0;
    foreach ($all_customers as $ca) {
        if (strtolower(trim($ca)) === $a_lower) $a['cust_count']++;
    }
}
unset($a);

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="row mb-3 d-print-none">
  <div class="col-md-8">
    <div class="alert alert-info alert-dismissible fade show py-2 mb-0" role="alert">
      <i class="fas fa-map-marked-alt mr-1"></i> <strong>Area &amp; Territory Management</strong> — Manage delivery zones and assign to employees.
      <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
  </div>
  <div class="col-md-4 text-md-right">
    <button class="btn btn-outline-secondary shadow-sm mr-2" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
    <button class="btn btn-success shadow-sm" data-toggle="modal" data-target="#addAreaModal"><i class="fas fa-plus-circle mr-1"></i> Add Area</button>
  </div>
</div>

<div class="row mb-3 d-print-none">
  <div class="col-xl-3 col-md-6 mb-2">
    <div class="card border-left-primary shadow stat-card"><div class="card-body py-2">
      <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Areas</div>
      <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_areas ?></div>
    </div></div>
  </div>
  <div class="col-xl-3 col-md-6 mb-2">
    <div class="card border-left-success shadow stat-card"><div class="card-body py-2">
      <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Areas</div>
      <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $active_areas ?></div>
    </div></div>
  </div>
  <div class="col-xl-3 col-md-6 mb-2">
    <div class="card border-left-info shadow stat-card"><div class="card-body py-2">
      <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Assigned Customers</div>
      <div class="h5 mb-0 font-weight-bold text-gray-800"><?= count($all_customers) ?></div>
    </div></div>
  </div>
</div>

<div class="card shadow">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
    <h6 class="mb-0"><i class="fas fa-map-marker-alt mr-1"></i> Registered Areas (<?= $total_areas ?>)</h6>
    <input type="text" id="areaSearch" class="form-control form-control-sm d-print-none mt-2 mt-md-0" placeholder="Search area / city…" style="max-width:260px;">
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-bordered table-hover mb-0" id="areaTable">
        <thead>
          <tr>
            <th style="width:50px;">#</th>
            <th>Area / Territory</th>
            <th>City</th>
            <th>Description</th>
            <th class="text-center">Customers</th>
            <th class="text-center">Status</th>
            <th class="text-center d-print-none" style="width:110px;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($areas)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No areas yet. Click "Add Area" to create one.</td></tr>
          <?php else: $i=1; foreach ($areas as $a): ?>
            <tr>
              <td><?= $i++ ?></td>
              <td class="font-weight-bold text-primary"><?= htmlspecialchars($a['name']) ?></td>
              <td><?= htmlspecialchars($a['city'] ?: 'Lahore') ?></td>
              <td><?= htmlspecialchars($a['description'] ?: '—') ?></td>
              <td class="text-center"><span class="badge badge-primary badge-pill px-2"><?= $a['cust_count'] ?></span></td>
              <td class="text-center">
                <?= $a['status'] == 1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>' ?>
              </td>
              <td class="text-center d-print-none" nowrap>
                <button type="button" class="btn btn-sm btn-outline-warning btn-edit-area"
                  data-id="<?= $a['id'] ?>" data-name="<?= htmlspecialchars($a['name']) ?>"
                  data-city="<?= htmlspecialchars($a['city']) ?>" data-desc="<?= htmlspecialchars($a['description']) ?>"
                  data-status="<?= $a['status'] ?>"><i class="fas fa-edit"></i></button>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete area <?= htmlspecialchars($a['name']) ?>?');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $a['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash-alt"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add Area Modal -->
<div class="modal fade" id="addAreaModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post">
      <input type="hidden" name="action" value="add">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-plus-circle text-success mr-1"></i> Add New Area</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label font-weight-bold">Area / Territory Name *</label>
          <input type="text" name="name" class="form-control" required placeholder="e.g. Johar Town, Gulberg">
        </div>
        <div class="form-group">
          <label class="form-label font-weight-bold">City</label>
          <input type="text" name="city" class="form-control" value="Lahore" placeholder="e.g. Lahore">
        </div>
        <div class="form-group">
          <label class="form-label font-weight-bold">Description</label>
          <textarea name="description" class="form-control" rows="2" placeholder="Optional notes"></textarea>
        </div>
        <div class="form-group mb-0">
          <label class="form-label font-weight-bold">Status</label>
          <select name="status" class="form-control">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-success"><i class="fas fa-save mr-1"></i> Save Area</button>
      </div>
    </form>
  </div></div>
</div>

<!-- Edit Area Modal -->
<div class="modal fade" id="editAreaModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="editAreaId">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-edit text-warning mr-1"></i> Edit Area</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label font-weight-bold">Area Name *</label>
          <input type="text" name="name" id="editAreaName" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label font-weight-bold">City</label>
          <input type="text" name="city" id="editAreaCity" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label font-weight-bold">Description</label>
          <textarea name="description" id="editAreaDesc" class="form-control" rows="2"></textarea>
        </div>
        <div class="form-group mb-0">
          <label class="form-label font-weight-bold">Status</label>
          <select name="status" id="editAreaStatus" class="form-control">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-warning"><i class="fas fa-save mr-1"></i> Update Area</button>
      </div>
    </form>
  </div></div>
</div>

<script>
$(document).ready(function(){
  $('.btn-edit-area').click(function(){
    $('#editAreaId').val($(this).data('id'));
    $('#editAreaName').val($(this).data('name'));
    $('#editAreaCity').val($(this).data('city'));
    $('#editAreaDesc').val($(this).data('desc'));
    $('#editAreaStatus').val($(this).data('status'));
    $('#editAreaModal').modal('show');
  });
  $('#areaSearch').on('keyup', function(){
    var q = $(this).val().toLowerCase();
    $('#areaTable tbody tr').each(function(){ $(this).toggle($(this).text().toLowerCase().indexOf(q) > -1); });
  });
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
