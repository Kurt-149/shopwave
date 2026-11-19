<?php
// authentication/forgot-password-process.php
// JSON endpoint for forgot-password.php. Three actions:
//   request_code   -> emails a 6-digit code (same reply whether or not the account exists)
//   verify_code    -> checks the code
//   reset_password -> saves the new password once the code is confirmed, and records the change

require_once __DIR__ . '/../core/init.php';
require_once __DIR__ . '/../core/session-handler.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/url-helper.php';
require_once __DIR__ . '/../core/email.php';

header('Content-Type: application/json; charset=utf-8');

function respond(bool $success, string $message, array $extra = []): void
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

// Testing only: the code is shown on the page only when APP_ENV=local AND the request
// comes from this computer. Both checks must pass, so visitors on the live site never see it.
function devMode(): bool
{
    return env('APP_ENV', '') === 'local' && isLocalhost();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respond(false, 'Invalid request.');
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    respond(false, 'Security check failed. Please refresh the page and try again.');
}

$action = $_POST['action'] ?? '';

if ($action === 'request_code') {
    $email = trim($_POST['email'] ?? '');
    if (!validateEmail($email)) {
        respond(false, 'Please enter a valid email address.');
    }

    // Limits: 3 codes per email and 10 requests per network every 15 minutes.
    if (!checkRateLimit('reset_email_' . strtolower($email), 3, 900)
        || !checkRateLimit('reset_ip_' . getClientIP(), 10, 900)) {
        respond(false, 'Too many requests. Please wait 15 minutes and try again.');
    }

    $stmt = getPdo()->prepare('SELECT id, username, email FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    // The request is stored even when no account matches, and the reply is the same
    // either way. That way this page cannot be used to find out which emails have accounts.
    $_SESSION['reset_flow'] = [
        'email'     => $email,
        'user_id'   => $user ? (int) $user['id'] : null,
        'code_hash' => hash('sha256', $code),
        'expires'   => time() + 600, // 10 minutes
        'attempts'  => 0,
        'verified'  => false,
    ];
    if (devMode()) {
        $_SESSION['reset_flow']['dev_code'] = $code;
    }

    if ($user) {
        $body = "Hi {$user['username']},\n\n"
              . "Your code to reset your SHOPWAVE password is: $code\n\n"
              . "The code expires in 10 minutes. If you did not ask for this, ignore this email.\n\n"
              . "SHOPWAVE";

        if (!sendEmail($user['email'], 'Your SHOPWAVE password reset code', $body)) {
            error_log('[forgot-password] could not send code to user ID ' . $user['id']);
        }
    }

    $extra = devMode() ? ['dev_code' => $code] : [];
    respond(true, 'If an account uses this email, a 6-digit code has been sent.', $extra);
}

if ($action === 'verify_code') {
    $flow = $_SESSION['reset_flow'] ?? null;
    if (!is_array($flow) || time() > $flow['expires'] || $flow['attempts'] >= 5) {
        unset($_SESSION['reset_flow']);
        respond(false, 'This code has expired or too many wrong codes were entered. Start again.');
    }

    // Keep digits only, so spaces or dashes in the typed code do not matter.
    $typed = preg_replace('/\D/', '', $_POST['code'] ?? '');
    if (!hash_equals($flow['code_hash'], hash('sha256', $typed))) {
        $_SESSION['reset_flow']['attempts']++;
        respond(false, 'Wrong code. Check your email and try again.');
    }

    $_SESSION['reset_flow']['verified']       = true;
    $_SESSION['reset_flow']['verified_until'] = time() + 600; // 10 minutes to pick a password
    unset($_SESSION['reset_flow']['dev_code']);

    respond(true, 'Email confirmed. Choose your new password.', ['email' => $flow['email']]);
}

if ($action === 'reset_password') {
    $flow = $_SESSION['reset_flow'] ?? null;
    if (!is_array($flow) || empty($flow['verified']) || time() > $flow['verified_until'] || $flow['user_id'] === null) {
        unset($_SESSION['reset_flow']);
        respond(false, 'Your confirmation has expired. Start again.');
    }

    $newPassword = $_POST['new_password'] ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';

    if ($newPassword !== $confirm) {
        respond(false, 'Passwords do not match.');
    }

    // Same rules as the rest of the site: 8 to 72 characters, 3 of 4 character types.
    $check = validatePassword($newPassword);
    if ($check !== true) {
        respond(false, implode(' ', $check));
    }

    $userId = (int) $flow['user_id'];

    try {
        $pdo = getPdo();
        $pdo->beginTransaction();

        $pdo->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?')
            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);

        // Record the change. It is in the same transaction, so a change is never saved without its record.
        $pdo->prepare('INSERT INTO password_changes (user_id, method, ip_address) VALUES (?, ?, ?)')
            ->execute([$userId, 'forgot-password', getClientIP()]);

        // Sign out every saved session for this account.
        $pdo->prepare('DELETE FROM user_sessions WHERE user_id = ?')->execute([$userId]);

        $pdo->commit();
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Password reset failed: ' . $e->getMessage());
        respond(false, 'An error occurred. Please try again.');
    }

    logSecurityEvent('Password reset with email code for user ID ' . $userId);

    $_SESSION = [];
    session_destroy();

    $message = 'Password changed. Please log in with your new password.';
    respond(true, $message, ['redirect' => 'login-page.php?success=' . urlencode($message)]);
}

respond(false, 'Invalid request.');