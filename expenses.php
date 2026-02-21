<?php
/**
 * KiTAcc - Expense Management
 * Record and manage expense entries
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();
requireRole(ROLE_BRANCH_FINANCE);

$user = getCurrentUser();
$branchId = getActiveBranchId() ?? $user['branch_id'];
$page_title = 'Expenses - KiTAcc';

// Fetch data
try {
    $pdo = db();

    $sql = "SELECT id, name FROM accounts WHERE is_active = 1";
    $params = [];
    if ($branchId !== null) {
        $sql .= " AND branch_id = ?";
        $params[] = $branchId;
    }
    $sql .= " ORDER BY name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $accounts = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT id, name FROM categories WHERE type = 'expense' AND is_active = 1 ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll();

    $funds = [];
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

    $sql = "SELECT t.*, c.name AS category_name, a.name AS account_name, f.name AS fund_name, u.name AS created_by_name
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
            LEFT JOIN accounts a ON t.account_id = a.id
            LEFT JOIN funds f ON t.fund_id = f.id
            LEFT JOIN users u ON t.created_by = u.id
            WHERE t.type = 'expense'";
    $params = [];
    if ($branchId !== null) {
        $sql .= " AND t.branch_id = ?";
        $params[] = $branchId;
    }
    // Count total expense records
    $countSql = "SELECT COUNT(*) FROM transactions t WHERE t.type = 'expense'";
    $countParams = [];
    if ($branchId !== null) {
        $countSql .= " AND t.branch_id = ?";
        $countParams[] = $branchId;
    }
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $totalRecords = (int) $countStmt->fetchColumn();

    $currentPage = max(1, intval($_GET['page'] ?? 1));
    $pager = paginate($totalRecords, $currentPage, 25);

    $sql .= " ORDER BY t.date DESC, t.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $pager['per_page'];
    $params[] = $pager['offset'];
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $expenseList = $stmt->fetchAll();

} catch (Exception $e) {
    $accounts = [];
    $categories = [];
    $funds = [];
    $expenseList = [];
    $pager = paginate(0, 1, 25);
}

include __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-between align-center mb-6">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--gray-800);">Expenses</h1>
        <p class="text-muted">Record and manage expense entries</p>
    </div>
    <button class="btn btn-primary" onclick="KiTAcc.openModal('addExpenseModal')">
        <i class="fas fa-plus"></i> Add Expense
    </button>
</div>

<!-- Expense Table -->
<div class="card table-responsive-cards">
    <div class="card-header">
        <h3 class="card-title">Expense Records</h3>
    </div>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Category</th>
                    <th>Account</th>
                    <?php if (isFundMode()): ?>
                        <th>Fund</th><?php endif; ?>
                    <th>Ref #</th>
                    <th class="text-right">Amount</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expenseList)): ?>
                    <tr>
                        <td colspan="<?php echo isFundMode() ? 8 : 7; ?>">
                            <div class="empty-state"><i class="fas fa-arrow-up"></i>
                                <h3>No Expenses Recorded</h3>
                                <p>Start by adding your first expense entry.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($expenseList as $exp): ?>
                        <tr>
                            <td><?php echo formatDate($exp['date']); ?></td>
                            <td class="truncate" style="max-width: 180px;">
                                <?php echo htmlspecialchars($exp['description'] ?? '—'); ?>
                            </td>
                            <td><span
                                    class="badge badge-danger"><?php echo htmlspecialchars($exp['category_name'] ?? '—'); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($exp['account_name']); ?></td>
                            <?php if (isFundMode()): ?>
                                <td><?php echo htmlspecialchars($exp['fund_name'] ?? 'General Fund'); ?></td><?php endif; ?>
                            <td><?php echo htmlspecialchars($exp['reference_number'] ?? '—'); ?></td>
                            <td class="text-right font-semibold text-danger">-<?php echo formatCurrency($exp['amount']); ?></td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn btn-icon btn-ghost" title="View Receipt"
                                        onclick="viewReceipt(<?php echo $exp['id']; ?>)"><i class="fas fa-image"></i></button>
                                    <button class="btn btn-icon btn-ghost" title="Edit"
                                        onclick="editExpense(<?php echo $exp['id']; ?>)"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-icon btn-ghost text-danger" title="Delete"
                                        onclick="deleteExpense(<?php echo $exp['id']; ?>)"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Cards -->
    <div class="mobile-card">
        <?php if (empty($expenseList)): ?>
            <div class="empty-state"><i class="fas fa-arrow-up"></i>
                <p>No expenses recorded.</p>
            </div>
        <?php else: ?>
            <?php foreach ($expenseList as $exp): ?>
                <div class="mobile-card-item">
                    <div class="mobile-card-header">
                        <span class="font-semibold"><?php echo htmlspecialchars($exp['description'] ?? 'Expense'); ?></span>
                        <span class="font-semibold text-danger">-<?php echo formatCurrency($exp['amount']); ?></span>
                    </div>
                    <div class="mobile-card-row"><span class="mobile-card-label">Date</span><span
                            class="mobile-card-value"><?php echo formatDate($exp['date']); ?></span></div>
                    <div class="mobile-card-row"><span class="mobile-card-label">Category</span><span
                            class="mobile-card-value"><?php echo htmlspecialchars($exp['category_name'] ?? '—'); ?></span></div>
                    <div class="mobile-card-row"><span class="mobile-card-label">Account</span><span
                            class="mobile-card-value"><?php echo htmlspecialchars($exp['account_name']); ?></span></div>
                    <div class="mobile-card-actions">
                        <button class="btn btn-sm btn-ghost" onclick="editExpense(<?php echo $exp['id']; ?>)"><i
                                class="fas fa-edit"></i> Edit</button>
                        <button class="btn btn-sm btn-ghost text-danger" onclick="deleteExpense(<?php echo $exp['id']; ?>)"><i
                                class="fas fa-trash"></i> Delete</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php renderPagination($pager, 'expenses.php', $_GET); ?>
</div>

<!-- Add Expense Modal -->
<div class="modal-overlay" id="addExpenseModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="expenseModalTitle">Add Expense</h3>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="expenseForm" data-validate>
                <input type="hidden" id="expenseId" name="id" value="">

                <div class="form-group">
                    <label class="form-label required">Date</label>
                    <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label required">Amount (RM)</label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="0.01" placeholder="0.00"
                        required>
                </div>

                <div class="form-group">
                    <label class="form-label required">Account</label>
                    <select name="account_id" class="form-control" required>
                        <option value="">Select Account</option>
                        <?php foreach ($accounts as $acc): ?>
                            <option value="<?php echo $acc['id']; ?>"><?php echo htmlspecialchars($acc['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label required">Category</label>
                    <select name="category_id" class="form-control" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (isFundMode()): ?>
                    <div class="form-group">
                        <label class="form-label">Fund</label>
                        <select name="fund_id" class="form-control">
                            <option value="">General Fund (Unallocated)</option>
                            <?php foreach ($funds as $fund): ?>
                                <option value="<?php echo $fund['id']; ?>"><?php echo htmlspecialchars($fund['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2"
                        placeholder="e.g. Electricity bill"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Reference Number</label>
                    <input type="text" name="reference_number" class="form-control" placeholder="e.g. INV-001">
                </div>

                <div class="form-group">
                    <label class="form-label">Receipt Image</label>
                    <div class="upload-zone" id="expenseReceiptZone">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Click or drag to upload receipt</p>
                        <p class="text-muted">JPG, PNG — max 5MB (auto-compressed)</p>
                    </div>
                    <input type="file" id="expenseCameraFile" accept="image/*" capture="environment"
                        style="display: none;">
                    <input type="file" id="expenseGalleryFile" accept="image/*" style="display: none;">
                    <div class="upload-preview" id="expenseReceiptPreview"></div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="KiTAcc.closeModal('addExpenseModal')">Cancel</button>
            <button class="btn btn-primary" id="expenseSubmitBtn" onclick="submitExpense()">
                <i class="fas fa-save"></i> Save Expense
            </button>
        </div>
    </div>
</div>

<!-- Mobile Upload Action Sheet -->
<div class="upload-action-overlay" id="expUploadActionSheet">
    <div class="upload-action-sheet">
        <div class="upload-action-title">Upload Receipt</div>
        <button type="button" class="upload-action-btn" id="expCameraBtn">
            <i class="fas fa-camera"></i>
            <span><span class="action-label">Take Photo</span><span class="action-desc">Use your camera to capture
                    receipt</span></span>
        </button>
        <button type="button" class="upload-action-btn" id="expGalleryBtn">
            <i class="fas fa-images"></i>
            <span><span class="action-label">Choose from Gallery</span><span class="action-desc">Select an existing
                    photo</span></span>
        </button>
        <button type="button" class="upload-action-cancel" id="expCancelBtn">Cancel</button>
    </div>
</div>

<?php
$page_scripts = <<<'SCRIPT'
<script>
    const expUploadZone = document.getElementById('expenseReceiptZone');
    const expCameraInput = document.getElementById('expenseCameraFile');
    const expGalleryInput = document.getElementById('expenseGalleryFile');
    const expReceiptPreview = document.getElementById('expenseReceiptPreview');
    const expActionSheet = document.getElementById('expUploadActionSheet');
    let expCompressedFile = null;

    function isMobileDevice() {
        return /Android|iPhone|iPad|iPod|webOS|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
            || (navigator.maxTouchPoints > 1 && window.innerWidth <= 1024);
    }

    function openExpActionSheet() { expActionSheet.classList.add('active'); }
    function closeExpActionSheet() { expActionSheet.classList.remove('active'); }

    if (expUploadZone) {
        expUploadZone.addEventListener('click', () => {
            if (isMobileDevice()) { openExpActionSheet(); }
            else { expGalleryInput.click(); }
        });
        expUploadZone.addEventListener('dragover', (e) => { e.preventDefault(); expUploadZone.classList.add('dragover'); });
        expUploadZone.addEventListener('dragleave', () => expUploadZone.classList.remove('dragover'));
        expUploadZone.addEventListener('drop', (e) => {
            e.preventDefault(); expUploadZone.classList.remove('dragover');
            if (e.dataTransfer.files.length) handleExpReceiptFile(e.dataTransfer.files[0]);
        });
    }

    document.getElementById('expCameraBtn').addEventListener('click', () => { closeExpActionSheet(); expCameraInput.click(); });
    document.getElementById('expGalleryBtn').addEventListener('click', () => { closeExpActionSheet(); expGalleryInput.click(); });
    document.getElementById('expCancelBtn').addEventListener('click', closeExpActionSheet);
    expActionSheet.addEventListener('click', (e) => { if (e.target === expActionSheet) closeExpActionSheet(); });

    expCameraInput.addEventListener('change', (e) => { if (e.target.files.length) handleExpReceiptFile(e.target.files[0]); });
    expGalleryInput.addEventListener('change', (e) => { if (e.target.files.length) handleExpReceiptFile(e.target.files[0]); });

    async function handleExpReceiptFile(file) {
        if (!file.type.startsWith('image/')) { KiTAcc.toast('Please select an image file.', 'error'); return; }
        expCompressedFile = await KiTAcc.compressImage(file);
        const reader = new FileReader();
        reader.onload = (e) => { expReceiptPreview.innerHTML = `<img src="${e.target.result}" alt="Receipt">`; };
        reader.readAsDataURL(expCompressedFile);
    }

    function submitExpense() {
        const form = document.getElementById('expenseForm');
        if (!KiTAcc.validateForm(form)) return;
        const formData = new FormData(form);
        formData.append('type', 'expense');
        if (expCompressedFile) formData.append('receipt', expCompressedFile);
        const id = document.getElementById('expenseId').value;
        formData.append('action', id ? 'update' : 'create');
        const btn = document.getElementById('expenseSubmitBtn');
        btn.disabled = true;
        KiTAcc.postForm('api/transactions.php', formData, function(res) {
            if (res.success) {
                KiTAcc.toast(res.message || 'Expense saved!', 'success');
                KiTAcc.closeModal('addExpenseModal');
                setTimeout(() => location.reload(), 800);
            } else {
                KiTAcc.toast(res.message || 'Error saving expense.', 'error');
            }
            btn.disabled = false;
        }, function() {
            KiTAcc.toast('Connection error.', 'error');
            btn.disabled = false;
        });
    }

    function editExpense(id) {
        KiTAcc.get('api/transactions.php?action=get&id=' + id, function(res) {
            if (res.success) {
                const d = res.data;
                document.getElementById('expenseId').value = d.id;
                document.getElementById('expenseModalTitle').textContent = 'Edit Expense';
                const form = document.getElementById('expenseForm');
                form.querySelector('[name="date"]').value = d.date;
                form.querySelector('[name="amount"]').value = d.amount;
                form.querySelector('[name="account_id"]').value = d.account_id;
                form.querySelector('[name="category_id"]').value = d.category_id;
                if (form.querySelector('[name="fund_id"]')) form.querySelector('[name="fund_id"]').value = d.fund_id || '';
                form.querySelector('[name="description"]').value = d.description || '';
                form.querySelector('[name="reference_number"]').value = d.reference_number || '';
                KiTAcc.openModal('addExpenseModal');
            }
        });
    }

    function deleteExpense(id) {
        if (!KiTAcc.confirm('Are you sure you want to delete this expense?')) return;
        KiTAcc.post('api/transactions.php', { action: 'delete', id: id }, function(res) {
            if (res.success) { KiTAcc.toast('Expense deleted.', 'success'); setTimeout(() => location.reload(), 600); }
            else KiTAcc.toast(res.message || 'Error deleting.', 'error');
        });
    }

    function viewReceipt(id) {
        KiTAcc.get('api/transactions.php?action=get&id=' + id, function(res) {
            if (res.success && res.data.receipt_path) window.open(res.data.receipt_path, '_blank');
            else KiTAcc.toast('No receipt attached.', 'info');
        });
    }
</script>
SCRIPT;

include __DIR__ . '/includes/footer.php';
?>