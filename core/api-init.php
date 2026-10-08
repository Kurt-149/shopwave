<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/session-handler.php';
}

require_once __DIR__ . '/../authentication/database.php';
require_once __DIR__ . '/url-helper.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Access-Control-Allow-Credentials: true');