<?php
/**
 * Guest Access Page — for QR code and guest link entry.
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();

// If already logged in as student, redirect
if (isStudent()) { redirect('/student/dashboard.php'); }

$error = '';
$token = '';

// Determine token: POST overrides GET for form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['token'])) {
    $token = trim($_POST['token']);
} elseif (!empty($_GET['token'])) {
    $token = trim($_GET['token']);
}

function processGuestToken(string $token): ?string {
    if (empty($token)) {
        return 'Please enter or scan your access token.';
    }
    $result = guestLogin($token);
    if ($result['success']) {
        $testParam = $result['test_id'] ?? ($_SESSION['test_id'] ?? 0);
        if ($testParam > 0) {
            redirect('/student/test.php?test_id=' . $testParam);
        } else {
            redirect('/student/dashboard.php');
        }
    }
    return $result['error'] ?? 'Unknown error.';
}

if (!empty($token)) {
    $error = processGuestToken($token);
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guest Access — Test Platform</title>
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
            <strong>Guest Access</strong>
            <span>Use your guest link or QR code to access tests instantly.</span>
        </div>
        <div class="hero-features">
            <div class="hero-feature">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                No account required
            </div>
            <div class="hero-feature">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                Instant test access via token
            </div>
            <div class="hero-feature">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                QR code compatible
            </div>
        </div>
    </div>

    <!-- Auth Card -->
    <div class="auth-card">
        <div class="logo-mark">T</div>
        <h1>Guest Access</h1>
        <p class="subtitle">Use your guest link or QR code to access tests</p>

        <?php if ($error): ?>
            <div class="auth-alert error">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16zm0 1a7 7 0 1 0 0 14 7 7 0 0 0 0-14zm0 9.5a.75.75 0 1 1 0 1.5.75.75 0 0 1 0-1.5zM10 6a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-1 0v-4A.5.5 0 0 1 10 6z"/></svg>
                <span><?= h($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="token">Access Token</label>
                <input class="form-input" type="text" id="token" name="token"
                       value="<?= h($token) ?>" placeholder="Paste your token or scan QR code" required autofocus>
                <div class="form-hint">Enter the token from your guest link email or QR code.</div>
            </div>
            <button type="submit" class="btn btn-primary w-full">
                Access Tests
            </button>
        </form>

        <div class="auth-footer">
            <a href="login.php">Sign in with your account</a>
        </div>
    </div>
</body>
</html>
