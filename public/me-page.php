<?php
require_once __DIR__ . '/../core/init.php';
require_once __DIR__ . '/../core/session-handler.php';
require_once __DIR__ . '/../authentication/database.php';
require_once __DIR__ . '/../core/config.php';

$root = BASE_URL;

$isLoggedIn = isLoggedIn();
$userId = $isLoggedIn ? getCurrentUserId() : 0;
require_once dirname(__DIR__) . '/core/security.php';
$csrfToken = generateCsrfToken();

$timeout = 1800;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $timeout)) {
    session_unset();
    session_destroy();
    header('Location: ' . $root . '/authentication/login-page.php?error=' . urlencode('Session expired. Please login again.'));
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

$userId = filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT);
if (!$userId || $userId <= 0) {
    session_unset();
    session_destroy();
    header('Location: ' . $root . '/authentication/login-page.php');
    exit;
}

try {
    $pdo = getPdo();

    $s = $pdo->prepare("SELECT id, username, email, phone, profile_picture, created_at FROM users WHERE id = ? LIMIT 1");
    $s->execute([$userId]);
    $user = $s->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        session_unset();
        session_destroy();
        header('Location: ' . $root . '/authentication/login-page.php');
        exit;
    }

    $s = $pdo->prepare("SELECT
        COUNT(*) AS total,
        SUM(status = 'pending')   AS pending,
        SUM(status = 'shipped')   AS shipped,
        SUM(status = 'delivered') AS delivered
      FROM orders WHERE user_id = ?");
    $s->execute([$userId]);
    $oc = $s->fetch(PDO::FETCH_ASSOC);
    $totalOrders    = (int)($oc['total']     ?? 0);
    $totalPending   = (int)($oc['pending']   ?? 0);
    $totalShipped   = (int)($oc['shipped']   ?? 0);
    $totalDelivered = (int)($oc['delivered'] ?? 0);

    $s = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE user_id = ?");
    $s->execute([$userId]);
    $totalReviews = (int)$s->fetchColumn();

    try {
        $s = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $s->execute([$userId]);
        $unreadCount = (int)$s->fetchColumn();

        $s = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
        $s->execute([$userId]);
        $totalNotifications = (int)$s->fetchColumn();

        $s = $pdo->prepare("SELECT id, type, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
        $s->execute([$userId]);
        $dropdownNotifs = $s->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $unreadCount = 0;
        $totalNotifications = 0;
        $dropdownNotifs = [];
    }
} catch (PDOException $e) {
    error_log('[me-page] DB: ' . $e->getMessage());
    $user = ['username' => 'User', 'email' => '', 'phone' => '', 'profile_picture' => null, 'created_at' => date('Y-m-d')];
    $totalOrders = $totalPending = $totalShipped = $totalDelivered = $totalReviews = 0;
    $unreadCount = $totalNotifications = 0;
    $dropdownNotifs = [];
}

function timeAgo(string $dt): string
{
    $d = (new DateTime())->diff(new DateTime($dt));
    if ($d->y) return $d->y  . ' year'  . ($d->y  > 1 ? 's' : '') . ' ago';
    if ($d->m) return $d->m  . ' month' . ($d->m  > 1 ? 's' : '') . ' ago';
    if ($d->d) return $d->d  . ' day'   . ($d->d  > 1 ? 's' : '') . ' ago';
    if ($d->h) return $d->h  . ' hour'  . ($d->h  > 1 ? 's' : '') . ' ago';
    if ($d->i) return $d->i  . ' min'   . ($d->i  > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

$notifIcons = ['order' => '📦', 'promo' => '🏷️', 'review' => '★', 'alert' => '⚠️'];
$joinedDate = date('M j, Y', strtotime($user['created_at']));
$isAdmin    = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title>My Account — SHOPWAVE</title>
    <style>
        .burger-container,
        .menu-box,
        .category-list,
        .burger-icon {
            display: none !important
        }
    </style>
    <link rel="stylesheet" href="<?php echo asset('design/main-layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('design/web-design/me-page.css'); ?>">
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
                                                $nType   = $n['type'] ?? 'order';
                                                $nIcon   = $notifIcons[$nType] ?? '📦';
                                                $nUnread = !(bool)$n['is_read'];
                                                $nAgo    = timeAgo($n['created_at'] ?? '');

                                                // Parse status prefix: DB stores "shipped|Your order..."
                                                $rawMsg  = $n['message'] ?? '';
                                                if ($nType === 'order' && strpos($rawMsg, '|') !== false) {
                                                    [$orderStatus, $mainMsg] = explode('|', $rawMsg, 2);
                                                } else {
                                                    $orderStatus = '';
                                                    $mainMsg     = $rawMsg;
                                                }
                                                $noteMsg = '';
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
                                                            <button class="notif-reason-btn">View Reason</button>
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
                <span class="current">Profile</span>
            </div>
            <div class="profile-dashboard">

                <aside class="profile-sidebar">
                    <div class="user-info-card">
                        <div class="user-avatar" onclick="openProfileImageLightbox()">
                            <?php if (!empty($user['profile_picture'])): ?>
                                <img src="<?php echo $root . htmlspecialchars($user['profile_picture']); ?>"
                                    alt="<?php echo htmlspecialchars($user['username']); ?>"
                                    onerror="this.onerror=null; this.src='<?php echo $root; ?>/images/default-avatar.png';">
                            <?php else: ?>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="rgb(8,8,8)" width="60px" height="60px">
                                    <path d="M480-480q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47ZM160-160v-112q0-34 17.5-62.5T224-378q62-31 129-46.5T480-440q68 0 135 15.5T744-378q29 15 46.5 43.5T808-272v112H160Z" />
                                </svg>
                            <?php endif; ?>
                        </div>
                        <h2 class="user-name"><?php echo htmlspecialchars($user['username']); ?></h2>
                        <p class="user-email"><?php echo htmlspecialchars($user['email']); ?></p>
                        <p class="user-joined">Member since <?php echo $joinedDate; ?></p>
                    </div>

                    <nav class="profile-nav">
                        <a href="#profile" class="nav-item active" onclick="showSection('profile'); return false;">
                            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
                                <path d="M234-276q51-39 114-61.5T480-360q69 0 132 22.5T726-276q35-41 54.5-93T800-480q0-133-93.5-226.5T480-800q-133 0-226.5 93.5T160-480q0 59 19.5 111t54.5 93Zm246-164q-59 0-99.5-40.5T340-580q0-59 40.5-99.5T480-720q59 0 99.5 40.5T620-580q0 59-40.5 99.5T480-440Z" />
                            </svg>
                            <span>My Profile</span>
                        </a>
                        <a href="#edit-profile" class="nav-item" onclick="showSection('edit-profile'); return false;">
                            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
                                <path d="M200-200h57l391-391-57-57-391 391v57Zm-80 80v-170l528-527q12-11 26.5-17t30.5-6q16 0 31 6t26 18l55 56q12 11 17.5 26t5.5 30q0 16-5.5 30.5T817-647L290-120H120Zm640-584-56-56 56 56Zm-141 85-28-29 57 57-29-28Z" />
                            </svg>
                            <span>Edit Profile</span>
                        </a>

                        <div class="nav-divider"></div>

                        <a href="#to-ship" class="nav-item" onclick="showSection('to-ship'); return false;">
                            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
                                <path d="M240-160q-50 0-85-35t-35-85H40v-440q0-33 23.5-56.5T120-800h560v160h120l120 160v200h-80q0 50-35 85t-85 35q-50 0-85-35t-35-85H360q0 50-35 85t-85 35Zm0-80q17 0 28.5-11.5T280-280q0-17-11.5-28.5T240-320q-17 0-28.5 11.5T200-280q0 17 11.5 28.5T240-240ZM120-360h32q17-18 39-29t49-11q27 0 49 11t39 29h272v-360H120v360Zm600 120q17 0 28.5-11.5T760-280q0-17-11.5-28.5T720-320q-17 0-28.5 11.5T680-280q0 17 11.5 28.5T720-240Zm-40-200h170l-90-120h-80v120ZM360-540Z" />
                            </svg>
                            <span>To Ship</span>
                        </a>
                        <a href="#to-receive" class="nav-item" onclick="showSection('to-receive'); return false;">
                            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
                                <path d="M440-183v-274L200-596v274l240 139Zm80 0 240-139v-274L520-457v274Zm-80 92L160-252q-19-11-29.5-29T120-321v-318q0-22 10.5-40t29.5-29l280-161q19-11 40-11t40 11l280 161q19 11 29.5 29t10.5 40v318q0 22-10.5 40T800-252L520-91q-19 11-40 11t-40-11ZM240-637l240 139 240-139-240-139-240 139Z" />
                            </svg>
                            <span>To Receive</span>
                        </a>
                        <a href="#completed" class="nav-item" onclick="showSection('completed'); return false;">
                            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
                                <path d="m424-296 282-282-56-56-226 226-114-114-56 56 170 170Zm56 216q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z" />
                            </svg>
                            <span>Completed</span>
                        </a>
                        <a href="#orders" class="nav-item" onclick="showSection('orders'); return false;">
                            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
                                <path d="M240-80q-50 0-85-35t-35-85v-120h120v-560l60 60 60-60 60 60 60-60 60 60 60-60 60 60 60-60v560h120v120q0 50-35 85t-85 35H240Z" />
                            </svg>
                            <span>All Orders</span>
                        </a>

                        <div class="nav-divider"></div>

                        <a href="#reviews" class="nav-item" onclick="showSection('reviews'); return false;">
                            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
                                <path d="m354-287 126-76 126 77-33-144 111-96-146-13-58-136-58 135-146 13 111 97-33 143ZM233-120l65-281L80-590l288-25 112-265 112 265 288 25-218 189 65 281-247-149-247 149Z" />
                            </svg>
                            <span>My Reviews</span>
                        </a>
                        <a href="#notifications" class="nav-item" onclick="showSection('notifications'); return false;">
                            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
                                <path d="M160-200v-80h80v-280q0-83 50-147.5T420-792v-28q0-25 17.5-42.5T480-880q25 0 42.5 17.5T540-820v28q80 20 130 84.5T720-560v280h80v80H160Zm320-300Zm0 420q-33 0-56.5-23.5T400-160h160q0 33-23.5 56.5T480-80ZM320-280h320v-280q0-66-47-113t-113-47q-66 0-113 47t-47 113v280Z" />
                            </svg>
                            <span>Notifications</span>
                            <?php if ($unreadCount > 0): ?>
                                <span class="badge"><?php echo $unreadCount; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="#settings" class="nav-item" onclick="showSection('settings'); return false;">
                            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
                                <path d="m370-80-16-128q-13-5-24.5-12T307-235l-119 50L78-375l103-78q-1-7-1-13.5v-27q0-6.5 1-13.5L78-585l110-190 119 50q11-8 23-15t24-12l16-128h220l16 128q13 5 24.5 12t22.5 15l119-50 110 190-103 78q1 7 1 13.5v27q0 6.5-2 13.5l103 78-110 190-118-50q-11 8-23 15t-24 12L590-80H370Zm70-80h79l14-106q31-8 57.5-23.5T639-327l99 41 39-68-86-65q5-14 7-29.5t2-31.5q0-16-2-31.5t-7-29.5l86-65-39-68-99 42q-22-23-48.5-38.5T533-694l-13-106h-79l-14 106q-31 8-57.5 23.5T321-633l-99-41-39 68 86 64q-5 15-7 30t-2 32q0 16 2 31t7 30l-86 65 39 68 99-42q22 23 48.5 38.5T427-266l13 106Zm42-180q58 0 99-41t41-99q0-58-41-99t-99-41q-59 0-99.5 41T342-480q0 58 40.5 99t99.5 41Zm-2-140Z" />
                            </svg>
                            <span>Settings</span>
                        </a>

                    </nav>
                </aside>

                <main class="profile-main">

                    <section id="section-profile" class="content-section active">
                        <h1 class="section-title">My Profile</h1>
                        <div class="profile-section-grid">
                            <div class="quick-stats">
                                <div class="stat-card">
                                    <div class="stat-icon orders">
                                        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                            <path d="m787-145 28-28-75-75v-112h-40v128l87 87Zm-587 25q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v268q-19-9-39-15.5t-41-9.5v-243H200v560h242q3 22 9.5 42t15.5 38H200Zm0-120v40-560 243-3 280Zm80-40h163q3-21 9.5-41t14.5-39H280v80Zm0-160h244q32-30 71.5-50t84.5-27v-3H280v80Zm0-160h400v-80H280v80ZM720-40q-83 0-141.5-58.5T520-240q0-83 58.5-141.5T720-440q83 0 141.5 58.5T920-240q0 83-58.5 141.5T720-40Z" />
                                        </svg>
                                    </div>
                                    <div class="stat-info">
                                        <span class="stat-label">Total Orders</span>
                                        <span class="stat-value"><?php echo $totalOrders; ?></span>
                                    </div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-icon reviews">
                                        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                            <path d="m668-380 152-130 120 10-176 153 52 227-102-62-46-198Zm-94-292-42-98 46-110 92 217-96-9ZM294-287l126-76 126 77-33-144 111-96-146-13-58-136-58 135-146 13 111 97-33 143ZM173-120l65-281L20-590l288-25 112-265 112 265 288 25-218 189 65 281-247-149-247 149Zm247-340Z" />
                                        </svg>
                                    </div>
                                    <div class="stat-info">
                                        <span class="stat-label">Reviews Written</span>
                                        <span class="stat-value"><?php echo $totalReviews; ?></span>
                                    </div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-icon shipping">
                                        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                            <path d="M538.5-138.5Q480-197 480-280t58.5-141.5Q597-480 680-480t141.5 58.5Q880-363 880-280t-58.5 141.5Q763-80 680-80t-141.5-58.5ZM747-185l28-28-75-75v-112h-40v128l87 87Zm-547 65q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h167q11-35 43-57.5t70-22.5q40 0 71.5 22.5T594-840h166q33 0 56.5 23.5T840-760v250q-18-13-38-22t-42-16v-212h-80v120H280v-120h-80v560h212q7 22 16 42t22 38H200Zm308.5-651.5Q520-783 520-800t-11.5-28.5Q497-840 480-840t-28.5 11.5Q440-817 440-800t11.5 28.5Q463-760 480-760t28.5-11.5Z" />
                                        </svg>
                                    </div>
                                    <div class="stat-info">
                                        <span class="stat-label">Pending Delivery</span>
                                        <span class="stat-value"><?php echo $totalPending; ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="profile-card">
                                <div class="profile-info-row">
                                    <span class="info-label">Username</span>
                                    <span class="info-value"><?php echo htmlspecialchars($user['username']); ?></span>
                                </div>
                                <div class="profile-info-row">
                                    <span class="info-label">Email</span>
                                    <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                                </div>
                                <div class="profile-info-row">
                                    <span class="info-label">Account Created</span>
                                    <span class="info-value"><?php echo $joinedDate; ?></span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="section-edit-profile" class="content-section">
                        <h1 class="section-title">Edit Profile</h1>
                        <div class="edit-profile-card">
                            <form id="editProfileForm" class="edit-form" enctype="multipart/form-data">
                                <div class="profile-picture-upload">
                                    <div class="profile-picture-preview" id="profilePictureContainer">
                                        <?php if (!empty($user['profile_picture'])): ?>
                                            <img src="<?php echo $root . htmlspecialchars($user['profile_picture']); ?>"
                                                alt="Profile Picture"
                                                id="profilePicturePreview"
                                                onerror="this.onerror=null; this.src='<?php echo $root; ?>/images/default-avatar.png';">
                                        <?php else: ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" height="80px" viewBox="0 -960 960 960" width="80px" id="profilePicturePreview" fill="#64748b">
                                                <path d="M234-276q51-39 114-61.5T480-360q69 0 132 22.5T726-276q35-41 54.5-93T800-480q0-133-93.5-226.5T480-800q-133 0-226.5 93.5T160-480q0 59 19.5 111t54.5 93Zm246-164q-59 0-99.5-40.5T340-580q0-59 40.5-99.5T480-720q59 0 99.5 40.5T620-580q0 59-40.5 99.5T480-440Z" />
                                            </svg>
                                        <?php endif; ?>
                                    </div>
                                    <div class="upload-btn-wrapper">
                                        <button type="button" class="upload-btn" onclick="document.getElementById('profile_picture').click();">Choose Photo</button>
                                        <input type="file" id="profile_picture" name="profile_picture" accept="image/*" style="display:none;">
                                    </div>
                                    <p style="font-size:.75rem;color:#94a3b8;margin-top:.5rem;">Max 5MB · JPG, PNG, GIF, WebP</p>
                                </div>

                                <div class="form-group">
                                    <label for="username">Username</label>
                                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="+63 912 000 0000">
                                </div>

                                <div class="form-divider"></div>
                                <p style="font-size:.875rem;font-weight:600;color:#64748b;margin-bottom:1rem;">
                                    Change Password <span style="font-weight:400;">(leave blank to keep current)</span>
                                </p>

                                <div class="form-group">
                                    <label for="current_password">Current Password</label>
                                    <input type="password" id="current_password" name="current_password" placeholder="Enter your current password" autocomplete="current-password">
                                </div>

                                <div class="form-group">
                                    <label for="new_password">New Password</label>
                                    <input type="password" id="new_password" name="new_password" placeholder="Enter new password (min 8 characters)" autocomplete="new-password">
                                </div>

                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" autocomplete="new-password">
                                </div>

                                <div class="form-actions">
                                    <button type="button" class="btn-secondary" onclick="showSection('profile')">Cancel</button>
                                    <button type="submit" class="btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </section>

                    <section id="section-to-ship" class="content-section">
                        <h1 class="section-title">To Ship</h1>
                        <div class="orders-list" id="toShipOrders">
                            <div class="loading">Loading orders...</div>
                        </div>
                    </section>

                    <section id="section-to-receive" class="content-section">
                        <h1 class="section-title">To Receive</h1>
                        <div class="orders-list" id="toReceiveOrders">
                            <div class="loading">Loading orders...</div>
                        </div>
                    </section>

                    <section id="section-completed" class="content-section">
                        <h1 class="section-title">Completed Orders</h1>
                        <div class="orders-list" id="completedOrders">
                            <div class="loading">Loading orders...</div>
                        </div>
                    </section>

                    <section id="section-reviews" class="content-section">
                        <h1 class="section-title">My Reviews</h1>
                        <div class="reviews-list" id="reviewsList">
                            <div class="loading">Loading reviews...</div>
                        </div>
                    </section>

                    <section id="section-orders" class="content-section">
                        <h1 class="section-title">Order History</h1>
                        <div class="orders-list" id="allOrders">
                            <div class="loading">Loading orders...</div>
                        </div>
                    </section>

                    <section id="section-notifications" class="content-section">
                        <h1 class="section-title">All Notifications</h1>
                        <div id="notificationsContent">
                            <div class="loading">Loading notifications...</div>
                        </div>
                    </section>
                    <section id="section-settings" class="content-section">
                        <h1 class="section-title">Settings</h1>
                        <div class="settings-card">
                            <h3>Notification Preferences</h3>
                            <div class="setting-item">
                                <label class="toggle-label">
                                    <input type="checkbox" class="toggle-input" checked>
                                    <span class="toggle-slider"></span>
                                    <span class="toggle-text">Email notifications for order updates</span>
                                </label>
                            </div>
                            <div class="setting-item">
                                <label class="toggle-label">
                                    <input type="checkbox" class="toggle-input" checked>
                                    <span class="toggle-slider"></span>
                                    <span class="toggle-text">Promotional emails and offers</span>
                                </label>
                            </div>
                            <div class="setting-item">
                                <label class="toggle-label">
                                    <input type="checkbox" class="toggle-input" checked>
                                    <span class="toggle-slider"></span>
                                    <span class="toggle-text">Order status push updates</span>
                                </label>
                            </div>
                        </div>
                        <div class="settings-card">
                            <h3>Privacy</h3>
                            <div class="setting-item">
                                <label class="toggle-label">
                                    <input type="checkbox" class="toggle-input" checked>
                                    <span class="toggle-slider"></span>
                                    <span class="toggle-text">Show my reviews publicly</span>
                                </label>
                            </div>
                            <div class="setting-item">
                                <label class="toggle-label">
                                    <input type="checkbox" class="toggle-input" checked>
                                    <span class="toggle-slider"></span>
                                    <span class="toggle-text">Allow personalised recommendations</span>
                                </label>
                            </div>
                        </div>
                        <div class="settings-card danger-zone">
                            <h3>Danger Zone</h3>
                            <p class="warning-text">Permanently delete your account and all associated data. This action <strong>cannot be undone</strong>.</p>
                            <div style="margin-top:1rem;">
                                <button class="btn-danger" onclick="confirmDeleteAccount()">Delete Account</button>
                            </div>
                        </div>
                        <div class="settings-card">
                            <h3>Session</h3>
                            <p style="font-size:0.875rem;color:#64748b;margin-bottom:1rem;">Sign out of your account on this device.</p>
                            <a href="<?php echo $root; ?>/authentication/logout-page.php" class="btn-danger" style="display:inline-flex;align-items:center;gap:0.5rem;text-decoration:none;">
                                <svg xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px" fill="currentColor">
                                    <path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h280v80H200v560h280v80H200Zm440-160-55-58 102-102H360v-80h327L585-622l55-58 200 200-200 200Z" />
                                </svg>
                                Logout
                            </a>
                        </div>
                    </section>
                </main>
            </div>
        </div>
    </div>
    <div class="profile-image-lightbox" id="profileImageLightbox" onclick="closeProfileImageLightbox()">
        <div class="profile-lightbox-content">
            <button class="profile-lightbox-close" onclick="closeProfileImageLightbox()">×</button>
            <img src="" alt="Profile Image" id="profileLightboxImage">
        </div>
    </div>
    <script src="<?php echo asset('design/toast.js'); ?>"></script>
    <script src="<?php echo asset('design/item-count.js'); ?>"></script>
    <script src="<?php echo asset('design/movement/review.js'); ?>"></script>
    <script>window.APP_ROOT = '<?php echo $root; ?>';</script>
    <script src="<?php echo asset('design/notifications.js'); ?>"></script>
    <script src="<?php echo asset('design/me-page.js'); ?>"></script>
</body>

</html>