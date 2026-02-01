<?php
header('Content-Type: application/json');
require_once '../config.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Handle POST requests for updating user data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = getCurrentUserId();
    
    try {
        if (isset($input['avatar'])) {
            $avatar = sanitize($input['avatar']);
            $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $stmt->execute([$avatar, $user_id]);
            
            echo json_encode(['success' => true, 'message' => 'Avatar updated successfully']);
            exit;
        }
        
        echo json_encode(['success' => false, 'error' => 'Invalid update request']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to update user data']);
        exit;
    }
}

// Handle GET requests for fetching user data
try {
    $user_id = getCurrentUserId();
    
    $stmt = $pdo->prepare("SELECT id, username, phone, balance, avatar, referral_code, total_referrals, referral_earnings, is_admin, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }

    // Check referral_enabled setting
    $referral_enabled = true;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'referral_enabled'");
        $stmt->execute();
        $referral_enabled = ($stmt->fetchColumn() ?? 'true') === 'true';
    } catch (Exception $e) {
        $referral_enabled = true;
    }
    if (!$referral_enabled) {
        unset($user['referral_code']);
        unset($user['total_referrals']);
        unset($user['referral_earnings']);
    }

    echo json_encode([
        'success' => true,
        'user' => $user
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to fetch user data']);
}
?> 