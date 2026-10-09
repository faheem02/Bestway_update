<?php
ob_start();
/**
 * Bestway Wholesale Distribution - Purchase Return (Debit Note)
 * Return medicines against purchase bill, deduct stock & adjust supplier balance
 */
$page_title = "Purchase Return (Debit Note)";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['admin']);
require_once __DIR__ . '/../../includes/header.php';

$message = "";
$msg_type = "";

// Auto-generate Return No (e.g. PRTN-2026-0001)
$auto_return_no = "PRTN-" . date('Y') . "-0001";
if ($db_connected && $pdo) {
    try {
        $stmt_seq = $pdo->query("SELECT return_no FROM purchase_returns WHERE return_no REGEXP '^PRTN-[0-9]{4}-[0-9]+$' ORDER BY id DESC LIMIT 1");
        $last_ret = $stmt_seq->fetchColumn();
        if ($last_ret && preg_match('/PRTN-\d+-(\d+)/i', $last_ret, $matches)) {
            $next_num = intval($matches[1]) + 1;
            $auto_return_no = "PRTN-" . date('Y') . "-" . str_pad($next_num, 4, '0', STR_PAD_LEFT);
        } else {
            $stmt_cnt = $pdo->query("SELECT COUNT(*) FROM purchase_returns");
            $cnt = intval($stmt_cnt->fetchColumn()) + 1;
            $auto_return_no = "PRTN-" . date('Y') . "-" . str_pad($cnt, 4, '0', STR_PAD_LEFT);
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

// Handle Delete Return Action (Revert)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id'])) {
    $del_id = intval($_GET['id']);
    if ($db_connected && $pdo) {
        try {
            $pdo->beginTransaction();

            $stmt_ret = $pdo->prepare("SELECT * FROM purchase_returns WHERE id = ?");
            $stmt_ret->execute([$del_id]);
            $ret_data = $stmt_ret->fetch(PDO::FETCH_ASSOC);

            if ($ret_data) {
                // Fetch return items to revert stock
                $stmt_ritems = $pdo->prepare("SELECT product_id, batch_no, quantity, total_price FROM purchase_return_items WHERE purchase_return_id = ?");
                $stmt_ritems->execute([$del_id]);
                $ritems = $stmt_ritems->fetchAll(PDO::FETCH_ASSOC);

                $stmt_add_stock = $pdo->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = ?");
                $stmt_add_batch = $pdo->prepare("UPDATE product_batches SET current_stock = current_stock + ? WHERE product_id = ? AND batch_no = ?");

                foreach ($ritems as $ri) {
                    $stmt_add_stock->execute([$ri['quantity'], $ri['product_id']]);
                    if (!empty($ri['batch_no'])) {
                        $stmt_add_batch->execute([$ri['quantity'], $ri['product_id'], $ri['batch_no']]);
                    }
                }

                // Revert Supplier Balance (Add back the deducted return amount)
                $sup_id = intval($ret_data['supplier_id']);
                $ret_amt = floatval($ret_data['total_amount']);
                if ($sup_id > 0 && $ret_amt > 0) {
                    $pdo->prepare("UPDATE suppliers SET current_balance = current_balance + ? WHERE id = ?")->execute([$ret_amt, $sup_id]);
                }

                // Remove from ledgers
                $pdo->prepare("DELETE FROM supplier_ledgers WHERE reference_no = ? AND supplier_id = ?")->execute([$ret_data['return_no'], $sup_id]);

                // Delete items & return
                $pdo->prepare("DELETE FROM purchase_return_items WHERE purchase_return_id = ?")->execute([$del_id]);
                $pdo->prepare("DELETE FROM purchase_returns WHERE id = ?")->execute([$del_id]);

                $pdo->commit();
                $message = "Purchase Return #{$ret_data['return_no']} cancelled successfully and stock restored.";
                $msg_type = "success";
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $message = "Error: " . $e->getMessage();
            $msg_type = "danger";
        }
    }
}

// Handle Form Submission: Create Purchase Return
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_return'])) {
    $return_no       = trim($_POST['return_no'] ?? $auto_return_no);
    $purchase_id     = intval($_POST['purchase_id'] ?? 0);
    $supplier_id     = intval($_POST['supplier_id'] ?? 0);
    $supplier_name   = trim($_POST['supplier_name'] ?? '');
    $return_date     = trim($_POST['return_date'] ?? date('Y-m-d'));
    $refund_type     = trim($_POST['refund_type'] ?? 'Adjust / Deduct Supplier Balance');
    $cash_account_id = !empty($_POST['cash_account_id']) ? intval($_POST['cash_account_id']) : null;
    $bank_account_id = !empty($_POST['bank_account_id']) ? intval($_POST['bank_account_id']) : null;
    $reason          = trim($_POST['reason'] ?? 'Purchase Return to Supplier');
    $items           = $_POST['items'] ?? [];

    if ($purchase_id <= 0) {
        $message = "Please select a purchase bill / invoice.";
        $msg_type = "danger";
    } elseif (empty($items) || !is_array($items)) {
        $message = "Please include at least one product item to return.";
        $msg_type = "danger";
    } else {
        if ($db_connected && $pdo) {
            try {
                $pdo->beginTransaction();

                $validated_return_items = [];
                $total_return_amount = 0;

                foreach ($items as $pi_id => $ret_data) {
                    $ret_qty   = intval($ret_data['quantity'] ?? $ret_data['qty'] ?? 0);
                    $max_qty   = intval($ret_data['max_qty'] ?? 0);
                    $pid       = intval($ret_data['product_id'] ?? 0);
                    $pname     = trim($ret_data['item_name'] ?? $ret_data['product_name'] ?? 'Product');
                    $bno       = trim($ret_data['batch_no'] ?? '');
                    $unit_price = floatval($ret_data['purchase_price'] ?? $ret_data['price'] ?? 0);
                    $condition = trim($ret_data['condition'] ?? 'Good');
                    $purchase_item_id = intval($ret_data['purchase_item_id'] ?? $pi_id);

                    if ($ret_qty > 0) {
                        if ($max_qty > 0 && $ret_qty > $max_qty) {
                            throw new Exception("Error: Return quantity ({$ret_qty}) for product '{$pname}' cannot exceed purchased quantity ({$max_qty}).");
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

                $user_id = $_SESSION['user_id'] ?? 1;

                // 1. Insert into purchase_returns
                $stmt_pr = $pdo->prepare("
                    INSERT INTO purchase_returns (
                        return_no, purchase_id, supplier_id, return_date, total_amount, reason,
                        status, created_by, payment_method, cash_account_id, bank_account_id
                    ) VALUES (?, ?, ?, ?, ?, ?, 'Adjusted', ?, ?, ?, ?)
                ");
                $stmt_pr->execute([
                    $return_no, $purchase_id, $supplier_id, $return_date, $total_return_amount,
                    $reason, $user_id, $refund_type, $cash_account_id, $bank_account_id
                ]);
                $return_id = $pdo->lastInsertId();

                // 2. Insert items and DEDUCT INVENTORY (goods leaving warehouse to supplier)
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
                        $return_id, $v['product_id'], $v['batch_no'], $v['quantity'],
                        $v['price'], $v['total'], $v['purchase_item_id'], $v['condition']
                    ]);

                    // Deduct stock from warehouse
                    if ($v['product_id'] > 0) {
                        $stmt_sub_stock->execute([$v['quantity'], $v['product_id']]);
                        if (!empty($v['batch_no'])) {
                            $stmt_sub_batch->execute([$v['quantity'], $v['product_id'], $v['batch_no']]);
                        }
                    }
                }

                // 3. Financial Settlement
                if ($refund_type === 'Adjust / Deduct Supplier Balance' || $refund_type === 'Adjust / Deduct Customer Balance') {
                    // Reduce payable to supplier
                    $stmt_sbal = $pdo->prepare("SELECT current_balance FROM suppliers WHERE id = ? FOR UPDATE");
                    $stmt_sbal->execute([$supplier_id]);
                    $current_sup_bal = floatval($stmt_sbal->fetchColumn() ?? 0);
                    $new_sup_bal = max(0, $current_sup_bal - $total_return_amount);

                    $pdo->prepare("UPDATE suppliers SET current_balance = ? WHERE id = ?")->execute([$new_sup_bal, $supplier_id]);

                    // Debit Supplier account in ledger
                    $stmt_sledger = $pdo->prepare("
                        INSERT INTO supplier_ledgers (
                            supplier_id, transaction_date, transaction_type,
                            reference_no, debit_amount, credit_amount,
                            running_balance, description
                        ) VALUES (?, ?, 'Purchase Return', ?, ?, 0.00, ?, ?)
                    ");
                    $stmt_sledger->execute([
                        $supplier_id, $return_date, $return_no,
                        $total_return_amount, $new_sup_bal,
                        "Debit Note / Return #{$return_no} | Reason: {$reason}"
                    ]);
                } elseif ($refund_type === 'Cash Refund' && $cash_account_id) {
                    // Cash received from supplier into cash account
                    $pdo->prepare("UPDATE cash_accounts SET balance = balance + ? WHERE id = ?")->execute([$total_return_amount, $cash_account_id]);
                } elseif ($refund_type === 'Bank Refund' && $bank_account_id) {
                    // Bank refund received from supplier into bank account
                    $pdo->prepare("UPDATE bank_accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$total_return_amount, $bank_account_id]);
                }

                $pdo->commit();
                $message = "Purchase Return #{$return_no} kamiyabi se save ho gaya aur warehouse stock se items deduct kar diye gaye hain! <a href='print_purchase_return.php?id={$return_id}' target='_blank' class='fw-bold text-dark text-decoration-underline ms-2'><i class='fa-solid fa-print'></i> Print Debit Note</a>";
                $msg_type = "success";

                // Refresh sequence number
                $auto_return_no = "PRTN-" . date('Y') . "-" . str_pad($return_id + 1, 4, '0', STR_PAD_LEFT);

            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $message = "Error: " . $e->getMessage();
                $msg_type = "danger";
            }
        }
    }
}

// Fetch all available Purchase Bills for the dropdown
$purchases_list = [];
if ($db_connected && $pdo) {
    try {
        $stmt_purchases = $pdo->query("
            SELECT p.id, p.bill_no, p.purchase_date, p.grand_total, p.supplier_id, s.name as supplier_name, s.company_name as supplier_company
            FROM purchases p
            LEFT JOIN suppliers s ON s.id = p.supplier_id
            ORDER BY p.id DESC LIMIT 150
        ");
        $purchases_list = $stmt_purchases->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Preset Purchase ID from URL if provided
$selected_purchase_id = intval($_GET['purchase_id'] ?? 0);
$selected_purchase = null;
$selected_items = [];

if ($selected_purchase_id > 0 && $db_connected && $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, s.name as supplier_name, s.company_name as supplier_company, s.current_balance as supplier_balance
            FROM purchases p
            LEFT JOIN suppliers s ON s.id = p.supplier_id
            WHERE p.id = ?
        ");
        $stmt->execute([$selected_purchase_id]);
        $selected_purchase = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($selected_purchase) {
            $stmt_items = $pdo->prepare("
                SELECT pi.*, pr.name as product_name, pr.product_code
                FROM purchase_items pi
                LEFT JOIN products pr ON pr.id = pi.product_id
                WHERE pi.purchase_id = ?
            ");
            $stmt_items->execute([$selected_purchase_id]);
            $selected_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {}
}

// Fetch Recent Purchase Returns
$recent_returns = [];
if ($db_connected && $pdo) {
    try {
        $recent_returns = $pdo->query("
            SELECT 
                pr.*,
                s.name as supplier_name,
                s.company_name as supplier_company,
                COUNT(pri.id) as item_count,
                SUM(pri.quantity) as total_units_returned
            FROM purchase_returns pr
            LEFT JOIN suppliers s ON pr.supplier_id = s.id
            LEFT JOIN purchase_return_items pri ON pri.purchase_return_id = pr.id
            GROUP BY pr.id, s.name, s.company_name
            ORDER BY pr.id DESC
            LIMIT 15
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
            <h4 class="fw-bold mb-0 text-dark">Purchase Return</h4>
            <p class="text-muted small mb-0">Return medicines against purchase bill, deduct stock & adjust supplier balance</p>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="purchases.php" class="btn btn-outline-secondary bg-white shadow-sm px-3 rounded-3">
            <i class="fa-solid fa-cart-shopping me-1 text-primary"></i> Inward Purchases List
        </a>
    </div>
</div>

<!-- Alert Notifications -->
<?php if (!empty($message)): ?>
    <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show border-0 shadow-sm rounded-3 d-flex align-items-center mb-4" role="alert">
        <i class="fa-solid <?= $msg_type === 'success' ? 'fa-check-circle text-success' : 'fa-exclamation-triangle text-danger' ?> fs-5 me-2"></i>
        <div class="fw-medium"><?= $message ?></div>
        <button type="button" class="close ml-auto" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
<?php endif; ?>

<!-- Return Creation Form -->
<form action="" method="POST" id="returnForm">
    <div class="return-card p-4 p-md-5 mb-4">

        <div class="mb-4 pb-3 border-bottom">
            <div class="section-tag"><i class="fas fa-file-invoice mr-1"></i> STEP 1: SELECT PURCHASE BILL / INVOICE</div>
            
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-muted mb-1">Select Purchase Bill / Invoice <span class="text-danger">*</span></label>
                    <select name="purchase_id" id="purchaseSelect" class="form-select" onchange="loadPurchaseForReturn(this.value)" required>
                        <option value="">-- Choose Purchase Invoice --</option>
                        <?php foreach ($purchases_list as $pur): ?>
                            <option value="<?= $pur['id'] ?>" <?= ($selected_purchase_id == $pur['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pur['bill_no']) ?> - <?= htmlspecialchars($pur['supplier_name'] ?? 'Supplier') ?> <?= !empty($pur['supplier_company']) ? '(' . htmlspecialchars($pur['supplier_company']) . ')' : '' ?> (Rs. <?= number_format($pur['grand_total'], 2) ?>)
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

            <?php if ($selected_purchase): ?>
                <div class="alert alert-info border-0 rounded-3 mt-3 d-flex justify-content-between align-items-center mb-0" style="background-color: #e0f2fe; color: #0369a1;">
                    <div>
                        <strong class="fs-6 text-primary"><?= htmlspecialchars($selected_purchase['supplier_name']) ?> <?= !empty($selected_purchase['supplier_company']) ? '(' . htmlspecialchars($selected_purchase['supplier_company']) . ')' : '' ?></strong>
                        <span class="text-muted ms-2">(Invoice Date: <?= date('d M Y', strtotime($selected_purchase['purchase_date'])) ?>)</span>
                    </div>
                    <div>
                        <span>Billed Total: <strong class="text-primary fs-6">Rs. <?= number_format($selected_purchase['grand_total'], 2) ?></strong></span>
                    </div>
                </div>
                <input type="hidden" name="supplier_id" value="<?= $selected_purchase['supplier_id'] ?>">
                <input type="hidden" name="supplier_name" value="<?= htmlspecialchars($selected_purchase['supplier_name']) ?>">
            <?php endif; ?>
        </div>

        <!-- Step 2: Returned Products Table -->
        <div class="mb-4">
            <div class="section-tag"><i class="fas fa-pills mr-1"></i> STEP 2: INVOICED PRODUCTS TO RETURN</div>

            <?php if (empty($selected_items)): ?>
                <div class="p-5 text-center border rounded-3 bg-light text-muted">
                    <i class="fas fa-hand-pointer fa-2x mb-2 text-secondary d-block"></i>
                    Please <strong>select a purchase bill / invoice</strong> above to load its products.
                </div>
            <?php else: ?>
                <div class="table-responsive border rounded-3 overflow-hidden shadow-sm mb-3">
                    <table class="table table-return mb-0">
                        <thead>
                            <tr>
                                <th style="width: 45px;" class="text-center">#</th>
                                <th>MEDICINE / PRODUCT NAME</th>
                                <th style="width: 110px;" class="text-center">PURCHASED QTY</th>
                                <th style="width: 140px;" class="text-center">RETURN QTY <span class="text-danger">*</span></th>
                                <th style="width: 130px;" class="text-end">PURCHASE PRICE (RS)</th>
                                <th style="width: 180px;">CONDITION</th>
                                <th style="width: 140px;" class="text-end">REFUND AMOUNT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($selected_items as $idx => $it): 
                                $purchased_q = intval($it['quantity']);
                                $pp = floatval($it['purchase_price']);
                                $p_name = !empty($it['product_name']) ? $it['product_name'] : 'Medicine #' . $it['product_id'];
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
                                        <input type="hidden" name="items[<?= $idx ?>][purchase_item_id]" value="<?= $it['id'] ?>">
                                        <input type="hidden" name="items[<?= $idx ?>][purchase_price]" id="price_<?= $idx ?>" value="<?= $pp ?>">
                                        <input type="hidden" name="items[<?= $idx ?>][price]" value="<?= $pp ?>">
                                        <input type="hidden" name="items[<?= $idx ?>][max_qty]" value="<?= $purchased_q ?>">
                                    </td>
                                    <td class="text-center fw-bold text-secondary"><?= $purchased_q ?></td>
                                    <td>
                                        <input type="number" name="items[<?= $idx ?>][quantity]" id="qty_<?= $idx ?>" class="form-control form-control-sm text-center fw-bold text-danger ret-qty-input" min="0" max="<?= $purchased_q ?>" value="0" oninput="calculateReturnRow(<?= $idx ?>, <?= $purchased_q ?>)">
                                        <small class="text-muted d-block text-center" style="font-size: 10px;">Max: <?= $purchased_q ?></small>
                                    </td>
                                    <td class="text-end font-monospace">Rs. <?= number_format($pp, 2) ?></td>
                                    <td>
                                        <select name="items[<?= $idx ?>][condition]" class="form-select form-select-sm">
                                            <option value="Good (Restock in Store)">Good (Restock in Store)</option>
                                            <option value="Damaged / Expiry Claim">Damaged / Expiry Claim</option>
                                            <option value="Excess / Recall">Excess / Recall</option>
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
                            <textarea name="reason" class="form-control" rows="3" placeholder="Supplier excess dispatch, expired stock claim, packaging damaged etc..."></textarea>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <div class="p-3 border rounded bg-light">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted mb-1">Refund Method</label>
                                <select name="refund_type" id="refundTypeSelect" class="form-select fw-semibold" onchange="toggleAccountDropdown()">
                                    <option value="Adjust / Deduct Supplier Balance">Adjust / Deduct Supplier Balance</option>
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
                                <button type="submit" name="save_return" class="btn btn-danger w-100 fw-bold py-2 shadow-sm text-uppercase">
                                    <i class="fas fa-check mr-1"></i> Confirm & Save Return
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>

    </div>
</form>

<!-- Recent Purchase Returns Table -->
<?php if (!empty($recent_returns)): ?>
    <div class="return-card p-4">
        <div class="section-tag mb-3"><i class="fas fa-history mr-1"></i> Recent Purchase Return Records</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-uppercase text-muted small" style="font-size: 0.76rem;">
                    <tr>
                        <th>Debit Note #</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Reason</th>
                        <th class="text-center">Items</th>
                        <th class="text-end">Total Amount</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" style="width: 125px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_returns as $rt): ?>
                        <tr>
                            <td>
                                <span class="badge bg-light text-danger border font-monospace fw-bold">
                                    <?= htmlspecialchars($rt['return_no']) ?>
                                </span>
                            </td>
                            <td><?= date('d M Y', strtotime($rt['return_date'])) ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($rt['supplier_name'] ?? 'General Supplier') ?></div>
                                <div class="text-muted small" style="font-size: 0.72rem;"><?= htmlspecialchars($rt['supplier_company'] ?? '') ?></div>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.72rem;">
                                    <?= htmlspecialchars($rt['reason']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border"><?= $rt['item_count'] ?> Products</span>
                            </td>
                            <td class="text-end">
                                <span class="fw-bold text-danger font-monospace">Rs. <?= number_format($rt['total_amount'], 2) ?></span>
                            </td>
                            <td class="text-center">
                                <?php 
                                $st = !empty($rt['status']) ? $rt['status'] : 'Completed';
                                $badge_cls = ($st === 'Completed' || $st === 'Adjusted') ? 'badge-success' : (($st === 'Pending') ? 'badge-warning' : 'badge-danger');
                                ?>
                                <span class="badge <?= $badge_cls ?> px-2 py-1" style="font-size: 0.78rem;">
                                    <i class="fas fa-check-circle mr-1"></i> <?= htmlspecialchars($st) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center align-items-center" style="gap: 5px;">
                                    <a href="print_purchase_return.php?id=<?= $rt['id'] ?>" 
                                       target="_blank" 
                                       class="btn btn-sm btn-outline-primary px-2 py-1" 
                                       title="Print Debit Note / Voucher">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <a href="edit_purchase_return.php?id=<?= $rt['id'] ?>" 
                                       class="btn btn-sm btn-outline-info px-2 py-1" 
                                       title="Edit Purchase Return">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="purchase_return.php?action=delete&id=<?= $rt['id'] ?>" 
                                       class="btn btn-sm btn-outline-danger px-2 py-1" 
                                       title="Cancel Return & Revert Stock"
                                       onclick="return confirm('Are you sure you want to cancel this purchase return and revert stock and supplier balance?');">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<script>
function loadPurchaseForReturn(purchaseId) {
    if (purchaseId) {
        window.location.href = 'purchase_return.php?purchase_id=' + encodeURIComponent(purchaseId);
    } else {
        window.location.href = 'purchase_return.php';
    }
}

function calculateReturnRow(idx, maxQty) {
    const qtyInput = document.getElementById('qty_' + idx);
    let qty = parseInt(qtyInput.value) || 0;

    if (qty < 0) {
        qty = 0;
        qtyInput.value = 0;
    }
    if (qty > maxQty) {
        alert('Error: Return quantity cannot exceed purchased quantity (' + maxQty + ').');
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

// Client side validation on form submission
document.getElementById('returnForm').addEventListener('submit', function (e) {
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

<?php require_once __DIR__ . '/../../includes/footer.php';
if (ob_get_level()) { ob_end_flush(); }
?>
