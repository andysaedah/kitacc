<?php
/**
 * KiTAcc - Account Types API (Superadmin only)
 * CRUD + toggle active for account types
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
            $description = trim($_POST['description'] ?? '');
            if (!$name)
                throw new Exception('Name is required.');

            // Check for duplicate name
            $chk = $pdo->prepare("SELECT COUNT(*) FROM account_types WHERE name = ?");
            $chk->execute([$name]);
            if ($chk->fetchColumn() > 0)
                throw new Exception('An account type with this name already exists.');

            $pdo->prepare("INSERT INTO account_types (name, description) VALUES (?, ?)")
                ->execute([$name, $description ?: null]);
            auditLog('account_type_created', 'account_types', $pdo->lastInsertId());
            echo json_encode(['success' => true, 'message' => 'Account type created.']);
            break;

        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            if (!$name)
                throw new Exception('Name is required.');

            // Check for duplicate name (exclude self)
            $chk = $pdo->prepare("SELECT COUNT(*) FROM account_types WHERE name = ? AND id != ?");
            $chk->execute([$name, $id]);
            if ($chk->fetchColumn() > 0)
                throw new Exception('An account type with this name already exists.');

            $pdo->prepare("UPDATE account_types SET name = ?, description = ? WHERE id = ?")
                ->execute([$name, $description ?: null, $id]);
            auditLog('account_type_updated', 'account_types', $id);
            echo json_encode(['success' => true, 'message' => 'Account type updated.']);
            break;

        case 'delete':
            $id = intval($_POST['id'] ?? 0);
            // Check if any accounts use this type
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE account_type_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0)
                throw new Exception('Cannot delete account type that is assigned to existing accounts.');

            $pdo->prepare("DELETE FROM account_types WHERE id = ?")->execute([$id]);
            auditLog('account_type_deleted', 'account_types', $id);
            echo json_encode(['success' => true, 'message' => 'Account type deleted.']);
            break;

        case 'toggle_active':
            $id = intval($_POST['id'] ?? 0);
            $isActive = intval($_POST['is_active'] ?? 0);
            $pdo->prepare("UPDATE account_types SET is_active = ? WHERE id = ?")->execute([$isActive, $id]);
            auditLog($isActive ? 'account_type_activated' : 'account_type_deactivated', 'account_types', $id);
            echo json_encode(['success' => true, 'message' => $isActive ? 'Account type activated.' : 'Account type deactivated.']);
            break;

        default:
            throw new Exception('Invalid action.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
