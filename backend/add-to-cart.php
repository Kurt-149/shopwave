<?php
require_once __DIR__ . '/../core/api-init.php';
require_once __DIR__ . '/../core/session-handler.php';
require_once __DIR__ . '/../authentication/database.php';
require_once __DIR__ . '/../core/security.php';
requireCsrfPost();

$pdo = getPdo();

header('Content-Type: application/json');
setSecurityHeaders();

if (!isLoggedIn()) {
    echo json_encode([
        'success' => false, 
        'message' => 'Please login to add items to cart',
        'login_required' => true
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$quantity  = isset($_POST['quantity'])   ? intval($_POST['quantity'])   : 1;
$color     = trim($_POST['selected_color'] ?? '');
$size      = trim($_POST['selected_size']  ?? '');
$userId    = $_SESSION['user_id'];

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit;
}

if ($quantity <= 0 || $quantity > 999) {
    echo json_encode(['success' => false, 'message' => 'Invalid quantity']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("SELECT id, name, stock, status FROM products WHERE id = ? FOR UPDATE");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }

    if ($product['status'] !== 'active') {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Product is not available']);
        exit;
    }

    if ($quantity > $product['stock']) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Not enough stock. Only ' . $product['stock'] . ' available']);
        exit;
    }

    $colorVal = $color !== '' ? $color : null;
    $sizeVal  = $size  !== '' ? $size  : null;

    $stmt = $pdo->prepare("
        SELECT id, quantity FROM cart
        WHERE user_id = ? AND product_id = ?
        AND (selected_color <=> ?) AND (selected_size <=> ?)
        FOR UPDATE
    ");
    $stmt->execute([$userId, $productId, $colorVal, $sizeVal]);
    $existingCart = $stmt->fetch();

    if ($existingCart) {
        $newQuantity = $existingCart['quantity'] + $quantity;
        if ($newQuantity > $product['stock']) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Cannot add more. You already have ' . $existingCart['quantity'] . ' in cart. Maximum stock: ' . $product['stock']
            ]);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE cart SET quantity = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newQuantity, $existingCart['id']]);
        $message = 'Cart updated! Quantity increased to ' . $newQuantity;
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO cart (user_id, product_id, quantity, selected_color, selected_size, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$userId, $productId, $quantity, $colorVal, $sizeVal]);
        $message = 'Product added to cart!';
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM cart WHERE user_id = ?");
    $stmt->execute([$userId]);
    $cartTotal = $stmt->fetchColumn() ?? 0;
    
    $pdo->commit();

    echo json_encode([
        'success'      => true,
        'message'      => $message,
        'cart_count'   => (int)$cartTotal,
        'product_name' => $product['name']
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Add to cart error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to add to cart. Please try again.']);
}