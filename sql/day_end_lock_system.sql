-- 1. Alter day_end_sessions table to add comprehensive financial summary and reopen metrics
ALTER TABLE `day_end_sessions`
  ADD COLUMN `total_expenses` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN `total_purchases` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN `total_cash_payments` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN `total_card_payments` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN `total_pending_bills` INT NOT NULL DEFAULT 0,
  ADD COLUMN `stock_adjustments` INT NOT NULL DEFAULT 0,
  ADD COLUMN `closing_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN `reopened_by` INT NULL,
  ADD COLUMN `reopened_at` TIMESTAMP NULL,
  ADD COLUMN `reopen_reason` TEXT NULL,
  ADD CONSTRAINT `fk_des_reopened_by` FOREIGN KEY (`reopened_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- 2. Alter users table to support standard manager and superadmin roles
ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin', 'cashier', 'manager', 'super_admin') NOT NULL DEFAULT 'cashier';

-- 3. Install database-level triggers to enforce locked business days

-- SALES TRIGGERS
DROP TRIGGER IF EXISTS trg_sales_before_insert;
DELIMITER //
CREATE TRIGGER trg_sales_before_insert BEFORE INSERT ON sales FOR EACH ROW
BEGIN
    DECLARE day_status VARCHAR(20) DEFAULT 'open';
    SELECT status INTO day_status FROM day_end_sessions WHERE business_date = DATE(COALESCE(NEW.sale_date, NOW()));
    IF day_status = 'closed' AND COALESCE(@bypass_day_end_lock, 0) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This business day has been closed through Day End Process. Modifications are not permitted.';
    END IF;
END//
DELIMITER ;

DROP TRIGGER IF EXISTS trg_sales_before_update;
DELIMITER //
CREATE TRIGGER trg_sales_before_update BEFORE UPDATE ON sales FOR EACH ROW
BEGIN
    DECLARE day_status VARCHAR(20) DEFAULT 'open';
    SELECT status INTO day_status FROM day_end_sessions WHERE business_date = DATE(OLD.sale_date);
    IF day_status = 'closed' AND COALESCE(@bypass_day_end_lock, 0) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This business day has been closed through Day End Process. Modifications are not permitted.';
    END IF;
END//
DELIMITER ;

DROP TRIGGER IF EXISTS trg_sales_before_delete;
DELIMITER //
CREATE TRIGGER trg_sales_before_delete BEFORE DELETE ON sales FOR EACH ROW
BEGIN
    DECLARE day_status VARCHAR(20) DEFAULT 'open';
    SELECT status INTO day_status FROM day_end_sessions WHERE business_date = DATE(OLD.sale_date);
    IF day_status = 'closed' AND COALESCE(@bypass_day_end_lock, 0) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This business day has been closed through Day End Process. Modifications are not permitted.';
    END IF;
END//
DELIMITER ;

-- SALE ITEMS TRIGGERS
DROP TRIGGER IF EXISTS trg_sale_items_before_insert;
DELIMITER //
CREATE TRIGGER trg_sale_items_before_insert BEFORE INSERT ON sale_items FOR EACH ROW
BEGIN
    DECLARE day_status VARCHAR(20) DEFAULT 'open';
    DECLARE s_date DATETIME;
    SELECT sale_date INTO s_date FROM sales WHERE id = NEW.sale_id;
    SELECT status INTO day_status FROM day_end_sessions WHERE business_date = DATE(s_date);
    IF day_status = 'closed' AND COALESCE(@bypass_day_end_lock, 0) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This business day has been closed through Day End Process. Modifications are not permitted.';
    END IF;
END//
DELIMITER ;

DROP TRIGGER IF EXISTS trg_sale_items_before_update;
DELIMITER //
CREATE TRIGGER trg_sale_items_before_update BEFORE UPDATE ON sale_items FOR EACH ROW
BEGIN
    DECLARE day_status VARCHAR(20) DEFAULT 'open';
    DECLARE s_date DATETIME;
    SELECT sale_date INTO s_date FROM sales WHERE id = OLD.sale_id;
    SELECT status INTO day_status FROM day_end_sessions WHERE business_date = DATE(s_date);
    IF day_status = 'closed' AND COALESCE(@bypass_day_end_lock, 0) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This business day has been closed through Day End Process. Modifications are not permitted.';
    END IF;
END//
DELIMITER ;

DROP TRIGGER IF EXISTS trg_sale_items_before_delete;
DELIMITER //
CREATE TRIGGER trg_sale_items_before_delete BEFORE DELETE ON sale_items FOR EACH ROW
BEGIN
    DECLARE day_status VARCHAR(20) DEFAULT 'open';
    DECLARE s_date DATETIME;
    SELECT sale_date INTO s_date FROM sales WHERE id = OLD.sale_id;
    SELECT status INTO day_status FROM day_end_sessions WHERE business_date = DATE(s_date);
    IF day_status = 'closed' AND COALESCE(@bypass_day_end_lock, 0) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This business day has been closed through Day End Process. Modifications are not permitted.';
    END IF;
END//
DELIMITER ;

-- PURCHASES TRIGGERS
DROP TRIGGER IF EXISTS trg_purchases_before_insert;
DELIMITER //
CREATE TRIGGER trg_purchases_before_insert BEFORE INSERT ON purchases FOR EACH ROW
BEGIN
    DECLARE day_status VARCHAR(20) DEFAULT 'open';
    SELECT status INTO day_status FROM day_end_sessions WHERE business_date = COALESCE(NEW.purchase_date, CURRENT_DATE());
    IF day_status = 'closed' AND COALESCE(@bypass_day_end_lock, 0) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This business day has been closed through Day End Process. Modifications are not permitted.';
    END IF;
END//
DELIMITER ;

DROP TRIGGER IF EXISTS trg_purchases_before_update;
DELIMITER //
CREATE TRIGGER trg_purchases_before_update BEFORE UPDATE ON purchases FOR EACH ROW
BEGIN
    DECLARE day_status VARCHAR(20) DEFAULT 'open';
    SELECT status INTO day_status FROM day_end_sessions WHERE business_date = OLD.purchase_date;
    IF day_status = 'closed' AND COALESCE(@bypass_day_end_lock, 0) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This business day has been closed through Day End Process. Modifications are not permitted.';
    END IF;
END//
DELIMITER ;

DROP TRIGGER IF EXISTS trg_purchases_before_delete;
DELIMITER //
CREATE TRIGGER trg_purchases_before_delete BEFORE DELETE ON purchases FOR EACH ROW
BEGIN
    DECLARE day_status VARCHAR(20) DEFAULT 'open';
    SELECT status INTO day_status FROM day_end_sessions WHERE business_date = OLD.purchase_date;
    IF day_status = 'closed' AND COALESCE(@bypass_day_end_lock, 0) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This business day has been closed through Day End Process. Modifications are not permitted.';
    END IF;
END//
DELIMITER ;

-- PURCHASE ITEMS TRIGGERS
DROP TRIGGER IF EXISTS trg_purchase_items_before_insert;
DELIMITER //
CREATE TRIGGER trg_purchase_items_before_insert BEFORE INSERT ON purchase_items FOR EACH ROW
BEGIN
    DECLARE day_status VARCHAR(20) DEFAULT 'open';
    DECLARE p_date DATE;
    SELECT purchase_date INTO p_date FROM purchases WHERE id = NEW.purchase_id;
    SELECT status INTO day_status FROM day_end_sessions WHERE business_date = p_date;
    IF day_status = 'closed' AND COALESCE(@bypass_day_end_lock, 0) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This business day has been closed through Day End Process. Modifications are not permitted.';
    END IF;
END//
DELIMITER ;

DROP TRIGGER IF EXISTS trg_purchase_items_before_update;
DELIMITER //
CREATE TRIGGER trg_purchase_items_before_update BEFORE UPDATE ON purchase_items FOR EACH ROW
BEGIN
    DECLARE day_status VARCHAR(20) DEFAULT 'open';
    DECLARE p_date DATE;
    SELECT purchase_date INTO p_date FROM purchases WHERE id = OLD.purchase_id;
    SELECT status INTO day_status FROM day_end_sessions WHERE business_date = p_date;
    IF day_status = 'closed' AND COALESCE(@bypass_day_end_lock, 0) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This business day has been closed through Day End Process. Modifications are not permitted.';
    END IF;
END//
DELIMITER ;

DROP TRIGGER IF EXISTS trg_purchase_items_before_delete;
DELIMITER //
CREATE TRIGGER trg_purchase_items_before_delete BEFORE DELETE ON purchase_items FOR EACH ROW
BEGIN
    DECLARE day_status VARCHAR(20) DEFAULT 'open';
    DECLARE p_date DATE;
    SELECT purchase_date INTO p_date FROM purchases WHERE id = OLD.purchase_id;
    SELECT status INTO day_status FROM day_end_sessions WHERE business_date = p_date;
    IF day_status = 'closed' AND COALESCE(@bypass_day_end_lock, 0) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This business day has been closed through Day End Process. Modifications are not permitted.';
    END IF;
END//
DELIMITER ;

-- STOCK MOVEMENTS TRIGGERS
DROP TRIGGER IF EXISTS trg_stock_movements_before_insert;
DELIMITER //
CREATE TRIGGER trg_stock_movements_before_insert BEFORE INSERT ON stock_movements FOR EACH ROW
BEGIN
    DECLARE day_status VARCHAR(20) DEFAULT 'open';
    SELECT status INTO day_status FROM day_end_sessions WHERE business_date = DATE(COALESCE(NEW.created_at, NOW()));
    IF day_status = 'closed' AND COALESCE(@bypass_day_end_lock, 0) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This business day has been closed through Day End Process. Modifications are not permitted.';
    END IF;
END//
DELIMITER ;

DROP TRIGGER IF EXISTS trg_stock_movements_before_update;
DELIMITER //
CREATE TRIGGER trg_stock_movements_before_update BEFORE UPDATE ON stock_movements FOR EACH ROW
BEGIN
    DECLARE day_status VARCHAR(20) DEFAULT 'open';
    SELECT status INTO day_status FROM day_end_sessions WHERE business_date = DATE(OLD.created_at);
    IF day_status = 'closed' AND COALESCE(@bypass_day_end_lock, 0) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This business day has been closed through Day End Process. Modifications are not permitted.';
    END IF;
END//
DELIMITER ;

DROP TRIGGER IF EXISTS trg_stock_movements_before_delete;
DELIMITER //
CREATE TRIGGER trg_stock_movements_before_delete BEFORE DELETE ON stock_movements FOR EACH ROW
BEGIN
    DECLARE day_status VARCHAR(20) DEFAULT 'open';
    SELECT status INTO day_status FROM day_end_sessions WHERE business_date = DATE(OLD.created_at);
    IF day_status = 'closed' AND COALESCE(@bypass_day_end_lock, 0) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This business day has been closed through Day End Process. Modifications are not permitted.';
    END IF;
END//
DELIMITER ;

-- INVENTORY MOVEMENTS TRIGGERS
DROP TRIGGER IF EXISTS trg_inventory_movements_before_insert;
DELIMITER //
CREATE TRIGGER trg_inventory_movements_before_insert BEFORE INSERT ON inventory_movements FOR EACH ROW
BEGIN
    DECLARE day_status VARCHAR(20) DEFAULT 'open';
    SELECT status INTO day_status FROM day_end_sessions WHERE business_date = NEW.business_date;
    IF day_status = 'closed' AND COALESCE(@bypass_day_end_lock, 0) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This business day has been closed through Day End Process. Modifications are not permitted.';
    END IF;
END//
DELIMITER ;

DROP TRIGGER IF EXISTS trg_inventory_movements_before_update;
DELIMITER //
CREATE TRIGGER trg_inventory_movements_before_update BEFORE UPDATE ON inventory_movements FOR EACH ROW
BEGIN
    DECLARE day_status VARCHAR(20) DEFAULT 'open';
    SELECT status INTO day_status FROM day_end_sessions WHERE business_date = OLD.business_date;
    IF day_status = 'closed' AND COALESCE(@bypass_day_end_lock, 0) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This business day has been closed through Day End Process. Modifications are not permitted.';
    END IF;
END//
DELIMITER ;

DROP TRIGGER IF EXISTS trg_inventory_movements_before_delete;
DELIMITER //
CREATE TRIGGER trg_inventory_movements_before_delete BEFORE DELETE ON inventory_movements FOR EACH ROW
BEGIN
    DECLARE day_status VARCHAR(20) DEFAULT 'open';
    SELECT status INTO day_status FROM day_end_sessions WHERE business_date = OLD.business_date;
    IF day_status = 'closed' AND COALESCE(@bypass_day_end_lock, 0) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This business day has been closed through Day End Process. Modifications are not permitted.';
    END IF;
END//
DELIMITER ;

-- INVENTORY LOGS TRIGGERS
DROP TRIGGER IF EXISTS trg_inventory_logs_before_insert;
DELIMITER //
CREATE TRIGGER trg_inventory_logs_before_insert BEFORE INSERT ON inventory_logs FOR EACH ROW
BEGIN
    DECLARE day_status VARCHAR(20) DEFAULT 'open';
    SELECT status INTO day_status FROM day_end_sessions WHERE business_date = DATE(COALESCE(NEW.action_date, NOW()));
    IF day_status = 'closed' AND COALESCE(@bypass_day_end_lock, 0) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This business day has been closed through Day End Process. Modifications are not permitted.';
    END IF;
END//
DELIMITER ;

DROP TRIGGER IF EXISTS trg_inventory_logs_before_update;
DELIMITER //
CREATE TRIGGER trg_inventory_logs_before_update BEFORE UPDATE ON inventory_logs FOR EACH ROW
BEGIN
    DECLARE day_status VARCHAR(20) DEFAULT 'open';
    SELECT status INTO day_status FROM day_end_sessions WHERE business_date = DATE(OLD.action_date);
    IF day_status = 'closed' AND COALESCE(@bypass_day_end_lock, 0) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This business day has been closed through Day End Process. Modifications are not permitted.';
    END IF;
END//
DELIMITER ;

DROP TRIGGER IF EXISTS trg_inventory_logs_before_delete;
DELIMITER //
CREATE TRIGGER trg_inventory_logs_before_delete BEFORE DELETE ON inventory_logs FOR EACH ROW
BEGIN
    DECLARE day_status VARCHAR(20) DEFAULT 'open';
    SELECT status INTO day_status FROM day_end_sessions WHERE business_date = DATE(OLD.action_date);
    IF day_status = 'closed' AND COALESCE(@bypass_day_end_lock, 0) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This business day has been closed through Day End Process. Modifications are not permitted.';
    END IF;
END//
DELIMITER ;

-- PENDING SALES TRIGGERS
DROP TRIGGER IF EXISTS trg_pending_sales_before_update;
DELIMITER //
CREATE TRIGGER trg_pending_sales_before_update BEFORE UPDATE ON pending_sales FOR EACH ROW
BEGIN
    DECLARE day_status VARCHAR(20) DEFAULT 'open';
    SELECT status INTO day_status FROM day_end_sessions WHERE business_date = DATE(OLD.created_at);
    IF day_status = 'closed' AND COALESCE(@bypass_day_end_lock, 0) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This business day has been closed through Day End Process. Modifications are not permitted.';
    END IF;
END//
DELIMITER ;

DROP TRIGGER IF EXISTS trg_pending_sales_before_delete;
DELIMITER //
CREATE TRIGGER trg_pending_sales_before_delete BEFORE DELETE ON pending_sales FOR EACH ROW
BEGIN
    DECLARE day_status VARCHAR(20) DEFAULT 'open';
    SELECT status INTO day_status FROM day_end_sessions WHERE business_date = DATE(OLD.created_at);
    IF day_status = 'closed' AND COALESCE(@bypass_day_end_lock, 0) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This business day has been closed through Day End Process. Modifications are not permitted.';
    END IF;
END//
DELIMITER ;
