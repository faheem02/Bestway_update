<?php
/**
 * Bestway Wholesale Distribution - Print Filtered Product Catalog & Stock Report
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(['admin']);

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

// Company Info
$biz_name = 'Bestway Distribution';
$biz_tag  = 'Authorized Pharmaceutical Wholesalers & Distributors';
$biz_addr = 'Flate #01 Majid Haleema Sadia road Gate #01 Al Rehman Garden Ph 2 Sharaqpur Road Sheikhupura';
$biz_ph   = '0329-9339000';

$products = [];
$comp_name_selected = '';
$cat_name_selected = '';

if ($db_connected && $pdo) {
    try {
        $c_settings = $pdo->query("SELECT * FROM company_settings LIMIT 1")->fetch();
        if ($c_settings) {
            $biz_name = $c_settings['business_name'] ?: $biz_name;
            $biz_tag  = $c_settings['tagline'] ?: $biz_tag;
            $biz_addr = $c_settings['address'] ?: $biz_addr;
            $biz_ph   = $c_settings['phone'] ?: $biz_ph;
        }

        if ($company_filter > 0) {
            $c_row = $pdo->query("SELECT name FROM companies WHERE id = $company_filter LIMIT 1")->fetch();
            if ($c_row) $comp_name_selected = $c_row['name'];
        }

        if ($category_filter > 0) {
            $ct_row = $pdo->query("SELECT name FROM categories WHERE id = $category_filter LIMIT 1")->fetch();
            if ($ct_row) $cat_name_selected = $ct_row['name'];
        }

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
            ORDER BY p.name ASC
        ");
        $stmt->execute($params);
        $products = $stmt->fetchAll();
    } catch (Exception $e) {
        die("Error fetching products: " . $e->getMessage());
    }
}

// Compute Totals
$tot_stock_qty = 0;
$tot_stock_val = 0;

foreach ($products as $pr) {
    $stk = intval($pr['current_stock'] ?? 0);
    $tot_stock_qty += $stk;
    $cost = floatval($pr['purchase_price'] ?? 0);
    $tot_stock_val += ($stk * $cost);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products & Stock Catalog - <?= htmlspecialchars($biz_name) ?></title>
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
                <i class="fa-solid fa-boxes me-1"></i> Showing <strong><?= count($products) ?></strong> filtered products
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
                    <h4 class="fw-bold text-primary mb-1">PRODUCT CATALOG & STOCK REPORT</h4>
                    <div class="text-muted small">Generated: <strong><?= date('d-M-Y h:i A') ?></strong></div>
                    <div class="text-muted small">User: <strong><?= htmlspecialchars($_SESSION['user_fullname'] ?? 'Admin') ?></strong></div>
                </div>
            </div>
        </div>

        <!-- Filter Tags -->
        <div class="filter-tags d-flex flex-wrap gap-3 align-items-center">
            <div><strong>Applied Filters:</strong></div>
            <?php if (!empty($comp_name_selected)): ?>
                <div>Company: <span class="badge bg-secondary"><?= htmlspecialchars($comp_name_selected) ?></span></div>
            <?php else: ?>
                <div>Company: <span class="badge bg-light text-dark border">All Companies</span></div>
            <?php endif; ?>

            <?php if (!empty($cat_name_selected)): ?>
                <div>Category: <span class="badge bg-secondary"><?= htmlspecialchars($cat_name_selected) ?></span></div>
            <?php endif; ?>

            <?php if (!empty($stock_status)): ?>
                <div>Stock Status: <span class="badge bg-info text-dark"><?= ucfirst(str_replace('_', ' ', $stock_status)) ?></span></div>
            <?php endif; ?>

            <?php if (!empty($search)): ?>
                <div>Search: "<em><?= htmlspecialchars($search) ?></em>"</div>
            <?php endif; ?>

            <div class="ms-auto text-end fw-bold text-secondary">
                Total Products: <?= count($products) ?>
            </div>
        </div>

        <!-- Products Table -->
        <table class="table-report">
            <thead>
                <tr>
                    <th style="width: 32px;" class="text-center">#</th>
                    <th style="width: 90px;">Item Code</th>
                    <th>Medicine / Product Name</th>
                    <th>Company / Manufacturer</th>
                    <th>Category</th>
                    <th class="text-end" style="width: 85px;">TP / Cost</th>
                    <th class="text-end" style="width: 85px;">Sale Price</th>
                    <th class="text-center" style="width: 70px;">Stock</th>
                    <th class="text-end" style="width: 95px;">Stock Value</th>
                    <th class="text-center" style="width: 75px;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-4 text-muted">No products found matching the filter criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php $sn = 1; foreach ($products as $pr): 
                        $stk = intval($pr['current_stock'] ?? 0);
                        $reorder = intval($pr['reorder_level'] ?? 0);
                        $tp = floatval($pr['purchase_price'] ?? 0);
                        $sp = floatval($pr['sale_price'] ?? 0);
                        $val = $stk * $tp;

                        if ($stk <= 0) {
                            $st_badge = '<span class="badge bg-danger-subtle text-danger border border-danger">Out of Stock</span>';
                        } elseif ($stk <= $reorder) {
                            $st_badge = '<span class="badge bg-warning-subtle text-warning border border-warning">Low Stock</span>';
                        } else {
                            $st_badge = '<span class="badge bg-success-subtle text-success border border-success">In Stock</span>';
                        }
                    ?>
                        <tr>
                            <td class="text-center text-muted"><?= $sn++ ?></td>
                            <td><span class="font-monospace text-muted"><?= htmlspecialchars($pr['product_code']) ?></span></td>
                            <td>
                                <strong><?= htmlspecialchars($pr['name']) ?></strong>
                                <?php if (!empty($pr['generic_name'])): ?>
                                    <div class="text-muted" style="font-size: 10px;"><?= htmlspecialchars($pr['generic_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($pr['company_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($pr['category_name'] ?? 'General') ?></td>
                            <td class="text-end font-monospace"><?= number_format($tp, 2) ?></td>
                            <td class="text-end font-monospace text-primary fw-bold"><?= number_format($sp, 2) ?></td>
                            <td class="text-center font-monospace fw-bold <?= ($stk <= 0) ? 'text-danger' : (($stk <= $reorder) ? 'text-warning' : 'text-dark') ?>">
                                <?= number_format($stk) ?>
                            </td>
                            <td class="text-end font-monospace"><?= number_format($val, 2) ?></td>
                            <td class="text-center"><?= $st_badge ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="7" class="text-end">TOTALS:</th>
                    <th class="text-center font-monospace"><?= number_format($tot_stock_qty) ?></th>
                    <th class="text-end font-monospace text-primary">Rs. <?= number_format($tot_stock_val, 2) ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>

        <!-- Signatures / Footer -->
        <div class="row mt-5 pt-4 text-center">
            <div class="col-4">
                <div class="border-top pt-2 text-muted small">Stock Verification Officer</div>
            </div>
            <div class="col-4">
                <div class="border-top pt-2 text-muted small">Warehouse Manager</div>
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
