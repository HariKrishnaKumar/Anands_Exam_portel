<?php
/**
 * Mailer — Send OTP emails via SMTP or PHP mail().
 */

require_once __DIR__ . '/../config/mail.php';

/**
 * Send an email.
 * Returns: true on success, false + error info on failure.
 */
function sendMail(string $to, string $subject, string $body): array {
    // Dev mode: log to file instead of sending
    if (defined('MAIL_DEV_MODE') && MAIL_DEV_MODE) {
        $log = sprintf(
            "[%s] TO: %s | SUBJECT: %s | BODY: %s\n",
            date('Y-m-d H:i:s'), $to, $subject, $body
        );
        $logDir = dirname(MAIL_DEV_LOG);
        if (!is_dir($logDir)) mkdir($logDir, 0777, true);
        file_put_contents(MAIL_DEV_LOG, $log, FILE_APPEND | LOCK_EX);
        return ['success' => true, 'dev_mode' => true];
    }

    if (MAIL_DRIVER === 'smtp') {
        return sendSmtpMail($to, $subject, $body);
    }

    return sendNativeMail($to, $subject, $body);
}

/**
 * Send via PHP mail() function (requires configured sendmail).
 */
function sendNativeMail(string $to, string $subject, string $body): array {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
    $headers .= "Reply-To: " . MAIL_FROM . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    $sent = @mail($to, $subject, $body, $headers);
    if ($sent) {
        return ['success' => true];
    }
    return ['success' => false, 'error' => 'PHP mail() failed. Check sendmail config.'];
}

/**
 * Send via direct SMTP connection (no external library needed).
 */
function sendSmtpMail(string $to, string $subject, string $body): array {
    $errno = 0;
    $errstr = '';

    $socket = @fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, 15);
    if (!$socket) {
        return ['success' => false, 'error' => "SMTP connection failed: $errstr ($errno)"];
    }

    // Read banner
    $resp = smtpReadResponse($socket);

    // EHLO
    $resp = smtpSendAndRead($socket, "EHLO " . gethostname());
    if (!smtpIsSuccess($resp)) {
        fclose($socket);
        return ['success' => false, 'error' => "EHLO failed: " . smtpFirstLine($resp)];
    }

    // STARTTLS
    $resp = smtpSendAndRead($socket, "STARTTLS");
    if (!smtpIsSuccess($resp)) {
        fclose($socket);
        return ['success' => false, 'error' => "STARTTLS failed: " . smtpFirstLine($resp)];
    }

    // Enable TLS
    $ok = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    if (!$ok) {
        fclose($socket);
        return ['success' => false, 'error' => 'TLS handshake failed'];
    }

    // EHLO again (post-TLS)
    $resp = smtpSendAndRead($socket, "EHLO " . gethostname());
    if (!smtpIsSuccess($resp)) {
        fclose($socket);
        return ['success' => false, 'error' => "EHLO (TLS) failed: " . smtpFirstLine($resp)];
    }

    // AUTH LOGIN
    $resp = smtpSendAndRead($socket, "AUTH LOGIN");
    if (!smtpIsSuccess($resp) && strpos($resp, '334') === false) {
        fclose($socket);
        return ['success' => false, 'error' => "AUTH not supported: " . smtpFirstLine($resp)];
    }

    // Username (base64)
    $resp = smtpSendAndRead($socket, base64_encode(SMTP_USERNAME));
    if (strpos($resp, '334') === false) {
        fclose($socket);
        return ['success' => false, 'error' => "SMTP username rejected: " . smtpFirstLine($resp)];
    }

    // Password (base64)
    $resp = smtpSendAndRead($socket, base64_encode(SMTP_PASSWORD));
    if (strpos($resp, '235') === false) {
        $detail = smtpFirstLine($resp);
        fclose($socket);
        $hint = '';
        if (strpos($detail, '535') !== false) {
            $hint = ' — Gmail requires an App Password (not your regular password). Generate one at https://myaccount.google.com/apppasswords';
        }
        return ['success' => false, 'error' => "SMTP auth failed: $detail$hint"];
    }

    // MAIL FROM
    $resp = smtpSendAndRead($socket, "MAIL FROM:<" . SMTP_FROM . ">");
    if (!smtpIsSuccess($resp)) {
        fclose($socket);
        return ['success' => false, 'error' => "MAIL FROM failed: " . smtpFirstLine($resp)];
    }

    // RCPT TO
    $resp = smtpSendAndRead($socket, "RCPT TO:<" . $to . ">");
    if (!smtpIsSuccess($resp)) {
        fclose($socket);
        return ['success' => false, 'error' => "RCPT TO failed: " . smtpFirstLine($resp)];
    }

    // DATA
    $resp = smtpSendAndRead($socket, "DATA");
    if (strpos($resp, '354') === false) {
        fclose($socket);
        return ['success' => false, 'error' => "DATA command failed: " . smtpFirstLine($resp)];
    }

    // Send email content
    $messageId = '<' . bin2hex(random_bytes(16)) . '@' . SMTP_HOST . '>';
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">\r\n";
    $headers .= "To: <" . $to . ">\r\n";
    $headers .= "Subject: $subject\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    $headers .= "Message-ID: $messageId\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    $fullMsg = $headers . "\r\n" . $body . "\r\n.\r\n";
    fputs($socket, $fullMsg);
    $resp = '';
    while ($line = fgets($socket, 512)) {
        $resp .= $line;
        if (isset($line[3]) && $line[3] !== '-') break;
    }

    fputs($socket, "QUIT\r\n");
    fclose($socket);

    if (smtpIsSuccess($resp) || strpos($resp, '250') !== false || strpos($resp, 'OK') !== false) {
        return ['success' => true];
    }
    return ['success' => false, 'error' => "SMTP send failed: " . smtpFirstLine($resp)];
}

/**
 * Send a command and read the full multi-line response.
 */
function smtpSendAndRead($socket, string $cmd): string {
    fputs($socket, $cmd . "\r\n");
    return smtpReadResponse($socket);
}

/**
 * Read SMTP response (handles multi-line continuations).
 */
function smtpReadResponse($socket): string {
    $response = '';
    while ($line = fgets($socket, 512)) {
        if ($line === false) break;
        $response .= $line;
        // Multi-line responses have '-' at position 3, last line has ' '
        if (strlen($line) >= 4 && $line[3] !== '-') break;
        if (strlen($line) < 4) break; // Short line, treat as end
    }
    return $response;
}

/**
 * Check if SMTP response is a success (2xx or 3xx).
 */
function smtpIsSuccess(string $response): bool {
    if (strlen($response) < 3) return false;
    $code = substr($response, 0, 3);
    return $code[0] === '2' || $code[0] === '3';
}

/**
 * Get first line of SMTP response.
 */
function smtpFirstLine(string $response): string {
    $nl = strpos($response, "\n");
    return $nl !== false ? trim(substr($response, 0, $nl)) : trim($response);
}

/**
 * Send OTP verification email.
 */
function sendOtpEmail(string $to, string $name, string $otp): array {
    $subject = "Your OTP for Email Verification — Test Platform";

    $body = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>OTP Verification</title></head>
<body style="font-family: 'Segoe UI', Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 24px;">
    <div style="max-width: 480px; margin: 0 auto; background: #ffffff; border-radius: 8px; padding: 32px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
        <div style="font-size: 28px; font-weight: 700; color: #0078D4; margin-bottom: 4px;">Test Platform</div>
        <div style="font-size: 13px; color: #666; margin-bottom: 24px;">Email Verification</div>

        <p style="color: #333; font-size: 15px; line-height: 1.6;">Hi <strong>$name</strong>,</p>
        <p style="color: #333; font-size: 15px; line-height: 1.6;">
            Your one-time verification code is:
        </p>

        <div style="text-align: center; margin: 28px 0;">
            <span style="display: inline-block; font-size: 36px; font-weight: 700; letter-spacing: 8px;
                         color: #0078D4; background: #F0F6FC; padding: 16px 32px; border-radius: 8px;">
                $otp
            </span>
        </div>

        <p style="color: #666; font-size: 13px; line-height: 1.5;">
            This code expires in <strong>10 minutes</strong>. If you didn't request this, please ignore this email.
        </p>

        <hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">
        <p style="color: #999; font-size: 12px; text-align: center;">
            &copy; 2026 Test Platform. All rights reserved.
        </p>
    </div>
</body>
</html>
HTML;

    return sendMail($to, $subject, $body);
}
