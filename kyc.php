<?php
session_start();
require_once '../config.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle KYC status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }
    
    $kyc_id = filter_var($_POST['kyc_id'], FILTER_SANITIZE_NUMBER_INT);
    $action = in_array($_POST['action'], ['approve', 'reject']) ? $_POST['action'] : '';
    $reason = isset($_POST['reason']) ? htmlspecialchars($_POST['reason'], ENT_QUOTES) : '';
    $admin_notes = isset($_POST['admin_notes']) ? htmlspecialchars($_POST['admin_notes'], ENT_QUOTES) : '';
    
    try {
        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE kyc_verification SET status = 'approved', admin_notes = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$admin_notes, $kyc_id]);
            
            // Log this action
            logAdminAction($_SESSION['user_id'], "Approved KYC #$kyc_id");
            
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE kyc_verification SET status = 'rejected', reason = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$reason, $admin_notes, $kyc_id]);
            
            // Log this action
            logAdminAction($_SESSION['user_id'], "Rejected KYC #$kyc_id");
        }
        
        header('Location: kyc.php?success=1');
        exit;
    } catch (Exception $e) {
        $error = 'Database error occurred';
        error_log("KYC Update Error: " . $e->getMessage());
    }
}

// Fetch KYC submissions with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$status_filter = isset($_GET['status']) ? filter_var($_GET['status'], FILTER_SANITIZE_STRING) : '';
$search_query = isset($_GET['search']) ? filter_var($_GET['search'], FILTER_SANITIZE_STRING) : '';

$where_conditions = [];
$params = [];

if ($status_filter && in_array($status_filter, ['pending', 'approved', 'rejected'])) {
    $where_conditions[] = "k.status = ?";
    $params[] = $status_filter;
}

if ($search_query) {
    $where_conditions[] = "(u.username LIKE ? OR u.phone LIKE ? OR k.full_name LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

try {
    // Get total count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM kyc_verification k JOIN users u ON k.user_id = u.id $where_clause");
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    
    // Get submissions
    $stmt = $pdo->prepare("
        SELECT k.*, u.username, u.phone 
        FROM kyc_verification k 
        JOIN users u ON k.user_id = u.id 
        $where_clause 
        ORDER BY k.created_at DESC 
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $kyc_submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_pages = ceil($total / $limit);
    
} catch (Exception $e) {
    $error = 'Failed to load KYC submissions';
    $kyc_submissions = [];
    error_log("KYC Fetch Error: " . $e->getMessage());
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
    <title>KYC Management - Admin Panel</title>
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
        
        .status-pending { background: hsl(38, 92%, 50%); color: white; }
        .status-approved { background: hsl(142, 71%, 45%); color: white; }
        .status-rejected { background: hsl(0, 84%, 60%); color: white; }
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
                    <p class="text-xs text-gray-400">KYC Management</p>
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
                <a href="kyc.php" class="nav-item active block px-6 py-3 flex items-center space-x-3">
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
            <h2 class="text-3xl font-bold text-white">KYC Management</h2>
            <p class="text-gray-400">Review and verify user identity documents</p>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="bg-green-900/50 border border-green-700/50 p-4 rounded-lg animate__animated animate__fadeIn mb-6">
                <div class="flex items-center">
                    <svg class="h-5 w-5 text-green-400 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <div class="ml-3">
                        <p class="text-sm text-green-200">KYC status updated successfully!</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="bg-red-900/50 border border-red-700/50 p-4 rounded-lg animate__animated animate__shakeX mb-6">
                <div class="flex items-center">
                    <svg class="h-5 w-5 text-red-400 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                    <div class="ml-3">
                        <p class="text-sm text-red-200"><?php echo htmlspecialchars($error); ?></p>
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
                <input type="text" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search_query); ?>" 
                       class="bg-gray-800 border border-gray-700 rounded px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-purple-500">
                <button type="submit" class="filter-btn bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded transition duration-200">
                    Apply Filters
                </button>
                <a href="kyc.php" class="filter-btn bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded transition duration-200">
                    Clear Filters
                </a>
            </form>
        </div>

        <!-- KYC Submissions Table -->
        <div class="card mb-8">
            <div class="px-6 py-4 border-b border-gray-800 flex justify-between items-center">
                <h3 class="text-lg font-medium text-white">KYC Submissions</h3>
                <div class="text-gray-400">
                    Showing <?php echo count($kyc_submissions); ?> of <?php echo $total; ?> submissions
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-800">
                    <thead class="bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Full Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Date of Birth</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Submitted</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        <?php foreach ($kyc_submissions as $kyc): ?>
                            <tr class="table-row animate__animated animate__fadeIn">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gray-800 flex items-center justify-center overflow-hidden">
                                            <span class="text-white"><?php echo strtoupper(substr($kyc['username'], 0, 1)); ?></span>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-white"><?php echo htmlspecialchars($kyc['username']); ?></div>
                                            <div class="text-sm text-gray-400"><?php echo htmlspecialchars($kyc['phone']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-white">
                                    <?php echo htmlspecialchars($kyc['full_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-white">
                                    <?php echo htmlspecialchars($kyc['date_of_birth']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full status-<?php echo $kyc['status']; ?>">
                                        <?php echo ucfirst($kyc['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                                    <?php echo date('M j, Y H:i', strtotime($kyc['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <button onclick="viewKYC(<?php echo $kyc['id']; ?>)" 
                                                class="action-btn bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">View</button>
                                        <?php if ($kyc['status'] === 'pending'): ?>
                                            <button onclick="approveKYC(<?php echo $kyc['id']; ?>)" 
                                                    class="action-btn bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm">Approve</button>
                                            <button onclick="rejectKYC(<?php echo $kyc['id']; ?>)" 
                                                    class="action-btn bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">Reject</button>
                                        <?php endif; ?>
                                    </div>
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
                    <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search_query); ?>" 
                       class="pagination-btn px-4 py-2 border border-gray-700 rounded bg-gray-800 text-gray-300 hover:bg-gray-700">
                        Previous
                    </a>
                <?php endif; ?>
                
                <div class="flex space-x-1">
                    <?php 
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    if ($start > 1) {
                        echo '<a href="?page=1&status='.urlencode($status_filter).'&search='.urlencode($search_query).'" class="pagination-btn px-3 py-1 rounded">1</a>';
                        if ($start > 2) echo '<span class="px-2 py-1">...</span>';
                    }
                    
                    for ($i = $start; $i <= $end; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search_query); ?>" 
                           class="pagination-btn px-3 py-1 rounded <?php echo $i === $page ? 'bg-purple-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; 
                    
                    if ($end < $total_pages) {
                        if ($end < $total_pages - 1) echo '<span class="px-2 py-1">...</span>';
                        echo '<a href="?page='.$total_pages.'&status='.urlencode($status_filter).'&search='.urlencode($search_query).'" class="pagination-btn px-3 py-1 rounded">'.$total_pages.'</a>';
                    }
                    ?>
                </div>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search_query); ?>" 
                       class="pagination-btn px-4 py-2 border border-gray-700 rounded bg-gray-800 text-gray-300 hover:bg-gray-700">
                        Next
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- View KYC Modal -->
    <div id="viewModal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
        <div class="modal-overlay absolute inset-0"></div>
        <div class="modal-content bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-4xl relative z-10 border border-gray-700">
            <button id="closeViewModal" class="absolute top-4 right-4 text-gray-400 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <h3 class="text-xl font-bold text-white mb-2">KYC Details</h3>
            <p class="text-gray-400 mb-6">Submission ID: <span id="modalKycId" class="text-purple-400 font-mono"></span></p>
            
            <div id="kycDetails" class="space-y-6">
                <!-- KYC details will be loaded here via AJAX -->
            </div>
        </div>
    </div>

    <!-- Approve/Reject Modal -->
    <div id="actionModal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
        <div class="modal-overlay absolute inset-0"></div>
        <div class="modal-content bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-md relative z-10 border border-gray-700">
            <button id="closeActionModal" class="absolute top-4 right-4 text-gray-400 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <h3 id="actionTitle" class="text-xl font-bold text-white mb-2">Process KYC</h3>
            <p class="text-gray-400 mb-6">Submission ID: <span id="modalActionKycId" class="text-purple-400 font-mono"></span></p>
            
            <form method="POST" id="actionForm" class="space-y-4">
                <input type="hidden" name="kyc_id" id="kycId">
                <input type="hidden" name="action" id="actionType">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div id="rejectReasonDiv" class="hidden">
                    <label for="reason" class="block text-sm font-medium text-gray-300 mb-1">Rejection Reason</label>
                    <textarea name="reason" id="reason" rows="3" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="Required reason for rejection..."></textarea>
                </div>
                
                <div>
                    <label for="admin_notes" class="block text-sm font-medium text-gray-300 mb-1">Admin Notes</label>
                    <textarea name="admin_notes" id="admin_notes" rows="3" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="Optional notes..."></textarea>
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
        function viewKYC(kycId) {
            fetch(`get_kyc_details.php?id=${kycId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const kyc = data.kyc;
                        
                        // Format the documents
                        let selfieHtml = `<img src="../${kyc.selfie_path}" alt="Selfie" class="w-full max-w-xs rounded-lg border border-gray-700 hover:border-purple-500 transition duration-200">`;
                        
                        let idHtml = '';
                        if (kyc.id_path.endsWith('.pdf')) {
                            idHtml = `<a href="../${kyc.id_path}" target="_blank" class="text-blue-400 hover:text-blue-300">View PDF Document</a>`;
                        } else {
                            idHtml = `<img src="../${kyc.id_path}" alt="ID Document" class="w-full max-w-xs rounded-lg border border-gray-700 hover:border-purple-500 transition duration-200">`;
                        }
                        
                        // Additional documents if any
                        let additionalDocsHtml = '';
                        if (kyc.additional_docs_path) {
                            if (kyc.additional_docs_path.endsWith('.pdf')) {
                                additionalDocsHtml = `
                                    <div class="mt-4">
                                        <h4 class="text-white font-semibold mb-2">Additional Document</h4>
                                        <a href="../${kyc.additional_docs_path}" target="_blank" class="text-blue-400 hover:text-blue-300">View PDF Document</a>
                                    </div>
                                `;
                            } else {
                                additionalDocsHtml = `
                                    <div class="mt-4">
                                        <h4 class="text-white font-semibold mb-2">Additional Document</h4>
                                        <img src="../${kyc.additional_docs_path}" alt="Additional Document" class="w-full max-w-xs rounded-lg border border-gray-700 hover:border-purple-500 transition duration-200">
                                    </div>
                                `;
                            }
                        }
                        
                        // Build the HTML
                        document.getElementById('kycDetails').innerHTML = `
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h4 class="text-white font-semibold mb-3">User Information</h4>
                                    <div class="bg-gray-700/50 rounded-lg p-4 space-y-3">
                                        <div><span class="text-gray-300">Username:</span> <span class="text-white">${kyc.username}</span></div>
                                        <div><span class="text-gray-300">Phone:</span> <span class="text-white">${kyc.phone}</span></div>
                                        <div><span class="text-gray-300">Full Name:</span> <span class="text-white">${kyc.full_name}</span></div>
                                        <div><span class="text-gray-300">Date of Birth:</span> <span class="text-white">${kyc.date_of_birth}</span></div>
                                        <div><span class="text-gray-300">Address:</span> <span class="text-white">${kyc.address || 'Not provided'}</span></div>
                                        <div><span class="text-gray-300">Status:</span> <span class="px-2 py-1 rounded text-sm status-${kyc.status}">${kyc.status.charAt(0).toUpperCase() + kyc.status.slice(1)}</span></div>
                                        ${kyc.reason ? `<div><span class="text-gray-300">Rejection Reason:</span> <span class="text-red-300">${kyc.reason}</span></div>` : ''}
                                        ${kyc.admin_notes ? `<div><span class="text-gray-300">Admin Notes:</span> <span class="text-gray-300">${kyc.admin_notes}</span></div>` : ''}
                                    </div>
                                </div>
                                
                                <div>
                                    <h4 class="text-white font-semibold mb-3">Documents</h4>
                                    <div class="space-y-4">
                                        <div>
                                            <p class="text-gray-300 text-sm mb-1">Selfie Photo:</p>
                                            ${selfieHtml}
                                        </div>
                                        <div>
                                            <p class="text-gray-300 text-sm mb-1">Government ID:</p>
                                            ${idHtml}
                                        </div>
                                        ${additionalDocsHtml}
                                    </div>
                                </div>
                            </div>
                            
                            <div class="pt-4 border-t border-gray-700">
                                <h4 class="text-white font-semibold mb-2">Timestamps</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-300">
                                    <div>Submitted: ${new Date(kyc.created_at).toLocaleString()}</div>
                                    ${kyc.updated_at ? `<div>Last Updated: ${new Date(kyc.updated_at).toLocaleString()}</div>` : ''}
                                </div>
                            </div>
                        `;
                        
                        document.getElementById('modalKycId').textContent = kyc.id;
                        document.getElementById('viewModal').classList.remove('hidden');
                    } else {
                        alert('Failed to load KYC details: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading KYC details');
                });
        }

        function approveKYC(kycId) {
            document.getElementById('kycId').value = kycId;
            document.getElementById('actionType').value = 'approve';
            document.getElementById('actionTitle').textContent = 'Approve KYC Submission';
            document.getElementById('modalActionKycId').textContent = kycId;
            document.getElementById('submitBtn').textContent = 'Approve';
            document.getElementById('submitBtn').className = 'px-4 py-2 rounded text-white bg-green-600 hover:bg-green-700 transition duration-200';
            document.getElementById('rejectReasonDiv').classList.add('hidden');
            document.getElementById('actionModal').classList.remove('hidden');
        }

        function rejectKYC(kycId) {
            document.getElementById('kycId').value = kycId;
            document.getElementById('actionType').value = 'reject';
            document.getElementById('actionTitle').textContent = 'Reject KYC Submission';
            document.getElementById('modalActionKycId').textContent = kycId;
            document.getElementById('submitBtn').textContent = 'Reject';
            document.getElementById('submitBtn').className = 'px-4 py-2 rounded text-white bg-red-600 hover:bg-red-700 transition duration-200';
            document.getElementById('rejectReasonDiv').classList.remove('hidden');
            document.getElementById('actionModal').classList.remove('hidden');
        }

        function hideViewModal() {
            document.getElementById('viewModal').classList.add('hidden');
        }

        function hideActionModal() {
            document.getElementById('actionModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.id === 'viewModal') hideViewModal();
            if (e.target.id === 'actionModal') hideActionModal();
        });

        // Close modals with escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideViewModal();
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