<?php
/**
 * Shared site header, used by every public page.
 * Pages set these before including this file (defaults are used if not set):
 * $root, $unreadCount, $totalNotifications, $dropdownNotifs, $notifIcons, $search.
 */
$root = $root ?? BASE_URL;
$unreadCount = $unreadCount ?? 0;
$totalNotifications = $totalNotifications ?? 0;
$dropdownNotifs = $dropdownNotifs ?? [];
$notifIcons = $notifIcons ?? ['order' => '📦', 'promo' => '🏷️', 'review' => '★', 'alert' => '⚠️'];
$search = $search ?? '';

if (!function_exists('timeAgo')) {
    function timeAgo(string $dt): string
    {
        if (empty($dt)) return '';
        $d = (new DateTime())->diff(new DateTime($dt));
        if ($d->y) return $d->y . ' year' . ($d->y > 1 ? 's' : '') . ' ago';
        if ($d->m) return $d->m . ' month' . ($d->m > 1 ? 's' : '') . ' ago';
        if ($d->d) return $d->d . ' day' . ($d->d > 1 ? 's' : '') . ' ago';
        if ($d->h) return $d->h . ' hour' . ($d->h > 1 ? 's' : '') . ' ago';
        if ($d->i) return $d->i . ' min' . ($d->i > 1 ? 's' : '') . ' ago';
        return 'Just now';
    }
}
?>
<!-- Adds the CSRF token to every POST body sent with fetch(), on any page that uses this header. -->
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
        <header class="header">
            <div class="header-main">
                <div class="header-inner">
                    <a href="<?php echo $root; ?>/index.php" class="logo">SHOPWAVE</a>
                    <nav class="nav-categories" aria-label="Categories">
                        <a href="<?php echo $root; ?>/public/shop.php">New in</a>
                        <a href="<?php echo $root; ?>/public/shop.php?category=1">Women</a>
                        <a href="<?php echo $root; ?>/public/shop.php?category=2">Men</a>
                        <a href="<?php echo $root; ?>/public/shop.php?category=3">Kids</a>
                        <a href="<?php echo $root; ?>/public/shop.php?sale=1" class="nav-sale">Sale</a>
                    </nav>
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
                                                $nType   = $n['type'] ?? 'order';
                                                $nIcon   = $notifIcons[$nType] ?? '📦';
                                                $nUnread = !(bool)$n['is_read'];
                                                $nAgo    = timeAgo($n['created_at'] ?? '');
                                                $rawMsg  = $n['message'] ?? '';
                                                $mainMsg = $rawMsg;
                                                $noteMsg = '';
                                                if ($nType === 'order' && strpos($rawMsg, '|') !== false) {
                                                    [, $mainMsg] = explode('|', $rawMsg, 2);
                                                }
                                                if (strpos($mainMsg, ' | ') !== false) {
                                                    [$mainMsg, $noteMsg] = explode(' | ', $mainMsg, 2);
                                                }
                                            ?>
                                                <div class="notif-dd-item <?php echo $nUnread ? 'unread' : ''; ?>"
                                                     data-notif-id="<?php echo (int)$n['id']; ?>"
                                                     data-notif-type="<?php echo htmlspecialchars($nType); ?>"
                                                     data-notif-note="<?php echo htmlspecialchars($noteMsg); ?>">
                                                    <div class="notif-dd-icon <?php echo htmlspecialchars($nType); ?>"><?php echo $nIcon; ?></div>
                                                    <div class="notif-dd-body">
                                                        <div class="notif-dd-msg"><?php echo htmlspecialchars(trim($mainMsg)); ?></div>
                                                        <?php if ($noteMsg): ?>
                                                            <button class="notif-reason-btn" type="button">View Reason</button>
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