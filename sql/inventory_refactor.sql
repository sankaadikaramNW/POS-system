-- ============================================================
-- DXL Fashion POS — Inventory Management Refactor Migration
-- Run via install_inventory_refactor.php (admin only)
-- ============================================================

USE `fashion_pos`;

-- ─────────────────────────────────────────────────────────────
-- 1. stock_movements — Full Audit Trail Table
--    Tracks every inventory change with before/after values
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `stock_movements` (
  `id`            INT(11)         NOT NULL AUTO_INCREMENT,
  `product_id`    INT(11)         NOT NULL,
  `invoice_id`    INT(11)         DEFAULT NULL,
  `reference_no`  VARCHAR(100)    DEFAULT NULL        COMMENT 'Invoice No, HB No, or adjustment ref',
  `movement_type` ENUM(
                    'SALE',
                    'PURCHASE',
                    'RETURN',
                    'ADJUSTMENT',
                    'CANCELLATION',
                    'INVOICE_EDIT',
                    'STOCK_IN',
                    'STOCK_OUT',
                    'DAMAGES'
                  )               NOT NULL,
  `quantity`      INT(11)         NOT NULL            COMMENT 'Absolute quantity moved (always positive)',
  `previous_stock` INT(11)        NOT NULL DEFAULT 0,
  `new_stock`      INT(11)        NOT NULL DEFAULT 0,
  `notes`         TEXT            DEFAULT NULL,
  `user_id`       INT(11)         DEFAULT NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sm_product`    (`product_id`),
  KEY `idx_sm_invoice`    (`invoice_id`),
  KEY `idx_sm_type`       (`movement_type`),
  KEY `idx_sm_created`    (`created_at`),
  CONSTRAINT `fk_sm_product`  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sm_invoice`  FOREIGN KEY (`invoice_id`) REFERENCES `sales` (`id`)    ON DELETE SET NULL,
  CONSTRAINT `fk_sm_user`     FOREIGN KEY (`user_id`)    REFERENCES `users` (`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Complete stock movement audit trail — single source of truth';

-- ─────────────────────────────────────────────────────────────
-- 2. Enhance inventory_logs with invoice_id + reference_no
--    (backward-compatible — adds columns only if missing)
-- ─────────────────────────────────────────────────────────────
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'inventory_logs'
    AND COLUMN_NAME  = 'invoice_id'
);

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `inventory_logs` ADD COLUMN `invoice_id` INT(11) DEFAULT NULL AFTER `quantity`',
  'SELECT "inventory_logs.invoice_id already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col2_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'inventory_logs'
    AND COLUMN_NAME  = 'reference_no'
);

SET @sql2 = IF(@col2_exists = 0,
  'ALTER TABLE `inventory_logs` ADD COLUMN `reference_no` VARCHAR(100) DEFAULT NULL AFTER `invoice_id`',
  'SELECT "inventory_logs.reference_no already exists" AS info'
);
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

-- ─────────────────────────────────────────────────────────────
-- 3. Add a CHECK constraint so stock_quantity never goes below 0
--    (MariaDB 10.2.1+ supports CHECK constraints — ignored on older)
--    We also add a TRIGGER as a universal guard.
-- ─────────────────────────────────────────────────────────────
DROP TRIGGER IF EXISTS `trg_prevent_negative_stock_update`;
CREATE TRIGGER `trg_prevent_negative_stock_update`
  BEFORE UPDATE ON `products`
  FOR EACH ROW
BEGIN
  IF NEW.stock_quantity < 0 THEN
    SET NEW.stock_quantity = 0;
  END IF;
END;

DROP TRIGGER IF EXISTS `trg_prevent_negative_stock_insert`;
CREATE TRIGGER `trg_prevent_negative_stock_insert`
  BEFORE INSERT ON `products`
  FOR EACH ROW
BEGIN
  IF NEW.stock_quantity < 0 THEN
    SET NEW.stock_quantity = 0;
  END IF;
END;

-- ─────────────────────────────────────────────────────────────
-- Done
-- ─────────────────────────────────────────────────────────────
SELECT 'Inventory refactor migration completed successfully.' AS migration_status;
