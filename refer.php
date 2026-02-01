<?php
include 'db.php';
session_start();

// ... existing PHP logic ...
// (Retaining all PHP logic as provided)
// Set Timezone
date_default_timezone_set('Asia/Kolkata');

// 1. AUTH CHECK
if(!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// 2. FETCH DATA
$query = "SELECT referral_code FROM users WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$ref_code = $user['referral_code'];
$invite_link = "https://" . $_SERVER['HTTP_HOST'] . "/register.html?ref=" . $ref_code;

// 3. LOGIC
function calculateCommission($conn, $my_id) {
    $sql = "SELECT COUNT(DISTINCT u.id) as active_count FROM users u JOIN transactions t ON u.id = t.user_id WHERE u.referred_by = ? AND t.type = 'deposit' AND t.status = 'approved'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $my_id);
    $stmt->execute();
    return ($stmt->get_result()->fetch_assoc()['active_count'] ?? 0) * 100; 
}

function getDepositStats($conn, $my_id, $period) {
    $sql = "SELECT COALESCE(SUM(t.amount), 0) as total FROM transactions t JOIN users u ON t.user_id = u.id WHERE u.referred_by = ? AND t.type = 'deposit' AND t.status = 'approved'";
    if($period == 'today') $sql .= " AND DATE(t.created_at) = CURDATE()";
    elseif($period == 'yesterday') $sql .= " AND DATE(t.created_at) = SUBDATE(CURDATE(), 1)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $my_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['total'];
}

function getUserStats($conn, $my_id, $period) {
    $sql = "SELECT COUNT(id) as total FROM users WHERE referred_by = ?";
    if($period == 'today') $sql .= " AND DATE(created_at) = CURDATE()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $my_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['total'];
}

$total_commission = calculateCommission($conn, $user_id);
$total_users = getUserStats($conn, $user_id, 'all');
$today_users = getUserStats($conn, $user_id, 'today');
$total_deposit = getDepositStats($conn, $user_id, 'all');
$today_deposit = getDepositStats($conn, $user_id, 'today');
$yesterday_deposit = getDepositStats($conn, $user_id, 'yesterday');

// Search & History Logic
$search_uid = $_GET['search_uid'] ?? '';
$list_sql = "SELECT u.id, u.created_at, (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = u.id AND type='deposit' AND status='approved') as my_dep FROM users u WHERE u.referred_by = ?";
if(!empty($search_uid)) $list_sql .= " AND u.id LIKE ?";
$list_sql .= " ORDER BY u.created_at DESC LIMIT 50";
$l_stmt = $conn->prepare($list_sql);
if(!empty($search_uid)) { $p = "%$search_uid%"; $l_stmt->bind_param("is", $user_id, $p); } else { $l_stmt->bind_param("i", $user_id); }
$l_stmt->execute();
$list_res = $l_stmt->get_result();

$history_sql = "SELECT DATE(t.created_at) as date, SUM(t.amount) as daily_total FROM transactions t JOIN users u ON t.user_id = u.id WHERE u.referred_by = ? AND t.type = 'deposit' AND t.status = 'approved' GROUP BY DATE(t.created_at) ORDER BY date DESC LIMIT 30";
$h_stmt = $conn->prepare($history_sql);
$h_stmt->bind_param("i", $user_id);
$h_stmt->execute();
$history_res = $h_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Agency Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&display=swap');

        /* --- RESPONSIVE LAYOUT FIXES --- */
        :root { --nav-height: 70px; --safe-bottom: env(safe-area-inset-bottom); }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { height: 100%; width: 100%; overflow: hidden; background-color: #0f172a; color: white; font-family: 'Poppins', sans-serif; touch-action: manipulation; }
        body { display: flex; flex-direction: column; }
        .gaming-font { font-family: 'Rajdhani', sans-serif; }

        /* SCROLLABLE CONTENT AREA */
        .content-area { flex: 1; overflow-y: auto; overflow-x: hidden; padding-bottom: calc(var(--nav-height) + 20px + var(--safe-bottom)); -webkit-overflow-scrolling: touch; }

        /* HEADER */
        .agency-header { background: linear-gradient(180deg, #10b981 0%, #0284c7 100%); padding: 20px; padding-top: calc(15px + env(safe-area-inset-top)); border-radius: 0 0 30px 30px; box-shadow: 0 10px 30px rgba(2, 132, 199, 0.3); margin-bottom: 20px; flex-shrink: 0; }

        /* STATS */
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 0 16px; }
        .stat-box { background: #1e293b; border: 1px solid rgba(255,255,255,0.05); border-radius: 16px; padding: 15px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }

        /* TABS */
        .tab-btn { flex: 1; padding: 10px; text-align: center; font-size: 13px; border-radius: 10px; color: #94a3b8; transition: 0.3s; }
        .tab-btn.active { background: #3b82f6; color: white; font-weight: 700; }

        /* MENU ITEMS */
        .menu-item { background: #1e293b; padding: 16px; border-radius: 16px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; border: 1px solid rgba(255,255,255,0.05); }
        .menu-item:active { transform: scale(0.98); background: #334155; }

        /* BOTTOM SHEETS */
        .bottom-sheet { position: fixed; bottom: -100%; left: 0; right: 0; background: #1e293b; border-radius: 24px 24px 0 0; padding: 20px; padding-bottom: 100px; z-index: 500; transition: bottom 0.3s cubic-bezier(0.4, 0, 0.2, 1); height: 80vh; display: flex; flex-direction: column; box-shadow: 0 -10px 40px rgba(0,0,0,0.5); }
        .bottom-sheet.active { bottom: 0; }
        .overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 400; backdrop-filter: blur(2px); }

        /* FIXED NAV BAR */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(30, 41, 59, 0.95);
            border-top: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: space-around;
            padding: 12px 0 10px;
            z-index: 1000;
            backdrop-filter: blur(12px);
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: rgba(255,255,255,0.65);
            font-size: 12px;
            transition: all 0.3s ease;
            padding: 6px 16px;
            border-radius: 16px;
            position: relative;
        }

        .nav-item i {
            font-size: 20px;
            margin-bottom: 4px;
        }

        .nav-item.active {
            background: rgba(139, 92, 246, 0.25);
            color: #fff;
            transform: translateY(-6px);
        }

        .nav-item.active i {
            color: #8b5cf6;
            text-shadow: 0 0 10px rgba(139,92,246,0.8);
        }

        .nav-item.active::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 6px;
            height: 6px;
            background: #8b5cf6;
            border-radius: 50%;
            box-shadow: 0 0 10px #8b5cf6;
        }

        .nav-item:hover {
            color: #fff;
            background: rgba(255,255,255,0.05);
        }

        /* Page content ke niche space */
        body { padding-bottom: 75px; }

        /* Utility */
        #toast { visibility: hidden; min-width: 200px; background: #333; color: #fff; text-align: center; border-radius: 50px; padding: 12px; position: fixed; z-index: 2000; left: 50%; bottom: 100px; transform: translateX(-50%); font-size: 12px; }
        #toast.show { visibility: visible; animation: fadein 0.5s, fadeout 0.5s 2.5s; }
        @keyframes fadein { from {bottom: 80px; opacity: 0;} to {bottom: 100px; opacity: 1;} }
        @keyframes fadeout { from {bottom: 100px; opacity: 1;} to {bottom: 80px; opacity: 0;} }
    </style>
</head>
<body>

    <div class="content-area">
        
        <div class="agency-header">
            <div class="flex justify-between items-center mb-6 text-white/90">
                <i class="fas fa-chevron-left text-xl p-2" onclick="location.href='index.php'"></i>
                <span class="font-bold text-lg">Promotion</span>
                <i class="fas fa-headset text-xl p-2 cursor-pointer" onclick="window.location.href='https://play.1xclube.org/support'"></i>
            </div>
            
            <div class="text-center pb-4">
                <p class="text-xs text-white/70 uppercase font-bold tracking-widest mb-1">Total Commission</p>
                <h1 class="text-6xl font-bold gaming-font">₹<?php echo number_format($total_commission, 2); ?></h1>
            </div>
        </div>

        <div class="px-4 mb-4">
            <div class="bg-[#1e293b] rounded-2xl p-4 border border-white/10 relative overflow-hidden shadow-lg">
                <div class="absolute top-0 right-0 p-2 opacity-5 pointer-events-none">
                    <i class="fas fa-gift text-6xl text-white"></i>
                </div>
                <div class="flex justify-between items-center mb-2">
                    <p class="text-[10px] text-gray-400 uppercase tracking-wider font-bold">My Referral Code</p>
                    <span class="text-[10px] text-green-400 font-bold bg-green-500/10 px-2 py-0.5 rounded">Active</span>
                </div>
                <div class="flex items-center justify-between bg-black/30 rounded-xl p-3 border border-dashed border-white/20">
                    <span class="text-xl font-mono font-bold text-white tracking-widest pl-1" id="codeTxt"><?php echo $ref_code; ?></span>
                    <button onclick="copyCode()" class="bg-blue-600 active:scale-95 text-white px-4 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1 shadow-lg shadow-blue-500/30">
                        <i class="far fa-copy"></i> COPY
                    </button>
                </div>
            </div>
        </div>

        <div class="px-4 mb-4">
            <div class="flex bg-[#1e293b] p-1 rounded-xl border border-white/5">
                <div class="tab-btn active" onclick="switchStats('today', this)">Today</div>
                <div class="tab-btn" onclick="switchStats('total', this)">Total</div>
            </div>
        </div>

        <div id="stats-today" class="stats-grid">
            <div class="stat-box border-l-4 border-green-500">
                <p class="text-2xl font-bold gaming-font text-green-400">₹<?php echo number_format($today_deposit, 2); ?></p>
                <p class="text-[10px] text-gray-400 uppercase mt-1">Today's Deposit</p>
            </div>
            <div class="stat-box border-l-4 border-blue-500">
                <p class="text-2xl font-bold gaming-font text-blue-400"><?php echo $today_users; ?></p>
                <p class="text-[10px] text-gray-400 uppercase mt-1">New Register</p>
            </div>
        </div>

        <div id="stats-total" class="stats-grid hidden">
            <div class="stat-box border-l-4 border-purple-500">
                <p class="text-2xl font-bold gaming-font text-purple-400">₹<?php echo number_format($total_deposit, 2); ?></p>
                <p class="text-[10px] text-gray-400 uppercase mt-1">Total Deposit</p>
            </div>
            <div class="stat-box border-l-4 border-orange-500">
                <p class="text-2xl font-bold gaming-font text-orange-400"><?php echo $total_users; ?></p>
                <p class="text-[10px] text-gray-400 uppercase mt-1">Total Registered</p>
            </div>
        </div>

        <div class="px-4 mt-6 space-y-3">
            <div class="bg-gradient-to-r from-indigo-600 to-blue-600 rounded-xl p-4 flex items-center justify-between shadow-lg" onclick="copyLink()">
                <div>
                    <p class="font-bold text-base">Refer & Earn ₹100</p>
                    <p class="text-[10px] text-white/70">On friend's deposit</p>
                </div>
                <button class="bg-white text-indigo-600 px-3 py-1.5 rounded-lg text-xs font-bold">COPY LINK</button>
            </div>

            <div class="menu-item" onclick="openSheet()">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-orange-500/20 flex items-center justify-center text-orange-500">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-bold">Subordinate Data</p>
                        <p class="text-[10px] text-gray-500">View team list</p>
                    </div>
                    <i class="fas fa-chevron-right text-gray-500 text-xs"></i>
                </div>
            </div>

            <div class="menu-item" onclick="openDepositHistory()">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-blue-500/20 flex items-center justify-center text-blue-500">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-bold">Turnover History</p>
                        <p class="text-[10px] text-gray-500">Day-wise collection</p>
                    </div>
                    <i class="fas fa-chevron-right text-gray-500 text-xs"></i>
                </div>
            </div>
        </div>
    </div> <input type="text" id="linkInput" value="<?php echo $invite_link; ?>" class="hidden">

    <div class="overlay" id="overlay" onclick="closeAllSheets()"></div>

    <div class="bottom-sheet" id="sheet">
        <div class="flex justify-between items-center mb-4 pb-4 border-b border-white/10">
            <h3 class="text-lg font-bold">Team List <span class="text-xs bg-blue-600 px-2 py-0.5 rounded-full ml-2"><?php echo $list_res->num_rows; ?></span></h3>
            <div class="w-8 h-8 bg-white/10 rounded-full flex items-center justify-center cursor-pointer" onclick="closeAllSheets()">
                <i class="fas fa-times text-gray-400"></i>
            </div>
        </div>

        <form method="GET" action="refer.php" class="mb-4 relative">
            <input type="text" name="search_uid" value="<?php echo htmlspecialchars($search_uid); ?>" class="w-full bg-black/30 border border-white/10 rounded-xl py-3 pl-10 pr-4 text-sm text-white outline-none focus:border-blue-500" placeholder="Search UID">
            <i class="fas fa-search absolute left-3 top-3.5 text-gray-500"></i>
            <input type="hidden" name="sheet" value="open">
        </form>

        <div class="overflow-y-auto flex-1 space-y-2 pb-10">
            <?php if($list_res->num_rows > 0): ?>
                <?php while($row = $list_res->fetch_assoc()): ?>
                <div class="bg-white/5 p-3 rounded-xl flex justify-between items-center border border-white/5">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-gray-800 flex items-center justify-center text-gray-400 text-xs border border-white/10"><i class="fas fa-user"></i></div>
                        <div>
                            <p class="text-sm font-bold text-white">UID: <?php echo $row['id']; ?></p>
                            <p class="text-[10px] text-gray-500"><?php echo date('d M, Y', strtotime($row['created_at'])); ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-green-400 font-bold gaming-font">₹<?php echo number_format($row['my_dep'], 2); ?></p>
                        <p class="text-[9px] <?php echo $row['my_dep'] > 0 ? 'text-blue-400' : 'text-gray-500'; ?> uppercase font-bold"><?php echo $row['my_dep'] > 0 ? 'Active' : 'Reg.'; ?></p>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-center text-gray-500 text-sm py-10">No subordinates found</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="bottom-sheet" id="depositHistorySheet">
        <div class="flex justify-between items-center mb-4 pb-4 border-b border-white/10">
            <h3 class="text-lg font-bold">Team Deposit History</h3>
            <div class="w-8 h-8 bg-white/10 rounded-full flex items-center justify-center cursor-pointer" onclick="closeAllSheets()">
                <i class="fas fa-times text-gray-400"></i>
            </div>
        </div>
        <div class="overflow-y-auto flex-1 space-y-2 pb-10">
            <?php if($history_res->num_rows > 0): ?>
                <?php while($h_row = $history_res->fetch_assoc()): ?>
                <div class="bg-white/5 p-3 rounded-xl flex justify-between items-center border border-white/5">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center text-blue-400"><i class="far fa-calendar-check text-xs"></i></div>
                        <div>
                            <p class="text-sm font-bold text-white"><?php echo date('d M, Y', strtotime($h_row['date'])); ?></p>
                            <p class="text-[10px] text-gray-500"><?php echo date('l', strtotime($h_row['date'])); ?></p>
                        </div>
                    </div>
                    <p class="text-green-400 font-bold gaming-font">₹<?php echo number_format($h_row['daily_total'], 2); ?></p>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-center text-gray-500 text-sm py-10">No deposits in last 30 days</p>
            <?php endif; ?>
        </div>
    </div>

    <div id="toast">Action Successful</div>

        <!-- Bottom Navigation -->
        <div class="bottom-nav">
            <a href="index.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>

            <a href="kyc.html" class="nav-item">
                <i class="fas fa-gamepad"></i>
                <span>Kyc</span>
            </a>

            <a href="wallet.php" class="nav-item">
                <i class="fas fa-wallet"></i>
                <span>Wallet</span>
            </a>

            <a href="refer.php" class="nav-item active">
                <i class="fas fa-gift"></i>
                <span>Promos</span>
            </a>

            <a href="profile.php" class="nav-item">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        </div>

    <script>
        function copyLink() {
            var el = document.getElementById('linkInput');
            navigator.clipboard.writeText(el.value);
            showToast('Link Copied!');
        }
        function copyCode() {
            navigator.clipboard.writeText('<?php echo $ref_code; ?>');
            showToast('Code Copied!');
        }
        function showToast(msg) {
            var t = document.getElementById('toast');
            t.innerText = msg; t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 2000);
        }
        function switchStats(type, btn) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            if(type === 'today') {
                document.getElementById('stats-today').classList.remove('hidden');
                document.getElementById('stats-total').classList.add('hidden');
            } else {
                document.getElementById('stats-today').classList.add('hidden');
                document.getElementById('stats-total').classList.remove('hidden');
            }
        }
        function openSheet() { document.getElementById('overlay').style.display = 'block'; setTimeout(() => document.getElementById('sheet').classList.add('active'), 10); }
        function openDepositHistory() { document.getElementById('overlay').style.display = 'block'; setTimeout(() => document.getElementById('depositHistorySheet').classList.add('active'), 10); }
        function closeAllSheets() {
            document.querySelectorAll('.bottom-sheet').forEach(s => s.classList.remove('active'));
            setTimeout(() => document.getElementById('overlay').style.display = 'none', 300);
        }
        <?php if(isset($_GET['sheet'])): ?> openSheet(); <?php endif; ?>
    </script>

</body>
</html>