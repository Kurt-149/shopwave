<?php
/**
 * Per colour/size stock.
 *
 * A product that has rows in product_variant_stock is sold per combination
 * (colour + size). A product without rows keeps using products.stock as one pool.
 * products.stock always equals the sum of its variant rows when they exist.
 *
 * All functions that lock rows must be called inside a transaction.
 */

function vsNormalize($value): string
{
    return trim((string) ($value ?? ''));
}

function vsProductUsesVariantStock(PDO $pdo, int $productId): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM product_variant_stock WHERE product_id = ? LIMIT 1");
    $stmt->execute([$productId]);
    return (bool) $stmt->fetchColumn();
}

/**
 * The row for one colour/size combination, or null when that combination is not sold.
 */
function vsFetchRow(PDO $pdo, int $productId, string $color, string $size, bool $lock = false): ?array
{
    $sql = "SELECT id, color, size, stock FROM product_variant_stock
            WHERE product_id = ? AND color = ? AND size = ?" . ($lock ? " FOR UPDATE" : "");
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$productId, $color, $size]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * How many units can be sold for this selection.
 * Returns ['available' => int, 'error' => string|null].
 */
function vsAvailability(PDO $pdo, int $productId, int $baseStock, string $color, string $size, bool $lock = false): array
{
    if (!vsProductUsesVariantStock($pdo, $productId)) {
        return ['available' => max(0, $baseStock), 'error' => null];
    }

    $row = vsFetchRow($pdo, $productId, $color, $size, $lock);
    if ($row === null) {
        return ['available' => 0, 'error' => 'That colour and size combination is not available. Please choose again.'];
    }

    return ['available' => (int) $row['stock'], 'error' => null];
}

/**
 * Human-readable stock message for a limit of $available units.
 */
function vsStockMessage(int $available): string
{
    return $available <= 0
        ? 'Sold out in this colour or size. Please choose another option.'
        : 'Only ' . $available . ' left in this colour or size.';
}

/**
 * Take $qty units from the chosen combination and from the product total.
 * Throws RuntimeException when there is not enough stock.
 */
function vsDeduct(PDO $pdo, int $productId, string $color, string $size, int $qty): void
{
    if ($qty <= 0) {
        throw new RuntimeException('Invalid quantity');
    }

    if (vsProductUsesVariantStock($pdo, $productId)) {
        $stmt = $pdo->prepare("UPDATE product_variant_stock SET stock = stock - ?
                               WHERE product_id = ? AND color = ? AND size = ? AND stock >= ?");
        $stmt->execute([$qty, $productId, $color, $size, $qty]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Insufficient stock for selected colour/size');
        }
    }

    $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
    $stmt->execute([$qty, $productId, $qty]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Insufficient product stock');
    }
}

/**
 * Keep products.stock equal to the sum of the product's variant rows.
 */
function vsSyncProductTotal(PDO $pdo, int $productId): void
{
    $stmt = $pdo->prepare("UPDATE products
        SET stock = (SELECT COALESCE(SUM(v.stock), 0) FROM product_variant_stock v WHERE v.product_id = ?)
        WHERE id = ?");
    $stmt->execute([$productId, $productId]);
}

/**
 * Change the colour and/or size of one cart line.
 *
 * $newColor / $newSize: the new value, or null to keep the current one.
 * If the new combination already exists in the cart, the two lines are merged.
 *
 * Returns ['ok' => bool, 'message' => string, 'merged' => bool, 'quantity' => int,
 *          'selected_color' => string, 'selected_size' => string]
 */
function vsChangeCartOptions(PDO $pdo, int $userId, int $cartId, ?string $newColor, ?string $newSize): array
{
    $stmt = $pdo->prepare("SELECT product_id FROM cart WHERE id = ? AND user_id = ?");
    $stmt->execute([$cartId, $userId]);
    $productId = $stmt->fetchColumn();
    if ($productId === false) {
        return ['ok' => false, 'message' => 'Cart item not found'];
    }
    $productId = (int) $productId;

    // Lock the product first, then the cart lines (same order as add-to-cart).
    $stmt = $pdo->prepare("SELECT id, stock FROM products WHERE id = ? FOR UPDATE");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product) {
        return ['ok' => false, 'message' => 'Product not found'];
    }

    $stmt = $pdo->prepare("SELECT id, quantity, selected_color, selected_size FROM cart WHERE id = ? AND user_id = ? FOR UPDATE");
    $stmt->execute([$cartId, $userId]);
    $line = $stmt->fetch();
    if (!$line) {
        return ['ok' => false, 'message' => 'Cart item not found'];
    }

    $currentColor = vsNormalize($line['selected_color']);
    $currentSize  = vsNormalize($line['selected_size']);
    $color = $newColor === null ? $currentColor : vsNormalize($newColor);
    $size  = $newSize  === null ? $currentSize  : vsNormalize($newSize);
    $quantity = (int) $line['quantity'];

    if ($color === $currentColor && $size === $currentSize) {
        return [
            'ok' => true, 'message' => 'No change', 'merged' => false, 'quantity' => $quantity,
            'selected_color' => $color, 'selected_size' => $size,
        ];
    }

    // Another line in the cart with the target combination, if any.
    $stmt = $pdo->prepare("SELECT id, quantity FROM cart
        WHERE user_id = ? AND product_id = ? AND id <> ?
          AND COALESCE(selected_color, '') = ? AND COALESCE(selected_size, '') = ?
        FOR UPDATE");
    $stmt->execute([$userId, $productId, $cartId, $color, $size]);
    $existing = $stmt->fetch() ?: null;

    $needed = $quantity + ($existing ? (int) $existing['quantity'] : 0);

    $availability = vsAvailability($pdo, $productId, (int) $product['stock'], $color, $size, true);
    if ($availability['error'] !== null) {
        return ['ok' => false, 'message' => $availability['error']];
    }
    if ($availability['available'] < $needed) {
        return ['ok' => false, 'message' => vsStockMessage((int) $availability['available'])];
    }

    if ($existing) {
        $pdo->prepare("UPDATE cart SET quantity = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$needed, $existing['id']]);
        $pdo->prepare("DELETE FROM cart WHERE id = ?")->execute([$cartId]);

        return [
            'ok' => true, 'message' => 'Merged with your existing item', 'merged' => true, 'quantity' => $needed,
            'selected_color' => $color, 'selected_size' => $size,
        ];
    }

    $pdo->prepare("UPDATE cart SET selected_color = ?, selected_size = ?, updated_at = NOW() WHERE id = ?")
        ->execute([$color !== '' ? $color : null, $size !== '' ? $size : null, $cartId]);

    return [
        'ok' => true, 'message' => 'Updated', 'merged' => false, 'quantity' => $quantity,
        'selected_color' => $color, 'selected_size' => $size,
    ];
}

function vsKey(string $color, string $size): string
{
    return $color . "\x1f" . $size;
}

/**
 * Every colour/size combination a product with these options must stock.
 * Returns [] when the product has no colour or size options.
 */
function vsBuildCombos(array $colorNames, array $sizeNames): array
{
    if (!$colorNames && !$sizeNames) {
        return [];
    }
    $colors = $colorNames ?: [''];
    $sizes  = $sizeNames  ?: [''];

    $combos = [];
    foreach ($colors as $color) {
        foreach ($sizes as $size) {
            $combos[] = ['color' => $color, 'size' => $size];
        }
    }
    return $combos;
}

/**
 * Reads the stock typed for each combination (JSON from the admin form).
 * Every combination needs a whole number of 0 or more.
 * Returns ['rows' => [['color', 'size', 'stock'], ...], 'total' => int, 'error' => string|null].
 */
function vsReadSubmittedStock(array $combos, string $json): array
{
    $submitted = json_decode($json, true);
    $byKey = [];
    if (is_array($submitted)) {
        foreach ($submitted as $item) {
            if (!is_array($item)) {
                continue;
            }
            $byKey[vsKey(trim((string) ($item['color'] ?? '')), trim((string) ($item['size'] ?? '')))] = $item['stock'] ?? null;
        }
    }

    $rows = [];
    $total = 0;
    foreach ($combos as $combo) {
        $raw = $byKey[vsKey($combo['color'], $combo['size'])] ?? null;
        if ($raw === null || $raw === '' || !is_numeric($raw) || (int) $raw != $raw || (int) $raw < 0) {
            return [
                'rows' => [], 'total' => 0,
                'error' => 'Enter a stock number (0 or more) for every colour and size.',
            ];
        }
        $stock = (int) $raw;
        $rows[] = ['color' => $combo['color'], 'size' => $combo['size'], 'stock' => $stock];
        $total += $stock;
    }

    return ['rows' => $rows, 'total' => $total, 'error' => null];
}

/**
 * Replace a product's per colour/size rows. An empty $rows list switches the
 * product back to shared stock (products.stock).
 */
function vsSaveCombos(PDO $pdo, int $productId, array $rows): void
{
    $pdo->prepare("DELETE FROM product_variant_stock WHERE product_id = ?")->execute([$productId]);

    $stmt = $pdo->prepare("INSERT INTO product_variant_stock (product_id, color, size, stock) VALUES (?, ?, ?, ?)");
    foreach ($rows as $row) {
        $stmt->execute([$productId, $row['color'], $row['size'], $row['stock']]);
    }
}

/**
 * Current rows for the admin form: [['color', 'size', 'stock'], ...].
 */
function vsLoadCombos(PDO $pdo, int $productId): array
{
    $stmt = $pdo->prepare("SELECT color, size, stock FROM product_variant_stock WHERE product_id = ? ORDER BY color, size");
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}