<?php
/**
 * KiTAcc - Login Page
 */
require_once __DIR__ . '/includes/config.php';

// Handle logout
if (isset($_GET['logout'])) {
    logoutUser();
    header('Location: login.php');
    exit;
}

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// Handle login via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAjax()) {
    header('Content-Type: application/json');

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Please enter username and password.']);
        exit;
    }

    $result = loginUser($username, $password);
    echo json_encode($result);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - KiTAcc</title>
    <meta name="csrf-token" content="<?php echo generateCsrfToken(); ?>">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>
    <div class="login-page">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <i class="fas fa-donate"></i>
                </div>
                <h1 class="login-title">KiTAcc</h1>
                <p class="login-subtitle">
                    <?php echo htmlspecialchars(getChurchName()); ?> — Church Account Made Easy
                </p>
            </div>

            <div id="loginAlert" style="display: none;" class="alert alert-danger mb-4">
                <i class="fas fa-exclamation-circle alert-icon"></i>
                <span id="loginAlertText"></span>
            </div>

            <?php if (isset($_GET['timeout'])): ?>
                <div class="alert alert-warning mb-4">
                    <i class="fas fa-clock alert-icon"></i>
                    <span>Your session has expired due to inactivity. Please login again.</span>
                </div>
            <?php endif; ?>

            <form id="loginForm" class="login-form" data-validate>
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <i class="fas fa-user input-group-icon"></i>
                        <input type="text" id="username" name="username" class="form-control has-icon-left"
                            placeholder="Enter username" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock input-group-icon"></i>
                        <input type="password" id="password" name="password"
                            class="form-control has-icon-left has-icon-right" placeholder="Enter password" required>
                        <button type="button" class="input-group-icon-right toggle-password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" id="loginBtn" class="btn btn-primary w-full btn-lg">
                    <span id="loginBtnText">Sign In</span>
                    <span id="loginBtnSpinner" style="display: none;" class="spinner"></span>
                </button>
            </form>

            <div style="text-align: center; margin-top: 0.75rem;">
                <a href="forgot_password.php"
                    style="color: var(--primary); font-size: 0.8125rem; text-decoration: none;">
                    Forgot Password?
                </a>
            </div>

            <div class="login-footer">
                <p>&copy;
                    <?php echo date('Y'); ?>
                    <?php echo htmlspecialchars(getChurchName()); ?>
                </p>
                <p>KiTAcc - Built with <span style="color:#e74c3c;">&hearts;</span> by
                    <a href="https://www.acreativemagic.com/" target="_blank" rel="noopener"
                        style="color: var(--primary); font-weight: 600; text-decoration: none;">CreativeMagic</a>.
                </p>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script src="js/app.js"></script>
    <script>
        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');
        const loginBtnText = document.getElementById('loginBtnText');
        const loginBtnSpinner = document.getElementById('loginBtnSpinner');
        const loginAlert = document.getElementById('loginAlert');
        const loginAlertText = document.getElementById('loginAlertText');

        // Password toggle
        document.querySelector('.toggle-password').addEventListener('click', function () {
            const input = this.parentElement.querySelector('input');
            const icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });

        loginForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;

            if (!username || !password) {
                showAlert('Please enter username and password.');
                return;
            }

            // Set loading state
            loginBtn.disabled = true;
            loginBtnText.textContent = 'Signing in...';
            loginBtnSpinner.style.display = 'inline-block';
            loginAlert.style.display = 'none';

            const formData = new FormData();
            formData.append('username', username);
            formData.append('password', password);
            formData.append('csrf_token', KiTAcc.getCsrfToken());

            KiTAcc.postForm('login.php', formData, function (response) {
                if (response.success) {
                    loginBtnText.textContent = 'Success! Redirecting...';
                    window.location.href = response.redirect || 'dashboard.php';
                } else {
                    showAlert(response.message || 'Login failed.');
                    resetButton();
                }
            }, function () {
                showAlert('Connection error. Please try again.');
                resetButton();
            });
        });

        function showAlert(msg) {
            loginAlertText.textContent = msg;
            loginAlert.style.display = 'flex';
        }

        function resetButton() {
            loginBtn.disabled = false;
            loginBtnText.textContent = 'Sign In';
            loginBtnSpinner.style.display = 'none';
        }
    </script>
</body>

</html>