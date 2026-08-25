# 📘 Exam Portal — Simple User Guide

> **For:** Teachers, Administrators, and Staff  
> **Purpose:** Explains every button, screen, and feature in plain English  
> **Last updated:** July 2026

---

## 🎯 What Is This?

This is an online exam platform. Think of it as a digital classroom where:

- **Admins** (you) create tests, manage students, and view results
- **Students** log in, take tests, and see their scores

Everything lives inside your web browser — no software to install.

---

## 🔑 Logging In

**URL:** Ask your technical team for the link.

You'll see a login screen with two fields:

| Field | What to enter |
|-------|---------------|
| **Email** | Your work email address |
| **Password** | The password given to you |

There's also a **dropdown** to choose your role:
- **Admin** → if you're a teacher or staff member
- **Student** → if you're taking tests

Click **"Sign In"** and you're in.

> 💡 **Forgot your password?** Ask your system administrator to reset it.

---

## 👤 How Student Registration & Login Works

Students have two ways to access the system:

### Option 1: Registered Students (Email + Password)

**Step 1 — Admin Creates the Account**
> You (the admin) can add students manually go to **Student Management → Students** and click **"Add Student"**. Fill in their name, email, roll number, and batch, then save. The student can now log in.

**Step 2 — Student Signs Up (Self-Registration)**
> Students can also register themselves by visiting the **Sign Up** page. They fill in:
> - **Full Name**
> - **Email address**
> - **Password** (choose one)
> - **Roll Number**
> - **Batch** (select from a dropdown)
>
> After submitting, their account is **pending verification** — you need to approve it.

**Step 3 — Admin Verifies the Student**
> 1. Go to **Student Management → Pending Verifications** in the sidebar
> 2. You'll see a list of new students waiting for approval
> 3. Click **"Verify"** next to their name
> 4. The student gets an email confirmation and can now log in

**Step 4 — Student Logs In**
> 1. Go to the login page
> 2. Select **"Student"** as the role
> 3. Enter their email and password
> 4. Click **"Sign In"**
> 5. They land on their **Student Dashboard** where they see assigned tests

### Option 2: Guest Access (No Account Required)

This is for **temporary or one-time access** — no email or password needed.

> 1. The admin generates a **Guest Link** or **QR Code** (see instructions below)
> 2. The student opens the link (or scans the QR code with their phone)
> 3. They're taken directly to the test — no login required
> 4. The guest access expires automatically after the test ends or 30 days

👉 *Jump to the **Guest Link & QR Code** section below for step-by-step instructions.*

---

# 🖥️ ADMIN DASHBOARD — What You See First

After logging in as an admin, this is your home screen. It gives you a birds-eye view of everything happening.

## Top Bar (Header)

```
[☰]  [Search... Ctrl+K]     [Create Assessment]  [🌙]  [🔔]  [👤 Admin Name]
```

| Button | What it does |
|--------|-------------|
| **☰ (hamburger menu)** | Collapses or expands the left sidebar to give you more screen space |
| **Search bar** | Type anything — student names, test titles, courses — and it quickly finds results. Press `Ctrl+K` on your keyboard to jump to it anytime |
| **Create Assessment** | Takes you directly to the test creation page |
| **🌙/☀️ (moon/sun)** | Switches between **Dark Mode** (easier on the eyes at night) and **Light Mode** (bright daytime view) |
| **🔔 (bell)** | Shows your notifications |
| **👤 Your name** | Your profile menu — change settings or sign out |

## Statistics Cards (The 4 Big Numbers)

These 4 boxes at the top show your system at a glance:

| Card | What it tells you |
|------|-------------------|
| **👥 Total Students** | How many students are registered in the system |
| **📝 Total Assessments** | How many tests have been created (includes drafts, active, and completed) |
| **📊 Active Assessments** | Tests that are currently running — students can take them right now |
| **⚠️ Pending Actions** | Things that need your attention (like verifying new student accounts) |

Each card has a small arrow → that you can click to see more details.

## Charts Section

### 📈 Average Performance (The Line Graph)

This is a chart that shows how students are performing over time. 

- The **line** goes up when scores improve and down when they drop
- The **green dot** at the end shows the current average score
- Hover your mouse over the chart to see exact numbers for each point in time

> **What to look for:** If the line is trending upward, students are improving. If it's dropping, you may want to review the teaching material.

### 🎯 Assessment Distribution (The Coloured Ring)

This circle shows the **status of all your tests**:

| Colour | What it means |
|--------|---------------|
| **Blue** 🟦 | Active tests — students can take these |
| **Amber** 🟧 | Upcoming tests — scheduled but not yet started |
| **Green** 🟩 | Completed tests — already finished |

The **big number in the centre** shows the dominant category. For example, if most of your tests are Active, it'll show "71% Active".

The **legend on the right** breaks down the exact count for each status.

### 🔄 Submission Overview (Another Coloured Ring)

This circle shows the **progress of student submissions**:

| Colour | What it means |
|--------|---------------|
| **Cyan** 🔷 | In Progress — students are currently taking the test |
| **Amber** 🟧 | Submitted — students have finished but not yet graded |
| **Green** 🟩 | Evaluated — grading is complete and results are available |

## Quick Actions (The 6 Shortcut Cards)

These are one-click shortcuts to your most common tasks:

| Icon | Action |
|------|--------|
| **➕ Create Assessment** | Start making a new test |
| **⏳ Pending Verifications** | Approve new student accounts that need confirmation |
| **👥 Manage Students** | View and edit student information |
| **📚 Manage Batches** | Organise students into groups/batches |
| **📖 Manage Courses** | Add or edit courses |
| **📊 View Reports** | See detailed performance reports |

## Recent Activity

### 📋 Recent Assessments

A table showing the most recently created or modified tests:
- **Name** — the test title
- **Status** — whether it's Active, Draft, Paused, or Completed
- **Batch** — which student group it's assigned to
- **Questions** — how many questions in the test
- **Actions** — buttons to edit, view, or manage the assessment

### 👤 Recent Students

A list of students who recently joined or interacted with the platform:
- **Name** and **Email**
- **Batch** they belong to
- **Status** — Active or Pending
- **Actions** — buttons to view their profile or edit details

### 🔴 Live Activity Feed

A real-time stream showing what's happening right now:
- *"John started Test 3"*
- *"Sarah submitted Chemistry Quiz"*
- *"Admin paused Midterm Exam"*

This auto-refreshes, so you can monitor activity without refreshing the page.

### ✅ System Status

4 indicators showing if everything is working properly:

| Indicator | What it checks |
|-----------|----------------|
| **Database** | Are student records accessible? |
| **Mail Server** | Can the system send emails (verifications, notifications)? |
| **Analysis API** | Is the grading/analysis engine running? |
| **Storage** | Can files and data be saved? |

Each shows a green check ✅ (healthy) or warning ⚠️ (needs attention).

---

# 🏛️ SETTING UP YOUR INSTITUTION — Step by Step

When you first start using the system, you need to set things up in this order:

```
Step 1: Add Colleges  →  Step 2: Add Courses  →  Step 3: Add Batches
                                                       ↓
                                              Step 4: Add Students
                                                       ↓
                                              Step 5: Create Tests
```

Think of it like a real school:
- **College** = The main institution (e.g., "City Engineering College")
- **Course** = A program of study (e.g., "B.Tech Computer Science")
- **Batch** = A specific group of students (e.g., "Batch 2025-A")
- **Students** = Individual learners
- **Tests** = Assessments created for a batch

---

## Step 1: Adding a College

1. In the left sidebar, go to **Institution Management → Colleges**
2. Click the **"+ Add College"** button
3. A pop-up form appears. Fill in:
   - **College Name** (required) — e.g., *"City Engineering College"*
   - **Address** (optional) — e.g., *"123 Main Street, New York"*
4. Click **"Save"**
5. The college appears in the list. You can **Edit** or **Delete** it anytime using the buttons on the right.

> 💡 **Tip:** Add all your colleges first before moving to courses.

**The Colleges Table shows:**
| Column | What it is |
|--------|-----------|
| **ID** | Auto-generated number |
| **Name** | The college name |
| **Address** | Physical address |
| **Courses** | How many courses belong to this college (clickable badge) |
| **Created** | Date added |
| **Actions** | Edit / Delete buttons |

---

## Step 2: Adding a Course

A course belongs to a college. For example, "B.Tech Computer Science" belongs to "City Engineering College".

1. In the left sidebar, go to **Institution Management → Courses**
2. Click **"+ Add Course"**
3. Fill in:
   - **College** (required) — select from the dropdown (shows all colleges you added)
   - **Course Name** (required) — e.g., *"B.Tech Computer Science"*
4. Click **"Save"**
5. The course appears in the list

**The Courses Table shows:**
| Column | What it is |
|--------|-----------|
| **ID** | Auto-generated number |
| **College** | Which college this course belongs to |
| **Name** | The course name |
| **Batches** | How many batches under this course |
| **Created** | Date added |
| **Actions** | Edit / Delete buttons |

---

## Step 3: Adding a Batch

A batch is a group of students within a course. Think of it as a classroom or section.

1. In the left sidebar, go to **Institution Management → Batches**
2. Click **"+ Add Batch"**
3. Fill in:
   - **College** (required) — select from dropdown
   - **Course** (required) — select from dropdown (shows courses under selected college)
   - **Batch Name** (required) — e.g., *"Batch 2025-A"*, *"Morning Section"*, *"Group 1"*
4. Click **"Save"**
5. The batch appears in the list

**You can filter batches** by college or course using the dropdown filters at the top of the page.

**The Batches Table shows:**
| Column | What it is |
|--------|-----------|
| **ID** | Auto-generated number |
| **Name** | The batch name |
| **Course** | Which course this batch belongs to |
| **College** | Which college (through the course) |
| **Students** | How many students in this batch (clickable number) |
| **Created** | Date added |
| **Actions** | Edit / Delete buttons |

---

## Step 4: Adding Students

You can add students **one by one** or **in bulk**.

### Adding a Single Student

1. Go to **Student Management → Students**
2. Click **"Add Student"**
3. Fill in:
   - **Name** (required)
   - **Email** (required) — this will be their login ID
   - **Roll Number** — optional, for your records
   - **Batch** (required) — select from dropdown
   - **Password** — if left blank, a random password is generated
4. Click **"Save"**
5. The student can now log in using their email and password

### Bulk Import Students from CSV

For adding many students at once:

1. On the **Students** page, scroll to the **"Import Students from CSV"** section
2. Your CSV file should have columns: `name, email, roll_number, batch_id` (ask your technical team for the exact batch IDs)
3. Click **"Choose File"**, select your CSV, and click **"Import"**

### What Students See After You Add Them

- Students receive a **verification email** (if self-registered)
- They log in at the login page by selecting **"Student"** role
- They see their **Student Dashboard** with assigned tests

---

## Step 5: Creating a Test (Assessment)

1. Click **"Create Assessment"** in the top bar, OR go to **Assessment Studio → Create Assessment**
2. Fill in the test details:

   | Field | What to enter |
   |-------|-------------|
   | **Assessment Title** | A clear name (e.g., "Midterm Physics Exam") |
   | **Batch** | Which student group should take this test |
   | **Duration (minutes)** | How long students have (e.g., 60 for 1 hour) |
   | **Start Time** | When the test becomes available (optional — leave blank to publish immediately) |
   | **End Time** | When the test closes automatically |
   | **Passing Marks** | Minimum score to pass (optional) |
   | **Negative Marking** | Deduct points for wrong answers (optional — e.g., 0.25 means -0.25 per wrong answer) |
   | **Shuffle Questions** | Randomize question order for each student |
   | **Instructions** | Text shown to students before they begin |

3. Click **"Save"** to create the test
4. You'll be taken to the **Question Editor** where you can:
   - **Add questions manually** (MCQ, Coding, or Explanation)
   - **Import from CSV** (MCQ only — see the CSV section below)
   - **Pick from Question Library** (reuse questions from previous tests)
5. When ready, click **"Publish"** to make it available to students

> 💡 **Publishing options:**
> - **Publish Now** — test goes live immediately
> - **Schedule** — set a future start time
> - **Save as Draft** — come back later to finish

---

# 📂 LEFT SIDEBAR — Complete Guide to Every Menu Item

The sidebar on the left is your control panel. Each item opens a different page. Here's exactly what you'll find on each page — every button, every table, every feature explained.

---

## 📊 Overview

### Dashboard
**What you see:** Your home screen with statistics cards (student count, assessment count, active tests, pending actions), performance charts (line graph and segmented rings), 6 quick-action shortcut cards, recent activity tables, live activity feed, and system status indicators.

**What you can do here:**
- See a bird's-eye view of everything happening in the system
- Click any stat card arrow → to drill into details
- Click Quick Action cards → to jump to common tasks
- Hover over the performance chart → to see exact scores
- Monitor live activity → auto-refreshes in real time
- Check system status → green = healthy, yellow = needs attention

---

## 🏛️ Institution Management

### Colleges
**What you see:** A table listing all colleges with columns: ID, Name, Address, Courses count (shows how many courses under each college), Created date, and Action buttons.

**What you can do here:**
| Button / Feature | How to use it |
|-----------------|---------------|
| **+ Add College** | Opens a pop-up form. Enter **College Name** (required) and **Address** (optional). Click Save. |
| **Edit** (in Actions column) | Opens the same form pre-filled. Change the name or address, click Save. |
| **Delete** (in Actions column) | Click to remove the college. ⚠️ Warning: This also deletes all courses and batches under it. |
| **Courses badge** (number) | Shows how many courses belong to this college. Click to see them (if linked). |

### Courses
**What you see:** A table listing all courses with columns: ID, College (which college it belongs to), Name, Batches count (how many batches under this course), Created date, and Actions.

**What you can do here:**
| Button / Feature | How to use it |
|-----------------|---------------|
| **+ Add Course** | Opens a form. First select a **College** from the dropdown, then enter the **Course Name**. Click Save. |
| **Edit** | Change the course name or move it to a different college. |
| **Delete** | Remove the course. ⚠️ This also deletes all batches and student assignments. |
| **Batches badge** | Shows how many batches exist under this course. |

### Batches
**What you see:** A filterable table listing all batches with columns: ID, Name, Course, College, Students count, Created date, and Actions. At the top, there are **dropdown filters** to narrow by College or Course.

**What you can do here:**
| Button / Feature | How to use it |
|-----------------|---------------|
| **+ Add Batch** | Opens a form. Select **College** → then **Course** (filters based on college) → enter **Batch Name** (e.g., "Batch 2025-A", "Morning Section"). Click Save. |
| **College / Course filters** | Use the dropdowns at the top to show only batches from a specific college or course. |
| **Edit** | Change the batch name or reassign it to a different course. |
| **Delete** | Remove the batch. |
| **Students badge** | Shows how many students are in this batch. Click to view them. |

---

## 👥 Student Management

### Students
**What you see:** A searchable table of all registered students with columns: Name, Email, Roll Number, Batch, Status (Active or Pending), Created date, and Actions. At the top, there's a **search bar** and **filter dropdowns**.

**What you can do here:**
| Button / Feature | How to use it |
|-----------------|---------------|
| **Add Student** | Opens a form. Fill in: **Name**, **Email** (their login ID), **Roll Number** (optional), **Batch** (select from dropdown), **Password** (leave blank to auto-generate). Click Save. |
| **Search bar** | Type any name, email, or roll number to find a specific student. |
| **Batch filter** | Show only students from a specific batch. |
| **Edit** | Update student details or change their batch. |
| **Delete** | Remove the student from the system. |
| **Generate Guest Link / QR Code** | Opens a form to create temporary guest access. Select a **Batch**, optionally pick a **specific test**, choose **Guest Link** (URL) or **QR Code** (scannable image). Click Generate. *(See the Guest Link section below for details.)* |

### Pending Verifications
**What you see:** A list of students who registered themselves but haven't been approved yet. Columns: Name, Email, Roll Number, Batch, Registered date, and Verify button.

**What you can do here:**
| Button / Feature | How to use it |
|-----------------|---------------|
| **Verify** | Click to approve the student. They'll be able to log in immediately after verification. |
| **Search / Filter** | Find specific pending students by name or batch. |

### Guest Access
**What you see:** *(This is the same as the "Generate Guest Link / QR Code" feature on the Students page, described in detail in its own section below.)*

---

## 🎨 Assessment Studio

### Create Assessment
**What you see:** A multi-step form to build a new test.

**Step 1 — Test Settings:**
| Field | What to enter |
|-------|-------------|
| **Assessment Title** | A clear name (e.g., "Midterm Physics Exam") |
| **Batch** | Which student group should take this test |
| **Duration (minutes)** | How long students have (e.g., 60 = 1 hour) |
| **Start Time** | When the test opens (optional) |
| **End Time** | When the test closes automatically |
| **Passing Marks** | Minimum score to pass (optional, 0 = not set) |
| **Negative Marking** | Points deducted for wrong answers (e.g., 0.25) |
| **Shuffle Questions** | Check this to randomize question order per student |
| **Instructions** | Text shown to students before they start |

Click **"Save"** to create the test.

**Step 2 — Question Editor (after saving):**
Now you're on the question editing page. Here you can:

| Section | What you can do |
|---------|----------------|
| **Add Question Manually** | Select question type (MCQ / Coding / Explanation), enter marks, type the question, add options (for MCQ), select correct answer, click "Add Question" |
| **Bulk Import from CSV** | Upload a CSV file with MCQ questions (see the CSV section for format) |
| **Question Library** | Browse and import questions you've used in previous tests |

**Step 3 — Publish:**
| Option | What happens |
|--------|-------------|
| **Publish Now** | Test goes live immediately — students can start |
| **Schedule** | Set a future date/time for the test to go live |
| **Save as Draft** | Save and come back later to finish |

### Draft Assessments
**What you see:** A list of tests you've started but haven't published yet. Each shows the title, batch, course, last edited date, and a link to continue editing.

**What you can do here:**
| Button | What it does |
|--------|-------------|
| **Continue Editing** | Takes you back to the question editor to add more questions or change settings |
| **Delete Draft** | Discard the draft permanently |

### Question Library
**What you see:** A searchable, filterable bank of **every question** ever created across all tests. Shows: Question text, Type (MCQ/Coding/Explanation), which test it came from, batch, course, and college.

**What you can do here:**
| Button / Feature | How to use it |
|-----------------|---------------|
| **Search** | Type keywords to find specific questions |
| **Type filter** | Filter by MCQ, Coding, or Explanation |
| **Stats at top** | See total counts: "245 Total Questions · 180 MCQ · 40 Coding · 25 Explanation" |
| **+ New Question** | Jump directly to the Assessment Studio to create a new question |

---

## 📋 Assessment Management

### All Assessments
**What you see:** A tabbed interface with 4 tabs — **Upcoming**, **Live**, **Paused**, **Completed**. Each tab shows a table of tests in that status. Each row shows: Title, Batch, Course, Questions count, Submissions, Students, and Action buttons.

**What you can do in each tab:**

**Upcoming Tab** (tests scheduled but not yet started):
| Button | What it does |
|--------|-------------|
| **Edit** | Change test settings or add more questions |
| **Publish** | Make the test live immediately |
| **Delete** | Remove the test permanently |

**Live Tab** (tests currently running):
| Button | What it does |
|--------|-------------|
| **Live Monitor** | Open the live monitor for this test |
| **Pause** | Temporarily stop the test (students can resume later) |
| **End** | Permanently end the test (no more submissions) |
| **Extend Time** | Add extra minutes to all students currently taking the test |

**Paused Tab** (tests temporarily stopped):
| Button | What it does |
|--------|-------------|
| **Resume** | Reactivate the test so students can continue |
| **End** | Permanently end the test |
| **Edit** | Change settings before resuming |

**Completed Tab** (finished tests):
| Button | What it does |
|--------|-------------|
| **View Results** | See all scores and performance data |
| **Grading** | Grade any remaining coding/explanation questions |
| **Reports** | Generate detailed PCI reports |

### Live Monitor
**What you see:** First, a list of active tests to choose from. After selecting a test, you see a **real-time dashboard** showing:

| Section | What it shows |
|---------|---------------|
| **Summary cards** | Total students, Active now, Submitted, Time remaining |
| **Student list** | Each student: name, status (In Progress / Submitted), time spent, questions answered, tab switches |
| **Live indicators** | Green pulsing dot next to students currently taking the test |
| **Refresh** | Auto-updates to show latest activity |

**What you can do here:**
| Button / Feature | How to use it |
|-----------------|---------------|
| **Select a test** | Pick from the list of currently active tests |
| **Refresh** | Click to get the latest data |
| **View student** | Click a student's name to see their detailed progress |
| **Monitor tab switches** | The "Switches" column shows how many times a student switched tabs (potential cheating indicator) |

### Grading
**What you see:** A two-step page:

**Step 1 — Select Test:**
- Dropdown showing all tests with submissions needing grading
- Each test shows: title, batch, and number of pending submissions

**Step 2 — Grade Submissions:**
- List of students who submitted, with columns: Name, Roll #, Submitted time, Status, Score, Ungraded count, and Grade button
- Click **"Grade"** to open the detailed grading view

**Grading View (detailed):**
For each question, you see:
| Question Type | What you see | What you do |
|--------------|-------------|-------------|
| **MCQ** | The question, all options (correct answer marked with ✅, student's selection), auto-calculated score | Override marks if needed (e.g., give full points for a flawed question) |
| **Coding** | The question, student's code in a read-only box | Read the code, enter marks (0 to max) |
| **Explanation** | The question, student's written answer | Read the answer, enter marks (0 to max) |

At the bottom: **"Save All Grades"** button — click when done. This marks the submission as **Evaluated**.

---

## 📊 Reports & Analytics

### Reports
**What you see:** A detailed reporting page where you can:
| Feature | What it does |
|---------|-------------|
| **Select Test** | Pick a test to analyse |
| **Select Student** | Drill into a specific student's performance |
| **Generate PCI Report** | Click to compute the Performance Competency Index — a weighted score combining MCQ (40%), Coding (30%), and Explanation (30%) performance |

**PCI Report shows:**
- Overall PCI score (0–100%)
- Per-category breakdown (MCQ, Coding, Explanation)
- Student ranking within the batch

### Tab Activity
**What you see:** A list of tests with tab-switching data. After selecting a test, you see a table of students and how many times they switched tabs during the test.

**What you can do here:**
| Button / Feature | How to use it |
|-----------------|---------------|
| **Select Test** | Pick a test from the dropdown |
| **Sort by switches** | See which students switched tabs the most |
| **Extend Timer** | If a student had technical issues, you can add extra minutes to their timer |
| **View details** | See when each switch happened (first switch, last switch) |

### Failed Logins
**What you see:** A log of unsuccessful login attempts with columns: Email used, IP address, Attempt type, Timestamp, and User agent (browser info).

**What you can do here:**
| Button / Feature | How to use it |
|-----------------|---------------|
| **Search by email** | Find attempts from a specific email address |
| **Filter by type** | Filter by attempt type (e.g., wrong password, account not found) |
| **Pagination** | Browse through pages of logs (50 per page) |

---

## ⚙️ System

### Settings
**What you see:** *(Feature coming soon)* This page will eventually let you configure:
- Email server settings (for sending verification emails)
- Authentication options
- Platform branding
- Security preferences

Currently shows a "Coming Soon" message.

### Activity Logs
**What you see:** *(Feature coming soon)* This page will eventually show a complete audit trail of:
- All admin actions (who created/deleted what)
- Student logins and activity
- Assessment changes
- System events

Currently shows a "Coming Soon" message.

---

## ❓ Support

### Help & Documentation
**What you see:** Built-in help resources and links to documentation (including this manual).

---

## Bottom of Sidebar

| Button | What it does |
|--------|-------------|
| **🚪 Sign Out** | Logs you out of the system and returns you to the login page |

---

---

# 🧑‍🎓 STUDENT EXPERIENCE

This is what students see when they log in.

## Student Dashboard

- **Welcome greeting** with the student's name and today's date
- **Statistics cards:** How many tests they have total, completed, and pending
- **My Tests:** A list of assigned tests with buttons to Start or View Results
- **Info card:** Helpful tips about the platform

## Student Sidebar

| Menu Item | What it does |
|-----------|-------------|
| **📊 Dashboard** | Home screen with test overview |
| **📝 My Tests** | View all assigned tests |
| **📈 Results** | See scores and marks for completed tests |
| **📉 Analytics** | Charts showing performance trends over time |
| **👤 Profile** | View and edit personal information |
| **🌙 Dark/Light Mode** | Switch between dark and light themes |
| **🚪 Sign Out** | Log out |

## Taking a Test

1. Click **"Start Test"** on any active test
2. A full-screen test interface opens with a **timer** counting down
3. Answer questions by clicking on your choice
4. The **question navigator** (dots on the side) shows which questions you've answered
5. Click **"Submit"** when finished
6. **⚠️ Important:** The system detects if you switch tabs — excessive tab switching may be flagged

---

# 🛠️ COMMON TASKS — Step by Step

## Creating a New Test

1. Click **"Create Assessment"** in the top bar, OR go to **Assessment Studio → Create Assessment** in the sidebar
2. Fill in the details:
   - **Title** — Give your test a name (e.g., "Midterm Physics")
   - **Duration** — How many minutes students have to complete it
   - **Batch** — Which student group should take this test
3. **Add questions:**
   - Write new questions, or
   - Pick questions from your **Question Library**
4. Choose question types (multiple choice, true/false, etc.)
5. Click **"Save as Draft"** to finish later, or **"Publish"** to make it available to students

## Verifying a New Student

1. Go to **Student Management → Pending Verifications** in the sidebar
2. You'll see a list of students waiting for approval
3. Click **"Verify"** next to their name to approve them
4. They'll receive an email confirming their account is active

## Viewing Test Results

1. Go to **Assessment Management → All Assessments**
2. Find the test you want to review
3. Click the **"Results"** button to see:
   - Each student's score
   - Class average
   - Question-by-question breakdown

## Pausing or Ending a Test

1. Go to **Assessment Management → All Assessments**
2. Find the active test
3. Click:
   - **"Pause"** to temporarily stop the test (students can resume later)
   - **"End"** to permanently stop it (no more submissions allowed)

---

# 🔗 GUEST LINK & QR CODE — Giving Test Access Without Login

Guest access lets students take a test **without creating an account**. This is useful for:
- **Demo tests** — let prospective students try the platform
- **One-time exams** — no need to create permanent accounts
- **Walk-in candidates** — quick access via QR code scan

> ⚠️ Guest access is **temporary** — it expires after the test ends or 30 days, whichever comes first.

---

## How Guest Access Works (Simplified)

```
You generate a link/QR → Share it with students → They open it → They take the test
```

No email. No password. No verification. Just click and go.

---

## Generating a Guest Link or QR Code

1. Go to **Student Management → Students** in the sidebar
2. Look for the **"Generate Guest Link / QR Code"** section (usually near the top or as a button)
3. A pop-up form appears with these fields:

   | Field | What to select |
   |-------|---------------|
   | **Batch** | Which student group this guest access is for |
   | **Specific Test** (optional) | If you want to limit access to one test only. Leave blank to allow access to all tests assigned to that batch |
   | **Link Type** | Choose **Guest Link** (a web URL) or **QR Code** (scannable image) |

4. Click **"Generate"**

### What You Get

**If you chose Guest Link:**
> You'll see a long URL that looks like:
> ```
> https://yourdomain.com/guest.php?token=abc123xyz...
> ```
> Copy this link and send it to your students via email, WhatsApp, or any messaging app.

**If you chose QR Code:**
> You'll see a **square QR code image** with the same link encoded in it.
> - Students can scan it with their phone camera (iPhone, Android)
> - Most modern phones scan QR codes automatically from the camera app
> - No special app needed

---

## How Students Use Guest Access

### Via Link
1. Student clicks the link you sent them
2. They land on a **Guest Access** page
3. The token is already filled in — they just click **"Access Tests"**
4. They're taken directly to their assigned test

### Via QR Code
1. Student opens their phone's **Camera app**
2. Points it at the QR code
3. A notification pops up — "Open this link in browser?"
4. They tap **"Open"**
5. Same as above — they're in the test instantly

### Manual Token Entry (Backup)
If the link or QR doesn't work:
1. Go to the login page
2. Click **"Have a guest link? Click here"**
3. Type or paste the token (the long code after `?token=`)
4. Click **"Access Tests"**

---

## Managing Guest Entries

Guest entries are stored in the system and you can see them in the database. Each entry tracks:
- **Batch** it was created for
- **Test** (if limited to one test)
- **Token** — the unique code
- **Expiry date** — when it expires
- **Type** — Guest Link or QR Code

> 💡 **Tips:**
> - Generate a **new QR code for each batch** — don't reuse the same one across different groups
> - Guest links work on **any device** — phone, tablet, laptop
> - You can generate **multiple guest links** for different batches or tests
> - If a link expires, just generate a new one

---

# 📝 WORKING WITH QUESTIONS — In Detail

The system supports **three types of questions**:

| Type | What it is | How it's scored |
|------|-----------|-----------------|
| **MCQ** (Multiple Choice) | Student picks one correct answer from A, B, C, or D | ✅ Auto-graded — the system marks it right or wrong instantly |
| **Coding** | Student writes code (Python, JavaScript, etc.) | 👩‍🏫 Manually graded — you read the code and assign marks |
| **Explanation** | Student writes a paragraph explaining a concept | 👩‍🏫 Manually graded — you read the answer and assign marks |

---

## 🎯 Adding Questions One by One (Manual)

When creating or editing a test, scroll to the **"Add Question Manually"** section.

### Step 1: Choose the Question Type

Click the **Question Type** dropdown and select:
- **MCQ (Multiple Choice)** — for objective questions with 4 options
- **Coding** — for programming questions
- **Explanation** — for descriptive/theory questions

### Step 2: Set Marks

Enter how many points this question is worth (e.g., `1`, `2`, `5`, `10`).

### Step 3: Type Your Question

In the **Question Text** box, write the question clearly.

**Good examples:**
```
What is the capital of France?
```
```
Write a Python function to check if a number is prime.
```
```
Explain the process of photosynthesis.
```

### Step 4: Fill in the Answer Details

#### For MCQ Questions:

You'll see an **Options** box. Type each option on a new line:

```
Option A text here
Option B text here
Option C text here
Option D text here
```

**Example:**
```
London
Berlin
Paris
Madrid
```

Then select the **Correct Answer** from the dropdown (A, B, C, or D).

#### For Coding Questions:

The MCQ options box will disappear. Students will see a **code editor** where they can type their program. You'll grade it later manually.

#### For Explanation Questions:

Similar to Coding — students get a large text box to write their answer. You'll grade it manually.

### Step 5: Click **"Add Question"**

The question is added to your test. Repeat to add more questions.

---

## 📂 Bulk Upload via CSV File (for MCQ Questions Only)

If you have many MCQ questions, use a **CSV file** to add them all at once.

### What is a CSV file?

A CSV file is a simple text file that stores data in a table format. You can create it in **Microsoft Excel**, **Google Sheets**, or even **Notepad**.

Each row = one question.  
Each column = one piece of information about that question.

### The Exact CSV Format

Your CSV file must have these **7 columns** in this exact order:

| Column | What to put | Example |
|--------|-------------|---------|
| **Question Text** | The question you want to ask | `What is the capital of France?` |
| **Option A** | First answer choice | `London` |
| **Option B** | Second answer choice | `Berlin` |
| **Option C** | Third answer choice | `Paris` |
| **Option D** | Fourth answer choice | `Madrid` |
| **Correct Answer** | The correct letter: A, B, C, or D | `C` |
| **Marks** | Points for this question (1, 2, 5, etc.) | `1` |

### 📄 Sample CSV — Copy This Template

Open Notepad or any text editor, copy-paste the following, and save it as `questions.csv`:

```
question_text,option_a,option_b,option_c,option_d,correct_answer,marks
What is the capital of France?,London,Berlin,Paris,Madrid,C,1
Which planet is known as the Red Planet?,Venus,Mars,Jupiter,Saturn,B,1
What is 2 + 2?,3,4,5,6,B,1
Which gas do plants absorb from the atmosphere?,Oxygen,Nitrogen,Carbon Dioxide,Hydrogen,C,2
What is the largest ocean on Earth?,Atlantic,Indian,Arctic,Pacific,D,1
Which animal is known as the King of the Jungle?,Tiger,Lion,Elephant,Giraffe,B,1
What is the chemical symbol for water?,H2O,CO2,NaCl,O2,A,2
How many days are in a leap year?,365,366,364,367,B,1
Which country has the largest population?,USA,India,China,Indonesia,B,1
What is the speed of light approximately?,300000 km/s,150000 km/s,500000 km/s,100000 km/s,A,2
```

### How to Create in Excel / Google Sheets

1. Open Excel or Google Sheets
2. Create these **7 columns** in the first row:
   - `question_text` | `option_a` | `option_b` | `option_c` | `option_d` | `correct_answer` | `marks`
3. Fill in your questions row by row
4. Save as **CSV (Comma delimited) (*.csv)**

> ⚠️ **Important rules:**
> - The first row must be the column headers (as shown above)
> - Correct answer must be **A, B, C, or D** (capital letter)
> - Every question needs at least Option A and Option B
> - Marks must be a number (1, 2, 5, etc.)
> - Do not use commas inside your question text or options (they'll confuse the file)

### How to Upload the CSV File

1. Go to **Assessment Studio → Create Assessment** (or edit an existing draft)
2. Scroll to the **"Bulk Import from CSV"** section
3. Click **"Choose File"** and select your CSV file
4. Click **"Import CSV"**
5. You'll see a success message: *"Imported X questions successfully."*

> ⚠️ If there are errors (like missing options or wrong answer format), the system will show a warning message telling you which line to fix.

---

## ✅ How Grading Works (For Each Question Type)

### MCQ Grading (Automatic)

Good news — MCQ questions are **auto-graded**! The system knows the correct answer and marks it automatically:

- **Correct answer selected** → Student gets full marks
- **Wrong answer selected** → Student gets 0 marks
- **No answer selected** → Student gets 0 marks

You can still **override** the marks if needed (e.g., if a question had a typo and you want to give everyone full points).

### Coding Grading (Manual)

Since there's no single "right" answer for code, **you grade it yourself**:

1. Go to **Assessment Management → Grading** in the sidebar
2. Select the test you want to grade
3. You'll see a list of students who have submitted
4. Click **"Grade"** next to a student's name
5. You'll see:
   - The **question text** (what you asked)
   - The **student's code** in a read-only box
   - A **marks input field** (enter 0 to max points)
6. Read the code and enter a fair score
7. Click **"Save All Grades"**

### Explanation Grading (Manual)

Same process as Coding:

1. Go to **Assessment Management → Grading**
2. Select the test → click **"Grade"** next to a student
3. You'll see:
   - The **question text**
   - The **student's written answer**
   - A **marks input field**
4. Read the answer, judge its quality, and assign marks
5. Click **"Save All Grades"**

### The Grading Dashboard

When you visit the Grading page, you'll see a table showing:

| Column | What it tells you |
|--------|------------------|
| **Student** | Name and email |
| **Roll #** | Student's roll number |
| **Submitted** | When they submitted (e.g., "2 hours ago") |
| **Status** | Submitted (not yet graded) or Evaluated (graded) |
| **Score** | Current score out of total |
| **Ungraded** | Number of coding/explanation questions still needing marks |
| **Grade** | Click to start grading |

---

## 💡 Tips for Writing Good Questions

### MCQ Best Practices

✅ **Do:**
- Keep options roughly the same length
- Make wrong answers plausible (not obviously wrong)
- Use clear, simple language
- Write one question at a time (don't combine multiple questions)

❌ **Don't:**
- Use "All of the above" or "None of the above" (they're confusing)
- Make the correct answer consistently longer or shorter than wrong ones
- Use negative phrasing like "Which is NOT..." (unless you're careful)

### Coding Question Tips

- Be specific about what the code should do
- Mention the programming language (Python, JavaScript, etc.)
- Provide example input → expected output if possible
- Set marks based on complexity (5–10 marks for harder problems)

### Explanation Question Tips

- Ask open-ended questions that test understanding
- Mention what key points the answer should cover
- Set a reasonable word count expectation

---

# 🌙 Theme Toggle (Dark / Light Mode)

Found in the **top bar** (admin) or **sidebar** (student).  

| Icon | Mode |
|------|------|
| **Moon icon** 🌙 | Click to switch to **Dark Mode** — easier on the eyes in low light |
| **Sun icon** ☀️ | Click to switch to **Light Mode** — bright daytime view |

Your preference is remembered the next time you log in.

---

# ❓ FREQUENTLY ASKED QUESTIONS

**Q: A student says they can't log in. What do I check?**
> First, go to **Pending Verifications** and approve their account. If they're already verified, check that their email and password are correct.

**Q: I need to give a student extra time on a test.**
> Go to **All Assessments**, find the test, click **"Edit"**, and adjust the duration. Or contact your technical team to extend time for specific students.

**Q: How do I see if students cheated?**
> Go to **Reports → Tab Activity**. This shows which students switched tabs during the test and how many times.

**Q: Can I reuse questions from a previous test?**
> Yes! Add your questions to the **Question Library** first. Then when creating a new test, you can import them.

**Q: What's the difference between Pause and End?**
> **Pause** is temporary — students can resume where they left off. **End** is permanent — no one can submit after you end a test.

**Q: I accidentally created a test with wrong settings.**
> Don't worry. Save it as **Draft** (not Published). Drafts can be edited freely. If it's already published, you can **Pause** it, edit the settings, then resume.

**Q: How do I switch between Dark and Light mode?**
> Click the **🌙/☀️ icon** in the top bar (admin) or sidebar (student). Your preference will be remembered next time you log in.

**Q: What does the search bar search for?**
> It searches through all the navigation items — type "student" to find the Students page, "report" to find Reports, etc.

**Q: How do I add a question with an image or diagram?**
> Currently, questions support text only. You can describe the diagram in words. For example: *"Refer to the diagram showing the water cycle. What stage comes after evaporation?"*

**Q: Can I change the order of questions after adding them?**
> Questions appear in the order you add them. To reorder, you'd need to delete and re-add them in the desired order. Plan your question sequence before adding.

**Q: What happens if a student doesn't answer an MCQ?**
> It's marked as unanswered and they get 0 marks for that question. There's no negative marking unless you enable it in the test settings.

**Q: How do I give partial credit for a coding question?**
> On the Grading page, you can enter any marks from 0 to the maximum. For example, if a coding question is worth 10 marks and the student's code mostly works but has a small bug, you can give 7 marks.

**Q: Can I re-grade a question after saving?**
> Yes! Go back to **Grading**, select the test and student, change the marks, and click "Save All Grades" again. The system will update the total score.

**Q: What if my CSV upload fails?**
> Common reasons:
> - **Wrong file format** — make sure it's saved as `.csv` (not `.xlsx` or `.txt`)
> - **Missing columns** — your file must have all 7 columns in order
> - **Wrong answer letter** — use only A, B, C, or D (capital letter)
> - **Commas inside text** — avoid commas in your questions/options, or enclose them in quotes like `"My question, with a comma?"`
> 
> Fix the issue and try uploading again.

**Q: Is there a limit on how many questions I can add?**
> There's no hard limit, but for practical purposes, keep tests manageable — most students can handle 30–60 questions per session depending on duration.

---

# 📱 USING ON MOBILE

The system works on phones and tablets too.

- **On phones:** The sidebar is hidden by default. Tap the **☰ icon** (top left) to open it
- **On tablets:** The sidebar shrinks to show only icons
- **Graphs and charts** automatically resize to fit your screen
- Turn your phone **sideways (landscape)** for a wider view of tables and data

---

> 💡 **Need more help?** Contact your system administrator or the person who set up your Exam Portal. They can help with technical issues and account setup.
