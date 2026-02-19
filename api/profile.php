<?php
/**
 * KiTAcc - Profile API
 */
require_once __DIR__ . '/../includes/config.php';
requireLogin();
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$user = getCurrentUser();

try {
    $pdo = db();
    validateCsrf();

    switch ($action) {
        case 'update_profile':
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            if (!$name || !$email)
                throw new Exception('Name and email are required.');

            $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?")
                ->execute([$name, $email, $phone, $user['id']]);

            // Clear session cache
            unset($_SESSION['user_data']);
            auditLog('profile_updated', 'users', $user['id']);
            echo json_encode(['success' => true, 'message' => 'Profile updated.']);
            break;

        case 'change_password':
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $pwError = validatePasswordStrength($new);
            if ($pwError)
                throw new Exception($pwError);

            $result = changePassword($user['id'], $current, $new);
            echo json_encode($result);
            break;

        default:
            throw new Exception('Invalid action.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
