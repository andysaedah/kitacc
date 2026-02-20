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

    // Helper: fetch account snapshot for audit
    $getAccountSnapshot = function ($id) use ($pdo) {
        $s = $pdo->prepare("SELECT a.id, a.name, a.account_number, a.balance, a.is_active, a.is_default,
                                   a.branch_id, b.name AS branch_name,
                                   a.account_type_id, at.name AS account_type
                            FROM accounts a
                            LEFT JOIN branches b ON a.branch_id = b.id
                            LEFT JOIN account_types at ON a.account_type_id = at.id
                            WHERE a.id = ?");
        $s->execute([$id]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    };

    switch ($action) {
        case 'create':
            if ($user['role'] !== ROLE_SUPERADMIN)
                throw new Exception('Only superadmin can create accounts.');
            $name = trim($_POST['name'] ?? '');
            $accountNumber = trim($_POST['account_number'] ?? '');
            $accountTypeId = intval($_POST['account_type_id'] ?? 0);
            $balance = floatval($_POST['balance'] ?? 0);
            $targetBranch = !empty($_POST['branch_id']) ? intval($_POST['branch_id']) : $branchId;
            if (!$name)
                throw new Exception('Name is required.');
            if (!$accountTypeId)
                throw new Exception('Account type is required.');
            if ($balance < 0)
                throw new Exception('Starting balance cannot be negative.');

            $pdo->prepare("INSERT INTO accounts (branch_id, name, account_type_id, account_number, balance) VALUES (?, ?, ?, ?, ?)")
                ->execute([$targetBranch, $name, $accountTypeId, $accountNumber, $balance]);
            $newId = $pdo->lastInsertId();
            $newSnap = $getAccountSnapshot($newId);
            auditLog('account_created', 'accounts', $newId, null, $newSnap);
            echo json_encode(['success' => true, 'message' => 'Account created.']);
            break;

        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            if (!$name)
                throw new Exception('Name is required.');

            // Capture old state before update
            $oldSnap = $getAccountSnapshot($id);
            if (!$oldSnap)
                throw new Exception('Account not found.');

            if ($user['role'] === ROLE_SUPERADMIN) {
                $accountTypeId = intval($_POST['account_type_id'] ?? 0);
                if (!$accountTypeId)
                    throw new Exception('Account type is required.');

                // Check if balance change is allowed (only if no transactions)
                $updates = "name = ?, account_number = ?, account_type_id = ?, branch_id = ?";
                $updateParams = [$name, trim($_POST['account_number'] ?? ''), $accountTypeId, intval($_POST['branch_id'] ?? 0)];

                if (isset($_POST['balance'])) {
                    $newBalance = floatval($_POST['balance']);
                    $txnCheck = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE account_id = ?");
                    $txnCheck->execute([$id]);
                    if ($txnCheck->fetchColumn() > 0) {
                        // Balance locked — skip balance update silently
                    } else {
                        $updates .= ", balance = ?";
                        $updateParams[] = $newBalance;
                    }
                }

                $updateParams[] = $id;
                $pdo->prepare("UPDATE accounts SET $updates WHERE id = ?")->execute($updateParams);
            } else {
                // Non-superadmin cannot edit accounts
                throw new Exception('Only superadmin can edit accounts.');
            }

            $newSnap = $getAccountSnapshot($id);
            auditLog('account_updated', 'accounts', $id, $oldSnap, $newSnap);
            echo json_encode(['success' => true, 'message' => 'Account updated.']);
            break;

        case 'delete':
            if ($user['role'] !== ROLE_SUPERADMIN)
                throw new Exception('Only superadmin can delete accounts.');
            $id = intval($_POST['id'] ?? 0);

            // Capture old state before delete
            $oldSnap = $getAccountSnapshot($id);
            if (!$oldSnap)
                throw new Exception('Account not found.');

            if ($oldSnap['is_default'])
                throw new Exception('Cannot delete the default account.');
            // Check if account has transactions
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE account_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0)
                throw new Exception('Cannot delete account with existing transactions.');
            $pdo->prepare("DELETE FROM accounts WHERE id = ?")->execute([$id]);
            auditLog('account_deleted', 'accounts', $id, $oldSnap, null);
            echo json_encode(['success' => true, 'message' => 'Account deleted.']);
            break;

        case 'toggle_active':
            $id = intval($_POST['id'] ?? 0);
            $isActive = intval($_POST['is_active'] ?? 0);

            // Capture old state
            $oldSnap = $getAccountSnapshot($id);
            if (!$oldSnap)
                throw new Exception('Account not found.');
            if ($oldSnap['is_default'] && !$isActive)
                throw new Exception('Cannot deactivate the default account. It must always remain active.');
            // Verify ownership for non-superadmin
            if ($user['role'] !== ROLE_SUPERADMIN && (int) $oldSnap['branch_id'] !== (int) $branchId)
                throw new Exception('Access denied.');

            $pdo->prepare("UPDATE accounts SET is_active = ? WHERE id = ?")->execute([$isActive, $id]);
            $newSnap = $getAccountSnapshot($id);
            auditLog($isActive ? 'account_activated' : 'account_deactivated', 'accounts', $id, $oldSnap, $newSnap);
            echo json_encode(['success' => true, 'message' => $isActive ? 'Account activated.' : 'Account deactivated.']);
            break;

        default:
            throw new Exception('Invalid action.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
