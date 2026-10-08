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

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Please login']);
    exit;
}

$userId = filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT);
if ($userId === false || $userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid session']);
    exit;
}

$cartId = filter_var($_POST['cart_id'] ?? 0, FILTER_VALIDATE_INT);
if ($cartId === false || $cartId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid cart item']);
    exit;
}

if (!checkRateLimit('cart_remove_' . $userId, 30, 60)) {
    echo json_encode(['success' => false, 'message' => 'Too many requests. Please slow down.']);
    exit;
}

try {
    $pdo = getPdo();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id FROM cart WHERE id = ? AND user_id = ? FOR UPDATE");
    $stmt->execute([$cartId, $userId]);

    if (!$stmt->fetch()) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Cart item not found']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
    $stmt->execute([$cartId, $userId]);

    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = ?");
        $stmt->execute([$userId]);
        $cartCount = (int) $stmt->fetchColumn();

        $pdo->commit();

        echo json_encode([
            'success'    => true,
            'message'    => 'Item removed from cart',
            'cart_count' => $cartCount,
        ]);
    } else {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to remove item']);
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("[remove-from-cart] Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to remove item. Please try again.']);
}