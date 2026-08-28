# Security Audit Report — Yajurvedh Exam Portal
**Date:** 2026-08-28 | **Scope:** SAST + DAST + SCA | **Codebase:** PHP 8.2 + MySQL 8 + Flask + Playwright

---

## Executive Summary

| Category | CRITICAL | HIGH | MEDIUM | LOW | INFO |
|----------|----------|------|--------|-----|------|
| SAST — SQL Injection | 0 | 0 | 1 | 3 | 1 |
| SAST — XSS | 0 | 1 | 3 | 2 | 0 |
| SAST — Auth/Session/CSRF | 0 | 2 | 4 | 4 | 7 |
| SAST — Secrets/Path/Upload | 1 | 2 | 4 | 2 | 1 |
| DAST — HTTP Security | 0 | 5 | 4 | 1 | 5 |
| SCA — Dependencies | 0 | 3 | 1 | 2 | 1 |
| **TOTAL** | **1** | **13** | **17** | **14** | **15** |

**Risk Rating: HIGH** — 1 CRITICAL + 13 HIGH severity findings require immediate remediation before production deployment.

---

## CRITICAL Findings (1)

### C-01: Hardcoded SMTP Password in .env
- **Category:** Hardcoded Secret
- **File:** `.env:13`
- **Evidence:** `SMTP_PASSWORD=xabw atyx pblm jipa`
- **Impact:** Full email account takeover if .env is leaked via misconfiguration, backup, or disk access
- **Fix:** Rotate the Gmail App Password immediately. Use a secrets manager for production.

---

## HIGH Findings (13)

### H-01: Missing session.cookie_httponly
- **Category:** Session Security | **File:** `src/php/includes/session.php`
- **Evidence:** No `session_set_cookie_params()` or `ini_set('session.cookie_httponly')` before `session_start()`
- **Impact:** Session cookie accessible via JavaScript — XSS = full session hijack
- **Fix:** Add `session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax'])` before `session_start()`

### H-02: Missing session.cookie_secure
- **Category:** Session Security | **File:** `src/php/includes/session.php`
- **Evidence:** Cookie sent over HTTP without Secure flag
- **Impact:** Session hijacking via network sniffing on public WiFi
- **Fix:** Add `'secure' => true` in production (requires HTTPS)

### H-03: No Content-Security-Policy Header
- **Category:** HTTP Headers | **Evidence:** No CSP header on any response
- **Impact:** No defense against XSS, inline script injection, or data exfiltration
- **Fix:** Implement strict CSP: `default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; frame-ancestors 'none';`

### H-04: No X-Frame-Options Header
- **Category:** Clickjacking | **Evidence:** Login page fully frameable
- **Impact:** Login form can be embedded in attacker's iframe for credential theft
- **Fix:** Add `X-Frame-Options: DENY` and CSP `frame-ancestors 'none'`

### H-05: No HSTS Header
- **Category:** Transport Security | **Evidence:** No Strict-Transport-Security header
- **Impact:** Users can be downgraded to HTTP via MITM attacks
- **Fix:** Add `Strict-Transport-Security: max-age=31536000; includeSubDomains`

### H-06: DOM-Based Stored XSS via innerHTML
- **Category:** XSS | **Files:** `signup.php`, `admin/students.php`, `admin/batches.php`, `admin/test_builder.php`
- **Evidence:** API data (`c.name`, `b.name`) inserted via `innerHTML` without escaping
- **Impact:** Malicious course/batch name executes JS in every user's browser
- **Fix:** Use `textContent` instead of `innerHTML` for option text, or create a JS `escapeHtml()` helper

### H-07: gunicorn HTTP Request Smuggling (CVE-2024-6827)
- **Category:** SCA | **File:** `src/python/requirements.txt`
- **Evidence:** `gunicorn==21.2.0` — CVSS 7.5 HIGH
- **Impact:** Cache poisoning, session hijacking, SSRF
- **Fix:** `pip install gunicorn==26.2.0`

### H-08: mysql-connector-python Takeover (CVE-2024-21272)
- **Category:** SCA | **File:** `src/python/requirements.txt`
- **Evidence:** `mysql-connector-python==8.2.0` — CVSS 7.5 HIGH
- **Impact:** Database connector takeover by network attacker
- **Fix:** `pip install mysql-connector-python==26.7.0`

### H-09: Unpinned CDN Import (lucide@latest)
- **Category:** Supply Chain | **Files:** 10 PHP files (student pages + admin_header.php)
- **Evidence:** `<script src="https://unpkg.com/lucide@latest"></script>`
- **Impact:** Supply chain attack — malicious JS loaded on every page
- **Fix:** Pin version + add SRI hash: `<script src="https://unpkg.com/lucide@0.460.0/dist/umd/lucide.min.js" integrity="sha384-XXXXX" crossorigin="anonymous"></script>`

### H-10: Dangerous HTTP Methods Allowed
- **Category:** DAST | **Evidence:** DELETE/PUT/PATCH accepted on login.php and admin pages (200 OK)
- **Impact:** CSRF amplification, potential cache poisoning
- **Fix:** Return 405 Method Not Allowed for non-GET/POST on state-changing endpoints

### H-11: Server Info Disclosure (PHP Version)
- **Category:** DAST | **Evidence:** `X-Powered-By: PHP/8.2.12` on every response
- **Impact:** Attacker can target known PHP 8.2.12 CVEs
- **Fix:** Set `expose_php = Off` in php.ini

### H-12: SPA Navigation innerHTML Amplifier
- **Category:** XSS | **File:** `src/php/includes/admin_footer.php:529`
- **Evidence:** `main.innerHTML = newMain.innerHTML` — any XSS in admin pages triggers during SPA nav
- **Impact:** XSS in any admin page executes within authenticated session
- **Fix:** Use DOM manipulation methods instead of innerHTML for content swap

### H-13: Hardcoded Admin Password in schema.sql
- **Category:** Hardcoded Secret | **File:** `sql/schema.sql:25-28`
- **Evidence:** `password: admin123` documented in comment, committed to repo
- **Impact:** Default admin credentials known to anyone with repo access
- **Fix:** Remove plaintext password from comments; enforce password change on first login

---

## MEDIUM Findings (17)

| # | Finding | Category | File |
|---|---------|----------|------|
| M-01 | LIKE wildcard injection in search (unescaped %/_) | SQL Injection | `admin/students.php`, `admin/colleges.php`, `admin/failed_logins.php`, `admin/question_library.php` |
| M-02 | Signup OTP has no max-attempts lockout | Auth | `verify-otp.php` / `auth.php` |
| M-03 | Forgot-password rate limit is session-only (bypassable) | Auth | `forgot-password.php` |
| M-04 | IP spoofing via X-Forwarded-For in brute-force logging | Auth | `auth.php:40-53` |
| M-05 | Missing session.cookie_samesite | Session | `session.php` |
| M-06 | No session timeout (_login_ts set but never checked) | Session | `auth.php:137` |
| M-07 | Logout via GET with no CSRF token (CSRF DoS) | CSRF | `logout.php` |
| M-08 | No X-Content-Type-Options header | HTTP Headers | All pages |
| M-09 | No X-XSS-Protection header | HTTP Headers | All pages |
| M-10 | No Referrer-Policy header | HTTP Headers | All pages |
| M-11 | Router path traversal without realpath() | Path Traversal | `router.php:23,67,100` |
| M-12 | CSV import: no MIME check, no is_uploaded_file() | File Upload | `test_builder.php:116`, `assessment_studio.php:138` |
| M-13 | Debug/seed scripts in project root | Info Disclosure | `_seed_test.php`, `check_admins.php`, `run_migration.php` |
| M-14 | No rate limiting on signup/OTP/password reset | Brute Force | `signup.php`, `verify-otp.php`, `forgot-password.php` |
| M-15 | 6-digit OTP with no lockout = brute-forceable | Auth | `auth.php:158,484` |
| M-16 | flask session cache poisoning (CVE-2026-27205) | SCA | `requirements.txt` |
| M-17 | SPA navigation amplifies any XSS via innerHTML | XSS | `admin_footer.php:529` |

---

## LOW Findings (14)

| # | Finding | Category | File |
|---|---------|----------|------|
| L-01 | Interpolated LIMIT/OFFSET in SQL (mitigated by int cast) | SQL | `pending_verifications.php`, `failed_logins.php` |
| L-02 | Dynamic IN clause in submit_answer (mitigated by intval) | SQL | `submit_answer.php` |
| L-03 | Missing h() on status in profile page | XSS | `student/profile.php:258` |
| L-04 | Missing h() on student name initial | XSS | `student/profile.php:136` |
| L-05 | Brute-force check fails open on DB error | Auth | `auth.php:106-108` |
| L-06 | Password reset OTP lockout only 30 seconds | Auth | `forgot-password.php:17-18` |
| L-07 | Guest login doesn't regenerate session ID | Session | `auth.php:598-618` |
| L-08 | Sequential student_id in URL | Info Leak | `signup.php:62-66` |
| L-09 | Missing CSRF on CSV import | CSRF | `assessment_studio.php:138` |
| L-10 | Router no canonicalization before readfile/require | Path | `router.php:45,73,118` |
| L-11 | Python API URL construction (SSRF potential if extended) | SSRF | `helpers.php:148` |
| L-12 | Email leaked in OTP redirect URL | Info Leak | `login.php:121` |
| L-13 | chart.js CDN (pinned, no CVEs) | SCA | `reports.php` |
| L-14 | @playwright/test (dev-only, no CVEs) | SCA | `package.json` |

---

## Positive Security Controls (What's Done Right)

| Control | Status | Evidence |
|---------|--------|----------|
| **PDO Prepared Statements** | ✅ Excellent | `ATTR_EMULATE_PREPARES => false`, all queries parameterized |
| **CSRF Protection** | ✅ Excellent | 256-bit tokens, `hash_equals()`, enforced on all POST forms |
| **XSS Encoding** | ✅ Strong | `h()` helper used consistently across output contexts |
| **Password Hashing** | ✅ Excellent | bcrypt cost 10 for passwords, cost 8 for OTPs |
| **Session Fixation** | ✅ Fixed | `session_regenerate_id(true)` after login + 30-min rotation |
| **Login Brute-Force** | ✅ Implemented | 5 attempts / 15-min window, DB-backed |
| **OTP Expiration** | ✅ Enforced | 10-minute expiry on all OTPs |
| **.env Gitignored** | ✅ Confirmed | Never committed to git history |
| **Input Type Casting** | ✅ Consistent | `(int)` cast on all user-supplied IDs |
| **No Dangerous Functions** | ✅ Clean | No `eval()`, `exec()`, `system()`, `unserialize()` in production |
| **No phpinfo()** | ✅ Clean | Not found in any file |
| **No Directory Listing** | ✅ Blocked | /src/, /sql/ return 404 |
| **No CORS Misconfig** | ✅ Safe | No Access-Control-Allow-Origin headers |

---

## Remediation Priority

### Immediate (Before Production)
1. **Session cookie hardening** — Add httponly/secure/samesite to `session.php` (H-01, H-02, M-05)
2. **Security headers** — Add CSP, X-Frame-Options, HSTS, X-Content-Type-Options (H-03, H-04, H-05, M-08)
3. **Pin lucide CDN** — Replace `@latest` with pinned version + SRI (H-09)
4. **Upgrade Python deps** — gunicorn, mysql-connector-python, flask (H-07, H-08, M-16)
5. **OTP rate limiting** — Add max-attempts to signup OTP verification (M-02, M-14)
6. **Remove PHP version disclosure** — Set `expose_php = Off` (H-11)
7. **Remove hardcoded admin password** from schema.sql comments (H-13)

### Short-Term (Within 1 Week)
8. **DOM XSS fix** — Replace innerHTML with textContent in JS dropdowns (H-06)
9. **Logout CSRF** — Change to POST with CSRF token (M-07)
10. **Session timeout** — Enforce _login_ts check in startSession() (M-06)
11. **IP spoofing fix** — Use only REMOTE_ADDR or validate proxy IPs (M-04)
12. **CSV upload validation** — Add is_uploaded_file() + MIME check (M-12)
13. **HTTP method restriction** — Return 405 for DELETE/PUT/PATCH (H-10)
14. **Delete dev scripts** from project root (M-13)

### Medium-Term (Within 1 Month)
15. **Path traversal hardening** — Add realpath() check in router.php (M-11, L-10)
16. **LIKE wildcard escaping** — Escape % and _ in search queries (M-01)
17. **SPA navigation security** — Use DOM manipulation instead of innerHTML (H-12)
18. **Missing h() calls** — Fix 2 instances in profile.php (L-03, L-04)

---

*Report generated by SAST + DAST + SCA automated security audit on 2026-08-28*
