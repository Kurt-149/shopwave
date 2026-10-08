<?php
require_once __DIR__ . '/core/init.php';

$request = $_SERVER['REQUEST_URI'];
$basePath = dirname($_SERVER['SCRIPT_NAME']);

if ($basePath != '/' && strpos($request, $basePath) === 0) {
    $request = substr($request, strlen($basePath));
}

$path = parse_url($request, PHP_URL_PATH);
$path = ltrim($path, '/');

$routes = [
    'product/([a-zA-Z0-9-]+)' => 'public/product-details.php?slug=$1',
    'category/([a-zA-Z0-9-]+)' => 'public/shop.php?category_slug=$1',
    'cart' => 'public/cart.php',
    'checkout' => 'public/checkout.php',
    'profile' => 'public/me-page.php',
    'order/([A-Z0-9-]+)' => 'public/order-success.php?order=$1',
    'shop' => 'public/shop.php',
    '' => 'index.php'
];

foreach ($routes as $pattern => $target) {
    if (preg_match('#^' . $pattern . '$#', $path, $matches)) {
        array_shift($matches);
        parse_str(parse_url($target, PHP_URL_QUERY) ?? '', $params);
        foreach ($matches as $index => $value) {
            $params['slug'] = $value;
        }
        $_GET = array_merge($_GET, $params);
        require_once $target;
        exit;
    }
}

// 404
require_once 'public/404.php';