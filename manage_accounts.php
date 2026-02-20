<?php
/**
 * KiTAcc - Manage Accounts (Superadmin only)
 * Create, edit, delete accounts across all branches. Set starting balance (one-time).
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();
requireRole(ROLE_SUPERADMIN);

$user = getCurrentUser();
$page_title = 'Manage Accounts - KiTAcc';
$currencySymbol = getSetting('currency_symbol', 'RM');

try {
    $pdo = db();

    // Fetch all accounts grouped by branch
    $sql = "SELECT a.*, b.name AS branch_name, 
                   at.name AS type_name, at.icon AS type_icon, at.color AS type_color,
                   (SELECT COUNT(*) FROM transactions t WHERE t.account_id = a.id) AS txn_count
            FROM accounts a 
            LEFT JOIN branches b ON a.branch_id = b.id 
            LEFT JOIN account_types at ON a.account_type_id = at.id 
            ORDER BY b.name, a.is_default DESC, a.name";
    $accountList = $pdo->query($sql)->fetchAll();

    // Group accounts by branch
    $accountsByBranch = [];
    foreach ($accountList as $acc) {
        $branchName = $acc['branch_name'] ?? 'Unassigned';
        $accountsByBranch[$branchName][] = $acc;
    }

    // Branches for dropdown
    $branches = $pdo->query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name")->fetchAll();

    // Account types for dropdown
    $accountTypes = [];
    try {
        $accountTypes = $pdo->query("SELECT id, name, icon, color FROM account_types WHERE is_active = 1 ORDER BY name")->fetchAll();
    } catch (Exception $e) {}
} catch (Exception $e) {
    $accountsByBranch = [];
    $branches = [];
    $accountTypes = [];
}

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-between align-center mb-6">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--gray-800);">Manage Accounts</h1>
        <p class="text-muted">Create and manage bank accounts for all branches</p>
    </div>
    <button class="btn btn-primary" onclick="openAddAccount()"><i class="fas fa-plus"></i> Add Account</button>
</div>

<?php if (empty($accountsByBranch)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state"><i class="fas fa-university"></i>
                <h3>No Accounts</h3>
                <p>Create a bank account and assign it to a branch.</p>
            </div>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($accountsByBranch as $branchName => $accounts): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-code-branch" style="color: var(--primary); margin-right: 0.5rem;"></i>
                    <?php echo htmlspecialchars($branchName); ?>
                    <span class="badge badge-secondary" style="margin-left: 0.5rem; font-size: 0.7rem;"><?php echo count($accounts); ?> account<?php echo count($accounts) !== 1 ? 's' : ''; ?></span>
                </h3>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Account</th>
                            <th>Type</th>
                            <th>Account Number</th>
                            <th class="text-right">Balance (<?php echo $currencySymbol; ?>)</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Transactions</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $acc): ?>
                            <tr style="<?php echo !$acc['is_active'] ? 'opacity: 0.5;' : ''; ?>">
                                <td>
                                    <div class="d-flex align-center gap-2">
                                        <span class="font-semibold"><?php echo htmlspecialchars($acc['name']); ?></span>
                                        <?php if ($acc['is_default']): ?>
                                            <span class="badge badge-primary" style="font-size: 0.6rem;">DEFAULT</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="d-flex align-center gap-2">
                                        <i class="fas <?php echo htmlspecialchars($acc['type_icon'] ?? 'fa-university'); ?>" style="color: var(--primary);"></i>
                                        <?php echo htmlspecialchars($acc['type_name'] ?? '—'); ?>
                                    </span>
                                </td>
                                <td class="text-muted"><?php echo htmlspecialchars($acc['account_number'] ?: '—'); ?></td>
                                <td class="text-right font-semibold" style="color: var(--primary);">
                                    <?php echo formatCurrency($acc['balance']); ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($acc['is_active']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($acc['txn_count'] > 0): ?>
                                        <span class="badge badge-secondary"><?php echo $acc['txn_count']; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <button class="btn btn-sm btn-ghost" title="Edit"
                                            onclick="editAccount(<?php echo $acc['id']; ?>, '<?php echo htmlspecialchars(addslashes($acc['name'])); ?>', '<?php echo htmlspecialchars(addslashes($acc['account_number'] ?? '')); ?>', <?php echo $acc['account_type_id'] ?? 0; ?>, <?php echo $acc['branch_id']; ?>, <?php echo $acc['balance']; ?>, <?php echo $acc['txn_count']; ?>)"><i class="fas fa-edit"></i></button>
                                        <?php if (!$acc['is_default']): ?>
                                            <button class="btn btn-sm btn-ghost text-danger" title="Delete"
                                                onclick="deleteAccount(<?php echo $acc['id']; ?>)"><i class="fas fa-trash"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Cards -->
            <div class="mobile-card">
                <?php foreach ($accounts as $acc): ?>
                    <div class="mobile-card-item" style="<?php echo !$acc['is_active'] ? 'opacity: 0.5;' : ''; ?>">
                        <div class="mobile-card-header">
                            <span class="font-semibold d-flex align-center gap-2">
                                <?php echo htmlspecialchars($acc['name']); ?>
                                <?php if ($acc['is_default']): ?>
                                    <span class="badge badge-primary" style="font-size: 0.6rem;">DEFAULT</span>
                                <?php endif; ?>
                            </span>
                            <span style="font-weight: 700; color: var(--primary);"><?php echo formatCurrency($acc['balance']); ?></span>
                        </div>
                        <div class="mobile-card-row">
                            <span class="mobile-card-label">Type</span>
                            <span class="mobile-card-value"><i class="fas <?php echo htmlspecialchars($acc['type_icon'] ?? 'fa-university'); ?>"></i> <?php echo htmlspecialchars($acc['type_name'] ?? '—'); ?></span>
                        </div>
                        <?php if ($acc['account_number']): ?>
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">Account #</span>
                                <span class="mobile-card-value"><?php echo htmlspecialchars($acc['account_number']); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="mobile-card-row">
                            <span class="mobile-card-label">Status</span>
                            <span class="mobile-card-value">
                                <?php if ($acc['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="mobile-card-row">
                            <span class="mobile-card-label">Transactions</span>
                            <span class="mobile-card-value"><?php echo $acc['txn_count']; ?></span>
                        </div>
                        <div class="mobile-card-actions">
                            <button class="btn btn-sm btn-ghost" onclick="editAccount(<?php echo $acc['id']; ?>, '<?php echo htmlspecialchars(addslashes($acc['name'])); ?>', '<?php echo htmlspecialchars(addslashes($acc['account_number'] ?? '')); ?>', <?php echo $acc['account_type_id'] ?? 0; ?>, <?php echo $acc['branch_id']; ?>, <?php echo $acc['balance']; ?>, <?php echo $acc['txn_count']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                            <?php if (!$acc['is_default']): ?>
                                <button class="btn btn-sm btn-ghost text-danger" onclick="deleteAccount(<?php echo $acc['id']; ?>)"><i class="fas fa-trash"></i> Delete</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Add/Edit Account Modal -->
<div class="modal-overlay" id="addAccountModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="accountModalTitle">Add Account</h3>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="accountForm">
                <input type="hidden" id="accountId" name="id" value="">
                <div class="form-group">
                    <label class="form-label required">Branch</label>
                    <select name="branch_id" id="accountBranch" class="form-control" required>
                        <option value="">Select Branch</option>
                        <?php foreach ($branches as $br): ?>
                            <option value="<?php echo $br['id']; ?>"><?php echo htmlspecialchars($br['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label required">Account Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Main Bank Account" required>
                </div>
                <div class="form-group">
                    <label class="form-label required">Account Type</label>
                    <select name="account_type_id" class="form-control" required>
                        <option value="">Select Account Type</option>
                        <?php foreach ($accountTypes as $at): ?>
                            <option value="<?php echo $at['id']; ?>"><?php echo htmlspecialchars($at['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Account Number</label>
                    <input type="text" name="account_number" class="form-control" placeholder="Optional">
                </div>
                <div class="form-group" id="balanceGroup">
                    <label class="form-label" id="balanceLabel">Starting Balance (<?php echo $currencySymbol; ?>)</label>
                    <input type="number" name="balance" id="balanceInput" class="form-control" step="0.01" min="0" value="0.00" placeholder="0.00">
                    <span class="form-help" id="balanceHelp">Set the initial account balance. Can only be set once — locked after first transaction.</span>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="KiTAcc.closeModal('addAccountModal')">Cancel</button>
            <button class="btn btn-primary" onclick="submitAccount()"><i class="fas fa-save"></i> Save</button>
        </div>
    </div>
</div>

<?php
$page_scripts = <<<'SCRIPT'
<script>
    function openAddAccount() {
        document.getElementById('accountId').value = '';
        document.getElementById('accountModalTitle').textContent = 'Add Account';
        document.getElementById('accountForm').reset();
        // Show balance field, make editable
        var balGroup = document.getElementById('balanceGroup');
        balGroup.style.display = '';
        document.getElementById('balanceInput').readOnly = false;
        document.getElementById('balanceInput').value = '0.00';
        document.getElementById('balanceHelp').textContent = 'Set the initial account balance. Can only be set once — locked after first transaction.';
        KiTAcc.openModal('addAccountModal');
    }

    function submitAccount() {
        var form = document.getElementById('accountForm');
        var data = KiTAcc.serializeForm(form);
        data.action = data.id ? 'update' : 'create';
        KiTAcc.post('api/accounts.php', data, function(res) {
            if (res.success) { KiTAcc.toast('Account saved!', 'success'); setTimeout(function() { location.reload(); }, 600); }
            else KiTAcc.toast(res.message || 'Error.', 'error');
        });
    }

    function editAccount(id, name, accNum, accountTypeId, branchId, balance, txnCount) {
        document.getElementById('accountId').value = id;
        document.getElementById('accountModalTitle').textContent = 'Edit Account';
        var form = document.getElementById('accountForm');
        form.querySelector('[name="name"]').value = name;
        form.querySelector('[name="account_number"]').value = accNum;
        form.querySelector('[name="account_type_id"]').value = accountTypeId;
        document.getElementById('accountBranch').value = branchId;

        // Balance: editable only if no transactions
        var balGroup = document.getElementById('balanceGroup');
        var balInput = document.getElementById('balanceInput');
        balGroup.style.display = '';
        balInput.value = balance;
        if (txnCount > 0) {
            balInput.readOnly = true;
            document.getElementById('balanceHelp').textContent = 'Balance is locked — this account has ' + txnCount + ' transaction(s).';
        } else {
            balInput.readOnly = false;
            document.getElementById('balanceHelp').textContent = 'You can still adjust the starting balance (no transactions yet).';
        }

        KiTAcc.openModal('addAccountModal');
    }

    function deleteAccount(id) {
        if (!KiTAcc.confirm('Delete this account? This cannot be undone.')) return;
        KiTAcc.post('api/accounts.php', { action: 'delete', id: id }, function(res) {
            if (res.success) { KiTAcc.toast('Deleted.', 'success'); setTimeout(function() { location.reload(); }, 600); }
            else KiTAcc.toast(res.message || 'Error.', 'error');
        });
    }
</script>
SCRIPT;
include __DIR__ . '/includes/footer.php';
?>
