<?php
/**
 * KiTAcc - Reset Password
 * Token-validated password reset form (no login required)
 */
require_once __DIR__ . '/includes/config.php';

// If already logged in, redirect
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$token = $_GET['token'] ?? '';
$tokenValid = false;
$tokenData = null;
$error = '';

if (empty($token)) {
    $error = 'No reset token provided.';
} else {
    $tokenData = validateResetToken($token);
    $tokenValid = $tokenData['valid'];
    if (!$tokenValid) {
        $error = $tokenData['message'];
    }
}

// Handle password reset (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAjax()) {
    header('Content-Type: application/json');
    validateCsrf();

    $postToken = $_POST['token'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($postToken)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }

    $validation = validateResetToken($postToken);
    if (!$validation['valid']) {
        echo json_encode(['success' => false, 'message' => $validation['message']]);
        exit;
    }

    $pwError = validatePasswordStrength($newPassword);
    if ($pwError) {
        echo json_encode(['success' => false, 'message' => $pwError]);
        exit;
    }

    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }

    try {
        $pdo = db();

        // Update password
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $validation['user_id']]);

        // Mark token as used
        consumeResetToken($validation['token_id']);

        auditLog('password_reset', 'user', $validation['user_id']);

        echo json_encode(['success' => true, 'message' => 'Password reset successful! Redirecting to login...']);
        exit;

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to reset password. Please try again.']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - KiTAcc</title>
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
                    <i class="fas fa-church"></i>
                </div>
                <h1 class="login-title">Reset Password</h1>
                <p class="login-subtitle">
                    <?php if ($tokenValid): ?>
                        Set a new password for <strong>
                            <?php echo htmlspecialchars($tokenData['name']); ?>
                        </strong>
                    <?php else: ?>
                        Password reset link
                    <?php endif; ?>
                </p>
            </div>

            <div id="alertBox" style="display: <?php echo $error ? 'flex' : 'none'; ?>;"
                class="alert mb-4 <?php echo $error ? 'alert-danger' : ''; ?>">
                <i class="fas alert-icon <?php echo $error ? 'fa-exclamation-circle' : ''; ?>" id="alertIcon"></i>
                <span id="alertText">
                    <?php echo htmlspecialchars($error); ?>
                </span>
            </div>

            <?php if ($tokenValid): ?>
                <form id="resetForm" class="login-form">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                    <div class="form-group">
                        <label for="new_password" class="form-label">New Password</label>
                        <div class="input-group">
                            <i class="fas fa-lock input-group-icon"></i>
                            <input type="password" id="new_password" name="new_password" class="form-control has-icon-left has-icon-right"
                                placeholder="Min 8 characters" required minlength="8">
                            <button type="button" class="input-group-icon-right toggle-password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <i class="fas fa-lock input-group-icon"></i>
                            <input type="password" id="confirm_password" name="confirm_password"
                                class="form-control has-icon-left has-icon-right" placeholder="Re-enter password" required minlength="8">
                            <button type="button" class="input-group-icon-right toggle-password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" id="submitBtn" class="btn btn-primary w-full btn-lg">
                        <span id="btnText">Reset Password</span>
                        <span id="btnSpinner" style="display: none;" class="spinner"></span>
                    </button>
                </form>
            <?php endif; ?>

            <?php if (!$tokenValid): ?>
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="forgot_password.php" class="btn btn-outline" style="margin-bottom: 0.75rem;">
                        <i class="fas fa-redo"></i> Request New Link
                    </a>
                </div>
            <?php endif; ?>

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
    <?php if ($tokenValid): ?>
        <script>
            const form = document.getElementById('resetForm');
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const btnSpinner = document.getElementById('btnSpinner');
            const alertBox = document.getElementById('alertBox');
            const alertIcon = document.getElementById('alertIcon');
            const alertText = document.getElementById('alertText');

            form.addEventListener('submit', function (e) {
                e.preventDefault();

                const pw = document.getElementById('new_password').value;
                const cpw = document.getElementById('confirm_password').value;

                if (pw.length < 8) {
                    showAlert('Password must be at least 8 characters.', 'error');
                    return;
                }
                if (pw !== cpw) {
                    showAlert('Passwords do not match.', 'error');
                    return;
                }

                submitBtn.disabled = true;
                btnText.textContent = 'Resetting...';
                btnSpinner.style.display = 'inline-block';
                alertBox.style.display = 'none';

                const formData = new FormData(form);
                formData.append('csrf_token', KiTAcc.getCsrfToken());

                KiTAcc.postForm('reset_password.php', formData, function (res) {
                    showAlert(res.message, res.success ? 'success' : 'error');
                    if (res.success) {
                        form.style.display = 'none';
                        setTimeout(function () { window.location.href = 'login.php'; }, 2000);
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
                btnText.textContent = 'Reset Password';
                btnSpinner.style.display = 'none';
            }
        </script>
    <?php endif; ?>
</body>

</html>