<?php
/**
 * Bestway Wholesale Distribution - Item Wise Sale Report
 *
 * Shows aggregate sale amounts grouped per product (item-wise),
 * filterable by date range, area / route, salesman and order booker.
 */
$page_title = "Item Wise Sale Report";
$compact_page_heading = true;
$hide_topbar_title = true;
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

// ---- Filters ----
$start_date   = isset($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-01');
$end_date     = isset($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-d');
$area         = trim($_GET['area'] ?? '');
$salesman_id  = (int)($_GET['salesman_id'] ?? 0);

// ---- Build WHERE clause ----
$where_clauses = ["1=1"];
$params = [];
$types  = "";

if (!empty($start_date)) {
    $where_clauses[] = "si.invoice_date >= ?";
    $params[] = $start_date;
    $types .= "s";
}
if (!empty($end_date)) {
    $where_clauses[] = "si.invoice_date <= ?";
    $params[] = $end_date;
    $types .= "s";
}
if ($area !== '') {
    $where_clauses[] = "(LOWER(si.route_name) = LOWER(?) OR LOWER(c.area) = LOWER(?))";
    $params[] = $area;
    $params[] = $area;
    $types .= "ss";
}
if ($salesman_id > 0) {
    $where_clauses[] = "si.booker_id = ?";
    $params[] = $salesman_id;
    $types .= "i";
}

$where_sql = implode(" AND ", $where_clauses);

$summary = [
    'total_products' => 0, 'total_invoices' => 0, 'total_qty' => 0,
    'total_bonus' => 0, 'total_gross' => 0, 'total_discount' => 0, 'total_net' => 0,
    'total_cost' => 0, 'total_margin' => 0,
];

// ---- Summary metrics ----
$summary_sql = "SELECT
    COUNT(DISTINCT COALESCE(p.id, it.product_id)) AS total_products,
    COUNT(DISTINCT si.id) AS total_invoices,
    COALESCE(SUM(it.quantity), 0) AS total_qty,
    COALESCE(SUM(it.bonus_quantity), 0) AS total_bonus,
    COALESCE(SUM(it.unit_price * it.quantity), 0) AS total_gross,
    COALESCE(SUM(it.unit_price * it.quantity) - SUM(COALESCE(it.total_price, it.total_amount, it.unit_price * it.quantity)), 0) AS total_discount,
    COALESCE(SUM(COALESCE(it.total_price, it.total_amount, it.unit_price * it.quantity)), 0) AS total_net,
    COALESCE(SUM(it.quantity * COALESCE(pb.purchase_price, p.purchase_price, 0)), 0) AS total_cost,
    COALESCE(SUM(COALESCE(it.total_price, it.total_amount, it.unit_price * it.quantity)) - SUM(it.quantity * COALESCE(pb.purchase_price, p.purchase_price, 0)), 0) AS total_margin
FROM sale_items it
JOIN sales_invoices si ON it.invoice_id = si.id
LEFT JOIN products p ON p.id = it.product_id
LEFT JOIN product_batches pb ON pb.id = it.batch_id
LEFT JOIN customers c ON c.id = si.customer_id
WHERE {$where_sql}";

$stmt_sum = $conn->prepare($summary_sql);
if (!empty($params)) {
    $stmt_sum->bind_param($types, ...$params);
}
$stmt_sum->execute();
$summary_res = $stmt_sum->get_result()->fetch_assoc();
$stmt_sum->close();
if ($summary_res) { $summary = array_merge($summary, array_map('floatval', $summary_res)); }

// ---- Item-wise aggregates ----
$list_sql = "SELECT
    COALESCE(p.id, it.product_id) AS product_id,
    COALESCE(p.product_code, '') AS product_code,
    COALESCE(NULLIF(it.item_name, ''), p.name, 'Item') AS item_name,
    COUNT(DISTINCT si.id) AS invoice_count,
    SUM(it.quantity) AS total_qty,
    SUM(COALESCE(it.bonus_quantity, 0)) AS total_bonus,
    ROUND(AVG(it.unit_price), 2) AS avg_unit_price,
    ROUND(AVG(COALESCE(pb.purchase_price, p.purchase_price, 0)), 2) AS avg_cost_price,
    SUM(it.unit_price * it.quantity) AS gross_amount,
    SUM(COALESCE(it.total_price, it.total_amount, it.unit_price * it.quantity)) AS net_amount,
    SUM(it.quantity * COALESCE(pb.purchase_price, p.purchase_price, 0)) AS total_cost,
    (SUM(COALESCE(it.total_price, it.total_amount, it.unit_price * it.quantity)) - SUM(it.quantity * COALESCE(pb.purchase_price, p.purchase_price, 0))) AS margin_amount
FROM sale_items it
JOIN sales_invoices si ON it.invoice_id = si.id
LEFT JOIN products p ON p.id = it.product_id
LEFT JOIN product_batches pb ON pb.id = it.batch_id
LEFT JOIN customers c ON c.id = si.customer_id
WHERE {$where_sql}
GROUP BY product_id, item_name, p.product_code
ORDER BY net_amount DESC, total_qty DESC";

$stmt_list = $conn->prepare($list_sql);
if (!empty($params)) {
    $stmt_list->bind_param($types, ...$params);
}
$stmt_list->execute();
$items_list = $stmt_list->get_result();
$stmt_list->close();

// ---- Filter dropdowns ----
$area_options   = [];
$salesmen_list  = [];
$bookers_list   = [];

if ($pdo) {
    try {
        $a1 = $pdo->query("SELECT name FROM areas WHERE status = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
        $a2 = $pdo->query("SELECT name FROM routes WHERE status = 'Active' ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
        $a3 = $pdo->query("SELECT DISTINCT route_name FROM sales_invoices WHERE route_name IS NOT NULL AND route_name != '' ORDER BY route_name ASC")->fetchAll(PDO::FETCH_COLUMN);
        $area_options = array_values(array_unique(array_filter(array_merge($a1 ?: [], $a2 ?: [], $a3 ?: []))));
        sort($area_options);

        $salesmen_list = $pdo->query("SELECT id, full_name FROM employees WHERE employee_type = 'salesman' AND status = 1 ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($salesmen_list)) {
            $salesmen_list = $pdo->query("SELECT id, full_name FROM employees WHERE employee_type = 'salesman' AND status = 1 ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {}
}
?>

<div class="content-wrapper p-3 p-md-4">
<?php
$report_title       = "Item Wise Sale Report";
$report_subtitle    = "Product-Wise Sales & Revenue Summary";
$report_period      = date('d M Y', strtotime($start_date)) . " to " . date('d M Y', strtotime($end_date));
$report_filename    = "item_wise_sale_" . date('Ymd');
$report_orientation = "landscape";
?>

    <!-- Header Actions -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 d-print-none">
        <div>
            <h4 class="fw-bold mb-1"><i class="fa-solid fa-cubes text-primary me-2"></i>Item Wise Sale Report</h4>
            <span class="text-muted small">Product-wise sale quantity, gross, discount, net amounts and profit margin.</span>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-secondary btn-sm shadow-sm">
                <i class="fa-solid fa-print me-1"></i> Print Report
            </button>
            <button onclick="exportReportToPDF('#printableReportArea', '<?php echo $report_filename; ?>', 'landscape')" class="btn btn-outline-danger btn-sm shadow-sm">
                <i class="fa-solid fa-file-pdf me-1"></i> Export PDF
            </button>
            <button onclick="exportTableToCSV('item_wise_sale_<?php echo date('Ymd'); ?>.csv')" class="btn btn-outline-success btn-sm shadow-sm">
                <i class="fa-solid fa-file-excel me-1"></i> Export CSV
            </button>
        </div>
    </div>

    <!-- Printable Report Container -->
    <div id="printableReportArea">
        <?php require __DIR__ . '/../../includes/report_header.php'; ?>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4 row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-5">
        <div class="col">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body p-3 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">Products Sold</span>
                        <h4 class="fw-bold mb-0 text-dark"><?= number_format($summary['total_products']) ?></h4>
                    </div>
                    <div class="p-3 bg-primary-subtle text-primary rounded-circle"><i class="fa-solid fa-boxes fs-4"></i></div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body p-3 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">Total Qty</span>
                        <h4 class="fw-bold mb-0 text-dark"><?= number_format($summary['total_qty']) ?> <small class="text-muted font-weight-normal">(+<?= number_format($summary['total_bonus']) ?>)</small></h4>
                    </div>
                    <div class="p-3 bg-info-subtle text-info rounded-circle"><i class="fa-solid fa-pills fs-4"></i></div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body p-3 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">Gross Amount</span>
                        <h4 class="fw-bold mb-0 text-primary">Rs. <?= number_format($summary['total_gross'], 2) ?></h4>
                    </div>
                    <div class="p-3 bg-secondary-subtle text-secondary rounded-circle"><i class="fa-solid fa-chart-line fs-4"></i></div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body p-3 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">Net Sale</span>
                        <h4 class="fw-bold mb-0 text-dark">Rs. <?= number_format($summary['total_net'], 2) ?></h4>
                        <span class="small text-warning fw-semibold">Disc: Rs. <?= number_format($summary['total_discount'], 2) ?></span>
                    </div>
                    <div class="p-3 bg-warning-subtle text-warning rounded-circle"><i class="fa-solid fa-money-bill-wave fs-4"></i></div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body p-3 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">Total Profit / Margin</span>
                        <?php 
                        $margin_color = $summary['total_margin'] >= 0 ? 'text-success' : 'text-danger';
                        ?>
                        <h4 class="fw-bold mb-0 <?= $margin_color ?>">Rs. <?= number_format($summary['total_margin'], 2) ?></h4>
                        <span class="small text-muted fw-semibold">Cost: Rs. <?= number_format($summary['total_cost'], 2) ?></span>
                    </div>
                    <div class="p-3 bg-success-subtle text-success rounded-circle"><i class="fa-solid fa-hand-holding-dollar fs-4"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm mb-4 d-print-none">
        <div class="card-body p-3">
            <form method="GET" class="row g-2 align-items-end" id="filterForm">
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">Date From</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">Date To</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">Area / Route</label>
                    <select name="area" class="form-select form-select-sm">
                        <option value="">All Areas</option>
                        <?php foreach ($area_options as $a): ?>
                            <option value="<?= htmlspecialchars($a) ?>" <?= ($area === $a) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($a) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">Salesman</label>
                    <select name="salesman_id" class="form-select form-select-sm">
                        <option value="">All Salesmen</option>
                        <?php foreach ($salesmen_list as $sm): ?>
                            <option value="<?= $sm['id'] ?>" <?= ($salesman_id == $sm['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sm['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                    <a href="item_wise_sale.php" class="btn btn-light btn-sm border" title="Reset Filters">
                        <i class="fa-solid fa-undo"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Item Wise Table Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-cubes text-primary me-2"></i>Product Sales Breakdown (<?php echo $items_list->num_rows; ?> Items)
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="itemWiseTable">
                <thead class="table-light">
                    <tr class="text-muted small text-uppercase">
                        <th>#</th>
                        <th>Item Name</th>
                        <th>Code</th>
                        <th class="text-center">Invoices</th>
                        <th class="text-center">Qty</th>
                        <th class="text-center">Bonus</th>
                        <th class="text-end">Avg Rate</th>
                        <th class="text-end">Avg Cost</th>
                        <th class="text-end">Gross Amount</th>
                        <th class="text-end">Discount</th>
                        <th class="text-end">Net Amount</th>
                        <th class="text-end">Profit / Margin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($items_list->num_rows > 0): ?>
                        <?php $idx = 1; while ($row = $items_list->fetch_assoc()): 
                            $row_margin = (float)($row['margin_amount'] ?? 0);
                            $margin_text_color = $row_margin >= 0 ? 'text-success' : 'text-danger';
                        ?>
                            <tr>
                                <td class="text-muted fw-bold"><?= $idx++ ?></td>
                                <td class="fw-semibold text-dark">
                                    <?= htmlspecialchars($row['item_name']) ?>
                                </td>
                                <td><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($row['product_code'] ?: '-') ?></span></td>
                                <td class="text-center"><?= number_format($row['invoice_count']) ?></td>
                                <td class="text-center fw-bold text-dark"><?= number_format($row['total_qty']) ?></td>
                                <td class="text-center text-muted"><?= number_format($row['total_bonus']) ?></td>
                                <td class="text-end text-muted">Rs. <?= number_format($row['avg_unit_price'], 2) ?></td>
                                <td class="text-end text-muted">Rs. <?= number_format($row['avg_cost_price'], 2) ?></td>
                                <td class="text-end text-dark">Rs. <?= number_format($row['gross_amount'], 2) ?></td>
                                <td class="text-end text-warning">Rs. <?= number_format($row['gross_amount'] - $row['net_amount'], 2) ?></td>
                                <td class="text-end fw-bold text-dark">Rs. <?= number_format($row['net_amount'], 2) ?></td>
                                <td class="text-end fw-bold <?= $margin_text_color ?>">Rs. <?= number_format($row_margin, 2) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="12" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-box-open fs-2 mb-2 d-block text-secondary opacity-50"></i>
                                No sale items found for the selected criteria.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <?php if ($items_list->num_rows > 0): ?>
                <?php 
                $tot_margin_color = $summary['total_margin'] >= 0 ? 'text-success' : 'text-danger';
                ?>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="4" class="text-end">Totals:</td>
                        <td class="text-center"><?= number_format($summary['total_qty']) ?></td>
                        <td class="text-center text-muted"><?= number_format($summary['total_bonus']) ?></td>
                        <td class="text-end text-muted">-</td>
                        <td class="text-end text-muted">-</td>
                        <td class="text-end">Rs. <?= number_format($summary['total_gross'], 2) ?></td>
                        <td class="text-end text-warning">Rs. <?= number_format($summary['total_discount'], 2) ?></td>
                        <td class="text-end text-dark">Rs. <?= number_format($summary['total_net'], 2) ?></td>
                        <td class="text-end <?= $tot_margin_color ?>">Rs. <?= number_format($summary['total_margin'], 2) ?></td>
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
    let rows = document.querySelectorAll("#itemWiseTable tr");
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

function exportReportToPDF(selector, filename, orientation) {
    const el = document.querySelector(selector);
    if (!el) return;
    const opts = { margin: [10, 10, 10, 10], filename: filename + '.pdf', image: {type: 'jpeg', quality: 0.95}, html2canvas: {scale: 2}, jsPDF: {unit: 'mm', format: 'a4', orientation: orientation || 'landscape'} };
    if (typeof html2pdf === 'undefined') {
        const s = document.createElement('script');
        s.src = '<?= BASE_URL ?>assets/js/html2pdf.bundle.min.js';
        s.onload = () => html2pdf().set(opts).from(el).save();
        document.body.appendChild(s);
    } else {
        html2pdf().set(opts).from(el).save();
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>