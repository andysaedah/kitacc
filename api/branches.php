<?php
/**
 * KiTAcc - Branches API (Superadmin only)
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
            if (!$name)
                throw new Exception('Name is required.');
            $pdo->prepare("INSERT INTO branches (name, address, phone) VALUES (?, ?, ?)")
                ->execute([$name, trim($_POST['address'] ?? ''), trim($_POST['phone'] ?? '')]);
            auditLog('branch_created', 'branches', $pdo->lastInsertId());
            echo json_encode(['success' => true, 'message' => 'Branch created.']);
            break;
        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $pdo->prepare("UPDATE branches SET name = ?, address = ?, phone = ? WHERE id = ?")
                ->execute([trim($_POST['name']), trim($_POST['address'] ?? ''), trim($_POST['phone'] ?? ''), $id]);
            auditLog('branch_updated', 'branches', $id);
            echo json_encode(['success' => true, 'message' => 'Branch updated.']);
            break;
        case 'toggle_active':
            $id = intval($_POST['id'] ?? 0);
            $isActive = intval($_POST['is_active'] ?? 0);
            $pdo->prepare("UPDATE branches SET is_active = ? WHERE id = ?")->execute([$isActive, $id]);
            auditLog('branch_toggle_active', 'branches', $id, "is_active: $isActive");
            echo json_encode(['success' => true, 'message' => $isActive ? 'Branch activated.' : 'Branch deactivated.']);
            break;
        default:
            throw new Exception('Invalid action.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
