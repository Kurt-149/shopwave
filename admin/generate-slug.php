<?php
require_once __DIR__ . '/../core/security.php';
requireCsrfPost();
// FIX: Removed session_start() here. init.php loads session-handler.php which
// starts the session with the correct DB handler and cookie params.
require_once dirname(__DIR__) . '/core/init.php';
require_once dirname(__DIR__) . '/authentication/database.php';
require_once dirname(__DIR__) . '/core/slug-helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('<h1>Unauthorized</h1><p>Admin access required.</p>');
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
    <title>Generate Product Slugs</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 900px; margin: 0 auto; }
        .success { color: green; }
        .error   { color: red; }
        .info    { color: blue; }
        .product { padding: 10px; border-bottom: 1px solid #ddd; }
        .btn     { padding: 10px 20px; background: #3b82f6; color: white; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Generate Product Slugs</h1>
    <p>This will create SEO-friendly URLs for all your products.</p>

    <?php
    if (isset($_POST['generate'])) {
        echo "<hr><h2>Processing...</h2>";

        $pdo = getPdo(); // FIX: was using global $pdo directly

        $stmt    = $pdo->query("
            SELECT p.id, p.name, p.slug, c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            ORDER BY p.id ASC
        ");
        $products = $stmt->fetchAll();

        $updated = 0;
        $skipped = 0;

        foreach ($products as $product) {
            echo "<div class='product'>";

            if (!empty($product['slug']) && !isset($_POST['force'])) {
                echo "<span class='info'>⏭️ Skipped:</span> {$product['name']} ";
                echo "<em>(already has slug: {$product['slug']})</em>";
                $skipped++;
            } else {
                $categorySlug = $product['category_name']
                    ? generateCategorySlug($product['category_name'])
                    : '';

                $slug = generateProductSlug($product['name'], $product['id'], $categorySlug);

                try {
                    $pdo->prepare("UPDATE products SET slug = ? WHERE id = ?")
                        ->execute([$slug, $product['id']]);
                    echo "<span class='success'>✅ Generated:</span> {$product['name']} → <strong>$slug</strong>";
                    $updated++;
                } catch (PDOException $e) {
                    echo "<span class='error'>Error:</span> {$product['name']} - {$e->getMessage()}";
                }
            }

            echo "</div>";
        }

        echo "<hr>";
        echo "<h2>Summary</h2>";
        echo "<p class='success'>✅ Updated: $updated products</p>";
        echo "<p class='info'>⏭️ Skipped: $skipped products</p>";
        echo "<p><a href='products.php'>← Back to Products</a></p>";

    } else {
    ?>
        <form method="POST">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(function_exists('generateCsrfToken') ? generateCsrfToken() : '', ENT_QUOTES); ?>">
            <p>
                <label>
                    <input type="checkbox" name="force" value="1">
                    Force regenerate ALL slugs (even if they already exist)
                </label>
            </p>
            <button type="submit" name="generate" class="btn">🚀 Generate Slugs Now</button>
        </form>
        <p><a href="products.php">← Back to Products</a></p>
    <?php
    }
    ?>
</body>
</html>
