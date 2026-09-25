<?php
/**
 * Bestway Wholesale Distribution - Stock Valuation & Inventory Report
 */
$page_title = "Stock Report";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

if (!$pdo) {
    die("<div class='p-4 text-danger'>Database connection unavailable.</div>");
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$company_id = isset($_GET['company_id']) ? intval($_GET['company_id']) : 0;
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$stock_status = isset($_GET['stock_status']) ? trim($_GET['stock_status']) : '';

// Build query
$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(p.name LIKE :s OR p.generic_name LIKE :s OR p.product_code LIKE :s OR p.barcode LIKE :s)";
    $params[':s'] = "%{$search}%";
}
if ($company_id > 0) {
    $where[] = "p.company_id = :company_id";
    $params[':company_id'] = $company_id;
}
if ($category_id > 0) {
    $where[] = "p.category_id = :category_id";
    $params[':category_id'] = $category_id;
}
if ($stock_status === 'in_stock') {
    $where[] = "p.current_stock > p.reorder_level";
} elseif ($stock_status === 'low_stock') {
    $where[] = "p.current_stock > 0 AND p.current_stock <= p.reorder_level";
} elseif ($stock_status === 'out_of_stock') {
    $where[] = "p.current_stock <= 0";
}

$where_sql = implode(" AND ", $where);

// Summary metrics across full inventory (or filtered)
$stmt_summary = $pdo->prepare("SELECT 
    COUNT(*) as total_items,
    COALESCE(SUM(p.current_stock), 0) as total_units,
    COALESCE(SUM(p.current_stock * p.purchase_price), 0) as total_cost_val,
    COALESCE(SUM(p.current_stock * p.trade_price), 0) as total_tp_val,
    COALESCE(SUM(p.current_stock * p.retail_price), 0) as total_retail_val,
    SUM(CASE WHEN p.current_stock > 0 AND p.current_stock <= p.reorder_level THEN 1 ELSE 0 END) as low_stock_count,
    SUM(CASE WHEN p.current_stock <= 0 THEN 1 ELSE 0 END) as out_of_stock_count
FROM products p
WHERE {$where_sql}");
$stmt_summary->execute($params);
$summary = $stmt_summary->fetch(PDO::FETCH_ASSOC);

// Fetch products list
$stmt_list = $pdo->prepare("SELECT 
    p.id, p.product_code, p.barcode, p.name, p.generic_name,
    p.current_stock, p.reorder_level, p.stock_unit,
    p.purchase_price, p.trade_price, p.retail_price,
    c.name as company_name,
    cat.name as category_name
FROM products p
LEFT JOIN companies c ON p.company_id = c.id
LEFT JOIN categories cat ON p.category_id = cat.id
WHERE {$where_sql}
ORDER BY p.name ASC");
$stmt_list->execute($params);
$products = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

// Fetch companies and categories for filter dropdowns
$companies = $pdo->query("SELECT id, name FROM companies ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content-wrapper p-3 p-md-4">
<?php
$report_title       = "Physical Stock & Inventory Valuation Report";
$report_subtitle    = "Status: " . ($stock_status ? ucfirst(str_replace('_', ' ', $stock_status)) : 'All Inventory');
$report_period      = "As of " . date('d M Y');
$report_filename    = "stock_report_" . date('Ymd');
$report_orientation = "landscape";
?>

    <!-- Header Actions -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 d-print-none">
        <div>
            <h4 class="fw-bold mb-1"><i class="fa-solid fa-boxes text-primary me-2"></i>Stock & Inventory Report</h4>
            <span class="text-muted small">Live warehouse quantities, low-stock reorder warnings, and valuation at cost & trade prices.</span>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-secondary btn-sm shadow-sm">
                <i class="fa-solid fa-print me-1"></i> Print Stock Report
            </button>
            <button onclick="exportReportToPDF('#printableReportArea', '<?php echo $report_filename; ?>', 'landscape')" class="btn btn-outline-danger btn-sm shadow-sm">
                <i class="fa-solid fa-file-pdf me-1"></i> Export PDF
            </button>
            <button onclick="exportTableToCSV('stock_report_<?php echo date('Ymd'); ?>.csv')" class="btn btn-outline-success btn-sm shadow-sm">
                <i class="fa-solid fa-file-excel me-1"></i> Export CSV
            </button>
            <a href="<?php echo BASE_URL; ?>modules/product/add_product.php" class="btn btn-primary btn-sm shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> Add Product
            </a>
        </div>
    </div>

    <!-- Printable Report Container -->
    <div id="printableReportArea">
        <?php require __DIR__ . '/../../includes/report_header.php'; ?>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm mb-4 d-print-none">
        <div class="card-body p-3">
            <form method="GET" class="row g-2 align-items-end" id="filterForm">
                <div class="col-md-3 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">Search Product / Formula / Code</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="e.g. Panadol, Paracetamol..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">Company / Manufacturer</label>
                    <select name="company_id" class="form-select form-select-sm">
                        <option value="0">All Companies</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($company_id == $c['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">Category</label>
                    <select name="category_id" class="form-select form-select-sm">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo ($category_id == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">Stock Level</label>
                    <select name="stock_status" class="form-select form-select-sm">
                        <option value="">All Stock Levels</option>
                        <option value="in_stock" <?php echo ($stock_status === 'in_stock') ? 'selected' : ''; ?>>Healthy Stock</option>
                        <option value="low_stock" <?php echo ($stock_status === 'low_stock') ? 'selected' : ''; ?>>Low Stock Warning</option>
                        <option value="out_of_stock" <?php echo ($stock_status === 'out_of_stock') ? 'selected' : ''; ?>>Out of Stock (Zero)</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                    <a href="stock_report.php" class="btn btn-light btn-sm border" title="Reset Filters">
                        <i class="fa-solid fa-undo"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>


    <!-- Stock Table Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-list-check text-primary me-2"></i>Stock Inventory Breakdown (<?php echo count($products); ?> Records)
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="stockTable">
                <thead class="table-light">
                    <tr class="text-muted small text-uppercase">
                        <th>Code</th>
                        <th>Product & Formula</th>
                        <th>Company</th>
                        <th>Category</th>
                        <th class="text-center">Stock</th>
                        <th class="text-center">Unit</th>
                        <th class="text-end">Cost Rate</th>
                        <th class="text-end">Trade Price</th>
                        <th class="text-end">Valuation (Cost)</th>
                        <th class="text-end">Valuation (TP)</th>
                        <th class="text-center">Status</th>
                        <th class="text-center d-print-none">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $row): 
                            $stk = (float)$row['current_stock'];
                            $reorder = (float)$row['reorder_level'];
                            $cost_r = (float)$row['purchase_price'];
                            $tp_r = (float)$row['trade_price'];
                            $val_cost = $stk * $cost_r;
                            $val_tp = $stk * $tp_r;

                            if ($stk <= 0) {
                                $st_badge = '<span class="badge bg-danger">Out of Stock</span>';
                            } elseif ($stk <= $reorder) {
                                $st_badge = '<span class="badge bg-warning text-dark">Low Stock</span>';
                            } else {
                                $st_badge = '<span class="badge bg-success">In Stock</span>';
                            }
                        ?>
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['product_code'] ?: 'P-'.$row['id']); ?></span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['name']); ?></div>
                                    <?php if ($row['generic_name']): ?>
                                        <small class="text-muted"><i class="fa-solid fa-flask me-1"></i><?php echo htmlspecialchars($row['generic_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><small class="text-muted"><?php echo htmlspecialchars($row['company_name'] ?: '-'); ?></small></td>
                                <td><small class="text-muted"><?php echo htmlspecialchars($row['category_name'] ?: '-'); ?></small></td>
                                <td class="text-center">
                                    <span class="fw-bold <?php echo ($stk <= 0) ? 'text-danger' : (($stk <= $reorder) ? 'text-warning' : 'text-dark'); ?>">
                                        <?php echo number_format($stk); ?>
                                    </span>
                                </td>
                                <td class="text-center text-muted small"><?php echo htmlspecialchars($row['stock_unit'] ?: 'Pack'); ?></td>
                                <td class="text-end text-muted">Rs. <?php echo number_format($cost_r, 2); ?></td>
                                <td class="text-end text-dark fw-semibold">Rs. <?php echo number_format($tp_r, 2); ?></td>
                                <td class="text-end text-success fw-semibold">Rs. <?php echo number_format($val_cost, 2); ?></td>
                                <td class="text-end text-primary fw-bold">Rs. <?php echo number_format($val_tp, 2); ?></td>
                                <td class="text-center"><?php echo $st_badge; ?></td>
                                <td class="text-center d-print-none">
                                    <a href="<?php echo BASE_URL; ?>modules/product/edit_product.php?id=<?php echo $row['id']; ?>" class="btn btn-outline-secondary btn-sm" title="Edit Product">
                                        <i class="fa-solid fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="12" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-box-open fs-2 mb-2 d-block text-secondary opacity-50"></i>
                                No stock records found matching your filters.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($products)): ?>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="4" class="text-end">Grand Totals:</td>
                        <td class="text-center"><?php echo number_format($summary['total_units']); ?></td>
                        <td colspan="3" class="text-end">Valuation Totals:</td>
                        <td class="text-end text-success">Rs. <?php echo number_format($summary['total_cost_val'], 2); ?></td>
                        <td class="text-end text-primary">Rs. <?php echo number_format($summary['total_tp_val'], 2); ?></td>
                        <td colspan="2" class="d-print-none"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
    </div> <!-- End #printableReportArea -->
</div> <!-- End .content-wrapper -->

<script>
function exportTableToCSV(filename) {
    let csv = [];
    let rows = document.querySelectorAll("#stockTable tr");
    
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll("td, th");
        let limit = cols.length - (rows[i].querySelector(".d-print-none") ? 1 : 0);
        for (let j = 0; j < limit; j++) {
            let text = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, "").trim();
            text = text.replace(/"/g, '""');
            row.push('"' + text + '"');
        }
        csv.push(row.join(","));
    }

    let csvFile = new Blob([csv.join("\n")], {type: "text/csv"});
    let downloadLink = document.createElement("a");
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
