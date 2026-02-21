<?php
/**
 * KiTAcc - Transactions API
 * Handles create, update, delete, get for income & expense
 */
require_once __DIR__ . '/../includes/config.php';
requireLogin();

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

try {
    $pdo = db();
    $user = getCurrentUser();
    $branchId = getActiveBranchId() ?? $user['branch_id'];

    // Helper: build a human-readable transaction snapshot
    $getTxnSnapshot = function ($row) use ($pdo) {
        // Accept a row array (from SELECT *) and resolve names
        if (!$row) return null;
        $acct = $pdo->prepare("SELECT name FROM accounts WHERE id = ?");
        $acct->execute([$row['account_id']]);
        $cat = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $cat->execute([$row['category_id']]);
        $fund = null;
        if (!empty($row['fund_id'])) {
            $f = $pdo->prepare("SELECT name FROM funds WHERE id = ?");
            $f->execute([$row['fund_id']]);
            $fund = $f->fetchColumn() ?: null;
        }
        return [
            'id' => $row['id'] ?? null,
            'type' => $row['type'],
            'date' => $row['date'],
            'amount' => $row['amount'],
            'account' => $acct->fetchColumn() ?: ('ID:' . $row['account_id']),
            'category' => $cat->fetchColumn() ?: ('ID:' . $row['category_id']),
            'fund' => $fund,
            'description' => $row['description'] ?? '',
            'reference_number' => $row['reference_number'] ?? '',
        ];
    };

    switch ($action) {

        case 'create':
            requireRole(ROLE_BRANCH_FINANCE);
            validateCsrf();

            $type = $_POST['type'] ?? '';
            if (!in_array($type, ['income', 'expense']))
                throw new Exception('Invalid type.');

            $date = $_POST['date'] ?? '';
            $amount = floatval($_POST['amount'] ?? 0);
            $accountId = intval($_POST['account_id'] ?? 0);
            $categoryId = intval($_POST['category_id'] ?? 0);
            $fundId = !empty($_POST['fund_id']) ? intval($_POST['fund_id']) : null;
            $description = trim($_POST['description'] ?? '');
            $referenceNumber = trim($_POST['reference_number'] ?? '');

            if (!$date || $amount <= 0 || !$accountId || !$categoryId) {
                throw new Exception('Required fields missing.');
            }

            // Verify account belongs to user's branch
            if ($user['role'] !== ROLE_SUPERADMIN) {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE id = ? AND branch_id = ?");
                $chk->execute([$accountId, $branchId]);
                if ($chk->fetchColumn() == 0)
                    throw new Exception('Invalid account selected.');
            }

            // Fund balance validation for expenses (fund mode)
            if ($type === 'expense' && isFundMode()) {
                $checkFundId = $fundId;
                if (!$checkFundId) {
                    // No fund selected = General Fund
                    $checkFundId = getGeneralFundId($branchId);
                }
                if ($checkFundId) {
                    $fundBal = getFundBalance($checkFundId);
                    if ($amount > $fundBal) {
                        $fnStmt = $pdo->prepare("SELECT name FROM funds WHERE id = ?");
                        $fnStmt->execute([$checkFundId]);
                        $fundName = $fnStmt->fetchColumn() ?: 'Selected fund';
                        throw new Exception($fundName . ' has insufficient balance (RM ' . number_format($fundBal, 2) . ' available).');
                    }
                }
            }

            // Receipt upload
            $receiptPath = null;
            if (!empty($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
                $receiptPath = handleUpload($_FILES['receipt'], 'uploads/' . $type);
            }

            $stmt = $pdo->prepare("INSERT INTO transactions (branch_id, type, date, amount, account_id, category_id, fund_id, description, reference_number, receipt_path, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$branchId, $type, $date, $amount, $accountId, $categoryId, $fundId, $description, $referenceNumber, $receiptPath, $user['id']]);

            // Update account balance
            $balanceChange = $type === 'income' ? $amount : -$amount;
            $pdo->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?")->execute([$balanceChange, $accountId]);

            $newId = $pdo->lastInsertId();
            $newSnap = $getTxnSnapshot([
                'id' => $newId, 'type' => $type, 'date' => $date, 'amount' => $amount,
                'account_id' => $accountId, 'category_id' => $categoryId, 'fund_id' => $fundId,
                'description' => $description, 'reference_number' => $referenceNumber
            ]);
            auditLog($type . '_created', 'transactions', $newId, null, $newSnap);
            echo json_encode(['success' => true, 'message' => ucfirst($type) . ' recorded successfully.']);
            break;

        case 'update':
            requireRole(ROLE_BRANCH_FINANCE);
            validateCsrf();

            $id = intval($_POST['id'] ?? 0);
            if (!$id)
                throw new Exception('Invalid ID.');

            // Get old transaction for balance reversal (with branch check)
            if ($user['role'] === ROLE_SUPERADMIN) {
                $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
                $stmt->execute([$id]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND branch_id = ?");
                $stmt->execute([$id, $branchId]);
            }
            $old = $stmt->fetch();
            if (!$old)
                throw new Exception('Transaction not found.');

            // Reverse old balance
            $oldChange = $old['type'] === 'income' ? -$old['amount'] : $old['amount'];
            $pdo->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?")->execute([$oldChange, $old['account_id']]);

            $amount = floatval($_POST['amount'] ?? 0);
            $accountId = intval($_POST['account_id'] ?? 0);
            $categoryId = intval($_POST['category_id'] ?? 0);
            $fundId = !empty($_POST['fund_id']) ? intval($_POST['fund_id']) : null;

            // Verify account belongs to user's branch
            if ($user['role'] !== ROLE_SUPERADMIN) {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE id = ? AND branch_id = ?");
                $chk->execute([$accountId, $branchId]);
                if ($chk->fetchColumn() == 0)
                    throw new Exception('Invalid account selected.');
            }

            // Receipt
            $receiptPath = $old['receipt_path'];
            if (!empty($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
                $receiptPath = handleUpload($_FILES['receipt'], 'uploads/' . $old['type']);
            }

            $stmt = $pdo->prepare("UPDATE transactions SET date = ?, amount = ?, account_id = ?, category_id = ?, fund_id = ?, description = ?, reference_number = ?, receipt_path = ? WHERE id = ?");
            $stmt->execute([
                $_POST['date'],
                $amount,
                $accountId,
                $categoryId,
                $fundId,
                trim($_POST['description'] ?? ''),
                trim($_POST['reference_number'] ?? ''),
                $receiptPath,
                $id
            ]);

            // Apply new balance
            $newChange = $old['type'] === 'income' ? $amount : -$amount;
            $pdo->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?")->execute([$newChange, $accountId]);

            $oldSnap = $getTxnSnapshot($old);
            $newSnap = $getTxnSnapshot([
                'id' => $id, 'type' => $old['type'], 'date' => $_POST['date'], 'amount' => $amount,
                'account_id' => $accountId, 'category_id' => $categoryId, 'fund_id' => $fundId,
                'description' => trim($_POST['description'] ?? ''), 'reference_number' => trim($_POST['reference_number'] ?? '')
            ]);
            auditLog('transaction_updated', 'transactions', $id, $oldSnap, $newSnap);
            echo json_encode(['success' => true, 'message' => 'Transaction updated.']);
            break;

        case 'delete':
            requireRole(ROLE_BRANCH_FINANCE);
            validateCsrf();

            $id = intval($_POST['id'] ?? 0);
            if ($user['role'] === ROLE_SUPERADMIN) {
                $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
                $stmt->execute([$id]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND branch_id = ?");
                $stmt->execute([$id, $branchId]);
            }
            $txn = $stmt->fetch();
            if (!$txn)
                throw new Exception('Not found.');

            // Reverse balance
            $change = $txn['type'] === 'income' ? -$txn['amount'] : $txn['amount'];
            $pdo->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?")->execute([$change, $txn['account_id']]);

            $oldSnap = $getTxnSnapshot($txn);
            $pdo->prepare("DELETE FROM transactions WHERE id = ?")->execute([$id]);
            auditLog('transaction_deleted', 'transactions', $id, $oldSnap, null);
            echo json_encode(['success' => true, 'message' => 'Transaction deleted.']);
            break;

        case 'get':
            $id = intval($_GET['id'] ?? 0);
            if ($user['role'] === ROLE_SUPERADMIN) {
                $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
                $stmt->execute([$id]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND branch_id = ?");
                $stmt->execute([$id, $branchId]);
            }
            $data = $stmt->fetch();
            if (!$data)
                throw new Exception('Not found.');
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        default:
            throw new Exception('Invalid action.');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
