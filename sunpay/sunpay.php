<?php
// spdeposit.php (SunPay Request File)

// 1. Debugging Setup (Agar koi issue aaye to 'sunpay_debug.txt' check kar sakein)
$logFile = 'sunpay_debug.txt';
function writeLog($msg) {
    global $logFile;
    $date = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$date] $msg" . PHP_EOL, FILE_APPEND);
}

// 2. Basic Setup
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
session_start();

writeLog("--- New SunPay Request Started ---");

// ============================================================
// 3. DATABASE CONFIGURATION
// ============================================================
$servername = "localhost";
$username = "chikenof_chick"; 
$password = "chikenof_chick"; 
$dbname = "chikenof_chick";   

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    writeLog("DB Connection Failed");
    echo json_encode(['respCode' => 'FAIL', 'tradeMsg' => 'Database Error']);
    exit;
}

// ============================================================
// 4. SUNPAY CREDENTIALS & FUNCTIONS
// ============================================================
define('SUNPAY_MERCHANT_ID', '202111011');
define('SUNPAY_KEY', '71663c49ad884f4ca3c7ac29462ddc70');
define('SUNPAY_CHANNEL_CODE', '102');
define('SUNPAY_PAYMENT_URL', 'https://pay.sunpayonline.xyz/pay/web');
// Notify URL ko trim kiya taaki space issue na kare
define('SUNPAY_NOTIFY_URL', trim('https://trade.1xclube.org/sunpay/notify.php'));

function generateOrderId() {
    return 'DEP' . date('YmdHis') . rand(1000,9999);
}

function sunpaySign(array $params): string {
    unset($params['sign'], $params['sign_type']);
    ksort($params);
    $query = urldecode(http_build_query($params));
    return md5($query . '&key=' . SUNPAY_KEY);
}

function buildSunpayRequest($orderId, $amount) {
    $data = [
        'version'       => '1.0',
        'mch_id'        => SUNPAY_MERCHANT_ID,
        'mch_order_no'  => $orderId,
        'pay_type'      => SUNPAY_CHANNEL_CODE,
        'trade_amount'  => number_format($amount, 2, '.', ''),
        'order_date'    => date('Y-m-d H:i:s'),
        'notify_url'    => SUNPAY_NOTIFY_URL,
        'goods_name'    => 'Wallet Recharge',
        'sign_type'     => 'MD5'
    ];
    $data['sign'] = sunpaySign($data);
    return $data;
}

// ============================================================
// 5. PROCESS REQUEST
// ============================================================

// Check User
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
$amount = $_POST['amount'] ?? 0;

if ($amount < 1) {
    echo json_encode(['respCode' => 'FAIL', 'tradeMsg' => 'Invalid Amount']);
    exit;
}

$mch_order_no = generateOrderId();
$trade_amount = number_format((float)$amount, 2, '.', '');

writeLog("Order: $mch_order_no | Amount: $trade_amount");

// --- STEP A: INSERT INTO DATABASE (Pending) ---
$sql = "INSERT INTO transactions (user_id, type, amount, status, method, deposit_method, transaction_id, mch_order_no, created_at) VALUES (?, 'deposit', ?, 'pending', 'sunpay', 'sunpay', ?, ?, NOW())";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    writeLog("SQL Prepare Error: " . $conn->error);
    echo json_encode(['respCode' => 'FAIL', 'tradeMsg' => 'System Error']);
    exit;
}
$stmt->bind_param("idss", $user_id, $trade_amount, $mch_order_no, $mch_order_no);
if(!$stmt->execute()) {
    writeLog("SQL Execute Error: " . $stmt->error);
    echo json_encode(['respCode' => 'FAIL', 'tradeMsg' => 'DB Insert Failed']);
    exit;
}
$stmt->close();


// --- STEP B: CALL SUNPAY API ---
$requestData = buildSunpayRequest($mch_order_no, $amount);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, SUNPAY_PAYMENT_URL);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($requestData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    writeLog("CURL Error: $err");
    echo json_encode(['respCode' => 'FAIL', 'tradeMsg' => 'Connection Error']);
} else {
    writeLog("API Response: $response");
    
    $result = json_decode($response, true);
    
    // SunPay usually returns URL in 'payInfo'
    if (isset($result['respCode']) && $result['respCode'] == 'SUCCESS' && isset($result['payInfo'])) {
        
        $paymentUrl = $result['payInfo'];
        
        // --- STEP C: UPDATE DATABASE WITH URL ---
        $updateSql = "UPDATE transactions SET payment_url = ? WHERE mch_order_no = ?";
        $upStmt = $conn->prepare($updateSql);
        if ($upStmt) {
            $upStmt->bind_param("ss", $paymentUrl, $mch_order_no);
            $upStmt->execute();
            $upStmt->close();
            writeLog("URL Saved Successfully");
        }
        
        // Return Success to Frontend
        echo json_encode(['respCode' => 'SUCCESS', 'payInfo' => $paymentUrl]);
        
    } else {
        // Agar fail hua
        writeLog("SunPay Returned Fail");
        echo $response;
    }
}
?>