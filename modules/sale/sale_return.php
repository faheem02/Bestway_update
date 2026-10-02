<?php
/**
 * Bestway Wholesale Distribution - Customer Sales Return
 * Return medicines against invoice, replenish inventory & adjust customer balance
 */
$page_title = "Customer Sale Return";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

// Database connection for sales invoices

$message = "";
$msg_type = "";

// Auto-generate Return No
$auto_return_no = "SRTN-" . date('Y') . "-0001";
if ($db_connected && $pdo) {
    try {
        $stmt_seq = $pdo->query("SELECT return_no FROM sale_returns WHERE return_no REGEXP '^SRTN-[0-9]{4}-[0-9]+$' ORDER BY id DESC LIMIT 1");
        $last_ret = $stmt_seq->fetchColumn();
        if ($last_ret && preg_match('/SRTN-\d+-(\d+)/i', $last_ret, $matches)) {
            $next_num = intval($matches[1]) + 1;
            $auto_return_no = "SRTN-" . date('Y') . "-" . str_pad($next_num, 4, '0', STR_PAD_LEFT);
        } else {
            $stmt_cnt = $pdo->query("SELECT COUNT(*) FROM sale_returns");
            $cnt = intval($stmt_cnt->fetchColumn()) + 1;
            $auto_return_no = "SRTN-" . date('Y') . "-" . str_pad($cnt, 4, '0', STR_PAD_LEFT);
        }
    } catch (Exception $e) {}
}

// Fetch Cash & Bank Accounts
$cash_accounts = [];
$bank_accounts = [];
if ($db_connected && $pdo) {
    try {
        $cash_accounts = $pdo->query("SELECT id, account_name, balance FROM cash_accounts ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        $bank_accounts = $pdo->query("SELECT id, bank_name, account_title, account_number FROM bank_accounts WHERE status = 'Active' ORDER BY bank_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Handle Form Submission: Create Sale Return
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['save_return'])) {
    $return_no       = trim($_POST['return_no'] ?? $auto_return_no);
    $invoice_id      = intval($_POST['invoice_id'] ?? 0);
    $customer_name   = trim($_POST['customer_name'] ?? '');
    $return_date     = trim($_POST['return_date'] ?? date('Y-m-d'));
    $refund_type     = trim($_POST['refund_type'] ?? 'Deduct Balance');
    $cash_account_id = !empty($_POST['cash_account_id']) ? intval($_POST['cash_account_id']) : null;
    $bank_account_id = !empty($_POST['bank_account_id']) ? intval($_POST['bank_account_id']) : null;
    $reason          = trim($_POST['reason'] ?? 'Customer Return');
    $items           = $_POST['items'] ?? [];

    if ($invoice_id <= 0) {
        $message = "Please select a Sales Invoice.";
        $msg_type = "danger";
    } elseif (empty($items) || !is_array($items)) {
        $message = "Please add at least one medicine item to return.";
        $msg_type = "danger";
    } else {
        try {
            $pdo->beginTransaction();
            $conn->begin_transaction();

            // ── Return price & limits come from the DB, never from the browser ──
            // sale_items.total_price already has the item discount AND the invoice
            // level discount applied, so the refund must be taken proportionally
            // from it. Using unit_price instead would refund the pre-discount
            // amount (e.g. 50.00 x 50 = 2500 instead of the billed 2300).
            $sold_map = [];
            $stmt_sold = $conn->prepare("SELECT id, product_id, item_name, quantity, total_price FROM sale_items WHERE invoice_id = ?");
            if ($stmt_sold) {
                $stmt_sold->bind_param("i", $invoice_id);
                $stmt_sold->execute();
                $res_sold = $stmt_sold->get_result();
                while ($row_sold = $res_sold->fetch_assoc()) {
                    $row_sold['product_id'] = intval($row_sold['product_id']);
                    $sold_map[$row_sold['product_id']][] = $row_sold;
                }
                $stmt_sold->close();
            }

            // Quantity already returned earlier against this invoice, so the same
            // stock cannot be returned twice.
            $returned_map = [];
            try {
                $stmt_ret_qty = $conn->prepare("SELECT sri.product_id, COALESCE(SUM(sri.quantity), 0) AS returned_qty
                    FROM sale_return_items sri
                    JOIN sale_returns sr ON (sr.id = sri.return_id OR sr.id = sri.sale_return_id)
                    WHERE (sr.sale_id = ? OR sr.invoice_id = ?) AND sr.status = 'Completed'
                    GROUP BY sri.product_id");
                if ($stmt_ret_qty) {
                    $stmt_ret_qty->bind_param("ii", $invoice_id, $invoice_id);
                    $stmt_ret_qty->execute();
                    $res_rq = $stmt_ret_qty->get_result();
                    while ($row_rq = $res_rq->fetch_assoc()) {
                        $returned_map[intval($row_rq['product_id'])] = intval($row_rq['returned_qty']);
                    }
                    $stmt_ret_qty->close();
                }
            } catch (Exception $e) {}

            $total_return_amount = 0;
            $validated_return_items = [];
            $line_cursor = [];

            foreach ($items as $itm) {
                $pid        = intval($itm['product_id'] ?? 0);
                $pname      = trim($itm['item_name'] ?? '');
                $ret_qty    = intval($itm['quantity'] ?? 0);
                $condition  = trim($itm['condition'] ?? 'Good / Resalable');

                if ($ret_qty <= 0) continue;

                if (empty($sold_map[$pid])) {
                    throw new Exception("Error: medicine '{$pname}' does not belong to the selected invoice.");
                }

                // Same product can appear on several invoice lines — consume them in order.
                $cursor = $line_cursor[$pid] ?? 0;
                $lines  = $sold_map[$pid];
                if (!isset($lines[$cursor])) {
                    throw new Exception("Error: no remaining sold quantity for medicine '{$pname}'.");
                }
                $line = $lines[$cursor];

                $sold_qty     = intval($line['quantity']);
                $line_total   = floatval($line['total_price']);
                $already_ret  = $returned_map[$pid] ?? 0;
                $consumed_ret = min($sold_qty, $already_ret);
                $available    = max(0, $sold_qty - $consumed_ret);
                $returned_map[$pid] = max(0, $already_ret - $consumed_ret);

                if ($ret_qty > $available) {
                    throw new Exception("Error: Return quantity ({$ret_qty}) for medicine '{$pname}' cannot exceed returnable quantity ({$available}).");
                }

                // Take the refund proportionally from the discounted line total.
                $line_ret = $sold_qty > 0 ? round($line_total * $ret_qty / $sold_qty, 2) : 0.0;
                $unit_price = $ret_qty > 0 ? round($line_ret / $ret_qty, 2) : 0.0;

                $total_return_amount += $line_ret;

                $validated_return_items[] = [
                    'product_id' => $pid,
                    'name'       => $pname,
                    'quantity'   => $ret_qty,
                    'price'      => $unit_price,
                    'total'      => $line_ret,
                    'condition'  => $condition
                ];

                $line_cursor[$pid] = $cursor + 1;
            }

            if (empty($validated_return_items)) {
                throw new Exception("Please enter a return quantity of 1 or more for at least one item.");
            }

            $user_id = $_SESSION['user_id'] ?? 1;

            $customer_id = 0;
            $stmt_cust = $conn->prepare("SELECT customer_id FROM sales_invoices WHERE id = ?");
            if ($stmt_cust) {
                $stmt_cust->bind_param("i", $invoice_id);
                $stmt_cust->execute();
                $res_cust = $stmt_cust->get_result()->fetch_assoc();
                if ($res_cust && isset($res_cust['customer_id'])) {
                    $customer_id = intval($res_cust['customer_id']);
                }
                $stmt_cust->close();
            }

            // 1. Insert into sale_returns table
            $stmt_ret = $pdo->prepare("
                INSERT INTO sale_returns (
                    return_no, sale_id, return_date, customer_id, refund_type,
                    total_amount, deduction_amount, net_refund_amount, reason, status, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Completed', ?)
            ");
            $stmt_ret->execute([
                $return_no, $invoice_id, $return_date, $customer_id, $refund_type,
                $total_return_amount, 0.00, $total_return_amount, $reason, $user_id
            ]);
            $return_id = $pdo->lastInsertId();

            // 2. Insert line items & restore stock
            $stmt_item = $pdo->prepare("
                INSERT INTO sale_return_items (return_id, sale_return_id, product_id, quantity, unit_price, total_price, `condition`)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_stock = $pdo->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = ?");

            foreach ($validated_return_items as $vi) {
                $stmt_item->execute([$return_id, $return_id, $vi['product_id'], $vi['quantity'], $vi['price'], $vi['total'], $vi['condition']]);
                if ($vi['condition'] === 'Good / Resalable') {
                    $stmt_stock->execute([$vi['quantity'], $vi['product_id']]);
                }
            }

            // 3. Adjust customer balance or cash/bank account if refund
            if ($refund_type === 'Store Credit' || $refund_type === 'Credit Note') {
                $pdo->prepare("UPDATE customers SET current_balance = GREATEST(0, current_balance - ?) WHERE id = ?")->execute([$total_return_amount, $customer_id]);
            } elseif ($refund_type === 'Cash Refund' && $cash_account_id) {
                $pdo->prepare("UPDATE cash_accounts SET balance = GREATEST(0, balance - ?) WHERE id = ?")->execute([$total_return_amount, $cash_account_id]);
            } elseif ($refund_type === 'Bank Refund' && $bank_account_id) {
                $pdo->prepare("UPDATE bank_accounts SET current_balance = GREATEST(0, current_balance - ?) WHERE id = ?")->execute([$total_return_amount, $bank_account_id]);
            }

            $pdo->commit();
            $conn->commit();

            $message = "Sale Return #{$return_no} saved successfully and stock restored to inventory! <a href='print_return.php?id={$return_id}' target='_blank' class='fw-bold text-dark text-decoration-underline ms-2'><i class='fa-solid fa-print'></i> Print Return Voucher</a>";
            $msg_type = "success";

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $conn->rollback();
            $message = "Error: " . $e->getMessage();
            $msg_type = "danger";
        }
    }
}

// Fetch all available Sales Invoices for the dropdown with their return status
$invoices_list = [];
$res_inv = $conn->query("
    SELECT si.id, si.invoice_no, si.customer_name, si.invoice_date, si.grand_total, si.balance_due,
           COALESCE(items_stat.total_sold_qty, 0) AS total_sold_qty,
           COALESCE(ret_stat.total_ret_qty, 0) AS total_ret_qty
    FROM sales_invoices si
    LEFT JOIN (
        SELECT invoice_id, SUM(quantity) AS total_sold_qty
        FROM sale_items
        GROUP BY invoice_id
    ) items_stat ON items_stat.invoice_id = si.id
    LEFT JOIN (
        SELECT COALESCE(sr.sale_id, sr.invoice_id) AS inv_id, SUM(sri.quantity) AS total_ret_qty
        FROM sale_returns sr
        JOIN sale_return_items sri ON (sri.return_id = sr.id OR sri.sale_return_id = sr.id)
        WHERE sr.status = 'Completed'
        GROUP BY COALESCE(sr.sale_id, sr.invoice_id)
    ) ret_stat ON ret_stat.inv_id = si.id
    ORDER BY si.id DESC LIMIT 150
");
if ($res_inv) {
    $invoices_list = $res_inv->fetch_all(MYSQLI_ASSOC);
}

// Preset Invoice ID from URL if provided
$selected_invoice_id = intval($_GET['invoice_id'] ?? 0);
$selected_invoice = null;
$selected_items = [];
$existing_invoice_returns = [];
$already_returned = [];
$total_inv_sold = 0;
$total_inv_ret = 0;
$total_available_to_return = 0;

if ($selected_invoice_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM sales_invoices WHERE id = ?");
    $stmt->bind_param("i", $selected_invoice_id);
    $stmt->execute();
    $r = $stmt->get_result();
    $selected_invoice = $r->fetch_assoc();
    $stmt->close();

    if ($selected_invoice) {
        $stmt_items = $conn->prepare("SELECT * FROM sale_items WHERE invoice_id = ?");
        $stmt_items->bind_param("i", $selected_invoice_id);
        $stmt_items->execute();
        $r_items = $stmt_items->get_result();
        $selected_items = $r_items->fetch_all(MYSQLI_ASSOC);
        $stmt_items->close();

        // How much of each product was already returned
        try {
            $stmt_ar = $conn->prepare("SELECT sri.product_id, COALESCE(SUM(sri.quantity), 0) AS returned_qty
                FROM sale_return_items sri
                JOIN sale_returns sr ON (sr.id = sri.return_id OR sr.id = sri.sale_return_id)
                WHERE (sr.sale_id = ? OR sr.invoice_id = ?) AND sr.status = 'Completed'
                GROUP BY sri.product_id");
            if ($stmt_ar) {
                $stmt_ar->bind_param("ii", $selected_invoice_id, $selected_invoice_id);
                $stmt_ar->execute();
                $res_ar = $stmt_ar->get_result();
                while ($row_ar = $res_ar->fetch_assoc()) {
                    $already_returned[intval($row_ar['product_id'])] = intval($row_ar['returned_qty']);
                    $total_inv_ret += intval($row_ar['returned_qty']);
                }
                $stmt_ar->close();
            }

            // Existing return vouchers for this invoice
            $stmt_eir = $conn->prepare("SELECT id, return_no, return_date, total_amount, status 
                FROM sale_returns 
                WHERE (sale_id = ? OR invoice_id = ?) AND status = 'Completed' 
                ORDER BY id DESC");
            if ($stmt_eir) {
                $stmt_eir->bind_param("ii", $selected_invoice_id, $selected_invoice_id);
                $stmt_eir->execute();
                $existing_invoice_returns = $stmt_eir->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt_eir->close();
            }
        } catch (Exception $e) {}

        // Calculate available returnable quantity across items
        $ret_pool = $already_returned;
        foreach ($selected_items as $it) {
            $pid = intval($it['product_id']);
            $sold_q = intval($it['quantity']);
            $total_inv_sold += $sold_q;

            $already_ret_this = $ret_pool[$pid] ?? 0;
            $consume = min($sold_q, $already_ret_this);
            $ret_avail = max(0, $sold_q - $consume);
            $ret_pool[$pid] = max(0, $already_ret_this - $consume);
            $total_available_to_return += $ret_avail;
        }
    }
}

// Fetch Recent Sales Returns
$recent_returns = [];
if ($db_connected && $pdo) {
    try {
        $recent_returns = $pdo->query("
            SELECT sr.*, COUNT(sri.id) as total_items_count
            FROM sale_returns sr
            LEFT JOIN sale_return_items sri ON (sri.return_id = sr.id OR sri.sale_return_id = sr.id)
            GROUP BY sr.id
            ORDER BY sr.id DESC LIMIT 15
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

?>

<style>
    .page-title-badge {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        box-shadow: 0 4px 14px rgba(239, 68, 68, 0.25);
    }
    .return-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 4px 24px rgba(15, 23, 42, 0.04);
    }
    .section-tag {
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: #ef4444;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        background: rgba(239, 68, 68, 0.08);
        border-radius: 20px;
        margin-bottom: 12px;
    }
    .table-return th {
        background-color: #f8fafc;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #475569;
        padding: 12px;
        border-bottom: 2px solid #e2e8f0;
    }
    .table-return td {
        padding: 10px 12px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.9rem;
    }
</style>

<!-- Top Title Bar -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <div class="page-title-badge">
            <i class="fa-solid fa-undo"></i>
        </div>
        <div>
            <h4 class="fw-bold mb-0 text-dark">Customer Sales Return</h4>
            <p class="text-muted small mb-0">Record returned medicines against sales invoice, restock warehouse & issue credit</p>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="sales.php" class="btn btn-outline-secondary bg-white shadow-sm px-3 rounded-3">
            <i class="fa-solid fa-receipt me-1"></i> Sales Invoices
        </a>
    </div>
</div>

<!-- Alert Notifications -->
<?php if (!empty($message)): ?>
    <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show border-0 shadow-sm rounded-3 d-flex align-items-center mb-4" role="alert">
        <i class="fa-solid <?= $msg_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> fs-5 me-2"></i>
        <div class="fw-medium"><?= $message ?></div>
        <button type="button" class="close ml-auto" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
<?php endif; ?>

<!-- Return Creation Form -->
<form action="" method="POST" id="returnForm">
    <div class="return-card p-4 p-md-5 mb-4">

        <div class="mb-4 pb-3 border-bottom">
            <div class="section-tag"><i class="fa-solid fa-file-invoice"></i> Step 1: Select Sales Invoice</div>
            
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-muted mb-1">Select Sales Invoice <span class="text-danger">*</span></label>
                    <select name="invoice_id" id="invoiceSelect" class="form-select" onchange="loadInvoiceForReturn(this.value)" required>
                        <option value="">-- Choose Sales Invoice --</option>
                        <?php foreach ($invoices_list as $inv): 
                            $s_qty = (int)($inv['total_sold_qty'] ?? 0);
                            $r_qty = (int)($inv['total_ret_qty'] ?? 0);
                            $is_fully = ($s_qty > 0 && $r_qty >= $s_qty);
                            $is_part  = ($r_qty > 0 && $r_qty < $s_qty);
                            $badge_txt = $is_fully ? ' — [FULLY RETURNED (Pura Return Ho Chuka)]' : ($is_part ? ' — [Partially Returned (' . ($s_qty - $r_qty) . ' remaining)]' : '');
                        ?>
                            <option value="<?= $inv['id'] ?>" <?= ($selected_invoice_id == $inv['id']) ? 'selected' : '' ?> style="<?= $is_fully ? 'color:#94a3b8;background:#f8fafc;' : '' ?>">
                                <?= htmlspecialchars($inv['invoice_no']) ?> - <?= htmlspecialchars($inv['customer_name']) ?> (Rs. <?= number_format($inv['grand_total'], 2) ?>)<?= $badge_txt ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted mb-1">Return Voucher Number</label>
                    <input type="text" name="return_no" class="form-control font-monospace fw-bold bg-light text-danger" value="<?= htmlspecialchars($auto_return_no) ?>" readonly>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Return Date</label>
                    <input type="date" name="return_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <?php if ($selected_invoice): ?>
                <div class="alert alert-info border-0 rounded-3 mt-3 d-flex justify-content-between align-items-center mb-0">
                    <div>
                        <strong class="fs-6"><?= htmlspecialchars($selected_invoice['customer_name']) ?></strong>
                        <span class="text-muted ms-2">(Invoice Date: <?= date('d M Y', strtotime($selected_invoice['invoice_date'])) ?>)</span>
                    </div>
                    <div>
                        <span>Billed Total: <strong>Rs. <?= number_format($selected_invoice['grand_total'], 2) ?></strong></span>
                    </div>
                </div>
                <input type="hidden" name="customer_name" value="<?= htmlspecialchars($selected_invoice['customer_name']) ?>">
            <?php endif; ?>
        </div>

        <!-- Step 2: Returned Products Table -->
        <div class="mb-4">
            <div class="section-tag"><i class="fa-solid fa-pills"></i> Step 2: Invoiced Products to Return</div>

            <?php if (empty($selected_items)): ?>
                <div class="p-5 text-center border rounded-3 bg-light text-muted">
                    <i class="fa-solid fa-hand-pointer fs-2 mb-2 text-secondary d-block"></i>
                    Please <strong>select a Sales Invoice</strong> above to load its medicines.
                </div>
            <?php elseif ($total_available_to_return <= 0): ?>
                <div class="alert alert-warning border-warning shadow-sm rounded-3 p-4 mb-3">
                    <div class="d-flex align-items-start gap-3">
                        <i class="fa-solid fa-triangle-exclamation text-warning fs-2 mt-1"></i>
                        <div class="flex-grow-1">
                            <h5 class="fw-bold mb-1 text-dark">Is Invoice ke tamam items pehle hi mukammal tor par return ho chuke hain! (Fully Returned)</h5>
                            <p class="text-muted mb-2">
                                Total Sold: <strong class="text-dark"><?= $total_inv_sold ?> units</strong> &middot; 
                                Total Already Returned: <strong class="text-danger"><?= $total_inv_ret ?> units</strong>. 
                                Is invoice me mazeed return karne ke liye koi quantity baqi nahi hai.
                            </p>
                            <?php if (!empty($existing_invoice_returns)): ?>
                                <div class="p-3 bg-white rounded border mt-2">
                                    <strong class="text-dark d-block mb-1"><i class="fa-solid fa-file-invoice text-danger me-1"></i> Pehle se darj shuda Return Vouchers:</strong>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($existing_invoice_returns as $eir): ?>
                                            <a href="print_return.php?id=<?= $eir['id'] ?>" target="_blank" class="btn btn-sm btn-outline-danger font-monospace">
                                                <i class="fa-solid fa-print me-1"></i><?= htmlspecialchars($eir['return_no']) ?> &mdash; Rs. <?= number_format($eir['total_amount'], 2) ?> (<?= date('d M Y', strtotime($eir['return_date'])) ?>)
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="mt-3">
                                <a href="sale_return.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i> Dusri Invoice Select Karein</a>
                                <a href="sales.php" class="btn btn-outline-primary btn-sm ms-2"><i class="fa-solid fa-list me-1"></i> View All Sales</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                    <span class="text-muted small">Return ke liye available medicines: <strong class="text-success"><?= $total_available_to_return ?> units</strong></span>
                    <button type="button" class="btn btn-sm btn-outline-danger fw-bold" onclick="setAllRowsMax()">
                        <i class="fa-solid fa-check-double me-1"></i> Return Full Invoice (Tamam Return Karein)
                    </button>
                </div>
                <div class="table-responsive border rounded-3 overflow-hidden shadow-sm mb-3">
                    <table class="table table-return mb-0">
                        <thead>
                            <tr>
                                <th style="width: 45px;" class="text-center">#</th>
                                <th>Medicine / Product Name</th>
                                <th style="width: 110px;" class="text-center">Sold Qty</th>
                                <th style="width: 180px;" class="text-center">Return Qty <span class="text-danger">*</span></th>
                                <th style="width: 140px;" class="text-end">Refund Rate</th>
                                <th style="width: 180px;">Condition</th>
                                <th style="width: 140px;" class="text-end">Refund Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $ret_pool = $already_returned;
                            foreach ($selected_items as $idx => $it):
                                $pid = intval($it['product_id']);
                                $sold_q = intval($it['quantity']);
                                $tp = $sold_q > 0 ? round(floatval($it['total_price']) / $sold_q, 2) : 0.0;
                                $already_this = $ret_pool[$pid] ?? 0;
                                $consume = min($sold_q, $already_this);
                                $returnable = max(0, $sold_q - $consume);
                                $ret_pool[$pid] = max(0, $already_this - $consume);
                            ?>
                                <tr>
                                    <td class="text-center text-muted fw-bold"><?= $idx + 1 ?></td>
                                    <td>
                                        <strong class="text-dark"><?= htmlspecialchars($it['item_name']) ?></strong>
                                        <input type="hidden" name="items[<?= $idx ?>][product_id]" value="<?= $it['product_id'] ?>">
                                        <input type="hidden" name="items[<?= $idx ?>][item_name]" value="<?= htmlspecialchars($it['item_name']) ?>">
                                        <input type="hidden" name="items[<?= $idx ?>][unit_price]" id="price_<?= $idx ?>" value="<?= $tp ?>">
                                        <input type="hidden" name="items[<?= $idx ?>][max_qty]" value="<?= $returnable ?>">
                                    </td>
                                    <td class="text-center fw-bold text-secondary">
                                        <?= $sold_q ?>
                                        <?php if ($consume > 0): ?>
                                            <small class="d-block text-danger font-weight-normal" style="font-size:10px;">-<?= $consume ?> returned</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($returnable > 0): ?>
                                            <div class="input-group input-group-sm justify-content-center" style="max-width: 165px; margin: 0 auto;">
                                                <button type="button" class="btn btn-outline-secondary px-2" onclick="adjustQty(<?= $idx ?>, -1, <?= $returnable ?>)" title="Decrease">-</button>
                                                <input type="number" 
                                                       name="items[<?= $idx ?>][quantity]" 
                                                       id="qty_<?= $idx ?>" 
                                                       class="form-control form-control-sm text-center fw-bold text-danger ret-qty-input" 
                                                       min="0" 
                                                       max="<?= $returnable ?>" 
                                                       value="0" 
                                                       onfocus="if(this.value==='0') this.value='';" 
                                                       onblur="if(this.value==='') { this.value='0'; calculateReturnRow(<?= $idx ?>, <?= $returnable ?>); }"
                                                       oninput="calculateReturnRow(<?= $idx ?>, <?= $returnable ?>)">
                                                <button type="button" class="btn btn-outline-secondary px-2" onclick="adjustQty(<?= $idx ?>, 1, <?= $returnable ?>)" title="Increase">+</button>
                                                <button type="button" class="btn btn-danger px-2 fw-bold" onclick="setRowMax(<?= $idx ?>, <?= $returnable ?>)" title="Return All Available">All</button>
                                            </div>
                                            <small class="text-muted d-block text-center mt-1" style="font-size: 10px;">Available: <strong><?= $returnable ?></strong></small>
                                        <?php else: ?>
                                            <input type="hidden" name="items[<?= $idx ?>][quantity]" value="0">
                                            <span class="badge bg-light text-muted border">fully returned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end font-monospace">Rs. <?= number_format($tp, 2) ?></td>
                                    <td>
                                        <select name="items[<?= $idx ?>][condition]" class="form-select form-select-sm">
                                            <option value="Good / Resalable">Good (Restock in Store)</option>
                                            <option value="Damaged / Expiry Claim">Damaged / Expiry (Discard)</option>
                                        </select>
                                    </td>
                                    <td class="text-end font-monospace fw-bold text-dark">
                                        <span id="rowTotal_<?= $idx ?>">Rs. 0.00</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Step 3: Refund Settlement & Reason -->
                <div class="row g-4 pt-3 border-top justify-content-between align-items-start">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted mb-1">Return Reason / Remarks</label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="Customer excess order, expired stock claim, packaging damaged etc..."></textarea>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <div class="p-3 border rounded bg-light">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted mb-1">Refund Method</label>
                                <select name="refund_type" id="refundTypeSelect" class="form-select fw-semibold" onchange="toggleAccountDropdown()">
                                    <option value="Deduct Balance">Adjust / Deduct Customer Balance</option>
                                    <option value="Cash Refund">Cash Refund (Pay from Cash Account)</option>
                                    <option value="Bank Refund">Bank Transfer Refund</option>
                                </select>
                            </div>

                            <div class="mb-3 d-none" id="cashAccountBox">
                                <label class="form-label small fw-bold text-muted mb-1">Cash Account</label>
                                <select name="cash_account_id" class="form-select">
                                    <?php foreach ($cash_accounts as $ca): ?>
                                        <option value="<?= $ca['id'] ?>"><?= htmlspecialchars($ca['account_name']) ?> (Bal: Rs. <?= number_format($ca['balance'], 2) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3 d-none" id="bankAccountBox">
                                <label class="form-label small fw-bold text-muted mb-1">Bank Account</label>
                                <select name="bank_account_id" class="form-select">
                                    <?php foreach ($bank_accounts as $ba): ?>
                                        <option value="<?= $ba['id'] ?>"><?= htmlspecialchars($ba['bank_name']) ?> (<?= htmlspecialchars($ba['account_title']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <span class="fw-bold text-dark fs-6">Total Return Refund:</span>
                                <span class="fw-bold text-danger fs-5 font-monospace" id="grandRefundTotalDisplay">Rs. 0.00</span>
                            </div>

                            <div class="mt-4">
                                <button type="submit" name="save_return" id="saveReturnBtn" class="btn btn-danger w-100 fw-bold py-2 shadow-sm text-uppercase">
                                    <i class="fa-solid fa-check me-1"></i> Confirm &amp; Save Return
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>

    </div>
</form>

<!-- Recent Sales Returns Table -->
<?php if (!empty($recent_returns)): ?>
    <div class="return-card p-4">
        <div class="section-tag mb-3"><i class="fa-solid fa-history"></i> Recent Sales Return Records</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr class="table-light">
                        <th>Return #</th>
                        <th>Date</th>
                        <th>Return Reason</th>
                        <th>Refund Method</th>
                        <th class="text-center">Items</th>
                        <th class="text-end">Refund Amount</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_returns as $rr): ?>
                        <tr>
                            <td><strong class="text-danger font-monospace"><?= htmlspecialchars($rr['return_no']) ?></strong></td>
                            <td><?= date('d M Y', strtotime($rr['return_date'])) ?></td>
                            <td><?= htmlspecialchars($rr['reason'] ?: 'Customer Return') ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($rr['refund_type']) ?></span></td>
                            <td class="text-center"><?= intval($rr['total_items_count']) ?> items</td>
                            <td class="text-end font-monospace fw-bold text-dark">Rs. <?= number_format($rr['total_amount'], 2) ?></td>
                            <td class="text-center">
                                <a href="print_return.php?id=<?= $rr['id'] ?>" target="_blank" class="btn btn-outline-primary btn-sm px-2 py-1" title="Print Return Voucher">
                                    <i class="fa-solid fa-print"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<script>
    function loadInvoiceForReturn(invId) {
        if (invId) {
            window.location.href = 'sale_return.php?invoice_id=' + invId;
        }
    }

    function calculateReturnRow(idx, maxQty) {
        const qtyInput = document.getElementById('qty_' + idx);
        if (!qtyInput) return;
        let qty = parseInt(qtyInput.value) || 0;

        if (qty > maxQty) {
            alert('⚠️ Return quantity cannot exceed available quantity (' + maxQty + ')!');
            qty = maxQty;
            qtyInput.value = maxQty;
        } else if (qty < 0) {
            qty = 0;
            qtyInput.value = 0;
        }

        const price = parseFloat(document.getElementById('price_' + idx).value) || 0;
        const total = qty * price;
        const rowTotalEl = document.getElementById('rowTotal_' + idx);
        if (rowTotalEl) {
            rowTotalEl.textContent = 'Rs. ' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        recalculateGrandRefund();
    }

    function adjustQty(idx, delta, maxQty) {
        const qtyInput = document.getElementById('qty_' + idx);
        if (!qtyInput) return;
        let current = parseInt(qtyInput.value) || 0;
        let updated = current + delta;
        if (updated < 0) updated = 0;
        if (updated > maxQty) updated = maxQty;
        qtyInput.value = updated;
        calculateReturnRow(idx, maxQty);
    }

    function setRowMax(idx, maxQty) {
        const qtyInput = document.getElementById('qty_' + idx);
        if (!qtyInput) return;
        qtyInput.value = maxQty;
        calculateReturnRow(idx, maxQty);
    }

    function setAllRowsMax() {
        document.querySelectorAll('.ret-qty-input').forEach(input => {
            const idx = input.id.replace('qty_', '');
            const max = parseInt(input.getAttribute('max')) || 0;
            input.value = max;
            calculateReturnRow(idx, max);
        });
    }

    function recalculateGrandRefund() {
        let grand = 0;
        document.querySelectorAll('.ret-qty-input').forEach(input => {
            const idx = input.id.replace('qty_', '');
            const q = parseInt(input.value) || 0;
            const p = parseFloat(document.getElementById('price_' + idx).value) || 0;
            grand += (q * p);
        });
        const el = document.getElementById('grandRefundTotalDisplay');
        if (el) el.textContent = 'Rs. ' + grand.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function toggleAccountDropdown() {
        const typeSelect = document.getElementById('refundTypeSelect');
        if (!typeSelect) return;
        const type = typeSelect.value;
        const cBox = document.getElementById('cashAccountBox');
        const bBox = document.getElementById('bankAccountBox');

        if (cBox) cBox.classList.add('d-none');
        if (bBox) bBox.classList.add('d-none');

        if (type === 'Cash Refund' && cBox) cBox.classList.remove('d-none');
        if (type === 'Bank Refund' && bBox) bBox.classList.remove('d-none');
    }

    document.addEventListener('DOMContentLoaded', function() {
        const returnForm = document.getElementById('returnForm');
        if (returnForm) {
            returnForm.addEventListener('submit', function(e) {
                const qtyInputs = document.querySelectorAll('.ret-qty-input');
                if (qtyInputs.length === 0) {
                    return; // No items loaded or fully returned
                }

                let totalReturnQty = 0;
                qtyInputs.forEach(input => {
                    totalReturnQty += (parseInt(input.value) || 0);
                });

                if (totalReturnQty <= 0) {
                    e.preventDefault();
                    alert('⚠️ Barahe karam kam az kam 1 item ki return quantity 1 ya zyada darj karein!\n(Please enter a return quantity of 1 or more for at least one item.)');
                    const firstInput = document.querySelector('.ret-qty-input');
                    if (firstInput) {
                        firstInput.focus();
                        firstInput.select();
                    }
                    return false;
                }
            });
        }
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
