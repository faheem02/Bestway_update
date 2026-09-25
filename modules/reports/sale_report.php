<?php
/**
 * Bestway Wholesale Distribution - Sales Report
 */
$page_title = "Sales Report";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

// Database connection for sales

// Get filter params
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-d');
$booker_id = isset($_GET['booker_id']) ? trim($_GET['booker_id']) : '';
$route_id = isset($_GET['route_id']) ? trim($_GET['route_id']) : '';
$payment_method = isset($_GET['payment_method']) ? trim($_GET['payment_method']) : '';

// Build query
$where_clauses = ["1=1"];
$params = [];
$types = "";

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
if (!empty($booker_id)) {
    $where_clauses[] = "si.booker_id = ?";
    $params[] = $booker_id;
    $types .= "s";
}
if (!empty($route_id)) {
    $where_clauses[] = "si.route_id = ?";
    $params[] = $route_id;
    $types .= "s";
}
if (!empty($payment_method)) {
    $where_clauses[] = "si.payment_method = ?";
    $params[] = $payment_method;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_clauses);

// Fetch summary metrics
$summary_sql = "SELECT 
    COUNT(*) as total_invoices,
    COALESCE(SUM(si.subtotal), 0) as total_gross,
    COALESCE(SUM(si.discount_amount), 0) as total_discount,
    COALESCE(SUM(si.grand_total), 0) as total_net,
    COALESCE(SUM(si.paid_amount), 0) as total_paid,
    COALESCE(SUM(si.balance_due), 0) as total_due
FROM sales_invoices si
WHERE {$where_sql}";

$stmt_summary = $conn->prepare($summary_sql);
if (!empty($params)) {
    $stmt_summary->bind_param($types, ...$params);
}
$stmt_summary->execute();
$summary = $stmt_summary->get_result()->fetch_assoc();
$stmt_summary->close();

// Fetch invoices
$list_sql = "SELECT 
    si.id, si.invoice_no, si.customer_name, si.invoice_date, si.payment_method,
    si.subtotal, si.discount_amount, si.grand_total, si.paid_amount, si.balance_due,
    si.booker_name, si.route_name
FROM sales_invoices si
WHERE {$where_sql}
ORDER BY si.invoice_date DESC, si.id DESC";

$stmt_list = $conn->prepare($list_sql);
if (!empty($params)) {
    $stmt_list->bind_param($types, ...$params);
}
$stmt_list->execute();
$invoices = $stmt_list->get_result();
$stmt_list->close();

// Fetch bookers and routes for filters from bestway_wholesale
$bookers_list = [];
$routes_list = [];
if ($pdo) {
    try {
        $bookers_list = $pdo->query("SELECT id, full_name AS name FROM employees WHERE employee_type = 'salesman' AND status = 1 ORDER BY full_name ASC")->fetchAll();
        $routes_list = $pdo->query("SELECT id, name FROM routes ORDER BY name ASC")->fetchAll();
    } catch (Exception $e) {}
}
?>

<div class="content-wrapper p-3 p-md-4">
<?php
$report_title       = "Sales & Revenue Report";
$report_subtitle    = "Detailed Sales Invoices & Receivables Summary";
$report_period      = date('d M Y', strtotime($start_date)) . " to " . date('d M Y', strtotime($end_date));
$report_filename    = "sales_report_" . date('Ymd');
$report_orientation = "landscape";
?>

    <!-- Header Actions -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 d-print-none">
        <div>
            <h4 class="fw-bold mb-1"><i class="fa-solid fa-chart-line text-primary me-2"></i>Sales Report</h4>
            <span class="text-muted small">Comprehensive overview of sales, revenue, discounts, and receivables.</span>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-secondary btn-sm shadow-sm">
                <i class="fa-solid fa-print me-1"></i> Print Report
            </button>
            <button onclick="exportReportToPDF('#printableReportArea', '<?php echo $report_filename; ?>', 'landscape')" class="btn btn-outline-danger btn-sm shadow-sm">
                <i class="fa-solid fa-file-pdf me-1"></i> Export PDF
            </button>
            <button onclick="exportTableToCSV('sales_report_<?php echo date('Ymd'); ?>.csv')" class="btn btn-outline-success btn-sm shadow-sm">
                <i class="fa-solid fa-file-excel me-1"></i> Export CSV
            </button>
            <a href="<?php echo BASE_URL; ?>modules/sale/new_sale.php" class="btn btn-primary btn-sm shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> New Sale
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
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">Date From</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">Date To</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">Salesman</label>
                    <select name="booker_id" class="form-select form-select-sm">
                        <option value="">All Bookers</option>
                        <?php foreach ($bookers_list as $b): ?>
                            <option value="<?php echo $b['id']; ?>" <?php echo ($booker_id == $b['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($b['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">Route / Area</label>
                    <select name="route_id" class="form-select form-select-sm">
                        <option value="">All Routes</option>
                        <?php foreach ($routes_list as $r): ?>
                            <option value="<?php echo $r['id']; ?>" <?php echo ($route_id == $r['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($r['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">Payment Method</label>
                    <select name="payment_method" class="form-select form-select-sm">
                        <option value="">All Methods</option>
                        <option value="Cash" <?php echo ($payment_method == 'Cash') ? 'selected' : ''; ?>>Cash</option>
                        <option value="Credit" <?php echo ($payment_method == 'Credit') ? 'selected' : ''; ?>>Credit</option>
                        <option value="Bank" <?php echo ($payment_method == 'Bank') ? 'selected' : ''; ?>>Bank Transfer</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                    <a href="sale_report.php" class="btn btn-light btn-sm border" title="Reset Filters">
                        <i class="fa-solid fa-undo"></i>
                    </a>
                </div>
                <!-- Quick Date Presets -->
                <div class="col-12 mt-2 pt-2 border-top d-flex gap-1 flex-wrap">
                    <span class="small text-muted me-2 align-self-center">Quick Ranges:</span>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="setRange('today')">Today</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="setRange('yesterday')">Yesterday</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="setRange('this_week')">This Week</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="setRange('this_month')">This Month</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="setRange('last_month')">Last Month</button>
                </div>
            </form>
        </div>
    </div>


    <!-- Sales Table Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-receipt text-primary me-2"></i>Invoice Breakdown (<?php echo $invoices->num_rows; ?> Records)
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="salesTable">
                <thead class="table-light">
                    <tr class="text-muted small text-uppercase">
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Booker</th>
                        <th>Route</th>
                        <th>Method</th>
                        <th class="text-end">Gross Total</th>
                        <th class="text-end">Discount</th>
                        <th class="text-end">Net Total</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Balance</th>
                        <th class="text-center d-print-none">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($invoices->num_rows > 0): ?>
                        <?php while ($row = $invoices->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold text-primary">
                                    <a href="<?php echo BASE_URL; ?>modules/sale/view_sale.php?id=<?php echo $row['id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($row['invoice_no'] ?: 'INV-'.$row['id']); ?>
                                    </a>
                                </td>
                                <td><?php echo date('d-m-Y', strtotime($row['invoice_date'])); ?></td>
                                <td class="fw-semibold text-dark"><?php echo htmlspecialchars($row['customer_name'] ?: 'Walk-in'); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['booker_name'] ?: 'Direct'); ?></span></td>
                                <td><small class="text-muted"><?php echo htmlspecialchars($row['route_name'] ?: '-'); ?></small></td>
                                <td>
                                    <?php 
                                        $method = $row['payment_method'] ?: 'Cash';
                                        $badge_cls = ($method == 'Cash') ? 'bg-success' : (($method == 'Credit') ? 'bg-warning text-dark' : 'bg-info text-dark');
                                    ?>
                                    <span class="badge <?php echo $badge_cls; ?>"><?php echo htmlspecialchars($method); ?></span>
                                </td>
                                <td class="text-end text-muted">Rs. <?php echo number_format($row['subtotal'], 2); ?></td>
                                <td class="text-end text-warning">Rs. <?php echo number_format($row['discount_amount'], 2); ?></td>
                                <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($row['grand_total'], 2); ?></td>
                                <td class="text-end text-success">Rs. <?php echo number_format($row['paid_amount'], 2); ?></td>
                                <td class="text-end fw-bold <?php echo ($row['balance_due'] > 0) ? 'text-danger' : 'text-muted'; ?>">
                                    Rs. <?php echo number_format($row['balance_due'], 2); ?>
                                </td>
                                <td class="text-center d-print-none">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo BASE_URL; ?>modules/sale/view_sale.php?id=<?php echo $row['id']; ?>" class="btn btn-outline-secondary" title="View Invoice">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <a href="<?php echo BASE_URL; ?>modules/sale/print_invoice.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn btn-outline-primary" title="Print Invoice">
                                            <i class="fa-solid fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="12" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-folder-open fs-2 mb-2 d-block text-secondary opacity-50"></i>
                                No sales invoices found for the selected criteria.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <?php if ($invoices->num_rows > 0): ?>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="6" class="text-end">Totals:</td>
                        <td class="text-end">Rs. <?php echo number_format($summary['total_gross'], 2); ?></td>
                        <td class="text-end text-warning">Rs. <?php echo number_format($summary['total_discount'], 2); ?></td>
                        <td class="text-end text-success">Rs. <?php echo number_format($summary['total_net'], 2); ?></td>
                        <td class="text-end text-primary">Rs. <?php echo number_format($summary['total_paid'], 2); ?></td>
                        <td class="text-end text-danger">Rs. <?php echo number_format($summary['total_due'], 2); ?></td>
                        <td class="d-print-none"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
    </div> <!-- End #printableReportArea -->
</div> <!-- End .content-wrapper -->

<script>
function setRange(type) {
    const today = new Date();
    let start = new Date();
    let end = new Date();

    const formatDate = (d) => {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    if (type === 'today') {
        // start and end are today
    } else if (type === 'yesterday') {
        start.setDate(today.getDate() - 1);
        end.setDate(today.getDate() - 1);
    } else if (type === 'this_week') {
        const dayOfWeek = today.getDay(); // 0 is Sunday
        const diff = today.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1); // Monday
        start.setDate(diff);
    } else if (type === 'this_month') {
        start = new Date(today.getFullYear(), today.getMonth(), 1);
    } else if (type === 'last_month') {
        start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        end = new Date(today.getFullYear(), today.getMonth(), 0);
    }

    document.querySelector('input[name="start_date"]').value = formatDate(start);
    document.querySelector('input[name="end_date"]').value = formatDate(end);
    document.getElementById('filterForm').submit();
}

function exportTableToCSV(filename) {
    let csv = [];
    let rows = document.querySelectorAll("#salesTable tr");
    
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll("td, th");
        // Exclude last column (Action)
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
