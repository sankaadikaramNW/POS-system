-- ============================================================
-- DXL Fashion POS — Full System Reset to Clean State
-- WARNING: This PERMANENTLY deletes absolutely ALL data in all tables.
-- Clears: Catalog, Products, Users, Sales, Purchases, Categories, Brands, Shifts, Day Ends.
-- Recreates: Default Admin Account (sanka / password123)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. Catalog & Products ────────────────────────────────────
TRUNCATE TABLE `products`;
TRUNCATE TABLE `categories`;
TRUNCATE TABLE `brands`;

-- ── 2. Sales & Invoices ──────────────────────────────────────
TRUNCATE TABLE `sales`;
TRUNCATE TABLE `sale_items`;
TRUNCATE TABLE `pending_sales`;
TRUNCATE TABLE `pending_sales_logs`;

-- ── 3. Purchases & Inventory ─────────────────────────────────
TRUNCATE TABLE `purchases`;
TRUNCATE TABLE `purchase_items`;
TRUNCATE TABLE `inventory_logs`;
TRUNCATE TABLE `inventory_movements`;
TRUNCATE TABLE `stock_movements`;

-- ── 4. Partners & Contacts ───────────────────────────────────
TRUNCATE TABLE `customers`;
TRUNCATE TABLE `suppliers`;

-- ── 5. Human Resources & Attendance ──────────────────────────
TRUNCATE TABLE `employees`;
TRUNCATE TABLE `attendance`;

-- ── 6. Shifts & Day End Reconciliations ──────────────────────
TRUNCATE TABLE `cashier_shifts`;
TRUNCATE TABLE `day_end_sessions`;
TRUNCATE TABLE `payment_reconciliation`;
TRUNCATE TABLE `z_reports`;

-- Safe Truncate for optional/custom tables if they exist
SET @de = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'day_end_records');
SET @sql_de = IF(@de > 0, 'TRUNCATE TABLE `day_end_records`', 'SELECT "day_end_records skipped" AS info');
PREPARE s FROM @sql_de; EXECUTE s; DEALLOCATE PREPARE s;

SET @ds = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'day_end_summary');
SET @sql_ds = IF(@ds > 0, 'TRUNCATE TABLE `day_end_summary`', 'SELECT "day_end_summary skipped" AS info');
PREPARE s2 FROM @sql_ds; EXECUTE s2; DEALLOCATE PREPARE s2;

SET @dl = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'day_end_logs');
SET @sql_dl = IF(@dl > 0, 'TRUNCATE TABLE `day_end_logs`', 'SELECT "day_end_logs skipped" AS info');
PREPARE s3 FROM @sql_dl; EXECUTE s3; DEALLOCATE PREPARE s3;

SET @cs = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cashier_sessions');
SET @sql_cs = IF(@cs > 0, 'TRUNCATE TABLE `cashier_sessions`', 'SELECT "cashier_sessions skipped" AS info');
PREPARE s4 FROM @sql_cs; EXECUTE s4; DEALLOCATE PREPARE s4;

-- ── 7. Audit Logs ────────────────────────────────────────────
TRUNCATE TABLE `audit_logs`;

-- ── 8. Users (Re-inserts Default Admin Account) ─────────────
TRUNCATE TABLE `users`;
INSERT INTO `users` (`id`, `full_name`, `username`, `password`, `role`) VALUES
(1, 'Administrator', 'admin', '$2y$10$3lBHAJYToK.GhPldqTKFLee3DWdedzpkH95SAJ1Z3nZS9HMnTWMB.', 'admin');

SET FOREIGN_KEY_CHECKS = 1;

-- ── 9. Verification ──────────────────────────────────────────
SELECT 'products' AS tbl, COUNT(*) AS records FROM products UNION ALL
SELECT 'categories', COUNT(*) FROM categories UNION ALL
SELECT 'brands', COUNT(*) FROM brands UNION ALL
SELECT 'sales', COUNT(*) FROM sales UNION ALL
SELECT 'purchases', COUNT(*) FROM purchases UNION ALL
SELECT 'customers', COUNT(*) FROM customers UNION ALL
SELECT 'suppliers', COUNT(*) FROM suppliers UNION ALL
SELECT 'employees', COUNT(*) FROM employees UNION ALL
SELECT 'cashier_shifts', COUNT(*) FROM cashier_shifts UNION ALL
SELECT 'users', COUNT(*) FROM users;

SELECT 'All tables successfully zeroed. Sanka Admin User re-created.' AS status;
