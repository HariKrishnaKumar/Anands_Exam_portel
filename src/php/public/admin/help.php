<?php
$pageTitle = 'Help & Documentation';
require_once __DIR__ . '/../../includes/admin_header.php';

$pdo = getDB();

// ─── Live stats for the help header (non-tech friendly context) ────
$stats = [
    'colleges' => (int)$pdo->query("SELECT COUNT(*) FROM colleges")->fetchColumn(),
    'batches'  => (int)$pdo->query("SELECT COUNT(*) FROM batches")->fetchColumn(),
    'tests'    => (int)$pdo->query("SELECT COUNT(*) FROM tests")->fetchColumn(),
    'active'   => (int)$pdo->query("SELECT COUNT(*) FROM tests WHERE status='active'")->fetchColumn(),
];
?>

<style>
/* ═══════════════ Help & Documentation ═══════════════ */
.help-hero { display:flex; align-items:center; gap:var(--space-6); padding:var(--space-6) var(--space-7); border:1px solid var(--border); border-radius:var(--radius-2); background:var(--card); box-shadow:var(--shadow-1); margin-bottom:var(--space-6); }
.help-hero-icon { flex-shrink:0; width:56px; height:56px; border-radius:18px; display:grid; place-items:center; color:#fff; background:linear-gradient(135deg, var(--accent), var(--accent2)); box-shadow:var(--accent-glow); }
.help-hero h2 { margin:0 0 4px; font-size:1.35rem; font-weight:700; }
.help-hero p { margin:0; color:var(--text-muted); font-size:.92rem; line-height:1.55; }

/* Quick topic chips */
.help-topics { display:flex; flex-wrap:wrap; gap:var(--space-2); margin-bottom:var(--space-6); }
.help-topic { display:inline-flex; align-items:center; gap:8px; padding:8px 14px; border:1px solid var(--border); border-radius:999px; background:var(--card); color:var(--text); font-size:.84rem; font-weight:500; text-decoration:none; transition:all .18s ease; cursor:pointer; }
.help-topic:hover { border-color:var(--accent); color:var(--accent); box-shadow:var(--accent-glow); transform:translateY(-1px); }
.help-topic svg { width:15px; height:15px; }

/* Accordion (dropdown) sections */
.help-group { margin-bottom:var(--space-5); }
.help-group-title { display:flex; align-items:center; gap:10px; font-size:1.02rem; font-weight:700; color:var(--text); margin:0 0 var(--space-3); padding:0 4px; }
.help-group-title svg { width:18px; height:18px; color:var(--accent); }

.help-acc { margin-bottom:var(--space-3); border:1px solid var(--border); border-radius:var(--radius-2); background:var(--card); overflow:hidden; box-shadow:var(--shadow-1); transition:border-color .18s ease; }
.help-acc:hover { border-color:var(--border-strong); }
.help-acc[open] { border-color:var(--accent); }

.help-acc summary { list-style:none; cursor:pointer; display:flex; align-items:center; gap:var(--space-4); padding:var(--space-4) var(--space-5); user-select:none; }
.help-acc summary::-webkit-details-marker { display:none; }
.help-acc summary:focus-visible { outline:2px solid var(--accent); outline-offset:-2px; border-radius:inherit; }
.help-acc-head-icon { flex-shrink:0; width:38px; height:38px; border-radius:11px; display:grid; place-items:center; background:var(--accent-bg); color:var(--accent); }
.help-acc-head-icon svg { width:19px; height:19px; }
.help-acc-head-text { flex:1; min-width:0; }
.help-acc-head-text strong { display:block; font-size:.95rem; font-weight:650; color:var(--text); }
.help-acc-head-text span { display:block; font-size:.8rem; color:var(--text-muted); margin-top:2px; }
.help-acc-chevron { flex-shrink:0; color:var(--text-muted); transition:transform .22s ease; }
.help-acc[open] .help-acc-chevron { transform:rotate(180deg); color:var(--accent); }

.help-acc-body { padding:0 var(--space-5) var(--space-5); border-top:1px solid var(--border); padding-top:var(--space-4); animation:helpFade .22s ease; }
@keyframes helpFade { from { opacity:0; transform:translateY(-4px);} to { opacity:1; transform:none; } }
.help-acc-body p { margin:0 0 var(--space-3); font-size:.9rem; line-height:1.65; color:var(--text); }
.help-acc-body p:last-child { margin-bottom:0; }
.help-acc-body ol, .help-acc-body ul { margin:0 0 var(--space-3); padding-left:22px; font-size:.9rem; line-height:1.7; color:var(--text); }
.help-acc-body li { margin-bottom:var(--space-2); }
.help-acc-body li:last-child { margin-bottom:0; }
.help-acc-body code { background:var(--bg-secondary); border:1px solid var(--border); border-radius:6px; padding:2px 7px; font-size:.8rem; font-family:'Consolas','Cascadia Code',monospace; color:var(--accent); }
.help-acc-body strong { font-weight:650; }

.help-meta { display:flex; flex-wrap:wrap; gap:var(--space-2); margin:var(--space-4) 0 var(--space-2); }
.help-kbd { background:var(--bg-secondary); border:1px solid var(--border); border-bottom-width:2px; border-radius:6px; padding:2px 8px; font-size:.75rem; font-family:'Consolas',monospace; color:var(--text); }

.help-tip, .help-warn, .help-ok { display:flex; gap:10px; align-items:flex-start; border-radius:var(--radius-1); padding:12px 14px; margin:var(--space-3) 0; font-size:.86rem; line-height:1.55; }
.help-tip { background:var(--accent-bg); border:1px solid var(--accent-border, rgba(79,140,255,.25)); color:var(--text); }
.help-warn { background:var(--yellow-bg); border:1px solid var(--yellow-border); color:var(--text); }
.help-ok   { background:var(--green-bg);  border:1px solid var(--green-border);  color:var(--text); }
.help-tip svg, .help-warn svg, .help-ok svg { flex-shrink:0; width:17px; height:17px; margin-top:1px; }
.help-tip svg { color:var(--accent); }
.help-warn svg { color:var(--yellow); }
.help-ok svg { color:var(--green); }
.help-tip strong, .help-warn strong, .help-ok strong { font-weight:650; }

.help-steps { counter-reset:helpstep; margin:var(--space-3) 0; }
.help-step { position:relative; padding:0 0 var(--space-3) 34px; }
.help-step::before { counter-increment:helpstep; content:counter(helpstep); position:absolute; left:0; top:0; width:24px; height:24px; border-radius:50%; background:var(--accent); color:#fff; font-size:.75rem; font-weight:700; display:grid; place-items:center; box-shadow:var(--accent-glow); }
.help-step::after { content:''; position:absolute; left:11px; top:26px; bottom:2px; width:2px; background:var(--border); }
.help-step:last-child::after { display:none; }
.help-step strong { display:block; font-size:.88rem; margin-bottom:3px; }
.help-step p { margin:0; font-size:.86rem; color:var(--text-muted); line-height:1.6; }

.help-grid2 { display:grid; grid-template-columns:1fr 1fr; gap:var(--space-4); }
.help-mini { border:1px solid var(--border); border-radius:var(--radius-1); padding:14px 16px; background:var(--bg-primary); font-size:.84rem; line-height:1.6; }
.help-mini strong { display:flex; align-items:center; gap:8px; margin-bottom:6px; }
.help-mini svg { width:15px; height:15px; color:var(--accent); }
@media (max-width:760px) { .help-grid2 { grid-template-columns:1fr; } .help-hero { flex-direction:column; text-align:center; } }
</style>

<div class="dashboard-header">
    <div class="dashboard-header-left">
        <h1>Help &amp; Documentation</h1>
        <p class="dashboard-subtitle">Simple guides for every part of the platform — click any topic below</p>
    </div>
</div>

<!-- ─── Hero + Quick Topics ─────────────────────────────── -->
<div class="help-hero">
    <div class="help-hero-icon"><?= icon('help', 28) ?></div>
    <div>
        <h2>Welcome! How can we help you today?</h2>
        <p>This guide explains every feature in plain language, step by step. Click a topic below to open its instructions, or use the quick links to jump straight to what you need.</p>
    </div>
</div>

<div class="help-topics js-help-topics" role="navigation" aria-label="Help topics">
    <a class="help-topic" href="#get-started"><?= icon('dashboard', 15) ?> Getting Started</a>
    <a class="help-topic" href="#colleges"><?= icon('college', 15) ?> Colleges</a>
    <a class="help-topic" href="#courses"><?= icon('course', 15) ?> Courses</a>
    <a class="help-topic" href="#batches"><?= icon('batch', 15) ?> Batches</a>
    <a class="help-topic" href="#students"><?= icon('student', 15) ?> Students</a>
    <a class="help-topic" href="#guest"><?= icon('external-link', 15) ?> Guest Access &amp; QR</a>
    <a class="help-topic" href="#assessments"><?= icon('file-text', 15) ?> Assessments</a>
    <a class="help-topic" href="#questions"><?= icon('database', 15) ?> Question Library</a>
    <a class="help-topic" href="#manage"><?= icon('status', 15) ?> Managing Tests</a>
    <a class="help-topic" href="#grading"><?= icon('grading', 15) ?> Grading</a>
    <a class="help-topic" href="#monitor"><?= icon('pulse', 15) ?> Live Monitor</a>
    <a class="help-topic" href="#reports"><?= icon('chart', 15) ?> Reports</a>
    <a class="help-topic" href="#tab"><?= icon('activity', 15) ?> Tab Activity</a>
    <a class="help-topic" href="#security"><?= icon('shield', 15) ?> Security</a>
    <a class="help-topic" href="#troubleshoot"><?= icon('warning', 15) ?> Troubleshooting</a>
    <a class="help-topic" href="#faq"><?= icon('question-circle', 15) ?> FAQ</a>
</div>

<!-- ═══════════════ 1. GETTING STARTED ═══════════════ -->
<div class="help-group" id="get-started">
    <h3 class="help-group-title"><?= icon('dashboard', 18) ?> Getting Started</h3>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('login', 19) ?></span>
            <span class="help-acc-head-text"><strong>How do I sign in to the platform?</strong><span>Logging in as an administrator</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <div class="help-steps">
                <div class="help-step"><strong>Open the website</strong><p>Go to your platform address (for example <code>http://localhost:8000</code>) in any web browser.</p></div>
                <div class="help-step"><strong>Go to the Sign In page</strong><p>Click <strong>Sign In</strong> or <strong>Login</strong> in the top right corner.</p></div>
                <div class="help-step"><strong>Enter your details</strong><p>Type your email address and password, then choose <strong>Admin</strong> as the account type.</p></div>
                <div class="help-step"><strong>Click Sign In</strong><p>You will land on the Dashboard, which shows quick statistics and shortcuts.</p></div>
            </div>
            <div class="help-tip"><?= icon('lightbulb', 17) ?><span><strong>Tip:</strong> If the Dashboard doesn't appear, check for a red warning message at the top of the login form — it will tell you exactly what is wrong (for example, a wrong email or password).</span></div>
        </div>
    </details>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('grid', 19) ?></span>
            <span class="help-acc-head-text"><strong>Understanding the left menu</strong><span>What each menu item does</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <p>The blue panel on the left is your control room. Click any item to open that area:</p>
            <div class="help-grid2">
                <div class="help-mini"><strong><?= icon('dashboard', 15) ?> Dashboard</strong>Your home page — numbers and shortcuts at a glance.</div>
                <div class="help-mini"><strong><?= icon('college', 15) ?> Colleges</strong>Manage colleges, their streams, and their dashboards.</div>
                <div class="help-mini"><strong><?= icon('course', 15) ?> Courses</strong>Create and manage courses within colleges.</div>
                <div class="help-mini"><strong><?= icon('batch', 15) ?> Batches</strong>Groups of students who take the same tests together.</div>
                <div class="help-mini"><strong><?= icon('student', 15) ?> Students</strong>Add students, generate guest links and QR codes.</div>
                <div class="help-mini"><strong><?= icon('plus', 15) ?> Assessment Studio</strong>Build a new test from scratch, step by step.</div>
                <div class="help-mini"><strong><?= icon('database', 15) ?> Question Library</strong>Browse all questions across all tests.</div>
                <div class="help-mini"><strong><?= icon('status', 15) ?> Assessments</strong>See every test and control it (pause, resume, end).</div>
                <div class="help-mini"><strong><?= icon('pulse', 15) ?> Live Monitor</strong>Watch tests while students are taking them.</div>
                <div class="help-mini"><strong><?= icon('grading', 15) ?> Grading</strong>View and grade submitted answers.</div>
                <div class="help-mini"><strong><?= icon('chart', 15) ?> Reports</strong>Results, pass rates, and performance summaries.</div>
                <div class="help-mini"><strong><?= icon('warning', 15) ?> Failed Logins</strong>See who tried to sign in and failed.</div>
            </div>
        </div>
    </details>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('settings', 19) ?></span>
            <span class="help-acc-head-text"><strong>Personal settings &amp; appearance</strong><span>Theme, account options, and signing out</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <p>In the top bar you will find:</p>
            <ul>
                <li><strong>Create Assessment</strong> — jump straight into building a new test.</li>
                <li><strong>Moon / Sun button</strong> — switch between dark and light appearance.</li>
                <li><strong>Bell icon</strong> — notifications about your platform.</li>
                <li><strong>Your name (top right)</strong> — opens <strong>Account Settings</strong> and <strong>Sign Out</strong>.</li>
            </ul>
            <p>Use <strong>Sign Out</strong> whenever you leave the computer — it keeps your account safe.</p>
            <div class="help-tip"><?= icon('lightbulb', 17) ?><span><strong>Tip:</strong> Press <span class="help-kbd">Ctrl</span> + <span class="help-kbd">K</span> anywhere on the admin pages to search colleges, students, or tests instantly.</span></div>
        </div>
    </details>
</div>

<!-- ═══════════════ 2. COLLEGES ═══════════════ -->
<div class="help-group" id="colleges">
    <h3 class="help-group-title"><?= icon('college', 18) ?> Colleges</h3>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('plus', 19) ?></span>
            <span class="help-acc-head-text"><strong>How to add a new college</strong><span>The 4-step college wizard</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <div class="help-steps">
                <div class="help-step"><strong>Open Colleges</strong><p>From the left menu, click <strong>Colleges</strong>, then the <strong>Create College</strong> button.</p></div>
                <div class="help-step"><strong>Step 1 — Basic information</strong><p>Enter the college name, a short nickname (like <em>IIT Madras</em>), the year it was established, website, email, phone, address and country.</p></div>
                <div class="help-step"><strong>Step 2 — Academic information</strong><p>Add <strong>Streams</strong> (branches or departments such as Engineering, Science, Data Science). Each stream needs a name and a short ID code.</p></div>
                <div class="help-step"><strong>Step 3 — Batch creation</strong><p>Create <strong>Batches</strong> (groups of students, e.g. <em>Engineering 2026</em>). A batch belongs to one stream.</p></div>
                <div class="help-step"><strong>Step 4 — Review &amp; Confirm</strong><p>Check the summary, then confirm. Your college is now live and appears in <strong>Colleges</strong>.</p></div>
            </div>
            <div class="help-ok"><?= icon('check-circle', 17) ?><span><strong>You're done!</strong> Once a college exists, you can add courses, batches, students and tests for it.</span></div>
        </div>
    </details>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('building2', 19) ?></span>
            <span class="help-acc-head-text"><strong>College dashboard &amp; editing</strong><span>Viewing and changing college information</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <p>Every college has its own dashboard page. From <strong>Colleges</strong>, click a college name (or a row's <strong>View</strong> button) to open it.</p>
            <p>On the college dashboard you can review its details at a glance. To change information, go back to <strong>Colleges</strong> and use the edit option on that college's row.</p>
            <div class="help-tip"><?= icon('lightbulb', 17) ?><span><strong>Tip:</strong> College ID codes are short and unique — think of them like a registration number. You'll see them in lists to tell colleges apart quickly.</span></div>
        </div>
    </details>
</div>

<!-- ═══════════════ 3. COURSES ═══════════════ -->
<div class="help-group" id="courses">
    <h3 class="help-group-title"><?= icon('course', 18) ?> Courses</h3>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('book-open', 19) ?></span>
            <span class="help-acc-head-text"><strong>Adding and managing courses</strong><span>Courses sit inside colleges</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <div class="help-steps">
                <div class="help-step"><strong>Open Courses</strong><p>Click <strong>Courses</strong> in the left menu.</p></div>
                <div class="help-step"><strong>Add a course</strong><p>Click the <strong>Add Course</strong> button, pick the college it belongs to, enter a course name (e.g. <em>Data Structures</em>) and a course code.</p></div>
                <div class="help-step"><strong>Save</strong><p>Click save — the course now appears in the list, assigned to its college.</p></div>
            </div>
            <p>You can <strong>edit</strong> or <strong>delete</strong> a course using the buttons on its row. Deleting a course deletes the batches and students under it, so only remove courses you no longer need.</p>
            <div class="help-warn"><?= icon('triangle-alert', 17) ?><span><strong>Heads up:</strong> An exam/test always belongs to a <strong>batch</strong>, and a batch belongs to a <strong>course</strong>. So the order is always: College → Course → Batch → Test.</span></div>
        </div>
    </details>
</div>

<!-- ═══════════════ 4. BATCHES ═══════════════ -->
<div class="help-group" id="batches">
    <h3 class="help-group-title"><?= icon('batch', 18) ?> Batches</h3>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('layers', 19) ?></span>
            <span class="help-acc-head-text"><strong>Creating and managing batches</strong><span>Groups of students who take tests together</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <div class="help-steps">
                <div class="help-step"><strong>Open Batches</strong><p>Click <strong>Batches</strong> in the left menu.</p></div>
                <div class="help-step"><strong>Add a batch</strong><p>Click the <strong>Add Batch</strong> button. Choose the course it belongs to, and give the batch a name — for example <em>Engineering 2026 Batch A</em>.</p></div>
                <div class="help-step"><strong>Save</strong><p>Your batch is ready. Students added to this batch will see the tests created for it.</p></div>
            </div>
            <p>Batches are the <strong>target audience</strong> of every test — when you create a test you pick which batch takes it.</p>
        </div>
    </details>
</div>

<!-- ═══════════════ 5. STUDENTS ═══════════════ -->
<div class="help-group" id="students">
    <h3 class="help-group-title"><?= icon('student', 18) ?> Students</h3>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('user-plus', 19) ?></span>
            <span class="help-acc-head-text"><strong>Adding students</strong><span>Manual entry and filters</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <div class="help-steps">
                <div class="help-step"><strong>Open Students</strong><p>Click <strong>Students</strong> in the left menu.</p></div>
                <div class="help-step"><strong>Add a student</strong><p>Use the <strong>Add Student</strong> form: choose the batch, enter the student's name, email, roll number and a starting password.</p></div>
                <div class="help-step"><strong>Save</strong><p>The student now has a login account. Tell them their email and password — they sign in from the normal <strong>Login</strong> page as a <strong>Student</strong>.</p></div>
            </div>
            <p>Use the filters (college / course / batch) and the <strong>search box</strong> to find students quickly, even in large lists.</p>
        </div>
    </details>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('mail', 19) ?></span>
            <span class="help-acc-head-text"><strong>Students signing up themselves (OTP)</strong><span>Self-registration with email verification</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <p>Students can also create their own account:</p>
            <ol>
                <li>They open the <strong>Sign Up</strong> page and fill in their details, choosing the correct batch.</li>
                <li>The system sends a <strong>verification code (OTP)</strong> to their email.</li>
                <li>They enter the code on the <strong>Verify</strong> page.</li>
                <li>They can now sign in as a student and see the tests for their batch.</li>
            </ol>
            <p>New accounts that still need approval appear under <strong>Pending Verifications</strong>, where you can accept or reject them.</p>
        </div>
    </details>
</div>

<!-- ═══════════════ 6. GUEST ACCESS & QR ═══════════════ -->
<div class="help-group" id="guest">
    <h3 class="help-group-title"><?= icon('external-link', 18) ?> Guest Access &amp; QR Codes</h3>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('qr-code', 19) ?></span>
            <span class="help-acc-head-text"><strong>Creating a guest link or QR code</strong><span>Let students take a test without an account</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <div class="help-steps">
                <div class="help-step"><strong>Open Students</strong><p>Click <strong>Students</strong> in the left menu.</p></div>
                <div class="help-step"><strong>Choose the batch and test</strong><p>In the <strong>Guest Access</strong> section, pick the batch and (optionally) the test the guest should take.</p></div>
                <div class="help-step"><strong>Generate</strong><p>Click <strong>Guest Link</strong> for a clickable web address, or <strong>QR Code</strong> for a scannable image.</p></div>
                <div class="help-step"><strong>Share it</strong><p>Send the link by email/chat, or print / show the QR code. Guests open the link or scan the code to enter the test instantly — no account needed.</p></div>
            </div>
            <div class="help-tip"><?= icon('lightbulb', 17) ?><span><strong>Tip:</strong> If you pick a specific test, the guest lands directly on it. If you don't pick a test, the guest sees the dashboard for that batch instead.</span></div>
            <div class="help-warn"><?= icon('triangle-alert', 17) ?><span><strong>Important:</strong> When a test is chosen, the guest link stays valid <strong>until that test's end date</strong>. If a test's end date is in the past, the link falls back to 30 days from creation so it is never "born expired".</span></div>
        </div>
    </details>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('clock', 19) ?></span>
            <span class="help-acc-head-text"><strong>How long do guest links last?</strong><span>Expiry rules explained simply</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <ul>
                <li><strong>No test chosen:</strong> the link works for <strong>30 days</strong> from creation.</li>
                <li><strong>Test chosen:</strong> the link works until the test's <strong>end date &amp; time</strong>.</li>
                <li>After expiry, guests see <em>"Invalid or expired link"</em> — simply generate a fresh link.</li>
            </ul>
            <div class="help-tip"><?= icon('lightbulb', 17) ?><span><strong>Tip:</strong> Generate a fresh QR code right before the exam starts, so all codes are long-lasting and up to date.</span></div>
        </div>
    </details>
</div>

<!-- ═══════════════ 7. ASSESSMENTS ═══════════════ -->
<div class="help-group" id="assessments">
    <h3 class="help-group-title"><?= icon('file-text', 18) ?> Assessments (Tests)</h3>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('plus', 19) ?></span>
            <span class="help-acc-head-text"><strong>Creating a new assessment</strong><span>The Assessment Studio wizard</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <div class="help-steps">
                <div class="help-step"><strong>Open Assessment Studio</strong><p>Click <strong>Create Assessment</strong> (top bar) or <strong>Assessment Studio</strong> in the menu.</p></div>
                <div class="help-step"><strong>Step 1 — Basic information</strong><p>Give the test a <strong>title</strong> (e.g. <em>Mid-Term CSE Core</em>), a description, choose the <strong>batch</strong> that will take it, and set the <strong>duration</strong> in minutes.</p></div>
                <div class="help-step"><strong>Step 2 — Question builder</strong><p>Add questions one by one: choose the type (<strong>MCQ</strong>), enter the question, the answer options (one per line), the correct answer, and the marks. Tick <strong>Shuffle questions</strong> if you want the order randomized per student.</p></div>
                <div class="help-step"><strong>Step 3 — Review &amp; Publish</strong><p>Check your test, then <strong>publish</strong>. You'll also be able to set start/end times from the Assessment Management page.</p></div>
            </div>
            <div class="help-ok"><?= icon('check-circle', 17) ?><span><strong>Result:</strong> The test appears under <strong>Assessments</strong> and students in the chosen batch can take it once it is live.</span></div>
        </div>
    </details>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('upload', 19) ?></span>
            <span class="help-acc-head-text"><strong>Importing questions from a file</strong><span>Bulk-load many questions at once</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <p>In the Assessment Studio you can upload a CSV/text file with one question per line to add many questions quickly.</p>
            <p>Format: each line should contain the question, then your options, and the correct answer in the expected columns. The import screen shows exactly which columns are expected and warns you about any lines it couldn't read.</p>
            <div class="help-tip"><?= icon('lightbulb', 17) ?><span><strong>Tip:</strong> Import a small file first (2–3 questions) to check the format, then import the full file.</span></div>
        </div>
    </details>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('timer', 19) ?></span>
            <span class="help-acc-head-text"><strong>How tests work for students</strong><span>Timer, autosave, and submission explained</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <ul>
                <li>The student sees a <strong>countdown timer</strong> while taking the test. When it reaches zero, the test submits automatically.</li>
                <li>Answers are <strong>auto-saved</strong> as the student moves through questions — no need to press save.</li>
                <li>The question navigator panel lets students jump between questions and see which ones are answered.</li>
                <li>A <strong>Submit</strong> button finishes the test early if the student is done.</li>
                <li>If the student switches to another browser tab, the system <strong>quietly records</strong> it (that's the Tab Activity report).</li>
            </ul>
        </div>
    </details>
</div>

<!-- ═══════════════ 8. QUESTION LIBRARY ═══════════════ -->
<div class="help-group" id="questions">
    <h3 class="help-group-title"><?= icon('database', 18) ?> Question Library</h3>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('database', 19) ?></span>
            <span class="help-acc-head-text"><strong>Browsing all questions</strong><span>Search and filter by type</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <p>The <strong>Question Library</strong> shows every question stored in the platform — including the ones you added inside tests.</p>
            <ul>
                <li>Filter by question type (<strong>MCQ</strong>, <strong>Coding</strong>, <strong>Explanation</strong>).</li>
                <li>See the question text, correct answer, and marks at a glance.</li>
                <li>Use it as a reference while you plan new tests.</li>
            </ul>
            <p>Question types: <strong>MCQ</strong> = multiple choice with one correct option; <strong>Coding</strong> = programming questions; <strong>Explanation</strong> = written explanation answers.</p>
        </div>
    </details>
</div>

<!-- ═══════════════ 9. MANAGING TESTS ═══════════════ -->
<div class="help-group" id="manage">
    <h3 class="help-group-title"><?= icon('status', 18) ?> Managing Tests (Assessment Management)</h3>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('play', 19) ?></span>
            <span class="help-acc-head-text"><strong>Publishing &amp; scheduling a test</strong><span>Making a test available to students</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <div class="help-steps">
                <div class="help-step"><strong>Open Assessments</strong><p>Click <strong>Assessments</strong> in the left menu.</p></div>
                <div class="help-step"><strong>Publish now</strong><p>Find your test and click <strong>Publish</strong> to make it live immediately — students can start it right away.</p></div>
                <div class="help-step"><strong>Or schedule</strong><p>Use <strong>Schedule</strong> to choose a <strong>start date/time</strong> and an <strong>end date/time</strong>. The test opens and closes automatically.</p></div>
            </div>
            <div class="help-warn"><?= icon('triangle-alert', 17) ?><span><strong>Very important:</strong> always set an <strong>end date in the future</strong>! If the end time is in the past, students and guest links will see "Test is not available". This is the #1 cause of "my test won't open".</span></div>
        </div>
    </details>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('pause', 19) ?></span>
            <span class="help-acc-head-text"><strong>Pause, resume, and extend time</strong><span>Reacting during a live test</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <ul>
                <li><strong>Pause</strong> — freezes the test for everyone. Students see "Test Paused" and cannot continue until you resume. Useful when something goes wrong mid-exam.</li>
                <li><strong>Resume</strong> — continues the test after a pause.</li>
                <li><strong>Extend Time</strong> — add extra minutes to a specific student's timer (for example, a student who had a technical issue).</li>
                <li><strong>End Test (Force End)</strong> — closes the test for everyone immediately. Only use when the exam must stop.</li>
            </ul>
            <div class="help-tip"><?= icon('lightbulb', 17) ?><span><strong>Tip:</strong> Pausing and resuming does not erase anyone's progress — students keep their saved answers.</span></div>
        </div>
    </details>
</div>

<!-- ═══════════════ 10. GRADING ═══════════════ -->
<div class="help-group" id="grading">
    <h3 class="help-group-title"><?= icon('grading', 18) ?> Grading</h3>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('clipboard-check', 19) ?></span>
            <span class="help-acc-head-text"><strong>Checking and grading submissions</strong><span>From submitted answers to final scores</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <div class="help-steps">
                <div class="help-step"><strong>Open Grading</strong><p>Click <strong>Grading</strong> in the left menu.</p></div>
                <div class="help-step"><strong>Pick a test</strong><p>Select the test you want to grade. Submitted students appear in the list.</p></div>
                <div class="help-step"><strong>Open a submission</strong><p>Click a student to see their answers side by side with the correct answers.</p></div>
                <div class="help-step"><strong>Enter marks &amp; save</strong><p>Give marks for each question (MCQ comparisons are quick), then save. The student's score and percentage are updated immediately.</p></div>
            </div>
            <p>Students see "Submitted — awaiting result" until you evaluate. After evaluation, they see their score and the results report.</p>
        </div>
    </details>
</div>

<!-- ═══════════════ 11. LIVE MONITOR ═══════════════ -->
<div class="help-group" id="monitor">
    <h3 class="help-group-title"><?= icon('pulse', 18) ?> Live Monitor</h3>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('activity', 19) ?></span>
            <span class="help-acc-head-text"><strong>Watching a test in real time</strong><span>Who is taking the test right now</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <p><strong>Live Monitor</strong> shows you, in real time:</p>
            <ul>
                <li>Which tests are currently live.</li>
                <li>How many students have started, how many submitted.</li>
                <li>Student status at a glance (in progress / submitted).</li>
            </ul>
            <p>Use it during an exam to confirm everyone is connected and progressing normally.</p>
        </div>
    </details>
</div>

<!-- ═══════════════ 12. REPORTS ═══════════════ -->
<div class="help-group" id="reports">
    <h3 class="help-group-title"><?= icon('chart', 18) ?> Reports &amp; Analytics</h3>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('chart-bar-big', 19) ?></span>
            <span class="help-acc-head-text"><strong>Reading the reports</strong><span>Results and performance summaries</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <p>The <strong>Reports</strong> page gives you the overview:</p>
            <ul>
                <li><strong>Results per test</strong> — who took it, marks obtained, percentage.</li>
                <li><strong>Pass/fail summaries</strong> — how many students passed, with the pass threshold.</li>
                <li><strong>Performance trends</strong> — how groups performed over time.</li>
            </ul>
            <p>Numbers update as soon as grading is saved, so the report is always fresh.</p>
        </div>
    </details>
</div>

<!-- ═══════════════ 13. TAB ACTIVITY ═══════════════ -->
<div class="help-group" id="tab">
    <h3 class="help-group-title"><?= icon('activity', 18) ?> Tab Activity (Proctoring)</h3>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('eye', 19) ?></span>
            <span class="help-acc-head-text"><strong>Tracking tab switches during a test</strong><span>Seeing who left the test window</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <p>While students take a test, the platform quietly records if they <strong>switch to another browser tab or window</strong>. (This is the "tab switch" event.)</p>
            <p>In <strong>Tab Activity</strong> you can see:</p>
            <ul>
                <li>Which student switched tabs, and how many times.</li>
                <li>When the switches happened.</li>
                <li>Which test they were taking.</li>
            </ul>
            <div class="help-tip"><?= icon('lightbulb', 17) ?><span><strong>Tip:</strong> One accidental switch happens — look at the <em>number</em> and <em>timing</em> of switches before drawing conclusions. Frequent switching during a test may suggest help-seeking behavior.</span></div>
            <div class="help-ok"><?= icon('check-circle', 17) ?><span><strong>Fixed:</strong> A past bug made the platform crash instead of logging tab switches. The crash is gone — every switch is now recorded safely, and late switches (after submission) are ignored.</span></div>
        </div>
    </details>
</div>

<!-- ═══════════════ 14. SECURITY ═══════════════ -->
<div class="help-group" id="security">
    <h3 class="help-group-title"><?= icon('shield', 18) ?> Security &amp; Logs</h3>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('warning', 19) ?></span>
            <span class="help-acc-head-text"><strong>Failed logins &amp; activity logs</strong><span>Who tried to sign in, and what happened on the platform</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <ul>
                <li><strong>Failed Logins</strong> — every unsuccessful sign-in attempt, with the email used, the time, and the IP. Check it if you suspect someone is trying to guess passwords.</li>
                <li><strong>Activity Logs</strong> — the platform's journal: who created tests, generated QR codes, graded submissions, and more.</li>
                <li><strong>Pending Verifications</strong> — sign-up requests waiting for your approval. Review the person's details, then approve or reject.</li>
            </ul>
            <div class="help-tip"><?= icon('lightbulb', 17) ?><span><strong>Good practice:</strong> glance at Failed Logins after a busy period and approve pending students promptly so they can start their tests.</span></div>
        </div>
    </details>
</div>

<!-- ═══════════════ 15. TROUBLESHOOTING ═══════════════ -->
<div class="help-group" id="troubleshoot">
    <h3 class="help-group-title"><?= icon('warning', 18) ?> Troubleshooting</h3>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('triangle-alert', 19) ?></span>
            <span class="help-acc-head-text"><strong>"Test is not available right now"</strong><span>Why students can't open a test</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <p>This message means one of three things:</p>
            <ol>
                <li><strong>The test's end date has passed</strong> — edit the test and set an end date in the future.</li>
                <li><strong>The start date hasn't arrived yet</strong> — wait or publish now instead of scheduling.</li>
                <li><strong>The test is paused or ended</strong> — check Assessment Management and resume it.</li>
            </ol>
            <div class="help-warn"><?= icon('triangle-alert', 17) ?><span><strong>Most common cause:</strong> a test with an end date in the past. Always verify the end date is in the future when scheduling.</span></div>
        </div>
    </details>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('external-link', 19) ?></span>
            <span class="help-acc-head-text"><strong>"Invalid or expired link" (guest/QR)</strong><span>QR codes and guest links not working</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <ol>
                <li><strong>The link has expired</strong> — links tied to a test expire at the test's end time. Generate a fresh guest link or QR code.</li>
                <li><strong>The test was linked to a past date</strong> — old links created for tests that ended are dead by design. New links are protected from this bug (they default to 30 days).</li>
                <li><strong>The token was copied incorrectly</strong> — QR codes scanned with the phone camera sometimes add extra characters. Ask the guest to paste the link directly if scanning fails.</li>
            </ol>
            <div class="help-tip"><?= icon('lightbulb', 17) ?><span><strong>Quick fix:</strong> regenerate the link from <strong>Students → Guest Access</strong> at exam time and share the new one.</span></div>
        </div>
    </details>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('user', 19) ?></span>
            <span class="help-acc-head-text"><strong>Student can't sign in</strong><span>Login problems on the student side</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <ol>
                <li><strong>Check the account type</strong> — students must choose <strong>Student</strong> on the login page, not Admin.</li>
                <li><strong>Password reset</strong> — if they forgot the password, edit the student from <strong>Students</strong> and set a new one.</li>
                <li><strong>Pending verification</strong> — self-registered students can't sign in until you approve them under <strong>Pending Verifications</strong>.</li>
                <li><strong>Wrong batch</strong> — a student only sees tests for their batch. Confirm the student is in the right batch.</li>
            </ol>
        </div>
    </details>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('server', 19) ?></span>
            <span class="help-acc-head-text"><strong>Page shows a red technical error</strong><span>What to do when something crashes</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <p>A plain "Something went wrong" page usually means a <strong>technical problem behind the scenes</strong> (for example, the database is not running).</p>
            <ol>
                <li><strong>Refresh the page</strong> — a momentary hiccup often fixes itself.</li>
                <li><strong>Check the database</strong> — make sure the database service is started (contact your IT person with this note: <em>"MySQL should be running on port 3306"</em>).</li>
                <li><strong>Retry the action</strong> — if it still fails, note down what you were doing and tell your technical support. Include the words you saw in the error message.</li>
            </ol>
            <div class="help-ok"><?= icon('check-circle', 17) ?><span><strong>Already fixed bugs:</strong> tab-switch crashes, expired-date deadlinks, and college-dashboard 404 errors were all repaired. If a red error appears after an update, refresh or restart the server once.</span></div>
        </div>
    </details>
</div>

<!-- ═══════════════ 16. FAQ ═══════════════ -->
<div class="help-group" id="faq">
    <h3 class="help-group-title"><?= icon('question-circle', 18) ?> Frequently Asked Questions</h3>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('question-circle', 19) ?></span>
            <span class="help-acc-head-text"><strong>What is the difference between a college, course, and batch?</strong><span>Simple hierarchy explained</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <p>Think of it like a family tree:</p>
            <ul>
                <li><strong>College</strong> = the whole institution (e.g. <em>SR Institute of Technology</em>) with streams (departments).</li>
                <li><strong>Course</strong> = a subject taught in the college (e.g. <em>Data Structures</em>).</li>
                <li><strong>Batch</strong> = a group of students taking the course together (e.g. <em>2026 Batch A</em>).</li>
                <li><strong>Test</strong> = an assessment assigned to a batch.</li>
            </ul>
            <p>Students belong to batches; tests belong to batches. That's the whole chain!</p>
        </div>
    </details>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('refresh-cw', 19) ?></span>
            <span class="help-acc-head-text"><strong>Do students lose progress if the test is paused?</strong><span>Safety of answers during interruption</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <p><strong>No.</strong> Answers are saved automatically while the test runs. If you pause the test (or a student's internet drops), the answers already saved remain safe. When the test resumes, the student picks up exactly where they left off.</p>
        </div>
    </details>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('timer', 19) ?></span>
            <span class="help-acc-head-text"><strong>Can I give a student extra time?</strong><span>Extending the timer individually</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <p>Yes. In <strong>Assessment Management</strong>, open the test and use the <strong>Extend Time</strong> option for the specific student. Add 5, 10 or more minutes — their timer grows immediately. Perfect for students who had technical trouble at the start.</p>
        </div>
    </details>

    <details class="help-acc">
        <summary>
            <span class="help-acc-head-icon"><?= icon('chart-bar-big', 19) ?></span>
            <span class="help-acc-head-text"><strong>When do students see their scores?</strong><span>From submission to result</span></span>
            <span class="help-acc-chevron"><?= icon('chevron-down', 18) ?></span>
        </summary>
        <div class="help-acc-body">
            <p>As soon as a student submits, they see "Submitted — awaiting result". After you <strong>evaluate their submission on the Grading page</strong>, their score and percentage appear on the results page and in reports.</p>
        </div>
    </details>
</div>

<?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>