<?php
/**
 * KiTAcc - Bottom Navigation (Mobile)
 * Fixed bottom bar with role-appropriate navigation items
 */
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<nav class="bottom-nav" aria-label="Mobile navigation">
    <div class="bottom-nav-items">
        <a href="dashboard.php" class="bottom-nav-item <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>"
            aria-label="Dashboard">
            <i class="fas fa-th-large" aria-hidden="true"></i>
            <span>Dashboard</span>
        </a>
        <?php if (hasRole(ROLE_BRANCH_FINANCE)): ?>
            <a href="income.php" class="bottom-nav-item <?php echo $currentPage === 'income' ? 'active' : ''; ?>"
                aria-label="Income">
                <i class="fas fa-arrow-down" aria-hidden="true"></i>
                <span>Income</span>
            </a>
            <a href="expenses.php" class="bottom-nav-item <?php echo $currentPage === 'expenses' ? 'active' : ''; ?>"
                aria-label="Expenses">
                <i class="fas fa-arrow-up" aria-hidden="true"></i>
                <span>Expenses</span>
            </a>
        <?php else: ?>
            <a href="claim_submit.php"
                class="bottom-nav-item <?php echo $currentPage === 'claim_submit' ? 'active' : ''; ?>"
                aria-label="Submit Claim">
                <i class="fas fa-file-upload" aria-hidden="true"></i>
                <span>Claim</span>
            </a>
            <a href="claims.php" class="bottom-nav-item <?php echo $currentPage === 'claims' ? 'active' : ''; ?>"
                aria-label="My Claims">
                <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                <span>My Claims</span>
            </a>
        <?php endif; ?>
        <a href="#" class="bottom-nav-item" aria-label="More options"
            onclick="document.getElementById('sidebar').classList.toggle('mobile-open'); return false;">
            <i class="fas fa-ellipsis-h" aria-hidden="true"></i>
            <span>More</span>
        </a>
    </div>
</nav>