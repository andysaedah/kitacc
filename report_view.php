<?php
/**
 * KiTAcc - Report View (Print-ready)
 * Standalone page for generating printable financial reports
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();
requireRole(ROLE_BRANCH_FINANCE);

$user = getCurrentUser();
$pdo = db();

// ========================================
// PARSE PARAMETERS
// ========================================
$reportType = $_GET['type'] ?? 'overall'; // income, expenses, overall
$branchId = $_GET['branch_id'] ?? null;
$month = isset($_GET['month']) ? intval($_GET['month']) : null;
$year = isset($_GET['year']) ? intval($_GET['year']) : (int) date('Y');
$dateFrom = $_GET['date_from'] ?? null;
$dateTo = $_GET['date_to'] ?? null;

// Branch access control
if (!hasRole(ROLE_ADMIN_FINANCE)) {
    // Branch finance can only see their own branch
    $branchId = $user['branch_id'];
} elseif ($branchId === 'all' || $branchId === '') {
    $branchId = null; // all branches
} else {
    $branchId = intval($branchId);
}

// Determine date range
if ($dateFrom && $dateTo) {
    // Custom range
    $startDate = $dateFrom;
    $endDate = $dateTo;
    $periodLabel = date('d M Y', strtotime($dateFrom)) . ' - ' . date('d M Y', strtotime($dateTo));
    // Calculate months in range for columns
    $startDt = new DateTime($dateFrom);
    $endDt = new DateTime($dateTo);
    $isCustomRange = true;
} elseif ($month) {
    // Single month
    $startDate = sprintf('%04d-%02d-01', $year, $month);
    $endDate = date('Y-m-t', strtotime($startDate));
    $monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $periodLabel = $monthNames[$month] . ' ' . $year;
    $isCustomRange = false;
} else {
    // Full year
    $startDate = $year . '-01-01';
    $endDate = $year . '-12-31';
    $periodLabel = 'January - December ' . $year;
    $isCustomRange = false;
}

// Build list of months for columns
$monthColumns = [];
$sd = new DateTime($startDate);
$ed = new DateTime($endDate);
$ed->modify('first day of this month'); // normalize to first of month for iteration
$iter = clone $sd;
$iter->modify('first day of this month');
while ($iter <= $ed) {
    $monthColumns[] = [
        'num' => (int) $iter->format('n'),
        'year' => (int) $iter->format('Y'),
        'label' => $iter->format('M'),
        'key' => $iter->format('Y-n')
    ];
    $iter->modify('+1 month');
}

// Church name
$churchName = getChurchName();

// ========================================
// FETCH BRANCHES
// ========================================
$branchFilter = '';
$branchParams = [];
if ($branchId) {
    $branchFilter = 'AND t.branch_id = ?';
    $branchParams = [$branchId];
}

$branchStmt = $pdo->prepare("SELECT id, name FROM branches WHERE is_active = 1" .
    ($branchId ? " AND id = ?" : "") . " ORDER BY name");
$branchStmt->execute($branchId ? [$branchId] : []);
$branches = $branchStmt->fetchAll();

// ========================================
// FETCH ACCOUNT STARTING BALANCES (per branch)
// ========================================
$accountBalances = [];
$accBalSql = "SELECT branch_id, COALESCE(SUM(balance), 0) AS total_balance FROM accounts WHERE is_active = 1";
$accBalParams = [];
if ($branchId) {
    $accBalSql .= " AND branch_id = ?";
    $accBalParams[] = $branchId;
}
$accBalSql .= " GROUP BY branch_id";
$accBalStmt = $pdo->prepare($accBalSql);
$accBalStmt->execute($accBalParams);
foreach ($accBalStmt->fetchAll() as $ab) {
    $accountBalances[$ab['branch_id']] = floatval($ab['total_balance']);
}
$totalAccountBalance = array_sum($accountBalances);

// ========================================
// FETCH DATA
// ========================================

if ($reportType === 'income' || $reportType === 'expenses') {
    // Detailed report: categories as rows, months as columns
    $txType = ($reportType === 'income') ? 'income' : 'expense';

    $sql = "SELECT t.category_id, c.name AS category_name, 
                DATE_FORMAT(t.date, '%Y-%c') AS ym,
                SUM(t.amount) AS total
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
            WHERE t.type = ? AND t.date BETWEEN ? AND ?
            {$branchFilter}
            GROUP BY t.category_id, c.name, ym
            ORDER BY c.name, ym";

    $params = array_merge([$txType, $startDate, $endDate], $branchParams);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Organize data: category => month_key => amount
    $data = [];
    foreach ($rows as $r) {
        $catName = $r['category_name'] ?: 'Uncategorized';
        $key = $r['ym'];
        if (!isset($data[$catName]))
            $data[$catName] = [];
        $data[$catName][$key] = floatval($r['total']);
    }
    ksort($data); // alphabetical categories

} elseif ($reportType === 'overall') {
    // Overall: branches as groups, income/expense rows per branch
    $sql = "SELECT t.branch_id, b.name AS branch_name, t.type,
                DATE_FORMAT(t.date, '%Y-%c') AS ym,
                SUM(t.amount) AS total
            FROM transactions t
            LEFT JOIN branches b ON t.branch_id = b.id
            WHERE t.type IN ('income','expense') AND t.date BETWEEN ? AND ?
            {$branchFilter}
            GROUP BY t.branch_id, b.name, t.type, ym
            ORDER BY b.name, t.type, ym";

    $params = array_merge([$startDate, $endDate], $branchParams);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Organize: branch => type => month_key => amount
    $overallData = [];
    foreach ($rows as $r) {
        $brName = $r['branch_name'] ?: 'Unknown Branch';
        $key = $r['ym'];
        if (!isset($overallData[$brName]))
            $overallData[$brName] = ['income' => [], 'expense' => []];
        $overallData[$brName][$r['type']][$key] = floatval($r['total']);
    }
    ksort($overallData);

    // Also fetch per-category totals for notes section
    $catSql = "SELECT c.name AS category_name, t.type, SUM(t.amount) AS total
               FROM transactions t
               LEFT JOIN categories c ON t.category_id = c.id
               WHERE t.type IN ('income','expense') AND t.date BETWEEN ? AND ?
               {$branchFilter}
               GROUP BY c.name, t.type
               ORDER BY t.type, total DESC";
    $catStmt = $pdo->prepare($catSql);
    $catStmt->execute(array_merge([$startDate, $endDate], $branchParams));
    $categoryTotals = $catStmt->fetchAll();
}

// ========================================
// CLAIMS REPORT
// ========================================
if ($reportType === 'claims') {
    $claimBranchFilter = $branchId ? 'AND cl.branch_id = ?' : '';
    $claimBranchParams = $branchId ? [$branchId] : [];

    // Detailed claims list
    $clSql = "SELECT cl.*, 
                u.display_name AS submitter_name,
                c.name AS category_name,
                a.name AS account_name,
                ap.display_name AS approver_name
              FROM claims cl
              LEFT JOIN users u ON cl.submitted_by = u.id
              LEFT JOIN categories c ON cl.category_id = c.id
              LEFT JOIN accounts a ON cl.account_id = a.id
              LEFT JOIN users ap ON cl.approved_by = ap.id
              WHERE cl.created_at BETWEEN ? AND ?
              {$claimBranchFilter}
              ORDER BY cl.created_at DESC";
    $clStmt = $pdo->prepare($clSql);
    $clStmt->execute(array_merge([$startDate, $endDate . ' 23:59:59'], $claimBranchParams));
    $claimsList = $clStmt->fetchAll();

    // Summary by status
    $clSumSql = "SELECT cl.status, COUNT(*) AS cnt, SUM(cl.amount) AS total
                 FROM claims cl
                 WHERE cl.created_at BETWEEN ? AND ?
                 {$claimBranchFilter}
                 GROUP BY cl.status";
    $clSumStmt = $pdo->prepare($clSumSql);
    $clSumStmt->execute(array_merge([$startDate, $endDate . ' 23:59:59'], $claimBranchParams));
    $claimSummary = [];
    foreach ($clSumStmt->fetchAll() as $cs) {
        $claimSummary[$cs['status']] = ['count' => (int) $cs['cnt'], 'total' => floatval($cs['total'])];
    }

    // Monthly breakdown
    $clMonthSql = "SELECT cl.status,
                     DATE_FORMAT(cl.created_at, '%Y-%c') AS ym,
                     COUNT(*) AS cnt, SUM(cl.amount) AS total
                   FROM claims cl
                   WHERE cl.created_at BETWEEN ? AND ?
                   {$claimBranchFilter}
                   GROUP BY cl.status, ym
                   ORDER BY ym";
    $clMonthStmt = $pdo->prepare($clMonthSql);
    $clMonthStmt->execute(array_merge([$startDate, $endDate . ' 23:59:59'], $claimBranchParams));
    $claimMonthly = ['pending' => [], 'approved' => [], 'rejected' => []];
    foreach ($clMonthStmt->fetchAll() as $cm) {
        $claimMonthly[$cm['status']][$cm['ym']] = floatval($cm['total']);
    }

    // By category
    $clCatSql = "SELECT c.name AS category_name, cl.status, SUM(cl.amount) AS total
                 FROM claims cl
                 LEFT JOIN categories c ON cl.category_id = c.id
                 WHERE cl.created_at BETWEEN ? AND ?
                 {$claimBranchFilter}
                 GROUP BY c.name, cl.status
                 ORDER BY total DESC";
    $clCatStmt = $pdo->prepare($clCatSql);
    $clCatStmt->execute(array_merge([$startDate, $endDate . ' 23:59:59'], $claimBranchParams));
    $claimCategories = $clCatStmt->fetchAll();
}

// Report title
$reportTypeLabels = [
    'income' => 'Income',
    'expenses' => 'Expenses',
    'overall' => 'Overall',
    'claims' => 'Claims'
];
$typeLabel = $reportTypeLabels[$reportType] ?? 'Report';
$branchLabel = $branchId ? ($branches[0]['name'] ?? '') : '';

// Helper: format value with red color if negative
function fmtVal($val, $highlight = false)
{
    $formatted = number_format($val, 2);
    $cls = '';
    if ($val < 0)
        $cls .= 'text-danger ';
    if ($highlight)
        $cls .= 'overall-highlight ';
    if ($cls)
        return '<span class="' . trim($cls) . '">' . $formatted . '</span>';
    return $formatted;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report - <?php echo $typeLabel; ?> <?php echo $year; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 10px;
            color: #333;
            background: #fff;
            padding: 15px;
        }

        .report-header {
            margin-bottom: 12px;
        }

        .report-title {
            font-size: 13px;
            font-weight: 700;
            color: #1a365d;
            margin-bottom: 2px;
        }

        .report-subtitle {
            font-size: 10px;
            color: #4a5568;
            font-weight: 600;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 9px;
        }

        .report-table th {
            background: #edf2f7;
            border: 1px solid #cbd5e0;
            padding: 4px 6px;
            text-align: right;
            font-weight: 700;
            color: #2d3748;
            white-space: nowrap;
        }

        .report-table th:first-child {
            text-align: left;
            min-width: 140px;
        }

        .report-table td {
            border: 1px solid #e2e8f0;
            padding: 3px 6px;
            text-align: right;
            white-space: nowrap;
        }

        .report-table td:first-child {
            text-align: left;
            font-weight: 500;
        }

        .report-table tr.branch-header td {
            background: #f7fafc;
            font-weight: 700;
            color: #1a365d;
            border-bottom: 2px solid #2b6cb0;
            text-align: left;
        }

        .report-table tr.total-row td {
            font-weight: 700;
            border-top: 2px solid #2d3748;
            background: #f7fafc;
        }

        .report-table tr.subtotal-row td {
            font-weight: 600;
            border-top: 1px solid #a0aec0;
        }

        .report-table tr.balance-row td {
            font-weight: 700;
        }

        .text-right {
            text-align: right;
        }

        .text-left {
            text-align: left !important;
        }

        .text-danger {
            color: #e53e3e;
        }

        .text-success {
            color: #38a169;
        }

        .bg-highlight {
            background: #fffff0;
        }

        .overall-highlight {
            background: #fefcbf !important;
            font-weight: 700 !important;
        }

        .summary-section {
            margin-top: 15px;
            font-size: 10px;
        }

        .summary-section table {
            border-collapse: collapse;
        }

        .summary-section td {
            padding: 2px 10px 2px 0;
        }

        .summary-section .label {
            font-weight: 600;
        }

        .summary-section .value {
            text-align: right;
            font-weight: 700;
        }

        .notes-section {
            margin-top: 15px;
            font-size: 10px;
        }

        .notes-section h4 {
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 4px;
            color: #1a365d;
        }

        .notes-section table td {
            padding: 1px 10px 1px 0;
        }

        .notes-total {
            border-top: 2px solid #333;
            font-weight: 700;
            background: #fefcbf;
        }

        .btn-print {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px;
            background: #2b6cb0;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 15px;
        }

        .btn-print:hover {
            background: #2c5282;
        }

        @media print {
            @page {
                size: landscape;
                margin: 10mm;
            }

            .btn-print,
            .no-print {
                display: none !important;
            }

            body {
                padding: 0;
                font-size: 8px;
            }

            .report-table {
                font-size: 8px;
            }

            .report-table th,
            .report-table td {
                padding: 2px 4px;
            }
        }
    </style>
</head>

<body>

    <button class="btn-print no-print" onclick="window.print()">
        <i>🖨️</i> Print / Save as PDF
    </button>

    <div class="report-header">
        <div class="report-title">
            Laporan Kewangan <?php echo htmlspecialchars($churchName); ?> <?php echo $year; ?>
            (<?php echo $periodLabel; ?>) - <?php echo $typeLabel; ?>
        </div>
        <?php if ($branchLabel): ?>
            <div class="report-subtitle"><?php echo htmlspecialchars($branchLabel); ?></div>
        <?php endif; ?>
    </div>

    <?php if ($reportType === 'income' || $reportType === 'expenses'): ?>
        <!-- ========================================
         DETAILED INCOME / EXPENSES REPORT
         ======================================== -->
        <?php
        // For single-branch detailed reports, show branch name
        if ($branchId && !empty($branches)):
            ?>
            <div class="report-subtitle" style="margin-bottom: 6px; color: #1a365d; font-weight: 700;">
                <?php echo htmlspecialchars($branches[0]['name']); ?>
            </div>
        <?php endif; ?>

        <table class="report-table">
            <thead>
                <tr>
                    <th></th>
                    <?php foreach ($monthColumns as $mc): ?>
                        <th><?php echo $mc['label']; ?></th>
                    <?php endforeach; ?>
                    <th class="overall-highlight">Overall</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // b/f row (placeholder — can be extended)
                $monthTotals = array_fill_keys(array_column($monthColumns, 'key'), 0);
                $grandTotal = 0;

                foreach ($data as $catName => $monthData):
                    $rowTotal = 0;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($catName); ?></td>
                        <?php foreach ($monthColumns as $mc):
                            $key = $mc['key'];
                            $val = $monthData[$key] ?? 0;
                            $rowTotal += $val;
                            $monthTotals[$key] += $val;
                            ?>
                            <td><?php echo fmtVal($val); ?></td>
                        <?php endforeach; ?>
                        <td class="overall-highlight"><?php echo fmtVal($rowTotal); ?></td>
                    </tr>
                    <?php
                    $grandTotal += $rowTotal;
                endforeach;
                ?>
                <!-- Totals row -->
                <tr class="total-row">
                    <td></td>
                    <?php foreach ($monthColumns as $mc): ?>
                        <td><?php echo fmtVal($monthTotals[$mc['key']]); ?></td>
                    <?php endforeach; ?>
                    <td class="overall-highlight"><?php echo fmtVal($grandTotal); ?></td>
                </tr>
            </tbody>
        </table>

    <?php elseif ($reportType === 'overall'): ?>
        <!-- ========================================
         OVERALL INCOME/EXPENSES REPORT
         ======================================== -->
        <table class="report-table">
            <thead>
                <tr>
                    <th></th>
                    <?php foreach ($monthColumns as $mc): ?>
                        <th><?php echo $mc['label']; ?></th>
                    <?php endforeach; ?>
                    <th>Total (RM)</th>
                    <th>Balance (RM)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $grandIncome = array_fill_keys(array_column($monthColumns, 'key'), 0);
                $grandExpense = array_fill_keys(array_column($monthColumns, 'key'), 0);
                $totalAllIncome = 0;
                $totalAllExpenses = 0;

                foreach ($overallData as $branchName => $types):
                    // Find this branch's account balance
                    $branchAccBal = 0;
                    foreach ($branches as $br) {
                        if ($br['name'] === $branchName) {
                            $branchAccBal = $accountBalances[$br['id']] ?? 0;
                            break;
                        }
                    }
                    ?>
                    <!-- Branch Header -->
                    <tr class="branch-header">
                        <td colspan="<?php echo count($monthColumns) + 3; ?>">
                            <?php echo htmlspecialchars($branchName); ?>
                        </td>
                    </tr>
                    <!-- B/F row -->
                    <tr style="background: #f7fafc;">
                        <td style="padding-left: 12px; font-weight: 600; font-style: italic;">B/F (Account Balance)</td>
                        <?php foreach ($monthColumns as $mc): ?>
                            <td></td>
                        <?php endforeach; ?>
                        <td style="font-weight: 600;"><?php echo fmtVal($branchAccBal); ?></td>
                        <td></td>
                    </tr>
                    <?php
                    // Income row
                    $incomeTotal = 0;
                    ?>
                    <tr>
                        <td style="padding-left: 12px;">Income</td>
                        <?php foreach ($monthColumns as $mc):
                            $key = $mc['key'];
                            $val = $types['income'][$key] ?? 0;
                            $incomeTotal += $val;
                            $grandIncome[$key] += $val;
                            ?>
                            <td><?php echo fmtVal($val); ?></td>
                        <?php endforeach; ?>
                        <td><?php echo number_format($incomeTotal, 2); ?></td>
                        <td></td>
                    </tr>
                    <?php
                    // Expense row
                    $expenseTotal = 0;
                    ?>
                    <tr>
                        <td style="padding-left: 12px;">Expenses</td>
                        <?php foreach ($monthColumns as $mc):
                            $key = $mc['key'];
                            $val = $types['expense'][$key] ?? 0;
                            $expenseTotal += $val;
                            $grandExpense[$key] += $val;
                            ?>
                            <td><?php echo fmtVal($val); ?></td>
                        <?php endforeach; ?>
                        <td><?php echo fmtVal($expenseTotal); ?></td>
                        <?php $branchBalance = $branchAccBal + $incomeTotal - $expenseTotal; ?>
                        <td><?php echo fmtVal($branchBalance); ?></td>
                    </tr>
                    <?php
                    $totalAllIncome += $incomeTotal;
                    $totalAllExpenses += $expenseTotal;
                endforeach;
                ?>

                <?php // Always show combined totals and summary ?>
                <?php $totalBalance = $totalAccountBalance + $totalAllIncome - $totalAllExpenses; ?>
                <!-- Combined Totals -->
                <tr class="branch-header">
                    <td colspan="<?php echo count($monthColumns) + 3; ?>">
                        <?php echo htmlspecialchars($churchName); ?>
                    </td>
                </tr>
                <!-- B/F Total row -->
                <tr style="background: #f7fafc;">
                    <td style="padding-left: 12px; font-weight: 600; font-style: italic;">B/F (Account Balance)</td>
                    <?php foreach ($monthColumns as $mc): ?>
                        <td></td>
                    <?php endforeach; ?>
                    <td style="font-weight: 600;"><?php echo fmtVal($totalAccountBalance); ?></td>
                    <td></td>
                </tr>
                <tr class="subtotal-row">
                    <td style="padding-left: 12px;"><?php echo htmlspecialchars($churchName); ?> Income</td>
                    <?php foreach ($monthColumns as $mc): ?>
                        <td><?php echo fmtVal($grandIncome[$mc['key']]); ?></td>
                    <?php endforeach; ?>
                    <td><?php echo fmtVal($totalAllIncome); ?></td>
                    <td></td>
                </tr>
                <tr class="subtotal-row">
                    <td style="padding-left: 12px;"><?php echo htmlspecialchars($churchName); ?> Expenses</td>
                    <?php foreach ($monthColumns as $mc): ?>
                        <td><?php echo fmtVal($grandExpense[$mc['key']]); ?></td>
                    <?php endforeach; ?>
                    <td><?php echo fmtVal($totalAllExpenses); ?></td>
                    <?php $totalBalance = $totalAccountBalance + $totalAllIncome - $totalAllExpenses; ?>
                    <td class="overall-highlight"><?php echo fmtVal($totalBalance); ?></td>
                </tr>
                <!-- Overall balance row -->
                <tr class="balance-row">
                    <td><strong>Overall (RM) =</strong></td>
                    <?php foreach ($monthColumns as $mc):
                        $diff = ($grandIncome[$mc['key']] ?? 0) - ($grandExpense[$mc['key']] ?? 0);
                        ?>
                        <td><?php echo fmtVal($diff); ?></td>
                    <?php endforeach; ?>
                    <td></td>
                    <td class="overall-highlight"><?php echo fmtVal($totalBalance); ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Summary Section -->
        <div class="summary-section">
            <table>
                <tr>
                    <td class="label">Account Balance B/F (RM) =</td>
                    <td class="value"><?php echo number_format($totalAccountBalance, 2); ?></td>
                </tr>
                <tr>
                    <td class="label">Total Income (RM) =</td>
                    <td class="value"><?php echo number_format($totalAllIncome, 2); ?></td>
                </tr>
                <tr>
                    <td class="label">Total Expenses (RM) =</td>
                    <td class="value"><?php echo number_format($totalAllExpenses, 2); ?></td>
                </tr>
                <?php $deficit = $totalAccountBalance + $totalAllIncome - $totalAllExpenses; ?>
                <tr>
                    <td class="label <?php echo $deficit < 0 ? 'text-danger' : ''; ?>">
                        Total <?php echo $deficit >= 0 ? 'Surplus' : 'Deficit'; ?> (RM) =
                    </td>
                    <td class="value <?php echo $deficit < 0 ? 'text-danger' : ''; ?>">
                        <?php echo number_format($deficit, 2); ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Notes Section: Category Breakdown -->
        <?php
        $incomeCats = array_filter($categoryTotals, fn($r) => $r['type'] === 'income');
        $expenseCats = array_filter($categoryTotals, fn($r) => $r['type'] === 'expense');
        ?>
        <?php if (!empty($incomeCats)): ?>
            <div class="notes-section">
                <h4>Notes Income Year <?php echo $year; ?> (RM) :</h4>
                <table>
                    <?php
                    $notesIncTotal = 0;
                    foreach ($incomeCats as $cat):
                        $notesIncTotal += $cat['total'];
                        ?>
                        <tr>
                            <td>Total <?php echo htmlspecialchars($cat['category_name'] ?: 'Uncategorized'); ?> =</td>
                            <td class="text-right"><?php echo number_format($cat['total'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="notes-total">
                        <td><strong>TOTAL (RM) =</strong></td>
                        <td class="text-right overall-highlight">
                            <strong><?php echo number_format($notesIncTotal, 2); ?></strong>
                        </td>
                    </tr>
                </table>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <?php if ($reportType === 'claims'): ?>
        <!-- ========================================
         CLAIMS REPORT
         ======================================== -->
        <?php if ($branchId && !empty($branches)): ?>
            <div class="report-subtitle" style="margin-bottom: 6px; color: #1a365d; font-weight: 700;">
                <?php echo htmlspecialchars($branches[0]['name']); ?>
            </div>
        <?php endif; ?>

        <!-- Claims Summary -->
        <div style="margin-bottom: 12px;">
            <table class="report-table" style="width: auto;">
                <thead>
                    <tr>
                        <th class="text-left">Status</th>
                        <th>Count</th>
                        <th>Total (RM)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $statuses = ['pending', 'approved', 'rejected'];
                    $statusLabels = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
                    $statusColors = ['pending' => '#d69e2e', 'approved' => '#38a169', 'rejected' => '#e53e3e'];
                    $grandClaimCount = 0;
                    $grandClaimTotal = 0;
                    foreach ($statuses as $st):
                        $cnt = $claimSummary[$st]['count'] ?? 0;
                        $tot = $claimSummary[$st]['total'] ?? 0;
                        $grandClaimCount += $cnt;
                        $grandClaimTotal += $tot;
                    ?>
                        <tr>
                            <td style="color: <?php echo $statusColors[$st]; ?>; font-weight: 600;">
                                <?php echo $statusLabels[$st]; ?>
                            </td>
                            <td><?php echo $cnt; ?></td>
                            <td><?php echo number_format($tot, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td>Total</td>
                        <td><?php echo $grandClaimCount; ?></td>
                        <td><?php echo number_format($grandClaimTotal, 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Monthly Breakdown -->
        <?php if (count($monthColumns) > 1): ?>
        <div style="margin-bottom: 12px;">
            <div style="font-size: 11px; font-weight: 700; color: #1a365d; margin-bottom: 4px;">Claims by Month (RM)</div>
            <table class="report-table">
                <thead>
                    <tr>
                        <th class="text-left">Status</th>
                        <?php foreach ($monthColumns as $mc): ?>
                            <th><?php echo $mc['label']; ?></th>
                        <?php endforeach; ?>
                        <th class="overall-highlight">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $monthGrandTotals = array_fill_keys(array_column($monthColumns, 'key'), 0);
                    foreach ($statuses as $st):
                        $rowTotal = 0;
                    ?>
                        <tr>
                            <td style="color: <?php echo $statusColors[$st]; ?>; font-weight: 600;">
                                <?php echo $statusLabels[$st]; ?>
                            </td>
                            <?php foreach ($monthColumns as $mc):
                                $key = $mc['key'];
                                $val = $claimMonthly[$st][$key] ?? 0;
                                $rowTotal += $val;
                                $monthGrandTotals[$key] += $val;
                            ?>
                                <td><?php echo fmtVal($val); ?></td>
                            <?php endforeach; ?>
                            <td class="overall-highlight"><?php echo fmtVal($rowTotal); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td>Total</td>
                        <?php foreach ($monthColumns as $mc): ?>
                            <td><?php echo fmtVal($monthGrandTotals[$mc['key']]); ?></td>
                        <?php endforeach; ?>
                        <td class="overall-highlight"><?php echo fmtVal($grandClaimTotal); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Detailed Claims List -->
        <div style="font-size: 11px; font-weight: 700; color: #1a365d; margin-bottom: 4px;">Claims Detail</div>
        <table class="report-table">
            <thead>
                <tr>
                    <th class="text-left" style="min-width: 60px;">Date</th>
                    <th class="text-left">Submitted By</th>
                    <th class="text-left">Title</th>
                    <th class="text-left">Category</th>
                    <th>Amount (RM)</th>
                    <th>Status</th>
                    <th class="text-left">Approved By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($claimsList)): ?>
                    <tr><td colspan="7" style="text-align: center; color: #a0aec0;">No claims found for this period</td></tr>
                <?php else: ?>
                    <?php foreach ($claimsList as $cl): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($cl['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($cl['submitter_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($cl['title'] ?: ($cl['description'] ? mb_substr($cl['description'], 0, 40) . '...' : '-')); ?></td>
                            <td><?php echo htmlspecialchars($cl['category_name'] ?? '-'); ?></td>
                            <td><?php echo number_format($cl['amount'], 2); ?></td>
                            <td style="color: <?php echo $statusColors[$cl['status']] ?? '#333'; ?>; font-weight: 600;">
                                <?php echo ucfirst($cl['status']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($cl['approver_name'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Category Breakdown -->
        <?php if (!empty($claimCategories)): ?>
            <div class="notes-section">
                <h4>Claims by Category (RM) :</h4>
                <table>
                    <?php
                    $catTotals = [];
                    foreach ($claimCategories as $cc) {
                        $name = $cc['category_name'] ?: 'Uncategorized';
                        if (!isset($catTotals[$name])) $catTotals[$name] = 0;
                        $catTotals[$name] += floatval($cc['total']);
                    }
                    arsort($catTotals);
                    $catGrand = 0;
                    foreach ($catTotals as $name => $total):
                        $catGrand += $total;
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($name); ?> =</td>
                            <td class="text-right"><?php echo number_format($total, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="notes-total">
                        <td><strong>TOTAL (RM) =</strong></td>
                        <td class="text-right overall-highlight">
                            <strong><?php echo number_format($catGrand, 2); ?></strong>
                        </td>
                    </tr>
                </table>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</body>

</html>