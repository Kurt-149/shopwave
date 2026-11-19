<?php
// admin/partials/admin-sidebar.php
// Shared admin menu. Before including it, set $root (BASE_URL) and $activeAdminPage
// (for example 'users.php') so the right item is highlighted.
$activeAdminPage = $activeAdminPage ?? '';
$adminMenu = [
    'dashboard.php'        => 'Dashboard',
    'products.php'         => 'Products',
    'categories.php'       => 'Categories',
    'orders.php'           => 'Orders',
    'customers.php'        => 'Customers',
    'users.php'            => 'Users',
    'password-changes.php' => 'Password Changes',
    'analytics.php'        => 'Analytics',
    'reviews.php'          => 'Reviews',
    'settings.php'         => 'Settings',
];
?>
<aside class="admin-sidebar">
    <div class="admin-logo">
        SHOPWAVE
        <div style="font-size: 0.75rem; color: var(--muted); font-weight: normal;">Admin Panel</div>
    </div>
    <nav class="admin-nav">
        <?php foreach ($adminMenu as $file => $label): ?>
            <a href="<?php echo $file; ?>"
               class="admin-nav-item<?php echo $file === $activeAdminPage ? ' active' : ''; ?>">
                <span><?php echo htmlspecialchars($label); ?></span>
            </a>
        <?php endforeach; ?>
        <div style="border-top: 1px solid var(--border); margin: var(--space-md) 0;"></div>
        <a href="<?php echo htmlspecialchars($root ?? ''); ?>/index.php" class="admin-nav-item">
            <span>View Store</span>
        </a>
    </nav>
</aside>