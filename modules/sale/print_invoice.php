<?php
/**
 * Bestway Distribution - Print Invoice
 * Two types: Sale Invoice (no header) | Warranty Sale Invoice (with header + warranty section)
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

$id   = intval($_GET['id']   ?? 0);
$type = $_GET['type'] ?? ''; // 'sale' or 'warranty' — empty = show selection modal

if ($id <= 0) die('Invalid Invoice ID.');

// ── Fetch invoice ──────────────────────────────────────────────────────────────
$invoice = null; $items = [];
try {
    // Try sales_invoices first (full system)
    $stmt = $pdo->prepare("SELECT * FROM sales_invoices WHERE id = ? LIMIT 1");
    $stmt->execute([$id]); $invoice = $stmt->fetch();

    if ($invoice) {
        $si = $pdo->prepare("SELECT si.*, COALESCE(NULLIF(si.item_name, ''), p.name) AS item_display_name, p.name AS pname, p.generic_name, c.name AS company_name
                              FROM sale_items si
                              LEFT JOIN products p ON p.id = si.product_id
                              LEFT JOIN companies c ON c.id = p.company_id
                              WHERE si.invoice_id = ? ORDER BY si.id ASC");
        $si->execute([$id]); $items = $si->fetchAll();
    }
} catch (Exception $e) {}

// Fallback: try the `sales` table (used by new_sale.php / DSR)
if (!$invoice) {
    try {
        $stmt = $pdo->prepare(
            "SELECT s.*, c.full_name AS customer_name, c.phone AS customer_phone,
                    c.area AS route_name, e.full_name AS booker_name
             FROM sales s
             LEFT JOIN customers c ON c.id = s.customer_id
             LEFT JOIN employees e ON e.id = s.salesman_id
             WHERE s.id = ? LIMIT 1");
        $stmt->execute([$id]); $invoice = $stmt->fetch();

        if ($invoice) {
            // Normalise column names to match sales_invoices layout
            $invoice['invoice_date']    = $invoice['sale_date'] ?? date('Y-m-d');
            $invoice['grand_total']     = $invoice['grand_total'] ?? 0;
            $invoice['subtotal']        = $invoice['grand_total'];
            $invoice['discount_amount'] = 0;
            $invoice['previous_balance']= 0;
            $invoice['net_payable']     = $invoice['grand_total'];
            $invoice['balance_due']     = $invoice['due_amount'] ?? 0;
            $invoice['payment_method']  = $invoice['payment_type'] ?? 'Credit';

            $si = $pdo->prepare(
                "SELECT si.*, p.name AS item_name, p.name AS pname, p.generic_name,
                        c.name AS company_name, si.unit_price, si.subtotal AS total_amount
                 FROM sale_items si
                 LEFT JOIN products p ON p.id = si.product_id
                 LEFT JOIN companies c ON c.id = p.company_id
                 WHERE si.sale_id = ? ORDER BY si.id ASC");
            $si->execute([$id]); $items = $si->fetchAll();
        }
    } catch (Exception $e) {}
}

if (!$invoice) die('Invoice not found.');

// ── Auto-detect invoice type from the customer's saved preference ──────────────
// Har customer ko add/edit karte waqt "Invoice Type" (Sale / Warranty) allocate hoti hai.
// Agar URL me type nahi diya gaya to us customer ki saved preference ke hisab se sahi invoice khud-b-khud print hogi.
// &manual=1 dene se selection screen dobara dikhai ja sakti hai (override ke liye).
$cust_license = '';
$cust_id = intval($invoice['customer_id'] ?? 0);
$detected_type = null;

if ($cust_id > 0) {
    try {
        $ct = $pdo->prepare("SELECT invoice_type, license_number FROM customers WHERE id = ? LIMIT 1");
        $ct->execute([$cust_id]);
        $crow = $ct->fetch();
        if ($crow) {
            if (!empty($crow['invoice_type'])) {
                $detected_type = strtolower(trim($crow['invoice_type']));
            }
            if (!empty($crow['license_number'])) {
                $cust_license = trim($crow['license_number']);
            }
        }
    } catch (Exception $e) {}
}

// Fallback: match by customer_name if customer_id was not linked or license was empty
if ((!$detected_type || empty($cust_license)) && !empty($invoice['customer_name'])) {
    try {
        $cname = trim($invoice['customer_name']);
        $ct2 = $pdo->prepare("SELECT invoice_type, license_number FROM customers WHERE name = ? OR shop_name = ? LIMIT 1");
        $ct2->execute([$cname, $cname]);
        $crow2 = $ct2->fetch();
        if ($crow2) {
            if (!$detected_type && !empty($crow2['invoice_type'])) {
                $detected_type = strtolower(trim($crow2['invoice_type']));
            }
            if (empty($cust_license) && !empty($crow2['license_number'])) {
                $cust_license = trim($crow2['license_number']);
            }
        }
    } catch (Exception $e) {}
}

$manual = isset($_GET['manual']);
if ($type === '' && !$manual) {
    if ($detected_type && in_array($detected_type, ['sale', 'warranty'], true)) {
        $type = $detected_type;
    } else {
        // Default to 'sale' (regular invoice) without prompting
        $type = 'sale';
    }
}

// ── Company info ───────────────────────────────────────────────────────────────
$company = [
    'name'             => 'Bestway Distribution',
    'address'          => 'Flate #01 Majid Haleema Sadia road Gate #01 Al Rehman Garden Ph 2 Sharaqpur Road Sheikhupura',
    'phone'            => '0329-9339000',
    'owner'            => 'Syed Wasim Hussain Sherazi',
    'drug_license_no'  => '05-354-0078-140668D',
    'drug_license_upto'=> '17-09-2031',
];

// Company drug / trade license — DB se override (same fallback pattern as includes/report_header.php)
try {
    $cs_row = $pdo ? $pdo->query("SELECT drug_license_no, drug_license_valid_upto FROM company_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC) : null;
    if ($cs_row) {
        if (!empty($cs_row['drug_license_no'])) $company['drug_license_no'] = $cs_row['drug_license_no'];
        if (!empty($cs_row['drug_license_valid_upto'])) $company['drug_license_upto'] = date('d-m-Y', strtotime($cs_row['drug_license_valid_upto']));
    }
} catch (Exception $e) {}

$inv_no      = htmlspecialchars($invoice['invoice_no'] ?? '#'.$id);
$inv_date    = date('d-M-Y', strtotime($invoice['invoice_date'] ?? date('Y-m-d')));
$cust_name   = htmlspecialchars($invoice['customer_name'] ?? 'Walk-in Customer');
$cust_phone  = htmlspecialchars($invoice['customer_phone'] ?? '');
$route_name  = htmlspecialchars($invoice['route_name'] ?? '');
$booker_name = htmlspecialchars($invoice['booker_name'] ?? '');
$notes       = htmlspecialchars($invoice['notes'] ?? '');
$grand_total = (float)($invoice['grand_total'] ?? 0);
$subtotal    = (float)($invoice['subtotal']     ?? $grand_total);
$discount    = (float)($invoice['discount_amount'] ?? 0);
$paid        = (float)($invoice['paid_amount']  ?? 0);
$balance_due = (float)($invoice['balance_due']  ?? ($grand_total - $paid));
$prev_bal    = (float)($invoice['previous_balance'] ?? 0);
$net_payable = (float)($invoice['net_payable']  ?? ($grand_total + $prev_bal));
$inv_balance = max(0, $grand_total - $paid);
$pay_method  = htmlspecialchars($invoice['payment_method'] ?? 'Credit');

// Totals
$total_qty = array_sum(array_column($items, 'quantity'));

// Signature image (inline data URI for instant, reliable print rendering)
$sign_file = __DIR__ . '/../../assets/images/sign.png';
$sign_src = file_exists($sign_file) ? 'data:image/png;base64,' . base64_encode(file_get_contents($sign_file)) : (BASE_URL . 'assets/images/sign.png');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice <?= $inv_no ?> — Bestway Distribution</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
/* ── Base ───────────────────────────────────────────────────── */
body { font-family:'Poppins',sans-serif; background:#f1f5f9; font-size:13px; color:#1e293b; }
.invoice-wrap { max-width:860px; margin:20px auto; background:#fff; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,.08); padding:32px 40px; }
.no-print { display:block; }

/* ── Header ─────────────────────────────────────────────────── */
.inv-header { border-bottom:2px solid #10b981; padding-bottom:12px; margin-bottom:16px; }
.company-name { font-size:20px; font-weight:800; color:#0f172a; text-transform:uppercase; letter-spacing:.5px; }
.company-meta { font-size:11px; color:#475569; line-height:1.6; }
.inv-title-box { text-align:right; }
.inv-type-badge { display:inline-block; padding:3px 14px; border-radius:4px; font-size:11px; font-weight:700; letter-spacing:1px; text-transform:uppercase; margin-bottom:4px; }
.badge-sale { background:#0f172a; color:#fff; }
.badge-warranty { background:#10b981; color:#fff; }
.inv-no { font-size:18px; font-weight:800; color:#0f172a; font-family:monospace; }
.inv-meta-small { font-size:11px; color:#64748b; line-height:1.7; }

/* ── Info Boxes ──────────────────────────────────────────────── */
.info-box { background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 14px; font-size:12px; }
.info-box .label { font-size:10px; font-weight:700; text-transform:uppercase; color:#94a3b8; letter-spacing:.5px; }

/* ── Table ───────────────────────────────────────────────────── */
.inv-table { width:100%; border-collapse:collapse; margin:14px 0; }
.inv-table th { background:#1e293b; color:#fff; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; padding:7px 8px; border:1px solid #0f172a; }
.inv-table td { padding:6px 8px; border:1px solid #e2e8f0; font-size:12px; vertical-align:middle; }
.inv-table tbody tr:nth-child(even) td { background:#f8fafc; }
.inv-table tfoot td { background:#ecfdf5 !important; border:1px solid #a7f3d0 !important; font-weight:700; font-size:12px; }

/* ── Totals box ──────────────────────────────────────────────── */
.totals-box { border:1px solid #e2e8f0; border-radius:6px; padding:12px 16px; background:#f8fafc; }
.totals-row { display:flex; justify-content:space-between; align-items:center; font-size:12px; padding:3px 0; border-bottom:1px solid #f1f5f9; }
.totals-row:last-child { border-bottom:none; }
.totals-grand { font-size:15px; font-weight:800; color:#10b981; padding:6px 0 2px; }
.totals-due { font-size:14px; font-weight:800; color:#ef4444; }

/* ── Signature area ──────────────────────────────────────────── */
.sig-row { margin-top:28px; }
.sig-box { text-align:center; }
.sig-line { border-top:1px dashed #94a3b8; padding-top:5px; font-size:11px; font-weight:600; color:#475569; margin-top:40px; }
.sig-img { height:70px; object-fit:contain; }

/* ── Warranty section ────────────────────────────────────────── */
.warranty-section { margin-top:22px; border-top:2px solid #10b981; padding-top:14px; font-size:11px; color:#1e293b; line-height:1.65; }
.warranty-section h4 { font-size:13px; font-weight:700; color:#0f172a; margin-bottom:6px; }
.warranty-section .note { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:5px; padding:10px 12px; margin-top:12px; font-size:10.5px; }
.warranty-sig-row { display:flex; justify-content:flex-end; margin-top:14px; }
.warranty-sig-box { text-align:center; width:200px; }
.warranty-sig-img { max-height:85px; max-width:140px; object-fit:contain; display:inline-block; margin-bottom:2px; }
.warranty-sig-line { border-top:1px solid #0f172a; padding-top:4px; font-size:10px; font-weight:700; color:#0f172a; }

/* ── Footer note ─────────────────────────────────────────────── */
.inv-footer-note { text-align:center; font-size:10px; color:#94a3b8; margin-top:14px; border-top:1px solid #e2e8f0; padding-top:8px; }

/* ── Action bar ──────────────────────────────────────────────── */
.action-bar { max-width:860px; margin:0 auto 16px; display:flex; justify-content:space-between; align-items:center; }

/* ── PRINT ───────────────────────────────────────────────────── */
@media print {
  body { background:#fff !important; }
  .no-print { display:none !important; }
  .invoice-wrap { box-shadow:none; margin:0; padding:10px 14px; max-width:100%; border-radius:0; }
  .inv-table th { background:#1e293b !important; color:#fff !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  .inv-table tfoot td { background:#ecfdf5 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  @page { size:A4 portrait; margin:8mm 8mm; }
}
</style>
</head>
<body>

<?php if ($type === ''): ?>
<!-- ============================================================
     PRINT TYPE SELECTION MODAL (shown when no type param)
     ============================================================ -->
<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f1f5f9;">
  <div style="background:#fff;border-radius:14px;box-shadow:0 8px 32px rgba(0,0,0,.12);padding:40px 48px;max-width:460px;width:100%;text-align:center;">
    <div style="font-size:2.5rem;margin-bottom:12px;">🖨️</div>
    <h4 style="font-weight:800;color:#0f172a;margin-bottom:4px;">Select Invoice Type</h4>
    <p style="color:#64748b;font-size:13px;margin-bottom:28px;">
      Invoice <strong><?= $inv_no ?></strong> — <?= $cust_name ?>
    </p>

    <div style="display:flex;gap:16px;justify-content:center;">

      <!-- Sale Invoice -->
      <a href="print_invoice.php?id=<?= $id ?>&type=sale"
         style="display:flex;flex-direction:column;align-items:center;background:#f8fafc;border:2px solid #e2e8f0;border-radius:12px;padding:22px 28px;text-decoration:none;color:#0f172a;transition:border-color .2s;min-width:170px;"
         onmouseover="this.style.borderColor='#10b981'" onmouseout="this.style.borderColor='#e2e8f0'">
        <i class="fas fa-file-invoice" style="font-size:2rem;color:#0f172a;margin-bottom:10px;"></i>
        <span style="font-weight:700;font-size:14px;">Sale Invoice</span>
        <span style="font-size:11px;color:#94a3b8;margin-top:4px;">No company header</span>
      </a>

      <!-- Warranty Sale Invoice -->
      <a href="print_invoice.php?id=<?= $id ?>&type=warranty"
         style="display:flex;flex-direction:column;align-items:center;background:#f0fdf4;border:2px solid #10b981;border-radius:12px;padding:22px 28px;text-decoration:none;color:#0f172a;min-width:170px;">
        <i class="fas fa-shield-alt" style="font-size:2rem;color:#10b981;margin-bottom:10px;"></i>
        <span style="font-weight:700;font-size:14px;">Warranty Invoice</span>
        <span style="font-size:11px;color:#64748b;margin-top:4px;">With header + warranty</span>
      </a>

    </div>

    <a href="sales.php" style="display:inline-block;margin-top:22px;font-size:12px;color:#94a3b8;text-decoration:none;">
      <i class="fas fa-arrow-left" style="margin-right:4px;"></i> Back to Sales
    </a>
  </div>
</div>

<?php else: ?>
<!-- ============================================================
     ACTUAL INVOICE PRINT PAGE
     ============================================================ -->

<!-- Action Bar -->
<div class="action-bar no-print">
  <div>
    <a href="new_sale.php" class="btn btn-success btn-sm mr-2 font-weight-bold">
      <i class="fas fa-plus mr-1"></i> Create Another Sale
    </a>
    <a href="sales.php" class="btn btn-outline-secondary btn-sm mr-2">
      <i class="fas fa-list mr-1"></i> All Sales
    </a>
    <a href="print_invoice.php?id=<?= $id ?>&manual=1" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-exchange-alt mr-1"></i> Change Type
    </a>
  </div>
  <div>
    <button onclick="window.print()" class="btn btn-primary btn-sm px-4 font-weight-bold">
      <i class="fas fa-print mr-1"></i>
      Print <?= $type === 'warranty' ? 'Warranty Invoice' : 'Sale Invoice' ?>
    </button>
  </div>
</div>

<div class="invoice-wrap">

  <!-- ── HEADER ────────────────────────────────────────────────── -->
  <?php if ($type === 'warranty'): ?>
  <!-- WARRANTY: Full company header -->
  <div class="inv-header d-flex justify-content-between align-items-start">
    <div>
      <div class="company-name"><?= htmlspecialchars($company['name']) ?></div>
      <div class="company-meta">
        <i class="fas fa-map-marker-alt mr-1"></i><?= htmlspecialchars($company['address']) ?><br>
        <i class="fas fa-phone mr-1"></i><?= htmlspecialchars($company['phone']) ?><br>
        <?php if (!empty($company['drug_license_no'])): ?>
        <i class="fas fa-id-card mr-1"></i><span class="text-nowrap">Company Drug Lic #:</span> <strong><?= htmlspecialchars($company['drug_license_no']) ?></strong>
        &nbsp;|&nbsp;Valid up to: <strong><?= htmlspecialchars($company['drug_license_upto']) ?></strong>
        <?php endif; ?>
      </div>
    </div>
    <div class="inv-title-box">
      <div><span class="inv-type-badge badge-warranty">Warranty Sale Invoice</span></div>
      <div class="inv-no"><?= $inv_no ?></div>
      <div class="inv-meta-small">
        Date: <strong><?= $inv_date ?></strong><br>
        Payment: <strong><?= $pay_method ?></strong>
        <?php if ($booker_name): ?><br>Salesman: <strong><?= $booker_name ?></strong><?php endif; ?>
      </div>
    </div>
  </div>
  <?php else: ?>
  <!-- SALE: No company header — just invoice title -->
  <div class="inv-header d-flex justify-content-between align-items-center">
    <div>
      <span class="inv-type-badge badge-sale">Sale Invoice</span>
      <div class="inv-no mt-1"><?= $inv_no ?></div>
    </div>
    <div class="inv-title-box">
      <div class="inv-meta-small">
        Date: <strong><?= $inv_date ?></strong><br>
        Payment: <strong><?= $pay_method ?></strong>
        <?php if ($booker_name): ?><br>Salesman: <strong><?= $booker_name ?></strong><?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── CUSTOMER INFO ─────────────────────────────────────────── -->
  <div class="row mb-3">
    <div class="col-7">
      <div class="info-box h-100">
        <div class="label">Billed To</div>
        <div class="font-weight-bold mt-1" style="font-size:14px;"><?= $cust_name ?></div>
        <?php if ($cust_phone): ?><div class="text-muted small"><i class="fas fa-phone mr-1"></i><?= $cust_phone ?></div><?php endif; ?>
        <?php if ($route_name): ?><div class="text-muted small"><i class="fas fa-map-marker-alt mr-1"></i>Area: <?= $route_name ?></div><?php endif; ?>
        <?php if (!empty($cust_license)): ?>
          <div class="small font-weight-bold mt-1 text-dark"><i class="fas fa-id-card text-success mr-1"></i>Customer Drug Lic #: <span class="text-primary font-weight-bold"><?= htmlspecialchars($cust_license) ?></span></div>
        <?php elseif ($type === 'warranty'): ?>
          <div class="small font-weight-bold mt-1 text-muted"><i class="fas fa-id-card mr-1"></i>Customer Drug Lic #: <span class="fst-italic">not on record</span></div>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-5">
      <div class="info-box h-100 text-right">
        <div class="label">Invoice Summary</div>
        <div class="mt-1 small">
          Items: <strong><?= count($items) ?></strong><br>
          Total Qty: <strong><?= number_format($total_qty) ?></strong><br>
          <?php if ($notes): ?><span class="text-muted">Note: <?= $notes ?></span><?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ── ITEMS TABLE ───────────────────────────────────────────── -->
  <table class="inv-table">
    <thead>
      <tr>
        <th style="width:30px;" class="text-center">#</th>
        <th>Medicine / Product</th>
        <th class="text-center" style="width:65px;">Batch</th>
        <th class="text-center" style="width:55px;">Qty (Pcs)</th>
        <th class="text-right"  style="width:85px;">Rate</th>
        <th class="text-center" style="width:50px;">Disc%</th>
        <th class="text-right"  style="width:95px;">Amount</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $row_gross = 0;
      foreach ($items as $idx => $it):
        $raw_name = !empty($it['item_display_name']) ? $it['item_display_name'] : (!empty($it['item_name']) ? $it['item_name'] : (!empty($it['pname']) ? $it['pname'] : 'Item'));
        $cleaned_name = preg_replace('/\s*(\[|\()?prd\s*-\s*\d+(\]|\))?/i', '', $raw_name);
        $name    = htmlspecialchars(trim($cleaned_name) ?: $raw_name);
        $company_nm = htmlspecialchars($it['company_name'] ?? '');
        $generic = htmlspecialchars($it['generic_name'] ?? '');
        $batch   = htmlspecialchars(!empty($it['batch_no']) ? $it['batch_no'] : '-');
        $qty     = (float)($it['quantity'] ?? 0);
        $rate    = (float)(!empty($it['unit_price']) ? $it['unit_price'] : (!empty($it['sale_price']) ? $it['sale_price'] : (!empty($it['trade_price']) ? $it['trade_price'] : 0)));
        $disc    = (float)($it['discount_percent'] ?? 0);
        $amount  = (float)(!empty($it['total_amount']) ? $it['total_amount'] : (!empty($it['total_price']) ? $it['total_price'] : ($qty * $rate)));
        $row_gross += $amount;
      ?>
      <tr>
        <td class="text-center text-muted"><?= $idx + 1 ?></td>
        <td>
          <strong><?= $name ?></strong>
          <?php if ($generic): ?><span class="text-muted"> — <?= $generic ?></span><?php endif; ?>
          <?php if ($company_nm): ?><br><small class="text-muted"><?= $company_nm ?></small><?php endif; ?>
        </td>
        <td class="text-center"><small><?= $batch ?></small></td>
        <td class="text-center font-weight-bold"><?= number_format($qty) ?></td>
        <td class="text-right font-monospace"><?= number_format($rate, 2) ?></td>
        <td class="text-center text-muted"><?= $disc > 0 ? $disc.'%' : '-' ?></td>
        <td class="text-right font-weight-bold font-monospace"><?= number_format($amount, 2) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($items)): ?>
        <tr><td colspan="7" class="text-center text-muted py-3">No items found.</td></tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="3" class="text-right">Total:</td>
        <td class="text-center font-weight-bold"><?= number_format($total_qty) ?></td>
        <td colspan="2" class="text-right">Grand Total:</td>
        <td class="text-right font-monospace">Rs. <?= number_format($grand_total, 2) ?></td>
      </tr>
    </tfoot>
  </table>

  <!-- ── TOTALS ───────────────────────────────────── -->
  <div class="row">
    <!-- Right: Totals -->
    <div class="col-12">
      <div class="totals-box">
        <div class="totals-row">
          <span class="text-muted">Subtotal:</span>
          <span class="font-weight-bold font-monospace">Rs. <?= number_format($subtotal, 2) ?></span>
        </div>
        <?php if ($discount > 0): ?>
        <div class="totals-row">
          <span class="text-muted">Discount:</span>
          <span class="text-danger font-monospace">— Rs. <?= number_format($discount, 2) ?></span>
        </div>
        <?php endif; ?>

        <div class="totals-row totals-grand">
          <span>Bill Total:</span>
          <span class="font-monospace">Rs. <?= number_format($grand_total, 2) ?></span>
        </div>
        <div class="totals-row">
          <span class="text-success font-weight-bold">Paid:</span>
          <span class="text-success font-monospace">Rs. <?= number_format($paid, 2) ?></span>
        </div>
        <div class="totals-row totals-due">
          <span>Invoice Balance:</span>
          <span class="font-monospace">Rs. <?= number_format($inv_balance, 2) ?></span>
        </div>
      </div>
    </div>
  </div>

  <?php if ($type === 'warranty'): ?>
  <!-- ── WARRANTY SECTION ──────────────────────────────────────── -->
  <div class="warranty-section">
    <h4>FORM 2A (See Rules 19 and 30)</h4>

    <p><strong>Warranty under Section 23(1)(i) of the Drugs Act, 1976</strong><br>
    I/We (<?= htmlspecialchars($company['owner']) ?>), being a person resident in Pakistan, carrying on business at the aforesaid
    (<?= htmlspecialchars($company['address']) ?>) under the name of <?= htmlspecialchars($company['name']) ?>,
    being authorized distributors of the manufacturers/principals, do hereby give this warranty that the drugs herein above
    described as sold by me and contained in this invoice do not contravene in any way the provisions of Section 23 of the Drugs Act, 1976.</p>

    <!-- Signature bottom-right -->
    <div class="warranty-sig-row">
      <div class="warranty-sig-box">
        <img src="<?= $sign_src ?>" alt="Authorized Signature" class="warranty-sig-img"><br>
        <div class="warranty-sig-line">
          <?= htmlspecialchars($company['owner']) ?><br>
          Authorized Distributor<br>
          <?= htmlspecialchars($company['name']) ?>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── FOOTER NOTE ───────────────────────────────────────────── -->
  <div class="inv-footer-note">
    <?php if ($type === 'warranty'): ?>
      <?= htmlspecialchars($company['name']) ?> &mdash; <?= htmlspecialchars($company['address']) ?> &mdash; <?= htmlspecialchars($company['phone']) ?>
    <?php else: ?>
      Thank you for your business! Goods once sold will only be accepted for return as per company return policy.
    <?php endif; ?>
  </div>

</div><!-- /.invoice-wrap -->

<script>
  // Auto-print when page loads (type is already selected)
  window.addEventListener('load', function() {
    // Small delay to ensure images load
    setTimeout(function() { window.print(); }, 400);
  });
</script>

<?php endif; // end type !== '' ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
