<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
$page_title = 'Employees';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . 'login.php'); exit;
}
requireRole(['admin']);

$type = $_GET['type'] ?? '';
$types = ['salesman' => 'Salesman', 'general' => 'General'];

$where = ''; $params = [];
if ($type && isset($types[$type])) { $where = 'WHERE employee_type = :t'; $params[':t'] = $type; }

try {
    $sql = "SELECT e.*, u.username AS login_username
            FROM employees e
            LEFT JOIN users u ON e.user_id = u.id
            $where ORDER BY e.employee_type, e.full_name";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    $employees = $stmt->fetchAll();
    $counts = [
        'salesman' => countRows('employees', 'employee_type', 'salesman'),
        'general'  => countRows('employees', 'employee_type', 'general'),
    ];
} catch (Exception $e) {
    $employees = []; $counts = ['salesman'=>0,'general'=>0];
}
$total = array_sum($counts);

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="row mb-3">
  <div class="col-md-3 col-6 mb-2">
    <a href="index.php" class="text-decoration-none">
      <div class="small-box bg-slate">
        <div class="inner"><h3><?= $total ?></h3><p>Total Employees</p></div>
        <div class="icon"><i class="fas fa-users"></i></div>
      </div>
    </a>
  </div>
  <div class="col-md-4 col-sm-6 mb-2">
    <a href="index.php?type=salesman" class="text-decoration-none">
      <div class="small-box bg-emerald">
        <div class="inner"><h3><?= $counts['salesman'] ?></h3><p>Salesman</p></div>
        <div class="icon"><i class="fas fa-user-tie"></i></div>
      </div>
    </a>
  </div>
  <div class="col-md-4 col-sm-6 mb-2">
    <a href="index.php?type=general" class="text-decoration-none">
      <div class="small-box bg-blue">
        <div class="inner"><h3><?= $counts['general'] ?></h3><p>General</p></div>
        <div class="icon"><i class="fas fa-user-cog"></i></div>
      </div>
    </a>
  </div>
</div>

<div class="card shadow">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap d-print-none">
    <h6 class="mb-0"><i class="fas fa-list mr-1"></i> Employees <?= $type && isset($types[$type]) ? '— ' . $types[$type] : '' ?></h6>
    <div class="d-flex flex-wrap align-items-center mt-2 mt-md-0">
      <input type="text" id="empSearch" class="form-control form-control-sm mr-2" style="min-width:200px;" placeholder="Search name, phone, CNIC…" autocomplete="off">
      <form method="get" class="form-inline mb-0 mr-2">
        <select name="type" class="form-control form-control-sm" onchange="this.form.submit()">
          <option value="">All Types</option>
          <?php foreach ($types as $k => $v): ?>
            <option value="<?= $k ?>" <?= $type == $k ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <button class="btn btn-sm btn-outline-secondary mr-2" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
      <a href="ledger.php" class="btn btn-sm btn-outline-info mr-2"><i class="fas fa-file-invoice-dollar mr-1"></i> Commission Ledger</a>
      <a href="create.php" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Add Employee</a>
    </div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-bordered table-hover mb-0" id="employeesTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Emp ID</th>
            <th>Name</th>
            <th>Type</th>
            <th>Phone</th>
            <th>CNIC</th>
            <th>Login User</th>
            <th class="text-right">Commission (%)</th>
            <th class="text-center">Status</th>
            <th class="text-center d-print-none">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($employees)): ?>
            <tr><td colspan="10" class="text-center text-muted py-4">No employees found. <a href="create.php">Add your first employee</a>.</td></tr>
          <?php else: $i = 1; foreach ($employees as $e): ?>
            <tr>
              <td><?= $i++ ?></td>
              <td><code><?= htmlspecialchars($e['emp_code'] ?? '-') ?></code></td>
              <td class="font-weight-bold"><?= htmlspecialchars($e['full_name']) ?></td>
              <td>
                <?php if ($e['employee_type'] == 'salesman'): ?><span class="badge badge-success">Salesman</span>
                <?php else: ?><span class="badge badge-secondary">General</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($e['phone'] ?: '-') ?></td>
              <td><?= htmlspecialchars($e['cnic'] ?: '-') ?></td>
              <td><?= $e['login_username'] ? '<code>' . htmlspecialchars($e['login_username']) . '</code>' : '<span class="text-muted">No login</span>' ?></td>
              <td class="text-right"><?= number_format((float)($e['commission_rate'] ?? 0), 2) ?>%</td>
              <td class="text-center">
                <?php if ($e['status']): ?>
                  <span class="badge badge-success">Active</span>
                <?php else: ?>
                  <span class="badge badge-danger">Inactive</span>
                <?php endif; ?>
              </td>
              <td class="text-center d-print-none">
                <a href="ledger.php?emp_id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-info" title="View Commission & Ledger"><i class="fas fa-file-invoice-dollar"></i></a>
                <a href="edit.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                <a href="delete.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this employee?');" title="Delete"><i class="fas fa-trash"></i></a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          <tr id="empNoMatch" style="display:none;"><td colspan="10" class="text-center text-muted py-3">No employees match your search.</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
$(document).ready(function(){
  $('#empSearch').on('input', function(){
    var q = $.trim(this.value).toLowerCase();
    var vis = 0;
    $('#employeesTable tbody tr:not(#empNoMatch)').each(function(){
      var show = !q || $(this).text().toLowerCase().indexOf(q) > -1;
      $(this).toggle(show); if (show) vis++;
    });
    $('#empNoMatch').toggle(vis === 0 && q !== '');
  });
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
