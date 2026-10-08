<?php
require_once dirname(__DIR__) . '/core/init.php';
require_once dirname(__DIR__) . '/authentication/database.php';
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/core/security.php';
$root = BASE_URL;
requireAdmin();

$pdo = getPdo();

$order_id = $_GET['id'] ?? null;

if (!$order_id || !is_numeric($order_id)) {
    header('Location: orders.php');
    exit;
}
try {
    $stmt = $pdo->prepare("
        SELECT o.*, u.username, u.email, u.phone
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        header('Location: orders.php');
        exit;
    }
    $stmt = $pdo->prepare("
        SELECT oi.*, p.image_url, p.brand
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database error");
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
    <title>Order Details - SHOPWAVE Admin</title>
    <link rel="stylesheet" href="<?php echo $root; ?>/design/main-layout.css">
    <link rel="stylesheet" href="<?php echo $root; ?>/design/admin/admin.css">
    <style>
        .order-items-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .order-item-compact {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem;
            background: #f8fafc;
            border-radius: 0.5rem;
            border: 1px solid #e2e8f0;
        }

        .order-item-compact-image {
            width: 60px;
            height: 60px;
            border-radius: 0.375rem;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            background: white;
            flex-shrink: 0;
        }

        .order-item-compact-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .order-item-compact-image svg {
            width: 100%;
            height: 100%;
            padding: 12px;
            fill: #94a3b8;
        }

        .order-item-compact-details {
            flex: 1;
            min-width: 0;
        }

        .order-item-compact-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .order-item-compact-variant {
            font-size: 0.7rem;
            color: #3b82f6;
            background: rgba(59, 130, 246, 0.08);
            border: 1px solid rgba(59, 130, 246, 0.2);
            padding: 0.15rem 0.5rem;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 0.25rem;
        }

        .order-item-compact-qty {
            font-size: 0.7rem;
            color: #64748b;
        }

        .order-item-compact-price {
            text-align: right;
            flex-shrink: 0;
            min-width: 80px;
        }

        .order-item-compact-current {
            font-weight: 700;
            color: #3b82f6;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .order-item-compact-original {
            font-size: 0.65rem;
            color: #94a3b8;
            text-decoration: line-through;
            margin-top: 0.125rem;
        }

        @media (max-width: 480px) {
            .order-item-compact {
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .order-item-compact-image {
                width: 50px;
                height: 50px;
            }

            .order-item-compact-price {
                margin-left: auto;
            }
        }
    </style>
</head>

<body>
    <div class="container-admin">
        <div class="admin-layout">
            <!-- Sidebar -->
            <aside class="admin-sidebar">
                <div class="admin-logo">
                    SHOPWAVE
                    <div>Admin Panel</div>
                </div>
                <nav class="admin-nav">
                    <a href="dashboard.php" class="admin-nav-item">
                        <span class="admin-nav-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                <path d="M520-600v-240h320v240H520ZM120-440v-400h320v400H120Zm400 320v-400h320v400H520Zm-400 0v-240h320v240H120Zm80-400h160v-240H200v240Zm400 320h160v-240H600v240Zm0-480h160v-80H600v80ZM200-200h160v-80H200v80Zm160-320Zm240-160Zm0 240ZM360-280Z" />
                            </svg></span>
                        <span>Dashboard</span>
                    </a>
                    <a href="products.php" class="admin-nav-item">
                        <span class="admin-nav-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                <path d="M200-640v440h560v-440H640v320l-160-80-160 80v-320H200Zm0 520q-33 0-56.5-23.5T120-200v-499q0-14 4.5-27t13.5-24l50-61q11-14 27.5-21.5T250-840h460q18 0 34.5 7.5T772-811l50 61q9 11 13.5 24t4.5 27v499q0 33-23.5 56.5T760-120H200Zm16-600h528l-34-40H250l-34 40Zm184 80v190l80-40 80 40v-190H400Zm-200 0h560-560Z" />
                            </svg></span>
                        <span>Products</span>
                    </a>
                    <a href="categories.php" class="admin-nav-item">
                        <span class="admin-nav-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                <path d="m260-520 220-360 220 360H260ZM700-80q-75 0-127.5-52.5T520-260q0-75 52.5-127.5T700-440q75 0 127.5 52.5T880-260q0 75-52.5 127.5T700-80Zm-580-20v-320h320v320H120Zm580-60q42 0 71-29t29-71q0-42-29-71t-71-29q-42 0-71 29t-29 71q0 42 29 71t71 29Zm-500-20h160v-160H200v160Zm202-420h156l-78-126-78 126Zm78 0ZM360-340Zm340 80Z" />
                            </svg></span>
                        <span>Categories</span>
                    </a>
                    <a href="orders.php" class="admin-nav-item active">
                        <span class="admin-nav-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                <path d="M160-160v-516L82-846l72-34 94 202h464l94-202 72 34-78 170v516H160Zm240-280h160q17 0 28.5-11.5T600-480q0-17-11.5-28.5T560-520H400q-17 0-28.5 11.5T360-480q0 17 11.5 28.5T400-440ZM240-240h480v-358H240v358Zm0 0v-358 358Z" />
                            </svg></span>
                        <span>Orders</span>
                    </a>
                    <a href="customers.php" class="admin-nav-item">
                        <span class="admin-nav-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                <path d="M367-527q-47-47-47-113t47-113q47-47 113-47t113 47q47 47 47 113t-47 113q-47 47-113 47t-113-47ZM160-160v-112q0-34 17.5-62.5T224-378q62-31 126-46.5T480-440q66 0 130 15.5T736-378q29 15 46.5 43.5T800-272v112H160Zm80-80h480v-32q0-11-5.5-20T700-306q-54-27-109-40.5T480-360q-56 0-111 13.5T260-306q-9 5-14.5 14t-5.5 20v32Zm296.5-343.5Q560-607 560-640t-23.5-56.5Q513-720 480-720t-56.5 23.5Q400-673 400-640t23.5 56.5Q447-560 480-560t56.5-23.5ZM480-640Zm0 400Z" />
                            </svg></span>
                        <span>Customers</span>
                    </a>
                    <a href="analytics.php" class="admin-nav-item">
                        <span class="admin-nav-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                <path d="M280-280h80v-200h-80v200Zm320 0h80v-400h-80v400Zm-160 0h80v-120h-80v120Zm0-200h80v-80h-80v80ZM200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm0-80h560v-560H200v560Zm0-560v560-560Z" />
                            </svg></span>
                        <span>Analytics</span>
                    </a>
                    <a href="reviews.php" class="admin-nav-item">
                        <span class="admin-nav-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                <path d="M240-400h320v-80H240v80Zm0-120h480v-80H240v80Zm0-120h480v-80H240v80ZM80-80v-720q0-33 23.5-56.5T160-880h640q33 0 56.5 23.5T880-800v480q0 33-23.5 56.5T800-240H240L80-80Zm126-240h594v-480H160v525l46-45Zm-46 0v-480 480Z" />
                            </svg></span>
                        <span>Reviews</span>
                    </a>
                    <a href="settings.php" class="admin-nav-item">
                        <span class="admin-nav-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                <path d="m370-80-16-128q-13-5-24.5-12T307-235l-119 50L78-375l103-78q-1-7-1-13.5v-27q0-6.5 1-13.5L78-585l110-190 119 50q11-8 23-15t24-12l16-128h220l16 128q13 5 24.5 12t22.5 15l119-50 110 190-103 78q1 7 1 13.5v27q0 6.5-2 13.5l103 78-110 190-118-50q-11 8-23 15t-24 12L590-80H370Zm70-80h79l14-106q31-8 57.5-23.5T639-327l99 41 39-68-86-65q5-14 7-29.5t2-31.5q0-16-2-31.5t-7-29.5l86-65-39-68-99 42q-22-23-48.5-38.5T533-694l-13-106h-79l-14 106q-31 8-57.5 23.5T321-633l-99-41-39 68 86 64q-5 15-7 30t-2 32q0 16 2 31t7 30l-86 65 39 68 99-42q22 23 48.5 38.5T427-266l13 106Zm42-180q58 0 99-41t41-99q0-58-41-99t-99-41q-59 0-99.5 41T342-480q0 58 40.5 99t99.5 41Zm-2-140Z" />
                            </svg></span>
                        <span>Settings</span>
                    </a>
                    <div style="border-top: 1px solid var(--border); margin: var(--space-md) 0;"></div>
                    <a href="<?php echo $root; ?>/index.php" class="admin-nav-item">
                        <span class="admin-nav-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                <path d="M200-520q-33 0-56.5-23.5T120-600v-160q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v160q0 33-23.5 56.5T760-520H200Zm0-80h560v-160H200v160Zm0 480q-33 0-56.5-23.5T120-200v-160q0-33 23.5-56.5T200-440h560q33 0 56.5 23.5T840-360v160q0 33-23.5 56.5T760-120H200Zm0-80h560v-160H200v160Zm0-560v160-160Zm0 400v160-160Z" />
                            </svg></span>
                        <span>View Store</span>
                    </a>
                </nav>
            </aside>

            <main class="admin-main">
                <header class="admin-header">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <a href="orders.php" class="back-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px" fill="currentColor">
                                <path d="M400-80 0-480l400-400 71 71-329 329 329 329-71 71Z" />
                            </svg>
                            Back to Orders
                        </a>
                        <h1>Order #<?php echo htmlspecialchars($order['order_number']); ?></h1>
                    </div>
                </header>

                <div class="admin-content">
                    <div class="details-grid">
                        <!-- Left Column - Order Items -->
                        <div>
                            <div class="card">
                                <h2 class="card-title">Order Items</h2>

                                <div class="order-items-list">
                                    <?php foreach ($items as $item):
                                        $hasDiscount = !empty($item['original_price']) && floatval($item['original_price']) > floatval($item['product_price']);
                                        $discountPct = $hasDiscount ? round(((floatval($item['original_price']) - floatval($item['product_price'])) / floatval($item['original_price'])) * 100) : 0;
                                        $variants = array_filter([$item['selected_color'], $item['selected_size']]);
                                    ?>
                                        <div class="order-item-compact">
                                            <div class="order-item-compact-image">
                                                <?php if ($item['image_url']): ?>
                                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>"
                                                        alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                                <?php else: ?>
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960">
                                                        <path d="M200-640v440h560v-440H640v320l-160-80-160 80v-320H200Z" />
                                                    </svg>
                                                <?php endif; ?>
                                            </div>

                                            <div class="order-item-compact-details">
                                                <div class="order-item-compact-name"><?php echo htmlspecialchars($item['product_name']); ?></div>

                                                <?php if (!empty($variants)): ?>
                                                    <div class="order-item-compact-variant">
                                                        <?php echo htmlspecialchars(implode(' / ', $variants)); ?>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="order-item-compact-qty">Qty: <?php echo $item['quantity']; ?></div>
                                            </div>

                                            <div class="order-item-compact-price">
                                                <div class="order-item-compact-current">P<?php echo number_format($item['product_price'], 2); ?></div>
                                                <?php if ($hasDiscount): ?>
                                                    <div class="order-item-compact-original">P<?php echo number_format($item['original_price'], 2); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Price Breakdown -->
                                <div style="border-top: 1px solid #e2e8f0; margin-top: 1rem; padding-top: 1rem;">
                                    <?php
                                    $subtotal     = floatval($order['subtotal']);
                                    $shippingFee  = floatval($order['shipping_fee']);
                                    $tax          = floatval($order['tax_amount']);
                                    $total        = floatval($order['total_amount']);
                                    ?>

                                    <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; color: #64748b;">
                                        <span>Subtotal</span>
                                        <span>P<?php echo number_format($subtotal, 2); ?></span>
                                    </div>

                                    <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; color: #64748b;">
                                        <span>Shipping Fee</span>
                                        <?php if ($shippingFee == 0): ?>
                                            <span style="color: #22c55e;">FREE</span>
                                        <?php else: ?>
                                            <span>P<?php echo number_format($shippingFee, 2); ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($tax > 0): ?>
                                        <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; color: #64748b;">
                                            <span>Tax (12% VAT)</span>
                                            <span>P<?php echo number_format($tax, 2); ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-top: 2px solid #e2e8f0; margin-top: 0.5rem; font-weight: 700;">
                                        <span>Total</span>
                                        <span style="color: #3b82f6; font-size: 1.1rem;">P<?php echo number_format($total, 2); ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Shipping Information -->
                            <div class="card">
                                <h2 class="card-title">Shipping Information</h2>

                                <div style="display: flex; justify-content: space-between; padding: 0.6rem 0; border-bottom: 1px dashed #e2e8f0;">
                                    <span style="color: #64748b; font-size: 0.85rem;">Full Name</span>
                                    <span style="font-weight: 600; color: #1e293b; font-size: 0.9rem;"><?php echo htmlspecialchars($order['shipping_name']); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 0.6rem 0; border-bottom: 1px dashed #e2e8f0;">
                                    <span style="color: #64748b; font-size: 0.85rem;">Email</span>
                                    <span style="font-weight: 600; color: #1e293b; font-size: 0.9rem;"><?php echo htmlspecialchars($order['shipping_email']); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 0.6rem 0; border-bottom: 1px dashed #e2e8f0;">
                                    <span style="color: #64748b; font-size: 0.85rem;">Phone</span>
                                    <span style="font-weight: 600; color: #1e293b; font-size: 0.9rem;"><?php echo htmlspecialchars($order['shipping_phone']); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 0.6rem 0; border-bottom: 1px dashed #e2e8f0;">
                                    <span style="color: #64748b; font-size: 0.85rem;">Address</span>
                                    <span style="font-weight: 600; color: #1e293b; font-size: 0.9rem;"><?php echo htmlspecialchars($order['shipping_address']); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 0.6rem 0; border-bottom: 1px dashed #e2e8f0;">
                                    <span style="color: #64748b; font-size: 0.85rem;">City</span>
                                    <span style="font-weight: 600; color: #1e293b; font-size: 0.9rem;"><?php echo htmlspecialchars($order['shipping_city']); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 0.6rem 0; border-bottom: 1px dashed #e2e8f0;">
                                    <span style="color: #64748b; font-size: 0.85rem;">Postal Code</span>
                                    <span style="font-weight: 600; color: #1e293b; font-size: 0.9rem;"><?php echo htmlspecialchars($order['shipping_postal']); ?></span>
                                </div>
                                <?php if (!empty($order['shipping_country'])): ?>
                                    <div style="display: flex; justify-content: space-between; padding: 0.6rem 0; border-bottom: 1px dashed #e2e8f0;">
                                        <span style="color: #64748b; font-size: 0.85rem;">Country</span>
                                        <span style="font-weight: 600; color: #1e293b; font-size: 0.9rem;"><?php echo htmlspecialchars($order['shipping_country']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($order['tracking_number'])): ?>
                                    <div style="display: flex; justify-content: space-between; padding: 0.6rem 0;">
                                        <span style="color: #64748b; font-size: 0.85rem;">Tracking #</span>
                                        <span style="font-weight: 600; color: #1e293b; font-size: 0.9rem;"><?php echo htmlspecialchars($order['tracking_number']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($order['notes'])): ?>
                                <div class="card">
                                    <h2 class="card-title">Order Notes</h2>
                                    <p style="color: #64748b; margin: 0;"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Right Column - Order Summary -->
                        <div>
                            <div class="card">
                                <h2 class="card-title">Update Status</h2>
                                <form method="POST" action="orders.php">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(function_exists('generateCsrfToken') ? generateCsrfToken() : '', ENT_QUOTES); ?>">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                                        <select name="status" class="form-select">
                                            <?php foreach (['pending', 'processing', 'shipped', 'delivered', 'completed', 'cancelled'] as $s): ?>
                                                <option value="<?php echo $s; ?>" <?php echo $order['status'] === $s ? 'selected' : ''; ?>>
                                                    <?php echo ucfirst($s); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div style="display:flex; flex-direction:column; gap:.35rem;">
                                            <label style="font-size:.8rem; font-weight:600; color:#64748b;">Message to customer <span style="font-weight:400;">(optional)</span></label>
                                            <textarea name="admin_note" rows="3" class="form-select" style="resize:vertical; font-size:.875rem; padding:.6rem .75rem;" placeholder="e.g. Your package has been handed to JRS Express, tracking #12345."></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                                            Update Status
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <div class="card">
                                <h2 class="card-title">Customer Information</h2>

                                <div style="display: flex; justify-content: space-between; padding: 0.6rem 0; border-bottom: 1px dashed #e2e8f0;">
                                    <span style="color: #64748b; font-size: 0.85rem;">Username</span>
                                    <span style="font-weight: 600; color: #1e293b; font-size: 0.9rem;"><?php echo htmlspecialchars($order['username']); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 0.6rem 0; border-bottom: 1px dashed #e2e8f0;">
                                    <span style="color: #64748b; font-size: 0.85rem;">Email</span>
                                    <span style="font-weight: 600; color: #1e293b; font-size: 0.9rem;"><?php echo htmlspecialchars($order['email']); ?></span>
                                </div>
                                <?php if (!empty($order['phone'])): ?>
                                    <div style="display: flex; justify-content: space-between; padding: 0.6rem 0;">
                                        <span style="color: #64748b; font-size: 0.85rem;">Phone</span>
                                        <span style="font-weight: 600; color: #1e293b; font-size: 0.9rem;"><?php echo htmlspecialchars($order['phone']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="<?php echo $root; ?>/design/admin/admin.js"></script>
</body>

</html>