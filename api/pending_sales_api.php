<?php
// api/pending_sales_api.php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit();
}

$action = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'unknown';

switch ($action) {
    case 'hold':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
            exit();
        }

        $cart_data_json = $_POST['cart_data'] ?? '';
        if (empty($cart_data_json) || $cart_data_json === '[]') {
            echo json_encode(['success' => false, 'message' => 'Prevent empty carts from being held.']);
            exit();
        }

        $customer_id = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
        $customer_name = $_POST['customer_name'] ?? 'Walk-in Customer';
        $subtotal = floatval($_POST['subtotal'] ?? 0);
        $discount_amount = floatval($_POST['discount_amount'] ?? 0);
        $tax_amount = floatval($_POST['tax_amount'] ?? 0);
        $grand_total = floatval($_POST['grand_total'] ?? 0);
        $notes = $_POST['notes'] ?? '';

        try {
            $pdo->beginTransaction();

            // Generate Sequential Unique Hold Bill Number (HB-000001 format)
            // Query for secure ID generation
            $stmt = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM pending_sales");
            $next_id = $stmt->fetchColumn();
            
            $hold_bill_no = 'HB-' . str_pad($next_id, 6, '0', STR_PAD_LEFT);

            // Double check uniqueness
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM pending_sales WHERE hold_bill_no = ?");
            $check_stmt->execute([$hold_bill_no]);
            if ($check_stmt->fetchColumn() > 0) {
                // If by some race condition it exists, try to get a random addition or fail to ensure consistency
                $hold_bill_no = 'HB-' . str_pad($next_id + rand(1, 99), 6, '0', STR_PAD_LEFT);
            }

            // Insert pending sale
            $ins_stmt = $pdo->prepare("
                INSERT INTO pending_sales 
                (hold_bill_no, customer_id, customer_name, cart_data_json, subtotal, discount_amount, tax_amount, grand_total, notes, status, cashier_id, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', ?, NOW(), NOW())
            ");
            $ins_stmt->execute([
                $hold_bill_no,
                $customer_id,
                $customer_name,
                $cart_data_json,
                $subtotal,
                $discount_amount,
                $tax_amount,
                $grand_total,
                $notes,
                $user_id
            ]);

            // Add Audit Trail Log: Bill Held
            $log_stmt = $pdo->prepare("
                INSERT INTO pending_sales_logs (user_id, username, action, log_date, log_time, bill_no) 
                VALUES (?, ?, 'Bill Held', CURDATE(), CURTIME(), ?)
            ");
            $log_stmt->execute([$user_id, $username, $hold_bill_no]);

            $pdo->commit();

            // Get active count
            $cnt_stmt = $pdo->query("SELECT COUNT(*) FROM pending_sales WHERE status = 'PENDING'");
            $active_count = $cnt_stmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'message' => 'Bill held successfully.',
                'hold_bill_no' => $hold_bill_no,
                'active_count' => $active_count
            ]);

        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
        }
        break;

    case 'count':
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM pending_sales WHERE status = 'PENDING'");
            $count = $stmt->fetchColumn();
            echo json_encode(['success' => true, 'count' => $count]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'list':
        $search = $_GET['search'] ?? '';
        $start_date = $_GET['start_date'] ?? '';
        $end_date = $_GET['end_date'] ?? '';
        $sort = $_GET['sort'] ?? 'created_at_desc';
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = max(1, intval($_GET['limit'] ?? 10));
        $offset = ($page - 1) * $limit;

        $conditions = ["status = 'PENDING'"];
        $params = [];

        if (!empty($search)) {
            $conditions[] = "(hold_bill_no LIKE ? OR customer_name LIKE ? OR notes LIKE ?)";
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }

        if (!empty($start_date)) {
            $conditions[] = "DATE(created_at) >= ?";
            $params[] = $start_date;
        }

        if (!empty($end_date)) {
            $conditions[] = "DATE(created_at) <= ?";
            $params[] = $end_date;
        }

        $where = implode(' AND ', $conditions);

        // Sorting mapping
        $order_by = "created_at DESC";
        if ($sort === 'created_at_asc') {
            $order_by = "created_at ASC";
        } elseif ($sort === 'amount_desc') {
            $order_by = "grand_total DESC";
        } elseif ($sort === 'amount_asc') {
            $order_by = "grand_total ASC";
        }

        try {
            // Count total
            $count_query = "SELECT COUNT(*) FROM pending_sales WHERE $where";
            $c_stmt = $pdo->prepare($count_query);
            $c_stmt->execute($params);
            $total_records = $c_stmt->fetchColumn();
            $total_pages = ceil($total_records / $limit);

            // Fetch records
            $fetch_query = "
                SELECT ps.*, u.full_name as cashier_name 
                FROM pending_sales ps
                LEFT JOIN users u ON ps.cashier_id = u.id
                WHERE $where
                ORDER BY $order_by
                LIMIT $limit OFFSET $offset
            ";
            $f_stmt = $pdo->prepare($fetch_query);
            $f_stmt->execute($params);
            $bills = $f_stmt->fetchAll();

            // Enrich each bill with standard formatted wait duration indicator
            $now = new DateTime();
            foreach ($bills as &$bill) {
                $hold_time = new DateTime($bill['created_at']);
                $interval = $now->diff($hold_time);
                $minutes_elapsed = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
                $bill['minutes_elapsed'] = $minutes_elapsed;
            }

            echo json_encode([
                'success' => true,
                'bills' => $bills,
                'pagination' => [
                    'current_page' => $page,
                    'limit' => $limit,
                    'total_records' => $total_records,
                    'total_pages' => $total_pages
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'details':
        $hold_bill_no = $_GET['hold_bill_no'] ?? '';
        if (empty($hold_bill_no)) {
            echo json_encode(['success' => false, 'message' => 'Hold Bill Number is required.']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("
                SELECT ps.*, u.full_name as cashier_name 
                FROM pending_sales ps 
                LEFT JOIN users u ON ps.cashier_id = u.id 
                WHERE ps.hold_bill_no = ?
            ");
            $stmt->execute([$hold_bill_no]);
            $bill = $stmt->fetch();

            if (!$bill) {
                echo json_encode(['success' => false, 'message' => 'Hold bill not found.']);
                exit();
            }

            echo json_encode([
                'success' => true,
                'bill' => $bill
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'resume':
        $hold_bill_no = $_GET['hold_bill_no'] ?? '';
        if (empty($hold_bill_no)) {
            echo json_encode(['success' => false, 'message' => 'Hold Bill Number is required.']);
            exit();
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM pending_sales WHERE hold_bill_no = ?");
            $stmt->execute([$hold_bill_no]);
            $bill = $stmt->fetch();

            if (!$bill) {
                throw new Exception('Hold bill not found.');
            }

            if ($bill['status'] !== 'PENDING') {
                throw new Exception('Prevent completed or cancelled bills from being resumed.');
            }

            // Update status / timestamp
            $upd_stmt = $pdo->prepare("UPDATE pending_sales SET resumed_at = NOW(), updated_at = NOW() WHERE hold_bill_no = ?");
            $upd_stmt->execute([$hold_bill_no]);

            // Add Audit Trail Log: Bill Resumed
            $log_stmt = $pdo->prepare("
                INSERT INTO pending_sales_logs (user_id, username, action, log_date, log_time, bill_no) 
                VALUES (?, ?, 'Bill Resumed', CURDATE(), CURTIME(), ?)
            ");
            $log_stmt->execute([$user_id, $username, $hold_bill_no]);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'bill' => $bill
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'cancel':
        $hold_bill_no = $_GET['hold_bill_no'] ?? '';
        if (empty($hold_bill_no)) {
            echo json_encode(['success' => false, 'message' => 'Hold Bill Number is required.']);
            exit();
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM pending_sales WHERE hold_bill_no = ?");
            $stmt->execute([$hold_bill_no]);
            $bill = $stmt->fetch();

            if (!$bill) {
                throw new Exception('Hold bill not found.');
            }

            if ($bill['status'] !== 'PENDING') {
                throw new Exception('Only active PENDING bills can be cancelled.');
            }

            // Update status / timestamp
            $upd_stmt = $pdo->prepare("UPDATE pending_sales SET status = 'CANCELLED', cancelled_at = NOW(), updated_at = NOW() WHERE hold_bill_no = ?");
            $upd_stmt->execute([$hold_bill_no]);

            // Add Audit Trail Log: Bill Cancelled
            $log_stmt = $pdo->prepare("
                INSERT INTO pending_sales_logs (user_id, username, action, log_date, log_time, bill_no) 
                VALUES (?, ?, 'Bill Cancelled', CURDATE(), CURTIME(), ?)
            ");
            $log_stmt->execute([$user_id, $username, $hold_bill_no]);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Pending bill cancelled successfully.'
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action specifier.']);
        break;
}
?>
