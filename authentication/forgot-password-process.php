<?php
require_once __DIR__ . '/../core/init.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../core/security.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Security validation failed']);
    exit;
}

if (!checkRateLimit('forgot_password_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 5, 600)) {
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Please try again in 10 minutes.']);
    exit;
}

if (trim($_POST['action'] ?? '') !== 'request_reset') {
    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

// Same answer whether or not the account exists (prevents email enumeration)
$genericReply = [
    'success' => true,
    'message' => 'If an account exists for that email, a reset link has been sent. It expires in 1 hour.'
];

try {
    $pdo = getPdo();

    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Random token goes in the email; only its SHA-256 hash is stored.
        $token     = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $pdo->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0")
            ->execute([$user['id']]);

        $pdo->prepare(
            "INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))"
        )->execute([$user['id'], $tokenHash]);

        $link = getBaseUrl() . '/authentication/reset-password.php?token=' . $token;

        $subject = 'SHOPWAVE password reset';
        $body    = "Hi " . $user['username'] . ",\n\n"
                 . "Use this link to reset your password (valid for 1 hour):\n$link\n\n"
                 . "If you didn't ask for this, you can ignore this email.";
        $headers = "From: no-reply@shopwave.local\r\nContent-Type: text/plain; charset=UTF-8";

        $sent = @mail($email, $subject, $body, $headers);

        // Local development: mail() is usually not set up, so log the link instead.
        if (!$sent && $isLocalhost) {
            error_log('[forgot-password] mail() unavailable. Reset link for ' . $email . ': ' . $link);
        } elseif (!$sent) {
            error_log('[forgot-password] mail() failed for user ID ' . $user['id']);
        }
    }

    echo json_encode($genericReply);
    exit;
} catch (Throwable $e) {
    error_log('[forgot-password] ' . $e->getMessage());
    echo json_encode($genericReply);   // don't reveal internal errors either
    exit;
}
