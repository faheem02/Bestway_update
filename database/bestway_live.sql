-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 08, 2026 at 10:10 AM
-- Server version: 8.0.46-cll-lve
-- PHP Version: 8.4.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `atrmarke_BestWay_dis`
--

-- --------------------------------------------------------

--
-- Table structure for table `account_ledgers`
--

CREATE TABLE `account_ledgers` (
  `id` int NOT NULL,
  `account_type` enum('Cash','Bank') COLLATE utf8mb4_general_ci NOT NULL,
  `account_id` int NOT NULL,
  `transaction_date` date NOT NULL,
  `transaction_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `reference_no` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `debit_amount` decimal(14,2) DEFAULT '0.00',
  `credit_amount` decimal(14,2) DEFAULT '0.00',
  `running_balance` decimal(14,2) DEFAULT '0.00',
  `description` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `module` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `reference_id` int DEFAULT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `module`, `reference_id`, `description`, `ip_address`, `created_at`) VALUES
(1, 1, 'create', 'expense_category', NULL, 'Created expense category: Flat Rent', NULL, '2026-10-01'),
(2, 1, 'create', 'expense_category', NULL, 'Created expense category: D. Pharmacist', NULL, '2026-10-01'),
(3, 1, 'create', 'expense_category', NULL, 'Created expense category: Electric Bill', NULL, '2026-10-01'),
(4, 1, 'create', 'expense_category', NULL, 'Created expense category: Fuel ⛽', NULL, '2026-10-07');

-- --------------------------------------------------------

--
-- Table structure for table `areas`
--

CREATE TABLE `areas` (
  `id` int NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `city` varchar(100) COLLATE utf8mb4_general_ci DEFAULT 'Lahore',
  `description` text COLLATE utf8mb4_general_ci,
  `status` tinyint(1) DEFAULT '1',
  `created_at` date DEFAULT NULL,
  `updated_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `areas`
--

INSERT INTO `areas` (`id`, `name`, `city`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Sanda Kalan', 'Lahore', 'Mohny Road*Sheesh Mehal Road*Sanat Nager* Sanda* Out fall Road', 1, '2026-09-29', NULL),
(2, 'Shahdra', 'Lahore', '', 1, '2026-09-29', '2026-09-29'),
(3, 'Imamia Colony', 'Lahore', '', 1, '2026-09-29', NULL),
(4, 'Rana Town', 'Lahore', '', 1, '2026-09-29', NULL),
(5, 'Kala Khatai Morr', 'Lahore', '', 1, '2026-09-29', NULL),
(6, 'Begum Kot', 'Lahore', 'Gia Mussa', 1, '2026-09-29', '2026-09-29'),
(7, 'Kot Abdul Malik', 'Lahore', '', 1, '2026-09-29', NULL),
(8, 'Badami bagh', 'Lahore', 'lohy Waly Pylly-Misree Shah-Hanif Park -Sadaat Colony', 1, '2026-09-30', NULL),
(9, 'Walton', 'Lahore', 'Main Road -Kot lakhpat', 1, '2026-09-30', NULL),
(10, 'Abid Market', 'Lahore', 'Tample road -Abid Market -Abid market Road', 1, '2026-09-30', NULL),
(11, 'China Scheme', 'Lahore', '', 1, '2026-09-30', NULL),
(12, 'ARG', 'Lahore', '', 1, '2026-10-06', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `bank_accounts`
--

CREATE TABLE `bank_accounts` (
  `id` int NOT NULL,
  `bank_name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `account_title` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `account_number` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `branch_name` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `opening_balance` decimal(14,2) DEFAULT '0.00',
  `opening_date` date DEFAULT NULL,
  `current_balance` decimal(14,2) DEFAULT '0.00',
  `status` enum('Active','Inactive') COLLATE utf8mb4_general_ci DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bank_transactions`
--

CREATE TABLE `bank_transactions` (
  `id` int NOT NULL,
  `bank_account_id` int NOT NULL,
  `transaction_date` date NOT NULL,
  `transaction_type` enum('deposit','withdrawal','transfer_in','transfer_out') COLLATE utf8mb4_general_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `reference_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reference_id` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bookers`
--

CREATE TABLE `bookers` (
  `id` int NOT NULL,
  `booker_code` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `phone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_general_ci,
  `commission_rate` decimal(5,2) DEFAULT '0.00',
  `opening_balance` decimal(14,2) DEFAULT '0.00',
  `current_balance` decimal(14,2) DEFAULT '0.00',
  `status` enum('Active','Inactive') COLLATE utf8mb4_general_ci DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `booker_ledgers`
--

CREATE TABLE `booker_ledgers` (
  `id` int NOT NULL,
  `booker_id` int NOT NULL,
  `transaction_date` date NOT NULL,
  `transaction_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `reference_no` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `debit_amount` decimal(14,2) DEFAULT '0.00',
  `credit_amount` decimal(14,2) DEFAULT '0.00',
  `running_balance` decimal(14,2) DEFAULT '0.00',
  `description` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `address` text COLLATE utf8mb4_general_ci,
  `phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `created_at` date NOT NULL,
  `updated_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `brands`
--

CREATE TABLE `brands` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `status` tinyint(1) DEFAULT '1',
  `created_at` date NOT NULL,
  `updated_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cash_accounts`
--

CREATE TABLE `cash_accounts` (
  `id` int NOT NULL,
  `account_name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `account_type` enum('Cash','Petty Cash') COLLATE utf8mb4_general_ci DEFAULT 'Cash',
  `balance` decimal(14,2) DEFAULT '0.00',
  `is_default` tinyint(1) DEFAULT '0',
  `status` enum('Active','Inactive') COLLATE utf8mb4_general_ci DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cash_book`
--

CREATE TABLE `cash_book` (
  `id` int NOT NULL,
  `daily_id` int DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `transaction_type` enum('opening_balance','inflow','outflow','closing_balance') COLLATE utf8mb4_general_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `reference_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reference_id` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cash_book`
--

INSERT INTO `cash_book` (`id`, `daily_id`, `transaction_date`, `transaction_type`, `amount`, `description`, `reference_type`, `reference_id`, `created_by`, `created_at`) VALUES
(1, 1, '2026-10-01', 'outflow', 30000.00, 'Expense: Monthly Rent', 'expense', 1, 1, '2026-10-01'),
(2, 1, '2026-10-01', 'outflow', 17000.00, 'Expense: Monthly Salary', 'expense', 2, 1, '2026-10-01'),
(4, 1, '2026-10-01', 'outflow', 1700.00, 'Expense: Monthly E. Bill', 'expense', 3, 1, '2026-10-01'),
(5, 2, '2026-10-07', 'outflow', 5500.00, 'Expense: Asad', 'expense', 4, 1, '2026-10-07');

-- --------------------------------------------------------

--
-- Table structure for table `cash_book_daily`
--

CREATE TABLE `cash_book_daily` (
  `id` int NOT NULL,
  `date` date NOT NULL,
  `opening_balance` decimal(12,2) DEFAULT '0.00',
  `total_inflow` decimal(12,2) DEFAULT '0.00',
  `total_outflow` decimal(12,2) DEFAULT '0.00',
  `closing_balance` decimal(12,2) DEFAULT '0.00',
  `status` enum('open','closed') COLLATE utf8mb4_general_ci DEFAULT 'open',
  `created_by` int DEFAULT NULL,
  `created_at` date NOT NULL,
  `updated_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cash_book_daily`
--

INSERT INTO `cash_book_daily` (`id`, `date`, `opening_balance`, `total_inflow`, `total_outflow`, `closing_balance`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, '2026-10-01', 0.00, 0.00, 48700.00, -48700.00, 'open', 1, '2026-10-01', NULL),
(2, '2026-10-07', 0.00, 0.00, 5500.00, -5500.00, 'open', 1, '2026-10-07', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `cash_book_transactions`
--

CREATE TABLE `cash_book_transactions` (
  `id` int NOT NULL,
  `transaction_date` date NOT NULL,
  `account_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `type` enum('Income','Expense','Transfer_In','Transfer_Out','Debit','Credit') COLLATE utf8mb4_general_ci DEFAULT 'Income',
  `source_module` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `voucher_no` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `party_name` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `description` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `status` enum('Active','Inactive') COLLATE utf8mb4_general_ci DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `code`, `description`, `status`) VALUES
(1, 'Cream', NULL, NULL, 'Active'),
(2, 'Cap', NULL, NULL, 'Active'),
(3, 'Tab', NULL, NULL, 'Active'),
(4, 'Inj', NULL, NULL, 'Active'),
(5, 'Syp', NULL, NULL, 'Active'),
(6, 'Spray', NULL, NULL, 'Active'),
(7, 'Oint', NULL, NULL, 'Active'),
(8, 'Sachets', NULL, NULL, 'Active'),
(9, 'Drop', NULL, NULL, 'Active'),
(10, 'Lotion', NULL, NULL, 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` int NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `contact_person` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_general_ci,
  `status` enum('Active','Inactive') COLLATE utf8mb4_general_ci DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `company_settings`
--

CREATE TABLE `company_settings` (
  `id` int NOT NULL,
  `business_name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Bestway Distribution',
  `tagline` varchar(255) COLLATE utf8mb4_general_ci DEFAULT 'Wholesale Medicine & Pharma Distribution',
  `logo_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT 'assets/images/logo.png',
  `phone` varchar(100) COLLATE utf8mb4_general_ci DEFAULT '0300-1234567 / 0321-7654321',
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT 'info@bestwaypharma.com',
  `address` text COLLATE utf8mb4_general_ci,
  `ntn_no` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `strn_no` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `invoice_footer_notes` text COLLATE utf8mb4_general_ci,
  `drug_license_no` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `drug_license_valid_upto` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int NOT NULL,
  `customer_code` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `shop_name` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `phone` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `address` text COLLATE utf8mb4_general_ci,
  `route_id` int DEFAULT NULL,
  `area` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `opening_balance` decimal(14,2) DEFAULT '0.00',
  `current_balance` decimal(14,2) DEFAULT '0.00',
  `status` enum('Active','Inactive') COLLATE utf8mb4_general_ci DEFAULT 'Active',
  `invoice_type` enum('sale','warranty') COLLATE utf8mb4_general_ci DEFAULT 'sale',
  `created_at` date DEFAULT NULL,
  `license_number` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `customer_code`, `name`, `shop_name`, `phone`, `address`, `route_id`, `area`, `opening_balance`, `current_balance`, `status`, `invoice_type`, `created_at`, `license_number`) VALUES
(1, 'C-0001', 'Nayab Haider Shah', 'Haider Pharmacy', '0324_4004056', 'Mohny Road Lahore', NULL, 'Sanda kalan', 0.00, 18352.92, 'Active', 'warranty', NULL, '05-352-0063-065552P'),
(3, 'C-0002', 'Imran Azher', 'Imran Medicos', '0300-4339275', 'Ameen Park', NULL, 'Sanda Kalan', 0.00, 0.00, 'Active', 'warranty', NULL, '05-352-0063-104086M'),
(4, 'C-0003', 'Azher Hussain', 'Mughal Pharmacy', '03004480486', 'Ameen Park', NULL, 'Sanda Kalan', 0.00, 5062.63, 'Active', 'warranty', NULL, '05-352-0063072811P'),
(5, 'C-0004', 'Aqib', 'Liaquit Sons Pharmacy', '03080426690', 'Ameen Park', NULL, 'Sanda Kalan', 0.00, 15777.28, 'Active', 'warranty', NULL, '05-352-0063-126959P'),
(6, 'C-0005', 'Usman', 'Saeeda M/S', '03238496927', 'Ameen Park', NULL, 'Sanda Kalan', 0.00, 447.79, 'Active', 'warranty', NULL, '05-352-0063-96814M'),
(7, 'C-0006', 'Moon', 'Moon M/S', '03334289503', 'Kareem Park', NULL, 'Sanda Kalan', 0.00, 2159.77, 'Active', 'warranty', NULL, '05-352-0063-022345M'),
(8, 'C-0007', 'Ammar', 'Medisafe Pharmacy', '03075413411', 'Kasur Pura Bazar', NULL, 'Sanda Kalan', 0.00, 2289.77, 'Active', 'warranty', NULL, '05-352-0062-1393513P'),
(9, 'C-0008', 'Usama', 'New Green Pharmacy', '03044835856', 'Kasur Pura Bazar', NULL, 'Sanda Kalan', 0.00, 6084.26, 'Active', 'warranty', NULL, '05-352-0062-140056P'),
(10, 'C-0009', 'Malik Tanveer Haider', 'New Malik medical store', '0333 4436687', 'Etihad chemical factory opposite', NULL, 'Rana Town', 0.00, 2606.20, 'Active', 'sale', NULL, NULL),
(11, 'C-0010', 'Shahid', 'Malik M/S', '0300-9470100', '', NULL, 'Sanda Kalan', 0.00, 5626.65, 'Active', 'warranty', NULL, '05-352-0063-022517M'),
(12, 'C-0011', 'Mian Shehzad', 'Mian M/S', '03217775056', 'Kareem Park Bazar', NULL, 'Sanda Kalan', 0.00, 0.00, 'Active', 'warranty', NULL, '05-352-0063-025432M'),
(13, 'C-0012', 'Shahid SB', 'Haider healthcare pharmacy', '0314 7980484', 'Ravi Rian gt road', NULL, 'Rana Town', 0.00, 3591.47, 'Active', 'warranty', NULL, '05-354-0077-108482p'),
(14, 'C-0013', 'Faizan', 'Qartaba M/S', '03236045413', 'Ameen Park', NULL, 'Sanda Kalan', 0.00, 1461.51, 'Active', 'warranty', NULL, '05-352-0063-043781M'),
(15, 'C-0014', 'Shakeel Sheikh', 'Sheikh M/S', '03246574251', 'Ameen Park', NULL, 'Sanda Kalan', 0.00, 1962.97, 'Active', 'warranty', NULL, '05-352-0063-90270M'),
(16, 'C-0015', 'Talha Alyas', 'Madina Pharmacy', '03214096607', 'Mohny Road', NULL, 'Sanda Kalan', 0.00, 0.00, 'Active', 'warranty', NULL, '05-352-0063-99060P'),
(17, 'C-0016', 'Muhammad Zahid', 'Hijab medical store', '0308 4537238', 'Haider road Rana Town', NULL, 'Rana Town', 0.00, 13326.46, 'Active', 'warranty', NULL, '05-354-0078-122875'),
(18, 'C-0017', 'Sohail', 'Sohail pharmacy', '0300 4831918', 'Haider road Rana Town', NULL, 'Rana Town', 0.00, 2564.49, 'Active', 'sale', NULL, NULL),
(19, 'C-0018', 'Ali', 'New Shahid medical stire', '0300 8141806', 'Haider road Rana Town', NULL, 'Rana Town', 0.00, 2006.21, 'Active', 'warranty', NULL, '05-354-0078-87612'),
(20, 'C-0019', 'Unique', 'Unique pharmacy', '0310 4841122', 'College road Rana Town', NULL, 'Rana Town', 0.00, 1892.20, 'Active', 'sale', NULL, NULL),
(21, 'C-0020', 'Raza sb', 'Raza medical and gernal store', '0309 4479314', 'College road Rana Town', NULL, 'Rana Town', 0.00, 2608.90, 'Active', 'warranty', NULL, '05354-0078058699m'),
(22, 'C-0021', 'Bilal', 'Javeed sons pharmacy', '0305 8771561', 'Rachana town Bazar', NULL, 'Rana Town', 0.00, 2972.51, 'Active', 'warranty', NULL, '05-354-0078-138928p'),
(23, 'C-0022', 'Waseem', 'Waseem pharmacy', '0300 1312025', 'Rachana town Bazar', NULL, 'Rana Town', 0.00, 7597.84, 'Active', 'warranty', NULL, '05-354-0078-112938p'),
(24, 'C-0023', 'Hafiz Ayaz', 'Hafiz Pharmacy', '03134091988', 'Sanat Nager  Usman Ghani RD', NULL, 'Sanda Kalan', 0.00, 2871.64, 'Active', 'warranty', NULL, '05-352-0063-013037M'),
(25, 'C-0024', 'Asif', 'Asif Pharmacy', '03214770411', 'Outfall Road', NULL, 'Sanda Kalan', 0.00, 0.00, 'Active', 'warranty', NULL, '05-352-0063-88581P'),
(26, 'C-0025', 'Asif', 'Asif Pharmacy', '03214770411', 'Outfall Road', NULL, 'Sanda Kalan', 0.00, 0.00, 'Active', 'warranty', NULL, '05-352-0063-88581P'),
(27, 'C-0026', 'Ch mubashir ahmad', 'Khadam and sons pharmacy', '0304 9697002', 'Rana Town gt road', NULL, 'Rana Town', 0.00, 6327.13, 'Active', 'sale', NULL, NULL),
(28, 'C-0027', 'Sher Afzal', 'Khan M/S', '03032870287', 'Outfall Road', NULL, 'Sanda Kalan', 0.00, 2180.27, 'Active', 'warranty', NULL, '05-352-0063-115416M'),
(29, 'C-0028', 'Nouman', 'Smart Care Pharmacy', '03224580979', 'Sanda Akhry Bus stop', NULL, 'Sanda Kalan', 0.00, 2702.14, 'Active', 'warranty', NULL, '05-352-0063-120850P'),
(30, 'C-0029', 'Zaheer', 'Nazir Sons Pharmacy', '03334992227', 'Sanda Main Road', NULL, 'Sanda Kalan', 0.00, 1561.37, 'Active', 'warranty', NULL, '05-352-0863-061313P'),
(31, 'C-0030', 'Sameer', 'Saad Medical Store', '03099634214', 'Rajjgar', NULL, 'Sanda Kalan', 0.00, 2800.02, 'Active', 'warranty', NULL, '05-352-0063-103669M'),
(32, 'C-0031', 'Talib Hussain', 'Fiazan Pharmacy', '03020587071', 'Rajjgar', NULL, 'Sanda Kalan', 0.00, 559.80, 'Active', 'warranty', NULL, '05-352-0063-10121P'),
(33, 'C-0032', 'M Iqbal', 'Medicare pharmacy', '03066666465', 'Umer Road Islampura', NULL, 'Sanda Kalan', 0.00, 7825.34, 'Active', 'warranty', NULL, '05-652-0063-072186P'),
(34, 'C-0033', 'Haider Sultan', 'Mediprime Pharmacy', '03254444356', 'Abdali Road', NULL, 'Sanda Kalan', 0.00, 2562.45, 'Active', 'warranty', NULL, '05-352-0063-134808P'),
(35, 'C-0034', 'Imran', 'Bismillah Pharmacy', '03004571110', 'Abdali Chowk', NULL, 'Sanda Kalan', 0.00, 1560.69, 'Active', 'warranty', NULL, '05-352-0063-98235P'),
(36, 'C-0035', 'Shehbaz', 'Riaz M/S', '03154620041', 'Islampura Main Bazaar', NULL, 'Sanda Kalan', 0.00, 935.81, 'Active', 'warranty', NULL, '05-352-0063-031981DIS'),
(37, 'C-0036', 'Arslan', 'New Mughal Pharmacy', '03204039486', 'Malik Park', NULL, 'Sanda Kalan', 0.00, 2903.31, 'Active', 'warranty', NULL, '05-352-0063-028287P'),
(38, 'C-0037', 'M Saeed', 'Al Shifa Pharmacy', '0322-4148841', 'Hafeez Road', NULL, 'Sanda Kalan', 0.00, 1359.92, 'Active', 'warranty', NULL, '05-352-0063-118069P'),
(39, 'C-0038', 'Khuram', 'Kamran M/S', '03214124136', 'Hafeez Raod', NULL, 'Sanda Kalan', 0.00, 2375.94, 'Active', 'warranty', NULL, '05-352-0062-0072M'),
(40, 'C-0039', 'Fida hussain', 'Bismillah medical store', '0301 4215590', 'Khala khati morr pathak', NULL, 'Kala Khatai Morr', 0.00, 2061.36, 'Active', 'warranty', NULL, '05-352-0062-96963m'),
(41, 'C-0040', 'Imran', 'Imran medical store', '0322 4648722', 'Kala khatai road', NULL, 'Kala Khatai Morr', 0.00, 2866.29, 'Active', 'warranty', NULL, '05-352-0062-038827m'),
(42, 'C-0041', 'Muhammad tayyab', 'Tayyab pharmacy', '0317 4596996', 'Qaiser town kala khatai moor', NULL, 'Kala Khatai Morr', 0.00, 1680.39, 'Active', 'warranty', NULL, '05-352-0062-126735p'),
(43, 'C-0042', 'Lassni', 'Lassni medical store immima colony', '0321 4714981', 'Imamia colony Bazar', NULL, 'Kala Khatai Morr', 0.00, 10363.59, 'Active', 'sale', NULL, NULL),
(44, 'C-0043', 'Pak', 'Pak online pharmacy imamia colony', '0321 1001028', '', NULL, 'Kala Khatai Morr', 0.00, 2981.37, 'Active', 'sale', NULL, NULL),
(45, 'C-0044', 'Hamza', 'Green pharmacy imamia colony', '0318 4063306', 'Imamia colony Bazar', NULL, 'Kala Khatai Morr', 0.00, 2469.16, 'Active', 'warranty', NULL, '05-353-4063306p'),
(46, 'C-0045', 'Syed', 'Al syed pharmacy imamia colony', '0331 4687953', '', NULL, 'Kala Khatai Morr', 0.00, 8727.69, 'Active', 'sale', NULL, NULL),
(47, 'C-0046', 'Naveed anjum', 'H fareed sons pharmacy', '0305 7709446', 'Imamia colony Bazar', NULL, 'Kala Khatai Morr', 0.00, 1030.91, 'Active', 'warranty', NULL, '05-354-0078-113862m'),
(48, 'C-0047', 'Habbib', 'Gravity Plus pharmacy feroz wala', '0318 4350893', 'Feroz wala Bazar', NULL, 'Kala Khatai Morr', 0.00, 886.19, 'Active', 'warranty', NULL, '05-354-0078-97129p'),
(49, 'C-0048', 'Nouman Asif', 'Nouman Pharmacy', '03120459757', 'I Block Market', NULL, 'ARG', 0.00, 8295.66, 'Active', 'warranty', NULL, '05-354-0078-073031P'),
(50, 'C-0049', 'Qammar Zaman', 'QS Medical & Cosmetics Store', '0301-4882787', 'Main Peko Road', NULL, 'Walton', 0.00, 10001.44, 'Active', 'warranty', NULL, '05-352-0065-9885676M'),
(51, 'C-0050', 'Makhdoom Mumtaz', 'Makhdoom Pharmacy', '03214700003', 'Karmawala Main Bazar', NULL, 'Walton', 0.00, 5062.22, 'Active', 'warranty', NULL, '05-352-0065-88711P'),
(52, 'C-0051', 'Imran', 'Madina Pharmacy', '03133121010', 'Karmawala Main Bazar', NULL, 'Walton', 0.00, 5370.46, 'Active', 'warranty', NULL, '05-352-0065-034463P'),
(53, 'C-0052', 'M.Tahir Islam', 'Ahsen Pharmacy', '03064231363', 'Karmawala Main Bazar', NULL, 'Walton', 0.00, 0.00, 'Active', 'warranty', NULL, '05-352-0065-106832P'),
(54, 'C-0053', 'Kashif', 'Kashif M/S', '0317-4110300', 'Karmawala Main Bazar', NULL, 'Walton', 0.00, 5883.25, 'Active', 'warranty', NULL, '05-352-0065-026126M'),
(55, 'C-0054', 'Wasim', 'Wasim M/S', '03463798707', 'Karmawala Main Bazar', NULL, 'Walton', 0.00, 10692.48, 'Active', 'warranty', NULL, '05-352-0065-131580M'),
(56, 'C-0055', 'Ali Raza', 'Fine M/S', '03004182260', 'Karmawala Main Bazar', NULL, 'Walton', 0.00, 3038.60, 'Active', 'warranty', NULL, '05-352-0065-028144D'),
(57, 'C-0056', 'Malik Nasir', 'Malik M/S', '03214594733', 'Najaf Bazar (Behind karmawala Bazar)', NULL, 'Walton', 0.00, 0.00, 'Active', 'warranty', NULL, '05-352-0065-0523DIS'),
(58, 'C-0057', 'Asif', 'Rehman Pharmacy', '0301-4940313', 'Main Bazar Pindi Stop', NULL, 'Walton', 0.00, 4436.94, 'Active', 'warranty', NULL, '05-352-0070-036975D'),
(59, 'C-0058', 'Fareed', 'Fareed M/S', '03214376650', '05-352-0065-0226M', NULL, 'Walton', 0.00, 0.00, 'Active', 'warranty', NULL, NULL),
(60, 'C-0059', 'Bilal', 'ADD Plus Pharmacy', '03234270425', 'Main Pindi Stop Road', NULL, 'Walton', 0.00, 4255.96, 'Active', 'warranty', NULL, '05-352-0065-135437P'),
(61, 'C-0060', 'Masood Ahmed', 'Al Raziq Pharmacy', '03243215939', 'Main Bazar Gopal Nagar', NULL, 'Walton', 0.00, 1468.00, 'Active', 'warranty', NULL, '05-352-0065-134174P'),
(62, 'C-0061', 'Hammad', 'New Hammad', '0313-4684914', 'Gopal Nager', NULL, 'Walton', 0.00, 17916.63, 'Active', 'warranty', NULL, '05-352-0065-107201M'),
(63, 'C-0062', 'Hafiz Mubeen', 'Hafiz Care Pharmacy', '03006588628', 'Gopal Nager', NULL, 'Walton', 0.00, 9164.72, 'Active', 'warranty', NULL, '05-352-0065-052173P'),
(64, 'C-0063', 'Wasim', 'Health Mart Pharmacy', '03014460070', 'Main Road Abid Market', NULL, 'Abid Market', 0.00, 3014.36, 'Active', 'warranty', NULL, '05-352-0063013985P'),
(65, 'C-0064', 'Asif', 'Service Medicos', '03214263438', 'Main Abid Market Road', NULL, 'Abid Market', 0.00, 6098.04, 'Active', 'warranty', NULL, '05-352-0063-081939P'),
(66, 'C-0065', 'Ubaid', 'Care Medicos', '03354511777', 'Oppsit Abid Market Road', NULL, 'Abid Market', 0.00, 4846.52, 'Active', 'warranty', NULL, '05-352-0063-053727M'),
(67, 'C-0066', 'Ali', 'Muhammed Mustafa', '03204030436', 'Mola Baksh Road', NULL, 'Abid Market', 0.00, 20435.94, 'Active', 'warranty', NULL, '05-352-0063-118494P');

-- --------------------------------------------------------

--
-- Table structure for table `customer_ledgers`
--

CREATE TABLE `customer_ledgers` (
  `id` int NOT NULL,
  `customer_id` int NOT NULL,
  `transaction_date` date NOT NULL,
  `transaction_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `reference_no` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `debit_amount` decimal(14,2) DEFAULT '0.00',
  `credit_amount` decimal(14,2) DEFAULT '0.00',
  `running_balance` decimal(14,2) NOT NULL,
  `description` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_ledgers`
--

INSERT INTO `customer_ledgers` (`id`, `customer_id`, `transaction_date`, `transaction_type`, `reference_no`, `debit_amount`, `credit_amount`, `running_balance`, `description`) VALUES
(11428, 1, '2026-10-05', 'Sale Invoice', 'INV-0011', 15566.11, 0.00, 15566.11, 'Sale Invoice #INV-0011'),
(11429, 1, '2026-10-06', 'Sale Invoice', 'INV-0039', 1962.02, 0.00, 17528.13, 'Sale Invoice #INV-0039'),
(11430, 1, '2026-10-06', 'Sale Invoice', 'INV-0040', 824.79, 0.00, 18352.92, 'Sale Invoice #INV-0040'),
(11431, 4, '2026-10-05', 'Sale Invoice', 'INV-0001', 5062.63, 0.00, 5062.63, 'Sale Invoice #INV-0001'),
(11432, 5, '2026-10-05', 'Sale Invoice', 'INV-0034', 15777.28, 0.00, 15777.28, 'Sale Invoice #INV-0034'),
(11433, 6, '2026-10-05', 'Sale Invoice', 'INV-0002', 447.79, 0.00, 447.79, 'Sale Invoice #INV-0002'),
(11434, 7, '2026-10-05', 'Sale Invoice', 'INV-0003', 2159.77, 0.00, 2159.77, 'Sale Invoice #INV-0003'),
(11435, 8, '2026-10-05', 'Sale Invoice', 'INV-0004', 2289.77, 0.00, 2289.77, 'Sale Invoice #INV-0004'),
(11436, 9, '2026-10-05', 'Sale Invoice', 'INV-0005', 6084.26, 0.00, 6084.26, 'Sale Invoice #INV-0005'),
(11437, 10, '2026-10-05', 'Sale Invoice', 'INV-0006', 2606.20, 0.00, 2606.20, 'Sale Invoice #INV-0006'),
(11438, 11, '2026-10-05', 'Sale Invoice', 'INV-0007', 5626.65, 0.00, 5626.65, 'Sale Invoice #INV-0007'),
(11439, 13, '2026-10-05', 'Sale Invoice', 'INV-0008', 3591.47, 0.00, 3591.47, 'Sale Invoice #INV-0008'),
(11440, 14, '2026-10-05', 'Sale Invoice', 'INV-0009', 1461.51, 0.00, 1461.51, 'Sale Invoice #INV-0009'),
(11441, 15, '2026-10-05', 'Sale Invoice', 'INV-0010', 1962.97, 0.00, 1962.97, 'Sale Invoice #INV-0010'),
(11442, 17, '2026-10-05', 'Sale Invoice', 'INV-0012', 13326.46, 0.00, 13326.46, 'Sale Invoice #INV-0012'),
(11443, 18, '2026-10-05', 'Sale Invoice', 'INV-0013', 2564.49, 0.00, 2564.49, 'Sale Invoice #INV-0013'),
(11444, 19, '2026-10-05', 'Sale Invoice', 'INV-0014', 2006.21, 0.00, 2006.21, 'Sale Invoice #INV-0014'),
(11445, 20, '2026-10-05', 'Sale Invoice', 'INV-0015', 1892.20, 0.00, 1892.20, 'Sale Invoice #INV-0015'),
(11446, 21, '2026-10-05', 'Sale Invoice', 'INV-0016', 2608.90, 0.00, 2608.90, 'Sale Invoice #INV-0016'),
(11447, 22, '2026-10-05', 'Sale Invoice', 'INV-0017', 1071.00, 0.00, 1071.00, 'Sale Invoice #INV-0017'),
(11448, 22, '2026-10-05', 'Sale Invoice', 'INV-0018', 1901.51, 0.00, 2972.51, 'Sale Invoice #INV-0018'),
(11449, 23, '2026-10-05', 'Sale Invoice', 'INV-0019', 3618.13, 0.00, 3618.13, 'Sale Invoice #INV-0019'),
(11450, 23, '2026-10-06', 'Sale Invoice', 'INV-0048', 3979.71, 0.00, 7597.84, 'Sale Invoice #INV-0048'),
(11451, 24, '2026-10-05', 'Sale Invoice', 'INV-0020', 2871.64, 0.00, 2871.64, 'Sale Invoice #INV-0020'),
(11452, 27, '2026-10-05', 'Sale Invoice', 'INV-0022', 6327.13, 0.00, 6327.13, 'Sale Invoice #INV-0022'),
(11453, 28, '2026-10-05', 'Sale Invoice', 'INV-0021', 2180.27, 0.00, 2180.27, 'Sale Invoice #INV-0021'),
(11454, 29, '2026-10-05', 'Sale Invoice', 'INV-0023', 2702.14, 0.00, 2702.14, 'Sale Invoice #INV-0023'),
(11455, 30, '2026-10-05', 'Sale Invoice', 'INV-0024', 752.37, 0.00, 752.37, 'Sale Invoice #INV-0024'),
(11456, 30, '2026-10-05', 'Sale Invoice', 'INV-0035', 809.00, 0.00, 1561.37, 'Sale Invoice #INV-0035'),
(11457, 31, '2026-10-05', 'Sale Invoice', 'INV-0025', 2800.02, 0.00, 2800.02, 'Sale Invoice #INV-0025'),
(11458, 32, '2026-10-05', 'Sale Invoice', 'INV-0026', 559.80, 0.00, 559.80, 'Sale Invoice #INV-0026'),
(11459, 33, '2026-10-05', 'Sale Invoice', 'INV-0027', 7825.34, 0.00, 7825.34, 'Sale Invoice #INV-0027'),
(11460, 34, '2026-10-05', 'Sale Invoice', 'INV-0028', 2562.45, 0.00, 2562.45, 'Sale Invoice #INV-0028'),
(11461, 35, '2026-10-05', 'Sale Invoice', 'INV-0029', 1560.69, 0.00, 1560.69, 'Sale Invoice #INV-0029'),
(11462, 36, '2026-10-05', 'Sale Invoice', 'INV-0030', 935.81, 0.00, 935.81, 'Sale Invoice #INV-0030'),
(11463, 37, '2026-10-05', 'Sale Invoice', 'INV-0031', 2903.31, 0.00, 2903.31, 'Sale Invoice #INV-0031'),
(11464, 38, '2026-10-05', 'Sale Invoice', 'INV-0032', 1359.92, 0.00, 1359.92, 'Sale Invoice #INV-0032'),
(11465, 39, '2026-10-05', 'Sale Invoice', 'INV-0033', 2375.94, 0.00, 2375.94, 'Sale Invoice #INV-0033'),
(11466, 40, '2026-10-06', 'Sale Invoice', 'INV-0036', 2061.36, 0.00, 2061.36, 'Sale Invoice #INV-0036'),
(11467, 41, '2026-10-06', 'Sale Invoice', 'INV-0037', 2866.29, 0.00, 2866.29, 'Sale Invoice #INV-0037'),
(11468, 42, '2026-10-06', 'Sale Invoice', 'INV-0038', 1680.39, 0.00, 1680.39, 'Sale Invoice #INV-0038'),
(11469, 43, '2026-10-06', 'Sale Invoice', 'INV-0041', 3518.22, 0.00, 3518.22, 'Sale Invoice #INV-0041'),
(11470, 43, '2026-10-06', 'Sale Invoice', 'INV-0049', 3204.41, 0.00, 6722.63, 'Sale Invoice #INV-0049'),
(11471, 43, '2026-10-06', 'Sale Invoice', 'INV-0051', 3640.96, 0.00, 10363.59, 'Sale Invoice #INV-0051'),
(11472, 44, '2026-10-06', 'Sale Invoice', 'INV-0042', 2981.37, 0.00, 2981.37, 'Sale Invoice #INV-0042'),
(11473, 45, '2026-10-06', 'Sale Invoice', 'INV-0043', 2469.16, 0.00, 2469.16, 'Sale Invoice #INV-0043'),
(11474, 46, '2026-10-06', 'Sale Invoice', 'INV-0044', 4285.01, 0.00, 4285.01, 'Sale Invoice #INV-0044'),
(11475, 46, '2026-10-06', 'Sale Invoice', 'INV-0050', 4442.68, 0.00, 8727.69, 'Sale Invoice #INV-0050'),
(11476, 47, '2026-10-06', 'Sale Invoice', 'INV-0045', 1030.91, 0.00, 1030.91, 'Sale Invoice #INV-0045'),
(11477, 48, '2026-10-06', 'Sale Invoice', 'INV-0046', 886.19, 0.00, 886.19, 'Sale Invoice #INV-0046'),
(11478, 49, '2026-10-06', 'Sale Invoice', 'INV-0047', 8295.66, 0.00, 8295.66, 'Sale Invoice #INV-0047'),
(11479, 50, '2026-10-07', 'Sale Invoice', 'INV-0052', 5000.72, 0.00, 5000.72, 'Sale Invoice #INV-0052'),
(11480, 50, '2026-10-08', 'Sale Invoice', 'INV-0084', 5000.72, 0.00, 10001.44, 'Sale Invoice #INV-0084'),
(11481, 51, '2026-10-07', 'Sale Invoice', 'INV-0053', 2531.11, 0.00, 2531.11, 'Sale Invoice #INV-0053'),
(11482, 51, '2026-10-08', 'Sale Invoice', 'INV-0083', 2531.11, 0.00, 5062.22, 'Sale Invoice #INV-0083'),
(11483, 52, '2026-10-07', 'Sale Invoice', 'INV-0054', 2685.23, 0.00, 2685.23, 'Sale Invoice #INV-0054'),
(11484, 52, '2026-10-08', 'Sale Invoice', 'INV-0081', 2685.23, 0.00, 5370.46, 'Sale Invoice #INV-0081'),
(11485, 54, '2026-10-07', 'Sale Invoice', 'INV-0055', 2978.66, 0.00, 2978.66, 'Sale Invoice #INV-0055'),
(11486, 54, '2026-10-08', 'Sale Invoice', 'INV-0080', 2904.59, 0.00, 5883.25, 'Sale Invoice #INV-0080'),
(11487, 55, '2026-10-07', 'Sale Invoice', 'INV-0056', 5346.24, 0.00, 5346.24, 'Sale Invoice #INV-0056'),
(11488, 55, '2026-10-08', 'Sale Invoice', 'INV-0079', 5346.24, 0.00, 10692.48, 'Sale Invoice #INV-0079'),
(11489, 56, '2026-10-07', 'Sale Invoice', 'INV-0057', 1519.30, 0.00, 1519.30, 'Sale Invoice #INV-0057'),
(11490, 56, '2026-10-08', 'Sale Invoice', 'INV-0078', 1519.30, 0.00, 3038.60, 'Sale Invoice #INV-0078'),
(11491, 58, '2026-10-07', 'Sale Invoice', 'INV-0058', 2218.47, 0.00, 2218.47, 'Sale Invoice #INV-0058'),
(11492, 58, '2026-10-08', 'Sale Invoice', 'INV-0077', 2218.47, 0.00, 4436.94, 'Sale Invoice #INV-0077'),
(11494, 61, '2026-10-07', 'Sale Invoice', 'INV-0060', 301.72, 0.00, 301.72, 'Sale Invoice #INV-0060'),
(11495, 61, '2026-10-07', 'Sale Invoice', 'INV-0072', 291.57, 0.00, 593.29, 'Sale Invoice #INV-0072'),
(11496, 61, '2026-10-08', 'Sale Invoice', 'INV-0073', 291.57, 0.00, 884.86, 'Sale Invoice #INV-0073'),
(11497, 61, '2026-10-08', 'Sale Invoice', 'INV-0074', 291.57, 0.00, 1176.43, 'Sale Invoice #INV-0074'),
(11498, 61, '2026-10-08', 'Sale Invoice', 'INV-0075', 291.57, 0.00, 1468.00, 'Sale Invoice #INV-0075'),
(11499, 62, '2026-10-07', 'Sale Invoice', 'INV-0061', 5972.21, 0.00, 5972.21, 'Sale Invoice #INV-0061'),
(11500, 62, '2026-10-07', 'Sale Invoice', 'INV-0071', 5972.21, 0.00, 11944.42, 'Sale Invoice #INV-0071'),
(11501, 62, '2026-10-08', 'Sale Invoice', 'INV-0076', 5972.21, 0.00, 17916.63, 'Sale Invoice #INV-0076'),
(11502, 63, '2026-10-07', 'Sale Invoice', 'INV-0062', 4582.36, 0.00, 4582.36, 'Sale Invoice #INV-0062'),
(11503, 63, '2026-10-07', 'Sale Invoice', 'INV-0070', 4582.36, 0.00, 9164.72, 'Sale Invoice #INV-0070'),
(11504, 64, '2026-10-07', 'Sale Invoice', 'INV-0063', 1507.18, 0.00, 1507.18, 'Sale Invoice #INV-0063'),
(11505, 64, '2026-10-07', 'Sale Invoice', 'INV-0068', 1507.18, 0.00, 3014.36, 'Sale Invoice #INV-0068'),
(11506, 65, '2026-10-07', 'Sale Invoice', 'INV-0064', 3049.02, 0.00, 3049.02, 'Sale Invoice #INV-0064'),
(11507, 65, '2026-10-07', 'Sale Invoice', 'INV-0069', 3049.02, 0.00, 6098.04, 'Sale Invoice #INV-0069'),
(11508, 66, '2026-10-07', 'Sale Invoice', 'INV-0065', 2423.26, 0.00, 2423.26, 'Sale Invoice #INV-0065'),
(11509, 66, '2026-10-07', 'Sale Invoice', 'INV-0067', 2423.26, 0.00, 4846.52, 'Sale Invoice #INV-0067'),
(11510, 67, '2026-10-07', 'Sale Invoice', 'INV-0066', 10217.97, 0.00, 10217.97, 'Sale Invoice #INV-0066'),
(11511, 67, '2026-10-08', 'Sale Invoice', 'INV-0082', 10217.97, 0.00, 20435.94, 'Sale Invoice #INV-0082'),
(11512, 60, '2026-10-07', 'Sale Invoice', 'INV-0059', 2127.98, 0.00, 2127.98, 'Sale Invoice #INV-0059'),
(11513, 60, '2026-10-08', 'Sale Invoice', 'INV-0085', 2127.98, 0.00, 4255.96, 'Sale Invoice #INV-0085');

-- --------------------------------------------------------

--
-- Table structure for table `customer_payments`
--

CREATE TABLE `customer_payments` (
  `id` int NOT NULL,
  `voucher_no` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `payment_date` date NOT NULL,
  `customer_id` int NOT NULL,
  `sale_id` int DEFAULT NULL,
  `route_id` int DEFAULT NULL,
  `collector_staff_id` int DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `payment_method` enum('Cash','Bank Transfer','Online') COLLATE utf8mb4_general_ci DEFAULT 'Cash',
  `bank_account_id` int DEFAULT NULL,
  `discount_allowed` decimal(12,2) DEFAULT '0.00',
  `remarks` text COLLATE utf8mb4_general_ci,
  `created_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_receipts`
--

CREATE TABLE `customer_receipts` (
  `id` int NOT NULL,
  `customer_id` int NOT NULL,
  `sale_id` int DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` enum('cash','bank') COLLATE utf8mb4_general_ci DEFAULT 'cash',
  `bank_account_id` int DEFAULT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `receipt_date` date NOT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_challans`
--

CREATE TABLE `delivery_challans` (
  `id` int NOT NULL,
  `challan_no` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `invoice_id` int DEFAULT NULL,
  `invoice_no` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `challan_date` date NOT NULL,
  `customer_name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `customer_phone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `delivery_address` text COLLATE utf8mb4_general_ci,
  `route_name` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `booker_name` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `vehicle_no` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `driver_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `driver_phone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `total_cartons` int DEFAULT '1',
  `total_items` int DEFAULT '0',
  `total_quantity` int DEFAULT '0',
  `delivery_status` enum('Pending','In Transit','Dispatched','Delivered','Cancelled') COLLATE utf8mb4_general_ci DEFAULT 'Dispatched',
  `notes` text COLLATE utf8mb4_general_ci,
  `created_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_challans`
--

INSERT INTO `delivery_challans` (`id`, `challan_no`, `invoice_id`, `invoice_no`, `challan_date`, `customer_name`, `customer_phone`, `delivery_address`, `route_name`, `booker_name`, `vehicle_no`, `driver_name`, `driver_phone`, `total_cartons`, `total_items`, `total_quantity`, `delivery_status`, `notes`, `created_by`) VALUES
(22, 'DC-0001', 22, 'INV-0001', '2026-10-05', 'Mughal Pharmacy', '03004480486', 'Ameen Park', 'Sanda Kalan', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 7, 12, 'Dispatched', '', 1),
(23, 'DC-0002', 23, 'INV-0002', '2026-10-05', 'Saeeda M/S', '03238496927', 'Ameen Park', 'Sanda Kalan', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 1, 1, 'Dispatched', '', 1),
(24, 'DC-0003', 24, 'INV-0003', '2026-10-05', 'Moon M/S', '03334289503', 'Kareem Park', 'Sanda Kalan', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 3, 4, 'Dispatched', '', 1),
(25, 'DC-0004', 25, 'INV-0004', '2026-10-05', 'Medisafe Pharmacy', '03075413411', 'Kasur Pura Bazar', 'Sanda Kalan', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 4, 5, 'Dispatched', '', 1),
(26, 'DC-0005', 26, 'INV-0005', '2026-10-05', 'New Green Pharmacy', '03044835856', 'Kasur Pura Bazar', 'Sanda Kalan', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 11, 16, 'Dispatched', '', 1),
(27, 'DC-0006', 27, 'INV-0006', '2026-10-05', 'New Malik medical store', '0333 4436687', 'Etihad chemical factory opposite', 'Rana Town', 'Raju', 'Delivery Van', 'Logistics Rider', '', 1, 6, 15, 'Dispatched', '', 1),
(28, 'DC-0007', 28, 'INV-0007', '2026-10-05', 'Malik M/S', '0300-9470100', '', 'Sanda Kalan', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 9, 14, 'Dispatched', '', 1),
(29, 'DC-0008', 29, 'INV-0008', '2026-10-05', 'Haider healthcare pharmacy', '0314 7980484', 'Ravi Rian gt road', 'Rana Town', 'Raju', 'Delivery Van', 'Logistics Rider', '', 1, 4, 10, 'Dispatched', '', 1),
(30, 'DC-0009', 30, 'INV-0009', '2026-10-05', 'Qartaba M/S', '03236045413', 'Ameen Park', 'Sanda Kalan', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 3, 13, 'Dispatched', '', 1),
(31, 'DC-0010', 31, 'INV-0010', '2026-10-05', 'Sheikh M/S', '03246574251', 'Ameen Park', 'Sanda Kalan', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 5, 5, 'Dispatched', '', 1),
(32, 'DC-0011', 32, 'INV-0011', '2026-10-05', 'Haider Pharmacy', '0324_4004056', 'Mohny Road Lahore', 'Sanda kalan', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 11, 21, 'Dispatched', '', 1),
(33, 'DC-0012', 33, 'INV-0012', '2026-10-05', 'Hijab medical store', '0308 4537238', 'Haider road Rana Town', 'Rana Town', 'Raju', 'Delivery Van', 'Logistics Rider', '', 1, 16, 39, 'Dispatched', '', 1),
(34, 'DC-0013', 34, 'INV-0013', '2026-10-05', 'Sohail pharmacy', '0300 4831918', 'Haider road Rana Town', 'Rana Town', 'Raju', 'Delivery Van', 'Logistics Rider', '', 1, 4, 5, 'Dispatched', '', 1),
(35, 'DC-0014', 35, 'INV-0014', '2026-10-05', 'New Shahid medical stire', '0300 8141806', 'Haider road Rana Town', 'Rana Town', 'Raju', 'Delivery Van', 'Logistics Rider', '', 1, 3, 10, 'Dispatched', '', 1),
(36, 'DC-0015', 36, 'INV-0015', '2026-10-05', 'Unique pharmacy', '0310 4841122', 'College road Rana Town', 'Rana Town', 'Raju', 'Delivery Van', 'Logistics Rider', '', 1, 3, 4, 'Dispatched', '', 1),
(37, 'DC-0016', 37, 'INV-0016', '2026-10-05', 'Raza medical and gernal store', '0309 4479314', 'College road Rana Town', 'Rana Town', 'Raju', 'Delivery Van', 'Logistics Rider', '', 1, 4, 7, 'Dispatched', '', 1),
(38, 'DC-0017', 38, 'INV-0017', '2026-10-05', 'Javeed sons pharmacy', '0305 8771561', 'Rachana town Bazar', 'Rana Town', 'Raju', 'Delivery Van', 'Logistics Rider', '', 1, 3, 5, 'Dispatched', '', 1),
(39, 'DC-0018', 39, 'INV-0018', '2026-10-05', 'Javeed sons pharmacy', '0305 8771561', 'Rachana town Bazar', 'Rana Town', 'Raju', 'Delivery Van', 'Logistics Rider', '', 1, 4, 7, 'Dispatched', '', 1),
(40, 'DC-0019', 40, 'INV-0019', '2026-10-05', 'Waseem pharmacy', '0300 1312025', 'Rachana town Bazar', 'Rana Town', 'Raju', 'Delivery Van', 'Logistics Rider', '', 1, 8, 14, 'Dispatched', '', 1),
(41, 'DC-0020', 41, 'INV-0020', '2026-10-05', 'Hafiz Pharmacy', '03134091988', 'Sanat Nager  Usman Ghani RD', 'Sanda Kalan', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 8, 13, 'Dispatched', '', 1),
(42, 'DC-0021', 42, 'INV-0021', '2026-10-05', 'Khan M/S', '03032870287', 'Outfall Road', 'Sanda Kalan', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 6, 8, 'Dispatched', '', 1),
(43, 'DC-0022', 43, 'INV-0022', '2026-10-05', 'Khadam and sons pharmacy', '0304 9697002', 'Rana Town gt road', 'Rana Town', 'Raju', 'Delivery Van', 'Logistics Rider', '', 1, 7, 21, 'Dispatched', '', 1),
(44, 'DC-0023', 44, 'INV-0023', '2026-10-05', 'Smart Care Pharmacy', '03224580979', 'Sanda Akhry Bus stop', 'Sanda Kalan', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 3, 5, 'Dispatched', '', 1),
(45, 'DC-0024', 45, 'INV-0024', '2026-10-05', 'Nazir Sons Pharmacy', '03334992227', 'Sanda Main Road', 'Sanda Kalan', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 1, 1, 'Dispatched', '', 1),
(46, 'DC-0025', 46, 'INV-0025', '2026-10-05', 'Saad Medical Store', '03099634214', 'Rajjgar', 'Sanda Kalan', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 3, 3, 'Dispatched', '', 1),
(47, 'DC-0026', 48, 'INV-0026', '2026-10-05', 'Fiazan Pharmacy', '03020587071', 'Rajjgar', 'Sanda Kalan', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 2, 3, 'Dispatched', '', 1),
(48, 'DC-0027', 49, 'INV-0027', '2026-10-05', 'Medicare pharmacy', '03066666465', 'Umer Road Islampura', 'Sanda Kalan', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 3, 8, 'Dispatched', '', 1),
(49, 'DC-0028', 50, 'INV-0028', '2026-10-05', 'Mediprime Pharmacy', '03254444356', 'Abdali Road', 'Sanda Kalan', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 2, 6, 'Dispatched', '', 1),
(50, 'DC-0029', 51, 'INV-0029', '2026-10-05', 'Bismillah Pharmacy', '03004571110', 'Abdali Chowk', 'Sanda Kalan', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 2, 2, 'Dispatched', '', 1),
(51, 'DC-0030', 52, 'INV-0030', '2026-10-05', 'Riaz M/S', '03154620041', 'Islampura Main Bazaar', 'Sanda Kalan', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 2, 12, 'Dispatched', '', 1),
(52, 'DC-0031', 53, 'INV-0031', '2026-10-05', 'New Mughal Pharmacy', '03204039486', 'Malik Park', 'Sanda Kalan', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 5, 19, 'Dispatched', '', 1),
(53, 'DC-0032', 54, 'INV-0032', '2026-10-05', 'Al Shifa Pharmacy', '0322-4148841', 'Hafeez Road', 'Sanda Kalan', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 3, 7, 'Dispatched', '', 1),
(54, 'DC-0033', 55, 'INV-0033', '2026-10-05', 'Kamran M/S', '03214124136', 'Hafeez Raod', 'Sanda Kalan', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 6, 16, 'Dispatched', '', 1),
(55, 'DC-0034', 56, 'INV-0034', '2026-10-05', 'Liaquit Sons Pharmacy', '03080426690', 'Ameen Park', 'Sanda Kalan', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 21, 40, 'Dispatched', '', 1),
(56, 'DC-0035', 57, 'INV-0035', '2026-10-05', 'Nazir Sons Pharmacy', '03334992227', 'Sanda Main Road', 'Sanda Kalan', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 1, 1, 'Dispatched', '', 1),
(57, 'DC-0036', 58, 'INV-0036', '2026-10-06', 'Bismillah medical store', '0301 4215590', 'Khala khati morr pathak', 'Kala Khatai Morr', 'Raju', 'Delivery Van', 'Logistics Rider', '', 1, 5, 8, 'Dispatched', '', 1),
(58, 'DC-0037', 59, 'INV-0037', '2026-10-06', 'Imran medical store', '0322 4648722', 'Kala khatai road', 'Kala Khatai Morr', 'Raju', 'Delivery Van', 'Logistics Rider', '', 1, 6, 19, 'Dispatched', '', 1),
(59, 'DC-0038', 60, 'INV-0038', '2026-10-06', 'Tayyab pharmacy', '0317 4596996', 'Qaiser town kala khatai moor', 'Kala Khatai Morr', 'Raju', 'Delivery Van', 'Logistics Rider', '', 1, 5, 7, 'Dispatched', '', 1),
(60, 'DC-0039', 61, 'INV-0039', '2026-10-06', 'Haider Pharmacy', '0324_4004056', 'Mohny Road Lahore', 'Sanda kalan', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 2, 4, 'Dispatched', '', 1),
(61, 'DC-0040', 62, 'INV-0040', '2026-10-06', 'Haider Pharmacy', '0324_4004056', 'Mohny Road Lahore', 'Sanda kalan', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 1, 2, 'Dispatched', '', 1),
(62, 'DC-0041', 63, 'INV-0041', '2026-10-06', 'Lassni medical store immima colony', '0321 4714981', 'Imamia colony Bazar', 'Kala Khatai Morr', 'Raju', 'Delivery Van', 'Logistics Rider', '', 1, 5, 8, 'Dispatched', '', 1),
(63, 'DC-0042', 64, 'INV-0042', '2026-10-06', 'Pak online pharmacy imamia colony', '0321 1001028', '', 'Kala Khatai Morr', 'Raju', 'Delivery Van', 'Logistics Rider', '', 1, 3, 5, 'Dispatched', '', 1),
(64, 'DC-0043', 65, 'INV-0043', '2026-10-06', 'Green pharmacy imamia colony', '0318 4063306', 'Imamia colony Bazar', 'Kala Khatai Morr', 'Raju', 'Delivery Van', 'Logistics Rider', '', 1, 2, 6, 'Dispatched', '', 1),
(65, 'DC-0044', 66, 'INV-0044', '2026-10-06', 'Al syed pharmacy imamia colony', '0331 4687953', '', 'Kala Khatai Morr', 'Raju', 'Delivery Van', 'Logistics Rider', '', 1, 5, 12, 'Dispatched', '', 1),
(66, 'DC-0045', 67, 'INV-0045', '2026-10-06', 'H fareed sons pharmacy', '0305 7709446', 'Imamia colony Bazar', 'Kala Khatai Morr', 'Raju', 'Delivery Van', 'Logistics Rider', '', 1, 3, 3, 'Dispatched', '', 1),
(67, 'DC-0046', 68, 'INV-0046', '2026-10-06', 'Gravity Plus pharmacy feroz wala', '0318 4350893', 'Feroz wala Bazar', 'Kala Khatai Morr', 'Raju', 'Delivery Van', 'Logistics Rider', '', 1, 3, 3, 'Dispatched', '', 1),
(68, 'DC-0047', 69, 'INV-0047', '2026-10-06', 'Nouman Pharmacy', '03120459757', 'I Block Market', 'ARG', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 13, 25, 'Dispatched', '', 1),
(69, 'DC-0048', 70, 'INV-0048', '2026-10-06', 'Waseem pharmacy', '0300 1312025', 'Rachana town Bazar', 'Rana Town', 'Raju', 'Delivery Van', 'Logistics Rider', '', 1, 8, 14, 'Dispatched', '', 1),
(70, 'DC-0049', 71, 'INV-0049', '2026-10-06', 'Lassni medical store immima colony', '0321 4714981', 'Imamia colony Bazar', 'Kala Khatai Morr', 'Raju', 'Delivery Van', 'Logistics Rider', '', 1, 4, 6, 'Dispatched', '', 1),
(71, 'DC-0050', 72, 'INV-0050', '2026-10-06', 'Al syed pharmacy imamia colony', '0331 4687953', '', 'Kala Khatai Morr', 'Raju', 'Delivery Van', 'Logistics Rider', '', 1, 5, 12, 'Dispatched', '', 1),
(72, 'DC-0051', 73, 'INV-0051', '2026-10-06', 'Lassni medical store immima colony', '0321 4714981', 'Imamia colony Bazar', 'Kala Khatai Morr', 'Raju', 'Delivery Van', 'Logistics Rider', '', 1, 5, 8, 'Dispatched', '', 1),
(73, 'DC-0052', 74, 'INV-0052', '2026-10-07', 'QS Medical & Cosmetics Store', '0301-4882787', 'Main Peko Road', 'Walton', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 6, 16, 'Dispatched', '', 1),
(74, 'DC-0053', 75, 'INV-0053', '2026-10-07', 'Makhdoom Pharmacy', '03214700003', 'Karmawala Main Bazar', 'Walton', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 7, 10, 'Dispatched', '', 1),
(75, 'DC-0054', 76, 'INV-0054', '2026-10-07', 'Madina Pharmacy', '03133121010', 'Karmawala Main Bazar', 'Walton', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 3, 4, 'Dispatched', '', 1),
(76, 'DC-0055', 77, 'INV-0055', '2026-10-07', 'Kashif M/S', '0317-4110300', 'Karmawala Main Bazar', 'Walton', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 7, 13, 'Dispatched', '', 1),
(77, 'DC-0056', 78, 'INV-0056', '2026-10-07', 'Wasim M/S', '03463798707', 'Karmawala Main Bazar', 'Walton', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 7, 17, 'Dispatched', '', 1),
(78, 'DC-0057', 79, 'INV-0057', '2026-10-07', 'Fine M/S', '03004182260', 'Karmawala Main Bazar', 'Walton', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 4, 4, 'Dispatched', '', 1),
(79, 'DC-0058', 80, 'INV-0058', '2026-10-07', 'Rehman Pharmacy', '0301-4940313', 'Main Bazar Pindi Stop', 'Walton', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 3, 5, 'Dispatched', '', 1),
(80, 'DC-0059', 81, 'INV-0059', '2026-10-07', 'ADD Plus Pharmacy', '03234270425', 'Main Pindi Stop Road', 'Walton', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 4, 15, 'Dispatched', '', 1),
(81, 'DC-0060', 82, 'INV-0060', '2026-10-07', 'Al Raziq Pharmacy', '03243215939', 'Main Bazar Gopal Nagar', 'Walton', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 1, 4, 'Dispatched', '', 1),
(82, 'DC-0061', 83, 'INV-0061', '2026-10-07', 'New Hammad', '0313-4684914', 'Gopal Nager', 'Walton', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 10, 16, 'Dispatched', '', 1),
(83, 'DC-0062', 84, 'INV-0062', '2026-10-07', 'Hafiz Care Pharmacy', '03006588628', 'Gopal Nager', 'Walton', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 6, 23, 'Dispatched', '', 1),
(84, 'DC-0063', 85, 'INV-0063', '2026-10-07', 'Health Mart Pharmacy', '03014460070', 'Main Road Abid Market', 'Abid Market', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 2, 7, 'Dispatched', '', 1),
(85, 'DC-0064', 86, 'INV-0064', '2026-10-07', 'Service Medicos', '03214263438', 'Main Abid Market Road', 'Abid Market', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 3, 9, 'Dispatched', '', 1),
(86, 'DC-0065', 87, 'INV-0065', '2026-10-07', 'Care Medicos', '03354511777', 'Oppsit Abid Market Road', 'Abid Market', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 3, 6, 'Dispatched', '', 1),
(87, 'DC-0066', 88, 'INV-0066', '2026-10-07', 'Muhammed Mustafa', '03204030436', 'Mola Baksh Road', 'Abid Market', 'Sherazi', 'Delivery Van', 'Logistics Rider', '', 1, 10, 16, 'Dispatched', '', 1),
(88, 'DC-0067', 89, 'INV-0067', '2026-10-07', 'Care Medicos', '03354511777', 'Oppsit Abid Market Road', 'Abid Market', '', 'Delivery Van', 'Logistics Rider', '', 1, 3, 6, 'Dispatched', '', 1),
(89, 'DC-0068', 90, 'INV-0068', '2026-10-07', 'Health Mart Pharmacy', '03014460070', 'Main Road Abid Market', 'Abid Market', '', 'Delivery Van', 'Logistics Rider', '', 1, 2, 7, 'Dispatched', '', 1),
(90, 'DC-0069', 91, 'INV-0069', '2026-10-07', 'Service Medicos', '03214263438', 'Main Abid Market Road', 'Abid Market', '', 'Delivery Van', 'Logistics Rider', '', 1, 3, 9, 'Dispatched', '', 1),
(91, 'DC-0070', 92, 'INV-0070', '2026-10-07', 'Hafiz Care Pharmacy', '03006588628', 'Gopal Nager', 'Walton', '', 'Delivery Van', 'Logistics Rider', '', 1, 6, 23, 'Dispatched', '', 1),
(92, 'DC-0071', 93, 'INV-0071', '2026-10-07', 'New Hammad', '0313-4684914', 'Gopal Nager', 'Walton', '', 'Delivery Van', 'Logistics Rider', '', 1, 10, 16, 'Dispatched', '', 1),
(93, 'DC-0072', 94, 'INV-0072', '2026-10-07', 'Al Raziq Pharmacy', '03243215939', 'Main Bazar Gopal Nagar', 'Walton', '', 'Delivery Van', 'Logistics Rider', '', 1, 2, 6, 'Dispatched', '', 1),
(94, 'DC-0073', 95, 'INV-0073', '2026-10-08', 'Al Raziq Pharmacy', '03243215939', 'Main Bazar Gopal Nagar', 'Walton', '', 'Delivery Van', 'Logistics Rider', '', 1, 2, 6, 'Dispatched', '', 1),
(95, 'DC-0074', 96, 'INV-0074', '2026-10-08', 'Al Raziq Pharmacy', '03243215939', 'Main Bazar Gopal Nagar', 'Walton', '', 'Delivery Van', 'Logistics Rider', '', 1, 2, 6, 'Dispatched', '', 1),
(96, 'DC-0075', 97, 'INV-0075', '2026-10-08', 'Al Raziq Pharmacy', '03243215939', 'Main Bazar Gopal Nagar', 'Walton', '', 'Delivery Van', 'Logistics Rider', '', 1, 2, 6, 'Dispatched', '', 1),
(97, 'DC-0076', 98, 'INV-0076', '2026-10-08', 'New Hammad', '0313-4684914', 'Gopal Nager', 'Walton', '', 'Delivery Van', 'Logistics Rider', '', 1, 10, 16, 'Dispatched', '', 1),
(98, 'DC-0077', 99, 'INV-0077', '2026-10-08', 'Rehman Pharmacy', '0301-4940313', 'Main Bazar Pindi Stop', 'Walton', '', 'Delivery Van', 'Logistics Rider', '', 1, 3, 5, 'Dispatched', '', 1),
(99, 'DC-0078', 100, 'INV-0078', '2026-10-08', 'Fine M/S', '03004182260', 'Karmawala Main Bazar', 'Walton', '', 'Delivery Van', 'Logistics Rider', '', 1, 4, 4, 'Dispatched', '', 1),
(100, 'DC-0079', 101, 'INV-0079', '2026-10-08', 'Wasim M/S', '03463798707', 'Karmawala Main Bazar', 'Walton', '', 'Delivery Van', 'Logistics Rider', '', 1, 7, 17, 'Dispatched', '', 1),
(101, 'DC-0080', 102, 'INV-0080', '2026-10-08', 'Kashif M/S', '0317-4110300', 'Karmawala Main Bazar', 'Walton', '', 'Delivery Van', 'Logistics Rider', '', 1, 7, 10, 'Dispatched', '', 1),
(102, 'DC-0081', 103, 'INV-0081', '2026-10-08', 'Madina Pharmacy', '03133121010', 'Karmawala Main Bazar', 'Walton', '', 'Delivery Van', 'Logistics Rider', '', 1, 3, 4, 'Dispatched', '', 1),
(103, 'DC-0082', 104, 'INV-0082', '2026-10-08', 'Muhammed Mustafa', '03204030436', 'Mola Baksh Road', 'Abid Market', '', 'Delivery Van', 'Logistics Rider', '', 1, 10, 16, 'Dispatched', '', 1),
(104, 'DC-0083', 105, 'INV-0083', '2026-10-08', 'Makhdoom Pharmacy', '03214700003', 'Karmawala Main Bazar', 'Walton', '', 'Delivery Van', 'Logistics Rider', '', 1, 7, 10, 'Dispatched', '', 1),
(105, 'DC-0084', 106, 'INV-0084', '2026-10-08', 'QS Medical & Cosmetics Store', '0301-4882787', 'Main Peko Road', 'Walton', '', 'Delivery Van', 'Logistics Rider', '', 1, 6, 16, 'Dispatched', '', 1),
(106, 'DC-0085', 107, 'INV-0085', '2026-10-08', 'ADD Plus Pharmacy', '03234270425', 'Main Pindi Stop Road', 'Walton', '', 'Delivery Van', 'Logistics Rider', '', 1, 4, 15, 'Dispatched', '', 1);

-- --------------------------------------------------------

--
-- Table structure for table `delivery_challan_items`
--

CREATE TABLE `delivery_challan_items` (
  `id` int NOT NULL,
  `challan_id` int NOT NULL,
  `product_id` int DEFAULT NULL,
  `item_name` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `batch_no` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `unit_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'Pack',
  `remarks` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_challan_items`
--

INSERT INTO `delivery_challan_items` (`id`, `challan_id`, `product_id`, `item_name`, `batch_no`, `quantity`, `unit_type`, `remarks`) VALUES
(23, 22, 22, 'Alcuflex 550mg Tab 30s', 'DEFAULT', 1, 'Pack', ''),
(24, 22, 40, 'Polyfax Skin Oint 20g', 'BAT-260929', 4, 'Pack', ''),
(25, 22, 63, 'Ossobon D Tab', 'DEFAULT', 3, 'Pack', ''),
(26, 22, 128, 'Cebosh 100/5ml Syp', 'DEFAULT', 1, 'Pack', ''),
(27, 22, 131, 'Atorva 10mg Tab 20s', 'DEFAULT', 1, 'Pack', ''),
(28, 22, 132, 'Atorva 20mg Tab 20s', 'DEFAULT', 1, 'Pack', ''),
(29, 22, 145, 'Osteocare D3 Tab', 'DEFAULT', 1, 'Pack', ''),
(30, 23, 51, 'Levopraid 25mg Tab', 'BAT-260929', 1, 'Pack', ''),
(31, 24, 94, 'Sita Met 50/500mg Tab 14s', 'DEFAULT', 2, 'Pack', ''),
(32, 24, 63, 'Ossobon D Tab', 'DEFAULT', 1, 'Pack', ''),
(33, 24, 131, 'Atorva 10mg Tab 20s', 'DEFAULT', 1, 'Pack', ''),
(34, 25, 46, 'Arinac Fort Tab', 'BAT-260929', 1, 'Pack', ''),
(35, 25, 44, 'Sunny D Inj', 'BAT-260929', 2, 'Pack', ''),
(36, 25, 139, 'Diampa LXR 10/5/1000 mg Tab 14s', 'DEFAULT', 1, 'Pack', ''),
(37, 25, 90, 'Elexine 15mg Tab', 'DEFAULT', 1, 'Pack', ''),
(38, 26, 57, 'Methix Tab 20s', 'BAT-260929', 1, 'Pack', ''),
(39, 26, 15, 'Azomax 500mg Tab', 'BAT-260929', 1, 'Pack', ''),
(40, 26, 2, 'Velosef 500mg Cap', 'BAT-260929', 1, 'Pack', ''),
(41, 26, 25, 'Klaricid 250mg Tab', 'BAT-260929', 1, 'Pack', ''),
(42, 26, 27, 'Somogel Cream', 'BAT-260929', 1, 'Pack', ''),
(43, 26, 1, 'Clobevate Cream', 'BAT-260929', 4, 'Pack', ''),
(44, 26, 46, 'Arinac Fort Tab', 'DEFAULT', 1, 'Pack', ''),
(45, 26, 35, 'Canderel 18mg Tab 100s', 'BAT-260929', 1, 'Pack', ''),
(46, 26, 43, 'Zyrtec Syp 60ml', 'BAT-260929', 1, 'Pack', ''),
(47, 26, 9, 'Kestine 10mg Tab 14s', 'BAT-260929', 1, 'Pack', ''),
(48, 26, 10, 'Ulsanic Syp 120ml', 'BAT-260929', 3, 'Pack', ''),
(49, 27, 38, 'Betnovate N Cream', 'BAT-260929', 3, 'Pack', ''),
(50, 27, 39, 'Calpol 6+ Syp 90ml', 'BAT-260929', 4, 'Pack', ''),
(51, 27, 40, 'Polyfax Skin Oint 20g', 'DEFAULT', 2, 'Pack', ''),
(52, 27, 1, 'Clobevate Cream', 'DEFAULT', 3, 'Pack', ''),
(53, 27, 2, 'Velosef 500mg Cap', 'DEFAULT', 1, 'Pack', ''),
(54, 27, 27, 'Somogel Cream', 'DEFAULT', 2, 'Pack', ''),
(55, 28, 81, 'Magnett 400mg Cap', 'DEFAULT', 1, 'Pack', ''),
(56, 28, 89, 'Nirvanol 10mg Tab', 'DEFAULT', 2, 'Pack', ''),
(57, 28, 140, 'Diampa LXR 25/5/1000 mg Tab 14s', 'DEFAULT', 1, 'Pack', ''),
(58, 28, 143, 'Treviamet 50/500 tab 14s', 'DEFAULT', 1, 'Pack', ''),
(59, 28, 45, 'Entox P Tab', 'BAT-260929', 1, 'Pack', ''),
(60, 28, 94, 'Sita Met 50/500mg Tab 14s', 'DEFAULT', 2, 'Pack', ''),
(61, 28, 9, 'Kestine 10mg Tab 14s', 'DEFAULT', 2, 'Pack', ''),
(62, 28, 40, 'Polyfax Skin Oint 20g', 'DEFAULT', 3, 'Pack', ''),
(63, 28, 11, 'Empaa 10mg Tab 28s', 'BAT-260929', 1, 'Pack', ''),
(64, 29, 130, 'Apranax 550mg Tab', 'DEFAULT', 1, 'Pack', ''),
(65, 29, 95, 'Sita Met 50/1000mg Tab 14s', 'DEFAULT', 2, 'Pack', ''),
(66, 29, 94, 'Sita Met 50/500mg Tab 14s', 'DEFAULT', 2, 'Pack', ''),
(67, 29, 44, 'Sunny D Inj', 'DEFAULT', 5, 'Pack', ''),
(68, 30, 55, 'Cellgee Tab 30s', 'BAT-260929', 1, 'Pack', ''),
(69, 30, 75, 'ECP Tab', 'DEFAULT', 10, 'Pack', ''),
(70, 30, 84, 'Cefiget 100/5ml Syp 30ml', 'DEFAULT', 2, 'Pack', ''),
(71, 31, 120, 'Myolax 4mg Tab', 'DEFAULT', 1, 'Pack', ''),
(72, 31, 122, 'Xavor 50mg Tab', 'DEFAULT', 1, 'Pack', ''),
(73, 31, 40, 'Polyfax Skin Oint 20g', 'DEFAULT', 1, 'Pack', ''),
(74, 31, 13, 'Rhinosone P Spray 15ml', 'BAT-260929', 1, 'Pack', ''),
(75, 31, 49, 'Combivair 400mg Cap', 'BAT-260929', 1, 'Pack', ''),
(76, 32, 33, 'Solo 10mg Tab 14s', 'BAT-260929', 2, 'Pack', ''),
(77, 32, 125, 'Deximox E/D', 'DEFAULT', 3, 'Pack', ''),
(78, 32, 106, 'Sita 100mg Tab 14s', 'DEFAULT', 2, 'Pack', ''),
(79, 32, 55, 'Cellgee Tab 30s', 'DEFAULT', 1, 'Pack', ''),
(80, 32, 57, 'Methix Tab 20s', 'DEFAULT', 2, 'Pack', ''),
(81, 32, 49, 'Combivair 400mg Cap', 'DEFAULT', 2, 'Pack', ''),
(82, 32, 9, 'Kestine 10mg Tab 14s', 'DEFAULT', 3, 'Pack', ''),
(83, 32, 12, 'Empaa M 12.5/500mg Tab 28s', 'BAT-260929', 2, 'Pack', ''),
(84, 32, 73, 'Beceptor 10mg Tab', 'DEFAULT', 1, 'Pack', ''),
(85, 32, 58, 'Intig D Tab', 'BAT-260929', 1, 'Pack', ''),
(86, 32, 136, 'Derma Smooth Lotion 120ml', 'DEFAULT', 2, 'Pack', ''),
(87, 33, 14, 'Azomax 250mg Cap 12s', 'BAT-260929', 1, 'Pack', ''),
(88, 33, 15, 'Azomax 500mg Tab', 'DEFAULT', 1, 'Pack', ''),
(89, 33, 83, 'Polybion Z Cap', 'DEFAULT', 1, 'Pack', ''),
(90, 33, 96, 'Nuberol Tab 100s', 'DEFAULT', 1, 'Pack', ''),
(91, 33, 132, 'Atorva 20mg Tab 20s', 'DEFAULT', 1, 'Pack', ''),
(92, 33, 97, 'Magnett 100/5ml Syp', 'DEFAULT', 5, 'Pack', ''),
(93, 33, 80, 'Adenuric 40mg Tab 20s', 'DEFAULT', 5, 'Pack', ''),
(94, 33, 5, 'Elezo 150 Cap', 'BAT-260929', 2, 'Pack', ''),
(95, 33, 8, 'Novoteph 40mg Cap', 'BAT-260929', 2, 'Pack', ''),
(96, 33, 99, 'Amodip 10mg Tab', 'DEFAULT', 1, 'Pack', ''),
(97, 33, 112, 'Tobra D E/D', 'DEFAULT', 2, 'Pack', ''),
(98, 33, 111, 'Tobra E/D', 'DEFAULT', 2, 'Pack', ''),
(99, 33, 39, 'Calpol 6+ Syp 90ml', 'DEFAULT', 5, 'Pack', ''),
(100, 33, 43, 'Zyrtec Syp 60ml', 'DEFAULT', 3, 'Pack', ''),
(101, 33, 1, 'Clobevate Cream', 'DEFAULT', 5, 'Pack', ''),
(102, 33, 16, 'Revital Multi Tab 45s', 'BAT-260929', 2, 'Pack', ''),
(103, 34, 67, 'Zestril 5mg Tab', 'DEFAULT', 2, 'Pack', ''),
(104, 34, 16, 'Revital Multi Tab 45s', 'DEFAULT', 1, 'Pack', ''),
(105, 34, 115, 'Ezium 20mg Cap 14s', 'DEFAULT', 1, 'Pack', ''),
(106, 34, 8, 'Novoteph 40mg Cap', 'DEFAULT', 1, 'Pack', ''),
(107, 35, 44, 'Sunny D Inj', 'DEFAULT', 3, 'Pack', ''),
(108, 35, 27, 'Somogel Cream', 'DEFAULT', 6, 'Pack', ''),
(109, 35, 131, 'Atorva 10mg Tab 20s', 'DEFAULT', 1, 'Pack', ''),
(110, 36, 16, 'Revital Multi Tab 45s', 'DEFAULT', 2, 'Pack', ''),
(111, 36, 125, 'Deximox E/D', 'DEFAULT', 1, 'Pack', ''),
(112, 36, 111, 'Tobra E/D', 'DEFAULT', 1, 'Pack', ''),
(113, 37, 131, 'Atorva 10mg Tab 20s', 'DEFAULT', 1, 'Pack', ''),
(114, 37, 132, 'Atorva 20mg Tab 20s', 'DEFAULT', 1, 'Pack', ''),
(115, 37, 34, 'Xylor Tab 20s', 'BAT-260929', 2, 'Pack', ''),
(116, 37, 13, 'Rhinosone P Spray 15ml', 'DEFAULT', 3, 'Pack', ''),
(117, 38, 112, 'Tobra D E/D', 'DEFAULT', 2, 'Pack', ''),
(118, 38, 111, 'Tobra E/D', 'DEFAULT', 2, 'Pack', ''),
(119, 38, 126, 'Eyebradex E/D', 'DEFAULT', 1, 'Pack', ''),
(120, 39, 102, 'Lophos Tab 100s', 'DEFAULT', 1, 'Pack', ''),
(121, 39, 86, 'Lice -O-Nil Cream', 'DEFAULT', 3, 'Pack', ''),
(122, 39, 35, 'Canderel 18mg Tab 100s', 'DEFAULT', 2, 'Pack', ''),
(123, 39, 112, 'Tobra D E/D', 'DEFAULT', 1, 'Pack', ''),
(124, 40, 65, 'Tenormin 50mg Tab', 'DEFAULT', 2, 'Pack', ''),
(125, 40, 18, 'Famila 28F 3Cycle Tab', 'BAT-260929', 2, 'Pack', ''),
(126, 40, 17, 'ST.MOM 200mg Tab 10s', 'BAT-260929', 5, 'Pack', ''),
(127, 40, 74, 'Atenolol Tab', 'DEFAULT', 1, 'Pack', ''),
(128, 40, 75, 'ECP Tab', 'DEFAULT', 1, 'Pack', ''),
(129, 40, 96, 'Nuberol Tab 100s', 'DEFAULT', 1, 'Pack', ''),
(130, 40, 52, 'Levopraid  50mg Tab', 'BAT-260929', 1, 'Pack', ''),
(131, 40, 35, 'Canderel 18mg Tab 100s', 'DEFAULT', 1, 'Pack', ''),
(132, 41, 17, 'ST.MOM 200mg Tab 10s', 'DEFAULT', 1, 'Pack', ''),
(133, 41, 75, 'ECP Tab', 'DEFAULT', 4, 'Pack', ''),
(134, 41, 80, 'Adenuric 40mg Tab 20s', 'DEFAULT', 1, 'Pack', ''),
(135, 41, 81, 'Magnett 400mg Cap', 'DEFAULT', 2, 'Pack', ''),
(136, 41, 83, 'Polybion Z Cap', 'DEFAULT', 1, 'Pack', ''),
(137, 41, 136, 'Derma Smooth Lotion 120ml', 'DEFAULT', 1, 'Pack', ''),
(138, 41, 111, 'Tobra E/D', 'DEFAULT', 1, 'Pack', ''),
(139, 41, 38, 'Betnovate N Cream', 'DEFAULT', 2, 'Pack', ''),
(140, 42, 43, 'Zyrtec Syp 60ml', 'DEFAULT', 2, 'Pack', ''),
(141, 42, 42, 'Augmentin  156.25/5ml Syp', 'DEFAULT', 1, 'Pack', ''),
(142, 42, 41, 'Augmentin DS 312.5/5ml Syp', 'BAT-260929', 1, 'Pack', ''),
(143, 42, 13, 'Rhinosone P Spray 15ml', 'DEFAULT', 1, 'Pack', ''),
(144, 42, 16, 'Revital Multi Tab 45s', 'DEFAULT', 1, 'Pack', ''),
(145, 42, 32, 'Clobederm NN Oint 15g', 'BAT-260929', 2, 'Pack', ''),
(146, 43, 86, 'Lice -O-Nil Cream', 'DEFAULT', 5, 'Pack', ''),
(147, 43, 87, 'Venticort 400mg Cap', 'DEFAULT', 1, 'Pack', ''),
(148, 43, 73, 'Beceptor 10mg Tab', 'DEFAULT', 4, 'Pack', ''),
(149, 43, 49, 'Combivair 400mg Cap', 'DEFAULT', 2, 'Pack', ''),
(150, 43, 143, 'Treviamet 50/500 tab 14s', 'DEFAULT', 2, 'Pack', ''),
(151, 43, 130, 'Apranax 550mg Tab', 'DEFAULT', 2, 'Pack', ''),
(152, 43, 75, 'ECP Tab', 'DEFAULT', 5, 'Pack', ''),
(153, 44, 14, 'Azomax 250mg Cap 12s', 'DEFAULT', 2, 'Pack', ''),
(154, 44, 15, 'Azomax 500mg Tab', 'DEFAULT', 2, 'Pack', ''),
(155, 44, 58, 'Intig D Tab', 'DEFAULT', 1, 'Pack', ''),
(156, 45, 48, 'Flagyl 400mg Tab', 'BAT-260929', 1, 'Pack', ''),
(157, 46, 65, 'Tenormin 50mg Tab', 'DEFAULT', 1, 'Pack', ''),
(158, 46, 132, 'Atorva 20mg Tab 20s', 'DEFAULT', 1, 'Pack', ''),
(159, 46, 119, 'Spasfon Tab', 'DEFAULT', 1, 'Pack', ''),
(160, 47, 60, 'Fusiderm Cream', 'DEFAULT', 1, 'Pack', ''),
(161, 47, 88, 'Nirvanol 5mg Tab', 'DEFAULT', 2, 'Pack', ''),
(162, 48, 133, 'Atorva 40mg Tab 20s', 'DEFAULT', 2, 'Pack', ''),
(163, 48, 132, 'Atorva 20mg Tab 20s', 'DEFAULT', 4, 'Pack', ''),
(164, 48, 120, 'Myolax 4mg Tab', 'DEFAULT', 2, 'Pack', ''),
(165, 49, 140, 'Diampa LXR 25/5/1000 mg Tab 14s', 'DEFAULT', 5, 'Pack', ''),
(166, 49, 11, 'Empaa 10mg Tab 28s', 'DEFAULT', 1, 'Pack', ''),
(167, 50, 57, 'Methix Tab 20s', 'DEFAULT', 1, 'Pack', ''),
(168, 50, 55, 'Cellgee Tab 30s', 'DEFAULT', 1, 'Pack', ''),
(169, 51, 10, 'Ulsanic Syp 120ml', 'DEFAULT', 2, 'Pack', ''),
(170, 51, 75, 'ECP Tab', 'DEFAULT', 10, 'Pack', ''),
(171, 52, 75, 'ECP Tab', 'DEFAULT', 10, 'Pack', ''),
(172, 52, 129, 'Musidin 2mg Tab', 'DEFAULT', 4, 'Pack', ''),
(173, 52, 143, 'Treviamet 50/500 tab 14s', 'DEFAULT', 1, 'Pack', ''),
(174, 52, 111, 'Tobra E/D', 'DEFAULT', 2, 'Pack', ''),
(175, 52, 122, 'Xavor 50mg Tab', 'DEFAULT', 2, 'Pack', ''),
(176, 53, 44, 'Sunny D Inj', 'DEFAULT', 2, 'Pack', ''),
(177, 53, 13, 'Rhinosone P Spray 15ml', 'DEFAULT', 3, 'Pack', ''),
(178, 53, 112, 'Tobra D E/D', 'DEFAULT', 2, 'Pack', ''),
(179, 54, 38, 'Betnovate N Cream', 'DEFAULT', 3, 'Pack', ''),
(180, 54, 39, 'Calpol 6+ Syp 90ml', 'DEFAULT', 3, 'Pack', ''),
(181, 54, 40, 'Polyfax Skin Oint 20g', 'DEFAULT', 3, 'Pack', ''),
(182, 54, 43, 'Zyrtec Syp 60ml', 'DEFAULT', 2, 'Pack', ''),
(183, 54, 1, 'Clobevate Cream', 'DEFAULT', 4, 'Pack', ''),
(184, 54, 44, 'Sunny D Inj', 'DEFAULT', 1, 'Pack', ''),
(185, 55, 139, 'Diampa LXR 10/5/1000 mg Tab 14s', 'DEFAULT', 2, 'Pack', ''),
(186, 55, 93, 'Amodip V 5/80 Tab', 'DEFAULT', 1, 'Pack', ''),
(187, 55, 51, 'Levopraid 25mg Tab', 'DEFAULT', 2, 'Pack', ''),
(188, 55, 75, 'ECP Tab', 'DEFAULT', 10, 'Pack', ''),
(189, 55, 52, 'Levopraid  50mg Tab', 'DEFAULT', 2, 'Pack', ''),
(190, 55, 93, 'Amodip V 5/80 Tab', 'DEFAULT', 1, 'Pack', ''),
(191, 55, 109, 'Lipirex 20mg Tab', 'DEFAULT', 4, 'Pack', ''),
(192, 55, 123, 'Xavor DIU 50mg Tab', 'DEFAULT', 1, 'Pack', ''),
(193, 55, 16, 'Revital Multi Tab 45s', 'DEFAULT', 2, 'Pack', ''),
(194, 55, 23, 'E Clar Syp 60ml', 'BAT-260929', 1, 'Pack', ''),
(195, 55, 22, 'Alcuflex 550mg Tab 30s', 'DEFAULT', 1, 'Pack', ''),
(196, 55, 11, 'Empaa 10mg Tab 28s', 'DEFAULT', 1, 'Pack', ''),
(197, 55, 43, 'Zyrtec Syp 60ml', 'DEFAULT', 3, 'Pack', ''),
(198, 55, 130, 'Apranax 550mg Tab', 'DEFAULT', 1, 'Pack', ''),
(199, 55, 142, 'Montiget 10mg Tab', 'DEFAULT', 1, 'Pack', ''),
(200, 55, 86, 'Lice -O-Nil Cream', 'DEFAULT', 1, 'Pack', ''),
(201, 55, 143, 'Treviamet 50/500 tab 14s', 'DEFAULT', 1, 'Pack', ''),
(202, 55, 50, 'Craflim Tab', 'BAT-260929', 2, 'Pack', ''),
(203, 55, 101, 'HCQ 200Tab', 'DEFAULT', 1, 'Pack', ''),
(204, 55, 100, 'Rovista 5mg Tab', 'DEFAULT', 1, 'Pack', ''),
(205, 55, 119, 'Spasfon Tab', 'DEFAULT', 1, 'Pack', ''),
(206, 56, 48, 'Flagyl 400mg Tab', 'DEFAULT', 1, 'Pack', ''),
(207, 57, 65, 'Tenormin 50mg Tab', 'DEFAULT', 1, 'Pack', ''),
(208, 57, 78, 'Zafnol Tab', 'DEFAULT', 4, 'Pack', ''),
(209, 57, 95, 'Sita Met 50/1000mg Tab 14s', 'DEFAULT', 1, 'Pack', ''),
(210, 57, 94, 'Sita Met 50/500mg Tab 14s', 'DEFAULT', 1, 'Pack', ''),
(211, 57, 78, 'Zafnol Tab', 'DEFAULT', 1, 'Pack', ''),
(212, 58, 1, 'Clobevate Cream', 'DEFAULT', 6, 'Pack', ''),
(213, 58, 32, 'Clobederm NN Oint 15g', 'DEFAULT', 3, 'Pack', ''),
(214, 58, 29, 'Betaderm N Cream 15g', 'BAT-260929', 3, 'Pack', ''),
(215, 58, 30, 'Betaderm N Oint 15g', 'BAT-260929', 3, 'Pack', ''),
(216, 58, 8, 'Novoteph 40mg Cap', 'DEFAULT', 1, 'Pack', ''),
(217, 58, 38, 'Betnovate N Cream', 'DEFAULT', 3, 'Pack', ''),
(218, 59, 17, 'ST.MOM 200mg Tab 10s', 'DEFAULT', 2, 'Pack', ''),
(219, 59, 78, 'Zafnol Tab', 'DEFAULT', 1, 'Pack', ''),
(220, 59, 86, 'Lice -O-Nil Cream', 'DEFAULT', 1, 'Pack', ''),
(221, 59, 96, 'Nuberol Tab 100s', 'DEFAULT', 1, 'Pack', ''),
(222, 59, 27, 'Somogel Cream', 'DEFAULT', 2, 'Pack', ''),
(223, 60, 147, 'Surbex Z Tab', 'DEFAULT', 2, 'Pack', ''),
(224, 60, 22, 'Alcuflex 550mg Tab 30s', 'DEFAULT', 2, 'Pack', ''),
(225, 61, 69, 'Klaricid XL Tab 5s', 'DEFAULT', 2, 'Pack', ''),
(226, 62, 63, 'Ossobon D Tab', 'DEFAULT', 2, 'Pack', ''),
(227, 62, 130, 'Apranax 550mg Tab', 'DEFAULT', 2, 'Pack', ''),
(228, 62, 34, 'Xylor Tab 20s', 'DEFAULT', 2, 'Pack', ''),
(229, 62, 19, 'Wilgesic Fort Tab 100s', 'BAT-260929', 1, 'Pack', ''),
(230, 62, 55, 'Cellgee Tab 30s', '', 1, 'Pack', ''),
(231, 63, 63, 'Ossobon D Tab', 'DEFAULT', 2, 'Pack', ''),
(232, 63, 66, 'Tenormin 100mg Tab', 'DEFAULT', 1, 'Pack', ''),
(233, 63, 11, 'Empaa 10mg Tab 28s', 'DEFAULT', 2, 'Pack', ''),
(234, 64, 105, 'Sita 50mg Tab 14s', 'DEFAULT', 4, 'Pack', ''),
(235, 64, 112, 'Tobra D E/D', 'DEFAULT', 2, 'Pack', ''),
(236, 65, 57, 'Methix Tab 20s', '', 1, 'Pack', ''),
(237, 65, 9, 'Kestine 10mg Tab 14s', 'DEFAULT', 2, 'Pack', ''),
(238, 65, 101, 'HCQ 200Tab', 'DEFAULT', 2, 'Pack', ''),
(239, 65, 39, 'Calpol 6+ Syp 90ml', 'DEFAULT', 3, 'Pack', ''),
(240, 65, 147, 'Surbex Z Tab', 'DEFAULT', 4, 'Pack', ''),
(241, 66, 9, 'Kestine 10mg Tab 14s', 'DEFAULT', 1, 'Pack', ''),
(242, 66, 22, 'Alcuflex 550mg Tab 30s', 'DEFAULT', 1, 'Pack', ''),
(243, 66, 7, 'Novidat 250mg Tab', 'BAT-260929', 1, 'Pack', ''),
(244, 67, 18, 'Famila 28F 3Cycle Tab', 'DEFAULT', 1, 'Pack', ''),
(245, 67, 45, 'Entox P Tab', 'DEFAULT', 1, 'Pack', ''),
(246, 67, 115, 'Ezium 20mg Cap 14s', 'DEFAULT', 1, 'Pack', ''),
(247, 68, 60, 'Fusiderm Cream', 'DEFAULT', 1, 'Pack', ''),
(248, 68, 61, 'Fusiderm H Cream', 'DEFAULT', 1, 'Pack', ''),
(249, 68, 2, 'Velosef 500mg Cap', 'DEFAULT', 1, 'Pack', ''),
(250, 68, 86, 'Lice -O-Nil Cream', 'DEFAULT', 1, 'Pack', ''),
(251, 68, 94, 'Sita Met 50/500mg Tab 14s', 'DEFAULT', 3, 'Pack', ''),
(252, 68, 137, 'Sofvasc V 5/80mg Tab  14s', 'DEFAULT', 1, 'Pack', ''),
(253, 68, 58, 'Intig D Tab', 'DEFAULT', 1, 'Pack', ''),
(254, 68, 105, 'Sita 50mg Tab 14s', 'DEFAULT', 1, 'Pack', ''),
(255, 68, 113, 'Jentin Met 50/500mg Tab 14s', 'DEFAULT', 1, 'Pack', ''),
(256, 68, 118, 'Laprazol 30mg Cap 14s', 'DEFAULT', 1, 'Pack', ''),
(257, 68, 11, 'Empaa 10mg Tab 28s', 'DEFAULT', 1, 'Pack', ''),
(258, 68, 1, 'Clobevate Cream', 'DEFAULT', 10, 'Pack', ''),
(259, 68, 13, 'Rhinosone P Spray 15ml', 'DEFAULT', 2, 'Pack', ''),
(260, 69, 66, 'Tenormin 100mg Tab', 'DEFAULT', 2, 'Pack', ''),
(261, 69, 18, 'Famila 28F 3Cycle Tab', 'DEFAULT', 2, 'Pack', ''),
(262, 69, 17, 'ST.MOM 200mg Tab 10s', 'DEFAULT', 5, 'Pack', ''),
(263, 69, 74, 'Atenolol Tab', 'DEFAULT', 1, 'Pack', ''),
(264, 69, 75, 'ECP Tab', '', 1, 'Pack', ''),
(265, 69, 96, 'Nuberol Tab 100s', 'DEFAULT', 1, 'Pack', ''),
(266, 69, 52, 'Levopraid  50mg Tab', 'DEFAULT', 1, 'Pack', ''),
(267, 69, 35, 'Canderel 18mg Tab 100s', 'DEFAULT', 1, 'Pack', ''),
(268, 70, 63, 'Ossobon D Tab', 'DEFAULT', 2, 'Pack', ''),
(269, 70, 130, 'Apranax 550mg Tab', 'DEFAULT', 2, 'Pack', ''),
(270, 70, 19, 'Wilgesic Fort Tab 100s', 'DEFAULT', 1, 'Pack', ''),
(271, 70, 55, 'Cellgee Tab 30s', '', 1, 'Pack', ''),
(272, 71, 57, 'Methix Tab 20s', '', 1, 'Pack', ''),
(273, 71, 9, 'Kestine 10mg Tab 14s', 'DEFAULT', 2, 'Pack', ''),
(274, 71, 101, 'HCQ 200Tab', 'DEFAULT', 2, 'Pack', ''),
(275, 71, 39, 'Calpol 6+ Syp 90ml', 'DEFAULT', 3, 'Pack', ''),
(276, 71, 147, 'Surbex Z Tab', 'DEFAULT', 4, 'Pack', ''),
(277, 72, 63, 'Ossobon D Tab', 'DEFAULT', 2, 'Pack', ''),
(278, 72, 130, 'Apranax 550mg Tab', 'DEFAULT', 2, 'Pack', ''),
(279, 72, 34, 'Xylor Tab 20s', 'DEFAULT', 2, 'Pack', ''),
(280, 72, 19, 'Wilgesic Fort Tab 100s', 'DEFAULT', 1, 'Pack', ''),
(281, 72, 55, 'Cellgee Tab 30s', '', 1, 'Pack', ''),
(282, 73, 18, 'Famila 28F 3Cycle Tab', 'DEFAULT', 2, 'Pack', ''),
(283, 73, 66, 'Tenormin 100mg Tab', 'DEFAULT', 1, 'Pack', ''),
(284, 73, 17, 'ST.MOM 200mg Tab 10s', 'DEFAULT', 3, 'Pack', ''),
(285, 73, 123, 'Xavor DIU 50mg Tab', 'DEFAULT', 1, 'Pack', ''),
(286, 73, 9, 'Kestine 10mg Tab 14s', 'DEFAULT', 2, 'Pack', ''),
(287, 73, 147, 'Surbex Z Tab', 'DEFAULT', 7, 'Pack', ''),
(288, 74, 79, 'Zodip Tab', 'DEFAULT', 2, 'Pack', ''),
(289, 74, 39, 'Calpol 6+ Syp 90ml', 'DEFAULT', 1, 'Pack', ''),
(290, 74, 38, 'Betnovate N Cream', 'DEFAULT', 2, 'Pack', ''),
(291, 74, 35, 'Canderel 18mg Tab 100s', 'DEFAULT', 2, 'Pack', ''),
(292, 74, 7, 'Novidat 250mg Tab', 'DEFAULT', 1, 'Pack', ''),
(293, 74, 14, 'Azomax 250mg Cap 12s', 'DEFAULT', 1, 'Pack', ''),
(294, 74, 72, 'Cardura 2mg Tab', 'DEFAULT', 1, 'Pack', ''),
(295, 75, 61, 'Fusiderm H Cream', 'DEFAULT', 2, 'Pack', ''),
(296, 75, 132, 'Atorva 20mg Tab 20s', 'DEFAULT', 1, 'Pack', ''),
(297, 75, 57, 'Methix Tab 20s', '', 1, 'Pack', ''),
(298, 76, 64, 'Tenormin 25mg Tab', 'DEFAULT', 1, 'Pack', ''),
(299, 76, 65, 'Tenormin 50mg Tab', 'DEFAULT', 1, 'Pack', ''),
(300, 76, 75, 'ECP Tab', '', 6, 'Pack', ''),
(301, 76, 49, 'Combivair 400mg Cap', 'DEFAULT', 1, 'Pack', ''),
(302, 76, 111, 'Tobra E/D', 'DEFAULT', 2, 'Pack', ''),
(303, 76, 11, 'Empaa 10mg Tab 28s', 'DEFAULT', 1, 'Pack', ''),
(304, 76, 41, 'Augmentin DS 312.5/5ml Syp', 'DEFAULT', 1, 'Pack', ''),
(305, 77, 2, 'Velosef 500mg Cap', 'DEFAULT', 2, 'Pack', ''),
(306, 77, 65, 'Tenormin 50mg Tab', 'DEFAULT', 2, 'Pack', ''),
(307, 77, 97, 'Magnett 100/5ml Syp', 'DEFAULT', 2, 'Pack', ''),
(308, 77, 144, 'Treviamet 50/1000 tab 14s', 'DEFAULT', 1, 'Pack', ''),
(309, 77, 28, 'Betaderm Cream 15g', 'BAT-260929', 3, 'Pack', ''),
(310, 77, 38, 'Betnovate N Cream', 'DEFAULT', 4, 'Pack', ''),
(311, 77, 147, 'Surbex Z Tab', 'DEFAULT', 3, 'Pack', ''),
(312, 78, 9, 'Kestine 10mg Tab 14s', 'DEFAULT', 1, 'Pack', ''),
(313, 78, 10, 'Ulsanic Syp 120ml', 'DEFAULT', 1, 'Pack', ''),
(314, 78, 49, 'Combivair 400mg Cap', 'DEFAULT', 1, 'Pack', ''),
(315, 78, 61, 'Fusiderm H Cream', 'DEFAULT', 1, 'Pack', ''),
(316, 79, 2, 'Velosef 500mg Cap', 'DEFAULT', 2, 'Pack', ''),
(317, 79, 86, 'Lice -O-Nil Cream', 'DEFAULT', 1, 'Pack', ''),
(318, 79, 45, 'Entox P Tab', 'DEFAULT', 2, 'Pack', ''),
(319, 80, 78, 'Zafnol Tab', 'DEFAULT', 5, 'Pack', ''),
(320, 80, 79, 'Zodip Tab', 'DEFAULT', 5, 'Pack', ''),
(321, 80, 86, 'Lice -O-Nil Cream', 'DEFAULT', 3, 'Pack', ''),
(322, 80, 42, 'Augmentin  156.25/5ml Syp', 'DEFAULT', 2, 'Pack', ''),
(323, 81, 75, 'ECP Tab', '', 4, 'Pack', ''),
(324, 82, 10, 'Ulsanic Syp 120ml', 'DEFAULT', 3, 'Pack', ''),
(325, 82, 55, 'Cellgee Tab 30s', '', 2, 'Pack', ''),
(326, 82, 65, 'Tenormin 50mg Tab', 'DEFAULT', 2, 'Pack', ''),
(327, 82, 18, 'Famila 28F 3Cycle Tab', 'DEFAULT', 2, 'Pack', ''),
(328, 82, 92, 'Sea Cal Sachets', 'DEFAULT', 1, 'Pack', ''),
(329, 82, 93, 'Amodip V 5/80 Tab', 'DEFAULT', 1, 'Pack', ''),
(330, 82, 45, 'Entox P Tab', 'DEFAULT', 1, 'Pack', ''),
(331, 82, 103, 'Gablin 75mg Cap', 'DEFAULT', 1, 'Pack', ''),
(332, 82, 112, 'Tobra D E/D', 'DEFAULT', 1, 'Pack', ''),
(333, 82, 35, 'Canderel 18mg Tab 100s', 'DEFAULT', 2, 'Pack', ''),
(334, 83, 15, 'Azomax 500mg Tab', 'DEFAULT', 2, 'Pack', ''),
(335, 83, 51, 'Levopraid 25mg Tab', 'DEFAULT', 2, 'Pack', ''),
(336, 83, 5, 'Elezo 150 Cap', 'DEFAULT', 1, 'Pack', ''),
(337, 83, 28, 'Betaderm Cream 15g', 'DEFAULT', 5, 'Pack', ''),
(338, 83, 29, 'Betaderm N Cream 15g', 'DEFAULT', 3, 'Pack', ''),
(339, 83, 27, 'Somogel Cream', 'DEFAULT', 10, 'Pack', ''),
(340, 84, 123, 'Xavor DIU 50mg Tab', 'DEFAULT', 5, 'Pack', ''),
(341, 84, 111, 'Tobra E/D', 'DEFAULT', 2, 'Pack', ''),
(342, 85, 101, 'HCQ 200Tab', 'DEFAULT', 2, 'Pack', ''),
(343, 85, 53, 'Rovista 20mg Tab', 'BAT-260929', 1, 'Pack', ''),
(344, 85, 79, 'Zodip Tab', 'DEFAULT', 6, 'Pack', ''),
(345, 86, 74, 'Atenolol Tab', 'DEFAULT', 1, 'Pack', ''),
(346, 86, 94, 'Sita Met 50/500mg Tab 14s', 'DEFAULT', 3, 'Pack', ''),
(347, 86, 122, 'Xavor 50mg Tab', 'DEFAULT', 2, 'Pack', ''),
(348, 87, 53, 'Rovista 20mg Tab', 'DEFAULT', 1, 'Pack', ''),
(349, 87, 14, 'Azomax 250mg Cap 12s', 'DEFAULT', 1, 'Pack', ''),
(350, 87, 94, 'Sita Met 50/500mg Tab 14s', 'DEFAULT', 2, 'Pack', ''),
(351, 87, 96, 'Nuberol Tab 100s', 'DEFAULT', 1, 'Pack', ''),
(352, 87, 141, 'leflox 250mg Tab', 'DEFAULT', 2, 'Pack', ''),
(353, 87, 102, 'Lophos Tab 100s', 'DEFAULT', 1, 'Pack', ''),
(354, 87, 110, 'Minoxin Plus 5% Sol 60ml', 'DEFAULT', 1, 'Pack', ''),
(355, 87, 10, 'Ulsanic Syp 120ml', 'DEFAULT', 1, 'Pack', ''),
(356, 87, 11, 'Empaa 10mg Tab 28s', 'DEFAULT', 1, 'Pack', ''),
(357, 87, 147, 'Surbex Z Tab', '78997', 5, 'Pack', ''),
(358, 88, 74, 'Atenolol Tab', '410', 1, 'Pack', ''),
(359, 88, 94, 'Sita Met 50/500mg Tab 14s', 'EV131', 3, 'Pack', ''),
(360, 88, 122, 'Xavor 50mg Tab', '260704', 2, 'Pack', ''),
(361, 89, 123, 'Xavor DIU 50mg Tab', '252394', 5, 'Pack', ''),
(362, 89, 111, 'Tobra E/D', 'TBD396', 2, 'Pack', ''),
(363, 90, 101, 'HCQ 200Tab', 'F14110', 2, 'Pack', ''),
(364, 90, 53, 'Rovista 20mg Tab', 'F07059', 1, 'Pack', ''),
(365, 90, 79, 'Zodip Tab', '558', 6, 'Pack', ''),
(366, 91, 15, 'Azomax 500mg Tab', 'N6970', 2, 'Pack', ''),
(367, 91, 51, 'Levopraid 25mg Tab', 'LN2509U', 2, 'Pack', ''),
(368, 91, 5, 'Elezo 150 Cap', '055O004', 1, 'Pack', ''),
(369, 91, 28, 'Betaderm Cream 15g', 'JU014M', 5, 'Pack', ''),
(370, 91, 29, 'Betaderm N Cream 15g', 'JW017M', 3, 'Pack', ''),
(371, 91, 27, 'Somogel Cream', '(10) 902623XV', 10, 'Pack', ''),
(372, 92, 10, 'Ulsanic Syp 120ml', '261089', 3, 'Pack', ''),
(373, 92, 55, 'Cellgee Tab 30s', '', 2, 'Pack', ''),
(374, 92, 65, 'Tenormin 50mg Tab', '264D019', 2, 'Pack', ''),
(375, 92, 18, 'Famila 28F 3Cycle Tab', 'K325', 2, 'Pack', ''),
(376, 92, 92, 'Sea Cal Sachets', '255', 1, 'Pack', ''),
(377, 92, 93, 'Amodip V 5/80 Tab', 'ANO46', 1, 'Pack', ''),
(378, 92, 45, 'Entox P Tab', '265B078', 1, 'Pack', ''),
(379, 92, 103, 'Gablin 75mg Cap', 'FJ005', 1, 'Pack', ''),
(380, 92, 112, 'Tobra D E/D', '(10) TDD804', 1, 'Pack', ''),
(381, 92, 35, 'Canderel 18mg Tab 100s', 'ACH047', 2, 'Pack', ''),
(382, 93, 75, 'ECP Tab', '', 4, 'Pack', ''),
(383, 93, 39, 'Calpol 6+ Syp 90ml', '588C', 2, 'Pack', ''),
(384, 94, 75, 'ECP Tab', '', 4, 'Pack', ''),
(385, 94, 39, 'Calpol 6+ Syp 90ml', '588C', 2, 'Pack', ''),
(386, 95, 75, 'ECP Tab', '', 4, 'Pack', ''),
(387, 95, 39, 'Calpol 6+ Syp 90ml', '588C', 2, 'Pack', ''),
(388, 96, 75, 'ECP Tab', '', 4, 'Pack', ''),
(389, 96, 39, 'Calpol 6+ Syp 90ml', '588C', 2, 'Pack', ''),
(390, 97, 10, 'Ulsanic Syp 120ml', '261089', 3, 'Pack', ''),
(391, 97, 55, 'Cellgee Tab 30s', '', 2, 'Pack', ''),
(392, 97, 65, 'Tenormin 50mg Tab', '264D019', 2, 'Pack', ''),
(393, 97, 18, 'Famila 28F 3Cycle Tab', 'K325', 2, 'Pack', ''),
(394, 97, 92, 'Sea Cal Sachets', '255', 1, 'Pack', ''),
(395, 97, 93, 'Amodip V 5/80 Tab', 'ANO46', 1, 'Pack', ''),
(396, 97, 45, 'Entox P Tab', '265B078', 1, 'Pack', ''),
(397, 97, 103, 'Gablin 75mg Cap', 'FJ005', 1, 'Pack', ''),
(398, 97, 112, 'Tobra D E/D', '(10) TDD804', 1, 'Pack', ''),
(399, 97, 35, 'Canderel 18mg Tab 100s', 'ACH047', 2, 'Pack', ''),
(400, 98, 2, 'Velosef 500mg Cap', '389V', 2, 'Pack', ''),
(401, 98, 86, 'Lice -O-Nil Cream', '(10) 3918', 1, 'Pack', ''),
(402, 98, 45, 'Entox P Tab', '265B078', 2, 'Pack', ''),
(403, 99, 9, 'Kestine 10mg Tab 14s', '261322', 1, 'Pack', ''),
(404, 99, 10, 'Ulsanic Syp 120ml', '261089', 1, 'Pack', ''),
(405, 99, 49, 'Combivair 400mg Cap', '260325', 1, 'Pack', ''),
(406, 99, 61, 'Fusiderm H Cream', 'F107', 1, 'Pack', ''),
(407, 100, 2, 'Velosef 500mg Cap', '389V', 2, 'Pack', ''),
(408, 100, 65, 'Tenormin 50mg Tab', '264D019', 2, 'Pack', ''),
(409, 100, 97, 'Magnett 100/5ml Syp', 'DEFAULT', 2, 'Pack', ''),
(410, 100, 144, 'Treviamet 50/1000 tab 14s', 'F08183', 1, 'Pack', ''),
(411, 100, 28, 'Betaderm Cream 15g', 'JU014M', 3, 'Pack', ''),
(412, 100, 38, 'Betnovate N Cream', '5M7E', 4, 'Pack', ''),
(413, 100, 147, 'Surbex Z Tab', '872487XV', 3, 'Pack', ''),
(414, 101, 64, 'Tenormin 25mg Tab', '263D005', 1, 'Pack', ''),
(415, 101, 65, 'Tenormin 50mg Tab', '264D019', 1, 'Pack', ''),
(416, 101, 75, 'ECP Tab', '', 3, 'Pack', ''),
(417, 101, 49, 'Combivair 400mg Cap', '260325', 1, 'Pack', ''),
(418, 101, 111, 'Tobra E/D', 'TBD396', 2, 'Pack', ''),
(419, 101, 11, 'Empaa 10mg Tab 28s', '997', 1, 'Pack', ''),
(420, 101, 41, 'Augmentin DS 312.5/5ml Syp', 'AH4S', 1, 'Pack', ''),
(421, 102, 61, 'Fusiderm H Cream', 'F107', 2, 'Pack', ''),
(422, 102, 132, 'Atorva 20mg Tab 20s', 'R14AE', 1, 'Pack', ''),
(423, 102, 57, 'Methix Tab 20s', 'PFT2016', 1, 'Pack', ''),
(424, 103, 53, 'Rovista 20mg Tab', 'F07059', 1, 'Pack', ''),
(425, 103, 14, 'Azomax 250mg Cap 12s', 'N7522', 1, 'Pack', ''),
(426, 103, 94, 'Sita Met 50/500mg Tab 14s', 'EV131', 2, 'Pack', ''),
(427, 103, 96, 'Nuberol Tab 100s', 'CEH097', 1, 'Pack', ''),
(428, 103, 141, 'leflox 250mg Tab', 'F01171', 2, 'Pack', ''),
(429, 103, 102, 'Lophos Tab 100s', '864RA', 1, 'Pack', ''),
(430, 103, 110, 'Minoxin Plus 5% Sol 60ml', '082126', 1, 'Pack', ''),
(431, 103, 10, 'Ulsanic Syp 120ml', '261089', 1, 'Pack', ''),
(432, 103, 11, 'Empaa 10mg Tab 28s', '997', 1, 'Pack', ''),
(433, 103, 147, 'Surbex Z Tab', '872487XV', 5, 'Pack', ''),
(434, 104, 79, 'Zodip Tab', '558', 2, 'Pack', ''),
(435, 104, 39, 'Calpol 6+ Syp 90ml', '588C', 1, 'Pack', ''),
(436, 104, 38, 'Betnovate N Cream', '5M7E', 2, 'Pack', ''),
(437, 104, 35, 'Canderel 18mg Tab 100s', 'ACH047', 2, 'Pack', ''),
(438, 104, 7, 'Novidat 250mg Tab', '138P020', 1, 'Pack', ''),
(439, 104, 14, 'Azomax 250mg Cap 12s', 'N7522', 1, 'Pack', ''),
(440, 104, 72, 'Cardura 2mg Tab', 'N7325', 1, 'Pack', ''),
(441, 105, 18, 'Famila 28F 3Cycle Tab', 'K325', 2, 'Pack', ''),
(442, 105, 66, 'Tenormin 100mg Tab', '265D003', 1, 'Pack', ''),
(443, 105, 17, 'ST.MOM 200mg Tab 10s', '814', 3, 'Pack', ''),
(444, 105, 123, 'Xavor DIU 50mg Tab', '252394', 1, 'Pack', ''),
(445, 105, 9, 'Kestine 10mg Tab 14s', '261322', 2, 'Pack', ''),
(446, 105, 147, 'Surbex Z Tab', '872487XV', 7, 'Pack', ''),
(447, 106, 78, 'Zafnol Tab', 'DEFAULT', 5, 'Pack', ''),
(448, 106, 79, 'Zodip Tab', '558', 5, 'Pack', ''),
(449, 106, 86, 'Lice -O-Nil Cream', '(10) 3918', 3, 'Pack', ''),
(450, 106, 42, 'Augmentin  156.25/5ml Syp', '8N4X', 2, 'Pack', '');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_riders`
--

CREATE TABLE `delivery_riders` (
  `id` int NOT NULL,
  `rider_name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `phone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `vehicle_no` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `vehicle_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'Delivery Van',
  `cnic` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_general_ci,
  `status` enum('Active','Inactive') COLLATE utf8mb4_general_ci DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `emp_code` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `employee_type` enum('general','salesman') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'salesman',
  `phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `area` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cnic` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `salary` decimal(12,2) DEFAULT '0.00',
  `commission_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `status` tinyint(1) DEFAULT '1',
  `created_at` date DEFAULT NULL,
  `updated_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `user_id`, `emp_code`, `full_name`, `employee_type`, `phone`, `area`, `cnic`, `address`, `joining_date`, `salary`, `commission_rate`, `status`, `created_at`, `updated_at`) VALUES
(1, 8, 'EMP-0001', 'Sherazi', 'salesman', '0329_9339000', 'Sanda Kalan', '00000000000', 'ARG', '2026-09-29', 0.00, 2.00, 1, '2026-09-29', '2026-10-01'),
(2, 9, 'EMP-0002', 'Raju', 'salesman', '0309_1453956', '', '00000000000', 'Rana Town', '2026-09-29', 0.00, 2.00, 0, '2026-09-29', '2026-10-07');

-- --------------------------------------------------------

--
-- Table structure for table `employee_salaries`
--

CREATE TABLE `employee_salaries` (
  `id` int NOT NULL,
  `slip_no` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `employee_id` int NOT NULL,
  `salary_month` varchar(7) COLLATE utf8mb4_general_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` enum('cash','bank') COLLATE utf8mb4_general_ci DEFAULT 'cash',
  `bank_account_id` int DEFAULT NULL,
  `payment_date` date NOT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_by` int DEFAULT NULL,
  `created_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int NOT NULL,
  `voucher_no` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `expense_date` date NOT NULL,
  `category_id` int DEFAULT NULL,
  `expense_category` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `title` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` enum('Cash','Bank') COLLATE utf8mb4_general_ci DEFAULT 'Cash',
  `cash_account_id` int DEFAULT NULL,
  `bank_account_id` int DEFAULT NULL,
  `paid_to` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `payee_name` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `vendor_name` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `bill_no` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `receipt_no` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `voucher_no`, `expense_date`, `category_id`, `expense_category`, `title`, `amount`, `payment_method`, `cash_account_id`, `bank_account_id`, `paid_to`, `payee_name`, `description`, `vendor_name`, `bill_no`, `receipt_no`, `created_by`, `created_at`) VALUES
(1, 'EXP-0001', '2026-10-01', 1, NULL, NULL, 30000.00, 'Cash', NULL, NULL, NULL, NULL, 'Monthly Rent', 'Kashif', '', NULL, 1, '2026-10-01'),
(2, 'EXP-0002', '2026-10-01', 2, NULL, NULL, 17000.00, 'Cash', NULL, NULL, NULL, NULL, 'Monthly Salary', 'D. pharmacist', '', NULL, 1, '2026-10-01'),
(3, 'EXP-0003', '2026-10-01', 3, NULL, NULL, 1700.00, 'Cash', NULL, NULL, NULL, NULL, 'Monthly E. Bill', 'Kashif', '', NULL, 1, '2026-10-01'),
(4, 'EXP-0004', '2026-10-07', 4, NULL, NULL, 5500.00, 'Cash', NULL, NULL, NULL, NULL, 'Asad', '', '', NULL, 1, '2026-10-07');

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

CREATE TABLE `expense_categories` (
  `id` int NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `status` enum('Active','Inactive') COLLATE utf8mb4_general_ci DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expense_categories`
--

INSERT INTO `expense_categories` (`id`, `name`, `description`, `status`) VALUES
(1, 'Flat Rent', '', 'Active'),
(2, 'D. Pharmacist', '', 'Active'),
(3, 'Electric Bill', '', 'Active'),
(4, 'Fuel ⛽', '', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `opening_stock_logs`
--

CREATE TABLE `opening_stock_logs` (
  `id` int NOT NULL,
  `product_id` int NOT NULL,
  `batch_no` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `purchase_rate` decimal(12,2) DEFAULT '0.00',
  `trade_rate` decimal(12,2) DEFAULT '0.00',
  `total_value` decimal(14,2) DEFAULT '0.00',
  `entry_date` date NOT NULL,
  `remarks` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `opening_stock_logs`
--

INSERT INTO `opening_stock_logs` (`id`, `product_id`, `batch_no`, `expiry_date`, `quantity`, `purchase_rate`, `trade_rate`, `total_value`, `entry_date`, `remarks`, `created_by`) VALUES
(1, 1, 'BAT-260929', '2028-09-29', 1.00, 185.05, 185.05, 185.05, '2026-09-28', 'Opening stock added during product creation', 1),
(2, 2, 'BAT-260929', '2028-09-29', 1.00, 603.17, 603.17, 603.17, '2026-09-28', 'Opening stock added during product creation', 1),
(3, 3, 'BAT-260929', '2028-09-29', 1.00, 1219.75, 1219.75, 1219.75, '2026-09-28', 'Opening stock added during product creation', 1),
(4, 4, 'BAT-260929', '2028-09-29', 1.00, 428.66, 428.66, 428.66, '2026-09-28', 'Opening stock added during product creation', 1),
(5, 5, 'BAT-260929', '2028-09-29', 1.00, 573.75, 573.75, 573.75, '2026-09-28', 'Opening stock added during product creation', 1),
(6, 6, 'BAT-260929', '2028-09-29', 1.00, 340.00, 340.00, 340.00, '2026-09-28', 'Opening stock added during product creation', 1),
(7, 7, 'BAT-260929', '2028-09-29', 1.00, 233.75, 233.75, 233.75, '2026-09-28', 'Opening stock added during product creation', 1),
(8, 8, 'BAT-260929', '2028-09-29', 1.00, 433.50, 433.50, 433.50, '2026-09-28', 'Opening stock added during product creation', 1),
(9, 9, 'BAT-260929', '2028-09-29', 1.00, 252.71, 252.71, 252.71, '2026-09-28', 'Opening stock added during product creation', 1),
(10, 10, 'BAT-260929', '2028-09-29', 1.00, 351.48, 351.48, 351.48, '2026-09-28', 'Opening stock added during product creation', 1),
(11, 11, 'BAT-260929', '2028-09-29', 1.00, 833.00, 833.00, 833.00, '2026-09-28', 'Opening stock added during product creation', 1),
(12, 12, 'BAT-260929', '2028-09-29', 1.00, 904.40, 904.40, 904.40, '2026-09-28', 'Opening stock added during product creation', 1),
(13, 13, 'BAT-260929', '2028-09-29', 1.00, 199.75, 199.75, 199.75, '2026-09-28', 'Opening stock added during product creation', 1),
(14, 14, 'BAT-260929', '2028-09-29', 1.00, 675.19, 675.19, 675.19, '2026-09-28', 'Opening stock added during product creation', 1),
(15, 15, 'BAT-260929', '2028-09-29', 1.00, 466.40, 466.40, 466.40, '2026-09-28', 'Opening stock added during product creation', 1),
(16, 16, 'BAT-260929', '2028-09-29', 1.00, 696.45, 696.45, 696.45, '2026-09-28', 'Opening stock added during product creation', 1),
(17, 17, 'BAT-260929', '2028-09-29', 1.00, 169.60, 169.60, 169.60, '2026-09-28', 'Opening stock added during product creation', 1),
(18, 18, 'BAT-260929', '2028-09-29', 1.00, 127.39, 127.39, 127.39, '2026-09-28', 'Opening stock added during product creation', 1),
(19, 19, 'BAT-260929', '2028-09-29', 1.00, 782.00, 782.00, 782.00, '2026-09-28', 'Opening stock added during product creation', 1),
(22, 22, 'BAT-260929', '2028-09-29', 1.00, 580.89, 580.89, 580.89, '2026-09-28', 'Opening stock added during product creation', 1),
(23, 23, 'BAT-260929', '2028-09-29', 1.00, 350.20, 350.20, 350.20, '2026-09-28', 'Opening stock added during product creation', 1),
(24, 24, 'BAT-260929', '2028-09-29', 1.00, 327.25, 327.25, 327.25, '2026-09-28', 'Opening stock added during product creation', 1),
(25, 25, 'BAT-260929', '2028-09-29', 1.00, 464.64, 464.64, 464.64, '2026-09-28', 'Opening stock added during product creation', 1),
(26, 26, 'BAT-260929', '2028-09-29', 1.00, 841.62, 841.62, 841.62, '2026-09-28', 'Opening stock added during product creation', 1),
(27, 27, 'BAT-260929', '2028-09-29', 1.00, 165.75, 165.75, 165.75, '2026-09-28', 'Opening stock added during product creation', 1),
(28, 28, 'BAT-260929', '2028-09-29', 1.00, 61.20, 61.20, 61.20, '2026-09-28', 'Opening stock added during product creation', 1),
(29, 29, 'BAT-260929', '2028-09-29', 1.00, 102.00, 102.00, 102.00, '2026-09-28', 'Opening stock added during product creation', 1),
(30, 30, 'BAT-260929', '2028-09-29', 1.00, 102.00, 102.00, 102.00, '2026-09-28', 'Opening stock added during product creation', 1),
(31, 31, 'BAT-260929', '2028-09-29', 1.00, 61.20, 61.20, 61.20, '2026-09-28', 'Opening stock added during product creation', 1),
(32, 32, 'BAT-260929', '2028-09-29', 1.00, 127.50, 127.50, 127.50, '2026-09-28', 'Opening stock added during product creation', 1),
(33, 33, 'BAT-260929', '2028-09-29', 1.00, 382.50, 382.50, 382.50, '2026-09-28', 'Opening stock added during product creation', 1),
(34, 34, 'BAT-260929', '2028-09-29', 1.00, 242.53, 242.53, 242.53, '2026-09-28', 'Opening stock added during product creation', 1),
(35, 35, 'BAT-260929', '2028-09-29', 1.00, 236.02, 236.02, 236.02, '2026-09-28', 'Opening stock added during product creation', 1),
(36, 36, 'BAT-260929', '2028-09-29', 1.00, 431.60, 431.60, 431.60, '2026-09-28', 'Opening stock added during product creation', 1),
(37, 38, 'BAT-260929', '2028-09-29', 1.00, 153.53, 153.53, 153.53, '2026-09-28', 'Opening stock added during product creation', 1),
(38, 39, 'BAT-260929', '2028-09-29', 1.00, 101.48, 101.48, 101.48, '2026-09-28', 'Opening stock added during product creation', 1),
(39, 40, 'BAT-260929', '2028-09-29', 1.00, 190.03, 190.03, 190.03, '2026-09-28', 'Opening stock added during product creation', 1),
(40, 41, 'BAT-260929', '2028-09-29', 1.00, 531.57, 531.57, 531.57, '2026-09-28', 'Opening stock added during product creation', 1),
(41, 42, 'BAT-260929', '2028-09-29', 1.00, 322.69, 322.69, 322.69, '2026-09-28', 'Opening stock added during product creation', 1),
(42, 43, 'BAT-260929', '2028-09-29', 1.00, 134.89, 134.89, 134.89, '2026-09-28', 'Opening stock added during product creation', 1),
(43, 44, 'BAT-260929', '2028-09-29', 1.00, 208.25, 208.25, 208.25, '2026-09-28', 'Opening stock added during product creation', 1),
(44, 45, 'BAT-260929', '2028-09-29', 1.00, 442.00, 442.00, 442.00, '2026-09-28', 'Opening stock added during product creation', 1),
(45, 46, 'BAT-260929', '2028-09-29', 1.00, 1275.00, 1275.00, 1275.00, '2026-09-28', 'Opening stock added during product creation', 1),
(46, 47, 'BAT-260929', '2028-09-29', 1.00, 187.00, 187.00, 187.00, '2026-09-28', 'Opening stock added during product creation', 1),
(47, 48, 'BAT-260929', '2028-09-29', 1.00, 809.00, 809.00, 809.00, '2026-09-28', 'Opening stock added during product creation', 1),
(48, 49, 'BAT-260929', '2028-09-29', 1.00, 619.82, 619.82, 619.82, '2026-09-28', 'Opening stock added during product creation', 1),
(49, 50, 'BAT-260929', '2028-09-29', 1.00, 301.58, 301.58, 301.58, '2026-09-28', 'Opening stock added during product creation', 1),
(50, 51, 'BAT-260929', '2028-09-29', 1.00, 456.93, 456.93, 456.93, '2026-09-28', 'Opening stock added during product creation', 1),
(51, 52, 'BAT-260929', '2028-09-29', 1.00, 790.66, 790.66, 790.66, '2026-09-28', 'Opening stock added during product creation', 1),
(52, 53, 'BAT-260929', '2028-09-29', 1.00, 1713.60, 1713.60, 1713.60, '2026-09-28', 'Opening stock added during product creation', 1),
(54, 55, 'BAT-260929', '2028-09-29', 1.00, 922.03, 922.03, 922.03, '2026-09-29', 'Opening stock added during product creation', 1),
(56, 57, 'BAT-260929', '2028-09-29', 1.00, 922.03, 922.03, 922.03, '2026-09-29', 'Opening stock added during product creation', 1),
(57, 58, 'BAT-260929', '2028-09-29', 1.00, 446.25, 446.25, 446.25, '2026-09-29', 'Opening stock added during product creation', 1);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int NOT NULL,
  `product_code` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `barcode` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `name` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `generic_name` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `company_id` int DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `unit_id` int DEFAULT NULL,
  `pack_size` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `packs_per_box` int DEFAULT '1',
  `tablets_per_pack` int DEFAULT '10',
  `total_tablets_per_box` int DEFAULT '10',
  `stock_unit` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'Pack',
  `purchase_price` decimal(12,2) DEFAULT '0.00',
  `trade_price` decimal(12,2) DEFAULT '0.00',
  `retail_price` decimal(12,2) DEFAULT '0.00',
  `wholesale_price` decimal(12,2) DEFAULT '0.00',
  `discount_percent` decimal(5,2) DEFAULT '0.00',
  `max_discount_percent` decimal(5,2) DEFAULT '0.00',
  `reorder_level` int DEFAULT '10',
  `opening_stock` int DEFAULT '0',
  `current_stock` int DEFAULT '0',
  `location_rack` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `requires_prescription` tinyint(1) DEFAULT '0',
  `cold_chain` tinyint(1) DEFAULT '0',
  `status` enum('Active','Inactive') COLLATE utf8mb4_general_ci DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `product_code`, `barcode`, `name`, `generic_name`, `company_id`, `category_id`, `unit_id`, `pack_size`, `packs_per_box`, `tablets_per_pack`, `total_tablets_per_box`, `stock_unit`, `purchase_price`, `trade_price`, `retail_price`, `wholesale_price`, `discount_percent`, `max_discount_percent`, `reorder_level`, `opening_stock`, `current_stock`, `location_rack`, `requires_prescription`, `cold_chain`, `status`) VALUES
(1, 'PRD-0001', NULL, 'Clobevate Cream', NULL, NULL, 1, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 185.05, 185.05, 175.80, 175.80, 5.00, 5.00, 20, 1, 369, NULL, 0, 0, 'Active'),
(2, 'PRD-0002', NULL, 'Velosef 500mg Cap', NULL, NULL, 2, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 603.17, 603.17, 573.01, 573.01, 5.00, 5.00, 20, 1, 244, NULL, 0, 0, 'Active'),
(3, 'PRD-0003', NULL, 'Savesto 50mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 1219.75, 1219.75, 1207.55, 1207.55, 1.00, 1.00, 20, 1, 12, NULL, 0, 0, 'Active'),
(4, 'PRD-0004', NULL, 'Bisleri Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 428.66, 428.66, 424.37, 424.37, 1.00, 1.00, 20, 1, 42, NULL, 0, 0, 'Active'),
(5, 'PRD-0005', NULL, 'Elezo 150 Cap', NULL, NULL, 2, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 573.75, 573.75, 568.01, 568.01, 1.00, 1.00, 20, 1, 8, NULL, 0, 0, 'Active'),
(6, 'PRD-0006', NULL, 'Grasil 500mg Ini', NULL, NULL, 4, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 340.00, 340.00, 336.60, 336.60, 1.00, 1.00, 20, 1, 31, NULL, 0, 0, 'Active'),
(7, 'PRD-0007', NULL, 'Novidat 250mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 233.75, 233.75, 231.41, 231.41, 1.00, 1.00, 20, 1, 29, NULL, 0, 0, 'Active'),
(8, 'PRD-0008', NULL, 'Novoteph 40mg Cap', NULL, NULL, 2, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 433.50, 433.50, 429.17, 429.17, 1.00, 1.00, 20, 1, 22, NULL, 0, 0, 'Active'),
(9, 'PRD-0009', NULL, 'Kestine 10mg Tab 14s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 252.71, 252.71, 247.66, 247.66, 2.00, 2.00, 20, 1, 49, NULL, 0, 0, 'Active'),
(10, 'PRD-0010', NULL, 'Ulsanic Syp 120ml', NULL, NULL, 5, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 351.48, 351.48, 344.45, 344.45, 2.00, 2.00, 20, 1, 43, NULL, 0, 0, 'Active'),
(11, 'PRD-0011', NULL, 'Empaa 10mg Tab 28s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 833.00, 833.00, 816.34, 816.34, 2.00, 2.00, 20, 1, 3, NULL, 0, 0, 'Active'),
(12, 'PRD-0012', NULL, 'Empaa M 12.5/500mg Tab 28s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 904.40, 904.40, 886.31, 886.31, 2.00, 2.00, 20, 1, 9, NULL, 0, 0, 'Active'),
(13, 'PRD-0013', NULL, 'Rhinosone P Spray 15ml', NULL, NULL, 6, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 199.75, 199.75, 195.76, 195.76, 2.00, 2.00, 20, 1, 42, NULL, 0, 0, 'Active'),
(14, 'PRD-0014', NULL, 'Azomax 250mg Cap 12s', NULL, NULL, 2, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 675.19, 675.19, 668.44, 668.44, 1.00, 1.00, 20, 1, 96, NULL, 0, 0, 'Active'),
(15, 'PRD-0015', NULL, 'Azomax 500mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 466.40, 466.40, 461.74, 461.74, 1.00, 1.00, 20, 1, 95, NULL, 0, 0, 'Active'),
(16, 'PRD-0016', NULL, 'Revital Multi Tab 45s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 696.45, 696.45, 682.52, 682.52, 2.00, 2.00, 20, 1, 49, NULL, 0, 0, 'Active'),
(17, 'PRD-0017', NULL, 'ST.MOM 200mg Tab 10s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 169.60, 169.60, 166.21, 166.21, 2.00, 2.00, 20, 1, 217, NULL, 0, 0, 'Active'),
(18, 'PRD-0018', NULL, 'Famila 28F 3Cycle Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 127.39, 127.39, 124.84, 124.84, 2.00, 2.00, 20, 1, 212, NULL, 0, 0, 'Active'),
(19, 'PRD-0019', NULL, 'Wilgesic Fort Tab 100s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 782.00, 782.00, 719.44, 719.44, 8.00, 8.00, 20, 1, 50, NULL, 0, 0, 'Active'),
(22, 'PRD-0022', NULL, 'Alcuflex 550mg Tab 30s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 580.89, 580.89, 551.85, 551.85, 5.00, 5.00, 20, 1, 33, NULL, 0, 0, 'Active'),
(23, 'PRD-0023', NULL, 'E Clar Syp 60ml', NULL, NULL, 5, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 262.65, 350.20, 332.69, 332.69, 5.00, 5.00, 20, 1, 12, NULL, 0, 0, 'Active'),
(24, 'PRD-0024', NULL, 'E Clar 250mg Tab 10s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 327.25, 327.25, 310.89, 310.89, 5.00, 5.00, 20, 1, 13, NULL, 0, 0, 'Active'),
(25, 'PRD-0025', NULL, 'Klaricid 250mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 464.64, 464.64, 455.35, 455.35, 2.00, 2.00, 20, 1, 55, NULL, 0, 0, 'Active'),
(26, 'PRD-0026', NULL, 'Klaricid 500mg Tab 10s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 841.62, 841.62, 824.79, 824.79, 2.00, 2.00, 20, 1, 64, NULL, 0, 0, 'Active'),
(27, 'PRD-0027', NULL, 'Somogel Cream', NULL, NULL, 1, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 165.75, 165.75, 160.78, 160.78, 3.00, 3.00, 20, 1, 122, NULL, 0, 0, 'Active'),
(28, 'PRD-0028', NULL, 'Betaderm Cream 15g', NULL, NULL, 1, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 61.20, 61.20, 59.36, 59.36, 3.00, 3.00, 20, 1, 137, NULL, 0, 0, 'Active'),
(29, 'PRD-0029', NULL, 'Betaderm N Cream 15g', NULL, NULL, 1, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 102.00, 102.00, 96.90, 96.90, 5.00, 5.00, 20, 1, 283, NULL, 0, 0, 'Active'),
(30, 'PRD-0030', NULL, 'Betaderm N Oint 15g', NULL, NULL, 7, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 102.00, 102.00, 96.90, 96.90, 5.00, 5.00, 20, 1, 286, NULL, 0, 0, 'Active'),
(31, 'PRD-0031', NULL, 'Betaderm Oint 15g', NULL, NULL, 7, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 61.20, 61.20, 58.14, 58.14, 5.00, 5.00, 20, 1, 145, NULL, 0, 0, 'Active'),
(32, 'PRD-0032', NULL, 'Clobederm NN Oint 15g', NULL, NULL, 7, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 127.50, 127.50, 121.13, 121.13, 5.00, 5.00, 20, 1, 283, NULL, 0, 0, 'Active'),
(33, 'PRD-0033', NULL, 'Solo 10mg Tab 14s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 382.50, 382.50, 344.25, 344.25, 10.00, 10.00, 20, 1, 21, NULL, 0, 0, 'Active'),
(34, 'PRD-0034', NULL, 'Xylor Tab 20s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 242.53, 242.53, 218.28, 218.28, 10.00, 10.00, 20, 1, 42, NULL, 0, 0, 'Active'),
(35, 'PRD-0035', NULL, 'Canderel 18mg Tab 100s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 236.02, 236.02, 236.02, 236.02, 0.00, 0.00, 20, 1, 44, NULL, 0, 0, 'Active'),
(36, 'PRD-0036', NULL, 'Canderel Tab 225s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 431.60, 431.60, 431.60, 431.60, 0.00, 0.00, 20, 1, 61, NULL, 0, 0, 'Active'),
(38, 'PRD-0037', NULL, 'Betnovate N Cream', NULL, NULL, 1, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 153.53, 153.53, 145.85, 145.85, 5.00, 5.00, 20, 1, 214, NULL, 0, 0, 'Active'),
(39, 'PRD-0039', NULL, 'Calpol 6+ Syp 90ml', NULL, NULL, 5, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 101.48, 101.48, 96.41, 96.41, 5.00, 5.00, 20, 1, 31, NULL, 0, 0, 'Active'),
(40, 'PRD-0040', NULL, 'Polyfax Skin Oint 20g', NULL, NULL, 7, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 190.03, 190.03, 180.53, 180.53, 5.00, 5.00, 20, 1, 218, NULL, 0, 0, 'Active'),
(41, 'PRD-0041', NULL, 'Augmentin DS 312.5/5ml Syp', NULL, NULL, 5, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 531.57, 531.57, 504.99, 504.99, 5.00, 5.00, 20, 1, 70, NULL, 0, 0, 'Active'),
(42, 'PRD-0042', NULL, 'Augmentin  156.25/5ml Syp', NULL, NULL, 5, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 322.69, 322.69, 306.56, 306.56, 5.00, 5.00, 20, 1, 74, NULL, 0, 0, 'Active'),
(43, 'PRD-0043', NULL, 'Zyrtec Syp 60ml', NULL, NULL, 5, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 134.89, 134.89, 124.10, 124.10, 8.00, 8.00, 20, 1, 117, NULL, 0, 0, 'Active'),
(44, 'PRD-0044', NULL, 'Sunny D Inj', NULL, NULL, 4, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 208.25, 208.25, 156.19, 156.19, 25.00, 25.00, 20, 1, 90, NULL, 0, 0, 'Active'),
(45, 'PRD-0045', NULL, 'Entox P Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 442.00, 442.00, 433.16, 433.16, 2.00, 2.00, 20, 1, 40, NULL, 0, 0, 'Active'),
(46, 'PRD-0046', NULL, 'Arinac Fort Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 1275.00, 1275.00, 1236.75, 1236.75, 3.00, 3.00, 20, 1, 69, NULL, 0, 0, 'Active'),
(47, 'PRD-0047', NULL, 'Xonica 8mg Tab 10s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 187.00, 187.00, 177.65, 177.65, 5.00, 5.00, 20, 1, 36, NULL, 0, 0, 'Active'),
(48, 'PRD-0048', NULL, 'Flagyl 400mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 809.00, 809.00, 809.00, 809.00, 0.00, 0.00, 20, 1, 90, NULL, 0, 0, 'Active'),
(49, 'PRD-0049', NULL, 'Combivair 400mg Cap', NULL, NULL, 2, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 619.82, 619.82, 607.42, 607.42, 2.00, 2.00, 20, 1, 14, NULL, 0, 0, 'Active'),
(50, 'PRD-0050', NULL, 'Craflim Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 301.58, 301.58, 295.55, 295.55, 2.00, 2.00, 20, 1, 49, NULL, 0, 0, 'Active'),
(51, 'PRD-0051', NULL, 'Levopraid 25mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 456.93, 456.93, 447.79, 447.79, 2.00, 2.00, 20, 1, 26, NULL, 0, 0, 'Active'),
(52, 'PRD-0052', NULL, 'Levopraid  50mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 790.66, 790.66, 751.13, 751.13, 5.00, 5.00, 20, 1, 14, NULL, 0, 0, 'Active'),
(53, 'PRD-0053', NULL, 'Rovista 20mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 1713.60, 1713.60, 1696.46, 1696.46, 1.00, 1.00, 20, 1, 14, NULL, 0, 0, 'Active'),
(55, 'PRD-0055', NULL, 'Cellgee Tab 30s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 850.00, 850.00, 807.50, 807.50, 5.00, 5.00, 20, 1, 0, NULL, 0, 0, 'Active'),
(57, 'PRD-0056', NULL, 'Methix Tab 20s', NULL, NULL, NULL, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 1088.00, 1088.00, 1033.60, 1033.60, 5.00, 5.00, 20, 1, 6, NULL, 0, 0, 'Active'),
(58, 'PRD-0057', NULL, 'Intig D Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 446.25, 446.25, 441.79, 441.79, 1.00, 1.00, 20, 1, 28, NULL, 0, 0, 'Active'),
(59, 'PRD-0058', NULL, 'Regro 5% Spray', NULL, NULL, 6, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 1025.41, 1025.41, 922.87, 922.87, 10.00, 10.00, 20, 0, 20, NULL, 0, 0, 'Active'),
(60, 'PRD-0060', NULL, 'Fusiderm Cream', NULL, NULL, 1, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 324.50, 324.50, 292.05, 292.05, 10.00, 10.00, 20, 0, 28, NULL, 0, 0, 'Active'),
(61, 'PRD-0061', NULL, 'Fusiderm H Cream', NULL, NULL, 1, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 355.30, 355.30, 319.77, 319.77, 10.00, 10.00, 20, 0, 26, NULL, 0, 0, 'Active'),
(62, 'PRD-0062', NULL, 'Fixitil 100/5ml Syp', NULL, NULL, 5, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 233.75, 233.75, 222.06, 222.06, 5.00, 5.00, 20, 0, 30, NULL, 0, 0, 'Active'),
(63, 'PRD-0063', NULL, 'Ossobon D Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 425.00, 425.00, 412.25, 412.25, 3.00, 3.00, 20, 0, 22, NULL, 0, 0, 'Active'),
(64, 'PRD-0064', NULL, 'Tenormin 25mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 201.02, 201.02, 194.99, 194.99, 3.00, 3.00, 20, 0, 99, NULL, 0, 0, 'Active'),
(65, 'PRD-0065', NULL, 'Tenormin 50mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 354.02, 354.02, 343.40, 343.40, 3.00, 3.00, 20, 0, 93, NULL, 0, 0, 'Active'),
(66, 'PRD-0066', NULL, 'Tenormin 100mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 540.40, 540.40, 524.19, 524.19, 3.00, 3.00, 20, 0, 98, NULL, 0, 0, 'Active'),
(67, 'PRD-0067', NULL, 'Zestril 5mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 579.70, 579.70, 562.31, 562.31, 3.00, 3.00, 20, 0, 98, NULL, 0, 0, 'Active'),
(68, 'PRD-0068', NULL, 'Zestril 10mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 1113.50, 1113.50, 1080.10, 1080.10, 3.00, 3.00, 20, 0, 100, NULL, 0, 0, 'Active'),
(69, 'PRD-0069', NULL, 'Klaricid XL Tab 5s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 420.81, 420.81, 412.39, 412.39, 2.00, 2.00, 20, 0, 26, NULL, 0, 0, 'Active'),
(70, 'PRD-0070', NULL, 'Klaricid Syp 30ml', NULL, NULL, 5, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 246.37, 246.37, 241.44, 241.44, 2.00, 2.00, 20, 0, 70, NULL, 0, 0, 'Active'),
(71, 'PRD-0071', NULL, 'Klaricid Syp 60ml', NULL, NULL, 5, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 491.77, 491.77, 481.93, 481.93, 2.00, 2.00, 20, 0, 70, NULL, 0, 0, 'Active'),
(72, 'PRD-0072', NULL, 'Cardura 2mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 676.86, 676.86, 670.09, 670.09, 1.00, 1.00, 20, 0, 34, NULL, 0, 0, 'Active'),
(73, 'PRD-0073', NULL, 'Beceptor 10mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 191.25, 191.25, 181.69, 181.69, 5.00, 5.00, 20, 0, 5, NULL, 0, 0, 'Active'),
(74, 'PRD-0074', NULL, 'Atenolol Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 148.75, 148.75, 147.26, 147.26, 1.00, 1.00, 20, 0, 31, NULL, 0, 0, 'Active'),
(75, 'PRD-0075', NULL, 'ECP Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 24.94, 24.94, 24.69, 24.69, 1.00, 1.00, 20, 0, 0, NULL, 0, 0, 'Active'),
(76, 'PRD-0076', NULL, 'Simvazaf 10mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 119.67, 119.67, 113.69, 113.69, 5.00, 5.00, 20, 0, 20, NULL, 0, 0, 'Active'),
(77, 'PRD-0077', NULL, 'Simvazaf 20mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 212.75, 212.75, 202.11, 202.11, 5.00, 5.00, 20, 0, 25, NULL, 0, 0, 'Active'),
(78, 'PRD-0078', NULL, 'Zafnol Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 97.75, 106.25, 105.19, 105.19, 1.00, 1.00, 20, 0, 40, NULL, 0, 0, 'Active'),
(79, 'PRD-0079', NULL, 'Zodip Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 74.86, 74.86, 74.11, 74.11, 1.00, 1.00, 20, 0, 37, NULL, 0, 0, 'Active'),
(80, 'PRD-0080', NULL, 'Adenuric 40mg Tab 20s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 476.00, 476.00, 466.48, 466.48, 2.00, 2.00, 20, 0, 20, NULL, 0, 0, 'Active'),
(81, 'PRD-0081', NULL, 'Magnett 400mg Cap', NULL, NULL, 2, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 405.45, 450.50, 441.49, 441.49, 2.00, 2.00, 20, 0, 7, NULL, 0, 0, 'Active'),
(82, 'PRD-0082', NULL, 'Nocid 20mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 312.37, 312.37, 306.12, 306.12, 2.00, 2.00, 20, 0, 15, NULL, 0, 0, 'Active'),
(83, 'PRD-0083', NULL, 'Polybion Z Cap', NULL, NULL, 2, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 400.18, 400.18, 392.18, 392.18, 2.00, 2.00, 20, 0, 10, NULL, 0, 0, 'Active'),
(84, 'PRD-0084', NULL, 'Cefiget 100/5ml Syp 30ml', NULL, NULL, 5, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 267.60, 267.60, 264.92, 264.92, 1.00, 1.00, 20, 0, 18, NULL, 0, 0, 'Active'),
(85, 'PRD-0085', NULL, 'Diagesic P Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 1955.00, 1955.00, 1857.25, 1857.25, 5.00, 5.00, 20, 0, 10, NULL, 0, 0, 'Active'),
(86, 'PRD-0086', NULL, 'Lice -O-Nil Cream', NULL, NULL, 1, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 212.50, 212.50, 206.13, 206.13, 3.00, 3.00, 20, 0, 6, NULL, 0, 0, 'Active'),
(87, 'PRD-0087', NULL, 'Venticort 400mg Cap', NULL, NULL, 2, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 484.50, 484.50, 469.97, 469.97, 3.00, 3.00, 20, 0, 17, NULL, 0, 0, 'Active'),
(88, 'PRD-0088', NULL, 'Nirvanol 5mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 148.75, 148.75, 133.88, 133.88, 10.00, 10.00, 20, 0, 18, NULL, 0, 0, 'Active'),
(89, 'PRD-0089', NULL, 'Nirvanol 10mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 233.75, 233.75, 210.38, 210.38, 10.00, 10.00, 20, 0, 8, NULL, 0, 0, 'Active'),
(90, 'PRD-0090', NULL, 'Elexine 15mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 510.00, 510.00, 433.50, 433.50, 15.00, 15.00, 20, 0, 9, NULL, 0, 0, 'Active'),
(91, 'PRD-0091', NULL, 'Sea Cal Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 1197.65, 1197.65, 1137.77, 1137.77, 5.00, 5.00, 20, 0, 20, NULL, 0, 0, 'Active'),
(92, 'PRD-0092', NULL, 'Sea Cal Sachets', NULL, NULL, 8, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 439.12, 439.12, 417.16, 417.16, 5.00, 5.00, 20, 0, 19, NULL, 0, 0, 'Active'),
(93, 'PRD-0093', NULL, 'Amodip V 5/80 Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 361.25, 361.25, 354.03, 354.03, 2.00, 2.00, 20, 0, 22, NULL, 0, 0, 'Active'),
(94, 'PRD-0094', NULL, 'Sita Met 50/500mg Tab 14s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 599.25, 599.25, 587.27, 587.27, 2.00, 2.00, 20, 0, 35, NULL, 0, 0, 'Active'),
(95, 'PRD-0095', NULL, 'Sita Met 50/1000mg Tab 14s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 617.10, 617.10, 604.76, 604.76, 2.00, 2.00, 20, 0, 27, NULL, 0, 0, 'Active'),
(96, 'PRD-0096', NULL, 'Nuberol Tab 100s', NULL, NULL, NULL, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 722.33, 722.33, 715.11, 715.11, 1.00, 1.00, 20, 0, 8, NULL, 0, 0, 'Active'),
(97, 'PRD-0097', NULL, 'Magnett 100/5ml Syp', NULL, NULL, 5, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 218.03, 242.25, 237.41, 237.41, 2.00, 2.00, 20, 0, 3, NULL, 0, 0, 'Active'),
(98, 'PRD-0098', NULL, 'Amodip 5/160mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 464.10, 464.10, 454.82, 454.82, 2.00, 2.00, 20, 0, 13, NULL, 0, 0, 'Active'),
(99, 'PRD-0099', NULL, 'Amodip 10mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 654.21, 654.21, 641.13, 641.13, 2.00, 2.00, 20, 0, 11, NULL, 0, 0, 'Active'),
(100, 'PRD-0100', NULL, 'Rovista 5mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 697.00, 697.00, 697.00, 697.00, 0.00, 0.00, 20, 0, 9, NULL, 0, 0, 'Active'),
(101, 'PRD-0101', NULL, 'HCQ 200Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 472.86, 472.86, 472.86, 472.86, 0.00, 0.00, 20, 0, 5, NULL, 0, 0, 'Active'),
(102, 'PRD-0102', NULL, 'Lophos Tab 100s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 654.34, 654.34, 654.34, 654.34, 0.00, 0.00, 20, 0, 4, NULL, 0, 0, 'Active'),
(103, 'PRD-0103', NULL, 'Gablin 75mg Cap', NULL, NULL, 2, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 567.80, 567.80, 528.05, 528.05, 7.00, 7.00, 20, 0, 29, NULL, 0, 0, 'Active'),
(104, 'PRD-0104', NULL, 'Gablin 100mg Cap', NULL, NULL, 2, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 571.31, 571.31, 531.32, 531.32, 7.00, 7.00, 20, 0, 20, NULL, 0, 0, 'Active'),
(105, 'PRD-0105', NULL, 'Sita 50mg Tab 14s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 512.47, 512.47, 502.22, 502.22, 2.00, 2.00, 20, 0, 20, NULL, 0, 0, 'Active'),
(106, 'PRD-0106', NULL, 'Sita 100mg Tab 14s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 875.50, 875.50, 857.99, 857.99, 2.00, 2.00, 20, 0, 18, NULL, 0, 0, 'Active'),
(107, 'PRD-0107', NULL, 'Xiga 10mg Tab 14s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 602.65, 602.65, 590.60, 590.60, 2.00, 2.00, 20, 0, 25, NULL, 0, 0, 'Active'),
(108, 'PRD-0108', NULL, 'Hilin 100mg Cap', NULL, NULL, 2, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 392.70, 392.70, 384.85, 384.85, 2.00, 2.00, 20, 0, 20, NULL, 0, 0, 'Active'),
(109, 'PRD-0109', NULL, 'Lipirex 20mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 816.00, 816.00, 734.40, 734.40, 10.00, 10.00, 20, 0, 14, NULL, 0, 0, 'Active'),
(110, 'PRD-0110', NULL, 'Minoxin Plus 5% Sol 60ml', NULL, NULL, 6, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 930.75, 930.75, 884.21, 884.21, 5.00, 5.00, 20, 0, 39, NULL, 0, 0, 'Active'),
(111, 'PRD-0111', NULL, 'Tobra E/D', NULL, NULL, 9, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 191.25, 191.25, 181.69, 181.69, 5.00, 5.00, 20, 0, 9, NULL, 0, 0, 'Active'),
(112, 'PRD-0112', NULL, 'Tobra D E/D', NULL, NULL, 9, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 242.25, 242.25, 230.14, 230.14, 5.00, 5.00, 20, 0, 13, NULL, 0, 0, 'Active'),
(113, 'PRD-0113', NULL, 'Jentin Met 50/500mg Tab 14s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 522.75, 522.75, 517.52, 517.52, 1.00, 1.00, 20, 0, 16, NULL, 0, 0, 'Active'),
(114, 'PRD-0114', NULL, 'Jentin Met 50/1000mg Tab 14s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 535.50, 535.50, 530.15, 530.15, 1.00, 1.00, 20, 0, 5, NULL, 0, 0, 'Active'),
(115, 'PRD-0115', NULL, 'Ezium 20mg Cap 14s', NULL, NULL, 2, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 331.50, 331.50, 328.19, 328.19, 1.00, 1.00, 20, 0, 19, NULL, 0, 0, 'Active'),
(116, 'PRD-0116', NULL, 'Q Bal Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 935.00, 935.00, 841.50, 841.50, 10.00, 10.00, 20, 0, 10, NULL, 0, 0, 'Active'),
(117, 'PRD-0117', NULL, 'Diabryl 4mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 364.65, 364.65, 346.42, 346.42, 5.00, 5.00, 20, 0, 20, NULL, 0, 0, 'Active'),
(118, 'PRD-0118', NULL, 'Laprazol 30mg Cap 14s', NULL, NULL, 2, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 364.65, 364.65, 346.42, 346.42, 5.00, 5.00, 20, 0, 29, NULL, 0, 0, 'Active'),
(119, 'PRD-0119', NULL, 'Spasfon Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 1489.20, 1489.20, 1444.52, 1444.52, 3.00, 3.00, 20, 0, 18, NULL, 0, 0, 'Active'),
(120, 'PRD-0120', NULL, 'Myolax 4mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 752.25, 752.25, 722.16, 722.16, 4.00, 4.00, 20, 0, 17, NULL, 0, 0, 'Active'),
(121, 'PRD-0121', NULL, 'Anifed Retard Tab 50s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 317.90, 317.90, 311.54, 311.54, 2.00, 2.00, 20, 0, 25, NULL, 0, 0, 'Active'),
(122, 'PRD-0122', NULL, 'Xavor 50mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 262.35, 262.35, 257.10, 257.10, 2.00, 2.00, 20, 0, 25, NULL, 0, 0, 'Active'),
(123, 'PRD-0123', NULL, 'Xavor DIU 50mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 233.43, 233.43, 228.76, 228.76, 2.00, 2.00, 20, 0, 23, NULL, 0, 0, 'Active'),
(124, 'PRD-0124', NULL, 'DV Losartan 50mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 282.74, 392.70, 353.43, 353.43, 10.00, 10.00, 20, 0, 10, NULL, 0, 0, 'Active'),
(125, 'PRD-0125', NULL, 'Deximox E/D', NULL, NULL, 9, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 356.15, 356.15, 345.47, 345.47, 3.00, 3.00, 20, 0, 17, NULL, 0, 0, 'Active'),
(126, 'PRD-0126', NULL, 'Eyebradex E/D', NULL, NULL, 9, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 255.00, 255.00, 247.35, 247.35, 3.00, 3.00, 20, 0, 19, NULL, 0, 0, 'Active'),
(127, 'PRD-0127', NULL, 'Aerokast 10mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 408.00, 408.00, 387.60, 387.60, 5.00, 5.00, 20, 0, 22, NULL, 0, 0, 'Active'),
(128, 'PRD-0128', NULL, 'Cebosh 100/5ml Syp', NULL, NULL, 5, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 267.62, 267.62, 240.86, 240.86, 10.00, 10.00, 20, 0, 19, NULL, 0, 0, 'Active'),
(129, 'PRD-0129', NULL, 'Musidin 2mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 216.99, 216.99, 206.14, 206.14, 5.00, 5.00, 20, 0, 16, NULL, 0, 0, 'Active'),
(130, 'PRD-0130', NULL, 'Apranax 550mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 296.29, 448.93, 426.48, 426.48, 5.00, 5.00, 20, 0, 14, NULL, 0, 0, 'Active'),
(131, 'PRD-0131', NULL, 'Atorva 10mg Tab 20s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 636.65, 636.65, 572.99, 572.99, 10.00, 10.00, 20, 0, 16, NULL, 0, 0, 'Active'),
(132, 'PRD-0132', NULL, 'Atorva 20mg Tab 20s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 1124.55, 1124.55, 1012.10, 1012.10, 10.00, 10.00, 20, 0, 11, NULL, 0, 0, 'Active'),
(133, 'PRD-0133', NULL, 'Atorva 40mg Tab 20s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 1295.91, 1295.91, 1166.32, 1166.32, 10.00, 10.00, 20, 0, 18, NULL, 0, 0, 'Active'),
(134, 'PRD-0134', NULL, 'Lanzol 30mg Cap', NULL, NULL, 2, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 480.34, 480.34, 456.32, 456.32, 5.00, 5.00, 20, 0, 12, NULL, 0, 0, 'Active'),
(135, 'PRD-0135', NULL, 'Azotek 250mg Tab 12s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 208.25, 208.25, 197.84, 197.84, 5.00, 5.00, 20, 0, 20, NULL, 0, 0, 'Active'),
(136, 'PRD-0136', NULL, 'Derma Smooth Lotion 120ml', NULL, NULL, 10, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 403.75, 403.75, 391.64, 391.64, 3.00, 3.00, 20, 0, 9, NULL, 0, 0, 'Active'),
(137, 'PRD-0137', NULL, 'Sofvasc V 5/80mg Tab  14s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 380.55, 380.55, 369.13, 369.13, 3.00, 3.00, 20, 0, 19, NULL, 0, 0, 'Active'),
(138, 'PRD-0138', NULL, 'Sofvasc V 10/160mg Tab  14s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 638.44, 638.44, 619.29, 619.29, 3.00, 3.00, 20, 0, 20, NULL, 0, 0, 'Active'),
(139, 'PRD-0139', NULL, 'Diampa LXR 10/5/1000 mg Tab 14s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 310.25, 310.25, 307.15, 307.15, 1.00, 1.00, 20, 0, 7, NULL, 0, 0, 'Active'),
(140, 'PRD-0140', NULL, 'Diampa LXR 25/5/1000 mg Tab 14s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 352.75, 352.75, 349.22, 349.22, 1.00, 1.00, 20, 0, 4, NULL, 0, 0, 'Active'),
(141, 'PRD-0141', NULL, 'leflox 250mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 578.00, 578.00, 572.22, 572.22, 1.00, 1.00, 20, 0, 18, NULL, 0, 0, 'Active'),
(142, 'PRD-0142', NULL, 'Montiget 10mg Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 471.75, 471.75, 467.03, 467.03, 1.00, 1.00, 20, 0, 19, NULL, 0, 0, 'Active'),
(143, 'PRD-0143', NULL, 'Treviamet 50/500 tab 14s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 963.90, 963.90, 954.26, 954.26, 1.00, 1.00, 20, 0, 15, NULL, 0, 0, 'Active'),
(144, 'PRD-0144', NULL, 'Treviamet 50/1000 tab 14s', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 999.60, 999.60, 989.60, 989.60, 1.00, 1.00, 20, 0, 19, NULL, 0, 0, 'Active'),
(145, 'PRD-0145', NULL, 'Osteocare D3 Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 806.65, 806.65, 725.99, 725.99, 10.00, 10.00, 20, 0, 29, NULL, 0, 0, 'Active'),
(146, 'PRD-0146', NULL, 'PAT 4 Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 2163.00, 2163.00, 2119.74, 2119.74, 2.00, 2.00, 10, 0, 10, NULL, 0, 0, 'Active'),
(147, 'PRD-0147', NULL, 'Surbex Z Tab', NULL, NULL, 3, NULL, '1 Pcs', 1, 1, 1, 'Pcs', 433.50, 433.50, 429.17, 429.17, 1.00, 1.00, 20, 0, 77, NULL, 0, 0, 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `product_batches`
--

CREATE TABLE `product_batches` (
  `id` int NOT NULL,
  `product_id` int NOT NULL,
  `batch_no` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `manufacturing_date` date DEFAULT NULL,
  `expiry_date` date NOT NULL,
  `purchase_price` decimal(12,2) DEFAULT '0.00',
  `trade_price` decimal(12,2) DEFAULT '0.00',
  `retail_price` decimal(12,2) DEFAULT '0.00',
  `initial_quantity` int DEFAULT '0',
  `current_stock` int DEFAULT '0',
  `status` enum('Active','Expired','Claimed','Exhausted') COLLATE utf8mb4_general_ci DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_batches`
--

INSERT INTO `product_batches` (`id`, `product_id`, `batch_no`, `manufacturing_date`, `expiry_date`, `purchase_price`, `trade_price`, `retail_price`, `initial_quantity`, `current_stock`, `status`) VALUES
(1, 1, 'BAT-260929', '2026-09-28', '2028-09-29', 185.05, 185.05, 175.80, 1, 0, 'Active'),
(2, 2, 'BAT-260929', '2026-09-28', '2028-09-29', 603.17, 603.17, 554.92, 1, 0, 'Active'),
(3, 3, 'BAT-260929', '2026-09-28', '2028-09-29', 1219.75, 1219.75, 1195.36, 1, 1, 'Active'),
(4, 4, 'BAT-260929', '2026-09-28', '2028-09-29', 428.66, 428.66, 420.09, 1, 0, 'Active'),
(5, 5, 'BAT-260929', '2026-09-28', '2028-09-29', 573.75, 573.75, 562.28, 1, 0, 'Active'),
(6, 6, 'BAT-260929', '2026-09-28', '2028-09-29', 340.00, 340.00, 333.20, 1, 1, 'Active'),
(7, 7, 'BAT-260929', '2026-09-28', '2028-09-29', 233.75, 233.75, 231.41, 1, 0, 'Active'),
(8, 8, 'BAT-260929', '2026-09-28', '2028-09-29', 433.50, 433.50, 429.17, 1, 0, 'Active'),
(9, 9, 'BAT-260929', '2026-09-28', '2028-09-29', 252.71, 252.71, 247.66, 1, 0, 'Active'),
(10, 10, 'BAT-260929', '2026-09-28', '2028-09-29', 351.48, 351.48, 344.45, 1, 0, 'Active'),
(11, 11, 'BAT-260929', '2026-09-28', '2028-09-29', 833.00, 833.00, 816.34, 1, 0, 'Active'),
(12, 12, 'BAT-260929', '2026-09-28', '2028-09-29', 904.40, 904.40, 886.31, 1, 0, 'Active'),
(13, 13, 'BAT-260929', '2026-09-28', '2028-09-29', 199.75, 199.75, 195.76, 1, 0, 'Active'),
(14, 14, 'BAT-260929', '2026-09-28', '2028-09-29', 675.19, 675.19, 661.69, 1, 0, 'Active'),
(15, 15, 'BAT-260929', '2026-09-28', '2028-09-29', 466.40, 466.40, 457.07, 1, 0, 'Active'),
(16, 16, 'BAT-260929', '2026-09-28', '2028-09-29', 696.45, 696.45, 682.52, 1, 0, 'Active'),
(17, 17, 'BAT-260929', '2026-09-28', '2028-09-29', 169.60, 169.60, 161.12, 1, 0, 'Active'),
(18, 18, 'BAT-260929', '2026-09-28', '2028-09-29', 127.39, 127.39, 121.02, 1, 0, 'Active'),
(19, 19, 'BAT-260929', '2026-09-28', '2028-09-29', 782.00, 782.00, 719.44, 1, 0, 'Active'),
(22, 22, 'BAT-260929', '2026-09-28', '2028-09-29', 580.89, 580.89, 534.42, 1, 0, 'Active'),
(23, 23, 'BAT-260929', '2026-09-28', '2028-09-29', 350.20, 350.20, 332.69, 1, 0, 'Active'),
(24, 24, 'BAT-260929', '2026-09-28', '2028-09-29', 327.25, 327.25, 310.89, 1, 1, 'Active'),
(25, 25, 'BAT-260929', '2026-09-28', '2028-09-29', 464.64, 464.64, 455.35, 1, 0, 'Active'),
(26, 26, 'BAT-260929', '2026-09-28', '2028-09-29', 841.62, 841.62, 824.79, 1, 0, 'Active'),
(27, 27, 'BAT-260929', '2026-09-28', '2028-09-29', 165.75, 165.75, 160.78, 1, 0, 'Active'),
(28, 28, 'BAT-260929', '2026-09-28', '2028-09-29', 61.20, 61.20, 59.36, 1, 0, 'Active'),
(29, 29, 'BAT-260929', '2026-09-28', '2028-09-29', 102.00, 102.00, 96.90, 1, 0, 'Active'),
(30, 30, 'BAT-260929', '2026-09-28', '2028-09-29', 102.00, 102.00, 96.90, 1, 0, 'Active'),
(31, 31, 'BAT-260929', '2026-09-28', '2028-09-29', 61.20, 61.20, 58.14, 1, 1, 'Active'),
(32, 32, 'BAT-260929', '2026-09-28', '2028-09-29', 127.50, 127.50, 121.13, 1, 0, 'Active'),
(33, 33, 'BAT-260929', '2026-09-28', '2028-09-29', 382.50, 382.50, 344.25, 1, 0, 'Active'),
(34, 34, 'BAT-260929', '2026-09-28', '2028-09-29', 242.53, 242.53, 218.28, 1, 0, 'Active'),
(35, 35, 'BAT-260929', '2026-09-28', '2028-09-29', 236.02, 236.02, 236.02, 1, 0, 'Active'),
(36, 36, 'BAT-260929', '2026-09-28', '2028-09-29', 431.60, 431.60, 431.60, 1, 1, 'Active'),
(37, 38, 'BAT-260929', '2026-09-28', '2028-09-29', 153.53, 153.53, 145.85, 1, 0, 'Active'),
(38, 39, 'BAT-260929', '2026-09-28', '2028-09-29', 101.48, 101.48, 96.41, 1, 0, 'Active'),
(39, 40, 'BAT-260929', '2026-09-28', '2028-09-29', 190.03, 190.03, 180.53, 1, 0, 'Active'),
(40, 41, 'BAT-260929', '2026-09-28', '2028-09-29', 531.57, 531.57, 504.99, 1, 0, 'Active'),
(41, 42, 'BAT-260929', '2026-09-28', '2028-09-29', 322.69, 322.69, 306.56, 1, 0, 'Active'),
(42, 43, 'BAT-260929', '2026-09-28', '2028-09-29', 134.89, 134.89, 124.10, 1, 0, 'Active'),
(43, 44, 'BAT-260929', '2026-09-28', '2028-09-29', 208.25, 208.25, 156.19, 1, 0, 'Active'),
(44, 45, 'BAT-260929', '2026-09-28', '2028-09-29', 442.00, 442.00, 433.16, 1, 0, 'Active'),
(45, 46, 'BAT-260929', '2026-09-28', '2028-09-29', 1275.00, 1275.00, 1236.75, 1, 0, 'Active'),
(46, 47, 'BAT-260929', '2026-09-28', '2028-09-29', 187.00, 187.00, 177.65, 1, 1, 'Active'),
(47, 48, 'BAT-260929', '2026-09-28', '2028-09-29', 809.00, 809.00, 809.00, 1, 0, 'Active'),
(48, 49, 'BAT-260929', '2026-09-28', '2028-09-29', 619.82, 619.82, 607.42, 1, 0, 'Active'),
(49, 50, 'BAT-260929', '2026-09-28', '2028-09-29', 301.58, 301.58, 295.55, 1, 0, 'Active'),
(50, 51, 'BAT-260929', '2026-09-28', '2028-09-29', 456.93, 456.93, 447.79, 1, 0, 'Active'),
(51, 52, 'BAT-260929', '2026-09-28', '2028-09-29', 790.66, 790.66, 751.13, 1, 0, 'Active'),
(52, 53, 'BAT-260929', '2026-09-28', '2028-09-29', 1713.60, 1713.60, 1696.46, 1, 0, 'Active'),
(53, 2, '389V', NULL, '2028-09-29', 361.90, 554.92, 554.92, 250, 240, 'Active'),
(54, 53, 'F07059', NULL, '2028-10-01', 1559.38, 1696.46, 1696.46, 10, 12, 'Active'),
(55, 1, '679D', NULL, '2028-09-29', 148.04, 175.80, 175.80, 400, 369, 'Active'),
(56, 3, '176FB2', NULL, '2028-10-01', 1109.97, 1195.36, 1195.36, 3, 11, 'Active'),
(57, 4, '025P023', NULL, '2028-09-29', 385.79, 420.09, 420.09, 40, 41, 'Active'),
(58, 9, '261322', NULL, '2028-09-29', 222.38, 247.66, 247.66, 60, 26, 'Active'),
(59, 10, '261089', NULL, '2028-09-29', 316.33, 344.45, 344.45, 50, 33, 'Active'),
(60, 13, 'RPD476', NULL, '2028-09-30', 182.77, 195.75, 195.75, 50, 30, 'Active'),
(61, 14, 'N7522', NULL, '2028-09-30', 624.28, 661.69, 661.69, 100, 94, 'Active'),
(62, 15, 'N6970', NULL, '2028-09-30', 431.23, 457.07, 457.07, 100, 93, 'Active'),
(63, 16, 'VT2B', NULL, '2028-09-30', 633.14, 682.52, 682.52, 55, 46, 'Active'),
(64, 17, '814', NULL, '2028-09-30', 154.18, 161.12, 161.12, 120, 202, 'Active'),
(65, 18, 'K325', NULL, '2028-09-30', 101.91, 121.02, 121.02, 40, 198, 'Active'),
(66, 19, '5838', NULL, '2028-09-30', 641.24, 719.44, 719.44, 50, 48, 'Active'),
(69, 22, '261D006', NULL, '2028-09-30', 464.71, 534.42, 534.42, 30, 26, 'Active'),
(70, 23, 'DEFAULT', NULL, '2028-09-30', 262.65, 332.69, 332.69, 12, 12, 'Active'),
(71, 24, '353', NULL, '2028-09-30', 245.44, 310.89, 310.89, 12, 12, 'Active'),
(72, 25, '862203XV', NULL, '2028-09-30', 413.95, 455.35, 455.35, 55, 55, 'Active'),
(73, 26, '852971XV', NULL, '2028-10-01', 721.39, 824.79, 824.79, 55, 63, 'Active'),
(74, 27, '(10) 902623XV', NULL, '2028-09-30', 142.07, 160.78, 160.78, 140, 110, 'Active'),
(75, 28, 'JU014M', NULL, '2028-09-30', 54.91, 59.36, 59.36, 144, 129, 'Active'),
(76, 29, 'JW017M', NULL, '2028-09-30', 84.11, 96.90, 96.90, 288, 280, 'Active'),
(77, 30, 'JZ007M', NULL, '2028-09-30', 84.11, 96.90, 96.90, 288, 286, 'Active'),
(78, 31, 'JY007M', NULL, '2028-09-30', 60.69, 58.14, 58.14, 144, 144, 'Active'),
(79, 32, 'KV024M', NULL, '2028-09-30', 117.43, 121.13, 121.13, 288, 277, 'Active'),
(80, 33, 'GS007L', NULL, '2028-09-30', 173.86, 344.25, 344.25, 22, 9, 'Active'),
(81, 34, 'PN001M', NULL, '2028-09-30', 161.69, 218.28, 218.28, 45, 40, 'Active'),
(82, 38, '5M7E', NULL, '2028-09-30', 133.50, 145.85, 145.85, 230, 208, 'Active'),
(83, 39, '588C', NULL, '2028-09-30', 85.24, 96.41, 96.41, 50, 21, 'Active'),
(84, 40, '9C3A', NULL, '2028-09-30', 165.24, 180.53, 180.53, 230, 218, 'Active'),
(85, 41, 'AH4S', NULL, '2028-09-30', 451.83, 504.99, 504.99, 70, 66, 'Active'),
(86, 42, '8N4X', NULL, '2028-09-30', 274.29, 306.56, 306.56, 70, 64, 'Active'),
(87, 43, '655E', NULL, '2028-09-30', 107.91, 124.10, 124.10, 125, 111, 'Active'),
(88, 44, 'FY26022', NULL, '2028-09-30', 104.13, 156.19, 156.19, 100, 88, 'Active'),
(89, 45, '265B078', NULL, '2028-09-30', 401.82, 433.16, 433.16, 44, 36, 'Active'),
(90, 46, '(10)862118XV', NULL, '2028-09-30', 1092.86, 1236.75, 1236.75, 70, 69, 'Active'),
(91, 48, 'AN678', NULL, '2028-09-30', 752.37, 809.00, 809.00, 90, 89, 'Active'),
(92, 49, '260325', NULL, '2028-09-30', 545.44, 607.42, 607.42, 20, 0, 'Active'),
(93, 50, 'AF3007V', NULL, '2028-09-30', 271.42, 295.55, 295.55, 50, 49, 'Active'),
(94, 51, 'LN2509U', NULL, '2028-09-30', 411.24, 447.79, 447.79, 30, 24, 'Active'),
(95, 52, 'APO606', NULL, '2028-09-30', 672.06, 751.13, 751.13, 15, 12, 'Active'),
(96, 11, '997', NULL, '2028-09-29', 749.70, 816.34, 816.34, 10, 1, 'Active'),
(97, 12, '009', NULL, '2028-09-30', 813.96, 886.31, 886.31, 10, 0, 'Active'),
(98, 47, 'T27025', NULL, '2028-09-29', 159.32, 177.65, 177.65, 35, 35, 'Active'),
(99, 35, 'ACH047', NULL, '2028-09-29', 225.16, 236.02, 236.02, 50, 36, 'Active'),
(100, 36, 'DCH060', NULL, '2028-09-29', 414.62, 431.60, 431.60, 60, 60, 'Active'),
(102, 55, 'BAT-260929', '2026-09-29', '2028-09-29', 922.03, 922.03, 875.93, 1, 0, 'Active'),
(103, 55, 'ECLT-169', NULL, '2028-09-29', 598.41, 684.76, 684.76, 5, 0, 'Active'),
(107, 57, 'BAT-260929', '2026-09-29', '2028-09-29', 922.03, 922.03, 875.93, 1, 0, 'Active'),
(108, 57, 'PFT2016', NULL, '2028-10-07', 946.56, 1033.60, 1033.60, 10, 0, 'Active'),
(109, 5, '055O004', NULL, '2028-09-29', 516.38, 562.27, 562.27, 10, 7, 'Active'),
(110, 6, '074P006', NULL, '2028-10-02', 314.36, 333.20, 333.20, 20, 30, 'Active'),
(111, 7, '138P020', NULL, '2028-09-29', 219.73, 231.41, 231.41, 30, 28, 'Active'),
(112, 8, '143P007', NULL, '2028-09-29', 403.15, 429.17, 429.17, 25, 22, 'Active'),
(113, 58, 'BAT-260929', '2026-09-29', '2028-09-29', 446.25, 446.25, 437.33, 1, 0, 'Active'),
(114, 58, '0839023', NULL, '2028-10-02', 406.09, 437.32, 437.32, 20, 22, 'Active'),
(115, 59, 'RG224', NULL, '2028-09-29', 741.99, 871.60, 871.60, 20, 20, 'Active'),
(116, 60, 'FS163', NULL, '2028-09-29', 226.49, 275.82, 275.82, 30, 28, 'Active'),
(117, 61, 'F107', NULL, '2028-09-29', 250.31, 302.00, 302.00, 30, 23, 'Active'),
(118, 62, '314A', NULL, '2028-09-29', 175.31, 222.06, 222.06, 30, 30, 'Active'),
(119, 63, 'FM710', NULL, '2028-09-29', 374.00, 408.00, 408.00, 30, 18, 'Active'),
(120, 64, '263D005', NULL, '2028-09-30', 170.87, 190.97, 190.97, 100, 98, 'Active'),
(121, 65, '264D019', NULL, '2028-09-30', 300.92, 336.32, 336.32, 100, 84, 'Active'),
(122, 66, '265D003', NULL, '2028-09-30', 459.34, 513.38, 513.38, 100, 95, 'Active'),
(123, 67, '261E008', NULL, '2028-09-30', 508.05, 567.82, 567.82, 100, 98, 'Active'),
(124, 68, '262E008', NULL, '2028-09-30', 946.48, 1057.83, 1057.83, 100, 100, 'Active'),
(125, 69, '852932XV', NULL, '2028-10-01', 360.69, 412.39, 412.39, 28, 26, 'Active'),
(126, 70, '(10)902501XV', NULL, '2028-10-01', 211.17, 241.44, 241.44, 70, 70, 'Active'),
(127, 71, '(10)872738XV', NULL, '2028-10-01', 421.52, 481.93, 481.93, 70, 70, 'Active'),
(128, 74, '410', NULL, '2028-10-07', 0.00, 147.26, 147.26, 30, 27, 'Active'),
(129, 75, '337', NULL, '2028-10-02', 23.19, 24.69, 24.69, 50, 0, 'Active'),
(130, 76, '092', NULL, '2028-10-02', 101.72, 113.69, 113.69, 20, 20, 'Active'),
(131, 77, '119', NULL, '2028-10-02', 180.84, 202.11, 202.11, 25, 25, 'Active'),
(132, 78, 'DEFAULT', NULL, '2028-10-02', 97.75, 105.19, 105.19, 50, 34, 'Active'),
(133, 79, '558', NULL, '2028-10-02', 69.62, 74.11, 74.11, 50, 24, 'Active'),
(134, 80, 'T26265', NULL, '2028-10-02', 428.40, 466.48, 466.48, 20, 14, 'Active'),
(135, 81, 'DEFAULT', NULL, '2028-10-02', 405.45, 441.49, 441.49, 10, 7, 'Active'),
(136, 82, 'N7235', NULL, '2028-10-02', 265.51, 306.12, 306.12, 15, 15, 'Active'),
(137, 83, '48326', NULL, '2028-10-02', 360.16, 392.18, 392.18, 12, 10, 'Active'),
(138, 84, '06D050', NULL, '2028-10-02', 246.19, 264.92, 264.92, 20, 18, 'Active'),
(139, 85, '5764', NULL, '2028-10-02', 1622.65, 1857.25, 1857.25, 10, 10, 'Active'),
(140, 86, '(10) 3918', NULL, '2028-10-02', 180.63, 206.13, 206.13, 20, 1, 'Active'),
(141, 87, '6GZ023', NULL, '2028-10-02', 469.97, 469.96, 469.96, 20, 11, 'Active'),
(142, 88, 'GTF381', NULL, '2028-10-02', 89.25, 133.88, 133.88, 20, 18, 'Active'),
(143, 89, 'GTG446', NULL, '2028-10-02', 140.25, 210.38, 210.38, 10, 8, 'Active'),
(144, 90, 'GTG419', NULL, '2028-10-02', 306.00, 433.50, 433.50, 10, 9, 'Active'),
(145, 91, '274', NULL, '2028-10-02', 958.12, 1137.77, 1137.77, 20, 20, 'Active'),
(146, 92, '255', NULL, '2028-10-02', 351.30, 417.16, 417.16, 20, 17, 'Active'),
(147, 93, 'ANO46', NULL, '2028-10-02', 325.12, 354.02, 354.02, 25, 20, 'Active'),
(148, 94, 'EV131', NULL, '2028-10-02', 557.30, 587.26, 587.26, 50, 30, 'Active'),
(149, 95, 'EX091', NULL, '2028-10-02', 573.90, 604.76, 604.76, 30, 27, 'Active'),
(150, 96, 'CEH097', NULL, '2028-10-02', 664.54, 715.11, 715.11, 10, 4, 'Active'),
(151, 97, 'DEFAULT', NULL, '2028-10-02', 218.03, 237.41, 237.41, 10, 1, 'Active'),
(152, 98, 'AKO019', NULL, '2028-10-02', 417.69, 454.82, 454.82, 10, 10, 'Active'),
(153, 99, 'AH060', NULL, '2028-10-02', 588.79, 641.13, 641.13, 10, 9, 'Active'),
(154, 100, 'F05049', NULL, '2028-10-02', 662.15, 697.00, 697.00, 10, 9, 'Active'),
(155, 101, 'F14110', NULL, '2028-10-02', 453.95, 472.86, 472.86, 10, 1, 'Active'),
(156, 102, '864RA', NULL, '2028-10-02', 628.17, 654.34, 654.34, 1, 3, 'Active'),
(157, 103, 'FJ005', NULL, '2028-10-02', 454.24, 528.05, 528.05, 30, 27, 'Active'),
(158, 104, 'FK003', NULL, '2028-10-02', 457.05, 531.32, 531.32, 20, 20, 'Active'),
(159, 105, 'FQ003', NULL, '2028-10-02', 461.22, 502.22, 502.22, 25, 20, 'Active'),
(160, 106, 'FR003', NULL, '2028-10-02', 787.95, 857.99, 857.99, 20, 6, 'Active'),
(161, 107, 'PS268', NULL, '2028-10-02', 542.38, 590.60, 590.60, 25, 25, 'Active'),
(162, 108, '260312', NULL, '2028-10-02', 361.28, 384.85, 384.85, 20, 20, 'Active'),
(163, 109, '262059', NULL, '2028-10-02', 505.92, 734.40, 734.40, 20, 4, 'Active'),
(164, 111, 'TBD396', NULL, '2028-10-02', 160.65, 181.69, 181.69, 20, 4, 'Active'),
(165, 112, '(10) TDD804', NULL, '2028-10-02', 230.14, 230.14, 230.14, 20, 8, 'Active'),
(166, 113, 'CGH006', NULL, '2028-10-02', 480.93, 517.52, 517.52, 17, 16, 'Active'),
(167, 114, 'CHH008', NULL, '2028-10-02', 492.66, 530.14, 530.14, 5, 5, 'Active'),
(168, 115, 'DVH013', NULL, '2028-10-07', 0.00, 328.19, 328.19, 20, 19, 'Active'),
(169, 117, '23375', NULL, '2028-10-02', 313.60, 346.42, 346.42, 20, 20, 'Active'),
(170, 118, '0054', NULL, '2028-10-02', 302.66, 346.42, 346.42, 30, 29, 'Active'),
(171, 119, 'H14637', NULL, '2028-10-02', 1340.28, 1444.52, 1444.52, 20, 18, 'Active'),
(172, 120, '26MY015', NULL, '2028-10-02', 646.94, 722.16, 722.16, 20, 17, 'Active'),
(173, 121, '2595', NULL, '2028-10-02', 282.93, 311.54, 311.54, 25, 25, 'Active'),
(174, 122, '260704', NULL, '2028-10-02', 236.12, 257.10, 257.10, 30, 23, 'Active'),
(175, 123, '252394', NULL, '2028-10-02', 210.09, 228.76, 228.76, 30, 17, 'Active'),
(176, 124, 'DEFAULT', NULL, '2028-10-02', 282.74, 353.43, 353.43, 10, 10, 'Active'),
(177, 125, 'F5793', NULL, '2028-10-02', 309.85, 345.47, 345.47, 20, 0, 'Active'),
(178, 126, 'F5967', NULL, '2028-10-02', 221.85, 247.35, 247.35, 20, 19, 'Active'),
(179, 127, 'F5003', NULL, '2028-10-02', 310.08, 387.60, 387.60, 20, 20, 'Active'),
(180, 128, 'R270021', NULL, '2028-10-02', 176.63, 240.86, 240.86, 20, 19, 'Active'),
(181, 129, '002485', NULL, '2028-10-02', 177.93, 206.14, 206.14, 20, 16, 'Active'),
(182, 130, 'DEFAULT', NULL, '2028-10-02', 296.29, 426.48, 426.48, 20, 10, 'Active'),
(183, 131, 'S13AA', NULL, '2028-10-02', 420.19, 572.99, 572.99, 20, 16, 'Active'),
(184, 132, 'R14AE', NULL, '2028-10-02', 742.20, 1012.10, 1012.10, 20, 10, 'Active'),
(185, 133, 'S15BT', NULL, '2028-10-02', 984.89, 1166.32, 1166.32, 20, 18, 'Active'),
(186, 134, '545AB', NULL, '2028-10-02', 408.29, 456.32, 456.32, 12, 12, 'Active'),
(187, 135, '124', NULL, '2028-10-02', 160.35, 197.84, 197.84, 20, 20, 'Active'),
(188, 136, 'L2881', NULL, '2028-10-02', 355.30, 391.64, 391.64, 12, 0, 'Active'),
(189, 137, '(10)5848', NULL, '2028-10-02', 323.47, 369.13, 369.13, 20, 19, 'Active'),
(190, 138, '(10)5933', NULL, '2028-10-02', 542.67, 619.29, 619.29, 20, 20, 'Active'),
(191, 139, '044FP4', NULL, '2028-10-02', 285.43, 307.15, 307.15, 10, 7, 'Active'),
(192, 140, '072FP5', NULL, '2028-10-02', 324.53, 349.22, 349.22, 10, 4, 'Active'),
(193, 141, 'F01171', NULL, '2028-10-02', 525.98, 572.22, 572.22, 20, 16, 'Active'),
(194, 142, 'F19067', NULL, '2028-10-02', 424.58, 467.03, 467.03, 20, 19, 'Active'),
(195, 143, 'F09181', NULL, '2028-10-02', 906.07, 954.26, 954.26, 20, 15, 'Active'),
(196, 144, 'F08183', NULL, '2028-10-02', 939.62, 989.60, 989.60, 20, 18, 'Active'),
(197, 145, 'NG0E03-012', NULL, '2028-10-02', 613.05, 725.99, 725.99, 30, 29, 'Active'),
(198, 72, 'N7325', NULL, '2028-10-02', 612.22, 670.09, 670.09, 35, 33, 'Active'),
(199, 73, '021P002', NULL, '2028-10-02', 153.77, 181.69, 181.69, 10, 0, 'Active'),
(200, 110, '082126', NULL, '2028-10-02', 818.48, 884.21, 884.21, 40, 38, 'Active'),
(201, 116, '270074', NULL, '2028-10-02', 705.10, 990.00, 990.00, 10, 10, 'Active'),
(202, 146, '25N3002', NULL, '2028-10-04', 1948.86, 2098.11, 2098.11, 10, 10, 'Active'),
(203, 147, '872487XV', NULL, '2028-10-06', 398.11, 429.17, 429.17, 98, 58, 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

CREATE TABLE `purchases` (
  `id` int NOT NULL,
  `bill_no` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `purchase_date` date NOT NULL,
  `receiving_date` date NOT NULL,
  `supplier_id` int NOT NULL,
  `po_id` int DEFAULT NULL,
  `subtotal` decimal(14,2) DEFAULT '0.00',
  `discount_amount` decimal(12,2) DEFAULT '0.00',
  `tax_amount` decimal(12,2) DEFAULT '0.00',
  `freight_charges` decimal(12,2) DEFAULT '0.00',
  `grand_total` decimal(14,2) DEFAULT '0.00',
  `paid_amount` decimal(14,2) DEFAULT '0.00',
  `balance_amount` decimal(14,2) DEFAULT '0.00',
  `payment_type` enum('Cash','Credit','Bank') COLLATE utf8mb4_general_ci DEFAULT 'Credit',
  `payment_status` enum('Paid','Partial','Unpaid') COLLATE utf8mb4_general_ci DEFAULT 'Unpaid',
  `status` enum('Received','Pending') COLLATE utf8mb4_general_ci DEFAULT 'Received',
  `notes` text COLLATE utf8mb4_general_ci,
  `created_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchases`
--

INSERT INTO `purchases` (`id`, `bill_no`, `purchase_date`, `receiving_date`, `supplier_id`, `po_id`, `subtotal`, `discount_amount`, `tax_amount`, `freight_charges`, `grand_total`, `paid_amount`, `balance_amount`, `payment_type`, `payment_status`, `status`, `notes`, `created_by`) VALUES
(1, 'PUR-2026-0001', '2026-09-29', '2026-09-29', 1, NULL, 820429.52, 0.00, 0.00, 0.00, 820429.52, 0.00, 820429.52, 'Credit', 'Unpaid', 'Received', '', 1),
(2, 'PUR-2026-0002', '2026-09-29', '2026-09-29', 1, NULL, 15636.60, 0.00, 0.00, 0.00, 15636.60, 0.00, 15636.60, 'Credit', 'Unpaid', 'Received', '', 1),
(3, 'PUR-2026-0003', '2026-09-29', '2026-09-29', 1, NULL, 5576.34, 0.00, 0.00, 0.00, 5576.34, 0.00, 5576.34, 'Credit', 'Unpaid', 'Received', '', 1),
(4, 'PUR-2026-0004', '2026-09-29', '2026-09-29', 1, NULL, 36135.40, 0.00, 2208.10, 0.00, 36135.40, 0.00, 36135.40, 'Credit', 'Unpaid', 'Received', '', 1),
(5, 'PUR-2026-0005', '2026-09-29', '2026-09-29', 1, NULL, 10428.02, 0.00, 1635.42, 0.00, 10428.02, 0.00, 10428.02, 'Credit', 'Unpaid', 'Received', '', 1),
(6, 'PUR-2026-0006', '2026-09-29', '2026-09-29', 1, NULL, 7435.98, 0.00, 1166.18, 0.00, 7435.98, 0.00, 7435.98, 'Credit', 'Unpaid', 'Received', '', 1),
(7, 'PUR-2026-0007', '2026-09-29', '2026-09-29', 1, NULL, 7435.98, 0.00, 1166.18, 0.00, 7435.98, 0.00, 7435.98, 'Credit', 'Unpaid', 'Received', '', 1),
(8, 'PUR-2026-0008', '2026-09-29', '2026-09-29', 1, NULL, 28090.37, 0.00, 0.00, 0.00, 28090.37, 0.00, 28090.37, 'Credit', 'Unpaid', 'Received', '', 1),
(9, 'PUR-2026-0009', '2026-09-29', '2026-09-29', 1, NULL, 8211.00, 0.00, 0.00, 0.00, 8211.00, 0.00, 8211.00, 'Credit', 'Unpaid', 'Received', '', 1),
(10, 'PUR-2026-0010', '2026-09-29', '2026-09-29', 1, NULL, 29143.81, 0.00, 144.99, 0.00, 29143.81, 0.00, 29143.81, 'Credit', 'Unpaid', 'Received', '', 1),
(11, 'PUR-2026-0011', '2026-09-29', '2026-09-29', 1, NULL, 5259.37, 0.00, 0.00, 0.00, 5259.37, 0.00, 5259.37, 'Credit', 'Unpaid', 'Received', '', 1),
(12, 'PUR-2026-0012', '2026-09-29', '2026-09-29', 1, NULL, 11220.00, 0.00, 0.00, 0.00, 11220.00, 0.00, 11220.00, 'Credit', 'Unpaid', 'Received', '', 1),
(13, 'PUR-2026-0013', '2026-09-29', '2026-09-29', 1, NULL, 184836.93, 0.00, 0.00, 0.00, 184836.93, 0.00, 184836.93, 'Credit', 'Unpaid', 'Received', '', 1),
(14, 'PUR-2026-0014', '2026-09-29', '2026-09-29', 1, NULL, 59583.31, 0.00, 0.00, 0.00, 59583.31, 0.00, 59583.31, 'Credit', 'Unpaid', 'Received', '', 1),
(15, 'PUR-2026-0015', '2026-09-30', '2026-09-30', 1, NULL, 139471.41, 0.00, 0.00, 0.00, 139471.41, 0.00, 139471.41, 'Credit', 'Unpaid', 'Received', '', 1),
(16, 'PUR-2026-0016', '2026-09-30', '2026-09-30', 1, NULL, 89352.70, 0.00, 0.00, 0.00, 89352.70, 0.00, 89352.70, 'Credit', 'Unpaid', 'Received', '', 1),
(17, 'PUR-2026-0017', '2026-09-30', '2026-09-30', 1, NULL, 35077.29, 0.00, 0.00, 0.00, 35077.29, 0.00, 35077.29, 'Credit', 'Unpaid', 'Received', '', 1),
(18, 'PUR-2026-0018', '2026-09-30', '2026-09-30', 1, NULL, 84738.36, 0.00, 0.00, 0.00, 84738.36, 0.00, 84738.36, 'Credit', 'Unpaid', 'Received', '', 1),
(19, 'PUR-2026-0019', '2026-09-30', '2026-09-30', 1, NULL, 447970.77, 0.00, 2009.91, 0.00, 447970.77, 0.00, 447970.77, 'Credit', 'Unpaid', 'Received', '', 1),
(20, 'PUR-2026-0020', '2026-09-30', '2026-09-30', 1, NULL, 28593.40, 0.00, 0.00, 0.00, 28593.40, 0.00, 28593.40, 'Credit', 'Unpaid', 'Received', '', 1),
(21, 'PUR-2026-0021', '2026-09-30', '2026-09-30', 1, NULL, 15593.76, 0.00, 0.00, 0.00, 15593.76, 0.00, 15593.76, 'Credit', 'Unpaid', 'Received', '', 1),
(22, 'PUR-2026-0022', '2026-09-30', '2026-09-30', 1, NULL, 238564.40, 0.00, 0.00, 0.00, 238564.40, 0.00, 238564.40, 'Credit', 'Unpaid', 'Received', '', 1),
(23, 'PUR-2026-0023', '2026-10-01', '2026-10-01', 1, NULL, 16676.66, 0.00, 0.00, 0.00, 16676.66, 0.00, 16676.66, 'Credit', 'Unpaid', 'Received', '', 1),
(24, 'PUR-2026-0024', '2026-10-01', '2026-10-01', 1, NULL, 64487.28, 0.00, 0.00, 0.00, 64487.28, 0.00, 64487.28, 'Credit', 'Unpaid', 'Received', '', 1),
(25, 'PUR-2026-0025', '2026-10-02', '2026-10-02', 2, NULL, 558410.27, 0.00, 0.00, 0.00, 558410.27, 0.00, 558410.27, 'Credit', 'Unpaid', 'Received', '', 1),
(26, 'PUR-2026-0026', '2026-10-02', '2026-10-02', 1, NULL, 30169.86, 0.00, 129.90, 0.00, 30169.86, 0.00, 30169.86, 'Credit', 'Unpaid', 'Received', '', 1),
(27, 'PUR-2026-0027', '2026-10-02', '2026-10-02', 2, NULL, 3140.83, 0.00, 0.00, 0.00, 3140.83, 0.00, 3140.83, 'Credit', 'Unpaid', 'Received', '', 1),
(28, 'PUR-2026-0028', '2026-10-02', '2026-10-02', 1, NULL, 39790.13, 0.00, 162.88, 0.00, 39790.13, 0.00, 39790.13, 'Credit', 'Unpaid', 'Received', '', 1),
(29, 'PUR-2026-0029', '2026-10-04', '2026-10-04', 2, NULL, 19488.63, 0.00, 0.00, 0.00, 19488.63, 0.00, 19488.63, 'Credit', 'Unpaid', 'Received', '', 1),
(30, 'PUR-2026-0030', '2026-10-06', '2026-10-06', 1, NULL, 39015.00, 0.00, 0.00, 0.00, 39015.00, 0.00, 39015.00, 'Credit', 'Unpaid', 'Received', '', 1),
(31, 'PUR-2026-0031', '2026-10-07', '2026-10-07', 1, NULL, 946.56, 0.00, 0.00, 0.00, 946.56, 0.00, 946.56, 'Credit', 'Unpaid', 'Received', '', 1),
(32, 'PUR-2026-0032', '2026-10-07', '2026-10-07', 1, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Credit', 'Unpaid', 'Received', '', 1);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_items`
--

CREATE TABLE `purchase_items` (
  `id` int NOT NULL,
  `purchase_id` int NOT NULL,
  `product_id` int NOT NULL,
  `batch_no` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `expiry_date` date NOT NULL,
  `quantity` int NOT NULL,
  `raw_quantity` int DEFAULT NULL,
  `unit_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'Pack',
  `bonus_quantity` int DEFAULT '0',
  `purchase_price` decimal(12,2) NOT NULL,
  `trade_price` decimal(12,2) NOT NULL,
  `retail_price` decimal(12,2) NOT NULL,
  `discount_percent` decimal(5,2) DEFAULT '0.00',
  `sale_discount_percent` decimal(5,2) DEFAULT '0.00',
  `discount_amount` decimal(12,2) DEFAULT '0.00',
  `total_price` decimal(14,2) NOT NULL,
  `tax_percent` decimal(5,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(12,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_items`
--

INSERT INTO `purchase_items` (`id`, `purchase_id`, `product_id`, `batch_no`, `expiry_date`, `quantity`, `raw_quantity`, `unit_type`, `bonus_quantity`, `purchase_price`, `trade_price`, `retail_price`, `discount_percent`, `sale_discount_percent`, `discount_amount`, `total_price`, `tax_percent`, `tax_amount`) VALUES
(46, 3, 47, 'DEFAULT', '2028-09-29', 35, 35, 'Pcs', 0, 187.00, 187.00, 177.65, 14.80, 5.00, 968.66, 5576.34, 0.00, 0.00),
(47, 4, 35, 'DEFAULT', '2028-09-29', 50, 50, 'Pcs', 0, 236.02, 236.02, 236.02, 10.00, 0.00, 1180.10, 11258.15, 6.00, 637.25),
(48, 4, 36, 'DEFAULT', '2028-09-29', 60, 60, 'Pcs', 0, 431.60, 431.60, 431.60, 10.00, 0.00, 2589.60, 24877.25, 6.74, 1570.85),
(49, 5, 55, 'DEFAULT', '2028-09-29', 5, 5, 'Pcs', 0, 720.80, 720.80, 684.76, 30.00, 5.00, 1081.20, 2992.04, 18.60, 469.24),
(50, 5, 54, 'DEFAULT', '2028-09-29', 10, 10, 'Pcs', 0, 922.03, 922.03, 875.93, 32.00, 5.00, 2950.50, 7435.98, 18.60, 1166.18),
(51, 6, 56, 'DEFAULT', '2028-09-29', 10, 10, 'Pcs', 0, 922.03, 922.03, 875.93, 32.00, 5.00, 2950.50, 7435.98, 18.60, 1166.18),
(52, 7, 57, 'DEFAULT', '2028-09-29', 10, 10, 'Pcs', 0, 922.03, 922.03, 875.93, 32.00, 5.00, 2950.50, 7435.98, 18.60, 1166.18),
(58, 10, 59, 'DEFAULT', '2028-09-29', 20, 20, 'Pcs', 0, 1025.41, 1025.41, 871.60, 28.00, 15.00, 5742.30, 14839.73, 0.50, 73.83),
(59, 10, 60, 'DEFAULT', '2028-09-29', 30, 30, 'Pcs', 0, 324.50, 324.50, 275.82, 30.55, 15.00, 2974.04, 6794.76, 0.50, 33.80),
(60, 10, 61, 'DEFAULT', '2028-09-29', 30, 30, 'Pcs', 0, 355.30, 355.30, 302.00, 29.90, 15.00, 3187.04, 7509.32, 0.50, 37.36),
(61, 11, 62, 'DEFAULT', '2028-09-29', 30, 30, 'Pcs', 0, 233.75, 233.75, 222.06, 25.00, 5.00, 1753.13, 5259.37, 0.00, 0.00),
(63, 13, 3, 'DEFAULT', '2028-09-29', 3, 3, 'Pcs', 0, 1219.75, 1219.75, 1195.36, 9.00, 2.00, 329.33, 3329.92, 0.00, 0.00),
(64, 13, 2, 'DEFAULT', '2028-09-29', 200, 200, 'Pcs', 50, 603.17, 603.17, 554.92, 25.00, 8.00, 30158.50, 90475.50, 0.00, 0.00),
(65, 13, 4, 'DEFAULT', '2028-09-29', 40, 40, 'Pcs', 0, 428.66, 428.66, 420.09, 10.00, 2.00, 1714.64, 15431.76, 0.00, 0.00),
(66, 13, 1, 'DEFAULT', '2028-09-29', 320, 320, 'Pcs', 80, 185.05, 185.05, 175.80, 0.00, 5.00, 0.00, 59216.00, 0.00, 0.00),
(67, 13, 5, 'DEFAULT', '2028-09-29', 10, 10, 'Pcs', 0, 573.75, 573.75, 562.27, 10.00, 2.00, 573.75, 5163.75, 0.00, 0.00),
(68, 13, 63, 'DEFAULT', '2028-09-29', 30, 30, 'Pcs', 0, 425.00, 425.00, 408.00, 12.00, 4.00, 1530.00, 11220.00, 0.00, 0.00),
(69, 14, 11, 'DEFAULT', '2028-09-29', 10, 10, 'Pcs', 0, 833.00, 833.00, 816.34, 10.00, 2.00, 833.00, 7497.00, 0.00, 0.00),
(70, 14, 10, 'DEFAULT', '2028-09-29', 50, 50, 'Pcs', 0, 351.48, 351.48, 344.45, 10.00, 2.00, 1757.40, 15816.60, 0.00, 0.00),
(71, 14, 9, 'DEFAULT', '2028-09-29', 60, 60, 'Pcs', 0, 252.71, 252.71, 247.66, 12.00, 2.00, 1819.51, 13343.09, 0.00, 0.00),
(72, 14, 6, 'DEFAULT', '2028-09-29', 20, 20, 'Pcs', 0, 340.00, 340.00, 333.20, 8.00, 2.00, 544.00, 6256.00, 0.00, 0.00),
(73, 14, 7, 'DEFAULT', '2028-09-29', 30, 30, 'Pcs', 0, 233.75, 233.75, 231.41, 6.00, 1.00, 420.75, 6591.75, 0.00, 0.00),
(74, 14, 8, 'DEFAULT', '2028-09-29', 25, 25, 'Pcs', 0, 433.50, 433.50, 429.17, 7.00, 1.00, 758.63, 10078.87, 0.00, 0.00),
(75, 15, 14, 'DEFAULT', '2028-09-30', 100, 100, 'Pcs', 0, 675.19, 675.19, 661.69, 7.54, 2.00, 5090.93, 62428.07, 0.00, 0.00),
(76, 15, 15, 'DEFAULT', '2028-09-30', 100, 100, 'Pcs', 0, 466.40, 466.40, 457.07, 7.54, 2.00, 3516.66, 43123.34, 0.00, 0.00),
(77, 15, 17, 'DEFAULT', '2028-09-30', 200, 200, 'Pcs', 20, 169.60, 169.60, 161.12, 0.00, 5.00, 0.00, 33920.00, 0.00, 0.00),
(78, 16, 23, 'DEFAULT', '2028-09-30', 12, 12, 'Pcs', 0, 350.20, 350.20, 332.69, 25.00, 5.00, 1050.60, 3151.80, 0.00, 0.00),
(79, 16, 19, 'DEFAULT', '2028-09-30', 50, 50, 'Pcs', 0, 782.00, 782.00, 719.44, 18.00, 8.00, 7038.00, 32062.00, 0.00, 0.00),
(80, 16, 18, 'DEFAULT', '2028-09-30', 20, 20, 'Pcs', 0, 127.39, 127.39, 121.02, 20.00, 5.00, 509.56, 2038.24, 0.00, 0.00),
(81, 16, 16, 'DEFAULT', '2028-09-30', 50, 50, 'Pcs', 5, 696.45, 696.45, 682.52, 0.00, 2.00, 0.00, 34822.50, 0.00, 0.00),
(82, 16, 13, 'DEFAULT', '2028-09-30', 50, 50, 'Pcs', 0, 199.75, 199.75, 195.75, 8.50, 2.00, 848.94, 9138.56, 0.00, 0.00),
(83, 16, 12, 'DEFAULT', '2028-09-30', 10, 10, 'Pcs', 0, 904.40, 904.40, 886.31, 10.00, 2.00, 904.40, 8139.60, 0.00, 0.00),
(84, 17, 28, 'DEFAULT', '2028-09-30', 136, 136, 'Pcs', 8, 61.20, 61.20, 59.36, 5.00, 3.00, 416.16, 7907.04, 0.00, 0.00),
(85, 17, 29, 'DEFAULT', '2028-09-30', 250, 250, 'Pcs', 38, 102.00, 102.00, 96.90, 5.00, 5.00, 1275.00, 24225.00, 0.00, 0.00),
(86, 17, 24, 'DEFAULT', '2028-09-30', 12, 12, 'Pcs', 0, 327.25, 327.25, 310.89, 25.00, 5.00, 981.75, 2945.25, 0.00, 0.00),
(87, 18, 25, 'DEFAULT', '2028-09-30', 50, 50, 'Pcs', 5, 464.64, 464.64, 455.35, 2.00, 2.00, 464.64, 22767.36, 0.00, 0.00),
(88, 18, 26, 'DEFAULT', '2028-09-30', 50, 50, 'Pcs', 0, 841.62, 841.62, 824.79, 0.00, 2.00, 0.00, 42081.00, 0.00, 0.00),
(89, 18, 27, 'DEFAULT', '2028-09-30', 120, 120, 'Pcs', 20, 165.75, 165.75, 160.78, 0.00, 3.00, 0.00, 19890.00, 0.00, 0.00),
(90, 19, 30, 'DEFAULT', '2028-09-30', 250, 250, 'Pcs', 38, 102.00, 102.00, 96.90, 5.00, 5.00, 1275.00, 24225.00, 0.00, 0.00),
(91, 19, 31, 'DEFAULT', '2028-09-30', 136, 136, 'Pcs', 8, 61.20, 61.20, 58.14, 0.00, 5.00, 0.00, 8739.36, 5.00, 416.16),
(92, 19, 32, 'DEFAULT', '2028-09-30', 250, 250, 'Pcs', 35, 127.50, 127.50, 121.13, 0.00, 5.00, 0.00, 33468.75, 5.00, 1593.75),
(93, 19, 33, 'DEFAULT', '2028-09-30', 10, 10, 'Pcs', 12, 382.50, 382.50, 344.25, 0.00, 10.00, 0.00, 3825.00, 0.00, 0.00),
(94, 19, 34, 'DEFAULT', '2028-09-30', 30, 30, 'Pcs', 15, 242.53, 242.53, 218.28, 0.00, 10.00, 0.00, 7275.90, 0.00, 0.00),
(95, 19, 38, 'DEFAULT', '2028-09-30', 200, 200, 'Pcs', 30, 153.53, 153.53, 145.85, 0.00, 5.00, 0.00, 30706.00, 0.00, 0.00),
(96, 19, 43, 'DEFAULT', '2028-09-30', 100, 100, 'Pcs', 25, 134.89, 134.89, 124.10, 0.00, 8.00, 0.00, 13489.00, 0.00, 0.00),
(97, 19, 41, 'DEFAULT', '2028-09-30', 70, 70, 'Pcs', 0, 531.57, 531.57, 504.99, 15.00, 5.00, 5581.49, 31628.42, 0.00, 0.00),
(98, 19, 42, 'DEFAULT', '2028-09-30', 70, 70, 'Pcs', 0, 322.69, 322.69, 306.56, 15.00, 5.00, 3388.25, 19200.06, 0.00, 0.00),
(99, 19, 40, 'DEFAULT', '2028-09-30', 200, 200, 'Pcs', 30, 190.03, 190.03, 180.53, 0.00, 5.00, 0.00, 38006.00, 0.00, 0.00),
(100, 19, 39, 'DEFAULT', '2028-09-30', 50, 50, 'Pcs', 0, 101.48, 101.48, 96.41, 16.00, 5.00, 811.84, 4262.16, 0.00, 0.00),
(101, 19, 22, 'DEFAULT', '2028-09-30', 30, 30, 'Pcs', 0, 580.89, 580.89, 534.42, 20.00, 8.00, 3485.34, 13941.36, 0.00, 0.00),
(102, 19, 48, 'DEFAULT', '2028-09-30', 90, 90, 'Pcs', 0, 809.00, 809.00, 809.00, 7.00, 0.00, 5096.70, 67713.30, 0.00, 0.00),
(103, 19, 49, 'DEFAULT', '2028-09-30', 20, 20, 'Pcs', 0, 619.82, 619.82, 607.42, 12.00, 2.00, 1487.57, 10908.83, 0.00, 0.00),
(104, 19, 50, 'DEFAULT', '2028-09-30', 50, 50, 'Pcs', 0, 301.58, 301.58, 295.55, 10.00, 2.00, 1507.90, 13571.10, 0.00, 0.00),
(105, 19, 52, 'DEFAULT', '2028-09-30', 15, 15, 'Pcs', 0, 790.66, 790.66, 751.13, 15.00, 5.00, 1778.99, 10080.92, 0.00, 0.00),
(106, 19, 51, 'DEFAULT', '2028-09-30', 30, 30, 'Pcs', 0, 456.93, 456.93, 447.79, 10.00, 2.00, 1370.79, 12337.11, 0.00, 0.00),
(107, 19, 46, 'DEFAULT', '2028-09-30', 60, 60, 'Pcs', 10, 1275.00, 1275.00, 1236.75, 0.00, 3.00, 0.00, 76500.00, 0.00, 0.00),
(108, 19, 45, 'DEFAULT', '2028-09-30', 40, 40, 'Pcs', 4, 442.00, 442.00, 433.16, 0.00, 2.00, 0.00, 17680.00, 0.00, 0.00),
(109, 19, 44, 'DEFAULT', '2028-09-30', 50, 50, 'Pcs', 50, 208.25, 208.25, 156.19, 0.00, 25.00, 0.00, 10412.50, 0.00, 0.00),
(110, 20, 58, 'DEFAULT', '2028-09-30', 20, 20, 'Pcs', 0, 446.25, 446.25, 437.32, 8.00, 2.00, 714.00, 8211.00, 0.00, 0.00),
(111, 20, 18, 'DEFAULT', '2028-09-30', 200, 200, 'Pcs', 0, 127.39, 127.39, 121.02, 20.00, 5.00, 5095.60, 20382.40, 0.00, 0.00),
(112, 21, 53, 'DEFAULT', '2028-09-30', 10, 10, 'Pcs', 0, 1713.60, 1713.60, 1696.46, 9.00, 1.00, 1542.24, 15593.76, 0.00, 0.00),
(113, 22, 64, 'DEFAULT', '2028-09-30', 100, 100, 'Pcs', 0, 201.02, 201.02, 190.97, 15.00, 5.00, 3015.30, 17086.70, 0.00, 0.00),
(114, 22, 65, 'DEFAULT', '2028-09-30', 100, 100, 'Pcs', 0, 354.02, 354.02, 336.32, 15.00, 5.00, 5310.30, 30091.70, 0.00, 0.00),
(115, 22, 66, 'DEFAULT', '2028-09-30', 100, 100, 'Pcs', 0, 540.40, 540.40, 513.38, 15.00, 5.00, 8106.00, 45934.00, 0.00, 0.00),
(116, 22, 67, 'DEFAULT', '2028-09-30', 100, 100, 'Pcs', 0, 597.70, 597.70, 567.82, 15.00, 5.00, 8965.50, 50804.50, 0.00, 0.00),
(117, 22, 68, 'DEFAULT', '2028-09-30', 100, 100, 'Pcs', 0, 1113.50, 1113.50, 1057.83, 15.00, 5.00, 16702.50, 94647.50, 0.00, 0.00),
(118, 23, 53, 'DEFAULT', '2028-10-01', 5, 5, 'Pcs', 0, 1713.60, 1713.60, 1696.46, 9.00, 1.00, 771.12, 7796.88, 0.00, 0.00),
(119, 23, 3, 'DEFAULT', '2028-10-01', 8, 8, 'Pcs', 0, 1219.75, 1219.75, 1195.36, 9.00, 2.00, 878.22, 8879.78, 0.00, 0.00),
(120, 24, 26, 'DEFAULT', '2028-10-01', 12, 12, 'Pcs', 2, 841.62, 841.62, 824.79, 0.00, 2.00, 0.00, 10099.44, 0.00, 0.00),
(121, 24, 69, 'DEFAULT', '2028-10-01', 24, 24, 'Pcs', 4, 420.81, 420.81, 412.39, 0.00, 2.00, 0.00, 10099.44, 0.00, 0.00),
(122, 24, 70, 'DEFAULT', '2028-10-01', 60, 60, 'Pcs', 10, 246.37, 246.37, 241.44, 0.00, 2.00, 0.00, 14782.20, 0.00, 0.00),
(123, 24, 71, 'DEFAULT', '2028-10-01', 60, 60, 'Pcs', 10, 491.77, 491.77, 481.93, 0.00, 2.00, 0.00, 29506.20, 0.00, 0.00),
(124, 25, 74, 'DEFAULT', '2028-10-02', 30, 30, 'Pcs', 0, 148.75, 148.75, 147.26, 8.00, 1.00, 357.00, 4105.50, 0.00, 0.00),
(125, 25, 75, 'DEFAULT', '2028-10-02', 50, 50, 'Pcs', 0, 24.94, 24.94, 24.69, 7.00, 1.00, 87.29, 1159.71, 0.00, 0.00),
(126, 25, 76, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 119.67, 119.67, 113.69, 15.00, 5.00, 359.01, 2034.39, 0.00, 0.00),
(127, 25, 77, 'DEFAULT', '2028-10-02', 25, 25, 'Pcs', 0, 212.75, 212.75, 202.11, 15.00, 5.00, 797.81, 4520.94, 0.00, 0.00),
(128, 25, 78, 'DEFAULT', '2028-10-02', 50, 50, 'Pcs', 0, 106.25, 106.25, 105.19, 8.00, 1.00, 425.00, 4887.50, 0.00, 0.00),
(129, 25, 79, 'DEFAULT', '2028-10-02', 50, 50, 'Pcs', 0, 74.86, 74.86, 74.11, 7.00, 1.00, 262.01, 3480.99, 0.00, 0.00),
(130, 25, 80, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 476.00, 476.00, 466.48, 10.00, 2.00, 952.00, 8568.00, 0.00, 0.00),
(131, 25, 81, 'DEFAULT', '2028-10-02', 10, 10, 'Pcs', 0, 450.50, 450.50, 441.49, 10.00, 2.00, 450.50, 4054.50, 0.00, 0.00),
(132, 25, 82, 'DEFAULT', '2028-10-02', 15, 15, 'Pcs', 0, 312.37, 312.37, 306.12, 15.00, 2.00, 702.83, 3982.72, 0.00, 0.00),
(133, 25, 83, 'DEFAULT', '2028-10-02', 12, 12, 'Pcs', 0, 400.18, 400.18, 392.18, 10.00, 2.00, 480.22, 4321.94, 0.00, 0.00),
(134, 25, 84, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 267.60, 267.60, 264.92, 8.00, 1.00, 428.16, 4923.84, 0.00, 0.00),
(135, 25, 85, 'DEFAULT', '2028-10-02', 10, 10, 'Pcs', 0, 1955.00, 1955.00, 1857.25, 17.00, 5.00, 3323.50, 16226.50, 0.00, 0.00),
(136, 25, 86, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 212.50, 212.50, 206.13, 15.00, 3.00, 637.50, 3612.50, 0.00, 0.00),
(137, 25, 87, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 484.50, 484.50, 469.96, 3.00, 3.00, 290.70, 9399.30, 0.00, 0.00),
(138, 25, 88, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 148.75, 148.75, 133.88, 40.00, 10.00, 1190.00, 1785.00, 0.00, 0.00),
(139, 25, 89, 'DEFAULT', '2028-10-02', 10, 10, 'Pcs', 0, 233.75, 233.75, 210.38, 40.00, 10.00, 935.00, 1402.50, 0.00, 0.00),
(140, 25, 90, 'DEFAULT', '2028-10-02', 10, 10, 'Pcs', 0, 510.00, 510.00, 433.50, 40.00, 15.00, 2040.00, 3060.00, 0.00, 0.00),
(141, 25, 91, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 1197.65, 1197.65, 1137.77, 20.00, 5.00, 4790.60, 19162.40, 0.00, 0.00),
(142, 25, 92, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 439.12, 439.12, 417.16, 20.00, 5.00, 1756.48, 7025.92, 0.00, 0.00),
(143, 25, 93, 'DEFAULT', '2028-10-02', 25, 25, 'Pcs', 0, 361.25, 361.25, 354.02, 10.00, 2.00, 903.13, 8128.12, 0.00, 0.00),
(144, 25, 94, 'DEFAULT', '2028-10-02', 50, 50, 'Pcs', 0, 599.25, 599.25, 587.26, 7.00, 2.00, 2097.38, 27865.12, 0.00, 0.00),
(145, 25, 95, 'DEFAULT', '2028-10-02', 30, 30, 'Pcs', 0, 617.10, 617.10, 604.76, 7.00, 2.00, 1295.91, 17217.09, 0.00, 0.00),
(146, 25, 96, 'DEFAULT', '2028-10-02', 10, 10, 'Pcs', 0, 722.33, 722.33, 715.11, 8.00, 1.00, 577.86, 6645.44, 0.00, 0.00),
(147, 25, 97, 'DEFAULT', '2028-10-02', 10, 10, 'Pcs', 0, 242.25, 242.25, 237.41, 10.00, 2.00, 242.25, 2180.25, 0.00, 0.00),
(148, 25, 98, 'DEFAULT', '2028-10-02', 10, 10, 'Pcs', 0, 464.10, 464.10, 454.82, 10.00, 2.00, 464.10, 4176.90, 0.00, 0.00),
(149, 25, 99, 'DEFAULT', '2028-10-02', 10, 10, 'Pcs', 0, 654.21, 654.21, 641.13, 10.00, 2.00, 654.21, 5887.89, 0.00, 0.00),
(150, 25, 100, 'DEFAULT', '2028-10-02', 10, 10, 'Pcs', 0, 697.00, 697.00, 697.00, 5.00, 0.00, 348.50, 6621.50, 0.00, 0.00),
(151, 25, 101, 'DEFAULT', '2028-10-02', 10, 10, 'Pcs', 0, 472.86, 472.86, 472.86, 4.00, 0.00, 189.14, 4539.46, 0.00, 0.00),
(152, 25, 102, 'DEFAULT', '2028-10-02', 1, 1, 'Pcs', 0, 654.34, 654.34, 654.34, 4.00, 0.00, 26.17, 628.17, 0.00, 0.00),
(153, 25, 103, 'DEFAULT', '2028-10-02', 30, 30, 'Pcs', 0, 567.80, 567.80, 528.05, 20.00, 7.00, 3406.80, 13627.20, 0.00, 0.00),
(154, 25, 104, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 571.31, 571.31, 531.32, 20.00, 7.00, 2285.24, 9140.96, 0.00, 0.00),
(155, 25, 105, 'DEFAULT', '2028-10-02', 25, 25, 'Pcs', 0, 512.47, 512.47, 502.22, 10.00, 2.00, 1281.18, 11530.57, 0.00, 0.00),
(156, 25, 106, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 875.50, 875.50, 857.99, 10.00, 2.00, 1751.00, 15759.00, 0.00, 0.00),
(157, 25, 107, 'DEFAULT', '2028-10-02', 25, 25, 'Pcs', 0, 602.65, 602.65, 590.60, 10.00, 2.00, 1506.63, 13559.62, 0.00, 0.00),
(158, 25, 108, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 392.70, 392.70, 384.85, 8.00, 2.00, 628.32, 7225.68, 0.00, 0.00),
(159, 25, 109, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 816.00, 816.00, 734.40, 38.00, 10.00, 6201.60, 10118.40, 0.00, 0.00),
(160, 25, 111, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 191.25, 191.25, 181.69, 16.00, 5.00, 612.00, 3213.00, 0.00, 0.00),
(161, 25, 112, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 242.25, 242.25, 230.14, 5.00, 5.00, 242.25, 4602.75, 0.00, 0.00),
(162, 25, 113, 'DEFAULT', '2028-10-02', 17, 17, 'Pcs', 0, 522.75, 522.75, 517.52, 8.00, 1.00, 710.94, 8175.81, 0.00, 0.00),
(163, 25, 114, 'DEFAULT', '2028-10-02', 5, 5, 'Pcs', 0, 535.50, 535.50, 530.14, 8.00, 1.00, 214.20, 2463.30, 0.00, 0.00),
(164, 25, 115, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 331.50, 331.50, 328.19, 8.00, 1.00, 530.40, 6099.60, 0.00, 0.00),
(165, 25, 117, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 364.65, 364.65, 346.42, 14.00, 5.00, 1021.02, 6271.98, 0.00, 0.00),
(166, 25, 118, 'DEFAULT', '2028-10-02', 30, 30, 'Pcs', 0, 364.65, 364.65, 346.42, 17.00, 5.00, 1859.72, 9079.78, 0.00, 0.00),
(167, 25, 119, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 1489.20, 1489.20, 1444.52, 10.00, 3.00, 2978.40, 26805.60, 0.00, 0.00),
(168, 25, 120, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 752.25, 752.25, 722.16, 14.00, 4.00, 2106.30, 12938.70, 0.00, 0.00),
(169, 25, 121, 'DEFAULT', '2028-10-02', 25, 25, 'Pcs', 0, 317.90, 317.90, 311.54, 11.00, 2.00, 874.23, 7073.28, 0.00, 0.00),
(170, 25, 122, 'DEFAULT', '2028-10-02', 30, 30, 'Pcs', 0, 262.35, 262.35, 257.10, 10.00, 2.00, 787.05, 7083.45, 0.00, 0.00),
(171, 25, 123, 'DEFAULT', '2028-10-02', 30, 30, 'Pcs', 0, 233.43, 233.43, 228.76, 10.00, 2.00, 700.29, 6302.61, 0.00, 0.00),
(172, 25, 124, 'DEFAULT', '2028-10-02', 10, 10, 'Pcs', 0, 392.70, 392.70, 353.43, 28.00, 10.00, 1099.56, 2827.44, 0.00, 0.00),
(173, 25, 125, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 356.15, 356.15, 345.47, 13.00, 3.00, 925.99, 6197.01, 0.00, 0.00),
(174, 25, 126, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 255.00, 255.00, 247.35, 13.00, 3.00, 663.00, 4437.00, 0.00, 0.00),
(175, 25, 127, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 408.00, 408.00, 387.60, 24.00, 5.00, 1958.40, 6201.60, 0.00, 0.00),
(176, 25, 128, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 267.62, 267.62, 240.86, 34.00, 10.00, 1819.82, 3532.58, 0.00, 0.00),
(177, 25, 129, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 216.99, 216.99, 206.14, 18.00, 5.00, 781.16, 3558.64, 0.00, 0.00),
(178, 25, 130, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 448.93, 448.93, 426.48, 34.00, 5.00, 3052.72, 5925.88, 0.00, 0.00),
(179, 25, 131, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 636.65, 636.65, 572.99, 34.00, 10.00, 4329.22, 8403.78, 0.00, 0.00),
(180, 25, 132, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 1124.55, 1124.55, 1012.10, 34.00, 10.00, 7646.94, 14844.06, 0.00, 0.00),
(181, 25, 133, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 1295.91, 1295.91, 1166.32, 24.00, 10.00, 6220.37, 19697.83, 0.00, 0.00),
(182, 25, 134, 'DEFAULT', '2028-10-02', 12, 12, 'Pcs', 0, 480.34, 480.34, 456.32, 15.00, 5.00, 864.61, 4899.47, 0.00, 0.00),
(183, 25, 135, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 208.25, 208.25, 197.84, 23.00, 5.00, 957.95, 3207.05, 0.00, 0.00),
(184, 25, 136, 'DEFAULT', '2028-10-02', 12, 12, 'Pcs', 0, 403.75, 403.75, 391.64, 12.00, 3.00, 581.40, 4263.60, 0.00, 0.00),
(185, 25, 137, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 380.55, 380.55, 369.13, 15.00, 3.00, 1141.65, 6469.35, 0.00, 0.00),
(186, 25, 138, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 638.44, 638.44, 619.29, 15.00, 3.00, 1915.32, 10853.48, 0.00, 0.00),
(187, 25, 139, 'DEFAULT', '2028-10-02', 10, 10, 'Pcs', 0, 310.25, 310.25, 307.15, 8.00, 1.00, 248.20, 2854.30, 0.00, 0.00),
(188, 25, 140, 'DEFAULT', '2028-10-02', 10, 10, 'Pcs', 0, 352.75, 352.75, 349.22, 8.00, 1.00, 282.20, 3245.30, 0.00, 0.00),
(189, 25, 141, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 578.00, 578.00, 572.22, 9.00, 1.00, 1040.40, 10519.60, 0.00, 0.00),
(190, 25, 142, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 471.75, 471.75, 467.03, 10.00, 1.00, 943.50, 8491.50, 0.00, 0.00),
(191, 25, 143, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 963.90, 963.90, 954.26, 6.00, 1.00, 1156.68, 18121.32, 0.00, 0.00),
(192, 25, 144, 'DEFAULT', '2028-10-02', 20, 20, 'Pcs', 0, 999.60, 999.60, 989.60, 6.00, 1.00, 1199.52, 18792.48, 0.00, 0.00),
(193, 25, 145, 'DEFAULT', '2028-10-02', 30, 30, 'Pcs', 0, 806.65, 806.65, 725.99, 24.00, 10.00, 5807.88, 18391.62, 0.00, 0.00),
(194, 26, 72, 'DEFAULT', '2028-10-02', 35, 35, 'Pcs', 0, 676.86, 676.86, 670.09, 10.00, 1.00, 2369.01, 21427.70, 0.50, 106.61),
(195, 26, 73, 'DEFAULT', '2028-10-02', 10, 10, 'Pcs', 0, 191.25, 191.25, 181.69, 20.00, 5.00, 382.50, 1537.65, 0.50, 7.65),
(196, 26, 6, 'DEFAULT', '2028-10-02', 10, 10, 'Pcs', 0, 340.00, 340.00, 333.20, 8.00, 2.00, 272.00, 3143.64, 0.50, 15.64),
(197, 26, 58, 'DEFAULT', '2028-10-02', 10, 10, 'Pcs', 0, 446.25, 446.25, 437.32, 9.00, 2.00, 401.63, 4060.87, 0.00, 0.00),
(198, 27, 102, 'DEFAULT', '2028-10-02', 5, 5, 'Pcs', 0, 654.34, 654.34, 654.34, 4.00, 0.00, 130.87, 3140.83, 0.00, 0.00),
(199, 28, 110, 'DEFAULT', '2028-10-02', 35, 35, 'Pcs', 5, 930.75, 930.75, 884.21, 0.00, 5.00, 0.00, 32739.13, 0.50, 162.88),
(200, 28, 116, 'DEFAULT', '2028-10-02', 10, 10, 'Pcs', 0, 1100.00, 1100.00, 990.00, 35.90, 10.00, 3949.00, 7051.00, 0.00, 0.00),
(201, 29, 146, 'DEFAULT', '2028-10-04', 10, 10, 'Pcs', 0, 2163.00, 2163.00, 2098.11, 9.90, 3.00, 2141.37, 19488.63, 0.00, 0.00),
(202, 30, 147, 'DEFAULT', '2028-10-06', 90, 90, 'Pcs', 8, 433.50, 433.50, 429.17, 0.00, 1.00, 0.00, 39015.00, 0.00, 0.00),
(203, 31, 57, 'DEFAULT', '2028-10-07', 1, 1, 'Pcs', 0, 1088.00, 1088.00, 1033.60, 13.00, 5.00, 141.44, 946.56, 0.00, 0.00),
(204, 32, 115, 'DEFAULT', '2028-10-07', 1, 1, 'Pcs', 0, 331.50, 331.50, 328.19, 0.00, 1.00, 0.00, 0.00, 0.00, 0.00),
(205, 32, 74, 'DEFAULT', '2028-10-07', 1, 1, 'Pcs', 0, 148.75, 148.75, 147.26, 0.00, 1.00, 0.00, 0.00, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_returns`
--

CREATE TABLE `purchase_returns` (
  `id` int NOT NULL,
  `return_no` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `purchase_id` int DEFAULT NULL,
  `supplier_id` int NOT NULL,
  `return_date` date NOT NULL,
  `total_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `reason` text COLLATE utf8mb4_general_ci,
  `payment_method` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cash_account_id` int DEFAULT NULL,
  `bank_account_id` int DEFAULT NULL,
  `status` enum('Completed','Pending','Cancelled') COLLATE utf8mb4_general_ci DEFAULT 'Completed',
  `created_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_return_items`
--

CREATE TABLE `purchase_return_items` (
  `id` int NOT NULL,
  `purchase_return_id` int NOT NULL,
  `purchase_item_id` int DEFAULT NULL,
  `product_id` int NOT NULL,
  `batch_no` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `purchase_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_price` decimal(14,2) NOT NULL DEFAULT '0.00',
  `return_condition` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'Good'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `routes`
--

CREATE TABLE `routes` (
  `id` int NOT NULL,
  `route_code` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `area_description` text COLLATE utf8mb4_general_ci,
  `delivery_day` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `assigned_booker_id` int DEFAULT NULL,
  `status` enum('Active','Inactive') COLLATE utf8mb4_general_ci DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int NOT NULL,
  `invoice_no` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `sale_date` date NOT NULL,
  `sale_time` time DEFAULT NULL,
  `customer_id` int NOT NULL,
  `route_id` int DEFAULT NULL,
  `booker_id` int DEFAULT NULL,
  `subtotal` decimal(14,2) DEFAULT '0.00',
  `item_discount` decimal(12,2) DEFAULT '0.00',
  `special_discount_percent` decimal(5,2) DEFAULT '0.00',
  `special_discount_amount` decimal(12,2) DEFAULT '0.00',
  `tax_percent` decimal(5,2) DEFAULT '0.00',
  `tax_amount` decimal(12,2) DEFAULT '0.00',
  `round_off` decimal(6,2) DEFAULT '0.00',
  `grand_total` decimal(14,2) DEFAULT '0.00',
  `paid_amount` decimal(14,2) DEFAULT '0.00',
  `balance_amount` decimal(14,2) DEFAULT '0.00',
  `payment_type` enum('Cash','Credit','Bank') COLLATE utf8mb4_general_ci DEFAULT 'Credit',
  `payment_status` enum('Paid','Partial','Unpaid') COLLATE utf8mb4_general_ci DEFAULT 'Unpaid',
  `delivery_status` enum('Pending','Dispatched','Delivered','Cancelled') COLLATE utf8mb4_general_ci DEFAULT 'Delivered',
  `gate_pass_no` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `vehicle_no` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `driver_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_invoices`
--

CREATE TABLE `sales_invoices` (
  `id` int NOT NULL,
  `invoice_no` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `customer_name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `customer_id` int DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `payment_terms` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'Credit 30 Days',
  `payment_method` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'Cash',
  `cash_account_id` int DEFAULT NULL,
  `bank_account_id` int DEFAULT NULL,
  `subtotal` decimal(14,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(14,2) DEFAULT '0.00',
  `discount_percent` decimal(5,2) DEFAULT '0.00',
  `tax_amount` decimal(12,2) DEFAULT '0.00',
  `shipping_cost` decimal(10,2) DEFAULT '0.00',
  `adjustment` decimal(10,2) DEFAULT '0.00',
  `round_off` decimal(10,2) DEFAULT '0.00',
  `grand_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `previous_balance` decimal(14,2) DEFAULT '0.00',
  `net_payable` decimal(14,2) DEFAULT '0.00',
  `paid_amount` decimal(14,2) DEFAULT '0.00',
  `balance_due` decimal(14,2) DEFAULT '0.00',
  `booker_id` int DEFAULT NULL,
  `booker_name` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `route_id` int DEFAULT NULL,
  `route_name` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales_invoices`
--

INSERT INTO `sales_invoices` (`id`, `invoice_no`, `customer_name`, `customer_id`, `invoice_date`, `payment_terms`, `payment_method`, `cash_account_id`, `bank_account_id`, `subtotal`, `discount_amount`, `discount_percent`, `tax_amount`, `shipping_cost`, `adjustment`, `round_off`, `grand_total`, `previous_balance`, `net_payable`, `paid_amount`, `balance_due`, `booker_id`, `booker_name`, `route_id`, `route_name`, `notes`, `created_by`) VALUES
(22, 'INV-0001', 'Mughal Pharmacy', 4, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 5451.48, 388.85, 0.00, 0.00, 0.00, 0.00, 0.00, 5062.63, 0.00, 5062.63, 0.00, 5062.63, 1, 'Sherazi', NULL, 'Sanda Kalan', '', NULL),
(23, 'INV-0002', 'Saeeda M/S', 6, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 456.93, 9.14, 0.00, 0.00, 0.00, 0.00, 0.00, 447.79, 0.00, 447.79, 0.00, 447.79, 1, 'Sherazi', NULL, 'Sanda Kalan', '', NULL),
(24, 'INV-0003', 'Moon M/S', 7, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 2260.15, 100.38, 0.00, 0.00, 0.00, 0.00, 0.00, 2159.77, 0.00, 2159.77, 0.00, 2159.77, 1, 'Sherazi', NULL, 'Sanda Kalan', '', NULL),
(25, 'INV-0004', 'Medisafe Pharmacy', 8, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 2511.75, 221.98, 0.00, 0.00, 0.00, 0.00, 0.00, 2289.77, 0.00, 2289.77, 0.00, 2289.77, 1, 'Sherazi', NULL, 'Sanda Kalan', '', NULL),
(26, 'INV-0005', 'New Green Pharmacy', 9, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 6315.25, 230.99, 0.00, 0.00, 0.00, 0.00, 0.00, 6084.26, 0.00, 6084.26, 0.00, 6084.26, 1, 'Sherazi', NULL, 'Sanda Kalan', '', NULL),
(27, 'INV-0006', 'New Malik medical store', 10, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 2736.39, 130.19, 0.00, 0.00, 0.00, 0.00, 0.00, 2606.20, 0.00, 2606.20, 0.00, 2606.20, 2, 'Raju', NULL, 'Rana Town', '', NULL),
(28, 'INV-0007', 'Malik M/S', 11, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 5783.66, 157.01, 0.00, 0.00, 0.00, 0.00, 0.00, 5626.65, 0.00, 5626.65, 0.00, 5626.65, 1, 'Sherazi', NULL, 'Sanda Kalan', '', NULL),
(29, 'INV-0008', 'Haider healthcare pharmacy', 13, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 3922.88, 331.41, 0.00, 0.00, 0.00, 0.00, 0.00, 3591.47, 0.00, 3591.47, 0.00, 3591.47, 2, 'Raju', NULL, 'Rana Town', '', NULL),
(30, 'INV-0009', 'Qartaba M/S', 14, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 1505.40, 43.89, 0.00, 0.00, 0.00, 0.00, 0.00, 1461.51, 0.00, 1461.51, 0.00, 1461.51, 1, 'Sherazi', NULL, 'Sanda Kalan', '', NULL),
(31, 'INV-0010', 'Sheikh M/S', 15, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 2024.20, 61.23, 0.00, 0.00, 0.00, 0.00, 0.00, 1962.97, 0.00, 1962.97, 0.00, 1962.97, 1, 'Sherazi', NULL, 'Sanda Kalan', '', NULL),
(32, 'INV-0011', 'Haider Pharmacy', 1, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 15952.00, 386.20, 0.00, 0.00, 0.00, 0.00, 0.00, 15566.11, 0.00, 15566.11, 0.00, 15566.11, 1, 'Sherazi', NULL, 'Sanda kalan', '', NULL),
(33, 'INV-0012', 'Hijab medical store', 17, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 13745.83, 419.37, 0.00, 0.00, 0.00, 0.00, 0.00, 13326.46, 0.00, 13326.46, 0.00, 13326.46, 2, 'Raju', NULL, 'Rana Town', '', NULL),
(34, 'INV-0013', 'Sohail pharmacy', 18, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 2620.85, 56.36, 0.00, 0.00, 0.00, 0.00, 0.00, 2564.49, 0.00, 2564.49, 0.00, 2564.49, 2, 'Raju', NULL, 'Rana Town', '', NULL),
(35, 'INV-0014', 'New Shahid medical stire', 19, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 2255.90, 249.69, 0.00, 0.00, 0.00, 0.00, 0.00, 2006.21, 0.00, 2006.21, 0.00, 2006.21, 2, 'Raju', NULL, 'Rana Town', '', NULL),
(36, 'INV-0015', 'Unique pharmacy', 20, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 1940.30, 48.10, 0.00, 0.00, 0.00, 0.00, 0.00, 1892.20, 0.00, 1892.20, 0.00, 1892.20, 2, 'Raju', NULL, 'Rana Town', '', NULL),
(37, 'INV-0016', 'Raza medical and gernal store', 21, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 2845.51, 236.61, 0.00, 0.00, 0.00, 0.00, 0.00, 2608.90, 0.00, 2608.90, 0.00, 2608.90, 2, 'Raju', NULL, 'Rana Town', '', NULL),
(38, 'INV-0017', 'Javeed sons pharmacy', 22, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 1122.00, 51.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1071.00, 0.00, 1071.00, 0.00, 1071.00, 2, 'Raju', NULL, 'Rana Town', '', NULL),
(39, 'INV-0018', 'Javeed sons pharmacy', 22, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 2006.13, 104.62, 0.00, 0.00, 0.00, 0.00, 0.00, 1901.51, 0.00, 1901.51, 0.00, 1901.51, 2, 'Raju', NULL, 'Rana Town', '', NULL),
(40, 'INV-0019', 'Waseem pharmacy', 23, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 3733.52, 115.39, 0.00, 0.00, 0.00, 0.00, 0.00, 3618.13, 0.00, 3618.13, 0.00, 3618.13, 2, 'Raju', NULL, 'Rana Town', '', NULL),
(41, 'INV-0020', 'Hafiz Pharmacy', 24, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 2948.60, 76.96, 0.00, 0.00, 0.00, 0.00, 0.00, 2871.64, 0.00, 2871.64, 0.00, 2871.64, 1, 'Sherazi', NULL, 'Sanda Kalan', '', NULL),
(42, 'INV-0021', 'Khan M/S', 28, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 2275.00, 94.97, 0.00, 0.00, 0.00, 0.00, 0.00, 2180.27, 0.00, 2180.27, 0.00, 2180.27, 1, 'Sherazi', NULL, 'Sanda Kalan', '', NULL),
(43, 'INV-0022', 'Khadam and sons pharmacy', 27, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 6502.00, 174.87, 0.00, 0.00, 0.00, 0.00, 0.00, 6327.13, 0.00, 6327.13, 0.00, 6327.13, 2, 'Raju', NULL, 'Rana Town', '', NULL),
(44, 'INV-0023', 'Smart Care Pharmacy', 29, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 2729.43, 27.29, 0.00, 0.00, 0.00, 0.00, 0.00, 2702.14, 0.00, 2702.14, 0.00, 2702.14, 1, 'Sherazi', NULL, 'Sanda Kalan', '', NULL),
(45, 'INV-0024', 'Nazir Sons Pharmacy', 30, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 809.00, 56.63, 0.00, 0.00, 0.00, 0.00, 0.00, 752.37, 0.00, 752.37, 0.00, 752.37, 1, 'Sherazi', NULL, 'Sanda Kalan', '', NULL),
(46, 'INV-0025', 'Saad Medical Store', 31, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 2967.77, 167.75, 0.00, 0.00, 0.00, 0.00, 0.00, 2800.02, 0.00, 2800.02, 0.00, 2800.02, 1, 'Sherazi', NULL, 'Sanda Kalan', '', NULL),
(48, 'INV-0026', 'Fiazan Pharmacy', 32, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 622.00, 62.20, 0.00, 0.00, 0.00, 0.00, 0.00, 559.80, 0.00, 559.80, 0.00, 559.80, 1, 'Sherazi', NULL, 'Sanda Kalan', '', NULL),
(49, 'INV-0027', 'Medicare pharmacy', 33, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 8594.52, 769.18, 0.00, 0.00, 0.00, 0.00, 0.00, 7825.34, 0.00, 7825.34, 0.00, 7825.34, 1, 'Sherazi', NULL, 'Sanda Kalan', '', NULL),
(50, 'INV-0028', 'Mediprime Pharmacy', 34, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 2596.75, 34.30, 0.00, 0.00, 0.00, 0.00, 0.00, 2562.45, 0.00, 2562.45, 0.00, 2562.45, 1, 'Sherazi', NULL, 'Sanda Kalan', '', NULL),
(51, 'INV-0029', 'Bismillah Pharmacy', 35, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 1642.83, 82.14, 0.00, 0.00, 0.00, 0.00, 0.00, 1560.69, 0.00, 1560.69, 0.00, 1560.69, 1, 'Sherazi', NULL, 'Sanda Kalan', '', NULL),
(52, 'INV-0030', 'Riaz M/S', 36, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 952.36, 16.55, 0.00, 0.00, 0.00, 0.00, 0.00, 935.81, 0.00, 935.81, 0.00, 935.81, 1, 'Sherazi', NULL, 'Sanda Kalan', '', NULL),
(53, 'INV-0031', 'New Mughal Pharmacy', 37, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 2988.46, 85.15, 0.00, 0.00, 0.00, 0.00, 0.00, 2903.31, 0.00, 2903.31, 0.00, 2903.31, 1, 'Sherazi', NULL, 'Sanda Kalan', '', NULL),
(54, 'INV-0032', 'Al Shifa Pharmacy', 38, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 1500.25, 140.34, 0.00, 0.00, 0.00, 0.00, 0.00, 1359.92, 0.00, 1359.92, 0.00, 1359.92, 1, 'Sherazi', NULL, 'Sanda Kalan', '', NULL),
(55, 'INV-0033', 'Kamran M/S', 39, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 2553.35, 177.41, 0.00, 0.00, 0.00, 0.00, 0.00, 2375.94, 0.00, 2375.94, 0.00, 2375.94, 1, 'Sherazi', NULL, 'Sanda Kalan', '', NULL),
(56, 'INV-0034', 'Liaquit Sons Pharmacy', 5, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 16505.97, 728.69, 0.00, 0.00, 0.00, 0.00, 0.00, 15777.28, 0.00, 15777.28, 0.00, 15777.28, 1, 'Sherazi', NULL, 'Sanda Kalan', '', NULL),
(57, 'INV-0035', 'Nazir Sons Pharmacy', 30, '2026-10-05', 'Cash', 'Cash', NULL, NULL, 809.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 809.00, 0.00, 809.00, 0.00, 809.00, 1, 'Sherazi', NULL, 'Sanda Kalan', '', NULL),
(58, 'INV-0036', 'Bismillah medical store', 40, '2026-10-06', 'Cash', 'Cash', NULL, NULL, 2101.62, 40.26, 0.00, 0.00, 0.00, 0.00, 0.00, 2061.36, 0.00, 2061.36, 0.00, 2061.36, 2, 'Raju', NULL, 'Kala Khatai Morr', '', NULL),
(59, 'INV-0037', 'Imran medical store', 41, '2026-10-06', 'Cash', 'Cash', NULL, NULL, 2998.89, 132.60, 0.00, 0.00, 0.00, 0.00, 0.00, 2866.29, 0.00, 2866.29, 0.00, 2866.29, 2, 'Raju', NULL, 'Kala Khatai Morr', '', NULL),
(60, 'INV-0038', 'Tayyab pharmacy', 42, '2026-10-06', 'Cash', 'Cash', NULL, NULL, 1711.78, 31.39, 0.00, 0.00, 0.00, 0.00, 0.00, 1680.39, 0.00, 1680.39, 0.00, 1680.39, 2, 'Raju', NULL, 'Kala Khatai Morr', '', NULL),
(61, 'INV-0039', 'Haider Pharmacy', 1, '2026-10-06', 'Cash', 'Cash', NULL, NULL, 2028.78, 66.76, 0.00, 0.00, 0.00, 0.00, 0.00, 1962.02, 0.00, 1962.02, 0.00, 1962.02, 1, 'Sherazi', NULL, 'Sanda kalan', '', NULL),
(62, 'INV-0040', 'Haider Pharmacy', 1, '2026-10-06', 'Cash', 'Cash', NULL, NULL, 841.62, 16.83, 0.00, 0.00, 0.00, 0.00, 0.00, 824.79, 0.00, 824.79, 0.00, 824.79, 1, 'Sherazi', NULL, 'Sanda kalan', '', NULL),
(63, 'INV-0041', 'Lassni medical store immima colony', 43, '2026-10-06', 'Cash', 'Cash', NULL, NULL, 3735.72, 217.50, 0.00, 0.00, 0.00, 0.00, 0.00, 3518.22, 0.00, 3518.22, 0.00, 3518.22, 2, 'Raju', NULL, 'Kala Khatai Morr', '', NULL),
(64, 'INV-0042', 'Pak online pharmacy imamia colony', 44, '2026-10-06', 'Cash', 'Cash', NULL, NULL, 3056.40, 75.03, 0.00, 0.00, 0.00, 0.00, 0.00, 2981.37, 0.00, 2981.37, 0.00, 2981.37, 2, 'Raju', NULL, 'Kala Khatai Morr', '', NULL),
(65, 'INV-0043', 'Green pharmacy imamia colony', 45, '2026-10-06', 'Cash', 'Cash', NULL, NULL, 2534.38, 65.22, 0.00, 0.00, 0.00, 0.00, 0.00, 2469.16, 0.00, 2469.16, 0.00, 2469.16, 2, 'Raju', NULL, 'Kala Khatai Morr', '', NULL),
(66, 'INV-0044', 'Al syed pharmacy imamia colony', 46, '2026-10-06', 'Cash', 'Cash', NULL, NULL, 4411.61, 126.60, 0.00, 0.00, 0.00, 0.00, 0.00, 4285.01, 0.00, 4285.01, 0.00, 4285.01, 2, 'Raju', NULL, 'Kala Khatai Morr', '', NULL),
(67, 'INV-0045', 'H fareed sons pharmacy', 47, '2026-10-06', 'Cash', 'Cash', NULL, NULL, 1067.35, 36.44, 0.00, 0.00, 0.00, 0.00, 0.00, 1030.91, 0.00, 1030.91, 0.00, 1030.91, 2, 'Raju', NULL, 'Kala Khatai Morr', '', NULL),
(68, 'INV-0046', 'Gravity Plus pharmacy feroz wala', 48, '2026-10-06', 'Cash', 'Cash', NULL, NULL, 900.89, 14.70, 0.00, 0.00, 0.00, 0.00, 0.00, 886.19, 0.00, 886.19, 0.00, 886.19, 2, 'Raju', NULL, 'Kala Khatai Morr', '', NULL),
(69, 'INV-0047', 'Nouman Pharmacy', 49, '2026-10-06', 'Cash', 'Cash', NULL, NULL, 8602.89, 307.23, 0.00, 0.00, 0.00, 0.00, 0.00, 8295.66, 0.00, 8295.66, 0.00, 8295.66, 1, 'Sherazi', NULL, 'ARG', '', NULL),
(70, 'INV-0048', 'Waseem pharmacy', 23, '2026-10-06', 'Cash', 'Cash', NULL, NULL, 4106.28, 126.57, 0.00, 0.00, 0.00, 0.00, 0.00, 3979.71, 0.00, 3979.71, 0.00, 3979.71, 2, 'Raju', NULL, 'Rana Town', '', NULL),
(71, 'INV-0049', 'Lassni medical store immima colony', 43, '2026-10-06', 'Cash', 'Cash', NULL, NULL, 3379.86, 175.45, 0.00, 0.00, 0.00, 0.00, 0.00, 3204.41, 0.00, 3204.41, 0.00, 3204.41, 2, 'Raju', NULL, 'Kala Khatai Morr', '', NULL),
(72, 'INV-0050', 'Al syed pharmacy imamia colony', 46, '2026-10-06', 'Cash', 'Cash', NULL, NULL, 4577.58, 134.90, 0.00, 0.00, 0.00, 0.00, 0.00, 4442.68, 0.00, 4442.68, 0.00, 4442.68, 2, 'Raju', NULL, 'Kala Khatai Morr', '', NULL),
(73, 'INV-0051', 'Lassni medical store immima colony', 43, '2026-10-06', 'Cash', 'Cash', NULL, NULL, 3864.92, 223.96, 0.00, 0.00, 0.00, 0.00, 0.00, 3640.96, 0.00, 3640.96, 0.00, 3640.96, 2, 'Raju', NULL, 'Kala Khatai Morr', '', NULL),
(74, 'INV-0052', 'QS Medical & Cosmetics Store', 50, '2026-10-07', 'Cash', 'Cash', NULL, NULL, 5077.33, 76.61, 0.00, 0.00, 0.00, 0.00, 0.00, 5000.72, 0.00, 5000.72, 0.00, 5000.72, 1, 'Sherazi', NULL, 'Walton', '', NULL),
(75, 'INV-0053', 'Makhdoom Pharmacy', 51, '2026-10-07', 'Cash', 'Cash', NULL, NULL, 2616.10, 84.99, 0.00, 0.00, 0.00, 0.00, 0.00, 2531.11, 0.00, 2531.11, 0.00, 2531.11, 1, 'Sherazi', NULL, 'Walton', '', NULL),
(76, 'INV-0054', 'Madina Pharmacy', 52, '2026-10-07', 'Cash', 'Cash', NULL, NULL, 2923.15, 237.92, 0.00, 0.00, 0.00, 0.00, 0.00, 2685.23, 0.00, 2685.23, 0.00, 2685.23, 1, 'Sherazi', NULL, 'Walton', '', NULL),
(77, 'INV-0055', 'Kashif M/S', 54, '2026-10-07', 'Cash', 'Cash', NULL, NULL, 3071.57, 92.91, 0.00, 0.00, 0.00, 0.00, 0.00, 2978.66, 0.00, 2978.66, 0.00, 2978.66, 1, 'Sherazi', NULL, 'Walton', '', NULL),
(78, 'INV-0056', 'Wasim M/S', 55, '2026-10-07', 'Cash', 'Cash', NULL, NULL, 5496.70, 150.46, 0.00, 0.00, 0.00, 0.00, 0.00, 5346.24, 0.00, 5346.24, 0.00, 5346.24, 1, 'Sherazi', NULL, 'Walton', '', NULL),
(79, 'INV-0057', 'Fine M/S', 56, '2026-10-07', 'Cash', 'Cash', NULL, NULL, 1579.31, 60.01, 0.00, 0.00, 0.00, 0.00, 0.00, 1519.30, 0.00, 1519.30, 0.00, 1519.30, 1, 'Sherazi', NULL, 'Walton', '', NULL),
(80, 'INV-0058', 'Rehman Pharmacy', 58, '2026-10-07', 'Cash', 'Cash', NULL, NULL, 2302.84, 84.37, 0.00, 0.00, 0.00, 0.00, 0.00, 2218.47, 0.00, 2218.47, 0.00, 2218.47, 1, 'Sherazi', NULL, 'Walton', '', NULL),
(81, 'INV-0059', 'ADD Plus Pharmacy', 60, '2026-10-07', 'Cash', 'Cash', NULL, NULL, 2188.43, 60.45, 0.00, 0.00, 0.00, 0.00, 0.00, 2127.98, 0.00, 2127.98, 0.00, 2127.98, 1, 'Sherazi', NULL, 'Walton', '', NULL),
(82, 'INV-0060', 'Al Raziq Pharmacy', 61, '2026-10-07', 'Cash', 'Cash', NULL, NULL, 302.00, 1.00, 0.00, 0.00, 0.00, 0.00, 0.00, 301.72, 0.00, 301.72, 0.00, 301.72, 1, 'Sherazi', NULL, 'Walton', '', NULL),
(83, 'INV-0061', 'New Hammad', 62, '2026-10-07', 'Cash', 'Cash', NULL, NULL, 6241.72, 269.51, 0.00, 0.00, 0.00, 0.00, 0.00, 5972.21, 0.00, 5972.21, 0.00, 5972.21, 1, 'Sherazi', NULL, 'Walton', '', NULL),
(84, 'INV-0062', 'Hafiz Care Pharmacy', 63, '2026-10-07', 'Cash', 'Cash', NULL, NULL, 4689.91, 107.55, 0.00, 0.00, 0.00, 0.00, 0.00, 4582.36, 0.00, 4582.36, 0.00, 4582.36, 1, 'Sherazi', NULL, 'Walton', '', NULL),
(85, 'INV-0063', 'Health Mart Pharmacy', 64, '2026-10-07', 'Cash', 'Cash', NULL, NULL, 1549.65, 42.47, 0.00, 0.00, 0.00, 0.00, 0.00, 1507.18, 0.00, 1507.18, 0.00, 1507.18, 1, 'Sherazi', NULL, 'Abid Market', '', NULL),
(86, 'INV-0064', 'Service Medicos', 65, '2026-10-07', 'Cash', 'Cash', NULL, NULL, 3108.48, 59.46, 0.00, 0.00, 0.00, 0.00, 0.00, 3049.02, 0.00, 3049.02, 0.00, 3049.02, 1, 'Sherazi', NULL, 'Abid Market', '', NULL),
(87, 'INV-0065', 'Care Medicos', 66, '2026-10-07', 'Cash', 'Cash', NULL, NULL, 2471.20, 47.94, 0.00, 0.00, 0.00, 0.00, 0.00, 2423.26, 0.00, 2423.26, 0.00, 2423.26, 1, 'Sherazi', NULL, 'Abid Market', '', NULL),
(88, 'INV-0066', 'Muhammed Mustafa', 67, '2026-10-07', 'Cash', 'Cash', NULL, NULL, 10402.69, 184.72, 0.00, 0.00, 0.00, 0.00, 0.00, 10217.97, 0.00, 10217.97, 0.00, 10217.97, 1, 'Sherazi', NULL, 'Abid Market', '', NULL),
(89, 'INV-0067', 'Care Medicos', 66, '2026-10-07', 'Cash', 'Cash', NULL, NULL, 2471.20, 47.94, 0.00, 0.00, 0.00, 0.00, 0.00, 2423.26, 0.00, 2423.26, 0.00, 2423.26, NULL, '', NULL, 'Abid Market', '', NULL),
(90, 'INV-0068', 'Health Mart Pharmacy', 64, '2026-10-07', 'Cash', 'Cash', NULL, NULL, 1549.65, 42.47, 0.00, 0.00, 0.00, 0.00, 0.00, 1507.18, 0.00, 1507.18, 0.00, 1507.18, NULL, '', NULL, 'Abid Market', '', NULL),
(91, 'INV-0069', 'Service Medicos', 65, '2026-10-07', 'Cash', 'Cash', NULL, NULL, 3108.48, 59.46, 0.00, 0.00, 0.00, 0.00, 0.00, 3049.02, 0.00, 3049.02, 0.00, 3049.02, NULL, '', NULL, 'Abid Market', '', NULL),
(92, 'INV-0070', 'Hafiz Care Pharmacy', 63, '2026-10-07', 'Cash', 'Cash', NULL, NULL, 4689.91, 107.55, 0.00, 0.00, 0.00, 0.00, 0.00, 4582.36, 0.00, 4582.36, 0.00, 4582.36, NULL, '', NULL, 'Walton', '', NULL),
(93, 'INV-0071', 'New Hammad', 62, '2026-10-07', 'Cash', 'Cash', NULL, NULL, 6241.72, 269.51, 0.00, 0.00, 0.00, 0.00, 0.00, 5972.21, 0.00, 5972.21, 0.00, 5972.21, NULL, '', NULL, 'Walton', '', NULL),
(94, 'INV-0072', 'Al Raziq Pharmacy', 61, '2026-10-07', 'Cash', 'Cash', NULL, NULL, 302.72, 11.15, 0.00, 0.00, 0.00, 0.00, 0.00, 291.57, 0.00, 291.57, 0.00, 291.57, NULL, '', NULL, 'Walton', '', NULL),
(95, 'INV-0073', 'Al Raziq Pharmacy', 61, '2026-10-08', 'Cash', 'Cash', NULL, NULL, 302.72, 11.15, 0.00, 0.00, 0.00, 0.00, 0.00, 291.57, 0.00, 291.57, 0.00, 291.57, NULL, '', NULL, 'Walton', '', NULL),
(96, 'INV-0074', 'Al Raziq Pharmacy', 61, '2026-10-08', 'Cash', 'Cash', NULL, NULL, 302.72, 11.15, 0.00, 0.00, 0.00, 0.00, 0.00, 291.57, 0.00, 291.57, 0.00, 291.57, NULL, '', NULL, 'Walton', '', NULL),
(97, 'INV-0075', 'Al Raziq Pharmacy', 61, '2026-10-08', 'Cash', 'Cash', NULL, NULL, 302.72, 11.15, 0.00, 0.00, 0.00, 0.00, 0.00, 291.57, 0.00, 291.57, 0.00, 291.57, NULL, '', NULL, 'Walton', '', NULL),
(98, 'INV-0076', 'New Hammad', 62, '2026-10-08', 'Cash', 'Cash', NULL, NULL, 6241.72, 269.51, 0.00, 0.00, 0.00, 0.00, 0.00, 5972.21, 0.00, 5972.21, 0.00, 5972.21, NULL, '', NULL, 'Walton', '', NULL),
(99, 'INV-0077', 'Rehman Pharmacy', 58, '2026-10-08', 'Cash', 'Cash', NULL, NULL, 2302.84, 84.37, 0.00, 0.00, 0.00, 0.00, 0.00, 2218.47, 0.00, 2218.47, 0.00, 2218.47, NULL, '', NULL, 'Walton', '', NULL),
(100, 'INV-0078', 'Fine M/S', 56, '2026-10-08', 'Cash', 'Cash', NULL, NULL, 1579.31, 60.01, 0.00, 0.00, 0.00, 0.00, 0.00, 1519.30, 0.00, 1519.30, 0.00, 1519.30, NULL, '', NULL, 'Walton', '', NULL),
(101, 'INV-0079', 'Wasim M/S', 55, '2026-10-08', 'Cash', 'Cash', NULL, NULL, 5496.70, 150.46, 0.00, 0.00, 0.00, 0.00, 0.00, 5346.24, 0.00, 5346.24, 0.00, 5346.24, NULL, '', NULL, 'Walton', '', NULL),
(102, 'INV-0080', 'Kashif M/S', 54, '2026-10-08', 'Cash', 'Cash', NULL, NULL, 2996.75, 92.16, 0.00, 0.00, 0.00, 0.00, 0.00, 2904.59, 0.00, 2904.59, 0.00, 2904.59, NULL, '', NULL, 'Walton', '', NULL),
(103, 'INV-0081', 'Madina Pharmacy', 52, '2026-10-08', 'Cash', 'Cash', NULL, NULL, 2923.15, 237.92, 0.00, 0.00, 0.00, 0.00, 0.00, 2685.23, 0.00, 2685.23, 0.00, 2685.23, NULL, '', NULL, 'Walton', '', NULL),
(104, 'INV-0082', 'Muhammed Mustafa', 67, '2026-10-08', 'Cash', 'Cash', NULL, NULL, 10402.69, 184.72, 0.00, 0.00, 0.00, 0.00, 0.00, 10217.97, 0.00, 10217.97, 0.00, 10217.97, NULL, '', NULL, 'Abid Market', '', NULL),
(105, 'INV-0083', 'Makhdoom Pharmacy', 51, '2026-10-08', 'Cash', 'Cash', NULL, NULL, 2616.10, 84.99, 0.00, 0.00, 0.00, 0.00, 0.00, 2531.11, 0.00, 2531.11, 0.00, 2531.11, NULL, '', NULL, 'Walton', '', NULL),
(106, 'INV-0084', 'QS Medical & Cosmetics Store', 50, '2026-10-08', 'Cash', 'Cash', NULL, NULL, 5077.33, 76.61, 0.00, 0.00, 0.00, 0.00, 0.00, 5000.72, 0.00, 5000.72, 0.00, 5000.72, NULL, '', NULL, 'Walton', '', NULL),
(107, 'INV-0085', 'ADD Plus Pharmacy', 60, '2026-10-08', 'Cash', 'Cash', NULL, NULL, 2188.43, 60.45, 0.00, 0.00, 0.00, 0.00, 0.00, 2127.98, 0.00, 2127.98, 0.00, 2127.98, NULL, '', NULL, 'Walton', '', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int NOT NULL,
  `invoice_id` int NOT NULL,
  `sale_id` int DEFAULT NULL,
  `product_id` int NOT NULL,
  `item_name` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `batch_id` int DEFAULT NULL,
  `batch_no` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `bonus_quantity` int DEFAULT '0',
  `unit_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `trade_price` decimal(12,2) DEFAULT '0.00',
  `retail_price` decimal(12,2) DEFAULT '0.00',
  `discount_percent` decimal(5,2) DEFAULT '0.00',
  `extra_discount_percent` decimal(5,2) DEFAULT '0.00',
  `total_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `sale_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_price` decimal(14,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `invoice_id`, `sale_id`, `product_id`, `item_name`, `batch_id`, `batch_no`, `expiry_date`, `quantity`, `bonus_quantity`, `unit_price`, `trade_price`, `retail_price`, `discount_percent`, `extra_discount_percent`, `total_amount`, `sale_price`, `total_price`) VALUES
(23, 22, NULL, 22, 'Alcuflex 550mg Tab 30s', NULL, 'DEFAULT', '2028-09-30', 1, 0, 580.89, 580.89, 0.00, 5.00, 0.00, 551.85, 580.89, 551.85),
(24, 22, NULL, 40, 'Polyfax Skin Oint 20g', NULL, 'BAT-260929', '2028-09-29', 4, 0, 190.03, 190.03, 0.00, 5.00, 0.00, 722.11, 190.03, 722.11),
(25, 22, NULL, 63, 'Ossobon D Tab', NULL, 'DEFAULT', '2028-09-29', 3, 0, 425.00, 425.00, 0.00, 3.00, 0.00, 1236.75, 425.00, 1236.75),
(26, 22, NULL, 128, 'Cebosh 100/5ml Syp', NULL, 'DEFAULT', '2028-10-02', 1, 0, 267.62, 267.62, 0.00, 10.00, 0.00, 240.86, 267.62, 240.86),
(27, 22, NULL, 131, 'Atorva 10mg Tab 20s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 636.65, 636.65, 0.00, 10.00, 0.00, 572.99, 636.65, 572.99),
(28, 22, NULL, 132, 'Atorva 20mg Tab 20s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 1124.55, 1124.55, 0.00, 10.00, 0.00, 1012.10, 1124.55, 1012.10),
(29, 22, NULL, 145, 'Osteocare D3 Tab', NULL, 'DEFAULT', '2028-10-02', 1, 0, 806.65, 806.65, 0.00, 10.00, 0.00, 725.99, 806.65, 725.99),
(30, 23, NULL, 51, 'Levopraid 25mg Tab', NULL, 'BAT-260929', '2028-09-29', 1, 0, 456.93, 456.93, 0.00, 2.00, 0.00, 447.79, 456.93, 447.79),
(31, 24, NULL, 94, 'Sita Met 50/500mg Tab 14s', NULL, 'DEFAULT', '2028-10-02', 2, 0, 599.25, 599.25, 0.00, 2.00, 0.00, 1174.53, 599.25, 1174.53),
(32, 24, NULL, 63, 'Ossobon D Tab', NULL, 'DEFAULT', '2028-09-29', 1, 0, 425.00, 425.00, 0.00, 3.00, 0.00, 412.25, 425.00, 412.25),
(33, 24, NULL, 131, 'Atorva 10mg Tab 20s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 636.65, 636.65, 0.00, 10.00, 0.00, 572.99, 636.65, 572.99),
(34, 25, NULL, 46, 'Arinac Fort Tab', NULL, 'BAT-260929', '2028-09-29', 1, 0, 1275.00, 1275.00, 0.00, 3.00, 0.00, 1236.75, 1275.00, 1236.75),
(35, 25, NULL, 44, 'Sunny D Inj', NULL, 'BAT-260929', '2028-09-29', 2, 0, 208.25, 208.25, 0.00, 25.00, 0.00, 312.38, 208.25, 312.38),
(36, 25, NULL, 139, 'Diampa LXR 10/5/1000 mg Tab 14s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 310.25, 310.25, 0.00, 1.00, 0.00, 307.15, 310.25, 307.15),
(37, 25, NULL, 90, 'Elexine 15mg Tab', NULL, 'DEFAULT', '2028-10-02', 1, 0, 510.00, 510.00, 0.00, 15.00, 0.00, 433.50, 510.00, 433.50),
(38, 26, NULL, 57, 'Methix Tab 20s', NULL, 'BAT-260929', '2028-09-29', 1, 0, 922.03, 922.03, 0.00, 5.00, 0.00, 875.93, 922.03, 875.93),
(39, 26, NULL, 15, 'Azomax 500mg Tab', NULL, 'BAT-260929', '2028-09-29', 1, 0, 466.40, 466.40, 0.00, 1.00, 0.00, 461.74, 466.40, 461.74),
(40, 26, NULL, 2, 'Velosef 500mg Cap', NULL, 'BAT-260929', '2028-09-29', 1, 0, 603.17, 603.17, 0.00, 5.00, 0.00, 573.01, 603.17, 573.01),
(41, 26, NULL, 25, 'Klaricid 250mg Tab', NULL, 'BAT-260929', '2028-09-29', 1, 0, 464.64, 464.64, 0.00, 2.00, 0.00, 455.35, 464.64, 455.35),
(42, 26, NULL, 27, 'Somogel Cream', NULL, 'BAT-260929', '2028-09-29', 1, 0, 165.75, 165.75, 0.00, 3.00, 0.00, 160.78, 165.75, 160.78),
(43, 26, NULL, 1, 'Clobevate Cream', NULL, 'BAT-260929', '2028-09-29', 4, 0, 185.05, 185.05, 0.00, 5.00, 0.00, 703.19, 185.05, 703.19),
(44, 26, NULL, 46, 'Arinac Fort Tab', NULL, 'DEFAULT', '2028-09-30', 1, 0, 1275.00, 1275.00, 0.00, 3.00, 0.00, 1236.75, 1275.00, 1236.75),
(45, 26, NULL, 35, 'Canderel 18mg Tab 100s', NULL, 'BAT-260929', '2028-09-29', 1, 0, 236.02, 236.02, 0.00, 10.00, 0.00, 212.42, 236.02, 212.42),
(46, 26, NULL, 43, 'Zyrtec Syp 60ml', NULL, 'BAT-260929', '2028-09-29', 1, 0, 134.89, 134.89, 0.00, 8.00, 0.00, 124.10, 134.89, 124.10),
(47, 26, NULL, 9, 'Kestine 10mg Tab 14s', NULL, 'BAT-260929', '2028-09-29', 1, 0, 252.71, 252.71, 0.00, 2.00, 0.00, 247.66, 252.71, 247.66),
(48, 26, NULL, 10, 'Ulsanic Syp 120ml', NULL, 'BAT-260929', '2028-09-29', 3, 0, 351.48, 351.48, 0.00, 2.00, 0.00, 1033.35, 351.48, 1033.35),
(49, 27, NULL, 38, 'Betnovate N Cream', NULL, 'BAT-260929', '2028-09-29', 3, 0, 153.53, 153.53, 0.00, 5.00, 0.00, 437.56, 153.53, 437.56),
(50, 27, NULL, 39, 'Calpol 6+ Syp 90ml', NULL, 'BAT-260929', '2028-09-29', 4, 0, 101.48, 101.48, 0.00, 5.00, 0.00, 385.62, 101.48, 385.62),
(51, 27, NULL, 40, 'Polyfax Skin Oint 20g', NULL, 'DEFAULT', '2028-09-30', 2, 0, 190.03, 190.03, 0.00, 5.00, 0.00, 361.06, 190.03, 361.06),
(52, 27, NULL, 1, 'Clobevate Cream', NULL, 'DEFAULT', '2028-09-29', 3, 0, 185.05, 185.05, 0.00, 5.00, 0.00, 527.39, 185.05, 527.39),
(53, 27, NULL, 2, 'Velosef 500mg Cap', NULL, 'DEFAULT', '2028-09-29', 1, 0, 603.17, 603.17, 0.00, 5.00, 0.00, 573.01, 603.17, 573.01),
(54, 27, NULL, 27, 'Somogel Cream', NULL, 'DEFAULT', '2028-09-30', 2, 0, 165.75, 165.75, 0.00, 3.00, 0.00, 321.56, 165.75, 321.56),
(55, 28, NULL, 81, 'Magnett 400mg Cap', NULL, 'DEFAULT', '2028-10-02', 1, 0, 450.50, 450.50, 0.00, 2.00, 0.00, 441.49, 450.50, 441.49),
(56, 28, NULL, 89, 'Nirvanol 10mg Tab', NULL, 'DEFAULT', '2028-10-02', 2, 0, 233.75, 233.75, 0.00, 10.00, 0.00, 420.75, 233.75, 420.75),
(57, 28, NULL, 140, 'Diampa LXR 25/5/1000 mg Tab 14s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 352.75, 352.75, 0.00, 1.00, 0.00, 349.22, 352.75, 349.22),
(58, 28, NULL, 143, 'Treviamet 50/500 tab 14s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 963.90, 963.90, 0.00, 1.00, 0.00, 954.26, 963.90, 954.26),
(59, 28, NULL, 45, 'Entox P Tab', NULL, 'BAT-260929', '2028-09-29', 1, 0, 442.00, 442.00, 0.00, 2.00, 0.00, 433.16, 442.00, 433.16),
(60, 28, NULL, 94, 'Sita Met 50/500mg Tab 14s', NULL, 'DEFAULT', '2028-10-02', 2, 0, 599.25, 599.25, 0.00, 2.00, 0.00, 1174.53, 599.25, 1174.53),
(61, 28, NULL, 9, 'Kestine 10mg Tab 14s', NULL, 'DEFAULT', '2028-09-29', 2, 0, 252.71, 252.71, 0.00, 2.00, 0.00, 495.31, 252.71, 495.31),
(62, 28, NULL, 40, 'Polyfax Skin Oint 20g', NULL, 'DEFAULT', '2028-09-30', 3, 0, 190.03, 190.03, 0.00, 5.00, 0.00, 541.59, 190.03, 541.59),
(63, 28, NULL, 11, 'Empaa 10mg Tab 28s', NULL, 'BAT-260929', '2028-09-29', 1, 0, 833.00, 833.00, 0.00, 2.00, 0.00, 816.34, 833.00, 816.34),
(64, 29, NULL, 130, 'Apranax 550mg Tab', NULL, 'DEFAULT', '2028-10-02', 1, 0, 448.93, 448.93, 0.00, 5.00, 0.00, 426.48, 448.93, 426.48),
(65, 29, NULL, 95, 'Sita Met 50/1000mg Tab 14s', NULL, 'DEFAULT', '2028-10-02', 2, 0, 617.10, 617.10, 0.00, 2.00, 0.00, 1209.52, 617.10, 1209.52),
(66, 29, NULL, 94, 'Sita Met 50/500mg Tab 14s', NULL, 'DEFAULT', '2028-10-02', 2, 0, 599.25, 599.25, 0.00, 2.00, 0.00, 1174.53, 599.25, 1174.53),
(67, 29, NULL, 44, 'Sunny D Inj', NULL, 'DEFAULT', '2028-09-30', 5, 0, 208.25, 208.25, 0.00, 25.00, 0.00, 780.94, 208.25, 780.94),
(68, 30, NULL, 55, 'Cellgee Tab 30s', NULL, 'BAT-260929', '2028-09-29', 1, 0, 720.80, 720.80, 0.00, 5.00, 0.00, 684.76, 720.80, 684.76),
(69, 30, NULL, 75, 'ECP Tab', NULL, 'DEFAULT', '2028-10-02', 10, 0, 24.94, 24.94, 0.00, 1.00, 0.00, 246.91, 24.94, 246.91),
(70, 30, NULL, 84, 'Cefiget 100/5ml Syp 30ml', NULL, 'DEFAULT', '2028-10-02', 2, 0, 267.60, 267.60, 0.00, 1.00, 0.00, 529.85, 267.60, 529.85),
(71, 31, NULL, 120, 'Myolax 4mg Tab', NULL, 'DEFAULT', '2028-10-02', 1, 0, 752.25, 752.25, 0.00, 4.00, 0.00, 722.16, 752.25, 722.16),
(72, 31, NULL, 122, 'Xavor 50mg Tab', NULL, 'DEFAULT', '2028-10-02', 1, 0, 262.35, 262.35, 0.00, 2.00, 0.00, 257.10, 262.35, 257.10),
(73, 31, NULL, 40, 'Polyfax Skin Oint 20g', NULL, 'DEFAULT', '2028-09-30', 1, 0, 190.03, 190.03, 0.00, 5.00, 0.00, 180.53, 190.03, 180.53),
(74, 31, NULL, 13, 'Rhinosone P Spray 15ml', NULL, 'BAT-260929', '2028-09-29', 1, 0, 199.75, 199.75, 0.00, 2.00, 0.00, 195.76, 199.75, 195.76),
(75, 31, NULL, 49, 'Combivair 400mg Cap', NULL, 'BAT-260929', '2028-09-29', 1, 0, 619.82, 619.82, 0.00, 2.00, 0.00, 607.42, 619.82, 607.42),
(87, 33, NULL, 14, 'Azomax 250mg Cap 12s', NULL, 'BAT-260929', '2028-09-29', 1, 0, 675.19, 675.19, 0.00, 1.00, 0.00, 668.44, 675.19, 668.44),
(88, 33, NULL, 15, 'Azomax 500mg Tab', NULL, 'DEFAULT', '2028-09-30', 1, 0, 466.40, 466.40, 0.00, 1.00, 0.00, 461.74, 466.40, 461.74),
(89, 33, NULL, 83, 'Polybion Z Cap', NULL, 'DEFAULT', '2028-10-02', 1, 0, 400.18, 400.18, 0.00, 2.00, 0.00, 392.18, 400.18, 392.18),
(90, 33, NULL, 96, 'Nuberol Tab 100s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 722.33, 722.33, 0.00, 1.00, 0.00, 715.11, 722.33, 715.11),
(91, 33, NULL, 132, 'Atorva 20mg Tab 20s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 1124.55, 1124.55, 0.00, 10.00, 0.00, 1012.10, 1124.55, 1012.10),
(92, 33, NULL, 97, 'Magnett 100/5ml Syp', NULL, 'DEFAULT', '2028-10-02', 5, 0, 242.25, 242.25, 0.00, 2.00, 0.00, 1187.03, 242.25, 1187.03),
(93, 33, NULL, 80, 'Adenuric 40mg Tab 20s', NULL, 'DEFAULT', '2028-10-02', 5, 0, 476.00, 476.00, 0.00, 2.00, 0.00, 2332.40, 476.00, 2332.40),
(94, 33, NULL, 5, 'Elezo 150 Cap', NULL, 'BAT-260929', '2028-09-29', 2, 0, 573.75, 573.75, 0.00, 1.00, 0.00, 1136.03, 573.75, 1136.03),
(95, 33, NULL, 8, 'Novoteph 40mg Cap', NULL, 'BAT-260929', '2028-09-29', 2, 0, 433.50, 433.50, 0.00, 1.00, 0.00, 858.33, 433.50, 858.33),
(96, 33, NULL, 99, 'Amodip 10mg Tab', NULL, 'DEFAULT', '2028-10-02', 1, 0, 654.21, 654.21, 0.00, 2.00, 0.00, 641.13, 654.21, 641.13),
(97, 33, NULL, 112, 'Tobra D E/D', NULL, 'DEFAULT', '2028-10-02', 2, 0, 242.25, 242.25, 0.00, 5.00, 0.00, 460.28, 242.25, 460.28),
(98, 33, NULL, 111, 'Tobra E/D', NULL, 'DEFAULT', '2028-10-02', 2, 0, 191.25, 191.25, 0.00, 5.00, 0.00, 363.38, 191.25, 363.38),
(99, 33, NULL, 39, 'Calpol 6+ Syp 90ml', NULL, 'DEFAULT', '2028-09-30', 5, 0, 101.48, 101.48, 0.00, 5.00, 0.00, 482.03, 101.48, 482.03),
(100, 33, NULL, 43, 'Zyrtec Syp 60ml', NULL, 'DEFAULT', '2028-09-30', 3, 0, 134.89, 134.89, 0.00, 8.00, 0.00, 372.30, 134.89, 372.30),
(101, 33, NULL, 1, 'Clobevate Cream', NULL, 'DEFAULT', '2028-09-29', 5, 0, 185.05, 185.05, 0.00, 5.00, 0.00, 878.99, 185.05, 878.99),
(102, 33, NULL, 16, 'Revital Multi Tab 45s', NULL, 'BAT-260929', '2028-09-29', 2, 0, 696.45, 696.45, 0.00, 2.00, 0.00, 1365.04, 696.45, 1365.04),
(103, 34, NULL, 67, 'Zestril 5mg Tab', NULL, 'DEFAULT', '2028-09-30', 2, 0, 579.70, 579.70, 0.00, 3.00, 0.00, 1124.62, 579.70, 1124.62),
(104, 34, NULL, 16, 'Revital Multi Tab 45s', NULL, 'DEFAULT', '2028-09-30', 1, 0, 696.45, 696.45, 0.00, 2.00, 0.00, 682.52, 696.45, 682.52),
(105, 34, NULL, 115, 'Ezium 20mg Cap 14s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 331.50, 331.50, 0.00, 1.00, 0.00, 328.19, 331.50, 328.19),
(106, 34, NULL, 8, 'Novoteph 40mg Cap', NULL, 'DEFAULT', '2028-09-29', 1, 0, 433.50, 433.50, 0.00, 1.00, 0.00, 429.17, 433.50, 429.17),
(107, 35, NULL, 44, 'Sunny D Inj', NULL, 'DEFAULT', '2028-09-30', 3, 0, 208.25, 208.25, 0.00, 25.00, 0.00, 468.56, 208.25, 468.56),
(108, 35, NULL, 27, 'Somogel Cream', NULL, 'DEFAULT', '2028-09-30', 6, 0, 165.75, 165.75, 0.00, 3.00, 0.00, 964.67, 165.75, 964.67),
(109, 35, NULL, 131, 'Atorva 10mg Tab 20s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 636.65, 636.65, 0.00, 10.00, 0.00, 572.99, 636.65, 572.99),
(110, 36, NULL, 16, 'Revital Multi Tab 45s', NULL, 'DEFAULT', '2028-09-30', 2, 0, 696.45, 696.45, 0.00, 2.00, 0.00, 1365.04, 696.45, 1365.04),
(111, 36, NULL, 125, 'Deximox E/D', NULL, 'DEFAULT', '2028-10-02', 1, 0, 356.15, 356.15, 0.00, 3.00, 0.00, 345.47, 356.15, 345.47),
(112, 36, NULL, 111, 'Tobra E/D', NULL, 'DEFAULT', '2028-10-02', 1, 0, 191.25, 191.25, 0.00, 5.00, 0.00, 181.69, 191.25, 181.69),
(137, 37, NULL, 131, 'Atorva 10mg Tab 20s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 636.65, 636.65, 0.00, 10.00, 0.00, 572.99, 636.65, 572.99),
(138, 37, NULL, 132, 'Atorva 20mg Tab 20s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 1124.55, 1124.55, 0.00, 10.00, 0.00, 1012.10, 1124.55, 1012.10),
(139, 37, NULL, 34, 'Xylor Tab 20s', NULL, 'BAT-260929', '2028-09-29', 2, 0, 242.53, 242.53, 0.00, 10.00, 0.00, 436.55, 242.53, 436.55),
(140, 37, NULL, 13, 'Rhinosone P Spray 15ml', NULL, 'DEFAULT', '2028-09-30', 3, 0, 199.75, 199.75, 0.00, 2.00, 0.00, 587.27, 199.75, 587.27),
(141, 38, NULL, 112, 'Tobra D E/D', NULL, 'DEFAULT', '2028-10-02', 2, 0, 242.25, 242.25, 0.00, 5.00, 0.00, 460.28, 242.25, 460.28),
(142, 38, NULL, 111, 'Tobra E/D', NULL, 'DEFAULT', '2028-10-02', 2, 0, 191.25, 191.25, 0.00, 5.00, 0.00, 363.38, 191.25, 363.38),
(143, 38, NULL, 126, 'Eyebradex E/D', NULL, 'DEFAULT', '2028-10-02', 1, 0, 255.00, 255.00, 0.00, 3.00, 0.00, 247.35, 255.00, 247.35),
(144, 39, NULL, 102, 'Lophos Tab 100s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 654.34, 654.34, 0.00, 4.00, 0.00, 628.17, 654.34, 628.17),
(145, 39, NULL, 86, 'Lice -O-Nil Cream', NULL, 'DEFAULT', '2028-10-02', 3, 0, 212.50, 212.50, 0.00, 3.00, 0.00, 618.38, 212.50, 618.38),
(146, 39, NULL, 35, 'Canderel 18mg Tab 100s', NULL, 'DEFAULT', '2028-09-29', 2, 0, 236.02, 236.02, 0.00, 10.00, 0.00, 424.84, 236.02, 424.84),
(147, 39, NULL, 112, 'Tobra D E/D', NULL, 'DEFAULT', '2028-10-02', 1, 0, 242.25, 242.25, 0.00, 5.00, 0.00, 230.14, 242.25, 230.14),
(161, 40, NULL, 65, 'Tenormin 50mg Tab', NULL, 'DEFAULT', '2028-09-30', 2, 0, 354.02, 354.02, 0.00, 3.00, 0.00, 686.80, 354.02, 686.80),
(162, 40, NULL, 18, 'Famila 28F 3Cycle Tab', NULL, 'BAT-260929', '2028-09-29', 2, 0, 127.39, 127.39, 0.00, 2.00, 0.00, 249.68, 127.39, 249.68),
(163, 40, NULL, 17, 'ST.MOM 200mg Tab 10s', NULL, 'BAT-260929', '2028-09-29', 5, 0, 169.60, 169.60, 0.00, 2.00, 0.00, 831.04, 169.60, 831.04),
(164, 40, NULL, 74, 'Atenolol Tab', NULL, 'DEFAULT', '2028-10-02', 1, 0, 148.75, 148.75, 0.00, 1.00, 0.00, 147.26, 148.75, 147.26),
(165, 40, NULL, 75, 'ECP Tab', NULL, 'DEFAULT', '2028-10-02', 1, 0, 24.94, 24.94, 0.00, 1.00, 0.00, 24.69, 24.94, 24.69),
(166, 40, NULL, 96, 'Nuberol Tab 100s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 722.33, 722.33, 0.00, 1.00, 0.00, 715.11, 722.33, 715.11),
(167, 40, NULL, 52, 'Levopraid  50mg Tab', NULL, 'BAT-260929', '2028-09-29', 1, 0, 790.66, 790.66, 0.00, 5.00, 0.00, 751.13, 790.66, 751.13),
(168, 40, NULL, 35, 'Canderel 18mg Tab 100s', NULL, 'DEFAULT', '2028-09-29', 1, 0, 236.02, 236.02, 0.00, 10.00, 0.00, 212.42, 236.02, 212.42),
(169, 41, NULL, 17, 'ST.MOM 200mg Tab 10s', NULL, 'DEFAULT', '2028-09-30', 1, 0, 169.60, 169.60, 0.00, 2.00, 0.00, 166.21, 169.60, 166.21),
(170, 41, NULL, 75, 'ECP Tab', NULL, 'DEFAULT', '2028-10-02', 4, 0, 24.94, 24.94, 0.00, 1.00, 0.00, 98.76, 24.94, 98.76),
(171, 41, NULL, 80, 'Adenuric 40mg Tab 20s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 476.00, 476.00, 0.00, 2.00, 0.00, 466.48, 476.00, 466.48),
(172, 41, NULL, 81, 'Magnett 400mg Cap', NULL, 'DEFAULT', '2028-10-02', 2, 0, 450.50, 450.50, 0.00, 2.00, 0.00, 882.98, 450.50, 882.98),
(173, 41, NULL, 83, 'Polybion Z Cap', NULL, 'DEFAULT', '2028-10-02', 1, 0, 400.18, 400.18, 0.00, 2.00, 0.00, 392.18, 400.18, 392.18),
(174, 41, NULL, 136, 'Derma Smooth Lotion 120ml', NULL, 'DEFAULT', '2028-10-02', 1, 0, 403.75, 403.75, 0.00, 3.00, 0.00, 391.64, 403.75, 391.64),
(175, 41, NULL, 111, 'Tobra E/D', NULL, 'DEFAULT', '2028-10-02', 1, 0, 191.25, 191.25, 0.00, 5.00, 0.00, 181.69, 191.25, 181.69),
(176, 41, NULL, 38, 'Betnovate N Cream', NULL, 'DEFAULT', '2028-09-30', 2, 0, 153.53, 153.53, 0.00, 5.00, 0.00, 291.71, 153.53, 291.71),
(189, 42, NULL, 43, 'Zyrtec Syp 60ml', NULL, 'DEFAULT', '2028-09-30', 2, 0, 134.89, 134.89, 0.00, 8.00, 0.00, 248.20, 134.89, 248.20),
(190, 42, NULL, 42, 'Augmentin  156.25/5ml Syp', NULL, 'DEFAULT', '2028-09-30', 1, 0, 322.69, 322.69, 0.00, 5.00, 0.00, 306.56, 322.69, 306.56),
(191, 42, NULL, 41, 'Augmentin DS 312.5/5ml Syp', NULL, 'DEFAULT', '2028-09-30', 1, 0, 531.57, 531.57, 0.00, 5.00, 0.00, 504.99, 531.57, 504.99),
(192, 42, NULL, 13, 'Rhinosone P Spray 15ml', NULL, 'DEFAULT', '2028-09-30', 1, 0, 199.75, 199.75, 0.00, 2.00, 0.00, 195.76, 199.75, 195.76),
(193, 42, NULL, 16, 'Revital Multi Tab 45s', NULL, 'DEFAULT', '2028-09-30', 1, 0, 696.45, 696.45, 0.00, 2.00, 0.00, 682.52, 696.45, 682.52),
(194, 42, NULL, 32, 'Clobederm NN Oint 15g', NULL, 'DEFAULT', '2028-09-30', 2, 0, 127.50, 127.50, 0.00, 5.00, 0.00, 242.25, 127.50, 242.25),
(195, 43, NULL, 86, 'Lice -O-Nil Cream', NULL, 'DEFAULT', '2028-10-02', 5, 0, 212.50, 212.50, 0.00, 3.00, 0.00, 1030.63, 212.50, 1030.63),
(196, 43, NULL, 87, 'Venticort 400mg Cap', NULL, 'DEFAULT', '2028-10-02', 1, 0, 484.50, 484.50, 0.00, 3.00, 0.00, 469.97, 484.50, 469.97),
(197, 43, NULL, 73, 'Beceptor 10mg Tab', NULL, 'DEFAULT', '2028-10-02', 4, 0, 191.25, 191.25, 0.00, 5.00, 0.00, 726.75, 191.25, 726.75),
(198, 43, NULL, 49, 'Combivair 400mg Cap', NULL, 'DEFAULT', '2028-09-30', 2, 0, 619.82, 619.82, 0.00, 2.00, 0.00, 1214.85, 619.82, 1214.85),
(199, 43, NULL, 143, 'Treviamet 50/500 tab 14s', NULL, 'DEFAULT', '2028-10-02', 2, 0, 963.90, 963.90, 0.00, 1.00, 0.00, 1908.52, 963.90, 1908.52),
(200, 43, NULL, 130, 'Apranax 550mg Tab', NULL, 'DEFAULT', '2028-10-02', 2, 0, 448.93, 448.93, 0.00, 5.00, 0.00, 852.97, 448.93, 852.97),
(201, 43, NULL, 75, 'ECP Tab', NULL, 'DEFAULT', '2028-10-02', 5, 0, 24.94, 24.94, 0.00, 1.00, 0.00, 123.45, 24.94, 123.45),
(202, 44, NULL, 14, 'Azomax 250mg Cap 12s', NULL, 'DEFAULT', '2028-09-30', 2, 0, 675.19, 675.19, 0.00, 1.00, 0.00, 1336.88, 675.19, 1336.88),
(203, 44, NULL, 15, 'Azomax 500mg Tab', NULL, 'DEFAULT', '2028-09-30', 2, 0, 466.40, 466.40, 0.00, 1.00, 0.00, 923.47, 466.40, 923.47),
(204, 44, NULL, 58, 'Intig D Tab', NULL, 'DEFAULT', '2028-10-02', 1, 0, 446.25, 446.25, 0.00, 1.00, 0.00, 441.79, 446.25, 441.79),
(205, 45, NULL, 48, 'Flagyl 400mg Tab', NULL, 'BAT-260929', '2028-09-29', 1, 0, 809.00, 809.00, 0.00, 7.00, 0.00, 752.37, 809.00, 752.37),
(206, 46, NULL, 65, 'Tenormin 50mg Tab', NULL, 'DEFAULT', '2028-09-30', 1, 0, 354.02, 354.02, 0.00, 3.00, 0.00, 343.40, 354.02, 343.40),
(207, 46, NULL, 132, 'Atorva 20mg Tab 20s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 1124.55, 1124.55, 0.00, 10.00, 0.00, 1012.10, 1124.55, 1012.10),
(208, 46, NULL, 119, 'Spasfon Tab', NULL, 'DEFAULT', '2028-10-02', 1, 0, 1489.20, 1489.20, 0.00, 3.00, 0.00, 1444.52, 1489.20, 1444.52),
(209, 48, NULL, 60, 'Fusiderm Cream', NULL, 'DEFAULT', '2028-09-29', 1, 0, 324.50, 324.50, 0.00, 10.00, 0.00, 292.05, 324.50, 292.05),
(210, 48, NULL, 88, 'Nirvanol 5mg Tab', NULL, 'DEFAULT', '2028-10-02', 2, 0, 148.75, 148.75, 0.00, 10.00, 0.00, 267.75, 148.75, 267.75),
(211, 49, NULL, 133, 'Atorva 40mg Tab 20s', NULL, 'DEFAULT', '2028-10-02', 2, 0, 1295.91, 1295.91, 0.00, 10.00, 0.00, 2332.64, 1295.91, 2332.64),
(212, 49, NULL, 132, 'Atorva 20mg Tab 20s', NULL, 'DEFAULT', '2028-10-02', 4, 0, 1124.55, 1124.55, 0.00, 10.00, 0.00, 4048.38, 1124.55, 4048.38),
(213, 49, NULL, 120, 'Myolax 4mg Tab', NULL, 'DEFAULT', '2028-10-02', 2, 0, 752.25, 752.25, 0.00, 4.00, 0.00, 1444.32, 752.25, 1444.32),
(214, 50, NULL, 140, 'Diampa LXR 25/5/1000 mg Tab 14s', NULL, 'DEFAULT', '2028-10-02', 5, 0, 352.75, 352.75, 0.00, 1.00, 0.00, 1746.11, 352.75, 1746.11),
(215, 50, NULL, 11, 'Empaa 10mg Tab 28s', NULL, 'DEFAULT', '2028-09-29', 1, 0, 833.00, 833.00, 0.00, 2.00, 0.00, 816.34, 833.00, 816.34),
(216, 51, NULL, 57, 'Methix Tab 20s', NULL, 'DEFAULT', '2028-09-29', 1, 0, 922.03, 922.03, 0.00, 5.00, 0.00, 875.93, 922.03, 875.93),
(217, 51, NULL, 55, 'Cellgee Tab 30s', NULL, 'DEFAULT', '2028-09-29', 1, 0, 720.80, 720.80, 0.00, 5.00, 0.00, 684.76, 720.80, 684.76),
(218, 52, NULL, 10, 'Ulsanic Syp 120ml', NULL, 'DEFAULT', '2028-09-29', 2, 0, 351.48, 351.48, 0.00, 2.00, 0.00, 688.90, 351.48, 688.90),
(219, 52, NULL, 75, 'ECP Tab', NULL, 'DEFAULT', '2028-10-02', 10, 0, 24.94, 24.94, 0.00, 1.00, 0.00, 246.91, 24.94, 246.91),
(220, 53, NULL, 75, 'ECP Tab', NULL, 'DEFAULT', '2028-10-02', 10, 0, 24.94, 24.94, 0.00, 1.00, 0.00, 246.91, 24.94, 246.91),
(221, 53, NULL, 129, 'Musidin 2mg Tab', NULL, 'DEFAULT', '2028-10-02', 4, 0, 216.99, 216.99, 0.00, 5.00, 0.00, 824.56, 216.99, 824.56),
(222, 53, NULL, 143, 'Treviamet 50/500 tab 14s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 963.90, 963.90, 0.00, 1.00, 0.00, 954.26, 963.90, 954.26),
(223, 53, NULL, 111, 'Tobra E/D', NULL, 'DEFAULT', '2028-10-02', 2, 0, 191.25, 191.25, 0.00, 5.00, 0.00, 363.38, 191.25, 363.38),
(224, 53, NULL, 122, 'Xavor 50mg Tab', NULL, 'DEFAULT', '2028-10-02', 2, 0, 262.35, 262.35, 0.00, 2.00, 0.00, 514.21, 262.35, 514.21),
(225, 54, NULL, 44, 'Sunny D Inj', NULL, 'DEFAULT', '2028-09-30', 2, 0, 208.25, 208.25, 0.00, 25.00, 0.00, 312.38, 208.25, 312.38),
(226, 54, NULL, 13, 'Rhinosone P Spray 15ml', NULL, 'DEFAULT', '2028-09-30', 3, 0, 199.75, 199.75, 0.00, 2.00, 0.00, 587.27, 199.75, 587.27),
(227, 54, NULL, 112, 'Tobra D E/D', NULL, 'DEFAULT', '2028-10-02', 2, 0, 242.25, 242.25, 0.00, 5.00, 0.00, 460.28, 242.25, 460.28),
(228, 55, NULL, 38, 'Betnovate N Cream', NULL, 'DEFAULT', '2028-09-30', 3, 0, 153.53, 153.53, 0.00, 5.00, 0.00, 437.56, 153.53, 437.56),
(229, 55, NULL, 39, 'Calpol 6+ Syp 90ml', NULL, 'DEFAULT', '2028-09-30', 3, 0, 101.48, 101.48, 0.00, 5.00, 0.00, 289.22, 101.48, 289.22),
(230, 55, NULL, 40, 'Polyfax Skin Oint 20g', NULL, 'DEFAULT', '2028-09-30', 3, 0, 190.03, 190.03, 0.00, 5.00, 0.00, 541.59, 190.03, 541.59),
(231, 55, NULL, 43, 'Zyrtec Syp 60ml', NULL, 'DEFAULT', '2028-09-30', 2, 0, 134.89, 134.89, 0.00, 8.00, 0.00, 248.20, 134.89, 248.20),
(232, 55, NULL, 1, 'Clobevate Cream', NULL, 'DEFAULT', '2028-09-29', 4, 0, 185.05, 185.05, 0.00, 5.00, 0.00, 703.19, 185.05, 703.19),
(233, 55, NULL, 44, 'Sunny D Inj', NULL, 'DEFAULT', '2028-09-30', 1, 0, 208.25, 208.25, 0.00, 25.00, 0.00, 156.19, 208.25, 156.19),
(234, 56, NULL, 139, 'Diampa LXR 10/5/1000 mg Tab 14s', NULL, 'DEFAULT', '2028-10-02', 2, 0, 310.25, 310.25, 0.00, 1.00, 0.00, 614.30, 310.25, 614.30),
(235, 56, NULL, 93, 'Amodip V 5/80 Tab', NULL, 'DEFAULT', '2028-10-02', 1, 0, 361.25, 361.25, 0.00, 2.00, 0.00, 354.03, 361.25, 354.03),
(236, 56, NULL, 51, 'Levopraid 25mg Tab', NULL, 'DEFAULT', '2028-09-30', 2, 0, 456.93, 456.93, 0.00, 2.00, 0.00, 895.58, 456.93, 895.58),
(237, 56, NULL, 75, 'ECP Tab', NULL, 'DEFAULT', '2028-10-02', 10, 0, 24.94, 24.94, 0.00, 1.00, 0.00, 246.91, 24.94, 246.91),
(238, 56, NULL, 52, 'Levopraid  50mg Tab', NULL, 'DEFAULT', '2028-09-30', 2, 0, 790.66, 790.66, 0.00, 5.00, 0.00, 1502.25, 790.66, 1502.25),
(239, 56, NULL, 93, 'Amodip V 5/80 Tab', NULL, 'DEFAULT', '2028-10-02', 1, 0, 361.25, 361.25, 0.00, 2.00, 0.00, 354.03, 361.25, 354.03),
(240, 56, NULL, 109, 'Lipirex 20mg Tab', NULL, 'DEFAULT', '2028-10-02', 4, 0, 816.00, 816.00, 0.00, 10.00, 0.00, 2937.60, 816.00, 2937.60),
(241, 56, NULL, 123, 'Xavor DIU 50mg Tab', NULL, 'DEFAULT', '2028-10-02', 1, 0, 233.43, 233.43, 0.00, 2.00, 0.00, 228.76, 233.43, 228.76),
(242, 56, NULL, 16, 'Revital Multi Tab 45s', NULL, 'DEFAULT', '2028-09-30', 2, 0, 696.45, 696.45, 0.00, 2.00, 0.00, 1365.04, 696.45, 1365.04),
(243, 56, NULL, 23, 'E Clar Syp 60ml', NULL, 'BAT-260929', '2028-09-29', 1, 0, 350.20, 350.20, 0.00, 5.00, 0.00, 332.69, 350.20, 332.69),
(244, 56, NULL, 22, 'Alcuflex 550mg Tab 30s', NULL, 'DEFAULT', '2028-09-30', 1, 0, 580.89, 580.89, 0.00, 5.00, 0.00, 551.85, 580.89, 551.85),
(245, 56, NULL, 11, 'Empaa 10mg Tab 28s', NULL, 'DEFAULT', '2028-09-29', 1, 0, 833.00, 833.00, 0.00, 2.00, 0.00, 816.34, 833.00, 816.34),
(246, 56, NULL, 43, 'Zyrtec Syp 60ml', NULL, 'DEFAULT', '2028-09-30', 3, 0, 134.89, 134.89, 0.00, 8.00, 0.00, 372.30, 134.89, 372.30),
(247, 56, NULL, 130, 'Apranax 550mg Tab', NULL, 'DEFAULT', '2028-10-02', 1, 0, 448.93, 448.93, 0.00, 5.00, 0.00, 426.48, 448.93, 426.48),
(248, 56, NULL, 142, 'Montiget 10mg Tab', NULL, 'DEFAULT', '2028-10-02', 1, 0, 471.75, 471.75, 0.00, 1.00, 0.00, 467.03, 471.75, 467.03),
(249, 56, NULL, 86, 'Lice -O-Nil Cream', NULL, 'DEFAULT', '2028-10-02', 1, 0, 212.50, 212.50, 0.00, 3.00, 0.00, 206.13, 212.50, 206.13),
(250, 56, NULL, 143, 'Treviamet 50/500 tab 14s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 963.90, 963.90, 0.00, 1.00, 0.00, 954.26, 963.90, 954.26),
(251, 56, NULL, 50, 'Craflim Tab', NULL, 'BAT-260929', '2028-09-29', 2, 0, 301.58, 301.58, 0.00, 2.00, 0.00, 591.10, 301.58, 591.10),
(252, 56, NULL, 101, 'HCQ 200Tab', NULL, 'DEFAULT', '2028-10-02', 1, 0, 472.86, 472.86, 0.00, 4.00, 0.00, 453.95, 472.86, 453.95),
(253, 56, NULL, 100, 'Rovista 5mg Tab', NULL, 'DEFAULT', '2028-10-02', 1, 0, 697.00, 697.00, 0.00, 5.00, 0.00, 662.15, 697.00, 662.15),
(254, 56, NULL, 119, 'Spasfon Tab', NULL, 'DEFAULT', '2028-10-02', 1, 0, 1489.20, 1489.20, 0.00, 3.00, 0.00, 1444.52, 1489.20, 1444.52),
(285, 32, NULL, 33, 'Solo 10mg Tab 14s', NULL, 'DEFAULT', '2028-09-30', 2, 0, 382.50, 382.50, 0.00, 10.00, 0.00, 688.50, 382.50, 688.50),
(286, 32, NULL, 125, 'Deximox E/D', NULL, 'DEFAULT', '2028-10-02', 3, 0, 356.15, 356.15, 0.00, 3.00, 0.00, 1036.40, 356.15, 1036.40),
(287, 32, NULL, 106, 'Sita 100mg Tab 14s', NULL, 'DEFAULT', '2028-10-02', 2, 0, 875.50, 875.50, 0.00, 2.00, 0.00, 1715.98, 875.50, 1715.98),
(288, 32, NULL, 55, 'Cellgee Tab 30s', NULL, '', NULL, 1, 0, 720.80, 720.80, 0.00, 5.00, 0.00, 684.76, 720.80, 684.76),
(289, 32, NULL, 57, 'Methix Tab 20s', NULL, '', NULL, 2, 0, 922.03, 922.03, 0.00, 5.00, 0.00, 1751.86, 922.03, 1751.86),
(290, 32, NULL, 49, 'Combivair 400mg Cap', NULL, 'DEFAULT', '2028-09-30', 2, 0, 619.82, 619.82, 0.00, 2.00, 0.00, 1214.85, 619.82, 1214.85),
(291, 32, NULL, 9, 'Kestine 10mg Tab 14s', NULL, 'DEFAULT', '2028-09-29', 3, 0, 252.71, 252.71, 0.00, 2.00, 0.00, 742.97, 252.71, 742.97),
(292, 32, NULL, 12, 'Empaa M 12.5/500mg Tab 28s', NULL, '', NULL, 2, 0, 904.40, 904.40, 0.00, 2.00, 0.00, 1772.62, 904.40, 1772.62),
(293, 32, NULL, 73, 'Beceptor 10mg Tab', NULL, '', NULL, 1, 0, 191.25, 191.25, 0.00, 5.00, 0.00, 181.69, 191.25, 181.69),
(294, 32, NULL, 58, 'Intig D Tab', NULL, 'DEFAULT', '2028-10-02', 1, 0, 446.25, 446.25, 0.00, 1.00, 0.00, 441.79, 446.25, 441.79),
(295, 32, NULL, 136, 'Derma Smooth Lotion 120ml', NULL, '', NULL, 2, 0, 403.75, 403.75, 0.00, 3.00, 0.00, 783.28, 403.75, 783.28),
(296, 32, NULL, 109, 'Lipirex 20mg Tab', NULL, 'DEFAULT', '2028-10-02', 2, 0, 816.00, 816.00, 0.00, 0.00, 0.00, 1632.00, 816.00, 1632.00),
(297, 32, NULL, 87, 'Venticort 400mg Cap', NULL, 'DEFAULT', '2028-10-02', 2, 0, 484.50, 484.50, 0.00, 0.00, 0.00, 969.00, 484.50, 969.00),
(298, 32, NULL, 13, 'Rhinosone P Spray 15ml', NULL, 'DEFAULT', '2028-09-30', 3, 0, 199.75, 199.75, 0.00, 0.00, 0.00, 599.25, 199.75, 599.25),
(299, 32, NULL, 26, 'Klaricid 500mg Tab 10s', NULL, 'DEFAULT', '2028-10-01', 1, 0, 841.62, 841.62, 0.00, 0.00, 0.00, 841.62, 841.62, 841.62),
(300, 32, NULL, 18, 'Famila 28F 3Cycle Tab', NULL, 'DEFAULT', '2028-09-30', 4, 0, 127.39, 127.39, 0.00, 0.00, 0.00, 509.56, 127.39, 509.56),
(301, 57, NULL, 48, 'Flagyl 400mg Tab', NULL, 'DEFAULT', '2028-09-30', 1, 0, 809.00, 809.00, 0.00, 0.00, 0.00, 809.00, 809.00, 809.00),
(302, 58, NULL, 65, 'Tenormin 50mg Tab', NULL, 'DEFAULT', '2028-09-30', 1, 0, 354.02, 354.02, 0.00, 3.00, 0.00, 343.40, 354.02, 343.40),
(303, 58, NULL, 78, 'Zafnol Tab', NULL, 'DEFAULT', '2028-10-02', 4, 0, 106.25, 106.25, 0.00, 1.00, 0.00, 420.75, 106.25, 420.75),
(304, 58, NULL, 95, 'Sita Met 50/1000mg Tab 14s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 617.10, 617.10, 0.00, 2.00, 0.00, 604.76, 617.10, 604.76),
(305, 58, NULL, 94, 'Sita Met 50/500mg Tab 14s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 599.25, 599.25, 0.00, 2.00, 0.00, 587.27, 599.25, 587.27),
(306, 58, NULL, 78, 'Zafnol Tab', NULL, 'DEFAULT', '2028-10-02', 1, 0, 106.25, 106.25, 0.00, 1.00, 0.00, 105.19, 106.25, 105.19),
(307, 59, NULL, 1, 'Clobevate Cream', NULL, 'DEFAULT', '2028-09-29', 6, 0, 185.05, 185.05, 0.00, 5.00, 0.00, 1054.79, 185.05, 1054.79),
(308, 59, NULL, 32, 'Clobederm NN Oint 15g', NULL, 'DEFAULT', '2028-09-30', 3, 0, 127.50, 127.50, 0.00, 5.00, 0.00, 363.38, 127.50, 363.38),
(309, 59, NULL, 29, 'Betaderm N Cream 15g', NULL, 'BAT-260929', '2028-09-29', 3, 0, 102.00, 102.00, 0.00, 5.00, 0.00, 290.70, 102.00, 290.70),
(310, 59, NULL, 30, 'Betaderm N Oint 15g', NULL, 'BAT-260929', '2028-09-29', 3, 0, 102.00, 102.00, 0.00, 5.00, 0.00, 290.70, 102.00, 290.70),
(311, 59, NULL, 8, 'Novoteph 40mg Cap', NULL, 'DEFAULT', '2028-09-29', 1, 0, 433.50, 433.50, 0.00, 1.00, 0.00, 429.17, 433.50, 429.17),
(312, 59, NULL, 38, 'Betnovate N Cream', NULL, 'DEFAULT', '2028-09-30', 3, 0, 153.53, 153.53, 0.00, 5.00, 0.00, 437.56, 153.53, 437.56),
(313, 60, NULL, 17, 'ST.MOM 200mg Tab 10s', NULL, 'DEFAULT', '2028-09-30', 2, 0, 169.60, 169.60, 0.00, 2.00, 0.00, 332.42, 169.60, 332.42),
(314, 60, NULL, 78, 'Zafnol Tab', NULL, 'DEFAULT', '2028-10-02', 1, 0, 106.25, 106.25, 0.00, 1.00, 0.00, 105.19, 106.25, 105.19),
(315, 60, NULL, 86, 'Lice -O-Nil Cream', NULL, 'DEFAULT', '2028-10-02', 1, 0, 212.50, 212.50, 0.00, 3.00, 0.00, 206.13, 212.50, 206.13),
(316, 60, NULL, 96, 'Nuberol Tab 100s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 722.33, 722.33, 0.00, 1.00, 0.00, 715.11, 722.33, 715.11),
(317, 60, NULL, 27, 'Somogel Cream', NULL, 'DEFAULT', '2028-09-30', 2, 0, 165.75, 165.75, 0.00, 3.00, 0.00, 321.56, 165.75, 321.56),
(318, 61, NULL, 147, 'Surbex Z Tab', NULL, 'DEFAULT', '2028-10-06', 2, 0, 433.50, 433.50, 0.00, 1.00, 0.00, 858.33, 433.50, 858.33),
(319, 61, NULL, 22, 'Alcuflex 550mg Tab 30s', NULL, 'DEFAULT', '2028-09-30', 2, 0, 580.89, 580.89, 0.00, 5.00, 0.00, 1103.69, 580.89, 1103.69),
(320, 62, NULL, 69, 'Klaricid XL Tab 5s', NULL, 'DEFAULT', '2028-10-01', 2, 0, 420.81, 420.81, 0.00, 2.00, 0.00, 824.79, 420.81, 824.79),
(321, 63, NULL, 63, 'Ossobon D Tab', NULL, 'DEFAULT', '2028-09-29', 2, 0, 425.00, 425.00, 0.00, 3.00, 0.00, 824.50, 425.00, 824.50),
(322, 63, NULL, 130, 'Apranax 550mg Tab', NULL, 'DEFAULT', '2028-10-02', 2, 0, 448.93, 448.93, 0.00, 5.00, 0.00, 852.97, 448.93, 852.97),
(323, 63, NULL, 34, 'Xylor Tab 20s', NULL, 'DEFAULT', '2028-09-30', 2, 0, 242.53, 242.53, 0.00, 10.00, 0.00, 436.55, 242.53, 436.55),
(324, 63, NULL, 19, 'Wilgesic Fort Tab 100s', NULL, 'BAT-260929', '2028-09-29', 1, 0, 782.00, 782.00, 0.00, 8.00, 0.00, 719.44, 782.00, 719.44),
(325, 63, NULL, 55, 'Cellgee Tab 30s', NULL, '', NULL, 1, 0, 720.80, 720.80, 0.00, 5.00, 0.00, 684.76, 720.80, 684.76),
(326, 64, NULL, 63, 'Ossobon D Tab', NULL, 'DEFAULT', '2028-09-29', 2, 0, 425.00, 425.00, 0.00, 3.00, 0.00, 824.50, 425.00, 824.50),
(327, 64, NULL, 66, 'Tenormin 100mg Tab', NULL, 'DEFAULT', '2028-09-30', 1, 0, 540.40, 540.40, 0.00, 3.00, 0.00, 524.19, 540.40, 524.19),
(328, 64, NULL, 11, 'Empaa 10mg Tab 28s', NULL, 'DEFAULT', '2028-09-29', 2, 0, 833.00, 833.00, 0.00, 2.00, 0.00, 1632.68, 833.00, 1632.68),
(329, 65, NULL, 105, 'Sita 50mg Tab 14s', NULL, 'DEFAULT', '2028-10-02', 4, 0, 512.47, 512.47, 0.00, 2.00, 0.00, 2008.88, 512.47, 2008.88),
(330, 65, NULL, 112, 'Tobra D E/D', NULL, 'DEFAULT', '2028-10-02', 2, 0, 242.25, 242.25, 0.00, 5.00, 0.00, 460.28, 242.25, 460.28),
(331, 66, NULL, 57, 'Methix Tab 20s', NULL, '', NULL, 1, 0, 922.03, 922.03, 0.00, 5.00, 0.00, 875.93, 922.03, 875.93),
(332, 66, NULL, 9, 'Kestine 10mg Tab 14s', NULL, 'DEFAULT', '2028-09-29', 2, 0, 252.71, 252.71, 0.00, 2.00, 0.00, 495.31, 252.71, 495.31),
(333, 66, NULL, 101, 'HCQ 200Tab', NULL, 'DEFAULT', '2028-10-02', 2, 0, 472.86, 472.86, 0.00, 4.00, 0.00, 907.89, 472.86, 907.89),
(334, 66, NULL, 39, 'Calpol 6+ Syp 90ml', NULL, 'DEFAULT', '2028-09-30', 3, 0, 101.48, 101.48, 0.00, 5.00, 0.00, 289.22, 101.48, 289.22),
(335, 66, NULL, 147, 'Surbex Z Tab', NULL, 'DEFAULT', '2028-10-06', 4, 0, 433.50, 433.50, 0.00, 1.00, 0.00, 1716.66, 433.50, 1716.66),
(336, 67, NULL, 9, 'Kestine 10mg Tab 14s', NULL, 'DEFAULT', '2028-09-29', 1, 0, 252.71, 252.71, 0.00, 2.00, 0.00, 247.66, 252.71, 247.66),
(337, 67, NULL, 22, 'Alcuflex 550mg Tab 30s', NULL, 'DEFAULT', '2028-09-30', 1, 0, 580.89, 580.89, 0.00, 5.00, 0.00, 551.85, 580.89, 551.85),
(338, 67, NULL, 7, 'Novidat 250mg Tab', NULL, 'BAT-260929', '2028-09-29', 1, 0, 233.75, 233.75, 0.00, 1.00, 0.00, 231.41, 233.75, 231.41),
(339, 68, NULL, 18, 'Famila 28F 3Cycle Tab', NULL, 'DEFAULT', '2028-09-30', 1, 0, 127.39, 127.39, 0.00, 2.00, 0.00, 124.84, 127.39, 124.84),
(340, 68, NULL, 45, 'Entox P Tab', NULL, 'DEFAULT', '2028-09-30', 1, 0, 442.00, 442.00, 0.00, 2.00, 0.00, 433.16, 442.00, 433.16),
(341, 68, NULL, 115, 'Ezium 20mg Cap 14s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 331.50, 331.50, 0.00, 1.00, 0.00, 328.19, 331.50, 328.19),
(342, 69, NULL, 60, 'Fusiderm Cream', NULL, 'DEFAULT', '2028-09-29', 1, 0, 324.50, 324.50, 0.00, 10.00, 0.00, 292.05, 324.50, 292.05),
(343, 69, NULL, 61, 'Fusiderm H Cream', NULL, 'DEFAULT', '2028-09-29', 1, 0, 355.30, 355.30, 0.00, 10.00, 0.00, 319.77, 355.30, 319.77),
(344, 69, NULL, 2, 'Velosef 500mg Cap', NULL, 'DEFAULT', '2028-09-29', 1, 0, 603.17, 603.17, 0.00, 5.00, 0.00, 573.01, 603.17, 573.01),
(345, 69, NULL, 86, 'Lice -O-Nil Cream', NULL, 'DEFAULT', '2028-10-02', 1, 0, 212.50, 212.50, 0.00, 3.00, 0.00, 206.13, 212.50, 206.13),
(346, 69, NULL, 94, 'Sita Met 50/500mg Tab 14s', NULL, 'DEFAULT', '2028-10-02', 3, 0, 599.25, 599.25, 0.00, 2.00, 0.00, 1761.80, 599.25, 1761.80),
(347, 69, NULL, 137, 'Sofvasc V 5/80mg Tab  14s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 380.55, 380.55, 0.00, 3.00, 0.00, 369.13, 380.55, 369.13),
(348, 69, NULL, 58, 'Intig D Tab', NULL, 'DEFAULT', '2028-10-02', 1, 0, 446.25, 446.25, 0.00, 1.00, 0.00, 441.79, 446.25, 441.79),
(349, 69, NULL, 105, 'Sita 50mg Tab 14s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 512.47, 512.47, 0.00, 2.00, 0.00, 502.22, 512.47, 502.22),
(350, 69, NULL, 113, 'Jentin Met 50/500mg Tab 14s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 522.75, 522.75, 0.00, 1.00, 0.00, 517.52, 522.75, 517.52),
(351, 69, NULL, 118, 'Laprazol 30mg Cap 14s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 364.65, 364.65, 0.00, 5.00, 0.00, 346.42, 364.65, 346.42),
(352, 69, NULL, 11, 'Empaa 10mg Tab 28s', NULL, 'DEFAULT', '2028-09-29', 1, 0, 833.00, 833.00, 0.00, 2.00, 0.00, 816.34, 833.00, 816.34),
(353, 69, NULL, 1, 'Clobevate Cream', NULL, 'DEFAULT', '2028-09-29', 10, 0, 185.05, 185.05, 0.00, 5.00, 0.00, 1757.98, 185.05, 1757.98),
(354, 69, NULL, 13, 'Rhinosone P Spray 15ml', NULL, 'DEFAULT', '2028-09-30', 2, 0, 199.75, 199.75, 0.00, 2.00, 0.00, 391.51, 199.75, 391.51),
(355, 70, NULL, 66, 'Tenormin 100mg Tab', NULL, 'DEFAULT', '2028-09-30', 2, 0, 540.40, 540.40, 0.00, 3.00, 0.00, 1048.38, 540.40, 1048.38),
(356, 70, NULL, 18, 'Famila 28F 3Cycle Tab', NULL, 'DEFAULT', '2028-09-30', 2, 0, 127.39, 127.39, 0.00, 2.00, 0.00, 249.68, 127.39, 249.68),
(357, 70, NULL, 17, 'ST.MOM 200mg Tab 10s', NULL, 'DEFAULT', '2028-09-30', 5, 0, 169.60, 169.60, 0.00, 2.00, 0.00, 831.04, 169.60, 831.04),
(358, 70, NULL, 74, 'Atenolol Tab', NULL, 'DEFAULT', '2028-10-02', 1, 0, 148.75, 148.75, 0.00, 1.00, 0.00, 147.26, 148.75, 147.26),
(359, 70, NULL, 75, 'ECP Tab', NULL, '', NULL, 1, 0, 24.94, 24.94, 0.00, 1.00, 0.00, 24.69, 24.94, 24.69),
(360, 70, NULL, 96, 'Nuberol Tab 100s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 722.33, 722.33, 0.00, 1.00, 0.00, 715.11, 722.33, 715.11),
(361, 70, NULL, 52, 'Levopraid  50mg Tab', NULL, 'DEFAULT', '2028-09-30', 1, 0, 790.66, 790.66, 0.00, 5.00, 0.00, 751.13, 790.66, 751.13),
(362, 70, NULL, 35, 'Canderel 18mg Tab 100s', NULL, 'DEFAULT', '2028-09-29', 1, 0, 236.02, 236.02, 0.00, 10.00, 0.00, 212.42, 236.02, 212.42),
(363, 71, NULL, 63, 'Ossobon D Tab', NULL, 'DEFAULT', '2028-09-29', 2, 0, 425.00, 425.00, 0.00, 3.00, 0.00, 824.50, 425.00, 824.50),
(364, 71, NULL, 130, 'Apranax 550mg Tab', NULL, 'DEFAULT', '2028-10-02', 2, 0, 448.93, 448.93, 0.00, 5.00, 0.00, 852.97, 448.93, 852.97),
(365, 71, NULL, 19, 'Wilgesic Fort Tab 100s', NULL, 'DEFAULT', '2028-09-30', 1, 0, 782.00, 782.00, 0.00, 8.00, 0.00, 719.44, 782.00, 719.44),
(366, 71, NULL, 55, 'Cellgee Tab 30s', NULL, '', NULL, 1, 0, 850.00, 850.00, 0.00, 5.00, 0.00, 807.50, 850.00, 807.50),
(367, 72, NULL, 57, 'Methix Tab 20s', NULL, '', NULL, 1, 0, 1088.00, 1088.00, 0.00, 5.00, 0.00, 1033.60, 1088.00, 1033.60),
(368, 72, NULL, 9, 'Kestine 10mg Tab 14s', NULL, 'DEFAULT', '2028-09-29', 2, 0, 252.71, 252.71, 0.00, 2.00, 0.00, 495.31, 252.71, 495.31),
(369, 72, NULL, 101, 'HCQ 200Tab', NULL, 'DEFAULT', '2028-10-02', 2, 0, 472.86, 472.86, 0.00, 4.00, 0.00, 907.89, 472.86, 907.89),
(370, 72, NULL, 39, 'Calpol 6+ Syp 90ml', NULL, 'DEFAULT', '2028-09-30', 3, 0, 101.48, 101.48, 0.00, 5.00, 0.00, 289.22, 101.48, 289.22),
(371, 72, NULL, 147, 'Surbex Z Tab', NULL, 'DEFAULT', '2028-10-06', 4, 0, 433.50, 433.50, 0.00, 1.00, 0.00, 1716.66, 433.50, 1716.66),
(372, 73, NULL, 63, 'Ossobon D Tab', NULL, 'DEFAULT', '2028-09-29', 2, 0, 425.00, 425.00, 0.00, 3.00, 0.00, 824.50, 425.00, 824.50),
(373, 73, NULL, 130, 'Apranax 550mg Tab', NULL, 'DEFAULT', '2028-10-02', 2, 0, 448.93, 448.93, 0.00, 5.00, 0.00, 852.97, 448.93, 852.97),
(374, 73, NULL, 34, 'Xylor Tab 20s', NULL, 'DEFAULT', '2028-09-30', 2, 0, 242.53, 242.53, 0.00, 10.00, 0.00, 436.55, 242.53, 436.55),
(375, 73, NULL, 19, 'Wilgesic Fort Tab 100s', NULL, 'DEFAULT', '2028-09-30', 1, 0, 782.00, 782.00, 0.00, 8.00, 0.00, 719.44, 782.00, 719.44),
(376, 73, NULL, 55, 'Cellgee Tab 30s', NULL, '', NULL, 1, 0, 850.00, 850.00, 0.00, 5.00, 0.00, 807.50, 850.00, 807.50),
(377, 74, NULL, 18, 'Famila 28F 3Cycle Tab', NULL, 'DEFAULT', '2028-09-30', 2, 0, 127.39, 127.39, 0.00, 2.00, 0.00, 249.68, 127.39, 249.68),
(378, 74, NULL, 66, 'Tenormin 100mg Tab', NULL, 'DEFAULT', '2028-09-30', 1, 0, 540.40, 540.40, 0.00, 3.00, 0.00, 524.19, 540.40, 524.19),
(379, 74, NULL, 17, 'ST.MOM 200mg Tab 10s', NULL, 'DEFAULT', '2028-09-30', 3, 0, 169.60, 169.60, 0.00, 2.00, 0.00, 498.62, 169.60, 498.62),
(380, 74, NULL, 123, 'Xavor DIU 50mg Tab', NULL, 'DEFAULT', '2028-10-02', 1, 0, 233.43, 233.43, 0.00, 2.00, 0.00, 228.76, 233.43, 228.76),
(381, 74, NULL, 9, 'Kestine 10mg Tab 14s', NULL, 'DEFAULT', '2028-09-29', 2, 0, 252.71, 252.71, 0.00, 2.00, 0.00, 495.31, 252.71, 495.31),
(382, 74, NULL, 147, 'Surbex Z Tab', NULL, 'DEFAULT', '2028-10-06', 7, 0, 433.50, 433.50, 0.00, 1.00, 0.00, 3004.16, 433.50, 3004.16),
(383, 75, NULL, 79, 'Zodip Tab', NULL, 'DEFAULT', '2028-10-02', 2, 0, 74.86, 74.86, 0.00, 1.00, 0.00, 148.22, 74.86, 148.22),
(384, 75, NULL, 39, 'Calpol 6+ Syp 90ml', NULL, 'DEFAULT', '2028-09-30', 1, 0, 101.48, 101.48, 0.00, 5.00, 0.00, 96.41, 101.48, 96.41),
(385, 75, NULL, 38, 'Betnovate N Cream', NULL, 'DEFAULT', '2028-09-30', 2, 0, 153.53, 153.53, 0.00, 5.00, 0.00, 291.71, 153.53, 291.71),
(386, 75, NULL, 35, 'Canderel 18mg Tab 100s', NULL, 'DEFAULT', '2028-09-29', 2, 0, 236.02, 236.02, 0.00, 10.00, 0.00, 424.84, 236.02, 424.84),
(387, 75, NULL, 7, 'Novidat 250mg Tab', NULL, 'DEFAULT', '2028-09-29', 1, 0, 233.75, 233.75, 0.00, 1.00, 0.00, 231.41, 233.75, 231.41),
(388, 75, NULL, 14, 'Azomax 250mg Cap 12s', NULL, 'DEFAULT', '2028-09-30', 1, 0, 675.19, 675.19, 0.00, 1.00, 0.00, 668.44, 675.19, 668.44),
(389, 75, NULL, 72, 'Cardura 2mg Tab', NULL, 'DEFAULT', '2028-10-02', 1, 0, 676.86, 676.86, 0.00, 1.00, 0.00, 670.09, 676.86, 670.09),
(390, 76, NULL, 61, 'Fusiderm H Cream', NULL, 'DEFAULT', '2028-09-29', 2, 0, 355.30, 355.30, 0.00, 10.00, 0.00, 639.54, 355.30, 639.54),
(391, 76, NULL, 132, 'Atorva 20mg Tab 20s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 1124.55, 1124.55, 0.00, 10.00, 0.00, 1012.10, 1124.55, 1012.10),
(392, 76, NULL, 57, 'Methix Tab 20s', NULL, '', NULL, 1, 0, 1088.00, 1088.00, 0.00, 5.00, 0.00, 1033.60, 1088.00, 1033.60),
(393, 77, NULL, 64, 'Tenormin 25mg Tab', NULL, 'DEFAULT', '2028-09-30', 1, 0, 201.02, 201.02, 0.00, 3.00, 0.00, 194.99, 201.02, 194.99),
(394, 77, NULL, 65, 'Tenormin 50mg Tab', NULL, 'DEFAULT', '2028-09-30', 1, 0, 354.02, 354.02, 0.00, 3.00, 0.00, 343.40, 354.02, 343.40),
(395, 77, NULL, 75, 'ECP Tab', NULL, '', NULL, 6, 0, 24.94, 24.94, 0.00, 1.00, 0.00, 148.14, 24.94, 148.14),
(396, 77, NULL, 49, 'Combivair 400mg Cap', NULL, 'DEFAULT', '2028-09-30', 1, 0, 619.82, 619.82, 0.00, 2.00, 0.00, 607.42, 619.82, 607.42),
(397, 77, NULL, 111, 'Tobra E/D', NULL, 'DEFAULT', '2028-10-02', 2, 0, 191.25, 191.25, 0.00, 5.00, 0.00, 363.38, 191.25, 363.38),
(398, 77, NULL, 11, 'Empaa 10mg Tab 28s', NULL, 'DEFAULT', '2028-09-29', 1, 0, 833.00, 833.00, 0.00, 2.00, 0.00, 816.34, 833.00, 816.34),
(399, 77, NULL, 41, 'Augmentin DS 312.5/5ml Syp', NULL, 'DEFAULT', '2028-09-30', 1, 0, 531.57, 531.57, 0.00, 5.00, 0.00, 504.99, 531.57, 504.99),
(400, 78, NULL, 2, 'Velosef 500mg Cap', NULL, 'DEFAULT', '2028-09-29', 2, 0, 603.17, 603.17, 0.00, 5.00, 0.00, 1146.02, 603.17, 1146.02),
(401, 78, NULL, 65, 'Tenormin 50mg Tab', NULL, 'DEFAULT', '2028-09-30', 2, 0, 354.02, 354.02, 0.00, 3.00, 0.00, 686.80, 354.02, 686.80),
(402, 78, NULL, 97, 'Magnett 100/5ml Syp', NULL, 'DEFAULT', '2028-10-02', 2, 0, 242.25, 242.25, 0.00, 2.00, 0.00, 474.81, 242.25, 474.81),
(403, 78, NULL, 144, 'Treviamet 50/1000 tab 14s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 999.60, 999.60, 0.00, 1.00, 0.00, 989.60, 999.60, 989.60),
(404, 78, NULL, 28, 'Betaderm Cream 15g', NULL, 'BAT-260929', '2028-09-29', 3, 0, 61.20, 61.20, 0.00, 3.00, 0.00, 178.09, 61.20, 178.09),
(405, 78, NULL, 38, 'Betnovate N Cream', NULL, 'DEFAULT', '2028-09-30', 4, 0, 153.53, 153.53, 0.00, 5.00, 0.00, 583.41, 153.53, 583.41),
(406, 78, NULL, 147, 'Surbex Z Tab', NULL, 'DEFAULT', '2028-10-06', 3, 0, 433.50, 433.50, 0.00, 1.00, 0.00, 1287.50, 433.50, 1287.50),
(407, 79, NULL, 9, 'Kestine 10mg Tab 14s', NULL, 'DEFAULT', '2028-09-29', 1, 0, 252.71, 252.71, 0.00, 2.00, 0.00, 247.66, 252.71, 247.66),
(408, 79, NULL, 10, 'Ulsanic Syp 120ml', NULL, 'DEFAULT', '2028-09-29', 1, 0, 351.48, 351.48, 0.00, 2.00, 0.00, 344.45, 351.48, 344.45),
(409, 79, NULL, 49, 'Combivair 400mg Cap', NULL, 'DEFAULT', '2028-09-30', 1, 0, 619.82, 619.82, 0.00, 2.00, 0.00, 607.42, 619.82, 607.42),
(410, 79, NULL, 61, 'Fusiderm H Cream', NULL, 'DEFAULT', '2028-09-29', 1, 0, 355.30, 355.30, 0.00, 10.00, 0.00, 319.77, 355.30, 319.77),
(411, 80, NULL, 2, 'Velosef 500mg Cap', NULL, 'DEFAULT', '2028-09-29', 2, 0, 603.17, 603.17, 0.00, 5.00, 0.00, 1146.02, 603.17, 1146.02),
(412, 80, NULL, 86, 'Lice -O-Nil Cream', NULL, 'DEFAULT', '2028-10-02', 1, 0, 212.50, 212.50, 0.00, 3.00, 0.00, 206.13, 212.50, 206.13),
(413, 80, NULL, 45, 'Entox P Tab', NULL, 'DEFAULT', '2028-09-30', 2, 0, 442.00, 442.00, 0.00, 2.00, 0.00, 866.32, 442.00, 866.32),
(414, 81, NULL, 78, 'Zafnol Tab', NULL, 'DEFAULT', '2028-10-02', 5, 0, 106.25, 106.25, 0.00, 1.00, 0.00, 525.94, 106.25, 525.94),
(415, 81, NULL, 79, 'Zodip Tab', NULL, 'DEFAULT', '2028-10-02', 5, 0, 74.86, 74.86, 0.00, 1.00, 0.00, 370.56, 74.86, 370.56),
(416, 81, NULL, 86, 'Lice -O-Nil Cream', NULL, 'DEFAULT', '2028-10-02', 3, 0, 212.50, 212.50, 0.00, 3.00, 0.00, 618.38, 212.50, 618.38),
(417, 81, NULL, 42, 'Augmentin  156.25/5ml Syp', NULL, 'DEFAULT', '2028-09-30', 2, 0, 322.69, 322.69, 0.00, 5.00, 0.00, 613.11, 322.69, 613.11),
(419, 82, NULL, 75, 'ECP Tab', NULL, '', NULL, 4, 0, 24.94, 24.94, 0.00, 1.00, 0.00, 98.76, 24.94, 98.76),
(420, 82, NULL, 39, 'Calpol 6+ Syp 90ml', NULL, 'DEFAULT', '2028-09-30', 2, 0, 101.48, 101.48, 0.00, 0.00, 0.00, 202.96, 101.48, 202.96),
(421, 83, NULL, 10, 'Ulsanic Syp 120ml', NULL, 'DEFAULT', '2028-09-29', 3, 0, 351.48, 351.48, 0.00, 2.00, 0.00, 1033.35, 351.48, 1033.35),
(422, 83, NULL, 55, 'Cellgee Tab 30s', NULL, '', NULL, 2, 0, 850.00, 850.00, 0.00, 5.00, 0.00, 1615.00, 850.00, 1615.00),
(423, 83, NULL, 65, 'Tenormin 50mg Tab', NULL, 'DEFAULT', '2028-09-30', 2, 0, 354.02, 354.02, 0.00, 3.00, 0.00, 686.80, 354.02, 686.80),
(424, 83, NULL, 18, 'Famila 28F 3Cycle Tab', NULL, 'DEFAULT', '2028-09-30', 2, 0, 127.39, 127.39, 0.00, 2.00, 0.00, 249.68, 127.39, 249.68),
(425, 83, NULL, 92, 'Sea Cal Sachets', NULL, 'DEFAULT', '2028-10-02', 1, 0, 439.12, 439.12, 0.00, 5.00, 0.00, 417.16, 439.12, 417.16),
(426, 83, NULL, 93, 'Amodip V 5/80 Tab', NULL, 'DEFAULT', '2028-10-02', 1, 0, 361.25, 361.25, 0.00, 2.00, 0.00, 354.03, 361.25, 354.03),
(427, 83, NULL, 45, 'Entox P Tab', NULL, 'DEFAULT', '2028-09-30', 1, 0, 442.00, 442.00, 0.00, 2.00, 0.00, 433.16, 442.00, 433.16),
(428, 83, NULL, 103, 'Gablin 75mg Cap', NULL, 'DEFAULT', '2028-10-02', 1, 0, 567.80, 567.80, 0.00, 7.00, 0.00, 528.05, 567.80, 528.05),
(429, 83, NULL, 112, 'Tobra D E/D', NULL, 'DEFAULT', '2028-10-02', 1, 0, 242.25, 242.25, 0.00, 5.00, 0.00, 230.14, 242.25, 230.14),
(430, 83, NULL, 35, 'Canderel 18mg Tab 100s', NULL, 'DEFAULT', '2028-09-29', 2, 0, 236.02, 236.02, 0.00, 10.00, 0.00, 424.84, 236.02, 424.84),
(431, 84, NULL, 15, 'Azomax 500mg Tab', NULL, 'DEFAULT', '2028-09-30', 2, 0, 466.40, 466.40, 0.00, 1.00, 0.00, 923.47, 466.40, 923.47),
(432, 84, NULL, 51, 'Levopraid 25mg Tab', NULL, 'DEFAULT', '2028-09-30', 2, 0, 456.93, 456.93, 0.00, 2.00, 0.00, 895.58, 456.93, 895.58),
(433, 84, NULL, 5, 'Elezo 150 Cap', NULL, 'DEFAULT', '2028-09-29', 1, 0, 573.75, 573.75, 0.00, 1.00, 0.00, 568.01, 573.75, 568.01),
(434, 84, NULL, 28, 'Betaderm Cream 15g', NULL, 'DEFAULT', '2028-09-30', 5, 0, 61.20, 61.20, 0.00, 3.00, 0.00, 296.82, 61.20, 296.82),
(435, 84, NULL, 29, 'Betaderm N Cream 15g', NULL, 'DEFAULT', '2028-09-30', 3, 0, 102.00, 102.00, 0.00, 5.00, 0.00, 290.70, 102.00, 290.70),
(436, 84, NULL, 27, 'Somogel Cream', NULL, 'DEFAULT', '2028-09-30', 10, 0, 165.75, 165.75, 0.00, 3.00, 0.00, 1607.78, 165.75, 1607.78),
(437, 85, NULL, 123, 'Xavor DIU 50mg Tab', NULL, 'DEFAULT', '2028-10-02', 5, 0, 233.43, 233.43, 0.00, 2.00, 0.00, 1143.81, 233.43, 1143.81),
(438, 85, NULL, 111, 'Tobra E/D', NULL, 'DEFAULT', '2028-10-02', 2, 0, 191.25, 191.25, 0.00, 5.00, 0.00, 363.38, 191.25, 363.38),
(439, 86, NULL, 101, 'HCQ 200Tab', NULL, 'DEFAULT', '2028-10-02', 2, 0, 472.86, 472.86, 0.00, 4.00, 0.00, 907.89, 472.86, 907.89),
(440, 86, NULL, 53, 'Rovista 20mg Tab', NULL, 'BAT-260929', '2028-09-29', 1, 0, 1713.60, 1713.60, 0.00, 1.00, 0.00, 1696.46, 1713.60, 1696.46),
(441, 86, NULL, 79, 'Zodip Tab', NULL, 'DEFAULT', '2028-10-02', 6, 0, 74.86, 74.86, 0.00, 1.00, 0.00, 444.67, 74.86, 444.67),
(442, 87, NULL, 74, 'Atenolol Tab', NULL, 'DEFAULT', '2028-10-02', 1, 0, 148.75, 148.75, 0.00, 1.00, 0.00, 147.26, 148.75, 147.26),
(443, 87, NULL, 94, 'Sita Met 50/500mg Tab 14s', NULL, 'DEFAULT', '2028-10-02', 3, 0, 599.25, 599.25, 0.00, 2.00, 0.00, 1761.80, 599.25, 1761.80),
(444, 87, NULL, 122, 'Xavor 50mg Tab', NULL, 'DEFAULT', '2028-10-02', 2, 0, 262.35, 262.35, 0.00, 2.00, 0.00, 514.21, 262.35, 514.21),
(445, 88, NULL, 53, 'Rovista 20mg Tab', NULL, 'DEFAULT', '2028-10-01', 1, 0, 1713.60, 1713.60, 0.00, 1.00, 0.00, 1696.46, 1713.60, 1696.46),
(446, 88, NULL, 14, 'Azomax 250mg Cap 12s', NULL, 'DEFAULT', '2028-09-30', 1, 0, 675.19, 675.19, 0.00, 1.00, 0.00, 668.44, 675.19, 668.44),
(447, 88, NULL, 94, 'Sita Met 50/500mg Tab 14s', NULL, 'DEFAULT', '2028-10-02', 2, 0, 599.25, 599.25, 0.00, 2.00, 0.00, 1174.53, 599.25, 1174.53),
(448, 88, NULL, 96, 'Nuberol Tab 100s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 722.33, 722.33, 0.00, 1.00, 0.00, 715.11, 722.33, 715.11),
(449, 88, NULL, 141, 'leflox 250mg Tab', NULL, 'DEFAULT', '2028-10-02', 2, 0, 578.00, 578.00, 0.00, 1.00, 0.00, 1144.44, 578.00, 1144.44),
(450, 88, NULL, 102, 'Lophos Tab 100s', NULL, 'DEFAULT', '2028-10-02', 1, 0, 654.34, 654.34, 0.00, 4.00, 0.00, 628.17, 654.34, 628.17),
(451, 88, NULL, 110, 'Minoxin Plus 5% Sol 60ml', NULL, 'DEFAULT', '2028-10-02', 1, 0, 930.75, 930.75, 0.00, 5.00, 0.00, 884.21, 930.75, 884.21),
(452, 88, NULL, 10, 'Ulsanic Syp 120ml', NULL, 'DEFAULT', '2028-09-29', 1, 0, 351.48, 351.48, 0.00, 2.00, 0.00, 344.45, 351.48, 344.45),
(453, 88, NULL, 11, 'Empaa 10mg Tab 28s', NULL, 'DEFAULT', '2028-09-29', 1, 0, 833.00, 833.00, 0.00, 2.00, 0.00, 816.34, 833.00, 816.34),
(454, 88, NULL, 147, 'Surbex Z Tab', NULL, '78997', '2028-10-06', 5, 0, 433.50, 433.50, 0.00, 1.00, 0.00, 2145.83, 433.50, 2145.83),
(455, 89, NULL, 74, 'Atenolol Tab', NULL, '410', '2028-10-07', 1, 0, 148.75, 148.75, 0.00, 1.00, 0.00, 147.26, 148.75, 147.26),
(456, 89, NULL, 94, 'Sita Met 50/500mg Tab 14s', NULL, 'EV131', '2028-10-02', 3, 0, 599.25, 599.25, 0.00, 2.00, 0.00, 1761.80, 599.25, 1761.80),
(457, 89, NULL, 122, 'Xavor 50mg Tab', NULL, '260704', '2028-10-02', 2, 0, 262.35, 262.35, 0.00, 2.00, 0.00, 514.21, 262.35, 514.21),
(458, 90, NULL, 123, 'Xavor DIU 50mg Tab', NULL, '252394', '2028-10-02', 5, 0, 233.43, 233.43, 0.00, 2.00, 0.00, 1143.81, 233.43, 1143.81),
(459, 90, NULL, 111, 'Tobra E/D', NULL, 'TBD396', '2028-10-02', 2, 0, 191.25, 191.25, 0.00, 5.00, 0.00, 363.38, 191.25, 363.38),
(460, 91, NULL, 101, 'HCQ 200Tab', NULL, 'F14110', '2028-10-02', 2, 0, 472.86, 472.86, 0.00, 4.00, 0.00, 907.89, 472.86, 907.89),
(461, 91, NULL, 53, 'Rovista 20mg Tab', NULL, 'F07059', '2028-10-01', 1, 0, 1713.60, 1713.60, 0.00, 1.00, 0.00, 1696.46, 1713.60, 1696.46),
(462, 91, NULL, 79, 'Zodip Tab', NULL, '558', '2028-10-02', 6, 0, 74.86, 74.86, 0.00, 1.00, 0.00, 444.67, 74.86, 444.67),
(463, 92, NULL, 15, 'Azomax 500mg Tab', NULL, 'N6970', '2028-09-30', 2, 0, 466.40, 466.40, 0.00, 1.00, 0.00, 923.47, 466.40, 923.47),
(464, 92, NULL, 51, 'Levopraid 25mg Tab', NULL, 'LN2509U', '2028-09-30', 2, 0, 456.93, 456.93, 0.00, 2.00, 0.00, 895.58, 456.93, 895.58),
(465, 92, NULL, 5, 'Elezo 150 Cap', NULL, '055O004', '2028-09-29', 1, 0, 573.75, 573.75, 0.00, 1.00, 0.00, 568.01, 573.75, 568.01),
(466, 92, NULL, 28, 'Betaderm Cream 15g', NULL, 'JU014M', '2028-09-30', 5, 0, 61.20, 61.20, 0.00, 3.00, 0.00, 296.82, 61.20, 296.82),
(467, 92, NULL, 29, 'Betaderm N Cream 15g', NULL, 'JW017M', '2028-09-30', 3, 0, 102.00, 102.00, 0.00, 5.00, 0.00, 290.70, 102.00, 290.70),
(468, 92, NULL, 27, 'Somogel Cream', NULL, '(10) 902623XV', '2028-09-30', 10, 0, 165.75, 165.75, 0.00, 3.00, 0.00, 1607.78, 165.75, 1607.78),
(469, 93, NULL, 10, 'Ulsanic Syp 120ml', NULL, '261089', '2028-09-29', 3, 0, 351.48, 351.48, 0.00, 2.00, 0.00, 1033.35, 351.48, 1033.35),
(470, 93, NULL, 55, 'Cellgee Tab 30s', NULL, '', NULL, 2, 0, 850.00, 850.00, 0.00, 5.00, 0.00, 1615.00, 850.00, 1615.00),
(471, 93, NULL, 65, 'Tenormin 50mg Tab', NULL, '264D019', '2028-09-30', 2, 0, 354.02, 354.02, 0.00, 3.00, 0.00, 686.80, 354.02, 686.80),
(472, 93, NULL, 18, 'Famila 28F 3Cycle Tab', NULL, 'K325', '2028-09-30', 2, 0, 127.39, 127.39, 0.00, 2.00, 0.00, 249.68, 127.39, 249.68),
(473, 93, NULL, 92, 'Sea Cal Sachets', NULL, '255', '2028-10-02', 1, 0, 439.12, 439.12, 0.00, 5.00, 0.00, 417.16, 439.12, 417.16),
(474, 93, NULL, 93, 'Amodip V 5/80 Tab', NULL, 'ANO46', '2028-10-02', 1, 0, 361.25, 361.25, 0.00, 2.00, 0.00, 354.03, 361.25, 354.03),
(475, 93, NULL, 45, 'Entox P Tab', NULL, '265B078', '2028-09-30', 1, 0, 442.00, 442.00, 0.00, 2.00, 0.00, 433.16, 442.00, 433.16),
(476, 93, NULL, 103, 'Gablin 75mg Cap', NULL, 'FJ005', '2028-10-02', 1, 0, 567.80, 567.80, 0.00, 7.00, 0.00, 528.05, 567.80, 528.05),
(477, 93, NULL, 112, 'Tobra D E/D', NULL, '(10) TDD804', '2028-10-02', 1, 0, 242.25, 242.25, 0.00, 5.00, 0.00, 230.14, 242.25, 230.14),
(478, 93, NULL, 35, 'Canderel 18mg Tab 100s', NULL, 'ACH047', '2028-09-29', 2, 0, 236.02, 236.02, 0.00, 10.00, 0.00, 424.84, 236.02, 424.84),
(479, 94, NULL, 75, 'ECP Tab', NULL, '', NULL, 4, 0, 24.94, 24.94, 0.00, 1.00, 0.00, 98.76, 24.94, 98.76),
(480, 94, NULL, 39, 'Calpol 6+ Syp 90ml', NULL, '588C', '2028-09-30', 2, 0, 101.48, 101.48, 0.00, 5.00, 0.00, 192.81, 101.48, 192.81),
(481, 95, NULL, 75, 'ECP Tab', NULL, '', NULL, 4, 0, 24.94, 24.94, 0.00, 1.00, 0.00, 98.76, 24.94, 98.76),
(482, 95, NULL, 39, 'Calpol 6+ Syp 90ml', NULL, '588C', '2028-09-30', 2, 0, 101.48, 101.48, 0.00, 5.00, 0.00, 192.81, 101.48, 192.81);
INSERT INTO `sale_items` (`id`, `invoice_id`, `sale_id`, `product_id`, `item_name`, `batch_id`, `batch_no`, `expiry_date`, `quantity`, `bonus_quantity`, `unit_price`, `trade_price`, `retail_price`, `discount_percent`, `extra_discount_percent`, `total_amount`, `sale_price`, `total_price`) VALUES
(483, 96, NULL, 75, 'ECP Tab', NULL, '', NULL, 4, 0, 24.94, 24.94, 0.00, 1.00, 0.00, 98.76, 24.94, 98.76),
(484, 96, NULL, 39, 'Calpol 6+ Syp 90ml', NULL, '588C', '2028-09-30', 2, 0, 101.48, 101.48, 0.00, 5.00, 0.00, 192.81, 101.48, 192.81),
(485, 97, NULL, 75, 'ECP Tab', NULL, '', NULL, 4, 0, 24.94, 24.94, 0.00, 1.00, 0.00, 98.76, 24.94, 98.76),
(486, 97, NULL, 39, 'Calpol 6+ Syp 90ml', NULL, '588C', '2028-09-30', 2, 0, 101.48, 101.48, 0.00, 5.00, 0.00, 192.81, 101.48, 192.81),
(487, 98, NULL, 10, 'Ulsanic Syp 120ml', NULL, '261089', '2028-09-29', 3, 0, 351.48, 351.48, 0.00, 2.00, 0.00, 1033.35, 351.48, 1033.35),
(488, 98, NULL, 55, 'Cellgee Tab 30s', NULL, '', NULL, 2, 0, 850.00, 850.00, 0.00, 5.00, 0.00, 1615.00, 850.00, 1615.00),
(489, 98, NULL, 65, 'Tenormin 50mg Tab', NULL, '264D019', '2028-09-30', 2, 0, 354.02, 354.02, 0.00, 3.00, 0.00, 686.80, 354.02, 686.80),
(490, 98, NULL, 18, 'Famila 28F 3Cycle Tab', NULL, 'K325', '2028-09-30', 2, 0, 127.39, 127.39, 0.00, 2.00, 0.00, 249.68, 127.39, 249.68),
(491, 98, NULL, 92, 'Sea Cal Sachets', NULL, '255', '2028-10-02', 1, 0, 439.12, 439.12, 0.00, 5.00, 0.00, 417.16, 439.12, 417.16),
(492, 98, NULL, 93, 'Amodip V 5/80 Tab', NULL, 'ANO46', '2028-10-02', 1, 0, 361.25, 361.25, 0.00, 2.00, 0.00, 354.03, 361.25, 354.03),
(493, 98, NULL, 45, 'Entox P Tab', NULL, '265B078', '2028-09-30', 1, 0, 442.00, 442.00, 0.00, 2.00, 0.00, 433.16, 442.00, 433.16),
(494, 98, NULL, 103, 'Gablin 75mg Cap', NULL, 'FJ005', '2028-10-02', 1, 0, 567.80, 567.80, 0.00, 7.00, 0.00, 528.05, 567.80, 528.05),
(495, 98, NULL, 112, 'Tobra D E/D', NULL, '(10) TDD804', '2028-10-02', 1, 0, 242.25, 242.25, 0.00, 5.00, 0.00, 230.14, 242.25, 230.14),
(496, 98, NULL, 35, 'Canderel 18mg Tab 100s', NULL, 'ACH047', '2028-09-29', 2, 0, 236.02, 236.02, 0.00, 10.00, 0.00, 424.84, 236.02, 424.84),
(497, 99, NULL, 2, 'Velosef 500mg Cap', NULL, '389V', '2028-09-29', 2, 0, 603.17, 603.17, 0.00, 5.00, 0.00, 1146.02, 603.17, 1146.02),
(498, 99, NULL, 86, 'Lice -O-Nil Cream', NULL, '(10) 3918', '2028-10-02', 1, 0, 212.50, 212.50, 0.00, 3.00, 0.00, 206.13, 212.50, 206.13),
(499, 99, NULL, 45, 'Entox P Tab', NULL, '265B078', '2028-09-30', 2, 0, 442.00, 442.00, 0.00, 2.00, 0.00, 866.32, 442.00, 866.32),
(500, 100, NULL, 9, 'Kestine 10mg Tab 14s', NULL, '261322', '2028-09-29', 1, 0, 252.71, 252.71, 0.00, 2.00, 0.00, 247.66, 252.71, 247.66),
(501, 100, NULL, 10, 'Ulsanic Syp 120ml', NULL, '261089', '2028-09-29', 1, 0, 351.48, 351.48, 0.00, 2.00, 0.00, 344.45, 351.48, 344.45),
(502, 100, NULL, 49, 'Combivair 400mg Cap', NULL, '260325', '2028-09-30', 1, 0, 619.82, 619.82, 0.00, 2.00, 0.00, 607.42, 619.82, 607.42),
(503, 100, NULL, 61, 'Fusiderm H Cream', NULL, 'F107', '2028-09-29', 1, 0, 355.30, 355.30, 0.00, 10.00, 0.00, 319.77, 355.30, 319.77),
(504, 101, NULL, 2, 'Velosef 500mg Cap', NULL, '389V', '2028-09-29', 2, 0, 603.17, 603.17, 0.00, 5.00, 0.00, 1146.02, 603.17, 1146.02),
(505, 101, NULL, 65, 'Tenormin 50mg Tab', NULL, '264D019', '2028-09-30', 2, 0, 354.02, 354.02, 0.00, 3.00, 0.00, 686.80, 354.02, 686.80),
(506, 101, NULL, 97, 'Magnett 100/5ml Syp', NULL, 'DEFAULT', '2028-10-02', 2, 0, 242.25, 242.25, 0.00, 2.00, 0.00, 474.81, 242.25, 474.81),
(507, 101, NULL, 144, 'Treviamet 50/1000 tab 14s', NULL, 'F08183', '2028-10-02', 1, 0, 999.60, 999.60, 0.00, 1.00, 0.00, 989.60, 999.60, 989.60),
(508, 101, NULL, 28, 'Betaderm Cream 15g', NULL, 'JU014M', '2028-09-30', 3, 0, 61.20, 61.20, 0.00, 3.00, 0.00, 178.09, 61.20, 178.09),
(509, 101, NULL, 38, 'Betnovate N Cream', NULL, '5M7E', '2028-09-30', 4, 0, 153.53, 153.53, 0.00, 5.00, 0.00, 583.41, 153.53, 583.41),
(510, 101, NULL, 147, 'Surbex Z Tab', NULL, '872487XV', '2028-10-06', 3, 0, 433.50, 433.50, 0.00, 1.00, 0.00, 1287.50, 433.50, 1287.50),
(511, 102, NULL, 64, 'Tenormin 25mg Tab', NULL, '263D005', '2028-09-30', 1, 0, 201.02, 201.02, 0.00, 3.00, 0.00, 194.99, 201.02, 194.99),
(512, 102, NULL, 65, 'Tenormin 50mg Tab', NULL, '264D019', '2028-09-30', 1, 0, 354.02, 354.02, 0.00, 3.00, 0.00, 343.40, 354.02, 343.40),
(513, 102, NULL, 75, 'ECP Tab', NULL, '', NULL, 3, 0, 24.94, 24.94, 0.00, 1.00, 0.00, 74.07, 24.94, 74.07),
(514, 102, NULL, 49, 'Combivair 400mg Cap', NULL, '260325', '2028-09-30', 1, 0, 619.82, 619.82, 0.00, 2.00, 0.00, 607.42, 619.82, 607.42),
(515, 102, NULL, 111, 'Tobra E/D', NULL, 'TBD396', '2028-10-02', 2, 0, 191.25, 191.25, 0.00, 5.00, 0.00, 363.38, 191.25, 363.38),
(516, 102, NULL, 11, 'Empaa 10mg Tab 28s', NULL, '997', '2028-09-29', 1, 0, 833.00, 833.00, 0.00, 2.00, 0.00, 816.34, 833.00, 816.34),
(517, 102, NULL, 41, 'Augmentin DS 312.5/5ml Syp', NULL, 'AH4S', '2028-09-30', 1, 0, 531.57, 531.57, 0.00, 5.00, 0.00, 504.99, 531.57, 504.99),
(518, 103, NULL, 61, 'Fusiderm H Cream', NULL, 'F107', '2028-09-29', 2, 0, 355.30, 355.30, 0.00, 10.00, 0.00, 639.54, 355.30, 639.54),
(519, 103, NULL, 132, 'Atorva 20mg Tab 20s', NULL, 'R14AE', '2028-10-02', 1, 0, 1124.55, 1124.55, 0.00, 10.00, 0.00, 1012.10, 1124.55, 1012.10),
(520, 103, NULL, 57, 'Methix Tab 20s', NULL, 'PFT2016', '2028-10-07', 1, 0, 1088.00, 1088.00, 0.00, 5.00, 0.00, 1033.60, 1088.00, 1033.60),
(521, 104, NULL, 53, 'Rovista 20mg Tab', NULL, 'F07059', '2028-10-01', 1, 0, 1713.60, 1713.60, 0.00, 1.00, 0.00, 1696.46, 1713.60, 1696.46),
(522, 104, NULL, 14, 'Azomax 250mg Cap 12s', NULL, 'N7522', '2028-09-30', 1, 0, 675.19, 675.19, 0.00, 1.00, 0.00, 668.44, 675.19, 668.44),
(523, 104, NULL, 94, 'Sita Met 50/500mg Tab 14s', NULL, 'EV131', '2028-10-02', 2, 0, 599.25, 599.25, 0.00, 2.00, 0.00, 1174.53, 599.25, 1174.53),
(524, 104, NULL, 96, 'Nuberol Tab 100s', NULL, 'CEH097', '2028-10-02', 1, 0, 722.33, 722.33, 0.00, 1.00, 0.00, 715.11, 722.33, 715.11),
(525, 104, NULL, 141, 'leflox 250mg Tab', NULL, 'F01171', '2028-10-02', 2, 0, 578.00, 578.00, 0.00, 1.00, 0.00, 1144.44, 578.00, 1144.44),
(526, 104, NULL, 102, 'Lophos Tab 100s', NULL, '864RA', '2028-10-02', 1, 0, 654.34, 654.34, 0.00, 4.00, 0.00, 628.17, 654.34, 628.17),
(527, 104, NULL, 110, 'Minoxin Plus 5% Sol 60ml', NULL, '082126', '2028-10-02', 1, 0, 930.75, 930.75, 0.00, 5.00, 0.00, 884.21, 930.75, 884.21),
(528, 104, NULL, 10, 'Ulsanic Syp 120ml', NULL, '261089', '2028-09-29', 1, 0, 351.48, 351.48, 0.00, 2.00, 0.00, 344.45, 351.48, 344.45),
(529, 104, NULL, 11, 'Empaa 10mg Tab 28s', NULL, '997', '2028-09-29', 1, 0, 833.00, 833.00, 0.00, 2.00, 0.00, 816.34, 833.00, 816.34),
(530, 104, NULL, 147, 'Surbex Z Tab', NULL, '872487XV', '2028-10-06', 5, 0, 433.50, 433.50, 0.00, 1.00, 0.00, 2145.83, 433.50, 2145.83),
(531, 105, NULL, 79, 'Zodip Tab', NULL, '558', '2028-10-02', 2, 0, 74.86, 74.86, 0.00, 1.00, 0.00, 148.22, 74.86, 148.22),
(532, 105, NULL, 39, 'Calpol 6+ Syp 90ml', NULL, '588C', '2028-09-30', 1, 0, 101.48, 101.48, 0.00, 5.00, 0.00, 96.41, 101.48, 96.41),
(533, 105, NULL, 38, 'Betnovate N Cream', NULL, '5M7E', '2028-09-30', 2, 0, 153.53, 153.53, 0.00, 5.00, 0.00, 291.71, 153.53, 291.71),
(534, 105, NULL, 35, 'Canderel 18mg Tab 100s', NULL, 'ACH047', '2028-09-29', 2, 0, 236.02, 236.02, 0.00, 10.00, 0.00, 424.84, 236.02, 424.84),
(535, 105, NULL, 7, 'Novidat 250mg Tab', NULL, '138P020', '2028-09-29', 1, 0, 233.75, 233.75, 0.00, 1.00, 0.00, 231.41, 233.75, 231.41),
(536, 105, NULL, 14, 'Azomax 250mg Cap 12s', NULL, 'N7522', '2028-09-30', 1, 0, 675.19, 675.19, 0.00, 1.00, 0.00, 668.44, 675.19, 668.44),
(537, 105, NULL, 72, 'Cardura 2mg Tab', NULL, 'N7325', '2028-10-02', 1, 0, 676.86, 676.86, 0.00, 1.00, 0.00, 670.09, 676.86, 670.09),
(538, 106, NULL, 18, 'Famila 28F 3Cycle Tab', NULL, 'K325', '2028-09-30', 2, 0, 127.39, 127.39, 0.00, 2.00, 0.00, 249.68, 127.39, 249.68),
(539, 106, NULL, 66, 'Tenormin 100mg Tab', NULL, '265D003', '2028-09-30', 1, 0, 540.40, 540.40, 0.00, 3.00, 0.00, 524.19, 540.40, 524.19),
(540, 106, NULL, 17, 'ST.MOM 200mg Tab 10s', NULL, '814', '2028-09-30', 3, 0, 169.60, 169.60, 0.00, 2.00, 0.00, 498.62, 169.60, 498.62),
(541, 106, NULL, 123, 'Xavor DIU 50mg Tab', NULL, '252394', '2028-10-02', 1, 0, 233.43, 233.43, 0.00, 2.00, 0.00, 228.76, 233.43, 228.76),
(542, 106, NULL, 9, 'Kestine 10mg Tab 14s', NULL, '261322', '2028-09-29', 2, 0, 252.71, 252.71, 0.00, 2.00, 0.00, 495.31, 252.71, 495.31),
(543, 106, NULL, 147, 'Surbex Z Tab', NULL, '872487XV', '2028-10-06', 7, 0, 433.50, 433.50, 0.00, 1.00, 0.00, 3004.16, 433.50, 3004.16),
(544, 107, NULL, 78, 'Zafnol Tab', NULL, 'DEFAULT', '2028-10-02', 5, 0, 106.25, 106.25, 0.00, 1.00, 0.00, 525.94, 106.25, 525.94),
(545, 107, NULL, 79, 'Zodip Tab', NULL, '558', '2028-10-02', 5, 0, 74.86, 74.86, 0.00, 1.00, 0.00, 370.56, 74.86, 370.56),
(546, 107, NULL, 86, 'Lice -O-Nil Cream', NULL, '(10) 3918', '2028-10-02', 3, 0, 212.50, 212.50, 0.00, 3.00, 0.00, 618.38, 212.50, 618.38),
(547, 107, NULL, 42, 'Augmentin  156.25/5ml Syp', NULL, '8N4X', '2028-09-30', 2, 0, 322.69, 322.69, 0.00, 5.00, 0.00, 613.11, 322.69, 613.11);

-- --------------------------------------------------------

--
-- Table structure for table `sale_returns`
--

CREATE TABLE `sale_returns` (
  `id` int NOT NULL,
  `return_no` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `sale_id` int DEFAULT NULL,
  `invoice_id` int DEFAULT NULL,
  `return_date` date NOT NULL,
  `customer_id` int NOT NULL,
  `refund_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'Credit Note',
  `total_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `deduction_amount` decimal(12,2) DEFAULT '0.00',
  `net_refund_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `reason` text COLLATE utf8mb4_general_ci,
  `payment_method` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cash_account_id` int DEFAULT NULL,
  `bank_account_id` int DEFAULT NULL,
  `status` enum('Completed','Pending','Cancelled') COLLATE utf8mb4_general_ci DEFAULT 'Completed',
  `created_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_returns`
--

INSERT INTO `sale_returns` (`id`, `return_no`, `sale_id`, `invoice_id`, `return_date`, `customer_id`, `refund_type`, `total_amount`, `deduction_amount`, `net_refund_amount`, `reason`, `payment_method`, `cash_account_id`, `bank_account_id`, `status`, `created_by`) VALUES
(1, 'SRTN-2026-0001', 1, NULL, '2026-09-29', 1, 'Deduct Balance', 580.89, 0.00, 580.89, '', NULL, NULL, NULL, 'Completed', 1),
(2, 'SRTN-2026-0002', 2, NULL, '2026-09-29', 1, 'Deduct Balance', 954.51, 0.00, 954.51, '', NULL, NULL, NULL, 'Completed', 1),
(3, 'SRTN-2026-0003', 3, NULL, '2026-10-01', 3, 'Deduct Balance', 534.42, 0.00, 534.42, '', NULL, NULL, NULL, 'Completed', 1),
(4, 'SRTN-2026-0004', 4, NULL, '2026-10-01', 3, 'Deduct Balance', 1532.78, 0.00, 1532.78, '', NULL, NULL, NULL, 'Completed', 1),
(5, 'SRTN-2026-0005', 5, NULL, '2026-10-02', 1, 'Deduct Balance', 466.48, 0.00, 466.48, '', NULL, NULL, NULL, 'Completed', 1),
(6, 'SRTN-2026-0006', 6, NULL, '2026-10-02', 1, 'Deduct Balance', 387.60, 0.00, 387.60, '', NULL, NULL, NULL, 'Completed', 1),
(7, 'SRTN-2026-0007', 15, NULL, '2026-10-02', 3, 'Deduct Balance', 534.42, 0.00, 534.42, '', NULL, NULL, NULL, 'Completed', 1),
(8, 'SRTN-2026-0008', 14, NULL, '2026-10-02', 3, 'Deduct Balance', 641.13, 0.00, 641.13, '', NULL, NULL, NULL, 'Completed', 1),
(9, 'SRTN-2026-0009', 13, NULL, '2026-10-02', 3, 'Deduct Balance', 534.42, 0.00, 534.42, '', NULL, NULL, NULL, 'Completed', 1),
(10, 'SRTN-2026-0010', 11, NULL, '2026-10-02', 3, 'Deduct Balance', 466.48, 0.00, 466.48, '', NULL, NULL, NULL, 'Completed', 1),
(11, 'SRTN-2026-0011', 10, NULL, '2026-10-02', 3, 'Deduct Balance', 387.60, 0.00, 387.60, '', NULL, NULL, NULL, 'Completed', 1),
(12, 'SRTN-2026-0012', 9, NULL, '2026-10-02', 3, 'Deduct Balance', 466.48, 0.00, 466.48, '', NULL, NULL, NULL, 'Completed', 1),
(13, 'SRTN-2026-0013', 8, NULL, '2026-10-02', 3, 'Deduct Balance', 466.48, 0.00, 466.48, '', NULL, NULL, NULL, 'Completed', 1),
(14, 'SRTN-2026-0014', 7, NULL, '2026-10-02', 3, 'Deduct Balance', 534.42, 0.00, 534.42, '', NULL, NULL, NULL, 'Completed', 1),
(15, 'SRTN-2026-0015', 12, NULL, '2026-10-02', 3, 'Deduct Balance', 641.13, 0.00, 641.13, '', NULL, NULL, NULL, 'Completed', 1),
(16, 'SRTN-2026-0016', 17, NULL, '2026-10-03', 1, 'Deduct Balance', 466.48, 0.00, 466.48, '', NULL, NULL, NULL, 'Completed', 1),
(17, 'SRTN-2026-0017', 21, NULL, '2026-10-04', 3, 'Deduct Balance', 1364.45, 0.00, 1364.45, '', NULL, NULL, NULL, 'Completed', 1),
(18, 'SRTN-2026-0018', 20, NULL, '2026-10-04', 3, 'Deduct Balance', 147.26, 0.00, 147.26, '', NULL, NULL, NULL, 'Completed', 1),
(19, 'SRTN-2026-0019', 19, NULL, '2026-10-04', 3, 'Deduct Balance', 551.85, 0.00, 551.85, '', NULL, NULL, NULL, 'Completed', 1),
(20, 'SRTN-2026-0020', 18, NULL, '2026-10-04', 1, 'Deduct Balance', 466.48, 0.00, 466.48, '', NULL, NULL, NULL, 'Completed', 1),
(21, 'SRTN-2026-0021', 45, NULL, '2026-10-05', 30, 'Deduct Balance', 752.37, 0.00, 752.37, '', NULL, NULL, NULL, 'Completed', 1),
(22, 'SRTN-2026-0022', 36, NULL, '2026-10-06', 20, 'Deduct Balance', 527.16, 0.00, 527.16, '', NULL, NULL, NULL, 'Completed', 1),
(23, 'SRTN-2026-0023', 38, NULL, '2026-10-06', 22, 'Deduct Balance', 230.14, 0.00, 230.14, '', NULL, NULL, NULL, 'Completed', 1),
(24, 'SRTN-2026-0024', 40, NULL, '2026-10-06', 23, 'Deduct Balance', 3618.13, 0.00, 3618.13, '', NULL, NULL, NULL, 'Completed', 1),
(25, 'SRTN-2026-0025', 52, NULL, '2026-10-06', 36, 'Deduct Balance', 935.81, 0.00, 935.81, '', NULL, NULL, NULL, 'Completed', 1),
(26, 'SRTN-2026-0026', 54, NULL, '2026-10-06', 38, 'Deduct Balance', 1359.93, 0.00, 1359.93, '', NULL, NULL, NULL, 'Completed', 1),
(27, 'SRTN-2026-0027', 42, NULL, '2026-10-06', 28, 'Deduct Balance', 2180.28, 0.00, 2180.28, '', NULL, NULL, NULL, 'Completed', 1),
(28, 'SRTN-2026-0028', 66, NULL, '2026-10-06', 46, 'Deduct Balance', 4285.01, 0.00, 4285.01, '', NULL, NULL, NULL, 'Completed', 1),
(29, 'SRTN-2026-0029', 63, NULL, '2026-10-06', 43, 'Deduct Balance', 3518.22, 0.00, 3518.22, '', NULL, NULL, NULL, 'Completed', 1),
(30, 'SRTN-2026-0030', 71, NULL, '2026-10-06', 43, 'Deduct Balance', 3204.41, 0.00, 3204.41, '', NULL, NULL, NULL, 'Completed', 1),
(31, 'SRTN-2026-0031', 70, NULL, '2026-10-07', 23, 'Deduct Balance', 3979.71, 0.00, 3979.71, '', NULL, NULL, NULL, 'Completed', 1),
(32, 'SRTN-2026-0032', 60, NULL, '2026-10-07', 42, 'Deduct Balance', 1680.41, 0.00, 1680.41, '', NULL, NULL, NULL, 'Completed', 1),
(33, 'SRTN-2026-0033', 74, NULL, '2026-10-08', 50, 'Deduct Balance', 5000.72, 0.00, 5000.72, '', NULL, NULL, NULL, 'Completed', 1),
(34, 'SRTN-2026-0034', 75, NULL, '2026-10-08', 51, 'Deduct Balance', 2531.12, 0.00, 2531.12, '', NULL, NULL, NULL, 'Completed', 1),
(35, 'SRTN-2026-0035', 88, NULL, '2026-10-08', 67, 'Deduct Balance', 10217.98, 0.00, 10217.98, '', NULL, NULL, NULL, 'Completed', 1),
(36, 'SRTN-2026-0036', 76, NULL, '2026-10-08', 52, 'Deduct Balance', 2685.24, 0.00, 2685.24, '', NULL, NULL, NULL, 'Completed', 1),
(37, 'SRTN-2026-0037', 77, NULL, '2026-10-08', 54, 'Deduct Balance', 2978.66, 0.00, 2978.66, '', NULL, NULL, NULL, 'Completed', 1),
(38, 'SRTN-2026-0038', 78, NULL, '2026-10-08', 55, 'Deduct Balance', 5346.23, 0.00, 5346.23, '', NULL, NULL, NULL, 'Completed', 1),
(39, 'SRTN-2026-0039', 79, NULL, '2026-10-08', 56, 'Deduct Balance', 1519.30, 0.00, 1519.30, '', NULL, NULL, NULL, 'Completed', 1),
(40, 'SRTN-2026-0040', 80, NULL, '2026-10-08', 58, 'Deduct Balance', 2218.47, 0.00, 2218.47, '', NULL, NULL, NULL, 'Completed', 1),
(41, 'SRTN-2026-0041', 81, NULL, '2026-10-08', 60, 'Deduct Balance', 2127.99, 0.00, 2127.99, '', NULL, NULL, NULL, 'Completed', 1),
(42, 'SRTN-2026-0042', 82, NULL, '2026-10-08', 61, 'Deduct Balance', 301.72, 0.00, 301.72, '', NULL, NULL, NULL, 'Completed', 1),
(43, 'SRTN-2026-0043', 83, NULL, '2026-10-08', 62, 'Deduct Balance', 5972.21, 0.00, 5972.21, '', NULL, NULL, NULL, 'Completed', 1),
(44, 'SRTN-2026-0044', 84, NULL, '2026-10-08', 63, 'Deduct Balance', 4582.36, 0.00, 4582.36, '', NULL, NULL, NULL, 'Completed', 1),
(45, 'SRTN-2026-0045', 86, NULL, '2026-10-08', 65, 'Deduct Balance', 3049.02, 0.00, 3049.02, '', NULL, NULL, NULL, 'Completed', 1),
(46, 'SRTN-2026-0046', 85, NULL, '2026-10-08', 64, 'Deduct Balance', 1507.19, 0.00, 1507.19, '', NULL, NULL, NULL, 'Completed', 1),
(47, 'SRTN-2026-0047', 87, NULL, '2026-10-08', 66, 'Deduct Balance', 2423.27, 0.00, 2423.27, '', NULL, NULL, NULL, 'Completed', 1),
(48, 'SRTN-2026-0048', 93, NULL, '2026-10-08', 62, 'Deduct Balance', 5972.21, 0.00, 5972.21, '', NULL, NULL, NULL, 'Completed', 1),
(49, 'SRTN-2026-0049', 94, NULL, '2026-10-08', 61, 'Deduct Balance', 291.57, 0.00, 291.57, '', NULL, NULL, NULL, 'Completed', 1),
(50, 'SRTN-2026-0050', 95, NULL, '2026-10-08', 61, 'Deduct Balance', 291.57, 0.00, 291.57, '', NULL, NULL, NULL, 'Completed', 1);

-- --------------------------------------------------------

--
-- Table structure for table `sale_return_items`
--

CREATE TABLE `sale_return_items` (
  `id` int NOT NULL,
  `return_id` int NOT NULL,
  `sale_return_id` int DEFAULT NULL,
  `product_id` int NOT NULL,
  `batch_no` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `unit_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_price` decimal(14,2) NOT NULL DEFAULT '0.00',
  `condition` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'Good'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_return_items`
--

INSERT INTO `sale_return_items` (`id`, `return_id`, `sale_return_id`, `product_id`, `batch_no`, `quantity`, `unit_price`, `total_price`, `condition`) VALUES
(1, 1, 1, 22, NULL, 1, 580.89, 580.89, 'Good / Resalable'),
(2, 2, 2, 22, NULL, 1, 534.42, 534.42, 'Good / Resalable'),
(3, 2, 2, 4, NULL, 1, 420.09, 420.09, 'Good / Resalable'),
(4, 3, 3, 22, NULL, 1, 534.42, 534.42, 'Good / Resalable'),
(5, 4, 4, 42, NULL, 5, 306.56, 1532.78, 'Good / Resalable'),
(6, 5, 5, 80, NULL, 1, 466.48, 466.48, 'Good / Resalable'),
(7, 6, 6, 127, NULL, 1, 387.60, 387.60, 'Good / Resalable'),
(8, 7, 7, 22, NULL, 1, 534.42, 534.42, 'Good / Resalable'),
(9, 8, 8, 99, NULL, 1, 641.13, 641.13, 'Good / Resalable'),
(10, 9, 9, 22, NULL, 1, 534.42, 534.42, 'Good / Resalable'),
(11, 10, 10, 80, NULL, 1, 466.48, 466.48, 'Good / Resalable'),
(12, 11, 11, 127, NULL, 1, 387.60, 387.60, 'Good / Resalable'),
(13, 12, 12, 80, NULL, 1, 466.48, 466.48, 'Good / Resalable'),
(14, 13, 13, 80, NULL, 1, 466.48, 466.48, 'Good / Resalable'),
(15, 14, 14, 22, NULL, 1, 534.42, 534.42, 'Good / Resalable'),
(16, 15, 15, 99, NULL, 1, 641.13, 641.13, 'Good / Resalable'),
(17, 16, 16, 80, NULL, 1, 466.48, 466.48, 'Good / Resalable'),
(18, 17, 17, 98, NULL, 3, 454.82, 1364.45, 'Good / Resalable'),
(19, 18, 18, 74, NULL, 1, 147.26, 147.26, 'Good / Resalable'),
(20, 19, 19, 22, NULL, 1, 551.85, 551.85, 'Good / Resalable'),
(21, 20, 20, 80, NULL, 1, 466.48, 466.48, 'Good / Resalable'),
(22, 21, 21, 48, NULL, 1, 752.37, 752.37, 'Good / Resalable'),
(23, 22, 22, 125, NULL, 1, 345.47, 345.47, 'Good / Resalable'),
(24, 22, 22, 111, NULL, 1, 181.69, 181.69, 'Good / Resalable'),
(25, 23, 23, 112, NULL, 1, 230.14, 230.14, 'Good / Resalable'),
(26, 24, 24, 65, NULL, 2, 343.40, 686.80, 'Good / Resalable'),
(27, 24, 24, 18, NULL, 2, 124.84, 249.68, 'Good / Resalable'),
(28, 24, 24, 17, NULL, 5, 166.21, 831.04, 'Good / Resalable'),
(29, 24, 24, 74, NULL, 1, 147.26, 147.26, 'Good / Resalable'),
(30, 24, 24, 75, NULL, 1, 24.69, 24.69, 'Good / Resalable'),
(31, 24, 24, 96, NULL, 1, 715.11, 715.11, 'Good / Resalable'),
(32, 24, 24, 52, NULL, 1, 751.13, 751.13, 'Good / Resalable'),
(33, 24, 24, 35, NULL, 1, 212.42, 212.42, 'Good / Resalable'),
(34, 25, 25, 10, NULL, 2, 344.45, 688.90, 'Good / Resalable'),
(35, 25, 25, 75, NULL, 10, 24.69, 246.91, 'Good / Resalable'),
(36, 26, 26, 44, NULL, 2, 156.19, 312.38, 'Good / Resalable'),
(37, 26, 26, 13, NULL, 3, 195.76, 587.27, 'Good / Resalable'),
(38, 26, 26, 112, NULL, 2, 230.14, 460.28, 'Good / Resalable'),
(39, 27, 27, 43, NULL, 2, 124.10, 248.20, 'Good / Resalable'),
(40, 27, 27, 42, NULL, 1, 306.56, 306.56, 'Good / Resalable'),
(41, 27, 27, 41, NULL, 1, 504.99, 504.99, 'Good / Resalable'),
(42, 27, 27, 13, NULL, 1, 195.76, 195.76, 'Good / Resalable'),
(43, 27, 27, 16, NULL, 1, 682.52, 682.52, 'Good / Resalable'),
(44, 27, 27, 32, NULL, 2, 121.13, 242.25, 'Good / Resalable'),
(45, 28, 28, 57, NULL, 1, 875.93, 875.93, 'Good / Resalable'),
(46, 28, 28, 9, NULL, 2, 247.66, 495.31, 'Good / Resalable'),
(47, 28, 28, 101, NULL, 2, 453.95, 907.89, 'Good / Resalable'),
(48, 28, 28, 39, NULL, 3, 96.41, 289.22, 'Good / Resalable'),
(49, 28, 28, 147, NULL, 4, 429.17, 1716.66, 'Good / Resalable'),
(50, 29, 29, 63, NULL, 2, 412.25, 824.50, 'Good / Resalable'),
(51, 29, 29, 130, NULL, 2, 426.49, 852.97, 'Good / Resalable'),
(52, 29, 29, 34, NULL, 2, 218.28, 436.55, 'Good / Resalable'),
(53, 29, 29, 19, NULL, 1, 719.44, 719.44, 'Good / Resalable'),
(54, 29, 29, 55, NULL, 1, 684.76, 684.76, 'Good / Resalable'),
(55, 30, 30, 63, NULL, 2, 412.25, 824.50, 'Good / Resalable'),
(56, 30, 30, 130, NULL, 2, 426.49, 852.97, 'Good / Resalable'),
(57, 30, 30, 19, NULL, 1, 719.44, 719.44, 'Good / Resalable'),
(58, 30, 30, 55, NULL, 1, 807.50, 807.50, 'Good / Resalable'),
(59, 31, 31, 66, NULL, 2, 524.19, 1048.38, 'Good / Resalable'),
(60, 31, 31, 18, NULL, 2, 124.84, 249.68, 'Good / Resalable'),
(61, 31, 31, 17, NULL, 5, 166.21, 831.04, 'Good / Resalable'),
(62, 31, 31, 74, NULL, 1, 147.26, 147.26, 'Good / Resalable'),
(63, 31, 31, 75, NULL, 1, 24.69, 24.69, 'Good / Resalable'),
(64, 31, 31, 96, NULL, 1, 715.11, 715.11, 'Good / Resalable'),
(65, 31, 31, 52, NULL, 1, 751.13, 751.13, 'Good / Resalable'),
(66, 31, 31, 35, NULL, 1, 212.42, 212.42, 'Good / Resalable'),
(67, 32, 32, 17, NULL, 2, 166.21, 332.42, 'Good / Resalable'),
(68, 32, 32, 78, NULL, 1, 105.19, 105.19, 'Good / Resalable'),
(69, 32, 32, 86, NULL, 1, 206.13, 206.13, 'Good / Resalable'),
(70, 32, 32, 96, NULL, 1, 715.11, 715.11, 'Good / Resalable'),
(71, 32, 32, 27, NULL, 2, 160.78, 321.56, 'Good / Resalable'),
(72, 33, 33, 18, NULL, 2, 124.84, 249.68, 'Good / Resalable'),
(73, 33, 33, 66, NULL, 1, 524.19, 524.19, 'Good / Resalable'),
(74, 33, 33, 17, NULL, 3, 166.21, 498.62, 'Good / Resalable'),
(75, 33, 33, 123, NULL, 1, 228.76, 228.76, 'Good / Resalable'),
(76, 33, 33, 9, NULL, 2, 247.66, 495.31, 'Good / Resalable'),
(77, 33, 33, 147, NULL, 7, 429.17, 3004.16, 'Good / Resalable'),
(78, 34, 34, 79, NULL, 2, 74.11, 148.22, 'Good / Resalable'),
(79, 34, 34, 39, NULL, 1, 96.41, 96.41, 'Good / Resalable'),
(80, 34, 34, 38, NULL, 2, 145.86, 291.71, 'Good / Resalable'),
(81, 34, 34, 35, NULL, 2, 212.42, 424.84, 'Good / Resalable'),
(82, 34, 34, 7, NULL, 1, 231.41, 231.41, 'Good / Resalable'),
(83, 34, 34, 14, NULL, 1, 668.44, 668.44, 'Good / Resalable'),
(84, 34, 34, 72, NULL, 1, 670.09, 670.09, 'Good / Resalable'),
(85, 35, 35, 53, NULL, 1, 1696.46, 1696.46, 'Good / Resalable'),
(86, 35, 35, 14, NULL, 1, 668.44, 668.44, 'Good / Resalable'),
(87, 35, 35, 94, NULL, 2, 587.27, 1174.53, 'Good / Resalable'),
(88, 35, 35, 96, NULL, 1, 715.11, 715.11, 'Good / Resalable'),
(89, 35, 35, 141, NULL, 2, 572.22, 1144.44, 'Good / Resalable'),
(90, 35, 35, 102, NULL, 1, 628.17, 628.17, 'Good / Resalable'),
(91, 35, 35, 110, NULL, 1, 884.21, 884.21, 'Good / Resalable'),
(92, 35, 35, 10, NULL, 1, 344.45, 344.45, 'Good / Resalable'),
(93, 35, 35, 11, NULL, 1, 816.34, 816.34, 'Good / Resalable'),
(94, 35, 35, 147, NULL, 5, 429.17, 2145.83, 'Good / Resalable'),
(95, 36, 36, 61, NULL, 2, 319.77, 639.54, 'Good / Resalable'),
(96, 36, 36, 132, NULL, 1, 1012.10, 1012.10, 'Good / Resalable'),
(97, 36, 36, 57, NULL, 1, 1033.60, 1033.60, 'Good / Resalable'),
(98, 37, 37, 64, NULL, 1, 194.99, 194.99, 'Good / Resalable'),
(99, 37, 37, 65, NULL, 1, 343.40, 343.40, 'Good / Resalable'),
(100, 37, 37, 75, NULL, 6, 24.69, 148.14, 'Good / Resalable'),
(101, 37, 37, 49, NULL, 1, 607.42, 607.42, 'Good / Resalable'),
(102, 37, 37, 111, NULL, 2, 181.69, 363.38, 'Good / Resalable'),
(103, 37, 37, 11, NULL, 1, 816.34, 816.34, 'Good / Resalable'),
(104, 37, 37, 41, NULL, 1, 504.99, 504.99, 'Good / Resalable'),
(105, 38, 38, 2, NULL, 2, 573.01, 1146.02, 'Good / Resalable'),
(106, 38, 38, 65, NULL, 2, 343.40, 686.80, 'Good / Resalable'),
(107, 38, 38, 97, NULL, 2, 237.41, 474.81, 'Good / Resalable'),
(108, 38, 38, 144, NULL, 1, 989.60, 989.60, 'Good / Resalable'),
(109, 38, 38, 28, NULL, 3, 59.36, 178.09, 'Good / Resalable'),
(110, 38, 38, 38, NULL, 4, 145.85, 583.41, 'Good / Resalable'),
(111, 38, 38, 147, NULL, 3, 429.17, 1287.50, 'Good / Resalable'),
(112, 39, 39, 9, NULL, 1, 247.66, 247.66, 'Good / Resalable'),
(113, 39, 39, 10, NULL, 1, 344.45, 344.45, 'Good / Resalable'),
(114, 39, 39, 49, NULL, 1, 607.42, 607.42, 'Good / Resalable'),
(115, 39, 39, 61, NULL, 1, 319.77, 319.77, 'Good / Resalable'),
(116, 40, 40, 2, NULL, 2, 573.01, 1146.02, 'Good / Resalable'),
(117, 40, 40, 86, NULL, 1, 206.13, 206.13, 'Good / Resalable'),
(118, 40, 40, 45, NULL, 2, 433.16, 866.32, 'Good / Resalable'),
(119, 41, 41, 78, NULL, 5, 105.19, 525.94, 'Good / Resalable'),
(120, 41, 41, 79, NULL, 5, 74.11, 370.56, 'Good / Resalable'),
(121, 41, 41, 86, NULL, 3, 206.13, 618.38, 'Good / Resalable'),
(122, 41, 41, 42, NULL, 2, 306.56, 613.11, 'Good / Resalable'),
(123, 42, 42, 75, NULL, 4, 24.69, 98.76, 'Good / Resalable'),
(124, 42, 42, 39, NULL, 2, 101.48, 202.96, 'Good / Resalable'),
(125, 43, 43, 10, NULL, 3, 344.45, 1033.35, 'Good / Resalable'),
(126, 43, 43, 55, NULL, 2, 807.50, 1615.00, 'Good / Resalable'),
(127, 43, 43, 65, NULL, 2, 343.40, 686.80, 'Good / Resalable'),
(128, 43, 43, 18, NULL, 2, 124.84, 249.68, 'Good / Resalable'),
(129, 43, 43, 92, NULL, 1, 417.16, 417.16, 'Good / Resalable'),
(130, 43, 43, 93, NULL, 1, 354.03, 354.03, 'Good / Resalable'),
(131, 43, 43, 45, NULL, 1, 433.16, 433.16, 'Good / Resalable'),
(132, 43, 43, 103, NULL, 1, 528.05, 528.05, 'Good / Resalable'),
(133, 43, 43, 112, NULL, 1, 230.14, 230.14, 'Good / Resalable'),
(134, 43, 43, 35, NULL, 2, 212.42, 424.84, 'Good / Resalable'),
(135, 44, 44, 15, NULL, 2, 461.74, 923.47, 'Good / Resalable'),
(136, 44, 44, 51, NULL, 2, 447.79, 895.58, 'Good / Resalable'),
(137, 44, 44, 5, NULL, 1, 568.01, 568.01, 'Good / Resalable'),
(138, 44, 44, 28, NULL, 5, 59.36, 296.82, 'Good / Resalable'),
(139, 44, 44, 29, NULL, 3, 96.90, 290.70, 'Good / Resalable'),
(140, 44, 44, 27, NULL, 10, 160.78, 1607.78, 'Good / Resalable'),
(141, 45, 45, 101, NULL, 2, 453.95, 907.89, 'Good / Resalable'),
(142, 45, 45, 53, NULL, 1, 1696.46, 1696.46, 'Good / Resalable'),
(143, 45, 45, 79, NULL, 6, 74.11, 444.67, 'Good / Resalable'),
(144, 46, 46, 123, NULL, 5, 228.76, 1143.81, 'Good / Resalable'),
(145, 46, 46, 111, NULL, 2, 181.69, 363.38, 'Good / Resalable'),
(146, 47, 47, 74, NULL, 1, 147.26, 147.26, 'Good / Resalable'),
(147, 47, 47, 94, NULL, 3, 587.27, 1761.80, 'Good / Resalable'),
(148, 47, 47, 122, NULL, 2, 257.11, 514.21, 'Good / Resalable'),
(149, 48, 48, 10, NULL, 3, 344.45, 1033.35, 'Good / Resalable'),
(150, 48, 48, 55, NULL, 2, 807.50, 1615.00, 'Good / Resalable'),
(151, 48, 48, 65, NULL, 2, 343.40, 686.80, 'Good / Resalable'),
(152, 48, 48, 18, NULL, 2, 124.84, 249.68, 'Good / Resalable'),
(153, 48, 48, 92, NULL, 1, 417.16, 417.16, 'Good / Resalable'),
(154, 48, 48, 93, NULL, 1, 354.03, 354.03, 'Good / Resalable'),
(155, 48, 48, 45, NULL, 1, 433.16, 433.16, 'Good / Resalable'),
(156, 48, 48, 103, NULL, 1, 528.05, 528.05, 'Good / Resalable'),
(157, 48, 48, 112, NULL, 1, 230.14, 230.14, 'Good / Resalable'),
(158, 48, 48, 35, NULL, 2, 212.42, 424.84, 'Good / Resalable'),
(159, 49, 49, 75, NULL, 4, 24.69, 98.76, 'Good / Resalable'),
(160, 49, 49, 39, NULL, 2, 96.41, 192.81, 'Good / Resalable'),
(161, 50, 50, 75, NULL, 4, 24.69, 98.76, 'Good / Resalable'),
(162, 50, 50, 39, NULL, 2, 96.41, 192.81, 'Good / Resalable');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int NOT NULL,
  `employee_code` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `role` varchar(100) COLLATE utf8mb4_general_ci DEFAULT 'Staff',
  `designation` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cnic` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `salary` decimal(12,2) DEFAULT '0.00',
  `commission_rate` decimal(5,2) DEFAULT '0.00',
  `address` text COLLATE utf8mb4_general_ci,
  `joining_date` date DEFAULT NULL,
  `status` enum('Active','Inactive') COLLATE utf8mb4_general_ci DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int NOT NULL,
  `supplier_code` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `company_name` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `phone` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `mobile` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ntn_strn` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_general_ci,
  `bank_details` text COLLATE utf8mb4_general_ci,
  `credit_days` int DEFAULT '30',
  `opening_balance` decimal(14,2) DEFAULT '0.00',
  `current_balance` decimal(14,2) DEFAULT '0.00',
  `status` enum('Active','Inactive') COLLATE utf8mb4_general_ci DEFAULT 'Active',
  `created_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `supplier_code`, `name`, `company_name`, `phone`, `mobile`, `email`, `ntn_strn`, `address`, `bank_details`, `credit_days`, `opening_balance`, `current_balance`, `status`, `created_at`) VALUES
(1, 'SUP-0001', 'Nayab Shah', 'Pharmacy', '03244004056', NULL, '', NULL, 'Mohny Road Lahore', NULL, 0, 0.00, 2499870.21, 'Active', NULL),
(2, 'SUP-0002', 'Buraq Pharma', 'Lohari', '0322-7957955', NULL, '', NULL, 'Lohari', NULL, 0, 0.00, 581039.73, 'Active', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `supplier_ledgers`
--

CREATE TABLE `supplier_ledgers` (
  `id` int NOT NULL,
  `supplier_id` int NOT NULL,
  `transaction_date` date NOT NULL,
  `transaction_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `reference_no` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `debit_amount` decimal(14,2) DEFAULT '0.00',
  `credit_amount` decimal(14,2) DEFAULT '0.00',
  `running_balance` decimal(14,2) NOT NULL,
  `description` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier_ledgers`
--

INSERT INTO `supplier_ledgers` (`id`, `supplier_id`, `transaction_date`, `transaction_type`, `reference_no`, `debit_amount`, `credit_amount`, `running_balance`, `description`) VALUES
(415, 2, '2026-10-02', 'Purchase Bill', 'PUR-2026-0025', 0.00, 558410.27, 558410.27, 'Purchase Bill #PUR-2026-0025'),
(416, 2, '2026-10-02', 'Purchase Bill', 'PUR-2026-0027', 0.00, 3140.83, 561551.10, 'Purchase Bill #PUR-2026-0027'),
(417, 2, '2026-10-04', 'Purchase Bill', 'PUR-2026-0029', 0.00, 19488.63, 581039.73, 'Purchase Bill #PUR-2026-0029'),
(473, 1, '2026-09-29', 'Purchase Bill', 'PUR-2026-0001', 0.00, 820429.52, 820429.52, 'Purchase Bill #PUR-2026-0001'),
(474, 1, '2026-09-29', 'Purchase Bill', 'PUR-2026-0002', 0.00, 15636.60, 836066.12, 'Purchase Bill #PUR-2026-0002'),
(475, 1, '2026-09-29', 'Purchase Bill', 'PUR-2026-0003', 0.00, 5576.34, 841642.46, 'Purchase Bill #PUR-2026-0003'),
(476, 1, '2026-09-29', 'Purchase Bill', 'PUR-2026-0004', 0.00, 36135.40, 877777.86, 'Purchase Bill #PUR-2026-0004'),
(477, 1, '2026-09-29', 'Purchase Bill', 'PUR-2026-0005', 0.00, 10428.02, 888205.88, 'Purchase Bill #PUR-2026-0005'),
(478, 1, '2026-09-29', 'Purchase Bill', 'PUR-2026-0006', 0.00, 7435.98, 895641.86, 'Purchase Bill #PUR-2026-0006'),
(479, 1, '2026-09-29', 'Purchase Bill', 'PUR-2026-0007', 0.00, 7435.98, 903077.84, 'Purchase Bill #PUR-2026-0007'),
(480, 1, '2026-09-29', 'Purchase Bill', 'PUR-2026-0008', 0.00, 28090.37, 931168.21, 'Purchase Bill #PUR-2026-0008'),
(481, 1, '2026-09-29', 'Purchase Bill', 'PUR-2026-0009', 0.00, 8211.00, 939379.21, 'Purchase Bill #PUR-2026-0009'),
(482, 1, '2026-09-29', 'Purchase Bill', 'PUR-2026-0010', 0.00, 29143.81, 968523.02, 'Purchase Bill #PUR-2026-0010'),
(483, 1, '2026-09-29', 'Purchase Bill', 'PUR-2026-0011', 0.00, 5259.37, 973782.39, 'Purchase Bill #PUR-2026-0011'),
(484, 1, '2026-09-29', 'Purchase Bill', 'PUR-2026-0012', 0.00, 11220.00, 985002.39, 'Purchase Bill #PUR-2026-0012'),
(485, 1, '2026-09-29', 'Purchase Bill', 'PUR-2026-0013', 0.00, 184836.93, 1169839.32, 'Purchase Bill #PUR-2026-0013'),
(486, 1, '2026-09-29', 'Purchase Bill', 'PUR-2026-0014', 0.00, 59583.31, 1229422.63, 'Purchase Bill #PUR-2026-0014'),
(487, 1, '2026-09-30', 'Purchase Bill', 'PUR-2026-0015', 0.00, 139471.41, 1368894.04, 'Purchase Bill #PUR-2026-0015'),
(488, 1, '2026-09-30', 'Purchase Bill', 'PUR-2026-0016', 0.00, 89352.70, 1458246.74, 'Purchase Bill #PUR-2026-0016'),
(489, 1, '2026-09-30', 'Purchase Bill', 'PUR-2026-0017', 0.00, 35077.29, 1493324.03, 'Purchase Bill #PUR-2026-0017'),
(490, 1, '2026-09-30', 'Purchase Bill', 'PUR-2026-0018', 0.00, 84738.36, 1578062.39, 'Purchase Bill #PUR-2026-0018'),
(491, 1, '2026-09-30', 'Purchase Bill', 'PUR-2026-0019', 0.00, 447970.77, 2026033.16, 'Purchase Bill #PUR-2026-0019'),
(492, 1, '2026-09-30', 'Purchase Bill', 'PUR-2026-0020', 0.00, 28593.40, 2054626.56, 'Purchase Bill #PUR-2026-0020'),
(493, 1, '2026-09-30', 'Purchase Bill', 'PUR-2026-0021', 0.00, 15593.76, 2070220.32, 'Purchase Bill #PUR-2026-0021'),
(494, 1, '2026-09-30', 'Purchase Bill', 'PUR-2026-0022', 0.00, 238564.40, 2308784.72, 'Purchase Bill #PUR-2026-0022'),
(495, 1, '2026-10-01', 'Purchase Bill', 'PUR-2026-0023', 0.00, 16676.66, 2325461.38, 'Purchase Bill #PUR-2026-0023'),
(496, 1, '2026-10-01', 'Purchase Bill', 'PUR-2026-0024', 0.00, 64487.28, 2389948.66, 'Purchase Bill #PUR-2026-0024'),
(497, 1, '2026-10-02', 'Purchase Bill', 'PUR-2026-0026', 0.00, 30169.86, 2420118.52, 'Purchase Bill #PUR-2026-0026'),
(498, 1, '2026-10-02', 'Purchase Bill', 'PUR-2026-0028', 0.00, 39790.13, 2459908.65, 'Purchase Bill #PUR-2026-0028'),
(499, 1, '2026-10-06', 'Purchase Bill', 'PUR-2026-0030', 0.00, 39015.00, 2498923.65, 'Purchase Bill #PUR-2026-0030'),
(500, 1, '2026-10-07', 'Purchase Bill', 'PUR-2026-0031', 0.00, 946.56, 2499870.21, 'Purchase Bill #PUR-2026-0031'),
(501, 1, '2026-10-07', 'Purchase Bill', 'PUR-2026-0032', 0.00, 0.00, 2499870.21, 'Purchase Bill #PUR-2026-0032');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_payments`
--

CREATE TABLE `supplier_payments` (
  `id` int NOT NULL,
  `voucher_no` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `payment_date` date NOT NULL,
  `supplier_id` int NOT NULL,
  `purchase_id` int DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `payment_method` enum('Cash','Bank Transfer') COLLATE utf8mb4_general_ci DEFAULT 'Cash',
  `bank_account_id` int DEFAULT NULL,
  `discount_received` decimal(12,2) DEFAULT '0.00',
  `remarks` text COLLATE utf8mb4_general_ci,
  `created_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `udhaar_book`
--

CREATE TABLE `udhaar_book` (
  `id` int NOT NULL,
  `party_name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `invoice_no` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `invoice_id` int DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `type` enum('Payable','Receivable') COLLATE utf8mb4_general_ci DEFAULT 'Receivable',
  `credit_date` date NOT NULL,
  `description` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `udhaar_book`
--

INSERT INTO `udhaar_book` (`id`, `party_name`, `invoice_no`, `invoice_id`, `amount`, `type`, `credit_date`, `description`) VALUES
(22, 'Mughal Pharmacy', 'INV-0001', 22, 5062.63, '', '2026-10-05', 'Sales Invoice #INV-0001 (Credit Sale)'),
(23, 'Saeeda M/S', 'INV-0002', 23, 447.79, '', '2026-10-05', 'Sales Invoice #INV-0002 (Credit Sale)'),
(24, 'Moon M/S', 'INV-0003', 24, 2159.77, '', '2026-10-05', 'Sales Invoice #INV-0003 (Credit Sale)'),
(25, 'Medisafe Pharmacy', 'INV-0004', 25, 2289.77, '', '2026-10-05', 'Sales Invoice #INV-0004 (Credit Sale)'),
(26, 'New Green Pharmacy', 'INV-0005', 26, 6084.26, '', '2026-10-05', 'Sales Invoice #INV-0005 (Credit Sale)'),
(27, 'New Malik medical store', 'INV-0006', 27, 2606.20, '', '2026-10-05', 'Sales Invoice #INV-0006 (Credit Sale)'),
(28, 'Malik M/S', 'INV-0007', 28, 5626.65, '', '2026-10-05', 'Sales Invoice #INV-0007 (Credit Sale)'),
(29, 'Haider healthcare pharmacy', 'INV-0008', 29, 3591.47, '', '2026-10-05', 'Sales Invoice #INV-0008 (Credit Sale)'),
(30, 'Qartaba M/S', 'INV-0009', 30, 1461.51, '', '2026-10-05', 'Sales Invoice #INV-0009 (Credit Sale)'),
(31, 'Sheikh M/S', 'INV-0010', 31, 1962.97, '', '2026-10-05', 'Sales Invoice #INV-0010 (Credit Sale)'),
(32, 'Haider Pharmacy', NULL, 32, 15566.11, '', '2026-10-05', 'Sales Invoice # (Credit Sale)'),
(33, 'Hijab medical store', 'INV-0012', 33, 13326.46, '', '2026-10-05', 'Sales Invoice #INV-0012 (Credit Sale)'),
(34, 'Sohail pharmacy', 'INV-0013', 34, 2564.49, '', '2026-10-05', 'Sales Invoice #INV-0013 (Credit Sale)'),
(35, 'New Shahid medical stire', 'INV-0014', 35, 2006.21, '', '2026-10-05', 'Sales Invoice #INV-0014 (Credit Sale)'),
(36, 'Unique pharmacy', 'INV-0015', 36, 1892.20, '', '2026-10-05', 'Sales Invoice #INV-0015 (Credit Sale)'),
(37, 'Raza medical and gernal store', 'INV-0016', 37, 2608.90, '', '2026-10-05', 'Sales Invoice #INV-0016 (Credit Sale)'),
(38, 'Javeed sons pharmacy', 'INV-0017', 38, 1071.00, '', '2026-10-05', 'Sales Invoice #INV-0017 (Credit Sale)'),
(39, 'Javeed sons pharmacy', 'INV-0018', 39, 1901.51, '', '2026-10-05', 'Sales Invoice #INV-0018 (Credit Sale)'),
(40, 'Waseem pharmacy', 'INV-0019', 40, 3618.13, '', '2026-10-05', 'Sales Invoice #INV-0019 (Credit Sale)'),
(41, 'Hafiz Pharmacy', 'INV-0020', 41, 2871.64, '', '2026-10-05', 'Sales Invoice #INV-0020 (Credit Sale)'),
(42, 'Khan M/S', NULL, 42, 2180.27, '', '2026-10-05', 'Sales Invoice # (Credit Sale)'),
(43, 'Khadam and sons pharmacy', 'INV-0022', 43, 6327.13, '', '2026-10-05', 'Sales Invoice #INV-0022 (Credit Sale)'),
(44, 'Smart Care Pharmacy', 'INV-0023', 44, 2702.14, '', '2026-10-05', 'Sales Invoice #INV-0023 (Credit Sale)'),
(45, 'Nazir Sons Pharmacy', 'INV-0024', 45, 752.37, '', '2026-10-05', 'Sales Invoice #INV-0024 (Credit Sale)'),
(46, 'Saad Medical Store', 'INV-0025', 46, 2800.02, '', '2026-10-05', 'Sales Invoice #INV-0025 (Credit Sale)'),
(47, 'Fiazan Pharmacy', 'INV-0026', 48, 559.80, '', '2026-10-05', 'Sales Invoice #INV-0026 (Credit Sale)'),
(48, 'Medicare pharmacy', 'INV-0027', 49, 7825.34, '', '2026-10-05', 'Sales Invoice #INV-0027 (Credit Sale)'),
(49, 'Mediprime Pharmacy', 'INV-0028', 50, 2562.45, '', '2026-10-05', 'Sales Invoice #INV-0028 (Credit Sale)'),
(50, 'Bismillah Pharmacy', 'INV-0029', 51, 1560.69, '', '2026-10-05', 'Sales Invoice #INV-0029 (Credit Sale)'),
(51, 'Riaz M/S', 'INV-0030', 52, 935.81, '', '2026-10-05', 'Sales Invoice #INV-0030 (Credit Sale)'),
(52, 'New Mughal Pharmacy', 'INV-0031', 53, 2903.31, '', '2026-10-05', 'Sales Invoice #INV-0031 (Credit Sale)'),
(53, 'Al Shifa Pharmacy', 'INV-0032', 54, 1359.92, '', '2026-10-05', 'Sales Invoice #INV-0032 (Credit Sale)'),
(54, 'Kamran M/S', 'INV-0033', 55, 2375.94, '', '2026-10-05', 'Sales Invoice #INV-0033 (Credit Sale)'),
(55, 'Liaquit Sons Pharmacy', 'INV-0034', 56, 15777.28, '', '2026-10-05', 'Sales Invoice #INV-0034 (Credit Sale)'),
(56, 'Nazir Sons Pharmacy', 'INV-0035', 57, 809.00, '', '2026-10-05', 'Sales Invoice #INV-0035 (Credit Sale)'),
(57, 'Bismillah medical store', 'INV-0036', 58, 2061.36, '', '2026-10-06', 'Sales Invoice #INV-0036 (Credit Sale)'),
(58, 'Imran medical store', 'INV-0037', 59, 2866.29, '', '2026-10-06', 'Sales Invoice #INV-0037 (Credit Sale)'),
(59, 'Tayyab pharmacy', 'INV-0038', 60, 1680.39, '', '2026-10-06', 'Sales Invoice #INV-0038 (Credit Sale)'),
(60, 'Haider Pharmacy', 'INV-0039', 61, 1962.02, '', '2026-10-06', 'Sales Invoice #INV-0039 (Credit Sale)'),
(61, 'Haider Pharmacy', 'INV-0040', 62, 824.79, '', '2026-10-06', 'Sales Invoice #INV-0040 (Credit Sale)'),
(62, 'Lassni medical store immima colony', 'INV-0041', 63, 3518.22, '', '2026-10-06', 'Sales Invoice #INV-0041 (Credit Sale)'),
(63, 'Pak online pharmacy imamia colony', 'INV-0042', 64, 2981.37, '', '2026-10-06', 'Sales Invoice #INV-0042 (Credit Sale)'),
(64, 'Green pharmacy imamia colony', 'INV-0043', 65, 2469.16, '', '2026-10-06', 'Sales Invoice #INV-0043 (Credit Sale)'),
(65, 'Al syed pharmacy imamia colony', 'INV-0044', 66, 4285.01, '', '2026-10-06', 'Sales Invoice #INV-0044 (Credit Sale)'),
(66, 'H fareed sons pharmacy', 'INV-0045', 67, 1030.91, '', '2026-10-06', 'Sales Invoice #INV-0045 (Credit Sale)'),
(67, 'Gravity Plus pharmacy feroz wala', 'INV-0046', 68, 886.19, '', '2026-10-06', 'Sales Invoice #INV-0046 (Credit Sale)'),
(68, 'Nouman Pharmacy', 'INV-0047', 69, 8295.66, '', '2026-10-06', 'Sales Invoice #INV-0047 (Credit Sale)'),
(69, 'Waseem pharmacy', 'INV-0048', 70, 3979.71, '', '2026-10-06', 'Sales Invoice #INV-0048 (Credit Sale)'),
(70, 'Lassni medical store immima colony', 'INV-0049', 71, 3204.41, '', '2026-10-06', 'Sales Invoice #INV-0049 (Credit Sale)'),
(71, 'Al syed pharmacy imamia colony', 'INV-0050', 72, 4442.68, '', '2026-10-06', 'Sales Invoice #INV-0050 (Credit Sale)'),
(72, 'Lassni medical store immima colony', 'INV-0051', 73, 3640.96, '', '2026-10-06', 'Sales Invoice #INV-0051 (Credit Sale)'),
(73, 'QS Medical & Cosmetics Store', 'INV-0052', 74, 5000.72, '', '2026-10-07', 'Sales Invoice #INV-0052 (Credit Sale)'),
(74, 'Makhdoom Pharmacy', 'INV-0053', 75, 2531.11, '', '2026-10-07', 'Sales Invoice #INV-0053 (Credit Sale)'),
(75, 'Madina Pharmacy', 'INV-0054', 76, 2685.24, '', '2026-10-07', 'Sales Invoice #INV-0054 (Credit Sale)'),
(76, 'Kashif M/S', 'INV-0055', 77, 2978.66, '', '2026-10-07', 'Sales Invoice #INV-0055 (Credit Sale)'),
(77, 'Wasim M/S', 'INV-0056', 78, 5346.24, '', '2026-10-07', 'Sales Invoice #INV-0056 (Credit Sale)'),
(78, 'Fine M/S', 'INV-0057', 79, 1519.30, '', '2026-10-07', 'Sales Invoice #INV-0057 (Credit Sale)'),
(79, 'Rehman Pharmacy', 'INV-0058', 80, 2218.47, '', '2026-10-07', 'Sales Invoice #INV-0058 (Credit Sale)'),
(80, 'ADD Plus Pharmacy', 'INV-0059', 81, 2127.98, '', '2026-10-07', 'Sales Invoice #INV-0059 (Credit Sale)'),
(81, 'Al Raziq Pharmacy', NULL, 82, 301.72, '', '2026-10-07', 'Sales Invoice # (Credit Sale)'),
(82, 'New Hammad', 'INV-0061', 83, 5972.21, '', '2026-10-07', 'Sales Invoice #INV-0061 (Credit Sale)'),
(83, 'Hafiz Care Pharmacy', 'INV-0062', 84, 4582.36, '', '2026-10-07', 'Sales Invoice #INV-0062 (Credit Sale)'),
(84, 'Health Mart Pharmacy', 'INV-0063', 85, 1507.18, '', '2026-10-07', 'Sales Invoice #INV-0063 (Credit Sale)'),
(85, 'Service Medicos', 'INV-0064', 86, 3049.02, '', '2026-10-07', 'Sales Invoice #INV-0064 (Credit Sale)'),
(86, 'Care Medicos', 'INV-0065', 87, 2423.26, '', '2026-10-07', 'Sales Invoice #INV-0065 (Credit Sale)'),
(87, 'Muhammed Mustafa', 'INV-0066', 88, 10217.97, '', '2026-10-07', 'Sales Invoice #INV-0066 (Credit Sale)'),
(88, 'Care Medicos', 'INV-0067', 89, 2423.26, '', '2026-10-07', 'Sales Invoice #INV-0067 (Credit Sale)'),
(89, 'Health Mart Pharmacy', 'INV-0068', 90, 1507.18, '', '2026-10-07', 'Sales Invoice #INV-0068 (Credit Sale)'),
(90, 'Service Medicos', 'INV-0069', 91, 3049.02, '', '2026-10-07', 'Sales Invoice #INV-0069 (Credit Sale)'),
(91, 'Hafiz Care Pharmacy', 'INV-0070', 92, 4582.36, '', '2026-10-07', 'Sales Invoice #INV-0070 (Credit Sale)'),
(92, 'New Hammad', 'INV-0071', 93, 5972.21, '', '2026-10-07', 'Sales Invoice #INV-0071 (Credit Sale)'),
(93, 'Al Raziq Pharmacy', 'INV-0072', 94, 291.57, '', '2026-10-07', 'Sales Invoice #INV-0072 (Credit Sale)'),
(94, 'Al Raziq Pharmacy', 'INV-0073', 95, 291.57, '', '2026-10-08', 'Sales Invoice #INV-0073 (Credit Sale)'),
(95, 'Al Raziq Pharmacy', 'INV-0074', 96, 291.57, '', '2026-10-08', 'Sales Invoice #INV-0074 (Credit Sale)'),
(96, 'Al Raziq Pharmacy', 'INV-0075', 97, 291.57, '', '2026-10-08', 'Sales Invoice #INV-0075 (Credit Sale)'),
(97, 'New Hammad', 'INV-0076', 98, 5972.21, '', '2026-10-08', 'Sales Invoice #INV-0076 (Credit Sale)'),
(98, 'Rehman Pharmacy', 'INV-0077', 99, 2218.47, '', '2026-10-08', 'Sales Invoice #INV-0077 (Credit Sale)'),
(99, 'Fine M/S', 'INV-0078', 100, 1519.30, '', '2026-10-08', 'Sales Invoice #INV-0078 (Credit Sale)'),
(100, 'Wasim M/S', 'INV-0079', 101, 5346.24, '', '2026-10-08', 'Sales Invoice #INV-0079 (Credit Sale)'),
(101, 'Kashif M/S', 'INV-0080', 102, 2904.59, '', '2026-10-08', 'Sales Invoice #INV-0080 (Credit Sale)'),
(102, 'Madina Pharmacy', 'INV-0081', 103, 2685.24, '', '2026-10-08', 'Sales Invoice #INV-0081 (Credit Sale)'),
(103, 'Muhammed Mustafa', 'INV-0082', 104, 10217.97, '', '2026-10-08', 'Sales Invoice #INV-0082 (Credit Sale)'),
(104, 'Makhdoom Pharmacy', 'INV-0083', 105, 2531.11, '', '2026-10-08', 'Sales Invoice #INV-0083 (Credit Sale)'),
(105, 'QS Medical & Cosmetics Store', 'INV-0084', 106, 5000.72, '', '2026-10-08', 'Sales Invoice #INV-0084 (Credit Sale)'),
(106, 'ADD Plus Pharmacy', 'INV-0085', 107, 2127.98, '', '2026-10-08', 'Sales Invoice #INV-0085 (Credit Sale)');

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `id` int NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `short_name` varchar(20) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `full_name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('Admin','Manager','Accountant','Operator','salesman') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Admin',
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('Active','Inactive') COLLATE utf8mb4_general_ci DEFAULT 'Active',
  `last_login` datetime DEFAULT NULL,
  `created_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`, `email`, `phone`, `status`, `last_login`, `created_at`) VALUES
(1, 'admin', '5121472', 'Administrator', 'Admin', NULL, NULL, 'Active', '2026-10-08 10:01:14', NULL),
(6, 'saif ali', 'raju1', 'saif ali', 'salesman', NULL, '56565461111', 'Active', NULL, '2026-09-25'),
(7, 'wasim', '12345', 'wasim sherazi', 'salesman', NULL, '656328888', 'Active', NULL, '2026-09-25'),
(8, 'sherazi', '51214', 'Sherazi', 'salesman', NULL, '03299339000', 'Active', '2026-10-07 20:59:06', '2026-09-29'),
(9, 'Raju', 'Ali51214', 'Raju', 'salesman', NULL, '03091453956', 'Inactive', '2026-10-07 07:00:44', '2026-09-29');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_ledgers`
--
ALTER TABLE `account_ledgers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_type` (`account_type`,`account_id`),
  ADD KEY `transaction_date` (`transaction_date`);

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `module` (`module`);

--
-- Indexes for table `areas`
--
ALTER TABLE `areas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_areas_name` (`name`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bank_name` (`bank_name`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `bank_transactions`
--
ALTER TABLE `bank_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bank_account_id` (`bank_account_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `transaction_date` (`transaction_date`);

--
-- Indexes for table `bookers`
--
ALTER TABLE `bookers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booker_code` (`booker_code`),
  ADD KEY `name` (`name`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `booker_ledgers`
--
ALTER TABLE `booker_ledgers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booker_id` (`booker_id`),
  ADD KEY `transaction_date` (`transaction_date`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `brands`
--
ALTER TABLE `brands`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cash_accounts`
--
ALTER TABLE `cash_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_name` (`account_name`);

--
-- Indexes for table `cash_book`
--
ALTER TABLE `cash_book`
  ADD PRIMARY KEY (`id`),
  ADD KEY `daily_id` (`daily_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `transaction_date` (`transaction_date`);

--
-- Indexes for table `cash_book_daily`
--
ALTER TABLE `cash_book_daily`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `date` (`date`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `cash_book_transactions`
--
ALTER TABLE `cash_book_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_date` (`transaction_date`),
  ADD KEY `voucher_no` (`voucher_no`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `name` (`name`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `name` (`name`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `company_settings`
--
ALTER TABLE `company_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_code` (`customer_code`),
  ADD KEY `customer_code_2` (`customer_code`),
  ADD KEY `shop_name` (`shop_name`),
  ADD KEY `name` (`name`),
  ADD KEY `route_id` (`route_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `customer_ledgers`
--
ALTER TABLE `customer_ledgers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `transaction_date` (`transaction_date`),
  ADD KEY `reference_no` (`reference_no`);

--
-- Indexes for table `customer_payments`
--
ALTER TABLE `customer_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `voucher_no` (`voucher_no`),
  ADD KEY `voucher_no_2` (`voucher_no`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `payment_date` (`payment_date`),
  ADD KEY `route_id` (`route_id`),
  ADD KEY `idx_sale_id` (`sale_id`);

--
-- Indexes for table `customer_receipts`
--
ALTER TABLE `customer_receipts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `bank_account_id` (`bank_account_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `receipt_date` (`receipt_date`);

--
-- Indexes for table `delivery_challans`
--
ALTER TABLE `delivery_challans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `challan_no` (`challan_no`),
  ADD KEY `challan_no_2` (`challan_no`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `challan_date` (`challan_date`),
  ADD KEY `delivery_status` (`delivery_status`);

--
-- Indexes for table `delivery_challan_items`
--
ALTER TABLE `delivery_challan_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `challan_id` (`challan_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `delivery_riders`
--
ALTER TABLE `delivery_riders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rider_name` (`rider_name`),
  ADD KEY `vehicle_no` (`vehicle_no`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `employee_type` (`employee_type`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `employee_salaries`
--
ALTER TABLE `employee_salaries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `voucher_no` (`voucher_no`),
  ADD KEY `voucher_no_2` (`voucher_no`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `expense_date` (`expense_date`);

--
-- Indexes for table `expense_categories`
--
ALTER TABLE `expense_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `name` (`name`);

--
-- Indexes for table `opening_stock_logs`
--
ALTER TABLE `opening_stock_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `entry_date` (`entry_date`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_code` (`product_code`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `product_code_2` (`product_code`),
  ADD KEY `name` (`name`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `unit_id` (`unit_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `product_batches`
--
ALTER TABLE `product_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `batch_no` (`batch_no`),
  ADD KEY `expiry_date` (`expiry_date`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bill_no` (`bill_no`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `purchase_date` (`purchase_date`);

--
-- Indexes for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_id` (`purchase_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `batch_no` (`batch_no`);

--
-- Indexes for table `purchase_returns`
--
ALTER TABLE `purchase_returns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `return_no` (`return_no`),
  ADD KEY `return_no_2` (`return_no`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `return_date` (`return_date`);

--
-- Indexes for table `purchase_return_items`
--
ALTER TABLE `purchase_return_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_return_id` (`purchase_return_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `routes`
--
ALTER TABLE `routes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `route_code` (`route_code`),
  ADD KEY `name` (`name`),
  ADD KEY `assigned_booker_id` (`assigned_booker_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_no` (`invoice_no`),
  ADD KEY `invoice_no_2` (`invoice_no`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `sale_date` (`sale_date`),
  ADD KEY `route_id` (`route_id`);

--
-- Indexes for table `sales_invoices`
--
ALTER TABLE `sales_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_no` (`invoice_no`),
  ADD KEY `invoice_no_2` (`invoice_no`),
  ADD KEY `invoice_date` (`invoice_date`),
  ADD KEY `customer_name` (`customer_name`),
  ADD KEY `booker_id` (`booker_id`),
  ADD KEY `route_id` (`route_id`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `batch_no` (`batch_no`);

--
-- Indexes for table `sale_returns`
--
ALTER TABLE `sale_returns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `return_no` (`return_no`),
  ADD KEY `return_no_2` (`return_no`),
  ADD KEY `return_date` (`return_date`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `sale_return_items`
--
ALTER TABLE `sale_return_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `return_id` (`return_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_code` (`employee_code`),
  ADD KEY `name` (`name`),
  ADD KEY `role` (`role`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supplier_code` (`supplier_code`),
  ADD KEY `supplier_code_2` (`supplier_code`),
  ADD KEY `company_name` (`company_name`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `supplier_ledgers`
--
ALTER TABLE `supplier_ledgers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `transaction_date` (`transaction_date`),
  ADD KEY `reference_no` (`reference_no`);

--
-- Indexes for table `supplier_payments`
--
ALTER TABLE `supplier_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `voucher_no` (`voucher_no`),
  ADD KEY `voucher_no_2` (`voucher_no`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `payment_date` (`payment_date`);

--
-- Indexes for table `udhaar_book`
--
ALTER TABLE `udhaar_book`
  ADD PRIMARY KEY (`id`),
  ADD KEY `party_name` (`party_name`),
  ADD KEY `invoice_no` (`invoice_no`),
  ADD KEY `credit_date` (`credit_date`);

--
-- Indexes for table `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`id`),
  ADD KEY `name` (`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account_ledgers`
--
ALTER TABLE `account_ledgers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `areas`
--
ALTER TABLE `areas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bank_transactions`
--
ALTER TABLE `bank_transactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bookers`
--
ALTER TABLE `bookers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `booker_ledgers`
--
ALTER TABLE `booker_ledgers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `brands`
--
ALTER TABLE `brands`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cash_accounts`
--
ALTER TABLE `cash_accounts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cash_book`
--
ALTER TABLE `cash_book`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `cash_book_daily`
--
ALTER TABLE `cash_book_daily`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `cash_book_transactions`
--
ALTER TABLE `cash_book_transactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `company_settings`
--
ALTER TABLE `company_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `customer_ledgers`
--
ALTER TABLE `customer_ledgers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11514;

--
-- AUTO_INCREMENT for table `customer_payments`
--
ALTER TABLE `customer_payments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_receipts`
--
ALTER TABLE `customer_receipts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `delivery_challans`
--
ALTER TABLE `delivery_challans`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=107;

--
-- AUTO_INCREMENT for table `delivery_challan_items`
--
ALTER TABLE `delivery_challan_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=451;

--
-- AUTO_INCREMENT for table `delivery_riders`
--
ALTER TABLE `delivery_riders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `employee_salaries`
--
ALTER TABLE `employee_salaries`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `expense_categories`
--
ALTER TABLE `expense_categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `opening_stock_logs`
--
ALTER TABLE `opening_stock_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=148;

--
-- AUTO_INCREMENT for table `product_batches`
--
ALTER TABLE `product_batches`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=204;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `purchase_items`
--
ALTER TABLE `purchase_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=206;

--
-- AUTO_INCREMENT for table `purchase_returns`
--
ALTER TABLE `purchase_returns`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_return_items`
--
ALTER TABLE `purchase_return_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `routes`
--
ALTER TABLE `routes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_invoices`
--
ALTER TABLE `sales_invoices`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=108;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=548;

--
-- AUTO_INCREMENT for table `sale_returns`
--
ALTER TABLE `sale_returns`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `sale_return_items`
--
ALTER TABLE `sale_return_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=163;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `supplier_ledgers`
--
ALTER TABLE `supplier_ledgers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=502;

--
-- AUTO_INCREMENT for table `supplier_payments`
--
ALTER TABLE `supplier_payments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `udhaar_book`
--
ALTER TABLE `udhaar_book`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=107;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bank_transactions`
--
ALTER TABLE `bank_transactions`
  ADD CONSTRAINT `bank_transactions_ibfk_1` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bank_transactions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cash_book`
--
ALTER TABLE `cash_book`
  ADD CONSTRAINT `cash_book_ibfk_1` FOREIGN KEY (`daily_id`) REFERENCES `cash_book_daily` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `cash_book_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cash_book_daily`
--
ALTER TABLE `cash_book_daily`
  ADD CONSTRAINT `cash_book_daily_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `customer_receipts`
--
ALTER TABLE `customer_receipts`
  ADD CONSTRAINT `customer_receipts_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_receipts_ibfk_2` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customer_receipts_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
