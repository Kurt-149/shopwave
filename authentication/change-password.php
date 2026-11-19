<?php
// authentication/change-password.php
// Shows the code form, then the new-password form. One page, two steps.

require_once __DIR__ . '/../core/init.php';
require_once __DIR__ . '/../core/session-handler.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/url-helper.php';

setSecurityHeaders();
requireLogin();

// Testing only: the code is shown on the page only when APP_ENV=local AND the request
// comes from this computer. Both checks must pass.
function isTestingMode(): bool
{
    return env('APP_ENV', '') === 'local' && isLocalhost();
}

$error = $_GET['error'] ?? '';

$userId  = getCurrentUserId();
$pending = $_SESSION['pw_change'] ?? null;
if (!is_array($pending) || (int) $pending['user_id'] !== $userId) {
    $pending = null;
}

// Each step has a time limit. Clear the request when it runs out.
if ($pending !== null) {
    $expired = !empty($pending['verified'])
        ? time() > $pending['verified_until']
        : (time() > $pending['expires'] || $pending['attempts'] >= 5);

    if ($expired) {
        unset($_SESSION['pw_change']);
        $pending = null;
        $error   = 'This request has expired or too many wrong codes were entered. Start again from your profile.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security check failed. Please try again.';
    } elseif ($pending === null || !empty($pending['verified'])) {
        $error = 'No code is waiting to be checked. Start again from your profile.';
    } else {
        // Keep digits only, so spaces or dashes in the typed code do not matter.
        $typed = preg_replace('/\D/', '', $_POST['code'] ?? '');

        if (hash_equals($pending['code_hash'], hash('sha256', $typed))) {
            $_SESSION['pw_change']['verified']       = true;
            $_SESSION['pw_change']['verified_until'] = time() + 600; // 10 minutes to pick a password
            unset($_SESSION['pw_change']['dev_code']);
            header('Location: change-password.php');
            exit;
        }

        // Wrong code: count it. After 5 wrong codes the request is cleared.
        $_SESSION['pw_change']['attempts']++;
        $error = 'Wrong code. Check your email and try again.';
    }
}

// Work out which step to show.
if ($pending === null) {
    $step = 'none';
} elseif (empty($pending['verified'])) {
    $step = 'code';
} else {
    $step = 'password';
}

// Testing only: the code is sent to the page after a short delay, to copy a real email wait.
$autofill = ($step === 'code' && isTestingMode() && !empty($pending['dev_code'])) ? $pending['dev_code'] : '';

$csrf  = generateCsrfToken();
$nonce = htmlspecialchars($_SESSION['csp_nonce'] ?? '', ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - SHOPWAVE</title>
    <link rel="stylesheet" href="<?php echo asset('design/main-layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('design/web-design/loginPage.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('design/shopwave-theme.css'); ?>">
</head>
<body>
    <div class="login-wrapper">
        <a href="../public/me-page.php" class="back-home">Back to profile</a>
        <div class="login-container">
            <div class="login-header">
                <h1>Change Password</h1>
                <?php if ($step === 'code'): ?>
                    <p>Enter the 6-digit code we emailed you</p>
                <?php elseif ($step === 'password'): ?>
                    <p>Choose your new password</p>
                <?php endif; ?>
            </div>
            <div class="login-body">
                <?php if ($error !== ''): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if ($step === 'none'): ?>
                    <a href="../public/me-page.php" class="login-btn"
                       style="display:block;text-align:center;text-decoration:none;">
                        Back to profile
                    </a>

                <?php elseif ($step === 'code'): ?>
                    <?php if ($autofill !== ''): ?>
                        <p id="codeWait" style="font-size:.9rem;color:#1d4ed8;">
                            Sending the code to your email... (testing mode) 5...
                        </p>
                    <?php endif; ?>
                    <form method="POST" action="change-password.php">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>">
                        <div class="form-group">
                            <label for="code">Verification code</label>
                            <input type="text" id="code" name="code"
                                   data-autofill="<?php echo htmlspecialchars($autofill, ENT_QUOTES); ?>"
                                   inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                                   autocomplete="one-time-code" required>
                        </div>
                        <button type="submit" class="login-btn">Verify Code</button>
                    </form>

                <?php else: ?>
                    <form method="POST" action="change-password-process.php" id="changePasswordForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>">
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password"
                                   minlength="8" maxlength="72" autocomplete="new-password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password"
                                   minlength="8" maxlength="72" autocomplete="new-password" required>
                        </div>
                        <p style="font-size:.85rem;color:#666;">
                            8 to 72 characters, with at least 3 of: uppercase, lowercase, numbers, symbols.
                        </p>
                        <button type="submit" class="login-btn">Change Password</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script nonce="<?php echo $nonce; ?>">
        // Testing mode: after 5 seconds, fill the code in, as if the email had arrived.
        const codeInput = document.getElementById('code');
        if (codeInput && codeInput.dataset.autofill) {
            const wait = document.getElementById('codeWait');
            let seconds = 5;
            const timer = setInterval(() => {
                seconds--;
                if (seconds > 0) {
                    wait.textContent = 'Sending the code to your email... (testing mode) ' + seconds + '...';
                } else {
                    clearInterval(timer);
                    codeInput.value = codeInput.dataset.autofill;
                    wait.textContent = 'Code received. (testing mode: filled in for you)';
                }
            }, 1000);
        }

        document.getElementById('changePasswordForm')?.addEventListener('submit', function (e) {
            if (document.getElementById('new_password').value !== document.getElementById('confirm_password').value) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    </script>
</body>
</html>