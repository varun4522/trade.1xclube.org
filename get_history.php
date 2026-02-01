<?php
// Is line se browser ko pata chalega ki ye DATA hai, PAGE nahi
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

include 'db.php';
session_start();

// Agar login nahi hai, to khali list bhejo (REDIRECT MAT KARNA)
if (!isset($_SESSION['user_id'])) {
    echo json_encode([]); 
    exit;
}

$user_id = $_SESSION['user_id'];

// Data fetch karo
$stmt = $conn->prepare("SELECT * FROM chicken_history WHERE user_id = ? ORDER BY id DESC LIMIT 20");
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    $result = $stmt->get_result();
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    echo json_encode($history);
} else {
    echo json_encode([]);
}
?>