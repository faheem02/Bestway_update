<?php
/**
 * Bestway Distribution - Add Opening Stock & Inventory Inward
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['admin']);
$page_title = "Add Opening Stock & Batches";
require_once __DIR__ . '/../../includes/header.php';

$message = "";
$msg_type = "";
$selected_product_id = intval($_GET['product_id'] ?? 0);

// Handle Opening Stock Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_stock'])) {
    $product_id   = intval($_POST['product_id'] ?? 0);
    $qty          = floatval($_POST['quantity'] ?? 0);
    $stock_unit   = trim($_POST['stock_unit'] ?? 'Pack');
    $batch_no     = trim($_POST['batch_no'] ?? '');
    $expiry_date  = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $purchase_rate= floatval($_POST['purchase_rate'] ?? 0);
    $trade_rate   = floatval($_POST['trade_rate'] ?? 0);
    $remarks      = trim($_POST['remarks'] ?? 'Opening Stock Addition');

    if ($product_id <= 0 || $qty <= 0) {
        $message = "Product aur Quantity dono darj karna lazmi hain.";
        $msg_type = "danger";
    } else {
        if ($db_connected && $pdo) {
            try {
                // Fetch product details for conversion
                $p_stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                $p_stmt->execute([$product_id]);
                $prod = $p_stmt->fetch();

                if (!$prod) {
                    throw new Exception("Product not found in system.");
                }

                $p_box  = max(1, intval($prod['packs_per_box'] ?? 10));
                $t_pack = max(1, intval($prod['tablets_per_pack'] ?? 10));

                // Direct piece quantity addition
                $packs_to_add = $qty;

                if (empty($batch_no)) {
                    $batch_no = "BAT-" . date('ymd-Hi');
                }
                if (empty($expiry_date)) {
                    $expiry_date = date('Y-m-d', strtotime('+2 years'));
                }
                if ($trade_rate <= 0) {
                    $trade_rate = floatval($prod['trade_price'] > 0 ? $prod['trade_price'] : ($prod['purchase_price'] ?? 0));
                }
                $purchase_rate = $trade_rate;
                $total_val = $packs_to_add * $trade_rate;

                $pdo->beginTransaction();

                // 1. Update products stock
                $upd_stmt = $pdo->prepare("
                    UPDATE products 
                    SET current_stock = current_stock + ?,
                        opening_stock = opening_stock + ?
                    WHERE id = ?
                ");
                $upd_stmt->execute([$packs_to_add, $packs_to_add, $product_id]);

                // 2. Insert into product_batches
                $b_stmt = $pdo->prepare("
                    INSERT INTO product_batches (
                        product_id, batch_no, manufacturing_date, expiry_date,
                        purchase_price, trade_price, retail_price,
                        initial_quantity, current_stock, status
                    ) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, 'Active')
                ");
                $b_stmt->execute([
                    $product_id,
                    $batch_no,
                    $expiry_date,
                    $purchase_rate,
                    $trade_rate,
                    $prod['retail_price'] ?? 0,
                    $packs_to_add,
                    $packs_to_add
                ]);

                // 3. Insert into opening_stock_logs
                $log_stmt = $pdo->prepare("
                    INSERT INTO opening_stock_logs (
                        product_id, batch_no, expiry_date, quantity,
                        purchase_rate, trade_rate, total_value, entry_date, remarks, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?)
                ");
                $uid = $_SESSION['user_id'] ?? 1;
                $log_stmt->execute([
                    $product_id,
                    $batch_no,
                    $expiry_date,
                    $packs_to_add,
                    $purchase_rate,
                    $trade_rate,
                    $total_val,
                    $remarks,
                    $uid
                ]);

                $pdo->commit();
                $message = "Opening stock of {$qty} {$stock_unit} added successfully.";
                $msg_type = "success";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $message = "Error: " . $e->getMessage();
                $msg_type = "danger";
            }
        }
    }
}


// Fetch Products & Units for selection
$products = [];
$units = [];
$recent_logs = [];

if ($db_connected && $pdo) {
    try {
        $units = $pdo->query("SELECT id, name, short_name FROM units ORDER BY name ASC")->fetchAll();
        if (empty($units)) {
            $units = [
                ['id' => 1, 'name' => 'Pack', 'short_name' => 'Pac'],
                ['id' => 2, 'name' => 'Box', 'short_name' => 'Box']
            ];
        }

        $products = $pdo->query("
            SELECT id, product_code, name, pack_size, packs_per_box, tablets_per_pack, current_stock, purchase_price, trade_price 
            FROM products 
            WHERE status='Active' 
            ORDER BY name ASC
        ")->fetchAll();

        // Fetch last 15 stock logs
        $recent_logs = $pdo->query("
            SELECT l.*, p.name as product_name, p.product_code, p.packs_per_box, p.tablets_per_pack 
            FROM opening_stock_logs l
            JOIN products p ON l.product_id = p.id
            ORDER BY l.id DESC
            LIMIT 15
        ")->fetchAll();
    } catch (Exception $e) {}
}
?>

<style>
    .page-title-badge {
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, #0d9488 0%, #0284c7 100%);
        color: #ffffff;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        box-shadow: 0 4px 12px rgba(13, 148, 136, 0.25);
    }
    .form-panel-card, .table-panel-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        overflow: hidden;
    }
    .panel-header {
        background: #ffffff;
        border-bottom: 1px solid #f1f5f9;
        padding: 16px 20px;
    }
</style>

<!-- Top Title & Navigation -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <div class="page-title-badge">
            <i class="fa-solid fa-boxes"></i>
        </div>
        <div>
            <h4 class="fw-bold mb-0 text-dark">Add Opening Stock & Batches</h4>
            <p class="text-muted small mb-0">Record physical warehouse inventory, batch numbers, expiry dates & inward costs</p>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="view_product_list.php" class="btn btn-outline-secondary fw-semibold bg-white shadow-sm px-3 py-2 rounded-3">
            <i class="fa-solid fa-list me-1 text-primary"></i> Product Catalog
        </a>
        <a href="add_product.php" class="btn btn-primary fw-bold px-3 py-2 shadow-sm rounded-3">
            <i class="fa-solid fa-plus me-1"></i> Add Product
        </a>
    </div>
</div>

<!-- Alert Notifications -->
<?php if (!empty($message)): ?>
    <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show border-0 shadow-sm rounded-3 d-flex align-items-center mb-4" role="alert">
        <i class="fa-solid <?= $msg_type === 'success' ? 'fa-check-circle text-success' : 'fa-exclamation-triangle text-danger' ?> fs-4 me-3"></i>
        <div class="fw-semibold"><?= htmlspecialchars($message) ?></div>
        <button type="button" class="close ml-auto" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left: Add Stock Form (5 Cols) -->
    <div class="col-lg-5">
        <div class="form-panel-card">
            <div class="panel-header">
                <div class="fw-bold text-dark fs-6">
                    <i class="fa-solid fa-plus-circle text-teal me-1" style="color: #0d9488;"></i> Stock Inward Entry
                </div>
            </div>
            <div class="p-3 p-md-4">
                <form method="POST" action="" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Select Product / Medicine <span class="text-danger">*</span></label>
                        <select name="product_id" id="prodSelect" class="form-select fw-semibold" required>
                            <option value="">-- Choose Product --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>" 
                                        data-pbox="<?= $p['packs_per_box'] ?? 10 ?>" 
                                        data-tpack="<?= $p['tablets_per_pack'] ?? 10 ?>"
                                        data-cost="<?= $p['purchase_price'] ?>"
                                        data-tp="<?= $p['trade_price'] ?>"
                                        data-stock="<?= $p['current_stock'] ?>"
                                        <?= ($selected_product_id === $p['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['product_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please select a product.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Quantity to Add (Pcs) <span class="text-danger">*</span></label>
                        <input type="number" step="1" min="1" name="quantity" id="stockQtyInput" class="form-control text-center fw-bold fs-5 text-primary" value="1" required>
                        <small class="text-muted">Enter quantity directly in Pieces (Pcs)</small>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-dark">Batch Number</label>
                            <input type="text" name="batch_no" class="form-control font-monospace" placeholder="e.g. B-1024" value="BT-<?= date('md') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-dark">Expiry Date</label>
                            <input type="date" name="expiry_date" class="form-control" value="<?= date('Y-m-d', strtotime('+2 years')) ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Trade Price / TP Rate (Rs.)</label>
                        <input type="number" step="0.01" min="0" name="trade_rate" id="tpInput" class="form-control text-end fw-semibold" placeholder="0.00" oninput="if(document.getElementById('costInput')) document.getElementById('costInput').value = this.value;">
                        <input type="hidden" name="purchase_rate" id="costInput" value="0.00">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-dark">Remarks / Source</label>
                        <input type="text" name="remarks" class="form-control" placeholder="e.g. Initial physical godown count">
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" name="save_stock" class="btn btn-primary fw-bold py-2 shadow-sm rounded-3">
                            <i class="fa-solid fa-boxes me-1"></i> Add Stock & Update Inventory
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right: Stock Inward Logs Table (7 Cols) -->
    <div class="col-lg-7">
        <div class="table-panel-card">
            <div class="panel-header d-flex align-items-center justify-content-between">
                <div class="fw-bold text-dark fs-6">
                    <i class="fa-solid fa-history text-primary me-1"></i> Recent Inward Stock Entries
                </div>
                <span class="badge bg-light text-dark border px-2 py-1"><?= count($recent_logs) ?> Recent</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-uppercase text-muted small" style="font-size: 0.76rem;">
                        <tr>
                            <th>Product & Code</th>
                            <th>Batch & Expiry</th>
                            <th class="text-center">Quantity (Packs)</th>
                            <th class="text-end">Cost Rate</th>
                            <th>Date & Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_logs)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-boxes fs-2 text-secondary opacity-50 mb-2 d-block"></i>
                                    <span>No opening stock entry records found.</span>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_logs as $log): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($log['product_name']) ?></div>
                                        <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.7rem;">
                                            <?= htmlspecialchars($log['product_code']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="font-monospace fw-semibold small text-primary"><?= htmlspecialchars($log['batch_no']) ?></div>
                                        <div class="text-muted" style="font-size: 0.72rem;">Exp: <?= date('M Y', strtotime($log['expiry_date'])) ?></div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success-subtle text-success border border-success px-2 py-1 fw-bold">
                                            +<?= $log['quantity'] ?> Packs
                                        </span>
                                    </td>
                                    <td class="text-end fw-semibold text-secondary">
                                        Rs. <?= number_format($log['purchase_rate'], 2) ?>
                                    </td>
                                    <td>
                                        <div class="small fw-medium text-dark"><?= date('d M Y', strtotime($log['entry_date'])) ?></div>
                                        <div class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($log['remarks'] ?? '') ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    const prodSelect    = document.getElementById('prodSelect');
    const specCard      = document.getElementById('prodSpecCard');
    const formulaText   = document.getElementById('dispFormulaText');
    const currStockBadge= document.getElementById('dispCurrStock');
    const costInput     = document.getElementById('costInput');
    const tpInput       = document.getElementById('tpInput');

    function syncProductDetails() {
        const opt = prodSelect.options[prodSelect.selectedIndex];
        if (!opt || !opt.value) {
            specCard.classList.add('d-none');
            return;
        }

        const pbox  = opt.dataset.pbox || 10;
        const tpack = opt.dataset.tpack || 10;
        const cost  = opt.dataset.cost || 0;
        const tp    = opt.dataset.tp || 0;
        const stock = opt.dataset.stock || 0;

        specCard.classList.remove('d-none');
        formulaText.textContent = `1 Box = ${pbox} Packs = ${pbox * tpack} Tablets / Pieces`;
        currStockBadge.textContent = `Current Stock: ${stock} Packs`;

        const tpVal = tp > 0 ? parseFloat(tp).toFixed(2) : (cost > 0 ? parseFloat(cost).toFixed(2) : '');
        if (tpInput) tpInput.value = tpVal;
        if (costInput) costInput.value = tpVal;
    }

    prodSelect.addEventListener('change', syncProductDetails);
    syncProductDetails();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
