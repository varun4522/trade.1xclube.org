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
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    try { $pdo = new PDO($dsn, $user, $pass); } catch (\PDOException $e) {}
}

// --- LIVE DATA API ---
if (isset($_GET['api']) && $_GET['api'] == 'live_stats') {
    ob_clean();
    header('Content-Type: application/json');
    try {
        // Today's Stats
        $q1 = $pdo->query("SELECT SUM(bet_amount) as vol, COUNT(DISTINCT user_id) as active_players FROM hilo_history WHERE DATE(created_at) = CURDATE()");
        $stats = $q1->fetch(PDO::FETCH_ASSOC);
        
        // Recent History Feed
        $q2 = $pdo->query("SELECT user_id, bet_amount, status, created_at, multiplier FROM hilo_history ORDER BY id DESC LIMIT 2");
        $feed = $q2->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'volume' => floatval($stats['vol'] ?? 0),
            'players' => intval($stats['active_players'] ?? 0),
            'feed' => $feed
        ]);
    } catch(Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
    exit;
}

require_once 'includes/header.php';

$success_message = '';

// Handle Settings Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hilo_mode = isset($_POST['hilo_mode']) ? 'true' : 'false';
    $hilo_win_chance = $_POST['hilo_win_chance'] ?? '50';
    $hilo_force_result = $_POST['hilo_force_result'] ?? 'normal';

    $settings = [
        'hilo_mode' => $hilo_mode,
        'hilo_win_chance' => $hilo_win_chance,
        'hilo_force_result' => $hilo_force_result
    ];

    foreach ($settings as $key => $val) {
        $stmt = $pdo->prepare("INSERT INTO admin_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $val, $val]);
    }
    $success_message = "✅ Hilo Settings Updated!";
}

// Fetch Current Settings
$s = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM admin_settings WHERE setting_key LIKE 'hilo_%'");
while ($row = $stmt->fetch()) { $s[$row['setting_key']] = $row['setting_value']; }

$is_manual = ($s['hilo_mode'] ?? 'false') === 'true';
$win_chance = $s['hilo_win_chance'] ?? '50';
$force_res = $s['hilo_force_result'] ?? 'normal';
?>

<div class="max-w-6xl mx-auto mt-6 px-4 pb-10">
    <div class="mb-6 flex flex-col md:flex-row justify-between items-center gap-4">
        <div>
            <h2 class="text-3xl font-bold text-white flex items-center gap-3">
                <i class="fa-solid fa-cards text-purple-500"></i> Hilo Monitor
            </h2>
            <p class="text-gray-400 text-sm mt-1">Real-time Card Analytics & Win/Loss Control</p>
        </div>
        <div class="bg-gray-800 px-4 py-2 rounded-lg border border-gray-700 flex items-center gap-2">
            <div class="w-2 h-2 rounded-full <?php echo $is_manual ? 'bg-purple-500' : 'bg-green-500'; ?> animate-pulse"></div>
            <span class="text-white font-bold text-sm"><?php echo $is_manual ? 'Manual (God Mode)' : 'Auto (RTP)'; ?></span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <div class="bg-gray-800 rounded-2xl p-6 border border-gray-700 shadow-lg relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-10"><i class="fa-solid fa-sack-dollar text-6xl text-yellow-500"></i></div>
            <h3 class="text-gray-400 text-xs font-bold uppercase tracking-wider mb-1">Today's Volume</h3>
            <div class="text-3xl font-mono font-bold text-white flex items-center gap-2">
                <span class="text-yellow-500">₹</span><span id="liveVol">0.00</span>
            </div>
        </div>
        <div class="bg-gray-800 rounded-2xl p-6 border border-gray-700 shadow-lg relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-10"><i class="fa-solid fa-users text-6xl text-blue-500"></i></div>
            <h3 class="text-gray-400 text-xs font-bold uppercase tracking-wider mb-1">Total Players</h3>
            <div class="text-3xl font-mono font-bold text-white"><span id="livePlayers">0</span></div>
        </div>
        <div class="bg-gray-800 rounded-2xl border border-gray-700 shadow-lg flex flex-col h-64 lg:h-auto overflow-hidden">
            <div class="bg-gray-900/50 px-4 py-2 border-b border-gray-700 flex justify-between items-center">
                <span class="text-xs font-bold text-gray-300">LIVE FEED</span>
                <span class="text-[10px] bg-red-500/20 text-red-400 px-2 py-0.5 rounded animate-pulse">MONITOR</span>
            </div>
            <div id="liveFeed" class="flex-1 overflow-y-auto p-2 space-y-2 custom-scrollbar" style="min-height: 200px;">
                <div class="text-center text-gray-500 text-xs mt-4 italic">Waiting for bets...</div>
            </div>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div id="msgBox" class="bg-green-500/10 border border-green-500/50 text-green-400 px-6 py-4 rounded-xl mb-6 flex items-center gap-3">
            <i class="fa-solid fa-check-circle text-xl"></i> <?= $success_message; ?>
        </div>
        <script>setTimeout(() => { document.getElementById('msgBox').style.display = 'none'; }, 3000);</script>
    <?php endif; ?>

    <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-3 bg-gray-800 rounded-2xl p-6 border border-gray-700 shadow-xl flex justify-between items-center">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-gray-700 flex items-center justify-center"><i class="fa-solid fa-sliders text-xl text-gray-300"></i></div>
                <div><h3 class="text-white font-bold">Control Strategy</h3><p class="text-xs text-gray-400">Random RTP vs Forced Decision</p></div>
            </div>
            <div class="flex items-center gap-3 bg-gray-900 p-2 rounded-xl border border-gray-600">
                <span class="text-xs font-bold text-green-400">AUTO</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="hilo_mode" id="modeSwitch" class="sr-only peer" onchange="toggleUI()" <?= $is_manual ? 'checked' : ''; ?>>
                    <div class="w-14 h-7 bg-gray-700 rounded-full peer peer-checked:bg-purple-600 after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:after:translate-x-full"></div>
                </label>
                <span class="text-xs font-bold text-purple-400">MANUAL</span>
            </div>
        </div>

        <div id="autoBox" class="md:col-span-3 bg-gray-800 rounded-2xl p-8 border border-green-500/30 shadow-lg <?= $is_manual ? 'hidden' : ''; ?>">
            <h3 class="text-xl font-bold text-green-400 mb-6 flex items-center gap-2"><i class="fa-solid fa-dice"></i> Auto User Win Percentage</h3>
            <div class="grid grid-cols-4 gap-4">
                <?php foreach(['25', '50', '75', '100'] as $val): ?>
                <label class="cursor-pointer">
                    <input type="radio" name="hilo_win_chance" value="<?= $val ?>" class="peer sr-only" <?= $win_chance == $val ? 'checked' : ''; ?>>
                    <div class="py-4 rounded-xl bg-gray-700 border-2 border-transparent peer-checked:border-green-500 peer-checked:bg-green-500/20 text-center transition-all">
                        <div class="text-2xl font-black text-white"><?= $val ?>%</div>
                        <div class="text-[10px] text-gray-400 uppercase font-bold tracking-widest">Chance</div>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
            <p class="text-xs text-gray-400 mt-6 italic text-center"><i class="fa-solid fa-info-circle"></i> Low % means high profit for admin. 100% means user wins every guess.</p>
        </div>

        <div id="manualBox" class="md:col-span-3 bg-gray-800 rounded-2xl p-8 border border-purple-500/30 shadow-lg <?= $is_manual ? '' : 'hidden'; ?>">
            <h3 class="text-xl font-bold text-purple-400 mb-6 flex items-center gap-2"><i class="fa-solid fa-gavel"></i> Global Result Forcer</h3>
            <div class="grid grid-cols-3 gap-4">
                <label class="cursor-pointer">
                    <input type="radio" name="hilo_force_result" value="win" class="peer sr-only" <?= $force_res=='win'?'checked':''; ?>>
                    <div class="p-6 rounded-xl bg-gray-700 border-2 border-transparent peer-checked:border-green-500 peer-checked:bg-green-500/20 text-center transition-all">
                        <i class="fa-solid fa-trophy text-2xl text-green-400 mb-2"></i><div class="font-bold text-white uppercase">Force Win</div>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="hilo_force_result" value="loss" class="peer sr-only" <?= $force_res=='loss'?'checked':''; ?>>
                    <div class="p-6 rounded-xl bg-gray-700 border-2 border-transparent peer-checked:border-red-500 peer-checked:bg-red-500/20 text-center transition-all">
                        <i class="fa-solid fa-skull text-2xl text-red-400 mb-2"></i><div class="font-bold text-white uppercase">Force Loss</div>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="hilo_force_result" value="normal" class="peer sr-only" <?= $force_res=='normal'?'checked':''; ?>>
                    <div class="p-6 rounded-xl bg-gray-700 border-2 border-transparent peer-checked:border-gray-400 peer-checked:bg-gray-600 text-center transition-all">
                        <i class="fa-solid fa-shuffle text-2xl text-gray-400 mb-2"></i><div class="font-bold text-white uppercase">Normal</div>
                    </div>
                </label>
            </div>
        </div>

        <div class="md:col-span-3">
            <button type="submit" class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-bold py-5 rounded-2xl shadow-lg transition-all active:scale-95 text-lg tracking-widest">SAVE SYSTEM CONFIGURATION</button>
        </div>
    </form>
</div>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #1f2937; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 4px; }
</style>

<script>
function toggleUI() {
    const isMan = document.getElementById('modeSwitch').checked;
    document.getElementById('manualBox').classList.toggle('hidden', !isMan);
    document.getElementById('autoBox').classList.toggle('hidden', isMan);
}

function fetchLiveStats() {
    fetch('?api=live_stats')
        .then(r => r.json())
        .then(data => {
            if(data.error) return;
            document.getElementById('liveVol').innerText = data.volume.toFixed(2);
            document.getElementById('livePlayers').innerText = data.players;
            
            const list = document.getElementById('liveFeed');
            if(data.feed.length === 0) {
                list.innerHTML = '<div class="text-center text-gray-500 text-xs mt-4 italic">No activity recorded...</div>';
            } else {
                let html = '';
                data.feed.forEach(f => {
                    const isWin = f.status === 'win';
                    html += `
                        <div class="bg-gray-700/40 p-3 rounded-xl border border-gray-600 animate__animated animate__fadeIn">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="text-white font-bold text-sm">UID: ${f.user_id}</div>
                                    <div class="text-[9px] text-gray-500">${f.created_at.split(' ')[1]}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-white font-bold text-xs font-mono">₹${parseFloat(f.bet_amount).toFixed(2)}</div>
                                    ${isWin ? '<span class="text-green-400 font-bold text-[10px] uppercase">'+f.multiplier+'x Win</span>' : '<span class="text-red-400 font-bold text-[10px] uppercase">Busted</span>'}
                                </div>
                            </div>
                        </div>`;
                });
                list.innerHTML = html;
            }
        });
}
setInterval(fetchLiveStats, 2000);
fetchLiveStats();
</script>