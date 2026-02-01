<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config.php';

// Check if user is admin
if (!isLoggedIn() || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

// Handle transaction processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_id = intval($_POST['transaction_id'] ?? 0);
    $action = sanitize($_POST['action'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    
    if ($transaction_id && in_array($action, ['approve', 'reject'])) {
        try {
            $pdo->beginTransaction();
            
            // Get transaction details
            $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND status = 'pending'");
            $stmt->execute([$transaction_id]);
            $transaction = $stmt->fetch();
            
            if ($transaction) {
                $new_status = $action === 'approve' ? 'approved' : 'rejected';
                
                // Update transaction status
                $stmt = $pdo->prepare("UPDATE transactions SET status = ?, admin_notes = ?, processed_by = ?, processed_at = NOW() WHERE id = ?");
                $stmt->execute([$new_status, $notes, $_SESSION['user_id'], $transaction_id]);
                
                // If approved deposit, add to user balance
                if ($action === 'approve' && $transaction['type'] === 'deposit') {
                    $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                    $stmt->execute([$transaction['amount'], $transaction['user_id']]);
                }
                
                // If rejected withdrawal, return money to user balance
                if ($action === 'reject' && $transaction['type'] === 'withdrawal') {
                    $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                    $stmt->execute([$transaction['amount'], $transaction['user_id']]);
                }
                
                $pdo->commit();
                $success_message = "Transaction " . $action . "d successfully";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "Failed to process transaction";
        }
    }
}

// Get transactions with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$status_filter = sanitize($_GET['status'] ?? '');
$type_filter = sanitize($_GET['type'] ?? '');

$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "t.status = ?";
    $params[] = $status_filter;
}

if ($type_filter) {
    $where_conditions[] = "t.type = ?";
    $params[] = $type_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions t $where_clause");
$stmt->execute($params);
$total = $stmt->fetchColumn();

// Get transactions
$sql = "
    SELECT t.*, u.username, u.phone, admin.username as processed_by_username 
    FROM transactions t 
    JOIN users u ON t.user_id = u.id 
    LEFT JOIN users admin ON t.processed_by = admin.id 
    $where_clause 
    ORDER BY t.created_at DESC 
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$total_pages = ceil($total / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - Admin Panel</title>
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
                <a href="transactions.php" class="nav-item active block px-6 py-3 flex items-center space-x-3">
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
            <h2 class="text-3xl font-bold text-white">Transactions</h2>
            <p class="text-gray-400">Manage deposits and withdrawals</p>
        </div>

        <?php if (isset($success_message)): ?>
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
                <select name="status" class="bg-gray-800 border border-gray-700 rounded px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-purple-500">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
                <select name="type" class="bg-gray-800 border border-gray-700 rounded px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-purple-500">
                    <option value="">All Types</option>
                    <option value="deposit" <?php echo $type_filter === 'deposit' ? 'selected' : ''; ?>>Deposit</option>
                    <option value="withdrawal" <?php echo $type_filter === 'withdrawal' ? 'selected' : ''; ?>>Withdrawal</option>
                </select>
                <button type="submit" class="filter-btn bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded transition duration-200">
                    Apply Filters
                </button>
                <a href="transactions.php" class="filter-btn bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded transition duration-200">
                    Clear Filters
                </a>
            </form>
        </div>

        <!-- Transactions Table -->
        <div class="card mb-8">
            <div class="px-6 py-4 border-b border-gray-800 flex justify-between items-center">
                <h3 class="text-lg font-medium text-white">Transaction List</h3>
                <div class="relative">
                    <input type="text" placeholder="Search transactions..." class="bg-gray-800 border border-gray-700 rounded px-4 py-2 pl-10 text-white focus:outline-none focus:ring-2 focus:ring-purple-500">
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Method</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Txn ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Screenshot</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        <?php foreach ($transactions as $transaction): ?>
                            <tr class="table-row animate__animated animate__fadeIn">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gray-800 flex items-center justify-center overflow-hidden">
                                            <span class="text-white"><?php echo strtoupper(substr($transaction['username'], 0, 1)); ?></span>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-white"><?php echo htmlspecialchars($transaction['username']); ?></div>
                                            <div class="text-sm text-gray-400"><?php echo htmlspecialchars($transaction['phone']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $transaction['type'] === 'deposit' ? 'bg-green-900 text-green-200' : 'bg-yellow-900 text-yellow-200'; ?>">
                                        <?php echo ucfirst($transaction['type']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-white">
                                    ₹<?php echo number_format($transaction['amount'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-white">
                                    <?php echo htmlspecialchars($transaction['method']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                                    <?php echo htmlspecialchars($transaction['transaction_id'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php if (!empty($transaction['screenshot'])): ?>
                                        <a href="../<?php echo htmlspecialchars($transaction['screenshot']); ?>" target="_blank" class="inline-block">
                                            <img src="../<?php echo htmlspecialchars($transaction['screenshot']); ?>" alt="Screenshot" class="w-12 h-12 object-cover rounded border border-gray-700 hover:border-purple-500 transition duration-200">
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-500">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $transaction['status'] === 'approved' ? 'bg-green-900 text-green-200' : 
                                              ($transaction['status'] === 'rejected' ? 'bg-red-900 text-red-200' : 'bg-yellow-900 text-yellow-200'); ?>">
                                        <?php echo ucfirst($transaction['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                                    <?php echo date('M j, Y H:i', strtotime($transaction['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <?php if ($transaction['status'] === 'pending'): ?>
                                        <button onclick="showActionModal(<?php echo $transaction['id']; ?>, 'approve')" 
                                                class="action-btn bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm mr-2">Approve</button>
                                        <button onclick="showActionModal(<?php echo $transaction['id']; ?>, 'reject')" 
                                                class="action-btn bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">Reject</button>
                                    <?php else: ?>
                                        <span class="text-gray-500">Processed by <?php echo htmlspecialchars($transaction['processed_by_username'] ?? 'Admin'); ?></span>
                                    <?php endif; ?>
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
                    <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>" 
                       class="pagination-btn px-4 py-2 border border-gray-700 rounded bg-gray-800 text-gray-300 hover:bg-gray-700">
                        Previous
                    </a>
                <?php endif; ?>
                
                <div class="flex space-x-1">
                    <?php 
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    if ($start > 1) {
                        echo '<a href="?page=1&status='.urlencode($status_filter).'&type='.urlencode($type_filter).'" class="pagination-btn px-3 py-1 rounded">1</a>';
                        if ($start > 2) echo '<span class="px-2 py-1">...</span>';
                    }
                    
                    for ($i = $start; $i <= $end; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>" 
                           class="pagination-btn px-3 py-1 rounded <?php echo $i === $page ? 'bg-purple-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; 
                    
                    if ($end < $total_pages) {
                        if ($end < $total_pages - 1) echo '<span class="px-2 py-1">...</span>';
                        echo '<a href="?page='.$total_pages.'&status='.urlencode($status_filter).'&type='.urlencode($type_filter).'" class="pagination-btn px-3 py-1 rounded">'.$total_pages.'</a>';
                    }
                    ?>
                </div>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>" 
                       class="pagination-btn px-4 py-2 border border-gray-700 rounded bg-gray-800 text-gray-300 hover:bg-gray-700">
                        Next
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Action Modal -->
    <div id="actionModal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
        <div class="modal-overlay absolute inset-0"></div>
        <div class="modal-content bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-md relative z-10 border border-gray-700">
            <button id="closeActionModal" class="absolute top-4 right-4 text-gray-400 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <h3 class="text-xl font-bold text-white mb-2" id="modalTitle">Process Transaction</h3>
            <p class="text-gray-400 mb-6">Transaction ID: <span id="modalTransactionId" class="text-purple-400 font-mono"></span></p>
            
            <form method="POST" id="actionForm" class="space-y-4">
                <input type="hidden" name="transaction_id" id="transactionId">
                <input type="hidden" name="action" id="actionType">
                
                <div>
                    <label for="adminNotes" class="block text-sm font-medium text-gray-300 mb-1">Admin Notes</label>
                    <textarea name="notes" id="adminNotes" rows="3" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="Optional notes..."></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="hideActionModal()" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded transition duration-200">
                        Cancel
                    </button>
                    <button type="submit" id="submitBtn" class="px-4 py-2 rounded text-white transition duration-200">
                        Confirm
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal logic
        function showActionModal(transactionId, action) {
            document.getElementById('transactionId').value = transactionId;
            document.getElementById('actionType').value = action;
            document.getElementById('modalTransactionId').textContent = transactionId;
            
            const modalTitle = document.getElementById('modalTitle');
            const submitBtn = document.getElementById('submitBtn');
            
            if (action === 'approve') {
                modalTitle.textContent = 'Approve Transaction';
                submitBtn.textContent = 'Approve';
                submitBtn.className = 'px-4 py-2 rounded text-white bg-green-600 hover:bg-green-700 transition duration-200';
            } else {
                modalTitle.textContent = 'Reject Transaction';
                submitBtn.textContent = 'Reject';
                submitBtn.className = 'px-4 py-2 rounded text-white bg-red-600 hover:bg-red-700 transition duration-200';
            }
            
            document.getElementById('actionModal').classList.remove('hidden');
        }

        function hideActionModal() {
            document.getElementById('actionModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('actionModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideActionModal();
            }
        });

        // Close modal with escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideActionModal();
            }
        });

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