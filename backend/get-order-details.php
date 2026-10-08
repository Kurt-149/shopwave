<?php
require_once __DIR__ . '/../core/api-init.php';
require_once __DIR__ . '/../authentication/database.php';
require_once __DIR__ . '/../core/security.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$pdo = getPdo();
setSecurityHeaders();

$order_number = $_GET['order_number'] ?? '';

if (empty($order_number)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Order number required']);
    exit;
}

if (!preg_match('/^ORD-\d{8}-[A-Z0-9]{8}$/', $order_number)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid order number format']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT
            o.order_number,
            o.status,
            o.total_amount,
            o.subtotal,
            o.shipping_fee,
            o.tax_amount,
            o.created_at,
            o.shipping_name,
            o.shipping_email,
            o.shipping_phone,
            o.shipping_address,
            o.shipping_city,
            o.shipping_postal,
            o.payment_method,
            o.payment_status,
            o.notes,
            oi.product_id,
            oi.quantity,
            oi.product_price AS price,
            oi.original_price,
            oi.product_name,
            oi.selected_color,
            oi.selected_size,
            oi.subtotal AS item_subtotal,
            p.image_url,
            p.brand
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE o.order_number = ? AND o.user_id = ?
    ");

    $stmt->execute([$order_number, $_SESSION['user_id']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }

    $order = [
        'order_number'    => $rows[0]['order_number'],
        'status'          => $rows[0]['status'],
        'total_amount'    => $rows[0]['total_amount'],
        'subtotal'        => $rows[0]['subtotal'],
        'shipping_fee'    => $rows[0]['shipping_fee'],
        'tax_amount'      => $rows[0]['tax_amount'],
        'created_at'      => $rows[0]['created_at'],
        'shipping_name'   => $rows[0]['shipping_name'],
        'shipping_email'  => $rows[0]['shipping_email'],
        'shipping_phone'  => $rows[0]['shipping_phone'],
        'shipping_address' => $rows[0]['shipping_address'],
        'shipping_city'   => $rows[0]['shipping_city'],
        'shipping_postal' => $rows[0]['shipping_postal'],
        'payment_method'  => $rows[0]['payment_method'],
        'payment_status'  => $rows[0]['payment_status'],
        'notes'           => $rows[0]['notes'],
        'items'           => []
    ];

    foreach ($rows as $row) {
        if ($row['product_id'] !== null) {
            $order['items'][] = [
                'product_name'   => $row['product_name'],
                'quantity'       => $row['quantity'],
                'price'          => $row['price'],
                'original_price' => $row['original_price'],
                'subtotal'       => $row['item_subtotal'],
                'selected_color' => $row['selected_color'],
                'selected_size'  => $row['selected_size'],
                'image_url'      => $row['image_url'],
                'brand'          => $row['brand'],
            ];
        }
    }

    echo json_encode(['success' => true, 'order' => $order]);

} catch (PDOException $e) {
    error_log("Database error in get-order-details.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}