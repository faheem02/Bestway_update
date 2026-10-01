<?php
require_once __DIR__ . '/config/database.php';

echo "<h2>Database Schema Updater & Cleaner</h2>";

if (!$db_connected || !$pdo) {
    die("Database connection failed. Please check config/database.php");
}

$queries = [
    // Add invoice_id to sale_items if missing
    "ALTER TABLE sale_items ADD COLUMN invoice_id INT NULL AFTER id",
    // Add sale_id to sale_items if missing (for legacy compatibility)
    "ALTER TABLE sale_items ADD COLUMN sale_id INT NULL AFTER invoice_id",
    // Add invoice_id to sale_returns
    "ALTER TABLE sale_returns ADD COLUMN invoice_id INT NULL AFTER sale_id",
    // Drop cheques table if exists
    "DROP TABLE IF EXISTS cheques",
    "ALTER TABLE company_settings DROP COLUMN drug_license_no",
    "ALTER TABLE customers DROP COLUMN drug_license_no",
    "ALTER TABLE sales_invoices DROP COLUMN drug_license_no",
    // Remove cheque columns and update payment_method enums
    "ALTER TABLE customer_payments DROP COLUMN cheque_no",
    "ALTER TABLE customer_payments DROP COLUMN cheque_date",
    "ALTER TABLE customer_payments DROP COLUMN cheque_status",
    "ALTER TABLE customer_payments MODIFY COLUMN payment_method ENUM('Cash', 'Bank Transfer', 'Online') DEFAULT 'Cash'",
    "ALTER TABLE supplier_payments DROP COLUMN cheque_no",
    "ALTER TABLE supplier_payments DROP COLUMN cheque_date",
    "ALTER TABLE supplier_payments DROP COLUMN cheque_status",
    "ALTER TABLE supplier_payments MODIFY COLUMN payment_method ENUM('Cash', 'Bank Transfer') DEFAULT 'Cash'",
    "ALTER TABLE sales MODIFY COLUMN payment_type ENUM('Cash', 'Credit', 'Bank') DEFAULT 'Credit'",
    "ALTER TABLE purchases MODIFY COLUMN payment_type ENUM('Cash', 'Credit', 'Bank') DEFAULT 'Credit'",

    // Ensure all required columns from bestway_wholesale.sql exist across all tables
    "ALTER TABLE `company_settings` ADD COLUMN `ntn_no` VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE `company_settings` ADD COLUMN `strn_no` VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE `company_settings` ADD COLUMN `drug_license_no` VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE `company_settings` ADD COLUMN `drug_license_valid_upto` DATE DEFAULT NULL",
    "ALTER TABLE `company_settings` ADD COLUMN `invoice_footer_notes` TEXT DEFAULT NULL",
    "ALTER TABLE `bookers` ADD COLUMN `commission_rate` DECIMAL(5,2) DEFAULT 0.00",
    "ALTER TABLE `customer_payments` ADD COLUMN `collector_staff_id` INT NULL",
    "ALTER TABLE `customer_payments` ADD COLUMN `discount_allowed` DECIMAL(12,2) DEFAULT 0.00",
    "ALTER TABLE `customer_payments` ADD COLUMN `sale_id` INT NULL AFTER `customer_id`",
    "ALTER TABLE `suppliers` ADD COLUMN `mobile` VARCHAR(50) NULL",
    "ALTER TABLE `suppliers` ADD COLUMN `email` VARCHAR(100) NULL",
    "ALTER TABLE `suppliers` ADD COLUMN `ntn_strn` VARCHAR(50) NULL",
    "ALTER TABLE `suppliers` ADD COLUMN `bank_details` TEXT NULL",
    "ALTER TABLE `suppliers` ADD COLUMN `credit_days` INT DEFAULT 30",
    "ALTER TABLE `supplier_payments` ADD COLUMN `discount_received` DECIMAL(12,2) DEFAULT 0.00",
    "ALTER TABLE `sales_invoices` ADD COLUMN `created_by` INT DEFAULT NULL",
    "ALTER TABLE `sales_invoices` ADD COLUMN `customer_id` INT NULL AFTER `customer_name`",
    "ALTER TABLE `customers` ADD COLUMN `invoice_type` ENUM('sale', 'warranty') DEFAULT 'sale' AFTER `status`",
    "ALTER TABLE `sale_items` ADD COLUMN `item_name` VARCHAR(200) NOT NULL DEFAULT ''",
    "ALTER TABLE `sale_items` ADD COLUMN `batch_no` VARCHAR(100) NULL",
    "ALTER TABLE `sale_items` ADD COLUMN `expiry_date` DATE NULL",
    "ALTER TABLE `sale_items` ADD COLUMN `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00",
    "ALTER TABLE `sale_items` ADD COLUMN `sale_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00",
    "ALTER TABLE `sale_items` ADD COLUMN `trade_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00",
    "ALTER TABLE `sale_items` ADD COLUMN `discount_percent` DECIMAL(5,2) DEFAULT 0.00",
    "ALTER TABLE `sale_items` ADD COLUMN `extra_discount_percent` DECIMAL(5,2) DEFAULT 0.00",
    "ALTER TABLE `sale_items` ADD COLUMN `total_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00",
    "ALTER TABLE `sale_items` ADD COLUMN `total_price` DECIMAL(14,2) NOT NULL DEFAULT 0.00",
    "ALTER TABLE `sales` ADD COLUMN `sale_time` TIME NULL",
    "ALTER TABLE `sale_returns` ADD COLUMN `payment_method` VARCHAR(50) NULL",
    "ALTER TABLE `sale_returns` ADD COLUMN `cash_account_id` INT NULL",
    "ALTER TABLE `sale_returns` ADD COLUMN `bank_account_id` INT NULL",
    "ALTER TABLE `sale_return_items` ADD COLUMN `return_id` INT NOT NULL DEFAULT 0",
    "ALTER TABLE `sale_return_items` ADD COLUMN `batch_no` VARCHAR(100) NULL",
    "ALTER TABLE `sale_return_items` ADD COLUMN `condition` VARCHAR(50) DEFAULT 'Good'",
    "ALTER TABLE `cash_accounts` ADD COLUMN `status` ENUM('Active', 'Inactive') DEFAULT 'Active'",
    "ALTER TABLE `expenses` ADD COLUMN `expense_category` VARCHAR(150) NULL",
    "ALTER TABLE `expenses` ADD COLUMN `title` VARCHAR(200) NULL",
    "ALTER TABLE `expenses` ADD COLUMN `paid_to` VARCHAR(150) NULL",
    "ALTER TABLE `expenses` ADD COLUMN `vendor_name` VARCHAR(150) NULL AFTER `description`",
    "ALTER TABLE `expenses` ADD COLUMN `bill_no` VARCHAR(100) NULL AFTER `vendor_name`",
    "ALTER TABLE `expenses` ADD COLUMN `created_at` DATE NULL AFTER `created_by`",
    "ALTER TABLE `expense_categories` ADD COLUMN `description` TEXT NULL AFTER `name`",
    "ALTER TABLE `staff` ADD COLUMN `employee_code` VARCHAR(50) NULL",
    "ALTER TABLE `staff` ADD COLUMN `designation` VARCHAR(100) NULL",
    "ALTER TABLE `staff` ADD COLUMN `salary` DECIMAL(12,2) DEFAULT 0.00",
    "ALTER TABLE `staff` ADD COLUMN `commission_rate` DECIMAL(5,2) DEFAULT 0.00",
    "ALTER TABLE `sale_items` DROP FOREIGN KEY `fk_si_sale`",
    "ALTER TABLE `sale_items` MODIFY COLUMN `sale_id` INT NULL DEFAULT NULL",
    "ALTER TABLE `bank_accounts` ADD COLUMN `opening_date` DATE NULL DEFAULT NULL AFTER `opening_balance`",

    // ---- Employees: commission-based, two types (general / salesman) ----
    "UPDATE employees SET employee_type = 'salesman' WHERE employee_type = 'order_booker'",
    "UPDATE employees SET employee_type = 'general' WHERE employee_type = 'loader'",
    "ALTER TABLE `employees` ADD COLUMN `commission_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER `salary`",
    "ALTER TABLE `employees` MODIFY COLUMN `employee_type` ENUM('general','salesman') NOT NULL DEFAULT 'salesman'",


    // ---- Users: replace order_booker role with salesman ----
    "UPDATE users SET role = 'salesman' WHERE role = 'order_booker'",
    "ALTER TABLE `users` MODIFY COLUMN `role` ENUM('Admin','Manager','Accountant','Operator','salesman') DEFAULT 'Admin'"
];

// Valid tables required by the application
$valid_tables = [
    'users',
    'company_settings',
    'companies',
    'categories',
    'units',
    'bookers',
    'booker_ledgers',
    'routes',
    'products',
    'product_batches',
    'opening_stock_logs',
    'customers',
    'customer_payments',
    'customer_ledgers',
    'suppliers',
    'supplier_payments',
    'supplier_ledgers',
    'sales_invoices',
    'sale_items',
    'sales',
    'sale_returns',
    'sale_return_items',
    'purchases',
    'purchase_items',
    'purchase_returns',
    'purchase_return_items',
    'bank_accounts',
    'cash_accounts',
    'account_ledgers',
    'cash_book_transactions',
    'udhaar_book',
    'expense_categories',
    'expenses',
    'delivery_riders',
    'delivery_challans',
    'delivery_challan_items',
    'staff',
    'employees',
    'employee_salaries',
    'areas',
    'activity_logs',
    'bank_transactions',
    'cash_book_daily',
    'cash_book',
    'brands',
    'branches',
    'customer_receipts'
];

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    // 1. Drop extra tables not in the valid list
    if (!in_array($table, $valid_tables)) {
        $queries[] = "DROP TABLE `$table`";
        continue;
    }
}

foreach ($queries as $query) {
    try {
        $pdo->exec($query);
        echo "<p style='color:green;'>SUCCESS: $query</p>";
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Duplicate column name') !== false || strpos($msg, 'check that') !== false || strpos($msg, 'Unknown column') !== false || strpos($msg, '1091') !== false) {
            echo "<p style='color:orange;'>SKIPPED (Already exists/Not found): $query</p>";
        } else {
            echo "<p style='color:red;'>ERROR: $query - " . $e->getMessage() . "</p>";
        }
    }
}

echo "<h3>Database Update & Cleanup Complete!</h3>";
echo "<a href='index.php'>Go back to Dashboard</a>";
?>
