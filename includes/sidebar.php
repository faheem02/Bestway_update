<?php
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir  = basename(dirname($_SERVER['PHP_SELF']));
?>
  <!-- ===== SIDEBAR ===== -->
  <nav class="sidebar" id="sidebar">

    <!-- Brand -->
    <a class="sidebar-brand" href="<?= BASE_URL ?>index.php">
      <div class="sidebar-brand-icon"><i class="fas fa-boxes"></i></div>
      <div>
        <span class="sidebar-brand-text">Bestway</span>
        <span class="sidebar-brand-sub">Distribution</span>
      </div>
    </a>

    <hr class="sidebar-divider">

    <!-- Dashboard -->
    <div class="nav-item <?= ($current_page == 'index.php' && $current_dir == 'Bestway') || ($current_page == 'index.php' && !in_array($current_dir, ['sale','purchase','product','customer','supplier','employees','areas','cashbook','bankbook','expense','reports'])) ? 'active' : '' ?>">
      <a class="nav-link" href="<?= BASE_URL ?>index.php">
        <i class="fas fa-fw fa-tachometer-alt"></i>
        <span>Dashboard</span>
      </a>
    </div>

    <hr class="sidebar-divider">
    <div class="sidebar-heading">Wholesale Modules</div>

    <!-- Sale -->
    <?php if (isAdmin() || isSalesTeam()): ?>
    <?php $on_sale = ($current_dir == 'sale'); ?>
    <div class="nav-item">
      <a class="nav-link <?= !$on_sale ? 'collapsed' : '' ?>" data-toggle="collapse" href="#collapseSale" role="button" aria-expanded="<?= $on_sale ? 'true' : 'false' ?>">
        <i class="fas fa-fw fa-receipt"></i>
        <span>Sale</span>
        <span class="arrow"><i class="fas fa-chevron-<?= $on_sale ? 'down' : 'right' ?>"></i></span>
      </a>
      <div class="collapse <?= $on_sale ? 'show' : '' ?>" id="collapseSale">
        <div class="collapse-inner">
          <a class="collapse-item <?= $current_page == 'new_sale.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/sale/new_sale.php"><i class="fas fa-plus-circle"></i> Add Sale</a>
          <a class="collapse-item <?= $current_page == 'sales.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/sale/sales.php"><i class="fas fa-list"></i> View All Sales</a>
          <?php if (isAdmin()): ?>
          <a class="collapse-item <?= $current_page == 'sale_return.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/sale/sale_return.php"><i class="fas fa-undo"></i> Sale Return</a>
          <a class="collapse-item <?= $current_page == 'order_booker_invoices.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/sale/order_booker_invoices.php"><i class="fas fa-user-tag"></i> Salesman Invoices</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Purchase (Admin only) -->
    <?php if (isAdmin()): ?>
    <?php $on_purchase = ($current_dir == 'purchase'); ?>
    <div class="nav-item">
      <a class="nav-link <?= !$on_purchase ? 'collapsed' : '' ?>" data-toggle="collapse" href="#collapsePurchase" role="button" aria-expanded="<?= $on_purchase ? 'true' : 'false' ?>">
        <i class="fas fa-fw fa-cart-arrow-down"></i>
        <span>Purchase</span>
        <span class="arrow"><i class="fas fa-chevron-<?= $on_purchase ? 'down' : 'right' ?>"></i></span>
      </a>
      <div class="collapse <?= $on_purchase ? 'show' : '' ?>" id="collapsePurchase">
        <div class="collapse-inner">
          <a class="collapse-item <?= $current_page == 'add_purchase.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/purchase/add_purchase.php"><i class="fas fa-plus-circle"></i> Add Purchase</a>
          <a class="collapse-item <?= $current_page == 'purchases.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/purchase/purchases.php"><i class="fas fa-list"></i> View Purchases</a>
          <a class="collapse-item <?= $current_page == 'purchase_return.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/purchase/purchase_return.php"><i class="fas fa-undo"></i> Purchase Return</a>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Product (Admin only) -->
    <?php if (isAdmin()): ?>
    <?php $on_product = ($current_dir == 'product'); ?>
    <div class="nav-item">
      <a class="nav-link <?= !$on_product ? 'collapsed' : '' ?>" data-toggle="collapse" href="#collapseProduct" role="button" aria-expanded="<?= $on_product ? 'true' : 'false' ?>">
        <i class="fas fa-fw fa-box-open"></i>
        <span>Product</span>
        <span class="arrow"><i class="fas fa-chevron-<?= $on_product ? 'down' : 'right' ?>"></i></span>
      </a>
      <div class="collapse <?= $on_product ? 'show' : '' ?>" id="collapseProduct">
        <div class="collapse-inner">
          <a class="collapse-item <?= $current_page == 'add_product.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/product/add_product.php"><i class="fas fa-plus-circle"></i> Add Product</a>
          <a class="collapse-item <?= $current_page == 'view_product_list.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/product/view_product_list.php"><i class="fas fa-list"></i> View Product List</a>
          <a class="collapse-item <?= $current_page == 'add_company.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/product/add_company.php"><i class="fas fa-industry"></i> Add Company</a>
          <a class="collapse-item <?= $current_page == 'add_category.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/product/add_category.php"><i class="fas fa-tags"></i> Add Category</a>
          <a class="collapse-item <?= $current_page == 'add_opening_stock.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/product/add_opening_stock.php"><i class="fas fa-boxes"></i> Opening Stock</a>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Customer -->
    <?php $on_customer = ($current_dir == 'customer'); ?>
    <div class="nav-item">
      <a class="nav-link <?= !$on_customer ? 'collapsed' : '' ?>" data-toggle="collapse" href="#collapseCustomer" role="button" aria-expanded="<?= $on_customer ? 'true' : 'false' ?>">
        <i class="fas fa-fw fa-users"></i>
        <span>Customer</span>
        <span class="arrow"><i class="fas fa-chevron-<?= $on_customer ? 'down' : 'right' ?>"></i></span>
      </a>
      <div class="collapse <?= $on_customer ? 'show' : '' ?>" id="collapseCustomer">
        <div class="collapse-inner">
          <a class="collapse-item <?= $current_page == 'customers.php' && isset($_GET['add']) ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/customer/customers.php?add=1"><i class="fas fa-user-plus"></i> Add Customer</a>
          <a class="collapse-item <?= $current_page == 'customers.php' && !isset($_GET['add']) ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/customer/customers.php"><i class="fas fa-address-card"></i> View Customers</a>
          <?php if (isAdmin()): ?>
          <a class="collapse-item <?= $current_page == 'customer_ledger.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/customer/customer_ledger.php"><i class="fas fa-book-open"></i> Customer Ledger</a>
          <a class="collapse-item <?= $current_page == 'receive_amount.php' && $current_dir == 'customer' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/customer/receive_amount.php"><i class="fas fa-hand-holding-usd"></i> Receive Amount</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if (isAdmin()): ?>
    <!-- Supplier (Admin only) -->
    <?php $on_supplier = ($current_dir == 'supplier'); ?>
    <div class="nav-item">
      <a class="nav-link <?= !$on_supplier ? 'collapsed' : '' ?>" data-toggle="collapse" href="#collapseSupplier" role="button" aria-expanded="<?= $on_supplier ? 'true' : 'false' ?>">
        <i class="fas fa-fw fa-truck-loading"></i>
        <span>Supplier</span>
        <span class="arrow"><i class="fas fa-chevron-<?= $on_supplier ? 'down' : 'right' ?>"></i></span>
      </a>
      <div class="collapse <?= $on_supplier ? 'show' : '' ?>" id="collapseSupplier">
        <div class="collapse-inner">
          <a class="collapse-item <?= $current_page == 'suppliers.php' && isset($_GET['add']) ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/supplier/suppliers.php?add=1"><i class="fas fa-plus-circle"></i> Add Supplier</a>
          <a class="collapse-item <?= $current_page == 'suppliers.php' && !isset($_GET['add']) ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/supplier/suppliers.php"><i class="fas fa-list"></i> View Suppliers</a>
          <a class="collapse-item <?= $current_page == 'supplier_ledger.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/supplier/supplier_ledger.php"><i class="fas fa-book"></i> Supplier Ledger</a>
          <a class="collapse-item <?= $current_page == 'pay_amount.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/supplier/pay_amount.php"><i class="fas fa-money-bill-alt"></i> Pay Amount</a>
        </div>
      </div>
    </div>

    <!-- Employees (Admin only) -->
    <?php $on_employees = ($current_dir == 'employees'); ?>
    <div class="nav-item">
      <a class="nav-link <?= !$on_employees ? 'collapsed' : '' ?>" data-toggle="collapse" href="#collapseEmployees" role="button" aria-expanded="<?= $on_employees ? 'true' : 'false' ?>">
        <i class="fas fa-fw fa-user-tie"></i>
        <span>Employees</span>
        <span class="arrow"><i class="fas fa-chevron-<?= $on_employees ? 'down' : 'right' ?>"></i></span>
      </a>
      <div class="collapse <?= $on_employees ? 'show' : '' ?>" id="collapseEmployees">
        <div class="collapse-inner">
          <a class="collapse-item <?= $current_page == 'create.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/employees/create.php"><i class="fas fa-user-plus"></i> Add Employee</a>
          <a class="collapse-item <?= $current_page == 'index.php' && $current_dir == 'employees' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/employees/index.php"><i class="fas fa-list"></i> View Employees</a>
          <a class="collapse-item <?= $current_page == 'ledger.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/employees/ledger.php"><i class="fas fa-book"></i> Employee Ledger</a>
          <a class="collapse-item <?= $current_page == 'pay_salary.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/employees/pay_salary.php"><i class="fas fa-money-check-alt"></i> Pay Salary</a>
        </div>
      </div>
    </div>

    <!-- Areas (Admin only) -->
    <div class="nav-item <?= $current_dir == 'areas' ? 'active' : '' ?>">
      <a class="nav-link <?= $current_dir == 'areas' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/areas/index.php">
        <i class="fas fa-fw fa-map-marked-alt"></i>
        <span>Areas</span>
      </a>
    </div>

    <!-- Delivery List -->
    <div class="nav-item <?= $current_dir == 'delivery' ? 'active' : '' ?>">
      <a class="nav-link <?= $current_dir == 'delivery' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/delivery/view_challan.php">
        <i class="fas fa-fw fa-truck-loading"></i>
        <span>Delivery List</span>
      </a>
    </div>

    <hr class="sidebar-divider">
    <div class="sidebar-heading">Finance</div>

    <!-- Cash Book (Admin only) -->
    <div class="nav-item <?= $current_dir == 'cashbook' ? 'active' : '' ?>">
      <a class="nav-link <?= $current_dir == 'cashbook' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/cashbook/index.php">
        <i class="fas fa-fw fa-money-bill-alt"></i>
        <span>Cash Book</span>
      </a>
    </div>

    <!-- Bank Book (Admin only) -->
    <div class="nav-item <?= $current_dir == 'bankbook' ? 'active' : '' ?>">
      <a class="nav-link <?= $current_dir == 'bankbook' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/bankbook/index.php">
        <i class="fas fa-fw fa-university"></i>
        <span>Bank Book</span>
      </a>
    </div>

    <!-- Expense (Admin only) -->
    <?php $on_expense = ($current_dir == 'expense'); ?>
    <div class="nav-item">
      <a class="nav-link <?= !$on_expense ? 'collapsed' : '' ?>" data-toggle="collapse" href="#collapseExpense" role="button" aria-expanded="<?= $on_expense ? 'true' : 'false' ?>">
        <i class="fas fa-fw fa-file-invoice-dollar"></i>
        <span>Expense</span>
        <span class="arrow"><i class="fas fa-chevron-<?= $on_expense ? 'down' : 'right' ?>"></i></span>
      </a>
      <div class="collapse <?= $on_expense ? 'show' : '' ?>" id="collapseExpense">
        <div class="collapse-inner">
          <a class="collapse-item <?= $current_page == 'index.php' && $current_dir == 'expense' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/expense/index.php"><i class="fas fa-file-invoice-dollar"></i> All Expenses</a>
          <a class="collapse-item <?= $current_page == 'categories.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/expense/categories.php"><i class="fas fa-tags"></i> Categories</a>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Reports (Admin only) -->
    <?php if (isAdmin()): ?>
    <hr class="sidebar-divider">
    <div class="sidebar-heading">Reports</div>

    <!-- Daily Sales Report -->
    <div class="nav-item <?= $current_page == 'dsr.php' ? 'active' : '' ?>">
      <a class="nav-link <?= $current_page == 'dsr.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/reports/dsr.php">
        <i class="fas fa-fw fa-calendar-check"></i>
        <span>Daily Sales Report</span>
      </a>
    </div>

    <!-- Item Wise Sale Report -->
    <div class="nav-item <?= $current_page == 'item_wise_sale.php' ? 'active' : '' ?>">
      <a class="nav-link <?= $current_page == 'item_wise_sale.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>modules/reports/item_wise_sale.php">
        <i class="fas fa-fw fa-boxes"></i>
        <span>Item Wise Sale Report</span>
      </a>
    </div>
    <?php endif; ?>

    <!-- Sidebar Footer / Logout -->
    <div class="sidebar-footer">
      <a class="nav-link" href="<?= BASE_URL ?>logout.php">
        <i class="fas fa-fw fa-sign-out-alt"></i>
        <span>Logout</span>
      </a>
    </div>

  </nav>
  <!-- End Sidebar -->
