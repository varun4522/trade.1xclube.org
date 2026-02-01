<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Include configuration only from the application directory to avoid open_basedir warnings
$configPath = __DIR__ . '/config.php';
if (file_exists($configPath) && is_readable($configPath)) {
  require_once $configPath;
} else {
  echo "Configuration file not found. Please ensure config.php is inside the application directory.";
  exit;
}

// If config.php defines DB connection, use it. Otherwise fall back to defaults below.
if (!isset($db) && defined('DB_HOST')) {
  try {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
      throw new Exception("Connection failed: " . $db->connect_error);
    }
  } catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
  }
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_query = $db->prepare("SELECT * FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user = $user_result->fetch_assoc();

if (!$user) {
    session_destroy();
    header("Location: login.html");
    exit();
}

// User Variables
$username = $user['username'] ?? 'Player';
$userBalance = $user['balance'] ?? 0.00; // Asli balance fetch ho raha hai

// Baaki data variables
$username = $user['username'] ?? 'Player';
$userLevel = $user['level'] ?? 1;
$userXP = $user['xp'] ?? 0;
$vipStatus = ($user['is_vip'] ?? 0) == 1; 
$dailyStreak = $user['streak'] ?? 0;
$referralCode = $user['referral_code'] ?? 'N/A';

// Show welcome popup logic
$showWelcomePopup = !isset($_COOKIE['welcome_popup_shown']);

// Investment Plans data
$games = [
    ['link' => 'index.php', 'name' => 'Basic Plan', 'logo' => 'https://cdn-icons-png.flaticon.com/512/4436/4436481.png', 'category' => 'Investment', 'popularity' => 85, 'featured' => true, 'jackpot' => 50000],
    ['link' => 'index.php', 'name' => 'Silver Plan', 'logo' => 'https://cdn-icons-png.flaticon.com/512/3699/3699995.png', 'category' => 'Investment', 'popularity' => 92, 'featured' => true, 'jackpot' => 150000],
    ['link' => 'index.php', 'name' => 'Gold Plan', 'logo' => 'https://cdn-icons-png.flaticon.com/512/4436/4436481.png', 'category' => 'Investment', 'popularity' => 98, 'featured' => true, 'jackpot' => 500000],
];

// Investment Plans data (replacing games)
$games = [
    [
        'link' => 'index.php',
        'name' => 'Basic Plan',
        'logo' => 'https://cdn-icons-png.flaticon.com/512/4436/4436481.png',
        'category' => 'Investment',
        'popularity' => 85,
        'featured' => true,
        'jackpot' => 50000
    ],
    [
        'link' => 'index.php',
        'name' => 'Silver Plan',
        'logo' => 'https://cdn-icons-png.flaticon.com/512/3699/3699995.png',
        'category' => 'Investment',
        'popularity' => 92,
        'featured' => true,
        'jackpot' => 150000
    ],
    [
        'link' => 'index.php',
        'name' => 'Gold Plan',
        'logo' => 'https://cdn-icons-png.flaticon.com/512/4436/4436481.png',
        'category' => 'Investment',
        'popularity' => 98,
        'featured' => true,
        'jackpot' => 500000
    ],
    [
        'link' => 'index.php',
        'name' => 'Platinum Plan',
        'logo' => 'https://cdn-icons-png.flaticon.com/512/3699/3699995.png',
        'category' => 'Investment',
        'popularity' => 94,
        'featured' => true,
        'jackpot' => 1000000
    ],
    [
        'link' => 'index.php',
        'name' => 'Diamond Plan',
        'logo' => 'https://cdn-icons-png.flaticon.com/512/4436/4436481.png',
        'category' => 'Investment',
        'popularity' => 96,
        'featured' => true,
        'jackpot' => 2000000
    ],
];

// Promotions data
$promotions = [
    ['title' => 'Welcome Bonus', 'description' => 'Get 200% bonus on your first deposit up to ₹10,000', 'icon' => 'fas fa-gift', 'color' => 'bg-orange-500', 'code' => 'WELCOME200'],
    ['title' => 'Daily Rewards', 'description' => 'Login daily to claim free coins up to ₹5,000', 'icon' => 'fas fa-calendar-alt', 'color' => 'bg-blue-500', 'code' => 'DAILYFREE'],
    ['title' => 'VIP Program', 'description' => 'Exclusive benefits for VIP members including cashback', 'icon' => 'fas fa-crown', 'color' => 'bg-yellow-500', 'code' => 'VIPONLY'],
    ['title' => 'Referral Bonus', 'description' => 'Earn 15% of your friends deposits for life', 'icon' => 'fas fa-user-plus', 'color' => 'bg-green-500', 'code' => 'REFER15'],
    ['title' => 'Weekend Reload', 'description' => '50% bonus on every deposit this weekend', 'icon' => 'fas fa-sync-alt', 'color' => 'bg-pink-500', 'code' => 'WEEKEND50'],
    ['title' => 'High Roller', 'description' => 'Special bonuses for deposits over ₹50,000', 'icon' => 'fas fa-coins', 'color' => 'bg-red-500', 'code' => 'BIGBONUS'],
];

// Recent activities
$activities = [
    ['icon' => 'fa-coins', 'text' => 'You received 500 bonus coins', 'time' => 'Just now', 'color' => 'text-yellow-400'],
    ['icon' => 'fa-trophy', 'text' => 'Level up! You reached level '.$userLevel, 'time' => '5 mins ago', 'color' => 'text-blue-400'],
    ['icon' => 'fa-bell', 'text' => 'New game available: Trade Club Deluxe', 'time' => '1 hour ago', 'color' => 'text-orange-400'],
    ['icon' => 'fa-gift', 'text' => 'Daily reward available to claim', 'time' => '3 hours ago', 'color' => 'text-green-400'],
    ['icon' => 'fa-user-plus', 'text' => 'Friend joined using your referral code', 'time' => '5 hours ago', 'color' => 'text-orange-400'],
    ['icon' => 'fa-star', 'text' => 'VIP status unlocked! Special rewards available', 'time' => '1 day ago', 'color' => 'text-orange-400'],
];

// Tournament data
$tournaments = [
    ['name' => 'Mega Weekend', 'prize' => '₹5,00,000', 'game' => 'All Games', 'players' => '1,245', 'ends' => '2 days', 'entry' => 'Free'],
    ['name' => 'Dice Masters', 'prize' => '₹2,50,000', 'game' => 'Dice Duel', 'players' => '872', 'ends' => '1 day', 'entry' => '₹500'],
    ['name' => 'Slot King', 'prize' => '₹3,75,000', 'game' => 'Golden Slots', 'players' => '1,532', 'ends' => '3 days', 'entry' => '₹1,000'],
    ['name' => 'Aviator Challenge', 'prize' => '₹6,00,000', 'game' => 'Aviator', 'players' => '2,145', 'ends' => '5 days', 'entry' => 'Free'],
];

// VIP tiers
$vipTiers = [
    ['level' => 1, 'name' => 'Bronze', 'required' => 1000, 'benefits' => ['5% Cashback', 'Weekly Bonus']],
    ['level' => 2, 'name' => 'Silver', 'required' => 5000, 'benefits' => ['7% Cashback', 'Daily Bonus', 'Faster Withdrawals']],
    ['level' => 3, 'name' => 'Gold', 'required' => 15000, 'benefits' => ['10% Cashback', 'Daily Bonus', 'Personal Manager']],
    ['level' => 4, 'name' => 'Platinum', 'required' => 50000, 'benefits' => ['15% Cashback', 'Exclusive Bonuses', 'VIP Events']],
    ['level' => 5, 'name' => 'Diamond', 'required' => 100000, 'benefits' => ['20% Cashback', 'All Benefits', 'Luxury Gifts']],
];

// Payment methods
$paymentMethods = [
    ['name' => 'UPI', 'icon' => 'https://cdn-icons-png.flaticon.com/512/196/196566.png', 'min' => 100, 'max' => 100000, 'fee' => '0%'],
    ['name' => 'Paytm', 'icon' => 'https://cdn-icons-png.flaticon.com/512/196/196578.png', 'min' => 100, 'max' => 50000, 'fee' => '0%'],
    ['name' => 'Bank Transfer', 'icon' => 'https://cdn-icons-png.flaticon.com/512/196/196561.png', 'min' => 500, 'max' => 500000, 'fee' => '0%'],
    ['name' => 'Credit Card', 'icon' => 'https://cdn-icons-png.flaticon.com/512/196/196578.png', 'min' => 500, 'max' => 100000, 'fee' => '2%'],
    ['name' => 'Cryptocurrency', 'icon' => 'https://cdn-icons-png.flaticon.com/512/196/196578.png', 'min' => 1000, 'max' => 1000000, 'fee' => '0%'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="theme-color" content="#0f172a" />
  <title>Trade Club Game - Premium Gaming Platform</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css" />
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    
    * {
      font-family: 'Poppins', sans-serif;
      -webkit-tap-highlight-color: transparent;
    }
    
   
    
  
    }
    
    .shooting-star::before {
      content: '';
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      width: 300px;
      height: 1px;
      background: linear-gradient(90deg, rgba(255,255,255,1), transparent);
    }
    
    @keyframes shooting {
      0% {
        transform: rotate(215deg) translateX(0);
        opacity: 1;
      }
      70% {
        opacity: 1;
      }
      100% {
        transform: rotate(215deg) translateX(-1000px);
        opacity: 0;
      }
    }

    /* Fixed header and spacing for bottom nav */
    header {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 1100;
      backdrop-filter: blur(8px);
      height: 72px;
    }

    /* Reserve space so content isn't hidden behind header/bottom nav */
    .main-content {
      padding-top: 76px; /* header height */
      padding-bottom: 92px; /* bottom nav + extra spacing */
    }

    /* Ensure bottom nav height is consistent and above other elements */
    .bottom-nav {
      height: 64px;
      padding-top: 8px;
      padding-bottom: env(safe-area-inset-bottom, 8px);
      z-index: 1200;
    }
    
    .gradient-bg {
      background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
      position: relative;
      overflow-x: hidden;
      min-height: 100vh;
    }
    
    .glass-effect {
      background: rgba(15, 23, 42, 0.85);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
      z-index: 10;
      position: relative;
    }
    
    .btn-gradient {
      background: linear-gradient(135deg, #ff6b35 0%, #ff8c42 50%, #ffa552 100%);
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      position: relative;
      overflow: hidden;
      box-shadow: 0 4px 15px rgba(255, 107, 53, 0.3);
    }
    
    .btn-gradient:hover {
      transform: translateY(-3px);
      box-shadow: 0 15px 30px rgba(99, 102, 241, 0.5);
    }
    
    .btn-gradient:active {
      transform: translateY(0);
    }
    
    .btn-gradient::after {
      content: '';
      position: absolute;
      top: -50%;
      left: -60%;
      width: 200%;
      height: 200%;
      background: linear-gradient(
        to right,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 0.2) 50%,
        rgba(255, 255, 255, 0) 100%
      );
      transform: rotate(30deg);
      transition: all 0.7s ease;
    }
    
    .btn-gradient:hover::after {
      left: 100%;
    }
    
    .btn-premium {
      background: linear-gradient(135deg, #f59e0b 0%, #f97316 50%, #ef4444 100%);
      box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
    }
    
    .btn-premium:hover {
      box-shadow: 0 15px 30px rgba(245, 158, 11, 0.5);
    }
    
    .btn-premium::after {
      background: linear-gradient(
        to right,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 0.3) 50%,
        rgba(255, 255, 255, 0) 100%
      );
    }
    
    .floating {
      animation: floating 6s ease-in-out infinite;
    }
    
    @keyframes floating {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-15px); }
    }
    
    .pulse-glow {
      animation: pulse-glow 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
    
    @keyframes pulse-glow {
      0%, 100% { box-shadow: 0 0 0 0 rgba(255, 107, 53, 0.5); }
      50% { box-shadow: 0 0 25px 15px rgba(255, 107, 53, 0); }
    }
    
    .text-gradient {
      background: linear-gradient(135deg, #ff6b35 0%, #ff8c42 100%);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }
    
    .logo-container {
      position: relative;
      width: 90px;
      height: 90px;
      margin: 0 auto;
    }
    
    .logo-container::before {
      content: '';
      position: absolute;
      inset: -5px;
      background: linear-gradient(135deg, #ff6b35, #ff8c42, #ffa552);
      border-radius: 20px;
      z-index: -1;
      filter: blur(10px);
      opacity: 0.7;
      animation: rotate-hue 6s linear infinite;
    }
    
    @keyframes rotate-hue {
      0% { filter: blur(10px) hue-rotate(0deg); }
      100% { filter: blur(10px) hue-rotate(360deg); }
    }
    
    .logo-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 16px;
      box-shadow: 0 8px 32px rgba(139, 92, 246, 0.3);
    }
    
    /* Slider styles */
    .slider {
      position: relative;
      width: 100%;
      height: 300px;
      overflow: hidden;
      border-radius: 16px;
    }
    
    .slide {
      position: absolute;
      top: 0;
      left: 0;
      width: 127%;
      height: 100%;
      opacity: 0;
      transition: opacity 1s ease-in-out;
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      background-attachment: scroll;
    }
    
    .slide.active {
      opacity: 1;
    }
    
    .slide-content {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      padding: 20px;
      background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
      color: white;
    }
    
    /* Transaction ticker */
    .ticker-container {
      overflow: hidden;
      position: relative;
      height: 50px;
    }
    
    .ticker {
      display: flex;
      position: absolute;
      white-space: nowrap;
      animation: ticker 30s linear infinite;
    }
    
    @keyframes ticker {
      0% { transform: translateX(100%); }
      100% { transform: translateX(-100%); }
    }
    
    .transaction-item {
      display: inline-flex;
      align-items: center;
      margin-right: 40px;
      padding: 8px 16px;
      border-radius: 20px;
      background: rgba(15, 23, 42, 0.7);
      border: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    /* Game card styles */
    .game-card {
      transition: all 0.3s ease;
      transform-style: preserve-3d;
    }
    
    .game-card:hover {
      transform: translateY(-10px) scale(1.03);
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }
    
    /* Winner card styles */
    .winner-card {
      position: relative;
      overflow: hidden;
    }
    
    .winner-card::after {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: linear-gradient(
        to bottom right,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 0.1) 50%,
        rgba(255, 255, 255, 0) 100%
      );
      transform: rotate(30deg);
      animation: shine 3s infinite;
    }
    
    @keyframes shine {
      0% { left: -100%; }
      20%, 100% { left: 100%; }
    }
    
    /* Popularity meter */
    .popularity-meter {
      height: 4px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 2px;
      overflow: hidden;
      margin-top: 8px;
    }
    
    .popularity-fill {
      height: 100%;
      background: linear-gradient(90deg, #10b981, #ff6b35);
      border-radius: 2px;
    }
    
    /* Welcome popup */
    .welcome-popup {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.8);
      z-index: 1000;
      display: flex;
      justify-content: center;
      align-items: center;
      backdrop-filter: blur(10px);
    }
    
    .welcome-content {
      max-width: 90%;
      width: 400px;
      border-radius: 20px;
      overflow: hidden;
      animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    
    @keyframes popIn {
      0% { transform: scale(0.8); opacity: 0; }
      100% { transform: scale(1); opacity: 1; }
    }
    
    /* Mobile bottom navigation */
    .mobile-nav {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      z-index: 50;
      display: none;
    }
    
    /* XP Progress Bar */
    .xp-progress {
      height: 6px;
      border-radius: 3px;
      background: rgba(255,255,255,0.1);
      overflow: hidden;
    }
    
    .xp-progress-fill {
      height: 100%;
      background: linear-gradient(90deg, #ff6b35, #ff8c42);
      border-radius: 3px;
      transition: width 0.5s ease;
    }
    
    /* VIP Badge */
    .vip-badge {
      background: linear-gradient(135deg, #f59e0b, #f97316);
      color: #fff;
      text-shadow: 0 1px 2px rgba(0,0,0,0.2);
      box-shadow: 0 4px 6px rgba(245, 158, 11, 0.3);
    }
    
    /* Notification Badge */
    .notification-badge {
      position: absolute;
      top: -5px;
      right: -5px;
      min-width: 20px;
      height: 20px;
      padding: 0 4px;
      background: #ef4444;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 10px;
      font-weight: bold;
      color: white;
    }
    
    /* Floating action button */
    .fab {
      position: fixed;
      bottom: 80px;
      right: 20px;
      width: 60px;
      height: 60px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 40;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
      transition: all 0.3s ease;
    }
    
    .fab:hover {
      transform: scale(1.1);
    }
    
    /* Activity feed item */
    .activity-item {
      position: relative;
      padding-left: 28px;
    }
    
    .activity-item::before {
      content: '';
      position: absolute;
      left: 8px;
      top: 0;
      bottom: 0;
      width: 2px;
      background: rgba(255,255,255,0.1);
    }
    
    .activity-item:last-child::before {
      display: none;
    }
    
    .activity-icon {
      position: absolute;
      left: 0;
      top: 0;
      width: 18px;
      height: 18px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
      .slider {
        height: 200px;
      }
      
      .mobile-nav {
        display: flex;
      }
      
      .desktop-only {
        display: none;
      }
    }
    
    /* Android-like touch feedback */
    .touch-feedback:active {
      transform: scale(0.98);
      opacity: 0.9;
    }
    
    /* Custom scrollbar */
    ::-webkit-scrollbar {
      width: 6px;
      height: 6px;
    }
    
    ::-webkit-scrollbar-track {
      background: rgba(255,255,255,0.05);
    }
    
    ::-webkit-scrollbar-thumb {
      background: rgba(255,255,255,0.1);
      border-radius: 3px;
    }
    
    ::-webkit-scrollbar-thumb:hover {
      background: rgba(255,255,255,0.2);
    }
   
     
    /* 3D Card Effect */
    .card-3d {
      transform-style: preserve-3d;
      transition: all 0.5s ease;
    }
    .card-3d:hover {
      transform: perspective(1000px) rotateX(5deg) rotateY(5deg) translateY(-10px);
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }
    
    /* Glow effect for featured games */
    .featured-glow {
      position: relative;
      overflow: hidden;
    }
    
    .featured-glow::after {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: linear-gradient(
        to bottom right,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 0.1) 50%,
        rgba(255, 255, 255, 0) 100%
      );
      transform: rotate(30deg);
      animation: shine 3s infinite;
    }
    
    /* Confetti effect */
    .confetti {
      position: absolute;
      width: 10px;
      height: 10px;
      background-color: #f00;
      opacity: 0;
    }
    
    /* Premium support button */
    .fab-support {
      position: fixed;
      bottom: 90px;
      right: 20px;
      width: 56px;
      height: 56px;
      background: linear-gradient(135deg, #6a11cb, #2575fc);
      border-radius: 50%;
      box-shadow: 0 0 12px rgba(102, 153, 255, 0.7), 0 0 25px rgba(106, 17, 203, 0.8);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 999;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .fab-support:hover {
      transform: scale(1.1);
      box-shadow: 0 0 15px rgba(255, 255, 255, 0.8), 0 0 30px rgba(106, 17, 203, 1);
    }
    
    .fab-support i {
      color: white;
      font-size: 22px;
    }
    
    /* Bottom navigation */
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
      backdrop-filter: blur(10px);
    }
    
    .nav-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-decoration: none;
      color: rgba(255,255,255,0.7);
      font-size: 12px;
      transition: none !important;
      position: relative;
      padding: 5px 15px;
      border-radius: 15px;
      transform: none !important;
    }
    
    .nav-item i {
      font-size: 20px;
      margin-bottom: 4px;
      transition: none !important;
      transform: none !important;
    }
    
    .nav-item.active {
      color: white;
      transform: none !important;
      background: rgba(255, 107, 53, 0.2);
    }
    
    .nav-item.active i {
      color: #ff6b35;
      text-shadow: 0 0 10px rgba(255, 107, 53, 0.5);
      transform: none !important;
      transition: none !important;
    }
    
    .nav-item:not(.active):hover {
      color: white;
      background: rgba(255,255,255,0.05);
      transform: none !important;
    }
    
    .nav-item.active::after {
      content: '';
      position: absolute;
      bottom: -10px;
      left: 50%;
      transform: translateX(-50%);
      width: 5px;
      height: 5px;
      background: #ff6b35;
      border-radius: 50%;
      box-shadow: 0 0 8px #ff6b35;
    }
    
    /* Add space at bottom of page to prevent content hiding */
    body {
      padding-bottom: 70px;
    }
    
    /* Jackpot counter */
    .jackpot-counter {
      font-family: 'Poppins', sans-serif;
      font-weight: 700;
      background: linear-gradient(to right, #f59e0b, #f97316, #ef4444);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      text-shadow: 0 0 10px rgba(245, 158, 11, 0.3);
    }
    
    /* Tournament card */
    .tournament-card {
      background: linear-gradient(135deg, rgba(30, 41, 59, 0.8), rgba(15, 23, 42, 0.9));
      border: 1px solid rgba(255, 255, 255, 0.1);
      transition: all 0.3s ease;
    }
    
    .tournament-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
      border-color: rgba(139, 92, 246, 0.3);
    }
    
    /* VIP tier progress */
    .vip-progress {
      height: 6px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 3px;
      overflow: hidden;
    }
    
    .vip-progress-fill {
      height: 100%;
      background: linear-gradient(90deg, #f59e0b, #f97316);
      border-radius: 3px;
    }
    
    /* Streak counter */
    .streak-counter {
      position: relative;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    
    .streak-counter::before {
      content: '';
      position: absolute;
      width: 100%;
      height: 100%;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(245,158,11,0.4) 0%, rgba(245,158,11,0) 70%);
      animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
      0% { transform: scale(0.8); opacity: 0.8; }
      70% { transform: scale(1.1); opacity: 0.3; }
      100% { transform: scale(0.8); opacity: 0.8; }
    }
    
    /* Swiper custom styles */
    .swiper-slide {
      display: flex;
      justify-content: center;
      align-items: center;
    }
    
    .swiper-pagination-bullet {
      background: rgba(255,255,255,0.5);
      opacity: 1;
    }
    
    .swiper-pagination-bullet-active {
      background: #ff6b35;
    }
    
    /* Payment method selector */
    .payment-method {
      transition: all 0.3s ease;
      border: 1px solid transparent;
    }
    
    .payment-method:hover {
      transform: translateY(-3px);
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .payment-method.selected {
      border-color: #ff6b35;
      box-shadow: 0 0 0 2px rgba(255, 107, 53, 0.3);
    }
    
    /* Referral code input */
    .referral-input {
      position: relative;
    }
    
    .referral-input input {
      padding-right: 100px;
    }
    
    .referral-code {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      background: rgba(255,255,255,0.1);
      padding: 2px 8px;
      border-radius: 4px;
      font-size: 12px;
      color: #8b5cf6;
    }
    
    /* Animated background for premium sections */
    .premium-bg {
      position: relative;
      overflow: hidden;
    }
    
    .premium-bg::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: linear-gradient(
        to bottom right,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 0.05) 50%,
        rgba(255, 255, 255, 0) 100%
      );
      transform: rotate(30deg);
      animation: shine 6s infinite;
    }
    
    /* Floating coins animation */
    .floating-coins {
      position: absolute;
      width: 100%;
      height: 100%;
      top: 0;
      left: 0;
      pointer-events: none;
      z-index: -1;
    }
    
    .coin {
      position: absolute;
      width: 20px;
      height: 20px;
      background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23f59e0b"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6" fill="%23fcd34d"/></svg>');
      background-size: contain;
      opacity: 0.6;
      animation: float-up 10s linear infinite;
    }
    
    @keyframes float-up {
      0% {
        transform: translateY(100vh) rotate(0deg);
        opacity: 0;
      }
      10% {
        opacity: 0.6;
      }
      90% {
        opacity: 0.6;
      }
      100% {
        transform: translateY(-100px) rotate(360deg);
        opacity: 0;
      }
    }
    
    /* Neon text effect */
    .neon-text {
      text-shadow: 0 0 5px #fff, 0 0 10px #fff, 0 0 15px #8b5cf6, 0 0 20px #8b5cf6;
      animation: flicker 1.5s infinite alternate;
    }
    
    @keyframes flicker {
      0%, 19%, 21%, 23%, 25%, 54%, 56%, 100% {
        text-shadow: 0 0 5px #fff, 0 0 10px #fff, 0 0 15px #ff6b35, 0 0 20px #ff6b35;
      }
      20%, 24%, 55% {        
        text-shadow: none;
      }
    }
    
    /* Gradient border */
    .gradient-border {
      position: relative;
      border-radius: 16px;
    }
    
    .gradient-border::before {
      content: '';
      position: absolute;
      top: -2px;
      left: -2px;
      right: -2px;
      bottom: -2px;
      background: linear-gradient(45deg, #ff6b35, #ff8c42, #ffa552);
      border-radius: 18px;
      z-index: -1;
      opacity: 0.7;
    }
    
    /* Animated button */
    .animated-button {
      position: relative;
      overflow: hidden;
    }
    
    .animated-button::after {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: linear-gradient(
        to bottom right,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 0.2) 50%,
        rgba(255, 255, 255, 0) 100%
      );
      transform: rotate(30deg);
      animation: shine 3s infinite;
    }
    
    /* Custom checkbox */
    .custom-checkbox {
      position: relative;
      width: 20px;
      height: 20px;
      appearance: none;
      -webkit-appearance: none;
      background: rgba(255,255,255,0.1);
      border-radius: 4px;
      cursor: pointer;
    }
    
    .custom-checkbox:checked {
      background: #8b5cf6;
    }
    
    .custom-checkbox:checked::after {
      content: '✓';
      position: absolute;
      color: white;
      font-size: 14px;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
    }
    
    /* Tooltip */
    .tooltip {
      position: relative;
    }
    
    .tooltip:hover .tooltip-text {
      visibility: visible;
      opacity: 1;
      transform: translateY(0);
    }
    
    .tooltip-text {
      visibility: hidden;
      opacity: 0;
      position: absolute;
      z-index: 100;
      bottom: 125%;
      left: 50%;
      transform: translateX(-50%) translateY(10px);
      background: rgba(30,41,59,0.95);
      border: 1px solid rgba(255,255,255,0.1);
      color: white;
      padding: 5px 10px;
      border-radius: 6px;
      font-size: 12px;
      white-space: nowrap;
      transition: all 0.2s ease;
      pointer-events: none;
    }
    
    .tooltip-text::after {
      content: '';
      position: absolute;
      top: 100%;
      left: 50%;
      transform: translateX(-50%);
      border-width: 5px;
      border-style: solid;
      border-color: rgba(30,41,59,0.95) transparent transparent transparent;
    }
  </style>
</head>
<body class="gradient-bg min-h-screen overflow-x-hidden">
  <!-- Cosmic Background -->
  <div class="cosmic-bg">
    <div class="stars"></div>
    <div class="floating-coins" id="floatingCoins"></div>
  </div>
  
  <!-- Welcome Popup -->
  <?php if($showWelcomePopup): ?>
  <div class="welcome-popup animate__animated animate__fadeIn">
    <div class="welcome-content glass-effect">
      <div class="relative">
        <img src="https://img.freepik.com/premium-photo/neon-welcome-lettering-textured-background_317169-2142.jpg" alt="Welcome to Trade Club" class="w-full h-40 object-cover">
        <div class="absolute top-0 left-0 right-0 bottom-0 bg-gradient-to-t from-black to-transparent"></div>
        <div class="absolute bottom-4 left-4">
          <h2 class="text-2xl font-bold text-white">Welcome to Trade Club!</h2>
          <p class="text-white/80">Claim your welcome bonus now</p>
        </div>
      </div>
      <div class="p-6">
        <p class="text-gray-300 mb-4">Join thousands of players winning big every day. Start with a 200% bonus on your first deposit!</p>
        <div class="flex space-x-3">
          <button onclick="closeWelcomePopup()" class="flex-1 bg-white/10 hover:bg-white/20 text-white px-4 py-2 rounded-lg transition-colors touch-feedback">
            Maybe Later
          </button>
          <button onclick="closeWelcomePopup(); claimWelcomeBonus();" class="flex-1 btn-gradient px-4 py-2 rounded-lg text-white font-medium touch-feedback">
            Claim Bonus
          </button>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
  
  <!-- Header/Navbar -->
  <header class="glass-effect site-header">
    <div class="container mx-auto px-4 py-3 flex justify-between items-center">
      <div class="flex items-center space-x-2">
        <div class="logo-container w-10 h-10">
          <img src="images/REG.jpg" alt="Trade Club Logo" class="logo-img">
        </div>
        <h1 class="text-lg font-bold text-white"><span class="text-gradient">Chicken</span> Road</h1>
      </div>
      
      <div class="flex items-center space-x-4">
        <div class="relative">
          <button onclick="openNotifPopup()" class="w-10 h-10 flex items-center justify-center rounded-full bg-white/5 hover:bg-white/10 transition-colors touch-feedback">
            <i class="fas fa-bell text-white"></i>
            <?php if(isset($unreadNotifications) && $unreadNotifications > 0): ?>
              <span class="notification-badge"><?= (int)$unreadNotifications ?></span>
            <?php endif; ?>
          </button>
          <!-- Notifications dropdown -->
          <div id="notificationsDropdown" class="hidden absolute right-0 mt-2 w-72 bg-gray-800 rounded-lg shadow-xl z-50 border border-gray-700">
            <div class="p-3 border-b border-gray-700 flex justify-between items-center">
              <h3 class="font-semibold text-white">Notifications</h3>
              <button class="text-xs text-indigo-400 hover:text-indigo-300">Mark all as read</button>
            </div>
            <div class="max-h-60 overflow-y-auto">
              <?php foreach(array_slice($activities, 0, 4) as $activity): ?>
                <div class="p-3 hover:bg-gray-700/50 transition-colors border-b border-gray-700/50 last:border-0">
                  <div class="flex items-start">
                    <div class="flex-shrink-0 mt-1">
                      <i class="fas <?= $activity['icon'] ?> <?= $activity['color'] ?>"></i>
                    </div>
                    <div class="ml-3">
                      <p class="text-sm text-white"><?= $activity['text'] ?></p>
                      <p class="text-xs text-gray-400 mt-1"><?= $activity['time'] ?></p>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="p-3 text-center border-t border-gray-700">
              
            </div>
          </div>
        </div>
        
        <div class="hidden md:flex items-center space-x-2 bg-white/5 px-3 py-1 rounded-full">
          <i class="fas fa-coins text-yellow-400"></i>
          <span class="text-white font-medium">₹<?= number_format($userBalance) ?></span>
          <button onclick="showDepositModal()" class="ml-2 text-xs bg-orange-600 hover:bg-orange-700 px-2 py-0.5 rounded-full transition-colors touch-feedback">
            +
          </button>
        </div>
        
        <div class="relative">
          <button onclick="toggleProfileMenu()" class="flex items-center space-x-2 focus:outline-none">
            <div class="w-8 h-8 rounded-full bg-orange-500 flex items-center justify-center text-white font-semibold relative">
              <?= strtoupper(substr($username, 0, 1)) ?>
              <?php if($vipStatus): ?>
                <div class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full vip-badge flex items-center justify-center">
                  <i class="fas fa-crown text-xs"></i>
                </div>
              <?php endif; ?>
            </div>
            <span class="text-white font-medium hidden md:inline"><?= $username ?></span>
          </button>
          
          <!-- Profile dropdown menu -->
          <div id="profileMenu" class="hidden absolute right-0 mt-2 w-48 bg-gray-800 rounded-md shadow-lg z-50 border border-gray-700">
            <div class="py-1">
              <div class="px-4 py-2 border-b border-gray-700">
                <p class="text-sm text-white">Signed in as</p>
                <p class="text-sm font-medium text-white truncate"><?= $username ?></p>
              </div>
              <a href="main.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">Your Profile</a>
             
              <a href="transactions.html" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">Transactions</a>
           
              <div class="border-t border-gray-700"></div>
              <a href="logout.php" class="block px-4 py-2 text-sm text-red-400 hover:bg-gray-700 hover:text-red-300">Sign out</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>

  <main class="relative z-10 pb-20 main-content">
    <!-- User Stats Section -->
    <section class="container mx-auto px-4 pt-6">
      <div class="glass-effect rounded-xl p-4 card-3d">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h2 class="text-xl font-bold text-white">Welcome back, <?= $username ?></h2>
            <p class="text-sm text-gray-400">Ready to play and win?</p>
          </div>
          <?php if($vipStatus): ?>
            <div class="vip-badge px-3 py-1 rounded-full text-xs font-semibold flex items-center">
              <i class="fas fa-crown mr-1"></i> VIP MEMBER
            </div>
          <?php else: ?>
            <button onclick="showVipModal()" class="bg-white/5 hover:bg-white/10 px-3 py-1 rounded-full text-xs font-semibold flex items-center transition-colors">
              <i class="fas fa-gem mr-1 text-purple-400"></i> UPGRADE
            </button>
          <?php endif; ?>
        </div>
        
        <div class="flex items-center justify-between mb-3">
          <div class="flex items-center space-x-2">
            <i class="fas fa-coins text-yellow-400"></i>
            <span class="text-white font-bold">₹<?= number_format($userBalance) ?></span>
          </div>
          <button onclick="showDepositModal()" class="btn-gradient px-4 py-1 rounded-full text-sm font-medium touch-feedback">
            + Deposit
          </button>
        </div>
        
        <div class="mb-2">
          <?php
          $xpInLevel = $userXP % 100;
          $xpPercent = ($xpInLevel / 100) * 100;
          ?>
          <div class="flex justify-between text-xs text-gray-400 mb-1">
            <span>Level <?= $userLevel ?></span>
            <span><?= $xpInLevel ?> / 100 XP</span>
          </div>
          <div class="xp-progress">
            <div class="xp-progress-fill" style="width: <?= $xpPercent ?>%"></div>
          </div>
        </div>
        
        <!-- Daily streak -->
        <div class="mt-4 flex items-center justify-between">
          <div class="flex items-center space-x-2">
            <i class="fas fa-fire text-orange-400"></i>
            <span class="text-sm text-white">Daily Streak</span>
          </div>
          <div class="flex items-center space-x-1">
            <?php for($i = 0; $i < 7; $i++): ?>
              <div class="w-6 h-6 rounded-full flex items-center justify-center <?= $i < $dailyStreak ? 'bg-orange-500 text-white' : 'bg-white/5 text-white/30' ?> text-xs">
                <?= $i+1 ?>
              </div>
            <?php endfor; ?>
          </div>
        </div>
      </div>
    </section>

    <!-- Hero Slider -->
    <section class="container mx-auto px-4 py-6">
      <div class="slider">
        <div class="slide active" style="background-image: url('https://i.ibb.co/rRdcFFdL/ban5.png'); background-color: #1a1a2e;">
        </div>
        <div class="slide" style="background-image: url('https://i.ibb.co/F474P7zf/ban4.png'); background-color: #1a1a2e;">
        </div>
      </div>
    </section>
    <script>
      (function(){
        const slides = document.querySelectorAll('.slider .slide');
        if(!slides || slides.length <= 1) return;
        let current = 0;
        const show = (index) => {
          slides.forEach((s,i)=> s.classList.toggle('active', i === index));
        };
        setInterval(() => {
          current = (current + 1) % slides.length;
          show(current);
        }, 4000); // 4000ms = 4s
      })();
    </script>

    <!-- Quick Actions -->
    <section class="container mx-auto px-4 py-6">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <a href="deposit.html" class="glass-effect p-4 rounded-xl flex flex-col items-center justify-center text-center hover:bg-white/5 transition-colors touch-feedback card-3d">
          <div class="w-12 h-12 bg-orange-500/20 rounded-full flex items-center justify-center mb-2">
            <i class="fas fa-wallet text-xl text-orange-400"></i>
          </div>
          <h3 class="text-sm md:text-base font-semibold text-white">Deposit</h3>
        </a>
        
        <a href="withdraw.html" class="glass-effect p-4 rounded-xl flex flex-col items-center justify-center text-center hover:bg-white/5 transition-colors touch-feedback card-3d">
          <div class="w-12 h-12 bg-green-500/20 rounded-full flex items-center justify-center mb-2">
            <i class="fas fa-money-bill-wave text-xl text-green-400"></i>
          </div>
          <h3 class="text-sm md:text-base font-semibold text-white">Withdraw</h3>
        </a>
        
        <a href="kyc.html" class="glass-effect p-4 rounded-xl flex flex-col items-center justify-center text-center hover:bg-white/5 transition-colors touch-feedback card-3d">
          <div class="w-12 h-12 bg-orange-500/20 rounded-full flex items-center justify-center mb-2">
            <i class="fas fa-gamepad text-xl text-orange-400"></i>
          </div>
          <h3 class="text-sm md:text-base font-semibold text-white">Kyc</h3>
        </a>
        
        <a href="refer.php" class="glass-effect p-4 rounded-xl flex flex-col items-center justify-center text-center hover:bg-white/5 transition-colors touch-feedback card-3d">
          <div class="w-12 h-12 bg-yellow-500/20 rounded-full flex items-center justify-center mb-2">
            <i class="fas fa-gift text-xl text-yellow-400"></i>
          </div>
          <h3 class="text-sm md:text-base font-semibold text-white">Promotions</h3>
        </a>
      </div>
    </section>

    <!-- Live Jackpot Counter -->
    <section class="container mx-auto px-4 py-6">
      <div class="glass-effect rounded-xl p-4 card-3d">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
          <div class="mb-4 md:mb-0">
            <h2 class="text-xl font-bold text-white">Live Jackpot</h2>
            <p class="text-sm text-gray-400">Current total prize pool</p>
          </div>
          <div class="text-right">
            <div class="text-3xl font-bold jackpot-counter" id="jackpotCounter">₹12,45,789</div>
            <p class="text-xs text-gray-400">Updated every minute</p>
          </div>
        </div>
        <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-3">
          <?php 
          $featuredGames = array_filter($games, function($game) { return $game['featured'] ?? false; });
          $randKeys = array_rand($featuredGames, min(4, count($featuredGames)));
          $randomFeaturedGames = is_array($randKeys) ? $randKeys : [$randKeys];
          foreach($randomFeaturedGames as $index): 
            $game = $featuredGames[$index];
          ?>
            <div class="bg-white/5 rounded-lg p-3">
              <div class="flex items-center space-x-2">
                <img src="<?= $game['logo'] ?>" alt="<?= $game['name'] ?>" class="w-8 h-8 rounded-md">
                <div>
                  <p class="text-xs text-gray-400"><?= $game['name'] ?></p>
                  <p class="text-sm font-semibold text-white">₹<?= number_format($game['jackpot']) ?></p>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- Popular Games -->
    <section class="container mx-auto px-4 py-8">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-white">Referral</h2>
        <a href="refer.php" class="text-indigo-400 hover:text-indigo-300 flex items-center touch-feedback">
        </a>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4">
        <?php 
        if(!isset($games) || !is_array($games)) {
          $games = [];
        }
        $popularGames = array_slice($games, 0,8);
        foreach($popularGames as $game): 
        ?>
          <a href="<?= $game['link'] ?>" class="game-card glass-effect rounded-xl overflow-hidden group transition duration-300 transform hover:scale-105 shadow-lg bg-white/5 featured-glow">
            <div class="aspect-square bg-white/10 flex items-center justify-center p-4 relative">
              <img src="<?= $game['logo'] ?>" alt="<?= $game['name'] ?>" class="w-full h-full object-contain transition-transform duration-300 group-hover:scale-110">
              <?php if($game['popularity'] > 90): ?>
                <div class="absolute top-2 right-2 bg-red-600 text-white text-xs px-2 py-0.5 rounded-full font-semibold shadow">
                  HOT
                </div>
              <?php endif; ?>
            </div>
            <div class="p-3">
              <h3 class="text-white font-medium truncate"><?= $game['name'] ?></h3>
              <span class="text-xs text-gray-400"><?= $game['category'] ?></span>
              <div class="popularity-meter mt-1">
                <div class="popularity-fill" style="width: <?= $game['popularity'] ?>%"></div>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Tournaments -->
    <section class="container mx-auto px-4 py-8">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-white">Live Tournaments</h2>
        <a href="tournaments.php" class="text-indigo-400 hover:text-indigo-300 flex items-center touch-feedback">
         
        </a>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php foreach(array_slice($tournaments, 0, 2) as $tournament): ?>
          <div class="tournament-card rounded-xl p-4">
            <div class="flex justify-between items-start">
              <div>
                <h3 class="text-lg font-bold text-white"><?= $tournament['name'] ?></h3>
                <p class="text-sm text-gray-400"><?= $tournament['game'] ?></p>
              </div>
              <div class="text-right">
                <div class="text-xl font-bold text-yellow-400"><?= $tournament['prize'] ?></div>
                <p class="text-xs text-gray-400">Prize Pool</p>
              </div>
            </div>
            <div class="mt-4 grid grid-cols-3 gap-2 text-center">
              <div>
                <p class="text-sm font-semibold text-white"><?= $tournament['players'] ?></p>
                <p class="text-xs text-gray-400">Players</p>
              </div>
              <div>
                <p class="text-sm font-semibold text-white"><?= $tournament['ends'] ?></p>
                <p class="text-xs text-gray-400">Ends In</p>
              </div>
              <div>
                <p class="text-sm font-semibold text-white"><?= $tournament['entry'] ?></p>
                <p class="text-xs text-gray-400">Entry</p>
              </div>
            </div>
            <button class="btn-gradient w-full mt-4 py-2 rounded-lg text-white font-medium touch-feedback">
              Join Now
            </button>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Recent Winners -->
    <section class="container mx-auto px-4 py-8">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-white">Recent Winners</h2>
        <a href="winners.php" class="text-indigo-400 hover:text-indigo-300 flex items-center touch-feedback">
       
        </a>
      </div>

      <div class="swiper winners-swiper">
        <div class="swiper-wrapper">
          <?php
          if(!isset($winners) || !is_array($winners)) {
            $winners = [
              ['name' => 'Rajesh K', 'amount' => 50000, 'game' => 'Wingo', 'avatar' => './avatar/1.png'],
              ['name' => 'Priya Sharma', 'amount' => 75000, 'game' => 'Crash', 'avatar' => './avatar/2.png'],
              ['name' => 'Amit Patel', 'amount' => 45000, 'game' => 'Mines', 'avatar' => './avatar/3.png'],
              ['name' => 'Neha Singh', 'amount' => 62000, 'game' => 'Aviator', 'avatar' => './avatar/4.png'],
              ['name' => 'Vikram Das', 'amount' => 88000, 'game' => 'Trade Club', 'avatar' => './avatar/5.png'],
            ];
          }
          foreach($winners as $winner): ?>
            <div class="swiper-slide">
              <div class="glass-effect rounded-xl p-4 text-center winner-card">
                <div class="w-16 h-16 rounded-full mx-auto mb-3 overflow-hidden border-2 border-yellow-400">
                  <img src="<?= $winner['avatar'] ?>" alt="<?= $winner['name'] ?>" class="w-full h-full object-cover">
                </div>
                <h3 class="font-semibold text-white"><?= $winner['name'] ?></h3>
                <p class="text-sm text-gray-400">Won ₹<?= number_format($winner['amount']) ?></p>
                <p class="text-xs text-indigo-400 mt-1">Playing <?= $winner['game'] ?></p>
                <div class="mt-3">
                  <span class="inline-block bg-yellow-500/10 text-yellow-400 text-xs px-2 py-1 rounded-full">
                    <i class="fas fa-trophy mr-1"></i> Winner
                  </span>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="swiper-pagination"></div>
      </div>
    </section>

   

    <!-- Recent Transactions -->
    <section class="container mx-auto px-4 py-8">
      <div class="glass-effect rounded-xl p-4">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-bold text-white">Live Transactions</h2>
          <i class="fas fa-exchange-alt text-gray-400"></i>
        </div>
        <div class="ticker-container">
          <div class="ticker">
            <?php
            if(!isset($transactions) || !is_array($transactions)) {
              $transactions = [
                ['user' => 'Rajesh K', 'type' => 'deposit', 'amount' => 5000, 'time' => '2 mins ago'],
                ['user' => 'Priya Sharma', 'type' => 'win', 'amount' => 12500, 'time' => '5 mins ago'],
                ['user' => 'Amit Patel', 'type' => 'withdraw', 'amount' => 8000, 'time' => '8 mins ago'],
                ['user' => 'Neha Singh', 'type' => 'deposit', 'amount' => 3500, 'time' => '12 mins ago'],
                ['user' => 'Vikram Das', 'type' => 'win', 'amount' => 7250, 'time' => '15 mins ago'],
                ['user' => 'Anjali Verma', 'type' => 'withdraw', 'amount' => 4000, 'time' => '18 mins ago'],
                ['user' => 'Harsh Kumar', 'type' => 'deposit', 'amount' => 10000, 'time' => '22 mins ago'],
                ['user' => 'Sneha Reddy', 'type' => 'win', 'amount' => 15000, 'time' => '25 mins ago'],
              ];
            }
            foreach($transactions as $txn): ?>
              <div class="transaction-item">
                <div class="flex items-center">
                  <?php if($txn['type'] === 'deposit'): ?>
                    <i class="fas fa-arrow-down text-green-400 mr-2"></i>
                  <?php elseif($txn['type'] === 'withdraw'): ?>
                    <i class="fas fa-arrow-up text-red-400 mr-2"></i>
                  <?php else: ?>
                    <i class="fas fa-trophy text-yellow-400 mr-2"></i>
                  <?php endif; ?>
                  <span class="text-white font-medium"><?= $txn['user'] ?></span>
                  <span class="mx-2 text-gray-400">•</span>
                  <span class="text-white">₹<?= number_format($txn['amount']) ?></span>
                  <span class="mx-2 text-gray-400">•</span>
                  <span class="text-gray-400 text-sm"><?= $txn['time'] ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </section>



  <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
  <script src="assets/js/shared_bottom_nav.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js"></script>
  <script>
    // Initialize Swiper for winners carousel
    const winnersSwiper = new Swiper('.winners-swiper', {
      slidesPerView: 'auto',
      spaceBetween: 15,
      pagination: {
        el: '.swiper-pagination',
        clickable: true,
      },
      breakpoints: {
        640: {
          slidesPerView: 2,
          spaceBetween: 20,
        },
        768: {
          slidesPerView: 3,
          spaceBetween: 25,
        },
        1024: {
          slidesPerView: 4,
          spaceBetween: 30,
        },
      }
    });

    // Close welcome popup and set cookie
    function closeWelcomePopup() {
      document.querySelector('.welcome-popup').classList.add('animate__fadeOut');
      setTimeout(() => {
        document.querySelector('.welcome-popup').style.display = 'none';
      }, 500);
      
      // Set cookie to not show again for 7 days
      document.cookie = "welcome_popup_shown=true; max-age=" + (60 * 60 * 24 * 7) + "; path=/";
    }
    
    // Claim welcome bonus (no direct balance changes on client)
    function claimWelcomeBonus() {
      // Show confetti effect
      createConfetti();
      // Real apps must call server to apply balance changes.
      showToast('🎉 Welcome bonus requested — check your notifications shortly.');
    }
    // Create confetti effect
    function createConfetti() {
      const colors = ['#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff', '#00ffff'];
      
    
      for (let i = 0; i < 100; i++) {
        const confetti = document.createElement('div');
        confetti.className = 'confetti';
        confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
        confetti.style.left = Math.random() * 100 + 'vw';
        confetti.style.top = -10 + 'px';
        confetti.style.transform = 'rotate(' + Math.random() * 360 + 'deg)';
        
        const animationDuration = Math.random() * 3 + 2;
        confetti.style.animation = `fall ${animationDuration}s linear forwards`;
        
        document.body.appendChild(confetti);
        
        // Remove confetti after animation
        setTimeout(() => {
          confetti.remove();
        }, animationDuration * 1000);
      }
      
      // Add CSS for falling animation
      const style = document.createElement('style');
      style.innerHTML = `
        @keyframes fall {
          to {
            transform: translateY(100vh) rotate(360deg);
            opacity: 0;
          }
        }
      `;
      document.head.appendChild(style);
    }
    
    // Show toast notification
    function showToast(message) {
      const toast = document.createElement('div');
      toast.className = 'toast fixed bottom-20 left-1/2 transform -translate-x-1/2 bg-orange-600 text-white px-4 py-2 rounded-lg shadow-lg z-50 animate__animated animate__fadeInUp';
      toast.textContent = message;
      document.body.appendChild(toast);
      
      setTimeout(() => {
        toast.classList.remove('animate__fadeInUp');
        toast.classList.add('animate__fadeOutDown');
        setTimeout(() => toast.remove(), 500);
      }, 3000);
    }

    // Update XP bar and level in UI
    function updateXP(xp, level) {
      const xpInLevel = xp % 100;
      const percent = (xpInLevel / 100) * 100;
      const label = document.querySelector('.xp-progress')?.previousElementSibling?.querySelector('span:last-child');
      if (label) label.innerText = `${xpInLevel} / 100 XP`;
      const bar = document.querySelector('.xp-progress-fill');
      if (bar) bar.style.width = percent + '%';
      const lvl = document.querySelector('.xp-progress')?.previousElementSibling?.querySelector('span:first-child');
      if (lvl) lvl.innerText = `Level ${level}`;
    }
    
    // Toggle notifications dropdown
    function toggleNotifications() {
      const dropdown = document.getElementById('notificationsDropdown');
      dropdown.classList.toggle('hidden');
      
      // Hide other dropdowns
      document.getElementById('profileMenu').classList.add('hidden');
      
      // Mark notifications as read
      const badge = document.querySelector('.notification-badge');
      if(badge) {
        badge.style.display = 'none';
      }
    }
    
    // Toggle profile menu
    function toggleProfileMenu() {
      const menu = document.getElementById('profileMenu');
      menu.classList.toggle('hidden');
      
      // Hide other dropdowns
      document.getElementById('notificationsDropdown').classList.add('hidden');
    }
    
    // Show deposit modal
    function showDepositModal() {
      const modal = document.getElementById('depositModal');
      modal.classList.remove('hidden');
    }
    
    // Hide deposit modal
    function hideDepositModal() {
      const modal = document.getElementById('depositModal');
      modal.classList.add('hidden');
    }
    
    // Show VIP modal
    function showVipModal() {
      const modal = document.getElementById('vipModal');
      modal.classList.remove('hidden');
    }
    
    // Hide VIP modal
    function hideVipModal() {
      const modal = document.getElementById('vipModal');
      modal.classList.add('hidden');
    }
    
    // Set deposit amount
    function setDepositAmount(amount) {
      document.getElementById('depositAmount').value = amount;
      checkDepositButton();
    }
    
    // Select payment method
    function selectPaymentMethod(element, method) {
      // Remove selected class from all buttons
      document.querySelectorAll('.payment-method').forEach(btn => {
        btn.classList.remove('selected');
      });
      
      // Add selected class to clicked button
      element.classList.add('selected');
      
      checkDepositButton();
    }
    
    // Check deposit button state
    function checkDepositButton() {
      const amount = document.getElementById('depositAmount').value;
      const methodSelected = document.querySelector('.payment-method.selected');
      const termsChecked = document.getElementById('termsCheckbox').checked;
      
      document.getElementById('depositButton').disabled = !(amount > 0 && methodSelected && termsChecked);
    }
    
    // Initialize terms checkbox event (null-safe + idempotent)
    const _termsEl = document.getElementById('termsCheckbox');
    if (_termsEl && !_termsEl.dataset._depositListener) {
      _termsEl.addEventListener('change', checkDepositButton);
      _termsEl.dataset._depositListener = '1';
    }
    const _depositAmtEl = document.getElementById('depositAmount');
    if (_depositAmtEl && !_depositAmtEl.dataset._depositListener) {
      _depositAmtEl.addEventListener('input', checkDepositButton);
      _depositAmtEl.dataset._depositListener = '1';
    }
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
      if (!event.target.closest('#notificationsDropdown') && !event.target.closest('.fa-bell')) {
        document.getElementById('notificationsDropdown').classList.add('hidden');
      }
      
      if (!event.target.closest('#profileMenu') && !event.target.closest('.flex.items-center.space-x-2')) {
        document.getElementById('profileMenu').classList.add('hidden');
      }
      
      if (event.target.closest('#depositModal') && !event.target.closest('.glass-effect')) {
        hideDepositModal();
      }
      
      if (event.target.closest('#vipModal') && !event.target.closest('.glass-effect')) {
        hideVipModal();
      }
    });
    
    // Initialize slider functionality
    const slides = document.querySelectorAll('.slide');
    let currentSlide = 0;
    
    function showSlide(n) {
      slides.forEach(slide => slide.classList.remove('active'));
      slides[n].classList.add('active');
    }
    
    function nextSlide() {
      currentSlide = (currentSlide + 1) % slides.length;
      showSlide(currentSlide);
    }
    
    // Change slide every 5 seconds
    setInterval(nextSlide, 5000);
    
    // Randomize transaction ticker speed slightly
    const tickers = document.querySelectorAll('.ticker');
    tickers.forEach(ticker => {
      const duration = 25 + Math.random() * 10;
      ticker.style.animationDuration = `${duration}s`;
    });
    
    // Simulate real-time transactions by updating the ticker periodically
    setInterval(() => {
      const transactions = document.querySelector('.ticker');
      const firstChild = transactions.firstElementChild;
      transactions.appendChild(firstChild.cloneNode(true));
      firstChild.remove();
    }, 3000);
    
    // Removed demo balance/XP simulators to avoid client-side balance manipulation.
     // Create shooting stars periodically
    setInterval(() => {
      const star = document.createElement('div');
      star.className = 'shooting-star';
      star.style.left = Math.random() * 100 + 'vw';
      star.style.top = Math.random() * 100 + 'vh';
      star.style.animationDelay = Math.random() * 5 + 's';
      document.querySelector('.cosmic-bg').appendChild(star);
      
      setTimeout(() => {
        star.remove();
      }, 3000);
    }, 3000);
   
    
    // Animate jackpot counter
    function animateJackpot() {
      const counter = document.getElementById('jackpotCounter');
      let current = 1245789;
      
      setInterval(() => {
        current += Math.floor(Math.random() * 1000);
        counter.textContent = '₹' + current.toLocaleString();
      }, 60000); // Update every minute
    }
    
    // Create floating coins
    function createFloatingCoins() {
      const container = document.getElementById('floatingCoins');
      const coinCount = 15;
      
      for (let i = 0; i < coinCount; i++) {
        const coin = document.createElement('div');
        coin.className = 'coin';
        coin.style.left = Math.random() * 100 + 'vw';
        coin.style.animationDuration = (5 + Math.random() * 10) + 's';
        coin.style.animationDelay = Math.random() * 5 + 's';
        container.appendChild(coin);
      }
    }
    
    // Initialize functions when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
      animateJackpot();
      createFloatingCoins();
      
      // Enable deposit button when terms are checked (attach only once)
      const __termsEl = document.getElementById('termsCheckbox');
      if (__termsEl && !__termsEl.dataset._depositListener) {
        __termsEl.addEventListener('change', function() { checkDepositButton(); });
        __termsEl.dataset._depositListener = '1';
      }
      // Check deposit amount input (attach only once)
      const __depositAmtEl = document.getElementById('depositAmount');
      if (__depositAmtEl && !__depositAmtEl.dataset._depositListener) {
        __depositAmtEl.addEventListener('input', function() { checkDepositButton(); });
        __depositAmtEl.dataset._depositListener = '1';
      }
    });
  </script>
  <script src="assets/js/shared_bottom_nav.js"></script>
</body>
</html>