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

// AJAX endpoint to fetch all active batches for a single product (used for live batch selection)
if (isset($_GET['action']) && $_GET['action'] === 'get_product_batches') {
    header('Content-Type: application/json');
    $pid = intval($_GET['product_id'] ?? 0);
    $batches = [];
    if ($db_connected && $pdo && $pid > 0) {
        try {
            $b_stmt = $pdo->prepare("
                SELECT id, product_id, batch_no, expiry_date, trade_price, retail_price, purchase_price, current_stock 
                FROM product_batches 
                WHERE product_id = ? AND status = 'Active' AND current_stock > 0
                ORDER BY (current_stock > 0) DESC, expiry_date ASC, id ASC
            ");
            $b_stmt->execute([$pid]);
            $batches = $b_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
    }
    echo json_encode(['success' => true, 'batches' => $batches]);
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

// Determine TP Discount SQL expression (from products table if column exists, or from latest purchase_items)
$has_tp_disc_col = false;
if ($db_connected && $pdo) {
    try {
        $chk = $pdo->query("SHOW COLUMNS FROM products LIKE 'tp_discount'")->fetch();
        if ($chk) $has_tp_disc_col = true;
    } catch (Exception $e) {}
}
$tp_disc_sql = $has_tp_disc_col
    ? "COALESCE(NULLIF(p.tp_discount, 0), (SELECT pi.discount_percent FROM purchase_items pi WHERE pi.product_id = p.id AND pi.discount_percent IS NOT NULL ORDER BY pi.id DESC LIMIT 1), 0.00) as tp_discount"
    : "COALESCE((SELECT pi.discount_percent FROM purchase_items pi WHERE pi.product_id = p.id AND pi.discount_percent IS NOT NULL ORDER BY pi.id DESC LIMIT 1), 0.00) as tp_discount";

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
                        u.name as base_unit_name, u.short_name as base_unit_short,
                        {$tp_disc_sql}
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
                        u.name as base_unit_name, u.short_name as base_unit_short,
                        {$tp_disc_sql}
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

    // Server-side recalculation of every line and of the bill totals (same formula as the form:
    // gross = qty x rate, disc = gross x disc%, GST on (gross - disc), each step rounded to 2dp).
    // The totals posted by the browser were trusted before — bills were saved with total 0.
    if (is_array($items)) {
        $calc_subtotal = 0.0;
        $calc_tax      = 0.0;
        foreach ($items as $k => $itm) {
            $q_  = max(0, intval($itm['quantity'] ?? 0));
            $b_  = max(0, intval($itm['bonus_quantity'] ?? 0));
            $c_  = floatval($itm['purchase_price'] ?? 0);
            $dp_ = floatval($itm['discount_percent'] ?? 0);
            $tp_ = floatval($itm['tax_percent'] ?? 0);
            $g_  = $q_ * $c_;
            $da_ = round($g_ * ($dp_ / 100), 2);
            $nb_ = max(0, round($g_ - $da_, 2));
            $ta_ = round($nb_ * ($tp_ / 100), 2);
            $rt_ = max(0, round($nb_ + $ta_, 2));
            $items[$k]['discount_amount'] = $da_;
            $items[$k]['tax_amount']      = $ta_;
            $items[$k]['total_price']     = $rt_;
            if (intval($itm['product_id'] ?? 0) > 0 && ($q_ + $b_) > 0) {
                $calc_subtotal += $rt_;
                $calc_tax      += $ta_;
            }
        }
        $subtotal        = round($calc_subtotal, 2);
        $tax_amount      = round($calc_tax, 2);
        $discount_amount = max(0, min($discount_amount, $subtotal));
        $grand_total     = round(max(0, $subtotal - $discount_amount + $freight_charges), 2);
    }

    // Same supplier bill saved twice (double click / page refresh) creates a duplicate payable
    $dup_bill = false;
    if ($db_connected && $pdo && $supplier_id > 0 && $bill_no !== '') {
        $dup_st = $pdo->prepare("SELECT id FROM purchases WHERE supplier_id = ? AND bill_no = ? LIMIT 1");
        $dup_st->execute([$supplier_id, $bill_no]);
        $dup_bill = (bool)$dup_st->fetchColumn();
    }

    if ($dup_bill) {
        $message = "Bill # {$bill_no} is already saved for this supplier. Duplicate not saved.";
        $msg_type = "danger";
    } elseif ($supplier_id <= 0) {
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
                        retail_price, discount_percent, sale_discount_percent, discount_amount, tax_percent, tax_amount, total_price
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt_stock = $pdo->prepare("
                    UPDATE products 
                    SET current_stock = current_stock + ?, 
                        purchase_price = ?,
                        trade_price = CASE WHEN ? > 0 THEN ? ELSE trade_price END,
                        discount_percent = CASE WHEN ? = 1 THEN ? ELSE discount_percent END,
                        max_discount_percent = CASE WHEN ? = 1 THEN ? ELSE max_discount_percent END,
                        retail_price = CASE WHEN ? = 1 THEN ? ELSE retail_price END,
                        wholesale_price = CASE WHEN ? = 1 THEN ? ELSE wholesale_price END
                    WHERE id = ?
                ");

                // Check or insert batch
                $stmt_chk_batch = $pdo->prepare("
                    SELECT id FROM product_batches 
                    WHERE product_id = ? AND UPPER(TRIM(batch_no)) = UPPER(TRIM(?)) 
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
                $stmt_sync_batches = $pdo->prepare("
                    UPDATE product_batches 
                    SET retail_price = ROUND(trade_price * (1 - ? / 100), 2) 
                    WHERE product_id = ? AND trade_price > 0
                ");

                $impacted_pids = [];

                foreach ($items as $itm) {
                    $pid         = intval($itm['product_id'] ?? 0);
                    $batch_no    = strtoupper(trim($itm['batch_no'] ?? ''));
                    if ($batch_no === '' || $batch_no === 'DEFAULT') {
                        // No batch number entered: give this purchase its own batch instead of
                        // adding the stock into an old (possibly sold-out) shared 'DEFAULT' batch.
                        $batch_no = 'AUTO-' . $purchase_id;
                    }
                    $expiry_date = !empty($itm['expiry_date']) ? $itm['expiry_date'] : date('Y-m-d', strtotime('+2 years'));
                    
                    // Quantity and Bonus in Pieces (Pcs) directly
                    $raw_qty         = max(0, intval($itm['quantity'] ?? 0));
                    $raw_bonus       = max(0, intval($itm['bonus_quantity'] ?? 0));
                    $all_total_stock = $raw_qty + $raw_bonus; // Total physical stock inward (purchased + bonus)

                    // Rate entered by user (Purchase Cost / TP to supplier)
                    $p_cost         = floatval($itm['purchase_price'] ?? 0);
                    $form_tp        = floatval($itm['trade_price'] ?? 0);
                    $has_sale_disc  = isset($itm['sale_discount_percent']) && trim((string)$itm['sale_discount_percent']) !== '';
                    $sale_disc_pct  = $has_sale_disc ? floatval($itm['sale_discount_percent']) : 0;

                    // Determine official Base Trade Price (TP)
                    $official_tp = ($p_cost > 0) ? $p_cost : ($form_tp > 0 ? $form_tp : 0);
                    if ($official_tp <= 0 && $pid > 0) {
                        $stmt_find_tp = $pdo->prepare("SELECT trade_price FROM products WHERE id = ?");
                        $stmt_find_tp->execute([$pid]);
                        $official_tp = floatval($stmt_find_tp->fetchColumn() ?: 0);
                    }
                    if ($official_tp <= 0) {
                        $official_tp = $p_cost;
                    }

                    // Selling price after customer sale discount on TP
                    $calc_sale_price = ($sale_disc_pct > 0 && $official_tp > 0)
                        ? round(max(0, $official_tp * (1 - ($sale_disc_pct / 100))), 2)
                        : $official_tp;

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
                        $impacted_pids[$pid] = true;

                        // Insert Item Record
                        $stmt_item->execute([
                            $purchase_id, $pid, $batch_no, $expiry_date,
                            $raw_qty, $raw_qty, 'Pcs', $raw_bonus, $p_cost, $official_tp,
                            $calc_sale_price, $disc_pct, $sale_disc_pct, $disc_amt, $tax_pct, $tax_amt, $row_total
                        ]);

                        // Update Product Main Stock, TP, Rates & Sale Discount %:
                        $stmt_stock->execute([
                            $all_total_stock, 
                            $net_unit_cost, 
                            $official_tp, $official_tp,
                            $has_sale_disc ? 1 : 0, $sale_disc_pct, 
                            $has_sale_disc ? 1 : 0, $sale_disc_pct, 
                            $has_sale_disc ? 1 : 0, $calc_sale_price, 
                            $has_sale_disc ? 1 : 0, $calc_sale_price, 
                            $pid
                        ]);

                        // Handle Product Batch:
                        $stmt_chk_batch->execute([$pid, $batch_no]);
                        $existing_batch_id = $stmt_chk_batch->fetchColumn();

                        if ($existing_batch_id) {
                            $stmt_upd_batch->execute([
                                $all_total_stock, 
                                $expiry_date, 
                                $net_unit_cost, 
                                $official_tp, $official_tp, 
                                $calc_sale_price, $calc_sale_price, 
                                $existing_batch_id
                            ]);
                        } else {
                            $stmt_ins_batch->execute([
                                $pid, $batch_no, $expiry_date,
                                $net_unit_cost, $official_tp, $calc_sale_price,
                                $all_total_stock, $all_total_stock
                            ]);
                        }

                        // Synchronize all batches of this product with the new sale discount
                        if ($has_sale_disc) {
                            $stmt_sync_batches->execute([$sale_disc_pct, $pid]);
                        }
                    }
                }

                // NOTE: products.current_stock was already increased above by the received qty.
                // It is no longer overwritten with SUM(batch stock): where batch stock had drifted
                // (e.g. sale returns restored product stock only) that overwrite silently deleted units.

                // 3. Supplier Ledger Update & Sync
                syncSupplierLedger($pdo, $supplier_id);

                // 4. Record Payment in Supplier Payments & Cash/Bank Book if Paid Amount > 0
                if ($paid_amount > 0) {
                    // Unique per purchase (rand(100,999) could repeat and fail the UNIQUE voucher_no)
                    $voucher_no = "PV-" . date('Ymd') . "-P" . $purchase_id;
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
                            // recordBankOutflow() already deducts bank_accounts.current_balance (was double-counted)
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
                u.name as base_unit_name, u.short_name as base_unit_short,
                {$tp_disc_sql}
            FROM products p
            LEFT JOIN companies c ON p.company_id = c.id
            LEFT JOIN categories cat ON p.category_id = cat.id
            LEFT JOIN units u ON p.unit_id = u.id
            ORDER BY p.name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Preload active batches for each product
        try {
            $all_batches = $pdo->query("
                SELECT id, product_id, batch_no, expiry_date, trade_price, retail_price, purchase_price, current_stock 
                FROM product_batches 
                WHERE status = 'Active' AND current_stock > 0
                ORDER BY (current_stock > 0) DESC, expiry_date ASC, id ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            $batch_map = [];
            foreach ($all_batches as $b) {
                $batch_map[$b['product_id']][] = $b;
            }
            foreach ($products as &$pr) {
                $pr['batches'] = $batch_map[$pr['id']] ?? [];
            }
            unset($pr);
        } catch (Exception $e) {}

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
        min-width: 420px;
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
                            <th style="min-width: 175px; width: 185px;" class="text-center">Batch #</th>
                            <th style="width: 130px;" class="text-center">Expiry Date</th>
                            <th style="width: 75px;" class="text-center">Qty</th>
                            <th style="width: 70px;" class="text-center">Bonus</th>
                            <th style="width: 70px;" class="text-center">Total Qty</th>
                            <th style="width: 105px;" class="text-right">TP Rate (Rs.)</th>
                            <th style="width: 80px;" class="text-center text-success">TP Disc %</th>
                            <th style="width: 75px;" class="text-center">GST %</th>
                            <th style="width: 110px;" class="text-right">Total (Rs.)</th>
                            <th style="width: 95px;" class="text-center text-success">Total Benefit %</th>
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
let latestSupplierResults = [];

function onSupplierSearchFocus() {
    const input = document.getElementById('supplierSearchInput');
    searchSuppliers(input ? input.value.trim() : '');
}

function onSupplierSearchInput() {
    const input = document.getElementById('supplierSearchInput');
    const q = input ? input.value.trim() : '';
    if (!q) {
        document.getElementById('supplierSelect').value = '';
        document.getElementById('supplierClearBtn').classList.add('d-none');
        document.getElementById('selectedSupplierBadge').classList.add('d-none');
    }
    searchSuppliers(q);
}

function searchSuppliers(query) {
    const dd = document.getElementById('supplierDropdown');
    if (!dd) return;
    currentSupplierIdx = -1;

    const qTrim = (query || '').trim();
    const qLower = qTrim.toLowerCase();

    // Instant local filter from catalog
    const localMatches = (suppliersCatalog || []).filter(s => {
        if (!qTrim) return true;
        const name = (s.name || '').toLowerCase();
        const comp = (s.company_name || '').toLowerCase();
        const phone = (s.phone || '');
        return name.includes(qLower) || comp.includes(qLower) || phone.includes(qLower);
    }).slice(0, 25);

    latestSupplierResults = localMatches;
    renderSupplierList(localMatches, qTrim);
    dd.classList.remove('d-none');

    // Live AJAX search to Database
    clearTimeout(supplierSearchTimer);
    supplierSearchTimer = setTimeout(() => {
        fetch('add_purchase.php?action=search_supplier&q=' + encodeURIComponent(qTrim))
            .then(r => r.json())
            .then(data => {
                if (Array.isArray(data) && data.length > 0) {
                    latestSupplierResults = data;
                    renderSupplierList(data, qTrim);
                }
            })
            .catch(() => {});
    }, 200);
}

function renderSupplierList(items, query) {
    const list = document.getElementById('supplierResultsList');
    if (!list) return;

    if (!items || items.length === 0) {
        list.innerHTML = `
            <div class="p-3 text-center text-muted small">
                <i class="fas fa-exclamation-circle mr-1 text-warning"></i>
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
                 onclick="selectSupplierById(${s.id})">
                <div class="d-flex justify-content-between align-items-center">
                    <strong class="text-dark">${nameHl}</strong>
                    <span class="badge ${bal > 0 ? 'badge-danger' : 'badge-secondary'}">
                        Payable: Rs. ${bal.toLocaleString('en-US', { minimumFractionDigits: 2 })}
                    </span>
                </div>
                <div class="small text-muted d-flex justify-content-start align-items-center mt-1">
                    ${compHl ? `<span class="mr-3"><i class="fas fa-industry mr-1 text-muted"></i>${compHl}</span>` : ''}
                    ${phoneHl ? `<span><i class="fas fa-phone mr-1 text-muted"></i>${phoneHl}</span>` : ''}
                </div>
            </div>
        `;
    }).join('');
}

function selectSupplierById(id) {
    const s = (latestSupplierResults && latestSupplierResults.find(x => x.id == id)) ||
              (suppliersCatalog && suppliersCatalog.find(x => x.id == id));
    if (s) {
        selectSupplierItem(s);
    }
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
    const items = dd ? dd.querySelectorAll('.supplier-item') : [];
    if (!dd || dd.classList.contains('d-none') || items.length === 0) {
        if (e.key === 'ArrowDown') {
            onSupplierSearchFocus();
        }
        return;
    }

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        currentSupplierIdx = Math.min(currentSupplierIdx + 1, items.length - 1);
        highlightSupplierItem(items);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        currentSupplierIdx = Math.max(currentSupplierIdx - 1, 0);
        highlightSupplierItem(items);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (currentSupplierIdx >= 0 && items[currentSupplierIdx]) {
            items[currentSupplierIdx].click();
        }
    } else if (e.key === 'Escape') {
        dd.classList.add('d-none');
    }
}

function highlightSupplierItem(items) {
    items.forEach((it, idx) => {
        if (idx === currentSupplierIdx) {
            it.classList.add('active');
            it.scrollIntoView({ block: 'nearest' });
        } else {
            it.classList.remove('active');
        }
    });
}

// ----------------------------------------------------
// 2. MEDICINE LIVE SEARCH FUNCTIONALITY
// ----------------------------------------------------
let medSearchTimers = {};
let currentMedIndices = {};
let latestProductResults = {};

function onMedSearchFocus(rowId) {
    const input = document.getElementById('medSearch_' + rowId);
    searchProducts(rowId, input ? input.value.trim() : '');
}

function onMedSearchInput(rowId) {
    const input = document.getElementById('medSearch_' + rowId);
    const q = input ? input.value.trim() : '';
    if (!q) {
        clearMedSelection(rowId);
    }
    searchProducts(rowId, q);
}

function searchProducts(rowId, query) {
    const dd = document.getElementById('medDropdown_' + rowId);
    if (!dd) return;
    currentMedIndices[rowId] = -1;

    const qTrim = (query || '').trim();
    const qLower = qTrim.toLowerCase();

    // Instant local filter from catalog
    const localMatches = (productsCatalog || []).filter(p => {
        if (!qTrim) return true;
        const name = (p.name || '').toLowerCase();
        const comp = (p.company_name || '').toLowerCase();
        const code = (p.product_code || '').toLowerCase();
        return name.includes(qLower) || comp.includes(qLower) || code.includes(qLower);
    }).slice(0, 25);

    renderProductList(rowId, localMatches, qTrim);
    dd.classList.remove('d-none');

    // Live AJAX search to Database
    clearTimeout(medSearchTimers[rowId]);
    medSearchTimers[rowId] = setTimeout(() => {
        fetch('add_purchase.php?action=search_med&q=' + encodeURIComponent(qTrim))
            .then(r => r.json())
            .then(data => {
                if (Array.isArray(data) && data.length > 0) {
                    renderProductList(rowId, data, qTrim);
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
                <i class="fas fa-exclamation-circle mr-1 text-warning"></i>
                No medicines found matching "<strong>${escapeHtml(query)}</strong>"
            </div>
        `;
        return;
    }

    latestProductResults[rowId] = items;

    list.innerHTML = items.map((p, idx) => {
        const nameHl = highlightMatch(p.name, query);
        const compHl = p.company_name ? highlightMatch(p.company_name, query) : 'General';
        const codeHl = p.product_code ? highlightMatch(p.product_code, query) : '';
        const tp = parseFloat(p.trade_price || 0);
        const tpDisc = parseFloat(p.tp_discount || 0);
        const saleDisc = parseFloat(p.discount_percent || 0);
        const cost = parseFloat(p.purchase_price || 0);
        const stock = parseInt(p.current_stock || 0);
        const bCount = (p.batches && Array.isArray(p.batches)) ? p.batches.length : 0;

        return `
            <div class="search-dropdown-item med-item-${rowId}" 
                 data-idx="${idx}"
                 onclick="selectProductById(${rowId}, ${p.id})">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <strong class="text-dark">${nameHl}</strong>
                        <span class="badge badge-light border text-primary ml-1">${codeHl}</span>
                        ${bCount > 0 ? `<span class="badge badge-info ml-1" style="font-size:0.68rem;">${bCount} ${bCount === 1 ? 'Batch' : 'Batches'}</span>` : ''}
                    </div>
                    <div class="text-right">
                        <span class="badge badge-light text-success font-monospace" style="font-size: 0.78rem;">
                            TP: Rs. ${tp.toFixed(2)}
                        </span>
                        <div class="d-flex justify-content-end align-items-center mt-1 flex-wrap" style="gap: 4px;">
                            <span class="badge ${tpDisc > 0 ? 'badge-success' : 'badge-light text-muted border'}" style="font-size: 0.70rem;" title="Supplier TP Discount %">
                                <i class="fas fa-tag mr-1"></i>TP Disc: ${tpDisc > 0 ? tpDisc.toFixed(1) + '%' : '0%'}
                            </span>
                            ${saleDisc > 0 ? `
                                <span class="badge badge-light text-primary border" style="font-size: 0.70rem;" title="Customer Sale Discount on TP">
                                    Sale Disc: ${saleDisc.toFixed(1)}%
                                </span>
                            ` : ''}
                        </div>
                    </div>
                </div>
                <div class="small text-muted d-flex justify-content-between align-items-center mt-1">
                    <span><i class="fas fa-industry mr-1 text-muted"></i>${compHl}</span>
                    <span class="text-secondary">Cost: <strong class="text-dark">Rs. ${cost.toFixed(2)}</strong> | Stock: <strong class="${stock > 0 ? 'text-dark' : 'text-danger'}">${stock}</strong></span>
                </div>
            </div>
        `;
    }).join('');
}

function selectProductById(rowId, pid) {
    const list = latestProductResults[rowId] || productsCatalog;
    const p = list ? list.find(x => x.id == pid) : null;
    if (p) {
        selectProductItem(rowId, p);
    }
}

function selectProductItem(rowId, p) {
    document.getElementById('productId_' + rowId).value = p.id;
    const label = p.name + (p.company_name ? ' (' + p.company_name + ')' : '');
    document.getElementById('medSearch_' + rowId).value = label;
    document.getElementById('medClearBtn_' + rowId).classList.remove('d-none');

    const rawCost  = parseFloat(p.purchase_price || 0);
    const baseTp   = parseFloat(p.trade_price || 0);
    const baseCost = (baseTp > 0) ? baseTp : (rawCost > 0 ? rawCost : 0);
    const pbox     = parseInt(p.packs_per_box || 1);

    document.getElementById('baseCost_' + rowId).value = baseCost.toFixed(2);
    document.getElementById('baseTp_' + rowId).value   = baseTp.toFixed(2);
    document.getElementById('packsPerBox_' + rowId).value = pbox;
    
    // Auto set TP Discount % from product's recorded TP discount (if available)
    const defTpDisc = parseFloat(p.tp_discount || 0);
    const discInput = document.getElementById('discPct_' + rowId);
    if (discInput) {
        discInput.value = (defTpDisc > 0) ? defTpDisc.toFixed(2) : '0.00';
    }

    // Auto set rate & TP (only if > 0)
    document.getElementById('cost_' + rowId).value = (baseCost > 0) ? baseCost.toFixed(2) : '';
    document.getElementById('tp_' + rowId).value   = (baseTp > 0) ? baseTp.toFixed(2) : '';

    // If product has default sale discount in catalog, populate it
    const defSaleDisc = parseFloat(p.discount_percent || 0);
    const saleDiscEl = document.getElementById('saleDiscPct_' + rowId);
    if (saleDiscEl) {
        saleDiscEl.value = (defSaleDisc > 0) ? defSaleDisc.toFixed(2) : '0.00';
    }

    // Auto-fill Qty to 1 if empty or 0
    const qtyInput = document.getElementById('qty_' + rowId);
    if (qtyInput && (!qtyInput.value || parseFloat(qtyInput.value) <= 0)) {
        qtyInput.value = '1';
    }

    // Populate Batch selection & details
    if (p.batches && Array.isArray(p.batches) && p.batches.length > 0) {
        populateProductBatchOptions(rowId, p.batches);
    } else {
        fetchBatchOptions(rowId, p.id);
    }

    calculateRowTotal(rowId);
    updateRowSalePrice(rowId);

    clearTimeout(medSearchTimers[rowId]);
    const dd = document.getElementById('medDropdown_' + rowId);
    if (dd) {
        dd.classList.add('d-none');
        const list = document.getElementById('medResultsList_' + rowId);
        if (list) list.innerHTML = '';
    }
    setActiveRow(rowId);

    // Show "+ Add Batch" quick button
    const metaBox = document.getElementById('productMetaBox_' + rowId);
    if (metaBox) metaBox.classList.remove('d-none');

    // Auto-focus Batch No input so user can type batch number directly or select
    const batchInput = document.getElementById('batchNo_' + rowId);
    if (batchInput) {
        batchInput.focus();
        batchInput.select();
    }
}

// ----------------------------------------------------
// BATCH MANAGEMENT & SELECTION FUNCTIONS
// ----------------------------------------------------
const rowBatchesMap = {};

function populateProductBatchOptions(rowId, batches) {
    rowBatchesMap[rowId] = Array.isArray(batches) ? batches : [];
    const menu = document.getElementById('batchMenu_' + rowId);
    const batchInput = document.getElementById('batchNo_' + rowId);
    const metaDisp = document.getElementById('batchMetaDisp_' + rowId);
    if (!menu) return;

    if (!batches || batches.length === 0) {
        menu.innerHTML = `
            <div class="px-3 py-2 text-muted small border-bottom bg-light">
                <i class="fas fa-boxes mr-1"></i>No existing batches
            </div>
            <a class="dropdown-item py-2 text-primary font-weight-bold" href="javascript:void(0)" onclick="createNewBatchPrompt(${rowId})">
                <i class="fas fa-plus-circle mr-1"></i>+ Enter New Batch
            </a>
        `;
        if (metaDisp) metaDisp.innerHTML = '<span class="badge badge-light border text-muted">New Batch</span>';
        if (batchInput && !batchInput.value.trim()) {
            batchInput.value = '';
            batchInput.placeholder = 'TYPE NEW BATCH #';
            batchInput.focus();
        }
        return;
    }

    let itemsHtml = `
        <div class="px-3 py-2 text-muted small border-bottom bg-light d-flex justify-content-between align-items-center">
            <span><i class="fas fa-layer-group mr-1 text-primary"></i><strong>${batches.length}</strong> ${batches.length === 1 ? 'Batch' : 'Batches'} Available</span>
            <span class="badge badge-info" style="font-size:0.68rem;">Select or Type</span>
        </div>
        <a class="dropdown-item py-2 text-success font-weight-bold border-bottom" href="javascript:void(0)" onclick="createNewBatchPrompt(${rowId})">
            <i class="fas fa-plus-circle mr-1"></i>+ Add New Batch
        </a>
    `;

    batches.forEach(b => {
        const bNo = b.batch_no || 'DEFAULT';
        const exp = b.expiry_date ? b.expiry_date : 'No Expiry';
        const stk = parseInt(b.current_stock || 0);
        itemsHtml += `
            <a class="dropdown-item py-2 border-bottom" href="javascript:void(0)" onclick="applyBatchSelectionById(${rowId}, ${b.id})">
                <div class="d-flex justify-content-between align-items-center">
                    <strong class="font-monospace text-dark">${escapeHtml(bNo)}</strong>
                    <span class="badge ${stk > 0 ? 'badge-success' : 'badge-secondary'}" style="font-size:0.70rem;">Stock: ${stk}</span>
                </div>
                <div class="small text-muted d-flex justify-content-between mt-1">
                    <span>Exp: ${exp}</span>
                    <span>TP: Rs. ${parseFloat(b.trade_price || 0).toFixed(2)}</span>
                </div>
            </a>
        `;
    });

    menu.innerHTML = itemsHtml;

    // Auto-select the first / latest batch if batchInput is empty
    if (batchInput && !batchInput.value.trim() && batches.length > 0) {
        applyBatchSelection(rowId, batches[0]);
    }
}

function fetchBatchOptions(rowId, productId) {
    if (!productId) return;
    const menu = document.getElementById('batchMenu_' + rowId);
    if (menu) {
        menu.innerHTML = `
            <div class="p-3 text-center text-muted small">
                <span class="spinner-border spinner-border-sm mr-1 text-primary"></span> Loading batches...
            </div>
        `;
    }

    fetch('add_purchase.php?action=get_product_batches&product_id=' + encodeURIComponent(productId))
        .then(r => r.json())
        .then(data => {
            const batches = (data && data.success && Array.isArray(data.batches)) ? data.batches : [];
            populateProductBatchOptions(rowId, batches);
        })
        .catch(() => {
            populateProductBatchOptions(rowId, []);
        });
}

function applyBatchSelectionById(rowId, batchId) {
    const batches = rowBatchesMap[rowId] || [];
    const b = batches.find(x => x.id == batchId);
    if (b) {
        applyBatchSelection(rowId, b);
    }
}

function applyBatchSelection(rowId, batch) {
    const batchInput = document.getElementById('batchNo_' + rowId);
    const expInput = document.getElementById('expiry_' + rowId);
    const costInput = document.getElementById('cost_' + rowId);
    const tpInput = document.getElementById('tp_' + rowId);
    const metaDisp = document.getElementById('batchMetaDisp_' + rowId);

    if (batchInput && batch) {
        batchInput.value = (batch.batch_no || '').toUpperCase();
    }
    if (expInput && batch && batch.expiry_date && batch.expiry_date !== '0000-00-00') {
        expInput.value = batch.expiry_date;
    }
    if (batch) {
        const bCost = parseFloat(batch.purchase_price || 0);
        const bTp = parseFloat(batch.trade_price || 0);
        if (bTp > 0 && tpInput) tpInput.value = bTp.toFixed(2);
        if (bCost > 0 && costInput && (!costInput.value || parseFloat(costInput.value) <= 0)) {
            costInput.value = bCost.toFixed(2);
        }
    }
    if (metaDisp && batch) {
        const stk = parseInt(batch.current_stock || 0);
        metaDisp.innerHTML = `<span class="badge badge-light border text-primary">Existing (Stock: ${stk})</span>`;
    }

    calculateRowTotal(rowId);
    updateRowSalePrice(rowId);
}

function createNewBatchPrompt(rowId) {
    const batchInput = document.getElementById('batchNo_' + rowId);
    const expInput = document.getElementById('expiry_' + rowId);
    const metaDisp = document.getElementById('batchMetaDisp_' + rowId);
    if (batchInput) {
        batchInput.value = '';
        batchInput.placeholder = 'TYPE NEW BATCH #';
        batchInput.focus();
    }
    if (expInput) {
        const d = new Date();
        d.setFullYear(d.getFullYear() + 2);
        expInput.value = d.toISOString().split('T')[0];
    }
    if (metaDisp) {
        metaDisp.innerHTML = '<span class="badge badge-success text-white">+ New Batch (Type Above)</span>';
    }
}

function onBatchManualInput(rowId) {
    const batchInput = document.getElementById('batchNo_' + rowId);
    const metaDisp = document.getElementById('batchMetaDisp_' + rowId);
    if (!batchInput) return;
    const val = batchInput.value.trim().toUpperCase();
    batchInput.value = val;

    const batches = rowBatchesMap[rowId] || [];
    const matched = batches.find(b => (b.batch_no || '').toUpperCase() === val);
    if (matched) {
        const expInput = document.getElementById('expiry_' + rowId);
        if (expInput && matched.expiry_date && matched.expiry_date !== '0000-00-00') {
            expInput.value = matched.expiry_date;
        }
        if (metaDisp) {
            metaDisp.innerHTML = `<span class="badge badge-light border text-primary">Existing (Stock: ${parseInt(matched.current_stock || 0)})</span>`;
        }
    } else {
        if (metaDisp) {
            metaDisp.innerHTML = val ? '<span class="badge badge-success text-white">+ New Batch</span>' : '';
        }
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
    const metaBox = document.getElementById('productMetaBox_' + rowId);
    if (metaBox) metaBox.classList.add('d-none');
    const batchInput = document.getElementById('batchNo_' + rowId);
    if (batchInput) batchInput.value = '';
    const metaDisp = document.getElementById('batchMetaDisp_' + rowId);
    if (metaDisp) metaDisp.innerHTML = '';
    const bMenu = document.getElementById('batchMenu_' + rowId);
    if (bMenu) {
        bMenu.innerHTML = `<div class="p-2 text-muted small text-center"><i class="fas fa-info-circle mr-1"></i>Select medicine first</div>`;
    }
    rowBatchesMap[rowId] = [];
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
    calculateBillTotals();
}

function onMedSearchKeydown(e, rowId) {
    const dd = document.getElementById('medDropdown_' + rowId);
    const items = dd ? dd.querySelectorAll('.med-item-' + rowId) : [];
    if (!dd || dd.classList.contains('d-none') || items.length === 0) {
        if (e.key === 'ArrowDown') {
            onMedSearchFocus(rowId);
        }
        return;
    }

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        currentMedIndices[rowId] = Math.min(currentMedIndices[rowId] + 1, items.length - 1);
        highlightMedItem(items, rowId);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        currentMedIndices[rowId] = Math.max(currentMedIndices[rowId] - 1, 0);
        highlightMedItem(items, rowId);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (currentMedIndices[rowId] >= 0 && items[currentMedIndices[rowId]]) {
            items[currentMedIndices[rowId]].click();
        }
    } else if (e.key === 'Escape') {
        dd.classList.add('d-none');
    }
}

function highlightMedItem(items, rowId) {
    items.forEach((it, idx) => {
        if (idx === currentMedIndices[rowId]) {
            it.classList.add('active');
            it.scrollIntoView({ block: 'nearest' });
        } else {
            it.classList.remove('active');
        }
    });
}
// ----------------------------------------------------
// ROW SELECTION & ACTIVE HIGHLIGHT
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

// ----------------------------------------------------
// 3. TABLE ROW & BILL CALCULATION LOGIC
// ----------------------------------------------------
function addNewItemRow(prefilledData = null) {
    rowCounter++;
    const tbody = document.getElementById('itemsTableBody');
    const tr = document.createElement('tr');
    tr.id = 'row_' + rowCounter;
    tr.className = 'item-row';
    tr.setAttribute('onclick', `setActiveRow(${rowCounter})`);
    tr.setAttribute('onfocusin', `setActiveRow(${rowCounter})`);

    const d = new Date();
    d.setFullYear(d.getFullYear() + 2);
    const defaultExpiry = d.toISOString().split('T')[0];

    const pId = prefilledData ? (prefilledData.product_id || '') : '';
    const medName = prefilledData ? (prefilledData.med_name || '') : '';
    const baseCost = prefilledData ? (prefilledData.base_cost || '0.00') : '0.00';
    const baseTp = prefilledData ? (prefilledData.base_tp || '0.00') : '0.00';
    const packsPerBox = prefilledData ? (prefilledData.packs_per_box || 1) : 1;
    const cost = prefilledData ? (prefilledData.cost || '') : '';
    const tp = prefilledData ? (prefilledData.tp || '') : '';
    const discPct = prefilledData ? (prefilledData.disc_pct || '') : '';
    const gstPct = prefilledData ? (prefilledData.gst_pct || '') : '';
    const saleDiscPct = prefilledData ? (prefilledData.sale_disc_pct || '') : '';
    const salePrice = prefilledData ? (prefilledData.sale_price || '') : '';
    const batchNo = prefilledData ? (prefilledData.batch_no || '') : '';
    const expiryDate = prefilledData ? (prefilledData.expiry_date || defaultExpiry) : defaultExpiry;

    tr.innerHTML = `
        <!-- 1. Medicine / Product Search -->
        <td class="position-relative" style="min-width: 240px;">
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
                           value="${medName}"
                           onfocus="onMedSearchFocus(${rowCounter})"
                           oninput="onMedSearchInput(${rowCounter})"
                           onkeydown="onMedSearchKeydown(event, ${rowCounter})">
                    <div class="input-group-append">
                        <button type="button" class="btn btn-outline-secondary ${pId ? '' : 'd-none'}" id="medClearBtn_${rowCounter}" onclick="clearMedSelection(${rowCounter})" title="Change medicine">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <input type="hidden" name="items[${rowCounter}][product_id]" id="productId_${rowCounter}" required value="${pId}">

                <!-- Live Medicine Dropdown -->
                <div id="medDropdown_${rowCounter}" class="search-dropdown-menu med-dropdown d-none">
                    <div id="medResultsList_${rowCounter}"></div>
                </div>

                <!-- Quick Action: Add Another Batch for this Product -->
                <div class="d-flex align-items-center justify-content-between mt-1 ${pId ? '' : 'd-none'}" id="productMetaBox_${rowCounter}">
                    <span class="badge badge-light border text-primary small py-0 px-1 font-weight-bold">
                        <i class="fas fa-check-circle text-success mr-1"></i>Selected
                    </span>
                    <button type="button" 
                            class="btn btn-xs btn-outline-success font-weight-bold py-0 px-2" 
                            onclick="addAnotherBatchForProduct(${rowCounter})" 
                            title="Add another batch for this medicine">
                        <i class="fas fa-plus-circle mr-1"></i>+ Add Batch
                    </button>
                </div>
            </div>
            <input type="hidden" id="baseCost_${rowCounter}" value="${baseCost}">
            <input type="hidden" id="baseTp_${rowCounter}" value="${baseTp}">
            <input type="hidden" id="packsPerBox_${rowCounter}" value="${packsPerBox}">
            <input type="hidden" id="totalQty_${rowCounter}" value="0">
            <input type="hidden" id="allTotalQty_${rowCounter}" value="0">
        </td>

        <!-- 1.5 Batch # (Select + Editable / Typeable) -->
        <td style="min-width: 175px; width: 185px;">
            <div class="input-group">
                <input type="text" 
                       name="items[${rowCounter}][batch_no]" 
                       id="batchNo_${rowCounter}" 
                       class="form-control text-center font-monospace font-weight-bold text-uppercase px-1" 
                       placeholder="BATCH #" 
                       value="${batchNo}" 
                       autocomplete="off" 
                       required
                       onfocus="this.select()"
                       oninput="onBatchManualInput(${rowCounter})">
                <div class="input-group-append">
                    <button type="button" 
                            class="btn btn-outline-secondary px-2" 
                            id="batchDropdownBtn_${rowCounter}" 
                            data-toggle="dropdown" 
                            aria-haspopup="true" 
                            aria-expanded="false" 
                            title="Select existing batch or choose + New Batch">
                        <i class="fas fa-caret-down"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-right shadow p-0 batch-dropdown-menu" 
                         id="batchMenu_${rowCounter}" 
                         style="max-height: 250px; overflow-y: auto; font-size: 0.85rem; min-width: 230px; z-index: 1070;">
                        <div class="p-2 text-muted small text-center" id="batchMenuEmpty_${rowCounter}">
                            <i class="fas fa-info-circle mr-1"></i>Select medicine first
                        </div>
                    </div>
                </div>
            </div>
            <div id="batchMetaDisp_${rowCounter}" class="text-muted text-center mt-1 font-monospace" style="font-size: 0.68rem; line-height: 1.1; white-space: nowrap;"></div>
        </td>

        <!-- 1.6 Expiry Date -->
        <td style="width: 130px;">
            <input type="date" 
                   name="items[${rowCounter}][expiry_date]" 
                   id="expiry_${rowCounter}" 
                   class="form-control text-center font-weight-bold px-1" 
                   value="${expiryDate}" 
                   required>
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
            <input type="number" step="0.01" min="0" name="items[${rowCounter}][purchase_price]" id="cost_${rowCounter}" class="form-control text-right font-weight-bold" placeholder="0.00" value="${cost}" onfocus="this.select()" required oninput="onCostChange(${rowCounter})">
        </td>

        <!-- 5. TP Discount % -->
        <td style="width: 80px;">
            <input type="number" step="0.01" min="0" max="100" name="items[${rowCounter}][discount_percent]" id="discPct_${rowCounter}" class="form-control text-center font-weight-bold text-success" placeholder="0.00" value="${discPct}" title="TP Discount % (Supplier Disc)" onfocus="this.select()" oninput="calculateRowTotal(${rowCounter})">
            <input type="hidden" name="items[${rowCounter}][discount_amount]" id="discAmt_${rowCounter}" value="0.00">
            <div id="discAmtDisp_${rowCounter}" class="text-center mt-1" style="font-size: 0.68rem; line-height: 1.1; white-space: nowrap;"></div>
        </td>

        <!-- 5.5 GST % -->
        <td style="width: 75px;">
            <input type="number" step="0.01" min="0" max="100" name="items[${rowCounter}][tax_percent]" id="gstPct_${rowCounter}" class="form-control text-center font-weight-bold text-primary" placeholder="0.00" value="${gstPct}" onfocus="this.select()" oninput="calculateRowTotal(${rowCounter})">
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
                <input type="number" step="0.01" min="0" max="100" name="items[${rowCounter}][sale_discount_percent]" id="saleDiscPct_${rowCounter}" class="form-control text-center font-weight-bold text-primary" placeholder="0.00" value="${saleDiscPct}" onfocus="this.select()" oninput="onRowSaleDiscChange(${rowCounter})">
                <div class="input-group-append">
                    <span class="input-group-text bg-white text-primary font-weight-bold px-1" style="font-size:0.70rem;">%</span>
                </div>
            </div>
            <div id="salePriceDisp_${rowCounter}" class="text-center font-weight-bold text-primary mt-1" style="font-size: 0.70rem;">Sale: Rs. 0.00</div>
            <input type="hidden" name="items[${rowCounter}][trade_price]" id="tp_${rowCounter}" value="${tp}">
            <input type="hidden" name="items[${rowCounter}][sale_price]" id="salePrice_${rowCounter}" value="${salePrice}">
        </td>

        <!-- 9. Action -->
        <td class="text-center" style="width: 40px;">
            <button type="button" class="btn-remove-row" title="Remove" onclick="removeRow(${rowCounter})">
                <i class="fas fa-times"></i>
            </button>
        </td>
    `;

    tbody.appendChild(tr);
    if (prefilledData) {
        calculateRowTotal(rowCounter);
        updateRowSalePrice(rowCounter);
    }
    calculateBillTotals();
    setActiveRow(rowCounter);
}

function addAnotherBatchForProduct(sourceRowId) {
    const pId = document.getElementById('productId_' + sourceRowId)?.value;
    if (!pId) return;

    const medName = document.getElementById('medSearch_' + sourceRowId)?.value || '';
    const baseCost = document.getElementById('baseCost_' + sourceRowId)?.value || '0.00';
    const baseTp = document.getElementById('baseTp_' + sourceRowId)?.value || '0.00';
    const packsPerBox = document.getElementById('packsPerBox_' + sourceRowId)?.value || 1;
    const cost = document.getElementById('cost_' + sourceRowId)?.value || '';
    const tp = document.getElementById('tp_' + sourceRowId)?.value || '';
    const discPct = document.getElementById('discPct_' + sourceRowId)?.value || '';
    const gstPct = document.getElementById('gstPct_' + sourceRowId)?.value || '';
    const saleDiscPct = document.getElementById('saleDiscPct_' + sourceRowId)?.value || '';
    const salePrice = document.getElementById('salePrice_' + sourceRowId)?.value || '';
    const expDate = document.getElementById('expiry_' + sourceRowId)?.value || '';

    addNewItemRow({
        product_id: pId,
        med_name: medName,
        base_cost: baseCost,
        base_tp: baseTp,
        packs_per_box: packsPerBox,
        cost: cost,
        tp: tp,
        disc_pct: discPct,
        gst_pct: gstPct,
        sale_disc_pct: saleDiscPct,
        sale_price: salePrice,
        batch_no: '',
        expiry_date: expDate
    });

    const newRowId = rowCounter;
    const cachedBatches = rowBatchesMap[sourceRowId] || [];
    if (cachedBatches.length > 0) {
        populateProductBatchOptions(newRowId, cachedBatches);
        createNewBatchPrompt(newRowId);
    } else {
        fetchBatchOptions(newRowId, pId);
    }

    const newBatchInput = document.getElementById('batchNo_' + rowCounter);
    if (newBatchInput) {
        newBatchInput.focus();
        newBatchInput.select();
    }
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
    // Calculate benefit percentage based on purchased quantity (qty * cost)
    const benefitPct = gross > 0 ? (discountBenefit / gross * 100) : 0;

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

    // Update row-level Total Benefit % (based on purchased quantity)
    const allDiscPct = benefitPct;
    
    const rowAllDiscInput = document.getElementById('rowAllDiscPct_' + rowId);
    if (rowAllDiscInput) rowAllDiscInput.value = allDiscPct.toFixed(2);

    const effectiveCost = getRowEffectiveCost(rowId);

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

    // Update Total Benefit % for all rows
    rows.forEach(tr => {
        const rId = tr.id.replace('row_', '');
        const cInput = document.getElementById('cost_' + rId);
        const qInput = document.getElementById('qty_' + rId);
        const bInput = document.getElementById('bonusQty_' + rId);
        const dInput = document.getElementById('discPct_' + rId);

        const rCost   = parseFloat(cInput ? cInput.value : 0) || 0;
        const rQty    = parseFloat(qInput ? qInput.value : 0) || 0;
        const rBonus  = parseFloat(bInput ? bInput.value : 0) || 0;
        const rDisc   = parseFloat(dInput ? dInput.value : 0) || 0;
        const effCost = getRowEffectiveCost(rId);

        const rGross      = rQty * rCost;
        const rDiscAmt    = parseFloat((rGross * (rDisc / 100)).toFixed(2));
        const rBonusValue = parseFloat((rBonus * rCost).toFixed(2));
        const rBenefit    = parseFloat((rDiscAmt + rBonusValue).toFixed(2));
        const rBenefitPct = rGross > 0 ? (rBenefit / rGross * 100) : 0;

        const radInput = document.getElementById('rowAllDiscPct_' + rId);
        if (radInput) radInput.value = rBenefitPct.toFixed(2);
        const ecDisp = document.getElementById('effectiveCostDisp_' + rId);
        if (ecDisp) ecDisp.textContent = 'Cost: ' + effCost.toFixed(2);

        const discDisplay = document.getElementById('discDisplay_' + rId);
        if (discDisplay) {
            if (rBenefit > 0) {
                discDisplay.innerHTML = `<span class="text-danger font-weight-bold" title="Total Benefit (Disc + Free Bonus Goods)">Benefit: ${rBenefit.toFixed(2)} (${rBenefitPct.toFixed(1)}%)</span>`;
            } else {
                discDisplay.innerHTML = '';
            }
        }
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

// Page initialization & safety checks
function initPurchasePage() {
    const purchaseForm = document.getElementById('purchaseForm');
    if (purchaseForm && !purchaseForm.getAttribute('data-initialized')) {
        purchaseForm.setAttribute('data-initialized', 'true');
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
                const qtyInput = document.getElementById('qty_' + rId);
                let qty = parseFloat(qtyInput?.value || 0);
                if (pid > 0 && qty <= 0) {
                    if (qtyInput) {
                        qtyInput.value = '1';
                        calculateRowTotal(rId);
                        qty = 1;
                    }
                }
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

    // Add first item row automatically if table is empty
    const tbody = document.getElementById('itemsTableBody');
    const existingRows = tbody ? tbody.querySelectorAll('tr') : [];
    if (tbody && existingRows.length === 0) {
        addNewItemRow();
    }

    // Quick Supplier Modal form submit
    const quickSupplierForm = document.getElementById('quickSupplierForm');
    const qsFeedback = document.getElementById('qsFeedback');

    if (quickSupplierForm && !quickSupplierForm.getAttribute('data-initialized')) {
        quickSupplierForm.setAttribute('data-initialized', 'true');
        quickSupplierForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (qsFeedback) qsFeedback.classList.add('d-none');

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
                    if (qsFeedback) {
                        qsFeedback.textContent = data.message || 'Could not register supplier.';
                        qsFeedback.classList.remove('d-none');
                    }
                }
            })
            .catch(err => {
                if (qsFeedback) {
                    qsFeedback.textContent = 'Network or server error.';
                    qsFeedback.classList.remove('d-none');
                }
            });
        });
    }

    // Auto-select text on focus so user can immediately overwrite values without backspacing
    document.addEventListener('focus', function(e) {
        if (e.target && (e.target.matches('input[type=number]') || e.target.matches('.med-search-input') || (e.target.id && e.target.id.startsWith('batchNo_')))) {
            e.target.select();
        }
    }, true);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPurchasePage);
} else {
    initPurchasePage();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php';
if (ob_get_level()) { ob_end_flush(); }
?>
