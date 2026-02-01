<?php
require_once '../config.php';

// Check if user is admin
if (!isLoggedIn() || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

$user_id = intval($_GET['user_id'] ?? 0);

if ($user_id <= 0) {
    header('Location: referrals.php');
    exit;
}

try {
    // Get user info
    $stmt = $pdo->prepare("SELECT id, username, email, referral_code, balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header('Location: referrals.php');
        exit;
    }

    // Get referral details
    $stmt = $pdo->prepare("
        SELECT id, username, email, balance, created_at FROM users 
        WHERE referral_parent = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id]);
    $referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate referral stats
    $total_referrals = count($referrals);
    $active_referrals = count(array_filter($referrals, fn($r) => $r['balance'] > 0));

} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

$current_page = 'referrals.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Details - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Poppins', sans-serif; }
        body { background: #0f172a; color: white; }
        .glass-effect { background: rgba(15, 23, 42, 0.75); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.08); }
    </style>
</head>
<body>
    <div class="flex h-screen">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 overflow-auto">
            <div class="p-6">
                <!-- Back Button -->
                <div class="mb-6">
                    <a href="referrals.php" class="text-indigo-400 hover:text-indigo-300 flex items-center gap-2">
                        <i class="fas fa-arrow-left"></i> Back to Referrals
                    </a>
                </div>

                <!-- User Info Card -->
                <div class="glass-effect rounded-lg p-6 mb-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold mb-2"><?php echo htmlspecialchars($user['username']); ?></h1>
                            <p class="text-gray-400"><?php echo htmlspecialchars($user['email']); ?></p>
                            <p class="text-sm text-gray-500 mt-2">Referral Code: <span class="font-mono text-indigo-400"><?php echo htmlspecialchars($user['referral_code']); ?></span></p>
                        </div>
                        <div class="text-right">
                            <p class="text-gray-400 text-sm mb-1">Balance</p>
                            <p class="text-2xl font-bold text-green-400">₹<?php echo number_format($user['balance'], 2); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                    <div class="glass-effect rounded-lg p-4">
                        <p class="text-gray-400 text-sm">Total Referrals</p>
                        <p class="text-2xl font-bold mt-1"><?php echo $total_referrals; ?></p>
                    </div>

                    <div class="glass-effect rounded-lg p-4">
                        <p class="text-gray-400 text-sm">Active Referrals</p>
                        <p class="text-2xl font-bold text-green-400 mt-1"><?php echo $active_referrals; ?></p>
                    </div>

                    <div class="glass-effect rounded-lg p-4">
                        <p class="text-gray-400 text-sm">Inactive Referrals</p>
                        <p class="text-2xl font-bold text-yellow-400 mt-1"><?php echo $total_referrals - $active_referrals; ?></p>
                    </div>
                </div>

                <!-- Referrals Table -->
                <div class="glass-effect rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-white/10">
                        <h2 class="font-bold text-lg">Referred Users</h2>
                    </div>

                    <?php if ($total_referrals > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-black/30 border-b border-white/10">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-sm font-semibold">Username</th>
                                        <th class="px-6 py-3 text-left text-sm font-semibold">Email</th>
                                        <th class="px-6 py-3 text-left text-sm font-semibold">Balance</th>
                                        <th class="px-6 py-3 text-left text-sm font-semibold">Status</th>
                                        <th class="px-6 py-3 text-left text-sm font-semibold">Joined</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($referrals as $ref): ?>
                                        <tr class="border-b border-white/5 hover:bg-white/5 transition">
                                            <td class="px-6 py-4 font-semibold"><?php echo htmlspecialchars($ref['username']); ?></td>
                                            <td class="px-6 py-4 text-gray-400"><?php echo htmlspecialchars($ref['email']); ?></td>
                                            <td class="px-6 py-4 font-semibold">₹<?php echo number_format($ref['balance'], 2); ?></td>
                                            <td class="px-6 py-4">
                                                <span class="px-3 py-1 rounded-full text-xs font-bold <?php echo $ref['balance'] > 0 ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'; ?>">
                                                    <?php echo $ref['balance'] > 0 ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-gray-400 text-sm"><?php echo date('d M Y', strtotime($ref['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="px-6 py-12 text-center text-gray-400">
                            <i class="fas fa-inbox text-3xl mb-3 opacity-50"></i>
                            <p>No referrals yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
