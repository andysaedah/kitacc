<?php
/**
 * KiTAcc - Core Configuration
 * Environment loading, helpers, CSRF, RBAC constants
 */

// ========================================
// ENVIRONMENT LOADING
// ========================================
function loadEnv(string $path): void
{
    if (!file_exists($path))
        return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#')
            continue;
        if (strpos($line, '=') === false)
            continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // Remove surrounding quotes
        if (preg_match('/^"(.*)"$/', $value, $m))
            $value = $m[1];
        if (preg_match("/^'(.*)'$/", $value, $m))
            $value = $m[1];
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }
}

// Load .env from project root
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    loadEnv($envPath);
} else {
    // Fallback to .env.example for initial setup
    loadEnv(__DIR__ . '/../.env.example');
}

// Set timezone
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Kuala_Lumpur');

// ========================================
// ERROR DISPLAY (based on APP_DEBUG)
// ========================================
$appDebug = filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
if ($appDebug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

// ========================================
// SESSION
// ========================================
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    // Enable secure cookies when on HTTPS
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// ========================================
// DATABASE
// ========================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/pagination.php';

// Override timezone from database setting (if available)
try {
    $dbTimezone = getSetting('timezone', '');
    if ($dbTimezone) {
        date_default_timezone_set($dbTimezone);
    }
} catch (Exception $e) {
    // DB not ready yet, keep .env timezone
}

// ========================================
// ROLE CONSTANTS
// ========================================
define('ROLE_SUPERADMIN', 'superadmin');
define('ROLE_ADMIN_FINANCE', 'admin_finance');
define('ROLE_BRANCH_FINANCE', 'branch_finance');
define('ROLE_USER', 'user');
define('ROLE_VIEWER', 'viewer');

// Role hierarchy (higher number = more access)
define('ROLE_LEVELS', [
    ROLE_VIEWER => 1,
    ROLE_USER => 2,
    ROLE_BRANCH_FINANCE => 3,
    ROLE_ADMIN_FINANCE => 4,
    ROLE_SUPERADMIN => 5,
]);

define('ROLE_LABELS', [
    ROLE_SUPERADMIN => 'Super Admin',
    ROLE_ADMIN_FINANCE => 'Admin Finance',
    ROLE_BRANCH_FINANCE => 'Branch Finance',
    ROLE_USER => 'User',
    ROLE_VIEWER => 'Viewer',
]);

// ========================================
// RBAC HELPERS
// ========================================

/**
 * Check if current user has at least the given role level
 */
function hasRole(string $minimumRole): bool
{
    $user = getCurrentUser();
    if (!$user)
        return false;
    $userLevel = ROLE_LEVELS[$user['role']] ?? 0;
    $requiredLevel = ROLE_LEVELS[$minimumRole] ?? 999;
    return $userLevel >= $requiredLevel;
}

/**
 * Require a minimum role or die with 403
 */
function requireRole(string $minimumRole): void
{
    if (!hasRole($minimumRole)) {
        if (isAjax()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            exit;
        }
        include __DIR__ . '/../403.php';
        exit;
    }
}

/**
 * Check if user can access a branch's data
 */
function canAccessBranch(int $branchId): bool
{
    $user = getCurrentUser();
    if (!$user)
        return false;
    if ($user['role'] === ROLE_SUPERADMIN)
        return true;
    return (int) $user['branch_id'] === $branchId;
}

/**
 * Get the branch ID to filter by (null = all branches for superadmin)
 */
function getActiveBranchId(): ?int
{
    $user = getCurrentUser();
    if (!$user)
        return null;
    if ($user['role'] === ROLE_SUPERADMIN)
        return null; // sees all
    return (int) $user['branch_id'];
}

// ========================================
// CSRF PROTECTION
// ========================================

function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken(?string $token): bool
{
    if (empty($token) || empty($_SESSION['csrf_token']))
        return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrf(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCsrfToken($token)) {
        if (isAjax()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
            exit;
        }
        include __DIR__ . '/../403.php';
        exit;
    }
}

// ========================================
// HELPERS
// ========================================

function base_url(string $path = ''): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script_dir = dirname($_SERVER['SCRIPT_NAME']);
    $base = $protocol . '://' . $host . rtrim($script_dir, '/');
    return $base . '/' . ltrim($path, '/');
}

function formatCurrency(float $amount): string
{
    $symbol = getSetting('currency_symbol', 'RM');
    return $symbol . ' ' . number_format($amount, 2);
}

function formatDate(string $date): string
{
    return date('d M Y', strtotime($date));
}

function formatDateTime(string $datetime): string
{
    return date('d M Y, h:i A', strtotime($datetime));
}

function getInitials(string $name): string
{
    $parts = explode(' ', trim($name));
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= strtoupper(mb_substr($part, 0, 1));
    }
    return $initials ?: '?';
}

function isAjax(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// ========================================
// SETTINGS HELPERS
// ========================================

function getSetting(string $key, string $default = ''): string
{
    static $cache = [];
    if (isset($cache[$key]))
        return $cache[$key];

    try {
        $stmt = db()->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $cache[$key] = $row ? ($row['setting_value'] ?? $default) : $default;
    } catch (Exception $e) {
        $cache[$key] = $default;
    }
    return $cache[$key];
}

function setSetting(string $key, string $value): bool
{
    try {
        $stmt = db()->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                               ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
        return $stmt->execute([$key, $value, $value]);
    } catch (Exception $e) {
        return false;
    }
}

function isSimpleMode(): bool
{
    return getSetting('accounting_mode', 'simple') === 'simple';
}

function isFundMode(): bool
{
    return getSetting('accounting_mode', 'simple') === 'fund';
}

function getChurchName(): string
{
    return getSetting('church_name', getenv('CHURCH_NAME') ?: 'My Church');
}

/**
 * Calculate the current balance of a fund.
 * For General Fund: Total Account Balances minus all other fund balances (guarantees totals match).
 * For other funds: income - expenses + transfers_in - transfers_out.
 * Returns the calculated balance as a float.
 */
function getFundBalance(int $fundId): float
{
    $pdo = db();

    // Get fund details
    $stmt = $pdo->prepare("SELECT id, name, branch_id FROM funds WHERE id = ?");
    $stmt->execute([$fundId]);
    $fund = $stmt->fetch();
    if (!$fund) return 0.0;

    if ($fund['name'] === 'General Fund') {
        // General Fund = Total Account Balances - Sum of all other active fund balances
        $branchId = $fund['branch_id'];

        // Total account balances for this branch
        $stmt2 = $pdo->prepare("SELECT COALESCE(SUM(balance), 0) FROM accounts WHERE is_active = 1 AND branch_id = ?");
        $stmt2->execute([$branchId]);
        $totalAccounts = floatval($stmt2->fetchColumn());

        // Sum of all other (non-General) fund balances for this branch
        $stmt3 = $pdo->prepare("SELECT f.id,
                    COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.fund_id = f.id AND t.type = 'income'), 0)
                    - COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.fund_id = f.id AND t.type = 'expense'), 0)
                    + COALESCE((SELECT SUM(ft.amount) FROM fund_transfers ft WHERE ft.to_fund_id = f.id), 0)
                    - COALESCE((SELECT SUM(ft.amount) FROM fund_transfers ft WHERE ft.from_fund_id = f.id), 0)
                    AS balance
                FROM funds f
                WHERE f.is_active = 1 AND f.branch_id = ? AND f.name != 'General Fund'");
        $stmt3->execute([$branchId]);
        $otherFundsTotal = 0.0;
        while ($row = $stmt3->fetch()) {
            $otherFundsTotal += floatval($row['balance']);
        }

        return $totalAccounts - $otherFundsTotal;
    } else {
        // Regular fund: income - expenses + transfers in - transfers out
        $stmt2 = $pdo->prepare("SELECT
                    COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.fund_id = f.id AND t.type = 'income'), 0)
                    - COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.fund_id = f.id AND t.type = 'expense'), 0)
                    + COALESCE((SELECT SUM(ft.amount) FROM fund_transfers ft WHERE ft.to_fund_id = f.id), 0)
                    - COALESCE((SELECT SUM(ft.amount) FROM fund_transfers ft WHERE ft.from_fund_id = f.id), 0)
                    AS balance
                FROM funds f WHERE f.id = ?");
        $stmt2->execute([$fundId]);
        $row = $stmt2->fetch();
        return $row ? floatval($row['balance']) : 0.0;
    }
}

/**
 * Get the General Fund ID for a given branch.
 * Returns fund ID or null if not found.
 */
function getGeneralFundId(int $branchId): ?int
{
    $pdo = db();
    $stmt = $pdo->prepare("SELECT id FROM funds WHERE name = 'General Fund' AND branch_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$branchId]);
    $id = $stmt->fetchColumn();
    return $id ? intval($id) : null;
}

// ========================================
// CSRF ALIAS
// ========================================

/**
 * Alias for requireCsrf — used by API endpoints
 */
function validateCsrf(): void
{
    requireCsrf();
}

// ========================================
// AUDIT LOG
// ========================================

/**
 * Log an action to the audit trail.
 * $oldValues / $newValues can be a string note, array, or null.
 */
function auditLog(string $action, string $entityType, $entityId = null, $oldValues = null, $newValues = null): void
{
    try {
        $user = getCurrentUser();
        if (is_array($oldValues))
            $oldValues = json_encode($oldValues);
        if (is_array($newValues))
            $newValues = json_encode($newValues);
        $stmt = db()->prepare("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user ? $user['id'] : null,
            $action,
            $entityType,
            $entityId ? intval($entityId) : null,
            $oldValues,
            $newValues,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        // silently fail - audit should not break app
    }
}

// ========================================
// UPLOAD HELPERS
// ========================================

function getUploadDir(string $subfolder = ''): string
{
    $base = __DIR__ . '/../' . (getenv('UPLOAD_DIR') ?: 'uploads');
    $dir = $base . ($subfolder ? '/' . ltrim($subfolder, '/') : '');
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function generateUploadFilename(string $originalName): string
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $datePrefix = date('Ymd');
    $random = bin2hex(random_bytes(4));
    return "{$datePrefix}_{$random}.{$ext}";
}

/**
 * Handle a file upload: validate, compress-move, return relative path.
 */
function handleUpload(array $file, string $subfolder = 'uploads'): ?string
{
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
    $maxSize = 10 * 1024 * 1024; // 10MB (client compresses to ~80%)

    if ($file['error'] !== UPLOAD_ERR_OK)
        return null;
    if ($file['size'] > $maxSize)
        throw new Exception('File too large (max 10MB).');

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed))
        throw new Exception('Invalid file type.');

    // Validate actual MIME type (extension alone can be spoofed)
    $allowedMimes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf'
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, $allowedMimes))
        throw new Exception('Invalid file type.');

    $dir = getUploadDir($subfolder);
    $filename = generateUploadFilename($file['name']);
    $destPath = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new Exception('Failed to save uploaded file.');
    }

    // Return relative path for storage
    // getUploadDir base is 'uploads/', so real path is 'uploads/' + subfolder + filename
    $baseName = getenv('UPLOAD_DIR') ?: 'uploads';
    return $baseName . '/' . ltrim($subfolder, '/') . '/' . $filename;
}
