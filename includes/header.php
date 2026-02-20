<?php
/**
 * KiTAcc - Header Partial
 * Top navigation with search, notifications, and user menu
 */

$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$churchName = getChurchName();
$isProduction = (getenv('APP_ENV') === 'production');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo htmlspecialchars($page_title ?? getSetting('app_name', 'KiTAcc')); ?>
    </title>
    <meta name="csrf-token" content="<?php echo generateCsrfToken(); ?>">
    <link rel="icon" type="image/svg+xml" href="img/logo-icon-purple.svg">

    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Vanilla CSS -->
    <?php
    $cssFile = $isProduction && file_exists(__DIR__ . '/../css/styles.min.css') ? 'css/styles.min.css' : 'css/styles.css';
    $cssPath = __DIR__ . '/../' . $cssFile;
    ?>
    <link rel="stylesheet" href="<?php echo $cssFile; ?>?v=<?php echo filemtime($cssPath); ?>">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body data-session-timeout="<?php echo (int) getSetting('session_timeout', '30'); ?>">
    <div class="app-wrapper" id="appWrapper">
        <?php include __DIR__ . '/sidebar.php'; ?>

        <div class="main-content">
            <!-- Top Header -->
            <header class="header">
                <div class="header-left">
                    <button class="sidebar-toggle" id="sidebarToggle" title="Toggle Sidebar"
                        aria-label="Toggle Sidebar">
                        <i class="fas fa-bars" aria-hidden="true"></i>
                    </button>

                    <div class="header-search">
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <input type="text" placeholder="Search..." id="globalSearch" aria-label="Search">
                    </div>
                </div>

                <div class="header-right">
                    <?php if (isFundMode()): ?>
                        <span class="mode-indicator" title="Fund Accounting Mode">
                            <i class="fas fa-circle" aria-hidden="true"></i> Fund Mode
                        </span>
                    <?php endif; ?>

                    <div class="user-dropdown" id="userDropdown">
                        <div class="header-user">
                            <div class="avatar-initial">
                                <?php echo htmlspecialchars($currentUser['initials']); ?>
                            </div>
                            <div class="header-user-info">
                                <span class="header-user-name">
                                    <?php echo htmlspecialchars($currentUser['name']); ?>
                                </span>
                                <span class="header-user-role">
                                    <?php echo htmlspecialchars($currentUser['role_label']); ?>
                                </span>
                            </div>
                            <i class="fas fa-chevron-down" aria-hidden="true" style="color: var(--gray-400); font-size: 0.75rem;"></i>
                        </div>

                        <div class="user-dropdown-menu">
                            <a href="profile.php"><i class="fas fa-user" aria-hidden="true"></i> My Profile</a>
                            <?php if (hasRole(ROLE_SUPERADMIN)): ?>
                                <a href="settings.php"><i class="fas fa-cog" aria-hidden="true"></i> Settings</a>
                            <?php endif; ?>
                            <div class="user-dropdown-divider"></div>
                            <a href="login.php?logout=1" style="color: var(--gray-500);"><i
                                    class="fas fa-sign-out-alt" aria-hidden="true"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="page-content">