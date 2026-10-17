<?php

require_once __DIR__ . '/../core/api-init.php';
require_once __DIR__ . '/../authentication/database.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/variant-stock.php';
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
$color  = trim(strip_tags($_POST['selected_color'] ?? ''));

if ($cartId === false || $cartId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid cart item']);
    exit;
}

if (strlen($color) > 100) {
    echo json_encode(['success' => false, 'message' => 'Invalid color value']);
    exit;
}

if (!checkRateLimit('cart_variant_' . $userId, 20, 60)) {
    echo json_encode(['success' => false, 'message' => 'Too many requests. Please slow down.']);
    exit;
}

try {
    $pdo = getPdo();

    $stmt = $pdo->prepare("SELECT product_id FROM cart WHERE id = ? AND user_id = ?");
    $stmt->execute([$cartId, $userId]);
    $productId = $stmt->fetchColumn();
    if ($productId === false) {
        echo json_encode(['success' => false, 'message' => 'Cart item not found']);
        exit;
    }

    if (!empty($color)) {
        $stmt = $pdo->prepare("
            SELECT id FROM product_variants
            WHERE product_id = ? AND type = 'color' AND value = ?
            LIMIT 1
        ");
        $stmt->execute([$productId, $color]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Invalid color option']);
            exit;
        }
    }

    $pdo->beginTransaction();
    $result = vsChangeCartOptions($pdo, $userId, $cartId, $color, null);
    if (!$result['ok']) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $result['message']]);
        exit;
    }
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => $result['message'],
        'merged'  => $result['merged'],
        'quantity' => $result['quantity'],
        'selected_color' => $result['selected_color'], 'selected_size' => $result['selected_size'],
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("[update-cart-color] Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to update color. Please try again.']);
}