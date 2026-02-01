<?php
// kajana.php - Secure Payout Interface with Quick Select
session_start();
require_once '../config.php';

// 1. Security Check
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    die("Access Denied: You are not authorized.");
}

// 2. Extra Password Logic
$ACCESS_PASS = "9717716323"; 

// Logout/Lock Logic
if (isset($_GET['lock'])) {
    unset($_SESSION['kajana_unlocked']);
    header("Location: kajana.php");
    exit;
}

if (isset($_POST['unlock_pass'])) {
    if ($_POST['unlock_pass'] === $ACCESS_PASS) {
        $_SESSION['kajana_unlocked'] = true;
    } else {
        $error_msg = "Wrong Password!";
    }
}

// Lock Screen
if (!isset($_SESSION['kajana_unlocked'])) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Locked - Kajana</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body { background: #0d1117; font-family: sans-serif; }</style>
</head>
<body class="h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-[#1f2937] p-8 rounded-2xl shadow-2xl text-center border border-gray-700">
        <div class="w-16 h-16 bg-red-500/10 rounded-full flex items-center justify-center mx-auto mb-4 text-red-500">
            <i class="fas fa-lock text-2xl"></i>
        </div>
        <h2 class="text-white text-xl font-bold mb-2">Restricted Area</h2>
        <form method="POST">
            <input type="password" name="unlock_pass" placeholder="Enter Password" class="w-full bg-[#111827] border border-gray-600 rounded-xl px-4 py-3 text-white mb-4 text-center tracking-widest">
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl transition">Unlock</button>
        </form>
    </div>
</body>
</html>
<?php
    exit; 
}

// --- MAIN PAGE LOGIC ---

// Fetch Saved Beneficiaries
$saved_bens = [];
try {
    $stmt = $pdo->query("SELECT * FROM beneficiaries ORDER BY last_used DESC");
    $saved_bens = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table shayad abhi bani nahi, ignore error
}

$banks = [
    'IDPT0021' => 'State Bank of India (SBI)',
    'IDPT0004' => 'HDFC Bank',
    'IDPT0007' => 'ICICI Bank',
    'IDPT0005' => 'Punjab National Bank (PNB)',
    'IDPT0001' => 'Canara Bank',
    'IDPT0016' => 'Axis Bank',
    'IDPT0011' => 'Kotak Mahindra Bank',
    'IDPT0025' => 'Bank of Baroda',
    'IDPT0010' => 'Union Bank of India',
    'IDPT0003' => 'Federal Bank',
    'IDPT0012' => 'IDFC First Bank',
    'IDPT0019' => 'Yes Bank',
    'IDPT0024' => 'Central Bank of India',
    'IDPT0006' => 'Indian Bank',
    'IDPT0022' => 'Indian Overseas Bank',
    'IDPT0023' => 'Bandhan Bank',
    'IDPT0017' => 'UCO Bank',
    'IDPT0002' => 'DCB Bank',
    'IDPT0008' => 'Syndicate Bank',
    'IDPT0009' => 'Karur Vysya Bank',
    'IDPT0013' => 'Andhra Bank',
    'IDPT0014' => 'Karnataka Bank',
    'IDPT0018' => 'South Indian Bank',
    'IDPT0020' => 'Standard Chartered Bank',
    'IDPT0099' => 'UPI Transfer' 
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Kajana - Quick Payout</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #0d1117; color: #e5e7eb; }
        .bg-glass { background: linear-gradient(145deg, #1f2937, #111827); border: 1px solid #374151; }
        .input-field { background: #0f141e; border: 1px solid #374151; transition: all 0.2s; font-size: 16px; }
        .input-field:focus { border-color: #8b5cf6; background: #151b26; outline: none; }
    </style>
</head>
<body class="min-h-screen flex flex-col p-4 sm:p-6 lg:p-8">

    <div class="w-full max-w-lg mx-auto mb-6 flex justify-between items-center">
        <a href="index.php" class="flex items-center gap-2 text-gray-400 hover:text-white transition bg-gray-800 px-4 py-2 rounded-lg border border-gray-700 text-sm font-medium">
            <i class="fas fa-arrow-left"></i> Dashboard
        </a>
        <div class="flex gap-2">
            <a href="payout_history.php" class="flex items-center gap-2 text-blue-400 hover:text-blue-300 transition bg-blue-900/20 px-4 py-2 rounded-lg border border-blue-800/30 text-sm font-medium">
                <i class="fas fa-history"></i>
            </a>
            <a href="?lock=true" class="flex items-center gap-2 text-red-400 hover:text-red-300 transition bg-red-900/20 px-4 py-2 rounded-lg border border-red-800/30 text-sm font-medium">
                <i class="fas fa-lock"></i>
            </a>
        </div>
    </div>

    <div class="w-full max-w-lg mx-auto bg-glass rounded-2xl shadow-2xl p-6 relative overflow-hidden">
        
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-purple-600 via-blue-500 to-indigo-500"></div>

        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Payout Terminal</h1>
                <p class="text-xs text-gray-400 mt-0.5">Status: <span class="text-green-400 font-bold">UNLOCKED</span></p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500/20 to-blue-500/20 border border-white/5 flex items-center justify-center text-purple-400 shadow-lg">
                <i class="fas fa-paper-plane text-xl"></i>
            </div>
        </div>

        <?php if(count($saved_bens) > 0): ?>
        <div class="mb-6 bg-blue-500/10 p-3 rounded-xl border border-blue-500/20">
            <label class="block text-xs font-bold text-blue-300 mb-2 uppercase tracking-wider">
                <i class="fas fa-bolt mr-1"></i> Quick Select (Saved Accounts)
            </label>
            <div class="relative">
                <select id="quickSelect" onchange="fillDetails(this.value)" class="w-full bg-[#111827] border border-blue-500/30 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-400 cursor-pointer">
                    <option value="">-- Select a Beneficiary --</option>
                    <?php foreach($saved_bens as $ben): ?>
                        <option value='<?php echo json_encode($ben); ?>'>
                            <?php echo $ben['account_name']; ?> (<?php echo substr($ben['account_number'], -4); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="absolute right-3 top-2.5 text-blue-400 pointer-events-none text-xs">
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <form id="payoutForm" class="space-y-5">
            
            <div>
                <label class="block text-xs font-semibold text-gray-400 mb-2 uppercase tracking-wider">Select Bank</label>
                <div class="relative">
                    <select id="bank_code" name="bank_code" class="input-field w-full rounded-xl px-4 py-3.5 text-white appearance-none cursor-pointer shadow-inner">
                        <option value="" disabled selected>-- Choose Receiving Bank --</option>
                        <?php foreach($banks as $code => $name): ?>
                            <option value="<?php echo $code; ?>"><?php echo $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="absolute right-4 top-4 text-gray-500 pointer-events-none">
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-400 mb-2 uppercase tracking-wider">Account Holder Name</label>
                    <div class="relative">
                        <i class="fas fa-user absolute left-4 top-4 text-gray-600"></i>
                        <input type="text" id="account_name" name="account_name" placeholder="Name as per bank records" class="input-field w-full rounded-xl pl-10 pr-4 py-3.5 text-white shadow-inner">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-400 mb-2 uppercase tracking-wider">Account Number</label>
                    <div class="relative">
                        <i class="fas fa-hashtag absolute left-4 top-4 text-gray-600"></i>
                        <input type="tel" id="account_number" name="account_number" placeholder="Enter Account No." class="input-field w-full rounded-xl pl-10 pr-4 py-3.5 text-white font-mono shadow-inner tracking-wide">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-400 mb-2 uppercase tracking-wider">IFSC Code</label>
                    <div class="relative">
                        <i class="fas fa-building absolute left-4 top-4 text-gray-600"></i>
                        <input type="text" id="ifsc_code" name="ifsc_code" placeholder="SBIN0001234" class="input-field w-full rounded-xl pl-10 pr-4 py-3.5 text-white font-mono uppercase shadow-inner">
                    </div>
                </div>
            </div>

            <div class="pt-2">
                <label class="block text-xs font-semibold text-gray-400 mb-2 uppercase tracking-wider">Transfer Amount</label>
                <div class="relative">
                    <span class="absolute left-4 top-3.5 text-gray-400 font-bold text-lg">₹</span>
                    <input type="number" id="amount" name="amount" placeholder="0.00" class="input-field w-full rounded-xl pl-10 pr-4 py-3.5 text-white text-xl font-bold shadow-inner">
                </div>
            </div>

            <button type="submit" id="submitBtn" class="w-full bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 text-white font-bold py-4 rounded-xl transition-all transform active:scale-[0.98] shadow-lg shadow-purple-900/30 flex items-center justify-center gap-3 mt-4 text-sm uppercase tracking-wide">
                <span>Initiate Transfer</span> <i class="fas fa-arrow-right"></i>
            </button>

        </form>

        <div id="responseArea" class="hidden mt-6 p-4 rounded-xl text-sm border animate-pulse"></div>
        
        <p class="text-center text-[10px] text-gray-600 mt-6">
            Session Active • <a href="?lock=true" class="text-red-500 hover:underline">Lock Terminal</a>
        </p>
    </div>

    <script>
        // --- AUTO FILL FUNCTION ---
        function fillDetails(jsonData) {
            if(!jsonData) return;
            const data = JSON.parse(jsonData);
            
            document.getElementById('account_name').value = data.account_name;
            document.getElementById('account_number').value = data.account_number;
            document.getElementById('ifsc_code').value = data.ifsc;
            document.getElementById('bank_code').value = data.bank_code;
            
            // Highlight effect
            const inputs = ['account_name', 'account_number', 'ifsc_code', 'bank_code'];
            inputs.forEach(id => {
                const el = document.getElementById(id);
                el.style.borderColor = '#60a5fa'; // Blue highlight
                setTimeout(() => el.style.borderColor = '#374151', 1000); // Remove after 1 sec
            });
        }

        document.getElementById('payoutForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('submitBtn');
            const responseDiv = document.getElementById('responseArea');
            const originalText = btn.innerHTML;

            const name = document.getElementById('account_name').value.trim();
            const acc = document.getElementById('account_number').value.trim();
            const ifsc = document.getElementById('ifsc_code').value.trim();
            const amt = document.getElementById('amount').value;

            if(name.length < 5) { alert("Account Name must be at least 5 characters long."); return; }
            if(!acc || !ifsc || !amt) { alert("All fields are required."); return; }

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-circle-notch fa-spin text-lg"></i> <span class="ml-2">Processing Request...</span>';
            responseDiv.classList.add('hidden');
            responseDiv.classList.remove('animate-pulse');

            const formData = new FormData(this);

            fetch('kajana_api.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                responseDiv.classList.remove('hidden');
                if(data.status === 'success') {
                    responseDiv.className = 'mt-6 p-4 rounded-xl text-sm border bg-green-500/10 border-green-500/30 text-green-400';
                    responseDiv.innerHTML = `<div class="flex items-start gap-3"><i class="fas fa-check-circle text-lg mt-0.5"></i><div><strong class="block text-green-300 mb-1">Transfer Initiated!</strong><span class="opacity-90">${data.message}</span><div class="mt-2 text-xs font-mono bg-black/20 p-2 rounded">Order ID: ${data.order_id}</div></div></div>`;
                    document.getElementById('payoutForm').reset();
                    
                    // Refresh page after 2 seconds to update the saved list
                    setTimeout(() => location.reload(), 2000);
                } else {
                    responseDiv.className = 'mt-6 p-4 rounded-xl text-sm border bg-red-500/10 border-red-500/30 text-red-400';
                    responseDiv.innerHTML = `<div class="flex items-start gap-3"><i class="fas fa-exclamation-circle text-lg mt-0.5"></i><div><strong class="block text-red-300 mb-1">Transfer Failed</strong><span class="opacity-90">${data.message}</span></div></div>`;
                }
            })
            .catch(error => {
                responseDiv.classList.remove('hidden');
                responseDiv.className = 'mt-6 p-4 rounded-xl text-sm border bg-yellow-500/10 border-yellow-500/30 text-yellow-400';
                responseDiv.innerHTML = `<div class="flex items-start gap-3"><i class="fas fa-wifi text-lg mt-0.5"></i><div><strong class="block text-yellow-300 mb-1">Network Error</strong><span class="opacity-90">Could not connect to server. Please try again.</span></div></div>`;
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        });
    </script>
</body>
</html>