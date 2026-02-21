<?php
/**
 * KiTAcc - 403 Access Denied Page
 * Styled error page shown when a user lacks permission
 */
require_once __DIR__ . '/includes/config.php';

http_response_code(403);

$currentUser = getCurrentUser();
$appName = getSetting('app_name', 'KiTAcc');
$churchName = getChurchName();
$isProduction = (getenv('APP_ENV') === 'production');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Access Denied | <?php echo htmlspecialchars($appName); ?></title>

    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <?php
    $cssFile = $isProduction && file_exists(__DIR__ . '/css/styles.min.css') ? 'css/styles.min.css' : 'css/styles.css';
    $cssPath = __DIR__ . '/' . $cssFile;
    ?>
    <link rel="stylesheet" href="<?php echo $cssFile; ?>?v=<?php echo filemtime($cssPath); ?>">

    <style>
        body {
            background: var(--gray-50);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .error-container {
            text-align: center;
            max-width: 480px;
            padding: 2.5rem;
        }

        .error-icon {
            width: 100px;
            height: 100px;
            background: var(--danger-light, #FEE2E2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .error-icon i {
            font-size: 2.5rem;
            color: var(--danger, #EF4444);
        }

        .error-code {
            font-size: 4rem;
            font-weight: 700;
            color: var(--gray-800, #1F2937);
            margin: 0 0 0.25rem;
            line-height: 1;
        }

        .error-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-700, #374051);
            margin: 0 0 0.75rem;
        }

        .error-message {
            font-size: 1rem;
            color: var(--gray-500, #6B6F80);
            margin: 0 0 2rem;
            line-height: 1.6;
        }

        .error-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .error-actions a {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-primary-err {
            background: var(--primary, #6C2BD9);
            color: #fff;
        }

        .btn-primary-err:hover {
            background: var(--primary-dark, #5B21B6);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(108, 43, 217, 0.3);
        }

        .btn-secondary-err {
            background: var(--gray-100, #F1F0F7);
            color: var(--gray-700, #374051);
        }

        .btn-secondary-err:hover {
            background: var(--gray-200, #E5E4EB);
            transform: translateY(-1px);
        }

        .error-footer {
            margin-top: 3rem;
            font-size: 0.8rem;
            color: var(--gray-400, #9CA0AF);
        }

        .error-user-info {
            margin-top: 1.5rem;
            padding: 0.75rem 1.25rem;
            background: var(--gray-100, #F1F0F7);
            border-radius: 0.5rem;
            font-size: 0.8125rem;
            color: var(--gray-500, #6B6F80);
        }
    </style>
</head>

<body>
    <div class="error-container">
        <div class="error-icon">
            <i class="fas fa-lock" aria-hidden="true"></i>
        </div>

        <p class="error-code">403</p>
        <h1 class="error-title">Access Denied</h1>
        <p class="error-message">
            You do not have permission to access this page.
            Please contact your administrator if you believe this is an error.
        </p>

        <?php if ($currentUser): ?>
            <div class="error-user-info">
                <i class="fas fa-user-circle" aria-hidden="true"></i>
                Logged in as <strong><?php echo htmlspecialchars($currentUser['name']); ?></strong>
                (<?php echo htmlspecialchars($currentUser['role_label']); ?>)
            </div>
        <?php endif; ?>

        <div class="error-actions" style="margin-top: 1.5rem;">
            <a href="dashboard.php" class="btn-primary-err">
                <i class="fas fa-home" aria-hidden="true"></i> Go to Dashboard
            </a>
            <a href="javascript:history.back()" class="btn-secondary-err">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Go Back
            </a>
        </div>

        <div class="error-footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($churchName); ?>. <?php echo htmlspecialchars($appName); ?></p>
        </div>
    </div>
</body>

</html>
