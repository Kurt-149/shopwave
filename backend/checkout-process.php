<?php

require_once __DIR__ . '/../authentication/database.php';
require_once __DIR__ . '/../core/security.php';
$pdo = getPdo();


if (!isset($_SESSION['user_id']) || basename($_SERVER['PHP_SELF']) == 'checkout-process.php') {
    // header('Location: ../authentication/login-page.php?error=' . urlencode('Please login to checkout'));
    // exit;
    // http_response_code(403);
    // die ('access denied');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$userId = $_SESSION['user_id'];
$checkoutItems = [];
$subtotal = 0;
$source = 'cart';

try {
    if (isset($_GET['product_id']) && isset($_GET['quantity'])) {
        $source = 'buynow';
        $productId = filter_var($_GET['product_id'], FILTER_VALIDATE_INT);
        $quantity = filter_var($_GET['quantity'], FILTER_VALIDATE_INT);
        $selectedColor = trim($_GET['selected_color'] ?? '');
        $selectedSize = trim($_GET['selected_size'] ?? '');

        if ($productId === false || $productId <= 0 || $quantity === false || $quantity <= 0) {
            $_SESSION['error'] = 'Invalid product or quantity';
            header('Location: shop.php'); exit;
        }

        $stmt = $pdo->prepare("SELECT p.id as product_id, p.name, p.price, p.discount_price, p.image_url, p.stock, p.brand, cat.name as category_name FROM products p LEFT JOIN categories cat ON p.category_id = cat.id WHERE p.id = ? AND p.status = 'active'");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product) { $_SESSION['error'] = 'Product not found'; header('Location: shop.php'); exit; }
        if ($quantity > $product['stock']) { $_SESSION['error'] = 'Not enough stock available. Available: ' . $product['stock']; header('Location: product-details.php?id=' . $productId); exit; }

        $price = (!empty($product['discount_price']) && floatval($product['discount_price']) < floatval($product['price'])) ? floatval($product['discount_price']) : floatval($product['price']);
        $checkoutItems[] = ['product_id' => $product['product_id'], 'name' => $product['name'], 'price' => $price, 'original_price' => floatval($product['price']), 'discount_price' => !empty($product['discount_price']) ? floatval($product['discount_price']) : null, 'quantity' => $quantity, 'image_url' => $product['image_url'], 'brand' => $product['brand'], 'category_name' => $product['category_name'], 'selected_color' => $selectedColor, 'selected_size' => $selectedSize, 'subtotal' => $price * $quantity];
        $subtotal = $price * $quantity;

    } else {
        $source = 'cart';
        $stmt = $pdo->prepare("SELECT c.id as cart_id, c.quantity, c.selected_color, c.selected_size, p.id as product_id, p.name, p.price, p.discount_price, p.image_url, p.stock, p.brand, cat.name as category_name FROM cart c JOIN products p ON c.product_id = p.id LEFT JOIN categories cat ON p.category_id = cat.id WHERE c.user_id = ? AND p.status = 'active'");
        $stmt->execute([$userId]);
        $rawItems = $stmt->fetchAll();

        if (empty($rawItems)) { $_SESSION['error'] = 'Your cart is empty'; header('Location: ../public/cart.php'); exit; }

        foreach ($rawItems as $item) {
            if ($item['quantity'] > $item['stock']) { $_SESSION['error'] = $item['name'] . ' has insufficient stock. Available: ' . $item['stock']; header('Location: cart.php'); exit; }
            $price = (!empty($item['discount_price']) && floatval($item['discount_price']) < floatval($item['price'])) ? floatval($item['discount_price']) : floatval($item['price']);
            $lineSubtotal = $price * $item['quantity'];
            $subtotal += $lineSubtotal;
            $checkoutItems[] = ['cart_id' => $item['cart_id'], 'product_id' => $item['product_id'], 'name' => $item['name'], 'price' => $price, 'original_price' => floatval($item['price']), 'discount_price' => !empty($item['discount_price']) ? floatval($item['discount_price']) : null, 'quantity' => $item['quantity'], 'image_url' => $item['image_url'], 'brand' => $item['brand'], 'category_name' => $item['category_name'], 'selected_color' => $item['selected_color'] ?? '', 'selected_size' => $item['selected_size'] ?? '', 'subtotal' => $lineSubtotal];
        }
    }

    $subtotal = round($subtotal, 2);
    $shipping = $subtotal >= 1000 ? 0 : 50;
    $tax = round($subtotal * 0.12, 2);
    $total = round($subtotal + $shipping + $tax, 2);

    $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) { $_SESSION['error'] = 'User not found'; header('Location: ../authentication/login-page.php'); exit; }

    $_SESSION['checkout_data'] = ['items' => $checkoutItems, 'subtotal' => $subtotal, 'shipping' => $shipping, 'tax' => $tax, 'total' => $total, 'source' => $source];

} catch (PDOException $e) {
    error_log("Database error in checkout: " . $e->getMessage());
    $_SESSION['error'] = 'An error occurred. Please try again.';
    header('Location: shop.php'); exit;
}