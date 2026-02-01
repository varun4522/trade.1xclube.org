<?php
// withdraw-history.php isme h 
include 'db.php'; // Database Connection
session_start();

// Timezone setup
date_default_timezone_set('Asia/Kolkata');

// Login Check
if(!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch Only Withdrawal Transactions
$sql = "SELECT * FROM transactions WHERE user_id = ? AND (type = 'Withdraw' OR type = 'withdrawal') ORDER BY id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
    <title>Withdraw History</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        * { font-family: 'Poppins', sans-serif; -webkit-tap-highlight-color: transparent; }
        
        body {
            background: #0f172a;
            color: white;
            padding-bottom: 90px; /* Space for Bottom Nav */
            min-height: 100vh;
        }

        /* Background Gradient Mesh */
        .bg-mesh {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: radial-gradient(circle at 50% -20%, #1e293b, #0f172a);
            z-index: -1;
        }

        /* Glass Header */
        .glass-header {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            position: sticky; top: 0; z-index: 50;
        }

        /* Transaction Card */
        .tx-card {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(5px);
            transition: transform 0.2s;
        }
        .tx-card:active { transform: scale(0.98); background: rgba(30, 41, 59, 0.8); }

        /* Status Colors */
        .text-approved { color: #4ade80; }
        .bg-approved { background: rgba(74, 222, 128, 0.1); border: 1px solid rgba(74, 222, 128, 0.2); }
        
        .text-pending { color: #facc15; }
        .bg-pending { background: rgba(250, 204, 21, 0.1); border: 1px solid rgba(250, 204, 21, 0.2); }
        
        .text-rejected { color: #f87171; }
        .bg-rejected { background: rgba(248, 113, 113, 0.1); border: 1px solid rgba(248, 113, 113, 0.2); }

        /* Left Borders */
        .border-l-approved { border-left: 3px solid #4ade80; }
        .border-l-pending { border-left: 3px solid #facc15; }
        .border-l-rejected { border-left: 3px solid #f87171; }

        /* Bottom Nav CSS */
        .bottom-nav {
            position: fixed; bottom: 0; left: 0; right: 0;
            background: rgba(30, 41, 59, 0.95);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex; justify-content: space-around; padding: 12px 0 10px;
            z-index: 1000; backdrop-filter: blur(10px);
        }
        .nav-item {
            display: flex; flex-direction: column; align-items: center;
            text-decoration: none; color: rgba(255, 255, 255, 0.7);
            font-size: 11px; font-weight: 500; width: 60px; padding: 5px 0;
            border-radius: 15px; transition: all 0.3s ease;
        }
        .nav-item i { font-size: 20px; margin-bottom: 4px; transition: all 0.3s ease; }
        .nav-item.active { color: white; transform: translateY(-5px); background: rgba(139, 92, 246, 0.2); }
        .nav-item.active i { color: #8b5cf6; text-shadow: 0 0 10px rgba(139, 92, 246, 0.5); }
    </style>
</head>
<body>

    <div class="bg-mesh"></div>

    <div class="glass-header px-4 py-4 flex items-center gap-4 shadow-lg">
        <a href="withdraw.html" class="w-9 h-9 rounded-full bg-white/5 border border-white/10 flex items-center justify-center text-white hover:bg-white/20 transition active:scale-95">
            <i class="fas fa-arrow-left text-sm"></i>
        </a>
        <h1 class="text-lg font-bold tracking-wide">Withdrawal History</h1>
    </div>

    <div class="max-w-md mx-auto p-4 space-y-4">
        
        <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <?php
                    // Status Logic
                    $status = strtolower($row['status'] ?? 'pending');
                    
                    if ($status == 'approved' || $status == 'completed') {
                        $badgeClass = 'bg-approved text-approved';
                        $borderClass = 'border-l-approved';
                        $statusText = 'Success';
                        $iconClass = 'fa-check-circle text-approved';
                        $iconBg = 'bg-green-500/10';
                    } elseif ($status == 'pending') {
                        $badgeClass = 'bg-pending text-pending';
                        $borderClass = 'border-l-pending';
                        $statusText = 'Pending';
                        $iconClass = 'fa-clock text-pending';
                        $iconBg = 'bg-yellow-500/10';
                    } else {
                        $badgeClass = 'bg-rejected text-rejected';
                        $borderClass = 'border-l-rejected';
                        $statusText = 'Failed';
                        $iconClass = 'fa-times-circle text-rejected';
                        $iconBg = 'bg-red-500/10';
                    }

                    // Date Formatting
                    $phpDate = strtotime($row['created_at']);
                    $dateStr = date('d/m/Y', $phpDate); 
                    $timeStr = date('h:i A', $phpDate);

                    // ID Display
                    $displayId = $row['mch_order_no'] ? $row['mch_order_no'] : ($row['transaction_id'] ? $row['transaction_id'] : 'TXN'.$row['id']);
                ?>

                <div class="tx-card p-4 rounded-xl animate__animated animate__fadeInUp <?php echo $borderClass; ?>">
                    
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full <?php echo $iconBg; ?> flex items-center justify-center border border-white/5">
                                <i class="fas fa-wallet text-sm <?php echo str_replace('fa-', '', $iconClass); ?>"></i>
                            </div>
                            
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold text-white uppercase tracking-wide">Withdraw</span>
                                    <span class="text-[10px] px-1.5 py-0.5 rounded font-bold <?php echo $badgeClass; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
                                </div>
                                
                                <div class="text-[10px] text-gray-400 font-medium mt-0.5">
                                    <?php echo $dateStr; ?> <span class="opacity-50 mx-1">|</span> <?php echo $timeStr; ?>
                                </div>
                                
                                <div class="text-[10px] text-gray-600 font-mono mt-0.5 truncate w-32">
                                    <?php echo $displayId; ?>
                                </div>
                            </div>
                        </div>

                        <div class="text-right">
                            <div class="text-base font-bold text-red-400">
                                -₹<?php echo number_format($row['amount'], 2); ?>
                            </div>
                            <div class="text-[10px] text-gray-500 mt-1">
                                Bank Transfer
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($row['admin_notes'])): ?>
                        <div class="mt-3 pt-2 border-t border-white/5 flex items-start gap-2">
                            <i class="fas fa-info-circle text-[10px] mt-0.5 <?php echo ($status == 'rejected') ? 'text-red-400' : 'text-gray-400'; ?>"></i>
                            <div class="text-[11px] leading-tight">
                                <span class="<?php echo ($status == 'rejected') ? 'text-red-400 font-medium' : 'text-gray-400 font-medium'; ?>">
                                    Reason:
                                </span>
                                <span class="text-gray-300 ml-1">
                                    <?php echo htmlspecialchars($row['admin_notes']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>
                    </div>

            <?php endwhile; ?>
        <?php else: ?>
            
            <div class="text-center py-20 animate__animated animate__fadeIn">
                <div class="w-16 h-16 bg-white/5 rounded-full flex items-center justify-center mx-auto mb-4 border border-white/5">
                    <i class="fas fa-wallet text-2xl text-gray-600"></i>
                </div>
                <p class="text-gray-400 font-medium">No withdrawals yet</p>
                <p class="text-xs text-gray-600 mt-1">Your withdrawal history will appear here</p>
            </div>

        <?php endif; ?>

    </div>

    <div class="bottom-nav">
        <a href="index.php" class="nav-item">
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
        
        <a href="main.php" class="nav-item">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
    </div>

</body>
</html>