<?php
$requestedFile = $_SERVER['SCRIPT_NAME'];

if (strpos($requestedFile, 'config.php') !== false || 
    strpos($requestedFile, 'database.php') !== false ||
    strpos($requestedFile, 'init.php') !== false) {
    header('HTTP/1.0 403 Forbidden');
    echo 'Access denied.';
    exit;
}