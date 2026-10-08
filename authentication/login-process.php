<?php
require_once __DIR__ . '/../core/init.php';
require_once __DIR__ . '/../core/session-handler.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/url-helper.php';

setSecurityHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    safeRedirect('login-page.php');
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    header("Location: login-page.php?error=" . urlencode("Security validation failed. Please try again."));
    exit;
}

$usernameOrEmail = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($usernameOrEmail) || empty($password)) {
    header("Location: login-page.php?error=" . urlencode("All fields are required"));
    exit;
}

if (!checkRateLimit('login_' . $usernameOrEmail, 5, 300)) {
    header("Location: login-page.php?error=" . urlencode("Too many login attempts. Please try again in 5 minutes."));
    exit;
}

try {
    $pdo = getPdo();
    
    $stmt = $pdo->prepare(
        "SELECT id, username, email, password, role FROM users 
         WHERE username = ? OR email = ? LIMIT 1"
    );
    $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !password_verify($password, $user['password'])) {
        header("Location: login-page.php?error=" . urlencode("Invalid username or password"));
        exit;
    }
    
    $_SESSION = array();
    
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['logged_in'] = true;
    $_SESSION['CREATED'] = time();
    $_SESSION['LAST_ACTIVITY'] = time();
    
    error_log("Login successful for user: " . $user['username']);
    
    $rateLimitKey = 'rate_limit_login_' . md5($usernameOrEmail);
    if (isset($_SESSION[$rateLimitKey])) {
        unset($_SESSION[$rateLimitKey]);
    }
    
    try {
        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);
    } catch (PDOException $e) {
        error_log("Could not update last_login: " . $e->getMessage());
    }
    
    if ($user['role'] === 'admin') {
        header("Location: " . getBaseUrl() . "/admin/dashboard.php");
        exit;
    } else {
        header("Location: " . getBaseUrl() . "/index.php");
        exit;
    }
    
} catch (PDOException $e) {
    error_log("Database error in login: " . $e->getMessage());
    header("Location: login-page.php?error=" . urlencode("An error occurred. Please try again."));
    exit;
}