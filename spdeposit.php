<?php
// FILE: spdeposit.php (Updated with URL Save & Debug)
session_start();
require_once('config.php'); 

// 1. Debugging Setup (Jasoos File)
$logFile = 'sunpay_debug.txt';
function writeLog($msg) {
    global $logFile;
    $date = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$date] $msg" . PHP_EOL, FILE_APPEND);
}

error_reporting(0); 
ini_set('display_errors', 0); 
ob_start();

header('Content-Type: application/json');

writeLog("--- New SunPay Request Started ---");

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Please login first.");
    }
    $user_id = $_SESSION['user_id'];

    // --- CONFIGURATION ---
    define('SUNPAY_MERCHANT_ID', '202111011');
    define('SUNPAY_KEY', '71663c49ad884f4ca3c7ac29462ddc70'); 
    define('SUNPAY_CHANNEL_CODE', '102'); 
    define('SUNPAY_PAYMENT_URL', 'https://pay.sunpayonline.xyz/pay/web');
    define('SUNPAY_NOTIFY_URL', 'https://play.1xclube.org/api/sunpay_notify.php'); 

    // --- DATABASE ENTRY (Table: transactions) ---
    $amount = $_POST['amount'] ?? '100';
    $order_id = date('YmdHis') . rand(1000,9999); // Generated Order ID
    
    // Database Connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) { 
        writeLog("DB Connection Failed");
        throw new Exception("DB Connection Error"); 
    }

    // Inserting into 'transactions' table
    // Hum 'deposit_method' bhi add kar rahe hain taaki BasePay jaisa same format rahe
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, status, method, deposit_method, mch_order_no, created_at) VALUES (?, 'deposit', ?, 'pending', 'sunpay', 'sunpay', ?, NOW())");
    $stmt->bind_param("ids", $user_id, $amount, $order_id);
    
    if (!$stmt->execute()) {
        writeLog("DB Insert Failed: " . $stmt->error);
        throw new Exception("Database Insert Failed: " . $stmt->error);
    }
    $stmt->close();
    
    // NOTE: Connection close nahi kiya, kyunki abhi update karna baki hai

    // --- SIGNATURE FUNCTION ---
    function sunpaySign($params) {
        $params = array_filter($params, function($v, $k) {
            return $v !== '' && $v !== null && $k !== 'sign' && $k !== 'sign_type';
        }, ARRAY_FILTER_USE_BOTH);
        ksort($params);
        $str = "";
        foreach ($params as $k => $v) { $str .= $k . "=" . $v . "&"; }
        $str .= "key=" . SUNPAY_KEY;
        return md5($str);
    }

    // --- PREPARE DATA ---
    $data = [
        'version'      => '1.0',
        'mch_id'       => SUNPAY_MERCHANT_ID,
        'mch_order_no' => $order_id, 
        'pay_type'     => SUNPAY_CHANNEL_CODE,
        'trade_amount' => sprintf("%.2f", $amount),
        'order_date'   => date('Y-m-d H:i:s'),
        'notify_url'   => SUNPAY_NOTIFY_URL,
        'goods_name'   => 'Deposit',
        'sign_type'    => 'MD5'
    ];
    $data['sign'] = sunpaySign($data);

    writeLog("Sending Request for Order: $order_id");

    // --- SEND TO GATEWAY ---
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, SUNPAY_PAYMENT_URL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    ob_clean(); 
    if ($err) { 
        writeLog("CURL Error: $err");
        echo json_encode(['respCode' => 'FAIL', 'tradeMsg' => 'Curl Error: ' . $err]); 
    } 
    else { 
        writeLog("Response: $response");
        
        $result = json_decode($response, true);
        
        // --- LOGIC TO EXTRACT URL & SAVE (The Fix) ---
        $finalUrl = null;
        // Hum teeno naam check karenge jo gateway bhej sakta hai
        if (isset($result['payInfo'])) { $finalUrl = $result['payInfo']; }
        elseif (isset($result['payUrl'])) { $finalUrl = $result['payUrl']; }
        elseif (isset($result['paymentUrl'])) { $finalUrl = $result['paymentUrl']; }

        if ($finalUrl) {
            // URL mil gaya! Ab Database update karte hain
            $updateSql = "UPDATE transactions SET payment_url = ? WHERE mch_order_no = ?";
            $upStmt = $conn->prepare($updateSql);
            if ($upStmt) {
                $upStmt->bind_param("ss", $finalUrl, $order_id);
                $upStmt->execute();
                $upStmt->close();
                writeLog("URL Saved Successfully: $finalUrl");
            } else {
                writeLog("Update Prepare Failed: " . $conn->error);
            }
            
            // Frontend ke liye ensure karte hain ki 'payInfo' key maujood ho
            if (!isset($result['payInfo'])) {
                $result['payInfo'] = $finalUrl;
            }
            echo json_encode($result);
        } else {
            // Agar URL nahi mila, to original response bhej do (error msg hoga shayad)
            echo $response;
        }
    }
    
    // Kaam khatam, ab connection band
    $conn->close();

} catch (Exception $e) {
    ob_clean();
    writeLog("Exception: " . $e->getMessage());
    echo json_encode(['respCode' => 'FAIL', 'tradeMsg' => 'Error: ' . $e->getMessage()]);
}
?>