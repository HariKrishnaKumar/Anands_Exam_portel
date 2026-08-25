# 🐛 Bug Tracker — Student Test Platform

> Auto-generated audit. Last run: 24-Jul-2026
> Tool: DEBUGMASTER AI — Universal Software Debugging Engine

---

## Severity Legend

| Level | Definition |
|-------|------------|
| 🔴 **Critical** | Blocks functionality, breaks rendering, causes data loss |
| 🟠 **High** | Major feature broken, significant user impact |
| 🟡 **Medium** | Partial functionality, workaround exists |
| 🔵 **Low** | Minor, cosmetic, non-blocking |
| ⚪ **Info** | Note, observation, no action required |

---

## 🔴 Critical Bugs

### CRIT-001: SVG iconSprite() dumped inside `<style>` tag

**File:** `src/php/public/student/test.php` — Line 190

**Code:**
```php
<style><?= iconSprite() ?></style>
```

**Problem:** The `iconSprite()` function returns raw SVG `<svg>` markup with `<g>` definitions. Placing SVG elements inside a `<style>` HTML tag produces invalid HTML. The browser will not parse the SVG sprite, and all `use` references to icons throughout the test page will silently fail (no icons render).

**Root Cause:** Copy-paste error during the test page creation. All other student pages correctly place `<?= iconSprite() ?>` before the `<style>` block.

**Impact:** Zero icons render on the test-taking page. This affects:
- Timer bar icons
- Question navigation dots
- Form elements
- Warning banners

**Fix:**
```diff
-    <style><?= iconSprite() ?></style>
+    <?= iconSprite() ?>
     <style>
```

---

### CRIT-002: Assets path resolution broken — CSS doesn't load

**Files:** All student/admin PHP pages

**All pages use this pattern:**
```php
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/student.css">
```

**Problem:** `BASE_URL` is defined as:
- XAMPP: `/test-platform/src/php/public`
- Built-in server: `''` (empty)

The rendered URL resolves to:
- XAMPP: `/test-platform/src/php/public/assets/css/student.css` ❌
- Built-in: `/assets/css/student.css` ❌

Actual CSS location: `assets/css/student.css` (at project root, one level above `src/php/public/`)

The assets directory is **not** inside `src/php/public/`, so `BASE_URL` cannot be used for assets. There is no symlink, no `.htaccess` rewrite, and no `ASSETS_URL` constant.

**Impact:** CSS never loads. All pages render unstyled HTML.

**Root Cause:** The project has no separate asset URL constant. Assets and PHP files live at different levels in the directory tree but share the same URL prefix.

**Fix:** Add an `ASSETS_URL` constant in `config/db.php`:
```php
define('ASSETS_URL', '/test-platform/assets');  // XAMPP
// define('ASSETS_URL', '/assets');  // PHP built-in server
```
Then update all pages to use `<?= ASSETS_URL ?>/css/student.css`.

---

## 🟠 High Bugs

### HIGH-001: Hardcoded `/test-platform` asset paths in test.php

**File:** `src/php/public/student/test.php`

**Lines:** 54, 65, 119, 144, 161, 189

**Problem:** These inline HTML error pages use a hardcoded path that doesn't respect `BASE_URL`:
```php
<link rel="stylesheet" href="/test-platform/assets/css/student.css">
```

This path is also wrong — it's `/test-platform/assets/...` not `/test-platform/src/php/public/assets/...`.

**Impact:** When the test page shows error states (paused, stopped, submitted, not available, no questions), the CSS won't load and the error page renders unstyled.

**Root Cause:** These are standalone `echo` blocks that bypass the normal page template.

---

### HIGH-002: Hardcoded asset path in verify-otp.php

**File:** `src/php/public/verify-otp.php` — Line 84

```php
<link rel="stylesheet" href="/test-platform/assets/css/student.css">
```

Same issue as HIGH-001. The OTP verification page won't load CSS.

---

### HIGH-003: "My Tests" sidebar link is a dead placeholder

**Files:**
- `src/php/public/student/dashboard.php` (line 76)
- `src/php/public/student/results.php` (line 87)
- `src/php/public/student/profile.php` (line 66)
- `src/php/public/student/analytics.php` (line 112)

**Code:**
```php
<a href="#" class="sidebar-nav-item">
    <?= icon('test', 20) ?>
    <span>My Tests</span>
</a>
```

**Problem:** The "My Tests" link in every sidebar navigation has `href="#"` — a no-op. When clicked, it scrolls to the top of the page with no navigation.

**Impact:** Users can see "My Tests" in the nav but cannot click to reach a dedicated tests page. There is no `my-tests.php` or equivalent file.

---

### HIGH-004: Massive sidebar HTML duplication across 5 student pages

**Files:**
- `src/php/public/student/dashboard.php` (lines 56–122)
- `src/php/public/student/results.php` (lines 71–129)
- `src/php/public/student/profile.php` (lines 50–108)
- `src/php/public/student/analytics.php` (lines 96–154)
- `src/php/public/student/test.php`

**Problem:** The full sidebar HTML (~67 lines) is copy-pasted into every student page. The same applies to the top navigation bar (~25 lines). Any change to navigation requires editing all 5 files. This already caused the `href="#"` dead link bug (HIGH-003) to propagate to every page.

**Risk:** Extremely high maintenance burden. Future nav changes will forget to update all files.

**Suggestion:** Extract sidebar and topnav into shared includes:
- `includes/student_sidebar.php`
- `includes/student_topnav.php`

---

## 🟡 Medium Bugs

### MED-001: Missing `.mt-5` utility class

**File:** `src/php/public/verify-otp.php` — Line 169

**Code:**
```php
<div class="text-center mt-5">
```

**Problem:** The CSS defines utility classes `mt-1`, `mt-2`, `mt-3`, `mt-4`, `mt-6`, and `mt-8`, but **not** `mt-5`. The element gets no margin-top.

**Impact:** The "Resend OTP" section has no spacing above it, potentially overlapping elements.

**Fix:** Add `.mt-5` to the CSS utility classes in `student.css`.

---

### MED-002: PHP 8 `match()` expression — version compatibility

**File:** `src/php/public/student/dashboard.php` — Lines 296–301, 397–402

**Code:**
```php
$statusClass = match($t['status']) {
    'active'    => 'badge-active',
    'paused'    => 'badge-pending',
    'completed' => 'badge-success',
    default     => 'badge-info',
};
```

**Problem:** The `match()` expression is a PHP 8.0 feature. If the server runs PHP 7.x, these lines produce a fatal syntax error. Currently no version check or alternate code.

**Impact:** Full page crash on PHP 7.x servers.

**Fix:** Convert to `switch()` or ternary chain for backward compatibility:
```php
$statusClass = 'badge-info';
if ($t['status'] === 'active') $statusClass = 'badge-active';
elseif ($t['status'] === 'paused') $statusClass = 'badge-pending';
elseif ($t['status'] === 'completed') $statusClass = 'badge-success';
```

---

### MED-003: str_contains() / str_starts_with() — PHP 8 polyfill needed

**File:** `src/php/includes/helpers.php` — Line 13

**Code:**
```php
if (str_starts_with($url, $prefix)) {
```

**Problem:** `str_starts_with()` is a PHP 8.0 function. If the server is on PHP 7.x (common for XAMPP), this will fatal error.

**Fix:** Add a polyfill at the top of helpers.php:
```php
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}
```

---

## 🔵 Low Bugs

### LOW-001: analytics.php — Redundant SQL JOIN pattern

**File:** `src/php/public/student/analytics.php` — Lines 19–31

**Current query:**
```sql
SELECT t.title, ...
FROM tests t
LEFT JOIN submissions s ON s.test_id = t.id AND s.student_id = ?
JOIN batches b ON b.id = t.batch_id
JOIN students st ON st.batch_id = b.id
WHERE st.id = ?
```

**Problem:** The `JOIN batches` + `JOIN students` chain is unnecessary. The batch relationship could be resolved directly from `tests.batch_id` and the student's batch from `students.batch_id`. The query also passes `$studentId` as parameter twice (same value). While it works, it's needlessly complex.

**Suggestion:** Simplify to:
```sql
SELECT t.title, ...
FROM tests t
LEFT JOIN submissions s ON s.test_id = t.id AND s.student_id = ?
WHERE t.batch_id = (SELECT batch_id FROM students WHERE id = ?)
ORDER BY t.start_time DESC
```

---

### LOW-002: verify-otp.php — Missing iconSprite() call

**File:** `src/php/public/verify-otp.php`

**Problem:** The page does not call `<?= iconSprite() ?>` before the closing `</body>`, so any SVG `<use>` references would fail. Currently the page uses inline SVGs but doesn't call `icon()` helper. If updated to use icons later, they won't render.

---

### LOW-003: Dashboard stat cards use `icon('arrow-right', 14)` as decoration

**Files:** All student pages

**Problem:** Stat card arrows use `icon('arrow-right', 14)` but aren't wrapped in interactive links. Screen readers will see the arrow icon with no semantic meaning. These should be decorative (`aria-hidden="true"`).

---

### LOW-004: No favicon in any page

**Files:** All public PHP files

**Problem:** No `<link rel="icon">` anywhere. Browser tabs show a generic icon.

---

### LOW-005: Timer critical state uses bare `300` and `60` constants

**File:** `src/php/public/student/test.php` — Line 221, 334–335

**Code:**
```php
class="<?= $remaining < 300 ? ($remaining < 60 ? 'danger' : 'warning') : '' ?>"
...
timerDisplay.classList.toggle('warning', remainingSeconds < 300 && remainingSeconds >= 60);
timerDisplay.classList.toggle('danger', remainingSeconds < 60);
```

**Suggestion:** Define named constants for warning/danger thresholds (`WARNING_THRESHOLD = 300`, `DANGER_THRESHOLD = 60`) to improve maintainability.

---

## ⚪ Informational Notes

### INFO-001: 45 defined icons are never used

The `includes/icons.php` defines 79 icons but only 34 are used across the project. Unused icons include: `activity`, `archive`, `clipboard`, `code`, `collapse`, `copy`, `database`, `document`, `export`, `file`, `folder`, `help`, `home`, `lock`, `login`, `mail`, `notifications`, `refresh`, `reports`, `search`, `settings`, `shield`, `timer`, `upload`, `user`, `users`, `x`, and more.

This is not a bug but indicates the icon library was added preemptively. Consider pruning unused icons.

### INFO-002: `redirect()` function handles hardcoded paths intentionally

The `redirect()` function in `helpers.php` strips the `/test-platform/src/php/public` prefix from URLs before prepending `BASE_URL`. This means hardcoded redirect paths like:
```php
redirect('/test-platform/src/php/public/admin/dashboard.php');
```
...work correctly in both XAMPP and CLI-server modes. This is by design and not a bug.

### INFO-003: No CSRF issues found

All POST forms across the application properly include `csrfField()` and validate tokens server-side. No CSRF vulnerabilities detected.

### INFO-004: All include/require paths resolve correctly

No broken includes found. All 40+ `include`/`require` statements across the codebase point to existing files.

---

## Summary

| Severity | Count | Key Issues |
|----------|-------|------------|
| 🔴 Critical | 2 | SVG in `<style>` tag, Assets path broken |
| 🟠 High | 4 | Hardcoded paths, Dead nav link, Duplicate HTML |
| 🟡 Medium | 3 | Missing CSS classes, PHP 8 compat |
| 🔵 Low | 5 | SQL complexity, Missing sprite, a11y |
| ⚪ Info | 4 | Icon usage, CSRF, Includes |

**Total actionable bugs: 14**

---

*Generated by DEBUGMASTER AI — Universal Senior Software Debugging Engine*
