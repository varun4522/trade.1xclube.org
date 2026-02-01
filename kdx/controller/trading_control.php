<?php
// 1. DATABASE CONNECTION & CONFIG
$config_path = file_exists('../config.php') ? '../config.php' : '../../config.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    $host = 'localhost';
    $db   = 'chikenof_chick';
    $user = 'chikenof_chick';
    $pass = 'chikenof_chick';
    $charset = 'utf8mb4';
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    try { $pdo = new PDO($dsn, $user, $pass); } catch (\PDOException $e) {}
}

// --- LIVE DATA API (TIME FIX IMPLEMENTED) ---
if (isset($_GET['api']) && $_GET['api'] == 'live_stats') {
    ob_clean();
    header('Content-Type: application/json');
    
    if(!isset($pdo)) { echo json_encode(['error' => 'DB Connection Failed']); exit; }

    try {
        // Total Stats
        $q1 = $pdo->query("SELECT SUM(wager) as total_amount, COUNT(DISTINCT user_id) as active_users FROM trades WHERE result = 'pending'");
        $stats = $q1->fetch(PDO::FETCH_ASSOC);
        
        // FIXED TIME SQL: Using TIMESTAMPDIFF to get accurate seconds (30s, 29s...)
        $sql = "SELECT id, user_id, wager, direction, start_price, created_at, duration, 
                (duration - TIMESTAMPDIFF(SECOND, created_at, NOW())) as remaining 
                FROM trades WHERE result = 'pending' ORDER BY id DESC LIMIT 50";
        $q2 = $pdo->query($sql);
        $trades = $q2->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'total_amount' => floatval($stats['total_amount'] ?? 0),
            'active_users' => intval($stats['active_users'] ?? 0),
            'trades' => $trades
        ]);
    } catch(Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
    exit;
}
// ---------------------

require_once 'includes/header.php';

$success_message = '';
$error_message = '';

// Handle Settings Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $trading_mode = isset($_POST['trading_mode']) ? 'true' : 'false'; 
        $trading_win_chance = intval($_POST['trading_win_chance'] ?? 40); 
        $trading_next_result = $_POST['trading_next_result'] ?? 'random'; 

        if(isset($pdo)) {
            $settings = [
                'trading_mode' => $trading_mode,
                'trading_win_chance' => $trading_win_chance,
                'trading_next_result' => $trading_next_result
            ];
            foreach ($settings as $key => $val) {
                $stmt = $pdo->prepare("INSERT INTO admin_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$key, $val, $val]);
            }
            $success_message = "✅ Trading Settings Updated!";
        }
    } catch (Exception $e) { $error_message = "❌ Error: " . $e->getMessage(); }
}

// Fetch Current Settings
$s = [];
if(isset($pdo)) {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM admin_settings WHERE setting_key LIKE 'trading_%'");
    while ($row = $stmt->fetch()) { $s[$row['setting_key']] = $row['setting_value']; }
}

$is_manual = ($s['trading_mode'] ?? 'false') === 'true';
$win_chance = intval($s['trading_win_chance'] ?? 40);
$next_res = $s['trading_next_result'] ?? 'random';
?>

<div class="max-w-6xl mx-auto mt-6 px-4 pb-10">
    <div class="mb-6 flex flex-col md:flex-row justify-between items-center gap-4">
        <div>
            <h2 class="text-3xl font-bold text-white flex items-center gap-3">
                <i class="fa-solid fa-chart-line text-green-500"></i> Trading Monitor
            </h2>
            <p class="text-gray-400 text-sm mt-1">Real-time Trade Analytics & Result Control</p>
        </div>
        <div class="bg-gray-800 px-4 py-2 rounded-lg border border-gray-700 flex items-center gap-2">
            <div class="w-2 h-2 rounded-full <?php echo $is_manual ? 'bg-blue-500' : 'bg-green-500'; ?> animate-pulse"></div>
            <span class="text-white font-bold text-sm" id="modeLabel"><?php echo $is_manual ? 'Manual Mode' : 'Auto (RTP)'; ?></span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <div class="bg-gray-800 rounded-2xl p-6 border border-gray-700 shadow-lg relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-10"><i class="fa-solid fa-sack-dollar text-6xl text-yellow-500"></i></div>
            <h3 class="text-gray-400 text-xs font-bold uppercase tracking-wider mb-1">Live Active Volume</h3>
            <div class="text-3xl font-mono font-bold text-white flex items-center gap-2">
                <span class="text-yellow-500">₹</span><span id="liveTotalAmt">0.00</span>
            </div>
        </div>
        <div class="bg-gray-800 rounded-2xl p-6 border border-gray-700 shadow-lg relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-10"><i class="fa-solid fa-users text-6xl text-blue-500"></i></div>
            <h3 class="text-gray-400 text-xs font-bold uppercase tracking-wider mb-1">Active Players</h3>
            <div class="text-3xl font-mono font-bold text-white"><span id="liveUserCount">0</span></div>
        </div>
        <div class="bg-gray-800 rounded-2xl p-0 border border-gray-700 shadow-lg flex flex-col h-64 lg:h-auto overflow-hidden">
            <div class="bg-gray-900/50 px-4 py-2 border-b border-gray-700 flex justify-between items-center">
                <span class="text-xs font-bold text-gray-300 uppercase tracking-widest">Live Feed</span>
                <span class="text-[10px] bg-red-500/20 text-red-400 px-2 py-0.5 rounded animate-pulse">LIVE MONITOR</span>
            </div>
            <div id="liveFeed" class="flex-1 overflow-y-auto p-2 space-y-2 custom-scrollbar" style="min-height: 200px;">
                <div class="text-center text-gray-500 text-xs mt-4 italic">Waiting for bets...</div>
            </div>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div id="msgBox" class="bg-green-500/10 border border-green-500/50 text-green-400 px-6 py-4 rounded-xl mb-6 flex items-center gap-3 animate__animated animate__fadeInDown">
            <i class="fa-solid fa-check-circle text-xl"></i> <?= htmlspecialchars($success_message); ?>
        </div>
        <script>setTimeout(() => { document.getElementById('msgBox').style.display = 'none'; }, 3000);</script>
    <?php endif; ?>

    <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-3 bg-gray-800 rounded-2xl p-6 border border-gray-700 shadow-xl">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-gray-700 flex items-center justify-center"><i class="fa-solid fa-sliders text-xl text-gray-300"></i></div>
                    <div><h3 class="text-white font-bold">Control Logic</h3><p class="text-xs text-gray-400">Random RTP vs Forced Decision</p></div>
                </div>
                <div class="flex items-center gap-3 bg-gray-900 p-2 rounded-lg border border-gray-600">
                    <span class="text-xs font-bold text-green-400 pl-2">AUTO</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="trading_mode" id="modeSwitch" class="sr-only peer" onchange="toggleUI()" <?= $is_manual ? 'checked' : ''; ?>>
                        <div class="w-12 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                    <span class="text-xs font-bold text-blue-400 pr-2">MANUAL</span>
                </div>
            </div>
        </div>

        <div id="autoBox" class="md:col-span-3 bg-gray-800 rounded-2xl p-8 border border-green-500/30 shadow-lg <?= $is_manual ? 'hidden' : ''; ?>">
            <h3 class="text-xl font-bold text-green-400 mb-6 flex items-center gap-2"><i class="fa-solid fa-dice"></i> Auto Win Rate</h3>
            <div class="mb-4">
                <div class="flex justify-between text-sm font-bold text-gray-300 mb-2"><span>Admin Profitability (User Win %)</span><span id="winDisplay" class="text-green-400"><?= $win_chance; ?>% Win Rate</span></div>
                <input type="range" name="trading_win_chance" min="0" max="100" value="<?= $win_chance; ?>" class="w-full h-3 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-green-500" oninput="document.getElementById('winDisplay').innerText = this.value + '% Win Rate'">
            </div>
        </div>

        <div id="manualBox" class="md:col-span-3 bg-gray-800 rounded-2xl p-8 border border-blue-500/30 shadow-lg <?= $is_manual ? '' : 'hidden'; ?>">
            <h3 class="text-xl font-bold text-blue-400 mb-6 flex items-center gap-2"><i class="fa-solid fa-gavel"></i> Global Result Forcer</h3>
            <div class="grid grid-cols-3 gap-4">
                <label class="cursor-pointer">
                    <input type="radio" name="trading_next_result" value="win" class="peer sr-only" <?= $next_res=='win'?'checked':''; ?>>
                    <div class="p-4 rounded-xl bg-gray-700 border-2 border-transparent peer-checked:border-green-500 peer-checked:bg-green-500/20 text-center transition-all">
                        <i class="fa-solid fa-trophy text-2xl text-green-400 mb-2"></i><div class="font-bold text-white">FORCE WIN</div>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="trading_next_result" value="loss" class="peer sr-only" <?= $next_res=='loss'?'checked':''; ?>>
                    <div class="p-4 rounded-xl bg-gray-700 border-2 border-transparent peer-checked:border-red-500 peer-checked:bg-red-500/20 text-center transition-all">
                        <i class="fa-solid fa-skull text-2xl text-red-400 mb-2"></i><div class="font-bold text-white">FORCE LOSS</div>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="trading_next_result" value="random" class="peer sr-only" <?= $next_res=='random'?'checked':''; ?>>
                    <div class="p-4 rounded-xl bg-gray-700 border-2 border-transparent peer-checked:border-gray-400 peer-checked:bg-gray-600 text-center transition-all">
                        <i class="fa-solid fa-shuffle text-2xl text-gray-400 mb-2"></i><div class="font-bold text-white">NORMAL (50/50)</div>
                    </div>
                </label>
            </div>
        </div>

        <div class="md:col-span-3">
            <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-bold py-4 rounded-xl shadow-lg transition-all active:scale-95">SAVE CONFIGURATION</button>
        </div>
    </form>
</div>

<script>
let current_market_price = 0;

function toggleUI() {
    const isMan = document.getElementById('modeSwitch').checked;
    document.getElementById('manualBox').classList.toggle('hidden', !isMan);
    document.getElementById('autoBox').classList.toggle('hidden', isMan);
    document.getElementById('modeLabel').innerText = isMan ? 'Manual Mode' : 'Auto (RTP)';
}

// Simulating market price noise to check live win/loss status in admin
function updateDummyPrice() {
    if(current_market_price === 0) current_market_price = 64186.00;
    current_market_price += (Math.random() - 0.5) * 8;
}
setInterval(updateDummyPrice, 1000);

function fetchLiveStats() {
    fetch('?api=live_stats')
        .then(r => r.json())
        .then(data => {
            if(data.error) return;

            document.getElementById('liveTotalAmt').innerText = data.total_amount.toFixed(2);
            document.getElementById('liveUserCount').innerText = data.active_users;
            
            const list = document.getElementById('liveFeed');
            if(data.trades.length === 0) {
                list.innerHTML = '<div class="text-center text-gray-500 text-xs mt-4 italic">No active trades currently...</div>';
            } else {
                let html = '';
                data.trades.forEach(t => {
                    const dir = t.direction.toLowerCase();
                    const isUp = (dir === 'up' || dir === 'call');
                    const entryPrice = parseFloat(t.start_price);
                    
                    // --- LIVE WIN/LOSS PREDICTION ---
                    let statusBadge = '<span class="text-[9px] font-bold text-gray-400 bg-gray-900/50 px-1.5 py-0.5 rounded">WAITING</span>';
                    if(current_market_price > 0) {
                        const userWinning = (isUp && current_market_price > entryPrice) || (!isUp && current_market_price < entryPrice);
                        statusBadge = userWinning 
                            ? '<span class="text-[9px] font-bold text-green-400 bg-green-900/30 px-1.5 py-0.5 rounded border border-green-500/30">USER WINNING</span>' 
                            : '<span class="text-[9px] font-bold text-red-400 bg-red-900/30 px-1.5 py-0.5 rounded border border-red-500/30">USER LOSING</span>';
                    }

                    // --- TIME FORMAT FIX (Ensuring proper countdown) ---
                  // यह लॉजिक किसी भी बड़े नंबर को शुद्ध सेकंड्स (0-59) में बदल देगा

  let timeLeft = Math.max(0, parseInt(t.remaining));
  if (timeLeft > 60) {
    // अगर 1 मिनट वाला ट्रेड है तो 60 से ऊपर का हिस्सा हटा देगा
    timeLeft = timeLeft % 60; 
 }
    let timeDisplay = timeLeft + 's';

                    html += `
                        <div class="bg-gray-700/40 p-3 rounded-xl border border-gray-600 animate__animated animate__fadeIn">
                            <div class="flex justify-between items-start mb-2">
                                <div><span class="text-white font-bold text-sm">UID: ${t.user_id}</span></div>
                                <div><span class="text-yellow-400 font-mono font-bold text-sm bg-yellow-900/20 px-2 py-0.5 rounded">${timeDisplay}</span></div>
                            </div>
                            <div class="flex justify-between items-center bg-gray-900/40 p-2 rounded-lg">
                                <div><span class="text-white font-bold text-xs">₹${parseFloat(t.wager).toFixed(2)}</span></div>
                                <div>${statusBadge}</div>
                                <div>${isUp ? '<span class="text-green-400 font-bold text-[10px]">UP ▲</span>' : '<span class="text-red-400 font-bold text-[10px]">DOWN ▼</span>'}</div>
                            </div>
                        </div>`;
                });
                list.innerHTML = html;
            }
        });
}

setInterval(fetchLiveStats, 1000);
fetchLiveStats(); 
</script>
</body>
</html>