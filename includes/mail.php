<?php
/**
 * KiTAcc - Mail Helper (SMTP2Go HTTP API)
 * Zero-dependency email sending via SMTP2Go's REST API
 */

/**
 * Send email via SMTP2Go API
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $htmlBody HTML email body
 * @param string|null $toName Recipient name (optional)
 * @return array ['success' => bool, 'message' => string]
 */
function sendMail(string $to, string $subject, string $htmlBody, ?string $toName = null): array
{
    $apiKey = getSetting('smtp_api_key', '');
    $senderEmail = getSetting('smtp_sender_email', '');
    $senderName = getSetting('smtp_sender_name', getSetting('app_name', 'KiTAcc'));

    if (empty($apiKey) || empty($senderEmail)) {
        return ['success' => false, 'message' => 'SMTP2Go is not configured. Go to API Integration settings.'];
    }

    $payload = [
        'api_key' => $apiKey,
        'to' => [$toName ? "$toName <$to>" : $to],
        'sender' => "$senderName <$senderEmail>",
        'subject' => $subject,
        'html_body' => $htmlBody,
    ];

    $ch = curl_init('https://api.smtp2go.com/v3/email/send');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'message' => 'Connection failed: ' . $error];
    }

    $result = json_decode($response, true);

    if ($httpCode === 200 && isset($result['data']['succeeded']) && $result['data']['succeeded'] > 0) {
        return ['success' => true, 'message' => 'Email sent successfully.'];
    }

    $errorMsg = $result['data']['error'] ?? $result['data']['failures'][0] ?? 'Unknown error from SMTP2Go.';
    return ['success' => false, 'message' => $errorMsg];
}

/**
 * Generate a password reset token for a user
 * 
 * @param int $userId
 * @return array ['success' => bool, 'token' => string|null, 'message' => string]
 */
function generatePasswordResetToken(int $userId): array
{
    try {
        $pdo = db();

        // Rate limit: max 1 token per user per 10 minutes
        $stmt = $pdo->prepare("SELECT created_at FROM password_reset_tokens 
                               WHERE user_id = ? AND used_at IS NULL 
                               ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$userId]);
        $recent = $stmt->fetch();

        if ($recent && strtotime($recent['created_at']) > time() - 600) {
            return ['success' => false, 'token' => null, 'message' => 'A reset link was already sent recently. Please wait before requesting again.'];
        }

        // Generate cryptographic token
        $token = bin2hex(random_bytes(32)); // 64-char hex string
        $tokenHash = hash('sha256', $token); // Store hash, not token
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

        $stmt = $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $tokenHash, $expiresAt]);

        return ['success' => true, 'token' => $token, 'message' => 'Token generated.'];

    } catch (Exception $e) {
        return ['success' => false, 'token' => null, 'message' => 'Failed to generate reset token.'];
    }
}

/**
 * Validate a password reset token
 * 
 * @param string $token Raw token from URL
 * @return array ['valid' => bool, 'user_id' => int|null, 'token_id' => int|null, 'message' => string]
 */
function validateResetToken(string $token): array
{
    try {
        $tokenHash = hash('sha256', $token);
        $pdo = db();

        $stmt = $pdo->prepare("SELECT t.id, t.user_id, t.expires_at, t.used_at, u.email, u.name 
                               FROM password_reset_tokens t
                               JOIN users u ON t.user_id = u.id
                               WHERE t.token_hash = ?");
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch();

        if (!$row) {
            return ['valid' => false, 'user_id' => null, 'token_id' => null, 'message' => 'Invalid reset link.'];
        }

        if ($row['used_at'] !== null) {
            return ['valid' => false, 'user_id' => null, 'token_id' => null, 'message' => 'This reset link has already been used.'];
        }

        if (strtotime($row['expires_at']) < time()) {
            return ['valid' => false, 'user_id' => null, 'token_id' => null, 'message' => 'This reset link has expired.'];
        }

        return [
            'valid' => true,
            'user_id' => (int) $row['user_id'],
            'token_id' => (int) $row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'message' => 'Token is valid.'
        ];

    } catch (Exception $e) {
        return ['valid' => false, 'user_id' => null, 'token_id' => null, 'message' => 'Validation failed.'];
    }
}

/**
 * Consume a reset token (mark as used)
 */
function consumeResetToken(int $tokenId): void
{
    db()->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?")->execute([$tokenId]);
}

/**
 * Build a branded password reset email
 */
function buildResetEmail(string $userName, string $resetLink): string
{
    $appName = getSetting('app_name', 'KiTAcc');
    $churchName = getChurchName();

    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;font-family:'Inter',Arial,sans-serif;background:#f4f5f7;">
<div style="max-width:520px;margin:40px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);">
    <div style="background:linear-gradient(135deg,#6c3fa0,#8b5cf6);padding:32px 24px;text-align:center;">
        <h1 style="color:#fff;margin:0;font-size:24px;">{$appName}</h1>
        <p style="color:rgba(255,255,255,0.8);margin:6px 0 0;font-size:13px;">{$churchName}</p>
    </div>
    <div style="padding:32px 24px;">
        <p style="color:#374151;font-size:15px;margin:0 0 16px;">Hi <strong>{$userName}</strong>,</p>
        <p style="color:#6b7280;font-size:14px;line-height:1.6;margin:0 0 24px;">
            A password reset was requested for your account. Click the button below to set a new password. This link will expire in <strong>1 hour</strong>.
        </p>
        <div style="text-align:center;margin:24px 0;">
            <a href="{$resetLink}" style="display:inline-block;padding:12px 32px;background:linear-gradient(135deg,#6c3fa0,#8b5cf6);color:#fff;text-decoration:none;border-radius:8px;font-weight:600;font-size:14px;">
                Reset Password
            </a>
        </div>
        <p style="color:#9ca3af;font-size:12px;line-height:1.5;margin:24px 0 0;">
            If you didn't request this, you can safely ignore this email. Your password will remain unchanged.
        </p>
        <hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">
        <p style="color:#9ca3af;font-size:11px;margin:0;text-align:center;">
            If the button doesn't work, copy and paste this link:<br>
            <a href="{$resetLink}" style="color:#8b5cf6;word-break:break-all;">{$resetLink}</a>
        </p>
    </div>
</div>
</body>
</html>
HTML;
}
