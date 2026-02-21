<?php
/**
 * KiTAcc - Review Claims
 * Branch Finance / Admin Finance review pending claims
 * Tabs: Pending | Approved/Paid | Rejected
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();
requireRole(ROLE_BRANCH_FINANCE);

$user = getCurrentUser();
$branchId = getActiveBranchId();
$page_title = 'Review Claims - KiTAcc';

// Active tab from URL (defaults to pending)
$activeStatus = $_GET['status'] ?? 'pending';
if (!in_array($activeStatus, ['pending', 'approved', 'rejected'])) {
    $activeStatus = 'pending';
}

try {
    $pdo = db();

    // Branch filter helper
    $branchWhere = '';
    $branchParams = [];
    if ($branchId !== null) {
        $branchWhere = ' AND cl.branch_id = ?';
        $branchParams = [$branchId];
    }

    // Count per status (single query, no performance penalty)
    $countSql = "SELECT 
        SUM(cl.status = 'pending') AS pending_count,
        SUM(cl.status = 'approved') AS approved_count,
        SUM(cl.status = 'rejected') AS rejected_count
        FROM claims cl WHERE 1=1" . $branchWhere;
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($branchParams);
    $counts = $countStmt->fetch();
    $pendingCount = (int) ($counts['pending_count'] ?? 0);
    $approvedCount = (int) ($counts['approved_count'] ?? 0);
    $rejectedCount = (int) ($counts['rejected_count'] ?? 0);

    // Fetch claims for active tab only
    $totalRecords = match ($activeStatus) {
        'approved' => $approvedCount,
        'rejected' => $rejectedCount,
        default => $pendingCount,
    };

    $currentPage = max(1, intval($_GET['page'] ?? 1));
    $pager = paginate($totalRecords, $currentPage, 25);

    $sql = "SELECT cl.*, c.name AS category_name, u.name AS submitted_by_name
            FROM claims cl
            LEFT JOIN categories c ON cl.category_id = c.id
            LEFT JOIN users u ON cl.submitted_by = u.id
            WHERE cl.status = ?" . $branchWhere . "
            ORDER BY cl.created_at DESC LIMIT ? OFFSET ?";
    $params = array_merge([$activeStatus], $branchParams, [$pager['per_page'], $pager['offset']]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $claims = $stmt->fetchAll();

    // Accounts & funds for approval (only needed on pending tab)
    $accounts = [];
    $funds = [];
    if ($activeStatus === 'pending') {
        $sql = "SELECT id, name FROM accounts WHERE is_active = 1";
        $params = [];
        if ($branchId !== null) {
            $sql .= " AND branch_id = ?";
            $params[] = $branchId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $accounts = $stmt->fetchAll();

        if (isFundMode()) {
            $fsql = "SELECT id, name FROM funds WHERE is_active = 1 AND name != 'General Fund'";
            $fparams = [];
            if ($branchId !== null) {
                $fsql .= " AND branch_id = ?";
                $fparams[] = $branchId;
            }
            $fsql .= " ORDER BY name";
            $stmt = $pdo->prepare($fsql);
            $stmt->execute($fparams);
            $funds = $stmt->fetchAll();
        }
    }

} catch (Exception $e) {
    $claims = [];
    $accounts = [];
    $funds = [];
    $pendingCount = $approvedCount = $rejectedCount = 0;
}

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-between align-center mb-6">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--gray-800);">Review Claims</h1>
        <p class="text-muted">Approve or reject submitted claims</p>
    </div>
</div>

<!-- Status Tabs -->
<div style="margin-bottom: 1.5rem;">
    <div class="tabs">
        <a href="claim_review.php?status=pending" class="tab <?php echo $activeStatus === 'pending' ? 'active' : ''; ?>" style="text-decoration: none;">
            <i class="fas fa-hourglass-half" style="margin-right: 0.375rem;"></i>Pending
            <?php if ($pendingCount > 0): ?>
                <span class="badge badge-warning" style="margin-left: 0.375rem; font-size: 0.7rem;"><?php echo $pendingCount; ?></span>
            <?php endif; ?>
        </a>
        <a href="claim_review.php?status=approved" class="tab <?php echo $activeStatus === 'approved' ? 'active' : ''; ?>" style="text-decoration: none;">
            <i class="fas fa-check-circle" style="margin-right: 0.375rem;"></i>Approved / Paid
            <?php if ($approvedCount > 0): ?>
                <span class="badge badge-success" style="margin-left: 0.375rem; font-size: 0.7rem;"><?php echo $approvedCount; ?></span>
            <?php endif; ?>
        </a>
        <a href="claim_review.php?status=rejected" class="tab <?php echo $activeStatus === 'rejected' ? 'active' : ''; ?>" style="text-decoration: none;">
            <i class="fas fa-times-circle" style="margin-right: 0.375rem;"></i>Rejected
            <?php if ($rejectedCount > 0): ?>
                <span class="badge badge-danger" style="margin-left: 0.375rem; font-size: 0.7rem;"><?php echo $rejectedCount; ?></span>
            <?php endif; ?>
        </a>
    </div>
</div>

<!-- Claims List -->
<?php if (empty($claims)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <?php if ($activeStatus === 'pending'): ?>
                    <i class="fas fa-clipboard-check"></i>
                    <h3>No Pending Claims</h3>
                    <p>There are no claims waiting for review.</p>
                <?php elseif ($activeStatus === 'approved'): ?>
                    <i class="fas fa-check-circle"></i>
                    <h3>No Approved Claims</h3>
                    <p>No claims have been approved yet.</p>
                <?php else: ?>
                    <i class="fas fa-times-circle"></i>
                    <h3>No Rejected Claims</h3>
                    <p>No claims have been rejected.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($claims as $cl): ?>
        <div class="card mb-4" id="claim-<?php echo $cl['id']; ?>">
            <div class="card-header">
                <div>
                    <h3 class="card-title">
                        <?php echo htmlspecialchars($cl['description'] ?: $cl['title']); ?>
                    </h3>
                    <span class="text-muted" style="font-size: 0.75rem;">
                        By
                        <?php echo htmlspecialchars($cl['submitted_by_name']); ?> —
                        <?php echo formatDateTime($cl['created_at']); ?>
                    </span>
                </div>
                <?php
                $statusLabel = match ($cl['status']) { 'approved' => 'Approved / Paid', 'rejected' => 'Rejected', default => 'Pending'};
                $statusBadge = match ($cl['status']) { 'approved' => 'badge-success', 'rejected' => 'badge-danger', default => 'badge-warning'};
                ?>
                <span class="badge <?php echo $statusBadge; ?>">
                    <?php echo $statusLabel; ?>
                </span>
            </div>
            <div class="card-body">
                <div class="claim-details">
                    <div class="mobile-card-row">
                        <span class="mobile-card-label">Amount</span>
                        <span class="mobile-card-value font-semibold" style="font-size: 1.125rem; color: var(--primary);">
                            <?php echo formatCurrency($cl['amount']); ?>
                        </span>
                    </div>
                    <?php if (!empty($cl['receipt_date'])): ?>
                        <div class="mobile-card-row">
                            <span class="mobile-card-label">Receipt Date</span>
                            <span class="mobile-card-value">
                                <?php echo formatDate($cl['receipt_date']); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    <div class="mobile-card-row">
                        <span class="mobile-card-label">Category</span>
                        <span class="mobile-card-value">
                            <?php echo htmlspecialchars($cl['category_name'] ?? '—'); ?>
                        </span>
                    </div>
                    <?php if ($cl['description']): ?>
                        <div class="mobile-card-row">
                            <span class="mobile-card-label">Description</span>
                            <span class="mobile-card-value">
                                <?php echo nl2br(htmlspecialchars($cl['description'])); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    <div class="mobile-card-row">
                        <span class="mobile-card-label">Receipt</span>
                        <span class="mobile-card-value">
                            <?php if ($cl['receipt_path']): ?>
                                <a href="javascript:void(0)" class="receipt-link"
                                    onclick="openReceiptModal('<?php echo htmlspecialchars($cl['receipt_path']); ?>')">
                                    <i class="fas fa-file-image"></i> View Receipt
                                </a>
                            <?php else: ?>
                                <span class="text-muted"><i class="fas fa-minus-circle"></i> No receipt</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if ($cl['status'] === 'rejected' && !empty($cl['rejection_reason'])): ?>
                        <div class="mobile-card-row"
                            style="background: var(--danger-light); margin: 0.5rem -1.5rem -0.5rem; padding: 0.75rem 1.5rem; border-radius: 0 0 var(--radius-lg) var(--radius-lg);">
                            <span class="mobile-card-label" style="color: var(--danger);"><i class="fas fa-comment-slash"
                                    style="margin-right: 0.25rem;"></i>Rejection Reason</span>
                            <span class="mobile-card-value" style="color: var(--danger);">
                                <?php echo htmlspecialchars($cl['rejection_reason']); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($cl['status'] === 'pending'): ?>
                <div class="card-footer">
                    <div class="d-flex gap-3 flex-wrap align-center">
                        <select id="account-<?php echo $cl['id']; ?>" class="form-control" style="max-width: 200px;">
                            <option value="">Pay from account...</option>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?php echo $acc['id']; ?>">
                                    <?php echo htmlspecialchars($acc['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isFundMode()): ?>
                        <select id="fund-<?php echo $cl['id']; ?>" class="form-control" style="max-width: 200px;">
                            <option value="">General Fund</option>
                            <?php foreach ($funds as $fund): ?>
                                <option value="<?php echo $fund['id']; ?>">
                                    <?php echo htmlspecialchars($fund['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                        <button class="btn btn-success btn-sm" onclick="approveClaim(<?php echo $cl['id']; ?>)"><i
                                class="fas fa-check"></i> Approve</button>
                        <button class="btn btn-danger btn-sm" onclick="rejectClaim(<?php echo $cl['id']; ?>)"><i
                                class="fas fa-times"></i> Reject</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php renderPagination($pager, 'claim_review.php', $_GET); ?>

<!-- Receipt Modal -->
<div class="modal-overlay" id="receiptModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-receipt"
                    style="margin-right: 0.5rem; color: var(--primary);"></i>Receipt</h3>
            <button class="modal-close" onclick="closeReceiptModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="text-align: center; padding: 1rem;">
            <img id="receiptModalImg" src="" alt="Receipt"
                style="max-width: 100%; max-height: 70vh; border-radius: var(--radius); object-fit: contain;">
        </div>
        <div class="modal-footer">
            <a id="receiptDownloadLink" href="" target="_blank" class="btn btn-primary btn-sm"><i
                    class="fas fa-external-link-alt"></i> Open Full Size</a>
            <button class="btn btn-outline btn-sm" onclick="closeReceiptModal()">Close</button>
        </div>
    </div>
</div>

<?php
$page_scripts = <<<'SCRIPT'
<script>
    function openReceiptModal(src) {
        document.getElementById('receiptModalImg').src = src;
        document.getElementById('receiptDownloadLink').href = src;
        document.getElementById('receiptModal').classList.add('active');
    }

    function closeReceiptModal() {
        document.getElementById('receiptModal').classList.remove('active');
        document.getElementById('receiptModalImg').src = '';
    }

    // Close modal on overlay click
    document.getElementById('receiptModal').addEventListener('click', function(e) {
        if (e.target === this) closeReceiptModal();
    });

    function approveClaim(id) {
        const accountId = document.getElementById('account-' + id).value;
        if (!accountId) { KiTAcc.toast('Please select an account to pay from.', 'warning'); return; }
        if (!KiTAcc.confirm('Approve this claim? An expense entry will be created automatically.')) return;

        var data = { action: 'approve', id: id, account_id: accountId };
        var fundEl = document.getElementById('fund-' + id);
        if (fundEl && fundEl.value) { data.fund_id = fundEl.value; }

        KiTAcc.post('api/claims.php', data, function(res) {
            if (res.success) { KiTAcc.toast('Claim approved!', 'success'); setTimeout(() => location.reload(), 800); }
            else KiTAcc.toast(res.message || 'Error approving claim.', 'error');
        });
    }

    function rejectClaim(id) {
        const reason = prompt('Please provide a rejection reason (required):');
        if (reason === null) return;
        if (!reason.trim()) { KiTAcc.toast('Rejection reason is required.', 'warning'); return; }

        KiTAcc.post('api/claims.php', { action: 'reject', id: id, rejection_reason: reason }, function(res) {
            if (res.success) { KiTAcc.toast('Claim rejected.', 'info'); setTimeout(() => location.reload(), 800); }
            else KiTAcc.toast(res.message || 'Error rejecting claim.', 'error');
        });
    }
</script>
SCRIPT;

include __DIR__ . '/includes/footer.php';
?>