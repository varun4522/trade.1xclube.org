<?php
require_once '../config.php';

// Check if user is admin
if (!isLoggedIn() || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

$success_message = '';
$error_message = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $controlled_crash_enabled = isset($_POST['controlled_crash_enabled']) ? 'true' : 'false';
        $controlled_crash_rectangle = intval($_POST['controlled_crash_rectangle'] ?? 1);

        // Update settings
        $settings_arr = [
            'controlled_crash_enabled' => $controlled_crash_enabled,
            'controlled_crash_rectangle' => $controlled_crash_rectangle
        ];
        
        foreach ($settings_arr as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO admin_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }
        $success_message = "Controlled crash settings updated successfully!";
    } catch (Exception $e) {
        $error_message = "Failed to update settings";
    }
}

// Get current settings
try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM admin_settings WHERE setting_key IN ('controlled_crash_enabled', 'controlled_crash_rectangle')");
    $stmt->execute();
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    $error_message = "Failed to load settings";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controlled Crash Settings - SGS Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.3s ease-out'
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' }
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(10px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .toggle-checkbox:checked {
            @apply right-0 border-blue-500;
            right: 0;
            border-color: #3B82F6;
        }
        .toggle-checkbox:checked + .toggle-label {
            @apply bg-blue-500;
            background-color: #3B82F6;
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-200 transition-colors duration-300">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="bg-gray-800 text-white w-64 py-6 transform transition-all duration-300 ease-in-out">
            <div class="px-6 animate-fade-in">
                <h1 class="text-2xl font-bold">Admin Panel</h1>
                <p class="text-gray-400 text-sm">Trade Club Game Management</p>
            </div>
            <nav class="mt-8 space-y-1">
                <a href="index.php" class="block px-6 py-3 text-gray-300 hover:bg-gray-700 transition-colors duration-200">Dashboard</a>
                <a href="users.php" class="block px-6 py-3 text-gray-300 hover:bg-gray-700 transition-colors duration-200">Users</a>
                <a href="transactions.php" class="block px-6 py-3 text-gray-300 hover:bg-gray-700 transition-colors duration-200">Transactions</a>
                <a href="bets.php" class="block px-6 py-3 text-gray-300 hover:bg-gray-700 transition-colors duration-200">Bet History</a>
                <a href="kyc.php" class="block px-6 py-3 text-gray-300 hover:bg-gray-700 transition-colors duration-200">KYC Management</a>
                <a href="referral_details.php" class="block px-6 py-3 text-gray-300 hover:bg-gray-700 transition-colors duration-200">Referrals</a>
                <a href="settings.php" class="block px-6 py-3 text-gray-300 hover:bg-gray-700 transition-colors duration-200">Settings</a>
                <a href="controlled_crash.php" class="block px-6 py-3 bg-blue-600 text-white hover:bg-blue-700 transition-colors duration-200">Controlled Crash</a>
                <a href="logout.php" class="block px-6 py-3 text-red-400 hover:bg-gray-700 transition-colors duration-200">Logout</a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 p-8 animate-fade-in">
            <div class="mb-8">
                <h2 class="text-3xl font-bold text-white">Controlled Crash Settings</h2>
                <p class="text-gray-400">Control when the chicken crashes in the game</p>
            </div>

            <?php if ($success_message): ?>
                <div class="bg-green-900 border border-green-700 text-green-100 px-4 py-3 rounded mb-4 animate-slide-up">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="bg-red-900 border border-red-700 text-red-100 px-4 py-3 rounded mb-4 animate-slide-up">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Settings Form -->
            <div class="bg-gray-800 rounded-lg shadow-lg p-6 animate-slide-up transition-all duration-300 hover:shadow-xl">
                <form method="POST">
                    <div class="space-y-6">
                        <div>
                            <div class="flex items-center">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="controlled_crash_enabled" class="sr-only peer" 
                                           <?php echo ($settings['controlled_crash_enabled'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                    <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                    <span class="ml-3 text-lg font-medium text-gray-300">Enable Controlled Crash</span>
                                </label>
                            </div>
                            <p class="text-sm text-gray-400 mt-2">When enabled, the chicken will crash at the specified rectangle number instead of random crashes</p>
                        </div>
                        
                        <div>
                            <label class="block text-lg font-medium text-gray-300 mb-2">Crash Rectangle Number</label>
                            <input type="number" name="controlled_crash_rectangle" value="<?php echo $settings['controlled_crash_rectangle'] ?? 1; ?>" 
                                   class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white focus:ring-blue-500 focus:border-blue-500 transition duration-300" min="1" max="50">
                            <p class="text-sm text-gray-400 mt-2">Enter the rectangle number where the chicken should crash (1 = first rectangle after HOME)</p>
                        </div>
                    </div>
                    
                    <div class="mt-8">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 text-lg font-medium transition-colors duration-300 transform hover:scale-105">
                            Save Settings
                        </button>
                    </div>
                </form>
            </div>

            <!-- Current Status -->
            <div class="mt-8 bg-gray-800 rounded-lg shadow-lg p-6 animate-slide-up transition-all duration-300 hover:shadow-xl">
                <h3 class="text-xl font-bold text-white mb-4">Current Status</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-gray-700 p-4 rounded-lg transition-transform duration-300 hover:scale-101">
                        <h4 class="font-medium text-gray-300 mb-2">Controlled Crash</h4>
                        <div class="text-lg font-semibold">
                            <?php echo ($settings['controlled_crash_enabled'] ?? 'false') === 'true' ? '<span class="text-green-400">✅ Enabled</span>' : '<span class="text-red-400">❌ Disabled</span>'; ?>
                        </div>
                    </div>
                    
                    <div class="bg-gray-700 p-4 rounded-lg transition-transform duration-300 hover:scale-101">
                        <h4 class="font-medium text-gray-300 mb-2">Crash Rectangle</h4>
                        <div class="text-lg font-semibold text-blue-400">
                            <?php echo intval($settings['controlled_crash_rectangle'] ?? 1); ?>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 p-4 bg-blue-900/30 rounded-lg border border-blue-800/50 animate-pulse">
                    <h4 class="font-medium text-blue-300 mb-2">How it works:</h4>
                    <ul class="text-sm text-blue-200 space-y-1">
                        <li class="flex items-start"><span class="mr-2">•</span> <strong>Enabled:</strong> Chicken crashes at the exact rectangle number you specify</li>
                        <li class="flex items-start"><span class="mr-2">•</span> <strong>Disabled:</strong> Chicken crashes randomly (20% chance at each position)</li>
                        <li class="flex items-start"><span class="mr-2">•</span> <strong>Rectangle 1:</strong> First rectangle after HOME</li>
                        <li class="flex items-start"><span class="mr-2">•</span> <strong>Rectangle 2:</strong> Second rectangle after HOME</li>
                        <li class="flex items-start"><span class="mr-2">•</span> <strong>And so on...</strong></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Add animation to form elements on focus
        document.querySelectorAll('input, select, textarea').forEach(element => {
            element.addEventListener('focus', () => {
                element.classList.add('ring-2', 'ring-blue-500');
            });
            element.addEventListener('blur', () => {
                element.classList.remove('ring-2', 'ring-blue-500');
            });
        });
    </script>
</body>
</html>