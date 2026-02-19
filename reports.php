<?php
/**
 * KiTAcc - Financial Reports
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();
requireRole(ROLE_BRANCH_FINANCE);

$page_title = 'Reports - KiTAcc';
$user = getCurrentUser();

try {
    $pdo = db();
    // Branches for dropdown (admin_finance + superadmin)
    $branches = [];
    if (hasRole(ROLE_ADMIN_FINANCE)) {
        $stmt = $pdo->query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name");
        $branches = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $branches = [];
}

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-between align-center mb-6">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--gray-800);">Reports</h1>
        <p class="text-muted">Generate financial reports</p>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-file-alt"
                style="color: var(--primary); margin-right: 0.5rem;"></i>Report Parameters</h3>
    </div>
    <div class="card-body">
        <form id="reportForm" onsubmit="return generateReport(event)">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Report Type -->
                <div class="form-group">
                    <label class="form-label required">Report Type</label>
                    <select name="report_type" id="reportType" class="form-control" required>
                        <option value="income">Income (Detailed)</option>
                        <option value="expenses">Expenses (Detailed)</option>
                        <option value="overall" selected>Income / Expenses (Overall)</option>
                    </select>
                </div>

                <?php if (hasRole(ROLE_ADMIN_FINANCE) && !empty($branches)): ?>
                    <!-- Branch Selection -->
                    <div class="form-group">
                        <label class="form-label required">Branch</label>
                        <select name="branch_id" id="branchSelect" class="form-control" required>
                            <option value="all">All Branches</option>
                            <?php foreach ($branches as $br): ?>
                                <option value="<?php echo $br['id']; ?>">
                                    <?php echo htmlspecialchars($br['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Period Selection -->
            <div class="form-group" style="margin-top: 1rem;">
                <label class="form-label required">Period</label>
                <div class="d-flex gap-4 align-center" style="flex-wrap: wrap;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="radio" name="period_type" value="monthly" checked onchange="togglePeriod()">
                        <span>Month & Year</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="radio" name="period_type" value="yearly" onchange="togglePeriod()">
                        <span>Full Year</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="radio" name="period_type" value="custom" onchange="togglePeriod()">
                        <span>Custom Date Range</span>
                    </label>
                </div>
            </div>

            <!-- Monthly Period -->
            <div id="monthlyPeriod" class="grid grid-cols-1 md:grid-cols-2 gap-4" style="margin-top: 0.5rem;">
                <div class="form-group">
                    <label class="form-label">Month</label>
                    <select name="month" id="monthSelect" class="form-control">
                        <?php
                        $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                        $currentMonth = (int) date('n');
                        foreach ($months as $i => $m) {
                            $val = $i + 1;
                            $sel = ($val === $currentMonth) ? 'selected' : '';
                            echo "<option value=\"{$val}\" {$sel}>{$m}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Year</label>
                    <select name="year" id="yearSelect" class="form-control">
                        <?php
                        $currentYear = (int) date('Y');
                        for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
                            echo "<option value=\"{$y}\">{$y}</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <!-- Yearly Period -->
            <div id="yearlyPeriod" class="grid grid-cols-1 md:grid-cols-2 gap-4"
                style="margin-top: 0.5rem; display: none;">
                <div class="form-group">
                    <label class="form-label">Year</label>
                    <select name="full_year" id="fullYearSelect" class="form-control">
                        <?php
                        for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
                            echo "<option value=\"{$y}\">{$y}</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <!-- Custom Date Range -->
            <div id="customPeriod" class="grid grid-cols-1 md:grid-cols-2 gap-4"
                style="margin-top: 0.5rem; display: none;">
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="date_from" id="dateFrom" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input type="date" name="date_to" id="dateTo" class="form-control">
                </div>
            </div>

            <div style="margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-print"></i> Generate Report</button>
            </div>
        </form>
    </div>
</div>

<?php
$page_scripts = <<<'SCRIPT'
<script>
    function togglePeriod() {
        const type = document.querySelector('input[name="period_type"]:checked').value;
        document.getElementById('monthlyPeriod').style.display = type === 'monthly' ? '' : 'none';
        document.getElementById('yearlyPeriod').style.display = type === 'yearly' ? '' : 'none';
        document.getElementById('customPeriod').style.display = type === 'custom' ? '' : 'none';
    }

    function generateReport(e) {
        e.preventDefault();
        const form = document.getElementById('reportForm');
        const type = form.querySelector('[name="report_type"]').value;
        const periodType = form.querySelector('input[name="period_type"]:checked').value;
        const branchEl = form.querySelector('[name="branch_id"]');

        let params = new URLSearchParams();
        params.set('type', type);

        if (branchEl) params.set('branch_id', branchEl.value);

        if (periodType === 'monthly') {
            const m = form.querySelector('[name="month"]').value;
            const y = form.querySelector('[name="year"]').value;
            params.set('month', m);
            params.set('year', y);
        } else if (periodType === 'yearly') {
            const y = form.querySelector('[name="full_year"]').value;
            params.set('year', y);
        } else {
            const from = form.querySelector('[name="date_from"]').value;
            const to = form.querySelector('[name="date_to"]').value;
            if (!from || !to) {
                KiTAcc.toast('Please select start and end dates.', 'error');
                return false;
            }
            params.set('date_from', from);
            params.set('date_to', to);
        }

        window.open('report_view.php?' + params.toString(), '_blank');
        return false;
    }
</script>
SCRIPT;
include __DIR__ . '/includes/footer.php';
?>