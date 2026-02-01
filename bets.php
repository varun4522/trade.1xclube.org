<?php
require_once '../config.php';

// Check if user is admin
if (!isLoggedIn() || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

// Get bet history with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$result_filter = sanitize($_GET['result'] ?? '');
$user_filter = intval($_GET['user_id'] ?? 0);

try {
    $where_conditions = [];
    $params = [];
    
    if ($result_filter) {
        $where_conditions[] = "bh.result = ?";
        $params[] = $result_filter;
    }
    
    if ($user_filter) {
        $where_conditions[] = "bh.user_id = ?";
        $params[] = $user_filter;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Get total count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bet_history bh $where_clause");
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    
    // Get bet history
    $sql = "
        SELECT bh.*, u.username, u.phone, u.avatar
        FROM bet_history bh 
        JOIN users u ON bh.user_id = u.id 
        $where_clause 
        ORDER BY bh.created_at DESC 
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bets = $stmt->fetchAll();
    
    $total_pages = ceil($total / $limit);
    
    // Get users for filter
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE is_admin = 0 ORDER BY username");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error_message = "Failed to fetch bet history: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bet History - Admin Panel</title>
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
        
        .win-badge {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(74, 222, 128, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(74, 222, 128, 0); }
            100% { box-shadow: 0 0 0 0 rgba(74, 222, 128, 0); }
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
                    <p class="text-xs text-gray-400">Mines Game Management</p>
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
                <a href="bets.php" class="nav-item active block px-6 py-3 flex items-center space-x-3">
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
                <a href="referral_details.php" class="nav-item block px-6 py-3 flex items-center space-x-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h3a2 2 0 012 2v5a2 2 0 01-2 2h-3m-3 0H8a2 2 0 01-2-2V9a2 2 0 012-2h3m-1 4l2.5 2.5L15 11m-5-4l2.5 2.5L15 7" />
                    </svg>
                    <span>Referrals</span>
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
            <h2 class="text-3xl font-bold text-white">Bet History</h2>
            <p class="text-gray-400">View all user bets</p>
        </div>

        <?php if (isset($error_message)): ?>
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

        <!-- Filters -->
        <div class="card mb-6">
            <form method="GET" class="flex flex-wrap gap-4 p-6">
                <select name="result" class="bg-gray-800 border border-gray-700 rounded px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-purple-500">
                    <option value="">All Results</option>
                    <option value="win" <?php echo $result_filter === 'win' ? 'selected' : ''; ?>>Win</option>
                    <option value="loss" <?php echo $result_filter === 'loss' ? 'selected' : ''; ?>>Loss</option>
                    <option value="cashout" <?php echo $result_filter === 'cashout' ? 'selected' : ''; ?>>Cashout</option>
                </select>
                <select name="user_id" class="bg-gray-800 border border-gray-700 rounded px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-purple-500">
                    <option value="">All Users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['username']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="filter-btn bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded transition duration-200">
                    Apply Filters
                </button>
                <a href="bets.php" class="filter-btn bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded transition duration-200">
                    Clear Filters
                </a>
            </form>
        </div>

        <!-- Bet History Table -->
        <div class="card mb-8">
            <div class="px-6 py-4 border-b border-gray-800 flex justify-between items-center">
                <h3 class="text-lg font-medium text-white">Bet History</h3>
                <div class="relative">
                    <input type="text" placeholder="Search bets..." class="bg-gray-800 border border-gray-700 rounded px-4 py-2 pl-10 text-white focus:outline-none focus:ring-2 focus:ring-purple-500">
                    <svg class="h-5 w-5 text-gray-400 absolute left-3 top-2.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-800">
                    <thead class="bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Bet Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Bombs</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Result</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Multiplier</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Winnings</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        <?php foreach ($bets as $bet): ?>
                            <tr class="table-row animate__animated animate__fadeIn">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gray-800 flex items-center justify-center overflow-hidden">
                                            <?php if (!empty($bet['avatar'])): ?>
                                                <img src="../<?php echo htmlspecialchars($bet['avatar']); ?>" alt="<?php echo htmlspecialchars($bet['username']); ?>" class="h-full w-full object-cover">
                                            <?php else: ?>
                                                <span class="text-white"><?php echo strtoupper(substr($bet['username'], 0, 1)); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-white"><?php echo htmlspecialchars($bet['username']); ?></div>
                                            <div class="text-sm text-gray-400"><?php echo htmlspecialchars($bet['phone']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-white">
                                    ₹<?php echo number_format($bet['bet_amount'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-white">
                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-800 border border-purple-500">
                                        <?php echo $bet['bomb_count']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $bet['result'] === 'win' ? 'bg-green-900 text-green-200 win-badge' : 
                                              ($bet['result'] === 'loss' ? 'bg-red-900 text-red-200' : 'bg-yellow-900 text-yellow-200'); ?>">
                                        <?php echo ucfirst($bet['result']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-white">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-900 text-purple-200">
                                        x<?php echo number_format($bet['multiplier'], 2); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-white">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $bet['winnings'] > 0 ? 'bg-green-900 text-green-200' : 'bg-gray-700 text-gray-300'; ?>">
                                        ₹<?php echo number_format($bet['winnings'], 2); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                                    <?php echo date('M j, Y H:i', strtotime($bet['created_at'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="flex justify-center items-center space-x-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&result=<?php echo urlencode($result_filter); ?>&user_id=<?php echo $user_filter; ?>" 
                       class="pagination-btn px-4 py-2 border border-gray-700 rounded bg-gray-800 text-gray-300 hover:bg-gray-700">
                        Previous
                    </a>
                <?php endif; ?>
                
                <div class="flex space-x-1">
                    <?php 
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    if ($start > 1) {
                        echo '<a href="?page=1&result='.urlencode($result_filter).'&user_id='.$user_filter.'" class="pagination-btn px-3 py-1 rounded">1</a>';
                        if ($start > 2) echo '<span class="px-2 py-1">...</span>';
                    }
                    
                    for ($i = $start; $i <= $end; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&result=<?php echo urlencode($result_filter); ?>&user_id=<?php echo $user_filter; ?>" 
                           class="pagination-btn px-3 py-1 rounded <?php echo $i === $page ? 'bg-purple-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; 
                    
                    if ($end < $total_pages) {
                        if ($end < $total_pages - 1) echo '<span class="px-2 py-1">...</span>';
                        echo '<a href="?page='.$total_pages.'&result='.urlencode($result_filter).'&user_id='.$user_filter.'" class="pagination-btn px-3 py-1 rounded">'.$total_pages.'</a>';
                    }
                    ?>
                </div>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&result=<?php echo urlencode($result_filter); ?>&user_id=<?php echo $user_filter; ?>" 
                       class="pagination-btn px-4 py-2 border border-gray-700 rounded bg-gray-800 text-gray-300 hover:bg-gray-700">
                        Next
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
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