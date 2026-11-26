<?php
// authentication/forgot-password.php
// Step 1: enter email. Step 2: enter the 6-digit code. Step 3: choose a new password.
// Each step talks to forgot-password-process.php.

require_once __DIR__ . '/../core/init.php';
require_once __DIR__ . '/../core/session-handler.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/url-helper.php';

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - SHOPWAVE</title>
    
    <!-- Link to the Master CSS File -->
    <link rel="stylesheet" href="<?php echo asset('design/web-design/auth-style.css'); ?>">
    
    <style>
        /* Custom styles specifically for the reset steps */
        .reset-step {
            overflow: hidden;
            max-height: 0;
            opacity: 0;
            transition: max-height 0.45s ease, opacity 0.35s ease;
        }
        .reset-step.visible {
            max-height: 800px; /* Increased to fit new password fields */
            opacity: 1;
        }
        
        /* Status Messages */
        .status-msg {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            opacity: 0;
            transform: translateY(-6px);
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        .status-msg.show {
            opacity: 1;
            transform: translateY(0);
        }
        .status-msg.error   { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .status-msg.success { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
        .status-msg.loading { background: #eff6ff; color: #2563eb; border: 1px solid #93c5fd; }
        
        /* Spinner */
        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid currentColor;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            flex-shrink: 0;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        /* Confirmed Email Box */
        .email-confirmed {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: #059669;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding: 0.75rem 1rem;
            background: #ecfdf5;
            border-radius: 8px;
            border: 1px solid #a7f3d0;
        }
        
        .divider {
            border: none;
            border-top: 1px solid var(--border-color);
            margin: 1.5rem 0;
        }
        
        #email:disabled {
            background: #f3f4f6;
            color: #9ca3af;
            cursor: not-allowed;
        }
        
        .code-input {
            text-align: center;
            font-size: 1.5rem;
            font-weight: 600;
            letter-spacing: 0.5em;
            padding-left: 0.5em; /* Offset the letter spacing for perfect centering */
        }
        
        .code-wait {
            font-size: 0.875rem;
            color: #2563eb;
            margin-bottom: 1rem;
            min-height: 1.2em;
        }
        
        .switch-email {
            text-align: center;
            margin-top: 1rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="page-background">
        
        <div class="auth-container">
            
            <!-- LEFT SIDE: Branding -->
            <div class="branding-panel">
                <div class="branding-content">
                    <h2>Reset Your<br>Password.</h2>
                    <p>We'll get you back to shopping in no time.</p>
                </div>
            </div>

            <!-- RIGHT SIDE: Form -->
            <div class="form-panel">
                
                <div class="login-header">
                    <h1>Reset Password</h1>
                    <p>Enter your email and we'll send you a 6-digit code.</p>
                </div>
                
                <div class="login-body">

                    <div id="statusMsg" class="status-msg" role="alert"></div>

                    <!-- Step 1: email -->
                    <div id="step1">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email"
                                placeholder="Enter your registered email"
                                maxlength="100"
                                autocomplete="email">
                        </div>
                        <button type="button" class="auth-btn" id="checkEmailBtn" onclick="checkEmail()">
                            Send Code
                        </button>
                    </div>

                    <!-- Step 2: code -->
                    <div id="step2" class="reset-step">
                        <hr class="divider">
                        <p id="codeWait" class="code-wait"></p>
                        <div class="form-group">
                            <label for="code">6-digit code</label>
                            <input type="text" id="code" name="code" class="code-input"
                                placeholder="------"
                                inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                                autocomplete="one-time-code">
                        </div>
                        <button type="button" class="auth-btn" id="verifyCodeBtn" onclick="verifyCode()">
                            Verify Code
                        </button>
                        <p class="switch-email">
                            <a href="forgot-password.php">Use a different email</a>
                        </p>
                    </div>

                    <!-- Step 3: new password -->
                    <div id="step3" class="reset-step">
                        <hr class="divider">
                        <div id="emailConfirmed" class="email-confirmed">
                            <svg xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px" fill="currentColor">
                                <path d="m424-296 282-282-56-56-226 226-114-114-56 56 170 170Zm56 216q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/>
                            </svg>
                            <span id="confirmedEmailText"></span>
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="new_password" placeholder="Enter new password" autocomplete="new-password">
                                <button type="button" class="toggle-password" id="toggleNew" onclick="togglePw('new_password', 'toggleNew')">
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
                            <label for="confirm_password">Confirm New Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="confirm_password" placeholder="Confirm new password" autocomplete="new-password">
                                <button type="button" class="toggle-password" id="toggleConfirm" onclick="togglePw('confirm_password', 'toggleConfirm')">
                                    <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor" class="eye-open">
                                        <path d="M480-320q75 0 127.5-52.5T660-500q0-75-52.5-127.5T480-680q-75 0-127.5 52.5T300-500q0 75 52.5 127.5T480-320Zm0-72q-45 0-76.5-31.5T372-500q0-45 31.5-76.5T480-608q45 0 76.5 31.5T588-500q0 45-31.5 76.5T480-392Zm0 192q-146 0-266-81.5T40-500q54-137 174-218.5T480-800q146 0 266 81.5T920-500q-54 137-174 218.5T480-200Z"/>
                                    </svg>
                                    <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor" class="eye-closed" style="display:none;">
                                        <path d="m644-428-58-58q9-47-27-88t-93-32l-58-58q17-8 34.5-12t37.5-4q75 0 127.5 52.5T660-500q0 20-4 37.5T644-428Zm128 126-58-56q38-29 67.5-63.5T832-500q-50-101-143.5-160.5T480-720q-29 0-57 4t-55 12l-62-62q41-17 84-25.5t90-8.5q151 0 269 83.5T920-500q-23 59-60.5 109.5T772-302Zm20 246L624-224q-35 11-70.5 17.5T480-200q-151 0-269-83.5T40-500q21-53 53-98.5t73-81.5L56-792l56-56 736 736-56 56ZM222-624q-29 26-53 57t-41 67q50 101 143.5 160.5T480-280q20 0 39-2.5t39-7.5l-36-38q-11 3-21 5t-21 2q-75 0-127.5-52.5T300-500q0-11 2-21t5-21l-85-82Z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <p style="font-size:0.75rem; color:#6b7280; margin-bottom:1rem;">
                            8 to 72 characters, with at least 3 of: uppercase, lowercase, numbers, symbols.
                        </p>
                        
                        <button type="button" class="auth-btn" id="resetBtn" onclick="resetPassword()">
                            Save New Password
                        </button>
                    </div>

                </div>
                
                <div class="login-footer">
                    <p>Remember your password? <a href="login-page.php">Login</a></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        let flowEmail = '';
        const csrfToken = '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>';

        function showStatus(msg, type) {
            const el = document.getElementById('statusMsg');
            el.className = 'status-msg ' + type;
            el.innerHTML = type === 'loading' ? '<span class="spinner"></span>' : '';
            const text = document.createElement('span');
            text.textContent = msg; 
            el.appendChild(text);
            requestAnimationFrame(() => el.classList.add('show'));
        }

        function showStep(id) {
            const step = document.getElementById(id);
            step.classList.add('visible');
            const field = step.querySelector('input');
            if (field) field.focus();
        }

        function togglePw(inputId, btnId) {
            const input = document.getElementById(inputId);
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            const btn = document.getElementById(btnId);
            
            const eyeOpen = btn.querySelector('.eye-open');
            const eyeClosed = btn.querySelector('.eye-closed');
            
            if (isHidden) {
                eyeOpen.style.display = 'none';
                eyeClosed.style.display = 'block';
            } else {
                eyeOpen.style.display = 'block';
                eyeClosed.style.display = 'none';
            }
        }

        // Show the eye icon once the user starts typing.
        ['new_password', 'confirm_password'].forEach(id => {
            const input = document.getElementById(id);
            const toggleId = id === 'new_password' ? 'toggleNew' : 'toggleConfirm';
            input.addEventListener('input', () => {
                document.getElementById(toggleId).classList.toggle('visible', input.value.length > 0);
            });
        });

        function post(action, fields) {
            const body = new URLSearchParams({ action: action, csrf_token: csrfToken, ...fields });
            return fetch('forgot-password-process.php', { method: 'POST', body: body, credentials: 'same-origin' })
                .then(r => r.json());
        }

        function startCodeWait(code) {
            const wait      = document.getElementById('codeWait');
            const codeInput = document.getElementById('code');
            const verifyBtn = document.getElementById('verifyCodeBtn');

            verifyBtn.disabled = true;
            let seconds = 5;
            wait.textContent = 'Sending the code to your email... (testing mode) ' + seconds + '...';

            const timer = setInterval(() => {
                seconds--;
                if (seconds > 0) {
                    wait.textContent = 'Sending the code to your email... (testing mode) ' + seconds + '...';
                } else {
                    clearInterval(timer);
                    codeInput.value = code;
                    wait.textContent = 'Code received. (testing mode: filled in for you)';
                    verifyBtn.disabled = false;
                }
            }, 1000);
        }

        function checkEmail() {
            const email = document.getElementById('email').value.trim();
            if (!email) { showStatus('Please enter your email address.', 'error'); return; }

            const btn = document.getElementById('checkEmailBtn');
            btn.disabled = true;
            btn.textContent = 'Sending...';
            showStatus('Sending code...', 'loading');

            post('request_code', { email: email })
                .then(data => {
                    if (data.success) {
                        flowEmail = email;
                        showStatus(data.message, 'success');
                        document.getElementById('email').disabled = true;
                        btn.style.display = 'none';
                        showStep('step2');

                        if (data.dev_code) {
                            startCodeWait(data.dev_code);
                        } else {
                            document.getElementById('codeWait').textContent = 'Check your email for the 6-digit code.';
                        }
                    } else {
                        showStatus(data.message || 'Something went wrong. Please try again.', 'error');
                        btn.disabled = false;
                        btn.textContent = 'Send Code';
                    }
                })
                .catch(() => {
                    showStatus('Something went wrong. Please try again.', 'error');
                    btn.disabled = false;
                    btn.textContent = 'Send Code';
                });
        }

        function verifyCode() {
            const code = document.getElementById('code').value.trim();
            if (!/^\d{6}$/.test(code)) { showStatus('Enter the 6-digit code from your email.', 'error'); return; }

            const btn = document.getElementById('verifyCodeBtn');
            btn.disabled = true;
            btn.textContent = 'Checking...';

            post('verify_code', { code: code })
                .then(data => {
                    if (data.success) {
                        showStatus(data.message, 'success');
                        document.getElementById('confirmedEmailText').textContent = flowEmail;
                        document.getElementById('step2').classList.remove('visible');
                        showStep('step3');
                    } else {
                        showStatus(data.message || 'Wrong code. Please try again.', 'error');
                        btn.disabled = false;
                        btn.textContent = 'Verify Code';
                    }
                })
                .catch(() => {
                    showStatus('Something went wrong. Please try again.', 'error');
                    btn.disabled = false;
                    btn.textContent = 'Verify Code';
                });
        }

        function resetPassword() {
            const newPass     = document.getElementById('new_password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            if (!newPass || !confirmPass) { showStatus('Please fill in both password fields.', 'error'); return; }
            if (newPass !== confirmPass)  { showStatus('Passwords do not match.', 'error'); return; }

            const btn = document.getElementById('resetBtn');
            btn.disabled = true;
            btn.textContent = 'Saving...';

            post('reset_password', { new_password: newPass, confirm_password: confirmPass })
                .then(data => {
                    if (data.success) {
                        showStatus(data.message, 'success');
                        setTimeout(() => { window.location.href = data.redirect; }, 1200);
                    } else {
                        showStatus(data.message || 'Could not save the password. Please try again.', 'error');
                        btn.disabled = false;
                        btn.textContent = 'Save New Password';
                    }
                })
                .catch(() => {
                    showStatus('Something went wrong. Please try again.', 'error');
                    btn.disabled = false;
                    btn.textContent = 'Save New Password';
                });
        }

        document.getElementById('email').addEventListener('keydown', e => {
            if (e.key === 'Enter') checkEmail();
        });
        document.getElementById('code').addEventListener('keydown', e => {
            if (e.key === 'Enter') verifyCode();
        });
        document.getElementById('confirm_password').addEventListener('keydown', e => {
            if (e.key === 'Enter') resetPassword();
        });
    </script>
</body>
</html>