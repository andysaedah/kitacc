<?php
/**
 * KiTAcc - Forgot Password
 * Self-service password reset request (no login required)
 */
require_once __DIR__ . '/includes/config.php';

// If already logged in, redirect
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$success = false;
$error = '';

// Handle form submission (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAjax()) {
    header('Content-Type: application/json');
    validateCsrf();

    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit;
    }

    // Always return success message to prevent email enumeration
    $genericMsg = 'If an account with that email exists, a password reset link has been sent.';

    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $result = generatePasswordResetToken($user['id']);

            if ($result['success']) {
                $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                    . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
                $resetLink = rtrim($baseUrl, '/') . '/reset_password.php?token=' . $result['token'];

                $htmlBody = buildResetEmail($user['name'], $resetLink);
                sendMail($user['email'], getSetting('app_name', 'KiTAcc') . ' - Password Reset', $htmlBody, $user['name']);
            }
            // If rate-limited, silently succeed (don't reveal timing)
        }

        auditLog('forgot_password_request', 'user', $user['id'] ?? null, ['email' => $email]);

    } catch (Exception $e) {
        // Fail silently — don't reveal internal errors
    }

    echo json_encode(['success' => true, 'message' => $genericMsg]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - KiTAcc</title>
    <meta name="csrf-token" content="<?php echo generateCsrfToken(); ?>">

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
                    <img src="img/logo-white.svg" alt="BWCC" class="login-logo-img">
                </div>
                <h1 class="login-title">Forgot Password</h1>
                <p class="login-subtitle">
                    Enter your email to receive a reset link
                </p>
            </div>

            <div id="alertBox" style="display: none;" class="alert mb-4">
                <i class="fas alert-icon" id="alertIcon"></i>
                <span id="alertText"></span>
            </div>

            <form id="forgotForm" class="login-form" style="display: block;">
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-group">
                        <i class="fas fa-envelope input-group-icon"></i>
                        <input type="email" id="email" name="email" class="form-control has-icon-left"
                            placeholder="Enter your registered email" required>
                    </div>
                </div>

                <button type="submit" id="submitBtn" class="btn btn-primary w-full btn-lg">
                    <span id="btnText">Send Reset Link</span>
                    <span id="btnSpinner" style="display: none;" class="spinner"></span>
                </button>
            </form>

            <div style="text-align: center; margin-top: 1rem;">
                <a href="login.php" style="color: var(--primary); font-size: 0.875rem; text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            </div>

            <div class="login-footer">
                <p>&copy;
                    <?php echo date('Y'); ?>
                    <?php echo htmlspecialchars(getChurchName()); ?>
                </p>
                <p><strong>KiTAcc</strong> — Church Account Made Easy</p>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script src="js/app.js"></script>
    <script>
        const form = document.getElementById('forgotForm');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const btnSpinner = document.getElementById('btnSpinner');
        const alertBox = document.getElementById('alertBox');
        const alertIcon = document.getElementById('alertIcon');
        const alertText = document.getElementById('alertText');

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            const email = document.getElementById('email').value.trim();
            if (!email) return;

            submitBtn.disabled = true;
            btnText.textContent = 'Sending...';
            btnSpinner.style.display = 'inline-block';
            alertBox.style.display = 'none';

            const formData = new FormData();
            formData.append('email', email);
            formData.append('csrf_token', KiTAcc.getCsrfToken());

            KiTAcc.postForm('forgot_password.php', formData, function (res) {
                showAlert(res.message, res.success ? 'success' : 'error');
                if (res.success) {
                    form.style.display = 'none';
                }
                resetBtn();
            }, function () {
                showAlert('Connection error. Please try again.', 'error');
                resetBtn();
            });
        });

        function showAlert(msg, type) {
            alertBox.className = 'alert mb-4 alert-' + (type === 'success' ? 'success' : 'danger');
            alertIcon.className = 'fas alert-icon fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle');
            alertText.textContent = msg;
            alertBox.style.display = 'flex';
        }

        function resetBtn() {
            submitBtn.disabled = false;
            btnText.textContent = 'Send Reset Link';
            btnSpinner.style.display = 'none';
        }
    </script>
</body>

</html>