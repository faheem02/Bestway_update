<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

// AJAX endpoint for live product search from bestway_wholesale.products
if (isset($_GET['action']) && $_GET['action'] === 'search_product') {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    $res = [];
    if ($db_connected && $pdo) {
        $sql = "SELECT p.id, p.product_code, p.name, p.generic_name, 
                       COALESCE(NULLIF(p.trade_price, 0), (SELECT pi.trade_price FROM purchase_items pi WHERE pi.product_id = p.id AND pi.trade_price > 0 ORDER BY pi.id DESC LIMIT 1), (SELECT pi.purchase_price FROM purchase_items pi WHERE pi.product_id = p.id AND pi.purchase_price > 0 ORDER BY pi.id DESC LIMIT 1), p.purchase_price, p.retail_price, 0) as trade_price,
                       COALESCE(NULLIF(p.retail_price, 0), NULLIF(p.trade_price, 0), (SELECT pi.trade_price FROM purchase_items pi WHERE pi.product_id = p.id AND pi.trade_price > 0 ORDER BY pi.id DESC LIMIT 1), p.purchase_price, 0) as sale_price,
                       p.retail_price, p.purchase_price, p.current_stock, p.stock_unit, p.packs_per_box, 
                       p.discount_percent,
                       COALESCE(NULLIF(p.discount_percent, 0), (SELECT pi.discount_percent FROM purchase_items pi WHERE pi.product_id = p.id AND pi.discount_percent > 0 ORDER BY pi.id DESC LIMIT 1), 0) as default_discount,
                       c.name as company_name, u.name as base_unit_name 
                FROM products p 
                LEFT JOIN companies c ON c.id = p.company_id 
                LEFT JOIN units u ON u.id = p.unit_id 
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

// AJAX endpoint for live customer search
if (isset($_GET['action']) && $_GET['action'] === 'search_customer') {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    $area = trim($_GET['area'] ?? '');
    $res = [];
    if ($db_connected && $pdo) {
        $where = ["status = 'Active'"];
        $params = [];
        if ($area !== '') {
            $where[] = "LOWER(TRIM(area)) = LOWER(TRIM(:area))";
            $params[':area'] = $area;
        }
        if ($q === '') {
            $sql = "SELECT id, name, shop_name, phone, route_id, area, current_balance FROM customers WHERE " . implode(' AND ', $where) . " ORDER BY shop_name ASC, name ASC LIMIT 25";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $term = "%{$q}%";
            $where[] = "(shop_name LIKE :q1 OR name LIKE :q2 OR phone LIKE :q3)";
            $params[':q1'] = $term;
            $params[':q2'] = $term;
            $params[':q3'] = $term;
            $params[':exact'] = "{$q}%";
            $params[':start_n'] = "{$q}%";
            $sql = "SELECT id, name, shop_name, phone, route_id, area, current_balance FROM customers WHERE " . implode(' AND ', $where) . " ORDER BY CASE WHEN shop_name LIKE :exact THEN 1 WHEN name LIKE :start_n THEN 2 ELSE 3 END, shop_name ASC LIMIT 25";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode($res);
    exit;
}

$page_title = "Create Sales Invoice";
$compact_page_heading = true;
$hide_topbar_title = true;
require_once __DIR__ . '/../../includes/header.php';

// Database configuration for sales invoices
if (empty($conn) || $conn->connect_error) {
    $conn = @new mysqli($db_host ?? 'localhost', $db_user ?? 'root', $db_pass ?? '', $db_name ?? 'bestway_wholesale');
}


// 1. Auto-generate next invoice number starting from INV-0001
$next_invoice_no = "INV-0001";
try {
    $res = $conn->query("SELECT invoice_no FROM sales_invoices WHERE invoice_no REGEXP '^INV-[0-9]+$' ORDER BY id DESC LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        if (preg_match('/INV-(\d+)/i', $row['invoice_no'], $matches)) {
            $next_num = (int)$matches[1] + 1;
            $next_invoice_no = "INV-" . str_pad($next_num, 4, '0', STR_PAD_LEFT);
        }
    } else {
        $res2 = $conn->query("SELECT id FROM sales_invoices ORDER BY id DESC LIMIT 1");
        if ($res2 && $row2 = $res2->fetch_assoc()) {
            $next_num = (int)$row2['id'] + 1;
            $next_invoice_no = "INV-" . str_pad($next_num, 4, '0', STR_PAD_LEFT);
        }
    }
} catch (Exception $e) {}

// 2. Fetch active bookers, routes, accounts, customers & system products
$bookers = [];
$routes  = [];
$cash_accounts = [];
$bank_accounts = [];
$customers = [];
$initial_products = [];
$units = [];

if ($db_connected && $pdo) {
    try {
        $units = $pdo->query("SELECT id, name, short_name FROM units ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

        $bookers = getActiveBookers($pdo);

        $routes = $pdo->query("
            SELECT id, name, route_code, assigned_booker_id 
            FROM routes 
            WHERE status = 'Active'
            ORDER BY name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $cash_accounts = $pdo->query("SELECT id, account_name, balance FROM cash_accounts ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        $bank_accounts = $pdo->query("SELECT id, bank_name, account_title, account_number FROM bank_accounts WHERE status = 'Active' ORDER BY bank_name ASC")->fetchAll(PDO::FETCH_ASSOC);

        $customers = $pdo->query("SELECT id, name, shop_name, phone, route_id, area, current_balance FROM customers WHERE status = 'Active' ORDER BY shop_name ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);

        // ===== AREAS WORKFLOW (similar to Mehboob Traders) =====
        // Sirf registered active areas ko areas table se fetch karein (taake customer records ke typo ya cities show na hon)
        $areas_table = $pdo->query("SELECT name FROM areas WHERE status = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
        $all_areas_combined = array_values(array_filter($areas_table));
        sort($all_areas_combined, SORT_NATURAL | SORT_FLAG_CASE);

        // Salesmen covering areas
        $salesmen_all = $pdo->query("SELECT id, full_name, area FROM employees WHERE (employee_type = 'salesman' OR employee_type = 'order_booker') AND status = 1 ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
        function salesmenForAreaBestway($salesmen_all, $areaName) {
            $area_l = strtolower(trim($areaName));
            $out = [];
            foreach ($salesmen_all as $e) {
                $parts = array_map('strtolower', array_map('trim', explode(',', $e['area'] ?? '')));
                if (in_array($area_l, $parts, true)) $out[] = $e;
            }
            return $out;
        }

        $area_cards = [];
        foreach ($all_areas_combined as $an) {
            $cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE LOWER(TRIM(area)) = LOWER(TRIM(?))");
            $cnt_stmt->execute([$an]);
            $area_cards[] = [
                'name' => $an,
                'shops' => (int)$cnt_stmt->fetchColumn(),
                'salesmen' => salesmenForAreaBestway($salesmen_all, $an),
            ];
        }

        $view_area = trim($_GET['area'] ?? '');
        $preselected_customer = null;
        $preselected_customer_id = intval($_GET['customer_id'] ?? 0);
        if ($preselected_customer_id > 0) {
            $c_stmt = $pdo->prepare("SELECT id, name, shop_name, phone, route_id, area, current_balance, invoice_type FROM customers WHERE id = ? LIMIT 1");
            $c_stmt->execute([$preselected_customer_id]);
            $preselected_customer = $c_stmt->fetch(PDO::FETCH_ASSOC);
            if ($preselected_customer && empty($view_area) && !empty($preselected_customer['area'])) {
                $view_area = $preselected_customer['area'];
            }
        }

        $area_customers = [];
        $page_area_salesmen = [];
        if ($view_area !== '') {
            $cust_stmt = $pdo->prepare("SELECT id, name, shop_name, phone, area, current_balance, route_id FROM customers WHERE LOWER(TRIM(area)) = LOWER(TRIM(?)) ORDER BY shop_name ASC, name ASC");
            $cust_stmt->execute([$view_area]);
            $area_customers = $cust_stmt->fetchAll(PDO::FETCH_ASSOC);
            $page_area_salesmen = salesmenForAreaBestway($salesmen_all, $view_area);
        }

        $stmt_init_p = $pdo->query("
            SELECT 
                p.id, 
                p.product_code, 
                p.name, 
                p.generic_name, 
                COALESCE(NULLIF(p.trade_price, 0), (SELECT pi.trade_price FROM purchase_items pi WHERE pi.product_id = p.id AND pi.trade_price > 0 ORDER BY pi.id DESC LIMIT 1), (SELECT pi.purchase_price FROM purchase_items pi WHERE pi.product_id = p.id AND pi.purchase_price > 0 ORDER BY pi.id DESC LIMIT 1), p.purchase_price, p.retail_price, 0) as trade_price,
                p.retail_price, 
                p.purchase_price,
                p.current_stock, 
                p.stock_unit, 
                p.packs_per_box,
                p.discount_percent,
                COALESCE(NULLIF(p.discount_percent, 0), (SELECT pi.discount_percent FROM purchase_items pi WHERE pi.product_id = p.id AND pi.discount_percent > 0 ORDER BY pi.id DESC LIMIT 1), 0) as default_discount,
                c.name as company_name,
                u.name as base_unit_name
            FROM products p
            LEFT JOIN companies c ON c.id = p.company_id
            LEFT JOIN units u ON u.id = p.unit_id
            WHERE p.status = 'Active'
            ORDER BY p.name ASC
        ");
        $initial_products = $stmt_init_p->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

$saved_invoice_id = 0;
$saved_invoice_no = '';
$saved_customer_invoice_type = 'sale';

// NEW: Auto salesman — if the logged-in user is a salesman (employee_type='salesman'), 
// ALL sales they create are credited to them automatically (server-side, non-spoofable).
$auto_salesman = null;
if ($pdo && isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
    $auto_salesman = currentSalesmanForUser($pdo, (int)$_SESSION['user_id']);
}

// 3. Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $invoice_no    = trim($_POST['invoice_no'] ?? '') ?: $next_invoice_no;
    $party_type    = trim($_POST['billing_party_type'] ?? 'customer');
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_id   = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
    $invoice_date  = trim($_POST['invoice_date'] ?? date('Y-m-d'));
    $payment_terms  = trim($_POST['payment_terms'] ?? 'Cash');
    $payment_mode   = trim($_POST['payment_mode'] ?? 'Cash');
    $payment_method = 'Cash';
    $cash_account_id = null;
    $bank_account_id = null;

    if ($payment_mode === 'Cash' || str_starts_with($payment_mode, 'Cash_')) {
        $payment_method  = 'Cash';
        if (str_starts_with($payment_mode, 'Cash_')) {
            $cash_account_id = intval(substr($payment_mode, 5));
        } else {
            // Get default cash account
            try {
                $def_cash = $pdo->query("SELECT id FROM cash_accounts WHERE is_default = 1 LIMIT 1")->fetch();
                if (!$def_cash) $def_cash = $pdo->query("SELECT id FROM cash_accounts ORDER BY id ASC LIMIT 1")->fetch();
                $cash_account_id = $def_cash ? (int)$def_cash['id'] : null;
            } catch (Exception $e) { $cash_account_id = null; }
        }
    } elseif ($payment_mode === 'Bank' || str_starts_with($payment_mode, 'Bank_')) {
        $payment_method  = 'Bank';
        if (str_starts_with($payment_mode, 'Bank_')) {
            $bank_account_id = intval(substr($payment_mode, 5));
        } else {
            $bank_account_id = intval($_POST['bank_account_id_select'] ?? 0) ?: null;
        }
    } elseif ($payment_mode === 'Credit') {
        $payment_method  = 'Credit';
    } else {
        $payment_method  = $payment_mode;
    }
    $notes = trim($_POST['notes'] ?? '');

    $booker_id   = !empty($_POST['booker_id']) ? intval($_POST['booker_id']) : (!empty($_POST['visible_booker_id']) ? intval($_POST['visible_booker_id']) : null);
    $booker_name = trim($_POST['booker_name'] ?? '');
    $route_id_raw = trim($_POST['route_id'] ?? '');
    $route_id    = (is_numeric($route_id_raw) && intval($route_id_raw) > 0) ? intval($route_id_raw) : null;
    $route_name  = trim($_POST['route_name'] ?? '');
    if (empty($route_name) && str_starts_with($route_id_raw, 'area_')) {
        $route_name = substr($route_id_raw, 5);
    }

    // Auto-detect Salesman from Area / Route or Customer if not explicitly chosen
    if (empty($booker_id) && $pdo) {
        $check_area = strtolower(trim($route_name ?: ''));
        if (empty($check_area) && !empty($customer_id)) {
            try {
                $c_area_stmt = $pdo->prepare("SELECT area FROM customers WHERE id = ?");
                $c_area_stmt->execute([$customer_id]);
                $check_area = strtolower(trim($c_area_stmt->fetchColumn() ?: ''));
            } catch (Exception $e) {}
        }
        if (!empty($check_area) && !empty($salesmen_all)) {
            foreach ($salesmen_all as $sm) {
                $parts = array_map('strtolower', array_map('trim', explode(',', $sm['area'] ?? '')));
                if (in_array($check_area, $parts, true)) {
                    $booker_id   = (int)$sm['id'];
                    $booker_name = (string)$sm['full_name'];
                    break;
                }
            }
        }
    }

    // Booker and Customer handling
    // Salesman logged in → his own sales are ALWAYS credited to him (server-side override, ignores any spoofed booker fields)
    if ($auto_salesman) {
        $booker_id   = (int)$auto_salesman['id'];
        $booker_name = (string)$auto_salesman['full_name'];
    }

    if ($booker_id && empty($booker_name)) {
        foreach ($bookers as $b) {
            if ($b['id'] == $booker_id) { $booker_name = $b['name']; break; }
        }
        if (empty($booker_name) && !empty($salesmen_all)) {
            foreach ($salesmen_all as $sm) {
                if ($sm['id'] == $booker_id) { $booker_name = $sm['full_name']; break; }
            }
        }
    }

    if ($party_type === 'booker' || (empty($customer_name) && !empty($booker_id))) {
        // Salesman Order Mode (No customer chosen)
        $customer_name = !empty($booker_name) ? "Salesman: " . $booker_name : "Salesman Order";
        $customer_id   = null;
    }

    if ($route_id && empty($route_name)) {
        foreach ($routes as $r) {
            if ($r['id'] == $route_id) { $route_name = $r['name']; break; }
        }
    }

    $product_ids = (isset($_POST['product_id']) && is_array($_POST['product_id'])) ? $_POST['product_id'] : [];
    $item_names  = (isset($_POST['item_name']) && is_array($_POST['item_name'])) ? $_POST['item_name'] : [];
    $quantities  = (isset($_POST['quantity']) && is_array($_POST['quantity'])) ? $_POST['quantity'] : [];
    $unit_prices = (isset($_POST['unit_price']) && is_array($_POST['unit_price'])) ? $_POST['unit_price'] : [];
    $discounts   = (isset($_POST['disc_percent']) && is_array($_POST['disc_percent'])) ? $_POST['disc_percent'] : [];
    $extra_discs = (isset($_POST['extra_disc_percent']) && is_array($_POST['extra_disc_percent'])) ? $_POST['extra_disc_percent'] : [];

    if ($party_type === 'booker') {
        if (empty($booker_id)) {
            $message = "Please select a Salesman.";
            $msg_type = "danger";
        } elseif (empty($route_id)) {
            $message = "Selecting Area / Route is required for Salesman Orders.";
            $msg_type = "danger";
        }
    } else {
        if (empty($customer_name)) {
            $message = "Please select a Registered Customer.";
            $msg_type = "danger";
        }
        // When customer is selected, Area / Route is OPTIONAL
    }

    if (empty($message) && (empty($invoice_date) || empty($product_ids))) {
        $message = "Invoice date and at least one medicine item are required.";
        $msg_type = "danger";
    } else {
        try {
            // STEP 1: VALIDATE PRODUCTS & STOCK FROM SYSTEM (Zero-trust, Pcs based)
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
                    throw new Exception("Row #" . ($i + 1) . ": Please search and select a registered medicine from the system.");
                }

                if ($qty <= 0) {
                    throw new Exception("Row #" . ($i + 1) . ": Quantity must be at least 1.");
                }

                // Verify product and current stock directly from DB
                $stmt_chk = $pdo->prepare("SELECT id, product_code, name, current_stock, trade_price FROM products WHERE id = ?");
                $stmt_chk->execute([$pid]);
                $prod_row = $stmt_chk->fetch(PDO::FETCH_ASSOC);

                if (!$prod_row) {
                    throw new Exception("Row #" . ($i + 1) . ": Selected medicine does not exist in the system.");
                }

                $available_stock = intval($prod_row['current_stock']);
                $p_name          = $prod_row['name'];

                // Check 1: Is product in stock?
                if ($available_stock <= 0) {
                    throw new Exception("Error: Medicine '{$p_name}' is out of stock (Stock: 0)!");
                }

                // Check 2: Does requested qty exceed available stock?
                if ($qty > $available_stock) {
                    throw new Exception("Error: Available stock for '{$p_name}' is only {$available_stock} Pcs, but entered quantity is {$qty}.");
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

            if (empty($item_names) || !is_array($item_names)) {
                throw new Exception("Please include at least one product item.");
            }

            $items_net = $subtotal - $total_discount_cut;
            $order_discount_percent = floatval($_POST['order_discount_percent'] ?? 0);
            $order_discount_amount  = $items_net * ($order_discount_percent / 100);
            $total_discount_cut    += $order_discount_amount;

            $shipping_cost    = 0.00;
            $adjustment       = 0.00;
            $round_off        = floatval($_POST['round_off'] ?? 0);
            $grand_total      = ($items_net - $order_discount_amount) + $round_off;

            $previous_balance = floatval($_POST['previous_balance'] ?? 0);
            $net_payable      = $grand_total;
            $paid_amount      = floatval($_POST['paid_amount'] ?? 0);
            $balance_due      = max(0, $grand_total - $paid_amount);

            // STEP 2: START TRANSACTIONS
            $conn->begin_transaction();
            $pdo->beginTransaction();

            // 1. Insert parent invoice
            $stmt = $conn->prepare("INSERT INTO sales_invoices (invoice_no, customer_name, customer_id, invoice_date, payment_terms, payment_method, cash_account_id, bank_account_id, subtotal, discount_amount, discount_percent, shipping_cost, adjustment, round_off, grand_total, previous_balance, net_payable, paid_amount, balance_due, booker_id, booker_name, route_id, route_name, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssisssiidddddddddddisiss", 
                $invoice_no, $customer_name, $customer_id, $invoice_date, $payment_terms, $payment_method,
                $cash_account_id, $bank_account_id,
                $subtotal, $total_discount_cut, $order_discount_percent, $shipping_cost, $adjustment, $round_off, 
                $grand_total, $previous_balance, $net_payable, $paid_amount, $balance_due,
                $booker_id, $booker_name, $route_id, $route_name, $notes
            );
            $stmt->execute();
            $invoice_id = $conn->insert_id;
            $stmt->close();

            // 2. Insert items into sale_items AND DEDUCT STOCK
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
                    $invoice_id,
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

                // DEDUCT FROM PRODUCT STOCK
                $stmt_deduct_stock->execute([$qty, $pid]);
            }

// Update cash/bank balance if paid amount > 0
            if ($paid_amount > 0) {
                $book_desc = "Sale Invoice #{$invoice_no} - {$customer_name}";
                $user_id_book = $_SESSION['user_id'] ?? 1;
                if ($payment_method === 'Cash') {
                    if ($cash_account_id) {
                        $pdo->prepare("UPDATE cash_accounts SET balance = balance + ? WHERE id = ?")->execute([$paid_amount, $cash_account_id]);
                    }
                    recordCashInflow($pdo, $invoice_date, $paid_amount, $book_desc, 'sale', $invoice_id, $user_id_book);
                } elseif ($payment_method === 'Bank' && $bank_account_id) {
                    $pdo->prepare("UPDATE bank_accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$paid_amount, $bank_account_id]);
                    recordBankInflow($pdo, $invoice_date, $paid_amount, $book_desc, 'sale', $invoice_id, $user_id_book, $bank_account_id);
                }
            }

            // Save into Credit Book (Udhaar) if credit sale or has balance due
            $credit_amount = ($balance_due > 0) ? $balance_due : (($payment_method === 'Credit') ? $grand_total : 0);
            if ($credit_amount > 0 && ($payment_method === 'Credit' || $balance_due > 0)) {
                $desc = "Sales Invoice #{$invoice_no}";
                if ($paid_amount > 0) {
                    $desc .= " (Total: Rs. " . number_format($grand_total, 2) . ", Paid: Rs. " . number_format($paid_amount, 2) . ", Due: Rs. " . number_format($balance_due, 2) . ")";
                } else {
                    $desc .= " (Credit Sale)";
                }
                if (!empty($notes)) {
                    $desc .= " - " . $notes;
                }

                $stmt_u = $pdo->prepare("INSERT INTO udhaar_book (party_name, invoice_no, invoice_id, amount, type, credit_date, description) VALUES (?, ?, ?, ?, 'Given', ?, ?)");
                $stmt_u->execute([$customer_name, $invoice_no, $invoice_id, $credit_amount, $invoice_date, $desc]);
            }

            $conn->commit();
            $pdo->commit();

            // Create Delivery Challan automatically
            try { createDeliveryChallanForInvoice($pdo, $invoice_id); } catch (Exception $e) {}

            // Refresh customer balance from saved invoices
            if (!empty($customer_id)) {
                try { updateCustomerBalance($pdo, $customer_id); } catch (Exception $e) {}
            }

            $saved_invoice_id = $invoice_id;
            $saved_invoice_no = $invoice_no;
            $saved_customer_invoice_type = 'sale';
            if (!empty($customer_id)) {
                try {
                    $ct_q = $pdo->prepare("SELECT invoice_type FROM customers WHERE id = ? LIMIT 1");
                    $ct_q->execute([$customer_id]);
                    $c_type_val = $ct_q->fetchColumn();
                    if ($c_type_val && in_array($c_type_val, ['warranty', 'sale'], true)) {
                        $saved_customer_invoice_type = $c_type_val;
                    }
                } catch (Exception $e) {}
            } elseif (!empty($customer_name)) {
                try {
                    $ct_q = $pdo->prepare("SELECT invoice_type FROM customers WHERE name = ? OR shop_name = ? LIMIT 1");
                    $ct_q->execute([$customer_name, $customer_name]);
                    $c_type_val = $ct_q->fetchColumn();
                    if ($c_type_val && in_array($c_type_val, ['warranty', 'sale'], true)) {
                        $saved_customer_invoice_type = $c_type_val;
                    }
                } catch (Exception $e) {}
            }

            $message = "Sales Invoice ({$invoice_no}) saved successfully, stock updated" . (($credit_amount > 0) ? " and recorded in Credit Book (Udhaar)!" : "!");
            $msg_type = "success";

            // Update next sequence for display after submission
            if (preg_match('/INV-(\d+)/i', $invoice_no, $m_inv)) {
                $next_invoice_no = "INV-" . str_pad((int)$m_inv[1] + 1, 4, '0', STR_PAD_LEFT);
            }

            // Refresh initial products to reflect updated stock in UI
            $stmt_init_p = $pdo->query("
                SELECT 
                    p.id, 
                    p.product_code, 
                    p.name, 
                    p.generic_name, 
                    p.trade_price, 
                    p.retail_price, 
                    p.current_stock, 
                    p.stock_unit, 
                    p.packs_per_box,
                    c.name as company_name
                FROM products p
                LEFT JOIN companies c ON c.id = p.company_id
                WHERE p.status = 'Active'
                ORDER BY p.name ASC
            ");
            $initial_products = $stmt_init_p->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $conn->rollback();
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = $e->getMessage();
            $msg_type = "danger";
        }
    }
}

$show_sale_form = (!empty($preselected_customer) || isset($_GET['direct']) || $_SERVER['REQUEST_METHOD'] === 'POST' || !empty($saved_invoice_id));

?>

<!-- Clean Form & Table Styles Consistent with Purchase Module & Mehboob Traders -->
<style>
    /* Mehboob Traders Style Area Cards & Customers */
    .area-card { 
        border: 1px solid #dbe1ea; 
        border-radius: 12px; 
        padding: 20px 18px; 
        transition: all .2s ease; 
        background: #ffffff; 
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        position: relative;
        overflow: hidden;
    }
    .area-card:hover { 
        border-color: #4e73df; 
        box-shadow: 0 8px 24px rgba(78, 115, 223, 0.18); 
        transform: translateY(-3px); 
    }
    .area-card-icon { 
        width: 46px; 
        height: 46px; 
        border-radius: 12px; 
        background: rgba(78, 115, 223, 0.12); 
        color: #4e73df; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        margin-bottom: 12px; 
        font-size: 1.3rem; 
    }
    .area-card-name { 
        font-weight: 700; 
        font-size: 1.12rem; 
        color: #1e293b; 
        margin-bottom: 4px; 
    }
    .area-card-meta { 
        color: #64748b; 
        font-size: 0.88rem; 
        margin-bottom: 8px; 
    }
    .area-card-sales { 
        display: flex; 
        flex-wrap: wrap; 
        gap: 4px; 
    }
    .badge-lg { 
        font-size: 0.95rem; 
        padding: .5em .8em; 
    }
    .customer-table-row {
        cursor: pointer;
        transition: background 0.15s ease;
    }
    .customer-table-row:hover {
        background-color: #f0f7ff !important;
    }

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
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="font-weight-bold text-dark mb-1">
            <i class="fas fa-file-invoice-dollar text-primary mr-2"></i> Create Sales Invoice
        </h4>
    </div>
    <div>
        <a href="sales.php" class="btn btn-sm btn-outline-secondary font-weight-bold shadow-sm">
            <i class="fas fa-list mr-1"></i> View All Sales
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

<?php if (!$show_sale_form): ?>
    <?php if ($view_area === ''): ?>
        <!-- ==============================================
             STEP 1: ALL AREAS GRID (like Mehboob Traders)
             ============================================== -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-wrap justify-content-between align-items-center bg-white border-bottom">
                <div>
                    <h5 class="mb-0 font-weight-bold text-primary">
                        <i class="fas fa-clipboard-check mr-2"></i> Book Sale / Order
                    </h5>
                    <small class="text-muted">Select an area to view registered shops and start booking</small>
                </div>
                <div class="d-flex flex-wrap align-items-center mt-2 mt-sm-0">
                    <a href="new_sale.php?direct=1" class="btn btn-sm btn-primary font-weight-bold mr-2 shadow-sm">
                        <i class="fas fa-bolt mr-1"></i> Direct Sale / Invoice
                    </a>
                    <a href="sales.php" class="btn btn-sm btn-outline-secondary font-weight-bold mr-2">
                        <i class="fas fa-list mr-1"></i> Invoices
                    </a>
                    <a href="../areas/index.php" class="btn btn-sm btn-outline-info font-weight-bold">
                        <i class="fas fa-map-marked-alt mr-1"></i> Manage Areas
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3 align-items-center">
                    <div class="col-md-5 col-sm-6 mb-2 mb-sm-0">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                            </div>
                            <input type="text" id="areaFilterInput" class="form-control" placeholder="Search area or salesman..." autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-7 col-sm-6 text-sm-right text-muted small">
                        <i class="fas fa-map-marker-alt text-primary mr-1"></i> <span id="areaCount" class="font-weight-bold"><?= count($area_cards) ?></span> area<?= count($area_cards) == 1 ? '' : 's' ?> available
                    </div>
                </div>

                <?php if (empty($area_cards)): ?>
                    <div class="alert alert-warning text-center py-4 mb-0">
                        <i class="fas fa-exclamation-triangle fa-2x mb-2 d-block text-warning"></i>
                        No areas found. Add areas in <a href="../areas/index.php" class="alert-link font-weight-bold">Areas Module</a> or assign areas to customers.
                    </div>
                <?php else: ?>
                    <div class="row" id="areaCardsContainer">
                        <?php foreach ($area_cards as $ac): ?>
                            <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3 area-col" data-area="<?= htmlspecialchars(strtolower($ac['name'])) ?>">
                                <a href="new_sale.php?area=<?= urlencode($ac['name']) ?>" class="area-card d-block h-100 text-decoration-none">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="area-card-icon"><i class="fas fa-map-marked-alt"></i></div>
                                        <span class="badge badge-light border text-primary font-weight-bold"><?= $ac['shops'] ?> <?= $ac['shops'] == 1 ? 'Customer' : 'Customers' ?></span>
                                    </div>
                                    <div class="area-card-name"><?= htmlspecialchars($ac['name']) ?></div>
                                    <div class="area-card-meta"><i class="fas fa-store mr-1 text-muted"></i><?= $ac['shops'] ?> <?= $ac['shops'] == 1 ? 'shop / customer' : 'shops / customers' ?></div>
                                    <?php if (!empty($ac['salesmen'])): ?>
                                        <div class="area-card-sales mt-2">
                                            <?php foreach ($ac['salesmen'] as $sm): ?>
                                                <span class="badge badge-light border text-dark mr-1 mb-1"><i class="fas fa-user-tie text-primary mr-1"></i><?= htmlspecialchars($sm['full_name']) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div id="noAreaFound" class="alert alert-light border text-center py-4 d-none">
                        <i class="fas fa-search text-muted fa-2x mb-2 d-block"></i>
                        <span class="text-muted">No areas match your search.</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- ==============================================================
             STEP 2: CUSTOMERS IN SELECTED AREA (like Mehboob Traders)
             ============================================== -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-wrap justify-content-between align-items-center bg-white border-bottom">
                <div class="d-flex align-items-center flex-wrap mb-2 mb-md-0">
                    <a href="new_sale.php" class="btn btn-sm btn-outline-secondary font-weight-bold mr-3">
                        <i class="fas fa-undo mr-1"></i> All Areas
                    </a>
                    <span class="badge badge-primary badge-lg mr-2">
                        <i class="fas fa-map-marker-alt mr-1"></i> Area: <?= htmlspecialchars($view_area) ?>
                    </span>
                    <span class="badge badge-light border text-dark font-weight-bold mr-2">
                        <span id="customerVisibleCount"><?= count($area_customers) ?></span> customer<?= count($area_customers) == 1 ? '' : 's' ?>
                    </span>
                    <?php foreach ($page_area_salesmen as $sm): ?>
                        <span class="badge badge-light border text-dark mr-1"><i class="fas fa-user-tie text-primary mr-1"></i> Salesman: <?= htmlspecialchars($sm['full_name']) ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="d-flex flex-wrap align-items-center">
                    <a href="new_sale.php?area=<?= urlencode($view_area) ?>&direct=1" class="btn btn-sm btn-outline-primary font-weight-bold mr-2">
                        <i class="fas fa-bolt mr-1"></i> Direct Invoice in this Area
                    </a>
                    <a href="../customer/customers.php" class="btn btn-sm btn-outline-success font-weight-bold">
                        <i class="fas fa-user-plus mr-1"></i> Add Customer
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3 align-items-center">
                    <div class="col-md-5 col-sm-6 mb-2 mb-sm-0">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                            </div>
                            <input type="text" id="shopSearch" class="form-control" placeholder="Search customer name, shop or phone..." autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-7 col-sm-6 text-sm-right text-muted small">
                        Click on any customer or the <strong>Order</strong> button to open sales invoice
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="shopTable">
                        <thead class="bg-light text-primary">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Customer / Shop Name</th>
                                <th>Phone</th>
                                <th class="text-right">Current Balance</th>
                                <th class="text-center" style="width: 140px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($area_customers)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-5">
                                        <i class="fas fa-store-slash fa-2x mb-2 d-block text-muted"></i>
                                        No customers registered in <strong><?= htmlspecialchars($view_area) ?></strong> yet.
                                        <div class="mt-2">
                                            <a href="../customer/customers.php" class="btn btn-sm btn-success font-weight-bold"><i class="fas fa-user-plus mr-1"></i> Add New Customer</a>
                                            <a href="new_sale.php" class="btn btn-sm btn-outline-secondary ml-2 font-weight-bold"><i class="fas fa-undo mr-1"></i> Back to Areas</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $i = 0; foreach ($area_customers as $ct): $i++; ?>
                                    <?php $bal = (float)($ct['current_balance'] ?? 0); ?>
                                    <tr class="customer-table-row" onclick="window.location.href='new_sale.php?area=<?= urlencode($view_area) ?>&customer_id=<?= $ct['id'] ?>'">
                                        <td class="text-center font-weight-bold text-muted align-middle"><?= $i ?></td>
                                        <td>
                                            <strong class="text-dark d-block" style="font-size: 1rem;">
                                                <i class="fas fa-hospital text-primary mr-1"></i> <?= htmlspecialchars($ct['shop_name'] ?: $ct['name']) ?>
                                            </strong>
                                            <?php if (!empty($ct['shop_name']) && !empty($ct['name']) && $ct['shop_name'] !== $ct['name']): ?>
                                                <small class="text-muted"><i class="fas fa-user mr-1"></i><?= htmlspecialchars($ct['name']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle">
                                            <?= !empty($ct['phone']) ? '<i class="fas fa-phone-alt text-muted mr-1"></i>' . htmlspecialchars($ct['phone']) : '<span class="text-muted">-</span>' ?>
                                        </td>
                                        <td class="text-right align-middle font-weight-bold <?= $bal > 0 ? 'text-danger' : 'text-success' ?>">
                                            Rs. <?= number_format($bal, 2) ?>
                                        </td>
                                        <td class="text-center align-middle" onclick="event.stopPropagation();">
                                            <a href="new_sale.php?area=<?= urlencode($view_area) ?>&customer_id=<?= $ct['id'] ?>" class="btn btn-sm btn-success font-weight-bold shadow-sm px-3">
                                                <i class="fas fa-cart-plus mr-1"></i> Order
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php else: ?>
    <!-- ==============================================================
         STEP 3: SALE INVOICE WINDOW (Existing form with Customer prefilled)
         ============================================== -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 bg-white p-3 rounded border shadow-sm">
        <div class="d-flex align-items-center flex-wrap">
            <a href="new_sale.php" class="btn btn-sm btn-outline-secondary font-weight-bold mr-2 mb-1 mb-sm-0">
                <i class="fas fa-undo mr-1"></i> All Areas
            </a>
            <?php if (!empty($view_area)): ?>
                <a href="new_sale.php?area=<?= urlencode($view_area) ?>" class="btn btn-sm btn-outline-primary font-weight-bold mr-2 mb-1 mb-sm-0">
                    <i class="fas fa-users mr-1"></i> <?= htmlspecialchars($view_area) ?> Customers
                </a>
            <?php endif; ?>
            <?php if ($preselected_customer): ?>
                <span class="badge badge-success badge-lg py-2 px-3 font-weight-bold mb-1 mb-sm-0 shadow-sm">
                    <i class="fas fa-check-circle mr-1"></i> <?= htmlspecialchars($preselected_customer['shop_name'] ?: $preselected_customer['name']) ?>
                    <?php if (!empty($preselected_customer['area'])): ?>
                        <span class="ml-1 opacity-75">(<?= htmlspecialchars($preselected_customer['area']) ?>)</span>
                    <?php endif; ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="mt-2 mt-sm-0">
            <a href="sales.php" class="btn btn-sm btn-outline-secondary font-weight-bold">
                <i class="fas fa-list mr-1"></i> View All Sales
            </a>
        </div>
    </div>

    <!-- Main Invoice Form -->
    <form action="" method="POST" id="saleForm" class="needs-validation" novalidate>
        <!-- 1. Customer & Routing Details Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-store mr-2"></i> Customer & Routing Information
                </h6>
            </div>
            <div class="card-body">
                <?php 
                $initial_booker_id = '';
                $initial_booker_name = '';
                if (!empty($page_area_salesmen)) {
                    foreach ($page_area_salesmen as $pas) {
                        foreach ($bookers as $sb) {
                            if (strtolower(trim($pas['full_name'])) === strtolower(trim($sb['name'])) || (int)$pas['id'] === (int)$sb['id']) {
                                $initial_booker_id = (int)$sb['id'];
                                $initial_booker_name = $sb['name'];
                                break 2;
                            }
                        }
                    }
                }
                ?>
                <input type="hidden" name="billing_party_type" value="customer">
                <input type="hidden" name="booker_id" id="bookerSelect" value="<?= $initial_booker_id ?>">
                <input type="hidden" name="booker_name" id="bookerNameInput" value="<?= htmlspecialchars($initial_booker_name) ?>">

                <?php if (!$auto_salesman): ?>
                    <!-- Visible Salesman attribution: admin selects which salesman this sale belongs to (commission credited to them) -->
                    <div class="row mb-3">
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label font-weight-bold small mb-1">
                                <i class="fas fa-user-tie text-primary mr-1"></i> Salesman
                                <span id="salesmanAreaBadge" class="badge badge-light border text-primary ml-1 <?= !empty($view_area) ? '' : 'd-none' ?>">Area: <span id="salesmanAreaText"><?= htmlspecialchars($view_area) ?></span></span>
                            </label>
                            <select name="visible_booker_id" id="visibleSalesmanSelect" class="form-control font-weight-bold" onchange="syncVisibleSalesman(this)">
                                <option value="">-- Direct / Office (No Salesman) --</option>
                                <?php 
                                $filtered_bookers = $bookers;
                                if (!empty($view_area)) {
                                    $filtered_bookers = array_filter($bookers, function($b) use ($view_area) {
                                        $parts = array_map('strtolower', array_map('trim', explode(',', $b['area'] ?? '')));
                                        return in_array(strtolower(trim($view_area)), $parts, true);
                                    });
                                }
                                ?>
                                <?php if (!empty($view_area) && empty($filtered_bookers)): ?>
                                    <option value="" disabled>-- No salesman allocated to <?= htmlspecialchars($view_area) ?> --</option>
                                <?php endif; ?>
                                <?php foreach ($filtered_bookers as $sb): ?>
                                    <?php 
                                    $is_sel = ($initial_booker_id && (int)$sb['id'] === (int)$initial_booker_id);
                                    if (!$is_sel && count($filtered_bookers) === 1 && !empty($view_area)) {
                                        $is_sel = true;
                                        $initial_booker_id = (int)$sb['id'];
                                        $initial_booker_name = $sb['name'];
                                    }
                                    ?>
                                    <option value="<?= (int)$sb['id'] ?>" data-booker="<?= htmlspecialchars($sb['name']) ?>" data-areas="<?= htmlspecialchars($sb['area'] ?? '') ?>" <?= $is_sel ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sb['name']) ?><?= !empty($sb['commission_rate']) ? ' (' . rtrim(rtrim(number_format((float)$sb['commission_rate'], 2, '.', ''), '0'), '.') . '%)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Row 1: Customer Name, Invoice #, Date -->
                <div class="row">
                    <div class="col-md-5 mb-3">
                        <label class="form-label font-weight-bold small mb-1" id="customerLabel">
                            Customer Name <span class="text-danger">*</span>
                        </label>
                        <div class="position-relative" id="customerSearchContainer">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-white"><i class="fas fa-hospital text-muted"></i></span>
                                </div>
                                <input type="text"
                                       name="customer_name"
                                       id="customerSearchInput"
                                       class="form-control font-weight-bold"
                                       placeholder="<?= !empty($view_area) ? 'Search ' . htmlspecialchars($view_area) . ' customer / shop...' : 'Type shop name or customer to search...' ?>"
                                       autocomplete="off"
                                       value="<?= htmlspecialchars($preselected_customer ? ($preselected_customer['shop_name'] ?: $preselected_customer['name']) : '') ?>"
                                       required
                                       onfocus="onCustomerSearchFocus()"
                                       oninput="onCustomerSearchInput()"
                                       onkeydown="onCustomerSearchKeydown(event)">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary <?= $preselected_customer ? '' : 'd-none' ?>" id="customerClearBtn" onclick="clearCustomerSelection()" title="Clear selection">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" name="customer_id" id="customerIdInput" value="<?= $preselected_customer ? $preselected_customer['id'] : '' ?>">
                            <!-- Live Customer Results Dropdown -->
                            <div id="customerDropdown" class="search-dropdown-menu d-none">
                                <div id="customerResultsList"></div>
                            </div>
                        </div>
                        <div class="small text-muted mt-1 d-flex flex-wrap justify-content-between align-items-center" id="customerBalText">
                            <span>Current Balance: <strong class="text-danger font-weight-bold" id="customerBalDisplay">Rs. <?= $preselected_customer ? number_format((float)$preselected_customer['current_balance'], 2) : '0.00' ?></strong></span>
                            <span id="customerAreaBadge" class="badge badge-light text-primary border <?= (!empty($preselected_customer['area'])) ? '' : 'd-none' ?>"><i class="fas fa-map-marker-alt mr-1"></i><span id="customerAreaText"><?= htmlspecialchars($preselected_customer['area'] ?? '') ?></span></span>
                        </div>
                    </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label font-weight-bold small mb-1">
                        Invoice Number <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-light"><i class="fas fa-receipt text-muted"></i></span>
                        </div>
                        <input type="text" name="invoice_no" class="form-control font-weight-bold bg-light text-primary font-monospace" value="<?= htmlspecialchars($next_invoice_no) ?>" readonly required>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <label class="form-label font-weight-bold small mb-1">
                        Invoice Date <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white"><i class="far fa-calendar-alt text-muted"></i></span>
                        </div>
                        <input type="date" name="invoice_date" id="invoiceDate" class="form-control font-weight-bold" required>
                    </div>
                </div>
            </div>

            <!-- Row 2: Area / Route, Payment Account -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label font-weight-bold small mb-1" id="routeLabel">
                        <i class="fas fa-map-marked-alt text-info mr-1"></i> Area / Route
                        <span class="badge badge-light border text-muted ml-1" id="routeStatusBadge">Optional</span>
                    </label>
                    <select name="route_id" id="routeSelect" class="form-control font-weight-bold" onchange="onAreaRouteSelectChange(this)">
                        <option value="">-- Choose Area / Route (Optional) --</option>
                        <?php if (!empty($all_areas_combined)): ?>
                            <optgroup label="Registered Areas">
                                <?php foreach ($all_areas_combined as $ca): ?>
                                    <?php $is_sel_area = (!empty($view_area) && strtolower(trim($ca)) === strtolower(trim($view_area))); ?>
                                    <option value="area_<?= htmlspecialchars($ca) ?>" 
                                            data-route-name="<?= htmlspecialchars($ca) ?>"
                                            <?= $is_sel_area ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($ca) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <?php if (!empty($routes)): ?>
                            <optgroup label="Defined Routes">
                                <?php foreach ($routes as $r): ?>
                                    <?php $is_sel_r = (!empty($view_area) && strtolower(trim($r['name'])) === strtolower(trim($view_area))); ?>
                                    <option value="<?= $r['id'] ?>" 
                                            data-route-name="<?= htmlspecialchars($r['name']) ?>"
                                            <?= $is_sel_r ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r['name']) ?> (<?= htmlspecialchars($r['route_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                    <input type="hidden" name="route_name" id="routeNameInput" value="<?= htmlspecialchars($view_area) ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label font-weight-bold small mb-1">
                        <i class="fas fa-wallet text-success mr-1"></i> Payment Method <span class="text-danger">*</span>
                    </label>
                    <select name="payment_mode" id="paymentModeSelect" class="form-control font-weight-bold" required onchange="toggleSalePaymentFields()">
                        <option value="Cash" selected>Cash (Immediate Payment)</option>
                        <option value="Bank">Bank Transfer</option>
                        <option value="Credit">Credit (Udhaar)</option>
                    </select>
                </div>

                <!-- Bank Account Field (shown only when Bank is selected) -->
                <div class="col-md-6 mb-3 d-none" id="saleBankAccountBox">
                    <label class="form-label font-weight-bold small mb-1">
                        <i class="fas fa-university text-primary mr-1"></i> Bank Account
                    </label>
                    <?php if (!empty($bank_accounts)): ?>
                        <select name="bank_account_id_select" id="saleBankAccountSelect" class="form-control font-weight-bold">
                            <option value="">-- Select Bank Account --</option>
                            <?php foreach ($bank_accounts as $ba): ?>
                                <option value="<?= $ba['id'] ?>" <?= $ba === reset($bank_accounts) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ba['bank_name']) ?> — <?= htmlspecialchars($ba['account_title']) ?> (<?= htmlspecialchars($ba['account_number']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <div class="alert alert-warning py-2 mb-0">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            Koi bank account nahi mila.
                            <a href="<?= BASE_URL ?>modules/bankbook/index.php" target="_blank" class="font-weight-bold">Bank Account Add Karen &rarr;</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Product Items Table Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-wrap justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-pills mr-2"></i> Medicine & Product Billing Items
            </h6>
            <button type="button" class="btn btn-sm btn-primary font-weight-bold" onclick="addNewRow(true)">
                <i class="fas fa-plus mr-1"></i> Add Product Item
            </button>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 align-middle items-table" id="saleItemsTable">
                    <thead>
                        <tr>
                            <th style="width: 45px;" class="text-center">#</th>
                            <th style="min-width: 280px;">Medicine / Product Name <span class="text-danger">*</span></th>
                            <th style="width: 110px;" class="text-center">Quantity (Pcs) <span class="text-danger">*</span></th>
                            <th style="width: 130px;" class="text-right">Price (Rs.) <span class="text-danger">*</span></th>
                            <th style="width: 95px;" class="text-center">Discount %</th>
                            <th style="width: 140px;" class="text-right">Net Total (Rs.)</th>
                            <th style="width: 45px;" class="text-center"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <!-- Dynamic rows populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 3. Bottom Row: Summary & Notes (Left) + Calculation & Checkout (Right) -->
    <div class="row">
        <!-- Left: Summary Counts & Remarks -->
        <div class="col-lg-7 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-info-circle mr-2"></i> Sale Summary & Notes
                    </h6>
                </div>
                <div class="card-body">
                    <div class="p-3 bg-light rounded mb-3 border">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small font-weight-bold">Total Invoice Items:</span>
                            <strong class="font-weight-bold text-dark" id="totalItemsCountBadge">0 item(s)</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small font-weight-bold">Items Gross Subtotal:</span>
                            <strong class="font-weight-bold text-dark font-monospace" id="itemsGrossDisplay">Rs. 0.00</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small font-weight-bold">Total Product Discounts:</span>
                            <strong class="font-weight-bold text-danger font-monospace" id="productDiscDisplay">- Rs. 0.00</strong>
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label class="form-label font-weight-bold text-muted small">
                            <i class="fas fa-sticky-note mr-1 text-primary"></i> Invoice Remarks / Notes
                        </label>
                        <textarea name="notes" id="saleNotes" rows="4" class="form-control" placeholder="Optional delivery notes, instructions or customer remarks..."></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Calculation Box & Checkout -->
        <div class="col-lg-5 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-calculator mr-2"></i> Bill Summary & Checkout
                    </h6>
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <div class="calc-box mb-3">
                        <div class="mb-2">
                            <label class="small font-weight-bold text-muted mb-1">Discount (%):</label>
                            <input type="number" step="0.01" min="0" max="100" name="order_discount_percent" id="orderDiscount" class="form-control form-control-sm text-right font-weight-bold" placeholder="0.00" value="" onfocus="this.select()">
                        </div>

                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="roundOffCheck" name="is_round_off">
                                <label class="custom-control-label small font-weight-bold text-muted" for="roundOffCheck" style="cursor: pointer;">Round Off</label>
                            </div>
                            <input type="number" step="0.01" name="round_off" id="roundOffValue" class="form-control form-control-sm text-right font-weight-bold" style="width: 100px;" placeholder="0.00" onfocus="this.select()">
                        </div>

                        <div class="calc-row grand-total mb-2">
                            <span>Current Bill:</span>
                            <span id="dispCurrentBill">Rs. 0.00</span>
                            <input type="hidden" name="grand_total" id="currentBillInput" value="0.00">
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2 py-2 px-2 bg-light rounded border">
                            <span class="small font-weight-bold text-muted"><i class="fas fa-wallet mr-1 text-info"></i> Current Balance:</span>
                            <strong class="font-weight-bold text-danger font-monospace" id="summaryCustomerBalance">Rs. <?= $preselected_customer ? number_format((float)$preselected_customer['current_balance'], 2) : '0.00' ?></strong>
                            <input type="hidden" name="previous_balance" id="previousBalanceInput" value="0.00">
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2 pt-2 border-top">
                            <span class="font-weight-bold text-success">Net Payable:</span>
                            <strong class="text-success font-monospace" style="font-size: 1.15rem;" id="dispNetPayable">Rs. 0.00</strong>
                            <input type="hidden" name="net_payable" id="netPayableInput" value="0.00">
                        </div>

                        <div class="mb-2">
                            <label class="small font-weight-bold text-dark mb-1">Paid Amount (Rs.):</label>
                            <input type="number" step="0.01" min="0" name="paid_amount" id="paidAmountInput" class="form-control form-control-sm text-right font-weight-bold text-dark" placeholder="0.00" value="" onfocus="this.select()">
                        </div>

                        <div class="calc-row balance-due mb-0">
                            <span>Balance Due:</span>
                            <span id="dispBalanceDue">Rs. 0.00</span>
                            <input type="hidden" name="balance_due" id="balanceDueInput" value="0.00">
                        </div>
                    </div>

                    <div>
                        <button type="submit" class="btn btn-primary btn-block font-weight-bold py-2 shadow-sm" id="btnSaveSale">
                            <i class="fas fa-save mr-1"></i> SAVE SALES INVOICE
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
<?php endif; ?>

<!-- Live Product Search, Dynamic Stock Verification & Calculation Script -->
<script>
    // Initialize system products & units catalog from server
    const systemProducts = <?= json_encode($initial_products) ?>;
    let systemUnits = <?= json_encode($units) ?>;
    let rowCounter = 0;

    // Helper: Get Unit Options HTML from System Units
    function getUnitOptionsHtml(selectedUnit = 'Pack') {
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

    // Initialize date picker
    const invoiceDateField = document.getElementById('invoiceDate');
    if (invoiceDateField && !invoiceDateField.value) {
        invoiceDateField.valueAsDate = new Date();
    }

    const itemsBody = document.getElementById('itemsBody');
    const addRowBtn = document.getElementById('addRowBtn');

    // Create a new table row with searchable product dropdown
    function createRow(rowId) {
        const tr = document.createElement('tr');
        tr.id = 'row_' + rowId;
        tr.className = 'sale-item-row';
        tr.innerHTML = `
            <td class="text-center font-weight-bold text-muted small align-middle">
                <span class="row-index">${rowId}</span>
            </td>
            <td>
                <div class="product-search-container" id="searchContainer_${rowId}">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white"><i class="fas fa-pills text-muted"></i></span>
                        </div>
                        <input type="text" 
                               class="form-control font-weight-bold med-search-input" 
                               id="medSearch_${rowId}" 
                               placeholder="Search medicine name..." 
                               autocomplete="off"
                               onfocus="onMedSearchFocus(${rowId})"
                               oninput="onMedSearchInput(${rowId})"
                               onkeydown="onMedSearchKeydown(event, ${rowId})"
                               required>
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-secondary d-none med-clear-btn" id="medClearBtn_${rowId}" onclick="clearProductRow(${rowId})" title="Clear selection">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="product_id[]" id="productId_${rowId}" class="product-id-input" value="" required>
                    <input type="hidden" name="item_name[]" id="itemName_${rowId}" class="item-name-input" value="" required>
                    <input type="hidden" id="stockAvailable_${rowId}" class="stock-available-input" value="0">
                    <input type="hidden" id="baseTp_${rowId}" value="0.00">

                    <!-- Stock and product details info pill -->
                    <div class="product-info-pill mt-1 d-none" id="productInfoPill_${rowId}">
                        <span class="stock-pill" id="stockPillBadge_${rowId}"></span>
                        <span class="text-muted small ml-1" id="productSubInfo_${rowId}"></span>
                    </div>

                    <!-- Live search dropdown menu -->
                    <div class="search-dropdown-menu med-dropdown d-none" id="medDropdown_${rowId}">
                        <div class="med-results-list" id="medResultsList_${rowId}"></div>
                    </div>
                </div>
            </td>
            <td>
                <input type="number" name="quantity[]" id="qty_${rowId}" class="form-control qty-input text-center font-weight-bold" min="1" placeholder="1" value="" onfocus="this.select()" oninput="onQtyChange(${rowId})" required>
            </td>
            <td>
                <input type="number" step="0.01" min="0" name="unit_price[]" id="tp_${rowId}" class="form-control tp-input text-right font-weight-bold" placeholder="0.00" onfocus="this.select()" required>
            </td>
            <td>
                <input type="number" step="0.1" min="0" max="100" name="disc_percent[]" id="disc_${rowId}" class="form-control disc-input text-center" placeholder="0.00" value="" onfocus="this.select()">
            </td>
            <td>
                <input type="text" class="form-control font-weight-bold row-net text-right font-monospace bg-light" id="rowNet_${rowId}" placeholder="0.00" value="" readonly>
            </td>
            <td class="text-center align-middle">
                <button type="button" class="btn-remove-row remove-row" title="Delete Row">
                    <i class="fas fa-times"></i>
                </button>
            </td>
        `;
        return tr;
    }

    function addNewRow(focusSearch = false) {
        rowCounter++;
        const newTr = createRow(rowCounter);
        itemsBody.appendChild(newTr);
        updateRowIndices();
        recalculateAll();

        if (focusSearch) {
            const input = document.getElementById('medSearch_' + rowCounter);
            if (input) {
                input.focus();
            }
        }
    }

    // Update row numbers (1, 2, 3...)
    function updateRowIndices() {
        const rows = itemsBody.querySelectorAll('tr');
        rows.forEach((row, idx) => {
            const indexEl = row.querySelector('.row-index');
            if (indexEl) indexEl.textContent = (idx + 1);
        });
        const badge = document.getElementById('totalItemsCountBadge');
        if (badge) badge.textContent = rows.length + ' item(s)';
    }

    // Product Search and Autocomplete Engine
    const medSearchTimers = {};
    const currentMedIdxs = {};

    function onMedSearchFocus(rowId) {
        const input = document.getElementById('medSearch_' + rowId);
        searchProductsForRow(rowId, input ? input.value.trim() : '');
    }

    function onMedSearchInput(rowId) {
        const input = document.getElementById('medSearch_' + rowId);
        const q = input ? input.value.trim() : '';
        if (!q) {
            clearProductRowDataOnly(rowId);
        }
        searchProductsForRow(rowId, q);
    }

    function searchProductsForRow(rowId, query) {
        const dd = document.getElementById('medDropdown_' + rowId);
        if (!dd) return;
        currentMedIdxs[rowId] = -1;

        // 1. Instant local catalog filter
        const qLower = query.toLowerCase();
        const localMatches = systemProducts.filter(p => {
            if (!query) return true;
            return (p.name && p.name.toLowerCase().includes(qLower)) ||
                   (p.product_code && p.product_code.toLowerCase().includes(qLower)) ||
                   (p.generic_name && p.generic_name.toLowerCase().includes(qLower)) ||
                   (p.company_name && p.company_name.toLowerCase().includes(qLower));
        }).slice(0, 30);

        renderProductList(rowId, localMatches, query);
        dd.classList.remove('d-none');

        // 2. Live AJAX query for any dynamic or newly added DB products
        clearTimeout(medSearchTimers[rowId]);
        medSearchTimers[rowId] = setTimeout(() => {
            fetch('new_sale.php?action=search_product&q=' + encodeURIComponent(query))
                .then(r => r.json())
                .then(data => {
                    if (Array.isArray(data)) {
                        renderProductList(rowId, data, query);
                    }
                })
                .catch(() => {});
        }, 150);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function highlightMatch(text, query) {
        if (!text) return '';
        if (!query) return escapeHtml(text);
        const escapedQuery = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const regex = new RegExp('(' + escapedQuery + ')', 'gi');
        return escapeHtml(text).replace(regex, '<span class="highlight-match">$1</span>');
    }

    // ---------------------------------------------------------
    // CUSTOMER & SALESMAN AREA FILTERING FUNCTIONALITY
    // ---------------------------------------------------------
    let currentActiveArea = <?= json_encode($view_area) ?> || '';
    const allBookers = <?= json_encode($bookers) ?: '[]' ?>;
    const customersCatalog = <?= json_encode($customers) ?: '[]' ?>;
    let customerSearchTimer = null;
    let currentCustomerIdx  = -1;
    let selectedCustomer    = null;

    function salesmanCoversArea(salesmanAreaStr, targetArea) {
        if (!targetArea) return true;
        if (!salesmanAreaStr) return false;
        const target = targetArea.trim().toLowerCase();
        const parts = salesmanAreaStr.toLowerCase().split(',').map(s => s.trim());
        return parts.includes(target);
    }

    function filterSalesmenByArea(areaName) {
        const sel = document.getElementById('visibleSalesmanSelect');
        if (!sel) return;

        const currentVal = sel.value;
        sel.innerHTML = '';

        // Always include Direct / Office
        const defOpt = document.createElement('option');
        defOpt.value = '';
        defOpt.textContent = '-- Direct / Office (No Salesman) --';
        sel.appendChild(defOpt);

        const matchingBookers = allBookers.filter(b => salesmanCoversArea(b.area, areaName));

        if (areaName && matchingBookers.length === 0) {
            const noOpt = document.createElement('option');
            noOpt.value = '';
            noOpt.disabled = true;
            noOpt.textContent = '-- No salesman allocated to ' + areaName + ' --';
            sel.appendChild(noOpt);
        }

        matchingBookers.forEach(b => {
            const opt = document.createElement('option');
            opt.value = b.id;
            opt.setAttribute('data-booker', b.name);
            opt.setAttribute('data-areas', b.area || '');
            const comm = b.commission_rate && parseFloat(b.commission_rate) > 0 
                ? ' (' + parseFloat(b.commission_rate).toString() + '%)' 
                : '';
            opt.textContent = b.name + comm;
            sel.appendChild(opt);
        });

        // Retain current selection if valid in this area
        const stillValid = matchingBookers.some(b => String(b.id) === String(currentVal));
        if (stillValid) {
            sel.value = currentVal;
        } else {
            if (matchingBookers.length === 1 && areaName) {
                sel.value = String(matchingBookers[0].id);
            } else {
                sel.value = '';
            }
        }
        syncVisibleSalesman(sel);

        const badge = document.getElementById('salesmanAreaBadge');
        const badgeText = document.getElementById('salesmanAreaText');
        if (badge && badgeText) {
            if (areaName) {
                badgeText.textContent = areaName;
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }
        }
    }

    function onAreaRouteSelectChange(selectEl) {
        if (!selectEl) return;
        const opt = selectEl.options[selectEl.selectedIndex];
        const areaName = opt && selectEl.value ? (opt.getAttribute('data-route-name') || opt.text).trim() : '';

        const rNameInput = document.getElementById('routeNameInput');
        if (rNameInput) rNameInput.value = areaName;

        currentActiveArea = areaName;

        // Filter salesman dropdown
        filterSalesmenByArea(currentActiveArea);

        // If existing selected customer doesn't belong to newly chosen area, clear customer
        if (selectedCustomer && currentActiveArea) {
            const cArea = (selectedCustomer.area || '').trim().toLowerCase();
            if (cArea !== currentActiveArea.toLowerCase()) {
                clearCustomerSelection();
            }
        }

        const custInput = document.getElementById('customerSearchInput');
        if (custInput) {
            custInput.placeholder = currentActiveArea 
                ? 'Search ' + currentActiveArea + ' customer / shop...' 
                : 'Type shop name or customer to search...';
        }

        selectEl.classList.remove('is-invalid');
    }

    function onCustomerSearchFocus() {
        const input = document.getElementById('customerSearchInput');
        searchCustomers(input.value.trim());
    }

    function onCustomerSearchInput() {
        const input = document.getElementById('customerSearchInput');
        const q = input.value.trim();
        if (!q) {
            selectedCustomer = null;
            const idInput = document.getElementById('customerIdInput');
            if (idInput) idInput.value = '';
        }
        searchCustomers(q);
    }

    function searchCustomers(query) {
        const dd = document.getElementById('customerDropdown');
        const input = document.getElementById('customerSearchInput');
        if (!dd || !input) return;
        currentCustomerIdx = -1;

        if (selectedCustomer && query === selectedCustomer.label) {
            dd.classList.add('d-none');
            return;
        }

        const qLower = query.toLowerCase();
        const localMatches = customersCatalog.filter(c => {
            // Strictly enforce Area filter when an area is active
            if (currentActiveArea) {
                const cArea = (c.area || '').trim().toLowerCase();
                if (cArea !== currentActiveArea.toLowerCase()) return false;
            }
            if (!query) return true;
            const shop = (c.shop_name || '');
            const name = (c.name || '');
            return shop.toLowerCase().includes(qLower) ||
                   name.toLowerCase().includes(qLower) ||
                   (c.phone && c.phone.includes(qLower));
        }).slice(0, 20);

        renderCustomerList(localMatches, query);
        dd.classList.remove('d-none');

        clearTimeout(customerSearchTimer);
        customerSearchTimer = setTimeout(() => {
            let url = 'new_sale.php?action=search_customer&q=' + encodeURIComponent(query);
            if (currentActiveArea) {
                url += '&area=' + encodeURIComponent(currentActiveArea);
            }
            fetch(url)
                .then(r => r.json())
                .then(data => {
                    if (Array.isArray(data)) {
                        renderCustomerList(data, query);
                    }
                })
                .catch(() => {});
        }, 200);
    }

    function renderCustomerList(items, query) {
        const list = document.getElementById('customerResultsList');
        if (!list) return;

        if (!items || items.length === 0) {
            const areaMsg = currentActiveArea ? ' in area "<strong>' + escapeHtml(currentActiveArea) + '</strong>"' : '';
            list.innerHTML = `
                <div class="p-3 text-center text-muted small">
                    <i class="fas fa-exclamation-triangle text-warning mr-1"></i>
                    No customer found${areaMsg} matching "<strong>${escapeHtml(query)}</strong>"
                </div>
            `;
            return;
        }

        list.innerHTML = items.map((c, idx) => {
            const shopHl  = c.shop_name ? highlightMatch(c.shop_name, query) : '';
            const nameHl  = c.name && c.name !== c.shop_name ? highlightMatch(c.name, query) : '';
            const phoneHl = c.phone ? highlightMatch(c.phone, query) : '';
            const areaHl  = c.area ? '<i class="fas fa-map-marker-alt mr-1"></i>' + highlightMatch(c.area, query) : '';
            const bal = parseFloat(c.current_balance || 0);

            return `
                <div class="search-dropdown-item customer-item"
                     data-idx="${idx}"
                     onclick='selectCustomerItem(${JSON.stringify(c).replace(/'/g, "&apos;")})'>
                    <div class="d-flex justify-content-between align-items-center">
                        <strong class="text-dark">${shopHl || nameHl || 'Unnamed Customer'}</strong>
                        <span class="badge ${bal > 0 ? 'badge-danger' : 'badge-secondary'}">Bal: Rs. ${bal.toLocaleString('en-US', { minimumFractionDigits: 2 })}</span>
                    </div>
                    <div class="small text-muted mt-1">
                        ${nameHl ? `<span>${nameHl}</span>` : ''}
                        ${phoneHl ? `<span class="ml-2"><i class="fas fa-phone mr-1"></i>${phoneHl}</span>` : ''}
                        ${areaHl ? `<span class="ml-2">${areaHl}</span>` : ''}
                    </div>
                </div>
            `;
        }).join('');
    }

    function selectCustomerItem(c) {
        const label = c.shop_name ? c.shop_name : c.name;
        selectedCustomer = { ...c, label: label };

        const input = document.getElementById('customerSearchInput');
        input.value = label;
        const idInput = document.getElementById('customerIdInput');
        if (idInput) idInput.value = c.id;
        const clearBtn = document.getElementById('customerClearBtn');
        if (clearBtn) clearBtn.classList.remove('d-none');

        const bal = parseFloat(c.current_balance || 0);
        const balDisplay = document.getElementById('customerBalDisplay');
        if (balDisplay) balDisplay.textContent = 'Rs. ' + bal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const summaryBal = document.getElementById('summaryCustomerBalance');
        if (summaryBal) summaryBal.textContent = 'Rs. ' + bal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const prevBalInput = document.getElementById('previousBalanceInput');
        if (prevBalInput) prevBalInput.value = '0.00';

        // Show customer Area badge
        const areaBadge = document.getElementById('customerAreaBadge');
        const areaText  = document.getElementById('customerAreaText');
        if (areaBadge && areaText) {
            if (c.area) {
                areaText.textContent = c.area;
                areaBadge.classList.remove('d-none');
            } else {
                areaBadge.classList.add('d-none');
            }
        }

        // Auto-select Area / Route if customer has one and routeSelect exists
        const custArea = (c.area || '').trim();
        const rSelect = document.getElementById('routeSelect');
        const rNameInput = document.getElementById('routeNameInput');
        if (custArea && rSelect) {
            let matched = false;
            const areaLower = custArea.toLowerCase();
            for (let k = 0; k < rSelect.options.length; k++) {
                const opt = rSelect.options[k];
                const optName = (opt.getAttribute('data-route-name') || opt.text).trim().toLowerCase();
                if (optName === areaLower) {
                    rSelect.value = opt.value;
                    matched = true;
                    break;
                }
            }
            if (!matched) {
                const dynOpt = document.createElement('option');
                dynOpt.value = 'area_' + custArea;
                dynOpt.setAttribute('data-route-name', custArea);
                dynOpt.setAttribute('data-dyn-cust', '1');
                dynOpt.textContent = custArea;
                rSelect.appendChild(dynOpt);
                rSelect.value = dynOpt.value;
            }
            if (rNameInput) rNameInput.value = custArea;
            currentActiveArea = custArea;
            filterSalesmenByArea(currentActiveArea);
        }

        const dd = document.getElementById('customerDropdown');
        if (dd) dd.classList.add('d-none');
        recalculateAll();
    }

    function syncVisibleSalesman(el) {
        if (!el) return;
        const val = el.value || '';
        const opt = el.selectedIndex >= 0 ? el.options[el.selectedIndex] : null;
        const name = opt ? (opt.getAttribute('data-booker') || (val ? opt.text : '')) : '';
        const bId = document.getElementById('bookerSelect');
        const bName = document.getElementById('bookerNameInput');
        if (bId) bId.value = val;
        if (bName) bName.value = name;
    }

    function clearCustomerSelection() {
        selectedCustomer = null;
        const input = document.getElementById('customerSearchInput');
        if (input) input.value = '';
        const idInput = document.getElementById('customerIdInput');
        if (idInput) idInput.value = '';
        const clearBtn = document.getElementById('customerClearBtn');
        if (clearBtn) clearBtn.classList.add('d-none');
        const balDisplay = document.getElementById('customerBalDisplay');
        if (balDisplay) balDisplay.textContent = 'Rs. 0.00';
        const summaryBal = document.getElementById('summaryCustomerBalance');
        if (summaryBal) summaryBal.textContent = 'Rs. 0.00';
        const prevBalInput = document.getElementById('previousBalanceInput');
        if (prevBalInput) prevBalInput.value = '0.00';

        const rSelect = document.getElementById('routeSelect');
        const dynOpt = rSelect ? rSelect.querySelector('option[data-dyn-cust="1"]') : null;
        if (dynOpt) {
            dynOpt.remove();
            rSelect.value = '';
            const rNameInput = document.getElementById('routeNameInput');
            if (rNameInput) rNameInput.value = '';
            currentActiveArea = '';
            filterSalesmenByArea('');
        }

        const areaBadge = document.getElementById('customerAreaBadge');
        if (areaBadge) areaBadge.classList.add('d-none');
        const dd = document.getElementById('customerDropdown');
        if (dd) dd.classList.add('d-none');
        recalculateAll();
    }

    function onCustomerSearchKeydown(e) {
        const dd = document.getElementById('customerDropdown');
        if (!dd) return;
        const items = dd.querySelectorAll('.customer-item');
        if (dd.classList.contains('d-none') || items.length === 0) {
            if (e.key === 'ArrowDown') {
                onCustomerSearchFocus();
            }
            return;
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            currentCustomerIdx = Math.min(currentCustomerIdx + 1, items.length - 1);
            highlightActiveDropdownItem(items, currentCustomerIdx);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            currentCustomerIdx = Math.max(currentCustomerIdx - 1, 0);
            highlightActiveDropdownItem(items, currentCustomerIdx);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (currentCustomerIdx >= 0 && items[currentCustomerIdx]) {
                items[currentCustomerIdx].click();
            }
        } else if (e.key === 'Escape') {
            dd.classList.add('d-none');
        }
    }

    function renderProductList(rowId, items, query) {
        const list = document.getElementById('medResultsList_' + rowId);
        if (!list) return;

        if (!items || items.length === 0) {
            list.innerHTML = `
                <div class="p-3 text-center text-muted small">
                    <i class="fas fa-exclamation-triangle text-warning mr-1"></i>
                    No medicine found matching "<strong>${escapeHtml(query)}</strong>".
                    <div class="text-secondary mt-1" style="font-size:0.75rem;">Only medicines added in the system can be sold.</div>
                </div>
            `;
            return;
        }

        list.innerHTML = items.map((p, idx) => {
            const nameHl = highlightMatch(p.name, query);
            const codeHl = p.product_code ? highlightMatch(p.product_code, query) : '';
            const compHl = p.company_name ? highlightMatch(p.company_name, query) : '';
            // Use sale_price (retail_price) as the display price; fall back to trade_price
            const salePrice = parseFloat(p.sale_price || p.retail_price || p.trade_price || 0);
            const stock = parseInt(p.current_stock || 0);

            let stockBadge = '';
            let itemClass = `search-dropdown-item med-item-${rowId}`;

            if (stock > 10) {
                stockBadge = `<span class="badge badge-success"><i class="fas fa-boxes mr-1"></i>Stock: ${stock}</span>`;
            } else if (stock > 0) {
                stockBadge = `<span class="badge badge-warning"><i class="fas fa-exclamation-triangle mr-1"></i>Low Stock: ${stock}</span>`;
            } else {
                stockBadge = `<span class="badge badge-danger"><i class="fas fa-ban mr-1"></i>Out of Stock (0)</span>`;
                itemClass += ' out-of-stock';
            }

            const disc = parseFloat(p.default_discount || p.discount_percent || 0);

            return `
                <div class="${itemClass}" 
                     data-idx="${idx}"
                     onclick='handleProductSelection(${rowId}, ${JSON.stringify(p).replace(/'/g, "&apos;")})'>
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong class="text-dark">${nameHl}</strong>
                        </div>
                        <div>${stockBadge}</div>
                    </div>
                    <div class="small text-muted d-flex justify-content-between align-items-center mt-1">
                        <span><i class="fas fa-industry mr-1"></i>${compHl || 'General'}</span>
                        <span class="text-dark font-weight-bold">
                            Sale Price: Rs. ${salePrice.toFixed(2)}
                            ${disc > 0 ? `<span class="badge badge-primary ml-1">${disc.toFixed(1)}% Disc</span>` : ''}
                        </span>
                    </div>
                </div>
            `;
        }).join('');
    }

    function handleProductSelection(rowId, p) {
        const stock = parseInt(p.current_stock || 0);

        // REQUIREMENT: Out of stock validation and alert
        if (stock <= 0) {
            alert("⚠️ Error: Product '" + p.name + "' is out of stock! (Available Stock: 0)\nYou cannot sell this product.");
            return;
        }

        selectProductItem(rowId, p);
    }

    function selectProductItem(rowId, p) {
        const stock = parseInt(p.current_stock || 0);

        // TP rate priority: trade_price (product master) > purchase_items trade_price > purchase price
        let tpRate = parseFloat(p.trade_price || 0);
        if (tpRate <= 0) tpRate = parseFloat(p.purchase_price || 0);

        document.getElementById('productId_' + rowId).value = p.id;
        document.getElementById('itemName_' + rowId).value = p.name;
        document.getElementById('medSearch_' + rowId).value = p.name;
        document.getElementById('stockAvailable_' + rowId).value = stock;
        document.getElementById('baseTp_' + rowId).value = tpRate.toFixed(2);

        // Set Price field to TP rate (sale price is computed after discount in Net Total)
        const tpInput = document.getElementById('tp_' + rowId);
        tpInput.value = (tpRate > 0) ? tpRate.toFixed(2) : '0.00';

        // Auto-fill Default Discount % from Purchase (only if > 0)
        const defDisc = parseFloat(p.default_discount || p.discount_percent || 0) || 0;
        const discInput = document.getElementById('disc_' + rowId);
        if (discInput) {
            discInput.value = defDisc > 0 ? defDisc.toFixed(2) : '';
        }

        const clearBtn = document.getElementById('medClearBtn_' + rowId);
        if (clearBtn) clearBtn.classList.remove('d-none');

        const qtyInput = document.getElementById('qty_' + rowId);
        if (qtyInput) {
            if (!qtyInput.value) qtyInput.value = 1;
            qtyInput.focus();
            qtyInput.select();
        }

        const infoPill = document.getElementById('productInfoPill_' + rowId);
        const pillBadge = document.getElementById('stockPillBadge_' + rowId);
        const subInfo = document.getElementById('productSubInfo_' + rowId);

        if (infoPill && pillBadge && subInfo) {
            infoPill.classList.remove('d-none');
            if (stock > 10) {
                pillBadge.className = 'stock-pill badge badge-success';
                pillBadge.innerHTML = `<i class="fas fa-boxes mr-1"></i>Stock: <strong>${stock} Pcs</strong> Available`;
            } else {
                pillBadge.className = 'stock-pill badge badge-warning';
                pillBadge.innerHTML = `<i class="fas fa-exclamation-triangle mr-1"></i>Low Stock: <strong>${stock} Pcs</strong> Available`;
            }
            subInfo.textContent = (p.company_name ? p.company_name : '') + (defDisc > 0 ? (p.company_name ? ' | ' : '') + 'Purchase Disc: ' + defDisc.toFixed(1) + '%' : '');
        }

        const dd = document.getElementById('medDropdown_' + rowId);
        if (dd) dd.classList.add('d-none');

        recalculateAll();
    }

    function clearProductRow(rowId) {
        document.getElementById('medSearch_' + rowId).value = '';
        clearProductRowDataOnly(rowId);
        document.getElementById('medSearch_' + rowId).focus();
        searchProductsForRow(rowId, '');
    }

    function clearProductRowDataOnly(rowId) {
        document.getElementById('productId_' + rowId).value = '';
        document.getElementById('itemName_' + rowId).value = '';
        document.getElementById('stockAvailable_' + rowId).value = '0';
        document.getElementById('baseTp_' + rowId).value = '0.00';
        document.getElementById('tp_' + rowId).value = '';
        const discInput = document.getElementById('disc_' + rowId);
        if (discInput) discInput.value = '';
        const clearBtn = document.getElementById('medClearBtn_' + rowId);
        if (clearBtn) clearBtn.classList.add('d-none');
        const infoPill = document.getElementById('productInfoPill_' + rowId);
        if (infoPill) infoPill.classList.add('d-none');
        const qtyInput = document.getElementById('qty_' + rowId);
        if (qtyInput) {
            qtyInput.value = '';
            qtyInput.removeAttribute('max');
        }
        recalculateAll();
    }

    // Live quantity validation against stock in Pcs
    function onQtyChange(rowId) {
        const qtyInput = document.getElementById('qty_' + rowId);
        const stockVal = parseInt(document.getElementById('stockAvailable_' + rowId).value) || 0;
        const prodName = document.getElementById('itemName_' + rowId).value || 'Medicine';
        const pid      = document.getElementById('productId_' + rowId).value;

        let enteredQty = parseInt(qtyInput.value) || 0;

        if (pid) {
            if (stockVal <= 0) {
                alert("⚠️ Error: '" + prodName + "' is out of stock! (Available Stock: 0)");
                qtyInput.value = 1;
                recalculateAll();
                return;
            }
            if (enteredQty > stockVal) {
                alert("⚠️ Error: Only " + stockVal + " Pcs available in stock for '" + prodName + "'!\nEntered quantity has been set to " + stockVal + ".");
                qtyInput.value = stockVal;
            } else if (enteredQty < 1) {
                qtyInput.value = 1;
            }
        }
        recalculateAll();
    }

    // Keyboard navigation in search dropdown
    function onMedSearchKeydown(e, rowId) {
        const dd = document.getElementById('medDropdown_' + rowId);
        if (!dd) return;
        const items = dd.querySelectorAll('.search-dropdown-item');
        if (dd.classList.contains('d-none') || items.length === 0) {
            if (e.key === 'ArrowDown') {
                onMedSearchFocus(rowId);
            }
            return;
        }

        if (currentMedIdxs[rowId] === undefined) currentMedIdxs[rowId] = -1;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            currentMedIdxs[rowId] = Math.min(currentMedIdxs[rowId] + 1, items.length - 1);
            highlightActiveDropdownItem(items, currentMedIdxs[rowId]);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            currentMedIdxs[rowId] = Math.max(currentMedIdxs[rowId] - 1, 0);
            highlightActiveDropdownItem(items, currentMedIdxs[rowId]);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (currentMedIdxs[rowId] >= 0 && items[currentMedIdxs[rowId]]) {
                items[currentMedIdxs[rowId]].click();
            }
        } else if (e.key === 'Escape') {
            dd.classList.add('d-none');
        }
    }

    function highlightActiveDropdownItem(items, activeIdx) {
        items.forEach((item, idx) => {
            if (idx === activeIdx) {
                item.classList.add('active');
                item.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            } else {
                item.classList.remove('active');
            }
        });
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.product-search-container')) {
            document.querySelectorAll('.med-dropdown').forEach(dd => dd.classList.add('d-none'));
        }
        if (!e.target.closest('#customerSearchContainer')) {
            const cdd = document.getElementById('customerDropdown');
            if (cdd) cdd.classList.add('d-none');
        }
    });

    // Customer & Route Controllers
    const customerSearchInput = document.getElementById('customerSearchInput');
    const customerIdInput     = document.getElementById('customerIdInput');
    const routeSelect         = document.getElementById('routeSelect');
    const routeNameInput      = document.getElementById('routeNameInput');
    const prevBalanceInput    = document.getElementById('previousBalanceInput');

    if (routeSelect) {
        routeSelect.addEventListener('change', function () {
            onAreaRouteSelectChange(this);
        });
        if (routeSelect.value && !currentActiveArea) {
            const opt = routeSelect.options[routeSelect.selectedIndex];
            currentActiveArea = opt ? (opt.getAttribute('data-route-name') || opt.text).trim() : '';
        }
    }
    if (currentActiveArea) {
        filterSalesmenByArea(currentActiveArea);
    }

    function onCustomerChange() {
        if (!customerSearchInput) return;
        if (selectedCustomer) {
            const bal = parseFloat(selectedCustomer.current_balance || 0);
            if (prevBalanceInput) prevBalanceInput.value = bal > 0 ? bal.toFixed(2) : '';
            if (customerIdInput) customerIdInput.value = selectedCustomer.id || '';
            const balDisplay = document.getElementById('customerBalDisplay');
            if (balDisplay) balDisplay.textContent = 'Rs. ' + bal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        } else {
            if (prevBalanceInput) prevBalanceInput.value = '';
            if (customerIdInput) customerIdInput.value = '';
            const balDisplay = document.getElementById('customerBalDisplay');
            if (balDisplay) balDisplay.textContent = 'Rs. 0.00';
        }
        recalculateAll();
    }

    // Toggle Bank Account field based on payment method selection
    function toggleSalePaymentFields() {
        const mode = document.getElementById('paymentModeSelect').value;
        const bankBox = document.getElementById('saleBankAccountBox');
        const paidInput = document.getElementById('paidAmountInput');
        const grandTotal = parseFloat(document.getElementById('currentBillInput')?.value || 0) || 0;

        if (mode === 'Bank') {
            if (bankBox) bankBox.classList.remove('d-none');
            if (paidInput && !parseFloat(paidInput.value) && grandTotal > 0) paidInput.value = grandTotal.toFixed(2);
        } else if (mode === 'Cash') {
            if (bankBox) bankBox.classList.add('d-none');
            if (paidInput && !parseFloat(paidInput.value) && grandTotal > 0) paidInput.value = grandTotal.toFixed(2);
        } else {
            // Credit
            if (bankBox) bankBox.classList.add('d-none');
            if (paidInput) paidInput.value = '';
        }
        recalculateAll();
    }

    // Mathematical Calculation Engine
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

            row.querySelector('.row-net').value = (lineNet > 0) ? lineNet.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : (qty > 0 && tp > 0 ? '0.00' : '');

            grossTotal += lineGross;
            itemsNetTotal += lineNet;
        });

        // Update left info box
        const totalProductDiscs = grossTotal - itemsNetTotal;
        const grossEl = document.getElementById('itemsGrossDisplay');
        const discEl  = document.getElementById('productDiscDisplay');
        if (grossEl) grossEl.textContent = 'Rs. ' + grossTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        if (discEl) discEl.textContent = '- Rs. ' + totalProductDiscs.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        // Order level discount
        const orderDiscPercent = parseFloat(document.getElementById('orderDiscount').value) || 0;
        const orderDiscountAmt = itemsNetTotal * (orderDiscPercent / 100);
        const afterOrderDisc   = itemsNetTotal - orderDiscountAmt;

        let rawBill = afterOrderDisc;

        // Round Off
        const roundOffCheck = document.getElementById('roundOffCheck');
        const roundOffInput = document.getElementById('roundOffValue');
        let roundOffVal = 0;

        if (roundOffCheck && roundOffCheck.checked) {
            const targetRounded = Math.round(rawBill);
            roundOffVal = +(targetRounded - rawBill).toFixed(2);
            roundOffInput.value = roundOffVal !== 0 ? roundOffVal.toFixed(2) : '0.00';
        } else if (roundOffInput && roundOffInput.value !== '') {
            roundOffVal = parseFloat(roundOffInput.value) || 0;
        }

        const currentBill = rawBill + roundOffVal;
        const curBillInput = document.getElementById('currentBillInput');
        if (curBillInput) curBillInput.value = currentBill.toFixed(2);
        const dispBill = document.getElementById('dispCurrentBill');
        if (dispBill) dispBill.textContent = 'Rs. ' + currentBill.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        // Net Payable (Equal to Current Bill — Previous balance is not added to invoice)
        const netPayable = currentBill;
        const netPayInput = document.getElementById('netPayableInput');
        if (netPayInput) netPayInput.value = netPayable.toFixed(2);
        const dispNet = document.getElementById('dispNetPayable');
        if (dispNet) dispNet.textContent = 'Rs. ' + netPayable.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        // Paid Amount & Balance Due
        const paidAmount = parseFloat(document.getElementById('paidAmountInput').value) || 0;
        const balanceDue = Math.max(0, netPayable - paidAmount);
        const balDueInput = document.getElementById('balanceDueInput');
        if (balDueInput) balDueInput.value = balanceDue.toFixed(2);
        const dispBal = document.getElementById('dispBalanceDue');
        if (dispBal) dispBal.textContent = 'Rs. ' + balanceDue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Realtime listeners
    itemsBody.addEventListener('input', recalculateAll);
    document.getElementById('orderDiscount').addEventListener('input', recalculateAll);
    document.getElementById('paidAmountInput').addEventListener('input', recalculateAll);
    document.getElementById('roundOffValue').addEventListener('input', function() {
        document.getElementById('roundOffCheck').checked = false;
        recalculateAll();
    });
    document.getElementById('roundOffCheck').addEventListener('change', recalculateAll);





    // Form handlers and initialization only when saleForm is present
    const form = document.getElementById('saleForm');
    if (form) {
        if (addRowBtn) {
            addRowBtn.addEventListener('click', function () {
                addNewRow(true);
            });
        }

        if (itemsBody) {
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
        }

        form.addEventListener('submit', function (event) {
            let hasError = false;
            let errorMsg = '';

            const custInput = document.getElementById('customerSearchInput');
            if (!custInput || !custInput.value.trim()) {
                alert('⚠️ Please select a registered customer.');
                if (custInput) custInput.focus();
                event.preventDefault();
                return false;
            }

            const rows = itemsBody ? itemsBody.querySelectorAll('.sale-item-row') : [];
            if (!rows || rows.length === 0) {
                alert('Please add at least one product.');
                event.preventDefault();
                return false;
            }

            rows.forEach((row, idx) => {
                const pId = row.querySelector('.product-id-input').value;
                const pName = row.querySelector('.item-name-input').value || 'Medicine';
                const qty = parseInt(row.querySelector('.qty-input').value) || 0;
                const stock = parseInt(row.querySelector('.stock-available-input').value) || 0;

                if (!pId) {
                    hasError = true;
                    errorMsg = `Row #${idx + 1}: Please search and select a registered product from the system list.`;
                    row.querySelector('.med-search-input').focus();
                } else if (stock <= 0) {
                    hasError = true;
                    errorMsg = `Row #${idx + 1}: Product '${pName}' is out of stock (Stock: 0)!`;
                } else if (qty > stock) {
                    hasError = true;
                    errorMsg = `Row #${idx + 1}: Product '${pName}' has only ${stock} in stock, but entered quantity is ${qty}!`;
                }
            });

            if (hasError) {
                alert('⚠️ ' + errorMsg);
                event.preventDefault();
                event.stopPropagation();
                return false;
            }

            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);

        // Initial Row Creation on Page Load
        if (itemsBody) {
            addNewRow(false);
        }

        // Auto pre-populate and trigger customer selection if customer was selected
        <?php if (!empty($preselected_customer)): ?>
        try {
            const preCust = <?= json_encode($preselected_customer) ?>;
            selectCustomerItem(preCust);
            setTimeout(function() {
                const firstMed = document.getElementById('medSearch_1');
                if (firstMed) firstMed.focus();
            }, 150);
        } catch (err) {
            console.error('Customer preload error:', err);
        }
        <?php endif; ?>

        // Sync visible salesman dropdown if pre-selected
        const visSalesman = document.getElementById('visibleSalesmanSelect');
        if (visSalesman && visSalesman.value && typeof syncVisibleSalesman === 'function') {
            syncVisibleSalesman(visSalesman);
        }
    }

    // Step 1: Area Filter Search
    const areaFilterInput = document.getElementById('areaFilterInput');
    if (areaFilterInput) {
        areaFilterInput.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            let visible = 0;
            document.querySelectorAll('.area-col').forEach(function(col) {
                const text = col.textContent.toLowerCase();
                const show = (q === '' || text.includes(q));
                col.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            const countEl = document.getElementById('areaCount');
            if (countEl) countEl.textContent = visible;
            const noArea = document.getElementById('noAreaFound');
            if (noArea) {
                if (visible === 0) noArea.classList.remove('d-none');
                else noArea.classList.add('d-none');
            }
        });
    }

    // Step 2: Customer / Shop Search in Area
    const shopSearchInput = document.getElementById('shopSearch');
    if (shopSearchInput) {
        shopSearchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            let visible = 0;
            const rows = document.querySelectorAll('#shopTable tbody tr:not(#noShopFoundRow)');
            rows.forEach(function(row) {
                const text = row.textContent.toLowerCase();
                const show = (q === '' || text.includes(q));
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            const visCount = document.getElementById('customerVisibleCount');
            if (visCount) visCount.textContent = visible;

            let noRow = document.getElementById('noShopFoundRow');
            if (visible === 0 && q !== '') {
                if (!noRow) {
                    const tbody = document.querySelector('#shopTable tbody');
                    if (tbody) {
                        noRow = document.createElement('tr');
                        noRow.id = 'noShopFoundRow';
                        noRow.innerHTML = '<td colspan="5" class="text-center text-muted py-4"><i class="fas fa-search fa-2x mb-2 d-block text-muted"></i>No customer matches "<b>' + escapeHtml(q) + '</b>"</td>';
                        tbody.appendChild(noRow);
                    }
                } else {
                    noRow.querySelector('td').innerHTML = '<i class="fas fa-search fa-2x mb-2 d-block text-muted"></i>No customer matches "<b>' + escapeHtml(q) + '</b>"';
                    noRow.style.display = '';
                }
            } else if (noRow) {
                noRow.remove();
            }
        });
    }

    // Global focus auto-select: immediately highlights content so typing overwrites without backspacing
    document.addEventListener('focus', function(e) {
        if (e.target && (e.target.matches('input[type=number]') || e.target.matches('.med-search-input'))) {
            e.target.select();
        }
    }, true);
</script>

<?php if (!empty($saved_invoice_id)): ?>
<!-- Direct Print Redirect: Customer ki allocated invoice type ke mutabiq seedha print khulega -->
<div style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.96);z-index:99999;display:flex;flex-direction:column;align-items:center;justify-content:center;">
    <div class="spinner-border text-success mb-3" style="width: 3.5rem; height: 3.5rem;" role="status">
        <span class="sr-only">Loading...</span>
    </div>
    <h4 class="font-weight-bold text-dark mb-1">Invoice <?= htmlspecialchars($saved_invoice_no) ?> Saved!</h4>
    <p class="text-muted mb-3">Opening <strong><?= ucfirst($saved_customer_invoice_type) ?> Invoice</strong> for printing...</p>
    <a href="print_invoice.php?id=<?= $saved_invoice_id ?>&type=<?= $saved_customer_invoice_type ?>" class="btn btn-sm btn-outline-primary">
        Click here if not redirected automatically
    </a>
</div>
<script>
    window.location.href = "print_invoice.php?id=<?= $saved_invoice_id ?>&type=<?= $saved_customer_invoice_type ?>";
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
