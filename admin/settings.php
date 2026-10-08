<?php
require_once dirname(__DIR__) . '/core/init.php';
require_once dirname(__DIR__) . '/core/session-handler.php';
require_once dirname(__DIR__) . '/authentication/database.php';
require_once dirname(__DIR__) . '/core/security.php';
requireCsrfPost();
requireAdmin();

$pdo = getPdo();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All password fields are required";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            if ($user && password_verify($current_password, $user['password'])) {
                // Update password
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed, $_SESSION['user_id']]);
                $success = "Password changed successfully!";
            } else {
                $error = "Current password is incorrect";
            }
        } catch (PDOException $e) {
            $error = "Failed to change password";
        }
    }
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch();

    $db_stats = [
        'products' => $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
        'orders' => $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
        'customers' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn(),
        'categories' => $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn(),
    ];
} catch (PDOException $e) {
    $admin = null;
    $db_stats = null;
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
    <title>Settings - SHOPWAVE Admin</title>
    <link rel="stylesheet" href="../design/main-layout.css">
    <link rel="stylesheet" href="../design/admin/admin.css">
    <style>
        .settings-container {
            max-width: 1200px;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-lg);
            margin-bottom: var(--space-lg);
        }

        .settings-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: var(--space-lg);
            box-shadow: var(--shadow-sm);
        }

        .settings-card h2 {
            font-size: var(--fs-md);
            font-weight: 600;
            margin-bottom: var(--space-md);
            color: var(--text);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .settings-card h2::before {
            font-size: 1.25rem;
        }

        .compact-form-group {
            margin-bottom: var(--space-md);
        }

        .compact-form-group:last-child {
            margin-bottom: 0;
        }

        .compact-label {
            font-size: var(--fs-xs);
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: var(--space-xs);
            display: block;
        }

        .compact-value {
            font-size: var(--fs-sm);
            color: var(--text);
            padding: var(--space-sm);
            background: var(--bg);
            border-radius: var(--radius);
            font-weight: 500;
        }

        .stats-grid-compact {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: var(--space-sm);
        }

        .stat-box {
            text-align: center;
            padding: var(--space-md);
            background: var(--bg);
            border-radius: var(--radius);
            border: 1px solid var(--border);
        }

        .stat-box-value {
            font-size: var(--fs-xl);
            font-weight: bold;
            color: var(--primary);
            margin-bottom: var(--space-xs);
        }

        .stat-box-label {
            font-size: var(--fs-xs);
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .password-form {
            display: grid;
            gap: var(--space-md);
        }

        .password-form .form-input {
            padding: var(--space-sm);
            font-size: var(--fs-sm);
        }

        .password-form .btn {
            justify-self: start;
        }

        @media (max-width: 968px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid-compact {
                grid-template-columns: repeat(2, 1fr);
            }
        }
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
                    <a href="orders.php" class="admin-nav-item">
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
                    <a href="reviews.php" class="admin-nav-item active">
                        <span class="admin-nav-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                <path d="M240-400h320v-80H240v80Zm0-120h480v-80H240v80Zm0-120h480v-80H240v80ZM80-80v-720q0-33 23.5-56.5T160-880h640q33 0 56.5 23.5T880-800v480q0 33-23.5 56.5T800-240H240L80-80Zm126-240h594v-480H160v525l46-45Zm-46 0v-480 480Z" />
                            </svg></span>
                        <span>Reviews</span>
                    </a>
                    <a href="settings.php" class="admin-nav-item active">
                        <span class="admin-nav-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                <path d="m370-80-16-128q-13-5-24.5-12T307-235l-119 50L78-375l103-78q-1-7-1-13.5v-27q0-6.5 1-13.5L78-585l110-190 119 50q11-8 23-15t24-12l16-128h220l16 128q13 5 24.5 12t22.5 15l119-50 110 190-103 78q1 7 1 13.5v27q0 6.5-2 13.5l103 78-110 190-118-50q-11 8-23 15t-24 12L590-80H370Zm70-80h79l14-106q31-8 57.5-23.5T639-327l99 41 39-68-86-65q5-14 7-29.5t2-31.5q0-16-2-31.5t-7-29.5l86-65-39-68-99 42q-22-23-48.5-38.5T533-694l-13-106h-79l-14 106q-31 8-57.5 23.5T321-633l-99-41-39 68 86 64q-5 15-7 30t-2 32q0 16 2 31t7 30l-86 65 39 68 99-42q22 23 48.5 38.5T427-266l13 106Zm42-180q58 0 99-41t41-99q0-58-41-99t-99-41q-59 0-99.5 41T342-480q0 58 40.5 99t99.5 41Zm-2-140Z" />
                            </svg></span>
                        <span>Settings</span>
                    </a>
                    <div style="border-top: 1px solid var(--border); margin: var(--space-md) 0;"></div>
                    <a href="/index.php" class="admin-nav-item">
                        <span class="admin-nav-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                <path d="M200-520q-33 0-56.5-23.5T120-600v-160q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v160q0 33-23.5 56.5T760-520H200Zm0-80h560v-160H200v160Zm0 480q-33 0-56.5-23.5T120-200v-160q0-33 23.5-56.5T200-440h560q33 0 56.5 23.5T840-360v160q0 33-23.5 56.5T760-120H200Zm0-80h560v-160H200v160Zm0-560v160-160Zm0 400v160-160Z" />
                            </svg></span>
                        <span>View Store</span>
                    </a>
                </nav>
            </aside>
            <main class="admin-main">
                <header class="admin-header">
                    <h1 style="margin: 0;">Settings</h1>
                </header>

                <div class="admin-content">
                    <div class="settings-container">
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <div class="settings-grid">
                            <?php if ($admin): ?>
                                <div class="settings-card">
                                    <h2>Admin Profile</h2>
                                    <div class="compact-form-group">
                                        <span class="compact-label">Username</span>
                                        <div class="compact-value"><?php echo htmlspecialchars($admin['username']); ?></div>
                                    </div>
                                    <div class="compact-form-group">
                                        <span class="compact-label">Email</span>
                                        <div class="compact-value"><?php echo htmlspecialchars($admin['email']); ?></div>
                                    </div>
                                    <div class="compact-form-group">
                                        <span class="compact-label">Role</span>
                                        <div class="compact-value">Administrator</div>
                                    </div>
                                    <div class="compact-form-group">
                                        <span class="compact-label">Member Since</span>
                                        <div class="compact-value"><?php echo date('F d, Y', strtotime($admin['created_at'])); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="settings-card">
                                <h2>Change Password</h2>
                                <form method="POST" class="password-form">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(function_exists('generateCsrfToken') ? generateCsrfToken() : '', ENT_QUOTES); ?>">
                                    <input type="hidden" name="action" value="change_password">
                                    <div>
                                        <label class="compact-label">Current Password</label>
                                        <input type="password" name="current_password" class="form-input" required>
                                    </div>
                                    <div>
                                        <label class="compact-label">New Password</label>
                                        <input type="password" name="new_password" class="form-input"
                                            minlength="6" required>
                                        <small style="color: var(--muted); font-size: var(--fs-xs); display: block; margin-top: var(--space-xs);">
                                            Minimum 6 characters
                                        </small>
                                    </div>
                                    <div>
                                        <label class="compact-label">Confirm New Password</label>
                                        <input type="password" name="confirm_password" class="form-input" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Update Password</button>
                                </form>
                            </div>
                        </div>
                        <?php if ($db_stats): ?>
                            <div class="settings-card">
                                <h2><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                        <path d="M520-600v-240h320v240H520ZM120-440v-400h320v400H120Zm400 320v-400h320v400H520Zm-400 0v-240h320v240H120Zm80-400h160v-240H200v240Zm400 320h160v-240H600v240Zm0-480h160v-80H600v80ZM200-200h160v-80H200v80Zm160-320Zm240-160Zm0 240ZM360-280Z" />
                                    </svg> Database Statistics</h2>
                                <div class="stats-grid-compact">
                                    <div class="stat-box">
                                        <div class="stat-box-value"><?php echo number_format($db_stats['products']); ?></div>
                                        <div class="stat-box-label">Products</div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="stat-box-value"><?php echo number_format($db_stats['orders']); ?></div>
                                        <div class="stat-box-label">Orders</div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="stat-box-value"><?php echo number_format($db_stats['customers']); ?></div>
                                        <div class="stat-box-label">Customers</div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="stat-box-value"><?php echo number_format($db_stats['categories']); ?></div>
                                        <div class="stat-box-label">Categories</div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="settings-card">
                            <h2> System Information</h2>
                            <div class="stats-grid-compact">
                                <div class="stat-box">
                                    <div class="stat-box-value" style="font-size: var(--fs-md);"><?php echo phpversion(); ?></div>
                                    <div class="stat-box-label">PHP Version</div>
                                </div>
                                <div class="stat-box">
                                    <div class="stat-box-value" style="font-size: var(--fs-xs);">
                                        <?php
                                        $server = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
                                        echo strlen($server) > 15 ? substr($server, 0, 15) . '...' : $server;
                                        ?>
                                    </div>
                                    <div class="stat-box-label">Server</div>
                                </div>
                                <div class="stat-box">
                                    <div class="stat-box-value" style="font-size: var(--fs-md);">MySQL</div>
                                    <div class="stat-box-label">Database</div>
                                </div>
                                <div class="stat-box">
                                    <div class="stat-box-value" style="font-size: var(--fs-md);">v1.0</div>
                                    <div class="stat-box-label">SHOPWAVE</div>
                                </div>
                            </div>
                        </div>
                        <div class="settings-card" style="border: 2px solid #fee2e2; background: #fff5f5;">
                            <h2 style="color: #991b1b;">
                                <svg xmlns="http://www.w3.org/2000/svg" height="22px" viewBox="0 -960 960 960" width="22px" fill="#991b1b">
                                    <path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h280v80H200v560h280v80H200Zm440-160-55-58 102-102H360v-80h327L585-622l55-58 200 200-200 200Z"/>
                                </svg>
                                Session
                            </h2>
                            <p style="font-size: 0.875rem; color: var(--muted); margin-bottom: 1.25rem;">
                                Sign out of your admin account on this device.
                            </p>
                            <a href="<?php echo isset($root) ? $root : '..'; ?>/authentication/logout-page.php"
                               class="btn btn-danger"
                               onclick="return confirm('Are you sure you want to logout?')"
                               style="display:inline-flex;align-items:center;gap:0.5rem;text-decoration:none;">
                                <svg xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px" fill="currentColor">
                                    <path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h280v80H200v560h280v80H200Zm440-160-55-58 102-102H360v-80h327L585-622l55-58 200 200-200 200Z"/>
                                </svg>
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
        <script src="../design/admin/admin.js"></script>
    </div>
</body>

</html>