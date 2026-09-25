<?php
$page_title = "Wholesale Executive Dashboard";
require_once __DIR__ . '/includes/header.php';

// Initialize variables (Clean live data foundation)
$today = date('Y-m-d');
$today_sales = 0.00;
$today_cash_sales = 0.00;
$today_credit_sales = 0.00;
$today_purchases = 0.00;
$today_cash_purchases = 0.00;
$today_credit_purchases = 0.00;
$today_expenses = 0.00;
$today_cash_expenses = 0.00;
$today_bank_expenses = 0.00;
$total_receivables = 0.00;
$today_recovered = 0.00;
$total_payables = 0.00;
$cash_in_hand = 0.00;
$total_products_count = 0;
$low_stock_count = 0;
$low_stock_products = [];
$near_expiry_count = 0;
$near_expiry_batches = [];
$recent_sales = [];
$top_routes = [];

// Prepare 7-day chart data
$sales_chart_labels = [];
$sales_chart_data = [];
$recovery_chart_data = [];

for ($i = 6; $i >= 0; $i--) {
    $date_ts = strtotime("-{$i} days");
    $day_sql = date('Y-m-d', $date_ts);
    $sales_chart_labels[] = ($i === 0) ? 'Today' : date('D, d M', $date_ts);
    $sales_chart_data[$day_sql] = 0.00;
    $recovery_chart_data[$day_sql] = 0.00;
}

// Fetch live database figures if connected
if (!empty($pdo) && $db_connected) {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $bkid = currentBookerId($pdo);

    // 1. Today Sales
    try {
        if (isAdmin()) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(grand_total), 0) as total, 
                                          COALESCE(SUM(paid_amount), 0) as cash_total,
                                          COALESCE(SUM(CASE WHEN paid_amount < grand_total THEN grand_total - paid_amount ELSE 0 END), 0) as credit_total
                                   FROM sales_invoices WHERE DATE(invoice_date) = :today");
            $stmt->execute(['today' => $today]);
        } else {
            $bk_sql = $bkid ? " OR booker_id = :bkid" : "";
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(grand_total), 0) as total, 
                                          COALESCE(SUM(paid_amount), 0) as cash_total,
                                          COALESCE(SUM(CASE WHEN paid_amount < grand_total THEN grand_total - paid_amount ELSE 0 END), 0) as credit_total
                                   FROM sales_invoices 
                                   WHERE DATE(invoice_date) = :today AND (created_by = :uid {$bk_sql})");
            $params = ['today' => $today, 'uid' => $uid];
            if ($bkid) $params['bkid'] = $bkid;
            $stmt->execute($params);
        }
        if ($res = $stmt->fetch()) {
            $today_sales = (float)$res['total'];
            $today_cash_sales = (float)$res['cash_total'];
            $today_credit_sales = (float)$res['credit_total'];
        }
    } catch (Exception $e) {}

    // Admin-only financial statistics
    if (isAdmin()) {
        // 2. Market Receivables (Customer Balances)
        try {
            $stmt = $pdo->query("SELECT COALESCE(SUM(current_balance), 0) as total FROM customers");
            if ($res = $stmt->fetch()) {
                $total_receivables = (float)$res['total'];
            }
        } catch (Exception $e) {}

        // Today Recovered
        try {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM customer_payments WHERE DATE(payment_date) = :today");
            $stmt->execute(['today' => $today]);
            if ($res = $stmt->fetch()) {
                $today_recovered = (float)$res['total'];
            }
        } catch (Exception $e) {}

        // 3. Today Purchases
        try {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(grand_total), 0) as total,
                                          COALESCE(SUM(CASE WHEN payment_type = 'Cash' THEN grand_total ELSE 0 END), 0) as cash_total,
                                          COALESCE(SUM(CASE WHEN payment_type != 'Cash' THEN grand_total ELSE 0 END), 0) as credit_total
                                   FROM purchases WHERE DATE(purchase_date) = :today");
            $stmt->execute(['today' => $today]);
            if ($res = $stmt->fetch()) {
                $today_purchases = (float)$res['total'];
                $today_cash_purchases = (float)$res['cash_total'];
                $today_credit_purchases = (float)$res['credit_total'];
            }
        } catch (Exception $e) {}

        // 4. Today Expenses
        try {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total,
                                          COALESCE(SUM(CASE WHEN payment_method = 'Cash' THEN amount ELSE 0 END), 0) as cash_total,
                                          COALESCE(SUM(CASE WHEN payment_method != 'Cash' THEN amount ELSE 0 END), 0) as bank_total
                                   FROM expenses WHERE DATE(expense_date) = :today");
            $stmt->execute(['today' => $today]);
            if ($res = $stmt->fetch()) {
                $today_expenses = (float)$res['total'];
                $today_cash_expenses = (float)$res['cash_total'];
                $today_bank_expenses = (float)$res['bank_total'];
            }
        } catch (Exception $e) {}

        // 5. Supplier Payables
        try {
            $stmt = $pdo->query("SELECT COALESCE(SUM(current_balance), 0) as total FROM suppliers");
            if ($res = $stmt->fetch()) {
                $total_payables = (float)$res['total'];
            }
        } catch (Exception $e) {}

        // 6. Cash in Hand
        try {
            $stmt = $pdo->query("SELECT balance FROM cash_accounts WHERE is_default = 1 LIMIT 1");
            if ($res = $stmt->fetch()) {
                $cash_in_hand = (float)$res['balance'];
            } else {
                $stmt2 = $pdo->query("SELECT COALESCE(SUM(balance), 0) as total FROM cash_accounts");
                if ($res2 = $stmt2->fetch()) {
                    $cash_in_hand = (float)$res2['total'];
                }
            }
        } catch (Exception $e) {}
    }

    // 7. Total Active Products Count
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE status = 'Active'");
        if ($res = $stmt->fetch()) {
            $total_products_count = (int)$res['total'];
        }
    } catch (Exception $e) {}

    // 8. Low Stock Products (Below Reorder Level)
    try {
        $stmt = $pdo->query("SELECT p.*, c.name as company_name, cat.name as category_name 
                             FROM products p 
                             LEFT JOIN companies c ON p.company_id = c.id 
                             LEFT JOIN categories cat ON p.category_id = cat.id 
                             WHERE p.current_stock <= p.reorder_level AND p.status = 'Active'
                             ORDER BY p.current_stock ASC LIMIT 6");
        $low_stock_products = $stmt->fetchAll();
        $low_stock_count = count($low_stock_products);
    } catch (Exception $e) {}

    // 9. Near Expiry Batches (< 90 Days)
    try {
        $limit_date = date('Y-m-d', strtotime('+90 days'));
        $stmt = $pdo->prepare("SELECT b.*, p.name as product_name, c.name as company_name 
                               FROM product_batches b 
                               JOIN products p ON b.product_id = p.id 
                               LEFT JOIN companies c ON p.company_id = c.id 
                               WHERE b.expiry_date <= :limit_date AND b.expiry_date >= :today AND b.current_stock > 0 
                               ORDER BY b.expiry_date ASC LIMIT 5");
        $stmt->execute(['limit_date' => $limit_date, 'today' => $today]);
        $near_expiry_batches = $stmt->fetchAll();
        $near_expiry_count = count($near_expiry_batches);
    } catch (Exception $e) {}

    // 10. Recent Sales Invoices
    try {
        if (isAdmin()) {
            $stmt = $pdo->prepare("SELECT s.*, COALESCE(s.customer_name, c.name, 'Walk-in') as customer_name, c.shop_name, COALESCE(s.route_name, r.name, '') as route_name 
                                   FROM sales_invoices s 
                                   LEFT JOIN customers c ON s.customer_id = c.id 
                                   LEFT JOIN routes r ON s.route_id = r.id 
                                   ORDER BY s.id DESC LIMIT 6");
            $stmt->execute();
        } else {
            $bk_sql = $bkid ? " OR s.booker_id = :bkid" : "";
            $stmt = $pdo->prepare("SELECT s.*, COALESCE(s.customer_name, c.name, 'Walk-in') as customer_name, c.shop_name, COALESCE(s.route_name, r.name, '') as route_name 
                                   FROM sales_invoices s 
                                   LEFT JOIN customers c ON s.customer_id = c.id 
                                   LEFT JOIN routes r ON s.route_id = r.id 
                                   WHERE (s.created_by = :uid {$bk_sql})
                                   ORDER BY s.id DESC LIMIT 6");
            $params = ['uid' => $uid];
            if ($bkid) $params['bkid'] = $bkid;
            $stmt->execute($params);
        }
        $recent_sales = $stmt->fetchAll();
    } catch (Exception $e) {}

    // 11. 7-Day Sales & Recovery Data for Chart (Admin only)
    if (isAdmin()) {
        try {
            $seven_days_ago = date('Y-m-d', strtotime('-6 days'));
            $stmt = $pdo->prepare("SELECT DATE(invoice_date) as sdate, COALESCE(SUM(grand_total), 0) as total 
                                   FROM sales_invoices 
                                   WHERE DATE(invoice_date) >= :start_date 
                                   GROUP BY DATE(invoice_date)");
            $stmt->execute(['start_date' => $seven_days_ago]);
            while ($row = $stmt->fetch()) {
                if (isset($sales_chart_data[$row['sdate']])) {
                    $sales_chart_data[$row['sdate']] = (float)$row['total'];
                }
            }

            $stmt2 = $pdo->prepare("SELECT DATE(payment_date) as pdate, COALESCE(SUM(amount), 0) as total 
                                    FROM customer_payments 
                                    WHERE DATE(payment_date) >= :start_date 
                                    GROUP BY DATE(payment_date)");
            $stmt2->execute(['start_date' => $seven_days_ago]);
            while ($row2 = $stmt2->fetch()) {
                if (isset($recovery_chart_data[$row2['pdate']])) {
                    $recovery_chart_data[$row2['pdate']] = (float)$row2['total'];
                }
            }
        } catch (Exception $e) {}

        // 12. Route-wise Sales Share
        try {
            $stmt = $pdo->query("SELECT r.name as route_name, COALESCE(SUM(s.grand_total), 0) as total_sales 
                                 FROM sales_invoices s 
                                 JOIN routes r ON s.route_id = r.id 
                                 GROUP BY r.id, r.name 
                                 ORDER BY total_sales DESC LIMIT 5");
            $top_routes = $stmt->fetchAll();
        } catch (Exception $e) {}
    }
}

$chart_sales_values = array_values($sales_chart_data);
$chart_recovery_values = array_values($recovery_chart_data);

?>

      <!-- Greeting Bar -->
      <div class="greet-bar mb-4 d-flex align-items-center justify-content-between flex-wrap">
        <div>
          <h4 class="mb-1">Welcome back, <?= htmlspecialchars($_SESSION['user_fullname'] ?? 'Admin') ?>!</h4>
          <p class="mb-0 small" style="opacity:0.75;"><?= date('l, d F Y') ?> &mdash; Bestway Distribution Dashboard</p>
        </div>
        <div class="mt-2 mt-md-0">
          <a href="<?= BASE_URL ?>modules/sale/new_sale.php" class="btn btn-light btn-sm mr-2">
            <i class="fas fa-plus-circle mr-1"></i> New Sale
          </a>
          <?php if (isAdmin()): ?>
          <a href="<?= BASE_URL ?>modules/purchase/add_purchase.php" class="btn btn-light btn-sm">
            <i class="fas fa-cart-arrow-down mr-1"></i> Add Purchase
          </a>
          <?php else: ?>
          <a href="<?= BASE_URL ?>modules/sale/sales.php" class="btn btn-light btn-sm">
            <i class="fas fa-file-invoice mr-1"></i> Invoices
          </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- KPI Stats Row -->
      <div class="row mb-4">
        <!-- Today Sales (Visible to All, scoped) -->
        <div class="<?= isAdmin() ? 'col-xl-3 col-md-6' : 'col-xl-4 col-md-6' ?> mb-3">
          <div class="card border-left-primary stat-card h-100">
            <div class="card-body py-3">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <div class="stat-label">Today Sales</div>
                  <div class="stat-value text-primary"><?= format_currency($today_sales) ?></div>
                  <div class="stat-sub">Cash: <?= format_currency($today_cash_sales) ?> &bull; Credit: <?= format_currency($today_credit_sales) ?></div>
                </div>
                <div class="icon-circle icon-emerald">
                  <i class="fas fa-receipt"></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <?php if (isAdmin()): ?>
        <!-- Today Purchase (Admin only) -->
        <div class="col-xl-3 col-md-6 mb-3">
          <div class="card border-left-warning stat-card h-100">
            <div class="card-body py-3">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <div class="stat-label">Today Purchase</div>
                  <div class="stat-value text-warning"><?= format_currency($today_purchases) ?></div>
                  <div class="stat-sub">Cash: <?= format_currency($today_cash_purchases) ?> &bull; Credit: <?= format_currency($today_credit_purchases) ?></div>
                </div>
                <div class="icon-circle icon-amber">
                  <i class="fas fa-cart-arrow-down"></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Total Receivables (Admin only) -->
        <div class="col-xl-3 col-md-6 mb-3">
          <div class="card border-left-info stat-card h-100">
            <div class="card-body py-3">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <div class="stat-label">Total Receivables</div>
                  <div class="stat-value text-info"><?= format_currency($total_receivables) ?></div>
                  <div class="stat-sub">Recovered Today: <?= format_currency($today_recovered) ?></div>
                </div>
                <div class="icon-circle icon-info">
                  <i class="fas fa-hand-holding-usd"></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Cash in Hand (Admin only) -->
        <div class="col-xl-3 col-md-6 mb-3">
          <div class="card border-left-success stat-card h-100">
            <div class="card-body py-3">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <div class="stat-label">Cash in Hand</div>
                  <div class="stat-value text-success"><?= format_currency($cash_in_hand) ?></div>
                  <div class="stat-sub">Payables: <?= format_currency($total_payables) ?></div>
                </div>
                <div class="icon-circle icon-cash">
                  <i class="fas fa-money-bill-alt"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php else: ?>
        <!-- Salesman View: Total Products & Low Stock Items -->
        <div class="col-xl-4 col-md-6 mb-3">
          <div class="card border-left-info stat-card h-100">
            <div class="card-body py-3">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <div class="stat-label">Active Products</div>
                  <div class="stat-value text-info"><?= number_format($total_products_count) ?></div>
                  <div class="stat-sub">Available in catalog</div>
                </div>
                <div class="icon-circle icon-info">
                  <i class="fas fa-box-open"></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-3">
          <div class="card border-left-warning stat-card h-100">
            <div class="card-body py-3">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <div class="stat-label">Low Stock Alerts</div>
                  <div class="stat-value text-warning"><?= (int)$low_stock_count ?></div>
                  <div class="stat-sub">Items below reorder level</div>
                </div>
                <div class="icon-circle icon-amber">
                  <i class="fas fa-exclamation-triangle"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Quick Actions Row -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <h6 class="mb-0"><i class="fas fa-bolt text-warning mr-2"></i>Quick Actions</h6>
            </div>
            <div class="card-body py-3">
              <div class="row">
                <div class="col-6 col-sm-4 col-md-2 mb-2">
                  <a href="<?= BASE_URL ?>modules/sale/new_sale.php" class="chip-chip d-flex flex-column text-center">
                    <i class="fas fa-file-invoice mb-1"></i>
                    <span>New Invoice</span>
                  </a>
                </div>
                <div class="col-6 col-sm-4 col-md-2 mb-2">
                  <a href="<?= BASE_URL ?>modules/sale/sales.php" class="chip-chip d-flex flex-column text-center">
                    <i class="fas fa-list mb-1"></i>
                    <span>View Sales</span>
                  </a>
                </div>
                <div class="col-6 col-sm-4 col-md-2 mb-2">
                  <a href="<?= BASE_URL ?>modules/reports/dsr.php" class="chip-chip d-flex flex-column text-center">
                    <i class="fas fa-calendar-check mb-1"></i>
                    <span>DSR Report</span>
                  </a>
                </div>
                <?php if (isAdmin()): ?>
                <div class="col-6 col-sm-4 col-md-2 mb-2">
                  <a href="<?= BASE_URL ?>modules/purchase/add_purchase.php" class="chip-chip d-flex flex-column text-center">
                    <i class="fas fa-cart-arrow-down mb-1"></i>
                    <span>Add Purchase</span>
                  </a>
                </div>
                <div class="col-6 col-sm-4 col-md-2 mb-2">
                  <a href="<?= BASE_URL ?>modules/product/add_product.php" class="chip-chip d-flex flex-column text-center">
                    <i class="fas fa-box-open mb-1"></i>
                    <span>Add Product</span>
                  </a>
                </div>
                <div class="col-6 col-sm-4 col-md-2 mb-2">
                  <a href="<?= BASE_URL ?>modules/customer/receive_amount.php" class="chip-chip d-flex flex-column text-center">
                    <i class="fas fa-hand-holding-usd mb-1"></i>
                    <span>Receive Cash</span>
                  </a>
                </div>
                <?php else: ?>
                <div class="col-6 col-sm-4 col-md-2 mb-2">
                  <a href="<?= BASE_URL ?>modules/product/view_product_list.php" class="chip-chip d-flex flex-column text-center">
                    <i class="fas fa-boxes mb-1"></i>
                    <span>Product List</span>
                  </a>
                </div>
                <div class="col-6 col-sm-4 col-md-2 mb-2">
                  <a href="<?= BASE_URL ?>modules/customer/customers.php" class="chip-chip d-flex flex-column text-center">
                    <i class="fas fa-users mb-1"></i>
                    <span>Customers</span>
                  </a>
                </div>
                <div class="col-6 col-sm-4 col-md-2 mb-2">
                  <a href="<?= BASE_URL ?>modules/customer/customers.php?add=1" class="chip-chip d-flex flex-column text-center">
                    <i class="fas fa-user-plus mb-1"></i>
                    <span>Add Customer</span>
                  </a>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <?php if (isAdmin()): ?>
      <!-- Charts Row (Admin only) -->
      <div class="row mb-4">
        <!-- 7-Day Sales Chart -->
        <div class="col-xl-8 mb-3">
          <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
              <h6 class="mb-0"><i class="fas fa-chart-bar text-primary mr-2"></i>7-Day Sales vs Recovery</h6>
              <span class="badge badge-primary">Weekly</span>
            </div>
            <div class="card-body">
              <div style="position:relative; height:260px;">
                <canvas id="salesRecoveryChart"></canvas>
              </div>
            </div>
          </div>
        </div>

        <!-- Stats Summary -->
        <div class="col-xl-4 mb-3">
          <div class="card h-100">
            <div class="card-header">
              <h6 class="mb-0"><i class="fas fa-tachometer-alt text-success mr-2"></i>Quick Stats</h6>
            </div>
            <div class="card-body p-0">
              <div class="activity-list">
                <div class="activity-item">
                  <div class="activity-dot dot-in"><i class="fas fa-box"></i></div>
                  <div class="flex-grow-1">
                    <div class="activity-text">Total Products</div>
                    <div class="activity-date">Active in system</div>
                  </div>
                  <div class="activity-amount text-primary"><?= number_format($total_products_count) ?></div>
                </div>
                <div class="activity-item">
                  <div class="activity-dot" style="background:#f59e0b;"><i class="fas fa-exclamation-triangle"></i></div>
                  <div class="flex-grow-1">
                    <div class="activity-text">Low Stock Items</div>
                    <div class="activity-date">Below reorder level</div>
                  </div>
                  <div class="activity-amount text-warning"><?= $low_stock_count ?></div>
                </div>
                <div class="activity-item">
                  <div class="activity-dot dot-out"><i class="fas fa-file-invoice-dollar"></i></div>
                  <div class="flex-grow-1">
                    <div class="activity-text">Today Expenses</div>
                    <div class="activity-date">Cash + Bank</div>
                  </div>
                  <div class="activity-amount text-danger"><?= format_currency($today_expenses) ?></div>
                </div>
                <div class="activity-item">
                  <div class="activity-dot dot-in"><i class="fas fa-truck-loading"></i></div>
                  <div class="flex-grow-1">
                    <div class="activity-text">Supplier Payables</div>
                    <div class="activity-date">Outstanding balance</div>
                  </div>
                  <div class="activity-amount text-danger"><?= format_currency($total_payables) ?></div>
                </div>
                <div class="activity-item">
                  <div class="activity-dot" style="background:#6366f1;"><i class="fas fa-university"></i></div>
                  <div class="flex-grow-1">
                    <div class="activity-text">Market Receivables</div>
                    <div class="activity-date">Customer balance due</div>
                  </div>
                  <div class="activity-amount text-primary"><?= format_currency($total_receivables) ?></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Tables Row -->
      <div class="row">
        <!-- Low Stock Alert -->
        <div class="col-xl-6 mb-4">
          <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
              <h6 class="mb-0"><i class="fas fa-exclamation-triangle text-warning mr-2"></i>Low Stock Alert</h6>
              <?php if (isAdmin()): ?>
              <a href="<?= BASE_URL ?>modules/purchase/add_purchase.php" class="btn btn-warning btn-sm">
                <i class="fas fa-cart-plus mr-1"></i> Order Stock
              </a>
              <?php else: ?>
              <a href="<?= BASE_URL ?>modules/product/view_product_list.php" class="btn btn-outline-warning btn-sm">
                <i class="fas fa-boxes mr-1"></i> All Products
              </a>
              <?php endif; ?>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover mb-0">
                  <thead>
                    <tr>
                      <th>Product</th>
                      <th>Company</th>
                      <th class="text-center">Stock</th>
                      <th class="text-center">Reorder</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!empty($low_stock_products)): ?>
                      <?php foreach ($low_stock_products as $prod): ?>
                        <tr>
                          <td>
                            <strong><?= htmlspecialchars($prod['name']) ?></strong>
                          </td>
                          <td><span class="badge badge-info"><?= htmlspecialchars($prod['company_name'] ?? '-') ?></span></td>
                          <td class="text-center">
                            <strong class="<?= $prod['current_stock'] == 0 ? 'text-danger' : 'text-warning' ?>">
                              <?= $prod['current_stock'] ?>
                            </strong>
                          </td>
                          <td class="text-center text-muted"><?= $prod['reorder_level'] ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="4" class="text-center text-muted py-4">
                          <i class="fas fa-check-circle text-success fa-2x mb-2 d-block"></i>
                          All stock levels are optimal.
                        </td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Sales -->
        <div class="col-xl-6 mb-4">
          <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
              <h6 class="mb-0"><i class="fas fa-receipt text-primary mr-2"></i>Recent Invoices</h6>
              <a href="<?= BASE_URL ?>modules/sale/sales.php" class="btn btn-outline-primary btn-sm">View All</a>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover mb-0">
                  <thead>
                    <tr>
                      <th>Invoice #</th>
                      <th>Customer</th>
                      <th class="text-right">Amount</th>
                      <th class="text-center">Print</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!empty($recent_sales)): ?>
                      <?php foreach ($recent_sales as $sale): ?>
                        <tr>
                          <td><span class="badge badge-primary"><?= htmlspecialchars($sale['invoice_no']) ?></span></td>
                          <td>
                            <strong><?= htmlspecialchars($sale['customer_name']) ?></strong>
                            <?php if (!empty($sale['shop_name'])): ?>
                              <br><small class="text-muted"><?= htmlspecialchars($sale['shop_name']) ?></small>
                            <?php endif; ?>
                          </td>
                          <td class="text-right font-weight-bold"><?= format_currency($sale['grand_total']) ?></td>
                          <td class="text-center">
                            <a href="<?= BASE_URL ?>modules/sale/print_invoice.php?id=<?= $sale['id'] ?>" target="_blank" class="btn btn-sm btn-light border" title="Select Type & Print">
                              <i class="fas fa-print text-muted"></i>
                            </a>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="4" class="text-center text-muted py-4">
                          <i class="fas fa-receipt fa-2x mb-2 d-block text-primary"></i>
                          No invoices yet.
                          <a href="<?= BASE_URL ?>modules/sale/new_sale.php" class="d-block mt-2 btn btn-primary btn-sm">Create Invoice</a>
                        </td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

<?php if (isAdmin()): ?>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var chartCanvas = document.getElementById('salesRecoveryChart');
    if (!chartCanvas) return;
    var ctx1 = chartCanvas.getContext('2d');
    new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: <?= json_encode($sales_chart_labels) ?>,
            datasets: [
                {
                    label: 'Sales (Rs.)',
                    data: <?= json_encode($chart_sales_values) ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderColor: '#10b981',
                    borderWidth: 1,
                    borderRadius: 4
                },
                {
                    label: 'Recovery (Rs.)',
                    data: <?= json_encode($chart_recovery_values) ?>,
                    backgroundColor: 'rgba(14, 165, 233, 0.7)',
                    borderColor: '#0ea5e9',
                    borderWidth: 1,
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: { font: { family: 'Poppins' }, boxWidth: 12, padding: 12 }
                },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return ' ' + ctx.dataset.label + ': Rs. ' + Number(ctx.raw).toLocaleString();
                        }
                    }
                }
            },
            scales: {
                x: { grid: { display: false } },
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(v) {
                            if (v >= 1000) return 'Rs ' + (v/1000) + 'k';
                            return 'Rs ' + v;
                        }
                    }
                }
            }
        }
    });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
