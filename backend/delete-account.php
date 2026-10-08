<?php
require_once __DIR__ . '/../core/api-init.php';
require_once __DIR__ . '/../authentication/database.php';
require_once __DIR__ . '/../core/security.php';
requireCsrfPost();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$pdo = getPdo();
setSecurityHeaders();

$password = $_POST['password'] ?? '';

if (empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password confirmation required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Incorrect password']);
        exit;
    }
} catch (PDOException $e) {
    error_log("Password verification error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Verification failed']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("DELETE FROM review_votes WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    $stmt = $pdo->prepare("DELETE FROM reviews WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    $stmt = $pdo->prepare("SELECT id FROM orders WHERE user_id = ?");
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($orders)) {
        $placeholders = str_repeat('?,', count($orders) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id IN ($placeholders)");
        $stmt->execute($orders);
    }
    
    $stmt = $pdo->prepare("DELETE FROM orders WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    
    $pdo->commit();
    session_destroy();

    error_log("User account deleted: ID $userId at " . date('Y-m-d H:i:s'));
    
    echo json_encode([
        'success' => true,
        'message' => 'Account successfully deleted'
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Account deletion error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete account. Please try again or contact support.'
    ]);
}