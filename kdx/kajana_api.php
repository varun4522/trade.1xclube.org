<?php
// FILE: /kdx/kajana_api.php
// Backend Logic (Fixed: Waits for Callback/UTR)

session_start();
header('Content-Type: application/json');
require_once '../config.php'; 

// 1. Security Check
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

// 2. Configuration
$MERCHANT_ID = "100567121";
$PAYMENT_KEY = "NULLKI70AXG5T3NTFJZTYJX8EFILY09D";
$API_URL     = "https://api.nekpayment.com/pay/transfer";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 3. Get Input
    $bank_code = $_POST['bank_code'] ?? 'IDPT0001'; 
    $account_name = trim($_POST['account_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $ifsc_code = trim($_POST['ifsc_code'] ?? ''); 
    $amount = floatval($_POST['amount'] ?? 0);
    $order_id = "PAY" . time() . rand(100,999); 

    // 4. Validate Input
    if (strlen($account_name) < 5) {
        echo json_encode(['status' => 'error', 'message' => 'Account Name must be > 5 characters']);
        exit;
    }
    if ($amount <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Amount']);
        exit;
    }

    // =========================================================
    //  AUTO-SAVE BENEFICIARY (Quick Select Feature)
    // =========================================================
    try {
        $check = $pdo->prepare("SELECT id FROM beneficiaries WHERE account_number = ?");
        $check->execute([$account_number]);
        
        if ($check->rowCount() > 0) {
            $upd = $pdo->prepare("UPDATE beneficiaries SET account_name=?, ifsc=?, bank_code=?, last_used=NOW() WHERE account_number=?");
            $upd->execute([$account_name, $ifsc_code, $bank_code, $account_number]);
        } else {
            $ins = $pdo->prepare("INSERT INTO beneficiaries (account_name, account_number, ifsc, bank_code) VALUES (?, ?, ?, ?)");
            $ins->execute([$account_name, $account_number, $ifsc_code, $bank_code]);
        }
    } catch (Exception $e) {
        // Ignore save errors
    }
    // =========================================================

    // 5. Save Initial Record to DB (Status: PENDING)
    try {
        $stmt = $pdo->prepare("INSERT INTO payouts (order_id, amount, account_name, account_number, bank_code, ifsc, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$order_id, $amount, $account_name, $account_number, $bank_code, $ifsc_code]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()]);
        exit;
    }

    // 6. Prepare Payload
    $params = [
        'mch_id'          => $MERCHANT_ID,
        'mch_transferId'  => $order_id,
        'transfer_amount' => number_format($amount, 2, '.', ''),
        'apply_date'      => date('Y-m-d H:i:s'),
        'bank_code'       => $bank_code, 
        'receive_name'    => $account_name,
        'receive_account' => $account_number,
        'remark'          => $ifsc_code,
        'back_url'        => 'https://play.1xclube.org/kdx/payout_callback.php' 
    ];

    // 7. Generate Signature
    $sign_params = array_filter($params, function($v) { return $v !== '' && $v !== null; });
    ksort($sign_params);
    $sign_str = '';
    foreach ($sign_params as $key => $val) { $sign_str .= $key . '=' . $val . '&'; }
    $sign_str .= 'key=' . $PAYMENT_KEY;
    
    $params['sign'] = md5($sign_str);
    $params['sign_type'] = 'MD5';

    // 8. Send Request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $API_URL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        echo json_encode(['status' => 'error', 'message' => 'Connection Error: ' . $curl_error]);
        exit;
    }

    // 9. Handle Response
    $result = json_decode($response, true);
    
    // Log Response
    $stmt = $pdo->prepare("UPDATE payouts SET api_response=? WHERE order_id=?");
    $stmt->execute([$response, $order_id]);

    // *** IMPORTANT FIX HERE ***
    if (isset($result['respCode']) && $result['respCode'] === 'SUCCESS') {
        // Hum yahan 'success' update NAHI karenge.
        // Hum frontend ko bolenge ki request le li gayi hai.
        // Asli 'Success' tab hoga jab Callback aayega.
        
        echo json_encode([
            'status' => 'success', 
            'message' => 'Request Initiated. Waiting for Bank Confirmation...',
            'order_id' => $order_id
        ]);
        
    } else {
        // Agar turant fail hua, tabhi Failed mark karo
        $pdo->prepare("UPDATE payouts SET status='failed' WHERE order_id=?")->execute([$order_id]);
        
        $msg = $result['errorMsg'] ?? 'Unknown Gateway Error';
        echo json_encode(['status' => 'error', 'message' => $msg]);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
}
?>