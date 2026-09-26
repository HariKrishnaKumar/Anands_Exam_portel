<?php
/**
 * Faculty Login — /faculty-login.php
 *
 * Clone of bgs.php with a College dropdown replacing "Account Type".
 * Credentials are assigned by an admin (table `faculty`); there is no
 * self-registration path here — the account must exist already.
 *
 * CONCURRENCY DESIGN (inherited from bgs.php):
 *  - facultyLogin() calls session_write_close() BEFORE returning, so the
 *    redirect() below never holds the session file lock.
 *  - CSRF validation still uses the session (open during POST processing).
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();

// Already signed in → go straight to the read-only dashboard.
if (isFaculty()) { redirect('/faculty/dashboard.php'); }
if (isAdmin())    { redirect('/admin/dashboard.php'); }
if (isStudent())  { redirect('/student/dashboard.php'); }

$error = '';

/**
 * Audit: record a successful faculty sign-in so an admin can see WHEN a
 * faculty member logged in and FROM WHERE.
 *
 * latitude/longitude are sent from the hidden geo_* fields that the
 * browser's Geolocation API fills in on this page. They are optional:
 * if the user denies the permission or no fix is available the row is
 * still written with NULL coordinates — time and IP are always captured.
 *
 * Never throws: an audit failure must not break the login itself.
 */
function logFacultyLogin(array $post): void {
    try {
        $pdo = getDB();

        $lat = trim((string)($post['geo_lat'] ?? ''));
        $lng = trim((string)($post['geo_lng'] ?? ''));
        $acc = trim((string)($post['geo_accuracy'] ?? ''));

        $validCoords = $lat !== '' && $lng !== ''
            && is_numeric($lat) && is_numeric($lng)
            && (float)$lat >= -90 && (float)$lat <= 90
            && (float)$lng >= -180 && (float)$lng <= 180;

        $stmt = $pdo->prepare("
            INSERT INTO faculty_login_log
                (faculty_id, college_id, email, ip_address, user_agent,
                 latitude, longitude, accuracy_m, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $_SESSION['faculty_id']       ?? null,
            $_SESSION['faculty_college_id'] ?? null,
            (string)($_SESSION['faculty_email'] ?? ''),
            (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            $validCoords ? round((float)$lat, 7) : null,
            $validCoords ? round((float)$lng, 7) : null,
            ($validCoords && $acc !== '' && is_numeric($acc)) ? (int)$acc : null,
        ]);
    } catch (Throwable $e) {
        error_log('faculty login log failed: ' . $e->getMessage());
    }
}

$pdo = getDB();
$colleges = $pdo->query('SELECT id, name FROM colleges ORDER BY name ASC')->fetchAll();
$selectedCollege = (string)($_POST['college_id'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $error = 'Invalid form submission. Please refresh and try again.';
    } else {
        $collegeId = (string)($_POST['college_id'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $password  = $_POST['password'] ?? '';

        if ($collegeId === '' || !ctype_digit($collegeId)) {
            $error = 'Please select your college.';
        } elseif (empty($email) || empty($password)) {
            $error = 'Please enter email and password.';
        } else {
            // On success facultyLogin() has already closed the session lock.
            $result = facultyLogin($collegeId, $email, $password);

            if ($result['success']) {
                // Audit BEFORE the redirect: records time + lat/long for admins.
                logFacultyLogin($_POST);
                header('Location: ' . BASE_URL . '/faculty/dashboard.php');
                exit;
            } else {
                $error = $result['error'];
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
    <title>Faculty Sign In</title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/student.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .auth-page { background: #fdfdfd !important; }
        .auth-page::before { display: none !important; }
        .auth-page::after { display: none !important; }
    </style>
</head>
<body class="auth-page">

    <!-- Top-left company branding -->
    <div class="auth-top-brand">
        <img src="<?= ASSETS_URL ?>/img/bgs-logo.png" alt="BGS Group" class="auth-top-brand-logo">
        <div class="auth-top-brand-text">
            <span class="auth-top-brand-name">BGS Group Of Institutions</span>
        </div>
    </div>

    <!-- Hero Section (hidden mobile, visible tablet+) -->
    <div class="auth-hero">
        <div class="hero-text">
            <strong>Faculty Portal</strong>
            <span>Sign in to view read-only analytics for your college — scores, attendance and progress.</span>
        </div>
        <div class="hero-features">
            <div class="hero-feature">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                College-scoped student analytics
            </div>
            <div class="hero-feature">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                Scores &amp; attendance per test
            </div>
            <div class="hero-feature">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                Read-only — no edits possible
            </div>
        </div>
    </div>

    <!-- Auth Card -->
    <div class="auth-card">
        <img src="<?= ASSETS_URL ?>/img/bgs-logo.png" alt="BGS Group" class="logo-mark">
        <h1>Faculty Sign In</h1>
        <p class="subtitle">Select your college, then sign in</p>

        <?php if ($error): ?>
            <div class="auth-alert error">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16zm0 1a7 7 0 1 0 0 14 7 7 0 0 0 0-14zm0 9.5a.75.75 0 1 1 0 1.5.75.75 0 0 1 0-1.5zM10 6a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-1 0v-4A.5.5 0 0 1 10 6z"/></svg>
                <span><?= h($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" id="facultyLoginForm">
            <?= csrfField() ?>

            <!-- Machine location for the admin login audit (filled by JS) -->
            <input type="hidden" name="geo_lat" id="geo_lat" value="">
            <input type="hidden" name="geo_lng" id="geo_lng" value="">
            <input type="hidden" name="geo_accuracy" id="geo_accuracy" value="">

            <div class="form-group">
                <label for="college_id">College</label>
                <select class="form-select" id="college_id" name="college_id" required>
                    <option value="">— Select your college —</option>
                    <?php foreach ($colleges as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $selectedCollege === (string)$c['id'] ? 'selected' : '' ?>>
                            <?= h($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
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
            Not a faculty member?
            <a href="bgs.php">Candidate / Admin sign in</a>
        </div>
    </div>

    <script>
    /**
     * Location capture for the admin audit log.
     *
     * Fills the hidden geo_* fields from the Geolocation API. Two rules:
     *  - NEVER block a sign-in: if permission is denied / unavailable the
     *    form submits straight away with empty coordinates.
     *  - If the fix is still pending when the user clicks Sign In, hold the
     *    submit for at most 2.5s and then go regardless.
     */
    (function () {
        var form = document.getElementById('facultyLoginForm');
        if (!form || !navigator.geolocation) { return; } // no API → submit as-is

        var latEl = document.getElementById('geo_lat');
        var lngEl = document.getElementById('geo_lng');
        var accEl = document.getElementById('geo_accuracy');
        var resolved = false;
        var pending = true;

        function done() { pending = false; }

        navigator.geolocation.getCurrentPosition(
            function (pos) {
                latEl.value = Number(pos.coords.latitude).toFixed(7);
                lngEl.value = Number(pos.coords.longitude).toFixed(7);
                accEl.value = pos.coords.accuracy != null ? Math.round(pos.coords.accuracy) : '';
                resolved = true;
                done();
            },
            function () { done(); },           // denied / unavailable
            { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
        );

        form.addEventListener('submit', function (e) {
            if (resolved || !pending) { return; }   // already have an answer
            e.preventDefault();

            var go = false;
            function release() {
                if (go) { return; }
                go = true;
                form.submit();                      // native submit: no re-entry
            }
            var poll = setInterval(function () {
                if (!pending) { clearInterval(poll); release(); }
            }, 50);
            setTimeout(function () { clearInterval(poll); release(); }, 2500);
        });
    })();
    </script>
</body>
</html>
