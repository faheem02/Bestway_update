<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
$page_title = 'Edit Employee';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . 'login.php'); exit;
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT e.*, u.username AS login_username FROM employees e LEFT JOIN users u ON e.user_id = u.id WHERE e.id = ? LIMIT 1");
$stmt->execute([$id]);
$emp = $stmt->fetch();
if (!$emp) { redirect('index.php', 'Employee not found.', 'error'); }

try { $all_areas = $pdo->query("SELECT id, name, city FROM areas WHERE status = 1 ORDER BY name ASC")->fetchAll(); }
catch (Exception $e) { $all_areas = []; }

$emp_areas = array_map('trim', explode(',', $emp['area'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name     = trim($_POST['full_name'] ?? '');
    $employee_type = $_POST['employee_type'] ?? 'salesman';
    if (!in_array($employee_type, ['salesman', 'general'], true)) $employee_type = 'salesman';
    $phone         = trim($_POST['phone'] ?? '');
    $selected_areas = (array)($_POST['areas'] ?? []);
    $area          = implode(', ', array_filter(array_map('trim', $selected_areas)));
    $cnic          = trim($_POST['cnic'] ?? '');
    $address       = trim($_POST['address'] ?? '');
    $joining_date  = $_POST['joining_date'] ?: null;
    $commission    = (float)($_POST['commission_rate'] ?? 0);
    if ($commission < 0) $commission = 0;
    if ($commission > 100) $commission = 100;
    $status        = (int)($_POST['status'] ?? 1);

    if ($full_name === '') { redirect('edit.php?id=' . $id, 'Employee name is required.', 'error'); }

    // Salesman needs a login account. Create one if missing and credentials provided.
    $username = trim($_POST['login_username'] ?? '');
    $password = $_POST['login_password'] ?? '';
    if ($employee_type === 'salesman' && empty($emp['user_id'])) {
        if ($username !== '' && $password !== '') {
            if (strlen($password) < 4) { redirect('edit.php?id=' . $id, 'Password must be at least 4 characters.', 'error'); }
            $chk2 = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $chk2->execute([$username]);
            if ($chk2->fetch()) { redirect('edit.php?id=' . $id, 'Username "' . $username . '" already exists.', 'error'); }
            $uid = insert('users', [
                'username'   => $username,
                'password'   => $password,
                'full_name'  => $full_name,
                'phone'      => $phone,
                'role'       => 'salesman',
                'status'     => 'Active',
                'created_at' => date('Y-m-d'),
            ]);
            $pdo->prepare("UPDATE employees SET user_id = ? WHERE id = ?")->execute([$uid, $id]);
        }
    } elseif ($employee_type === 'salesman' && !empty($emp['user_id'])) {
        $user_status = ($status == 1) ? 'Active' : 'Inactive';
        $pdo->prepare("UPDATE users SET role = 'salesman', status = ? WHERE id = ?")->execute([$user_status, $emp['user_id']]);
    }
    if (!empty($emp['user_id'])) {
        $user_status = ($status == 1) ? 'Active' : 'Inactive';
        $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$user_status, $emp['user_id']]);
    }

    update('employees', [
        'full_name'     => $full_name,
        'employee_type' => $employee_type,
        'phone'         => $phone,
        'area'          => $area,
        'cnic'          => $cnic,
        'address'       => $address,
        'joining_date'  => $joining_date,
        'salary'        => 0,
        'commission_rate' => $commission,
        'status'        => $status,
        'updated_at'    => date('Y-m-d'),
    ], $id);

    redirect('index.php', 'Employee "' . $full_name . '" updated successfully.');
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="card shadow">
  <div class="card-header">
    <h6 class="mb-0"><i class="fas fa-user-edit mr-1"></i> Edit Employee</h6>
  </div>
  <div class="card-body">
    <form method="post">
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Employee ID</label>
          <input type="text" class="form-control bg-light font-weight-bold text-success" value="<?= htmlspecialchars($emp['emp_code'] ?? '-') ?>" readonly>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Employee Name *</label>
          <input type="text" name="full_name" class="form-control" required value="<?= htmlspecialchars($emp['full_name']) ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Employee Type *</label>
          <select name="employee_type" class="form-control" required>
            <option value="salesman" <?= $emp['employee_type']=='salesman' ? 'selected':'' ?>>Salesman</option>
            <option value="general" <?= $emp['employee_type']=='general' ? 'selected':'' ?>>General</option>
          </select>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Phone</label>
          <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($emp['phone'] ?? '') ?>">
        </div>

        <div class="col-md-12 mb-3">
          <label class="form-label font-weight-bold">Assigned Areas</label>
          <div class="border rounded p-3 bg-light">
            <?php if (empty($all_areas)): ?>
              <p class="text-muted small mb-0">No areas added yet.</p>
            <?php else: ?>
            <div class="row">
              <?php foreach ($all_areas as $ar): ?>
              <div class="col-md-3 col-sm-6 mb-2">
                <div class="custom-control custom-checkbox">
                  <?php $checked = in_array($ar['name'], $emp_areas) ? 'checked' : ''; ?>
                  <input type="checkbox" name="areas[]" value="<?= htmlspecialchars($ar['name']) ?>" class="custom-control-input" id="area_e_<?= $ar['id'] ?>" <?= $checked ?>>
                  <label class="custom-control-label" for="area_e_<?= $ar['id'] ?>">
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
          <input type="text" name="cnic" class="form-control" value="<?= htmlspecialchars($emp['cnic'] ?? '') ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Joining Date</label>
          <input type="date" name="joining_date" class="form-control datepicker" value="<?= htmlspecialchars($emp['joining_date'] ?? '') ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Commission Rate (%) <span class="text-muted small">— sales par</span></label>
          <input type="number" name="commission_rate" step="0.01" min="0" max="100" class="form-control" value="<?= htmlspecialchars($emp['commission_rate'] ?? '0') ?>">
          <small class="text-muted">Salesman ki commission unki sales ke hisaab se automatically banegi.</small>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Address</label>
          <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($emp['address'] ?? '') ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <option value="1" <?= $emp['status'] ? 'selected' : '' ?>>Active</option>
            <option value="0" <?= !$emp['status'] ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>

        <div class="col-12"><hr>
          <h6 class="text-primary"><i class="fas fa-lock mr-1"></i> Login Account</h6>
          <?php if (!empty($emp['user_id']) && !empty($emp['login_username'])): ?>
            <p class="text-muted small mb-0">
              Login: <code><?= htmlspecialchars($emp['login_username']) ?></code>
              <span class="text-success"><i class="fas fa-check-circle"></i> Already linked to this salesman.</span>
            </p>
          <?php else: ?>
            <p class="text-muted small" id="loginHint">
              Salesman ke liye login account banayein (username + password dein). General employee ko login ki zaroorat nahi.
            </p>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Login Username</label>
                <input type="text" name="login_username" class="form-control" placeholder="e.g. ali_salesman" autocomplete="off">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Password <span class="text-muted small">(min 4 chars)</span></label>
                <input type="text" name="login_password" class="form-control" placeholder="……">
              </div>
            </div>
          <?php endif; ?>
        </div>

        <div class="col-12 d-flex justify-content-between mt-2">
          <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
          <button type="submit" class="btn btn-primary px-5"><i class="fas fa-save mr-1"></i> Update Employee</button>
        </div>
      </div>
    </form>
  </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
