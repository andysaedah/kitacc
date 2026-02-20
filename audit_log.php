<?php
/**
 * KiTAcc - Audit Log
 * View all system activity: login/logout, CRUD actions, changes
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();
requireRole(ROLE_SUPERADMIN);

$page_title = 'Audit Log - KiTAcc';
$user = getCurrentUser();

// Filters
$filterUser = $_GET['user_id'] ?? '';
$filterAction = $_GET['action'] ?? '';
$filterEntity = $_GET['entity_type'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

try {
    $pdo = db();

    // Build query with filters
    $where = [];
    $params = [];

    if ($filterUser) {
        $where[] = 'a.user_id = ?';
        $params[] = intval($filterUser);
    }
    if ($filterAction) {
        $where[] = 'a.action = ?';
        $params[] = $filterAction;
    }
    if ($filterEntity) {
        $where[] = 'a.entity_type = ?';
        $params[] = $filterEntity;
    }
    if ($filterDateFrom) {
        $where[] = 'a.created_at >= ?';
        $params[] = $filterDateFrom . ' 00:00:00';
    }
    if ($filterDateTo) {
        $where[] = 'a.created_at <= ?';
        $params[] = $filterDateTo . ' 23:59:59';
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count total
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_log a {$whereClause}");
    $countStmt->execute($params);
    $totalRecords = intval($countStmt->fetchColumn());
    $totalPages = max(1, ceil($totalRecords / $perPage));

    // Fetch records
    $sql = "SELECT a.*, u.name AS user_name, u.email AS user_email
            FROM audit_log a
            LEFT JOIN users u ON a.user_id = u.id
            {$whereClause}
            ORDER BY a.created_at DESC
            LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);
    $params[] = $perPage;
    $params[] = $offset;
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    // Get distinct values for filter dropdowns
    $actions = $pdo->query("SELECT DISTINCT action FROM audit_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
    $entityTypes = $pdo->query("SELECT DISTINCT entity_type FROM audit_log ORDER BY entity_type")->fetchAll(PDO::FETCH_COLUMN);
    $users = $pdo->query("SELECT id, name FROM users ORDER BY name")->fetchAll();

} catch (Exception $e) {
    $logs = [];
    $actions = [];
    $entityTypes = [];
    $users = [];
    $totalRecords = 0;
    $totalPages = 1;
}

// Action badge colors
function actionBadge(string $action): string
{
    $map = [
        'login' => 'badge-success',
        'logout' => 'badge-secondary',
        'create' => 'badge-primary',
        'income_created' => 'badge-primary',
        'expense_created' => 'badge-primary',
        'transaction_updated' => 'badge-warning',
        'transaction_deleted' => 'badge-danger',
        'update' => 'badge-warning',
        'delete' => 'badge-danger',
        'approve' => 'badge-success',
        'reject' => 'badge-danger',
        'toggle_active' => 'badge-warning',
        'change_password' => 'badge-warning',
        'transfer' => 'badge-primary',
        'delete_transfer' => 'badge-danger',
        // Account actions
        'account_created' => 'badge-primary',
        'account_updated' => 'badge-warning',
        'account_deleted' => 'badge-danger',
        'account_activated' => 'badge-success',
        'account_deactivated' => 'badge-danger',
        // Fund actions
        'fund_created' => 'badge-primary',
        'fund_updated' => 'badge-warning',
        'fund_deleted' => 'badge-danger',
        'fund_transfer' => 'badge-primary',
        'fund_transfer_deleted' => 'badge-danger',
        // Claim actions
        'claim_submitted' => 'badge-primary',
        'claim_updated' => 'badge-warning',
        'claim_approved' => 'badge-success',
        'claim_rejected' => 'badge-danger',
        'claim_deleted' => 'badge-danger',
    ];
    return $map[$action] ?? 'badge-secondary';
}

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-between align-center mb-6">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--gray-800);">Audit Log</h1>
        <p class="text-muted">System activity trail —
            <?php echo number_format($totalRecords); ?> records
        </p>
    </div>
</div>

<!-- Filters -->
<div class="card mb-6">
    <div class="card-body" style="padding: 1rem;">
        <form method="GET" style="display: flex; flex-direction: column; gap: 0.75rem;">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 0.75rem;">User</label>
                    <select name="user_id" class="form-control" style="font-size: 0.8125rem;">
                        <option value="">All Users</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $filterUser == $u['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 0.75rem;">Action</label>
                    <select name="action" class="form-control" style="font-size: 0.8125rem;">
                        <option value="">All Actions</option>
                        <?php foreach ($actions as $a): ?>
                            <option value="<?php echo htmlspecialchars($a); ?>" <?php echo $filterAction === $a ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $a))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 0.75rem;">Entity Type</label>
                    <select name="entity_type" class="form-control" style="font-size: 0.8125rem;">
                        <option value="">All Types</option>
                        <?php foreach ($entityTypes as $et): ?>
                            <option value="<?php echo htmlspecialchars($et); ?>" <?php echo $filterEntity === $et ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $et))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4" style="align-items: end;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 0.75rem;">From</label>
                    <input type="date" name="date_from" class="form-control" style="font-size: 0.8125rem;"
                        value="<?php echo htmlspecialchars($filterDateFrom); ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 0.75rem;">To</label>
                    <input type="date" name="date_to" class="form-control" style="font-size: 0.8125rem;"
                        value="<?php echo htmlspecialchars($filterDateTo); ?>">
                </div>
                <div class="d-flex gap-2" style="align-items: end;">
                    <button type="submit" class="btn btn-primary" style="font-size: 0.8125rem; flex: 1;"><i
                            class="fas fa-filter"></i> Filter</button>
                    <a href="audit_log.php" class="btn btn-outline"
                        style="font-size: 0.8125rem; flex: 1; text-align: center;">Clear</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Audit Log Table -->
<div class="card table-responsive-cards">
    <!-- Desktop Table -->
    <div class="table-container">
        <table class="table" id="auditTable">
            <thead>
                <tr>
                    <th style="width: 140px;">Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Entity</th>
                    <th>ID</th>
                    <th>IP Address</th>
                    <th style="width: 40px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted" style="padding: 2rem;">No audit records found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="font-size: 0.75rem; color: var(--gray-500); white-space: nowrap;">
                                <?php echo date('d M Y, h:i:s A', strtotime($log['created_at'])); ?>
                            </td>
                            <td>
                                <div style="font-weight: 600; font-size: 0.8125rem;">
                                    <?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?>
                                </div>
                                <?php if ($log['user_email']): ?>
                                    <div style="font-size: 0.6875rem; color: var(--gray-400);">
                                        <?php echo htmlspecialchars($log['user_email']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo actionBadge($log['action']); ?>">
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $log['action']))); ?>
                                </span>
                            </td>
                            <td style="font-size: 0.8125rem;">
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $log['entity_type']))); ?>
                            </td>
                            <td style="font-size: 0.8125rem; color: var(--gray-500);">
                                <?php echo $log['entity_id'] ? '#' . $log['entity_id'] : '-'; ?>
                            </td>
                            <td style="font-size: 0.75rem; color: var(--gray-400); font-family: monospace;">
                                <?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?>
                            </td>
                            <td>
                                <?php if ($log['old_values'] || $log['new_values']): ?>
                                    <button class="btn btn-icon btn-ghost" style="width: 24px; height: 24px; font-size: 0.625rem;"
                                        title="View Details" onclick="showDetails(<?php echo $log['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($log['old_values'] || $log['new_values']): ?>
                            <tr id="details-<?php echo $log['id']; ?>" style="display: none;">
                                <td colspan="7" style="padding: 0.75rem 1rem; background: var(--gray-50);">
                                    <div style="font-size: 0.75rem;">
                                        <?php if ($log['old_values']): ?>
                                            <div style="margin-bottom: 0.5rem;">
                                                <strong style="color: var(--gray-600);">Old Values:</strong>
                                                <pre
                                                    style="margin: 0.25rem 0; padding: 0.5rem; background: var(--white); border: 1px solid var(--gray-200); border-radius: 4px; font-size: 0.6875rem; overflow-x: auto; white-space: pre-wrap;"><?php
                                                    $old = json_decode($log['old_values'], true);
                                                    echo $old ? htmlspecialchars(json_encode($old, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) : htmlspecialchars($log['old_values']);
                                                    ?></pre>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($log['new_values']): ?>
                                            <div>
                                                <strong style="color: var(--gray-600);">New Values:</strong>
                                                <pre
                                                    style="margin: 0.25rem 0; padding: 0.5rem; background: var(--white); border: 1px solid var(--gray-200); border-radius: 4px; font-size: 0.6875rem; overflow-x: auto; white-space: pre-wrap;"><?php
                                                    $new = json_decode($log['new_values'], true);
                                                    echo $new ? htmlspecialchars(json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) : htmlspecialchars($log['new_values']);
                                                    ?></pre>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Cards -->
    <div class="mobile-card">
        <?php if (empty($logs)): ?>
            <div class="empty-state"><i class="fas fa-history"></i>
                <p>No audit records found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($logs as $log): ?>
                <div class="mobile-card-item">
                    <div class="mobile-card-header">
                        <span class="font-semibold" style="font-size: 0.8125rem;">
                            <?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?>
                        </span>
                        <span class="badge <?php echo actionBadge($log['action']); ?>">
                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $log['action']))); ?>
                        </span>
                    </div>
                    <div class="mobile-card-row">
                        <span class="mobile-card-label">Timestamp</span>
                        <span class="mobile-card-value" style="font-size: 0.75rem;">
                            <?php echo date('d M Y, h:i A', strtotime($log['created_at'])); ?>
                        </span>
                    </div>
                    <div class="mobile-card-row">
                        <span class="mobile-card-label">Entity</span>
                        <span class="mobile-card-value">
                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $log['entity_type']))); ?>
                            <?php if ($log['entity_id']): ?> #<?php echo $log['entity_id']; ?><?php endif; ?>
                        </span>
                    </div>
                    <div class="mobile-card-row">
                        <span class="mobile-card-label">IP</span>
                        <span class="mobile-card-value" style="font-family: monospace; font-size: 0.75rem;">
                            <?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?>
                        </span>
                    </div>
                    <?php if ($log['old_values'] || $log['new_values']): ?>
                        <div style="margin-top: 0.5rem;">
                            <button class="btn btn-outline btn-sm" style="width: 100%; font-size: 0.75rem;"
                                onclick="showDetails(<?php echo $log['id']; ?>)">
                                <i class="fas fa-eye"></i> View Changes
                            </button>
                            <div id="mobile-details-<?php echo $log['id']; ?>"
                                style="display: none; margin-top: 0.5rem; font-size: 0.75rem;">
                                <?php if ($log['old_values']): ?>
                                    <div style="margin-bottom: 0.5rem;">
                                        <strong style="color: var(--gray-600);">Old Values:</strong>
                                        <pre
                                            style="margin: 0.25rem 0; padding: 0.5rem; background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: 4px; font-size: 0.625rem; overflow-x: auto; white-space: pre-wrap;"><?php
                                            $old = json_decode($log['old_values'], true);
                                            echo $old ? htmlspecialchars(json_encode($old, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) : htmlspecialchars($log['old_values']);
                                            ?></pre>
                                    </div>
                                <?php endif; ?>
                                <?php if ($log['new_values']): ?>
                                    <div>
                                        <strong style="color: var(--gray-600);">New Values:</strong>
                                        <pre
                                            style="margin: 0.25rem 0; padding: 0.5rem; background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: 4px; font-size: 0.625rem; overflow-x: auto; white-space: pre-wrap;"><?php
                                            $new = json_decode($log['new_values'], true);
                                            echo $new ? htmlspecialchars(json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) : htmlspecialchars($log['new_values']);
                                            ?></pre>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
    <div class="d-flex justify-center gap-2" style="margin-top: 1rem;">
        <?php
        $queryParams = $_GET;
        unset($queryParams['page']);
        $baseUrl = 'audit_log.php?' . http_build_query($queryParams);
        ?>
        <?php if ($page > 1): ?>
            <a href="<?php echo $baseUrl . '&page=' . ($page - 1); ?>" class="btn btn-outline" style="font-size: 0.8125rem;">
                <i class="fas fa-chevron-left"></i> Prev
            </a>
        <?php endif; ?>

        <span class="d-flex align-center" style="font-size: 0.8125rem; color: var(--gray-500); padding: 0 0.5rem;">
            Page
            <?php echo $page; ?> of
            <?php echo $totalPages; ?>
        </span>

        <?php if ($page < $totalPages): ?>
            <a href="<?php echo $baseUrl . '&page=' . ($page + 1); ?>" class="btn btn-outline" style="font-size: 0.8125rem;">
                Next <i class="fas fa-chevron-right"></i>
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
$page_scripts = <<<'SCRIPT'
<script>
    function showDetails(id) {
        const row = document.getElementById('details-' + id);
        if (row) {
            row.style.display = row.style.display === 'none' ? '' : 'none';
        }
        const mobile = document.getElementById('mobile-details-' + id);
        if (mobile) {
            mobile.style.display = mobile.style.display === 'none' ? 'block' : 'none';
        }
    }
</script>
SCRIPT;
include __DIR__ . '/includes/footer.php';
?>