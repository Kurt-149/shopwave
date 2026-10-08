<?php
require_once __DIR__ . '/../authentication/database.php';
require_once __DIR__ . '/../core/api-init.php';
header('Content-Type: application/json');

$productId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid product']);
    exit;
}

try {
    $pdo = getPdo();
    
    $stmt = $pdo->prepare("
        SELECT type, value, image_url 
        FROM product_variants 
        WHERE product_id = ? 
        ORDER BY type, display_order
    ");
    $stmt->execute([$productId]);
    $variants = $stmt->fetchAll();
    
    $colors = [];
    $sizes = [];
    
    foreach ($variants as $v) {
        if ($v['type'] === 'color') {
            $colors[] = [
                'name' => $v['value'], 
                'image' => $v['image_url']
            ];
        } else if ($v['type'] === 'size') {
            $sizes[] = $v['value'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'colors' => $colors,
        'sizes' => $sizes
    ]);
    
} catch (PDOException $e) {
    error_log("Get product variants error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}