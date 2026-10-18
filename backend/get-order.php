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

$rate_limit_key = 'order_fetch_' . $_SESSION['user_id'];
if (!checkRateLimit($rate_limit_key, 30, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many requests']);
    exit;
}

$status = $_GET['status'] ?? 'all';
$user_id = $_SESSION['user_id'];
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 2;
$offset = ($page - 1) * $limit;

try {
    $where = "o.user_id = ?";
    $params = [$user_id];

    if ($status !== 'all') {
        // Each tab covers every admin status that belongs with it.
        $tabStatuses = [
            'pending'   => ['pending', 'processing'],
            'shipped'   => ['shipped'],
            'delivered' => ['delivered', 'completed'],
            'cancelled' => ['cancelled'],
        ];
        if (!isset($tabStatuses[$status])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid status']);
            exit;
        }
        $statusList = $tabStatuses[$status];
        $where .= " AND o.status IN (" . implode(',', array_fill(0, count($statusList), '?')) . ")";
        array_push($params, ...$statusList);
    }

    $countSql = "SELECT COUNT(DISTINCT o.id) FROM orders o WHERE $where";
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $totalOrders = $stmtCount->fetchColumn();
    $totalPages = ceil($totalOrders / $limit);

    // admin_note needs migrations/002_orders_admin_note.sql; until it is run, return null.
    $hasAdminNote = (bool) $pdo->query("SHOW COLUMNS FROM orders LIKE 'admin_note'")->fetch();
    $adminNoteSelect = $hasAdminNote ? 'o.admin_note' : 'NULL AS admin_note';

    $sql = "
        SELECT
            o.id,
            o.order_number,
            o.status,
            $adminNoteSelect,
            o.total_amount,
            o.subtotal,
            o.shipping_fee,
            o.tax_amount,
            o.created_at,
            o.payment_method,
            oi.product_id,
            oi.quantity,
            oi.product_price AS price,
            oi.original_price,
            oi.product_name,
            oi.selected_color,
            oi.selected_size,
            oi.subtotal AS item_subtotal,
            p.image_url
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE $where
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $executeParams = array_merge($params, [$limit, $offset]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($executeParams);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $orders = [];
    foreach ($rows as $row) {
        $orderId = $row['id'];
        if (!isset($orders[$orderId])) {
            $orders[$orderId] = [
                'id'             => (int) $row['id'],
                'order_number'   => $row['order_number'],
                'status'         => $row['status'],
                'admin_note'     => $row['admin_note'] ?? null,
                'total_amount'   => $row['total_amount'],
                'subtotal'       => $row['subtotal'],
                'shipping_fee'   => $row['shipping_fee'],
                'tax_amount'     => $row['tax_amount'],
                'created_at'     => $row['created_at'],
                'payment_method' => $row['payment_method'],
                'items'          => []
            ];
        }

        if ($row['product_id'] !== null) {
            $orders[$orderId]['items'][] = [
                'product_name'   => $row['product_name'],
                'quantity'       => $row['quantity'],
                'price'          => $row['price'],
                'original_price' => $row['original_price'],
                'subtotal'       => $row['item_subtotal'],
                'selected_color' => $row['selected_color'],
                'selected_size'  => $row['selected_size'],
                'image_url'      => $row['image_url'],
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'orders'  => array_values($orders),
        'pagination' => [
            'current_page' => $page,
            'total_pages'  => $totalPages,
            'total_orders' => $totalOrders,
            'per_page'     => $limit
        ]
    ]);

} catch (PDOException $e) {
    error_log("Database error in get-order.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}