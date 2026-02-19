<?php
/**
 * KiTAcc - Generic CRUD API: Accounts
 */
require_once __DIR__ . '/../includes/config.php';
requireLogin();
requireRole(ROLE_BRANCH_FINANCE);
header('Content-Type: application/json');

try {
    $pdo = db();
    $user = getCurrentUser();
    $branchId = getActiveBranchId() ?? $user['branch_id'];
    $action = $_POST['action'] ?? '';
    validateCsrf();

    switch ($action) {
        case 'create':
            $name = trim($_POST['name'] ?? '');
            $type = $_POST['type'] ?? 'bank';
            $accountNumber = trim($_POST['account_number'] ?? '');
            $targetBranch = ($user['role'] === ROLE_SUPERADMIN && !empty($_POST['branch_id'])) ? intval($_POST['branch_id']) : $branchId;
            if (!$name)
                throw new Exception('Name is required.');

            $pdo->prepare("INSERT INTO accounts (branch_id, name, type, account_number) VALUES (?, ?, ?, ?)")
                ->execute([$targetBranch, $name, $type, $accountNumber]);
            auditLog('account_created', 'accounts', $pdo->lastInsertId());
            echo json_encode(['success' => true, 'message' => 'Account created.']);
            break;

        case 'update':
            $id = intval($_POST['id'] ?? 0);
            if ($user['role'] === ROLE_SUPERADMIN) {
                $pdo->prepare("UPDATE accounts SET name = ?, type = ?, account_number = ? WHERE id = ?")
                    ->execute([trim($_POST['name']), $_POST['type'], trim($_POST['account_number'] ?? ''), $id]);
            } else {
                $pdo->prepare("UPDATE accounts SET name = ?, type = ?, account_number = ? WHERE id = ? AND branch_id = ?")
                    ->execute([trim($_POST['name']), $_POST['type'], trim($_POST['account_number'] ?? ''), $id, $branchId]);
            }
            auditLog('account_updated', 'accounts', $id);
            echo json_encode(['success' => true, 'message' => 'Account updated.']);
            break;

        case 'delete':
            $id = intval($_POST['id'] ?? 0);
            // Verify ownership
            if ($user['role'] !== ROLE_SUPERADMIN) {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE id = ? AND branch_id = ?");
                $chk->execute([$id, $branchId]);
                if ($chk->fetchColumn() == 0)
                    throw new Exception('Account not found.');
            }
            // Check if account has transactions
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE account_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0)
                throw new Exception('Cannot delete account with existing transactions.');
            $pdo->prepare("DELETE FROM accounts WHERE id = ?")->execute([$id]);
            auditLog('account_deleted', 'accounts', $id);
            echo json_encode(['success' => true, 'message' => 'Account deleted.']);
            break;

        default:
            throw new Exception('Invalid action.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
