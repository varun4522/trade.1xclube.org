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
$result_filter = sanitize($_GET['result'] ?? '');

try {
    // Build query with optional result filter
    $where_clause = "WHERE user_id = ?";
    $params = [$user_id];
    
    if (!empty($result_filter)) {
        $where_clause .= " AND result = ?";
        $params[] = $result_filter;
    }
    
    // Get total count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bet_history $where_clause");
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    
    // Get bet history
    $stmt = $pdo->prepare("
        SELECT * FROM bet_history 
        $where_clause 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->bindValue(1, $params[0], PDO::PARAM_INT);
    if (count($params) > 3) {
        $stmt->bindValue(2, $params[1], PDO::PARAM_STR);
        $stmt->bindValue(3, $params[2], PDO::PARAM_INT);
        $stmt->bindValue(4, $params[3], PDO::PARAM_INT);
    } else if (count($params) > 2) {
        $stmt->bindValue(2, $params[1], PDO::PARAM_INT);
        $stmt->bindValue(3, $params[2], PDO::PARAM_INT);
    } else {
        $stmt->bindValue(2, $params[1], PDO::PARAM_INT);
    }
    $stmt->execute();
    $bets = $stmt->fetchAll();
    
    $total_pages = ceil($total / $limit);
    
    echo json_encode([
        'success' => true,
        'bets' => $bets,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_records' => $total,
            'has_prev' => $page > 1,
            'has_next' => $page < $total_pages
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to fetch bet history']);
}
?> 