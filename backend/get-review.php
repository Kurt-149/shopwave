<?php
require_once __DIR__ . '/../core/api-init.php';
require_once __DIR__ . '/../core/session-handler.php';
require_once __DIR__ . '/../authentication/database.php';
require_once __DIR__ . '/../core/security.php';

$pdo = getPdo();
header('Content-Type: application/json');
setSecurityHeaders();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$reviewId = filter_var($_GET['review_id'] ?? 0, FILTER_VALIDATE_INT);
$userId = $_SESSION['user_id'];

if ($reviewId === false || $reviewId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid review ID']);
    exit;
}

if (!checkRateLimit('get_review_' . $userId, 30, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many requests']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, rating, comment, product_id 
        FROM reviews 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$reviewId, $userId]);
    $review = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($review) {
        echo json_encode([
            'success' => true,
            'review' => $review
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Review not found']);
    }
    
} catch (PDOException $e) {
    error_log("Get review error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}