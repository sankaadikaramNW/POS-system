-- Hold Sale / Pending Customer Billing Database Schema
-- DXL Fashion POS

-- 1. pending_sales Table
CREATE TABLE IF NOT EXISTS `pending_sales` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `hold_bill_no` VARCHAR(50) UNIQUE NOT NULL,
  `customer_id` INT NULL,
  `customer_name` VARCHAR(100) NULL,
  `cart_data_json` LONGTEXT NOT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `grand_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `notes` TEXT NULL,
  `status` ENUM('PENDING', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'PENDING',
  `cashier_id` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `resumed_at` DATETIME NULL,
  `completed_at` DATETIME NULL,
  `cancelled_at` DATETIME NULL,
  INDEX `idx_ps_status` (`status`),
  INDEX `idx_ps_created_at` (`created_at`),
  INDEX `idx_ps_cashier` (`cashier_id`),
  CONSTRAINT `fk_ps_cashier` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ps_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. pending_sales_logs Table (Audit Trail)
CREATE TABLE IF NOT EXISTS `pending_sales_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `username` VARCHAR(50) NOT NULL,
  `action` VARCHAR(100) NOT NULL, -- 'Bill Held', 'Bill Resumed', 'Bill Completed', 'Bill Cancelled'
  `log_date` DATE NOT NULL,
  `log_time` TIME NOT NULL,
  `bill_no` VARCHAR(50) NOT NULL,
  INDEX `idx_psl_bill` (`bill_no`),
  CONSTRAINT `fk_psl_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
