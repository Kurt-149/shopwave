<?php
function isLocalhost() {
    $localhost = ['localhost', '127.0.0.1', '::1'];
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', $localhost) || 
           strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;
}

function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $protocol = 'https://';
    }
    
    $host = $_SERVER['HTTP_HOST'];
    
    // On localhost, just return protocol + host (no subfolder needed)
    if (isLocalhost()) {
        return $protocol . $host;
    }
    
    // On InfinityFree, files sit directly in /htdocs root — no subfolder
    return $protocol . $host;
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
        header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        exit;
    }
}