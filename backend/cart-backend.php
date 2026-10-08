<?php

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/var.php';
require_once __DIR__ . '/../authentication/database.php';
require_once __DIR__ . '/../core/security.php';
requireCsrfPost();

if(basename($_SERVER['PHP_SELF']) == 'cart-backend.php'){
    http_response_code(403);
    die ('Access denied');
}
// header('Content-Type: application/json');
// if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
//     echo json_encode(['success' => false, 'message' => 'Invalid request method']);
//     exit;
// }


if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . '/authentication/login-page.php?error=' . urlencode('Please login to view your cart'));
    exit;
}

$timeout = $time ?? 1800;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $timeout)) {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . '/authentication/login-page.php?error=' . urlencode('Session expired. Please login again.'));
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

$userId = filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT);
if ($userId === false || $userId <= 0) {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . '/authentication/login-page.php?error=' . urlencode('Invalid session. Please login again.'));
    exit;
}

try {
    $pdo = getPdo();
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . '/authentication/login-page.php?error=' . urlencode('Account not found. Please login again.'));
        exit;
    }
    if (isset($_SESSION['username']) && $user['username'] !== $_SESSION['username']) {
        $_SESSION['username'] = $user['username'];
    }
} catch (PDOException $e) {
    error_log("[cart-backend] User validation error: " . $e->getMessage());
    header('Location: ' . BASE_URL . '/authentication/login-page.php?error=' . urlencode('An error occurred. Please try again.'));
    exit;
}

function validateCartQuantity(PDO $pdo, int $productId, int $quantity): array {
    $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ? AND status = 'active'");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product) return ['valid' => false, 'message' => 'Product not found or unavailable.'];
    if ($quantity < 1) return ['valid' => false, 'message' => 'Quantity must be at least 1.'];
    if ($quantity > (int) $product['stock']) return ['valid' => false, 'message' => "Only {$product['stock']} left in stock."];
    return ['valid' => true, 'message' => ''];
}

try {
    $stmt = $pdo->prepare("
        SELECT c.id AS cart_id, c.quantity, c.selected_color, c.selected_size, c.created_at AS added_at,
            p.id AS product_id, p.name, p.price, p.discount_price, p.image_url, p.stock, p.brand,
            cat.name AS category_name, cat.id AS category_id,
            pv.image_url AS color_image_url,
            CASE WHEN p.discount_price IS NOT NULL AND p.discount_price > 0 AND p.discount_price < p.price
                THEN ROUND(p.discount_price * c.quantity, 2)
                ELSE ROUND(p.price * c.quantity, 2) END AS subtotal
        FROM cart c
        JOIN products p ON p.id = c.product_id
        LEFT JOIN categories cat ON cat.id = p.category_id
        LEFT JOIN product_variants pv ON pv.product_id = p.id AND pv.type = 'color' AND pv.value = c.selected_color
        WHERE c.user_id = ? AND p.status = 'active'
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$userId]);
    $cartItems = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("[cart-backend] Fetch cart items error: " . $e->getMessage());
    $cartItems = [];
}

$productColors = [];
$productSizes  = [];

if (!empty($cartItems)) {
    $productIds   = array_unique(array_column($cartItems, 'product_id'));
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    try {
        $colorStmt = $pdo->prepare("SELECT product_id, value FROM product_variants WHERE product_id IN ($placeholders) AND type = 'color' ORDER BY product_id, display_order ASC");
        $colorStmt->execute($productIds);
        foreach ($colorStmt->fetchAll() as $row) $productColors[$row['product_id']][] = $row['value'];
    } catch (PDOException $e) { error_log("[cart-backend] Fetch colors error: " . $e->getMessage()); }

    try {
        $sizeStmt = $pdo->prepare("SELECT product_id, value FROM product_variants WHERE product_id IN ($placeholders) AND type = 'size' ORDER BY product_id, display_order ASC");
        $sizeStmt->execute($productIds);
        foreach ($sizeStmt->fetchAll() as $row) $productSizes[$row['product_id']][] = $row['value'];
    } catch (PDOException $e) { error_log("[cart-backend] Fetch sizes error: " . $e->getMessage()); }
}

$subtotal = 0;
$totalItems = 0;
foreach ($cartItems as $item) {
    $qty = max(1, (int) $item['quantity']);
    $effectivePrice = (!empty($item['discount_price']) && (float) $item['discount_price'] < (float) $item['price']) ? (float) $item['discount_price'] : (float) $item['price'];
    $subtotal   += $effectivePrice * $qty;
    $totalItems += $qty;
}
$subtotal = round($subtotal, 2);
$shipping = $subtotal >= 1000 ? 0 : 50;
$tax      = round($subtotal * 0.12, 2);
$total    = round($subtotal + $shipping + $tax, 2);

$suggestedProducts = [];
try {
    $cartProductIds  = array_column($cartItems, 'product_id');
    $cartCategoryIds = array_unique(array_filter(array_column($cartItems, 'category_id')));

    $idExclusion = '';
    $excludeParams = [];
    if (!empty($cartProductIds)) {
        $idExclusion = 'AND p.id NOT IN (' . implode(',', array_fill(0, count($cartProductIds), '?')) . ')';
        $excludeParams = $cartProductIds;
    }

    if (!empty($cartCategoryIds)) {
        $catPlaceholders = implode(',', array_fill(0, count($cartCategoryIds), '?'));
        $params = array_merge($cartCategoryIds, $excludeParams);
        $stmt = $pdo->prepare("SELECT p.id, p.name, p.price, p.discount_price, p.image_url, p.stock, cat.name AS category_name FROM products p LEFT JOIN categories cat ON cat.id = p.category_id WHERE p.category_id IN ($catPlaceholders) $idExclusion AND p.status = 'active' AND p.stock > 0 ORDER BY RAND() LIMIT 9");
        $stmt->execute($params);
        $suggestedProducts = $stmt->fetchAll();
    }

    if (empty($suggestedProducts)) {
        $stmt = $pdo->prepare("SELECT p.id, p.name, p.price, p.discount_price, p.image_url, p.stock, cat.name AS category_name FROM products p LEFT JOIN categories cat ON cat.id = p.category_id WHERE p.status = 'active' AND p.stock > 0 $idExclusion ORDER BY RAND() LIMIT 9");
        $stmt->execute($excludeParams);
        $suggestedProducts = $stmt->fetchAll();
    }
} catch (PDOException $e) { error_log("[cart-backend] Suggested products error: " . $e->getMessage()); }