<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$booker_id = (int)($_GET['booker_id'] ?? 0);
$from_date = trim($_GET['from_date'] ?? date('Y-m-01'));
$to_date = trim($_GET['to_date'] ?? date('Y-m-d'));

if ($booker_id <= 0) {
    die("Invalid Booker ID.");
}

$selected_booker = null;
$ledger_entries = [];
$total_debit = 0.00;
$total_credit = 0.00;
$opening_balance = 0.00;

if ($db_connected && $pdo) {
    try {
        $stmt_sel = $pdo->prepare("SELECT * FROM bookers WHERE id = :id");
        $stmt_sel->execute(['id' => $booker_id]);
        $selected_booker = $stmt_sel->fetch();

        if ($selected_booker) {
            // Calculate opening balance before $from_date
            $stmt_open = $pdo->prepare("SELECT COALESCE(SUM(debit_amount - credit_amount), 0) as prior_bal 
                                        FROM booker_ledgers 
                                        WHERE booker_id = :id AND transaction_date < :from_date");
            $stmt_open->execute([
                'id' => $booker_id,
                'from_date' => $from_date
            ]);
            $opening_balance = (float)$stmt_open->fetchColumn();

            // Fetch ledger records within date range
            $stmt_led = $pdo->prepare("SELECT * FROM booker_ledgers 
                                       WHERE booker_id = :id AND transaction_date >= :from_date AND transaction_date <= :to_date 
                                       ORDER BY transaction_date ASC, id ASC");
            $stmt_led->execute([
                'id' => $booker_id,
                'from_date' => $from_date,
                'to_date' => $to_date
            ]);
            $ledger_entries = $stmt_led->fetchAll();
        } else {
            die("Booker not found.");
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
    <title>Print Booker Ledger - <?php echo htmlspecialchars($selected_booker['name']); ?></title>
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
            <a href="booker_ledger.php?booker_id=<?php echo $booker_id; ?>&from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>" class="btn btn-outline-secondary">
                &larr; Back to Ledger
            </a>
            <button onclick="window.print()" class="btn btn-primary fw-bold">
                Print Document
            </button>
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

        <!-- Booker Profile Strip -->
        <div class="mb-4 p-3 border rounded border-dark">
            <div class="row g-2">
                <div class="col-12 col-md-6 border-end border-dark">
                    <span class="badge bg-light text-dark border border-dark font-monospace mb-1">
                        <?php echo htmlspecialchars($selected_booker['booker_code'] ?? 'BKR-'.$selected_booker['id']); ?>
                    </span>
                    <h5 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($selected_booker['name']); ?></h5>
                </div>
                <div class="col-12 col-md-6 ps-md-3">
                    <div class="text-dark">
                        <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($selected_booker['phone']); ?></p>
                        <?php if (!empty($selected_booker['address'])): ?>
                            <p class="mb-1"><strong>Address:</strong> <?php echo htmlspecialchars($selected_booker['address']); ?></p>
                        <?php endif; ?>
                        <p class="mb-0 fw-bold">Current Net Balance: 
                            <span class="<?php echo ((float)$selected_booker['current_balance'] > 0) ? 'text-danger' : 'text-success'; ?>">
                                <?php echo format_currency($selected_booker['current_balance']); ?>
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
                    <th style="width: 15%;">Ref / Receipt #</th>
                    <th style="width: 15%;">Type</th>
                    <th style="width: 25%;">Description</th>
                    <th class="text-end" style="width: 11%;">Debit (Charge)</th>
                    <th class="text-end" style="width: 11%;">Credit (Deposit)</th>
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
                        $running = $running + $debit - $credit;
                        $total_debit += $debit;
                        $total_credit += $credit;
                ?>
                    <tr>
                        <td><?php echo date('d-m-Y', strtotime($entry['transaction_date'])); ?></td>
                        <td><strong><?php echo htmlspecialchars($entry['reference_no']); ?></strong></td>
                        <td><?php echo htmlspecialchars($entry['transaction_type']); ?></td>
                        <td><?php echo htmlspecialchars($entry['description'] ?? '-'); ?></td>
                        <td class="text-end text-danger fw-semibold">
                            <?php echo ($debit > 0) ? format_currency($debit) : '-'; ?>
                        </td>
                        <td class="text-end text-success fw-semibold">
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
                            <em>No transaction records found for this booker between selected dates.</em>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold table-light">
                    <td colspan="4" class="text-end">Period Total:</td>
                    <td class="text-end text-danger"><?php echo format_currency($total_debit); ?></td>
                    <td class="text-end text-success"><?php echo format_currency($total_credit); ?></td>
                    <td class="text-end">
                        <?php echo format_currency($running); ?>
                    </td>
                </tr>
            </tfoot>
        </table>

        <!-- Print Buttons -->
        <div class="text-center mt-4 no-print">
            <button onclick="window.print()" class="btn btn-primary px-4 me-2">Print Now</button>
            <button onclick="window.close()" class="btn btn-secondary px-4">Close Window</button>
        </div>
    </div>
</body>
</html>
