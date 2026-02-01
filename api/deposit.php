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

// Accept form-data for file upload
$amount = floatval($_POST['amount'] ?? 0);
$method = sanitize($_POST['deposit_method'] ?? '');
$description = sanitize($_POST['description'] ?? '');
$transaction_id = sanitize($_POST['transaction_id'] ?? '');

// Validate amount
if ($amount < 10) {
    echo json_encode(['success' => false, 'error' => 'Minimum deposit amount is ₹10']);
    exit;
}
if ($amount > 100000) {
    echo json_encode(['success' => false, 'error' => 'Maximum deposit amount is ₹100,000']);
    exit;
}
// Validate method
if (empty($method)) {
    echo json_encode(['success' => false, 'error' => 'Payment method is required']);
    exit;
}
// Validate transaction id
if (empty($transaction_id)) {
    echo json_encode(['success' => false, 'error' => 'Transaction ID is required']);
    exit;
}
// Handle screenshot upload
$screenshot_path = null;
if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!in_array($_FILES['screenshot']['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'error' => 'Invalid screenshot file type. Only JPG, PNG, and GIF are allowed.']);
        exit;
    }
    if ($_FILES['screenshot']['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'Screenshot file is too large. Maximum size is 5MB.']);
        exit;
    }
    $uploads_dir = '../uploads/';
    if (!is_dir($uploads_dir)) {
        mkdir($uploads_dir, 0755, true);
    }
    $ext = pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION);
    $filename = 'deposit_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    $target = $uploads_dir . $filename;
    if (move_uploaded_file($_FILES['screenshot']['tmp_name'], $target)) {
        $screenshot_path = 'uploads/' . $filename;
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to upload screenshot.']);
        exit;
    }
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("
        INSERT INTO transactions (user_id, type, amount, method, description, status, created_at, deposit_method, transaction_id, screenshot)
        VALUES (?, 'deposit', ?, ?, ?, 'pending', NOW(), ?, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $amount,
        $method,
        $description,
        $method, // deposit_method (for now, same as method)
        $transaction_id,
        $screenshot_path
    ]);
    $pdo->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Deposit request submitted successfully. It will be processed by admin.',
        'transaction_id' => $pdo->lastInsertId()
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Deposit error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to process deposit request']);
}
?> 