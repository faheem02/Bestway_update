<?php
/**
 * Bestway Wholesale Distribution - View All Sales Invoices
 */
$page_title = "Sale Invoices";
$compact_page_heading = true;
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

// Database connection for sales invoices
if (empty($conn) || $conn->connect_error) {
    $conn = @new mysqli($db_host ?? 'localhost', $db_user ?? 'root', $db_pass ?? '', $db_name ?? 'bestway_wholesale');
}
if (!$conn || $conn->connect_error) {
    // Graceful fallback
}


$message = "";
$msg_type = "";

// Handle Delete Invoice Action (with complete stock reversion)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id'])) {
    $del_id = intval($_GET['id']);
    try {
        // Fetch invoice
        $stmt_inv = $conn->prepare("SELECT * FROM sales_invoices WHERE id = ?");
        $stmt_inv->bind_param("i", $del_id);
        $stmt_inv->execute();
        $inv_res = $stmt_inv->get_result();
        $inv = $inv_res->fetch_assoc();
        $stmt_inv->close();

        if ($inv) {
            $conn->begin_transaction();
            if ($db_connected && $pdo) {
                $pdo->beginTransaction();
            }

            // 1. Fetch items to revert stock
            $stmt_items = $conn->prepare("SELECT product_id, item_name, quantity FROM sale_items WHERE invoice_id = ?");
            $stmt_items->bind_param("i", $del_id);
            $stmt_items->execute();
            $items_res = $stmt_items->get_result();
            $items = $items_res->fetch_all(MYSQLI_ASSOC);
            $stmt_items->close();

            if ($db_connected && $pdo) {
                $stmt_add_stock = $pdo->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = ?");
                $stmt_add_batch = $pdo->prepare("UPDATE product_batches SET current_stock = current_stock + ? WHERE product_id = ? ORDER BY id DESC LIMIT 1");

                foreach ($items as $it) {
                    $pid = intval($it['product_id'] ?? 0);
                    $qty = intval($it['quantity'] ?? 0);
                    if ($pid > 0 && $qty > 0) {
                        $stmt_add_stock->execute([$qty, $pid]);
                        $stmt_add_batch->execute([$qty, $pid]);
                    }
                }

                // 2. Revert Cash / Bank Account if payment was recorded
                $paid_amt = floatval($inv['paid_amount'] ?? 0);
                if ($paid_amt > 0) {
                    if (!empty($inv['cash_account_id'])) {
                        $pdo->prepare("UPDATE cash_accounts SET balance = GREATEST(0, balance - ?) WHERE id = ?")->execute([$paid_amt, $inv['cash_account_id']]);
                    } elseif (!empty($inv['bank_account_id'])) {
                        $pdo->prepare("UPDATE bank_accounts SET current_balance = GREATEST(0, current_balance - ?) WHERE id = ?")->execute([$paid_amt, $inv['bank_account_id']]);
                    }
                }
            }

            // 3. Delete items & invoice
            $conn->query("DELETE FROM sale_items WHERE invoice_id = $del_id");
            $conn->query("DELETE FROM sales_invoices WHERE id = $del_id");

            // 4. Remove from Credit Book (Udhaar)
            if ($db_connected && $pdo) {
                $pdo->prepare("DELETE FROM udhaar_book WHERE invoice_id = ? OR invoice_no = ?")->execute([$del_id, $inv['invoice_no']]);
                
                // 5. Remove Delivery Challan
                $ch_ids = $pdo->prepare("SELECT id FROM delivery_challans WHERE invoice_id = ?");
                $ch_ids->execute([$del_id]);
                foreach ($ch_ids->fetchAll(PDO::FETCH_COLUMN) as $cid) {
                    $pdo->prepare("DELETE FROM delivery_challan_items WHERE challan_id = ?")->execute([$cid]);
                    $pdo->prepare("DELETE FROM delivery_challans WHERE id = ?")->execute([$cid]);
                }
            }

            $conn->commit();
            if ($db_connected && $pdo) {
                $pdo->commit();
            }

            // Refresh customer balance after invoice deletion
            if ($db_connected && $pdo && !empty($inv['customer_id'])) {
                try { updateCustomerBalance($pdo, intval($inv['customer_id'])); } catch (Exception $e) {}
            }

            $message = "Sales Invoice #{$inv['invoice_no']} deleted successfully and stock restored to inventory.";
            $msg_type = "success";
        } else {
            throw new Exception("Invoice record not found.");
        }
    } catch (Exception $e) {
        $conn->rollback();
        if ($db_connected && $pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = "Delete error: " . $e->getMessage();
        $msg_type = "danger";
    }
}


// Filter parameters
$search       = trim($_GET['search'] ?? '');
$booker_id    = intval($_GET['booker_id'] ?? 0);
$route_id     = intval($_GET['route_id'] ?? 0);
$status       = trim($_GET['status'] ?? '');
$payment_mode = trim($_GET['payment_mode'] ?? '');
$start_date   = trim($_GET['start_date'] ?? '');
$end_date     = trim($_GET['end_date'] ?? '');

// Build where clause
$where_clauses = ["1=1"];

if (!isAdmin()) {
    $uid = intval($_SESSION['user_id'] ?? 0);
    $bkid = currentBookerId($pdo);
    if ($bkid) {
        $where_clauses[] = "(created_by = $uid OR booker_id = $bkid)";
    } else {
        $where_clauses[] = "created_by = $uid";
    }
}

if (!empty($search)) {
    $s_esc = $conn->real_escape_string($search);
    $where_clauses[] = "(invoice_no LIKE '%$s_esc%' OR customer_name LIKE '%$s_esc%' OR booker_name LIKE '%$s_esc%' OR route_name LIKE '%$s_esc%')";
}
if ($booker_id > 0) {
    $where_clauses[] = "booker_id = $booker_id";
}
if ($route_id > 0) {
    $where_clauses[] = "route_id = $route_id";
}
if (!empty($status)) {
    if ($status === 'Paid') {
        $where_clauses[] = "balance_due <= 0.01";
    } elseif ($status === 'Partial') {
        $where_clauses[] = "(paid_amount > 0 AND balance_due > 0.01)";
    } elseif ($status === 'Unpaid') {
        $where_clauses[] = "(paid_amount <= 0 AND balance_due > 0.01)";
    }
}
if (!empty($payment_mode)) {
    $pm_esc = $conn->real_escape_string($payment_mode);
    $where_clauses[] = "payment_method = '$pm_esc'";
}
if (!empty($start_date)) {
    $sd_esc = $conn->real_escape_string($start_date);
    $where_clauses[] = "invoice_date >= '$sd_esc'";
}
if (!empty($end_date)) {
    $ed_esc = $conn->real_escape_string($end_date);
    $where_clauses[] = "invoice_date <= '$ed_esc'";
}

$where_sql = implode(" AND ", $where_clauses);

// Fetch Overall KPI Metrics
$kpi_res = $conn->query("
    SELECT 
        COUNT(*) as total_count,
        COALESCE(SUM(grand_total), 0) as total_volume,
        COALESCE(SUM(paid_amount), 0) as total_paid,
        COALESCE(SUM(balance_due), 0) as total_balance
    FROM sales_invoices
");
$kpi = $kpi_res ? $kpi_res->fetch_assoc() : [];

$tot_invoices = intval($kpi['total_count'] ?? 0);
$tot_volume   = floatval($kpi['total_volume'] ?? 0);
$tot_paid     = floatval($kpi['total_paid'] ?? 0);
$tot_balance  = floatval($kpi['total_balance'] ?? 0);

// Fetch Invoices with Items Count
$sql = "
    SELECT 
        si.*,
        c.invoice_type AS customer_invoice_type,
        e.commission_rate AS salesman_commission_rate,
        (SELECT COUNT(*) FROM sale_items WHERE invoice_id = si.id) as item_count,
        (SELECT COALESCE(SUM(quantity), 0) FROM sale_items WHERE invoice_id = si.id) as total_units
    FROM sales_invoices si
    LEFT JOIN customers c ON (c.id = si.customer_id OR (si.customer_id IS NULL AND (c.name = si.customer_name OR c.shop_name = si.customer_name)))
    LEFT JOIN employees e ON e.id = si.booker_id
    WHERE $where_sql
    ORDER BY si.id DESC
    LIMIT 300
";
$invoices_res = $conn->query($sql);
$invoices = $invoices_res ? $invoices_res->fetch_all(MYSQLI_ASSOC) : [];

// Fetch Bookers and Routes for Filter dropdowns
$bookers = [];
$routes  = [];
if ($db_connected && $pdo) {
    try {
        $bookers = getActiveBookers($pdo);
        $routes  = $pdo->query("SELECT id, name, route_code FROM routes WHERE status = 'Active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}
?>

<style>
    .page-title-badge {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        box-shadow: 0 4px 14px rgba(2, 132, 199, 0.25);
    }
    .kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 16px 20px;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
        transition: transform 0.15s ease;
    }
    .kpi-card:hover {
        transform: translateY(-2px);
    }
    .filter-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.02);
    }
    .table-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 4px 20px rgba(15, 23, 42, 0.03);
        overflow: hidden;
    }
    .table-sales thead th {
        background: #f8fafc;
        color: #475569;
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        border-bottom: 2px solid #e2e8f0;
        padding: 12px 14px;
    }
    .table-sales tbody td {
        padding: 12px 14px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.9rem;
    }
    .table-sales tbody tr:hover {
        background-color: #f8fafc;
    }
    .status-badge {
        font-size: 0.75rem;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 20px;
        letter-spacing: 0.3px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .action-btn {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.82rem;
        transition: all 0.15s ease;
    }
</style>

<!-- Top Action Bar -->
<div class="d-flex justify-content-end align-items-center mb-3">
    <div class="d-flex flex-wrap gap-2">
        <a href="print_sales.php?<?= http_build_query($_GET) ?>" target="_blank" class="btn btn-sm btn-outline-secondary bg-white fw-semibold shadow-sm px-3 rounded-3">
            <i class="fa-solid fa-print me-1"></i> Print Invoices
        </a>
        <?php if (isAdmin()): ?>
        <a href="order_booker_invoices.php" class="btn btn-sm btn-outline-info fw-semibold shadow-sm px-3 rounded-3" title="Salesman Invoices & Profit">
            <i class="fa-solid fa-user-tag me-1"></i> Salesman Invoices
        </a>
        <?php endif; ?>
        <a href="new_sale.php" class="btn btn-sm btn-primary fw-semibold shadow-sm px-3 rounded-3">
            <i class="fa-solid fa-plus me-1"></i> Create New Sale
        </a>
    </div>
</div>

<!-- Alert Notifications -->
<?php if (!empty($message)): ?>
    <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show border-0 shadow-sm rounded-3 d-flex align-items-center mb-4" role="alert">
        <i class="fa-solid <?= $msg_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> fs-5 me-2"></i>
        <div class="fw-medium"><?= htmlspecialchars($message) ?></div>
        <button type="button" class="close ml-auto" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
<?php endif; ?>

<!-- KPI Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="kpi-card border-start border-4 border-primary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-bold text-uppercase">Total Invoices</span>
                    <h3 class="fw-bold text-dark mt-1 mb-0"><?= number_format($tot_invoices) ?></h3>
                </div>
                <div class="rounded-circle bg-primary-subtle text-primary p-3">
                    <i class="fa-solid fa-file-invoice fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="kpi-card border-start border-4 border-info">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-bold text-uppercase">Total Sales Volume</span>
                    <h3 class="fw-bold text-dark mt-1 mb-0">Rs. <?= number_format($tot_volume, 2) ?></h3>
                </div>
                <div class="rounded-circle bg-info-subtle text-info p-3">
                    <i class="fa-solid fa-chart-line fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="kpi-card border-start border-4 border-success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-bold text-uppercase">Total Paid (Cash/Bank)</span>
                    <h3 class="fw-bold text-success mt-1 mb-0">Rs. <?= number_format($tot_paid, 2) ?></h3>
                </div>
                <div class="rounded-circle bg-success-subtle text-success p-3">
                    <i class="fa-solid fa-wallet fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="kpi-card border-start border-4 border-danger">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-bold text-uppercase">Receivable Balance</span>
                    <h3 class="fw-bold text-danger mt-1 mb-0">Rs. <?= number_format($tot_balance, 2) ?></h3>
                </div>
                <div class="rounded-circle bg-danger-subtle text-danger p-3">
                    <i class="fa-solid fa-hand-holding-usd fs-4"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter Panel -->
<div class="filter-card p-3 p-md-4 mb-4">
    <form method="GET" action="" class="row g-3">
        <div class="col-md-3">
            <label class="form-label small fw-bold text-muted mb-1">Search Keywords</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-start-0" placeholder="Invoice #, Pharmacy name..." value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-bold text-muted mb-1">Salesman</label>
            <select name="booker_id" class="form-select form-select-sm">
                <option value="">All Salesmen</option>
                <?php foreach ($bookers as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= ($booker_id == $b['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-bold text-muted mb-1">Route / Area</label>
            <select name="route_id" class="form-select form-select-sm">
                <option value="">All Routes</option>
                <?php foreach ($routes as $r): ?>
                    <option value="<?= $r['id'] ?>" <?= ($route_id == $r['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($r['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-bold text-muted mb-1">Payment Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                <option value="Paid" <?= ($status === 'Paid') ? 'selected' : '' ?>>Paid (Clear)</option>
                <option value="Partial" <?= ($status === 'Partial') ? 'selected' : '' ?>>Partial (Partial Paid)</option>
                <option value="Unpaid" <?= ($status === 'Unpaid') ? 'selected' : '' ?>>Unpaid (Credit)</option>
            </select>
        </div>
        <div class="col-md-3 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-sm btn-primary fw-semibold px-3 flex-grow-1">
                <i class="fa-solid fa-filter me-1"></i> Filter
            </button>
            <a href="print_sales.php?<?= http_build_query($_GET) ?>" target="_blank" class="btn btn-sm btn-outline-secondary bg-white px-3 shadow-sm" title="Print Filtered Invoices">
                <i class="fa-solid fa-print"></i>
            </a>
            <a href="sales.php" class="btn btn-sm btn-outline-secondary px-3" title="Reset Filters">
                <i class="fa-solid fa-undo"></i>
            </a>
        </div>
    </form>
</div>

<!-- Sales Data Table Card -->
<div class="table-card mb-4">
    <div class="table-responsive">
        <table class="table table-sales mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;" class="text-center">#</th>
                    <th>Invoice No</th>
                    <th>Date</th>
                    <th>Pharmacy / Customer</th>
                    <th>Salesman</th>
                    <th class="text-center">Items</th>
                    <th class="text-end">Grand Total</th>
                    <th class="text-end">Paid Amount</th>
                    <th class="text-end">Balance Due</th>
                    <th class="text-center">Status</th>
                    <th style="width: 170px;" class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="11" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-folder-open fs-2 mb-2 d-block text-secondary"></i>
                            No Sales Invoices found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoices as $idx => $inv): ?>
                        <?php
                            $bal = floatval($inv['balance_due']);
                            $paid = floatval($inv['paid_amount']);
                            $grand = floatval($inv['grand_total']);

                            if ($bal <= 0.01) {
                                $status_badge = '<span class="status-badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-check"></i> Paid</span>';
                            } elseif ($paid > 0) {
                                $status_badge = '<span class="status-badge bg-warning-subtle text-warning border border-warning-subtle"><i class="fa-solid fa-clock"></i> Partial</span>';
                            } else {
                                $status_badge = '<span class="status-badge bg-danger-subtle text-danger border border-danger-subtle"><i class="fa-solid fa-exclamation-circle"></i> Unpaid</span>';
                            }
                        ?>
                        <tr>
                            <td class="text-center text-muted fw-bold"><?= $idx + 1 ?></td>
                            <td>
                                <a href="view_sale.php?id=<?= $inv['id'] ?>" class="font-monospace fw-bold text-primary text-decoration-none">
                                    <?= htmlspecialchars($inv['invoice_no']) ?>
                                </a>
                            </td>
                            <td class="text-nowrap text-secondary">
                                <?= date('d M Y', strtotime($inv['invoice_date'])) ?>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($inv['customer_name']) ?></div>
                                
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><i class="fa-solid fa-user-tie text-muted me-1"></i><?= htmlspecialchars($inv['booker_name'] ?: 'Direct') ?></div>
                                <?php 
                                    $comm_rate = floatval($inv['salesman_commission_rate'] ?? 0);
                                    if (!empty($inv['booker_id']) && $comm_rate > 0): 
                                        $this_sale_comm = round($grand * $comm_rate / 100, 2);
                                ?>
                                    <small class="badge bg-primary-subtle text-primary border border-primary-subtle mt-1" title="Salesman Commission (<?= number_format($comm_rate, 2) ?>%)">
                                        <?= number_format($comm_rate, 2) ?>% (Rs. <?= number_format($this_sale_comm, 2) ?>)
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border"><?= intval($inv['item_count']) ?> items</span>
                            </td>
                            <td class="text-end fw-bold text-dark">
                                Rs. <?= number_format($grand, 2) ?>
                            </td>
                            <td class="text-end text-success fw-semibold">
                                Rs. <?= number_format($paid, 2) ?>
                            </td>
                            <td class="text-end fw-bold <?= $bal > 0.01 ? 'text-danger' : 'text-muted' ?>">
                                Rs. <?= number_format($bal, 2) ?>
                            </td>
                            <td class="text-center">
                                <?= $status_badge ?>
                            </td>
                            <td class="text-center text-nowrap">
                                <a href="view_sale.php?id=<?= $inv['id'] ?>" class="btn btn-light btn-sm action-btn text-info border" title="View Details">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <?php
                                    $cust_inv_type = (!empty($inv['customer_invoice_type']) && $inv['customer_invoice_type'] === 'warranty') ? 'warranty' : 'sale';
                                    $print_title   = ($cust_inv_type === 'warranty') ? 'Print Warranty Invoice' : 'Print Sale Invoice';
                                ?>
                                <a href="print_invoice.php?id=<?= $inv['id'] ?>&type=<?= $cust_inv_type ?>" target="_blank" class="btn btn-light btn-sm action-btn text-primary border" title="<?= $print_title ?>">
                                    <i class="fa-solid fa-print"></i>
                                </a>
                                <a href="edit_sale.php?id=<?= $inv['id'] ?>" class="btn btn-light btn-sm action-btn text-warning border" title="Edit Sale">
                                    <i class="fa-solid fa-edit"></i>
                                </a>
                                <a href="sale_return.php?invoice_id=<?= $inv['id'] ?>" class="btn btn-light btn-sm action-btn text-success border" title="Sale Return">
                                    <i class="fa-solid fa-undo"></i>
                                </a>
                                <a href="sales.php?action=delete&id=<?= $inv['id'] ?>" class="btn btn-light btn-sm action-btn text-danger border" onclick="return confirm('Are you sure you want to delete Invoice #<?= htmlspecialchars($inv['invoice_no']) ?>? Sold stock will be restored to inventory.')" title="Delete Sale">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
