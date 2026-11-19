<?php
// admin/users.php
// Admin only: the number of accounts, and a list of ID, username and email.

require_once dirname(__DIR__) . '/core/init.php';
require_once dirname(__DIR__) . '/core/session-handler.php';
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/authentication/database.php';
require_once dirname(__DIR__) . '/core/security.php';
requireAdmin();

$root    = BASE_URL;
$pdo     = getPdo();
$perPage = 50;

// Counts per role, for example ['user' => 12, 'admin' => 1].
$roleCounts = $pdo->query('SELECT role, COUNT(*) AS n FROM users GROUP BY role')
                  ->fetchAll(PDO::FETCH_KEY_PAIR);
$totalUsers = array_sum($roleCounts);
$totalPages = max(1, (int) ceil($totalUsers / $perPage));

$page   = min(max(1, (int) ($_GET['page'] ?? 1)), $totalPages);
$offset = ($page - 1) * $perPage;

// $perPage and $offset are whole numbers made in this file, so they are safe in the SQL text.
$users = $pdo->query("SELECT id, username, email FROM users ORDER BY id ASC LIMIT $perPage OFFSET $offset")
             ->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - SHOPWAVE Admin</title>
    <link rel="stylesheet" href="<?php echo $root; ?>/design/main-layout.css">
    <link rel="stylesheet" href="<?php echo $root; ?>/design/admin/admin.css">
    <link rel="stylesheet" href="<?php echo $root; ?>/design/admin/adminCss.css">
    <link rel="stylesheet" href="<?php echo $root; ?>/design/admin/admin-table-scroll.css">
    <link rel="stylesheet" href="<?php echo asset('design/shopwave-theme.css'); ?>">
    <style>
        .users-table { font-size: 0.85rem; width: 100%; }
        .users-table th,
        .users-table td { padding: 0.5rem 0.6rem; text-align: left; white-space: nowrap; }
        .card { overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container-admin">
        <div class="admin-layout">
            <?php $activeAdminPage = 'users.php'; require __DIR__ . '/partials/admin-sidebar.php'; ?>

            <main class="admin-main">
                <header class="admin-header">
                    <div class="page-separate">
                        <h1 class="page-title">Users</h1>
                    </div>
                </header>

                <div class="admin-content">
                    <div class="admin-content-wrapper">
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-value"><?php echo number_format($totalUsers); ?></div>
                                <div class="stat-label">Total Accounts</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo number_format((int) ($roleCounts['user'] ?? 0)); ?></div>
                                <div class="stat-label">Customers</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo number_format((int) ($roleCounts['admin'] ?? 0)); ?></div>
                                <div class="stat-label">Admins</div>
                            </div>
                        </div>

                        <div class="card">
                            <table class="users-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $u): ?>
                                        <tr>
                                            <td><?php echo (int) $u['id']; ?></td>
                                            <td><?php echo htmlspecialchars($u['username'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($u['email'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($users)): ?>
                                        <tr><td colspan="3">No users yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="pagination">
                            <span class="pagination-info">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>" class="pagination-btn">‹ Prev</a>
                            <?php endif; ?>
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>" class="pagination-btn">Next ›</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>