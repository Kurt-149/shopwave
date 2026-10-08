<?php

require_once __DIR__ . '/../core/api-init.php';
require_once __DIR__ . '/../authentication/database.php';
require_once __DIR__ . '/../core/security.php';

$pdo = getPdo();

header('Content-Type: application/json');
setSecurityHeaders();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to edit reviews']);
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Security validation failed']);
    exit;
}

if (!checkRateLimit('edit_review_' . $_SESSION['user_id'], 5, 300)) {
    echo json_encode(['success' => false, 'message' => 'Too many edit attempts. Please wait.']);
    exit;
}

$reviewId = filter_var($_POST['review_id'] ?? 0, FILTER_VALIDATE_INT);
$rating = filter_var($_POST['rating'] ?? 0, FILTER_VALIDATE_INT);
$comment = trim($_POST['comment'] ?? '');
$userId = $_SESSION['user_id'];

if ($reviewId === false || $reviewId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid review ID']);
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
    $stmt = $pdo->prepare("SELECT edit_count, user_id FROM reviews WHERE id = ? AND user_id = ?");
    $stmt->execute([$reviewId, $userId]);
    $currentReview = $stmt->fetch();

    if (!$currentReview) {
        echo json_encode(['success' => false, 'message' => 'Review not found or you do not have permission to edit it']);
        exit;
    }
    if ($currentReview && isset($currentReview['edit_count']) && $currentReview['edit_count'] >= 1) {
        echo json_encode(['success' => false, 'message' => 'You can only edit your review once. Contact support if you need further changes.']);
        exit;
    }
    $stmt = $pdo->prepare("
        UPDATE reviews 
        SET rating = ?, 
            comment = ?, 
            status = 'approved',
            is_edited = 1,
            edit_count = COALESCE(edit_count, 0) + 1,
            updated_at = NOW() 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$rating, $comment, $reviewId, $userId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Review updated successfully! Your changes are now visible.',
            'review_id' => $reviewId
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No changes were made to the review.']);
    }
} catch (PDOException $e) {
    error_log("Edit review error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again.']);
}