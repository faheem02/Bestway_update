<?php
ob_start();
/**
 * Bestway Wholesale Distribution - Add Inward Purchase (GRN)
 * Enterprise B2B Multi-Unit Batch & Expiry Management
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['admin']);

// Quick Add Supplier AJAX Endpoint
if (isset($_GET['action']) && $_GET['action'] === 'quick_supplier' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $s_name    = trim($_POST['supplier_name'] ?? '');
    $s_company = trim($_POST['supplier_company'] ?? '');
    $s_phone   = trim($_POST['supplier_phone'] ?? '');
    $s_address = trim($_POST['supplier_address'] ?? '');

    if (empty($s_name)) {
        echo json_encode(['success' => false, 'message' => 'Supplier name is required.']);
        exit;
    }

    try {
        $stmt_sup = $pdo->prepare("
            INSERT INTO suppliers (supplier_code, name, company_name, phone, address, opening_balance, current_balance, status)
            VALUES (?, ?, ?, ?, ?, 0.00, 0.00, 'Active')
        ");
        $sup_code = "SUP-" . rand(1000, 9999);
        $stmt_sup->execute([$sup_code, $s_name, $s_company !== '' ? $s_company : '', $s_phone !== '' ? $s_phone : '', $s_address ?: null]);
        $new_id = $pdo->lastInsertId();
        echo json_encode([
            'success' => true,
            'id'      => $new_id,
            'name'    => $s_name,
            'company_name' => $s_company,
            'balance' => "0.00"
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Live Search Supplier AJAX Endpoint
if (isset($_GET['action']) && $_GET['action'] === 'search_supplier') {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    $results = [];
    if ($db_connected && $pdo) {
        try {
            if ($q === '') {
                $stmt = $pdo->query("SELECT id, name, company_name, phone, current_balance FROM suppliers WHERE status = 'Active' ORDER BY name ASC LIMIT 25");
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $term = "%{$q}%";
                $stmt = $pdo->prepare("
                    SELECT id, name, company_name, phone, current_balance 
                    FROM suppliers 
                    WHERE status = 'Active' 
                      AND (name LIKE :q1 OR company_name LIKE :q2 OR phone LIKE :q3)
                    ORDER BY 
                      CASE 
                        WHEN name LIKE :exact THEN 1
                        WHEN name LIKE :start THEN 2
                        WHEN company_name LIKE :start_c THEN 3
                        ELSE 4
                      END, name ASC 
                    LIMIT 25
                ");
                $stmt->execute([
                    ':q1' => $term,
                    ':q2' => $term,
                    ':q3' => $term,
                    ':exact' => $q,
                    ':start' => "{$q}%",
                    ':start_c' => "{$q}%"
                ]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {}
    }
    echo json_encode($results);
    exit;
}

// Live Search Medicine & Brand AJAX Endpoint
if (isset($_GET['action']) && $_GET['action'] === 'search_product') {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    $results = [];
    if ($db_connected && $pdo) {
        try {
            if ($q === '') {
                $stmt = $pdo->query("
                    SELECT 
                        p.id, p.product_code, p.name, p.purchase_price, p.trade_price, p.discount_percent,
                        p.stock_unit, p.packs_per_box, p.tablets_per_pack, p.current_stock,
                        c.name as company_name, cat.name as category_name,
                        u.name as base_unit_name, u.short_name as base_unit_short
                    FROM products p
                    LEFT JOIN companies c ON p.company_id = c.id
                    LEFT JOIN categories cat ON p.category_id = cat.id
                    LEFT JOIN units u ON p.unit_id = u.id
                    ORDER BY p.name ASC 
                    LIMIT 25
                ");
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $term = "%{$q}%";
                $stmt = $pdo->prepare("
                    SELECT 
                        p.id, p.product_code, p.name, p.purchase_price, p.trade_price, p.discount_percent,
                        p.stock_unit, p.packs_per_box, p.tablets_per_pack, p.current_stock,
                        c.name as company_name, cat.name as category_name,
                        u.name as base_unit_name, u.short_name as base_unit_short
                    FROM products p
                    LEFT JOIN companies c ON p.company_id = c.id
                    LEFT JOIN categories cat ON p.category_id = cat.id
                    LEFT JOIN units u ON p.unit_id = u.id
                    WHERE p.name LIKE :q1 
                       OR p.product_code LIKE :q2 
                       OR c.name LIKE :q3 
                       OR cat.name LIKE :q4
                    ORDER BY 
                      CASE 
                        WHEN p.name LIKE :exact THEN 1
                        WHEN p.name LIKE :start THEN 2
                        WHEN c.name LIKE :start_c THEN 3
                        ELSE 4
                      END, p.name ASC 
                    LIMIT 30
                ");
                $stmt->execute([
                    ':q1' => $term,
                    ':q2' => $term,
                    ':q3' => $term,
                    ':q4' => $term,
                    ':exact' => $q,
                    ':start' => "{$q}%",
                    ':start_c' => "{$q}%"
                ]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {}
    }
    echo json_encode($results);
    exit;
}

$message  = "";
$msg_type = "";

// Auto Generate Purchase Bill Reference (e.g. PUR-2026-0001)
$auto_bill_no = "PUR-" . date('Y') . "-0001";
if ($db_connected && $pdo) {
    try {
        $stmt_seq = $pdo->query("SELECT bill_no FROM purchases WHERE bill_no REGEXP '^PUR-[0-9]{4}-[0-9]+$' ORDER BY id DESC LIMIT 1");
        $last_bill = $stmt_seq->fetchColumn();
        if ($last_bill && preg_match('/PUR-\d+-(\d+)/i', $last_bill, $matches)) {
            $next_num = intval($matches[1]) + 1;
            $auto_bill_no = "PUR-" . date('Y') . "-" . str_pad($next_num, 4, '0', STR_PAD_LEFT);
        } else {
            $stmt_cnt = $pdo->query("SELECT COUNT(*) FROM purchases");
            $cnt = intval($stmt_cnt->fetchColumn()) + 1;
            $auto_bill_no = "PUR-" . date('Y') . "-" . str_pad($cnt, 4, '0', STR_PAD_LEFT);
        }
    } catch (Exception $e) {}
}

// Handle Purchase Form Submission
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (isset($_POST['save_purchase']) || isset($_POST['items']))) {
    $bill_no         = trim($_POST['bill_no'] ?? $auto_bill_no);
    $supplier_id     = intval($_POST['supplier_id'] ?? 0);
    $purchase_date   = trim($_POST['purchase_date'] ?? date('Y-m-d'));
    $receiving_date  = trim($_POST['receiving_date'] ?? $purchase_date);
    $payment_type    = trim($_POST['payment_type'] ?? 'Credit');
    $bank_account_id = intval($_POST['bank_account_id'] ?? 0);
    $notes           = trim($_POST['notes'] ?? '');

    $subtotal        = floatval($_POST['subtotal'] ?? 0);
    $discount_amount = floatval($_POST['discount_amount'] ?? 0);
    $tax_amount      = floatval($_POST['tax_amount'] ?? 0);
    $freight_charges = floatval($_POST['freight_charges'] ?? 0);
    $grand_total     = floatval($_POST['grand_total'] ?? 0);
    $paid_amount     = floatval($_POST['paid_amount'] ?? 0);
    $balance_amount  = floatval($_POST['balance_amount'] ?? 0);

    // Items array
    $items = $_POST['items'] ?? [];

    if ($supplier_id <= 0) {
        $message = "Please select a supplier.";
        $msg_type = "danger";
    } elseif (empty($items) || !is_array($items)) {
        $message = "Please add at least one product item.";
        $msg_type = "danger";
    } else {
        if ($db_connected && $pdo) {
            try {
                $pdo->beginTransaction();

                // Payment Status Determination
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

                $user_id = $_SESSION['user_id'] ?? 1;

                // 1. Insert into purchases table
                $stmt_pur = $pdo->prepare("
                    INSERT INTO purchases (
                        bill_no, purchase_date, receiving_date, supplier_id,
                        subtotal, discount_amount, tax_amount, freight_charges,
                        grand_total, paid_amount, balance_amount,
                        payment_type, payment_status, status, notes, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Received', ?, ?)
                ");
                $stmt_pur->execute([
                    $bill_no, $purchase_date, $receiving_date, $supplier_id,
                    $subtotal, $discount_amount, $tax_amount, $freight_charges,
                    $grand_total, $paid_amount, $balance_amount,
                    $payment_type, $payment_status, $notes, $user_id
                ]);
                $purchase_id = $pdo->lastInsertId();

                // 2. Insert items, update batches and increment products stock
                $stmt_item = $pdo->prepare("
                    INSERT INTO purchase_items (
                        purchase_id, product_id, batch_no, expiry_date,
                        quantity, raw_quantity, unit_type, bonus_quantity, purchase_price, trade_price,
                        retail_price, discount_percent, discount_amount, tax_percent, tax_amount, total_price
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt_stock = $pdo->prepare("
                    UPDATE products 
                    SET current_stock = current_stock + ?, 
                        purchase_price = ?,
                        discount_percent = CASE WHEN ? > 0 THEN ? ELSE discount_percent END,
                        retail_price = CASE WHEN ? > 0 THEN ? ELSE retail_price END
                    WHERE id = ?
                ");

                // Check or insert batch
                $stmt_chk_batch = $pdo->prepare("
                    SELECT id FROM product_batches 
                    WHERE product_id = ? AND batch_no = ? 
                    LIMIT 1
                ");
                $stmt_upd_batch = $pdo->prepare("
                    UPDATE product_batches 
                    SET current_stock = current_stock + ?, 
                        expiry_date = ?, 
                        purchase_price = ?, 
                        trade_price = CASE WHEN ? > 0 THEN ? ELSE trade_price END,
                        retail_price = CASE WHEN ? > 0 THEN ? ELSE retail_price END
                    WHERE id = ?
                ");
                $stmt_ins_batch = $pdo->prepare("
                    INSERT INTO product_batches (
                        product_id, batch_no, expiry_date, 
                        purchase_price, trade_price, retail_price,
                        initial_quantity, current_stock, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active')
                ");

                foreach ($items as $itm) {
                    $pid         = intval($itm['product_id'] ?? 0);
                    $batch_no    = trim($itm['batch_no'] ?? 'DEFAULT');
                    $expiry_date = !empty($itm['expiry_date']) ? $itm['expiry_date'] : date('Y-m-d', strtotime('+2 years'));
                    
                    // Quantity and Bonus in Pieces (Pcs) directly
                    $raw_qty         = max(0, intval($itm['quantity'] ?? 0));
                    $raw_bonus       = max(0, intval($itm['bonus_quantity'] ?? 0));
                    $all_total_stock = $raw_qty + $raw_bonus; // Total physical stock inward (purchased + bonus)

                    // Rate entered by user (Purchase Cost to supplier)
                    $p_cost         = floatval($itm['purchase_price'] ?? 0);
                    // tp_ JS field is updated to calcSalePrice when user sets Sale Discount %; use it if available
                    $form_tp        = floatval($itm['trade_price'] ?? 0);
                    $sale_disc_pct  = floatval($itm['sale_discount_percent'] ?? 0);
                    $sale_price     = floatval($itm['sale_price'] ?? 0);
                    // Determine the Trade Price to store (selling rate):
                    // Priority: explicit sale_price > form trade_price > p_cost*(1-sale_disc%) > p_cost
                    if ($sale_price > 0) {
                        $tp_rate = $sale_price; // JS-computed sale price (baseTp * (1 - saleDisc%))
                    } elseif ($sale_disc_pct > 0 && $p_cost > 0) {
                        $tp_rate    = max(0, $p_cost * (1 - ($sale_disc_pct / 100)));
                        $sale_price = $tp_rate;
                    } elseif ($form_tp > 0) {
                        $tp_rate    = $form_tp;
                        $sale_price = $form_tp;
                    } else {
                        $tp_rate    = $p_cost; // fallback: no discount set, use purchase cost as TP
                        $sale_price = $p_cost;
                    }
                    $disc_pct       = floatval($itm['discount_percent'] ?? 0);
                    
                    // Financial calculation: gross = raw_qty * p_cost, disc_amt = gross * disc_pct / 100
                    $gross_row = $raw_qty * $p_cost;
                    $disc_amt  = round($gross_row * ($disc_pct / 100), 2);
                    $net_bef_tax = max(0, $gross_row - $disc_amt);
                    $tax_pct   = floatval($itm['tax_percent'] ?? 0);
                    $tax_amt   = floatval($itm['tax_amount'] ?? 0);
                    if ($tax_amt <= 0 && $tax_pct > 0) {
                        $tax_amt = round($net_bef_tax * ($tax_pct / 100), 2);
                    }
                    $row_total = floatval($itm['total_price'] ?? max(0, $net_bef_tax + $tax_amt));

                    // Distribute overall bill discount proportionally to calculate net effective cost
                    $bill_disc_ratio = ($subtotal > 0 && $discount_amount > 0) ? min(1, $discount_amount / $subtotal) : 0;
                    $row_net_after_bill_disc = max(0, $row_total * (1 - $bill_disc_ratio));

                    // Net effective unit cost after BOTH discounts and item GST, spread over total received qty incl. bonus
                    $net_unit_cost = ($all_total_stock > 0) ? ($row_net_after_bill_disc / $all_total_stock) : $p_cost;

                    if ($pid > 0 && $all_total_stock > 0) {
                        // Insert Item Record
                        $stmt_item->execute([
                            $purchase_id, $pid, $batch_no, $expiry_date,
                            $raw_qty, $raw_qty, 'Pcs', $raw_bonus, $p_cost, $tp_rate,
                            $sale_price, $disc_pct, $disc_amt, $tax_pct, $tax_amt, $row_total
                        ]);

                        // Update Product Main Stock & Rates: add all_total_stock (purchased + bonus)
                        // NOTE: products.trade_price is NOT updated here — official TP comes from Add/Edit Product
                        $stmt_stock->execute([
                            $all_total_stock, 
                            $net_unit_cost, 
                            $sale_disc_pct, $sale_disc_pct, 
                            $sale_price, $sale_price, 
                            $pid
                        ]);

                        // Handle Product Batch: add all_total_stock
                        $stmt_chk_batch->execute([$pid, $batch_no]);
                        $existing_batch_id = $stmt_chk_batch->fetchColumn();

                        if ($existing_batch_id) {
                            $stmt_upd_batch->execute([
                                $all_total_stock, 
                                $expiry_date, 
                                $net_unit_cost, 
                                $tp_rate, $tp_rate, 
                                $sale_price, $sale_price, 
                                $existing_batch_id
                            ]);
                        } else {
                            $stmt_ins_batch->execute([
                                $pid, $batch_no, $expiry_date,
                                $net_unit_cost, $tp_rate, $sale_price,
                                $all_total_stock, $all_total_stock
                            ]);
                        }
                    }
                }

                // 3. Supplier Ledger Update & Sync
                syncSupplierLedger($pdo, $supplier_id);

                // 4. Record Payment in Supplier Payments & Cash/Bank Book if Paid Amount > 0
                if ($paid_amount > 0) {
                    $voucher_no = "PV-" . date('Ymd') . "-" . rand(100, 999);
                    $stmt_spay = $pdo->prepare("
                        INSERT INTO supplier_payments (
                            voucher_no, payment_date, supplier_id, purchase_id,
                            amount, payment_method, bank_account_id,
                            remarks, created_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $pay_method = ($payment_type === 'Bank') ? 'Bank Transfer' : 'Cash';
                    $stmt_spay->execute([
                        $voucher_no, $purchase_date, $supplier_id, $purchase_id,
                        $paid_amount, $pay_method, $bank_account_id ?: null,
                        "Immediate Payment for Bill #{$bill_no}", $user_id
                    ]);

                    // Deduct from Cash / Bank Ledger
                    $sup_name_stmt = $pdo->prepare("SELECT name FROM suppliers WHERE id = ?");
                    $sup_name_stmt->execute([$supplier_id]);
                    $s_p_name = $sup_name_stmt->fetchColumn() ?: "Supplier";
                    $pay_desc = "Cash Paid on Purchase Bill #{$bill_no} - {$s_p_name}";

                    if ($payment_type === 'Cash') {
                        // Default cash account
                        $c_acc = $pdo->query("SELECT id FROM cash_accounts WHERE is_default = 1 LIMIT 1")->fetch();
                        $cash_acc_id = $c_acc ? $c_acc['id'] : 1;

                        // Update Cash Account Balance
                        $pdo->prepare("UPDATE cash_accounts SET balance = balance - ? WHERE id = ?")->execute([$paid_amount, $cash_acc_id]);

                        // Record in Cash Book (visible in Cashbook module)
                        recordCashOutflow($pdo, $purchase_date, $paid_amount, $pay_desc, 'purchase', $purchase_id, $user_id);
                    } elseif ($payment_type === 'Bank') {
                        // If no specific bank account, use first available
                        if (!$bank_account_id) {
                            $first_bank = $pdo->query("SELECT id FROM bank_accounts WHERE status = 'Active' ORDER BY id ASC LIMIT 1")->fetch();
                            $bank_account_id = $first_bank ? (int)$first_bank['id'] : 0;
                        }
                        if ($bank_account_id > 0) {
                            $bank_pay_desc = "Bank Transfer on Purchase Bill #{$bill_no} - {$s_p_name}";
                            // Update Bank Account Balance
                            $pdo->prepare("UPDATE bank_accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$paid_amount, $bank_account_id]);
                            // Record in Bank Book (visible in Bankbook module)
                            recordBankOutflow($pdo, $purchase_date, $paid_amount, $bank_pay_desc, 'purchase', $purchase_id, $user_id, $bank_account_id);
                        }
                    }
                }

                $pdo->commit();

                // Redirect to view purchase bill or catalog
                header("Location: view_purchase.php?id={$purchase_id}&msg=created");
                exit;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $message = "Error saving purchase: " . $e->getMessage();
                $msg_type = "danger";
            }
        }
    }
}

$page_title = "Add Inward Purchase";
$compact_page_heading = true;
require_once __DIR__ . '/../../includes/header.php';

// Fetch Suppliers, Products, Units, and Bank Accounts
$suppliers = [];
$products  = [];
$units     = [];
$bank_accounts = [];

if ($db_connected && $pdo) {
    try {
        $suppliers = $pdo->query("
            SELECT id, name, company_name, current_balance, phone 
            FROM suppliers 
            WHERE status = 'Active' 
            ORDER BY name ASC
        ")->fetchAll();

        $units = $pdo->query("SELECT id, name, short_name FROM units ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($units)) {
            $units = [
                ['id' => 1, 'name' => 'Pack', 'short_name' => 'Pac'],
                ['id' => 2, 'name' => 'Box', 'short_name' => 'Box']
            ];
        }

        $products = $pdo->query("
            SELECT 
                p.id, p.product_code, p.name, p.purchase_price, p.trade_price, p.discount_percent,
                p.stock_unit, p.packs_per_box, p.tablets_per_pack, p.current_stock,
                c.name as company_name, cat.name as category_name,
                u.name as base_unit_name, u.short_name as base_unit_short
            FROM products p
            LEFT JOIN companies c ON p.company_id = c.id
            LEFT JOIN categories cat ON p.category_id = cat.id
            LEFT JOIN units u ON p.unit_id = u.id
            ORDER BY p.name ASC
        ")->fetchAll();

        $bank_accounts = $pdo->query("
            SELECT id, bank_name, account_title, account_number, current_balance 
            FROM bank_accounts 
            WHERE status = 'Active' 
            ORDER BY bank_name ASC
        ")->fetchAll();

        // Cash accounts - to check if Cash payment method should be shown
        $cash_accounts = $pdo->query("
            SELECT id, account_name, balance 
            FROM cash_accounts 
            ORDER BY is_default DESC, id ASC
        ")->fetchAll();

    } catch (Exception $e) {}
}
?>

<style>
    .items-table {
        margin-bottom: 0;
        width: 100%;
    }
    .items-table thead th {
        background: #f8fafc;
        color: #475569;
        font-size: 0.76rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        border-bottom: 2px solid #e3e6f0;
        padding: 10px 8px;
        vertical-align: middle;
        white-space: nowrap;
    }
    .items-table tbody td {
        padding: 8px 6px;
        vertical-align: top;
        border-bottom: 1px solid #e3e6f0;
    }
    .items-table .form-control {
        height: 38px;
        font-size: 0.88rem;
    }
    .items-table input[type=number] {
        -moz-appearance: textfield;
    }
    .items-table input[type=number]::-webkit-outer-spin-button,
    .items-table input[type=number]::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    .col-all-total {
        background-color: #f0fdf4 !important;
        color: #166534 !important;
        font-weight: 700 !important;
    }
    .col-row-total {
        background-color: #f8fafc !important;
        color: #0f172a !important;
        font-weight: 700 !important;
    }
    .items-table tbody tr {
        transition: background-color 0.15s ease;
    }
    .items-table tbody tr.active-item-row {
        background-color: #f0f9ff !important;
    }
    .items-table tbody tr.active-item-row td {
        background-color: #f0f9ff !important;
    }

    .calc-box {
        background: #f8fafc;
        border: 1px solid #e3e6f0;
        border-radius: 8px;
        padding: 16px;
    }
    .calc-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        font-size: 0.9rem;
    }
    .calc-row.grand-total {
        font-size: 1.25rem;
        font-weight: 800;
        color: #4e73df;
        padding-top: 10px;
        border-top: 2px dashed #cbd5e1;
        margin-bottom: 10px;
    }
    .calc-row.balance-due {
        font-size: 1.1rem;
        font-weight: 800;
        color: #e74a3b;
        padding-top: 8px;
        border-top: 1px solid #e3e6f0;
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

    /* Live Search Dropdown Styles */
    .items-table-wrapper { overflow: visible !important; }
    .table-responsive { overflow: visible !important; }

    .search-dropdown-menu {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #ffffff;
        z-index: 1060;
        max-height: 280px;
        overflow-y: auto;
        margin-top: 4px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15);
    }
    .med-dropdown {
        min-width: 340px;
        width: 100%;
    }
    .search-dropdown-item {
        padding: 8px 12px;
        cursor: pointer;
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.15s ease;
    }
    .search-dropdown-item:last-child {
        border-bottom: none;
    }
    .search-dropdown-item:hover, .search-dropdown-item.active {
        background: #f0f9ff;
        border-left: 3px solid #4e73df;
    }
    .search-dropdown-item .highlight-match {
        background: #fef08a;
        font-weight: 700;
        border-radius: 2px;
        padding: 0 1px;
    }
</style>

<!-- Top Title Bar -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h4 class="font-weight-bold text-dark mb-1">
            <i class="fas fa-cart-plus text-primary mr-2"></i> Add Purchase
        </h4>
        <p class="text-muted small mb-0">Record new stock inward from pharmaceutical manufacturers & suppliers</p>
    </div>
    <div>
        <a href="purchases.php" class="btn btn-sm btn-outline-secondary font-weight-bold">
            <i class="fas fa-list mr-1"></i> View All Purchases
        </a>
    </div>
</div>

<!-- Alert Notifications -->
<?php if (!empty($message)): ?>
    <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show shadow-sm mb-4" role="alert">
        <i class="fas <?= $msg_type === 'success' ? 'fa-check-circle text-success' : 'fa-exclamation-triangle text-danger' ?> mr-2"></i>
        <strong><?= htmlspecialchars($message) ?></strong>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
<?php endif; ?>

<form method="POST" action="" id="purchaseForm" class="needs-validation" novalidate>
    <!-- 1. Bill Header & Supplier Info Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-file-invoice mr-2"></i> Inward Bill & Supplier Details
            </h6>
        </div>
        <div class="card-body">
            <div class="row">
                <!-- Bill No -->
                <div class="col-md-3 mb-3">
                    <label class="form-label font-weight-bold">Purchase Bill / Ref # <span class="text-danger">*</span></label>
                    <input type="text" name="bill_no" id="billNo" class="form-control font-weight-bold bg-light" value="<?= htmlspecialchars($auto_bill_no) ?>" required>
                </div>

                <!-- Supplier Selection & Live Search -->
                <div class="col-md-6 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label font-weight-bold mb-0">Supplier / Manufacturer <span class="text-danger">*</span></label>
                        <button type="button" class="btn btn-sm btn-link p-0 font-weight-bold text-primary" data-toggle="modal" data-target="#modalQuickSupplier">
                            <i class="fas fa-plus-circle mr-1"></i> Quick Add
                        </button>
                    </div>
                    
                    <div class="position-relative" id="supplierSearchContainer">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white"><i class="fas fa-truck text-muted"></i></span>
                            </div>
                            <input type="text" 
                                   id="supplierSearchInput" 
                                   class="form-control font-weight-bold" 
                                   placeholder="Type alphabet or phone to search supplier..." 
                                   autocomplete="off"
                                   onfocus="onSupplierSearchFocus()"
                                   oninput="onSupplierSearchInput()"
                                   onkeydown="onSupplierSearchKeydown(event)">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary d-none" id="supplierClearBtn" onclick="clearSupplierSelection()" title="Clear selection">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <input type="hidden" name="supplier_id" id="supplierSelect" required value="">

                        <!-- Live Supplier Results Dropdown -->
                        <div id="supplierDropdown" class="search-dropdown-menu d-none">
                            <div id="supplierResultsList"></div>
                        </div>
                    </div>

                    <div id="supplierBalText" class="small text-muted mt-1 d-flex justify-content-between align-items-center">
                        <span>Current Payable: <strong class="text-danger font-weight-bold" id="supBalDisplay">Rs. 0.00</strong></span>
                        <span id="selectedSupplierBadge" class="badge badge-light text-primary border d-none"></span>
                    </div>
                </div>

                <!-- Invoice Date -->
                <div class="col-md-3 mb-3">
                    <label class="form-label font-weight-bold">Invoice Date <span class="text-danger">*</span></label>
                    <input type="date" name="purchase_date" class="form-control font-weight-bold" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Product Items Table Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-wrap justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-pills mr-2"></i> Inward Medicines & Stock Items
            </h6>
            <button type="button" class="btn btn-sm btn-primary font-weight-bold" onclick="addNewItemRow()">
                <i class="fas fa-plus mr-1"></i> Add Product Item
            </button>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 align-middle items-table" id="itemsTable">
                    <thead>
                        <tr>
                            <th style="min-width: 220px;">Medicine / Product Name</th>
                            <th style="width: 75px;" class="text-center">Qty</th>
                            <th style="width: 70px;" class="text-center">Bonus</th>
                            <th style="width: 70px;" class="text-center">Total Qty</th>
                            <th style="width: 105px;" class="text-right">TP / Cost</th>
                            <th style="width: 75px;" class="text-center">Disc %</th>
                            <th style="width: 75px;" class="text-center">GST %</th>
                            <th style="width: 110px;" class="text-right">Total (Rs.)</th>
                            <th style="width: 95px;" class="text-center text-success">TP Disc %</th>
                            <th style="width: 115px;" class="text-center text-primary">Sale Disc %</th>
                            <th style="width: 40px;" class="text-center"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsTableBody">
                        <!-- Dynamic Row generated via JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 3. Bottom Row: Payment Details & Notes (Left) + Summary & Save (Right) -->
    <div class="row">
        <!-- Left: Payment Method, Accounts & Notes -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-credit-card mr-2"></i> Payment Details & Remarks
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-weight-bold">Payment Method</label>
                            <select name="payment_type" id="paymentType" class="form-control font-weight-bold" onchange="togglePaymentFields()">
                                <option value="Credit" selected>Credit (Udhaar / Payable)</option>
                                <option value="Cash">Cash (Immediate Paid)</option>
                                <option value="Bank">Bank Transfer</option>
                            </select>
                        </div>

                        <!-- Dynamic Bank Selection Box -->
                        <div id="bankAccountBox" class="col-md-6 mb-3 d-none">
                            <label class="form-label font-weight-bold">Company Bank Account</label>
                            <?php if (!empty($bank_accounts)): ?>
                                <select name="bank_account_id" class="form-control">
                                    <option value="">-- Select Bank --</option>
                                    <?php foreach ($bank_accounts as $b): ?>
                                        <option value="<?= $b['id'] ?>">
                                            <?= htmlspecialchars($b['bank_name']) ?> — <?= htmlspecialchars($b['account_title']) ?> (<?= htmlspecialchars($b['account_number']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <div class="alert alert-warning py-2 mb-1">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Koi bank account create nahi kiya gaya.
                                    <a href="<?= BASE_URL ?>modules/bankbook/index.php" target="_blank" class="font-weight-bold">
                                        Bank Account Add Karen &rarr;
                                    </a>
                                </div>
                                <input type="hidden" name="bank_account_id" value="">
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-2">
                        <label class="form-label font-weight-bold text-muted small">Notes / Delivery Remarks</label>
                        <textarea name="notes" rows="4" class="form-control" placeholder="Optional notes..."></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Calculation Box & Actions -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-calculator mr-2"></i> Bill Summary & Checkout
                    </h6>
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <div class="calc-box mb-3">
                        <div class="calc-row">
                            <span class="text-muted">Total Items:</span>
                            <strong id="summaryItemsCount" class="text-dark">0 Lines</strong>
                        </div>
                        <div class="calc-row">
                            <span class="text-muted">Total Base Qty:</span>
                            <strong id="summaryTotalQty" class="text-dark">0 Pcs</strong>
                        </div>
                        <div class="calc-row">
                            <span class="text-muted">Gross Subtotal:</span>
                            <strong id="summarySubtotal" class="font-weight-bold">Rs. 0.00</strong>
                        </div>

                        <div class="calc-row" id="discountPctRow" style="display:none !important;">
                            <span class="text-muted"><i class="fas fa-tag text-success mr-1"></i> Total Discount:</span>
                            <span>
                                <span id="summaryDiscountAmt" class="text-success font-weight-bold">Rs. 0.00</span>
                                <span id="summaryDiscountPct" class="badge badge-success ml-1">0.00%</span>
                            </span>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small font-weight-bold text-muted mb-1">Bill Discount (Rs.):</label>
                            <input type="number" step="0.01" name="discount_amount" id="billDiscount" class="form-control form-control-sm text-right font-weight-bold" placeholder="0.00" onfocus="this.select()" oninput="calculateBillTotals()">
                        </div>

                        <!-- Auto-calculated Item-wise GST Summary Row -->
                        <div class="calc-row py-1">
                            <span class="text-muted"><i class="fas fa-percent text-primary mr-1"></i> Total Items GST:</span>
                            <strong id="summaryTaxAmount" class="text-primary font-weight-bold">Rs. 0.00</strong>
                            <input type="hidden" name="tax_amount" id="taxAmount" value="0.00">
                        </div>

                        <div class="calc-row grand-total">
                            <span>Grand Total:</span>
                            <span id="summaryGrandTotal" class="text-primary font-weight-bold">Rs. 0.00</span>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small font-weight-bold text-success mb-1">Amount Paid (Rs.):</label>
                            <input type="number" step="0.01" name="paid_amount" id="paidAmount" class="form-control text-right font-weight-bold text-success" placeholder="0.00" onfocus="this.select()" oninput="calculateBillTotals()">
                        </div>

                        <div class="calc-row balance-due">
                            <span>Balance Due:</span>
                            <span id="summaryBalanceDue" class="text-danger font-weight-bold">Rs. 0.00</span>
                        </div>

                        <!-- Hidden calculation values for form submission -->
                        <input type="hidden" name="subtotal" id="hiddenSubtotal" value="0.00">
                        <input type="hidden" name="grand_total" id="hiddenGrandTotal" value="0.00">
                        <input type="hidden" name="balance_amount" id="hiddenBalanceAmount" value="0.00">
                    </div>

                    <div>
                        <button type="submit" name="save_purchase" class="btn btn-primary btn-block font-weight-bold py-2 shadow-sm">
                            <i class="fas fa-save mr-1"></i> Save Inward Purchase
                        </button>
                        <a href="purchases.php" class="btn btn-outline-secondary btn-block mt-2">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Modal: Quick Add Supplier -->
<div class="modal fade" id="modalQuickSupplier" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="quickSupplierForm">
                <div class="modal-header">
                    <h5 class="modal-title font-weight-bold text-primary">
                        <i class="fas fa-truck mr-2"></i> Register New Supplier
                    </h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div id="qsFeedback" class="alert alert-danger d-none py-2 small"></div>
                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Supplier / Distributor Name <span class="text-danger">*</span></label>
                        <input type="text" id="qsName" class="form-control font-weight-bold" placeholder="e.g. Ali Pharma Traders" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Company / Agency Name</label>
                        <input type="text" id="qsCompany" class="form-control" placeholder="e.g. Getz, GSK Distributor">
                    </div>
                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Phone / WhatsApp</label>
                        <input type="text" id="qsPhone" class="form-control" placeholder="0300-1234567">
                    </div>
                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Address / City</label>
                        <input type="text" id="qsAddress" class="form-control" placeholder="Medicine Market, Lahore">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnSubmitSupplier" class="btn btn-primary font-weight-bold">
                        <i class="fas fa-check mr-1"></i> Save Supplier
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Master lookup JSON & Live Search Scripts -->
<script>
let suppliersCatalog = <?= json_encode($suppliers) ?>;
let productsCatalog  = <?= json_encode($products) ?>;
let systemUnits      = <?= json_encode($units) ?>;
let rowCounter = 0;

// Helper: Get Unit Options HTML from System Units (managed in add_units.php)
function getUnitOptionsHtml(selectedUnit = '') {
    if (!systemUnits || systemUnits.length === 0) {
        return `<option value="Pack" ${selectedUnit === 'Pack' ? 'selected' : ''}>Pack</option>
                <option value="Box" ${selectedUnit === 'Box' ? 'selected' : ''}>Box</option>`;
    }
    return systemUnits.map(u => {
        const uName = u.name || '';
        const isSel = (selectedUnit && (uName.toLowerCase() === selectedUnit.toLowerCase() || (u.short_name && u.short_name.toLowerCase() === selectedUnit.toLowerCase()))) ? 'selected' : '';
        return `<option value="${escapeHtml(uName)}" ${isSel}>${escapeHtml(uName)}</option>`;
    }).join('');
}

// Helper: Escape HTML
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Helper: Highlight matching query letters
function highlightMatch(text, query) {
    if (!text) return '';
    if (!query) return escapeHtml(text);
    const escaped = escapeHtml(text);
    const qEscaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const regex = new RegExp(`(${qEscaped})`, 'gi');
    return escaped.replace(regex, '<mark class="highlight-match">$1</mark>');
}

// ----------------------------------------------------
// 1. SUPPLIER LIVE SEARCH FUNCTIONALITY
// ----------------------------------------------------
let supplierSearchTimer = null;
let currentSupplierIdx = -1;

function onSupplierSearchFocus() {
    const input = document.getElementById('supplierSearchInput');
    searchSuppliers(input.value.trim());
}

function onSupplierSearchInput() {
    const input = document.getElementById('supplierSearchInput');
    const q = input.value.trim();
    if (!q) {
        document.getElementById('supplierSelect').value = '';
        document.getElementById('supplierClearBtn').classList.add('d-none');
        document.getElementById('selectedSupplierBadge').classList.add('d-none');
    }
    searchSuppliers(q);
}

function searchSuppliers(query) {
    const dd = document.getElementById('supplierDropdown');
    currentSupplierIdx = -1;

    // A. Local instant filter
    const qLower = query.toLowerCase();
    const localMatches = suppliersCatalog.filter(s => {
        if (!query) return true;
        return (s.name && s.name.toLowerCase().includes(qLower)) ||
               (s.company_name && s.company_name.toLowerCase().includes(qLower)) ||
               (s.phone && s.phone.includes(qLower));
    }).slice(0, 20);

    renderSupplierList(localMatches, query);
    dd.classList.remove('d-none');

    // B. Live AJAX search to Database
    clearTimeout(supplierSearchTimer);
    supplierSearchTimer = setTimeout(() => {
        fetch('add_purchase.php?action=search_supplier&q=' + encodeURIComponent(query))
            .then(r => r.json())
            .then(data => {
                if (Array.isArray(data)) {
                    renderSupplierList(data, query);
                }
            })
            .catch(() => {});
    }, 200);
}

function renderSupplierList(items, query) {
    const list = document.getElementById('supplierResultsList');
    if (!items || items.length === 0) {
        list.innerHTML = `
            <div class="p-3 text-center text-muted small">
                <i class="fa-solid fa-exclamation-circle me-1 text-warning"></i>
                No suppliers found matching "<strong>${escapeHtml(query)}</strong>"
            </div>
        `;
        return;
    }

    list.innerHTML = items.map((s, idx) => {
        const bal = parseFloat(s.current_balance || 0);
        const nameHl = highlightMatch(s.name, query);
        const compHl = s.company_name ? highlightMatch(s.company_name, query) : '';
        const phoneHl = s.phone ? highlightMatch(s.phone, query) : '';

        return `
            <div class="search-dropdown-item supplier-item" 
                 data-idx="${idx}"
                 onclick='selectSupplierItem(${JSON.stringify(s).replace(/'/g, "&apos;")})'>
                <div class="d-flex justify-content-between align-items-center">
                    <strong class="text-dark">${nameHl}</strong>
                    <span class="badge ${bal > 0 ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary'}">
                        Payable: Rs. ${bal.toLocaleString('en-US', { minimumFractionDigits: 2 })}
                    </span>
                </div>
                <div class="small text-muted d-flex gap-3 mt-1">
                    ${compHl ? `<span><i class="fa-solid fa-industry me-1"></i>${compHl}</span>` : ''}
                    ${phoneHl ? `<span><i class="fa-solid fa-phone me-1"></i>${phoneHl}</span>` : ''}
                </div>
            </div>
        `;
    }).join('');
}

function selectSupplierItem(s) {
    document.getElementById('supplierSelect').value = s.id;
    const label = s.name + (s.company_name ? ' (' + s.company_name + ')' : '');
    document.getElementById('supplierSearchInput').value = label;
    document.getElementById('supplierClearBtn').classList.remove('d-none');
    
    const bal = parseFloat(s.current_balance || 0);
    document.getElementById('supBalDisplay').textContent = 'Rs. ' + bal.toLocaleString('en-US', { minimumFractionDigits: 2 });
    
    const badge = document.getElementById('selectedSupplierBadge');
    if (s.company_name) {
        badge.textContent = s.company_name;
        badge.classList.remove('d-none');
    } else {
        badge.classList.add('d-none');
    }

    document.getElementById('supplierDropdown').classList.add('d-none');
}

function clearSupplierSelection() {
    document.getElementById('supplierSelect').value = '';
    document.getElementById('supplierSearchInput').value = '';
    document.getElementById('supplierClearBtn').classList.add('d-none');
    document.getElementById('selectedSupplierBadge').classList.add('d-none');
    document.getElementById('supBalDisplay').textContent = 'Rs. 0.00';
    document.getElementById('supplierSearchInput').focus();
    searchSuppliers('');
}

function onSupplierSearchKeydown(e) {
    const dd = document.getElementById('supplierDropdown');
    const items = dd.querySelectorAll('.supplier-item');
    if (dd.classList.contains('d-none') || items.length === 0) {
        if (e.key === 'ArrowDown') {
            onSupplierSearchFocus();
        }
        return;
    }

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        currentSupplierIdx = Math.min(currentSupplierIdx + 1, items.length - 1);
        highlightActiveItem(items, currentSupplierIdx);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        currentSupplierIdx = Math.max(currentSupplierIdx - 1, 0);
        highlightActiveItem(items, currentSupplierIdx);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (currentSupplierIdx >= 0 && items[currentSupplierIdx]) {
            items[currentSupplierIdx].click();
        }
    } else if (e.key === 'Escape') {
        dd.classList.add('d-none');
    }
}

// ----------------------------------------------------
// 2. MEDICINE & BRAND LIVE SEARCH FUNCTIONALITY
// ----------------------------------------------------
const medSearchTimers = {};
const currentMedIdxs = {};

function onMedSearchFocus(rowId) {
    const input = document.getElementById('medSearch_' + rowId);
    searchProductsForRow(rowId, input.value.trim());
}

function onMedSearchInput(rowId) {
    const input = document.getElementById('medSearch_' + rowId);
    const q = input.value.trim();
    if (!q) {
        document.getElementById('productId_' + rowId).value = '';
        document.getElementById('medClearBtn_' + rowId).classList.add('d-none');
        document.getElementById('formulaBadge_' + rowId).classList.add('d-none');
    }
    searchProductsForRow(rowId, q);
}

function searchProductsForRow(rowId, query) {
    const dd = document.getElementById('medDropdown_' + rowId);
    if (!dd) return;
    currentMedIdxs[rowId] = -1;

    // A. Local instant filter
    const qLower = query.toLowerCase();
    const localMatches = productsCatalog.filter(p => {
        if (!query) return true;
        return (p.name && p.name.toLowerCase().includes(qLower)) ||
               (p.product_code && p.product_code.toLowerCase().includes(qLower)) ||
               (p.company_name && p.company_name.toLowerCase().includes(qLower)) ||
               (p.category_name && p.category_name.toLowerCase().includes(qLower));
    }).slice(0, 25);

    renderProductList(rowId, localMatches, query);
    dd.classList.remove('d-none');

    // B. Live AJAX search to Database
    clearTimeout(medSearchTimers[rowId]);
    medSearchTimers[rowId] = setTimeout(() => {
        fetch('add_purchase.php?action=search_product&q=' + encodeURIComponent(query))
            .then(r => r.json())
            .then(data => {
                if (Array.isArray(data)) {
                    renderProductList(rowId, data, query);
                }
            })
            .catch(() => {});
    }, 200);
}

function renderProductList(rowId, items, query) {
    const list = document.getElementById('medResultsList_' + rowId);
    if (!list) return;

    if (!items || items.length === 0) {
        list.innerHTML = `
            <div class="p-3 text-center text-muted small">
                <i class="fa-solid fa-pills me-1 text-warning"></i>
                No medicine or brand found matching "<strong>${escapeHtml(query)}</strong>"
            </div>
        `;
        return;
    }

    list.innerHTML = items.map((p, idx) => {
        const nameHl = highlightMatch(p.name, query);
        const compHl = p.company_name ? highlightMatch(p.company_name, query) : 'General';
        const codeHl = p.product_code ? highlightMatch(p.product_code, query) : '';
        const tp = parseFloat(p.trade_price || 0);
        const cost = parseFloat(p.purchase_price || 0);
        const stock = parseInt(p.current_stock || 0);

        return `
            <div class="search-dropdown-item med-item-${rowId}" 
                 data-idx="${idx}"
                 onclick='selectProductItem(${rowId}, ${JSON.stringify(p).replace(/'/g, "&apos;")})'>
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <strong class="text-dark">${nameHl}</strong>
                        <span class="badge bg-light text-primary border ms-1">${codeHl}</span>
                    </div>
                    <span class="badge bg-success-subtle text-success">
                        TP: Rs. ${tp.toFixed(2)}
                    </span>
                </div>
                <div class="small text-muted d-flex justify-content-between align-items-center mt-1">
                    <span><i class="fa-solid fa-industry me-1"></i>${compHl}</span>
                    <span class="text-secondary">Cost: <strong>Rs. ${cost.toFixed(2)}</strong> | Stock: ${stock}</span>
                </div>
            </div>
        `;
    }).join('');
}

function selectProductItem(rowId, p) {
    document.getElementById('productId_' + rowId).value = p.id;
    const label = p.name + (p.company_name ? ' (' + p.company_name + ')' : '');
    document.getElementById('medSearch_' + rowId).value = label;
    document.getElementById('medClearBtn_' + rowId).classList.remove('d-none');

    const rawCost  = parseFloat(p.purchase_price || 0);
    const baseTp   = parseFloat(p.trade_price || 0);
    // Auto-fulfill rate: use trade_price (official TP from Add/Edit Product) first so TP rate shows as given on Add Product
    const baseCost = (baseTp > 0) ? baseTp : (rawCost > 0 ? rawCost : 0);
    const pbox     = parseInt(p.packs_per_box || 1);

    // Store base reference rates & packaging conversion
    document.getElementById('baseCost_' + rowId).value = baseCost.toFixed(2);
    document.getElementById('baseTp_' + rowId).value   = baseTp.toFixed(2);
    document.getElementById('packsPerBox_' + rowId).value = pbox;
    // NOTE: do NOT seed row Discount % from products.discount_percent (that's the SALE discount on TP)
    document.getElementById('discPct_' + rowId).value = '0.00';

    // Auto set rate & TP (only if > 0)
    document.getElementById('cost_' + rowId).value = (baseCost > 0) ? baseCost.toFixed(2) : '';
    document.getElementById('tp_' + rowId).value   = (baseTp > 0) ? baseTp.toFixed(2) : '';

    // If product has default sale discount in catalog, populate it
    const defSaleDisc = parseFloat(product.discount_percent || 0);
    const saleDiscEl = document.getElementById('saleDiscPct_' + rowId);
    if (saleDiscEl) {
        saleDiscEl.value = (defSaleDisc > 0) ? defSaleDisc.toFixed(2) : '';
    }

    calculateRowTotal(rowId);
    updateRowSalePrice(rowId);
    document.getElementById('medDropdown_' + rowId).classList.add('d-none');
    setActiveRow(rowId);

    // Auto-focus quantity field for instant data entry and select content
    const qtyInput = document.getElementById('qty_' + rowId);
    if (qtyInput) {
        if (!qtyInput.value) qtyInput.value = '1';
        qtyInput.focus();
        qtyInput.select();
    }
}

function onCostChange(rowId) {
    calculateRowTotal(rowId);
    updateRowSalePrice(rowId);
}

function clearMedSelection(rowId) {
    document.getElementById('productId_' + rowId).value = '';
    document.getElementById('medSearch_' + rowId).value = '';
    document.getElementById('medClearBtn_' + rowId).classList.add('d-none');
    document.getElementById('baseCost_' + rowId).value = '0.00';
    document.getElementById('baseTp_' + rowId).value = '0.00';
    document.getElementById('cost_' + rowId).value = '';
    document.getElementById('tp_' + rowId).value = '';
    document.getElementById('qty_' + rowId).value = '';
    if (document.getElementById('bonusQty_' + rowId)) document.getElementById('bonusQty_' + rowId).value = '';
    document.getElementById('discPct_' + rowId).value = '';
    document.getElementById('discAmt_' + rowId).value = '0.00';
    if (document.getElementById('gstPct_' + rowId)) document.getElementById('gstPct_' + rowId).value = '';
    if (document.getElementById('taxAmt_' + rowId)) document.getElementById('taxAmt_' + rowId).value = '0.00';
    document.getElementById('rowTotal_' + rowId).value = '';
    if (document.getElementById('rowAllDiscPct_' + rowId)) document.getElementById('rowAllDiscPct_' + rowId).value = '0.00';
    if (document.getElementById('effectiveCostDisp_' + rowId)) document.getElementById('effectiveCostDisp_' + rowId).textContent = 'Cost: 0.00';
    if (document.getElementById('saleDiscPct_' + rowId)) document.getElementById('saleDiscPct_' + rowId).value = '';
    if (document.getElementById('salePriceDisp_' + rowId)) document.getElementById('salePriceDisp_' + rowId).textContent = 'Sale: Rs. 0.00';
    if (document.getElementById('salePrice_' + rowId)) document.getElementById('salePrice_' + rowId).value = '0.00';
    const discDisp = document.getElementById('discDisplay_' + rowId);
    if (discDisp) discDisp.innerHTML = '';
    const bValDisp = document.getElementById('bonusValDisp_' + rowId);
    if (bValDisp) bValDisp.innerHTML = '';
    const dAmtDisp = document.getElementById('discAmtDisp_' + rowId);
    if (dAmtDisp) dAmtDisp.innerHTML = '';
    const tAmtDisp = document.getElementById('taxAmtDisp_' + rowId);
    if (tAmtDisp) tAmtDisp.innerHTML = '';
    calculateRowTotal(rowId);

    document.getElementById('medSearch_' + rowId).focus();
    searchProductsForRow(rowId, '');
}

// ----------------------------------------------------
// ROW SELECTION & HIGHLIGHT
// ----------------------------------------------------
let activeRowId = null;

function setActiveRow(rowId) {
    rowId = parseInt(rowId);
    if (!rowId || !document.getElementById('row_' + rowId)) return;

    activeRowId = rowId;

    // Visual row highlight
    document.querySelectorAll('#itemsTableBody tr').forEach(r => {
        r.classList.remove('active-item-row');
    });
    const currRow = document.getElementById('row_' + rowId);
    if (currRow) currRow.classList.add('active-item-row');
}

function getRowEffectiveCost(rowId) {
    if (!rowId || !document.getElementById('row_' + rowId)) return 0;

    const costInput = document.getElementById('cost_' + rowId);
    const discInput = document.getElementById('discPct_' + rowId);
    const gstInput  = document.getElementById('gstPct_' + rowId);
    const qtyEl     = document.getElementById('qty_' + rowId);
    const bonusEl   = document.getElementById('bonusQty_' + rowId);

    if (!costInput) return 0;
    const rawRate = parseFloat(costInput.value) || 0;
    const discPct = parseFloat(discInput ? discInput.value : 0) || 0;
    const gstPct  = parseFloat(gstInput ? gstInput.value : 0) || 0;
    const qty     = parseFloat(qtyEl ? qtyEl.value : 0) || 0;
    const bonus   = parseFloat(bonusEl ? bonusEl.value : 0) || 0;
    const totalQty = qty + bonus;

    // 1. After Row Discount %
    const costAfterRowDisc = rawRate * (1 - (discPct / 100));

    // 2. Add GST % on row (increases effective landed unit cost)
    const costWithGst = costAfterRowDisc * (1 + (gstPct / 100));

    // 3. After Bill Discount (proportional to row total vs subtotal)
    let totalSubtotal = 0;
    document.querySelectorAll('#itemsTableBody tr').forEach(tr => {
        const rId = tr.id.replace('row_', '');
        const rTotal = parseFloat(document.getElementById('rowTotal_' + rId)?.value) || 0;
        totalSubtotal += rTotal;
    });

    const billDiscount = parseFloat(document.getElementById('billDiscount')?.value) || 0;

    let netEffectiveCost = costWithGst;
    if (totalSubtotal > 0 && billDiscount > 0) {
        const billDiscRatio = Math.min(1, billDiscount / totalSubtotal);
        netEffectiveCost = costWithGst * (1 - billDiscRatio);
    }

    // 4. Landed unit cost after spreading discount & GST over total received qty (purchased + free bonus)
    const landedUnitCost = totalQty > 0 ? (netEffectiveCost * qty) / totalQty : netEffectiveCost;

    return Math.max(0, landedUnitCost);
}

function onRowSaleDiscChange(rowId) {
    updateRowSalePrice(rowId);
}

function updateRowSalePrice(rowId) {
    if (!rowId || !document.getElementById('row_' + rowId)) return;
    const costInput     = document.getElementById('cost_' + rowId);
    const rawCost       = parseFloat(costInput ? costInput.value : 0) || 0;
    const baseTpInput   = document.getElementById('baseTp_' + rowId);
    let baseTp          = parseFloat(baseTpInput ? baseTpInput.value : 0) || 0;
    if (baseTp <= 0) baseTp = rawCost;

    const saleDiscInput = document.getElementById('saleDiscPct_' + rowId);
    const saleDiscPct   = parseFloat(saleDiscInput ? saleDiscInput.value : 0) || 0;

    // Selling price = BaseTP * (1 - Sale Discount % / 100)
    const calcSalePrice = Math.max(0, baseTp * (1 - (saleDiscPct / 100)));

    const tpInput        = document.getElementById('tp_' + rowId);
    const salePriceInput = document.getElementById('salePrice_' + rowId);
    if (tpInput)        tpInput.value        = calcSalePrice.toFixed(2);
    if (salePriceInput) salePriceInput.value = calcSalePrice.toFixed(2);

    const dispSale = document.getElementById('salePriceDisp_' + rowId);
    if (dispSale) {
        if (calcSalePrice > 0) {
            dispSale.innerHTML = `Sale: <strong class="text-primary">Rs. ${calcSalePrice.toFixed(2)}</strong>`;
        } else {
            dispSale.innerHTML = `Sale: <span class="text-muted">Rs. 0.00</span>`;
        }
    }
}

function onMedSearchKeydown(e, rowId) {
    const dd = document.getElementById('medDropdown_' + rowId);
    if (!dd) return;
    const items = dd.querySelectorAll('.med-item-' + rowId);
    if (dd.classList.contains('d-none') || items.length === 0) {
        if (e.key === 'ArrowDown') {
            onMedSearchFocus(rowId);
        }
        return;
    }

    let activeIdx = currentMedIdxs[rowId] ?? -1;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIdx = Math.min(activeIdx + 1, items.length - 1);
        currentMedIdxs[rowId] = activeIdx;
        highlightActiveItem(items, activeIdx);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIdx = Math.max(activeIdx - 1, 0);
        currentMedIdxs[rowId] = activeIdx;
        highlightActiveItem(items, activeIdx);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (activeIdx >= 0 && items[activeIdx]) {
            items[activeIdx].click();
        }
    } else if (e.key === 'Escape') {
        dd.classList.add('d-none');
    }
}

function highlightActiveItem(items, activeIdx) {
    items.forEach((it, idx) => {
        if (idx === activeIdx) {
            it.classList.add('active');
            it.scrollIntoView({ block: 'nearest' });
        } else {
            it.classList.remove('active');
        }
    });
}

// ----------------------------------------------------
// 3. TABLE ROW & BILL CALCULATION LOGIC
// ----------------------------------------------------
function addNewItemRow() {
    rowCounter++;
    const tbody = document.getElementById('itemsTableBody');
    const tr = document.createElement('tr');
    tr.id = 'row_' + rowCounter;
    tr.className = 'item-row';
    tr.setAttribute('onclick', `setActiveRow(${rowCounter})`);
    tr.setAttribute('onfocusin', `setActiveRow(${rowCounter})`);

    tr.innerHTML = `
        <td class="position-relative" style="min-width: 260px;">
            <div class="position-relative" id="medSearchContainer_${rowCounter}">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white text-muted"><i class="fas fa-search"></i></span>
                    </div>
                    <input type="text" 
                           id="medSearch_${rowCounter}" 
                           class="form-control font-weight-bold med-search-input" 
                           placeholder="Type to search medicine..." 
                           autocomplete="off"
                           onfocus="onMedSearchFocus(${rowCounter})"
                           oninput="onMedSearchInput(${rowCounter})"
                           onkeydown="onMedSearchKeydown(event, ${rowCounter})">
                    <div class="input-group-append">
                        <button type="button" class="btn btn-outline-secondary d-none" id="medClearBtn_${rowCounter}" onclick="clearMedSelection(${rowCounter})" title="Change medicine">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <input type="hidden" name="items[${rowCounter}][product_id]" id="productId_${rowCounter}" required value="">

                <!-- Live Medicine Dropdown -->
                <div id="medDropdown_${rowCounter}" class="search-dropdown-menu med-dropdown d-none">
                    <div id="medResultsList_${rowCounter}"></div>
                </div>
            </div>
            <input type="hidden" id="baseCost_${rowCounter}" value="0.00">
            <input type="hidden" id="baseTp_${rowCounter}" value="0.00">
            <input type="hidden" id="packsPerBox_${rowCounter}" value="1">
            <input type="hidden" id="totalQty_${rowCounter}" value="0">
            <input type="hidden" id="allTotalQty_${rowCounter}" value="0">
            <input type="hidden" name="items[${rowCounter}][batch_no]" value="DEFAULT">
            <input type="hidden" name="items[${rowCounter}][expiry_date]" value="<?= date('Y-m-d', strtotime('+2 years')) ?>">
        </td>

        <!-- 2. Quantity (Pcs) -->
        <td style="width: 75px;">
            <input type="number" min="1" name="items[${rowCounter}][quantity]" id="qty_${rowCounter}" class="form-control text-center font-weight-bold" placeholder="1" value="" onfocus="this.select()" required oninput="calculateRowTotal(${rowCounter})">
        </td>

        <!-- 3. Bonus Quantity (Pcs) -->
        <td style="width: 70px;">
            <input type="number" min="0" name="items[${rowCounter}][bonus_quantity]" id="bonusQty_${rowCounter}" class="form-control text-center font-weight-bold text-primary" placeholder="0" value="" onfocus="this.select()" oninput="calculateRowTotal(${rowCounter})">
            <div id="bonusValDisp_${rowCounter}" class="text-center mt-1" style="font-size: 0.68rem; line-height: 1.1; white-space: nowrap;"></div>
        </td>

        <!-- 3.5 Total Quantity (Pcs) -->
        <td style="width: 70px;">
            <input type="number" readonly id="totalQtyDisp_${rowCounter}" class="form-control text-center font-weight-bold col-all-total" placeholder="0" value="">
        </td>

        <!-- 4. Purchase Rate / TP -->
        <td style="width: 105px;">
            <input type="number" step="0.01" min="0" name="items[${rowCounter}][purchase_price]" id="cost_${rowCounter}" class="form-control text-right font-weight-bold" placeholder="0.00" value="" onfocus="this.select()" required oninput="onCostChange(${rowCounter})">
        </td>

        <!-- 5. Discount % -->
        <td style="width: 75px;">
            <input type="number" step="0.01" min="0" max="100" name="items[${rowCounter}][discount_percent]" id="discPct_${rowCounter}" class="form-control text-center font-weight-bold" placeholder="0.00" value="" onfocus="this.select()" oninput="calculateRowTotal(${rowCounter})">
            <input type="hidden" name="items[${rowCounter}][discount_amount]" id="discAmt_${rowCounter}" value="0.00">
            <div id="discAmtDisp_${rowCounter}" class="text-center mt-1" style="font-size: 0.68rem; line-height: 1.1; white-space: nowrap;"></div>
        </td>

        <!-- 5.5 GST % -->
        <td style="width: 75px;">
            <input type="number" step="0.01" min="0" max="100" name="items[${rowCounter}][tax_percent]" id="gstPct_${rowCounter}" class="form-control text-center font-weight-bold text-primary" placeholder="0.00" value="" onfocus="this.select()" oninput="calculateRowTotal(${rowCounter})">
            <input type="hidden" name="items[${rowCounter}][tax_amount]" id="taxAmt_${rowCounter}" value="0.00">
            <div id="taxAmtDisp_${rowCounter}" class="text-center mt-1" style="font-size: 0.68rem; line-height: 1.1; white-space: nowrap;"></div>
        </td>

        <!-- 6. Total (Rs.) -->
        <td style="width: 110px;">
            <input type="number" step="0.01" readonly name="items[${rowCounter}][total_price]" id="rowTotal_${rowCounter}" class="form-control text-right font-weight-bold col-row-total" placeholder="0.00" value="">
            <div id="discDisplay_${rowCounter}" class="text-right mt-1" style="font-size: 0.68rem; line-height: 1.1; white-space: nowrap;"></div>
        </td>

        <!-- 7. TP Disc. Recv % -->
        <td style="width: 95px;">
            <div class="input-group input-group-sm">
                <input type="text" readonly id="rowAllDiscPct_${rowCounter}" class="form-control text-center font-weight-bold text-success bg-light" placeholder="0.00" value="0.00">
                <div class="input-group-append">
                    <span class="input-group-text bg-light text-success font-weight-bold px-1" style="font-size:0.70rem;">%</span>
                </div>
            </div>
            <div id="effectiveCostDisp_${rowCounter}" class="text-center text-muted mt-1" style="font-size: 0.68rem;">Cost: 0.00</div>
        </td>

        <!-- 8. Sale Disc on TP % -->
        <td style="width: 115px;">
            <div class="input-group input-group-sm">
                <input type="number" step="0.01" min="0" max="100" name="items[${rowCounter}][sale_discount_percent]" id="saleDiscPct_${rowCounter}" class="form-control text-center font-weight-bold text-primary" placeholder="0.00" value="" onfocus="this.select()" oninput="onRowSaleDiscChange(${rowCounter})">
                <div class="input-group-append">
                    <span class="input-group-text bg-white text-primary font-weight-bold px-1" style="font-size:0.70rem;">%</span>
                </div>
            </div>
            <div id="salePriceDisp_${rowCounter}" class="text-center font-weight-bold text-primary mt-1" style="font-size: 0.70rem;">Sale: Rs. 0.00</div>
            <input type="hidden" name="items[${rowCounter}][trade_price]" id="tp_${rowCounter}" value="0.00">
            <input type="hidden" name="items[${rowCounter}][sale_price]" id="salePrice_${rowCounter}" value="0.00">
        </td>

        <!-- 9. Action -->
        <td class="text-center" style="width: 40px;">
            <button type="button" class="btn-remove-row" title="Remove" onclick="removeRow(${rowCounter})">
                <i class="fas fa-times"></i>
            </button>
        </td>
    `;

    tbody.appendChild(tr);
    calculateBillTotals();
    setActiveRow(rowCounter);
}

function calculateRowTotal(rowId) {
    const qtyEl     = document.getElementById('qty_' + rowId);
    const costEl    = document.getElementById('cost_' + rowId);
    const discPctEl = document.getElementById('discPct_' + rowId);
    const discAmtEl = document.getElementById('discAmt_' + rowId);
    const gstPctEl  = document.getElementById('gstPct_' + rowId);
    const taxAmtEl  = document.getElementById('taxAmt_' + rowId);
    const totalEl   = document.getElementById('rowTotal_' + rowId);
    const bonusEl   = document.getElementById('bonusQty_' + rowId);
    const totalQtyEl     = document.getElementById('totalQty_' + rowId);
    const allTotalQtyEl  = document.getElementById('allTotalQty_' + rowId);
    const totalQtyDispEl = document.getElementById('totalQtyDisp_' + rowId);

    if (!qtyEl || !costEl || !discPctEl || !totalEl) return;

    const qty     = parseFloat(qtyEl.value) || 0;
    const bonus   = parseFloat(bonusEl ? bonusEl.value : 0) || 0;
    const totalQty = qty + bonus;
    const cost    = parseFloat(costEl.value) || 0;
    const discPct = parseFloat(discPctEl.value) || 0;
    const gstPct  = parseFloat(gstPctEl ? gstPctEl.value : 0) || 0;

    // Total quantity display (purchased + bonus)
    if (totalQtyDispEl) totalQtyDispEl.value = (totalQty > 0) ? totalQty : '';
    if (totalQtyEl) totalQtyEl.value = qty;
    if (allTotalQtyEl) allTotalQtyEl.value = totalQty;

    // Financial Calculation (Gross -> Disc -> GST -> Net payable)
    const gross      = qty * cost;
    const discAmt    = parseFloat((gross * (discPct / 100)).toFixed(2));
    const netBefTax  = Math.max(0, parseFloat((gross - discAmt).toFixed(2)));
    const taxAmt     = parseFloat((netBefTax * (gstPct / 100)).toFixed(2));
    const netTotal   = Math.max(0, parseFloat((netBefTax + taxAmt).toFixed(2)));

    // Bonus value + combined discount benefit
    const bonusValue = parseFloat((bonus * cost).toFixed(2));
    const discountBenefit = parseFloat((discAmt + bonusValue).toFixed(2));
    const totalValue = totalQty * cost;
    const benefitPct = totalValue > 0 ? (discountBenefit / totalValue * 100) : 0;

    if (discAmtEl) discAmtEl.value = discAmt.toFixed(2);
    if (taxAmtEl) taxAmtEl.value = taxAmt.toFixed(2);
    totalEl.value = (netTotal > 0) ? netTotal.toFixed(2) : (qty > 0 && cost > 0 ? '0.00' : '');

    const bonusValDisp = document.getElementById('bonusValDisp_' + rowId);
    if (bonusValDisp) {
        bonusValDisp.innerHTML = (bonusValue > 0) ? `<span class="text-info font-weight-bold">+Rs. ${bonusValue.toFixed(2)}</span>` : '';
    }

    const discAmtDisp = document.getElementById('discAmtDisp_' + rowId);
    if (discAmtDisp) {
        discAmtDisp.innerHTML = (discAmt > 0) ? `<span class="text-success font-weight-bold">-Rs. ${discAmt.toFixed(2)}</span>` : '';
    }

    const taxAmtDisp = document.getElementById('taxAmtDisp_' + rowId);
    if (taxAmtDisp) {
        taxAmtDisp.innerHTML = (taxAmt > 0) ? `<span class="text-primary font-weight-bold">+Rs. ${taxAmt.toFixed(2)}</span>` : '';
    }

    const discDisplay = document.getElementById('discDisplay_' + rowId);
    if (discDisplay) {
        if (discountBenefit > 0) {
            discDisplay.innerHTML = `<span class="text-danger font-weight-bold" title="Total Benefit (Disc + Free Bonus Goods)">Benefit: ${discountBenefit.toFixed(2)} (${benefitPct.toFixed(1)}%)</span>`;
        } else {
            discDisplay.innerHTML = '';
        }
    }

    // Update row-level TP Discount Received (%)
    const rawCost = parseFloat(costEl.value) || 0;
    const effectiveCost = getRowEffectiveCost(rowId);
    const allDiscPct = (rawCost > 0 && rawCost >= effectiveCost) ? (((rawCost - effectiveCost) / rawCost) * 100) : 0;
    
    const rowAllDiscInput = document.getElementById('rowAllDiscPct_' + rowId);
    if (rowAllDiscInput) rowAllDiscInput.value = allDiscPct.toFixed(2);

    const effCostDisp = document.getElementById('effectiveCostDisp_' + rowId);
    if (effCostDisp) effCostDisp.textContent = 'Cost: ' + effectiveCost.toFixed(2);

    updateRowSalePrice(rowId);
    calculateBillTotals();
}

function removeRow(rowId) {
    const tr = document.getElementById('row_' + rowId);
    if (tr) {
        tr.remove();
        calculateBillTotals();
    }
}

function calculateBillTotals() {
    const rows = document.querySelectorAll('#itemsTableBody tr');
    let subtotal      = 0;
    let totalTax      = 0;
    let grossSum      = 0;  // Qty × Cost (before discount)
    let totalStockQty = 0;  // All physical inward packs
    let totalPurchQty = 0;  // Purchased base packs
    let totalBonusQty = 0;  // Bonus base packs

    rows.forEach(tr => {
        const rowId = tr.id.replace('row_', '');
        const totalQtyEl    = document.getElementById('totalQty_' + rowId);
        const allTotalQtyEl = document.getElementById('allTotalQty_' + rowId);
        const qtyEl         = document.getElementById('qty_' + rowId);
        const costEl        = document.getElementById('cost_' + rowId);
        const rTotalEl      = document.getElementById('rowTotal_' + rowId);
        const taxAmtEl      = document.getElementById('taxAmt_' + rowId);

        const baseQty    = parseInt(totalQtyEl ? totalQtyEl.value : 0) || 0;
        const allBaseQty = parseInt(allTotalQtyEl ? allTotalQtyEl.value : 0) || baseQty;
        const rawQty     = parseFloat(qtyEl ? qtyEl.value : 0) || 0;
        const cost       = parseFloat(costEl ? costEl.value : 0) || 0;
        const rTotal     = parseFloat(rTotalEl ? rTotalEl.value : 0) || 0;
        const taxAmt     = parseFloat(taxAmtEl ? taxAmtEl.value : 0) || 0;

        totalPurchQty += baseQty;
        totalStockQty += allBaseQty;
        subtotal      += rTotal;
        totalTax      += taxAmt;
        grossSum      += (rawQty * cost);
    });

    totalBonusQty = Math.max(0, totalStockQty - totalPurchQty);

    const billDiscount = parseFloat(document.getElementById('billDiscount')?.value) || 0;

    const taxAmtInput = document.getElementById('taxAmount');
    if (taxAmtInput) taxAmtInput.value = totalTax.toFixed(2);
    const summaryTax = document.getElementById('summaryTaxAmount');
    if (summaryTax) summaryTax.textContent = 'Rs. ' + totalTax.toFixed(2);

    const grandTotal = Math.max(0, subtotal - billDiscount);

    // Row-level discount + bill discount
    const netBeforeTaxSum = Math.max(0, subtotal - totalTax);
    const rowLevelDisc    = Math.max(0, grossSum - netBeforeTaxSum);
    const totalDiscAmt    = rowLevelDisc + billDiscount;
    const totalDiscPct    = (grossSum > 0) ? ((totalDiscAmt / grossSum) * 100) : 0;

    const discPctRow = document.getElementById('discountPctRow');
    if (totalDiscAmt > 0 && discPctRow) {
        discPctRow.style.removeProperty('display');
        discPctRow.style.display = 'flex';
        document.getElementById('summaryDiscountAmt').textContent = 'Rs. ' + totalDiscAmt.toFixed(2);
        document.getElementById('summaryDiscountPct').textContent = totalDiscPct.toFixed(2) + '%';
    } else if (discPctRow) {
        discPctRow.style.display = 'none';
    }

    const paidInput   = document.getElementById('paidAmount');
    const paymentType = document.getElementById('paymentType').value;

    let paidAmount = parseFloat(paidInput.value) || 0;
    if (paymentType === 'Cash' && paidAmount === 0 && grandTotal > 0) {
        paidAmount = grandTotal;
        paidInput.value = grandTotal.toFixed(2);
    }

    const balanceDue = Math.max(0, grandTotal - paidAmount);

    document.getElementById('summaryItemsCount').textContent  = rows.length + ' Lines';
    let qtySummaryText = totalStockQty + ' Pcs';
    if (totalBonusQty > 0) {
        qtySummaryText += ` (${totalPurchQty} + ${totalBonusQty} Bonus)`;
    }
    document.getElementById('summaryTotalQty').textContent    = qtySummaryText;
    document.getElementById('summarySubtotal').textContent    = 'Rs. ' + subtotal.toFixed(2);
    document.getElementById('summaryGrandTotal').textContent  = 'Rs. ' + grandTotal.toFixed(2);
    document.getElementById('summaryBalanceDue').textContent  = 'Rs. ' + balanceDue.toFixed(2);

    document.getElementById('hiddenSubtotal').value      = subtotal.toFixed(2);
    document.getElementById('hiddenGrandTotal').value    = grandTotal.toFixed(2);
    document.getElementById('hiddenBalanceAmount').value = balanceDue.toFixed(2);

    // Update TP Discount Received for all rows in case bill discount changed
    rows.forEach(tr => {
        const rId = tr.id.replace('row_', '');
        const cInput = document.getElementById('cost_' + rId);
        const rCost = parseFloat(cInput ? cInput.value : 0) || 0;
        const effCost = getRowEffectiveCost(rId);
        const allPct = (rCost > 0 && rCost >= effCost) ? (((rCost - effCost) / rCost) * 100) : 0;
        const radInput = document.getElementById('rowAllDiscPct_' + rId);
        if (radInput) radInput.value = allPct.toFixed(2);
        const ecDisp = document.getElementById('effectiveCostDisp_' + rId);
        if (ecDisp) ecDisp.textContent = 'Cost: ' + effCost.toFixed(2);
    });
}

function togglePaymentFields() {
    const type = document.getElementById('paymentType').value;
    const bankBox = document.getElementById('bankAccountBox');
    const paidInput = document.getElementById('paidAmount');
    const grandTotal = parseFloat(document.getElementById('hiddenGrandTotal').value) || 0;

    if (type === 'Bank') {
        if (bankBox) bankBox.classList.remove('d-none');
        if (!parseFloat(paidInput.value) && grandTotal > 0) paidInput.value = grandTotal.toFixed(2);
    } else if (type === 'Cash') {
        if (bankBox) bankBox.classList.add('d-none');
        if (!parseFloat(paidInput.value) && grandTotal > 0) paidInput.value = grandTotal.toFixed(2);
    } else {
        // Credit
        if (bankBox) bankBox.classList.add('d-none');
        paidInput.value = '';
    }
    calculateBillTotals();
}

// Global click listener to close open search dropdowns
document.addEventListener('click', function(e) {
    // Supplier dropdown
    if (!e.target.closest('#supplierSearchContainer')) {
        const sdd = document.getElementById('supplierDropdown');
        if (sdd) sdd.classList.add('d-none');
    }

    // Medicine dropdowns
    document.querySelectorAll('.med-dropdown').forEach(dd => {
        const container = dd.closest('[id^="medSearchContainer_"]');
        if (!container || !container.contains(e.target)) {
            dd.classList.add('d-none');
        }
    });
});

// DOM Ready initialization
document.addEventListener('DOMContentLoaded', function() {
    // Client-side form submission validation
    const purchaseForm = document.getElementById('purchaseForm');
    if (purchaseForm) {
        purchaseForm.addEventListener('submit', function(e) {
            const supId = parseInt(document.getElementById('supplierSelect')?.value || 0);
            if (!supId || supId <= 0) {
                e.preventDefault();
                alert('Please select a Supplier / Distributor first.');
                const supInput = document.getElementById('supplierSearchInput');
                if (supInput) { supInput.focus(); supInput.select(); }
                return false;
            }

            const rows = document.querySelectorAll('#itemsTableBody tr');
            let validItemsCount = 0;
            rows.forEach(tr => {
                const rId = tr.id.replace('row_', '');
                const pid = parseInt(document.getElementById('productId_' + rId)?.value || 0);
                const qty = parseFloat(document.getElementById('qty_' + rId)?.value || 0);
                if (pid > 0 && qty > 0) {
                    validItemsCount++;
                }
            });

            if (validItemsCount === 0) {
                e.preventDefault();
                alert('Please add at least one valid Product / Medicine item.');
                const firstMedInput = document.querySelector('.med-search-input');
                if (firstMedInput) firstMedInput.focus();
                return false;
            }
        });
    }

    // Add first item row automatically
    addNewItemRow();

    // Quick Supplier Modal form submit
    const quickSupplierForm = document.getElementById('quickSupplierForm');
    const qsFeedback = document.getElementById('qsFeedback');

    quickSupplierForm.addEventListener('submit', function(e) {
        e.preventDefault();
        qsFeedback.classList.add('d-none');

        const name    = document.getElementById('qsName').value.trim();
        const company = document.getElementById('qsCompany').value.trim();
        const phone   = document.getElementById('qsPhone').value.trim();
        const address = document.getElementById('qsAddress').value.trim();

        if (!name) return;

        const fd = new FormData();
        fd.append('supplier_name', name);
        fd.append('supplier_company', company);
        fd.append('supplier_phone', phone);
        fd.append('supplier_address', address);

        fetch('add_purchase.php?action=quick_supplier', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const newSup = {
                    id: data.id,
                    name: data.name,
                    company_name: data.company_name || company || '',
                    phone: phone,
                    current_balance: '0.00'
                };
                suppliersCatalog.unshift(newSup);
                selectSupplierItem(newSup);
                $('#modalQuickSupplier').modal('hide');
                quickSupplierForm.reset();
            } else {
                qsFeedback.textContent = data.message || 'Could not register supplier.';
                qsFeedback.classList.remove('d-none');
            }
        })
        .catch(err => {
            qsFeedback.textContent = 'Network or server error.';
            qsFeedback.classList.remove('d-none');
        });
    });

    // Auto-select text on focus so user can immediately overwrite values without backspacing
    document.addEventListener('focus', function(e) {
        if (e.target && (e.target.matches('input[type=number]') || e.target.matches('.med-search-input'))) {
            e.target.select();
        }
    }, true);
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php';
if (ob_get_level()) { ob_end_flush(); }
?>
