<?php
/**
 * KiTAcc - Visual Chart Report
 * Standalone page for generating bar & trend charts based on report filters
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();
requireRole(ROLE_BRANCH_FINANCE);

$user = getCurrentUser();
$pdo = db();

// ========================================
// PARSE PARAMETERS (same as report_view.php)
// ========================================
$reportType = $_GET['type'] ?? 'overall';
$branchId   = $_GET['branch_id'] ?? null;
$month      = isset($_GET['month']) ? intval($_GET['month']) : null;
$year       = isset($_GET['year']) ? intval($_GET['year']) : (int) date('Y');
$dateFrom   = $_GET['date_from'] ?? null;
$dateTo     = $_GET['date_to'] ?? null;

// Branch access control
if (!hasRole(ROLE_ADMIN_FINANCE)) {
    $branchId = $user['branch_id'];
} elseif ($branchId === 'all' || $branchId === '') {
    $branchId = null;
} else {
    $branchId = intval($branchId);
}

// Determine date range
if ($dateFrom && $dateTo) {
    $startDate = $dateFrom;
    $endDate = $dateTo;
    $periodLabel = date('d M Y', strtotime($dateFrom)) . ' - ' . date('d M Y', strtotime($dateTo));
} elseif ($month) {
    $startDate = sprintf('%04d-%02d-01', $year, $month);
    $endDate = date('Y-m-t', strtotime($startDate));
    $monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $periodLabel = $monthNames[$month] . ' ' . $year;
} else {
    $startDate = $year . '-01-01';
    $endDate   = $year . '-12-31';
    $periodLabel = 'January - December ' . $year;
}

// Build month columns
$monthColumns = [];
$sd = new DateTime($startDate);
$ed = new DateTime($endDate);
$ed->modify('first day of this month');
$iter = clone $sd;
$iter->modify('first day of this month');
while ($iter <= $ed) {
    $monthColumns[] = [
        'num'   => (int) $iter->format('n'),
        'year'  => (int) $iter->format('Y'),
        'label' => $iter->format('M Y'),
        'key'   => $iter->format('Y-n')
    ];
    $iter->modify('+1 month');
}

$churchName = getChurchName();

// ========================================
// FETCH DATA
// ========================================
$branchFilter = '';
$branchParams = [];
if ($branchId) {
    $branchFilter = 'AND t.branch_id = ?';
    $branchParams = [$branchId];
}

// Get branch name if single branch
$branchLabel = '';
if ($branchId) {
    $brStmt = $pdo->prepare("SELECT name FROM branches WHERE id = ?");
    $brStmt->execute([$branchId]);
    $branchLabel = $brStmt->fetchColumn() ?: '';
}

// Always fetch income & expense per month for charts (regardless of report type)
$sql = "SELECT t.type,
            DATE_FORMAT(t.date, '%Y-%c') AS ym,
            SUM(t.amount) AS total
        FROM transactions t
        WHERE t.type IN ('income','expense') AND t.date BETWEEN ? AND ?
        {$branchFilter}
        GROUP BY t.type, ym
        ORDER BY ym";

$params = array_merge([$startDate, $endDate], $branchParams);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Organize: type => month_key => amount
$chartData = ['income' => [], 'expense' => []];
foreach ($rows as $r) {
    $chartData[$r['type']][$r['ym']] = floatval($r['total']);
}

// Build arrays for Chart.js
$labels = [];
$incomeValues = [];
$expenseValues = [];
foreach ($monthColumns as $mc) {
    $labels[] = $mc['label'];
    $incomeValues[] = $chartData['income'][$mc['key']] ?? 0;
    $expenseValues[] = $chartData['expense'][$mc['key']] ?? 0;
}

// Also fetch per-branch data if showing all branches
$branchChartData = [];
if (!$branchId) {
    $brSql = "SELECT b.name AS branch_name, t.type,
                DATE_FORMAT(t.date, '%Y-%c') AS ym,
                SUM(t.amount) AS total
              FROM transactions t
              LEFT JOIN branches b ON t.branch_id = b.id
              WHERE t.type IN ('income','expense') AND t.date BETWEEN ? AND ?
              GROUP BY b.name, t.type, ym
              ORDER BY b.name, ym";
    $brStmt2 = $pdo->prepare($brSql);
    $brStmt2->execute([$startDate, $endDate]);
    foreach ($brStmt2->fetchAll() as $r) {
        $bn = $r['branch_name'] ?: 'Unknown';
        if (!isset($branchChartData[$bn])) {
            $branchChartData[$bn] = ['income' => [], 'expense' => []];
        }
        $branchChartData[$bn][$r['type']][$r['ym']] = floatval($r['total']);
    }
}

// Fetch per-category data for pie/breakdown charts
$catSql = "SELECT c.name AS category_name, t.type, SUM(t.amount) AS total
           FROM transactions t
           LEFT JOIN categories c ON t.category_id = c.id
           WHERE t.type IN ('income','expense') AND t.date BETWEEN ? AND ?
           {$branchFilter}
           GROUP BY c.name, t.type
           ORDER BY t.type, total DESC";
$catStmt = $pdo->prepare($catSql);
$catStmt->execute(array_merge([$startDate, $endDate], $branchParams));
$categoryRows = $catStmt->fetchAll();

$incomeCategories = [];
$expenseCategories = [];
foreach ($categoryRows as $cr) {
    $name = $cr['category_name'] ?: 'Uncategorized';
    if ($cr['type'] === 'income') {
        $incomeCategories[$name] = floatval($cr['total']);
    } else {
        $expenseCategories[$name] = floatval($cr['total']);
    }
}

// ========================================
// CLAIMS DATA FOR CHARTS
// ========================================
$claimBranchFilter = $branchId ? 'AND cl.branch_id = ?' : '';
$claimBranchParams = $branchId ? [$branchId] : [];

// Claims by status totals
$clSumSql = "SELECT cl.status, COUNT(*) AS cnt, SUM(cl.amount) AS total
             FROM claims cl
             WHERE cl.created_at BETWEEN ? AND ?
             {$claimBranchFilter}
             GROUP BY cl.status";
$clSumStmt = $pdo->prepare($clSumSql);
$clSumStmt->execute(array_merge([$startDate, $endDate . ' 23:59:59'], $claimBranchParams));
$claimStatusData = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($clSumStmt->fetchAll() as $cs) {
    $claimStatusData[$cs['status']] = floatval($cs['total']);
}

// Claims monthly by status
$clMonthSql = "SELECT cl.status,
                 DATE_FORMAT(cl.created_at, '%Y-%c') AS ym,
                 SUM(cl.amount) AS total
               FROM claims cl
               WHERE cl.created_at BETWEEN ? AND ?
               {$claimBranchFilter}
               GROUP BY cl.status, ym
               ORDER BY ym";
$clMonthStmt = $pdo->prepare($clMonthSql);
$clMonthStmt->execute(array_merge([$startDate, $endDate . ' 23:59:59'], $claimBranchParams));
$claimMonthlyChart = ['pending' => [], 'approved' => [], 'rejected' => []];
foreach ($clMonthStmt->fetchAll() as $cm) {
    $claimMonthlyChart[$cm['status']][$cm['ym']] = floatval($cm['total']);
}

// Build claims arrays for Chart.js
$claimPendingValues = [];
$claimApprovedValues = [];
$claimRejectedValues = [];
foreach ($monthColumns as $mc) {
    $claimPendingValues[] = $claimMonthlyChart['pending'][$mc['key']] ?? 0;
    $claimApprovedValues[] = $claimMonthlyChart['approved'][$mc['key']] ?? 0;
    $claimRejectedValues[] = $claimMonthlyChart['rejected'][$mc['key']] ?? 0;
}

// Claims by category
$clCatSql = "SELECT c.name AS category_name, SUM(cl.amount) AS total
             FROM claims cl
             LEFT JOIN categories c ON cl.category_id = c.id
             WHERE cl.created_at BETWEEN ? AND ?
             {$claimBranchFilter}
             GROUP BY c.name
             ORDER BY total DESC";
$clCatStmt = $pdo->prepare($clCatSql);
$clCatStmt->execute(array_merge([$startDate, $endDate . ' 23:59:59'], $claimBranchParams));
$claimCats = [];
foreach ($clCatStmt->fetchAll() as $cc) {
    $claimCats[$cc['category_name'] ?: 'Uncategorized'] = floatval($cc['total']);
}

// JSON encode for JS
$jsLabels    = json_encode($labels);
$jsIncome    = json_encode($incomeValues);
$jsExpenses  = json_encode($expenseValues);
$jsIncCats   = json_encode(array_keys($incomeCategories));
$jsIncCatVal = json_encode(array_values($incomeCategories));
$jsExpCats   = json_encode(array_keys($expenseCategories));
$jsExpCatVal = json_encode(array_values($expenseCategories));

// Claims JSON
$jsClaimStatusLabels = json_encode(['Pending', 'Approved', 'Rejected']);
$jsClaimStatusValues = json_encode(array_values($claimStatusData));
$jsClaimPending  = json_encode($claimPendingValues);
$jsClaimApproved = json_encode($claimApprovedValues);
$jsClaimRejected = json_encode($claimRejectedValues);
$jsClaimCatLabels = json_encode(array_keys($claimCats));
$jsClaimCatValues = json_encode(array_values($claimCats));
$isClaimsReport = ($reportType === 'claims') ? 'true' : 'false';

// Branch chart data for stacked charts
$jsBranchData = json_encode($branchChartData);
$jsMonthKeys  = json_encode(array_column($monthColumns, 'key'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visual Chart - <?php echo htmlspecialchars($churchName); ?> <?php echo $periodLabel; ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f3ff;
            color: #1a202c;
            padding: 20px;
        }
        .chart-header {
            text-align: center;
            margin-bottom: 24px;
        }
        .chart-header h1 {
            font-size: 1.4rem;
            font-weight: 700;
            color: #6C2BD9;
            margin-bottom: 4px;
        }
        .chart-header p {
            font-size: 0.9rem;
            color: #718096;
        }
        .btn-bar {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-print {
            background: #2b6cb0;
            color: #fff;
        }
        .btn-print:hover { background: #2c5282; }
        .btn-back {
            background: #e2e8f0;
            color: #2d3748;
        }
        .btn-back:hover { background: #cbd5e0; }

        .charts-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .chart-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
        }
        .chart-card h3 {
            font-size: 1rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #f0e6ff;
        }
        .chart-container {
            position: relative;
            width: 100%;
            max-height: 420px;
        }
        .half-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }
        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card .stat-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .stat-card .stat-value {
            font-size: 1.25rem;
            font-weight: 700;
        }
        .stat-income { border-left: 4px solid #38a169; }
        .stat-income .stat-value { color: #38a169; }
        .stat-expense { border-left: 4px solid #e53e3e; }
        .stat-expense .stat-value { color: #e53e3e; }
        .stat-balance { border-left: 4px solid #6C2BD9; }
        .stat-balance .stat-value { color: #6C2BD9; }

        @media print {
            .btn-bar { display: none !important; }
            body { background: #fff; padding: 10px; }
            .chart-card { box-shadow: none; border: 1px solid #e2e8f0; page-break-inside: avoid; }
            .half-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .half-grid { grid-template-columns: 1fr; }
            body { padding: 12px; }
            .chart-header h1 { font-size: 1.1rem; }
        }
    </style>
</head>
<body>

<div class="chart-header">
    <h1><?php echo htmlspecialchars($churchName); ?></h1>
    <p>Financial Visual Chart &mdash; <?php echo htmlspecialchars($periodLabel); ?>
    <?php if ($branchLabel): ?> &mdash; <?php echo htmlspecialchars($branchLabel); ?><?php endif; ?></p>
</div>

<div class="btn-bar no-print">
    <button class="btn btn-back" onclick="window.close(); return false;">&#x2190; Close</button>
    <button class="btn btn-print" onclick="window.print()">&#x1F5A8;&#xFE0F; Print / Save as PDF</button>
</div>

<!-- Summary Stats -->
<?php
$totalIncome  = array_sum($incomeValues);
$totalExpense = array_sum($expenseValues);
$netBalance   = $totalIncome - $totalExpense;
?>

<?php if ($reportType === 'claims'): ?>
    <!-- CLAIMS SUMMARY STATS -->
    <div class="summary-stats">
        <div class="stat-card" style="border-left: 4px solid #d69e2e;">
            <div class="stat-label">Pending</div>
            <div class="stat-value" style="color: #d69e2e;">RM <?php echo number_format($claimStatusData['pending'], 2); ?></div>
        </div>
        <div class="stat-card stat-income">
            <div class="stat-label">Approved</div>
            <div class="stat-value">RM <?php echo number_format($claimStatusData['approved'], 2); ?></div>
        </div>
        <div class="stat-card stat-expense">
            <div class="stat-label">Rejected</div>
            <div class="stat-value">RM <?php echo number_format($claimStatusData['rejected'], 2); ?></div>
        </div>
        <div class="stat-card stat-balance">
            <div class="stat-label">Total Claims</div>
            <div class="stat-value">RM <?php echo number_format(array_sum($claimStatusData), 2); ?></div>
        </div>
    </div>

    <div class="charts-grid">
        <!-- 1. Bar Chart: Claims by Status per Month -->
        <div class="chart-card">
            <h3>&#x1F4CA; Claims by Status (Bar Chart)</h3>
            <div class="chart-container">
                <canvas id="claimBarChart"></canvas>
            </div>
        </div>

        <!-- 2. Trend Line Chart: Claims over Time -->
        <div class="chart-card">
            <h3>&#x1F4C8; Claims Trend</h3>
            <div class="chart-container">
                <canvas id="claimTrendChart"></canvas>
            </div>
        </div>

        <!-- 3. Claims Breakdown -->
        <div class="half-grid">
            <div class="chart-card">
                <h3>&#x1F4CB; Claims by Status</h3>
                <div class="chart-container">
                    <canvas id="claimStatusPie"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h3>&#x1F4B8; Claims by Category</h3>
                <div class="chart-container">
                    <canvas id="claimCatPie"></canvas>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- INCOME / EXPENSES SUMMARY STATS -->
    <div class="summary-stats">
        <div class="stat-card stat-income">
            <div class="stat-label">Total Income</div>
            <div class="stat-value">RM <?php echo number_format($totalIncome, 2); ?></div>
        </div>
        <div class="stat-card stat-expense">
            <div class="stat-label">Total Expenses</div>
            <div class="stat-value">RM <?php echo number_format($totalExpense, 2); ?></div>
        </div>
        <div class="stat-card stat-balance">
            <div class="stat-label">Net <?php echo $netBalance >= 0 ? 'Surplus' : 'Deficit'; ?></div>
            <div class="stat-value">RM <?php echo number_format($netBalance, 2); ?></div>
        </div>
    </div>

    <div class="charts-grid">
        <!-- 1. Bar Chart: Income vs Expenses -->
        <div class="chart-card">
            <h3>&#x1F4CA; Income vs Expenses (Bar Chart)</h3>
            <div class="chart-container">
                <canvas id="barChart"></canvas>
            </div>
        </div>

        <!-- 2. Trend Line Chart -->
        <div class="chart-card">
            <h3>&#x1F4C8; Income &amp; Expenses Trend</h3>
            <div class="chart-container">
                <canvas id="trendChart"></canvas>
            </div>
        </div>

        <!-- 3. Category Breakdown (Doughnut Charts) -->
        <div class="half-grid">
            <div class="chart-card">
                <h3>&#x1F4B0; Income by Category</h3>
                <div class="chart-container">
                    <canvas id="incomePieChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h3>&#x1F4B8; Expenses by Category</h3>
                <div class="chart-container">
                    <canvas id="expensePieChart"></canvas>
                </div>
            </div>
        </div>

        <?php if (!empty($branchChartData) && count($branchChartData) > 1): ?>
        <!-- 4. Branch Comparison -->
        <div class="chart-card">
            <h3>&#x1F3E2; Branch Comparison (Net Income)</h3>
            <div class="chart-container">
                <canvas id="branchChart"></canvas>
            </div>
        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
(function() {
    // Register datalabels plugin globally
    Chart.register(ChartDataLabels);
    // Default: disable datalabels globally (enable per-chart)
    Chart.defaults.plugins.datalabels = { display: false };

    const labels     = <?php echo $jsLabels; ?>;
    const incomeData = <?php echo $jsIncome; ?>;
    const expenseData = <?php echo $jsExpenses; ?>;

    const incCatLabels = <?php echo $jsIncCats; ?>;
    const incCatValues = <?php echo $jsIncCatVal; ?>;
    const expCatLabels = <?php echo $jsExpCats; ?>;
    const expCatValues = <?php echo $jsExpCatVal; ?>;

    const branchData = <?php echo $jsBranchData; ?>;
    const monthKeys  = <?php echo $jsMonthKeys; ?>;

    const isClaims = <?php echo $isClaimsReport; ?>;

    // Color palette
    const incomeColor   = '#38a169';
    const incomeColorBg = 'rgba(56, 161, 105, 0.7)';
    const expenseColor   = '#e53e3e';
    const expenseColorBg = 'rgba(229, 62, 62, 0.7)';
    const pendingColor   = '#d69e2e';
    const pendingColorBg = 'rgba(214, 158, 46, 0.7)';
    const approvedColor   = '#38a169';
    const approvedColorBg = 'rgba(56, 161, 105, 0.7)';
    const rejectedColor   = '#e53e3e';
    const rejectedColorBg = 'rgba(229, 62, 62, 0.7)';
    const palette = [
        '#6C2BD9','#38a169','#e53e3e','#3182ce','#d69e2e',
        '#dd6b20','#319795','#805ad5','#d53f8c','#2b6cb0',
        '#975a16','#285e61','#702459','#1a365d','#744210'
    ];

    // Common options
    const commonOpts = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top', labels: { font: { size: 12, weight: '600' }, padding: 12 } },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        return ctx.dataset.label + ': RM ' + ctx.parsed.y.toLocaleString('en-MY', {minimumFractionDigits: 2});
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(v) { return 'RM ' + v.toLocaleString(); },
                    font: { size: 11 }
                },
                grid: { color: 'rgba(0,0,0,0.06)' }
            },
            x: { ticks: { font: { size: 11 } }, grid: { display: false } }
        }
    };

    // Doughnut common options
    const doughnutOpts = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { font: {size: 11}, padding: 8 } },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        const total = ctx.dataset.data.reduce((a,b) => a+b, 0);
                        const pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                        return ctx.label + ': RM ' + ctx.parsed.toLocaleString('en-MY', {minimumFractionDigits: 2}) + ' (' + pct + '%)';
                    }
                }
            },
            datalabels: {
                display: true,
                color: '#fff',
                font: { size: 11, weight: '700' },
                textShadowColor: 'rgba(0,0,0,0.4)',
                textShadowBlur: 3,
                formatter: function(value, ctx) {
                    const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                    if (total === 0 || value === 0) return '';
                    const pct = ((value / total) * 100).toFixed(1);
                    return pct + '%';
                }
            }
        }
    };

    const dataLabelOpts = {
        display: true,
        anchor: 'end',
        align: 'end',
        offset: 2,
        font: { size: 10, weight: '600' },
        color: '#2d3748',
        formatter: function(v) {
            if (v === 0) return '';
            return 'RM ' + v.toLocaleString('en-MY', {minimumFractionDigits: 0, maximumFractionDigits: 0});
        }
    };

    if (isClaims) {
        // ========== CLAIMS CHARTS ==========
        const claimPending  = <?php echo $jsClaimPending; ?>;
        const claimApproved = <?php echo $jsClaimApproved; ?>;
        const claimRejected = <?php echo $jsClaimRejected; ?>;
        const claimStatusLabels = <?php echo $jsClaimStatusLabels; ?>;
        const claimStatusValues = <?php echo $jsClaimStatusValues; ?>;
        const claimCatLabels = <?php echo $jsClaimCatLabels; ?>;
        const claimCatValues = <?php echo $jsClaimCatValues; ?>;

        // 1. Claim Bar Chart (by status per month)
        const clBarOpts = JSON.parse(JSON.stringify(commonOpts));
        clBarOpts.plugins.datalabels = dataLabelOpts;
        new Chart(document.getElementById('claimBarChart'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Pending', data: claimPending, backgroundColor: pendingColorBg, borderColor: pendingColor, borderWidth: 2, borderRadius: 4 },
                    { label: 'Approved', data: claimApproved, backgroundColor: approvedColorBg, borderColor: approvedColor, borderWidth: 2, borderRadius: 4 },
                    { label: 'Rejected', data: claimRejected, backgroundColor: rejectedColorBg, borderColor: rejectedColor, borderWidth: 2, borderRadius: 4 }
                ]
            },
            options: clBarOpts
        });

        // 2. Claim Trend Chart
        const clTrendOpts = JSON.parse(JSON.stringify(commonOpts));
        clTrendOpts.plugins.datalabels = {
            display: true, anchor: 'end', align: 'top', offset: 4,
            font: { size: 10, weight: '600' },
            color: function(ctx) { return ctx.dataset.borderColor; },
            formatter: function(v) {
                if (v === 0) return '';
                return 'RM ' + v.toLocaleString('en-MY', {minimumFractionDigits: 0, maximumFractionDigits: 0});
            }
        };
        clTrendOpts.interaction = { intersect: false, mode: 'index' };

        // Total claims per month for trend
        const claimTotalPerMonth = labels.map((_, i) => claimPending[i] + claimApproved[i] + claimRejected[i]);

        new Chart(document.getElementById('claimTrendChart'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Approved', data: claimApproved, borderColor: approvedColor, backgroundColor: 'rgba(56, 161, 105, 0.1)', borderWidth: 3, pointRadius: 5, pointHoverRadius: 7, pointBackgroundColor: approvedColor, fill: true, tension: 0.3 },
                    { label: 'Pending', data: claimPending, borderColor: pendingColor, backgroundColor: 'rgba(214, 158, 46, 0.1)', borderWidth: 3, pointRadius: 5, pointHoverRadius: 7, pointBackgroundColor: pendingColor, fill: true, tension: 0.3 },
                    { label: 'Rejected', data: claimRejected, borderColor: rejectedColor, backgroundColor: 'rgba(229, 62, 62, 0.1)', borderWidth: 3, pointRadius: 5, pointHoverRadius: 7, pointBackgroundColor: rejectedColor, fill: true, tension: 0.3 }
                ]
            },
            options: clTrendOpts
        });

        // 3. Claims Status Pie
        if (claimStatusValues.some(v => v > 0)) {
            new Chart(document.getElementById('claimStatusPie'), {
                type: 'doughnut',
                data: {
                    labels: claimStatusLabels,
                    datasets: [{ data: claimStatusValues, backgroundColor: [pendingColor, approvedColor, rejectedColor], borderWidth: 2, borderColor: '#fff' }]
                },
                options: doughnutOpts
            });
        } else {
            document.getElementById('claimStatusPie').parentElement.innerHTML += '<p style="text-align:center;color:#a0aec0;padding:40px 0;">No claims data</p>';
        }

        // 4. Claims Category Pie
        if (claimCatLabels.length > 0) {
            new Chart(document.getElementById('claimCatPie'), {
                type: 'doughnut',
                data: {
                    labels: claimCatLabels,
                    datasets: [{ data: claimCatValues, backgroundColor: palette.slice(0, claimCatLabels.length), borderWidth: 2, borderColor: '#fff' }]
                },
                options: doughnutOpts
            });
        } else {
            document.getElementById('claimCatPie').parentElement.innerHTML += '<p style="text-align:center;color:#a0aec0;padding:40px 0;">No claims data</p>';
        }

    } else {
        // ========== INCOME / EXPENSES CHARTS ==========

        // 1. Bar Chart
        const barOpts = JSON.parse(JSON.stringify(commonOpts));
        barOpts.plugins.datalabels = dataLabelOpts;
        new Chart(document.getElementById('barChart'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Income', data: incomeData, backgroundColor: incomeColorBg, borderColor: incomeColor, borderWidth: 2, borderRadius: 4 },
                    { label: 'Expenses', data: expenseData, backgroundColor: expenseColorBg, borderColor: expenseColor, borderWidth: 2, borderRadius: 4 }
                ]
            },
            options: barOpts
        });

        // 2. Trend Chart
        const trendOpts = JSON.parse(JSON.stringify(commonOpts));
        trendOpts.plugins.tooltip = {
            callbacks: {
                label: function(ctx) {
                    return ctx.dataset.label + ': RM ' + ctx.parsed.y.toLocaleString('en-MY', {minimumFractionDigits: 2});
                }
            }
        };
        trendOpts.plugins.datalabels = {
            display: true, anchor: 'end', align: 'top', offset: 4,
            font: { size: 10, weight: '600' },
            color: function(ctx) { return ctx.dataset.borderColor; },
            formatter: function(v) {
                if (v === 0) return '';
                return 'RM ' + v.toLocaleString('en-MY', {minimumFractionDigits: 0, maximumFractionDigits: 0});
            }
        };
        trendOpts.interaction = { intersect: false, mode: 'index' };

        new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Income', data: incomeData, borderColor: incomeColor, backgroundColor: 'rgba(56, 161, 105, 0.1)', borderWidth: 3, pointRadius: 5, pointHoverRadius: 7, pointBackgroundColor: incomeColor, fill: true, tension: 0.3 },
                    { label: 'Expenses', data: expenseData, borderColor: expenseColor, backgroundColor: 'rgba(229, 62, 62, 0.1)', borderWidth: 3, pointRadius: 5, pointHoverRadius: 7, pointBackgroundColor: expenseColor, fill: true, tension: 0.3 }
                ]
            },
            options: trendOpts
        });

        // 3. Income Pie
        if (incCatLabels.length > 0) {
            new Chart(document.getElementById('incomePieChart'), {
                type: 'doughnut',
                data: { labels: incCatLabels, datasets: [{ data: incCatValues, backgroundColor: palette.slice(0, incCatLabels.length), borderWidth: 2, borderColor: '#fff' }] },
                options: doughnutOpts
            });
        } else {
            document.getElementById('incomePieChart').parentElement.innerHTML += '<p style="text-align:center;color:#a0aec0;padding:40px 0;">No income data</p>';
        }

        // 4. Expense Pie
        if (expCatLabels.length > 0) {
            new Chart(document.getElementById('expensePieChart'), {
                type: 'doughnut',
                data: { labels: expCatLabels, datasets: [{ data: expCatValues, backgroundColor: palette.slice(0, expCatLabels.length), borderWidth: 2, borderColor: '#fff' }] },
                options: doughnutOpts
            });
        } else {
            document.getElementById('expensePieChart').parentElement.innerHTML += '<p style="text-align:center;color:#a0aec0;padding:40px 0;">No expense data</p>';
        }

        // 5. Branch Comparison
        const branchCanvas = document.getElementById('branchChart');
        if (branchCanvas) {
            const branchNames = Object.keys(branchData);
            const datasets = branchNames.map((bn, i) => {
                const netData = monthKeys.map(mk => {
                    const inc = (branchData[bn].income && branchData[bn].income[mk]) || 0;
                    const exp = (branchData[bn].expense && branchData[bn].expense[mk]) || 0;
                    return inc - exp;
                });
                return {
                    label: bn, data: netData,
                    borderColor: palette[i % palette.length],
                    backgroundColor: palette[i % palette.length] + '33',
                    borderWidth: 2, pointRadius: 4, fill: false, tension: 0.3
                };
            });

            const branchOpts = JSON.parse(JSON.stringify(commonOpts));
            branchOpts.plugins.tooltip = {
                callbacks: { label: function(ctx) { return ctx.dataset.label + ': RM ' + ctx.parsed.y.toLocaleString('en-MY', {minimumFractionDigits: 2}); } }
            };
            new Chart(branchCanvas, { type: 'line', data: { labels: labels, datasets: datasets }, options: branchOpts });
        }
    }
})();
</script>

</body>
</html>
