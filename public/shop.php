<?php
require_once __DIR__ . '/../core/security.php';
require_once dirname(__DIR__) . '/core/init.php';
require_once dirname(__DIR__) . '/core/session-handler.php';
require_once dirname(__DIR__) . '/authentication/database.php';
require_once dirname(__DIR__) . '/core/config.php';

$root = BASE_URL;

$isLoggedIn = isLoggedIn();
$userId = $isLoggedIn ? getCurrentUserId() : 0;

require_once dirname(__DIR__) . '/backend/shopbackend.php';
$page = 'shop';
$hasActiveFilters = !empty($search) || !empty($category) || !empty($min_price) || !empty($max_price);
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

function renderShopCard(array $product, string $root, bool $soldOut): string
{
    $id   = (int)$product['id'];
    $url  = $root . '/public/product-details.php?id=' . $id;
    $name = htmlspecialchars($product['name']);
    $cat  = htmlspecialchars($product['category_name'] ?? 'Uncategorized');
    $price = (float)$product['price'];
    $disc  = (float)($product['discount_price'] ?? 0);
    $hasDiscount = $disc > 0 && $disc < $price;
    $now = $hasDiscount ? $disc : $price;
    $pct = $hasDiscount ? (int)round(($price - $disc) / $price * 100) : 0;
    $stock = (int)($product['stock'] ?? 0);
    $ratingCount = (int)($product['rating_count'] ?? 0);
    $avg = (float)($product['avg_rating'] ?? 0);

    if ($soldOut) {
        $stockHtml = '<span class="shop-stock out">Sold Out</span>';
    } elseif ($stock > 10) {
        $stockHtml = '<span class="shop-stock in">In Stock (' . $stock . ')</span>';
    } else {
        $stockHtml = '<span class="shop-stock low">Low Stock (' . $stock . ')</span>';
    }

    $ratingHtml = $ratingCount > 0
        ? '<span class="shop-card-rating rated">★ ' . number_format($avg, 1) . ' (' . $ratingCount . ')</span>'
        : '<span class="shop-card-rating">No ratings yet</span>';

    $media = !empty($product['image_url'])
        ? '<img src="' . htmlspecialchars($product['image_url']) . '" alt="' . $name . '" loading="lazy">'
        : '<div class="shop-placeholder">No image</div>';
    $save = $pct > 0 ? '<span class="shop-save">Save ' . $pct . '%</span>' : '';
    $was = $hasDiscount ? '<span class="shop-price-was">P' . number_format($price, 2) . '</span>' : '';

    return '<article class="shop-card' . ($soldOut ? ' is-sold' : '') . '">'
        . '<a href="' . $url . '" class="shop-card-media">' . $save . $media . '</a>'
        . '<div class="shop-card-body">'
        . '<span class="shop-card-cat">' . $cat . '</span>'
        . '<a href="' . $url . '" class="shop-card-name">' . $name . '</a>'
        . $ratingHtml
        . '<div class="shop-card-price"><span class="shop-price-now">P' . number_format($now, 2) . '</span>' . $was . '</div>'
        . $stockHtml
        . '</div></article>';
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
    <title>Shop - SHOPWAVE</title>
    <style>
        .burger-container,
        .menu-box,
        .category-list,
        .burger-icon {
            display: none !important
        }
    </style>
    <link rel="stylesheet" href="<?php echo asset('design/main-layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('design/web-design/shop.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('design/web-design/shop-breadcrumbs.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('design/shopwave-theme.css'); ?>">
</head>

<body>
    <div class="wrapper">

        <div class="announce-bar">Free shipping on orders over ₱1,000 · Cash on delivery available · 30-day returns</div>
        <?php require __DIR__ . '/../includes/site-header.php'; ?>

        <div class="mobile-search-bar" id="mobileSearchBar" style="display:none;">
            <form action="<?php echo $root; ?>/public/shop.php" method="GET" class="search-wrapper">
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
        </div>

        <div class="page-body">

            <div class="breadcrumbs">
                <a href="<?php echo $root; ?>/index.php">Home</a>
                <span class="separator">›</span>
                <span class="current">Shop</span>
            </div>

            <div class="shop-title-row">
                <h1 class="shop-title">
                    <svg xmlns="http://www.w3.org/2000/svg" height="28px" viewBox="0 -960 960 960" width="28px" fill="currentColor">
                        <path d="M280-80q-33 0-56.5-23.5T200-160q0-33 23.5-56.5T280-240q33 0 56.5 23.5T360-160q0 33-23.5 56.5T280-80Zm400 0q-33 0-56.5-23.5T600-160q0-33 23.5-56.5T680-240q33 0 56.5 23.5T760-160q0 33-23.5 56.5T680-80ZM246-720l96 200h280l110-200H246Zm-38-80h590q23 0 35 20.5t1 41.5L692-482q-11 20-29.5 31T622-440H324l-44 80h480v80H280q-45 0-68-39.5t-2-78.5l54-98-144-304H40v-80h130l38 80Z" />
                    </svg>
                    All products
                </h1>
                <span class="shop-count"><?php echo (int)$total_products; ?> <?php echo (int)$total_products === 1 ? 'product' : 'products'; ?></span>
            </div>

            <nav class="shop-chips" aria-label="Filter by category">
                <a href="<?php echo $root; ?>/public/shop.php" class="shop-chip <?php echo empty($category) ? 'active' : ''; ?>">All</a>
                <?php foreach ($categories as $cat): ?>
                    <a href="<?php echo $root; ?>/public/shop.php?category=<?php echo (int)$cat['id']; ?>" class="shop-chip <?php echo ($category ?? 0) === (int)$cat['id'] ? 'active' : ''; ?>"><?php echo htmlspecialchars($cat['name']); ?></a>
                <?php endforeach; ?>
            </nav>

            <div class="shop-controls">
                <button class="filter-toggle-btn <?php echo $hasActiveFilters ? 'has-filters open' : ''; ?>" id="filterToggleBtn" type="button">
                    <svg xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px" fill="currentColor">
                        <path d="M440-160v-320L200-840h560L520-480v320l-80-80Z" />
                    </svg>
                    Filters
                    <?php if ($hasActiveFilters): ?><span class="filter-dot"></span><?php endif; ?>
                    <svg class="toggle-arrow" xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px" fill="currentColor">
                        <path d="M480-344 240-584l56-56 184 184 184-184 56 56-240 240Z" />
                    </svg>
                </button>

                <div class="sort-wrapper">
                    <label class="sort-label" for="sortSelect">
                        <svg xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px" fill="currentColor">
                            <path d="M120-240v-80h240v80H120Zm0-200v-80h480v80H120Zm0-200v-80h720v80H120Z" />
                        </svg>
                        Sort by:
                    </label>
                    <select id="sortSelect" class="sort-select" onchange="window.location.href='<?php echo $root; ?>/public/shop.php?sort='+this.value+'<?php echo ($search ?? '') ? '&search=' . urlencode($search) : ''; ?><?php echo ($category ?? '') ? '&category=' . ($category ?? '') : ''; ?><?php echo ($min_price ?? '') ? '&min_price=' . ($min_price ?? '') : ''; ?><?php echo ($max_price ?? '') ? '&max_price=' . ($max_price ?? '') : ''; ?>'">
                        <option value="newest" <?php echo ($sort ?? 'newest') == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="price_low" <?php echo ($sort ?? '') == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_high" <?php echo ($sort ?? '') == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="name" <?php echo ($sort ?? '') == 'name' ? 'selected' : ''; ?>>Name: A-Z</option>
                    </select>
                </div>
            </div>

            <div class="filter-panel <?php echo $hasActiveFilters ? 'open' : ''; ?>" id="filterPanel">
                <form method="GET" action="<?php echo $root; ?>/public/shop.php" class="filter-form">
                    <div class="filter-fields">
                        <div class="filter-group">
                            <label class="filter-label">Search</label>
                            <input type="text" name="search" class="filter-input" placeholder="Search products..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Category</label>
                            <select name="category" class="filter-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo ($category ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Min Price (P)</label>
                            <input type="number" name="min_price" class="filter-input" placeholder="0" min="0" max="100000" step="100" value="<?php echo htmlspecialchars($min_price ?? ''); ?>">
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Max Price (P)</label>
                            <input type="number" name="max_price" class="filter-input" placeholder="Any" min="0" max="100000" step="100" value="<?php echo htmlspecialchars($max_price ?? ''); ?>">
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="filter-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px" fill="currentColor">
                                <path d="M382-240 154-468l57-57 171 171 367-367 57 57-424 424Z" />
                            </svg>
                            Apply Filters
                        </button>
                        <a href="<?php echo $root; ?>/public/shop.php" class="clear-filters">
                            <svg xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px" fill="currentColor">
                                <path d="m256-200-56-56 224-224-224-224 56-56 224 224 224-224 56 56-224 224 224 224-56 56-224-224-224 224Z" />
                            </svg>
                            Clear All
                        </a>
                    </div>
                </form>
            </div>

            <div class="shop-main">
                <?php if (empty($products)): ?>
                    <div class="empty-state">
                        <svg class="empty-icon" xmlns="http://www.w3.org/2000/svg" height="80px" viewBox="0 -960 960 960" width="80px" fill="currentColor">
                            <path d="M784-120 532-372q-30 24-69 38t-83 14q-109 0-184.5-75.5T120-580q0-109 75.5-184.5T380-840q109 0 184.5 75.5T640-580q0 44-14 83t-38 69l252 252-56 56ZM380-400q75 0 127.5-52.5T560-580q0-75-52.5-127.5T380-760q-75 0-127.5 52.5T200-580q0 75 52.5 127.5T380-400Z" />
                        </svg>
                        <h3 class="empty-title">No Products Found</h3>
                        <p class="empty-desc">We couldn't find any products matching your filters.</p>
                        <a href="<?php echo $root; ?>/public/shop.php" class="btn-primary">Clear All Filters</a>
                    </div>
                <?php else: ?>

                    <?php
                    $has_filters = !empty($search) || !empty($category) || !empty($min_price) || !empty($max_price);

                    $available_products = [];
                    $soldout_products = [];

                    foreach ($products as $product) {
                        if ($product['stock'] > 0) {
                            $available_products[] = $product;
                        } else {
                            $soldout_products[] = $product;
                        }
                    }
                    ?>

                    <?php if (!empty($available_products)): ?>
                        <div class="products-grid">
                            <?php foreach ($available_products as $product): echo renderShopCard($product, $root, false); endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($soldout_products)): ?>
                        <?php if ($has_filters): ?>
                            <h2 class="filter-section-title">Sold Out Items</h2>
                        <?php endif; ?>
                        <div class="products-grid">
                            <?php foreach ($soldout_products as $product): echo renderShopCard($product, $root, true); endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>

                <?php if (($total_pages ?? 1) > 1): ?>
                    <div class="pagination">
                        <a href="<?php echo ($current_page ?? 1) > 1 ? $root . '/public/shop.php?' . ($query_string ?? '') . (($query_string ?? '') ? '&' : '') . 'page=' . (($current_page ?? 1) - 1) : '#'; ?>"
                            class="pagination-btn <?php echo ($current_page ?? 1) <= 1 ? 'disabled' : ''; ?>"
                            <?php echo ($current_page ?? 1) <= 1 ? 'onclick="return false;"' : ''; ?>>
                            <svg xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px" fill="currentColor">
                                <path d="M560-240 320-480l240-240 56 56-184 184 184 184-56 56Z" />
                            </svg>
                            Previous
                        </a>
                        <div class="pagination-numbers">
                            <?php
                            $start_page = max(1, ($current_page ?? 1) - 2);
                            $end_page = min($total_pages ?? 1, ($current_page ?? 1) + 2);
                            if ($start_page > 1) {
                                echo '<a href="' . $root . '/public/shop.php?' . ($query_string ?? '') . (($query_string ?? '') ? '&' : '') . 'page=1" class="pagination-num">1</a>';
                                if ($start_page > 2) echo '<span class="pagination-dots">...</span>';
                            }
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                $active_class = ($i == ($current_page ?? 1)) ? 'active' : '';
                                echo '<a href="' . $root . '/public/shop.php?' . ($query_string ?? '') . (($query_string ?? '') ? '&' : '') . 'page=' . $i . '" class="pagination-num ' . $active_class . '">' . $i . '</a>';
                            }
                            if ($end_page < ($total_pages ?? 1)) {
                                if ($end_page < ($total_pages ?? 1) - 1) echo '<span class="pagination-dots">...</span>';
                                echo '<a href="' . $root . '/public/shop.php?' . ($query_string ?? '') . (($query_string ?? '') ? '&' : '') . 'page=' . ($total_pages ?? 1) . '" class="pagination-num">' . ($total_pages ?? 1) . '</a>';
                            }
                            ?>
                        </div>
                        <a href="<?php echo ($current_page ?? 1) < ($total_pages ?? 1) ? $root . '/public/shop.php?' . ($query_string ?? '') . (($query_string ?? '') ? '&' : '') . 'page=' . (($current_page ?? 1) + 1) : '#'; ?>"
                            class="pagination-btn <?php echo ($current_page ?? 1) >= ($total_pages ?? 1) ? 'disabled' : ''; ?>"
                            <?php echo ($current_page ?? 1) >= ($total_pages ?? 1) ? 'onclick="return false;"' : ''; ?>>
                            Next
                            <svg xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px" fill="currentColor">
                                <path d="M504-480 320-664l56-56 240 240-240 240-56-56 184-184Z" />
                            </svg>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
              <footer class="shop-footer-wrap">
            <div class="shop-footer">
                <div>
                    <h4>Shop</h4>
                    <?php foreach ($categories as $cat): ?>
                        <a href="<?php echo $root; ?>/public/shop.php?category=<?php echo (int)$cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></a>
                    <?php endforeach; ?>
                </div>
                <div>
                    <h4>Payments</h4>
                    <span>Cash on delivery, GCash, bank transfer, cards</span>
                </div>
            </div>
            <p class="shop-copy">&copy; <?php echo date('Y'); ?> SHOPWAVE. All rights reserved.</p>
        </footer>

        </div>
    </div>

    <div class="rate-limit-indicator" id="rateLimitIndicator">
        <span> Adding too fast</span>
        <div class="rate-limit-progress">
            <div class="rate-limit-fill" id="rateLimitProgress" style="width: 0%;"></div>
        </div>
    </div>
    <script src="<?php echo asset('design/csrf-fetch.js'); ?>"></script>
    <script src="<?php echo asset('design/toast.js'); ?>"></script>
    <script src="<?php echo asset('design/item-count.js'); ?>"></script>
    <script>window.APP_ROOT = '<?php echo $root; ?>';</script>
    <script src="<?php echo asset('design/notifications.js'); ?>"></script>
    <script src="<?php echo asset('design/shop.js'); ?>"></script>
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