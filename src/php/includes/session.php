<?php
/**
 * Session management + CSRF protection.
 */

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Regenerate session ID every 30 minutes to prevent fixation
    if (!isset($_SESSION['_last_regenerated']) || (time() - $_SESSION['_last_regenerated']) > 1800) {
        session_regenerate_id(true);
        $_SESSION['_last_regenerated'] = time();
    }
    // Ensure CSRF token exists
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
