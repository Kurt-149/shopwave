<?php

require_once __DIR__ . '/../core/init.php';
require_once __DIR__ . '/../authentication/database.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/create-notification.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed']);
    exit;
}

$reviewId = filter_var($_POST['review_id'] ?? 0, FILTER_VALIDATE_INT);
$status   = $_POST['status'] ?? '';

if (!$reviewId || !in_array($status, ['approved', 'rejected'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

try {
    $pdo = getPdo();

    $stmt = $pdo->prepare("SELECT r.user_id, r.status, p.name AS product_name FROM reviews r JOIN products p ON p.id = r.product_id WHERE r.id = ?");
    $stmt->execute([$reviewId]);
    $review = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$review) {
        echo json_encode(['success' => false, 'message' => 'Review not found']);
        exit;
    }

    if ($review['status'] === $status) {
        echo json_encode(['success' => false, 'message' => 'Review is already ' . $status]);
        exit;
    }

    $pdo->prepare("UPDATE reviews SET status = ? WHERE id = ?")->execute([$status, $reviewId]);

    if ($status === 'approved') {
        createNotification($review['user_id'], 'review', "Your review for \"{$review['product_name']}\" has been approved!");
    } else {
        createNotification($review['user_id'], 'review', "Your review for \"{$review['product_name']}\" has been rejected.");
    }

    echo json_encode(['success' => true, 'message' => 'Review ' . $status . ' successfully']);

} catch (PDOException $e) {
    error_log('update-review-status error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}