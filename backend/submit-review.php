<?php

require_once __DIR__ . '/../core/api-init.php';
require_once __DIR__ . '/../authentication/database.php';
require_once __DIR__ . '/../core/security.php';

$pdo = getPdo();
header('Content-Type: application/json');
setSecurityHeaders();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to submit a review']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['success' => true, 'csrf_token' => generateCsrfToken()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please refresh the page and try again.']);
    exit;
}

if (!checkRateLimit('review_' . $_SESSION['user_id'], 3, 300)) {
    echo json_encode(['success' => false, 'message' => 'Too many reviews submitted. Please wait a few minutes.']);
    exit;
}

$productId = filter_var($_POST['product_id'] ?? 0, FILTER_VALIDATE_INT);
$rating = filter_var($_POST['rating'] ?? 0, FILTER_VALIDATE_INT);
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
$userId = $_SESSION['user_id'];

if ($productId === false || $productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit;
}

if ($rating === false || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
    exit;
}

if (empty($comment)) {
    echo json_encode(['success' => false, 'message' => 'Please write a comment']);
    exit;
}

if (strlen($comment) < 10) {
    echo json_encode(['success' => false, 'message' => 'Comment must be at least 10 characters']);
    exit;
}

if (strlen($comment) > 1000) {
    echo json_encode(['success' => false, 'message' => 'Comment must not exceed 1000 characters']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    $stmtCheck = $pdo->prepare("SELECT id FROM products WHERE id = ? AND status = 'active'");
    $stmtCheck->execute([$productId]);
    if (!$stmtCheck->fetch()) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }
    
    $stmtExisting = $pdo->prepare("SELECT id FROM reviews WHERE product_id = ? AND user_id = ?");
    $stmtExisting->execute([$productId, $userId]);
    if ($stmtExisting->fetch()) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'You have already reviewed this product']);
        exit;
    }
    
    $stmtPurchase = $pdo->prepare("
        SELECT COUNT(*) FROM order_items oi 
        JOIN orders o ON oi.order_id = o.id 
        WHERE oi.product_id = ? 
        AND o.user_id = ? 
        AND o.status IN ('delivered', 'completed')
    ");
    $stmtPurchase->execute([$productId, $userId]);
    $hasPurchased = $stmtPurchase->fetchColumn() > 0;
    
    if (!$hasPurchased) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'You can only review products you have purchased and received']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO reviews (product_id, user_id, rating, comment, status, verified_purchase, created_at, updated_at) 
        VALUES (?, ?, ?, ?, 'approved', 1, NOW(), NOW())
    ");
    $stmt->execute([$productId, $userId, $rating, $comment]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Thank you for your review!'
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error in submit-review: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to submit review. Please try again.']);
}