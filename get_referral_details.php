<?php
session_start();
require_once '../config.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Check if user ID is provided
if (!isset($_GET['user_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'User ID required']);
    exit;
}

$user_id = intval($_GET['user_id']);

try {
    // Get user details
    $stmt = $pdo->prepare("
        SELECT u.*, referrer.username as referrer_username, referrer.phone as referrer_phone
        FROM users u 
        LEFT JOIN users referrer ON u.referred_by = referrer.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    // Get users referred by this user
    $stmt = $pdo->prepare("
        SELECT 
            u.*,
            (SELECT COUNT(*) FROM bet_history WHERE user_id = u.id) as total_bets,
            (SELECT COUNT(*) FROM transactions WHERE user_id = u.id) as total_transactions,
            (SELECT SUM(amount) FROM transactions WHERE user_id = u.id AND status = 'approved') as total_deposits
        FROM users u 
        WHERE u.referred_by = ? 
        ORDER BY u.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent activity of referred users
    $stmt = $pdo->prepare("
        SELECT 
            bh.*, u.username, u.phone
        FROM bet_history bh
        JOIN users u ON bh.user_id = u.id
        WHERE u.referred_by = ?
        ORDER BY bh.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate statistics
    $total_referrals = count($referrals);
    $total_referral_earnings = array_sum(array_column($referrals, 'referral_earnings'));
    $total_bets_by_referrals = array_sum(array_column($referrals, 'total_bets'));
    $total_transactions_by_referrals = array_sum(array_column($referrals, 'total_transactions'));
    $total_deposits_by_referrals = array_sum(array_column($referrals, 'total_deposits'));
    
    // Generate HTML content
    $html = '
    <div class="space-y-6">
        <!-- User Info -->
        <div class="bg-gray-50 rounded-lg p-6">
            <div class="flex items-center mb-4">
                <img class="h-16 w-16 rounded-full" src="../' . htmlspecialchars($user['avatar']) . '" alt="">
                <div class="ml-4">
                    <h4 class="text-xl font-bold text-gray-900">' . htmlspecialchars($user['username']) . '</h4>
                    <p class="text-gray-600">' . htmlspecialchars($user['phone']) . '</p>
                    <p class="text-sm text-gray-500">Referral Code: ' . htmlspecialchars($user['referral_code']) . '</p>
                </div>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center">
                    <p class="text-2xl font-bold text-blue-600">' . $total_referrals . '</p>
                    <p class="text-sm text-gray-600">Total Referrals</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-green-600">₹' . number_format($user['referral_earnings'], 2) . '</p>
                    <p class="text-sm text-gray-600">Personal Earnings</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-orange-600">₹' . number_format($total_referral_earnings, 2) . '</p>
                    <p class="text-sm text-gray-600">Total Generated</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-orange-600">' . $total_bets_by_referrals . '</p>
                    <p class="text-sm text-gray-600">Total Bets</p>
                </div>
            </div>
        </div>
        
        <!-- Referrer Info -->
        <div class="bg-white rounded-lg border p-4">
            <h5 class="font-semibold text-gray-900 mb-2">Referrer Information</h5>
            ' . ($user['referrer_username'] ? 
                '<p><strong>Referrer:</strong> ' . htmlspecialchars($user['referrer_username']) . ' (' . htmlspecialchars($user['referrer_phone']) . ')</p>' : 
                '<p class="text-gray-500">No referrer</p>'
            ) . '
        </div>
        
        <!-- Referrals List -->
        <div class="bg-white rounded-lg border">
            <div class="px-6 py-4 border-b">
                <h5 class="font-semibold text-gray-900">Referred Users (' . $total_referrals . ')</h5>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Joined</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Activity</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Earnings</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">';
    
    if (empty($referrals)) {
        $html .= '
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                                No referrals yet
                            </td>
                        </tr>';
    } else {
        foreach ($referrals as $referral) {
            $html .= '
                        <tr>
                            <td class="px-4 py-4">
                                <div class="flex items-center">
                                    <img class="h-8 w-8 rounded-full" src="../' . htmlspecialchars($referral['avatar']) . '" alt="">
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900">' . htmlspecialchars($referral['username']) . '</p>
                                        <p class="text-sm text-gray-500">' . htmlspecialchars($referral['phone']) . '</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-500">
                                ' . date('M j, Y', strtotime($referral['created_at'])) . '
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-900">
                                <div>' . $referral['total_bets'] . ' bets</div>
                                <div class="text-gray-500">' . $referral['total_transactions'] . ' transactions</div>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-900">
                                ₹' . number_format($referral['referral_earnings'], 2) . '
                            </td>
                        </tr>';
        }
    }
    
    $html .= '
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="bg-white rounded-lg border">
            <div class="px-6 py-4 border-b">
                <h5 class="font-semibold text-gray-900">Recent Activity by Referrals</h5>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Activity</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">';
    
    if (empty($recent_activity)) {
        $html .= '
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                                No recent activity
                            </td>
                        </tr>';
    } else {
        foreach ($recent_activity as $activity) {
            $html .= '
                        <tr>
                            <td class="px-4 py-4 text-sm text-gray-900">
                                ' . htmlspecialchars($activity['username']) . '
                            </td>
                            <td class="px-4 py-4">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ' . 
                                    ($activity['result'] === 'cashout' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800') . '">
                                    ' . ucfirst($activity['result']) . '
                                </span>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-900">
                                ₹' . number_format($activity['profit_loss'], 2) . '
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-500">
                                ' . date('M j, Y H:i', strtotime($activity['created_at'])) . '
                            </td>
                        </tr>';
        }
    }
    
    $html .= '
                    </tbody>
                </table>
            </div>
        </div>
    </div>';
    
    echo json_encode([
        'success' => true,
        'html' => $html,
        'user' => $user,
        'referrals' => $referrals,
        'stats' => [
            'total_referrals' => $total_referrals,
            'total_referral_earnings' => $total_referral_earnings,
            'total_bets_by_referrals' => $total_bets_by_referrals,
            'total_transactions_by_referrals' => $total_transactions_by_referrals,
            'total_deposits_by_referrals' => $total_deposits_by_referrals
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?> 