<?php
// 1. DATABASE CONNECTION & API (Header se pehle)
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
    if(!isset($pdo)) { echo json_encode(['error' => 'DB Connection Failed']); exit; }

    try {
        // 1. Total Volume Today (Only real users, excluding bot UID 0)
        $q1 = $pdo->query("SELECT SUM(bet_amount) as total_vol, COUNT(DISTINCT user_id) as active_players FROM coin_history WHERE DATE(created_at) = CURDATE() AND user_id != 0");
        $stats = $q1->fetch(PDO::FETCH_ASSOC);
        
        // 2. Recent Bets Feed (Last 3)
        $q2 = $pdo->query("SELECT user_id, bet_amount, choice, result, win_amount, created_at FROM coin_history WHERE user_id != 0 ORDER BY id DESC LIMIT 3");
        $history = $q2->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'volume' => floatval($stats['total_vol'] ?? 0),
            'players' => intval($stats['active_players'] ?? 0),
            'feed' => $history
        ]);
    } catch(Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
    exit;
}

require_once 'includes/header.php';
$success_message = '';

// Handle Settings Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $coin_mode = isset($_POST['coin_mode']) ? 'true' : 'false';
    $coin_win_chance = intval($_POST['coin_win_chance'] ?? 50);
    $coin_force_result = $_POST['coin_force_result'] ?? 'normal';

    $settings = [
        'coin_mode' => $coin_mode,
        'coin_win_chance' => $coin_win_chance,
        'coin_force_result' => $coin_force_result
    ];

    foreach ($settings as $key => $val) {
        $stmt = $pdo->prepare("INSERT INTO admin_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $val, $val]);
    }
    $success_message = "✅ Coin Flip logic updated successfully!";
}

// Fetch Current Settings
$s = [];
$stmt = $pdo->query("SELECT * FROM admin_settings WHERE setting_key LIKE 'coin_%'");
while($r = $stmt->fetch()){ $s[$r['setting_key']] = $r['setting_value']; }

$is_manual = ($s['coin_mode'] ?? 'false') === 'true';
$win_chance = intval($s['coin_win_chance'] ?? 50);
$force_res = $s['coin_force_result'] ?? 'normal';
?>

<div class="max-w-6xl mx-auto mt-6 px-4 pb-10">
    
    <div class="mb-6 flex flex-col md:flex-row justify-between items-center gap-4">
        <div>
            <h2 class="text-3xl font-bold text-white flex items-center gap-3">
                <i class="fa-solid fa-coins text-yellow-500"></i> Coin Flip Controller
            </h2>
            <p class="text-gray-400 text-sm mt-1">Live Monitor & Flip Result Forcer</p>
        </div>
        <div class="bg-gray-800 border border-gray-700 px-4 py-2 rounded-lg">
            <span class="text-white font-bold text-sm" id="modeLabel"><?php echo $is_manual ? 'Manual God Mode' : 'Auto RTP Mode'; ?></span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <div class="bg-gray-800 rounded-2xl p-6 border border-gray-700 shadow-lg relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-10"><i class="fa-solid fa-sack-dollar text-6xl text-yellow-500"></i></div>
            <h3 class="text-gray-400 text-xs font-bold uppercase mb-1">Live Volume</h3>
            <div class="text-3xl font-mono font-bold text-white flex items-center gap-2">₹<span id="liveVol">0.00</span></div>
        </div>
        <div class="bg-gray-800 rounded-2xl p-6 border border-gray-700 shadow-lg relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-10"><i class="fa-solid fa-users text-6xl text-blue-500"></i></div>
            <h3 class="text-gray-400 text-xs font-bold uppercase mb-1">Real Players</h3>
            <div class="text-3xl font-mono font-bold text-white" id="livePlayers">0</div>
        </div>
        <div class="bg-gray-800 rounded-2xl border border-gray-700 shadow-lg flex flex-col h-48 overflow-hidden">
            <div class="bg-gray-900/50 px-4 py-2 border-b border-gray-700 text-xs font-bold text-gray-300">LIVE FEED (LAST 3)</div>
            <div id="liveFeed" class="flex-1 overflow-y-auto p-2 space-y-2 custom-scrollbar"></div>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div id="msgBox" class="bg-green-500/10 border border-green-500/50 text-green-400 px-6 py-4 rounded-xl mb-6 flex items-center gap-3">
            <i class="fa-solid fa-circle-check"></i> <?= $success_message; ?>
        </div>
        <script>setTimeout(() => { document.getElementById('msgBox').style.display = 'none'; }, 2500);</script>
    <?php endif; ?>

    <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-3 bg-gray-800 rounded-2xl p-6 border border-gray-700 shadow-xl flex justify-between items-center">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-yellow-500/20 flex items-center justify-center text-yellow-500"><i class="fa-solid fa-sliders"></i></div>
                <h3 class="text-white font-bold text-lg">System Strategy</h3>
            </div>
            <div class="flex items-center gap-3 bg-gray-900 p-2 rounded-xl border border-gray-600">
                <span class="text-xs font-bold text-green-400">AUTO</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="coin_mode" id="modeSwitch" class="sr-only peer" onchange="toggleInputs()" <?= $is_manual ? 'checked' : ''; ?>>
                    <div class="w-14 h-7 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
                <span class="text-xs font-bold text-blue-400">MANUAL</span>
            </div>
        </div>

        <div id="autoBox" class="md:col-span-3 bg-gray-800 rounded-2xl p-8 border border-green-500/30 shadow-lg <?= $is_manual ? 'hidden' : ''; ?>">
            <h3 class="text-xl font-bold text-green-400 mb-6 flex items-center gap-2"><i class="fa-solid fa-dice"></i> User Win Probability</h3>
            <div class="grid grid-cols-4 gap-4">
                <?php foreach([25, 50, 75, 100] as $pc): ?>
                <label class="cursor-pointer">
                    <input type="radio" name="coin_win_chance" value="<?= $pc ?>" class="peer sr-only" <?= $win_chance == $pc ? 'checked' : ''; ?>>
                    <div class="py-4 rounded-xl bg-gray-700 border-2 border-transparent peer-checked:border-green-500 peer-checked:bg-green-500/20 text-center transition-all">
                        <div class="text-2xl font-black text-white"><?= $pc ?>%</div>
                        <div class="text-[10px] text-gray-400 uppercase font-bold tracking-widest">RTP</div>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="manualBox" class="md:col-span-3 bg-gray-800 rounded-2xl p-8 border border-yellow-500/30 shadow-lg <?= $is_manual ? '' : 'hidden'; ?>">
            <h3 class="text-xl font-bold text-yellow-500 mb-6 flex items-center gap-2"><i class="fa-solid fa-bolt"></i> Global Force Flip</h3>
            <div class="grid grid-cols-3 gap-4">
                <label class="cursor-pointer">
                    <input type="radio" name="coin_force_result" value="win" class="peer sr-only" <?= $force_res=='win'?'checked':''; ?>>
                    <div class="p-6 rounded-xl bg-gray-700 border-2 border-transparent peer-checked:border-green-500 peer-checked:bg-green-500/20 text-center transition-all">
                        <i class="fa-solid fa-trophy text-2xl text-green-400 mb-2"></i><div class="font-bold text-white uppercase">FORCE WIN</div>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="coin_force_result" value="loss" class="peer sr-only" <?= $force_res=='loss'?'checked':''; ?>>
                    <div class="p-6 rounded-xl bg-gray-700 border-2 border-transparent peer-checked:border-red-500 peer-checked:bg-red-500/20 text-center transition-all">
                        <i class="fa-solid fa-skull text-2xl text-red-400 mb-2"></i><div class="font-bold text-white uppercase">FORCE LOSS</div>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="coin_force_result" value="normal" class="peer sr-only" <?= $force_res=='normal'?'checked':''; ?>>
                    <div class="p-6 rounded-xl bg-gray-700 border-2 border-transparent peer-checked:border-gray-400 peer-checked:bg-gray-600 text-center transition-all">
                        <i class="fa-solid fa-shuffle text-2xl text-gray-400 mb-2"></i><div class="font-bold text-white uppercase">NORMAL</div>
                    </div>
                </label>
            </div>
        </div>

        <button type="submit" class="md:col-span-3 bg-gradient-to-r from-yellow-600 to-yellow-500 text-black font-black py-5 rounded-2xl text-lg uppercase tracking-widest shadow-xl active:scale-95 transition-all">
            UPDATE COIN FLIP LOGIC
        </button>
    </form>
</div>

<script>
function toggleInputs() {
    const isManual = document.getElementById('modeSwitch').checked;
    document.getElementById('manualBox').classList.toggle('hidden', !isManual);
    document.getElementById('autoBox').classList.toggle('hidden', isManual);
    document.getElementById('modeLabel').innerText = isManual ? "Manual God Mode" : "Auto RTP Mode";
}

function fetchStats() {
    fetch('?api=live_stats').then(r => r.json()).then(data => {
        if(data.error) return;
        document.getElementById('liveVol').innerText = parseFloat(data.volume).toFixed(2);
        document.getElementById('livePlayers').innerText = data.players;
        
        const list = document.getElementById('liveFeed');
        if(data.feed.length === 0) {
            list.innerHTML = '<div class="text-center text-gray-600 text-xs mt-4 italic">Waiting for flips...</div>';
        } else {
            let html = '';
            data.feed.forEach(f => {
                const isWin = parseFloat(f.win_amount) > 0;
                html += `<div class="bg-gray-700/40 p-3 rounded-xl border border-gray-600 flex justify-between items-center animate__animated animate__fadeIn">
                            <div>
                                <div class="text-white font-bold text-sm">UID: ${f.user_id}</div>
                                <div class="text-[10px] text-gray-500 uppercase">${f.choice} | ${f.created_at.split(' ')[1]}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-white font-bold font-mono">₹${parseFloat(f.bet_amount).toFixed(2)}</div>
                                <span class="${isWin?'text-green-400':'text-red-400'} font-bold text-[10px] uppercase">${isWin?'WIN':'LOSS'}</span>
                            </div>
                        </div>`;
            });
            list.innerHTML = html;
        }
    });
}
setInterval(fetchStats, 2000);
fetchStats();
</script>