<?php
/**
 * Bestway Wholesale Distribution - View All Purchases
 * Inward Goods Receipts, Supplier Invoices & Payment Status
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['admin']);
$page_title = "Inward Purchases & Bills";
$compact_page_heading = true;
$hide_topbar_title = true;
require_once __DIR__ . '/../../includes/header.php';

// Handle Inline Delete Action
$message = "";
$msg_type = "";
if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id'])) {
    $del_id = intval($_GET['id']);
    if ($db_connected && $pdo) {
        try {
            $pdo->beginTransaction();

            // Fetch purchase info
            $stmt_p = $pdo->prepare("SELECT * FROM purchases WHERE id = ?");
            $stmt_p->execute([$del_id]);
            $pur = $stmt_p->fetch();

            if ($pur) {
                // 1. Revert product stocks and batches
                $stmt_items = $pdo->prepare("SELECT product_id, batch_no, quantity, bonus_quantity FROM purchase_items WHERE purchase_id = ?");
                $stmt_items->execute([$del_id]);
                $items = $stmt_items->fetchAll();

                $stmt_revert_stock = $pdo->prepare("UPDATE products SET current_stock = GREATEST(0, current_stock - ?) WHERE id = ?");
                $stmt_revert_batch = $pdo->prepare("UPDATE product_batches SET current_stock = GREATEST(0, current_stock - ?) WHERE product_id = ? AND batch_no = ?");

                foreach ($items as $it) {
                    $tot_qty = intval($it['quantity']) + intval($it['bonus_quantity']);
                    $stmt_revert_stock->execute([$tot_qty, $it['product_id']]);
                    $stmt_revert_batch->execute([$tot_qty, $it['product_id'], $it['batch_no']]);
                }

                // 2. Revert Supplier Balance
                $sup_id = intval($pur['supplier_id']);
                $balance_impact = floatval($pur['balance_amount']);
                if ($balance_impact > 0 && $sup_id > 0) {
                    $pdo->prepare("UPDATE suppliers SET current_balance = GREATEST(0, current_balance - ?) WHERE id = ?")->execute([$balance_impact, $sup_id]);
                }

                // 3. Delete from ledgers & payments
                $pdo->prepare("DELETE FROM supplier_ledgers WHERE reference_no = ? AND supplier_id = ?")->execute([$pur['bill_no'], $sup_id]);
                $pdo->prepare("DELETE FROM supplier_payments WHERE purchase_id = ?")->execute([$del_id]);

                // 4. Delete items and purchase
                $pdo->prepare("DELETE FROM purchase_items WHERE purchase_id = ?")->execute([$del_id]);
                $pdo->prepare("DELETE FROM purchases WHERE id = ?")->execute([$del_id]);

                $pdo->commit();
                $message = "Purchase Bill #{$pur['bill_no']} deleted successfully and inventory/balance reverted.";
                $msg_type = "success";
            } else {
                throw new Exception("Purchase record not found.");
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $message = "Delete error: " . $e->getMessage();
            $msg_type = "danger";
        }
    }
}


// Filter Parameters
$search         = trim($_GET['search'] ?? '');
$supplier_id    = intval($_GET['supplier_id'] ?? 0);
$payment_status = trim($_GET['payment_status'] ?? '');
$payment_type   = trim($_GET['payment_type'] ?? '');
$start_date     = trim($_GET['start_date'] ?? '');
$end_date       = trim($_GET['end_date'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(p.bill_no LIKE ? OR s.name LIKE ? OR s.company_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($supplier_id > 0) {
    $where[] = "p.supplier_id = ?";
    $params[] = $supplier_id;
}

if (!empty($payment_status)) {
    $where[] = "p.payment_status = ?";
    $params[] = $payment_status;
}

if (!empty($payment_type)) {
    $where[] = "p.payment_type = ?";
    $params[] = $payment_type;
}

if (!empty($start_date)) {
    $where[] = "p.purchase_date >= ?";
    $params[] = $start_date;
}

if (!empty($end_date)) {
    $where[] = "p.purchase_date <= ?";
    $params[] = $end_date;
}

$where_sql = implode(" AND ", $where);

// Metrics
$total_purchases_count = 0;
$total_purchase_volume = 0;
$total_paid_volume     = 0;
$total_payable_balance = 0;
$purchases_list        = [];

if ($db_connected && $pdo) {
    try {
        // High Level KPIs
        $kpi = $pdo->query("
            SELECT 
                COUNT(*) as tot_count,
                SUM(grand_total) as tot_grand,
                SUM(paid_amount) as tot_paid,
                SUM(balance_amount) as tot_bal
            FROM purchases
        ")->fetch();

        $total_purchases_count = intval($kpi['tot_count'] ?? 0);
        $total_purchase_volume = floatval($kpi['tot_grand'] ?? 0);
        $total_paid_volume     = floatval($kpi['tot_paid'] ?? 0);
        $total_payable_balance = floatval($kpi['tot_bal'] ?? 0);

        // Fetch Inward Purchases with Supplier Info & Items count
        $stmt_purchases = $pdo->prepare("
            SELECT 
                p.*,
                s.name as supplier_name,
                s.company_name as supplier_company,
                s.phone as supplier_phone,
                COUNT(pi.id) as item_count,
                SUM(pi.quantity + pi.bonus_quantity) as total_packs_received,
                SUM(pi.quantity * pi.purchase_price) as gross_amount
            FROM purchases p
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            LEFT JOIN purchase_items pi ON pi.purchase_id = p.id
            WHERE $where_sql
            GROUP BY p.id, s.name, s.company_name, s.phone
            ORDER BY p.purchase_date DESC, p.id DESC
            LIMIT 200
        ");
        $stmt_purchases->execute($params);
        $purchases_list = $stmt_purchases->fetchAll();

        // Suppliers Dropdown for Filter
        $suppliers_list = $pdo->query("SELECT id, name, company_name FROM suppliers ORDER BY name ASC")->fetchAll();
    } catch (Exception $e) {}
}
?>

<style>
    :root {
        --theme-primary: #0284c7;
        --theme-primary-hover: #0369a1;
        --theme-teal: #0d9488;
        --theme-card-bg: #ffffff;
        --theme-border: #e2e8f0;
    }

    .page-title-badge {
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
        color: #ffffff;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);
    }

    .kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 16px 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .kpi-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        flex-shrink: 0;
    }

    .filter-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 16px 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        margin-bottom: 20px;
    }

    .catalog-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }

    .table thead th {
        background: #f8fafc;
        color: #475569;
        font-size: 0.76rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        border-bottom: 2px solid #e2e8f0;
        padding: 12px 14px;
        vertical-align: middle;
    }
    .table tbody td {
        padding: 12px 14px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }

    .action-btn {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        color: #475569;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        transition: all 0.15s ease;
        text-decoration: none;
        cursor: pointer;
        padding: 0;
    }
    .action-btn:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
        color: #1e293b;
    }
    .action-btn.btn-view {
        background: #f0f9ff;
        border-color: #bae6fd;
        color: #0284c7;
    }
    .action-btn.btn-view:hover {
        background: #0284c7;
        color: #ffffff !important;
    }
    .action-btn.btn-print {
        background: #f0fdf4;
        border-color: #bbf7d0;
        color: #16a34a;
    }
    .action-btn.btn-print:hover {
        background: #16a34a;
        color: #ffffff !important;
    }
    .action-btn.btn-edit {
        background: #fefce8;
        border-color: #fef08a;
        color: #d97706;
    }
    .action-btn.btn-edit:hover {
        background: #d97706;
        color: #ffffff !important;
    }
    .action-btn.btn-delete {
        background: #fef2f2;
        border-color: #fecaca;
        color: #dc2626;
    }
    .action-btn.btn-delete:hover {
        background: #dc2626;
        color: #ffffff !important;
    }
</style>

<!-- Top Title & Quick Action -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <div class="page-title-badge">
            <i class="fa-solid fa-cart-shopping"></i>
        </div>
        <div>
            <h4 class="fw-bold mb-0 text-dark">View Purchases</h4>
            <p class="text-muted small mb-0">Manage stock receiving, supplier invoices, payment balances & returns</p>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="add_purchase.php" class="btn btn-primary fw-bold px-3 py-2 shadow-sm rounded-3">
            <i class="fa-solid fa-plus me-1"></i> Add Inward Purchase
        </a>
        <a href="purchase_return.php" class="btn btn-outline-danger fw-semibold bg-white shadow-sm px-3 py-2 rounded-3">
            <i class="fa-solid fa-undo me-1 text-danger"></i> Purchase Return
        </a>
    </div>
</div>

<!-- Alert Notifications -->
<?php if (!empty($message)): ?>
    <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show border-0 shadow-sm rounded-3 d-flex align-items-center mb-4" role="alert">
        <i class="fa-solid <?= $msg_type === 'success' ? 'fa-check-circle text-success' : 'fa-exclamation-triangle text-danger' ?> fs-4 me-3"></i>
        <div class="fw-semibold"><?= htmlspecialchars($message) ?></div>
        <button type="button" class="close ml-auto" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
<?php endif; ?>

<!-- Summary KPIs -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#e0f2fe; color:#0284c7;">
                <i class="fa-solid fa-cart-arrow-down"></i>
            </div>
            <div>
                <span class="text-muted small fw-semibold">Total Purchases</span>
                <h4 class="fw-bold mb-0 text-dark">Rs. <?= number_format($total_purchase_volume, 2) ?></h4>
                <span class="text-muted small"><?= number_format($total_purchases_count) ?> Inward Bills</span>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#dcfce7; color:#16a34a;">
                <i class="fa-solid fa-check-circle"></i>
            </div>
            <div>
                <span class="text-muted small fw-semibold">Amount Paid</span>
                <h4 class="fw-bold mb-0 text-success">Rs. <?= number_format($total_paid_volume, 2) ?></h4>
                <span class="text-success small fw-semibold">Settled Inward Cash/Bank</span>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#fee2e2; color:#dc2626;">
                <i class="fa-solid fa-hand-holding-usd"></i>
            </div>
            <div>
                <span class="text-muted small fw-semibold">Outstanding Payables</span>
                <h4 class="fw-bold mb-0 text-danger">Rs. <?= number_format($total_payable_balance, 2) ?></h4>
                <span class="text-danger small fw-semibold">Credit Balance to Suppliers</span>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#fef3c7; color:#d97706;">
                <i class="fa-solid fa-boxes"></i>
            </div>
            <div>
                <span class="text-muted small fw-semibold">Filtered Bills</span>
                <h4 class="fw-bold mb-0 text-warning"><?= count($purchases_list) ?> Records</h4>
                <span class="text-muted small">Current View Match</span>
            </div>
        </div>
    </div>
</div>

<!-- Multi-Criteria Filter Bar -->
<div class="filter-card">
    <form method="GET" action="" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small fw-bold text-muted mb-1">Search</label>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search Bill #, Supplier name..." value="<?= htmlspecialchars($search) ?>">
        </div>

        <div class="col-md-3">
            <label class="form-label small fw-bold text-muted mb-1">Supplier</label>
            <select name="supplier_id" class="form-select form-select-sm">
                <option value="0">All Suppliers</option>
                <?php foreach ($suppliers_list as $sl): ?>
                    <option value="<?= $sl['id'] ?>" <?= ($supplier_id == $sl['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sl['name']) ?> <?= !empty($sl['company_name']) ? "({$sl['company_name']})" : "" ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label small fw-bold text-muted mb-1">Payment Status</label>
            <select name="payment_status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                <option value="Paid" <?= ($payment_status === 'Paid') ? 'selected' : '' ?>>Paid (Clear)</option>
                <option value="Partial" <?= ($payment_status === 'Partial') ? 'selected' : '' ?>>Partial (Half)</option>
                <option value="Unpaid" <?= ($payment_status === 'Unpaid') ? 'selected' : '' ?>>Unpaid (Credit)</option>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label small fw-bold text-muted mb-1">From Date</label>
            <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start_date) ?>">
        </div>

        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary w-100 fw-bold">
                <i class="fa-solid fa-filter me-1"></i> Filter
            </button>
            <a href="purchases.php" class="btn btn-sm btn-light border px-2" title="Reset Filters">
                <i class="fa-solid fa-arrow-rotate-left text-muted"></i>
            </a>
        </div>
    </form>
</div>

<!-- Purchases Master Table -->
<div class="catalog-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 140px;">Bill / Invoice #</th>
                    <th>Date</th>
                    <th>Supplier / Manufacturer</th>
                    <th class="text-center">Total Qty (Pcs)</th>
                    <th class="text-end" title="Total Discount Received on this Bill (Bonus + Disc)">Total Discount</th>
                    <th class="text-end">Grand Total</th>
                    <th class="text-end">Paid Amount</th>
                    <th class="text-end">Balance Due</th>
                    <th class="text-center">Status</th>
                    <th class="text-center" style="width: 135px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($purchases_list)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-cart-shopping fs-1 text-secondary opacity-50 mb-3 d-block"></i>
                            <h6 class="fw-bold text-dark">No purchase bills found</h6>
                            <p class="small text-muted mb-3">No purchase records found matching your filters or no bills added yet.</p>
                            <a href="add_purchase.php" class="btn btn-sm btn-primary fw-bold px-3 py-2">
                                <i class="fa-solid fa-plus me-1"></i> Create First Purchase
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($purchases_list as $pur): ?>
                        <tr>
                            <td>
                                <a href="view_purchase.php?id=<?= $pur['id'] ?>" class="text-decoration-none fw-bold font-monospace text-primary">
                                    <?= htmlspecialchars($pur['bill_no']) ?>
                                </a>
                                <div class="text-muted small" style="font-size: 0.72rem;">
                                    <i class="fa-solid fa-wallet me-1"></i><?= htmlspecialchars($pur['payment_type'] ?? 'Credit') ?>
                                </div>
                            </td>

                            <td>
                                <div class="fw-semibold text-dark"><?= date('d M Y', strtotime($pur['purchase_date'])) ?></div>
                                <?php if (!empty($pur['receiving_date']) && $pur['receiving_date'] !== $pur['purchase_date']): ?>
                                <div class="text-muted small" style="font-size: 0.72rem;">Recv: <?= date('d M Y', strtotime($pur['receiving_date'])) ?></div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($pur['supplier_name'] ?? 'General Supplier') ?></div>
                                <?php if (!empty($pur['supplier_company'])): ?>
                                    <span class="badge bg-light text-secondary border" style="font-size: 0.72rem;">
                                        <?= htmlspecialchars($pur['supplier_company']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="text-center fw-semibold text-dark">
                                <?= number_format($pur['total_packs_received'] ?? 0) ?>
                            </td>

                            <?php
                                $gross_row = floatval($pur['gross_amount'] ?? 0);
                                $subtotal_row = floatval($pur['subtotal'] ?? 0);
                                $row_disc = max(0, $gross_row - $subtotal_row);
                                $bill_disc = floatval($pur['discount_amount'] ?? 0);
                                $tot_disc = $row_disc + $bill_disc;
                                $tot_disc_pct = ($gross_row > 0) ? (($tot_disc / $gross_row) * 100) : 0;
                            ?>
                            <td class="text-end">
                                <?php if ($tot_disc > 0): ?>
                                    <span class="fw-bold text-success font-monospace">
                                        Rs. <?= number_format($tot_disc, 2) ?>
                                    </span>
                                    <div class="mt-1">
                                        <span class="badge bg-success-subtle text-success border border-success px-1" style="font-size: 0.68rem;" title="Bonus + Disc Received">
                                            <?= number_format($tot_disc_pct, 1) ?>% off
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small font-monospace">Rs. 0.00</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-end">
                                <span class="fw-bold text-dark fs-6 font-monospace">
                                    Rs. <?= number_format($pur['grand_total'], 2) ?>
                                </span>
                            </td>

                            <td class="text-end">
                                <span class="fw-bold text-success font-monospace">
                                    Rs. <?= number_format($pur['paid_amount'], 2) ?>
                                </span>
                            </td>

                            <td class="text-end">
                                <?php if ($pur['balance_amount'] > 0): ?>
                                    <span class="fw-bold text-danger font-monospace">
                                        Rs. <?= number_format($pur['balance_amount'], 2) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small font-monospace">Rs. 0.00</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-center">
                                <?php if ($pur['payment_status'] === 'Paid'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success px-2 py-1">
                                        <i class="fa-solid fa-check-circle me-1"></i> Paid
                                    </span>
                                <?php elseif ($pur['payment_status'] === 'Partial'): ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning px-2 py-1">
                                        <i class="fa-solid fa-history me-1"></i> Partial
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1">
                                        <i class="fa-solid fa-exclamation-circle me-1"></i> Unpaid
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="text-center">
                                <div class="d-flex align-items-center justify-content-center gap-1">
                                    <!-- 1. View Purchase -->
                                    <a href="view_purchase.php?id=<?= $pur['id'] ?>" class="action-btn btn-view" title="View Purchase Bill">
                                        <i class="fa-solid fa-eye text-primary"></i>
                                    </a>

                                    <!-- 2. Print Receiving Slip -->
                                    <a href="print_purchase.php?id=<?= $pur['id'] ?>" target="_blank" class="action-btn btn-print" title="Print Goods Receipt (GRN)">
                                        <i class="fa-solid fa-print text-success"></i>
                                    </a>

                                    <!-- 3. Edit Purchase -->
                                    <a href="edit_purchase.php?id=<?= $pur['id'] ?>" class="action-btn btn-edit" title="Edit Purchase Bill">
                                        <i class="fa-solid fa-edit text-warning"></i>
                                    </a>

                                    <!-- 4. Delete Purchase -->
                                    <a href="purchases.php?action=delete&id=<?= $pur['id'] ?>" 
                                       class="action-btn btn-delete" 
                                       title="Delete Purchase & Revert Stock"
                                       onclick="return confirm('Are you sure you want to delete Bill [<?= htmlspecialchars(addslashes($pur['bill_no'])) ?>]? This will revert stock and supplier balance.');">
                                        <i class="fa-solid fa-trash text-danger"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
