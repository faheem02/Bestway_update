<?php
/**
 * Bestway Wholesale Distribution - Salesman Invoices & Profit
 * Per-salesman sales invoices with estimated purchase-cost based profit margin.
 * Admin only (same as source app: mehboob_traders).
 */
$page_title = 'Salesman Invoices & Profit';
$compact_page_heading = true;
$hide_topbar_title = true;
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

if (!isAdmin()) {
    redirect(BASE_URL . 'modules/sale/sales.php', 'Salesman Invoices report is available to Admin only.', 'warning');
}

$is_admin = isAdmin();

// Determine target salesman
$ob = isset($_GET['order_booker_id']) ? trim((string)$_GET['order_booker_id']) : '';

// Fetch all salesmen (from employees table where employee_type = 'salesman', same source used by getActiveBookers)
$b_agg_stmt = $pdo->query("
    SELECT e.id, e.full_name AS name, e.emp_code AS booker_code, e.phone, e.area, e.address,
           COUNT(si.id) AS invoice_count,
           COALESCE(SUM(si.grand_total), 0) AS total_sales
    FROM employees e
    LEFT JOIN sales_invoices si ON si.booker_id = e.id
    WHERE e.employee_type = 'salesman' AND e.status = 1
    GROUP BY e.id, e.full_name, e.emp_code, e.phone, e.area, e.address
    ORDER BY e.full_name ASC
");
$all_bookers = $b_agg_stmt->fetchAll();

// Cost total per booker (estimated purchase cost from items)
$booker_costs = [];
try {
    $c_stmt = $pdo->query("
        SELECT si.booker_id, COALESCE(SUM(p.purchase_price * s2.quantity), 0) AS total_cost
        FROM sale_items s2
        JOIN products p ON p.id = s2.product_id
        JOIN sales_invoices si ON si.id = s2.invoice_id
        WHERE si.booker_id IS NOT NULL
        GROUP BY si.booker_id
    ");
    foreach ($c_stmt->fetchAll() as $rc) { $booker_costs[(int)$rc['booker_id']] = (float)$rc['total_cost']; }
} catch (Exception $e) {}

foreach ($all_bookers as &$bk) {
    $bk['total_cost'] = $booker_costs[(int)$bk['id']] ?? 0.0;
}
unset($bk);

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$rid  = $_GET['route_id'] ?? '';
$area = trim($_GET['area'] ?? '');

$routes = [];
try { $routes = $pdo->query("SELECT id, name FROM routes WHERE status = 'Active' ORDER BY name ASC")->fetchAll(); } catch (Exception $e) {}
$areas = [];
try {
    $a_stmt = $pdo->query("SELECT DISTINCT c.area FROM sales_invoices si LEFT JOIN customers c ON c.id = si.customer_id WHERE c.area IS NOT NULL AND c.area <> '' ORDER BY c.area ASC");
    foreach ($a_stmt->fetchAll() as $a) { $areas[] = $a['area']; }
} catch (Exception $e) {}
if ($area !== '' && !in_array($area, $areas, true)) { $area = ''; }

$selected_booker = null;
if ($ob !== '' && $ob !== 'all') {
    try {
        $sb_stmt = $pdo->prepare("SELECT id, full_name AS name, emp_code AS booker_code, phone, area, address FROM employees WHERE id = ?");
        $sb_stmt->execute([(int)$ob]);
        $selected_booker = $sb_stmt->fetch();
    } catch (Exception $e) {}
}

$sales = [];
$total_sales = 0; $total_cost = 0; $total_profit = 0;
$total_paid = 0;  $total_due = 0;

if ($ob !== '') {
    $sql = "SELECT si.*,
                   c.name AS customer_name, c.shop_name AS customer_shop, c.phone AS customer_phone,
                   c.area AS customer_area, c.invoice_type AS customer_invoice_type,
                   b.id AS ob_id, b.name AS order_taker_name,
                   (SELECT COALESCE(SUM(p.purchase_price * s2.quantity), 0)
                    FROM sale_items s2 JOIN products p ON p.id = s2.product_id
                    WHERE s2.invoice_id = si.id) AS total_cost
            FROM sales_invoices si
            LEFT JOIN customers c ON c.id = si.customer_id
            LEFT JOIN employees b ON b.id = si.booker_id
            WHERE 1=1";
    $params = [];

    if ($ob !== 'all') {
        $sql .= " AND si.booker_id = ?";
        $params[] = (int)$ob;
    }

    if ($from) { $sql .= " AND si.invoice_date >= ?"; $params[] = $from; }
    if ($to)   { $sql .= " AND si.invoice_date <= ?"; $params[] = $to; }
    if ($rid !== '')  { $sql .= " AND si.route_id = ?"; $params[] = (int)$rid; }
    if ($area !== '') { $sql .= " AND LOWER(c.area) = LOWER(?)"; $params[] = $area; }

    $sql .= " ORDER BY si.invoice_date DESC, si.id DESC";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $sales = $stmt->fetchAll();
    } catch (Exception $e) { $sales = []; }

    foreach ($sales as &$s) {
        $sale_amt = (float)$s['grand_total'];
        $cost_amt = (float)$s['total_cost'];
        $profit   = $sale_amt - $cost_amt;
        $margin   = $sale_amt > 0 ? ($profit / $sale_amt) * 100 : 0;
        $s['calc_profit'] = $profit;
        $s['calc_margin'] = $margin;

        $total_sales  += $sale_amt;
        $total_cost   += $cost_amt;
        $total_profit += $profit;
        $total_paid   += (float)$s['paid_amount'];
        $total_due    += (float)$s['balance_due'];
    }
    unset($s);
}

$overall_margin = $total_sales > 0 ? ($total_profit / $total_sales) * 100 : 0;

$route_name = '';
if ($rid !== '') {
    try {
        $rn = $pdo->prepare("SELECT name FROM routes WHERE id = ?");
        $rn->execute([(int)$rid]);
        $route_name = (string)$rn->fetchColumn();
    } catch (Exception $e) {}
}

$printed_by = '';
if (!empty($_SESSION['user_id'])) {
    try {
        $pu = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $pu->execute([(int)$_SESSION['user_id']]);
        $printed_by = (string)$pu->fetchColumn();
    } catch (Exception $e) {}
}
?>

<main id="main">
  <div class="container-fluid">
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-3">
      <div class="me-3 mb-2 mb-sm-0">
        <h4 class="fw-bold mb-0 text-dark"><i class="fas fa-user-tag mr-1"></i> Salesman Invoices &amp; Profit</h4>
        <p class="text-muted small mb-0">Per-salesman sales invoices with estimated profit margin</p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <?php if ($is_admin && $ob !== ''): ?>
          <a href="order_booker_invoices.php" class="btn btn-sm btn-outline-secondary fw-semibold">
            <i class="fas fa-exchange-alt mr-1"></i> Change Salesman
          </a>
        <?php endif; ?>
        <a href="sales.php" class="btn btn-sm btn-outline-primary fw-semibold">
          <i class="fas fa-file-invoice mr-1"></i> All Invoices
        </a>
        <a href="new_sale.php" class="btn btn-sm btn-success fw-semibold">
          <i class="fas fa-plus mr-1"></i> New Sale
        </a>
        <?php if ($ob !== ''): ?>
          <button type="button" class="btn btn-sm btn-primary fw-semibold" onclick="window.print()">
            <i class="fas fa-print mr-1"></i> Print
          </button>
        <?php endif; ?>
      </div>
    </div>

    <div class="card shadow mb-4">
      <div class="card-body">

        <?php if ($ob === ''): ?>
          <!-- ========== LANDING SCREEN: SALESMAN SELECTION ========== -->
          <div class="py-3">
            <div class="text-center mb-4">
              <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-2" style="width: 60px; height: 60px;">
                <i class="fas fa-user-check fa-2x text-primary"></i>
              </div>
              <h4 class="font-weight-bold text-gray-800 mb-1">Select an Salesman</h4>
              <p class="text-muted">Choose an salesman to view their sales invoices and profit margin.</p>
            </div>

            <div class="row justify-content-center mb-4">
              <div class="col-md-7 col-lg-6">
                <div class="input-group shadow-sm">
                  <div class="input-group-prepend">
                    <span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted"></i></span>
                  </div>
                  <input type="text" id="bookerQuickFilter" class="form-control border-left-0" placeholder="Search salesman by name, code, phone, or address..." autocomplete="off">
                  <?php if (count($all_bookers) > 0): ?>
                    <div class="input-group-append">
                      <a href="order_booker_invoices.php?order_booker_id=all" class="btn btn-outline-secondary" title="View invoices for all salesmen">
                        <i class="fas fa-users"></i> All Bookers
                      </a>
                    </div>
                  <?php endif; ?>
                </div>
                <small class="text-muted d-block mt-1 text-center">Type any letter to filter the list below instantly.</small>
              </div>
            </div>

            <div class="row" id="bookerCardsRow">
              <?php if (empty($all_bookers)): ?>
                <div class="col-12 text-center text-muted py-5">
                  <i class="fas fa-user-slash fa-3x mb-3 text-secondary"></i>
                  <p class="mb-0">No active salesmen found in the system.</p>
                  <a href="<?= BASE_URL ?>modules/employees/create.php" class="btn btn-primary btn-sm mt-2"><i class="fas fa-user-plus"></i> Add Salesman</a>
                </div>
              <?php else: ?>
                <?php foreach ($all_bookers as $b): ?>
                  <?php
                    $b_sales  = (float)$b['total_sales'];
                    $b_cost   = (float)$b['total_cost'];
                    $b_profit = $b_sales - $b_cost;
                    $b_margin = $b_sales > 0 ? ($b_profit / $b_sales) * 100 : 0;
                  ?>
                  <div class="col-md-6 col-lg-4 mb-3 booker-card-item" data-search="<?= htmlspecialchars(strtolower($b['name'] . ' ' . ($b['booker_code'] ?? '') . ' ' . ($b['address'] ?? '') . ' ' . ($b['phone'] ?? ''))) ?>">
                    <div class="card h-100 border-left-primary shadow-sm">
                      <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                          <div>
                            <h5 class="font-weight-bold mb-0 text-gray-900"><?= htmlspecialchars($b['name']) ?></h5>
                            <small class="text-muted"><?= htmlspecialchars($b['booker_code'] ?? '') ?></small>
                          </div>
                          <span class="badge badge-primary badge-pill px-2 py-1"><?= (int)$b['invoice_count'] ?> Invoices</span>
                        </div>

                        <div class="small text-muted mb-2 text-truncate">
                          <?php if (!empty($b['phone'])): ?>
                            <div><i class="fas fa-phone-alt fa-fw mr-1"></i> <?= htmlspecialchars($b['phone']) ?></div>
                          <?php endif; ?>
                          <?php if (!empty($b['address'])): ?>
                            <div class="mt-1"><i class="fas fa-map-marker-alt fa-fw mr-1 text-danger"></i> <?= htmlspecialchars($b['address']) ?></div>
                          <?php endif; ?>
                        </div>

                        <div class="bg-light rounded p-2 mb-3">
                          <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Total Sales:</span>
                            <strong class="text-dark">PKR <?= formatCurrency($b_sales) ?></strong>
                          </div>
                          <div class="d-flex justify-content-between small">
                            <span class="text-muted">Est. Profit:</span>
                            <strong class="<?= $b_profit >= 0 ? 'text-success' : 'text-danger' ?>">
                              PKR <?= formatCurrency($b_profit) ?>
                              <?php if ($b_sales > 0): ?><small>(<?= number_format($b_margin, 1) ?>%)</small><?php endif; ?>
                            </strong>
                          </div>
                        </div>

                        <a href="order_booker_invoices.php?order_booker_id=<?= $b['id'] ?>" class="btn btn-primary btn-block btn-sm">
                          <i class="fas fa-file-invoice mr-1"></i> View Invoices &amp; Profit <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <div id="noBookersFound" class="text-center py-5 d-none">
              <i class="fas fa-search fa-2x text-muted mb-2"></i>
              <p class="text-muted mb-0">No salesmen matched your search query.</p>
            </div>
          </div>

        <?php else: ?>
          <!-- ========== INVOICES & PROFIT REPORT VIEW ========== -->

          <!-- PRINT HEADER -->
          <div class="d-none d-print-block mb-3">
            <div class="text-center pb-2 border-bottom">
              <h3 class="font-weight-bold mb-0" style="color: #0f172a;"><?= htmlspecialchars(APP_SHORT_NAME) ?></h3>
              <div class="small text-muted">Wholesale Distribution &middot; Pakistan</div>
              <h5 class="font-weight-bold text-primary mt-2 mb-1">SALESMAN INVOICES &amp; PROFIT REPORT</h5>
              <div class="small text-dark font-weight-bold">
                Salesman: <?= htmlspecialchars($selected_booker ? $selected_booker['name'] : 'All Salesmen') ?>
                <?php if ($selected_booker && !empty($selected_booker['phone'])): ?>
                  &middot; Phone: <?= htmlspecialchars($selected_booker['phone']) ?>
                <?php endif; ?>
                <?php if ($from || $to): ?>
                  &middot; Period: <?= $from ? formatDate($from) : 'Start' ?> to <?= $to ? formatDate($to) : 'Present' ?>
                <?php endif; ?>
                <?php if ($route_name): ?>
                  &middot; Route: <?= htmlspecialchars($route_name) ?>
                <?php endif; ?>
                <?php if ($area): ?>
                  &middot; Area: <?= htmlspecialchars($area) ?>
                <?php endif; ?>
              </div>
              <div class="small text-muted mt-1">Printed on <?= date('d-m-Y H:i') ?><?= $printed_by ? ' by ' . htmlspecialchars($printed_by) : '' ?></div>
            </div>

            <table class="table table-sm table-bordered mt-2 mb-3">
              <tr class="text-center">
                <th>Total Invoices</th>
                <th>Total Sales</th>
                <th>Purchase Cost</th>
                <th>Total Profit</th>
                <th>Profit Margin</th>
                <th>Total Due</th>
              </tr>
              <tr class="text-center font-weight-bold">
                <td><?= count($sales) ?></td>
                <td>PKR <?= formatCurrency($total_sales) ?></td>
                <td class="text-secondary">PKR <?= formatCurrency($total_cost) ?></td>
                <td style="color: <?= $total_profit >= 0 ? '#0f766e' : '#b91c1c' ?>;">PKR <?= formatCurrency($total_profit) ?></td>
                <td style="color: <?= $overall_margin >= 0 ? '#0f766e' : '#b91c1c' ?>;"><?= number_format($overall_margin, 1) ?>%</td>
                <td style="color: #b91c1c;">PKR <?= formatCurrency($total_due) ?></td>
              </tr>
            </table>
          </div>

          <!-- FILTER TOOLBAR (SCREEN ONLY) -->
          <form method="get" class="mb-4 d-print-none bg-light p-3 rounded border">
            <input type="hidden" name="order_booker_id" value="<?= htmlspecialchars($ob) ?>">
            <div class="form-row align-items-end">
              <?php if ($is_admin): ?>
                <div class="col-lg-2 col-md-4 mb-2">
                  <label class="small text-muted mb-1 font-weight-bold"><i class="fas fa-user-tie"></i> Salesman</label>
                  <select class="form-control form-control-sm" onchange="window.location.href='order_booker_invoices.php?order_booker_id=' + this.value + '<?= $from ? '&from=' . urlencode($from) : '' ?><?= $to ? '&to=' . urlencode($to) : '' ?><?= $rid !== '' ? '&route_id=' . urlencode($rid) : '' ?>'">
                    <option value="all" <?= $ob === 'all' ? 'selected' : '' ?>>-- All Salesmen --</option>
                    <?php foreach ($all_bookers as $b): ?>
                      <option value="<?= $b['id'] ?>" <?= $ob == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              <?php endif; ?>
              <div class="col-lg-2 col-md-4 mb-2">
                <label class="small text-muted mb-1 font-weight-bold"><i class="fas fa-calendar-alt"></i> From Date</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($from) ?>">
              </div>
              <div class="col-lg-2 col-md-4 mb-2">
                <label class="small text-muted mb-1 font-weight-bold"><i class="fas fa-calendar-alt"></i> To Date</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?= htmlspecialchars($to) ?>">
              </div>
              <div class="col-lg-2 col-md-4 mb-2">
                <label class="small text-muted mb-1 font-weight-bold"><i class="fas fa-truck"></i> Route</label>
                <select name="route_id" class="form-control form-control-sm">
                  <option value="">-- All Routes --</option>
                  <?php foreach ($routes as $r): ?>
                    <option value="<?= $r['id'] ?>" <?= $rid == $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-lg-2 col-md-4 mb-2">
                <label class="small text-muted mb-1 font-weight-bold"><i class="fas fa-map-marker-alt"></i> Area</label>
                <select name="area" class="form-control form-control-sm">
                  <option value="">-- All Areas --</option>
                  <?php foreach ($areas as $ar): ?>
                    <option value="<?= htmlspecialchars($ar) ?>" <?= $area === $ar ? 'selected' : '' ?>><?= htmlspecialchars($ar) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-lg-1 col-md-4 mb-2">
                <button type="submit" class="btn btn-sm btn-primary btn-block"><i class="fas fa-filter"></i> Filter</button>
              </div>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
              <?php if ($from || $to || $rid !== '' || $area !== ''): ?>
                <a href="order_booker_invoices.php?order_booker_id=<?= urlencode($ob) ?>" class="btn btn-xs btn-outline-secondary">
                  <i class="fas fa-times mr-1"></i> Clear Filters
                </a>
              <?php else: ?>
                <div></div>
              <?php endif; ?>
              <div>
                <input type="text" id="invoiceTableSearch" class="form-control form-control-sm" placeholder="Live search invoice/customer..." style="max-width: 250px;">
              </div>
            </div>
          </form>

          <!-- KPI METRIC CARDS (SCREEN ONLY) -->
          <div class="row mb-4 d-print-none">
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
              <div class="card border-left-primary shadow-sm h-100 py-2">
                <div class="card-body py-1">
                  <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Invoices</div>
                  <div class="h5 mb-0 font-weight-bold text-gray-800"><?= count($sales) ?></div>
                  <small class="text-muted">Total orders recorded</small>
                </div>
              </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
              <div class="card border-left-info shadow-sm h-100 py-2">
                <div class="card-body py-1">
                  <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Sales</div>
                  <div class="h5 mb-0 font-weight-bold text-gray-800">PKR <?= formatCurrency($total_sales) ?></div>
                  <small class="text-muted">Gross sale revenue</small>
                </div>
              </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
              <div class="card border-left-secondary shadow-sm h-100 py-2">
                <div class="card-body py-1">
                  <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Purchase Cost</div>
                  <div class="h5 mb-0 font-weight-bold text-gray-800">PKR <?= formatCurrency($total_cost) ?></div>
                  <small class="text-muted">At product purchase rates</small>
                </div>
              </div>
            </div>
            <div class="col-xl-3 col-md-6 col-sm-6 mb-3">
              <div class="card border-left-success shadow-sm h-100 py-2">
                <div class="card-body py-1">
                  <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Profit &amp; Margin</div>
                  <div class="h5 mb-0 font-weight-bold <?= $total_profit >= 0 ? 'text-success' : 'text-danger' ?>">
                    PKR <?= formatCurrency($total_profit) ?>
                    <span class="badge badge-pill <?= $overall_margin >= 0 ? 'badge-success' : 'badge-danger' ?> ml-1" style="font-size: 0.75rem;"><?= number_format($overall_margin, 1) ?>%</span>
                  </div>
                  <small class="text-muted">Sales minus purchase cost</small>
                </div>
              </div>
            </div>
            <div class="col-xl-3 col-md-6 col-sm-12 mb-3">
              <div class="card border-left-warning shadow-sm h-100 py-2">
                <div class="card-body py-1">
                  <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Collections / Due</div>
                  <div class="small">
                    <span class="text-success font-weight-bold">Paid: PKR <?= formatCurrency($total_paid) ?></span> &middot;
                    <span class="<?= $total_due > 0 ? 'text-danger font-weight-bold' : 'text-muted' ?>">Due: PKR <?= formatCurrency($total_due) ?></span>
                  </div>
                  <small class="text-muted"><?= $total_sales > 0 ? number_format(($total_paid / $total_sales) * 100, 0) : 0 ?>% recovered</small>
                </div>
              </div>
            </div>
          </div>

          <!-- INVOICES & PROFIT TABLE -->
          <div class="table-responsive">
            <table class="table table-bordered table-hover" id="invoicesTable">
              <thead class="thead-light">
                <tr>
                  <th style="width: 40px;">#</th>
                  <th>Invoice No</th>
                  <th>Date</th>
                  <?php if ($ob === 'all'): ?><th>Salesman</th><?php endif; ?>
                  <th>Customer</th>
                  <th>Area</th>
                  <th>Route</th>
                  <th class="text-right">Sale Total</th>
                  <th class="text-right">Cost Total</th>
                  <th class="text-right font-weight-bold text-success" style="min-width: 130px;">Profit (Margin)</th>
                  <th class="text-right">Paid</th>
                  <th class="text-right">Due</th>
                  <th class="text-center d-print-none" style="min-width: 100px;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($sales)): ?>
                  <tr>
                    <td colspan="<?= $ob === 'all' ? 13 : 12 ?>" class="text-center text-muted py-5">
                      <i class="fas fa-inbox fa-3x mb-3 text-secondary"></i>
                      <p class="mb-0">No sales invoices found for the selected criteria.</p>
                    </td>
                  </tr>
                <?php else: $i = 1; foreach ($sales as $s): $inv_type = (($s['customer_invoice_type'] ?? '') === 'warranty') ? 'warranty' : 'sale'; ?>
                  <tr>
                    <td><?= $i++ ?></td>
                    <td class="font-weight-bold text-primary">
                      <a href="print_invoice.php?id=<?= (int)$s['id'] ?>&type=<?= $inv_type ?>" target="_blank" title="View Full Invoice"><?= htmlspecialchars($s['invoice_no']) ?></a>
                    </td>
                    <td style="white-space: nowrap;"><?= formatDate($s['invoice_date']) ?></td>
                    <?php if ($ob === 'all'): ?>
                      <td><strong><?= htmlspecialchars($s['order_taker_name'] ?? $s['booker_name'] ?? 'Walk-in') ?></strong></td>
                    <?php endif; ?>
                    <td>
                      <div class="font-weight-bold text-dark"><?= htmlspecialchars($s['customer_name'] ?? $s['customer_shop'] ?? 'Walk-in Customer') ?></div>
                      <?php if (!empty($s['customer_phone'])): ?>
                        <small class="text-muted"><i class="fas fa-phone-alt fa-xs"></i> <?= htmlspecialchars($s['customer_phone']) ?></small>
                      <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($s['customer_area'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($s['route_name'] ?: '—') ?></td>
                    <td class="text-right font-weight-bold">PKR <?= formatCurrency($s['grand_total']) ?></td>
                    <td class="text-right text-muted">PKR <?= formatCurrency($s['total_cost']) ?></td>
                    <td class="text-right font-weight-bold <?= $s['calc_profit'] >= 0 ? 'text-success' : 'text-danger' ?>">
                      PKR <?= formatCurrency($s['calc_profit']) ?>
                      <br><span class="badge badge-pill <?= $s['calc_margin'] >= 0 ? 'badge-success' : 'badge-danger' ?>" style="font-size: 0.7rem;"><?= number_format($s['calc_margin'], 1) ?>%</span>
                    </td>
                    <td class="text-right text-success">PKR <?= formatCurrency($s['paid_amount']) ?></td>
                    <td class="text-right <?= $s['balance_due'] > 0 ? 'text-danger font-weight-bold' : 'text-muted' ?>">PKR <?= formatCurrency($s['balance_due']) ?></td>
                    <td class="text-center d-print-none" nowrap>
                      <a href="print_invoice.php?id=<?= (int)$s['id'] ?>&type=<?= $inv_type ?>" class="btn btn-xs btn-outline-success" target="_blank" title="Print Invoice"><i class="fas fa-print"></i></a>
                      <a href="view_sale.php?id=<?= (int)$s['id'] ?>" class="btn btn-xs btn-outline-primary" target="_blank" title="View Invoice"><i class="fas fa-eye"></i></a>
                      <button type="button" class="btn btn-xs btn-outline-info btn-profit-breakdown" data-id="<?= (int)$s['id'] ?>" data-inv="<?= htmlspecialchars($s['invoice_no']) ?>" title="View Profit Breakdown"><i class="fas fa-chart-pie"></i></button>
                      <?php if ($is_admin): ?>
                        <a href="edit_sale.php?id=<?= (int)$s['id'] ?>" class="btn btn-xs btn-outline-warning" title="Edit Sale"><i class="fas fa-edit"></i></a>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
              <tfoot class="font-weight-bold bg-light">
                <tr>
                  <td colspan="<?= $ob === 'all' ? 7 : 6 ?>" class="text-right text-uppercase">Grand Total (<?= count($sales) ?> Invoices):</td>
                  <td class="text-right text-dark">PKR <?= formatCurrency($total_sales) ?></td>
                  <td class="text-right text-muted">PKR <?= formatCurrency($total_cost) ?></td>
                  <td class="text-right <?= $total_profit >= 0 ? 'text-success' : 'text-danger' ?>">PKR <?= formatCurrency($total_profit) ?><br><span class="badge badge-pill <?= $overall_margin >= 0 ? 'badge-success' : 'badge-danger' ?>" style="font-size: 0.72rem;"><?= number_format($overall_margin, 1) ?>% Margin</span></td>
                  <td class="text-right text-success">PKR <?= formatCurrency($total_paid) ?></td>
                  <td class="text-right <?= $total_due > 0 ? 'text-danger' : 'text-muted' ?>">PKR <?= formatCurrency($total_due) ?></td>
                  <td class="d-print-none"></td>
                </tr>
              </tfoot>
            </table>
          </div>

          <!-- PRINT FOOTER -->
          <div class="d-none d-print-block mt-4 pt-3 border-top">
            <div class="d-flex justify-content-between">
              <div>
                <strong>Prepared by:</strong> <?= htmlspecialchars($printed_by ?: 'Administrator') ?><br>
                <small class="text-muted"><?= htmlspecialchars(APP_NAME) ?></small>
              </div>
              <div class="text-center" style="min-width: 180px;">
                <div class="border-bottom pb-4 mb-1"></div>
                <strong>Salesman Signature</strong>
              </div>
              <div class="text-right" style="min-width: 180px;">
                <div class="border-bottom pb-4 mb-1"></div>
                <strong>Authorized Signature</strong>
              </div>
            </div>
          </div>

        <?php endif; ?>

      </div>
    </div>
  </div>
</main>

<!-- ===== PROFIT BREAKDOWN MODAL ===== -->
<div class="modal fade" id="profitBreakdownModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white py-2">
        <h6 class="modal-title font-weight-bold" id="profitModalTitle"><i class="fas fa-chart-pie mr-1"></i> Invoice Profit Margin Breakdown</h6>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body p-3" id="profitModalBody">
        <div class="text-center py-4" id="profitModalLoader">
          <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
          <p class="text-muted mt-2 mb-0">Loading invoice profit details...</p>
        </div>
        <div id="profitModalContent" class="d-none">
          <div class="row mb-3 pb-2 border-bottom">
            <div class="col-sm-6">
              <div><strong>Invoice:</strong> <span id="pmInvNo" class="text-primary font-weight-bold"></span></div>
              <div><strong>Customer:</strong> <span id="pmCustomer"></span></div>
              <div class="small text-muted" id="pmCustInfo"></div>
            </div>
            <div class="col-sm-6 text-sm-right">
              <div><strong>Date:</strong> <span id="pmDate"></span></div>
              <div><strong>Salesman:</strong> <span id="pmBooker"></span></div>
              <div><strong>Route:</strong> <span id="pmRoute"></span></div>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-bordered">
              <thead class="thead-light">
                <tr>
                  <th>Product</th>
                  <th class="text-center">Qty</th>
                  <th class="text-right">Sale Price</th>
                  <th class="text-right">Sale Total</th>
                  <th class="text-right">Purchase Rate</th>
                  <th class="text-right">Cost Total</th>
                  <th class="text-right font-weight-bold text-success">Profit</th>
                  <th class="text-center font-weight-bold">Margin</th>
                </tr>
              </thead>
              <tbody id="pmItemsTbody"></tbody>
              <tfoot class="font-weight-bold bg-light" id="pmTfoot"></tfoot>
            </table>
          </div>
          <div class="alert alert-secondary py-2 px-3 small mb-0">
            <i class="fas fa-info-circle text-primary mr-1"></i>
            <strong>Note:</strong> Profit is calculated dynamically using each item's sale rate minus the product's purchase rate (cost per unit).
          </div>
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<style>
.btn-xs { padding: 0.15rem 0.4rem; font-size: 0.75rem; line-height: 1.4; border-radius: 0.2rem; }
@media print {
  @page { size: A4 landscape; margin: 8mm; }
  .table th { font-size: 11px !important; padding: 5px 6px !important; }
  .table td { font-size: 11px !important; padding: 4px 6px !important; }
}
</style>

<script>
$(document).ready(function(){
  $('#bookerQuickFilter').on('input', function(){
    var q = $.trim($(this).val()).toLowerCase();
    var matchCount = 0;
    $('.booker-card-item').each(function(){
      var text = $(this).data('search') || '';
      if (!q || text.indexOf(q) !== -1) { $(this).show(); matchCount++; }
      else { $(this).hide(); }
    });
    if (matchCount === 0) { $('#noBookersFound').removeClass('d-none'); }
    else { $('#noBookersFound').addClass('d-none'); }
  });

  $('#invoiceTableSearch').on('input', function(){
    var q = $.trim($(this).val()).toLowerCase();
    $('#invoicesTable tbody tr').each(function(){
      var text = $(this).text().toLowerCase();
      if (!q || text.indexOf(q) !== -1) { $(this).show(); }
      else { $(this).hide(); }
    });
  });

  $('.btn-profit-breakdown').on('click', function(){
    var saleId = $(this).data('id');
    var invNo = $(this).data('inv');
    $('#profitModalTitle').html('<i class="fas fa-chart-pie mr-1"></i> Invoice #' + invNo + ' Profit Margin Breakdown');
    $('#profitModalLoader').show();
    $('#profitModalContent').addClass('d-none');
    $('#profitBreakdownModal').modal('show');

    $.getJSON('ajax_invoice_profit_breakdown.php', {id: saleId}, function(res){
      $('#profitModalLoader').hide();
      if (res.error) { alert(res.error); $('#profitBreakdownModal').modal('hide'); return; }
      $('#profitModalContent').removeClass('d-none');

      $('#pmInvNo').text(res.invoice_no);
      $('#pmCustomer').text(res.customer_name);
      var custInfo = [];
      if (res.customer_phone) custInfo.push('Phone: ' + res.customer_phone);
      if (res.customer_area) custInfo.push('Area: ' + res.customer_area);
      $('#pmCustInfo').text(custInfo.join(' | '));
      $('#pmDate').text(res.sale_date);
      $('#pmBooker').text(res.order_taker_name);
      $('#pmRoute').text(res.route_name || '—');

      var $tbody = $('#pmItemsTbody').empty();
      $.each(res.items, function(i, item){
        var profitClass = item.profit >= 0 ? 'text-success' : 'text-danger';
        var badgeClass = item.margin_pct >= 0 ? 'badge-success' : 'badge-danger';
        var tr = '<tr>' +
          '<td><strong>' + item.product_name + '</strong></td>' +
          '<td class="text-center">' + item.quantity + ' <br><small class="text-muted">' + item.packaging_label + '</small></td>' +
          '<td class="text-right">PKR ' + parseFloat(item.sale_price).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + '</td>' +
          '<td class="text-right font-weight-bold">PKR ' + parseFloat(item.sale_subtotal).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + '</td>' +
          '<td class="text-right">PKR ' + parseFloat(item.cost_per_unit).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + ' <br><small class="text-muted">Rate: ' + parseFloat(item.purchase_price).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + '</small></td>' +
          '<td class="text-right text-muted">PKR ' + parseFloat(item.cost_total).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + '</td>' +
          '<td class="text-right font-weight-bold ' + profitClass + '">PKR ' + parseFloat(item.profit).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + '</td>' +
          '<td class="text-center"><span class="badge ' + badgeClass + '">' + item.margin_pct + '%</span></td>' +
          '</tr>';
        $tbody.append(tr);
      });

      var netProfitClass = res.net_profit >= 0 ? 'text-success' : 'text-danger';
      var netBadgeClass = res.margin_pct >= 0 ? 'badge-success' : 'badge-danger';
      var tfootHtml = '<tr>' +
        '<td colspan="3" class="text-right text-uppercase">Total:</td>' +
        '<td class="text-right">PKR ' + parseFloat(res.total_sale).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + '</td>' +
        '<td></td>' +
        '<td class="text-right">PKR ' + parseFloat(res.total_cost).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + '</td>' +
        '<td class="text-right ' + netProfitClass + '">PKR ' + parseFloat(res.net_profit).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + '</td>' +
        '<td class="text-center"><span class="badge ' + netBadgeClass + '">' + res.margin_pct + '%</span></td>' +
        '</tr>';

      if (res.discount_amount > 0) {
        tfootHtml += '<tr class="text-muted small">' +
          '<td colspan="3" class="text-right">Invoice Discount Applied:</td>' +
          '<td class="text-right text-danger">- PKR ' + parseFloat(res.discount_amount).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + '</td>' +
          '<td colspan="4"></td>' +
          '</tr>';
      }

      $('#pmTfoot').html(tfootHtml);
    }).fail(function(){
      $('#profitModalLoader').hide();
      alert('Failed to load profit details. Please try again.');
      $('#profitBreakdownModal').modal('hide');
    });
  });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>