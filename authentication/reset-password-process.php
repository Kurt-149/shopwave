<?php
require_once __DIR__ . '/../core/init.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../core/security.php';

setSecurityHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login-page.php');
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    header('Location: login-page.php?error=' . urlencode('Security validation failed'));
    exit;
}

$token           = trim($_POST['token']            ?? '');
$newPassword     = $_POST['new_password']           ?? '';
$confirmPassword = $_POST['confirm_password']       ?? '';

if (empty($token) || empty($newPassword) || empty($confirmPassword)) {
    header('Location: reset-password.php?token=' . urlencode($token) . '&error=' . urlencode('All fields are required'));
    exit;
}

if ($newPassword !== $confirmPassword) {
    header('Location: reset-password.php?token=' . urlencode($token) . '&error=' . urlencode('Passwords do not match'));
    exit;
}

if (strlen($newPassword) < 8 || strlen($newPassword) > 30) {
    header('Location: reset-password.php?token=' . urlencode($token) . '&error=' . urlencode('Password must be 8-30 characters'));
    exit;
}

try {
    $pdo = getPdo();

    $stmt = $pdo->prepare("
        SELECT pr.*, u.id as user_id
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([hash('sha256', $token)]);
    $reset = $stmt->fetch();

    if (!$reset) {
        header('Location: login-page.php?error=' . urlencode('Invalid or expired reset token'));
        exit;
    }

    $pdo->beginTransaction();

    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?")
        ->execute([$hashedPassword, $reset['user_id']]);

    $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?")
        ->execute([$reset['id']]);

    $pdo->commit();

    error_log('Password reset successful for user ID: ' . $reset['user_id']);

    header('Location: login-page.php?success=' . urlencode('Password reset successful! Please login with your new password.'));
    exit;

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Database error in password reset: ' . $e->getMessage());
    header('Location: reset-password.php?token=' . urlencode($token) . '&error=' . urlencode('An error occurred. Please try again.'));
    exit;
}