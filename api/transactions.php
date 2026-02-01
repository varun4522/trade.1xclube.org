<?php
header('Content-Type: application/json');
require_once '../config.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = getCurrentUserId();
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // Get total count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total = $stmt->fetchColumn();
    
    // Get transactions
    $stmt = $pdo->prepare("
        SELECT t.*, u.username as processed_by_username 
        FROM transactions t 
        LEFT JOIN users u ON t.processed_by = u.id 
        WHERE t.user_id = ? 
        ORDER BY t.created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $transactions = $stmt->fetchAll();
    
    $total_pages = ceil($total / $limit);
    
    echo json_encode([
        'success' => true,
        'transactions' => $transactions,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_records' => $total,
            'has_prev' => $page > 1,
            'has_next' => $page < $total_pages
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?> 