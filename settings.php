<?php
/**
 * KiTAcc - System Settings (Superadmin only)
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();
requireRole(ROLE_SUPERADMIN);

$page_title = 'Settings - KiTAcc';

// Current settings
$appName = getSetting('app_name', 'KiTAcc');
$appTagline = getSetting('app_tagline', 'Church Account Made Easy');
$churchName = getSetting('church_name', 'My Church');
$timezone = getSetting('timezone', 'Asia/Kuala_Lumpur');
$accountingMode = getSetting('accounting_mode', 'simple');
$currencySymbol = getSetting('currency_symbol', 'RM');
$sessionTimeout = getSetting('session_timeout', '30');

// Common timezones
$timezones = [
    'Asia/Kuala_Lumpur' => 'Malaysia (UTC+8)',
    'Asia/Singapore' => 'Singapore (UTC+8)',
    'Asia/Jakarta' => 'Indonesia - WIB (UTC+7)',
    'Asia/Makassar' => 'Indonesia - WITA (UTC+8)',
    'Asia/Jayapura' => 'Indonesia - WIT (UTC+9)',
    'Asia/Manila' => 'Philippines (UTC+8)',
    'Asia/Bangkok' => 'Thailand (UTC+7)',
    'Asia/Taipei' => 'Taiwan (UTC+8)',
    'Asia/Hong_Kong' => 'Hong Kong (UTC+8)',
    'Asia/Tokyo' => 'Japan (UTC+9)',
    'Asia/Seoul' => 'South Korea (UTC+9)',
    'Asia/Kolkata' => 'India (UTC+5:30)',
    'Australia/Sydney' => 'Australia - Sydney (UTC+10/11)',
    'Pacific/Auckland' => 'New Zealand (UTC+12/13)',
    'Europe/London' => 'United Kingdom (UTC+0/1)',
    'America/New_York' => 'US - Eastern (UTC-5/-4)',
    'America/Chicago' => 'US - Central (UTC-6/-5)',
    'America/Los_Angeles' => 'US - Pacific (UTC-8/-7)',
];

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-between align-center mb-6">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--gray-800);">System Settings</h1>
        <p class="text-muted">Manage your application configuration</p>
    </div>
</div>

<div class="card" style="max-width: 700px;">
    <div class="card-body">
        <form id="settingsForm">
            <!-- Application Settings -->
            <div style="margin-bottom: 1.5rem;">
                <h3 style="font-size: 1rem; font-weight: 600; color: var(--gray-700); margin-bottom: 1rem;">
                    <i class="fas fa-desktop" style="color: var(--primary); margin-right: 0.5rem;"></i>Application
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label required">App Name</label>
                        <input type="text" name="app_name" class="form-control"
                            value="<?php echo htmlspecialchars($appName); ?>" required>
                        <span class="form-help">Displayed in sidebar & footer</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tagline</label>
                        <input type="text" name="app_tagline" class="form-control"
                            value="<?php echo htmlspecialchars($appTagline); ?>">
                        <span class="form-help">Shown in footer & login page</span>
                    </div>
                </div>
            </div>

            <hr style="border: none; border-top: 1px solid var(--gray-200); margin: 1.5rem 0;">

            <!-- Church Settings -->
            <div style="margin-bottom: 1.5rem;">
                <h3 style="font-size: 1rem; font-weight: 600; color: var(--gray-700); margin-bottom: 1rem;">
                    <i class="fas fa-church" style="color: var(--primary); margin-right: 0.5rem;"></i>Church
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label required">Church Name</label>
                        <input type="text" name="church_name" class="form-control"
                            value="<?php echo htmlspecialchars($churchName); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Currency Symbol</label>
                        <input type="text" name="currency_symbol" class="form-control"
                            value="<?php echo htmlspecialchars($currencySymbol); ?>" style="max-width: 100px;">
                    </div>
                </div>
            </div>

            <hr style="border: none; border-top: 1px solid var(--gray-200); margin: 1.5rem 0;">

            <!-- System Settings -->
            <div style="margin-bottom: 1.5rem;">
                <h3 style="font-size: 1rem; font-weight: 600; color: var(--gray-700); margin-bottom: 1rem;">
                    <i class="fas fa-cogs" style="color: var(--primary); margin-right: 0.5rem;"></i>System
                </h3>

                <div class="form-group">
                    <label class="form-label required">Timezone</label>
                    <select name="timezone" class="form-control" style="max-width: 350px;">
                        <?php foreach ($timezones as $tz => $label): ?>
                            <option value="<?php echo $tz; ?>" <?php echo $timezone === $tz ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label required">Session Timeout (minutes)</label>
                    <select name="session_timeout" class="form-control" style="max-width: 200px;">
                        <?php
                        $timeoutOptions = [15 => '15 minutes', 30 => '30 minutes (recommended)', 60 => '1 hour', 120 => '2 hours'];
                        foreach ($timeoutOptions as $val => $label):
                        ?>
                            <option value="<?php echo $val; ?>" <?php echo $sessionTimeout == $val ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-help">Auto-logout after inactivity. OWASP recommends 15-30 min for financial apps.</span>
                </div>

                <div class="form-group mt-4">
                    <label class="form-label required">Accounting Mode</label>
                    <div class="d-flex gap-4 mt-2">
                        <label class="form-check">
                            <input type="radio" name="accounting_mode" value="simple" class="form-check-input" <?php echo $accountingMode === 'simple' ? 'checked' : ''; ?>>
                            <span class="form-check-label"><strong>Simple Mode</strong><br><small
                                    class="text-muted">Track by Account + Category only</small></span>
                        </label>
                        <label class="form-check">
                            <input type="radio" name="accounting_mode" value="fund" class="form-check-input" <?php echo $accountingMode === 'fund' ? 'checked' : ''; ?>>
                            <span class="form-check-label"><strong>Fund Accounting</strong><br><small
                                    class="text-muted">Track by Account + Category + Fund</small></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="alert alert-info mt-4">
                <i class="fas fa-info-circle alert-icon"></i>
                <div class="alert-content">
                    <strong>Note:</strong> Switching between modes will not delete any data. Fund fields will simply be
                    hidden or shown.
                </div>
            </div>

            <button type="button" class="btn btn-primary mt-4" onclick="saveSettings()"><i class="fas fa-save"></i> Save
                Settings</button>
        </form>
    </div>
</div>

<?php
$page_scripts = <<<'SCRIPT'
<script>
    function saveSettings() {
        const data = KiTAcc.serializeForm(document.getElementById('settingsForm'));
        data.action = 'update';
        KiTAcc.post('api/settings.php', data, function(res) {
            if (res.success) { KiTAcc.toast('Settings saved!', 'success'); setTimeout(() => location.reload(), 800); }
            else KiTAcc.toast(res.message || 'Error.', 'error');
        });
    }
</script>
SCRIPT;
include __DIR__ . '/includes/footer.php';
?>