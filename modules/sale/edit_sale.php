<?php
/**
 * Bestway Wholesale Distribution - Edit Sales Invoice
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

// AJAX endpoint for live product search from bestway_wholesale.products
if (isset($_GET['action']) && $_GET['action'] === 'search_product') {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    $res = [];
    if ($db_connected && $pdo) {
        $sql = "SELECT p.id, p.product_code, p.name, p.generic_name, p.trade_price, p.retail_price, p.purchase_price, p.current_stock, p.stock_unit, p.packs_per_box, c.name as company_name, p.discount_percent 
                FROM products p 
                LEFT JOIN companies c ON c.id = p.company_id 
                WHERE p.status = 'Active'";
        if ($q !== '') {
            $sql .= " AND (p.name LIKE ? OR p.product_code LIKE ? OR p.generic_name LIKE ? OR c.name LIKE ?)";
            $stmt = $pdo->prepare($sql . " ORDER BY p.name ASC LIMIT 30");
            $like = "%$q%";
            $stmt->execute([$like, $like, $like, $like]);
        } else {
            $stmt = $pdo->prepare($sql . " ORDER BY p.name ASC LIMIT 60");
            $stmt->execute();
        }
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode($res);
    exit;
}

$page_title = "Edit Sales Invoice";
require_once __DIR__ . '/../../includes/header.php';

// Database connection for sales invoices

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo "<div class='alert alert-danger m-4'>Invalid Invoice ID. <a href='sales.php'>Wapis jayein</a></div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$message = "";
$msg_type = "";

// Fetch Bookers, Routes, Accounts, Customers & System Products
$bookers = [];
$routes  = [];
$cash_accounts = [];
$bank_accounts = [];
$customers = [];
$initial_products = [];

if ($db_connected && $pdo) {
    try {
        $bookers = getActiveBookers($pdo);
        $routes  = $pdo->query("SELECT id, name, route_code, assigned_booker_id FROM routes WHERE status = 'Active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $cash_accounts = $pdo->query("SELECT id, account_name, balance FROM cash_accounts ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        $bank_accounts = $pdo->query("SELECT id, bank_name, account_title, account_number FROM bank_accounts WHERE status = 'Active' ORDER BY bank_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $customers = $pdo->query("SELECT id, name, shop_name, phone, route_id, current_balance FROM customers WHERE status = 'Active' ORDER BY shop_name ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt_init_p = $pdo->query("
            SELECT p.id, p.product_code, p.name, p.generic_name, p.trade_price, p.retail_price, p.current_stock, p.stock_unit, p.packs_per_box, c.name as company_name 
            FROM products p 
            LEFT JOIN companies c ON c.id = p.company_id 
            WHERE p.status = 'Active' 
            ORDER BY p.name ASC
        ");
        $initial_products = $stmt_init_p->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Handle Form Submission: Update Invoice
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $invoice_date  = trim($_POST['invoice_date'] ?? date('Y-m-d'));
    $payment_terms  = trim($_POST['payment_terms'] ?? 'Cash');
    $payment_mode   = trim($_POST['payment_mode'] ?? 'Cash');
    $payment_method = 'Cash';
    $cash_account_id = null;
    $bank_account_id = null;

    if (str_starts_with($payment_mode, 'Cash_')) {
        $payment_method  = 'Cash';
        $cash_account_id = intval(substr($payment_mode, 5));
    } elseif (str_starts_with($payment_mode, 'Bank_')) {
        $payment_method  = 'Bank';
        $bank_account_id = intval(substr($payment_mode, 5));
    } elseif ($payment_mode === 'Credit') {
        $payment_method  = 'Credit';
    } else {
        $payment_method  = $payment_mode;
    }
    $notes = trim($_POST['notes'] ?? '');

    $booker_id   = !empty($_POST['booker_id']) ? intval($_POST['booker_id']) : null;
    $booker_name = trim($_POST['booker_name'] ?? '');
    $route_id    = !empty($_POST['route_id']) ? intval($_POST['route_id']) : null;
    $route_name  = trim($_POST['route_name'] ?? '');

    if ($booker_id && empty($booker_name)) {
        foreach ($bookers as $b) {
            if ($b['id'] == $booker_id) { $booker_name = $b['name']; break; }
        }
    }
    if ($route_id && empty($route_name)) {
        foreach ($routes as $r) {
            if ($r['id'] == $route_id) { $route_name = $r['name']; break; }
        }
    }

    // Salesman logged-in -> invoice ALWAYS credited to him (server-side, ignores spoofed booker fields)
    $auto_salesman = null;
    if (!empty($_SESSION['user_id'])) {
        $auto_salesman = currentSalesmanForUser($pdo, (int)$_SESSION['user_id']);
    }
    if ($auto_salesman) {
        $booker_id    = (int)$auto_salesman['id'];
        $booker_name  = (string)$auto_salesman['full_name'];
    }

    $product_ids = (isset($_POST['product_id']) && is_array($_POST['product_id'])) ? $_POST['product_id'] : [];
    $item_names  = (isset($_POST['item_name']) && is_array($_POST['item_name'])) ? $_POST['item_name'] : [];
    $quantities  = (isset($_POST['quantity']) && is_array($_POST['quantity'])) ? $_POST['quantity'] : [];
    $unit_prices = (isset($_POST['unit_price']) && is_array($_POST['unit_price'])) ? $_POST['unit_price'] : [];
    $discounts   = (isset($_POST['disc_percent']) && is_array($_POST['disc_percent'])) ? $_POST['disc_percent'] : [];
    $extra_discs = (isset($_POST['extra_disc_percent']) && is_array($_POST['extra_disc_percent'])) ? $_POST['extra_disc_percent'] : [];

    if (empty($customer_name) || empty($invoice_date) || empty($product_ids)) {
        $message = "Customer name, date, and at least one product item are required.";
        $msg_type = "danger";
    } else {
        try {
            $conn->begin_transaction();
            if ($db_connected && $pdo) {
                $pdo->beginTransaction();
            }

            // STEP 1: Fetch old items and REVERT their quantities to stock
            $old_items_stmt = $conn->prepare("SELECT product_id, quantity FROM sale_items WHERE invoice_id = ?");
            $old_items_stmt->bind_param("i", $id);
            $old_items_stmt->execute();
            $old_res = $old_items_stmt->get_result();
            $old_items = $old_res->fetch_all(MYSQLI_ASSOC);
            $old_items_stmt->close();

            if ($db_connected && $pdo) {
                $stmt_restore = $pdo->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = ?");
                foreach ($old_items as $oit) {
                    $opid = intval($oit['product_id']);
                    $oqty = intval($oit['quantity']);
                    if ($opid > 0 && $oqty > 0) {
                        $stmt_restore->execute([$oqty, $opid]);
                    }
                }
            }

            // STEP 2: Validate new items against fresh database stock
            $validated_items = [];
            $subtotal = 0;
            $total_discount_cut = 0;

            for ($i = 0; $i < count($product_ids); $i++) {
                $pid   = intval($product_ids[$i] ?? 0);
                $qty   = intval($quantities[$i] ?? 0);
                $tp    = floatval($unit_prices[$i] ?? 0);
                $disc  = floatval($discounts[$i] ?? 0);
                $xdisc = floatval($extra_discs[$i] ?? 0);
                $name  = trim($item_names[$i] ?? '');

                if ($pid <= 0) {
                    throw new Exception("Row #" . ($i + 1) . ": Please search and select a registered product from the system list.");
                }
                if ($qty <= 0) {
                    throw new Exception("Row #" . ($i + 1) . ": Quantity must be at least 1.");
                }

                $stmt_chk = $pdo->prepare("SELECT id, product_code, name, current_stock, trade_price FROM products WHERE id = ?");
                $stmt_chk->execute([$pid]);
                $prod_row = $stmt_chk->fetch(PDO::FETCH_ASSOC);

                if (!$prod_row) {
                    throw new Exception("Row #" . ($i + 1) . ": Product ID {$pid} not found in system.");
                }

                $avail_stock = intval($prod_row['current_stock']);
                $p_name = $prod_row['name'];

                if ($avail_stock <= 0) {
                    throw new Exception("⚠️ Error: Product '{$p_name}' is out of stock (Stock: 0)!");
                }
                if ($qty > $avail_stock) {
                    throw new Exception("⚠️ Error: Product '{$p_name}' has only {$avail_stock} in stock, but entered quantity is {$qty}!");
                }

                $gross = $qty * $tp;
                $net   = $gross - ($gross * ($disc / 100));

                $subtotal += $gross;
                $total_discount_cut += ($gross - $net);

                $validated_items[] = [
                    'product_id' => $pid,
                    'name'       => $p_name,
                    'qty'        => $qty,
                    'tp'         => $tp,
                    'disc'       => $disc,
                    'xdisc'      => $xdisc,
                    'net'        => $net
                ];
            }

            // Calculations
            $items_net = $subtotal - $total_discount_cut;
            $order_discount_percent = floatval($_POST['order_discount_percent'] ?? 0);
            $order_discount_amount  = $items_net * ($order_discount_percent / 100);
            $total_discount_cut    += $order_discount_amount;

            $shipping_cost    = 0.00;
            $adjustment       = 0.00;
            $round_off        = floatval($_POST['round_off'] ?? 0);
            $grand_total      = ($items_net - $order_discount_amount) + $round_off;

            $previous_balance = floatval($_POST['previous_balance'] ?? 0);
            $net_payable      = $grand_total + $previous_balance;
            $paid_amount      = floatval($_POST['paid_amount'] ?? 0);
            $balance_due      = $net_payable - $paid_amount;

            // STEP 3: Update parent invoice
            $upd_stmt = $conn->prepare("
                UPDATE sales_invoices SET 
                    customer_name = ?, invoice_date = ?, payment_terms = ?, payment_method = ?,
                    cash_account_id = ?, bank_account_id = ?, subtotal = ?, discount_amount = ?, discount_percent = ?,
                    shipping_cost = ?, adjustment = ?, round_off = ?, grand_total = ?, previous_balance = ?,
                    net_payable = ?, paid_amount = ?, balance_due = ?, booker_id = ?, booker_name = ?,
                    route_id = ?, route_name = ?, notes = ?
                WHERE id = ?
            ");
            $upd_stmt->bind_param("sssssiiddddddddddisissi",
                $customer_name, $invoice_date, $payment_terms, $payment_method,
                $cash_account_id, $bank_account_id,
                $subtotal, $total_discount_cut, $order_discount_percent, $shipping_cost, $adjustment, $round_off,
                $grand_total, $previous_balance, $net_payable, $paid_amount, $balance_due,
                $booker_id, $booker_name, $route_id, $route_name, $notes,
                $id
            );
            $upd_stmt->execute();
            $upd_stmt->close();

            // STEP 4: Delete old sale items and insert newly validated items with new stock deduction
            $pdo->prepare("DELETE FROM sale_items WHERE invoice_id = ?")->execute([$id]);

            $stmt_si = $pdo->prepare("
                INSERT INTO sale_items 
                (invoice_id, sale_id, product_id, item_name, batch_no, expiry_date, quantity, unit_price, sale_price, trade_price, discount_percent, extra_discount_percent, total_amount, total_price) 
                VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_deduct_stock = $pdo->prepare("UPDATE products SET current_stock = GREATEST(0, current_stock - ?) WHERE id = ?");
            $stmt_batches_fifo = $pdo->prepare("SELECT id, batch_no, expiry_date, current_stock FROM product_batches WHERE product_id = ? AND current_stock > 0 ORDER BY expiry_date ASC, id ASC");
            $stmt_deduct_batch = $pdo->prepare("UPDATE product_batches SET current_stock = GREATEST(0, current_stock - ?) WHERE id = ?");

            foreach ($validated_items as $v_item) {
                $pid   = $v_item['product_id'];
                $pname = $v_item['name'];
                $qty   = $v_item['qty'];
                $tp    = $v_item['tp'];
                $disc  = $v_item['disc'];
                $xdisc = $v_item['xdisc'];
                $l_net = $v_item['net'];

                // DEDUCT FROM BATCHES (FIFO) & get batch info
                $stmt_batches_fifo->execute([$pid]);
                $batches = $stmt_batches_fifo->fetchAll(PDO::FETCH_ASSOC);
                $rem_qty = $qty;
                $assigned_batch = '';
                $assigned_expiry = null;

                if (!empty($batches)) {
                    $assigned_batch = $batches[0]['batch_no'] ?? '';
                    $assigned_expiry = !empty($batches[0]['expiry_date']) ? $batches[0]['expiry_date'] : null;
                    foreach ($batches as $b) {
                        if ($rem_qty <= 0) break;
                        $b_stock = intval($b['current_stock']);
                        $deduct = min($rem_qty, $b_stock);
                        $stmt_deduct_batch->execute([$deduct, $b['id']]);
                        $rem_qty -= $deduct;
                    }
                }

                // Insert into sale_items using PDO
                $stmt_si->execute([
                    $id,
                    $pid,
                    $pname,
                    $assigned_batch,
                    $assigned_expiry,
                    $qty,
                    $tp,
                    $tp,
                    $tp,
                    $disc,
                    $xdisc,
                    $l_net,
                    $l_net
                ]);

                // Deduct stock from inventory
                $stmt_deduct_stock->execute([$qty, $pid]);
            }

            // Update cash/bank balance and record in cashbook/bankbook
            if ($paid_amount > 0) {
                $book_desc = "Sale Invoice #{$invoice['invoice_no']} - {$customer_name} (Edited)";
                $user_id_book = $_SESSION['user_id'] ?? 1;
                if ($payment_method === 'Cash') {
                    if ($cash_account_id) {
                        $pdo->prepare("UPDATE cash_accounts SET balance = balance + ? WHERE id = ?")->execute([$paid_amount, $cash_account_id]);
                    }
                    recordCashInflow($pdo, $invoice_date, $paid_amount, $book_desc, 'sale', $id, $user_id_book);
                } elseif ($payment_method === 'Bank' && $bank_account_id) {
                    $pdo->prepare("UPDATE bank_accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$paid_amount, $bank_account_id]);
                    recordBankInflow($pdo, $invoice_date, $paid_amount, $book_desc, 'sale', $id, $user_id_book, $bank_account_id);
                }
            }

            // Synchronize Credit Book (Udhaar)
            if ($db_connected && $pdo) {
                $credit_amount = ($balance_due > 0) ? $balance_due : (($payment_method === 'Credit') ? $grand_total : 0);
                $stmt_chk_u = $pdo->prepare("SELECT id FROM udhaar_book WHERE invoice_id = ? OR invoice_no = ?");
                $stmt_chk_u->execute([$id, $invoice['invoice_no']]);
                $existing_u_id = $stmt_chk_u->fetchColumn();

                if ($credit_amount > 0 && ($payment_method === 'Credit' || $balance_due > 0)) {
                    $desc = "Sales Invoice #{$invoice['invoice_no']}";
                    if ($paid_amount > 0) {
                        $desc .= " (Total: Rs. " . number_format($grand_total, 2) . ", Paid: Rs. " . number_format($paid_amount, 2) . ", Due: Rs. " . number_format($balance_due, 2) . ")";
                    } else {
                        $desc .= " (Credit Sale)";
                    }
                    if (!empty($notes)) {
                        $desc .= " - " . $notes;
                    }

                    if ($existing_u_id) {
                        $stmt_u = $pdo->prepare("UPDATE udhaar_book SET party_name = ?, invoice_no = ?, invoice_id = ?, amount = ?, type = 'Given', credit_date = ?, description = ? WHERE id = ?");
                        $stmt_u->execute([$customer_name, $invoice['invoice_no'], $id, $credit_amount, $invoice_date, $desc, $existing_u_id]);
                    } else {
                        $stmt_u = $pdo->prepare("INSERT INTO udhaar_book (party_name, invoice_no, invoice_id, amount, type, credit_date, description) VALUES (?, ?, ?, ?, 'Given', ?, ?)");
                        $stmt_u->execute([$customer_name, $invoice['invoice_no'], $id, $credit_amount, $invoice_date, $desc]);
                    }
                } else {
                    if ($existing_u_id) {
                        $pdo->prepare("DELETE FROM udhaar_book WHERE id = ?")->execute([$existing_u_id]);
                    }
                }
            }

            $conn->commit();
            if ($db_connected && $pdo) {
                $pdo->commit();
            }

            // Refresh customer balance from updated invoice
            if ($db_connected && $pdo) {
                try {
                    $cid_stmt = $pdo->prepare("SELECT customer_id FROM sales_invoices WHERE id = ?");
                    $cid_stmt->execute([$id]);
                    $inv_cid = (int)$cid_stmt->fetchColumn();
                    if ($inv_cid > 0) {
                        updateCustomerBalance($pdo, $inv_cid);
                    }
                } catch (Exception $e) {}
            }

            $message = "Sales Invoice updated successfully and Credit Book (Udhaar) synchronized!";
            $msg_type = "success";

            // Refresh initial products
            $stmt_init_p = $pdo->query("SELECT p.id, p.product_code, p.name, p.trade_price, p.current_stock FROM products p WHERE p.status = 'Active' ORDER BY p.name ASC");
            $initial_products = $stmt_init_p->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $conn->rollback();
            if ($db_connected && $pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = $e->getMessage();
            $msg_type = "danger";
        }
    }
}

// Fetch current invoice state
$stmt = $conn->prepare("SELECT * FROM sales_invoices WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$inv_res = $stmt->get_result();
$invoice = $inv_res->fetch_assoc();
$stmt->close();

// Fetch line items
$item_stmt = $conn->prepare("SELECT * FROM sale_items WHERE invoice_id = ? ORDER BY id ASC");
$item_stmt->bind_param("i", $id);
$item_stmt->execute();
$items_res = $item_stmt->get_result();
$existing_items = $items_res->fetch_all(MYSQLI_ASSOC);
$item_stmt->close();

?>

<!-- Clean Form & Table Styles Consistent with Purchase Module -->
<style>
    .items-table th {
        background-color: #f8f9fc;
        color: #4e73df;
        font-weight: 700;
        font-size: 0.82rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
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
    .items-table tbody tr {
        transition: background-color 0.15s ease;
    }
    .items-table tbody tr:hover {
        background-color: #f8f9fc;
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
    .product-search-container { position: relative; }
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
    .search-dropdown-item.out-of-stock {
        background: #fff8f8;
        opacity: 0.9;
    }
    .search-dropdown-item.out-of-stock:hover {
        background: #fee2e2;
        border-left: 3px solid #e74a3b;
    }
    .search-dropdown-item .highlight-match {
        background: #fef08a;
        font-weight: 700;
        border-radius: 2px;
        padding: 0 1px;
    }
    .stock-pill {
        font-size: 0.76rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        border-radius: 6px;
    }
</style>

<!-- Top Title Bar -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800 font-weight-bold">
            <i class="fas fa-edit text-primary mr-2"></i>Edit Sales Invoice #<?= htmlspecialchars($invoice['invoice_no']) ?>
        </h1>
        <p class="text-muted small mb-0">Modify products, quantities, prices & recalculate balance due</p>
    </div>
    <div class="d-flex">
        <a href="view_sale.php?id=<?= $invoice['id'] ?>" class="btn btn-outline-primary btn-sm shadow-sm mr-2">
            <i class="fas fa-eye fa-sm mr-1"></i> View Invoice
        </a>
        <a href="sales.php" class="btn btn-secondary btn-sm shadow-sm">
            <i class="fas fa-arrow-left fa-sm mr-1"></i> Back to Sales
        </a>
    </div>
</div>

<!-- Alert Notifications -->
<?php if (!empty($message)): ?>
    <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show shadow-sm" role="alert">
        <i class="fas <?= $msg_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> mr-2"></i>
        <strong><?= htmlspecialchars($message) ?></strong>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
<?php endif; ?>

<!-- Invoice Edit Form -->
<form action="" method="POST" id="saleForm">
    <!-- Card 1: Customer & Routing Details -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-store mr-1"></i> Customer & Routing Information
            </h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-5 mb-3">
                    <label class="font-weight-bold text-gray-700 small">Customer Name <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-hospital text-muted"></i></span>
                        </div>
                        <select name="customer_name" id="customerSelect" class="form-control" required>
                            <option value="">-- Select Registered Customer --</option>
                            <?php foreach ($customers as $c): 
                                $c_display = !empty($c['shop_name']) ? $c['shop_name'] : $c['name'];
                                $is_sel = ($invoice['customer_name'] === $c_display) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($c_display) ?>" data-balance="<?= $c['current_balance'] ?>" data-route="<?= $c['route_id'] ?>" <?= $is_sel ?>>
                                    <?= htmlspecialchars($c_display) ?> (<?= htmlspecialchars($c['name']) ?>) - Bal: Rs. <?= number_format($c['current_balance'], 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="font-weight-bold text-gray-700 small">Invoice Number</label>
                    <input type="text" name="invoice_no" class="form-control font-weight-bold text-primary" value="<?= htmlspecialchars($invoice['invoice_no']) ?>" readonly>
                </div>

                <div class="col-md-3 mb-3">
                    <label class="font-weight-bold text-gray-700 small">Invoice Date <span class="text-danger">*</span></label>
                    <input type="date" name="invoice_date" class="form-control" value="<?= htmlspecialchars($invoice['invoice_date']) ?>" required>
                </div>
            </div>

            <div class="row">
                <input type="hidden" name="booker_id" id="bookerSelect" value="<?= htmlspecialchars($invoice['booker_id'] ?? '') ?>">
                <input type="hidden" name="booker_name" id="bookerNameInput" value="<?= htmlspecialchars($invoice['booker_name'] ?? '') ?>">

                <div class="col-md-6 mb-2">
                    <label class="font-weight-bold text-gray-700 small">Route / Area</label>
                    <select name="route_id" id="routeSelect" class="form-control">
                        <option value="">-- Main Route --</option>
                        <?php foreach ($routes as $r): ?>
                            <option value="<?= $r['id'] ?>" data-route-name="<?= htmlspecialchars($r['name']) ?>" <?= ($invoice['route_id'] == $r['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="route_name" id="routeNameInput" value="<?= htmlspecialchars($invoice['route_name']) ?>">
                </div>

                <div class="col-md-6 mb-2">
                    <label class="font-weight-bold text-gray-700 small">Payment Method</label>
                    <select name="payment_mode" class="form-control font-weight-bold">
                        <?php if (!empty($cash_accounts)): ?>
                            <optgroup label="Cash Accounts">
                                <?php foreach ($cash_accounts as $ca): ?>
                                    <option value="Cash_<?= $ca['id'] ?>" <?= ($invoice['cash_account_id'] == $ca['id']) ? 'selected' : '' ?>>Cash: <?= htmlspecialchars($ca['account_name']) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php else: ?>
                            <option value="Cash" <?= ($invoice['payment_method'] === 'Cash') ? 'selected' : '' ?>>Cash in Hand</option>
                        <?php endif; ?>

                        <?php if (!empty($bank_accounts)): ?>
                            <optgroup label="Bank Accounts">
                                <?php foreach ($bank_accounts as $ba): ?>
                                    <option value="Bank_<?= $ba['id'] ?>" <?= ($invoice['bank_account_id'] == $ba['id']) ? 'selected' : '' ?>>Bank: <?= htmlspecialchars($ba['bank_name']) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>

                        <option value="Credit" <?= ($invoice['payment_method'] === 'Credit') ? 'selected' : '' ?>>Credit (Udhaar)</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 2: Medicine Billing Items -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-pills mr-1"></i> Medicine Billing Items
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered items-table mb-0" id="saleItemsTable" width="100%" cellspacing="0">
                    <thead class="thead-light">
                        <tr>
                            <th style="width: 40px;" class="text-center">#</th>
                            <th style="min-width: 320px;">Medicine / Product Name <span class="text-danger">*</span></th>
                            <th style="width: 120px;" class="text-center">Qty (Pcs) <span class="text-danger">*</span></th>
                            <th style="width: 150px;" class="text-right">TP / Price (Rs) <span class="text-danger">*</span></th>
                            <th style="width: 110px;" class="text-center">Disc %</th>
                            <th style="width: 160px;" class="text-right">Net Total (Rs)</th>
                            <th style="width: 60px;" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <!-- Populated dynamically via JS -->
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white border-top-0 py-3">
            <button type="button" id="addRowBtn" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-plus mr-1"></i> Add Another Medicine
            </button>
        </div>
    </div>

    <!-- Card 3 & 4: Summary & Calculations -->
    <div class="row">
        <!-- Left: Summary Breakdown & Notes -->
        <div class="col-lg-7 mb-4">
            <div class="card shadow mb-3">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-list-alt mr-1"></i> Items Breakdown
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="text-muted font-weight-bold">Items Gross Subtotal:</span>
                        <span class="font-weight-bold text-gray-800" id="itemsGrossDisplay">Rs. 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="text-muted font-weight-bold">Total Product Discounts:</span>
                        <span class="font-weight-bold text-danger" id="productDiscDisplay">- Rs. 0.00</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Checkout Calculation Box -->
        <div class="col-lg-5 mb-4">
            <div class="card shadow calc-box">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-calculator mr-1"></i> Bill Summary & Checkout
                    </h6>
                </div>
                <div class="card-body">
                    <div class="calc-row">
                        <span class="calc-label">Order Discount (%)</span>
                        <div class="calc-input">
                            <input type="number" step="0.01" min="0" max="100" name="order_discount_percent" id="orderDiscount" class="form-control text-right" placeholder="0.00" value="<?= floatval($invoice['discount_percent']) > 0 ? floatval($invoice['discount_percent']) : '' ?>" onfocus="this.select()">
                        </div>
                    </div>

                    <div class="calc-row">
                        <span class="calc-label">Round Off</span>
                        <div class="calc-input">
                            <input type="number" step="0.01" name="round_off" id="roundOffValue" class="form-control text-right" placeholder="0.00" value="<?= floatval($invoice['round_off']) != 0 ? floatval($invoice['round_off']) : '' ?>" onfocus="this.select()">
                        </div>
                    </div>

                    <div class="calc-row">
                        <span class="calc-label font-weight-bold text-dark">Current Bill</span>
                        <div class="calc-input">
                            <input type="text" name="grand_total" id="currentBillInput" class="form-control text-right font-weight-bold" placeholder="0.00" readonly>
                        </div>
                    </div>

                    <div class="calc-row">
                        <span class="calc-label font-weight-bold text-warning">Previous Balance</span>
                        <div class="calc-input">
                            <input type="number" step="0.01" name="previous_balance" id="previousBalanceInput" class="form-control text-right font-weight-bold text-warning" placeholder="0.00" value="<?= floatval($invoice['previous_balance']) != 0 ? floatval($invoice['previous_balance']) : '' ?>" onfocus="this.select()">
                        </div>
                    </div>

                    <div class="calc-row">
                        <span class="calc-label font-weight-bold text-primary">Net Payable</span>
                        <div class="calc-input">
                            <input type="text" name="net_payable" id="netPayableInput" class="form-control text-right font-weight-bold text-primary" placeholder="0.00" readonly>
                        </div>
                    </div>

                    <div class="calc-row">
                        <span class="calc-label font-weight-bold text-dark">Paid Amount</span>
                        <div class="calc-input">
                            <input type="number" step="0.01" min="0" name="paid_amount" id="paidAmountInput" class="form-control text-right font-weight-bold" placeholder="0.00" value="<?= floatval($invoice['paid_amount']) > 0 ? floatval($invoice['paid_amount']) : '' ?>" onfocus="this.select()">
                        </div>
                    </div>

                    <div class="calc-row">
                        <span class="calc-label font-weight-bold text-danger">Balance Due</span>
                        <div class="calc-input">
                            <input type="text" name="balance_due" id="balanceDueInput" class="form-control text-right font-weight-bold text-danger" placeholder="0.00" readonly>
                        </div>
                    </div>

                    <hr class="my-3">

                    <button type="submit" class="btn btn-primary btn-block btn-lg font-weight-bold shadow-sm">
                        <i class="fas fa-save mr-2"></i> UPDATE SALES INVOICE
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- JS for Live Product Search & Calculation Engine -->
<script>
    const systemProducts = <?= json_encode($initial_products) ?>;
    const existingItems = <?= json_encode($existing_items) ?>;
    let rowCounter = 0;

    const itemsBody = document.getElementById('itemsBody');
    const addRowBtn = document.getElementById('addRowBtn');

    function createRow(rowId, defaultData = null) {
        const tr = document.createElement('tr');
        tr.id = 'row_' + rowId;
        tr.className = 'sale-item-row';
        tr.innerHTML = `
            <td class="text-center font-weight-bold text-gray-600 align-middle">
                <span class="row-index"></span>
            </td>
            <td>
                <div class="product-search-container" id="searchContainer_${rowId}">
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-pills text-muted"></i></span>
                        </div>
                        <input type="text" 
                               class="form-control med-search-input" 
                               id="medSearch_${rowId}" 
                               placeholder="Search medicine name or code..." 
                               autocomplete="off"
                               onfocus="onMedSearchFocus(${rowId})"
                               oninput="onMedSearchInput(${rowId})"
                               onkeydown="onMedSearchKeydown(event, ${rowId})"
                               required>
                        <div class="input-group-append d-none" id="medClearBtn_${rowId}">
                            <button type="button" class="btn btn-outline-secondary" onclick="clearProductRow(${rowId})" title="Clear selection">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="product_id[]" id="productId_${rowId}" class="product-id-input" value="" required>
                    <input type="hidden" name="item_name[]" id="itemName_${rowId}" class="item-name-input" value="" required>
                    <input type="hidden" id="stockAvailable_${rowId}" class="stock-available-input" value="0">

                    <div class="search-dropdown-menu med-dropdown d-none shadow" id="medDropdown_${rowId}">
                        <div class="med-results-list" id="medResultsList_${rowId}"></div>
                    </div>
                </div>
            </td>
            <td>
                <input type="number" name="quantity[]" id="qty_${rowId}" class="form-control form-control-sm qty-input text-center font-weight-bold" min="1" placeholder="1" value="" onfocus="this.select()" oninput="onQtyChange(${rowId})" required>
            </td>
            <td>
                <input type="number" step="0.01" min="0" name="unit_price[]" id="tp_${rowId}" class="form-control form-control-sm tp-input text-right font-weight-bold" placeholder="0.00" onfocus="this.select()" required>
            </td>
            <td>
                <input type="number" step="0.1" min="0" max="100" name="disc_percent[]" id="disc_${rowId}" class="form-control form-control-sm disc-input text-center" placeholder="0.00" value="" onfocus="this.select()">
            </td>
            <td>
                <input type="text" class="form-control form-control-sm font-weight-bold row-net text-right text-dark bg-light" id="rowNet_${rowId}" placeholder="0.00" value="" readonly>
            </td>
            <td class="text-center align-middle">
                <button type="button" class="btn-remove-row remove-row" title="Delete Row">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </td>
        `;
        return tr;
    }

    function addNewRow(defaultData = null, focusSearch = false) {
        rowCounter++;
        const newTr = createRow(rowCounter);
        itemsBody.appendChild(newTr);

        if (defaultData) {
            const rowId = rowCounter;
            document.getElementById('productId_' + rowId).value = defaultData.product_id;
            document.getElementById('itemName_' + rowId).value = defaultData.item_name;
            document.getElementById('medSearch_' + rowId).value = defaultData.item_name;
            document.getElementById('qty_' + rowId).value = defaultData.quantity || '';
            document.getElementById('tp_' + rowId).value = parseFloat(defaultData.unit_price) > 0 ? parseFloat(defaultData.unit_price).toFixed(2) : '';
            document.getElementById('disc_' + rowId).value = parseFloat(defaultData.discount_percent) > 0 ? parseFloat(defaultData.discount_percent).toFixed(1) : '';

            // Find stock from system products
            const prod = systemProducts.find(p => p.id == defaultData.product_id);
            const stock = prod ? parseInt(prod.current_stock) + parseInt(defaultData.quantity) : 999;
            document.getElementById('stockAvailable_' + rowId).value = stock;

            const clearBtn = document.getElementById('medClearBtn_' + rowId);
            if (clearBtn) clearBtn.classList.remove('d-none');
        }

        updateRowIndices();
        recalculateAll();

        if (focusSearch) {
            const input = document.getElementById('medSearch_' + rowCounter);
            if (input) input.focus();
        }
    }

    function updateRowIndices() {
        const rows = itemsBody.querySelectorAll('tr');
        rows.forEach((row, idx) => {
            const indexEl = row.querySelector('.row-index');
            if (indexEl) indexEl.textContent = (idx + 1);
        });
    }

    // Search functions
    const medSearchTimers = {};
    const currentMedIdxs = {};

    function onMedSearchFocus(rowId) {
        const input = document.getElementById('medSearch_' + rowId);
        searchProductsForRow(rowId, input ? input.value.trim() : '');
    }

    function onMedSearchInput(rowId) {
        const input = document.getElementById('medSearch_' + rowId);
        const q = input ? input.value.trim() : '';
        if (!q) clearProductRowDataOnly(rowId);
        searchProductsForRow(rowId, q);
    }

    function searchProductsForRow(rowId, query) {
        const dd = document.getElementById('medDropdown_' + rowId);
        if (!dd) return;
        currentMedIdxs[rowId] = -1;

        const qLower = query.toLowerCase();
        const localMatches = systemProducts.filter(p => {
            if (!query) return true;
            return (p.name && p.name.toLowerCase().includes(qLower)) ||
                   (p.product_code && p.product_code.toLowerCase().includes(qLower));
        }).slice(0, 30);

        renderProductList(rowId, localMatches, query);
        dd.classList.remove('d-none');
    }

    function renderProductList(rowId, items, query) {
        const list = document.getElementById('medResultsList_' + rowId);
        if (!list) return;

        if (!items || items.length === 0) {
            list.innerHTML = `<div class="p-3 text-center text-muted small">No medicine found matching "${query}".</div>`;
            return;
        }

        list.innerHTML = items.map((p, idx) => {
            const stock = parseInt(p.current_stock || 0);
            const salePrice = parseFloat(p.retail_price || p.trade_price || p.purchase_price || 0);

            let stockBadge = stock > 0 ? `<span class="badge badge-success">Stock: ${stock}</span>` : `<span class="badge badge-danger">Out of Stock (0)</span>`;

            return `
                <div class="search-dropdown-item" data-idx="${idx}" onclick='handleProductSelection(${rowId}, ${JSON.stringify(p).replace(/'/g, "&apos;")})'>
                    <div class="d-flex justify-content-between align-items-center">
                        <strong class="text-dark font-weight-bold">${p.name}</strong>
                        ${stockBadge}
                    </div>
                    <div class="small text-muted d-flex justify-content-between mt-1">
                        <span>${p.company_name || 'General'}</span>
                        <span class="text-primary font-weight-bold">Sale Price: Rs. ${salePrice.toFixed(2)}</span>
                    </div>
                </div>
            `;
        }).join('');
    }

    function handleProductSelection(rowId, p) {
        const stock = parseInt(p.current_stock || 0);
        if (stock <= 0) {
            alert("⚠️ Error: '" + p.name + "' is out of stock! (Available Stock: 0)");
            return;
        }
        selectProductItem(rowId, p);
    }

    function selectProductItem(rowId, p) {
        const stock = parseInt(p.current_stock || 0);
        let tpRate = parseFloat(p.trade_price || 0);
        if (tpRate <= 0) tpRate = parseFloat(p.purchase_price || 0);

        document.getElementById('productId_' + rowId).value = p.id;
        document.getElementById('itemName_' + rowId).value = p.name;
        document.getElementById('medSearch_' + rowId).value = p.name;
        document.getElementById('stockAvailable_' + rowId).value = stock;
        document.getElementById('tp_' + rowId).value = tpRate > 0 ? tpRate.toFixed(2) : '';

        // Auto-fill Default Discount % if available
        const defDisc = parseFloat(p.discount_percent || 0) || 0;
        const discInput = document.getElementById('disc_' + rowId);
        if (discInput && !discInput.value) {
            discInput.value = defDisc > 0 ? defDisc.toFixed(1) : '';
        }

        const clearBtn = document.getElementById('medClearBtn_' + rowId);
        if (clearBtn) clearBtn.classList.remove('d-none');

        const qtyInput = document.getElementById('qty_' + rowId);
        qtyInput.max = stock;

        const dd = document.getElementById('medDropdown_' + rowId);
        if (dd) dd.classList.add('d-none');

        recalculateAll();
        qtyInput.focus();
        qtyInput.select();
    }

    function clearProductRow(rowId) {
        document.getElementById('medSearch_' + rowId).value = '';
        clearProductRowDataOnly(rowId);
        document.getElementById('medSearch_' + rowId).focus();
    }

    function clearProductRowDataOnly(rowId) {
        document.getElementById('productId_' + rowId).value = '';
        document.getElementById('itemName_' + rowId).value = '';
        document.getElementById('stockAvailable_' + rowId).value = '0';
        document.getElementById('tp_' + rowId).value = '';
        const clearBtn = document.getElementById('medClearBtn_' + rowId);
        if (clearBtn) clearBtn.classList.add('d-none');
        recalculateAll();
    }

    function onQtyChange(rowId) {
        const qtyInput = document.getElementById('qty_' + rowId);
        const stockVal = parseInt(document.getElementById('stockAvailable_' + rowId).value) || 0;
        const prodName = document.getElementById('itemName_' + rowId).value || 'Medicine';
        const pid      = document.getElementById('productId_' + rowId).value;

        let enteredQty = parseInt(qtyInput.value) || 0;

        if (pid && stockVal > 0 && enteredQty > stockVal) {
            alert("⚠️ Error: Only " + stockVal + " available for '" + prodName + "'!");
            qtyInput.value = stockVal;
        } else if (enteredQty < 0) {
            qtyInput.value = '';
        }
        recalculateAll();
    }

    function onMedSearchKeydown(e, rowId) {
        const dd = document.getElementById('medDropdown_' + rowId);
        if (!dd) return;
        if (e.key === 'Escape') dd.classList.add('d-none');
    }

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.product-search-container')) {
            document.querySelectorAll('.med-dropdown').forEach(dd => dd.classList.add('d-none'));
        }
    });

    function recalculateAll() {
        let grossTotal = 0;
        let itemsNetTotal = 0;
        const rows = itemsBody.querySelectorAll('tr');

        rows.forEach(row => {
            const qty   = parseFloat(row.querySelector('.qty-input').value) || 0;
            const tp    = parseFloat(row.querySelector('.tp-input').value) || 0;
            const disc  = parseFloat(row.querySelector('.disc-input').value) || 0;

            const lineGross = qty * tp;
            const lineNet = lineGross - (lineGross * (disc / 100));

            row.querySelector('.row-net').value = lineNet > 0 ? lineNet.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';

            grossTotal += lineGross;
            itemsNetTotal += lineNet;
        });

        const totalProductDiscs = grossTotal - itemsNetTotal;
        document.getElementById('itemsGrossDisplay').textContent = 'Rs. ' + grossTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('productDiscDisplay').textContent = '- Rs. ' + totalProductDiscs.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        const orderDiscPercent = parseFloat(document.getElementById('orderDiscount').value) || 0;
        const orderDiscountAmt = itemsNetTotal * (orderDiscPercent / 100);
        const afterOrderDisc   = itemsNetTotal - orderDiscountAmt;

        const roundOffVal = parseFloat(document.getElementById('roundOffValue').value) || 0;
        const currentBill = afterOrderDisc + roundOffVal;
        document.getElementById('currentBillInput').value = currentBill > 0 ? currentBill.toFixed(2) : '';

        const prevBalance = parseFloat(document.getElementById('previousBalanceInput').value) || 0;
        const netPayable = currentBill + prevBalance;
        document.getElementById('netPayableInput').value = netPayable > 0 ? netPayable.toFixed(2) : '';

        const paidAmount = parseFloat(document.getElementById('paidAmountInput').value) || 0;
        const balanceDue = netPayable - paidAmount;
        document.getElementById('balanceDueInput').value = balanceDue != 0 ? balanceDue.toFixed(2) : '';
    }

    itemsBody.addEventListener('input', recalculateAll);
    document.getElementById('orderDiscount').addEventListener('input', recalculateAll);
    document.getElementById('previousBalanceInput').addEventListener('input', recalculateAll);
    document.getElementById('paidAmountInput').addEventListener('input', recalculateAll);
    document.getElementById('roundOffValue').addEventListener('input', recalculateAll);

    addRowBtn.addEventListener('click', function () {
        addNewRow(null, true);
    });

    itemsBody.addEventListener('click', function (e) {
        const delBtn = e.target.closest('.remove-row');
        if (delBtn) {
            const rows = itemsBody.querySelectorAll('tr');
            if (rows.length > 1) {
                delBtn.closest('tr').remove();
                updateRowIndices();
                recalculateAll();
            } else {
                alert('Invoice me kam az kam ek medicine item hona zaroori hai.');
            }
        }
    });

    // Populate existing items
    if (existingItems && existingItems.length > 0) {
        existingItems.forEach(item => addNewRow(item));
    } else {
        addNewRow();
    }

    // Auto-select input text on focus to prevent having to backspace/delete
    document.addEventListener('focus', function(e) {
        if (e.target && (e.target.matches('input[type="number"]') || e.target.classList.contains('med-search-input'))) {
            e.target.select();
        }
    }, true);
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
