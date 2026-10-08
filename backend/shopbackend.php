<?php

require_once __DIR__ . '/../authentication/database.php';

$pdo = getPdo();

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $search);
$category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 0;

if (strlen($search) > 100) $search = substr($search, 0, 100);

$allowed_sorts = ['newest', 'price_low', 'price_high', 'name'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sorts) ? $_GET['sort'] : 'newest';

if ($min_price < 0) $min_price = 0;
if ($max_price < 0) $max_price = 0;
if ($max_price > 0 && $max_price < $min_price) { $temp = $min_price; $min_price = $max_price; $max_price = $temp; }

$where_conditions = ["p.status = 'active'"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR p.brand LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}
if ($category > 0) { $where_conditions[] = "p.category_id = ?"; $params[] = $category; }
if ($min_price > 0) { $where_conditions[] = "COALESCE(p.discount_price, p.price) >= ?"; $params[] = $min_price; }
if ($max_price > 0) { $where_conditions[] = "COALESCE(p.discount_price, p.price) <= ?"; $params[] = $max_price; }

$where_clause = implode(' AND ', $where_conditions);

switch ($sort) {
    case 'price_low':  $order_by = 'CASE WHEN p.stock > 0 THEN 0 ELSE 1 END, COALESCE(p.discount_price, p.price) ASC, p.created_at DESC'; break;
    case 'price_high': $order_by = 'CASE WHEN p.stock > 0 THEN 0 ELSE 1 END, COALESCE(p.discount_price, p.price) DESC, p.created_at DESC'; break;
    case 'name':       $order_by = 'CASE WHEN p.stock > 0 THEN 0 ELSE 1 END, p.name ASC'; break;
    default:           $order_by = 'CASE WHEN p.stock > 0 THEN 0 ELSE 1 END, p.created_at DESC'; break;
}

try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $where_clause");
    $count_stmt->execute($params);
    $total_products = $count_stmt->fetchColumn();
    $total_pages = ceil($total_products / 12);
    $current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    if ($current_page > 1000) $current_page = 1;
    if ($current_page > $total_pages && $total_pages > 0) {
        $current_page = $total_pages;
    }
    $offset = ($current_page - 1) * 12;
    
    $stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE $where_clause ORDER BY $order_by LIMIT 12 OFFSET ?");
    $stmt->execute(array_merge($params, [$offset]));
    $products = $stmt->fetchAll();
    $categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    error_log("Database error in shop: " . $e->getMessage());
    $products = []; $categories = []; $total_products = 0; $total_pages = 0;
}

$query_params = $_GET;
unset($query_params['page']);
$query_string = http_build_query($query_params);