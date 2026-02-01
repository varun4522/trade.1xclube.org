<?php
require_once '../config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    // Total Count
    $total_stmt = $pdo->query("SELECT COUNT(*) FROM payouts");
    $total_rows = $total_stmt->fetchColumn();
    $total_pages = ceil($total_rows / $limit);

    // Fetch Payouts (Ab isme utr column bhi aayega kyunki SELECT * hai)
    $stmt = $pdo->prepare("SELECT * FROM payouts ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute();
    $payouts = $stmt->fetchAll();

} catch (Exception $e) {
    $error_message = "Error fetching data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Payout History - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #0d1117; color: #e5e7eb; }
        .card { background: #1f2937; border: 1px solid #374151; }
        .status-badge { padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .status-success { background: rgba(34, 197, 94, 0.1); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.2); }
        .status-failed { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }
        .status-pending { background: rgba(234, 179, 8, 0.1); color: #facc15; border: 1px solid rgba(234, 179, 8, 0.2); }
    </style>
</head>
<body class="flex flex-col lg:flex-row min-h-screen bg-[#0d1117]">

    <?php include 'sidebar.php'; ?>

    <div class="flex-1 w-full p-4 lg:p-8">
        <div class="animate__animated animate__fadeIn">
            
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-white">Payout History</h2>
                    <p class="text-sm text-gray-400">Track all manual withdrawals</p>
                </div>
                <a href="kajana.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2">
                    <i class="fas fa-plus"></i> New Payout
                </a>
            </div>

            <div class="card rounded-xl overflow-hidden shadow-xl">
                <div class="overflow-x-auto">
                    <table class="w-full whitespace-nowrap text-left text-sm">
                        <thead class="bg-gray-800/50 text-gray-400 text-xs uppercase font-semibold">
                            <tr>
                                <th class="px-6 py-4">Order ID</th>
                                <th class="px-6 py-4">Beneficiary</th>
                                <th class="px-6 py-4">Amount</th>
                                <th class="px-6 py-4">UTR Number</th>
                                <th class="px-6 py-4">Ifsc Code</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Response / Error</th>
                                <th class="px-6 py-4">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            <?php if (empty($payouts)): ?>
                                <tr>
                                    <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                        No payout history found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payouts as $row): ?>
                                    <tr class="hover:bg-gray-700/30 transition">
                                        <td class="px-6 py-4 font-mono text-xs text-gray-400">
                                            <?php echo htmlspecialchars($row['order_id']); ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="font-medium text-white"><?php echo htmlspecialchars($row['account_name']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['account_number']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 font-bold text-white">
                                            ₹<?php echo number_format($row['amount'], 2); ?>
                                        </td>
                                        
                                        <td class="px-6 py-4">
                                            <?php if (!empty($row['utr'])): ?>
                                                <span class="font-mono text-xs text-blue-300 bg-blue-900/20 px-2 py-1 rounded">
                                                    <?php echo htmlspecialchars($row['utr']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-gray-600">-</span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="px-6 py-4 text-xs text-gray-400">
                                            <div class="font-mono"><?php echo htmlspecialchars($row['ifsc']); ?></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php 
                                                $s = $row['status'];
                                                $cls = ($s=='success') ? 'status-success' : (($s=='failed') ? 'status-failed' : 'status-pending');
                                            ?>
                                            <span class="status-badge <?php echo $cls; ?>"><?php echo ucfirst($s); ?></span>
                                        </td>
                                        <td class="px-6 py-4 text-xs max-w-xs truncate">
                                            <?php 
                                                // Extract error message from JSON if possible
                                                $api_resp = json_decode($row['api_response'], true);
                                                if(isset($api_resp['errorMsg'])) {
                                                    echo '<span class="text-red-400" title="'.$api_resp['errorMsg'].'">'.$api_resp['errorMsg'].'</span>';
                                                } elseif(isset($api_resp['message'])) {
                                                    echo '<span class="text-gray-400">'.$api_resp['message'].'</span>';
                                                } else {
                                                    echo '<span class="text-gray-600 truncate">'.htmlspecialchars(substr($row['api_response'], 0, 30)).'...</span>';
                                                }
                                            ?>
                                        </td>
                                        <td class="px-6 py-4 text-xs text-gray-500">
                                            <?php echo date('d M, H:i', strtotime($row['created_at'])); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="p-4 border-t border-gray-700 flex justify-center gap-2 bg-gray-800/30">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>" class="px-3 py-1 bg-gray-700 rounded text-sm hover:bg-gray-600 text-white">Prev</a>
                        <?php endif; ?>
                        <span class="px-3 py-1 text-sm text-gray-400">Page <?php echo $page; ?></span>
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>" class="px-3 py-1 bg-gray-700 rounded text-sm hover:bg-gray-600 text-white">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</body>
</html>