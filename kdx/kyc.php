<?php
session_start();
require_once '../config.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle KYC status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }
    
    $kyc_id = filter_var($_POST['kyc_id'], FILTER_SANITIZE_NUMBER_INT);
    $action = in_array($_POST['action'], ['approve', 'reject']) ? $_POST['action'] : '';
    $reason = isset($_POST['reason']) ? htmlspecialchars($_POST['reason'], ENT_QUOTES) : '';
    $admin_notes = isset($_POST['admin_notes']) ? htmlspecialchars($_POST['admin_notes'], ENT_QUOTES) : '';
    
    try {
        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE kyc_verification SET status = 'approved', admin_notes = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$admin_notes, $kyc_id]);
            logAdminAction($_SESSION['user_id'], "Approved KYC #$kyc_id");
            
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE kyc_verification SET status = 'rejected', reason = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$reason, $admin_notes, $kyc_id]);
            logAdminAction($_SESSION['user_id'], "Rejected KYC #$kyc_id");
        }
        
        header('Location: kyc.php?success=1');
        exit;
    } catch (Exception $e) {
        $error = 'Database error occurred';
    }
}

// Fetch KYC submissions with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$status_filter = isset($_GET['status']) ? filter_var($_GET['status'], FILTER_SANITIZE_STRING) : '';
$search_query = isset($_GET['search']) ? filter_var($_GET['search'], FILTER_SANITIZE_STRING) : '';

$where_conditions = [];
$params = [];

if ($status_filter && in_array($status_filter, ['pending', 'approved', 'rejected'])) {
    $where_conditions[] = "k.status = ?";
    $params[] = $status_filter;
}

if ($search_query) {
    $where_conditions[] = "(u.username LIKE ? OR u.phone LIKE ? OR k.full_name LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM kyc_verification k JOIN users u ON k.user_id = u.id $where_clause");
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("
        SELECT k.*, u.username, u.phone 
        FROM kyc_verification k 
        JOIN users u ON k.user_id = u.id 
        $where_clause 
        ORDER BY k.created_at DESC 
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $kyc_submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_pages = ceil($total / $limit);
} catch (Exception $e) {
    $error = 'Failed to load KYC submissions';
    $kyc_submissions = [];
}

function logAdminAction($admin_id, $action) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, ip_address, user_agent) VALUES (?, ?, ?, ?)");
        $stmt->execute([$admin_id, $action, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '']);
    } catch (Exception $e) {}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>KYC Management - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: hsl(258, 90%, 66%); --primary-light: hsl(258, 90%, 72%); }
        html, body { font-family: 'Inter', sans-serif; background: hsl(220, 20%, 8%); color: hsl(0, 0%, 90%); min-height: 100vh; }
        
        .modal-body::-webkit-scrollbar { width: 6px; }
        .modal-body::-webkit-scrollbar-track { background: #1f2937; }
        .modal-body::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 3px; }
        
        .status-pending { background: rgba(234, 179, 8, 0.2); color: #facc15; border: 1px solid rgba(234, 179, 8, 0.3); }
        .status-approved { background: rgba(34, 197, 94, 0.2); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.3); }
        .status-rejected { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
    </style>
</head>
<body class="flex flex-col lg:flex-row min-h-screen bg-[#0d1117]">

    <?php include 'sidebar.php'; ?>

    <div class="flex-1 w-full p-4 lg:p-8">
        
        <div class="animate__animated animate__fadeIn">
            <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-white">KYC Requests</h2>
                    <p class="text-sm text-gray-400">Verify user identities</p>
                </div>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="mb-6 bg-green-500/10 border border-green-500/20 text-green-400 px-4 py-3 rounded-lg flex items-center gap-2">
                    <i class="fas fa-check-circle"></i> Update successful!
                </div>
            <?php endif; ?>

            <div class="bg-[#1f2937] p-4 rounded-xl border border-gray-700 mb-6 shadow-lg">
                <form method="GET" class="flex flex-col md:flex-row gap-3">
                    <div class="relative w-full md:w-48">
                        <select name="status" class="w-full bg-gray-800 border border-gray-600 text-white rounded-lg px-4 py-2.5 focus:outline-none focus:border-purple-500 appearance-none">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                        <i class="fas fa-chevron-down absolute right-3 top-3.5 text-gray-400 pointer-events-none text-xs"></i>
                    </div>
                    
                    <div class="flex-1 relative">
                        <i class="fas fa-search absolute left-3 top-3.5 text-gray-500"></i>
                        <input type="text" name="search" placeholder="Search by name, phone..." value="<?php echo htmlspecialchars($search_query); ?>" 
                               class="w-full bg-gray-800 border border-gray-600 text-white rounded-lg pl-10 pr-4 py-2.5 focus:outline-none focus:border-purple-500">
                    </div>
                    
                    <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-2.5 rounded-lg font-medium transition shadow-lg shadow-purple-900/20 w-full md:w-auto">
                        Search
                    </button>
                </form>
            </div>

            <div class="bg-[#1f2937] rounded-xl border border-gray-700 shadow-xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full whitespace-nowrap text-left">
                        <thead class="bg-gray-800/50 text-gray-400 text-xs uppercase font-semibold">
                            <tr>
                                <th class="px-6 py-4">User</th>
                                <th class="px-6 py-4">Details</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Submitted</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700 text-sm">
                            <?php if(empty($kyc_submissions)): ?>
                                <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500 flex flex-col items-center justify-center">
                                    <i class="fas fa-file-alt text-4xl mb-3 opacity-20"></i>
                                    No records found
                                </td></tr>
                            <?php else: ?>
                                <?php foreach ($kyc_submissions as $kyc): ?>
                                    <tr class="hover:bg-gray-700/30 transition">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-9 h-9 rounded-full bg-gray-700 flex items-center justify-center text-white font-bold text-xs border border-gray-600">
                                                    <?php echo strtoupper(substr($kyc['username'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div class="font-medium text-white"><?php echo htmlspecialchars($kyc['username']); ?></div>
                                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($kyc['phone']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-gray-300 font-medium"><?php echo htmlspecialchars($kyc['full_name']); ?></div>
                                            <div class="text-xs text-gray-500">DOB: <?php echo htmlspecialchars($kyc['date_of_birth']); ?></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-2.5 py-1 rounded-full text-xs font-medium status-<?php echo $kyc['status']; ?>">
                                                <?php echo ucfirst($kyc['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-gray-400 text-xs">
                                            <?php echo date('d M, Y', strtotime($kyc['created_at'])); ?><br>
                                            <span class="text-gray-600"><?php echo date('h:i A', strtotime($kyc['created_at'])); ?></span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex justify-end gap-2">
                                                <button onclick="viewKYC(<?php echo $kyc['id']; ?>)" class="bg-blue-600/20 text-blue-400 hover:bg-blue-600 hover:text-white px-3 py-1.5 rounded-lg transition text-xs font-medium border border-blue-600/30 flex items-center gap-1">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <?php if ($kyc['status'] === 'pending'): ?>
                                                    <button onclick="approveKYC(<?php echo $kyc['id']; ?>)" class="bg-green-600/20 text-green-400 hover:bg-green-600 hover:text-white px-3 py-1.5 rounded-lg transition text-xs font-medium border border-green-600/30" title="Approve">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button onclick="rejectKYC(<?php echo $kyc['id']; ?>)" class="bg-red-600/20 text-red-400 hover:bg-red-600 hover:text-white px-3 py-1.5 rounded-lg transition text-xs font-medium border border-red-600/30" title="Reject">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_pages > 1): ?>
                    <div class="p-4 border-t border-gray-700 flex justify-center gap-2 bg-gray-800/50">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search_query); ?>" class="px-3 py-1.5 bg-gray-700 rounded-lg hover:bg-gray-600 text-sm text-gray-300">Prev</a>
                        <?php endif; ?>
                        
                        <span class="px-3 py-1.5 text-sm text-gray-500">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search_query); ?>" class="px-3 py-1.5 bg-gray-700 rounded-lg hover:bg-gray-600 text-sm text-gray-300">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="viewModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-black/80 backdrop-blur-sm transition-opacity opacity-0" id="viewModalBackdrop"></div>

        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-2xl bg-[#1f2937] text-left shadow-2xl transition-all sm:my-8 w-full max-w-4xl border border-gray-700 flex flex-col max-h-[90vh]">
                    
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-700 bg-[#111827]">
                        <div>
                            <h3 class="text-lg font-bold text-white leading-6">KYC Details</h3>
                            <p class="text-xs text-gray-400 mt-0.5">ID: <span id="modalKycId" class="font-mono text-purple-400">#</span></p>
                        </div>
                        <button type="button" onclick="hideViewModal()" class="text-gray-400 hover:text-white bg-gray-800 hover:bg-gray-700 rounded-lg p-2 transition">
                            <i class="fas fa-times text-lg"></i>
                        </button>
                    </div>

                    <div class="p-6 overflow-y-auto modal-body bg-[#0d1117]" id="kycDetails">
                        <div class="flex flex-col items-center justify-center py-12">
                            <i class="fas fa-circle-notch fa-spin text-4xl text-purple-500 mb-3"></i>
                            <p class="text-gray-500 text-sm">Loading details...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="actionModal" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-black/80 backdrop-blur-sm"></div>
        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative bg-[#1f2937] rounded-2xl shadow-2xl w-full max-w-md p-6 border border-gray-700 transform transition-all">
                    <div class="text-center mb-6">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full mb-4" id="actionIconBg">
                            <i id="actionIcon" class="text-xl"></i>
                        </div>
                        <h3 id="actionTitle" class="text-xl font-bold text-white">Process KYC</h3>
                        <p class="text-gray-400 text-sm mt-1">Submission ID: <span id="modalActionKycId" class="text-purple-400 font-mono"></span></p>
                    </div>
                    
                    <form method="POST" id="actionForm" class="space-y-4">
                        <input type="hidden" name="kyc_id" id="kycId">
                        <input type="hidden" name="action" id="actionType">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div id="rejectReasonDiv" class="hidden">
                            <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase">Rejection Reason <span class="text-red-400">*</span></label>
                            <textarea name="reason" rows="3" class="w-full bg-[#0d1117] border border-gray-600 rounded-xl px-4 py-3 text-white focus:border-red-500 focus:ring-1 focus:ring-red-500 outline-none placeholder-gray-600 text-sm" placeholder="Explain why this is rejected..."></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1.5 uppercase">Admin Notes (Internal)</label>
                            <textarea name="admin_notes" rows="2" class="w-full bg-[#0d1117] border border-gray-600 rounded-xl px-4 py-3 text-white focus:border-purple-500 outline-none placeholder-gray-600 text-sm" placeholder="Optional notes for staff..."></textarea>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-3 pt-2">
                            <button type="button" onclick="hideActionModal()" class="w-full py-2.5 rounded-xl bg-gray-700 text-white hover:bg-gray-600 transition font-medium text-sm">Cancel</button>
                            <button type="submit" id="submitBtn" class="w-full py-2.5 rounded-xl text-white transition font-medium text-sm shadow-lg"></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // --- View Modal Logic ---
        function viewKYC(kycId) {
            const modal = document.getElementById('viewModal');
            const backdrop = document.getElementById('viewModalBackdrop');
            const detailsDiv = document.getElementById('kycDetails');
            
            document.getElementById('modalKycId').textContent = '#' + kycId;
            modal.classList.remove('hidden');
            setTimeout(() => backdrop.classList.remove('opacity-0'), 10);

            // Fetch Data
            fetch(`get_kyc_details.php?id=${kycId}`)
                .then(res => res.json())
                .then(data => {
                    if(data.success) {
                        const kyc = data.kyc;
                        
                        const renderImage = (path, title) => `
                            <div class="group relative">
                                <p class="text-xs text-gray-400 mb-2 uppercase font-semibold tracking-wider flex items-center gap-2"><i class="fas fa-file-image"></i> ${title}</p>
                                <a href="../${path}" target="_blank" class="block overflow-hidden rounded-xl border border-gray-700 bg-black/50 aspect-video relative">
                                    <img src="../${path}" class="w-full h-full object-contain p-2 group-hover:scale-105 transition duration-300" alt="${title}">
                                    <div class="absolute inset-0 bg-black/60 flex items-center justify-center opacity-0 group-hover:opacity-100 transition backdrop-blur-sm">
                                        <span class="text-white text-sm font-medium bg-white/10 px-3 py-1.5 rounded-full border border-white/20"><i class="fas fa-expand mr-1"></i> View Full</span>
                                    </div>
                                </a>
                            </div>
                        `;

                        let selfie = renderImage(kyc.selfie_path, 'Selfie Photo');
                        let govId = renderImage(kyc.id_path, 'Government ID');
                        let extra = kyc.additional_docs_path ? renderImage(kyc.additional_docs_path, 'Extra Doc') : '';

                        let statusColor = kyc.status === 'approved' ? 'text-green-400' : (kyc.status === 'rejected' ? 'text-red-400' : 'text-yellow-400');

                        detailsDiv.innerHTML = `
                            <div class="grid lg:grid-cols-2 gap-8">
                                <div class="space-y-6">
                                    <div class="bg-[#111827] rounded-xl p-5 border border-gray-700">
                                        <h4 class="text-white font-bold border-b border-gray-700 pb-3 mb-4 flex items-center gap-2"><i class="fas fa-user-circle text-purple-500"></i> User Profile</h4>
                                        <div class="space-y-4 text-sm">
                                            <div class="flex justify-between items-center"><span class="text-gray-400">Username</span> <span class="text-white font-mono bg-gray-800 px-2 py-0.5 rounded text-xs">${kyc.username}</span></div>
                                            <div class="flex justify-between items-center"><span class="text-gray-400">Full Name</span> <span class="text-white font-medium">${kyc.full_name}</span></div>
                                            <div class="flex justify-between items-center"><span class="text-gray-400">Phone</span> <span class="text-white font-medium">${kyc.phone}</span></div>
                                            <div class="flex justify-between items-center"><span class="text-gray-400">Date of Birth</span> <span class="text-white">${kyc.date_of_birth}</span></div>
                                            <div class="flex justify-between items-center pt-2 border-t border-gray-700"><span class="text-gray-400">Status</span> <span class="font-bold uppercase text-xs px-2 py-0.5 rounded-full bg-opacity-20 border ${kyc.status === 'approved' ? 'bg-green-500 border-green-500 text-green-400' : (kyc.status === 'rejected' ? 'bg-red-500 border-red-500 text-red-400' : 'bg-yellow-500 border-yellow-500 text-yellow-400')}">${kyc.status}</span></div>
                                        </div>
                                    </div>
                                    
                                    ${kyc.reason ? `
                                        <div class="bg-red-900/10 border border-red-500/30 p-4 rounded-xl">
                                            <p class="text-xs text-red-400 font-bold mb-1 uppercase tracking-wider flex items-center gap-2"><i class="fas fa-exclamation-triangle"></i> Rejection Reason</p>
                                            <p class="text-sm text-red-200">${kyc.reason}</p>
                                        </div>` : ''}
                                    
                                    ${kyc.admin_notes ? `
                                        <div class="bg-blue-900/10 border border-blue-500/30 p-4 rounded-xl">
                                            <p class="text-xs text-blue-400 font-bold mb-1 uppercase tracking-wider flex items-center gap-2"><i class="fas fa-sticky-note"></i> Admin Notes</p>
                                            <p class="text-sm text-blue-200">${kyc.admin_notes}</p>
                                        </div>` : ''}
                                </div>

                                <div>
                                    <h4 class="text-white font-bold border-b border-gray-700 pb-3 mb-4 flex items-center gap-2"><i class="fas fa-folder-open text-blue-500"></i> Documents</h4>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        ${selfie}
                                        ${govId}
                                        ${extra}
                                    </div>
                                </div>
                            </div>
                            
                            <div class="pt-6 mt-6 border-t border-gray-700">
                                <div class="flex flex-wrap gap-6 text-xs text-gray-500">
                                    <span class="flex items-center gap-1"><i class="far fa-clock"></i> Submitted: ${new Date(kyc.created_at).toLocaleString()}</span>
                                    ${kyc.updated_at ? `<span class="flex items-center gap-1"><i class="fas fa-history"></i> Updated: ${new Date(kyc.updated_at).toLocaleString()}</span>` : ''}
                                </div>
                            </div>
                        `;
                    } else {
                        detailsDiv.innerHTML = `
                            <div class="flex flex-col items-center justify-center py-12 text-center">
                                <i class="fas fa-exclamation-circle text-4xl text-red-500 mb-3"></i>
                                <p class="text-red-400">Failed to load data.</p>
                                <button onclick="viewKYC(${kycId})" class="mt-4 px-4 py-2 bg-gray-800 rounded-lg text-white text-sm hover:bg-gray-700">Retry</button>
                            </div>`;
                    }
                })
                .catch(() => {
                    detailsDiv.innerHTML = `
                            <div class="flex flex-col items-center justify-center py-12 text-center">
                                <i class="fas fa-wifi text-4xl text-red-500 mb-3"></i>
                                <p class="text-red-400">Network Error</p>
                            </div>`;
                });
        }

        function hideViewModal() {
            const modal = document.getElementById('viewModal');
            const backdrop = document.getElementById('viewModalBackdrop');
            backdrop.classList.add('opacity-0');
            setTimeout(() => modal.classList.add('hidden'), 200);
        }

        // --- Action Modal Logic ---
        function approveKYC(id) { openActionModal(id, 'approve'); }
        function rejectKYC(id) { openActionModal(id, 'reject'); }

        function openActionModal(id, type) {
            const modal = document.getElementById('actionModal');
            document.getElementById('kycId').value = id;
            document.getElementById('actionType').value = type;
            document.getElementById('modalActionKycId').textContent = '#' + id;
            
            const title = document.getElementById('actionTitle');
            const btn = document.getElementById('submitBtn');
            const rejectDiv = document.getElementById('rejectReasonDiv');
            const icon = document.getElementById('actionIcon');
            const iconBg = document.getElementById('actionIconBg');

            if(type === 'approve') {
                title.textContent = "Approve Verification";
                title.className = "text-xl font-bold text-white";
                btn.textContent = "Confirm Approval";
                btn.className = "w-full py-2.5 rounded-xl bg-green-600 text-white hover:bg-green-700 transition font-medium text-sm shadow-lg shadow-green-900/30";
                rejectDiv.classList.add('hidden');
                
                icon.className = "fas fa-check text-2xl text-green-400";
                iconBg.className = "mx-auto flex h-14 w-14 items-center justify-center rounded-full mb-4 bg-green-500/10 border border-green-500/20";
            } else {
                title.textContent = "Reject Verification";
                title.className = "text-xl font-bold text-white";
                btn.textContent = "Confirm Rejection";
                btn.className = "w-full py-2.5 rounded-xl bg-red-600 text-white hover:bg-red-700 transition font-medium text-sm shadow-lg shadow-red-900/30";
                rejectDiv.classList.remove('hidden');
                
                icon.className = "fas fa-times text-2xl text-red-400";
                iconBg.className = "mx-auto flex h-14 w-14 items-center justify-center rounded-full mb-4 bg-red-500/10 border border-red-500/20";
            }
            
            modal.classList.remove('hidden');
        }

        function hideActionModal() {
            document.getElementById('actionModal').classList.add('hidden');
        }

        // Close on backdrop click
        document.getElementById('viewModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('viewModalBackdrop') || e.target.closest('#viewModalBackdrop')) hideViewModal();
        });
    </script>
</body>
</html>