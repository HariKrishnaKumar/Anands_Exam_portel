<?php
/**
 * OTP Verification Page.
 * Student is redirected here after signup to verify their email.
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();

// If already verified + logged in, redirect
if (isStudent()) { redirect('/student/dashboard.php'); }

$error = '';
$success = '';
$studentId = (int)($_GET['student_id'] ?? ($_POST['student_id'] ?? 0));
$email = trim($_GET['email'] ?? ($_POST['email'] ?? ''));
$otpDev = $_GET['otp_dev'] ?? ''; // Only shown in dev mode

if ($studentId <= 0 || empty($email)) {
    // No student info — redirect to signup
    redirect('/signup.php');
}

// Handle OTP verification POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCsrfToken()) {
        $error = 'Invalid form submission. Please refresh and try again.';
    } elseif ($_POST['action'] === 'verify') {
        $otp = trim($_POST['otp'] ?? '');
        if (!preg_match('/^\d{6}$/', $otp)) {
            $error = 'Please enter a valid 6-digit OTP.';
        } else {
            $result = verifyStudentOtp($studentId, $otp);
            if ($result['success']) {
                $success = 'Email verified successfully! You can now sign in.';
            } else {
                $error = $result['error'] ?? 'Verification failed. Please try again.';
            }
        }
    } elseif ($_POST['action'] === 'resend') {
        $result = resendStudentOtp($studentId);
        if ($result['success']) {
            $success = 'A new OTP has been sent to your email.';
            if (defined('MAIL_DEV_MODE') && MAIL_DEV_MODE) {
                // In dev mode, show the OTP from the log
                $otpDev = $result['otp'] ?? '(check storage/logs/otp.log)';
            }
        } else {
            $error = $result['error'] ?? 'Failed to resend OTP. Please try again.';
        }
    }
}

// Get student name for greeting
$studentName = '';
try {
    $pdo = getDB();
    // Check unverified_students first
    $stmt = $pdo->prepare("SELECT name FROM unverified_students WHERE id = ?");
    $stmt->execute([$studentId]);
    $s = $stmt->fetch();
    if ($s) {
        $studentName = $s['name'];
    } else {
        // Possibly already verified — check students table
        $stmt = $pdo->prepare("SELECT name FROM students WHERE id = ?");
        $stmt->execute([$studentId]);
        $s = $stmt->fetch();
        if ($s) {
            $studentName = $s['name'];
            $success = 'Email already verified! You can sign in now.';
        }
    }
} catch (Exception $e) {
    // Ignore
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email — Test Platform</title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/student.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="auth-page">

    <!-- Hero Section (hidden mobile, visible tablet+) -->
    <div class="auth-hero">
        <div class="hero-logo">T</div>
        <div class="hero-text">
            <strong>Verify Your Email</strong>
            <span>One more step to activate your account and start testing.</span>
        </div>
        <div class="hero-features">
            <div class="hero-feature">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                Secure email verification
            </div>
            <div class="hero-feature">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                Instant account activation
            </div>
            <div class="hero-feature">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                Start your tests right away
            </div>
        </div>
    </div>

    <!-- Auth Card -->
    <div class="auth-card">
        <div class="logo-mark">T</div>
        <h1>Verify Your Email</h1>
        <p class="subtitle">
            <?php if ($studentName): ?>
                Hi <strong><?= h($studentName) ?></strong>,
            <?php endif; ?>
            we sent a 6-digit code to <strong><?= h($email) ?></strong>
        </p>

        <?php if ($error): ?>
            <div class="auth-alert error">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16zm0 1a7 7 0 1 0 0 14 7 7 0 0 0 0-14zm0 9.5a.75.75 0 1 1 0 1.5.75.75 0 0 1 0-1.5zM10 6a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-1 0v-4A.5.5 0 0 1 10 6z"/></svg>
                <span><?= h($error) ?>
                    <?php if (strpos($error, 'expired') !== false): ?>
                        <form method="POST" style="margin-top:8px;">
                            <?= csrfField() ?>
                            <input type="hidden" name="student_id" value="<?= $studentId ?>">
                            <input type="hidden" name="email" value="<?= h($email) ?>">
                            <input type="hidden" name="action" value="resend">
                            <button type="submit" class="btn btn-sm btn-secondary">Resend OTP</button>
                        </form>
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="auth-alert success">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                <span><?= h($success) ?><br><a href="login.php" class="text-accent" style="text-decoration:underline;margin-top:4px;display:inline-block;">Sign in</a></span>
            </div>
        <?php endif; ?>

        <?php if (defined('MAIL_DEV_MODE') && MAIL_DEV_MODE && $otpDev): ?>
            <div class="otp-dev-box">
                <span class="badge badge-pending" style="margin-bottom:8px;">DEV MODE</span>
                <div style="margin-top:4px;">Your OTP is:</div>
                <code><?= h($otpDev) ?></code>
                <div style="font-size:11px;color:#999;margin-top:4px;">
                    (Also logged in <code>storage/logs/otp.log</code>)
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" id="otpForm">
            <?= csrfField() ?>
            <input type="hidden" name="student_id" value="<?= $studentId ?>">
            <input type="hidden" name="email" value="<?= h($email) ?>">
            <input type="hidden" name="action" value="verify">

            <div class="form-group">
                <label for="otp">Enter OTP</label>
                <input class="form-input otp-input" type="text" id="otp" name="otp"
                       placeholder="000000" maxlength="6" inputmode="numeric"
                       pattern="[0-9]{6}" autocomplete="one-time-code"
                       value="<?= h($_POST['otp'] ?? '') ?>" required autofocus>
            </div>

            <button type="submit" class="btn btn-primary w-full">
                Verify Email
            </button>
        </form>

        <div class="auth-footer">
            <form method="POST" style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="student_id" value="<?= $studentId ?>">
                <input type="hidden" name="email" value="<?= h($email) ?>">
                <input type="hidden" name="action" value="resend">
                <button type="submit" class="btn btn-sm btn-ghost text-accent" style="border:none;">
                    Resend OTP
                </button>
            </form>
            <span class="otp-timer-text">OTP expires in 10 minutes</span>
        </div>

        <div class="auth-footer" style="margin-top:var(--space-4);">
            <a href="signup.php" class="auth-footer-link">← Back to registration</a>
        </div>
        <?php endif; ?>
    </div>

    <script>
    // Auto-submit on 6 digits
    document.getElementById('otp')?.addEventListener('input', function() {
        if (this.value.length === 6 && /^\d{6}$/.test(this.value)) {
            // Optional: auto-submit after a short delay
        }
    });
    </script>
</body>
</html>
