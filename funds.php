<?php
/**
 * KiTAcc - Funds Management (Fund Accounting Mode only)
 * Fund balance cards, inter-fund transfers, transfer history
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();
requireRole(ROLE_BRANCH_FINANCE);

if (isSimpleMode()) {
    header('Location: dashboard.php');
    exit;
}

$user = getCurrentUser();
$branchId = getActiveBranchId() ?? $user['branch_id'];
$page_title = 'Funds - KiTAcc';

try {
    $pdo = db();

    // Auto-create General Fund if it doesn't exist for this branch
    $gfCheck = $pdo->prepare("SELECT id FROM funds WHERE name = 'General Fund' AND branch_id = ? LIMIT 1");
    $gfCheck->execute([$branchId ?? 1]);
    if (!$gfCheck->fetchColumn()) {
        $pdo->prepare("INSERT INTO funds (branch_id, name, description) VALUES (?, 'General Fund', 'Default unallocated fund')")
            ->execute([$branchId ?? 1]);
    }

    // Fetch funds with calculated balances
    // General Fund also includes transactions with fund_id IS NULL (unallocated)
    // and account starting balances for the branch
    $sql = "SELECT f.*,
                COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.fund_id = f.id AND t.type = 'income'), 0)
                - COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.fund_id = f.id AND t.type = 'expense'), 0)
                + COALESCE((SELECT SUM(ft.amount) FROM fund_transfers ft WHERE ft.to_fund_id = f.id), 0)
                - COALESCE((SELECT SUM(ft.amount) FROM fund_transfers ft WHERE ft.from_fund_id = f.id), 0)
                + CASE WHEN f.name = 'General Fund' THEN
                    COALESCE((SELECT SUM(t2.amount) FROM transactions t2 WHERE t2.fund_id IS NULL AND t2.type = 'income' AND t2.branch_id = f.branch_id), 0)
                    - COALESCE((SELECT SUM(t2.amount) FROM transactions t2 WHERE t2.fund_id IS NULL AND t2.type = 'expense' AND t2.branch_id = f.branch_id), 0)
                    + COALESCE((SELECT SUM(a.balance) FROM accounts a WHERE a.is_active = 1 AND a.branch_id = f.branch_id), 0)
                  ELSE 0 END
                AS balance
            FROM funds f WHERE f.is_active = 1";
    $params = [];
    if ($branchId !== null) {
        $sql .= " AND f.branch_id = ?";
        $params[] = $branchId;
    }
    $sql .= " ORDER BY CASE WHEN f.name = 'General Fund' THEN 0 ELSE 1 END, f.name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $funds = $stmt->fetchAll();

    // Total account balances (for comparison)
    $accSql = "SELECT COALESCE(SUM(balance), 0) FROM accounts WHERE is_active = 1";
    $accParams = [];
    if ($branchId !== null) {
        $accSql .= " AND branch_id = ?";
        $accParams[] = $branchId;
    }
    $totalAccountBalance = $pdo->prepare($accSql);
    $totalAccountBalance->execute($accParams);
    $totalAccounts = floatval($totalAccountBalance->fetchColumn());

    // Total fund balances
    $totalFunds = array_sum(array_column($funds, 'balance'));

    // Transfer history
    $tfSql = "SELECT ft.*, f1.name AS from_fund_name, f2.name AS to_fund_name, u.name AS created_by_name
              FROM fund_transfers ft
              LEFT JOIN funds f1 ON ft.from_fund_id = f1.id
              LEFT JOIN funds f2 ON ft.to_fund_id = f2.id
              LEFT JOIN users u ON ft.created_by = u.id
              WHERE 1=1";
    $tfParams = [];
    if ($branchId !== null) {
        $tfSql .= " AND ft.branch_id = ?";
        $tfParams[] = $branchId;
    }
    $tfSql .= " ORDER BY ft.created_at DESC LIMIT 50";
    $stmt = $pdo->prepare($tfSql);
    $stmt->execute($tfParams);
    $transfers = $stmt->fetchAll();

    // Branches for dropdown (superadmin)
    $branches = [];
    if ($user['role'] === ROLE_SUPERADMIN) {
        $stmt = $pdo->query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name");
        $branches = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $funds = [];
    $transfers = [];
    $branches = [];
    $totalAccounts = 0;
    $totalFunds = 0;
}

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-between align-center mb-6">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--gray-800);">Funds</h1>
        <p class="text-muted">Manage fund allocations and transfers</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline" onclick="KiTAcc.openModal('addFundModal')"><i class="fas fa-plus"></i> Add
            Fund</button>
        <button class="btn btn-primary" onclick="KiTAcc.openModal('transferModal')"><i class="fas fa-exchange-alt"></i>
            Transfer</button>
    </div>
</div>

<!-- Current Fund Balances -->
<div class="card mb-6">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-wallet" style="color: var(--primary); margin-right: 0.5rem;"></i>Current
            Fund Balances</h3>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <?php foreach ($funds as $fund): ?>
                <?php $isGeneral = ($fund['name'] === 'General Fund'); ?>
                <div
                    style="border: 1px solid var(--gray-200); border-radius: 8px; padding: 1rem; position: relative; background: var(--white);">
                    <div class="d-flex justify-between align-center">
                        <span
                            style="font-size: 0.625rem; font-weight: 700; text-transform: uppercase; color: var(--gray-500); letter-spacing: 0.05em;"><?php echo strtoupper(substr($fund['name'], 0, 5)); ?></span>
                        <div class="d-flex align-center gap-2">
                            <?php if (!$isGeneral): ?>
                                <button class="btn btn-icon btn-ghost" style="width: 20px; height: 20px; font-size: 0.625rem;"
                                    title="Edit"
                                    onclick="editFund(<?php echo $fund['id']; ?>, '<?php echo htmlspecialchars(addslashes($fund['name'])); ?>', '<?php echo htmlspecialchars(addslashes($fund['description'] ?? '')); ?>', '<?php echo $fund['branch_id']; ?>')"><i
                                        class="fas fa-edit"></i></button>
                            <?php endif; ?>
                            <i class="fas fa-lock"
                                style="font-size: 0.75rem; color: <?php echo $fund['balance'] > 0 ? 'var(--success)' : 'var(--gray-400)'; ?>;"></i>
                        </div>
                    </div>
                    <div style="font-size: 0.875rem; font-weight: 600; color: var(--gray-700); margin: 0.25rem 0;">
                        <?php echo htmlspecialchars($fund['name']); ?>
                    </div>
                    <div
                        style="font-size: 1.125rem; font-weight: 700; color: <?php echo $fund['balance'] > 0 ? 'var(--success)' : 'var(--danger)'; ?>;">
                        <?php echo formatCurrency($fund['balance']); ?>
                    </div>
                    <?php if ($fund['description']): ?>
                        <div style="font-size: 0.625rem; color: var(--gray-400); margin-top: 0.25rem;">
                            <?php echo htmlspecialchars($fund['description']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Summary row -->
        <div
            style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <span class="text-muted" style="font-size: 0.75rem;">Total Account Balances</span>
                <div class="font-semibold" style="font-size: 1rem;"><?php echo formatCurrency($totalAccounts); ?></div>
            </div>
            <div>
                <span class="text-muted" style="font-size: 0.75rem;">Total Allocated to Funds</span>
                <div class="font-semibold" style="font-size: 1rem;"><?php echo formatCurrency($totalFunds); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Transfer History -->
<div class="card table-responsive-cards">
    <div class="card-header">
        <h3 class="card-title">Transfer History</h3>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>From Fund</th>
                    <th>To Fund</th>
                    <th class="text-right">Amount</th>
                    <th>Description</th>
                    <th>By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transfers)): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state"><i class="fas fa-exchange-alt"></i>
                                <h3>No Transfers</h3>
                                <p>No fund transfers yet. Create your first transfer above.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transfers as $tf): ?>
                        <tr>
                            <td><?php echo formatDate($tf['created_at']); ?></td>
                            <td><span class="badge badge-danger"><?php echo htmlspecialchars($tf['from_fund_name']); ?></span>
                            </td>
                            <td><span class="badge badge-success"><?php echo htmlspecialchars($tf['to_fund_name']); ?></span>
                            </td>
                            <td class="text-right font-semibold"><?php echo formatCurrency($tf['amount']); ?></td>
                            <td class="text-muted"><?php echo htmlspecialchars($tf['description'] ?? '—'); ?></td>
                            <td class="text-muted"><?php echo htmlspecialchars($tf['created_by_name'] ?? '—'); ?></td>
                            <td>
                                <button class="btn btn-icon btn-ghost text-danger btn-sm" title="Delete"
                                    onclick="deleteTransfer(<?php echo $tf['id']; ?>)"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Cards -->
    <div class="mobile-card">
        <?php if (empty($transfers)): ?>
            <div class="empty-state"><i class="fas fa-exchange-alt"></i>
                <p>No fund transfers yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($transfers as $tf): ?>
                <div class="mobile-card-item">
                    <div class="mobile-card-header">
                        <span class="font-semibold"><?php echo formatCurrency($tf['amount']); ?></span>
                        <span class="text-muted" style="font-size: 0.75rem;"><?php echo formatDate($tf['created_at']); ?></span>
                    </div>
                    <div class="mobile-card-row"><span class="mobile-card-label">From</span><span
                            class="mobile-card-value"><span
                                class="badge badge-danger"><?php echo htmlspecialchars($tf['from_fund_name']); ?></span></span>
                    </div>
                    <div class="mobile-card-row"><span class="mobile-card-label">To</span><span class="mobile-card-value"><span
                                class="badge badge-success"><?php echo htmlspecialchars($tf['to_fund_name']); ?></span></span>
                    </div>
                    <?php if ($tf['description']): ?>
                        <div class="mobile-card-row"><span class="mobile-card-label">Note</span><span
                                class="mobile-card-value text-muted"><?php echo htmlspecialchars($tf['description']); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="mobile-card-actions">
                        <button class="btn btn-sm btn-ghost text-danger" onclick="deleteTransfer(<?php echo $tf['id']; ?>)"><i
                                class="fas fa-trash"></i>
                            Delete</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Transfer Modal -->
<div class="modal-overlay" id="transferModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Fund Transfer</h3>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="transferForm" data-validate>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label required">From Fund</label>
                        <select name="from_fund_id" class="form-control" required>
                            <option value="">Select Source</option>
                            <?php foreach ($funds as $fund): ?>
                                <option value="<?php echo $fund['id']; ?>">
                                    <?php echo htmlspecialchars($fund['name']); ?>
                                    (<?php echo formatCurrency($fund['balance']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">To Fund</label>
                        <select name="to_fund_id" class="form-control" required>
                            <option value="">Select Destination</option>
                            <?php foreach ($funds as $fund): ?>
                                <option value="<?php echo $fund['id']; ?>">
                                    <?php echo htmlspecialchars($fund['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label required">Amount (RM)</label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="0.01" placeholder="0.00"
                        required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2"
                        placeholder="e.g. Allocate funds for building project"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="KiTAcc.closeModal('transferModal')">Cancel</button>
            <button class="btn btn-primary" id="transferSubmitBtn" onclick="submitTransfer()"><i
                    class="fas fa-exchange-alt"></i> Transfer</button>
        </div>
    </div>
</div>

<!-- Add/Edit Fund Modal -->
<div class="modal-overlay" id="addFundModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="fundModalTitle">Add Fund</h3><button class="modal-close"><i
                    class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="fundForm"><input type="hidden" id="fundId" name="id" value="">
                <?php if ($user['role'] === ROLE_SUPERADMIN && !empty($branches)): ?>
                    <div class="form-group">
                        <label class="form-label required">Branch</label>
                        <select name="branch_id" class="form-control" required>
                            <option value="">Select Branch</option>
                            <?php foreach ($branches as $br): ?>
                                <option value="<?php echo $br['id']; ?>"><?php echo htmlspecialchars($br['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="form-group"><label class="form-label required">Fund Name</label><input type="text"
                        name="name" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Description</label><textarea name="description"
                        class="form-control" rows="2"></textarea></div>
            </form>
        </div>
        <div class="modal-footer"><button class="btn btn-outline"
                onclick="KiTAcc.closeModal('addFundModal')">Cancel</button><button class="btn btn-primary"
                onclick="submitFund()"><i class="fas fa-save"></i> Save</button></div>
    </div>
</div>

<?php
$page_scripts = <<<'SCRIPT'
<script>
    function submitFund() {
        const data = KiTAcc.serializeForm(document.getElementById('fundForm'));
        data.action = data.id ? 'update' : 'create';
        KiTAcc.post('api/funds.php', data, function(res) {
            if (res.success) { KiTAcc.toast('Saved!', 'success'); setTimeout(() => location.reload(), 600); }
            else KiTAcc.toast(res.message || 'Error.', 'error');
        });
    }
    function editFund(id, name, desc, branchId) {
        document.getElementById('fundId').value = id;
        document.getElementById('fundModalTitle').textContent = 'Edit Fund';
        const form = document.getElementById('fundForm');
        form.querySelector('[name="name"]').value = name;
        form.querySelector('[name="description"]').value = desc;
        const branchSelect = form.querySelector('[name="branch_id"]');
        if (branchSelect) branchSelect.value = branchId;
        KiTAcc.openModal('addFundModal');
    }
    function deleteFund(id) {
        if (!KiTAcc.confirm('Delete fund?')) return;
        KiTAcc.post('api/funds.php', { action: 'delete', id: id }, function(res) {
            if (res.success) { KiTAcc.toast('Deleted.', 'success'); setTimeout(() => location.reload(), 600); }
            else KiTAcc.toast(res.message || 'Error.', 'error');
        });
    }

    function submitTransfer() {
        const form = document.getElementById('transferForm');
        if (!KiTAcc.validateForm(form)) return;
        const data = KiTAcc.serializeForm(form);
        if (data.from_fund_id === data.to_fund_id) {
            KiTAcc.toast('Cannot transfer to the same fund.', 'warning');
            return;
        }
        data.action = 'transfer';
        const btn = document.getElementById('transferSubmitBtn');
        btn.disabled = true;
        KiTAcc.post('api/funds.php', data, function(res) {
            if (res.success) {
                KiTAcc.toast('Transfer completed!', 'success');
                KiTAcc.closeModal('transferModal');
                setTimeout(() => location.reload(), 600);
            } else {
                KiTAcc.toast(res.message || 'Transfer failed.', 'error');
            }
            btn.disabled = false;
        }, function() {
            KiTAcc.toast('Connection error.', 'error');
            btn.disabled = false;
        });
    }

    function deleteTransfer(id) {
        if (!KiTAcc.confirm('Delete this transfer? Fund balances will be recalculated.')) return;
        KiTAcc.post('api/funds.php', { action: 'delete_transfer', id: id }, function(res) {
            if (res.success) { KiTAcc.toast('Transfer deleted.', 'success'); setTimeout(() => location.reload(), 600); }
            else KiTAcc.toast(res.message || 'Error.', 'error');
        });
    }
</script>
SCRIPT;
include __DIR__ . '/includes/footer.php';
?>