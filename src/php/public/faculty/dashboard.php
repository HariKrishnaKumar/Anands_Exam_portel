<?php
/**
 * Faculty Dashboard — /faculty/dashboard.php
 *
 * READ-ONLY analytics scoped to the signed-in faculty member's college.
 * Nothing here writes to the database: the only form is method="GET"
 * (batch + test picker + semester/year filters).
 *
 * Scope rule (same join used by admin/students.php:130):
 *   students s JOIN batches b ON b.id = s.batch_id
 *             JOIN courses  c ON c.id = b.course_id
 *   WHERE c.college_id = <faculty college>
 */
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
startSession();
requireFaculty();

$collegeId   = (int)($_SESSION['faculty_college_id'] ?? 0);
$collegeName = (string)($_SESSION['faculty_college_name'] ?? '');
$pdo         = getDB();

// ─── Filters (GET only — never mutates state) ─────────────
$batchIdRaw  = trim((string)($_GET['batch_id'] ?? ''));
$semesterRaw = trim((string)($_GET['semester'] ?? ''));
$yearRaw     = trim((string)($_GET['year'] ?? ''));
$testIdRaw   = trim((string)($_GET['test_id'] ?? ''));

$semester = (is_numeric($semesterRaw) && (int)$semesterRaw > 0) ? (int)$semesterRaw : null;
$year     = (is_numeric($yearRaw) && (int)$yearRaw > 0) ? (int)$yearRaw : null;

// ─── 1. Batches that belong to this college ───────────────
// Loaded first: the batch filter scopes both the roster and the test picker,
// so the id has to be validated against this college before anything uses it.
$batchStmt = $pdo->prepare("
    SELECT b.id, b.name, b.section, c.name AS course_name
    FROM batches b
    JOIN courses c ON c.id = b.course_id
    WHERE c.college_id = ?
    ORDER BY c.name ASC, b.name ASC, b.id ASC
");
$batchStmt->execute([$collegeId]);
$batches = $batchStmt->fetchAll();

$selectedBatch = null;
foreach ($batches as $b) {
    if ((int)$b['id'] === (int)$batchIdRaw) {
        $selectedBatch = $b;
        break;
    }
}
$selectedBatchId = $selectedBatch !== null ? (int)$selectedBatch['id'] : null;

// ─── 2. Tests that belong to this college (and batch) ─────
$testSql = "
    SELECT t.id, t.batch_id, t.title, t.status, t.start_time, t.end_time,
           b.name AS batch_name, b.section, c.name AS course_name
    FROM tests t
    JOIN batches b ON b.id = t.batch_id
    JOIN courses  c ON c.id = b.course_id
    WHERE c.college_id = ?";
$testParams = [$collegeId];
if ($selectedBatchId !== null) {
    // A test belongs to exactly one batch, so narrow the picker to match.
    $testSql .= " AND t.batch_id = ?";
    $testParams[] = $selectedBatchId;
}
$testSql .= " ORDER BY COALESCE(t.start_time, t.created_at) DESC, t.id DESC";
$testStmt = $pdo->prepare($testSql);
$testStmt->execute($testParams);
$tests = $testStmt->fetchAll();

$testsCount = count($tests);

// Selected test — must belong to this college, otherwise fall back to none.
$selectedTestId = null;
foreach ($tests as $t) {
    if ((int)$t['id'] === (int)$testIdRaw) {
        $selectedTestId = (int)$t['id'];
        break;
    }
}
$selectedTest = null;
foreach ($tests as $t) {
    if ((int)$t['id'] === $selectedTestId) {
        $selectedTest = $t;
        break;
    }
}

// ─── 3. Student roster (college + optional batch/semester/year) ─
$where = ['c.college_id = ?'];
$params = [$collegeId];
if ($selectedBatchId !== null) { $where[] = 's.batch_id = ?'; $params[] = $selectedBatchId; }
if ($semester !== null) { $where[] = 's.semester = ?';      $params[] = $semester; }
if ($year !== null)     { $where[] = 's.year_of_joining = ?'; $params[] = $year; }

$studentStmt = $pdo->prepare("
    SELECT s.id, s.batch_id, s.name, s.roll_number, s.section, s.semester, s.year_of_joining,
           b.name AS batch_name, b.section AS batch_section, c.name AS course_name
    FROM students s
    JOIN batches b ON b.id = s.batch_id
    JOIN courses  c ON c.id = b.course_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY s.name ASC
");
$studentStmt->execute($params);
$students = $studentStmt->fetchAll();
$totalStudents = count($students);

// Available filter options (college-wide)
$semesters = $pdo->query("
    SELECT DISTINCT s.semester FROM students s
    JOIN batches b ON b.id = s.batch_id
    JOIN courses c ON c.id = b.course_id
    WHERE c.college_id = " . (int)$collegeId . " AND s.semester IS NOT NULL
    ORDER BY s.semester ASC
")->fetchAll(PDO::FETCH_COLUMN);

$years = $pdo->query("
    SELECT DISTINCT s.year_of_joining FROM students s
    JOIN batches b ON b.id = s.batch_id
    JOIN courses c ON c.id = b.course_id
    WHERE c.college_id = " . (int)$collegeId . " AND s.year_of_joining IS NOT NULL
    ORDER BY s.year_of_joining DESC
")->fetchAll(PDO::FETCH_COLUMN);

// ─── 4. Submissions for the selected test (attendance) ────
$subByStudent = [];
if ($selectedTestId !== null) {
    $subStmt = $pdo->prepare("
        SELECT su.student_id, su.status, su.submitted_at,
               COALESCE(su.total_score, su.total_marks_obtained, su.auto_score) AS score
        FROM submissions su
        JOIN students s ON s.id = su.student_id
        JOIN batches  b ON b.id = s.batch_id
        JOIN courses  c ON c.id = b.course_id
        WHERE su.test_id = ? AND c.college_id = ?
    ");
    $subStmt->execute([$selectedTestId, $collegeId]);
    foreach ($subStmt->fetchAll() as $row) {
        $subByStudent[(int)$row['student_id']] = $row;
    }
}

// A test belongs to exactly one batch, so only students in THAT batch are
// eligible to attend. Students in other batches are "—" (not "not attended").
$testBatchId = $selectedTest !== null ? (int)$selectedTest['batch_id'] : null;

$eligible   = 0;
$attended   = 0;
$skipped    = 0; // in roster but in a different batch than the selected test
$scoresForTest = [];

foreach ($students as $s) {
    if ($selectedTestId === null) { continue; }
    if ((int)$s['batch_id'] !== $testBatchId) { $skipped++; continue; }

    $eligible++;
    $sub = $subByStudent[(int)$s['id']] ?? null;
    if ($sub !== null) {
        $attended++;
        if ($sub['score'] !== null) {
            $scoresForTest[] = (float)$sub['score'];
        }
    }
}

$notAttended = max(0, $eligible - $attended);
$attendancePct = $eligible > 0 ? round(($attended / $eligible) * 100, 1) : 0.0;
$avgScoreTest = count($scoresForTest) > 0
    ? round(array_sum($scoresForTest) / count($scoresForTest), 2)
    : 0.0;

// ─── 5. College-wide aggregates ───────────────────────────
$attStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT su.student_id) AS attended_any
    FROM submissions su
    JOIN tests t ON t.id = su.test_id
    JOIN batches b ON b.id = t.batch_id
    JOIN courses  c ON c.id = b.course_id
    WHERE c.college_id = ?
");
$attStmt->execute([$collegeId]);
$attendedAny = (int)$attStmt->fetchColumn();

$avgStmt = $pdo->prepare("
    SELECT AVG(COALESCE(su.total_score, su.total_marks_obtained, su.auto_score)) AS avg_all
    FROM submissions su
    JOIN tests t ON t.id = su.test_id
    JOIN batches b ON b.id = t.batch_id
    JOIN courses  c ON c.id = b.course_id
    WHERE c.college_id = ?
      AND (su.total_score IS NOT NULL OR su.total_marks_obtained IS NOT NULL OR su.auto_score > 0)
");
$avgStmt->execute([$collegeId]);
$avgAllRaw = $avgStmt->fetchColumn();
$avgAll = $avgAllRaw !== null && $avgAllRaw !== false ? round((float)$avgAllRaw, 2) : 0.0;

// Total students in college (unfiltered) for the college-wide attendance %
$totalStudentsAll = (int)$pdo->query("
    SELECT COUNT(*) FROM students s
    JOIN batches b ON b.id = s.batch_id
    JOIN courses c ON c.id = b.course_id
    WHERE c.college_id = " . (int)$collegeId
)->fetchColumn();
$collegeAttendancePct = $totalStudentsAll > 0
    ? round(($attendedAny / $totalStudentsAll) * 100, 1)
    : 0.0;

// ─── 6. Average score per semester (chart) ────────────────
$semStmt = $pdo->prepare("
    SELECT s.semester,
           AVG(COALESCE(su.total_score, su.total_marks_obtained, su.auto_score)) AS avg_score,
           COUNT(DISTINCT s.id) AS students
    FROM students s
    JOIN batches b ON b.id = s.batch_id
    JOIN courses  c ON c.id = b.course_id
    LEFT JOIN submissions su ON su.student_id = s.id
    WHERE c.college_id = ? AND s.semester IS NOT NULL
    GROUP BY s.semester
    ORDER BY s.semester ASC
");
$semStmt->execute([$collegeId]);
$semesterRows = $semStmt->fetchAll();

$semLabels = [];
$semAvg    = [];
foreach ($semesterRows as $row) {
    $semLabels[] = 'Sem ' . (int)$row['semester'];
    $semAvg[]    = $row['avg_score'] !== null ? round((float)$row['avg_score'], 2) : 0;
}

$pageTitle = 'Faculty Dashboard';
require_once __DIR__ . '/../../includes/faculty_header.php';
?>

<div class="dashboard-header" style="margin-bottom:var(--space-4);">
    <div class="dashboard-header-left">
        <h1><?= h($collegeName) ?> — Student Analytics</h1>
        <div class="dashboard-subtitle">
            Signed in as <?= h($_SESSION['faculty_name'] ?? 'Faculty') ?> &middot;
            Read-only view &middot; <?= $testsCount ?> test<?= $testsCount === 1 ? '' : 's' ?> in scope
        </div>
    </div>
    <div class="dashboard-header-right">
        <span class="badge badge-active">Read-only</span>
    </div>
</div>

<!-- ─── Filters (GET) ─────────────────────────────────── -->
<div class="table-card" style="margin-bottom:var(--space-4);">
    <div class="table-card-header"><h3>Filters</h3></div>
    <div class="table-card-body">
        <form method="GET" class="analytics-grid" style="gap:var(--space-3);align-items:end;">
            <div class="form-group" style="margin:0;">
                <label for="batch_id">Batch</label>
                <select class="form-select" id="batch_id" name="batch_id">
                    <option value="">All batches</option>
                    <?php foreach ($batches as $b): ?>
                        <option value="<?= (int)$b['id'] ?>"<?= $selectedBatchId === (int)$b['id'] ? ' selected' : '' ?>>
                            <?= h($b['name']) ?> (<?= h($b['course_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label for="test_id">Attendance test</label>
                <select class="form-select" id="test_id" name="test_id">
                    <option value="">— Select a test —</option>
                    <?php foreach ($tests as $t): ?>
                        <option value="<?= (int)$t['id'] ?>"<?= $selectedTestId === (int)$t['id'] ? ' selected' : '' ?>>
                            <?= h($t['title']) ?> (<?= h($t['course_name']) ?> / <?= h($t['batch_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label for="semester">Semester</label>
                <select class="form-select" id="semester" name="semester">
                    <option value="">All semesters</option>
                    <?php foreach ($semesters as $sm): ?>
                        <option value="<?= (int)$sm ?>"<?= $semester === (int)$sm ? ' selected' : '' ?>>Semester <?= (int)$sm ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label for="year">Year of joining</label>
                <select class="form-select" id="year" name="year">
                    <option value="">All years</option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?= (int)$y ?>"<?= $year === (int)$y ? ' selected' : '' ?>><?= (int)$y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <button type="submit" class="btn btn-primary">Apply</button>
                <a href="<?= BASE_URL ?>/faculty/dashboard.php" class="btn btn-ghost">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- ─── KPI cards ─────────────────────────────────────── -->
<div class="analytics-grid" style="margin-bottom:var(--space-6);">
    <div class="analytics-card">
        <div class="analytics-card-header"><h3>Students in scope</h3></div>
        <div class="analytics-card-body" style="padding:var(--space-4);">
            <div style="font-size:2rem;font-weight:700;" id="kpiStudents"><?= $totalStudents ?></div>
            <div class="text-muted"><?= $selectedBatch !== null ? 'Batch ' . h($selectedBatch['name']) : 'All batches' ?>
                <?= $semester !== null ? ' &middot; Semester ' . (int)$semester : ' &middot; all semesters' ?>
                <?= $year !== null ? ' &middot; joined ' . (int)$year : '' ?></div>
        </div>
    </div>

    <div class="analytics-card">
        <div class="analytics-card-header"><h3>Attendance<?= $selectedTest ? ' — ' . h($selectedTest['title']) : '' ?></h3></div>
        <div class="analytics-card-body" style="padding:var(--space-4);">
            <div style="font-size:2rem;font-weight:700;" id="kpiAttendance">
                <?= $selectedTestId !== null ? $attended . ' / ' . $eligible : '—' ?>
            </div>
            <div class="text-muted">
                <?php if ($selectedTestId === null): ?>
                    Select a test above to see attendance.
                <?php else: ?>
                    <span id="kpiAttended"><?= $attended ?></span> attended &middot;
                    <span id="kpiNotAttended"><?= $notAttended ?></span> not attended
                    &middot; <strong id="kpiAttendancePct"><?= $attendancePct ?>%</strong>
                    <?php if ($skipped > 0): ?>
                        <br><span class="text-muted"><?= $skipped ?> student<?= $skipped === 1 ? '' : 's' ?> in a different batch (not eligible for this test)</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="analytics-card">
        <div class="analytics-card-header"><h3>Average score<?= $selectedTest ? ' — selected test' : '' ?></h3></div>
        <div class="analytics-card-body" style="padding:var(--space-4);">
            <div style="font-size:2rem;font-weight:700;" id="kpiAvgScore">
                <?= $selectedTest ? $avgScoreTest : $avgAll ?>
            </div>
            <div class="text-muted">
                <?= $selectedTest ? 'College-wide average: ' . h((string)$avgAll) : 'Across all tests in this college' ?>
            </div>
        </div>
    </div>

    <div class="analytics-card">
        <div class="analytics-card-header"><h3>Tests in college</h3></div>
        <div class="analytics-card-body" style="padding:var(--space-4);">
            <div style="font-size:2rem;font-weight:700;" id="kpiTests"><?= $testsCount ?></div>
            <div class="text-muted">
                <span id="kpiAttendedAny"><?= $attendedAny ?></span> of <?= $totalStudentsAll ?> students have attended at least one
                (<?= $collegeAttendancePct ?>%)
            </div>
        </div>
    </div>
</div>

<!-- ─── Charts ────────────────────────────────────────── -->
<div class="analytics-grid" style="margin-bottom:var(--space-6);">
    <div class="analytics-card">
        <div class="analytics-card-header"><h3>Attendance split (selected test)</h3></div>
        <div class="analytics-card-body" style="padding:var(--space-4);">
            <div class="chart-wrapper" style="position:relative;height:320px;">
                <canvas id="attendanceChart"></canvas>
            </div>
            <div id="attendanceEmpty" class="text-muted" style="display:none;text-align:center;padding:var(--space-6);">
                Select a test above to see attendance for this college.
            </div>
        </div>
    </div>

    <div class="analytics-card">
        <div class="analytics-card-header"><h3>Average score by semester</h3></div>
        <div class="analytics-card-body" style="padding:var(--space-4);">
            <div class="chart-wrapper" style="position:relative;height:320px;">
                <canvas id="semesterChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ─── Student roster ────────────────────────────────── -->
<div class="table-card" style="margin-bottom:var(--space-6);">
    <div class="table-card-header">
        <h3>Students (<?= $totalStudents ?>)</h3>
    </div>
    <div class="table-card-body">
        <table class="data-table" id="facultyRoster">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Roll Number</th>
                    <th>Course</th>
                    <th>Batch</th>
                    <th>Section</th>
                    <th>Semester</th>
                    <th>Year</th>
                    <th>Attendance</th>
                    <th>Score</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($totalStudents === 0): ?>
                    <tr><td colspan="10" style="text-align:center;padding:var(--space-6);color:var(--gray-50);">
                        No students match these filters for this college.
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($students as $i => $s):
                        $sid  = (int)$s['id'];
                        $sub  = $subByStudent[$sid] ?? null;
                        $seen = $sub !== null;
                    ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td><strong><?= h($s['name']) ?></strong></td>
                        <td><?= h($s['roll_number'] ?: '—') ?></td>
                        <td><?= h($s['course_name']) ?></td>
                        <td><?= h($s['batch_name']) ?><?= $s['batch_section'] ? ' / ' . h($s['batch_section']) : '' ?></td>
                        <td><?= h($s['section'] ?: '—') ?></td>
                        <td><?= $s['semester'] !== null ? (int)$s['semester'] : '<span class="text-muted">Not set</span>' ?></td>
                        <td><?= $s['year_of_joining'] ? (int)$s['year_of_joining'] : '—' ?></td>
                        <td>
                            <?php if ($selectedTestId === null): ?>
                                <span class="text-muted">—</span>
                            <?php elseif ((int)$s['batch_id'] !== $testBatchId): ?>
                                <span class="text-muted" title="Different batch — not eligible for this test">—</span>
                            <?php elseif ($seen): ?>
                                <span class="badge badge-success" id="att-<?= $sid ?>">Attended</span>
                            <?php else: ?>
                                <span class="badge badge-pending" id="att-<?= $sid ?>">Not attended</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($seen && (int)$s['batch_id'] === $testBatchId && $sub['score'] !== null): ?>
                                <strong id="score-<?= $sid ?>"><?= h((string)$sub['score']) ?></strong>
                            <?php else: ?>
                                <span class="text-muted" id="score-<?= $sid ?>">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    'use strict';

    // Safe mount — destroy any prior instance (mirrors admin/reports.php:721)
    function mountChart(canvasId, config) {
        const el = document.getElementById(canvasId);
        if (!el || typeof Chart === 'undefined') return null;
        const existing = Chart.getChart(el);
        if (existing) existing.destroy();
        return new Chart(el, config);
    }

    const baseOptions = { responsive: true, maintainAspectRatio: false, resizeDelay: 200 };

    const hasTest   = <?= json_encode($selectedTestId !== null) ?>;
    const attended  = <?= json_encode($attended) ?>;
    const notAttend = <?= json_encode($notAttended) ?>;
    const semLabels = <?= json_encode(array_values($semLabels)) ?>;
    const semAvg    = <?= json_encode(array_values($semAvg)) ?>;

    const attEmpty = document.getElementById('attendanceEmpty');
    const attWrap  = document.getElementById('attendanceChart');
    if (attWrap) attWrap.parentElement.style.display = hasTest ? '' : 'none';
    if (attEmpty) attEmpty.style.display = hasTest ? 'none' : '';

    if (hasTest) {
        mountChart('attendanceChart', {
            type: 'doughnut',
            data: {
                labels: ['Attended', 'Not attended'],
                datasets: [{
                    data: [attended, notAttend],
                    backgroundColor: ['#0078D4', '#BC2F32'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: Object.assign({}, baseOptions, {
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                const total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                                const pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                                return ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            })
        });
    }

    if (semLabels.length) {
        mountChart('semesterChart', {
            type: 'bar',
            data: {
                labels: semLabels,
                datasets: [{
                    label: 'Average score',
                    data: semAvg,
                    backgroundColor: semAvg.map(function (v) {
                        return v >= 75 ? '#0B6A0B88' : v >= 50 ? '#8A6D0088' : '#BC2F3288';
                    }),
                    borderColor: semAvg.map(function (v) {
                        return v >= 75 ? '#0B6A0B' : v >= 50 ? '#8A6D00' : '#BC2F32';
                    }),
                    borderWidth: 1
                }]
            },
            options: Object.assign({}, baseOptions, {
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, suggestedMax: 100 } }
            })
        });
    }
})();
</script>

<?php require_once __DIR__ . '/../../includes/faculty_footer.php'; ?>
