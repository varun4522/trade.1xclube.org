<?php
require_once '../config.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables
$search = isset($_GET['search']) ? trim(filter_var($_GET['search'], FILTER_SANITIZE_STRING)) : '';
$user = null;
$referrer = null;
$referrals = [];
$success_message = '';
$error_message = '';

// Handle update referral earnings or referrer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }
    
    $user_id = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
    if ($user_id === false) {
        $error_message = 'Invalid user ID';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Update referral earnings if provided
            if (isset($_POST['referral_earnings'])) {
                $earnings = filter_var($_POST['referral_earnings'], FILTER_VALIDATE_FLOAT);
                if ($earnings !== false) {
                    $stmt = $pdo->prepare('UPDATE users SET referral_earnings = ? WHERE id = ?');
                    $stmt->execute([$earnings, $user_id]);
                    $success_message = 'Referral earnings updated.';
                    
                    // Log this action
                    logAdminAction($_SESSION['user_id'], "Updated referral earnings for user #$user_id to $earnings");
                }
            }
            
            // Update referrer if provided
            if (isset($_POST['referrer_id'])) {
                $referrer_id = filter_var($_POST['referrer_id'], FILTER_VALIDATE_INT);
                $stmt = $pdo->prepare('UPDATE users SET referred_by = ? WHERE id = ?');
                $stmt->execute([$referrer_id ?: null, $user_id]);
                $success_message = 'Referrer updated.';
                
                // Log this action
                $action = $referrer_id ? "Set referrer for user #$user_id to #$referrer_id" : "Removed referrer for user #$user_id";
                logAdminAction($_SESSION['user_id'], $action);
            }
            
            $pdo->commit();
            
            // Redirect to avoid form resubmission
            header('Location: referrals.php?search=' . urlencode($search));
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = 'Database error: ' . $e->getMessage();
            error_log("Referral update error: " . $e->getMessage());
        }
    }
}

// Search for user
if ($search) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? OR phone = ? LIMIT 1');
        $stmt->execute([$search, $search]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Get referrer
            if ($user['referred_by']) {
                $stmt = $pdo->prepare('SELECT id, username, phone FROM users WHERE id = ?');
                $stmt->execute([$user['referred_by']]);
                $referrer = $stmt->fetch();
            }
            
            // Get users referred by this user
            $stmt = $pdo->prepare('SELECT id, username, phone, referral_earnings, created_at FROM users WHERE referred_by = ? ORDER BY created_at DESC');
            $stmt->execute([$user['id']]);
            $referrals = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        $error_message = 'Failed to search user';
        error_log("User search error: " . $e->getMessage());
    }
}

// Get top referrers
try {
    $stmt = $pdo->query('SELECT id, username, phone, total_referrals, referral_earnings FROM users WHERE total_referrals > 0 ORDER BY total_referrals DESC, referral_earnings DESC LIMIT 10');
    $top_referrers = $stmt->fetchAll();
} catch (Exception $e) {
    $error_message = 'Failed to load top referrers';
    error_log("Top referrers error: " . $e->getMessage());
    $top_referrers = [];
}

// Function to log admin actions
function logAdminAction($admin_id, $action) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, ip_address, user_agent) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $admin_id,
            $action,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        error_log("Failed to log admin action: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Management - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: hsl(258, 90%, 66%);
            --primary-light: hsl(258, 90%, 72%);
            --glow: 0 0 15px hsla(258, 90%, 66%, 0.5);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: hsl(220, 20%, 8%);
            color: hsl(0, 0%, 90%);
        }
        
        .sidebar {
            background: hsl(220, 15%, 12%);
            border-right: 1px solid hsl(220, 15%, 18%);
        }
        
        .nav-item {
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .nav-item:hover {
            background: hsl(220, 15%, 18%);
        }
        
        .nav-item.active {
            background: hsl(220, 15%, 20%);
            border-left: 3px solid var(--primary);
        }
        
        .card {
            background: hsl(220, 15%, 16%);
            border: 1px solid hsl(220, 15%, 20%);
            transition: all 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--glow);
            border-color: hsl(220, 15%, 25%);
        }
        
        .table-row {
            transition: all 0.2s;
        }
        
        .table-row:hover {
            background: hsl(220, 15%, 20%) !important;
        }
        
        .action-btn {
            transition: all 0.2s;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .modal-overlay {
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            animation: modalFadeIn 0.3s ease-out;
        }
        
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .filter-btn {
            transition: all 0.2s;
        }
        
        .filter-btn:hover {
            transform: translateY(-1px);
        }
        
        .pagination-btn {
            transition: all 0.2s;
        }
        
        .pagination-btn:hover {
            background: hsl(220, 15%, 25%);
        }
        
        .badge {
            display: inline-block;
            padding: 0.25em 0.4em;
            font-size: 75%;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
        }
        
        .badge-primary {
            color: #fff;
            background-color: var(--primary);
        }
    </style>
</head>
<body class="flex min-h-screen">
    <!-- Sidebar -->
    <div class="sidebar w-64 flex-shrink-0 py-6 animate__animated animate__fadeInLeft">
        <div class="px-6">
            <div class="flex items-center space-x-3 mb-6">
                <div class="w-10 h-10 rounded-full bg-gradient-to-r from-purple-500 to-indigo-600 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-bold">Admin Panel</h1>
                    <p class="text-xs text-gray-400">Referral Management</p>
                </div>
            </div>
            
            <nav class="mt-8 space-y-1">
                <a href="index.php" class="nav-item block px-6 py-3 flex items-center space-x-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span>Dashboard</span>
                </a>
                <a href="users.php" class="nav-item block px-6 py-3 flex items-center space-x-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <span>Users</span>
                </a>
                <a href="transactions.php" class="nav-item block px-6 py-3 flex items-center space-x-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Transactions</span>
                </a>
                <a href="bets.php" class="nav-item block px-6 py-3 flex items-center space-x-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <span>Bet History</span>
                </a>
                <a href="kyc.php" class="nav-item block px-6 py-3 flex items-center space-x-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <span>KYC Management</span>
                </a>
                <a href="referrals.php" class="nav-item active block px-6 py-3 flex items-center space-x-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h3a2 2 0 012 2v5a2 2 0 01-2 2h-3m-3 0H8a2 2 0 01-2-2V9a2 2 0 012-2h3m-1 4l2.5 2.5L15 11m-5-4l2.5 2.5L15 7" />
                    </svg>
                    <span>Referrals</span>
                </a>
                <a href="referral_details.php" class="nav-item block px-6 py-3 flex items-center space-x-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span>Referral Details</span>
                </a>
                <a href="settings.php" class="nav-item block px-6 py-3 flex items-center space-x-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>Settings</span>
                </a>
                <a href="controlled_crash.php" class="nav-item block px-6 py-3 flex items-center space-x-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span>Controlled Crash</span>
                </a>
                <a href="logout.php" class="nav-item block px-6 py-3 flex items-center space-x-3 text-red-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span>Logout</span>
                </a>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 p-8 animate__animated animate__fadeIn">
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-white">Referral Management</h2>
            <p class="text-gray-400">Search users, view and manage referrals</p>
        </div>

        <?php if ($success_message): ?>
            <div class="bg-green-900/50 border border-green-700/50 p-4 rounded-lg animate__animated animate__fadeIn mb-6">
                <div class="flex items-center">
                    <svg class="h-5 w-5 text-green-400 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <div class="ml-3">
                        <p class="text-sm text-green-200"><?php echo htmlspecialchars($success_message); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="bg-red-900/50 border border-red-700/50 p-4 rounded-lg animate__animated animate__shakeX mb-6">
                <div class="flex items-center">
                    <svg class="h-5 w-5 text-red-400 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                    <div class="ml-3">
                        <p class="text-sm text-red-200"><?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Search Form -->
        <div class="card mb-6">
            <form method="GET" class="flex flex-wrap gap-4 p-6">
                <div class="flex-1">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by username or phone" 
                           class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>
                <button type="submit" class="filter-btn bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded transition duration-200">
                    Search
                </button>
                <a href="referral_details.php" class="filter-btn bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition duration-200">
                    View Detailed Referrals
                </a>
            </form>
        </div>

        <?php if ($user): ?>
            <!-- User Referral Details -->
            <div class="card mb-8">
                <div class="px-6 py-4 border-b border-gray-800">
                    <h3 class="text-xl font-bold text-white">User: <?php echo htmlspecialchars($user['username']); ?></h3>
                    <p class="text-gray-400"><?php echo htmlspecialchars($user['phone']); ?></p>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <h4 class="text-lg font-semibold text-white mb-3">Referral Information</h4>
                            <div class="bg-gray-800/50 rounded-lg p-4 space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Referral Code:</span>
                                    <span class="font-mono text-purple-400"><?php echo htmlspecialchars($user['referral_code']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Total Referrals:</span>
                                    <span class="text-white"><?php echo $user['total_referrals']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Referral Earnings:</span>
                                    <span class="text-white">₹<?php echo number_format($user['referral_earnings'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="text-lg font-semibold text-white mb-3">Update Referral Settings</h4>
                            <form method="POST" class="bg-gray-800/50 rounded-lg p-4 space-y-4">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-1">Referral Earnings</label>
                                    <input type="number" name="referral_earnings" value="<?php echo $user['referral_earnings']; ?>" step="0.01" 
                                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-purple-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-1">Referrer</label>
                                    <select name="referrer_id" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-purple-500">
                                        <option value="">None</option>
                                        <?php
                                        try {
                                            $all_users = $pdo->query('SELECT id, username FROM users WHERE is_admin = 0 ORDER BY username')->fetchAll();
                                            foreach ($all_users as $u) {
                                                $selected = ($referrer && $u['id'] == $referrer['id']) ? 'selected' : '';
                                                if ($u['id'] != $user['id']) {
                                                    echo "<option value='{$u['id']}' $selected>{$u['username']}</option>";
                                                }
                                            }
                                        } catch (Exception $e) {
                                            error_log("Failed to load users: " . $e->getMessage());
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded transition duration-200">
                                    Update Settings
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="text-lg font-semibold text-white mb-3">Referrer</h4>
                            <?php if ($referrer): ?>
                                <div class="bg-gray-800/50 rounded-lg p-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gray-700 flex items-center justify-center">
                                            <span class="text-white"><?php echo strtoupper(substr($referrer['username'], 0, 1)); ?></span>
                                        </div>
                                        <div>
                                            <div class="text-white"><?php echo htmlspecialchars($referrer['username']); ?></div>
                                            <div class="text-gray-400 text-sm"><?php echo htmlspecialchars($referrer['phone']); ?></div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="bg-gray-800/50 rounded-lg p-4 text-gray-400">
                                    No referrer assigned
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <h4 class="text-lg font-semibold text-white mb-3">Users Referred (<?php echo count($referrals); ?>)</h4>
                            <?php if ($referrals): ?>
                                <div class="bg-gray-800/50 rounded-lg overflow-hidden">
                                    <table class="min-w-full divide-y divide-gray-700">
                                        <thead class="bg-gray-900">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">User</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Earnings</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Date</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-700">
                                            <?php foreach ($referrals as $r): ?>
                                                <tr class="hover:bg-gray-700/50">
                                                    <td class="px-4 py-3 whitespace-nowrap">
                                                        <div class="flex items-center space-x-2">
                                                            <div class="flex-shrink-0 h-8 w-8 rounded-full bg-gray-700 flex items-center justify-center">
                                                                <span class="text-xs text-white"><?php echo strtoupper(substr($r['username'], 0, 1)); ?></span>
                                                            </div>
                                                            <div>
                                                                <div class="text-sm text-white"><?php echo htmlspecialchars($r['username']); ?></div>
                                                                <div class="text-xs text-gray-400"><?php echo htmlspecialchars($r['phone']); ?></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-white">
                                                        ₹<?php echo number_format($r['referral_earnings'], 2); ?>
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-400">
                                                        <?php echo date('M j, Y', strtotime($r['created_at'])); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="bg-gray-800/50 rounded-lg p-4 text-gray-400">
                                    No users referred yet
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif ($search): ?>
            <div class="card p-6 mb-8">
                <div class="text-center py-8">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 class="text-lg font-medium text-white mt-4">User not found</h3>
                    <p class="text-gray-400 mt-1">No user found with username or phone matching "<?php echo htmlspecialchars($search); ?>"</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Top Referrers -->
        <div class="card">
            <div class="px-6 py-4 border-b border-gray-800">
                <h3 class="text-lg font-medium text-white">Top Referrers</h3>
                <p class="text-gray-400">Users with the most successful referrals</p>
            </div>
            <div class="p-6">
                <?php if ($top_referrers): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-700">
                            <thead class="bg-gray-900">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Rank</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Total Referrals</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Referral Earnings</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                <?php foreach ($top_referrers as $index => $tr): ?>
                                    <tr class="hover:bg-gray-700/50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="badge badge-primary">#<?php echo $index + 1; ?></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center space-x-3">
                                                <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gray-700 flex items-center justify-center">
                                                    <span class="text-white"><?php echo strtoupper(substr($tr['username'], 0, 1)); ?></span>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-medium text-white"><?php echo htmlspecialchars($tr['username']); ?></div>
                                                    <div class="text-sm text-gray-400"><?php echo htmlspecialchars($tr['phone']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-white">
                                            <?php echo $tr['total_referrals']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-white">
                                            ₹<?php echo number_format($tr['referral_earnings'], 2); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="referrals.php?search=<?php echo urlencode($tr['username']); ?>" class="text-purple-400 hover:text-purple-300">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <h3 class="text-lg font-medium text-white mt-4">No referral data yet</h3>
                        <p class="text-gray-400 mt-1">There are no users with referrals at this time</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Animate table rows on scroll
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate__fadeIn');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1
        });

        document.querySelectorAll('.table-row').forEach((el, index) => {
            el.style.setProperty('--animate-delay', `${index * 0.05}s`);
            observer.observe(el);
        });
    </script>
</body>
</html>