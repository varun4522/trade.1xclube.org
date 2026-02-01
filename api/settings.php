<?php
header('Content-Type: application/json');
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Get all settings from database
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM admin_settings");
    $stmt->execute();
    
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Return all settings
    echo json_encode([
        'success' => true,
        'settings' => [
            'min_bet' => floatval($settings['min_bet'] ?? 10),
            'max_bet' => floatval($settings['max_bet'] ?? 10000),
            'min_deposit' => floatval($settings['min_deposit'] ?? 300),
            'max_deposit' => floatval($settings['max_deposit'] ?? 50000),
            'min_withdrawal' => floatval($settings['min_withdrawal'] ?? 420),
            'max_withdrawal' => floatval($settings['max_withdrawal'] ?? 50000),
            'referral_bonus' => floatval($settings['referral_bonus'] ?? 100),
            'signup_bonus' => floatval($settings['signup_bonus'] ?? 0),
            'refer_bonus' => floatval($settings['refer_bonus'] ?? 0),
            'maintenance_mode' => ($settings['maintenance_mode'] ?? 'false') === 'true',
            'upi_id' => $settings['upi_id'] ?? '',
            'qr_code' => $settings['qr_code'] ?? '',
            'bank_details' => $settings['bank_details'] ?? '',
            'support_link' => $settings['support_link'] ?? '',
            'preset_btn_1' => floatval($settings['preset_btn_1'] ?? 0.5),
            'preset_btn_2' => floatval($settings['preset_btn_2'] ?? 1),
            'preset_btn_3' => floatval($settings['preset_btn_3'] ?? 2),
            'preset_btn_4' => floatval($settings['preset_btn_4'] ?? 7),
            'controlled_crash_enabled' => ($settings['controlled_crash_enabled'] ?? 'false') === 'true',
            'controlled_crash_rectangle' => intval($settings['controlled_crash_rectangle'] ?? 1),
            'site_title' => $settings['site_title'] ?? 'Trade Club',
            'logo' => $settings['logo'] ?? 'images/chicken.png',
            'powered_by_logo' => $settings['powered_by_logo'] ?? 'images/chicken.png',
            'kyc_enabled' => ($settings['kyc_enabled'] ?? 'true') === 'true',
            'referral_enabled' => ($settings['referral_enabled'] ?? 'true') === 'true'
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to load settings']);
}
?> 