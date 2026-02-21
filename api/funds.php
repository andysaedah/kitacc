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

    // Helper: fetch fund snapshot for audit
    $getFundSnapshot = function ($id) use ($pdo) {
        $s = $pdo->prepare("SELECT f.id, f.name, f.description, f.is_active,
                                   f.branch_id, b.name AS branch_name
                            FROM funds f
                            LEFT JOIN branches b ON f.branch_id = b.id
                            WHERE f.id = ?");
        $s->execute([$id]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    };

    // Helper: fetch transfer snapshot for audit
    $getTransferSnapshot = function ($id) use ($pdo) {
        $s = $pdo->prepare("SELECT ft.id, ft.amount, ft.description,
                                   ft.branch_id, b.name AS branch_name,
                                   ft.from_fund_id, ff.name AS from_fund_name,
                                   ft.to_fund_id, tf.name AS to_fund_name,
                                   ft.created_by, u.name AS created_by_name
                            FROM fund_transfers ft
                            LEFT JOIN branches b ON ft.branch_id = b.id
                            LEFT JOIN funds ff ON ft.from_fund_id = ff.id
                            LEFT JOIN funds tf ON ft.to_fund_id = tf.id
                            LEFT JOIN users u ON ft.created_by = u.id
                            WHERE ft.id = ?");
        $s->execute([$id]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    };

    switch ($action) {
        case 'create':
            $name = trim($_POST['name'] ?? '');
            if (!$name)
                throw new Exception('Name is required.');
            $targetBranch = ($user['role'] === ROLE_SUPERADMIN && !empty($_POST['branch_id'])) ? intval($_POST['branch_id']) : $branchId;
            $pdo->prepare("INSERT INTO funds (branch_id, name, description) VALUES (?, ?, ?)")
                ->execute([$targetBranch, $name, trim($_POST['description'] ?? '')]);
            $newId = $pdo->lastInsertId();
            $newSnap = $getFundSnapshot($newId);
            auditLog('fund_created', 'funds', $newId, null, $newSnap);
            echo json_encode(['success' => true, 'message' => 'Fund created.']);
            break;
        case 'update':
            $id = intval($_POST['id'] ?? 0);

            // Capture old state
            $oldSnap = $getFundSnapshot($id);
            if (!$oldSnap)
                throw new Exception('Fund not found.');

            // Verify ownership for non-superadmin
            if ($user['role'] !== ROLE_SUPERADMIN) {
                if ((int) $oldSnap['branch_id'] !== (int) $branchId)
                    throw new Exception('Fund not found.');
            }
            $targetBranch = ($user['role'] === ROLE_SUPERADMIN && !empty($_POST['branch_id'])) ? intval($_POST['branch_id']) : $branchId;
            $pdo->prepare("UPDATE funds SET branch_id = ?, name = ?, description = ? WHERE id = ?")
                ->execute([$targetBranch, trim($_POST['name']), trim($_POST['description'] ?? ''), $id]);

            $newSnap = $getFundSnapshot($id);
            auditLog('fund_updated', 'funds', $id, $oldSnap, $newSnap);
            echo json_encode(['success' => true, 'message' => 'Fund updated.']);
            break;
        case 'delete':
            $id = intval($_POST['id'] ?? 0);

            // Capture old state
            $oldSnap = $getFundSnapshot($id);
            if (!$oldSnap)
                throw new Exception('Fund not found.');

            // Verify ownership for non-superadmin
            if ($user['role'] !== ROLE_SUPERADMIN) {
                if ((int) $oldSnap['branch_id'] !== (int) $branchId)
                    throw new Exception('Fund not found.');
            }
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE fund_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0)
                throw new Exception('Cannot delete fund with existing transactions.');
            $pdo->prepare("DELETE FROM funds WHERE id = ?")->execute([$id]);
            auditLog('fund_deleted', 'funds', $id, $oldSnap, null);
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

            // Fund balance validation
            $fromBal = getFundBalance($fromFundId);
            if ($amount > $fromBal) {
                $fnStmt = $pdo->prepare("SELECT name FROM funds WHERE id = ?");
                $fnStmt->execute([$fromFundId]);
                $fundName = $fnStmt->fetchColumn() ?: 'Source fund';
                throw new Exception($fundName . ' has insufficient balance (RM ' . number_format($fromBal, 2) . ' available).');
            }

            $pdo->prepare("INSERT INTO fund_transfers (branch_id, from_fund_id, to_fund_id, amount, description, created_by) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$branchId, $fromFundId, $toFundId, $amount, $description, $user['id']]);

            $newId = $pdo->lastInsertId();
            $transferSnap = $getTransferSnapshot($newId);
            auditLog('fund_transfer', 'fund_transfers', $newId, null, $transferSnap);
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

            $transferSnap = $getTransferSnapshot($id);
            $pdo->prepare("DELETE FROM fund_transfers WHERE id = ?")->execute([$id]);
            auditLog('fund_transfer_deleted', 'fund_transfers', $id, $transferSnap, null);
            echo json_encode(['success' => true, 'message' => 'Transfer deleted.']);
            break;
        default:
            throw new Exception('Invalid action.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
