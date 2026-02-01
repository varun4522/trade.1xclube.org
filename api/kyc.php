<?php
session_start();
require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch kyc_enabled setting
$kyc_enabled = true;
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'kyc_enabled'");
    $stmt->execute();
    $kyc_enabled = ($stmt->fetchColumn() ?? 'true') === 'true';
} catch (Exception $e) {
    $kyc_enabled = true; // fallback to enabled if error
}

// Handle GET request - fetch KYC status
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!$kyc_enabled) {
        echo json_encode(['success' => false, 'error' => 'KYC system is currently disabled']);
        exit;
    }
    try {
        $stmt = $pdo->prepare("SELECT * FROM kyc_verification WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$user_id]);
        $kyc = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($kyc) {
            // Don't return file paths for security
            unset($kyc['selfie_path']);
            unset($kyc['id_path']);
        }
        
        echo json_encode([
            'success' => true,
            'kyc' => $kyc
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
}

// Handle POST request - submit KYC
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$kyc_enabled) {
        echo json_encode(['success' => false, 'error' => 'KYC system is currently disabled']);
        exit;
    }
    try {
        // Validate required fields
        if (!isset($_POST['fullName']) || !isset($_POST['dateOfBirth']) || 
            !isset($_FILES['selfieFile']) || !isset($_FILES['idFile'])) {
            echo json_encode(['success' => false, 'error' => 'All fields are required']);
            exit;
        }
        
        $fullName = trim($_POST['fullName']);
        $dateOfBirth = $_POST['dateOfBirth'];
        
        // Validate name
        if (empty($fullName) || strlen($fullName) < 2) {
            echo json_encode(['success' => false, 'error' => 'Please enter a valid full name']);
            exit;
        }
        
        // Validate date of birth
        if (empty($dateOfBirth)) {
            echo json_encode(['success' => false, 'error' => 'Please select your date of birth']);
            exit;
        }
        
        // Check if user already has a pending or approved KYC
        $stmt = $pdo->prepare("SELECT status FROM kyc_verification WHERE user_id = ? AND status IN ('pending', 'approved') ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$user_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            echo json_encode(['success' => false, 'error' => 'You already have a KYC submission in progress or approved']);
            exit;
        }
        
        // Handle file uploads
        $uploadDir = '../uploads/kyc/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Validate and upload selfie
        $selfieFile = $_FILES['selfieFile'];
        if ($selfieFile['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Error uploading selfie']);
            exit;
        }
        
        if (!in_array($selfieFile['type'], ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'])) {
            echo json_encode(['success' => false, 'error' => 'Selfie must be an image file (JPG, PNG, GIF)']);
            exit;
        }
        
        if ($selfieFile['size'] > 5 * 1024 * 1024) { // 5MB limit
            echo json_encode(['success' => false, 'error' => 'Selfie file size must be less than 5MB']);
            exit;
        }
        
        $selfieExt = pathinfo($selfieFile['name'], PATHINFO_EXTENSION);
        $selfieFilename = 'selfie_' . $user_id . '_' . time() . '.' . $selfieExt;
        $selfiePath = $uploadDir . $selfieFilename;
        
        if (!move_uploaded_file($selfieFile['tmp_name'], $selfiePath)) {
            echo json_encode(['success' => false, 'error' => 'Failed to save selfie']);
            exit;
        }
        
        // Validate and upload ID document
        $idFile = $_FILES['idFile'];
        if ($idFile['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Error uploading ID document']);
            exit;
        }
        
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        if (!in_array($idFile['type'], $allowedTypes)) {
            echo json_encode(['success' => false, 'error' => 'ID document must be an image (JPG, PNG) or PDF']);
            exit;
        }
        
        if ($idFile['size'] > 5 * 1024 * 1024) { // 5MB limit
            echo json_encode(['success' => false, 'error' => 'ID document file size must be less than 5MB']);
            exit;
        }
        
        $idExt = pathinfo($idFile['name'], PATHINFO_EXTENSION);
        $idFilename = 'id_' . $user_id . '_' . time() . '.' . $idExt;
        $idPath = $uploadDir . $idFilename;
        
        if (!move_uploaded_file($idFile['tmp_name'], $idPath)) {
            echo json_encode(['success' => false, 'error' => 'Failed to save ID document']);
            exit;
        }
        
        // Save KYC data to database
        $stmt = $pdo->prepare("INSERT INTO kyc_verification (user_id, full_name, date_of_birth, selfie_path, id_path, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute([$user_id, $fullName, $dateOfBirth, $selfiePath, $idPath]);
        
        $kycId = $pdo->lastInsertId();
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'KYC submitted successfully',
            'kyc' => [
                'id' => $kycId,
                'full_name' => $fullName,
                'date_of_birth' => $dateOfBirth,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error occurred']);
    }
}
?> 