<?php
require_once '../config.php';

// Check if user is admin
if (!isLoggedIn() || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

$success_message = '';
$error_message = '';

// Get current settings first
try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM admin_settings");
    $stmt->execute();
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    $error_message = "Failed to load settings: " . $e->getMessage();
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate and sanitize inputs
        $min_bet = floatval($_POST['min_bet'] ?? 10);
        $max_bet = floatval($_POST['max_bet'] ?? 10000);
        $referral_bonus = floatval($_POST['referral_bonus'] ?? 100);
        $maintenance_mode = isset($_POST['maintenance_mode']) ? 'true' : 'false';
        $kyc_enabled = isset($_POST['kyc_enabled']) ? 'true' : 'false';
        $referral_enabled = isset($_POST['referral_enabled']) ? 'true' : 'false';
        
        // --- Payment Gateway Controls ---
        $enable_basepay = isset($_POST['enable_basepay']) ? 'true' : 'false';
        $enable_sunpay = isset($_POST['enable_sunpay']) ? 'true' : 'false';
        $enable_manual = isset($_POST['enable_manual']) ? 'true' : 'false';
        // -------------------------------------

        $upi_id = trim($_POST['upi_id'] ?? '');
        $qr_code_path = $settings['qr_code'] ?? '';
        $bank_details = trim($_POST['bank_details'] ?? '');
        $signup_bonus = floatval($_POST['signup_bonus'] ?? 0);
        $refer_bonus = floatval($_POST['refer_bonus'] ?? 0);
        $min_deposit = floatval($_POST['min_deposit'] ?? 100);
        $max_deposit = floatval($_POST['max_deposit'] ?? 50000);
        $min_withdrawal = floatval($_POST['min_withdrawal'] ?? 500);
        $max_withdrawal = floatval($_POST['max_withdrawal'] ?? 100000);
        $preset_btn_1 = floatval($_POST['preset_btn_1'] ?? 0.5);
        $preset_btn_2 = floatval($_POST['preset_btn_2'] ?? 1);
        $preset_btn_3 = floatval($_POST['preset_btn_3'] ?? 2);
        $preset_btn_4 = floatval($_POST['preset_btn_4'] ?? 7);
        $site_title = trim($_POST['site_title'] ?? 'Trade Club');
        $logo_path = $settings['logo'] ?? 'images/chicken.png';
        $powered_by_logo_path = $settings['powered_by_logo'] ?? 'images/chicken.png';
        $support_link = trim($_POST['support_link'] ?? '');
        $whatsapp_support = trim($_POST['whatsapp_support'] ?? '');
        $email_support = trim($_POST['email_support'] ?? '');

        // Handle QR code upload
        if (isset($_FILES['qr_code']) && $_FILES['qr_code']['error'] === UPLOAD_ERR_OK) {
            $target = '../avatar/qr_code.png';
            if (move_uploaded_file($_FILES['qr_code']['tmp_name'], $target)) {
                $qr_code_path = 'avatar/qr_code.png';
            }
        }

        // Handle logo upload
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            if (!is_dir('../images/')) mkdir('../images/', 0755, true);
            $target = '../images/logo.png';
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $target)) {
                $logo_path = 'images/logo.png';
            }
        }

        // Handle Powered By logo upload
        if (isset($_FILES['powered_by_logo']) && $_FILES['powered_by_logo']['error'] === UPLOAD_ERR_OK) {
            if (!is_dir('../images/')) mkdir('../images/', 0755, true);
            $target = '../images/powered_by_logo.png';
            if (move_uploaded_file($_FILES['powered_by_logo']['tmp_name'], $target)) {
                $powered_by_logo_path = 'images/powered_by_logo.png';
            }
        }

        // Update settings array
        $settings_arr = [
            'min_bet' => $min_bet,
            'max_bet' => $max_bet,
            'referral_bonus' => $referral_bonus,
            'maintenance_mode' => $maintenance_mode,
            'kyc_enabled' => $kyc_enabled,
            'referral_enabled' => $referral_enabled,
            'enable_basepay' => $enable_basepay,
            'enable_sunpay' => $enable_sunpay,
            'enable_manual' => $enable_manual,
            'upi_id' => $upi_id,
            'qr_code' => $qr_code_path,
            'bank_details' => $bank_details,
            'signup_bonus' => $signup_bonus,
            'refer_bonus' => $refer_bonus,
            'min_deposit' => $min_deposit,
            'max_deposit' => $max_deposit,
            'min_withdrawal' => $min_withdrawal,
            'max_withdrawal' => $max_withdrawal,
            'support_link' => $support_link,
            'whatsapp_support' => $whatsapp_support,
            'email_support' => $email_support,
            'preset_btn_1' => $preset_btn_1,
            'preset_btn_2' => $preset_btn_2,
            'preset_btn_3' => $preset_btn_3,
            'preset_btn_4' => $preset_btn_4,
            'site_title' => $site_title,
            'logo' => $logo_path,
            'powered_by_logo' => $powered_by_logo_path
        ];
        
        // Update database
        foreach ($settings_arr as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO admin_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }
        
        $success_message = "Settings updated successfully!";
        
        // Reload settings
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM admin_settings");
        $stmt->execute();
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {
        $error_message = "Failed to update settings: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Settings - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: hsl(258, 90%, 66%); --primary-light: hsl(258, 90%, 72%); }
        html, body { font-family: 'Inter', sans-serif; background: hsl(220, 20%, 8%); color: hsl(0, 0%, 90%); min-height: 100vh; }
        
        /* Custom Checkbox */
        .custom-checkbox {
            appearance: none; -webkit-appearance: none;
            width: 40px; height: 20px;
            background: #374151; border-radius: 20px;
            position: relative; cursor: pointer; outline: none;
            transition: background 0.3s;
        }
        .custom-checkbox::after {
            content: ''; position: absolute; top: 2px; left: 2px;
            width: 16px; height: 16px; background: white; border-radius: 50%;
            transition: transform 0.3s;
        }
        .custom-checkbox:checked { background: #3b82f6; }
        .custom-checkbox:checked::after { transform: translateX(20px); }
    </style>
</head>
<body class="flex flex-col lg:flex-row min-h-screen bg-[#0d1117]">

    <?php include 'sidebar.php'; ?>

    <div class="flex-1 w-full p-4 lg:p-8">
        
        <div class="animate__animated animate__fadeIn">
            <div class="mb-8">
                <h2 class="text-3xl font-bold text-white">Settings</h2>
                <p class="text-gray-400">Configure platform settings and policies</p>
            </div>

            <?php if ($success_message): ?>
                <div class="bg-green-500/10 border border-green-500/20 p-4 rounded-lg mb-6 flex items-center gap-3">
                    <i class="fas fa-check-circle text-green-400"></i>
                    <p class="text-sm text-green-200"><?php echo htmlspecialchars($success_message); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="bg-red-500/10 border border-red-500/20 p-4 rounded-lg mb-6 flex items-center gap-3 animate__animated animate__shakeX">
                    <i class="fas fa-exclamation-triangle text-red-400"></i>
                    <p class="text-sm text-red-200"><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-8 pb-20">
                
                <div class="bg-[#1f2937] p-6 rounded-xl border border-gray-700 shadow-lg">
                    <div class="border-b border-gray-700 pb-4 mb-6">
                        <h3 class="text-lg font-medium text-white flex items-center gap-2">
                            <i class="fas fa-wallet text-blue-400"></i> Payment Gateways
                        </h3>
                    </div>
                    
                    <div class="space-y-4">
                        <label class="flex items-center justify-between cursor-pointer p-4 rounded-lg bg-gray-800 hover:bg-gray-700/50 transition border border-gray-700">
                            <div>
                                <span class="text-white font-medium block">Basepay</span>
                                <span class="text-xs text-gray-500">Enable/Disable Basepay gateway</span>
                            </div>
                            <input type="checkbox" name="enable_basepay" <?php echo ($settings['enable_basepay'] ?? 'true') === 'true' ? 'checked' : ''; ?> class="custom-checkbox">
                        </label>
                        
                        <label class="flex items-center justify-between cursor-pointer p-4 rounded-lg bg-gray-800 hover:bg-gray-700/50 transition border border-gray-700">
                            <div>
                                <span class="text-white font-medium block">Sunpay</span>
                                <span class="text-xs text-gray-500">Enable/Disable Sunpay gateway</span>
                            </div>
                            <input type="checkbox" name="enable_sunpay" <?php echo ($settings['enable_sunpay'] ?? 'true') === 'true' ? 'checked' : ''; ?> class="custom-checkbox">
                        </label>
                        
                        <label class="flex items-center justify-between cursor-pointer p-4 rounded-lg bg-gray-800 hover:bg-gray-700/50 transition border border-gray-700">
                            <div>
                                <span class="text-white font-medium block">Manual Pay (QR)</span>
                                <span class="text-xs text-gray-500">Enable/Disable Manual QR payment</span>
                            </div>
                            <input type="checkbox" name="enable_manual" <?php echo ($settings['enable_manual'] ?? 'true') === 'true' ? 'checked' : ''; ?> class="custom-checkbox">
                        </label>
                    </div>
                </div>

                <div class="bg-[#1f2937] p-6 rounded-xl border border-gray-700 shadow-lg">
                    <div class="border-b border-gray-700 pb-4 mb-6">
                        <h3 class="text-lg font-medium text-white flex items-center gap-2">
                            <i class="fas fa-paint-brush text-purple-500"></i> Branding
                        </h3>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-2">Site Title</label>
                            <input type="text" name="site_title" value="<?php echo htmlspecialchars($settings['site_title'] ?? 'Trade Club'); ?>" 
                                   class="w-full bg-gray-800 border border-gray-600 rounded-lg px-4 py-2.5 text-white focus:border-purple-500 outline-none transition">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-2">Site Logo</label>
                            <div class="flex items-center gap-4">
                                <?php if (!empty($settings['logo'])): ?>
                                    <img src="../<?php echo htmlspecialchars($settings['logo']); ?>" class="h-10 w-10 object-contain bg-gray-700 rounded p-1">
                                <?php endif; ?>
                                <input type="file" name="logo" accept="image/*" class="text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gray-700 file:text-white hover:file:bg-gray-600">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-[#1f2937] p-6 rounded-xl border border-gray-700 shadow-lg">
                    <div class="border-b border-gray-700 pb-4 mb-6">
                        <h3 class="text-lg font-medium text-white flex items-center gap-2">
                            <i class="fas fa-coins text-yellow-500"></i> Financial Limits
                        </h3>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1">Min Deposit</label>
                            <input type="number" name="min_deposit" value="<?php echo $settings['min_deposit'] ?? 100; ?>" class="w-full bg-gray-800 border border-gray-600 rounded-lg px-3 py-2 text-white focus:border-green-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1">Max Deposit</label>
                            <input type="number" name="max_deposit" value="<?php echo $settings['max_deposit'] ?? 50000; ?>" class="w-full bg-gray-800 border border-gray-600 rounded-lg px-3 py-2 text-white focus:border-green-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1">Min Withdrawal</label>
                            <input type="number" name="min_withdrawal" value="<?php echo $settings['min_withdrawal'] ?? 500; ?>" class="w-full bg-gray-800 border border-gray-600 rounded-lg px-3 py-2 text-white focus:border-red-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1">Max Withdrawal</label>
                            <input type="number" name="max_withdrawal" value="<?php echo $settings['max_withdrawal'] ?? 100000; ?>" class="w-full bg-gray-800 border border-gray-600 rounded-lg px-3 py-2 text-white focus:border-red-500 outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-2">Deposit UPI ID</label>
                            <input type="text" name="upi_id" value="<?php echo htmlspecialchars($settings['upi_id'] ?? ''); ?>" placeholder="example@upi" class="w-full bg-gray-800 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-2">QR Code</label>
                            <input type="file" name="qr_code" accept="image/*" class="w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gray-700 file:text-white hover:file:bg-gray-600">
                        </div>
                    </div>

                     <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-400 mb-2">Bank Details (Optional)</label>
                        <textarea name="bank_details" rows="3" class="w-full bg-gray-800 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-blue-500 outline-none" placeholder="Bank Name, Account Number, IFSC..."><?php echo htmlspecialchars($settings['bank_details'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="bg-[#1f2937] p-6 rounded-xl border border-gray-700 shadow-lg">
                    <div class="border-b border-gray-700 pb-4 mb-6">
                        <h3 class="text-lg font-medium text-white flex items-center gap-2">
                            <i class="fas fa-chart-line text-blue-400"></i> Investment Configuration
                        </h3>
                    </div>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1">Preset 1</label>
                            <input type="number" step="0.1" name="preset_btn_1" value="<?php echo $settings['preset_btn_1'] ?? 0.5; ?>" class="w-full bg-gray-800 border border-gray-600 rounded-lg px-3 py-2 text-white text-center">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1">Preset 2</label>
                            <input type="number" step="0.1" name="preset_btn_2" value="<?php echo $settings['preset_btn_2'] ?? 1; ?>" class="w-full bg-gray-800 border border-gray-600 rounded-lg px-3 py-2 text-white text-center">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1">Preset 3</label>
                            <input type="number" step="0.1" name="preset_btn_3" value="<?php echo $settings['preset_btn_3'] ?? 2; ?>" class="w-full bg-gray-800 border border-gray-600 rounded-lg px-3 py-2 text-white text-center">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1">Preset 4</label>
                            <input type="number" step="0.1" name="preset_btn_4" value="<?php echo $settings['preset_btn_4'] ?? 5; ?>" class="w-full bg-gray-800 border border-gray-600 rounded-lg px-3 py-2 text-white text-center">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-2">Min Bet</label>
                            <input type="number" name="min_bet" value="<?php echo $settings['min_bet'] ?? 10; ?>" class="w-full bg-gray-800 border border-gray-600 rounded-lg px-4 py-2 text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-2">Max Bet</label>
                            <input type="number" name="max_bet" value="<?php echo $settings['max_bet'] ?? 10000; ?>" class="w-full bg-gray-800 border border-gray-600 rounded-lg px-4 py-2 text-white">
                        </div>
                    </div>
                </div>

                <div class="bg-[#1f2937] p-6 rounded-xl border border-gray-700 shadow-lg">
                    <div class="border-b border-gray-700 pb-4 mb-6">
                        <h3 class="text-lg font-medium text-white flex items-center gap-2">
                            <i class="fas fa-toggle-on text-green-400"></i> System Control
                        </h3>
                    </div>
                    
                    <div class="space-y-4">
                        <label class="flex items-center justify-between cursor-pointer p-4 rounded-lg bg-gray-800 hover:bg-gray-700/50 transition border border-gray-700">
                            <div>
                                <span class="text-white font-medium block">Maintenance Mode</span>
                                <span class="text-xs text-gray-500">Close site for users (Admins can still access)</span>
                            </div>
                            <input type="checkbox" name="maintenance_mode" <?php echo ($settings['maintenance_mode'] ?? 'false') === 'true' ? 'checked' : ''; ?> class="custom-checkbox">
                        </label>
                        
                        <label class="flex items-center justify-between cursor-pointer p-4 rounded-lg bg-gray-800 hover:bg-gray-700/50 transition border border-gray-700">
                            <div>
                                <span class="text-white font-medium block">KYC Requirement</span>
                                <span class="text-xs text-gray-500">Users must verify identity before withdrawal</span>
                            </div>
                            <input type="checkbox" name="kyc_enabled" <?php echo ($settings['kyc_enabled'] ?? 'true') === 'true' ? 'checked' : ''; ?> class="custom-checkbox">
                        </label>
                        
                        <label class="flex items-center justify-between cursor-pointer p-4 rounded-lg bg-gray-800 hover:bg-gray-700/50 transition border border-gray-700">
                            <div>
                                <span class="text-white font-medium block">Referral System</span>
                                <span class="text-xs text-gray-500">Enable/Disable referral bonuses</span>
                            </div>
                            <input type="checkbox" name="referral_enabled" <?php echo ($settings['referral_enabled'] ?? 'true') === 'true' ? 'checked' : ''; ?> class="custom-checkbox">
                        </label>
                    </div>
                </div>

                <div class="fixed bottom-6 right-6 z-40">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-4 rounded-full shadow-2xl transition-transform hover:scale-105 flex items-center gap-2 font-bold text-lg border border-blue-400/20">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>

            </form>
        </div>
    </div>
</body>
</html>