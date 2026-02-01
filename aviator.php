<?php
// logic stays exactly same as your code
session_start();
error_reporting(0);
header("Cache-Control: no-cache, no-store, must-revalidate");

define('DB_HOST', 'localhost');
define('DB_USER', 'chikenof_chick');
define('DB_PASS', 'chikenof_chick');
define('DB_NAME', 'chikenof_chick');
define('WIN_MULTIPLIER', 1.84);

date_default_timezone_set('Asia/Kolkata');
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) { die("Connection failed"); }
$conn->query("SET time_zone = '+05:30'");

function getSettings($conn) {
    $sets = ['mode'=>'false', 'win_chance'=>40, 'next_res'=>'random'];
    $q = $conn->query("SELECT * FROM admin_settings WHERE setting_key LIKE 'trading_%'");
    while($r = $q->fetch_assoc()) {
        if($r['setting_key'] == 'trading_mode') $sets['mode'] = $r['setting_value'];
        if($r['setting_key'] == 'trading_win_chance') $sets['win_chance'] = intval($r['setting_value']);
        if($r['setting_key'] == 'trading_next_result') $sets['next_res'] = $r['setting_value'];
    }
    return $sets;
}

if (isset($_GET['action']) && $_GET['action'] == 'sync_data') {
    header('Content-Type: application/json');
    $user_id = $_SESSION['user_id'] ?? 0;
    $response = ['balance' => 0, 'active' => [], 'completed' => [], 'history' => [], 'admin_res' => 'random'];
    $u = $conn->query("SELECT balance FROM users WHERE id = $user_id")->fetch_assoc();
    $response['balance'] = $u ? $u['balance'] : 0;
    $q = $conn->query("SELECT * FROM trades WHERE user_id = $user_id ORDER BY id DESC LIMIT 15");
    $now = time();
    $settings = getSettings($conn);
    $is_manual = ($settings['mode'] === 'true');
    $response['admin_res'] = $is_manual ? $settings['next_res'] : 'auto'; 
    $response['win_chance'] = $settings['win_chance'];
    while ($trade = $q->fetch_assoc()) {
        $created = strtotime($trade['created_at']);
        $end_time = $created + $trade['duration'];
        $remaining = $end_time - $now;
        if ($trade['result'] == 'pending' && $remaining <= 0) {
            $is_win = false; $dir = strtolower($trade['direction']); $entry_price = floatval($trade['start_price']);
            if ($is_manual) {
                if ($settings['next_res'] == 'win') $is_win = true;
                else if ($settings['next_res'] == 'loss') $is_win = false;
                else $is_win = (mt_rand(1, 100) <= 50); 
            } else { $is_win = (mt_rand(1, 100) <= $settings['win_chance']); }
            $gap = mt_rand(200, 500) / 100; 
            if ($is_win) { $close_price = ($dir == 'up') ? $entry_price + $gap : $entry_price - $gap; }
            else { $close_price = ($dir == 'up') ? $entry_price - $gap : $entry_price + $gap; }
            $payout = $is_win ? ($trade['wager'] * WIN_MULTIPLIER) : 0;
            $result = $is_win ? 'win' : 'lose';
            $conn->query("UPDATE trades SET result = '$result', payout = $payout, end_price = '$close_price', updated_at = NOW() WHERE id = {$trade['id']}");
            if ($is_win) { $conn->query("UPDATE users SET balance = balance + $payout WHERE id = $user_id"); $response['balance'] += $payout; }
            $response['completed'][] = ['result' => $result, 'payout' => $payout, 'close_price' => $close_price];
            $trade['result'] = $result; $trade['payout'] = $payout;
        }
        if ($trade['result'] == 'pending') {
            $response['active'][] = ['id' => $trade['id'], 'dir' => $trade['direction'], 'wager' => $trade['wager'], 'entry' => (float)$trade['start_price'], 'time' => $remaining, 'elapsed' => $now - $created, 'duration' => $trade['duration']];
        }
        $response['history'][] = ['id' => $trade['id'], 'type' => $trade['direction'], 'wager' => $trade['wager'], 'payout' => $trade['payout'], 'result' => $trade['result'], 'time_str' => date('H:i', $created), 'remaining' => ($trade['result'] == 'pending') ? $remaining : 0];
    }
    echo json_encode($response); exit;
}

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$user_id = $_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trade'])) {
    $wager = floatval($_POST['amount']); $direction = $_POST['direction']; $duration = intval($_POST['duration']); $entry = floatval($_POST['current_price']); $current_time = date('Y-m-d H:i:s');
    $user = $conn->query("SELECT balance FROM users WHERE id = $user_id")->fetch_assoc();
    if ($user && $user['balance'] >= $wager) {
        $conn->query("UPDATE users SET balance = balance - $wager WHERE id = $user_id");
        $stmt = $conn->prepare("INSERT INTO trades (user_id, wager, direction, duration, start_price, result, created_at) VALUES (?, ?, ?, ?, ?, 'pending', ?)");
        $stmt->bind_param("idsids", $user_id, $wager, $direction, $duration, $entry, $current_time);
        $stmt->execute(); echo json_encode(['status' => 'success']);
    } else { echo json_encode(['status' => 'error', 'msg' => 'Low Balance']); }
    exit;
}
$user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Trading Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap');
        * { font-family: 'Roboto', sans-serif; -webkit-tap-highlight-color: transparent; box-sizing: border-box; touch-action: pan-x pan-y; }
        html, body { height: 100%; margin: 0; padding: 0; overflow: hidden; background-color: #1e222d; }
        body { display: flex; flex-direction: column; }
        .header-bar { flex: 0 0 60px; background: #2a2e39; padding: 0 12px; padding-top: env(safe-area-inset-top); display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #363a45; z-index: 50; }
        #chart-container { flex: 1; position: relative; min-height: 0; background: #1e222d; }
        .controls-panel { flex: 0 0 auto; background: #1e222d; padding: 12px 16px; border-top: 1px solid #363a45; padding-bottom: max(12px, env(safe-area-inset-bottom)); z-index: 50; }
        .bal-display { background: #f0b90b; color: #000; padding: 6px 12px; border-radius: 8px; font-weight: 700; font-size: 14px; }
        svg { width: 100%; height: 100%; display: block; }
        .chart-area { fill: url(#chartGradient); opacity: 0.2; }
        .chart-line { fill: none; stroke: #42a5f5; stroke-width: 2.5; stroke-linejoin: round; }
        .pulse-dot { fill: #42a5f5; filter: drop-shadow(0 0 6px #42a5f5); }
        #pills-area { position: absolute; bottom: 12px; left: 12px; z-index: 30; display: flex; flex-direction: column-reverse; gap: 8px; }
        .payout-pill { background: #2a2e39; border-radius: 8px; padding: 6px 10px; display: flex; align-items: center; gap: 10px; border: 1px solid #363a45; animation: slideIn 0.3s ease-out; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .pill-icon img { width: 20px; height: 20px; border-radius: 50%; border: 1px solid #f0b90b; }
        .pill-data { display: flex; align-items: center; gap: 6px; font-weight: bold; font-size: 13px; color: white; }
        .pill-profit { color: #00c853; } .pill-loss { color: #ff3d00; }
        .input-box { background: #2a2e39; border: 1px solid #363a45; border-radius: 8px; flex: 1; display: flex; flex-direction: column; justify-content: center; position: relative; height: 50px; }
        .input-val { background: transparent; color: white; font-weight: bold; font-size: 16px; width: 100%; outline: none; text-align: center; border: none; }
        .adj-btn { position: absolute; top: 0; bottom: 0; width: 36px; display: flex; align-items: center; justify-content: center; color: #787b86; cursor: pointer; font-size: 20px; }
        .adj-left { left: 0; border-right: 1px solid #363a45; } .adj-right { right: 0; border-left: 1px solid #363a45; }
        .btn-green { background: #00c853; color: white; border-radius: 8px; box-shadow: 0 4px 0 #009624; height: 44px; width: 100%; }
        .btn-red { background: #ff3d00; color: white; border-radius: 8px; box-shadow: 0 4px 0 #bf360c; height: 44px; width: 100%; }
        .result-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); z-index: 1000; display: none; align-items: center; justify-content: center; }
        .result-box { background: #2a2e39; padding: 30px; border-radius: 20px; text-align: center; width: 85%; max-width: 320px; border: 1px solid #363a45; transform: scale(0.5); transition: 0.3s; }
        .result-box.show { transform: scale(1); }
        .drawer { position: fixed; top: 0; left: -320px; width: 280px; height: 100%; background: #2a2e39; z-index: 200; transition: 0.3s; padding: 16px; display: flex; flex-direction: column; padding-top: max(16px, env(safe-area-inset-top)); box-shadow: 5px 0 20px rgba(0,0,0,0.5); }
        .drawer.open { left: 0; }
        .drawer-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 199; display: none; }
        .drawer-overlay.active { display: block; }
        .hist-item { background: #1e222d; padding: 10px; border-radius: 8px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; border-left: 4px solid #363a45; }
        .hist-item.win { border-left-color: #00c853; } .hist-item.lose { border-left-color: #ff3d00; }
        .live-price-tag { position: absolute; right: 0; transform: translateY(-50%); background: #42a5f5; color: white; font-size: 11px; font-weight: bold; padding: 4px 8px; border-radius: 4px 0 0 4px; z-index: 20; }
        .connector-line { stroke: #f0b90b; stroke-width: 1; stroke-dasharray: 4; opacity: 0.8; }
    </style>
</head>
<body>

    <div id="resultOverlay" class="result-overlay" onclick="closePopup()">
        <div id="resultBox" class="result-box">
            <div id="resIcon" class="text-5xl mb-3"></div>
            <div id="resTitle" class="text-white font-bold text-xl"></div>
            <div id="resAmount" class="text-3xl font-bold font-mono my-2"></div>
            <div class="text-xs text-gray-500 mt-2 uppercase tracking-widest">TAP OR WAIT TO CLOSE</div>
        </div>
    </div>

    <div id="drawerOverlay" class="drawer-overlay" onclick="toggleDrawer()"></div>

    <header class="header-bar">
        <div class="flex items-center gap-3">
            <button onclick="toggleDrawer()" class="text-gray-400 w-8 h-8 flex items-center justify-center"><i class="fas fa-bars text-lg"></i></button>
            <div class="bg-[#363a45] px-3 py-1.5 rounded-lg flex items-center gap-2">
                <img src="https://cryptologos.cc/logos/bitcoin-btc-logo.png" class="w-4 h-4">
                <span class="font-bold text-sm text-white">Crypto IDX</span>
                <span class="text-xs text-green-400 font-bold">84%</span>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div class="bal-display flex items-center gap-2"><i class="fas fa-wallet"></i><span id="displayBalance">₹<?= number_format($user['balance'], 2) ?></span></div>
            <button onclick="location.href='index.php'" class="bg-[#363a45] w-8 h-8 rounded-lg flex items-center justify-center"><i class="fas fa-home text-white text-sm"></i></button>
        </div>
    </header>

    <main id="chart-container">
        <div id="pills-area"></div>
        <svg id="chartSvg">
            <defs>
                <linearGradient id="chartGradient" x1="0" x2="0" y1="0" y2="1">
                    <stop offset="0%" stop-color="#42a5f5" stop-opacity="0.2"/><stop offset="100%" stop-color="#42a5f5" stop-opacity="0"/>
                </linearGradient>
            </defs>
            <path id="areaPath" class="chart-area" />
            <path id="linePath" class="chart-line" />
            <line id="curPriceLine" x1="0" x2="100%" stroke="#42a5f5" stroke-width="1" stroke-dasharray="4" opacity="0.5" />
            <circle id="pulseDot" r="4" class="pulse-dot" />
            <g id="tradeMarkers"></g>
        </svg>
        <div id="livePriceTag" class="live-price-tag">0.00</div>
    </main>

    <footer class="controls-panel">
        <div class="flex gap-3 mb-3">
            <div class="input-box">
                <div class="adj-btn adj-left" onclick="adjTime(-30)">-</div>
                <span class="text-[10px] text-gray-400 text-center absolute top-1 w-full uppercase font-bold">Time</span>
                <input type="text" id="timeDisplay" value="30s" readonly class="input-val">
                <div class="adj-btn adj-right" onclick="adjTime(30)">+</div>
                <input type="hidden" id="tradeDuration" value="30">
            </div>
            <div class="input-box">
                <div class="adj-btn adj-left" onclick="adjAmt(-100)">-</div>
                <span class="text-[10px] text-gray-400 text-center absolute top-1 w-full uppercase font-bold">Investment</span>
                <input type="number" id="tradeAmount" value="100" class="input-val">
                <div class="adj-btn adj-right" onclick="adjAmt(100)">+</div>
            </div>
        </div>
        <div class="text-center text-xs text-gray-400 font-bold mb-3 uppercase tracking-tighter">Earnings +84% <span id="payoutDisplay" class="text-green-400 ml-1">₹184.00</span></div>
        <div class="flex gap-4">
            <button onclick="placeTrade('up')" class="btn-green flex items-center justify-center text-xl"><i class="fas fa-arrow-up"></i></button>
            <button onclick="placeTrade('down')" class="btn-red flex items-center justify-center text-xl"><i class="fas fa-arrow-down"></i></button>
        </div>
    </footer>

    <div id="drawer" class="drawer">
        <div class="flex justify-between items-center mb-4"><h3 class="text-white font-bold text-lg"><i class="fas fa-history mr-2"></i> Trades</h3><button onclick="toggleDrawer()" class="text-gray-400"><i class="fas fa-times text-xl"></i></button></div>
        <div id="historyList" class="space-y-2 overflow-y-auto flex-1"></div>
    </div>

    <script>
        document.addEventListener('touchstart', function(event) { if (event.touches.length > 1) { event.preventDefault(); } }, { passive: false });
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function(event) { let now = (new Date()).getTime(); if (now - lastTouchEnd <= 300) { event.preventDefault(); } lastTouchEnd = now; }, false);

        const VISIBLE_POINTS = 50; 
        let price = 64186.00;
        let chartData = [];
        let activeTrades = [];
        let adminRes = 'random';
        let winChance = 40;
        let popupTimer = null; // Auto-close timer reference
        
        for(let i=0; i<100; i++) { price += (Math.random() - 0.5) * 15; chartData.push(price); }

        const pathLine = document.getElementById('linePath');
        const pathArea = document.getElementById('areaPath');
        const curLine = document.getElementById('curPriceLine');
        const pulseDot = document.getElementById('pulseDot');
        const priceTag = document.getElementById('livePriceTag');
        const markerGroup = document.getElementById('tradeMarkers');
        const pillsArea = document.getElementById('pills-area');

        function renderChart() {
            const width = document.getElementById('chart-container').clientWidth;
            const height = document.getElementById('chart-container').clientHeight;
            let bias = 0;
            activeTrades.forEach(t => {
                let currentElapsed = t.elapsed + (Date.now() - t.lastSyncTime) / 1000;
                if (currentElapsed >= 24 && currentElapsed <= 29.5) {
                    let entry = parseFloat(t.entry);
                    let targetWin = false;
                    if (adminRes === 'win') targetWin = true;
                    else if (adminRes === 'loss') targetWin = false;
                    else if (adminRes === 'auto') { let seed = t.id % 100; targetWin = (seed < winChance); }
                    else { targetWin = (t.id % 2 === 0); }
                    if (t.dir === 'up') {
                        if (targetWin && price <= entry) bias = 4.5;
                        if (!targetWin && price >= entry) bias = -4.5;
                    } else {
                        if (targetWin && price >= entry) bias = -4.5;
                        if (!targetWin && price <= entry) bias = 4.5;
                    }
                }
            });
            price += ((Math.random() - 0.5) * 6) + bias;
            chartData.push(price);
            if(chartData.length > 200) chartData.shift();
            const viewData = chartData.slice(chartData.length - VISIBLE_POINTS);
            const min = Math.min(...viewData);
            const max = Math.max(...viewData);
            const range = max - min || 1;
            const padding = height * 0.2;
            const points = viewData.map((val, i) => {
                const x = (i / (VISIBLE_POINTS - 1)) * width;
                const y = height - ((val - min) / range * (height - padding*2) + padding);
                return [x, y];
            });
            let d = `M ${points[0][0]} ${points[0][1]}`;
            for (let i = 1; i < points.length; i++) d += ` L ${points[i][0]} ${points[i][1]}`;
            pathLine.setAttribute('d', d);
            pathArea.setAttribute('d', `${d} L ${width} ${height} L 0 ${height} Z`);
            const lastY = points[points.length-1][1];
            curLine.setAttribute('y1', lastY); curLine.setAttribute('y2', lastY);
            pulseDot.setAttribute('cx', points[points.length-1][0]); pulseDot.setAttribute('cy', lastY);
            priceTag.innerText = price.toFixed(2); priceTag.style.top = `${lastY}px`;
            markerGroup.innerHTML = '';
            activeTrades.forEach(t => {
                let y = height - ((t.entry - min) / range * (height - padding*2) + padding);
                let stepX = width / (VISIBLE_POINTS - 1);
                let entryX = width - (t.elapsed * stepX);
                if(entryX > -50 && entryX < width + 50) {
                    const g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
                    const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                    line.setAttribute('x1', entryX); line.setAttribute('y1', y); line.setAttribute('x2', width); line.setAttribute('y2', y);
                    line.setAttribute('class', 'connector-line'); g.appendChild(line);
                    const tag = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                    tag.setAttribute('d', `M ${entryX} ${y} L ${entryX-10} ${y-12} L ${entryX-60} ${y-12} L ${entryX-60} ${y+12} L ${entryX-10} ${y+12} Z`);
                    tag.style.fill = '#f0b90b'; g.appendChild(tag);
                    const txt = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                    txt.setAttribute('x', entryX-35); txt.setAttribute('y', y+4); txt.setAttribute('text-anchor', 'middle'); txt.style.fill = 'black'; txt.style.fontSize = '11px'; txt.style.fontWeight = 'bold';
                    txt.textContent = '₹' + t.wager; g.appendChild(txt); markerGroup.appendChild(g);
                }
            });
            pillsArea.innerHTML = ''; 
            activeTrades.forEach(t => {
                let isWin = (t.dir === 'up' ? price > t.entry : price < t.entry);
                let profitText = isWin ? '₹' + (t.wager * 1.84).toFixed(2) : '₹0.00';
                let profitClass = isWin ? 'text-green-400' : 'text-red-400';
                let dirIcon = t.dir === 'up' ? 'fa-arrow-up' : 'fa-arrow-down';
                let dirBg = t.dir === 'up' ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400';
                let timeRem = Math.max(0, Math.ceil(t.time));
                pillsArea.innerHTML += `<div class="payout-pill"><div class="pill-icon"><img src="https://cryptologos.cc/logos/bitcoin-btc-logo.png"></div><div class="pill-data"><span>₹${parseFloat(t.wager).toFixed(2)}</span><i class="fas fa-arrow-right text-gray-500 text-[10px]"></i><span class="${profitClass}">${profitText}</span></div><div class="bg-[#363a45] px-2 rounded text-[11px] font-mono font-bold">${timeRem}s</div><div class="w-5 h-5 rounded flex items-center justify-center ${dirBg}"><i class="fas ${dirIcon} text-[10px] transform -rotate-45"></i></div></div>`;
            });
        }

        function adjTime(val) { let el = document.getElementById('tradeDuration'); let n = parseInt(el.value) + val; if(n < 30) n = 30; if(n > 60) n = 60; el.value = n; document.getElementById('timeDisplay').value = `${n}s`; }
        function adjAmt(val) { let el = document.getElementById('tradeAmount'); let n = parseInt(el.value) + val; if(n >= 100) el.value = n; document.getElementById('payoutDisplay').innerText = `₹${(el.value * 1.84).toFixed(2)}`; }
        function placeTrade(dir) {
            const amt = document.getElementById('tradeAmount').value; const dur = document.getElementById('tradeDuration').value;
            const fd = new FormData(); fd.append('trade', 1); fd.append('amount', amt); fd.append('direction', dir); fd.append('duration', dur); fd.append('current_price', price);
            fetch('', { method: 'POST', body: fd }).then(r=>r.json()).then(d=>{ if(d.status === 'success') { syncData(); } else { alert(d.msg); } });
        }

        function syncData() {
            fetch('?action=sync_data').then(r=>r.json()).then(d=>{
                document.getElementById('displayBalance').innerText = '₹' + parseFloat(d.balance).toFixed(2);
                adminRes = d.admin_res; winChance = d.win_chance;
                activeTrades = d.active.map(t => ({...t, lastSyncTime: Date.now()}));
                let hHtml = ''; d.history.forEach(h => {
                    const isP = h.result === 'pending'; const win = h.result === 'win';
                    const aTxt = isP ? '...' : (win ? '+₹'+h.payout : '₹0');
                    const aCol = isP ? 'text-yellow-400' : (win ? 'text-green-400' : 'text-gray-400');
                    hHtml += `<div class="hist-item ${isP?'pending':(win?'win':'lose')}"><div><div class="font-bold text-white text-xs">Crypto IDX</div><div class="text-[10px] text-gray-400">${h.type.toUpperCase()} | ₹${h.wager}</div></div><div class="text-right"><div class="font-bold ${aCol} text-sm">${aTxt}</div><div class="text-[10px] text-gray-500">${isP ? '⏳ '+h.remaining+'s' : h.time_str}</div></div></div>`;
                });
                document.getElementById('historyList').innerHTML = hHtml;
                d.completed.forEach(c => { if(c.close_price) { price = parseFloat(c.close_price); chartData[chartData.length-1] = price; } showPopup(c.result === 'win', c.payout); });
            });
        }

        function showPopup(win, amt) { 
            const ov = document.getElementById('resultOverlay'); 
            const box = document.getElementById('resultBox');
            
            if(popupTimer) clearTimeout(popupTimer); // Clear existing timer

            ov.style.display = 'flex'; 
            setTimeout(() => box.classList.add('show'), 10); 
            
            document.getElementById('resIcon').innerHTML = win ? '🏆' : '📉'; 
            document.getElementById('resTitle').innerHTML = win ? 'Awesome Win!' : 'Trade Lost'; 
            document.getElementById('resTitle').style.color = win ? '#00c853' : '#ff3d00'; 
            document.getElementById('resAmount').innerHTML = (win ? '+₹' : '₹') + parseFloat(amt).toFixed(2); 
            document.getElementById('resAmount').style.color = win ? '#00c853' : '#ff3d00'; 

            // AUTO CLOSE AFTER 3 SECONDS
            popupTimer = setTimeout(closePopup, 3000);
        }
        
        function closePopup() { 
            document.getElementById('resultBox').classList.remove('show'); 
            setTimeout(() => document.getElementById('resultOverlay').style.display = 'none', 300); 
        }
        
        function toggleDrawer() { document.getElementById('drawer').classList.toggle('open'); document.getElementById('drawerOverlay').classList.toggle('active'); }

        window.addEventListener('resize', renderChart);
        setInterval(renderChart, 1000);
        setInterval(syncData, 1000);
        syncData();
    </script>
</body>
</html>