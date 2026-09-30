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
 * Location is MANDATORY for a faculty sign-in.
 *
 * The hidden geo_* fields are filled by the Geolocation API on this page.
 * A sign-in is only accepted when both coordinates are present and inside
 * the legal lat/long ranges — this is the server-side half of the gate,
 * so a client with JS disabled (or the fields stripped) cannot get in.
 *
 * Returns true only when the coordinates are usable.
 */
function geoCoordinatesValid(array $post): bool {
    $lat = trim((string)($post['geo_lat'] ?? ''));
    $lng = trim((string)($post['geo_lng'] ?? ''));

    return $lat !== '' && $lng !== ''
        && is_numeric($lat) && is_numeric($lng)
        && (float)$lat >= -90 && (float)$lat <= 90
        && (float)$lng >= -180 && (float)$lng <= 180;
}

/**
 * Audit: record a successful faculty sign-in so an admin can see WHEN a
 * faculty member logged in and FROM WHERE.
 *
 * latitude/longitude always come through now — geoCoordinatesValid()
 * rejects the request before we ever reach a successful login, so the
 * audit row is written with real coordinates every time.
 *
 * Never throws: an audit failure must not break the login itself.
 */
function logFacultyLogin(array $post): void {
    try {
        $pdo = getDB();

        $lat = trim((string)($post['geo_lat'] ?? ''));
        $lng = trim((string)($post['geo_lng'] ?? ''));
        $acc = trim((string)($post['geo_accuracy'] ?? ''));

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
            round((float)$lat, 7),
            round((float)$lng, 7),
            ($acc !== '' && is_numeric($acc)) ? (int)$acc : null,
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

        if (!geoCoordinatesValid($_POST)) {
            $error = 'Location access is required to sign in. Please allow location sharing and try again.';
        } elseif ($collegeId === '' || !ctype_digit($collegeId)) {
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

        /* Location gate — sign-in is blocked until the browser shares a fix */
        .geo-status {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 13px;
            line-height: 1.45;
            padding: 9px 12px;
            border-radius: 8px;
            border: 1px solid transparent;
            margin: 0 0 14px;
        }
        .geo-status .geo-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex: 0 0 auto;
            margin-top: 5px;
        }
        .geo-status.is-pending { background: #f5f7ff; border-color: #dfe4ff; color: #4b5563; }
        .geo-status.is-pending .geo-dot { background: #9aa4d8; animation: geoPulse 1.2s ease-in-out infinite; }
        .geo-status.is-ok { background: #effaf3; border-color: #bfe8cf; color: #12724a; }
        .geo-status.is-ok .geo-dot { background: #16a34a; }
        .geo-status.is-blocked { background: #fff1f1; border-color: #ffd2d2; color: #b02020; }
        .geo-status.is-blocked .geo-dot { background: #dc2626; }
        .geo-retry {
            margin-top: 8px;
            padding: 5px 12px;
            font-size: 12px;
            border: 1px solid #dc2626;
            background: #fff;
            color: #b02020;
            border-radius: 6px;
            cursor: pointer;
        }
        .geo-retry:hover { background: #fff1f1; }
        @keyframes geoPulse { 0%, 100% { opacity: 1; } 50% { opacity: .35; } }
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

            <!-- Location gate: the form cannot be submitted until the browser
                 shares a fix (see the script at the bottom of this page). -->
            <div class="geo-status is-pending" id="geoStatus" role="status" aria-live="polite">
                <span class="geo-dot"></span>
                <span>
                    <span id="geoStatusText">Waiting for location permission — required to sign in.</span>
                    <button type="button" class="geo-retry" id="geoRetry" hidden>Retry location</button>
                </span>
            </div>

            <button type="submit" class="btn btn-primary w-full" id="facultySubmit" disabled>
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
     * MANDATORY location gate for the faculty sign-in.
     *
     * Rules:
     *  - A sign-in is only possible once the browser has reported a fix:
     *    the submit button stays disabled until geo_lat/geo_lng are filled.
     *  - Permission denied / no API / no fix → the button is re-enabled but
     *    the submit handler stops the POST and re-asks, so nothing can slip
     *    through without coordinates.
     *  - The server enforces the same rule (geoCoordinatesValid()), so
     *    disabling JS does not bypass the gate.
     */
    (function () {
        var form      = document.getElementById('facultyLoginForm');
        var statusEl  = document.getElementById('geoStatus');
        var statusTxt = document.getElementById('geoStatusText');
        var retryBtn  = document.getElementById('geoRetry');
        var submitBtn = document.getElementById('facultySubmit');
        if (!form || !statusEl || !statusTxt || !submitBtn) { return; }

        var latEl = document.getElementById('geo_lat');
        var lngEl = document.getElementById('geo_lng');
        var accEl = document.getElementById('geo_accuracy');

        var PENDING = 'pending';
        var OK      = 'ok';
        var BLOCKED = 'blocked';

        var state = PENDING;
        var inFlight = false;

        function render(message) {
            statusEl.className = 'geo-status is-' + state;
            statusTxt.textContent = message;
            if (retryBtn) { retryBtn.hidden = (state !== BLOCKED); }
            // Never enabled without a fix; enabled-but-blocked so a click
            // can re-ask and show the reason.
            submitBtn.disabled = (state === PENDING);
        }

        function clearCoords() {
            latEl.value = '';
            lngEl.value = '';
            accEl.value = '';
        }

        function blockedMessage(err) {
            var base = 'Location access is required to sign in. ';
            if (!navigator.geolocation) {
                return base + 'This browser has no Geolocation API.';
            }
            if (err && err.code === 1) {
                return base + 'Location was blocked for this site — allow it in your browser, then press Retry location.';
            }
            if (err && err.code === 2) {
                return base + 'No position fix available — check your device location, then press Retry location.';
            }
            if (err && err.code === 3) {
                return base + 'Timed out waiting for a fix — press Retry location.';
            }
            return base + 'Press Retry location to try again.';
        }

        function requestLocation() {
            if (inFlight) { return; }
            if (!navigator.geolocation) {
                state = BLOCKED;
                render(blockedMessage(null));
                return;
            }
            inFlight = true;
            state = PENDING;
            render('Waiting for location permission — required to sign in.');

            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    inFlight = false;
                    latEl.value = Number(pos.coords.latitude).toFixed(7);
                    lngEl.value = Number(pos.coords.longitude).toFixed(7);
                    accEl.value = pos.coords.accuracy != null ? Math.round(pos.coords.accuracy) : '';
                    state = OK;
                    render('Location shared \u2713 ' + latEl.value + ', ' + lngEl.value);
                },
                function (err) {
                    inFlight = false;
                    clearCoords();
                    state = BLOCKED;
                    render(blockedMessage(err));
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        }

        form.addEventListener('submit', function (e) {
            if (state === OK && latEl.value !== '' && lngEl.value !== '') { return; }
            e.preventDefault();
            if (state === PENDING) { return; }   // still waiting on the prompt
            requestLocation();                    // denied/unavailable → re-ask
            return false;
        });

        if (retryBtn) {
            retryBtn.addEventListener('click', function () { requestLocation(); });
        }

        requestLocation();   // ask as soon as the page renders
    })();
    </script>
</body>
</html>
