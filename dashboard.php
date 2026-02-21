<?php
/**
 * KiTAcc - Dashboard
 * Overview stats, charts, and recent transactions
 * Accessible by branch_finance and above
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();
requireRole(ROLE_BRANCH_FINANCE);

$user = getCurrentUser();
$branchId = getActiveBranchId();
$churchName = getChurchName();
$page_title = 'Dashboard - KiTAcc';

// Branch filter — superadmin & admin_finance can switch branches
$canFilterBranch = in_array($user['role'], [ROLE_SUPERADMIN, ROLE_ADMIN_FINANCE]);
$allBranches = [];
if ($canFilterBranch) {
    try {
        $pdo = db();
        $stmt = $pdo->query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name");
        $allBranches = $stmt->fetchAll();
    } catch (Exception $e) {
        $allBranches = [];
    }

    // Override branchId if filter param is provided
    if (isset($_GET['branch'])) {
        $branchId = $_GET['branch'] === 'all' ? null : intval($_GET['branch']);
    }
}

$selectedBranch = $_GET['branch'] ?? ($branchId ?? 'all');

// ========================================
// FETCH DASHBOARD DATA
// ========================================
try {
    $pdo = db();

    // ---- Current month date range (index-friendly) ----
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');

    // ---- Income & Expense Totals (current month) — 1 query instead of 2 ----
    $sql = "SELECT type, COALESCE(SUM(amount), 0) AS total FROM transactions WHERE date BETWEEN ? AND ? AND type IN ('income','expense')";
    $params = [$monthStart, $monthEnd];
    if ($branchId !== null) {
        $sql .= " AND branch_id = ?";
        $params[] = $branchId;
    }
    $sql .= " GROUP BY type";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $totals = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $incomeTotal = (float) ($totals['income'] ?? 0);
    $expenseTotal = (float) ($totals['expense'] ?? 0);

    // ---- Total Account Balance ----
    $sql = "SELECT COALESCE(SUM(balance), 0) FROM accounts WHERE is_active = 1";
    $params = [];
    if ($branchId !== null) {
        $sql .= " AND branch_id = ?";
        $params[] = $branchId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $totalBalance = (float) $stmt->fetchColumn();

    // ---- Pending Claims ----
    $sql = "SELECT COUNT(*) FROM claims WHERE status = 'pending'";
    $params = [];
    if ($branchId !== null) {
        $sql .= " AND branch_id = ?";
        $params[] = $branchId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pendingClaims = $stmt->fetchColumn();

    // ---- Recent Transactions ----
    $sql = "SELECT t.*, c.name AS category_name, a.name AS account_name, u.name AS created_by_name 
            FROM transactions t 
            LEFT JOIN categories c ON t.category_id = c.id 
            LEFT JOIN accounts a ON t.account_id = a.id 
            LEFT JOIN users u ON t.created_by = u.id
            WHERE 1=1";
    $params = [];
    if ($branchId !== null) {
        $sql .= " AND t.branch_id = ?";
        $params[] = $branchId;
    }
    $sql .= " ORDER BY t.date DESC, t.created_at DESC LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $recentTransactions = $stmt->fetchAll();

    // ---- Monthly Chart Data (last 6 months) — 1 query instead of 12 ----
    $chartStart = date('Y-m-01', strtotime('-5 months'));
    $chartEnd = date('Y-m-t');
    $sql = "SELECT DATE_FORMAT(date, '%Y-%m') AS ym, type, COALESCE(SUM(amount), 0) AS total
            FROM transactions
            WHERE date BETWEEN ? AND ? AND type IN ('income','expense')";
    $params = [$chartStart, $chartEnd];
    if ($branchId !== null) {
        $sql .= " AND branch_id = ?";
        $params[] = $branchId;
    }
    $sql .= " GROUP BY ym, type ORDER BY ym";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $chartRows = $stmt->fetchAll();

    // Build chart data array keyed by month
    $chartMap = [];
    foreach ($chartRows as $row) {
        $chartMap[$row['ym']][$row['type']] = (float) $row['total'];
    }
    $chartData = [];
    for ($i = 5; $i >= 0; $i--) {
        $ym = date('Y-m', strtotime("-{$i} months"));
        $chartData[] = [
            'label' => date('M Y', strtotime("-{$i} months")),
            'income' => $chartMap[$ym]['income'] ?? 0,
            'expense' => $chartMap[$ym]['expense'] ?? 0
        ];
    }

    // ---- Expense Category Breakdown (current month) ----
    $sql = "SELECT COALESCE(c.name, 'Uncategorized') AS category_name, COALESCE(SUM(t.amount), 0) AS total
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
            WHERE t.type = 'expense' AND t.date BETWEEN ? AND ?";
    $params = [$monthStart, $monthEnd];
    if ($branchId !== null) {
        $sql .= " AND t.branch_id = ?";
        $params[] = $branchId;
    }
    $sql .= " GROUP BY t.category_id, c.name ORDER BY total DESC LIMIT 8";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $categoryBreakdown = $stmt->fetchAll();

} catch (Exception $e) {
    $incomeTotal = 0;
    $expenseTotal = 0;
    $totalBalance = 0;
    $pendingClaims = 0;
    $recentTransactions = [];
    $chartData = [];
    $categoryBreakdown = [];
}

include __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="page-header d-flex justify-between align-center mb-6 flex-wrap gap-4">
    <div>
        <h1 class="page-title">
            Shalom, <?php echo htmlspecialchars($user['name']); ?>!
        </h1>
        <p class="text-muted">
            <?php echo htmlspecialchars($churchName); ?> —
            <?php echo date('F Y'); ?>
        </p>
    </div>
    <?php if ($canFilterBranch): ?>
        <div>
            <select class="form-control" id="branchFilter" onchange="filterByBranch(this.value)" style="min-width: 180px;">
                <option value="all" <?php echo $selectedBranch === 'all' ? 'selected' : ''; ?>>All Branches</option>
                <?php foreach ($allBranches as $br): ?>
                    <option value="<?php echo $br['id']; ?>" <?php echo (string) $selectedBranch === (string) $br['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($br['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php elseif ($user['branch_name']): ?>
        <span class="badge badge-primary">
            <i class="fas fa-map-marker-alt"></i>&nbsp;
            <?php echo htmlspecialchars($user['branch_name']); ?>
        </span>
    <?php endif; ?>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <!-- Total Income -->
    <div class="stat-card">
        <div class="stat-icon success">
            <i class="fas fa-arrow-down"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Income This Month</div>
            <div class="stat-value" data-counter="<?php echo $incomeTotal; ?>" data-prefix="RM " data-decimals="2">
                <?php echo formatCurrency($incomeTotal); ?>
            </div>
        </div>
    </div>

    <!-- Total Expenses -->
    <div class="stat-card">
        <div class="stat-icon danger">
            <i class="fas fa-arrow-up"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Expenses This Month</div>
            <div class="stat-value" data-counter="<?php echo $expenseTotal; ?>" data-prefix="RM " data-decimals="2">
                <?php echo formatCurrency($expenseTotal); ?>
            </div>
        </div>
    </div>

    <!-- Total Balance -->
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="fas fa-wallet"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Total Balance</div>
            <div class="stat-value" data-counter="<?php echo $totalBalance; ?>" data-prefix="RM " data-decimals="2">
                <?php echo formatCurrency($totalBalance); ?>
            </div>
        </div>
    </div>

    <!-- Pending Claims -->
    <div class="stat-card">
        <div class="stat-icon warning">
            <i class="fas fa-clipboard-list"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Pending Claims</div>
            <div class="stat-value" data-counter="<?php echo $pendingClaims; ?>" data-decimals="0">
                <?php echo $pendingClaims; ?>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
    <!-- Income vs Expenses Chart -->
    <div class="card" style="grid-column: span 2;">
        <div class="card-header">
            <h3 class="card-title">Income vs Expenses</h3>
            <span class="text-muted" style="font-size: 0.75rem;">Last 6 Months</span>
        </div>
        <div class="card-body">
            <div class="chart-container" style="position: relative;">
                <div class="chart-skeleton" id="barChartSkeleton">
                    <div class="skeleton-row"><div class="skeleton" style="height: 60%; width: 12%;"></div><div class="skeleton" style="height: 80%; width: 12%;"></div><div class="skeleton" style="height: 45%; width: 12%;"></div><div class="skeleton" style="height: 70%; width: 12%;"></div><div class="skeleton" style="height: 55%; width: 12%;"></div><div class="skeleton" style="height: 90%; width: 12%;"></div></div>
                </div>
                <canvas id="incomeExpenseChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Category Breakdown -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Expense Breakdown</h3>
        </div>
        <div class="card-body">
            <div class="chart-container" style="position: relative;">
                <div class="chart-skeleton" id="doughnutChartSkeleton">
                    <div class="skeleton-circle" style="width: 140px; height: 140px; margin: 1rem auto;"></div>
                    <div class="skeleton-text medium" style="margin: 0.5rem auto;"></div>
                </div>
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Recent Transactions -->
<div class="card table-responsive-cards">
    <div class="card-header">
        <h3 class="card-title">Recent Transactions</h3>
        <a href="transactions.php" class="btn btn-sm btn-outline">View All</a>
    </div>

    <!-- Desktop Table -->
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Category</th>
                    <th>Account</th>
                    <th>Type</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentTransactions)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state" style="padding: 2rem;">
                                <i class="fas fa-receipt"></i>
                                <p>No transactions yet. Start recording your church finances!</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentTransactions as $txn): ?>
                        <tr>
                            <td>
                                <?php echo formatDate($txn['date']); ?>
                            </td>
                            <td class="truncate" style="max-width: 200px;">
                                <?php echo htmlspecialchars($txn['description'] ?? '—'); ?>
                            </td>
                            <td><span class="badge badge-secondary">
                                    <?php echo htmlspecialchars($txn['category_name'] ?? '—'); ?>
                                </span></td>
                            <td>
                                <?php echo htmlspecialchars($txn['account_name'] ?? '—'); ?>
                            </td>
                            <td>
                                <?php if ($txn['type'] === 'income'): ?>
                                    <span class="badge badge-success">Income</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Expense</span>
                                <?php endif; ?>
                            </td>
                            <td
                                class="text-right font-semibold <?php echo $txn['type'] === 'income' ? 'text-success' : 'text-danger'; ?>">
                                <?php echo ($txn['type'] === 'income' ? '+' : '-') . formatCurrency($txn['amount']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Cards -->
    <div class="mobile-card">
        <?php if (empty($recentTransactions)): ?>
            <div class="empty-state" style="padding: 2rem;">
                <i class="fas fa-receipt"></i>
                <p>No transactions yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($recentTransactions as $txn): ?>
                <div class="mobile-card-item">
                    <div class="mobile-card-header">
                        <span class="font-semibold">
                            <?php echo htmlspecialchars($txn['description'] ?? 'Transaction'); ?>
                        </span>
                        <?php if ($txn['type'] === 'income'): ?>
                            <span class="badge badge-success">Income</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Expense</span>
                        <?php endif; ?>
                    </div>
                    <div class="mobile-card-row">
                        <span class="mobile-card-label">Date</span>
                        <span class="mobile-card-value">
                            <?php echo formatDate($txn['date']); ?>
                        </span>
                    </div>
                    <div class="mobile-card-row">
                        <span class="mobile-card-label">Amount</span>
                        <span
                            class="mobile-card-value font-semibold <?php echo $txn['type'] === 'income' ? 'text-success' : 'text-danger'; ?>">
                            <?php echo ($txn['type'] === 'income' ? '+' : '-') . formatCurrency($txn['amount']); ?>
                        </span>
                    </div>
                    <div class="mobile-card-row">
                        <span class="mobile-card-label">Category</span>
                        <span class="mobile-card-value">
                            <?php echo htmlspecialchars($txn['category_name'] ?? '—'); ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
$chartDataJson = json_encode($chartData);
$categoryBreakdownJson = json_encode($categoryBreakdown);
$page_scripts = <<<SCRIPT
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
<script>Chart.register(ChartDataLabels);</script>
<script>
    function filterByBranch(val) {
        window.location.href = 'dashboard.php?branch=' + encodeURIComponent(val);
    }

    // Hide skeleton helper
    function hideSkeleton(id) {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    }

    // Chart Data from PHP
    const chartData = {$chartDataJson};
    const categoryData = {$categoryBreakdownJson};
    
    // Income vs Expense Bar Chart
    const ctx1 = document.getElementById('incomeExpenseChart');
    if (ctx1) {
        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: chartData.map(d => d.label),
                datasets: [
                    {
                        label: 'Income',
                        data: chartData.map(d => d.income),
                        backgroundColor: 'rgba(16, 185, 129, 0.8)',
                        borderColor: 'rgba(16, 185, 129, 1)',
                        borderWidth: 1,
                        borderRadius: 6,
                        barPercentage: 0.6
                    },
                    {
                        label: 'Expenses',
                        data: chartData.map(d => d.expense),
                        backgroundColor: 'rgba(239, 68, 68, 0.8)',
                        borderColor: 'rgba(239, 68, 68, 1)',
                        borderWidth: 1,
                        borderRadius: 6,
                        barPercentage: 0.6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    datalabels: {
                        anchor: 'end',
                        align: 'top',
                        color: '#374051',
                        font: { weight: 'bold', size: 11 },
                        formatter: function(value) {
                            if (!value) return '';
                            return 'RM ' + value.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0});
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'RM ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        hideSkeleton('barChartSkeleton');
    }
    
    // Category Breakdown Doughnut — real data
    const ctx2 = document.getElementById('categoryChart');
    if (ctx2) {
        const catColors = [
            'rgba(108, 43, 217, 0.8)',
            'rgba(168, 85, 247, 0.8)',
            'rgba(192, 132, 252, 0.8)',
            'rgba(59, 130, 246, 0.8)',
            'rgba(14, 165, 233, 0.8)',
            'rgba(20, 184, 166, 0.8)',
            'rgba(245, 158, 11, 0.8)',
            'rgba(239, 68, 68, 0.8)'
        ];
        const catLabels = categoryData.length > 0 ? categoryData.map(d => d.category_name) : ['No Data'];
        const catValues = categoryData.length > 0 ? categoryData.map(d => parseFloat(d.total)) : [1];
        const catBg = categoryData.length > 0 ? catColors.slice(0, categoryData.length) : ['rgba(200,200,200,0.5)'];

        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: catLabels,
                datasets: [{
                    data: catValues,
                    backgroundColor: catBg,
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { position: 'bottom' },
                    datalabels: {
                        color: '#fff',
                        font: { weight: 'bold', size: 12 },
                        formatter: function(value, ctx) {
                            const dataset = ctx.chart.data.datasets[0].data;
                            const total = dataset.reduce((a, b) => a + b, 0);
                            if (!total || !value) return '';
                            const pct = ((value / total) * 100).toFixed(1);
                            return pct + '%';
                        }
                    }
                }
            }
        });
        hideSkeleton('doughnutChartSkeleton');
    }
</script>
SCRIPT;

include __DIR__ . '/includes/footer.php';
?>