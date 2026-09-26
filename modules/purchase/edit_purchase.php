<?php
ob_start();
/**
 * Bestway Wholesale Distribution - Edit Inward Purchase (GRN)
 */
$page_title = "Edit Inward Purchase";
require_once __DIR__ . '/../../includes/header.php';

$purchase_id = intval($_GET['id'] ?? 0);
$purchase = null;
$items = [];
$message = "";
$msg_type = "";

if ($db_connected && $pdo && $purchase_id > 0) {
    try {
        $stmt_p = $pdo->prepare("SELECT * FROM purchases WHERE id = ?");
        $stmt_p->execute([$purchase_id]);
        $purchase = $stmt_p->fetch();

        if ($purchase) {
            $stmt_i = $pdo->prepare("
                SELECT pi.*, pr.name as product_name, pr.product_code, pr.stock_unit, pr.packs_per_box
                FROM purchase_items pi
                LEFT JOIN products pr ON pi.product_id = pr.id
                WHERE pi.purchase_id = ?
                ORDER BY pi.id ASC
            ");
            $stmt_i->execute([$purchase_id]);
            $items = $stmt_i->fetchAll();
        }
    } catch (Exception $e) {}
}

if (!$purchase) {
    echo '<div class="alert alert-danger m-4">Purchase bill not found. <a href="purchases.php">Go Back</a></div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Handle Form Submission: Update Purchase
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['update_purchase'])) {
    $supplier_id     = intval($_POST['supplier_id'] ?? $purchase['supplier_id']);
    $purchase_date   = trim($_POST['purchase_date'] ?? $purchase['purchase_date']);
    $receiving_date  = trim($_POST['receiving_date'] ?? $purchase_date);
    $payment_type    = trim($_POST['payment_type'] ?? $purchase['payment_type']);
    $notes           = trim($_POST['notes'] ?? '');

    $subtotal        = floatval($_POST['subtotal'] ?? 0);
    $discount_amount = floatval($_POST['discount_amount'] ?? 0);
    $tax_amount      = floatval($_POST['tax_amount'] ?? 0);
    $freight_charges = floatval($_POST['freight_charges'] ?? 0);
    $grand_total     = floatval($_POST['grand_total'] ?? 0);
    $paid_amount     = floatval($_POST['paid_amount'] ?? 0);
    $balance_amount  = floatval($_POST['balance_amount'] ?? 0);

    $posted_items = $_POST['items'] ?? [];

    if ($supplier_id <= 0) {
        $message = "Please select a Supplier.";
        $msg_type = "danger";
    } elseif (empty($posted_items) || !is_array($posted_items)) {
        $message = "Please add at least one item.";
        $msg_type = "danger";
    } else {
        if ($db_connected && $pdo) {
            try {
                $pdo->beginTransaction();

                // 1. Revert previous stock and batches
                $stmt_prev = $pdo->prepare("SELECT product_id, batch_no, quantity, bonus_quantity FROM purchase_items WHERE purchase_id = ?");
                $stmt_prev->execute([$purchase_id]);
                $prev_items = $stmt_prev->fetchAll();

                $stmt_revert_stock = $pdo->prepare("UPDATE products SET current_stock = GREATEST(0, current_stock - ?) WHERE id = ?");
                $stmt_revert_batch = $pdo->prepare("UPDATE product_batches SET current_stock = GREATEST(0, current_stock - ?) WHERE product_id = ? AND batch_no = ?");

                foreach ($prev_items as $pit) {
                    $tot_prev = intval($pit['quantity']) + intval($pit['bonus_quantity']);
                    $stmt_revert_stock->execute([$tot_prev, $pit['product_id']]);
                    $stmt_revert_batch->execute([$tot_prev, $pit['product_id'], $pit['batch_no']]);
                }

                // 2. Revert previous supplier balance impact
                $old_sup_id = intval($purchase['supplier_id']);
                $old_balance = floatval($purchase['balance_amount']);
                if ($old_sup_id > 0 && $old_balance > 0) {
                    $pdo->prepare("UPDATE suppliers SET current_balance = GREATEST(0, current_balance - ?) WHERE id = ?")->execute([$old_balance, $old_sup_id]);
                }

                // 3. Delete old items
                $pdo->prepare("DELETE FROM purchase_items WHERE purchase_id = ?")->execute([$purchase_id]);

                // Payment Status
                if ($paid_amount >= $grand_total && $grand_total > 0) {
                    $payment_status = 'Paid';
                    $balance_amount = 0.00;
                } elseif ($paid_amount > 0) {
                    $payment_status = 'Partial';
                    $balance_amount = max(0, $grand_total - $paid_amount);
                } else {
                    $payment_status = 'Unpaid';
                    $balance_amount = $grand_total;
                }

                // 4. Update purchase
                $stmt_upd = $pdo->prepare("
                    UPDATE purchases SET 
                        supplier_id = ?, purchase_date = ?, receiving_date = ?,
                        subtotal = ?, discount_amount = ?, tax_amount = ?, freight_charges = ?,
                        grand_total = ?, paid_amount = ?, balance_amount = ?,
                        payment_type = ?, payment_status = ?, notes = ?
                    WHERE id = ?
                ");
                $stmt_upd->execute([
                    $supplier_id, $purchase_date, $receiving_date,
                    $subtotal, $discount_amount, $tax_amount, $freight_charges,
                    $grand_total, $paid_amount, $balance_amount,
                    $payment_type, $payment_status, $notes, $purchase_id
                ]);

                // 5. Insert new items and apply stock
                $stmt_item = $pdo->prepare("
                    INSERT INTO purchase_items (
                        purchase_id, product_id, batch_no, expiry_date,
                        quantity, bonus_quantity, purchase_price, trade_price,
                        retail_price, discount_percent, discount_amount, tax_percent, tax_amount, total_price
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt_add_stock = $pdo->prepare("
                    UPDATE products 
                    SET current_stock = current_stock + ?, 
                        purchase_price = ?, 
                        trade_price = ? 
                    WHERE id = ?
                ");

                $stmt_chk_batch = $pdo->prepare("SELECT id FROM product_batches WHERE product_id = ? AND batch_no = ? LIMIT 1");
                $stmt_upd_batch = $pdo->prepare("UPDATE product_batches SET current_stock = current_stock + ?, expiry_date = ?, purchase_price = ?, trade_price = ? WHERE id = ?");
                $stmt_ins_batch = $pdo->prepare("INSERT INTO product_batches (product_id, batch_no, expiry_date, purchase_price, trade_price, retail_price, initial_quantity, current_stock, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active')");

                foreach ($posted_items as $itm) {
                    $pid         = intval($itm['product_id'] ?? 0);
                    $batch_no    = trim($itm['batch_no'] ?? 'DEFAULT');
                    $expiry_date = !empty($itm['expiry_date']) ? $itm['expiry_date'] : date('Y-m-d', strtotime('+2 years'));
                    $final_qty   = $raw_qty;
                    $final_bonus = $bonus_qty;
                    $total_stock = $final_qty + $final_bonus;

                    $p_cost      = floatval($itm['purchase_price'] ?? 0);
                    $tp_rate     = floatval($itm['trade_price'] ?? 0);
                    $disc_pct    = floatval($itm['discount_percent'] ?? 0);
                    $disc_amt    = floatval($itm['discount_amount'] ?? 0);
                    $tax_pct     = floatval($itm['tax_percent'] ?? 0);
                    $tax_amt     = floatval($itm['tax_amount'] ?? 0);
                    $gross_row   = $raw_qty * $p_cost;
                    $net_bef_tax = max(0, $gross_row - $disc_amt);
                    if ($tax_amt <= 0 && $tax_pct > 0) {
                        $tax_amt = round($net_bef_tax * ($tax_pct / 100), 2);
                    }
                    $row_total   = floatval($itm['total_price'] ?? max(0, $net_bef_tax + $tax_amt));

                    // Distribute overall bill discount proportionally
                    $bill_disc_ratio = ($subtotal > 0 && $discount_amount > 0) ? min(1, $discount_amount / $subtotal) : 0;
                    $row_net_after_bill_disc = max(0, $row_total * (1 - $bill_disc_ratio));

                    // Net effective unit cost after BOTH discounts and GST
                    $net_unit_cost = ($raw_qty > 0) ? ($row_net_after_bill_disc / $raw_qty) : $p_cost;

                    if ($pid > 0 && ($final_qty > 0 || $final_bonus > 0)) {
                        $stmt_item->execute([
                            $purchase_id, $pid, $batch_no, $expiry_date,
                            $final_qty, $final_bonus, $net_unit_cost, $tp_rate,
                            $tp_rate, $disc_pct, $disc_amt, $tax_pct, $tax_amt, $row_total
                        ]);

                        $stmt_add_stock->execute([$total_stock, $base_pack_cost, $base_pack_tp, $pid]);

                        $stmt_chk_batch->execute([$pid, $batch_no]);
                        $b_id = $stmt_chk_batch->fetchColumn();
                        if ($b_id) {
                            $stmt_upd_batch->execute([$total_stock, $expiry_date, $base_pack_cost, $base_pack_tp, $b_id]);
                        } else {
                            $stmt_ins_batch->execute([$pid, $batch_no, $expiry_date, $base_pack_cost, $base_pack_tp, $base_pack_tp, $total_stock, $total_stock]);
                        }
                    }
                }

                // 6. Sync supplier ledgers (for both old and new supplier if changed)
                if ($old_sup_id > 0) {
                    syncSupplierLedger($pdo, $old_sup_id);
                }
                if ($supplier_id > 0 && $supplier_id !== $old_sup_id) {
                    syncSupplierLedger($pdo, $supplier_id);
                }

                $pdo->commit();
                header("Location: view_purchase.php?id={$purchase_id}&msg=updated");
                exit;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $message = "Error updating purchase: " . $e->getMessage();
                $msg_type = "danger";
            }
        }
    }
}


// Suppliers, Products & Units
$suppliers = $pdo->query("SELECT id, name, company_name, current_balance FROM suppliers ORDER BY name ASC")->fetchAll();
$units     = $pdo->query("SELECT id, name, short_name FROM units ORDER BY name ASC")->fetchAll();
if (empty($units)) {
    $units = [
        ['id' => 1, 'name' => 'Pack', 'short_name' => 'Pac'],
        ['id' => 2, 'name' => 'Box', 'short_name' => 'Box']
    ];
}
$products  = $pdo->query("
    SELECT p.id, p.product_code, p.name, p.purchase_price, p.trade_price, p.discount_percent, p.stock_unit, p.packs_per_box, p.current_stock, c.name as company_name 
    FROM products p
    LEFT JOIN companies c ON p.company_id = c.id
    ORDER BY p.name ASC
")->fetchAll();
?>

<style>
    .items-table-wrapper {
        background: #ffffff;
    }
    .btn-remove-row {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #fff1f2;
        color: #e11d48;
        border: 1px solid #fecdd3;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .btn-remove-row:hover {
        background: #e11d48;
        color: #ffffff;
    }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h4 class="font-weight-bold text-dark mb-1">
            <i class="fas fa-edit text-primary mr-2"></i> Edit Bill #<?= htmlspecialchars($purchase['bill_no']) ?>
        </h4>
        <p class="text-muted small mb-0">Modify inward invoice items, rates, and supplier payment details</p>
    </div>
    <div>
        <a href="view_purchase.php?id=<?= $purchase['id'] ?>" class="btn btn-sm btn-outline-secondary font-weight-bold">
            <i class="fas fa-eye mr-1"></i> View Bill
        </a>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show shadow-sm mb-4" role="alert">
        <i class="fas <?= $msg_type === 'success' ? 'fa-check-circle text-success' : 'fa-exclamation-triangle text-danger' ?> mr-2"></i>
        <strong><?= htmlspecialchars($message) ?></strong>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
<?php endif; ?>

<form method="POST" action="" id="editPurchaseForm" class="needs-validation" novalidate>
    <!-- 1. Header Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-file-invoice mr-2"></i> Bill & Supplier Information
            </h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label font-weight-bold">Bill Reference #</label>
                    <input type="text" class="form-control font-weight-bold bg-light" value="<?= htmlspecialchars($purchase['bill_no']) ?>" readonly>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label font-weight-bold">Supplier / Manufacturer <span class="text-danger">*</span></label>
                    <select name="supplier_id" id="supplierSelect" class="form-control font-weight-bold" required>
                        <option value="">-- Select Supplier --</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($purchase['supplier_id'] == $s['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['name']) ?> <?= !empty($s['company_name']) ? "({$s['company_name']})" : "" ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label font-weight-bold">Invoice Date <span class="text-danger">*</span></label>
                    <input type="date" name="purchase_date" class="form-control font-weight-bold" value="<?= $purchase['purchase_date'] ?>" required>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Items Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-wrap justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-pills mr-2"></i> Inward Medicines & Stock Items
            </h6>
            <button type="button" class="btn btn-sm btn-primary font-weight-bold" onclick="addEditRow()">
                <i class="fas fa-plus mr-1"></i> Add Product Item
            </button>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 align-middle" id="editItemsTable">
                    <thead class="bg-light text-dark font-weight-bold text-uppercase small">
                        <tr>
                            <th style="min-width: 240px;">Medicine</th>
                            <th style="width: 85px;" class="text-center">Qty (Pcs)</th>
                            <th style="width: 120px;" class="text-right">Cost / Rate</th>
                            <th style="width: 110px;" class="text-right">TP Ref</th>
                            <th style="width: 85px;" class="text-center">Bonus (Pcs)</th>
                            <th style="width: 85px;" class="text-center">Disc %</th>
                            <th style="width: 85px;" class="text-center">GST %</th>
                            <th style="width: 125px;" class="text-right">Total (Rs.)</th>
                            <th style="width: 45px;" class="text-center"></th>
                        </tr>
                    </thead>
                    <tbody id="editItemsBody">
                        <!-- Populated via JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 3. Bottom Row: Payment & Notes (Left) + Totals & Actions (Right) -->
    <div class="row">
        <div class="col-lg-7 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-credit-card mr-2"></i> Payment & Remarks
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Payment Type:</label>
                        <select name="payment_type" class="form-control font-weight-bold">
                            <option value="Credit" <?= ($purchase['payment_type'] === 'Credit') ? 'selected' : '' ?>>Credit (Udhaar / Payable)</option>
                            <option value="Cash" <?= ($purchase['payment_type'] === 'Cash') ? 'selected' : '' ?>>Cash (Immediate Paid)</option>
                            <option value="Bank" <?= ($purchase['payment_type'] === 'Bank') ? 'selected' : '' ?>>Bank Transfer</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label font-weight-bold text-muted">Notes / Delivery Remarks</label>
                        <textarea name="notes" rows="4" class="form-control" placeholder="Optional notes..."><?= htmlspecialchars($purchase['notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-calculator mr-2"></i> Totals & Payment Summary
                    </h6>
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <div class="p-3 bg-light rounded mb-3 border">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Subtotal:</span>
                            <strong id="dispSubtotal" class="font-weight-bold">Rs. <?= number_format($purchase['subtotal'], 2) ?></strong>
                        </div>
                        <div class="mb-2">
                            <label class="small font-weight-bold text-muted mb-0">Discount (Rs.):</label>
                            <input type="number" step="0.01" name="discount_amount" id="editDiscount" class="form-control form-control-sm text-right font-weight-bold" placeholder="0.00" value="<?= $purchase['discount_amount'] > 0 ? $purchase['discount_amount'] : '' ?>" onfocus="this.select()" oninput="recalcEditTotals()">
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Total Items GST:</span>
                            <strong id="dispTax" class="font-weight-bold text-primary">Rs. <?= number_format($purchase['tax_amount'] ?? 0, 2) ?></strong>
                            <input type="hidden" name="tax_amount" id="editTax" value="<?= $purchase['tax_amount'] ?? 0 ?>">
                        </div>
                        <div class="mb-2">
                            <label class="small font-weight-bold text-muted mb-0">Freight (Rs.):</label>
                            <input type="number" step="0.01" name="freight_charges" id="editFreight" class="form-control form-control-sm text-right font-weight-bold" placeholder="0.00" value="<?= $purchase['freight_charges'] > 0 ? $purchase['freight_charges'] : '' ?>" onfocus="this.select()" oninput="recalcEditTotals()">
                        </div>
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top border-2 mb-2">
                            <span class="font-weight-bold text-primary">Grand Total:</span>
                            <span id="dispGrandTotal" class="font-weight-bold text-primary" style="font-size: 1.25rem;">Rs. <?= number_format($purchase['grand_total'], 2) ?></span>
                        </div>
                        <div class="mb-2">
                            <label class="small text-success font-weight-bold mb-0">Paid Amount (Rs.):</label>
                            <input type="number" step="0.01" name="paid_amount" id="editPaid" class="form-control form-control-sm text-right font-weight-bold text-success" placeholder="0.00" value="<?= $purchase['paid_amount'] > 0 ? $purchase['paid_amount'] : '' ?>" onfocus="this.select()" oninput="recalcEditTotals()">
                        </div>
                        <div class="d-flex justify-content-between pt-2 border-top">
                            <span class="font-weight-bold text-danger">Balance Due:</span>
                            <span id="dispBalance" class="font-weight-bold text-danger" style="font-size: 1.1rem;">Rs. <?= number_format($purchase['balance_amount'], 2) ?></span>
                        </div>

                        <input type="hidden" name="subtotal" id="hSubtotal" value="<?= $purchase['subtotal'] ?>">
                        <input type="hidden" name="grand_total" id="hGrand" value="<?= $purchase['grand_total'] ?>">
                        <input type="hidden" name="balance_amount" id="hBal" value="<?= $purchase['balance_amount'] ?>">
                    </div>

                    <div>
                        <button type="submit" name="update_purchase" class="btn btn-primary btn-block font-weight-bold py-2 shadow-sm">
                            <i class="fas fa-save mr-1"></i> Update Purchase Bill
                        </button>
                        <a href="view_purchase.php?id=<?= $purchase['id'] ?>" class="btn btn-outline-secondary btn-block mt-2">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
const productsCatalog = <?= json_encode($products) ?>;
const existingItems = <?= json_encode($items) ?>;
const systemUnits = <?= json_encode($units) ?>;
let editCounter = 0;

function getEditUnitOptionsHtml(selectedUnit = '') {
    if (!systemUnits || systemUnits.length === 0) {
        return `<option value="Pack" ${selectedUnit === 'Pack' ? 'selected' : ''}>Pack</option>
                <option value="Box" ${selectedUnit === 'Box' ? 'selected' : ''}>Box</option>`;
    }
    return systemUnits.map(u => {
        const uName = u.name || '';
        const isSel = (selectedUnit && (uName.toLowerCase() === selectedUnit.toLowerCase() || (u.short_name && u.short_name.toLowerCase() === selectedUnit.toLowerCase()))) ? 'selected' : '';
        return `<option value="${uName}" ${isSel}>${uName}</option>`;
    }).join('');
}

function addEditRow(itemData = null) {
    editCounter++;
    const tbody = document.getElementById('editItemsBody');
    const tr = document.createElement('tr');
    tr.id = 'erow_' + editCounter;

    const selectedPid = itemData ? itemData.product_id : '';
    const batchNo     = itemData ? itemData.batch_no : ('B-' + Math.floor(1000 + Math.random() * 9000));
    const expiry      = itemData ? itemData.expiry_date : '<?= date("Y-m-d", strtotime("+2 years")) ?>';
    const qty         = itemData ? itemData.quantity : '';
    const bonus       = (itemData && itemData.bonus_quantity > 0) ? itemData.bonus_quantity : '';
    const cost        = (itemData && parseFloat(itemData.purchase_price) > 0) ? parseFloat(itemData.purchase_price).toFixed(2) : '';
    const tp          = (itemData && parseFloat(itemData.trade_price) > 0) ? parseFloat(itemData.trade_price).toFixed(2) : '';
    const discPct     = (itemData && parseFloat(itemData.discount_percent) > 0) ? parseFloat(itemData.discount_percent).toFixed(2) : '';
    const gstPct      = (itemData && parseFloat(itemData.tax_percent) > 0) ? parseFloat(itemData.tax_percent).toFixed(2) : '';
    const total       = (itemData && parseFloat(itemData.total_price) > 0) ? parseFloat(itemData.total_price).toFixed(2) : '';
    const pbox        = itemData ? (itemData.packs_per_box || 1) : 1;
    const selectedUnit = itemData ? (itemData.unit_type || itemData.stock_unit || 'Pack') : 'Pack';

    tr.innerHTML = `
        <td>
            <select name="items[${editCounter}][product_id]" class="form-select form-select-sm fw-semibold" required onchange="onEditProductChange(${editCounter})">
                <option value="">-- Choose Product --</option>
                ${productsCatalog.map(p => `
                    <option value="${p.id}" 
                            data-cost="${p.purchase_price}" 
                            data-tp="${p.trade_price}"
                            data-disc="${p.discount_percent || 0}"
                            data-stockunit="${p.stock_unit || 'Pack'}"
                            data-pbox="${p.packs_per_box || 1}"
                            ${p.id == selectedPid ? 'selected' : ''}>
                        ${p.name} [${p.product_code}]
                    </option>
                `).join('')}
            </select>
            <input type="hidden" name="items[${editCounter}][packs_per_box]" id="epbox_${editCounter}" value="${pbox}">
            <input type="hidden" id="ebaseCost_${editCounter}" value="${cost || '0.00'}">
            <input type="hidden" id="ebaseTp_${editCounter}" value="${tp || '0.00'}">
        </td>
        <input type="hidden" name="items[${editCounter}][batch_no]" value="${batchNo}">
        <input type="hidden" name="items[${editCounter}][expiry_date]" value="${expiry}">
        <td>
            <input type="number" min="1" name="items[${editCounter}][quantity]" id="eqty_${editCounter}" class="form-control form-control-sm text-center fw-bold" value="${qty}" placeholder="1" onfocus="this.select()" required oninput="calcEditRow(${editCounter})">
        </td>
        <td>
            <input type="number" step="0.01" name="items[${editCounter}][purchase_price]" id="ecost_${editCounter}" class="form-control form-control-sm text-end font-monospace text-danger fw-bold" value="${cost}" placeholder="0.00" onfocus="this.select()" required oninput="calcEditRow(${editCounter})">
        </td>
        <td>
            <input type="number" step="0.01" min="0" name="items[${editCounter}][trade_price]" id="etp_${editCounter}" class="form-control form-control-sm text-end font-monospace text-primary" value="${tp}" placeholder="0.00" onfocus="this.select()" oninput="calcEditRow(${editCounter})">
        </td>
        <td>
            <input type="number" min="0" name="items[${editCounter}][bonus_quantity]" id="ebonus_${editCounter}" class="form-control form-control-sm text-center" value="${bonus}" placeholder="0" onfocus="this.select()" oninput="calcEditRow(${editCounter})">
        </td>
        <td>
            <input type="number" step="0.01" min="0" max="100" name="items[${editCounter}][discount_percent]" id="edisc_${editCounter}" class="form-control form-control-sm text-center" value="${discPct}" placeholder="0.00" onfocus="this.select()" oninput="calcEditRow(${editCounter})">
            <input type="hidden" name="items[${editCounter}][discount_amount]" id="ediscAmt_${editCounter}" value="0.00">
        </td>
        <td>
            <input type="number" step="0.01" min="0" max="100" name="items[${editCounter}][tax_percent]" id="egst_${editCounter}" class="form-control form-control-sm text-center text-primary fw-bold" value="${gstPct}" placeholder="0.00" onfocus="this.select()" oninput="calcEditRow(${editCounter})">
            <input type="hidden" name="items[${editCounter}][tax_amount]" id="etaxAmt_${editCounter}" value="0.00">
        </td>
        <td>
            <input type="number" step="0.01" readonly name="items[${editCounter}][total_price]" id="erowTot_${editCounter}" class="form-control form-control-sm text-end font-monospace fw-bold bg-success-subtle" value="${total}" placeholder="0.00">
            <div id="ediscDisplay_${editCounter}" class="text-end mt-1" style="font-size: 0.70rem; min-height: 14px; line-height: 1.3;"></div>
        </td>
        <td class="text-center">
            <button type="button" class="btn-remove-row" onclick="removeEditRow(${editCounter})">
                <i class="fas fa-times"></i>
            </button>
        </td>
    `;

    tbody.appendChild(tr);
    calcEditRow(editCounter);
}

function onEditProductChange(rowId) {
    const tr = document.getElementById('erow_' + rowId);
    const select = tr.querySelector('select');
    const opt = select.options[select.selectedIndex];
    if (!opt || !opt.value) return;

    const baseCost  = parseFloat(opt.getAttribute('data-cost') || 0);
    const baseTp    = parseFloat(opt.getAttribute('data-tp') || 0);
    const discPct   = parseFloat(opt.getAttribute('data-disc') || 0);

    document.getElementById('ebaseCost_' + rowId).value = baseCost.toFixed(2);
    document.getElementById('ebaseTp_' + rowId).value   = baseTp.toFixed(2);
    document.getElementById('edisc_' + rowId).value     = discPct.toFixed(2);
    document.getElementById('ecost_' + rowId).value     = baseCost.toFixed(2);
    document.getElementById('etp_' + rowId).value       = baseTp.toFixed(2);

    calcEditRow(rowId);
}

function calcEditRow(rowId) {
    const qtyEl      = document.getElementById('eqty_' + rowId);
    const costEl     = document.getElementById('ecost_' + rowId);
    const bonusEl    = document.getElementById('ebonus_' + rowId);
    const discEl     = document.getElementById('edisc_' + rowId);
    const discAmtEl  = document.getElementById('ediscAmt_' + rowId);
    const totalEl    = document.getElementById('erowTot_' + rowId);
    if (!qtyEl || !costEl || !bonusEl || !discEl || !discAmtEl || !totalEl) return;

    const qty      = parseFloat(qtyEl.value)   || 0;
    const cost     = parseFloat(costEl.value)  || 0;
    const bonusPct = parseFloat(bonusEl.value) || 0;
    const discPct  = parseFloat(discEl.value)  || 0;

    // Gross = Qty × Cost
    // BonusAmt = Gross × Bonus% / 100   ← company bonus, bill se minus
    // DiscAmt  = Gross × Disc%  / 100   ← trade discount, bill se minus
    // Total    = Gross - BonusAmt - DiscAmt
    const gross    = qty * cost;
    const bonusAmt = parseFloat((gross * (bonusPct / 100)).toFixed(2));
    const dAmt     = parseFloat((gross * (discPct  / 100)).toFixed(2));
    const totalRed = parseFloat((bonusAmt + dAmt).toFixed(2));
    const netBefTax = Math.max(0, parseFloat((gross - totalRed).toFixed(2)));
    const gstPct   = parseFloat(document.getElementById('egst_' + rowId)?.value) || 0;
    const taxAmt   = parseFloat((netBefTax * (gstPct / 100)).toFixed(2));
    const net      = Math.max(0, parseFloat((netBefTax + taxAmt).toFixed(2)));

    discAmtEl.value = totalRed.toFixed(2);
    const taxAmtEl = document.getElementById('etaxAmt_' + rowId);
    if (taxAmtEl) taxAmtEl.value = taxAmt.toFixed(2);
    totalEl.value   = net.toFixed(2);

    const discDisplay = document.getElementById('ediscDisplay_' + rowId);
    if (discDisplay) {
        const parts = [];
        if (bonusAmt > 0) parts.push(`<span class="text-primary">Bonus: -${bonusAmt.toFixed(2)}</span>`);
        if (dAmt > 0)     parts.push(`<span class="text-success">Disc: -${dAmt.toFixed(2)}</span>`);
        if (taxAmt > 0)   parts.push(`<span class="text-primary font-weight-bold">GST: +${taxAmt.toFixed(2)}</span>`);
        discDisplay.innerHTML = parts.join(' ');
    }

    recalcEditTotals();
}

function removeEditRow(rowId) {
    const tr = document.getElementById('erow_' + rowId);
    if (tr) {
        tr.remove();
        recalcEditTotals();
    }
}

function recalcEditTotals() {
    const rows = document.querySelectorAll('#editItemsBody tr');
    let subtotal = 0;
    let totalTax = 0;

    rows.forEach(tr => {
        const rowId = tr.id.replace('erow_', '');
        subtotal += parseFloat(document.getElementById('erowTot_' + rowId)?.value) || 0;
        totalTax += parseFloat(document.getElementById('etaxAmt_' + rowId)?.value) || 0;
    });

    const disc = parseFloat(document.getElementById('editDiscount').value) || 0;
    const freight = parseFloat(document.getElementById('editFreight').value) || 0;
    const grand = Math.max(0, (subtotal - disc) + freight);
    const paid = parseFloat(document.getElementById('editPaid').value) || 0;
    const bal = Math.max(0, grand - paid);

    document.getElementById('dispSubtotal').textContent = 'Rs. ' + subtotal.toFixed(2);
    const dispTaxEl = document.getElementById('dispTax');
    if (dispTaxEl) dispTaxEl.textContent = 'Rs. ' + totalTax.toFixed(2);
    const editTaxEl = document.getElementById('editTax');
    if (editTaxEl) editTaxEl.value = totalTax.toFixed(2);

    document.getElementById('dispGrandTotal').textContent = 'Rs. ' + grand.toFixed(2);
    document.getElementById('dispBalance').textContent = 'Rs. ' + bal.toFixed(2);

    document.getElementById('hSubtotal').value = subtotal.toFixed(2);
    document.getElementById('hGrand').value = grand.toFixed(2);
    document.getElementById('hBal').value = bal.toFixed(2);
}

document.addEventListener('DOMContentLoaded', function() {
    if (existingItems && existingItems.length > 0) {
        existingItems.forEach(item => addEditRow(item));
    } else {
        addEditRow();
    }
});

// Auto-select contents on focus so typing immediately overwrites without manual deletion
document.addEventListener('focus', function(e) {
    if (e.target && (e.target.matches('input[type="number"]') || e.target.classList.contains('form-control'))) {
        e.target.select();
    }
}, true);
</script>

<?php require_once __DIR__ . '/../../includes/footer.php';
if (ob_get_level()) { ob_end_flush(); }
?>
