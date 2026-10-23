<?php
/**
 * Bestway Distribution - Enterprise Product Catalog & Inventory List
 * Multi-Unit B2B Wholesale Inventory Management
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

// Quick AJAX Endpoint for fetching all batches of a product
if (isset($_GET['action']) && $_GET['action'] === 'get_batches' && !empty($_GET['id'])) {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    $pid = intval($_GET['id']);
    $batches = [];

    if ($db_connected && $pdo) {
        try {
            $b_stmt = $pdo->prepare("
                SELECT 
                    id, 
                    batch_no, 
                    expiry_date, 
                    manufacturing_date, 
                    purchase_price, 
                    trade_price, 
                    retail_price, 
                    initial_quantity, 
                    current_stock, 
                    status
                FROM product_batches
                WHERE product_id = ?
                ORDER BY (current_stock > 0) DESC, expiry_date ASC, id DESC
            ");
            $b_stmt->execute([$pid]);
            $batches = $b_stmt->fetchAll(PDO::FETCH_ASSOC);

            // If empty, check purchase_items as fallback
            if (empty($batches)) {
                $pi_stmt = $pdo->prepare("
                    SELECT 
                        pi.id, 
                        pi.batch_no, 
                        pi.expiry_date, 
                        NULL as manufacturing_date,
                        pi.purchase_price, 
                        pi.trade_price, 
                        pi.retail_price, 
                        pi.quantity as initial_quantity, 
                        pi.quantity as current_stock, 
                        'From Purchase' as status
                    FROM purchase_items pi
                    WHERE pi.product_id = ? AND pi.batch_no IS NOT NULL AND pi.batch_no != ''
                    ORDER BY pi.id DESC
                ");
                $pi_stmt->execute([$pid]);
                $batches = $pi_stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {}
    }

    echo json_encode(['success' => true, 'batches' => $batches]);
    exit;
}

$page_title = "Products";
$compact_page_heading = true;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin']);

// Action column (View / Edit / Delete) is Admin only
$can_manage_products = isAdmin();

// Handle Inline Delete Action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id'])) {
    if (!$can_manage_products) {
        redirect(BASE_URL . 'modules/product/view_product_list.php', 'You do not have permission to delete products.', 'warning');
    }
    $del_id = intval($_GET['id']);
    if ($db_connected && $pdo) {
        try {
            // Check if product exists in sale_items or purchase_items
            $chk_sale = $pdo->prepare("SELECT COUNT(*) FROM sale_items WHERE product_id = ?");
            $chk_sale->execute([$del_id]);
            $used_in_sale = $chk_sale->fetchColumn();

            if ($used_in_sale > 0) {
                $del_msg = "This product has already been used in invoices and cannot be deleted. You can set its status to Inactive instead.";
                $del_type = "warning";
            } else {
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM opening_stock_logs WHERE product_id = ?")->execute([$del_id]);
                $pdo->prepare("DELETE FROM product_batches WHERE product_id = ?")->execute([$del_id]);
                $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$del_id]);
                $pdo->commit();
                $del_msg = "Product deleted successfully.";
                $del_type = "success";
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $del_msg = "Delete error: " . $e->getMessage();
            $del_type = "danger";
        }
    }
}


// Filter Parameters
$search          = trim($_GET['search'] ?? '');
$company_filter  = intval($_GET['company_id'] ?? 0);
$category_filter = intval($_GET['category_id'] ?? 0);
$stock_status    = trim($_GET['stock_status'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(p.name LIKE ? OR p.product_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($company_filter > 0) {
    $where[] = "p.company_id = ?";
    $params[] = $company_filter;
}

if ($category_filter > 0) {
    $where[] = "p.category_id = ?";
    $params[] = $category_filter;
}

if ($stock_status === 'in_stock') {
    $where[] = "p.current_stock > p.reorder_level";
} elseif ($stock_status === 'low_stock') {
    $where[] = "p.current_stock > 0 AND p.current_stock <= p.reorder_level";
} elseif ($stock_status === 'out_of_stock') {
    $where[] = "p.current_stock <= 0";
}

$where_sql = implode(" AND ", $where);

$products = [];
$total_products = 0;
$total_inventory_cost = 0;
$low_stock_count = 0;
$out_of_stock_count = 0;

if ($db_connected && $pdo) {
    try {
        // High Level Analytics KPI
        $kpi = $pdo->query("
            SELECT 
                COUNT(*) as total_items,
                SUM(current_stock * COALESCE(NULLIF(trade_price, 0), purchase_price, 0)) as total_val,
                SUM(CASE WHEN current_stock <= reorder_level AND current_stock > 0 THEN 1 ELSE 0 END) as low_cnt,
                SUM(CASE WHEN current_stock <= 0 THEN 1 ELSE 0 END) as out_cnt
            FROM products
        ")->fetch();

        $total_products       = intval($kpi['total_items'] ?? 0);
        $total_inventory_cost = floatval($kpi['total_val'] ?? 0);
        $low_stock_count      = intval($kpi['low_cnt'] ?? 0);
        $out_of_stock_count   = intval($kpi['out_cnt'] ?? 0);

        // Fetch Filtered Products with latest Batch & Expiry
        $stmt = $pdo->prepare("
            SELECT 
                p.*,
                COALESCE(c.name, (
                    SELECT COALESCE(NULLIF(s.company_name, ''), s.name)
                    FROM purchases pu
                    JOIN suppliers s ON pu.supplier_id = s.id
                    JOIN purchase_items pi ON pi.purchase_id = pu.id
                    WHERE pi.product_id = p.id
                    ORDER BY pu.id DESC LIMIT 1
                )) as company_name,
                cat.name as category_name,
                u.name as unit_name,
                u.short_name as unit_short,
                COALESCE(
                    (SELECT pb.batch_no FROM product_batches pb WHERE pb.product_id = p.id AND pb.current_stock > 0 ORDER BY pb.expiry_date ASC, pb.id DESC LIMIT 1),
                    (SELECT pb.batch_no FROM product_batches pb WHERE pb.product_id = p.id ORDER BY pb.id DESC LIMIT 1),
                    (SELECT pi.batch_no FROM purchase_items pi WHERE pi.product_id = p.id AND pi.batch_no IS NOT NULL AND pi.batch_no != '' ORDER BY pi.id DESC LIMIT 1)
                ) as batch_no,
                COALESCE(
                    (SELECT pb.expiry_date FROM product_batches pb WHERE pb.product_id = p.id AND pb.current_stock > 0 ORDER BY pb.expiry_date ASC, pb.id DESC LIMIT 1),
                    (SELECT pb.expiry_date FROM product_batches pb WHERE pb.product_id = p.id ORDER BY pb.id DESC LIMIT 1),
                    (SELECT pi.expiry_date FROM purchase_items pi WHERE pi.product_id = p.id AND pi.expiry_date IS NOT NULL ORDER BY pi.id DESC LIMIT 1)
                ) as expiry_date,
                (SELECT COUNT(*) FROM product_batches pb WHERE pb.product_id = p.id) as batch_count
            FROM products p
            LEFT JOIN companies c ON p.company_id = c.id
            LEFT JOIN categories cat ON p.category_id = cat.id
            LEFT JOIN units u ON p.unit_id = u.id
            WHERE $where_sql
            ORDER BY p.id DESC
            LIMIT 300
        ");
        $stmt->execute($params);
        $products = $stmt->fetchAll();

        // Dropdowns data
        $companies  = $pdo->query("SELECT id, name FROM companies WHERE status='Active' ORDER BY name ASC")->fetchAll();
        $categories = $pdo->query("SELECT id, name FROM categories WHERE status='Active' ORDER BY name ASC")->fetchAll();
    } catch (Exception $e) {}
}
?>

<style>
    :root {
        --theme-primary: #0284c7;
        --theme-primary-hover: #0369a1;
        --theme-teal: #0d9488;
        --theme-card-bg: #ffffff;
        --theme-border: #e2e8f0;
    }

    .page-title-badge {
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
        color: #ffffff;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);
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

    /* Filter Card */
    .filter-card {
        background: #ffffff;
        border: 1px solid var(--theme-border);
        border-radius: 12px;
        padding: 12px 16px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.02);
        margin-bottom: 16px;
    }

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

    .packing-formula-badge {
        background: #f0fdf4;
        color: #166534;
        border: 1px solid #bbf7d0;
        border-radius: 6px;
        padding: 3px 8px;
        font-size: 0.74rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        margin-top: 4px;
    }

    .rate-badge {
        font-family: monospace;
        font-size: 0.92rem;
        font-weight: 700;
    }

    .profit-pill {
        background: #e0f2fe;
        color: #0369a1;
        border-radius: 6px;
        padding: 2px 7px;
        font-size: 0.75rem;
        font-weight: 700;
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
    .action-btn.btn-batches {
        background: #ecfdf5;
        border-color: #a7f3d0;
        color: #059669;
    }
    .action-btn.btn-batches:hover {
        background: #059669;
        border-color: #059669;
        color: #ffffff !important;
    }
    .action-btn.btn-batches:hover i {
        color: #ffffff !important;
    }
    .action-btn.btn-view {
        background: #f0f9ff;
        border-color: #bae6fd;
        color: #0284c7;
    }
    .action-btn.btn-view:hover {
        background: #0284c7;
        border-color: #0284c7;
        color: #ffffff !important;
    }
    .action-btn.btn-view:hover i {
        color: #ffffff !important;
    }
    .action-btn.btn-edit {
        background: #fefce8;
        border-color: #fef08a;
        color: #d97706;
    }
    .action-btn.btn-edit:hover {
        background: #d97706;
        border-color: #d97706;
        color: #ffffff !important;
    }
    .action-btn.btn-edit:hover i {
        color: #ffffff !important;
    }
    .action-btn.btn-delete {
        background: #fef2f2;
        border-color: #fecaca;
        color: #dc2626;
    }
    .action-btn.btn-delete:hover {
        background: #dc2626;
        border-color: #dc2626;
        color: #ffffff !important;
    }
    .action-btn.btn-delete:hover i {
        color: #ffffff !important;
    }
    @media print {
        .page-title-badge, .filter-card, .btn, .sidebar, .topbar, #sidebarWrapper, .pagination, .action-btn, .action-col {
            display: none !important;
        }
        body, .content, #content-wrapper {
            background: #fff !important;
            margin: 0 !important;
            padding: 0 !important;
        }
    }
</style>

<!-- Top Action Bar -->
<div class="d-flex justify-content-end align-items-center mb-3">
    <div class="d-flex flex-wrap gap-2">
        <?php if ($can_manage_products): ?>
        <a href="print_products.php?<?= http_build_query($_GET) ?>" target="_blank" class="btn btn-sm btn-outline-secondary bg-white fw-semibold shadow-sm px-3 rounded-3">
            <i class="fa-solid fa-print me-1"></i> Print Products
        </a>
        <?php endif; ?>
        <a href="../sale/new_sale.php" class="btn btn-sm btn-outline-primary bg-white fw-semibold shadow-sm px-3 rounded-3">
            <i class="fa-solid fa-cart-plus me-1"></i> Create Sales Invoice
        </a>
        <?php if ($can_manage_products): ?>
        <a href="add_product.php" class="btn btn-sm btn-primary fw-semibold shadow-sm px-3 rounded-3">
            <i class="fa-solid fa-plus me-1"></i> Add New Product
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Alert Message Notification -->
<?php if (!empty($del_msg)): ?>
    <div class="alert alert-<?= $del_type ?> alert-dismissible fade show border-0 shadow-sm rounded-3 d-flex align-items-center mb-4" role="alert">
        <i class="fa-solid <?= $del_type === 'success' ? 'fa-check-circle text-success' : 'fa-exclamation-triangle text-warning' ?> fs-4 me-3"></i>
        <div class="fw-semibold"><?= htmlspecialchars($del_msg) ?></div>
        <button type="button" class="close ml-auto" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
<?php endif; ?>

<!-- KPI Metric Summary Row -->
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-lg-3">
        <div class="kpi-metric-card">
            <div class="kpi-icon-box icon-primary">
                <i class="fa-solid fa-pills"></i>
            </div>
            <div>
                <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px;">Total Products</span>
                <h4 class="fw-bold mb-0 text-dark"><?= number_format($total_products) ?></h4>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="kpi-metric-card">
            <div class="kpi-icon-box icon-success">
                <i class="fa-solid fa-university"></i>
            </div>
            <div>
                <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px;">Stock Value (TP)</span>
                <h4 class="fw-bold mb-0 text-success">Rs. <?= number_format($total_inventory_cost, 2) ?></h4>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="kpi-metric-card">
            <div class="kpi-icon-box icon-warning">
                <i class="fa-solid fa-exclamation-triangle"></i>
            </div>
            <div>
                <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px;">Low Stock Warning</span>
                <h4 class="fw-bold mb-0 text-warning"><?= number_format($low_stock_count) ?> <span class="fs-6 text-muted fw-normal">Items</span></h4>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="kpi-metric-card">
            <div class="kpi-icon-box icon-danger">
                <i class="fa-solid fa-box-open"></i>
            </div>
            <div>
                <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px;">Out of Stock</span>
                <h4 class="fw-bold mb-0 text-danger"><?= number_format($out_of_stock_count) ?> <span class="fs-6 text-muted fw-normal">Items</span></h4>
            </div>
        </div>
    </div>
</div>

<!-- Search & Filtering Bar -->
<div class="filter-card">
    <form method="GET" action="" class="row g-2 align-items-center">
        <div class="col-xl-3 col-lg-3 col-md-4">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-start-0" placeholder="Search Brand name, Product code..." value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>

        <div class="col-xl-3 col-lg-3 col-md-4">
            <select name="company_id" class="form-select form-select-sm">
                <option value="">-- All Companies / Brands --</option>
                <?php foreach ($companies as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($company_filter == $c['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-xl-2 col-lg-2 col-md-4">
            <select name="category_id" class="form-select form-select-sm">
                <option value="">-- All Categories --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($category_filter == $cat['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-xl-2 col-lg-2 col-md-6">
            <select name="stock_status" class="form-select form-select-sm">
                <option value="">-- All Stock Status --</option>
                <option value="in_stock" <?= ($stock_status === 'in_stock') ? 'selected' : '' ?>>In Stock</option>
                <option value="low_stock" <?= ($stock_status === 'low_stock') ? 'selected' : '' ?>>Low Stock</option>
                <option value="out_of_stock" <?= ($stock_status === 'out_of_stock') ? 'selected' : '' ?>>Out of Stock</option>
            </select>
        </div>

        <div class="col-xl-2 col-lg-2 col-md-6 d-flex gap-1">
            <button type="submit" class="btn btn-sm btn-primary w-100 fw-bold" title="Filter Records">
                <i class="fa-solid fa-filter me-1"></i> Filter
            </button>
            <a href="print_products.php?<?= http_build_query($_GET) ?>" target="_blank" class="btn btn-sm btn-outline-secondary bg-white px-2 shadow-sm" title="Print Filtered Products">
                <i class="fa-solid fa-print"></i>
            </a>
            <a href="view_product_list.php" class="btn btn-sm btn-outline-secondary px-2" title="Reset Filters">
                <i class="fa-solid fa-undo"></i>
            </a>
        </div>
    </form>
</div>

<!-- Product Master Table -->
<div class="catalog-card">
    <div class="table-responsive">
        <table class="table catalog-table">
            <thead>
                <tr>
                    <th style="width: 130px;">Product Code</th>
                    <th style="width: 28%;">Product Name</th>
                    <th style="width: 22%;">Company</th>
                    <th style="width: 16%;">Category</th>
                    <th class="text-center" style="width: 14%;">Total Batches</th>
                    <th class="text-center" style="width: 14%;">Total Quantity</th>
                    <th class="text-center action-col" style="width: 110px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-box-open fs-1 text-secondary opacity-50 mb-3 d-block"></i>
                            <h6 class="fw-bold text-dark">No products found</h6>
                            <p class="small text-muted mb-3">No products match your current filters or no products have been added yet.</p>
                            <?php if ($can_manage_products): ?>
                            <a href="add_product.php" class="btn btn-sm btn-primary fw-bold px-3 py-2">
                                <i class="fa-solid fa-plus me-1"></i> Add First Product
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <?php
                            $cur_stock = floatval($p['current_stock'] ?? 0);
                            $b_count = intval($p['batch_count'] ?? 0);
                            $qty_disp = ($cur_stock == intval($cur_stock)) ? intval($cur_stock) : $cur_stock;
                            $reorder = floatval($p['reorder_level'] ?? 10);
                        ?>
                        <tr>
                            <td>
                                <span class="badge bg-light text-secondary border font-monospace px-2 py-1" style="font-size: 0.8rem;">
                                    <?= htmlspecialchars($p['product_code']) ?>
                                </span>
                            </td>

                            <td>
                                <div class="fw-bold text-dark" style="font-size: 0.90rem;"><?= htmlspecialchars($p['name']) ?></div>
                            </td>

                            <td>
                                <div class="text-secondary fw-medium"><?= htmlspecialchars(!empty($p['company_name']) ? $p['company_name'] : '—') ?></div>
                            </td>

                            <td>
                                <span class="text-secondary"><?= htmlspecialchars(!empty($p['category_name']) ? $p['category_name'] : '—') ?></span>
                            </td>

                            <td class="text-center">
                                <span class="text-dark fw-semibold"><?= $b_count ?> <?= $b_count == 1 ? 'Batch' : 'Batches' ?></span>
                            </td>

                            <td class="text-center">
                                <?php if ($cur_stock <= 0): ?>
                                    <span class="text-danger fw-bold">0 Pcs (Out)</span>
                                <?php elseif ($cur_stock <= $reorder): ?>
                                    <span class="text-danger fw-bold"><?= $qty_disp ?> Pcs (Low)</span>
                                <?php else: ?>
                                    <span class="text-dark fw-semibold"><?= $qty_disp ?> Pcs</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-center action-col">
                                <div class="d-flex align-items-center justify-content-center gap-1">
                                    <!-- View Batches Button -->
                                    <a href="product_batches.php?product_id=<?= $p['id'] ?>" 
                                       class="action-btn btn-batches" 
                                       title="View Batches (<?= $b_count ?>)">
                                        <i class="fa-solid fa-layer-group text-success"></i>
                                    </a>

                                    <?php if ($can_manage_products): ?>
                                    <!-- Edit Product -->
                                    <a href="edit_product.php?id=<?= $p['id'] ?>" class="action-btn btn-edit" title="Edit Product Details">
                                        <i class="fa-solid fa-edit text-warning"></i>
                                    </a>

                                    <!-- Delete Product -->
                                    <a href="view_product_list.php?action=delete&id=<?= $p['id'] ?>" 
                                       class="action-btn btn-delete" 
                                       title="Delete Product"
                                       onclick="return confirm('Are you sure you want to delete product [<?= htmlspecialchars(addslashes($p['name'])) ?>]?');">
                                        <i class="fa-solid fa-trash text-danger"></i>
                                    </a>
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

<!-- Modal: View Product Details -->
<div class="modal fade" id="productViewModal" tabindex="-1" aria-labelledby="productViewModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <!-- Modal Header -->
            <div class="modal-header text-white p-3 px-4" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);">
                <div class="d-flex align-items-center gap-2">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-white text-primary fw-bold font-monospace px-2 py-1" id="vProdCode">PRD-0001</span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fw-semibold" id="vProdStatus">Active</span>
                        </div>
                        <h5 class="modal-title fw-bold text-white mb-0" id="vProdName">Product Details</h5>
                    </div>
                </div>
                <button type="button" class="close text-white opacity-75" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close" style="background:none; border:none; font-size:1.5rem;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body p-4 bg-light">
                <!-- Master Info Badges Row -->
                <div class="row g-2 mb-3">
                    <div class="col-md-6 col-12">
                        <div class="p-2 px-3 rounded-3 bg-white border d-flex align-items-center gap-2 h-100 shadow-sm">
                            <div class="rounded-circle bg-primary-subtle p-2 text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                                <i class="fa-solid fa-industry fs-6"></i>
                            </div>
                            <div class="overflow-hidden">
                                <span class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.70rem; letter-spacing: 0.5px;">Company / Manufacturer</span>
                                <strong class="text-dark text-truncate d-block" id="vProdCompany">—</strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-2 px-3 rounded-3 bg-white border d-flex align-items-center gap-2 h-100 shadow-sm">
                            <div class="rounded-circle p-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; background-color: #ccfbf1; color: #0d9488;">
                                <i class="fa-solid fa-capsules fs-6"></i>
                            </div>
                            <div class="overflow-hidden">
                                <span class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.70rem; letter-spacing: 0.5px;">Category / Form</span>
                                <strong class="text-dark text-truncate d-block" id="vProdCategory">—</strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-2 px-3 rounded-3 bg-white border d-flex align-items-center gap-2 h-100 shadow-sm">
                            <div class="rounded-circle bg-secondary-subtle p-2 text-secondary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                                <i class="fa-solid fa-boxes-stacked fs-6"></i>
                            </div>
                            <div class="overflow-hidden">
                                <span class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.70rem; letter-spacing: 0.5px;">Stock Unit</span>
                                <strong class="text-dark text-truncate d-block" id="vProdUnit">Pcs</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Secondary Meta Tags: Rack & Barcode -->
                <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
                    <span class="badge bg-white text-secondary border px-3 py-2 fw-medium shadow-sm">
                        <i class="fa-solid fa-location-dot text-danger me-1"></i> Rack / Shelf: <strong class="text-dark" id="vProdRack">—</strong>
                    </span>
                    <span class="badge bg-white text-secondary border px-3 py-2 fw-medium shadow-sm">
                        <i class="fa-solid fa-barcode text-dark me-1"></i> Barcode: <strong class="text-dark font-monospace" id="vProdBarcode">—</strong>
                    </span>
                </div>

                <!-- HIGHLIGHT CARD 1: Batch & Expiry Details (User's primary request) -->
                <div class="card border-0 shadow-sm rounded-3 mb-3 bg-white" style="border-left: 4px solid #0284c7 !important;">
                    <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                        <span class="fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="fa-solid fa-calendar-check text-primary"></i> Active Batch & Expiry Details
                        </span>
                        <span class="badge" id="vProdExpiryStatus">Valid</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-2 text-center align-items-center">
                            <div class="col-6">
                                <div class="p-3 rounded-3 bg-light border">
                                    <span class="text-muted small d-block mb-1 fw-semibold text-uppercase" style="font-size: 0.72rem;">Batch Number</span>
                                    <div class="fw-bold text-primary font-monospace fs-5" id="vProdBatch">—</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 rounded-3 bg-light border">
                                    <span class="text-muted small d-block mb-1 fw-semibold text-uppercase" style="font-size: 0.72rem;">Expiry Date</span>
                                    <div class="fw-bold text-dark font-monospace fs-5" id="vProdExpiry">—</div>
                                    <div class="text-muted small mt-1" id="vProdDaysLeft" style="font-size: 0.75rem;">—</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- HIGHLIGHT CARD 2: Pricing & Commercial Rate Details -->
                <div class="card border-0 shadow-sm rounded-3 mb-3 bg-white" style="border-left: 4px solid #10b981 !important;">
                    <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                        <span class="fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="fa-solid fa-receipt text-success"></i> Pricing & Rate Details
                        </span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle" id="vMarginBadge">Margin: Rs. 0.00</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-2 text-center mb-2">
                            <div class="col-4">
                                <div class="p-2 rounded-2 bg-light border">
                                    <span class="text-muted small d-block fw-semibold" style="font-size: 0.72rem;">Trade Price (TP)</span>
                                    <span class="fw-bold text-primary fs-6 font-monospace" id="vTp">Rs. 0.00</span>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 rounded-2 bg-light border">
                                    <span class="text-muted small d-block fw-semibold" style="font-size: 0.72rem;">Sale Discount</span>
                                    <span class="fw-bold text-warning fs-6 font-monospace" id="vDisc">0.00%</span>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 rounded-2 bg-light border">
                                    <span class="text-muted small d-block fw-semibold" style="font-size: 0.72rem;">Sale Rate</span>
                                    <span class="fw-bold text-success fs-6 font-monospace" id="vSale">Rs. 0.00</span>
                                </div>
                            </div>
                        </div>
                        <div class="row g-2 text-center">
                            <div class="col-6">
                                <div class="p-2 rounded-2 bg-white border border-dashed">
                                    <span class="text-muted small d-block" style="font-size: 0.72rem;">Purchase Cost (Inward)</span>
                                    <span class="fw-bold text-secondary font-monospace" id="vCost">Rs. 0.00</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 rounded-2 bg-white border border-dashed">
                                    <span class="text-muted small d-block" style="font-size: 0.72rem;">Retail Price (MRP)</span>
                                    <span class="fw-bold text-dark font-monospace" id="vRetail">Rs. 0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- HIGHLIGHT CARD 3: Stock Overview -->
                <div class="card border-0 shadow-sm rounded-3 mb-3 bg-white" style="border-left: 4px solid #f59e0b !important;">
                    <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                        <span class="fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="fa-solid fa-warehouse text-warning"></i> Stock on Hand
                        </span>
                        <span class="badge" id="vStockBadge">In Stock</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-2 align-items-center text-center">
                            <div class="col-md-6 col-12">
                                <div class="p-3 rounded-2 bg-light border">
                                    <div class="text-muted small mb-1 fw-semibold text-uppercase" style="font-size: 0.72rem;">Available Physical Stock</div>
                                    <div class="fw-bold text-primary display-6 font-monospace" id="vStockPacks">0</div>
                                    <div class="text-muted small mt-1">Available in Distribution Store</div>
                                </div>
                            </div>
                            <div class="col-md-6 col-12">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="p-2 rounded-2 bg-light border">
                                            <span class="text-muted small d-block" style="font-size: 0.72rem;">Opening Stock</span>
                                            <strong class="text-dark fs-6 font-monospace" id="vOpeningStock">0</strong>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2 rounded-2 bg-light border">
                                            <span class="text-muted small d-block" style="font-size: 0.72rem;">Reorder Alert</span>
                                            <strong class="text-danger fs-6 font-monospace" id="vReorderLevel">10</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- HIGHLIGHT CARD 4: All Recorded Batches (Multi-Batch Breakdown Table) -->
                <div class="card border-0 shadow-sm rounded-3 bg-white">
                    <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                        <span class="fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="fa-solid fa-list-check text-info"></i> All Batches Breakdown
                        </span>
                        <span class="badge bg-light text-secondary border font-monospace" id="vBatchCountBadge">0 Batches</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0" style="font-size: 0.82rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3 py-2">Batch #</th>
                                        <th class="py-2 text-center">Expiry Date</th>
                                        <th class="py-2 text-end">Purchase Cost</th>
                                        <th class="py-2 text-center">Batch Stock</th>
                                        <th class="pe-3 py-2 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="vBatchTableBody">
                                    <tr>
                                        <td colspan="5" class="text-center py-3 text-muted">
                                            <i class="fa-solid fa-spinner fa-spin me-1"></i> Loading batches...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Modal Footer -->
            <div class="modal-footer p-3 bg-white border-top d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary px-4 rounded-3" data-dismiss="modal" data-bs-dismiss="modal">Close</button>
                <div class="d-flex gap-2">
                    <a href="#" id="vBtnOpening" class="btn btn-outline-primary fw-semibold px-3 rounded-3">
                        <i class="fa-solid fa-plus-circle me-1"></i> Add / Adjust Stock
                    </a>
                    <a href="#" id="vBtnEdit" class="btn btn-warning fw-bold px-4 rounded-3 text-dark">
                        <i class="fa-solid fa-edit me-1"></i> Edit Product
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showProductModal(input) {
    let p = {};
    try {
        if (!input) {
            p = {};
        } else if (typeof input === 'object' && input.nodeType === 1) {
            // It's a DOM element (this)
            const jsonStr = input.getAttribute('data-product');
            p = jsonStr ? JSON.parse(jsonStr) : {};
        } else if (typeof input === 'object') {
            p = input;
        } else if (typeof input === 'string') {
            p = JSON.parse(input);
        }
    } catch (e) {
        console.error('Error parsing product data:', e);
        p = {};
    }

    // Immediately open modal
    openProductModal();

    try {
        // 1. Header & Master Info
        const codeEl = document.getElementById('vProdCode');
        if (codeEl) codeEl.textContent = p.code || 'PRD-0000';

        const nameEl = document.getElementById('vProdName');
        if (nameEl) nameEl.textContent = p.name || 'Product Details';

        const statusBadge = document.getElementById('vProdStatus');
        if (statusBadge) {
            const isAct = (p.status || 'Active').toLowerCase() === 'active';
            statusBadge.textContent = isAct ? 'Active' : 'Inactive';
            statusBadge.className = isAct 
                ? 'badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fw-semibold' 
                : 'badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 fw-semibold';
        }

        const compEl = document.getElementById('vProdCompany');
        if (compEl) compEl.textContent = (p.company && p.company !== '—') ? p.company : 'Not Assigned';

        const catEl = document.getElementById('vProdCategory');
        if (catEl) catEl.textContent = (p.category && p.category !== '—') ? p.category : 'Not Assigned';

        const unitEl = document.getElementById('vProdUnit');
        if (unitEl) unitEl.textContent = p.stock_unit || 'Pcs';

        const rackEl = document.getElementById('vProdRack');
        if (rackEl) rackEl.textContent = p.location_rack || '—';

        const barEl = document.getElementById('vProdBarcode');
        if (barEl) barEl.textContent = p.barcode || '—';

        // 2. Active Batch & Expiry
        const batchEl = document.getElementById('vProdBatch');
        if (batchEl) batchEl.textContent = p.batch || '—';

        const expEl = document.getElementById('vProdExpiry');
        if (expEl) expEl.textContent = p.expiry || '—';

        // Expiry calculation
        const expBadge = document.getElementById('vProdExpiryStatus');
        const daysLeftEl = document.getElementById('vProdDaysLeft');
        if (p.raw_expiry && p.raw_expiry !== '0000-00-00') {
            const dateStr = p.raw_expiry.indexOf('T') > -1 ? p.raw_expiry : (p.raw_expiry.split(' ')[0] + 'T00:00:00');
            const expDate = new Date(dateStr);
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            if (!isNaN(expDate.getTime())) {
                const diffMs = expDate.getTime() - today.getTime();
                const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));

                if (diffDays < 0) {
                    if (expBadge) {
                        expBadge.textContent = 'Expired';
                        expBadge.className = 'badge bg-danger text-white px-2 py-1';
                    }
                    if (daysLeftEl) {
                        daysLeftEl.textContent = 'Expired ' + Math.abs(diffDays) + ' days ago';
                        daysLeftEl.className = 'text-danger small mt-1 fw-bold';
                    }
                } else if (diffDays <= 90) {
                    if (expBadge) {
                        expBadge.textContent = 'Expiring Soon';
                        expBadge.className = 'badge bg-warning text-dark px-2 py-1';
                    }
                    if (daysLeftEl) {
                        daysLeftEl.textContent = diffDays + ' days remaining';
                        daysLeftEl.className = 'text-warning-emphasis small mt-1 fw-semibold';
                    }
                } else {
                    if (expBadge) {
                        expBadge.textContent = 'Valid';
                        expBadge.className = 'badge bg-success text-white px-2 py-1';
                    }
                    const years = Math.floor(diffDays / 365);
                    const remDays = diffDays % 365;
                    const months = Math.floor(remDays / 30);
                    let timeStr = '';
                    if (years > 0) timeStr += years + ' yr ';
                    if (months > 0) timeStr += months + ' mos ';
                    if (!timeStr) timeStr = diffDays + ' days ';
                    if (daysLeftEl) {
                        daysLeftEl.textContent = timeStr + 'remaining (' + diffDays + ' days)';
                        daysLeftEl.className = 'text-muted small mt-1';
                    }
                }
            } else {
                if (expBadge) {
                    expBadge.textContent = 'No Expiry';
                    expBadge.className = 'badge bg-secondary text-white px-2 py-1';
                }
                if (daysLeftEl) {
                    daysLeftEl.textContent = '—';
                    daysLeftEl.className = 'text-muted small mt-1';
                }
            }
        } else {
            if (expBadge) {
                expBadge.textContent = 'No Expiry';
                expBadge.className = 'badge bg-secondary text-white px-2 py-1';
            }
            if (daysLeftEl) {
                daysLeftEl.textContent = 'No expiration date recorded';
                daysLeftEl.className = 'text-muted small mt-1';
            }
        }

        // 3. Pricing & Rates
        const tp   = parseFloat(p.tp) || 0;
        const disc = parseFloat(p.disc) || 0;
        const sale = parseFloat(p.sale) || (tp > 0 ? (tp * (1 - (disc / 100))) : 0);
        const cost = parseFloat(p.cost) || 0;
        const retail = parseFloat(p.retail) || 0;
        const profit = sale - cost;
        const marginPct = (cost > 0) ? ((profit / cost) * 100).toFixed(1) : '0';

        const tpEl = document.getElementById('vTp');
        if (tpEl) tpEl.textContent = 'Rs. ' + tp.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        const discEl = document.getElementById('vDisc');
        if (discEl) discEl.textContent = disc.toFixed(2) + '%';

        const saleEl = document.getElementById('vSale');
        if (saleEl) saleEl.textContent = 'Rs. ' + sale.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        const costEl = document.getElementById('vCost');
        if (costEl) costEl.textContent = 'Rs. ' + cost.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        const retEl = document.getElementById('vRetail');
        if (retEl) retEl.textContent = 'Rs. ' + retail.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        const marginBadge = document.getElementById('vMarginBadge');
        if (marginBadge) {
            marginBadge.textContent = 'Profit: Rs. ' + profit.toFixed(2) + ' (' + (profit >= 0 ? '+' : '') + marginPct + '%)';
            marginBadge.className = profit >= 0 
                ? 'badge bg-success-subtle text-success border border-success-subtle' 
                : 'badge bg-danger-subtle text-danger border border-danger-subtle';
        }

        // 4. Stock Details
        const stock = parseFloat(p.stock) || 0;
        const reorder = parseFloat(p.reorder) || 10;
        const unitLabel = p.stock_unit || 'Pcs';

        const stockPacksEl = document.getElementById('vStockPacks');
        if (stockPacksEl) stockPacksEl.textContent = stock + ' ' + unitLabel;

        const openEl = document.getElementById('vOpeningStock');
        if (openEl) openEl.textContent = (parseFloat(p.opening_stock) || 0) + ' ' + unitLabel;

        const reorderEl = document.getElementById('vReorderLevel');
        if (reorderEl) reorderEl.textContent = reorder + ' ' + unitLabel;

        const badge = document.getElementById('vStockBadge');
        if (badge) {
            if (stock <= 0) {
                badge.textContent = 'Out of Stock (0)';
                badge.className = 'badge bg-danger text-white px-2 py-1';
            } else if (stock <= reorder) {
                badge.textContent = 'Low Stock (' + stock + ')';
                badge.className = 'badge bg-warning text-dark px-2 py-1';
            } else {
                badge.textContent = 'In Stock (' + stock + ')';
                badge.className = 'badge bg-success text-white px-2 py-1';
            }
        }

        // 5. Action Buttons
        const btnEdit = document.getElementById('vBtnEdit');
        if (btnEdit && p.id) {
            btnEdit.href = 'edit_product.php?id=' + p.id;
        }

        const btnOpening = document.getElementById('vBtnOpening');
        if (btnOpening && p.id) {
            btnOpening.href = 'add_opening_stock.php?product_id=' + p.id;
        }

        // 6. Fetch all batches for this product via AJAX
        if (p.id) {
            const batchTbody = document.getElementById('vBatchTableBody');
            const batchCountBadge = document.getElementById('vBatchCountBadge');
            if (batchTbody) {
                batchTbody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted"><i class="fa-solid fa-spinner fa-spin me-1"></i> Loading batches...</td></tr>';
            }

            fetch('view_product_list.php?action=get_batches&id=' + p.id)
                .then(res => res.json())
                .then(data => {
                    if (data.success && Array.isArray(data.batches) && data.batches.length > 0) {
                        if (batchCountBadge) {
                            batchCountBadge.textContent = data.batches.length + (data.batches.length === 1 ? ' Batch' : ' Batches');
                        }
                        let html = '';
                        data.batches.forEach(b => {
                            const bStock = parseFloat(b.current_stock) || 0;
                            const bCost = parseFloat(b.purchase_price) || 0;
                            const bExp = b.expiry_date && b.expiry_date !== '0000-00-00' ? b.expiry_date : '—';
                            
                            let bStatus = b.status || 'Active';
                            let bBadgeClass = 'badge bg-secondary-subtle text-secondary';
                            if (bStock > 0) {
                                bBadgeClass = 'badge bg-success-subtle text-success border border-success-subtle';
                            } else if (bStock === 0) {
                                bBadgeClass = 'badge bg-danger-subtle text-danger border border-danger-subtle';
                            }

                            html += `
                                <tr>
                                    <td class="ps-3 py-2 fw-semibold font-monospace text-primary">
                                        <i class="fa-solid fa-barcode text-secondary me-1 opacity-75"></i>${escapeHtml(b.batch_no || 'DEFAULT')}
                                    </td>
                                    <td class="py-2 text-center font-monospace">${escapeHtml(bExp)}</td>
                                    <td class="py-2 text-end font-monospace text-muted">Rs. ${bCost.toFixed(2)}</td>
                                    <td class="py-2 text-center font-monospace fw-bold ${bStock > 0 ? 'text-dark' : 'text-danger'}">
                                        ${bStock} ${escapeHtml(unitLabel)}
                                    </td>
                                    <td class="pe-3 py-2 text-center">
                                        <span class="${bBadgeClass}" style="font-size: 0.72rem;">${escapeHtml(bStatus)}</span>
                                    </td>
                                </tr>
                            `;
                        });
                        if (batchTbody) batchTbody.innerHTML = html;
                    } else {
                        if (batchCountBadge) {
                            batchCountBadge.textContent = (p.batch && p.batch !== '—') ? '1 Batch' : '0 Batches';
                        }
                        if (batchTbody) {
                            batchTbody.innerHTML = `
                                <tr>
                                    <td class="ps-3 py-2 fw-semibold font-monospace text-primary">${escapeHtml(p.batch || '—')}</td>
                                    <td class="py-2 text-center font-monospace">${escapeHtml(p.expiry || '—')}</td>
                                    <td class="py-2 text-end font-monospace text-muted">Rs. ${cost.toFixed(2)}</td>
                                    <td class="py-2 text-center font-monospace fw-bold">${stock} ${escapeHtml(unitLabel)}</td>
                                    <td class="pe-3 py-2 text-center"><span class="badge bg-primary-subtle text-primary" style="font-size: 0.72rem;">Current</span></td>
                                </tr>
                            `;
                        }
                    }
                })
                .catch(() => {
                    if (batchCountBadge) batchCountBadge.textContent = (p.batch && p.batch !== '—') ? '1 Batch' : '0 Batches';
                    if (batchTbody) {
                        batchTbody.innerHTML = `
                            <tr>
                                <td class="ps-3 py-2 fw-semibold font-monospace text-primary">${escapeHtml(p.batch || '—')}</td>
                                <td class="py-2 text-center font-monospace">${escapeHtml(p.expiry || '—')}</td>
                                <td class="py-2 text-end font-monospace text-muted">Rs. ${cost.toFixed(2)}</td>
                                <td class="py-2 text-center font-monospace fw-bold">${stock} ${escapeHtml(unitLabel)}</td>
                                <td class="pe-3 py-2 text-center"><span class="badge bg-primary-subtle text-primary" style="font-size: 0.72rem;">Current</span></td>
                            </tr>
                        `;
                    }
                });
        }
    } catch (err) {
        console.error('Error populating product modal fields:', err);
    }
}

function openProductModal() {
    const modalEl = document.getElementById('productViewModal');
    if (!modalEl) return;

    if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
        window.jQuery('#productViewModal').modal('show');
    } else if (typeof $ !== 'undefined' && typeof $.fn !== 'undefined' && typeof $.fn.modal === 'function') {
        $('#productViewModal').modal('show');
    } else {
        // Pure CSS/DOM fallback
        modalEl.classList.add('show');
        modalEl.style.display = 'block';
        modalEl.removeAttribute('aria-hidden');
        modalEl.setAttribute('aria-modal', 'true');
        document.body.classList.add('modal-open');
        
        let backdrop = document.querySelector('.modal-backdrop');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            document.body.appendChild(backdrop);
        }
    }
}

function closeProductModal() {
    const modalEl = document.getElementById('productViewModal');
    if (!modalEl) return;

    if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
        window.jQuery('#productViewModal').modal('hide');
    } else if (typeof $ !== 'undefined' && typeof $.fn !== 'undefined' && typeof $.fn.modal === 'function') {
        $('#productViewModal').modal('hide');
    } else {
        modalEl.classList.remove('show');
        modalEl.style.display = 'none';
        modalEl.setAttribute('aria-hidden', 'true');
        modalEl.removeAttribute('aria-modal');
        document.body.classList.remove('modal-open');
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) backdrop.remove();
    }
}

// Global delegated click listener so clicking ANY .btn-view button ALWAYS opens modal
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-view');
        if (btn) {
            e.preventDefault();
            showProductModal(btn);
        }
    });
});

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
