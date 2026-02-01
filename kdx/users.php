<?php
require_once '../config.php';

// Check if user is admin
if (!isLoggedIn() || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

// Handle user actions (block/unblock, reset password, change password)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    if ($_POST['action'] === 'block') {
        $pdo->prepare('UPDATE users SET is_blocked = 1 WHERE id = ?')->execute([$user_id]);
    } elseif ($_POST['action'] === 'unblock') {
        $pdo->prepare('UPDATE users SET is_blocked = 0 WHERE id = ?')->execute([$user_id]);
    } elseif ($_POST['action'] === 'reset_password') {
        $pdo->prepare('UPDATE users SET password = "123456" WHERE id = ?')->execute([$user_id]);
    } elseif ($_POST['action'] === 'change_password' && !empty($_POST['new_password'])) {
        $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$new_password, $user_id]);
        $success_message = "Password updated successfully!";
    }
    header('Location: users.php');
    exit;
}

// Get users with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    // Get total count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE is_admin = 0");
    $stmt->execute();
    $total = $stmt->fetchColumn();
    
    // Get users with referral data
    $sql = "
        SELECT u.*, 
               (SELECT COUNT(*) FROM transactions WHERE user_id = u.id) as total_transactions,
               (SELECT COUNT(*) FROM trades WHERE user_id = u.id) as total_bets,
               (SELECT COUNT(*) FROM users WHERE referred_by = u.id) as total_referrals,
               u.referral_earnings as referral_earnings
        FROM users u 
        WHERE u.is_admin = 0
        ORDER BY u.created_at DESC 
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    $total_pages = ceil($total / $limit);
} catch (Exception $e) {
    $error_message = "Failed to fetch users: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Users - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* NEW SLIDER CSS */
        .sidebar { background: #12151c; border-right: 1px solid #1f2530; transition: transform 0.4s ease; z-index: 1000; }
        .nav-link { color: #8a94a6; padding: 12px 16px; border-radius: 12px; display: flex; align-items: center; gap: 12px; font-size: 14px; transition: 0.2s; }
        .nav-link:hover { background: #1a1e29; color: #fff; }
        .nav-link.active { background: #2563eb; color: #fff; font-weight: 600; box-shadow: 0 4px 15px rgba(37, 99, 235, 0.2); }
        .label-sm { font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px; }
        @media (max-width: 1024px) { #sidebar { position: fixed; transform: translateX(-100%); } #sidebar.open { transform: translateX(0); } }

        /* PREVIOUS STYLE PRESERVED */
        body { font-family: 'Inter', sans-serif; background: hsl(220, 20%, 8%); color: hsl(0, 0%, 90%); }
        .card { background: hsl(220, 15%, 16%); border: 1px solid hsl(220, 15%, 20%); transition: all 0.3s; }
        .card:hover { transform: translateY(-5px); border-color: hsl(220, 15%, 25%); }
        .table-row:hover { background: hsl(220, 15%, 20%) !important; }
        .action-btn { transition: all 0.2s; }
        .action-btn:hover { transform: translateY(-2px); }
        .modal-overlay { background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(5px); }
        .modal-content { animation: modalFadeIn 0.3s ease-out; }
        @keyframes modalFadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="flex flex-col lg:flex-row min-h-screen">

    <div class="lg:hidden flex items-center justify-between p-4 bg-[#12151c] border-b border-gray-800 sticky top-0 z-[1001]">
        <button onclick="toggleSidebar()" class="text-white text-xl"><i class="fas fa-bars-staggered"></i></button>
        <span class="font-bold tracking-tight text-blue-500 uppercase">SGS Executive</span>
        <div class="w-8"></div>
    </div>

    <div id="overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/60 z-[999] hidden backdrop-blur-sm"></div>

    <aside id="sidebar" class="sidebar w-64 flex-shrink-0 flex flex-col fixed lg:relative lg:translate-x-0 h-screen overflow-y-auto bg-[#111827]">
    <div class="p-6">
        <h1 class="text-xl font-bold text-white mb-10 flex items-center gap-2">
            <i class="fas fa-chart-line text-blue-500"></i> Admin Panel
        </h1>

        <nav class="space-y-2">
            
            <a href="index.php" class="nav-link ">
                <i class="fas fa-home w-6 text-center"></i> Dashboard
            </a>

            <a href="users.php" class="nav-link active">
                <i class="fas fa-user-group w-6 text-center"></i> User Base
            </a>

            <a href="transactions.php" class="nav-link">
                <i class="fas fa-wallet w-6 text-center"></i> Transactions
            </a>

            <a href="kyc.php" class="nav-link">
                <i class="fas fa-id-card w-6 text-center"></i> KYC Verification
            </a>

            <a href="bets.php" class="nav-link">
                <i class="fas fa-dice w-6 text-center"></i> Bet History
            </a>

            <a href="referral_details.php" class="nav-link">
                <i class="fas fa-share-nodes w-6 text-center"></i> Referrals
            </a>
            
            <a href="settings.php" class="nav-link">
                <i class="fas fa-cog w-6 text-center"></i> Settings
            </a>

            <div class="mt-12">
                <a href="logout.php" class="nav-link text-red-500"><i class="fas fa-power-off"></i> Logout</a>
            </div>
            </nav>
        </div>
    </aside>


    <div class="flex-1 p-8 animate__animated animate__fadeIn">
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-white">Users</h2>
            <p class="text-gray-400">Manage user accounts</p>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="bg-red-900/50 border border-red-700/50 p-4 rounded-lg animate__animated animate__shakeX mb-6">
                <div class="flex items-center text-sm text-red-200">
                    <p><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <div class="card mb-8 rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-800 flex justify-between items-center">
                <h3 class="text-lg font-medium text-white">User List</h3>
                <div class="relative">
                    <input type="text" placeholder="Search users..." class="bg-gray-800 border border-gray-700 rounded px-4 py-2 pl-10 text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <svg class="h-5 w-5 text-gray-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-800">
                    <thead class="bg-gray-900/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Balance</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Referrals</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Transactions</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Bets</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Joined</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        <?php foreach ($users as $user): ?>
                            <tr class="table-row">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gray-800 flex items-center justify-center overflow-hidden">
                                            <img src="../<?php echo htmlspecialchars($user['avatar']); ?>" class="h-full w-full object-cover">
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-white"><?php echo htmlspecialchars($user['username']); ?></div>
                                            <div class="text-xs text-gray-400"><?php echo htmlspecialchars($user['phone']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-white">₹<?php echo number_format($user['balance'], 2); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-white">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-900/40 text-purple-200 border border-purple-500/20">
                                        <?php echo $user['total_referrals']; ?> (₹<?php echo number_format($user['referral_earnings'], 2); ?>)
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-white">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-900/40 text-blue-200 border border-blue-500/20"><?php echo $user['total_transactions']; ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-white">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-900/40 text-green-200 border border-green-500/20"><?php echo $user['total_bets']; ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if (!empty($user['is_blocked'])): ?>
                                        <span class="px-2 py-1 text-xs rounded-full bg-red-900/40 text-red-300 border border-red-500/20">Blocked</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 text-xs rounded-full bg-green-900/40 text-green-300 border border-green-500/20">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <?php if (!empty($user['is_blocked'])): ?>
                                            <button name="action" value="unblock" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-xs transition">Unblock</button>
                                        <?php else: ?>
                                            <button name="action" value="block" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-xs transition">Block</button>
                                        <?php endif; ?>
                                    </form>
                                    <button onclick="openModal('<?php echo $user['id']; ?>', '<?php echo htmlspecialchars($user['username']); ?>')" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-xs ml-2 transition">Password</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="flex justify-center items-center space-x-2 pb-10">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="px-3 py-1 rounded <?php echo $i === $page ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-400'; ?> text-sm"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>

    <div id="passwordModal" class="fixed inset-0 flex items-center justify-center z-[2000] hidden">
        <div class="modal-overlay absolute inset-0" onclick="closeModal()"></div>
        <div class="modal-content bg-gray-800 rounded-xl shadow-2xl p-6 w-full max-w-md relative z-10 border border-gray-700">
            <h3 class="text-xl font-bold text-white mb-2">Change Password</h3>
            <p class="text-gray-400 mb-6 text-sm">User: <span id="modalUsername" class="text-blue-400"></span></p>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="user_id" id="modalUserId">
                <input type="hidden" name="action" value="change_password">
                <input type="password" name="new_password" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white focus:border-blue-500 outline-none" placeholder="New Password" required minlength="4">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded-lg transition">Update Now</button>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() { 
            document.getElementById('sidebar').classList.toggle('open'); 
            document.getElementById('overlay').classList.toggle('hidden'); 
        }
        function openModal(id, name) {
            document.getElementById('passwordModal').classList.remove('hidden');
            document.getElementById('modalUserId').value = id;
            document.getElementById('modalUsername').innerText = name;
        }
        function closeModal() { document.getElementById('passwordModal').classList.add('hidden'); }
    </script>
</body>
</html>