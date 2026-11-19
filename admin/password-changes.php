<?php
// admin/password-changes.php
// Admin only: every password change, with counts so you can track them.

require_once dirname(__DIR__) . '/core/init.php';
require_once dirname(__DIR__) . '/core/session-handler.php';
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/authentication/database.php';
require_once dirname(__DIR__) . '/core/security.php';
requireAdmin();

$root    = BASE_URL;
$perPage = 50;
$methodLabels = [
    'profile'         => 'Profile page',
    'forgot-password' => 'Forgot password',
];

$error      = '';
$totals     = ['total' => 0, 'today' => 0, 'last_7_days' => 0, 'last_30_days' => 0];
$byMethod   = [];
$rows       = [];
$totalPages = 1;
$page       = max(1, (int) ($_GET['page'] ?? 1));

try {
    $pdo = getPdo();

    // CURDATE() and NOW() use the database clock, so "today" matches the stored times.
    $totals = $pdo->query("SELECT
            COUNT(*) AS total,
            COALESCE(SUM(changed_at >= CURDATE()), 0) AS today,
            COALESCE(SUM(changed_at >= NOW() - INTERVAL 7 DAY), 0) AS last_7_days,
            COALESCE(SUM(changed_at >= NOW() - INTERVAL 30 DAY), 0) AS last_30_days
        FROM password_changes")->fetch(PDO::FETCH_ASSOC);

    $byMethod = $pdo->query('SELECT method, COUNT(*) AS n FROM password_changes GROUP BY method')
                    ->fetchAll(PDO::FETCH_KEY_PAIR);

    $totalPages = max(1, (int) ceil(((int) $totals['total']) / $perPage));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $perPage;

    // $perPage and $offset are whole numbers made in this file, so they are safe in the SQL text.
    $rows = $pdo->query("SELECT pc.id, pc.user_id, pc.method, pc.ip_address, pc.changed_at,
                                u.username, u.email
                         FROM password_changes pc
                         LEFT JOIN users u ON u.id = pc.user_id
                         ORDER BY pc.changed_at DESC, pc.id DESC
                         LIMIT $perPage OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[password-changes] ' . $e->getMessage());
    $error = 'The password_changes table is missing. Create it first (see the SQL in the instructions).';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Changes - SHOPWAVE Admin</title>
    <link rel="stylesheet" href="<?php echo $root; ?>/design/main-layout.css">
    <link rel="stylesheet" href="<?php echo $root; ?>/design/admin/admin.css">
    <link rel="stylesheet" href="<?php echo $root; ?>/design/admin/adminCss.css">
    <link rel="stylesheet" href="<?php echo $root; ?>/design/admin/admin-table-scroll.css">
    <link rel="stylesheet" href="<?php echo asset('design/shopwave-theme.css'); ?>">
    <style>
        .changes-table { font-size: 0.85rem; width: 100%; }
        .changes-table th,
        .changes-table td { padding: 0.5rem 0.6rem; text-align: left; white-space: nowrap; }
        .card { overflow-x: auto; }
        .method-line { font-size: 0.85rem; color: var(--muted); margin-bottom: 0.75rem; }
        .error-box { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; padding: 0.75rem 1rem; border-radius: var(--radius); margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="container-admin">
        <div class="admin-layout">
            <?php $activeAdminPage = 'password-changes.php'; require __DIR__ . '/partials/admin-sidebar.php'; ?>

            <main class="admin-main">
                <header class="admin-header">
                    <div class="page-separate">
                        <h1 class="page-title">Password Changes</h1>
                    </div>
                </header>

                <div class="admin-content">
                    <div class="admin-content-wrapper">
                        <?php if ($error !== ''): ?>
                            <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-value"><?php echo number_format((int) $totals['total']); ?></div>
                                <div class="stat-label">All Time</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo number_format((int) $totals['today']); ?></div>
                                <div class="stat-label">Today</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo number_format((int) $totals['last_7_days']); ?></div>
                                <div class="stat-label">Last 7 Days</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo number_format((int) $totals['last_30_days']); ?></div>
                                <div class="stat-label">Last 30 Days</div>
                            </div>
                        </div>

                        <p class="method-line">
                            <?php foreach ($methodLabels as $key => $label): ?>
                                <?php echo htmlspecialchars($label); ?>: <?php echo number_format((int) ($byMethod[$key] ?? 0)); ?> &nbsp;·&nbsp;
                            <?php endforeach; ?>
                        </p>

                        <div class="card">
                            <table class="changes-table">
                                <thead>
                                    <tr>
                                        <th>Record</th>
                                        <th>User ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Method</th>
                                        <th>IP Address</th>
                                        <th>Changed At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $r): ?>
                                        <tr>
                                            <td><?php echo (int) $r['id']; ?></td>
                                            <td><?php echo (int) $r['user_id']; ?></td>
                                            <td><?php echo $r['username'] !== null ? htmlspecialchars($r['username']) : '(deleted user)'; ?></td>
                                            <td><?php echo htmlspecialchars($r['email'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($methodLabels[$r['method']] ?? $r['method']); ?></td>
                                            <td><?php echo htmlspecialchars($r['ip_address'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($r['changed_at']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($rows) && $error === ''): ?>
                                        <tr><td colspan="7">No password changes yet.</td></tr>
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