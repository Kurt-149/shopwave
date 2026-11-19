<?php
// authentication/change-password-process.php
// Step 3: saves the new password, records the change, signs out every session, then sends the user to login.

require_once __DIR__ . '/../core/init.php';
require_once __DIR__ . '/../core/session-handler.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../core/security.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../public/me-page.php');
    exit;
}

function backToChangePassword(string $message): void
{
    header('Location: change-password.php?error=' . urlencode($message));
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    backToChangePassword('Security check failed. Please try again.');
}

$userId  = getCurrentUserId();
$pending = $_SESSION['pw_change'] ?? null;

// Only allowed after the code was checked, and only for a short time.
if (!is_array($pending) || (int) $pending['user_id'] !== $userId
    || empty($pending['verified']) || time() > $pending['verified_until']) {
    unset($_SESSION['pw_change']);
    header('Location: ../public/me-page.php');
    exit;
}

$newPassword = $_POST['new_password'] ?? '';
$confirm     = $_POST['confirm_password'] ?? '';

if ($newPassword !== $confirm) {
    backToChangePassword('Passwords do not match.');
}

// Same rules as the rest of the site: 8 to 72 characters, 3 of 4 character types.
$check = validatePassword($newPassword);
if ($check !== true) {
    backToChangePassword(implode(' ', $check));
}

try {
    $pdo = getPdo();
    $pdo->beginTransaction();

    $pdo->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?')
        ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);

    // Record the change. It is in the same transaction, so a change is never saved without its record.
    $pdo->prepare('INSERT INTO password_changes (user_id, method, ip_address) VALUES (?, ?, ?)')
        ->execute([$userId, 'profile', getClientIP()]);

    // Remove saved sessions for this account, so other devices must log in again.
    $pdo->prepare('DELETE FROM user_sessions WHERE user_id = ?')->execute([$userId]);

    $pdo->commit();
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Password change failed: ' . $e->getMessage());
    backToChangePassword('An error occurred. Please try again.');
}

logSecurityEvent('Password changed for user ID ' . $userId);

// Sign this device out too. The user logs in again with the new password.
$_SESSION = [];
session_destroy();

header('Location: login-page.php?success=' . urlencode('Password changed. Please log in with your new password.'));
exit;