-- =====================================================================
-- BESTWAY WHOLESALE MEDICINE DISTRIBUTION SYSTEM
-- Database Schema: `bestway_wholesale`
-- Clean Schema (No Timestamps)
-- =====================================================================

-- CREATE DATABASE IF NOT EXISTS `bestway_wholesale` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `bestway_wholesale`;

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- ---------------------------------------------------------------------
-- 1. SYSTEM USERS & AUTHENTICATION
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `role` ENUM('Admin', 'Manager', 'Accountant', 'Operator', 'salesman') DEFAULT 'Admin',
  `email` VARCHAR(100) NULL,
  `phone` VARCHAR(50) NULL,
  `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
  `last_login` DATETIME NULL,
  `created_at` DATE NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 2. COMPANY SETTINGS & PRINT HEADER CONFIGURATION
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `company_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `business_name` VARCHAR(150) NOT NULL DEFAULT 'Bestway Distribution',
  `tagline` VARCHAR(255) DEFAULT 'Wholesale Medicine & Pharma Distribution',
  `logo_path` VARCHAR(255) DEFAULT 'assets/images/logo.png',
  `phone` VARCHAR(100) DEFAULT '0300-1234567 / 0321-7654321',
  `email` VARCHAR(100) DEFAULT 'info@bestwaypharma.com',
  `address` TEXT DEFAULT NULL,
  `ntn_no` VARCHAR(50) DEFAULT NULL,
  `strn_no` VARCHAR(50) DEFAULT NULL,
  `drug_license_no` VARCHAR(100) DEFAULT NULL,
  `drug_license_valid_upto` DATE DEFAULT NULL,
  `invoice_footer_notes` TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 3. PHARMACEUTICAL COMPANIES / MANUFACTURERS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `companies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `code` VARCHAR(50) UNIQUE NULL,
  `contact_person` VARCHAR(100) NULL,
  `phone` VARCHAR(50) NULL,
  `email` VARCHAR(100) NULL,
  `address` TEXT NULL,
  `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
  INDEX (`name`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 4. MEDICINE CATEGORIES
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) UNIQUE NULL,
  `description` TEXT NULL,
  `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
  INDEX (`name`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 5. MEASUREMENT UNITS (UOM)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `units` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL,
  `short_name` VARCHAR(20) NOT NULL,
  INDEX (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 6. ORDER BOOKERS / SALESMEN
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bookers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booker_code` VARCHAR(50) UNIQUE NULL,
  `name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(50) NULL,
  `address` TEXT NULL,
  `commission_rate` DECIMAL(5,2) DEFAULT 0.00,
  `opening_balance` DECIMAL(14,2) DEFAULT 0.00,
  `current_balance` DECIMAL(14,2) DEFAULT 0.00,
  `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
  INDEX (`name`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 7. BOOKER LEDGERS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `booker_ledgers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booker_id` INT NOT NULL,
  `transaction_date` DATE NOT NULL,
  `transaction_type` VARCHAR(50) NOT NULL,
  `reference_no` VARCHAR(100) NULL,
  `debit_amount` DECIMAL(14,2) DEFAULT 0.00,
  `credit_amount` DECIMAL(14,2) DEFAULT 0.00,
  `running_balance` DECIMAL(14,2) DEFAULT 0.00,
  `description` TEXT NULL,
  INDEX (`booker_id`),
  INDEX (`transaction_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 8. ROUTES & DELIVERY AREAS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `routes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `route_code` VARCHAR(50) UNIQUE NULL,
  `name` VARCHAR(150) NOT NULL,
  `area_description` TEXT NULL,
  `delivery_day` VARCHAR(50) NULL,
  `assigned_booker_id` INT NULL,
  `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
  INDEX (`name`),
  INDEX (`assigned_booker_id`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8b. AREAS & TERRITORIES
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `areas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(200) NOT NULL,
  `city` VARCHAR(100) DEFAULT 'Lahore',
  `description` TEXT NULL,
  `status` TINYINT(1) DEFAULT 1,
  `created_at` DATE NULL,
  `updated_at` DATE NULL,
  UNIQUE KEY `uk_areas_name` (`name`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 9. PRODUCTS / MEDICINES MASTER
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_code` VARCHAR(50) UNIQUE NULL,
  `barcode` VARCHAR(100) UNIQUE NULL,
  `name` VARCHAR(200) NOT NULL,
  `generic_name` VARCHAR(200) NULL,
  `company_id` INT NULL,
  `category_id` INT NULL,
  `unit_id` INT NULL,
  `pack_size` VARCHAR(50) NULL,
  `packs_per_box` INT DEFAULT 1,
  `tablets_per_pack` INT DEFAULT 10,
  `total_tablets_per_box` INT DEFAULT 10,
  `stock_unit` VARCHAR(50) DEFAULT 'Pack',
  `purchase_price` DECIMAL(12,2) DEFAULT 0.00,
  `trade_price` DECIMAL(12,2) DEFAULT 0.00,
  `retail_price` DECIMAL(12,2) DEFAULT 0.00,
  `wholesale_price` DECIMAL(12,2) DEFAULT 0.00,
  `discount_percent` DECIMAL(5,2) DEFAULT 0.00,
  `max_discount_percent` DECIMAL(5,2) DEFAULT 0.00,
  `reorder_level` INT DEFAULT 10,
  `opening_stock` INT DEFAULT 0,
  `current_stock` INT DEFAULT 0,
  `location_rack` VARCHAR(50) NULL,
  `requires_prescription` TINYINT(1) DEFAULT 0,
  `cold_chain` TINYINT(1) DEFAULT 0,
  `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
  INDEX (`product_code`),
  INDEX (`name`),
  INDEX (`company_id`),
  INDEX (`category_id`),
  INDEX (`unit_id`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 10. PRODUCT BATCHES
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_batches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `batch_no` VARCHAR(100) NOT NULL,
  `manufacturing_date` DATE NULL,
  `expiry_date` DATE NOT NULL,
  `purchase_price` DECIMAL(12,2) DEFAULT 0.00,
  `trade_price` DECIMAL(12,2) DEFAULT 0.00,
  `retail_price` DECIMAL(12,2) DEFAULT 0.00,
  `initial_quantity` INT DEFAULT 0,
  `current_stock` INT DEFAULT 0,
  `status` ENUM('Active', 'Expired', 'Claimed', 'Exhausted') DEFAULT 'Active',
  INDEX (`product_id`),
  INDEX (`batch_no`),
  INDEX (`expiry_date`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 11. OPENING STOCK & ADJUSTMENT LOGS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `opening_stock_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `batch_no` VARCHAR(100) NOT NULL,
  `expiry_date` DATE NULL,
  `quantity` DECIMAL(12,2) NOT NULL,
  `purchase_rate` DECIMAL(12,2) DEFAULT 0.00,
  `trade_rate` DECIMAL(12,2) DEFAULT 0.00,
  `total_value` DECIMAL(14,2) DEFAULT 0.00,
  `entry_date` DATE NOT NULL,
  `remarks` VARCHAR(255) NULL,
  `created_by` INT NULL,
  INDEX (`product_id`),
  INDEX (`entry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 12. CUSTOMERS / PHARMACIES MASTER
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `customer_code` VARCHAR(50) UNIQUE NULL,
  `name` VARCHAR(150) NOT NULL,
  `shop_name` VARCHAR(200) NOT NULL,
  `phone` VARCHAR(50) NOT NULL,
  `address` TEXT NULL,
  `route_id` INT NULL,
  `area` VARCHAR(200) NULL,
  `opening_balance` DECIMAL(14,2) DEFAULT 0.00,
  `current_balance` DECIMAL(14,2) DEFAULT 0.00,
  `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
  `invoice_type` ENUM('sale', 'warranty') DEFAULT 'sale',
  INDEX (`customer_code`),
  INDEX (`shop_name`),
  INDEX (`name`),
  INDEX (`route_id`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 13. CUSTOMER PAYMENTS / RECOVERIES
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customer_payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `voucher_no` VARCHAR(50) UNIQUE NOT NULL,
  `payment_date` DATE NOT NULL,
  `customer_id` INT NOT NULL,
  `sale_id` INT NULL,
  `route_id` INT NULL,
  `collector_staff_id` INT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `payment_method` ENUM('Cash', 'Bank Transfer', 'Online') DEFAULT 'Cash',
  `bank_account_id` INT NULL,
  `discount_allowed` DECIMAL(12,2) DEFAULT 0.00,
  `remarks` TEXT NULL,
  `created_by` INT NULL,
  INDEX (`voucher_no`),
  INDEX (`customer_id`),
  INDEX (`sale_id`),
  INDEX (`payment_date`),
  INDEX (`route_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 14. CUSTOMER LEDGERS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customer_ledgers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT NOT NULL,
  `transaction_date` DATE NOT NULL,
  `transaction_type` VARCHAR(50) NOT NULL,
  `reference_no` VARCHAR(100) NOT NULL,
  `debit_amount` DECIMAL(14,2) DEFAULT 0.00,
  `credit_amount` DECIMAL(14,2) DEFAULT 0.00,
  `running_balance` DECIMAL(14,2) NOT NULL,
  `description` TEXT NULL,
  INDEX (`customer_id`),
  INDEX (`transaction_date`),
  INDEX (`reference_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 15. SUPPLIERS / VENDORS MASTER
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `supplier_code` VARCHAR(50) UNIQUE NULL,
  `name` VARCHAR(150) NOT NULL,
  `company_name` VARCHAR(200) NOT NULL,
  `phone` VARCHAR(50) NOT NULL,
  `mobile` VARCHAR(50) NULL,
  `email` VARCHAR(100) NULL,
  `ntn_strn` VARCHAR(50) NULL,
  `address` TEXT NULL,
  `bank_details` TEXT NULL,
  `credit_days` INT DEFAULT 30,
  `opening_balance` DECIMAL(14,2) DEFAULT 0.00,
  `current_balance` DECIMAL(14,2) DEFAULT 0.00,
  `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
  INDEX (`supplier_code`),
  INDEX (`company_name`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 16. SUPPLIER PAYMENTS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `supplier_payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `voucher_no` VARCHAR(50) UNIQUE NOT NULL,
  `payment_date` DATE NOT NULL,
  `supplier_id` INT NOT NULL,
  `purchase_id` INT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `payment_method` ENUM('Cash', 'Bank Transfer') DEFAULT 'Cash',
  `bank_account_id` INT NULL,
  `discount_received` DECIMAL(12,2) DEFAULT 0.00,
  `remarks` TEXT NULL,
  `created_by` INT NULL,
  INDEX (`voucher_no`),
  INDEX (`supplier_id`),
  INDEX (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 17. SUPPLIER LEDGERS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `supplier_ledgers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `supplier_id` INT NOT NULL,
  `transaction_date` DATE NOT NULL,
  `transaction_type` VARCHAR(50) NOT NULL,
  `reference_no` VARCHAR(100) NOT NULL,
  `debit_amount` DECIMAL(14,2) DEFAULT 0.00,
  `credit_amount` DECIMAL(14,2) DEFAULT 0.00,
  `running_balance` DECIMAL(14,2) NOT NULL,
  `description` TEXT NULL,
  INDEX (`supplier_id`),
  INDEX (`transaction_date`),
  INDEX (`reference_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 18. SALES INVOICES (HEADER)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sales_invoices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_no` VARCHAR(50) NOT NULL UNIQUE,
  `customer_name` VARCHAR(150) NOT NULL,
  `customer_id` INT NULL,
  `invoice_date` DATE NOT NULL,
  `payment_terms` VARCHAR(50) DEFAULT 'Credit 30 Days',
  `payment_method` VARCHAR(50) DEFAULT 'Cash',
  `cash_account_id` INT DEFAULT NULL,
  `bank_account_id` INT DEFAULT NULL,
  `subtotal` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(14,2) DEFAULT 0.00,
  `discount_percent` DECIMAL(5,2) DEFAULT 0.00,
  `tax_amount` DECIMAL(12,2) DEFAULT 0.00,
  `shipping_cost` DECIMAL(10,2) DEFAULT 0.00,
  `adjustment` DECIMAL(10,2) DEFAULT 0.00,
  `round_off` DECIMAL(10,2) DEFAULT 0.00,
  `grand_total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `previous_balance` DECIMAL(14,2) DEFAULT 0.00,
  `net_payable` DECIMAL(14,2) DEFAULT 0.00,
  `paid_amount` DECIMAL(14,2) DEFAULT 0.00,
  `balance_due` DECIMAL(14,2) DEFAULT 0.00,
  `booker_id` INT DEFAULT NULL,
  `booker_name` VARCHAR(150) DEFAULT NULL,
  `route_id` INT DEFAULT NULL,
  `route_name` VARCHAR(150) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  INDEX (`invoice_no`),
  INDEX (`invoice_date`),
  INDEX (`customer_name`),
  INDEX (`booker_id`),
  INDEX (`route_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 19. SALE ITEMS (INVOICE LINE ITEMS)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sale_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_id` INT NOT NULL,
  `sale_id` INT NULL,
  `product_id` INT NOT NULL,
  `item_name` VARCHAR(200) NOT NULL,
  `batch_id` INT NULL,
  `batch_no` VARCHAR(100) NULL,
  `expiry_date` DATE NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `bonus_quantity` INT DEFAULT 0,
  `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `trade_price` DECIMAL(12,2) DEFAULT 0.00,
  `retail_price` DECIMAL(12,2) DEFAULT 0.00,
  `discount_percent` DECIMAL(5,2) DEFAULT 0.00,
  `extra_discount_percent` DECIMAL(5,2) DEFAULT 0.00,
  `total_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  INDEX (`invoice_id`),
  INDEX (`sale_id`),
  INDEX (`product_id`),
  INDEX (`batch_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 20. SALES (DASHBOARD & REPORTING COMPATIBILITY TABLE)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sales` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_no` VARCHAR(50) UNIQUE NOT NULL,
  `sale_date` DATE NOT NULL,
  `sale_time` TIME NULL,
  `customer_id` INT NOT NULL,
  `route_id` INT NULL,
  `booker_id` INT NULL,
  `subtotal` DECIMAL(14,2) DEFAULT 0.00,
  `item_discount` DECIMAL(12,2) DEFAULT 0.00,
  `special_discount_percent` DECIMAL(5,2) DEFAULT 0.00,
  `special_discount_amount` DECIMAL(12,2) DEFAULT 0.00,
  `tax_percent` DECIMAL(5,2) DEFAULT 0.00,
  `tax_amount` DECIMAL(12,2) DEFAULT 0.00,
  `round_off` DECIMAL(6,2) DEFAULT 0.00,
  `grand_total` DECIMAL(14,2) DEFAULT 0.00,
  `paid_amount` DECIMAL(14,2) DEFAULT 0.00,
  `balance_amount` DECIMAL(14,2) DEFAULT 0.00,
  `payment_type` ENUM('Cash', 'Credit', 'Bank') DEFAULT 'Credit',
  `payment_status` ENUM('Paid', 'Partial', 'Unpaid') DEFAULT 'Unpaid',
  `delivery_status` ENUM('Pending', 'Dispatched', 'Delivered', 'Cancelled') DEFAULT 'Delivered',
  `gate_pass_no` VARCHAR(50) NULL,
  `vehicle_no` VARCHAR(50) NULL,
  `driver_name` VARCHAR(100) NULL,
  `notes` TEXT NULL,
  `created_by` INT NULL,
  INDEX (`invoice_no`),
  INDEX (`customer_id`),
  INDEX (`sale_date`),
  INDEX (`route_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 21. SALE RETURNS (HEADER)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sale_returns` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `return_no` VARCHAR(50) NOT NULL UNIQUE,
  `sale_id` INT NULL,
  `invoice_id` INT NULL,
  `return_date` DATE NOT NULL,
  `customer_id` INT NOT NULL,
  `refund_type` VARCHAR(50) DEFAULT 'Credit Note',
  `total_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `deduction_amount` DECIMAL(12,2) DEFAULT 0.00,
  `net_refund_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `reason` TEXT NULL,
  `payment_method` VARCHAR(50) NULL,
  `cash_account_id` INT NULL,
  `bank_account_id` INT NULL,
  `status` ENUM('Completed', 'Pending', 'Cancelled') DEFAULT 'Completed',
  `created_by` INT NULL,
  INDEX (`return_no`),
  INDEX (`return_date`),
  INDEX (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 22. SALE RETURN ITEMS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sale_return_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `return_id` INT NOT NULL,
  `sale_return_id` INT NULL,
  `product_id` INT NOT NULL,
  `batch_no` VARCHAR(100) NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_price` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `condition` VARCHAR(50) DEFAULT 'Good',
  INDEX (`return_id`),
  INDEX (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 23. PURCHASES (INWARD BILLS HEADER)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchases` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `bill_no` VARCHAR(100) NOT NULL,
  `purchase_date` DATE NOT NULL,
  `receiving_date` DATE NOT NULL,
  `supplier_id` INT NOT NULL,
  `po_id` INT NULL,
  `subtotal` DECIMAL(14,2) DEFAULT 0.00,
  `discount_amount` DECIMAL(12,2) DEFAULT 0.00,
  `tax_amount` DECIMAL(12,2) DEFAULT 0.00,
  `freight_charges` DECIMAL(12,2) DEFAULT 0.00,
  `grand_total` DECIMAL(14,2) DEFAULT 0.00,
  `paid_amount` DECIMAL(14,2) DEFAULT 0.00,
  `balance_amount` DECIMAL(14,2) DEFAULT 0.00,
  `payment_type` ENUM('Cash', 'Credit', 'Bank') DEFAULT 'Credit',
  `payment_status` ENUM('Paid', 'Partial', 'Unpaid') DEFAULT 'Unpaid',
  `status` ENUM('Received', 'Pending') DEFAULT 'Received',
  `notes` TEXT NULL,
  `created_by` INT NULL,
  INDEX (`bill_no`),
  INDEX (`supplier_id`),
  INDEX (`purchase_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 24. PURCHASE ITEMS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchase_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `purchase_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `batch_no` VARCHAR(100) NOT NULL,
  `expiry_date` DATE NOT NULL,
  `quantity` INT NOT NULL,
  `raw_quantity` INT NULL,
  `unit_type` VARCHAR(50) DEFAULT 'Pack',
  `bonus_quantity` INT DEFAULT 0,
  `purchase_price` DECIMAL(12,2) NOT NULL,
  `trade_price` DECIMAL(12,2) NOT NULL,
  `retail_price` DECIMAL(12,2) NOT NULL,
  `discount_percent` DECIMAL(5,2) DEFAULT 0.00,
  `discount_amount` DECIMAL(12,2) DEFAULT 0.00,
  `total_price` DECIMAL(14,2) NOT NULL,
  INDEX (`purchase_id`),
  INDEX (`product_id`),
  INDEX (`batch_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 25. PURCHASE RETURNS (HEADER)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchase_returns` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `return_no` VARCHAR(50) NOT NULL UNIQUE,
  `purchase_id` INT NULL,
  `supplier_id` INT NOT NULL,
  `return_date` DATE NOT NULL,
  `total_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `reason` TEXT NULL,
  `payment_method` VARCHAR(50) NULL,
  `cash_account_id` INT NULL,
  `bank_account_id` INT NULL,
  `status` ENUM('Completed', 'Pending', 'Cancelled') DEFAULT 'Completed',
  `created_by` INT NULL,
  INDEX (`return_no`),
  INDEX (`supplier_id`),
  INDEX (`return_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 26. PURCHASE RETURN ITEMS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchase_return_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `purchase_return_id` INT NOT NULL,
  `purchase_item_id` INT NULL,
  `product_id` INT NOT NULL,
  `batch_no` VARCHAR(100) NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `purchase_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_price` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `return_condition` VARCHAR(50) DEFAULT 'Good',
  INDEX (`purchase_return_id`),
  INDEX (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 27. BANK ACCOUNTS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bank_accounts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `bank_name` VARCHAR(150) NOT NULL,
  `account_title` VARCHAR(150) NOT NULL,
  `account_number` VARCHAR(100) NOT NULL,
  `branch_name` VARCHAR(150) NULL,
  `opening_balance` DECIMAL(14,2) DEFAULT 0.00,
  `current_balance` DECIMAL(14,2) DEFAULT 0.00,
  `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
  INDEX (`bank_name`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 28. CASH ACCOUNTS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cash_accounts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `account_name` VARCHAR(150) NOT NULL,
  `account_type` ENUM('Cash', 'Petty Cash') DEFAULT 'Cash',
  `balance` DECIMAL(14,2) DEFAULT 0.00,
  `is_default` TINYINT(1) DEFAULT 0,
  `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
  INDEX (`account_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 29. ACCOUNT LEDGERS (CASH & BANK PASSBOOK)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `account_ledgers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `account_type` ENUM('Cash', 'Bank') NOT NULL,
  `account_id` INT NOT NULL,
  `transaction_date` DATE NOT NULL,
  `transaction_type` VARCHAR(50) NOT NULL,
  `reference_no` VARCHAR(100) NULL,
  `debit_amount` DECIMAL(14,2) DEFAULT 0.00,
  `credit_amount` DECIMAL(14,2) DEFAULT 0.00,
  `running_balance` DECIMAL(14,2) DEFAULT 0.00,
  `description` TEXT NULL,
  INDEX (`account_type`, `account_id`),
  INDEX (`transaction_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 30. CASH BOOK TRANSACTIONS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cash_book_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `transaction_date` DATE NOT NULL,
  `account_type` VARCHAR(50) NULL,
  `type` ENUM('Income', 'Expense', 'Transfer_In', 'Transfer_Out', 'Debit', 'Credit') DEFAULT 'Income',
  `source_module` VARCHAR(100) NULL,
  `voucher_no` VARCHAR(100) NULL,
  `party_name` VARCHAR(150) NULL,
  `amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `description` TEXT NULL,
  INDEX (`transaction_date`),
  INDEX (`voucher_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 31. UDHAAR / CREDIT BOOK
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `udhaar_book` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `party_name` VARCHAR(150) NOT NULL,
  `invoice_no` VARCHAR(100) NULL,
  `invoice_id` INT NULL,
  `amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `type` ENUM('Payable', 'Receivable') DEFAULT 'Receivable',
  `credit_date` DATE NOT NULL,
  `description` TEXT NULL,
  INDEX (`party_name`),
  INDEX (`invoice_no`),
  INDEX (`credit_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 32. EXPENSE CATEGORIES
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `expense_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
  INDEX (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 33. EXPENSES
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `voucher_no` VARCHAR(50) UNIQUE NOT NULL,
  `expense_date` DATE NOT NULL,
  `category_id` INT NULL,
  `expense_category` VARCHAR(150) NULL,
  `title` VARCHAR(200) NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `payment_method` ENUM('Cash', 'Bank') DEFAULT 'Cash',
  `cash_account_id` INT NULL,
  `bank_account_id` INT NULL,
  `paid_to` VARCHAR(150) NULL,
  `payee_name` VARCHAR(150) NULL,
  `description` TEXT NULL,
  `receipt_no` VARCHAR(100) NULL,
  `created_by` INT NULL,
  INDEX (`voucher_no`),
  INDEX (`category_id`),
  INDEX (`expense_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 34. DELIVERY RIDERS & VANS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `delivery_riders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `rider_name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(50) NULL,
  `vehicle_no` VARCHAR(100) NOT NULL,
  `vehicle_type` VARCHAR(50) DEFAULT 'Delivery Van',
  `cnic` VARCHAR(50) NULL,
  `address` TEXT NULL,
  `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
  INDEX (`rider_name`),
  INDEX (`vehicle_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 35. DELIVERY CHALLANS (GATE PASS HEADER)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `delivery_challans` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `challan_no` VARCHAR(50) NOT NULL UNIQUE,
  `invoice_id` INT NULL,
  `invoice_no` VARCHAR(50) NULL,
  `challan_date` DATE NOT NULL,
  `customer_name` VARCHAR(150) NOT NULL,
  `customer_phone` VARCHAR(50) NULL,
  `delivery_address` TEXT NULL,
  `route_name` VARCHAR(150) NULL,
  `booker_name` VARCHAR(150) NULL,
  `vehicle_no` VARCHAR(100) NULL,
  `driver_name` VARCHAR(100) NULL,
  `driver_phone` VARCHAR(50) NULL,
  `total_cartons` INT DEFAULT 1,
  `total_items` INT DEFAULT 0,
  `total_quantity` INT DEFAULT 0,
  `delivery_status` ENUM('Pending', 'In Transit', 'Dispatched', 'Delivered', 'Cancelled') DEFAULT 'Dispatched',
  `notes` TEXT NULL,
  `created_by` INT NULL,
  INDEX (`challan_no`),
  INDEX (`invoice_id`),
  INDEX (`challan_date`),
  INDEX (`delivery_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 36. DELIVERY CHALLAN ITEMS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `delivery_challan_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `challan_id` INT NOT NULL,
  `product_id` INT NULL,
  `item_name` VARCHAR(200) NOT NULL,
  `batch_no` VARCHAR(100) NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `unit_type` VARCHAR(50) DEFAULT 'Pack',
  `remarks` VARCHAR(255) NULL,
  INDEX (`challan_id`),
  INDEX (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 37. STAFF / EMPLOYEES DIRECTORY
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `staff` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_code` VARCHAR(50) UNIQUE NULL,
  `name` VARCHAR(150) NOT NULL,
  `role` VARCHAR(100) DEFAULT 'Staff',
  `designation` VARCHAR(100) NULL,
  `phone` VARCHAR(50) NULL,
  `cnic` VARCHAR(50) NULL,
  `salary` DECIMAL(12,2) DEFAULT 0.00,
  `commission_rate` DECIMAL(5,2) DEFAULT 0.00,
  `address` TEXT NULL,
  `joining_date` DATE NULL,
  `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
  INDEX (`name`),
  INDEX (`role`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ---------------------------------------------------------------------
-- 38. EMPLOYEES (GENERAL STAFF / SALESMEN - COMMISSION BASED)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `employees` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `emp_code` VARCHAR(50) NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `employee_type` ENUM('general', 'salesman') NOT NULL DEFAULT 'salesman',
  `phone` VARCHAR(20) NULL,
  `area` VARCHAR(255) NULL,
  `cnic` VARCHAR(30) NULL,
  `address` VARCHAR(255) NULL,
  `joining_date` DATE NULL,
  `salary` DECIMAL(12,2) DEFAULT 0.00,
  `commission_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `status` TINYINT(1) DEFAULT 1,
  `created_at` DATE NOT NULL,
  `updated_at` DATE NULL,
  INDEX (`user_id`),
  INDEX (`employee_type`),
  INDEX (`status`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 39. EMPLOYEE SALARIES / PAY SLIPS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `employee_salaries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slip_no` VARCHAR(50) NOT NULL,
  `employee_id` INT NOT NULL,
  `salary_month` VARCHAR(7) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `payment_method` ENUM('cash', 'bank') DEFAULT 'cash',
  `bank_account_id` INT NULL,
  `payment_date` DATE NOT NULL,
  `notes` TEXT NULL,
  `created_by` INT NULL,
  `created_at` DATE NOT NULL,
  INDEX (`employee_id`),
  INDEX (`bank_account_id`),
  INDEX (`created_by`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 40. ACTIVITY LOGS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` VARCHAR(100) NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `reference_id` INT NULL,
  `description` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATE NOT NULL,
  INDEX (`user_id`),
  INDEX (`module`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 41. BANK TRANSACTIONS (PASSBOOK)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bank_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `bank_account_id` INT NOT NULL,
  `transaction_date` DATE NOT NULL,
  `transaction_type` ENUM('deposit', 'withdrawal', 'transfer_in', 'transfer_out') NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `description` TEXT NULL,
  `reference_type` VARCHAR(50) NULL,
  `reference_id` INT NULL,
  `created_by` INT NULL,
  `created_at` DATE NOT NULL,
  INDEX (`bank_account_id`),
  INDEX (`created_by`),
  INDEX (`transaction_date`),
  FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 42. CASH BOOK DAILY
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cash_book_daily` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `date` DATE NOT NULL,
  `opening_balance` DECIMAL(12,2) DEFAULT 0.00,
  `total_inflow` DECIMAL(12,2) DEFAULT 0.00,
  `total_outflow` DECIMAL(12,2) DEFAULT 0.00,
  `closing_balance` DECIMAL(12,2) DEFAULT 0.00,
  `status` ENUM('open', 'closed') DEFAULT 'open',
  `created_by` INT NULL,
  `created_at` DATE NOT NULL,
  `updated_at` DATE NULL,
  UNIQUE KEY `date` (`date`),
  INDEX (`created_by`),
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 43. CASH BOOK
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cash_book` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `daily_id` INT NULL,
  `transaction_date` DATE NOT NULL,
  `transaction_type` ENUM('opening_balance', 'inflow', 'outflow', 'closing_balance') NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `description` TEXT NULL,
  `reference_type` VARCHAR(50) NULL,
  `reference_id` INT NULL,
  `created_by` INT NULL,
  `created_at` DATE NOT NULL,
  INDEX (`daily_id`),
  INDEX (`created_by`),
  INDEX (`transaction_date`),
  FOREIGN KEY (`daily_id`) REFERENCES `cash_book_daily` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 44. BRANDS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `brands` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `status` TINYINT(1) DEFAULT 1,
  `created_at` DATE NOT NULL,
  `updated_at` DATE NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 45. BRANCHES
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `branches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `address` TEXT NULL,
  `phone` VARCHAR(20) NULL,
  `status` TINYINT(1) DEFAULT 1,
  `created_at` DATE NOT NULL,
  `updated_at` DATE NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 46. CUSTOMER RECEIPTS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customer_receipts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT NOT NULL,
  `sale_id` INT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `payment_method` ENUM('cash', 'bank') DEFAULT 'cash',
  `bank_account_id` INT NULL,
  `description` TEXT NULL,
  `receipt_date` DATE NOT NULL,
  `created_by` INT NULL,
  `created_at` DATE NOT NULL,
  INDEX (`customer_id`),
  INDEX (`sale_id`),
  INDEX (`bank_account_id`),
  INDEX (`created_by`),
  INDEX (`receipt_date`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


SET FOREIGN_KEY_CHECKS = 1;

