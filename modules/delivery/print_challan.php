<?php
/**
 * Bestway Wholesale Distribution - Printable Delivery Challan
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';


$challan_id  = intval($_GET['id'] ?? 0);
$invoice_id  = intval($_GET['invoice_id'] ?? 0);

$challan = null;
$items   = [];

// Case 1: Load from delivery_challans table
if ($challan_id > 0 && $db_connected && $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM delivery_challans WHERE id = ?");
    $stmt->execute([$challan_id]);
    $challan = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($challan) {
        $stmt_items = $pdo->prepare("SELECT * FROM delivery_challan_items WHERE challan_id = ? ORDER BY id ASC");
        $stmt_items->execute([$challan_id]);
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Case 2: Load directly from sales_invoices if invoice_id is passed
if (!$challan && $invoice_id > 0 && $conn) {
    $stmt = $conn->prepare("SELECT * FROM sales_invoices WHERE id = ?");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $inv = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($inv) {
        $challan = [
            'challan_no'      => 'DC-' . str_replace('INV-', '', $inv['invoice_no']),
            'invoice_no'      => $inv['invoice_no'],
            'challan_date'    => $inv['invoice_date'],
            'challan_time'    => null,
            'customer_name'   => $inv['customer_name'],
            'customer_phone'  => '',
            'delivery_address'=> '',
            'route_name'      => $inv['route_name'] ?? '',
            'booker_name'     => $inv['booker_name'] ?? '',
            'vehicle_no'      => 'Delivery Van',
            'driver_name'     => 'Logistics Rider',
            'driver_phone'    => '',
            'total_cartons'   => 1,
            'notes'           => ''
        ];

        $stmt_items = $conn->prepare("SELECT item_name, quantity, batch_no FROM sale_items WHERE invoice_id = ? ORDER BY id ASC");
        $stmt_items->bind_param("i", $invoice_id);
        $stmt_items->execute();
        $res_it = $stmt_items->get_result();
        while ($r = $res_it->fetch_assoc()) {
            $items[] = [
                'item_name' => $r['item_name'],
                'batch_no'  => $r['batch_no'],
                'quantity'  => $r['quantity'],
                'unit_type' => 'Pack',
                'remarks'   => ''
            ];
        }
        $stmt_items->close();
    }
}

if (!$challan) {
    die("
        <div style='font-family: sans-serif; text-align: center; padding: 50px;'>
            <h2 style='color: #ef4444;'>Delivery Challan record not found.</h2>
            <p style='color: #64748b;'>Requested Challan ID: " . htmlspecialchars($challan_id ?: $invoice_id) . "</p>
            <a href='view_challan.php' style='display: inline-block; padding: 10px 20px; background: #0284c7; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold;'>Back to Delivery Challans</a>
        </div>
    ");
}

// Calculate units
$total_units = 0;
foreach ($items as $it) {
    $total_units += intval($it['quantity'] ?? 0);
}

// Fetch Company Settings if available
$company = [
    'name'    => 'Bestway Distribution',
    'tagline' => 'Wholesale Medicine & Pharma Distribution',
    'phone'   => '0300-1234567 / 0321-7654321',
    'address' => 'Flate #01 Majid Haleema Sadia road Gate #01 Al Rehman Garden Ph 2 Sharaqpur Road Sheikhupura'
];
if ($db_connected && $pdo) {
    try {
        $cs_row = $pdo->query("SELECT * FROM company_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($cs_row) {
            if (!empty($cs_row['business_name']))   $company['name']    = $cs_row['business_name'];
            if (!empty($cs_row['tagline']))         $company['tagline'] = $cs_row['tagline'];
            if (!empty($cs_row['phone']))           $company['phone']   = $cs_row['phone'];
            if (!empty($cs_row['address']))         $company['address'] = $cs_row['address'];
            
        }
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Challan #<?= htmlspecialchars($challan['challan_no']) ?> - <?= htmlspecialchars($company['name']) ?></title>
    
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
        .challan-sheet {
            background: #ffffff;
            max-width: 820px;
            margin: 20px auto;
            padding: 30px 40px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 2px solid #e2e8f0;
        }
        .challan-title-badge {
            display: inline-block;
            background: #0d9488;
            color: #ffffff;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 4px 14px;
            border-radius: 4px;
            margin-bottom: 6px;
        }
        .table-challan {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            margin-bottom: 20px;
        }
        .table-challan th {
            background-color: #f0fdfa;
            color: #0f766e;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 8px 10px;
            border-top: 2px solid #14b8a6;
            border-bottom: 2px solid #14b8a6;
        }
        .table-challan td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
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
            .challan-sheet {
                box-shadow: none;
                margin: 0;
                max-width: 100%;
                border: 1px solid #000;
                padding: 15px;
            }
            .table-challan th {
                background-color: #f0fdfa !important;
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
        <a href="view_challan.php" class="btn btn-outline-secondary btn-sm bg-white">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Delivery Challans
        </a>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-success btn-sm px-4 fw-bold shadow-sm" style="background-color: #0d9488; border-color: #0d9488;">
                <i class="fa-solid fa-print me-1"></i> Print Delivery Challan
            </button>
            <button onclick="downloadChallanPDF()" class="btn btn-danger btn-sm px-4 fw-bold shadow-sm">
                <i class="fa-solid fa-file-pdf me-1"></i> Download PDF
            </button>
        </div>
    </div>

    <!-- Challan Sheet -->
    <div class="challan-sheet">
        
        <!-- Header -->
        <div class="row align-items-center pb-3 border-bottom mb-3">
            <div class="col-7">
                <div class="d-flex align-items-center gap-3">
                    <img src="<?= BASE_URL ?>assets/images/logo.png" alt="Company Logo" style="height: 55px; max-width: 160px; object-fit: contain;">
                    <div>
                        <h4 class="fw-bold mb-0 text-dark text-uppercase"><?= htmlspecialchars($company['name']) ?></h4>
                        <div class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($company['tagline']) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-5 text-end">
                <div class="fw-bold text-dark" style="font-size: 12px;"><i class="fa-solid fa-location-dot text-muted me-1"></i><?= htmlspecialchars($company['address']) ?></div>
                <div class="text-muted" style="font-size: 11px;"><i class="fa-solid fa-phone text-muted me-1"></i><?= htmlspecialchars($company['phone']) ?></div>
                
            </div>
        </div>

        <!-- Subheader -->
        <div class="row g-3 mb-3">
            <!-- Destination / Customer Info -->
            <div class="col-6">
                <div class="info-card h-100">
                    <div class="text-muted text-uppercase fw-bold" style="font-size: 10px;">Consignee / Deliver To:</div>
                    <div class="fw-bold fs-6 text-dark mt-1"><?= htmlspecialchars($challan['customer_name']) ?></div>
                    <?php if (!empty($challan['customer_phone'])): ?>
                        <div class="small text-muted font-monospace"><i class="fa-solid fa-phone text-muted me-1"></i><?= htmlspecialchars($challan['customer_phone']) ?></div>
                    <?php endif; ?>
                    <div class="text-secondary small mt-1">
                        <i class="fa-solid fa-route text-muted me-1"></i>Route / Area: <strong><?= htmlspecialchars($challan['route_name'] ?: 'Local Distribution') ?></strong>
                    </div>
                    <?php if (!empty($challan['delivery_address'])): ?>
                        <div class="text-muted small mt-1">Address: <?= htmlspecialchars($challan['delivery_address']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Challan & Logistics Meta -->
            <div class="col-6">
                <div class="info-card h-100 text-end">
                    <div class="challan-title-badge">Delivery Challan</div>
                    <div class="fw-bold fs-5 text-dark font-monospace"><?= htmlspecialchars($challan['challan_no']) ?></div>
                    <?php if (!empty($challan['invoice_no'])): ?>
                        <div class="text-muted small mt-1">Invoice Ref: <strong class="text-primary font-monospace"><?= htmlspecialchars($challan['invoice_no']) ?></strong></div>
                    <?php endif; ?>
                    <div class="text-muted small">Date: <strong><?= date('d M Y', strtotime($challan['challan_date'])) ?></strong></div>
                    <div class="text-muted small">Vehicle: <strong><?= htmlspecialchars($challan['vehicle_no'] ?: 'Company Van') ?></strong></div>
                    <div class="text-muted small">Driver / Rider: <strong><?= htmlspecialchars($challan['driver_name'] ?: 'Rider') ?></strong></div>
                </div>
            </div>
        </div>

        <!-- Dispatched Goods Table -->
        <table class="table-challan">
            <thead>
                <tr>
                    <th style="width: 35px;" class="text-center">#</th>
                    <th>Medicine / Product Description</th>
                    <th style="width: 120px;">Batch No</th>
                    <th style="width: 100px;" class="text-center">Dispatched Qty</th>
                    <th style="width: 80px;" class="text-center">Unit</th>
                    <th style="width: 180px;">Remarks / Packaging</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $idx => $it): ?>
                    <tr>
                        <td class="text-center text-muted fw-bold"><?= $idx + 1 ?></td>
                        <td>
                            <strong class="text-dark"><?= htmlspecialchars($it['item_name']) ?></strong>
                        </td>
                        <td><?= htmlspecialchars($it['batch_no'] ?: 'STANDARD') ?></td>
                        <td class="text-center fw-bold text-dark fs-6"><?= intval($it['quantity']) ?></td>
                        <td class="text-center text-muted"><?= htmlspecialchars($it['unit_type'] ?? 'Pack') ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($it['remarks'] ?: 'Intact Pack') ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr style="background-color: #f0fdfa; font-weight: bold;">
                    <td colspan="3" class="text-end text-dark">Total Consignment Summary:</td>
                    <td class="text-center fs-6 text-success" style="color: #0f766e !important;"><?= $total_units ?> Units</td>
                    <td colspan="2" class="text-muted small"><?= count($items) ?> item(s) in <?= intval($challan['total_cartons'] ?? 1) ?> carton(s)</td>
                </tr>
            </tbody>
        </table>

        <!-- Signatures and Handover Verification -->
        <div class="row pt-4 mt-4 text-center">
            <div class="col-4">
                <div class="signature-box">Warehouse Dispatcher<br><small class="text-muted fw-normal">Checked & Handed Over</small></div>
            </div>
            <div class="col-4">
                <div class="signature-box">Delivery Rider / Driver<br><small class="text-muted fw-normal">Transport In-Charge</small></div>
            </div>
            <div class="col-4">
                <div class="signature-box">Pharmacy Received Stamp<br><small class="text-muted fw-normal">Signature & Date Received</small></div>
            </div>
        </div>

        <div class="text-center text-muted border-top mt-4 pt-2" style="font-size: 10px;">
            Received the above-mentioned pharmaceutical goods in sealed, intact condition at proper storage temperature.
        </div>

    <!-- html2pdf.js for client-side PDF export -->
    <script src="<?= BASE_URL ?>assets/js/html2pdf.bundle.min.js"></script>
    <script>
    function downloadChallanPDF() {
        const element = document.querySelector('.challan-sheet');
        const opt = {
            margin: [5, 5, 5, 5],
            filename: 'Challan_<?= htmlspecialchars($challan['challan_no'] ?? 'delivery') ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, logging: false },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        html2pdf().set(opt).from(element).save();
    }
    </script>
</body>
</html>
