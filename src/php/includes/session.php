<?php
/**
 * Session management + CSRF protection.
 */

/** Session lifetime in seconds (30 minutes). */
define('SESSION_TIMEOUT', 1800);

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // ─── Cookie hardening (H-01, H-02, M-05) ───
        session_set_cookie_params([
            'lifetime' => SESSION_TIMEOUT,
            'path'     => '/',
            'httponly'  => true,
            'secure'   => false, // Set true in production with HTTPS
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    // ─── Session timeout enforcement (M-06) ───
    // If _login_ts exists and the session has been idle too long, destroy it.
    if (isset($_SESSION['_login_ts']) && (time() - $_SESSION['_login_ts']) > SESSION_TIMEOUT) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        // Caller will re-create session on next startSession() call
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Reset CSRF token for fresh session
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return;
    }

    // Regenerate session ID every 30 minutes to prevent fixation
    if (!isset($_SESSION['_last_regenerated']) || (time() - $_SESSION['_last_regenerated']) > 1800) {
        session_regenerate_id(true);
        $_SESSION['_last_regenerated'] = time();
    }

    // Update last activity timestamp for timeout tracking
    if (isset($_SESSION['_login_ts'])) {
        $_SESSION['_login_ts'] = time();
    }

    // Ensure CSRF token exists
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // ─── Security headers (H-03/H-04/H-05/M-08/M-09/M-10) ───
    if (!headers_sent()) {
        // Remove PHP version disclosure (H-11)
        header_remove('X-Powered-By');
        // Prevent MIME sniffing (M-08)
        header('X-Content-Type-Options: nosniff');
        // Prevent clickjacking (H-04)
        header('X-Frame-Options: DENY');
        // XSS protection for legacy browsers (M-09)
        header('X-XSS-Protection: 1; mode=block');
        // Referrer policy (M-10)
        header('Referrer-Policy: strict-origin-when-cross-origin');
        // Permissions policy
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        // Content Security Policy (H-03) — strict whitelist
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://unpkg.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
        // HSTS (H-05) — only enable in production with HTTPS
        // header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function getCsrfToken(): string {
    return $_SESSION['csrf_token'] ?? '';
}

/**
 * Render a hidden CSRF input field.
 */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate CSRF token from POST/GET.
 * Call at the start of every form processing endpoint.
 */
function validateCsrfToken(?string $token = null): bool {
    $token = $token ?? ($_POST['csrf_token'] ?? ($_GET['csrf_token'] ?? ''));
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Require valid CSRF token or die.
 */
function requireCsrf(): void {
    if (!validateCsrfToken()) {
        http_response_code(403);
        die(json_encode(['error' => 'Invalid or missing CSRF token. Please refresh and try again.']));
    }
}
