<?php
require_once '../config.php';

// Check if user is admin
if (!isLoggedIn() || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

try {
    $today_dt = date('Y-m-d');
    $yest_dt = date('Y-m-d', strtotime("-1 day"));

    // --- 1. FINANCIAL SUMMARY ---
    $today_deposit = $pdo->query("SELECT SUM(amount) FROM transactions WHERE status = 'approved' AND type = 'deposit' AND DATE(created_at) = '$today_dt'")->fetchColumn() ?: 0;
    $yest_deposit = $pdo->query("SELECT SUM(amount) FROM transactions WHERE status = 'approved' AND type = 'deposit' AND DATE(created_at) = '$yest_dt'")->fetchColumn() ?: 0;

    // --- 2. ACQUISITION FUNNEL (TODAY) ---
    $today_reg = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = '$today_dt' AND is_admin = 0")->fetchColumn();
    $today_active = $pdo->query("SELECT COUNT(DISTINCT u.id) FROM users u JOIN transactions t ON u.id = t.user_id WHERE DATE(u.created_at) = '$today_dt' AND t.status = 'approved' AND t.type = 'deposit'")->fetchColumn();
    $today_dormant = $today_reg - $today_active;

    // --- 3. ACQUISITION FUNNEL (YESTERDAY) ---
    $yest_reg = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = '$yest_dt' AND is_admin = 0")->fetchColumn();
    $yest_active = $pdo->query("SELECT COUNT(DISTINCT u.id) FROM users u JOIN transactions t ON u.id = t.user_id WHERE DATE(u.created_at) = '$yest_dt' AND t.status = 'approved' AND t.type = 'deposit'")->fetchColumn();
    $yest_dormant = $yest_reg - $yest_active;

    // --- 4. GLOBAL LIFETIME DATA ---
    $total_base = $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 0")->fetchColumn();
    $total_recharged = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM transactions WHERE status = 'approved' AND type = 'deposit'")->fetchColumn();
    $total_unpaid = $total_base - $total_recharged;

    // --- 5. PERFORMANCE CHART DATA ---
    $week_chart = $pdo->query("SELECT DATE(created_at) as d, SUM(amount) as v FROM transactions WHERE status = 'approved' AND type = 'deposit' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY d ORDER BY d")->fetchAll();
    $week_total_vol = array_sum(array_column($week_chart, 'v'));

    // --- 6. RECENT APPROVED TRANSACTIONS ONLY ---
    $pending_trx = $pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'pending'")->fetchColumn();
    $recent_trx = $pdo->query("SELECT t.*, u.username FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.status = 'approved' ORDER BY t.created_at DESC LIMIT 5")->fetchAll();

} catch (Exception $e) { $error = $e->getMessage(); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>SGS Admin | Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700&display=swap');
        body { background-color: #0d0f14; color: #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; }
        .sidebar { background: #12151c; border-right: 1px solid #1f2530; transition: transform 0.4s ease; z-index: 1000; }
        .nav-link { color: #8a94a6; padding: 12px 16px; border-radius: 12px; display: flex; align-items: center; gap: 12px; font-size: 14px; transition: 0.2s; }
        .nav-link:hover { background: #1a1e29; color: #fff; }
        .nav-link.active { background: #2563eb; color: #fff; font-weight: 600; box-shadow: 0 4px 15px rgba(37, 99, 235, 0.2); }
        .standard-card { background: #12151c; border: 1px solid #1f2530; border-radius: 16px; }
        .label-sm { font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px; }
        @media (max-width: 1024px) { #sidebar { position: fixed; transform: translateX(-100%); } #sidebar.open { transform: translateX(0); } }
    </style>
</head>
<body class="flex flex-col lg:flex-row min-h-screen">

    <div class="lg:hidden flex items-center justify-between p-4 bg-[#12151c] border-b border-gray-800 sticky top-0 z-[1001]">
        <button onclick="toggleSidebar()" class="text-white text-xl"><i class="fas fa-bars-staggered"></i></button>
        <span class="font-bold tracking-tight text-blue-500 uppercase">SGS Executive</span>
        <div class="w-8"></div>
    </div>

   <aside id="sidebar" class="sidebar w-64 flex-shrink-0 flex flex-col fixed lg:relative lg:translate-x-0 h-screen overflow-y-auto bg-[#111827]">
    <div class="p-6">
        <h1 class="text-xl font-bold text-white mb-10 flex items-center gap-2">
            <i class="fas fa-chart-line text-blue-500"></i> Admin Panel
        </h1>

        <nav class="space-y-2">
            
            <a href="index.php" class="nav-link active">
                <i class="fas fa-home w-6 text-center"></i> Dashboard
            </a>

            <a href="users.php" class="nav-link">
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
                <a href="settings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a>
                <a href="logout.php" class="nav-link text-red-500"><i class="fas fa-power-off"></i> Logout</a>
            </div>
            </nav>
        </div>
    </aside>

    <div id="overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/60 z-[999] hidden backdrop-blur-sm"></div>

    <main class="flex-1 p-4 lg:p-8 w-full overflow-y-auto">

        <div class="mb-10">
            <h3 class="label-sm mb-4">Volume Overview</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="standard-card p-8 border-l-4 border-emerald-500 bg-gradient-to-r from-emerald-900/10 to-transparent">
                    <p class="label-sm text-emerald-500">Approved Deposit (Today)</p>
                    <h2 class="text-4xl font-bold text-emerald-400 mt-2">₹<?= number_format($today_deposit, 2) ?></h2>
                </div>
                <div class="standard-card p-8 border-l-4 border-slate-700">
                    <p class="label-sm text-gray-500">Approved Deposit (Yesterday)</p>
                    <h2 class="text-4xl font-bold text-white mt-2">₹<?= number_format($yest_deposit, 2) ?></h2>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-10">
            <div class="standard-card p-8">
                <div class="flex justify-between items-center mb-8 border-b border-gray-800 pb-4">
                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Growth Funnel (Today)</h4>
                    <span class="text-[9px] bg-emerald-500/10 text-emerald-400 px-2 py-1 rounded font-bold border border-emerald-500/20">LIVE</span>
                </div>
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div><p class="text-2xl font-bold"><?= $today_reg ?></p><p class="text-[10px] text-gray-500 uppercase mt-1">Registers</p></div>
                    <div><p class="text-2xl font-bold text-blue-500"><?= $today_active ?></p><p class="text-[10px] text-blue-600 uppercase mt-1">Recharged</p></div>
                    <div><p class="text-2xl font-bold text-rose-500"><?= $today_dormant ?></p><p class="text-[10px] text-rose-600 uppercase mt-1">No Deposit</p></div>
                </div>
            </div>

            <div class="standard-card p-8 opacity-60">
                <div class="flex justify-between items-center mb-8 border-b border-gray-800 pb-4">
                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Growth Funnel (Yesterday)</h4>
                </div>
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div><p class="text-2xl font-bold"><?= $yest_reg ?></p><p class="text-[10px] text-gray-500 uppercase mt-1">Registers</p></div>
                    <div><p class="text-2xl font-bold text-blue-500"><?= $yest_active ?></p><p class="text-[10px] text-blue-600 uppercase mt-1">Recharged</p></div>
                    <div><p class="text-2xl font-bold text-rose-500"><?= $yest_dormant ?></p><p class="text-[10px] text-rose-600 uppercase mt-1">No Deposit</p></div>
                </div>
            </div>
        </div>

        <div class="standard-card p-10 mb-10 relative overflow-hidden">
            <div class="absolute top-8 right-8 text-right">
                <p class="label-sm text-blue-400">7-Day Total Volume</p>
                <p class="text-3xl font-bold text-white">₹<?= number_format($week_total_vol) ?></p>
            </div>
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-16 flex items-center gap-2">
                <i class="fas fa-chart-bar text-blue-500"></i> Market Turnover Trend
            </h3>
            <div class="h-80 w-full">
                <canvas id="weeklyChart"></canvas>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
            <div class="standard-card p-5">
                <p class="label-sm">Total Users</p>
                <p class="text-2xl font-bold mt-1"><?= number_format($total_base) ?></p>
            </div>
            <div class="standard-card p-5">
                <p class="label-sm text-blue-500">Recharged Members</p>
                <p class="text-2xl font-bold text-blue-500 mt-1"><?= number_format($total_recharged) ?></p>
            </div>
            <div class="standard-card p-5">
                <p class="label-sm text-red-500">Not Recharged</p>
                <p class="text-2xl font-bold text-red-500 mt-1"><?= number_format($total_unpaid) ?></p>
            </div>
            <div class="standard-card p-5">
                <p class="label-sm text-yellow-500">Pending Payments</p>
                <p class="text-2xl font-bold text-yellow-500 mt-1"><?= $pending_trx ?></p>
            </div>
        </div>

        <div class="standard-card overflow-hidden mb-12 shadow-2xl">
            <div class="px-6 py-4 border-b border-gray-800 bg-[#1a1e29] flex justify-between items-center">
                <h4 class="text-xs font-bold text-emerald-500 uppercase tracking-widest italic">Approved Transaction Stream</h4>
                <a href="transactions.php" class="text-[10px] text-blue-400 font-bold uppercase hover:underline">Auditors Log</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-black/20">
                            <th class="px-6 py-4 label-sm">Reference Account</th>
                            <th class="px-6 py-4 label-sm text-center">Type</th>
                            <th class="px-6 py-4 label-sm text-right">Volume</th>
                            <th class="px-6 py-4 label-sm text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        <?php foreach($recent_trx as $t): ?>
                        <tr class="hover:bg-emerald-500/5 transition">
                            <td class="px-6 py-4 font-semibold text-slate-300"><?= htmlspecialchars($t['username']) ?></td>
                            <td class="px-6 py-4 text-center"><span class="text-[9px] font-bold px-2 py-0.5 bg-slate-800 rounded uppercase tracking-tighter"><?= $t['type'] ?></span></td>
                            <td class="px-6 py-4 text-right font-bold text-emerald-400">₹<?= number_format($t['amount'], 2) ?></td>
                            <td class="px-6 py-4 text-center"><span class="px-3 py-1 rounded-full text-[10px] font-bold bg-emerald-900/20 text-emerald-400 border border-emerald-500/20"><?= strtoupper($t['status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script>
        function toggleSidebar() { 
            document.getElementById('sidebar').classList.toggle('open'); 
            document.getElementById('overlay').classList.toggle('hidden'); 
        }

        const ctx = document.getElementById('weeklyChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($week_chart, 'd')); ?>,
                datasets: [{
                    label: 'Turnover',
                    data: <?php echo json_encode(array_column($week_chart, 'v')); ?>,
                    backgroundColor: '#2563eb',
                    borderRadius: 8,
                    barThickness: 24,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { 
                        grid: { color: 'rgba(255,255,255,0.03)', drawTicks: false }, 
                        ticks: { 
                            color: '#64748b', 
                            font: { size: 10 },
                            stepSize: 10000,
                            callback: function(value) {
                                if (value >= 1000) return '₹' + (value/1000) + 'k';
                                return '₹' + value;
                            }
                        },
                        min: 0,
                        max: 100000
                    },
                    x: { grid: { display: false }, ticks: { color: '#64748b', font: { size: 10 } } }
                }
            }
        });
    </script>
</body>
</html>