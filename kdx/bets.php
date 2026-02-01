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

$result_filter = isset($_GET['result']) ? htmlspecialchars($_GET['result']) : '';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Bet History - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: hsl(258, 90%, 66%); --primary-light: hsl(258, 90%, 72%); --glow: 0 0 15px hsla(258, 90%, 66%, 0.5); }
        html, body { font-family: 'Inter', sans-serif; background: hsl(220, 20%, 8%); color: hsl(0, 0%, 90%); min-height: 100vh; }
        .card { background: hsl(220, 15%, 16%); border: 1px solid hsl(220, 15%, 20%); transition: all 0.3s; }
        .card:hover { transform: translateY(-2px); box-shadow: var(--glow); border-color: hsl(220, 15%, 25%); }
        .table-row { transition: all 0.2s; }
        .table-row:hover { background: hsl(220, 15%, 20%) !important; }
        .filter-btn { transition: all 0.2s; }
        .filter-btn:hover { transform: translateY(-1px); }
        .win-badge { animation: pulse 2s infinite; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(74, 222, 128, 0.7); } 70% { box-shadow: 0 0 0 6px rgba(74, 222, 128, 0); } 100% { box-shadow: 0 0 0 0 rgba(74, 222, 128, 0); } }
    </style>
</head>
<body class="flex flex-col lg:flex-row min-h-screen bg-[#0d1117]">

    <?php include 'sidebar.php'; ?>

    <div class="flex-1 w-full p-4 lg:p-8">
        
        <div class="animate__animated animate__fadeIn">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-white">Bet History</h2>
                <p class="text-sm text-gray-400">View all user bets</p>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="bg-red-900/50 border border-red-700/50 p-4 rounded-lg animate__animated animate__shakeX mb-6 flex items-center gap-3">
                    <i class="fas fa-exclamation-triangle text-red-400"></i>
                    <p class="text-sm text-red-200"><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            <?php endif; ?>

            <div class="card mb-6 rounded-xl p-4 shadow-lg">
                <form method="GET" class="flex flex-col sm:flex-row gap-3">
                    <select name="result" class="w-full sm:w-48 bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:ring-2 focus:ring-purple-500 appearance-none">
                        <option value="">All Results</option>
                        <option value="win" <?php echo $result_filter === 'win' ? 'selected' : ''; ?>>Win</option>
                        <option value="loss" <?php echo $result_filter === 'loss' ? 'selected' : ''; ?>>Loss</option>
                        <option value="cashout" <?php echo $result_filter === 'cashout' ? 'selected' : ''; ?>>Cashout</option>
                    </select>
                    
                    <select name="user_id" class="w-full sm:w-48 bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:ring-2 focus:ring-purple-500 appearance-none">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <div class="flex gap-2 w-full sm:w-auto">
                        <button type="submit" class="filter-btn bg-purple-600 hover:bg-purple-700 text-white px-6 py-2.5 rounded-lg transition font-medium w-full sm:w-auto">
                            Apply
                        </button>
                        <a href="bets.php" class="filter-btn bg-gray-700 hover:bg-gray-600 text-white px-6 py-2.5 rounded-lg transition font-medium text-center w-full sm:w-auto">
                            Clear
                        </a>
                    </div>
                </form>
            </div>

            <div class="card mb-8 rounded-xl overflow-hidden shadow-xl">
                <div class="px-6 py-4 border-b border-gray-800 flex justify-between items-center bg-[#1f2937]">
                    <h3 class="text-lg font-medium text-white">Bet History</h3>
                    <div class="text-xs text-gray-400">Total: <?php echo $total; ?></div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full whitespace-nowrap text-left text-sm">
                        <thead class="bg-gray-900/50 text-gray-400 uppercase font-semibold text-xs">
                            <tr>
                                <th class="px-6 py-3">User</th>
                                <th class="px-6 py-3">Bet Amount</th>
                                <th class="px-6 py-3">Bombs</th>
                                <th class="px-6 py-3">Result</th>
                                <th class="px-6 py-3">Multiplier</th>
                                <th class="px-6 py-3">Winnings</th>
                                <th class="px-6 py-3">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800">
                            <?php foreach ($bets as $index => $bet): ?>
                                <tr class="table-row animate__animated animate__fadeIn" style="animation-delay: <?php echo $index * 0.05; ?>s">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex-shrink-0 h-9 w-9 rounded-full bg-gray-800 flex items-center justify-center overflow-hidden border border-gray-700 text-xs font-bold text-white">
                                                <?php if (!empty($bet['avatar'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($bet['avatar']); ?>" alt="" class="h-full w-full object-cover">
                                                <?php else: ?>
                                                    <?php echo strtoupper(substr($bet['username'], 0, 1)); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="font-medium text-white"><?php echo htmlspecialchars($bet['username']); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($bet['phone']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 font-mono text-gray-300">
                                        ₹<?php echo number_format($bet['bet_amount'] ?? 0, 2); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-800 border border-purple-500 text-xs font-bold text-white">
                                            <?php echo $bet['bomb_count'] ?? '-'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php
                                            $res = $bet['result'] ?? 'unknown';
                                            $resClass = '';
                                            if ($res === 'win') {
                                                $resClass = 'bg-green-900/40 text-green-300 border-green-500/20 win-badge';
                                            } elseif ($res === 'loss') {
                                                $resClass = 'bg-red-900/40 text-red-300 border-red-500/20';
                                            } else {
                                                $resClass = 'bg-yellow-900/40 text-yellow-300 border-yellow-500/20';
                                            }
                                        ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full border <?php echo $resClass; ?>">
                                            <?php echo ucfirst($res); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-0.5 rounded text-xs font-medium bg-purple-900/40 text-purple-200 border border-purple-500/20">
                                            x<?php echo number_format((float)($bet['multiplier'] ?? 0), 2); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-bold">
                                        <?php 
                                            // FIX: Handle undefined winnings and null value
                                            $winAmount = $bet['winnings'] ?? ($bet['win_amount'] ?? 0);
                                            $winAmount = floatval($winAmount); 
                                        ?>
                                        <span class="<?php echo $winAmount > 0 ? 'text-green-400' : 'text-gray-500'; ?>">
                                            ₹<?php echo number_format($winAmount, 2); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-xs text-gray-500">
                                        <?php echo date('d M, H:i', strtotime($bet['created_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_pages > 1): ?>
                    <div class="p-4 border-t border-gray-800 flex justify-center gap-2 bg-gray-900/30">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&result=<?php echo urlencode($result_filter); ?>&user_id=<?php echo $user_filter; ?>" 
                               class="px-3 py-1.5 bg-gray-800 text-gray-400 rounded-lg hover:bg-gray-700 text-sm transition">Prev</a>
                        <?php endif; ?>
                        
                        <span class="px-3 py-1.5 text-sm text-gray-500">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&result=<?php echo urlencode($result_filter); ?>&user_id=<?php echo $user_filter; ?>" 
                               class="px-3 py-1.5 bg-gray-800 text-gray-400 rounded-lg hover:bg-gray-700 text-sm transition">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>
</html>