<?php
// Error reporting off to prevent JS breakage
error_reporting(0);
ini_set('display_errors', 0);

include 'db.php'; 
session_start();

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 1. Fetch Settings & Gateway Status
$upi_id = "demo@upi";
$enable_basepay = 'true';
$enable_sunpay = 'true';
$enable_manual = 'true';

// Pre-fill amount from GET param if provided (safe integer)
$prefillAmount = 0;
if (isset($_GET['amount'])) {
    $prefillAmount = intval(preg_replace('/[^0-9]/', '', $_GET['amount']));
    if ($prefillAmount < 0) $prefillAmount = 0;
}

if (isset($conn) && $conn) {
    // Fetch settings safely
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM admin_settings WHERE setting_key IN ('upi_id', 'enable_basepay', 'enable_sunpay', 'enable_manual')");
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while($row = $res->fetch_assoc()){
            if($row['setting_key'] == 'upi_id') $upi_id = $row['setting_value'];
            if($row['setting_key'] == 'enable_basepay') $enable_basepay = $row['setting_value'];
            if($row['setting_key'] == 'enable_sunpay') $enable_sunpay = $row['setting_value'];
            if($row['setting_key'] == 'enable_manual') $enable_manual = $row['setting_value'];
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
  <title>Deposit Funds</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');
    
    * { 
        font-family: 'Outfit', sans-serif; 
        -webkit-tap-highlight-color: transparent; 
    }
    
    body {
        background: linear-gradient(135deg, #ffffff 0%, #fff5f0 25%, #ffe8dc 50%, #ffd4c8 100%);
        color: #1a1a1a;
        min-height: 100vh;
        overflow-x: hidden;
    }

    /* Glass Effect Cards */
    .glass-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 2px solid rgba(255, 107, 53, 0.2);
        box-shadow: 0 8px 32px rgba(255, 107, 53, 0.15);
    }

    /* Input Amount Styling */
    .amount-wrapper {
        background: linear-gradient(145deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.01) 100%);
        border: 1px solid rgba(255, 255, 255, 0.08);
        transition: all 0.3s ease;
    }
    .amount-wrapper:focus-within {
        border-color: #ff6b35;
        box-shadow: 0 0 15px rgba(255, 107, 53, 0.3);
        background: rgba(255, 255, 255, 1);
    }

    /* Quick Chip Buttons */
    .chip-btn {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.05);
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .chip-btn:active {
        transform: scale(0.95);
    }
    .chip-btn:hover {
        background: rgba(255, 255, 255, 0.08);
        border-color: rgba(255, 255, 255, 0.1);
    }

    /* Payment Method Cards */
    .method-card {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(255, 255, 255, 0.05);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }
    
    /* Selection States */
    .method-card.active {
        background: rgba(255, 107, 53, 0.1);
        border-color: #ff6b35;
    }
    .method-card.active .indicator {
        background: #ff6b35;
        box-shadow: 0 0 10px #ff6b35;
    }
    
    /* Sunpay Specific */
    .method-card[data-method="sunpay"].active {
        background: rgba(234, 179, 8, 0.1);
        border-color: #eab308;
    }
    .method-card[data-method="sunpay"].active .indicator {
        background: #eab308;
        box-shadow: 0 0 10px #eab308;
    }

    /* Submit Button Gradient */
    .btn-glow {
        background: linear-gradient(135deg, #ff6b35 0%, #ff8c42 50%, #ffa552 100%);
        box-shadow: 0 4px 20px rgba(255, 107, 53, 0.4);
        transition: all 0.3s ease;
    }
    .btn-glow:active {
        transform: translateY(2px);
        box-shadow: 0 2px 10px rgba(255, 107, 53, 0.3);
    }

    /* Floating Particles */
    .particle {
        position: fixed;
        width: 4px; height: 4px;
        background: rgba(255, 107, 53, 0.3);
        border-radius: 50%;
        pointer-events: none;
        z-index: 0;
        animation: floatUp 10s infinite linear;
    }
    @keyframes floatUp { 0% { transform: translateY(100vh); opacity: 0; } 50% { opacity: 1; } 100% { transform: translateY(-10vh); opacity: 0; } }
  </style>
</head>
<body class="flex flex-col items-center p-4">

  <div class="fixed top-0 left-0 w-full h-full overflow-hidden pointer-events-none">
      <div class="absolute top-[-10%] left-[-10%] w-64 h-64 bg-blue-600/10 rounded-full blur-3xl"></div>
      <div class="absolute bottom-[-10%] right-[-10%] w-64 h-64 bg-orange-600/10 rounded-full blur-3xl"></div>
  </div>

  <div class="w-full max-w-md flex justify-between items-center mb-6 relative z-10 animate__animated animate__fadeInDown">
    <div class="flex items-center gap-3">
        <button onclick="window.location.href='index.php'" class="w-10 h-10 rounded-full glass-card flex items-center justify-center hover:bg-white/5 active:scale-95 transition">
            <i class="fas fa-arrow-left text-gray-300"></i>
        </button>
        <div>
            <h1 class="text-xl font-bold tracking-tight">Add Funds</h1>
            <p class="text-xs text-gray-400">Secure Deposit</p>
        </div>
    </div>
    <button onclick="window.location.href='transactions.html'" class="glass-card px-3 py-2 rounded-xl text-xs font-medium text-gray-300 flex items-center gap-2 hover:bg-white/5 transition">
        <i class="fas fa-history text-blue-400"></i> History
    </button>
  </div>

  <div class="w-full max-w-md relative z-10 animate__animated animate__fadeInUp">
    
    <div class="glass-card rounded-3xl p-6 mb-4">
        <label class="text-xs font-medium text-gray-400 uppercase tracking-wider ml-1 mb-2 block">Enter Amount</label>
        
        <div class="amount-wrapper rounded-2xl flex items-center px-4 py-3 mb-4">
                 <span class="text-2xl text-blue-500 font-bold mr-2">₹</span>
                 <input id="depositAmount" type="number" value="<?php echo ($prefillAmount>0)?htmlspecialchars($prefillAmount):'300'; ?>" min="100"
                     class="w-full bg-transparent text-4xl font-bold text-white placeholder-gray-600 outline-none" 
                     placeholder="0" oninput="updatePaymentDetails()">
        </div>

        <div class="grid grid-cols-4 gap-2">
            <button onclick="addAmount(300)" class="chip-btn py-2.5 rounded-xl text-xs font-semibold text-gray-300">+300</button>
            <button onclick="addAmount(500)" class="chip-btn py-2.5 rounded-xl text-xs font-semibold text-gray-300">+500</button>
            <button onclick="addAmount(1000)" class="chip-btn py-2.5 rounded-xl text-xs font-semibold text-gray-300">+1k</button>
            <button onclick="addAmount(5000)" class="chip-btn py-2.5 rounded-xl text-xs font-semibold text-gray-300">+5k</button>
            <button onclick="addAmount(10000)" class="chip-btn py-2.5 rounded-xl text-xs font-semibold text-gray-300">+10k</button>
            <button onclick="addAmount(15000)" class="chip-btn py-2.5 rounded-xl text-xs font-semibold text-gray-300">+15k</button>
            <button onclick="addAmount(25000)" class="chip-btn py-2.5 rounded-xl text-xs font-semibold text-gray-300">+25k</button>
            <button onclick="addAmount(50000)" class="chip-btn py-2.5 rounded-xl text-xs font-semibold text-gray-300">+50k</button>
        </div>
        <p class="text-[10px] text-gray-500 mt-2 ml-1">*Minimum deposit limit is ₹100</p>
    </div>

    <div class="mb-24"> <h3 class="text-sm font-semibold text-gray-300 mb-3 ml-1">Select Method</h3>
        
        <div class="space-y-3" id="paymentMethodsList">
            
            <?php if($enable_basepay === 'true'): ?>
            <div data-method="basepay" class="method-card p-4 rounded-2xl cursor-pointer flex items-center justify-between group">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-green-500/10 flex items-center justify-center border border-green-500/20 group-hover:scale-110 transition-transform">
                        <i class="fas fa-bolt text-lg text-green-400"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-white">Basepay</h4>
                        <span class="text-[10px] bg-green-500/20 text-green-400 px-1.5 py-0.5 rounded ml-auto">Recommended</span>
                        <p class="text-xs text-gray-400 mt-0.5">Fastest & Secure</p>
                    </div>
                </div>
                <div class="w-5 h-5 rounded-full border-2 border-gray-600 indicator flex items-center justify-center transition-all">
                    <i class="fas fa-check text-[10px] text-white"></i>
                </div>
            </div>
            <?php endif; ?>

            <?php if($enable_sunpay === 'true'): ?>
            <div data-method="sunpay" class="method-card p-4 rounded-2xl cursor-pointer flex items-center justify-between group">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-yellow-500/10 flex items-center justify-center border border-yellow-500/20 group-hover:scale-110 transition-transform">
                        <i class="fas fa-sun text-lg text-yellow-400"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-white">Sunpay</h4>
                        <p class="text-xs text-gray-400 mt-0.5">Instant Transfer</p>
                    </div>
                </div>
                <div class="w-5 h-5 rounded-full border-2 border-gray-600 indicator flex items-center justify-center transition-all">
                    <i class="fas fa-check text-[10px] text-white"></i>
                </div>
            </div>
            <?php endif; ?>

            <?php if($enable_manual === 'true'): ?>
            <div data-method="qr" class="method-card p-4 rounded-2xl cursor-pointer flex items-center justify-between group">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-blue-500/10 flex items-center justify-center border border-blue-500/20 group-hover:scale-110 transition-transform">
                        <i class="fas fa-qrcode text-lg text-blue-400"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-white">Manual Pay</h4>
                        <p class="text-xs text-gray-400 mt-0.5">Scan QR Code</p>
                    </div>
                </div>
                <div class="w-5 h-5 rounded-full border-2 border-gray-600 indicator flex items-center justify-center transition-all">
                    <i class="fas fa-check text-[10px] text-white"></i>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <input type="hidden" id="depositMethod" value="">

        <div id="paymentDetails" class="mt-4"></div>

        <div id="manualFieldsWrapper" class="hidden mt-4 animate__animated animate__fadeIn">
            <div class="glass-card p-5 rounded-2xl space-y-4">
                <div>
                    <label class="text-xs text-gray-400 mb-1.5 block ml-1">Transaction ID / UTR</label>
                    <div class="relative">
                        <i class="fas fa-hashtag absolute left-4 top-3.5 text-gray-500 text-xs"></i>
                        <input type="text" id="transactionId" 
                               class="w-full bg-black/30 border border-white/10 rounded-xl py-3 pl-10 pr-4 text-sm text-white focus:border-blue-500 focus:outline-none transition-colors"
                               placeholder="Enter 12 digit UTR">
                    </div>
                </div>
                <div>
                    <label class="text-xs text-gray-400 mb-1.5 block ml-1">Upload Screenshot</label>
                    <label class="block w-full h-28 border-2 border-dashed border-white/10 rounded-xl cursor-pointer hover:border-blue-500/50 hover:bg-white/5 transition-all relative overflow-hidden group">
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <div class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                                <i class="fas fa-cloud-upload-alt text-gray-400"></i>
                            </div>
                            <span class="text-xs text-gray-500 group-hover:text-gray-300">Tap to browse image</span>
                        </div>
                        <input type="file" id="screenshot" accept="image/*" class="opacity-0 w-full h-full cursor-pointer">
                    </label>
                    <div id="fileName" class="text-xs text-green-400 mt-2 hidden flex items-center gap-1">
                        <i class="fas fa-check-circle"></i> <span id="fileText"></span>
                    </div>
                </div>
            </div>
        </div>

    </div>

  </div>

  <div class="fixed bottom-0 left-0 w-full p-4 bg-gradient-to-t from-[#0b0f19] to-transparent z-40">
      <div class="max-w-md mx-auto">
          <button id="depositBtn" class="w-full btn-glow text-white font-bold py-4 rounded-2xl flex items-center justify-center gap-2 text-lg disabled:opacity-70 disabled:cursor-not-allowed">
              <span>Deposit Now</span>
              <i class="fas fa-arrow-right animate-pulse"></i>
          </button>
      </div>
  </div>

  <div id="loadingModal" class="fixed inset-0 flex items-center justify-center bg-black/90 z-50 hidden backdrop-blur-sm animate__animated animate__fadeIn">
    <div class="text-center">
        <div class="w-20 h-20 border-4 border-blue-500/30 border-t-blue-500 rounded-full animate-spin mx-auto mb-4"></div>
        <h3 class="text-xl font-bold text-white mb-1 loading-text">Processing...</h3>
        <p class="text-sm text-gray-400">Please do not close this window</p>
    </div>
  </div>

  <div id="resultModal" class="fixed inset-0 flex items-center justify-center bg-black/90 z-50 hidden animate__animated animate__zoomIn">
    <div class="glass-card p-8 rounded-3xl text-center max-w-xs w-full relative overflow-hidden">
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full h-20 bg-green-500/20 blur-3xl"></div>
        
        <div class="w-16 h-16 bg-green-500/10 rounded-full flex items-center justify-center mx-auto mb-4 border border-green-500/30">
            <i class="fas fa-check text-3xl text-green-400"></i>
        </div>
        <h2 class="text-2xl font-bold text-white mb-2">Request Sent!</h2>
        <p class="text-gray-400 text-sm mb-6">Your deposit request of <span id="resultAmount" class="text-white font-bold"></span> has been submitted.</p>
        <button id="closeModalBtn" class="w-full bg-white/10 hover:bg-white/20 text-white py-3 rounded-xl font-medium transition">
            Close
        </button>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
  <script>
    const adminUpiId = "<?php echo htmlspecialchars(trim($upi_id)); ?>"; 
    let selectedMethod = '';

    document.addEventListener('DOMContentLoaded', function() {
        initMethodSelection();
        initQuickButtons();
        initFileUpload();

        // --- FIXED AUTO-SELECT LOGIC ---
        setTimeout(() => {
            const firstMethod = document.querySelector('.method-card');
            if(firstMethod) {
                const cards = document.querySelectorAll('.method-card');
                const hiddenInput = document.getElementById('depositMethod');
                const manualWrapper = document.getElementById('manualFieldsWrapper');

                cards.forEach(c => c.classList.remove('active'));
                
                firstMethod.classList.add('active');
                
                selectedMethod = firstMethod.dataset.method;
                hiddenInput.value = selectedMethod;
                
                if(selectedMethod === 'qr') {
                    manualWrapper.classList.remove('hidden');
                } else {
                    manualWrapper.classList.add('hidden');
                }
                
                updatePaymentDetails();
            }
        }, 100);
    });

    function initMethodSelection() {
      const cards = document.querySelectorAll('.method-card');
      const hiddenInput = document.getElementById('depositMethod');
      const manualWrapper = document.getElementById('manualFieldsWrapper');

      cards.forEach(card => {
        card.addEventListener('click', () => {
          // Reset classes
          cards.forEach(c => c.classList.remove('active'));
          // Set active
          card.classList.add('active');
          
          selectedMethod = card.dataset.method;
          hiddenInput.value = selectedMethod;
          
          if(selectedMethod === 'qr') {
            manualWrapper.classList.remove('hidden');
            
            // --- INSTANT SCROLL FIX ---
            // Update UI first
            updatePaymentDetails(); 
            
            // Force instant jump
            setTimeout(() => {
                const el = document.getElementById('paymentDetails');
                el.scrollIntoView({ behavior: 'auto', block: 'center' });
                // Small adjustment if header covers it
                window.scrollBy(0, -50);
            }, 10);

          } else {
            manualWrapper.classList.add('hidden');
            updatePaymentDetails();
          }
        });
      });
    }

    function addAmount(val) {
        const input = document.getElementById('depositAmount');
        input.value = val;
        input.parentElement.classList.add('scale-105', 'border-blue-500');
        setTimeout(() => input.parentElement.classList.remove('scale-105', 'border-blue-500'), 200);
        updatePaymentDetails();
    }
    
    function updatePaymentDetails() {
      const amount = document.getElementById('depositAmount').value || 0;
      const detailsDiv = document.getElementById('paymentDetails');
      detailsDiv.innerHTML = ''; 

      if (selectedMethod === 'qr') {
        const upiLink = `upi://pay?pa=${adminUpiId}&pn=GameDeposit&am=${amount}&cu=INR`;
        const qrApi = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(upiLink)}`;
        
        detailsDiv.innerHTML = `
          <div class="glass-card p-6 rounded-2xl animate__animated animate__fadeIn flex flex-col items-center text-center">
             <p class="text-xs text-gray-400 mb-3 uppercase tracking-wider font-semibold">Scan to Pay ₹${amount}</p>
             <div class="bg-white p-2 rounded-xl mb-4 shadow-lg shadow-white/5">
                <img src="${qrApi}" class="w-48 h-48" alt="Payment QR">
             </div>
             
             <div class="w-full bg-black/30 rounded-xl p-3 flex items-center justify-between border border-white/10 group">
                <div class="text-left overflow-hidden mr-2">
                    <p class="text-[10px] text-gray-500 font-bold mb-0.5 uppercase">UPI ID</p>
                    <p class="text-sm font-mono text-blue-400 truncate">${adminUpiId}</p>
                </div>
                <button onclick="copyUpi()" class="bg-blue-600/20 text-blue-400 p-2.5 rounded-lg hover:bg-blue-600 hover:text-white transition active:scale-95 shrink-0">
                    <i class="fas fa-copy"></i>
                </button>
             </div>
             <p class="text-[10px] text-gray-500 mt-3 flex items-center gap-1">
                <i class="fas fa-info-circle"></i> After payment, upload screenshot & UTR below
             </p>
          </div>`;
      } 
    }

    function copyUpi() {
        navigator.clipboard.writeText(adminUpiId).then(() => {
            const btn = document.querySelector('#paymentDetails button');
            if(btn) {
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i>';
                btn.classList.add('bg-green-500/20', 'text-green-400');
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.classList.remove('bg-green-500/20', 'text-green-400');
                }, 2000);
            }
        });
    }

    function initFileUpload() {
        const input = document.getElementById('screenshot');
        const labelDiv = document.getElementById('fileName');
        const labelText = document.getElementById('fileText');
        input.addEventListener('change', (e) => {
            if(e.target.files[0]) {
                labelText.textContent = e.target.files[0].name;
                labelDiv.classList.remove('hidden');
            }
        });
    }

    function initQuickButtons() {
        // Function to make sure quick buttons work even if not inline
    }

    // --- Form Submission ---
    const depositBtn = document.getElementById('depositBtn');
    if (depositBtn) {
        depositBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (!selectedMethod) {
                alert("Please select a payment method. If none appear, contact support.");
                return;
            }
            
            const amount = parseFloat(document.getElementById('depositAmount').value);
            
            if (!amount || amount < 100) {
                alert(`Minimum amount ₹100 required!`);
                return;
            }

            const formData = new FormData();
            formData.append('amount', amount);
            formData.append('deposit_method', selectedMethod);

            let endpointUrl = '';

            // Routing Logic
            if (selectedMethod === 'basepay') {
                endpointUrl = 'basepay_request.php'; 
                formData.append('transaction_id', 'basepay_auto');
            } 
            else if (selectedMethod === 'sunpay') {
                endpointUrl = 'spdeposit.php';
                formData.append('transaction_id', 'sunpay_auto');
            } 
            else { 
                endpointUrl = 'api/deposit.php'; 
                const transId = document.getElementById('transactionId').value.trim();
                const screenshot = document.getElementById('screenshot').files[0];
                if (!transId) { alert('Enter UTR Number'); return; }
                if (!screenshot) { alert('Upload Payment Screenshot'); return; }
                formData.append('transaction_id', transId);
                formData.append('screenshot', screenshot);
            }

            // Lock UI
            const originalContent = depositBtn.innerHTML;
            depositBtn.disabled = true;
            depositBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i>';
            
            const loadingModal = document.getElementById('loadingModal');
            const loadingText = document.querySelector('.loading-text');
            if(loadingText) loadingText.textContent = "Processing...";
            loadingModal.classList.remove('hidden');

            fetch(endpointUrl, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                // Basepay/Sunpay Redirect
                if (selectedMethod === 'basepay' || selectedMethod === 'sunpay') {
                    if ((data.respCode === 'SUCCESS' && data.payInfo) || (data.status === 'success' && data.payment_url)) {
                        const paymentUrl = data.payInfo || data.payment_url;
                        window.open(paymentUrl, '_blank');
                        
                        if(loadingText) loadingText.innerHTML = 'Payment Page Opened...<br>Redirecting...';
                        setTimeout(() => window.location.href = 'transactions.html', 3000);
                    } else {
                        throw new Error(data.tradeMsg || "Gateway Error");
                    }
                } 
                // Manual Success
                else {
                    if (data.success) {
                        loadingModal.classList.add('hidden');
                        document.getElementById('resultAmount').textContent = '₹' + amount;
                        document.getElementById('resultModal').classList.remove('hidden');
                    } else {
                        throw new Error(data.error || 'Failed');
                    }
                }
            })
            .catch(error => {
                loadingModal.classList.add('hidden');
                alert(error.message || 'Network error.');
                depositBtn.disabled = false;
                depositBtn.innerHTML = originalContent;
            });
        });
    }
    
    // Close Modal
    document.getElementById('closeModalBtn')?.addEventListener('click', () => {
        window.location.href = 'transactions.html';
    });

  </script>
</body>
</html>