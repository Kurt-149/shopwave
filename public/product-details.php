<?php
require_once dirname(__DIR__) . '/core/init.php';
require_once dirname(__DIR__) . '/core/session-handler.php';
require_once dirname(__DIR__) . '/authentication/database.php';
require_once dirname(__DIR__) . '/core/config.php';

$root = BASE_URL;

$isLoggedIn = isLoggedIn();
$userId = $isLoggedIn ? getCurrentUserId() : 0;

require_once dirname(__DIR__) . '/core/security.php';
require_once dirname(__DIR__) . '/backend/detailsbackend.php';

$page = 'product';
if (!$product) {
    header('Location: ' . $root . '/public/shop.php?error=' . urlencode('Product not found'));
    exit;
}
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - SHOPWAVE</title>
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <link rel="stylesheet" href="<?php echo asset('design/main-layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('design/web-design/product-details.css'); ?>">
</head>

<body>
    <div class="wrapper">
        <nav class="mobile-product-header">
            <a href="javascript:history.length > 1 ? history.back() : window.location.href='<?php echo $root; ?>/public/shop.php'"
                class="mph-back" aria-label="Go back">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                    <path d="M400-80 0-480l400-400 71 71-329 329 329 329-71 71Z" />
                </svg>
            </a>
            <h1 class="first-header">Product Details</h1>
            <div class="mph-right">
                <a href="<?php echo $root; ?>/public/cart.php" class="mph-cart" aria-label="Cart">
                    <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor">
                        <path d="M280-80q-33 0-56.5-23.5T200-160q0-33 23.5-56.5T280-240q33 0 56.5 23.5T360-160q0 33-23.5 56.5T280-80Zm400 0q-33 0-56.5-23.5T600-160q0-33 23.5-56.5T680-240q33 0 56.5 23.5T760-160q0 33-23.5 56.5T680-80ZM246-720l96 200h280l110-200H246Zm-38-80h590q23 0 35 20.5t1 41.5L692-482q-11 20-29.5 31T622-440H324l-44 80h480v80H280q-45 0-68-39.5t-2-78.5l54-98-144-304H40v-80h130l38 80Z" />
                    </svg>
                    <span class="mph-cart-badge cart-count-badge" style="display:none;">0</span>
                </a>
                <div class="mph-more-wrap" id="mphMoreWrap">
                    <button class="mph-more-btn" onclick="toggleMobileMore()" aria-label="More options" aria-expanded="false">
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
                        <button class="mph-report" onclick="mphReportItem()" role="menuitem">
                            <svg xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px" fill="currentColor">
                                <path d="M480-280q17 0 28.5-11.5T520-320q0-17-11.5-28.5T480-360q-17 0-28.5 11.5T440-320q0 17 11.5 28.5T480-280Zm-40-160h80v-240h-80v240Zm40 360q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z" />
                            </svg>
                            Report this item
                        </button>
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
            <div class="breadcrumbs">
                <a href="<?php echo $root; ?>/index.php">Home</a>
                <span class="separator">›</span>
                <a href="<?php echo $root; ?>/public/shop.php">Shop</a>
                <span class="separator">›</span>
                <span class="current"><?php echo htmlspecialchars($product['name']); ?></span>
            </div>

            <div class="product-detail-top">
                <div class="product-gallery">
                    <div class="main-image">
                        <?php if ($product['image_url']): ?>
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>"
                                alt="<?php echo htmlspecialchars($product['name']); ?>"
                                id="mainProductImage">
                            <button class="view-image-btn" onclick="openImageLightbox()" aria-label="View full image">
                                <svg xmlns="http://www.w3.org/2000/svg" height="16px" viewBox="0 -960 960 960" width="16px" fill="currentColor">
                                    <path d="M120-120v-270h80v150l504-504H554v-80h270v270h-80v-150L240-200h150v80H120Z" />
                                </svg>
                                View Image
                            </button>
                        <?php else: ?>
                            <div class="no-image-placeholder">
                                <svg xmlns="http://www.w3.org/2000/svg" height="80px" viewBox="0 -960 960 960" width="80px" fill="#93c5fd">
                                    <path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm0-80h560v-560H200v560Zm40-80h480L570-480 450-320l-90-120-120 160Zm-40 80v-560 560Z" />
                                </svg>
                                <p>No Image Available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="product-info-main">
                    <div class="product-header-block">
                        <div class="product-title-row">
                            <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                            <?php if (!empty($product['discount_price']) && $product['discount_price'] < $product['price']): ?>
                                <span class="sale-badge">
                                    <img src="<?php echo $root; ?>/image/tag.png" alt=""> SALE
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="product-meta">
                            <span class="product-category-badge">
                                <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                            </span>
                            <?php if (!empty($product['brand'])): ?>
                                <span class="product-brand-badge"><?php echo htmlspecialchars($product['brand']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="product-price-section">
                            <?php if (!empty($product['discount_price']) && $product['discount_price'] < $product['price']): ?>
                                <span class="current-price">P<?php echo number_format($product['discount_price'], 2); ?></span>
                                <span class="original-price">P<?php echo number_format($product['price'], 2); ?></span>
                                <span class="discount-percentage">
                                    -<?php echo round((($product['price'] - $product['discount_price']) / $product['price']) * 100); ?>% off
                                </span>
                            <?php else: ?>
                                <span class="current-price">P<?php echo number_format($product['price'], 2); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="product-stats">
                        <div class="rating-display">
                            <div class="stars">
                                <?php
                                $avgRating = (float)($product['avg_rating'] ?? 0);
                                $fullStars = floor($avgRating);
                                $halfStar = ($avgRating - $fullStars) >= 0.5;
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $fullStars) echo '<span class="star filled">★</span>';
                                    elseif ($i == $fullStars + 1 && $halfStar) echo '<span class="star half">★</span>';
                                    else echo '<span class="star">★</span>';
                                }
                                ?>
                            </div>
                            <span class="rating-number"><?php echo number_format($avgRating, 1); ?></span>
                        </div>
                        <span class="stat-divider">|</span>
                        <span class="ratings-count"><?php echo (int)($product['rating_count'] ?? 0); ?> Ratings</span>
                        <span class="stat-divider">|</span>
                        <span class="sold-count"><?php echo (int)($product['sold_count'] ?? 0); ?> Sold</span>
                    </div>

                    <div class="variant-section">
                        <?php
                        $colors = array_map(fn($c) => [
                            'name' => $c['name'],
                            'image' => $c['image_url'] ?: $product['image_url'],
                        ], $product['colors'] ?? []);
                        $sizes = $product['sizes'] ?? [];
                        ?>
                        <div class="variant-row">
                            <span class="variant-label">Color:</span>
                            <?php if ($colorOverflow): ?>
                                <div class="variant-dropdown-wrap" id="colorDropdownWrap">
                                    <button class="variant-trigger" onclick="toggleDropdown('colorDropdownWrap')" id="colorTrigger">
                                        <span id="colorTriggerText">See all <?php echo count($colors); ?> colors</span>
                                        <span class="trigger-arrow">▾</span>
                                    </button>
                                    <div class="variant-dropdown" id="colorDropdown">
                                        <?php foreach ($colors as $color): ?>
                                            <button class="variant-btn color-btn"
                                                onclick="selectVariant(this, 'color', 'colorTriggerText', 'colorDropdown', 'colorDropdownWrap')"
                                                data-value="<?php echo htmlspecialchars($color['name']); ?>"
                                                data-image="<?php echo htmlspecialchars($color['image']); ?>">
                                                <?php if (!empty($color['image'])): ?>
                                                    <img src="<?php echo htmlspecialchars($color['image']); ?>" alt="<?php echo htmlspecialchars($color['name']); ?>">
                                                <?php endif; ?>
                                                <span><?php echo htmlspecialchars($color['name']); ?></span>
                                                <span class="variant-check">✓</span>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="variant-choices">
                                    <?php foreach ($colors as $color): ?>
                                        <button class="variant-btn color-btn"
                                            onclick="selectVariant(this, 'color', null, null, null)"
                                            data-value="<?php echo htmlspecialchars($color['name']); ?>"
                                            data-image="<?php echo htmlspecialchars($color['image']); ?>">
                                            <?php if (!empty($color['image'])): ?>
                                                <img src="<?php echo htmlspecialchars($color['image']); ?>" alt="<?php echo htmlspecialchars($color['name']); ?>">
                                            <?php endif; ?>
                                            <span><?php echo htmlspecialchars($color['name']); ?></span>
                                            <span class="variant-check">✓</span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="variant-row">
                            <span class="variant-label">Size:</span>
                            <?php if ($sizeOverflow): ?>
                                <div class="variant-dropdown-wrap" id="sizeDropdownWrap">
                                    <button class="variant-trigger" onclick="toggleDropdown('sizeDropdownWrap')" id="sizeTrigger">
                                        <span id="sizeTriggerText">See all <?php echo count($sizes); ?> sizes</span>
                                        <span class="trigger-arrow">▾</span>
                                    </button>
                                    <div class="variant-dropdown" id="sizeDropdown">
                                        <?php foreach ($sizes as $size): ?>
                                            <button class="variant-btn size-btn"
                                                onclick="selectVariant(this, 'size', 'sizeTriggerText', 'sizeDropdown', 'sizeDropdownWrap')"
                                                data-value="<?php echo htmlspecialchars($size); ?>">
                                                <span><?php echo htmlspecialchars($size); ?></span>
                                                <span class="variant-check">✓</span>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="variant-choices">
                                    <?php foreach ($sizes as $size): ?>
                                        <button class="variant-btn size-btn"
                                            onclick="selectVariant(this, 'size', null, null, null)"
                                            data-value="<?php echo htmlspecialchars($size); ?>">
                                            <span><?php echo htmlspecialchars($size); ?></span>
                                            <span class="variant-check">✓</span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="product-actions-wrap">
                        <div class="qty-stock-row">
                            <div class="quantity-controls">
                                <button type="button" class="qty-btn" onclick="decreaseQty()" aria-label="Decrease quantity"
                                    <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?>>−</button>
                                <input type="number" id="quantity" value="<?php echo $product['stock'] > 0 ? 1 : 0; ?>" min="1" max="<?php echo $product['stock']; ?>"
                                    onchange="validateQty()" aria-label="Quantity"
                                    <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?>>
                                <button type="button" class="qty-btn" onclick="increaseQty()" aria-label="Increase quantity"
                                    <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?>>+</button>
                            </div>
                            <div class="stock-status">
                                <?php if ($product['stock'] > 10): ?>
                                    <span class="in-stock">In Stock (<?php echo $product['stock']; ?> available)</span>
                                <?php elseif ($product['stock'] > 0): ?>
                                    <span class="low-stock">Low Stock — Only <?php echo $product['stock']; ?> left</span>
                                <?php else: ?>
                                    <span class="out-of-stock">Out of Stock</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="product-actions">
                            <button class="btn-add-cart"
                                onclick="addToCart(<?php echo $product['id']; ?>, this)"
                                <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?>
                                data-product-id="<?php echo $product['id']; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor">
                                    <path d="M280-80q-33 0-56.5-23.5T200-160q0-33 23.5-56.5T280-240q33 0 56.5 23.5T360-160q0 33-23.5 56.5T280-80Zm400 0q-33 0-56.5-23.5T600-160q0-33 23.5-56.5T680-240q33 0 56.5 23.5T760-160q0 33-23.5 56.5T680-80ZM246-720l96 200h280l110-200H246Zm-38-80h590q23 0 35 20.5t1 41.5L692-482q-11 20-29.5 31T622-440H324l-44 80h480v80H280q-45 0-68-39.5t-2-78.5l54-98-144-304H40v-80h130l38 80Z" />
                                </svg>
                                <?php echo $product['stock'] <= 0 ? 'Sold Out' : 'Add to Cart'; ?>
                            </button>
                            <button class="btn-buy-now"
                                onclick="buyNow(<?php echo $product['id']; ?>)"
                                <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?>>
                                <?php echo $product['stock'] <= 0 ? 'Sold Out' : 'Buy Now'; ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="trust-strip">
                <div class="trust-item">
                    <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor">
                        <path d="m424-296 282-282-56-56-226 226-114-114-56 56 170 170Zm56 216q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z" />
                    </svg>
                    Free Returns
                </div>
                <div class="trust-item">
                    <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor">
                        <path d="m424-296 282-282-56-56-226 226-114-114-56 56 170 170Zm56 216q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z" />
                    </svg>
                    Secure Checkout
                </div>
                <div class="trust-item">
                    <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor">
                        <path d="m424-296 282-282-56-56-226 226-114-114-56 56 170 170Zm56 216q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z" />
                    </svg>
                    Ships in 1–3 Days
                </div>
            </div>

            <div class="section-card description-section">
                <div class="section-card-header">
                    <h2>Product Description</h2>
                </div>
                <div class="description-content">
                    <?php if (!empty($product['description'])): ?>
                        <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    <?php else: ?>
                        <p class="no-description">No description available for this product.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="section-card reviews-section">
                <div class="section-card-header">
                    <h2>Customer Reviews</h2>
                    <div class="overall-rating">
                        <div class="rating-score">
                            <span class="score-number"><?php echo number_format((float)($product['avg_rating'] ?? 0), 1); ?></span>
                            <div class="stars-large">
                                <?php
                                $avgRating = (float)($product['avg_rating'] ?? 0);
                                $fullStars = floor($avgRating);
                                $halfStar = ($avgRating - $fullStars) >= 0.5;
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $fullStars) echo '<span class="star filled">★</span>';
                                    elseif ($i == $fullStars + 1 && $halfStar) echo '<span class="star half">★</span>';
                                    else echo '<span class="star">★</span>';
                                }
                                ?>
                            </div>
                            <p class="total-reviews"><?php echo (int)($product['rating_count'] ?? 0); ?> Reviews</p>
                        </div>
                    </div>
                </div>

                <div class="rating-breakdown">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <?php
                        $count = $product['rating_breakdown'][$i] ?? 0;
                        $totalRatings = $product['rating_count'] ?? 0;
                        $percentage = $totalRatings > 0 ? ($count / $totalRatings) * 100 : 0;
                        ?>
                        <div class="rating-bar-item">
                            <span class="rating-label"><?php echo $i; ?> ★</span>
                            <div class="rating-bar">
                                <div class="rating-bar-fill" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                            <span class="rating-count"><?php echo $count; ?></span>
                        </div>
                    <?php endfor; ?>
                </div>

                <?php if (isLoggedIn()): ?>
                    <?php if (!isset($userHasReviewed) || !$userHasReviewed): ?>
                        <?php if (isset($userCanReview) && $userCanReview): ?>
                            <div class="write-review-section">
                                <h3>📝 Write a Review</h3>
                                <form id="reviewForm" class="review-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <div class="form-group">
                                        <label>Your Rating <span class="required">*</span></label>
                                        <div class="star-rating-input">
                                            <input type="radio" name="rating" id="star5" value="5"><label for="star5">★</label>
                                            <input type="radio" name="rating" id="star4" value="4"><label for="star4">★</label>
                                            <input type="radio" name="rating" id="star3" value="3"><label for="star3">★</label>
                                            <input type="radio" name="rating" id="star2" value="2"><label for="star2">★</label>
                                            <input type="radio" name="rating" id="star1" value="1"><label for="star1">★</label>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="reviewComment">Your Review <span class="required">*</span></label>
                                        <textarea id="reviewComment" name="comment" rows="5" placeholder="Share your experience with this product..." required minlength="10" maxlength="1000"></textarea>
                                        <small class="char-count">Minimum 10 characters, maximum 1000 characters</small>
                                    </div>
                                    <button type="submit" class="submit-review-btn">Submit Review</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="login-to-review">
                                <p>📦 You can only review products you have purchased and received.</p>
                                <p><a href="<?php echo $root; ?>/public/me-page.php#orders">View your orders</a></p>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="already-reviewed">
                            <p>✓ You have already reviewed this product</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="login-to-review">
                        <p>Please <a href="<?php echo $root; ?>/authentication/login-page.php">login</a> to write a review</p>
                    </div>
                <?php endif; ?>

                <div class="reviews-list">
                    <?php if (!empty($reviews)): ?>
                        <?php foreach ($reviews as $review): ?>
                            <div class="review-item" id="review-<?php echo $review['id']; ?>" data-review-id="<?php echo $review['id']; ?>">
                                <div class="review-header">
                                    <div class="reviewer-info">
                                        <div class="reviewer-avatar">
                                            <?php if (!empty($review['profile_picture'])): ?>
                                                <img src="<?php echo $root . '/' . htmlspecialchars($review['profile_picture']); ?>" alt="Profile">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($review['username'], 0, 1)); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <h4 class="reviewer-name">
                                                <?php echo htmlspecialchars($review['username']); ?>
                                                <?php if (isset($review['is_edited']) && $review['is_edited'] == 1): ?>
                                                    <span class="edited-badge">Edited</span>
                                                <?php endif; ?>
                                                <?php if (isset($review['verified_purchase']) && $review['verified_purchase']): ?>
                                                    <span class="verified-badge">✓ Verified Purchase</span>
                                                <?php endif; ?>
                                                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $review['user_id']): ?>
                                                    <span class="review-owner-badge">You</span>
                                                <?php endif; ?>
                                            </h4>
                                            <p class="review-date"><?php echo date('F j, Y', strtotime($review['created_at'])); ?></p>
                                        </div>
                                    </div>
                                    <div class="review-rating">
                                        <?php for ($i = 1; $i <= 5; $i++) echo $i <= $review['rating'] ? '<span class="star filled">★</span>' : '<span class="star">★</span>'; ?>
                                    </div>
                                </div>

                                <?php if (!empty($review['comment'])): ?>
                                    <p class="review-comment"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                <?php endif; ?>

                                <?php if (!empty($review['admin_reply'])): ?>
                                    <div class="admin-reply-section">
                                        <div class="admin-reply-header">
                                            <span class="admin-reply-header-title">Store Owner's Response</span>
                                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                                <div class="admin-reply-btns">
                                                    <button class="admin-edit-reply-btn" onclick="editAdminReply(<?php echo $review['id']; ?>, <?php echo htmlspecialchars(json_encode($review['admin_reply']), ENT_QUOTES); ?>)">Edit</button>
                                                    <button class="admin-edit-reply-btn delete" onclick="deleteAdminReply(<?php echo $review['id']; ?>)">Delete</button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="admin-reply-content"><?php echo nl2br(htmlspecialchars($review['admin_reply'])); ?></div>
                                        <?php if (!empty($review['admin_reply_at'])): ?>
                                            <div class="admin-reply-date">Replied on <?php echo date('F j, Y', strtotime($review['admin_reply_at'])); ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="review-helpful">
                                    <span>Was this helpful?</span>
                                    <?php if (isLoggedIn()): ?>
                                        <button class="vote-helpful" data-review-id="<?php echo $review['id']; ?>">
                                            <img src="<?php echo $root; ?>/image/thumb-up.png" alt="thumb up"> Yes <span class="vote-count">(<?php echo $review['helpful_count'] ?? 0; ?>)</span>
                                        </button>
                                        <button class="vote-not-helpful" data-review-id="<?php echo $review['id']; ?>">
                                            <img src="<?php echo $root; ?>/image/dislike.png" alt=""> No
                                        </button>
                                    <?php else: ?>
                                        <span>
                                            <img src="<?php echo $root; ?>/image/thumb-up.png" alt="thumb up"> Yes (<?php echo $review['helpful_count'] ?? 0; ?>)
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php
                                $isAdmin      = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
                                $isOwnReview  = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $review['user_id'];
                                $hasReply     = !empty($review['admin_reply']);
                                $showActions  = $isAdmin || $isOwnReview;
                                ?>
                                <?php if ($showActions): ?>
                                    <div class="review-actions">
                                        <?php if ($isOwnReview && !$isAdmin): ?>
                                            <button class="review-action-btn edit-btn" onclick="editReview(<?php echo $review['id']; ?>)">
                                                <img src="<?php echo $root; ?>/image/comment.png" alt="edit"> Edit
                                            </button>
                                            <button class="review-action-btn delete-btn" onclick="deleteReview(<?php echo $review['id']; ?>)">
                                                <img src="<?php echo $root; ?>/image/delete.png" alt="delete"> Delete
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($isAdmin): ?>
                                            <?php if (!$hasReply): ?>
                                                <button class="review-action-btn reply-btn" onclick="replyToReview(<?php echo $review['id']; ?>)">
                                                    <img src="<?php echo $root; ?>/image/reply.png" alt="reply"> Reply as Store Owner
                                                </button>
                                            <?php endif; ?>
                                            <button class="review-action-btn delete-btn" onclick="adminDeleteReview(<?php echo $review['id']; ?>)">
                                                <img src="<?php echo $root; ?>/image/delete.png" alt="delete"> Remove Review
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <?php if (isset($totalReviews, $reviewsPerPage, $reviewPage) && $totalReviews > $reviewsPerPage): ?>
                            <?php $totalReviewPages = ceil($totalReviews / $reviewsPerPage); ?>
                            <div class="reviews-pagination">
                                <?php if ($reviewPage > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['review_page' => $reviewPage - 1])); ?>" class="pagination-btn">← Previous</a>
                                <?php endif; ?>
                                <span class="page-info">Page <?php echo $reviewPage; ?> of <?php echo $totalReviewPages; ?></span>
                                <?php if ($reviewPage < $totalReviewPages): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['review_page' => $reviewPage + 1])); ?>" class="pagination-btn">Next →</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="no-reviews">
                            <p>No reviews yet. Be the first to review this product!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="related-products-section">
                <h2>Related Products</h2>
                <div class="related-products-grid">
                    <?php if (!empty($relatedProducts)): ?>
                        <?php foreach ($relatedProducts as $related): ?>
                            <a href="<?php echo $root; ?>/public/product-details.php?id=<?php echo $related['id']; ?>" class="related-product-card">
                                <div class="related-product-image">
                                    <?php if (!empty($related['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($related['image_url']); ?>" alt="<?php echo htmlspecialchars($related['name']); ?>" loading="lazy">
                                    <?php else: ?>
                                        <div class="no-image">🛍️</div>
                                    <?php endif; ?>
                                </div>
                                <div class="related-product-info">
                                    <h3><?php echo htmlspecialchars($related['name']); ?></h3>
                                    <?php if (!empty($related['brand'])): ?>
                                        <span class="related-product-brand"><?php echo htmlspecialchars($related['brand']); ?></span>
                                    <?php endif; ?>
                                    <div class="related-product-price">
                                        <?php if (!empty($related['discount_price']) && $related['discount_price'] < $related['price']): ?>
                                            <span class="price-current">P<?php echo number_format($related['discount_price'], 2); ?></span>
                                            <span class="price-original">P<?php echo number_format($related['price'], 2); ?></span>
                                        <?php else: ?>
                                            <span class="price-current">P<?php echo number_format($related['price'], 2); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-related">No related products found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="image-lightbox" id="imageLightbox" onclick="closeImageLightbox()">
        <button class="lightbox-close" onclick="closeImageLightbox()" aria-label="Close">
            <svg xmlns="http://www.w3.org/2000/svg" height="22px" viewBox="0 -960 960 960" width="22px" fill="currentColor">
                <path d="m256-200-56-56 224-224-224-224 56-56 224 224 224-224 56 56-224 224 224 224-56 56-224-224-224 224Z" />
            </svg>
        </button>
        <img src="" alt="Product full view" id="lightboxImage" onclick="event.stopPropagation()">
    </div>
    <script>window.APP_ROOT = '<?php echo $root; ?>';</script>
    <script src="<?php echo asset('design/notifications.js'); ?>"></script>
    <script src="<?php echo asset('design/toast.js'); ?>"></script>
    <script src="<?php echo asset('design/item-count.js'); ?>"></script>
    <script src="<?php echo asset('design/movement/product-details.js'); ?>"></script>
    <script src="<?php echo asset('design/movement/review.js'); ?>"></script>
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
                        mobileSearchBar.querySelector('input').focus();
                    }
                });
            }
        });

        function toggleMobileMore() {
            const wrap = document.getElementById('mphMoreWrap');
            if (!wrap) return;
            const isOpen = wrap.classList.toggle('open');
            if (isOpen) {
                setTimeout(() => {
                    document.addEventListener('click', function closeMphHandler(e) {
                        if (!wrap.contains(e.target)) {
                            wrap.classList.remove('open');
                            document.removeEventListener('click', closeMphHandler);
                        }
                    });
                }, 10);
            }
        }

        function mphReportItem() {
            const wrap = document.getElementById('mphMoreWrap');
            if (wrap) wrap.classList.remove('open');
            alert('Thank you for your report. We will review this item.');
        }
    </script>
</body>

</html>