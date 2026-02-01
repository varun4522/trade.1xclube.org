<?php
// basepay_request.php

// 1. Setup Debugging (Ye file banegi server par)
$logFile = 'basepay_debug.txt';
function writeLog($msg) {
    global $logFile;
    $date = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$date] $msg" . PHP_EOL, FILE_APPEND);
}

error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
session_start();

writeLog("--- New Request Started ---");

// ============================================================
// 2. DATABASE CONFIGURATION
// ============================================================
$servername = "localhost";
$username = "chikenof_chick"; 
$password = "chikenof_chick"; 
$dbname = "chikenof_chick";   

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    writeLog("DB Connection Failed: " . $conn->connect_error);
    echo json_encode(['respCode' => 'FAIL', 'tradeMsg' => 'Database Connection Failed']);
    exit;
}

// ============================================================
// 3. BASEPAY SETTINGS
// ============================================================
$mch_id = "100567121"; 
$key = "eebffc308408408dba442e41808a2a61"; 
$gateway_url = "https://api.nekpayment.com/pay/web";
$pay_type = "105"; 

// 4. User ID & Amount
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; 
$amount = $_POST['amount'] ?? 0;

if ($amount < 1) {
    echo json_encode(['respCode' => 'FAIL', 'tradeMsg' => 'Invalid Amount']);
    exit;
}

$trade_amount = number_format((float)$amount, 2, '.', '');
$mch_order_no = "ORD" . time() . rand(1000,9999);
$order_date = date('Y-m-d H:i:s');

// Domain Detect
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$notify_url = "$protocol://$host/basepay_notify.php";
$page_url = "$protocol://$host/transactions.html"; 

writeLog("Order Generated: $mch_order_no | Amount: $trade_amount");

// 5. INSERT (Initial Record)
$sql = "INSERT INTO transactions (user_id, type, amount, status, method, deposit_method, transaction_id, mch_order_no, created_at) VALUES (?, 'deposit', ?, 'pending', 'basepay', 'basepay', ?, ?, NOW())";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    writeLog("Insert Prepare Failed: " . $conn->error);
    echo json_encode(['respCode' => 'FAIL', 'tradeMsg' => 'SQL Error']);
    exit;
}
$stmt->bind_param("idss", $user_id, $trade_amount, $mch_order_no, $mch_order_no);
if($stmt->execute()) {
    writeLog("Insert Success for Order: $mch_order_no");
} else {
    writeLog("Insert Execute Failed: " . $stmt->error);
}
$stmt->close();

// 6. Prepare Basepay Parameters
$params = [
    "goods_name" => "AddFunds",
    "mch_id" => $mch_id,
    "mch_order_no" => $mch_order_no,
    "mch_return_msg" => "UID_" . $user_id,
    "notify_url" => $notify_url,
    "order_date" => $order_date,
    "page_url" => $page_url,
    "pay_type" => $pay_type,
    "trade_amount" => $trade_amount,
    "version" => "1.0"
];

// 7. Generate Signature
ksort($params);
$signStr = "";
foreach ($params as $k => $v) {
    if ($v !== "" && $v !== null) {
        $signStr .= $k . "=" . $v . "&";
    }
}
$signStr .= "key=" . $key;
$sign = md5($signStr);

$params['sign'] = $sign;
$params['sign_type'] = 'MD5';

// 8. Send Request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $gateway_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

// 9. Handle Response & SAVE URL
if ($err) {
    writeLog("CURL Error: $err");
    echo json_encode(['respCode' => 'FAIL', 'tradeMsg' => 'CURL Error']);
} else {
    writeLog("API Response: $response"); // Sabse Important Line
    
    $result = json_decode($response, true);

    // Koshish karo URL dhundne ki (payUrl ya payInfo)
    $finalUrl = null;
    if (isset($result['payUrl'])) {
        $finalUrl = $result['payUrl'];
    } elseif (isset($result['payInfo'])) {
        $finalUrl = $result['payInfo'];
    }

    if ($result['respCode'] == 'SUCCESS' && $finalUrl) {
        
        writeLog("URL Found: $finalUrl. Attempting Update...");

        // === UPDATE QUERY ===
        $updateSql = "UPDATE transactions SET payment_url = ? WHERE mch_order_no = ?";
        $upStmt = $conn->prepare($updateSql);
        if ($upStmt) {
            $upStmt->bind_param("ss", $finalUrl, $mch_order_no);
            if($upStmt->execute()) {
                writeLog("Update Query Executed Successfully.");
            } else {
                writeLog("Update Query Failed: " . $upStmt->error);
            }
            $upStmt->close();
        } else {
            writeLog("Update Prepare Failed: " . $conn->error);
        }

        // Send to Frontend
        echo json_encode(['respCode' => 'SUCCESS', 'payInfo' => $finalUrl]);

    } else {
        writeLog("Failed to parse URL from response.");
        echo $response;
    }
}
?>