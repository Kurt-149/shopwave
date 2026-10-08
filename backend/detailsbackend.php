<?php

require_once dirname(__DIR__) . '/core/security.php';
require_once dirname(__DIR__) . '/core/slug-helper.php';
require_once __DIR__ . '/../authentication/database.php';

$csrfToken = generateCsrfToken();
$pdo = getPdo();

if (!isset($_SESSION['recently_viewed'])) {
    $_SESSION['recently_viewed'] = [];
}

$productId = 0;
$product = null;

if (isset($_GET['slug']) && !empty($_GET['slug'])) {
    $slug = trim($_GET['slug']);
    $stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.slug = ? AND p.status = 'active'");
    $stmt->execute([$slug]);
    $product = $stmt->fetch();
    if (!$product) {
        $extractedId = getIdFromSlug($slug);
        if ($extractedId) $productId = $extractedId;
    } else {
        $productId = $product['id'];
    }
}

if (!$product && isset($_GET['id'])) {
    $productId = filter_var($_GET['id'], FILTER_VALIDATE_INT);
}

if (!$product && $productId > 0) {
    $stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ? AND p.status = 'active'");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
}

if (!$product) {
    header('Location: shop.php?error=' . urlencode('Product not found'));
    exit;
}

if ($productId === false || $productId <= 0) {
    header('Location: shop.php');
    exit;
}

if ($productId > 0) {
    if (($key = array_search($productId, $_SESSION['recently_viewed'])) !== false) {
        unset($_SESSION['recently_viewed'][$key]);
    }
    array_unshift($_SESSION['recently_viewed'], $productId);
    $_SESSION['recently_viewed'] = array_slice($_SESSION['recently_viewed'], 0, 12);
}

try {
    $stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ? AND p.status = 'active'");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product) { header('Location: shop.php'); exit; }

    $vStmt = $pdo->prepare("SELECT type, value, image_url FROM product_variants WHERE product_id = ? ORDER BY type, display_order ASC");
    $vStmt->execute([$productId]);
    $allVariants = $vStmt->fetchAll();

    $product['colors'] = array_values(array_map(function($v) use ($product) {
        return ['name' => $v['value'], 'image_url' => $v['image_url'] ?: $product['image_url']];
    }, array_filter($allVariants, fn($v) => $v['type'] === 'color')));

    $product['sizes'] = array_values(array_column(array_filter($allVariants, fn($v) => $v['type'] === 'size'), 'value'));

    $colorOverflow = count($product['colors']) > 5;
    $sizeOverflow  = count($product['sizes']) > 6;

    $reviewsPerPage = 6;
    $reviewPage = isset($_GET['review_page']) ? max(1, min(1000, intval($_GET['review_page']))) : 1;
    $reviewOffset = ($reviewPage - 1) * $reviewsPerPage;

    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE product_id = ? AND status = 'approved'");
    $stmtCount->execute([$productId]);
    $totalReviews = $stmtCount->fetchColumn();
    $totalReviewPages = ceil($totalReviews / $reviewsPerPage);
    if ($reviewPage > $totalReviewPages && $totalReviewPages > 0) {
        $reviewPage = $totalReviewPages;
        $reviewOffset = ($reviewPage - 1) * $reviewsPerPage;
    }

    $stmtReviews = $pdo->prepare("
        SELECT r.*, u.username, u.profile_picture,
            (SELECT COUNT(*) FROM review_votes WHERE review_id = r.id AND vote_type = 'helpful') as helpful_count,
            (SELECT COUNT(*) FROM review_votes WHERE review_id = r.id AND vote_type = 'not_helpful') as not_helpful_count,
            CASE WHEN EXISTS (
                SELECT 1 FROM order_items oi 
                JOIN orders o ON oi.order_id = o.id 
                WHERE oi.product_id = r.product_id 
                AND o.user_id = r.user_id 
                AND o.status IN ('delivered', 'completed')
            ) THEN 1 ELSE 0 END as verified_purchase
        FROM reviews r LEFT JOIN users u ON r.user_id = u.id
        WHERE r.product_id = ? AND r.status = 'approved'
        ORDER BY r.created_at DESC LIMIT ? OFFSET ?
    ");
    $stmtReviews->execute([$productId, $reviewsPerPage, $reviewOffset]);
    $reviews = $stmtReviews->fetchAll();

    $stmtAvg = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as rating_count,
        SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
        SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
        SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
        SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
        SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
        FROM reviews WHERE product_id = ? AND status = 'approved'");
    $stmtAvg->execute([$productId]);
    $ratingData = $stmtAvg->fetch();

    $product['avg_rating']    = $ratingData['avg_rating'] ?? 0;
    $product['rating_count']  = $ratingData['rating_count'] ?? 0;
    $product['rating_breakdown'] = [5 => $ratingData['five_star'] ?? 0, 4 => $ratingData['four_star'] ?? 0, 3 => $ratingData['three_star'] ?? 0, 2 => $ratingData['two_star'] ?? 0, 1 => $ratingData['one_star'] ?? 0];

    try {
        $stmtSold = $pdo->prepare("SELECT COALESCE(SUM(oi.quantity), 0) as total_sold FROM order_items oi INNER JOIN orders o ON oi.order_id = o.id WHERE oi.product_id = ? AND o.status IN ('completed', 'delivered')");
        $stmtSold->execute([$productId]);
        $product['sold_count'] = $stmtSold->fetch()['total_sold'] ?? 0;
    } catch (PDOException $e) { $product['sold_count'] = 0; }

    $excludeIds = [$productId];
    if (!empty($_SESSION['recently_viewed'])) $excludeIds = array_merge($excludeIds, $_SESSION['recently_viewed']);
    if (isset($_SESSION['user_id'])) {
        $stmtCart = $pdo->prepare("SELECT product_id FROM cart WHERE user_id = ?");
        $stmtCart->execute([$_SESSION['user_id']]);
        $cartItems = $stmtCart->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($cartItems)) $excludeIds = array_merge($excludeIds, $cartItems);
    }
    $excludeIds = array_unique($excludeIds);
    $excludePlaceholders = implode(',', array_fill(0, count($excludeIds), '?'));

    $stmtRelated = $pdo->prepare("SELECT id, name, price, discount_price, image_url, category_id, brand FROM products WHERE category_id = ? AND id NOT IN ($excludePlaceholders) AND status = 'active' ORDER BY CASE WHEN stock > 0 THEN 0 ELSE 1 END, RAND() LIMIT 4");
    $stmtRelated->execute(array_merge([$product['category_id']], $excludeIds));
    $relatedProducts = $stmtRelated->fetchAll();

    $userHasReviewed = false;
    $userCanReview = false;
    
    if (isset($_SESSION['user_id'])) {
        $stmtCheckReview = $pdo->prepare("SELECT id FROM reviews WHERE product_id = ? AND user_id = ?");
        $stmtCheckReview->execute([$productId, $_SESSION['user_id']]);
        $userHasReviewed = $stmtCheckReview->fetch() !== false;
        
        $stmtCanReview = $pdo->prepare("
            SELECT COUNT(*) FROM order_items oi 
            JOIN orders o ON oi.order_id = o.id 
            WHERE oi.product_id = ? 
            AND o.user_id = ? 
            AND o.status IN ('delivered', 'completed')
        ");
        $stmtCanReview->execute([$productId, $_SESSION['user_id']]);
        $userCanReview = $stmtCanReview->fetchColumn() > 0;
    }
} catch (PDOException $e) {
    error_log("Database error in product details: " . $e->getMessage());
    header('Location: shop.php');
    exit;
}