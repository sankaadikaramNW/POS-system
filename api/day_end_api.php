<?php
// api/day_end_api.php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Authentication and Authorization Check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Only administrators are permitted to perform the Day End process.']);
    exit();
}

require_once __DIR__ . '/day_end_service.php';

try {
    $service = new DayEndService($pdo);
    
    // Get the current active business date or default to today's date
    $current_date_stmt = $pdo->query("SELECT business_date FROM day_end_sessions WHERE status = 'open' ORDER BY business_date DESC LIMIT 1");
    $business_date = $current_date_stmt->fetchColumn() ?: date('Y-m-d');

    $action = $_GET['action'] ?? '';
    $user_id = $_SESSION['user_id'];

    switch ($action) {
        case 'verify_transactions':
            $pending = $service->getPendingTransactions($business_date);
            echo json_encode([
                'success' => true,
                'business_date' => $business_date,
                'pending_count' => count($pending),
                'pending_transactions' => $pending
            ]);
            break;

        case 'get_shifts':
            $shifts = $service->getCashierShifts($business_date);
            echo json_encode([
                'success' => true,
                'business_date' => $business_date,
                'shifts' => $shifts
            ]);
            break;

        case 'close_shift':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            $cashier_id = intval($_POST['cashier_id'] ?? 0);
            $opening_cash = floatval($_POST['opening_cash'] ?? 0.00);
            $closing_cash = floatval($_POST['closing_cash'] ?? 0.00);

            if ($cashier_id <= 0) {
                throw new Exception('Missing or invalid cashier selection');
            }

            $success = $service->closeCashierShift($cashier_id, $business_date, $opening_cash, $closing_cash, $user_id);
            echo json_encode(['success' => true, 'message' => 'Cashier shift reconciled and closed successfully.']);
            break;

        case 'reconcile_payments':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $gateway_totals = [
                'Cash' => floatval($_POST['gateway_cash'] ?? 0.00),
                'Card' => floatval($_POST['gateway_card'] ?? 0.00),
                'Mobile' => floatval($_POST['gateway_mobile'] ?? 0.00),
            ];

            $results = $service->reconcilePayments($business_date, $gateway_totals, $user_id);
            echo json_encode([
                'success' => true,
                'message' => 'Payment Reconciliation complete.',
                'reconciliation' => $results
            ]);
            break;

        case 'get_sales_summary':
            $data = $service->getSalesSummary($business_date);
            echo json_encode([
                'success' => true,
                'business_date' => $business_date,
                'data' => $data
            ]);
            break;

        case 'sync_inventory':
            $results = $service->syncInventory($business_date, $user_id);
            echo json_encode([
                'success' => true,
                'message' => 'Inventory synchronized successfully.',
                'results' => $results
            ]);
            break;

        case 'generate_z_report':
            $success = $service->generateZReport($business_date, $user_id);
            echo json_encode([
                'success' => true,
                'message' => 'Z-Report compiled and persisted successfully.'
            ]);
            break;

        case 'run_backup':
            $filename = $service->backupDatabase($user_id);
            echo json_encode([
                'success' => true,
                'message' => 'System tables backed up successfully.',
                'backup_file' => $filename
            ]);
            break;

        case 'finalize_day':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }

            // Safety check: Prevent duplicate finalizations
            $check_open = $pdo->prepare("SELECT COUNT(*) FROM day_end_sessions WHERE business_date = ? AND status = 'open'");
            $check_open->execute([$business_date]);
            if ($check_open->fetchColumn() == 0) {
                throw new Exception("This business date ($business_date) is already finalized or does not exist.");
            }

            $next_date = $service->finalizeDayEnd($business_date, $user_id);
            echo json_encode([
                'success' => true,
                'message' => "Business day $business_date locked. Next business date is: $next_date",
                'next_business_date' => $next_date
            ]);
            break;

        default:
            throw new Exception("Unknown action: " . htmlspecialchars($action));
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
