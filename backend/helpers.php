<?php
require_once __DIR__ . '/../core/api-init.php';
function getCartCount($userId, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) as total FROM cart WHERE user_id = ?");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error getting cart count: " . $e->getMessage());
        return 0;
    }
}

function formatPrice($price) {
    return 'P' . number_format(floatval($price), 2);
}

function getProductImage($imageUrl) {
    return !empty($imageUrl) ? htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') : '../images/placeholder.jpg';
}

function getStockStatus($stock) {
    if ($stock > 10) {
        return ['class' => 'in-stock', 'text' => 'In Stock (' . $stock . ')'];
    } elseif ($stock > 0) {
        return ['class' => 'low-stock', 'text' => 'Low Stock (' . $stock . ')'];
    } else {
        return ['class' => 'out-of-stock', 'text' => 'Out of Stock'];
    }
}

function calculateDiscount($original, $discounted) {
    if ($original > 0 && $discounted > 0 && $discounted < $original) {
        return round((($original - $discounted) / $original) * 100);
    }
    return 0;
}