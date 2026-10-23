<?php
/**
 * Bestway Distribution - Add Product / Medicine Management
 * Clean & Simple Layout (Pieces / Pcs standard)
 */
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
$page_title = "Add New Product";

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}
requireRole(['admin']);

// Quick AJAX Endpoints for creating Company or Category on the fly
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    if ($_GET['action'] === 'quick_add_company' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $cname = trim($_POST['company_name'] ?? '');
        $ccontact = trim($_POST['contact_person'] ?? '');
        $cphone = trim($_POST['phone'] ?? '');

        if (empty($cname)) {
            echo json_encode(['success' => false, 'message' => 'Company name is required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO companies (name, contact_person, phone, status) VALUES (?, ?, ?, 'Active')");
            $stmt->execute([$cname, $ccontact ?: null, $cphone ?: null]);
            $new_id = $pdo->lastInsertId();
            echo json_encode(['success' => true, 'id' => $new_id, 'name' => $cname]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($_GET['action'] === 'quick_add_category' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $cat_name = trim($_POST['category_name'] ?? '');
        $cat_code = trim($_POST['category_code'] ?? '');

        if (empty($cat_name)) {
            echo json_encode(['success' => false, 'message' => 'Category name is required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name, code, status) VALUES (?, ?, 'Active')");
            $stmt->execute([$cat_name, $cat_code ?: null]);
            $new_id = $pdo->lastInsertId();
            echo json_encode(['success' => true, 'id' => $new_id, 'name' => $cat_name]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
}

$message = "";
$msg_type = "";

// Fetch Dropdown Options
$companies = [];
$categories = [];

if ($db_connected && $pdo) {
    try {
        $companies = $pdo->query("SELECT id, name, code FROM companies WHERE status='Active' ORDER BY name ASC")->fetchAll();
        $categories = $pdo->query("SELECT id, name FROM categories WHERE status='Active' ORDER BY name ASC")->fetchAll();
    } catch (Exception $e) {}
}

// Auto-generate Product Code (e.g. PRD-0001)
$auto_product_code = "PRD-0001";
if ($db_connected && $pdo) {
    try {
        $stmt_seq = $pdo->query("SELECT product_code FROM products WHERE product_code REGEXP '^PRD-[0-9]+$' ORDER BY id DESC LIMIT 1");
        $last_code = $stmt_seq->fetchColumn();
        if ($last_code && preg_match('/PRD-(\d+)/i', $last_code, $matches)) {
            $auto_product_code = "PRD-" . str_pad(((int)$matches[1] + 1), 4, '0', STR_PAD_LEFT);
        } else {
            $stmt_last = $pdo->query("SELECT MAX(id) FROM products");
            $last_id = (int)$stmt_last->fetchColumn();
            $auto_product_code = "PRD-" . str_pad($last_id + 1, 4, '0', STR_PAD_LEFT);
        }
    } catch (Exception $e) {}
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    $product_code     = trim($_POST['product_code'] ?? $auto_product_code);
    $name             = trim($_POST['name'] ?? '');
    $company_id       = !empty($_POST['company_id']) ? intval($_POST['company_id']) : null;
    $category_id      = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $trade_price      = floatval($_POST['trade_price'] ?? 0);
    $discount_percent = floatval($_POST['discount_percent'] ?? 0);
    $retail_price     = ($discount_percent > 0) ? round(max(0, $trade_price * (1 - ($discount_percent / 100))), 2) : $trade_price;
    $wholesale_price  = $retail_price;
    $purchase_price   = $trade_price;
    $reorder_level    = intval($_POST['reorder_level'] ?? 10);
    $location_rack    = trim($_POST['location_rack'] ?? '');
    $status           = in_array($_POST['status'] ?? '', ['Active', 'Inactive']) ? $_POST['status'] : 'Active';

    // Parse Multiple Batches
    $batch_nos            = $_POST['batch_no'] ?? [];
    $batch_expiries       = $_POST['batch_expiry'] ?? [];
    $batch_rates          = $_POST['batch_rate'] ?? [];
    $batch_purchase_rates = $_POST['batch_purchase_rate'] ?? [];
    $batch_stocks         = $_POST['batch_stock'] ?? [];

    $batches_to_insert = [];
    $total_opening_stock = 0;

    if (is_array($batch_nos)) {
        for ($i = 0; $i < count($batch_nos); $i++) {
            $b_num   = trim($batch_nos[$i] ?? '');
            $b_exp   = trim($batch_expiries[$i] ?? '');
            $b_rate  = floatval($batch_rates[$i] ?? 0);
            $b_pur   = floatval($batch_purchase_rates[$i] ?? 0);
            $b_qty   = intval($batch_stocks[$i] ?? 0);

            // Accept batch if number, stock, or rate is provided
            if (!empty($b_num) || $b_qty > 0 || $b_rate > 0) {
                if (empty($b_num)) {
                    $b_num = "BAT-" . date('ymd') . "-" . ($i + 1);
                }
                if (empty($b_exp)) {
                    $b_exp = date('Y-m-d', strtotime('+2 years'));
                }
                if ($b_rate <= 0) {
                    $b_rate = $trade_price;
                }
                if ($b_pur <= 0) {
                    $b_pur = $b_rate;
                }
                $batches_to_insert[] = [
                    'batch_no'       => $b_num,
                    'expiry_date'    => $b_exp,
                    'trade_price'    => $b_rate,
                    'retail_price'   => $b_rate,
                    'purchase_price' => $b_pur,
                    'stock'          => max(0, $b_qty)
                ];
                $total_opening_stock += max(0, $b_qty);
            }
        }
    }

    // Fallback: If no multi-batch rows entered, check if single opening_stock input was sent
    if (empty($batches_to_insert)) {
        $single_stock = intval($_POST['opening_stock'] ?? 0);
        $single_batch = trim($_POST['single_batch_no'] ?? ("BAT-" . date('ymd')));
        $single_exp   = !empty($_POST['single_expiry_date']) ? $_POST['single_expiry_date'] : date('Y-m-d', strtotime('+2 years'));
        $batches_to_insert[] = [
            'batch_no'       => $single_batch,
            'expiry_date'    => $single_exp,
            'trade_price'    => $trade_price,
            'retail_price'   => $trade_price,
            'purchase_price' => $trade_price,
            'stock'          => max(0, $single_stock)
        ];
        $total_opening_stock = max(0, $single_stock);
    }

    $opening_stock = $total_opening_stock;
    $current_stock = $total_opening_stock;

    // If main trade_price was not entered, use the first batch rate as product master TP
    if ($trade_price <= 0 && !empty($batches_to_insert)) {
        $trade_price     = $batches_to_insert[0]['trade_price'];
        $retail_price    = ($discount_percent > 0) ? round(max(0, $trade_price * (1 - ($discount_percent / 100))), 2) : $trade_price;
        $wholesale_price = $retail_price;
        $purchase_price  = $batches_to_insert[0]['purchase_price'];
    }

    // Defaults for legacy unit columns
    $packs_per_box         = 1;
    $tablets_per_pack      = 1;
    $total_tablets_per_box = 1;
    $stock_unit            = 'Pcs';
    $pack_size             = '1 Pcs';

    if (empty($name)) {
        $message = "Product / Medicine name is required.";
        $msg_type = "danger";
    } else {
        if ($db_connected && $pdo) {
            try {
                // Check if product_code already exists
                $chk_code = $pdo->prepare("SELECT id FROM products WHERE product_code = ? LIMIT 1");
                $chk_code->execute([$product_code]);
                if ($chk_code->fetch()) {
                    throw new Exception("Product code '{$product_code}' already exists. Please use a unique code.");
                }

                $pdo->beginTransaction();

                // 1. Insert into products
                $stmt = $pdo->prepare("
                    INSERT INTO products (
                        product_code, barcode, name, generic_name, company_id, category_id,
                        unit_id, pack_size, packs_per_box, tablets_per_pack, total_tablets_per_box, stock_unit,
                        purchase_price, trade_price, retail_price,
                        wholesale_price, discount_percent, max_discount_percent,
                        reorder_level, opening_stock, current_stock, location_rack,
                        requires_prescription, cold_chain, status
                    ) VALUES (
                        ?, NULL, ?, NULL, ?, ?,
                        NULL, ?, ?, ?, ?, ?,
                        ?, ?, ?,
                        ?, ?, ?,
                        ?, ?, ?, ?,
                        0, 0, ?
                    )
                ");

                $stmt->execute([
                    $product_code ?: null,
                    $name,
                    $company_id,
                    $category_id,
                    $pack_size,
                    $packs_per_box,
                    $tablets_per_pack,
                    $total_tablets_per_box,
                    $stock_unit,
                    $purchase_price,
                    $trade_price,
                    $retail_price,
                    $wholesale_price,
                    $discount_percent,
                    $discount_percent,
                    $reorder_level,
                    $opening_stock,
                    $current_stock,
                    $location_rack ?: null,
                    $status
                ]);

                $last_inserted_id = $pdo->lastInsertId();

                // 2. Insert all product batches into product_batches
                $batch_stmt = $pdo->prepare("
                    INSERT INTO product_batches (
                        product_id, batch_no, manufacturing_date, expiry_date,
                        purchase_price, trade_price, retail_price,
                        initial_quantity, current_stock, status
                    ) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, 'Active')
                ");

                $log_stmt = $pdo->prepare("
                    INSERT INTO opening_stock_logs (
                        product_id, batch_no, expiry_date, quantity,
                        purchase_rate, trade_rate, total_value, entry_date, remarks, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), 'Opening stock batch added during product creation', ?)
                ");

                $user_id = $_SESSION['user_id'] ?? 1;

                foreach ($batches_to_insert as $b) {
                    $batch_stmt->execute([
                        $last_inserted_id,
                        $b['batch_no'],
                        $b['expiry_date'],
                        $b['purchase_price'],
                        $b['trade_price'],
                        $b['retail_price'],
                        $b['stock'],
                        $b['stock']
                    ]);

                    // 3. Log to opening_stock_logs if stock > 0
                    if ($b['stock'] > 0) {
                        $total_val = $b['stock'] * ($b['purchase_price'] > 0 ? $b['purchase_price'] : $b['trade_price']);
                        $log_stmt->execute([
                            $last_inserted_id,
                            $b['batch_no'],
                            $b['expiry_date'],
                            $b['stock'],
                            $b['purchase_price'],
                            $b['trade_price'],
                            $total_val,
                            $user_id
                        ]);
                    }
                }

                $pdo->commit();
                $message = "Product '{$name}' successfully added with " . count($batches_to_insert) . " batch(es)! Total Opening Stock: {$opening_stock} Pcs (Code: {$product_code})";
                $msg_type = "success";

                // Re-calculate next product code
                $auto_product_code = "PRD-" . str_pad(((int)$last_inserted_id + 1), 4, '0', STR_PAD_LEFT);

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

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- Alert Feedback -->
<?php if (!empty($message)): ?>
  <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show shadow-sm" role="alert">
    <i class="fas <?= $msg_type === 'success' ? 'fa-check-circle text-success' : 'fa-exclamation-triangle text-danger' ?> mr-2"></i>
    <strong><?= htmlspecialchars($message) ?></strong>
    <?php if ($msg_type === 'success'): ?>
      <a href="view_product_list.php" class="alert-link ml-2">View Product List &rarr;</a>
    <?php endif; ?>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
  </div>
<?php endif; ?>

<div class="card shadow mb-4">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
    <h6 class="mb-0 font-weight-bold text-primary">
      <i class="fas fa-box-open mr-2"></i> Add New Product
    </h6>
    <div>
      <a href="view_product_list.php" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-list mr-1"></i> Product List
      </a>
    </div>
  </div>

  <div class="card-body">
    <form method="POST" action="" id="addProductForm">
      <div class="row">
        <!-- Product Code -->
        <div class="col-md-4 mb-3">
          <label class="form-label font-weight-bold">Product Code *</label>
          <div class="input-group">
            <input type="text" name="product_code" id="productCodeInput" class="form-control font-weight-bold text-success bg-light" value="<?= htmlspecialchars($auto_product_code) ?>" required>
            <div class="input-group-append">
              <button type="button" class="btn btn-outline-secondary" id="btnRefreshCode" title="Regenerate Code">
                <i class="fas fa-sync-alt"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Product Name -->
        <div class="col-md-8 mb-3">
          <label class="form-label font-weight-bold">Product / Medicine Name *</label>
          <input type="text" name="name" class="form-control font-weight-bold" placeholder="e.g. Panadol 500mg, Disprin, Augmentin 625mg" required autofocus>
        </div>

        <!-- Company / Manufacturer -->
        <div class="col-md-6 mb-3">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="form-label font-weight-bold mb-0">Company / Manufacturer</label>
            <button type="button" class="btn btn-sm btn-link p-0 text-primary" data-toggle="modal" data-target="#modalQuickCompany">
              <i class="fas fa-plus-circle mr-1"></i> Quick Add
            </button>
          </div>
          <select name="company_id" id="companySelect" class="form-control">
            <option value="">-- Select Company --</option>
            <?php foreach ($companies as $comp): ?>
              <option value="<?= $comp['id'] ?>">
                <?= htmlspecialchars($comp['name']) ?> <?= !empty($comp['code']) ? "(".htmlspecialchars($comp['code']).")" : "" ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Category -->
        <div class="col-md-6 mb-3">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="form-label font-weight-bold mb-0">Category / Dosage</label>
            <button type="button" class="btn btn-sm btn-link p-0 text-primary" data-toggle="modal" data-target="#modalQuickCategory">
              <i class="fas fa-plus-circle mr-1"></i> Quick Add
            </button>
          </div>
          <select name="category_id" id="categorySelect" class="form-control">
            <option value="">-- Select Category --</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <input type="hidden" name="trade_price" id="trade_price" value="0.00">

        <!-- Sale Discount % -->
        <div class="col-md-3 mb-3">
          <label class="form-label font-weight-bold">Default Sale Discount (%)</label>
          <div class="input-group">
            <input type="number" step="0.01" min="0" max="100" name="discount_percent" id="discount_percent" class="form-control text-right font-weight-bold text-success" placeholder="0.00" value="0.00" onfocus="this.select()">
            <div class="input-group-append">
              <span class="input-group-text font-weight-bold bg-light text-success">%</span>
            </div>
          </div>
          <small class="text-muted">Default customer discount on sale</small>
        </div>

        <!-- Reorder Level -->
        <div class="col-md-3 mb-3">
          <label class="form-label font-weight-bold">Low Stock Alert (Pcs)</label>
          <input type="number" min="0" name="reorder_level" class="form-control text-center" placeholder="10" value="" onfocus="this.select()">
          <small class="text-muted">Alert when stock falls below this</small>
        </div>

        <!-- Shelf / Rack Location -->
        <div class="col-md-3 mb-3">
          <label class="form-label font-weight-bold">Rack / Shelf Location</label>
          <input type="text" name="location_rack" class="form-control" placeholder="e.g. Shelf A-1, Rack 3">
          <small class="text-muted">Optional godown/store location</small>
        </div>

        <!-- Status -->
        <div class="col-md-3 mb-3">
          <label class="form-label font-weight-bold">Status</label>
          <select name="status" class="form-control font-weight-bold">
            <option value="Active" selected>Active</option>
            <option value="Inactive">Inactive</option>
          </select>
        </div>
      </div>

      <!-- Multiple Batches & Opening Stock Section -->
      <div class="card border mb-3 shadow-sm" style="border-radius: 8px;">
        <div class="card-header bg-light py-2 d-flex flex-wrap justify-content-between align-items-center">
          <div>
            <h6 class="m-0 font-weight-bold text-primary">
              <i class="fas fa-layer-group mr-1"></i> Product Batches & Opening Stock (Multiple Batches)
            </h6>
            <small class="text-muted">Type multiple batch numbers manually with their specific sale rates and opening stock</small>
          </div>
          <button type="button" class="btn btn-sm btn-primary font-weight-bold" onclick="addBatchRow()">
            <i class="fas fa-plus mr-1"></i> Add Another Batch
          </button>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0" id="batchesTable">
              <thead class="bg-light text-dark font-weight-bold" style="font-size: 13px;">
                <tr>
                  <th style="width: 45px;" class="text-center">#</th>
                  <th style="min-width: 170px;">Batch Number <span class="text-danger">*</span></th>
                  <th style="min-width: 140px;">Expiry Date <span class="text-danger">*</span></th>
                  <th style="min-width: 150px;" class="text-right">Batch Rate / TP (Rs.) <span class="text-danger">*</span></th>
                  <th style="min-width: 140px;" class="text-right">Purchase Rate (Rs.)</th>
                  <th style="min-width: 130px;" class="text-center">Opening Stock (Pcs)</th>
                  <th style="width: 50px;" class="text-center"></th>
                </tr>
              </thead>
              <tbody id="batchesTbody">
                <!-- Batch rows inserted here via JS -->
              </tbody>
            </table>
          </div>
        </div>
        <div class="card-footer bg-white py-2 d-flex flex-wrap justify-content-between align-items-center">
          <div class="text-muted small">
            <i class="fas fa-info-circle text-info mr-1"></i> Each batch has its own independent rate. In Sales billing, selecting this batch will auto-load this rate (and remain editable).
          </div>
          <div class="d-flex align-items-center">
            <span class="mr-3 font-weight-bold text-dark">
              Total Batches: <span class="badge badge-primary px-2" id="totalBatchesBadge">0</span>
            </span>
            <span class="font-weight-bold text-dark">
              Total Opening Stock: <span class="badge badge-success px-2 font-monospace" id="totalOpeningStockBadge">0 Pcs</span>
            </span>
            <input type="hidden" name="opening_stock" id="totalOpeningStockInput" value="0">
          </div>
        </div>
      </div>

      <hr class="mt-2 mb-3">

      <div class="d-flex justify-content-end align-items-center">
        <a href="view_product_list.php" class="btn btn-secondary mr-2">Cancel</a>
        <button type="submit" name="save_product" class="btn btn-primary px-4 font-weight-bold">
          <i class="fas fa-save mr-1"></i> Save Product
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Quick Add Company -->
<div class="modal fade" id="modalQuickCompany" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <form id="quickCompanyForm">
        <div class="modal-header">
          <h5 class="modal-title font-weight-bold"><i class="fas fa-building text-primary mr-2"></i> Add Company</h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <div id="qcFeedback" class="alert alert-danger d-none py-2 small"></div>
          <div class="mb-3">
            <label class="form-label font-weight-bold">Company Name *</label>
            <input type="text" id="qcName" class="form-control" required placeholder="e.g. GSK, Abbott, Getz Pharma">
          </div>
          <div class="mb-3">
            <label class="form-label font-weight-bold">Contact Person</label>
            <input type="text" id="qcContact" class="form-control" placeholder="Representative name">
          </div>
          <div class="mb-3">
            <label class="form-label font-weight-bold">Phone Number</label>
            <input type="text" id="qcPhone" class="form-control" placeholder="0300-1234567">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary font-weight-bold">Save Company</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Quick Add Category -->
<div class="modal fade" id="modalQuickCategory" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <form id="quickCategoryForm">
        <div class="modal-header">
          <h5 class="modal-title font-weight-bold"><i class="fas fa-tags text-primary mr-2"></i> Add Category</h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <div id="qcatFeedback" class="alert alert-danger d-none py-2 small"></div>
          <div class="mb-3">
            <label class="form-label font-weight-bold">Category Name *</label>
            <input type="text" id="qcatName" class="form-control" required placeholder="e.g. Tablets, Syrup, Injection, Capsules">
          </div>
          <div class="mb-3">
            <label class="form-label font-weight-bold">Short Code</label>
            <input type="text" id="qcatCode" class="form-control" placeholder="e.g. TAB, SYR, INJ">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary font-weight-bold">Save Category</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Code Refresh Handler
    const btnRefreshCode = document.getElementById('btnRefreshCode');
    if (btnRefreshCode) {
        btnRefreshCode.addEventListener('click', function() {
            const randNum = Math.floor(1000 + Math.random() * 9000);
            document.getElementById('productCodeInput').value = 'PRD-' + randNum;
        });
    }

    // Quick Add Company AJAX
    const quickCompanyForm = document.getElementById('quickCompanyForm');
    const qcFeedback       = document.getElementById('qcFeedback');
    const companySelect    = document.getElementById('companySelect');

    if (quickCompanyForm) {
        quickCompanyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            qcFeedback.classList.add('d-none');
            const name = document.getElementById('qcName').value.trim();
            const contact = document.getElementById('qcContact').value.trim();
            const phone = document.getElementById('qcPhone').value.trim();
            if (!name) return;

            const formData = new FormData();
            formData.append('company_name', name);
            formData.append('contact_person', contact);
            formData.append('phone', phone);

            fetch('add_product.php?action=quick_add_company', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const opt = new Option(data.name, data.id, true, true);
                    companySelect.add(opt);
                    $('#modalQuickCompany').modal('hide');
                    quickCompanyForm.reset();
                } else {
                    qcFeedback.textContent = data.message || 'Could not add company.';
                    qcFeedback.classList.remove('d-none');
                }
            })
            .catch(err => {
                qcFeedback.textContent = 'Server communication error.';
                qcFeedback.classList.remove('d-none');
            });
        });
    }

    // Quick Add Category AJAX
    const quickCategoryForm = document.getElementById('quickCategoryForm');
    const qcatFeedback       = document.getElementById('qcatFeedback');
    const categorySelect    = document.getElementById('categorySelect');

    if (quickCategoryForm) {
        quickCategoryForm.addEventListener('submit', function(e) {
            e.preventDefault();
            qcatFeedback.classList.add('d-none');
            const catName = document.getElementById('qcatName').value.trim();
            const catCode = document.getElementById('qcatCode').value.trim();
            if (!catName) return;

            const formData = new FormData();
            formData.append('category_name', catName);
            formData.append('category_code', catCode);

            fetch('add_product.php?action=quick_add_category', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const opt = new Option(data.name, data.id, true, true);
                    categorySelect.add(opt);
                    $('#modalQuickCategory').modal('hide');
                    quickCategoryForm.reset();
                } else {
                    qcatFeedback.textContent = data.message || 'Could not add category.';
                    qcatFeedback.classList.remove('d-none');
                }
            })
            .catch(err => {
                qcatFeedback.textContent = 'Server communication error.';
                qcatFeedback.classList.remove('d-none');
            });
        });
    }

    // Auto-select text on focus so user can immediately overwrite values without backspacing
    document.addEventListener('focus', function(e) {
        if (e.target && e.target.matches('input[type=number]')) {
            e.target.select();
        }
    }, true);

    // Initialize with 1 default batch row
    addBatchRow('BT-<?= date('md') ?>-01', '<?= date('Y-m-d', strtotime('+2 years')) ?>', '', '', '');
});

let batchRowCounter = 0;

function addBatchRow(batchNo = '', expiry = '', rate = '', purRate = '', stock = '') {
    batchRowCounter++;
    const tbody = document.getElementById('batchesTbody');
    if (!tbody) return;

    const defaultExp = expiry || '<?= date('Y-m-d', strtotime('+2 years')) ?>';
    const initialRate = (rate !== '' && rate !== null) ? rate : '';
    const initialPurRate = (purRate !== '' && purRate !== null) ? purRate : initialRate;

    const tr = document.createElement('tr');
    tr.id = 'batchRow_' + batchRowCounter;
    tr.innerHTML = `
        <td class="text-center font-weight-bold text-muted align-middle">
            <span class="batch-index">${batchRowCounter}</span>
        </td>
        <td>
            <input type="text" name="batch_no[]" class="form-control form-control-sm font-monospace font-weight-bold" placeholder="e.g. BT-${String(batchRowCounter).padStart(2, '0')}" value="${escapeHtml(batchNo)}" required>
        </td>
        <td>
            <input type="date" name="batch_expiry[]" class="form-control form-control-sm" value="${defaultExp}" required>
        </td>
        <td>
            <input type="number" step="0.01" min="0" name="batch_rate[]" class="form-control form-control-sm text-right font-weight-bold batch-rate-input text-primary" placeholder="0.00" value="${initialRate}" onfocus="this.select()" oninput="updateBatchTotals()" required>
        </td>
        <td>
            <input type="number" step="0.01" min="0" name="batch_purchase_rate[]" class="form-control form-control-sm text-right batch-pur-input" placeholder="0.00" value="${initialPurRate}" onfocus="this.select()">
        </td>
        <td>
            <input type="number" min="0" name="batch_stock[]" class="form-control form-control-sm text-center font-weight-bold batch-stock-input" placeholder="0" value="${stock}" onfocus="this.select()" oninput="updateBatchTotals()">
        </td>
        <td class="text-center align-middle">
            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="removeBatchRow(${batchRowCounter})" title="Remove batch">
                <i class="fas fa-trash-alt"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    updateBatchIndices();
    updateBatchTotals();
}

function removeBatchRow(rowId) {
    const row = document.getElementById('batchRow_' + rowId);
    const tbody = document.getElementById('batchesTbody');
    if (row && tbody) {
        if (tbody.querySelectorAll('tr').length <= 1) {
            alert('At least one batch row is required.');
            return;
        }
        row.remove();
        updateBatchIndices();
        updateBatchTotals();
    }
}

function updateBatchIndices() {
    const rows = document.querySelectorAll('#batchesTbody tr');
    rows.forEach((r, idx) => {
        const span = r.querySelector('.batch-index');
        if (span) span.textContent = idx + 1;
    });
    const badge = document.getElementById('totalBatchesBadge');
    if (badge) badge.textContent = rows.length;
}

function updateBatchTotals() {
    let totalStock = 0;
    const stockInputs = document.querySelectorAll('.batch-stock-input');
    stockInputs.forEach(inp => {
        totalStock += parseInt(inp.value || 0) || 0;
    });
    const stockBadge = document.getElementById('totalOpeningStockBadge');
    if (stockBadge) stockBadge.textContent = totalStock + ' Pcs';
    const hiddenTotal = document.getElementById('totalOpeningStockInput');
    if (hiddenTotal) hiddenTotal.value = totalStock;

    // Synchronize product master trade_price from the first batch's rate
    const firstRateInput = document.querySelector('#batchesTbody tr:first-child .batch-rate-input');
    const firstRate = firstRateInput ? (parseFloat(firstRateInput.value) || 0) : 0;
    const hiddenTp = document.getElementById('trade_price');
    if (hiddenTp) {
        hiddenTp.value = firstRate > 0 ? firstRate.toFixed(2) : '0.00';
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
