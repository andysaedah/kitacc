<?php
/**
 * KiTAcc - Categories Management
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();
requireRole(ROLE_SUPERADMIN);

$page_title = 'Categories - KiTAcc';

try {
    $pdo = db();
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY type, name");
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-between align-center mb-6">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--gray-800);">Categories</h1>
        <p class="text-muted">Manage income, expense & claim categories</p>
    </div>
    <button class="btn btn-primary" onclick="KiTAcc.openModal('addCatModal')"><i class="fas fa-plus"></i> Add
        Category</button>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <!-- Income Categories -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-arrow-down text-success"></i> Income Categories</h3>
        </div>
        <div class="card-body p-0">
            <?php $incomeCats = array_filter($categories, fn($c) => $c['type'] === 'income'); ?>
            <?php if (empty($incomeCats)): ?>
                <div class="empty-state p-4">
                    <p>No income categories.</p>
                </div>
            <?php else: ?>
                <?php foreach ($incomeCats as $cat): ?>
                    <div class="d-flex justify-between align-center p-3"
                        style="border-bottom: 1px solid var(--gray-100); <?php echo $cat['is_active'] ? '' : 'opacity: 0.5;'; ?>">
                        <div class="d-flex align-center gap-2">
                            <label class="toggle-switch">
                                <input type="checkbox" <?php echo $cat['is_active'] ? 'checked' : ''; ?>
                                    onchange="toggleCategoryActive(<?php echo $cat['id']; ?>, this.checked, this)">
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="font-medium">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </span>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-icon btn-ghost btn-sm"
                                onclick="editCategory(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars(addslashes($cat['name'])); ?>', '<?php echo $cat['type']; ?>')"><i
                                    class="fas fa-edit"></i></button>
                            <button class="btn btn-icon btn-ghost btn-sm text-danger"
                                onclick="deleteCategory(<?php echo $cat['id']; ?>)"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Expense Categories -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-arrow-up text-danger"></i> Expense Categories</h3>
        </div>
        <div class="card-body p-0">
            <?php $expenseCats = array_filter($categories, fn($c) => $c['type'] === 'expense'); ?>
            <?php if (empty($expenseCats)): ?>
                <div class="empty-state p-4">
                    <p>No expense categories.</p>
                </div>
            <?php else: ?>
                <?php foreach ($expenseCats as $cat): ?>
                    <div class="d-flex justify-between align-center p-3"
                        style="border-bottom: 1px solid var(--gray-100); <?php echo $cat['is_active'] ? '' : 'opacity: 0.5;'; ?>">
                        <div class="d-flex align-center gap-2">
                            <label class="toggle-switch">
                                <input type="checkbox" <?php echo $cat['is_active'] ? 'checked' : ''; ?>
                                    onchange="toggleCategoryActive(<?php echo $cat['id']; ?>, this.checked, this)">
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="font-medium">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </span>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-icon btn-ghost btn-sm"
                                onclick="editCategory(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars(addslashes($cat['name'])); ?>', '<?php echo $cat['type']; ?>')"><i
                                    class="fas fa-edit"></i></button>
                            <button class="btn btn-icon btn-ghost btn-sm text-danger"
                                onclick="deleteCategory(<?php echo $cat['id']; ?>)"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Claim Categories -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-file-invoice text-warning"></i> Claim Categories</h3>
        </div>
        <div class="card-body p-0">
            <?php $claimCats = array_filter($categories, fn($c) => $c['type'] === 'claim'); ?>
            <?php if (empty($claimCats)): ?>
                <div class="empty-state p-4">
                    <p>No claim categories.</p>
                </div>
            <?php else: ?>
                <?php foreach ($claimCats as $cat): ?>
                    <div class="d-flex justify-between align-center p-3"
                        style="border-bottom: 1px solid var(--gray-100); <?php echo $cat['is_active'] ? '' : 'opacity: 0.5;'; ?>">
                        <div class="d-flex align-center gap-2">
                            <label class="toggle-switch">
                                <input type="checkbox" <?php echo $cat['is_active'] ? 'checked' : ''; ?>
                                    onchange="toggleCategoryActive(<?php echo $cat['id']; ?>, this.checked, this)">
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="font-medium">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </span>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-icon btn-ghost btn-sm"
                                onclick="editCategory(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars(addslashes($cat['name'])); ?>', '<?php echo $cat['type']; ?>')"><i
                                    class="fas fa-edit"></i></button>
                            <button class="btn btn-icon btn-ghost btn-sm text-danger"
                                onclick="deleteCategory(<?php echo $cat['id']; ?>)"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="addCatModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="catModalTitle">Add Category</h3><button class="modal-close"><i
                    class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="catForm">
                <input type="hidden" id="catId" name="id" value="">
                <div class="form-group"><label class="form-label required">Name</label><input type="text" name="name"
                        class="form-control" required></div>
                <div class="form-group"><label class="form-label required">Type</label><select name="type"
                        class="form-control" required>
                        <option value="income">Income</option>
                        <option value="expense">Expense</option>
                        <option value="claim">Claim</option>
                    </select></div>
            </form>
        </div>
        <div class="modal-footer"><button class="btn btn-outline"
                onclick="KiTAcc.closeModal('addCatModal')">Cancel</button><button class="btn btn-primary"
                onclick="submitCategory()"><i class="fas fa-save"></i> Save</button></div>
    </div>
</div>

<?php
$page_scripts = <<<'SCRIPT'
<script>
    function submitCategory() {
        const data = KiTAcc.serializeForm(document.getElementById('catForm'));
        data.action = data.id ? 'update' : 'create';
        KiTAcc.post('api/categories.php', data, function(res) {
            if (res.success) { KiTAcc.toast('Saved!', 'success'); setTimeout(() => location.reload(), 600); }
            else KiTAcc.toast(res.message || 'Error.', 'error');
        });
    }
    function editCategory(id, name, type) {
        document.getElementById('catId').value = id;
        document.getElementById('catModalTitle').textContent = 'Edit Category';
        const form = document.getElementById('catForm');
        form.querySelector('[name="name"]').value = name;
        form.querySelector('[name="type"]').value = type;
        KiTAcc.openModal('addCatModal');
    }
    function deleteCategory(id) {
        if (!KiTAcc.confirm('Delete category?')) return;
        KiTAcc.post('api/categories.php', { action: 'delete', id: id }, function(res) {
            if (res.success) { KiTAcc.toast('Deleted.', 'success'); setTimeout(() => location.reload(), 600); }
            else KiTAcc.toast(res.message || 'Error.', 'error');
        });
    }
    function toggleCategoryActive(id, isActive, el) {
        const row = el.closest('div[class*="d-flex"]').parentElement;
        KiTAcc.post('api/categories.php', { action: 'toggle_active', id: id, is_active: isActive ? 1 : 0 }, function(res) {
            if (res.success) {
                KiTAcc.toast(isActive ? 'Category activated.' : 'Category deactivated.', 'success');
                row.style.opacity = isActive ? '1' : '0.5';
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