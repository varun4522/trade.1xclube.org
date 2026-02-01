<?php
// 1. DATABASE CONNECTION & API (Header se pehle)
$config_path = file_exists('../config.php') ? '../config.php' : '../../config.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    // Manual Fallback
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
        // 1. Total Volume Today (Aaj ka dhanda)
        $q1 = $pdo->query("SELECT SUM(bet_amount) as total_vol, COUNT(DISTINCT user_id) as active_players FROM chicken_history WHERE DATE(created_at) = CURDATE()");
        $stats = $q1->fetch(PDO::FETCH_ASSOC);
        
        // 2. Recent Bets Feed (Last 20)
        $q2 = $pdo->query("SELECT user_id, bet_amount, multiplier, win_amount, status, created_at FROM chicken_history ORDER BY id DESC LIMIT 3");
        $history = $q2->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'volume' => floatval($stats['total_vol'] ?? 0),
            'players' => intval($stats['active_players'] ?? 0),
            'feed' => $history
        ]);
    } catch(Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}
// ---------------------

require_once 'includes/header.php';

$success_message = '';
$error_message = '';

// Handle Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $cr_status = isset($_POST['cr_status']) ? 'true' : 'false';
        $cr_target = floatval($_POST['cr_target'] ?? 1.03);
        $cr_win_chance = intval($_POST['cr_win_chance'] ?? 50);

        if(isset($pdo)) {
            $settings_to_save = [
                'cr_status' => $cr_status,
                'cr_target' => $cr_target,
                'cr_win_chance' => $cr_win_chance
            ];

            foreach ($settings_to_save as $key => $val) {
                $stmt = $pdo->prepare("INSERT INTO admin_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$key, $val, $val]);
            }
            $success_message = "✅ Game Settings Updated Successfully!";
        }
    } catch (Exception $e) {
        $error_message = "❌ Error: " . $e->getMessage();
    }
}

// Fetch Settings
$s = [];
if(isset($pdo)) {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM admin_settings WHERE setting_key LIKE 'cr_%'");
    while ($row = $stmt->fetch()) {
        $s[$row['setting_key']] = $row['setting_value'];
    }
}

// Defaults
$is_manual = ($s['cr_status'] ?? 'false') === 'true';
$target_mult = floatval($s['cr_target'] ?? 1.03);
$win_chance = intval($s['cr_win_chance'] ?? 50);

// Available Multipliers for Chips
$MULTS = ['1.03', '1.07', '1.12', '1.17', '1.23', '1.29', '1.36', '1.44', '1.53', '1.63', '1.75', '1.88', '2.04', '2.22', '2.45', '2.72', '3.06', '3.50', '4.00', '4.60', '5.40', '6.50', '8.00', '10.00', '13.00', '17.00', '22.00', '30.00', '40.00', '50.00'];
?>

<div class="max-w-6xl mx-auto mt-6 px-4">
    
    <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h2 class="text-3xl font-bold text-white flex items-center gap-3">
                <i class="fa-solid fa-road text-blue-500"></i> Trade Club Control
            </h2>
            <p class="text-gray-400 text-sm mt-1">Live Feed & Game Logic Controller</p>
        </div>
        <div class="bg-gray-800 border border-gray-700 px-4 py-2 rounded-lg w-fit">
            <span class="text-gray-400 text-xs font-bold uppercase tracking-wider">Current Mode</span>
            <div class="flex items-center gap-2 mt-1">
                <div class="w-2 h-2 rounded-full <?php echo $is_manual ? 'bg-green-500' : 'bg-blue-500'; ?> animate-pulse"></div>
                <span class="text-white font-bold text-sm" id="modeLabelDisplay"><?php echo $is_manual ? 'Manual Control' : 'Auto (RTP)'; ?></span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        
        <div class="bg-gray-800 rounded-2xl p-6 border border-gray-700 shadow-lg relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-10"><i class="fa-solid fa-coins text-6xl text-yellow-500"></i></div>
            <h3 class="text-gray-400 text-xs font-bold uppercase tracking-wider mb-1">Today's Volume</h3>
            <div class="text-3xl font-mono font-bold text-white flex items-center gap-2">
                <span class="text-yellow-500">₹</span>
                <span id="liveVol">0.00</span>
            </div>
            <div class="mt-4 flex items-center gap-2 text-xs text-gray-500">
                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div> Total Bets Today
            </div>
        </div>

        <div class="bg-gray-800 rounded-2xl p-6 border border-gray-700 shadow-lg relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-10"><i class="fa-solid fa-users text-6xl text-blue-500"></i></div>
            <h3 class="text-gray-400 text-xs font-bold uppercase tracking-wider mb-1">Active Players</h3>
            <div class="text-3xl font-mono font-bold text-white">
                <span id="livePlayers">0</span>
            </div>
            <p class="mt-4 text-xs text-gray-500">Unique users played today</p>
        </div>

        <div class="bg-gray-800 rounded-2xl p-0 border border-gray-700 shadow-lg flex flex-col h-64 lg:h-auto overflow-hidden">
            <div class="bg-gray-900/50 px-4 py-2 border-b border-gray-700 flex justify-between items-center">
                <span class="text-xs font-bold text-gray-300">RECENT ACTION</span>
                <span class="text-[10px] bg-red-500/20 text-red-400 px-2 py-0.5 rounded animate-pulse">LIVE</span>
            </div>
            <div id="liveFeed" class="flex-1 overflow-y-auto p-2 space-y-2 custom-scrollbar" style="min-height: 150px;">
                <div class="text-center text-gray-500 text-xs mt-4">Waiting for bets...</div>
            </div>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div id="msgBox" class="bg-green-500/10 border border-green-500/50 text-green-400 px-6 py-4 rounded-xl mb-6 flex items-center gap-3 animate__animated animate__fadeInDown">
            <i class="fa-solid fa-circle-check text-xl"></i>
            <?= htmlspecialchars($success_message); ?>
        </div>
        <script>setTimeout(() => { document.getElementById('msgBox').style.display = 'none'; }, 3000);</script>
    <?php endif; ?>

    <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        <div class="md:col-span-3 bg-gray-800 rounded-2xl p-6 border border-gray-700 shadow-xl">
            <div class="flex flex-col md:flex-row justify-between items-center gap-6">
                <div class="flex gap-4 items-center">
                    <div class="w-14 h-14 rounded-full bg-blue-600/20 flex items-center justify-center text-blue-500 shrink-0">
                        <i class="fa-solid fa-gamepad text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-white">Game Logic Mode</h3>
                        <p class="text-sm text-gray-400">Choose how the game outcome is determined.</p>
                    </div>
                </div>
                
                <div class="flex items-center gap-3 bg-gray-900 p-2 rounded-xl border border-gray-600">
                    <span class="text-xs font-bold text-gray-400 pl-2">AUTO (RTP)</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="cr_status" id="modeSwitch" class="sr-only peer" 
                               onchange="toggleInputs()" <?= $is_manual ? 'checked' : ''; ?>>
                        <div class="w-14 h-7 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                    <span class="text-xs font-bold text-blue-400 pr-2">MANUAL (FIX)</span>
                </div>
            </div>
        </div>

        <div id="manualBox" class="md:col-span-3 bg-gray-800 rounded-2xl p-6 border border-blue-500/30 shadow-lg transition-all <?= $is_manual ? '' : 'hidden'; ?>">
            <div class="flex items-center justify-between mb-6 border-b border-gray-700 pb-4">
                <h3 class="text-xl font-bold text-blue-400 flex items-center gap-2">
                    <i class="fa-solid fa-bullseye"></i> Select Crash Point
                </h3>
                <div class="text-right">
                    <span class="text-xs text-gray-400 block">Selected Target</span>
                    <span class="text-2xl font-mono font-bold text-white" id="selectedMultDisplay"><?= number_format($target_mult, 2); ?>x</span>
                </div>
            </div>
            
            <input type="hidden" name="cr_target" id="cr_target_input" value="<?= $target_mult; ?>">

            <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-10 gap-3 max-h-96 overflow-y-auto pr-2 custom-scrollbar">
                <?php foreach ($MULTS as $m): ?>
                    <button type="button" 
                            class="chip-btn px-2 py-3 rounded-lg text-sm font-bold border transition-all duration-200 
                            <?= (floatval($m) == $target_mult) ? 'bg-blue-600 text-white border-blue-400 ring-2 ring-blue-400/50' : 'bg-gray-700 text-gray-300 border-gray-600 hover:bg-gray-600 hover:border-gray-500'; ?>"
                            onclick="selectMult(this, '<?= $m; ?>')">
                        <?= $m; ?>x
                    </button>
                <?php endforeach; ?>
            </div>
            
            <p class="text-xs text-gray-400 mt-4 bg-blue-900/20 p-3 rounded border border-blue-500/20">
                <i class="fa-solid fa-circle-info mr-1"></i>
                The game will run smoothly until <b><span id="hintMult"><?= $target_mult; ?></span>x</b>. Then <b>CRASH</b>.
            </p>
        </div>

        <div id="autoBox" class="md:col-span-3 bg-gray-800 rounded-2xl p-8 border border-green-500/30 shadow-lg transition-all <?= $is_manual ? 'hidden' : ''; ?>">
            <h3 class="text-xl font-bold text-green-400 mb-6 flex items-center gap-2">
                <i class="fa-solid fa-percent"></i> Auto Win Percentage
            </h3>

            <div class="mb-6">
                <div class="flex justify-between text-sm font-bold text-gray-300 mb-4">
                    <span>House Edge (Admin Profit)</span>
                    <span id="winValDisplay" class="text-green-400 bg-green-900/30 px-3 py-1 rounded border border-green-500/30"><?= $win_chance; ?>% User Win Rate</span>
                </div>
                
                <input type="range" name="cr_win_chance" id="winRange" min="0" max="100" value="<?= $win_chance; ?>"
                       class="w-full h-4 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-green-500 hover:accent-green-400 transition-all"
                       oninput="document.getElementById('winValDisplay').innerText = this.value + '% User Win Rate'">
                
                <div class="flex justify-between text-xs text-gray-500 mt-2 font-mono">
                    <span>0%</span><span>25%</span><span>50%</span><span>75%</span><span>100%</span>
                </div>
            </div>

            <div class="mt-8 bg-gray-900/50 rounded-xl p-5 border border-gray-700">
                <h4 class="text-gray-300 font-bold mb-4 text-xs uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-lightbulb text-yellow-500"></i> Admin Strategy Guide
                </h4>
                <div class="space-y-4 text-xs text-gray-400">
                    <div class="flex gap-4 items-start">
                        <span class="text-red-400 font-bold whitespace-nowrap bg-red-900/20 px-2 py-1 rounded border border-red-500/20">10% - 30%</span>
                        <p><strong class="text-white block mb-1">High Profit</strong> User crashes early (1-2 steps).</p>
                    </div>
                    <div class="flex gap-4 items-start">
                        <span class="text-yellow-400 font-bold whitespace-nowrap bg-yellow-900/20 px-2 py-1 rounded border border-yellow-500/20">40% - 60%</span>
                        <p><strong class="text-white block mb-1">Balanced</strong> Fair game. Best for retention.</p>
                    </div>
                    <div class="flex gap-4 items-start">
                        <span class="text-green-400 font-bold whitespace-nowrap bg-green-900/20 px-2 py-1 rounded border border-green-500/20">80% - 90%</span>
                        <p><strong class="text-white block mb-1">User Profit</strong> Users win big. Good for marketing.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="md:col-span-3 pb-8">
            <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold py-4 px-6 rounded-xl shadow-lg shadow-blue-900/20 transition-all transform hover:scale-[1.005] active:scale-[0.98] flex items-center justify-center gap-2 text-lg">
                <i class="fa-solid fa-floppy-disk"></i> SAVE SETTINGS
            </button>
        </div>

    </form>
</div>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #1f2937; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 4px; }
</style>

<script>
    function toggleInputs() {
        const isManual = document.getElementById('modeSwitch').checked;
        const manualBox = document.getElementById('manualBox');
        const autoBox = document.getElementById('autoBox');
        const label = document.getElementById('modeLabelDisplay');

        if (isManual) {
            manualBox.classList.remove('hidden');
            autoBox.classList.add('hidden');
            label.innerText = "Manual Control";
        } else {
            manualBox.classList.add('hidden');
            autoBox.classList.remove('hidden');
            label.innerText = "Auto (RTP)";
        }
    }

    function selectMult(btn, val) {
        document.querySelectorAll('.chip-btn').forEach(b => {
            b.className = 'chip-btn px-2 py-3 rounded-lg text-sm font-bold border transition-all duration-200 bg-gray-700 text-gray-300 border-gray-600 hover:bg-gray-600 hover:border-gray-500';
        });
        btn.className = 'chip-btn px-2 py-3 rounded-lg text-sm font-bold border transition-all duration-200 bg-blue-600 text-white border-blue-400 ring-2 ring-blue-400/50 transform scale-105';
        document.getElementById('cr_target_input').value = val;
        document.getElementById('selectedMultDisplay').innerText = val + 'x';
        document.getElementById('hintMult').innerText = val;
    }

    // --- LIVE FEED SCRIPT ---
    function fetchLiveStats() {
        fetch('?api=live_stats')
            .then(r => r.json())
            .then(data => {
                if(data.error) return;

                // Update Stats
                document.getElementById('liveVol').innerText = parseFloat(data.volume).toFixed(2);
                document.getElementById('livePlayers').innerText = data.players;

                // Update List
                const list = document.getElementById('liveFeed');
                if(data.feed.length === 0) {
                    list.innerHTML = '<div class="text-center text-gray-500 text-xs mt-4">No recent bets...</div>';
                } else {
                    let html = '';
                    data.feed.forEach(f => {
                        const isWin = f.status === 'win';
                        const badge = isWin 
                            ? `<span class="bg-green-900/30 text-green-400 px-2 py-1 rounded border border-green-500/30 text-[10px] font-bold"><i class="fa-solid fa-trophy mr-1"></i> ${f.multiplier}</span>` 
                            : `<span class="bg-red-900/30 text-red-400 px-2 py-1 rounded border border-red-500/30 text-[10px] font-bold"><i class="fa-solid fa-bomb mr-1"></i> CRASH</span>`;
                        
                        const amt = isWin ? f.win_amount : f.bet_amount;
                        const amtColor = isWin ? 'text-green-400' : 'text-gray-400';

                        html += `
                            <div class="bg-gray-700/40 p-2 rounded flex justify-between items-center text-xs border border-gray-600 animate__animated animate__fadeIn">
                                <div class="flex flex-col">
                                    <span class="text-gray-300 font-bold">UID: ${f.user_id}</span>
                                    <span class="text-[10px] text-gray-500">${f.created_at.split(' ')[1]}</span>
                                </div>
                                <div class="text-right">
                                    <div class="${amtColor} font-mono font-bold mb-1">₹${parseFloat(amt).toFixed(2)}</div>
                                    ${badge}
                                </div>
                            </div>
                        `;
                    });
                    list.innerHTML = html;
                }
            })
            .catch(e => console.log(e));
    }

    // Run every 2 seconds
    setInterval(fetchLiveStats, 2000);
    fetchLiveStats();
</script>

</div>
</body>
</html>