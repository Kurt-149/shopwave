<?php
// directory-protection.php
$requestPath = $_SERVER['REQUEST_URI'];
$scriptPath = $_SERVER['SCRIPT_NAME'];

if ($requestPath == dirname($scriptPath) . '/' || 
    $requestPath == dirname($scriptPath)) {
    
    header('HTTP/1.0 404 Not Found');
    header('Location: /index.php');
    exit;
}