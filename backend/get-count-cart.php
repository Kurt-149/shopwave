<?php

require_once __DIR__ . '/../core/api-init.php';
require_once __DIR__ . '/../authentication/database.php';
$pdo = getPdo();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'cart_count' => 0]);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM cart WHERE user_id = ?");
    $stmt->execute([$userId]);
    $cartTotal = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'cart_count' => (int)$cartTotal
    ]);
} catch (PDOException $e) {
    error_log("Get cart count error: " . $e->getMessage());
    echo json_encode(['success' => false, 'cart_count' => 0]);
}