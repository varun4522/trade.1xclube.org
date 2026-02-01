<?php
// test_callback.php - Manual Payout Callback Simulator
// Is file ko browser mein run karke tum "Fake Success" bhej sakte ho testing ke liye.

// 1. Settings
$target_url = "https://play.1xclube.org/kdx/payout_callback.php"; // Tumhara Callback URL
$payment_key = "NULLKI70AXG5T3NTFJZTYJX8EFILY09D"; // Tumhari Asli Key

// 2. Order Details (Ise Change kar sakte ho testing ke liye)
// IMPORTANT: Yahan wo Order ID dalo jo tumhare database mein 'pending' hai
$order_id_to_test = "PAY1767340998304"; 
$amount = "100.00";

// 3. Mock Data (Jaisa NekPayment bhejta hai)
$data = [
    'tradeResult'    => '1', // 1 = Success, 2 = Failed
    'merTransferId'  => $order_id_to_test,
    'merNo'          => '100567121',
    'tradeNo'        => 'NEKTEST' . time(), // Fake Gateway Transaction ID
    'transferAmount' => $amount,
    'applyDate'      => date('Y-m-d H:i:s'),
    'version'        => '1.0',
    'respCode'       => 'SUCCESS'
];

// 4. Generate Valid Signature
$sign_params = array_filter($data, function($v) { return $v !== '' && $v !== null; });
ksort($sign_params);
$str = '';
foreach ($sign_params as $k => $v) {
    $str .= $k . '=' . $v . '&';
}
$str .= 'key=' . $payment_key;
$data['sign'] = md5($str);
$data['signType'] = 'MD5';

// 5. Send POST Request (Using CURL)
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $target_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// SSL verify false kar rahe hain taaki local/test mein error na aaye
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

// 6. Output Result
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Callback Tester</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white flex items-center justify-center min-h-screen p-4">
    <div class="max-w-2xl w-full bg-gray-800 p-6 rounded-xl border border-gray-700 shadow-2xl">
        <h1 class="text-2xl font-bold mb-4 text-blue-400">Manual Callback Tester</h1>
        
        <div class="mb-6">
            <h3 class="text-gray-400 text-sm uppercase font-bold mb-2">Data Sent:</h3>
            <pre class="bg-black/50 p-4 rounded-lg text-xs text-green-400 overflow-x-auto"><?php print_r($data); ?></pre>
        </div>

        <div class="mb-6">
            <h3 class="text-gray-400 text-sm uppercase font-bold mb-2">Response from payout_callback.php:</h3>
            <div class="p-4 rounded-lg border <?php echo ($response === 'success') ? 'bg-green-900/20 border-green-500 text-green-400' : 'bg-red-900/20 border-red-500 text-red-400'; ?>">
                <span class="text-xl font-bold"><?php echo htmlspecialchars($response ?: "No Response / Empty"); ?></span>
                <?php if($error): ?>
                    <p class="text-sm text-red-300 mt-2">CURL Error: <?php echo $error; ?></p>
                <?php endif; ?>
            </div>
        </div>

        <p class="text-gray-500 text-xs text-center">
            Agar response "success" hai, to Database aur payout_logs.txt check karo.
        </p>
    </div>
</body>
</html>