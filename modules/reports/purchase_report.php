<?php
/**
 * Bestway Wholesale Distribution - Purchase Report
 */
$page_title = "Purchase Report";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

if (!$pdo) {
    die("<div class='p-4 text-danger'>Database connection unavailable.</div>");
}

// Get filter params
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-d');
$supplier_id = isset($_GET['supplier_id']) ? trim($_GET['supplier_id']) : '';
$payment_status = isset($_GET['payment_status']) ? trim($_GET['payment_status']) : '';
$payment_type = isset($_GET['payment_type']) ? trim($_GET['payment_type']) : '';

// Build query
$where_clauses = ["1=1"];
$params = [];

if (!empty($start_date)) {
    $where_clauses[] = "p.purchase_date >= :start_date";
    $params[':start_date'] = $start_date;
}
if (!empty($end_date)) {
    $where_clauses[] = "p.purchase_date <= :end_date";
    $params[':end_date'] = $end_date;
}
if (!empty($supplier_id)) {
    $where_clauses[] = "p.supplier_id = :supplier_id";
    $params[':supplier_id'] = $supplier_id;
}
if (!empty($payment_status)) {
    $where_clauses[] = "p.payment_status = :payment_status";
    $params[':payment_status'] = $payment_status;
}
if (!empty($payment_type)) {
    $where_clauses[] = "p.payment_type = :payment_type";
    $params[':payment_type'] = $payment_type;
}

$where_sql = implode(" AND ", $where_clauses);

// Summary metrics
$summary_stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_bills,
    COALESCE(SUM(p.subtotal), 0) as total_gross,
    COALESCE(SUM(p.discount_amount), 0) as total_discount,
    COALESCE(SUM(p.grand_total), 0) as total_net,
    COALESCE(SUM(p.paid_amount), 0) as total_paid,
    COALESCE(SUM(p.balance_amount), 0) as total_due
FROM purchases p
WHERE {$where_sql}");
$summary_stmt->execute($params);
$summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);

// Fetch purchases with supplier info and item counts
$list_stmt = $pdo->prepare("SELECT 
    p.id, p.bill_no, p.purchase_date, p.payment_type, p.payment_status,
    p.subtotal, p.discount_amount, p.grand_total, p.paid_amount, p.balance_amount,
    s.name as supplier_name, s.company_name,
    (SELECT COUNT(*) FROM purchase_items pi WHERE pi.purchase_id = p.id) as item_count
FROM purchases p
LEFT JOIN suppliers s ON p.supplier_id = s.id
WHERE {$where_sql}
ORDER BY p.purchase_date DESC, p.id DESC");
$list_stmt->execute($params);
$purchases = $list_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch suppliers for dropdown
$suppliers = $pdo->query("SELECT id, name, company_name FROM suppliers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content-wrapper p-3 p-md-4">
<?php
$report_title       = "Purchases & Payables Report";
$report_subtitle    = "Supplier Bills, Inventory Purchases & Payables Audit";
$report_period      = date('d M Y', strtotime($start_date)) . " to " . date('d M Y', strtotime($end_date));
$report_filename    = "purchase_report_" . date('Ymd');
$report_orientation = "landscape";
?>

    <!-- Header Actions -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 d-print-none">
        <div>
            <h4 class="fw-bold mb-1"><i class="fa-solid fa-cart-flatbed text-success me-2"></i>Purchase Report</h4>
            <span class="text-muted small">Overview of supplier procurement, inbound bills, payments, and payable balances.</span>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-secondary btn-sm shadow-sm">
                <i class="fa-solid fa-print me-1"></i> Print Report
            </button>
            <button onclick="exportReportToPDF('#printableReportArea', '<?php echo $report_filename; ?>', 'landscape')" class="btn btn-outline-danger btn-sm shadow-sm">
                <i class="fa-solid fa-file-pdf me-1"></i> Export PDF
            </button>
            <button onclick="exportTableToCSV('purchase_report_<?php echo date('Ymd'); ?>.csv')" class="btn btn-outline-success btn-sm shadow-sm">
                <i class="fa-solid fa-file-excel me-1"></i> Export CSV
            </button>
            <a href="<?php echo BASE_URL; ?>modules/purchase/add_purchase.php" class="btn btn-success btn-sm shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> New Purchase Bill
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
                <div class="col-md-3 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">Supplier</label>
                    <select name="supplier_id" class="form-select form-select-sm">
                        <option value="">All Suppliers</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo ($supplier_id == $s['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['name'] . ($s['company_name'] ? " ({$s['company_name']})" : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">Payment Status</label>
                    <select name="payment_status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="Paid" <?php echo ($payment_status == 'Paid') ? 'selected' : ''; ?>>Paid</option>
                        <option value="Partial" <?php echo ($payment_status == 'Partial') ? 'selected' : ''; ?>>Partial</option>
                        <option value="Unpaid" <?php echo ($payment_status == 'Unpaid') ? 'selected' : ''; ?>>Unpaid</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">Payment Type</label>
                    <select name="payment_type" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        <option value="Cash" <?php echo ($payment_type == 'Cash') ? 'selected' : ''; ?>>Cash</option>
                        <option value="Credit" <?php echo ($payment_type == 'Credit') ? 'selected' : ''; ?>>Credit</option>
                        <option value="Bank" <?php echo ($payment_type == 'Bank') ? 'selected' : ''; ?>>Bank</option>
                    </select>
                </div>
                <div class="col-md-1 col-sm-6 d-flex gap-1">
                    <button type="submit" class="btn btn-success btn-sm flex-fill" title="Apply Filter">
                        <i class="fa-solid fa-filter"></i>
                    </button>
                    <a href="purchase_report.php" class="btn btn-light btn-sm border" title="Reset Filters">
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


    <!-- Purchases Table Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-file-invoice text-success me-2"></i>Purchase Bills Breakdown (<?php echo count($purchases); ?> Records)
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="purchaseTable">
                <thead class="table-light">
                    <tr class="text-muted small text-uppercase">
                        <th>Bill #</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Payment Type</th>
                        <th class="text-center">Items</th>
                        <th class="text-end">Gross Amount</th>
                        <th class="text-end">Discount</th>
                        <th class="text-end">Net Total</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Balance</th>
                        <th class="text-center">Status</th>
                        <th class="text-center d-print-none">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($purchases)): ?>
                        <?php foreach ($purchases as $row): ?>
                            <tr>
                                <td class="fw-bold text-success">
                                    <a href="<?php echo BASE_URL; ?>modules/purchase/view_purchase.php?id=<?php echo $row['id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($row['bill_no']); ?>
                                    </a>
                                </td>
                                <td><?php echo date('d-m-Y', strtotime($row['purchase_date'])); ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($row['supplier_name'] ?: 'Unknown'); ?></div>
                                    <?php if ($row['company_name']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($row['company_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['payment_type']); ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary"><?php echo $row['item_count']; ?></span>
                                </td>
                                <td class="text-end text-muted">Rs. <?php echo number_format($row['subtotal'], 2); ?></td>
                                <td class="text-end text-success">Rs. <?php echo number_format($row['discount_amount'], 2); ?></td>
                                <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($row['grand_total'], 2); ?></td>
                                <td class="text-end text-primary">Rs. <?php echo number_format($row['paid_amount'], 2); ?></td>
                                <td class="text-end fw-bold <?php echo ($row['balance_amount'] > 0) ? 'text-danger' : 'text-muted'; ?>">
                                    Rs. <?php echo number_format($row['balance_amount'], 2); ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                        $st = $row['payment_status'];
                                        $badge = ($st == 'Paid') ? 'bg-success' : (($st == 'Partial') ? 'bg-warning text-dark' : 'bg-danger');
                                    ?>
                                    <span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars($st); ?></span>
                                </td>
                                <td class="text-center d-print-none">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo BASE_URL; ?>modules/purchase/view_purchase.php?id=<?php echo $row['id']; ?>" class="btn btn-outline-secondary" title="View Purchase">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <a href="<?php echo BASE_URL; ?>modules/purchase/print_purchase.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn btn-outline-success" title="Print Bill">
                                            <i class="fa-solid fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="12" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-folder-open fs-2 mb-2 d-block text-secondary opacity-50"></i>
                                No purchase bills found for the selected criteria.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($purchases)): ?>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="5" class="text-end">Totals:</td>
                        <td class="text-end">Rs. <?php echo number_format($summary['total_gross'], 2); ?></td>
                        <td class="text-end text-success">Rs. <?php echo number_format($summary['total_discount'], 2); ?></td>
                        <td class="text-end text-dark">Rs. <?php echo number_format($summary['total_net'], 2); ?></td>
                        <td class="text-end text-primary">Rs. <?php echo number_format($summary['total_paid'], 2); ?></td>
                        <td class="text-end text-danger">Rs. <?php echo number_format($summary['total_due'], 2); ?></td>
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
        // start & end are today
    } else if (type === 'yesterday') {
        start.setDate(today.getDate() - 1);
        end.setDate(today.getDate() - 1);
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
    let rows = document.querySelectorAll("#purchaseTable tr");
    
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
