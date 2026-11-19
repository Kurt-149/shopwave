<?php
// core/email.php
// Sends plain-text emails and builds the account verification flow.

require_once __DIR__ . '/url-helper.php';

const MAIL_FROM = 'no-reply@shopwave.local';

/**
 * Send a plain-text email.
 * Returns true when the email is sent (or logged on localhost), false otherwise.
 */
function sendEmail(string $to, string $subject, string $body): bool
{
    // 1. Check the address. Never trust what a user typed into a form.
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('[email] invalid address: ' . str_replace(["\r", "\n"], ' ', $to));
        return false;
    }

    // 2. Stop header injection. Line breaks in the subject could add their own
    //    headers, such as "Bcc: spam@example.com".
    $subject = str_replace(["\r", "\n"], ' ', $subject);

    $headers = 'From: ' . MAIL_FROM . "\r\n"
             . 'Content-Type: text/plain; charset=UTF-8';

    // 3. On localhost, log the email instead of sending it. Read it in
    //    C:\xampp\apache\logs\error.log.
    if (isLocalhost()) {
        error_log("[email] TO: $to | SUBJECT: $subject\n$body");
        return true;
    }

    // 4. On the live server, send it.
    return mail($to, $subject, $body, $headers);
}

/**
 * Build the subject and body of the verification email.
 */
function buildVerificationEmail(string $username, string $link): array
{
    $subject = 'Verify your SHOPWAVE email';

    $body = "Hi $username,\n\n"
          . "Thanks for signing up to SHOPWAVE. Please confirm your email address by opening this link:\n\n"
          . "$link\n\n"
          . "The link expires in 24 hours. If you did not create an account, you can ignore this email.\n\n"
          . "SHOPWAVE";

    return ['subject' => $subject, 'body' => $body];
}

/**
 * Create a verification token for a user and return the link to email.
 * Returns '' when APP_URL is missing, because the link would not work.
 */
function createVerificationLink(PDO $pdo, int $userId): string
{
    $baseUrl = getBaseUrl();
    if ($baseUrl === '') {
        error_log('[email] APP_URL is not set in .env; verification link not created.');
        return '';
    }

    // Delete older links for this user, so only the newest one works.
    $delete = $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ?');
    $delete->execute([$userId]);

    $token     = bin2hex(random_bytes(32)); // 64 random hex characters, sent to the user
    $tokenHash = hash('sha256', $token);    // only the hash is stored in the database

    // NOW() and the expiry both use MySQL's clock, so PHP's timezone cannot shift them.
    $insert = $pdo->prepare(
        'INSERT INTO email_verifications (user_id, token_hash, expires_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))'
    );
    $insert->execute([$userId, $tokenHash]);

    return $baseUrl . '/authentication/verify-email.php?token=' . $token;
}

/**
 * Send the verification email to a new user.
 * Returns true only if the link was created and the email was sent.
 */
function sendVerificationEmail(PDO $pdo, int $userId, string $email, string $username): bool
{
    try {
        $link = createVerificationLink($pdo, $userId);
        if ($link === '') {
            return false;
        }

        $message = buildVerificationEmail($username, $link);
        return sendEmail($email, $message['subject'], $message['body']);
    } catch (Throwable $e) {
        error_log('[email] verification failed: ' . $e->getMessage());
        return false;
    }
}