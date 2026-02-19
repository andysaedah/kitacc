<?php
/**
 * KiTAcc - User Management (Superadmin only)
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();
requireRole(ROLE_SUPERADMIN);

$page_title = 'Users - KiTAcc';

try {
    $pdo = db();
    $stmt = $pdo->query("SELECT u.*, b.name AS branch_name FROM users u LEFT JOIN branches b ON u.branch_id = b.id ORDER BY u.name");
    $users = $stmt->fetchAll();
    $stmt = $pdo->query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name");
    $branches = $stmt->fetchAll();
} catch (Exception $e) {
    $users = [];
    $branches = [];
}

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-between align-center mb-6">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--gray-800);">Users</h1>
        <p class="text-muted">Manage system users and roles</p>
    </div>
    <button class="btn btn-primary" onclick="openAddUser()"><i class="fas fa-plus"></i> Add User</button>
</div>

<div class="card table-responsive-cards">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Branch</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-center gap-2">
                                <div class="avatar-initial" style="width: 32px; height: 32px; font-size: 0.75rem;">
                                    <?php echo getInitials($u['name']); ?>
                                </div>
                                <span class="font-medium">
                                    <?php echo htmlspecialchars($u['name']); ?>
                                </span>
                            </div>
                        </td>
                        <td class="text-muted">
                            <?php echo htmlspecialchars($u['username']); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($u['email']); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($u['branch_name'] ?? '—'); ?>
                        </td>
                        <td><span class="badge badge-primary">
                                <?php echo ROLE_LABELS[$u['role']] ?? $u['role']; ?>
                            </span></td>
                        <td>
                            <label class="toggle-switch" title="Toggle active">
                                <input type="checkbox" <?php echo $u['is_active'] ? 'checked' : ''; ?>
                                    onchange="toggleUserActive(<?php echo $u['id']; ?>, this.checked)">
                                <span class="toggle-slider"></span>
                            </label>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <button class="btn btn-icon btn-ghost btn-sm"
                                    onclick='editUser(<?php echo json_encode($u); ?>)'><i class="fas fa-edit"></i></button>
                                <button class="btn btn-icon btn-ghost btn-sm" title="Send password reset email"
                                    onclick="resetPassword(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['name'], ENT_QUOTES); ?>')">
                                    <i class="fas fa-key" style="color: var(--warning);"></i></button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="mobile-card">
        <?php foreach ($users as $u): ?>
            <div class="mobile-card-item">
                <div class="mobile-card-header">
                    <div class="d-flex align-center gap-2">
                        <div class="avatar-initial" style="width: 32px; height: 32px; font-size: 0.75rem;">
                            <?php echo getInitials($u['name']); ?>
                        </div>
                        <span class="font-semibold">
                            <?php echo htmlspecialchars($u['name']); ?>
                        </span>
                    </div>
                    <span class="badge badge-primary">
                        <?php echo ROLE_LABELS[$u['role']] ?? $u['role']; ?>
                    </span>
                </div>
                <div class="mobile-card-row"><span class="mobile-card-label">Branch</span><span class="mobile-card-value">
                        <?php echo htmlspecialchars($u['branch_name'] ?? '—'); ?>
                    </span></div>
                <div class="mobile-card-row"><span class="mobile-card-label">Status</span><span class="mobile-card-value">
                        <label class="toggle-switch">
                            <input type="checkbox" <?php echo $u['is_active'] ? 'checked' : ''; ?>
                                onchange="toggleUserActive(<?php echo $u['id']; ?>, this.checked)">
                            <span class="toggle-slider"></span>
                        </label>
                    </span></div>
                <div class="mobile-card-actions">
                    <button class="btn btn-sm btn-ghost" onclick='editUser(<?php echo json_encode($u); ?>)'><i
                            class="fas fa-edit"></i> Edit</button>
                    <button class="btn btn-sm btn-ghost" style="color: var(--warning);"
                        onclick="resetPassword(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['name'], ENT_QUOTES); ?>')">
                        <i class="fas fa-key"></i> Reset Password</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="modal-overlay" id="addUserModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="userModalTitle">Add User</h3><button class="modal-close"><i
                    class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="userForm"><input type="hidden" id="userId" name="id" value="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group"><label class="form-label required">Full Name</label><input type="text"
                            name="name" class="form-control" required></div>
                    <div class="form-group"><label class="form-label required">Username</label><input type="text"
                            name="username" class="form-control" required></div>
                    <div class="form-group"><label class="form-label required">Email</label><input type="email"
                            name="email" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone"
                            class="form-control"></div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label required">Branch</label>
                        <select name="branch_id" class="form-control" required>
                            <option value="">Select Branch</option>
                            <?php foreach ($branches as $br): ?>
                                <option value="<?php echo $br['id']; ?>">
                                    <?php echo htmlspecialchars($br['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Role</label>
                        <select name="role" class="form-control" required>
                            <?php foreach (ROLE_LABELS as $key => $label): ?>
                                <option value="<?php echo $key; ?>">
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group" id="passwordField">
                    <label class="form-label required">Password</label>
                    <div class="input-group">
                        <input type="password" name="password" class="form-control has-icon-right" minlength="8">
                        <button type="button" class="input-group-icon-right toggle-password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <span class="form-help">Min 8 characters. Leave blank when editing to keep current password.</span>
                </div>
            </form>
        </div>
        <div class="modal-footer"><button class="btn btn-outline"
                onclick="KiTAcc.closeModal('addUserModal')">Cancel</button><button class="btn btn-primary"
                onclick="submitUser()"><i class="fas fa-save"></i> Save</button></div>
    </div>
</div>

<?php
$page_scripts = <<<'SCRIPT'
<script>
    function openAddUser() {
        document.getElementById('userId').value = '';
        document.getElementById('userModalTitle').textContent = 'Add User';
        document.getElementById('userForm').reset();
        document.getElementById('userForm').querySelector('[name="password"]').setAttribute('required', '');
        KiTAcc.openModal('addUserModal');
    }
    function editUser(data) {
        document.getElementById('userId').value = data.id;
        document.getElementById('userModalTitle').textContent = 'Edit User';
        const form = document.getElementById('userForm');
        form.querySelector('[name="name"]').value = data.name;
        form.querySelector('[name="username"]').value = data.username;
        form.querySelector('[name="email"]').value = data.email;
        form.querySelector('[name="phone"]').value = data.phone || '';
        form.querySelector('[name="branch_id"]').value = data.branch_id || '';
        form.querySelector('[name="role"]').value = data.role;
        form.querySelector('[name="password"]').removeAttribute('required');
        KiTAcc.openModal('addUserModal');
    }
    function submitUser() {
        const data = KiTAcc.serializeForm(document.getElementById('userForm'));
        data.action = data.id ? 'update' : 'create';
        KiTAcc.post('api/users.php', data, function(res) {
            if (res.success) { KiTAcc.toast('Saved!', 'success'); setTimeout(() => location.reload(), 600); }
            else KiTAcc.toast(res.message || 'Error.', 'error');
        });
    }
    function toggleUserActive(id, isActive) {
        KiTAcc.post('api/users.php', { action: 'toggle_active', id: id, is_active: isActive ? 1 : 0 }, function(res) {
            if (res.success) KiTAcc.toast(isActive ? 'User activated.' : 'User deactivated.', 'success');
            else { KiTAcc.toast(res.message || 'Error.', 'error'); setTimeout(() => location.reload(), 400); }
        });
    }
    function resetPassword(id, name) {
        if (!confirm('Send password reset email to ' + name + '?')) return;
        KiTAcc.post('api/users.php', { action: 'reset_password', id: id }, function(res) {
            if (res.success) KiTAcc.toast(res.message, 'success');
            else KiTAcc.toast(res.message || 'Failed to send reset email.', 'error');
        });
    }
</script>
SCRIPT;
include __DIR__ . '/includes/footer.php';
?>