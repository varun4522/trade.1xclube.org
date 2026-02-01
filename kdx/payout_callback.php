<?php
// NekPayment Callback Handler
// Path: /kdx/payout_callback.php

// Errors ko screen par mat dikhao (Gateway confuse ho jayega), bas log karo
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../config.php'; 

// --- CONFIGURATION ---
$payment_key = "NULLKI70AXG5T3NTFJZTYJX8EFILY09D";

// 1. DATA RECEIVE KARO
$data = $_POST;

// Agar POST khali hai, toh JSON input try karo (Safety ke liye)
if (empty($data)) {
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);
}

if (empty($data)) {
    die("No data received");
}

// LOGGING (Debugging ke liye zaroori hai)
$log_entry = date('Y-m-d H:i:s') . " - HIT RECEIVED: " . json_encode($data) . PHP_EOL;
file_put_contents('payout_logs.txt', $log_entry, FILE_APPEND);

// 2. SIGNATURE VERIFY KARO
$received_sign = $data['sign'] ?? '';
$calculated_sign = generateSignature($data, $payment_key);

// Agar signature match nahi hua toh log file mein error likho
if ($received_sign !== $calculated_sign) {
    file_put_contents('payout_logs.txt', "ERROR: Signature Mismatch! Calculated: $calculated_sign | Received: $received_sign" . PHP_EOL, FILE_APPEND);
}

if ($received_sign === $calculated_sign) {
    
    // 3. DATA EXTRACT KARO
    $status = $data['respCode'] ?? '';      // SUCCESS
    $tradeResult = $data['tradeResult'] ?? ''; // 1 = Success, 2 = Fail
    $order_id = $data['merTransferId'] ?? '';
    
    // Yahan hum UTR nikal rahe hain (Gateway se kabhi 'utr' aata hai)
    $utr_number = $data['utr'] ?? null; 
    
    // 4. DATABASE UPDATE KARO
    if ($status === 'SUCCESS') {
        
        if ($tradeResult == '1') {
            // SCENARIO: SUCCESS
            // Hum status update karenge AUR utr bhi save karenge
            $stmt = $pdo->prepare("UPDATE payouts SET status='success', api_response=?, utr=? WHERE order_id=?");
            $stmt->execute([json_encode($data), $utr_number, $order_id]);
            
            file_put_contents('payout_logs.txt', "--> Order $order_id MARKED SUCCESS with UTR: $utr_number" . PHP_EOL, FILE_APPEND);
            
        } elseif ($tradeResult == '2') {
            // SCENARIO: FAILED
            $stmt = $pdo->prepare("UPDATE payouts SET status='failed', api_response=? WHERE order_id=?");
            $stmt->execute([json_encode($data), $order_id]);
            
            file_put_contents('payout_logs.txt', "--> Order $order_id MARKED FAILED" . PHP_EOL, FILE_APPEND);
        }
    }

    // 5. GATEWAY KO CONFIRMATION BHEJO
    echo "success";

} else {
    // Agar Signature galat hai
    echo "fail";
}

// --- HELPER FUNCTION: SIGNATURE GENERATOR ---
function generateSignature($params, $key) {
    // 1. Unset fields jo signature mein nahi chahiye
    unset($params['sign']);
    unset($params['signType']);
    
    // *** IMPORTANT: UTR ko hata rahe hain taki Mismatch na ho ***
    unset($params['utr']); 
    
    // 2. Empty values hatao
    $params = array_filter($params, function($v) { 
        return $v !== '' && $v !== null; 
    });
    
    // 3. Sort alphabetically (A-Z)
    ksort($params);
    
    // 4. String banao
    $str = '';
    foreach ($params as $k => $v) {
        $str .= $k . '=' . $v . '&';
    }
    
    // 5. Key add karo aur Hash nikalo
    $str .= 'key=' . $key;
    
    return md5($str);
}
?>