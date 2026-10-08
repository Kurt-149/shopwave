<?php
function generateSlug($text, $options = []) {
    $defaults = [
        'separator' => '-',
        'lowercase' => true,
        'max_length' => 100,
        'replacements' => [
            '&' => 'and',
            '@' => 'at',
            '%' => 'percent',
            '$' => 'dollar',
            '#' => 'hash',
            'P' => 'peso'
        ]
    ];
    
    $options = array_merge($defaults, $options);
    
    $slug = $options['lowercase'] ? mb_strtolower($text, 'UTF-8') : $text;
    
    foreach ($options['replacements'] as $search => $replace) {
        $slug = str_replace($search, $replace, $slug);
    }
    
    $slug = removeAccents($slug);
    
    $slug = preg_replace('/[^\p{L}\p{N}\s' . preg_quote($options['separator'], '/') . ']/u', '', $slug);
    
    $slug = preg_replace('/[\s-]+/', $options['separator'], $slug);
    
    $slug = trim($slug, $options['separator']);
    
    if (strlen($slug) > $options['max_length']) {
        $slug = substr($slug, 0, $options['max_length']);
        $slug = trim($slug, $options['separator']);
    }
    
    return $slug;
}

function removeAccents($string) {
    $accents = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ñ' => 'n', 'ç' => 'c',
        'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'Ñ' => 'N', 'Ç' => 'C'
    ];
    
    return strtr($string, $accents);
}

function generateProductSlug($productName, $productId, $categorySlug = '', $pdo = null) {
    $slug = generateSlug($productName, [
        'max_length' => 80,
        'replacements' => ['&' => 'and', '@' => 'at', '%' => 'percent']
    ]);
    
    $finalSlug = $categorySlug ? $categorySlug . '-' . $slug : $slug;
    $finalSlug .= '-' . $productId;
    
    if ($pdo) {
        $finalSlug = ensureUniqueSlug($pdo, $finalSlug, $productId);
    }
    
    return $finalSlug;
}

function ensureUniqueSlug($pdo, $slug, $excludeId = null) {
    $originalSlug = $slug;
    $counter = 1;
    
    while (true) {
        $sql = "SELECT id FROM products WHERE slug = ?";
        $params = [$slug];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        if (!$stmt->fetch()) {
            break;
        }
        
        if (strpos($originalSlug, '-') !== false) {
            $slug = preg_replace('/-\d+$/', '-' . $counter, $originalSlug);
        } else {
            $slug = $originalSlug . '-' . $counter;
        }
        $counter++;
    }
    
    return $slug;
}

function generateCategorySlug($categoryName) {
    return generateSlug($categoryName, [
        'max_length' => 50,
        'replacements' => ['&' => 'and', '+' => 'plus']
    ]);
}

function getIdFromSlug($slug) {
    if (preg_match('/-(\d+)$/', $slug, $matches)) {
        return (int)$matches[1];
    }
    return false;
}

function getProductUrl($product, $absolute = false) {
    $base = $absolute ? getBaseUrl() : '';
    
    if (!empty($product['slug'])) {
        return $base . '/public/product/' . urlencode($product['slug']);
    }
    
    if (!empty($product['id'])) {
        return $base . '/public/product-details.php?id=' . $product['id'];
    }
    
    return $base . '/public/shop.php';
}