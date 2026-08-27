<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();

if (isStudent()) { redirect('/student/dashboard.php'); }
if (isAdmin()) { redirect('/admin/dashboard.php'); }

$error = '';
$success = '';
$email = trim($_POST['email'] ?? ($_GET['email'] ?? ''));
$otpDev = '';
$step = 1;

// ─── Rate Limiting ───────────────────────────────────
$maxAttempts = 5;
$cooldownSeconds = 30;

// Initialise session rate-limit keys
if (!isset($_SESSION['otp_attempts'])) $_SESSION['otp_attempts'] = 0;
if (!isset($_SESSION['otp_last_attempt'])) $_SESSION['otp_last_attempt'] = 0;

$attempts = $_SESSION['otp_attempts'];
$lastAttempt = $_SESSION['otp_last_attempt'];
$now = time();
$remaining = max(0, $cooldownSeconds - ($now - $lastAttempt));
$locked = $attempts >= $maxAttempts;

// Calculate lockout time remaining (30 seconds after 5th attempt)
$lockoutDuration = 30;
$lockoutRemaining = 0;
if ($locked) {
    $lockoutRemaining = max(0, $lockoutDuration - ($now - $lastAttempt));
    if ($lockoutRemaining <= 0) {
        // Reset after lockout expires
        $_SESSION['otp_attempts'] = 0;
        $_SESSION['otp_last_attempt'] = 0;
        $attempts = 0;
        $locked = false;
    }
}

// Reset attempts after 1 hour of inactivity
if ($attempts > 0 && !$locked && ($now - $lastAttempt) > 3600) {
    $_SESSION['otp_attempts'] = 0;
    $_SESSION['otp_last_attempt'] = 0;
    $attempts = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $error = 'Invalid form submission. Please refresh and try again.';
    } elseif (($_POST['action'] ?? '') === 'send_otp') {
        $email = trim($_POST['email'] ?? '');
        if ($locked) {
            $error = "You have tried more than $maxAttempts times. Please try again after {$lockoutRemaining} seconds.";
        } elseif (empty($email)) {
            $error = 'Please enter your email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Track attempt
            $_SESSION['otp_attempts'] = $attempts + 1;
            $_SESSION['otp_last_attempt'] = time();
            $result = generatePasswordResetOtp($email);
            if ($result['success']) {
                $otpDev = $result['otp'] ?? '';
                $step = 2;
            } else {
                $error = $result['error'] ?? 'Failed to send OTP.';
            }
        }
    } elseif (($_POST['action'] ?? '') === 'verify_otp') {
        $email = trim($_POST['email'] ?? '');
        $otp = trim($_POST['otp'] ?? '');
        if (empty($otp) || !preg_match('/^\d{6}$/', $otp)) {
            $error = 'Please enter a valid 6-digit OTP.';
            $step = 2;
        } else {
            $result = verifyPasswordResetOtp($email, $otp);
            if ($result['success']) {
                $step = 3;
                // Reset attempts on successful verification
                $_SESSION['otp_attempts'] = 0;
                $_SESSION['otp_last_attempt'] = 0;
            } else {
                $error = $result['error'] ?? 'Verification failed.';
                $step = 2;
            }
        }
    } elseif (($_POST['action'] ?? '') === 'reset_password') {
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        if (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
            $step = 3;
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
            $step = 3;
        } else {
            $result = setNewPassword($password);
            if ($result['success']) {
                $success = 'Your password has been reset successfully!';
            } else {
                $error = $result['error'] ?? 'Failed to reset password.';
                $step = 1;
            }
        }
    } elseif (($_POST['action'] ?? '') === 'resend') {
        $email = trim($_POST['email'] ?? '');
        if ($locked) {
            $error = "You have tried more than $maxAttempts times. Please try again after {$lockoutRemaining} seconds.";
            $step = 2;
        } else {
            $_SESSION['otp_attempts'] = $attempts + 1;
            $_SESSION['otp_last_attempt'] = time();
            $result = generatePasswordResetOtp($email);
            if ($result['success']) {
                $otpDev = $result['otp'] ?? '';
                $step = 2;
            } else {
                $error = $result['error'] ?? 'Failed to resend OTP.';
                $step = 2;
            }
        }
    }
} elseif (!empty($_SESSION['reset_otp_verified'])) {
    $step = 3;
}

// Recalculate after POST processing
$attempts = $_SESSION['otp_attempts'];
$lastAttempt = $_SESSION['otp_last_attempt'];
$remaining = max(0, $cooldownSeconds - (time() - $lastAttempt));
$locked = $attempts >= $maxAttempts;
if ($locked) {
    $lockoutRemaining = max(0, $lockoutDuration - (time() - $lastAttempt));
    if ($lockoutRemaining <= 0) {
        $_SESSION['otp_attempts'] = 0;
        $_SESSION['otp_last_attempt'] = 0;
        $attempts = 0;
        $locked = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Test Platform</title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/student.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .cooldown-bar {
            width: 100%;
            height: 4px;
            background: var(--gray-15);
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
            display: none;
        }
        .cooldown-bar.active { display: block; }
        .cooldown-bar-fill {
            height: 100%;
            background: var(--accent);
            border-radius: 2px;
            transition: width 1s linear;
        }
        .cooldown-text {
            font-size: 12px;
            color: var(--gray-50);
            text-align: center;
            margin-top: 6px;
            display: none;
        }
        .cooldown-text.active { display: block; }
        .attempts-text {
            font-size: 11px;
            color: var(--gray-40);
            text-align: center;
            margin-top: 4px;
        }
    </style>
</head>
<body class="auth-page">
    <div class="auth-hero">
        <div class="hero-logo">T</div>
        <div class="hero-text">
            <strong>Reset Password</strong>
            <span>
                <?php if ($step === 1): ?>
                    Enter your registered email to receive an OTP.
                <?php elseif ($step === 2): ?>
                    Enter the OTP sent to your email.
                <?php else: ?>
                    OTP verified! Set your new password.
                <?php endif; ?>
            </span>
        </div>
        <div class="hero-features">
            <div class="hero-feature">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                Secure OTP verification
            </div>
            <div class="hero-feature">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                OTP expires in 10 minutes
            </div>
            <div class="hero-feature">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                Set new password instantly
            </div>
        </div>
    </div>

    <div class="auth-card">
        <h1>
            <?php if ($step === 1): ?>Forgot Password
            <?php elseif ($step === 2): ?>Verify OTP
            <?php else: ?>Set New Password<?php endif; ?>
        </h1>
        <p class="subtitle">
            <?php if ($step === 1): ?>
                Enter your registered email
            <?php elseif ($step === 2): ?>
                We sent a 6-digit code to <strong><?= h($email) ?></strong>
            <?php else: ?>
                Choose a strong password for your account
            <?php endif; ?>
        </p>

        <?php if ($error): ?>
            <div class="auth-alert error">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16zm0 1a7 7 0 1 0 0 14 7 7 0 0 0 0-14zm0 9.5a.75.75 0 1 1 0 1.5.75.75 0 0 1 0-1.5zM10 6a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-1 0v-4A.5.5 0 0 1 10 6z"/></svg>
                <span><?= h($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="auth-alert success">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                <span><?= h($success) ?><br><a href="login.php" class="text-accent" style="text-decoration:underline;margin-top:4px;display:inline-block;">Sign in now</a></span>
            </div>
        <?php endif; ?>

        <?php if (defined('MAIL_DEV_MODE') && MAIL_DEV_MODE && $otpDev && $step === 2 && !$success): ?>
            <div class="otp-dev-box">
                <span class="badge badge-pending" style="margin-bottom:8px;">DEV MODE</span>
                <div style="margin-top:4px;">Your OTP is:</div>
                <code><?= h($otpDev) ?></code>
            </div>
        <?php endif; ?>

        <?php if ($step === 1 && !$success): ?>
        <form method="POST" id="sendOtpForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="send_otp">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input class="form-input" type="email" id="email" name="email"
                       value="<?= h($email) ?>" placeholder="your@email.com" required autofocus
                       <?= $locked ? 'disabled' : '' ?>>
            </div>
            <button type="submit" class="btn btn-primary w-full" id="sendOtpBtn"
                    <?= $locked ? 'disabled' : '' ?>>
                <?= $locked ? "Try again in {$lockoutRemaining}s" : 'Send OTP' ?>
            </button>
            <div class="cooldown-bar" id="cooldownBar"><div class="cooldown-bar-fill" id="cooldownFill"></div></div>
            <div class="cooldown-text" id="cooldownText"></div>
            <div class="attempts-text" id="attemptsText"><?= $maxAttempts - $attempts ?> of <?= $maxAttempts ?> attempts remaining</div>
        </form>
        <?php endif; ?>

        <?php if ($step === 2 && !$success): ?>
        <form method="POST" id="verifyOtpForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="verify_otp">
            <input type="hidden" name="email" value="<?= h($email) ?>">
            <div class="form-group">
                <label for="otp">Enter OTP</label>
                <input class="form-input otp-input" type="text" id="otp" name="otp"
                       placeholder="000000" maxlength="6" inputmode="numeric"
                       pattern="[0-9]{6}" autocomplete="one-time-code" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary w-full" id="verifyOtpBtn">Verify OTP</button>
        </form>
        <div class="auth-footer">
            <button type="button" class="btn btn-sm btn-ghost text-accent" style="border:none;" id="resendBtn"
                    <?= $locked ? 'disabled' : '' ?>>
                <?= $locked ? "Resend in {$lockoutRemaining}s" : 'Resend OTP' ?>
            </button>
            <div class="cooldown-bar" id="resendCooldownBar"><div class="cooldown-bar-fill" id="resendCooldownFill"></div></div>
            <div class="cooldown-text" id="resendCooldownText"></div>
            <span class="otp-timer-text">OTP expires in 10 minutes</span>
        </div>
        <?php endif; ?>

        <?php if ($step === 3 && !$success): ?>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="reset_password">
            <div class="form-group">
                <label for="password">New Password</label>
                <input class="form-input" type="password" id="password" name="password"
                       placeholder="Min 6 characters" required minlength="6" autofocus>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input class="form-input" type="password" id="confirm_password" name="confirm_password"
                       placeholder="Re-enter password" required minlength="6">
            </div>
            <button type="submit" class="btn btn-primary w-full">Reset Password</button>
        </form>
        <?php endif; ?>

        <div class="auth-footer" style="margin-top:var(--space-4);">
            <a href="login.php">Back to Sign In</a>
        </div>
    </div>

<script>
(function() {
    var COOLDOWN = <?= $cooldownSeconds ?>;
    var MAX_ATTEMPTS = <?= $maxAttempts ?>;
    var LOCKOUT = 30;
    var attempts = <?= $attempts ?>;
    var lastAttempt = <?= $lastAttempt ?>;
    var locked = <?= $locked ? 'true' : 'false' ?>;

    // ─── Shared cooldown timer ───
    function startCooldown(btnEl, barEl, fillEl, textEl, label, callback) {
        var now = Math.floor(Date.now() / 1000);
        var elapsed = now - lastAttempt;
        var total = locked ? LOCKOUT : COOLDOWN;
        var left = Math.max(0, total - elapsed);
        if (left <= 0 && !locked) { if (callback) callback(); return; }

        btnEl.disabled = true;
        barEl.classList.add('active');
        textEl.classList.add('active');

        function tick() {
            var now2 = Math.floor(Date.now() / 1000);
            var left2 = Math.max(0, total - (now2 - lastAttempt));

            // Update bar
            var pct = ((total - left2) / total) * 100;
            fillEl.style.width = pct + '%';

            // Update text
            if (locked) {
                btnEl.textContent = 'Try again in ' + left2 + 's';
                textEl.textContent = 'Too many attempts. Wait ' + left2 + 's before retrying.';
            } else {
                btnEl.textContent = label + ' in ' + left2 + 's';
                textEl.textContent = 'You can ' + label.toLowerCase() + ' again in ' + left2 + 's';
            }

            if (left2 <= 0) {
                clearInterval(timer);
                btnEl.disabled = false;
                btnEl.textContent = label;
                barEl.classList.remove('active');
                textEl.classList.remove('active');
                if (callback) callback();
            }
        }

        tick();
        var timer = setInterval(tick, 1000);
        return timer;
    }

    function updateAttempts() {
        var el = document.getElementById('attemptsText');
        if (el) {
            var remaining = Math.max(0, MAX_ATTEMPTS - attempts);
            el.textContent = remaining + ' of ' + MAX_ATTEMPTS + ' attempts remaining';
            if (remaining === 0) el.style.color = 'var(--red, #ef4444)';
        }
    }

    // ─── Step 1: Send OTP ───
    var sendBtn = document.getElementById('sendOtpBtn');
    if (sendBtn && (locked || lastAttempt > 0)) {
        var bar = document.getElementById('cooldownBar');
        var fill = document.getElementById('cooldownFill');
        var text = document.getElementById('cooldownText');
        startCooldown(sendBtn, bar, fill, text, 'Send OTP');
    }

    // ─── Step 2: Resend OTP (AJAX — no page reload) ───
    var resendBtn = document.getElementById('resendBtn');
    if (resendBtn && (locked || lastAttempt > 0)) {
        var rBar = document.getElementById('resendCooldownBar');
        var rFill = document.getElementById('resendCooldownFill');
        var rText = document.getElementById('resendCooldownText');
        startCooldown(resendBtn, rBar, rFill, rText, 'Resend OTP');
    }

    if (resendBtn) {
        resendBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (resendBtn.disabled) return;

            // Freeze button for 10 seconds
            attempts++;
            lastAttempt = Math.floor(Date.now() / 1000);

            var rBar = document.getElementById('resendCooldownBar');
            var rFill = document.getElementById('resendCooldownFill');
            var rText = document.getElementById('resendCooldownText');

            if (attempts >= MAX_ATTEMPTS) {
                locked = true;
                startCooldown(resendBtn, rBar, rFill, rText, 'Resend OTP');
            } else {
                startCooldown(resendBtn, rBar, rFill, rText, 'Resend OTP');
            }
            updateAttempts();

            // Actually resend via the form
            var form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = resendBtn.closest('.auth-footer').querySelector('form')
                ? resendBtn.closest('.auth-footer').querySelector('form').innerHTML
                : '<?= csrfField() ?>' +
                  '<input type="hidden" name="action" value="resend">' +
                  '<input type="hidden" name="email" value="<?= h($email) ?>">';
            document.body.appendChild(form);
            form.submit();
        });
    }

    // ─── Send OTP form: freeze on submit ───
    var sendForm = document.getElementById('sendOtpForm');
    if (sendForm) {
        sendForm.addEventListener('submit', function() {
            if (sendBtn && !sendBtn.disabled) {
                attempts++;
                lastAttempt = Math.floor(Date.now() / 1000);
                if (attempts >= MAX_ATTEMPTS) locked = true;
                updateAttempts();
            }
        });
    }

    updateAttempts();
})();
</script>
</body>
</html>
