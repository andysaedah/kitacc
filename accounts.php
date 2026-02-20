<?php
/**
 * KiTAcc - Accounts Management
 * Bank account CRUD with activate/deactivate
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();
requireRole(ROLE_BRANCH_FINANCE);

$user = getCurrentUser();
$branchId = getActiveBranchId();
$page_title = 'Accounts - KiTAcc';

try {
    $pdo = db();
    $sql = "SELECT a.*, b.name AS branch_name, 
                   at.name AS type_name, at.icon AS type_icon, at.color AS type_color
            FROM accounts a 
            LEFT JOIN branches b ON a.branch_id = b.id 
            LEFT JOIN account_types at ON a.account_type_id = at.id 
            WHERE 1=1";
    $params = [];
    if ($branchId !== null) {
        $sql .= " AND a.branch_id = ?";
        $params[] = $branchId;
    }
    $sql .= " ORDER BY a.is_default DESC, a.is_active DESC, a.name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $accountList = $stmt->fetchAll();

    // Branches for dropdown (superadmin)
    $branches = [];
    if ($user['role'] === ROLE_SUPERADMIN) {
        $stmt = $pdo->query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name");
        $branches = $stmt->fetchAll();
    }

    // Account types for dropdown
    $accountTypes = [];
    try {
        $stmt = $pdo->query("SELECT id, name, icon, color FROM account_types WHERE is_active = 1 ORDER BY name");
        $accountTypes = $stmt->fetchAll();
    } catch (Exception $e) {
        // account_types table may not exist yet (pre-migration)
    }
} catch (Exception $e) {
    $accountList = [];
    $branches = [];
    $accountTypes = [];
}

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-between align-center mb-6">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--gray-800);">Accounts</h1>
        <p class="text-muted">Manage bank accounts</p>
    </div>
    <?php if ($user['role'] === ROLE_SUPERADMIN): ?>
        <button class="btn btn-primary" onclick="openAddAccount()"><i class="fas fa-plus"></i> Add Account</button>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php if (empty($accountList)): ?>
        <div class="card" style="grid-column: 1 / -1;">
            <div class="card-body">
                <div class="empty-state"><i class="fas fa-university"></i>
                    <h3>No Accounts</h3>
                    <p>Add your first bank account.</p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($accountList as $acc): ?>
            <div class="stat-card" style="cursor: default; <?php echo !$acc['is_active'] ? 'opacity: 0.5;' : ''; ?>">
                <div class="stat-icon <?php echo htmlspecialchars($acc['type_color'] ?? 'primary'); ?>">
                    <i class="fas <?php echo htmlspecialchars($acc['type_icon'] ?? 'fa-university'); ?>"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label d-flex align-center gap-2">
                        <?php echo htmlspecialchars($acc['type_name'] ?? 'Unknown Type'); ?>
                        <?php if ($acc['is_default']): ?>
                            <span class="badge badge-primary" style="font-size: 0.6rem; padding: 0.15rem 0.4rem;">DEFAULT</span>
                        <?php endif; ?>
                        <?php if (!$acc['is_active']): ?>
                            <span class="badge badge-danger" style="font-size: 0.6rem; padding: 0.15rem 0.4rem;">INACTIVE</span>
                        <?php endif; ?>
                    </div>
                    <div class="stat-value" style="font-size: 1.125rem;">
                        <?php echo htmlspecialchars($acc['name']); ?>
                    </div>
                    <?php if ($acc['account_number']): ?>
                        <div class="text-muted mt-1" style="font-size: 0.75rem;">
                            <?php echo htmlspecialchars($acc['account_number']); ?>
                        </div>
                    <?php endif; ?>
                    <div class="font-semibold mt-2 text-primary">
                        <?php echo formatCurrency($acc['balance']); ?>
                    </div>
                    <div class="d-flex align-center justify-between mt-2">
                        <label class="toggle-switch" title="<?php echo $acc['is_default'] ? 'Default account cannot be deactivated' : ($acc['is_active'] ? 'Deactivate account' : 'Activate account'); ?>">
                            <input type="checkbox" <?php echo $acc['is_active'] ? 'checked' : ''; ?>
                                <?php echo $acc['is_default'] ? 'disabled' : ''; ?>
                                onchange="toggleAccountActive(<?php echo $acc['id']; ?>, this.checked, this)">
                            <span class="toggle-slider"></span>
                        </label>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-ghost"
                                onclick="editAccount(<?php echo $acc['id']; ?>, '<?php echo htmlspecialchars(addslashes($acc['name'])); ?>', '<?php echo htmlspecialchars(addslashes($acc['account_number'] ?? '')); ?>', '<?php echo $acc['account_type_id'] ?? ''; ?>')"><i
                                    class="fas fa-edit"></i></button>
                            <?php if ($user['role'] === ROLE_SUPERADMIN && !$acc['is_default']): ?>
                                <button class="btn btn-sm btn-ghost text-danger" onclick="deleteAccount(<?php echo $acc['id']; ?>)"><i
                                        class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Add/Edit Account Modal -->
<div class="modal-overlay" id="addAccountModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="accountModalTitle"><?php echo $user['role'] === ROLE_SUPERADMIN ? 'Add Account' : 'Rename Account'; ?></h3>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="accountForm">
                <input type="hidden" id="accountId" name="id" value="">
                <?php if ($user['role'] === ROLE_SUPERADMIN): ?>
                    <?php if (!empty($branches)): ?>
                        <div class="form-group" id="branchGroup">
                            <label class="form-label required">Branch</label>
                            <select name="branch_id" class="form-control" required>
                                <option value="">Select Branch</option>
                                <?php foreach ($branches as $br): ?>
                                    <option value="<?php echo $br['id']; ?>"><?php echo htmlspecialchars($br['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="form-group">
                    <label class="form-label required">Account Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Main Bank Account" required>
                </div>
                <?php if ($user['role'] === ROLE_SUPERADMIN): ?>
                    <div class="form-group" id="accountTypeGroup">
                        <label class="form-label required">Account Type</label>
                        <select name="account_type_id" class="form-control" required>
                            <option value="">Select Account Type</option>
                            <?php foreach ($accountTypes as $at): ?>
                                <option value="<?php echo $at['id']; ?>"><?php echo htmlspecialchars($at['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="accountNumberGroup">
                        <label class="form-label">Account Number</label>
                        <input type="text" name="account_number" class="form-control" placeholder="Optional">
                    </div>
                    <div class="form-group" id="startingBalanceGroup">
                        <label class="form-label">Starting Balance (<?php echo getSetting('currency_symbol', 'RM'); ?>)</label>
                        <input type="number" name="balance" class="form-control" step="0.01" min="0" value="0.00" placeholder="0.00">
                        <span class="form-help">Set the initial account balance (e.g. 2000.00). Only applies when creating.</span>
                    </div>
                <?php endif; ?>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="KiTAcc.closeModal('addAccountModal')">Cancel</button>
            <button class="btn btn-primary" onclick="submitAccount()"><i class="fas fa-save"></i> Save</button>
        </div>
    </div>
</div>

<?php
$isSuperadmin = $user['role'] === ROLE_SUPERADMIN ? 'true' : 'false';
$page_scripts = "<script>var IS_SUPERADMIN = {$isSuperadmin};</script>";
$page_scripts .= <<<'SCRIPT'
<script>
    function openAddAccount() {
        document.getElementById('accountId').value = '';
        document.getElementById('accountModalTitle').textContent = 'Add Account';
        document.getElementById('accountForm').reset();
        var balGroup = document.getElementById('startingBalanceGroup');
        if (balGroup) balGroup.style.display = '';
        KiTAcc.openModal('addAccountModal');
    }
    function submitAccount() {
        const form = document.getElementById('accountForm');
        const data = KiTAcc.serializeForm(form);
        data.action = data.id ? 'update' : 'create';
        KiTAcc.post('api/accounts.php', data, function(res) {
            if (res.success) { KiTAcc.toast('Account saved!', 'success'); setTimeout(() => location.reload(), 600); }
            else KiTAcc.toast(res.message || 'Error.', 'error');
        });
    }
    function editAccount(id, name, accNum, accountTypeId) {
        document.getElementById('accountId').value = id;
        document.getElementById('accountModalTitle').textContent = IS_SUPERADMIN ? 'Edit Account' : 'Rename Account';
        var form = document.getElementById('accountForm');
        form.querySelector('[name="name"]').value = name;
        if (IS_SUPERADMIN) {
            var accNumField = form.querySelector('[name="account_number"]');
            if (accNumField) accNumField.value = accNum;
            var typeField = form.querySelector('[name="account_type_id"]');
            if (typeField) typeField.value = accountTypeId || '';
            // Hide starting balance when editing
            var balGroup = document.getElementById('startingBalanceGroup');
            if (balGroup) balGroup.style.display = 'none';
        }
        KiTAcc.openModal('addAccountModal');
    }
    function deleteAccount(id) {
        if (!KiTAcc.confirm('Delete this account? This cannot be undone.')) return;
        KiTAcc.post('api/accounts.php', { action: 'delete', id: id }, function(res) {
            if (res.success) { KiTAcc.toast('Deleted.', 'success'); setTimeout(() => location.reload(), 600); }
            else KiTAcc.toast(res.message || 'Error.', 'error');
        });
    }
    function toggleAccountActive(id, isActive, el) {
        var card = el.closest('.stat-card');
        KiTAcc.post('api/accounts.php', { action: 'toggle_active', id: id, is_active: isActive ? 1 : 0 }, function(res) {
            if (res.success) {
                KiTAcc.toast(isActive ? 'Account activated.' : 'Account deactivated.', 'success');
                card.style.opacity = isActive ? '1' : '0.5';
                setTimeout(() => location.reload(), 600);
            } else {
                KiTAcc.toast(res.message || 'Error.', 'error');
                el.checked = !isActive;
            }
        });
    }
</script>
SCRIPT;
include __DIR__ . '/includes/footer.php';
?>