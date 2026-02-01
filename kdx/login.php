<?php
require_once '../config.php';

// If already logged in as admin, redirect to dashboard
if (isLoggedIn() && $_SESSION['is_admin']) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username and password are required';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, username, password, is_admin FROM users WHERE username = ? AND is_admin = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user) {
                if (password_verify($password, $user['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['is_admin'] = $user['is_admin'];
                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Invalid credentials';
                }
            } else {
                $error = 'Invalid credentials';
            }
        } catch (Exception $e) {
            $error = 'Login failed. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Chicken Game</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: hsl(258, 90%, 66%);
            --primary-light: hsl(258, 90%, 72%);
            --glow: 0 0 15px hsla(258, 90%, 66%, 0.5);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at 10% 20%, hsl(220, 15%, 12%) 0%, hsl(220, 20%, 8%) 90%);
            min-height: 100vh;
            color: hsl(0, 0%, 90%);
        }
        
        .login-container {
            perspective: 1000px;
        }
        
        .login-card {
            backdrop-filter: blur(12px) saturate(180%);
            -webkit-backdrop-filter: blur(12px) saturate(180%);
            background: hsla(220, 15%, 16%, 0.75);
            border-radius: 16px;
            border: 1px solid hsla(0, 0%, 100%, 0.1);
            box-shadow: var(--glow), 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            transform-style: preserve-3d;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        
        .login-card:hover {
            transform: translateY(-5px) rotateX(2deg) rotateY(2deg);
            box-shadow: 0 0 25px var(--primary-light), 0 30px 60px -12px rgba(0, 0, 0, 0.35);
        }
        
        .floating {
            animation: floating 6s ease-in-out infinite;
            filter: drop-shadow(0 10px 8px rgba(0, 0, 0, 0.3));
        }
        
        @keyframes floating {
            0%, 100% { transform: translateY(0) rotate(-2deg); }
            50% { transform: translateY(-20px) rotate(2deg); }
        }
        
        .input-field {
            transition: all 0.3s;
            background: hsla(220, 15%, 20%, 0.8);
            border: 1px solid hsla(0, 0%, 100%, 0.1);
            color: hsl(0, 0%, 90%);
        }
        
        .input-field:focus {
            background: hsla(220, 15%, 22%, 0.9);
            border-color: var(--primary);
            box-shadow: 0 0 0 3px hsla(258, 90%, 66%, 0.3);
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), hsl(271, 90%, 65%));
            background-size: 200% 200%;
            transition: all 0.5s;
            box-shadow: var(--glow);
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary:hover {
            background-position: 100% 100%;
            transform: translateY(-3px);
            box-shadow: 0 0 25px var(--primary-light), 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        
        .btn-primary:active {
            transform: translateY(1px);
        }
        
        .btn-primary::after {
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
            transition: all 0.5s;
        }
        
        .btn-primary:hover::after {
            left: 100%;
        }
        
        .particle {
            position: absolute;
            background: var(--primary);
            border-radius: 50%;
            pointer-events: none;
            opacity: 0;
        }
        
        @keyframes particle-anim {
            0% {
                transform: translate(0, 0);
                opacity: 1;
            }
            100% {
                transform: translate(var(--x), var(--y));
                opacity: 0;
            }
        }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <div class="login-container">
        <div class="login-card w-full max-w-md p-8 animate__animated animate__fadeIn animate__delay-1s relative overflow-hidden">
            <!-- Animated background elements -->
            <div class="absolute -top-20 -left-20 w-40 h-40 bg-purple-900 rounded-full opacity-10 blur-xl"></div>
            <div class="absolute -bottom-20 -right-20 w-60 h-60 bg-indigo-900 rounded-full opacity-10 blur-xl"></div>
            
            <div class="relative z-10">
                <div class="text-center mb-8">
                    <div class="floating inline-block mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <h2 class="text-3xl font-bold bg-gradient-to-r from-purple-400 to-indigo-300 bg-clip-text text-transparent mb-2">Admin Portal</h2>
                    <p class="text-gray-400">Secure access to dashboard</p>
                </div>

                <form class="space-y-6" method="POST" id="loginForm">
                    <?php if ($error): ?>
                        <div class="bg-red-900/50 border border-red-700/50 p-4 rounded-lg animate__animated animate__shakeX">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 text-red-400 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                                <div class="ml-3">
                                    <p class="text-sm text-red-200"><?php echo htmlspecialchars($error); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="space-y-5">
                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-300 mb-2">Username</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <input id="username" name="username" type="text" required 
                                       class="input-field pl-10 w-full px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 placeholder-gray-500"
                                       placeholder="admin">
                            </div>
                        </div>
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-300 mb-2">Password</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <input id="password" name="password" type="password" required 
                                       class="input-field pl-10 w-full px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 placeholder-gray-500"
                                       placeholder="••••••••">
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input id="remember-me" name="remember-me" type="checkbox" class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-600 rounded bg-gray-800">
                            <label for="remember-me" class="ml-2 block text-sm text-gray-400">Remember me</label>
                        </div>
                        <div class="text-sm">
                            <a href="#" class="font-medium text-purple-400 hover:text-purple-300 transition-colors">Forgot password?</a>
                        </div>
                    </div>

                    <div>
                        <button type="submit" id="loginButton"
                                class="btn-primary w-full flex justify-center items-center py-3 px-4 rounded-lg text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition-all duration-300">
                            <span class="relative z-10">Sign In</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="ml-2 h-5 w-5 relative z-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                            </svg>
                        </button>
                    </div>
                </form>

                <div class="mt-8 text-center text-xs text-gray-500">
                    <p>By continuing, you agree to our <a href="#" class="text-purple-400 hover:underline">Terms</a> and <a href="#" class="text-purple-400 hover:underline">Privacy Policy</a>.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginButton = document.getElementById('loginButton');
            const loginForm = document.getElementById('loginForm');
            
            // Button hover effect
            loginButton.addEventListener('mouseenter', function() {
                createParticles(this);
            });
            
            // Form submission animation
            loginForm.addEventListener('submit', function(e) {
                loginButton.disabled = true;
                loginButton.innerHTML = `
                    <svg class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Authenticating...
                `;
            });
            
            // Create particle effect
            function createParticles(button) {
                const rect = button.getBoundingClientRect();
                const particles = [];
                
                for (let i = 0; i < 8; i++) {
                    const particle = document.createElement('div');
                    particle.classList.add('particle');
                    
                    // Random size between 2px and 4px
                    const size = Math.random() * 2 + 2;
                    particle.style.width = `${size}px`;
                    particle.style.height = `${size}px`;
                    
                    // Position at center of button
                    particle.style.left = `${rect.width / 2}px`;
                    particle.style.top = `${rect.height / 2}px`;
                    
                    // Random direction and distance
                    const angle = Math.random() * Math.PI * 2;
                    const distance = Math.random() * 20 + 10;
                    const x = Math.cos(angle) * distance;
                    const y = Math.sin(angle) * distance;
                    
                    particle.style.setProperty('--x', `${x}px`);
                    particle.style.setProperty('--y', `${y}px`);
                    
                    particle.style.animation = `particle-anim ${Math.random() * 0.6 + 0.4}s forwards`;
                    
                    button.appendChild(particle);
                    particles.push(particle);
                    
                    // Remove after animation
                    setTimeout(() => {
                        particle.remove();
                    }, 1000);
                }
            }
            
            // 3D tilt effect
            const card = document.querySelector('.login-card');
            card.addEventListener('mousemove', (e) => {
                const xAxis = (window.innerWidth / 2 - e.pageX) / 25;
                const yAxis = (window.innerHeight / 2 - e.pageY) / 25;
                card.style.transform = `rotateY(${xAxis}deg) rotateX(${yAxis}deg)`;
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'rotateY(0deg) rotateX(0deg)';
                card.style.transition = 'all 0.5s ease';
                setTimeout(() => {
                    card.style.transition = '';
                }, 500);
            });
        });
    </script>
</body>
</html>