# Student Test & Analysis Platform — Logic Document

> **Version:** 1.0  
> **Stack:** PHP 8.3 + MySQL 8.0 + Python Flask (optional analysis API)  
> **Design:** Fluent 2 (Microsoft-inspired)  
> **Last Updated:** July 2026

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [System Architecture](#2-system-architecture)
3. [Folder Structure](#3-folder-structure)
4. [Database Schema (14 Tables)](#4-database-schema)
5. [Router & URL Structure](#5-router--url-structure)
6. [Authentication & User Roles](#6-authentication--user-roles)
7. [Student Registration & OTP Verification Flow](#7-student-registration--otp-verification-flow)
8. [Guest Access & QR Code Flow](#8-guest-access--qr-code-flow)
9. [Test Lifecycle](#9-test-lifecycle)
10. [Taking a Test (Student View)](#10-taking-a-test-student-view)
11. [Grading & Evaluation](#11-grading--evaluation)
12. [PCI Scoring System](#12-pci-scoring-system)
13. [Tab Switch Detection & Timer](#13-tab-switch-detection--timer)
14. [CSV Question Import](#14-csv-question-import)
15. [Python Analysis API](#15-python-analysis-api)
16. [UI Design System (Fluent 2)](#16-ui-design-system)
17. [Email / OTP System](#17-email--otp-system)
18. [Setup Guide](#18-setup-guide)
19. [Usage Manual](#19-usage-manual)
20. [Configuration Reference](#20-configuration-reference)

---

## 1. Project Overview

A college/internal assessment platform where:

- **Admins** create colleges → courses → batches → tests with MCQ/coding/explanation questions, generate guest links + QR codes, grade submissions, view PCI performance charts.
- **Students** register with email, verify via OTP, take timed tests with auto-save, tab-switch monitoring, and view results.
- **Guests** access tests via token links (shareable URL or scannable QR code) without registration.
- **PCI (Performance Competency Index)** — a weighted score: MCQ 40% + Coding 30% + Explanation 30%.

**Key constraints:**
- Students are organised under College → Course → Batch hierarchy.
- Tests belong to a batch; only students in that batch see and can take the test.
- Unverified students go to a separate `unverified_students` table until OTP verification.
- All failed logins are logged in `failed_login_log` for audit.
- Tab switches are recorded individually with timestamps.

---

## 2. System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         Browser                                  │
└──────────────────────────┬──────────────────────────────────────┘
                           │ HTTP
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│                    PHP Dev Server / Apache                        │
│                   php -S localhost:8000 router.php                │
│                                                                   │
│  ┌────────────────┐    ┌────────────────┐    ┌────────────────┐  │
│  │    router.php   │───▶│  src/php/public/│───▶│  src/php/api/  │  │
│  │  (URL rewriting)│    │  (Pages/Views) │    │  (AJAX APIs)   │  │
│  └────────────────┘    └────────────────┘    └────────────────┘  │
│                              │                                        │
│                              ▼                                        │
│                    ┌──────────────────┐                               │
│                    │  src/php/includes/│                               │
│                    │  (Auth, Helpers,  │                               │
│                    │   Mailer, Layout) │                               │
│                    └──────────────────┘                               │
│                              │                                        │
│                              ▼                                        │
│                    ┌──────────────────┐                               │
│                    │  src/php/config/ │                               │
│                    │  (DB, Mail)      │                               │
│                    └──────────────────┘                               │
└──────────────────────────┬──────────────────────────────────────────┘
                           │ PDO
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│                     MySQL 8.0 Database                           │
│                     test_platform                                 │
│                     14 tables                                    │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│           Python Flask API (optional, port 5000)                 │
│           /api/pci/calculate, /api/pci/batch/<id>,               │
│           /api/charts/test/<id>                                   │
└─────────────────────────────────────────────────────────────────┘
```

---

## 3. Folder Structure

```
project/
├── router.php                          # Dev server URL rewriter
├── sample_questions.csv                # 10 MCQ sample for CSV import
│
├── assets/
│   ├── css/
│   │   ├── student.css                 # Fluent 2 — student (minimalist)
│   │   └── admin.css                   # Fluent 2 — admin (data-dense)
│   ├── js/                             # (future)
│   └── images/                         # (future)
│
├── sql/
│   ├── schema.sql                      # Full database schema (12 base tables)
│   ├── migration_otp.sql               # OTP-related migrations
│   └── migration_unverified.sql        # unverified_students + failed_login_log
│
├── src/
│   └── php/
│       ├── config/
│       │   ├── db.php                  # DB connection, BASE_URL, PYTHON_API_URL
│       │   └── mail.php                # SMTP/Email config
│       │
│       ├── includes/
│       │   ├── session.php              # Session mgmt, CSRF tokens
│       │   ├── auth.php                 # Admin/Student/Guest auth, OTP, login logging
│       │   ├── helpers.php              # redirect(), h(), flash(), etc.
│       │   ├── mailer.php               # SMTP sender + PHP mail() fallback
│       │   ├── admin_header.php         # Admin layout (sidebar + topbar)
│       │   └── admin_footer.php         # Admin layout footer + </html>
│       │
│       ├── api/                         # AJAX endpoints
│       │   ├── get_courses.php          # College → Courses (JSON)
│       │   ├── get_batches.php          # Course → Batches (JSON)
│       │   ├── get_course_college.php   # Course → College lookup
│       │   ├── submit_answer.php        # Save/submit student answers
│       │   └── tab_switch.php           # Log tab switch events
│       │
│       └── public/                      # Public-facing pages
│           ├── login.php                # Admin & Student login
│           ├── signup.php               # Student registration
│           ├── verify-otp.php           # OTP verification page
│           ├── guest.php                # Guest token entry / QR redirect
│           ├── logout.php               # Session clear
│           ├── index.php                # Landing / redirect
│           │
│           ├── student/
│           │   ├── dashboard.php        # Student test list + results
│           │   └── test.php             # Test-taking interface (timer, questions)
│           │
│           └── admin/
│               ├── dashboard.php         # Admin home
│               ├── colleges.php          # CRUD — colleges
│               ├── courses.php           # CRUD — courses (per college)
│               ├── batches.php           # CRUD — batches (per course)
│               ├── students.php          # List students, generate guest links/QR
│               ├── pending_verifications.php  # Unverified registrations
│               ├── failed_logins.php     # Login attempt audit log
│               ├── test_builder.php      # Create/edit tests, questions, CSV import
│               ├── grading.php           # Grade submissions per test/student
│               ├── tab_switcher.php      # Tab switch logs per test
│               └── reports.php           # PCI charts, student drill-down
│
├── src/python/
│   ├── app.py                           # Flask API entry point
│   └── analysis/
│       ├── __init__.py
│       ├── pci.py                       # PCI calculation logic
│       └── charts.py                    # Chart data builders
│
└── docs/
    └── logic.md                         # ← THIS FILE
```

---

## 4. Database Schema

### 4.1 `admins` — Platform administrators

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK | |
| email | VARCHAR(255) UNIQUE | Login credential |
| password_hash | VARCHAR(255) | bcrypt |
| created_at | DATETIME | |

Seed: `admin@testplatform.com` / `admin123`

### 4.2 `colleges` — Educational institutions

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK | |
| name | VARCHAR(255) | |
| address | TEXT | |
| created_at | DATETIME | |

### 4.3 `courses` — Programs offered (unique per college)

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK | |
| college_id | INT FK → colleges | CASCADE delete |
| name | VARCHAR(255) | UNIQUE per college |
| created_at | DATETIME | |

### 4.4 `batches` — Cohorts within a course

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK | |
| course_id | INT FK → courses | CASCADE delete |
| name | VARCHAR(255) | e.g. "2024-2028" |
| created_at | DATETIME | |

### 4.5 `students` — Verified students

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK | |
| batch_id | INT FK → batches | RESTRICT delete |
| name | VARCHAR(255) | |
| phone | VARCHAR(20) | |
| email | VARCHAR(255) UNIQUE | |
| gender | ENUM('male','female','other') | |
| college_name | VARCHAR(255) | Denormalised from college |
| branch | VARCHAR(255) | |
| roll_number | VARCHAR(100) | |
| year_of_joining | INT | |
| course_name | VARCHAR(255) | Denormalised from course |
| password_hash | VARCHAR(255) | bcrypt |
| created_at | DATETIME | |

> **Note:** `email_verified`, `otp_hash`, `otp_expires_at` were removed from this table. Unverified students go to `unverified_students`.

### 4.6 `unverified_students` — Pre-OTP-verification registrations

Mirrors `students` columns **plus** `otp_hash` and `otp_expires_at`. On OTP verification, the row is **moved** (INSERT INTO students, DELETE FROM unverified_students).

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK | |
| batch_id | INT FK → batches | |
| name | VARCHAR(255) | |
| phone | VARCHAR(20) | |
| email | VARCHAR(255) UNIQUE | |
| gender | ENUM('male','female','other') | |
| college_name | VARCHAR(255) | |
| branch | VARCHAR(255) | |
| roll_number | VARCHAR(100) | |
| year_of_joining | INT | |
| course_name | VARCHAR(255) | |
| password_hash | VARCHAR(255) | |
| otp_hash | VARCHAR(64) | bcrypt of 6-digit OTP |
| otp_expires_at | DATETIME | 10 min from generation |
| created_at | DATETIME | |

### 4.7 `failed_login_log` — Audit log for failed auth attempts

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK | |
| email | VARCHAR(255) | Attempted email |
| student_name | VARCHAR(255) | Nullable |
| ip_address | VARCHAR(45) | Client IP |
| attempt_type | VARCHAR(50) | `signup_unverified`, `wrong_password`, `invalid_email`, `expired_otp`, `wrong_otp`, `duplicate_email` |
| reason | VARCHAR(255) | Human-readable |
| attempted_at | DATETIME | |

### 4.8 `guest_entries` — Guest links & QR codes

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK | |
| batch_id | INT FK → batches | |
| test_id | INT FK → tests | Nullable (link to specific test) |
| token | VARCHAR(64) UNIQUE | Secure random hex |
| type | ENUM('guest','qr') | |
| temp_data | JSON | Future use |
| student_id | INT FK → students | Nullable (if linked later) |
| status | ENUM('pending','linked','expired') | |
| expires_at | DATETIME | 30 days default, or test end_time |
| created_at | DATETIME | |

### 4.9 `tests` — Test definitions

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK | |
| batch_id | INT FK → batches | |
| title | VARCHAR(255) | |
| description | TEXT | |
| duration_minutes | INT | Default 30 |
| start_time | DATETIME | Nullable (scheduled) |
| end_time | DATETIME | Nullable |
| status | ENUM('upcoming','active','paused','completed') | |
| max_tab_switches | INT | NULL = unlimited |
| shuffle_questions | TINYINT(1) | |
| created_by | INT FK → admins | |
| created_at | DATETIME | |

### 4.10 `questions` — Test questions

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK | |
| test_id | INT FK → tests | CASCADE delete |
| type | ENUM('mcq','coding','explanation') | |
| question_text | TEXT | |
| options_json | JSON | MCQ only: `[{"key":"A","text":"..."},...]` |
| correct_answer | TEXT | MCQ: `"A"`, Coding: rubric ref, Explanation: rubric ref |
| marks | INT | |
| sort_order | INT | |
| created_at | DATETIME | |

### 4.11 `submissions` — Student test attempts

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK | |
| student_id | INT FK → students | |
| test_id | INT FK → tests | |
| status | ENUM('in_progress','submitted','evaluated') | |
| started_at | DATETIME | |
| submitted_at | DATETIME | Nullable |
| timer_extended_minutes | INT | Admin can extend per-student |
| total_marks_obtained | DECIMAL(10,2) | Set after evaluation |
| total_marks | DECIMAL(10,2) | Set after evaluation |

UNIQUE KEY on (student_id, test_id).

### 4.12 `student_answers` — Individual answer records

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK | |
| submission_id | INT FK → submissions | |
| question_id | INT FK → questions | |
| answer_json | JSON | MCQ: `{"selected":"A"}`, Coding: `{"code":"..."}`, Explanation: `{"text":"..."}` |
| marks_obtained | DECIMAL(10,2) | Set during grading |
| evaluated_at | DATETIME | |
| submitted_at | DATETIME | |

UNIQUE KEY on (submission_id, question_id).

### 4.13 `tab_switch_logs` — Tab visibility change log

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK | |
| submission_id | INT FK → submissions | |
| switch_count | INT | |
| type | ENUM('switch','timer_extend') | |
| metadata | JSON | Timer extend: `{"extended_by":5}` |
| timestamp | DATETIME(3) | Millisecond precision |

### 4.14 `pci_records` — Performance Competency Index scores

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK | |
| student_id | INT FK → students | |
| test_id | INT FK → tests | |
| pci_score | DECIMAL(5,2) | 0.00 – 100.00 |
| mcq_score | DECIMAL(5,2) | MCQ percentage |
| coding_score | DECIMAL(5,2) | Coding percentage |
| explanation_score | DECIMAL(5,2) | Explanation percentage |
| mcq_weight | DECIMAL(5,2) | Default 40.00 |
| coding_weight | DECIMAL(5,2) | Default 30.00 |
| explanation_weight | DECIMAL(5,2) | Default 30.00 |
| generated_at | DATETIME | |

UNIQUE KEY on (student_id, test_id).

---

## 5. Router & URL Structure

### 5.1 Dev Server (`php -S localhost:8000 router.php`)

The router (`router.php`) rewrites incoming URLs:

| Request URL | Actual File |
|-------------|-------------|
| `/` | `src/php/public/index.php` |
| `/login.php` | `src/php/public/login.php` |
| `/student/dashboard.php` | `src/php/public/student/dashboard.php` |
| `/admin/reports.php` | `src/php/public/admin/reports.php` |
| `/assets/css/student.css` | `assets/css/student.css` (direct) |
| `/api/get_courses.php` | `src/php/api/get_courses.php` |

**Prefix stripping:** The router strips `/test-platform`, `/src/php/public/`, and `/src/php/` prefixes for XAMPP portability. URLs in code use `<?= BASE_URL ?>`.

### 5.2 BASE_URL Detection

In `src/php/config/db.php`:

- **CLI server** (`php_sapi_name() === 'cli-server'`): `BASE_URL = ''` (empty)
- **Apache/XAMPP**: `BASE_URL = '/test-platform/src/php/public'`

All redirects and links must use `<?= BASE_URL ?>` prefix.

### 5.3 Asset URLs

Asset paths use `/test-platform/assets/...` hardcoded prefix. This works on both:

- **CLI server**: Router strips `/test-platform` and serves from project root `/assets/`
- **XAMPP**: Apache serves from `C:\xampp\htdocs\test-platform\assets\`

---

## 6. Authentication & User Roles

### 6.1 Roles

| Role | Session Key | Auth Function |
|------|-------------|---------------|
| Admin | `$_SESSION['role'] = 'admin'` + `admin_id` | `requireAdmin()` |
| Student | `$_SESSION['role'] = 'student'` + `student_id` | `requireStudent()` |
| Guest | `$_SESSION['role'] = 'guest'` + `guest_token` | Session check only |

### 6.2 Admin Login

- Route: `login.php` → POST with email + password
- Checks `admins` table with `password_verify()` (bcrypt)
- On failure: `logFailedLogin()` with type `wrong_password`
- On success: sets session vars, session_regenerate_id

### 6.3 Student Login

- Route: `login.php` → POST with email + password
- Checks `students` table first (verified users)
- If not found, checks `unverified_students` table
  - If found with matching password → returns error: "Please verify your email first"
  - If password doesn't match → `logFailedLogin()` with type `wrong_password`
- On success: sets session vars

### 6.4 Guest Token Login

- Route: `guest.php?token=XXX` or POST to `guest.php`
- Validates token in `guest_entries` table (status = 'pending', not expired)
- Sets session: `guest_token`, `guest_entry_id`, `batch_id`, `test_id`, `role = 'guest'`
- Redirects to `student/test.php?test_id=X`

### 6.5 Session & CSRF

- Session management: `session.php` — `startSession()`, CSRF token generation/validation
- Every form has hidden `csrf_token` field via `<?= csrfField() ?>`
- Every POST handler calls `requireCsrf()` before processing
- Session ID regenerated every 30 minutes

---

## 7. Student Registration & OTP Verification Flow

```
Student fills signup form
         │
         ▼
studentRegister() in auth.php
   ├── Checks duplicate email (both students + unverified_students)
   ├── Validates batch belongs to selected course & college
   ├── Gets college_name and course_name
   ├── Inserts into unverified_students (NOT students!)
   └── Calls generateStudentOtp()
         │
         ▼
generateStudentOtp()
   ├── Generates 6-digit OTP (random_int, left-padded)
   ├── Hashes OTP with bcrypt → otp_hash
   ├── Sets otp_expires_at = now + 10 minutes
   ├── UPDATE unverified_students SET otp_hash, otp_expires_at
   └── Calls sendOtpEmail() → SMTP or mail()
         │
         ▼
Student redirected to verify-otp.php?id=STUDENT_ID
         │
         ▼
User enters OTP → verifyStudentOtp()
   ├── Fetches from unverified_students
   ├── Checks otp_hash existence
   ├── Checks expiry (otp_expires_at > now)
   ├── Verifies OTP with password_verify()
   ├── BEGIN TRANSACTION
   │   ├── INSERT INTO students (all columns except otp fields)
   │   └── DELETE FROM unverified_students WHERE id = ?
   └── COMMIT
         │
         ▼
Student logged in → Redirect to dashboard
```

**On login attempt for unverified student:**
- `studentLogin()` detects email in `unverified_students`
- Returns `not_verified = true` with `student_id`
- `login.php` redirects to `verify-otp.php` with a "resend" option

**Resend OTP:**
- `resendStudentOtp()` → finds student in `unverified_students` → calls `generateStudentOtp()` again

---

## 8. Guest Access & QR Code Flow

### 8.1 Generation

Admin clicks "Generate Guest Link" or "QR Code" in **students.php** modal:

1. Select college → course → batch (hierarchical dropdowns using `/api/get_courses.php` and `/api/get_batches.php`)
2. Optionally link to a specific test
3. Form POSTs with action `generate_guest` or `generate_qr`
4. Server creates a `guest_entries` row with:
   - Secure random `token` (64-char hex via `random_bytes(32)`)
   - `expires_at` = 30 days from now (or test end_time if linked)
5. URL is: `BASE_URL . '/guest.php?token=' . $token`

### 8.2 QR Code Display

When `generate_qr` is selected, the resulting page shows:
- The guest URL (clickable + copy button)
- A QR code image from `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=URL`

### 8.3 Access Flow

1. User clicks guest link (or scans QR code)
2. `guest.php` validates token against `guest_entries` table
3. On success, session is set with guest role, batch_id, test_id
4. Redirects to `student/test.php?test_id=X` or `student/dashboard.php`
5. Guest can take the test but answers are NOT saved to DB (guest mode is read-only for now)

---

## 9. Test Lifecycle

```
[Admin] Creates Test → status = 'upcoming'
      │
      ▼
[Admin] Adds Questions (manually or CSV import)
      │
      ▼
[Admin] Activates Test → status = 'active'
      │
      ├── [Student] Sees "Start Test" button on dashboard
      │         │
      │         ▼
      │     Takes test (see section 10)
      │
      ├── [Admin] Pauses Test → status = 'paused'
      │     └── New students blocked; in-progress can still resume
      │
      ├── [Admin] Resumes Test → status = 'active'
      │
      └── [Admin] Stops Test → status = 'completed'
            └── All in-progress → auto-submitted
            └── No further access
      │
      ▼
[Admin] Grades submissions → status = 'evaluated'
      │
      ▼
[Admin] Generates PCI scores → pci_records populated
      │
      ▼
[Admin/Student] Views reports

```

### 9.1 Status Transitions

| From | To | Action | Effect |
|------|----|--------|--------|
| `upcoming` | `active` | Activate Now (admin) | Students can start |
| `active` | `paused` | Pause (admin) | Blocks new starts; resumes allowed |
| `paused` | `active` | Resume (admin) | Students can resume |
| `active` | `completed` | Stop (admin) | Auto-submits; blocks all access |
| `paused` | `completed` | Stop (admin) | Same |
| `upcoming` | — | — | Shows "Upcoming" badge |
| `active` | — | — | Shows "Start Test" if within time window |

### 9.2 Access Control Logic (in `student/test.php`)

```php
if status == 'completed'  → show "Test Stopped" page, exit
if status == 'paused' && no in-progress submission → show "Test Paused" page, exit
if status != 'active' && not guest → show "Not Available" page, exit
if outside start/end time window → show "Not Available" page, exit
```

### 9.3 Timer Extension

Admin can extend timer per-student in `test_builder.php`:
- Select test → look at "Active Submissions" section
- Choose +5, +10, +15, or +30 minutes
- Logged as `timer_extend` entry in `tab_switch_logs`
- Student's remaining time recalculated: `(duration_minutes × 60) + (timer_extended_minutes × 60) - elapsed`

---

## 10. Taking a Test (Student View)

### 10.1 Entry Points

- **Logged-in student:** Dashboard → "Start Test" or "Resume"
- **Guest:** Guest link/QR → redirected to test

### 10.2 Test Interface (`student/test.php`)

```
┌─────────────────────────────────────────────────────┐
│ Timer Bar: [Test Title]        [02:45:30] remaining  │
│                                   25 questions       │
├─────────────────────────────────────────────────────┤
│ Question Navigator (dot grid)                        │
│ ○ ○ ● ○ ○ ○ ● ○ ○ ○ ○ ○ ○ ○ ○ ○ ○ ○ ○ ○ ○ ○ ○ ○ ○ │
│   (● = answered, ○ = unanswered, dark ring = current)│
├─────────────────────────────────────────────────────┤
│ Question 3 of 25  (2 marks)                          │
│ What is the capital of France?                       │
│                                                      │
│ ◉ Paris                                              │
│ ○ London                                             │
│ ○ Berlin                                             │
│ ○ Madrid                                             │
│                                                      │
│ ──────────────────────────────────────────────────── │
│ Question 4 of 25  (Coding)  (5 marks)                │
│ Write a function to reverse a string                 │
│ ┌─────────────────────────────────────────────┐     │
│ │ function reverse(str) {                     │     │
│ │     return str.split('').reverse().join('');│     │
│ │ }                                           │     │
│ └─────────────────────────────────────────────┘     │
│                                                      │
│ ──────────────────────────────────────────────────── │
│ Question 5 of 25  (Explanation)  (3 marks)          │
│ Explain the concept of recursion                    │
│ ┌─────────────────────────────────────────────┐     │
│ │                                             │     │
│ │                                             │     │
│ └─────────────────────────────────────────────┘     │
│                                                      │
│              [ Submit Test ]                         │
└─────────────────────────────────────────────────────┘
```

### 10.3 Auto-Save

- Answers auto-save 2 seconds after the user stops changing a field
- Uses `POST /api/submit_answer.php` with `auto_save=1`
- MCQ radio buttons, coding textarea, explanation textarea all trigger auto-save

### 10.4 Submit

- "Submit Test" button with confirmation dialog
- Sends all answers to `/api/submit_answer.php`
- Sets submission status to `submitted`
- On timer expiry (reaches 00:00:00), form auto-submits

### 10.5 Result Display

After submission:
- If `status = 'submitted'` → "Test Submitted" message, "Results will be available once evaluated"
- If `status = 'evaluated'` → Score percentage + marks obtained / total marks

---

## 11. Grading & Evaluation

### 11.1 Grading Interface (`admin/grading.php`)

Admin selects a test → student → sees all questions with their answers:

- **MCQ:** System shows the selected option and the correct answer. Admin confirms marks.
- **Coding:** Admin reads the student's code and enters marks manually.
- **Explanation:** Admin reads the explanation and enters marks manually.

### 11.2 Save Grades

POST handler `save_grades`:
1. Iterates over answers, updates `marks_obtained` and `evaluated_at`
2. Calculates `total_marks_obtained` and `total_marks` for the submission
3. Updates submission: `status = 'evaluated'`, totals saved
4. Student dashboard shows percentage: `round(($total_marks_obtained / $total_marks) * 100)%`

---

## 12. PCI Scoring System

### 12.1 Formula

```
PCI Score = (MCQ% × 40%) + (Coding% × 30%) + (Explanation% × 30%)
```

Each category percentage = `(obtained / total) × 100`

### 12.2 Generation

Admin clicks "Generate PCI" in **reports.php** for a specific test:

1. Fetches all `evaluated` submissions
2. For each submission, gets per-category scores (MCQ, Coding, Explanation)
3. Calculates PCI using the formula
4. Upserts into `pci_records` table

### 12.3 Performance Bands (Python API)

| PCI Range | Band |
|-----------|------|
| 90–100 | Outstanding |
| 75–89 | Excellent |
| 60–74 | Good |
| 40–59 | Satisfactory |
| < 40 | Needs Improvement |

### 12.4 Reports (`admin/reports.php`)

**All Students view** (when no student_id filter):
- Bar chart: PCI score per student
- Doughnut chart: Category averages
- Stacked bar: MCQ/Coding/Explanation breakdown per student
- Histogram: PCI score distribution
- Table: Ranked list with drill-down to individual

**Individual Student view** (when `student_id=X`):
- Stats boxes: PCI Score, MCQ%, Coding%, Explanation%
- Radar chart: Score breakdown
- Back to All Students link

---

## 13. Tab Switch Detection & Timer

### 13.1 Detection

JavaScript listens to `visibilitychange` event:

```javascript
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        // Log to server via POST /api/tab_switch.php
        // Show warning banner for 3 seconds
    }
});
```

### 13.2 Logging

Each tab switch is logged as a row in `tab_switch_logs`:
- `submission_id` — which test attempt
- `switch_count` — how many switched detected (typically 1)
- `type = 'switch'`
- `timestamp` — with millisecond precision

### 13.3 Admin View

`admin/tab_switcher.php` shows tab switch logs per test.

### 13.4 Timer

Server-side timer tracking:
- `started_at` stored in `submissions`
- `duration_minutes` from test definition
- `timer_extended_minutes` per submission (admin can extend)
- Remaining = `(duration_minutes × 60 + timer_extended_minutes × 60) - (now - started_at)`

Client-side countdown:
- Server passes remaining seconds to JS
- JS updates display every second
- Timer color changes: warning (yellow) when < 5 min, danger (red) when < 1 min
- Auto-submits when reaches 00:00:00

---

## 14. CSV Question Import

### 14.1 Format

CSV file with 7 columns (header row optional but recommended):

```
question_text, option_a, option_b, option_c, option_d, correct_answer, marks
```

Example:
```csv
question_text,option_a,option_b,option_c,option_d,correct_answer,marks
What is the capital of France?,London,Berlin,Paris,Madrid,C,1
Which planet is known as the Red Planet?,Venus,Mars,Jupiter,Saturn,B,1
```

### 14.2 Import Logic (in `test_builder.php`)

1. Admin selects a test and uploads the CSV file
2. Server reads line by line with `fgetcsv()`
3. First line skipped if it matches `/question/i` (header detection)
4. Each row validates:
   - Question text must not be empty
   - At least 2 options required (A, B minimum)
   - Correct answer must be A, B, C, or D
5. Builds JSON options array: `[{"key":"A","text":"..."},...]`
6. Inserts into `questions` table with `type = 'mcq'`
7. Reports count of imported questions + any warnings

### 14.3 Sample File

`sample_questions.csv` at project root contains 10 MCQ questions ready for import.

---

## 15. Python Analysis API

### 15.1 Overview

Optional Flask microservice for advanced PCI analysis and chart data generation.

### 15.2 Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/health` | GET | Health check |
| `/api/pci/calculate` | POST | Calculate PCI for a submission |
| `/api/pci/batch/<test_id>` | GET | All PCI scores for a test |
| `/api/pci/student/<student_id>` | GET | PCI history for a student |
| `/api/charts/test/<test_id>` | GET | Chart-ready data for a test |

### 15.3 Running

```bash
cd src/python
pip install flask mysql-connector-python
python app.py
# Runs on http://127.0.0.1:5000
```

### 15.4 PHP Integration

PHP calls Python API via `callPythonApi()` in helpers.php when available. Falls back to PHP-side PCI calculation (in reports.php) if Python API is offline.

### 15.5 Configuration

`PYTHON_API_URL` in `config/db.php`: default `http://127.0.0.1:5000`

---

## 16. UI Design System

### 16.1 Fluent 2 — Design Tokens

Defined as CSS custom properties in `:root` of each stylesheet:

#### Color Palette

| Token | Value | Usage |
|-------|-------|-------|
| `--white` | `#FFFFFF` | Backgrounds |
| `--gray-5` | `#F5F5F5` | Page background |
| `--gray-10` | `#EDEBE9` | Card backgrounds |
| `--gray-20` | `#E1DFDD` | Borders |
| `--gray-30` | `#D2D0CE` | Divider |
| `--gray-40` | `#B3B0AD` | Disabled text |
| `--gray-50` | `#8A8886` | Secondary text |
| `--gray-60` | `#605E5C` | Body text |
| `--gray-90` | `#292827` | Heading text |
| `--accent` | `#0078D4` | Primary blue |
| `--green` | `#0B6A0B` | Success |
| `--red` | `#BC2F32` | Error/Danger |
| `--yellow` | `#8A6D00` | Warning |
| `--orange` | `#C85A00` | Coding questions |

#### Typography

| Token | Value |
|-------|-------|
| `--font` | `'Segoe UI Variable', 'Segoe UI', -apple-system, sans-serif` |
| `--mono` | `'Cascadia Code', 'Fira Code', 'Consolas', monospace` |

#### Spacing

4px grid: `--space-1: 4px`, `--space-2: 8px`, ..., `--space-12: 48px`

#### Radius

`--radius-sm: 2px`, `--radius-md: 4px`, `--radius-lg: 8px`

### 16.2 Student Theme (`student.css`)

- **Style:** Minimalist, clean, card-based
- **Layout:** Single column, centered container (max 1120px)
- **Header:** White sticky header with logo and sign-out link
- **Cards:** White rounded cards with subtle shadow
- **Buttons:** Accent blue primary, outlined secondary
- **Badges:** Rounded pills for status indicators
- **Auth pages:** Centered card (max 480px)
- **Test page:** Full-width with sticky timer bar

### 16.3 Admin Theme (`admin.css`)

- **Style:** Industrial, data-dense, dark sidebar
- **Layout:** Fixed sidebar (dark) + scrollable main content
- **Sidebar:** 240px, dark background, nav sections with icons
- **Topbar:** Page title + admin email
- **Data tables:** Full-width, striped, with action columns
- **Panels:** Bordered sections with headers
- **Stats row:** Horizontal stat boxes with large numbers
- **Modals:** Overlay + centered modal for forms

---

## 17. Email / OTP System

### 17.1 Configuration (`config/mail.php`)

| Setting | Description | Current Value |
|---------|-------------|---------------|
| `MAIL_DRIVER` | `'smtp'` or `'mail'` | `'smtp'` |
| `SMTP_HOST` | SMTP server | `smtp.gmail.com` |
| `SMTP_PORT` | Port | `587` (TLS) |
| `SMTP_USERNAME` | Gmail address | `test.dev.hari0003@gmail.com` |
| `SMTP_PASSWORD` | Gmail App Password | `ukzb epeu crqm ezzq` |
| `SMTP_FROM` | From address | Same as username |
| `MAIL_DEV_MODE` | Log instead of send | `false` |
| `MAIL_DEV_LOG` | Log file path | `storage/logs/otp.log` |

### 17.2 SMTP Sender (`includes/mailer.php`)

Direct socket implementation — no PHPMailer dependency:
1. `fsockopen()` to SMTP server on port 587
2. EHLO → STARTTLS → TLS handshake → EHLO (post-TLS) → AUTH LOGIN
3. **AUTH LOGIN:** Sends username + password as base64
4. MAIL FROM → RCPT TO → DATA → Send email content
5. Email includes `Message-ID`, `Date`, `MIME-Version`, `Content-Type` headers

### 17.3 Dev Mode

When `MAIL_DEV_MODE = true`, OTP emails are **logged to file** instead of being sent:
```
storage/logs/otp.log
```
The OTP is also returned in the `studentRegister()` response as `otp_dev` for testing.

### 17.4 Gmail App Password

Required for SMTP authentication with Gmail:
1. Enable 2-Factor Authentication
2. Generate App Password at https://myaccount.google.com/apppasswords
3. Use the 16-character App Password in `SMTP_PASSWORD`

---

## 18. Setup Guide

### 18.1 Prerequisites

- PHP 8.3+
- MySQL 8.0+
- Python 3.9+ (optional, for analysis API)
- Composer (optional, no dependencies required)

### 18.2 Database Setup

```sql
-- Option A: Run schema file
mysql -u root < sql/schema.sql

-- Option B: Create manually
CREATE DATABASE test_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE test_platform;
-- Then run all CREATE TABLE statements from schema.sql
```

### 18.3 Configuration

1. **`src/php/config/db.php`:**
   - Set `DB_ENV` to `'local'` or `'production'`
   - For production, update production DB credentials
   - `BASE_URL` auto-detects based on `php_sapi_name()`

2. **`src/php/config/mail.php`:**
   - Fill in SMTP credentials (or use `MAIL_DEV_MODE = true` for testing)
   - For Gmail, generate an App Password

### 18.4 Running the Server

```bash
# Development server
php -S localhost:8000 router.php

# XAMPP
# Place project in C:\xampp\htdocs\test-platform\
# Access at http://localhost/test-platform/src/php/public/
```

### 18.5 Seed Data

Default admin is inserted in `schema.sql`:
- Email: `admin@testplatform.com`
- Password: `admin123`

Run `php _seed_test.php` to generate 50 sample students and test data.

### 18.6 Python API (Optional)

```bash
cd src/python
pip install flask mysql-connector-python
python app.py
# Runs on http://127.0.0.1:5000
```

---

## 19. Usage Manual

### 19.1 Admin Flow

#### First Time Setup

```
1. Login:   /login.php        → admin@testplatform.com / admin123
2. Create:  Colleges          → e.g. "University of Technology"
3. Create:  Courses           → e.g. "Computer Science" (under college)
4. Create:  Batches           → e.g. "2024-2028" (under course)
```

#### Managing Students

```
1. Students → view list, filter by college/course/batch, search
2. "Generate Guest Link" → select hierarchy → optional test → get URL + QR
3. "Pending Verifications" → view unverified registrations
4. "Failed Logins" → audit failed login attempts
```

#### Creating & Managing a Test

```
1. Test Builder → "Create Test"
   - Select batch, enter title, duration, schedule (optional), status
2. Click "Questions" on the test row
3. Add questions individually (MCQ/Coding/Explanation) OR
   Import CSV → upload CSV file → "Import CSV"
4. "Activate Now" to make test available to students
5. Monitor → "Pause" (block new), "Stop" (end test), "Delete" (remove)
```

#### Grading

```
1. Grading → select test → select student
2. Review each answer and enter marks
3. "Save Grades" → submission marked as evaluated
4. Student dashboard shows percentage score
```

#### Reports & Analytics

```
1. PCI Reports → select test
2. "Generate PCI" → calculates weighted scores
3. View charts: bar chart, doughnut, stacked bar, histogram
4. Click "Detail" on any student for individual radar chart
5. Or from Students page: "Visualize" → opens reports with student pre-selected
```

### 19.2 Student Flow

#### Registration

```
1. /signup.php → fill form (name, email, phone, college/course/batch, etc.)
2. Check email for OTP (or dev log file if MAIL_DEV_MODE = true)
3. Enter OTP on /verify-otp.php
4. Automatically logged in → redirect to dashboard
```

#### Taking a Test

```
1. Dashboard → tests listed with status badges
2. "Start Test" → opens test interface
3. Answer questions (MCQ radio buttons, coding/explanation textareas)
4. Questions auto-save (2 sec after change)
5. Use question navigator dots to jump between questions
6. Submit when done (or timer auto-submits)
7. View result: percentage score (if evaluated) or "Submitted" (if pending)
```

#### Test Statuses (Dashboard)

| Badge | Meaning |
|-------|---------|
| "Start Test" | Test is active and available |
| "Resume" | You have an in-progress attempt |
| "Resume (Paused)" | Test was paused but you can resume |
| "Paused by Admin" | Test paused, no access yet |
| "Starts in X min" | Scheduled test not yet started |
| "Upcoming" | Test not yet active |
| "View Result" | Evaluated — shows score % |
| "Submitted" | Submitted, awaiting evaluation |
| "Expired" | Test window has passed |

### 19.3 Guest Flow

```
1. Admin generates guest link or QR code → shares with guest
2. Guest clicks link (or scans QR on phone)
3. Redirected directly to the test (if linked to a test)
4. Guest can view and navigate the test
5. (Guest answers are not persisted to database)
```

---

## 20. Configuration Reference

### 20.1 `config/db.php`

```php
define('DB_ENV', 'local');              // 'local' | 'production'
date_default_timezone_set('Asia/Kolkata');
define('PYTHON_API_URL', 'http://127.0.0.1:5000');
// BASE_URL auto-detected based on PHP SAPI
```

### 20.2 `config/mail.php`

```php
define('MAIL_DRIVER', 'smtp');          // 'smtp' | 'mail'
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your@gmail.com');
define('SMTP_PASSWORD', '16-char-app-password');
define('SMTP_FROM', 'your@gmail.com');
define('SMTP_FROM_NAME', 'Test Platform');
define('MAIL_DEV_MODE', false);         // true = log OTP to file
define('MAIL_DEV_LOG', __DIR__ . '/../storage/logs/otp.log');
```

### 20.3 Environment Variables (for `DB_ENV = 'production'`)

| Variable | Default | Description |
|----------|---------|-------------|
| `DB_HOST` | MySQL host | Database server |
| `DB_PORT` | 3306 | Database port |
| `DB_NAME` | test_platform | Database name |
| `DB_USER` | MySQL user | |
| `DB_PASS` | MySQL password | |
| `PORT` | 5000 | Python API port |

---

## Appendix: Key Design Decisions

1. **Unverified students in separate table** — Keeps `students` table clean (only verified users). OTP columns not needed on verified records. Prevents login attempts on unverified accounts from succeeding.

2. **Direct SMTP over PHPMailer** — No external dependency. Implements SMTP protocol directly via PHP sockets. Works on any host with `fsockopen()`.

3. **Server-side timer tracking** — Remaining time calculated server-side from `started_at` + `duration_minutes`. Client JS is just a display. Prevents time manipulation.

4. **Test pause vs stop** — Pause is temporary (students with in-progress submissions can still resume). Stop is permanent (auto-submits all, no further access).

5. **PCI formula** — MCQ 40%, Coding 30%, Explanation 30%. Weights are stored in `pci_records` per record for auditability. Weight changes won't retroactively change historical scores.

6. **Router prefix stripping** — Designed to work identically on PHP built-in server and XAMPP/Apache without changing any code.

---

*End of Logic Document*
