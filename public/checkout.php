<?php
require_once dirname(__DIR__) . '/core/init.php';
require_once dirname(__DIR__) . '/core/session-handler.php';
require_once dirname(__DIR__) . '/authentication/database.php';
require_once dirname(__DIR__) . '/core/config.php';

$root = BASE_URL;

if (!isLoggedIn()) {
    header('Location: ' . $root . '/authentication/login-page.php?error=' . urlencode('Please login to continue'));
    exit;
}

require_once dirname(__DIR__) . '/core/security.php';
$csrfToken = generateCsrfToken();
require_once dirname(__DIR__) . '/backend/checkout-process.php';
// Remove the JSON header that checkout-process sets
header('Content-Type: text/html; charset=UTF-8');
$page = 'checkout';
try {
    if (isset($_SESSION['user_id'])) {
        $s = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $s->execute([$_SESSION['user_id']]);
        $unreadCount = (int)$s->fetchColumn();

        $s = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
        $s->execute([$_SESSION['user_id']]);
        $totalNotifications = (int)$s->fetchColumn();

        $s = $pdo->prepare("SELECT id, type, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
        $s->execute([$_SESSION['user_id']]);
        $dropdownNotifs = $s->fetchAll(PDO::FETCH_ASSOC);

        $notifIcons = ['order' => '📦', 'promo' => '🏷️', 'review' => '★', 'alert' => '⚠️'];
    } else {
        $unreadCount = 0;
        $totalNotifications = 0;
        $dropdownNotifs = [];
        $notifIcons = [];
    }
} catch (PDOException $e) {
    $unreadCount = 0;
    $totalNotifications = 0;
    $dropdownNotifs = [];
    $notifIcons = [];
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
    <title>Checkout - SHOPWAVE</title>
    <link rel="stylesheet" href="<?php echo asset('design/main-layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('design/web-design/shop-breadcrumbs.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('design/web-design/checkout.css'); ?>">
</head>

<body>
    <div class="wrapper">
        <nav class="mobile-product-header">
            <button type="button" class="mph-back" aria-label="Go back" onclick="history.back()">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                    <path d="M400-80 0-480l400-400 71 71-329 329 329 329-71 71Z" />
                </svg>
            </button>
            <span class="mph-title">Checkout</span>
            <div class="mph-right">
                <a href="<?php echo $root; ?>/public/cart.php" class="mph-cart">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                        <path d="M280-80q-33 0-56.5-23.5T200-160q0-33 23.5-56.5T280-240q33 0 56.5 23.5T360-160q0 33-23.5 56.5T280-80Zm400 0q-33 0-56.5-23.5T600-160q0-33 23.5-56.5T680-240q33 0 56.5 23.5T760-160q0 33-23.5 56.5T680-80ZM246-720l96 200h280l110-200H246Zm-38-80h590q23 0 35 20.5t1 41.5L692-482q-11 20-29.5 31T622-440H324l-44 80h480v80H280q-45 0-68-39.5t-2-78.5l54-98-144-304H40v-80h130l38 80Zm134 280h280-280Z" />
                    </svg>
                </a>
            </div>
        </nav>
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
                            <a href="<?php echo $root; ?>/authentication/login-page.php" class="login">Login</a>
                            <a href="<?php echo $root; ?>/authentication/sign-up.php" class="signup">Sign Up</a>
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

            <div class="breadcrumbs">
                <a href="<?php echo $root; ?>/index.php">Home</a>
                <span class="separator">›</span>
                <?php if ($source === 'cart'): ?>
                    <a href="<?php echo $root; ?>/public/cart.php">Cart</a>
                    <span class="separator">›</span>
                <?php endif; ?>
                <span class="current">Checkout</span>
            </div>

            <div class="checkout-wrapper">
                <div class="checkout-content">
                    <div class="checkout-form-section">
                        <form id="checkoutForm" class="checkout-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                            <div class="form-section">
                                <h2>
                                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                        <path d="M155-195q-35-35-35-85H40v-440q0-33 23.5-56.5T120-800h560v160h120l120 160v200h-80q0 50-35 85t-85 35q-50 0-85-35t-35-85H360q0 50-35 85t-85 35q-50 0-85-35Zm113.5-56.5Q280-263 280-280t-11.5-28.5Q257-320 240-320t-28.5 11.5Q200-297 200-280t11.5 28.5Q223-240 240-240t28.5-11.5ZM120-360h32q17-18 39-29t49-11q27 0 49 11t39 29h272v-360H120v360Zm628.5 108.5Q760-263 760-280t-11.5-28.5Q737-320 720-320t-28.5 11.5Q680-297 680-280t11.5 28.5Q703-240 720-240t28.5-11.5ZM680-440h170l-90-120h-80v120ZM360-540Z" />
                                    </svg>
                                    Shipping Information
                                </h2>

                                <div class="form-group">
                                    <label for="shipping_name">Full Name <span class="required">*</span></label>
                                    <input type="text" id="shipping_name" name="shipping_name"
                                        value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>"
                                        placeholder="Enter your full name" required maxlength="100">
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="shipping_email">Email <span class="required">*</span></label>
                                        <input type="email" id="shipping_email" name="shipping_email"
                                            value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                            placeholder="you@email.com" required maxlength="100">
                                    </div>
                                    <div class="form-group">
                                        <label for="shipping_phone">Phone Number <span class="required">*</span></label>
                                        <input type="tel" id="shipping_phone" name="shipping_phone"
                                            placeholder="+63 XXX XXX XXXX" required maxlength="20"
                                            pattern="^[\d\s\+\-\(\)]+$">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="shipping_address">Street Address <span class="required">*</span></label>
                                    <input type="text" id="shipping_address" name="shipping_address"
                                        placeholder="House No., Street Name, Barangay" required maxlength="255">
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="shipping_city">City <span class="required">*</span></label>
                                        <input type="text" id="shipping_city" name="shipping_city"
                                            placeholder="e.g. Manila" required maxlength="100">
                                    </div>
                                    <div class="form-group">
                                        <label for="shipping_postal">Postal Code <span class="required">*</span></label>
                                        <input type="text" id="shipping_postal" name="shipping_postal"
                                            placeholder="e.g. 1000" required maxlength="20">
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h2>
                                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                        <path d="M560-440q-50 0-85-35t-35-85q0-50 35-85t85-35q50 0 85 35t35 85q0 50-35 85t-85 35ZM280-320q-33 0-56.5-23.5T200-400v-320q0-33 23.5-56.5T280-800h560q33 0 56.5 23.5T920-720v320q0 33-23.5 56.5T840-320H280Zm80-80h400q0-33 23.5-56.5T840-480v-160q-33 0-56.5-23.5T760-720H360q0 33-23.5 56.5T280-640v160q33 0 56.5 23.5T360-400Zm440 240H120q-33 0-56.5-23.5T40-240v-440h80v440h680v80ZM280-400v-320 320Z" />
                                    </svg>
                                    Payment Method
                                </h2>

                                <div class="payment-methods">
                                    <label class="payment-option">
                                        <input type="radio" name="payment_method" value="cod">
                                        <div class="payment-card">
                                            <div class="payment-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                                    <path d="M600-320h120q17 0 28.5-11.5T760-360v-240q0-17-11.5-28.5T720-640H600q-17 0-28.5 11.5T560-600v240q0 17 11.5 28.5T600-320Zm40-80v-160h40v160h-40Zm-280 80h120q17 0 28.5-11.5T520-360v-240q0-17-11.5-28.5T480-640H360q-17 0-28.5 11.5T320-600v240q0 17 11.5 28.5T360-320Zm40-80v-160h40v160h-40Zm-200 80h80v-320h-80v320ZM80-160v-640h800v640H80Zm80-80h640v-480H160v480Z" />
                                                </svg>
                                            </div>
                                            <div class="payment-details">
                                                <h3>Cash on Delivery</h3>
                                                <p>Pay when you receive your order</p>
                                            </div>
                                            <div class="payment-check">
                                                <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor">
                                                    <path d="m424-296 282-282-56-56-226 226-114-114-56 56 170 170Zm56 216q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z" />
                                                </svg>
                                            </div>
                                        </div>
                                    </label>

                                    <label class="payment-option">
                                        <input type="radio" name="payment_method" value="gcash">
                                        <div class="payment-card">
                                            <div class="payment-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                                    <path d="M280-40q-33 0-56.5-23.5T200-120v-720q0-33 23.5-56.5T280-920h400q33 0 56.5 23.5T760-840v124q18 7 29 22t11 34v80q0 19-11 34t-29 22v404q0 33-23.5 56.5T680-40H280Zm0-80h400v-720H280v720Zm228.5-611.5Q520-743 520-760t-11.5-28.5Q497-800 480-800t-28.5 11.5Q440-777 440-760t11.5 28.5Q463-720 480-720t28.5-11.5Z" />
                                                </svg>
                                            </div>
                                            <div class="payment-details">
                                                <h3>GCash</h3>
                                                <p>Pay via GCash mobile wallet</p>
                                            </div>
                                            <div class="payment-check">
                                                <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor">
                                                    <path d="m424-296 282-282-56-56-226 226-114-114-56 56 170 170Zm56 216q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z" />
                                                </svg>
                                            </div>
                                        </div>
                                    </label>

                                    <label class="payment-option">
                                        <input type="radio" name="payment_method" value="bank">
                                        <div class="payment-card">
                                            <div class="payment-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                                    <path d="M200-280v-280h80v280h-80Zm240 0v-280h80v280h-80ZM80-120v-80h800v80H80Zm600-160v-280h80v280h-80ZM80-640v-80l400-200 400 200v80H80Zm178-80h444L480-830 258-720Z" />
                                                </svg>
                                            </div>
                                            <div class="payment-details">
                                                <h3>Bank Transfer</h3>
                                                <p>Transfer to our bank account</p>
                                            </div>
                                            <div class="payment-check">
                                                <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor">
                                                    <path d="m424-296 282-282-56-56-226 226-114-114-56 56 170 170Zm56 216q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z" />
                                                </svg>
                                            </div>
                                        </div>
                                    </label>

                                    <label class="payment-option">
                                        <input type="radio" name="payment_method" value="card">
                                        <div class="payment-card">
                                            <div class="payment-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                                    <path d="M880-720v480q0 33-23.5 56.5T800-160H160q-33 0-56.5-23.5T80-240v-480q0-33 23.5-56.5T160-800h640q33 0 56.5 23.5T880-720Zm-720 80h640v-80H160v80Zm0 160v240h640v-240H160Z" />
                                                </svg>
                                            </div>
                                            <div class="payment-details">
                                                <h3>Credit/Debit Card</h3>
                                                <p>Pay with Visa, Mastercard, etc.</p>
                                            </div>
                                            <div class="payment-check">
                                                <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor">
                                                    <path d="m424-296 282-282-56-56-226 226-114-114-56 56 170 170Zm56 216q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z" />
                                                </svg>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div class="form-section">
                                <h2>
                                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                        <path d="M160-160v-516L82-846l72-34 94 202h464l94-202 72 34-78 170v516H160Zm240-280h160q17 0 28.5-11.5T600-480q0-17-11.5-28.5T560-520H400q-17 0-28.5 11.5T360-480q0 17 11.5 28.5T400-440ZM240-240h480v-358H240v358Z" />
                                    </svg>
                                    Order Notes <span class="optional">(Optional)</span>
                                </h2>
                                <div class="form-group">
                                    <label for="notes">Special instructions for your order</label>
                                    <textarea id="notes" name="notes" rows="4"
                                        placeholder="e.g. Please deliver after 5 PM, leave at gate, etc." maxlength="500"></textarea>
                                </div>
                            </div>

                            <button type="submit" class="btn-place-order" id="placeOrderBtn">
                                <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor">
                                    <path d="M440-183v-274L200-596v274l240 139Zm80 0 240-139v-274L520-457v274Zm-40 92L160-252q-19-11-29.5-29T120-321v-318q0-22 10.5-40t29.5-29l320-184q19-11 40-11t40 11l320 184q19 11 29.5 29t10.5 40v318q0 22-10.5 40T880-252L560-91q-19 11-40 11t-40-11Z" />
                                </svg>
                                Place Order
                            </button>
                        </form>
                    </div>

                    <div class="order-summary">
                        <h2>Order Summary</h2>
                        <div class="summary-items">
                            <?php foreach ($checkoutItems as $item): ?>
                                <?php
                                $hasDiscount = !empty($item['discount_price']) && floatval($item['discount_price']) < floatval($item['original_price']);
                                $discountPct = $hasDiscount
                                    ? round((($item['original_price'] - $item['discount_price']) / $item['original_price']) * 100)
                                    : 0;
                                ?>
                                <div class="summary-item">
                                    <div class="summary-item-image">
                                        <?php if (!empty($item['image_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>"
                                                alt="<?php echo htmlspecialchars($item['name']); ?>">
                                        <?php else: ?>
                                            <div class="no-image">
                                                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#9ca3af">
                                                    <path d="M200-640v440h560v-440H640v320l-160-80-160 80v-320H200Zm0 520q-33 0-56.5-23.5T120-200v-499q0-14 4.5-27t13.5-24l50-61q11-14 27.5-21.5T250-840h460q18 0 34.5 7.5T772-811l50 61q9 11 13.5 24t4.5 27v499q0 33-23.5 56.5T760-120H200Zm16-600h528l-34-40H250l-34 40Zm184 80v190l80-40 80 40v-190H400Zm-200 0h560-560Z" />
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="summary-item-details">
                                        <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                        <?php if (!empty($item['brand'])): ?>
                                            <p class="item-brand">Brand: <?php echo htmlspecialchars($item['brand']); ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($item['selected_color']) || !empty($item['selected_size'])): ?>
                                            <p class="item-variants-text">Color & Size:
                                                <?php
                                                $parts = [];
                                                if (!empty($item['selected_color'])) $parts[] = $item['selected_color'];
                                                if (!empty($item['selected_size']))  $parts[] = $item['selected_size'];
                                                echo htmlspecialchars(implode(' / ', $parts));
                                                ?>
                                            </p>
                                        <?php endif; ?>
                                        <div class="summary-item-pricing">
                                            <?php if ($hasDiscount): ?>
                                                <span class="summary-price-discounted">P<?php echo number_format($item['discount_price'], 2); ?></span>
                                                <span class="summary-price-original">P<?php echo number_format($item['original_price'], 2); ?></span>
                                                <span class="summary-discount-badge">-<?php echo $discountPct; ?>%</span>
                                            <?php else: ?>
                                                <span class="summary-price-discounted">P<?php echo number_format($item['price'], 2); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="item-qty">Quantity: <?php echo (int)$item['quantity']; ?></p>
                                    </div>
                                    <div class="summary-item-price">
                                        P<?php echo number_format($item['subtotal'], 2); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="summary-divider"></div>

                        <div class="summary-totals">
                            <div class="summary-row">
                                <span>Subtotal (<?php echo array_sum(array_column($checkoutItems, 'quantity')); ?> items)</span>
                                <span>P<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Shipping Fee</span>
                                <span class="<?php echo $shipping == 0 ? 'free-shipping' : ''; ?>">
                                    <?php echo $shipping > 0 ? 'P' . number_format($shipping, 2) : 'FREE'; ?>
                                </span>
                            </div>
                            <div class="summary-row">
                                <span>Tax (12% VAT)</span>
                                <span>P<?php echo number_format($tax, 2); ?></span>
                            </div>
                            <div class="summary-divider"></div>
                            <div class="summary-total">
                                <span>Total</span>
                                <span>P<?php echo number_format($total, 2); ?></span>
                            </div>
                        </div>

                        <?php if ($shipping == 0): ?>
                            <div class="free-shipping-notice">
                                <svg xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px" fill="#22c55e">
                                    <path d="m424-296 282-282-56-56-226 226-114-114-56 56 170 170Zm56 216q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z" />
                                </svg>
                                You qualify for FREE shipping!
                            </div>
                        <?php else: ?>
                            <div class="shipping-notice">
                                <svg xmlns="http://www.w3.org/2000/svg" height="16px" viewBox="0 -960 960 960" width="16px" fill="#fb923c">
                                    <path d="M440-280h80v-240h-80v240Zm40-320q17 0 28.5-11.5T520-640q0-17-11.5-28.5T480-680q-17 0-28.5 11.5T440-640q0 17 11.5 28.5T480-600Zm0 520q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z" />
                                </svg>
                                Add P<?php echo number_format(1000 - $subtotal, 2); ?> more for FREE shipping!
                            </div>
                        <?php endif; ?>

                        <div class="secure-checkout">
                            <svg xmlns="http://www.w3.org/2000/svg" height="16px" viewBox="0 -960 960 960" width="16px" fill="#22c55e">
                                <path d="M480-80q-139-35-229.5-159.5T160-516v-244l320-120 320 120v244q0 152-90.5 276.5T480-80Zm0-84q104-33 172-132t68-220v-189l-240-90-240 90v189q0 121 68 220t172 132Zm0-316Z" />
                            </svg>
                            Secure Checkout — Your data is protected
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <script src="<?php echo asset('design/toast.js'); ?>"></script>
    <script src="<?php echo asset('design/item-count.js'); ?>"></script>
    <script src="<?php echo asset('design/checkout.js'); ?>"></script>
    <script>window.APP_ROOT = '<?php echo $root; ?>';</script>
    <script src="<?php echo asset('design/notifications.js'); ?>"></script>
    <style>
        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .summary-item-pricing {
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 3px 0;
            flex-wrap: wrap;
        }

        .summary-price-discounted {
            font-size: var(--fs-sm);
            font-weight: 700;
            color: var(--primary);
        }

        .summary-price-original {
            font-size: var(--fs-xs);
            color: var(--muted);
            text-decoration: line-through;
        }

        .summary-discount-badge {
            font-size: var(--fs-xs);
            font-weight: 700;
            color: #22c55e;
            background: rgba(34, 197, 94, 0.1);
            padding: 1px 6px;
            border-radius: 20px;
        }
    </style>
</body>

</html>