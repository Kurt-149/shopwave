<?php
require_once __DIR__ . '/../core/api-init.php';
require_once __DIR__ . '/../authentication/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}
$pdo = getPdo();

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 8;
$offset = ($page - 1) * $limit;

try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE user_id = ?");
    $countStmt->execute([$_SESSION['user_id']]);
    $totalReviews = $countStmt->fetchColumn();
    $totalPages = ceil($totalReviews / $limit);
    
    $stmt = $pdo->prepare("
        SELECT 
            r.id,
            r.product_id,
            r.rating,
            r.comment,
            r.status,
            r.created_at,
            COALESCE(p.name, 'Product unavailable') AS product_name,
            p.image_url
        FROM reviews r
        LEFT JOIN products p ON r.product_id = p.id
        WHERE r.user_id = ?
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?
    ");

    $stmt->execute([$_SESSION['user_id'], $limit, $offset]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'reviews' => $reviews,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_reviews' => $totalReviews,
            'per_page' => $limit
        ]
    ]);

} catch (PDOException $e) {
    error_log("Database error in get-user-review.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
}