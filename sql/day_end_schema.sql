-- Day End Process Schema SQL
-- DX Fashion POS

-- 1. day_end_sessions
CREATE TABLE IF NOT EXISTS `day_end_sessions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `business_date` DATE NOT NULL UNIQUE,
  `status` ENUM('open', 'closed') NOT NULL DEFAULT 'open',
  `opened_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `closed_at` TIMESTAMP NULL,
  `opened_by` INT NULL,
  `closed_by` INT NULL,
  `total_sales` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_tax` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `net_sales` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_des_open_by` FOREIGN KEY (`opened_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_des_close_by` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. cashier_shifts
CREATE TABLE IF NOT EXISTS `cashier_shifts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `cashier_id` INT NOT NULL,
  `business_date` DATE NOT NULL,
  `opening_cash` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `expected_cash` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `closing_cash` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `variance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `shift_start` DATETIME NOT NULL,
  `shift_end` DATETIME NULL,
  `status` ENUM('open', 'closed') NOT NULL DEFAULT 'open',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_cs_cashier` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. payment_reconciliation
CREATE TABLE IF NOT EXISTS `payment_reconciliation` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `business_date` DATE NOT NULL,
  `payment_method` VARCHAR(50) NOT NULL,
  `pos_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `gateway_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `variance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `reconciled_by` INT NULL,
  `reconciled_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pr_user` FOREIGN KEY (`reconciled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. z_reports
CREATE TABLE IF NOT EXISTS `z_reports` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `business_date` DATE NOT NULL UNIQUE,
  `branch_name` VARCHAR(100) DEFAULT 'Main Branch',
  `total_sales` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `gross_sales` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `net_sales` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discounts` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `refunds` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `net_profit_estimate` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `z_report_pdf_path` VARCHAR(255) NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_zr_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. audit_logs
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. inventory_movements
CREATE TABLE IF NOT EXISTS `inventory_movements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `business_date` DATE NOT NULL,
  `movement_type` ENUM('SALE', 'RETURN', 'ADJUSTMENT', 'STOCK_IN') NOT NULL,
  `quantity` INT NOT NULL,
  `previous_stock` INT NOT NULL,
  `new_stock` INT NOT NULL,
  `reference_id` VARCHAR(50) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_im_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
