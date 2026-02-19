<?php
/**
 * KiTAcc - My Claims
 * View claim history and status
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();
requireRole(ROLE_USER);

$user = getCurrentUser();
$page_title = 'My Claims - KiTAcc';

try {
    $pdo = db();

    // Count total claims
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM claims WHERE submitted_by = ?");
    $countStmt->execute([$user['id']]);
    $totalRecords = (int) $countStmt->fetchColumn();

    $currentPage = max(1, intval($_GET['page'] ?? 1));
    $pager = paginate($totalRecords, $currentPage, 25);

    $stmt = $pdo->prepare("SELECT cl.*, c.name AS category_name, u2.name AS approved_by_name
                           FROM claims cl
                           LEFT JOIN categories c ON cl.category_id = c.id
                           LEFT JOIN users u2 ON cl.approved_by = u2.id
                           WHERE cl.submitted_by = ?
                           ORDER BY cl.created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute([$user['id'], $pager['per_page'], $pager['offset']]);
    $claims = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT id, name FROM categories WHERE type = 'claim' AND is_active = 1 ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    $claims = [];
    $categories = [];
}

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-between align-center mb-6">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--gray-800);">My Claims</h1>
        <p class="text-muted">Track your claim submissions</p>
    </div>
    <a href="claim_submit.php" class="btn btn-primary"><i class="fas fa-plus"></i> New Claim</a>
</div>

<div class="card table-responsive-cards">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Category</th>
                    <th class="text-right">Amount</th>
                    <th>Status</th>
                    <th>Reviewed By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($claims)): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state"><i class="fas fa-clipboard-list"></i>
                                <h3>No Claims</h3>
                                <p>You haven't submitted any claims yet.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($claims as $cl): ?>
                        <tr>
                            <td>
                                <?php echo formatDate($cl['created_at']); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($cl['description'] ?: $cl['title']); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($cl['category_name'] ?? '—'); ?>
                            </td>
                            <td class="text-right font-semibold">
                                <?php echo formatCurrency($cl['amount']); ?>
                            </td>
                            <td>
                                <?php
                                $statusBadge = match ($cl['status']) {
                                    'approved' => 'badge-success',
                                    'rejected' => 'badge-danger',
                                    default => 'badge-warning'
                                };
                                ?>
                                <?php
                                $statusLabel = match ($cl['status']) {
                                    'approved' => 'Approved / Paid',
                                    'rejected' => 'Rejected',
                                    default => 'Pending'
                                };
                                ?>
                                <span class="badge <?php echo $statusBadge; ?>">
                                    <?php echo $statusLabel; ?>
                                </span>
                            </td>
                            <td class="text-muted">
                                <?php echo htmlspecialchars($cl['approved_by_name'] ?? '—'); ?>
                            </td>
                            <td>
                                <?php if ($cl['status'] === 'pending'): ?>
                                    <div class="table-actions">
                                        <button class="btn btn-icon btn-ghost" title="Edit"
                                            onclick="editClaim(<?php echo $cl['id']; ?>)"><i class="fas fa-edit"></i></button>
                                        <button class="btn btn-icon btn-ghost text-danger" title="Delete"
                                            onclick="deleteClaim(<?php echo $cl['id']; ?>)"><i class="fas fa-trash"></i></button>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="mobile-card">
        <?php if (empty($claims)): ?>
            <div class="empty-state"><i class="fas fa-clipboard-list"></i>
                <p>No claims yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($claims as $cl): ?>
                <div class="mobile-card-item">
                    <div class="mobile-card-header">
                        <span class="font-semibold">
                            <?php echo htmlspecialchars($cl['description'] ?: $cl['title']); ?>
                        </span>
                        <?php $statusBadge = match ($cl['status']) { 'approved' => 'badge-success', 'rejected' => 'badge-danger', default => 'badge-warning'}; ?>
                        <?php $statusLabel = match ($cl['status']) { 'approved' => 'Approved / Paid', 'rejected' => 'Rejected', default => 'Pending'}; ?>
                        <span class="badge <?php echo $statusBadge; ?>">
                            <?php echo $statusLabel; ?>
                        </span>
                    </div>
                    <div class="mobile-card-row"><span class="mobile-card-label">Date</span><span class="mobile-card-value">
                            <?php echo formatDate($cl['created_at']); ?>
                        </span></div>
                    <div class="mobile-card-row"><span class="mobile-card-label">Amount</span><span
                            class="mobile-card-value font-semibold">
                            <?php echo formatCurrency($cl['amount']); ?>
                        </span></div>
                    <?php if ($cl['rejection_reason']): ?>
                        <div class="mobile-card-row"><span class="mobile-card-label">Reason</span><span
                                class="mobile-card-value text-danger">
                                <?php echo htmlspecialchars($cl['rejection_reason']); ?>
                            </span></div>
                    <?php endif; ?>
                    <?php if ($cl['status'] === 'pending'): ?>
                        <div class="mobile-card-actions">
                            <button class="btn btn-sm btn-ghost" onclick="editClaim(<?php echo $cl['id']; ?>)"><i
                                    class="fas fa-edit"></i> Edit</button>
                            <button class="btn btn-sm btn-ghost text-danger" onclick="deleteClaim(<?php echo $cl['id']; ?>)"><i
                                    class="fas fa-trash"></i> Delete</button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php renderPagination($pager, 'claims.php', $_GET); ?>
</div>

<!-- Edit Claim Modal -->
<div class="modal-overlay" id="editClaimModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Edit Claim</h3>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="editClaimForm" data-validate>
                <input type="hidden" id="editClaimId" name="id" value="">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label required">Receipt Date</label>
                        <input type="date" name="receipt_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-control">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>">
                                    <?php echo htmlspecialchars($cat['name']); ?>
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
                    <textarea name="description" class="form-control" rows="3"
                        placeholder="Provide details about this expense..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Receipt Image</label>
                    <div class="upload-zone" id="editClaimReceiptZone">
                        <i class="fas fa-camera"></i>
                        <p><strong>Take a photo</strong> or upload receipt</p>
                        <p class="text-muted">JPG, PNG — auto-compressed to 80% quality</p>
                    </div>
                    <input type="file" id="editClaimCameraFile" accept="image/*" capture="environment"
                        style="display: none;">
                    <input type="file" id="editClaimGalleryFile" accept="image/*" style="display: none;">
                    <div class="upload-preview" id="editClaimReceiptPreview"></div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="KiTAcc.closeModal('editClaimModal')">Cancel</button>
            <button class="btn btn-primary" id="editClaimSubmitBtn" onclick="updateClaim()">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
    </div>
</div>

<!-- Mobile Upload Action Sheet for Edit -->
<div class="upload-action-overlay" id="editClaimUploadActionSheet">
    <div class="upload-action-sheet">
        <div class="upload-action-title">Replace Receipt</div>
        <button type="button" class="upload-action-btn" id="editClaimCameraBtn">
            <i class="fas fa-camera"></i>
            <span><span class="action-label">Take Photo</span><span class="action-desc">Use your camera to capture
                    receipt</span></span>
        </button>
        <button type="button" class="upload-action-btn" id="editClaimGalleryBtn">
            <i class="fas fa-images"></i>
            <span><span class="action-label">Choose from Gallery</span><span class="action-desc">Select an existing
                    photo</span></span>
        </button>
        <button type="button" class="upload-action-cancel" id="editClaimCancelBtn">Cancel</button>
    </div>
</div>

<?php
$page_scripts = <<<'SCRIPT'
<script>
    const editUploadZone = document.getElementById('editClaimReceiptZone');
    const editCameraInput = document.getElementById('editClaimCameraFile');
    const editGalleryInput = document.getElementById('editClaimGalleryFile');
    const editReceiptPreview = document.getElementById('editClaimReceiptPreview');
    const editActionSheet = document.getElementById('editClaimUploadActionSheet');
    let editCompressedFile = null;

    function isMobileDevice() {
        return /Android|iPhone|iPad|iPod|webOS|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
            || (navigator.maxTouchPoints > 1 && window.innerWidth <= 1024);
    }

    function openEditActionSheet() { editActionSheet.classList.add('active'); }
    function closeEditActionSheet() { editActionSheet.classList.remove('active'); }

    editUploadZone.addEventListener('click', () => {
        if (isMobileDevice()) { openEditActionSheet(); }
        else { editGalleryInput.click(); }
    });
    editUploadZone.addEventListener('dragover', (e) => { e.preventDefault(); editUploadZone.classList.add('dragover'); });
    editUploadZone.addEventListener('dragleave', () => editUploadZone.classList.remove('dragover'));
    editUploadZone.addEventListener('drop', (e) => {
        e.preventDefault(); editUploadZone.classList.remove('dragover');
        if (e.dataTransfer.files.length) handleEditReceiptFile(e.dataTransfer.files[0]);
    });

    document.getElementById('editClaimCameraBtn').addEventListener('click', () => { closeEditActionSheet(); editCameraInput.click(); });
    document.getElementById('editClaimGalleryBtn').addEventListener('click', () => { closeEditActionSheet(); editGalleryInput.click(); });
    document.getElementById('editClaimCancelBtn').addEventListener('click', closeEditActionSheet);
    editActionSheet.addEventListener('click', (e) => { if (e.target === editActionSheet) closeEditActionSheet(); });

    editCameraInput.addEventListener('change', (e) => { if (e.target.files.length) handleEditReceiptFile(e.target.files[0]); });
    editGalleryInput.addEventListener('change', (e) => { if (e.target.files.length) handleEditReceiptFile(e.target.files[0]); });

    async function handleEditReceiptFile(file) {
        if (!file.type.startsWith('image/')) { KiTAcc.toast('Please select an image file.', 'error'); return; }
        editCompressedFile = await KiTAcc.compressImage(file);
        const reader = new FileReader();
        reader.onload = (e) => { editReceiptPreview.innerHTML = `<img src="${e.target.result}" alt="Receipt">`; };
        reader.readAsDataURL(editCompressedFile);
    }

    function editClaim(id) {
        editCompressedFile = null;
        KiTAcc.get('api/claims.php?action=get&id=' + id, function(res) {
            if (res.success) {
                const d = res.data;
                document.getElementById('editClaimId').value = d.id;
                const form = document.getElementById('editClaimForm');
                form.querySelector('[name="receipt_date"]').value = d.receipt_date || '';
                form.querySelector('[name="category_id"]').value = d.category_id || '';
                form.querySelector('[name="amount"]').value = d.amount;
                form.querySelector('[name="description"]').value = d.description || '';

                // Show existing receipt
                if (d.receipt_path) {
                    editReceiptPreview.innerHTML = `<img src="${d.receipt_path}" alt="Receipt">`;
                } else {
                    editReceiptPreview.innerHTML = '';
                }

                KiTAcc.openModal('editClaimModal');
            } else {
                KiTAcc.toast(res.message || 'Error loading claim.', 'error');
            }
        });
    }

    function updateClaim() {
        const form = document.getElementById('editClaimForm');
        if (!KiTAcc.validateForm(form)) return;
        const formData = new FormData(form);
        formData.append('action', 'update');
        if (editCompressedFile) formData.append('receipt', editCompressedFile);
        const btn = document.getElementById('editClaimSubmitBtn');
        btn.disabled = true;
        KiTAcc.postForm('api/claims.php', formData, function(res) {
            if (res.success) {
                KiTAcc.toast('Claim updated!', 'success');
                KiTAcc.closeModal('editClaimModal');
                setTimeout(() => location.reload(), 800);
            } else {
                KiTAcc.toast(res.message || 'Error updating claim.', 'error');
            }
            btn.disabled = false;
        }, function() {
            KiTAcc.toast('Connection error.', 'error');
            btn.disabled = false;
        });
    }

    function deleteClaim(id) {
        if (!KiTAcc.confirm('Are you sure you want to delete this pending claim?')) return;
        KiTAcc.post('api/claims.php', { action: 'delete', id: id }, function(res) {
            if (res.success) { KiTAcc.toast('Claim deleted.', 'success'); setTimeout(() => location.reload(), 600); }
            else KiTAcc.toast(res.message || 'Error deleting claim.', 'error');
        });
    }
</script>
SCRIPT;

include __DIR__ . '/includes/footer.php'; ?>