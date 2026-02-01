<?php
// Config Path
if(file_exists('../config.php')) {
    require_once '../config.php';
} else {
    require_once 'config.php';
}

// Admin Check
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    // header('Location: login.php'); // Login check if needed
    // exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Game Controller - SGS Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: hsl(258, 90%, 66%); }
        body { font-family: 'Inter', sans-serif; background: hsl(220, 20%, 8%); color: hsl(0, 0%, 90%); }
        
        /* Sidebar Styling */
        .sidebar { background: hsl(220, 15%, 12%); border-right: 1px solid hsl(220, 15%, 18%); }
        
        /* Nav Items */
        .nav-item { transition: all 0.3s; border-left: 3px solid transparent; }
        .nav-item:hover { background: hsl(220, 15%, 18%); }
        .nav-item.active { background: hsl(220, 15%, 20%); border-left: 3px solid var(--primary); }
        
        /* Cards */
        .card { background: hsl(220, 15%, 16%); border: 1px solid hsl(220, 15%, 20%); transition: all 0.3s; }
        .card:hover { transform: translateY(-5px); border-color: var(--primary); }
        
        /* Mobile Zoom Fix & Smooth Scroll */
        * { touch-action: manipulation; }
        html { scroll-behavior: smooth; }
        
        /* Overlay for Mobile */
        .overlay { background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(2px); }
    </style>
</head>
<body class="flex min-h-screen relative overflow-x-hidden">

    <div id="sidebarOverlay" onclick="toggleSidebar()" class="fixed inset-0 overlay z-40 hidden md:hidden animate__animated animate__fadeIn"></div>

    <div id="mainSidebar" class="sidebar w-64 flex-shrink-0 py-6 h-screen fixed md:sticky top-0 left-0 z-50 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out overflow-y-auto">
        
        <div class="px-6 mb-6 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-full bg-gradient-to-r from-purple-500 to-indigo-600 flex items-center justify-center">
                    <i class="fa-solid fa-gamepad text-white"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold">Admin Panel</h1>
                    <p class="text-xs text-gray-400">Game Controller</p>
                </div>
            </div>
            <button onclick="toggleSidebar()" class="md:hidden text-gray-400 hover:text-white">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>

        <nav class="mt-8 space-y-1">
            <a href="index.php" class="nav-item block px-6 py-3 flex items-center space-x-3">
                <i class="fa-solid fa-chart-pie w-5 text-gray-400"></i>
                <span>Dashboard</span>
            </a>
            <a href="users.php" class="nav-item block px-6 py-3 flex items-center space-x-3">
                <i class="fa-solid fa-users w-5 text-gray-400"></i>
                <span>Users</span>
            </a>
            <a href="transactions.php" class="nav-item block px-6 py-3 flex items-center space-x-3">
                <i class="fa-solid fa-money-bill-transfer w-5 text-gray-400"></i>
                <span>Transactions</span>
            </a>
            
            <a href="controlled_crash.php" class="nav-item active block px-6 py-3 flex items-center space-x-3">
                <i class="fa-solid fa-gamepad w-5 text-gray-400"></i>
                <span>Controlled Crash</span>
            </a>

            <div class="pt-6 pb-2 px-6 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                Direct Game Access
            </div>

            <a href="controller/chicken_road_control.php" class="nav-item block px-6 py-2 flex items-center space-x-3 text-sm group">
                <i class="fa-solid fa-road w-5 text-center text-blue-500 group-hover:animate-pulse"></i>
                <span>Trade Club</span>
            </a>

            <a href="controller/trading_control.php" class="nav-item block px-6 py-2 flex items-center space-x-3 text-sm group">
                <i class="fa-solid fa-chart-line w-5 text-center text-green-500"></i>
                <span>Trading</span>
            </a>

            <a href="controller/hilo_control.php" class="nav-item block px-6 py-2 flex items-center space-x-3 text-sm group">
                <i class="fa-solid fa-arrows-up-down w-5 text-center text-yellow-500"></i>
                <span>Hilo</span>
            </a>

            <a href="controller/coin_control.php" class="nav-item block px-6 py-2 flex items-center space-x-3 text-sm group">
                <i class="fa-solid fa-coins w-5 text-center text-yellow-400"></i>
                <span>Coin Flip</span>
            </a>

            <!--<a href="controller/andar_bahar_control.php" class="nav-item block px-6 py-2 flex items-center space-x-3 text-sm group">-->
            <!--    <i class="fa-solid fa-diamond w-5 text-center text-red-500"></i>-->
            <!--    <span>Andar Bahar</span>-->
            <!--</a>-->

            <!--<a href="controller/egg_million_control.php" class="nav-item block px-6 py-2 flex items-center space-x-3 text-sm group">-->
            <!--    <i class="fa-solid fa-egg w-5 text-center text-orange-500"></i>-->
            <!--    <span>Egg Million</span>-->
            <!--</a>-->

            <!--<a href="controller/mines_control.php" class="nav-item block px-6 py-2 flex items-center space-x-3 text-sm group">-->
            <!--    <i class="fa-solid fa-bomb w-5 text-center text-purple-500"></i>-->
            <!--    <span>Mines</span>-->
            <!--</a>-->

            <a href="logout.php" class="nav-item block px-6 py-3 flex items-center space-x-3 text-red-400 mt-4 border-t border-gray-800">
                <i class="fa-solid fa-right-from-bracket w-5"></i>
                <span>Logout</span>
            </a>
        </nav>
    </div>

    <div class="flex-1 p-4 md:p-8 overflow-y-auto h-screen animate__animated animate__fadeIn">
        
        <div class="md:hidden flex justify-between items-center mb-6 bg-gray-800 p-4 rounded-lg sticky top-0 z-30 shadow-lg border-b border-gray-700">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="text-white bg-gray-700 hover:bg-gray-600 p-2 rounded-lg transition-colors focus:ring-2 focus:ring-blue-500">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                <h1 class="text-lg font-bold">God Mode</h1>
            </div>
            <a href="index.php" class="text-gray-400 hover:text-white text-sm bg-gray-900 px-3 py-1.5 rounded-full border border-gray-700">
                <i class="fa-solid fa-arrow-left mr-1"></i> Back
            </a>
        </div>

        <div class="mb-8 hidden md:block">
            <h2 class="text-3xl font-bold text-white">God Mode</h2>
            <p class="text-gray-400">Select a game to manipulate results and settings.</p>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">

            <a href="controller/chicken_road_control.php" class="card rounded-xl p-6 flex flex-col items-center justify-center group relative overflow-hidden h-40 md:h-48">
                <div class="absolute top-2 right-2 w-2 h-2 bg-green-500 rounded-full animate-pulse shadow-[0_0_10px_#22c55e]"></div>
                <div class="w-14 h-14 md:w-16 md:h-16 rounded-full bg-gray-800 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform shadow-lg">
                    <i class="fa-solid fa-road text-2xl md:text-3xl text-blue-500"></i>
                </div>
                <h3 class="font-bold text-base md:text-lg text-white text-center">Trade Club</h3>
                <p class="text-[10px] md:text-xs text-gray-500 mt-1 uppercase tracking-wider">Controller</p>
            </a>

            <a href="controller/trading_control.php" class="card rounded-xl p-6 flex flex-col items-center justify-center group relative overflow-hidden h-40 md:h-48">
                <div class="w-14 h-14 md:w-16 md:h-16 rounded-full bg-gray-800 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform shadow-lg">
                    <i class="fa-solid fa-chart-line text-2xl md:text-3xl text-green-500"></i>
                </div>
                <h3 class="font-bold text-base md:text-lg text-white text-center">Trading</h3>
                <p class="text-[10px] md:text-xs text-gray-500 mt-1 uppercase tracking-wider">Graph</p>
            </a>

            <a href="controller/hilo_control.php" class="card rounded-xl p-6 flex flex-col items-center justify-center group relative overflow-hidden h-40 md:h-48">
                <div class="w-14 h-14 md:w-16 md:h-16 rounded-full bg-gray-800 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform shadow-lg">
                    <i class="fa-solid fa-arrows-up-down text-2xl md:text-3xl text-yellow-500"></i>
                </div>
                <h3 class="font-bold text-base md:text-lg text-white text-center">Hilo</h3>
                <p class="text-[10px] md:text-xs text-gray-500 mt-1 uppercase tracking-wider">Cards</p>
            </a>

            <a href="controller/coin_control.php" class="card rounded-xl p-6 flex flex-col items-center justify-center group relative overflow-hidden h-40 md:h-48">
                <div class="w-14 h-14 md:w-16 md:h-16 rounded-full bg-gray-800 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform shadow-lg">
                    <i class="fa-solid fa-coins text-2xl md:text-3xl text-yellow-400"></i>
                </div>
                <h3 class="font-bold text-base md:text-lg text-white text-center">Coin Flip</h3>
                <p class="text-[10px] md:text-xs text-gray-500 mt-1 uppercase tracking-wider">H/T Fix</p>
            </a>

            <!--<a href="controller/andar_bahar.php" class="card rounded-xl p-6 flex flex-col items-center justify-center group relative overflow-hidden h-40 md:h-48">-->
            <!--    <div class="w-14 h-14 md:w-16 md:h-16 rounded-full bg-gray-800 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform shadow-lg">-->
            <!--        <i class="fa-solid fa-diamond text-2xl md:text-3xl text-red-500"></i>-->
            <!--    </div>-->
            <!--    <h3 class="font-bold text-base md:text-lg text-white text-center">Andar Bahar</h3>-->
            <!--    <p class="text-[10px] md:text-xs text-gray-500 mt-1 uppercase tracking-wider">Casino</p>-->
            <!--</a>-->

            <!--<a href="controller/egg_million.php" class="card rounded-xl p-6 flex flex-col items-center justify-center group relative overflow-hidden h-40 md:h-48">-->
            <!--    <div class="w-14 h-14 md:w-16 md:h-16 rounded-full bg-gray-800 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform shadow-lg">-->
            <!--        <i class="fa-solid fa-egg text-2xl md:text-3xl text-orange-500"></i>-->
            <!--    </div>-->
            <!--    <h3 class="font-bold text-base md:text-lg text-white text-center">Egg Million</h3>-->
            <!--    <p class="text-[10px] md:text-xs text-gray-500 mt-1 uppercase tracking-wider">Logic</p>-->
            <!--</a>-->

            <!--<a href="controller/mines.php" class="card rounded-xl p-6 flex flex-col items-center justify-center group relative overflow-hidden h-40 md:h-48">-->
            <!--    <div class="w-14 h-14 md:w-16 md:h-16 rounded-full bg-gray-800 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform shadow-lg">-->
            <!--        <i class="fa-solid fa-bomb text-2xl md:text-3xl text-purple-500"></i>-->
            <!--    </div>-->
            <!--    <h3 class="font-bold text-base md:text-lg text-white text-center">Mines</h3>-->
            <!--    <p class="text-[10px] md:text-xs text-gray-500 mt-1 uppercase tracking-wider">Bomb Set</p>-->
            <!--</a>-->

        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('mainSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            // Toggle sidebar visibility classes
            if (sidebar.classList.contains('-translate-x-full')) {
                // Open Sidebar
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
            } else {
                // Close Sidebar
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
            }
        }
    </script>
</body>
</html>