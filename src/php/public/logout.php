<?php
/**
 * Logout endpoint — POST-only with CSRF validation (M-07 fix).
 * Any GET request to this URL is redirected back to login.
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrfToken()) {
    logout();
    redirect('/login.php');
} else {
    // GET or invalid CSRF — just redirect (never logout via GET)
    redirect('/login.php');
}
