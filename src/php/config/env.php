<?php
/**
 * Minimal .env loader (no Composer dependency).
 *
 * Usage:  require_once __DIR__ . '/env.php';
 *         $val = env('SMTP_PASSWORD', 'fallback');
 *
 * Looks for a .env file at (first match wins):
 *   1. project root  (../../.env relative to this file)
 *   2. src/php/.env
 *
 * .env is gitignored — real secrets live ONLY there.
 * Commit .env.example as the template instead.
 */

if (!function_exists('env')) {
    /**
     * Get an environment variable: real env var first, then .env file, then default.
     */
    function env(string $key, ?string $default = null): ?string
    {
        // 1. Real environment variable / getenv (Render, Oracle Cloud, etc.)
        $v = getenv($key);
        if ($v !== false && $v !== '') {
            return $v;
        }

        // 2. Value loaded from .env into $_ENV by load_env_file()
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }

        return $default;
    }

    /**
     * Parse a .env file into $_ENV (once per request).
     * Supports: KEY=value, # comments, "quoted" values, blank lines.
     */
    function load_env_file(string $path): void
    {
        static $loaded = [];
        if (isset($loaded[$path]) || !is_readable($path)) {
            return;
        }
        $loaded[$path] = true;

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue; // skip comments & blanks
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key   = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            // strip surrounding quotes
            $len = strlen($value);
            if ($len >= 2 && (($value[0] === '"' && $value[$len - 1] === '"') || ($value[0] === "'" && $value[$len - 1] === "'"))) {
                $value = substr($value, 1, -1);
            }

            // never override real environment variables
            if (getenv($key) === false) {
                $_ENV[$key] = $value;
            }
        }
    }

    // Auto-load the project-root .env on include.
    load_env_file(dirname(__DIR__, 2) . '/.env');
}
