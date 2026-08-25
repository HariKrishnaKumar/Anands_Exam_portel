<?php
/**
 * Fluent 2 Test Platform - Database Configuration
 * 
 * Switch between local and production by changing DB_ENV.
 */

require_once __DIR__ . '/env.php';

define('DB_ENV', env('DB_ENV', 'local')); // 'local' or 'production'

// Set PHP timezone to match MySQL (Asia/Kolkata = UTC+5:30).
// Change to your local timezone if different.
date_default_timezone_set('Asia/Kolkata');

if (DB_ENV === 'production') {
    // Production settings — ALL values injected via environment (.env / platform vars)
    define('DB_HOST', env('DB_HOST', '127.0.0.1'));
    define('DB_PORT', env('DB_PORT', '3306'));
    define('DB_NAME', env('DB_NAME', 'test_platform'));
    define('DB_USER', env('DB_USER', 'root'));
    define('DB_PASS', env('DB_PASS', ''));
} else {
    // Local settings — values come from .env so nothing sensitive is in source
    define('DB_HOST', env('DB_HOST', '127.0.0.1'));
    define('DB_PORT', env('DB_PORT', '3306'));
    define('DB_NAME', env('DB_NAME', 'test_platform'));
    define('DB_USER', env('DB_USER', 'root'));
    define('DB_PASS', env('DB_PASS', ''));
}

define('PYTHON_API_URL', 'http://127.0.0.1:5000');

/*
 * BASE_URL: URL prefix for all redirects and links.
 * - PHP built-in server (cli-server): empty string — doc root is public/
 * - XAMPP / Apache: '/test-platform/src/php/public'
 */
if (php_sapi_name() === 'cli-server') {
    define('BASE_URL', '');
    define('ASSETS_URL', '/assets');
} else {
    define('BASE_URL', '/test-platform/src/php/public');
    define('ASSETS_URL', '/test-platform/assets');
}

/**
 * Get PDO database connection.
 * All queries MUST use this with prepared statements.
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/**
 * Global error/exception handlers → notification bell.
 * Registered once per request; they only run on uncaught failures and
 * never suppress the default PHP handling (return false = continue).
 * notifyAdmin() lives in helpers.php — the function_exists guard covers
 * include order differences across entry points (APIs, pages).
 */
if (!function_exists('__registerNotificationHandlers')) {
    function __registerNotificationHandlers(): bool { return true; }
    set_error_handler(function (int $errno, string $errstr, string $errfile = '', int $errline = 0): bool {
        if (!(error_reporting() & $errno)) return false; // @-suppressed or non-fatal noise
        $interesting = [E_ERROR, E_WARNING, E_USER_ERROR, E_USER_WARNING, E_RECOVERABLE_ERROR];
        if (!in_array($errno, $interesting, true)) return false;
        if (function_exists('notifyAdmin')) {
            notifyAdmin('system', 'PHP Error [' . $errno . ']', $errstr . ' — ' . basename($errfile) . ':' . $errline);
        }
        return false; // keep default handler behavior
    });
    set_exception_handler(function (Throwable $e): void {
        if (function_exists('notifyAdmin')) {
            notifyAdmin('system', 'Uncaught Exception', $e->getMessage() . ' — ' . basename($e->getFile()) . ':' . $e->getLine());
        }
    });
}
