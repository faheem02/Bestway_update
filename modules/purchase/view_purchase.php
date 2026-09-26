<?php
/**
 * Bestway Wholesale Distribution - View Purchase Bill & Goods Receiving Note
 */
$page_title = "Purchase Details";
$compact_page_heading = true;
require_once __DIR__ . '/../../includes/header.php';

$purchase_id = intval($_GET['id'] ?? 0);
$purchase = null;
$items = [];
$supplier = null;
$payments = [];

if ($db_connected && $pdo && $purchase_id > 0) {
    try {
        // Fetch purchase
        $stmt_p = $pdo->prepare("
            SELECT p.*, u.username as created_by_name 
            FROM purchases p
            LEFT JOIN users u ON p.created_by = u.id
            WHERE p.id = ?
        ");
        $stmt_p->execute([$purchase_id]);
        $purchase = $stmt_p->fetch();

        if ($purchase) {
            // Fetch supplier
            $stmt_s = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
            $stmt_s->execute([$purchase['supplier_id']]);
            $supplier = $stmt_s->fetch();

            // Fetch items
            $stmt_i = $pdo->prepare("
                SELECT pi.*, pr.name as product_name, pr.product_code, pr.stock_unit, pr.packs_per_box, c.name as company_name
                FROM purchase_items pi
                LEFT JOIN products pr ON pi.product_id = pr.id
                LEFT JOIN companies c ON pr.company_id = c.id
                WHERE pi.purchase_id = ?
                ORDER BY pi.id ASC
            ");
            $stmt_i->execute([$purchase_id]);
            $items = $stmt_i->fetchAll();

            // Calculate gross amount and total discounts received (Bonus + Disc + Bill Disc)
            $gross_sum = 0;
            foreach ($items as $it) {
                $gross_sum += ($it['quantity'] * $it['purchase_price']);
            }
            $subtotal_val = floatval($purchase['subtotal'] ?? 0);
            $row_disc_total = max(0, $gross_sum - $subtotal_val);
            $bill_disc_val = floatval($purchase['discount_amount'] ?? 0);
            $total_disc_received = $row_disc_total + $bill_disc_val;
            $disc_percent_calc = ($gross_sum > 0) ? (($total_disc_received / $gross_sum) * 100) : 0;

            // Fetch payments
            $stmt_pay = $pdo->prepare("
                SELECT sp.*, ba.bank_name, ba.account_number 
                FROM supplier_payments sp
                LEFT JOIN bank_accounts ba ON sp.bank_account_id = ba.id
                WHERE sp.purchase_id = ?
                ORDER BY sp.id ASC
            ");
            $stmt_pay->execute([$purchase_id]);
            $payments = $stmt_pay->fetchAll();
        }
    } catch (Exception $e) {}
}

if (!$purchase) {
    echo '<div class="alert alert-danger m-4">Purchase record not found. <a href="purchases.php">Go Back</a></div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

?>

<style>
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

    .bill-panel {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        overflow: hidden;
    }

    .bill-header-bar {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 20px 24px;
    }

    .meta-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px;
    }

    .table thead th {
        background: #f8fafc;
        color: #475569;
        font-size: 0.76rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        border-bottom: 2px solid #e2e8f0;
        padding: 12px;
        vertical-align: middle;
    }
    .table tbody td {
        padding: 12px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }

    .totals-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 18px;
    }
    .totals-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        font-size: 0.92rem;
    }
    .totals-row.grand {
        font-size: 1.25rem;
        font-weight: 800;
        color: #0284c7;
        padding-top: 10px;
        border-top: 2px dashed #cbd5e1;
        margin-top: 8px;
        margin-bottom: 10px;
    }
</style>

<!-- Top Toolbar -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <div class="d-flex align-items-center gap-2">
        <a href="purchases.php" class="btn btn-sm btn-outline-secondary bg-white rounded-3 shadow-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> All Purchases
        </a>
        <span class="badge bg-primary text-white font-monospace px-3 py-2" style="font-size:0.95rem;">
            Bill #<?= htmlspecialchars($purchase['bill_no']) ?>
        </span>
        <?php if ($purchase['payment_status'] === 'Paid'): ?>
            <span class="badge bg-success-subtle text-success border border-success px-2 py-1">Paid</span>
        <?php elseif ($purchase['payment_status'] === 'Partial'): ?>
            <span class="badge bg-warning-subtle text-warning border border-warning px-2 py-1">Partial</span>
        <?php else: ?>
            <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1">Unpaid</span>
        <?php endif; ?>
        <span class="text-muted small d-none d-md-inline ms-1">Recorded: <?= date('d M Y', strtotime($purchase['purchase_date'])) ?></span>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="print_purchase.php?id=<?= $purchase['id'] ?>" target="_blank" class="btn btn-sm btn-success fw-bold px-3 py-2 shadow-sm rounded-3">
            <i class="fa-solid fa-print me-1"></i> Print GRN Slip
        </a>
        <a href="edit_purchase.php?id=<?= $purchase['id'] ?>" class="btn btn-sm btn-warning fw-bold px-3 py-2 shadow-sm rounded-3 text-dark">
            <i class="fa-solid fa-edit me-1"></i> Edit Bill
        </a>
    </div>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'created'): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 d-flex align-items-center mb-4" role="alert">
        <i class="fa-solid fa-check-circle text-success fs-4 me-3"></i>
        <div class="fw-semibold">Purchase Bill kamiyabi se save ho gaya aur inventory stock update ho gaya hai.</div>
        <button type="button" class="close ml-auto" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
<?php endif; ?>

<div class="bill-panel mb-4">
    <!-- Header Summary Strip -->
    <div class="bill-header-bar">
        <div class="row g-3 align-items-center">
            <div class="col-md-6">
                <span class="text-muted small d-block">Purchase Date</span>
                <strong class="text-dark fs-6"><?= date('d F, Y', strtotime($purchase['purchase_date'])) ?></strong>
            </div>
            <div class="col-md-6 text-md-end">
                <span class="text-muted small d-block">Payment Mode</span>
                <strong class="text-primary fs-6"><?= htmlspecialchars($purchase['payment_type']) ?></strong>
            </div>
        </div>
    </div>

    <div class="p-4">
        <!-- Supplier & Delivery Meta Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="meta-box h-100">
                    <div class="fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                        <i class="fa-solid fa-truck text-primary"></i> Supplier / Distributor Profile
                    </div>
                    <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($supplier['name'] ?? 'General Supplier') ?></h5>
                    <?php if (!empty($supplier['company_name'])): ?>
                        <div class="text-muted small fw-semibold mb-2"><?= htmlspecialchars($supplier['company_name']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($supplier['phone'])): ?>
                        <div class="small text-muted"><i class="fa-solid fa-phone text-primary me-1"></i> <?= htmlspecialchars($supplier['phone']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($supplier['address'])): ?>
                        <div class="small text-muted"><i class="fa-solid fa-location-dot text-danger me-1"></i> <?= htmlspecialchars($supplier['address']) ?></div>
                    <?php endif; ?>
                    <div class="mt-2 pt-2 border-top small">
                        Current Supplier Balance: <strong class="text-danger font-monospace">Rs. <?= number_format($supplier['current_balance'] ?? 0, 2) ?></strong>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="meta-box h-100">
                    <div class="fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                        <i class="fa-solid fa-circle-info text-primary"></i> Receiving & Verification
                    </div>
                    <div class="small text-muted mb-1">Bill Reference: <strong><?= htmlspecialchars($purchase['bill_no']) ?></strong></div>
                    <div class="small text-muted mb-1">Created By: <strong><?= htmlspecialchars($purchase['created_by_name'] ?? 'Admin') ?></strong></div>
                    <?php if (!empty($purchase['notes'])): ?>
                        <div class="small text-muted mt-2 p-2 bg-white rounded border">
                            <strong>Remarks:</strong> <?= nl2br(htmlspecialchars($purchase['notes'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Inward Items Table -->
        <div class="table-responsive mb-4">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Medicine / Brand Name</th>
                        <th class="text-center">Inward Qty</th>
                        <th class="text-center">Bonus</th>
                        <th class="text-end">Cost (&lt; TP)</th>
                        <th class="text-end">Official TP</th>
                        <th class="text-center">Disc %</th>
                        <th class="text-center">GST %</th>
                        <th class="text-end">Total (Rs.)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $idx = 1; foreach ($items as $item): ?>
                        <tr>
                            <td><?= $idx++ ?></td>
                            <td>
                                <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($item['product_name']) ?></div>
                                <span class="badge bg-light text-muted border font-monospace"><?= htmlspecialchars($item['product_code']) ?></span>
                                <span class="text-muted small ms-1"><?= htmlspecialchars($item['company_name'] ?? '') ?></span>
                            </td>
                            <td class="text-center">
                                <span class="fw-bold text-dark"><?= number_format($item['quantity']) ?></span>
                                <span class="text-muted small">Pcs</span>
                            </td>
                            <td class="text-center fw-bold">
                                <?= intval($item['bonus_quantity'] ?? 0) ?>
                            </td>
                            <td class="text-end font-monospace text-danger fw-bold">
                                Rs. <?= number_format($item['purchase_price'], 2) ?>
                            </td>
                            <td class="text-end font-monospace text-primary">
                                Rs. <?= number_format($item['trade_price'], 2) ?>
                            </td>
                            <td class="text-center">
                                <?= ($item['discount_percent'] > 0) ? number_format($item['discount_percent'], 1) . '%' : '-' ?>
                            </td>
                            <td class="text-center font-monospace">
                                <?= (($item['tax_percent'] ?? 0) > 0) ? '<span class="text-primary fw-bold">' . number_format($item['tax_percent'], 1) . '%</span>' : '-' ?>
                            </td>
                            <td class="text-end font-monospace fw-bold text-dark">
                                Rs. <?= number_format($item['total_price'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ══ TOTAL DISCOUNT RECEIVED CALLOUT ══ -->
        <?php if ($total_disc_received > 0): ?>
        <div class="card border-0 mb-4 shadow-sm rounded-3" style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border: 1px solid #a7f3d0 !important;">
            <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white shadow-sm" style="width: 44px; height: 44px; background: #059669; font-size: 1.2rem;">
                        <i class="fa-solid fa-tags"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-success fs-6">
                            🏷️ Total Discount Received on this Bill
                        </div>
                        <div class="text-secondary small">
                            Items (Bonus + Disc): <strong class="text-success font-monospace">Rs. <?= number_format($row_disc_total, 2) ?></strong>
                            <?php if ($bill_disc_val > 0): ?>
                                &nbsp;+&nbsp; Bill Discount: <strong class="text-success font-monospace">Rs. <?= number_format($bill_disc_val, 2) ?></strong>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="text-end">
                    <div class="fw-bold font-monospace text-success fs-5">
                        Rs. <?= number_format($total_disc_received, 2) ?>
                    </div>
                    <span class="badge bg-success px-2 py-1 fw-bold">
                        <?= number_format($disc_percent_calc, 2) ?>% Off Total
                    </span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Financial Breakdown & Settlement Status -->
        <div class="row g-4 justify-content-end">
            <div class="col-lg-5">
                <div class="totals-card">
                    <div class="totals-row">
                        <span class="text-muted">Gross Amount:</span>
                        <span class="font-monospace fw-bold text-dark">Rs. <?= number_format($gross_sum, 2) ?></span>
                    </div>
                    <?php if ($row_disc_total > 0): ?>
                        <div class="totals-row">
                            <span class="text-muted">Items Discount (Bonus + Disc):</span>
                            <span class="font-monospace text-success fw-bold">− Rs. <?= number_format($row_disc_total, 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="totals-row">
                        <span class="text-muted">Net Subtotal:</span>
                        <span class="font-monospace fw-bold text-dark">Rs. <?= number_format($subtotal_val, 2) ?></span>
                    </div>
                    <?php if ($bill_disc_val > 0): ?>
                        <div class="totals-row">
                            <span class="text-muted">Bill Discount:</span>
                            <span class="font-monospace text-success fw-bold">− Rs. <?= number_format($bill_disc_val, 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($total_disc_received > 0): ?>
                        <div class="totals-row p-2 rounded-2 my-2" style="background:#f0fdf4; border:1px solid #bbf7d0;">
                            <div>
                                <span class="fw-bold text-success d-block" style="font-size:0.86rem;">
                                    <i class="fa-solid fa-tags me-1"></i> Total Discount Received on this Bill:
                                </span>
                                <span class="text-muted" style="font-size:0.73rem;">
                                    Bonus & Disc: Rs. <?= number_format($row_disc_total, 2) ?><?= ($bill_disc_val > 0) ? ' + Bill: Rs. ' . number_format($bill_disc_val, 2) : '' ?>
                                </span>
                            </div>
                            <div class="text-end">
                                <span class="font-monospace fw-bold text-success fs-6">− Rs. <?= number_format($total_disc_received, 2) ?></span>
                                <span class="badge bg-success text-white ms-1"><?= number_format($disc_percent_calc, 1) ?>% off</span>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (($purchase['tax_amount'] ?? 0) > 0): ?>
                        <div class="totals-row">
                            <span class="text-muted">GST / Sales Tax:</span>
                            <span class="font-monospace text-primary fw-bold">+ Rs. <?= number_format($purchase['tax_amount'], 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($purchase['freight_charges'] > 0): ?>
                        <div class="totals-row">
                            <span class="text-muted">Freight / Shipping:</span>
                            <span class="font-monospace text-dark">+ Rs. <?= number_format($purchase['freight_charges'], 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="totals-row grand">
                        <span>Grand Total:</span>
                        <span>Rs. <?= number_format($purchase['grand_total'], 2) ?></span>
                    </div>
                    <div class="totals-row">
                        <span class="text-muted">Paid Amount:</span>
                        <span class="font-monospace text-success fw-bold">Rs. <?= number_format($purchase['paid_amount'], 2) ?></span>
                    </div>
                    <div class="totals-row pt-2 border-top">
                        <span class="fw-bold text-danger">Balance Remaining:</span>
                        <span class="font-monospace fw-bold text-danger fs-6">Rs. <?= number_format($purchase['balance_amount'], 2) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
