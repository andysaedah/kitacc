<?php
/**
 * KiTAcc - Branch Management (Superadmin only)
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();
requireRole(ROLE_SUPERADMIN);

$page_title = 'Branches - KiTAcc';

try {
    $pdo = db();
    $stmt = $pdo->query("SELECT * FROM branches ORDER BY name");
    $branches = $stmt->fetchAll();
} catch (Exception $e) {
    $branches = [];
}

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-between align-center mb-6">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--gray-800);">Branches</h1>
        <p class="text-muted">Manage church branches</p>
    </div>
    <button class="btn btn-primary" onclick="KiTAcc.openModal('addBranchModal')"><i class="fas fa-plus"></i> Add
        Branch</button>
</div>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Address</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($branches)): ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state"><i class="fas fa-code-branch"></i>
                                <h3>No Branches</h3>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($branches as $br): ?>
                        <tr>
                            <td class="font-semibold">
                                <?php echo htmlspecialchars($br['name']); ?>
                            </td>
                            <td class="text-muted">
                                <?php echo htmlspecialchars($br['address'] ?? '—'); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($br['phone'] ?? '—'); ?>
                            </td>
                            <td>
                                <label class="toggle-switch" title="Toggle active">
                                    <input type="checkbox" <?php echo $br['is_active'] ? 'checked' : ''; ?>
                                        onchange="toggleBranchActive(<?php echo $br['id']; ?>, this.checked)">
                                    <span class="toggle-slider"></span>
                                </label>
                            </td>
                            <td>
                                <button class="btn btn-icon btn-ghost btn-sm"
                                    onclick="editBranch(<?php echo htmlspecialchars(json_encode($br)); ?>)"><i
                                        class="fas fa-edit"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="addBranchModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="branchModalTitle">Add Branch</h3><button class="modal-close"><i
                    class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="branchForm"><input type="hidden" id="branchId" name="id" value="">
                <div class="form-group"><label class="form-label required">Branch Name</label><input type="text"
                        name="name" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Address</label><textarea name="address"
                        class="form-control" rows="2"></textarea></div>
                <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone"
                        class="form-control"></div>
            </form>
        </div>
        <div class="modal-footer"><button class="btn btn-outline"
                onclick="KiTAcc.closeModal('addBranchModal')">Cancel</button><button class="btn btn-primary"
                onclick="submitBranch()"><i class="fas fa-save"></i> Save</button></div>
    </div>
</div>

<?php
$page_scripts = <<<'SCRIPT'
<script>
    function submitBranch() { const data = KiTAcc.serializeForm(document.getElementById('branchForm')); data.action = data.id ? 'update' : 'create'; KiTAcc.post('api/branches.php', data, function(res) { if (res.success) { KiTAcc.toast('Saved!', 'success'); setTimeout(() => location.reload(), 600); } else KiTAcc.toast(res.message || 'Error.', 'error'); }); }
    function editBranch(data) { document.getElementById('branchId').value = data.id; document.getElementById('branchModalTitle').textContent = 'Edit Branch'; const form = document.getElementById('branchForm'); form.querySelector('[name="name"]').value = data.name; form.querySelector('[name="address"]').value = data.address || ''; form.querySelector('[name="phone"]').value = data.phone || ''; KiTAcc.openModal('addBranchModal'); }
    function toggleBranchActive(id, isActive) {
        KiTAcc.post('api/branches.php', { action: 'toggle_active', id: id, is_active: isActive ? 1 : 0 }, function(res) {
            if (res.success) KiTAcc.toast(isActive ? 'Branch activated.' : 'Branch deactivated.', 'success');
            else { KiTAcc.toast(res.message || 'Error.', 'error'); setTimeout(() => location.reload(), 400); }
        });
    }
</script>
SCRIPT;
include __DIR__ . '/includes/footer.php';
?>