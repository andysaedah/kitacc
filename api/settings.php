<?php
/**
 * KiTAcc - Settings API
 */
require_once __DIR__ . '/../includes/config.php';
requireLogin();
requireRole(ROLE_SUPERADMIN);
header('Content-Type: application/json');

try {
    $pdo = db();
    $action = $_POST['action'] ?? '';
    validateCsrf();

    if ($action === 'update') {
        $settings = [
            'app_name' => trim($_POST['app_name'] ?? 'KiTAcc'),
            'app_tagline' => trim($_POST['app_tagline'] ?? ''),
            'church_name' => trim($_POST['church_name'] ?? ''),
            'currency_symbol' => trim($_POST['currency_symbol'] ?? 'RM'),
            'timezone' => trim($_POST['timezone'] ?? 'Asia/Kuala_Lumpur'),
            'accounting_mode' => in_array($_POST['accounting_mode'] ?? '', ['simple', 'fund']) ? $_POST['accounting_mode'] : 'simple',
        ];

        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }

        // Clear settings cache
        unset($_SESSION['settings_cache']);
        auditLog('settings_updated', 'settings', null, json_encode($settings));
        echo json_encode(['success' => true, 'message' => 'Settings saved.']);
    } else {
        throw new Exception('Invalid action.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
