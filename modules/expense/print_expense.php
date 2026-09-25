<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("Invalid Expense ID!");
}

$expense = null;
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT e.*, 
                   COALESCE(c.name, 'General') as category_name,
                   ca.account_name as cash_acc_name,
                   ba.bank_name, ba.account_title as bank_acc_title, ba.account_number
            FROM expenses e
            LEFT JOIN expense_categories c ON e.category_id = c.id
            LEFT JOIN cash_accounts ca ON e.cash_account_id = ca.id
            LEFT JOIN bank_accounts ba ON e.bank_account_id = ba.id
            WHERE e.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $expense = $stmt->fetch();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage());
    }
}

if (!$expense) {
    die("Expense record not found!");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Voucher #<?php echo htmlspecialchars($expense['voucher_no']); ?> &bull; <?php echo APP_SHORT_NAME; ?></title>
    
    <!-- Google Font: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f1f5f9;
            color: #0f172a;
            padding: 30px 15px;
        }

        .voucher-sheet {
            max-width: 720px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid #cbd5e1;
            padding: 2.5rem;
            position: relative;
        }

        .voucher-header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .voucher-badge {
            background: #e0f2fe;
            color: #0284c7;
            font-size: 0.85rem;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 8px;
            display: inline-block;
        }

        .info-table th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 10px 14px;
            width: 32%;
            border-color: #e2e8f0;
        }

        .info-table td {
            padding: 10px 14px;
            color: #0f172a;
            font-weight: 600;
            border-color: #e2e8f0;
        }

        .amount-highlight-box {
            background: #f8fafc;
            border: 2px dashed #0284c7;
            border-radius: 12px;
            padding: 1.25rem;
            margin: 1.75rem 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sig-box {
            border-top: 1px solid #94a3b8;
            text-align: center;
            padding-top: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            color: #475569;
            margin-top: 50px;
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .voucher-sheet {
                box-shadow: none;
                border: 1px solid #000000;
                padding: 1.5rem;
                max-width: 100%;
                border-radius: 0;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="text-center mb-3 no-print">
    <div class="d-inline-flex gap-2">
        <button onclick="window.print()" class="btn btn-primary fw-bold px-4 shadow-sm" style="background-color: #0284c7; border-color: #0284c7;">
            <i class="fa-solid fa-print me-1"></i> Print Voucher
        </button>
        <button onclick="window.close()" class="btn btn-outline-secondary px-3">
            <i class="fa-solid fa-times me-1"></i> Close
        </button>
    </div>
</div>

<div class="voucher-sheet">
    
    <!-- Header -->
    <div class="voucher-header d-flex justify-content-between align-items-start">
        <div class="d-flex align-items-center gap-3">
            <img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="Logo" style="height: 52px; width: 52px; object-fit: contain; border-radius: 50%; border: 1px solid #e2e8f0;">
            <div>
                <h4 class="fw-bolder text-dark mb-0"><?php echo APP_NAME; ?></h4>
                <small class="text-muted">Wholesale Medicine Distribution System</small>
            </div>
        </div>
        <div class="text-end">
            <span class="voucher-badge">EXPENSE DEBIT VOUCHER</span>
            <div class="fw-bold font-monospace mt-1" style="font-size: 1.05rem;">
                #<?php echo htmlspecialchars($expense['voucher_no']); ?>
            </div>
        </div>
    </div>

    <!-- Date & Voucher Details Table -->
    <table class="table table-bordered info-table mb-4">
        <tbody>
            <tr>
                <th>Voucher / Invoice #</th>
                <td class="font-monospace text-primary fw-bold">
                    <?php echo htmlspecialchars($expense['voucher_no']); ?>
                </td>
            </tr>
            <tr>
                <th>Expense Date</th>
                <td>
                    <i class="fa-regular fa-calendar me-1 text-muted"></i>
                    <?php echo date('d-M-Y (l)', strtotime($expense['expense_date'])); ?>
                </td>
            </tr>
            <tr>
                <th>Category</th>
                <td>
                    <span class="badge bg-primary-subtle text-primary fw-semibold px-2 py-1">
                        <i class="fa-solid fa-tag me-1"></i>
                        <?php echo htmlspecialchars($expense['category_name']); ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th>Expense Title / Purpose</th>
                <td class="fs-6">
                    <?php echo htmlspecialchars($expense['description']); ?>
                </td>
            </tr>
            <tr>
                <th>Paid From (Account)</th>
                <td>
                    <?php if ($expense['payment_method'] === 'Bank'): ?>
                        <i class="fa-solid fa-building-columns text-info me-1"></i>
                        Bank: <strong><?php echo htmlspecialchars($expense['bank_name'] ?? 'Bank Account'); ?></strong>
                        (<?php echo htmlspecialchars($expense['bank_acc_title'] ?? ''); ?><?php if (!empty($expense['account_number'])) echo ' - A/C: ' . htmlspecialchars($expense['account_number']); ?>)
                    <?php else: ?>
                        <i class="fa-solid fa-money-bill-alt text-success me-1"></i>
                        Cash: <strong><?php echo htmlspecialchars($expense['cash_acc_name'] ?? 'Main Cash Drawer'); ?></strong>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if (!empty($expense['receipt_no']) && $expense['receipt_no'] !== $expense['voucher_no']): ?>
            <tr>
                <th>Bill / Ref #</th>
                <td>
                    <?php echo htmlspecialchars($expense['receipt_no']); ?>
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Amount Highlight Box -->
    <div class="amount-highlight-box">
        <div>
            <span class="text-muted small fw-bold text-uppercase d-block">Total Expense Amount</span>
            <span class="badge bg-success-subtle text-success mt-1">Paid in Full</span>
        </div>
        <div class="text-end">
            <span class="fs-2 fw-bolder text-danger font-monospace">
                <?php echo format_currency($expense['amount']); ?>
            </span>
        </div>
    </div>

    <!-- Signatures Row -->
    <div class="row pt-4 mt-2">
        <div class="col-4">
            <div class="sig-box">
                Prepared By (Cashier)
            </div>
        </div>
        <div class="col-4">
            <div class="sig-box">
                Checked & Verified
            </div>
        </div>
        <div class="col-4">
            <div class="sig-box">
                Receiver Signature
            </div>
        </div>
    </div>

    <div class="text-center text-muted small mt-4 pt-3 border-top" style="font-size: 0.72rem;">
        This is an official system-generated expense debit voucher from <?php echo APP_NAME; ?>.
    </div>

</div>

<script>
    // Auto-trigger print when opened if parameter is passed
    window.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('autoprint') === '1') {
            window.print();
        }
    });
</script>

</body>
</html>
