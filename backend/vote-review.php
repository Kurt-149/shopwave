<?php

require_once __DIR__ . '/../core/api-init.php';
require_once __DIR__ . '/../authentication/database.php';
require_once __DIR__ . '/../core/security.php';
requireCsrfPost();

$pdo = getPdo();
header('Content-Type: application/json');
setSecurityHeaders();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to vote']);
    exit;
}

if (!checkRateLimit('vote_review_' . $_SESSION['user_id'], 10, 60)) {
    echo json_encode(['success' => false, 'message' => 'Too many votes. Please wait.']);
    exit;
}

$reviewId = filter_var($_POST['review_id'] ?? 0, FILTER_VALIDATE_INT);
$voteType = trim($_POST['vote_type'] ?? '');
$userId = $_SESSION['user_id'];

if ($reviewId === false || $reviewId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid review ID']);
    exit;
}

if (!in_array($voteType, ['helpful', 'not_helpful'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid vote type']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("SELECT id FROM reviews WHERE id = ? FOR UPDATE");
    $stmt->execute([$reviewId]);
    if (!$stmt->fetch()) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Review not found']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT vote_type 
        FROM review_votes 
        WHERE review_id = ? AND user_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$reviewId, $userId]);
    $existingVote = $stmt->fetch();
    
    $action = '';
    
    if ($existingVote) {
        if ($existingVote['vote_type'] === $voteType) {
            $stmt = $pdo->prepare("
                DELETE FROM review_votes 
                WHERE review_id = ? AND user_id = ?
            ");
            $stmt->execute([$reviewId, $userId]);
            $action = 'removed';
        } else {
            $stmt = $pdo->prepare("
                UPDATE review_votes 
                SET vote_type = ?, created_at = NOW() 
                WHERE review_id = ? AND user_id = ?
            ");
            $stmt->execute([$voteType, $reviewId, $userId]);
            $action = 'updated';
        }
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO review_votes (review_id, user_id, vote_type, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$reviewId, $userId, $voteType]);
        $action = 'added';
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN vote_type = 'helpful' THEN 1 ELSE 0 END) as helpful_count,
            SUM(CASE WHEN vote_type = 'not_helpful' THEN 1 ELSE 0 END) as not_helpful_count
        FROM review_votes 
        WHERE review_id = ?
    ");
    $stmt->execute([$reviewId]);
    $counts = $stmt->fetch();
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'action' => $action,
        'helpful_count' => (int)($counts['helpful_count'] ?? 0),
        'not_helpful_count' => (int)($counts['not_helpful_count'] ?? 0),
        'message' => 'Vote recorded successfully'
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Vote review error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}