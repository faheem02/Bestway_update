<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
$page_title = 'Add Employee';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . 'login.php'); exit;
}
requireRole(['admin']);
global $role_map;

try { $all_areas = $pdo->query("SELECT id, name, city FROM areas WHERE status = 1 ORDER BY name ASC")->fetchAll(); }
catch (Exception $e) { $all_areas = []; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name     = trim($_POST['full_name'] ?? '');
    $employee_type = $_POST['employee_type'] ?? 'salesman';
    $phone         = trim($_POST['phone'] ?? '');
    $selected_areas = (array)($_POST['areas'] ?? []);
    $area          = implode(', ', array_filter(array_map('trim', $selected_areas)));
    $cnic          = trim($_POST['cnic'] ?? '');
    $address       = trim($_POST['address'] ?? '');
    $joining_date  = $_POST['joining_date'] ?: null;
    $commission    = (float)($_POST['commission_rate'] ?? 0);
    $username      = trim($_POST['username'] ?? '');
    $password      = $_POST['password'] ?? '';

    if ($full_name === '') { redirect('create.php', 'Please enter employee name.', 'error'); }
    if (!in_array($employee_type, ['salesman','general'])) $employee_type = 'salesman';
    if ($commission < 0) $commission = 0;
    if ($commission > 100) $commission = 100;

    $emp_code = trim($_POST['emp_code'] ?? '');
    if ($emp_code !== '') {
        $chk = $pdo->prepare("SELECT id FROM employees WHERE emp_code = ?");
        $chk->execute([$emp_code]);
        if ($chk->fetch()) $emp_code = '';
    }
    if ($emp_code === '') $emp_code = generateEmployeeCode();

    $needs_login = ($employee_type === 'salesman');
    if ($needs_login) {
        if ($username === '') { redirect('create.php', 'Login username is required for Salesman.', 'error'); }
        if (strlen($password) < 4) { redirect('create.php', 'Password must be at least 4 characters.', 'error'); }
        $chk2 = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $chk2->execute([$username]);
        if ($chk2->fetch()) { redirect('create.php', 'Username "' . $username . '" already exists.', 'error'); }
    }

    $pdo->beginTransaction();
    try {
        $user_id = null;
        if ($needs_login) {
            $user_id = insert('users', [
                'username'   => $username,
                'password'   => $password,
                'full_name'  => $full_name,
                'phone'      => $phone,
                'role'       => 'salesman',
                'status'     => 'Active',
                'created_at' => date('Y-m-d'),
            ]);
        }
        insert('employees', [
            'user_id'       => $user_id,
            'emp_code'      => $emp_code,
            'full_name'     => $full_name,
            'employee_type' => $employee_type,
            'phone'         => $phone,
            'area'          => $area,
            'cnic'          => $cnic,
            'address'       => $address,
            'joining_date'  => $joining_date,
            'salary'        => 0,
            'commission_rate' => $commission,
            'status'        => 1,
            'created_at'    => date('Y-m-d'),
        ]);
        $pdo->commit();
        redirect('index.php', 'Employee "' . $full_name . '" added successfully.');
    } catch (Exception $e) {
        $pdo->rollBack();
        redirect('create.php', 'Error: ' . $e->getMessage(), 'error');
    }
}

$next_emp_code = generateEmployeeCode();
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="card shadow">
  <div class="card-header">
    <h6 class="mb-0"><i class="fas fa-user-plus mr-1"></i> Add Employee</h6>
  </div>
  <div class="card-body">
    <form method="post">
      <input type="hidden" name="emp_code" value="<?= htmlspecialchars($next_emp_code) ?>">
      <div class="row">

        <div class="col-md-6 mb-3">
          <label class="form-label">Employee ID (Auto)</label>
          <input type="text" class="form-control font-weight-bold text-success bg-light" value="<?= htmlspecialchars($next_emp_code) ?>" readonly>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Employee Name *</label>
          <input type="text" name="full_name" class="form-control" required placeholder="e.g. Muhammad Ali">
        </div>

        <div class="col-md-6 mb-3">
          <label class="form-label">Employee Type *</label>
          <select name="employee_type" class="form-control" id="empTypeSelect" required>
            <option value="salesman">Salesman</option>
            <option value="general">General</option>
          </select>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Phone</label>
          <input type="text" name="phone" class="form-control" placeholder="03xx-xxxxxxx">
        </div>

        <div class="col-md-12 mb-3">
          <label class="form-label font-weight-bold">Assigned Areas / Territories</label>
          <div class="border rounded p-3 bg-light">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="small text-muted">Select one or more areas:</span>
              <div>
                <button type="button" class="btn btn-xs btn-outline-primary btn-sm py-0 px-2" id="selectAllAreas">Select All</button>
                <button type="button" class="btn btn-xs btn-outline-secondary btn-sm py-0 px-2 ml-1" id="clearAllAreas">Clear</button>
              </div>
            </div>
            <?php if (empty($all_areas)): ?>
              <p class="text-muted small mb-0">No areas added yet. <a href="<?= BASE_URL ?>modules/areas/index.php">Add areas first</a>.</p>
            <?php else: ?>
            <div class="row">
              <?php foreach ($all_areas as $ar): ?>
              <div class="col-md-3 col-sm-6 mb-2">
                <div class="custom-control custom-checkbox">
                  <input type="checkbox" name="areas[]" value="<?= htmlspecialchars($ar['name']) ?>" class="custom-control-input area-checkbox" id="area_<?= $ar['id'] ?>">
                  <label class="custom-control-label" for="area_<?= $ar['id'] ?>">
                    <?= htmlspecialchars($ar['name']) ?> <small class="text-muted">(<?= htmlspecialchars($ar['city']) ?>)</small>
                  </label>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="col-md-6 mb-3">
          <label class="form-label">CNIC</label>
          <input type="text" name="cnic" class="form-control" placeholder="xxxxx-xxxxxxx-x">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Joining Date</label>
          <input type="date" name="joining_date" class="form-control datepicker" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Commission Rate (%) <span class="text-muted small">— aap ki sales par</span></label>
          <input type="number" name="commission_rate" step="0.01" min="0" max="100" class="form-control" placeholder="0.00">
          <small class="text-muted">Salesman ki commission unki sales (invoices) ke hisaab se automatically banegi.</small>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Address</label>
          <input type="text" name="address" class="form-control" placeholder="Full address">
        </div>

        <div class="col-12"><hr>
          <h6 class="text-primary"><i class="fas fa-lock mr-1"></i> Login Account</h6>
          <p class="text-muted small" id="loginHint">Login is required only for <strong>Salesman</strong>.</p>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Login Username <span id="usernameReq" class="text-danger" style="display:none;">*</span></label>
          <input type="text" name="username" class="form-control" id="usernameInput" placeholder="e.g. ali_booker">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Password <span id="passwordReq" class="text-danger" style="display:none;">*</span></label>
          <input type="text" name="password" class="form-control" id="passwordInput" placeholder="Min 4 characters">
        </div>

        <div class="col-12 d-flex justify-content-between mt-2">
          <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
          <button type="submit" class="btn btn-primary px-5"><i class="fas fa-save mr-1"></i> Save Employee</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  var typeSelect    = document.getElementById('empTypeSelect');
  var usernameInput = document.getElementById('usernameInput');
  var passwordInput = document.getElementById('passwordInput');
  var usernameReq   = document.getElementById('usernameReq');
  var passwordReq   = document.getElementById('passwordReq');
  var loginHint     = document.getElementById('loginHint');

  function updateLoginFields() {
    var needsLogin = typeSelect.value === 'salesman';
    usernameInput.required = needsLogin;
    passwordInput.required = needsLogin;
    usernameReq.style.display = needsLogin ? 'inline' : 'none';
    passwordReq.style.display  = needsLogin ? 'inline' : 'none';
    loginHint.innerHTML = needsLogin
      ? 'Login is <strong>required</strong> for Salesman.'
      : 'Login is <strong>not needed</strong> for General employee.';
  }
  typeSelect.addEventListener('change', updateLoginFields);
  updateLoginFields();

  var selAll  = document.getElementById('selectAllAreas');
  var clrAll  = document.getElementById('clearAllAreas');
  if (selAll) selAll.addEventListener('click', function(){ document.querySelectorAll('.area-checkbox').forEach(function(c){ c.checked=true; }); });
  if (clrAll) clrAll.addEventListener('click', function(){ document.querySelectorAll('.area-checkbox').forEach(function(c){ c.checked=false; }); });
})();
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
