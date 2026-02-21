<?php
/**
 * KiTAcc - Transaction History
 * Combined view of all income/expense transactions
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();
requireRole(ROLE_USER);

$user = getCurrentUser();
$branchId = getActiveBranchId() ?? $user['branch_id'];
$page_title = 'Transactions - KiTAcc';

// Filters
$filterType = $_GET['type'] ?? '';
$filterMonth = $_GET['month'] ?? date('Y-m');
$filterCategory = $_GET['category_id'] ?? '';

try {
    $pdo = db();

    $sql = "SELECT t.*, c.name AS category_name, a.name AS account_name, f.name AS fund_name, u.name AS created_by_name
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
            LEFT JOIN accounts a ON t.account_id = a.id
            LEFT JOIN funds f ON t.fund_id = f.id
            LEFT JOIN users u ON t.created_by = u.id
            WHERE 1=1";
    $params = [];

    if ($branchId !== null) {
        $sql .= " AND t.branch_id = ?";
        $params[] = $branchId;
    }
    if ($filterType) {
        $sql .= " AND t.type = ?";
        $params[] = $filterType;
    }
    if ($filterMonth) {
        $monthStart = $filterMonth . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        $sql .= " AND t.date BETWEEN ? AND ?";
        $params[] = $monthStart;
        $params[] = $monthEnd;
    }
    if ($filterCategory) {
        $sql .= " AND t.category_id = ?";
        $params[] = $filterCategory;
    }

    // Count total records
    $countSql = "SELECT COUNT(*) FROM transactions t WHERE 1=1";
    $countParams = [];
    if ($branchId !== null) {
        $countSql .= " AND t.branch_id = ?";
        $countParams[] = $branchId;
    }
    if ($filterType) {
        $countSql .= " AND t.type = ?";
        $countParams[] = $filterType;
    }
    if ($filterMonth) {
        $monthStart = $filterMonth . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        $countSql .= " AND t.date BETWEEN ? AND ?";
        $countParams[] = $monthStart;
        $countParams[] = $monthEnd;
    }
    if ($filterCategory) {
        $countSql .= " AND t.category_id = ?";
        $countParams[] = $filterCategory;
    }
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $totalRecords = (int) $countStmt->fetchColumn();

    $currentPage = max(1, intval($_GET['page'] ?? 1));
    $pager = paginate($totalRecords, $currentPage, 25);

    $sql .= " ORDER BY t.date DESC, t.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $pager['per_page'];
    $params[] = $pager['offset'];
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();

    // All categories for filter
    $stmt = $pdo->prepare("SELECT id, name, type FROM categories WHERE is_active = 1 ORDER BY type, name");
    $stmt->execute();
    $allCategories = $stmt->fetchAll();

} catch (Exception $e) {
    $transactions = [];
    $allCategories = [];
}

include __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-between align-center mb-6">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--gray-800);">Transactions</h1>
        <p class="text-muted">View all financial transactions</p>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="d-flex gap-4 flex-wrap align-center">
            <div class="form-group m-0" style="min-width: 150px;">
                <label class="form-label">Month</label>
                <input type="month" name="month" class="form-control"
                    value="<?php echo htmlspecialchars($filterMonth); ?>">
            </div>
            <div class="form-group m-0" style="min-width: 150px;">
                <label class="form-label">Type</label>
                <select name="type" class="form-control">
                    <option value="">All</option>
                    <option value="income" <?php echo $filterType === 'income' ? 'selected' : ''; ?>>Income</option>
                    <option value="expense" <?php echo $filterType === 'expense' ? 'selected' : ''; ?>>Expense</option>
                </select>
            </div>
            <div class="form-group m-0" style="min-width: 150px;">
                <label class="form-label">Category</label>
                <select name="category_id" class="form-control">
                    <option value="">All</option>
                    <?php foreach ($allCategories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $filterCategory == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?> (
                            <?php echo $cat['type']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group m-0" style="padding-top: 1.5rem;">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
                <a href="transactions.php" class="btn btn-outline btn-sm">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Transactions Table -->
<div class="card table-responsive-cards">
    <div class="card-header">
        <h3 class="card-title">Transaction List</h3>
        <span class="text-muted">
            <?php echo count($transactions); ?> records
        </span>
    </div>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Category</th>
                    <th>Account</th>
                    <?php if (isFundMode()): ?>
                        <th>Fund</th>
                    <?php endif; ?>
                    <th>Type</th>
                    <th class="text-right">Amount</th>
                    <th>By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="<?php echo isFundMode() ? 8 : 7; ?>">
                            <div class="empty-state"><i class="fas fa-exchange-alt"></i>
                                <h3>No Transactions Found</h3>
                                <p>Try adjusting your filters.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $txn): ?>
                        <tr>
                            <td>
                                <?php echo formatDate($txn['date']); ?>
                            </td>
                            <td class="truncate" style="max-width: 200px;">
                                <?php echo htmlspecialchars($txn['description'] ?? '—'); ?>
                            </td>
                            <td><span class="badge <?php echo $txn['type'] === 'income' ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo htmlspecialchars($txn['category_name'] ?? '—'); ?>
                                </span></td>
                            <td>
                                <?php echo htmlspecialchars($txn['account_name']); ?>
                            </td>
                            <?php if (isFundMode()): ?>
                                <td>
                                    <?php echo htmlspecialchars($txn['fund_name'] ?? 'General Fund'); ?>
                                </td>
                            <?php endif; ?>
                            <td>
                                <span class="badge <?php echo $txn['type'] === 'income' ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo ucfirst($txn['type']); ?>
                                </span>
                            </td>
                            <td
                                class="text-right font-semibold <?php echo $txn['type'] === 'income' ? 'text-success' : 'text-danger'; ?>">
                                <?php echo ($txn['type'] === 'income' ? '+' : '-') . formatCurrency($txn['amount']); ?>
                            </td>
                            <td class="text-muted" style="font-size: 0.75rem;">
                                <?php echo htmlspecialchars($txn['created_by_name'] ?? '—'); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Cards -->
    <div class="mobile-card">
        <?php if (empty($transactions)): ?>
            <div class="empty-state"><i class="fas fa-exchange-alt"></i>
                <p>No transactions found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($transactions as $txn): ?>
                <div class="mobile-card-item">
                    <div class="mobile-card-header">
                        <span class="font-semibold">
                            <?php echo htmlspecialchars($txn['description'] ?? 'Transaction'); ?>
                        </span>
                        <span class="font-semibold <?php echo $txn['type'] === 'income' ? 'text-success' : 'text-danger'; ?>">
                            <?php echo ($txn['type'] === 'income' ? '+' : '-') . formatCurrency($txn['amount']); ?>
                        </span>
                    </div>
                    <div class="mobile-card-row"><span class="mobile-card-label">Date</span><span class="mobile-card-value">
                            <?php echo formatDate($txn['date']); ?>
                        </span></div>
                    <div class="mobile-card-row"><span class="mobile-card-label">Type</span><span
                            class="badge <?php echo $txn['type'] === 'income' ? 'badge-success' : 'badge-danger'; ?>">
                            <?php echo ucfirst($txn['type']); ?>
                        </span></div>
                    <div class="mobile-card-row"><span class="mobile-card-label">Category</span><span class="mobile-card-value">
                            <?php echo htmlspecialchars($txn['category_name'] ?? '—'); ?>
                        </span></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php renderPagination($pager, 'transactions.php', $_GET); ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>