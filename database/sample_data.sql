-- =====================================================================
-- BESTWAY WHOLESALE MEDICINE DISTRIBUTION SYSTEM
-- Sample Data Seed: `bestway_wholesale`
-- =====================================================================

USE `bestway_wholesale`;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Companies
INSERT INTO `companies` (`id`, `name`, `code`, `contact_person`, `phone`, `email`, `address`, `status`) VALUES
(1, 'Getz Pharma (Pvt) Ltd', 'GETZ', 'Tariq Mehmood', '021-38621111', 'info@getzpharma.com', 'Korangi Industrial Area, Karachi', 'Active'),
(2, 'GlaxoSmithKline (GSK)', 'GSK', 'Ali Hassan', '021-111475752', 'contact@gsk.com', 'Ferozepur Road, Lahore', 'Active'),
(3, 'Abbott Laboratories', 'ABT', 'Kamran Sheikh', '021-35069746', 'care@abbott.pk', 'Landhi Industrial Area, Karachi', 'Active'),
(4, 'Searle Company Limited', 'SRL', 'Zubair Ahmed', '021-35684341', 'sales@searle.com.pk', 'Clifton, Karachi', 'Active'),
(5, 'Sami Pharmaceuticals', 'SAMI', 'Rizwan Khan', '021-35061544', 'info@samipharmapk.com', 'Sector 15, Korangi, Karachi', 'Active'),
(6, 'Ferozsons Laboratories', 'FRZ', 'Usman Malik', '042-35851415', 'info@ferozsons-labs.com', 'Main Boulevard, Gulberg, Lahore', 'Active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 2. Categories
INSERT INTO `categories` (`id`, `name`, `code`, `description`, `status`) VALUES
(1, 'Tablets & Capsules', 'TAB', 'Oral solid dosage forms including coated, effervescent, and softgel tablets', 'Active'),
(2, 'Syrups & Suspensions', 'SYR', 'Liquid oral formulations, cough syrups, pediatric suspensions', 'Active'),
(3, 'Injections & IV Fluids', 'INJ', 'Sterile ampoules, vials, IV infusions, and saline drips', 'Active'),
(4, 'Eye & Ear Drops', 'DRP', 'Ophthalmic and otic sterile solutions and suspensions', 'Active'),
(5, 'Creams & Ointments', 'CRM', 'Topical dermatological creams, gels, and antiseptic ointments', 'Active'),
(6, 'Inhalers & Sprays', 'INH', 'Respiratory inhalers, rotacaps, and nasal sprays', 'Active'),
(7, 'Surgical & Disposables', 'SURG', 'Syringes, cannulas, surgical cotton, bandages, and alcohol swabs', 'Active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 3. Bookers
INSERT INTO `bookers` (`id`, `booker_code`, `name`, `phone`, `address`, `commission_rate`, `opening_balance`, `current_balance`, `status`) VALUES
(1, 'BKR-001', 'Muhammad Aslam', '0301-4455667', 'Sheikhupura City', 1.50, 0.00, 0.00, 'Active'),
(2, 'BKR-002', 'Shahid Hussain', '0322-8899112', 'Sharaqpur Road', 2.00, 0.00, 0.00, 'Active'),
(3, 'BKR-003', 'Waqas Ashraf', '0333-7711223', 'Farooqabad', 1.50, 0.00, 0.00, 'Active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 4. Routes
INSERT INTO `routes` (`id`, `route_code`, `name`, `area_description`, `delivery_day`, `assigned_booker_id`, `status`) VALUES
(1, 'RT-001', 'Sheikhupura City Center', 'Main Bazar, Hospital Road, Stadium Road, Civil Lines', 'Monday & Thursday', 1, 'Active'),
(2, 'RT-002', 'Sharaqpur Road Route', 'Al Rehman Garden, Faizpur Interchange, Kot Abdul Malik', 'Tuesday & Friday', 2, 'Active'),
(3, 'RT-003', 'Farooqabad & Muridke Road', 'Farooqabad Main Bazar, Railway Road, Mandi Faizabad', 'Wednesday & Saturday', 3, 'Active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 5. Products / Medicines
INSERT INTO `products` (`id`, `product_code`, `barcode`, `name`, `generic_name`, `company_id`, `category_id`, `unit_id`, `pack_size`, `packs_per_box`, `tablets_per_pack`, `total_tablets_per_box`, `stock_unit`, `purchase_price`, `trade_price`, `retail_price`, `wholesale_price`, `discount_percent`, `max_discount_percent`, `reorder_level`, `opening_stock`, `current_stock`, `location_rack`, `requires_prescription`, `cold_chain`, `status`) VALUES
(1, 'PRD-0001', '896400010101', 'Panadol 500mg Tablets', 'Paracetamol', 2, 1, 1, '20x10 (200 Tabs)', 20, 10, 200, 'Pack', 580.00, 640.00, 710.00, 640.00, 5.00, 8.00, 30, 150, 150, 'Rack A-1', 0, 0, 'Active'),
(2, 'PRD-0002', '896400010102', 'Panadol Extra Tablets', 'Paracetamol + Caffeine', 2, 1, 1, '10x10 (100 Tabs)', 10, 10, 100, 'Pack', 390.00, 435.00, 480.00, 435.00, 5.00, 8.00, 25, 120, 120, 'Rack A-2', 0, 0, 'Active'),
(3, 'PRD-0003', '896400010103', 'Augmentin 625mg Tablets', 'Co-Amoxiclav', 2, 1, 1, '1x6 (6 Tabs)', 1, 6, 6, 'Pack', 240.00, 275.00, 310.00, 275.00, 7.00, 10.00, 40, 200, 200, 'Rack B-1', 1, 0, 'Active'),
(4, 'PRD-0004', '896400010104', 'Risek 20mg Capsules', 'Omeprazole', 1, 1, 1, '2x7 (14 Caps)', 2, 7, 14, 'Pack', 310.00, 350.00, 395.00, 350.00, 8.00, 12.00, 35, 180, 180, 'Rack B-2', 0, 0, 'Active'),
(5, 'PRD-0005', '896400010105', 'Brufen 400mg Tablets', 'Ibuprofen', 3, 1, 1, '30x10 (300 Tabs)', 30, 10, 300, 'Pack', 720.00, 810.00, 900.00, 810.00, 5.00, 8.00, 20, 100, 100, 'Rack C-1', 0, 0, 'Active'),
(6, 'PRD-0006', '896400010106', 'Cac-1000 Plus Effervescent', 'Calcium + Vitamin C + D3', 2, 1, 1, '1x10 (10 Tabs)', 1, 10, 10, 'Bottle', 280.00, 320.00, 360.00, 320.00, 6.00, 9.00, 30, 140, 140, 'Rack C-2', 0, 0, 'Active'),
(7, 'PRD-0007', '896400010107', 'Arinac Forte Tablets', 'Ibuprofen + Pseudoephedrine', 3, 1, 1, '10x10 (100 Tabs)', 10, 10, 100, 'Pack', 460.00, 520.00, 580.00, 520.00, 6.00, 10.00, 25, 110, 110, 'Rack D-1', 0, 0, 'Active'),
(8, 'PRD-0008', '896400010108', 'Hydryllin Syrup 120ml', 'Aminophylline Compound', 4, 2, 4, '1x120ml', 1, 1, 1, 'Bottle', 125.00, 142.00, 160.00, 142.00, 5.00, 8.00, 50, 250, 250, 'Liquid Rack 1', 0, 0, 'Active'),
(9, 'PRD-0009', '896400010109', 'Cranmax Sachet', 'Cranberry Extract', 5, 1, 10, '1x10 Sachets', 1, 10, 10, 'Pack', 480.00, 545.00, 615.00, 545.00, 7.50, 10.00, 20, 90, 90, 'Rack D-2', 0, 0, 'Active'),
(10, 'PRD-0010', '896400010110', 'Softin 10mg Tablets', 'Loratadine', 6, 1, 1, '1x10 (10 Tabs)', 1, 10, 10, 'Pack', 180.00, 205.00, 230.00, 205.00, 8.00, 12.00, 30, 160, 160, 'Rack E-1', 0, 0, 'Active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 6. Product Batches
INSERT INTO `product_batches` (`id`, `product_id`, `batch_no`, `manufacturing_date`, `expiry_date`, `purchase_price`, `trade_price`, `retail_price`, `initial_quantity`, `current_stock`, `status`) VALUES
(1, 1, 'BAT-PND-2401', '2024-01-10', '2027-01-10', 580.00, 640.00, 710.00, 150, 150, 'Active'),
(2, 2, 'BAT-PNE-2403', '2024-03-15', '2027-03-15', 390.00, 435.00, 480.00, 120, 120, 'Active'),
(3, 3, 'BAT-AUG-2406', '2024-06-01', '2026-12-31', 240.00, 275.00, 310.00, 200, 200, 'Active'),
(4, 4, 'BAT-RSK-2405', '2024-05-20', '2027-05-20', 310.00, 350.00, 395.00, 180, 180, 'Active'),
(5, 5, 'BAT-BRF-2402', '2024-02-12', '2027-02-12', 720.00, 810.00, 900.00, 100, 100, 'Active'),
(6, 6, 'BAT-CAC-2404', '2024-04-05', '2026-10-31', 280.00, 320.00, 360.00, 140, 140, 'Active'),
(7, 7, 'BAT-ARN-2407', '2024-07-18', '2027-07-18', 460.00, 520.00, 580.00, 110, 110, 'Active'),
(8, 8, 'BAT-HYD-2408', '2024-08-01', '2026-08-01', 125.00, 142.00, 160.00, 250, 250, 'Active'),
(9, 9, 'BAT-CRN-2405', '2024-05-10', '2026-11-10', 480.00, 545.00, 615.00, 90, 90, 'Active'),
(10, 10, 'BAT-SFT-2409', '2024-09-01', '2027-09-01', 180.00, 205.00, 230.00, 160, 160, 'Active')
ON DUPLICATE KEY UPDATE `batch_no` = VALUES(`batch_no`);

-- 7. Customers / Pharmacies
INSERT INTO `customers` (`id`, `customer_code`, `name`, `shop_name`, `drug_license_no`, `phone`, `address`, `route_id`, `opening_balance`, `current_balance`, `status`) VALUES
(1, 'CUST-0001', 'Dr. Farhan Qureshi', 'Al-Shafi Pharmacy & Medicos', 'DL-SKP-2023-0192', '0300-8877665', 'Shop #12, Hospital Road, Sheikhupura', 1, 0.00, 18500.00, 'Active'),
(2, 'CUST-0002', 'Haji Munir Ahmed', 'Rehman Medicos & General Store', 'DL-SKP-2022-0844', '0321-6655443', 'Main Bazar, Near GPO, Sheikhupura', 1, 0.00, 34200.00, 'Active'),
(3, 'CUST-0003', 'M. Rizwan Butt', 'Care Plus Pharmacy', 'DL-SKP-2024-1102', '0334-9988771', 'Gate #01, Al Rehman Garden Phase 2, Sharaqpur Road', 2, 0.00, 9800.00, 'Active'),
(4, 'CUST-0004', 'Chaudhry Akram', 'Madina Medical Complex Pharmacy', 'DL-SKP-2021-0341', '0302-3344556', 'Faizpur Interchange, Sharaqpur Road', 2, 0.00, 45600.00, 'Active'),
(5, 'CUST-0005', 'Naveed Akhtar', 'City Pharmacy & Surgical', 'DL-SKP-2023-0599', '0313-2211009', 'Main Chowk, Farooqabad', 3, 0.00, 12400.00, 'Active')
ON DUPLICATE KEY UPDATE `shop_name` = VALUES(`shop_name`);

-- 8. Suppliers
INSERT INTO `suppliers` (`id`, `supplier_code`, `name`, `company_name`, `phone`, `mobile`, `email`, `ntn_strn`, `address`, `credit_days`, `opening_balance`, `current_balance`, `status`) VALUES
(1, 'SUP-0001', 'Mian Tariq Distribution', 'Getz Pharma Authorized Distributor', '042-37589901', '0300-5544332', 'tariq.dist@gmail.com', 'NTN-1234567-1', 'Circular Road, Urdu Bazar, Lahore', 30, 0.00, 75000.00, 'Active'),
(2, 'SUP-0002', 'Malik Brothers Pharma Agency', 'GSK & Abbott Direct Agency', '042-37234455', '0321-4433221', 'malikpharma@hotmail.com', 'NTN-2345678-2', 'Nishtar Road, Wholesale Market, Lahore', 30, 0.00, 120000.00, 'Active'),
(3, 'SUP-0003', 'Hassan Medicos Wholesale', 'Searle & Sami Distributors', '042-37661122', '0333-8877112', 'hassanmedicos@yahoo.com', 'NTN-3456789-3', 'Brandreth Road, Lahore', 21, 0.00, 48000.00, 'Active')
ON DUPLICATE KEY UPDATE `company_name` = VALUES(`company_name`);

-- 9. Delivery Riders
INSERT INTO `delivery_riders` (`id`, `rider_name`, `phone`, `vehicle_no`, `vehicle_type`, `cnic`, `address`, `status`) VALUES
(1, 'Kashif Ali', '0304-1122334', 'LEA-24-7890', 'Delivery Van', '35401-1234567-1', 'Sheikhupura City', 'Active'),
(2, 'Imran Jameel', '0323-5566778', 'LEB-23-4512', 'Motorcycle Loader', '35401-7654321-3', 'Sharaqpur Road', 'Active')
ON DUPLICATE KEY UPDATE `rider_name` = VALUES(`rider_name`);

-- 10. Staff
INSERT INTO `staff` (`id`, `employee_code`, `name`, `role`, `designation`, `phone`, `cnic`, `salary`, `commission_rate`, `address`, `joining_date`, `status`) VALUES
(1, 'EMP-001', 'Muhammad Zeeshan', 'Manager', 'Operations & Warehouse Manager', '0300-7654321', '35401-9876543-1', 45000.00, 0.00, 'Sheikhupura', '2023-01-01', 'Active'),
(2, 'EMP-002', 'Usman Ghani', 'Accountant', 'Chief Cashier & Accountant', '0321-8765432', '35401-4567890-5', 38000.00, 0.00, 'Sheikhupura', '2023-03-15', 'Active'),
(3, 'EMP-003', 'Bilal Ahmed', 'Delivery', 'Senior Delivery Officer', '0333-6543210', '35401-3216549-7', 28000.00, 0.00, 'Sharaqpur', '2023-06-01', 'Active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

SET FOREIGN_KEY_CHECKS = 1;
