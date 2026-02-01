<?php
/* --- PRO MINES (Mobile Responsive + History Drawer) --- */
session_start();

// 1. DATABASE CONFIG
define('DB_HOST', 'localhost');
define('DB_USER', 'chikenof_chick');
define('DB_PASS', 'chikenof_chick');
define('DB_NAME', 'chikenof_chick');

error_reporting(0);
ini_set('display_errors', 0);

$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->connect_error) { die(json_encode(['status' => 'error', 'message' => 'DB Connect Error'])); }
$db->query("SET time_zone = '+05:30'");

// Ensure User
if (!isset($_SESSION['user_id'])) { header("Location: login.html"); exit; }
$user_id = $_SESSION['user_id'];

// Get User Data
$uq = $db->prepare("SELECT balance FROM users WHERE id = ?");
$uq->bind_param("i", $user_id);
$uq->execute();
$user = $uq->get_result()->fetch_assoc();

// =============================================================
// 2. API HANDLING
// =============================================================
if (isset($_POST['act'])) {
    header('Content-Type: application/json');
    $act = $_POST['act'];

    // --- START GAME ---
    if ($act === 'start') {
        $bet = floatval($_POST['bet']);
        $mines = intval($_POST['mines']);
        
        if ($bet < 10) { echo json_encode(['status'=>'error', 'msg'=>'Min bet ₹10']); exit; }
        if ($bet > $user['balance']) { echo json_encode(['status'=>'error', 'msg'=>'Low Balance']); exit; }
        
        // Deduct Balance
        $db->query("UPDATE users SET balance = balance - $bet WHERE id = $user_id");
        
        // Generate Mines
        $grid = array_fill(0, 25, 'gem');
        $m_pos = [];
        while(count($m_pos) < $mines) {
            $r = rand(0, 24);
            if(!in_array($r, $m_pos)) { $m_pos[] = $r; $grid[$r] = 'mine'; }
        }
        
        $_SESSION['mines_game'] = [
            'active' => true,
            'bet' => $bet,
            'mines_count' => $mines,
            'grid' => $grid,
            'revealed' => []
        ];
        
        // Return updated balance
        $newBal = $user['balance'] - $bet;
        echo json_encode(['status'=>'success', 'bal'=>number_format($newBal, 2, '.', '')]);
        exit;
    }

    // --- CLICK TILE ---
    if ($act === 'click') {
        if (!isset($_SESSION['mines_game']) || !$_SESSION['mines_game']['active']) exit;
        
        $idx = intval($_POST['idx']);
        $g = &$_SESSION['mines_game'];
        
        if (in_array($idx, $g['revealed'])) exit; // Already clicked

        if ($g['grid'][$idx] === 'mine') {
            // BOOM!
            $g['active'] = false;
            
            // Save Loss
            $stmt = $db->prepare("INSERT INTO mines_history (user_id, bet_amount, mines_count, multiplier, win_amount, status, created_at) VALUES (?, ?, ?, 0, 0, 'loss', NOW())");
            $stmt->bind_param("idi", $user_id, $g['bet'], $g['mines_count']);
            $stmt->execute();
            
            $mines_loc = array_keys($g['grid'], 'mine');
            unset($_SESSION['mines_game']);
            
            echo json_encode(['status'=>'boom', 'mines'=>$mines_loc]);
        } else {
            // GEM FOUND
            $g['revealed'][] = $idx;
            
            // Calculate Multiplier
            $total = 25; $mines = $g['mines_count']; $rev = count($g['revealed']);
            $mul = 1;
            for($i=0; $i<$rev; $i++) { $mul *= ($total - $i) / ($total - $mines - $i); }
            $mul = floor($mul * 98) / 100; // 98% RTP adjustment
            
            $profit = $g['bet'] * $mul;
            
            echo json_encode([
                'status'=>'gem', 
                'profit'=>number_format($profit, 2), 
                'mul'=>number_format($mul, 2)
            ]);
        }
        exit;
    }

    // --- CASHOUT ---
    if ($act === 'cashout') {
        if (!isset($_SESSION['mines_game']) || !$_SESSION['mines_game']['active']) exit;
        $g = $_SESSION['mines_game'];
        
        // Recalculate Win
        $total = 25; $mines = $g['mines_count']; $rev = count($g['revealed']);
        $mul = 1;
        for($i=0; $i<$rev; $i++) { $mul *= ($total - $i) / ($total - $mines - $i); }
        $mul = floor($mul * 98) / 100;
        
        $win = $g['bet'] * $mul;
        
        // Update DB
        $db->query("UPDATE users SET balance = balance + $win WHERE id = $user_id");
        
        // Save History
        $stmt = $db->prepare("INSERT INTO mines_history (user_id, bet_amount, mines_count, multiplier, win_amount, status, created_at) VALUES (?, ?, ?, ?, ?, 'win', NOW())");
        $stmt->bind_param("ididd", $user_id, $g['bet'], $g['mines_count'], $mul, $win);
        $stmt->execute();
        
        $mines_loc = array_keys($g['grid'], 'mine');
        unset($_SESSION['mines_game']);
        
        // Get Final Balance
        $ub = $db->query("SELECT balance FROM users WHERE id = $user_id")->fetch_assoc();
        
        echo json_encode([
            'status'=>'success', 
            'win'=>number_format($win, 2), 
            'bal'=>number_format($ub['balance'], 2, '.', ''), 
            'mines'=>$mines_loc, 
            'mul'=>$mul
        ]);
        exit;
    }

    // --- HISTORY ---
    if ($act === 'history') {
        $q = $db->query("SELECT *, DATE_FORMAT(created_at, '%H:%i') as time_only FROM mines_history WHERE user_id = $user_id ORDER BY id DESC LIMIT 15");
        $h=[]; 
        while($r=$q->fetch_assoc()) $h[]=$r; 
        echo json_encode($h); 
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>Pro Mines</title>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&family=Rajdhani:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

<style>
    :root { --bg-dark: #0f172a; --panel-bg: #1e293b; --accent: #00e701; --danger: #ff4949; }
    
    /* RESET & LAYOUT */
    html { height: -webkit-fill-available; }
    body { 
        margin: 0; padding: 0; width: 100vw; height: 100vh; height: 100dvh; 
        background: var(--bg-dark); color: white; font-family: 'Poppins', sans-serif;
        overflow: hidden; display: flex; flex-direction: column;
        touch-action: manipulation; -webkit-user-select: none; user-select: none;
    }

    /* HEADER */
    .app-header { 
        flex: 0 0 60px; display: flex; align-items: center; justify-content: space-between; 
        padding: 0 16px; background: rgba(30, 41, 59, 0.95); 
        border-bottom: 1px solid rgba(255,255,255,0.05); z-index: 50; 
        padding-top: calc(10px + env(safe-area-inset-top));
    }
    .back-btn { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: #334155; border-radius: 12px; color: #94a3b8; }
    .balance-badge { background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; padding: 6px 14px; border-radius: 12px; font-family: "Rajdhani"; font-weight: 700; font-size: 1.1rem; }

    /* HISTORY DRAWER */
    .history-drawer { position: fixed; top: 0; right: 0; bottom: 0; width: 300px; background: #1e293b; z-index: 100; transform: translateX(100%); transition: transform 0.3s; border-left: 1px solid rgba(255,255,255,0.1); padding: 20px; display: flex; flex-direction: column; padding-top: calc(20px + env(safe-area-inset-top)); }
    .history-drawer.open { transform: translateX(0); }
    .h-item { background: rgba(0,0,0,0.2); padding: 12px; border-radius: 10px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; }
    .h-win { border-left: 4px solid #10b981; } .h-loss { border-left: 4px solid #ef4444; }

    /* GAME CONTENT */
    .game-content {
        flex: 1; overflow-y: auto; padding: 20px;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
    }
    .grid-container {
        width: 100%; max-width: 350px; aspect-ratio: 1;
        background: #1e293b; padding: 10px; border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px;
    }
    .tile {
        background: #334155; border-radius: 8px; cursor: pointer;
        position: relative; transition: all 0.1s;
        box-shadow: 0 4px 0 #1e293b;
        display: flex; align-items: center; justify-content: center;
    }
    .tile:active { transform: translateY(3px); box-shadow: none; }
    .tile.revealed { background: #0f172a; box-shadow: inset 0 0 0 2px #334155; transform: none; cursor: default; }
    .tile.dim { opacity: 0.3; }
    
    .svg-icon { width: 60%; height: 60%; animation: pop 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
    @keyframes pop { 0%{transform:scale(0)} 100%{transform:scale(1)} }

    /* CONTROLS (Bottom Fixed) */
    .bottom-controls {
        flex: 0 0 auto; background: #1e293b; padding: 20px;
        padding-bottom: calc(20px + env(safe-area-inset-bottom));
        border-radius: 24px 24px 0 0; box-shadow: 0 -5px 30px rgba(0,0,0,0.5);
        z-index: 40; border-top: 1px solid rgba(255,255,255,0.05);
    }
    .input-row { display: flex; gap: 10px; margin-bottom: 15px; }
    .grp { flex: 1; }
    .grp label { font-size: 10px; color: #94a3b8; font-weight: 700; text-transform: uppercase; display: block; margin-bottom: 5px; }
    .grp input, .grp select {
        width: 100%; background: #0f172a; border: 2px solid #334155;
        padding: 12px; border-radius: 10px; color: #fff; font-weight: 700;
        font-family: 'Rajdhani'; font-size: 16px; outline: none;
    }
    
    .btn {
        width: 100%; padding: 16px; border: none; border-radius: 14px;
        font-weight: 800; font-size: 16px; cursor: pointer; text-transform: uppercase;
        letter-spacing: 1px; transition: 0.2s;
    }
    .btn-green { background: linear-gradient(135deg, #00e701, #00b300); color: #000; box-shadow: 0 4px 0 #008000; }
    .btn-green:active { transform: translateY(4px); box-shadow: none; }
    .btn-cashout { background: #fff; color: #000; display: none; box-shadow: 0 4px 0 #cbd5e1; }

    /* MODAL */
    .modal {
        position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 200; 
        display: none; justify-content: center; align-items: center; backdrop-filter: blur(5px);
    }
    .m-card {
        background: #1e293b; width: 85%; max-width: 320px; padding: 30px;
        border-radius: 20px; text-align: center; border: 2px solid var(--accent);
        animation: zoomIn 0.3s;
    }
    @keyframes zoomIn { from{transform:scale(0.8);opacity:0} to{transform:scale(1);opacity:1} }
    .m-val { font-size: 36px; font-weight: 900; color: var(--accent); margin: 10px 0; font-family: 'Rajdhani'; }
    .m-sub { color: #94a3b8; font-size: 12px; font-weight: 700; text-transform: uppercase; }
</style>
</head>
<body>

    <header class="app-header">
        <div class="flex items-center gap-3">
            <a href="index.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
            <span class="font-bold text-lg text-white">MINES</span>
        </div>
        <div class="flex items-center gap-3">
            <div class="balance-badge">₹<span id="uiBalance"><?= number_format($user['balance'], 2) ?></span></div>
            <button onclick="toggleHistory()" class="back-btn"><i class="fas fa-history"></i></button>
        </div>
    </header>

    <div id="historyDrawer" class="history-drawer shadow-2xl">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-white">History</h3>
            <button onclick="toggleHistory()" class="text-slate-400"><i class="fas fa-times text-xl"></i></button>
        </div>
        <div id="historyList" class="flex-1 overflow-y-auto">Loading...</div>
    </div>
    <div id="historyOverlay" class="fixed inset-0 bg-black/50 z-90 hidden backdrop-blur-sm" onclick="toggleHistory()"></div>

    <div class="game-content">
        <div class="grid-container" id="grid">
            </div>
    </div>

    <div class="bottom-controls">
        <div class="input-row">
            <div class="grp">
                <label>Bet Amount</label>
                <input type="number" id="bet" value="100">
            </div>
            <div class="grp">
                <label>Mines (1-24)</label>
                <select id="mines">
                    <option value="1">1</option>
                    <option value="3" selected>3</option>
                    <option value="5">5</option>
                    <option value="10">10</option>
                    <option value="15">15</option>
                    <option value="20">20</option>
                    <option value="24">24</option>
                </select>
            </div>
        </div>
        <button id="btn_bet" class="btn btn-green">START GAME</button>
        <button id="btn_out" class="btn btn-cashout">CASHOUT</button>
    </div>

    <div class="modal" id="modal">
        <div class="m-card" id="m_content">
            <div class="m-sub" id="m_title">YOU WON</div>
            <div class="m-val" id="m_mul">2.00x</div>
            <div style="font-size:24px; color:#fff; font-weight:700; font-family:'Rajdhani'" id="m_win">₹200.00</div>
            <button class="btn" style="background:#334155; color:#fff; margin-top:20px;" onclick="closeModal()">CLOSE</button>
        </div>
    </div>

    <div style="display:none">
        <svg id="icon-gem" viewBox="0 0 512 512"><path fill="#00E701" d="M116.65 142.5L256 34.1l139.35 108.4H116.65zM256 477.9L37.15 174.5h437.7L256 477.9z"/></svg>
        <svg id="icon-bomb" viewBox="0 0 512 512"><path fill="#ff4949" d="M440.5 88.5l-52 52L415 167l67-67-25-25c-5-5-13-5-18 0zM352 144c-12 0-23 2-33 7l46 46c18 18 18 47 0 65s-47 18-65 0l-46-46c-5 10-7 21-7 33 0 58 47 105 105 105s105-47 105-105-47-105-105-105z"/><circle cx="205" cy="205" r="15" fill="#000"/></svg>
    </div>

<script>
const snd = {
    click: new Audio("https://assets.mixkit.co/active_storage/sfx/2568/2568-preview.mp3"),
    gem: new Audio("https://assets.mixkit.co/active_storage/sfx/2003/2003-preview.mp3"),
    bomb: new Audio("https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3"),
    win: new Audio("https://assets.mixkit.co/active_storage/sfx/2019/2019-preview.mp3")
};

function play(k) { let s = snd[k].cloneNode(); s.volume=0.5; s.play().catch(()=>{}); }

$(document).ready(() => {
    initGrid();
    loadHist();

    $('#btn_bet').click(() => {
        play('click');
        let b = $('#bet').val(), m = $('#mines').val();
        $.post('', {act:'start', bet:b, mines:m}, r => {
            if(r.status==='success') {
                $('#uiBalance').text(r.bal);
                toggleGame(true, b);
            } else alert(r.msg);
        }, 'json');
    });

    $('#grid').on('click', '.tile', function() {
        if($(this).hasClass('revealed')) return;
        let t = $(this);
        $.post('', {act:'click', idx:t.data('i')}, r => {
            if(r.status==='gem') {
                play('gem');
                t.addClass('revealed').html(getIcon('gem'));
                $('#btn_out').html(`CASHOUT ₹${r.profit} (${r.mul}x)`);
            } else if(r.status==='boom') {
                play('bomb');
                t.addClass('revealed mine').css('background','#ff4949').html(getIcon('bomb'));
                endGame(false, r.mines);
            }
        }, 'json');
    });

    $('#btn_out').click(() => {
        play('click');
        $.post('', {act:'cashout'}, r => {
            if(r.status==='success') {
                play('win'); confetti({origin:{y:0.8}});
                $('#uiBalance').text(r.bal);
                endGame(true, r.mines, r.win, r.mul);
            }
        }, 'json');
    });

    function initGrid() {
        let h=''; for(let i=0;i<25;i++) h+=`<div class="tile" data-i="${i}"></div>`;
        $('#grid').html(h);
    }

    function toggleGame(active, bet) {
        if(active) {
            $('.tile').removeClass('revealed mine dim').html('').css('pointer-events','auto');
            $('#btn_bet').hide(); $('#btn_out').show().html(`CASHOUT ₹${bet} (1.00x)`);
            $('input, select').prop('disabled', true);
        } else {
            $('#btn_bet').show(); $('#btn_out').hide();
            $('input, select').prop('disabled', false);
            initGrid();
        }
    }

    function endGame(win, mines, amt=0, mul='0.00') {
        mines.forEach(i => {
            let t = $(`.tile[data-i="${i}"]`);
            if(!t.hasClass('revealed')) t.addClass('revealed mine').css('opacity','0.5').html(getIcon('bomb'));
        });
        $('.tile:not(.revealed)').addClass('dim');
        $('.tile').css('pointer-events','none');

        let m = $('#m_content');
        if(win) {
            m.css('border-color', 'var(--accent)');
            $('#m_title').text('YOU WON').css('color','#94a3b8');
            $('#m_mul').text(mul+'x').css('color', 'var(--accent)');
            $('#m_win').text('₹'+amt);
        } else {
            m.css('border-color', 'var(--danger)');
            $('#m_title').text('GAME OVER').css('color','#94a3b8');
            $('#m_mul').text('BUSTED').css('color', 'var(--danger)');
            $('#m_win').text('');
        }
        setTimeout(() => $('#modal').fadeIn(200).css('display','flex'), 500);
        loadHist();
    }

    window.closeModal = () => { $('#modal').fadeOut(200); toggleGame(false); };
    window.toggleHistory = () => { 
        $('#historyDrawer').toggleClass('open'); 
        $('#historyOverlay').toggleClass('hidden');
        play('click');
    };

    function getIcon(type) {
        let svg = $(`#icon-${type}`).html();
        return `<svg class="svg-icon anim-pop" viewBox="0 0 512 512">${svg}</svg>`;
    }

    function loadHist() {
        $.post('', {act:'history'}, d => {
            let h = '';
            d.forEach(r => {
                let win = r.status === 'win';
                let cls = win ? 'h-win' : 'h-loss';
                let amt = win ? r.win_amount : r.bet_amount;
                let sign = win ? '+' : '-';
                let color = win ? 'text-emerald-400' : 'text-red-400';
                
                h += `<div class="h-item ${cls}">
                    <div><div class="text-white font-bold">${r.mines_count} Mines</div><div class="text-xs text-slate-400">${r.time_only || 'Just now'}</div></div>
                    <div class="${color} font-bold font-gaming">${sign}₹${amt}</div>
                </div>`;
            });
            $('#historyList').html(h || '<div class="text-center text-slate-500 mt-10">No history</div>');
        }, 'json');
    }
});
</script>
</body>
</html>