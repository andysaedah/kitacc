<?php
/**
 * KiTAcc - Submit Claim
 * Users submit expense claims with receipt upload
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();
requireRole(ROLE_USER);

$user = getCurrentUser();
$branchId = $user['branch_id'];
$page_title = 'Submit Claim - KiTAcc';

try {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT id, name FROM categories WHERE type = 'claim' AND is_active = 1 ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-between align-center mb-6">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--gray-800);">Submit Claim</h1>
        <p class="text-muted">Submit an expense claim with receipt</p>
    </div>
</div>

<div class="card" style="max-width: 700px;">
    <div class="card-body">
        <form id="claimForm" data-validate>
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
                <label class="form-label required">Receipt Image</label>
                <div class="upload-zone" id="claimReceiptZone">
                    <i class="fas fa-camera"></i>
                    <p><strong>Take a photo</strong> or upload receipt</p>
                    <p class="text-muted">JPG, PNG — auto-compressed to 80% quality</p>
                </div>
                <input type="file" id="claimCameraFile" accept="image/*" capture="environment" style="display: none;">
                <input type="file" id="claimGalleryFile" accept="image/*" style="display: none;">
                <div class="upload-preview" id="claimReceiptPreview"></div>
            </div>

            <!-- Mobile Upload Action Sheet -->
            <div class="upload-action-overlay" id="claimUploadActionSheet">
                <div class="upload-action-sheet">
                    <div class="upload-action-title">Upload Receipt</div>
                    <button type="button" class="upload-action-btn" id="claimCameraBtn">
                        <i class="fas fa-camera"></i>
                        <span><span class="action-label">Take Photo</span><span class="action-desc">Use your camera to
                                capture receipt</span></span>
                    </button>
                    <button type="button" class="upload-action-btn" id="claimGalleryBtn">
                        <i class="fas fa-images"></i>
                        <span><span class="action-label">Choose from Gallery</span><span class="action-desc">Select an
                                existing photo</span></span>
                    </button>
                    <button type="button" class="upload-action-cancel" id="claimCancelBtn">Cancel</button>
                </div>
            </div>

            <button type="button" class="btn btn-primary btn-lg w-full" id="claimSubmitBtn" onclick="submitClaim()">
                <i class="fas fa-paper-plane"></i> Submit Claim
            </button>
        </form>
    </div>
</div>

<?php
$page_scripts = <<<'SCRIPT'
<script>
    const claimUploadZone = document.getElementById('claimReceiptZone');
    const claimCameraInput = document.getElementById('claimCameraFile');
    const claimGalleryInput = document.getElementById('claimGalleryFile');
    const claimReceiptPreview = document.getElementById('claimReceiptPreview');
    const claimActionSheet = document.getElementById('claimUploadActionSheet');
    let claimCompressedFile = null;

    function isMobileDevice() {
        return /Android|iPhone|iPad|iPod|webOS|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
            || (navigator.maxTouchPoints > 1 && window.innerWidth <= 1024);
    }

    function openClaimActionSheet() { claimActionSheet.classList.add('active'); }
    function closeClaimActionSheet() { claimActionSheet.classList.remove('active'); }

    claimUploadZone.addEventListener('click', () => {
        if (isMobileDevice()) { openClaimActionSheet(); }
        else { claimGalleryInput.click(); }
    });
    claimUploadZone.addEventListener('dragover', (e) => { e.preventDefault(); claimUploadZone.classList.add('dragover'); });
    claimUploadZone.addEventListener('dragleave', () => claimUploadZone.classList.remove('dragover'));
    claimUploadZone.addEventListener('drop', (e) => {
        e.preventDefault(); claimUploadZone.classList.remove('dragover');
        if (e.dataTransfer.files.length) handleClaimFile(e.dataTransfer.files[0]);
    });

    document.getElementById('claimCameraBtn').addEventListener('click', () => { closeClaimActionSheet(); claimCameraInput.click(); });
    document.getElementById('claimGalleryBtn').addEventListener('click', () => { closeClaimActionSheet(); claimGalleryInput.click(); });
    document.getElementById('claimCancelBtn').addEventListener('click', closeClaimActionSheet);
    claimActionSheet.addEventListener('click', (e) => { if (e.target === claimActionSheet) closeClaimActionSheet(); });

    claimCameraInput.addEventListener('change', (e) => { if (e.target.files.length) handleClaimFile(e.target.files[0]); });
    claimGalleryInput.addEventListener('change', (e) => { if (e.target.files.length) handleClaimFile(e.target.files[0]); });

    async function handleClaimFile(file) {
        if (!file.type.startsWith('image/')) { KiTAcc.toast('Please select an image file.', 'error'); return; }
        claimCompressedFile = await KiTAcc.compressImage(file);
        const reader = new FileReader();
        reader.onload = (e) => { claimReceiptPreview.innerHTML = `<img src="${e.target.result}" alt="Receipt">`; };
        reader.readAsDataURL(claimCompressedFile);
    }

    function submitClaim() {
        const form = document.getElementById('claimForm');
        if (!KiTAcc.validateForm(form)) return;
        if (!claimCompressedFile) {
            KiTAcc.toast('Please attach a receipt image.', 'warning');
            return;
        }

        const formData = new FormData(form);
        formData.append('receipt', claimCompressedFile);
        formData.append('action', 'submit');

        const btn = document.getElementById('claimSubmitBtn');
        btn.disabled = true;

        KiTAcc.postForm('api/claims.php', formData, function(res) {
            if (res.success) {
                KiTAcc.toast('Claim submitted successfully!', 'success');
                setTimeout(() => window.location.href = 'claims.php', 1000);
            } else {
                KiTAcc.toast(res.message || 'Error submitting claim.', 'error');
                btn.disabled = false;
            }
        }, function() {
            KiTAcc.toast('Connection error.', 'error');
            btn.disabled = false;
        });
    }
</script>
SCRIPT;

include __DIR__ . '/includes/footer.php';
?>