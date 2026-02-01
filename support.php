<?php
session_start();

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    // Agar login nahi hai to login page par bhej do
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];
// ID ko 6 digits ka banana (Jaisa profile page me tha: 1 -> 000001)
$formatted_id = str_pad($user_id, 6, '0', STR_PAD_LEFT);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title> Help Center</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            /* Premium Light Theme Palette */
            --bg-dark: #ffffff;
            --bg-gradient: linear-gradient(135deg, #ffffff 0%, #fff5f0 100%);
            --glass-bg: rgba(255, 107, 53, 0.1);
            --glass-border: rgba(255, 107, 53, 0.2);
            --primary: #ff6b35;
            --text-main: #1a1a1a;
            --text-muted: #666;
            --success: #10b981;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-dark);
            background-image: var(--bg-gradient);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            justify-content: center;
        }

        /* Ambient Background Glows */
        .glow-bg {
            position: fixed;
            width: 300px;
            height: 300px;
            background: var(--primary);
            filter: blur(150px);
            opacity: 0.15;
            border-radius: 50%;
            z-index: -1;
        }
        .glow-1 { top: -50px; left: -50px; }
        .glow-2 { bottom: -50px; right: -50px; background: #8b5cf6; }

        /* Mobile Container */
        .app-container {
            width: 100%;
            max-width: 480px;
            min-height: 100vh;
            position: relative;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            display: flex;
            flex-direction: column;
        }

        /* Navbar */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            background: rgba(255, 255, 255, 0.98);
            border-bottom: 2px solid var(--glass-border);
            position: sticky;
            top: 0;
            z-index: 50;
            backdrop-filter: blur(10px);
        }

        .nav-btn {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: 0.2s;
        }

        .nav-btn:active { transform: scale(0.95); }

        .page-title {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        /* Hero Status */
        .hero-section {
            padding: 30px 24px;
            text-align: center;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: 20px;
            font-size: 12px;
            color: var(--success);
            font-weight: 600;
            margin-bottom: 15px;
        }

        .pulse {
            width: 8px;
            height: 8px;
            background: var(--success);
            border-radius: 50%;
            box-shadow: 0 0 0 rgba(16, 185, 129, 0.4);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        .hero-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            background: linear-gradient(to right, #fff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-desc {
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.5;
        }

        /* User ID Card */
        .uid-card {
            margin: 0 24px 30px;
            background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
            border-radius: 16px;
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--glass-border);
        }

        .uid-info label {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            margin-bottom: 4px;
        }

        .uid-value {
            font-family: monospace;
            font-size: 18px;
            font-weight: 600;
            letter-spacing: 1px;
            color: #a78bfa; /* Profile page wala violet color */
            text-shadow: 0 0 10px rgba(139, 92, 246, 0.3);
        }

        .copy-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
            transition: 0.2s;
        }

        .copy-btn:active { transform: scale(0.95); }

        /* Menu List */
        .menu-list {
            padding: 0 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 18px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            text-decoration: none;
            color: white;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .item-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-right: 16px;
            background: rgba(0,0,0,0.2);
        }

        /* Specific Colors */
        .is-telegram .item-icon {
            background: linear-gradient(135deg, #2AABEE 0%, #229ED9 100%);
            box-shadow: 0 4px 12px rgba(42, 171, 238, 0.3);
        }
        
        .is-channel .item-icon {
            background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        }

        .item-content { flex: 1; }
        
        .item-title { font-size: 16px; font-weight: 600; display: block; margin-bottom: 2px; }
        .item-sub { font-size: 12px; color: var(--text-muted); }
        .item-arrow { color: var(--text-muted); font-size: 14px; opacity: 0.5; }

        /* Footer */
        .footer {
            margin-top: auto;
            padding: 30px;
            text-align: center;
            color: var(--text-muted);
            font-size: 12px;
        }

        /* Toast Notification */
        .toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #ffffff;
            color: #000;
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 13px;
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            z-index: 100;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .toast.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }

    </style>
</head>
<body>

    <div class="glow-bg glow-1"></div>
    <div class="glow-bg glow-2"></div>

    <div class="app-container">
        
        <nav class="navbar">
            <a href="javascript:history.back()" class="nav-btn">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div class="page-title">Help Center</div>
            <a href="index.php" class="nav-btn">
                <i class="fa-solid fa-house"></i>
            </a>
        </nav>

        <div class="hero-section">
            <div class="status-badge">
                <div class="pulse"></div>
                Support Online
            </div>
            <h1 class="hero-title">How can we help?</h1>
            <p class="hero-desc">Our team is available 24/7 to resolve your queries instantly.</p>
        </div>

        <div class="uid-card">
            <div class="uid-info">
                <label>Your User ID</label>
                <span class="uid-value" id="userId"><?php echo $formatted_id; ?></span>
            </div>
            <button class="copy-btn" onclick="copyToClipboard()">
                <i class="fa-regular fa-copy"></i> Copy
            </button>
        </div>

        <div class="menu-list">
            
            <a href="https://t.me/lxclube" class="menu-item is-telegram">
                <div class="item-icon">
                    <i class="fa-brands fa-telegram"></i>
                </div>
                <div class="item-content">
                    <span class="item-title">Live Chat Support</span>
                    <span class="item-sub">Avg. response: 2 mins</span>
                </div>
                <i class="fa-solid fa-chevron-right item-arrow"></i>
            </a>

            <a href="https://t.me/lxclube" class="menu-item is-channel">
                <div class="item-icon">
                    <i class="fa-solid fa-bullhorn"></i>
                </div>
                <div class="item-content">
                    <span class="item-title">Official Channel</span>
                    <span class="item-sub">Updates, bonuses & news</span>
                </div>
                <i class="fa-solid fa-chevron-right item-arrow"></i>
            </a>

        </div>

        <div class="footer">
            <p>Secure • Encrypted • Private</p>
        </div>

    </div>

    <div class="toast" id="toast">
        <i class="fa-solid fa-circle-check" style="color: #10b981;"></i>
        ID Copied!
    </div>

    <script>
        function copyToClipboard() {
            // Get the text content from the PHP generated ID
            const userId = document.getElementById('userId').innerText;
            
            navigator.clipboard.writeText(userId).then(() => {
                showToast();
            }).catch(err => {
                // Fallback for older browsers
                const textArea = document.createElement("textarea");
                textArea.value = userId;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand("Copy");
                textArea.remove();
                showToast();
            });
        }

        function showToast() {
            const toast = document.getElementById('toast');
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 2000);
        }
    </script>
</body>
</html>