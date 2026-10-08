<?php

require_once __DIR__ . '/../core/api-init.php';
require_once __DIR__ . '/../authentication/database.php';
require_once dirname(__DIR__) . '/core/security.php';

$pdo = getPdo();
header('Content-Type: application/json');
setSecurityHeaders();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login']);
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
    exit;
}

if (!isset($_SESSION['checkout_data'])) {
    echo json_encode(['success' => false, 'message' => 'No checkout data found']);
    exit;
}

if (!checkRateLimit('order_' . $_SESSION['user_id'], 3, 3600)) {
    echo json_encode([
        'success' => false,
        'message' => 'You have placed too many orders recently. Please try again later.'
    ]);
    exit;
}

$userId = $_SESSION['user_id'];
$checkoutData = $_SESSION['checkout_data'];

$shippingName = trim($_POST['shipping_name'] ?? '');
$shippingEmail = trim($_POST['shipping_email'] ?? '');
$shippingPhone = trim($_POST['shipping_phone'] ?? '');
$shippingAddress = trim($_POST['shipping_address'] ?? '');
$shippingCity = trim($_POST['shipping_city'] ?? '');
$shippingPostal = trim($_POST['shipping_postal'] ?? '');
$paymentMethod = trim($_POST['payment_method'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if (empty($shippingName) || empty($shippingEmail) || empty($shippingPhone) ||
    empty($shippingAddress) || empty($shippingCity) || empty($shippingPostal) ||
    empty($paymentMethod)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
    exit;
}

if (!filter_var($shippingEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

if (!preg_match('/^[\d\s\+\-\(\)]+$/', $shippingPhone)) {
    echo json_encode(['success' => false, 'message' => 'Invalid phone number format']);
    exit;
}

$allowedPaymentMethods = ['cod', 'gcash', 'bank', 'card'];
if (!in_array($paymentMethod, $allowedPaymentMethods)) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment method']);
    exit;
}

if (strlen($shippingName) > 100 || strlen($shippingAddress) > 255 ||
    strlen($shippingCity) > 100 || strlen($shippingPostal) > 20 ||
    strlen($notes) > 500) {
    echo json_encode(['success' => false, 'message' => 'Input too long']);
    exit;
}

try {
    $pdo->beginTransaction();

    foreach ($checkoutData['items'] as $item) {
        $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ? FOR UPDATE");
        $stmt->execute([$item['product_id']]);
        $product = $stmt->fetch();

        if (!$product || $product['stock'] < $item['quantity']) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Product "' . htmlspecialchars($item['name']) . '" is out of stock or has insufficient quantity'
            ]);
            exit;
        }
    }

    $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));

    $stmt = $pdo->prepare("
        INSERT INTO orders (
            user_id, order_number, total_amount, subtotal, shipping_fee, tax_amount, status,
            shipping_name, shipping_email, shipping_phone,
            shipping_address, shipping_city, shipping_postal,
            payment_method, notes, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $userId,
        $orderNumber,
        $checkoutData['total'],
        $checkoutData['subtotal'],
        $checkoutData['shipping'],
        $checkoutData['tax'],
        $shippingName,
        $shippingEmail,
        $shippingPhone,
        $shippingAddress,
        $shippingCity,
        $shippingPostal,
        $paymentMethod,
        $notes
    ]);

    $orderId = $pdo->lastInsertId();

    foreach ($checkoutData['items'] as $item) {
        $originalPrice = $item['original_price'] ?? $item['price'];
        $selectedColor = $item['selected_color'] ?? null;
        $selectedSize = $item['selected_size'] ?? null;

        $stmt = $pdo->prepare("
            INSERT INTO order_items (
                order_id, product_id, product_name,
                product_price, original_price, quantity, subtotal,
                selected_color, selected_size
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $orderId,
            $item['product_id'],
            $item['name'],
            $item['price'],
            $originalPrice,
            $item['quantity'],
            $item['subtotal'],
            $selectedColor,
            $selectedSize
        ]);

        $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        $stmt->execute([$item['quantity'], $item['product_id']]);
    }

    if ($checkoutData['source'] === 'cart') {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->execute([$userId]);
    }

    $pdo->commit();

    unset($_SESSION['checkout_data']);
    $_SESSION['last_order_number'] = $orderNumber;

    echo json_encode([
        'success'      => true,
        'message'      => 'Order placed successfully!',
        'order_number' => $orderNumber,
        'order_id'     => $orderId
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Order processing error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to process order. Please try again.'
    ]);
}