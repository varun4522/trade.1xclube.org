<?php
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$amount = floatval($input['amount'] ?? 0);
$method = sanitize($input['method'] ?? '');
$description = sanitize($input['description'] ?? '');

// Validate amount
if ($amount < 100) {
    echo json_encode(['success' => false, 'error' => 'Minimum withdrawal amount is ₹100']);
    exit;
}

if ($amount > 50000) {
    echo json_encode(['success' => false, 'error' => 'Maximum withdrawal amount is ₹50,000']);
    exit;
}

// Validate method
if (empty($method)) {
    echo json_encode(['success' => false, 'error' => 'Payment method is required']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Check user balance
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    if ($user['balance'] < $amount) {
        echo json_encode(['success' => false, 'error' => 'Insufficient balance']);
        exit;
    }
    
    // Deduct amount from balance
    $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
    $stmt->execute([$amount, $_SESSION['user_id']]);
    
    // Create transaction record
    $stmt = $pdo->prepare("
        INSERT INTO transactions (user_id, type, amount, method, description, status, created_at) 
        VALUES (?, 'withdrawal', ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $amount, $method, $description]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Withdrawal request submitted successfully. It will be processed by admin.',
        'transaction_id' => $pdo->lastInsertId()
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Withdrawal error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to process withdrawal request']);
}
?> 