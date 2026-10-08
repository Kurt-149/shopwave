<?php
require_once __DIR__ . '/../core/init.php';

error_log("Logout - Session ID before destroy: " . session_id());

$_SESSION = array();

if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/', '', true, true);
}

session_destroy();

error_log("Logout - Session destroyed");

header("Location: " . getBaseUrl() . "/authentication/login-page.php?success=" . urlencode("You have been logged out successfully"));
exit;