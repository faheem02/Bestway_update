<?php
/**
 * Bestway Wholesale Distribution - Print Goods Receiving Note (GRN) / Purchase Bill
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

$purchase_id = intval($_GET['id'] ?? 0);
$purchase = null;
$items = [];
$supplier = null;
$company  = null;

if ($db_connected && $pdo && $purchase_id > 0) {
    try {
        // Company Settings
        $company = $pdo->query("SELECT * FROM company_settings LIMIT 1")->fetch();

        $stmt_p = $pdo->prepare("SELECT * FROM purchases WHERE id = ?");
        $stmt_p->execute([$purchase_id]);
        $purchase = $stmt_p->fetch();

        if ($purchase) {
            $stmt_s = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
            $stmt_s->execute([$purchase['supplier_id']]);
            $supplier = $stmt_s->fetch();

            $stmt_i = $pdo->prepare("
                SELECT pi.*, pr.name as product_name, pr.product_code, pr.stock_unit, c.name as company_name
                FROM purchase_items pi
                LEFT JOIN products pr ON pi.product_id = pr.id
                LEFT JOIN companies c ON pr.company_id = c.id
                WHERE pi.purchase_id = ?
                ORDER BY pi.id ASC
            ");
            $stmt_i->execute([$purchase_id]);
            $items = $stmt_i->fetchAll();
        }
    } catch (Exception $e) {}
}

if (!$purchase) {
    die("Purchase bill record not found.");
}

// Company info
$biz_name = $company['business_name']  ?? 'Bestway Pharma Wholesale';
$biz_tag  = $company['tagline']        ?? 'Authorized Pharmaceutical Distributors & Wholesalers';
$biz_ph   = $company['phone']          ?? '';
$biz_addr = $company['address']        ?? '';
$logo_src = !empty($company['logo_path']) ? '../../' . $company['logo_path'] : null;

// Totals
$subtotal    = floatval($purchase['subtotal']        ?? 0);
$disc_amt    = floatval($purchase['discount_amount'] ?? 0);
$tax_amt     = floatval($purchase['tax_amount']     ?? 0);
$grand_total = floatval($purchase['grand_total']     ?? 0);
$paid        = floatval($purchase['paid_amount']     ?? 0);
$balance     = floatval($purchase['balance_amount']  ?? 0);

// Total row-level disc (bonus+disc) = subtotal difference from gross
$gross_sum = 0;
foreach ($items as $it) {
    $gross_sum += ($it['quantity'] * $it['purchase_price']);
}
$row_disc_total = max(0, $gross_sum - $subtotal);
$total_disc_all = $row_disc_total + $disc_amt;
$total_disc_pct = ($gross_sum > 0) ? (($total_disc_all / $gross_sum) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GRN - <?= htmlspecialchars($purchase['bill_no']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', Arial, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
            font-size: 12.5px;
        }

        /* ── Print Button Bar ── */
        .no-print {
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 14px 0;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        /* ── Main Container ── */
        .print-wrap {
            max-width: 900px;
            margin: 24px auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }

        /* ── Header Band ── */
        .grn-header {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: #fff;
            padding: 20px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .grn-header .logo-wrap img {
            height: 52px;
            width: auto;
            object-fit: contain;
            border-radius: 8px;
            background: #fff;
            padding: 4px;
        }
        .grn-header .logo-wrap .biz-name {
            font-size: 1.4rem;
            font-weight: 800;
            letter-spacing: 0.01em;
        }
        .grn-header .logo-wrap .biz-tag {
            font-size: 0.78rem;
            opacity: 0.85;
            margin-top: 2px;
        }
        .grn-badge {
            background: rgba(255,255,255,0.18);
            border: 1.5px solid rgba(255,255,255,0.4);
            border-radius: 10px;
            padding: 10px 18px;
            text-align: right;
        }
        .grn-badge .grn-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            opacity: 0.85;
        }
        .grn-badge .grn-no {
            font-size: 1.15rem;
            font-weight: 800;
            font-family: monospace;
            letter-spacing: 0.02em;
            margin-top: 2px;
        }

        /* ── Meta / Supplier Box ── */
        .meta-strip {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 14px 28px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .meta-strip .label-sm {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #64748b;
            margin-bottom: 3px;
        }
        .meta-strip .sup-name {
            font-size: 1rem;
            font-weight: 800;
            color: #0f172a;
        }
        .meta-strip .sup-detail {
            font-size: 0.78rem;
            color: #475569;
            margin-top: 2px;
        }
        .meta-strip .date-row {
            font-size: 0.78rem;
            color: #475569;
            margin-bottom: 3px;
        }
        .meta-strip .date-row strong {
            color: #0f172a;
        }

        /* ── Items Table ── */
        .items-section { padding: 20px 28px 0; }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        .items-table thead tr {
            background: #0284c7;
            color: #fff;
        }
        .items-table thead th {
            padding: 9px 10px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border: none;
        }
        .items-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
        }
        .items-table tbody tr:last-child {
            border-bottom: 2px solid #e2e8f0;
        }
        .items-table tbody td {
            padding: 9px 10px;
            font-size: 12px;
            vertical-align: middle;
        }
        .items-table tbody tr:nth-child(even) td {
            background: #f8fafc;
        }
        .prod-name { font-weight: 700; color: #0f172a; }
        .prod-code { font-size: 0.70rem; color: #64748b; margin-top: 1px; }

        /* Bonus / Disc badges */
        .bonus-badge {
            display: inline-block;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            border-radius: 5px;
            padding: 1px 6px;
            font-size: 0.68rem;
            font-weight: 700;
        }
        .disc-badge {
            display: inline-block;
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
            border-radius: 5px;
            padding: 1px 6px;
            font-size: 0.68rem;
            font-weight: 700;
            margin-top: 2px;
        }

        /* ── Summary Panel ── */
        .summary-section {
            padding: 16px 28px 22px;
            display: flex;
            justify-content: flex-end;
        }
        .summary-table {
            width: 320px;
            border-collapse: collapse;
        }
        .summary-table td {
            padding: 6px 10px;
            font-size: 12.5px;
            border: none;
        }
        .summary-table .s-label { color: #475569; text-align: right; }
        .summary-table .s-val   { text-align: right; font-family: monospace; font-weight: 700; }
        .summary-table .row-sep td { border-top: 1px dashed #cbd5e1; }
        .summary-table .row-grand td {
            font-size: 1rem;
            font-weight: 800;
            color: #0284c7;
            border-top: 2px solid #0284c7;
            padding-top: 10px;
        }
        .summary-table .row-disc .s-label { color: #16a34a; }
        .summary-table .row-disc .s-val   { color: #16a34a; }
        .summary-table .row-paid .s-label { color: #16a34a; }
        .summary-table .row-paid .s-val   { color: #16a34a; }
        .summary-table .row-bal .s-label  { color: #dc2626; }
        .summary-table .row-bal .s-val    { color: #dc2626; }

        /* Total discount highlight box */
        .total-disc-box {
            margin: 0 28px 16px;
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            border: 1.5px solid #86efac;
            border-radius: 10px;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .total-disc-box .tdb-label {
            font-size: 0.78rem;
            font-weight: 700;
            color: #15803d;
        }
        .total-disc-box .tdb-amt {
            font-size: 1rem;
            font-weight: 800;
            color: #15803d;
            font-family: monospace;
        }
        .total-disc-box .tdb-pct {
            background: #15803d;
            color: #fff;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 0.75rem;
            font-weight: 700;
            margin-left: 8px;
        }

        /* ── Signatures ── */
        .sig-section {
            border-top: 1px dashed #cbd5e1;
            margin: 0 28px;
            padding: 18px 0 22px;
            display: flex;
            justify-content: space-between;
        }
        .sig-box {
            text-align: center;
            flex: 1;
            padding: 0 12px;
        }
        .sig-box .sig-line {
            border-top: 1px solid #94a3b8;
            padding-top: 6px;
            font-size: 0.72rem;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        /* ── Footer Note ── */
        .footer-note {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            padding: 10px 28px;
            font-size: 0.72rem;
            color: #94a3b8;
        }

        /* ── Print Media ── */
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            body { background: #fff; }
            .no-print { display: none !important; }
            .print-wrap {
                box-shadow: none;
                border-radius: 0;
                margin: 0;
                max-width: 100%;
            }
            .grn-header {
                background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%) !important;
                color: #fff !important;
            }
            .items-table thead tr {
                background: #0284c7 !important;
                color: #fff !important;
            }
            .total-disc-box {
                background: linear-gradient(135deg, #f0fdf4, #dcfce7) !important;
                border: 1.5px solid #86efac !important;
            }
            .bonus-badge {
                background: #eff6ff !important;
                color: #1d4ed8 !important;
                border: 1px solid #bfdbfe !important;
            }
            .disc-badge {
                background: #f0fdf4 !important;
                color: #16a34a !important;
                border: 1px solid #bbf7d0 !important;
            }
        }

    </style>
</head>
<body>

<!-- Print Button Bar -->
<div class="no-print d-flex gap-2">
    <button onclick="window.print()" class="btn btn-primary fw-bold px-4 py-2">
        <i class="fa-solid fa-print me-2"></i> Print GRN
    </button>
    <button onclick="downloadPurchasePDF()" class="btn btn-danger fw-bold px-4 py-2">
        <i class="fa-solid fa-file-pdf me-2"></i> Download PDF
    </button>
    <a href="view_purchase.php?id=<?= $purchase['id'] ?>" class="btn btn-outline-secondary px-4 py-2">
        ← Back
    </a>
</div>

<div class="print-wrap">

    <!-- ══ HEADER ══ -->
    <div class="grn-header">
        <!-- LEFT: Logo + Company Name + Tagline -->
        <div class="logo-wrap d-flex align-items-center gap-3">
            <?php if ($logo_src): ?>
                <img src="<?= htmlspecialchars($logo_src) ?>" alt="Logo">
            <?php endif; ?>
            <div>
                <div class="biz-name"><?= htmlspecialchars($biz_name) ?></div>
                <div class="biz-tag"><?= htmlspecialchars($biz_tag) ?></div>
            </div>
        </div>

        <!-- RIGHT: Company Details + Address -->
        <div style="text-align:right;">
            <div style="font-size:0.70rem; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; opacity:0.75; margin-bottom:4px;">Syed Wasim Hussain Sherazi</div>
            <?php if (!empty($biz_ph)): ?>
                <div style="font-size:0.85rem; font-weight:700; opacity:0.95; margin-bottom:2px;">
                    📞 <?= htmlspecialchars($biz_ph) ?>
                </div>
            <?php endif; ?>
            <?php if ($biz_addr): ?>
                <div style="font-size:0.78rem; opacity:0.85;">
                    📍 <?= htmlspecialchars($biz_addr) ?>
                </div>
            <?php else: ?>
                <div style="font-size:0.78rem; opacity:0.85;">📍 Flate #01 Majid Haleema Sadia road Gate #01 Al Rehman Garden Ph 2 Sharaqpur Road Sheikhupura</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ══ META STRIP ══ -->
    <div class="meta-strip">
        <!-- LEFT: Supplier Name + Company + Bill Number -->
        <div>
            <div class="label-sm">Supplier / Manufacturer</div>
            <div class="sup-name"><?= htmlspecialchars($supplier['name'] ?? 'General Supplier') ?></div>
            <?php if (!empty($supplier['company_name'])): ?>
                <div class="sup-detail"><?= htmlspecialchars($supplier['company_name']) ?></div>
            <?php endif; ?>
            <!-- Bill Number with supplier -->
            <div style="margin-top:8px; padding:5px 10px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; display:inline-block;">
                <span style="font-size:0.68rem; font-weight:700; text-transform:uppercase; color:#1d4ed8; letter-spacing:0.06em;">GRN / Bill No:</span>
                <span style="font-size:0.92rem; font-weight:800; font-family:monospace; color:#1e3a8a; margin-left:4px;"><?= htmlspecialchars($purchase['bill_no']) ?></span>
            </div>
        </div>

        <!-- RIGHT: Supplier Phone + Address + Invoice Info -->
        <div class="text-end">
            <?php if (!empty($supplier['phone'])): ?>
                <div class="date-row">📞 <strong><?= htmlspecialchars($supplier['phone']) ?></strong></div>
            <?php endif; ?>
            <?php if (!empty($supplier['address'])): ?>
                <div class="date-row">📍 <?= htmlspecialchars($supplier['address']) ?></div>
            <?php endif; ?>
            <div class="date-row" style="margin-top:6px;">Invoice Date: <strong><?= date('d M Y', strtotime($purchase['purchase_date'])) ?></strong></div>
            <div class="date-row">Payment: <strong><?= htmlspecialchars($purchase['payment_type']) ?> (<?= htmlspecialchars($purchase['payment_status']) ?>)</strong></div>
        </div>
    </div>


    <!-- ══ ITEMS TABLE ══ -->
    <div class="items-section">
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:28px;">#</th>
                    <th>Item / Medicine Description</th>
                    <th style="width:65px;" class="text-center">Qty</th>
                    <th style="width:130px;" class="text-center">Bonus, Disc & Tax</th>
                    <th style="width:90px;" class="text-end">Cost/Rate</th>
                    <th style="width:90px;" class="text-end">TP Ref</th>
                    <th style="width:100px;" class="text-end">Total (Rs.)</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($items as $item):
                    $bonus_pct = floatval($item['bonus_quantity']  ?? 0);
                    $disc_pct  = floatval($item['discount_percent'] ?? 0);
                    $tax_p     = floatval($item['tax_percent']      ?? 0);
                    $disc_a    = floatval($item['discount_amount']  ?? 0);
                    $gross_row = $item['quantity'] * $item['purchase_price'];
                    $bonus_amt = $gross_row * ($bonus_pct / 100);
                ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td>
                        <div class="prod-name"><?= htmlspecialchars($item['product_name']) ?></div>
                        <?php if (!empty($item['company_name'])): ?>
                            <div class="prod-code"><?= htmlspecialchars($item['company_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-center fw-bold"><?= $item['quantity'] ?></td>
                    <td class="text-center">
                        <?php if ($bonus_pct > 0): ?>
                            <span class="bonus-badge">Bonus <?= number_format($bonus_pct, 0) ?>% (-<?= number_format($bonus_amt, 2) ?>)</span>
                        <?php endif; ?>
                        <?php if ($disc_pct > 0): ?>
                            <span class="disc-badge">Disc <?= number_format($disc_pct, 0) ?>%</span>
                        <?php endif; ?>
                        <?php if ($tax_p > 0): ?>
                            <span class="badge" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;border-radius:5px;padding:1px 6px;font-size:0.68rem;font-weight:700;">GST <?= number_format($tax_p, 0) ?>%</span>
                        <?php endif; ?>
                        <?php if ($bonus_pct == 0 && $disc_pct == 0 && $tax_p == 0): ?>
                            <span class="text-muted" style="font-size:0.75rem;">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end font-monospace"><?= number_format($item['purchase_price'], 2) ?></td>
                    <td class="text-end font-monospace text-muted"><?= number_format($item['trade_price'], 2) ?></td>
                    <td class="text-end font-monospace fw-bold"><?= number_format($item['total_price'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- ══ TOTAL DISCOUNT HIGHLIGHT BOX ══ -->
    <?php if ($total_disc_all > 0): ?>
    <div class="total-disc-box mt-3">
        <div>
            <div class="tdb-label">🏷️ Total Discount Received on this Bill</div>
            <div style="font-size:0.72rem;color:#166534;margin-top:2px;">
                Row-level (Bonus + Disc): Rs. <?= number_format($row_disc_total, 2) ?>
                <?php if ($disc_amt > 0): ?>
                    &nbsp;+&nbsp; Bill Discount: Rs. <?= number_format($disc_amt, 2) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-flex align-items-center">
            <span class="tdb-amt">Rs. <?= number_format($total_disc_all, 2) ?></span>
            <span class="tdb-pct"><?= number_format($total_disc_pct, 2) ?>% off</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══ SUMMARY ══ -->
    <div class="summary-section">
        <table class="summary-table">
            <tr>
                <td class="s-label">Gross Amount:</td>
                <td class="s-val">Rs. <?= number_format($gross_sum, 2) ?></td>
            </tr>
            <?php if ($row_disc_total > 0): ?>
            <tr class="row-disc">
                <td class="s-label">Items Discount (Bonus+Disc):</td>
                <td class="s-val">− Rs. <?= number_format($row_disc_total, 2) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td class="s-label">Net Subtotal:</td>
                <td class="s-val">Rs. <?= number_format($subtotal, 2) ?></td>
            </tr>
            <?php if ($disc_amt > 0): ?>
            <tr class="row-disc">
                <td class="s-label">Bill Discount:</td>
                <td class="s-val">− Rs. <?= number_format($disc_amt, 2) ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($tax_amt > 0): ?>
            <tr>
                <td class="s-label">GST / Sales Tax:</td>
                <td class="s-val" style="color:#0284c7;">+ Rs. <?= number_format($tax_amt, 2) ?></td>
            </tr>
            <?php endif; ?>
            <tr class="row-grand">
                <td class="s-label" style="color:#0284c7;">Grand Total:</td>
                <td class="s-val" style="color:#0284c7;">Rs. <?= number_format($grand_total, 2) ?></td>
            </tr>
            <tr class="row-paid">
                <td class="s-label">Paid:</td>
                <td class="s-val">Rs. <?= number_format($paid, 2) ?></td>
            </tr>
            <tr class="row-bal row-sep">
                <td class="s-label">Balance Due:</td>
                <td class="s-val">Rs. <?= number_format($balance, 2) ?></td>
            </tr>
        </table>
    </div>

    <!-- ══ SIGNATURES ══ -->
    <div class="sig-section">
        <div class="sig-box">
            <div style="height: 36px;"></div>
            <div class="sig-line">Store Keeper / Receiver</div>
        </div>
        <div class="sig-box">
            <div style="height: 36px;"></div>
            <div class="sig-line">Quality Checked By</div>
        </div>
        <div class="sig-box">
            <div style="height: 36px;"></div>
            <div class="sig-line">Accounts / Authorized</div>
        </div>
    </div>

    <!-- ══ FOOTER ══ -->
    <div class="footer-note">
        <?= htmlspecialchars($company['invoice_footer_note'] ?? 'Thank you for your business. All disputes subject to local jurisdiction.') ?>
        &nbsp;|&nbsp; Date: <?= date('d M Y') ?>
    </div>

</div><!-- end print-wrap -->

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<!-- html2pdf.js for client-side PDF export -->
<script src="<?= BASE_URL ?>assets/js/html2pdf.bundle.min.js"></script>
<script>
function downloadPurchasePDF() {
    const element = document.querySelector('.print-wrap');
    const opt = {
        margin: [5, 5, 5, 5],
        filename: 'Purchase_Bill_<?= htmlspecialchars($purchase['bill_no'] ?? 'grn') ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, logging: false },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(element).save();
}
</script>
</body>
</html>