<?php
include 'db.php';
session_start();

if(!isset($_SESSION['user_id'])) {
    echo "0.00"; // Login nahi hai to 0 dikhao, redirect mat karo
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if($row = $result->fetch_assoc()) {
    // Comma hata kar plain number bhejo
    echo number_format($row['balance'], 2, '.', '');
} else {
    echo "0.00";
}
?>