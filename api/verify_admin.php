<?php
// api/verify_admin.php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$pin = $_POST['pin'] ?? '';

if (empty($pin)) {
    echo json_encode(['success' => false, 'message' => 'Admin PIN required']);
    exit();
}

// Fetch any admin user's PIN to verify
$stmt = $pdo->prepare("SELECT pin FROM users WHERE role = 'admin' LIMIT 1");
$stmt->execute();
$admin = $stmt->fetch();

if ($admin && $admin['pin'] === $pin) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Admin PIN']);
}
