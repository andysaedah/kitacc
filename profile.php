<?php
/**
 * KiTAcc - Profile Page
 * View/edit profile, change password
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();

$user = getCurrentUser();
$page_title = 'Profile - KiTAcc';

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-between align-center mb-6">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--gray-800);">My Profile</h1>
        <p class="text-muted">Manage your account information</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <!-- Profile Card -->
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 2rem;">
            <div class="avatar-initial xl" style="margin: 0 auto 1rem;">
                <?php echo htmlspecialchars($user['initials']); ?>
            </div>
            <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--gray-800); margin-bottom: 0.25rem;">
                <?php echo htmlspecialchars($user['name']); ?>
            </h3>
            <span class="badge badge-primary mb-2">
                <?php echo htmlspecialchars($user['role_label']); ?>
            </span>
            <p class="text-muted" style="font-size: 0.875rem;">
                <i class="fas fa-envelope"></i>
                <?php echo htmlspecialchars($user['email']); ?>
            </p>
            <?php if ($user['branch_name']): ?>
                <p class="text-muted" style="font-size: 0.875rem;">
                    <i class="fas fa-map-marker-alt"></i>
                    <?php echo htmlspecialchars($user['branch_name']); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Profile -->
    <div class="card" style="grid-column: span 2;">
        <div class="card-header">
            <div class="tabs">
                <button class="tab active" data-tab="profileTab">Profile Info</button>
                <button class="tab" data-tab="passwordTab">Change Password</button>
            </div>
        </div>
        <div class="card-body">
            <!-- Profile Tab -->
            <div class="tab-content active" id="profileTab">
                <form id="profileForm">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control"
                                value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control"
                                value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control"
                                value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                placeholder="e.g. +60123456789">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control"
                                value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            <span class="form-help">Username cannot be changed.</span>
                        </div>
                    </div>
                    <div style="margin-top: 1rem;">
                        <button type="button" class="btn btn-primary" onclick="updateProfile()">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <!-- Password Tab -->
            <div class="tab-content" id="passwordTab">
                <form id="passwordForm">
                    <div class="form-group">
                        <label class="form-label required">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">New Password</label>
                        <input type="password" name="new_password" class="form-control" minlength="8" required>
                        <span class="form-help">Minimum 8 characters</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="changePassword()">
                        <i class="fas fa-lock"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$page_scripts = <<<'SCRIPT'
<script>
    function updateProfile() {
        const form = document.getElementById('profileForm');
        const data = KiTAcc.serializeForm(form);
        data.action = 'update_profile';
        KiTAcc.post('api/profile.php', data, function(res) {
            if (res.success) KiTAcc.toast('Profile updated!', 'success');
            else KiTAcc.toast(res.message || 'Error updating profile.', 'error');
        });
    }

    function changePassword() {
        const form = document.getElementById('passwordForm');
        const data = KiTAcc.serializeForm(form);
        if (data.new_password !== data.confirm_password) {
            KiTAcc.toast('Passwords do not match.', 'error');
            return;
        }
        data.action = 'change_password';
        KiTAcc.post('api/profile.php', data, function(res) {
            if (res.success) {
                KiTAcc.toast('Password changed!', 'success');
                form.reset();
            } else {
                KiTAcc.toast(res.message || 'Error changing password.', 'error');
            }
        });
    }
</script>
SCRIPT;

include __DIR__ . '/includes/footer.php';
?>