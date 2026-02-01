echo "TEST SUCCESS";
<?php
require_once '../config.php';

// TEST UPDATE (only for dev, remove later)
$conn = getDBConnection();

// change orderId to your pending row
$orderId = 'DEP202512221147437914'; 
$amount  = 500.00;

$conn->query("
    UPDATE recharge
    SET status='SUCCESS'
    WHERE order_id='$orderId'
");

$conn->query("
    UPDATE profiles p
    JOIN recharge r ON p.id = r.user_id
    SET p.balance = p.balance + $amount
    WHERE r.order_id='$orderId'
");

echo "TEST SUCCESS";
