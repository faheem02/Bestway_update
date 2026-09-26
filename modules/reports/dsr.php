<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
$page_title = 'Daily Sales Report';
$compact_page_heading = true;
$hide_topbar_title = true;
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . 'login.php'); exit;
}

$date       = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
$salesman_id = (int)($_GET['salesman_id'] ?? 0);
$area        = trim($_GET['area'] ?? '');

try {
    $all_salesmen = $pdo->query("
        SELECT id, full_name 
        FROM employees 
        WHERE employee_type = 'salesman' AND status = 1 
        ORDER BY full_name
    ")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($all_salesmen)) {
        $all_salesmen = $pdo->query("
            SELECT id, name AS full_name 
            FROM bookers 
            WHERE status = 'Active' 
            ORDER BY name
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    $areas1 = $pdo->query("SELECT name FROM areas WHERE status = 1 ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    $areas2 = $pdo->query("SELECT name FROM routes WHERE status = 'Active' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    $areas3 = $pdo->query("SELECT DISTINCT route_name FROM sales_invoices WHERE route_name IS NOT NULL AND route_name != ''")->fetchAll(PDO::FETCH_COLUMN);
    $area_options = array_values(array_unique(array_filter(array_merge($areas1 ?: [], $areas2 ?: [], $areas3 ?: []))));
    sort($area_options);

    $bank_accounts = $pdo->query("SELECT id, account_title AS account_name, bank_name FROM bank_accounts WHERE status = 'Active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $products = $pdo->query("SELECT id, name, wholesale_price AS sale_price, purchase_price, current_stock FROM products WHERE status = 'Active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $customers = $pdo->query("SELECT id, name AS full_name, customer_code AS customer_no, current_balance, area FROM customers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $all_salesmen = []; $area_options = []; $bank_accounts = []; $products = []; $customers = [];
}

// ===== POST HANDLER: Add Sale =====
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $customer_id    = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        $salesman_post  = !empty($_POST['salesman_id']) ? (int)$_POST['salesman_id'] : null;
        $product_id     = !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null;
        $qty            = (float)($_POST['quantity'] ?? 0);
        $rate           = (float)($_POST['rate'] ?? 0);
        $sale_date      = !empty($_POST['sale_date']) ? $_POST['sale_date'] : $date;
        $payment_method = $_POST['payment_method'] ?: 'credit';
        $bank_id        = $payment_method == 'bank' ? ($_POST['bank_account_id'] ?: null) : null;
        $notes          = trim($_POST['notes'] ?? '');
        $paid_amount    = $payment_method == 'credit' ? 0 : (float)($_POST['paid_amount'] ?? 0);
        $back           = 'dsr.php?date=' . urlencode($sale_date);

        if (!$customer_id) { redirect($back, 'Select a customer.', 'error'); }
        if (!$product_id)  { redirect($back, 'Select a product.', 'error'); }
        if ($qty <= 0)     { redirect($back, 'Quantity must be greater than 0.', 'error'); }
        if ($rate <= 0)    { redirect($back, 'Rate must be greater than 0.', 'error'); }

        $cust = getById('customers', $customer_id);
        $cust_name = $cust ? $cust['name'] : 'Customer';
        $route_name = $cust ? ($cust['area'] ?? '') : '';
        
        $booker_name = '';
        if ($salesman_post) {
            $emp = getById('employees', $salesman_post);
            if ($emp) {
                $booker_name = $emp['full_name'];
            } else {
                $bk = getById('bookers', $salesman_post);
                if ($bk) $booker_name = $bk['name'];
            }
        }

        // Stock check
        $prod_row = null;
        try {
            $stmt_p = $pdo->prepare("SELECT name, current_stock, purchase_price, wholesale_price FROM products WHERE id = ?");
            $stmt_p->execute([$product_id]);
            $prod_row = $stmt_p->fetch(PDO::FETCH_ASSOC);
            if ($prod_row && $qty > (float)$prod_row['current_stock']) {
                redirect($back, 'Insufficient stock: ' . $prod_row['name'] . ' (only ' . (int)$prod_row['current_stock'] . ' in stock).', 'error');
            }
        } catch (Exception $e) {}

        $subtotal   = $qty * $rate;
        if ($paid_amount > $subtotal) $paid_amount = $subtotal;
        $due_amount = $subtotal - $paid_amount;

        // Generate invoice number
        $inv_res = $pdo->query("SELECT invoice_no FROM sales_invoices WHERE invoice_no REGEXP '^INV-[0-9]+$' ORDER BY id DESC LIMIT 1");
        $inv_num = 1;
        if ($inv_res && $irow = $inv_res->fetch(PDO::FETCH_ASSOC)) {
            if (preg_match('/INV-(\d+)/i', $irow['invoice_no'], $im)) {
                $inv_num = (int)$im[1] + 1;
            }
        }
        $invoice_no = 'INV-' . str_pad($inv_num, 4, '0', STR_PAD_LEFT);

        $pdo->beginTransaction();
        try {
            $stmt_ins = $pdo->prepare("
                INSERT INTO sales_invoices (
                    invoice_no, customer_name, customer_id, invoice_date, payment_terms, payment_method,
                    bank_account_id, subtotal, grand_total, net_payable, paid_amount, balance_due,
                    booker_id, booker_name, route_name, notes, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $payment_terms = $due_amount > 0 ? 'Credit' : 'Cash';
            $stmt_ins->execute([
                $invoice_no, $cust_name, $customer_id, $sale_date, $payment_terms, ucfirst($payment_method),
                $bank_id, $subtotal, $subtotal, $subtotal, $paid_amount, $due_amount,
                $salesman_post, $booker_name, $route_name, $notes, $_SESSION['user_id'] ?? 1
            ]);
            $invoice_id = $pdo->lastInsertId();

            $stmt_item = $pdo->prepare("
                INSERT INTO sale_items (
                    invoice_id, sale_id, product_id, item_name, quantity, unit_price, sale_price, total_amount, total_price
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_item->execute([
                $invoice_id, 0, $product_id, $prod_row ? $prod_row['name'] : 'Item', $qty, $rate, $rate, $subtotal, $subtotal
            ]);

            $pdo->prepare("UPDATE products SET current_stock = current_stock - ? WHERE id = ?")->execute([$qty, $product_id]);

            if ($paid_amount > 0) {
                $desc = 'Sale #' . $invoice_no;
                if ($payment_method == 'bank' && $bank_id) {
                    recordBankInflow($pdo, $sale_date, $paid_amount, $desc, 'sale', $invoice_id, $_SESSION['user_id'] ?? 1, $bank_id);
                } else {
                    recordCashInflow($pdo, $sale_date, $paid_amount, $desc, 'sale', $invoice_id, $_SESSION['user_id'] ?? 1);
                }
            }
            updateCustomerBalance($pdo, $customer_id);
            $pdo->commit();
            redirect($back, 'Sale saved: ' . $invoice_no . '. Stock updated.');
        } catch (Exception $e) {
            $pdo->rollBack();
            redirect($back, 'Error: ' . $e->getMessage(), 'error');
        }
    }

    if ($action === 'delete') {
        $sid  = (int)($_POST['id'] ?? 0);
        $sale = getById('sales_invoices', $sid);
        $is_inv = true;
        if (!$sale) {
            $sale = getById('sales', $sid);
            $is_inv = false;
        }
        if (!$sale) { redirect('dsr.php', 'Sale not found.', 'error'); }
        $sale_date_val = $sale['invoice_date'] ?? $sale['sale_date'] ?? $date;
        $back = 'dsr.php?date=' . urlencode($sale_date_val);

        $pdo->beginTransaction();
        try {
            // Restore stock
            $items = $pdo->prepare("SELECT product_id, quantity FROM sale_items WHERE invoice_id = ? OR (invoice_id = 0 AND sale_id = ?)");
            $items->execute([$sid, $sid]);
            foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $it) {
                if (!empty($it['product_id']) && !empty($it['quantity'])) {
                    $pdo->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = ?")->execute([$it['quantity'], $it['product_id']]);
                }
            }
            // Remove cash/bank entries
            $pdo->prepare("DELETE FROM cash_book WHERE reference_type='sale' AND reference_id=?")->execute([$sid]);
            try {
                $bts = $pdo->prepare("SELECT amount, bank_account_id FROM bank_transactions WHERE reference_type='sale' AND reference_id=?");
                $bts->execute([$sid]);
                foreach ($bts->fetchAll(PDO::FETCH_ASSOC) as $bt) {
                    $pdo->prepare("UPDATE bank_accounts SET current_balance = current_balance - ? WHERE id=?")->execute([$bt['amount'], $bt['bank_account_id']]);
                }
                $pdo->prepare("DELETE FROM bank_transactions WHERE reference_type='sale' AND reference_id=?");
                $pdo->execute([$sid]);
            } catch (Exception $e) {}

            try {
                $pdo->prepare("DELETE FROM udhaar_book WHERE invoice_id = ?")->execute([$sid]);
            } catch (Exception $e) {}

            $pdo->prepare("DELETE FROM sale_items WHERE invoice_id = ? OR sale_id = ?")->execute([$sid, $sid]);
            if ($is_inv) {
                $pdo->prepare("DELETE FROM sales_invoices WHERE id = ?")->execute([$sid]);
            } else {
                $pdo->prepare("DELETE FROM sales WHERE id = ?")->execute([$sid]);
            }
            if (!empty($sale['customer_id'])) {
                updateCustomerBalance($pdo, $sale['customer_id']);
            }
            $pdo->commit();
            redirect($back, 'Sale deleted.');
        } catch (Exception $e) {
            $pdo->rollBack();
            redirect($back, 'Error: ' . $e->getMessage(), 'error');
        }
    }

    redirect('dsr.php?date=' . urlencode($date));
}

// ===== READ DAY DATA =====
$sql = "SELECT s.id, s.invoice_no, s.invoice_date AS sale_date, s.grand_total, s.paid_amount,
               GREATEST(0, (s.grand_total - s.paid_amount)) AS due_amount,
               (CASE 
                   WHEN s.paid_amount >= s.grand_total THEN 'Paid'
                   WHEN s.paid_amount > 0 THEN 'Partial'
                   ELSE 'Credit'
               END) AS status,
               s.notes, s.booker_id AS salesman_id,
               COALESCE(s.customer_name, c.name, 'Walk-in') AS customer_name,
               c.phone AS customer_phone,
               COALESCE(s.route_name, c.area, '') AS customer_area,
               COALESCE(NULLIF(s.booker_name, ''), e.full_name, 'Direct') AS salesman_name,
               COALESCE(si.quantity, 1) AS quantity,
               COALESCE(si.unit_price, si.sale_price, 0) AS unit_price,
               COALESCE(NULLIF(si.total_amount, 0), NULLIF(si.total_price, 0), (si.quantity * si.unit_price), s.grand_total) AS subtotal,
               p.id AS product_id,
               COALESCE(NULLIF(si.item_name, ''), p.name, 'Item') AS product_name,
               COALESCE(p.purchase_price, 0) AS purchase_price
        FROM sales_invoices s
        LEFT JOIN sale_items si ON (si.invoice_id = s.id OR (si.invoice_id = 0 AND si.sale_id = s.id))
        LEFT JOIN customers c ON c.id = s.customer_id
        LEFT JOIN employees e ON e.id = s.booker_id
        LEFT JOIN products p ON p.id = si.product_id
        WHERE DATE(s.invoice_date) = ?";

$params = [$date];
if ($salesman_id > 0) {
    $sql .= " AND (s.booker_id = ? OR e.id = ?)";
    $params[] = $salesman_id;
    $params[] = $salesman_id;
}
if ($area !== '') {
    $sql .= " AND (LOWER(s.route_name) = LOWER(?) OR LOWER(c.area) = LOWER(?))";
    $params[] = $area;
    $params[] = $area;
}
$sql .= " ORDER BY s.id ASC, si.id ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $rows = [];
}

// Group by sale
$groups = [];
foreach ($rows as $r) {
    $sid = (int)$r['id'];
    if (!isset($groups[$sid])) {
        $groups[$sid] = [
            'id'            => $sid,
            'invoice_no'    => $r['invoice_no'],
            'sale_date'     => $r['sale_date'],
            'customer_name' => $r['customer_name'] ?: 'Walk-in',
            'customer_phone'=> $r['customer_phone'],
            'customer_area' => $r['customer_area'],
            'salesman_name' => $r['salesman_name'] ?: 'Direct',
            'grand_total'   => (float)$r['grand_total'],
            'paid_amount'   => (float)$r['paid_amount'],
            'due_amount'    => (float)$r['due_amount'],
            'status'        => $r['status'],
            'notes'         => $r['notes'],
            'items'         => [],
            'total_cost'    => 0,
            'profit'        => 0,
        ];
    }
    if (!empty($r['product_name'])) {
        $cost = (float)$r['purchase_price'] * (float)$r['quantity'];
        $groups[$sid]['items'][] = $r;
        $groups[$sid]['total_cost'] += $cost;
        $groups[$sid]['profit'] += (float)$r['subtotal'] - $cost;
    }
}

$day_total  = array_sum(array_column($groups, 'grand_total'));
$day_paid   = array_sum(array_column($groups, 'paid_amount'));
$day_due    = array_sum(array_column($groups, 'due_amount'));
$day_profit = array_sum(array_column($groups, 'profit'));
$inv_count  = count($groups);

$next_invoice_no = 'INV-0001';
try {
    $inv_res = $pdo->query("SELECT invoice_no FROM sales_invoices WHERE invoice_no REGEXP '^INV-[0-9]+$' ORDER BY id DESC LIMIT 1");
    if ($inv_res && $irow = $inv_res->fetch(PDO::FETCH_ASSOC)) {
        if (preg_match('/INV-(\d+)/i', $irow['invoice_no'], $im)) {
            $next_invoice_no = 'INV-' . str_pad((int)$im[1] + 1, 4, '0', STR_PAD_LEFT);
        }
    }
} catch (Exception $e) {}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="card shadow mb-3">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center d-print-none py-3">
    <h5 class="mb-0 font-weight-bold">
      <i class="fas fa-calendar-check text-primary mr-2"></i> Daily Sales Report (DSR)
      <small class="text-muted ml-1">&middot; <?= formatDate($date) ?></small>
    </h5>
    <div class="d-flex flex-wrap align-items-center mt-2 mt-sm-0">
      <button type="button" class="btn btn-sm btn-success mr-2 shadow-sm" data-toggle="modal" data-target="#addModal">
        <i class="fas fa-plus mr-1"></i> Add Entry
      </button>
      <button type="button" class="btn btn-sm btn-primary shadow-sm mr-2" onclick="window.print()">
        <i class="fas fa-print mr-1"></i> Print DSR
      </button>
      <a href="<?= BASE_URL ?>modules/sale/sales.php" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-file-invoice mr-1"></i> All Invoices
      </a>
    </div>
  </div>
  <div class="card-body pt-3">

    <!-- Filter Toolbar -->
    <div class="card bg-light border p-3 rounded mb-3 d-print-none shadow-sm">
      <form method="get" action="dsr.php" class="form-inline flex-wrap" style="gap:8px;">
        <div class="input-group input-group-sm mr-2 my-1">
          <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user-tie text-primary"></i></span></div>
          <select name="salesman_id" class="form-control" onchange="this.form.submit()">
            <option value="0">All Salesmen</option>
            <?php foreach ($all_salesmen as $sm): ?>
            <option value="<?= $sm['id'] ?>" <?= $salesman_id==$sm['id']?'selected':'' ?>><?= htmlspecialchars($sm['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="input-group input-group-sm mr-2 my-1">
          <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-map-marker-alt text-danger"></i></span></div>
          <select name="area" class="form-control">
            <option value="">All Areas</option>
            <?php foreach ($area_options as $ar): ?>
            <option value="<?= htmlspecialchars($ar) ?>" <?= $area===$ar?'selected':'' ?>><?= htmlspecialchars($ar) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="input-group input-group-sm mr-2 my-1">
          <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-calendar-day text-secondary"></i></span></div>
          <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date) ?>">
        </div>
        <button type="submit" class="btn btn-sm btn-primary my-1 mr-1 px-3"><i class="fas fa-filter mr-1"></i> Filter</button>
        <?php if ($salesman_id || $date !== date('Y-m-d') || $area): ?>
        <a href="dsr.php" class="btn btn-sm btn-outline-secondary my-1" title="Reset"><i class="fas fa-undo"></i></a>
        <?php endif; ?>

        <!-- Date navigation -->
        <div class="ml-auto my-1 d-flex align-items-center" style="gap:4px;">
          <a href="dsr.php?date=<?= date('Y-m-d', strtotime($date . ' -1 day')) ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-chevron-left"></i></a>
          <a href="dsr.php?date=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline-primary px-3">Today</a>
          <a href="dsr.php?date=<?= date('Y-m-d', strtotime($date . ' +1 day')) ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-chevron-right"></i></a>
        </div>
      </form>
    </div>

    <!-- Printable Header -->
    <div class="d-none d-print-block mb-3 text-center">
      <h4 class="font-weight-bold mb-0" style="color:#0f172a;">Bestway Distribution</h4>
      <h5 class="font-weight-bold text-primary mt-1 mb-0">DAILY SALES REPORT (DSR)</h5>
      <div class="font-weight-bold mt-1">Date: <?= formatDate($date) ?></div>
      <small>Printed on <?= formatDate(date('Y-m-d')) ?> &middot; <?= date('h:i A') ?></small>
    </div>

    <!-- Summary Stats -->
    <div class="row mb-3">
      <div class="col-6 col-md-3 mb-2">
        <div class="card border-left-primary shadow stat-card"><div class="card-body py-2">
          <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Invoices</div>
          <div class="h5 mb-0 font-weight-bold"><?= $inv_count ?></div>
        </div></div>
      </div>
      <div class="col-6 col-md-3 mb-2">
        <div class="card border-left-success shadow stat-card"><div class="card-body py-2">
          <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Day Total</div>
          <div class="h5 mb-0 font-weight-bold">PKR <?= formatCurrency($day_total) ?></div>
        </div></div>
      </div>
      <div class="col-6 col-md-3 mb-2">
        <div class="card border-left-info shadow stat-card"><div class="card-body py-2">
          <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Cash Received</div>
          <div class="h5 mb-0 font-weight-bold">PKR <?= formatCurrency($day_paid) ?></div>
        </div></div>
      </div>
      <div class="col-6 col-md-3 mb-2">
        <div class="card border-left-warning shadow stat-card"><div class="card-body py-2">
          <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Credit / Due</div>
          <div class="h5 mb-0 font-weight-bold">PKR <?= formatCurrency($day_due) ?></div>
        </div></div>
      </div>
    </div>

    <!-- DSR Table -->
    <?php if (empty($groups)): ?>
    <div class="text-center py-5 text-muted">
      <i class="fas fa-calendar-times fa-3x mb-3 d-block"></i>
      <h5>No sales on <?= formatDate($date) ?></h5>
      <p class="small">Click <strong>Add Entry</strong> to record a sale for this date.</p>
    </div>
    <?php else: ?>

    <div class="table-responsive">
      <table class="table table-bordered table-hover mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>Invoice</th>
            <th>Customer</th>
            <th>Area</th>
            <th>Salesman</th>
            <th>Items</th>
            <th class="text-right">Total</th>
            <th class="text-right">Paid</th>
            <th class="text-right">Due</th>
            <th class="text-center">Status</th>
            <th class="text-center d-print-none">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php $i=1; foreach ($groups as $g): ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><span class="badge badge-primary"><?= htmlspecialchars($g['invoice_no']) ?></span></td>
            <td>
              <strong><?= htmlspecialchars($g['customer_name']) ?></strong>
              <?php if ($g['customer_phone']): ?><br><small class="text-muted"><?= htmlspecialchars($g['customer_phone']) ?></small><?php endif; ?>
            </td>
            <td><?= htmlspecialchars($g['customer_area'] ?? '-') ?></td>
            <td><?= htmlspecialchars($g['salesman_name']) ?></td>
            <td>
              <?php foreach ($g['items'] as $it): ?>
              <div class="small"><?= htmlspecialchars($it['product_name']) ?> &times; <?= $it['quantity'] ?> @ PKR <?= formatCurrency($it['unit_price']) ?></div>
              <?php endforeach; ?>
            </td>
            <td class="text-right font-weight-bold">PKR <?= formatCurrency($g['grand_total']) ?></td>
            <td class="text-right text-success">PKR <?= formatCurrency($g['paid_amount']) ?></td>
            <td class="text-right <?= $g['due_amount']>0?'text-danger':'text-muted' ?>">PKR <?= formatCurrency($g['due_amount']) ?></td>
            <td class="text-center">
              <?= $g['due_amount'] > 0
                ? '<span class="badge badge-warning">Credit</span>'
                : '<span class="badge badge-success">Paid</span>' ?>
            </td>
            <td class="text-center d-print-none" nowrap>
              <a href="<?= BASE_URL ?>modules/sale/print_invoice.php?id=<?= $g['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print Invoice"><i class="fas fa-print"></i></a>
              <form method="post" class="d-inline" onsubmit="return confirm('Delete invoice <?= htmlspecialchars($g['invoice_no']) ?>?');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $g['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light font-weight-bold">
          <tr>
            <td colspan="6" class="text-right">Day Total (<?= $inv_count ?> invoices):</td>
            <td class="text-right">PKR <?= formatCurrency($day_total) ?></td>
            <td class="text-right text-success">PKR <?= formatCurrency($day_paid) ?></td>
            <td class="text-right text-danger">PKR <?= formatCurrency($day_due) ?></td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Add Entry Modal -->
<div class="modal fade" id="addModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="post">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="invoice_no" value="<?= htmlspecialchars($next_invoice_no) ?>">
      <div class="modal-header">
        <h5 class="modal-title font-weight-bold"><i class="fas fa-plus-circle text-success mr-1"></i> Add Sale Entry</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label font-weight-bold">Date *</label>
            <input type="date" name="sale_date" class="form-control datepicker" value="<?= $date ?>" required>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label font-weight-bold">Invoice No (Auto)</label>
            <input type="text" class="form-control bg-light text-success font-weight-bold" value="<?= htmlspecialchars($next_invoice_no) ?>" readonly>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label font-weight-bold">Customer *</label>
            <select name="customer_id" class="form-control" required>
              <option value="">— Select Customer —</option>
              <?php foreach ($customers as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['full_name']) ?> (<?= htmlspecialchars($c['customer_no']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label font-weight-bold">Salesman</label>
            <select name="salesman_id" class="form-control">
              <option value="">— None / Direct —</option>
              <?php foreach ($all_salesmen as $sm): ?>
              <option value="<?= $sm['id'] ?>"><?= htmlspecialchars($sm['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label font-weight-bold">Product *</label>
            <select name="product_id" class="form-control" required id="productSelect">
              <option value="">— Select Product —</option>
              <?php foreach ($products as $pr): ?>
              <option value="<?= $pr['id'] ?>" data-price="<?= $pr['sale_price'] ?>"><?= htmlspecialchars($pr['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label font-weight-bold">Quantity *</label>
            <input type="number" name="quantity" id="qty" step="0.01" min="0.01" class="form-control" required placeholder="0">
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label font-weight-bold">Rate (PKR) *</label>
            <input type="number" name="rate" id="rate" step="0.01" min="0.01" class="form-control" required placeholder="0.00">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label font-weight-bold">Payment Method</label>
            <select name="payment_method" id="dsrPayMethod" class="form-control">
              <option value="credit">Credit</option>
              <option value="cash">Cash</option>
              <option value="bank">Bank</option>
            </select>
          </div>
          <div class="col-md-6 mb-3 d-none" id="dsrPaidDiv">
            <label class="form-label font-weight-bold">Paid Amount</label>
            <input type="number" name="paid_amount" step="0.01" min="0" class="form-control" placeholder="0.00">
          </div>
          <div class="col-md-12 mb-3 d-none" id="dsrBankDiv">
            <label class="form-label font-weight-bold">Bank Account</label>
            <select name="bank_account_id" class="form-control">
              <?php foreach ($bank_accounts as $ba): ?>
              <option value="<?= $ba['id'] ?>"><?= htmlspecialchars($ba['account_name']) ?> — <?= htmlspecialchars($ba['bank_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-12 mb-3">
            <label class="form-label font-weight-bold">Notes</label>
            <input type="text" name="notes" class="form-control" placeholder="Optional">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-success px-4"><i class="fas fa-save mr-1"></i> Save Sale</button>
      </div>
    </form>
  </div></div>
</div>

<script>
$(document).ready(function(){
  // Auto-fill rate from product selection
  $('#productSelect').change(function(){
    var price = $(this).find('option:selected').data('price') || '';
    $('#rate').val(price);
  });

  // Payment method toggle
  $('#dsrPayMethod').change(function(){
    var v = this.value;
    $('#dsrPaidDiv').toggleClass('d-none', v === 'credit');
    $('#dsrBankDiv').toggleClass('d-none', v !== 'bank');
  });
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
