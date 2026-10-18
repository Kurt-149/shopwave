<?php
function isLocalhost() {
    // Only the real connection address counts. The Host header is set by the visitor.
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
}

function getBaseUrl() {
    // Use the configured site address when it is set (APP_URL in .env).
    // Falling back to the Host header lets a visitor build links to any domain.
    if (!empty($_ENV['APP_URL'])) {
        return rtrim($_ENV['APP_URL'], '/');
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $protocol = 'https://';
    }
    return $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function asset($path) {
    $base = getBaseUrl();
    $path = ltrim($path, '/');
    return $base . '/' . $path;
}

function enforceHTTPS() {
    if (isLocalhost()) {
        return;
    }
    
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
               (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    
    if (!$isHttps) {
        header('Location: ' . preg_replace('#^http://#', 'https://', getBaseUrl()) . $_SERVER['REQUEST_URI']);
        exit;
    }
}