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
require_once dirname(__DIR__) . '/backend/cart-backend.php';

$page = 'cart';
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
    <title>Shopping Cart - SHOPWAVE</title>
    <style>
        .burger-container,
        .menu-box,
        .category-list,
        .burger-icon {
            display: none !important
        }
    </style>
    <link rel="stylesheet" href="<?php echo asset('design/main-layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('design/web-design/shop-breadcrumbs.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('design/web-design/cart.css'); ?>">
</head>

<body>
    <div class="wrapper">

        <nav class="mobile-product-header">
            <button type="button" class="mph-back" aria-label="Go back" data-action="go-back">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                    <path d="M400-80 0-480l400-400 71 71-329 329 329 329-71 71Z" />
                </svg>
            </button>
            <div class="cart-count">
                <h1>Shopping Cart</h1>
                <span class="item-count">(<?php echo count($cartItems); ?>)</span>
            </div>
            <div class="mph-right">
                <?php if (!empty($cartItems)): ?>
                    <button class="mph-edit-btn" id="mphEditBtn" type="button" data-action="toggle-edit">
                        <svg xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px" fill="currentColor">
                            <path d="M200-200h57l391-391-57-57-391 391v57Zm-80 80v-170l528-528q12-11 26.5-17t30.5-6q16 0 31 6t26 18l55 56q12 11 17.5 26t5.5 30q0 16-5.5 30.5T817-647L289-120H120Zm640-584-56-56 56 56Zm-141 85-28-29 57 57-29-28Z" />
                        </svg>
                        <span id="mphEditLabel">Edit</span>
                    </button>
                <?php endif; ?>
                <div class="mph-more-wrap" id="mphMoreWrap">
                    <button class="mph-more-btn" type="button" aria-label="More options" aria-expanded="false" data-action="toggle-more">
                        <span class="mph-dots"><span></span><span></span><span></span></span>
                    </button>
                    <div class="mph-dropdown" role="menu">
                        <a href="<?php echo $root; ?>/index.php" role="menuitem">
                            <svg xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px" fill="currentColor">
                                <path d="M240-200h120v-240h240v240h120v-360L480-740 240-560v360Zm-80 80v-480l320-240 320 240v480H520v-240h-80v240H160Zm320-350Z" />
                            </svg>
                            Back to main page
                        </a>
                        <div class="mph-divider"></div>
                        <a href="<?php echo $root; ?>/public/shop.php" role="menuitem">
                            <svg xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px" fill="currentColor">
                                <path d="M280-80q-33 0-56.5-23.5T200-160q0-33 23.5-56.5T280-240q33 0 56.5 23.5T360-160q0 33-23.5 56.5T280-80Zm400 0q-33 0-56.5-23.5T600-160q0-33 23.5-56.5T680-240q33 0 56.5 23.5T760-160q0 33-23.5 56.5T680-80ZM246-720l96 200h280l110-200H246Zm-38-80h590q23 0 35 20.5t1 41.5L692-482q-11 20-29.5 31T622-440H324l-44 80h480v80H280q-45 0-68-39.5t-2-78.5l54-98-144-304H40v-80h130l38 80Zm134 280h280-280Z" />
                            </svg>
                            Continue Shopping
                        </a>
                    </div>
                </div>
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
                            <input type="text" name="search" placeholder="Search products..." class="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
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
            <div class="cart-content">
                <?php if (empty($cartItems)): ?>
                    <div class="empty-cart">
                        <svg xmlns="http://www.w3.org/2000/svg" height="120px" viewBox="0 -960 960 960" width="120px" fill="#9ca3af">
                            <path d="M280-80q-33 0-56.5-23.5T200-160q0-33 23.5-56.5T280-240q33 0 56.5 23.5T360-160q0 33-23.5 56.5T280-80Zm400 0q-33 0-56.5-23.5T600-160q0-33 23.5-56.5T680-240q33 0 56.5 23.5T760-160q0 33-23.5 56.5T680-80ZM246-720l96 200h280l110-200H246Zm-38-80h590q23 0 35 20.5t1 41.5L692-482q-11 20-29.5 31T622-440H324l-44 80h480v80H280q-45 0-68-39.5t-2-78.5l54-98-144-304H40v-80h130l38 80Zm134 280h280-280Z" />
                        </svg>
                        <h2>Your cart is empty</h2>
                        <p>Looks like you haven't added anything to your cart yet.</p>
                        <a href="<?php echo $root; ?>/public/shop.php" class="btn-continue-shopping">Continue Shopping</a>
                    </div>
                <?php else: ?>
                    <div class="cart-header">
                        <div class="cart-header-left">
                            <h1>Shopping Cart</h1>
                            <span class="item-count">(<?php echo count($cartItems); ?>)</span>
                        </div>
                        <button class="cart-clear-all" type="button" data-action="clear-all">
                            <svg xmlns="http://www.w3.org/2000/svg" height="16px" viewBox="0 -960 960 960" width="16px" fill="currentColor">
                                <path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm400-600H280v520h400v-520ZM360-280h80v-360h-80v360Zm160 0h80v-360h-80v360ZM280-720v520-520Z" />
                            </svg>
                            Clear All
                        </button>
                    </div>

                    <div class="cart-layout">
                        <div class="cart-items-section">
                            <?php foreach ($cartItems as $item): ?>
                                <div class="cart-item" data-cart-id="<?php echo $item['cart_id']; ?>">
                                    <label class="item-select-wrap" style="display:none;">
                                        <input type="checkbox" class="item-checkbox" value="<?php echo $item['cart_id']; ?>">
                                        <span class="item-checkmark"></span>
                                    </label>

                                    <div class="item-image">
                                        <a href="<?php echo $root; ?>/public/product-details.php?id=<?php echo $item['product_id']; ?>">
                                            <?php $displayImg = !empty($item['color_image_url']) ? $item['color_image_url'] : ($item['image_url'] ?? ''); ?>
                                            <?php if ($displayImg): ?>
                                                <img src="<?php echo htmlspecialchars($displayImg); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                            <?php else: ?>
                                                <div class="no-image">
                                                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                                        <path d="M200-640v440h560v-440H640v320l-160-80-160 80v-320H200Zm0 520q-33 0-56.5-23.5T120-200v-499q0-14 4.5-27t13.5-24l50-61q11-14 27.5-21.5T250-840h460q18 0 34.5 7.5T772-811l50 61q9 11 13.5 24t4.5 27v499q0 33-23.5 56.5T760-120H200Zm16-600h528l-34-40H250l-34 40Zm184 80v190l80-40 80 40v-190H400Zm-200 0h560-560Z" />
                                                    </svg>
                                                </div>
                                            <?php endif; ?>
                                        </a>
                                    </div>

                                    <div class="item-info">
                                        <h3 class="item-name">
                                            <a href="<?php echo $root; ?>/public/product-details.php?id=<?php echo $item['product_id']; ?>"><?php echo htmlspecialchars($item['name']); ?></a>
                                        </h3>
                                        <div class="item-meta">
                                            <?php if ($item['brand']): ?>
                                                <span class="item-brand">Brand: <?php echo htmlspecialchars($item['brand']); ?></span>
                                            <?php endif; ?>
                                            <span class="item-category">Category: <?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></span>
                                            <span class="item-brand">Stock: <?php echo htmlspecialchars($item['stock']); ?></span>
                                        </div>

                                        <?php
                                        $availableColors = $productColors[$item['product_id']] ?? [];
                                        $availableSizes  = $productSizes[$item['product_id']] ?? [];
                                        ?>
                                        <?php if (!empty($availableColors) || !empty($availableSizes)): ?>
                                            <div class="item-variants">
                                                <?php if (!empty($availableColors)): ?>
                                                    <div class="cart-variant-wrap" id="colorWrap-<?php echo $item['cart_id']; ?>">
                                                        Color:
                                                        <button class="cart-variant-trigger" type="button" data-action="toggle-variant">
                                                            <span class="color-label"><?php echo htmlspecialchars($item['selected_color'] ?? 'Select color'); ?></span>
                                                            <span class="trigger-arrow">▾</span>
                                                        </button>
                                                        <div class="cart-variant-dropdown">
                                                            <?php foreach ($availableColors as $color): ?>
                                                                <button class="cart-variant-option <?php echo ($color === ($item['selected_color'] ?? '')) ? 'selected' : ''; ?>"
                                                                    type="button"
                                                                    data-action="change-color"
                                                                    data-cart-id="<?php echo $item['cart_id']; ?>"
                                                                    data-value="<?php echo htmlspecialchars($color, ENT_QUOTES); ?>">
                                                                    <?php echo htmlspecialchars($color); ?><span class="option-check">✓</span>
                                                                </button>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($availableSizes)): ?>
                                                    <div class="cart-variant-wrap" id="sizeWrap-<?php echo $item['cart_id']; ?>">
                                                        Size:
                                                        <button class="cart-variant-trigger" type="button" data-action="toggle-variant">
                                                            <span class="size-label"><?php echo htmlspecialchars($item['selected_size'] ?? 'Select size'); ?></span>
                                                            <span class="trigger-arrow">▾</span>
                                                        </button>
                                                        <div class="cart-variant-dropdown">
                                                            <?php foreach ($availableSizes as $sz): ?>
                                                                <button class="cart-variant-option <?php echo ($sz === ($item['selected_size'] ?? '')) ? 'selected' : ''; ?>"
                                                                    type="button"
                                                                    data-action="change-size"
                                                                    data-cart-id="<?php echo $item['cart_id']; ?>"
                                                                    data-value="<?php echo htmlspecialchars($sz, ENT_QUOTES); ?>">
                                                                    <?php echo htmlspecialchars($sz); ?><span class="option-check">✓</span>
                                                                </button>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <div class="item-price-section">
                                            <?php if (!empty($item['discount_price']) && $item['discount_price'] < $item['price']): ?>
                                                <span class="item-price">P<?php echo number_format($item['discount_price'], 2); ?></span>
                                                <span class="item-price-original">P<?php echo number_format($item['price'], 2); ?></span>
                                                <span class="item-price-off">-<?php echo round((($item['price'] - $item['discount_price']) / $item['price']) * 100); ?>% off</span>
                                            <?php else: ?>
                                                <span class="item-price">P<?php echo number_format($item['price'], 2); ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($item['quantity'] > $item['stock']): ?>
                                            <p class="stock-warning">! Only <?php echo $item['stock']; ?> left in stock</p>
                                        <?php elseif ($item['stock'] <= 5): ?>
                                            <p class="stock-low">Only <?php echo $item['stock']; ?> left!</p>
                                        <?php endif; ?>

                                        <div class="item-quantity">
                                            <button class="qty-btn" type="button"
                                                data-action="update-qty"
                                                data-cart-id="<?php echo $item['cart_id']; ?>"
                                                data-qty="<?php echo $item['quantity'] - 1; ?>"
                                                data-stock="<?php echo $item['stock']; ?>">
                                                <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor">
                                                    <path d="M200-440v-80h560v80H200Z" />
                                                </svg>
                                            </button>
                                            <input type="number" class="qty-input" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['stock']; ?>" readonly>
                                            <button class="qty-btn" type="button"
                                                data-action="update-qty"
                                                data-cart-id="<?php echo $item['cart_id']; ?>"
                                                data-qty="<?php echo $item['quantity'] + 1; ?>"
                                                data-stock="<?php echo $item['stock']; ?>">
                                                <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor">
                                                    <path d="M440-440H200v-80h240v-240h80v240h240v80H520v240h-80v-240Z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="item-subtotal">
                                        <span class="subtotal-label">Subtotal</span>
                                        <span class="subtotal-amount">P<?php echo number_format($item['subtotal'], 2); ?></span>
                                    </div>

                                    <div class="item-actions">
                                        <a href="<?php echo $root; ?>/public/product-details.php?id=<?php echo $item['product_id']; ?>" class="btn-view-product" title="View Product">
                                            <svg xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px" fill="currentColor">
                                                <path d="M480-320q75 0 127.5-52.5T660-500q0-75-52.5-127.5T480-680q-75 0-127.5 52.5T300-500q0 75 52.5 127.5T480-320Zm0-72q-45 0-76.5-31.5T372-500q0-45 31.5-76.5T480-608q45 0 76.5 31.5T588-500q0 45-31.5 76.5T480-392Zm0 192q-146 0-266-81.5T40-500q54-137 174-218.5T480-800q146 0 266 81.5T920-500q-54 137-174 218.5T480-200Z" />
                                            </svg>
                                            View
                                        </a>
                                        <button class="item-remove" type="button" title="Remove item"
                                            data-action="remove"
                                            data-cart-id="<?php echo $item['cart_id']; ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
                                                <path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm400-600H280v520h400v-520ZM360-280h80v-360h-80v360Zm160 0h80v-360h-80v360ZM280-720v520-520Z" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="edit-mode-bar" id="editModeBar" style="display:none;">
                                <label class="select-all-wrap">
                                    <input type="checkbox" id="selectAllChk">
                                    <span>Select All</span>
                                </label>
                                <button class="delete-selected-btn" type="button" data-action="delete-selected">
                                    <svg xmlns="http://www.w3.org/2000/svg" height="16px" viewBox="0 -960 960 960" width="16px" fill="currentColor">
                                        <path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm400-600H280v520h400v-520ZM360-280h80v-360h-80v360Zm160 0h80v-360h-80v360ZM280-720v520-520Z" />
                                    </svg>
                                    Delete Selected
                                </button>
                            </div>
                            <?php if (!empty($suggestedProducts)): ?>
                                <div class="suggested-section">
                                    <h2>You May Also Like</h2>
                                    <div class="suggested-grid" id="suggestedGrid">
                                        <?php foreach ($suggestedProducts as $p): ?>
                                            <a href="<?php echo $root; ?>/public/product-details.php?id=<?php echo $p['id']; ?>" class="suggested-card">
                                                <div class="suggested-card-image">
                                                    <?php if ($p['image_url']): ?>
                                                        <img src="<?php echo htmlspecialchars($p['image_url']); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>">
                                                        <button class="suggested-view-btn" type="button"
                                                            data-action="open-lightbox"
                                                            data-src="<?php echo htmlspecialchars($p['image_url'], ENT_QUOTES); ?>">
                                                            <svg xmlns="http://www.w3.org/2000/svg" height="13px" viewBox="0 -960 960 960" width="13px" fill="currentColor">
                                                                <path d="M120-120v-270h80v150l504-504H554v-80h270v270h-80v-150L240-200h150v80H120Z" />
                                                            </svg>
                                                            View
                                                        </button>
                                                    <?php else: ?>
                                                        <div class="no-image">🛍️</div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="suggested-card-info">
                                                    <div class="suggested-card-category"><?php echo htmlspecialchars($p['category_name'] ?? ''); ?></div>
                                                    <h3 class="suggested-card-name"><?php echo htmlspecialchars($p['name']); ?></h3>
                                                    <div class="suggested-card-price">
                                                        <?php if (!empty($p['discount_price']) && $p['discount_price'] < $p['price']): ?>
                                                            <span class="suggested-price-current">P<?php echo number_format($p['discount_price'], 2); ?></span>
                                                            <span class="suggested-price-original">P<?php echo number_format($p['price'], 2); ?></span>
                                                        <?php else: ?>
                                                            <span class="suggested-price-current">P<?php echo number_format($p['price'], 2); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="suggested-pagination" id="suggestedPagination"></div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="cart-summary">
                            <h2>Order Summary</h2>
                            <div class="summary-details">
                                <div class="summary-row">
                                    <span>Subtotal (<?php echo $totalItems; ?> items)</span>
                                    <span id="summary-subtotal">P<?php echo number_format($subtotal, 2); ?></span>
                                </div>
                                <div class="summary-row">
                                    <span>Shipping Fee</span>
                                    <span id="summary-shipping" class="<?php echo $shipping == 0 ? 'free-shipping' : ''; ?>">
                                        <?php echo $shipping > 0 ? 'P' . number_format($shipping, 2) : 'FREE'; ?>
                                    </span>
                                </div>
                                <?php if ($shipping > 0 && $subtotal < 1000): ?>
                                    <div class="shipping-promo">
                                        <svg xmlns="http://www.w3.org/2000/svg" height="16px" viewBox="0 -960 960 960" width="16px" fill="#fb923c">
                                            <path d="M440-280h80v-240h-80v240Zm40-320q17 0 28.5-11.5T520-640q0-17-11.5-28.5T480-680q-17 0-28.5 11.5T440-640q0 17 11.5 28.5T480-600Zm0 520q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z" />
                                        </svg>
                                        Add P<?php echo number_format(1000 - $subtotal, 2); ?> more for FREE shipping!
                                    </div>
                                <?php endif; ?>
                                <div class="summary-row">
                                    <span>Tax (12% VAT)</span>
                                    <span id="summary-tax">P<?php echo number_format($tax, 2); ?></span>
                                </div>
                                <div class="summary-divider"></div>
                                <div class="summary-total">
                                    <span>Total</span>
                                    <span id="summary-total-amount">P<?php echo number_format($total, 2); ?></span>
                                </div>
                            </div>
                            <a href="<?php echo $root; ?>/public/checkout.php" class="btn-checkout">
                                <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor">
                                    <path d="M440-183v-274L200-596v274l240 139Zm80 0 240-139v-274L520-457v274Zm-40 92L160-252q-19-11-29.5-29T120-321v-318q0-22 10.5-40t29.5-29l320-184q19-11 40-11t40 11l320 184q19 11 29.5 29t10.5 40v318q0 22-10.5 40T880-252L560-91q-19 11-40 11t-40-11Z" />
                                </svg>
                                Proceed to Checkout
                            </a>
                            <div class="secure-checkout">
                                <svg xmlns="http://www.w3.org/2000/svg" height="16px" viewBox="0 -960 960 960" width="16px" fill="#22c55e">
                                    <path d="M480-80q-139-35-229.5-159.5T160-516v-244l320-120 320 120v244q0 152-90.5 276.5T480-80Zm0-84q104-33 172-132t68-220v-189l-240-90-240 90v189q0 121 68 220t172 132Zm0-316Z" />
                                </svg>
                                Secure Checkout
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($cartItems)): ?>
            <div class="sticky-checkout-bar" id="stickyBar">
                <div class="sticky-total-info">
                    <span class="sticky-total-label">Total</span>
                    <span class="sticky-total-amount" id="sticky-total">P<?php echo number_format($total, 2); ?></span>
                </div>
                <a href="<?php echo $root; ?>/public/checkout.php" class="sticky-checkout-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px" fill="currentColor">
                        <path d="M440-183v-274L200-596v274l240 139Zm80 0 240-139v-274L520-457v274Z" />
                    </svg>
                    Proceed to Checkout
                </a>
            </div>
        <?php endif; ?>

        <div class="cart-image-lightbox" id="cartLightbox">
            <button class="cart-lightbox-close" type="button" data-action="close-lightbox">
                <svg xmlns="http://www.w3.org/2000/svg" height="22px" viewBox="0 -960 960 960" width="22px" fill="currentColor">
                    <path d="m256-200-56-56 224-224-224-224 56-56 224 224 224-224 56 56-224 224 224 224-56 56-224-224-224 224Z" />
                </svg>
            </button>
            <img src="" alt="Product full view" id="cartLightboxImg">
        </div>
        <script src="<?php echo asset('design/toast.js'); ?>"></script>
        <script src="<?php echo asset('design/cart.js'); ?>"></script>
        <script src="<?php echo asset('design/item-count.js'); ?>"></script>
        <script>window.APP_ROOT = '<?php echo $root; ?>';</script>
    <script src="<?php echo asset('design/notifications.js'); ?>"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const searchToggle = document.querySelector('.mobile-search-toggle');
                const mobileSearchBar = document.getElementById('mobileSearchBar');
                if (searchToggle && mobileSearchBar) {
                    searchToggle.addEventListener('click', function() {
                        const isHidden = mobileSearchBar.style.display === 'none' || !mobileSearchBar.style.display;
                        mobileSearchBar.style.display = isHidden ? 'block' : 'none';
                        if (isHidden) {
                            mobileSearchBar.style.animation = 'slideDown 0.3s ease';
                            mobileSearchBar.querySelector('input')?.focus();
                        }
                    });
                }
            });
        </script>
</body>

</html>