<?php
/**
 * KiTAcc - API Integration Settings (Superadmin only)
 * Configure SMTP2Go email engine
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();
requireRole(ROLE_SUPERADMIN);

$page_title = 'API Integration - KiTAcc';

// Current SMTP settings
$smtpApiKey = getSetting('smtp_api_key', '');
$smtpSenderEmail = getSetting('smtp_sender_email', '');
$smtpSenderName = getSetting('smtp_sender_name', '');

// Handle test email (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAjax()) {
    header('Content-Type: application/json');
    validateCsrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'save_smtp') {
        $settings = [
            'smtp_api_key' => trim($_POST['smtp_api_key'] ?? ''),
            'smtp_sender_email' => trim($_POST['smtp_sender_email'] ?? ''),
            'smtp_sender_name' => trim($_POST['smtp_sender_name'] ?? ''),
        ];

        $pdo = db();
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }
        unset($_SESSION['settings_cache']);

        auditLog('smtp_settings_updated', 'settings', null, null, $settings);
        echo json_encode(['success' => true, 'message' => 'SMTP settings saved.']);
        exit;
    }

    if ($action === 'test_email') {
        $testTo = trim($_POST['test_email'] ?? '');
        if (empty($testTo) || !filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
            exit;
        }

        $appName = getSetting('app_name', 'KiTAcc');
        $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;font-family:'Inter',Arial,sans-serif;background:#f4f5f7;">
<div style="max-width:520px;margin:40px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);">
    <div style="background:linear-gradient(135deg,#6c3fa0,#8b5cf6);padding:32px 24px;text-align:center;">
        <h1 style="color:#fff;margin:0;font-size:24px;">{$appName}</h1>
        <p style="color:rgba(255,255,255,0.8);margin:6px 0 0;font-size:13px;">Email Configuration Test</p>
    </div>
    <div style="padding:32px 24px;text-align:center;">
        <div style="font-size:48px;margin-bottom:16px;">✅</div>
        <h2 style="color:#374151;font-size:18px;margin:0 0 8px;">It works!</h2>
        <p style="color:#6b7280;font-size:14px;line-height:1.6;margin:0;">
            Your SMTP2Go integration is configured correctly. {$appName} can now send emails for password resets and other notifications.
        </p>
    </div>
</div>
</body>
</html>
HTML;

        $result = sendMail($testTo, "$appName - Test Email", $htmlBody);
        echo json_encode($result);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-between align-center mb-6">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--gray-800);">API Integration</h1>
        <p class="text-muted">Configure external service integrations</p>
    </div>
</div>

<!-- SMTP2Go Settings -->
<div class="card" style="max-width: 700px;">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-envelope"
                style="color: var(--primary); margin-right: 0.5rem;"></i>SMTP2Go Email Engine</h3>
    </div>
    <div class="card-body">
        <div class="alert alert-info mb-4">
            <i class="fas fa-info-circle alert-icon"></i>
            <div class="alert-content">
                <strong>SMTP2Go</strong> is used for sending password reset emails and system notifications.
                Get your API key from <a href="https://app.smtp2go.com/settings/apikeys" target="_blank"
                    style="color: var(--primary); font-weight: 600;">smtp2go.com</a>.
            </div>
        </div>

        <form id="smtpForm">
            <div class="form-group">
                <label class="form-label required">API Key</label>
                <div class="input-group">
                    <input type="password" id="smtpApiKey" name="smtp_api_key" class="form-control has-icon-right"
                        value="<?php echo htmlspecialchars($smtpApiKey); ?>"
                        placeholder="api-XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX" required>
                    <button type="button" class="input-group-icon-right toggle-password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <span class="form-help">Your SMTP2Go API key (starts with "api-")</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label required">Sender Email</label>
                    <input type="email" name="smtp_sender_email" class="form-control"
                        value="<?php echo htmlspecialchars($smtpSenderEmail); ?>" placeholder="noreply@yourdomain.com"
                        required>
                    <span class="form-help">Must be verified in SMTP2Go</span>
                </div>

                <div class="form-group">
                    <label class="form-label">Sender Name</label>
                    <input type="text" name="smtp_sender_name" class="form-control"
                        value="<?php echo htmlspecialchars($smtpSenderName); ?>"
                        placeholder="<?php echo htmlspecialchars(getSetting('app_name', 'KiTAcc')); ?>">
                    <span class="form-help">Displayed as "from" name</span>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary" onclick="saveSmtp()">
                    <i class="fas fa-save"></i> Save Settings
                </button>
            </div>
        </form>

        <hr style="border: none; border-top: 1px solid var(--gray-200); margin: 1.5rem 0;">

        <!-- Test Email -->
        <h4 style="font-size: 0.875rem; font-weight: 600; color: var(--gray-700); margin-bottom: 0.75rem;">
            <i class="fas fa-paper-plane" style="color: var(--primary); margin-right: 0.5rem;"></i>Send Test Email
        </h4>
        <div class="d-flex gap-2 align-center flex-wrap">
            <div class="form-group m-0" style="flex: 1; min-width: 200px;">
                <input type="email" id="testEmail" class="form-control" placeholder="test@example.com">
            </div>
            <button type="button" class="btn btn-outline" onclick="sendTest()" id="testBtn">
                <i class="fas fa-paper-plane"></i> Send Test
            </button>
        </div>
        <div id="testResult" style="margin-top: 0.75rem; display: none;"></div>
    </div>
</div>

<?php
$page_scripts = <<<'SCRIPT'
<script>
    function saveSmtp() {
        const data = KiTAcc.serializeForm(document.getElementById('smtpForm'));
        data.action = 'save_smtp';
        KiTAcc.post('api_integration.php', data, function(res) {
            if (res.success) KiTAcc.toast(res.message, 'success');
            else KiTAcc.toast(res.message || 'Error saving settings.', 'error');
        });
    }

    function sendTest() {
        const email = document.getElementById('testEmail').value.trim();
        if (!email) { KiTAcc.toast('Enter an email address.', 'error'); return; }

        const btn = document.getElementById('testBtn');
        const result = document.getElementById('testResult');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        result.style.display = 'none';

        KiTAcc.post('api_integration.php', { action: 'test_email', test_email: email }, function(res) {
            result.style.display = 'block';
            if (res.success) {
                result.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle alert-icon"></i><div class="alert-content">' + res.message + '</div></div>';
            } else {
                result.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle alert-icon"></i><div class="alert-content">' + res.message + '</div></div>';
            }
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Test';
        }, function() {
            result.style.display = 'block';
            result.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle alert-icon"></i><div class="alert-content">Connection error.</div></div>';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Test';
        });
    }
</script>
SCRIPT;
include __DIR__ . '/includes/footer.php';
?>