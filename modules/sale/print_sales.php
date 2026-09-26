<?php
/**
 * Bestway Wholesale Distribution - Print Filtered Sales Invoices Report
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Database connection for sales invoices
if (empty($conn) || $conn->connect_error) {
    $conn = @new mysqli($db_host ?? 'localhost', $db_user ?? 'root', $db_pass ?? '', $db_name ?? 'bestway_wholesale');
}

// Filter parameters
$search       = trim($_GET['search'] ?? '');
$booker_id    = intval($_GET['booker_id'] ?? 0);
$route_id     = intval($_GET['route_id'] ?? 0);
$status       = trim($_GET['status'] ?? '');
$payment_mode = trim($_GET['payment_mode'] ?? '');
$start_date   = trim($_GET['start_date'] ?? '');
$end_date     = trim($_GET['end_date'] ?? '');

$where_clauses = ["1=1"];

if (!isAdmin()) {
    $uid = intval($_SESSION['user_id'] ?? 0);
    $bkid = currentBookerId($pdo);
    if ($bkid) {
        $where_clauses[] = "(created_by = $uid OR booker_id = $bkid)";
    } else {
        $where_clauses[] = "created_by = $uid";
    }
}

if (!empty($search)) {
    $s_esc = $conn->real_escape_string($search);
    $where_clauses[] = "(invoice_no LIKE '%$s_esc%' OR customer_name LIKE '%$s_esc%' OR booker_name LIKE '%$s_esc%' OR route_name LIKE '%$s_esc%')";
}
if ($booker_id > 0) {
    $where_clauses[] = "booker_id = $booker_id";
}
if ($route_id > 0) {
    $where_clauses[] = "route_id = $route_id";
}
if (!empty($status)) {
    if ($status === 'Paid') {
        $where_clauses[] = "balance_due <= 0.01";
    } elseif ($status === 'Partial') {
        $where_clauses[] = "(paid_amount > 0 AND balance_due > 0.01)";
    } elseif ($status === 'Unpaid') {
        $where_clauses[] = "(paid_amount <= 0 AND balance_due > 0.01)";
    }
}
if (!empty($payment_mode)) {
    $pm_esc = $conn->real_escape_string($payment_mode);
    $where_clauses[] = "payment_method = '$pm_esc'";
}
if (!empty($start_date)) {
    $sd_esc = $conn->real_escape_string($start_date);
    $where_clauses[] = "invoice_date >= '$sd_esc'";
}
if (!empty($end_date)) {
    $ed_esc = $conn->real_escape_string($end_date);
    $where_clauses[] = "invoice_date <= '$ed_esc'";
}

$where_sql = implode(" AND ", $where_clauses);

// Company Info
$biz_name = 'Bestway Distribution';
$biz_tag  = 'Authorized Pharmaceutical Wholesalers & Distributors';
$biz_addr = 'Flate #01 Majid Haleema Sadia road Gate #01 Al Rehman Garden Ph 2 Sharaqpur Road Sheikhupura';
$biz_ph   = '0329-9339000';

if ($db_connected && $pdo) {
    try {
        $c_settings = $pdo->query("SELECT * FROM company_settings LIMIT 1")->fetch();
        if ($c_settings) {
            $biz_name = $c_settings['business_name'] ?: $biz_name;
            $biz_tag  = $c_settings['tagline'] ?: $biz_tag;
            $biz_addr = $c_settings['address'] ?: $biz_addr;
            $biz_ph   = $c_settings['phone'] ?: $biz_ph;
        }
    } catch (Exception $e) {}
}

$salesman_label = '';
if ($booker_id > 0 && $db_connected && $pdo) {
    try {
        $b_row = $pdo->query("SELECT full_name FROM employees WHERE id = $booker_id LIMIT 1")->fetch();
        if ($b_row) $salesman_label = $b_row['full_name'];
    } catch (Exception $e) {}
}

$route_label = '';
if ($route_id > 0 && $db_connected && $pdo) {
    try {
        $r_row = $pdo->query("SELECT name FROM routes WHERE id = $route_id LIMIT 1")->fetch();
        if ($r_row) $route_label = $r_row['name'];
    } catch (Exception $e) {}
}

$sql = "
    SELECT 
        si.*,
        (SELECT COUNT(*) FROM sale_items WHERE invoice_id = si.id) as item_count,
        (SELECT COALESCE(SUM(quantity), 0) FROM sale_items WHERE invoice_id = si.id) as total_units
    FROM sales_invoices si
    WHERE $where_sql
    ORDER BY si.id DESC
";
$invoices_res = $conn->query($sql);
$invoices = $invoices_res ? $invoices_res->fetch_all(MYSQLI_ASSOC) : [];

// Compute Totals
$tot_grand = 0;
$tot_paid = 0;
$tot_balance = 0;
$tot_items = 0;

foreach ($invoices as $inv) {
    $tot_grand += floatval($inv['grand_total'] ?? 0);
    $tot_paid += floatval($inv['paid_amount'] ?? 0);
    $tot_balance += floatval($inv['balance_due'] ?? 0);
    $tot_items += intval($inv['item_count'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Invoices Report - <?= htmlspecialchars($biz_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background-color: #f1f5f9;
            color: #0f172a;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 20px;
        }
        .print-container {
            max-width: 1100px;
            margin: 0 auto;
            background: #ffffff;
            padding: 28px 32px;
            border-radius: 8px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
        }
        .report-header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 14px;
            margin-bottom: 16px;
        }
        .table-report {
            width: 100%;
            border-collapse: collapse;
            font-size: 11.5px;
        }
        .table-report th {
            background-color: #f1f5f9 !important;
            color: #1e293b;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 10.5px;
            padding: 7px 8px;
            border: 1px solid #cbd5e1;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .table-report td {
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        .table-report tr:nth-child(even) td {
            background-color: #f8fafc !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .table-report tfoot th {
            background-color: #e2e8f0 !important;
            font-weight: 800;
            font-size: 11px;
            padding: 8px;
            border: 1px solid #94a3b8;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .filter-tags {
            background-color: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 14px;
            font-size: 11px;
        }
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .print-container {
                box-shadow: none !important;
                border: none !important;
                max-width: 100% !important;
                padding: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            @page {
                size: A4 landscape;
                margin: 8mm 10mm;
            }
        }
    </style>
</head>
<body>

    <div class="print-container">
        <!-- Top Toolbar (Hidden on Print) -->
        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom no-print">
            <div class="text-muted small">
                <i class="fa-solid fa-file-invoice-dollar me-1"></i> Showing <strong><?= count($invoices) ?></strong> filtered sales invoices
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-sm btn-primary fw-bold px-3">
                    <i class="fa-solid fa-print me-1"></i> Print Now
                </button>
                <button onclick="window.close()" class="btn btn-sm btn-outline-secondary px-3">
                    <i class="fa-solid fa-times me-1"></i> Close
                </button>
            </div>
        </div>

        <!-- Report Header -->
        <div class="report-header">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h3 class="fw-bold mb-1 text-dark" style="letter-spacing: -0.5px;"><?= htmlspecialchars($biz_name) ?></h3>
                    <div class="text-secondary small fw-semibold"><?= htmlspecialchars($biz_tag) ?></div>
                    <div class="text-muted small mt-1">
                        <i class="fa-solid fa-location-dot me-1"></i> <?= htmlspecialchars($biz_addr) ?>
                        <?php if (!empty($biz_ph)): ?>
                            &nbsp;|&nbsp; <i class="fa-solid fa-phone me-1"></i> <?= htmlspecialchars($biz_ph) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-end">
                    <h4 class="fw-bold text-primary mb-1">SALES INVOICES REPORT</h4>
                    <div class="text-muted small">Generated: <strong><?= date('d-M-Y h:i A') ?></strong></div>
                    <div class="text-muted small">User: <strong><?= htmlspecialchars($_SESSION['user_fullname'] ?? 'Admin') ?></strong></div>
                </div>
            </div>
        </div>

        <!-- Filter Tags -->
        <div class="filter-tags d-flex flex-wrap gap-3 align-items-center">
            <div><strong>Applied Filters:</strong></div>
            <?php if (!empty($salesman_label)): ?>
                <div>Salesman: <span class="badge bg-secondary"><?= htmlspecialchars($salesman_label) ?></span></div>
            <?php endif; ?>
            <?php if (!empty($route_label)): ?>
                <div>Route/Area: <span class="badge bg-secondary"><?= htmlspecialchars($route_label) ?></span></div>
            <?php endif; ?>
            <?php if (!empty($status)): ?>
                <div>Status: <span class="badge bg-info text-dark"><?= htmlspecialchars($status) ?></span></div>
            <?php endif; ?>
            <?php if (!empty($payment_mode)): ?>
                <div>Payment Mode: <span class="badge bg-light text-dark border"><?= htmlspecialchars($payment_mode) ?></span></div>
            <?php endif; ?>
            <?php if (!empty($start_date) || !empty($end_date)): ?>
                <div>Date Range: <strong><?= !empty($start_date) ? date('d-M-Y', strtotime($start_date)) : 'Start' ?></strong> to <strong><?= !empty($end_date) ? date('d-M-Y', strtotime($end_date)) : 'Today' ?></strong></div>
            <?php else: ?>
                <div>Date Range: <span class="text-muted">All Dates</span></div>
            <?php endif; ?>
            <?php if (!empty($search)): ?>
                <div>Search: "<em><?= htmlspecialchars($search) ?></em>"</div>
            <?php endif; ?>

            <div class="ms-auto text-end fw-bold text-secondary">
                Total Invoices: <?= count($invoices) ?>
            </div>
        </div>

        <!-- Invoices Table -->
        <table class="table-report">
            <thead>
                <tr>
                    <th style="width: 32px;" class="text-center">#</th>
                    <th style="width: 100px;">Invoice No</th>
                    <th style="width: 85px;">Date</th>
                    <th>Pharmacy / Customer</th>
                    <th>Salesman</th>
                    <th class="text-center" style="width: 50px;">Items</th>
                    <th class="text-end" style="width: 95px;">Grand Total</th>
                    <th class="text-end" style="width: 90px;">Paid Amount</th>
                    <th class="text-end" style="width: 90px;">Balance Due</th>
                    <th class="text-center" style="width: 75px;">Mode</th>
                    <th class="text-center" style="width: 65px;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="11" class="text-center py-4 text-muted">No sales invoices found matching the filter criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php $sn = 1; foreach ($invoices as $inv): 
                        $i_grand = floatval($inv['grand_total'] ?? 0);
                        $i_paid = floatval($inv['paid_amount'] ?? 0);
                        $i_bal = floatval($inv['balance_due'] ?? 0);

                        $is_paid = ($i_bal <= 0.01);
                        $is_partial = (!$is_paid && $i_paid > 0);
                        $st_text = $is_paid ? 'Paid' : ($is_partial ? 'Partial' : 'Unpaid');
                        $st_class = $is_paid ? 'bg-success-subtle text-success border-success' : ($is_partial ? 'bg-warning-subtle text-warning border-warning' : 'bg-danger-subtle text-danger border-danger');
                    ?>
                        <tr>
                            <td class="text-center text-muted"><?= $sn++ ?></td>
                            <td><strong><?= htmlspecialchars($inv['invoice_no']) ?></strong></td>
                            <td><?= date('d-M-Y', strtotime($inv['invoice_date'])) ?></td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($inv['customer_name'] ?? 'Walk-in Customer') ?></div>
                                <?php if (!empty($inv['route_name'])): ?>
                                    <small class="text-muted"><i class="fa-solid fa-map-pin me-1"></i><?= htmlspecialchars($inv['route_name']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($inv['booker_name'] ?: 'Direct Counter') ?></td>
                            <td class="text-center font-monospace"><?= intval($inv['item_count']) ?></td>
                            <td class="text-end font-monospace fw-bold"><?= number_format($i_grand, 2) ?></td>
                            <td class="text-end font-monospace text-success"><?= number_format($i_paid, 2) ?></td>
                            <td class="text-end font-monospace text-danger fw-bold"><?= ($i_bal > 0) ? number_format($i_bal, 2) : '0.00' ?></td>
                            <td class="text-center"><small><?= htmlspecialchars($inv['payment_method'] ?? 'Cash') ?></small></td>
                            <td class="text-center">
                                <span class="badge border <?= $st_class ?>">
                                    <?= $st_text ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="5" class="text-end">TOTALS:</th>
                    <th class="text-center font-monospace"><?= number_format($tot_items) ?></th>
                    <th class="text-end font-monospace"><?= number_format($tot_grand, 2) ?></th>
                    <th class="text-end font-monospace text-success"><?= number_format($tot_paid, 2) ?></th>
                    <th class="text-end font-monospace text-danger"><?= number_format($tot_balance, 2) ?></th>
                    <th colspan="2"></th>
                </tr>
            </tfoot>
        </table>

        <!-- Signatures / Footer -->
        <div class="row mt-5 pt-4 text-center">
            <div class="col-4">
                <div class="border-top pt-2 text-muted small">Prepared By</div>
            </div>
            <div class="col-4">
                <div class="border-top pt-2 text-muted small">Billing Incharge</div>
            </div>
            <div class="col-4">
                <div class="border-top pt-2 text-muted small">Authorized Signature</div>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                window.print();
            }, 500);
        });
    </script>
</body>
</html>
