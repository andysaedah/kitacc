<?php
/**
 * KiTAcc - Categories API
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
            $type = $_POST['type'] ?? '';
            if (!$name || !in_array($type, ['income', 'expense', 'claim']))
                throw new Exception('Name and type are required.');
            $pdo->prepare("INSERT INTO categories (name, type) VALUES (?, ?)")->execute([$name, $type]);
            echo json_encode(['success' => true, 'message' => 'Category created.']);
            break;
        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $pdo->prepare("UPDATE categories SET name = ?, type = ? WHERE id = ?")->execute([trim($_POST['name']), $_POST['type'], $id]);
            echo json_encode(['success' => true, 'message' => 'Category updated.']);
            break;
        case 'delete':
            $id = intval($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE category_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0)
                throw new Exception('Cannot delete category with existing transactions.');
            $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Category deleted.']);
            break;
        case 'toggle_active':
            $id = intval($_POST['id'] ?? 0);
            $isActive = intval($_POST['is_active'] ?? 0);
            $pdo->prepare("UPDATE categories SET is_active = ? WHERE id = ?")->execute([$isActive, $id]);
            echo json_encode(['success' => true, 'message' => $isActive ? 'Category activated.' : 'Category deactivated.']);
            break;
        default:
            throw new Exception('Invalid action.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
