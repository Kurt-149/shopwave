<?php
require_once __DIR__ . '/./security.php';
if (!defined('SECURITY_ACCESS')) {
    define('SECURITY_ACCESS', true);
}

function requireLogin($redirectTo = '/authentication/login-page.php')
{
    if (!isLoggedIn()) {
        if (!empty($_SERVER['REQUEST_URI'])) {
            $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'];
        }
        safeRedirect($redirectTo . '?error=' . urlencode('Please login to continue'));
        exit;
    }
}

function requireAdmin($redirectTo = '/index.php')
{
    requireLogin();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        if (function_exists('logSecurityEvent')) {
            logSecurityEvent('Unauthorized admin access attempt', 'WARNING');
        }
        safeRedirect($redirectTo . '?error=' . urlencode('Admin access required'));
        exit;
    }
}

function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token']) ||
        empty($_SESSION['csrf_token_expiry']) ||
        $_SESSION['csrf_token_expiry'] < time()) {

        $_SESSION['csrf_token']        = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_expiry'] = time() + 3600;
    }

    return $_SESSION['csrf_token'];
}

function validateCsrfToken(string $token): bool
{
    if (empty($_SESSION['csrf_token']) ||
        empty($_SESSION['csrf_token_expiry']) ||
        $_SESSION['csrf_token_expiry'] < time()) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

function checkRateLimit(string $key, int $max_attempts = 5, int $window = 300): bool
{
    $cache_key = 'rate_limit_' . md5($key);

    if (function_exists('apcu_fetch')) {
        $data = apcu_fetch($cache_key);
        if ($data === false) {
            apcu_store($cache_key, ['attempts' => 1, 'time' => time()], $window);
            return true;
        }
        if (time() - $data['time'] > $window) {
            apcu_store($cache_key, ['attempts' => 1, 'time' => time()], $window);
            return true;
        }
        if ($data['attempts'] >= $max_attempts) return false;
        $data['attempts']++;
        apcu_store($cache_key, $data, $window);
        return true;
    }

    // File-based store: works without APCu and cannot be reset by clearing cookies.
    // Fails closed (blocks) if the storage folder is not writable.
    $dir = dirname(__DIR__) . '/storage/ratelimit';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
        error_log('[rate-limit] cannot create ' . $dir);
        return false;
    }

    $fp = @fopen($dir . '/' . $cache_key . '.json', 'c+');
    if ($fp === false) {
        error_log('[rate-limit] cannot open store for ' . $cache_key);
        return false;
    }

    flock($fp, LOCK_EX);
    $raw  = stream_get_contents($fp);
    $data = $raw ? json_decode($raw, true) : null;
    $now  = time();

    if (!is_array($data) || !isset($data['attempts'], $data['time']) || $now - $data['time'] > $window) {
        $data = ['attempts' => 0, 'time' => $now];
    }

    $allowed = $data['attempts'] < $max_attempts;
    if ($allowed) {
        $data['attempts']++;
    }

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data));
    flock($fp, LOCK_UN);
    fclose($fp);

    return $allowed;
}

function safeRedirect(string $location, array $allowed = []): void
{
    $defaultAllowed = [
        'index.php',
        '../index.php',
        'shop.php',
        '../public/shop.php',
        'login-page.php',
        '../authentication/login-page.php',
        'cart.php',
        'checkout.php',
        'me-page.php',
        '../public/me-page.php',
        'order-success.php',
        'product-details.php',
        'wishlist.php',
    ];

    $allowed      = array_merge($defaultAllowed, $allowed);
    $locationFile = basename(parse_url($location, PHP_URL_PATH));

    if (in_array($location, $allowed) ||
        in_array($locationFile, array_map('basename', $allowed))) {
        header("Location: $location");
        exit;
    }

    if (function_exists('logSecurityEvent')) {
        logSecurityEvent("Blocked redirect attempt to: $location", 'WARNING');
    }
    header('Location: ../index.php');
    exit;
}

function clean($data)
{
    if (is_array($data)) {
        return array_map('clean', $data);
    }
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

function validatePassword(string $password)
{
    $errors = [];
    if (strlen($password) < 8)  $errors[] = 'Password must be at least 8 characters long';
    if (strlen($password) > 72) $errors[] = 'Password must not exceed 72 characters';

    $strength = (int)preg_match('/[A-Z]/', $password)
              + (int)preg_match('/[a-z]/', $password)
              + (int)preg_match('/[0-9]/', $password)
              + (int)preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password);

    if ($strength < 3) {
        $errors[] = 'Password must contain at least 3 of: uppercase, lowercase, numbers, special characters';
    }

    return empty($errors) ? true : $errors;
}

function sanitizeImageUrl(string $url): string
{
    if (empty($url)) return '../images/placeholder.jpg';

    if (strpos($url, 'http') !== 0) {
        $url = str_replace(['../', '..\\', '//'], '', $url);
        $url = ltrim($url, '/');
        return clean('/images/' . $url);
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) return '../images/placeholder.jpg';

    $parsed          = parse_url($url);
    $allowedDomains  = ['images.unsplash.com', 'picsum.photos', 'via.placeholder.com', 'cdn.shopwave.com'];

    if (isset($parsed['host'])) {
        foreach ($allowedDomains as $domain) {
            if (strpos($parsed['host'], $domain) !== false) return clean($url);
        }
    }

    return '../images/placeholder.jpg';
}

function setSecurityHeaders(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    $nonce = bin2hex(random_bytes(16));
    $_SESSION['csp_nonce'] = $nonce;

    $csp = "default-src 'self'; "
         . "script-src 'self' 'nonce-$nonce' https://cdnjs.cloudflare.com; "
         . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
         . "font-src 'self' https://fonts.gstatic.com; "
         . "img-src 'self' data: https:; "
         . "connect-src 'self'; "
         . "frame-ancestors 'none';";

    header("Content-Security-Policy: $csp");
}

function setBackendSecurityHeaders(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
}

function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePhone(string $phone): bool
{
    $cleaned = preg_replace('/[\s\-\(\)]/', '', $phone);
    return (bool)preg_match('/^(\+?63|0)?9\d{9}$/', $cleaned);
}

function sanitizeFilename(string $filename): string
{
    $ext  = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $name = pathinfo($filename, PATHINFO_FILENAME);
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
    return uniqid() . '_' . substr($name, 0, 50) . '.' . $ext;
}

function validateFileUpload(
    array $file,
    array $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    int   $max_size      = 5242880
) {
    if (!isset($file['error']) || is_array($file['error'])) return 'Invalid file upload';
    if ($file['error'] !== UPLOAD_ERR_OK)                   return 'File upload failed';
    if ($file['size'] > $max_size)                          return 'File size exceeds limit (' . ($max_size / 1048576) . 'MB)';

    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowed_types)) return 'Invalid file type';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) return 'Invalid file extension';
    }

    return true;
}

function logSecurityEvent(string $event, string $level = 'INFO'): void
{
}

function isAjaxRequest(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function getClientIP(): string
{
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = trim(explode(',', $_SERVER[$header])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function requireApiAccess(): void
{
    $is_ajax  = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
             && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $is_fetch = isset($_SERVER['HTTP_SEC_FETCH_MODE'])
             && in_array($_SERVER['HTTP_SEC_FETCH_MODE'], ['cors', 'same-origin', 'navigate', 'no-cors']);
    $is_cli   = php_sapi_name() === 'cli';

    if (!$is_ajax && !$is_fetch && !$is_cli) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid access', 'code' => 'ACCESS_DENIED']);
        exit;
    }
}

/**
 * Reject state-changing POST requests without a valid CSRF token.
 * Accepts the token as form field `csrf_token` or header `X-CSRF-Token`.
 */
function requireCsrfPost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!validateCsrfToken((string) $token)) {
        http_response_code(403);
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['success' => false, 'message' => 'Security validation failed. Please refresh the page and try again.']);
        exit;
    }
}
