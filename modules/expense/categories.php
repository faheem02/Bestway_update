<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
$page_title = 'Expense Categories';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . 'login.php'); exit;
}
requireRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') { redirect('categories.php', 'Category name is required.', 'error'); }
    insert('expense_categories', [
        'name'        => $name,
        'description' => trim($_POST['description'] ?? ''),
        'status'      => 'Active',
    ]);
    logActivity($pdo, 'create', 'expense_category', null, 'Created expense category: ' . $name);
    redirect('categories.php', 'Expense category "' . $name . '" added.');
}

try { $categories = $pdo->query("SELECT * FROM expense_categories ORDER BY name")->fetchAll(); }
catch (Exception $e) { $categories = []; }

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="row mb-3 d-print-none">
  <div class="col-md-8">
    <div class="alert alert-danger alert-dismissible fade show py-2 mb-0" role="alert">
      <i class="fas fa-tags mr-1"></i> <strong>Expense Categories</strong> — Manage the categories used to group expenses.
      <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
  </div>
  <div class="col-md-4 text-md-right">
    <a href="index.php" class="btn btn-outline-secondary shadow-sm"><i class="fas fa-list mr-1"></i>All Expenses</a>
  </div>
</div>

<div class="row">
  <div class="col-lg-8">
    <div class="card shadow">
      <div class="card-header"><h6 class="mb-0"><i class="fas fa-plus-circle text-danger mr-1"></i> Add Expense Category</h6></div>
      <div class="card-body">
        <form method="post" class="row">
          <div class="col-md-5 mb-2">
            <label class="form-label">Category Name *</label>
            <input type="text" name="name" class="form-control" required placeholder="e.g. Rent">
          </div>
          <div class="col-md-5 mb-2">
            <label class="form-label">Description</label>
            <input type="text" name="description" class="form-control" placeholder="Optional">
          </div>
          <div class="col-md-2 mb-2 d-flex align-items-end">
            <button class="btn btn-danger btn-block"><i class="fas fa-plus mr-1"></i> Add</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="card shadow mt-3">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
    <h6 class="mb-0"><i class="fas fa-list mr-1"></i> All Categories (<?= count($categories) ?>)</h6>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-bordered table-hover">
        <thead><tr><th>#</th><th>Name</th><th>Description</th><th>Status</th><th class="text-center">Action</th></tr></thead>
        <tbody>
          <?php if (empty($categories)): ?>
            <tr><td colspan="5" class="text-center text-muted py-3">No expense categories yet. Add your first one above.</td></tr>
          <?php else: $i = 0; foreach ($categories as $c): $i++; ?>
            <tr>
              <td><?= $i ?></td>
              <td class="font-weight-bold"><?= htmlspecialchars($c['name']) ?></td>
              <td><?= htmlspecialchars($c['description'] ?? '-') ?></td>
              <td><?= strtolower((string)$c['status']) === 'active' ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>' ?></td>
              <td class="text-center">
                <form method="post" action="category_delete.php" class="d-inline" onsubmit="return confirm('Delete category "' + <?= json_encode(htmlspecialchars($c['name'])) ?> + '"?');">
                  <input type="hidden" name="id" value="<?= $c['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>