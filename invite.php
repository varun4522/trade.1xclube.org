<?php
include 'db.php';
session_start();

if(!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// User ka referral code fetch karna (Maan lijiye users table mein 'referral_code' column hai)
$query = "SELECT referral_code, balance FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

$ref_code = $user_data['referral_code'] ?? 'REF' . $user_id . rand(10,99);
$invite_link = "http://play.1xclube.org/login.html?ref=" . $ref_code;

// Total referrals count karna
$count_query = "SELECT COUNT(*) as total FROM users WHERE referred_by = ?";
$c_stmt = $conn->prepare($count_query);
$c_stmt->bind_param("s", $ref_code);
$c_stmt->execute();
$total_ref = $c_stmt->get_result()->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invite & Earn - Trade Club</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap');
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #ffffff, #fff5f0, #ffe8dc); color: #1a1a1a; }
        .glass { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(12px); border: 2px solid rgba(255, 107, 53, 0.2); }
        .gradient-text { background: linear-gradient(90deg, #ff6b35, #ff8c42); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .share-btn { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .share-btn:active { transform: scale(0.9); }
    </style>
</head>
<body class="pb-24">

    <div class="p-6 flex items-center justify-between">
        <button onclick="window.history.back()" class="w-10 h-10 rounded-2xl glass flex items-center justify-center">
            <i class="fas fa-chevron-left"></i>
        </button>
        <h1 class="text-lg font-bold">Invite Friends</h1>
        <div class="w-10"></div>
    </div>

    <div class="px-6">
        <div class="glass rounded-[35px] p-8 text-center mb-6 overflow-hidden relative">
            <div class="absolute -top-10 -right-10 w-32 h-32 bg-blue-500/20 rounded-full blur-3xl"></div>
            <img src="https://cdn-icons-png.flaticon.com/512/8141/8141411.png" class="w-24 mx-auto mb-4 animate-bounce" alt="Gift">
            <h2 class="text-2xl font-extrabold mb-2">Refer & Earn <span class="gradient-text">₹50</span></h2>
            <p class="text-gray-400 text-xs">Invite your friends and get bonus when they make their first deposit.</p>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-6">
            <div class="glass p-5 rounded-[28px] text-center">
                <p class="text-[10px] text-gray-500 uppercase font-bold tracking-wider mb-1">Total Invites</p>
                <p class="text-xl font-black text-blue-400"><?php echo $total_ref; ?></p>
            </div>
            <div class="glass p-5 rounded-[28px] text-center">
                <p class="text-[10px] text-gray-500 uppercase font-bold tracking-wider mb-1">Earnings</p>
                <p class="text-xl font-black text-green-400">₹<?php echo $total_ref * 50; ?></p>
            </div>
        </div>

        <div class="glass rounded-[30px] p-6 mb-8">
            <p class="text-sm font-semibold mb-4 text-gray-300">Your Referral Link</p>
            <div class="flex bg-[#0f172a] rounded-2xl p-2 border border-white/5">
                <input type="text" id="refLink" value="<?php echo $invite_link; ?>" readonly 
                    class="bg-transparent border-none outline-none flex-1 px-3 text-xs text-blue-300 font-medium">
                <button onclick="copyLink()" class="bg-blue-600 hover:bg-blue-500 px-4 py-2 rounded-xl text-xs font-bold share-btn">
                    COPY
                </button>
            </div>
        </div>

        <h3 class="text-sm font-bold mb-4 ml-2">How it works?</h3>
        <div class="space-y-4">
            <div class="flex items-start gap-4 glass p-4 rounded-2xl">
                <div class="w-8 h-8 bg-blue-500/10 rounded-lg flex items-center justify-center text-blue-400 font-bold">1</div>
                <p class="text-xs text-gray-300">Copy your link and share it with your friends on WhatsApp or Telegram.</p>
            </div>
            <div class="flex items-start gap-4 glass p-4 rounded-2xl">
                <div class="w-8 h-8 bg-green-500/10 rounded-lg flex items-center justify-center text-green-400 font-bold">2</div>
                <p class="text-xs text-gray-300">Your friend joins and makes a successful deposit of ₹500.</p>
            </div>
            <div class="flex items-start gap-4 glass p-4 rounded-2xl">
                <div class="w-8 h-8 bg-yellow-500/10 rounded-lg flex items-center justify-center text-yellow-400 font-bold">3</div>
                <p class="text-xs text-gray-300">You instantly receive ₹50 bonus in your game wallet!</p>
            </div>
        </div>
    </div>

    <div id="copyToast" class="fixed bottom-28 left-1/2 -translate-x-1/2 bg-green-500 text-white px-6 py-2 rounded-full text-xs font-bold hidden animate__animated animate__fadeInUp">
        Link Copied Successfully!
    </div>

    <nav class="fixed bottom-0 left-0 right-0 glass border-t border-white/10 px-6 py-3 flex justify-between items-center rounded-t-[40px] z-50">
        <a href="main.php" class="flex flex-col items-center text-gray-400"><i class="fas fa-house text-xl"></i></a>
        <a href="invite.php" class="flex flex-col items-center text-blue-500"><i class="fas fa-users text-xl"></i></a>
        <a href="wallet.php" class="flex flex-col items-center text-gray-400"><i class="fas fa-wallet text-xl"></i></a>
        <a href="profile.php" class="flex flex-col items-center text-gray-400"><i class="fas fa-user text-xl"></i></a>
    </nav>

    <script>
        function copyLink() {
            var copyText = document.getElementById("refLink");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            document.execCommand("copy");
            
            const toast = document.getElementById("copyToast");
            toast.classList.remove("hidden");
            setTimeout(() => toast.classList.add("hidden"), 2000);
        }
    </script>

</body>
</html>