<?php
require_once __DIR__ . '/../core/init.php';
require_once __DIR__ . '/../core/session-handler.php';
require_once __DIR__ . '/database.php';
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
    <title>Login - SHOPWAVE</title>
    
    <!-- Pointing to the new Master CSS file -->
    <link rel="stylesheet" href="<?php echo asset('design/web-design/auth-style.css'); ?>">
    
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
</head>
<body>
    <div class="page-background">
        
        <!-- Using .auth-container to lock the layout -->
        <div class="auth-container">
            
            <div class="branding-panel">
                <div class="branding-content">
                    <h2>Dive into<br>Style.</h2>
                    <p>Premium Swimwear Collection</p>
                </div>
            </div>

            <div class="form-panel">
                <div class="login-header">
                    <h1>SHOPWAVE</h1>
                    <p>Welcome back. Please enter your details.</p>
                </div>
                
                <div class="login-body">
                    <?php
                    if (isset($_GET['error'])) {
                        echo "<div class='alert alert-error'>" . htmlspecialchars($_GET['error']) . "</div>";
                    } elseif (isset($_GET['success'])) {
                        echo "<div class='alert alert-success'>" . htmlspecialchars($_GET['success']) . "</div>";
                    }
                    ?>
                    
                    <form action="login-process.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        
                        <div class="form-group">
                            <label for="username">Email Address</label>
                            <input type="text" id="username" name="username" placeholder="name@example.com" maxlength="100" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="password" name="password" placeholder="Enter your password" required
                                    oninput="handlePasswordInput('password', this)">
                                <button type="button" class="toggle-password" id="toggle-password"
                                    onclick="togglePasswordVisibility('password', this)" aria-label="Toggle password visibility">
                                    <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor" class="eye-open">
                                        <path d="M480-320q75 0 127.5-52.5T660-500q0-75-52.5-127.5T480-680q-75 0-127.5 52.5T300-500q0 75 52.5 127.5T480-320Zm0-72q-45 0-76.5-31.5T372-500q0-45 31.5-76.5T480-608q45 0 76.5 31.5T588-500q0 45-31.5 76.5T480-392Zm0 192q-146 0-266-81.5T40-500q54-137 174-218.5T480-800q146 0 266 81.5T920-500q-54 137-174 218.5T480-200Z"/>
                                    </svg>
                                    <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor" class="eye-closed" style="display:none;">
                                        <path d="m644-428-58-58q9-47-27-88t-93-32l-58-58q17-8 34.5-12t37.5-4q75 0 127.5 52.5T660-500q0 20-4 37.5T644-428Zm128 126-58-56q38-29 67.5-63.5T832-500q-50-101-143.5-160.5T480-720q-29 0-57 4t-55 12l-62-62q41-17 84-25.5t90-8.5q151 0 269 83.5T920-500q-23 59-60.5 109.5T772-302Zm20 246L624-224q-35 11-70.5 17.5T480-200q-151 0-269-83.5T40-500q21-53 53-98.5t73-81.5L56-792l56-56 736 736-56 56ZM222-624q-29 26-53 57t-41 67q50 101 143.5 160.5T480-280q20 0 39-2.5t39-7.5l-36-38q-11 3-21 5t-21 2q-75 0-127.5-52.5T300-500q0-11 2-21t5-21l-85-82Z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="form-actions-row">
                            <div class="checkbox-wrapper">
                                <input type="checkbox" id="stay-signed-in" name="stay_signed_in">
                                <label for="stay-signed-in">Stay signed in</label>
                                <div class="tooltip-container">
                                    <span class="tooltip-icon">?</span>
                                    <span class="tooltip-text">Keep me logged in on this device. Do not select this if you are using a public computer.</span>
                                </div>
                            </div>
                            
                            <div class="forgot-password-link">
                                <a href="<?php echo $root; ?>/authentication/forgot-password.php">Forgot password?</a>
                            </div>
                        </div>
                        
                        <button type="submit" class="auth-btn">Sign In</button>
                    </form>
                </div>

                <div class="social-section">
                    <div class="divider">
                        <span>or sign in with</span>
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
                    <p>Don't have an account? <a href="<?php echo $root; ?>/authentication/sign-up.php">Create one</a></p>
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
    </script>
</body>
</html>