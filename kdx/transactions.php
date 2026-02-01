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

// Function to sanitize input
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Handle transaction processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_id = intval($_POST['transaction_id'] ?? 0);
    $action = clean_input($_POST['action'] ?? '');
    
    // --- FIX: Handling Special Characters ---
    $notes = $_POST['notes'] ?? '';
    $notes = str_replace('₹', 'Rs ', $notes);
    $notes = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $notes);
    $notes = clean_input($notes);
    // --- FIX END ---
    
    if ($transaction_id && in_array($action, ['approve', 'reject'])) {
        try {
            $pdo->beginTransaction();
            
            // Get transaction details
            $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
            $stmt->execute([$transaction_id]);
            $transaction = $stmt->fetch();
            
            if ($transaction && strtolower($transaction['status']) === 'pending') {
                $new_status = $action === 'approve' ? 'approved' : 'rejected';
                
                // 1. Update transaction status
                $stmt = $pdo->prepare("UPDATE transactions SET status = ?, admin_notes = ?, processed_by = ?, processed_at = NOW() WHERE id = ?");
                $stmt->execute([$new_status, $notes, $_SESSION['user_id'], $transaction_id]);
                
                // 2. Logic for APPROVE (Deposit) -> Add Balance
                if ($action === 'approve' && strtolower($transaction['type']) === 'deposit') {
                    $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                    $stmt->execute([$transaction['amount'], $transaction['user_id']]);
                }
                
                // 3. Logic for REJECT (Withdrawal) -> Refund Balance
                if ($action === 'reject' && (strtolower($transaction['type']) === 'withdrawal' || strtolower($transaction['type']) === 'withdraw')) {
                    $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                    $stmt->execute([$transaction['amount'], $transaction['user_id']]);
                }
                
                $pdo->commit();
                $success_message = "Transaction " . ucfirst($action) . "ed successfully";
            } else {
                $error_message = "Transaction is not pending or not found.";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "Failed to process: " . $e->getMessage();
        }
    }
}

// Pagination & Filtering Logic
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$status_filter = clean_input($_GET['status'] ?? '');
$type_filter = clean_input($_GET['type'] ?? '');

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Transactions - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --primary: hsl(258, 90%, 66%); --primary-light: hsl(258, 90%, 72%); }
        html, body { font-family: 'Inter', sans-serif; background: hsl(220, 20%, 8%); color: hsl(0, 0%, 90%); min-height: 100vh; }
        
        .card { background: #1f2937; border: 1px solid #374151; transition: all 0.3s; }
        .card:hover { border-color: #4b5563; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .table-row:hover { background: rgba(55, 65, 81, 0.5); }
        
        /* Modal Animation */
        .modal-enter { animation: modalIn 0.3s ease-out forwards; }
        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
    </style>
</head>
<body class="flex flex-col lg:flex-row min-h-screen bg-[#0d1117]">

    <?php include 'sidebar.php'; ?>
    
    <div class="flex-1 w-full p-4 lg:p-8">
        
        <div class="animate__animated animate__fadeIn">
            <div class="mb-8">
                <h2 class="text-3xl font-bold text-white">Transactions</h2>
                <p class="text-gray-400">Manage all user transactions</p>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="bg-green-500/10 border border-green-500/20 p-4 rounded-lg mb-6 flex items-center gap-3">
                    <i class="fas fa-check-circle text-green-400"></i>
                    <p class="text-sm text-green-200"><?php echo htmlspecialchars($success_message); ?></p>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="bg-red-500/10 border border-red-500/20 p-4 rounded-lg mb-6 flex items-center gap-3 animate__animated animate__shakeX">
                    <i class="fas fa-exclamation-triangle text-red-400"></i>
                    <p class="text-sm text-red-200"><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            <?php endif; ?>

            <div class="card mb-6 rounded-xl p-4 shadow-lg">
                <form method="GET" class="flex flex-col sm:flex-row gap-4 flex-wrap">
                    <div class="relative flex-1 min-w-[200px]">
                        <i class="fas fa-filter absolute left-3 top-3.5 text-gray-500"></i>
                        <select name="status" class="w-full bg-gray-800 border border-gray-600 rounded-lg px-4 pl-10 py-2.5 text-white focus:outline-none focus:border-purple-500 appearance-none">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    
                    <div class="relative flex-1 min-w-[200px]">
                        <i class="fas fa-exchange-alt absolute left-3 top-3.5 text-gray-500"></i>
                        <select name="type" class="w-full bg-gray-800 border border-gray-600 rounded-lg px-4 pl-10 py-2.5 text-white focus:outline-none focus:border-purple-500 appearance-none">
                            <option value="">All Types</option>
                            <option value="deposit" <?php echo $type_filter === 'deposit' ? 'selected' : ''; ?>>Deposit</option>
                            <option value="withdrawal" <?php echo $type_filter === 'withdrawal' ? 'selected' : ''; ?>>Withdrawal</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-2.5 rounded-lg transition font-medium shadow-lg shadow-purple-900/20">
                        Apply
                    </button>
                    <a href="transactions.php" class="bg-gray-700 hover:bg-gray-600 text-white px-6 py-2.5 rounded-lg transition font-medium text-center">
                        Reset
                    </a>
                </form>
            </div>

            <div class="card mb-8 rounded-xl overflow-hidden shadow-xl">
                <div class="px-6 py-4 border-b border-gray-700 bg-gray-800/50 flex flex-col sm:flex-row justify-between items-center gap-4">
                    <h3 class="text-lg font-medium text-white">Transaction List</h3>
                    <div class="text-sm text-gray-400">Total: <?php echo $total; ?></div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-700 text-sm">
                        <thead class="bg-gray-900/50">
                            <tr>
                                <th class="px-6 py-3 text-left font-medium text-gray-400 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-400 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-400 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-400 uppercase tracking-wider">Method</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-400 uppercase tracking-wider">Txn ID</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-400 uppercase tracking-wider">Proof</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-400 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-right font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            <?php foreach ($transactions as $index => $transaction): ?>
                                <tr class="hover:bg-gray-700/30 transition table-row animate__animated animate__fadeIn" style="animation-delay: <?php echo ($index % 10) * 50; ?>ms">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-gray-700 flex items-center justify-center font-bold text-white text-xs">
                                                <?php echo strtoupper(substr($transaction['username'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="text-white font-medium"><?php echo htmlspecialchars($transaction['username']); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($transaction['phone']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if (strtolower($transaction['type']) === 'deposit'): ?>
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-500/10 text-green-400 border border-green-500/20">Deposit</span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-500/10 text-red-400 border border-red-500/20">Withdraw</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap font-mono text-white">
                                        ₹<?php echo number_format($transaction['amount'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-400">
                                        <?php echo htmlspecialchars($transaction['method']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500 font-mono">
                                        <?php echo htmlspecialchars($transaction['transaction_id'] ?? '-'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php if (!empty($transaction['screenshot'])): ?>
                                            <a href="../<?php echo htmlspecialchars($transaction['screenshot']); ?>" target="_blank" class="text-blue-400 hover:text-blue-300 flex items-center gap-1">
                                                <i class="fas fa-image"></i> View
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-600 text-xs">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                            $st = strtolower($transaction['status']);
                                            $statusClass = '';
                                            if ($st === 'approved') {
                                                $statusClass = 'text-green-400 bg-green-500/10 border-green-500/20';
                                            } elseif ($st === 'rejected') {
                                                $statusClass = 'text-red-400 bg-red-500/10 border-red-500/20';
                                            } else {
                                                $statusClass = 'text-yellow-400 bg-yellow-500/10 border-yellow-500/20';
                                            }
                                        ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full border <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($transaction['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">
                                        <?php echo date('M j, H:i', strtotime($transaction['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <?php if (strtolower($transaction['status']) === 'pending'): ?>
                                            <div class="flex justify-end gap-2">
                                                <button onclick="showActionModal(<?php echo $transaction['id']; ?>, 'approve')" 
                                                        class="bg-green-600/20 hover:bg-green-600 text-green-400 hover:text-white p-1.5 rounded transition border border-green-600/30" title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button onclick="showActionModal(<?php echo $transaction['id']; ?>, 'reject')" 
                                                        class="bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white p-1.5 rounded transition border border-red-600/30" title="Reject">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-600 italic">
                                                <?php echo htmlspecialchars($transaction['processed_by_username'] ?? 'Admin'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="flex justify-center items-center space-x-2 pb-8">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>" 
                           class="px-3 py-1 bg-gray-800 text-gray-400 rounded hover:bg-gray-700 text-sm">Prev</a>
                    <?php endif; ?>
                    
                    <span class="text-gray-500 text-sm">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>" 
                           class="px-3 py-1 bg-gray-800 text-gray-400 rounded hover:bg-gray-700 text-sm">Next</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="actionModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-black/80 backdrop-blur-sm transition-opacity" onclick="hideActionModal()"></div>
        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4 text-center">
                <div class="relative transform overflow-hidden rounded-2xl bg-gray-800 text-left shadow-2xl transition-all sm:w-full sm:max-w-md border border-gray-700 modal-enter w-full max-w-sm">
                    <div class="bg-[#1f2937] px-6 py-6">
                        <div class="text-center">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-700 mb-4" id="modalIcon"></div>
                            <h3 class="text-xl font-bold leading-6 text-white mb-1" id="modalTitle">Confirm Action</h3>
                            <p class="text-sm text-gray-400">Transaction ID: <span id="modalTransactionId" class="font-mono text-purple-400">#</span></p>
                        </div>
                        <form method="POST" class="mt-6 space-y-4">
                            <input type="hidden" name="transaction_id" id="transactionId">
                            <input type="hidden" name="action" id="actionType">
                            <div>
                                <label for="adminNotes" class="block text-xs font-medium text-gray-400 mb-1.5 text-left">Admin Notes (Optional)</label>
                                <textarea name="notes" id="adminNotes" rows="3" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-purple-500 placeholder-gray-600" placeholder="Reason for approval/rejection..."></textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-3 mt-6">
                                <button type="button" onclick="hideActionModal()" class="w-full justify-center rounded-lg bg-gray-700 px-3 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-gray-600 transition">Cancel</button>
                                <button type="submit" id="submitBtn" class="w-full justify-center rounded-lg px-3 py-2.5 text-sm font-semibold text-white shadow-sm transition">Confirm</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showActionModal(transactionId, action) {
            document.getElementById('transactionId').value = transactionId;
            document.getElementById('actionType').value = action;
            document.getElementById('modalTransactionId').textContent = transactionId;
            
            const modalTitle = document.getElementById('modalTitle');
            const submitBtn = document.getElementById('submitBtn');
            const modalIcon = document.getElementById('modalIcon');
            
            if (action === 'approve') {
                modalTitle.textContent = 'Approve Transaction';
                submitBtn.textContent = 'Approve Now';
                submitBtn.className = 'w-full justify-center rounded-lg bg-green-600 hover:bg-green-500 px-3 py-2.5 text-sm font-semibold text-white shadow-sm transition';
                modalIcon.innerHTML = '<i class="fas fa-check text-green-400 text-xl"></i>';
                modalIcon.className = 'mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-900/30 mb-4 border border-green-500/30';
            } else {
                modalTitle.textContent = 'Reject Transaction';
                submitBtn.textContent = 'Reject Request';
                submitBtn.className = 'w-full justify-center rounded-lg bg-red-600 hover:bg-red-500 px-3 py-2.5 text-sm font-semibold text-white shadow-sm transition';
                modalIcon.innerHTML = '<i class="fas fa-times text-red-400 text-xl"></i>';
                modalIcon.className = 'mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-900/30 mb-4 border border-red-500/30';
            }
            
            document.getElementById('actionModal').classList.remove('hidden');
        }

        function hideActionModal() {
            document.getElementById('actionModal').classList.add('hidden');
        }
    </script>
</body>
</html>