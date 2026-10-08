<?php
// FIX: Removed session_start() here. init.php loads session-handler.php which
// starts the session with the correct DB handler and cookie params.
require_once dirname(__DIR__) . '/core/init.php';
require_once dirname(__DIR__) . '/authentication/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Access Denied');
}

$generated = 0;
$skipped   = 0;

try {
    $pdo = getPdo(); // FIX: was using global $pdo directly

    $stmt     = $pdo->query("SELECT id FROM products WHERE product_code IS NULL OR product_code = ''");
    $products = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($products as $productId) {
        $productCode = 'PROD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));

        $stmt = $pdo->prepare("UPDATE products SET product_code = ? WHERE id = ?");
        if ($stmt->execute([$productCode, $productId])) {
            $generated++;
            echo " Product ID $productId → $productCode<br>";
        } else {
            $skipped++;
            echo " Failed: Product ID $productId<br>";
        }
    }

    echo "<br><strong>Done!</strong><br>";
    echo "Generated: $generated<br>";
    echo "Skipped: $skipped<br>";
    echo "<br><a href='products.php'>Back to Products</a><br>";
    echo "<br><strong style='color:red;'> DELETE THIS FILE NOW!</strong>";

} catch (PDOException $e) {
    die('Error: ' . $e->getMessage());
}
