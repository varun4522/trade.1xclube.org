<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', 'localhost');
define('DB_USER', 'chikenof_chick');
define('DB_PASS', 'chikenof_chick');
define('DB_NAME', 'chikenof_chick');

try {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        throw new Exception("Connection failed: " . $db->connect_error);
    }
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Ensure notifications table exists (simple schema)
$db->query("CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title TEXT NOT NULL,
  seen TINYINT(1) DEFAULT 0,
  meta JSON DEFAULT NULL,
  created_at INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Helper: add a notification and return boolean success
function addNotification($db, $userId, $title, $createdAt = null) {
  $createdAt = $createdAt ?? time();
  $ins = $db->prepare("INSERT INTO notifications (user_id, title, created_at) VALUES (?, ?, ?)");
  if ($ins === false) {
    error_log('Notify prepare failed: ' . $db->error);
    return false;
  }
  $ins->bind_param("isi", $userId, $title, $createdAt);
  if (!$ins->execute()) {
    error_log('Notify execute failed: ' . $db->error . ' | errno: ' . $db->errno);
    return false;
  }
  return true;
}

// Ensure users table has all required columns
$columnsToCheck = [
  'username' => "ALTER TABLE users ADD COLUMN username VARCHAR(100) NULL",
  'level' => "ALTER TABLE users ADD COLUMN level INT DEFAULT 1",
  'vip_level' => "ALTER TABLE users ADD COLUMN vip_level INT DEFAULT 1", 
  'xp' => "ALTER TABLE users ADD COLUMN xp INT DEFAULT 0",
  'last_login_date' => "ALTER TABLE users ADD COLUMN last_login_date DATE NULL",
  'is_vip' => "ALTER TABLE users ADD COLUMN is_vip TINYINT(1) DEFAULT 0",
  'streak' => "ALTER TABLE users ADD COLUMN streak INT DEFAULT 0",
  'last_ip' => "ALTER TABLE users ADD COLUMN last_ip VARCHAR(45) NULL"
];

foreach ($columnsToCheck as $colName => $alterQuery) {
  $colCheck = $db->query("SHOW COLUMNS FROM users LIKE '$colName'");
  if ($colCheck && $colCheck->num_rows == 0) {
    $db->query($alterQuery);
  }
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_query = $db->prepare("SELECT * FROM users WHERE id = ?");
$user_query = $user_query === false ? null : $user_query;
if (!$user_query) {
  error_log('Prepare failed (users): ' . $db->error);
  die('Database error. Please contact admin.');
}
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user = $user_result->fetch_assoc();

if (!$user) {
    session_destroy();
    header("Location: login.html");
    exit();
}

// User Variables
$username = $user['username'] ?? 'Player';
$userBalance = floatval($user['balance'] ?? 0.00);
$userLevel = $user['level'] ?? 1;
$vipLevel = intval($user['vip_level'] ?? 1);
$userXP = $user['xp'] ?? 0;
$vipStatus = ($user['is_vip'] ?? 0) == 1; 
$dailyStreak = $user['streak'] ?? 0;
$referralCode = $user['referral_code'] ?? 'N/A';
$showWelcomePopup = !isset($_COOKIE['welcome_popup_shown']);

// VIP badge helper
function getVipBadge($vipLevel) {
  switch ((int)$vipLevel) {
    case 2:
      return ['name' => 'Silver', 'class' => 'bg-gray-300 text-gray-900'];
    case 3:
      return ['name' => 'Gold', 'class' => 'bg-yellow-400 text-black'];
    case 4:
      return ['name' => 'Platinum', 'class' => 'bg-blue-500 text-white'];
    case 5:
      return ['name' => 'Diamond', 'class' => 'bg-purple-600 text-white'];
    default:
      return ['name' => 'Bronze', 'class' => 'bg-orange-600 text-white'];
  }
}

// derive badge for current user
$vipBadge = getVipBadge($vipLevel);

// Auto-match class name for VIP badges (bronze/silver/gold/platinum/diamond)
$vipClasses = [
  1 => 'bronze',
  2 => 'silver',
  3 => 'gold',
  4 => 'platinum',
  5 => 'diamond'
];
$vipClass = $vipClasses[$vipLevel] ?? 'bronze';

// addXP: safely add XP and handle level-ups (100 XP per level)
function addXP($db, $userId, $xpToAdd) {
  $xpToAdd = intval($xpToAdd);
  if ($xpToAdd <= 0) return false;
  try {
    $db->begin_transaction();
    $sel = $db->prepare("SELECT xp, level FROM users WHERE id = ? FOR UPDATE");
    if ($sel === false) { $db->rollback(); return false; }
    $sel->bind_param("i", $userId);
    $sel->execute();
    $res = $sel->get_result();
    $row = ($res) ? $res->fetch_assoc() : null;
    $curXP = intval($row['xp'] ?? 0);
    $curLevel = intval($row['level'] ?? 1);

    $newXP = $curXP + $xpToAdd;
    $levelsBefore = intdiv($curXP, 100);
    $levelsAfter = intdiv($newXP, 100);
    $levelsGained = max(0, $levelsAfter - $levelsBefore);
    $newLevel = $curLevel + $levelsGained;

    $upd = $db->prepare("UPDATE users SET xp = ?, level = ? WHERE id = ?");
    if ($upd === false) { $db->rollback(); return false; }
    $upd->bind_param("iii", $newXP, $newLevel, $userId);
    if (!$upd->execute()) { $db->rollback(); return false; }

    $db->commit();
    return ['xp' => $newXP, 'level' => $newLevel, 'levelsGained' => $levelsGained];
  } catch (Exception $e) {
    try { $db->rollback(); } catch (Exception $_) {}
    error_log('addXP error: ' . $e->getMessage());
    return false;
  }
}

// Daily login XP: award once per day
$todayDate = date('Y-m-d');
$lastLoginDate = $user['last_login_date'] ?? null;
if (empty($lastLoginDate) || $lastLoginDate !== $todayDate) {
  $xpRes = addXP($db, $user_id, 10);
  if ($xpRes !== false && is_array($xpRes)) {
    $userXP = $xpRes['xp'] ?? $userXP;
    $msg = 'Daily login: +10 XP';
    if (!empty($xpRes['levelsGained']) && $xpRes['levelsGained'] > 0) {
      $msg = 'Daily login: +10 XP — Leveled up to level ' . intval($xpRes['level']) . '!';
    }
    addNotification($db, $user_id, $msg, time());
  }
  $updDate = $db->prepare("UPDATE users SET last_login_date = ? WHERE id = ?");
  if ($updDate !== false) {
    $updDate->bind_param("si", $todayDate, $user_id);
    $updDate->execute();
  }
}

// --- INVESTMENT PLANS (loaded from DB) ---
$plans = [];
$planRes = $db->query("SELECT * FROM plans WHERE status = 1 ORDER BY cost ASC");
if ($planRes) {
  while ($row = $planRes->fetch_assoc()) {
    // Normalize column names to match existing UI keys
    $plan = $row;
    if (isset($row['duration']) && !isset($plan['time'])) $plan['time'] = $row['duration'];
    if (isset($row['duration_unit']) && !isset($plan['unit'])) $plan['unit'] = $row['duration_unit'];
    if (isset($row['vip_level']) && !isset($plan['vip'])) $plan['vip'] = $row['vip_level'];

    // Ensure numeric fields
    $plan['cost'] = isset($plan['cost']) ? (int)$plan['cost'] : 0;
    $plan['profit'] = isset($plan['profit']) ? (int)$plan['profit'] : 0;
    $plan['total'] = isset($plan['total']) ? (int)$plan['total'] : ($plan['cost'] + $plan['profit']);

    $plans[] = $plan;
  }
} else {
  // fallback: no plans in DB
  $plans = [];
}

// Ensure the profit and total return values are correctly set
foreach ($plans as &$plan) {
    if ($plan['cost'] == 300) {
        $plan['profit'] = 120;
        $plan['total'] = 420;
    } elseif ($plan['cost'] == 500) {
        $plan['profit'] = 200;
        $plan['total'] = 700;
    } else {
        // 35% ROI for all others
        $plan['profit'] = intval($plan['cost'] * 0.35);
        $plan['total'] = $plan['cost'] + $plan['profit'];
    }
}
unset($plan);



// --- FETCH INVESTMENT DATA ---
$usedPlans = [];
// Only consider plans that have been fully claimed as "used" so a user
// cannot re-invest in a plan after claiming its profit.
$useQ = $db->prepare("SELECT plan_id FROM user_investments WHERE user_id = ? AND status = 'claimed'");
if ($useQ === false) {
  error_log('Prepare failed (user_investments claimed): ' . $db->error);
  $resUse = [];
} else {
  $useQ->bind_param("i", $user_id);
  $useQ->execute();
  $resUse = $useQ->get_result();
}
if ($resUse) {
  while ($row = $resUse->fetch_assoc()) $usedPlans[] = intval($row['plan_id']);
}

// --- Fetch notifications and unread count for this user ---
$notifications = [];
$unreadNotifications = 0;
$notifQ = $db->prepare("SELECT id, title, seen, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 25");
if ($notifQ === false) {
  error_log('Prepare failed (notifications select): ' . $db->error);
  $notifRes = [];
} else {
  $notifQ->bind_param("i", $user_id);
  $notifQ->execute();
  $notifRes = $notifQ->get_result();
}
if ($notifRes) {
  while ($n = $notifRes->fetch_assoc()) $notifications[] = $n;
}
$countQ = $db->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND seen = 0");
if ($countQ === false) {
  error_log('Prepare failed (unread notifications count): ' . $db->error);
  $countRes = ['cnt' => 0];
} else {
  $countQ->bind_param("i", $user_id);
  $countQ->execute();
  $countResObj = $countQ->get_result();
  $countRes = ($countResObj) ? $countResObj->fetch_assoc() : ['cnt' => 0];
}
$unreadNotifications = intval($countRes['cnt'] ?? 0);

// Count finished-but-unseen notifications to show immediate toast
$finishedCount = 0;
$fQ = $db->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND seen = 0 AND title LIKE '%finished%'");
if ($fQ === false) {
  error_log('Prepare failed (finished notifications count): ' . $db->error);
  $fc = ['c' => 0];
} else {
  $fQ->bind_param("i", $user_id);
  $fQ->execute();
  $fcObj = $fQ->get_result();
  $fc = ($fcObj) ? $fcObj->fetch_assoc() : ['c' => 0];
}
$finishedCount = intval($fc['c'] ?? 0);

// IP check removed: IP-based anti-fraud handled at registration as requested.

// Fetch all active investments for the user so multiple concurrent
// investments are supported (but we will prevent duplicate active
// investments for the same plan).
$activeInvs = [];
$actQ = $db->prepare("SELECT * FROM user_investments WHERE user_id = ? AND status = 'active'");
if ($actQ === false) {
  error_log('Prepare failed (select active investments): ' . $db->error);
  $resActive = [];
} else {
  $actQ->bind_param("i", $user_id);
  $actQ->execute();
  $resActive = $actQ->get_result();
}
if ($resActive) {
  while ($r = $resActive->fetch_assoc()) $activeInvs[] = $r;
}

// If any active investment already finished (end_time <= now), insert a 'plan finished' notification
$nowTs = time();
foreach ($activeInvs as $aInv) {
  if (intval($aInv['end_time']) <= $nowTs) {
    $checkN = $db->prepare("SELECT id FROM notifications WHERE user_id = ? AND title = ? LIMIT 1");
    $t = "Investment #" . intval($aInv['id']) . " finished and ready to claim";
    if ($checkN === false) {
      error_log('Prepare failed (check finished notification): ' . $db->error);
      $exists = false;
    } else {
      $checkN->bind_param("is", $user_id, $t);
      $checkN->execute();
      $exObj = $checkN->get_result();
      $exists = ($exObj) ? $exObj->fetch_assoc() : false;
    }
    if (!$exists) {
      if (addNotification($db, $user_id, $t, $nowTs)) {
        // increment local counters so UI shows it without reload
        $unreadNotifications++;
        array_unshift($notifications, ['id'=>null,'title'=>$t,'seen'=>0,'created_at'=>$nowTs]);
      } else {
        error_log('Failed to add finished notification for user ' . $user_id . ' plan ' . intval($aInv['id']));
      }
    }
  }
}

$completedInvestments = [];
$compQ = $db->prepare("SELECT * FROM user_investments WHERE user_id = ? AND status = 'claimed' ORDER BY end_time DESC");
if ($compQ === false) {
  error_log('Prepare failed (select claimed investments): ' . $db->error);
  $resComp = [];
} else {
  $compQ->bind_param("i", $user_id);
  $compQ->execute();
  $resComp = $compQ->get_result();
}
if ($resComp) {
  while ($r = $resComp->fetch_assoc()) $completedInvestments[] = $r;
}

// --- Analytics: totals for user ---
$analytics = [
  'total_invested' => 0.0,
  'total_profit' => 0.0,
  'active_count' => 0,
  'roi_percent' => 0.0,
];
$aQ = $db->prepare("SELECT SUM(amount) as total_invested FROM user_investments WHERE user_id = ?");
if ($aQ === false) {
  error_log('Prepare failed (total_invested): ' . $db->error);
  $aRes = ['total_invested' => 0];
} else {
  $aQ->bind_param("i", $user_id);
  $aQ->execute();
  $aResObj = $aQ->get_result();
  $aRes = ($aResObj) ? $aResObj->fetch_assoc() : ['total_invested' => 0];
}
 $analytics['total_invested'] = floatval($aRes['total_invested'] ?? 0);

$pQ = $db->prepare("SELECT SUM(return_amount - amount) as total_profit FROM user_investments WHERE user_id = ? AND status = 'claimed'");
if ($pQ === false) {
  error_log('Prepare failed (total_profit): ' . $db->error);
  $pRes = ['total_profit' => 0];
} else {
  $pQ->bind_param("i", $user_id);
  $pQ->execute();
  $pResObj = $pQ->get_result();
  $pRes = ($pResObj) ? $pResObj->fetch_assoc() : ['total_profit' => 0];
}
$analytics['total_profit'] = floatval($pRes['total_profit'] ?? 0);

$acQ = $db->prepare("SELECT COUNT(*) as cnt FROM user_investments WHERE user_id = ? AND status = 'active'");
if ($acQ === false) {
  error_log('Prepare failed (active count): ' . $db->error);
  $acRes = ['cnt' => 0];
} else {
  $acQ->bind_param("i", $user_id);
  $acQ->execute();
  $acResObj = $acQ->get_result();
  $acRes = ($acResObj) ? $acResObj->fetch_assoc() : ['cnt' => 0];
}
$analytics['active_count'] = intval($acRes['cnt'] ?? 0);

// Force ROI percent to fixed 40% as requested
$analytics['roi_percent'] = 35;

// Balance low suggestion: if balance below minimum plan cost, insert lightweight notification
$minCost = PHP_INT_MAX;
foreach ($plans as $pp) { if (isset($pp['cost']) && $pp['cost'] < $minCost) $minCost = $pp['cost']; }
if ($userBalance < $minCost) {
  $t = "Low balance: deposit at least ₹" . intval($minCost) . " to join plans";
  $chk = $db->prepare("SELECT id FROM notifications WHERE user_id = ? AND title = ? LIMIT 1");
  if ($chk === false) {
    error_log('Prepare failed (low balance chk): ' . $db->error);
  } else {
    $chk->bind_param("is", $user_id, $t);
    $chk->execute();
    $chkResObj = $chk->get_result();
    if (!($chkResObj ? $chkResObj->fetch_assoc() : false)) {
      $ts = time();
      if (addNotification($db, $user_id, $t, $ts)) {
        $unreadNotifications++;
        array_unshift($notifications, ['id'=>null,'title'=>$t,'seen'=>0,'created_at'=>$ts]);
      } else {
        error_log('Failed to add low-balance notification for user ' . $user_id);
      }
    }
  }
}

// No category-based locking: all plans are considered unlocked by default.
// Locking is only applied when a user has already claimed a plan (status='claimed').

// Helper: returns one of 'locked','running','claim','available','completed'
function getPlanStatus($planId, $activeInvs, $usedPlans) {
  $planId = intval($planId);
  // If already used and not active
  $hasActiveForPlan = false;
  $activeInvForPlan = null;
  foreach ($activeInvs as $ai) {
    if (intval($ai['plan_id']) === $planId) { $hasActiveForPlan = true; $activeInvForPlan = $ai; break; }
  }

  if (in_array($planId, $usedPlans) && !$hasActiveForPlan) {
    return 'locked'; // Permanently locked - already used/claimed
  }

  // If currently running for this plan
  if ($hasActiveForPlan && $activeInvForPlan) {
    if (time() < intval($activeInvForPlan['end_time'])) return 'running';
    return 'claim';
  }

  // Not active and not claimed => available
  return 'available';
}

// Helper: returns end_time (int) for active plan or null
function getEndTime($planId, $activeInvs) {
  foreach ($activeInvs as $ai) {
    if (intval($ai['plan_id']) === intval($planId)) return intval($ai['end_time']);
  }
  return null;
}

// Helper: get next plan ID after current plan
function getNextPlanId($planId, $plans) {
  $currentIdx = -1;
  foreach ($plans as $i => $p) {
    if ($p['id'] == $planId) {
      $currentIdx = $i;
      break;
    }
  }
  if ($currentIdx >= 0 && $currentIdx + 1 < count($plans)) {
    return $plans[$currentIdx + 1]['id'];
  }
  return null;
}

// --- INVEST/CLAIM POST HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  header('Content-Type: application/json');
  $action = $_POST['action'];
  $planId = intval($_POST['plan_id'] ?? 0);
  $selectedPlan = null;
  foreach ($plans as $p) if ($p['id'] == $planId) { $selectedPlan = $p; break; }
  if ($action === 'mark_notifications') {
    $upd = $db->prepare("UPDATE notifications SET seen = 1 WHERE user_id = ?");
    if ($upd === false) {
      error_log('Prepare failed (mark notifications): ' . $db->error);
      echo json_encode(['status'=>'error']);
    } else {
      $upd->bind_param("i", $user_id);
      if ($upd->execute()) {
        echo json_encode(['status'=>'success']);
      } else {
        echo json_encode(['status'=>'error']);
      }
    }
    exit;
  }
  
  // --- NEXT button: auto-claim the specified completed plan only ---
  if ($action === 'next' && $selectedPlan) {
    $planId = intval($_POST['plan_id'] ?? 0);
    $now = time();
    $invQ = $db->prepare("SELECT * FROM user_investments WHERE user_id = ? AND plan_id = ? AND status = 'active' LIMIT 1");
    if ($invQ === false) {
      error_log('Prepare failed (next select): ' . $db->error);
      echo json_encode(['status'=>'error','msg'=>'Server error']); exit;
    }
    $invQ->bind_param("ii", $user_id, $planId);
    $invQ->execute();
    $invRes = $invQ->get_result();
    if ($invRes->num_rows == 0) { echo json_encode(['status'=>'error','msg'=>'No active plan']); exit; }
    $inv = $invRes->fetch_assoc();
    if ($now < $inv['end_time']) { echo json_encode(['status'=>'error','msg'=>'Plan not finished']); exit; }

    // Claim this single plan and update wallet
    $db->begin_transaction();
    try {
      $sel = $db->prepare("SELECT balance, streak, last_invest_date FROM users WHERE id = ? FOR UPDATE");
      if ($sel === false) throw new Exception('Server error');
      $sel->bind_param("i", $user_id);
      $sel->execute();
      $rObj = $sel->get_result();
      $r = ($rObj) ? $rObj->fetch_assoc() : ['balance'=>0,'streak'=>0,'last_invest_date'=>0];
      $curBal = floatval($r['balance'] ?? 0);
      $curStreak = intval($r['streak'] ?? 0);
      $lastInvestDate = intval($r['last_invest_date'] ?? 0);

      $ret = floatval($inv['return_amount']);
      $newBal = $curBal + $ret;

      // mark claimed
      $upInv = $db->prepare("UPDATE user_investments SET status = 'claimed' WHERE id = ?");
      if ($upInv === false) throw new Exception('Server error');
      $upInv->bind_param("i", $inv['id']);
      if (!$upInv->execute()) throw new Exception('Investment update failed');

      // streak
      $today = strtotime('today'); $yesterday = $today - 86400;
      if ($lastInvestDate >= $today) { }
      elseif ($lastInvestDate >= $yesterday) { $curStreak += 1; }
      else { $curStreak = 1; }
      $lastInvestDate = $now;

      if ($curStreak === 3) { $bonus = 100; $newBal += $bonus; if (!addNotification($db, $user_id, "Streak bonus: +₹".number_format($bonus)." (Day 3)", $now)) throw new Exception('Notification insert failed'); }
      if ($curStreak === 7) { $coupon = 'FREEINV'.strtoupper(substr(md5($user_id.time()),0,6)); if (!addNotification($db, $user_id, "Streak reward: free invest coupon ".$coupon." (Day 7)", $now)) throw new Exception('Notification insert failed'); }

      // update user
      $upd = $db->prepare("UPDATE users SET balance = ?, streak = ?, last_invest_date = ? WHERE id = ?");
      if ($upd === false) throw new Exception('Server error');
      $upd->bind_param("diii", $newBal, $curStreak, $lastInvestDate, $user_id);
      if (!$upd->execute()) throw new Exception('User update failed');

      // claim notification
      if (!addNotification($db, $user_id, "Investment #".$inv['id']." claimed: +₹".number_format($ret), $now)) throw new Exception('Notification insert failed');


      $db->commit();

      // Award XP for auto-claim (post-commit)
      $xpRes = addXP($db, $user_id, 30);
      if ($xpRes && is_array($xpRes)) {
        $msg = 'Profit claimed: +30 XP';
        if (!empty($xpRes['levelsGained']) && $xpRes['levelsGained'] > 0) {
          $msg .= ' — Level up to ' . intval($xpRes['level']) . ' 🎉';
        }
        addNotification($db, $user_id, $msg, $now);
      }

      // compute next plan info
      $nextId = getNextPlanId($planId, $plans);
      $nextCost = null; $needed = 0;
      if ($nextId !== null) {
        foreach ($plans as $p) if ($p['id'] == $nextId) { $nextCost = $p['cost']; break; }
        if ($nextCost !== null && $newBal < $nextCost) $needed = intval(ceil($nextCost - $newBal));
      }

      echo json_encode(['status'=>'success', 'msg'=>'Auto-claimed', 'balance'=>$newBal, 'nextPlanId'=>$nextId, 'nextPlanCost'=>$nextCost, 'needed'=>$needed, 'xp'=>$xpRes['xp'] ?? null, 'level'=>$xpRes['level'] ?? null]);
      exit;
    } catch (Exception $e) {
      $db->rollback();
      error_log('Next auto-claim failed: '.$e->getMessage().' | DB error: '.$db->error);
      echo json_encode(['status'=>'error','msg'=>'Auto-claim failed']); exit;
    }
  }
  
  if ($action === 'invest' && $selectedPlan) {
    // Enforce single purchase per user per plan (either active or already claimed)
    $chkQ = $db->prepare("SELECT id FROM user_investments WHERE user_id = ? AND plan_id = ? AND status IN ('active','claimed') LIMIT 1");
    if ($chkQ === false) {
      error_log('Prepare failed (chk duplicate plan): ' . $db->error);
      echo json_encode(['status'=>'error','msg'=>'Server error']);
      exit;
    }
    $chkQ->bind_param("ii", $user_id, $planId);
    $chkQ->execute();
    $chkRes = $chkQ->get_result();
    if ($chkRes && $chkRes->num_rows > 0) {
      echo json_encode(['status'=>'error', 'msg'=>'You can take this plan only once']); exit;
    }
    // Auto-claim removed from invest flow: auto-claim only happens via 'next' action.

    // Begin transaction: perform the new investment atomically (claims already applied)
    $db->begin_transaction();
    try {
      $now = time();

      // Lock and fetch current balance to ensure atomic deduction
      $selBal = $db->prepare("SELECT balance, streak, last_invest_date FROM users WHERE id = ? FOR UPDATE");
      if ($selBal === false) throw new Exception('Server error');
      $selBal->bind_param("i", $user_id);
      $selBal->execute();
      $curBalResObj = $selBal->get_result();
      $curBalRes = ($curBalResObj) ? $curBalResObj->fetch_assoc() : ['balance' => 0];
      $curBal = floatval($curBalRes['balance'] ?? 0);

      // Validate balance before deducting cost
      if ($curBal < $selectedPlan['cost']) {
        $db->rollback();
        echo json_encode([
          'status' => 'insufficient',
          'balance' => $curBal,
          'required' => $selectedPlan['cost'],
          'needed' => intval(ceil($selectedPlan['cost'] - $curBal))
        ]);
        exit;
      }

      // Deduct cost and insert new active investment
      $newBal = $curBal - $selectedPlan['cost'];
      $upd = $db->prepare("UPDATE users SET balance = ? WHERE id = ?");
      if ($upd === false) throw new Exception('Server error');
      $upd->bind_param("di", $newBal, $user_id);
      if (!$upd->execute()) throw new Exception('Balance update failed');

      $startTime = $now;
      $durationSec = ($selectedPlan['unit'] == 'Min') ? ($selectedPlan['time'] * 60) : ($selectedPlan['time'] * 3600);
      $endTime = $startTime + $durationSec;
      $retAmt = $selectedPlan['total'];
      $ins = $db->prepare("INSERT INTO user_investments (user_id, plan_id, start_time, end_time, amount, return_amount, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
      if ($ins === false) throw new Exception('Server error');
      $ins->bind_param("iiiidd", $user_id, $planId, $startTime, $endTime, $selectedPlan['cost'], $retAmt);
      if (!$ins->execute()) throw new Exception('Insert failed');

      $db->commit();

      // Award XP for investing (post-commit)
      $xpRes = addXP($db, $user_id, 20);
      if ($xpRes && is_array($xpRes)) {
        addNotification($db, $user_id, 'Investment bonus: +20 XP', time());
      }

      $response = ['status'=>'success', 'msg'=>'Investment started!', 'balance'=>$newBal, 'xp'=>$xpRes['xp'] ?? null, 'level'=>$xpRes['level'] ?? null];
      echo json_encode($response);
      exit;
    } catch (Exception $e) {
      $db->rollback();
      // Log detailed error for debugging
      error_log('Transaction failed (invest): ' . $e->getMessage() . ' | DB error: ' . $db->error);
      echo json_encode(['status'=>'error', 'msg' => 'DB Error: ' . $e->getMessage()]);
      exit;
    }
  }
  
  if ($action === 'claim' && $selectedPlan) {
    $claimQ = $db->prepare("SELECT * FROM user_investments WHERE user_id = ? AND plan_id = ? AND status = 'active'");
    if ($claimQ === false) {
      error_log('Prepare failed (claim select): ' . $db->error);
      echo json_encode(['status'=>'error','msg'=>'Server error']); exit;
    }
    $claimQ->bind_param("ii", $user_id, $planId);
    $claimQ->execute();
    $res = $claimQ->get_result();
    if ($res->num_rows == 0) { 
      echo json_encode(['status'=>'error', 'msg'=>'No active plan']); exit;
    }
    $invData = $res->fetch_assoc();
    if (time() < $invData['end_time']) { 
      echo json_encode(['status'=>'error', 'msg'=>'Timer not finished']); exit;
    }
    $db->begin_transaction();
    try {
      // Lock user row to safely update balance and streak metadata
      $selBal = $db->prepare("SELECT balance, streak, last_invest_date FROM users WHERE id = ? FOR UPDATE");
      if ($selBal === false) {
        error_log('Prepare failed (claim select balance for update): ' . $db->error);
        echo json_encode(['status'=>'error','msg'=>'Server error']); exit;
      }
      $selBal->bind_param("i", $user_id);
      $selBal->execute();
      $curBalResObj = $selBal->get_result();
      $curBalRes = ($curBalResObj) ? $curBalResObj->fetch_assoc() : ['balance' => 0, 'streak' => 0, 'last_invest_date' => 0];
      $curBal = floatval($curBalRes['balance'] ?? 0);
      $curStreak = intval($curBalRes['streak'] ?? 0);
      $lastInvestDate = intval($curBalRes['last_invest_date'] ?? 0);

      $newBal = $curBal + floatval($invData['return_amount']);

      // update investment status
      $upInv = $db->prepare("UPDATE user_investments SET status = 'claimed' WHERE id = ?");
      if ($upInv === false) throw new Exception('Server error');
      $upInv->bind_param("i", $invData['id']);
      if (!$upInv->execute()) throw new Exception('Investment update failed');

      // Streak handling
      $today = strtotime('today');
      $yesterday = $today - 86400;
      if ($lastInvestDate >= $today) {
        // same day, do not increment
      } elseif ($lastInvestDate >= $yesterday) {
        $curStreak += 1;
      } else {
        $curStreak = 1;
      }
      $lastInvestDate = time();

      // apply streak bonuses
      if ($curStreak === 3) {
        $bonus = 100;
        $newBal += $bonus;
        $notifTitle = "Streak bonus: +₹" . number_format($bonus) . " (Day 3)";
        $nowTs = time();
        if (!addNotification($db, $user_id, $notifTitle, $nowTs)) {
          error_log('Notification insert failed (streak bonus day 3)');
          throw new Exception('Notification insert failed');
        }
      }
      if ($curStreak === 7) {
        $coupon = 'FREEINV' . strtoupper(substr(md5($user_id . time()), 0, 6));
        $notifTitle = "Streak reward: free invest coupon " . $coupon . " (Day 7)";
        $nowTs = time();
        if (!addNotification($db, $user_id, $notifTitle, $nowTs)) {
          error_log('Notification insert failed (streak reward day 7)');
          throw new Exception('Notification insert failed');
        }
      }

      // update user balance and meta
      $upUser = $db->prepare("UPDATE users SET balance = ?, streak = ?, last_invest_date = ? WHERE id = ?");
      if ($upUser === false) throw new Exception('Server error');
      $upUser->bind_param("diii", $newBal, $curStreak, $lastInvestDate, $user_id);
      if (!$upUser->execute()) throw new Exception('User update failed');

      // add claim notification
      $insN = $db->prepare("INSERT INTO notifications (user_id, title, created_at) VALUES (?, ?, ?)");
      if ($insN === false) throw new Exception('Server error');
      $title = "Investment #" . $invData['id'] . " claimed: +₹" . number_format($invData['return_amount']);
      $nowTs = time();
      $insN->bind_param("isi", $user_id, $title, $nowTs);
      if (!$insN->execute()) {
        error_log('Notification insert failed (claim): ' . $db->error . ' | errno: ' . $db->errno);
        throw new Exception('Notification failed');
      }

      $db->commit();

      // Award XP for claiming profit (post-commit)
      $xpRes = addXP($db, $user_id, 30);
      if ($xpRes && is_array($xpRes)) {
        $msg = 'Profit claimed: +30 XP';
        if (!empty($xpRes['levelsGained']) && $xpRes['levelsGained'] > 0) {
          $msg .= ' — Level up to ' . intval($xpRes['level']) . ' 🎉';
        }
        addNotification($db, $user_id, $msg, time());
      }

      echo json_encode(['status'=>'success', 'msg'=>'Profit Claimed!', 'balance'=>$newBal, 'xp'=>$xpRes['xp'] ?? null, 'level'=>$xpRes['level'] ?? null]);
      exit;
    } catch (Exception $e) {
      $db->rollback();
      // Log detailed error for debugging
      error_log('Transaction failed (claim): ' . $e->getMessage() . ' | DB error: ' . $db->error);
      echo json_encode(['status'=>'error', 'msg' => 'DB Error: ' . $e->getMessage()]);
      exit;
    }
  }
  echo json_encode(['status'=>'error', 'msg'=>'Invalid request']);
  exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="theme-color" content="#ff6b35" />
  <title>Trade Club Game - Premium Gaming Platform</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css" />
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    
    * {
      font-family: 'Poppins', sans-serif;
      -webkit-tap-highlight-color: transparent;
    }
    
   
    
  
    }
    
    .shooting-star::before {
      content: '';
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      width: 300px;
      height: 1px;
      background: linear-gradient(90deg, rgba(255,255,255,1), transparent);
    }
    
    @keyframes shooting {
      0% {
        transform: rotate(215deg) translateX(0);
        opacity: 1;
      }
      70% {
        opacity: 1;
      }
      100% {
        transform: rotate(215deg) translateX(-1000px);
        opacity: 0;
      }
    }
    
    .gradient-bg {
      background: linear-gradient(135deg, #ffffff 0%, #fff5f0 25%, #ffe8dc 50%, #ffd4c8 100%);
      position: relative;
      overflow-x: hidden;
      min-height: 100vh;
    }
    
    .glass-effect {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 2px solid rgba(255, 107, 53, 0.2);
      box-shadow: 0 12px 40px rgba(255, 107, 53, 0.2);
      z-index: 10;
      position: relative;
    }
    
    .btn-gradient {
      background: linear-gradient(135deg, #ff6b35 0%, #ff8c42 50%, #ffa552 100%);
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      position: relative;
      overflow: hidden;
      box-shadow: 0 4px 15px rgba(255, 107, 53, 0.4);
    }
    
    .btn-gradient:hover {
      transform: translateY(-3px);
      box-shadow: 0 15px 30px rgba(255, 107, 53, 0.6);
    }
    
    .btn-gradient:active {
      transform: translateY(0);
    }
    
    .btn-gradient::after {
      content: '';
      position: absolute;
      top: -50%;
      left: -60%;
      width: 200%;
      height: 200%;
      background: linear-gradient(
        to right,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 0.2) 50%,
        rgba(255, 255, 255, 0) 100%
      );
      transform: rotate(30deg);
      transition: all 0.7s ease;
    }
    
    .btn-gradient:hover::after {
      left: 100%;
    }
    
    .btn-premium {
      background: linear-gradient(135deg, #ff8c42 0%, #ffa552 50%, #ffb86d 100%);
      box-shadow: 0 4px 15px rgba(255, 140, 66, 0.4);
    }
    
    .btn-premium:hover {
      box-shadow: 0 15px 30px rgba(255, 140, 66, 0.6);
    }
    
    .btn-premium::after {
      background: linear-gradient(
        to right,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 0.3) 50%,
        rgba(255, 255, 255, 0) 100%
      );
    }
    
    .floating {
      animation: floating 6s ease-in-out infinite;
    }
    
    @keyframes floating {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-15px); }
    }
    
    .pulse-glow {
      animation: pulse-glow 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
    
    @keyframes pulse-glow {
      0%, 100% { box-shadow: 0 0 0 0 rgba(255, 107, 53, 0.5); }
      50% { box-shadow: 0 0 25px 15px rgba(255, 107, 53, 0); }
    }
    
    .text-gradient {
      background: linear-gradient(135deg, #ff6b35 0%, #ff8c42 100%);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }
    
    .logo-container {
      position: relative;
      width: 90px;
      height: 90px;
      margin: 0 auto;
    }
    
    .logo-container::before {
      content: '';
      position: absolute;
      inset: -5px;
      background: linear-gradient(135deg, #ff6b35, #ff8c42, #ffa552);
      border-radius: 20px;
      z-index: -1;
      filter: blur(10px);
      opacity: 0.7;
      animation: rotate-hue 6s linear infinite;
    }
    
    @keyframes rotate-hue {
      0% { filter: blur(10px) hue-rotate(0deg); }
      100% { filter: blur(10px) hue-rotate(360deg); }
    }
    
    .logo-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 16px;
      box-shadow: 0 8px 32px rgba(255, 107, 53, 0.4);
    }
    
    /* Slider styles */
    .slider {
      position: relative;
      width: 100%;
      height: 300px;
      overflow: hidden;
      border-radius: 16px;
    }
    
    .slide {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      opacity: 0;
      transition: opacity 1s ease-in-out;
      background-size: cover;
      background-position: center;
    }
    
    .slide.active {
      opacity: 1;
    }
    
    .slide-content {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      padding: 20px;
      background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
      color: white;
    }
    
    /* Transaction ticker */
    .ticker-container {
      overflow: hidden;
      position: relative;
      height: 50px;
    }
    
    .ticker {
      display: flex;
      position: absolute;
      white-space: nowrap;
      animation: ticker 30s linear infinite;
    }
    
    @keyframes ticker {
      0% { transform: translateX(100%); }
      100% { transform: translateX(-100%); }
    }
    
    .transaction-item {
      display: inline-flex;
      align-items: center;
      margin-right: 40px;
      padding: 8px 16px;
      border-radius: 20px;
      background: rgba(255, 107, 53, 0.1);
      border: 1px solid rgba(255, 107, 53, 0.2);
    }
    
    /* Game card styles */
    .game-card {
      transition: all 0.3s ease;
      transform-style: preserve-3d;
    }

    .game-card:hover {
      transform: translateY(-10px) scale(1.03);
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    /* Investment Card Styles */
    .invest-card {
      background: linear-gradient(135deg, #ffffff 0%, #fff8f4 100%);
      border: 2px solid rgba(255, 107, 53, 0.3);
      border-radius: 16px;
      overflow: hidden;
      position: relative;
      transition: all 0.3s ease;
      box-shadow: 0 8px 16px rgba(255, 107, 53, 0.15);
    }

    .invest-card:hover {
      transform: translateY(-5px) scale(1.02);
      border-color: rgba(255, 107, 53, 0.6);
      box-shadow: 0 15px 40px rgba(255, 107, 53, 0.3);
    }

    .card-locked {
      opacity: 0.6;
      filter: grayscale(0.8);
    }

    .cat-badge {
      background: rgba(255, 107, 53, 0.15);
      color: #ff6b35;
      font-size: 10px;
      padding: 4px 8px;
      border-radius: 6px;
      text-transform: uppercase;
      font-weight: 600;
    }

    /* Modal Styles */
    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.8);
      z-index: 100;
      display: flex;
      justify-content: center;
      align-items: flex-end;
    }

    .modal-content {
      background: #ffffff;
      width: 100%;
      border-top-left-radius: 20px;
      border-top-right-radius: 20px;
      padding: 24px;
      animation: slideUp 0.3s;
      border-top: 3px solid #ff6b35;
    }

    @keyframes slideUp {
      from { transform: translateY(100%); }
      to { transform: translateY(0); }
    }

    /* Winner card styles */
    .winner-card {
      position: relative;
      overflow: hidden;
    }
    
    .winner-card::after {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: linear-gradient(
        to bottom right,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 0.1) 50%,
        rgba(255, 255, 255, 0) 100%
      );
      transform: rotate(30deg);
      animation: shine 3s infinite;
    }
    
    @keyframes shine {
      0% { left: -100%; }
      20%, 100% { left: 100%; }
    }
    
    /* Payment method selector */
    .welcome-popup {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.8);
      z-index: 1000;
      display: flex;
      justify-content: center;
      align-items: center;
      backdrop-filter: blur(10px);
    }
    
    .welcome-content {
      max-width: 90%;
      width: 400px;
      border-radius: 20px;
      overflow: hidden;
      animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    
    @keyframes popIn {
      0% { transform: scale(0.8); opacity: 0; }
      100% { transform: scale(1); opacity: 1; }
    }
    
    /* Mobile bottom navigation */
    .mobile-nav {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      z-index: 50;
      display: none;
    }
    
    /* XP Progress Bar */
    .xp-progress {
      height: 6px;
      border-radius: 3px;
      background: rgba(255,255,255,0.1);
      overflow: hidden;
    }
    
    .xp-progress-fill {
      height: 100%;
      background: linear-gradient(90deg, #ff6b35, #ff8c42);
      border-radius: 3px;
      transition: width 0.5s ease;
    }
    
    /* ===== VIP PILL (UI MATCH) ===== */
    .vip-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 18px;
      font-size: 13px;
      font-weight: 600;
      border-radius: 16px;

      background: linear-gradient(
        135deg,
        rgba(255, 107, 53, 0.1),
        rgba(255, 107, 53, 0.05)
      );

      border: 1px solid rgba(255, 107, 53, 0.2);
      color: #1a1a1a;

      box-shadow:
        inset 0 0 0 1px rgba(255, 107, 53, 0.1),
        0 6px 18px rgba(255, 107, 53, 0.15);

      backdrop-filter: blur(8px);
    }

    /* ICON */
    .vip-pill i { font-size: 12px; opacity: 0.9; }

    /* LEVEL COLORS */
    .vip-pill.bronze { color: #f1c27d; }
    .vip-pill.silver { color: #e5e7eb; }
    .vip-pill.gold { color: #fde68a; }
    .vip-pill.platinum { color: #c7d2fe; }
    .vip-pill.diamond { color: #e9d5ff; }
    
    /* Notification Badge */
    .notification-badge {
      position: absolute;
      top: -5px;
      right: -5px;
      min-width: 20px;
      height: 20px;
      padding: 0 4px;
      background: #ef4444;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 10px;
      font-weight: bold;
      color: white;
    }
    
    /* Floating action button */
    .fab {
      position: fixed;
      bottom: 80px;
      right: 20px;
      width: 60px;
      height: 60px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 40;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
      transition: all 0.3s ease;
    }
    
    .fab:hover {
      transform: scale(1.1);
    }
    
    /* Activity feed item */
    .activity-item {
      position: relative;
      padding-left: 28px;
    }
    
    .activity-item::before {
      content: '';
      position: absolute;
      left: 8px;
      top: 0;
      bottom: 0;
      width: 2px;
      background: rgba(255,255,255,0.1);
    }
    
    .activity-item:last-child::before {
      display: none;
    }
    
    .activity-icon {
      position: absolute;
      left: 0;
      top: 0;
      width: 18px;
      height: 18px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
      .slider {
        height: 200px;
      }
      
      .mobile-nav {
        display: flex;
      }
      
      .desktop-only {
        display: none;
      }
    }
    
    /* Android-like touch feedback */
    .touch-feedback:active {
      transform: scale(0.98);
      opacity: 0.9;
    }
    
    /* Custom scrollbar */
    ::-webkit-scrollbar {
      width: 6px;
      height: 6px;
    }
    
    ::-webkit-scrollbar-track {
      background: rgba(255,255,255,0.05);
    }
    
    ::-webkit-scrollbar-thumb {
      background: rgba(255,255,255,0.1);
      border-radius: 3px;
    }
    
    ::-webkit-scrollbar-thumb:hover {
      background: rgba(255,255,255,0.2);
    }
   
     
    /* 3D Card Effect */
    .card-3d {
      transform-style: preserve-3d;
      transition: all 0.5s ease;
    }
    .card-3d:hover {
      transform: perspective(1000px) rotateX(5deg) rotateY(5deg) translateY(-10px);
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }
    
    .confetti {
      position: absolute;
      width: 10px;
      height: 10px;
      background-color: #f00;
      opacity: 0;
    }
    
    /* Premium support button */
    .fab-support {
      position: fixed;
      bottom: 90px;
      right: 20px;
      width: 56px;
      height: 56px;
      background: linear-gradient(135deg, #6a11cb, #2575fc);
      border-radius: 50%;
      box-shadow: 0 0 12px rgba(102, 153, 255, 0.7), 0 0 25px rgba(106, 17, 203, 0.8);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 999;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .fab-support:hover {
      transform: scale(1.1);
      box-shadow: 0 0 15px rgba(255, 255, 255, 0.8), 0 0 30px rgba(106, 17, 203, 1);
    }
    
    .fab-support i {
      color: white;
      font-size: 22px;
    }
    
    /* Bottom navigation */
    .bottom-nav {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      background: rgba(255, 255, 255, 0.98);
      border-top: 2px solid rgba(255, 107, 53, 0.2);
      display: flex;
      justify-content: space-around;
      padding: 12px 0 10px;
      z-index: 1000;
      backdrop-filter: blur(10px);
    }
    
    .nav-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-decoration: none;
      color: rgba(26, 26, 26, 0.6);
      font-size: 12px;
      transition: all 0.3s ease;
      position: relative;
      padding: 5px 15px;
      border-radius: 15px;
    }
    
    .nav-item i {
      font-size: 20px;
      margin-bottom: 4px;
      transition: all 0.3s ease;
    }
    
    .nav-item.active {
      color: #ff6b35;
      transform: translateY(-5px);
      background: rgba(255, 107, 53, 0.15);
    }
    
    .nav-item.active i {
      color: #ff6b35;
      text-shadow: 0 0 10px rgba(255, 107, 53, 0.5);
    }
    
    .nav-item:not(.active):hover {
      color: #ff6b35;
      background: rgba(255, 107, 53, 0.08);
    }
    
    .nav-item.active::after {
      content: '';
      position: absolute;
      bottom: -10px;
      left: 50%;
      transform: translateX(-50%);
      width: 5px;
      height: 5px;
      background: #8b5cf6;
      border-radius: 50%;
      box-shadow: 0 0 8px #8b5cf6;
    }
    
    /* Add space at bottom of page to prevent content hiding */
    body {
      padding-bottom: 70px;
    }
    
    /* Jackpot counter */
    .jackpot-counter {
      font-family: 'Poppins', sans-serif;
      font-weight: 700;
      background: linear-gradient(to right, #f59e0b, #f97316, #ef4444);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      text-shadow: 0 0 10px rgba(245, 158, 11, 0.3);
    }
    
    /* Tournament card */

    
    /* Streak counter */
    .streak-counter {
      position: relative;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    
    .streak-counter::before {
      content: '';
      position: absolute;
      width: 100%;
      height: 100%;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(245,158,11,0.4) 0%, rgba(245,158,11,0) 70%);
      animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
      0% { transform: scale(0.8); opacity: 0.8; }
      70% { transform: scale(1.1); opacity: 0.3; }
      100% { transform: scale(0.8); opacity: 0.8; }
    }
    
    /* Swiper custom styles */
    .swiper-slide {
      display: flex;
      justify-content: center;
      align-items: center;
    }
    
    .swiper-pagination-bullet {
      background: rgba(255,255,255,0.5);
      opacity: 1;
    }
    
    .swiper-pagination-bullet-active {
      background: #8b5cf6;
    }
    
    /* Payment method selector */
    .payment-method {
      transition: all 0.3s ease;
      border: 1px solid transparent;
    }
    
    .payment-method:hover {
      transform: translateY(-3px);
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .payment-method.selected {
      border-color: #8b5cf6;
      box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.3);
    }
    
    /* Referral code input */
    .referral-input {
      position: relative;
    }
    
    .referral-input input {
      padding-right: 100px;
    }
    
    .referral-code {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      background: rgba(255,255,255,0.1);
      padding: 2px 8px;
      border-radius: 4px;
      font-size: 12px;
      color: #8b5cf6;
    }
    
    /* Animated background for premium sections */
    .premium-bg {
      position: relative;
      overflow: hidden;
    }
    
    .premium-bg::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: linear-gradient(
        to bottom right,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 0.05) 50%,
        rgba(255, 255, 255, 0) 100%
      );
      transform: rotate(30deg);
      animation: shine 6s infinite;
    }
    
    /* Floating coins animation */
    .floating-coins {
      position: absolute;
      width: 100%;
      height: 100%;
      top: 0;
      left: 0;
      pointer-events: none;
      z-index: -1;
    }
    
    .coin {
      position: absolute;
      width: 20px;
      height: 20px;
      background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23f59e0b"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6" fill="%23fcd34d"/></svg>');
      background-size: contain;
      opacity: 0.6;
      animation: float-up 10s linear infinite;
    }
    
    @keyframes float-up {
      0% {
        transform: translateY(100vh) rotate(0deg);
        opacity: 0;
      }
      10% {
        opacity: 0.6;
      }
      90% {
        opacity: 0.6;
      }
      100% {
        transform: translateY(-100px) rotate(360deg);
        opacity: 0;
      }
    }
    
    /* Neon text effect */
    .neon-text {
      text-shadow: 0 0 5px #fff, 0 0 10px #fff, 0 0 15px #8b5cf6, 0 0 20px #8b5cf6;
      animation: flicker 1.5s infinite alternate;
    }
    
    @keyframes flicker {
      0%, 19%, 21%, 23%, 25%, 54%, 56%, 100% {
        text-shadow: 0 0 5px #fff, 0 0 10px #fff, 0 0 15px #8b5cf6, 0 0 20px #8b5cf6;
      }
      20%, 24%, 55% {        
        text-shadow: none;
      }
    }
    
    /* Gradient border */
    .gradient-border {
      position: relative;
      border-radius: 16px;
    }
    
    .gradient-border::before {
      content: '';
      position: absolute;
      top: -2px;
      left: -2px;
      right: -2px;
      bottom: -2px;
      background: linear-gradient(45deg, #6366f1, #8b5cf6, #ec4899);
      border-radius: 18px;
      z-index: -1;
      opacity: 0.7;
    }
    
    /* Animated button */
    .animated-button {
      position: relative;
      overflow: hidden;
    }
    
    .animated-button::after {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: linear-gradient(
        to bottom right,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 0.2) 50%,
        rgba(255, 255, 255, 0) 100%
      );
      transform: rotate(30deg);
      animation: shine 3s infinite;
    }
    
    /* Custom checkbox */
    .custom-checkbox {
      position: relative;
      width: 20px;
      height: 20px;
      appearance: none;
      -webkit-appearance: none;
      background: rgba(255,255,255,0.1);
      border-radius: 4px;
      cursor: pointer;
    }
    
    .custom-checkbox:checked {
      background: #8b5cf6;
    }
    
    .custom-checkbox:checked::after {
      content: '✓';
      position: absolute;
      color: white;
      font-size: 14px;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
    }
    
    /* Tooltip */
    .tooltip {
      position: relative;
    }
    
    .tooltip:hover .tooltip-text {
      visibility: visible;
      opacity: 1;
      transform: translateY(0);
    }
    
    .tooltip-text {
      visibility: hidden;
      opacity: 0;
      position: absolute;
      z-index: 100;
      bottom: 125%;
      left: 50%;
      transform: translateX(-50%) translateY(10px);
      background: rgba(30,41,59,0.95);
      border: 1px solid rgba(255,255,255,0.1);
      color: white;
      padding: 5px 10px;
      border-radius: 6px;
      font-size: 12px;
      white-space: nowrap;
      transition: all 0.2s ease;
      pointer-events: none;
    }
    
    .tooltip-text::after {
      content: '';
      position: absolute;
      top: 100%;
      left: 50%;
      transform: translateX(-50%);
      border-width: 5px;
      border-style: solid;
      border-color: rgba(30,41,59,0.95) transparent transparent transparent;
    }

/* ================= MOBILE OPTIMIZATION ================= */
@media (max-width: 480px) {

  body {
    font-size: 14px;
  }

  .container {
    padding-left: 12px !important;
    padding-right: 12px !important;
  }

  /* prevent horizontal scroll */
  html, body {
    overflow-x: hidden;
  }

  /* cards compact */
  .glass-effect {
    padding: 12px !important;
  }

  .card-3d:hover {
    transform: none !important;
  }

  /* headings */
  h1 { font-size: 18px; }
  h2 { font-size: 16px; }
  h3 { font-size: 15px; }

  /* hero slider image height */
  .hero-card img {
    height: 140px;
    border-radius: 12px;
  }

  /* daily streak smaller circles */
  .daily-streak div {
    width: 22px !important;
    height: 22px !important;
    font-size: 11px !important;
  }

  /* invest cards compact */
  .invest-card {
    padding: 14px !important;
  }
  .invest-card h3 { font-size: 26px !important; }
  .invest-card button { padding: 12px !important; font-size: 14px !important; }

  /* modal bottom sheet look */
  .modal-content {
    border-radius: 20px 20px 0 0;
    padding: 18px;
  }

  .bottom-nav {
    padding-bottom: env(safe-area-inset-bottom);
  }

}

/* Prevent horizontal scroll globally */
html, body {
  width: 100%;
  overflow-x: hidden;
}

/* Header safe-area for notch phones */
header.glass-effect {
  padding-top: env(safe-area-inset-top);
}

/* Disable heavy hover/3D effects on touch devices */
@media (hover: none) {
  .card-3d:hover,
  .invest-card:hover,
  .btn-gradient:hover {
    transform: none !important;
    box-shadow: none !important;
  }
}

/* Invest card compact mobile tweaks (extra) */
@media (max-width: 480px) {
  .invest-card {
    padding: 14px !important;
    border-radius: 14px;
  }
  .invest-card h3 { font-size: 24px !important; }
  .invest-card button { font-size: 14px !important; padding: 12px !important; }
}

/* Modal max-height for bottom-sheet style */
.modal-content {
  max-height: 90vh;
  overflow-y: auto;
}

/* Bottom nav safe area */
.bottom-nav { padding-bottom: env(safe-area-inset-bottom); }

/* Toast position above bottom-nav */
.toast { bottom: 90px !important; }

/* Disable heavy hover effects on touch devices */
@media (hover: none) {
  .hover\:shadow-2xl:hover,
  .card-3d:hover,
  .invest-card:hover {
    transform: none !important;
    box-shadow: none !important;
  }
}

/* ===== MEMBER BADGE (UI MATCH FIX) ===== */
.member-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: .2px;
  backdrop-filter: blur(6px);
  border: 1px solid rgba(255,255,255,0.08);
  box-shadow: inset 0 0 0 1px rgba(255,255,255,0.03);
}

.member-badge i { font-size: 13px; }

.member-badge.bronze {
  color: #ffd7a1;
  background: linear-gradient(135deg,#2a1f17,#3b2a1c);
  box-shadow: 0 0 12px rgba(255,165,0,0.25), inset 0 0 12px rgba(255,165,0,0.15);
}

.member-badge.bronze i { color: #ffb703; }

.member-badge.silver {
  color: #e5e7eb;
  background: linear-gradient(135deg,#1f2933,#374151);
  box-shadow: 0 0 12px rgba(180,180,180,0.25), inset 0 0 12px rgba(255,255,255,0.1);
}

.member-badge.gold {
  color: #fff2cc;
  background: linear-gradient(135deg,#3b2f1b,#5a431e);
  box-shadow: 0 0 14px rgba(255,193,7,0.45), inset 0 0 14px rgba(255,193,7,0.2);
}

.member-badge.gold i { color: #ffd700; }
  </style>
<!-- Hide member badge globally -->
<style>
.member-badge { display: none !important; }
</style>
</head>
<body class="gradient-bg min-h-screen overflow-x-hidden">
  
  
  <!-- Welcome Popup -->
  <?php if($showWelcomePopup): ?>
  <div class="welcome-popup animate__animated animate__fadeIn">
    <div class="welcome-content glass-effect">
      <div class="relative">
        <img src="https://img.freepik.com/premium-photo/neon-welcome-lettering-textured-background_317169-2142.jpg" alt="Welcome to Trade Club" class="w-full h-40 object-cover">
        <div class="absolute top-0 left-0 right-0 bottom-0 bg-gradient-to-t from-black to-transparent"></div>
        <div class="absolute bottom-4 left-4">
          <h2 class="text-2xl font-bold text-white">Welcome to Trade Club!</h2>
          <p class="text-white/80">Claim your welcome bonus now</p>
        </div>
      </div>
      <div class="p-6">
        <p class="text-gray-300 mb-4">Join thousands of players winning big every day. Start with a 200% bonus on your first deposit!</p>
        <div class="flex space-x-3">
          <button onclick="closeWelcomePopup()" class="flex-1 bg-white/10 hover:bg-white/20 text-white px-4 py-2 rounded-lg transition-colors touch-feedback">
            Maybe Later
          </button>
          <button onclick="closeWelcomePopup(); claimWelcomeBonus();" class="flex-1 btn-gradient px-4 py-2 rounded-lg text-white font-medium touch-feedback">
            Claim Bonus
          </button>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <?php if(isset($_GET['msg']) || isset($_GET['error']) || isset($_GET['success'])): ?>
  <div id="toast" class="fixed top-6 right-6 z-50">
    <div class="px-4 py-3 rounded-lg glass-effect shadow-lg text-sm text-white">
      <?php if(isset($_GET['msg'])): echo htmlspecialchars($_GET['msg']); endif; ?>
      <?php if(isset($_GET['error'])): echo htmlspecialchars($_GET['error']); endif; ?>
      <?php if(isset($_GET['success'])): echo ($_GET['success']==1)? 'Investment started':'Claim successful'; endif; ?>
    </div>
  </div>
  <script>setTimeout(()=>{ const t=document.getElementById('toast'); if(t) t.remove(); },4000);</script>
  <?php endif; ?>
  
  <!-- Header/Navbar -->
  <header class="glass-effect fixed top-0 left-0 w-full z-50">
    <div class="container mx-auto px-4 py-2 flex justify-between items-center">
      <div class="flex items-center space-x-2">
       
        <h1 class="text-lg font-bold text-white"><span class="text-gradient">Trade</span> Club</h1>
      </div>
      
      <div class="flex items-center space-x-4">

        <!-- 🔔 NOTIFICATION (now at profile position) -->
        <div class="relative">
          <button onclick="openNotifPopup()"
            class="w-10 h-10 flex items-center justify-center rounded-full bg-white/5 hover:bg-white/10 transition-colors touch-feedback">
            <i class="fas fa-bell text-white"></i>

            <?php if($unreadNotifications > 0): ?>
              <span class="notification-badge"><?= $unreadNotifications ?></span>
            <?php endif; ?>
          </button>

          <!-- Notifications dropdown -->
          <div id="notificationsDropdown"
            class="hidden absolute right-0 mt-2 w-72 bg-gray-800 rounded-lg shadow-xl z-50 border border-gray-700">
            
            <div class="p-3 border-b border-gray-700 flex justify-between items-center">
              <h3 class="font-semibold text-white">Notifications</h3>
              <button class="text-xs text-indigo-400 hover:text-indigo-300">
                Mark all as read
              </button>
            </div>

            <div class="max-h-60 overflow-y-auto">
              <?php if (count($notifications) === 0): ?>
                <div class="p-3 text-sm text-gray-400">No notifications</div>
              <?php else: ?>
                <?php foreach($notifications as $n): ?>
                  <div class="p-3 hover:bg-gray-700/50 transition-colors border-b border-gray-700/50 <?php if(!$n['seen']) echo 'bg-gray-700/20'; ?>">
                    <p class="text-sm text-white"><?= htmlspecialchars($n['title']) ?></p>
                    <p class="text-xs text-gray-400 mt-1"><?= date('d M, H:i', $n['created_at']) ?></p>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

      </div>
    </div>
  </header>

  <main class="relative z-10 pt-20 pb-20">
      
      
       <!-- Hero Slider (ARWallet-style right card slider) -->
<style>
  .hero-card-slider {
    width: 100%;
    overflow: hidden;
    position: relative;
  }

  .hero-card-track {
    display: flex;
    transition: transform 0.5s ease;
  }

  .hero-card {
    flex: 0 0 100%;
    padding: 0 10px; /* mobile spacing */
    box-sizing: border-box;
  }

  .hero-card img {
    width: 100%;
    height: 140px;
    border-radius: 12px;
    object-fit: cover;
    display: block;
  }

  @media (min-width: 768px) {
    .hero-card { padding: 0 0; }
  }
}</style>

<style>
  .hero-dots{ display:flex; justify-content:center; gap:6px; margin-top:8px; }
  .hero-dot{ width:6px; height:6px; border-radius:50%; background:#94a3b8; opacity:.5; }
  .hero-dot.active{ background:#8b5cf6; opacity:1; }
</style>

<style>
  /* Hide hero slider and dots on desktop screens (>=1024px) */
  @media (min-width: 1024px) {
    .hero-card-slider, .hero-dots { display: none !important; }
  }
</style>

<section class="container mx-auto px-4 py-6">
  <div class="hero-card-slider">
    <div class="hero-card-track">

      <div class="hero-card" onclick="window.location.href='deposit.php'" style="cursor: pointer;">
        <img src="https://i.ibb.co/QvyyrXy9/AWBANNER15.png" alt="slide-1">
      </div>

      <div class="hero-card">
        <img src="https://i.ibb.co/Qvsf0WdN/PHOTO-2026-01-31-14-07-33.jpg" alt="slide-2">
      </div>
      
        <div class="hero-card" onclick="storeBackURL(); window.location.href='refer.php'" style="cursor: pointer;">
        <img src="https://i.ibb.co/Wv539DK5/PHOTO-2026-01-31-14-21-41.jpg" alt="slide-3">
      </div>

      <div class="hero-card">
        <img src="https://i.ibb.co/Yr2hHrP/PHOTO-2026-01-31-14-11-46.jpg" alt="slide-4">
      </div>

      <div class="hero-card" onclick="storeBackURL(); window.location.href='user-agreement.php'" style="cursor: pointer;">
        <img src="https://arb-new-pay.oss-ap-southeast-1.aliyuncs.com/vc-upload-1714420019523-2.png" alt="slide-5">
      </div>

    </div>
  </div>

  <div class="hero-dots" id="heroDots"></div>
</section>

<script>
// Store current page as back reference
function storeBackURL() {
  localStorage.setItem('backURL', window.location.href);
}

// Go back to stored URL
function goBack() {
  const backURL = localStorage.getItem('backURL');
  if (backURL) {
    localStorage.removeItem('backURL');
    window.location.href = backURL;
  } else {
    window.history.back();
  }
}

(function () {
  function initHeroCardSlider() {
    const track = document.querySelector('.hero-card-track');
    const cards = document.querySelectorAll('.hero-card');
    const dotsContainer = document.getElementById('heroDots');
    const slider = document.querySelector('.hero-card-slider');

    if (!track || cards.length === 0 || !dotsContainer) return;

    let index = 0;
    let autoPlayInterval = null;
    let touchStartX = 0;
    let touchEndX = 0;

    // create dots
    cards.forEach((_, i) => {
      const dot = document.createElement('span');
      dot.className = 'hero-dot' + (i === 0 ? ' active' : '');
      dot.style.cursor = 'pointer';
      dot.addEventListener('click', () => goToSlide(i));
      dotsContainer.appendChild(dot);
    });

    const dots = dotsContainer.querySelectorAll('.hero-dot');

    function updateSlider() {
      track.style.transform = `translateX(-${index * 100}%)`;
      dots.forEach(d => d.classList.remove('active'));
      dots[index].classList.add('active');
    }

    function goToSlide(i) {
      index = i;
      updateSlider();
      resetAutoPlay();
    }

    function nextSlide() {
      index = (index + 1) % cards.length;
      updateSlider();
    }

    function prevSlide() {
      index = (index - 1 + cards.length) % cards.length;
      updateSlider();
    }

    function startAutoPlay() {
      autoPlayInterval = setInterval(() => {
        nextSlide();
      }, 3500);
    }

    function resetAutoPlay() {
      clearInterval(autoPlayInterval);
      startAutoPlay();
    }

    // Touch swipe events
    slider.addEventListener('touchstart', (e) => {
      touchStartX = e.changedTouches[0].screenX;
      clearInterval(autoPlayInterval);
    }, false);

    slider.addEventListener('touchend', (e) => {
      touchEndX = e.changedTouches[0].screenX;
      handleSwipe();
      resetAutoPlay();
    }, false);

    function handleSwipe() {
      const swipeThreshold = 50; // minimum distance for swipe
      const diff = touchStartX - touchEndX;

      if (Math.abs(diff) > swipeThreshold) {
        if (diff > 0) {
          // Swiped left - next slide
          nextSlide();
        } else {
          // Swiped right - previous slide
          prevSlide();
        }
      }
    }

    // Start auto play
    startAutoPlay();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHeroCardSlider);
  } else {
    initHeroCardSlider();
  }
})();
</script>
    
    <!-- User Stats Section -->
    <section class="container mx-auto px-4 pt-6">
      <div class="glass-effect rounded-xl p-4 card-3d">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h2 class="text-xl font-bold text-white">Welcome back, <?= $username ?></h2>
            <p class="text-sm text-gray-400">Invest Smart Grow Wealth</p>
          </div>
          <?php
            $badgeClass = 'bronze';
            $badgeText  = 'Bronze Member';
            if ($vipLevel >= 3) { $badgeClass = 'silver'; $badgeText = 'Silver Member'; }
            if ($vipLevel >= 5) { $badgeClass = 'gold'; $badgeText = 'Gold Member'; }
          ?>
          <?php if($vipLevel >= 1): ?>
            <div class="member-badge <?= $badgeClass ?>">
              <i class="fas fa-crown"></i>
              <span><?= $badgeText ?></span>
            </div>
          <?php else: ?>
            <button onclick="showVipModal()" class="bg-white/5 hover:bg-white/10 px-3 py-1 rounded-full text-xs font-semibold flex items-center transition-colors">
              <i class="fas fa-gem mr-1 text-purple-400"></i> UPGRADE
            </button>
          <?php endif; ?>
        </div>
        
       <div class="flex items-center justify-between mb-3">
  <div class="flex items-center space-x-2">
    <i class="fas fa-coins text-yellow-400"></i>
    <span id="walletBalanceBig" class="text-white font-bold">₹<?= number_format($userBalance) ?></span>
  </div>
  
  <button onclick="window.location.href='deposit.php'" class="btn-gradient px-4 py-1 rounded-full text-sm font-medium touch-feedback">
    + Deposit
  </button>
</div>
        
        <div class="mb-2">
          <?php
          $xpInLevel = $userXP % 100;      // level ke andar ka XP
          $xpPercent = ($xpInLevel / 100) * 100;
          ?>
          <div class="flex justify-between text-xs text-gray-400 mb-1">
            <span>Level <?= $userLevel ?></span>
            <span><?= $xpInLevel ?> / 100 XP</span>
          </div>
          <div class="xp-progress">
            <div class="xp-progress-fill" style="width: <?= $xpPercent ?>%"></div>
          </div>
        </div>
        
        <!-- Daily streak -->
        <div class="mt-4 flex items-center justify-between">
          <div class="flex items-center space-x-2">
            <i class="fas fa-fire text-orange-400"></i>
            <span class="text-sm text-white">Daily Streak</span>
          </div>
          <div class="flex items-center space-x-1 daily-streak">
            <?php for($i = 0; $i < 7; $i++): ?>
              <div class="w-6 h-6 rounded-full flex items-center justify-center <?= $i < $dailyStreak ? 'bg-orange-500 text-white' : 'bg-white/5 text-white/30' ?> text-xs">
                <?= $i+1 ?>
              </div>
            <?php endfor; ?>
          </div>
        </div>
        
        <!-- Investment Analytics Cards (compact) -->
        <div class="mt-4 grid grid-cols-2 md:grid-cols-3 gap-2">
          <!-- Total Invested -->
          <div class="glass-effect px-3 py-2 rounded-lg flex items-center justify-between">
            <div>
              <p class="text-[11px] text-gray-400">Total Invested</p>
              <p class="text-lg font-semibold text-white">₹<?= number_format($analytics['total_invested']) ?></p>
            </div>
            <i class="fas fa-chart-line text-indigo-400 text-lg"></i>
          </div>

          <!-- Total Profit -->
          <div class="glass-effect px-3 py-2 rounded-lg flex items-center justify-between">
            <div>
              <p class="text-[11px] text-gray-400">Total Profit</p>
              <p class="text-lg font-semibold text-green-400">₹<?= number_format($analytics['total_profit']) ?></p>
            </div>
            <i class="fas fa-arrow-trend-up text-green-400 text-lg"></i>
          </div>

          <!-- Active Investments -->
          <div class="glass-effect px-3 py-2 rounded-lg flex items-center justify-between col-span-2 md:col-span-1">
            <div>
              <p class="text-[11px] text-gray-400">Active</p>
              <p class="text-lg font-semibold text-white">
                <?= $analytics['active_count'] ?> <span class="text-xs text-gray-400">(<?= $analytics['roi_percent'] ?>% ROI)</span>
              </p>
            </div>
            <i class="fas fa-bolt text-yellow-400 text-lg"></i>
          </div>
        </div>
      </div>
    </section>



    <!-- Investment Packages / Active Plans -->
    <section id="invest-section" class="container mx-auto px-4 py-6">
      
      <!-- Plan Category Tabs -->
      <div class="flex gap-2 mb-4 overflow-x-auto pb-2">
        <button onclick="switchPlanCategory('small')" id="tab-small" class="px-4 py-2 rounded-lg font-bold text-xs sm:text-sm whitespace-nowrap transition-all tab-btn active" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white;">
          <i class="fas fa-bolt mr-1"></i>Small Cap 
        </button>
        <button onclick="switchPlanCategory('mid')" id="tab-mid" class="px-4 py-2 rounded-lg font-bold text-xs sm:text-sm whitespace-nowrap transition-all tab-btn" style="background: rgba(255,255,255,0.1); color: #9ca3af;">
          <i class="fas fa-chart-line mr-1"></i>Mid Cap
        </button>
        <button onclick="switchPlanCategory('high')" id="tab-high" class="px-4 py-2 rounded-lg font-bold text-xs sm:text-sm whitespace-nowrap transition-all tab-btn" style="background: rgba(255,255,255,0.1); color: #9ca3af;">
          <i class="fas fa-crown mr-1"></i>High Cap
        </button>
      </div>

      <div class="flex justify-between items-end mb-4">
        <h2 class="text-xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 via-blue-500 to-purple-600 animate-pulse">💎 Active Plans</h2>
        <span class="text-xs text-white bg-gradient-to-r from-green-500 to-emerald-600 px-3 py-1.5 rounded-lg font-bold shadow-lg shadow-green-500/50">✨ Fixed 35% ROI ✨</span>
      </div>

      <script>
        function switchPlanCategory(category) {
          // Update tabs
          document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.style.background = 'rgba(255,255,255,0.1)';
            btn.style.color = '#9ca3af';
          });
          document.getElementById('tab-' + category).style.background = 'linear-gradient(135deg, #6366f1, #8b5cf6)';
          document.getElementById('tab-' + category).style.color = 'white';

          // Update plan cards
          document.querySelectorAll('[data-plan-category]').forEach(card => {
            card.style.display = card.getAttribute('data-plan-category') === category ? '' : 'none';
          });
        }
      </script>

      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 sm:gap-3">
        <?php
          // Categorize plans by duration
          function getPlanDurationHours($time, $unit) {
            $hours = intval($time);
            if (strtolower($unit) === 'min') $hours = $hours / 60;
            elseif (strtolower($unit) === 'day') $hours = $hours * 24;
            return $hours;
          }

          $smallCapPlans = [];
          $midCapPlans = [];
          $highCapPlans = [];

          foreach($plans as $plan) {
            $hours = getPlanDurationHours($plan['time'], $plan['unit']);
            if ($hours < 24) $smallCapPlans[] = $plan;
            elseif ($hours >= 25 && $hours <= 60) $midCapPlans[] = $plan;
            else $highCapPlans[] = $plan;
          }

          $allCategorizedPlans = [
            'small' => $smallCapPlans,
            'mid' => $midCapPlans,
            'high' => $highCapPlans
          ];

          $renderedPlans = [];
          foreach($allCategorizedPlans['small'] as $plan): 
            // Prevent duplicate rendering of the same plan ID
            if (in_array($plan['id'], $renderedPlans)) continue;
            $renderedPlans[] = $plan['id'];
          $status = getPlanStatus($plan['id'], $activeInvs, $usedPlans);
          $end = getEndTime($plan['id'], $activeInvs);
          // Prepare next plan metadata for client-side updates (used when converting running -> claim state)
          $nextId = getNextPlanId($plan['id'], $plans);
          $nextPlan = null;
          if ($nextId) {
            foreach ($plans as $p) {
              if ($p['id'] == $nextId) { $nextPlan = $p; break; }
            }
          }
        ?>
        <div class="relative p-3 rounded-lg border border-indigo-500/50 bg-gradient-to-br from-indigo-900/30 to-purple-900/20 shadow-lg hover:shadow-xl transition-all <?= ($status == 'locked') ? 'opacity-50' : '' ?>" data-plan-category="small">
          <div class="flex justify-between items-start mb-2">
            <div>
              <p class="text-sm font-bold text-white">₹<?= number_format($plan['cost']) ?></p>
              <p class="text-xs text-gray-400"><?= $plan['time'] ?> <?= $plan['unit'] ?></p>
            </div>
            <span class="text-xs bg-indigo-500 px-2 py-1 rounded-full text-white font-bold">+<?= intval(($plan['profit']/$plan['cost'])*100) ?>%</span>
          </div>
          <div class="text-xs text-gray-300 mb-2">Return: ₹<?= number_format($plan['total']) ?></div>
          <?php if ($status == 'locked'): ?>
            <button class="w-full py-2 bg-red-500/20 text-red-300 font-bold rounded-lg text-xs border border-red-500/30 cursor-not-allowed" disabled>Completed</button>
          <?php elseif ($status == 'running'): ?>
            <button class="w-full py-2 bg-blue-500/30 text-blue-200 font-bold rounded-lg text-xs flex items-center justify-center gap-1">
              <i class="fas fa-clock animate-spin"></i>
              <span class="countdown" data-end="<?= $end ?>" data-plan-id="<?= $plan['id'] ?>" data-total="<?= $plan['total'] ?>">...</span>
            </button>
          <?php elseif ($status == 'claim'): ?>
            <button type="button" onclick="claimProfit(<?= $plan['id'] ?>)" class="w-full py-2 bg-green-500/30 text-green-200 font-bold rounded-lg text-xs">Claim Now</button>
          <?php else: ?>
            <button type="button" onclick="openModal(<?= htmlspecialchars(json_encode($plan)) ?>)" class="w-full py-2 bg-indigo-500 hover:bg-indigo-600 text-white font-bold rounded-lg text-xs">INVEST</button>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <!-- Mid Cap Plans -->
        <?php
          foreach($allCategorizedPlans['mid'] as $plan):
            if (in_array($plan['id'], $renderedPlans)) continue;
            $renderedPlans[] = $plan['id'];
            $status = getPlanStatus($plan['id'], $activeInvs, $usedPlans);
            $end = getEndTime($plan['id'], $activeInvs);
            $nextId = getNextPlanId($plan['id'], $plans);
            $nextPlan = null;
            if ($nextId) {
              foreach ($plans as $p) {
                if ($p['id'] == $nextId) { $nextPlan = $p; break; }
              }
            }
        ?>
        <div class="relative p-3 rounded-lg border border-amber-500/50 bg-gradient-to-br from-amber-900/30 to-orange-900/20 shadow-lg hover:shadow-xl transition-all <?= ($status == 'locked') ? 'opacity-50' : '' ?>" data-plan-category="mid" style="display:none;">
          <div class="flex justify-between items-start mb-2">
            <div>
              <p class="text-sm font-bold text-white">₹<?= number_format($plan['cost']) ?></p>
              <p class="text-xs text-gray-400"><?= $plan['time'] ?> <?= $plan['unit'] ?></p>
            </div>
            <span class="text-xs bg-amber-500 px-2 py-1 rounded-full text-white font-bold">+<?= intval(($plan['profit']/$plan['cost'])*100) ?>%</span>
          </div>
          <div class="text-xs text-gray-300 mb-2">Return: ₹<?= number_format($plan['total']) ?></div>
          <?php if ($status == 'locked'): ?>
            <button class="w-full py-2 bg-red-500/20 text-red-300 font-bold rounded-lg text-xs border border-red-500/30 cursor-not-allowed" disabled>Completed</button>
          <?php elseif ($status == 'running'): ?>
            <button class="w-full py-2 bg-blue-500/30 text-blue-200 font-bold rounded-lg text-xs flex items-center justify-center gap-1">
              <i class="fas fa-clock animate-spin"></i>
              <span class="countdown" data-end="<?= $end ?>" data-plan-id="<?= $plan['id'] ?>" data-total="<?= $plan['total'] ?>">...</span>
            </button>
          <?php elseif ($status == 'claim'): ?>
            <button type="button" onclick="claimProfit(<?= $plan['id'] ?>)" class="w-full py-2 bg-green-500/30 text-green-200 font-bold rounded-lg text-xs">Claim Now</button>
          <?php else: ?>
            <button type="button" onclick="openModal(<?= htmlspecialchars(json_encode($plan)) ?>)" class="w-full py-2 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-lg text-xs">INVEST</button>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <!-- High Cap Plans -->
        <?php
          foreach($allCategorizedPlans['high'] as $plan):
            if (in_array($plan['id'], $renderedPlans)) continue;
            $renderedPlans[] = $plan['id'];
            $status = getPlanStatus($plan['id'], $activeInvs, $usedPlans);
            $end = getEndTime($plan['id'], $activeInvs);
            $nextId = getNextPlanId($plan['id'], $plans);
            $nextPlan = null;
            if ($nextId) {
              foreach ($plans as $p) {
                if ($p['id'] == $nextId) { $nextPlan = $p; break; }
              }
            }
        ?>
        <div class="relative p-3 rounded-lg border border-rose-500/50 bg-gradient-to-br from-rose-900/30 to-pink-900/20 shadow-lg hover:shadow-xl transition-all <?= ($status == 'locked') ? 'opacity-50' : '' ?>" data-plan-category="high" style="display:none;">
          <div class="flex justify-between items-start mb-2">
            <div>
              <p class="text-sm font-bold text-white">₹<?= number_format($plan['cost']) ?></p>
              <p class="text-xs text-gray-400"><?= $plan['time'] ?> <?= $plan['unit'] ?></p>
            </div>
            <span class="text-xs bg-rose-500 px-2 py-1 rounded-full text-white font-bold">+<?= intval(($plan['profit']/$plan['cost'])*100) ?>%</span>
          </div>
          <div class="text-xs text-gray-300 mb-2">Return: ₹<?= number_format($plan['total']) ?></div>
          <?php if ($status == 'locked'): ?>
            <button class="w-full py-2 bg-red-500/20 text-red-300 font-bold rounded-lg text-xs border border-red-500/30 cursor-not-allowed" disabled>Completed</button>
          <?php elseif ($status == 'running'): ?>
            <button class="w-full py-2 bg-blue-500/30 text-blue-200 font-bold rounded-lg text-xs flex items-center justify-center gap-1">
              <i class="fas fa-clock animate-spin"></i>
              <span class="countdown" data-end="<?= $end ?>" data-plan-id="<?= $plan['id'] ?>" data-total="<?= $plan['total'] ?>">...</span>
            </button>
          <?php elseif ($status == 'claim'): ?>
            <button type="button" onclick="claimProfit(<?= $plan['id'] ?>)" class="w-full py-2 bg-green-500/30 text-green-200 font-bold rounded-lg text-xs">Claim Now</button>
          <?php else: ?>
            <button type="button" onclick="openModal(<?= htmlspecialchars(json_encode($plan)) ?>)" class="w-full py-2 bg-rose-500 hover:bg-rose-600 text-white font-bold rounded-lg text-xs">INVEST</button>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Completed Plans / Investment History (Premium UI) -->
      <?php if (count($completedInvestments) > 0): ?>

      <style>
      /* ===== INVESTMENT HISTORY PREMIUM ===== */
      .history-card{
        background: linear-gradient(135deg,#0f172a,#1e293b);
        border: 1px solid rgba(255,255,255,0.08);
        border-left: 4px solid #22c55e;
        border-radius: 14px;
        padding: 14px;
        box-shadow: 0 6px 20px rgba(0,0,0,.35);
      }

      .history-badge{
        font-size: 10px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 999px;
        background: linear-gradient(90deg,#22c55e,#16a34a);
        color: #052e16;
      }

      .history-footer{
        margin-top: 10px;
        padding-top: 8px;
        border-top: 1px dashed rgba(255,255,255,.12);
        display: flex;
        justify-content: space-between;
        font-size: 11px;
        color: #9ca3af;
      }

      .history-footer i{
        color:#22c55e;
        margin-right:4px;
      }

      .roi-pill{
        background: rgba(34,197,94,.15);
        color:#22c55e;
        padding:2px 8px;
        border-radius:999px;
        font-weight:600;
      }

      /* scroll area tweaks */
      .history-scroll { overflow-y: auto; padding-right: 6px; }
      </style>

      <div class="mt-10 pt-6 border-t border-white/10">
        <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
          <i class="fas fa-clock-rotate-left text-purple-400"></i>
          Investment History
        </h3>

        <div class="space-y-3 history-scroll">
          <?php foreach($completedInvestments as $comp): 
            $compPlan = null;
            foreach($plans as $p){
              if($p['id']==$comp['plan_id']){ $compPlan=$p; break; }
            }
            if(!$compPlan) continue;
          ?>
          <div class="history-card">
            <div class="flex justify-between items-start">
              <div>
                <span class="history-badge">COMPLETED</span>
                <p class="text-sm text-white font-semibold mt-1">₹<?= number_format($compPlan['cost']) ?> Invested</p>
                <p class="text-xs text-gray-400"><?= $compPlan['time'].' '.$compPlan['unit'] ?> Plan</p>
              </div>

              <div class="text-right">
                <p class="text-xs text-gray-400">Profit</p>
                <p class="text-lg font-bold text-emerald-400">+₹<?= number_format($compPlan['profit']) ?></p>
              </div>
            </div>

            <div class="history-footer">
              <span>
                <i class="far fa-calendar-check"></i>
                <?= date('d M Y, h:i A',$comp['end_time']) ?>
              </span>
              <span class="roi-pill">+<?= intval($analytics['roi_percent'] ?? 40) ?>% ROI</span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <?php endif; ?>
      

      
      <div class="h-8"></div>
    </section>

    <!-- Insufficient Balance Modal (reusable) -->
    <div id="insufficientModal" class="fixed inset-0 bg-black/60 hidden z-50 flex items-center justify-center" onclick="if(event.target === this) closeInsufficientModal()">
      <div class="bg-white rounded-xl w-[90%] max-w-md p-6 text-center shadow-lg">
        
        <h2 class="text-xl font-bold text-red-600 mb-2">
          ❌ Insufficient Balance
        </h2>

        <p class="text-gray-700 mb-4">
          Your balance is not enough to invest in this plan.
        </p>

        <div class="bg-gray-100 rounded-lg p-4 text-left text-sm mb-4">
          <p>💰 <b>Your Balance:</b> ₹<span id="ib_balance">0</span></p>
          <p>📦 <b>Required:</b> ₹<span id="ib_required">0</span></p>
          <p class="text-red-600">⚠️ <b>Short By:</b> ₹<span id="ib_needed">0</span></p>
        </div>

        <div class="flex gap-3">
          <button type="button"
            onclick="closeInsufficientModal()"
            class="w-1/2 py-2 rounded-lg bg-gray-300 hover:bg-gray-400">
            Cancel
          </button>

          <button type="button"
            onclick="goToDepositWith(ibNeeded)"
            class="w-1/2 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700">
            💳 Deposit Now
          </button>
        </div>

      </div>
    </div>

    <!-- Invest Modal -->
    <div id="investModal" class="modal-overlay hidden" onclick="if(event.target === this) closeModal()">
      <div class="modal-content bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 border-2 border-purple-500/50">
        <div class="text-center mb-6">
          <div class="w-20 h-20 bg-gradient-to-br from-purple-500/30 to-indigo-600/30 rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg shadow-purple-500/50 animate-bounce">
            <i class="fas fa-rocket text-purple-400 text-3xl"></i>
          </div>
          <h3 class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-purple-400 to-pink-500">🎯 Confirm Investment</h3>
          <p class="text-gray-300 text-xs mt-2">Amount will be deducted immediately from your account</p>
        </div>

        <div class="bg-gradient-to-br from-purple-900/20 to-indigo-900/20 rounded-xl p-5 mb-6 space-y-4 border-2 border-purple-500/40 shadow-lg shadow-purple-500/30">
          <div class="flex justify-between text-sm bg-black/30 p-3 rounded-lg border border-purple-500/30">
            <span class="text-gray-300 font-medium">📊 Invest Amount</span>
            <span class="text-yellow-400 font-bold text-lg" id="m_cost">₹0</span>
          </div>
          <div class="flex justify-between text-sm bg-black/30 p-3 rounded-lg border border-cyan-500/30">
            <span class="text-gray-300 font-medium">⏱️ Duration</span>
            <span class="text-cyan-400 font-bold text-lg" id="m_time">0 Min</span>
          </div>
          <div class="h-px bg-gradient-to-r from-transparent via-purple-500/50 to-transparent"></div>
          <div class="flex justify-between text-sm bg-black/30 p-3 rounded-lg border border-green-500/30">
            <span class="text-gray-300 font-medium">💰 Total Returns (35%)</span>
            <span class="text-green-400 font-bold text-lg" id="m_return">₹0</span>
          </div>
        </div>

        <button type="button" id="confirmBtn" class="w-full bg-gradient-to-r from-green-500 to-emerald-600 py-3.5 rounded-xl font-bold text-white shadow-lg shadow-green-500/50 hover:shadow-green-400/70 hover:scale-105 transition-all mb-3">
          ✅ Confirm & Invest
        </button>
        <button onclick="closeModal()" class="w-full bg-transparent text-gray-400 text-sm font-medium py-2 hover:text-gray-300">
          Cancel
        </button>
      </div>
    </div>

    <script>
      let currentBalance = <?= $userBalance ?>;
      let selectedPlan = null;
      let ibNeeded = 0;

      function updateBalanceUI(newBalance) {
        currentBalance = Number(newBalance) || 0;
        const formatted = '₹' + Math.floor(currentBalance).toLocaleString();
        const el1 = document.getElementById('walletBalance');
        if (el1) el1.innerText = formatted;
        const el2 = document.getElementById('walletBalanceBig');
        if (el2) el2.innerText = formatted;
      }

      // SHOW Insufficient modal (reusable)
      function showInsufficientModal(currentBalanceVal, requiredAmount) {
        ibNeeded = Math.max(0, Math.ceil(requiredAmount - currentBalanceVal));
        document.getElementById('ib_balance').innerText = Math.floor(currentBalanceVal).toLocaleString();
        document.getElementById('ib_required').innerText = requiredAmount.toLocaleString();
        document.getElementById('ib_needed').innerText = ibNeeded.toLocaleString();
        document.getElementById('insufficientModal').classList.remove('hidden');
      }

      // REDIRECT only when user explicitly clicks Deposit Now
      // (detailed `goToDepositWith` defined later — keep single implementation)

      function openModal(plan) {
        // If client balance is insufficient, show the Insufficient Balance modal
        const curBal = Number(String(currentBalance).replace(/,/g, '')) || 0;
        const cost = Number(String(plan.cost).replace(/,/g, '')) || 0;
        if (curBal < cost) {
          showInsufficientModal(curBal, cost);
          return;
        }

        // Otherwise show confirm modal (UI-only). investPlan is called only from Confirm or Next.
        selectedPlan = plan;
        document.getElementById('m_cost').innerText = '₹' + cost.toLocaleString();
        document.getElementById('m_time').innerText = plan.time + ' ' + plan.unit;
        document.getElementById('m_return').innerText = '₹' + (Number(String(plan.total).replace(/,/g, '')) || 0).toLocaleString();
        document.getElementById('investModal').classList.remove('hidden');
      }

      // Reusable invest function used by Confirm button and Next Plan button
      // Client-side checks prevent calling the invest API when balance is insufficient.
      function investPlan(plan) {
        if (!plan || !plan.id) return;
        // normalize numbers
        const curBal = Number(String(currentBalance).replace(/,/g, '')) || 0;
        const cost = Number(String(plan.cost).replace(/,/g, '')) || 0;
        // If client-side balance insufficient, show the modal and do not call API
        if (curBal < cost) {
          showInsufficientModal(curBal, cost);
          return;
        }

        // Balance seems sufficient on client — call server to perform investment
        fetch(window.location.href, {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: `action=invest&plan_id=${plan.id}`
        })
        .then(res => res.text())
        .then(text => {
          try {
            const data = JSON.parse(text);
            if (data.status === 'success') {
              // Update UI without full reload
              updateBalanceUI(data.balance);
              closeModal();
              showToast('✅ Investment started!');
              // Optionally disable modal confirm button if present
              const conf = document.getElementById('confirmBtn'); if (conf) { conf.disabled = true; conf.classList.add('opacity-70','cursor-not-allowed'); }
              // Update XP bar in UI if provided by server
              if (data.xp && data.level) {
                try { updateXP(Number(data.xp), Number(data.level)); } catch (e) { console.error('XP update failed', e); }
              }
              // Refresh shortly so server-rendered state updates
              setTimeout(() => { try { location.reload(); } catch(e) { console.error('Reload failed', e); } }, 400);
            } else if (data.status === 'insufficient') {
              // Server-side race or other reason caused insufficiency — use server-provided balance and show modal
              const serverBal = Number(data.balance) || curBal;
              // Show modal based on server response (server is authoritative)
              showInsufficientModal(serverBal, cost);
              return;
            } else if (data.status === 'error') {
              // Friendly UI message on server-side error
              showToast('❌ ' + (data.msg || 'Server error'));
              console.log('Invest error:', data);
            } else {
              showToast('❌ Unexpected response from server');
              console.log('Invest error:', data);
            }
          } catch(e) {
            console.error('Parse error:', text, e);
            showToast('❌ Server error. Please try again.');
          }
        })
        .catch(err => {
          console.error('Investment error:', err);
          showToast('❌ Error processing investment');
        });
      }

      function closeModal() {
        document.getElementById('investModal').classList.add('hidden');
        selectedPlan = null;
      }

      // Update XP bar and level in UI
      function updateXP(xp, level) {
        const xpInLevel = xp % 100;
        const percent = (xpInLevel / 100) * 100;

        // level label (assumes structure: level and xp label are siblings before .xp-progress)
        const label = document.querySelector('.xp-progress')?.previousElementSibling?.querySelector('span:last-child');
        if (label) label.innerText = `${xpInLevel} / 100 XP`;

        const bar = document.querySelector('.xp-progress-fill');
        if (bar) bar.style.width = percent + '%';

        const lvl = document.querySelector('.xp-progress')?.previousElementSibling?.querySelector('span:first-child');
        if (lvl) lvl.innerText = `Level ${level}`;
      }

      function closeInsufficientModal() {
        document.getElementById('insufficientModal').classList.add('hidden');
      }

      // INVEST IN NEXT PLAN
      function investNextPlan(currentPlanId, nextPlan) {
        // Next button flow:
        // 1) Call server with action=next to auto-claim the finished plan and get updated balance
        // 2) If server says insufficient for the next plan, show modal (no redirect)
        // 3) If sufficient, update client balance and call investPlan(nextPlan)
        if (!nextPlan || !nextPlan.id) {
          showToast('Invalid next plan');
          return;
        }

        fetch(window.location.href, {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: `action=next&plan_id=${currentPlanId}`
        })
        .then(res => res.text())
        .then(text => {
          try {
            const data = JSON.parse(text);
            if (data.status === 'success') {
              // server returned updated balance after auto-claim
              const newBal = Number(data.balance) || 0;
              updateBalanceUI(newBal);
              if (data.xp && data.level) {
                try { updateXP(Number(data.xp), Number(data.level)); } catch (e) { console.error('XP update failed', e); }
              }
              // If server indicates a next plan cost / needed amount, use it
              const needed = parseInt(data.needed) || 0;
              const nextCost = data.nextPlanCost || nextPlan.cost;
              if (needed > 0) {
                // show insufficient modal using server-updated balance (server-provided values)
                showInsufficientModal(newBal, nextCost);
                return;
              }
              // sufficient — proceed to invest using updated balance
              investPlan(nextPlan);
              // Refresh shortly so server-rendered state updates
              setTimeout(() => { try { location.reload(); } catch(e) { console.error('Reload failed', e); } }, 400);
            } else if (data.status === 'insufficient') {
              // server-side insufficient (authoritative)
              const newBal = Number(data.balance) || 0;
              updateBalanceUI(newBal);
              // show modal using server-updated balance
              showInsufficientModal(newBal, nextPlan.cost);
            } else {
              showToast('❌ ' + (data.msg || 'Server error'));
            }
          } catch (e) {
            console.error('Next parse error:', text, e);
            showToast('❌ Server error. Please try again.');
          }
        })
        .catch(err => {
          console.error('Next action error:', err);
          showToast('❌ Error processing request');
        });
      }

      // CONFIRM INVESTMENT (attach listener only if button exists)
      const _confirmBtn = document.getElementById('confirmBtn');
      if (_confirmBtn && !_confirmBtn.dataset._investListener) {
        _confirmBtn.addEventListener('click', function(e) {
          e.preventDefault(); // prevent any default form submit/navigation
          if (!selectedPlan) return;
          const btn = this;
          btn.innerText = 'Processing...';
          // Guard: ensure fetch exists and selectedPlan is valid
          if (typeof fetch !== 'function' || !selectedPlan || !selectedPlan.id) {
            showToast('❌ Unable to process investment');
            btn.innerText = 'Confirm & Invest';
            return;
          }
          investPlan(selectedPlan);
          btn.innerText = 'Confirm & Invest';
        });
        _confirmBtn.dataset._investListener = '1';
      }

      // CLAIM PROFIT
      window.claimProfit = function(id) {
        if(!id) {
          showToast('Invalid plan ID');
          return;
        }
        
        fetch(window.location.href, {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: `action=claim&plan_id=${id}`
        })
        .then(res => res.text())
        .then(text => {
          try {
            const data = JSON.parse(text);
            if (data.status === 'success') {
              updateBalanceUI(data.balance);
              showToast('✅ ' + data.msg);
              try {
                const btn = document.querySelector(`button[onclick="claimProfit(${id})"]`);
                if (btn) {
                  btn.disabled = true;
                  btn.innerText = 'Completed';
                  btn.classList.add('opacity-60','cursor-not-allowed');
                }
              } catch (e) { console.error('Claim UI update error', e); }
              // Update XP bar in UI if provided by server
              if (data.xp && data.level) {
                try { updateXP(Number(data.xp), Number(data.level)); } catch (e) { console.error('XP update failed', e); }
              }
              // Refresh shortly so server-rendered state updates
              setTimeout(() => { try { location.reload(); } catch(e) { console.error('Reload failed', e); } }, 400);
            } else {
              showToast('❌ ' + (data.msg || 'Claim failed'));
              console.log('Claim error:', data);
            }
          } catch(e) {
            console.error('Parse error:', text);
            showToast('Error: Invalid response');
          }
        })
        .catch(err => {
          console.error('Claim error:', err);
          showToast('❌ Error processing claim');
        });
      };

      // Redirect to deposit page with amount (reads data-amount if not provided)
      function goToDepositWith(amount) {
        console.log('goToDepositWith called, amount param:', amount);
        const btn = document.getElementById('depositNowBtn');
        let amt = amount;
        if ((amt === undefined || amt === null || isNaN(amt)) && btn && btn.dataset && btn.dataset.amount) {
          amt = btn.dataset.amount;
        }
        // fallback to 0 (deposit page will handle min/default)
        amt = parseInt(amt) || 0;
        window.location.href = 'deposit.php' + (amt > 0 ? ('?amount=' + encodeURIComponent(amt)) : '');
      }

      // COUNTDOWN TIMER
      setInterval(() => {
        const now = Math.floor(Date.now() / 1000);
        document.querySelectorAll('.countdown').forEach(el => {
          const end = parseInt(el.getAttribute('data-end'));
          const diff = end - now;

          if (diff <= 0) {
            if (!el.dataset.done) {
              el.dataset.done = "true";
              // Convert this running card into a claim-ready card (targeted UI update)
              try {
                const parentBtn = el.closest('button');
                const planId = el.getAttribute('data-plan-id');
                const total = el.getAttribute('data-total') || '';
                if (parentBtn && planId) {
                  const claimBtn = document.createElement('button');
                  claimBtn.type = 'button';
                  claimBtn.className = 'w-full py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-bold rounded-xl text-sm shadow-lg shadow-green-500/50 hover:shadow-green-400/70 hover:scale-105 transition-transform';
                  claimBtn.innerText = '💚 Claim ₹' + (Number(total) ? Number(total).toLocaleString() : '');
                  claimBtn.setAttribute('onclick', `claimProfit(${planId})`);
                  parentBtn.replaceWith(claimBtn);
                } else {
                  // Fallback: reload the page if we can't perform a targeted update
                  location.reload();
                }
              } catch (err) {
                console.error('Countdown conversion error', err);
                location.reload();
              }
            }
          } else {
            const h = Math.floor(diff / 3600);
            const m = Math.floor((diff % 3600) / 60);
            const s = diff % 60;
            el.innerText = `${h}h ${m}m ${s}s`;
          }
        });
      }, 1000);
    </script>



   

  <!-- Mobile Bottom Navigation -->
  <div class="bottom-nav">
    <a href="index.php" class="nav-item active">
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
    
    <a href="refer.php" class="nav-item">
      <i class="fas fa-gift"></i>
      <span>Promos</span>
    </a>
    
    <a href="profile.php" class="nav-item">
      <i class="fas fa-user"></i>
      <span>Profile</span>
    </a>
  </div>

  <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js"></script>
  <script>
    // winners-swiper initialization removed (Big Wins section deleted)

    // Close welcome popup and set cookie
    function closeWelcomePopup() {
      document.querySelector('.welcome-popup').classList.add('animate__fadeOut');
      setTimeout(() => {
        document.querySelector('.welcome-popup').style.display = 'none';
      }, 500);
      
      // Set cookie to not show again for 7 days
      document.cookie = "welcome_popup_shown=true; max-age=" + (60 * 60 * 24 * 7) + "; path=/";
    }
    
    // Claim welcome bonus (no client-side balance changes)
    function claimWelcomeBonus() {
      createConfetti();
      showToast('🎉 Welcome bonus requested — server will apply it if eligible.');
    }
    // Create confetti effect
    function createConfetti() {
      const colors = ['#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff', '#00ffff'];
      
    
      for (let i = 0; i < 100; i++) {
        const confetti = document.createElement('div');
        confetti.className = 'confetti';
        confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
        confetti.style.left = Math.random() * 100 + 'vw';
        confetti.style.top = -10 + 'px';
        confetti.style.transform = 'rotate(' + Math.random() * 360 + 'deg)';
        
        const animationDuration = Math.random() * 3 + 2;
        confetti.style.animation = `fall ${animationDuration}s linear forwards`;
        
        document.body.appendChild(confetti);
        
        // Remove confetti after animation
        setTimeout(() => {
          confetti.remove();
        }, animationDuration * 1000);
      }
      
      // Add CSS for falling animation
      const style = document.createElement('style');
      style.innerHTML = `
        @keyframes fall {
          to {
            transform: translateY(100vh) rotate(360deg);
            opacity: 0;
          }
        }
      `;
      document.head.appendChild(style);
    }
    
    // Show toast notification
    function showToast(message) {
      const toast = document.createElement('div');
      toast.className = 'toast fixed bottom-20 left-1/2 transform -translate-x-1/2 bg-indigo-600 text-white px-4 py-2 rounded-lg shadow-lg z-50 animate__animated animate__fadeInUp';
      toast.textContent = message;
      document.body.appendChild(toast);
      
      setTimeout(() => {
        toast.classList.remove('animate__fadeInUp');
        toast.classList.add('animate__fadeOutDown');
        setTimeout(() => toast.remove(), 500);
      }, 3000);
    }
    
    // Initialize functions when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
      
      // Enable deposit button when terms are checked (attach only once)
      const __terms = document.getElementById('termsCheckbox');
      if (__terms && !__terms.dataset._depositListener) {
        __terms.addEventListener('change', function() { checkDepositButton(); });
        __terms.dataset._depositListener = '1';
      }
      // Check deposit amount input (attach only once)
      const __depAmt = document.getElementById('depositAmount');
      if (__depAmt && !__depAmt.dataset._depositListener) {
        __depAmt.addEventListener('input', function() { checkDepositButton(); });
        __depAmt.dataset._depositListener = '1';
      }
      // Show toast if there are finished investments ready to claim
      const finished = <?= intval($finishedCount) ?>;
      if (finished > 0) {
        showToast('🔔 You have ' + finished + ' finished investment(s) ready to claim');
      }
    });
  </script>

<!-- 🔔 Notification Popup -->
<div id="notifPopup"
  class="fixed inset-0 z-[9999] hidden bg-black/60 backdrop-blur-sm"
  onclick="if(event.target===this) closeNotifPopup()">

  <div
    class="absolute bottom-0 left-0 right-0 max-h-[75%] rounded-t-2xl bg-gradient-to-b from-gray-900 to-gray-950 border-t border-white/10 animate__animated animate__slideInUp">

    <!-- Header -->
    <div class="flex items-center justify-between px-5 py-4 border-b border-white/10">
      <h3 class="text-lg font-bold text-white flex items-center gap-2">
        <i class="fas fa-bell text-indigo-400"></i> Notifications
      </h3>
      <button onclick="markAllRead()" class="text-xs text-indigo-400">
        Mark all read
      </button>
    </div>

    <!-- Body -->
    <div class="max-h-[60vh] overflow-y-auto px-4 py-3 space-y-3">
      <?php if(count($notifications) === 0): ?>
        <div class="text-center text-gray-400 py-10">
          <i class="fas fa-inbox text-3xl mb-3"></i>
          <p>No notifications yet</p>
        </div>
      <?php else: ?>
        <?php foreach($notifications as $n): ?>
          <div
            class="p-4 rounded-xl border border-white/10
            <?= !$n['seen'] ? 'bg-indigo-500/10' : 'bg-white/5' ?>">
            
            <p class="text-sm text-white leading-snug">
              <?= htmlspecialchars($n['title']) ?>
            </p>
            <p class="text-xs text-gray-400 mt-1">
              <?= date('d M Y, h:i A', $n['created_at']) ?>
            </p>

            <?php if(!$n['seen']): ?>
              <span class="inline-block mt-2 text-[10px] px-2 py-0.5 rounded-full bg-indigo-600 text-white">
                NEW
              </span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="p-4 border-t border-white/10">
      <button onclick="closeNotifPopup()"
        class="w-full py-2 rounded-lg bg-white/10 text-white hover:bg-white/20">
        Close
      </button>
    </div>

  </div>
</div>

<script>
function openNotifPopup() {
  const p = document.getElementById('notifPopup');
  p.classList.remove('hidden');

  // mark read on open
  const badge = document.querySelector('.notification-badge');
  if (badge) badge.style.display = 'none';

  fetch(window.location.href, {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'action=mark_notifications'
  });
}

function closeNotifPopup() {
  document.getElementById('notifPopup').classList.add('hidden');
}

function markAllRead() {
  fetch(window.location.href, {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'action=mark_notifications'
  }).then(()=>{
    document.querySelectorAll('#notifPopup .bg-indigo-500\\/10')
      .forEach(el=>el.classList.replace('bg-indigo-500/10','bg-white/5'));
  });
}
</script>
</body>
</html>