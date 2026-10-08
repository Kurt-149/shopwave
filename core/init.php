<?php
ob_start();

ini_set('log_errors', 1);

require_once __DIR__ . '/url-helper.php';
enforceHTTPS();

require_once __DIR__ . '/error-handler.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

require_once __DIR__ . '/file-protection.php';
require_once __DIR__ . '/session-handler.php';