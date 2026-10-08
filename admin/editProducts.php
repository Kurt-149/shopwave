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
$errors = [];

$product_id = $_GET['id'] ?? null;
if (!$product_id || !is_numeric($product_id)) {
    header('Location: products.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    if (!$product) {
        header('Location: products.php');
        exit;
    }
    $vStmt = $pdo->prepare("SELECT type, value, image_url, display_order FROM product_variants WHERE product_id = ? ORDER BY type, display_order ASC");
    $vStmt->execute([$product_id]);
    $allVariants = $vStmt->fetchAll();
    $existingColors = array_values(array_filter($allVariants, fn($v) => $v['type'] === 'color'));
    $existingSizes  = implode(',', array_column(array_filter($allVariants, fn($v) => $v['type'] === 'size'), 'value'));
} catch (PDOException $e) {
    die("Database error");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name           = trim($_POST['name'] ?? '');
    $description    = trim($_POST['description'] ?? '');
    $price          = $_POST['price'] ?? '';
    $discount_price = trim($_POST['discount_price'] ?? '');
    $category_id    = $_POST['category_id'] ?? '';
    $subcategory    = trim($_POST['subcategory'] ?? '');
    $brand          = trim($_POST['brand'] ?? '');
    $stock          = $_POST['stock'] ?? '';
    $status         = $_POST['status'] ?? 'active';
    $sizes_raw      = trim($_POST['sizes'] ?? '');
    $colors_json    = $_POST['colors_data'] ?? '[]';
    $colors_data    = json_decode($colors_json, true) ?: [];

    if (empty($name))                         $errors[] = "Product name is required";
    if (empty($price) || !is_numeric($price)) $errors[] = "Valid price is required";
    if (empty($stock) || !is_numeric($stock)) $errors[] = "Valid stock quantity is required";
    if (empty($category_id))                  $errors[] = "Category is required";
    if (!empty($discount_price) && (!is_numeric($discount_price) || floatval($discount_price) >= floatval($price)))
        $errors[] = "Discount price must be less than regular price";

    $image_url  = $product['image_url'] ?: null;
    $upload_dir = __DIR__ . '/../images/products/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $fname = uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $fname)) {
                if ($image_url && strpos($image_url, '/images/products/') === 0 && file_exists(__DIR__ . '/' . $image_url))
                    unlink(__DIR__ . '/' . $image_url);
                $image_url = '/images/products/' . $fname;
            } else {
                $errors[] = "Failed to upload image";
            }
        } else {
            $errors[] = "Invalid image format";
        }
    }

    foreach ($colors_data as $i => &$colorEntry) {
        $fileKey = 'color_image_' . $i;
        if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $fname = uniqid('color_') . '.' . $ext;
                if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $upload_dir . $fname)) {
                    $colorEntry['image_url'] = '/images/products/' . $fname;
                }
            }
        }
        if (!isset($colorEntry['image_url'])) $colorEntry['image_url'] = null;
    }
    unset($colorEntry);

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $dp = !empty($discount_price) ? floatval($discount_price) : null;
            $pdo->prepare("UPDATE products SET name=?, description=?, price=?, discount_price=?, category_id=?, subcategory=?, brand=?, stock=?, image_url=?, status=?, updated_at=NOW() WHERE id=?")
                ->execute([$name, $description, $price, $dp, $category_id, $subcategory, $brand, $stock, $image_url, $status, $product_id]);
            $pdo->prepare("DELETE FROM product_variants WHERE product_id = ?")->execute([$product_id]);
            $variantStmt = $pdo->prepare("INSERT INTO product_variants (product_id, type, value, image_url, display_order) VALUES (?,?,?,?,?)");
            foreach ($colors_data as $i => $colorEntry) {
                if (!empty($colorEntry['name'])) {
                    $variantStmt->execute([$product_id, 'color', $colorEntry['name'], $colorEntry['image_url'] ?? null, $i]);
                }
            }
            if (!empty($sizes_raw)) {
                foreach (array_filter(array_map('trim', explode(',', $sizes_raw))) as $i => $s) {
                    $pdo->prepare("INSERT INTO product_variants (product_id, type, value, display_order) VALUES (?,?,?,?)")->execute([$product_id, 'size', $s, $i]);
                }
            }
            $pdo->commit();
            $_SESSION['success'] = "Product updated successfully!";
            header('Location: products.php');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }

    $existingColors = $colors_data;
    $existingSizes  = $sizes_raw;
}

try {
    $categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

$suggestedSizes  = ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', 'Free Size'];
$suggestedColors = ['Black', 'White', 'Gray', 'Navy', 'Red', 'Blue', 'Green', 'Brown', 'Beige', 'Pink', 'Yellow', 'Orange', 'Purple'];

$initialColorsJs = json_encode(array_values(array_map(fn($c) => [
    'name'      => is_array($c) ? ($c['name'] ?? $c['value'] ?? '') : ($c['value'] ?? ''),
    'image_url' => is_array($c) ? ($c['image_url'] ?? '') : ($c['image_url'] ?? ''),
], $existingColors)));
$initialSizesJs = json_encode(is_string($existingSizes)
    ? array_filter(array_map('trim', explode(',', $existingSizes)))
    : $existingSizes);
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
    <title>Edit Product - SHOPWAVE Admin</title>
    <link rel="stylesheet" href="<?php echo $root; ?>/design/main-layout.css">
    <link rel="stylesheet" href="<?php echo $root; ?>/design/admin/admin.css">
    <style>
        .form-card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid var(--border);
            max-width: 900px;
            margin: 0 auto;
        }
        .variant-section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text);
            padding: 0.75rem 0;
            border-bottom: 2px solid var(--primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .variant-section-title::before {
            content: '';
            display: inline-block;
            width: 4px;
            height: 1.2em;
            background: var(--primary);
            border-radius: 2px;
        }
        .form-group { margin-bottom: 1.5rem; }
        .form-label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text);
        }
        .form-label.required::after { content: ' *'; color: #ef4444; }
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border);
            border-radius: 0.5rem;
            font-size: 0.95rem;
            background: white;
            transition: all 0.15s;
            font-family: inherit;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }
        .form-textarea { min-height: 120px; resize: vertical; }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1rem;
        }
        .form-help { font-size: 0.8rem; color: var(--muted); margin-top: 0.25rem; }
        .form-actions { display: flex; gap: 1rem; margin-top: 2rem; }
        .current-image-wrapper {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }
        .current-image-thumb {
            width: 180px;
            height: 180px;
            border-radius: 0.5rem;
            overflow: hidden;
            border: 2px solid var(--border);
            position: relative;
            background: #f8fafc;
        }
        .current-image-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .current-image-view-btn {
            position: absolute;
            bottom: 8px; right: 8px;
            display: inline-flex; align-items: center; gap: 4px;
            padding: 0.4rem 0.75rem;
            background: rgba(0,0,0,0.7); color: white;
            border: none; border-radius: 999px;
            font-size: 0.7rem; font-weight: 600;
            cursor: pointer; backdrop-filter: blur(4px); transition: all 0.15s;
        }
        .current-image-view-btn:hover { background: rgba(0,0,0,0.9); transform: translateY(-1px); }
        .current-image-placeholder {
            width: 180px; height: 180px;
            border-radius: 0.5rem;
            border: 2px dashed var(--border);
            display: flex; align-items: center; justify-content: center;
            color: var(--muted); font-size: 0.9rem; background: #f8fafc;
        }
        .image-upload {
            display: block;
            border: 2px dashed var(--border);
            border-radius: 0.5rem;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.15s;
            background: #f8fafc;
        }
        .image-upload:hover { border-color: var(--primary); background: white; }
        .image-upload input[type="file"] {
            position: absolute;
            width: 1px; height: 1px;
            padding: 0; margin: -1px;
            overflow: hidden; opacity: 0;
        }
        .image-preview { margin-top: 1rem; display: none; text-align: center; }
        .image-preview img {
            max-width: 200px; max-height: 200px;
            border-radius: 0.5rem;
            border: 2px solid var(--border);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .color-variant-list { display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1rem; }
        .color-variant-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem;
            background: #f8fafc;
            border: 2px solid var(--border);
            border-radius: 0.5rem;
            animation: slideIn 0.2s ease;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .color-variant-thumb {
            width: 50px; height: 50px;
            border-radius: 0.375rem;
            border: 2px dashed var(--border);
            background: white;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; overflow: hidden; flex-shrink: 0;
        }
        .color-variant-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .color-variant-thumb svg { width: 24px; height: 24px; fill: #94a3b8; }
        .color-variant-name { flex: 1; font-weight: 600; font-size: 0.9rem; color: var(--text); }
        .color-variant-upload-label { font-size: 0.75rem; color: var(--primary); font-weight: 600; cursor: pointer; white-space: nowrap; }
        .color-variant-upload-label:hover { text-decoration: underline; }
        .color-variant-remove {
            background: none; border: none; color: #94a3b8;
            cursor: pointer; font-size: 1.2rem; padding: 0 0.5rem; transition: all 0.15s;
        }
        .color-variant-remove:hover { color: #ef4444; }
        .variant-input-row { display: flex; gap: 0.5rem; align-items: center; margin-bottom: 1rem; }
        .variant-text-input { flex: 1; }
        .variant-suggestions {
            display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;
            padding-top: 0.5rem; border-top: 1px solid var(--border);
        }
        .suggestions-label { font-size: 0.75rem; color: var(--muted); font-weight: 600; margin-right: 0.25rem; }
        .suggestion-chip {
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            border: 1.5px solid #d1d5db;
            background: white; color: #1e293b;
            font-size: 0.7rem; font-weight: 600;
            cursor: pointer; transition: all 0.15s;
        }
        .suggestion-chip:hover { border-color: var(--primary); background: var(--primary); color: white; }
        .suggestion-chip.chip-added {
            background: #1e293b; border-color: #1e293b; color: white;
            opacity: 0.5; cursor: default; pointer-events: none;
        }
        .variant-builder {
            background: #f8fafc; border: 2px solid var(--border);
            border-radius: 0.5rem; padding: 1rem;
            display: flex; flex-direction: column; gap: 0.75rem;
        }
        .variant-tags { display: flex; flex-wrap: wrap; gap: 0.5rem; min-height: 36px; }
        .variant-tag {
            display: inline-flex; align-items: center; gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            background: white; border: 2px solid #1e293b;
            border-radius: 999px; font-size: 0.7rem; font-weight: 600; color: #1e293b;
        }
        .variant-tag button {
            background: none; border: none; cursor: pointer;
            color: #1e293b; font-size: 1rem; padding: 0 0.25rem;
            display: flex; align-items: center; justify-content: center;
            border-radius: 50%; transition: all 0.15s;
        }
        .variant-tag button:hover { background: rgba(239,68,68,0.1); color: #ef4444; }
        .admin-image-lightbox {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.9);
            z-index: 9999;
            align-items: center; justify-content: center;
        }
        .admin-image-lightbox.open { display: flex; }
        .admin-image-lightbox img {
            max-width: 90vw; max-height: 90vh;
            object-fit: contain; border-radius: 0.5rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }
        .admin-lightbox-close {
            position: absolute; top: 20px; right: 20px;
            width: 40px; height: 40px; border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.3);
            background: rgba(0,0,0,0.5); color: white;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            backdrop-filter: blur(4px); transition: all 0.15s;
        }
        .admin-lightbox-close:hover { background: #ef4444; border-color: #ef4444; }
        .btn {
            padding: 0.75rem 1.5rem; border-radius: 0.5rem;
            font-weight: 600; font-size: 0.95rem; cursor: pointer;
            text-decoration: none; display: inline-flex;
            align-items: center; justify-content: center;
            gap: 0.5rem; border: none; transition: all 0.15s;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: 0 4px 8px rgba(59,130,246,0.3); }
        .btn-secondary { background: white; color: var(--text); border: 2px solid var(--border); }
        .btn-secondary:hover { border-color: var(--primary); color: var(--primary); }
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.85rem; }
        .back-btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            color: var(--primary); text-decoration: none;
            font-weight: 600; font-size: 0.95rem;
            padding: 0.5rem 1rem; border-radius: 0.5rem;
            background: #eff6ff; transition: all 0.15s;
        }
        .back-btn:hover { background: #dbeafe; }
        .alert { padding: 1rem 1.5rem; border-radius: 0.5rem; margin-bottom: 1.5rem; font-size: 0.95rem; }
        .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        @media (max-width: 768px) {
            .form-card { padding: 1.5rem; }
            .form-actions { flex-direction: row; }
            .form-actions .btn { width: 40%; }
            .variant-input-row { flex-direction: column; align-items: stretch; }
        }
        @media (max-width: 600px) {
            .form-card { padding: 1rem; }
            .form-group { margin-bottom: 1rem; }
            .form-label { font-size: 0.8rem; }
            .form-input, .form-select, .form-textarea { padding: 0.5rem 0.75rem; font-size: 0.875rem; }
            .form-grid { grid-template-columns: 1fr; gap: 0; }
            .variant-section-title { font-size: 0.9375rem; }
            .btn { padding: 0.5rem 1rem; font-size: 0.875rem; }
            .form-actions .btn { width: 48%; }
            .form-help { font-size: 0.7rem; }
            .alert { padding: 0.75rem 1rem; font-size: 0.875rem; }
        }
        @media (max-width: 400px) {
            .form-card { padding: 0.75rem; }
            .form-group { margin-bottom: 0.75rem; }
            .form-label { font-size: 0.75rem; margin-bottom: 0.3rem; }
            .form-input, .form-select, .form-textarea { padding: 0.4375rem 0.625rem; font-size: 0.8125rem; }
            .variant-section-title { font-size: 0.875rem; margin-bottom: 1rem; }
            .btn { padding: 0.4375rem 0.875rem; font-size: 0.8125rem; }
            .back-btn { font-size: 0.8125rem; padding: 0.375rem 0.75rem; }
            .image-upload { padding: 1rem; }
            .color-variant-card { padding: 0.5rem; gap: 0.5rem; }
            .color-variant-thumb { width: 40px; height: 40px; }
            .color-variant-name { font-size: 0.75rem; }
            .suggestion-chip { font-size: 0.625rem; padding: 0.2rem 0.5rem; }
            .form-help { font-size: 0.65rem; }
            .alert { padding: 0.625rem 0.75rem; font-size: 0.8125rem; margin-bottom: 1rem; }
        }
    </style>
</head>
<body>
    <div class="container-admin">
        <div class="admin-layout">
            <aside class="admin-sidebar">
                <div class="admin-logo">
                    SHOPWAVE
                    <div style="font-size:.75rem;color:var(--muted);font-weight:normal;">Admin Panel</div>
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
                    <div style="border-top:1px solid var(--border);margin:var(--space-md) 0;"></div>
                    <a href="<?php echo $root; ?>/index.php" class="admin-nav-item">
                        <span class="admin-nav-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f"><path d="M200-520q-33 0-56.5-23.5T120-600v-160q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v160q0 33-23.5 56.5T760-520H200Zm0-80h560v-160H200v160Zm0 480q-33 0-56.5-23.5T120-200v-160q0-33 23.5-56.5T200-440h560q33 0 56.5 23.5T840-360v160q0 33-23.5 56.5T760-120H200Zm0-80h560v-160H200v160Zm0-560v160-160Zm0 400v160-160Z"/></svg></span>
                        <span>View Store</span>
                    </a>
                    <a href="<?php echo $root; ?>/authentication/logout-page.php" class="admin-nav-item">
                        <span class="admin-nav-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f"><path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h280v80H200v560h280v80H200Zm440-160-55-58 102-102H360v-80h327L585-622l55-58 200 200-200 200Z"/></svg></span>
                        <span>Logout</span>
                    </a>
                </nav>
            </aside>

            <main class="admin-main">
                <header class="admin-header">
                    <div style="display:flex;align-items:center;gap:1rem;">
                        <a href="products.php" class="back-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px" fill="currentColor"><path d="M400-80 0-480l400-400 71 71-329 329 329 329-71 71Z"/></svg>
                            Back
                        </a>
                        <h1 style="font-size:1.1rem;margin:0;">Edit Product</h1>
                    </div>
                </header>

                <div class="admin-content">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <strong>Please fix the following errors:</strong>
                            <ul style="margin:0.5rem 0 0 1.5rem;">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="form-card">
                        <form method="POST" enctype="multipart/form-data" id="productForm">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(function_exists('generateCsrfToken') ? generateCsrfToken() : '', ENT_QUOTES); ?>">

                            <div class="variant-section-title">Product Info</div>

                            <div class="form-group">
                                <label class="form-label">Current Image</label>
                                <div class="current-image-wrapper">
                                    <?php if ($product['image_url']): ?>
                                        <div class="current-image-thumb">
                                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>"
                                                alt="<?php echo htmlspecialchars($product['name']); ?>">
                                            <button type="button" class="current-image-view-btn"
                                                onclick="openAdminLightbox('<?php echo htmlspecialchars($product['image_url'], ENT_QUOTES); ?>')">
                                                <svg xmlns="http://www.w3.org/2000/svg" height="14px" viewBox="0 -960 960 960" width="14px" fill="currentColor"><path d="M120-120v-270h80v150l504-504H554v-80h270v270h-80v-150L240-200h150v80H120Z"/></svg>
                                                View
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <div class="current-image-placeholder">No image uploaded</div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label required">Product Name</label>
                                <input type="text" name="name" class="form-input"
                                    value="<?php echo htmlspecialchars($product['name']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-textarea"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label required">Price (P)</label>
                                    <input type="number" name="price" class="form-input" step="0.01" min="0"
                                        value="<?php echo htmlspecialchars($product['price']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Discount Price (P) <span class="form-help">(optional)</span></label>
                                    <input type="number" name="discount_price" class="form-input" step="0.01" min="0"
                                        value="<?php echo htmlspecialchars($product['discount_price'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label required">Stock Quantity</label>
                                    <input type="number" name="stock" class="form-input" min="0"
                                        value="<?php echo htmlspecialchars($product['stock']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Brand</label>
                                    <input type="text" name="brand" class="form-input"
                                        value="<?php echo htmlspecialchars($product['brand'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label required">Category</label>
                                    <select name="category_id" class="form-select" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>"
                                                <?php echo $product['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Subcategory</label>
                                    <input type="text" name="subcategory" class="form-input"
                                        value="<?php echo htmlspecialchars($product['subcategory'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-group" style="max-width:300px;">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="active" <?php echo $product['status'] == 'active'       ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $product['status'] == 'inactive'     ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="out_of_stock" <?php echo $product['status'] == 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                                </select>
                            </div>

                            <div class="variant-section-title">Update Main Image</div>
                            <div class="form-help" style="margin-bottom:1rem;">Leave empty to keep current image.</div>

                            <div class="form-group">
                                <label class="image-upload" for="image-input">
                                    <div style="font-size:2rem;margin-bottom:0.5rem;">📷</div>
                                    <div style="font-weight:600;">Click to upload new image</div>
                                    <input type="file" id="image-input" name="image" accept="image/*">
                                </label>
                                <div class="image-preview" id="filePreview">
                                    <img id="filePreviewImg" src="" alt="New Image Preview">
                                </div>
                            </div>

                            <div class="variant-section-title">Color Variants</div>
                            <div class="form-help" style="margin-bottom:1rem;">Each color has its own image. Clicking a color on the product page switches to that image.</div>

                            <div class="form-group">
                                <div class="color-variant-list" id="colorVariantList"></div>
                                <div class="variant-input-row">
                                    <input type="text" id="colorInput" class="form-input variant-text-input"
                                        placeholder="Type a color name and press Enter...">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="addColorEntry()">+ Add Color</button>
                                </div>
                                <div class="variant-suggestions">
                                    <span class="suggestions-label">Quick add:</span>
                                    <?php foreach ($suggestedColors as $c): ?>
                                        <button type="button" class="suggestion-chip" onclick="addColorEntry('<?php echo $c; ?>')"><?php echo $c; ?></button>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="colors_data" id="colorsData" value="[]">
                            </div>

                            <div class="variant-section-title">Size Variants</div>

                            <div class="form-group">
                                <div class="variant-builder" id="sizeBuilder">
                                    <div class="variant-tags" id="sizeTags"></div>
                                    <div class="variant-input-row">
                                        <input type="text" id="sizeInput" class="form-input variant-text-input"
                                            placeholder="Type a size and press Enter or comma...">
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="addSizeFromInput()">Add</button>
                                    </div>
                                    <div class="variant-suggestions">
                                        <span class="suggestions-label">Quick add:</span>
                                        <?php foreach ($suggestedSizes as $s): ?>
                                            <button type="button" class="suggestion-chip" onclick="addSize('<?php echo $s; ?>')"><?php echo $s; ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <input type="hidden" name="sizes" id="sizesHidden" value="<?php echo htmlspecialchars(is_string($existingSizes) ? $existingSizes : implode(',', array_column(array_filter($allVariants ?? [], fn($v) => $v['type'] === 'size'), 'value'))); ?>">
                                <div class="form-help">Sizes shown on the product page</div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Update Product</button>
                                <a href="products.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div class="admin-image-lightbox" id="adminLightbox" onclick="closeAdminLightbox()">
        <button class="admin-lightbox-close" onclick="closeAdminLightbox()" type="button">
            <svg xmlns="http://www.w3.org/2000/svg" height="22px" viewBox="0 -960 960 960" width="22px" fill="currentColor"><path d="m256-200-56-56 224-224-224-224 56-56 224 224 224-224 56 56-224 224 224 224-56 56-224-224-224 224Z"/></svg>
        </button>
        <img src="" alt="Full product image" id="adminLightboxImg" onclick="event.stopPropagation()">
    </div>

    <script>
        window.INITIAL_COLORS = <?php echo $initialColorsJs; ?>;
        window.INITIAL_SIZES = <?php echo $initialSizesJs; ?>;
    </script>
    <script src="<?php echo $root; ?>/design/admin/product-variants.js"></script>
    <script src="<?php echo $root; ?>/design/admin/admin.js"></script>
    <script>
        function openAdminLightbox(src) {
            const lightbox = document.getElementById('adminLightbox');
            document.getElementById('adminLightboxImg').src = src;
            lightbox.classList.add('open');
            document.body.style.overflow = 'hidden';
        }
        function closeAdminLightbox() {
            document.getElementById('adminLightbox').classList.remove('open');
            document.body.style.overflow = '';
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeAdminLightbox();
        });
        document.getElementById('image-input')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('filePreviewImg').src = e.target.result;
                    document.getElementById('filePreview').style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>