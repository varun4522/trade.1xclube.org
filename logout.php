<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Logging Out - Trade Club</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
    body { font-family: 'Inter', sans-serif; background: #0b1120; }
    .glass-effect { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
    .loader-bar { transition: width 0.3s ease-out; }
  </style>
</head>
<body class="min-h-screen flex items-center justify-center relative overflow-hidden">
  
  <div id="particles-js" class="fixed inset-0 pointer-events-none"></div>
  
  <div class="glass-effect rounded-2xl p-10 mx-4 max-w-sm w-full text-center animate__animated animate__zoomIn relative z-10">
    <div class="mb-6">
      <div class="w-20 h-20 rounded-full bg-orange-500/10 flex items-center justify-center mx-auto border border-orange-500/20">
        <i class="fas fa-power-off text-indigo-400 text-3xl animate-pulse"></i>
      </div>
    </div>
    
    <h2 class="text-xl font-bold text-white mb-2">Logging Out...</h2>
    <p class="text-slate-400 text-sm mb-8">Securing your session and assets</p>
    
    <div class="w-full bg-slate-700/50 rounded-full h-1.5 mb-6 overflow-hidden">
      <div id="progress-bar" class="loader-bar bg-gradient-to-r from-orange-500 to-orange-600 h-full w-0"></div>
    </div>

    <div class="text-xs text-indigo-400/80 flex items-center justify-center gap-2">
      <i class="fas fa-shield-halved"></i>
      <span>Encryption Active</span>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
  <script>
    particlesJS("particles-js", {
      "particles": {
        "number": { "value": 40 },
        "color": { "value": "#ff6b35" },
        "opacity": { "value": 0.3 },
        "size": { "value": 2 },
        "line_linked": { "enable": true, "distance": 150, "color": "#6366f1", "opacity": 0.1, "width": 1 },
        "move": { "enable": true, "speed": 1 }
      }
    });

    // ISME KOI CONFIRMATION NAHI HAI - DIRECT ACTION
    document.addEventListener('DOMContentLoaded', function() {
      const progressBar = document.getElementById('progress-bar');
      
      // Turant bar badhao
      setTimeout(() => { progressBar.style.width = '45%'; }, 50);

      // Seedha API call bina kisi alert ke
      fetch('api/logout.php')
        .then(response => response.json())
        .then(data => {
            progressBar.style.width = '100%';
            setTimeout(() => {
              window.location.href = 'login.html';
            }, 300);
        })
        .catch(error => {
          // Error hone par bhi login page par bhej do taaki user fanse nahi
          window.location.href = 'login.html';
        });
    });
  </script>
</body>
</html>