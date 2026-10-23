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

requireRole(['admin']);

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

// Fetch all existing batches
$existing_batches = [];
if ($db_connected && $pdo) {
    try {
        $b_stmt = $pdo->prepare("SELECT * FROM product_batches WHERE product_id = ? ORDER BY id ASC");
        $b_stmt->execute([$product_id]);
        $existing_batches = $b_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $product_code     = trim($_POST['product_code'] ?? $prod['product_code']);
    $name             = trim($_POST['name'] ?? '');
    $company_id       = !empty($_POST['company_id']) ? intval($_POST['company_id']) : null;
    $category_id      = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $trade_price      = floatval($_POST['trade_price'] ?? 0);
    $purchase_price   = (floatval($prod['purchase_price'] ?? 0) > 0) ? floatval($prod['purchase_price']) : $trade_price;
    $discount_percent = floatval($_POST['discount_percent'] ?? 0);
    $entered_wholesale = floatval($_POST['wholesale_price'] ?? 0);
    $calc_sale_price  = ($discount_percent > 0) ? round(max(0, $trade_price * (1 - ($discount_percent / 100))), 2) : $trade_price;
    if ($discount_percent > 0) {
        $wholesale_price = $calc_sale_price;
    } else {
        $wholesale_price = $entered_wholesale > 0 ? $entered_wholesale : $trade_price;
    }
    $retail_price     = $wholesale_price;
    $reorder_level    = intval($_POST['reorder_level'] ?? 10);
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

                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    UPDATE products SET 
                        product_code = ?, name = ?, company_id = ?, category_id = ?,
                        purchase_price = ?, trade_price = ?, retail_price = ?, wholesale_price = ?,
                        discount_percent = ?, max_discount_percent = ?,
                        reorder_level = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $product_code, $name, $company_id, $category_id,
                    $purchase_price, $trade_price, $retail_price, $wholesale_price,
                    $discount_percent, $discount_percent,
                    $reorder_level, $status, $product_id
                ]);

                // Handle Multiple Batches Update & Insert
                $b_ids            = $_POST['batch_id'] ?? [];
                $b_nos            = $_POST['batch_no'] ?? [];
                $b_expiries       = $_POST['batch_expiry'] ?? [];
                $b_rates          = $_POST['batch_rate'] ?? [];
                $b_purchases      = $_POST['batch_purchase_rate'] ?? [];
                $b_stocks         = $_POST['batch_stock'] ?? [];

                $upd_batch_stmt = $pdo->prepare("
                    UPDATE product_batches 
                    SET batch_no = ?, expiry_date = ?, trade_price = ?, retail_price = ?, purchase_price = ?, current_stock = ?, status = ?
                    WHERE id = ? AND product_id = ?
                ");

                $ins_batch_stmt = $pdo->prepare("
                    INSERT INTO product_batches (
                        product_id, batch_no, manufacturing_date, expiry_date,
                        purchase_price, trade_price, retail_price,
                        initial_quantity, current_stock, status
                    ) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, 'Active')
                ");

                $recalculated_total_stock = 0;

                if (is_array($b_nos)) {
                    for ($i = 0; $i < count($b_nos); $i++) {
                        $bid   = intval($b_ids[$i] ?? 0);
                        $bno   = trim($b_nos[$i] ?? '');
                        $bexp  = trim($b_expiries[$i] ?? '');
                        $brate = floatval($b_rates[$i] ?? 0);
                        $bpur  = floatval($b_purchases[$i] ?? 0);
                        $bqty  = intval($b_stocks[$i] ?? 0);

                        if (empty($bno) && $bqty <= 0 && $brate <= 0) continue;

                        if (empty($bno)) {
                            $bno = "BAT-" . date('ymd') . "-" . ($i + 1);
                        }
                        if (empty($bexp)) {
                            $bexp = date('Y-m-d', strtotime('+2 years'));
                        }
                        if ($brate <= 0) $brate = $trade_price;
                        if ($bpur <= 0)  $bpur = $brate;
                        $bstatus = ($bqty > 0) ? 'Active' : 'Exhausted';

                        if ($bid > 0) {
                            $upd_batch_stmt->execute([
                                $bno, $bexp, $brate, $brate, $bpur, max(0, $bqty), $bstatus, $bid, $product_id
                            ]);
                            $recalculated_total_stock += max(0, $bqty);
                        } else {
                            $ins_batch_stmt->execute([
                                $product_id, $bno, $bexp, $bpur, $brate, $brate, max(0, $bqty), max(0, $bqty)
                            ]);
                            $recalculated_total_stock += max(0, $bqty);
                        }
                    }
                }

                // If batches were submitted, update products.current_stock to match
                if (!empty($b_nos)) {
                    $pdo->prepare("UPDATE products SET current_stock = ? WHERE id = ?")->execute([$recalculated_total_stock, $product_id]);
                }

                $pdo->commit();
                $message = "Product '{$name}' and its batches updated successfully!";
                $msg_type = "success";

                // Re-fetch updated data
                $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $prod = $stmt->fetch();

                // Re-fetch all batches
                $b_stmt = $pdo->prepare("SELECT * FROM product_batches WHERE product_id = ? ORDER BY id ASC");
                $b_stmt->execute([$product_id]);
                $existing_batches = $b_stmt->fetchAll(PDO::FETCH_ASSOC);

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
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

        <input type="hidden" name="trade_price" id="trade_price" value="<?= htmlspecialchars($prod['trade_price']) ?>">
        <input type="hidden" name="wholesale_price" id="wholesale_price" value="<?= htmlspecialchars($prod['wholesale_price'] > 0 ? $prod['wholesale_price'] : ($prod['retail_price'] > 0 ? $prod['retail_price'] : $prod['trade_price'])) ?>">

        <!-- Sale Discount % -->
        <div class="col-md-3 mb-3">
          <label class="form-label font-weight-bold">Default Sale Discount (%)</label>
          <div class="input-group">
            <input type="number" step="0.01" min="0" max="100" name="discount_percent" id="discount_percent" class="form-control text-right font-weight-bold text-success" value="<?= htmlspecialchars($prod['discount_percent'] ?? '0.00') ?>" onfocus="this.select()">
            <div class="input-group-append">
              <span class="input-group-text font-weight-bold bg-light text-success">%</span>
            </div>
          </div>
          <small class="text-muted">Default customer discount on TP</small>
        </div>

        <!-- Current Stock (Total across all batches) -->
        <div class="col-md-3 mb-3">
          <label class="form-label font-weight-bold">Total Stock (Pcs)</label>
          <input type="text" id="dispTotalStock" class="form-control text-center font-weight-bold bg-light" value="<?= htmlspecialchars($prod['current_stock']) ?> Pcs" readonly>
          <small class="text-muted">Calculated as sum of all batch stocks</small>
        </div>

        <!-- Reorder Level -->
        <div class="col-md-3 mb-3">
          <label class="form-label font-weight-bold">Low Stock Alert (Pcs)</label>
          <input type="number" min="0" name="reorder_level" class="form-control text-center" value="<?= htmlspecialchars($prod['reorder_level'] ?? 10) ?>">
          <small class="text-muted">Alert when stock falls below this</small>
        </div>

        <!-- Status -->
        <div class="col-md-3 mb-3">
          <label class="form-label font-weight-bold">Status</label>
          <select name="status" class="form-control">
            <option value="Active" <?= ($prod['status'] === 'Active') ? 'selected' : '' ?>>Active</option>
            <option value="Inactive" <?= ($prod['status'] === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>
      </div>

      <!-- Multiple Batches & Stock Management Section -->
      <div class="card border mb-3 shadow-sm" style="border-radius: 8px;">
        <div class="card-header bg-light py-2 d-flex flex-wrap justify-content-between align-items-center">
          <div>
            <h6 class="m-0 font-weight-bold text-primary">
              <i class="fas fa-layer-group mr-1"></i> Product Batches & Stock Management
            </h6>
            <small class="text-muted">Manage multiple batches with their individual rates and stocks</small>
          </div>
          <button type="button" class="btn btn-sm btn-primary font-weight-bold" onclick="addEditBatchRow()">
            <i class="fas fa-plus mr-1"></i> Add Another Batch
          </button>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0" id="editBatchesTable">
              <thead class="bg-light text-dark font-weight-bold" style="font-size: 13px;">
                <tr>
                  <th style="width: 45px;" class="text-center">#</th>
                  <th style="min-width: 170px;">Batch Number <span class="text-danger">*</span></th>
                  <th style="min-width: 140px;">Expiry Date <span class="text-danger">*</span></th>
                  <th style="min-width: 150px;" class="text-right">Batch Rate / TP (Rs.) <span class="text-danger">*</span></th>
                  <th style="min-width: 140px;" class="text-right">Purchase Rate (Rs.)</th>
                  <th style="min-width: 130px;" class="text-center">Current Stock (Pcs)</th>
                  <th style="width: 50px;" class="text-center"></th>
                </tr>
              </thead>
              <tbody id="editBatchesTbody">
                <!-- Batch rows populated via JS -->
              </tbody>
            </table>
          </div>
        </div>
        <div class="card-footer bg-white py-2 d-flex flex-wrap justify-content-between align-items-center">
          <div class="text-muted small">
            <i class="fas fa-info-circle text-info mr-1"></i> Each batch has its own independent rate. In sales invoices, selecting a batch loads that specific rate.
          </div>
          <div class="d-flex align-items-center">
            <span class="mr-3 font-weight-bold text-dark">
              Total Batches: <span class="badge badge-primary px-2" id="editTotalBatchesBadge"><?= count($existing_batches) ?></span>
            </span>
            <span class="font-weight-bold text-dark">
              Total Stock: <span class="badge badge-success px-2 font-monospace" id="editTotalStockBadge"><?= intval($prod['current_stock']) ?> Pcs</span>
            </span>
          </div>
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

const existingBatchesData = <?= json_encode($existing_batches) ?>;
let editBatchCounter = 0;

function addEditBatchRow(b = null) {
    editBatchCounter++;
    const tbody = document.getElementById('editBatchesTbody');
    if (!tbody) return;

    const mainTp = parseFloat(document.getElementById('trade_price')?.value) || 0;
    const batchId = b ? (b.id || 0) : 0;
    const batchNo = b ? (b.batch_no || '') : ('BT-' + String(editBatchCounter).padStart(2, '0'));
    const expiry = (b && b.expiry_date && b.expiry_date !== '0000-00-00') ? b.expiry_date : '<?= date('Y-m-d', strtotime('+2 years')) ?>';
    const rate = b ? (parseFloat(b.trade_price) > 0 ? parseFloat(b.trade_price).toFixed(2) : (mainTp > 0 ? mainTp.toFixed(2) : '0.00')) : (mainTp > 0 ? mainTp.toFixed(2) : '0.00');
    const purRate = b ? (parseFloat(b.purchase_price) > 0 ? parseFloat(b.purchase_price).toFixed(2) : rate) : rate;
    const stock = b ? parseInt(b.current_stock || 0) : 0;

    const tr = document.createElement('tr');
    tr.id = 'editBatchRow_' + editBatchCounter;
    tr.innerHTML = `
        <td class="text-center font-weight-bold text-muted align-middle">
            <span class="batch-index">${editBatchCounter}</span>
            <input type="hidden" name="batch_id[]" value="${batchId}">
        </td>
        <td>
            <input type="text" name="batch_no[]" class="form-control form-control-sm font-monospace font-weight-bold" placeholder="e.g. BT-01" value="${escapeHtml(batchNo)}" required>
        </td>
        <td>
            <input type="date" name="batch_expiry[]" class="form-control form-control-sm" value="${expiry}" required>
        </td>
        <td>
            <input type="number" step="0.01" min="0" name="batch_rate[]" class="form-control form-control-sm text-right font-weight-bold batch-rate-input text-primary" placeholder="0.00" value="${rate}" onfocus="this.select()" oninput="updateEditBatchTotals()" required>
        </td>
        <td>
            <input type="number" step="0.01" min="0" name="batch_purchase_rate[]" class="form-control form-control-sm text-right batch-pur-input" placeholder="0.00" value="${purRate}" onfocus="this.select()">
        </td>
        <td>
            <input type="number" min="0" name="batch_stock[]" class="form-control form-control-sm text-center font-weight-bold batch-stock-input" placeholder="0" value="${stock}" onfocus="this.select()" oninput="updateEditBatchTotals()">
        </td>
        <td class="text-center align-middle">
            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="removeEditBatchRow(${editBatchCounter}, ${batchId})" title="Remove batch">
                <i class="fas fa-trash-alt"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    updateEditBatchIndices();
    updateEditBatchTotals();
}

function removeEditBatchRow(rowId, batchId) {
    const row = document.getElementById('editBatchRow_' + rowId);
    const tbody = document.getElementById('editBatchesTbody');
    if (row && tbody) {
        if (tbody.querySelectorAll('tr').length <= 1) {
            alert('At least one batch row is required.');
            return;
        }
        if (batchId > 0) {
            if (!confirm('This batch exists in the database. Are you sure you want to remove it from this list? (Its remaining stock will be removed upon saving)')) {
                return;
            }
        }
        row.remove();
        updateEditBatchIndices();
        updateEditBatchTotals();
    }
}

function updateEditBatchIndices() {
    const rows = document.querySelectorAll('#editBatchesTbody tr');
    rows.forEach((r, idx) => {
        const span = r.querySelector('.batch-index');
        if (span) span.textContent = idx + 1;
    });
    const badge = document.getElementById('editTotalBatchesBadge');
    if (badge) badge.textContent = rows.length;
}

function updateEditBatchTotals() {
    let totalStock = 0;
    const stockInputs = document.querySelectorAll('.batch-stock-input');
    stockInputs.forEach(inp => {
        totalStock += parseInt(inp.value || 0) || 0;
    });
    const stockBadge = document.getElementById('editTotalStockBadge');
    if (stockBadge) stockBadge.textContent = totalStock + ' Pcs';
    const dispStock = document.getElementById('dispTotalStock');
    if (dispStock) dispStock.value = totalStock + ' Pcs';

    // Synchronize product master trade_price from the first batch's rate
    const firstRateInput = document.querySelector('#editBatchesTbody tr:first-child .batch-rate-input');
    const firstRate = firstRateInput ? (parseFloat(firstRateInput.value) || 0) : 0;
    const hiddenTp = document.getElementById('trade_price');
    const hiddenWholesale = document.getElementById('wholesale_price');
    if (hiddenTp && firstRate > 0) {
        hiddenTp.value = firstRate.toFixed(2);
        if (hiddenWholesale) hiddenWholesale.value = firstRate.toFixed(2);
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

document.addEventListener('DOMContentLoaded', function() {
    if (existingBatchesData && existingBatchesData.length > 0) {
        existingBatchesData.forEach(b => addEditBatchRow(b));
    } else {
        addEditBatchRow(null);
    }
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
