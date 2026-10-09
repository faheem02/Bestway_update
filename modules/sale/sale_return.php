<?php
/**
 * Bestway Wholesale Distribution - Customer Sales Return
 * Direct Product-Wise Return: Select customer, search medicines by alphabet, set quantity & rate, replenish inventory & adjust customer balance.
 */
$page_title = "Customer Sales Return";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// AJAX endpoint for live alphabet-wise product search
if (isset($_GET['action']) && $_GET['action'] === 'search_product') {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    $res = [];
    if ($db_connected && $pdo) {
        $sql = "SELECT p.id, p.product_code, p.name, p.generic_name, 
                       COALESCE(NULLIF(p.trade_price, 0), NULLIF(p.retail_price, 0), p.purchase_price, 0) as trade_price,
                       p.current_stock, p.stock_unit,
                       c.name as company_name 
                FROM products p 
                LEFT JOIN companies c ON c.id = p.company_id 
                WHERE p.status = 'Active'";
        if ($q !== '') {
            $sql .= " AND (p.name LIKE :q1 OR p.product_code LIKE :q2 OR p.generic_name LIKE :q3 OR c.name LIKE :q4)
                      ORDER BY 
                        CASE 
                          WHEN p.name LIKE :exact THEN 1
                          WHEN p.name LIKE :start THEN 2
                          WHEN c.name LIKE :start_c THEN 3
                          ELSE 4
                        END, p.name ASC 
                      LIMIT 35";
            $stmt = $pdo->prepare($sql);
            $like = "%$q%";
            $stmt->execute([
                ':q1' => $like,
                ':q2' => $like,
                ':q3' => $like,
                ':q4' => $like,
                ':exact' => $q,
                ':start' => "$q%",
                ':start_c' => "$q%"
            ]);
        } else {
            $stmt = $pdo->prepare($sql . " ORDER BY p.name ASC LIMIT 50");
            $stmt->execute();
        }
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode($res);
    exit;
}

requireRole(['admin']);
require_once __DIR__ . '/../../includes/header.php';

$message = "";
$msg_type = "";

// Flash message support
if (!empty($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $msg_type = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// Handle Delete Return Action (Revert Stock & Accounts)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id'])) {
    $del_id = intval($_GET['id']);
    if ($db_connected && $pdo && $del_id > 0) {
        try {
            $pdo->beginTransaction();

            $stmt_ret = $pdo->prepare("SELECT * FROM sale_returns WHERE id = ?");
            $stmt_ret->execute([$del_id]);
            $ret_data = $stmt_ret->fetch(PDO::FETCH_ASSOC);

            if ($ret_data) {
                // 1. Fetch return items to revert stock
                $stmt_ritems = $pdo->prepare("SELECT product_id, quantity, `condition` FROM sale_return_items WHERE return_id = ? OR sale_return_id = ?");
                $stmt_ritems->execute([$del_id, $del_id]);
                $ritems = $stmt_ritems->fetchAll(PDO::FETCH_ASSOC);

                $stmt_sub_stock = $pdo->prepare("UPDATE products SET current_stock = GREATEST(0, current_stock - ?) WHERE id = ?");

                foreach ($ritems as $ri) {
                    $cond = $ri['condition'] ?? 'Good';
                    if ($cond === 'Good / Resalable' || $cond === 'Good') {
                        $stmt_sub_stock->execute([$ri['quantity'], $ri['product_id']]);
                    }
                }

                // 2. Revert refund if cash, bank, or customer balance
                $ret_amt = floatval($ret_data['total_amount']);
                $refund_type = $ret_data['refund_type'] ?? '';
                $cash_acc_id = !empty($ret_data['cash_account_id']) ? intval($ret_data['cash_account_id']) : 0;
                $bank_acc_id = !empty($ret_data['bank_account_id']) ? intval($ret_data['bank_account_id']) : 0;
                $cust_id = intval($ret_data['customer_id'] ?? 0);

                if ($refund_type === 'Cash Refund' && $cash_acc_id > 0) {
                    $pdo->prepare("UPDATE cash_accounts SET balance = balance + ? WHERE id = ?")->execute([$ret_amt, $cash_acc_id]);
                } elseif ($refund_type === 'Bank Refund' && $bank_acc_id > 0) {
                    $pdo->prepare("UPDATE bank_accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$ret_amt, $bank_acc_id]);
                } elseif ($refund_type === 'Deduct Balance' || $refund_type === 'Store Credit' || $refund_type === 'Credit Note' || $refund_type === 'Adjust / Deduct Customer Balance') {
                    if ($cust_id > 0 && $ret_amt > 0) {
                        $pdo->prepare("UPDATE customers SET current_balance = current_balance + ? WHERE id = ?")->execute([$ret_amt, $cust_id]);
                    }
                }

                // 3. Delete items & return
                $pdo->prepare("DELETE FROM sale_return_items WHERE return_id = ? OR sale_return_id = ?")->execute([$del_id, $del_id]);
                $pdo->prepare("DELETE FROM sale_returns WHERE id = ?")->execute([$del_id]);

                $pdo->commit();

                // 4. Sync customer balance & ledger
                if ($cust_id > 0 && function_exists('updateCustomerBalance')) {
                    try { updateCustomerBalance($pdo, $cust_id); } catch (Exception $e) {}
                }

                $message = "Sale Return #{$ret_data['return_no']} deleted successfully and stock/accounts reverted.";
                $msg_type = "success";
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $message = "Delete error: " . $e->getMessage();
            $msg_type = "danger";
        }
    }
}

// Auto-generate Return No (e.g. SRTN-2026-0001)
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

// Fetch Active Customers
$customers_list = [];
if ($db_connected && $pdo) {
    try {
        $customers_list = $pdo->query("SELECT id, name, shop_name, current_balance, area FROM customers WHERE status = 'Active' ORDER BY shop_name ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
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

// Handle Form Submission: Create Sale Return (Direct Product-Wise)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['save_return'])) {
    $return_no       = trim($_POST['return_no'] ?? $auto_return_no);
    $customer_id     = intval($_POST['customer_id'] ?? 0);
    $customer_name   = trim($_POST['customer_name'] ?? '');
    $return_date     = trim($_POST['return_date'] ?? date('Y-m-d'));
    $refund_type     = trim($_POST['refund_type'] ?? 'Deduct Balance');
    $cash_account_id = !empty($_POST['cash_account_id']) ? intval($_POST['cash_account_id']) : null;
    $bank_account_id = !empty($_POST['bank_account_id']) ? intval($_POST['bank_account_id']) : null;
    $reason          = trim($_POST['reason'] ?? 'Customer Return');
    $items           = $_POST['items'] ?? [];

    if ($customer_id <= 0) {
        $message = "Barahe karam Customer / Pharmacy select karein.";
        $msg_type = "danger";
    } elseif (empty($items) || !is_array($items)) {
        $message = "Barahe karam kam az kam 1 product search karke add karein.";
        $msg_type = "danger";
    } else {
        try {
            $pdo->beginTransaction();

            $total_return_amount = 0;
            $validated_return_items = [];

            foreach ($items as $itm) {
                $pid        = intval($itm['product_id'] ?? 0);
                $pname      = trim($itm['item_name'] ?? '');
                $ret_qty    = intval($itm['quantity'] ?? 0);
                $unit_price = floatval($itm['unit_price'] ?? 0);
                $condition  = trim($itm['condition'] ?? 'Good / Resalable');

                if ($pid <= 0 || $ret_qty <= 0) continue;
                if ($unit_price < 0) $unit_price = 0.0;

                $line_ret = round($ret_qty * $unit_price, 2);
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

            if (empty($validated_return_items)) {
                throw new Exception("Please enter a return quantity of 1 or more for at least one item.");
            }

            $user_id = $_SESSION['user_id'] ?? 1;

            // 1. Insert into sale_returns table (Direct Return: sale_id = NULL, invoice_id = NULL)
            $stmt_ret = $pdo->prepare("
                INSERT INTO sale_returns (
                    return_no, sale_id, invoice_id, return_date, customer_id, refund_type,
                    total_amount, deduction_amount, net_refund_amount, reason, cash_account_id, bank_account_id, status, created_by
                ) VALUES (?, NULL, NULL, ?, ?, ?, ?, 0.00, ?, ?, ?, ?, 'Completed', ?)
            ");
            $stmt_ret->execute([
                $return_no, $return_date, $customer_id, $refund_type,
                $total_return_amount, $total_return_amount, $reason, $cash_account_id, $bank_account_id, $user_id
            ]);
            $return_id = $pdo->lastInsertId();

            // 2. Insert line items & replenish inventory stock
            $stmt_item = $pdo->prepare("
                INSERT INTO sale_return_items (return_id, sale_return_id, product_id, quantity, unit_price, total_price, `condition`)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_stock = $pdo->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = ?");

            foreach ($validated_return_items as $vi) {
                $stmt_item->execute([$return_id, $return_id, $vi['product_id'], $vi['quantity'], $vi['price'], $vi['total'], $vi['condition']]);
                if ($vi['condition'] === 'Good / Resalable' || $vi['condition'] === 'Good') {
                    $stmt_stock->execute([$vi['quantity'], $vi['product_id']]);
                }
            }

            // 3. Adjust customer balance or cash/bank account
            if ($refund_type === 'Store Credit' || $refund_type === 'Credit Note' || $refund_type === 'Deduct Balance' || $refund_type === 'Adjust / Deduct Customer Balance') {
                $pdo->prepare("UPDATE customers SET current_balance = GREATEST(0, current_balance - ?) WHERE id = ?")->execute([$total_return_amount, $customer_id]);
                if (function_exists('updateCustomerBalance')) {
                    try { updateCustomerBalance($pdo, $customer_id); } catch (Exception $e) {}
                }
            } elseif ($refund_type === 'Cash Refund' && $cash_account_id) {
                $pdo->prepare("UPDATE cash_accounts SET balance = GREATEST(0, balance - ?) WHERE id = ?")->execute([$total_return_amount, $cash_account_id]);
            } elseif ($refund_type === 'Bank Refund' && $bank_account_id) {
                $pdo->prepare("UPDATE bank_accounts SET current_balance = GREATEST(0, current_balance - ?) WHERE id = ?")->execute([$total_return_amount, $bank_account_id]);
            }

            $pdo->commit();

            $message = "Sale Return #{$return_no} saved successfully and stock restored to inventory! <a href='print_return.php?id={$return_id}' target='_blank' class='fw-bold text-dark text-decoration-underline ms-2'><i class='fa-solid fa-print'></i> Print Return Voucher</a>";
            $msg_type = "success";

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $message = "Error: " . $e->getMessage();
            $msg_type = "danger";
        }
    }
}

// Fetch Recent Sales Returns
$recent_returns = [];
if ($db_connected && $pdo) {
    try {
        $recent_returns = $pdo->query("
            SELECT sr.*, 
                   COUNT(sri.id) as total_items_count,
                   c.name as cust_name, c.shop_name as cust_shop
            FROM sale_returns sr
            LEFT JOIN sale_return_items sri ON (sri.return_id = sr.id OR sri.sale_return_id = sr.id)
            LEFT JOIN customers c ON c.id = sr.customer_id
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
    .product-search-box {
        position: relative;
    }
    .product-results-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 1060;
        max-height: 300px;
        overflow-y: auto;
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    }
    .product-search-item {
        padding: 10px 14px;
        border-bottom: 1px solid #f1f5f9;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .product-search-item:hover, .product-search-item.active {
        background: #fee2e2;
        border-left: 4px solid #dc2626;
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
            <p class="text-muted small mb-0">Direct product return: Search medicine, enter quantity &amp; rate, update inventory &amp; customer balance</p>
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
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Return Creation Form -->
<form action="" method="POST" id="returnForm">
    <div class="return-card p-4 p-md-5 mb-4">

        <!-- Step 1: Customer Selection -->
        <div class="mb-4 pb-3 border-bottom">
            <div class="section-tag"><i class="fa-solid fa-user"></i> Step 1: Customer Details</div>
            
            <div class="row g-3">
                <!-- Customer Selection -->
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-muted mb-1">Select Customer / Pharmacy <span class="text-danger">*</span></label>
                    <select name="customer_id" id="customerSelect" class="form-select fw-semibold" onchange="handleCustomerChange(this)" required>
                        <option value="">-- Choose Customer --</option>
                        <?php foreach ($customers_list as $cust): 
                            $c_disp = !empty($cust['shop_name']) ? ($cust['shop_name'] . ' (' . $cust['name'] . ')') : $cust['name'];
                        ?>
                            <option value="<?= $cust['id'] ?>" data-balance="<?= floatval($cust['current_balance']) ?>" data-area="<?= htmlspecialchars($cust['area'] ?? '') ?>">
                                <?= htmlspecialchars($c_disp) ?> &mdash; Bal: Rs. <?= number_format($cust['current_balance'], 2) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="customer_name" id="customerNameHidden" value="">
                </div>

                <!-- Return Voucher Number -->
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Return Voucher Number</label>
                    <input type="text" name="return_no" class="form-control font-monospace fw-bold bg-light text-danger" value="<?= htmlspecialchars($auto_return_no) ?>" readonly>
                </div>

                <!-- Return Date -->
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Return Date</label>
                    <input type="date" name="return_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <!-- Customer Info Badge -->
            <div id="customerInfoBadge" class="mt-3 p-2 px-3 rounded-3 bg-light border d-flex justify-content-between align-items-center d-none">
                <div>
                    <i class="fa-solid fa-store text-danger me-2"></i>
                    <span class="fw-bold text-dark" id="dispCustomerShop"></span>
                    <span class="text-muted small ms-2" id="dispCustomerArea"></span>
                </div>
                <div>
                    <span class="text-muted small">Current Balance:</span>
                    <strong class="text-danger font-monospace fs-6 ms-1" id="dispCustomerBal">Rs. 0.00</strong>
                </div>
            </div>
        </div>

        <!-- Step 2: Product Search & Return Items Table -->
        <div class="mb-4">
            <div class="section-tag"><i class="fa-solid fa-pills"></i> Step 2: Select Medicines to Return</div>

            <!-- Live Product Search Bar -->
            <div class="product-search-box mb-3">
                <label class="form-label small fw-bold text-muted mb-1">Search Product / Medicine by Alphabet or Name <span class="text-danger">*</span></label>
                <div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden">
                    <span class="input-group-text bg-white border-end-0 text-danger"><i class="fa-solid fa-search"></i></span>
                    <input type="text" id="directProductSearch" class="form-control border-start-0 ps-0 fs-6" placeholder="Medicine ka alphabet ya naam type karein (e.g. Panadol, Augmentin, Clobevate)..." autocomplete="off">
                    <button type="button" class="btn btn-danger px-4 fw-bold" onclick="focusProductSearch()">
                        <i class="fa-solid fa-magnifying-glass me-1"></i> Search
                    </button>
                </div>
                <!-- Live Search Results Dropdown -->
                <div id="productResultsDropdown" class="product-results-dropdown d-none"></div>
            </div>

            <!-- Direct Return Table -->
            <div class="table-responsive border rounded-3 overflow-hidden shadow-sm mb-3">
                <table class="table table-return mb-0" id="directReturnTable">
                    <thead>
                        <tr>
                            <th style="width: 45px;" class="text-center">#</th>
                            <th>Medicine / Product Name</th>
                            <th style="width: 130px;" class="text-center">In Store Stock</th>
                            <th style="width: 160px;" class="text-center">Return Qty <span class="text-danger">*</span></th>
                            <th style="width: 160px;" class="text-end">Return Rate (Rs.) <span class="text-danger">*</span></th>
                            <th style="width: 220px;">Condition</th>
                            <th style="width: 150px;" class="text-end">Refund Amount</th>
                            <th style="width: 50px;" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="directReturnTbody">
                        <!-- Rows added dynamically via JS -->
                    </tbody>
                </table>
            </div>

            <!-- Empty State Banner -->
            <div id="directEmptyState" class="p-5 text-center border rounded-3 bg-light text-muted mb-3">
                <i class="fa-solid fa-pills fs-1 mb-2 text-secondary d-block"></i>
                Abhi tak koi medicine add nahi ki gayi.<br>
                Upar diye gaye <strong>search box me alphabet ya naam type karein</strong> aur list se select karein.
            </div>

            <!-- Step 3: Refund Settlement & Remarks -->
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
                        <th>Customer</th>
                        <th>Return Reason</th>
                        <th>Refund Method</th>
                        <th class="text-center">Items</th>
                        <th class="text-end">Refund Amount</th>
                        <th class="text-center" style="width: 140px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_returns as $rr): 
                        $c_title = !empty($rr['cust_shop']) ? ($rr['cust_shop'] . ' (' . $rr['cust_name'] . ')') : ($rr['cust_name'] ?? 'Direct Customer');
                    ?>
                        <tr>
                            <td><strong class="text-danger font-monospace"><?= htmlspecialchars($rr['return_no']) ?></strong></td>
                            <td><?= date('d M Y', strtotime($rr['return_date'])) ?></td>
                            <td><span class="fw-semibold text-dark"><?= htmlspecialchars($c_title) ?></span></td>
                            <td><?= htmlspecialchars($rr['reason'] ?: 'Customer Return') ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($rr['refund_type']) ?></span></td>
                            <td class="text-center"><?= intval($rr['total_items_count']) ?> items</td>
                            <td class="text-end font-monospace fw-bold text-dark">Rs. <?= number_format($rr['total_amount'], 2) ?></td>
                            <td class="text-center">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <a href="print_return.php?id=<?= $rr['id'] ?>" target="_blank" class="btn btn-outline-primary btn-sm px-2 py-1" title="Print Return Voucher">
                                        <i class="fa-solid fa-print"></i>
                                    </a>
                                    <a href="edit_sale_return.php?id=<?= $rr['id'] ?>" class="btn btn-outline-warning btn-sm px-2 py-1 text-dark" title="Edit Sale Return">
                                        <i class="fa-solid fa-edit"></i>
                                    </a>
                                    <a href="sale_return.php?action=delete&id=<?= $rr['id'] ?>" class="btn btn-outline-danger btn-sm px-2 py-1" title="Delete Sale Return" onclick="return confirm('Kya aap waqai is Sale Return #<?= htmlspecialchars($rr['return_no']) ?> ko delete karna chahte hain? Stock aur accounts revert ho jayenge.');">
                                        <i class="fa-solid fa-trash"></i>
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
    let directRowCounter = 0;

    function handleCustomerChange(selectEl) {
        const selOption = selectEl.options[selectEl.selectedIndex];
        const badge = document.getElementById('customerInfoBadge');
        const hiddenName = document.getElementById('customerNameHidden');

        if (selectEl.value) {
            const bal = parseFloat(selOption.getAttribute('data-balance')) || 0;
            const area = selOption.getAttribute('data-area') || '';
            const text = selOption.text.split('—')[0].trim();

            document.getElementById('dispCustomerShop').textContent = text;
            document.getElementById('dispCustomerArea').textContent = area ? ('(' + area + ')') : '';
            document.getElementById('dispCustomerBal').textContent = 'Rs. ' + bal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (hiddenName) hiddenName.value = text;
            if (badge) badge.classList.remove('d-none');
        } else {
            if (badge) badge.classList.add('d-none');
            if (hiddenName) hiddenName.value = '';
        }
    }

    // Add selected medicine to return table
    function addDirectProduct(prod) {
        const tbody = document.getElementById('directReturnTbody');
        const emptyState = document.getElementById('directEmptyState');
        if (!tbody) return;

        // Check if product is already in the table
        const existingInput = document.querySelector(`.direct-pid-input[value="${prod.id}"]`);
        if (existingInput) {
            const existingRow = existingInput.closest('tr');
            const qtyField = existingRow.querySelector('.direct-qty-input');
            if (qtyField) {
                qtyField.value = (parseInt(qtyField.value) || 0) + 1;
                qtyField.focus();
                calculateDirectRow(qtyField);
            }
            hideDropdown();
            return;
        }

        directRowCounter++;
        const rIndex = directRowCounter;
        const rate = parseFloat(prod.trade_price) || 0;
        const stock = parseInt(prod.current_stock) || 0;

        const tr = document.createElement('tr');
        tr.id = 'direct_row_' + rIndex;
        tr.innerHTML = `
            <td class="text-center text-muted fw-bold row-index">${rIndex}</td>
            <td>
                <strong class="text-dark d-block">${escapeHtml(prod.name)}</strong>
                <small class="text-muted">${prod.product_code || ''} ${prod.generic_name ? '&bull; ' + escapeHtml(prod.generic_name) : ''}</small>
                <input type="hidden" name="items[${rIndex}][product_id]" class="direct-pid-input" value="${prod.id}">
                <input type="hidden" name="items[${rIndex}][item_name]" value="${escapeHtml(prod.name)}">
            </td>
            <td class="text-center">
                <span class="badge bg-light text-secondary border font-monospace">${stock} units</span>
            </td>
            <td>
                <input type="number" 
                       name="items[${rIndex}][quantity]" 
                       class="form-control form-control-sm text-center fw-bold text-danger direct-qty-input" 
                       value="1" 
                       min="1" 
                       step="1"
                       oninput="calculateDirectRow(this)">
            </td>
            <td>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted">Rs.</span>
                    <input type="number" 
                           name="items[${rIndex}][unit_price]" 
                           class="form-control form-control-sm text-end fw-semibold direct-price-input" 
                           value="${rate.toFixed(2)}" 
                           min="0" 
                           step="0.01" 
                           oninput="calculateDirectRow(this)">
                </div>
            </td>
            <td>
                <select name="items[${rIndex}][condition]" class="form-select form-select-sm">
                    <option value="Good / Resalable">Good (Restock in Store)</option>
                    <option value="Damaged / Expiry Claim">Damaged / Expiry (Discard)</option>
                </select>
            </td>
            <td class="text-end font-monospace fw-bold text-dark direct-row-total">
                Rs. ${rate.toFixed(2)}
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-outline-danger btn-sm px-2 py-1" onclick="removeDirectRow(this)" title="Remove item">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </td>
        `;

        tbody.appendChild(tr);
        if (emptyState) emptyState.classList.add('d-none');

        updateRowIndices();
        recalculateGrandRefund();
        hideDropdown();

        // Automatically focus on quantity field
        const addedQty = tr.querySelector('.direct-qty-input');
        if (addedQty) {
            addedQty.focus();
            addedQty.select();
        }
    }

    function calculateDirectRow(el) {
        const tr = el.closest('tr');
        if (!tr) return;

        const qtyInput = tr.querySelector('.direct-qty-input');
        const priceInput = tr.querySelector('.direct-price-input');
        const totalEl = tr.querySelector('.direct-row-total');

        const qty = parseInt(qtyInput.value) || 0;
        const price = parseFloat(priceInput.value) || 0;
        const total = qty * price;

        if (totalEl) {
            totalEl.textContent = 'Rs. ' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        recalculateGrandRefund();
    }

    function removeDirectRow(btn) {
        const tr = btn.closest('tr');
        if (tr) {
            tr.remove();
            updateRowIndices();
            recalculateGrandRefund();

            const tbody = document.getElementById('directReturnTbody');
            const emptyState = document.getElementById('directEmptyState');
            if (tbody && tbody.children.length === 0 && emptyState) {
                emptyState.classList.remove('d-none');
            }
        }
    }

    function updateRowIndices() {
        document.querySelectorAll('#directReturnTbody tr').forEach((tr, index) => {
            const idxEl = tr.querySelector('.row-index');
            if (idxEl) idxEl.textContent = index + 1;
        });
    }

    function recalculateGrandRefund() {
        let grand = 0;
        document.querySelectorAll('#directReturnTbody tr').forEach(tr => {
            const qtyInput = tr.querySelector('.direct-qty-input');
            const priceInput = tr.querySelector('.direct-price-input');
            if (qtyInput && priceInput) {
                const q = parseInt(qtyInput.value) || 0;
                const p = parseFloat(priceInput.value) || 0;
                grand += (q * p);
            }
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

    function focusProductSearch() {
        const searchInput = document.getElementById('directProductSearch');
        if (searchInput) {
            searchInput.focus();
            fetchProductResults(searchInput.value.trim());
        }
    }

    function hideDropdown() {
        const dd = document.getElementById('productResultsDropdown');
        if (dd) dd.classList.add('d-none');
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

    let searchTimeout = null;
    function fetchProductResults(query) {
        const dd = document.getElementById('productResultsDropdown');
        if (!dd) return;

        fetch('sale_return.php?action=search_product&q=' + encodeURIComponent(query))
            .then(res => res.json())
            .then(data => {
                dd.innerHTML = '';
                if (!data || data.length === 0) {
                    dd.innerHTML = '<div class="p-3 text-muted text-center small"><i class="fa-solid fa-circle-exclamation me-1"></i> Koi medicine nahi mili.</div>';
                    dd.classList.remove('d-none');
                    return;
                }

                data.forEach(p => {
                    const item = document.createElement('div');
                    item.className = 'product-search-item d-flex justify-content-between align-items-center';
                    item.innerHTML = `
                        <div>
                            <strong class="text-dark d-block">${escapeHtml(p.name)}</strong>
                            <small class="text-muted">${p.product_code || ''} ${p.generic_name ? '&bull; ' + escapeHtml(p.generic_name) : ''} ${p.company_name ? '&bull; ' + escapeHtml(p.company_name) : ''}</small>
                        </div>
                        <div class="text-end">
                            <span class="fw-bold text-danger font-monospace d-block">Rs. ${parseFloat(p.trade_price).toFixed(2)}</span>
                            <span class="badge bg-light text-secondary border font-monospace">Stock: ${p.current_stock || 0}</span>
                        </div>
                    `;
                    item.addEventListener('click', () => {
                        addDirectProduct(p);
                        const sInput = document.getElementById('directProductSearch');
                        if (sInput) sInput.value = '';
                    });
                    dd.appendChild(item);
                });

                dd.classList.remove('d-none');
            })
            .catch(err => {
                console.error(err);
            });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('directProductSearch');
        const dropdown = document.getElementById('productResultsDropdown');

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const q = this.value.trim();
                searchTimeout = setTimeout(() => {
                    fetchProductResults(q);
                }, 150);
            });

            searchInput.addEventListener('focus', function() {
                fetchProductResults(this.value.trim());
            });
        }

        // Close dropdown when clicked outside
        document.addEventListener('click', function(e) {
            if (dropdown && !dropdown.contains(e.target) && e.target !== searchInput) {
                hideDropdown();
            }
        });

        // Form submit validation
        const returnForm = document.getElementById('returnForm');
        if (returnForm) {
            returnForm.addEventListener('submit', function(e) {
                const customerSelect = document.getElementById('customerSelect');
                if (!customerSelect || !customerSelect.value) {
                    e.preventDefault();
                    alert('⚠️ Barahe karam pehle Customer select karein!\n(Please select a customer for the return)');
                    if (customerSelect) customerSelect.focus();
                    return false;
                }

                const qtyInputs = document.querySelectorAll('.direct-qty-input');
                if (qtyInputs.length === 0) {
                    e.preventDefault();
                    alert('⚠️ Barahe karam kam az kam 1 medicine add karein!\n(Please search and add at least one medicine to return)');
                    focusProductSearch();
                    return false;
                }

                let totalReturnQty = 0;
                qtyInputs.forEach(input => {
                    totalReturnQty += (parseInt(input.value) || 0);
                });

                if (totalReturnQty <= 0) {
                    e.preventDefault();
                    alert('⚠️ Barahe karam kam az kam 1 item ki return quantity 1 ya zyada darj karein!');
                    const firstInput = document.querySelector('.direct-qty-input');
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
