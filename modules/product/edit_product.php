<?php
/**
 * Bestway Distribution - Edit Product / Medicine Details
 * Clean & Simple Layout (Pieces / Pcs standard)
 */
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
$page_title = "Edit Product";

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$message = "";
$msg_type = "";
$product_id = intval($_GET['id'] ?? 0);

if ($product_id <= 0) {
    header("Location: view_product_list.php");
    exit;
}

// Fetch Existing Product Data
$prod = null;
if ($db_connected && $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $prod = $stmt->fetch();
}

if (!$prod) {
    header("Location: view_product_list.php");
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $product_code     = trim($_POST['product_code'] ?? $prod['product_code']);
    $name             = trim($_POST['name'] ?? '');
    $company_id       = !empty($_POST['company_id']) ? intval($_POST['company_id']) : null;
    $category_id      = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $purchase_price   = floatval($_POST['purchase_price'] ?? 0);
    $trade_price      = floatval($_POST['trade_price'] ?? 0);
    $wholesale_price  = floatval($_POST['wholesale_price'] ?? 0);
    if ($wholesale_price <= 0 && $trade_price > 0) {
        $wholesale_price = $trade_price;
    }
    $retail_price     = floatval($_POST['retail_price'] ?? $trade_price);
    $reorder_level    = intval($_POST['reorder_level'] ?? 10);
    $location_rack    = trim($_POST['location_rack'] ?? '');
    $status           = in_array($_POST['status'] ?? '', ['Active', 'Inactive']) ? $_POST['status'] : 'Active';

    if (empty($name)) {
        $message = "Product name is required.";
        $msg_type = "danger";
    } else {
        if ($db_connected && $pdo) {
            try {
                // Check if product_code already exists for another product
                $chk_code = $pdo->prepare("SELECT id FROM products WHERE product_code = ? AND id != ? LIMIT 1");
                $chk_code->execute([$product_code, $product_id]);
                if ($chk_code->fetch()) {
                    throw new Exception("Product code '{$product_code}' already exists for another product.");
                }

                $stmt = $pdo->prepare("
                    UPDATE products SET 
                        product_code = ?, name = ?, company_id = ?, category_id = ?,
                        purchase_price = ?, trade_price = ?, retail_price = ?, wholesale_price = ?,
                        reorder_level = ?, location_rack = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $product_code, $name, $company_id, $category_id,
                    $purchase_price, $trade_price, $retail_price, $wholesale_price,
                    $reorder_level, $location_rack ?: null, $status, $product_id
                ]);

                $message = "Product '{$name}' updated successfully!";
                $msg_type = "success";

                // Re-fetch updated data
                $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $prod = $stmt->fetch();

            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $msg_type = "danger";
            }
        }
    }
}

// Dropdown options
$companies  = [];
$categories = [];
if ($db_connected && $pdo) {
    try {
        $companies  = $pdo->query("SELECT id, name, code FROM companies WHERE status='Active' ORDER BY name ASC")->fetchAll();
        $categories = $pdo->query("SELECT id, name FROM categories WHERE status='Active' ORDER BY name ASC")->fetchAll();
    } catch (Exception $e) {}
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- Alert Feedback -->
<?php if (!empty($message)): ?>
  <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show shadow-sm" role="alert">
    <i class="fas <?= $msg_type === 'success' ? 'fa-check-circle text-success' : 'fa-exclamation-triangle text-danger' ?> mr-2"></i>
    <strong><?= htmlspecialchars($message) ?></strong>
    <a href="view_product_list.php" class="alert-link ml-2">Back to Catalog &rarr;</a>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
  </div>
<?php endif; ?>

<div class="card shadow mb-4">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
    <h6 class="mb-0 font-weight-bold text-primary">
      <i class="fas fa-edit mr-2"></i> Edit Product: <?= htmlspecialchars($prod['name']) ?>
    </h6>
    <div>
      <a href="view_product_list.php" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-list mr-1"></i> Product List
      </a>
      <a href="add_product.php" class="btn btn-sm btn-success ml-1">
        <i class="fas fa-plus mr-1"></i> Add New Product
      </a>
    </div>
  </div>

  <div class="card-body">
    <form method="POST" action="">
      <div class="row">
        <!-- Product Code -->
        <div class="col-md-4 mb-3">
          <label class="form-label font-weight-bold">Product Code *</label>
          <input type="text" name="product_code" class="form-control font-weight-bold text-success bg-light" value="<?= htmlspecialchars($prod['product_code']) ?>" required>
        </div>

        <!-- Product Name -->
        <div class="col-md-8 mb-3">
          <label class="form-label font-weight-bold">Product / Medicine Name *</label>
          <input type="text" name="name" class="form-control font-weight-bold" value="<?= htmlspecialchars($prod['name']) ?>" required>
        </div>

        <!-- Company / Manufacturer -->
        <div class="col-md-6 mb-3">
          <label class="form-label font-weight-bold">Company / Manufacturer</label>
          <select name="company_id" class="form-control">
            <option value="">-- Select Company --</option>
            <?php foreach ($companies as $c): ?>
              <option value="<?= $c['id'] ?>" <?= ($prod['company_id'] == $c['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Category -->
        <div class="col-md-6 mb-3">
          <label class="form-label font-weight-bold">Category / Dosage</label>
          <select name="category_id" class="form-control">
            <option value="">-- Select Category --</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>" <?= ($prod['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Trade Price / TP (First) -->
        <div class="col-md-4 mb-3">
          <label class="form-label font-weight-bold">Trade Price / TP Rate (Rs.) <span class="text-danger">*</span></label>
          <input type="number" step="0.01" min="0" name="trade_price" class="form-control text-right font-weight-bold text-primary" value="<?= htmlspecialchars($prod['trade_price']) ?>" required>
          <small class="text-muted">Company official TP rate</small>
        </div>

        <!-- Purchase Rate / Cost (Second) -->
        <div class="col-md-4 mb-3">
          <label class="form-label font-weight-bold">Purchase Rate / Cost (Rs.)</label>
          <input type="number" step="0.01" min="0" name="purchase_price" class="form-control text-right font-weight-bold text-dark" value="<?= htmlspecialchars($prod['purchase_price']) ?>">
          <small class="text-muted">Cost price from supplier</small>
        </div>

        <!-- Sale Rate (Third) -->
        <div class="col-md-4 mb-3">
          <label class="form-label font-weight-bold">Sale Rate (Rs.)</label>
          <input type="number" step="0.01" min="0" name="wholesale_price" class="form-control text-right font-weight-bold text-success" value="<?= htmlspecialchars($prod['wholesale_price'] > 0 ? $prod['wholesale_price'] : $prod['trade_price']) ?>">
          <small class="text-muted">Store selling rate to customers</small>
        </div>

        <!-- Current Stock (Display Only) -->
        <div class="col-md-4 mb-3">
          <label class="form-label font-weight-bold">Current Stock (Pcs)</label>
          <input type="text" class="form-control text-center font-weight-bold bg-light" value="<?= htmlspecialchars($prod['current_stock']) ?> Pcs" readonly>
          <small class="text-muted">Managed via Purchases & Sales</small>
        </div>

        <!-- Reorder Level -->
        <div class="col-md-4 mb-3">
          <label class="form-label font-weight-bold">Low Stock Alert (Pcs)</label>
          <input type="number" min="0" name="reorder_level" class="form-control text-center" value="<?= htmlspecialchars($prod['reorder_level'] ?? 10) ?>">
          <small class="text-muted">Alert when stock falls below this</small>
        </div>

        <!-- Shelf / Rack Location -->
        <div class="col-md-4 mb-3">
          <label class="form-label font-weight-bold">Rack / Shelf Location</label>
          <input type="text" name="location_rack" class="form-control" value="<?= htmlspecialchars($prod['location_rack'] ?? '') ?>" placeholder="e.g. Shelf A-1, Rack 3">
          <small class="text-muted">Optional godown/store location</small>
        </div>

        <!-- Status -->
        <div class="col-md-4 mb-3">
          <label class="form-label font-weight-bold">Status</label>
          <select name="status" class="form-control">
            <option value="Active" <?= ($prod['status'] === 'Active') ? 'selected' : '' ?>>Active</option>
            <option value="Inactive" <?= ($prod['status'] === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>
      </div>

      <hr class="mt-2 mb-3">

      <div class="d-flex justify-content-end align-items-center">
        <a href="view_product_list.php" class="btn btn-secondary mr-2">Cancel</a>
        <button type="submit" name="update_product" class="btn btn-primary px-4 font-weight-bold">
          <i class="fas fa-save mr-1"></i> Update Product
        </button>
      </div>
    </form>
  </div>
<script>
document.addEventListener('focus', function(e) {
    if (e.target && e.target.matches('input[type=number]')) {
        e.target.select();
    }
}, true);
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
