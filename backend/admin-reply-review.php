<?php
require_once __DIR__ . '/../core/api-init.php';
require_once __DIR__ . '/../authentication/database.php';
require_once __DIR__ . '/../core/security.php';

$pdo = getPdo();
header('Content-Type: application/json');
setSecurityHeaders();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Security validation failed']);
    exit;
}

$reviewId = filter_var($_POST['review_id'] ?? 0, FILTER_VALIDATE_INT);
$adminReply = trim($_POST['admin_reply'] ?? '');
$userId = $_SESSION['user_id'];

if ($reviewId === false || $reviewId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid review ID']);
    exit;
}

if (empty($adminReply)) {
    echo json_encode(['success' => false, 'message' => 'Reply cannot be empty']);
    exit;
}

if (strlen($adminReply) < 5) {
    echo json_encode(['success' => false, 'message' => 'Reply must be at least 5 characters']);
    exit;
}

if (strlen($adminReply) > 1000) {
    echo json_encode(['success' => false, 'message' => 'Reply must not exceed 1000 characters']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("SELECT id, product_id, admin_reply FROM reviews WHERE id = ? FOR UPDATE");
    $stmt->execute([$reviewId]);
    $review = $stmt->fetch();
    
    if (!$review) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Review not found']);
        exit;
    }

    $isEdit = !empty($review['admin_reply']);
    
    $stmt = $pdo->prepare("
        UPDATE reviews 
        SET admin_reply = ?, 
            admin_reply_at = NOW(),
            admin_reply_by = ?
        WHERE id = ?
    ");
    
    $stmt->execute([$adminReply, $userId, $reviewId]);
    
    if ($stmt->rowCount() > 0) {
        $pdo->commit();
        echo json_encode([
            'success' => true, 
            'message' => $isEdit ? 'Reply updated successfully' : 'Reply posted successfully',
            'review_id' => $reviewId,
            'action' => $isEdit ? 'updated' : 'created'
        ]);
    } else {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'No changes were made.']);
    }
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Admin reply error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again.']);
}