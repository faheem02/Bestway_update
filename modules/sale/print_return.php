<?php
/**
 * Bestway Wholesale Distribution - Printable Sales Return Voucher / Credit Note
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Error: Invalid Return ID.");
}

// Database connection for sales invoices

// Fetch Return Record
$stmt = $pdo->prepare("SELECT * FROM sale_returns WHERE id = ?");
$stmt->execute([$id]);
$return = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$return) {
    die("Error: Sales Return record not found.");
}

// Fetch original invoice details
$orig_invoice = null;
if (!empty($return['sale_id']) && $conn) {
    $stmt_inv = $conn->prepare("SELECT invoice_no, customer_name, invoice_date, route_name FROM sales_invoices WHERE id = ?");
    $stmt_inv->bind_param("i", $return['sale_id']);
    $stmt_inv->execute();
    $orig_invoice = $stmt_inv->get_result()->fetch_assoc();
    $stmt_inv->close();
}

// Fetch return items
$stmt_items = $pdo->prepare("
    SELECT sri.*, p.name as product_name, p.product_code 
    FROM sale_return_items sri 
    LEFT JOIN products p ON p.id = sri.product_id 
    WHERE sri.sale_return_id = ?
");
$stmt_items->execute([$id]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

// Company Details
$company = [
    'name' => 'BESTWAY PHARMACEUTICAL WHOLESALER',
    'tagline' => 'Licensed Medicine Wholesale, Distribution & Logistics',
    'phone' => '0300-1234567 / 042-37654321',
    'address' => 'Wholesale Medicine Market, Circular Road, Lahore, Pakistan'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale Return Voucher #<?= htmlspecialchars($return['return_no']) ?> - <?= htmlspecialchars($company['name']) ?></title>
    
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        body {
            background-color: #f1f5f9;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: #1e293b;
            font-size: 13px;
        }
        .voucher-sheet {
            background: #ffffff;
            max-width: 820px;
            margin: 20px auto;
            padding: 30px 40px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 2px solid #e2e8f0;
        }
        .table-voucher {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            margin-bottom: 20px;
        }
        .table-voucher th {
            background-color: #fef2f2;
            color: #991b1b;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 8px 10px;
            border-top: 2px solid #f87171;
            border-bottom: 2px solid #f87171;
        }
        .table-voucher td {
            padding: 8px 10px;
            border-bottom: 1px solid #fee2e2;
            font-size: 12px;
            vertical-align: middle;
        }
        .info-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px 14px;
        }
        .signature-box {
            border-top: 1px dashed #94a3b8;
            padding-top: 6px;
            font-size: 11px;
            text-align: center;
            color: #475569;
            font-weight: 600;
            margin-top: 50px;
        }
        .action-bar {
            max-width: 820px;
            margin: 20px auto 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        @media print {
            body {
                background: #ffffff;
                color: #000000;
                margin: 0;
            }
            .no-print {
                display: none !important;
            }
            .voucher-sheet {
                box-shadow: none;
                margin: 0;
                max-width: 100%;
                border: 1px solid #000;
                padding: 15px;
            }
            .table-voucher th {
                background-color: #fee2e2 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            @page {
                size: A4 portrait;
                margin: 10mm;
            }
        }
    </style>
</head>
<body>

    <!-- Action Toolbar (Hidden during print) -->
    <div class="action-bar no-print">
        <a href="sale_return.php" class="btn btn-outline-secondary btn-sm bg-white">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Sales Returns
        </a>
        <button onclick="window.print()" class="btn btn-danger btn-sm px-4 fw-bold shadow-sm">
            <i class="fa-solid fa-print me-1"></i> Print Return Voucher
        </button>
    </div>

    <!-- Voucher Sheet -->
    <div class="voucher-sheet">
        
        <!-- Header -->
        <div class="row align-items-center pb-3 border-bottom mb-3">
            <div class="col-8">
                <div class="d-flex align-items-center gap-2">
                    <div style="width: 44px; height: 44px; border-radius: 8px; background: #dc2626; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                        <i class="fa-solid fa-undo"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0 text-dark text-uppercase"><?= htmlspecialchars($company['name']) ?></h4>
                        <div class="text-muted" style="font-size: 11px;">Customer Sales Return Voucher & Credit Note</div>
                    </div>
                </div>
            </div>
            <div class="col-4 text-end">
                <span class="badge bg-danger px-3 py-2 fs-6 font-monospace">
                    <?= htmlspecialchars($return['return_no']) ?>
                </span>
                <div class="text-muted small mt-1">Return Date: <strong><?= date('d M Y', strtotime($return['return_date'])) ?></strong></div>
            </div>
        </div>

        <!-- Customer & Original Invoice Info -->
        <div class="row g-3 mb-3">
            <div class="col-6">
                <div class="info-card h-100">
                    <div class="text-muted text-uppercase fw-bold" style="font-size: 10px;">Returned By (Customer / Pharmacy)</div>
                    <div class="fw-bold fs-6 text-dark mt-1"><?= htmlspecialchars($orig_invoice['customer_name'] ?? 'Counter Customer') ?></div>
                    <div class="text-secondary small mt-1">Route / Area: <strong><?= htmlspecialchars($orig_invoice['route_name'] ?? 'Local') ?></strong></div>
                </div>
            </div>
            <div class="col-6">
                <div class="info-card h-100">
                    <div class="text-muted text-uppercase fw-bold" style="font-size: 10px;">Return Settlement Details</div>
                    <div class="small mt-1">Against Invoice: <strong class="text-primary font-monospace"><?= htmlspecialchars($orig_invoice['invoice_no'] ?? 'N/A') ?></strong></div>
                    <div class="small">Refund Type: <strong class="text-danger"><?= htmlspecialchars($return['refund_type']) ?></strong></div>
                    <div class="small">Reason: <strong><?= htmlspecialchars($return['reason'] ?: 'Customer Return') ?></strong></div>
                </div>
            </div>
        </div>

        <!-- Returned Items Table -->
        <table class="table-voucher">
            <thead>
                <tr>
                    <th style="width: 35px;" class="text-center">#</th>
                    <th>Medicine / Product Description</th>
                    <th style="width: 90px;" class="text-center">Return Qty</th>
                    <th style="width: 110px;" class="text-end">Rate / TP</th>
                    <th style="width: 130px;">Item Condition</th>
                    <th style="width: 130px;" class="text-end">Credit Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_return_units = 0;
                foreach ($items as $idx => $it): 
                    $q = intval($it['quantity']);
                    $total_return_units += $q;
                    $tp = floatval($it['unit_price']);
                    $line_tot = floatval($it['total_price']);
                ?>
                    <tr>
                        <td class="text-center text-muted fw-bold"><?= $idx + 1 ?></td>
                        <td>
                            <strong class="text-dark"><?= htmlspecialchars($it['product_name'] ?: 'Medicine') ?></strong>
                            <?php if (!empty($it['product_code'])): ?>
                                <span class="badge bg-light text-secondary border font-monospace ms-1" style="font-size: 9px;"><?= htmlspecialchars($it['product_code']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center fw-bold text-danger"><?= $q ?></td>
                        <td class="text-end font-monospace"><?= number_format($tp, 2) ?></td>
                        <td>
                            <span class="badge <?= $it['return_condition'] === 'Good / Resalable' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> border">
                                <?= htmlspecialchars($it['return_condition']) ?>
                            </span>
                        </td>
                        <td class="text-end font-monospace fw-bold text-dark">Rs. <?= number_format($line_tot, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr style="background-color: #fef2f2; font-weight: bold;">
                    <td colspan="2" class="text-end">Total Return Credit:</td>
                    <td class="text-center text-danger"><?= $total_return_units ?> Units</td>
                    <td colspan="2"></td>
                    <td class="text-end font-monospace text-danger fs-6">Rs. <?= number_format(floatval($return['total_amount']), 2) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Signatures and Verification -->
        <div class="row pt-4 mt-3 text-center">
            <div class="col-4">
                <div class="signature-box">Customer Signature</div>
            </div>
            <div class="col-4">
                <div class="signature-box">Store Receiver & QC</div>
            </div>
            <div class="col-4">
                <div class="signature-box">Authorized Accountant Stamp</div>
            </div>
        </div>

        <div class="text-center text-muted border-top mt-4 pt-2" style="font-size: 10px;">
            Inventory Restock: Returned products in good condition have been verified and returned to warehouse inventory.
        </div>

    </div>

</body>
</html>
