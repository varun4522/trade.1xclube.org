<?php
include 'db.php';
session_start();

// Login check
if(!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// 1. Fetch User details
$sql = "SELECT balance, referral_earnings, phone, username FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// 2. Fetch Transactions (ONLY APPROVED)
// NOTE: Agar aapke database me 'approved' ki jagah 'success' ya 'completed' likha hai, 
// to niche wali line me 'approved' ko change kar dena.
$trans_query = "SELECT * FROM transactions WHERE user_id = ? AND status = 'approved' ORDER BY id DESC LIMIT 10";

$t_stmt = $conn->prepare($trans_query);
$t_stmt->bind_param("i", $user_id);
$t_stmt->execute();
$transactions = $t_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
    <title>My Wallet - Trade Club</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Inter:wght@300;400;600&display=swap');
        
        :root {
            --primary: #ff6b35;
            --secondary: #ff8c42;
            --bg-dark: #ffffff;
            --card-bg: rgba(255, 255, 255, 0.95);
            --nav-bg: #fff5f0;
        }

        * { font-family: 'Inter', sans-serif; }
        h1, h2, h3, h4, .gaming-font { font-family: 'Rajdhani', sans-serif; }
        
        body {
            background: linear-gradient(135deg, #ffffff 0%, #fff5f0 25%, #ffe8dc 50%, #ffd4c8 100%);
            color: #1a1a1a;
            padding-bottom: 75px;
            background-image: 
                radial-gradient(circle at 50% 0%, rgba(255, 107, 53, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 100% 100%, rgba(255, 140, 66, 0.1) 0%, transparent 50%);
            min-height: 100vh;
        }

        /* Glass Components */
        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }

        /* Wallet Card Gradient */
        .wallet-card {
            background: linear-gradient(135deg, #ffffff 0%, #fff8f4 100%);
            position: relative;
            overflow: hidden;
            border: 2px solid rgba(255, 107, 53, 0.2);
        }
        
        .wallet-card::after {
            content: '';
            position: absolute;
            top: 0; right: 0; bottom: 0; left: 0;
            background: url('https://www.transparenttextures.com/patterns/carbon-fibre.png');
            opacity: 0.1;
            pointer-events: none;
        }

        /* Transaction Item */
        .transaction-item {
            transition: all 0.2s ease;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .transaction-item:hover {
            transform: translateX(5px);
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(139, 92, 246, 0.2);
        }

        /* Gradient Button */
        .btn-gaming {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            z-index: 1;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .btn-gaming:active { transform: scale(0.98); }

        /* --- PREMIUM BOTTOM NAVIGATION --- */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(30, 41, 59, 0.95);
            border-top: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: space-around;
            padding: 12px 0 10px;
            z-index: 1000;
            backdrop-filter: blur(12px);
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: rgba(255,255,255,0.65);
            font-size: 12px;
            transition: all 0.3s ease;
            padding: 6px 16px;
            border-radius: 16px;
            position: relative;
        }

        .nav-item i {
            font-size: 20px;
            margin-bottom: 4px;
        }

        .nav-item.active {
            background: rgba(139, 92, 246, 0.25);
            color: #fff;
            transform: translateY(-6px);
        }

        .nav-item.active i {
            color: #8b5cf6;
            text-shadow: 0 0 10px rgba(139,92,246,0.8);
        }

        .nav-item.active::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 6px;
            height: 6px;
            background: #8b5cf6;
            border-radius: 50%;
            box-shadow: 0 0 10px #8b5cf6;
        }

        .nav-item:hover {
            color: #fff;
            background: rgba(255,255,255,0.05);
        }

        .text-glow { text-shadow: 0 0 10px rgba(255,255,255,0.3); }
    </style>
</head>
<body>

    <div class="px-6 py-6 flex items-center gap-4">
        <button onclick="window.history.back()" class="w-10 h-10 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center text-gray-400 hover:text-white transition-colors">
            <i class="fas fa-arrow-left"></i>
        </button>
        <h1 class="text-2xl font-bold text-white gaming-font tracking-wide">My Wallet</h1>
    </div>

    <div class="container mx-auto px-4">
        
        <div class="wallet-card rounded-2xl p-6 mb-8 shadow-xl relative overflow-hidden">
            <div class="relative z-10 text-center">
                <p class="text-gray-400 text-xs font-medium uppercase tracking-widest mb-2">Total Balance</p>
                <h1 class="text-5xl font-bold text-white mb-8 gaming-font text-glow">
                    ₹<?php echo number_format($user['balance'], 2); ?>
                </h1>
                
                <div class="grid grid-cols-2 gap-4">
                    <button onclick="location.href='deposit.php'" class="btn-gaming py-3.5 rounded-xl font-bold text-white shadow-lg flex items-center justify-center gap-2">
                        <i class="fas fa-plus-circle"></i> Deposit
                    </button>
                    <button onclick="location.href='withdraw.html'" class="bg-white/10 border border-white/10 text-white py-3.5 rounded-xl font-bold hover:bg-white/20 transition-all flex items-center justify-center gap-2">
                        <i class="fas fa-arrow-down"></i> Withdraw
                    </button>
                </div>
            </div>
            
            <div class="absolute top-0 right-0 p-4 opacity-10">
                <i class="fas fa-wallet text-9xl text-white transform rotate-12 translate-x-4 translate-y-4"></i>
            </div>
            <div class="absolute bottom-0 left-0 w-full h-1/2 bg-gradient-to-t from-violet-900/20 to-transparent pointer-events-none"></div>
        </div>

        <div class="flex justify-between items-end mb-4 px-1">
            <h3 class="text-lg font-bold text-white gaming-font border-l-4 border-violet-500 pl-3">Recent Activity</h3>
            <button class="text-xs text-violet-400 font-medium hover:text-violet-300 transition-colors">Only Approved</button>
        </div>

        <div class="space-y-3 pb-4">
            <?php 
            if ($transactions->num_rows > 0) {
                while($row = $transactions->fetch_assoc()) { 
                    
                    $typeLower = strtolower($row['type']);
                    
                    // Logic: Withdraw = Red (-), Deposit/Win = Green (+)
                    if (strpos($typeLower, 'withdraw') !== false) {
                        $isDeposit = false;
                    } else {
                        $isDeposit = true;
                    }

                    $icon = $isDeposit ? 'fa-arrow-down' : 'fa-arrow-up';
                    
                    $colorClass = $isDeposit ? 'text-green-400' : 'text-red-500'; 
                    $bgClass = $isDeposit ? 'bg-green-500/10 text-green-400 border-green-500/20' : 'bg-red-500/10 text-red-500 border-red-500/20';
                    $sign = $isDeposit ? '+' : '-';
            ?>
                <div class="transaction-item p-4 rounded-xl flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 <?php echo $bgClass; ?> border rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas <?php echo $icon; ?> text-sm"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-gray-200 capitalize tracking-wide"><?php echo htmlspecialchars($row['type']); ?></p>
                            <p class="text-[10px] text-gray-500 font-mono mt-0.5">
                                <?php echo date('d M, h:i A', strtotime($row['created_at'])); ?>
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="<?php echo $colorClass; ?> font-bold text-base gaming-font">
                            <?php echo $sign . '₹' . number_format(abs($row['amount']), 2); ?>
                        </p>
                        <p class="text-[9px] text-gray-400 uppercase tracking-wider">Approved</p>
                    </div>
                </div>
            <?php 
                }
            } else {
            ?>
                <div class="text-center py-12 glass-card rounded-xl border-dashed border-2 border-gray-700">
                    <div class="w-16 h-16 bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-history text-2xl text-gray-500"></i>
                    </div>
                    <p class="text-gray-400 text-sm">No approved transactions found</p>
                </div>
            <?php } ?>
        </div>
    </div>

        <!-- Bottom Navigation -->
        <div class="bottom-nav">
            <a href="index.php" class="nav-item ">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>

            <a href="kyc.html" class="nav-item">
                <i class="fas fa-gamepad"></i>
                <span>Kyc</span>
            </a>

            <a href="wallet.php" class="nav-item active">
                <i class="fas fa-wallet"></i>
                <span>Wallet</span>
            </a>

            <a href="refer.php" class="nav-item">
                <i class="fas fa-gift"></i>
                <span>Promos</span>
            </a>

            <a href="profile.php" class="nav-item">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        </div>

</body>
</html>