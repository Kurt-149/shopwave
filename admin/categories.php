<?php
require_once dirname(__DIR__) . '/core/init.php';
require_once dirname(__DIR__) . '/core/session-handler.php';
require_once dirname(__DIR__) . '/core/config.php';
$root = BASE_URL;
require_once dirname(__DIR__) . '/authentication/database.php';
require_once dirname(__DIR__) . '/core/security.php';
requireCsrfPost();
requireAdmin();

$pdo = getPdo();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $image_url = trim($_POST['image_url'] ?? '');

    if (empty($name)) {
        $error = "Category name is required";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name, description, image_url) VALUES (?, ?, ?)");
            $stmt->execute([$name, $description, $image_url]);
            $success = "Category added successfully!";
        } catch (PDOException $e) {
            $error = "Failed to add category";
            error_log("Add category error: " . $e->getMessage());
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = $_POST['id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $image_url = trim($_POST['image_url'] ?? '');

    if (empty($name)) {
        $error = "Category name is required";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ?, image_url = ? WHERE id = ?");
            $stmt->execute([$name, $description, $image_url, $id]);
            $success = "Category updated successfully!";
        } catch (PDOException $e) {
            $error = "Failed to update category";
            error_log("Edit category error: " . $e->getMessage());
        }
    }
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE products SET category_id = NULL WHERE category_id = ?");
        $stmt->execute([$_GET['delete']]);
        
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        
        $pdo->commit();
        $success = "Category deleted successfully!";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Cannot delete category - it may have associated products";
        error_log("Delete category error: " . $e->getMessage());
    }
}

$items_per_page = 20;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

try {
    $total_items = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);
    
    $stmt = $pdo->prepare("
        SELECT c.*, COUNT(p.id) as product_count 
        FROM categories c 
        LEFT JOIN products p ON c.id = p.category_id 
        GROUP BY c.id 
        ORDER BY c.name
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$items_per_page, $offset]);
    $categories = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $categories = [];
    $total_items = 0;
    $total_pages = 0;
    error_log("Categories fetch error: " . $e->getMessage());
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
    <title>Categories Management - SHOPWAVE Admin</title>
    <link rel="stylesheet" href="<?php echo $root; ?>/design/main-layout.css">
    <link rel="stylesheet" href="<?php echo $root; ?>/design/admin/admin.css">
    <link rel="stylesheet" href="<?php echo $root; ?>/design/admin/adminCss.css">
    <link rel="stylesheet" href="<?php echo $root; ?>/design/admin/admin-table-scroll.css">
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
                    <a href="categories.php" class="admin-nav-item active">
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
                    <a href="analytics.php" class="admin-nav-item">
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
                    <div>
                        <h1 class="page-title">Categories Management</h1>
                        <div class="results-count">
                            <?php echo number_format($total_items); ?> categor<?php echo $total_items != 1 ? 'ies' : 'y'; ?>
                            <?php if ($total_pages >= 1): ?>
                                (Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>)
                            <?php endif; ?>
                        </div>
                    </div>
                    <button class="btn btn-primary" onclick="openAddModal()">+ Add Category</button>
                </header>

                <div class="admin-content">
                    <div class="admin-content-wrapper">
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <div class="card">
                            <?php if (empty($categories)): ?>
                                <div class="empty-state">
                                    <div style="font-size: 3rem; margin-bottom: var(--space-md);"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                                            <path d="m260-520 220-360 220 360H260ZM700-80q-75 0-127.5-52.5T520-260q0-75 52.5-127.5T700-440q75 0 127.5 52.5T880-260q0 75-52.5 127.5T700-80Zm-580-20v-320h320v320H120Zm580-60q42 0 71-29t29-71q0-42-29-71t-71-29q-42 0-71 29t-29 71q0 42 29 71t71 29Zm-500-20h160v-160H200v160Zm202-420h156l-78-126-78 126Zm78 0ZM360-340Zm340 80Z" />
                                        </svg></div>
                                    <h3>No Categories Yet</h3>
                                    <p style="color: var(--muted);">Start by adding your first category</p>
                                    <button class="btn btn-primary" onclick="openAddModal()">Add Category</button>
                                </div>
                            <?php else: ?>
                                <table class="categories-table" style="font-size:0.8rem;">
                                    <thead>
                                        <tr>
                                            <th style="padding:0.5rem 0.75rem;">Name</th>
                                            <th style="padding:0.5rem 0.75rem;">Description</th>
                                            <th style="padding:0.5rem 0.75rem;">Products</th>
                                            <th style="padding:0.5rem 0.75rem;">Created</th>
                                            <th style="padding:0.5rem 0.75rem;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($categories as $category): ?>
                                            <tr>
                                                <td style="padding:0.5rem 0.75rem;"><strong><?php echo htmlspecialchars($category['name']); ?></strong></td>
                                                <td style="padding:0.5rem 0.75rem;"><?php echo htmlspecialchars($category['description'] ?? 'No description'); ?></td>
                                                <td style="padding:0.5rem 0.75rem;white-space:nowrap;">
                                                    <span class="badge badge-primary">
                                                        <?php echo $category['product_count']; ?> products
                                                    </span>
                                                </td>
                                                <td style="padding:0.5rem 0.75rem;white-space:nowrap;"><?php echo date('M d, Y', strtotime($category['created_at'])); ?></td>
                                                <td style="padding:0.5rem 0.75rem;">
                                                    <button class="btn btn-secondary btn-sm"
                                                        onclick="openEditModal(<?php echo htmlspecialchars(json_encode($category)); ?>)">
                                                        Edit
                                                    </button>
                                                    <a href="?delete=<?php echo $category['id']; ?>&page=<?php echo $current_page; ?>"
                                                        class="btn btn-danger btn-sm"
                                                        onclick="return confirm('Delete this category? This will unlink all products in this category.')">
                                                        Delete
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <?php if ($total_pages > 1): ?>
                                    <div class="pagination">
                                        <span class="pagination-info">Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>

                                        <?php if ($current_page > 1): ?>
                                            <a href="?page=<?php echo $current_page - 1; ?>" class="pagination-btn">‹ Prev</a>
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
                                            <a href="?page=1" class="pagination-btn">1</a>
                                            <?php if ($start_page > 2): ?>
                                                <span class="pagination-btn disabled">...</span>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                            <?php if ($i === $current_page): ?>
                                                <span class="pagination-btn active"><?php echo $i; ?></span>
                                            <?php else: ?>
                                                <a href="?page=<?php echo $i; ?>" class="pagination-btn"><?php echo $i; ?></a>
                                            <?php endif; ?>
                                        <?php endfor; ?>

                                        <?php if ($end_page < $total_pages): ?>
                                            <?php if ($end_page < $total_pages - 1): ?>
                                                <span class="pagination-btn disabled">...</span>
                                            <?php endif; ?>
                                            <a href="?page=<?php echo $total_pages; ?>" class="pagination-btn"><?php echo $total_pages; ?></a>
                                        <?php endif; ?>

                                        <?php if ($current_page < $total_pages): ?>
                                            <a href="?page=<?php echo $current_page + 1; ?>" class="pagination-btn">Next ›</a>
                                        <?php else: ?>
                                            <span class="pagination-btn disabled">Next ›</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
        <div id="addModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Add New Category</h2>
                    <button class="close-btn" onclick="closeAddModal()">&times;</button>
                </div>
                <form method="POST">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(function_exists('generateCsrfToken') ? generateCsrfToken() : '', ENT_QUOTES); ?>">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label class="form-label">Category Name *</label>
                        <input type="text" name="name" class="form-input" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-textarea" maxlength="500"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Image URL</label>
                        <input type="url" name="image_url" class="form-input" placeholder="https://example.com/image.jpg">
                        <small style="color: var(--muted); font-size: 0.75rem;">Paste a direct image URL for the category card</small>
                    </div>
                    <div style="display: flex; gap: var(--space-sm); justify-content: flex-end;">
                        <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Category</button>
                    </div>
                </form>
            </div>
        </div>
        <div id="editModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Edit Category</h2>
                    <button class="close-btn" onclick="closeEditModal()">&times;</button>
                </div>
                <form method="POST">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(function_exists('generateCsrfToken') ? generateCsrfToken() : '', ENT_QUOTES); ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="form-group">
                        <label class="form-label">Category Name *</label>
                        <input type="text" name="name" id="edit_name" class="form-input" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_description" class="form-textarea" maxlength="500"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Image URL</label>
                        <input type="url" name="image_url" id="edit_image_url" class="form-input" placeholder="https://example.com/image.jpg">
                        <small style="color: var(--muted); font-size: 0.75rem;">Paste a direct image URL for the category card</small>
                    </div>
                    <div style="display: flex; gap: var(--space-sm); justify-content: flex-end;">
                        <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Category</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function openAddModal() {
                document.getElementById('addModal').classList.add('active');
            }

            function closeAddModal() {
                document.getElementById('addModal').classList.remove('active');
            }

            function openEditModal(category) {
                document.getElementById('edit_id').value = category.id;
                document.getElementById('edit_name').value = category.name;
                document.getElementById('edit_description').value = category.description || '';
                document.getElementById('edit_image_url').value = category.image_url || '';
                document.getElementById('editModal').classList.add('active');
            }

            function closeEditModal() {
                document.getElementById('editModal').classList.remove('active');
            }
            
            window.onclick = function(event) {
                if (event.target.classList.contains('modal')) {
                    event.target.classList.remove('active');
                }
            }
        </script>
        <script src="<?php echo $root; ?>/design/admin/admin.js"></script>
        <script src="<?php echo $root; ?>/design/admin/adminJs.js"></script>
    </div>
</body>

</html>