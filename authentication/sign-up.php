<?php
require_once __DIR__ . '/../core/init.php';
require_once __DIR__ . '/../core/session-handler.php';
require_once __DIR__ . '/../core/config.php';
$root = BASE_URL;
require_once dirname(__DIR__) . '/core/security.php';

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - SHOPWAVE</title>
    
    <link rel="stylesheet" href="<?php echo asset('design/web-design/auth-style.css'); ?>">
    
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
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
</head>
<body>
    <div class="page-background">
        
        <div class="auth-container">
            
            <div class="branding-panel">
                <div class="branding-content">
                    <h2>Join the<br>Wave.</h2>
                    <p>Create an account to start shopping</p>
                </div>
            </div>

            <div class="form-panel">
                <!-- The Back to Login button has been removed -->

                <div class="login-header">
                    <h1>SHOPWAVE</h1>
                    <p>Create your account in seconds.</p>
                </div>
                
                <div class="login-body">
                    <?php
                    if (isset($_GET['error'])) {
                        if ($_GET['error'] === 'account_taken') {
                            echo "<div class='alert alert-error'>Email already registered. Please log in.</div>";
                        } else {
                            echo "<div class='alert alert-error'>" . htmlspecialchars($_GET['error']) . "</div>";
                        }
                    }
                    if (isset($_GET['success'])) {
                        echo "<div class='alert alert-success'>" . htmlspecialchars($_GET['success']) . "</div>";
                    }
                    ?>

                    <form action="signup-process.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" placeholder="name@example.com" maxlength="100" required>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="password" name="password"
                                    placeholder="Create a password (min 8 characters)"
                                    minlength="8" maxlength="72"
                                    oninput="handlePasswordInput('password', this); checkPasswordStrength(this.value)"
                                    required>
                                <button type="button" class="toggle-password"
                                    onclick="togglePasswordVisibility('password', this)" aria-label="Toggle password visibility">
                                    <svg xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px" fill="currentColor" class="eye-open">
                                        <path d="M480-320q75 0 127.5-52.5T660-500q0-75-52.5-127.5T480-680q-75 0-127.5 52.5T300-500q0 75 52.5 127.5T480-320Zm0-72q-45 0-76.5-31.5T372-500q0-45 31.5-76.5T480-608q45 0 76.5 31.5T588-500q0 45-31.5 76.5T480-392Zm0 192q-146 0-266-81.5T40-500q54-137 174-218.5T480-800q146 0 266 81.5T920-500q-54 137-174 218.5T480-200Z" />
                                    </svg>
                                    <svg xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px" fill="currentColor" class="eye-closed" style="display:none;">
                                        <path d="m644-428-58-58q9-47-27-88t-93-32l-58-58q17-8 34.5-12t37.5-4q75 0 127.5 52.5T660-500q0 20-4 37.5T644-428Zm128 126-58-56q38-29 67.5-63.5T832-500q-50-101-143.5-160.5T480-720q-29 0-57 4t-55 12l-62-62q41-17 84-25.5t90-8.5q151 0 269 83.5T920-500q-23 59-60.5 109.5T772-302Zm20 246L624-224q-35 11-70.5 17.5T480-200q-151 0-269-83.5T40-500q21-53 53-98.5t73-81.5L56-792l56-56 736 736-56 56ZM222-624q-29 26-53 57t-41 67q50 101 143.5 160.5T480-280q20 0 39-2.5t39-7.5l-36-38q-11 3-21 5t-21 2q-75 0-127.5-52.5T300-500q0-11 2-21t5-21l-85-82Z" />
                                    </svg>
                                </button>
                            </div>
                            
                            <div class="password-strength">
                                <div class="strength-bar" id="strengthBar"></div>
                            </div>
                            <div class="strength-text" id="strengthText">Enter a password</div>
                        </div>

                        <button type="submit" class="auth-btn" id="submitBtn">Create Account</button>
                    </form>
                </div>

                <div class="social-section">
                    <div class="divider">
                        <span>or sign up with</span>
                    </div>
                    <div class="social-login">
                        <button type="button" class="social-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="18px" height="18px"><path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"/><path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"/><path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"/><path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z"/></svg>
                            Google
                        </button>
                        <button type="button" class="social-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="18px" height="18px"><path fill="#1877F2" d="M24,4C12.954,4,4,12.954,4,24c0,10.028,7.378,18.326,17.011,19.762V29.555h-5.103v-5.555h5.103v-4.429c0-5.035,2.999-7.817,7.588-7.817c2.199,0,4.498,0.393,4.498,0.393v4.946h-2.534c-2.495,0-3.273,1.549-3.273,3.137v3.77h5.569l-0.891,5.555H27.29V43.84C36.923,42.404,44,34.106,44,24C44,12.954,35.046,4,24,4z"/></svg>
                            Facebook
                        </button>
                    </div>
                </div>

                <div class="login-footer">
                    <p>Already have an account? <a href="<?php echo $root; ?>/authentication/login-page.php">Login</a></p>
                </div>
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
                btn.querySelector('.eye-open').style.display = 'block';
                btn.querySelector('.eye-closed').style.display = 'none';
            }
        }

        function togglePasswordVisibility(inputId, button) {
            const input = document.getElementById(inputId);
            const eyeOpen = button.querySelector('.eye-open');
            const eyeClosed = button.querySelector('.eye-closed');
            if (input.type === 'password') {
                input.type = 'text';
                eyeOpen.style.display = 'none';
                eyeClosed.style.display = 'block';
            } else {
                input.type = 'password';
                eyeOpen.style.display = 'block';
                eyeClosed.style.display = 'none';
            }
        }

        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            let strength = 0;
            
            if (password.length >= 8) strength += 25;
            if (password.match(/[a-z]/)) strength += 25;
            if (password.match(/[A-Z]/)) strength += 25;
            if (password.match(/[0-9]/) || password.match(/[^a-zA-Z0-9]/)) strength += 25;
            
            strengthBar.style.width = strength + '%';
            
            if (password.length === 0) {
                strengthBar.style.width = '0%';
                strengthText.textContent = 'Enter a password';
                strengthText.style.color = '#6b7280';
            } else if (strength <= 25) {
                strengthBar.style.background = '#ef4444';
                strengthText.textContent = 'Weak password';
                strengthText.style.color = '#ef4444';
            } else if (strength <= 50) {
                strengthBar.style.background = '#f59e0b';
                strengthText.textContent = 'Fair password';
                strengthText.style.color = '#f59e0b';
            } else if (strength <= 75) {
                strengthBar.style.background = '#3b82f6';
                strengthText.textContent = 'Good password';
                strengthText.style.color = '#3b82f6';
            } else {
                strengthBar.style.background = '#22c55e';
                strengthText.textContent = 'Strong password';
                strengthText.style.color = '#22c55e';
            }
        }
    </script>
</body>
</html>