<?php
header('Content-Type: application/json');
include 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (isset($data['bet'], $data['status'])) {
    $user_id = $_SESSION['user_id'];
    $bet = $data['bet'];
    $mult = $data['multiplier'] ?? '0.00x';
    $status = $data['status'];
    $win_amount = $data['win_amount'] ?? 0;
    $diff = $data['diff'] ?? 'Easy';

    // Balance update logic...
    if ($status == 'win') {
        $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->bind_param("di", $win_amount, $user_id);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->bind_param("di", $bet, $user_id);
        $stmt->execute();
    }

    // Insert history
    $stmt = $conn->prepare("INSERT INTO chicken_history (user_id, bet_amount, multiplier, win_amount, status, difficulty) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("idsdss", $user_id, $bet, $mult, $win_amount, $status, $diff);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
} else {
    echo json_encode(['status' => 'error']);
}
?>