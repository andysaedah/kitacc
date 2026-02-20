<?php
/**
 * KiTAcc - Account Types Management (Superadmin only)
 * Define account types, assign to branches, toggle active/inactive
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();
requireRole(ROLE_SUPERADMIN);

$page_title = 'Account Types - KiTAcc';

try {
    $pdo = db();

    // Get all account types with usage count
    $stmt = $pdo->query("
        SELECT at.*, 
               COUNT(a.id) AS account_count
        FROM account_types at
        LEFT JOIN accounts a ON a.account_type_id = at.id
        GROUP BY at.id
        ORDER BY at.name
    ");
    $accountTypes = $stmt->fetchAll();

    // Get branches for assignment
    $stmt = $pdo->query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name");
    $branches = $stmt->fetchAll();
} catch (Exception $e) {
    $accountTypes = [];
    $branches = [];
}

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-between align-center mb-6">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--gray-800);">Account Types</h1>
        <p class="text-muted">Define and manage account types for all branches</p>
    </div>
    <button class="btn btn-primary" onclick="openAddModal()"><i class="fas fa-plus"></i> Add Account Type</button>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($accountTypes)): ?>
            <div class="empty-state p-6">
                <i class="fas fa-layer-group"></i>
                <h3>No Account Types</h3>
                <p>Add your first account type to get started.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">Status</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th style="width: 80px;">Icon</th>
                            <th style="width: 100px;">Accounts</th>
                            <th style="width: 120px;">Created</th>
                            <th style="width: 100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accountTypes as $at): ?>
                            <tr style="<?php echo !$at['is_active'] ? 'opacity: 0.5;' : ''; ?>">
                                <td>
                                    <label class="toggle-switch">
                                        <input type="checkbox" <?php echo $at['is_active'] ? 'checked' : ''; ?>
                                            onchange="toggleTypeActive(<?php echo $at['id']; ?>, this.checked, this)">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </td>
                                <td>
                                    <span class="font-medium"><?php echo htmlspecialchars($at['name']); ?></span>
                                </td>
                                <td class="text-muted">
                                    <?php echo htmlspecialchars($at['description'] ?? '—'); ?>
                                </td>
                                <td>
                                    <span class="stat-icon <?php echo htmlspecialchars($at['color']); ?>" style="width: 2rem; height: 2rem; font-size: 0.8rem;">
                                        <i class="fas <?php echo htmlspecialchars($at['icon']); ?>"></i>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?php echo $at['account_count']; ?> account<?php echo $at['account_count'] != 1 ? 's' : ''; ?></span>
                                </td>
                                <td class="text-muted" style="font-size: 0.8rem;">
                                    <?php echo formatDate($at['created_at']); ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-icon btn-ghost btn-sm"
                                            onclick="editType(<?php echo $at['id']; ?>, '<?php echo htmlspecialchars(addslashes($at['name'])); ?>', '<?php echo htmlspecialchars(addslashes($at['description'] ?? '')); ?>', '<?php echo htmlspecialchars($at['icon']); ?>', '<?php echo htmlspecialchars($at['color']); ?>')"
                                            title="Edit"><i class="fas fa-edit"></i></button>
                                        <?php if ($at['account_count'] == 0): ?>
                                            <button class="btn btn-icon btn-ghost btn-sm text-danger"
                                                onclick="deleteType(<?php echo $at['id']; ?>)" title="Delete"><i
                                                    class="fas fa-trash"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="alert alert-info mt-4">
    <i class="fas fa-info-circle alert-icon"></i>
    <div class="alert-content">
        <strong>Tip:</strong> Account types are shared across all branches. When you create a new account in a branch, you can assign one of these types to it.
        Deactivating a type will hide it from the account creation dropdown but will not affect existing accounts.
    </div>
</div>

<!-- Add/Edit Account Type Modal -->
<div class="modal-overlay" id="typeModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="typeModalTitle">Add Account Type</h3>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="typeForm">
                <input type="hidden" id="typeId" name="id" value="">
                <div class="form-group">
                    <label class="form-label required">Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Savings Account" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control" placeholder="Brief description (optional)">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label required">Icon</label>
                        <select name="icon" class="form-control" required>
                            <option value="fa-university">Bank (fa-university)</option>
                            <option value="fa-coins">Coins (fa-coins)</option>
                            <option value="fa-wallet">Wallet (fa-wallet)</option>
                            <option value="fa-piggy-bank">Piggy Bank (fa-piggy-bank)</option>
                            <option value="fa-money-bill-wave">Cash (fa-money-bill-wave)</option>
                            <option value="fa-credit-card">Card (fa-credit-card)</option>
                            <option value="fa-hand-holding-usd">Holding USD (fa-hand-holding-usd)</option>
                            <option value="fa-landmark">Landmark (fa-landmark)</option>
                            <option value="fa-money-check-alt">Cheque (fa-money-check-alt)</option>
                            <option value="fa-donate">Donate (fa-donate)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Color</label>
                        <select name="color" class="form-control" required>
                            <option value="primary">Purple (Primary)</option>
                            <option value="secondary">Light Purple (Secondary)</option>
                            <option value="success">Green (Success)</option>
                            <option value="warning">Orange (Warning)</option>
                            <option value="danger">Red (Danger)</option>
                            <option value="info">Blue (Info)</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Preview</label>
                    <div id="iconPreview" class="d-flex align-center gap-2">
                        <span class="stat-icon primary" style="width: 2.5rem; height: 2.5rem;">
                            <i class="fas fa-university" id="previewIcon"></i>
                        </span>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="KiTAcc.closeModal('typeModal')">Cancel</button>
            <button class="btn btn-primary" onclick="submitType()"><i class="fas fa-save"></i> Save</button>
        </div>
    </div>
</div>

<?php
$page_scripts = <<<'SCRIPT'
<script>
    function openAddModal() {
        document.getElementById('typeId').value = '';
        document.getElementById('typeModalTitle').textContent = 'Add Account Type';
        document.getElementById('typeForm').reset();
        KiTAcc.openModal('typeModal');
    }

    function submitType() {
        const data = KiTAcc.serializeForm(document.getElementById('typeForm'));
        data.action = data.id ? 'update' : 'create';
        KiTAcc.post('api/account_types.php', data, function(res) {
            if (res.success) { KiTAcc.toast('Account type saved!', 'success'); setTimeout(() => location.reload(), 600); }
            else KiTAcc.toast(res.message || 'Error.', 'error');
        });
    }

    function editType(id, name, description, icon, color) {
        document.getElementById('typeId').value = id;
        document.getElementById('typeModalTitle').textContent = 'Edit Account Type';
        const form = document.getElementById('typeForm');
        form.querySelector('[name="name"]').value = name;
        form.querySelector('[name="description"]').value = description;
        form.querySelector('[name="icon"]').value = icon;
        form.querySelector('[name="color"]').value = color;
        updatePreview();
        KiTAcc.openModal('typeModal');
    }

    function deleteType(id) {
        if (!KiTAcc.confirm('Delete this account type? This cannot be undone.')) return;
        KiTAcc.post('api/account_types.php', { action: 'delete', id: id }, function(res) {
            if (res.success) { KiTAcc.toast('Deleted.', 'success'); setTimeout(() => location.reload(), 600); }
            else KiTAcc.toast(res.message || 'Error.', 'error');
        });
    }

    function toggleTypeActive(id, isActive, el) {
        const row = el.closest('tr');
        KiTAcc.post('api/account_types.php', { action: 'toggle_active', id: id, is_active: isActive ? 1 : 0 }, function(res) {
            if (res.success) {
                KiTAcc.toast(isActive ? 'Account type activated.' : 'Account type deactivated.', 'success');
                row.style.opacity = isActive ? '1' : '0.5';
            } else {
                KiTAcc.toast(res.message || 'Error.', 'error');
                el.checked = !isActive;
            }
        });
    }

    function updatePreview() {
        const icon = document.querySelector('[name="icon"]').value;
        const color = document.querySelector('[name="color"]').value;
        const previewIcon = document.getElementById('previewIcon');
        const previewWrap = previewIcon.closest('.stat-icon');
        previewIcon.className = 'fas ' + icon;
        previewWrap.className = 'stat-icon ' + color;
        previewWrap.style.width = '2.5rem';
        previewWrap.style.height = '2.5rem';
    }

    // Live preview on change
    document.querySelector('[name="icon"]').addEventListener('change', updatePreview);
    document.querySelector('[name="color"]').addEventListener('change', updatePreview);
</script>
SCRIPT;
include __DIR__ . '/includes/footer.php';
?>
