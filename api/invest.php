<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

// DB connection
define('DB_HOST', 'localhost');
define('DB_USER', 'chikenof_chick');
define('DB_PASS', 'chikenof_chick');
define('DB_NAME', 'chikenof_chick');

$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->connect_error) {
    echo json_encode(['status'=>'error','msg'=>'DB connection failed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status'=>'error','msg'=>'Not authenticated','redirect'=>'/login.html']);
    exit;
}
$user_id = intval($_SESSION['user_id']);

// Helper: add a notification
function addNotification($db, $userId, $title, $createdAt = null) {
    $createdAt = $createdAt ?? time();
    $ins = $db->prepare("INSERT INTO notifications (user_id, title, created_at) VALUES (?, ?, ?)");
    if ($ins === false) return false;
    $ins->bind_param('isi', $userId, $title, $createdAt);
    return $ins->execute();
}

// addXP helper (100 XP per level)
function addXP($db, $userId, $xpToAdd) {
    $xpToAdd = intval($xpToAdd);
    if ($xpToAdd <= 0) return false;
    try {
        $db->begin_transaction();
        $sel = $db->prepare("SELECT xp, level FROM users WHERE id = ? FOR UPDATE");
        if ($sel === false) { $db->rollback(); return false; }
        $sel->bind_param('i', $userId); $sel->execute();
        $res = $sel->get_result(); $row = ($res) ? $res->fetch_assoc() : null;
        $curXP = intval($row['xp'] ?? 0); $curLevel = intval($row['level'] ?? 1);
        $newXP = $curXP + $xpToAdd;
        $levelsBefore = intdiv($curXP, 100); $levelsAfter = intdiv($newXP, 100);
        $levelsGained = max(0, $levelsAfter - $levelsBefore);
        $newLevel = $curLevel + $levelsGained;
        $upd = $db->prepare("UPDATE users SET xp = ?, level = ? WHERE id = ?");
        if ($upd === false) { $db->rollback(); return false; }
        $upd->bind_param('iii', $newXP, $newLevel, $userId);
        if (!$upd->execute()) { $db->rollback(); return false; }
        $db->commit();
        return ['xp'=>$newXP, 'level'=>$newLevel, 'levelsGained'=>$levelsGained];
    } catch (Exception $e) {
        try { $db->rollback(); } catch (Exception $_) {}
        return false;
    }
}

// Plans definition
$plans = [
    ['id'=>1,'cat'=>'Small','cost'=>300,'time'=>45,'unit'=>'Min','profit'=>105,'total'=>405,'vip'=>1],
    ['id'=>2,'cat'=>'Small','cost'=>500,'time'=>60,'unit'=>'Min','profit'=>175,'total'=>675,'vip'=>2],
    ['id'=>3,'cat'=>'Small','cost'=>1000,'time'=>90,'unit'=>'Min','profit'=>350,'total'=>1350,'vip'=>3],
    ['id'=>4,'cat'=>'Small','cost'=>3000,'time'=>120,'unit'=>'Min','profit'=>1050,'total'=>4050,'vip'=>4],
    ['id'=>5,'cat'=>'Small','cost'=>5000,'time'=>180,'unit'=>'Min','profit'=>1750,'total'=>6750,'vip'=>5],
    ['id'=>6,'cat'=>'Mid','cost'=>10000,'time'=>6,'unit'=>'Hr','profit'=>3500,'total'=>13500,'vip'=>6],
    ['id'=>7,'cat'=>'Mid','cost'=>15000,'time'=>18,'unit'=>'Hr','profit'=>5250,'total'=>20250,'vip'=>7],
    ['id'=>8,'cat'=>'High','cost'=>25000,'time'=>36,'unit'=>'Hr','profit'=>8750,'total'=>33750,'vip'=>8],
    ['id'=>9,'cat'=>'High','cost'=>45000,'time'=>48,'unit'=>'Hr','profit'=>15750,'total'=>60750,'vip'=>9],
    ['id'=>10,'cat'=>'High','cost'=>80000,'time'=>72,'unit'=>'Hr','profit'=>28000,'total'=>108000,'vip'=>10],
];

$action = $_REQUEST['action'] ?? 'status';

// helper: get user
$stmt = $db->prepare("SELECT id, balance, vip_level FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) { echo json_encode(['status'=>'error','msg'=>'User not found']); exit; }

// fetch used plans
$usedPlans = [];
$q = $db->prepare("SELECT plan_id FROM user_investments WHERE user_id = ?");
$q->bind_param('i', $user_id);
$q->execute();
$res = $q->get_result();
while ($r = $res->fetch_assoc()) $usedPlans[] = intval($r['plan_id']);

// fetch active
$activeInv = null;
$q2 = $db->prepare("SELECT * FROM user_investments WHERE user_id = ? AND status = 'active' LIMIT 1");
$q2->bind_param('i', $user_id);
$q2->execute();
$r2 = $q2->get_result(); if ($r2->num_rows) $activeInv = $r2->fetch_assoc();

// fetch completed
$completed = [];
$q3 = $db->prepare("SELECT * FROM user_investments WHERE user_id = ? AND status = 'claimed' ORDER BY end_time DESC LIMIT 20");
$q3->bind_param('i', $user_id);
$q3->execute();
$r3 = $q3->get_result(); while ($row = $r3->fetch_assoc()) $completed[] = $row;

// compute unlocked: one per category (first unused)
function computeUnlocked($plans, $used, $vipLevel) {
    $unlocked = [];
    $cats = [];
    foreach ($plans as $p) $cats[$p['cat']][] = $p;
    foreach ($cats as $cat => $plist) {
        usort($plist, function($a,$b){ return $a['cost'] <=> $b['cost']; });
        $first = null; foreach ($plist as $p) { if (!in_array($p['id'], $used)) { $first = $p; break; } }
        if ($first) {
            // initial plan available for vip 1
            if ($vipLevel >= 1) $unlocked[] = intval($first['id']);
        }
    }
    return $unlocked;
}

$unlocked = computeUnlocked($plans, $usedPlans, intval($user['vip_level'] ?? 1));

if ($action === 'status') {
    echo json_encode(['status'=>'ok','plans'=>$plans,'used'=>$usedPlans,'active'=>$activeInv,'unlocked'=>$unlocked,'completed'=>$completed,'balance'=>floatval($user['balance']),'vip'=>intval($user['vip_level'] ?? 1)]);
    exit;
}

if ($action === 'invest') {
    $planId = intval($_POST['plan_id'] ?? 0);
    // find plan
    $plan = null; foreach ($plans as $p) if ($p['id']==$planId) { $plan = $p; break; }
    if (!$plan) { echo json_encode(['status'=>'error','msg'=>'Invalid plan']); exit; }

    // check used
    if (in_array($planId, $usedPlans)) { echo json_encode(['status'=>'error','msg'=>'Plan already used']); exit; }
    // check unlocked
    if (!in_array($planId, $unlocked)) { echo json_encode(['status'=>'error','msg'=>'Plan locked']); exit; }
    // check active
    $q4 = $db->prepare("SELECT id FROM user_investments WHERE user_id = ? AND status = 'active'");
    $q4->bind_param('i', $user_id); $q4->execute(); if ($q4->get_result()->num_rows) { echo json_encode(['status'=>'error','msg'=>'You already have an active investment']); exit; }

    // check balance
    $cost = floatval($plan['cost']);
    $userBal = floatval($user['balance']);
    if ($userBal < $cost) { echo json_encode(['status'=>'redirect','url'=>'/deposit.php','msg'=>'Insufficient funds']); exit; }

    // do transaction
    $db->begin_transaction();
    try {
        $newBal = floatval($user['balance']) - $cost;
        $up = $db->prepare("UPDATE users SET balance = ? WHERE id = ?");
        $up->bind_param('di', $newBal, $user_id); if (!$up->execute()) throw new Exception('balance update');

        $start = time();
        $duration = ($plan['unit']=='Min')?($plan['time']*60):($plan['time']*3600);
        $end = $start + $duration;
        $ret = floatval($plan['total']);
        $ins = $db->prepare("INSERT INTO user_investments (user_id, plan_id, start_time, end_time, amount, return_amount, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
        $ins->bind_param('iiiidd', $user_id, $planId, $start, $end, $cost, $ret);
        if (!$ins->execute()) throw new Exception('insert invest');

        $db->commit();
        // Award XP for investing
        $xpRes = addXP($db, $user_id, 20);
        if ($xpRes && is_array($xpRes)) {
            addNotification($db, $user_id, 'Investment bonus: +20 XP', time());
        }
        echo json_encode(['status'=>'success','msg'=>'Investment started','balance'=>$newBal,'xp'=>$xpRes['xp'] ?? null,'level'=>$xpRes['level'] ?? null]);
    } catch (Exception $e) {
        $db->rollback();
        echo json_encode(['status'=>'error','msg'=>'Transaction failed']);
    }
    exit;
}

if ($action === 'claim') {
    $planId = intval($_POST['plan_id'] ?? 0);
    $q5 = $db->prepare("SELECT * FROM user_investments WHERE user_id = ? AND plan_id = ? AND status = 'active'");
    $q5->bind_param('ii', $user_id, $planId);
    $q5->execute(); $r5 = $q5->get_result();
    if ($r5->num_rows == 0) { echo json_encode(['status'=>'error','msg'=>'No active plan']); exit; }
    $inv = $r5->fetch_assoc();
    if (time() < intval($inv['end_time'])) { echo json_encode(['status'=>'error','msg'=>'Timer not finished']); exit; }

    $db->begin_transaction();
    try {
        $sel = $db->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
        $sel->bind_param('i', $user_id); $sel->execute(); $cur = $sel->get_result()->fetch_assoc();
        $curBal = floatval($cur['balance'] ?? 0);
        $newBal = $curBal + floatval($inv['return_amount']);
        $u1 = $db->prepare("UPDATE users SET balance = ? WHERE id = ?"); $u1->bind_param('di', $newBal, $user_id);
        $u2 = $db->prepare("UPDATE user_investments SET status = 'claimed' WHERE id = ?"); $u2->bind_param('i', $inv['id']);
        if (!$u1->execute() || !$u2->execute()) throw new Exception('update failed');
        $db->commit();
        // Award XP for claiming profit
        $xpRes = addXP($db, $user_id, 30);
        if ($xpRes && is_array($xpRes)) {
            addNotification($db, $user_id, 'Profit claimed: +30 XP', time());
        }
        echo json_encode(['status'=>'success','msg'=>'Profit claimed','balance'=>$newBal,'xp'=>$xpRes['xp'] ?? null,'level'=>$xpRes['level'] ?? null]);
    } catch (Exception $e) {
        $db->rollback();
        echo json_encode(['status'=>'error','msg'=>'Transaction failed']);
    }
    exit;
}

echo json_encode(['status'=>'error','msg'=>'Unknown action']);
