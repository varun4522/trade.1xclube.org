<?php
header('Content-Type: application/json');
require_once '../config.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$new_balance = floatval($input['balance'] ?? 0);

if ($new_balance < 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid balance']);
    exit;
}

try {
    $user_id = getCurrentUserId();
    
    // Update user balance
    $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
    $stmt->execute([$new_balance, $user_id]);
    
    echo json_encode([
        'success' => true,
        'balance' => $new_balance
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to update balance']);
}
?> 