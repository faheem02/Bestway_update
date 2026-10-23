<?php
/**
 * Bestway Distribution - Product Batches Management
 * Displays all assigned batches, stocks, prices, and suppliers for a specific product.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

requireRole(['admin', 'salesman', 'operator']);

// Get Product Identifier (by product_id, id, or product_name)
$product_id = intval($_GET['product_id'] ?? ($_GET['id'] ?? 0));
$product_name_query = trim($_GET['product_name'] ?? '');

$product = null;

if ($db_connected && $pdo) {
    if ($product_id > 0) {
        $p_stmt = $pdo->prepare("
            SELECT p.*, 
                   c.name as company_name, 
                   cat.name as category_name, 
                   u.name as unit_name,
                   u.short_name as unit_short
            FROM products p
            LEFT JOIN companies c ON p.company_id = c.id
            LEFT JOIN categories cat ON p.category_id = cat.id
            LEFT JOIN units u ON p.unit_id = u.id
            WHERE p.id = ?
        ");
        $p_stmt->execute([$product_id]);
        $product = $p_stmt->fetch(PDO::FETCH_ASSOC);
    } elseif (!empty($product_name_query)) {
        $p_stmt = $pdo->prepare("
            SELECT p.*, 
                   c.name as company_name, 
                   cat.name as category_name, 
                   u.name as unit_name,
                   u.short_name as unit_short
            FROM products p
            LEFT JOIN companies c ON p.company_id = c.id
            LEFT JOIN categories cat ON p.category_id = cat.id
            LEFT JOIN units u ON p.unit_id = u.id
            WHERE p.name = ? OR p.name LIKE ?
            ORDER BY p.id DESC LIMIT 1
        ");
        $p_stmt->execute([$product_name_query, '%' . $product_name_query . '%']);
        $product = $p_stmt->fetch(PDO::FETCH_ASSOC);
        if ($product) {
            $product_id = intval($product['id']);
        }
    }
}

if (!$product) {
    $page_title = "Product Batches - Not Found";
    require_once __DIR__ . '/../../includes/header.php';
    echo "
    <div class='container-fluid py-5'>
        <div class='alert alert-warning shadow-sm border-0 rounded-3 text-center py-5'>
            <i class='fas fa-exclamation-triangle fa-3x text-warning mb-3 d-block'></i>
            <h4 class='fw-bold text-dark'>Product Not Found</h4>
            <p class='text-muted mb-4'>The requested product could not be located in the catalog.</p>
            <a href='view_product_list.php' class='btn btn-primary fw-bold px-4'>
                <i class='fas fa-arrow-left mr-1'></i> Back to Products List
            </a>
        </div>
    </div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$feedback = '';
$feedback_type = 'success';

// Handle POST: Add or Edit Batch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_batch') {
        $batch_no = trim($_POST['batch_no'] ?? '');
        $expiry_date = trim($_POST['expiry_date'] ?? '');
        $sale_price = floatval($_POST['sale_price'] ?? 0);
        $purchase_price = floatval($_POST['purchase_price'] ?? 0);
        $stock = intval($_POST['quantity'] ?? 0);

        if (empty($batch_no)) {
            $feedback = "Batch Number is required.";
            $feedback_type = "danger";
        } else {
            try {
                $ins_b = $pdo->prepare("
                    INSERT INTO product_batches 
                    (product_id, batch_no, expiry_date, purchase_price, trade_price, retail_price, initial_quantity, current_stock, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active')
                ");
                $ins_b->execute([$product_id, $batch_no, !empty($expiry_date) ? $expiry_date : '2028-12-31', $purchase_price, $sale_price, $sale_price, $stock, $stock]);

                // Sync total stock on product
                $sync_stmt = $pdo->prepare("UPDATE products SET current_stock = (SELECT COALESCE(SUM(current_stock), 0) FROM product_batches WHERE product_id = ?) WHERE id = ?");
                $sync_stmt->execute([$product_id, $product_id]);

                $feedback = "Batch [{$batch_no}] added successfully!";
                $feedback_type = "success";
            } catch (Exception $e) {
                $feedback = "Error adding batch: " . $e->getMessage();
                $feedback_type = "danger";
            }
        }
    } elseif ($action === 'edit_batch') {
        $batch_id = intval($_POST['batch_id'] ?? 0);
        $batch_no = trim($_POST['batch_no'] ?? '');
        $expiry_date = trim($_POST['expiry_date'] ?? '');
        $sale_price = floatval($_POST['sale_price'] ?? 0);
        $purchase_price = floatval($_POST['purchase_price'] ?? 0);
        $stock = intval($_POST['quantity'] ?? 0);

        if ($batch_id > 0 && !empty($batch_no)) {
            try {
                $upd_b = $pdo->prepare("
                    UPDATE product_batches 
                    SET batch_no = ?, expiry_date = ?, purchase_price = ?, trade_price = ?, retail_price = ?, current_stock = ?
                    WHERE id = ? AND product_id = ?
                ");
                $upd_b->execute([$batch_no, !empty($expiry_date) ? $expiry_date : '2028-12-31', $purchase_price, $sale_price, $sale_price, $stock, $batch_id, $product_id]);

                // Sync total stock on product
                $sync_stmt = $pdo->prepare("UPDATE products SET current_stock = (SELECT COALESCE(SUM(current_stock), 0) FROM product_batches WHERE product_id = ?) WHERE id = ?");
                $sync_stmt->execute([$product_id, $product_id]);

                $feedback = "Batch [{$batch_no}] updated successfully!";
                $feedback_type = "success";
            } catch (Exception $e) {
                $feedback = "Error updating batch: " . $e->getMessage();
                $feedback_type = "danger";
            }
        }
    }
}

// Handle GET: Delete Batch
if (isset($_GET['action']) && $_GET['action'] === 'delete_batch' && !empty($_GET['batch_id']) && isAdmin()) {
    $del_batch_id = intval($_GET['batch_id']);
    try {
        $del_stmt = $pdo->prepare("DELETE FROM product_batches WHERE id = ? AND product_id = ?");
        $del_stmt->execute([$del_batch_id, $product_id]);

        // Sync total stock on product
        $sync_stmt = $pdo->prepare("UPDATE products SET current_stock = (SELECT COALESCE(SUM(current_stock), 0) FROM product_batches WHERE product_id = ?) WHERE id = ?");
        $sync_stmt->execute([$product_id, $product_id]);

        $feedback = "Batch deleted successfully.";
        $feedback_type = "success";
    } catch (Exception $e) {
        $feedback = "Error deleting batch: " . $e->getMessage();
        $feedback_type = "danger";
    }
}

// Fetch all batches for this product with linked supplier
$batches = [];
if ($db_connected && $pdo) {
    try {
        $b_stmt = $pdo->prepare("
            SELECT 
                pb.*,
                COALESCE(
                    (SELECT s.name FROM purchase_items pi 
                     JOIN purchases pu ON pu.id = pi.purchase_id 
                     JOIN suppliers s ON s.id = pu.supplier_id 
                     WHERE pi.product_id = pb.product_id AND pi.batch_no = pb.batch_no 
                     ORDER BY pu.id DESC LIMIT 1),
                    (SELECT s.name FROM purchase_items pi 
                     JOIN purchases pu ON pu.id = pi.purchase_id 
                     JOIN suppliers s ON s.id = pu.supplier_id 
                     WHERE pi.product_id = pb.product_id 
                     ORDER BY pu.id DESC LIMIT 1),
                    (SELECT s.name FROM suppliers s WHERE s.status = 'Active' ORDER BY s.id ASC LIMIT 1),
                    '—'
                ) as supplier_name
            FROM product_batches pb
            WHERE pb.product_id = ?
            ORDER BY pb.expiry_date ASC, pb.id ASC
        ");
        $b_stmt->execute([$product_id]);
        $batches = $b_stmt->fetchAll(PDO::FETCH_ASSOC);

        // If no rows in product_batches yet, fallback to opening stock / product master
        if (empty($batches)) {
            $default_batch = !empty($product['batch_no']) ? $product['batch_no'] : 'DEFAULT';
            $default_exp = (!empty($product['expiry_date']) && $product['expiry_date'] !== '0000-00-00') ? $product['expiry_date'] : date('Y-m-d', strtotime('+2 years'));
            $batches = [
                [
                    'id' => 0,
                    'product_id' => $product['id'],
                    'batch_no' => $default_batch,
                    'expiry_date' => $default_exp,
                    'purchase_price' => $product['purchase_price'],
                    'trade_price' => $product['trade_price'],
                    'retail_price' => $product['retail_price'],
                    'current_stock' => $product['current_stock'],
                    'supplier_name' => 'General / Opening Stock'
                ]
            ];
        }
    } catch (Exception $e) {}
}

// Calculate summary numbers
$total_batches = count($batches);
$total_quantity = 0;
$entries_with_stock = 0;
$batch_numbers_with_stock = [];

foreach ($batches as $b) {
    $q = intval($b['current_stock'] ?? 0);
    $total_quantity += $q;
    if ($q > 0) {
        $entries_with_stock++;
        $batch_numbers_with_stock[] = $b['batch_no'];
    }
}

// Formatted Product ID (e.g. 0032)
$display_product_id = !empty($product['product_code']) ? $product['product_code'] : str_pad($product['id'], 4, '0', STR_PAD_LEFT);
$display_unit = !empty($product['unit_name']) ? $product['unit_name'] : (!empty($product['stock_unit']) ? $product['stock_unit'] : 'Pcs');

$page_title = "Product Batches - " . htmlspecialchars($product['name']);
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    :root {
        --theme-primary: #10b981;
        --theme-primary-hover: #059669;
        --theme-teal: #0d9488;
        --theme-card-bg: #ffffff;
        --theme-border: #e2e8f0;
    }

    /* KPI Cards */
    .kpi-metric-card {
        background: #ffffff;
        border: 1px solid var(--theme-border);
        border-radius: 12px;
        padding: 12px 18px;
        display: flex;
        align-items: center;
        gap: 14px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .kpi-metric-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 14px rgba(0, 0, 0, 0.05);
    }

    .kpi-icon-box {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }
    .icon-primary { background: #e0f2fe; color: #0284c7; }
    .icon-success { background: #dcfce7; color: #16a34a; }
    .icon-warning { background: #fef3c7; color: #d97706; }
    .icon-danger  { background: #fee2e2; color: #dc2626; }

    /* Catalog Table */
    .catalog-card {
        background: #ffffff;
        border: 1px solid var(--theme-border);
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
        overflow: hidden;
    }

    .catalog-table {
        margin-bottom: 0;
    }

    .catalog-table thead th {
        background-color: #f8fafc;
        border-bottom: 2px solid var(--theme-border);
        color: #475569;
        font-size: 0.76rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 700;
        padding: 10px 14px;
        vertical-align: middle;
        white-space: nowrap;
    }

    .catalog-table tbody td {
        padding: 9px 14px;
        font-size: 0.88rem;
        color: #1e293b;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }

    .catalog-table tbody tr:hover {
        background-color: #f8fafc;
    }

    .action-btn {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        color: #475569;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        transition: all 0.15s ease;
        text-decoration: none;
        cursor: pointer;
        padding: 0;
    }
    .action-btn:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
        color: #1e293b;
    }
    .action-btn.btn-edit {
        background: #fef3c7;
        border: 1px solid #fde68a;
        color: #d97706;
    }
    .action-btn.btn-edit i {
        color: #d97706 !important;
        font-size: 0.88rem;
    }
    .action-btn.btn-edit:hover {
        background: #d97706;
        border-color: #d97706;
        color: #ffffff !important;
        box-shadow: 0 2px 6px rgba(217, 119, 6, 0.35);
    }
    .action-btn.btn-edit:hover i {
        color: #ffffff !important;
    }
    .action-btn.btn-delete {
        background: #fee2e2;
        border: 1px solid #fecaca;
        color: #dc2626;
    }
    .action-btn.btn-delete i {
        color: #dc2626 !important;
        font-size: 0.88rem;
    }
    .action-btn.btn-delete:hover {
        background: #dc2626;
        border-color: #dc2626;
        color: #ffffff !important;
        box-shadow: 0 2px 6px rgba(220, 38, 38, 0.35);
    }
    .action-btn.btn-delete:hover i {
        color: #ffffff !important;
    }
</style>

<div class="container-fluid px-0 py-1">

    <!-- Top Action Bar -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h4 class="fw-bold text-dark mb-0">Product Batches</h4>
            <div class="text-muted small">Manage batch stocks, prices, and expiry dates</div>
        </div>
        <div class="d-flex gap-2">
            <a href="view_product_list.php" class="btn btn-sm btn-outline-secondary bg-white fw-semibold shadow-sm px-3 rounded-3">
                <i class="fas fa-arrow-left mr-1"></i> Back to Products
            </a>
            <?php if (isAdmin()): ?>
            <button type="button" class="btn btn-sm btn-primary fw-semibold shadow-sm px-3 rounded-3" data-toggle="modal" data-target="#modalAddBatch">
                <i class="fas fa-plus mr-1"></i> Add Batch
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alert / Feedback notification -->
    <?php if (!empty($feedback)): ?>
        <div class="alert alert-<?= $feedback_type ?> alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3 d-flex align-items-center" role="alert">
            <i class="fas <?= $feedback_type === 'success' ? 'fa-check-circle text-success' : 'fa-exclamation-triangle text-warning' ?> fs-5 mr-2"></i>
            <div class="fw-semibold"><?= htmlspecialchars($feedback) ?></div>
            <button type="button" class="close ml-auto" data-dismiss="alert" aria-label="Close"><span>&times;</span></button>
        </div>
    <?php endif; ?>

    <!-- Product Info Banner (Matching Project Theme) -->
    <div class="card border-0 shadow-sm rounded-3 mb-3 bg-white" style="border-left: 4px solid var(--theme-primary, #10b981) !important;">
        <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon-box icon-success">
                    <i class="fas fa-pills"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-light text-secondary border font-monospace px-2 py-1" style="font-size: 0.8rem;"><?= htmlspecialchars($display_product_id) ?></span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fw-semibold"><?= htmlspecialchars($product['status'] ?? 'Active') ?></span>
                    </div>
                    <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($product['name']) ?></h5>
                    <div class="text-muted small">
                        Company: <strong class="text-dark"><?= htmlspecialchars(!empty($product['company_name']) ? $product['company_name'] : '—') ?></strong>
                        &nbsp;|&nbsp; Category: <strong class="text-dark"><?= htmlspecialchars(!empty($product['category_name']) ? $product['category_name'] : '—') ?></strong>
                        &nbsp;|&nbsp; Stock Unit: <strong class="text-dark"><?= htmlspecialchars($display_unit) ?></strong>
                    </div>
                </div>
            </div>
            <div>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fw-semibold">
                    <i class="fas fa-check-circle mr-1"></i> <?= $entries_with_stock ?> <?= $entries_with_stock == 1 ? 'Batch' : 'Batches' ?> with Stock
                </span>
            </div>
        </div>
    </div>

    <!-- Metric Summary Statistics Strip (Matching KPI Card Style) -->
    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-lg-4">
            <div class="kpi-metric-card">
                <div class="kpi-icon-box icon-primary">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px;">Total Batches</span>
                    <h4 class="fw-bold mb-0 text-dark"><?= $total_batches ?></h4>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="kpi-metric-card">
                <div class="kpi-icon-box icon-success">
                    <i class="fas fa-boxes"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px;">Total Quantity</span>
                    <h4 class="fw-bold mb-0 text-success"><?= number_format($total_quantity) ?> <span class="fs-6 text-muted fw-normal"><?= htmlspecialchars($display_unit) ?></span></h4>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="kpi-metric-card">
                <div class="kpi-icon-box icon-warning">
                    <i class="fas fa-check-double"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px;">Entries with Stock</span>
                    <h4 class="fw-bold mb-0 text-dark"><?= $entries_with_stock ?> <span class="fs-6 text-muted fw-normal">Active</span></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Batch Numbers (with stock) Pills -->
    <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
        <span class="fw-bold text-dark me-1" style="font-size: 0.88rem;">Batch Numbers (with stock):</span>
        <?php if (!empty($batch_numbers_with_stock)): ?>
            <?php foreach ($batch_numbers_with_stock as $bno): ?>
                <span class="badge bg-light text-primary border font-monospace px-2 py-1 shadow-sm"><?= htmlspecialchars($bno) ?></span>
            <?php endforeach; ?>
        <?php else: ?>
            <span class="text-muted small italic">None currently in stock</span>
        <?php endif; ?>
    </div>

    <!-- Main Batches Table (Matching Catalog Table Style) -->
    <div class="catalog-card mb-4">
        <div class="table-responsive">
            <table class="table catalog-table">
                <thead>
                    <tr>
                        <th style="width: 50px;" class="text-center">Sr#</th>
                        <th style="width: 110px;">Product Code</th>
                        <th style="min-width: 120px;">Batch No</th>
                        <th style="width: 90px;" class="text-center">Quantity</th>
                        <th style="min-width: 150px;">Supplier</th>
                        <th style="min-width: 110px;">Category</th>
                        <th style="min-width: 130px;">Company</th>
                        <th style="min-width: 110px;" class="text-end text-right">Sale Price</th>
                        <th style="min-width: 110px;" class="text-end text-right">Purchase Price</th>
                        <th style="min-width: 120px;" class="text-end text-right">Total Amount</th>
                        <th style="width: 110px;">Expiry</th>
                        <th style="width: 80px;">Unit</th>
                        <th style="width: 90px;" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($batches)): ?>
                        <tr>
                            <td colspan="13" class="text-center py-5 text-muted">
                                <i class="fas fa-layer-group fa-2x text-secondary opacity-50 mb-2 d-block"></i>
                                <h6 class="fw-bold text-dark">No batch records found</h6>
                                <p class="small text-muted mb-0">No batches have been assigned to this product yet.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $sr = 0;
                        foreach ($batches as $b): 
                            $sr++;
                            $qty = intval($b['current_stock'] ?? 0);
                            $sale_p = floatval($b['trade_price'] > 0 ? $b['trade_price'] : ($b['retail_price'] > 0 ? $b['retail_price'] : $product['trade_price']));
                            $pur_p = floatval($b['purchase_price'] > 0 ? $b['purchase_price'] : $product['purchase_price']);
                            $tot_amt = $qty * ($sale_p > 0 ? $sale_p : $pur_p);
                            $exp_display = (!empty($b['expiry_date']) && $b['expiry_date'] !== '0000-00-00') ? date('d-m-Y', strtotime($b['expiry_date'])) : '—';
                        ?>
                            <tr>
                                <td class="text-center text-muted small"><?= $sr ?></td>
                                <td>
                                    <span class="badge bg-light text-secondary border font-monospace px-2 py-1" style="font-size: 0.8rem;">
                                        <?= htmlspecialchars($display_product_id) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark font-monospace"><?= htmlspecialchars($b['batch_no']) ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($qty <= 0): ?>
                                        <span class="text-danger fw-bold">0</span>
                                    <?php else: ?>
                                        <span class="text-dark fw-bold"><?= $qty ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-dark"><?= htmlspecialchars($b['supplier_name'] ?: '—') ?></span>
                                </td>
                                <td>
                                    <span class="text-secondary"><?= htmlspecialchars(!empty($product['category_name']) ? $product['category_name'] : '—') ?></span>
                                </td>
                                <td>
                                    <span class="text-secondary"><?= htmlspecialchars(!empty($product['company_name']) ? $product['company_name'] : '—') ?></span>
                                </td>
                                <td class="text-end text-right font-monospace fw-bold text-dark">
                                    Rs. <?= number_format($sale_p, 2) ?>
                                </td>
                                <td class="text-end text-right font-monospace text-muted">
                                    Rs. <?= number_format($pur_p, 2) ?>
                                </td>
                                <td class="text-end text-right font-monospace fw-bold text-dark">
                                    Rs. <?= number_format($tot_amt, 2) ?>
                                </td>
                                <td>
                                    <span class="font-monospace text-muted small"><?= $exp_display ?></span>
                                </td>
                                <td>
                                    <span class="text-muted small"><?= htmlspecialchars($display_unit) ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                        <?php if (isAdmin()): ?>
                                            <!-- Edit Batch Button -->
                                            <button type="button" 
                                                    class="action-btn btn-edit" 
                                                    title="Edit Batch" 
                                                    onclick="openEditBatchModal(<?= htmlspecialchars(json_encode([
                                                        'id' => $b['id'],
                                                        'batch_no' => $b['batch_no'],
                                                        'expiry_date' => $b['expiry_date'],
                                                        'sale_price' => $sale_p,
                                                        'purchase_price' => $pur_p,
                                                        'quantity' => $qty
                                                    ])) ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>

                                            <?php if ($b['id'] > 0): ?>
                                            <!-- Delete Batch Button -->
                                            <a href="product_batches.php?product_id=<?= $product_id ?>&action=delete_batch&batch_id=<?= $b['id'] ?>" 
                                               class="action-btn btn-delete" 
                                               title="Delete Batch" 
                                               onclick="return confirm('Are you sure you want to delete batch [<?= htmlspecialchars(addslashes($b['batch_no'])) ?>]?');">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add New Batch -->
<div class="modal fade" id="modalAddBatch" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST" action="product_batches.php?product_id=<?= $product_id ?>">
                <input type="hidden" name="action" value="add_batch">
                <div class="modal-header text-white p-3 px-4" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <h6 class="modal-title fw-bold text-white mb-0"><i class="fas fa-plus-circle mr-1"></i> Add New Batch</h6>
                    <button type="button" class="close text-white opacity-75" data-dismiss="modal" aria-label="Close" style="background:none; border:none; font-size:1.5rem;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark">Batch Number <span class="text-danger">*</span></label>
                        <input type="text" name="batch_no" class="form-control font-monospace fw-bold" placeholder="e.g. BLS-004" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold text-dark">Expiry Date <span class="text-danger">*</span></label>
                            <input type="date" name="expiry_date" class="form-control" value="<?= date('Y-m-d', strtotime('+2 years')) ?>" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold text-dark">Quantity (Stock)</label>
                            <input type="number" min="0" name="quantity" class="form-control text-center fw-bold" placeholder="0" value="0">
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold text-dark">Sale Price / TP (Rs.) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="sale_price" class="form-control text-end text-right fw-bold text-success" placeholder="0.00" value="<?= htmlspecialchars($product['trade_price'] ?? '0.00') ?>" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold text-dark">Purchase Price (Rs.)</label>
                            <input type="number" step="0.01" min="0" name="purchase_price" class="form-control text-end text-right fw-bold" placeholder="0.00" value="<?= htmlspecialchars($product['purchase_price'] ?? '0.00') ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white border-top p-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary px-3 rounded-3" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4 rounded-3 shadow-sm">Save Batch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Batch -->
<div class="modal fade" id="modalEditBatch" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST" action="product_batches.php?product_id=<?= $product_id ?>">
                <input type="hidden" name="action" value="edit_batch">
                <input type="hidden" name="batch_id" id="edit_batch_id" value="">
                <div class="modal-header text-white p-3 px-4" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);">
                    <h6 class="modal-title fw-bold text-white mb-0"><i class="fas fa-edit mr-1"></i> Edit Batch</h6>
                    <button type="button" class="close text-white opacity-75" data-dismiss="modal" aria-label="Close" style="background:none; border:none; font-size:1.5rem;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark">Batch Number <span class="text-danger">*</span></label>
                        <input type="text" name="batch_no" id="edit_batch_no" class="form-control font-monospace fw-bold" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold text-dark">Expiry Date <span class="text-danger">*</span></label>
                            <input type="date" name="expiry_date" id="edit_expiry_date" class="form-control" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold text-dark">Quantity (Stock)</label>
                            <input type="number" min="0" name="quantity" id="edit_quantity" class="form-control text-center fw-bold" required>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold text-dark">Sale Price / TP (Rs.) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="sale_price" id="edit_sale_price" class="form-control text-end text-right fw-bold text-primary" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold text-dark">Purchase Price (Rs.)</label>
                            <input type="number" step="0.01" min="0" name="purchase_price" id="edit_purchase_price" class="form-control text-end text-right fw-bold">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white border-top p-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary px-3 rounded-3" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4 rounded-3 shadow-sm">Update Batch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditBatchModal(data) {
    if (!data) return;
    document.getElementById('edit_batch_id').value = data.id || '';
    document.getElementById('edit_batch_no').value = data.batch_no || '';
    document.getElementById('edit_expiry_date').value = data.expiry_date || '';
    document.getElementById('edit_sale_price').value = parseFloat(data.sale_price || 0).toFixed(2);
    document.getElementById('edit_purchase_price').value = parseFloat(data.purchase_price || 0).toFixed(2);
    document.getElementById('edit_quantity').value = data.quantity || 0;
    
    $('#modalEditBatch').modal('show');
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
