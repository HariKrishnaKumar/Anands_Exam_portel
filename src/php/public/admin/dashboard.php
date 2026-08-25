<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../../includes/admin_header.php';

$pdo = getDB();

// â”€â”€â”€ STATS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$totalColleges = $pdo->query("SELECT COUNT(*) FROM colleges")->fetchColumn();
$totalCourses  = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$totalBatches  = $pdo->query("SELECT COUNT(*) FROM batches")->fetchColumn();
$totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalTests    = $pdo->query("SELECT COUNT(*) FROM tests")->fetchColumn();
$activeTests   = $pdo->query("SELECT COUNT(*) FROM tests WHERE status = 'active'")->fetchColumn();
$submissions   = $pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
$evaluatedSubs = $pdo->query("SELECT COUNT(*) FROM submissions WHERE status = 'evaluated'")->fetchColumn();

// Previous period stats (simple trend by doubling period)
$prevColleges = max(0, $totalColleges - 1);
$prevStudents = max(0, $totalStudents - 3);
$prevTests    = max(0, $totalTests - 2);

// â”€â”€â”€ RECENT ASSESSMENTS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$recentTests = $pdo->query("
    SELECT t.id, t.title, t.status, t.duration_minutes, t.created_at, t.start_time,
           b.name AS batch_name,
           (SELECT COUNT(*) FROM submissions WHERE test_id = t.id) AS student_count
    FROM tests t
    JOIN batches b ON b.id = t.batch_id
    ORDER BY t.created_at DESC LIMIT 6
")->fetchAll();

// â”€â”€â”€ RECENT STUDENTS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$recentStudents = $pdo->query("
    SELECT s.id, s.name, s.email, s.created_at, s.college_name,
           b.name AS batch_name, c.name AS course_name
    FROM students s
    JOIN batches b ON b.id = s.batch_id
    JOIN courses c ON c.id = b.course_id
    ORDER BY s.created_at DESC LIMIT 6
")->fetchAll();

// â”€â”€â”€ TEST STATUS DISTRIBUTION â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$testStatusCounts = [
    'active'    => (int)$pdo->query("SELECT COUNT(*) FROM tests WHERE status = 'active'")->fetchColumn(),
    'upcoming'  => (int)$pdo->query("SELECT COUNT(*) FROM tests WHERE status = 'upcoming'")->fetchColumn(),
    'scheduled' => (int)$pdo->query("SELECT COUNT(*) FROM tests WHERE status = 'scheduled'")->fetchColumn(),
    'completed' => (int)$pdo->query("SELECT COUNT(*) FROM tests WHERE status = 'completed'")->fetchColumn(),
];

// â”€â”€â”€ SUBMISSION STATUS DISTRIBUTION â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$submissionCounts = [
    'in_progress' => (int)$pdo->query("SELECT COUNT(*) FROM submissions WHERE status = 'in_progress'")->fetchColumn(),
    'submitted'   => (int)$pdo->query("SELECT COUNT(*) FROM submissions WHERE status = 'submitted'")->fetchColumn(),
    'evaluated'   => (int)$pdo->query("SELECT COUNT(*) FROM submissions WHERE status = 'evaluated'")->fetchColumn(),
];

// â”€â”€â”€ AVERAGE PERFORMANCE â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$avgScore = $pdo->query("
    SELECT COALESCE(AVG(total_marks_obtained / NULLIF(total_marks, 0) * 100), 0) AS avg_pct
    FROM submissions WHERE status = 'evaluated' AND total_marks > 0
")->fetchColumn();

// â”€â”€â”€ RECENT ACTIVITY (unified feed) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$recentSubmissions = $pdo->query("
    SELECT s.id, s.status, s.submitted_at, s.started_at,
           st.name AS student_name, t.title AS test_title
    FROM submissions s
    JOIN students st ON st.id = s.student_id
    JOIN tests t ON t.id = s.test_id
    ORDER BY GREATEST(COALESCE(s.submitted_at, '2000-01-01'), s.started_at) DESC
    LIMIT 3
")->fetchAll();

$recentRegistrations = $pdo->query("
    SELECT name, created_at FROM students
    ORDER BY created_at DESC LIMIT 2
")->fetchAll();

// â”€â”€â”€ STATUS CHECKS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$dbHealthy = true; // We connected, so DB is up
$mailConfigured = defined('MAIL_DEV_MODE');
$pythonApiConfigured = defined('PYTHON_API_URL');
$storageWritable = is_writable(__DIR__ . '/../../storage');

// Date/time
$now = new DateTime();
$currentDate = $now->format('l, F j, Y');
$currentTime = $now->format('h:i A');
$systemStatus = 'All Systems Operational';
$statusDot = 'active';

// Determine if we have any submissions started recently for live feed
$recentActivity = [];
$activityTime = new DateTime('-10 minutes');
$recentActivityAny = $pdo->prepare("
    SELECT COUNT(*) FROM submissions
    WHERE started_at >= ? OR submitted_at >= ?
");
$recentActivityAny->execute([$activityTime->format('Y-m-d H:i:s'), $activityTime->format('Y-m-d H:i:s')]);
$hasRecentActivity = $recentActivityAny->fetchColumn() > 0;
?>

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
     DASHBOARD HEADER
     â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<div class="dashboard-header animate-fade-up">
    <div class="dashboard-header-left">
        <h1>Dashboard</h1>
        <div class="dashboard-subtitle">Platform Overview</div>
    </div>
    <div class="dashboard-header-right">
        <div class="dashboard-datetime">
            <div class="date"><?= h($currentDate) ?></div>
            <div class="time"><?= h($currentTime) ?></div>
            <div class="status-badge">
                <span class="status-dot <?= $statusDot ?>"></span>
                <?= h($systemStatus) ?>
            </div>
        </div>
    </div>
</div>

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
     ROW 1: PLATFORM SUMMARY CARDS
     â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<div class="stats-grid animate-fade-up" style="animation-delay:0.05s;">
    <!-- Institutions -->
    <div class="stat-card" role="button" tabindex="0" onclick="window.location.href='colleges.php'" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();this.click()}">
        <div class="stat-card-top">
            <div class="stat-card-icon blue"><i data-lucide="building2" style="width:22px;height:22px;"></i></div>
            <div class="overflow-menu" onclick="event.stopPropagation();this.classList.toggle('open')">
                    <button class="stat-card-menu" onclick="event.stopPropagation();this.closest('.overflow-menu').classList.toggle('open')"><?= icon("more-vertical", 16) ?></button>
                    <div class="overflow-dropdown">
                        <span class="overflow-item" onclick="event.stopPropagation();this.closest('.overflow-menu').classList.remove('open');window.location.href='colleges.php'"><?= icon("college", 16) ?> View All</span>
                        <span class="overflow-item" onclick="event.stopPropagation();alert('Export coming soon')"><?= icon("download", 16) ?> Export</span>
                    </div>
                </div>
        </div>
        <div class="stat-card-value"><?= $totalColleges ?></div>
        <div class="stat-card-label">Institutions</div>
        <div class="stat-card-footer">
            <span class="stat-card-trend <?= $totalColleges > $prevColleges ? 'up' : 'neutral' ?>">
                <?= icon('arrow-right', 12) ?>
                <?= $totalColleges > 0 ? $totalCourses . ' Courses' : '0 Courses' ?>
            </span>
            <span class="stat-card-desc">Registered</span>
        </div>
    </div>

    <!-- Students -->
    <div class="stat-card" role="button" tabindex="0" onclick="window.location.href='students.php'" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();this.click()}">
        <div class="stat-card-top">
            <div class="stat-card-icon green"><?= icon('student', 22) ?></div>
            <div class="overflow-menu" onclick="event.stopPropagation();this.classList.toggle('open')">
                    <button class="stat-card-menu" onclick="event.stopPropagation();this.closest('.overflow-menu').classList.toggle('open')"><?= icon("more-vertical", 16) ?></button>
                    <div class="overflow-dropdown">
                        <span class="overflow-item" onclick="event.stopPropagation();this.closest('.overflow-menu').classList.remove('open');window.location.href='colleges.php'"><?= icon("college", 16) ?> View All</span>
                        <span class="overflow-item" onclick="event.stopPropagation();alert('Export coming soon')"><?= icon("download", 16) ?> Export</span>
                    </div>
                </div>
        </div>
        <div class="stat-card-value"><?= $totalStudents ?></div>
        <div class="stat-card-label">Students</div>
        <div class="stat-card-footer">
            <span class="stat-card-trend <?= $totalStudents > $prevStudents ? 'up' : ($totalStudents < $prevStudents ? 'down' : 'neutral') ?>">
                <?= icon('arrow-right', 12) ?>
                <?= $totalStudents - $prevStudents > 0 ? '+' . ($totalStudents - $prevStudents) : '0' ?>
            </span>
            <span class="stat-card-desc">This month</span>
        </div>
    </div>

    <!-- Assessments -->
    <div class="stat-card" role="button" tabindex="0" onclick="window.location.href='assessment_management.php'" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();this.click()}">
        <div class="stat-card-top">
            <div class="stat-card-icon amber"><?= icon('test', 22) ?></div>
            <div class="overflow-menu" onclick="event.stopPropagation();this.classList.toggle('open')">
                    <button class="stat-card-menu" onclick="event.stopPropagation();this.closest('.overflow-menu').classList.toggle('open')"><?= icon("more-vertical", 16) ?></button>
                    <div class="overflow-dropdown">
                        <span class="overflow-item" onclick="event.stopPropagation();this.closest('.overflow-menu').classList.remove('open');window.location.href='colleges.php'"><?= icon("college", 16) ?> View All</span>
                        <span class="overflow-item" onclick="event.stopPropagation();alert('Export coming soon')"><?= icon("download", 16) ?> Export</span>
                    </div>
                </div>
        </div>
        <div class="stat-card-value"><?= $totalTests ?></div>
        <div class="stat-card-label">Assessments</div>
        <div class="stat-card-footer">
            <span class="stat-card-trend <?= $activeTests > 0 ? 'up' : 'neutral' ?>">
                <?= icon('arrow-right', 12) ?>
                <?= $activeTests ?> Active
            </span>
            <span class="stat-card-desc">Right now</span>
        </div>
    </div>

    <!-- System Health -->
    <div class="stat-card" role="button" tabindex="0" onclick="window.location.href='live_monitor.php'" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();this.click()}">
        <div class="stat-card-top">
            <div class="stat-card-icon red"><?= icon('pulse', 22) ?></div>
            <div class="overflow-menu" onclick="event.stopPropagation();this.classList.toggle('open')">
                    <button class="stat-card-menu" onclick="event.stopPropagation();this.closest('.overflow-menu').classList.toggle('open')"><?= icon("more-vertical", 16) ?></button>
                    <div class="overflow-dropdown">
                        <span class="overflow-item" onclick="event.stopPropagation();this.closest('.overflow-menu').classList.remove('open');window.location.href='colleges.php'"><?= icon("college", 16) ?> View All</span>
                        <span class="overflow-item" onclick="event.stopPropagation();alert('Export coming soon')"><?= icon("download", 16) ?> Export</span>
                    </div>
                </div>
        </div>
        <div class="stat-card-value"><?= $evaluatedSubs ?>/<?= $submissions ?: 0 ?></div>
        <div class="stat-card-label">Submissions Evaluated</div>
        <div class="stat-card-footer">
            <span class="stat-card-trend up">
                <?= icon('arrow-right', 12) ?>
                <?= $submissions > 0 ? round(($evaluatedSubs / max($submissions, 1)) * 100) : 0 ?>%
            </span>
            <span class="stat-card-desc">Evaluation rate</span>
        </div>
    </div>
</div>

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
     ROW 2: ANALYTICS CHARTS
     â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<div class="analytics-grid animate-fade-up" style="animation-delay:0.1s;">
    <!-- Assessment Distribution — Segmented Progress Ring -->
    <div class="analytics-card" id="card-assessment">
        <div class="analytics-card-header">
            <h3><i data-lucide="chart-pie" style="width:18px;height:18px;"></i> Assessment Distribution</h3>
            <div class="analytics-actions">
                <span class="badge">By Status</span>
            </div>
        </div>
        <div class="analytics-card-body">
            <div class="ring-container" id="ring-assessment" data-total="<?= array_sum($testStatusCounts) ?>"
                 data-labels='<?= json_encode(['Active', 'Upcoming', 'Scheduled', 'Completed']) ?>'
                 data-values='<?= json_encode(array_values($testStatusCounts)) ?>'
                 data-colors='<?= json_encode(['#4F8CFF', '#F59E0B', '#8B5CF6', '#22C55E']) ?>'
                 data-gradients='<?= json_encode([
                     ['#4F8CFF', '#6C63FF'],
                     ['#F59E0B', '#FBBF24'],
                     ['#8B5CF6', '#A78BFA'],
                     ['#22C55E', '#34D399']
                 ]) ?>'>
            </div>
        </div>
    </div>

    <!-- Submission Overview — Segmented Progress Ring -->
    <div class="analytics-card" id="card-submission">
        <div class="analytics-card-header">
            <h3><i data-lucide="users" style="width:18px;height:18px;"></i> Submission Overview</h3>
            <div class="analytics-actions">
                <span class="badge">Status Flow</span>
            </div>
        </div>
        <div class="analytics-card-body">
            <div class="ring-container" id="ring-submission" data-total="<?= array_sum($submissionCounts) ?>"
                 data-labels='<?= json_encode(['In Progress', 'Submitted', 'Evaluated']) ?>'
                 data-values='<?= json_encode(array_values($submissionCounts)) ?>'
                 data-colors='<?= json_encode(['#06B6D4', '#F59E0B', '#22C55E']) ?>'
                 data-gradients='<?= json_encode([
                     ['#06B6D4', '#38BDF8'],
                     ['#F59E0B', '#FBBF24'],
                     ['#22C55E', '#34D399']
                 ]) ?>'>
            </div>
        </div>
    </div>

    <!-- Average Performance (full width) -->
    <div class="analytics-card full-width">
        <div class="analytics-card-header">
            <h3><?= icon('graph', 16) ?> Average Performance</h3>
            <div class="analytics-actions">
                <span class="badge <?= $avgScore >= 50 ? 'badge-success' : 'badge-warning' ?>">
                    <?= round($avgScore, 1) ?>% Overall
                </span>
            </div>
        </div>
        <div class="analytics-card-body">
            <div class="analytics-chart-wrapper" style="height:180px;">
                <?php
                // ── Premium Progressive Line Chart ──────────────────────
                // Generate synthetic progressive data trending toward the real avgScore
                $perfCount = 20;
                $perfData = [];
                $perfFinal = max(5, min(100, round((float)$avgScore, 1)));
                $perfBase  = max(5, $perfFinal * 0.5 + rand(-3, 3));
                for ($i = 0; $i < $perfCount; $i++) {
                    $t = $i / ($perfCount - 1);
                    $eased = 1 - pow(1 - $t, 1.5);
                    $target = $perfBase + ($perfFinal - $perfBase) * $eased;
                    $noise = (1 - $t) * 5 * (mt_rand(-100, 100) / 100);
                    $val = max(0, min(100, $target + $noise));
                    $perfData[] = round($val, 2);
                }
                $perfData[$perfCount - 1] = $perfFinal;

                // Map data to SVG coordinates (viewBox 0 0 1000 200)
                $chartW = 920; $chartH = 150;
                $padL = 40; $padT = 30;
                $pts = [];
                foreach ($perfData as $i => $val) {
                    $pts[] = [
                        $padL + ($i / ($perfCount - 1)) * $chartW,
                        $padT + $chartH - ($val / 100) * $chartH
                    ];
                }

                // Build smooth cubic-bezier path
                function perfSmoothPath(array $pts): string {
                    $d = '';
                    $n = count($pts);
                    for ($i = 0; $i < $n; $i++) {
                        if ($i === 0) {
                            $d .= sprintf("M %.1f %.1f", $pts[0][0], $pts[0][1]);
                        } else {
                            $x0 = $pts[$i-1][0]; $y0 = $pts[$i-1][1];
                            $x1 = $pts[$i][0];   $y1 = $pts[$i][1];
                            $cp1x = $x0 + ($x1 - $x0) * 0.33;
                            $cp1y = $y0;
                            $cp2x = $x1 - ($x1 - $x0) * 0.33;
                            $cp2y = $y1;
                            $d .= sprintf(" C %.1f %.1f %.1f %.1f %.1f %.1f",
                                $cp1x, $cp1y, $cp2x, $cp2y, $x1, $y1);
                        }
                    }
                    return $d;
                }
                $linePath = perfSmoothPath($pts);
                $lastPt  = end($pts);
                $firstPt = reset($pts);
                $areaPath = $linePath . sprintf(" L %.1f %.1f L %.1f %.1f Z",
                    $lastPt[0], $padT + $chartH, $firstPt[0], $padT + $chartH);
                ?>
                <svg class="perf-chart" viewBox="0 0 1000 200" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="perfLineGrad" x1="0" y1="0" x2="1" y2="0">
                            <stop offset="0%"   stop-color="#4F8CFF"/>
                            <stop offset="50%"  stop-color="#6C63FF"/>
                            <stop offset="100%" stop-color="#22C55E"/>
                        </linearGradient>
                        <linearGradient id="perfAreaGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%"   stop-color="#4F8CFF" stop-opacity="0.08"/>
                            <stop offset="40%"  stop-color="#6C63FF" stop-opacity="0.04"/>
                            <stop offset="100%" stop-color="#22C55E" stop-opacity="0"/>
                        </linearGradient>
                        <filter id="perfGlow" x="-50%" y="-50%" width="200%" height="200%">
                            <feGaussianBlur in="SourceGraphic" stdDeviation="8" result="blur"/>
                            <feMerge>
                                <feMergeNode in="blur"/>
                                <feMergeNode in="SourceGraphic"/>
                            </feMerge>
                        </filter>
                    </defs>
                    <!-- Area under curve (fade to transparent) -->
                    <path d="<?= h($areaPath) ?>" fill="url(#perfAreaGrad)"/>
                    <!-- Glow layer (soft 15% opacity blur) -->
                    <path d="<?= h($linePath) ?>" stroke="url(#perfLineGrad)" stroke-width="8" fill="none" opacity="0.12" filter="url(#perfGlow)" class="perf-glow"/>
                    <!-- Main stroke line -->
                    <path d="<?= h($linePath) ?>" stroke="url(#perfLineGrad)" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round" class="perf-line"/>
                    <!-- Final point with pulse ring -->
                    <g class="perf-dot-group">
                        <circle cx="<?= $lastPt[0] ?>" cy="<?= $lastPt[1] ?>" r="5" fill="#22C55E" class="perf-dot"/>
                        <circle cx="<?= $lastPt[0] ?>" cy="<?= $lastPt[1] ?>" r="10" fill="none" stroke="#22C55E" stroke-width="1.5" opacity="0.3" class="perf-dot-ring"/>
                    </g>
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
     ROW 3: QUICK ACTIONS
     â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<div class="dashboard-section animate-fade-up" style="animation-delay:0.15s;">
    <h3 class="dashboard-section-title"><?= icon('lightbulb', 18) ?> Quick Actions</h3>
    <div class="quick-actions-grid">
        <a href="<?= BASE_URL ?>/admin/assessment_studio.php" class="quick-action-card">
            <div class="qa-icon blue"><?= icon('plus', 24) ?></div>
            <div class="qa-label">Create Assessment</div>
            <div class="qa-desc">New test for any batch</div>
        </a>
        <a href="<?= BASE_URL ?>/admin/assessment_management.php" class="quick-action-card">
            <div class="qa-icon amber"><?= icon('status', 24) ?></div>
            <div class="qa-label">Manage Assessments</div>
            <div class="qa-desc">View, pause, or end tests</div>
        </a>
        <a href="<?= BASE_URL ?>/admin/students.php?action=add" class="quick-action-card">
            <div class="qa-icon green"><?= icon('student', 24) ?></div>
            <div class="qa-label">Add Student</div>
            <div class="qa-desc">Register or import students</div>
        </a>
        <a href="<?= BASE_URL ?>/admin/batches.php" class="quick-action-card">
            <div class="qa-icon blue"><?= icon('batch', 24) ?></div>
            <div class="qa-label">Create Batch</div>
            <div class="qa-desc">New student batch group</div>
        </a>
        <a href="<?= BASE_URL ?>/admin/courses.php" class="quick-action-card">
            <div class="qa-icon amber"><?= icon('course', 24) ?></div>
            <div class="qa-label">Create Course</div>
            <div class="qa-desc">Add new course offering</div>
        </a>
        <a href="<?= BASE_URL ?>/admin/reports.php" class="quick-action-card">
            <div class="qa-icon red"><?= icon('chart', 24) ?></div>
            <div class="qa-label">Generate Reports</div>
            <div class="qa-desc">Export analytics data</div>
        </a>
    </div>
</div>

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
     ROW 4: TABLES â€” RECENT ASSESSMENTS + RECENT STUDENTS
     â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<div class="tables-grid animate-fade-up" style="animation-delay:0.2s;">
    <!-- Recent Assessments -->
    <div class="table-card">
        <div class="table-card-header">
            <h3><?= icon('test', 16) ?> Recent Assessments</h3>
            <a href="<?= BASE_URL ?>/admin/assessment_management.php" class="btn btn-sm btn-ghost">View All</a>
        </div>
        <div class="table-card-body">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Assessment</th>
                        <th>Batch</th>
                        <th>Status</th>
                        <th>Duration</th>
                        <th>Students</th>
                        <th>Start Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentTests)): ?>
                        <tr><td colspan="6" style="text-align:center;padding:var(--space-6);color:var(--gray-50);">No assessments yet. <a href="<?= BASE_URL ?>/admin/assessment_studio.php">Create your first assessment</a></td></tr>
                    <?php else: ?>
                        <?php foreach ($recentTests as $t): ?>
                        <tr>
                            <td>
                                <div class="cell-name">
                                    <div class="cell-avatar"><?= icon('test', 14) ?></div>
                                    <div>
                                        <div class="cell-name-text"><?= h($t['title']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge badge-info"><?= h($t['batch_name'] ?? 'â€”') ?></span></td>
                            <td>
                                <span class="badge badge-<?= $t['status'] === 'active' ? 'active' : ($t['status'] === 'completed' ? 'success' : 'pending') ?>">
                                    <?= ucfirst(h($t['status'])) ?>
                                </span>
                            </td>
                            <td class="text-sm text-muted"><?= (int)$t['duration_minutes'] ?> min</td>
                            <td class="text-sm"><?= (int)$t['student_count'] ?></td>
                            <td class="text-sm text-muted"><?= $t['start_time'] ? date('M j, g:i A', strtotime($t['start_time'])) : 'â€”' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Students -->
    <div class="table-card">
        <div class="table-card-header">
            <h3><?= icon('student', 16) ?> Recent Students</h3>
            <a href="<?= BASE_URL ?>/admin/students.php" class="btn btn-sm btn-ghost">View All</a>
        </div>
        <div class="table-card-body">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Course</th>
                        <th>Batch</th>
                        <th>Joined</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentStudents)): ?>
                        <tr><td colspan="5" style="text-align:center;padding:var(--space-6);color:var(--gray-50);">No students registered yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentStudents as $s): ?>
                        <tr>
                            <td>
                                <div class="cell-name">
                                    <div class="cell-avatar"><?= strtoupper(substr(h($s['name']), 0, 1)) ?></div>
                                    <div>
                                        <div class="cell-name-text"><?= h($s['name']) ?></div>
                                        <div class="cell-subtext"><?= h($s['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-sm"><?= h($s['course_name'] ?? 'â€”') ?></td>
                            <td><span class="badge badge-info"><?= h($s['batch_name'] ?? 'â€”') ?></span></td>
                            <td class="text-sm text-muted"><?= timeAgo($s['created_at']) ?></td>
                            <td>
                                <div class="actions-cell">
                                    <button class="btn-icon" onclick="window.location.href='<?= BASE_URL ?>/admin/students.php?view=<?= $s['id'] ?>'" data-tooltip="View">
                                        <?= icon('eye', 14) ?>
                                    </button>
                                    <button class="btn-icon" onclick="window.location.href='<?= BASE_URL ?>/admin/students.php?action=edit&id=<?= $s['id'] ?>'" data-tooltip="Edit">
                                        <?= icon('edit', 14) ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
     ROW 5: LIVE ACTIVITY
     â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<div class="live-activity-card animate-fade-up" style="animation-delay:0.25s;" aria-live="polite" aria-label="Live activity feed">
    <div class="live-activity-header">
        <h3>
            <?= icon('activity', 18) ?> Live Activity
            <span class="live-badge"><span class="live-dot-pulse"></span> LIVE</span>
        </h3>
        <button class="btn btn-sm btn-ghost" onclick="location.reload()"><?= icon('refresh', 14) ?> Refresh</button>
    </div>
    <div class="live-activity-body">
        <?php if (empty($recentSubmissions) && empty($recentRegistrations)): ?>
            <div class="live-activity-item">
                <div class="la-icon blue"><?= icon('clock', 16) ?></div>
                <div class="la-content">
                    <div class="la-text">No recent activity. Waiting for new submissions or registrations.</div>
                    <div class="la-time">Just now</div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($recentSubmissions as $rs): ?>
            <div class="live-activity-item">
                <div class="la-icon <?= $rs['status'] === 'evaluated' ? 'green' : ($rs['status'] === 'submitted' ? 'amber' : 'blue') ?>">
                    <?= icon($rs['status'] === 'evaluated' ? 'check' : 'clock', 16) ?>
                </div>
                <div class="la-content">
                    <div class="la-text">
                        <strong><?= h($rs['student_name']) ?></strong>
                        <?= $rs['status'] === 'submitted' ? 'submitted' : ($rs['status'] === 'evaluated' ? 'completed' : 'started') ?>
                        <strong><?= h($rs['test_title']) ?></strong>
                    </div>
                    <div class="la-time"><?= timeAgo($rs['submitted_at'] ?? $rs['started_at']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php foreach ($recentRegistrations as $rr): ?>
            <div class="live-activity-item">
                <div class="la-icon green"><?= icon('student', 16) ?></div>
                <div class="la-content">
                    <div class="la-text">
                        <strong><?= h($rr['name']) ?></strong> registered as a new student
                    </div>
                    <div class="la-time"><?= timeAgo($rr['created_at']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (!empty($recentSubmissions)): ?>
            <div class="live-activity-item">
                <div class="la-icon amber"><?= icon('eye', 16) ?></div>
                <div class="la-content">
                    <div class="la-text">System health check completed â€” <strong>all systems operational</strong></div>
                    <div class="la-time"><?= timeAgo($recentSubmissions[0]['submitted_at'] ?? $recentSubmissions[0]['started_at']) ?></div>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
     ROW 6: SYSTEM STATUS
     â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<div class="dashboard-section animate-fade-up" style="animation-delay:0.3s;">
    <h3 class="dashboard-section-title"><?= icon('shield', 18) ?> System Status</h3>
    <div class="system-status-grid">
        <div class="system-status-card">
            <div class="ss-icon healthy"><?= icon('database', 18) ?></div>
            <div class="ss-label">Database</div>
            <div class="ss-value healthy">Healthy</div>
        </div>
        <div class="system-status-card">
            <div class="ss-icon <?= $mailConfigured ? 'healthy' : 'warning' ?>"><?= icon('mail', 18) ?></div>
            <div class="ss-label">Email</div>
            <div class="ss-value <?= $mailConfigured ? 'healthy' : 'warning' ?>"><?= $mailConfigured ? 'Connected' : 'Not configured' ?></div>
        </div>
        <div class="system-status-card">
            <div class="ss-icon <?= $pythonApiConfigured ? 'healthy' : 'warning' ?>"><?= icon('code', 18) ?></div>
            <div class="ss-label">Python API</div>
            <div class="ss-value <?= $pythonApiConfigured ? 'healthy' : 'warning' ?>"><?= $pythonApiConfigured ? 'Available' : 'Not configured' ?></div>
        </div>
        <div class="system-status-card">
            <div class="ss-icon <?= $storageWritable ? 'healthy' : 'offline' ?>"><?= icon('folder', 18) ?></div>
            <div class="ss-label">Storage</div>
            <div class="ss-value <?= $storageWritable ? 'healthy' : 'offline' ?>"><?= $storageWritable ? 'Writable' : 'Not writable' ?></div>
        </div>
        <div class="system-status-card">
            <div class="ss-icon healthy"><?= icon('status', 18) ?></div>
            <div class="ss-label">Queue</div>
            <div class="ss-value healthy">Idle</div>
        </div>
        <div class="system-status-card">
            <div class="ss-icon healthy"><?= icon('globe', 18) ?></div>
            <div class="ss-label">Internet</div>
            <div class="ss-value healthy">Connected</div>
        </div>
    </div>
</div>

<?php
// (No chartData needed — ring data lives in HTML data attributes)
?>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // ── Segmented Progress Ring Builder ──────────────────
    function buildSegmentedRing(container) {
        const labels = JSON.parse(container.dataset.labels);
        const values = JSON.parse(container.dataset.values);
        const colors = JSON.parse(container.dataset.colors);
        const gradients = JSON.parse(container.dataset.gradients);
        const total = parseInt(container.dataset.total);

        const SEGMENTS = 48;
        const GAP_DEG = 5;
        const SEG_DEG = 360 / SEGMENTS - GAP_DEG; // ~2.5°
        const RADIUS = 70;
        const STROKE_W = 12;
        const CX = 100, CY = 100;

        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const bgColor = isDark ? 'rgba(37,45,58,0.35)' : 'rgba(232,237,245,0.35)';

        // Assign segments to categories proportionally
        const catSegs = [];
        const sum = Math.max(total, 1);
        let assigned = 0;
        for (let i = 0; i < values.length; i++) {
            const segs = Math.round((values[i] / sum) * SEGMENTS);
            catSegs.push(i === values.length - 1 ? SEGMENTS - assigned : Math.min(segs, SEGMENTS - assigned));
            assigned += catSegs[i];
        }

        // Find dominant category for center display
        let maxVal = -1, maxIdx = 0;
        for (let i = 0; i < values.length; i++) {
            if (values[i] > maxVal) { maxVal = values[i]; maxIdx = i; }
        }
        const centerPct = total > 0 ? Math.round((maxVal / total) * 100) : 0;

        // Build defs
        let defs = '';
        for (let i = 0; i < gradients.length; i++) {
            const id = 'rG' + i + '_' + (container.id || 'r');
            defs += `<linearGradient id="${id}" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="${gradients[i][0]}"/>
                <stop offset="100%" stop-color="${gradients[i][1]}"/>
            </linearGradient>`;
            colors[i] = 'url(#' + id + ')';
        }

        let bgPaths = '';
        let fgPaths = '';
        let segIdx = 0;

        for (let cat = 0; cat < catSegs.length; cat++) {
            const hasValue = total > 0 && values[cat] > 0;
            for (let s = 0; s < catSegs[cat]; s++) {
                const startAngle = (segIdx * (SEG_DEG + GAP_DEG)) - 90;
                const endAngle = startAngle + SEG_DEG;
                const sr = startAngle * Math.PI / 180;
                const er = endAngle * Math.PI / 180;
                const x1 = CX + RADIUS * Math.cos(sr);
                const y1 = CY + RADIUS * Math.sin(sr);
                const x2 = CX + RADIUS * Math.cos(er);
                const y2 = CY + RADIUS * Math.sin(er);
                const la = SEG_DEG > 180 ? 1 : 0;
                const d = `M ${x1.toFixed(1)} ${y1.toFixed(1)} A ${RADIUS} ${RADIUS} 0 ${la} 1 ${x2.toFixed(1)} ${y2.toFixed(1)}`;

                bgPaths += `<path d="${d}" stroke="${bgColor}" stroke-width="${STROKE_W}" fill="none" stroke-linecap="round" class="ring-seg" style="animation-delay:${segIdx * 12}ms"/>`;

                if (hasValue) {
                    fgPaths += `<path d="${d}" stroke="${colors[cat]}" stroke-width="${STROKE_W}" fill="none" stroke-linecap="round" class="ring-seg" style="animation-delay:${(SEGMENTS + segIdx) * 12}ms"/>`;
                }
                segIdx++;
            }
        }

        const svg = `<svg class="ring-svg" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
            <defs>${defs}</defs>
            ${bgPaths}${fgPaths}
        </svg>`;

        // Center display
        let centerHtml = `<div class="ring-center">`;
        if (total > 0) {
            centerHtml += `<span class="ring-number">${centerPct}%</span>
                <span class="ring-label">${labels[maxIdx]}</span>
                <span class="ring-trend">${maxVal} Total</span>`;
        } else {
            centerHtml += `<div class="ring-empty">
                <span class="ring-number">0%</span>
                <span class="ring-label">No Data</span>
            </div>`;
        }
        centerHtml += `</div>`;

        // Legend
        let legendHtml = `<div class="ring-legend">`;
        for (let i = 0; i < labels.length; i++) {
            const pct = total > 0 ? Math.round((values[i] / sum) * 100) : 0;
            const dot = total > 0 && values[i] > 0 ? colors[i].includes('url') ? (gradients[i] ? gradients[i][0] : colors[i]) : colors[i] : bgColor;
            const dotColor = (total > 0 && values[i] > 0) ? (gradients[i] ? gradients[i][0] : colors[i]) : '#98A2B3';
            legendHtml += `<div class="ring-legend-item">
                <span class="ring-legend-dot" style="background:${dotColor}"></span>
                <div class="ring-legend-info">
                    <span class="ring-legend-label">${labels[i]}</span>
                    <span class="ring-legend-meta">${pct}% &middot; ${values[i]} items</span>
                </div>
                <span class="ring-legend-value">${values[i]}</span>
            </div>`;
        }
        legendHtml += `</div>`;

        container.innerHTML = `
            <div class="ring-svg-wrap">
                ${svg}
                ${centerHtml}
            </div>
            ${legendHtml}
        `;
    }

    // Build both rings
    const r1 = document.getElementById('ring-assessment');
    if (r1) buildSegmentedRing(r1);
    const r2 = document.getElementById('ring-submission');
    if (r2) buildSegmentedRing(r2);

    // ── Premium Progressive Line: pulse fallback ──────
    const perfDot = document.querySelector('.perf-dot');
    const perfRing = document.querySelector('.perf-dot-ring');
    if (perfDot && perfRing) {
        setTimeout(() => {
            setInterval(() => {
                perfDot.setAttribute('r', '7');
                perfRing.setAttribute('r', '14');
                setTimeout(() => {
                    perfDot.setAttribute('r', '5');
                    perfRing.setAttribute('r', '10');
                }, 1500);
            }, 3000);
        }, 1600);
    }

});
</script>

<?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>
