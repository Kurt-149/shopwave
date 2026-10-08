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
$size   = trim(strip_tags($_POST['selected_size'] ?? ''));

if ($cartId === false || $cartId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid cart item']);
    exit;
}

if (strlen($size) > 100) {
    echo json_encode(['success' => false, 'message' => 'Invalid size value']);
    exit;
}

if (!checkRateLimit('cart_variant_' . $userId, 20, 60)) {
    echo json_encode(['success' => false, 'message' => 'Too many requests. Please slow down.']);
    exit;
}

try {
    $pdo = getPdo();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT c.id, c.product_id FROM cart c WHERE c.id = ? AND c.user_id = ? FOR UPDATE");
    $stmt->execute([$cartId, $userId]);
    $cartItem = $stmt->fetch();

    if (!$cartItem) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Cart item not found']);
        exit;
    }

    if (!empty($size)) {
        $stmt = $pdo->prepare("
            SELECT id FROM product_variants
            WHERE product_id = ? AND type = 'size' AND value = ?
            LIMIT 1
        ");
        $stmt->execute([$cartItem['product_id'], $size]);
        if (!$stmt->fetch()) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Invalid size option']);
            exit;
        }
    }

    $pdo->prepare("UPDATE cart SET selected_size = ?, updated_at = NOW() WHERE id = ?")
        ->execute([$size ?: null, $cartId]);

    $pdo->commit();

    echo json_encode(['success' => true, 'selected_size' => $size]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("[update-cart-variant] Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to update size. Please try again.']);
}