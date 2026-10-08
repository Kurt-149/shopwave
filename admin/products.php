<?php
require_once __DIR__ . '/../core/init.php';
require_once __DIR__ . '/../core/session-handler.php';
require_once __DIR__ . '/../core/config.php';
$root = BASE_URL;
require_once __DIR__ . '/../authentication/database.php';
require_once __DIR__ . '/../core/security.php';
requireAdmin();

$pdo = getPdo();
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        header('Location: products.php?success=deleted');
        exit;
    } catch (PDOException $e) {
        $error = "Failed to delete product";
    }
}

$items_per_page = 8;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

$count_query = "SELECT COUNT(*) FROM products p WHERE 1=1";
$count_params = [];

if ($search) {
    $count_query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $count_params[] = "%$search%";
    $count_params[] = "%$search%";
}

if ($category) {
    $count_query .= " AND p.category_id = ?";
    $count_params[] = $category;
}

try {
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($count_params);
    $total_items = $count_stmt->fetchColumn();

    $total_pages = ceil($total_items / $items_per_page);
    $total_pages = max(1, (int)$total_pages);
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $items_per_page;
    $query = "SELECT p.*, c.name as category_name
              FROM products p
              LEFT JOIN categories c ON p.category_id = c.id
              WHERE 1=1";
    $params = [];

    if ($search) {
        $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($category) {
        $query .= " AND p.category_id = ?";
        $params[] = $category;
    }

    $query .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $items_per_page;
    $params[] = $offset;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    $categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $products = [];
    $categories = [];
    $total_items = 0;
    $total_pages = 1;
}

function getPaginationUrl($page, $search, $category)
{
    $params = ['page' => $page];
    if ($search) $params['search'] = $search;
    if ($category) $params['category'] = $category;
    return 'products.php?' . http_build_query($params);
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
    <title>Products Management - SHOPWAVE Admin</title>
    <link rel="stylesheet" href="<?php echo $root; ?>/design/main-layout.css">
    <link rel="stylesheet" href="<?php echo $root; ?>/design/admin/admin.css">
    <style>
        .admin-products-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.75rem;
            margin-bottom: 2rem;
        }

        .admin-product-card {
            background: white;
            border-radius: 0.5rem;
            overflow: hidden;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
        }

        .admin-product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.08);
            border-color: var(--primary-light);
        }

        .admin-product-image {
            width: 100%;
            aspect-ratio: 1/1;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .admin-product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .admin-product-image svg {
            width: 40px;
            height: 40px;
            fill: #94a3b8;
        }

        .admin-product-info {
            padding: 0.6rem;
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }

        .admin-product-category {
            font-size: 0.6rem;
            color: var(--primary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .admin-product-name {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text);
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .admin-product-price {
            font-weight: 700;
            color: var(--primary);
            font-size: 0.9rem;
            margin: 0.1rem 0;
        }

        .admin-product-stock {
            font-size: 0.65rem;
            padding: 0.15rem 0.4rem;
            border-radius: 20px;
            display: inline-block;
            width: fit-content;
            margin: 0.1rem 0;
        }

        .stock-high {
            background: rgba(34, 197, 94, 0.1);
            color: #16a34a;
        }

        .stock-low {
            background: rgba(251, 146, 60, 0.1);
            color: #ea580c;
        }

        .stock-out {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }

        .admin-product-actions {
            display: flex;
            gap: 0.3rem;
            margin-top: 0.3rem;
        }

        .admin-btn-edit,
        .admin-btn-delete {
            flex: 1;
            padding: 0.3rem 0.2rem;
            border-radius: 0.25rem;
            font-size: 0.6rem;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            transition: all 0.15s;
            cursor: pointer;
            border: none;
        }

        .admin-btn-edit {
            background: var(--primary);
            color: white;
        }

        .admin-btn-edit:hover {
            background: var(--primary-dark);
        }

        .admin-btn-delete {
            background: #ef4444;
            color: white;
        }

        .admin-btn-delete:hover {
            background: #dc2626;
        }

        .filters-section {
            background: white;
            padding: 1.25rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border);
        }

        .filter-row {
            display: flex;
            align-items: flex-end;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group .form-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--text);
        }

        .filter-group .form-input,
        .filter-group .form-select {
            width: 100%;
            padding: 0.6rem 0.75rem;
            border: 1px solid var(--border);
            border-radius: 0.375rem;
            font-size: 0.9rem;
            background: white;
        }

        .filter-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-shrink: 0;
        }

        .results-count {
            color: var(--muted);
            font-size: 0.9rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .filter-btn {
            padding: 0.6rem 1.5rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 0.375rem;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
        }

        .filter-btn:hover {
            background: var(--primary-dark);
        }

        @media (max-width: 1024px) {
            .admin-products-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .admin-products-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.5rem;
            }

            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                width: 100%;
            }

            .filter-actions {
                justify-content: space-between;
                width: 100%;
            }

            .admin-product-info {
                padding: 0.4rem;
            }

            .admin-product-name {
                font-size: 0.7rem;
            }

            .admin-product-price {
                font-size: 0.8rem;
            }

            .admin-btn-edit,
            .admin-btn-delete {
                font-size: 0.55rem;
                padding: 0.25rem 0.15rem;
            }
        }

        @media (max-width: 600px) {
            .admin-products-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.4rem;
            }

            .admin-product-info {
                padding: 0.3rem;
            }

            .admin-product-category {
                font-size: 0.5rem;
            }

            .admin-product-name {
                font-size: 0.65rem;
            }

            .admin-product-price {
                font-size: 0.7rem;
            }
        }

        @media (max-width: 480px) {
            .admin-products-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.3rem;
            }

            .admin-product-info {
                padding: 0.25rem;
            }

            .admin-product-name {
                font-size: 0.6rem;
            }

            .admin-product-price {
                font-size: 0.65rem;
            }

            .admin-btn-edit,
            .admin-btn-delete {
                font-size: 0.5rem;
                padding: 0.2rem 0.1rem;
            }
        }

        @media (max-width: 400px) {
            .admin-products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.35rem;
            }

            .admin-product-image {
                aspect-ratio: 4/3;
            }

            .admin-product-info {
                padding: 0.25rem;
                gap: 0.1rem;
            }

            .admin-product-category {
                font-size: 0.5rem;
            }

            .admin-product-name {
                font-size: 0.6rem;
            }

            .admin-product-price {
                font-size: 0.65rem;
                margin: 0;
            }

            .admin-product-stock {
                font-size: 0.5rem;
                padding: 0.1rem 0.3rem;
                margin: 0;
            }

            .admin-btn-edit,
            .admin-btn-delete {
                font-size: 0.5rem;
                padding: 0.2rem 0.1rem;
            }

            .admin-product-actions {
                margin-top: 0.2rem;
                gap: 0.2rem;
            }

            .filters-section {
                padding: 0.625rem;
                margin-bottom: 0.625rem;
            }

            .filter-group {
                min-width: unset;
            }

            .filter-group .form-input,
            .filter-group .form-select {
                padding: 0.375rem 0.5rem;
                font-size: 0.75rem;
            }

            .filter-group .form-label {
                font-size: 0.65rem;
            }

            .results-count {
                font-size: 0.7rem;
            }

            .filter-btn {
                padding: 0.375rem 0.75rem;
                font-size: 0.75rem;
            }
        }

        @media (max-width: 300px) {
            .admin-products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.25rem;
            }

            .admin-product-image {
                aspect-ratio: 4/3;
            }

            .admin-product-info {
                padding: 0.2rem;
                gap: 0.1rem;
            }

            .admin-product-category {
                font-size: 0.45rem;
            }

            .admin-product-name {
                font-size: 0.55rem;
            }

            .admin-product-price {
                font-size: 0.6rem;
                margin: 0;
            }

            .admin-product-stock {
                font-size: 0.45rem;
                padding: 0.1rem 0.25rem;
                margin: 0;
            }

            .admin-btn-edit,
            .admin-btn-delete {
                font-size: 0.45rem;
                padding: 0.15rem 0.1rem;
            }

            .admin-product-actions {
                margin-top: 0.15rem;
                gap: 0.15rem;
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
                    <a href="products.php" class="admin-nav-item active">
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
                    <div>
                        <h1 class="page-title">All Products</h1>
                    </div>
                    <a href="addProducts.php" class="btn btn-primary btn-add-product">+ Add New Product</a>
                </header>
                <div class="admin-content">
                    <div class="admin-content-wrapper">
                        <?php if (isset($_GET['success'])): ?>
                            <div class="alert alert-success">Product deleted successfully!</div>
                        <?php endif; ?>

                        <div class="filters-section">
                            <form method="GET" action="products.php">
                                <div class="filter-row">
                                    <div class="filter-group">
                                        <label class="form-label">Search Products</label>
                                        <input type="text" name="search" class="form-input"
                                            placeholder="Search by name..."
                                            value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                    <div class="filter-group">
                                        <label class="form-label">Category</label>
                                        <select name="category" class="form-select">
                                            <option value="">All Categories</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?php echo $cat['id']; ?>"
                                                    <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cat['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="filter-actions">
                                        <span class="results-count">Items: <?php echo number_format($total_items); ?> products</span>
                                        <button type="submit" class="filter-btn">Filter</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <?php if (empty($products)): ?>
                            <div class="card">
                                <div class="empty-state">
                                    <div class="empty-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                            <path d="M200-640v440h560v-440H640v320l-160-80-160 80v-320H200Zm0 520q-33 0-56.5-23.5T120-200v-499q0-14 4.5-27t13.5-24l50-61q11-14 27.5-21.5T250-840h460q18 0 34.5 7.5T772-811l50 61q9 11 13.5 24t4.5 27v499q0 33-23.5 56.5T760-120H200Zm16-600h528l-34-40H250l-34 40Zm184 80v190l80-40 80 40v-190H400Zm-200 0h560-560Z" />
                                        </svg></div>
                                    <h3>No Products Found</h3>
                                    <p style="color: var(--muted); margin: var(--space-md) 0;">
                                        <?php echo $search || $category ? 'Try adjusting your filters' : 'Get started by adding your first product'; ?>
                                    </p>
                                    <?php if (!$search && !$category): ?>
                                        <a href="addProducts.php" class="btn btn-primary">Add Product</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="admin-products-grid">
                                <?php foreach ($products as $product): ?>
                                    <div class="admin-product-card">
                                        <div class="admin-product-image">
                                            <?php if ($product['image_url']): ?>
                                                <img src="<?php echo htmlspecialchars($product['image_url']); ?>"
                                                    alt="<?php echo htmlspecialchars($product['name']); ?>">
                                            <?php else: ?>
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960">
                                                    <path d="M200-640v440h560v-440H640v320l-160-80-160 80v-320H200Z" />
                                                </svg>
                                            <?php endif; ?>
                                        </div>
                                        <div class="admin-product-info">
                                            <div class="admin-product-category"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></div>
                                            <div class="admin-product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                            <div class="admin-product-price">P<?php echo number_format($product['price'], 2); ?></div>
                                            <div class="admin-product-stock <?php echo $product['stock'] > 10 ? 'stock-high' : ($product['stock'] > 0 ? 'stock-low' : 'stock-out'); ?>">
                                                Stock: <?php echo $product['stock']; ?> units
                                            </div>
                                            <div class="admin-product-actions">
                                                <a href="editProducts.php?id=<?php echo $product['id']; ?>" class="admin-btn-edit">Edit</a>
                                                <a href="products.php?delete=<?php echo $product['id']; ?>" class="admin-btn-delete" onclick="return confirm('Delete this product?')">Delete</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="pagination-grid">
                                <?php if ($total_pages >= 1): ?>
                                    <div class="pagination">
                                        <span class="pagination-info">Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>

                                        <?php if ($current_page > 1): ?>
                                            <a href="<?php echo getPaginationUrl($current_page - 1, $search, $category); ?>" class="pagination-btn">‹ Prev</a>
                                        <?php else: ?>
                                            <span class="pagination-btn disabled">‹ Prev</span>
                                        <?php endif; ?>

                                        <?php
                                        $max_visible = 5;
                                        $start_page = max(1, $current_page - floor($max_visible / 2));
                                        $end_page = min($total_pages, $start_page + $max_visible - 1);

                                        if ($end_page - $start_page < $max_visible - 1) {
                                            $start_page = max(1, $end_page - $max_visible + 1);
                                        }

                                        if ($start_page > 1):
                                        ?>
                                            <a href="<?php echo getPaginationUrl(1, $search, $category); ?>" class="pagination-btn">1</a>
                                            <?php if ($start_page > 2): ?>
                                                <span class="pagination-btn disabled">...</span>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                            <?php if ($i === $current_page): ?>
                                                <span class="pagination-btn active"><?php echo $i; ?></span>
                                            <?php else: ?>
                                                <a href="<?php echo getPaginationUrl($i, $search, $category); ?>" class="pagination-btn"><?php echo $i; ?></a>
                                            <?php endif; ?>
                                        <?php endfor; ?>

                                        <?php if ($end_page < $total_pages): ?>
                                            <?php if ($end_page < $total_pages - 1): ?>
                                                <span class="pagination-btn disabled">...</span>
                                            <?php endif; ?>
                                            <a href="<?php echo getPaginationUrl($total_pages, $search, $category); ?>" class="pagination-btn"><?php echo $total_pages; ?></a>
                                        <?php endif; ?>

                                        <?php if ($current_page < $total_pages): ?>
                                            <a href="<?php echo getPaginationUrl($current_page + 1, $search, $category); ?>" class="pagination-btn">Next ›</a>
                                        <?php else: ?>
                                            <span class="pagination-btn disabled">Next ›</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
        <script src="<?php echo $root; ?>/design/admin/admin.js"></script>
        <script src="<?php echo $root; ?>/design/admin/adminJs.js"></script>
    </div>
</body>

</html>