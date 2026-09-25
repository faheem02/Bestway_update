<?php
/**
 * Bestway Wholesale Distribution - Profit & Loss (Income Statement) Report
 */
$page_title = "Profit & Loss Statement";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';


$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-d');

// 1. Fetch Sales Revenue from pharma_b2b.sales_invoices
$stmt_sales = $conn->prepare("SELECT 
    COUNT(*) as total_invoices,
    COALESCE(SUM(subtotal), 0) as gross_sales,
    COALESCE(SUM(discount_amount), 0) as sales_discounts,
    COALESCE(SUM(grand_total), 0) as net_sales
FROM sales_invoices 
WHERE invoice_date BETWEEN ? AND ?");
$stmt_sales->bind_param("ss", $start_date, $end_date);
$stmt_sales->execute();
$sales_data = $stmt_sales->get_result()->fetch_assoc();
$stmt_sales->close();

$gross_sales = (float)$sales_data['gross_sales'];
$sales_discounts = (float)$sales_data['sales_discounts'];
$net_sales = (float)$sales_data['net_sales'];

// 2. Fetch Cost of Goods Sold (COGS) from sold items joined with product purchase price
// Using cross-database join if possible, or querying product prices
$cogs = 0.0;
$product_profits = [];

$sold_sql = "SELECT 
    si.product_id,
    si.item_name,
    SUM(si.quantity) as total_qty,
    SUM(si.total_amount) as total_revenue,
    COALESCE(p.purchase_price, 0) as cost_price,
    COALESCE(p.trade_price, 0) as trade_price
FROM sales_invoices inv
JOIN sale_items si ON inv.id = si.invoice_id
LEFT JOIN products p ON si.product_id = p.id
WHERE inv.invoice_date BETWEEN ? AND ?
GROUP BY si.product_id, si.item_name, p.purchase_price, p.trade_price
ORDER BY total_revenue DESC";

$stmt_items = $conn->prepare($sold_sql);
if ($stmt_items) {
    $stmt_items->bind_param("ss", $start_date, $end_date);
    $stmt_items->execute();
    $res_items = $stmt_items->get_result();
    while ($row = $res_items->fetch_assoc()) {
        $qty = (float)$row['total_qty'];
        $rev = (float)$row['total_revenue'];
        $cost_unit = (float)$row['cost_price'];
        
        // If cost price not recorded, fallback to trade price * 0.85
        if ($cost_unit <= 0 && (float)$row['trade_price'] > 0) {
            $cost_unit = (float)$row['trade_price'] * 0.85;
        } elseif ($cost_unit <= 0 && $qty > 0) {
            $cost_unit = ($rev / $qty) * 0.85;
        }

        $line_cost = $qty * $cost_unit;
        $line_profit = $rev - $line_cost;
        $cogs += $line_cost;

        $row['calculated_cost'] = $line_cost;
        $row['calculated_profit'] = $line_profit;
        $row['profit_margin'] = ($rev > 0) ? ($line_profit / $rev * 100) : 0;
        $product_profits[] = $row;
    }
    $stmt_items->close();
}

$gross_profit = $net_sales - $cogs;
$gross_margin_pct = ($net_sales > 0) ? ($gross_profit / $net_sales * 100) : 0;

// 3. Fetch Operating Expenses from bestway_wholesale.expenses
$expenses_by_cat = [];
$total_expenses = 0.0;

if ($pdo) {
    try {
        $stmt_exp = $pdo->prepare("SELECT 
            COALESCE(c.name, 'General Operations') as category_name,
            COUNT(e.id) as voucher_count,
            COALESCE(SUM(e.amount), 0) as total_amount
        FROM expenses e
        LEFT JOIN categories c ON e.category_id = c.id
        WHERE e.expense_date BETWEEN :start_date AND :end_date
        GROUP BY COALESCE(c.name, 'General Operations')
        ORDER BY total_amount DESC");
        $stmt_exp->execute([':start_date' => $start_date, ':end_date' => $end_date]);
        $expenses_by_cat = $stmt_exp->fetchAll(PDO::FETCH_ASSOC);

        foreach ($expenses_by_cat as $exp) {
            $total_expenses += (float)$exp['total_amount'];
        }
    } catch (Exception $e) {}
}

// 4. Net Profit / (Loss)
$net_profit = $gross_profit - $total_expenses;
$net_profit_margin_pct = ($net_sales > 0) ? ($net_profit / $net_sales * 100) : 0;
$is_profitable = ($net_profit >= 0);
?>

<div class="content-wrapper p-3 p-md-4">
<?php
$report_title       = "Profit & Loss Statement";
$report_subtitle    = "Statement of Income, COGS, Overheads & Net Margins";
$report_period      = date('d M Y', strtotime($start_date)) . " to " . date('d M Y', strtotime($end_date));
$report_filename    = "profit_loss_" . date('Ymd');
$report_orientation = "portrait";
?>

    <!-- Header Actions -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 d-print-none">
        <div>
            <h4 class="fw-bold mb-1"><i class="fa-solid fa-scale-balanced text-primary me-2"></i>Profit & Loss Statement</h4>
            <span class="text-muted small">Financial performance summary, cost of goods sold, operating overheads, and net margins.</span>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-secondary btn-sm shadow-sm">
                <i class="fa-solid fa-print me-1"></i> Print Statement
            </button>
            <button onclick="exportReportToPDF('#printableReportArea', '<?php echo $report_filename; ?>', 'portrait')" class="btn btn-outline-danger btn-sm shadow-sm">
                <i class="fa-solid fa-file-pdf me-1"></i> Export PDF
            </button>
            <button onclick="exportTableToCSV('profit_loss_<?php echo date('Ymd'); ?>.csv')" class="btn btn-outline-success btn-sm shadow-sm">
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
                <div class="col-md-2 col-sm-6 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill">
                        <i class="fa-solid fa-calculator me-1"></i> Compute
                    </button>
                    <a href="profit_loss.php" class="btn btn-light btn-sm border" title="Reset Range">
                        <i class="fa-solid fa-undo"></i>
                    </a>
                </div>
                <!-- Quick Date Presets -->
                <div class="col-12 mt-2 pt-2 border-top d-flex gap-1 flex-wrap">
                    <span class="small text-muted me-2 align-self-center">Financial Period:</span>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="setRange('today')">Today</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="setRange('this_month')">This Month</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="setRange('last_month')">Last Month</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="setRange('this_quarter')">This Quarter</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="setRange('this_year')">This Financial Year</button>
                </div>
            </form>
        </div>
    </div>


    <!-- Formal Statement of Profit & Loss Table -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-0">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i>Statement of Profit & Loss
            </h5>
            <small class="text-muted">For the period from <?php echo date('d M Y', strtotime($start_date)); ?> to <?php echo date('d M Y', strtotime($end_date)); ?></small>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0" id="plStatementTable">
                <thead class="table-light">
                    <tr class="text-uppercase small text-muted">
                        <th style="width: 65%;">Account / Particulars</th>
                        <th class="text-end" style="width: 17%;">Sub-amount (Rs.)</th>
                        <th class="text-end" style="width: 18%;">Net Amount (Rs.)</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- REVENUE SECTION -->
                    <tr class="table-primary table-opacity-25 fw-bold">
                        <td colspan="3" class="text-uppercase"><i class="fa-solid fa-arrow-up text-primary me-2"></i>1. Operating Revenue</td>
                    </tr>
                    <tr>
                        <td class="ps-4">Gross Invoiced Sales</td>
                        <td class="text-end"><?php echo number_format($gross_sales, 2); ?></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td class="ps-4 text-danger"><em>Less: Trade Discounts & Rebates Given</em></td>
                        <td class="text-end text-danger">(<?php echo number_format($sales_discounts, 2); ?>)</td>
                        <td></td>
                    </tr>
                    <tr class="fw-bold">
                        <td class="ps-4 text-dark">Net Sales Revenue</td>
                        <td></td>
                        <td class="text-end text-dark fs-6"><?php echo number_format($net_sales, 2); ?></td>
                    </tr>

                    <!-- COGS SECTION -->
                    <tr class="table-warning table-opacity-25 fw-bold">
                        <td colspan="3" class="text-uppercase"><i class="fa-solid fa-cart-shopping text-warning me-2"></i>2. Cost of Goods Sold (COGS)</td>
                    </tr>
                    <tr>
                        <td class="ps-4">Direct Merchandise Purchase Cost of Items Sold</td>
                        <td class="text-end"><?php echo number_format($cogs, 2); ?></td>
                        <td></td>
                    </tr>
                    <tr class="fw-bold">
                        <td class="ps-4 text-dark">Total Cost of Sales</td>
                        <td></td>
                        <td class="text-end text-warning fs-6">(<?php echo number_format($cogs, 2); ?>)</td>
                    </tr>

                    <!-- GROSS PROFIT -->
                    <tr class="table-info table-opacity-25 fw-bold fs-6">
                        <td>GROSS PROFIT / (TRADING MARGIN)</td>
                        <td class="text-end small text-muted"><?php echo number_format($gross_margin_pct, 2); ?>%</td>
                        <td class="text-end text-dark">Rs. <?php echo number_format($gross_profit, 2); ?></td>
                    </tr>

                    <!-- EXPENSES SECTION -->
                    <tr class="table-danger table-opacity-25 fw-bold">
                        <td colspan="3" class="text-uppercase"><i class="fa-solid fa-receipt text-danger me-2"></i>3. Operating Overheads & Administrative Expenses</td>
                    </tr>
                    <?php if (!empty($expenses_by_cat)): ?>
                        <?php foreach ($expenses_by_cat as $exp_cat): ?>
                            <tr>
                                <td class="ps-4">
                                    <?php echo htmlspecialchars($exp_cat['category_name']); ?>
                                    <small class="text-muted">(<?php echo $exp_cat['voucher_count']; ?> vouchers)</small>
                                </td>
                                <td class="text-end"><?php echo number_format($exp_cat['total_amount'], 2); ?></td>
                                <td></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td class="ps-4 text-muted" colspan="2">No operating expenses recorded for this period.</td>
                            <td></td>
                        </tr>
                    <?php endif; ?>
                    <tr class="fw-bold">
                        <td class="ps-4 text-danger">Total Operating Expenses</td>
                        <td></td>
                        <td class="text-end text-danger fs-6">(<?php echo number_format($total_expenses, 2); ?>)</td>
                    </tr>

                    <!-- NET PROFIT -->
                    <tr class="<?php echo $is_profitable ? 'table-success bg-opacity-50' : 'table-danger bg-opacity-50'; ?> fw-bold fs-5">
                        <td class="<?php echo $is_profitable ? 'text-success' : 'text-danger'; ?>">
                            <i class="fa-solid <?php echo $is_profitable ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
                            NET OPERATING <?php echo $is_profitable ? 'PROFIT' : 'LOSS'; ?>
                        </td>
                        <td class="text-end fs-6 text-muted"><?php echo number_format($net_profit_margin_pct, 2); ?>% Margin</td>
                        <td class="text-end <?php echo $is_profitable ? 'text-success' : 'text-danger'; ?>">
                            Rs. <?php echo number_format($net_profit, 2); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Supplementary Tables: Product Margin Breakdown & Expenses -->
    <div class="row g-4">
        <!-- Top Sold Items Profitability -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-trophy text-warning me-2"></i>Item-wise Gross Profit Breakdown (Top Items)
                    </h6>
                </div>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Item Name</th>
                                <th class="text-center">Sold Qty</th>
                                <th class="text-end">Revenue</th>
                                <th class="text-end">Cost</th>
                                <th class="text-end">Gross Profit</th>
                                <th class="text-center">Margin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($product_profits)): ?>
                                <?php foreach (array_slice($product_profits, 0, 30) as $p): ?>
                                    <tr>
                                        <td class="fw-semibold text-dark"><?php echo htmlspecialchars($p['item_name']); ?></td>
                                        <td class="text-center"><?php echo number_format($p['total_qty']); ?></td>
                                        <td class="text-end">Rs. <?php echo number_format($p['total_revenue'], 0); ?></td>
                                        <td class="text-end text-muted">Rs. <?php echo number_format($p['calculated_cost'], 0); ?></td>
                                        <td class="text-end fw-bold text-success">Rs. <?php echo number_format($p['calculated_profit'], 0); ?></td>
                                        <td class="text-center">
                                            <span class="badge <?php echo ($p['profit_margin'] > 10) ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo number_format($p['profit_margin'], 1); ?>%
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No sales recorded for this period.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Expense Category Breakdown -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-pie-chart text-danger me-2"></i>Expenses by Head
                    </h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Expense Category</th>
                                <th class="text-center">Vouchers</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">Share %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($expenses_by_cat)): ?>
                                <?php foreach ($expenses_by_cat as $exp_row): 
                                    $share = ($total_expenses > 0) ? ($exp_row['total_amount'] / $total_expenses * 100) : 0;
                                ?>
                                    <tr>
                                        <td class="fw-semibold text-dark"><?php echo htmlspecialchars($exp_row['category_name']); ?></td>
                                        <td class="text-center"><span class="badge bg-light text-dark border"><?php echo $exp_row['voucher_count']; ?></span></td>
                                        <td class="text-end fw-bold text-danger">Rs. <?php echo number_format($exp_row['total_amount'], 2); ?></td>
                                        <td class="text-end text-muted"><?php echo number_format($share, 1); ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No expense vouchers in selected range.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
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
        // today
    } else if (type === 'this_month') {
        start = new Date(today.getFullYear(), today.getMonth(), 1);
    } else if (type === 'last_month') {
        start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        end = new Date(today.getFullYear(), today.getMonth(), 0);
    } else if (type === 'this_quarter') {
        const quarterMonth = Math.floor(today.getMonth() / 3) * 3;
        start = new Date(today.getFullYear(), quarterMonth, 1);
    } else if (type === 'this_year') {
        start = new Date(today.getFullYear(), 0, 1);
    }

    document.querySelector('input[name="start_date"]').value = formatDate(start);
    document.querySelector('input[name="end_date"]').value = formatDate(end);
    document.getElementById('filterForm').submit();
}

function exportTableToCSV(filename) {
    let csv = [];
    let rows = document.querySelectorAll("#plStatementTable tr");
    
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll("td, th");
        for (let j = 0; j < cols.length; j++) {
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
