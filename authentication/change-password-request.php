<?php
// authentication/change-password-request.php
// Step 1: the profile page POSTs here. Creates a 6-digit code and emails it.

require_once __DIR__ . '/../core/init.php';
require_once __DIR__ . '/../core/session-handler.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/url-helper.php';
require_once __DIR__ . '/../core/email.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../public/me-page.php');
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    header('Location: change-password.php?error=' . urlencode('Security check failed. Please try again.'));
    exit;
}

$userId = getCurrentUserId();

// At most 3 codes every 15 minutes per account, so nobody can flood an inbox.
if (!checkRateLimit('pwchange_user_' . $userId, 3, 900)) {
    header('Location: change-password.php?error=' . urlencode('Too many requests. Please wait 15 minutes and try again.'));
    exit;
}

$pdo  = getPdo();
$stmt = $pdo->prepare('SELECT username, email FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: ../public/me-page.php');
    exit;
}

// random_int gives 0 to 999999. str_pad keeps leading zeros, so 042817 stays 6 digits.
$code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

// The session is stored in the database, so only the hash of the code is kept.
$_SESSION['pw_change'] = [
    'user_id'   => $userId,
    'code_hash' => hash('sha256', $code),
    'expires'   => time() + 600, // 10 minutes
    'attempts'  => 0,
    'verified'  => false,
];

// Testing only: on localhost the code is filled in on the page. isLocalhost() checks
// the real connection address, so visitors on the live site never get this.
if (isLocalhost()) {
    $_SESSION['pw_change']['dev_code'] = $code;
}

$body = "Hi {$user['username']},\n\n"
      . "Your code to change your SHOPWAVE password is: $code\n\n"
      . "The code expires in 10 minutes. If you did not ask to change your password, ignore this email.\n\n"
      . "SHOPWAVE";

if (!sendEmail($user['email'], 'Your SHOPWAVE password change code', $body)) {
    unset($_SESSION['pw_change']);
    header('Location: change-password.php?error=' . urlencode('We could not send the email. Please try again.'));
    exit;
}

header('Location: change-password.php');
exit;