<?php
/**
 * KiTAcc - Funds API (branch-scoped)
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
            if (!$name)
                throw new Exception('Name is required.');
            $targetBranch = ($user['role'] === ROLE_SUPERADMIN && !empty($_POST['branch_id'])) ? intval($_POST['branch_id']) : $branchId;
            $pdo->prepare("INSERT INTO funds (branch_id, name, description) VALUES (?, ?, ?)")
                ->execute([$targetBranch, $name, trim($_POST['description'] ?? '')]);
            echo json_encode(['success' => true, 'message' => 'Fund created.']);
            break;
        case 'update':
            $id = intval($_POST['id'] ?? 0);
            // Verify ownership for non-superadmin
            if ($user['role'] !== ROLE_SUPERADMIN) {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM funds WHERE id = ? AND branch_id = ?");
                $chk->execute([$id, $branchId]);
                if ($chk->fetchColumn() == 0)
                    throw new Exception('Fund not found.');
            }
            $targetBranch = ($user['role'] === ROLE_SUPERADMIN && !empty($_POST['branch_id'])) ? intval($_POST['branch_id']) : $branchId;
            $pdo->prepare("UPDATE funds SET branch_id = ?, name = ?, description = ? WHERE id = ?")
                ->execute([$targetBranch, trim($_POST['name']), trim($_POST['description'] ?? ''), $id]);
            echo json_encode(['success' => true, 'message' => 'Fund updated.']);
            break;
        case 'delete':
            $id = intval($_POST['id'] ?? 0);
            // Verify ownership for non-superadmin
            if ($user['role'] !== ROLE_SUPERADMIN) {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM funds WHERE id = ? AND branch_id = ?");
                $chk->execute([$id, $branchId]);
                if ($chk->fetchColumn() == 0)
                    throw new Exception('Fund not found.');
            }
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE fund_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0)
                throw new Exception('Cannot delete fund with existing transactions.');
            $pdo->prepare("DELETE FROM funds WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Fund deleted.']);
            break;
        case 'transfer':
            $fromFundId = intval($_POST['from_fund_id'] ?? 0);
            $toFundId = intval($_POST['to_fund_id'] ?? 0);
            $amount = floatval($_POST['amount'] ?? 0);
            $description = trim($_POST['description'] ?? '');

            if (!$fromFundId || !$toFundId)
                throw new Exception('Please select both funds.');
            if ($fromFundId === $toFundId)
                throw new Exception('Cannot transfer to the same fund.');
            if ($amount <= 0)
                throw new Exception('Amount must be greater than zero.');

            // Verify both funds exist and belong to user's branch
            if ($user['role'] !== ROLE_SUPERADMIN) {
                $stmt = $pdo->prepare("SELECT id FROM funds WHERE id IN (?, ?) AND is_active = 1 AND branch_id = ?");
                $stmt->execute([$fromFundId, $toFundId, $branchId]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM funds WHERE id IN (?, ?) AND is_active = 1");
                $stmt->execute([$fromFundId, $toFundId]);
            }
            if ($stmt->rowCount() < 2)
                throw new Exception('One or both funds not found.');

            $pdo->prepare("INSERT INTO fund_transfers (branch_id, from_fund_id, to_fund_id, amount, description, created_by) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$branchId, $fromFundId, $toFundId, $amount, $description, $user['id']]);

            auditLog('fund_transfer', 'fund_transfers', $pdo->lastInsertId(), "From fund $fromFundId to fund $toFundId: $amount");
            echo json_encode(['success' => true, 'message' => 'Transfer completed.']);
            break;
        case 'delete_transfer':
            $id = intval($_POST['id'] ?? 0);
            if ($user['role'] === ROLE_SUPERADMIN) {
                $stmt = $pdo->prepare("SELECT * FROM fund_transfers WHERE id = ?");
                $stmt->execute([$id]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM fund_transfers WHERE id = ? AND branch_id = ?");
                $stmt->execute([$id, $branchId]);
            }
            $transfer = $stmt->fetch();
            if (!$transfer)
                throw new Exception('Transfer not found.');

            $pdo->prepare("DELETE FROM fund_transfers WHERE id = ?")->execute([$id]);
            auditLog('fund_transfer_deleted', 'fund_transfers', $id, "Amount: {$transfer['amount']}");
            echo json_encode(['success' => true, 'message' => 'Transfer deleted.']);
            break;
        default:
            throw new Exception('Invalid action.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
