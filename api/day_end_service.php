<?php
// api/day_end_service.php
require_once __DIR__ . '/../config/database.php';

class DayEndService {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Audit log helper
     */
    public function logAudit($user_id, $action, $details) {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $stmt = $this->pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $action, $details, $ip]);
        } catch (PDOException $e) {
            // Silently fail if audit logging fails
        }
    }

    /**
     * Check for open/unpaid transactions (e.g. sales where balance > 0)
     */
    public function getPendingTransactions($business_date) {
        $stmt = $this->pdo->prepare("
            SELECT s.id, s.invoice_no, s.total_amount, s.paid_amount, s.balance, u.full_name as cashier
            FROM sales s
            LEFT JOIN users u ON s.created_by = u.id
            WHERE DATE(s.sale_date) = ? AND s.balance > 0
        ");
        $stmt->execute([$business_date]);
        return $stmt->fetchAll();
    }

    /**
     * Get or create cashier shifts for the business date based on sales activities
     */
    public function getCashierShifts($business_date) {
        // Find users with role 'cashier' or 'admin' who have made sales or have active shifts
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT u.id, u.full_name, u.username, u.role,
                   cs.id as shift_id, cs.opening_cash, cs.expected_cash, cs.closing_cash, cs.variance, cs.status as shift_status, cs.shift_start, cs.shift_end
            FROM users u
            LEFT JOIN cashier_shifts cs ON u.id = cs.cashier_id AND cs.business_date = ?
            WHERE u.role IN ('cashier', 'admin')
               OR u.id IN (SELECT DISTINCT created_by FROM sales WHERE DATE(sale_date) = ?)
        ");
        $stmt->execute([$business_date, $business_date]);
        $shifts = $stmt->fetchAll();

        // For each cashier without an initialized shift, calculate expected cash based on their sales
        foreach ($shifts as &$shift) {
            if (empty($shift['shift_id'])) {
                // Fetch total cash sales for this cashier
                $sales_stmt = $this->pdo->prepare("
                    SELECT COALESCE(SUM(total_amount), 0) as cash_total
                    FROM sales
                    WHERE DATE(sale_date) = ? AND created_by = ? AND payment_method = 'Cash'
                ");
                $sales_stmt->execute([$business_date, $shift['id']]);
                $cash_total = $sales_stmt->fetchColumn();

                $shift['shift_id'] = null;
                $shift['opening_cash'] = 5000.00; // standard opening till fallback
                $shift['expected_cash'] = 5000.00 + $cash_total;
                $shift['closing_cash'] = 0.00;
                $shift['variance'] = 0.00;
                $shift['shift_status'] = 'open';
                $shift['shift_start'] = date('Y-m-d H:i:s');
                $shift['shift_end'] = null;
            }
        }
        return $shifts;
    }

    /**
     * Reconcile/Close a cashier shift
     */
    public function closeCashierShift($cashier_id, $business_date, $opening_cash, $closing_cash, $user_id) {
        $this->pdo->beginTransaction();
        try {
            // Get expected cash: opening_cash + Cash sales today by this cashier
            $sales_stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(total_amount), 0) as cash_total
                FROM sales
                WHERE DATE(sale_date) = ? AND created_by = ? AND payment_method = 'Cash'
            ");
            $sales_stmt->execute([$business_date, $cashier_id]);
            $cash_sales = $sales_stmt->fetchColumn();
            
            $expected_cash = $opening_cash + $cash_sales;
            $variance = $closing_cash - $expected_cash;

            // Check if shift already exists
            $check_stmt = $this->pdo->prepare("SELECT id FROM cashier_shifts WHERE cashier_id = ? AND business_date = ?");
            $check_stmt->execute([$cashier_id, $business_date]);
            $shift_id = $check_stmt->fetchColumn();

            if ($shift_id) {
                // Update
                $update_stmt = $this->pdo->prepare("
                    UPDATE cashier_shifts 
                    SET opening_cash = ?, expected_cash = ?, closing_cash = ?, variance = ?, shift_end = NOW(), status = 'closed'
                    WHERE id = ?
                ");
                $update_stmt->execute([$opening_cash, $expected_cash, $closing_cash, $variance, $shift_id]);
            } else {
                // Insert
                $insert_stmt = $this->pdo->prepare("
                    INSERT INTO cashier_shifts (cashier_id, business_date, opening_cash, expected_cash, closing_cash, variance, shift_start, shift_end, status)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), 'closed')
                ");
                $insert_stmt->execute([$cashier_id, $business_date, $opening_cash, $expected_cash, $closing_cash, $variance]);
            }

            $this->logAudit($user_id, 'SHIFT_CLOSE', "Closed shift for cashier ID $cashier_id. Counted: LKR $closing_cash, Expected: LKR $expected_cash, Variance: LKR $variance");
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Reconcile payment methods against actual gateway totals
     */
    public function reconcilePayments($business_date, $gateway_totals, $user_id) {
        $this->pdo->beginTransaction();
        try {
            $methods = ['Cash', 'Card', 'Mobile'];
            $results = [];

            foreach ($methods as $method) {
                // Calculate POS total for this payment method today
                $pos_stmt = $this->pdo->prepare("
                    SELECT COALESCE(SUM(total_amount), 0) 
                    FROM sales 
                    WHERE DATE(sale_date) = ? AND payment_method = ?
                ");
                $pos_stmt->execute([$business_date, $method]);
                $pos_total = $pos_stmt->fetchColumn();

                $gateway_total = floatval($gateway_totals[$method] ?? 0.00);
                $variance = $gateway_total - $pos_total;

                // Delete old reconciliation if exists to prevent duplicates
                $del_stmt = $this->pdo->prepare("DELETE FROM payment_reconciliation WHERE business_date = ? AND payment_method = ?");
                $del_stmt->execute([$business_date, $method]);

                // Insert new reconciliation
                $ins_stmt = $this->pdo->prepare("
                    INSERT INTO payment_reconciliation (business_date, payment_method, pos_total, gateway_total, variance, reconciled_by)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $ins_stmt->execute([$business_date, $method, $pos_total, $gateway_total, $variance, $user_id]);

                $results[$method] = [
                    'pos_total' => $pos_total,
                    'gateway_total' => $gateway_total,
                    'variance' => $variance
                ];
            }

            $this->logAudit($user_id, 'PAYMENT_RECONCILE', "Reconciled payment channels for business date: " . $business_date);
            $this->pdo->commit();
            return $results;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Get payment reconciliation data without modifying database
     */
    public function getPaymentReconciliation($business_date) {
        $methods = ['Cash', 'Card', 'Mobile'];
        $results = [];

        foreach ($methods as $method) {
            $stmt = $this->pdo->prepare("
                SELECT pos_total, gateway_total, variance 
                FROM payment_reconciliation 
                WHERE business_date = ? AND payment_method = ?
            ");
            $stmt->execute([$business_date, $method]);
            $recon = $stmt->fetch();

            if ($recon) {
                $results[$method] = [
                    'pos_total' => floatval($recon['pos_total']),
                    'gateway_total' => floatval($recon['gateway_total']),
                    'variance' => floatval($recon['variance'])
                ];
            } else {
                $pos_stmt = $this->pdo->prepare("
                    SELECT COALESCE(SUM(total_amount), 0) 
                    FROM sales 
                    WHERE DATE(sale_date) = ? AND payment_method = ? AND status != 'CANCELLED'
                ");
                $pos_stmt->execute([$business_date, $method]);
                $pos_total = floatval($pos_stmt->fetchColumn());

                $results[$method] = [
                    'pos_total' => $pos_total,
                    'gateway_total' => 0.00,
                    'variance' => -$pos_total
                ];
            }
        }
        return $results;
    }

    /**
     * Generate Sales Summary Reports
     */
    public function getSalesSummary($business_date) {
        // 1. Total/Gross/Net Sales, Tax, Discount
        $summary_stmt = $this->pdo->prepare("
            SELECT 
                COUNT(id) as total_invoices,
                COALESCE(SUM(total_amount), 0) as net_sales,
                COALESCE(SUM(discount), 0) as discounts,
                COALESCE(SUM(tax), 0) as tax,
                COALESCE(SUM(total_amount + discount), 0) as gross_sales
            FROM sales
            WHERE DATE(sale_date) = ?
        ");
        $summary_stmt->execute([$business_date]);
        $summary = $summary_stmt->fetch();

        // 2. Best selling products
        $best_sellers_stmt = $this->pdo->prepare("
            SELECT p.product_name, SUM(si.quantity) as total_qty, SUM(si.subtotal) as total_revenue
            FROM sale_items si
            JOIN sales s ON si.sale_id = s.id
            JOIN products p ON si.product_id = p.id
            WHERE DATE(s.sale_date) = ?
            GROUP BY si.product_id
            ORDER BY total_qty DESC
            LIMIT 5
        ");
        $best_sellers_stmt->execute([$business_date]);
        $best_sellers = $best_sellers_stmt->fetchAll();

        // 3. Sales by category
        $category_sales_stmt = $this->pdo->prepare("
            SELECT c.category_name, SUM(si.subtotal) as total_revenue
            FROM sale_items si
            JOIN sales s ON si.sale_id = s.id
            JOIN products p ON si.product_id = p.id
            JOIN categories c ON p.category_id = c.id
            WHERE DATE(s.sale_date) = ?
            GROUP BY p.category_id
            ORDER BY total_revenue DESC
        ");
        $category_sales_stmt->execute([$business_date]);
        $category_sales = $category_sales_stmt->fetchAll();

        // 4. Sales by cashier
        $cashier_sales_stmt = $this->pdo->prepare("
            SELECT u.full_name, COUNT(s.id) as invoices_count, SUM(s.total_amount) as total_revenue
            FROM sales s
            JOIN users u ON s.created_by = u.id
            WHERE DATE(s.sale_date) = ?
            GROUP BY s.created_by
        ");
        $cashier_sales_stmt->execute([$business_date]);
        $cashier_sales = $cashier_sales_stmt->fetchAll();

        // 5. Hourly Sales analytics
        $hourly_sales_stmt = $this->pdo->prepare("
            SELECT HOUR(sale_date) as sales_hour, SUM(total_amount) as hourly_revenue
            FROM sales
            WHERE DATE(sale_date) = ?
            GROUP BY HOUR(sale_date)
            ORDER BY sales_hour ASC
        ");
        $hourly_sales_stmt->execute([$business_date]);
        $hourly_sales = $hourly_sales_stmt->fetchAll();

        return [
            'summary' => $summary,
            'best_sellers' => $best_sellers,
            'category_sales' => $category_sales,
            'cashier_sales' => $cashier_sales,
            'hourly_sales' => $hourly_sales
        ];
    }

    /**
     * Synchronize Inventory Ledger and movement tracking
     */
    public function syncInventory($business_date, $user_id) {
        $this->pdo->beginTransaction();
        try {
            // Find all item quantities sold on the business date
            $items_stmt = $this->pdo->prepare("
                SELECT si.product_id, si.quantity, p.stock_quantity, s.invoice_no
                FROM sale_items si
                JOIN sales s ON si.sale_id = s.id
                JOIN products p ON si.product_id = p.id
                WHERE DATE(s.sale_date) = ?
            ");
            $items_stmt->execute([$business_date]);
            $sold_items = $items_stmt->fetchAll();

            $synced_count = 0;
            foreach ($sold_items as $item) {
                $product_id = $item['product_id'];
                $qty = $item['quantity'];
                $current_stock = $item['stock_quantity'];
                $invoice_no = $item['invoice_no'];

                // Ensure we don't log duplicate sale movements for the same invoice item
                $check_stmt = $this->pdo->prepare("
                    SELECT COUNT(*) FROM inventory_movements 
                    WHERE product_id = ? AND business_date = ? AND reference_id = ? AND movement_type = 'SALE'
                ");
                $check_stmt->execute([$product_id, $business_date, $invoice_no]);
                
                if ($check_stmt->fetchColumn() == 0) {
                    $prev_stock = $current_stock + $qty; // as stock is deducted at checkout
                    
                    // Register the inventory movement log
                    $move_stmt = $this->pdo->prepare("
                        INSERT INTO inventory_movements (product_id, business_date, movement_type, quantity, previous_stock, new_stock, reference_id)
                        VALUES (?, ?, 'SALE', ?, ?, ?, ?)
                    ");
                    $move_stmt->execute([$product_id, $business_date, $qty, $prev_stock, $current_stock, $invoice_no]);
                    $synced_count++;
                }
            }

            // Get low stock alerts
            $low_stock_stmt = $this->pdo->prepare("
                SELECT id, product_name, stock_quantity, reorder_level
                FROM products
                WHERE stock_quantity <= reorder_level
            ");
            $low_stock_stmt->execute();
            $low_stock_items = $low_stock_stmt->fetchAll();

            $this->logAudit($user_id, 'INVENTORY_SYNC', "Synchronized $synced_count sale items to inventory movements.");
            $this->pdo->commit();

            return [
                'synced_items_count' => $synced_count,
                'low_stock_alerts' => $low_stock_items
            ];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Generate & Save Z-Report
     */
    public function generateZReport($business_date, $user_id) {
        $this->pdo->beginTransaction();
        try {
            // Get sales sum
            $sales_stmt = $this->pdo->prepare("
                SELECT 
                    COALESCE(SUM(total_amount), 0) as net,
                    COALESCE(SUM(discount), 0) as discounts,
                    COALESCE(SUM(tax), 0) as tax,
                    COALESCE(SUM(total_amount + discount), 0) as gross
                FROM sales
                WHERE DATE(sale_date) = ?
            ");
            $sales_stmt->execute([$business_date]);
            $sales = $sales_stmt->fetch();

            // Calculate Net Cost of Goods Sold to estimate Net Profit: SUM(selling_price - purchase_price)
            $profit_stmt = $this->pdo->prepare("
                SELECT 
                    COALESCE(SUM((si.selling_price - p.purchase_price) * si.quantity), 0) as net_profit
                FROM sale_items si
                JOIN sales s ON si.sale_id = s.id
                JOIN products p ON si.product_id = p.id
                WHERE DATE(s.sale_date) = ?
            ");
            $profit_stmt->execute([$business_date]);
            $net_profit_estimate = $profit_stmt->fetchColumn() - $sales['discounts'];

            // Delete existing Z-Report for safety
            $del_stmt = $this->pdo->prepare("DELETE FROM z_reports WHERE business_date = ?");
            $del_stmt->execute([$business_date]);

            // Save Z-Report
            $ins_stmt = $this->pdo->prepare("
                INSERT INTO z_reports (business_date, total_sales, gross_sales, net_sales, discounts, tax_amount, net_profit_estimate, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ins_stmt->execute([
                $business_date,
                $sales['net'],
                $sales['gross'],
                $sales['net'],
                $sales['discounts'],
                $sales['tax'],
                $net_profit_estimate,
                $user_id
            ]);

            $this->logAudit($user_id, 'Z_REPORT_GENERATE', "Generated Z-Report for date: $business_date. Net: LKR " . $sales['net']);
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Backup Database Tables (Portable PHP implementation)
     */
    public function backupDatabase($user_id) {
        try {
            $tables = ['users', 'categories', 'brands', 'products', 'customers', 'suppliers', 'purchases', 'purchase_items', 'sales', 'sale_items', 'inventory_logs', 'day_end_sessions', 'cashier_shifts', 'payment_reconciliation', 'z_reports', 'audit_logs', 'inventory_movements'];
            
            $backup_dir = __DIR__ . '/../backups';
            if (!file_exists($backup_dir)) {
                mkdir($backup_dir, 0777, true);
            }

            $filename = 'backup_pos_' . date('Ymd_His') . '.sql';
            $filepath = $backup_dir . '/' . $filename;
            
            $sql_content = "-- DXL POS Database Backup\n";
            $sql_content .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
            
            foreach ($tables as $table) {
                // Drop Table
                $sql_content .= "DROP TABLE IF EXISTS `$table`;\n";
                
                // Show Create Table
                $create_stmt = $this->pdo->query("SHOW CREATE TABLE `$table`")->fetch();
                $sql_content .= $create_stmt['Create Table'] . ";\n\n";
                
                // Fetch table data
                $data_stmt = $this->pdo->query("SELECT * FROM `$table`");
                $rows = $data_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($rows) > 0) {
                    $sql_content .= "INSERT INTO `$table` VALUES \n";
                    $insert_rows = [];
                    foreach ($rows as $row) {
                        $values = [];
                        foreach ($row as $val) {
                            if ($val === null) {
                                $values[] = "NULL";
                            } else {
                                $values[] = $this->pdo->quote($val);
                            }
                        }
                        $insert_rows[] = "(" . implode(", ", $values) . ")";
                    }
                    $sql_content .= implode(",\n", $insert_rows) . ";\n\n";
                }
            }

            file_put_contents($filepath, $sql_content);
            $this->logAudit($user_id, 'DB_BACKUP', "Completed database backup to: $filename");
            return $filename;
        } catch (Exception $e) {
            throw new Exception("Backup execution failed: " . $e->getMessage());
        }
    }

    /**
     * Lock the day's transactions and transition to a new business date
     */
    public function finalizeDayEnd($business_date, $user_id) {
        $this->pdo->beginTransaction();
        try {
            // Get sales metrics for summary save
            $sales_stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(id) as sales_count,
                    COALESCE(SUM(total_amount), 0) as net,
                    COALESCE(SUM(discount), 0) as discounts,
                    COALESCE(SUM(tax), 0) as tax,
                    COALESCE(SUM(total_amount + discount), 0) as gross
                FROM sales
                WHERE DATE(sale_date) = ? AND status != 'CANCELLED'
            ");
            $sales_stmt->execute([$business_date]);
            $sales_metrics = $sales_stmt->fetch();

            // Total Purchases
            $purch_stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(total_amount), 0)
                FROM purchases
                WHERE purchase_date = ?
            ");
            $purch_stmt->execute([$business_date]);
            $total_purchases = floatval($purch_stmt->fetchColumn());

            // Total Cash Payments
            $cash_stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(total_amount), 0)
                FROM sales
                WHERE DATE(sale_date) = ? AND payment_method = 'Cash' AND status != 'CANCELLED'
            ");
            $cash_stmt->execute([$business_date]);
            $total_cash_payments = floatval($cash_stmt->fetchColumn());

            // Total Card Payments
            $card_stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(total_amount), 0)
                FROM sales
                WHERE DATE(sale_date) = ? AND payment_method = 'Card' AND status != 'CANCELLED'
            ");
            $card_stmt->execute([$business_date]);
            $total_card_payments = floatval($card_stmt->fetchColumn());

            // Total Pending Bills count
            $pending_stmt = $this->pdo->prepare("
                SELECT COUNT(id)
                FROM pending_sales
                WHERE status = 'PENDING' AND DATE(created_at) = ?
            ");
            $pending_stmt->execute([$business_date]);
            $total_pending_bills = intval($pending_stmt->fetchColumn());

            // Stock Adjustments count/sum
            $adjust_stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(ABS(quantity)), 0)
                FROM stock_movements
                WHERE movement_type = 'ADJUSTMENT' AND DATE(created_at) = ?
            ");
            $adjust_stmt->execute([$business_date]);
            $stock_adjustments = intval($adjust_stmt->fetchColumn());

            // Closing Balance (Sum of cashier closing drawer cash, or payments reconciled)
            $closing_stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(closing_cash), 0)
                FROM cashier_shifts
                WHERE business_date = ?
            ");
            $closing_stmt->execute([$business_date]);
            $closing_balance = floatval($closing_stmt->fetchColumn());
            if ($closing_balance == 0) {
                // fallback to calculated cash + card if no cashier shifts are closed
                $closing_balance = $total_cash_payments + $total_card_payments;
            }

            // 1. Close current day end session
            $close_stmt = $this->pdo->prepare("
                UPDATE day_end_sessions 
                SET status = 'closed', 
                    closed_at = NOW(), 
                    closed_by = ?, 
                    total_sales = ?, 
                    total_tax = ?, 
                    total_discount = ?, 
                    net_sales = ?,
                    total_expenses = 0.00,
                    total_purchases = ?,
                    total_cash_payments = ?,
                    total_card_payments = ?,
                    total_pending_bills = ?,
                    stock_adjustments = ?,
                    closing_balance = ?
                WHERE business_date = ? AND status = 'open'
            ");
            $close_stmt->execute([
                $user_id,
                $sales_metrics['gross'], // Total sales (gross)
                $sales_metrics['tax'],   // Total tax
                $sales_metrics['discounts'], // Total discounts
                $sales_metrics['net'],   // Net sales (Total Revenue)
                $total_purchases,
                $total_cash_payments,
                $total_card_payments,
                $total_pending_bills,
                $stock_adjustments,
                $closing_balance,
                $business_date
            ]);

            // 2. Open the next business date (tomorrow)
            $next_date = date('Y-m-d', strtotime($business_date . ' + 1 day'));
            
            // Delete if tomorrow's session already exists (e.g. from failed tests)
            $del_stmt = $this->pdo->prepare("DELETE FROM day_end_sessions WHERE business_date = ?");
            $del_stmt->execute([$next_date]);

            // Insert new session
            $open_stmt = $this->pdo->prepare("
                INSERT INTO day_end_sessions (business_date, status, opened_by)
                VALUES (?, 'open', ?)
            ");
            $open_stmt->execute([$next_date, $user_id]);

            // Reset shift counters (all shifts for the tomorrow are closed out, but let's make sure)
            $this->logAudit($user_id, 'DAY_CLOSE_FINAL', "Day finalized and locked for business date: $business_date. Next business date opened: $next_date");
            
            $this->pdo->commit();
            return $next_date;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Reopen a closed business day (Super Admin only)
     */
    public function reopenBusinessDay($business_date, $reason, $user_id) {
        $this->pdo->beginTransaction();
        try {
            // Check if the business day exists and is closed
            $check_stmt = $this->pdo->prepare("SELECT status FROM day_end_sessions WHERE business_date = ?");
            $check_stmt->execute([$business_date]);
            $status = $check_stmt->fetchColumn();
            
            if ($status !== 'closed') {
                throw new Exception("This business date ($business_date) is not closed.");
            }
            
            // Close any currently open session first (since only one session can be active at a time)
            $close_all = $this->pdo->prepare("UPDATE day_end_sessions SET status = 'closed', closed_at = NOW() WHERE status = 'open'");
            $close_all->execute();
            
            // Reopen the target session
            $reopen_stmt = $this->pdo->prepare("
                UPDATE day_end_sessions 
                SET status = 'open',
                    reopened_by = ?,
                    reopened_at = NOW(),
                    reopen_reason = ?
                WHERE business_date = ?
            ");
            $reopen_stmt->execute([$user_id, $reason, $business_date]);
            
            // Create audit log
            $this->logAudit($user_id, 'DAY_REOPENED', "Reopened business date: $business_date. Reason: $reason");
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
