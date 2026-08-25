<?php
/**
 * Login page — optimized for high-concurrency burst traffic.
 *
 * CONCURRENCY DESIGN:
 *  — adminLogin() / studentLogin() call session_write_close() BEFORE returning,
 *    so this page's redirect() never holds the session file lock.
 *  — Under 200+ simultaneous logins, this prevents the "session lock bottleneck"
 *    where each request serialises on the next request's .sess file.
 *  — CSRF validation still uses the session (it's open during POST processing).
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();

// If already logged in, redirect (session is open here — fine for a read-only check)
if (isAdmin()) { redirect('/admin/dashboard.php'); }
if (isStudent()) { redirect('/student/dashboard.php'); }

$error = '';
$verifySid = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $error = 'Invalid form submission. Please refresh and try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'student';

        if (empty($email) || empty($password)) {
            $error = 'Please enter email and password.';
        } else {
            // ─── Authenticate ───
            // On success, these functions call session_write_close() before returning,
            // releasing the session lock so the redirect below doesn't block other requests.
            if ($role === 'admin') {
                $result = adminLogin($email, $password);
            } else {
                $result = studentLogin($email, $password);
            }

            if ($result['success']) {
                // Session is already written and closed — redirect is lock-free.
                if ($role === 'admin') {
                    header('Location: ' . BASE_URL . '/admin/dashboard.php');
                } else {
                    header('Location: ' . BASE_URL . '/student/dashboard.php');
                }
                exit;
            } else {
                $error = $result['error'];
                if (!empty($result['not_verified'])) {
                    $verifySid = (int)$result['student_id'];
                }
                // On failure the session is still open (auth functions don't close
                // it on error) — we need to close it before rendering HTML.
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_write_close();
                }
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Test Platform</title>
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
            <strong>Welcome Back</strong>
            <span>Sign in to access your tests, track progress, and continue learning.</span>
        </div>
        <div class="hero-features">
            <div class="hero-feature">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                Real-time test monitoring
            </div>
            <div class="hero-feature">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                Detailed performance analytics
            </div>
            <div class="hero-feature">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                Seamless multi-device access
            </div>
        </div>
    </div>

    <!-- Auth Card -->
    <div class="auth-card">
        <div class="logo-mark">T</div>
        <h1>Sign In</h1>
        <p class="subtitle">Admin or Student access</p>

        <?php if ($error): ?>
            <div class="auth-alert error">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16zm0 1a7 7 0 1 0 0 14 7 7 0 0 0 0-14zm0 9.5a.75.75 0 1 1 0 1.5.75.75 0 0 1 0-1.5zM10 6a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-1 0v-4A.5.5 0 0 1 10 6z"/></svg>
                <span><?= h($error) ?>
                    <?php if ($verifySid > 0): ?>
                        <br><a href="<?= BASE_URL ?>/verify-otp.php?student_id=<?= $verifySid ?>&email=<?= urlencode(h($_POST['email'] ?? '')) ?>" class="text-accent" style="text-decoration:underline;margin-top:4px;display:inline-block;">Verify email now</a>
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfField() ?>

            <div class="form-group">
                <label for="role">Account Type</label>
                <select class="form-select" id="role" name="role" required>
                    <option value="student" <?= ($_POST['role'] ?? 'student') === 'student' ? 'selected' : '' ?>>Student</option>
                    <option value="admin" <?= ($_POST['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input class="form-input" type="email" id="email" name="email"
                       value="<?= h($_POST['email'] ?? '') ?>" placeholder="your@email.com" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input class="form-input" type="password" id="password" name="password"
                       placeholder="Enter your password" required>
            </div>

            <button type="submit" class="btn btn-primary w-full">
                Sign In
            </button>
        </form>

        <div class="auth-footer">
            Don't have an account?
            <a href="signup.php">Register here</a>
            <a href="guest.php" class="auth-footer-link">Have a guest link? Click here</a>
        </div>
    </div>
</body>
</html>
