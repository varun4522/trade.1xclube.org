<?php
include 'db.php';
session_start();

// Login check
if(!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// Database fetch
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) { session_destroy(); header("Location: login.html"); exit(); }

// Format UID
$formatted_uid = str_pad($user_id, 6, '0', STR_PAD_LEFT);

// --- ADDED COMMISSION LOGIC HERE ---
function calculateCommission($conn, $my_id) {
    // Count distinct users who have at least 1 approved deposit
    $sql = "SELECT COUNT(DISTINCT u.id) as active_count 
            FROM users u 
            JOIN transactions t ON u.id = t.user_id 
            WHERE u.referred_by = ? 
            AND t.type = 'deposit' 
            AND t.status = 'approved'";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $my_id);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['active_count'] ?? 0;
    
    return $count * 100; // ₹100 per active user
}

$total_commission = calculateCommission($conn, $user_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover"/>
  <title>My Profile</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Inter:wght@300;400;600&display=swap');
    
    :root {
      --bg-dark: #ffffff;
      --card-bg: rgba(255, 255, 255, 0.95);
      --primary: #ff6b35;
    }

    /* GLOBAL APP LAYOUT FIXES */
    html { height: -webkit-fill-available; }
    body { 
        margin: 0; padding: 0; width: 100vw; height: 100vh; height: 100dvh; 
        background: linear-gradient(135deg, #ffffff 0%, #fff5f0 25%, #ffe8dc 50%, #ffd4c8 100%);
        color: #1a1a1a; font-family: 'Inter', sans-serif;
        overflow: hidden;
        display: flex; flex-direction: column;
        touch-action: manipulation;
    }

    h1, h2, h3, h4, .gaming-font { font-family: 'Rajdhani', sans-serif; }

    /* SCROLLABLE CONTENT AREA */
    .content-scroll {
        flex: 1; /* Take remaining space */
        overflow-y: auto; /* Scroll inside this div */
        overflow-x: hidden;
        padding-bottom: calc(80px + env(safe-area-inset-bottom)); /* Extra space for bottom nav */
        width: 100%;
        display: flex; flex-direction: column;
        -webkit-overflow-scrolling: touch; /* Smooth scroll on iOS */
    }

    /* Ambient Background */
    .glow-bg {
        position: fixed;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(255, 107, 53, 0.15) 0%, rgba(0,0,0,0) 70%);
        border-radius: 50%;
        z-index: -1;
        top: -100px;
        left: 50%;
        transform: translateX(-50%);
        pointer-events: none;
    }

    /* Glass Cards */
    .glass-tile {
        background: linear-gradient(145deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.01) 100%);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 16px;
        transition: transform 0.2s, background 0.2s;
    }
    
    .glass-tile:active {
        transform: scale(0.98);
        background: rgba(255,255,255,0.08);
    }

    /* Stats Cards */
    .stat-card-1 {
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.1) 0%, rgba(139, 92, 246, 0) 100%);
        border: 1px solid rgba(139, 92, 246, 0.2);
    }
    .stat-card-2 {
        background: linear-gradient(135deg, rgba(6, 182, 212, 0.1) 0%, rgba(6, 182, 212, 0) 100%);
        border: 1px solid rgba(6, 182, 212, 0.2);
    }

    /* VIP Badge */
    .vip-badge {
        background: linear-gradient(90deg, #ffd700, #ffaa00);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        font-weight: 800;
        font-style: italic;
    }

        /* BOTTOM NAV - FIXED */
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

        /* Page content ke niche space */
        body { padding-bottom: 75px; }

  </style>
</head>
<body>

  <div class="glow-bg"></div>

  <div class="content-scroll">
      
      <div class="pt-10 pb-6 flex flex-col items-center relative w-full">
          <a href="change_password.php" class="absolute top-6 right-6 text-gray-400 hover:text-white p-2">
              <i class="fas fa-cog text-xl"></i>
          </a>

          <div class="relative mb-4">
              <div class="w-28 h-28 rounded-full flex items-center justify-center bg-gradient-to-tr from-violet-600 to-cyan-400 p-[3px] shadow-[0_0_20px_rgba(139,92,246,0.5)]">
                <img src="https://robohash.org/<?php echo $user_id; ?>.png?set=set4" 
                     class="w-full h-full rounded-full bg-[#13141a] object-cover border-4 border-[#0f1014]">
              </div>
              <div class="absolute bottom-2 right-2 w-6 h-6 bg-emerald-500 rounded-full border-4 border-[#0f1014] shadow-lg"></div>
          </div>

          <h2 class="text-3xl font-bold text-white gaming-font tracking-wide flex items-center gap-2">
              <?php echo htmlspecialchars($user['username']); ?>
              <i class="fas fa-check-circle text-blue-500 text-sm"></i>
          </h2>
          
          <div class="mt-2 flex items-center gap-3">
              <span class="px-3 py-1 rounded-full bg-white/5 border border-white/10 text-xs font-mono text-gray-400">
                  UID: <span class="text-white"><?php echo $formatted_uid; ?></span>
              </span>
              <span class="text-xs gaming-font vip-badge">
                  <i class="fas fa-crown text-yellow-500 mr-1"></i> VIP 1
              </span>
          </div>
      </div>

      <div class="px-5 grid grid-cols-2 gap-4 mb-8 w-full">
          <div class="stat-card-1 p-4 rounded-2xl relative overflow-hidden group">
              <div class="flex flex-col">
                  <span class="text-xs text-violet-300 uppercase tracking-wider font-semibold mb-1">Total Balance</span>
                  <span class="text-2xl font-bold text-white gaming-font">₹<?php echo number_format($user['balance'], 2); ?></span>
              </div>
              <i class="fas fa-wallet absolute -bottom-3 -right-3 text-5xl text-violet-500/10 group-hover:text-violet-500/20 transition-all"></i>
          </div>

          <div class="stat-card-2 p-4 rounded-2xl relative overflow-hidden group">
              <div class="flex flex-col">
                  <span class="text-xs text-cyan-300 uppercase tracking-wider font-semibold mb-1">Commission</span>
                  <span class="text-2xl font-bold text-white gaming-font">₹<?php echo number_format($total_commission, 2); ?></span>
              </div>
              <i class="fas fa-users absolute -bottom-3 -right-3 text-5xl text-cyan-500/10 group-hover:text-cyan-500/20 transition-all"></i>
          </div>
      </div>

      <div class="px-5 w-full">
          <h3 class="text-gray-500 text-xs font-bold uppercase mb-4 ml-2 tracking-widest">Main Menu</h3>
          
          <div class="flex flex-col gap-3">
              
              <a href="kyc.html" class="glass-tile p-4 flex items-center justify-between group">
                  <div class="flex items-center gap-4">
                      <div class="w-10 h-10 rounded-xl bg-violet-500/10 flex items-center justify-center text-violet-400 group-hover:scale-110 transition-transform">
                          <i class="fas fa-user-shield"></i>
                      </div>
                      <div class="flex flex-col">
                          <span class="text-sm font-semibold text-gray-200">Identity Verification</span>
                          <span class="text-[10px] text-gray-500">Status: Pending</span>
                      </div>
                  </div>
                  <i class="fas fa-chevron-right text-gray-600 text-xs group-hover:translate-x-1 transition-transform"></i>
              </a>

              <a href="wallet.php" class="glass-tile p-4 flex items-center justify-between group">
                  <div class="flex items-center gap-4">
                      <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-400 group-hover:scale-110 transition-transform">
                          <i class="fas fa-university"></i>
                      </div>
                      <div class="flex flex-col">
                          <span class="text-sm font-semibold text-gray-200">Bank & Wallet</span>
                          <span class="text-[10px] text-gray-500">Deposit / Withdraw</span>
                      </div>
                  </div>
                  <i class="fas fa-chevron-right text-gray-600 text-xs group-hover:translate-x-1 transition-transform"></i>
              </a>

              <a href="refer.php" class="glass-tile p-4 flex items-center justify-between group">
                  <div class="flex items-center gap-4">
                      <div class="w-10 h-10 rounded-xl bg-orange-500/10 flex items-center justify-center text-orange-400 group-hover:scale-110 transition-transform">
                          <i class="fas fa-gift"></i>
                      </div>
                      <div class="flex flex-col">
                          <span class="text-sm font-semibold text-gray-200">Refer & Earn</span>
                          <span class="text-[10px] text-gray-500">Invite friends</span>
                      </div>
                  </div>
                  <i class="fas fa-chevron-right text-gray-600 text-xs group-hover:translate-x-1 transition-transform"></i>
              </a>

              <a href="support.php" class="glass-tile p-4 flex items-center justify-between group">
                  <div class="flex items-center gap-4">
                      <div class="w-10 h-10 rounded-xl bg-pink-500/10 flex items-center justify-center text-pink-400 group-hover:scale-110 transition-transform">
                          <i class="fas fa-headset"></i>
                      </div>
                      <div class="flex flex-col">
                          <span class="text-sm font-semibold text-gray-200">Help Center</span>
                          <span class="text-[10px] text-gray-500">24/7 Support</span>
                      </div>
                  </div>
                  <i class="fas fa-chevron-right text-gray-600 text-xs group-hover:translate-x-1 transition-transform"></i>
              </a>

              <button onclick="logout()" class="mt-4 w-full p-4 rounded-xl border border-red-500/30 bg-red-500/5 text-red-500 font-semibold text-sm hover:bg-red-500/10 transition-all flex items-center justify-center gap-2 mb-4">
                  <i class="fas fa-sign-out-alt"></i> Log Out
              </button>

          </div>
      </div>
  </div>

    <!-- Bottom Navigation -->
    <div class="bottom-nav">
        <a href="index.php" class="nav-item">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>

        <a href="kyc.html" class="nav-item">
            <i class="fas fa-gamepad"></i>
            <span>Kyc</span>
        </a>

        <a href="wallet.php" class="nav-item">
            <i class="fas fa-wallet"></i>
            <span>Wallet</span>
        </a>

        <a href="refer.php" class="nav-item">
            <i class="fas fa-gift"></i>
            <span>Promos</span>
        </a>

        <a href="profile.php" class="nav-item active">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
    </div>

  <script>
    function logout() {
        if(confirm('Are you sure you want to logout?')) {
            window.location.href = 'logout.php';
        }
    }
  </script>
</body>
</html>