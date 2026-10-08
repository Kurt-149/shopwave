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

$cartId   = filter_var($_POST['cart_id']  ?? 0, FILTER_VALIDATE_INT);
$quantity = filter_var($_POST['quantity'] ?? 0, FILTER_VALIDATE_INT);

if ($cartId === false || $cartId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid cart item']);
    exit;
}

if ($quantity === false || $quantity < 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid quantity']);
    exit;
}

if (!checkRateLimit('cart_update_' . $userId, 20, 60)) {
    echo json_encode(['success' => false, 'message' => 'Too many cart operations. Please slow down.']);
    exit;
}

try {
    $pdo = getPdo();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT c.id, c.product_id, c.quantity,
               p.stock, p.price, p.discount_price, p.name, p.status
        FROM cart c
        JOIN products p ON p.id = c.product_id
        WHERE c.id = ? AND c.user_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$cartId, $userId]);
    $cartItem = $stmt->fetch();

    if (!$cartItem) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Cart item not found']);
        exit;
    }

    if ($cartItem['status'] !== 'active') {
        $pdo->prepare("DELETE FROM cart WHERE id = ?")->execute([$cartId]);
        $pdo->commit();
        echo json_encode(['success' => false, 'message' => 'Product is no longer available and has been removed from your cart']);
        exit;
    }

    if ($quantity === 0) {
        $pdo->prepare("DELETE FROM cart WHERE id = ?")->execute([$cartId]);
        $message = 'Item removed from cart';
    } else {
        if ($quantity > (int) $cartItem['stock']) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Only ' . $cartItem['stock'] . ' available in stock',
            ]);
            exit;
        }

        $pdo->prepare("UPDATE cart SET quantity = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$quantity, $cartId]);
        $message = 'Cart updated';
    }

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(c.quantity), 0) AS total_items,
            COALESCE(SUM(
                c.quantity * CASE
                    WHEN p.discount_price IS NOT NULL AND p.discount_price > 0 AND p.discount_price < p.price
                    THEN p.discount_price
                    ELSE p.price
                END
            ), 0) AS subtotal
        FROM cart c
        JOIN products p ON p.id = c.product_id
        WHERE c.user_id = ? AND p.status = 'active'
    ");
    $stmt->execute([$userId]);
    $totals = $stmt->fetch();

    $subtotal = round((float) $totals['subtotal'], 2);
    $shipping = $subtotal >= 1000 ? 0 : 50;
    $tax      = round($subtotal * 0.12, 2);
    $total    = round($subtotal + $shipping + $tax, 2);

    $pdo->commit();

    echo json_encode([
        'success'    => true,
        'message'    => $message,
        'cart_count' => (int) $totals['total_items'],
        'subtotal'   => $subtotal,
        'shipping'   => $shipping,
        'tax'        => $tax,
        'total'      => $total,
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("[update-cart] Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to update cart. Please try again.']);
}