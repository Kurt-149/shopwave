<?php
require_once __DIR__ . '/../core/init.php';
require_once __DIR__ . '/../core/session-handler.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../core/security.php';

setSecurityHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    safeRedirect('sign-up.php');
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    header("Location: sign-up.php?error=" . urlencode("Security validation failed. Please try again."));
    exit;
}

if (!checkRateLimit('signup_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 5, 600)) {
    header("Location: sign-up.php?error=" . urlencode("Too many signup attempts. Please try again later."));
    exit;
}

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: sign-up.php?error=" . urlencode("Invalid email address"));
    exit;
}

if (empty($username) || strlen($username) < 3 || strlen($username) > 30) {
    header("Location: sign-up.php?error=" . urlencode("Username must be between 3 and 30 characters"));
    exit;
}

if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    header("Location: sign-up.php?error=" . urlencode("Username can only contain letters, numbers, and underscores"));
    exit;
}

if (strlen($password) < 8) {
    header("Location: sign-up.php?error=" . urlencode("Password must be at least 8 characters long"));
    exit;
}

if (strlen($password) > 72) {
    header("Location: sign-up.php?error=" . urlencode("Password must not exceed 72 characters"));
    exit;
}

try {
    $pdo = getPdo();
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
    $stmt->execute([$username, $email]);
    
    if ($stmt->fetch()) {
        header("Location: sign-up.php?error=account_taken");
        exit;
    }
    
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, 'user', NOW())");
    $stmt->execute([$username, $email, $hash]);
    
    header("Location: login-page.php?success=" . urlencode("Account created successfully! Please login."));
    exit;
    
} catch (PDOException $e) {
    error_log("Database error in signup: " . $e->getMessage());
    header("Location: sign-up.php?error=" . urlencode("An error occurred. Please try again."));
    exit;
}