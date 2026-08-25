<?php
$pageTitle = 'Assessment Management';
require_once __DIR__ . '/../../includes/admin_header.php';

$pdo = getDB();
$message = '';

// Handle lifecycle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';
    $testId = (int)($_POST['id'] ?? 0);

    if ($action === 'pause' && $testId) {
        $pdo->prepare("UPDATE tests SET status = 'paused' WHERE id = ? AND status = 'active'")->execute([$testId]);
        $message = 'Assessment paused. Students cannot submit answers while paused.';
    } elseif ($action === 'resume' && $testId) {
        $pdo->prepare("UPDATE tests SET status = 'active' WHERE id = ? AND status = 'paused'")->execute([$testId]);
        $message = 'Assessment resumed. Students can continue.';
    } elseif ($action === 'force_end' && $testId) {
        $pdo->prepare("UPDATE tests SET status = 'completed', end_time = NOW() WHERE id = ? AND (status = 'active' OR status = 'paused')")->execute([$testId]);
        $message = 'Assessment forcefully ended. All submissions have been closed.';
    } elseif ($action === 'extend_time' && $testId && !empty($_POST['extend_minutes'])) {
        $minutes = max(1, min(120, (int)$_POST['extend_minutes']));
        $pdo->prepare("UPDATE submissions SET timer_extended_minutes = COALESCE(timer_extended_minutes, 0) + ? WHERE test_id = ? AND status = 'in_progress'")
            ->execute([$minutes, $testId]);
        $message = "Extended timer by $minutes minutes for all active students.";
    } elseif ($action === 'delete' && $testId) {
        $pdo->prepare("DELETE FROM questions WHERE test_id = ?")->execute([$testId]);
        $pdo->prepare("DELETE FROM submissions WHERE test_id = ?")->execute([$testId]);
        $pdo->prepare("DELETE FROM tests WHERE id = ?")->execute([$testId]);
        $message = 'Assessment and all related data deleted.';
    } elseif ($action === 'unpublish' && $testId) {
        $pdo->prepare("UPDATE tests SET status = 'upcoming' WHERE id = ? AND status = 'active'")->execute([$testId]);
        $message = 'Assessment unpublished. It has been moved back to upcoming.';
    } elseif ($action === 'publish_now' && $testId) {
        // Handle publishing from management page (both upcoming and scheduled)
        $dRow = $pdo->prepare("SELECT duration_minutes, title FROM tests WHERE id = ?");
        $dRow->execute([$testId]);
        $dRow = $dRow->fetch();
        $duration = $dRow ? (int)$dRow['duration_minutes'] : 30;
        $pTitle = $dRow ? $dRow['title'] : '';
        $pdo->prepare("UPDATE tests SET status = 'active', start_time = NOW(), end_time = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ? AND (status = 'upcoming' OR status = 'scheduled')")->execute([$duration, $testId]);
        redirect('/admin/assessment_management.php?tab=live&toast=published&title=' . urlencode($pTitle));
    } elseif ($action === 'cancel_schedule' && $testId) {
        // Move scheduled test back to upcoming
        $pdo->prepare("UPDATE tests SET status = 'upcoming', start_time = NULL, end_time = NULL WHERE id = ? AND status = 'scheduled'")->execute([$testId]);
        $sTitle = '';
        $sRow = $pdo->prepare("SELECT title FROM tests WHERE id = ?");
        $sRow->execute([$testId]);
        $sRow = $sRow->fetch();
        if ($sRow) $sTitle = $sRow['title'];
        redirect('/admin/assessment_management.php?tab=upcoming&toast=cancelled&title=' . urlencode($sTitle));
    } elseif ($action === 'schedule_publish' && $testId) {
        // Schedule a test for future publication
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? '';
        if (!empty($startTime) && !empty($endTime)) {
            try {
                $tz = new DateTimeZone('Asia/Kolkata');
                $startDT = new DateTime($startTime, $tz);
                $endDT = new DateTime($endTime, $tz);
                $now = new DateTime('now', $tz);
                if ($startDT < $now) {
                    $message = 'Start time must be in the future (IST).';
                } elseif ($endDT <= $startDT) {
                    $message = 'End time must be after start time.';
                } else {
                    $sTitle = '';
                    $sRow = $pdo->prepare("SELECT title FROM tests WHERE id = ?");
                    $sRow->execute([$testId]);
                    $sRow = $sRow->fetch();
                    if ($sRow) $sTitle = $sRow['title'];
                    $pdo->prepare("UPDATE tests SET status = 'scheduled', start_time = ?, end_time = ? WHERE id = ? AND (status = 'upcoming' OR status = 'scheduled')")
                        ->execute([$startDT->format('Y-m-d H:i:s'), $endDT->format('Y-m-d H:i:s'), $testId]);
                    redirect('/admin/assessment_management.php?tab=scheduled&toast=scheduled&title=' . urlencode($sTitle));
                }
            } catch (Exception $e) {
                $message = 'Invalid date format.';
            }
        } else {
            $message = 'Start and end times are required.';
        }
    }
}

// Current tab
$activeTab = $_GET['tab'] ?? 'upcoming';
$validTabs = ['upcoming', 'scheduled', 'live', 'paused', 'completed'];
if (!in_array($activeTab, $validTabs)) $activeTab = 'upcoming';

// Build query based on tab
$statusMap = [
    'upcoming'  => "t.status = 'upcoming'",
    'scheduled' => "t.status = 'scheduled'",
    'live'      => "t.status = 'active'",
    'paused'    => "t.status = 'paused'",
    'completed' => "t.status = 'completed'",
];

$whereClause = $statusMap[$activeTab];

$assessments = $pdo->prepare("
    SELECT t.*, b.name AS batch_name, b.section AS batch_section, c.name AS course_name, cl.name AS college_name,
           (SELECT COUNT(*) FROM questions WHERE test_id = t.id) AS question_count,
           (SELECT COUNT(*) FROM submissions WHERE test_id = t.id) AS submission_count,
           (SELECT COUNT(*) FROM submissions WHERE test_id = t.id AND status = 'in_progress') AS active_submissions,
           (SELECT COUNT(DISTINCT s.id) FROM students s
             JOIN test_sections ts ON ts.batch_id = s.batch_id
             WHERE ts.test_id = t.id) AS total_students,
           (SELECT GROUP_CONCAT(DISTINCT b2.section ORDER BY b2.section SEPARATOR ', ')
             FROM test_sections ts2 JOIN batches b2 ON b2.id = ts2.batch_id WHERE ts2.test_id = t.id) AS assigned_sections
    FROM tests t
    JOIN batches b ON b.id = t.batch_id
    JOIN courses c ON c.id = b.course_id
    JOIN colleges cl ON cl.id = c.college_id
    WHERE $whereClause
    ORDER BY t.created_at DESC
");
$assessments->execute();
$assessments = $assessments->fetchAll();

// Tab counts
$tabCounts = [];
foreach ($statusMap as $tab => $condition) {
    $tabCounts[$tab] = $pdo->query("SELECT COUNT(*) FROM tests t WHERE $condition")->fetchColumn();
}

// For extension modal
$activeLiveTest = null;
if ($activeTab === 'live' && !empty($assessments)) {
    $activeLiveTest = $assessments[0];
}
?>
<div>
    <?php if ($message): ?>
        <div class="alert alert-success">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
            <span><?= h($message) ?></span>
        </div>
    <?php endif; ?>

    <?php
    // Toast notifications from query params (passed via redirect from assessment_studio)
    $toastType = $_GET['toast'] ?? '';
    $toastTitle = $_GET['title'] ?? '';
    if ($toastType): ?>
    <div class="toast-container" id="toastContainer">
        <div class="toast toast-<?= $toastType === 'published' ? 'success' : ($toastType === 'scheduled' ? 'info' : 'success') ?>" id="activeToast">
            <?php if ($toastType === 'published'): ?>
            <svg viewBox="0 0 20 20" fill="currentColor" style="color:var(--green);"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
            <div style="flex:1;">
                <div style="font-weight:600;color:var(--gray-90);">Published Successfully</div>
                <div style="color:var(--gray-50);margin-top:2px;"><?= $toastTitle ? h($toastTitle) . ' is now live and visible to students.' : 'Assessment is now live and visible to students.' ?></div>
            </div>
            <?php elseif ($toastType === 'scheduled'): ?>
            <svg viewBox="0 0 20 20" fill="currentColor" style="color:var(--accent);"><path d="M5.5 2a.5.5 0 0 1 .5.5V3h8v-.5a.5.5 0 0 1 1 0V3h1a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h1v-.5a.5.5 0 0 1 .5-.5zM4 4a1 1 0 0 0-1 1v1h14V5a1 1 0 0 0-1-1H4z"/></svg>
            <div style="flex:1;">
                <div style="font-weight:600;color:var(--gray-90);">Scheduled</div>
                <div style="color:var(--gray-50);margin-top:2px;"><?= $toastTitle ? h($toastTitle) . ' has been scheduled for later publication.' : 'Assessment has been scheduled for later publication.' ?></div>
            </div>
            <?php endif; ?>
            <button onclick="dismissToast()" style="background:none;border:none;cursor:pointer;padding:4px;color:var(--gray-40);flex-shrink:0;">
                <svg viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;"><path d="M4.09 4.09a.5.5 0 0 1 .7 0L10 9.29l5.2-5.2a.5.5 0 0 1 .7.7L10.7 10l5.2 5.2a.5.5 0 0 1-.7.7L10 10.7l-5.2 5.2a.5.5 0 0 1-.7-.7L9.29 10 4.09 4.8a.5.5 0 0 1 0-.7z"/></svg>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="dashboard-header" style="margin-bottom:var(--space-4);">
        <div class="dashboard-header-left">
            <h1>Assessment Management</h1>
            <p class="dashboard-subtitle">Manage the full lifecycle of your assessments — from upcoming drafts to completed exams.</p>
        </div>
    </div>

    <!-- Lifecycle Tabs -->
    <div class="management-tabs">
        <div class="tabs">
            <a href="?tab=upcoming" class="tab <?= $activeTab === 'upcoming' ? 'active' : '' ?>">
                <?= icon('clock', 16) ?>
                Upcoming
                <span class="tab-badge"><?= (int)$tabCounts['upcoming'] ?></span>
            </a>
            <a href="?tab=scheduled" class="tab <?= $activeTab === 'scheduled' ? 'active' : '' ?>">
                <?= icon('calendar', 16) ?>
                Scheduled
                <span class="tab-badge"><?= (int)$tabCounts['scheduled'] ?></span>
            </a>
            <a href="?tab=live" class="tab <?= $activeTab === 'live' ? 'active' : '' ?>">
                <?= icon('play', 16) ?>
                Live
                <span class="tab-badge"><?= (int)$tabCounts['live'] ?></span>
            </a>
            <a href="?tab=paused" class="tab <?= $activeTab === 'paused' ? 'active' : '' ?>">
                <?= icon('pause', 16) ?>
                Paused
                <span class="tab-badge"><?= (int)$tabCounts['paused'] ?></span>
            </a>
            <a href="?tab=completed" class="tab <?= $activeTab === 'completed' ? 'active' : '' ?>">
                <?= icon('check-circle', 16) ?>
                Completed
                <span class="tab-badge"><?= (int)$tabCounts['completed'] ?></span>
            </a>
        </div>

        <div class="tab-content">
            <?php if (empty($assessments)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <?php if ($activeTab === 'upcoming'): ?>
                            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M5.5 2a.5.5 0 0 1 .5.5V3h8v-.5a.5.5 0 0 1 1 0V3h1a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h1v-.5a.5.5 0 0 1 .5-.5zM4 4a1 1 0 0 0-1 1v1h14V5a1 1 0 0 0-1-1H4z"/></svg>
                        <?php elseif ($activeTab === 'live'): ?>
                            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M4 3.5a.5.5 0 0 1 .75-.43l12.5 7a.5.5 0 0 1 0 .86l-12.5 7A.5.5 0 0 1 4 17.5v-14z"/></svg>
                        <?php elseif ($activeTab === 'paused'): ?>
                            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M5 3a1 1 0 0 0-1 1v12a1 1 0 0 0 2 0V4a1 1 0 0 0-1-1zm10 0a1 1 0 0 0-1 1v12a1 1 0 0 0 2 0V4a1 1 0 0 0-1-1z"/></svg>
                        <?php else: ?>
                            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                        <?php endif; ?>
                    </div>
                    <h3>
                        <?php
                        $emptyTitles = [
                            'upcoming' => 'No Upcoming Assessments',
                            'scheduled' => 'No Scheduled Assessments',
                            'live' => 'No Live Assessments',
                            'paused' => 'No Paused Assessments',
                            'completed' => 'No Completed Assessments',
                        ];
                        echo $emptyTitles[$activeTab] ?? 'No Assessments';
                        ?>
                    </h3>
                    <p>
                        <?php
                        $emptyMsgs = [
                            'upcoming' => 'Create a new assessment in the Assessment Studio to get started.',
                            'scheduled' => 'Schedule an assessment from the Test Builder or Assessment Studio.',
                            'live' => 'Publish an upcoming assessment to make it live.',
                            'paused' => 'No assessments are currently paused.',
                            'completed' => 'Completed assessments will appear here.',
                        ];
                        echo $emptyMsgs[$activeTab] ?? '';
                        ?>
                    </p>
                    <?php if ($activeTab === 'upcoming'): ?>
                        <a href="assessment_studio.php" class="btn btn-primary">
                            <?= icon('plus', 16) ?>
                            Create Assessment
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Batch</th>
                                <th>Questions</th>
                                <th>Duration</th>
                                <?php if ($activeTab === 'live' || $activeTab === 'paused'): ?>
                                    <th>Progress</th>
                                <?php elseif ($activeTab === 'completed'): ?>
                                    <th>Submissions</th>
                                <?php elseif ($activeTab === 'scheduled'): ?>
                                    <th>Starts At</th>
                                <?php else: ?>
                                    <th>Scheduled</th>
                                <?php endif; ?>
                                <th class="actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assessments as $a): ?>
                            <tr>
                                <td>
                                    <strong><?= h($a['title']) ?></strong>
                                    <div class="text-muted text-sm"><?= h($a['college_name']) ?> — <?= h($a['course_name']) ?></div>
                                </td>
                                <td class="text-sm">
                                    <?= h($a['batch_name']) ?>
                                    <?php if (!empty($a['assigned_sections'])): ?>
                                        <div class="text-muted text-xs" style="margin-top:2px;">Sections: <?= h($a['assigned_sections']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-active"><?= (int)$a['question_count'] ?></span></td>
                                <td class="text-sm"><?= $a['duration_minutes'] ?> min</td>

                                <?php if ($activeTab === 'live' || $activeTab === 'paused'): ?>
                                    <td>
                                        <div class="text-sm">
                                            <strong><?= (int)$a['active_submissions'] ?></strong> / <?= (int)$a['total_students'] ?> active
                                        </div>
                                        <?php if ((int)$a['total_students'] > 0): ?>
                                            <div class="progress-bar" style="margin-top:4px;width:100px;">
                                                <div class="progress-fill" style="width:<?= min(100, round(((int)$a['active_submissions'] / max(1, (int)$a['total_students'])) * 100)) ?>%;"></div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php elseif ($activeTab === 'completed'): ?>
                                    <td>
                                        <span class="badge badge-success"><?= (int)$a['submission_count'] ?> submitted</span>
                                    </td>
                                <?php elseif ($activeTab === 'scheduled'): ?>
                                    <td class="text-sm">
                                        <?php if ($a['start_time']): ?>
                                            <div style="font-weight:500"><?= date('d M Y, h:i A', strtotime($a['start_time'])) ?></div>
                                            <div class="text-muted" style="font-size:.75rem">IST</div>
                                        <?php else: ?>
                                            <span class="badge badge-pending">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                <?php else: ?>
                                    <td class="text-sm text-muted">
                                        <?php if ($a['start_time']): ?>
                                            <?= date('d M Y', strtotime($a['start_time'])) ?>
                                        <?php else: ?>
                                            <span class="badge badge-pending">Not scheduled</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>

                                <td class="actions">
                                    <!-- Shared actions -->
                                    <a href="assessment_studio.php?edit_test=<?= $a['id'] ?>" class="btn btn-sm btn-ghost" data-tooltip="Edit">
                                        <?= icon('edit', 14) ?>
                                    </a>

                                    <?php if ($activeTab === 'upcoming'): ?>
                                        <button class="btn btn-sm btn-primary" onclick="openPublishModal(<?= $a['id'] ?>, '<?= h($a['title']) ?>')">
                                            <?= icon('play', 14) ?>
                                            Publish
                                        </button>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this assessment? This cannot be undone.')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" data-tooltip="Delete">
                                                <?= icon('trash', 14) ?>
                                            </button>
                                        </form>

                                    <?php elseif ($activeTab === 'scheduled'): ?>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Publish this assessment now? Students will be able to start immediately.')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="publish_now">
                                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                <?= icon('play', 14) ?>
                                                Publish Now
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Cancel this schedule? The assessment will move back to upcoming.')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="cancel_schedule">
                                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-secondary">
                                                <?= icon('x', 14) ?>
                                                Cancel
                                            </button>
                                        </form>

                                    <?php elseif ($activeTab === 'live'): ?>
                                        <a href="live_monitor.php?test_id=<?= $a['id'] ?>" class="btn btn-sm btn-primary">
                                            <?= icon('pulse', 14) ?>
                                            Monitor
                                        </a>
                                        <button class="btn btn-sm btn-warning" onclick="openExtendModal(<?= $a['id'] ?>, '<?= h($a['title']) ?>')">
                                            <?= icon('clock', 14) ?>
                                            Extend
                                        </button>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Pause this assessment? Students will not be able to submit.')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="pause">
                                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-secondary"><?= icon('pause', 14) ?> Pause</button>
                                        </form>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Force end this assessment? All ongoing submissions will be closed.')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="force_end">
                                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><?= icon('stop', 14) ?> End</button>
                                        </form>

                                    <?php elseif ($activeTab === 'paused'): ?>
                                        <form method="POST" style="display:inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="resume">
                                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-success"><?= icon('play', 14) ?> Resume</button>
                                        </form>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Force end this assessment?')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="force_end">
                                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><?= icon('stop', 14) ?> End</button>
                                        </form>

                                    <?php elseif ($activeTab === 'completed'): ?>
                                        <a href="grading.php?test_id=<?= $a['id'] ?>" class="btn btn-sm btn-ghost">
                                            <?= icon('grading', 14) ?>
                                            Grade
                                        </a>
                                        <a href="reports.php?test_id=<?= $a['id'] ?>" class="btn btn-sm btn-ghost">
                                            <?= icon('chart', 14) ?>
                                            Reports
                                        </a>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this assessment and all its data?')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><?= icon('trash', 14) ?></button>
                                        </form>
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
</div>

<!-- Publish Modal -->
<div class="modal-overlay" id="publishModal" style="display:none;">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h3>Publish Assessment</h3>
            <button type="button" class="modal-close" onclick="closeModal('publishModal')">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M4.09 4.09a.5.5 0 0 1 .7 0L10 9.29l5.2-5.2a.5.5 0 0 1 .7.7L10.7 10l5.2 5.2a.5.5 0 0 1-.7.7L10 10.7l-5.2 5.2a.5.5 0 0 1-.7-.7L9.29 10 4.09 4.8a.5.5 0 0 1 0-.7z"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <p class="text-sm text-muted" style="margin-bottom:var(--space-4);" id="publishTitle">Publish assessment</p>
            <div style="display:flex;flex-direction:column;gap:var(--space-3);">
                <form method="POST" id="publishNowForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="publish_now">
                    <input type="hidden" name="id" id="publishTestId" value="">
                    <button type="submit" class="btn btn-primary w-full btn-lg">
                        <?= icon('play', 18) ?>
                        Publish Now — Make Live
                    </button>
                </form>
                <div style="position:relative;text-align:center;">
                    <span style="background:var(--white);padding:0 var(--space-3);color:var(--gray-40);font-size:var(--fs-11);position:relative;z-index:1;">or</span>
                    <hr style="border:none;border-top:1px solid var(--gray-15);margin:-9px auto 0;width:100%;">
                </div>
                <button class="btn btn-secondary w-full" onclick="showScheduleForm()">
                    <?= icon('calendar', 16) ?>
                    Schedule for Later
                </button>
            </div>

            <form method="POST" id="scheduleForm" style="display:none;margin-top:var(--space-4);padding-top:var(--space-4);border-top:1px solid var(--gray-10);">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="schedule_publish">
                <input type="hidden" name="id" id="scheduleTestId" value="">
                <div class="form-group">
                    <label>Start Date & Time</label>
                    <input class="form-input" type="datetime-local" name="start_time" required>
                </div>
                <div class="form-group">
                    <label>End Date & Time</label>
                    <input class="form-input" type="datetime-local" name="end_time" required>
                </div>
                <button type="submit" class="btn btn-primary w-full">Schedule Publication</button>
            </form>
        </div>
    </div>
</div>

<!-- Extend Time Modal -->
<div class="modal-overlay" id="extendModal" style="display:none;">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h3>Extend Time</h3>
            <button type="button" class="modal-close" onclick="closeModal('extendModal')">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M4.09 4.09a.5.5 0 0 1 .7 0L10 9.29l5.2-5.2a.5.5 0 0 1 .7.7L10.7 10l5.2 5.2a.5.5 0 0 1-.7.7L10 10.7l-5.2 5.2a.5.5 0 0 1-.7-.7L9.29 10 4.09 4.8a.5.5 0 0 1 0-.7z"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <p class="text-sm text-muted" style="margin-bottom:var(--space-4);" id="extendTitle">Extend time for all active students</p>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="extend_time">
                <input type="hidden" name="id" id="extendTestId" value="">
                <div class="form-group">
                    <label>Additional Minutes</label>
                    <input class="form-input" type="number" name="extend_minutes" min="1" max="120" value="5" required>
                    <div class="form-hint">Enter between 1 and 120 minutes to add to all active submissions.</div>
                </div>
                <button type="submit" class="btn btn-primary w-full">Extend Time</button>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.style.display = 'flex';
    el.classList.add('open');
}

function closeModal(id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('open');
    el.style.display = 'none';
}

// Close modals on overlay click
document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});

// Publish modal functions
function openPublishModal(id, title) {
    document.getElementById('publishTestId').value = id;
    document.getElementById('scheduleTestId').value = id;
    document.getElementById('publishTitle').textContent = 'Publish "' + title + '" — students will immediately be able to see and start this assessment.';
    var el = document.getElementById('publishModal');
    el.style.display = 'flex';
    el.classList.add('open');
    document.getElementById('scheduleForm').style.display = 'none';
}

function showScheduleForm() {
    document.getElementById('scheduleForm').style.display = 'block';
}

// Extend time modal
function openExtendModal(id, title) {
    document.getElementById('extendTestId').value = id;
    document.getElementById('extendTitle').textContent = 'Extend time for "' + title + '"';
    var el = document.getElementById('extendModal');
    el.style.display = 'flex';
    el.classList.add('open');
}

// Toast auto-dismiss
function dismissToast() {
    var t = document.getElementById('activeToast');
    if (t) { t.style.opacity = '0'; t.style.transform = 'translateX(20px)'; t.style.transition = 'all 0.3s ease'; setTimeout(function(){ var c = document.getElementById('toastContainer'); if (c) c.remove(); }, 300); }
}
(function(){
    var toast = document.getElementById('activeToast');
    if (toast) { setTimeout(dismissToast, 5000); }
})();
</script>

<?php
// Publish/schedule POST handling for this page (from modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    // Already handled at top of file — this block is a no-op placeholder.
    // Publish and schedule actions are processed in the header section above.
}
?>

<?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>