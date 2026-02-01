<?php
session_start();
define('DB_HOST', 'localhost');
define('DB_USER', 'chikenof_chick');
define('DB_PASS', 'chikenof_chick');
define('DB_NAME', 'chikenof_chick');

$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$user_id = (int)$_SESSION['user_id'];

// 1. Process Expired Trades
$expired = $db->query("SELECT * FROM trades WHERE user_id = $user_id AND result = 'pending' AND created_at < NOW() - INTERVAL duration SECOND");
$last_res = null;

while($t = $expired->fetch_assoc()) {
    $win = (mt_rand(1, 100) <= 38); // 38% Win Chance
    $payout = $win ? $t['wager'] * 1.8 : 0;
    $res_status = $win ? 'win' : 'lose';
    
    $db->query("UPDATE trades SET result = '$res_status', payout = $payout WHERE id = {$t['id']}");
    if($win) $db->query("UPDATE users SET balance = balance + $payout WHERE id = $user_id");
    
    $_SESSION['last_trade_res'] = ['result' => $res_status, 'amount' => $win ? $payout : $t['wager']];
}

if(isset($_GET['clear'])) {
    unset($_SESSION['last_trade_res']);
    exit;
}

// 2. Get Data
$user = $db->query("SELECT balance FROM users WHERE id = $user_id")->fetch_assoc();
$trades = $db->query("SELECT * FROM trades WHERE user_id = $user_id AND result = 'pending'");
$active = [];
while($row = $trades->fetch_assoc()) $active[] = $row;

echo json_encode([
    'balance' => $user['balance'],
    'trades' => $active,
    'last_result' => $_SESSION['last_trade_res'] ?? null
]);