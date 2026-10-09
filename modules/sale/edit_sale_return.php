<?php
/**
 * Bestway Wholesale Distribution - Edit Customer Sale Return
 * Modify returned medicines, adjust restored inventory & update financial accounts
 */
$page_title = "Edit Customer Sale Return";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(['admin']);

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: sale_return.php");
    exit;
}

$message = "";
$msg_type = "";

// Fetch Return Record
$stmt = $pdo->prepare("SELECT * FROM sale_returns WHERE id = ?");
$stmt->execute([$id]);
$return_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$return_data) {
    require_once __DIR__ . '/../../includes/header.php';
    echo '<div class="alert alert-danger m-4 shadow-sm rounded-3">Sale Return record not found. <a href="sale_return.php" class="btn btn-sm btn-outline-danger ms-3">Go Back to Sales Returns</a></div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
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

$invoice_id = !empty($return_data['invoice_id']) ? intval($return_data['invoice_id']) : intval($return_data['sale_id'] ?? 0);
$customer_id = intval($return_data['customer_id'] ?? 0);

// Fetch Original Invoice
$selected_invoice = null;
if ($invoice_id > 0) {
    $stmt_inv = $conn->prepare("SELECT * FROM sales_invoices WHERE id = ?");
    if ($stmt_inv) {
        $stmt_inv->bind_param("i", $invoice_id);
        $stmt_inv->execute();
        $selected_invoice = $stmt_inv->get_result()->fetch_assoc();
        $stmt_inv->close();
    }
}

// Fetch original invoice sold items (or existing return items if direct return)
$invoice_sold_items = [];
if ($invoice_id > 0) {
    $stmt_items = $conn->prepare("SELECT * FROM sale_items WHERE invoice_id = ?");
    if ($stmt_items) {
        $stmt_items->bind_param("i", $invoice_id);
        $stmt_items->execute();
        $invoice_sold_items = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_items->close();
    }
} else {
    $stmt_items = $pdo->prepare("
        SELECT sri.product_id, COALESCE(NULLIF(p.name, ''), 'Item') as item_name, sri.quantity, sri.unit_price, sri.total_price
        FROM sale_return_items sri
        LEFT JOIN products p ON p.id = sri.product_id
        WHERE sri.return_id = ? OR sri.sale_return_id = ?
        ORDER BY sri.id ASC
    ");
    $stmt_items->execute([$id, $id]);
    $invoice_sold_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch existing items for THIS return
$current_return_items = [];
$stmt_curr = $pdo->prepare("SELECT * FROM sale_return_items WHERE return_id = ? OR sale_return_id = ?");
$stmt_curr->execute([$id, $id]);
foreach ($stmt_curr->fetchAll(PDO::FETCH_ASSOC) as $cri) {
    $current_return_items[intval($cri['product_id'])] = $cri;
}

// Fetch quantities returned in OTHER returns for this invoice (excluding this return)
$other_returns_map = [];
try {
    $stmt_other = $conn->prepare("SELECT sri.product_id, COALESCE(SUM(sri.quantity), 0) AS returned_qty
        FROM sale_return_items sri
        JOIN sale_returns sr ON (sr.id = sri.return_id OR sr.id = sri.sale_return_id)
        WHERE (sr.sale_id = ? OR sr.invoice_id = ?) AND sr.status = 'Completed' AND sr.id != ?
        GROUP BY sri.product_id");
    if ($stmt_other) {
        $stmt_other->bind_param("iii", $invoice_id, $invoice_id, $id);
        $stmt_other->execute();
        $res_other = $stmt_other->get_result();
        while ($row_o = $res_other->fetch_assoc()) {
            $other_returns_map[intval($row_o['product_id'])] = intval($row_o['returned_qty']);
        }
        $stmt_other->close();
    }
} catch (Exception $e) {}

// Handle Form Submission: Update Sale Return
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['update_return'])) {
    $return_date     = trim($_POST['return_date'] ?? date('Y-m-d'));
    $refund_type     = trim($_POST['refund_type'] ?? 'Deduct Balance');
    $cash_account_id = !empty($_POST['cash_account_id']) ? intval($_POST['cash_account_id']) : null;
    $bank_account_id = !empty($_POST['bank_account_id']) ? intval($_POST['bank_account_id']) : null;
    $reason          = trim($_POST['reason'] ?? 'Customer Return');
    $items           = $_POST['items'] ?? [];

    if (empty($items) || !is_array($items)) {
        $message = "Please enter return quantities for medicines.";
        $msg_type = "danger";
    } else {
        try {
            $pdo->beginTransaction();
            $conn->begin_transaction();

            // 1. Fetch current items in DB to revert prior stock restoration
            $stmt_old_items = $pdo->prepare("SELECT product_id, quantity, `condition` FROM sale_return_items WHERE return_id = ? OR sale_return_id = ?");
            $stmt_old_items->execute([$id, $id]);
            $old_items = $stmt_old_items->fetchAll(PDO::FETCH_ASSOC);

            $stmt_sub_stock = $pdo->prepare("UPDATE products SET current_stock = GREATEST(0, current_stock - ?) WHERE id = ?");
            foreach ($old_items as $oi) {
                $cond = $oi['condition'] ?? 'Good';
                if ($cond === 'Good / Resalable' || $cond === 'Good') {
                    $stmt_sub_stock->execute([$oi['quantity'], $oi['product_id']]);
                }
            }

            // 2. Revert previous financial impact
            $old_total = floatval($return_data['total_amount']);
            $old_ref_type = $return_data['refund_type'] ?? '';
            $old_cash_id = !empty($return_data['cash_account_id']) ? intval($return_data['cash_account_id']) : 0;
            $old_bank_id = !empty($return_data['bank_account_id']) ? intval($return_data['bank_account_id']) : 0;

            if ($old_ref_type === 'Cash Refund' && $old_cash_id > 0) {
                $pdo->prepare("UPDATE cash_accounts SET balance = balance + ? WHERE id = ?")->execute([$old_total, $old_cash_id]);
            } elseif ($old_ref_type === 'Bank Refund' && $old_bank_id > 0) {
                $pdo->prepare("UPDATE bank_accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$old_total, $old_bank_id]);
            } elseif ($old_ref_type === 'Deduct Balance' || $old_ref_type === 'Store Credit' || $old_ref_type === 'Credit Note' || $old_ref_type === 'Adjust / Deduct Customer Balance') {
                if ($customer_id > 0 && $old_total > 0) {
                    $pdo->prepare("UPDATE customers SET current_balance = current_balance + ? WHERE id = ?")->execute([$old_total, $customer_id]);
                }
            }

            // 3. Process updated items
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

            $validated_return_items = [];
            $total_return_amount = 0;
            $line_cursor = [];
            $other_ret_pool = $other_returns_map;

            foreach ($items as $itm) {
                $pid       = intval($itm['product_id'] ?? 0);
                $pname     = trim($itm['item_name'] ?? '');
                $ret_qty   = intval($itm['quantity'] ?? 0);
                $condition = trim($itm['condition'] ?? 'Good / Resalable');

                if ($ret_qty <= 0) continue;

                if ($invoice_id > 0) {
                    if (empty($sold_map[$pid])) {
                        throw new Exception("Error: medicine '{$pname}' does not belong to the invoice.");
                    }

                    $cursor = $line_cursor[$pid] ?? 0;
                    $lines  = $sold_map[$pid];
                    if (!isset($lines[$cursor])) {
                        throw new Exception("Error: no sold line found for medicine '{$pname}'.");
                    }
                    $line = $lines[$cursor];

                    $sold_qty    = intval($line['quantity']);
                    $line_total  = floatval($line['total_price']);
                    $other_ret   = $other_ret_pool[$pid] ?? 0;
                    $consumed    = min($sold_qty, $other_ret);
                    $available   = max(0, $sold_qty - $consumed);
                    $other_ret_pool[$pid] = max(0, $other_ret - $consumed);

                    if ($ret_qty > $available) {
                        throw new Exception("Error: Return quantity ({$ret_qty}) for medicine '{$pname}' cannot exceed available quantity ({$available}).");
                    }

                    $line_ret   = $sold_qty > 0 ? round($line_total * $ret_qty / $sold_qty, 2) : 0.0;
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
                } else {
                    // Direct Return item validation
                    $unit_price = floatval($itm['unit_price'] ?? 0);
                    $line_ret   = round($unit_price * $ret_qty, 2);
                    $total_return_amount += $line_ret;

                    $validated_return_items[] = [
                        'product_id' => $pid,
                        'name'       => $pname,
                        'quantity'   => $ret_qty,
                        'price'      => $unit_price,
                        'total'      => $line_ret,
                        'condition'  => $condition
                    ];
                }
            }

            if (empty($validated_return_items)) {
                throw new Exception("Please enter a return quantity of 1 or more for at least one medicine.");
            }

            // 4. Delete old items and insert updated items
            $pdo->prepare("DELETE FROM sale_return_items WHERE return_id = ? OR sale_return_id = ?")->execute([$id, $id]);

            $stmt_ins_item = $pdo->prepare("
                INSERT INTO sale_return_items (return_id, sale_return_id, product_id, quantity, unit_price, total_price, `condition`)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_add_stock = $pdo->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = ?");

            foreach ($validated_return_items as $vi) {
                $stmt_ins_item->execute([$id, $id, $vi['product_id'], $vi['quantity'], $vi['price'], $vi['total'], $vi['condition']]);
                if ($vi['condition'] === 'Good / Resalable') {
                    $stmt_add_stock->execute([$vi['quantity'], $vi['product_id']]);
                }
            }

            // 5. Apply new financial settlement
            if ($refund_type === 'Store Credit' || $refund_type === 'Credit Note' || $refund_type === 'Deduct Balance' || $refund_type === 'Adjust / Deduct Customer Balance') {
                if ($customer_id > 0) {
                    $pdo->prepare("UPDATE customers SET current_balance = GREATEST(0, current_balance - ?) WHERE id = ?")->execute([$total_return_amount, $customer_id]);
                }
            } elseif ($refund_type === 'Cash Refund' && $cash_account_id) {
                $pdo->prepare("UPDATE cash_accounts SET balance = GREATEST(0, balance - ?) WHERE id = ?")->execute([$total_return_amount, $cash_account_id]);
            } elseif ($refund_type === 'Bank Refund' && $bank_account_id) {
                $pdo->prepare("UPDATE bank_accounts SET current_balance = GREATEST(0, current_balance - ?) WHERE id = ?")->execute([$total_return_amount, $bank_account_id]);
            }

            // 6. Update sale_returns header
            $stmt_up_ret = $pdo->prepare("
                UPDATE sale_returns SET
                    return_date = ?,
                    refund_type = ?,
                    total_amount = ?,
                    net_refund_amount = ?,
                    reason = ?,
                    cash_account_id = ?,
                    bank_account_id = ?
                WHERE id = ?
            ");
            $stmt_up_ret->execute([
                $return_date,
                $refund_type,
                $total_return_amount,
                $total_return_amount,
                $reason,
                $cash_account_id,
                $bank_account_id,
                $id
            ]);

            $pdo->commit();
            $conn->commit();

            if ($customer_id > 0 && function_exists('updateCustomerBalance')) {
                try { updateCustomerBalance($pdo, $customer_id); } catch (Exception $e) {}
            }

            // Reload fresh return data
            $stmt->execute([$id]);
            $return_data = $stmt->fetch(PDO::FETCH_ASSOC);

            // Reload current items
            $current_return_items = [];
            $stmt_curr->execute([$id, $id]);
            foreach ($stmt_curr->fetchAll(PDO::FETCH_ASSOC) as $cri) {
                $current_return_items[intval($cri['product_id'])] = $cri;
            }

            $message = "Sale Return #{$return_data['return_no']} updated successfully! <a href='print_return.php?id={$id}' target='_blank' class='fw-bold text-dark text-decoration-underline ms-2'><i class='fa-solid fa-print'></i> Print Voucher</a>";
            $msg_type = "success";

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $conn->rollback();
            $message = "Error: " . $e->getMessage();
            $msg_type = "danger";
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    .page-title-badge {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        box-shadow: 0 4px 14px rgba(245, 158, 11, 0.25);
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
        color: #d97706;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        background: rgba(245, 158, 11, 0.1);
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
            <i class="fa-solid fa-edit"></i>
        </div>
        <div>
            <h4 class="fw-bold mb-0 text-dark">Edit Sale Return #<?= htmlspecialchars($return_data['return_no']) ?></h4>
            <p class="text-muted small mb-0">Modify returned items, conditions, refund settlement and restore stock levels</p>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="sale_return.php" class="btn btn-outline-secondary bg-white shadow-sm px-3 rounded-3">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Returns
        </a>
        <a href="print_return.php?id=<?= $id ?>" target="_blank" class="btn btn-outline-primary bg-white shadow-sm px-3 rounded-3">
            <i class="fa-solid fa-print me-1"></i> Print Voucher
        </a>
    </div>
</div>

<!-- Alert Notifications -->
<?php if (!empty($message)): ?>
    <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show border-0 shadow-sm rounded-3 d-flex align-items-center mb-4" role="alert">
        <i class="fa-solid <?= $msg_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> fs-5 me-2"></i>
        <div class="fw-medium"><?= $message ?></div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Return Edit Form -->
<form action="" method="POST" id="editReturnForm">
    <div class="return-card p-4 p-md-5 mb-4">

        <div class="mb-4 pb-3 border-bottom">
            <div class="section-tag"><i class="fa-solid fa-file-invoice"></i> Return Details &amp; Original Invoice</div>
            
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted mb-1">Return Voucher Number</label>
                    <input type="text" class="form-control font-monospace fw-bold bg-light text-danger" value="<?= htmlspecialchars($return_data['return_no']) ?>" readonly>
                </div>

                <div class="col-md-5">
                    <label class="form-label small fw-bold text-muted mb-1">Original Sales Invoice</label>
                    <?php
                    $inv_display_val = 'Direct Return (No Invoice)';
                    if ($selected_invoice) {
                        $inv_display_val = ($selected_invoice['invoice_no'] ?? 'INV') . ' — ' . ($selected_invoice['customer_name'] ?? 'Customer') . ' (Rs. ' . number_format($selected_invoice['grand_total'] ?? 0, 2) . ')';
                    } elseif ($customer_id > 0) {
                        try {
                            $c_stmt = $pdo->prepare("SELECT name, shop_name FROM customers WHERE id = ?");
                            $c_stmt->execute([$customer_id]);
                            $c_row = $c_stmt->fetch(PDO::FETCH_ASSOC);
                            if ($c_row) {
                                $inv_display_val = 'Direct Return (No Invoice) — ' . (!empty($c_row['shop_name']) ? ($c_row['shop_name'] . ' [' . $c_row['name'] . ']') : $c_row['name']);
                            }
                        } catch (Exception $e) {}
                    }
                    ?>
                    <input type="text" class="form-control bg-light fw-semibold" value="<?= htmlspecialchars($inv_display_val) ?>" readonly>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Return Date</label>
                    <input type="date" name="return_date" class="form-control" value="<?= htmlspecialchars($return_data['return_date'] ?? date('Y-m-d')) ?>" required>
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
            <?php endif; ?>
        </div>

        <!-- Returned Products Table -->
        <div class="mb-4">
            <div class="section-tag"><i class="fa-solid fa-pills"></i> Edit Returned Products</div>

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
                        $other_pool = $other_returns_map;
                        $init_grand_refund = 0;
                        foreach ($invoice_sold_items as $idx => $it):
                            $pid = intval($it['product_id']);
                            $sold_q = intval($it['quantity']);
                            $tp = floatval($it['unit_price'] ?? ($sold_q > 0 ? round(floatval($it['total_price']) / $sold_q, 2) : 0.0));
                            
                            if ($invoice_id > 0) {
                                $other_ret = $other_pool[$pid] ?? 0;
                                $consume = min($sold_q, $other_ret);
                                $max_avail = max(0, $sold_q - $consume);
                                $other_pool[$pid] = max(0, $other_ret - $consume);
                            } else {
                                $consume = 0;
                                $max_avail = 99999;
                            }

                            $curr_item = $current_return_items[$pid] ?? null;
                            $current_qty = $curr_item ? intval($curr_item['quantity']) : ($invoice_id <= 0 ? $sold_q : 0);
                            $current_cond = $curr_item ? ($curr_item['condition'] ?? 'Good / Resalable') : 'Good / Resalable';
                            $row_refund = round($current_qty * $tp, 2);
                            $init_grand_refund += $row_refund;
                        ?>
                            <tr>
                                <td class="text-center text-muted fw-bold"><?= $idx + 1 ?></td>
                                <td>
                                    <strong class="text-dark"><?= htmlspecialchars($it['item_name']) ?></strong>
                                    <input type="hidden" name="items[<?= $idx ?>][product_id]" value="<?= $it['product_id'] ?>">
                                    <input type="hidden" name="items[<?= $idx ?>][item_name]" value="<?= htmlspecialchars($it['item_name']) ?>">
                                    <input type="hidden" name="items[<?= $idx ?>][unit_price]" id="price_<?= $idx ?>" value="<?= $tp ?>">
                                    <input type="hidden" name="items[<?= $idx ?>][max_qty]" value="<?= $max_avail ?>">
                                </td>
                                <td class="text-center fw-bold text-secondary">
                                    <?= $invoice_id > 0 ? $sold_q : '<span class="badge bg-light text-muted border">Direct</span>' ?>
                                    <?php if ($consume > 0): ?>
                                        <small class="d-block text-danger font-weight-normal" style="font-size:10px;">-<?= $consume ?> in other returns</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm justify-content-center" style="max-width: 165px; margin: 0 auto;">
                                        <button type="button" class="btn btn-outline-secondary px-2" onclick="adjustQty(<?= $idx ?>, -1, <?= $max_avail ?>)" title="Decrease">-</button>
                                        <input type="number" 
                                               name="items[<?= $idx ?>][quantity]" 
                                               id="qty_<?= $idx ?>" 
                                               class="form-control form-control-sm text-center fw-bold text-danger ret-qty-input" 
                                               min="0" 
                                               max="<?= $max_avail ?>" 
                                               value="<?= $current_qty ?>" 
                                               onfocus="if(this.value==='0') this.value='';" 
                                               onblur="if(this.value==='') { this.value='0'; calculateReturnRow(<?= $idx ?>, <?= $max_avail ?>); }"
                                               oninput="calculateReturnRow(<?= $idx ?>, <?= $max_avail ?>)">
                                        <button type="button" class="btn btn-outline-secondary px-2" onclick="adjustQty(<?= $idx ?>, 1, <?= $max_avail ?>)" title="Increase">+</button>
                                        <button type="button" class="btn btn-danger px-2 fw-bold" onclick="setRowMax(<?= $idx ?>, <?= $max_avail ?>)" title="Return All Available">All</button>
                                    </div>
                                    <small class="text-muted d-block text-center mt-1" style="font-size: 10px;"><?= $invoice_id > 0 ? ('Max returnable: <strong>' . $max_avail . '</strong>') : 'Direct Return Qty' ?></small>
                                </td>
                                <td class="text-end font-monospace">Rs. <?= number_format($tp, 2) ?></td>
                                <td>
                                    <select name="items[<?= $idx ?>][condition]" class="form-select form-select-sm">
                                        <option value="Good / Resalable" <?= ($current_cond === 'Good / Resalable' || $current_cond === 'Good') ? 'selected' : '' ?>>Good (Restock in Store)</option>
                                        <option value="Damaged / Expiry Claim" <?= ($current_cond === 'Damaged / Expiry Claim' || $current_cond === 'Damaged') ? 'selected' : '' ?>>Damaged / Expiry (Discard)</option>
                                    </select>
                                </td>
                                <td class="text-end font-monospace fw-bold text-dark">
                                    <span id="rowTotal_<?= $idx ?>">Rs. <?= number_format($row_refund, 2) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Refund Settlement & Reason -->
            <div class="row g-4 pt-3 border-top justify-content-between align-items-start">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1">Return Reason / Remarks</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Customer excess order, expired stock claim, packaging damaged etc..."><?= htmlspecialchars($return_data['reason'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="col-md-5">
                    <div class="p-3 border rounded bg-light">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted mb-1">Refund Method</label>
                            <?php $curr_ref_type = $return_data['refund_type'] ?? 'Deduct Balance'; ?>
                            <select name="refund_type" id="refundTypeSelect" class="form-select fw-semibold" onchange="toggleAccountDropdown()">
                                <option value="Deduct Balance" <?= in_array($curr_ref_type, ['Deduct Balance', 'Store Credit', 'Credit Note', 'Adjust / Deduct Customer Balance']) ? 'selected' : '' ?>>Adjust / Deduct Customer Balance</option>
                                <option value="Cash Refund" <?= ($curr_ref_type === 'Cash Refund') ? 'selected' : '' ?>>Cash Refund (Pay from Cash Account)</option>
                                <option value="Bank Refund" <?= ($curr_ref_type === 'Bank Refund') ? 'selected' : '' ?>>Bank Transfer Refund</option>
                            </select>
                        </div>

                        <div class="mb-3 <?= ($curr_ref_type === 'Cash Refund') ? '' : 'd-none' ?>" id="cashAccountBox">
                            <label class="form-label small fw-bold text-muted mb-1">Cash Account</label>
                            <select name="cash_account_id" class="form-select">
                                <?php foreach ($cash_accounts as $ca): ?>
                                    <option value="<?= $ca['id'] ?>" <?= ($return_data['cash_account_id'] == $ca['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($ca['account_name']) ?> (Bal: Rs. <?= number_format($ca['balance'], 2) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3 <?= ($curr_ref_type === 'Bank Refund') ? '' : 'd-none' ?>" id="bankAccountBox">
                            <label class="form-label small fw-bold text-muted mb-1">Bank Account</label>
                            <select name="bank_account_id" class="form-select">
                                <?php foreach ($bank_accounts as $ba): ?>
                                    <option value="<?= $ba['id'] ?>" <?= ($return_data['bank_account_id'] == $ba['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($ba['bank_name']) ?> (<?= htmlspecialchars($ba['account_title']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <span class="fw-bold text-dark fs-6">Total Return Refund:</span>
                            <span class="fw-bold text-danger fs-5 font-monospace" id="grandRefundTotalDisplay">Rs. <?= number_format($init_grand_refund, 2) ?></span>
                        </div>

                        <div class="mt-4 d-flex gap-2">
                            <a href="sale_return.php" class="btn btn-outline-secondary w-50 fw-semibold py-2">
                                Cancel
                            </a>
                            <button type="submit" name="update_return" id="updateReturnBtn" class="btn btn-warning w-50 fw-bold py-2 shadow-sm text-dark text-uppercase">
                                <i class="fa-solid fa-save me-1"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
</form>

<script>
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
        const form = document.getElementById('editReturnForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const qtyInputs = document.querySelectorAll('.ret-qty-input');
                let totalReturnQty = 0;
                qtyInputs.forEach(input => {
                    totalReturnQty += (parseInt(input.value) || 0);
                });

                if (totalReturnQty <= 0) {
                    e.preventDefault();
                    alert('⚠️ Barahe karam kam az kam 1 item ki return quantity 1 ya zyada darj karein!');
                    return false;
                }
            });
        }
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
