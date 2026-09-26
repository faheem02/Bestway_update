<?php
/**
 * Bestway Wholesale Distribution - Delivery List / Pack List
 * Matching Mehboob Traders Implementation
 */
require_once dirname(__DIR__, 2) . '/includes/functions.php';
$page_title = 'Delivery List / Pack List';
$compact_page_heading = true;

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . 'login.php'); exit;
}
requireRole(['admin', 'salesman']);

$my_areas = currentUserAreas($pdo); // null = admin (all areas)
$all_known = [];
try {
    $a1 = $pdo->query("SELECT name FROM areas WHERE status = 1 ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    $a2 = $pdo->query("SELECT name FROM routes WHERE status = 'Active' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    $all_known = array_values(array_unique(array_filter(array_merge($a1 ?: [], $a2 ?: []))));
    sort($all_known);
} catch (Exception $e) {}
if (empty($all_known)) {
    $all_known = allKnownAreas($pdo);
}
$allowed_areas = isAdmin() ? $all_known : (array)$my_areas;

// Filters
$today = date('Y-m-d');
$from = isset($_GET['from']) ? trim($_GET['from']) : '';
$to = isset($_GET['to']) ? trim($_GET['to']) : '';
$area = trim($_GET['area'] ?? '');
$salesman_id = (int)($_GET['salesman_id'] ?? 0);
$ob = $_GET['order_booker_id'] ?? '';
$q = trim($_GET['q'] ?? '');
$view_mode = trim($_GET['view'] ?? 'loading');
if (!in_array($view_mode, ['loading', 'vouchers'], true)) {
    $view_mode = 'loading';
}

if ($area !== '' && !in_array($area, $allowed_areas, true)) {
    $area = '';
}

// Dropdown options
$all_salesmen = [];
try {
    $all_salesmen = $pdo->query("SELECT id, full_name FROM employees WHERE employee_type IN ('salesman', 'general') AND status = 1 ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($all_salesmen)) {
        $all_salesmen = $pdo->query("SELECT id, full_name FROM employees WHERE status = 1 ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}

$all_order_bookers = [];
try {
    $all_order_bookers = $pdo->query("SELECT id, full_name, username FROM users WHERE role = 'salesman' AND status = 1 ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($all_order_bookers)) {
        $all_order_bookers = $pdo->query("SELECT id, name AS full_name, booker_code AS username FROM bookers WHERE status = 'Active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}

// Build Sales Query
$sql = "SELECT 
            si.id AS sale_id,
            si.invoice_no,
            si.invoice_date AS sale_date,
            si.invoice_date AS delivery_date,
            si.grand_total AS total_amount,
            si.discount_amount,
            si.paid_amount,
            si.balance_due AS due_amount,
            'completed' AS status,
            si.notes,
            si.created_by,
            si.customer_id,
            COALESCE(c.customer_code, CONCAT('CUS-', LPAD(COALESCE(si.customer_id, 0), 4, '0'))) AS customer_no,
            COALESCE(NULLIF(si.customer_name, ''), c.name, 'Walk-in Customer') AS customer_name,
            c.phone AS customer_phone,
            '' AS customer_cnic,
            c.address AS customer_address,
            '' AS customer_city,
            COALESCE(c.area, si.route_name, '') AS customer_area,
            si.booker_id AS salesman_id,
            COALESCE(e.full_name, si.booker_name, 'Direct') AS salesman_name,
            si.booker_id AS order_booker_id,
            COALESCE(u.full_name, si.booker_name, 'Office') AS order_booker_name,
            COALESCE(u.username, si.booker_name, 'office') AS order_booker_username
        FROM sales_invoices si
        LEFT JOIN customers c ON si.customer_id = c.id
        LEFT JOIN employees e ON si.booker_id = e.id
        LEFT JOIN users u ON si.created_by = u.id
        WHERE 1=1";

$params = [];

if ($from !== '') {
    $sql .= " AND si.invoice_date >= ?";
    $params[] = $from;
}
if ($to !== '') {
    $sql .= " AND si.invoice_date <= ?";
    $params[] = $to;
}
if ($area !== '') {
    $sql .= " AND (LOWER(c.area) = LOWER(?) OR LOWER(si.route_name) = LOWER(?))";
    $params[] = $area;
    $params[] = $area;
} elseif ($my_areas !== null) {
    if (empty($my_areas)) {
        $sql .= " AND 1=0";
    } else {
        $in_placeholders = implode(',', array_fill(0, count($my_areas), '?'));
        $sql .= " AND (c.area IN ($in_placeholders) OR si.route_name IN ($in_placeholders))";
        $params = array_merge($params, $my_areas, $my_areas);
    }
}
if ($salesman_id > 0) {
    $sql .= " AND si.booker_id = ?";
    $params[] = $salesman_id;
}
if (!isAdmin()) {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $bkid = function_exists('currentBookerId') ? currentBookerId($pdo) : null;
    $uname = $_SESSION['user_name'] ?? $_SESSION['username'] ?? '';
    if ($bkid) {
        $sql .= " AND (si.created_by = ? OR si.booker_id = ? OR si.booker_name = ?)";
        $params[] = $uid;
        $params[] = $bkid;
        $params[] = $uname;
    } else {
        $sql .= " AND (si.created_by = ? OR si.booker_name = ?)";
        $params[] = $uid;
        $params[] = $uname;
    }
} elseif ($ob !== '') {
    $sql .= " AND (si.created_by = ? OR si.booker_id = ?)";
    $params[] = $ob;
    $params[] = $ob;
}
if ($q !== '') {
    $sql .= " AND (si.invoice_no LIKE ? OR c.name LIKE ? OR si.customer_name LIKE ? OR c.customer_code LIKE ? OR c.phone LIKE ? OR c.area LIKE ? OR si.route_name LIKE ? OR si.booker_name LIKE ?)";
    $params = array_merge($params, ["%$q%", "%$q%", "%$q%", "%$q%", "%$q%", "%$q%", "%$q%", "%$q%"]);
}

$sql .= " ORDER BY si.invoice_date DESC, si.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch items for all matched sales
$sale_ids = array_column($sales, 'sale_id');
$items_by_sale = [];

if (!empty($sale_ids)) {
    $in_sale_ids = implode(',', array_map('intval', $sale_ids));
    $items_sql = "SELECT 
                    si.id,
                    COALESCE(si.invoice_id, si.sale_id) AS sale_id,
                    si.product_id,
                    si.quantity,
                    COALESCE(NULLIF(si.unit_price, 0), NULLIF(si.sale_price, 0), si.trade_price, 0) AS price,
                    COALESCE(si.total_price, si.total_amount, si.quantity * si.unit_price) AS subtotal,
                    COALESCE(p.product_code, '') AS product_code,
                    COALESCE(NULLIF(si.item_name, ''), p.name, 'Item') AS product_name,
                    COALESCE(p.packs_per_box, 1) AS boxes_per_carton,
                    COALESCE(p.pack_size, p.stock_unit, 'Pack') AS unit,
                    COALESCE(c.name, 'General') AS company_name
                  FROM sale_items si
                  LEFT JOIN products p ON si.product_id = p.id
                  LEFT JOIN companies c ON p.company_id = c.id
                  WHERE COALESCE(si.invoice_id, si.sale_id) IN ($in_sale_ids)
                  ORDER BY si.id ASC";
    $items_stmt = $pdo->query($items_sql);
    $all_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($all_items as $it) {
        $sid = $it['sale_id'];
        $bpc = max((int)($it['boxes_per_carton'] ?? 1), 1);
        $qty = (int)$it['quantity'];

        if ($bpc > 1) {
            $cartons = intdiv($qty, $bpc);
            $loose = $qty % $bpc;
        } else {
            $cartons = 0;
            $loose = $qty;
        }

        $it['cartons'] = $cartons;
        $it['loose'] = $loose;
        $it['bpc'] = $bpc;

        $items_by_sale[$sid][] = $it;
    }
}

// Group items for Delivery Loading Sheet by Product ID AND Price (Rate)
// Agar kisi product ka rate kam ya zyada hai to wo alag alag 2 dafa show hoga
$grouped_loading_items = [];
$total_loading_cartons = 0;
$total_loading_loose = 0;
$total_loading_qty = 0;
$total_loading_amount = 0;

if (!empty($all_items)) {
    foreach ($all_items as $it) {
        $pid = (int)($it['product_id'] ?? 0);
        $price = round((float)($it['price'] ?? 0), 2);
        $rate_key = $pid . '_' . number_format($price, 2, '.', '');

        $bpc = max((int)($it['boxes_per_carton'] ?? 1), 1);
        $qty = (int)$it['quantity'];
        $subtotal = (float)$it['subtotal'];

        if (!isset($grouped_loading_items[$rate_key])) {
            $grouped_loading_items[$rate_key] = [
                'product_id'     => $pid,
                'product_code'   => $it['product_code'],
                'product_name'   => $it['product_name'],
                'company_name'   => $it['company_name'],
                'bpc'            => $bpc,
                'unit'           => $it['unit'],
                'price'          => $price,
                'total_quantity' => 0,
                'cartons'        => 0,
                'loose'          => 0,
                'total_amount'   => 0.0,
                'orders_count'   => 0
            ];
        }

        $grouped_loading_items[$rate_key]['total_quantity'] += $qty;
        $grouped_loading_items[$rate_key]['total_amount']   += $subtotal;
        $grouped_loading_items[$rate_key]['orders_count']++;
    }

    foreach ($grouped_loading_items as &$git) {
        $bpc = $git['bpc'];
        $qty = $git['total_quantity'];
        if ($bpc > 1) {
            $git['cartons'] = intdiv($qty, $bpc);
            $git['loose']   = $qty % $bpc;
        } else {
            $git['cartons'] = 0;
            $git['loose']   = $qty;
        }

        $total_loading_cartons += $git['cartons'];
        $total_loading_loose   += $git['loose'];
        $total_loading_qty     += $qty;
        $total_loading_amount  += $git['total_amount'];
    }
    unset($git);

    uasort($grouped_loading_items, function($a, $b) {
        $cmp = strcasecmp($a['product_name'], $b['product_name']);
        if ($cmp === 0) {
            return ($a['price'] < $b['price']) ? -1 : 1;
        }
        return $cmp;
    });
}

// Attach items to each sale and calculate totals
$grand_total_vouchers = count($sales);
$grand_total_items = 0;
$grand_total_cartons = 0;
$grand_total_boxes = 0;
$grand_total_amount = 0;

foreach ($sales as &$s) {
    $sid = $s['sale_id'];
    $s['items'] = $items_by_sale[$sid] ?? [];

    $s_cartons = 0;
    $s_boxes = 0;
    $s_amount = 0;
    $prod_names = [];

    foreach ($s['items'] as $it) {
        $s_cartons += $it['cartons'];
        $s_boxes += $it['loose'];
        $s_amount += (float)$it['subtotal'];
        $grand_total_items++;
        $prod_names[] = $it['product_name'] . ' ' . $it['product_code'];
    }

    $s['total_cartons'] = $s_cartons;
    $s['total_boxes'] = $s_boxes;
    $s['calculated_amount'] = $s_amount;
    $s['product_search_blob'] = implode(' ', $prod_names);

    $grand_total_cartons += $s_cartons;
    $grand_total_boxes += $s_boxes;
    $grand_total_amount += (float)$s['total_amount'];
}
unset($s);

// Format helper for numbers
if (!function_exists('fmtNum')) {
    function fmtNum($n) {
        $f = (float)$n;
        if (floor($f) == $f) {
            return number_format($f, 0);
        }
        return number_format($f, 2);
    }
}

// Labels for filter summary
$period_desc = 'All Dates';
if ($from && $to) {
    $period_desc = ($from === $to) ? formatDate($from) : formatDate($from) . ' to ' . formatDate($to);
} elseif ($from) {
    $period_desc = 'From ' . formatDate($from);
} elseif ($to) {
    $period_desc = 'Until ' . formatDate($to);
}

$salesman_label = 'All Salesmen';
if ($salesman_id > 0) {
    foreach ($all_salesmen as $sm) {
        if ((int)$sm['id'] === $salesman_id) {
            $salesman_label = $sm['full_name'];
            break;
        }
    }
}

$area_label = $area !== '' ? $area : 'All Areas';

$ob_name = '';
if ($ob !== '' && isAdmin()) {
    $on = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $on->execute([$ob]);
    $ob_name = (string)$on->fetchColumn();
}

$printed_by = '';
if (!empty($_SESSION['user_id'])) {
    $pu = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $pu->execute([(int)$_SESSION['user_id']]);
    $printed_by = (string)$pu->fetchColumn();
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="card shadow-sm border-0 mb-4">
  <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center d-print-none border-bottom">
    <div class="d-flex align-items-center">
      <div class="icon-circle bg-primary-soft text-primary mr-3">
        <i class="fas fa-truck-loading fa-lg"></i>
      </div>
      <div>
        <h5 class="mb-0 font-weight-bold text-dark">
          Delivery List / Loading Sheet
        </h5>
      </div>
    </div>
    <div class="d-flex flex-wrap mt-2 mt-md-0 gap-2 align-items-center">
      <div class="btn-group btn-group-sm mr-2 shadow-sm">
        <a href="view_challan.php?<?= http_build_query(array_merge($_GET, ['view' => 'loading'])) ?>" class="btn <?= $view_mode === 'loading' ? 'btn-primary' : 'btn-outline-primary' ?>">
          <i class="fas fa-boxes mr-1"></i> Delivery List (Rate-wise)
        </a>
        <a href="view_challan.php?<?= http_build_query(array_merge($_GET, ['view' => 'vouchers'])) ?>" class="btn <?= $view_mode === 'vouchers' ? 'btn-primary' : 'btn-outline-primary' ?>">
          <i class="fas fa-receipt mr-1"></i> Customer Vouchers
        </a>
      </div>
      <button type="button" class="btn btn-sm btn-dark shadow-sm mr-2" onclick="window.print()">
        <i class="fas fa-print mr-1"></i> Print Delivery List
      </button>
      <a href="<?= BASE_URL ?>modules/sale/sales.php" class="btn btn-sm btn-outline-secondary mr-2">
        <i class="fas fa-file-invoice mr-1"></i> Invoices
      </a>
      <a href="<?= BASE_URL ?>modules/sale/new_sale.php" class="btn btn-sm btn-success">
        <i class="fas fa-plus mr-1"></i> Take Order
      </a>
    </div>
  </div>

  <div class="card-body p-3 p-md-4">

    <!-- Filters Form -->
    <form method="get" id="packlistFilterForm" class="d-print-none mb-4 p-3 rounded-lg border bg-light shadow-sm">
      <input type="hidden" name="view" value="<?=htmlspecialchars($view_mode)?>">
      <div class="row g-2 align-items-end">
        <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
          <label class="form-label font-weight-bold text-xs text-uppercase text-muted mb-1">From Date</label>
          <input type="date" name="from" id="fromDate" class="form-control form-control-sm" value="<?=htmlspecialchars($from)?>">
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
          <label class="form-label font-weight-bold text-xs text-uppercase text-muted mb-1">To Date</label>
          <input type="date" name="to" id="toDate" class="form-control form-control-sm" value="<?=htmlspecialchars($to)?>">
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
          <label class="form-label font-weight-bold text-xs text-uppercase text-muted mb-1">Area / Route</label>
          <select name="area" class="form-control form-control-sm auto-submit-select">
            <option value="">-- All Areas --</option>
            <?php foreach ($allowed_areas as $an): ?>
            <option value="<?=htmlspecialchars($an)?>" <?= $area === $an ? 'selected' : '' ?>><?=htmlspecialchars($an)?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
          <label class="form-label font-weight-bold text-xs text-uppercase text-muted mb-1">Delivery Man</label>
          <select name="salesman_id" class="form-control form-control-sm auto-submit-select">
            <option value="">-- All Salesmen --</option>
            <?php foreach ($all_salesmen as $se): ?>
            <option value="<?=$se['id']?>" <?= $salesman_id === (int)$se['id'] ? 'selected' : '' ?>><?=htmlspecialchars($se['full_name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if (isAdmin()): ?>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
          <label class="form-label font-weight-bold text-xs text-uppercase text-muted mb-1">Salesman</label>
          <select name="order_booker_id" class="form-control form-control-sm auto-submit-select">
            <option value="">-- All Bookers --</option>
            <?php foreach ($all_order_bookers as $ob_user): ?>
            <option value="<?=$ob_user['id']?>" <?= (string)$ob === (string)$ob_user['id'] ? 'selected' : '' ?>><?=htmlspecialchars($ob_user['full_name'])?> (<?=htmlspecialchars($ob_user['username'])?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="col-lg-<?=isAdmin() ? '2' : '4'?> col-md-4 col-sm-6 mb-2">
          <label class="form-label font-weight-bold text-xs text-uppercase text-muted mb-1">Search Keywords</label>
          <input type="text" name="q" class="form-control form-control-sm" placeholder="Invoice / Customer / Product..." value="<?=htmlspecialchars($q)?>">
        </div>
      </div>

      <div class="d-flex justify-content-end align-items-center pt-2 border-top mt-2">
        <a href="view_challan.php?view=<?=htmlspecialchars($view_mode)?>" class="btn btn-sm btn-outline-secondary mr-2"><i class="fas fa-undo mr-1"></i> Reset</a>
        <button type="submit" class="btn btn-sm btn-primary px-3 shadow-sm"><i class="fas fa-filter mr-1"></i> Apply Filter</button>
      </div>
    </form>

    <?php if ($view_mode === 'loading'): ?>
      <!-- ========================================================
           VIEW 1: DELIVERY LOADING SHEET (ITEM-WISE WITH RATES)
           ======================================================== -->

      <!-- Printable Header for Loading Sheet -->
      <div class="report-sheet d-none d-print-block mb-3">
        <div class="text-center pb-2 border-bottom mb-2">
          <h4 class="font-weight-bold mb-0" style="color:#0f172a;">Bestway Distribution</h4>
          <small class="text-muted">Wholesale Medicine &amp; Pharma Distribution</small>
          <h5 class="font-weight-bold text-primary mt-2 mb-1">DELIVERY LIST / VEHICLE LOADING SHEET</h5>
          <div class="small text-muted">
            Period: <strong><?= $period_desc ?></strong> &middot; 
            Area: <strong><?= htmlspecialchars($area_label) ?></strong> &middot; 
            Delivery Man: <strong><?= htmlspecialchars($salesman_label) ?></strong> &middot; 
            Printed on <?= date('d-m-Y H:i') ?>
          </div>
        </div>
      </div>

      <!-- Live Search for Loading Sheet -->
      <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 p-2 bg-white rounded border d-print-none shadow-sm">
        <div class="d-flex align-items-center" style="max-width: 380px; width: 100%;">
          <div class="input-group input-group-sm">
            <div class="input-group-prepend"><span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted"></i></span></div>
            <input type="text" id="liveLoadingSearch" class="form-control border-left-0" placeholder="Search product name, code, company...">
          </div>
        </div>
        <div class="small text-muted mt-2 mt-sm-0">
          Showing <strong><?= count($grouped_loading_items) ?></strong> items / rates &middot; Total Units: <strong class="text-dark"><?= number_format($total_loading_qty) ?></strong>
        </div>
      </div>

      <!-- Loading Sheet Table -->
      <div class="table-responsive border rounded shadow-sm mb-4">
        <table class="table table-bordered table-hover mb-0 loading-sheet-table" id="loadingSheetTable">
          <thead class="thead-light">
            <tr>
              <th style="width: 45px;" class="text-center">#</th>
              <th>Product / Medicine Name</th>
              <th>Company</th>
              <th class="text-right" style="width: 120px;">Rate (PKR)</th>
              <th class="text-center" style="width: 95px;">Carton Size</th>
              <th class="text-right" style="width: 95px;">Full Cartons</th>
              <th class="text-right" style="width: 95px;">Loose Boxes</th>
              <th class="text-right" style="width: 110px;">Total Qty</th>
              <th class="text-right" style="width: 130px;">Amount</th>
              <th class="text-center" style="width: 70px;">Loaded</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($grouped_loading_items)): ?>
              <tr>
                <td colspan="10" class="text-center text-muted py-4">No delivery stock items found for selected filter.</td>
              </tr>
            <?php else: $si = 0; foreach ($grouped_loading_items as $git): $si++; ?>
              <tr class="loading-item-row">
                <td class="text-center text-muted"><?= $si ?></td>
                <td>
                  <span class="font-weight-bold text-dark product-title"><?= htmlspecialchars($git['product_name']) ?></span>
                  <?php if (!empty($git['product_code'])): ?>
                    <small class="text-muted d-block font-monospace"><?= htmlspecialchars($git['product_code']) ?></small>
                  <?php endif; ?>
                </td>
                <td class="text-secondary"><?= htmlspecialchars($git['company_name'] ?: '-') ?></td>
                <td class="text-right font-weight-bold text-primary" style="font-size: 1.02rem;">
                  Rs. <?= number_format($git['price'], 2) ?>
                </td>
                <td class="text-center text-muted small">
                  <?= $git['bpc'] > 1 ? $git['bpc'] . ' / ctn' : 'Pack' ?>
                </td>
                <td class="text-right font-weight-bold <?= $git['cartons'] > 0 ? 'text-success' : 'text-muted' ?>">
                  <?= $git['cartons'] > 0 ? $git['cartons'] : '-' ?>
                </td>
                <td class="text-right font-weight-bold <?= $git['loose'] > 0 ? 'text-warning' : 'text-muted' ?>">
                  <?= $git['loose'] > 0 ? $git['loose'] : '-' ?>
                </td>
                <td class="text-right font-weight-bold text-dark" style="font-size: 1.05rem;">
                  <?= number_format($git['total_quantity']) ?> <small class="text-muted font-weight-normal"><?= htmlspecialchars($git['unit'] ?: 'Packs') ?></small>
                </td>
                <td class="text-right font-weight-bold text-success">
                  Rs. <?= number_format($git['total_amount'], 2) ?>
                </td>
                <td class="text-center">
                  <div class="checkbox-box d-inline-block border rounded" style="width: 20px; height: 20px; vertical-align: middle;"></div>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
          <?php if (!empty($grouped_loading_items)): ?>
          <tfoot class="thead-light font-weight-bold">
            <tr>
              <td colspan="5" class="text-right">Grand Total:</td>
              <td class="text-right text-success"><?= number_format($total_loading_cartons) ?></td>
              <td class="text-right text-warning"><?= number_format($total_loading_loose) ?></td>
              <td class="text-right text-dark"><?= number_format($total_loading_qty) ?></td>
              <td class="text-right text-success">Rs. <?= number_format($total_loading_amount, 2) ?></td>
              <td class="text-center">-</td>
            </tr>
          </tfoot>
          <?php endif; ?>
        </table>
      </div>

      <!-- Printable Signatures Footer for Loading Sheet -->
      <div class="delivery-print-footer d-none d-print-block mt-4 pt-2">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div style="width: 30%; border-top: 1px dashed #000; text-align: center; padding-top: 4px;">
            <strong>Loader Signature</strong>
          </div>
          <div style="width: 30%; border-top: 1px dashed #000; text-align: center; padding-top: 4px;">
            <strong>Delivery Man Signature</strong>
          </div>
          <div style="width: 30%; border-top: 1px dashed #000; text-align: center; padding-top: 4px;">
            <strong>Stock Incharge Signature</strong>
          </div>
        </div>
        <div class="d-flex justify-content-between align-items-center border-top pt-1">
          <div class="footer-powered font-weight-bold">POWERED BY: BESTWAY PHARMACEUTICAL DISTRIBUTORS &middot; Wholesale Management System</div>
          <div class="footer-meta text-muted">Printed on <?=date('d-m-Y H:i')?> &middot; <?=$period_desc?></div>
        </div>
      </div>

    <?php else: ?>
      <!-- ========================================================
           VIEW 2: CUSTOMER INVOICES / DELIVERY VOUCHERS
           ======================================================== -->

      <!-- Live on-screen search filter for vouchers -->
      <?php if (!empty($sales)): ?>
      <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 p-2 bg-white rounded border d-print-none shadow-sm">
        <div class="d-flex align-items-center" style="max-width: 380px; width: 100%;">
          <div class="input-group input-group-sm">
            <div class="input-group-prepend"><span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted"></i></span></div>
            <input type="text" id="liveVoucherSearch" class="form-control border-left-0" placeholder="Type customer, product, invoice, salesman...">
            <div class="input-group-append">
              <button class="btn btn-outline-secondary" type="button" id="clearLiveSearch" title="Clear"><i class="fas fa-times"></i></button>
            </div>
          </div>
        </div>
        <div class="small text-muted mt-2 mt-sm-0">
          Showing <strong><?=count($sales)?></strong> delivery vouchers
        </div>
      </div>
      <?php endif; ?>

      <?php if (empty($sales)): ?>
        <div class="alert alert-light border text-center py-5 shadow-sm">
          <div class="text-muted mb-3"><i class="fas fa-truck-loading fa-3x"></i></div>
          <h5 class="text-dark font-weight-bold">No delivery orders found</h5>
          <p class="text-muted small mb-0">No active delivery orders matched your selected date range or filter criteria.</p>
        </div>
      <?php else: ?>

        <!-- VOUCHERS CONTAINER (EXACT SAMPLE LAYOUT FOR SCREEN + PRINT) -->
        <div id="vouchersList">
        <?php foreach ($sales as $sale): 
          $cust_code = $sale['customer_no'] ?: ('CUS-' . str_pad($sale['customer_id'] ?? 0, 4, '0', STR_PAD_LEFT));
          $cust_area_bracket = !empty($sale['customer_area']) ? ' (' . htmlspecialchars($sale['customer_area']) . ')' : '';
          $cust_line1 = htmlspecialchars($cust_code . ': ' . ($sale['customer_name'] ?: 'Walk-in Customer')) . $cust_area_bracket;
          $cust_line2 = htmlspecialchars($sale['customer_address'] ?: ($sale['customer_area'] ?: $sale['customer_city'] ?: ''));
          $cust_phone = htmlspecialchars($sale['customer_phone'] ?: '');
          $cust_cnic = htmlspecialchars($sale['customer_cnic'] ?: '');

          $deliv_raw = $sale['delivery_date'] ?: $sale['sale_date'];
          $info_delivery_date = date('d-m-Y', strtotime($deliv_raw));
          $info_order_date = date('d-m-Y', strtotime($sale['sale_date']));
          $info_voucher = htmlspecialchars($sale['invoice_no']);
          $info_salesman = htmlspecialchars($sale['salesman_name'] ?: 'Unassigned');
          $info_ob = htmlspecialchars($sale['order_booker_name'] ?: ($sale['order_booker_username'] ?: 'Office'));
          
          $item_count = count($sale['items']);
          $search_text = strtolower($cust_line1 . ' ' . $cust_line2 . ' ' . $info_voucher . ' ' . $info_salesman . ' ' . $info_ob . ' ' . $cust_phone . ' ' . ($sale['product_search_blob'] ?? ''));
        ?>

        <div class="voucher-wrapper mb-3" data-search="<?=htmlspecialchars($search_text)?>" id="voucher-sale-<?=$sale['sale_id']?>">
          
          <!-- On-Screen Action Bar for Shortcuts (Screen Only) -->
          <div class="voucher-screen-toolbar d-flex justify-content-between align-items-center px-3 py-1 bg-white border border-bottom-0 rounded-top d-print-none">
            <div class="d-flex align-items-center">
              <span class="badge badge-light border text-dark mr-2"><i class="fas fa-hashtag text-muted mr-1"></i><?=$info_voucher?></span>
              <span class="badge badge-primary-soft mr-2"><i class="fas fa-calendar-check mr-1"></i>Delivery: <?=$info_delivery_date?></span>
              <?php if (!empty($sale['customer_phone'])): ?>
              <a href="tel:<?=$cust_phone?>" class="small text-muted mr-2 text-decoration-none" title="Call Customer">
                <i class="fas fa-phone text-success mr-1"></i><?=$cust_phone?>
              </a>
              <?php endif; ?>
            </div>
            <div class="d-flex align-items-center">
              <a href="<?= BASE_URL ?>modules/sale/view_sale.php?id=<?=$sale['sale_id']?>" target="_blank" class="btn btn-xs btn-outline-primary mr-1" title="View Full Invoice">
                <i class="fas fa-eye mr-1"></i> View Invoice
              </a>
              <a href="<?= BASE_URL ?>modules/sale/print_invoice.php?id=<?=$sale['sale_id']?>" target="_blank" class="btn btn-xs btn-outline-secondary" title="Print Invoice">
                <i class="fas fa-print mr-1"></i> Print Invoice
              </a>
            </div>
          </div>

          <div class="voucher-box">
            
            <!-- HEADER SECTION (PURCHASED BY M/S + INFORMATION) -->
            <table class="voucher-head-table">
              <tr>
                <!-- LEFT: PURCHASED BY M/S -->
                <td class="vh-left" style="width: 58%;">
                  <div class="vh-title-bar">PURCHASED BY M/S</div>
                  <div class="vh-content">
                    <div class="vh-line vh-cust-name font-weight-bold"><?=$cust_line1?></div>
                    <div class="vh-line vh-address"><?=$cust_line2 !== '' ? $cust_line2 : '&nbsp;'?></div>
                    <div class="vh-line">Cell No: <span class="font-weight-bold"><?=$cust_phone !== '' ? $cust_phone : 'N/A'?></span></div>
                    <div class="vh-line">CNIC: <span class="font-weight-bold"><?=$cust_cnic !== '' ? $cust_cnic : 'N/A'?></span></div>
                  </div>
                </td>

                <!-- RIGHT: INFORMATION -->
                <td class="vh-right" style="width: 42%;">
                  <div class="vh-title-bar">INFORMATION</div>
                  <div class="vh-content">
                    <div class="vh-line">Delivery Date: <span class="font-weight-bold"><?=$info_delivery_date?></span></div>
                    <div class="vh-line">Order Date: <span class="font-weight-bold"><?=$info_order_date?></span></div>
                    <div class="vh-line">Voucher No: <span class="font-weight-bold text-primary-print"><?=$info_voucher?></span></div>
                    <div class="vh-line">Delivery Man: <span class="font-weight-bold"><?=$info_salesman?></span></div>
                    <div class="vh-line">Salesman: <span class="font-weight-bold"><?=$info_ob?></span></div>
                  </div>
                </td>
              </tr>
            </table>

            <!-- ITEMS TABLE -->
            <table class="voucher-items-table">
              <thead>
                <tr>
                  <th class="col-item text-left">Item / Product Name</th>
                  <th class="col-carton text-center" style="width: 70px;">Carton</th>
                  <th class="col-box text-center" style="width: 70px;">Box</th>
                  <th class="col-price text-right" style="width: 100px;">Rate (PKR)</th>
                  <th class="col-amount text-right" style="width: 110px;">Amount</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($sale['items'])): ?>
                <tr>
                  <td colspan="5" class="text-center text-muted py-2">No items in this voucher</td>
                </tr>
                <?php else: ?>
                  <?php foreach ($sale['items'] as $it): 
                    $prod_label = ($it['product_code'] ? htmlspecialchars($it['product_code']) . ': ' : '') . htmlspecialchars($it['product_name']);
                  ?>
                  <tr>
                    <td class="col-item font-weight-bold"><?=$prod_label?></td>
                    <td class="col-carton text-center"><?=$it['cartons']?></td>
                    <td class="col-box text-center"><?=$it['loose']?></td>
                    <td class="col-price text-right"><?=fmtNum($it['price'])?></td>
                    <td class="col-amount text-right"><?=fmtNum($it['subtotal'])?></td>
                  </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
              <tfoot>
                <tr class="voucher-total-row font-weight-bold">
                  <td class="col-item text-left"><?=$item_count?> &lt;--- T O T A L ---&gt;</td>
                  <td class="col-carton text-center"><?=$sale['total_cartons']?></td>
                  <td class="col-box text-center"><?=$sale['total_boxes']?></td>
                  <td class="col-price text-right"></td>
                  <td class="col-amount text-right"><?=fmtNum($sale['total_amount'])?></td>
                </tr>
              </tfoot>
            </table>

          </div>
        </div>

        <?php endforeach; ?>
      </div>

      <!-- Printable Footer -->
      <div class="delivery-print-footer d-none d-print-block mt-4 pt-2">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div style="width: 45%; border-top: 1px dashed #000; text-align: center; padding-top: 4px;">
            <strong>Salesman / Delivery Man Signature</strong>
          </div>
          <div style="width: 45%; border-top: 1px dashed #000; text-align: center; padding-top: 4px;">
            <strong>Customer / Receiver Signature</strong>
          </div>
        </div>
        <div class="d-flex justify-content-between align-items-center border-top pt-1">
          <div class="footer-powered font-weight-bold">POWERED BY: BESTWAY PHARMACEUTICAL DISTRIBUTORS &middot; Wholesale Management System</div>
          <div class="footer-meta text-muted">Printed on <?=date('d-m-Y H:i')?> &middot; <?=$period_desc?></div>
        </div>
      </div>

    <?php endif; ?>
    <?php endif; ?>

  </div>
</div>

<style>
/* Base Screen Styling */
.icon-circle {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.bg-primary-soft { background-color: rgba(13, 110, 253, 0.10); color: #0d6efd; }
.bg-info-soft    { background-color: rgba(13, 202, 240, 0.12); color: #0dcaf0; }
.bg-success-soft { background-color: rgba(25, 135, 84, 0.15); color: #198754; }
.bg-warning-soft { background-color: rgba(245, 158, 11, 0.15); color: #b45309; }
.bg-dark-soft    { background-color: rgba(33, 37, 41, 0.10); color: #212529; }

.border-left-primary { border-left: 4px solid #0d6efd !important; }
.border-left-info    { border-left: 4px solid #0dcaf0 !important; }
.border-left-success { border-left: 4px solid #198754 !important; }
.border-left-warning { border-left: 4px solid #f59e0b !important; }
.border-left-dark    { border-left: 4px solid #343a40 !important; }

.kpi-stat-card {
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.kpi-stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 .5rem 1rem rgba(0,0,0,.08) !important;
}

.stat-icon {
  width: 38px;
  height: 38px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
}

.btn-xs {
  padding: 0.2rem 0.5rem;
  font-size: 0.75rem;
  line-height: 1.2;
}

.voucher-screen-toolbar {
  font-size: 12px;
  background-color: #f8fafc !important;
  border-color: #cbd5e1 !important;
}

.voucher-box {
  background: #ffffff;
  border: 1.5px solid #334155;
  border-radius: 0 0 2px 2px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.voucher-head-table {
  width: 100%;
  border-collapse: collapse;
  border-bottom: 1.5px solid #334155;
}

.voucher-head-table td {
  vertical-align: top;
  padding: 0;
  border: none;
}

.voucher-head-table td.vh-left {
  border-right: 1.5px solid #334155;
}

.vh-title-bar {
  background-color: #e2e8f0;
  color: #0f172a;
  font-weight: 800;
  font-size: 11px;
  letter-spacing: 0.5px;
  text-align: center;
  padding: 3px 6px;
  border-bottom: 1px solid #cbd5e1;
  text-transform: uppercase;
}

.vh-content {
  padding: 5px 8px;
  font-size: 12px;
  line-height: 1.4;
  color: #0f172a;
}

.vh-line {
  margin-bottom: 2px;
}

.vh-cust-name {
  font-size: 13px;
  color: #000000;
}

.voucher-items-table {
  width: 100%;
  border-collapse: collapse;
}

.voucher-items-table th {
  background-color: #f1f5f9;
  color: #0f172a;
  font-size: 11.5px;
  font-weight: 700;
  padding: 4px 8px;
  border: 1px solid #94a3b8;
  vertical-align: middle;
}

.voucher-items-table td {
  font-size: 12px;
  padding: 4px 8px;
  border: 1px solid #cbd5e1;
  color: #0f172a;
  vertical-align: middle;
}

.voucher-items-table tfoot td {
  background-color: #f8fafc;
  border-top: 1.5px solid #475569;
  border-bottom: 1px solid #475569;
  font-size: 12px;
  padding: 4px 8px;
  color: #000000;
}

.voucher-total-row td {
  font-size: 12.5px !important;
}

/* Print Specific Rules matching exact sample paper */
@media print {
  @page {
    size: A4 portrait;
    margin: 8mm 6mm;
  }

  body {
    background: #ffffff !important;
    color: #000000 !important;
    font-size: 11px !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  .card {
    border: none !important;
    box-shadow: none !important;
    padding: 0 !important;
    margin: 0 !important;
  }

  .card-body {
    padding: 0 !important;
  }

  .d-print-none {
    display: none !important;
  }

  .voucher-wrapper {
    page-break-inside: avoid !important;
    break-inside: avoid !important;
    margin-bottom: 14px !important;
  }

  .voucher-box {
    border: 1.5px solid #000000 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    background: #ffffff !important;
  }

  .voucher-head-table {
    border-bottom: 1.5px solid #000000 !important;
  }

  .voucher-head-table td.vh-left {
    border-right: 1.5px solid #000000 !important;
  }

  .vh-title-bar {
    background-color: #e5e7eb !important;
    color: #000000 !important;
    border-bottom: 1px solid #000000 !important;
    font-weight: 800 !important;
    font-size: 11px !important;
    padding: 3px 6px !important;
  }

  .vh-content {
    font-size: 11.5px !important;
    line-height: 1.35 !important;
    color: #000000 !important;
    padding: 4px 6px !important;
  }

  .vh-cust-name {
    font-size: 12.5px !important;
    font-weight: 800 !important;
    color: #000000 !important;
  }

  .voucher-items-table th {
    background-color: #f3f4f6 !important;
    color: #000000 !important;
    font-size: 11px !important;
    font-weight: 800 !important;
    border: 1px solid #000000 !important;
    padding: 3px 6px !important;
  }

  .voucher-items-table td {
    font-size: 11.5px !important;
    color: #000000 !important;
    border: 1px solid #4b5563 !important;
    padding: 3px 6px !important;
  }

  .voucher-items-table tfoot td {
    background-color: #f3f4f6 !important;
    border: 1px solid #000000 !important;
    border-top: 1.5px solid #000000 !important;
    font-size: 12px !important;
    font-weight: 800 !important;
    padding: 3px 6px !important;
  }

  .text-primary-print {
    color: #000000 !important;
  }

  .delivery-print-footer {
    border-top: 1px solid #9ca3af !important;
    padding-top: 6px !important;
    font-size: 10px !important;
    color: #000000 !important;
  }

  .loading-sheet-table {
    width: 100% !important;
    border-collapse: collapse !important;
    font-size: 11px !important;
  }
  .loading-sheet-table th,
  .loading-sheet-table td {
    border: 1px solid #000000 !important;
    padding: 3px 6px !important;
    color: #000000 !important;
  }
  .loading-sheet-table th {
    background-color: #f3f4f6 !important;
    font-weight: 800 !important;
  }
  .loading-sheet-table tfoot td {
    background-color: #f3f4f6 !important;
    font-weight: 800 !important;
    border-top: 1.5px solid #000000 !important;
  }
}
</style>

<script>
$(document).ready(function(){
  // Auto-submit dropdown filters when changed
  $('.auto-submit-select').on('change', function(){
    $('#packlistFilterForm').submit();
  });

  // Live on-screen search for Loading Sheet items
  $('#liveLoadingSearch').on('keyup input', function(){
    var q = $.trim($(this).val()).toLowerCase();
    $('#loadingSheetTable tbody tr.loading-item-row').each(function(){
      var text = $(this).text().toLowerCase();
      $(this).toggle(text.indexOf(q) > -1);
    });
  });

  // Live on-screen search filter for vouchers
  function filterVouchers() {
    var q = $.trim($('#liveVoucherSearch').val()).toLowerCase();

    if (!q) {
      $('.voucher-wrapper').show();
      return;
    }

    $('.voucher-wrapper').each(function(){
      var data = $(this).attr('data-search') || '';
      if (data.indexOf(q) !== -1) {
        $(this).show();
      } else {
        $(this).hide();
      }
    });
  }

  $('#liveVoucherSearch').on('keyup input', filterVouchers);

  $('#clearLiveSearch').on('click', function(){
    $('#liveVoucherSearch').val('');
    filterVouchers();
    $('#liveVoucherSearch').focus();
  });
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
