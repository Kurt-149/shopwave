<?php
require_once __DIR__ . '/./core/security.php';
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/core/init.php';

require_once __DIR__ . '/authentication/database.php';
require_once __DIR__ . '/core/config.php';

$root = BASE_URL;
$pdo = getPdo();
$page = 'home';

$isLoggedIn = isLoggedIn();
$userId = $isLoggedIn ? getCurrentUserId() : 0;

$featuredProducts = [];
$stats = ['total_products' => 0, 'total_customers' => 0, 'total_orders' => 0];
$footerCategories = [];

try {
    if ($pdo) {
        $featuredStmt = $pdo->query("
            SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.status = 'active' 
            ORDER BY p.created_at DESC 
            LIMIT 4
        ");
        $featuredProducts = $featuredStmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Featured products error: " . $e->getMessage());
}

try {
    if ($pdo) {
        $statsStmt = $pdo->query("
            SELECT 
                (SELECT COUNT(*) FROM products WHERE status = 'active') as total_products,
                (SELECT COUNT(*) FROM users WHERE role = 'user') as total_customers,
                (SELECT COUNT(*) FROM orders WHERE status = 'completed') as total_orders
        ");
        $stats = $statsStmt->fetch();
    }
} catch (PDOException $e) {
    error_log("Stats error: " . $e->getMessage());
}

try {
    if ($pdo) {
        $catStmt = $pdo->query("SELECT id, name, image_url FROM categories LIMIT 3");
        $footerCategories = $catStmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Categories error: " . $e->getMessage());
}

$unreadCount = 0;
$totalNotifications = 0;
$dropdownNotifs = [];
$notifIcons = ['order' => '📦', 'promo' => '🏷️', 'review' => '★', 'alert' => '⚠️'];

try {
    if ($isLoggedIn && $pdo) {
        $s = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $s->execute([$userId]);
        $unreadCount = (int)$s->fetchColumn();

        $s = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
        $s->execute([$userId]);
        $totalNotifications = (int)$s->fetchColumn();

        $s = $pdo->prepare("SELECT id, type, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
        $s->execute([$userId]);
        $dropdownNotifs = $s->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Notification error: " . $e->getMessage());
}

function timeAgo(string $dt): string
{
    if (empty($dt)) return '';
    $d = (new DateTime())->diff(new DateTime($dt));
    if ($d->y) return $d->y  . ' year'  . ($d->y  > 1 ? 's' : '') . ' ago';
    if ($d->m) return $d->m  . ' month' . ($d->m  > 1 ? 's' : '') . ' ago';
    if ($d->d) return $d->d  . ' day'   . ($d->d  > 1 ? 's' : '') . ' ago';
    if ($d->h) return $d->h  . ' hour'  . ($d->h  > 1 ? 's' : '') . ' ago';
    if ($d->i) return $d->i  . ' min'   . ($d->i  > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

// Simple, direct image URL builder — no helpers, no sanitizers
function categoryImageUrl(string $imageUrl): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $protocol = 'https://';
    }
    $host = $_SERVER['HTTP_HOST'];
    $path = ltrim($imageUrl, '/');
    return $protocol . $host . '/' . $path;
}
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
    <title>SHOPWAVE - Your Premier Online Shopping Destination</title>
    <meta name="description" content="Shop the latest fashion trends at SHOPWAVE. Stylish, comfortable, and affordable beachwear with free shipping on orders over ₱1,000!">
    <meta name="keywords" content="fashion, beachwear, clothing, online shopping, Philippines">
    <link rel="stylesheet" href="<?php echo asset('design/main-layout.css'); ?>">
</head>

<body>
    <div class="wrapper">
        <header class="header">
            <div class="header-main">
                <div class="header-inner">
                    <a href="<?php echo $root; ?>/index.php" class="logo">SHOPWAVE</a>
                    <div class="header-gap"></div>
                    <div class="actions">
                        <form action="<?php echo $root; ?>/public/shop.php" method="GET" class="search-wrapper desktop-search">
                            <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#6b7280">
                                <path d="M784-120 532-372q-30 24-69 38t-83 14q-109 0-184.5-75.5T120-580q0-109 75.5-184.5T380-840q109 0 184.5 75.5T640-580q0 44-14 83t-38 69l252 252-56 56ZM380-400q75 0 127.5-52.5T560-580q0-75-52.5-127.5T380-760q-75 0-127.5 52.5T200-580q0 75 52.5 127.5T380-400Z" />
                            </svg>
                            <input type="text" name="search" placeholder="Search products..." class="search" value="<?php echo htmlspecialchars($search ?? ''); ?>">
                            <button type="submit" class="search-btn">
                                <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffffff">
                                    <path d="M784-120 532-372q-30 24-69 38t-83 14q-109 0-184.5-75.5T120-580q0-109 75.5-184.5T380-840q109 0 184.5 75.5T640-580q0 44-14 83t-38 69l252 252-56 56ZM380-400q75 0 127.5-52.5T560-580q0-75-52.5-127.5T380-760q-75 0-127.5 52.5T200-580q0 75 52.5 127.5T380-400Z" />
                                </svg>
                            </button>
                        </form>
                        <button class="mobile-search-toggle icon" type="button" title="Search">
                            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                <path d="M784-120 532-372q-30 24-69 38t-83 14q-109 0-184.5-75.5T120-580q0-109 75.5-184.5T380-840q109 0 184.5 75.5T640-580q0 44-14 83t-38 69l252 252-56 56ZM380-400q75 0 127.5-52.5T560-580q0-75-52.5-127.5T380-760q-75 0-127.5 52.5T200-580q0 75 52.5 127.5T380-400Z" />
                            </svg>
                        </button>
                        <?php if (isLoggedIn()): ?>
                            <div class="homepage-icon">
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                    <a href="<?php echo $root; ?>/admin/dashboard.php" class="icon desktop-only" title="Admin">Admin</a>
                                <?php endif; ?>

                                <a href="<?php echo $root; ?>/public/cart.php" class="icon" title="Cart">
                                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                        <path d="M280-80q-33 0-56.5-23.5T200-160q0-33 23.5-56.5T280-240q33 0 56.5 23.5T360-160q0 33-23.5 56.5T280-80Zm400 0q-33 0-56.5-23.5T600-160q0-33 23.5-56.5T680-240q33 0 56.5 23.5T760-160q0 33-23.5 56.5T680-80ZM246-720l96 200h280l110-200H246Zm-38-80h590q23 0 35 20.5t1 41.5L692-482q-11 20-29.5 31T622-440H324l-44 80h480v80H280q-45 0-68-39.5t-2-78.5l54-98-144-304H40v-80h130l38 80Z" />
                                    </svg>
                                    <span class="badge cart-count-badge" style="display:none;">0</span>
                                </a>
                                <a href="<?php echo $root; ?>/public/me-page.php" class="icon" title="Profile">
                                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                        <path d="M234-276q51-39 114-61.5T480-360q69 0 132 22.5T726-276q35-41 54.5-93T800-480q0-133-93.5-226.5T480-800q-133 0-226.5 93.5T160-480q0 59 19.5 111t54.5 93Zm246-164q-59 0-99.5-40.5T340-580q0-59 40.5-99.5T480-720q59 0 99.5 40.5T620-580q0 59-40.5 99.5T480-440Zm0 360q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z" />
                                    </svg>
                                </a>
                                <div class="notif-wrap">
                                    <button class="icon" id="notifBellBtn" type="button" title="Notifications">
                                        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                            <path d="M160-200v-80h80v-280q0-83 50-147.5T420-792v-28q0-25 17.5-42.5T480-880q25 0 42.5 17.5T540-820v28q80 20 130 84.5T720-560v280h80v80H160Zm320-300Zm0 420q-33 0-56.5-23.5T400-160h160q0 33-23.5 56.5T480-80ZM320-280h320v-280q0-66-47-113t-113-47q-66 0-113 47t-47 113v280Z" />
                                        </svg>
                                        <?php if (isset($unreadCount) && $unreadCount > 0): ?>
                                            <span class="notif-red-dot"></span>
                                        <?php endif; ?>
                                    </button>

                                    <div class="notif-dropdown" id="notifDropdown">
                                        <div class="notif-dd-head">
                                            <span class="notif-dd-title">Notifications</span>
                                            <?php if (isset($unreadCount) && $unreadCount > 0): ?>
                                                <button class="notif-dd-mark" type="button" onclick="markAllRead()">Mark all read</button>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (empty($dropdownNotifs ?? [])): ?>
                                            <div class="notif-dd-empty">🔕 No notifications yet</div>
                                        <?php else: ?>
                                            <?php foreach ($dropdownNotifs as $n):
                                                $nType = htmlspecialchars($n['type'] ?? 'order');
                                                $nIcon = $notifIcons[$n['type'] ?? 'order'] ?? '📦';
                                                $nUnread = !(bool)$n['is_read'];
                                                $nAgo = timeAgo($n['created_at'] ?? '');
                                                $parts = explode(' | ', $n['message'] ?? '');
                                                $mainMsg = $parts[0];
                                                $noteMsg = $parts[1] ?? '';
                                            ?>
                                                <div class="notif-dd-item <?php echo $nUnread ? 'unread' : ''; ?>" onclick="handleNotificationClick(<?php echo (int)$n['id']; ?>, '<?php echo $nType; ?>', '<?php echo htmlspecialchars($n['message']); ?>')">
                                                    <div class="notif-dd-icon <?php echo $nType; ?>"><?php echo $nIcon; ?></div>
                                                    <div class="notif-dd-body">
                                                        <div class="notif-dd-msg"><?php echo htmlspecialchars($mainMsg); ?></div>
                                                        <?php if ($noteMsg): ?>
                                                            <button class="notif-reason-btn" onclick="event.stopPropagation(); showNotifReason('<?php echo htmlspecialchars($noteMsg); ?>')">View Reason</button>
                                                        <?php endif; ?>
                                                        <div class="notif-dd-time"><?php echo $nAgo; ?></div>
                                                    </div>
                                                    <div class="notif-dd-dot <?php echo $nUnread ? '' : 'read'; ?>"></div>
                                                </div>
                                            <?php endforeach; ?>

                                            <?php if (isset($totalNotifications) && $totalNotifications >= 5): ?>
                                                <a href="<?php echo $root; ?>/public/me-page.php#notifications" class="notif-view-all">
                                                    View all Notifications →
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="action-buttons">
                                <a href="<?php echo $root; ?>/authentication/login-page.php" class="login">Login</a>
                                <a href="<?php echo $root; ?>/authentication/sign-up.php" class="signup">Sign Up</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>
        <div class="mobile-search-bar" id="mobileSearchBar" style="display:none;">
            <form action="<?php echo $root; ?>/public/shop.php" method="GET" class="search-wrapper">
                <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#6b7280">
                    <path d="M784-120 532-372q-30 24-69 38t-83 14q-109 0-184.5-75.5T120-580q0-109 75.5-184.5T380-840q109 0 184.5 75.5T640-580q0 44-14 83t-38 69l252 252-56 56ZM380-400q75 0 127.5-52.5T560-580q0-75-52.5-127.5T380-760q-75 0-127.5 52.5T200-580q0 75 52.5 127.5T380-400Z" />
                </svg>
                <input type="text" name="search" placeholder="Search products..." class="search">
                <button type="submit" class="search-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffffff">
                        <path d="M784-120 532-372q-30 24-69 38t-83 14q-109 0-184.5-75.5T120-580q0-109 75.5-184.5T380-840q109 0 184.5 75.5T640-580q0 44-14 83t-38 69l252 252-56 56ZM380-400q75 0 127.5-52.5T560-580q0-75-52.5-127.5T380-760q-75 0-127.5 52.5T200-580q0 75 52.5 127.5T380-400Z" />
                    </svg>
                </button>
            </form>
        </div>

        <div class="page-body">
            <div class="hero-modern">
                <div class="hero-content">
                    <span class="hero-badge">
                        <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#92400e">
                            <path d="m489-460 91-55 91 55-24-104 80-69-105-9-42-98-42 98-105 9 80 69-24 104Zm19 260h224q-7 26-24 42t-44 20L228-85q-33 5-59.5-15.5T138-154L85-591q-4-33 16-59t53-30l46-6v80l-36 5 54 437 290-36Zm-148-80q-33 0-56.5-23.5T280-360v-440q0-33 23.5-56.5T360-880h440q33 0 56.5 23.5T880-800v440q0 33-23.5 56.5T800-280H360Z" />
                        </svg>
                        New Arrivals
                    </span>
                    <h1>Discover Your Style</h1>
                    <p>Stylish, comfortable, and affordable beachwear that enhances your confidence. Free shipping on orders over ₱1,000!</p>
                    <div class="hero-buttons">
                        <a href="<?php echo $root; ?>/public/shop.php" class="btn-primary">
                            <span>Explore Collection</span>
                        </a>
                        <a href="#featured" class="btn-secondary">View Featured</a>
                    </div>
                    <div class="hero-stats">
                        <div class="stat-item">
                            <strong><?php echo number_format($stats['total_products']); ?>+</strong>
                            <span>Products</span>
                        </div>
                        <div class="stat-item">
                            <strong><?php echo number_format($stats['total_customers']); ?>+</strong>
                            <span>Happy Customers</span>
                        </div>
                        <div class="stat-item">
                            <strong><?php echo number_format($stats['total_orders']); ?>+</strong>
                            <span>Orders Delivered</span>
                        </div>
                    </div>
                </div>
                <div class="hero-visual">
                    <div class="hero-collage">
                        <div class="collage-main">
                            <img src="./images/hero-images/women-image.jpg" alt="Women's beachwear collection" loading="eager">
                            <div class="collage-label">Women</div>
                        </div>
                        <div class="collage-side">
                            <div class="collage-top">
                                <img src="./images/hero-images/men-image.jpg" alt="Men's beachwear collection" loading="lazy">
                                <div class="collage-label">Men</div>
                            </div>
                            <div class="collage-bottom">
                                <img src="./images/hero-images/kid-image.jpg" alt="Kids beachwear collection" loading="lazy">
                                <div class="collage-label">Kids</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="trust-badges">
                <div class="trust-item">
                    <svg xmlns="http://www.w3.org/2000/svg" height="30px" viewBox="0 -960 960 960" width="30px" fill="#10b981">
                        <path d="m424-296 282-282-56-56-226 226-114-114-56 56 170 170Zm56 216q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z" />
                    </svg>
                    <div>
                        <strong>Secure Payment</strong>
                        <p>100% protected</p>
                    </div>
                </div>
                <div class="trust-item">
                    <svg xmlns="http://www.w3.org/2000/svg" height="30px" viewBox="0 -960 960 960" width="30px" fill="#10b981">
                        <path d="M240-160q-50 0-85-35t-35-85H40v-440q0-33 23.5-56.5T120-800h560v160h120l120 160v200h-80q0 50-35 85t-85 35q-50 0-85-35t-35-85H360q0 50-35 85t-85 35Zm0-80q17 0 28.5-11.5T280-280q0-17-11.5-28.5T240-320q-17 0-28.5 11.5T200-280q0 17 11.5 28.5T240-240ZM120-360h32q17-18 39-29t49-11q27 0 49 11t39 29h272v-360H120v360Zm600 120q17 0 28.5-11.5T760-280q0-17-11.5-28.5T720-320q-17 0-28.5 11.5T680-280q0 17 11.5 28.5T720-240Zm-40-200h170l-90-120h-80v120ZM360-540Z" />
                    </svg>
                    <div>
                        <strong>Fast Delivery</strong>
                        <p>2-5 business days</p>
                    </div>
                </div>
                <div class="trust-item">
                    <svg xmlns="http://www.w3.org/2000/svg" height="30px" viewBox="0 -960 960 960" width="30px" fill="#10b981">
                        <path d="M280-240q-17 0-28.5-11.5T240-280v-80h-40q-33 0-56.5-23.5T120-440v-320q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v320q0 33-23.5 56.5T760-360h-40v80q0 17-11.5 28.5T680-240H280Zm0-80h320v-80h160q0-33 23.5-56.5T840-480v-200q-33 0-56.5-23.5T760-760H200q0 33-23.5 56.5T120-680v200q33 0 56.5 23.5T200-400h80v80Zm200-160q50 0 85-35t35-85q0-50-35-85t-85-35q-50 0-85 35t-35 85q0 50 35 85t85 35Zm0-80q-17 0-28.5-11.5T440-600q0-17 11.5-28.5T480-640q17 0 28.5 11.5T520-600q0 17-11.5 28.5T480-560Z" />
                    </svg>
                    <div>
                        <strong>Easy Returns</strong>
                        <p>30-day guarantee</p>
                    </div>
                </div>
                <div class="trust-item">
                    <svg xmlns="http://www.w3.org/2000/svg" height="30px" viewBox="0 -960 960 960" width="30px" fill="#10b981">
                        <path d="M440-120v-240h80v80h320v80H520v80h-80Zm-320-80v-80h240v80H120Zm160-160v-80H120v-80h160v-80h80v240h-80Zm160-80v-80h400v80H440Zm160-160v-240h80v80h160v80H680v80h-80Zm-480-80v-80h400v80H120Z" />
                    </svg>
                    <div>
                        <strong>24/7 Support</strong>
                        <p>Always here for you</p>
                    </div>
                </div>
            </div>

            <?php if (!empty($featuredProducts)): ?>
                <section class="section-featured" id="featured">
                    <div class="section-header">
                        <div>
                            <h2>
                                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                    <path d="m489-460 91-55 91 55-24-104 80-69-105-9-42-98-42 98-105 9 80 69-24 104Zm19 260h224q-7 26-24 42t-44 20L228-85q-33 5-59.5-15.5T138-154L85-591q-4-33 16-59t53-30l46-6v80l-36 5 54 437 290-36Zm-148-80q-33 0-56.5-23.5T280-360v-440q0-33 23.5-56.5T360-880h440q33 0 56.5 23.5T880-800v440q0 33-23.5 56.5T800-280H360Z" />
                                </svg>
                                Featured Products
                            </h2>
                            <p>Check out our latest and most popular items</p>
                        </div>
                        <a href="<?php echo $root; ?>/public/shop.php" class="view-all">
                            View All
                            <svg xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px" fill="currentColor">
                                <path d="m321-80-71-71 329-329-329-329 71-71 400 400L321-80Z" />
                            </svg>
                        </a>
                    </div>
                    <div class="products-grid">
                        <?php foreach ($featuredProducts as $product): ?>
                            <a href="<?php echo $root; ?>/public/product-details.php?id=<?php echo $product['id']; ?>" class="product-card">
                                <?php if (!empty($product['discount_price'])): ?>
                                    <div class="product-badge">SALE</div>
                                <?php endif; ?>
                                <div class="product-image">
                                    <?php if (!empty($product['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    <?php else: ?>
                                        <div class="no-image">No Image</div>
                                    <?php endif; ?>
                                </div>
                                <div class="product-info">
                                    <span class="product-category"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></span>
                                    <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                                    <div class="product-price">
                                        <span class="price-current">₱<?php echo number_format($product['price'], 2); ?></span>
                                        <?php if (!empty($product['discount_price'])): ?>
                                            <span class="price-original">₱<?php echo number_format($product['discount_price'], 2); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="section-categories">
                <div class="section-header">
                    <div>
                        <h2>
                            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                <path d="M856-390 570-104q-12 12-27 18t-30 6q-15 0-30-6t-27-18L103-457q-11-11-17-25.5T80-513v-287q0-33 23.5-56.5T160-880h287q16 0 31 6.5t26 17.5l352 353q12 12 17.5 27t5.5 30q0 15-5.5 29.5T856-390ZM513-160l286-286-353-354H160v286l353 354ZM260-640q25 0 42.5-17.5T320-700q0-25-17.5-42.5T260-760q-25 0-42.5 17.5T200-700q0 25 17.5 42.5T260-640Z" />
                            </svg>
                            Shop by Category
                        </h2>
                        <p>Find exactly what you're looking for</p>
                    </div>
                </div>
                <div class="categories-grid">
                    <?php foreach ($footerCategories as $category): ?>
                        <a href="<?php echo $root; ?>/public/shop.php?category=<?php echo $category['id']; ?>" class="category-card">
                            <div class="category-image" style="background-image: url('<?php echo $root . '/' . ltrim((string)$category['image_url'], '/'); ?>');"></div>
                            <div class="category-overlay">
                                <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                                <p>Shop Now →</p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

        </div>

        <footer class="footer">
            <div class="footer-inner">
                <div class="footer-content">
                    <div class="footer-col">
                        <div class="texts">
                            <h3>SHOPWAVE</h3>
                            <p>Stylish, comfortable, and affordable beachwear that enhances confidence and celebrates a coastal lifestyle.</p>
                        </div>
                        <div class="social-links">
                            <a href="https://youtu.be/Aq5WXmQQooo?si=JwGyzmt6vQMS1dye" title="Facebook" aria-label="Facebook">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                                </svg>
                            </a>
                            <a href="https://youtu.be/Aq5WXmQQooo?si=JwGyzmt6vQMS1dye" title="Instagram" aria-label="Instagram">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069z" />
                                </svg>
                            </a>
                            <a href="https://youtu.be/Aq5WXmQQooo?si=JwGyzmt6vQMS1dye" title="Twitter" aria-label="Twitter">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z" />
                                </svg>
                            </a>
                        </div>
                    </div>
                    <div class="footer-col">
                        <h4>Shop</h4>
                        <a href="<?php echo $root; ?>/public/shop.php">All Products</a>
                        <?php if (!empty($footerCategories)): ?>
                            <?php foreach ($footerCategories as $cat): ?>
                                <a href="<?php echo $root; ?>/public/shop.php?category=<?php echo $cat['id']; ?>">
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="footer-col">
                        <button class="footer-accordion-btn" aria-expanded="false" aria-controls="accordion-cs">
                            <h4>Customer Service</h4>
                            <svg class="accordion-arrow" xmlns="http://www.w3.org/2000/svg" height="16px" viewBox="0 -960 960 960" width="16px" fill="currentColor">
                                <path d="M480-344 240-584l56-56 184 184 184-184 56 56-240 240Z" />
                            </svg>
                        </button>
                        <div class="footer-accordion-panel" id="accordion-cs">
                            <div class="footer-accordion-item">
                                <button class="footer-sub-btn" aria-expanded="false" aria-controls="cs-contact">
                                    <span>Contact Us</span>
                                    <svg class="accordion-arrow" xmlns="http://www.w3.org/2000/svg" height="14px" viewBox="0 -960 960 960" width="14px" fill="currentColor">
                                        <path d="M480-344 240-584l56-56 184 184 184-184 56 56-240 240Z" />
                                    </svg>
                                </button>
                                <div class="footer-sub-panel" id="cs-contact">
                                    <p>📧 support@shopwave.ph</p>
                                    <p>📞 +63 912 345 6789</p>
                                    <p>🕐 Mon–Sat, 9AM–6PM PHT</p>
                                    <p>📍 Metro Manila, Philippines</p>
                                </div>
                            </div>
                            <div class="footer-accordion-item">
                                <button class="footer-sub-btn" aria-expanded="false" aria-controls="cs-shipping">
                                    <span>Shipping &amp; Returns</span>
                                    <svg class="accordion-arrow" xmlns="http://www.w3.org/2000/svg" height="14px" viewBox="0 -960 960 960" width="14px" fill="currentColor">
                                        <path d="M480-344 240-584l56-56 184 184 184-184 56 56-240 240Z" />
                                    </svg>
                                </button>
                                <div class="footer-sub-panel" id="cs-shipping">
                                    <p>🚚 Free shipping on orders ₱1,000+</p>
                                    <p>📦 Standard delivery: 3–5 business days</p>
                                    <p>⚡ Express delivery: 1–2 business days</p>
                                    <p>↩️ 7-day hassle-free returns accepted</p>
                                    <p>💳 Refunds processed in 3–5 business days</p>
                                </div>
                            </div>
                            <div class="footer-accordion-item">
                                <button class="footer-sub-btn" aria-expanded="false" aria-controls="cs-faq">
                                    <span>FAQ</span>
                                    <svg class="accordion-arrow" xmlns="http://www.w3.org/2000/svg" height="14px" viewBox="0 -960 960 960" width="14px" fill="currentColor">
                                        <path d="M480-344 240-584l56-56 184 184 184-184 56 56-240 240Z" />
                                    </svg>
                                </button>
                                <div class="footer-sub-panel" id="cs-faq">
                                    <p><strong>How do I track my order?</strong><br>Use your order ID in the My Orders page.</p>
                                    <p><strong>Can I change my order?</strong><br>Changes are allowed within 1 hour of placing the order.</p>
                                    <p><strong>What payment methods are accepted?</strong><br>GCash, Maya, credit/debit cards, and COD.</p>
                                    <p><strong>Is my data safe?</strong><br>Yes — we use encrypted, secure storage for all accounts.</p>
                                </div>
                            </div>
                            <div class="footer-accordion-item">
                                <button class="footer-sub-btn" aria-expanded="false" aria-controls="cs-size">
                                    <span>Size Guide</span>
                                    <svg class="accordion-arrow" xmlns="http://www.w3.org/2000/svg" height="14px" viewBox="0 -960 960 960" width="14px" fill="currentColor">
                                        <path d="M480-344 240-584l56-56 184 184 184-184 56 56-240 240Z" />
                                    </svg>
                                </button>
                                <div class="footer-sub-panel" id="cs-size">
                                    <p><strong>Top / Shirts</strong><br>XS (32–34") · S (34–36") · M (36–38") · L (38–40") · XL (40–42") · 2XL (42–44")</p>
                                    <p><strong>Bottom / Shorts</strong><br>XS (26–28") · S (28–30") · M (30–32") · L (32–34") · XL (34–36") · 2XL (36–38")</p>
                                    <p>💡 Tip: When in doubt, size up for beachwear comfort.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="footer-col">
                        <button class="footer-accordion-btn" aria-expanded="false" aria-controls="accordion-about">
                            <h4>About</h4>
                            <svg class="accordion-arrow" xmlns="http://www.w3.org/2000/svg" height="16px" viewBox="0 -960 960 960" width="16px" fill="currentColor">
                                <path d="M480-344 240-584l56-56 184 184 184-184 56 56-240 240Z" />
                            </svg>
                        </button>
                        <div class="footer-accordion-panel" id="accordion-about">
                            <div class="footer-accordion-item">
                                <button class="footer-sub-btn" aria-expanded="false" aria-controls="about-story">
                                    <span>Our Story</span>
                                    <svg class="accordion-arrow" xmlns="http://www.w3.org/2000/svg" height="14px" viewBox="0 -960 960 960" width="14px" fill="currentColor">
                                        <path d="M480-344 240-584l56-56 184 184 184-184 56 56-240 240Z" />
                                    </svg>
                                </button>
                                <div class="footer-sub-panel" id="about-story">
                                    <p>SHOPWAVE was founded in 2025 by a group of beach lovers from Bulacan who believed coastal fashion should be affordable for everyone.</p>
                                    <p>🌊 We source quality fabrics locally and partner with Filipino artisans to create designs that celebrate our coastal culture and identity.</p>
                                </div>
                            </div>
                            <div class="footer-accordion-item">
                                <button class="footer-sub-btn" aria-expanded="false" aria-controls="about-privacy">
                                    <span>Privacy Policy</span>
                                    <svg class="accordion-arrow" xmlns="http://www.w3.org/2000/svg" height="14px" viewBox="0 -960 960 960" width="14px" fill="currentColor">
                                        <path d="M480-344 240-584l56-56 184 184 184-184 56 56-240 240Z" />
                                    </svg>
                                </button>
                                <div class="footer-sub-panel" id="about-privacy">
                                    <p>🔒 We collect only the information needed to process your orders and improve your experience.</p>
                                    <p>We do <strong>not</strong> sell or share your personal data with third parties.</p>
                                    <p>You can request deletion of your account and data at any time by contacting support.</p>
                                    <p>Compliant with the Philippine Data Privacy Act of 2012 (RA 10173).</p>
                                </div>
                            </div>
                            <div class="footer-accordion-item">
                                <button class="footer-sub-btn" aria-expanded="false" aria-controls="about-terms">
                                    <span>Terms &amp; Conditions</span>
                                    <svg class="accordion-arrow" xmlns="http://www.w3.org/2000/svg" height="14px" viewBox="0 -960 960 960" width="14px" fill="currentColor">
                                        <path d="M480-344 240-584l56-56 184 184 184-184 56 56-240 240Z" />
                                    </svg>
                                </button>
                                <div class="footer-sub-panel" id="about-terms">
                                    <p>By using SHOPWAVE you agree to our terms of service.</p>
                                    <p>📌 Orders are subject to product availability and stock confirmation.</p>
                                    <p>📌 SHOPWAVE reserves the right to cancel fraudulent or suspicious orders.</p>
                                    <p>📌 Prices are in Philippine Peso (₱) and may change without prior notice.</p>
                                    <p>📌 All disputes are subject to Philippine jurisdiction.</p>
                                </div>
                            </div>
                            <div class="footer-accordion-item">
                                <button class="footer-sub-btn" aria-expanded="false" aria-controls="about-careers">
                                    <span>Careers</span>
                                    <svg class="accordion-arrow" xmlns="http://www.w3.org/2000/svg" height="14px" viewBox="0 -960 960 960" width="14px" fill="currentColor">
                                        <path d="M480-344 240-584l56-56 184 184 184-184 56 56-240 240Z" />
                                    </svg>
                                </button>
                                <div class="footer-sub-panel" id="about-careers">
                                    <p>🧑‍💻 <strong>Web Developer</strong> — Full-time · Remote · PHP/MySQL</p>
                                    <p>🎨 <strong>Graphic Designer</strong> — Part-time · Remote · Fashion focus</p>
                                    <p>📦 <strong>Warehouse Staff</strong> — Full-time · On-site · Metro Manila</p>
                                    <p>📩 Send your resume to <em>careers@shopwave.ph</em></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="footer-bottom">
                    <p>&copy; <?php echo date('Y'); ?> SHOPWAVE. All rights reserved. Made with Love in the Philippines 🇵🇭</p>
                </div>
            </div>
        </footer>

    </div>
    <script>
        document.querySelectorAll('.footer-accordion-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const expanded = btn.getAttribute('aria-expanded') === 'true';
                btn.setAttribute('aria-expanded', !expanded);
                const panel = document.getElementById(btn.getAttribute('aria-controls'));
                panel.classList.toggle('open', !expanded);
            });
        });
        document.querySelectorAll('.footer-sub-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const expanded = btn.getAttribute('aria-expanded') === 'true';
                btn.closest('.footer-accordion-panel').querySelectorAll('.footer-sub-btn').forEach(b => {
                    if (b !== btn) {
                        b.setAttribute('aria-expanded', 'false');
                        document.getElementById(b.getAttribute('aria-controls')).classList.remove('open');
                    }
                });
                btn.setAttribute('aria-expanded', !expanded);
                const panel = document.getElementById(btn.getAttribute('aria-controls'));
                panel.classList.toggle('open', !expanded);
            });
        });
    </script>
    <script src="<?php echo asset('design/notifications.js'); ?>"></script>
    <script src="<?php echo asset('design/toast.js'); ?>"></script>
    <script src="<?php echo asset('design/move.js'); ?>"></script>
    <script src="<?php echo asset('design/item-count.js'); ?>"></script>
    <script>
        const mobileSearchToggle = document.querySelector('.mobile-search-toggle');
        const mobileSearchBar = document.getElementById('mobileSearchBar');

        if (mobileSearchToggle && mobileSearchBar) {
            mobileSearchToggle.addEventListener('click', () => {
                const isHidden = mobileSearchBar.style.display === 'none' || !mobileSearchBar.style.display;
                mobileSearchBar.style.display = isHidden ? 'block' : 'none';
                if (isHidden) {
                    mobileSearchBar.style.animation = 'slideDown 0.3s ease';
                    const input = mobileSearchBar.querySelector('input');
                    if (input) input.focus();
                }
            });
        }
    </script>
</body>

</html>