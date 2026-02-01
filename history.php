<?php
include 'db.php';
session_start();

if(!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// Database se sirf BET transactions fetch karna (Trade Club game ki history)
// Maan lete hain ki aapke table mein 'bet' ya 'game_bet' type ki entries hain
$sql = "SELECT * FROM transactions WHERE user_id = ? AND (type = 'bet' OR type = 'game') ORDER BY id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bet History - Trade Club</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Poppins', sans-serif; background: #0f172a; color: white; }
        .glass { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
        .win-gradient { background: linear-gradient(90deg, rgba(34, 197, 94, 0.1) 0%, transparent 100%); }
        .loss-gradient { background: linear-gradient(90deg, rgba(239, 68, 68, 0.1) 0%, transparent 100%); }
    </style>
</head>
<body class="pb-24">

    <div class="container mx-auto px-6 py-8">
        <div class="flex items-center mb-8">
            <button onclick="window.history.back()" class="w-10 h-10 rounded-full glass flex items-center justify-center mr-4">
                <i class="fas fa-chevron-left"></i>
            </button>
            <h1 class="text-xl font-bold">Bet History</h1>
        </div>

        <div class="space-y-4">
            <?php 
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) { 
                    // Profit/Loss calculate karein
                    $amount = $row['amount'];
                    $isWin = ($amount > 0);
                    $cardClass = $isWin ? 'win-gradient border-l-4 border-green-500' : 'loss-gradient border-l-4 border-red-500';
                    $statusText = $isWin ? 'WINNER' : 'LOST';
                    $statusColor = $isWin ? 'text-green-400' : 'text-red-400';
            ?>
                <div class="glass p-5 rounded-[28px] flex items-center justify-between <?php echo $cardClass; ?>">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-white/5 rounded-2xl flex items-center justify-center">
                            <i class="fas fa-gamepad <?php echo $statusColor; ?>"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold uppercase">Trade Club Bet</p>
                            <p class="text-[10px] text-gray-500"><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-black text-lg <?php echo $statusColor; ?>">
                            <?php echo ($isWin ? '+' : '') . '₹' . number_format($amount, 2); ?>
                        </p>
                        <p class="text-[10px] font-bold opacity-60 uppercase"><?php echo $statusText; ?></p>
                    </div>
                </div>
            <?php 
                }
            } else {
                echo '<div class="text-center py-20 text-gray-500">
                        <i class="fas fa-receipt text-5xl mb-4 opacity-20"></i>
                        <p>No bet records found.</p>
                      </div>';
            }
            ?>
        </div>
    </div>

    <nav class="fixed bottom-0 left-0 right-0 glass border-t border-white/10 px-6 py-3 flex justify-between items-center rounded-t-[40px] z-50">
        <a href="main.php" class="flex flex-col items-center text-gray-400"><i class="fas fa-house text-xl mb-1"></i><span class="text-[8px]">Home</span></a>
        <a href="refer.html" class="flex flex-col items-center text-gray-400"><i class="fas fa-sack-dollar text-xl mb-1"></i><span class="text-[8px]">Earn</span></a>
        <a href="wallet.php" class="flex flex-col items-center text-blue-500"><i class="fas fa-wallet text-xl mb-1"></i><span class="text-[8px]">Wallet</span></a>
        <a href="profile.php" class="flex flex-col items-center text-gray-400"><i class="fas fa-user text-xl mb-1"></i><span class="text-[8px]">Profile</span></a>
    </nav>

</body>
</html>