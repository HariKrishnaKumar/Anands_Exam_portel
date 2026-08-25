# Exam Portal — Full Project Audit Issues

**Audit date:** 2026-08-23
**Scope:** PHP backend (`src/php/`), Python analytics (`src/python/`), SQL layer (`sql/`), frontend (`assets/`, embedded JS), tests, docs, repo hygiene.
**Decision:** Forward-fix only (no git history scrubbing).

## Summary

| Severity | Count |
|----------|-------|
| CRITICAL | 6 |
| HIGH | 8 |
| MEDIUM | 14 |
| LOW | 8 |

---

## 🔴 CRITICAL

### C1. Live Gmail App Password committed to public repo
- **File:** `src/php/config/mail.php:42-44`
- **Detail:** `SMTP_USERNAME = 'test.dev.hari0003@gmail.com'` with a real Google App Password (`ukzb epeu crqm ezzq`) hardcoded; `MAIL_DEV_MODE = false`. `DEPLOYMENT.md` STEP 0 itself documents this leak but the fix was never applied.
- **Fix:** Revoke the app password in the Google account NOW. Move SMTP credentials to environment variables (`getenv()`), defaulting to dev mode. Since it's a public GitHub repo and history is not being scrubbed, revocation is the only real mitigation.

### C2. IDOR — answer submission has no ownership check
- **File:** `src/php/api/submit_answer.php:85`
- **Detail:** Query is `SELECT id, status FROM submissions WHERE id = ? AND test_id = ?` — never verifies `student_id` matches `$_SESSION['student_id']`. Any logged-in student can write answers into another student's submission by enumerating sequential submission IDs (auto-increment ints).
- **Fix:** Add `AND student_id = ?` binding `$_SESSION['student_id']`; return 404 when not found.

### C3. Flask debug mode on by default, bound to all interfaces, zero auth
- **Files:** `src/python/app.py:276-278` (debug + host), `92-189` (unauthenticated endpoints)
- **Detail:** `FLASK_DEBUG` defaults to `"1"` → Werkzeug interactive debugger (code-exec risk if PIN obtained). Binds `0.0.0.0` while printing a misleading `127.0.0.1` message. No token/API key on any endpoint: anyone on the network can read student PII (`app.py:163` returns name/email/roll_number) and trigger unauthenticated DB writes via `/api/pci/calculate`.
- **Fix:** Default `FLASK_DEBUG=0`; bind `127.0.0.1` unless explicitly configured; add shared-token auth header checked by all `/api/*` routes.

### C4. Fresh install from schema.sql is broken (schema ↔ migration drift)
- **Files:** `sql/migration_test_builder_wizard.sql:7,19`, `sql/migration_college_wizard.sql:7-19`, `sql/schema.sql` (whole)
- **Detail:**
  - `migration_test_builder_wizard.sql` does `ADD COLUMN ... AFTER \`passing_marks\`` but `passing_marks` exists nowhere → fails on any schema.sql-built DB. Also adds `'paused'`/`'scheduled'` statuses missing from schema.
  - `migration_college_wizard.sql` re-adds `admins.name` which schema.sql already has → duplicate-column failure. Its ~25 college columns + `college_streams`/`college_batches` tables are absent from schema.sql.
  - Aggregate drift: fresh installs silently lack `admins.role`, wizard fields, `admin_notifications`, `unverified_students`, `failed_login_log`, `tests.total_marks/test_type`, `batches.section/status`, `students.section/updated_at`, `test_sections`.
- **Fix:** Reconcile into one canonical fresh-install `schema.sql`; make ALTERs idempotent; add a `schema_migrations` tracking table.

### C5. Committed session cookie + real personal password
- **Files:** `-L` (repo root), `qa-run.mjs:6-7`, `tests/e2e_live_demo.spec.js:18`, `tests/evaluation.spec.js:11`, `tests/live-cycle.spec.js:19`
- **Detail:** Root file `-L` is a libcurl cookie jar containing a live-looking `PHPSESSID`. `Hari@2003` / `hariiphones83@gmail.com` are committed as QA credentials.
- **Fix:** Delete `-L`; replace hardcoded credentials with env vars or fixture accounts; invalidate exposed sessions.

### C6. Registration returns plaintext OTP unconditionally
- **File:** `src/php/includes/auth.php:451-465`
- **Detail:** `studentRegister()` returns `otp_dev => $otpResult['otp']` with comment "Only in dev mode" but there is **no dev-mode gate** — the OTP is returned to the client on every signup. If the OTP email fails, accounts can be self-verified without email access.
- **Fix:** Only include `otp_dev` when an explicit dev flag/config is set (e.g., `MAIL_DEV_MODE === true`).

---

## 🟠 HIGH

### H1. No test-access authorization for students
- **File:** `src/php/public/student/test.php:25-32`
- **Detail:** Test fetched by `id` alone; no check that the student's batch/section is assigned to the test (`test_sections`), or that the test is active for them. Any student can take any active test by changing `test_id`.
- **Fix:** Join against `test_sections` using `$_SESSION['batch_id']` for logged-in students.

### H2. Guest answer path allows overwriting arbitrary guest entries
- **File:** `src/php/api/submit_answer.php:58-77`
- **Detail:** Guest branch accepts any `guest_<id>` submission id without verifying it matches `$_SESSION['guest_entry_id']`. Any authenticated user can enumerate and overwrite other guests' `temp_data`.
- **Fix:** Compare `$guestEntryId === $_SESSION['guest_entry_id']`.

### H3. Tab-switch logging has same IDOR pattern
- **File:** `src/php/api/tab_switch.php:53`
- **Detail:** Same missing `student_id` ownership check as C2 — forged tab-switch logs can be planted against other students' submissions.
- **Fix:** Same ownership predicate.

### H4. Flask silent success for nonexistent submissions
- **File:** `src/python/app.py:104-152`
- **Detail:** Unknown `submission_id` yields all-zero scores → HTTP 200 full result body; PHP caller logs it as a successful recalculation. Also zero try/except around DB access → raw 500s (or debugger per C3).
- **Fix:** Return 404 when the submission lookup fails; wrap routes in error handlers returning JSON.

### H5. No security headers or session-cookie hardening anywhere
- **Files:** `src/php/includes/session.php` (whole), all pages
- **Detail:** No `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options` anywhere; `startSession()` never calls `session_set_cookie_params` (no httponly/samesite/secure). Admin soft-nav re-executes inline scripts from fetched HTML (`includes/admin_footer.php:461-508`) with no CSP backstop.
- **Fix:** Set cookie params in `session.php`; emit headers centrally (header includes or `.htaccess`).

### H6. Non-idempotent, order-fragile migrations, no version tracking
- **Files:** all `sql/migration_*.sql`
- **Detail:** Only `migration_students_updated_at.sql` guards with information_schema checks. Bare ALTERs everywhere fail on re-run. `migration_unverified.sql` deletes rows then drops columns it depends on from `migration_otp.sql`; `migration_batch_sections.sql` depends on `unverified_students`. No `schema_migrations` table exists.
- **Fix:** Fold into canonical schema (see C4); going forward use tracked, idempotent migrations.

### H7. Known default admin credential + fail-open brute-force check
- **Files:** `sql/schema.sql:25-28`, `src/php/includes/auth.php:104-108`
- **Detail:** `admin@testplatform.com` / `admin123` seeded and documented in repo. On DB error the throttle check returns "not locked" (documented fail-open).
- **Fix:** Force password change on first login or remove seed admin from production path; consider fail-closed for short window or at least alert loudly.

### H8. QA script mutates live data, bypasses test runner
- **File:** `qa-run.mjs:13,59-110`
- **Detail:** Logs in as admin, schedules/publishes/cancels real tests against localhost with no teardown; runs `headless:false`; lives outside Playwright (no retries/reports). Contains dead code and NaN-prone health score math (`qa-run.mjs:131,135`).
- **Fix:** Merge useful flows into `tests/` with fixture data + teardown, or delete.

---

## 🟡 MEDIUM

### M1. XSS-prone `innerHTML` DOM manipulation in dropdowns
- **Files:** `src/php/public/signup.php:296,306,334,344,375,385`; `src/php/public/admin/batches.php:319-342`; `src/php/public/admin/students.php:423-612`; `src/php/public/admin/test_builder.php:480,497`
- **Detail:** DB-derived names (`c.name`, `b.name`, `data.error`) concatenated into `innerHTML`; APIs (`api/get_courses.php` etc.) return raw JSON unescaped. Stored XSS possible via crafted names shown to admins.
- **Fix:** Build options with `document.createElement`/`textContent`, or escape server-side in API responses.

### M2. Unpinned CDN dependency on exam page, no SRI
- **File:** `src/php/public/student/test.php:200`
- **Detail:** `<script src="https://unpkg.com/lucide@latest">` floating `@latest`, no `integrity`/`crossorigin` on the highest-stakes page. Google Fonts render-blocking dependency too (lines 196-199).
- **Fix:** Vendor lucide locally under `assets/` or pin version with SRI.

### M3. Accessibility nearly absent on student pages
- **Files:** `src/php/public/student/**` (~9 `aria-*` usages across all public pages)
- **Detail:** Exam timer not `aria-live="polite"`; question-nav dots unlabeled anchors; radio groups lack `<fieldset>/<legend>`; zero `alt=` in test.php. Admin dashboard got partial a11y fixes; student flow did not.
- **Fix:** A11y pass on the exam flow.

### M4. CSS quality: 339 `!important` declarations
- **Files:** `assets/css/student.css` (165 of 4,067 lines file), `assets/css/admin.css` (174 of 6,170)
- **Detail:** Specificity wars driven by pervasive inline `style=` attributes in templates; duplicated page-scoped rules inside `test.php <style>` (lines 201-221).
- **Fix:** Incremental cleanup before adding features.

### M5. Dormant hardcoded `/test-platform/...` fetch paths in JS
- **Files:** `src/php/public/admin/batches.php:319,335,350`; `src/php/public/admin/students.php:410`
- **Detail:** Contradicts documented convention (path_bug_report.md): JS must use `BASE_URL . '/api'`. Works only because router.php strips prefixes; breaks under different alias.
- **Fix:** Inject API base like students.php partially does, consistently.

### M6. Missing FK + redundant index + precision mismatch in schema
- **File:** `sql/schema.sql:94,160-164,223-226`
- **Detail:** `guest_entries.test_id` has no FK constraint; `idx_pci_student` duplicates prefix of `uk_student_test_pci`; `total_marks_obtained/total_marks DECIMAL(10,2)` vs score columns `DECIMAL(6,2)` (>9999 marks overflow).
- **Fix:** Align in the canonical-schema pass (C4).

### M7. Seeder fabricates PCI data disconnected from marks
- **File:** `sql/seed_test_data.php:137,314-322`
- **Detail:** Random PCI scores (40–95) unrelated to actual computed marks; `created_by` hardcoded to admin id 1 (FK failure if admins reseeded); not idempotent.
- **Fix:** Derive PCI from seeded answers or clearly mark fabricated; look up admin id; add guard/reset.

### M8. Playwright config lacks webServer; stale artifact committed
- **Files:** `playwright.config.js:13-19`, `test-results.json` (tracked)
- **Detail:** Tests assume a server at `localhost:8000` already running — clean checkout fails. Committed `test-results.json` is stale output from another machine.
- **Fix:** Add `webServer` config running `php -S localhost:8000 router.php`; gitignore `test-results.json`.

### M9. Broken one-off scripts referencing foreign machine
- **Files:** `temp_validate.py:3-22`, `_check_auth.php`, `_seed_test.php`
- **Detail:** Hardcoded `C:\Users\ADMIN\Desktop\TEST\...` paths; `_seed_test.php` destructively DELETEs submissions/questions/students for test #2 with no environment guard.
- **Fix:** Delete or move out of repo.

### M10. CodeQL workflow ignores PHP — the primary language
- **File:** `.github/workflows/codeql.yml:44-49`
- **Detail:** Matrix is `javascript-typescript` + `python` only, for a ~35-file PHP codebase. Also verify checkout action pinning (`@v7` vs `codeql-action@v4`).
- **Fix:** Add `php` to matrix.

### M11. SECURITY.md is untouched template
- **File:** `SECURITY.md`
- **Detail:** GitHub boilerplate placeholder with fake version table; no vulnerability contact for a platform handling student PII.
- **Fix:** Write real policy.

### M12. DEPLOYMENT.md describes nonexistent infrastructure
- **File:** `DEPLOYMENT.md`
- **Detail:** Documents Render.com Docker deploy ("Apache+PHP+Flask one container") but no Dockerfile/render.yaml/docker-compose.yml exists anywhere. Its mandatory Step 0 security fix was never applied either.
- **Fix:** Either commit the deployment assets or rewrite guide to match reality (XAMPP / php -S / router.php).

### M13. README structure tree outdated
- **File:** `README.md:383-419`
- **Detail:** Omits `tests/`, `config/server/` (nginx/php-fpm/session-redis confs implying undocumented deployment targets), `docs/logic.md`, 3 of 10 SQL migrations. `package.json` metadata is placeholder (`"name": "test"`).
- **Fix:** Update structure section and package metadata.

### M14. Repo root clutter — ~25 non-project files tracked in git
- **Files:** `bugs.md`, `crash.txt`, `test_report.md`, `test.md`, `FIX_SUMMARY.md`, `FIXES_COMPLETED.md`, `WORK_COMPLETED.md`, `path_bug_report.md`, `ANALYTICS_CARD_REDESIGN.md`, `ADMIN_DASHBOARD.md`, `UI_SUGGESTIONS.md`, `image.png` (1.84 MB), `admin.png` (1.31 MB), `strix_runs/` (incl. SQLite DBs, SARIF, manifests referencing `C:\Users\ADMIN\Desktop\TEST`), `test-results.json`, `-L`, `_check_auth.php`, `_seed_test.php`, `temp_validate.py`, root `favicon.ico`
- **Detail:** AI-session reports, screenshots, security-scan artifacts and debug scripts deliberately committed.
- **Fix:** Move durable docs into `docs/`; delete artifacts; extend `.gitignore` (`strix_runs/`, `graphify-out/`, `test-results.json`, root PNGs, cookie files, `.claude/settings.local.json`).

---

## 🟢 LOW

| # | Issue | Location |
|---|-------|----------|
| L1 | Docstring says GET, endpoint is POST | `src/python/app.py:8 vs 92` |
| L2 | Hardcoded weights duplicated in INSERT instead of passed args | `src/python/app.py:129`, `helpers.php:245-250` |
| L3 | Dead import `build_radar_data`; radar chart unreachable | `src/python/app.py:25` |
| L4 | Deprecated MySQL `VALUES()` in ON DUPLICATE KEY UPDATE (8.0.20+) | `src/python/app.py:131-135`, `submit_answer.php:124` |
| L5 | Two connections per PCI request, autocommit race between read/write | `src/python/app.py:55,117` |
| L6 | Misleading startup message prints 127.0.0.1 while binding 0.0.0.0 | `src/python/app.py:277-278` |
| L7 | `getClientIp()` trusts X-Forwarded-For first → spoofable log IPs | `src/php/includes/auth.php:40-54` |
| L8 | `redirect()` falls back to JS redirect masking buffering bugs | `src/php/includes/helpers.php:33-38` |
| L9 | Remaining `match()` expression breaks PHP 7.x compat goal | `src/php/public/admin/colleges.php:856` |
| L10 | Favicon never wired (root favicon.ico unreferenced, no `<link rel=icon>`) | all pages |
| L11 | Magic timer constants 300/60; inline `onchange=` handlers | `src/php/public/student/test.php:232,297-314,391-392` |
| L12 | Notification poll replaces panel HTML wholesale (currently safe via server-side `h()`, fragile contract) | `admin_footer.php:373` |
| L13 | gunicorn pinned but unused; only run path is debug `python app.py` | `src/python/requirements.txt:3` |
| L14 | Tool configs committed (`.claude/settings.local.json`, `.gstack/`) | repo root |

---

## ✅ What's Good (no action needed)

- **SQL injection: clean** — prepared statements throughout PHP and Python; the one interpolated fragment (`assessment_management.php:126`) comes from a hardcoded whitelist map.
- **CSRF: consistent** — forms and state-changing APIs enforce tokens (`requireCsrf()`, hash_equals).
- **Auth design solid** — bcrypt (cost 10 passwords / 8 OTP), session regeneration, brute-force throttling via indexed log, failed-login audit trail, role-gated destructive endpoints (`delete_college.php`), transactional idempotent submission transitions.
- **Hybrid MCQ auto-grading pipeline** well designed (single transaction, conditional status flip prevents double-grading).
- **PCI math correct** — divide-by-zero guards, weight normalization, edge-case-safe distribution/stats (`analysis/pci.py`).
- All 21 admin pages inherit `requireAdmin()` via `admin_header.php:12`.

---

## Suggested Fix Order

1. **C1** revoke Gmail app password + env-var config *(user action required)*
2. **C2/H3/H2/H1** ownership + access authorization fixes
3. **C6** gate `otp_dev`
4. **C3/H4/L5/L6** Flask hardening
5. **C5/M9/M14/L14** secrets & repo hygiene cleanup
6. **C4/H6/M6** canonical schema reconciliation
7. **H5** session cookie params + security headers
8. **M1-M3** frontend security/a11y pass
9. **M7-M13** tooling & docs
