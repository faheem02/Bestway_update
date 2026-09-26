<?php
/**
 * Bestway Wholesale Distribution - View Sale Invoice Details
 */
$page_title = "Sale Invoice Details";
$compact_page_heading = true;
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

// Database connection for sales invoices
if (empty($conn) || $conn->connect_error) {
    $conn = @new mysqli($db_host ?? 'localhost', $db_user ?? 'root', $db_pass ?? '', $db_name ?? 'bestway_wholesale');
}
if (!$conn || $conn->connect_error) {
    // Connection fallback
}


$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo "<div class='alert alert-danger m-4'>Invalid Invoice ID. <a href='sales.php'>Back to Sales List</a></div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Fetch invoice details
$stmt = $conn->prepare("
    SELECT si.*, c.invoice_type AS customer_invoice_type, c.license_number, e.commission_rate AS salesman_commission_rate, e.employee_type AS salesman_type 
    FROM sales_invoices si
    LEFT JOIN customers c ON (c.id = si.customer_id OR (si.customer_id IS NULL AND (c.name = si.customer_name OR c.shop_name = si.customer_name)))
    LEFT JOIN employees e ON e.id = si.booker_id
    WHERE si.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$inv_res = $stmt->get_result();
$invoice = $inv_res->fetch_assoc();
$stmt->close();

$cust_lic = trim($invoice['license_number'] ?? '');
if (empty($cust_lic) && !empty($invoice['customer_name']) && $db_connected && $pdo) {
    try {
        $cname = trim($invoice['customer_name']);
        $chk = $pdo->prepare("SELECT license_number FROM customers WHERE name = ? OR shop_name = ? LIMIT 1");
        $chk->execute([$cname, $cname]);
        $cust_lic = trim($chk->fetchColumn() ?: '');
    } catch (Exception $e) {}
}

if (!$invoice) {
    echo "<div class='alert alert-danger m-4'>Sales Invoice record not found. <a href='sales.php'>Go Back</a></div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Fetch line items
$item_stmt = $conn->prepare("SELECT * FROM sale_items WHERE invoice_id = ? ORDER BY id ASC");
$item_stmt->bind_param("i", $id);
$item_stmt->execute();
$items_res = $item_stmt->get_result();
$items = $items_res->fetch_all(MYSQLI_ASSOC);
$item_stmt->close();

// Fetch product extra info from bestway_wholesale.products
$prod_info_map = [];
if ($db_connected && $pdo) {
    try {
        $p_res = $pdo->query("SELECT p.id, p.product_code, p.generic_name, c.name as company_name FROM products p LEFT JOIN companies c ON c.id = p.company_id")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($p_res as $pr) {
            $prod_info_map[$pr['id']] = $pr;
        }
    } catch (Exception $e) {}
}


// Calculate totals
$grand = floatval($invoice['grand_total']);
$paid  = floatval($invoice['paid_amount']);
$inv_balance = max(0, $grand - $paid);

if ($inv_balance <= 0.01) {
    $status_badge = '<span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-check-circle me-1"></i> Paid in Full</span>';
} elseif ($paid > 0) {
    $status_badge = '<span class="badge bg-warning text-dark px-3 py-2 fs-6"><i class="fa-solid fa-clock me-1"></i> Partially Paid</span>';
} else {
    $status_badge = '<span class="badge bg-danger px-3 py-2 fs-6"><i class="fa-solid fa-exclamation-triangle me-1"></i> Unpaid (Credit)</span>';
}
?>

<style>
    .invoice-view-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 4px 24px rgba(15, 23, 42, 0.04);
    }
    .section-header {
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #0284c7;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .table-view-items th {
        background-color: #f8fafc;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #475569;
        padding: 12px;
        border-bottom: 2px solid #e2e8f0;
    }
    .table-view-items td {
        padding: 12px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.92rem;
    }
    .financial-summary-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
    }
    .financial-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        font-size: 0.92rem;
    }
</style>

<!-- Top Toolbar -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <div class="d-flex align-items-center gap-2">
        <a href="sales.php" class="btn btn-sm btn-outline-secondary bg-white rounded-3 shadow-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Sales
        </a>
        <span class="badge bg-primary text-white font-monospace px-3 py-2" style="font-size:0.95rem;">
            <?= htmlspecialchars($invoice['invoice_no']) ?>
        </span>
        <span class="text-muted small">Recorded: <?= date('d M Y', strtotime($invoice['invoice_date'])) ?></span>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php
            $v_print_type = (!empty($invoice['customer_invoice_type']) && $invoice['customer_invoice_type'] === 'warranty') ? 'warranty' : 'sale';
        ?>
        <a href="print_invoice.php?id=<?= $invoice['id'] ?>&type=<?= $v_print_type ?>" target="_blank" class="btn btn-sm btn-primary fw-semibold shadow-sm px-3 rounded-3">
            <i class="fa-solid fa-print me-1"></i> Print Invoice
        </a>
        <a href="edit_sale.php?id=<?= $invoice['id'] ?>" class="btn btn-sm btn-outline-warning text-dark bg-white fw-semibold shadow-sm px-3 rounded-3">
            <i class="fa-solid fa-edit me-1"></i> Edit
        </a>
        <a href="sale_return.php?invoice_id=<?= $invoice['id'] ?>" class="btn btn-sm btn-outline-danger bg-white fw-semibold shadow-sm px-3 rounded-3">
            <i class="fa-solid fa-undo me-1"></i> Return Items
        </a>
    </div>
</div>

<div class="invoice-view-card p-4 p-md-5 mb-4">

    <!-- Invoice Header Meta -->
    <div class="row g-4 pb-4 border-bottom mb-4 align-items-center">
        <div class="col-md-6">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 font-monospace fs-6 mb-2">
                <?= htmlspecialchars($invoice['invoice_no']) ?>
            </span>
            <h3 class="fw-bold text-dark mb-1"><?= htmlspecialchars($invoice['customer_name']) ?></h3>
            <?php if (!empty($cust_lic)): ?>
                <div class="mt-2">
                    <span class="badge badge-light border text-dark font-weight-bold py-1 px-2 shadow-sm" style="font-size: 0.85rem;">
                        <i class="fas fa-id-card text-success mr-1"></i> Drug Lic #: <span class="text-primary font-monospace"><?= htmlspecialchars($cust_lic) ?></span>
                    </span>
                </div>
            <?php endif; ?>
        </div>
        <div class="col-md-6 text-md-end">
            <div class="mb-2"><?= $status_badge ?></div>
            <div class="text-secondary small">Invoice Date: <strong><?= date('d F Y', strtotime($invoice['invoice_date'])) ?></strong></div>
            <div class="text-secondary small">Payment Mode: <strong><?= htmlspecialchars($invoice['payment_method']) ?></strong></div>
        </div>
    </div>

    <!-- Booker & Delivery Notes -->
    <div class="row g-3 pb-3 border-bottom mb-4">
        <div class="col-md-6">
            <div class="section-header"><i class="fa-solid fa-user-tie"></i> Salesman &amp; Commission</div>
            <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($invoice['booker_name'] ?: 'Counter Direct') ?></div>
            <?php 
                $rate = floatval($invoice['salesman_commission_rate'] ?? 0);
                if (!empty($invoice['booker_id']) && $rate > 0): 
                    $sale_comm = round($grand * $rate / 100, 2);
            ?>
                <div class="mt-2 d-flex flex-wrap align-items-center gap-2">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                        <i class="fa-solid fa-percent me-1"></i> Rate: <?= number_format($rate, 2) ?>%
                    </span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                        <i class="fa-solid fa-coins me-1"></i> Commission on this Sale: Rs. <?= number_format($sale_comm, 2) ?>
                    </span>
                    <a href="../employees/ledger.php?emp_id=<?= (int)$invoice['booker_id'] ?>&month=<?= date('Y-m', strtotime($invoice['invoice_date'])) ?>" class="btn btn-xs btn-outline-secondary py-0" style="font-size:0.75rem;" target="_blank">
                        <i class="fa-solid fa-book-open me-1"></i> View Month Ledger
                    </a>
                </div>
            <?php else: ?>
                <small class="text-muted">Assigned Sales Officer</small>
            <?php endif; ?>
        </div>
        <div class="col-md-6">
            <div class="section-header"><i class="fa-regular fa-comment-dots"></i> Remarks / Instructions</div>
            <div class="text-dark small"><?= nl2br(htmlspecialchars($invoice['notes'] ?: 'No special instructions.')) ?></div>
        </div>
    </div>

    <!-- Items Table -->
    <div class="mb-4">
        <div class="section-header mb-3"><i class="fa-solid fa-pills"></i> Invoiced Medicine Items</div>
        <div class="table-responsive border rounded-3 overflow-hidden">
            <table class="table table-view-items mb-0">
                <thead>
                    <tr>
                        <th style="width: 45px;" class="text-center">#</th>
                        <th>Medicine / Product Name</th>
                        <th style="width: 100px;" class="text-center">Qty</th>
                        <th style="width: 130px;" class="text-end">Trade Price (TP)</th>
                        <th style="width: 130px;" class="text-end">Gross Amount</th>
                        <th style="width: 100px;" class="text-center">Disc %</th>
                        <th style="width: 140px;" class="text-end">Net Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $gross_sum = 0;
                    foreach ($items as $idx => $it): 
                        $q = intval($it['quantity']);
                        $tp = floatval($it['unit_price']);
                        $line_gross = $q * $tp;
                        $gross_sum += $line_gross;
                        $line_net = floatval($it['total_amount']);
                        $pid = intval($it['product_id']);
                        $pinfo = $prod_info_map[$pid] ?? null;
                    ?>
                        <tr>
                            <td class="text-center text-muted fw-bold"><?= $idx + 1 ?></td>
                            <td>
                                <?php 
                                    $item_name_cleaned = preg_replace('/\s*(\[|\()?prd\s*-\s*\d+(\]|\))?/i', '', $it['item_name']);
                                ?>
                                <strong class="text-dark"><?= htmlspecialchars(trim($item_name_cleaned) ?: $it['item_name']) ?></strong>
                                <?php if ($pinfo && !empty($pinfo['company_name'])): ?>
                                    <div class="small text-muted"><i class="fa-solid fa-industry me-1"></i><?= htmlspecialchars($pinfo['company_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center fw-bold text-dark"><?= $q ?></td>
                            <td class="text-end font-monospace">Rs. <?= number_format($tp, 2) ?></td>
                            <td class="text-end font-monospace">Rs. <?= number_format($line_gross, 2) ?></td>
                            <td class="text-center text-muted"><?= floatval($it['discount_percent']) ?>%</td>
                            <td class="text-end fw-bold font-monospace text-dark">Rs. <?= number_format($line_net, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Financials Breakdown -->
    <div class="row justify-content-end">
        <div class="col-lg-5 col-md-7">
            <div class="financial-summary-card">
                <div class="financial-row">
                    <span class="text-muted fw-semibold">Gross Subtotal:</span>
                    <span class="fw-bold text-dark font-monospace">Rs. <?= number_format($invoice['subtotal'], 2) ?></span>
                </div>
                <div class="financial-row">
                    <span class="text-muted fw-semibold">Total Discounts:</span>
                    <span class="fw-bold text-danger font-monospace">- Rs. <?= number_format($invoice['discount_amount'], 2) ?></span>
                </div>
                <?php if (floatval($invoice['round_off']) != 0): ?>
                    <div class="financial-row">
                        <span class="text-muted fw-semibold">Round Off:</span>
                        <span class="fw-bold text-secondary font-monospace">Rs. <?= number_format($invoice['round_off'], 2) ?></span>
                    </div>
                <?php endif; ?>
                <div class="financial-row pt-2 border-top border-2">
                    <span class="fw-bold text-dark fs-6">Current Bill Total:</span>
                    <span class="fw-bold text-primary fs-6 font-monospace">Rs. <?= number_format($invoice['grand_total'], 2) ?></span>
                </div>
                <div class="financial-row pt-2 border-top">
                    <span class="text-success fw-bold">Amount Paid:</span>
                    <span class="fw-bold text-success font-monospace">Rs. <?= number_format($invoice['paid_amount'], 2) ?></span>
                </div>
                <div class="financial-row pt-2 border-top border-2">
                    <span class="text-danger fw-bold fs-6">Invoice Balance:</span>
                    <span class="fw-bold text-danger fs-5 font-monospace">Rs. <?= number_format($inv_balance, 2) ?></span>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
