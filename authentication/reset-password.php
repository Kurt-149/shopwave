<?php
require_once __DIR__ . '/../core/init.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../core/config.php';
$root = BASE_URL;
require_once __DIR__ . '/../core/security.php';

$pdo   = getPdo();
$token = $_GET['token'] ?? '';
$error = '';
$validToken = false;

if (empty($token)) {
    $error = 'Invalid reset link';
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT pr.*, u.username, u.email
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.id
            WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([hash('sha256', $token)]);
        $reset = $stmt->fetch();
        if ($reset) {
            $validToken = true;
        } else {
            $error = 'This reset link is invalid or has expired';
        }
    } catch (PDOException $e) {
        error_log('Error verifying reset token: ' . $e->getMessage());
        $error = 'An error occurred. Please try again.';
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(function_exists('generateCsrfToken') ? generateCsrfToken() : '', ENT_QUOTES); ?>">
    <script>
    (function () {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (!meta || !meta.content || !window.fetch) return;
        var token = meta.content, origFetch = window.fetch;
        window.fetch = function (input, init) {
            init = init || {};
            if ((init.method || 'GET').toUpperCase() === 'POST' && init.body !== undefined) {
                var b = init.body;
                if (typeof FormData !== 'undefined' && b instanceof FormData) {
                    if (!b.has('csrf_token')) b.append('csrf_token', token);
                } else if (typeof URLSearchParams !== 'undefined' && b instanceof URLSearchParams) {
                    if (!b.has('csrf_token')) b.append('csrf_token', token);
                } else if (typeof b === 'string') {
                    if (b.indexOf('csrf_token=') === -1) {
                        init.body = b + (b ? '&' : '') + 'csrf_token=' + encodeURIComponent(token);
                    }
                }
            }
            return origFetch.call(this, input, init);
        };
    })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - SHOPWAVE</title>
    <link rel="stylesheet" href="<?php echo $root; ?>/design/main-layout.css">
    <link rel="stylesheet" href="<?php echo $root; ?>/design/web-design/loginPage.css">
</head>
<body>
    <div class="login-wrapper">
        <a href="<?php echo $root; ?>/authentication/login-page.php" class="back-home">
            <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor">
                <path d="M400-80 0-480l400-400 71 71-329 329 329 329-71 71Z"/>
            </svg>
            Back to Login
        </a>
        <div class="login-container">
            <div class="login-header">
                <h1>Reset Password</h1>
                <?php if ($validToken): ?>
                    <p>Enter your new password</p>
                <?php endif; ?>
            </div>
            <div class="login-body">
                <?php if ($error): ?>
                    <div class='alert alert-error'><?php echo htmlspecialchars($error); ?></div>
                    <a href="<?php echo $root; ?>/authentication/forgot-password.php"
                       class="login-btn"
                       style="display:block;text-align:center;text-decoration:none;margin-top:20px;">
                        Request New Link
                    </a>
                <?php elseif ($validToken): ?>
                    <?php if (isset($_GET['error'])): ?>
                        <div class='alert alert-error'><?php echo htmlspecialchars($_GET['error']); ?></div>
                    <?php endif; ?>
                    <form action="reset-password-process.php" method="POST" id="resetPasswordForm">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(function_exists('generateCsrfToken') ? generateCsrfToken() : '', ENT_QUOTES); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="token"      value="<?php echo htmlspecialchars($token); ?>">

                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="new_password" name="new_password"
                                    placeholder="Enter new password (min 8 characters)"
                                    minlength="8" maxlength="30"
                                    oninput="handlePasswordInput('new_password', this)"
                                    required>
                                <button type="button" class="toggle-password"
                                    onclick="togglePasswordVisibility('new_password', this)"
                                    aria-label="Toggle password visibility">
                                    <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor" class="eye-open">
                                        <path d="M480-320q75 0 127.5-52.5T660-500q0-75-52.5-127.5T480-680q-75 0-127.5 52.5T300-500q0 75 52.5 127.5T480-320Zm0-72q-45 0-76.5-31.5T372-500q0-45 31.5-76.5T480-608q45 0 76.5 31.5T588-500q0 45-31.5 76.5T480-392Zm0 192q-146 0-266-81.5T40-500q54-137 174-218.5T480-800q146 0 266 81.5T920-500q-54 137-174 218.5T480-200Z"/>
                                    </svg>
                                    <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor" class="eye-closed" style="display:none;">
                                        <path d="m644-428-58-58q9-47-27-88t-93-32l-58-58q17-8 34.5-12t37.5-4q75 0 127.5 52.5T660-500q0 20-4 37.5T644-428Zm128 126-58-56q38-29 67.5-63.5T832-500q-50-101-143.5-160.5T480-720q-29 0-57 4t-55 12l-62-62q41-17 84-25.5t90-8.5q151 0 269 83.5T920-500q-23 59-60.5 109.5T772-302Zm20 246L624-224q-35 11-70.5 17.5T480-200q-151 0-269-83.5T40-500q21-53 53-98.5t73-81.5L56-792l56-56 736 736-56 56ZM222-624q-29 26-53 57t-41 67q50 101 143.5 160.5T480-280q20 0 39-2.5t39-7.5l-36-38q-11 3-21 5t-21 2q-75 0-127.5-52.5T300-500q0-11 2-21t5-21l-85-82Z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="confirm_password" name="confirm_password"
                                    placeholder="Confirm new password"
                                    minlength="8" maxlength="30"
                                    oninput="handlePasswordInput('confirm_password', this)"
                                    required>
                                <button type="button" class="toggle-password"
                                    onclick="togglePasswordVisibility('confirm_password', this)"
                                    aria-label="Toggle password visibility">
                                    <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor" class="eye-open">
                                        <path d="M480-320q75 0 127.5-52.5T660-500q0-75-52.5-127.5T480-680q-75 0-127.5 52.5T300-500q0 75 52.5 127.5T480-320Zm0-72q-45 0-76.5-31.5T372-500q0-45 31.5-76.5T480-608q45 0 76.5 31.5T588-500q0 45-31.5 76.5T480-392Zm0 192q-146 0-266-81.5T40-500q54-137 174-218.5T480-800q146 0 266 81.5T920-500q-54 137-174 218.5T480-200Z"/>
                                    </svg>
                                    <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor" class="eye-closed" style="display:none;">
                                        <path d="m644-428-58-58q9-47-27-88t-93-32l-58-58q17-8 34.5-12t37.5-4q75 0 127.5 52.5T660-500q0 20-4 37.5T644-428Zm128 126-58-56q38-29 67.5-63.5T832-500q-50-101-143.5-160.5T480-720q-29 0-57 4t-55 12l-62-62q41-17 84-25.5t90-8.5q151 0 269 83.5T920-500q-23 59-60.5 109.5T772-302Zm20 246L624-224q-35 11-70.5 17.5T480-200q-151 0-269-83.5T40-500q21-53 53-98.5t73-81.5L56-792l56-56 736 736-56 56ZM222-624q-29 26-53 57t-41 67q50 101 143.5 160.5T480-280q20 0 39-2.5t39-7.5l-36-38q-11 3-21 5t-21 2q-75 0-127.5-52.5T300-500q0-11 2-21t5-21l-85-82Z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="login-btn">Reset Password</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        function handlePasswordInput(inputId, input) {
            const btn = input.parentElement.querySelector('.toggle-password');
            if (!btn) return;
            if (input.value.length > 0) {
                btn.classList.add('visible');
            } else {
                btn.classList.remove('visible');
                input.type = 'password';
                btn.querySelector('.eye-open').style.display  = 'block';
                btn.querySelector('.eye-closed').style.display = 'none';
            }
        }
        function togglePasswordVisibility(inputId, button) {
            const input    = document.getElementById(inputId);
            const eyeOpen  = button.querySelector('.eye-open');
            const eyeClosed = button.querySelector('.eye-closed');
            if (input.type === 'password') {
                input.type = 'text';
                eyeOpen.style.display  = 'none';
                eyeClosed.style.display = 'block';
            } else {
                input.type = 'password';
                eyeOpen.style.display  = 'block';
                eyeClosed.style.display = 'none';
            }
        }
        document.getElementById('resetPasswordForm')?.addEventListener('submit', function(e) {
            const newPass     = document.getElementById('new_password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            if (newPass !== confirmPass) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    </script>
</body>
</html>