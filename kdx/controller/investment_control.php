<?php
// Investment Admin Controller - manage active investments and force-close
$config_path = file_exists('../config.php') ? '../config.php' : '../../config.php';
if (file_exists($config_path)) {
    require_once $config_path;
}

// Simple API: return live active investments
if (isset($_GET['api']) && $_GET['api'] === 'live_stats') {
    header('Content-Type: application/json');
    if (!isset($pdo)) { echo json_encode(['error' => 'DB connection failed']); exit; }
    try {
        $stmt = $pdo->query("SELECT SUM(amount) as total_amount, COUNT(DISTINCT user_id) as active_users FROM user_investments WHERE status = 'active'");
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $q = $pdo->query("SELECT id, user_id, plan_id, amount, return_amount, start_time, end_time, status FROM user_investments WHERE status = 'active' ORDER BY id DESC LIMIT 200");
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);

        $live = [];
        foreach ($rows as $r) {
            $remaining = intval($r['end_time']) - time();
            if ($remaining < 0) $remaining = 0;
            $r['remaining'] = $remaining;
            $live[] = $r;
        }

        echo json_encode([
            'total_amount' => floatval($stats['total_amount'] ?? 0),
            'active_users' => intval($stats['active_users'] ?? 0),
            'investments' => $live
        ]);
    } catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
    exit;
}

// Handle admin actions (force close)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'force_close') {
    header('Content-Type: application/json');
    if (!isset($_SESSION) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        echo json_encode(['status'=>'error','msg'=>'Not authorized']); exit;
    }

    $invId = intval($_POST['id'] ?? 0);
    if ($invId <= 0) { echo json_encode(['status'=>'error','msg'=>'Invalid id']); exit; }

    try {
        $pdo->beginTransaction();
        $s = $pdo->prepare("SELECT * FROM user_investments WHERE id = ? FOR UPDATE");
        $s->execute([$invId]);
        $inv = $s->fetch(PDO::FETCH_ASSOC);
        if (!$inv) throw new Exception('Investment not found');
        if ($inv['status'] !== 'active') throw new Exception('Investment not active');

        // Update investment to claimed and set end_time now
        $now = time();
        $u = $pdo->prepare("UPDATE user_investments SET status = 'claimed', end_time = ? WHERE id = ?");
        $u->execute([$now, $invId]);

        // Credit user balance
        $credit = floatval($inv['return_amount']);
        $up = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $up->execute([$credit, $inv['user_id']]);

        // Insert a transaction record for auditing
        $t = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, method, description, status, created_at) VALUES (?, 'investment_claim', ?, 'admin_force', 'Admin forced claim for investment #{$invId}', 'approved', NOW())");
        $t->execute([$inv['user_id'], $credit]);

        // Insert notification (notifications table uses INT created_at in other places)
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $n = $pdo->prepare("INSERT INTO notifications (user_id, title, created_at) VALUES (?, ?, ?)");
            $n->execute([$inv['user_id'], "Your investment #{$invId} was force-closed by admin. +₹".number_format($credit,2), time()]);
        }

        $pdo->commit();
        echo json_encode(['status'=>'success','msg'=>'Investment force-closed and user credited','id'=>$invId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status'=>'error','msg'=>$e->getMessage()]);
    }
    exit;
}

require_once 'includes/header.php';
?>

<div class="max-w-6xl mx-auto mt-6 px-4 pb-10">
    <div class="mb-6 flex flex-col md:flex-row justify-between items-center gap-4">
        <div>
            <h2 class="text-3xl font-bold text-white flex items-center gap-3">
                <i class="fa-solid fa-sack-dollar text-yellow-400"></i> Investment Control
            </h2>
            <p class="text-gray-400 text-sm mt-1">View active investments and force-close to allow users claim.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 mb-8">
        <div class="bg-gray-800 rounded-2xl p-6 border border-gray-700 shadow-lg">
            <div class="flex justify-between items-center mb-4">
                <div><h3 class="text-xs font-bold text-gray-300 uppercase">Live Investments</h3></div>
                <div class="text-sm text-gray-400">Total Live: <span id="liveCount">0</span> • Amount: ₹<span id="liveAmt">0.00</span></div>
            </div>
            <div id="invList" class="space-y-3">Loading...</div>
        </div>
    </div>
</div>

<script>
async function loadLive() {
    const res = await fetch('?api=live_stats');
    const data = await res.json();
    if (data.error) { document.getElementById('invList').innerText = data.error; return; }
    document.getElementById('liveCount').innerText = data.active_users;
    document.getElementById('liveAmt').innerText = Number(data.total_amount).toFixed(2);

    const list = document.getElementById('invList');
    if (!data.investments || data.investments.length === 0) { list.innerHTML = '<div class="text-sm text-gray-400 italic">No active investments</div>'; return; }

    let html = '';
    data.investments.forEach(inv => {
        const rem = Math.max(0, inv.remaining);
        html += `
            <div class="bg-gray-700/40 p-3 rounded-xl border border-gray-600 flex justify-between items-center">
                <div>
                    <div class="text-sm font-bold">User: ${inv.user_id} • InvID: ${inv.id}</div>
                    <div class="text-xs text-gray-300">Amount: ₹${Number(inv.amount).toFixed(2)} • Return: ₹${Number(inv.return_amount).toFixed(2)}</div>
                    <div class="text-xs text-gray-400">Ends in: <span id="r_${inv.id}">${rem}s</span></div>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="forceClose(${inv.id})" class="bg-red-600 hover:bg-red-500 text-white px-3 py-2 rounded-lg text-sm">Force Close</button>
                </div>
            </div>`;
    });
    list.innerHTML = html;
}

async function forceClose(id) {
    if (!confirm('Force close investment #' + id + ' and credit user?')) return;
    const fd = new FormData(); fd.append('action','force_close'); fd.append('id', id);
    const res = await fetch('', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.status === 'success') { alert(data.msg); loadLive(); } else { alert('Error: ' + data.msg); }
}

setInterval(loadLive, 2000);
loadLive();
</script>

</body>
</html>
