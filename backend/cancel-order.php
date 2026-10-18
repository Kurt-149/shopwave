<?php
/**
 * Customer cancels their own order.
 * Only orders still "pending" can be cancelled. Their stock is put back in the same transaction.
 */
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/init.php';
require_once __DIR__ . '/../core/session-handler.php';
require_once __DIR__ . '/../authentication/database.php';
require_once __DIR__ . '/../core/variant-stock.php';
require_once __DIR__ . '/create-notification.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'], $_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in first.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Checked after the session is loaded, so the token can be compared.
requireCsrfPost();

$userId  = (int) $_SESSION['user_id'];
$orderId = (int) ($_POST['order_id'] ?? 0);
if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid order.']);
    exit;
}

try {
    $pdo = getPdo();
    $pdo->beginTransaction();

    // Lock the order so it cannot be cancelled twice at once.
    $stmt = $pdo->prepare("SELECT id, order_number, status FROM orders WHERE id = ? AND user_id = ? FOR UPDATE");
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit;
    }

    if ($order['status'] !== 'pending') {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'This order can no longer be cancelled because it is already being processed. Please contact us for help.',
        ]);
        exit;
    }

    $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")
        ->execute([$orderId]);

    // Put the stock back that was taken when the order was placed.
    $items = $pdo->prepare("SELECT product_id, quantity, selected_color, selected_size FROM order_items WHERE order_id = ?");
    $items->execute([$orderId]);
    foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $productId = (int) $item['product_id'];
        $qty       = (int) $item['quantity'];
        if ($productId <= 0 || $qty <= 0) {
            continue;
        }

        if (vsProductUsesVariantStock($pdo, $productId)) {
            $pdo->prepare("UPDATE product_variant_stock SET stock = stock + ?
                           WHERE product_id = ? AND color = ? AND size = ?")
                ->execute([$qty, $productId, vsNormalize($item['selected_color'] ?? ''), vsNormalize($item['selected_size'] ?? '')]);
            vsSyncProductTotal($pdo, $productId);
        } else {
            $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")
                ->execute([$qty, $productId]);
        }
    }

    $pdo->commit();

    createNotification($userId, 'order', "Your order #{$order['order_number']} has been cancelled.", 'cancelled');

    echo json_encode(['success' => true, 'message' => 'Your order has been cancelled.']);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[cancel-order] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not cancel the order. Please try again.']);
}