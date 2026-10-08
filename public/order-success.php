<?php
require_once __DIR__ . '/../core/security.php';
require_once dirname(__DIR__) . '/core/init.php';
require_once dirname(__DIR__) . '/core/session-handler.php';
require_once dirname(__DIR__) . '/authentication/database.php';
require_once dirname(__DIR__) . '/core/config.php';

$root = BASE_URL;

if (!isLoggedIn()) {
    header('Location: ' . $root . '/authentication/login-page.php?error=' . urlencode('Please login to continue'));
    exit;
}
$pdo = getPdo();
$orderNumber = null;
if (isset($_GET['order'])) {
    $orderNumber = trim($_GET['order']);
} elseif (isset($_SESSION['last_order_number'])) {
    $orderNumber = $_SESSION['last_order_number'];
}

if (!$orderNumber || !preg_match('/^ORD-\d{8}-[A-Z0-9]{8}$/', $orderNumber)) {
    $_SESSION['error'] = 'Invalid order number';
    header('Location: ' . $root . '/public/shop.php');
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT o.*, COUNT(oi.id) as item_count
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.order_number = ? AND o.user_id = ?
        GROUP BY o.id
    ");
    $stmt->execute([$orderNumber, $userId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $_SESSION['error'] = 'Order not found or access denied';
        header('Location: ' . $root . '/public/shop.php');
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT oi.*, p.image_url, p.brand, c.name as category_name
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order['id']]);
    $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    unset($_SESSION['last_order_number']);

    if ($order['subtotal'] === null)     $order['subtotal']     = $order['total_amount'];
    if ($order['shipping_fee'] === null) $order['shipping_fee'] = 0;
    if ($order['tax_amount'] === null)   $order['tax_amount']   = 0;

} catch (PDOException $e) {
    error_log("Error fetching order: " . $e->getMessage());
    $_SESSION['error'] = 'Error loading order details';
    header('Location: ' . $root . '/public/shop.php');
    exit;
}

$page = 'order-success';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(function_exists('generateCsrfToken') ? generateCsrfToken() : '', ENT_QUOTES); ?>">
    <script>
    (function () {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (!meta || !meta.content || !window.fetch) return;
        var token = meta.content, origFetch = window.fetch;
        window.fetch = function (input, init) {
            init = init || {};
            if ((init.method || 'GET').toUpperCase() === 'POST' && init.body !== undefined) {
                var b = init.body;
                if (typeof FormData !== 'undefined' && b instanceof FormData) {
                    if (!b.has('csrf_token')) b.append('csrf_token', token);
                } else if (typeof URLSearchParams !== 'undefined' && b instanceof URLSearchParams) {
                    if (!b.has('csrf_token')) b.append('csrf_token', token);
                } else if (typeof b === 'string') {
                    if (b.indexOf('csrf_token=') === -1) {
                        init.body = b + (b ? '&' : '') + 'csrf_token=' + encodeURIComponent(token);
                    }
                }
            }
            return origFetch.call(this, input, init);
        };
    })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - SHOPWAVE</title>
    <style>.burger-container,.menu-box,.category-list,.burger-icon{display:none!important}</style>
    <link rel="stylesheet" href="<?php echo asset('design/main-layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('design/web-design/order-success.css'); ?>">
</head>
<body>

<header class="order-success-header">
    <div class="header-main">
        <div class="header-inner">
            <a href="<?php echo $root; ?>/index.php" class="logo">SHOPWAVE</a>
            <a href="<?php echo $root; ?>/public/shop.php" class="continue-shopping-btn">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960">
                    <path d="M400-80 0-480l400-400 71 71-329 329 329 329-71 71Z"/>
                </svg>
                <span>Continue Shopping</span>
            </a>
        </div>
    </div>
</header>

<div class="wrapper">
    <div class="page-body">
        <div class="success-container">
            <div class="success-header">
                <div class="success-icon">✓</div>
                <h1>Order Placed Successfully!</h1>
                <p>Thank you for your purchase</p>
                <div class="order-number">
                    <span>Order Number:</span>
                    <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                </div>
            </div>

            <div class="security-notice">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px"><path d="M480-80q-139-35-229.5-159.5T160-516v-244l320-120 320 120v244q0 152-90.5 276.5T480-80Zm0-84q104-33 172-132t68-220v-189l-240-90-240 90v189q0 121 68 220t172 132Zm0-316Z"/></svg>
                <p><strong>Secure Order:</strong> Your order is protected with a unique reference number that only you can access.</p>
            </div>

            <div class="info-box">
                <p><strong>📧 Confirmation Email Sent</strong> — We've sent an order confirmation to <strong><?php echo htmlspecialchars($order['shipping_email'] ?? ''); ?></strong></p>
            </div>

            <div class="order-details">
                <h2>Order Details</h2>
                <div class="detail-row">
                    <span class="detail-label">Order Date:</span>
                    <span class="detail-value"><?php echo date('F j, Y, g:i a', strtotime($order['created_at'])); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Order Status:</span>
                    <span class="detail-value">
                        <span class="status-badge status-<?php echo htmlspecialchars($order['status']); ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Payment Method:</span>
                    <span class="detail-value"><?php echo strtoupper(htmlspecialchars($order['payment_method'])); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Payment Status:</span>
                    <span class="detail-value"><?php echo ucfirst($order['payment_status'] ?? 'Pending'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Total Items:</span>
                    <span class="detail-value"><?php echo (int)$order['item_count']; ?></span>
                </div>
            </div>

            <div class="order-items">
                <h2>Items Ordered</h2>
                <?php foreach ($orderItems as $item):
                    $hasDiscount = !empty($item['original_price']) && floatval($item['original_price']) > floatval($item['product_price']);
                    $discountPct = $hasDiscount ? round((($item['original_price'] - $item['product_price']) / $item['original_price']) * 100) : 0;
                    $variants = array_filter([$item['selected_color'] ?? '', $item['selected_size'] ?? '']);
                ?>
                    <div class="order-item">
                        <?php if (!empty($item['image_url'])): ?>
                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="item-image">
                        <?php else: ?>
                            <div class="no-image-placeholder">
                                <svg xmlns="http://www.w3.org/2000/svg" height="32px" viewBox="0 -960 960 960" width="32px" fill="#94a3b8"><path d="M200-640v440h560v-440H640v320l-160-80-160 80v-320H200Zm0 520q-33 0-56.5-23.5T120-200v-499q0-14 4.5-27t13.5-24l50-61q11-14 27.5-21.5T250-840h460q18 0 34.5 7.5T772-811l50 61q9 11 13.5 24t4.5 27v499q0 33-23.5 56.5T760-120H200Zm16-600h528l-34-40H250l-34 40Zm184 80v190l80-40 80 40v-190H400Zm-200 0h560-560Z"/></svg>
                            </div>
                        <?php endif; ?>
                        <div class="item-details">
                            <div class="item-top-row">
                                <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                <div class="item-subtotal">P<?php echo number_format($item['subtotal'], 2); ?></div>
                            </div>
                            <div class="item-meta-row">
                                <?php if (!empty($item['brand'])): ?>
                                    <span class="item-meta-label">Brand: <span class="item-meta-value"><?php echo htmlspecialchars($item['brand']); ?></span></span>
                                <?php endif; ?>
                                <?php if (!empty($item['category_name'])): ?>
                                    <span class="item-meta-label">Category: <span class="item-meta-value"><?php echo htmlspecialchars($item['category_name']); ?></span></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($item['selected_color']) || !empty($item['selected_size'])): ?>
                                <div class="item-variants-row">
                                    <?php if (!empty($item['selected_color'])): ?>
                                        <span class="item-meta-label">Color: <span class="item-variants-value"><?php echo htmlspecialchars($item['selected_color']); ?></span></span>
                                    <?php endif; ?>
                                    <?php if (!empty($item['selected_size'])): ?>
                                        <span class="item-meta-label">Size: <span class="item-variants-value"><?php echo htmlspecialchars($item['selected_size']); ?></span></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="item-qty-row">
                                <span class="item-meta-label">Quantity: <span class="item-qty-value"><?php echo (int)$item['quantity']; ?></span></span>
                            </div>
                            <div class="item-pricing">
                                <span class="item-price-current">P<?php echo number_format($item['product_price'], 2); ?></span>
                                <?php if ($hasDiscount): ?>
                                    <span class="item-price-original">P<?php echo number_format($item['original_price'], 2); ?></span>
                                    <span class="item-discount-badge">-<?php echo $discountPct; ?>%</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="total-section">
                <h3>Price Breakdown</h3>
                <div class="total-row">
                    <span>Subtotal (<?php echo (int)$order['item_count']; ?> items)</span>
                    <span>P<?php echo number_format($order['subtotal'], 2); ?></span>
                </div>
                <div class="total-row">
                    <span>Shipping Fee</span>
                    <?php if ($order['shipping_fee'] == 0): ?>
                        <span class="free-shipping-tag">FREE</span>
                    <?php else: ?>
                        <span>P<?php echo number_format($order['shipping_fee'], 2); ?></span>
                    <?php endif; ?>
                </div>
                <div class="total-row">
                    <span>Tax (12% VAT)</span>
                    <span>P<?php echo number_format($order['tax_amount'], 2); ?></span>
                </div>
                <div class="total-divider"></div>
                <div class="total-grand">
                    <span>Total</span>
                    <span>P<?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
            </div>

            <?php if (!empty($order['shipping_name'])): ?>
                <div class="shipping-info">
                    <h3>Shipping Information</h3>
                    <div class="shipping-address">
                        <strong><?php echo htmlspecialchars($order['shipping_name']); ?></strong><br>
                        <?php if (!empty($order['shipping_email'])): ?><?php echo htmlspecialchars($order['shipping_email']); ?><br><?php endif; ?>
                        <?php if (!empty($order['shipping_phone'])): ?><?php echo htmlspecialchars($order['shipping_phone']); ?><br><?php endif; ?>
                        <?php echo htmlspecialchars($order['shipping_address']); ?><br>
                        <?php echo htmlspecialchars($order['shipping_city']); ?>, <?php echo htmlspecialchars($order['shipping_postal']); ?>
                        <?php if (!empty($order['shipping_country'])): ?><br><?php echo htmlspecialchars($order['shipping_country']); ?><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($order['notes'])): ?>
                <div class="shipping-info">
                    <h3>Order Notes</h3>
                    <p style="color:#64748b;line-height:1.6;"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
                </div>
            <?php endif; ?>

            <div class="action-buttons">
                <a href="<?php echo $root; ?>/public/shop.php" class="btn btn-primary">Continue Shopping</a>
                <a href="<?php echo $root; ?>/public/me-page.php" class="btn btn-secondary">View My Orders</a>
            </div>

            <div class="info-box" style="margin-top:2rem;">
                <p><strong>📦 What's Next?</strong> You'll receive order updates via email. Track your order status in "My Orders". Expected delivery: 3–7 business days.</p>
            </div>
        </div>
    </div>
</div>
<script src="<?php echo asset('design/toast.js');?>"></script>
<script src="<?php echo asset('design/item-count.js'); ?>"></script>
<script>
    if (window.history.replaceState) window.history.replaceState(null, null, window.location.href);
</script>
</body>
</html>