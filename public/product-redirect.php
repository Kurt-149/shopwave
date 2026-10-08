<?php
session_start();
require_once dirname(__DIR__) . '/core/init.php';
require_once dirname(__DIR__) . '/authentication/database.php';
require_once dirname(__DIR__) . '/core/slug-helper.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    try {
        $pdo = getPdo();
        
        $stmt = $pdo->prepare("SELECT id, name, slug, category_id FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        
        if ($product) {
            if (!empty($product['slug'])) {
                $slug = $product['slug'];
            } else {
                $categorySlug = '';
                if ($product['category_id']) {
                    $catStmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
                    $catStmt->execute([$product['category_id']]);
                    $cat = $catStmt->fetch();
                    if ($cat) {
                        $categorySlug = generateCategorySlug($cat['name']);
                    }
                }
                
                $slug = generateProductSlug($product['name'], $product['id'], $categorySlug);
                
                $updateStmt = $pdo->prepare("UPDATE products SET slug = ? WHERE id = ?");
                $updateStmt->execute([$slug, $product['id']]);
            }
            
            $baseUrl = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
            header("Location: $baseUrl/public/product-details.php?slug=" . urlencode($slug), true, 301);
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error in product redirect: " . $e->getMessage());
    }
}

header("Location: /public/shop.php?error=" . urlencode("Product not found"));
exit;