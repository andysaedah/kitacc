<?php
/**
 * KiTAcc - Claims API
 */
require_once __DIR__ . '/../includes/config.php';
requireLogin();
header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

try {
    $pdo = db();
    $user = getCurrentUser();
    $branchId = $user['branch_id'];

    // Helper: build a human-readable claim snapshot
    $getClaimSnapshot = function ($row) use ($pdo) {
        if (!$row) return null;
        $cat = null;
        if (!empty($row['category_id'])) {
            $c = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
            $c->execute([$row['category_id']]);
            $cat = $c->fetchColumn() ?: null;
        }
        $submitter = null;
        if (!empty($row['submitted_by'])) {
            $u = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $u->execute([$row['submitted_by']]);
            $submitter = $u->fetchColumn() ?: null;
        }
        return [
            'id' => $row['id'] ?? null,
            'title' => $row['title'] ?? '',
            'amount' => $row['amount'],
            'receipt_date' => $row['receipt_date'] ?? '',
            'category' => $cat,
            'description' => $row['description'] ?? '',
            'status' => $row['status'] ?? 'pending',
            'submitted_by' => $submitter,
            'receipt_path' => $row['receipt_path'] ?? null,
        ];
    };

    switch ($action) {

        case 'get':
            requireRole(ROLE_USER);
            $id = intval($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM claims WHERE id = ? AND submitted_by = ?");
            $stmt->execute([$id, $user['id']]);
            $claim = $stmt->fetch();
            if (!$claim)
                throw new Exception('Claim not found.');
            echo json_encode(['success' => true, 'data' => $claim]);
            break;

        case 'update':
            requireRole(ROLE_USER);
            validateCsrf();

            $id = intval($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM claims WHERE id = ? AND submitted_by = ? AND status = 'pending'");
            $stmt->execute([$id, $user['id']]);
            $claim = $stmt->fetch();
            if (!$claim)
                throw new Exception('Claim not found or cannot be edited.');

            $amount = floatval($_POST['amount'] ?? 0);
            $receiptDate = trim($_POST['receipt_date'] ?? '');
            $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
            $description = trim($_POST['description'] ?? '');

            if ($amount <= 0)
                throw new Exception('Amount is required.');
            if (!$receiptDate)
                throw new Exception('Receipt date is required.');

            $title = $description ? mb_substr($description, 0, 100) : 'Claim ' . date('d/m/Y', strtotime($receiptDate));

            // Handle receipt replacement
            $receiptPath = $claim['receipt_path'];
            if (!empty($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
                $receiptPath = handleUpload($_FILES['receipt'], 'uploads/claims');
            }

            $oldSnap = $getClaimSnapshot($claim);

            $pdo->prepare("UPDATE claims SET title = ?, amount = ?, receipt_date = ?, category_id = ?, description = ?, receipt_path = ? WHERE id = ?")
                ->execute([$title, $amount, $receiptDate, $categoryId, $description, $receiptPath, $id]);

            $updatedStmt = $pdo->prepare("SELECT * FROM claims WHERE id = ?");
            $updatedStmt->execute([$id]);
            $newSnap = $getClaimSnapshot($updatedStmt->fetch());
            auditLog('claim_updated', 'claims', $id, $oldSnap, $newSnap);
            echo json_encode(['success' => true, 'message' => 'Claim updated.']);
            break;

        case 'submit':
            requireRole(ROLE_USER);
            validateCsrf();

            $amount = floatval($_POST['amount'] ?? 0);
            $receiptDate = trim($_POST['receipt_date'] ?? '');
            $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
            $description = trim($_POST['description'] ?? '');

            if ($amount <= 0)
                throw new Exception('Amount is required.');
            if (!$receiptDate)
                throw new Exception('Receipt date is required.');

            $receiptPath = null;
            if (!empty($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
                $receiptPath = handleUpload($_FILES['receipt'], 'uploads/claims');
            }

            // Auto-generate title from description or a default
            $title = $description ? mb_substr($description, 0, 100) : 'Claim ' . date('d/m/Y', strtotime($receiptDate));

            $stmt = $pdo->prepare("INSERT INTO claims (branch_id, submitted_by, title, amount, category_id, description, receipt_path, receipt_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$branchId, $user['id'], $title, $amount, $categoryId, $description, $receiptPath, $receiptDate]);

            $newId = $pdo->lastInsertId();
            $newClaimStmt = $pdo->prepare("SELECT * FROM claims WHERE id = ?");
            $newClaimStmt->execute([$newId]);
            $newSnap = $getClaimSnapshot($newClaimStmt->fetch());
            auditLog('claim_submitted', 'claims', $newId, null, $newSnap);
            echo json_encode(['success' => true, 'message' => 'Claim submitted for review.']);
            break;

        case 'approve':
            requireRole(ROLE_BRANCH_FINANCE);
            validateCsrf();

            $id = intval($_POST['id'] ?? 0);
            $accountId = intval($_POST['account_id'] ?? 0);
            $fundId = !empty($_POST['fund_id']) ? intval($_POST['fund_id']) : null;
            if (!$accountId)
                throw new Exception('Select an account to pay from.');

            // Verify account belongs to user's branch
            if ($user['role'] !== ROLE_SUPERADMIN) {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE id = ? AND branch_id = ?");
                $chk->execute([$accountId, $branchId]);
                if ($chk->fetchColumn() == 0)
                    throw new Exception('Invalid account selected.');
            }

            $stmt = $pdo->prepare("SELECT * FROM claims WHERE id = ? AND status = 'pending'");
            $stmt->execute([$id]);
            $claim = $stmt->fetch();
            if (!$claim)
                throw new Exception('Claim not found or already processed.');
            // Verify claim belongs to user's branch (unless superadmin)
            if ($user['role'] !== ROLE_SUPERADMIN && $claim['branch_id'] != $branchId)
                throw new Exception('Claim not found or already processed.');

            // Update claim
            $pdo->prepare("UPDATE claims SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?")
                ->execute([$user['id'], $id]);

            // Auto-create expense transaction (date = today = approved date, ignoring receipt_date)
            // Use the 'Claim' expense category for all approved claims
            $catStmt = $pdo->prepare("SELECT id FROM categories WHERE name = 'Claim' AND type = 'expense' LIMIT 1");
            $catStmt->execute();
            $claimCategoryId = $catStmt->fetchColumn() ?: $claim['category_id'];

            $stmt = $pdo->prepare("INSERT INTO transactions (branch_id, type, date, amount, account_id, category_id, fund_id, description, reference_number, receipt_path, created_by) VALUES (?, 'expense', CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $claim['branch_id'],
                $claim['amount'],
                $accountId,
                $claimCategoryId,
                $fundId,
                'Claim: ' . ($claim['description'] ?: $claim['title']),
                'CLAIM-' . $id,
                $claim['receipt_path'],
                $user['id']
            ]);

            // Update account balance
            $pdo->prepare("UPDATE accounts SET balance = balance - ? WHERE id = ?")->execute([$claim['amount'], $accountId]);

            $oldSnap = $getClaimSnapshot($claim);
            $approvedStmt = $pdo->prepare("SELECT * FROM claims WHERE id = ?");
            $approvedStmt->execute([$id]);
            $newSnap = $getClaimSnapshot($approvedStmt->fetch());
            $newSnap['approved_by'] = $user['name'];
            $newSnap['paid_from_account'] = (function() use ($pdo, $accountId) {
                $s = $pdo->prepare("SELECT name FROM accounts WHERE id = ?");
                $s->execute([$accountId]);
                return $s->fetchColumn() ?: ('ID:' . $accountId);
            })();
            auditLog('claim_approved', 'claims', $id, $oldSnap, $newSnap);
            echo json_encode(['success' => true, 'message' => 'Claim approved. Expense recorded.']);
            break;

        case 'reject':
            requireRole(ROLE_BRANCH_FINANCE);
            validateCsrf();

            $id = intval($_POST['id'] ?? 0);
            $reason = trim($_POST['rejection_reason'] ?? '');
            if (!$reason)
                throw new Exception('Rejection reason is required.');

            // Fetch claim and verify branch ownership
            $stmt = $pdo->prepare("SELECT * FROM claims WHERE id = ? AND status = 'pending'");
            $stmt->execute([$id]);
            $claim = $stmt->fetch();
            if (!$claim)
                throw new Exception('Claim not found or already processed.');
            if ($user['role'] !== ROLE_SUPERADMIN && $claim['branch_id'] != $branchId)
                throw new Exception('Claim not found or already processed.');

            $oldSnap = $getClaimSnapshot($claim);

            $pdo->prepare("UPDATE claims SET status = 'rejected', rejection_reason = ?, approved_by = ?, approved_at = NOW() WHERE id = ?")
                ->execute([$reason, $user['id'], $id]);

            $rejectedStmt = $pdo->prepare("SELECT * FROM claims WHERE id = ?");
            $rejectedStmt->execute([$id]);
            $newSnap = $getClaimSnapshot($rejectedStmt->fetch());
            $newSnap['rejected_by'] = $user['name'];
            $newSnap['rejection_reason'] = $reason;
            auditLog('claim_rejected', 'claims', $id, $oldSnap, $newSnap);
            echo json_encode(['success' => true, 'message' => 'Claim rejected.']);
            break;

        case 'delete':
            requireRole(ROLE_USER);
            validateCsrf();

            $id = intval($_POST['id'] ?? 0);

            $stmt = $pdo->prepare("SELECT * FROM claims WHERE id = ? AND submitted_by = ? AND status = 'pending'");
            $stmt->execute([$id, $user['id']]);
            $claim = $stmt->fetch();
            if (!$claim)
                throw new Exception('Claim not found or cannot be deleted.');

            $oldSnap = $getClaimSnapshot($claim);
            $pdo->prepare("DELETE FROM claims WHERE id = ?")->execute([$id]);

            auditLog('claim_deleted', 'claims', $id, $oldSnap, null);
            echo json_encode(['success' => true, 'message' => 'Claim deleted.']);
            break;

        default:
            throw new Exception('Invalid action.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
