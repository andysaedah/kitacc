<?php
/**
 * KiTAcc - Sidebar Navigation
 * Role-based collapsible navigation
 */

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$user = getCurrentUser();
$userRole = $user['role'] ?? '';
?>
<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="dashboard.php" class="sidebar-logo" aria-label="KiTAcc Home">
            <div class="sidebar-logo-icon">
                <i class="fas fa-box-heart" aria-hidden="true"></i>
            </div>
            <span class="sidebar-logo-text"><?php echo htmlspecialchars(getSetting('app_name', 'KiTAcc')); ?></span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <?php if (hasRole(ROLE_BRANCH_FINANCE)): ?>
            <div class="nav-section">
                <div class="nav-section-title">Main</div>

                <a href="dashboard.php" class="nav-item <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>" aria-label="Dashboard">
                    <i class="fas fa-th-large" aria-hidden="true"></i>
                    <span class="nav-item-text">Dashboard</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- Finance -->
        <?php if (hasRole(ROLE_BRANCH_FINANCE)): ?>
            <div class="nav-section">
                <div class="nav-section-title">Finance</div>

                <?php if (hasRole(ROLE_BRANCH_FINANCE)): ?>
                    <a href="income.php" class="nav-item <?php echo $currentPage === 'income' ? 'active' : ''; ?>" aria-label="Income">
                        <i class="fas fa-arrow-down" aria-hidden="true"></i>
                        <span class="nav-item-text">Income</span>
                    </a>

                    <a href="expenses.php" class="nav-item <?php echo $currentPage === 'expenses' ? 'active' : ''; ?>" aria-label="Expenses">
                        <i class="fas fa-arrow-up" aria-hidden="true"></i>
                        <span class="nav-item-text">Expenses</span>
                    </a>
                <?php endif; ?>

                <a href="transactions.php" class="nav-item <?php echo $currentPage === 'transactions' ? 'active' : ''; ?>" aria-label="Transactions">
                    <i class="fas fa-exchange-alt" aria-hidden="true"></i>
                    <span class="nav-item-text">Transactions</span>
                </a>

                <?php if (hasRole(ROLE_BRANCH_FINANCE)): ?>
                    <a href="reports.php" class="nav-item <?php echo $currentPage === 'reports' ? 'active' : ''; ?>" aria-label="Reports">
                        <i class="fas fa-file-alt" aria-hidden="true"></i>
                        <span class="nav-item-text">Reports</span>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Claims -->
        <?php if (hasRole(ROLE_USER)): ?>
            <div class="nav-section">
                <div class="nav-section-title">Claims</div>

                <a href="claim_submit.php" class="nav-item <?php echo $currentPage === 'claim_submit' ? 'active' : ''; ?>" aria-label="Submit Claim">
                    <i class="fas fa-file-upload" aria-hidden="true"></i>
                    <span class="nav-item-text">Submit Claim</span>
                </a>

                <a href="claims.php" class="nav-item <?php echo $currentPage === 'claims' ? 'active' : ''; ?>" aria-label="My Claims">
                    <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                    <span class="nav-item-text">My Claims</span>
                </a>

                <?php if (hasRole(ROLE_BRANCH_FINANCE)): ?>
                    <a href="claim_review.php" class="nav-item <?php echo $currentPage === 'claim_review' ? 'active' : ''; ?>" aria-label="Review Claims">
                        <i class="fas fa-clipboard-check" aria-hidden="true"></i>
                        <span class="nav-item-text">Review Claims</span>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Management -->
        <?php if (hasRole(ROLE_BRANCH_FINANCE)): ?>
            <div class="nav-section">
                <div class="nav-section-title">Management</div>

                <a href="accounts.php" class="nav-item <?php echo $currentPage === 'accounts' ? 'active' : ''; ?>" aria-label="Accounts">
                    <i class="fas fa-university" aria-hidden="true"></i>
                    <span class="nav-item-text">Accounts</span>
                </a>

                <?php if (isFundMode()): ?>
                    <a href="funds.php" class="nav-item <?php echo $currentPage === 'funds' ? 'active' : ''; ?>" aria-label="Funds">
                        <i class="fas fa-piggy-bank" aria-hidden="true"></i>
                        <span class="nav-item-text">Funds</span>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Logs (Superadmin only) -->
        <?php if (hasRole(ROLE_SUPERADMIN)): ?>
            <div class="nav-section">
                <div class="nav-section-title">Logs</div>

                <a href="audit_log.php" class="nav-item <?php echo $currentPage === 'audit_log' ? 'active' : ''; ?>" aria-label="Audit Log">
                    <i class="fas fa-history" aria-hidden="true"></i>
                    <span class="nav-item-text">Audit Log</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- Configuration (Superadmin only) -->
        <?php if (hasRole(ROLE_SUPERADMIN)): ?>
            <div class="nav-section">
                <div class="nav-section-title">Configuration</div>

                <a href="settings.php" class="nav-item <?php echo $currentPage === 'settings' ? 'active' : ''; ?>" aria-label="System Settings">
                    <i class="fas fa-cogs" aria-hidden="true"></i>
                    <span class="nav-item-text">System Settings</span>
                </a>

                <a href="branches.php" class="nav-item <?php echo $currentPage === 'branches' ? 'active' : ''; ?>" aria-label="Branches">
                    <i class="fas fa-code-branch" aria-hidden="true"></i>
                    <span class="nav-item-text">Branches</span>
                </a>

                <a href="users.php" class="nav-item <?php echo $currentPage === 'users' ? 'active' : ''; ?>" aria-label="Users">
                    <i class="fas fa-users" aria-hidden="true"></i>
                    <span class="nav-item-text">Users</span>
                </a>

                <a href="categories.php" class="nav-item <?php echo $currentPage === 'categories' ? 'active' : ''; ?>" aria-label="Categories">
                    <i class="fas fa-tags" aria-hidden="true"></i>
                    <span class="nav-item-text">Categories</span>
                </a>

                <a href="api_integration.php"
                    class="nav-item <?php echo $currentPage === 'api_integration' ? 'active' : ''; ?>" aria-label="API Integration">
                    <i class="fas fa-plug" aria-hidden="true"></i>
                    <span class="nav-item-text">API Integration</span>
                </a>
            </div>
        <?php endif; ?>

    </nav>

    <div class="sidebar-footer">
        <a href="login.php?logout=1" class="nav-item" style="color: rgba(255,255,255,0.7);" aria-label="Logout">
            <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
            <span class="nav-item-text">Logout</span>
        </a>
    </div>
</aside>

<!-- Mobile Overlay -->
<div class="mobile-overlay" id="mobileOverlay"></div>