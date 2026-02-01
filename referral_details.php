<?php
require_once '../config.php';

// Check if user is admin
if (!isLoggedIn() || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

// Get pagination parameters
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Get search parameters
$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? 'all'; // all, with_referrals, without_referrals

try {
    $where_conditions = [];
    $params = [];
    
    // Add search condition
    if ($search) {
        $where_conditions[] = "(u.username LIKE ? OR u.phone LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // Add filter conditions
    if ($filter === 'with_referrals') {
        $where_conditions[] = "u.total_referrals > 0";
    } elseif ($filter === 'without_referrals') {
        $where_conditions[] = "u.total_referrals = 0";
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Get total count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users u $where_clause");
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    
    // Get users with referral details
    $sql = "
        SELECT 
            u.*,
            (SELECT COUNT(*) FROM users WHERE referred_by = u.id) as total_referrals,
            (SELECT SUM(referral_earnings) FROM users WHERE referred_by = u.id) as total_referral_earnings,
            (SELECT COUNT(*) FROM bet_history WHERE user_id = u.id) as total_bets,
            (SELECT COUNT(*) FROM transactions WHERE user_id = u.id) as total_transactions,
            referrer.username as referrer_username,
            referrer.phone as referrer_phone
        FROM users u 
        LEFT JOIN users referrer ON u.referred_by = referrer.id
        $where_clause 
        ORDER BY u.total_referrals DESC, u.referral_earnings DESC, u.created_at DESC 
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    $total_pages = ceil($total / $limit);
    
} catch (Exception $e) {
    $error_message = "Failed to fetch referral data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referrals - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        },
                        dark: {
                            800: '#1e293b',
                            900: '#0f172a',
                        }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.3s ease-in-out',
                        'slide-up': 'slideUp 0.3s ease-out',
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-delay-100 {
            animation-delay: 0.1s;
        }
        
        .animate-delay-200 {
            animation-delay: 0.2s;
        }
        
        .dark .bg-gray-100 {
            background-color: #0f172a;
        }
        
        .dark .bg-white {
            background-color: #1e293b;
            color: #f8fafc;
        }
        
        .dark .text-gray-800 {
            color: #f8fafc;
        }
        
        .dark .text-gray-600 {
            color: #94a3b8;
        }
        
        .dark .border-gray-200 {
            border-color: #334155;
        }
        
        .dark .divide-gray-200 {
            border-color: #334155;
        }
        
        .dark .bg-gray-50 {
            background-color: #334155;
            color: #f8fafc;
        }
        
        .dark .text-gray-900 {
            color: #f8fafc;
        }
        
        .dark .text-gray-500 {
            color: #94a3b8;
        }
        
        .dark .hover\:bg-gray-50:hover {
            background-color: #334155;
        }
        
        .dark .bg-blue-500 {
            background-color: #0ea5e9;
        }
        
        .dark .bg-gray-800 {
            background-color: #020617;
        }
        
        .dark .text-white {
            color: #f8fafc;
        }
        
        .dark .text-gray-300 {
            color: #94a3b8;
        }
        
        .dark .hover\:bg-gray-700:hover {
            background-color: #1e293b;
        }
        
        .dark .bg-gray-700 {
            background-color: #1e293b;
        }
        
        .transition-all {
            transition-property: all;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
            transition-duration: 150ms;
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 transition-all duration-300">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="bg-gray-800 text-white w-64 py-6 flex-shrink-0">
            <div class="px-6">
                <h1 class="text-2xl font-bold animate__animated animate__fadeIn">Admin Panel</h1>
                <p class="text-gray-400 text-sm animate__animated animate__fadeIn animate-delay-100">Trade Club Game Management</p>
            </div>
            <nav class="mt-8 space-y-1">
                <a href="index.php" class="block px-6 py-3 text-gray-300 hover:bg-gray-700 transition-all duration-200 rounded-r-lg animate__animated animate__fadeInLeft animate-delay-100">Dashboard</a>
                <a href="users.php" class="block px-6 py-3 text-gray-300 hover:bg-gray-700 transition-all duration-200 rounded-r-lg animate__animated animate__fadeInLeft animate-delay-150">Users</a>
                <a href="transactions.php" class="block px-6 py-3 text-gray-300 hover:bg-gray-700 transition-all duration-200 rounded-r-lg animate__animated animate__fadeInLeft animate-delay-200">Transactions</a>
                <a href="bets.php" class="block px-6 py-3 text-gray-300 hover:bg-gray-700 transition-all duration-200 rounded-r-lg animate__animated animate__fadeInLeft animate-delay-250">Bet History</a>
                <a href="kyc.php" class="block px-6 py-3 text-gray-300 hover:bg-gray-700 transition-all duration-200 rounded-r-lg animate__animated animate__fadeInLeft animate-delay-300">KYC Management</a>
                <a href="referral_details.php" class="block px-6 py-3 bg-gray-700 text-white transition-all duration-200 rounded-r-lg animate__animated animate__fadeInLeft animate-delay-350">Referrals</a>
                <a href="settings.php" class="block px-6 py-3 text-gray-300 hover:bg-gray-700 transition-all duration-200 rounded-r-lg animate__animated animate__fadeInLeft animate-delay-400">Settings</a>
                <a href="controlled_crash.php" class="block px-6 py-3 text-gray-300 hover:bg-gray-700 transition-all duration-200 rounded-r-lg animate__animated animate__fadeInLeft animate-delay-450">Controlled Crash</a>
                <a href="logout.php" class="block px-6 py-3 text-red-400 hover:bg-gray-700 transition-all duration-200 rounded-r-lg animate__animated animate__fadeInLeft animate-delay-500">Logout</a>
            </nav>
            
            <!-- Dark mode toggle -->
            <div class="absolute bottom-4 left-4">
                <button id="darkModeToggle" class="p-2 rounded-full bg-gray-700 text-gray-300 hover:bg-gray-600 transition-all">
                    <svg id="darkIcon" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
                    </svg>
                    <svg id="lightIcon" class="w-5 h-5 hidden" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 p-8 animate__animated animate__fadeIn animate-delay-100">
            <div class="mb-8">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-3xl font-bold text-gray-800 dark:text-white">Referrals</h2>
                        <p class="text-gray-600 dark:text-gray-400">Comprehensive view of users and their referral activities</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500 dark:text-gray-400"><?php echo date('l, F j, Y'); ?></span>
                    </div>
                </div>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 animate__animated animate__fadeIn dark:bg-red-900 dark:border-red-700 dark:text-red-100">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <?php
                try {
                    // Total users
                    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 0");
                    $total_users = $stmt->fetchColumn();
                    
                    // Users with referrals
                    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE total_referrals > 0 AND is_admin = 0");
                    $users_with_referrals = $stmt->fetchColumn();
                    
                    // Total referral earnings
                    $stmt = $pdo->query("SELECT SUM(referral_earnings) FROM users WHERE is_admin = 0");
                    $total_referral_earnings = $stmt->fetchColumn() ?: 0;
                    
                    // Average referrals per user
                    $avg_referrals = $total_users > 0 ? round($users_with_referrals / $total_users * 100, 1) : 0;
                } catch (Exception $e) {
                    $total_users = $users_with_referrals = $total_referral_earnings = $avg_referrals = 0;
                }
                ?>
                
                <!-- Total Users Card -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 transition-all duration-300 hover:shadow-lg animate__animated animate__fadeIn animate-delay-100">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900 dark:text-blue-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Total Users</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo number_format($total_users); ?></p>
                            <div class="h-1 w-full bg-gray-200 mt-2 rounded-full overflow-hidden">
                                <div class="h-full bg-blue-500" style="width: 100%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- With Referrals Card -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 transition-all duration-300 hover:shadow-lg animate__animated animate__fadeIn animate-delay-200">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600 dark:bg-green-900 dark:text-green-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-300">With Referrals</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo number_format($users_with_referrals); ?></p>
                            <p class="text-sm text-gray-500 dark:text-gray-400"><?php echo $avg_referrals; ?>% of users</p>
                            <div class="h-1 w-full bg-gray-200 mt-2 rounded-full overflow-hidden">
                                <div class="h-full bg-green-500" style="width: <?php echo $avg_referrals; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Earnings Card -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 transition-all duration-300 hover:shadow-lg animate__animated animate__fadeIn animate-delay-300">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 dark:bg-yellow-900 dark:text-yellow-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Total Earnings</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">₹<?php echo number_format($total_referral_earnings, 2); ?></p>
                            <div class="h-1 w-full bg-gray-200 mt-2 rounded-full overflow-hidden">
                                <div class="h-full bg-yellow-500" style="width: <?php echo min(100, $total_referral_earnings / 1000); ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Avg Referrals Card -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 transition-all duration-300 hover:shadow-lg animate__animated animate__fadeIn animate-delay-400">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600 dark:bg-purple-900 dark:text-purple-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Avg Referrals</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                <?php 
                                try {
                                    $stmt = $pdo->query("SELECT AVG(total_referrals) FROM users WHERE total_referrals > 0 AND is_admin = 0");
                                    $avg = $stmt->fetchColumn();
                                    echo number_format($avg, 1);
                                } catch (Exception $e) {
                                    echo "0.0";
                                }
                                ?>
                            </p>
                            <div class="h-1 w-full bg-gray-200 mt-2 rounded-full overflow-hidden">
                                <div class="h-full bg-purple-500" style="width: <?php echo min(100, $avg * 10); ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6 transition-all duration-300 animate__animated animate__fadeIn animate-delay-200">
                <form method="GET" class="flex flex-col md:flex-row gap-4">
                    <div class="flex-1 relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search by username or phone" 
                               class="pl-10 w-full border border-gray-300 dark:border-gray-600 rounded-lg px-4 py-2 bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                    </div>
                    
                    <select name="filter" class="border border-gray-300 dark:border-gray-600 rounded-lg px-4 py-2 bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Users</option>
                        <option value="with_referrals" <?php echo $filter === 'with_referrals' ? 'selected' : ''; ?>>With Referrals</option>
                        <option value="without_referrals" <?php echo $filter === 'without_referrals' ? 'selected' : ''; ?>>Without Referrals</option>
                    </select>
                    
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition-all duration-300 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        Filter
                    </button>
                    <a href="referral_details.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition-all duration-300 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50 flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Clear
                    </a>
                </form>
            </div>

            <!-- Users Table -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden transition-all duration-300 animate__animated animate__fadeIn animate-delay-300">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <h3 class="text-lg font-medium text-gray-800 dark:text-white">User Referral Details</h3>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Showing <?php echo count($users); ?> of <?php echo number_format($total); ?> users
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Referrer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Referrals</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Earnings</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Activity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Joined</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($users as $index => $user): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-all duration-200 animate__animated animate__fadeIn" style="animation-delay: <?php echo ($index % 10) * 50 + 300; ?>ms">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 relative">
                                                <img class="h-10 w-10 rounded-full" src="../<?php echo htmlspecialchars($user['avatar']); ?>" alt="">
                                                <?php if ($user['total_referrals'] > 0): ?>
                                                    <span class="absolute -bottom-1 -right-1 bg-green-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
                                                        <?php echo $user['total_referrals']; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($user['username']); ?></div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($user['phone']); ?></div>
                                                <div class="text-xs text-gray-400 dark:text-gray-500"><?php echo htmlspecialchars($user['referral_code']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($user['referrer_username']): ?>
                                            <div class="text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($user['referrer_username']); ?></div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($user['referrer_phone']); ?></div>
                                        <?php else: ?>
                                            <span class="text-gray-400 dark:text-gray-500 text-sm">No referrer</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-white"><?php echo $user['total_referrals']; ?> users</div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">₹<?php echo number_format($user['total_referral_earnings'], 2); ?> earned</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-white">₹<?php echo number_format($user['referral_earnings'], 2); ?></div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">Personal earnings</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-white"><?php echo $user['total_bets']; ?> bets</div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo $user['total_transactions']; ?> transactions</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                        </div>
                                        <div class="text-xs text-gray-400 dark:text-gray-500">
                                            <?php echo date('h:i A', strtotime($user['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="viewReferrals(<?php echo $user['id']; ?>)" 
                                                class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-2 transition-all duration-200 transform hover:scale-110">
                                            <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                            View
                                        </button>
                                        <button onclick="viewDetails(<?php echo $user['id']; ?>)" 
                                                class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300 transition-all duration-200 transform hover:scale-110">
                                            <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            Details
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="mt-6 flex justify-center animate__animated animate__fadeIn animate-delay-500">
                    <nav class="flex items-center space-x-2">
                        <a href="?page=<?php echo max(1, $page - 1); ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>" 
                           class="px-4 py-2 border border-gray-300 rounded-md bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-all <?php echo $page <= 1 ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                            &laquo; Previous
                        </a>
                        
                        <?php 
                        // Show first page
                        if ($page > 3): ?>
                            <a href="?page=1&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>" 
                               class="px-4 py-2 border border-gray-300 rounded-md bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-all">
                                1
                            </a>
                            <?php if ($page > 4): ?>
                                <span class="px-4 py-2 text-gray-500">...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php 
                        // Show pages around current page
                        for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>" 
                               class="px-4 py-2 border rounded-md transition-all <?php echo $i === $page ? 'bg-blue-500 border-blue-500 text-white' : 'bg-white dark:bg-gray-800 border-gray-300 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php 
                        // Show last page
                        if ($page < $total_pages - 2): ?>
                            <?php if ($page < $total_pages - 3): ?>
                                <span class="px-4 py-2 text-gray-500">...</span>
                            <?php endif; ?>
                            <a href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>" 
                               class="px-4 py-2 border border-gray-300 rounded-md bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-all">
                                <?php echo $total_pages; ?>
                            </a>
                        <?php endif; ?>
                        
                        <a href="?page=<?php echo min($total_pages, $page + 1); ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>" 
                           class="px-4 py-2 border border-gray-300 rounded-md bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-all <?php echo $page >= $total_pages ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                            Next &raquo;
                        </a>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Referral Details Modal -->
    <div id="referralModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 transition-opacity duration-300">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto transform transition-all duration-300 scale-95 opacity-0" id="modalContent">
                <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Referral Details</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div id="referralContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Dark mode toggle
        const darkModeToggle = document.getElementById('darkModeToggle');
        const darkIcon = document.getElementById('darkIcon');
        const lightIcon = document.getElementById('lightIcon');
        
        // Check for saved user preference or system preference
        if (localStorage.getItem('darkMode') === 'true' || 
            (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
            darkIcon.classList.add('hidden');
            lightIcon.classList.remove('hidden');
        }
        
        darkModeToggle.addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
            const isDark = document.documentElement.classList.contains('dark');
            localStorage.setItem('darkMode', isDark);
            
            if (isDark) {
                darkIcon.classList.add('hidden');
                lightIcon.classList.remove('hidden');
            } else {
                darkIcon.classList.remove('hidden');
                lightIcon.classList.add('hidden');
            }
        });
        
        function viewReferrals(userId) {
            const modal = document.getElementById('referralModal');
            const modalContent = document.getElementById('modalContent');
            
            // Show modal with animation
            modal.classList.remove('hidden');
            setTimeout(() => {
                modalContent.classList.remove('scale-95', 'opacity-0');
                modalContent.classList.add('scale-100', 'opacity-100');
            }, 10);
            
            // Show loading state
            document.getElementById('referralContent').innerHTML = `
                <div class="flex justify-center items-center py-12">
                    <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
                </div>
            `;
            
            // Load referral details via AJAX
            fetch(`get_referral_details.php?user_id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('referralContent').innerHTML = data.html;
                    } else {
                        document.getElementById('referralContent').innerHTML = `
                            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 dark:bg-red-900 dark:border-red-700 dark:text-red-100">
                                Failed to load referral details: ${data.message || 'Unknown error'}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('referralContent').innerHTML = `
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 dark:bg-red-900 dark:border-red-700 dark:text-red-100">
                            Network error occurred while loading referral details
                        </div>
                    `;
                });
        }

        function viewDetails(userId) {
            // Open in new tab
            window.open(`users.php?user_id=${userId}`, '_blank');
        }

        function closeModal() {
            const modal = document.getElementById('referralModal');
            const modalContent = document.getElementById('modalContent');
            
            // Animate out
            modalContent.classList.remove('scale-100', 'opacity-100');
            modalContent.classList.add('scale-95', 'opacity-0');
            
            // Hide after animation
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        // Close modal when clicking outside
        document.getElementById('referralModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Add animation to table rows on hover
        document.querySelectorAll('tbody tr').forEach(row => {
            row.addEventListener('mouseenter', () => {
                row.classList.add('transform', 'hover:-translate-y-0.5', 'hover:shadow-md');
            });
            row.addEventListener('mouseleave', () => {
                row.classList.remove('transform', 'hover:-translate-y-0.5', 'hover:shadow-md');
            });
        });
    </script>
</body>
</html>