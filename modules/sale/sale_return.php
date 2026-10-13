<?php
/**
 * Bestway Wholesale Distribution - Sales Returns
 * Clean ERP Table: Customer column, dedicated View Products modal, and streamlined header.
 */
$page_title = "Sales Returns";
$compact_page_heading = true; // Prevents duplicate title heading in page body
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

                // 4. Sync customer balance if linked
                if ($cust_id > 0 && function_exists('updateCustomerBalance')) {
                    try { updateCustomerBalance($pdo, $cust_id); } catch (Exception $e) {}
                }

                $_SESSION['flash_message'] = "Sale Return #{$ret_data['return_no']} deleted successfully and stock reverted.";
                $_SESSION['flash_type'] = "success";
                header("Location: sale_return.php");
                exit;
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

// Fetch Cash & Bank Accounts
$cash_accounts = [];
$bank_accounts = [];
if ($db_connected && $pdo) {
    try {
        $cash_accounts = $pdo->query("SELECT id, account_name, balance FROM cash_accounts ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        $bank_accounts = $pdo->query("SELECT id, bank_name, account_title, account_number FROM bank_accounts WHERE status = 'Active' ORDER BY bank_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Handle Form Submission: Create Sale Return (Direct Product-Wise without Customer)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['save_return'])) {
    $return_no       = trim($_POST['return_no'] ?? $auto_return_no);
    $return_date     = trim($_POST['return_date'] ?? date('Y-m-d'));
    $refund_type     = trim($_POST['refund_type'] ?? 'Direct Restock');
    $cash_account_id = !empty($_POST['cash_account_id']) ? intval($_POST['cash_account_id']) : null;
    $bank_account_id = !empty($_POST['bank_account_id']) ? intval($_POST['bank_account_id']) : null;
    $reason          = trim($_POST['reason'] ?? '');
    $items           = $_POST['items'] ?? [];

    if (empty($items) || !is_array($items)) {
        $message = "Please search and add at least 1 medicine to return.";
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

            // 1. Insert into sale_returns table (customer_id = NULL)
            $stmt_ret = $pdo->prepare("
                INSERT INTO sale_returns (
                    return_no, sale_id, invoice_id, return_date, customer_id, refund_type,
                    total_amount, deduction_amount, net_refund_amount, reason, cash_account_id, bank_account_id, status, created_by
                ) VALUES (?, NULL, NULL, ?, NULL, ?, ?, 0.00, ?, ?, ?, ?, 'Completed', ?)
            ");
            $stmt_ret->execute([
                $return_no, $return_date, $refund_type,
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

            // 3. Deduct accounts if Cash or Bank Refund selected
            if ($refund_type === 'Cash Refund' && $cash_account_id) {
                $pdo->prepare("UPDATE cash_accounts SET balance = GREATEST(0, balance - ?) WHERE id = ?")->execute([$total_return_amount, $cash_account_id]);
            } elseif ($refund_type === 'Bank Refund' && $bank_account_id) {
                $pdo->prepare("UPDATE bank_accounts SET current_balance = GREATEST(0, current_balance - ?) WHERE id = ?")->execute([$total_return_amount, $bank_account_id]);
            }

            $pdo->commit();

            $_SESSION['flash_message'] = "Sale Return #{$return_no} saved successfully and inventory restored! <a href='print_return.php?id={$return_id}' target='_blank' class='fw-bold text-dark text-decoration-underline ms-2'><i class='fa-solid fa-print'></i> Print Voucher</a>";
            $_SESSION['flash_type'] = "success";
            header("Location: sale_return.php");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $message = "Error: " . $e->getMessage();
            $msg_type = "danger";
        }
    }
}

// Search & Date Filter Parameters
$search    = trim($_GET['search'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to   = trim($_GET['date_to'] ?? '');

$where_clauses = ["1=1"];
$params = [];

if ($search !== '') {
    $where_clauses[] = "(sr.return_no LIKE :s1 OR sr.reason LIKE :s2 OR c.name LIKE :s3 OR c.shop_name LIKE :s4 OR sr.id IN (
        SELECT sri_sub.return_id FROM sale_return_items sri_sub 
        JOIN products p_sub ON p_sub.id = sri_sub.product_id 
        WHERE p_sub.name LIKE :s5 OR p_sub.product_code LIKE :s6
    ))";
    $like = "%$search%";
    $params[':s1'] = $like;
    $params[':s2'] = $like;
    $params[':s3'] = $like;
    $params[':s4'] = $like;
    $params[':s5'] = $like;
    $params[':s6'] = $like;
}

if ($date_from !== '') {
    $where_clauses[] = "sr.return_date >= :df";
    $params[':df'] = $date_from;
}

if ($date_to !== '') {
    $where_clauses[] = "sr.return_date <= :dt";
    $params[':dt'] = $date_to;
}

$where_sql = implode(' AND ', $where_clauses);

// Pagination
$per_page = 40;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$total_records = 0;
$all_returns = [];
$items_by_return = [];

if ($db_connected && $pdo) {
    try {
        $stmt_cnt = $pdo->prepare("SELECT COUNT(*) FROM sale_returns sr LEFT JOIN customers c ON c.id = sr.customer_id WHERE $where_sql");
        $stmt_cnt->execute($params);
        $total_records = intval($stmt_cnt->fetchColumn());

        $sql_list = "
            SELECT sr.*, 
                   c.name as cust_name, c.shop_name as cust_shop
            FROM sale_returns sr
            LEFT JOIN customers c ON c.id = sr.customer_id
            WHERE $where_sql
            ORDER BY sr.id DESC 
            LIMIT $per_page OFFSET $offset
        ";
        $stmt_list = $pdo->prepare($sql_list);
        $stmt_list->execute($params);
        $all_returns = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($all_returns)) {
            $r_ids = array_column($all_returns, 'id');
            $in_placeholders = implode(',', array_fill(0, count($r_ids), '?'));
            $stmt_items = $pdo->prepare("
                SELECT sri.*, 
                       COALESCE(NULLIF(p.name, ''), 'Medicine') as product_name, 
                       p.product_code, 
                       p.generic_name
                FROM sale_return_items sri
                LEFT JOIN products p ON p.id = sri.product_id
                WHERE sri.return_id IN ($in_placeholders) OR sri.sale_return_id IN ($in_placeholders)
                ORDER BY sri.id ASC
            ");
            $stmt_items->execute(array_merge($r_ids, $r_ids));
            foreach ($stmt_items->fetchAll(PDO::FETCH_ASSOC) as $row_item) {
                $rid = !empty($row_item['return_id']) ? intval($row_item['return_id']) : intval($row_item['sale_return_id']);
                $items_by_return[$rid][] = $row_item;
            }
        }
    } catch (Exception $e) {}
}

$total_pages = ceil($total_records / $per_page);

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    /* Table Card */
    .table-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.03);
        overflow: hidden;
    }

    /* Table Header & Rows */
    .table-returns thead th {
        background-color: #f8fafc;
        color: #475569;
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        border-bottom: 2px solid #e2e8f0;
        padding: 12px 14px;
        vertical-align: middle;
        white-space: nowrap;
    }
    .table-returns tbody td {
        padding: 11px 14px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.88rem;
    }
    .table-returns tbody tr:hover {
        background-color: #fcfdfe;
    }

    /* Action Buttons */
    .action-btn {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.82rem;
        transition: all 0.15s ease;
    }

    /* Autocomplete dropdown in modal */
    .product-search-box {
        position: relative;
    }
    .product-results-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 1070;
        max-height: 280px;
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
    .product-search-item:hover {
        background: #fee2e2;
        border-left: 4px solid #dc2626;
    }
</style>

<!-- Top Action Bar (Matching sales.php layout, no duplicate heading) -->
<div class="d-flex justify-content-end align-items-center mb-3">
    <div class="d-flex flex-wrap gap-2">
        <a href="sales.php" class="btn btn-sm btn-outline-secondary bg-white shadow-sm px-3 rounded-2">
            <i class="fa-solid fa-receipt me-1"></i> Sales Invoices
        </a>
    </div>
</div>

<!-- Alert Notifications -->
<?php if (!empty($message)): ?>
    <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show border-0 shadow-sm rounded-3 d-flex align-items-center mb-3" role="alert">
        <i class="fa-solid <?= $msg_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> fs-5 me-2"></i>
        <div class="fw-medium"><?= $message ?></div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Returns Table Card with Filter Bar & New Return Button -->
<div class="table-card mb-4">
    <div class="p-3 border-bottom bg-white d-flex flex-wrap align-items-center justify-content-between gap-3">
        <!-- Search & Date Filter Form -->
        <form method="GET" action="sale_return.php" class="d-flex flex-wrap align-items-center gap-2 flex-grow-1">
            <div class="input-group input-group-sm" style="max-width: 320px;">
                <span class="input-group-text bg-light text-muted border-end-0"><i class="fa-solid fa-search"></i></span>
                <input type="text" name="search" class="form-control border-start-0" placeholder="Search voucher, customer, remarks..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="d-flex align-items-center gap-1">
                <input type="date" name="date_from" class="form-control form-control-sm" style="width: 140px;" value="<?= htmlspecialchars($date_from) ?>" title="From Date">
                <span class="text-muted small">to</span>
                <input type="date" name="date_to" class="form-control form-control-sm" style="width: 140px;" value="<?= htmlspecialchars($date_to) ?>" title="To Date">
                <button type="submit" class="btn btn-sm btn-danger fw-semibold px-3" title="Filter">
                    <i class="fa-solid fa-filter me-1"></i> Filter
                </button>
                <?php if ($search !== '' || $date_from !== '' || $date_to !== ''): ?>
                    <a href="sale_return.php" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
                        <i class="fa-solid fa-times"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Right Side: Single "New Return" Button -->
        <div>
            <button type="button" class="btn btn-sm btn-danger fw-semibold shadow-sm px-3 rounded-2" data-toggle="modal" data-target="#addSaleReturnModal" data-bs-toggle="modal" data-bs-target="#addSaleReturnModal">
                <i class="fa-solid fa-plus me-1"></i> New Return
            </button>
        </div>
    </div>

    <!-- Main Records Table -->
    <div class="table-responsive">
        <table class="table table-returns mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;" class="text-center">#</th>
                    <th style="width: 150px;">Voucher No</th>
                    <th style="width: 110px;">Date</th>
                    <th>Customer</th>
                    <th>Remarks</th>
                    <th style="width: 130px;" class="text-end">Total Amount</th>
                    <th style="width: 140px;" class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($all_returns)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-box-open fs-2 mb-2 d-block text-secondary"></i>
                            No Sales Return records found.
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-danger fw-semibold" data-toggle="modal" data-target="#addSaleReturnModal" data-bs-toggle="modal" data-bs-target="#addSaleReturnModal">
                                    <i class="fa-solid fa-plus me-1"></i> Create Sale Return
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $row_num = $offset;
                    foreach ($all_returns as $rr): 
                        $row_num++;
                        $c_display = !empty($rr['cust_shop']) ? ($rr['cust_shop'] . ' (' . $rr['cust_name'] . ')') : ($rr['cust_name'] ?: 'Direct / Counter Customer');
                    ?>
                        <tr>
                            <!-- Row Index -->
                            <td class="text-center text-muted fw-bold"><?= $row_num ?></td>

                            <!-- Voucher No (Only Return #, No Customer Name) -->
                            <td class="text-nowrap">
                                <span class="font-monospace fw-bold text-danger fs-6"><?= htmlspecialchars($rr['return_no']) ?></span>
                            </td>

                            <!-- Date -->
                            <td class="text-nowrap text-secondary">
                                <?= date('d M Y', strtotime($rr['return_date'])) ?>
                            </td>

                            <!-- Customer Column (Restored to its own field) -->
                            <td>
                                <div class="fw-semibold text-dark">
                                    <?= htmlspecialchars($c_display) ?>
                                </div>
                            </td>

                            <!-- Remarks Column -->
                            <td>
                                <?php if (!empty($rr['reason'])): ?>
                                    <div class="text-secondary small" style="line-height: 1.35;">
                                        <?= nl2br(htmlspecialchars($rr['reason'])) ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">&mdash;</span>
                                <?php endif; ?>
                            </td>

                            <!-- Total Amount Column -->
                            <td class="text-end text-nowrap">
                                <span class="fw-bold font-monospace text-dark fs-6">
                                    Rs. <?= number_format($rr['total_amount'], 2) ?>
                                </span>
                            </td>

                            <!-- Action Column -->
                            <td class="text-center text-nowrap">
                                <button type="button" 
                                        class="btn btn-light btn-sm action-btn text-info border" 
                                        title="View Returned Products"
                                        onclick="viewReturnItems(<?= $rr['id'] ?>, '<?= htmlspecialchars($rr['return_no']) ?>', '<?= date('d M Y', strtotime($rr['return_date'])) ?>', '<?= htmlspecialchars(addslashes($c_display)) ?>', '<?= htmlspecialchars(addslashes($rr['reason'] ?? '')) ?>')">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                                <a href="print_return.php?id=<?= $rr['id'] ?>" target="_blank" class="btn btn-light btn-sm action-btn text-primary border" title="Print Return Voucher">
                                    <i class="fa-solid fa-print"></i>
                                </a>
                                <a href="edit_sale_return.php?id=<?= $rr['id'] ?>" class="btn btn-light btn-sm action-btn text-warning border" title="Edit Sale Return">
                                    <i class="fa-solid fa-edit"></i>
                                </a>
                                <a href="sale_return.php?action=delete&id=<?= $rr['id'] ?>" class="btn btn-light btn-sm action-btn text-danger border" onclick="return confirm('Are you sure you want to delete Sale Return #<?= htmlspecialchars($rr['return_no']) ?>? Restored stock will be deducted back.')" title="Delete Return">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="d-flex flex-wrap align-items-center justify-content-between p-3 border-top bg-light">
            <span class="text-muted small">Showing page <?= $page ?> of <?= $total_pages ?> (<?= $total_records ?> total returns)</span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="sale_return.php?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>">Previous</a>
                        </li>
                    <?php endif; ?>
                    
                    <?php 
                    $start_p = max(1, $page - 2);
                    $end_p   = min($total_pages, $page + 2);
                    for ($p = $start_p; $p <= $end_p; $p++): 
                    ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="sale_return.php?page=<?= $p ?>&search=<?= urlencode($search) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="sale_return.php?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- ======================================================== -->
<!-- MODAL: ADD NEW SALE RETURN (DIRECT WITHOUT CUSTOMER)    -->
<!-- ======================================================== -->
<div class="modal fade" id="addSaleReturnModal" tabindex="-1" role="dialog" aria-labelledby="addSaleReturnModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg rounded-3">
            
            <form action="sale_return.php" method="POST" id="newReturnForm">
                <!-- Modal Header -->
                <div class="modal-header bg-danger text-white py-3 px-4">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-rotate-left fs-5"></i>
                        <h5 class="modal-title fw-bold mb-0" id="addSaleReturnModalLabel">Create Sales Return</h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white ml-auto" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <!-- Modal Body -->
                <div class="modal-body p-4">

                    <!-- Return Info Row -->
                    <div class="row g-3 mb-4 pb-3 border-bottom">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1">Return Voucher Number</label>
                            <input type="text" name="return_no" class="form-control font-monospace fw-bold bg-light text-danger" value="<?= htmlspecialchars($auto_return_no) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1">Return Date <span class="text-danger">*</span></label>
                            <input type="date" name="return_date" class="form-control fw-semibold" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <!-- Live Product Search Bar -->
                    <div class="product-search-box mb-3">
                        <label class="form-label small fw-bold text-muted mb-1">Search Medicine to Return <span class="text-danger">*</span></label>
                        <div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden">
                            <span class="input-group-text bg-white border-end-0 text-danger"><i class="fa-solid fa-search"></i></span>
                            <input type="text" id="directProductSearch" class="form-control border-start-0 ps-0 fs-6" placeholder="Type medicine name or code (e.g. Panadol, Augmentin, Clobevate)..." autocomplete="off">
                            <button type="button" class="btn btn-danger px-4 fw-bold" onclick="focusProductSearch()">
                                <i class="fa-solid fa-magnifying-glass me-1"></i> Search
                            </button>
                        </div>
                        <!-- Live Search Results Dropdown -->
                        <div id="productResultsDropdown" class="product-results-dropdown d-none"></div>
                    </div>

                    <!-- Direct Return Table -->
                    <div class="table-responsive border rounded-3 overflow-hidden shadow-sm mb-3">
                        <table class="table mb-0" id="directReturnTable">
                            <thead>
                                <tr class="table-light">
                                    <th style="width: 45px;" class="text-center">#</th>
                                    <th>Medicine Name</th>
                                    <th style="width: 120px;" class="text-center">Store Stock</th>
                                    <th style="width: 150px;" class="text-center">Return Qty <span class="text-danger">*</span></th>
                                    <th style="width: 160px;" class="text-end">Return Rate (Rs.) <span class="text-danger">*</span></th>
                                    <th style="width: 200px;">Condition</th>
                                    <th style="width: 140px;" class="text-end">Refund Amount</th>
                                    <th style="width: 50px;" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="directReturnTbody">
                                <!-- Dynamic rows added here -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Empty State Banner -->
                    <div id="directEmptyState" class="p-4 text-center border rounded-3 bg-light text-muted mb-4">
                        <i class="fa-solid fa-pills fs-2 mb-2 text-secondary d-block"></i>
                        No medicines added yet.<br>
                        Type in the search box above to add medicines to return.
                    </div>

                    <!-- Remarks & Refund Settlement Row -->
                    <div class="row g-3 pt-3 border-top">
                        <div class="col-md-7">
                            <label class="form-label small fw-bold text-muted mb-1">Remarks / Reason</label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="Enter reason: e.g. Customer excess stock, batch claim, expired medicine, damaged packaging..."></textarea>
                            <small class="text-muted">These remarks will be displayed in the return records table.</small>
                        </div>

                        <div class="col-md-5">
                            <div class="p-3 border rounded-3 bg-light">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted mb-1">Refund Method</label>
                                    <select name="refund_type" id="refundTypeSelect" class="form-select fw-semibold" onchange="toggleAccountDropdown()">
                                        <option value="Direct Restock">Direct Return (Stock Restock Only)</option>
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
                                    <span class="fw-bold text-dark fs-6">Grand Refund Total:</span>
                                    <span class="fw-bold text-danger fs-5 font-monospace" id="grandRefundTotalDisplay">Rs. 0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-outline-secondary px-4 fw-semibold" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_return" id="saveReturnBtn" class="btn btn-danger px-4 fw-bold shadow-sm">
                        <i class="fa-solid fa-check me-1"></i> Save Return
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: VIEW RETURNED PRODUCTS (EYE BUTTON)               -->
<!-- ======================================================== -->
<div class="modal fade" id="viewItemsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header bg-light py-3 px-4 border-bottom">
                <div>
                    <h5 class="modal-title fw-bold mb-0 text-dark" id="viewModalReturnNoTitle">Sale Return Details</h5>
                    <small class="text-muted" id="viewModalMetaInfo"></small>
                </div>
                <button type="button" class="btn-close ml-auto" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Voucher Meta Summary -->
                <div class="row g-2 p-3 bg-light rounded-3 mb-3 border">
                    <div class="col-md-4">
                        <span class="text-muted small d-block">Return Voucher:</span>
                        <strong class="text-danger font-monospace fs-6" id="viewModalReturnNo"></strong>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted small d-block">Return Date:</span>
                        <strong class="text-dark" id="viewModalDate"></strong>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted small d-block">Customer / Shop:</span>
                        <strong class="text-dark" id="viewModalCustomer"></strong>
                    </div>
                    <div class="col-12 mt-2 pt-2 border-top">
                        <span class="text-muted small d-block">Remarks / Reason:</span>
                        <span class="text-secondary small fst-italic" id="viewModalRemarks"></span>
                    </div>
                </div>

                <!-- Products Table -->
                <div class="table-responsive border rounded-3">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 45px;" class="text-center">#</th>
                                <th>Medicine Name</th>
                                <th>Generic / Code</th>
                                <th class="text-center">Condition</th>
                                <th class="text-center">Quantity</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Total Price</th>
                            </tr>
                        </thead>
                        <tbody id="viewItemsModalTbody">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="4" class="text-end">Total Refund:</td>
                                <td class="text-center font-monospace text-danger" id="viewItemsTotalQty">0</td>
                                <td></td>
                                <td class="text-end text-danger font-monospace fs-6" id="viewItemsTotalPrice">Rs. 0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light px-4 py-2">
                <button type="button" class="btn btn-secondary btn-sm px-4 fw-semibold" data-dismiss="modal" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    const returnItemsStore = <?= json_encode($items_by_return, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    let directRowCounter = 0;

    // View products modal when eye icon is clicked
    function viewReturnItems(returnId, returnNo, returnDate, customerName, remarks) {
        const items = returnItemsStore[returnId] || [];
        const modalEl = document.getElementById('viewItemsModal');

        document.getElementById('viewModalReturnNoTitle').textContent = 'Sale Return #' + returnNo + ' Details';
        document.getElementById('viewModalMetaInfo').textContent = returnDate + ' • ' + (items.length) + ' medicine item(s)';
        document.getElementById('viewModalReturnNo').textContent = returnNo;
        document.getElementById('viewModalDate').textContent = returnDate;
        document.getElementById('viewModalCustomer').textContent = customerName || 'Direct / Counter Customer';
        document.getElementById('viewModalRemarks').textContent = remarks ? remarks : '— None —';

        const tbody = document.getElementById('viewItemsModalTbody');
        const totalQtyEl = document.getElementById('viewItemsTotalQty');
        const totalPriceEl = document.getElementById('viewItemsTotalPrice');

        if (!tbody) return;
        tbody.innerHTML = '';

        let grandQty = 0;
        let grandTotal = 0;

        if (items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No items recorded for this return voucher.</td></tr>';
        } else {
            items.forEach((it, idx) => {
                const qty = parseInt(it.quantity) || 0;
                const price = parseFloat(it.unit_price) || 0;
                const lineTotal = parseFloat(it.total_price) || (qty * price);
                grandQty += qty;
                grandTotal += lineTotal;

                const isDamaged = (it.condition === 'Damaged / Expiry Claim');
                const condBadge = isDamaged 
                    ? '<span class="badge bg-warning text-dark border">Damaged / Expiry</span>' 
                    : '<span class="badge bg-light text-success border">Good (Restocked)</span>';

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="text-center text-muted fw-bold">${idx + 1}</td>
                    <td>
                        <strong class="text-dark">${escapeHtml(it.product_name || 'Medicine')}</strong>
                    </td>
                    <td>
                        <small class="text-muted">${escapeHtml(it.product_code || '')} ${it.generic_name ? '&bull; ' + escapeHtml(it.generic_name) : ''}</small>
                    </td>
                    <td class="text-center">${condBadge}</td>
                    <td class="text-center font-monospace fw-bold text-danger">${qty}</td>
                    <td class="text-end font-monospace">Rs. ${price.toFixed(2)}</td>
                    <td class="text-end font-monospace fw-bold text-dark">Rs. ${lineTotal.toFixed(2)}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        if (totalQtyEl) totalQtyEl.textContent = grandQty;
        if (totalPriceEl) totalPriceEl.textContent = 'Rs. ' + grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        // Open modal
        if (window.jQuery && $(modalEl).modal) {
            $(modalEl).modal('show');
        } else if (window.bootstrap && window.bootstrap.Modal) {
            new bootstrap.Modal(modalEl).show();
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

        // Focus on quantity field
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
                    dd.innerHTML = '<div class="p-3 text-muted text-center small"><i class="fa-solid fa-circle-exclamation me-1"></i> No medicines found matching your search.</div>';
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

        // Auto-focus search input when modal is opened
        const modalEl = document.getElementById('addSaleReturnModal');
        if (modalEl) {
            if (window.jQuery) {
                $(modalEl).on('shown.bs.modal', function () {
                    if (searchInput) searchInput.focus();
                });
            }
            modalEl.addEventListener('shown.bs.modal', function() {
                if (searchInput) searchInput.focus();
            });
        }

        // Form submit validation
        const returnForm = document.getElementById('newReturnForm');
        if (returnForm) {
            returnForm.addEventListener('submit', function(e) {
                const qtyInputs = document.querySelectorAll('.direct-qty-input');
                if (qtyInputs.length === 0) {
                    e.preventDefault();
                    alert('Please search and add at least one medicine to return.');
                    focusProductSearch();
                    return false;
                }

                let totalReturnQty = 0;
                qtyInputs.forEach(input => {
                    totalReturnQty += (parseInt(input.value) || 0);
                });

                if (totalReturnQty <= 0) {
                    e.preventDefault();
                    alert('Please enter a return quantity of 1 or more for at least one item.');
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
