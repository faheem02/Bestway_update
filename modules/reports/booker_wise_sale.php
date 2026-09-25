<?php
/**
 * Bestway Wholesale Distribution - Booker Wise Sales Report
 */
$page_title = "Booker Wise Sales Report";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

// Database connection for sales

// Get filter params
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-d');
$booker_id = isset($_GET['booker_id']) ? trim($_GET['booker_id']) : '';

// Build where clause
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

$where_sql = implode(" AND ", $where_clauses);

// Fetch summary metrics
$summary_sql = "SELECT 
    COUNT(DISTINCT si.booker_id) as active_bookers,
    COUNT(*) as total_orders,
    COALESCE(SUM(si.subtotal), 0) as total_gross,
    COALESCE(SUM(si.discount_amount), 0) as total_discount,
    COALESCE(SUM(si.grand_total), 0) as total_net,
    COALESCE(SUM(si.paid_amount), 0) as total_paid,
    COALESCE(SUM(si.balance_due), 0) as total_due
FROM sales_invoices si
WHERE {$where_sql}";

$stmt_sum = $conn->prepare($summary_sql);
if (!empty($params)) {
    $stmt_sum->bind_param($types, ...$params);
}
$stmt_sum->execute();
$summary = $stmt_sum->get_result()->fetch_assoc();
$stmt_sum->close();

// Fetch booker-wise aggregated totals
$booker_agg_sql = "SELECT 
    COALESCE(NULLIF(si.booker_name, ''), 'Direct Sales') as b_name,
    si.booker_id,
    COUNT(*) as order_count,
    COALESCE(SUM(si.subtotal), 0) as gross_amount,
    COALESCE(SUM(si.discount_amount), 0) as discount_amount,
    COALESCE(SUM(si.grand_total), 0) as net_amount,
    COALESCE(SUM(si.paid_amount), 0) as paid_amount,
    COALESCE(SUM(si.balance_due), 0) as due_amount
FROM sales_invoices si
WHERE {$where_sql}
GROUP BY si.booker_id, si.booker_name
ORDER BY net_amount DESC";

$stmt_agg = $conn->prepare($booker_agg_sql);
if (!empty($params)) {
    $stmt_agg->bind_param($types, ...$params);
}
$stmt_agg->execute();
$booker_summaries = $stmt_agg->get_result();
$stmt_agg->close();

// If single booker selected or detailed drill-down
$invoices_list = null;
if (!empty($booker_id)) {
    $inv_sql = "SELECT 
        si.id, si.invoice_no, si.customer_name, si.invoice_date, si.payment_method,
        si.route_name, si.grand_total, si.paid_amount, si.balance_due
    FROM sales_invoices si
    WHERE {$where_sql}
    ORDER BY si.invoice_date DESC, si.id DESC";
    $stmt_inv = $conn->prepare($inv_sql);
    $stmt_inv->bind_param($types, ...$params);
    $stmt_inv->execute();
    $invoices_list = $stmt_inv->get_result();
    $stmt_inv->close();
}

// Fetch bookers from bestway_wholesale for dropdown
$bookers_dropdown = [];
if ($pdo) {
    try {
        $bookers_dropdown = $pdo->query("SELECT id, full_name AS name, phone FROM employees WHERE employee_type = 'salesman' AND status = 1 ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}
?>

<div class="content-wrapper p-3 p-md-4">
<?php
$report_title       = "Booker Wise Sales Report";
$report_subtitle    = "Salesman Performance, Sales Dues & Recovery Audit";
$report_period      = date('d M Y', strtotime($start_date)) . " to " . date('d M Y', strtotime($end_date));
$report_filename    = "booker_report_" . date('Ymd');
$report_orientation = "landscape";
?>

    <!-- Header Actions -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 d-print-none">
        <div>
            <h4 class="fw-bold mb-1"><i class="fa-solid fa-user-tag text-info me-2"></i>Booker Wise Sales Report</h4>
            <span class="text-muted small">Sales performance, orders booked, cash collections, and dues per order booker.</span>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-secondary btn-sm shadow-sm">
                <i class="fa-solid fa-print me-1"></i> Print Report
            </button>
            <button onclick="exportReportToPDF('#printableReportArea', '<?php echo $report_filename; ?>', 'landscape')" class="btn btn-outline-danger btn-sm shadow-sm">
                <i class="fa-solid fa-file-pdf me-1"></i> Export PDF
            </button>
            <button onclick="exportTableToCSV('booker_report_<?php echo date('Ymd'); ?>.csv')" class="btn btn-outline-success btn-sm shadow-sm">
                <i class="fa-solid fa-file-excel me-1"></i> Export CSV
            </button>
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
                    <label class="form-label small fw-semibold mb-1">Date From</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">Date To</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="col-md-4 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">Select Salesman</label>
                    <select name="booker_id" class="form-select form-select-sm">
                        <option value="">All Bookers (Performance Comparison)</option>
                        <?php foreach ($bookers_dropdown as $b): ?>
                            <option value="<?php echo $b['id']; ?>" <?php echo ($booker_id == $b['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($b['name'] . ($b['phone'] ? " ({$b['phone']})" : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                    <a href="booker_wise_sale.php" class="btn btn-light btn-sm border" title="Reset Filters">
                        <i class="fa-solid fa-undo"></i>
                    </a>
                </div>
                <!-- Quick Date Presets -->
                <div class="col-12 mt-2 pt-2 border-top d-flex gap-1 flex-wrap">
                    <span class="small text-muted me-2 align-self-center">Quick Ranges:</span>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="setRange('today')">Today</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="setRange('this_week')">This Week</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="setRange('this_month')">This Month</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="setRange('last_month')">Last Month</button>
                </div>
            </form>
        </div>
    </div>


    <!-- Booker Summary Table -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-users-gear text-info me-2"></i>Booker Performance Summary
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="bookerSummaryTable">
                <thead class="table-light">
                    <tr class="text-muted small text-uppercase">
                        <th>Booker Name</th>
                        <th class="text-center">Orders Count</th>
                        <th class="text-end">Gross Sales</th>
                        <th class="text-end">Discount</th>
                        <th class="text-end">Net Sales</th>
                        <th class="text-end">Collected</th>
                        <th class="text-end">Outstanding Due</th>
                        <th class="text-center">Recovery %</th>
                        <th class="text-center d-print-none">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($booker_summaries->num_rows > 0): ?>
                        <?php while ($b = $booker_summaries->fetch_assoc()): 
                            $recovery_rate = ($b['net_amount'] > 0) ? ($b['paid_amount'] / $b['net_amount'] * 100) : 0;
                        ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($b['b_name']); ?></div>
                                    <?php if ($b['booker_id']): ?>
                                        <small class="text-muted">ID: #<?php echo $b['booker_id']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary"><?php echo $b['order_count']; ?></span>
                                </td>
                                <td class="text-end text-muted">Rs. <?php echo number_format($b['gross_amount'], 2); ?></td>
                                <td class="text-end text-warning">Rs. <?php echo number_format($b['discount_amount'], 2); ?></td>
                                <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($b['net_amount'], 2); ?></td>
                                <td class="text-end text-success fw-semibold">Rs. <?php echo number_format($b['paid_amount'], 2); ?></td>
                                <td class="text-end fw-bold <?php echo ($b['due_amount'] > 0) ? 'text-danger' : 'text-muted'; ?>">
                                    Rs. <?php echo number_format($b['due_amount'], 2); ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 6px; width: 60px;">
                                            <div class="progress-bar bg-success" style="width: <?php echo min(100, $recovery_rate); ?>%"></div>
                                        </div>
                                        <small class="fw-semibold"><?php echo number_format($recovery_rate, 1); ?>%</small>
                                    </div>
                                </td>
                                <td class="text-center d-print-none">
                                    <?php if ($b['booker_id']): ?>
                                        <a href="booker_wise_sale.php?booker_id=<?php echo $b['booker_id']; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="btn btn-outline-primary btn-sm" title="View Invoices Drill-down">
                                            <i class="fa-solid fa-list me-1"></i> Invoices
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-folder-open fs-2 mb-2 d-block text-secondary opacity-50"></i>
                                No booking data found for the selected dates.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <?php if ($booker_summaries->num_rows > 0): ?>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td>Grand Totals:</td>
                        <td class="text-center"><?php echo number_format($summary['total_orders']); ?></td>
                        <td class="text-end">Rs. <?php echo number_format($summary['total_gross'], 2); ?></td>
                        <td class="text-end text-warning">Rs. <?php echo number_format($summary['total_discount'], 2); ?></td>
                        <td class="text-end text-dark">Rs. <?php echo number_format($summary['total_net'], 2); ?></td>
                        <td class="text-end text-success">Rs. <?php echo number_format($summary['total_paid'], 2); ?></td>
                        <td class="text-end text-danger">Rs. <?php echo number_format($summary['total_due'], 2); ?></td>
                        <td colspan="2" class="d-print-none"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <!-- If Single Booker Selected: Invoice Drilldown Table -->
    <?php if ($invoices_list): ?>
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-file-invoice text-primary me-2"></i>Invoices Booked (<?php echo $invoices_list->num_rows; ?> Records)
                </h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="text-muted small text-uppercase">
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Route</th>
                            <th>Payment Method</th>
                            <th class="text-end">Net Total</th>
                            <th class="text-end">Paid</th>
                            <th class="text-end">Balance</th>
                            <th class="text-center d-print-none">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($invoices_list->num_rows > 0): ?>
                            <?php while ($inv = $invoices_list->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold text-primary">
                                        <a href="<?php echo BASE_URL; ?>modules/sale/view_sale.php?id=<?php echo $inv['id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($inv['invoice_no'] ?: 'INV-'.$inv['id']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo date('d-m-Y', strtotime($inv['invoice_date'])); ?></td>
                                    <td class="fw-semibold text-dark"><?php echo htmlspecialchars($inv['customer_name'] ?: 'Walk-in'); ?></td>
                                    <td><small class="text-muted"><?php echo htmlspecialchars($inv['route_name'] ?: '-'); ?></small></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($inv['payment_method'] ?: 'Cash'); ?></span></td>
                                    <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($inv['grand_total'], 2); ?></td>
                                    <td class="text-end text-success">Rs. <?php echo number_format($inv['paid_amount'], 2); ?></td>
                                    <td class="text-end fw-bold <?php echo ($inv['balance_due'] > 0) ? 'text-danger' : 'text-muted'; ?>">
                                        Rs. <?php echo number_format($inv['balance_due'], 2); ?>
                                    </td>
                                    <td class="text-center d-print-none">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?php echo BASE_URL; ?>modules/sale/view_sale.php?id=<?php echo $inv['id']; ?>" class="btn btn-outline-secondary" title="View">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>modules/sale/print_invoice.php?id=<?php echo $inv['id']; ?>" target="_blank" class="btn btn-outline-primary" title="Print">
                                                <i class="fa-solid fa-print"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    No invoices booked for this booker in the chosen date range.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
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
        // today
    } else if (type === 'this_week') {
        const dayOfWeek = today.getDay();
        const diff = today.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1);
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
    let rows = document.querySelectorAll("#bookerSummaryTable tr");
    
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
