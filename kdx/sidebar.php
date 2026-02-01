<div class="lg:hidden flex items-center justify-between p-4 bg-[#111827] border-b border-gray-800 sticky top-0 z-50">
    <button onclick="toggleSidebar()" class="text-white text-xl p-2 hover:bg-gray-800 rounded-lg transition">
        <i class="fas fa-bars-staggered"></i>
    </button>
    <span class="font-bold tracking-tight text-blue-500 uppercase">Admin Panel</span>
    <div class="w-8"></div> </div>

<div id="sidebarOverlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/60 z-40 hidden backdrop-blur-sm transition-opacity"></div>

<aside id="sidebar" class="sidebar w-64 flex-shrink-0 flex flex-col fixed lg:relative lg:translate-x-0 h-screen overflow-y-auto bg-[#111827] border-r border-gray-800 z-50 transition-transform duration-300 -translate-x-full">
    <div class="p-6">
        <h1 class="text-xl font-bold text-white mb-10 flex items-center gap-2">
            <i class="fas fa-chart-line text-blue-500"></i> Admin Panel
        </h1>

        <nav class="space-y-2">
            <?php $current_page = basename($_SERVER['PHP_SELF']); ?>

            <a href="index.php" class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                <i class="fas fa-home w-6 text-center"></i> Dashboard
            </a>

            <a href="users.php" class="nav-link <?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-group w-6 text-center"></i> User Base
            </a>

            <a href="transactions.php" class="nav-link <?php echo $current_page == 'transactions.php' ? 'active' : ''; ?>">
                <i class="fas fa-wallet w-6 text-center"></i> Transactions
            </a>
            
           <a href="kajana.php" class="nav-link <?php echo $current_page == 'kajana.php' ? 'active' : ''; ?>">
    <i class="fas fa-money-bill-transfer w-6 text-center"></i> Payout
</a>

            <a href="kyc.php" class="nav-link <?php echo $current_page == 'kyc.php' ? 'active' : ''; ?>">
                <i class="fas fa-id-card w-6 text-center"></i> KYC Verification
            </a>

            <a href="bets.php" class="nav-link <?php echo $current_page == 'bets.php' ? 'active' : ''; ?>">
                <i class="fas fa-dice w-6 text-center"></i> Bet History
            </a>

            <a href="referral_details.php" class="nav-link <?php echo $current_page == 'referral_details.php' ? 'active' : ''; ?>">
                <i class="fas fa-share-nodes w-6 text-center"></i> Referrals
            </a>
            
            <a href="settings.php" class="nav-link <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog w-6 text-center"></i> Settings
            </a>

            <div class="mt-8 border-t border-gray-800 pt-4">
                <a href="logout.php" class="nav-link text-red-400 hover:bg-red-500/10 hover:text-red-300">
                    <i class="fas fa-power-off w-6 text-center"></i> Logout
                </a>
            </div>
        </nav>
    </div>
</aside>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (sidebar.classList.contains('-translate-x-full')) {
            // Open Sidebar
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
            setTimeout(() => overlay.classList.remove('opacity-0'), 10); // Fade in backdrop
        } else {
            // Close Sidebar
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('opacity-0');
            setTimeout(() => overlay.classList.add('hidden'), 300); // Wait for transition
        }
    }
</script>

<style>
    .nav-link {
        display: flex;
        align-items: center;
        padding: 0.75rem 1rem;
        color: #9ca3af;
        border-radius: 0.5rem;
        transition: all 0.2s ease-in-out;
        text-decoration: none;
        margin-bottom: 0.25rem;
    }
    .nav-link:hover {
        background-color: #1f2937;
        color: white;
    }
    .nav-link.active {
        background-color: rgba(59, 130, 246, 0.1);
        color: #60a5fa;
        border-left: 3px solid #3b82f6;
    }
    .nav-link i { margin-right: 0.75rem; }
    
    /* Scrollbar for sidebar */
    .sidebar::-webkit-scrollbar { width: 5px; }
    .sidebar::-webkit-scrollbar-track { background: #111827; }
    .sidebar::-webkit-scrollbar-thumb { background: #374151; border-radius: 10px; }
</style>