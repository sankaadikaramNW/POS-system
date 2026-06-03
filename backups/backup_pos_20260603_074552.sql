-- DXL POS Database Backup
-- Generated: 2026-06-03 07:45:52

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','cashier','manager','super_admin') NOT NULL DEFAULT 'cashier',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `pin` varchar(4) DEFAULT '1234',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` VALUES 
('1', 'Administrator', 'admin', '$2y$10$skkW07I7ZN2b3AQ7UlShiejFQMqmfoHF/t.L/0ViGbl8Bjqe7ScH.', 'super_admin', '2026-06-01 21:15:46', '1234'),
('2', 'Sanka Adikaram', 'sanka', '$2y$10$skkW07I7ZN2b3AQ7UlShiejFQMqmfoHF/t.L/0ViGbl8Bjqe7ScH.', 'cashier', '2026-06-02 11:00:52', '1234');

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `categories` VALUES 
('1', 'T-shirts', '2026-06-01 21:17:36'),
('2', 'Denim', '2026-06-02 20:24:54');

DROP TABLE IF EXISTS `brands`;
CREATE TABLE `brands` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `brand_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `brands` VALUES 
('1', 'Adidas', '2026-06-01 21:17:21'),
('2', 'Moose', '2026-06-02 20:23:57'),
('3', 'Carnage', '2026-06-02 20:24:36');

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) DEFAULT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `size` varchar(10) DEFAULT NULL,
  `gender` varchar(20) DEFAULT 'Unisex',
  `color` varchar(50) DEFAULT NULL,
  `purchase_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_percent` int(11) DEFAULT 0,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0,
  `reorder_level` int(11) NOT NULL DEFAULT 5,
  `image` varchar(255) DEFAULT 'default.png',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `barcode` (`barcode`),
  KEY `category_id` (`category_id`),
  KEY `brand_id` (`brand_id`),
  CONSTRAINT `fk_prod_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_prod_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `products` VALUES 
('1', '1', '1', 't-shirt', NULL, '', 'Unisex', '', '500.00', '550.00', '10', '1', '0', '5', 'default.png', '2026-06-01 21:18:06'),
('2', '2', '2', 't-shirt', NULL, 'S', 'Unisex', 'Black', '750.00', '800.00', '0', '7', '0', '5', 'default.png', '2026-06-02 20:25:46');

DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `loyalty_points` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `purchases`;
CREATE TABLE `purchases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `purchase_date` date NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_purch_supp` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_purch_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `purchase_items`;
CREATE TABLE `purchase_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `purchase_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_id` (`purchase_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `fk_pi_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pi_purch` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `sales`;
CREATE TABLE `sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `tax` decimal(10,2) DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT 'Cash',
  `card_reference` varchar(4) DEFAULT NULL COMMENT 'Last 4 digits from card machine receipt',
  `paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sale_date` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `print_count` int(11) DEFAULT 1,
  `status` varchar(20) NOT NULL DEFAULT 'COMPLETED',
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_no` (`invoice_no`),
  KEY `customer_id` (`customer_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_sales_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sales_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `sales` VALUES 
('1', 'INV-260601-A1FF7', NULL, '1100.00', '0.00', '0.00', 'Cash', NULL, '1100.00', '0.00', '2026-06-01 21:19:14', '1', '1', 'COMPLETED', NULL, NULL, NULL),
('2', 'INV-260602-3FF74', NULL, '550.00', '0.00', '0.00', 'Cash', NULL, '550.00', '0.00', '2026-06-02 10:56:11', '1', '3', 'COMPLETED', NULL, NULL, NULL),
('3', 'INV-260602-E4E27', NULL, '3300.00', '0.00', '0.00', 'Cash', NULL, '3300.00', '0.00', '2026-06-02 11:45:52', '1', '1', 'COMPLETED', NULL, NULL, NULL),
('4', 'INV-260602-8C8BD', NULL, '550.00', '0.00', '0.00', 'Cash', NULL, '550.00', '0.00', '2026-06-02 11:46:38', '1', '2', 'COMPLETED', NULL, NULL, NULL),
('5', 'INV-260602-9330B', NULL, '550.00', '0.00', '0.00', 'Cash', NULL, '550.00', '0.00', '2026-06-02 13:16:18', '1', '1', 'COMPLETED', NULL, NULL, NULL),
('6', 'INV-260602-7F4B4', NULL, '3300.00', '0.00', '0.00', 'Card', '0852', '3300.00', '0.00', '2026-06-02 20:21:54', '1', '2', 'COMPLETED', NULL, NULL, NULL);

DROP TABLE IF EXISTS `sale_items`;
CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `selling_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `fk_si_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_si_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `sale_items` VALUES 
('1', '1', '1', '2', '550.00', '1100.00'),
('2', '2', '1', '1', '550.00', '550.00'),
('3', '3', '1', '6', '550.00', '3300.00'),
('4', '4', '1', '1', '550.00', '550.00'),
('5', '5', '1', '1', '550.00', '550.00'),
('6', '6', '1', '6', '550.00', '3300.00');

DROP TABLE IF EXISTS `inventory_logs`;
CREATE TABLE `inventory_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `action_date` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_inv_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inv_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `inventory_logs` VALUES 
('1', '1', 'SALE', '2', '1', 'INV-260601-A1FF7', '2026-06-01 21:19:14', '1'),
('2', '1', 'STOCK_IN', '9', NULL, 'ADJ-260602-4113', '2026-06-02 10:51:34', '1'),
('5', '1', 'SALE', '1', '2', 'INV-260602-3FF74', '2026-06-02 10:56:11', '1'),
('6', '1', 'SALE', '6', '3', 'INV-260602-E4E27', '2026-06-02 11:45:52', '1'),
('7', '1', 'SALE', '1', '4', 'INV-260602-8C8BD', '2026-06-02 11:46:38', '1'),
('8', '1', 'SALE', '1', '5', 'INV-260602-9330B', '2026-06-02 13:16:18', '1'),
('9', '1', 'SALE', '6', '6', 'INV-260602-7F4B4', '2026-06-02 20:21:54', '1');

DROP TABLE IF EXISTS `day_end_sessions`;
CREATE TABLE `day_end_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_date` date NOT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `opened_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `closed_at` timestamp NULL DEFAULT NULL,
  `opened_by` int(11) DEFAULT NULL,
  `closed_by` int(11) DEFAULT NULL,
  `total_sales` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_tax` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `net_sales` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_expenses` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_purchases` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_cash_payments` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_card_payments` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_pending_bills` int(11) NOT NULL DEFAULT 0,
  `stock_adjustments` int(11) NOT NULL DEFAULT 0,
  `closing_balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reopened_by` int(11) DEFAULT NULL,
  `reopened_at` timestamp NULL DEFAULT NULL,
  `reopen_reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `business_date` (`business_date`),
  KEY `fk_des_open_by` (`opened_by`),
  KEY `fk_des_close_by` (`closed_by`),
  KEY `fk_des_reopened_by` (`reopened_by`),
  CONSTRAINT `fk_des_close_by` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_des_open_by` FOREIGN KEY (`opened_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_des_reopened_by` FOREIGN KEY (`reopened_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `day_end_sessions` VALUES 
('1', '2026-06-01', 'open', '2026-06-01 21:20:33', NULL, '1', NULL, '0.00', '0.00', '0.00', '0.00', '2026-06-01 21:20:33', '0.00', '0.00', '0.00', '0.00', '0', '0', '0.00', NULL, NULL, NULL),
('2', '2026-06-02', 'open', '2026-06-02 10:43:12', NULL, '1', NULL, '0.00', '0.00', '0.00', '0.00', '2026-06-02 10:43:12', '0.00', '0.00', '0.00', '0.00', '0', '0', '0.00', NULL, NULL, NULL);

DROP TABLE IF EXISTS `cashier_shifts`;
CREATE TABLE `cashier_shifts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cashier_id` int(11) NOT NULL,
  `business_date` date NOT NULL,
  `opening_cash` decimal(10,2) NOT NULL DEFAULT 0.00,
  `expected_cash` decimal(10,2) NOT NULL DEFAULT 0.00,
  `closing_cash` decimal(10,2) NOT NULL DEFAULT 0.00,
  `variance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `shift_start` datetime NOT NULL,
  `shift_end` datetime DEFAULT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_cs_cashier` (`cashier_id`),
  CONSTRAINT `fk_cs_cashier` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `cashier_shifts` VALUES 
('1', '1', '2026-06-01', '5000.00', '6100.00', '5100.00', '-1000.00', '2026-06-01 21:21:51', '2026-06-01 21:21:51', 'closed', '2026-06-01 21:21:51'),
('2', '1', '2026-06-02', '5000.00', '9400.00', '9400.00', '0.00', '2026-06-02 11:48:58', '2026-06-02 11:48:58', 'closed', '2026-06-02 11:48:58'),
('3', '2', '2026-06-02', '5000.00', '5000.00', '5000.00', '0.00', '2026-06-02 11:49:03', '2026-06-02 11:49:03', 'closed', '2026-06-02 11:49:03');

DROP TABLE IF EXISTS `payment_reconciliation`;
CREATE TABLE `payment_reconciliation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_date` date NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `pos_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `gateway_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `variance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reconciled_by` int(11) DEFAULT NULL,
  `reconciled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_pr_user` (`reconciled_by`),
  CONSTRAINT `fk_pr_user` FOREIGN KEY (`reconciled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `payment_reconciliation` VALUES 
('7', '2026-06-01', 'Cash', '1100.00', '0.00', '-1100.00', '1', '2026-06-01 21:22:43'),
('8', '2026-06-01', 'Card', '0.00', '0.00', '0.00', '1', '2026-06-01 21:22:43'),
('9', '2026-06-01', 'Mobile', '0.00', '0.00', '0.00', '1', '2026-06-01 21:22:43'),
('46', '2026-06-02', 'Cash', '4950.00', '0.00', '-4950.00', '1', '2026-06-03 11:15:13'),
('47', '2026-06-02', 'Card', '3300.00', '0.00', '-3300.00', '1', '2026-06-03 11:15:13'),
('48', '2026-06-02', 'Mobile', '0.00', '0.00', '0.00', '1', '2026-06-03 11:15:13');

DROP TABLE IF EXISTS `z_reports`;
CREATE TABLE `z_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_date` date NOT NULL,
  `branch_name` varchar(100) DEFAULT 'Main Branch',
  `total_sales` decimal(10,2) NOT NULL DEFAULT 0.00,
  `gross_sales` decimal(10,2) NOT NULL DEFAULT 0.00,
  `net_sales` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discounts` decimal(10,2) NOT NULL DEFAULT 0.00,
  `refunds` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `net_profit_estimate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `z_report_pdf_path` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `business_date` (`business_date`),
  KEY `fk_zr_user` (`created_by`),
  CONSTRAINT `fk_zr_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `z_reports` VALUES 
('1', '2026-06-01', 'Main Branch', '1100.00', '1100.00', '1100.00', '0.00', '0.00', '0.00', '100.00', NULL, '1', '2026-06-01 21:22:43'),
('6', '2026-06-02', 'Main Branch', '8250.00', '8250.00', '8250.00', '0.00', '0.00', '0.00', '750.00', NULL, '1', '2026-06-03 11:15:13');

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_al_user` (`user_id`),
  CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `audit_logs` VALUES 
('1', '1', 'SHIFT_CLOSE', 'Closed shift for cashier ID 1. Counted: LKR 5100, Expected: LKR 6100, Variance: LKR -1000', '::1', '2026-06-01 21:21:51'),
('2', '1', 'PAYMENT_RECONCILE', 'Reconciled payment channels for business date: 2026-06-01', '::1', '2026-06-01 21:22:01'),
('3', '1', 'PAYMENT_RECONCILE', 'Reconciled payment channels for business date: 2026-06-01', '::1', '2026-06-01 21:22:11'),
('4', '1', 'INVENTORY_SYNC', 'Synchronized 1 sale items to inventory movements.', '::1', '2026-06-01 21:22:29'),
('5', '1', 'Z_REPORT_GENERATE', 'Generated Z-Report for date: 2026-06-01. Net: LKR 1100.00', '::1', '2026-06-01 21:22:43'),
('6', '1', 'PAYMENT_RECONCILE', 'Reconciled payment channels for business date: 2026-06-01', '::1', '2026-06-01 21:22:43'),
('7', '1', 'SHIFT_CLOSE', 'Closed shift for cashier ID 1. Counted: LKR 9400, Expected: LKR 9400, Variance: LKR 0', '::1', '2026-06-02 11:48:58'),
('8', '1', 'SHIFT_CLOSE', 'Closed shift for cashier ID 2. Counted: LKR 5000, Expected: LKR 5000, Variance: LKR 0', '::1', '2026-06-02 11:49:03'),
('9', '1', 'PAYMENT_RECONCILE', 'Reconciled payment channels for business date: 2026-06-02', '::1', '2026-06-02 11:49:04'),
('10', '1', 'PAYMENT_RECONCILE', 'Reconciled payment channels for business date: 2026-06-02', '::1', '2026-06-02 11:49:10'),
('11', '1', 'INVENTORY_SYNC', 'Synchronized 3 sale items to inventory movements.', '::1', '2026-06-02 11:49:21'),
('12', '1', 'Z_REPORT_GENERATE', 'Generated Z-Report for date: 2026-06-02. Net: LKR 4400.00', '::1', '2026-06-02 11:49:24'),
('13', '1', 'PAYMENT_RECONCILE', 'Reconciled payment channels for business date: 2026-06-02', '::1', '2026-06-02 11:49:24'),
('14', '1', 'DB_BACKUP', 'Completed database backup to: backup_pos_20260602_081933.sql', '::1', '2026-06-02 11:49:33'),
('15', '1', 'DB_BACKUP', 'Completed database backup to: backup_pos_20260602_081940.sql', '::1', '2026-06-02 11:49:40'),
('16', '1', 'DB_BACKUP', 'Completed database backup to: backup_pos_20260602_081951.sql', '::1', '2026-06-02 11:49:51'),
('17', '1', 'Z_REPORT_GENERATE', 'Generated Z-Report for date: 2026-06-02. Net: LKR 4400.00', '::1', '2026-06-02 11:49:57'),
('18', '1', 'PAYMENT_RECONCILE', 'Reconciled payment channels for business date: 2026-06-02', '::1', '2026-06-02 11:49:57'),
('19', '1', 'INVENTORY_SYNC', 'Synchronized 2 sale items to inventory movements.', '::1', '2026-06-03 10:38:19'),
('20', '1', 'DB_BACKUP', 'Completed database backup to: backup_pos_20260603_070827.sql', '::1', '2026-06-03 10:38:27'),
('21', '1', 'PAYMENT_RECONCILE', 'Reconciled payment channels for business date: 2026-06-02', '::1', '2026-06-03 11:08:58'),
('22', '1', 'PAYMENT_RECONCILE', 'Reconciled payment channels for business date: 2026-06-02', '::1', '2026-06-03 11:09:03'),
('23', '1', 'INVENTORY_SYNC', 'Synchronized 0 sale items to inventory movements.', '::1', '2026-06-03 11:09:28'),
('24', '1', 'Z_REPORT_GENERATE', 'Generated Z-Report for date: 2026-06-02. Net: LKR 8250.00', '::1', '2026-06-03 11:09:33'),
('25', '1', 'PAYMENT_RECONCILE', 'Reconciled payment channels for business date: 2026-06-02', '::1', '2026-06-03 11:09:33'),
('26', '1', 'PAYMENT_RECONCILE', 'Reconciled payment channels for business date: 2026-06-02', '::1', '2026-06-03 11:10:06'),
('27', '1', 'PAYMENT_RECONCILE', 'Reconciled payment channels for business date: 2026-06-02', '::1', '2026-06-03 11:10:23'),
('28', '1', 'PAYMENT_RECONCILE', 'Reconciled payment channels for business date: 2026-06-02', '::1', '2026-06-03 11:10:36'),
('29', '1', 'Z_REPORT_GENERATE', 'Generated Z-Report for date: 2026-06-02. Net: LKR 8250.00', '::1', '2026-06-03 11:10:36'),
('30', '1', 'DB_BACKUP', 'Completed database backup to: backup_pos_20260603_074057.sql', '::1', '2026-06-03 11:10:57'),
('31', '1', 'DB_BACKUP', 'Completed database backup to: backup_pos_20260603_074103.sql', '::1', '2026-06-03 11:11:03'),
('32', '1', 'DB_BACKUP', 'Completed database backup to: backup_pos_20260603_074239.sql', '::1', '2026-06-03 11:12:39'),
('33', '1', 'PAYMENT_RECONCILE', 'Reconciled payment channels for business date: 2026-06-02', '::1', '2026-06-03 11:14:25'),
('34', '1', 'PAYMENT_RECONCILE', 'Reconciled payment channels for business date: 2026-06-02', '::1', '2026-06-03 11:14:38'),
('35', '1', 'INVENTORY_SYNC', 'Synchronized 0 sale items to inventory movements.', '::1', '2026-06-03 11:15:07'),
('36', '1', 'PAYMENT_RECONCILE', 'Reconciled payment channels for business date: 2026-06-02', '::1', '2026-06-03 11:15:13'),
('37', '1', 'Z_REPORT_GENERATE', 'Generated Z-Report for date: 2026-06-02. Net: LKR 8250.00', '::1', '2026-06-03 11:15:13');

DROP TABLE IF EXISTS `inventory_movements`;
CREATE TABLE `inventory_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `business_date` date NOT NULL,
  `movement_type` enum('SALE','RETURN','ADJUSTMENT','STOCK_IN') NOT NULL,
  `quantity` int(11) NOT NULL,
  `previous_stock` int(11) NOT NULL,
  `new_stock` int(11) NOT NULL,
  `reference_id` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_im_prod` (`product_id`),
  CONSTRAINT `fk_im_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `inventory_movements` VALUES 
('1', '1', '2026-06-01', 'SALE', '2', '2', '0', 'INV-260601-A1FF7', '2026-06-01 21:22:29'),
('2', '1', '2026-06-02', 'SALE', '1', '2', '1', 'INV-260602-3FF74', '2026-06-02 11:49:21'),
('3', '1', '2026-06-02', 'SALE', '6', '7', '1', 'INV-260602-E4E27', '2026-06-02 11:49:21'),
('4', '1', '2026-06-02', 'SALE', '1', '2', '1', 'INV-260602-8C8BD', '2026-06-02 11:49:21'),
('5', '1', '2026-06-02', 'SALE', '1', '2', '1', 'INV-260602-9330B', '2026-06-03 10:38:19'),
('6', '1', '2026-06-02', 'SALE', '6', '7', '1', 'INV-260602-7F4B4', '2026-06-03 10:38:19');

