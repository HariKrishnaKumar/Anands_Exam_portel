<?php
/**
 * Security response headers for the authentication pages.
 *
 * Scoped to login / signup / forgot-password rather than applied globally:
 * `X-Frame-Options: DENY` on every response would break the student dashboard,
 * which embeds its test list in a same-origin <iframe>. Auth screens are the
 * ones that genuinely need clickjacking protection, and they only pull in the
 * local stylesheet plus Google Fonts, so the CSP below stays accurate for them.
 *
 * Required near the top of the page, before any output.
 */

if (headers_sent()) {
    return;
}

// Never let browsers MIME-sniff a response into something executable.
header('X-Content-Type-Options: nosniff');

// Auth screens must not be rendered inside anyone else's frame.
header('X-Frame-Options: DENY');

// Send only the origin (never path/query) when leaving the site.
header('Referrer-Policy: strict-origin-when-cross-origin');

// Baseline 'self', widened only for the two things these pages actually load:
// their own stylesheet/scripts and the Google Fonts CSS + font files.
header(
    "Content-Security-Policy: default-src 'self'; "
    . "script-src 'self' 'unsafe-inline'; "
    . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
    . "font-src 'self' data: https://fonts.gstatic.com https://fonts.googleapis.com; "
    . "img-src 'self' data:; "
    . "connect-src 'self'; "
    . "frame-ancestors 'none'; "
    . "base-uri 'self'; "
    . "form-action 'self'"
);
