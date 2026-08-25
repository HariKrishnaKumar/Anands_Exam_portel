# Exam Portal — Developer Quick Start Guide

**Last Updated:** August 21, 2026  
**Environment:** PHP 8.3.32, MySQL 8.0, Node.js (optional)

---

## Table of Contents

1. [Project Structure](#project-structure)
2. [Setup & Installation](#setup--installation)
3. [Running the Application](#running-the-application)
4. [Key Files & Locations](#key-files--locations)
5. [Common Development Tasks](#common-development-tasks)
6. [Debugging](#debugging)
7. [Contributing Guidelines](#contributing-guidelines)

---

## Project Structure

```
exam-portal/
├── src/
│   ├── php/
│   │   ├── api/                    # AJAX endpoints
│   │   │   ├── submit_answer.php   # Answer persistence
│   │   │   ├── tab_switch.php      # Tab switch logging
│   │   │   └── get_*.php           # Data fetch endpoints
│   │   ├── config/
│   │   │   ├── db.php              # Database + BASE_URL + ASSETS_URL config
│   │   │   └── mail.php            # Email configuration
│   │   ├── includes/
│   │   │   ├── admin_header.php    # Admin layout header
│   │   │   ├── admin_footer.php    # Admin layout footer
│   │   │   ├── student_header.php  # Student layout header (NEW)
│   │   │   ├── student_footer.php  # Student layout footer (NEW)
│   │   │   ├── session.php         # Session management
│   │   │   ├── auth.php            # Authentication helpers
│   │   │   ├── helpers.php         # Utility functions
│   │   │   ├── mailer.php          # Email helper
│   │   │   ├── icons.php           # Icon system
│   │   │   └── admin_footer.php    # Footer template
│   │   └── public/
│   │       ├── index.php           # Root redirect
│   │       ├── login.php           # Login page
│   │       ├── signup.php          # Student registration
│   │       ├── guest.php           # Guest token entry
│   │       ├── logout.php          # Session destroy
│   │       ├── verify-otp.php      # OTP verification
│   │       ├── admin/              # Admin pages
│   │       │   ├── dashboard.php   # Overview (stat cards, charts, activity)
│   │       │   ├── colleges.php    # Institution CRUD
│   │       │   ├── courses.php     # Course CRUD (cascading)
│   │       │   ├── batches.php     # Batch CRUD (cascading)
│   │       │   ├── students.php    # Student management + guest links
│   │       │   ├── test_builder.php # Test creation interface
│   │       │   ├── assessment_studio.php # Assessment builder
│   │       │   ├── grading.php     # Manual grading interface
│   │       │   ├── reports.php     # PCI analytics + charts
│   │       │   ├── tab_switcher.php # Tab switch monitoring
│   │       │   ├── live_monitor.php # Real-time activity
│   │       │   ├── activity_logs.php # Audit trail
│   │       │   ├── notifications.php # Admin notifications
│   │       │   ├── settings.php    # Admin settings
│   │       │   ├── failed_logins.php # Failed login audit
│   │       │   ├── question_library.php # Question management
│   │       │   ├── assessment_management.php # Test lifecycle
│   │       │   ├── help.php        # Help documentation
│   │       │   └── pending_verifications.php # Unverified students
│   │       └── student/
│   │           ├── dashboard.php   # Test list (uses student_header.php)
│   │           ├── test.php        # Test-taking interface
│   │           ├── results.php     # Results view (uses student_header.php)
│   │           ├── analytics.php   # Performance analytics (uses student_header.php)
│   │           └── profile.php     # Student profile (uses student_header.php)
│   └── python/
│       ├── app.py                  # Flask analytics API
│       ├── requirements.txt        # Python dependencies
│       └── analysis/
│           ├── pci.py              # Performance/Competency Index calculation
│           └── charts.py           # Chart data builders
├── sql/
│   ├── schema.sql                  # Database tables + structure
│   ├── seed_test_data.php          # Test data seeder
│   └── migration_*.sql             # Database migrations
├── assets/
│   └── css/
│       ├── admin.css               # Admin theme (Fluent 2)
│       └── student.css             # Student theme (Fluent 2)
├── tests/
│   ├── analytics-grid.spec.js      # Playwright E2E tests
│   └── fixtures/
│       └── analytics-grid-test.html # Test HTML fixtures
├── docs/
│   └── logic.md                    # Logic documentation
├── SRS/
│   ├── MASTER_SRS.html             # Complete specification
│   ├── STUDENT_PORTAL_SRS.html     # Student portal spec
│   └── SRS.html                    # Admin system spec
├── router.php                      # Development server router
├── playwright.config.js            # E2E test configuration
├── package.json                    # Node dependencies
├── ADMIN_DASHBOARD.md              # Admin UI specification (EXPANDED)
├── FIXES_COMPLETED.md              # Bug fix summary (NEW)
├── DEVELOPER_QUICK_START.md        # This file (NEW)
├── README.md                       # Project overview
└── MANUAL.md                       # User manual

```

---

## Setup & Installation

### Prerequisites

- **PHP:** 8.3+ (with PDO MySQL extension)
- **MySQL:** 8.0+
- **Node.js:** 16+ (optional, for Playwright E2E tests)
- **Git:** Latest version

### 1. Clone the Repository

```bash
git clone https://github.com/your-org/exam-portal.git
cd exam-portal
```

### 2. Configure Database

**XAMPP Local Setup:**
```bash
# Start XAMPP services
# Place project in: C:\xampp\htdocs\test-platform\

# Import schema
mysql -u root -p test_platform < sql/schema.sql

# (Optional) Seed test data
php sql/seed_test_data.php
```

**PHP CLI Server Setup:**
```bash
# No XAMPP needed - uses built-in PHP server
# Runs from project root: C:\Users\...\Exam_portel\
```

### 3. Configure Environment

Edit `src/php/config/db.php`:

```php
// Local (XAMPP)
define('DB_ENV', 'local');
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'test_platform');
define('DB_USER', 'root');
define('DB_PASS', '');

// Python API endpoint
define('PYTHON_API_URL', 'http://127.0.0.1:5000');
```

### 4. (Optional) Set Up Python API

```bash
cd src/python
pip install -r requirements.txt
python app.py
# Runs on http://localhost:5000
```

### 5. Install Dependencies (Playwright Tests)

```bash
npm install
npx playwright install
```

---

## Running the Application

### Option A: XAMPP (Recommended for Windows)

1. Start XAMPP Apache + MySQL
2. Navigate to: `http://localhost/test-platform/src/php/public/`
3. Default credentials:
   - **Admin:** admin@example.com / password
   - **Student:** (register via signup page)

### Option B: PHP Built-in Server

```bash
# From project root
php -S localhost:8000 router.php

# Navigate to: http://localhost:8000/
```

### Option C: Docker (Optional)

```bash
docker-compose up -d
# Auto-starts PHP, MySQL, Flask services
```

---

## Key Files & Locations

### Configuration

| File | Purpose | Edit When |
|------|---------|-----------|
| `src/php/config/db.php` | Database, BASE_URL, ASSETS_URL | Changing servers/database |
| `src/php/config/mail.php` | Email settings | Configuring SMTP |
| `router.php` | Dev server routing | Debugging URL issues |
| `playwright.config.js` | E2E test config | Changing test settings |

### Templates & Layouts

| File | Purpose | Used By |
|------|---------|---------|
| `src/php/includes/admin_header.php` | Admin page layout | All admin/* pages |
| `src/php/includes/admin_footer.php` | Admin footer | All admin/* pages |
| `src/php/includes/student_header.php` | Student layout | student/dashboard, results, analytics, profile |
| `src/php/includes/student_footer.php` | Student footer | Same as above |
| `src/php/includes/icons.php` | Icon system | All pages |

### API Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/submit_answer.php` | POST | Save test answers |
| `/api/tab_switch.php` | POST | Log tab switches |
| `/api/get_courses.php` | GET | Fetch courses by college |
| `/api/get_batches.php` | GET | Fetch batches by course |
| `/api/get_course_college.php` | GET | Fetch college by course |

### CSS & Design

| File | Purpose | Size |
|------|---------|------|
| `assets/css/admin.css` | Admin Fluent 2 theme | 15 KB |
| `assets/css/student.css` | Student Fluent 2 theme | 12 KB |

### Database

| File | Purpose |
|------|---------|
| `sql/schema.sql` | 12-table schema (colleges, courses, batches, students, tests, questions, answers, submissions, notifications, guest_entries, admin_notifications, activity_logs) |
| `sql/migration_*.sql` | Database schema updates |
| `sql/seed_test_data.php` | Sample data generator |

---

## Common Development Tasks

### Task 1: Add a New Admin Page

1. Create file: `src/php/public/admin/my_page.php`
2. Start with:
   ```php
   <?php
   $pageTitle = 'My Page Title';
   require_once __DIR__ . '/../../includes/admin_header.php';
   
   $pdo = getDB();
   // Your logic here
   ?>
   
   <!-- Your HTML here -->
   
   <?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>
   ```
3. Add to sidebar in `admin_header.php` (navigation tree)
4. Test navigation link

### Task 2: Add a New Student Page

1. Create file: `src/php/public/student/my_page.php`
2. Start with:
   ```php
   <?php
   $pageTitle = 'My Page Title';
   require_once __DIR__ . '/../../includes/session.php';
   require_once __DIR__ . '/../../includes/auth.php';
   require_once __DIR__ . '/../../includes/helpers.php';
   require_once __DIR__ . '/../../includes/icons.php';
   startSession();
   requireStudent();
   
   $pdo = getDB();
   $studentId = $_SESSION['student_id'];
   $currentPage = 'my_page'; // For nav highlighting
   // Your logic here
   ?>
   
   <!DOCTYPE html>
   <html>
   <head>
       <title><?= h($pageTitle) ?> | Test Platform</title>
       <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/student.css">
       <script src="https://unpkg.com/lucide@latest"></script>
   </head>
   <body>
   <?= iconSprite() ?>
   <?php include __DIR__ . '/../../includes/student_header.php'; ?>
   
   <!-- Your page-specific content here -->
   
   <?php include __DIR__ . '/../../includes/student_footer.php'; ?>
   <script>
   // Your JavaScript here
   lucide.createIcons();
   </script>
   </body>
   </html>
   ```
3. Add nav link in `student_header.php` (update nav array)
4. Test on mobile and desktop

### Task 3: Add a New API Endpoint

1. Create file: `src/php/api/my_endpoint.php`
2. Use this template:
   ```php
   <?php
   require_once __DIR__ . '/../config/db.php';
   require_once __DIR__ . '/../includes/session.php';
   require_once __DIR__ . '/../includes/auth.php';
   
   header('Content-Type: application/json');
   
   try {
       $method = $_SERVER['REQUEST_METHOD'];
       
       if ($method === 'POST') {
           $data = json_decode(file_get_contents('php://input'), true);
           // Process request
           echo json_encode(['success' => true, 'data' => $result]);
       } else {
           http_response_code(405);
           echo json_encode(['error' => 'Method not allowed']);
       }
   } catch (Exception $e) {
       http_response_code(500);
       echo json_encode(['error' => $e->getMessage()]);
   }
   ```
3. Test with `curl` or Postman
4. Verify CSRF token if needed

### Task 4: Modify Student Navigation

1. Edit `src/php/includes/student_header.php` (lines 25-42)
2. Add/remove nav items in the nav array:
   ```php
   <a href="dashboard.php" class="sidebar-nav-item <?= $currentNav === 'dashboard' ? 'active' : '' ?>">
       <?= icon('dashboard', 20) ?>
       <span>Dashboard</span>
   </a>
   ```
3. Changes automatically apply to all 4 student pages

### Task 5: Modify Admin Navigation

1. Edit `src/php/includes/admin_header.php` (lines 53-115)
2. Update the `$navSections` array:
   ```php
   'section_key' => [
       'label' => 'Section Title',
       'items' => [
           ['url' => 'page.php', 'label' => 'Item Label', 'icon' => 'icon-name', 'color' => 'classname'],
       ]
   ]
   ```
3. Changes automatically apply to all admin pages

### Task 6: Update CSS Theme

1. Edit `assets/css/admin.css` or `assets/css/student.css`
2. Use CSS variables already defined:
   ```css
   :root {
       --color-primary: #4F8CFF;
       --color-success: #22C55E;
       --color-error: #EF4444;
       --space-1: 8px;
       --space-2: 16px;
       --fs-base: 14px;
   }
   ```
3. Test light/dark mode toggle
4. Verify responsive breakpoints (1024px, 768px)

### Task 7: Add Icon to Icon System

1. Edit `src/php/includes/icons.php`
2. Add to `getIconDefinitions()` array:
   ```php
   'my-icon-name' => [
       'lucide'   => 'icon-name-in-lucide',
       'material' => 'icon_name_in_material',
       'label'    => 'Descriptive label for screen readers'
   ]
   ```
3. Use in templates: `<?= icon('my-icon-name', 20) ?>`

### Task 8: Run E2E Tests

```bash
# Run all tests
npx playwright test

# Run specific test file
npx playwright test tests/analytics-grid.spec.js

# Run in headed mode (see browser)
npx playwright test --headed

# Debug mode
npx playwright test --debug
```

---

## Debugging

### Enable Debug Mode

Add to `src/php/config/db.php`:

```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../errors.log');
```

### Common Issues

#### CSS Not Loading (404 Errors)

**Symptoms:** Page renders but no styling  
**Cause:** `ASSETS_URL` not configured correctly  
**Fix:** Check `src/php/config/db.php`:
```php
if (php_sapi_name() === 'cli-server') {
    define('ASSETS_URL', '/assets');  // Dev server
} else {
    define('ASSETS_URL', '/test-platform/assets');  // XAMPP
}
```

#### Icons Not Rendering

**Symptoms:** Icon boxes empty  
**Cause:** Lucide JS not loaded or iconSprite() missing  
**Fix:**
1. Verify `<?= iconSprite() ?>` called in `<body>`
2. Verify `<script src="https://unpkg.com/lucide@latest"></script>` in `<head>`
3. Verify `lucide.createIcons()` called at end of page

#### Database Connection Failed

**Symptoms:** White screen or "Connection refused"  
**Cause:** Database down or credentials wrong  
**Fix:**
```bash
# Check MySQL status
# XAMPP: Start MySQL in XAMPP Control Panel

# Or check connection
php -r "
    \$pdo = new PDO('mysql:host=127.0.0.1', 'root', '');
    echo 'Connected!';
"
```

#### 404 on Logout Link

**Symptoms:** "404 Not Found" when clicking "Sign Out"  
**Cause:** `BASE_URL` not used in href  
**Fix:** Use `<?= BASE_URL ?>/logout.php` not hardcoded path

### Browser Developer Tools

- **F12:** Open DevTools
- **Network tab:** Check for 404s on CSS/JS/fonts
- **Console tab:** Check for JavaScript errors
- **Elements tab:** Inspect HTML structure
- **Application → Local Storage:** Check theme preference

### PHP CLI Debugging

```bash
# Check PHP version
php -v

# Check loaded extensions
php -m | grep -i pdo

# Run PHP server with verbose output
php -S localhost:8000 router.php -d display_errors=1

# Test database connection
php src/php/config/db.php
```

---

## Contributing Guidelines

### Before You Commit

1. ✅ Test your changes locally (dev server + XAMPP)
2. ✅ Run `npx playwright test` to verify E2E tests pass
3. ✅ Check for PHP syntax errors: `php -l src/php/public/admin/my_page.php`
4. ✅ Verify no console errors (F12 → Console tab)
5. ✅ Test on mobile (DevTools → Toggle Device Toolbar)

### Code Style

**PHP:**
- Use `<?php ?>` tags, never `<?` shorthand
- Use prepared statements for all SQL (prevent injection)
- Follow PSR-12 style guide (4-space indents)
- Use meaningful variable names
- Comment complex logic

**JavaScript:**
- Use vanilla JS (no jQuery)
- Use `const`/`let`, never `var`
- Use camelCase for functions/variables
- Use `//` for single-line comments

**HTML:**
- Use semantic HTML5 elements
- Use `h()` function to escape output
- Include `role` attributes on custom components
- Use `data-*` attributes for JS hooks

**CSS:**
- Use CSS variables (--color-*, --space-*, --fs-*)
- Use flexbox/grid (no floats)
- Mobile-first approach (mobile styles, then media queries)
- Use 8px spacing grid

### Commit Messages

Format:
```
[CATEGORY] Brief description (50 chars max)

Longer explanation if needed. Mention issue numbers with #123.
```

Examples:
```
[BUGFIX] Fix CSS assets 404 on dev server
[FEATURE] Add student sidebar refactoring with shared includes
[REFACTOR] Extract common header HTML to student_header.php
[DOCS] Expand ADMIN_DASHBOARD.md specification
```

### Pull Request Template

```markdown
## Description
Brief summary of changes

## Type of Change
- [ ] Bug fix (non-breaking)
- [ ] New feature (non-breaking)
- [ ] Breaking change
- [ ] Documentation update

## Testing Done
- [ ] Tested on dev server (PHP CLI)
- [ ] Tested on XAMPP
- [ ] Tested on mobile (DevTools)
- [ ] Playwright E2E tests pass

## Checklist
- [ ] Code follows style guide
- [ ] No console errors
- [ ] No new 404 errors
- [ ] All links work
- [ ] Icons render
- [ ] CSS loads
```

---

## Need Help?

- **Documentation:** See [README.md](README.md), [MANUAL.md](MANUAL.md), [SRS/](SRS/)
- **Bug Reports:** Create issue with "BUGFIX:" prefix
- **Feature Requests:** Create issue with "FEATURE:" prefix
- **Questions:** Check existing issues or ask in discussions

---

**Last Updated:** August 21, 2026  
**Status:** ✅ Production Ready
