<?php
/**
 * Bestway Wholesale Distribution - Supplier Ledger & Balance Sheet Report
 */
$page_title = "Supplier Ledger Report";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

if (!$pdo) {
    die("<div class='p-4 text-danger'>Database connection unavailable.</div>");
}

$supplier_id = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : 0;
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-d');
$only_balance = isset($_GET['only_balance']) ? 1 : 0;

// Fetch all suppliers for dropdown
$suppliers_all = $pdo->query("SELECT id, supplier_code, name, company_name, phone, current_balance FROM suppliers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$selected_supplier = null;
$ledger_entries = [];
$opening_balance = 0.0;
$total_purchases_credit = 0.0;
$total_payments_debit = 0.0;
$closing_balance = 0.0;

if ($supplier_id > 0) {
    // Single Supplier Mode
    syncSupplierLedger($pdo, $supplier_id);
    $stmt_s = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt_s->execute([$supplier_id]);
    $selected_supplier = $stmt_s->fetch(PDO::FETCH_ASSOC);

    if ($selected_supplier) {
        // Calculate Opening Payable Balance prior to start_date
        $stmt_op = $pdo->prepare("SELECT 
            COALESCE(SUM(credit_amount), 0) - COALESCE(SUM(debit_amount), 0) as net_prior
            FROM supplier_ledgers 
            WHERE supplier_id = ? AND transaction_date < ?");
        $stmt_op->execute([$supplier_id, $start_date]);
        $prior_net = (float)($stmt_op->fetchColumn() ?: 0);
        $opening_balance = (float)$selected_supplier['opening_balance'] + $prior_net;

        // Fetch ledger entries in date range
        $stmt_l = $pdo->prepare("SELECT * FROM supplier_ledgers 
                                 WHERE supplier_id = ? AND transaction_date BETWEEN ? AND ? 
                                 ORDER BY transaction_date ASC, id ASC");
        $stmt_l->execute([$supplier_id, $start_date, $end_date]);
        $raw_entries = $stmt_l->fetchAll(PDO::FETCH_ASSOC);

        $running = $opening_balance;
        foreach ($raw_entries as $entry) {
            $deb = (float)$entry['debit_amount'];  // Payment made
            $crd = (float)$entry['credit_amount']; // Purchase bill
            $running += ($crd - $deb);
            $entry['calc_balance'] = $running;
            $ledger_entries[] = $entry;

            $total_purchases_credit += $crd;
            $total_payments_debit += $deb;
        }
        $closing_balance = $running;
    }
} else {
    // All Suppliers / Balance Sheet Mode
    $where_sheet = ["1=1"];
    if ($only_balance) {
        $where_sheet[] = "s.current_balance != 0";
    }
    $where_sheet_sql = implode(" AND ", $where_sheet);

    $stmt_sheet = $pdo->query("SELECT 
        s.id, s.supplier_code, s.name, s.company_name, s.phone, s.opening_balance, s.current_balance, s.status,
        COALESCE((SELECT SUM(credit_amount) FROM supplier_ledgers WHERE supplier_id = s.id), 0) as total_credited,
        COALESCE((SELECT SUM(debit_amount) FROM supplier_ledgers WHERE supplier_id = s.id), 0) as total_debited
    FROM suppliers s
    WHERE {$where_sheet_sql}
    ORDER BY s.current_balance DESC, s.name ASC");
    $supplier_balances = $stmt_sheet->fetchAll(PDO::FETCH_ASSOC);

    $summary_total_suppliers = count($supplier_balances);
    $summary_total_payables = array_sum(array_column($supplier_balances, 'current_balance'));
    $summary_total_purchases = array_sum(array_column($supplier_balances, 'total_credited'));
    $summary_total_payments = array_sum(array_column($supplier_balances, 'total_debited'));
}
?>

<div class="content-wrapper p-3 p-md-4">
<?php
$report_title       = $selected_supplier ? "Supplier Ledger Statement" : "Supplier Balance Sheet & Payables";
$report_subtitle    = $selected_supplier ? ($selected_supplier['name'] . ($selected_supplier['company_name'] ? " (" . $selected_supplier['company_name'] . ")" : "")) : "All Vendors & Suppliers Summary";
$report_period      = date('d M Y', strtotime($start_date)) . " to " . date('d M Y', strtotime($end_date));
$report_filename    = ($selected_supplier ? "supplier_ledger_" . $selected_supplier['id'] : "supplier_balance_sheet") . "_" . date('Ymd');
$report_orientation = $selected_supplier ? "portrait" : "landscape";
?>

    <!-- Header Actions -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 d-print-none">
        <div>
            <h4 class="fw-bold mb-1"><i class="fa-solid fa-truck-ramp-box text-warning me-2"></i>Supplier Ledger Report</h4>
            <span class="text-muted small">Vendor account statements, inward purchase bills, payments made, and accounts payable.</span>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-secondary btn-sm shadow-sm">
                <i class="fa-solid fa-print me-1"></i> Print Statement
            </button>
            <button onclick="exportReportToPDF('#printableReportArea', '<?php echo $report_filename; ?>', '<?php echo $report_orientation; ?>')" class="btn btn-outline-danger btn-sm shadow-sm">
                <i class="fa-solid fa-file-pdf me-1"></i> Export PDF
            </button>
            <button onclick="exportTableToCSV('supplier_ledger_<?php echo date('Ymd'); ?>.csv')" class="btn btn-outline-success btn-sm shadow-sm">
                <i class="fa-solid fa-file-excel me-1"></i> Export CSV
            </button>
            <a href="<?php echo BASE_URL; ?>modules/supplier/pay_amount.php" class="btn btn-success btn-sm shadow-sm">
                <i class="fa-solid fa-money-bill-alt me-1"></i> Make Payment
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
                <div class="col-md-5 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">Select Supplier</label>
                    <select name="supplier_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="0">-- All Suppliers (Balance Sheet View) --</option>
                        <?php foreach ($suppliers_all as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo ($supplier_id == $s['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['name'] . ($s['company_name'] ? " ({$s['company_name']})" : '')); ?> 
                                (Payable: Rs. <?php echo number_format($s['current_balance'], 0); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($supplier_id == 0): ?>
                    <div class="col-md-3 col-sm-6">
                        <div class="form-check form-switch mt-4">
                            <input class="form-check-input" type="checkbox" name="only_balance" id="onlyBalSwitch" value="1" <?php echo $only_balance ? 'checked' : ''; ?>>
                            <label class="form-check-label small fw-semibold" for="onlyBalSwitch">Only With Payable Dues</label>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label small fw-semibold mb-1">Date From</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label small fw-semibold mb-1">Date To</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                <?php endif; ?>

                <div class="col-md-2 col-sm-6 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                    <a href="supplier_ledger_report.php" class="btn btn-light btn-sm border" title="Reset View">
                        <i class="fa-solid fa-undo"></i>
                    </a>
                </div>

                <?php if ($supplier_id > 0): ?>
                <div class="col-12 mt-2 pt-2 border-top d-flex gap-1 flex-wrap">
                    <span class="small text-muted me-2 align-self-center">Quick Ranges:</span>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="setRange('today')">Today</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="setRange('this_month')">This Month</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="setRange('last_month')">Last Month</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="setRange('this_year')">This Year</button>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if ($selected_supplier): ?>
        <!-- SINGLE SUPPLIER LEDGER STATEMENT -->
        <div class="card border-0 shadow-sm mb-3 bg-light">
            <div class="card-body p-3">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 46px; height: 46px; font-size: 1.2rem;">
                                <i class="fa-solid fa-building"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($selected_supplier['name']); ?></h5>
                                <div class="text-muted small">
                                    Company: <strong class="text-dark"><?php echo htmlspecialchars($selected_supplier['company_name'] ?: 'N/A'); ?></strong> | 
                                    Code: <span class="badge bg-secondary"><?php echo htmlspecialchars($selected_supplier['supplier_code'] ?: 'S-'.$selected_supplier['id']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 text-md-end mt-2 mt-md-0">
                        <span class="small text-muted d-block">Phone: <?php echo htmlspecialchars($selected_supplier['phone'] ?: 'N/A'); ?></span>
                        <span class="small text-muted d-block">Address: <?php echo htmlspecialchars($selected_supplier['address'] ?: 'N/A'); ?></span>
                    </div>
                </div>
            </div>
        </div>


        <!-- Ledger Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-list-check text-success me-2"></i>Vendor Ledger Transactions (<?php echo count($ledger_entries); ?> Records)
                </h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="reportTable">
                    <thead class="table-light">
                        <tr class="text-muted small text-uppercase">
                            <th>Date</th>
                            <th>Reference / Bill #</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th class="text-end">Payments Made (Dr)</th>
                            <th class="text-end">Purchases / Inward (Cr)</th>
                            <th class="text-end">Payable Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Opening Balance Row -->
                        <tr class="table-light fw-semibold">
                            <td><span class="badge bg-light text-dark border">B/F</span> <?php echo date('d-m-Y', strtotime($start_date)); ?></td>
                            <td><em>OP-BAL</em></td>
                            <td><span class="badge bg-secondary">B/F Balance</span></td>
                            <td>Opening Balance B/F (Prior to <?php echo date('d-m-Y', strtotime($start_date)); ?>)</td>
                            <td class="text-end">-</td>
                            <td class="text-end">-</td>
                            <td class="text-end fw-bold">Rs. <?php echo number_format($opening_balance, 2); ?></td>
                        </tr>

                        <?php if (!empty($ledger_entries)): ?>
                            <?php foreach ($ledger_entries as $row): ?>
                                <tr>
                                    <td><?php echo date('d-m-Y', strtotime($row['transaction_date'])); ?></td>
                                    <td class="fw-semibold text-primary"><?php echo htmlspecialchars($row['reference_no'] ?: '-'); ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['transaction_type'] ?: 'Purchase'); ?></span>
                                    </td>
                                    <td><small class="text-muted"><?php echo htmlspecialchars($row['description'] ?: '-'); ?></small></td>
                                    <td class="text-end <?php echo ($row['debit_amount'] > 0) ? 'text-success fw-semibold' : 'text-muted'; ?>">
                                        <?php echo ($row['debit_amount'] > 0) ? 'Rs. ' . number_format($row['debit_amount'], 2) : '-'; ?>
                                    </td>
                                    <td class="text-end <?php echo ($row['credit_amount'] > 0) ? 'text-primary fw-semibold' : 'text-muted'; ?>">
                                        <?php echo ($row['credit_amount'] > 0) ? 'Rs. ' . number_format($row['credit_amount'], 2) : '-'; ?>
                                    </td>
                                    <td class="text-end fw-bold <?php echo ($row['calc_balance'] > 0) ? 'text-danger' : 'text-success'; ?>">
                                        Rs. <?php echo number_format($row['calc_balance'], 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    No new transactions occurred with this supplier in the selected date range.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="4" class="text-end">Period Totals:</td>
                            <td class="text-end text-success">Rs. <?php echo number_format($total_payments_debit, 2); ?></td>
                            <td class="text-end text-primary">Rs. <?php echo number_format($total_purchases_credit, 2); ?></td>
                            <td class="text-end text-danger fs-6">Rs. <?php echo number_format($closing_balance, 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

    <?php else: ?>

        <!-- Supplier Balance Sheet Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-scale-balanced text-success me-2"></i>Supplier Balance Sheet (<?php echo count($supplier_balances); ?> Suppliers)
                </h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="reportTable">
                    <thead class="table-light">
                        <tr class="text-muted small text-uppercase">
                            <th>Code</th>
                            <th>Supplier Name / Company</th>
                            <th>Phone</th>
                            <th class="text-end">Opening Bal</th>
                            <th class="text-end">Total Purchases</th>
                            <th class="text-end">Total Paid</th>
                            <th class="text-end">Current Payable</th>
                            <th class="text-center d-print-none">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($supplier_balances)): ?>
                            <?php foreach ($supplier_balances as $row): ?>
                                <tr>
                                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['supplier_code'] ?: 'S-'.$row['id']); ?></span></td>
                                    <td>
                                        <a href="supplier_ledger_report.php?supplier_id=<?php echo $row['id']; ?>" class="fw-bold text-decoration-none text-dark">
                                            <?php echo htmlspecialchars($row['name']); ?>
                                        </a>
                                        <?php if ($row['company_name']): ?>
                                            <div class="small text-muted"><?php echo htmlspecialchars($row['company_name']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?php echo htmlspecialchars($row['phone'] ?: '-'); ?></small></td>
                                    <td class="text-end text-muted">Rs. <?php echo number_format($row['opening_balance'], 2); ?></td>
                                    <td class="text-end text-primary">Rs. <?php echo number_format($row['total_credited'], 2); ?></td>
                                    <td class="text-end text-success">Rs. <?php echo number_format($row['total_debited'], 2); ?></td>
                                    <td class="text-end fw-bold <?php echo ($row['current_balance'] > 0) ? 'text-danger' : 'text-success'; ?>">
                                        Rs. <?php echo number_format($row['current_balance'], 2); ?>
                                    </td>
                                    <td class="text-center d-print-none">
                                        <a href="supplier_ledger_report.php?supplier_id=<?php echo $row['id']; ?>" class="btn btn-outline-success btn-sm" title="View Detailed Statement">
                                            <i class="fa-solid fa-file-lines me-1"></i> Ledger
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    No suppliers found matching the criteria.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="3" class="text-end">Total Outstanding Payables:</td>
                            <td class="text-end">-</td>
                            <td class="text-end text-primary">Rs. <?php echo number_format($summary_total_purchases, 2); ?></td>
                            <td class="text-end text-success">Rs. <?php echo number_format($summary_total_payments, 2); ?></td>
                            <td class="text-end text-danger fs-6">Rs. <?php echo number_format($summary_total_payables, 2); ?></td>
                            <td class="d-print-none"></td>
                        </tr>
                    </tfoot>
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
    } else if (type === 'this_month') {
        start = new Date(today.getFullYear(), today.getMonth(), 1);
    } else if (type === 'last_month') {
        start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        end = new Date(today.getFullYear(), today.getMonth(), 0);
    } else if (type === 'this_year') {
        start = new Date(today.getFullYear(), 0, 1);
    }

    if (document.querySelector('input[name="start_date"]')) {
        document.querySelector('input[name="start_date"]').value = formatDate(start);
        document.querySelector('input[name="end_date"]').value = formatDate(end);
        document.getElementById('filterForm').submit();
    }
}

function exportTableToCSV(filename) {
    let csv = [];
    let rows = document.querySelectorAll("#reportTable tr");
    
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
