<?php
/**
 * KiTAcc - Accounts (Branch Finance view)
 * View-only account list with active/inactive toggle
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
} catch (Exception $e) {
    $accountList = [];
}

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-between align-center mb-6">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--gray-800);">Accounts</h1>
        <p class="text-muted">View branch accounts and available balances</p>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php if (empty($accountList)): ?>
        <div class="card" style="grid-column: 1 / -1;">
            <div class="card-body">
                <div class="empty-state"><i class="fas fa-university"></i>
                    <h3>No Accounts</h3>
                    <p>No accounts have been assigned to your branch yet. Contact the superadmin.</p>
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
                    <div class="font-semibold mt-2 text-primary" style="font-size: 1.25rem;">
                        <?php echo formatCurrency($acc['balance']); ?>
                    </div>
                    <div class="text-muted" style="font-size: 0.7rem; margin-top: 0.25rem;">Current available balance</div>
                    <?php if (!$acc['is_default']): ?>
                        <div class="d-flex align-center mt-2">
                            <label class="toggle-switch" title="<?php echo $acc['is_active'] ? 'Deactivate account' : 'Activate account'; ?>">
                                <input type="checkbox" <?php echo $acc['is_active'] ? 'checked' : ''; ?>
                                    onchange="toggleAccountActive(<?php echo $acc['id']; ?>, this.checked, this)">
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="text-muted" style="font-size: 0.75rem; margin-left: 0.5rem;">
                                <?php echo $acc['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php
$page_scripts = <<<'SCRIPT'
<script>
    function toggleAccountActive(id, isActive, el) {
        var card = el.closest('.stat-card');
        KiTAcc.post('api/accounts.php', { action: 'toggle_active', id: id, is_active: isActive ? 1 : 0 }, function(res) {
            if (res.success) {
                KiTAcc.toast(isActive ? 'Account activated.' : 'Account deactivated.', 'success');
                card.style.opacity = isActive ? '1' : '0.5';
                setTimeout(function() { location.reload(); }, 600);
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