<?php
$pageTitle = 'Live Monitor';
require_once __DIR__ . '/../../includes/admin_header.php';

$pdo = getDB();
$testId = (int)($_GET['test_id'] ?? 0);

if (!$testId) {
    // If no test specified, show the active test selector
    $activeTests = $pdo->query("
        SELECT t.id, t.title, b.name AS batch_name, c.name AS course_name
        FROM tests t
        JOIN batches b ON b.id = t.batch_id
        JOIN courses c ON c.id = b.course_id
        WHERE t.status = 'active'
        ORDER BY t.created_at DESC
    ")->fetchAll();
    ?>
    <div style="max-width:600px;margin:0 auto;">
        <div style="margin-bottom:var(--space-4);">
            <h1 style="font-size:var(--fs-24);font-weight:600;margin-bottom:var(--space-1);">Live Monitor</h1>
            <p class="text-muted" style="font-size:var(--fs-14);">Select a live assessment to monitor student activity in real time.</p>
        </div>

        <?php if (empty($activeTests)): ?>
            <div class="panel">
                <div class="panel-body">
                    <div class="empty-state">
                        <div class="empty-icon">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M4 3.5a.5.5 0 0 1 .75-.43l12.5 7a.5.5 0 0 1 0 .86l-12.5 7A.5.5 0 0 1 4 17.5v-14z"/></svg>
                        </div>
                        <h3>No Live Assessments</h3>
                        <p>There are no assessments currently running. Publish an assessment to start monitoring.</p>
                        <a href="assessment_management.php?tab=upcoming" class="btn btn-primary">
                            <?= icon('arrow-right', 16) ?>
                            Go to Upcoming Assessments
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="panel">
                <div class="panel-header">
                    <h2>Select Assessment</h2>
                </div>
                <div class="panel-body">
                    <div style="display:flex;flex-direction:column;gap:var(--space-3);">
                        <?php foreach ($activeTests as $t): ?>
                            <a href="live_monitor.php?test_id=<?= $t['id'] ?>"
                               style="display:flex;align-items:center;justify-content:space-between;padding:var(--space-4) var(--space-5);border:1px solid var(--gray-15);border-radius:var(--radius-lg);text-decoration:none;color:inherit;transition:all var(--ease-fast);">
                                <div>
                                    <div style="font-weight:600;color:var(--gray-90);"><?= h($t['title']) ?></div>
                                    <div class="text-sm text-muted"><?= h($t['course_name']) ?> — <?= h($t['batch_name']) ?></div>
                                </div>
                                <span class="live-status" style="display:flex;align-items:center;gap:var(--space-2);font-size:var(--fs-12);color:var(--green);font-weight:500;">
                                    <span style="width:6px;height:6px;background:var(--green);border-radius:50%;animation:pulse 2s infinite;"></span>
                                    LIVE
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
    require_once __DIR__ . '/../../includes/admin_footer.php';
    exit;
}

// Fetch test details
$testStmt = $pdo->prepare("
    SELECT t.*, b.name AS batch_name, c.name AS course_name, cl.name AS college_name
    FROM tests t
    JOIN batches b ON b.id = t.batch_id
    JOIN courses c ON c.id = b.course_id
    JOIN colleges cl ON cl.id = c.college_id
    WHERE t.id = ?
");
$testStmt->execute([$testId]);
$test = $testStmt->fetch();

if (!$test) {
    echo '<div class="panel"><div class="panel-body"><div class="empty-state"><h3>Assessment Not Found</h3><p>The requested assessment does not exist.</p><a href="live_monitor.php" class="btn btn-primary">Back to Live Monitor</a></div></div></div>';
    require_once __DIR__ . '/../../includes/admin_footer.php';
    exit;
}

// Stats
$totalStudents = $pdo->prepare("SELECT COUNT(*) FROM students WHERE batch_id = ?");
$totalStudents->execute([$test['batch_id']]);
$totalStudents = $totalStudents->fetchColumn();

$submittedCount = $pdo->prepare("SELECT COUNT(*) FROM submissions WHERE test_id = ? AND status = 'submitted'");
$submittedCount->execute([$testId]);
$submittedCount = $submittedCount->fetchColumn();

$inProgressCount = $pdo->prepare("SELECT COUNT(*) FROM submissions WHERE test_id = ? AND status = 'in_progress'");
$inProgressCount->execute([$testId]);
$inProgressCount = $inProgressCount->fetchColumn();

$notStartedCount = $totalStudents - $submittedCount - $inProgressCount;

// Average score so far
$avgScore = $pdo->prepare("SELECT COALESCE(AVG(total_marks_obtained), 0) FROM submissions WHERE test_id = ? AND status = 'submitted'");
$avgScore->execute([$testId]);
$avgScore = round($avgScore->fetchColumn(), 1);

// Total marks
$totalMarks = $pdo->prepare("SELECT COALESCE(SUM(marks), 0) FROM questions WHERE test_id = ?");
$totalMarks->execute([$testId]);
$totalMarks = $totalMarks->fetchColumn();

// Active students with details
$activeStudents = $pdo->prepare("
    SELECT s.name, s.email, s.roll_number, sub.started_at, sub.timer_extended_minutes,
           sub.total_marks_obtained, sub.total_marks, sub.status, sub.submitted_at,
           TIMESTAMPDIFF(SECOND, sub.started_at, NOW()) AS elapsed_seconds
    FROM submissions sub
    JOIN students s ON s.id = sub.student_id
    WHERE sub.test_id = ? AND sub.status = 'in_progress'
    ORDER BY sub.started_at DESC
");
$activeStudents->execute([$testId]);
$activeStudents = $activeStudents->fetchAll();

// Completed students
$completedStudents = $pdo->prepare("
    SELECT s.name, s.email, s.roll_number, sub.started_at, sub.submitted_at,
           sub.total_marks_obtained, sub.total_marks, sub.status
    FROM submissions sub
    JOIN students s ON s.id = sub.student_id
    WHERE sub.test_id = ? AND sub.status = 'submitted'
    ORDER BY sub.submitted_at DESC
");
$completedStudents->execute([$testId]);
$completedStudents = $completedStudents->fetchAll();
?>
<!-- live-dot + monitor-refresh styles moved to admin.css -->

<div>
    <div class="live-monitor-header">
        <div>
            <div style="display:flex;align-items:center;gap:var(--space-3);margin-bottom:var(--space-1);">
                <a href="live_monitor.php" class="btn btn-sm btn-ghost" data-tooltip="Back to selection">
                    <?= icon('arrow-left', 14) ?>
                </a>
                <h1 style="font-size:var(--fs-20);font-weight:600;"><?= h($test['title']) ?></h1>
                <span class="live-status">
                    <span class="live-dot active"></span>
                    LIVE
                </span>
            </div>
            <div class="text-sm text-muted">
                <?= h($test['college_name']) ?> — <?= h($test['course_name']) ?> — <?= h($test['batch_name']) ?>
                &middot; <?= $test['duration_minutes'] ?> min &middot; <?= $totalMarks ?> total marks
            </div>
        </div>
        <div class="flex-center gap-3">
            <span class="monitor-refresh-indicator" id="refreshIndicator">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 0 0-5.66 13.66.5.5 0 0 0 .7-.7A7 7 0 1 1 16 10a7 7 0 0 1-9.44 6.5.5.5 0 0 0-.38.92A8 8 0 1 0 10 2z"/><path d="M6.5 9.5a.5.5 0 0 0 0 1h4.8l-1.15 1.15a.5.5 0 1 0 .7.7l2-2a.5.5 0 0 0 0-.7l-2-2a.5.5 0 1 0-.7.7L11.3 9.5H6.5z"/></svg>
                Auto-refreshing
            </span>
            <a href="assessment_management.php?tab=live" class="btn btn-sm btn-ghost">
                <?= icon('arrow-left', 14) ?>
                Back to Management
            </a>
        </div>
    </div>

    <!-- Live Stats Grid -->
    <div class="live-monitor-grid">
        <div class="live-stat">
            <div class="live-num" style="color:var(--green);"><?= $inProgressCount ?></div>
            <div class="live-label">In Progress</div>
            <div class="live-sub">Actively taking the assessment</div>
        </div>
        <div class="live-stat">
            <div class="live-num" style="color:var(--accent);"><?= $submittedCount ?></div>
            <div class="live-label">Submitted</div>
            <div class="live-sub">Completed and turned in</div>
        </div>
        <div class="live-stat">
            <div class="live-num" style="color:var(--gray-50);"><?= max(0, $notStartedCount) ?></div>
            <div class="live-label">Not Started</div>
            <div class="live-sub">Haven't begun yet</div>
        </div>
        <div class="live-stat">
            <div class="live-num"><?= $avgScore ?></div>
            <div class="live-label">Average Score</div>
            <div class="live-sub">Across <?= $submittedCount ?> submissions</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-4);">
        <!-- Active Students -->
        <div class="panel">
            <div class="panel-header">
                <h2>Active Students</h2>
                <span class="badge badge-active"><?= count($activeStudents) ?> active</span>
            </div>
            <?php if (empty($activeStudents)): ?>
                <div class="panel-body">
                    <div class="empty-state" style="padding:var(--space-8);">
                        <div class="empty-icon">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a4 4 0 1 0 0 8 4 4 0 0 0 0-8zM7 6a3 3 0 1 1 6 0 3 3 0 0 1-6 0zm-2 5a3 3 0 0 0-3 3v1a1 1 0 0 0 1 1h8.2a5 5 0 0 1-.1-1H3.3A.3.3 0 0 1 3 14.7 2 2 0 0 1 5 13h5.5a5 5 0 0 1 1-1H5zm8 0a4 4 0 1 1 0 8 4 4 0 0 1 0-8zm-1.5 3.5a.5.5 0 0 0 0 1h3a.5.5 0 0 0 0-1h-3z"/></svg>
                        </div>
                        <h3>No Active Students</h3>
                        <p>No students are currently taking this assessment.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Roll</th>
                                <th>Elapsed</th>
                                <th>Extended</th>
                                <th>Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeStudents as $s): 
                                $remaining = ($test['duration_minutes'] * 60) - (int)$s['elapsed_seconds'] + ((int)($s['timer_extended_minutes'] ?? 0) * 60);
                                $remainingMins = max(0, floor($remaining / 60));
                                $elapsedMins = floor((int)$s['elapsed_seconds'] / 60);
                            ?>
                            <tr>
                                <td>
                                    <strong><?= h($s['name']) ?></strong>
                                    <div class="text-sm text-muted"><?= h($s['email']) ?></div>
                                </td>
                                <td class="text-sm"><?= h($s['roll_number']) ?></td>
                                <td class="text-sm"><?= $elapsedMins ?> min</td>
                                <td class="text-sm">
                                    <?php if ((int)($s['timer_extended_minutes'] ?? 0) > 0): ?>
                                        <span class="badge badge-pending">+<?= (int)$s['timer_extended_minutes'] ?> min</span>
                                    <?php else: ?>
                                        <span class="text-muted">--</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-sm">--</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Completed Students -->
        <div class="panel">
            <div class="panel-header">
                <h2>Completed Students</h2>
                <span class="badge badge-success"><?= count($completedStudents) ?> done</span>
            </div>
            <?php if (empty($completedStudents)): ?>
                <div class="panel-body">
                    <div class="empty-state" style="padding:var(--space-8);">
                        <div class="empty-icon">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                        </div>
                        <h3>No Submissions Yet</h3>
                        <p>Students who complete the assessment will appear here.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Roll</th>
                                <th>Submitted</th>
                                <th>Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($completedStudents as $s): ?>
                            <tr>
                                <td>
                                    <strong><?= h($s['name']) ?></strong>
                                    <div class="text-sm text-muted"><?= h($s['email']) ?></div>
                                </td>
                                <td class="text-sm"><?= h($s['roll_number']) ?></td>
                                <td class="text-sm text-muted"><?= timeAgo($s['submitted_at']) ?></td>
                                <td>
                                    <?php if ($s['total_marks_obtained'] !== null): ?>
                                        <span class="badge <?= (float)$s['total_marks_obtained'] >= ((float)$s['total_marks'] * 0.4) ? 'badge-success' : 'badge-danger' ?>">
                                            <?= (float)$s['total_marks_obtained'] ?> / <?= (int)$s['total_marks'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-neutral">Pending review</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary -->
    <div class="panel" style="margin-top:var(--space-4);">
        <div class="panel-header">
            <h2>Overall Progress</h2>
        </div>
        <div class="panel-body">
            <?php $progressPct = $totalStudents > 0 ? round((($submittedCount + $inProgressCount) / $totalStudents) * 100) : 0; ?>
            <div style="display:flex;align-items:center;gap:var(--space-4);flex-wrap:wrap;">
                <div style="flex:1;min-width:200px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:var(--space-2);">
                        <span class="text-sm text-muted">Completion Progress</span>
                        <span class="text-sm font-weight:600;"><?= $progressPct ?>%</span>
                    </div>
                    <div class="progress-bar" style="height:8px;">
                        <div class="progress-fill" style="width:<?= $progressPct ?>%;"></div>
                    </div>
                </div>
                <div style="display:flex;gap:var(--space-5);flex-wrap:wrap;">
                    <div>
                        <div class="text-sm text-muted">Total Students</div>
                        <div style="font-size:var(--fs-20);font-weight:700;"><?= $totalStudents ?></div>
                    </div>
                    <div>
                        <div class="text-sm text-muted">Submitted</div>
                        <div style="font-size:var(--fs-20);font-weight:700;color:var(--accent);"><?= $submittedCount ?></div>
                    </div>
                    <div>
                        <div class="text-sm text-muted">In Progress</div>
                        <div style="font-size:var(--fs-20);font-weight:700;color:var(--green);"><?= $inProgressCount ?></div>
                    </div>
                    <div>
                        <div class="text-sm text-muted">Not Started</div>
                        <div style="font-size:var(--fs-20);font-weight:700;color:var(--gray-50);"><?= max(0, $notStartedCount) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh every 15 seconds
(function() {
    const indicator = document.getElementById('refreshIndicator');
    let isRefreshing = false;

    setInterval(function() {
        if (isRefreshing) return;
        isRefreshing = true;
        indicator.classList.add('refreshing');

        fetch(window.location.href, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function() {
            // Full page reload to get fresh data
            window.location.reload();
        })
        .catch(function() {
            isRefreshing = false;
            indicator.classList.remove('refreshing');
        });
    }, 15000);
})();
</script>

<?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>