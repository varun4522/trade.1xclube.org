<?php
header('Content-Type: application/json');
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$username = sanitize($input['username'] ?? '');
$phone = sanitize($input['phone'] ?? '');
$password = $input['password'] ?? '';
$referral_code = sanitize($input['referral_code'] ?? '');

// Validation
if (empty($username) || empty($phone) || empty($password)) {
    echo json_encode(['success' => false, 'error' => 'All fields are required']);
    exit;
}

if (strlen($username) < 3 || strlen($username) > 20) {
    echo json_encode(['success' => false, 'error' => 'Username must be 3-20 characters']);
    exit;
}

if (!preg_match('/^\d{10}$/', $phone)) {
    echo json_encode(['success' => false, 'error' => 'Phone number must be 10 digits']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters']);
    exit;
}

try {
    // Check if username already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Username already exists']);
        exit;
    }

    // Check if phone already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->execute([$phone]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Phone number already exists']);
        exit;
    }

    // Referral System logic
    $referred_by = null;
    $referral_enabled = true;

    if (!empty($referral_code)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
        $stmt->execute([$referral_code]);
        $referrer = $stmt->fetch();
        if ($referrer) {
            $referred_by = $referrer['id'];
        }
    }

    // Generate unique referral code
    do {
        $new_referral_code = generateReferralCode();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
        $stmt->execute([$new_referral_code]);
    } while ($stmt->fetch());

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // --- BALANCE FIX START ---
    // Yahan aap fixed amount set kar sakte hain. 
    // Agar 0 dena hai toh 0 rakhein, agar bonus dena hai toh amount likhein.
    $signup_bonus = 0.00; 
    $refer_bonus = 0.00; 
    // --- BALANCE FIX END ---

    // Insert new user
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, phone, password, referral_code, referred_by, balance) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $phone, $hashed_password, $new_referral_code, $referred_by, $signup_bonus]);
        $user_id = $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("User insertion error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Registration failed.']);
        exit;
    }

    // Referral Bonus Logic (If applicable)
    if ($referred_by && $refer_bonus > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET referral_earnings = referral_earnings + ?, total_referrals = total_referrals + 1, balance = balance + ? WHERE id = ?");
            $stmt->execute([$refer_bonus, $refer_bonus, $referred_by]);
            
            $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$refer_bonus, $user_id]);
        } catch (Exception $e) {
            error_log("Referral bonus error");
        }
    }

    // Session Start
    session_start();
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;

    echo json_encode([
        'success' => true, 
        'message' => 'Registration successful!',
        'user' => [
            'id' => $user_id,
            'username' => $username,
            'phone' => $phone
        ]
    ]);

} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server Error.']);
}
?>