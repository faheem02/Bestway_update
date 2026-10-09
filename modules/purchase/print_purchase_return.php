<?php
/**
 * Bestway Wholesale Distribution - Printable Purchase Return Voucher / Debit Note
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(['admin']);

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Error: Invalid Purchase Return ID.");
}

// Fetch Return Record
$stmt = $pdo->prepare("SELECT * FROM purchase_returns WHERE id = ?");
$stmt->execute([$id]);
$return = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$return) {
    die("Error: Purchase Return record not found.");
}

// Fetch Supplier Info
$supplier = null;
if (!empty($return['supplier_id'])) {
    $stmt_sup = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt_sup->execute([$return['supplier_id']]);
    $supplier = $stmt_sup->fetch(PDO::FETCH_ASSOC);
}

// Fetch Original Purchase Bill
$purchase = null;
if (!empty($return['purchase_id'])) {
    $stmt_p = $pdo->prepare("SELECT * FROM purchases WHERE id = ?");
    $stmt_p->execute([$return['purchase_id']]);
    $purchase = $stmt_p->fetch(PDO::FETCH_ASSOC);
}

// Fetch Return Items
$stmt_items = $pdo->prepare("
    SELECT pri.*, 
           COALESCE(NULLIF(p.name, ''), 'Medicine') as product_name, 
           p.product_code,
           p.stock_unit
    FROM purchase_return_items pri 
    LEFT JOIN products p ON p.id = pri.product_id 
    WHERE pri.purchase_return_id = ?
    ORDER BY pri.id ASC
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

// Try fetching from company_settings if exists
try {
    $cs = $pdo->query("SELECT * FROM company_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($cs) {
        if (!empty($cs['business_name'])) $company['name'] = $cs['business_name'];
        if (!empty($cs['tagline'])) $company['tagline'] = $cs['tagline'];
        if (!empty($cs['phone'])) $company['phone'] = $cs['phone'];
        if (!empty($cs['address'])) $company['address'] = $cs['address'];
    }
} catch (Exception $e) {}

$logo_file = __DIR__ . '/../../assets/images/logo.png';
$logo_src  = file_exists($logo_file) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logo_file)) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Return Debit Note #<?= htmlspecialchars($return['return_no']) ?> - <?= htmlspecialchars($company['name']) ?></title>
    
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
            max-width: 860px;
            margin: 20px auto;
            padding: 32px 40px;
            border-radius: 10px;
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
            padding: 9px 10px;
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
            border-radius: 8px;
            padding: 12px 16px;
        }
        .signature-box {
            border-top: 1px dashed #94a3b8;
            padding-top: 6px;
            font-size: 11px;
            text-align: center;
            color: #475569;
            font-weight: 600;
            margin-top: 24px;
        }
        .action-bar {
            max-width: 860px;
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
        <a href="purchase_return.php" class="btn btn-outline-secondary btn-sm bg-white shadow-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Purchase Returns
        </a>
        <div>
            <a href="edit_purchase_return.php?id=<?= $id ?>" class="btn btn-outline-primary btn-sm me-2 bg-white shadow-sm">
                <i class="fa-solid fa-pen-to-square me-1"></i> Edit Return
            </a>
            <button onclick="window.print()" class="btn btn-danger btn-sm px-4 fw-bold shadow-sm">
                <i class="fa-solid fa-print me-1"></i> Print Debit Note
            </button>
        </div>
    </div>

    <!-- Voucher Sheet -->
    <div class="voucher-sheet">
        
        <!-- Header -->
        <div class="row align-items-center pb-3 border-bottom border-2">
            <div class="col-8">
                <?php if (!empty($logo_src)): ?>
                    <img src="<?= $logo_src ?>" alt="Bestway Distribution" style="max-height: 52px; margin-bottom: 6px;">
                <?php endif; ?>
                <h4 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($company['name']) ?></h4>
                <div class="text-muted small"><?= htmlspecialchars($company['tagline']) ?></div>
                <div class="text-muted small"><i class="fa-solid fa-location-dot me-1"></i> <?= htmlspecialchars($company['address']) ?> | <i class="fa-solid fa-phone me-1"></i> <?= htmlspecialchars($company['phone']) ?></div>
            </div>
            <div class="col-4 text-end">
                <span class="badge bg-danger fs-6 px-3 py-2 text-uppercase font-monospace">DEBIT NOTE</span>
                <div class="fw-bold fs-5 text-danger font-monospace mt-1">#<?= htmlspecialchars($return['return_no']) ?></div>
                <div class="text-muted small">Date: <strong><?= date('d-M-Y', strtotime($return['return_date'])) ?></strong></div>
            </div>
        </div>

        <!-- Supplier & Reference Details -->
        <div class="row g-3 mt-1">
            <div class="col-6">
                <div class="info-card h-100">
                    <div class="text-muted text-uppercase small fw-bold mb-1" style="font-size: 10px;">Return To Supplier</div>
                    <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($supplier['name'] ?? 'Supplier #' . $return['supplier_id']) ?></div>
                    <?php if (!empty($supplier['company_name'])): ?>
                        <div class="text-secondary small fw-semibold"><?= htmlspecialchars($supplier['company_name']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($supplier['phone'])): ?>
                        <div class="text-muted small"><i class="fa-solid fa-phone me-1"></i> <?= htmlspecialchars($supplier['phone']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($supplier['address'])): ?>
                        <div class="text-muted small"><i class="fa-solid fa-map-marker-alt me-1"></i> <?= htmlspecialchars($supplier['address']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-6">
                <div class="info-card h-100">
                    <div class="text-muted text-uppercase small fw-bold mb-1" style="font-size: 10px;">Reference Details</div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Original Purchase Bill:</span>
                        <strong class="text-dark font-monospace"><?= htmlspecialchars($purchase['bill_no'] ?? 'N/A') ?></strong>
                    </div>
                    <?php if (!empty($purchase['purchase_date'])): ?>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Purchase Bill Date:</span>
                            <span><?= date('d-M-Y', strtotime($purchase['purchase_date'])) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Settlement Method:</span>
                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($return['payment_method'] ?? 'Adjust Supplier Balance') ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Status:</span>
                        <span class="badge bg-success-subtle text-success border border-success"><?= htmlspecialchars($return['status'] ?? 'Adjusted') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <table class="table-voucher">
            <thead>
                <tr>
                    <th style="width: 40px;" class="text-center">#</th>
                    <th>Medicine / Product Details</th>
                    <th style="width: 120px;" class="text-center">Batch #</th>
                    <th style="width: 140px;" class="text-center">Condition</th>
                    <th style="width: 90px;" class="text-center">Return Qty</th>
                    <th style="width: 120px;" class="text-end">Unit Cost</th>
                    <th style="width: 130px;" class="text-end">Total (PKR)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_qty = 0;
                $total_amount = 0;
                foreach ($items as $idx => $it): 
                    $q = intval($it['quantity']);
                    $p = floatval($it['purchase_price']);
                    $tot = floatval($it['total_price']);
                    $total_qty += $q;
                    $total_amount += $tot;
                ?>
                    <tr>
                        <td class="text-center text-muted"><?= $idx + 1 ?></td>
                        <td>
                            <strong class="text-dark"><?= htmlspecialchars($it['product_name']) ?></strong>
                            <?php if (!empty($it['product_code'])): ?>
                                <span class="text-muted small ms-1">(<?= htmlspecialchars($it['product_code']) ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center font-monospace small"><?= htmlspecialchars($it['batch_no'] ?? '-') ?></td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border" style="font-size: 11px;">
                                <?= htmlspecialchars($it['return_condition'] ?? 'Good') ?>
                            </span>
                        </td>
                        <td class="text-center fw-bold text-danger"><?= $q ?></td>
                        <td class="text-end font-monospace">Rs. <?= number_format($p, 2) ?></td>
                        <td class="text-end font-monospace fw-bold text-dark">Rs. <?= number_format($tot, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="border-top: 2px solid #f87171; background: #fff5f5;">
                    <td colspan="4" class="fw-bold text-uppercase text-dark text-end">Total Return Debit Amount:</td>
                    <td class="text-center fw-bold text-danger fs-6"><?= $total_qty ?></td>
                    <td></td>
                    <td class="text-end font-monospace fw-bold text-danger fs-6">Rs. <?= number_format($total_amount, 2) ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- Reason / Remarks -->
        <div class="info-card mb-4">
            <div class="text-muted text-uppercase small fw-bold mb-1" style="font-size: 10px;">Return Reason / Notes</div>
            <div class="text-dark"><?= !empty($return['reason']) ? nl2br(htmlspecialchars($return['reason'])) : 'Debit Note issued for goods returned back to supplier warehouse.' ?></div>
        </div>

        <!-- Signatures Footer -->
        <div class="row pt-4 mt-3">
            <div class="col-4">
                <div class="signature-box">
                    Prepared By<br>
                    <small class="text-muted fw-normal">Accounts / Audit</small>
                </div>
            </div>
            <div class="col-4">
                <div class="signature-box">
                    Warehouse Incharge<br>
                    <small class="text-muted fw-normal">Stock Checked & Dispatched</small>
                </div>
            </div>
            <div class="col-4">
                <div class="signature-box">
                    Supplier Representative<br>
                    <small class="text-muted fw-normal">Signature & Receiving Stamp</small>
                </div>
            </div>
        </div>

        <div class="text-center text-muted small mt-4 pt-3 border-top" style="font-size: 10px;">
            This is a computer generated Debit Note issued by Bestway Pharmaceutical Distribution. Printed on <?= date('d-M-Y h:i A') ?>.
        </div>

    </div>

</body>
</html>
