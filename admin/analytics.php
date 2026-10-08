<?php
require_once dirname(__DIR__) . '/core/init.php';
require_once dirname(__DIR__) . '/core/session-handler.php';
require_once dirname(__DIR__) . '/core/config.php';
$root = BASE_URL;
require_once dirname(__DIR__) . '/authentication/database.php';
require_once dirname(__DIR__) . '/core/security.php';
requireAdmin();

$pdo = getPdo();
$sales_summary = null;
$top_products = [];
$daily_orders = [];
$category_stats = [];
$status_dist = [];

try {
    $sales_summary = $pdo->query("
        SELECT 
            COUNT(*) as total_orders,
            SUM(total_amount) as total_revenue,
            AVG(total_amount) as avg_order_value,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN total_amount ELSE 0 END) as revenue_30days,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN total_amount ELSE 0 END) as revenue_7days
        FROM orders
        WHERE status != 'cancelled'
    ")->fetch();

    $top_products = $pdo->query("
        SELECT 
            p.name,
            p.price,
            SUM(oi.quantity) as total_sold,
            SUM(oi.subtotal) as total_revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        GROUP BY oi.product_id
        ORDER BY total_sold DESC
        LIMIT 5
    ")->fetchAll();

    $daily_orders = $pdo->query("
        SELECT 
            DATE(created_at) as order_date,
            COUNT(*) as order_count,
            SUM(total_amount) as daily_revenue
        FROM orders
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY order_date DESC
    ")->fetchAll();

    $category_stats = $pdo->query("
        SELECT 
            c.name as category_name,
            COUNT(DISTINCT p.id) as product_count,
            COALESCE(SUM(oi.quantity), 0) as items_sold,
            COALESCE(SUM(oi.subtotal), 0) as revenue
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id
        LEFT JOIN order_items oi ON p.id = oi.product_id
        GROUP BY c.id
        ORDER BY revenue DESC
    ")->fetchAll();

    $status_dist = $pdo->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM orders
        GROUP BY status
    ")->fetchAll();
    
} catch (PDOException $e) {
    error_log("Analytics error: " . $e->getMessage());
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
    <title>Analytics - SHOPWAVE Admin</title>
    <link rel="stylesheet" href="<?php echo $root; ?>/design/main-layout.css">
    <link rel="stylesheet" href="<?php echo $root; ?>/design/admin/admin.css">
    <link rel="stylesheet" href="<?php echo $root; ?>/design/admin/admin-table-scroll.css">
    <style>
        .grid-2 { gap: 1rem; }
        .grid-2 .card { overflow-x: auto; min-width: 0; }
        .data-table { font-size: 0.8rem; min-width: 300px; }
        .data-table th, .data-table td { padding: 0.5rem 0.6rem; }
        .card { overflow-x: auto; }
        .card .data-table { min-width: 400px; }
    </style>
</head>

<body>
    <div class="container-admin">
        <div class="admin-layout">
            <aside class="admin-sidebar">
                <div class="admin-logo">
                    SHOPWAVE
                    <div style="font-size: 0.75rem; color: var(--muted); font-weight: normal;">Admin Panel</div>
                </div>
                <nav class="admin-nav">
                    <a href="dashboard.php" class="admin-nav-item">
                        <span class="admin-nav-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f"><path d="M520-600v-240h320v240H520ZM120-440v-400h320v400H120Zm400 320v-400h320v400H520Zm-400 0v-240h320v240H120Zm80-400h160v-240H200v240Zm400 320h160v-240H600v240Zm0-480h160v-80H600v80ZM200-200h160v-80H200v80Zm160-320Zm240-160Zm0 240ZM360-280Z"/></svg></span>
                        <span>Dashboard</span>
                    </a>
                    <a href="products.php" class="admin-nav-item">
                        <span class="admin-nav-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f"><path d="M200-640v440h560v-440H640v320l-160-80-160 80v-320H200Zm0 520q-33 0-56.5-23.5T120-200v-499q0-14 4.5-27t13.5-24l50-61q11-14 27.5-21.5T250-840h460q18 0 34.5 7.5T772-811l50 61q9 11 13.5 24t4.5 27v499q0 33-23.5 56.5T760-120H200Zm16-600h528l-34-40H250l-34 40Zm184 80v190l80-40 80 40v-190H400Zm-200 0h560-560Z"/></svg></span>
                        <span>Products</span>
                    </a>
                    <a href="categories.php" class="admin-nav-item">
                        <span class="admin-nav-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f"><path d="m260-520 220-360 220 360H260ZM700-80q-75 0-127.5-52.5T520-260q0-75 52.5-127.5T700-440q75 0 127.5 52.5T880-260q0 75-52.5 127.5T700-80Zm-580-20v-320h320v320H120Zm580-60q42 0 71-29t29-71q0-42-29-71t-71-29q-42 0-71 29t-29 71q0 42 29 71t71 29Zm-500-20h160v-160H200v160Zm202-420h156l-78-126-78 126Zm78 0ZM360-340Zm340 80Z"/></svg></span>
                        <span>Categories</span>
                    </a>
                    <a href="orders.php" class="admin-nav-item">
                        <span class="admin-nav-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f"><path d="M160-160v-516L82-846l72-34 94 202h464l94-202 72 34-78 170v516H160Zm240-280h160q17 0 28.5-11.5T600-480q0-17-11.5-28.5T560-520H400q-17 0-28.5 11.5T360-480q0 17 11.5 28.5T400-440ZM240-240h480v-358H240v358Zm0 0v-358 358Z"/></svg></span>
                        <span>Orders</span>
                    </a>
                    <a href="customers.php" class="admin-nav-item">
                        <span class="admin-nav-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f"><path d="M367-527q-47-47-47-113t47-113q47-47 113-47t113 47q47 47 47 113t-47 113q-47 47-113 47t-113-47ZM160-160v-112q0-34 17.5-62.5T224-378q62-31 126-46.5T480-440q66 0 130 15.5T736-378q29 15 46.5 43.5T800-272v112H160Zm80-80h480v-32q0-11-5.5-20T700-306q-54-27-109-40.5T480-360q-56 0-111 13.5T260-306q-9 5-14.5 14t-5.5 20v32Zm296.5-343.5Q560-607 560-640t-23.5-56.5Q513-720 480-720t-56.5 23.5Q400-673 400-640t23.5 56.5Q447-560 480-560t56.5-23.5ZM480-640Zm0 400Z"/></svg></span>
                        <span>Customers</span>
                    </a>
                    <a href="analytics.php" class="admin-nav-item active">
                        <span class="admin-nav-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f"><path d="M280-280h80v-200h-80v200Zm320 0h80v-400h-80v400Zm-160 0h80v-120h-80v120Zm0-200h80v-80h-80v80ZM200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm0-80h560v-560H200v560Zm0-560v560-560Z"/></svg></span>
                        <span>Analytics</span>
                    </a>
                    <a href="reviews.php" class="admin-nav-item">
                        <span class="admin-nav-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f"><path d="M240-400h320v-80H240v80Zm0-120h480v-80H240v80Zm0-120h480v-80H240v80ZM80-80v-720q0-33 23.5-56.5T160-880h640q33 0 56.5 23.5T880-800v480q0 33-23.5 56.5T800-240H240L80-80Zm126-240h594v-480H160v525l46-45Zm-46 0v-480 480Z"/></svg></span>
                        <span>Reviews</span>
                    </a>
                    <a href="settings.php" class="admin-nav-item">
                        <span class="admin-nav-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f"><path d="m370-80-16-128q-13-5-24.5-12T307-235l-119 50L78-375l103-78q-1-7-1-13.5v-27q0-6.5 1-13.5L78-585l110-190 119 50q11-8 23-15t24-12l16-128h220l16 128q13 5 24.5 12t22.5 15l119-50 110 190-103 78q1 7 1 13.5v27q0 6.5-2 13.5l103 78-110 190-118-50q-11 8-23 15t-24 12L590-80H370Zm70-80h79l14-106q31-8 57.5-23.5T639-327l99 41 39-68-86-65q5-14 7-29.5t2-31.5q0-16-2-31.5t-7-29.5l86-65-39-68-99 42q-22-23-48.5-38.5T533-694l-13-106h-79l-14 106q-31 8-57.5 23.5T321-633l-99-41-39 68 86 64q-5 15-7 30t-2 32q0 16 2 31t7 30l-86 65 39 68 99-42q22 23 48.5 38.5T427-266l13 106Zm42-180q58 0 99-41t41-99q0-58-41-99t-99-41q-59 0-99.5 41T342-480q0 58 40.5 99t99.5 41Zm-2-140Z"/></svg></span>
                        <span>Settings</span>
                    </a>
                    <div style="border-top: 1px solid var(--border); margin: var(--space-md) 0;"></div>
                    <a href="<?php echo $root; ?>/index.php" class="admin-nav-item">
                        <span class="admin-nav-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f"><path d="M200-520q-33 0-56.5-23.5T120-600v-160q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v160q0 33-23.5 56.5T760-520H200Zm0-80h560v-160H200v160Zm0 480q-33 0-56.5-23.5T120-200v-160q0-33 23.5-56.5T200-440h560q33 0 56.5 23.5T840-360v160q0 33-23.5 56.5T760-120H200Zm0-80h560v-160H200v160Zm0-560v160-160Zm0 400v160-160Z"/></svg></span>
                        <span>View Store</span>
                    </a>
                   
                </nav>
            </aside>

            <main class="admin-main">
                <header class="admin-header">
                    <h1 style="margin: 0;">Analytics & Reports</h1>
                </header>

                <div class="admin-content">
                    <?php if ($sales_summary): ?>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-value">P<?php echo number_format($sales_summary['total_revenue']?? 0, 2); ?></div>
                                <div class="stat-label">Total Revenue</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo number_format($sales_summary['total_orders'] ?? 0, 2); ?></div>
                                <div class="stat-label">Total Orders</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value">P<?php echo number_format($sales_summary['avg_order_value'] ?? 0, 2); ?></div>
                                <div class="stat-label">Average Order Value</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value">P<?php echo number_format($sales_summary['revenue_30days'] ?? 0, 2); ?></div>
                                <div class="stat-label">Revenue (Last 30 Days)</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="grid-2">
                        <div class="card">
                            <h2 class="card-title">Top Selling Products</h2>
                            <?php if (empty($top_products)): ?>
                                <p style="text-align: center; color: var(--muted); padding: var(--space-lg);">
                                    No product sales yet
                                </p>
                            <?php else: ?>
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Sold</th>
                                            <th>Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_products as $product): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                                                <td><?php echo number_format($product['total_sold']); ?> units</td>
                                                <td><strong>P<?php echo number_format($product['total_revenue'], 2); ?></strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>

                        <div class="card">
                            <h2 class="card-title">Category Performance</h2>
                            <?php if (empty($category_stats)): ?>
                                <p style="text-align: center; color: var(--muted); padding: var(--space-lg);">
                                    No categories yet
                                </p>
                            <?php else: ?>
                                <?php
                                $max_revenue = max(array_column($category_stats, 'revenue'));
                                $max_revenue = $max_revenue > 0 ? $max_revenue : 1;
                                ?>
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>Products</th>
                                            <th>Sold</th>
                                            <th>Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($category_stats as $category): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($category['category_name']); ?></strong>
                                                    <div class="progress-bar">
                                                        <div class="progress-fill"
                                                            style="width: <?php echo ($category['revenue'] / $max_revenue * 100); ?>%"></div>
                                                    </div>
                                                </td>
                                                <td><?php echo number_format($category['product_count']); ?></td>
                                                <td><?php echo number_format($category['items_sold']); ?></td>
                                                <td><strong>P<?php echo number_format($category['revenue'], 2); ?></strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <h2 class="card-title">Orders (Last 7 Days)</h2>
                        <?php if (empty($daily_orders)): ?>
                            <p style="text-align: center; color: var(--muted); padding: var(--space-lg);">
                                No orders in the last 7 days
                            </p>
                        <?php else: ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Orders</th>
                                        <th>Revenue</th>
                                        <th>Avg. Order Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($daily_orders as $day): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y (D)', strtotime($day['order_date'])); ?></td>
                                            <td><?php echo number_format($day['order_count']); ?> orders</td>
                                            <td><strong>P<?php echo number_format($day['daily_revenue'], 2); ?></strong></td>
                                            <td>P<?php echo number_format($day['daily_revenue'] / $day['order_count'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($status_dist)): ?>
                        <div class="card">
                            <h2 class="card-title">Order Status Distribution</h2>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th>Count</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $total_orders = array_sum(array_column($status_dist, 'count'));
                                    foreach ($status_dist as $status):
                                    ?>
                                        <tr>
                                            <td><strong><?php echo ucfirst($status['status']); ?></strong></td>
                                            <td><?php echo number_format($status['count']); ?></td>
                                            <td>
                                                <?php
                                                $percentage = ($status['count'] / $total_orders) * 100;
                                                echo number_format($percentage, 1);
                                                ?>%
                                                <div class="progress-bar">
                                                    <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
        <script src="<?php echo $root; ?>/design/admin/admin.js"></script>
    </div>
</body>

</html>