<?php

require_once __DIR__ . '/../core/api-init.php';
require_once __DIR__ . '/../authentication/database.php';
require_once __DIR__ . '/../core/security.php';

$pdo = getPdo();

header('Content-Type: application/json');
setSecurityHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login to delete reviews']);
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed']);
    exit;
}

$reviewId = filter_var($_POST['review_id'] ?? 0, FILTER_VALIDATE_INT);
$userId   = $_SESSION['user_id'];
$isAdmin  = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

if ($reviewId === false || $reviewId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid review ID']);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($isAdmin) {
        $stmt = $pdo->prepare("SELECT id, user_id FROM reviews WHERE id = ?");
        $stmt->execute([$reviewId]);
    } else {
        $stmt = $pdo->prepare("SELECT id, user_id FROM reviews WHERE id = ? AND user_id = ?");
        $stmt->execute([$reviewId, $userId]);
    }

    $review = $stmt->fetch();

    if (!$review) {
        $pdo->rollBack();
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Review not found or you do not have permission to delete it']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM review_votes WHERE review_id = ?");
    $stmt->execute([$reviewId]);

    $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
    $stmt->execute([$reviewId]);

    if ($stmt->rowCount() > 0) {
        $pdo->commit();
        echo json_encode([
            'success' => true,
            'message' => $isAdmin ? 'Review removed by admin' : 'Review deleted successfully'
        ]);
    } else {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete review']);
    }
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Delete review error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again.']);
}