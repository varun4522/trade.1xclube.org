<?php
// Config Path Logic (Adjust based on file depth)
$config_path = '../../config.php';
if (file_exists($config_path)) {
    require_once $config_path;
}

// Current Page Name (For Active Menu Highlighting)
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Game Controller - God Mode</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: { gray: { 800: '#1f2937', 900: '#111827' } }
                }
            }
        }
    </script>
    <style>
        /* Disable Zoom & Improve Touch */
        * { touch-action: manipulation; -webkit-tap-highlight-color: transparent; }
        /* Hide Scrollbar but allow scroll */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen flex flex-col font-sans">

    <div class="bg-gray-800 shadow-md border-b border-gray-700 sticky top-0 z-50">
        
        <div class="flex justify-between items-center p-3 px-4 border-b border-gray-700/50">
            <h2 class="text-lg font-bold text-blue-500 flex items-center gap-2">
                <i class="fa-solid fa-gamepad"></i> God Mode
            </h2>
            <a href="../controlled_crash.php" class="text-xs bg-gray-700 hover:bg-gray-600 text-white px-3 py-1.5 rounded-full transition-colors">
                <i class="fa-solid fa-arrow-left mr-1"></i> Dashboard
            </a>
        </div>

        <div class="flex overflow-x-auto no-scrollbar py-2 px-2 bg-gray-800/95 backdrop-blur-sm">
            <?php
            // Only show investment control (we removed other game controllers)
            $games = [
                'investment_control.php' => ['icon' => 'fa-sack-dollar', 'name' => 'Investments'],
            ];

            foreach ($games as $file => $info) {
                // Check if this is the active page
                $isActive = ($current_page == $file);
                $activeClass = $isActive ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/30' : 'bg-gray-700 text-gray-400 hover:bg-gray-600 hover:text-white';
                
                echo '<a href="'.$file.'" class="flex-shrink-0 flex items-center gap-2 px-4 py-2 mx-1 rounded-lg text-sm font-medium transition-all duration-200 '.$activeClass.'">
                        <i class="fa-solid '.$info['icon'].'"></i>
                        <span>'.$info['name'].'</span>
                      </a>';
            }
            ?>
        </div>
    </div>

    <div class="flex-1 p-4 md:p-6 animate-fade-in">