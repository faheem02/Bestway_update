<?php
/**
 * Bestway Wholesale Distribution - Print Filtered Purchases Report
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(['admin']);

$search         = trim($_GET['search'] ?? '');
$supplier_id    = intval($_GET['supplier_id'] ?? 0);
$payment_status = trim($_GET['payment_status'] ?? '');
$start_date     = trim($_GET['start_date'] ?? '');
$end_date       = trim($_GET['end_date'] ?? '');

// Build where clause
$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(p.bill_no LIKE ? OR s.name LIKE ? OR s.company_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($supplier_id > 0) {
    $where[] = "p.supplier_id = ?";
    $params[] = $supplier_id;
}

if (!empty($payment_status)) {
    $where[] = "p.payment_status = ?";
    $params[] = $payment_status;
}

if (!empty($start_date)) {
    $where[] = "p.purchase_date >= ?";
    $params[] = $start_date;
}

if (!empty($end_date)) {
    $where[] = "p.purchase_date <= ?";
    $params[] = $end_date;
}

$where_sql = implode(" AND ", $where);

// Company Info
$biz_name = 'Bestway Distribution';
$biz_tag  = 'Authorized Pharmaceutical Wholesalers & Distributors';
$biz_addr = 'Flate #01 Majid Haleema Sadia road Gate #01 Al Rehman Garden Ph 2 Sharaqpur Road Sheikhupura';
$biz_ph   = '0329-9339000';

$purchases_list = [];
$supplier_name_selected = '';

if ($db_connected && $pdo) {
    try {
        $c_settings = $pdo->query("SELECT * FROM company_settings LIMIT 1")->fetch();
        if ($c_settings) {
            $biz_name = $c_settings['business_name'] ?: $biz_name;
            $biz_tag  = $c_settings['tagline'] ?: $biz_tag;
            $biz_addr = $c_settings['address'] ?: $biz_addr;
            $biz_ph   = $c_settings['phone'] ?: $biz_ph;
        }

        if ($supplier_id > 0) {
            $s_stmt = $pdo->prepare("SELECT name, company_name FROM suppliers WHERE id = ?");
            $s_stmt->execute([$supplier_id]);
            $s_row = $s_stmt->fetch();
            if ($s_row) {
                $supplier_name_selected = $s_row['name'] . (!empty($s_row['company_name']) ? " ({$s_row['company_name']})" : "");
            }
        }

        $stmt_purchases = $pdo->prepare("
            SELECT 
                p.*,
                s.name as supplier_name,
                s.company_name as supplier_company,
                COUNT(pi.id) as item_count,
                SUM(pi.quantity + pi.bonus_quantity) as total_packs_received,
                SUM(pi.quantity * pi.purchase_price) as gross_amount
            FROM purchases p
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            LEFT JOIN purchase_items pi ON pi.purchase_id = p.id
            WHERE $where_sql
            GROUP BY p.id, s.name, s.company_name
            ORDER BY p.purchase_date DESC, p.id DESC
        ");
        $stmt_purchases->execute($params);
        $purchases_list = $stmt_purchases->fetchAll();
    } catch (Exception $e) {
        die("Error fetching purchases: " . $e->getMessage());
    }
}

// Compute Totals
$tot_gross = 0;
$tot_discount = 0;
$tot_grand = 0;
$tot_paid = 0;
$tot_balance = 0;
$tot_packs = 0;

foreach ($purchases_list as $row) {
    $tot_gross += floatval($row['gross_amount'] ?? 0);
    $item_disc = max(0, floatval($row['gross_amount'] ?? 0) - floatval($row['subtotal'] ?? 0));
    $bill_disc = floatval($row['discount_amount'] ?? 0);
    $tot_discount += ($item_disc + $bill_disc);
    $tot_grand += floatval($row['grand_total'] ?? 0);
    $tot_paid += floatval($row['paid_amount'] ?? 0);
    $tot_balance += floatval($row['balance_amount'] ?? 0);
    $tot_packs += intval($row['total_packs_received'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Invoices Report - <?= htmlspecialchars($biz_name) ?></title>
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
                <i class="fa-solid fa-file-invoice me-1"></i> Showing <strong><?= count($purchases_list) ?></strong> filtered purchase bills
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
                    <h4 class="fw-bold text-primary mb-1">PURCHASE INVOICES REPORT</h4>
                    <div class="text-muted small">Generated: <strong><?= date('d-M-Y h:i A') ?></strong></div>
                    <div class="text-muted small">User: <strong><?= htmlspecialchars($_SESSION['user_fullname'] ?? 'Admin') ?></strong></div>
                </div>
            </div>
        </div>

        <!-- Filter Tags -->
        <div class="filter-tags d-flex flex-wrap gap-3 align-items-center">
            <div><strong>Applied Filters:</strong></div>
            <?php if (!empty($supplier_name_selected)): ?>
                <div>Supplier: <span class="badge bg-secondary"><?= htmlspecialchars($supplier_name_selected) ?></span></div>
            <?php else: ?>
                <div>Supplier: <span class="badge bg-light text-dark border">All Suppliers</span></div>
            <?php endif; ?>

            <?php if (!empty($payment_status)): ?>
                <div>Status: <span class="badge bg-info text-dark"><?= htmlspecialchars($payment_status) ?></span></div>
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
                Total Records: <?= count($purchases_list) ?>
            </div>
        </div>

        <!-- Invoices Table -->
        <table class="table-report">
            <thead>
                <tr>
                    <th style="width: 32px;" class="text-center">#</th>
                    <th style="width: 110px;">Bill #</th>
                    <th style="width: 85px;">Date</th>
                    <th>Supplier / Distributor</th>
                    <th class="text-center" style="width: 60px;">Qty</th>
                    <th class="text-end" style="width: 90px;">Gross (Rs.)</th>
                    <th class="text-end" style="width: 85px;">Discount</th>
                    <th class="text-end" style="width: 95px;">Net Total</th>
                    <th class="text-end" style="width: 90px;">Paid</th>
                    <th class="text-end" style="width: 90px;">Balance</th>
                    <th class="text-center" style="width: 65px;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($purchases_list)): ?>
                    <tr>
                        <td colspan="11" class="text-center py-4 text-muted">No purchase records found matching the filter criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php $sn = 1; foreach ($purchases_list as $pur): 
                        $i_gross = floatval($pur['gross_amount'] ?? 0);
                        $i_disc_row = max(0, $i_gross - floatval($pur['subtotal'] ?? 0));
                        $i_disc_bill = floatval($pur['discount_amount'] ?? 0);
                        $i_tot_disc = $i_disc_row + $i_disc_bill;
                        $i_grand = floatval($pur['grand_total'] ?? 0);
                        $i_paid = floatval($pur['paid_amount'] ?? 0);
                        $i_bal = floatval($pur['balance_amount'] ?? 0);
                    ?>
                        <tr>
                            <td class="text-center text-muted"><?= $sn++ ?></td>
                            <td><strong><?= htmlspecialchars($pur['bill_no']) ?></strong></td>
                            <td><?= date('d-M-Y', strtotime($pur['purchase_date'])) ?></td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($pur['supplier_name'] ?? 'N/A') ?></div>
                                <?php if (!empty($pur['supplier_company'])): ?>
                                    <small class="text-muted"><?= htmlspecialchars($pur['supplier_company']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-center font-monospace"><?= number_format($pur['total_packs_received']) ?></td>
                            <td class="text-end font-monospace"><?= number_format($i_gross, 2) ?></td>
                            <td class="text-end font-monospace text-success"><?= ($i_tot_disc > 0) ? number_format($i_tot_disc, 2) : '-' ?></td>
                            <td class="text-end font-monospace fw-bold"><?= number_format($i_grand, 2) ?></td>
                            <td class="text-end font-monospace text-success"><?= number_format($i_paid, 2) ?></td>
                            <td class="text-end font-monospace text-danger fw-bold"><?= ($i_bal > 0) ? number_format($i_bal, 2) : '0.00' ?></td>
                            <td class="text-center">
                                <span class="badge border <?= ($pur['payment_status'] === 'Paid') ? 'bg-success-subtle text-success border-success' : (($pur['payment_status'] === 'Partial') ? 'bg-warning-subtle text-warning border-warning' : 'bg-danger-subtle text-danger border-danger') ?>">
                                    <?= htmlspecialchars($pur['payment_status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="4" class="text-end">TOTALS:</th>
                    <th class="text-center font-monospace"><?= number_format($tot_packs) ?></th>
                    <th class="text-end font-monospace"><?= number_format($tot_gross, 2) ?></th>
                    <th class="text-end font-monospace text-success"><?= number_format($tot_discount, 2) ?></th>
                    <th class="text-end font-monospace"><?= number_format($tot_grand, 2) ?></th>
                    <th class="text-end font-monospace text-success"><?= number_format($tot_paid, 2) ?></th>
                    <th class="text-end font-monospace text-danger"><?= number_format($tot_balance, 2) ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>

        <!-- Signatures / Footer -->
        <div class="row mt-5 pt-4 text-center">
            <div class="col-4">
                <div class="border-top pt-2 text-muted small">Prepared By</div>
            </div>
            <div class="col-4">
                <div class="border-top pt-2 text-muted small">Warehouse / Receiving Incharge</div>
            </div>
            <div class="col-4">
                <div class="border-top pt-2 text-muted small">Authorized Signature</div>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('DOMContentLoaded', () => {
            // Auto open print dialog
            setTimeout(() => {
                window.print();
            }, 500);
        });
    </script>
</body>
</html>
