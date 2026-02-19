<?php
/**
 * KiTAcc - Users API (Superadmin only)
 */
require_once __DIR__ . '/../includes/config.php';
requireLogin();
requireRole(ROLE_SUPERADMIN);
header('Content-Type: application/json');

try {
    $pdo = db();
    $action = $_POST['action'] ?? '';
    validateCsrf();

    switch ($action) {
        case 'create':
            $name = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? ROLE_USER;
            $branchId = !empty($_POST['branch_id']) ? intval($_POST['branch_id']) : null;
            $phone = trim($_POST['phone'] ?? '');

            if (!$name || !$username || !$email || !$password)
                throw new Exception('All fields are required.');
            $pwError = validatePasswordStrength($password);
            if ($pwError)
                throw new Exception($pwError);

            // Check username uniqueness
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0)
                throw new Exception('Username already exists.');

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (branch_id, name, username, email, phone, password_hash, role) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$branchId, $name, $username, $email, $phone, $hash, $role]);

            auditLog('user_created', 'users', $pdo->lastInsertId(), "Role: $role");
            echo json_encode(['success' => true, 'message' => 'User created.']);
            break;

        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = $_POST['role'] ?? ROLE_USER;
            $branchId = !empty($_POST['branch_id']) ? intval($_POST['branch_id']) : null;
            $phone = trim($_POST['phone'] ?? '');

            $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ?, branch_id = ? WHERE id = ?")
                ->execute([$name, $email, $phone, $role, $branchId, $id]);

            // Reset password if provided
            if (!empty($_POST['password'])) {
                $pwError = validatePasswordStrength($_POST['password']);
                if ($pwError)
                    throw new Exception($pwError);
                $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $id]);
            }

            auditLog('user_updated', 'users', $id);
            echo json_encode(['success' => true, 'message' => 'User updated.']);
            break;
        case 'toggle_active':
            $id = intval($_POST['id'] ?? 0);
            $isActive = intval($_POST['is_active'] ?? 0);
            if ($id === 1 && !$isActive)
                throw new Exception('Cannot deactivate the primary superadmin.');
            $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$isActive, $id]);
            auditLog('user_toggle_active', 'users', $id, "is_active: $isActive");
            echo json_encode(['success' => true, 'message' => $isActive ? 'User activated.' : 'User deactivated.']);
            break;

        case 'reset_password':
            $id = intval($_POST['id'] ?? 0);
            if (!$id)
                throw new Exception('Invalid user.');

            // Get user info
            $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ? AND is_active = 1");
            $stmt->execute([$id]);
            $targetUser = $stmt->fetch();

            if (!$targetUser)
                throw new Exception('User not found or inactive.');
            if (empty($targetUser['email']))
                throw new Exception('User has no email address configured.');

            // Generate reset token
            $tokenResult = generatePasswordResetToken($targetUser['id']);
            if (!$tokenResult['success'])
                throw new Exception($tokenResult['message']);

            // Build reset link
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['SCRIPT_NAME']));
            $resetLink = rtrim($baseUrl, '/') . '/reset_password.php?token=' . $tokenResult['token'];

            // Send email
            $htmlBody = buildResetEmail($targetUser['name'], $resetLink);
            $mailResult = sendMail($targetUser['email'], getSetting('app_name', 'KiTAcc') . ' - Password Reset', $htmlBody, $targetUser['name']);

            if (!$mailResult['success'])
                throw new Exception('Failed to send email: ' . $mailResult['message']);

            auditLog('admin_reset_password', 'users', $id);
            echo json_encode(['success' => true, 'message' => 'Password reset link sent to ' . $targetUser['email']]);
            break;

        default:
            throw new Exception('Invalid action.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
