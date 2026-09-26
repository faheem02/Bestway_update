<?php
/**
 * Bestway Distribution - Enterprise Product Catalog & Inventory List
 * Multi-Unit B2B Wholesale Inventory Management
 */
$page_title = "Products";
$compact_page_heading = true;
require_once __DIR__ . '/../../includes/header.php';

// Handle Inline Delete Action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id'])) {
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
                SUM(current_stock * purchase_price) as total_val,
                SUM(CASE WHEN current_stock <= reorder_level AND current_stock > 0 THEN 1 ELSE 0 END) as low_cnt,
                SUM(CASE WHEN current_stock <= 0 THEN 1 ELSE 0 END) as out_cnt
            FROM products
        ")->fetch();

        $total_products       = intval($kpi['total_items'] ?? 0);
        $total_inventory_cost = floatval($kpi['total_val'] ?? 0);
        $low_stock_count      = intval($kpi['low_cnt'] ?? 0);
        $out_of_stock_count   = intval($kpi['out_cnt'] ?? 0);

        // Fetch Filtered Products
        $stmt = $pdo->prepare("
            SELECT 
                p.*,
                c.name as company_name,
                cat.name as category_name,
                u.name as unit_name,
                u.short_name as unit_short
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
        border-radius: 14px;
        padding: 18px 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .kpi-metric-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.06);
    }

    .kpi-icon-box {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
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
        padding: 16px 20px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.02);
        margin-bottom: 22px;
    }

    /* Catalog Table */
    .catalog-card {
        background: #ffffff;
        border: 1px solid var(--theme-border);
        border-radius: 14px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
        overflow: hidden;
    }

    .catalog-table {
        margin-bottom: 0;
    }

    .catalog-table thead th {
        background-color: #f8fafc;
        border-bottom: 2px solid var(--theme-border);
        color: #475569;
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 700;
        padding: 14px 16px;
        vertical-align: middle;
        white-space: nowrap;
    }

    .catalog-table tbody td {
        padding: 14px 16px;
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
        .page-title-badge, .filter-card, .btn, .sidebar, .topbar, #sidebarWrapper, .pagination, .action-btn, th:last-child, td:last-child {
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
        <a href="print_products.php?<?= http_build_query($_GET) ?>" target="_blank" class="btn btn-sm btn-outline-secondary bg-white fw-semibold shadow-sm px-3 rounded-3">
            <i class="fa-solid fa-print me-1"></i> Print Products
        </a>
        <a href="../sale/new_sale.php" class="btn btn-sm btn-outline-primary bg-white fw-semibold shadow-sm px-3 rounded-3">
            <i class="fa-solid fa-cart-plus me-1"></i> Create Sales Invoice
        </a>
        <a href="add_product.php" class="btn btn-sm btn-primary fw-semibold shadow-sm px-3 rounded-3">
            <i class="fa-solid fa-plus me-1"></i> Add New Product
        </a>
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
<div class="row g-3 mb-4">
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
                <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px;">Stock Value (Cost)</span>
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
        <div class="col-md-4">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-start-0" placeholder="Search Brand name, Product code..." value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>

        <div class="col-md-3">
            <select name="company_id" class="form-select form-select-sm">
                <option value="">-- All Companies / Brands --</option>
                <?php foreach ($companies as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($company_filter == $c['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <select name="category_id" class="form-select form-select-sm">
                <option value="">-- All Categories --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($category_filter == $cat['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <select name="stock_status" class="form-select form-select-sm">
                <option value="">-- All Stock Status --</option>
                <option value="in_stock" <?= ($stock_status === 'in_stock') ? 'selected' : '' ?>>In Stock</option>
                <option value="low_stock" <?= ($stock_status === 'low_stock') ? 'selected' : '' ?>>Low Stock</option>
                <option value="out_of_stock" <?= ($stock_status === 'out_of_stock') ? 'selected' : '' ?>>Out of Stock</option>
            </select>
        </div>

        <div class="col-md-2 d-flex gap-1">
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
                    <th style="width: 110px;">Product Code</th>
                    <th>Medicine / Product Name</th>
                    <th>Company / Category</th>
                    <th class="text-end">TP / Sale Rate</th>
                    <th class="text-center">Stock on Hand</th>
                    <th class="text-center" style="width: 115px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-box-open fs-1 text-secondary opacity-50 mb-3 d-block"></i>
                            <h6 class="fw-bold text-dark">No products found</h6>
                            <p class="small text-muted mb-3">No products match your current filters or no products have been added yet.</p>
                            <a href="add_product.php" class="btn btn-sm btn-primary fw-bold px-3 py-2">
                                <i class="fa-solid fa-plus me-1"></i> Add First Product
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <?php
                            $cost = floatval($p['purchase_price'] ?? 0);
                            $tp   = floatval($p['trade_price'] ?? 0);
                            $sale = floatval($p['wholesale_price'] > 0 ? $p['wholesale_price'] : $tp);
                            $profit_pack = $sale - $cost;
                            $cur_stock = floatval($p['current_stock'] ?? 0);
                            $p_box  = max(1, intval($p['packs_per_box'] ?? 10));
                            $t_pack = max(1, intval($p['tablets_per_pack'] ?? 10));
                            $tot_tabs = max(1, intval($p['total_tablets_per_box'] ?? ($p_box * $t_pack)));
                            $stock_boxes = $cur_stock / $p_box;
                            $stock_tabs  = $stock_boxes * $tot_tabs;
                        ?>
                        <tr>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace px-2 py-1">
                                    <?= htmlspecialchars($p['product_code']) ?>
                                </span>
                            </td>

                            <td>
                                <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($p['name']) ?></div>
                            </td>

                            <td>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($p['company_name'] ?? 'General') ?></div>
                                <span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.72rem;">
                                    <?= htmlspecialchars($p['category_name'] ?? 'Dosage') ?>
                                </span>
                            </td>

                            <td class="text-end">
                                <div class="fw-bold text-primary font-monospace">Rs. <?= number_format($tp, 2) ?></div>
                                <div class="small text-muted font-monospace">Sale: Rs. <?= number_format($sale, 2) ?></div>
                            </td>

                            <td class="text-center">
                                <?php if ($cur_stock <= 0): ?>
                                    <span class="badge bg-danger px-2 py-1">Out of Stock (0)</span>
                                <?php elseif ($cur_stock <= ($p['reorder_level'] ?? 10)): ?>
                                    <span class="badge bg-warning text-dark px-2 py-1">
                                        <?= $cur_stock ?> Pcs (Low)
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success px-2 py-1 fw-bold">
                                        <?= $cur_stock ?> Pcs
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="text-center">
                                <div class="d-flex align-items-center justify-content-center gap-1">
                                    <!-- 1. View Product Details -->
                                    <button type="button" 
                                            class="action-btn btn-view" 
                                            title="View Product Details" 
                                            onclick='showProductModal(<?= htmlspecialchars(json_encode([
                                                "id" => $p["id"],
                                                "code" => $p["product_code"],
                                                "name" => $p["name"],
                                                "company" => $p["company_name"] ?? "General Pharma",
                                                "category" => $p["category_name"] ?? "General Dosage",
                                                "stock_unit" => $p["stock_unit"] ?? "Pack",
                                                "p_box" => $p_box,
                                                "t_pack" => $t_pack,
                                                "tot_tabs" => $tot_tabs,
                                                "cost" => $cost,
                                                "tp" => $tp,
                                                "sale" => $sale,
                                                "profit" => $profit_pack,
                                                "stock" => $cur_stock,
                                                "stock_boxes" => number_format($stock_boxes, 1),
                                                "stock_tabs" => number_format($stock_tabs),
                                                "reorder" => $p["reorder_level"] ?? 10
                                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, "UTF-8") ?>)'>
                                        <i class="fa-solid fa-eye text-primary"></i>
                                    </button>

                                    <!-- 2. Edit Product -->
                                    <a href="edit_product.php?id=<?= $p['id'] ?>" class="action-btn btn-edit" title="Edit Product Details">
                                        <i class="fa-solid fa-edit text-warning"></i>
                                    </a>

                                    <!-- 3. Delete Product -->
                                    <a href="view_product_list.php?action=delete&id=<?= $p['id'] ?>" 
                                       class="action-btn btn-delete" 
                                       title="Delete Product"
                                       onclick="return confirm('Are you sure you want to delete product [<?= htmlspecialchars(addslashes($p['name'])) ?>]?');">
                                        <i class="fa-solid fa-trash text-danger"></i>
                                    </a>
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
<div class="modal fade" id="productViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header text-white p-3 px-4" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);">
                <div>
                    <span class="badge bg-white text-primary fw-bold font-monospace px-2 py-1 mb-1" id="vProdCode">PRD-0001</span>
                    <h5 class="modal-title fw-bold text-white mb-0" id="vProdName">Product Details</h5>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <!-- Meta Tags Row -->
                <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
                    <span class="badge bg-white text-dark border px-3 py-2 fw-semibold">
                        <i class="fa-solid fa-industry text-primary me-1"></i> <span id="vProdCompany">Company</span>
                    </span>
                    <span class="badge bg-white text-dark border px-3 py-2 fw-semibold">
                        <i class="fa-solid fa-capsules text-teal me-1" style="color:#0d9488;"></i> <span id="vProdCategory">Category</span>
                    </span>
                    <span class="badge bg-white text-dark border px-3 py-2 fw-semibold">
                        <i class="fa-solid fa-layer-group text-success me-1"></i> Unit: <strong>Pieces (Pcs)</strong>
                    </span>
                </div>

                <!-- Pricing Card -->
                <div class="card border-0 shadow-sm rounded-3 p-3 mb-3 bg-white">
                    <div class="fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                        <i class="fa-solid fa-money-bill-alt text-success"></i> Pricing Details
                    </div>
                    <div class="row g-2 text-center">
                        <div class="col-4">
                            <div class="p-2 rounded-2 bg-light border">
                                <span class="text-muted small d-block">Purchase Cost</span>
                                <span class="fw-bold text-danger fs-6" id="vCost">Rs. 0.00</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded-2 bg-light border">
                                <span class="text-muted small d-block">Official TP</span>
                                <span class="fw-bold text-primary fs-6" id="vTp">Rs. 0.00</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded-2 bg-light border">
                                <span class="text-muted small d-block">Sale Rate</span>
                                <span class="fw-bold text-success fs-6" id="vSale">Rs. 0.00</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Live Stock Breakdown Card -->
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                    <div class="fw-bold text-dark mb-2 d-flex align-items-center justify-content-between">
                        <div><i class="fa-solid fa-warehouse text-warning me-1"></i> Stock Available</div>
                        <span class="badge" id="vStockBadge">In Stock</span>
                    </div>
                    <div class="p-3 rounded-2 bg-light border text-center">
                        <div class="text-muted small mb-1">Available Quantity on Hand</div>
                        <div class="fw-bold text-primary display-6" id="vStockPacks">0</div>
                        <div class="text-muted small mt-1">Pieces (Pcs) in Warehouse</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer p-3 bg-white border-top d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary px-3 rounded-3" data-dismiss="modal">Close</button>
                <div class="d-flex gap-2">
                    <a href="#" id="vBtnEdit" class="btn btn-warning fw-bold px-4 rounded-3 text-dark">
                        <i class="fa-solid fa-edit me-1"></i> Edit Product
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showProductModal(p) {
    const profit = parseFloat(p.profit) || 0;
    document.getElementById('vProdCode').textContent = p.code || 'PRD-0000';
    document.getElementById('vProdName').textContent = p.name || '';
    document.getElementById('vProdCompany').textContent = p.company || 'General Pharma';
    document.getElementById('vProdCategory').textContent = p.category || 'General Dosage';

    const cost = parseFloat(p.cost) || 0;
    const tp   = parseFloat(p.tp) || 0;
    const sale = parseFloat(p.sale) || 0;

    if (document.getElementById('vCost')) document.getElementById('vCost').textContent = 'Rs. ' + cost.toFixed(2);
    if (document.getElementById('vTp')) document.getElementById('vTp').textContent = 'Rs. ' + tp.toFixed(2);
    if (document.getElementById('vSale')) document.getElementById('vSale').textContent = 'Rs. ' + sale.toFixed(2);
    if (document.getElementById('vProfit')) document.getElementById('vProfit').textContent = (profit >= 0 ? '+Rs. ' : '-Rs. ') + Math.abs(profit).toFixed(2);

    if (document.getElementById('vBelowTpTag')) {
        if (tp > 0 && cost < tp) {
            const belowTp = (((tp - cost) / tp) * 100).toFixed(1);
            document.getElementById('vBelowTpTag').textContent = belowTp + '% Below TP';
            document.getElementById('vBelowTpTag').className = 'text-success small fw-semibold';
        } else {
            document.getElementById('vBelowTpTag').textContent = 'At TP or Net Rate';
            document.getElementById('vBelowTpTag').className = 'text-muted small';
        }
    }

    if (document.getElementById('vStoreDiscTag')) {
        if (tp > 0 && sale < tp) {
            const storeDisc = (((tp - sale) / tp) * 100).toFixed(1);
            document.getElementById('vStoreDiscTag').textContent = storeDisc + '% Store Disc';
        } else if (tp > 0 && sale === tp) {
            document.getElementById('vStoreDiscTag').textContent = '100% Full TP';
        } else {
            document.getElementById('vStoreDiscTag').textContent = 'Standard Rate';
        }
    }

    if (document.getElementById('vProfitPct')) {
        if (cost > 0) {
            const marginPct = ((profit / cost) * 100).toFixed(1);
            document.getElementById('vProfitPct').textContent = (profit >= 0 ? '+' : '') + marginPct + '% Margin';
        } else {
            document.getElementById('vProfitPct').textContent = '0% Margin';
        }
    }

    const stock = parseFloat(p.stock) || 0;
    const reorder = parseFloat(p.reorder) || 10;
    document.getElementById('vStockPacks').textContent = stock + ' ' + (p.stock_unit || 'Packs');
    if (document.getElementById('vStockBoxes')) document.getElementById('vStockBoxes').textContent = p.stock_boxes + ' Boxes';
    if (document.getElementById('vStockTabs')) document.getElementById('vStockTabs').textContent = p.stock_tabs + ' Tablets';

    const badge = document.getElementById('vStockBadge');
    if (stock <= 0) {
        badge.textContent = 'Out of Stock';
        badge.className = 'badge bg-danger';
    } else if (stock <= reorder) {
        badge.textContent = 'Low Stock (' + stock + ')';
        badge.className = 'badge bg-warning text-dark';
    } else {
        badge.textContent = 'In Stock (' + stock + ')';
        badge.className = 'badge bg-success';
    }

    if (document.getElementById('vBtnInward')) {
        document.getElementById('vBtnInward').href = 'add_opening_stock.php?product_id=' + p.id;
    }

    if (document.getElementById('vBtnEdit')) {
        document.getElementById('vBtnEdit').href = 'edit_product.php?id=' + p.id;
    }

    $('#productViewModal').modal('show');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
