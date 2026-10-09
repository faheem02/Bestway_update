<?php
/**
 * Bestway Wholesale Distribution - Edit Purchase Return
 * Modify returned products, adjust supplier balance & inventory accordingly
 */
$page_title = "Edit Purchase Return";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(['admin']);

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: purchase_return.php");
    exit;
}

$message = "";
$msg_type = "";

// Fetch Return Record
$stmt = $pdo->prepare("SELECT * FROM purchase_returns WHERE id = ?");
$stmt->execute([$id]);
$return_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$return_data) {
    require_once __DIR__ . '/../../includes/header.php';
    echo '<div class="alert alert-danger m-4 shadow-sm rounded-3">Purchase Return record not found. <a href="purchase_return.php" class="btn btn-sm btn-outline-danger ms-3">Go Back to Purchase Returns</a></div>';
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

$purchase_id = intval($return_data['purchase_id'] ?? 0);
$supplier_id = intval($return_data['supplier_id'] ?? 0);

// Fetch Original Purchase Bill
$selected_purchase = null;
if ($purchase_id > 0) {
    $stmt_pur = $pdo->prepare("
        SELECT p.*, s.name as supplier_name, s.company_name as supplier_company 
        FROM purchases p 
        LEFT JOIN suppliers s ON s.id = p.supplier_id 
        WHERE p.id = ?
    ");
    $stmt_pur->execute([$purchase_id]);
    $selected_purchase = $stmt_pur->fetch(PDO::FETCH_ASSOC);
}

// Fetch Original Purchase Items
$purchased_items = [];
if ($purchase_id > 0) {
    $stmt_pi = $pdo->prepare("
        SELECT pi.*, p.name as product_name, p.product_code 
        FROM purchase_items pi 
        LEFT JOIN products p ON p.id = pi.product_id 
        WHERE pi.purchase_id = ?
        ORDER BY pi.id ASC
    ");
    $stmt_pi->execute([$purchase_id]);
    $purchased_items = $stmt_pi->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch currently returned items in THIS return
$current_return_items = [];
$stmt_curr = $pdo->prepare("SELECT * FROM purchase_return_items WHERE purchase_return_id = ?");
$stmt_curr->execute([$id]);
foreach ($stmt_curr->fetchAll(PDO::FETCH_ASSOC) as $cri) {
    $item_key = !empty($cri['purchase_item_id']) ? intval($cri['purchase_item_id']) : intval($cri['product_id']);
    $current_return_items[$item_key] = $cri;
}

// Fetch quantities returned in OTHER returns for this purchase bill (excluding this return)
$other_returns_map = [];
try {
    $stmt_other = $pdo->prepare("
        SELECT pri.purchase_item_id, pri.product_id, COALESCE(SUM(pri.quantity), 0) AS returned_qty
        FROM purchase_return_items pri
        JOIN purchase_returns pr ON pr.id = pri.purchase_return_id
        WHERE pr.purchase_id = ? AND pr.id != ?
        GROUP BY pri.purchase_item_id, pri.product_id
    ");
    $stmt_other->execute([$purchase_id, $id]);
    while ($row_o = $stmt_other->fetch(PDO::FETCH_ASSOC)) {
        $k = !empty($row_o['purchase_item_id']) ? intval($row_o['purchase_item_id']) : intval($row_o['product_id']);
        $other_returns_map[$k] = intval($row_o['returned_qty']);
    }
} catch (Exception $e) {}

// Handle Form Submission: Update Purchase Return
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_return'])) {
    $return_date     = trim($_POST['return_date'] ?? date('Y-m-d'));
    $refund_type     = trim($_POST['refund_type'] ?? 'Adjust / Deduct Supplier Balance');
    $cash_account_id = !empty($_POST['cash_account_id']) ? intval($_POST['cash_account_id']) : null;
    $bank_account_id = !empty($_POST['bank_account_id']) ? intval($_POST['bank_account_id']) : null;
    $reason          = trim($_POST['reason'] ?? 'Purchase Return to Supplier');
    $items           = $_POST['items'] ?? [];

    if (empty($items) || !is_array($items)) {
        $message = "Please include at least one product item to return.";
        $msg_type = "danger";
    } else {
        if ($db_connected && $pdo) {
            try {
                $pdo->beginTransaction();

                // 1. Revert previous stock deductions for this return
                $stmt_prev_items = $pdo->prepare("SELECT product_id, batch_no, quantity FROM purchase_return_items WHERE purchase_return_id = ?");
                $stmt_prev_items->execute([$id]);
                $prev_items = $stmt_prev_items->fetchAll(PDO::FETCH_ASSOC);

                $stmt_revert_stock = $pdo->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = ?");
                $stmt_revert_batch = $pdo->prepare("UPDATE product_batches SET current_stock = current_stock + ? WHERE product_id = ? AND batch_no = ?");

                foreach ($prev_items as $pi) {
                    if ($pi['product_id'] > 0) {
                        $stmt_revert_stock->execute([$pi['quantity'], $pi['product_id']]);
                        if (!empty($pi['batch_no'])) {
                            $stmt_revert_batch->execute([$pi['quantity'], $pi['product_id'], $pi['batch_no']]);
                        }
                    }
                }

                // 2. Revert previous financial settlement
                $old_total = floatval($return_data['total_amount']);
                $old_ref_type = $return_data['payment_method'] ?? 'Adjust / Deduct Supplier Balance';
                $old_cash_id = !empty($return_data['cash_account_id']) ? intval($return_data['cash_account_id']) : 0;
                $old_bank_id = !empty($return_data['bank_account_id']) ? intval($return_data['bank_account_id']) : 0;

                if ($old_ref_type === 'Cash Refund' && $old_cash_id > 0) {
                    $pdo->prepare("UPDATE cash_accounts SET balance = balance - ? WHERE id = ?")->execute([$old_total, $old_cash_id]);
                } elseif ($old_ref_type === 'Bank Refund' && $old_bank_id > 0) {
                    $pdo->prepare("UPDATE bank_accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$old_total, $old_bank_id]);
                } else {
                    // Revert supplier balance
                    if ($supplier_id > 0 && $old_total > 0) {
                        $pdo->prepare("UPDATE suppliers SET current_balance = current_balance + ? WHERE id = ?")->execute([$old_total, $supplier_id]);
                    }
                    // Delete old ledger row
                    $pdo->prepare("DELETE FROM supplier_ledgers WHERE reference_no = ? AND supplier_id = ?")->execute([$return_data['return_no'], $supplier_id]);
                }

                // 3. Process new updated items
                $validated_return_items = [];
                $total_return_amount = 0;

                foreach ($items as $pi_id => $ret_data) {
                    $ret_qty    = intval($ret_data['quantity'] ?? $ret_data['qty'] ?? 0);
                    $max_qty    = intval($ret_data['max_qty'] ?? 0);
                    $pid        = intval($ret_data['product_id'] ?? 0);
                    $pname      = trim($ret_data['item_name'] ?? $ret_data['product_name'] ?? 'Product');
                    $bno        = trim($ret_data['batch_no'] ?? '');
                    $unit_price = floatval($ret_data['purchase_price'] ?? $ret_data['price'] ?? 0);
                    $condition  = trim($ret_data['condition'] ?? 'Good');
                    $purchase_item_id = intval($ret_data['purchase_item_id'] ?? $pi_id);

                    if ($ret_qty > 0) {
                        if ($max_qty > 0 && $ret_qty > $max_qty) {
                            throw new Exception("Error: Return quantity ({$ret_qty}) for product '{$pname}' cannot exceed purchased returnable quantity ({$max_qty}).");
                        }

                        $line_total = $ret_qty * $unit_price;
                        $total_return_amount += $line_total;

                        $validated_return_items[] = [
                            'product_id'       => $pid,
                            'name'             => $pname,
                            'batch_no'         => $bno,
                            'quantity'         => $ret_qty,
                            'price'            => $unit_price,
                            'total'            => $line_total,
                            'condition'        => $condition,
                            'purchase_item_id' => $purchase_item_id
                        ];
                    }
                }

                if (empty($validated_return_items)) {
                    throw new Exception("Please enter a return quantity of 1 or more for at least one item.");
                }

                // 4. Delete old items and insert updated items
                $pdo->prepare("DELETE FROM purchase_return_items WHERE purchase_return_id = ?")->execute([$id]);

                $stmt_pr_item = $pdo->prepare("
                    INSERT INTO purchase_return_items (
                        purchase_return_id, product_id, batch_no, quantity,
                        purchase_price, total_price, purchase_item_id, return_condition
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt_sub_stock = $pdo->prepare("UPDATE products SET current_stock = GREATEST(0, current_stock - ?) WHERE id = ?");
                $stmt_sub_batch = $pdo->prepare("UPDATE product_batches SET current_stock = GREATEST(0, current_stock - ?) WHERE product_id = ? AND batch_no = ?");

                foreach ($validated_return_items as $v) {
                    $stmt_pr_item->execute([
                        $id, $v['product_id'], $v['batch_no'], $v['quantity'],
                        $v['price'], $v['total'], $v['purchase_item_id'], $v['condition']
                    ]);

                    if ($v['product_id'] > 0) {
                        $stmt_sub_stock->execute([$v['quantity'], $v['product_id']]);
                        if (!empty($v['batch_no'])) {
                            $stmt_sub_batch->execute([$v['quantity'], $v['product_id'], $v['batch_no']]);
                        }
                    }
                }

                // 5. Apply new financial settlement
                if ($refund_type === 'Adjust / Deduct Supplier Balance' || $refund_type === 'Adjust / Deduct Customer Balance') {
                    $stmt_sbal = $pdo->prepare("SELECT current_balance FROM suppliers WHERE id = ? FOR UPDATE");
                    $stmt_sbal->execute([$supplier_id]);
                    $current_sup_bal = floatval($stmt_sbal->fetchColumn() ?? 0);
                    $new_sup_bal = max(0, $current_sup_bal - $total_return_amount);

                    $pdo->prepare("UPDATE suppliers SET current_balance = ? WHERE id = ?")->execute([$new_sup_bal, $supplier_id]);

                    $stmt_sledger = $pdo->prepare("
                        INSERT INTO supplier_ledgers (
                            supplier_id, transaction_date, transaction_type,
                            reference_no, debit_amount, credit_amount,
                            running_balance, description
                        ) VALUES (?, ?, 'Purchase Return', ?, ?, 0.00, ?, ?)
                    ");
                    $stmt_sledger->execute([
                        $supplier_id, $return_date, $return_data['return_no'],
                        $total_return_amount, $new_sup_bal,
                        "Debit Note / Return #{$return_data['return_no']} (Updated) | Reason: {$reason}"
                    ]);
                } elseif ($refund_type === 'Cash Refund' && $cash_account_id) {
                    $pdo->prepare("UPDATE cash_accounts SET balance = balance + ? WHERE id = ?")->execute([$total_return_amount, $cash_account_id]);
                } elseif ($refund_type === 'Bank Refund' && $bank_account_id) {
                    $pdo->prepare("UPDATE bank_accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$total_return_amount, $bank_account_id]);
                }

                // 6. Update purchase_returns header record
                $stmt_upd_pr = $pdo->prepare("
                    UPDATE purchase_returns 
                    SET return_date = ?, total_amount = ?, reason = ?, payment_method = ?, cash_account_id = ?, bank_account_id = ?
                    WHERE id = ?
                ");
                $stmt_upd_pr->execute([
                    $return_date, $total_return_amount, $reason, $refund_type,
                    $cash_account_id, $bank_account_id, $id
                ]);

                $pdo->commit();
                $message = "Purchase Return #{$return_data['return_no']} kamiyabi se update ho gaya! <a href='print_purchase_return.php?id={$id}' target='_blank' class='fw-bold text-dark text-decoration-underline ms-2'><i class='fa-solid fa-print'></i> Print Debit Note</a>";
                $msg_type = "success";

                // Reload return data
                $stmt->execute([$id]);
                $return_data = $stmt->fetch(PDO::FETCH_ASSOC);

                // Reload current items
                $stmt_curr->execute([$id]);
                $current_return_items = [];
                foreach ($stmt_curr->fetchAll(PDO::FETCH_ASSOC) as $cri) {
                    $item_key = !empty($cri['purchase_item_id']) ? intval($cri['purchase_item_id']) : intval($cri['product_id']);
                    $current_return_items[$item_key] = $cri;
                }

            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $message = "Error: " . $e->getMessage();
                $msg_type = "danger";
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    .return-card {
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }
    .section-tag {
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        color: #dc2626;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .table-return thead th {
        background-color: #f8fafc;
        color: #475569;
        font-size: 0.74rem;
        font-weight: 700;
        text-transform: uppercase;
        border-bottom: 2px solid #e2e8f0;
        padding: 10px 12px;
    }
    .table-return tbody td {
        padding: 10px 12px;
        vertical-align: middle;
        font-size: 0.85rem;
    }
</style>

<div class="container-fluid px-3 py-3">

    <!-- Top Breadcrumb & Action Toolbar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="purchase_return.php" class="text-decoration-none text-muted small fw-semibold">
                    <i class="fas fa-arrow-left mr-1"></i> Purchase Returns
                </a>
                <span class="text-muted small">/</span>
                <span class="text-danger small fw-bold">Edit #<?= htmlspecialchars($return_data['return_no']) ?></span>
            </div>
            <h4 class="fw-bold mb-0 text-dark">
                <i class="fas fa-edit text-danger mr-2"></i> Edit Purchase Return / Debit Note
            </h4>
        </div>
        <div class="d-flex gap-2">
            <a href="print_purchase_return.php?id=<?= $id ?>" target="_blank" class="btn btn-outline-danger fw-semibold bg-white shadow-sm px-3 rounded-3">
                <i class="fas fa-print mr-1"></i> Print Debit Note
            </a>
            <a href="purchase_return.php" class="btn btn-outline-secondary fw-semibold bg-white shadow-sm px-3 rounded-3">
                <i class="fas fa-list mr-1"></i> All Returns
            </a>
        </div>
    </div>

    <!-- Alert Notification -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show border-0 shadow-sm rounded-3 d-flex align-items-center mb-4" role="alert">
            <i class="fa-solid <?= $msg_type === 'success' ? 'fa-check-circle text-success' : 'fa-exclamation-triangle text-danger' ?> fs-4 me-3"></i>
            <div><?= $message ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="POST" id="editReturnForm">
        <div class="return-card p-4 mb-4">
            
            <!-- Step 1: Info & Selection -->
            <div class="mb-4">
                <div class="section-tag mb-3"><i class="fas fa-receipt mr-1"></i> STEP 1: RETURN VOUCHER & SUPPLIER DETAILS</div>
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label small fw-bold text-muted mb-1">Original Purchase Bill</label>
                        <input type="text" class="form-control bg-light font-monospace fw-bold" 
                               value="<?= htmlspecialchars($selected_purchase['bill_no'] ?? 'N/A') ?> - <?= htmlspecialchars($selected_purchase['supplier_name'] ?? 'Supplier') ?> (Total: Rs. <?= number_format($selected_purchase['grand_total'] ?? 0, 2) ?>)" readonly>
                        <input type="hidden" name="purchase_id" value="<?= $purchase_id ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted mb-1">Debit Note Number</label>
                        <input type="text" name="return_no" class="form-control font-monospace fw-bold bg-light text-danger" value="<?= htmlspecialchars($return_data['return_no']) ?>" readonly>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted mb-1">Return Date</label>
                        <input type="date" name="return_date" class="form-control fw-semibold" value="<?= htmlspecialchars($return_data['return_date']) ?>" required>
                    </div>
                </div>

                <?php if ($selected_purchase): ?>
                    <div class="alert alert-info border-0 rounded-3 mt-3 d-flex justify-content-between align-items-center mb-0" style="background-color: #e0f2fe; color: #0369a1;">
                        <div>
                            <strong class="fs-6 text-primary"><?= htmlspecialchars($selected_purchase['supplier_name']) ?> <?= !empty($selected_purchase['supplier_company']) ? '(' . htmlspecialchars($selected_purchase['supplier_company']) . ')' : '' ?></strong>
                            <span class="text-muted ms-2">(Invoice Date: <?= date('d M Y', strtotime($selected_purchase['purchase_date'])) ?>)</span>
                        </div>
                        <div>
                            <span>Original Billed Amount: <strong class="text-primary fs-6">Rs. <?= number_format($selected_purchase['grand_total'], 2) ?></strong></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Step 2: Items Table -->
            <div class="mb-4">
                <div class="section-tag mb-3"><i class="fas fa-pills mr-1"></i> STEP 2: RETURNED PRODUCTS & QUANTITIES</div>

                <?php if (empty($purchased_items)): ?>
                    <div class="p-4 text-center border rounded-3 bg-light text-muted">
                        No products found for this purchase bill.
                    </div>
                <?php else: ?>
                    <div class="table-responsive border rounded-3 overflow-hidden shadow-sm mb-3">
                        <table class="table table-return mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 45px;" class="text-center">#</th>
                                    <th>MEDICINE / PRODUCT NAME</th>
                                    <th style="width: 100px;" class="text-center">PURCHASED</th>
                                    <th style="width: 130px;" class="text-center">RETURN QTY <span class="text-danger">*</span></th>
                                    <th style="width: 120px;" class="text-end">UNIT COST (RS)</th>
                                    <th style="width: 180px;">CONDITION</th>
                                    <th style="width: 140px;" class="text-end">REFUND AMOUNT</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                foreach ($purchased_items as $idx => $it): 
                                    $pi_id       = intval($it['id']);
                                    $purchased_q = intval($it['quantity']);
                                    $pp          = floatval($it['purchase_price']);
                                    $p_name      = !empty($it['product_name']) ? $it['product_name'] : 'Medicine #' . $it['product_id'];

                                    // Other returns on this line
                                    $other_q = $other_returns_map[$pi_id] ?? 0;
                                    $max_returnable = max(0, $purchased_q - $other_q);

                                    // Current quantity in this return
                                    $curr_item = $current_return_items[$pi_id] ?? null;
                                    $curr_q = $curr_item ? intval($curr_item['quantity']) : 0;
                                    $curr_cond = $curr_item['return_condition'] ?? 'Good';
                                ?>
                                    <tr>
                                        <td class="text-center text-muted fw-bold"><?= $idx + 1 ?></td>
                                        <td>
                                            <strong class="text-dark"><?= htmlspecialchars($p_name) ?></strong>
                                            <?php if (!empty($it['batch_no'])): ?>
                                                <span class="badge bg-light text-secondary border ms-1 font-monospace"><?= htmlspecialchars($it['batch_no']) ?></span>
                                            <?php endif; ?>
                                            <input type="hidden" name="items[<?= $idx ?>][product_id]" value="<?= $it['product_id'] ?>">
                                            <input type="hidden" name="items[<?= $idx ?>][product_name]" value="<?= htmlspecialchars($p_name) ?>">
                                            <input type="hidden" name="items[<?= $idx ?>][item_name]" value="<?= htmlspecialchars($p_name) ?>">
                                            <input type="hidden" name="items[<?= $idx ?>][batch_no]" value="<?= htmlspecialchars($it['batch_no'] ?? 'DEFAULT') ?>">
                                            <input type="hidden" name="items[<?= $idx ?>][purchase_item_id]" value="<?= $pi_id ?>">
                                            <input type="hidden" name="items[<?= $idx ?>][purchase_price]" id="price_<?= $idx ?>" value="<?= $pp ?>">
                                            <input type="hidden" name="items[<?= $idx ?>][price]" value="<?= $pp ?>">
                                            <input type="hidden" name="items[<?= $idx ?>][max_qty]" value="<?= $max_returnable ?>">
                                        </td>
                                        <td class="text-center fw-bold text-secondary">
                                            <?= $purchased_q ?>
                                            <?php if ($other_q > 0): ?>
                                                <div class="text-muted small" style="font-size: 10px;">(<?= $other_q ?> in other ret)</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <input type="number" name="items[<?= $idx ?>][quantity]" id="qty_<?= $idx ?>" 
                                                   class="form-control form-control-sm text-center fw-bold text-danger ret-qty-input" 
                                                   min="0" max="<?= $max_returnable ?>" 
                                                   value="<?= $curr_q ?>" 
                                                   oninput="calculateReturnRow(<?= $idx ?>, <?= $max_returnable ?>)">
                                            <small class="text-muted d-block text-center" style="font-size: 10px;">Max Avail: <?= $max_returnable ?></small>
                                        </td>
                                        <td class="text-end font-monospace">Rs. <?= number_format($pp, 2) ?></td>
                                        <td>
                                            <select name="items[<?= $idx ?>][condition]" class="form-select form-select-sm">
                                                <option value="Good" <?= ($curr_cond === 'Good' || $curr_cond === 'Good (Restock in Store)') ? 'selected' : '' ?>>Good (Restock in Store)</option>
                                                <option value="Damaged / Expiry Claim" <?= ($curr_cond === 'Damaged / Expiry Claim') ? 'selected' : '' ?>>Damaged / Expiry Claim</option>
                                                <option value="Excess / Recall" <?= ($curr_cond === 'Excess / Recall') ? 'selected' : '' ?>>Excess / Recall</option>
                                            </select>
                                        </td>
                                        <td class="text-end font-monospace fw-bold text-dark">
                                            <span id="rowTotal_<?= $idx ?>">Rs. <?= number_format($curr_q * $pp, 2) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Step 3: Refund Settlement & Reason -->
            <div class="row g-4 pt-3 border-top justify-content-between align-items-start">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1">Return Reason / Remarks</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Supplier excess dispatch, expired stock claim, packaging damaged etc..."><?= htmlspecialchars($return_data['reason'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="col-md-5">
                    <div class="p-3 border rounded bg-light">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted mb-1">Refund Method</label>
                            <?php $curr_method = $return_data['payment_method'] ?? 'Adjust / Deduct Supplier Balance'; ?>
                            <select name="refund_type" id="refundTypeSelect" class="form-select fw-semibold" onchange="toggleAccountDropdown()">
                                <option value="Adjust / Deduct Supplier Balance" <?= ($curr_method === 'Adjust / Deduct Supplier Balance' || $curr_method === 'Adjust / Deduct Customer Balance') ? 'selected' : '' ?>>Adjust / Deduct Supplier Balance</option>
                                <option value="Cash Refund" <?= ($curr_method === 'Cash Refund') ? 'selected' : '' ?>>Cash Refund (Pay from Cash Account)</option>
                                <option value="Bank Refund" <?= ($curr_method === 'Bank Refund') ? 'selected' : '' ?>>Bank Transfer Refund</option>
                            </select>
                        </div>

                        <div class="mb-3 <?= ($curr_method !== 'Cash Refund') ? 'd-none' : '' ?>" id="cashAccountBox">
                            <label class="form-label small fw-bold text-muted mb-1">Cash Account</label>
                            <select name="cash_account_id" class="form-select">
                                <?php foreach ($cash_accounts as $ca): ?>
                                    <option value="<?= $ca['id'] ?>" <?= ($return_data['cash_account_id'] == $ca['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($ca['account_name']) ?> (Bal: Rs. <?= number_format($ca['balance'], 2) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3 <?= ($curr_method !== 'Bank Refund') ? 'd-none' : '' ?>" id="bankAccountBox">
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
                            <span class="fw-bold text-dark fs-6">Total Return Debit Amount:</span>
                            <span class="fw-bold text-danger fs-5 font-monospace" id="grandRefundTotalDisplay">Rs. <?= number_format($return_data['total_amount'], 2) ?></span>
                        </div>

                        <div class="mt-4">
                            <button type="submit" name="update_return" class="btn btn-danger w-100 fw-bold py-2 shadow-sm text-uppercase">
                                <i class="fas fa-save mr-1"></i> Update & Save Purchase Return
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </form>

</div>

<script>
function calculateReturnRow(idx, maxQty) {
    const qtyInput = document.getElementById('qty_' + idx);
    let qty = parseInt(qtyInput.value) || 0;

    if (qty < 0) {
        qty = 0;
        qtyInput.value = 0;
    }
    if (qty > maxQty) {
        alert('Error: Return quantity cannot exceed available quantity (' + maxQty + ').');
        qty = maxQty;
        qtyInput.value = maxQty;
    }

    const price = parseFloat(document.getElementById('price_' + idx).value) || 0;
    const total = qty * price;

    document.getElementById('rowTotal_' + idx).textContent = 'Rs. ' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    calculateGrandRefundTotal();
}

function calculateGrandRefundTotal() {
    let grandTotal = 0;
    const inputs = document.querySelectorAll('.ret-qty-input');

    inputs.forEach(input => {
        const idParts = input.id.split('_');
        const idx = idParts[1];
        const qty = parseInt(input.value) || 0;
        const price = parseFloat(document.getElementById('price_' + idx).value) || 0;
        grandTotal += (qty * price);
    });

    document.getElementById('grandRefundTotalDisplay').textContent = 'Rs. ' + grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function toggleAccountDropdown() {
    const method = document.getElementById('refundTypeSelect').value;
    const cashBox = document.getElementById('cashAccountBox');
    const bankBox = document.getElementById('bankAccountBox');

    if (method === 'Cash Refund') {
        cashBox.classList.remove('d-none');
        bankBox.classList.add('d-none');
    } else if (method === 'Bank Refund') {
        bankBox.classList.remove('d-none');
        cashBox.classList.add('d-none');
    } else {
        cashBox.classList.add('d-none');
        bankBox.classList.add('d-none');
    }
}

document.getElementById('editReturnForm').addEventListener('submit', function (e) {
    const inputs = document.querySelectorAll('.ret-qty-input');
    let totalQty = 0;

    inputs.forEach(inp => {
        totalQty += (parseInt(inp.value) || 0);
    });

    if (totalQty <= 0) {
        alert('⚠️ Please enter a return quantity of 1 or more for at least one item.');
        e.preventDefault();
        return false;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
