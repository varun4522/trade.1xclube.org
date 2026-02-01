<?php
require_once '../config.php';
require_once 'sunpay.php';

/* LOG RAW DATA */
file_put_contents(
  __DIR__ . '/sunpay_log.txt',
  date('Y-m-d H:i:s') . ' ' . json_encode($_POST) . PHP_EOL,
  FILE_APPEND
);

$data = $_POST;

/* BASIC CHECK */
if (empty($data)) {
    exit('no data');
}

/* EXTRACT SIGN */
$recvSign = $data['sign'] ?? '';
unset($data['sign'], $data['signType']);

/* REMOVE EMPTY FIELDS (IMPORTANT) */
$data = array_filter($data, fn($v) => $v !== '' && $v !== null);

/* VERIFY SIGN */
$calcSign = sunpaySign($data);
if ($calcSign !== $recvSign) {
    exit('invalid sign');
}

/* CHECK PAYMENT STATUS */
if (($data['tradeResult'] ?? '') !== '1') {
    exit('fail');
}

$conn = getDBConnection();
$orderId = $data['mchOrderNo'];
$amount  = $data['amount'];

/* AVOID DOUBLE CREDIT */
$res = $conn->query("SELECT status FROM recharge WHERE order_id='$orderId'");
$row = $res->fetch_assoc();
if ($row && $row['status'] === 'SUCCESS') {
    exit('success');
}

/* UPDATE RECHARGE */
$conn->query("
  UPDATE recharge
  SET status='SUCCESS'
  WHERE order_id='$orderId'
");

/* CREDIT WALLET */
$conn->query("
  UPDATE profiles p
  JOIN recharge r ON p.id = r.user_id
  SET p.balance = p.balance + $amount
  WHERE r.order_id='$orderId'
");

echo "success";
