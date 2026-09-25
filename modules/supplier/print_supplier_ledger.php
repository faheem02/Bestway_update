<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$supplier_id = (int)($_GET['supplier_id'] ?? 0);
$from_date = trim($_GET['from_date'] ?? date('Y-m-01'));
$to_date = trim($_GET['to_date'] ?? date('Y-m-d'));

if ($supplier_id <= 0) {
    die("Invalid Supplier ID.");
}

$selected_supplier = null;
$ledger_entries = [];
$total_debit = 0.00;
$total_credit = 0.00;
$opening_balance = 0.00;

if ($db_connected && $pdo) {
    try {
        syncSupplierLedger($pdo, $supplier_id);
        $stmt_sel = $pdo->prepare("SELECT * FROM suppliers WHERE id = :id");
        $stmt_sel->execute(['id' => $supplier_id]);
        $selected_supplier = $stmt_sel->fetch();

        if ($selected_supplier) {
            // Calculate opening balance before $from_date
            $stmt_open = $pdo->prepare("SELECT COALESCE(SUM(credit_amount - debit_amount), 0) as prior_bal 
                                        FROM supplier_ledgers 
                                        WHERE supplier_id = :id AND transaction_date < :from_date");
            $stmt_open->execute([
                'id' => $supplier_id,
                'from_date' => $from_date
            ]);
            // Running balance for supplier = initial opening_balance + sum of prior credits (bills) - sum of prior debits (payments)
            $opening_balance = (float)($selected_supplier['opening_balance'] ?? 0) + (float)$stmt_open->fetchColumn();

            // Fetch ledger records within date range
            $stmt_led = $pdo->prepare("SELECT * FROM supplier_ledgers 
                                       WHERE supplier_id = :id AND transaction_date >= :from_date AND transaction_date <= :to_date 
                                       ORDER BY transaction_date ASC, id ASC");
            $stmt_led->execute([
                'id' => $supplier_id,
                'from_date' => $from_date,
                'to_date' => $to_date
            ]);
            $ledger_entries = $stmt_led->fetchAll();
        } else {
            die("Supplier not found.");
        }
    } catch (Exception $e) {
        die("Database error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Supplier Ledger - <?php echo htmlspecialchars($selected_supplier['company_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #fff;
            color: #000;
            font-family: Arial, sans-serif;
            font-size: 13px;
        }
        .container-print {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        .table-custom th {
            background-color: #f8f9fa !important;
            -webkit-print-color-adjust: exact;
            color-adjust: exact;
            font-size: 12px;
        }
        .table-custom td {
            font-size: 12px;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            @page {
                size: A4 portrait;
                margin: 10mm;
            }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="container-print">
        
        <!-- Action Buttons (Hidden in Print) -->
        <div class="no-print mb-4 d-flex justify-content-between align-items-center">
            <a href="supplier_ledger.php?supplier_id=<?php echo $supplier_id; ?>&from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>" class="btn btn-outline-secondary">
                &larr; Back to Ledger
            </a>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-primary fw-bold">
                    <i class="fa-solid fa-print me-1"></i> Print Document
                </button>
                <button onclick="downloadSupplierLedgerPDF()" class="btn btn-danger fw-bold">
                    <i class="fa-solid fa-file-pdf me-1"></i> Download PDF
                </button>
            </div>
        </div>

        <!-- Print Header -->
        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="Bestway Distribution" style="height: 60px; object-fit: contain;">
                <h3 class="fw-bold text-dark mb-0 lh-1">Bestway distribution</h3>
            </div>
            <div class="text-end" style="max-width: 50%;">
                <p class="mb-1 text-dark"><strong>Owner:</strong> Syed Wasim Hussain Sherazi</p>
                <p class="mb-0 small text-dark">Flate #01 Majid Haleema Sadia road Gate #01 Al Rehman Garden Ph 2 Sharaqpur Road Sheikhupura</p>
            </div>
        </div>

        <!-- Supplier Profile Strip -->
        <div class="mb-4 p-3 border rounded border-dark">
            <div class="row g-2">
                <div class="col-12 col-md-6 border-end border-dark">
                    <span class="badge bg-light text-dark border border-dark font-monospace mb-1">
                        <?php echo htmlspecialchars($selected_supplier['supplier_code'] ?? 'SUP-'.$selected_supplier['id']); ?>
                    </span>
                    <h5 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($selected_supplier['company_name']); ?></h5>
                    <p class="mb-0 text-dark"><strong>Contact:</strong> <?php echo htmlspecialchars($selected_supplier['name'] ?? '-'); ?></p>
                </div>
                <div class="col-12 col-md-6 ps-md-3">
                    <div class="text-dark">
                        <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($selected_supplier['phone']); ?></p>
                        <?php if (!empty($selected_supplier['address'])): ?>
                            <p class="mb-1"><strong>Address:</strong> <?php echo htmlspecialchars($selected_supplier['address']); ?></p>
                        <?php endif; ?>
                        <p class="mb-0 fw-bold">Current Net Balance: 
                            <span class="<?php echo ((float)$selected_supplier['current_balance'] > 0) ? 'text-danger' : 'text-success'; ?>">
                                <?php echo format_currency($selected_supplier['current_balance']); ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Header Row -->
        <div class="d-flex justify-content-between align-items-end mb-2">
            <h5 class="fw-bold mb-0">Statement of Account</h5>
            <div class="text-end">
                <p class="mb-0 fw-bold">Period: <?php echo date('d M Y', strtotime($from_date)); ?> to <?php echo date('d M Y', strtotime($to_date)); ?></p>
                <small>Date: <?php echo date('d M Y'); ?></small>
            </div>
        </div>

        <!-- Ledger Table -->
        <table class="table table-bordered table-custom">
            <thead>
                <tr>
                    <th style="width: 10%;">Date</th>
                    <th style="width: 15%;">Ref / Inv #</th>
                    <th style="width: 15%;">Type</th>
                    <th style="width: 25%;">Description</th>
                    <th class="text-end" style="width: 11%;">Debit (Paid)</th>
                    <th class="text-end" style="width: 11%;">Credit (Bill)</th>
                    <th class="text-end" style="width: 13%;">Balance</th>
                </tr>
            </thead>
            <tbody>
                <!-- Opening Balance Row -->
                <tr>
                    <td><strong><?php echo date('d-m-Y', strtotime($from_date)); ?></strong></td>
                    <td><span class="badge bg-secondary font-monospace">B/F</span></td>
                    <td><strong>Brought Forward</strong></td>
                    <td><em>Opening Balance prior to <?php echo date('d-m-Y', strtotime($from_date)); ?></em></td>
                    <td class="text-end">-</td>
                    <td class="text-end">-</td>
                    <td class="text-end fw-bold">
                        <?php echo format_currency($opening_balance); ?>
                    </td>
                </tr>

                <?php 
                $running = $opening_balance;
                if (!empty($ledger_entries)): 
                    foreach ($ledger_entries as $entry): 
                        $debit = (float)$entry['debit_amount'];
                        $credit = (float)$entry['credit_amount'];
                        $running = $running + $credit - $debit;
                        $total_debit += $debit;
                        $total_credit += $credit;
                ?>
                    <tr>
                        <td><?php echo date('d-m-Y', strtotime($entry['transaction_date'])); ?></td>
                        <td><strong><?php echo htmlspecialchars($entry['reference_no']); ?></strong></td>
                        <td><?php echo htmlspecialchars($entry['transaction_type']); ?></td>
                        <td><?php echo htmlspecialchars($entry['description'] ?? '-'); ?></td>
                        <td class="text-end text-success fw-semibold">
                            <?php echo ($debit > 0) ? format_currency($debit) : '-'; ?>
                        </td>
                        <td class="text-end text-danger fw-semibold">
                            <?php echo ($credit > 0) ? format_currency($credit) : '-'; ?>
                        </td>
                        <td class="text-end fw-bold">
                            <?php echo format_currency($running); ?>
                        </td>
                    </tr>
                <?php 
                    endforeach; 
                else: 
                ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <em>No transaction records found for this supplier between selected dates.</em>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold table-light">
                    <td colspan="4" class="text-end">Period Total:</td>
                    <td class="text-end text-success"><?php echo format_currency($total_debit); ?></td>
                    <td class="text-end text-danger"><?php echo format_currency($total_credit); ?></td>
                    <td class="text-end">
                        <?php echo format_currency($running); ?>
                    </td>
                </tr>
            </tfoot>
        </table>

        <!-- Print Buttons -->
        <div class="text-center mt-4 no-print">
            <button onclick="window.print()" class="btn btn-primary px-4 me-2"><i class="fa-solid fa-print me-1"></i> Print Now</button>
            <button onclick="downloadSupplierLedgerPDF()" class="btn btn-danger px-4 me-2"><i class="fa-solid fa-file-pdf me-1"></i> Download PDF</button>
            <button onclick="window.close()" class="btn btn-secondary px-4">Close Window</button>
        </div>
    </div>

    <!-- html2pdf.js for client-side PDF export -->
    <script src="<?php echo BASE_URL; ?>assets/js/html2pdf.bundle.min.js"></script>
    <script>
    function downloadSupplierLedgerPDF() {
        const element = document.querySelector('.container-print');
        const opt = {
            margin: [6, 6, 6, 6],
            filename: 'Supplier_Ledger_<?php echo $selected_supplier['id'] ?? 'sup'; ?>_<?php echo date('Ymd'); ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, logging: false },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        html2pdf().set(opt).from(element).save();
    }
    </script>
</body>
</html>
