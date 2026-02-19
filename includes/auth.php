<?php
/**
 * KiTAcc - Authentication Helpers
 * Login, logout, session management, password handling
 */

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require login or redirect
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        if (isAjax()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
            exit;
        }
        header('Location: login.php');
        exit;
    }

    // Session inactivity timeout
    $timeoutMinutes = (int) getSetting('session_timeout', '30');
    $timeoutSeconds = $timeoutMinutes * 60;
    if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeoutSeconds) {
        logoutUser();
        if (isAjax()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Session expired due to inactivity. Please login again.']);
            exit;
        }
        header('Location: login.php?timeout=1');
        exit;
    }

    // Absolute session lifetime: 12 hours max
    if (!empty($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 43200) {
        logoutUser();
        if (isAjax()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
            exit;
        }
        header('Location: login.php?timeout=1');
        exit;
    }

    // Update last activity timestamp
    $_SESSION['last_activity'] = time();

    // Validate session fingerprint (User-Agent binding)
    if (!empty($_SESSION['ua_fingerprint'])) {
        $currentFingerprint = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        if (!hash_equals($_SESSION['ua_fingerprint'], $currentFingerprint)) {
            logoutUser();
            if (isAjax()) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
                exit;
            }
            header('Location: login.php');
            exit;
        }
    }
}

/**
 * Validate password strength: min 8 chars, at least 1 number, at least 1 special char
 */
function validatePasswordStrength(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'Password must contain at least one number.';
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        return 'Password must contain at least one special character.';
    }
    return null; // valid
}

/**
 * Get current user data from session + database
 */
function getCurrentUser(): ?array
{
    if (!isLoggedIn())
        return null;

    // Cache in session to avoid repeated DB queries
    if (!empty($_SESSION['user_cache']) && $_SESSION['user_cache']['id'] == $_SESSION['user_id']) {
        return $_SESSION['user_cache'];
    }

    try {
        $stmt = db()->prepare("
            SELECT u.*, b.name AS branch_name 
            FROM users u 
            LEFT JOIN branches b ON u.branch_id = b.id 
            WHERE u.id = ? AND u.is_active = 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user) {
            // User no longer active or deleted
            session_destroy();
            return null;
        }

        // Build user data
        $userData = [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'] ?? '',
            'branch_id' => $user['branch_id'] ? (int) $user['branch_id'] : null,
            'branch_name' => $user['branch_name'] ?? 'No Branch',
            'role' => $user['role'],
            'role_label' => ROLE_LABELS[$user['role']] ?? $user['role'],
            'initials' => getInitials($user['name']),
        ];

        $_SESSION['user_cache'] = $userData;
        return $userData;

    } catch (Exception $e) {
        return null;
    }
}

/**
 * Clear user cache (call after profile updates)
 */
function clearUserCache(): void
{
    unset($_SESSION['user_cache']);
}

/**
 * Perform login
 */
function loginUser(string $username, string $password): array
{
    try {
        $pdo = db();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Rate limiting: max 5 failed attempts per IP in 15 minutes
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM audit_log 
             WHERE action = 'login_failed' AND ip_address = ? 
             AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        );
        $stmt->execute([$ip]);
        if ($stmt->fetchColumn() >= 5) {
            return ['success' => false, 'message' => 'Too many login attempts. Please try again in 15 minutes.'];
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            auditLog('login_failed', 'user', null, ['username' => $username]);
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            auditLog('login_failed', 'user', $user['id'], ['username' => $username]);
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        // Rotate CSRF token
        unset($_SESSION['csrf_token']);

        // Bind session to User-Agent
        $_SESSION['ua_fingerprint'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');

        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['last_activity'] = time();
        $_SESSION['login_time'] = time();
        clearUserCache();

        // Audit log
        auditLog('login', 'user', $user['id']);

        return ['success' => true, 'message' => 'Login successful.', 'redirect' => 'dashboard.php'];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Login failed. Please try again.'];
    }
}

/**
 * Perform logout
 */
function logoutUser(): void
{
    $userId = $_SESSION['user_id'] ?? null;
    if ($userId) {
        auditLog('logout', 'user', $userId);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}

/**
 * Change user password
 */
function changePassword(int $userId, string $currentPassword, string $newPassword): array
{
    try {
        $stmt = db()->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'message' => 'User not found.'];
        }

        if (!password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect.'];
        }

        $pwError = validatePasswordStrength($newPassword);
        if ($pwError) {
            return ['success' => false, 'message' => $pwError];
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = db()->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $userId]);

        auditLog('change_password', 'user', $userId);

        return ['success' => true, 'message' => 'Password changed successfully.'];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to change password.'];
    }
}
