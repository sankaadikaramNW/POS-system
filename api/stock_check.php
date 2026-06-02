<?php
/**
 * api/stock_check.php
 * DXL Fashion POS — Real-time Stock Validation API
 *
 * Accepts an array of {id, qty} items and returns current DB stock for each.
 * Used by the POS when recalling a held bill to refresh maxStock values
 * and warn the cashier if any items are now out-of-stock or under-stocked.
 *
 * POST body:
 *   items  = JSON string: [{id: 1, qty: 2}, {id: 5, qty: 1}, ...]
 *
 * Response:
 *   {
 *     success: true,
 *     items: [
 *       { id, product_name, requested_qty, current_stock, sufficient, image, selling_price }
 *     ],
 *     all_sufficient: bool,
 *     insufficient_count: int
 *   }
 */

session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['items'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request. POST items[] required.']);
    exit();
}

$raw_items = json_decode($_POST['items'], true);
if (!is_array($raw_items) || empty($raw_items)) {
    echo json_encode(['success' => false, 'message' => 'No items provided.']);
    exit();
}

try {
    // Build a single query to fetch all product stock in one round-trip
    $ids      = array_map(fn($i) => (int)$i['id'], $raw_items);
    $ids      = array_filter($ids, fn($id) => $id > 0);

    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'No valid product IDs.']);
        exit();
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("
        SELECT id, product_name, stock_quantity, selling_price, image
        FROM products
        WHERE id IN ($placeholders)
    ");
    $stmt->execute(array_values($ids));
    $db_products = $stmt->fetchAll();

    // Index by ID for fast lookup
    $db_map = [];
    foreach ($db_products as $p) {
        $db_map[(int)$p['id']] = $p;
    }

    $result_items     = [];
    $insufficient_cnt = 0;

    foreach ($raw_items as $item) {
        $item_id  = (int)($item['id']  ?? 0);
        $item_qty = (int)($item['qty'] ?? 1);

        if (!isset($db_map[$item_id])) {
            // Product no longer exists in DB
            $result_items[] = [
                'id'            => $item_id,
                'product_name'  => $item['name'] ?? 'Unknown',
                'requested_qty' => $item_qty,
                'current_stock' => 0,
                'sufficient'    => false,
                'product_exists'=> false,
                'selling_price' => 0,
                'image'         => 'default.png'
            ];
            $insufficient_cnt++;
            continue;
        }

        $db  = $db_map[$item_id];
        $ok  = ((int)$db['stock_quantity']) >= $item_qty;
        if (!$ok) $insufficient_cnt++;

        $result_items[] = [
            'id'            => $item_id,
            'product_name'  => $db['product_name'],
            'requested_qty' => $item_qty,
            'current_stock' => (int)$db['stock_quantity'],
            'sufficient'    => $ok,
            'product_exists'=> true,
            'selling_price' => (float)$db['selling_price'],
            'image'         => $db['image'] ?? 'default.png'
        ];
    }

    echo json_encode([
        'success'           => true,
        'items'             => $result_items,
        'all_sufficient'    => $insufficient_cnt === 0,
        'insufficient_count'=> $insufficient_cnt
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}
?>
