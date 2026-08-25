<?php
/**
 * Mail Configuration for OTP Email Sending.
 *
 * ===== SETUP INSTRUCTIONS =====
 *
 * Option A: PHP built-in mail() (XAMPP / local)
 *   1. Edit C:\xampp\php\php.ini, set:
 *        SMTP = smtp.gmail.com
 *        smtp_port = 587
 *        sendmail_from = your@gmail.com
 *        sendmail_path = "C:\xampp\sendmail\sendmail.exe -t"
 *
 *   2. Edit C:\xampp\sendmail\sendmail.ini:
 *        smtp_server=smtp.gmail.com
 *        smtp_port=587
 *        auth_username=your@gmail.com
 *        auth_password=your-app-password
 *        force_sender=your@gmail.com
 *
 *   Then set MAIL_DRIVER = 'mail' below.
 *
 * Option B: Direct SMTP (no external dependency)
 *   Set MAIL_DRIVER = 'smtp' and fill SMTP_* settings below.
 *   Uses PHP fsockopen — works on any host without sendmail.
 *
 * ===== GMAIL APP PASSWORD =====
 * For Gmail, you MUST use an App Password (not your regular password):
 *   1. Enable 2-Factor Authentication at https://myaccount.google.com/security
 *   2. Go to https://myaccount.google.com/apppasswords
 *   3. Generate an App Password for "Mail"
 *   4. Use that 16-character password below
 */

require_once __DIR__ . '/env.php';

// ─── MAIL DRIVER ──────────────────────────────────────────
// Options: 'mail' (PHP mail() + sendmail) or 'smtp' (direct SMTP)
// All values come from the .env file / environment variables.
// NEVER hardcode credentials here — see .env.example for the template.
define('MAIL_DRIVER', env('MAIL_DRIVER', 'smtp'));

// ─── SMTP SETTINGS (used when MAIL_DRIVER = 'smtp') ──────
define('SMTP_HOST',     env('SMTP_HOST',     'smtp.gmail.com'));
define('SMTP_PORT',     env('SMTP_PORT',     '587'));   // 587 for TLS, 465 for SSL
define('SMTP_USERNAME', env('SMTP_USERNAME'));          // from .env
define('SMTP_PASSWORD', env('SMTP_PASSWORD'));          // from .env — Gmail App Password
define('SMTP_FROM',     env('SMTP_FROM',     env('SMTP_USERNAME')));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', 'Test Platform'));

// ─── PHP MAIL() SETTINGS (used when MAIL_DRIVER = 'mail') ─
define('MAIL_FROM',      env('MAIL_FROM',      env('SMTP_USERNAME')));
define('MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'Test Platform'));

// ─── DEVELOPMENT SETTINGS ─────────────────────────────────
// When true, OTP is logged to file instead of sent (for testing)
define('MAIL_DEV_MODE', env('MAIL_DEV_MODE', 'false') === 'true');
define('MAIL_DEV_LOG',  __DIR__ . '/../storage/logs/otp.log');
