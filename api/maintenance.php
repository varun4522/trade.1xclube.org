<?php
header('Content-Type: application/json');
require_once '../config.php';

$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];

try {
    $stmt = $pdo->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'maintenance_mode'");
    $stmt->execute();
    $maintenance = $stmt->fetchColumn();
    $maintenance = ($maintenance === 'true');
    echo json_encode([
        'success' => true,
        'maintenance' => $maintenance,
        'is_admin' => $is_admin
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to fetch maintenance status']);
} 