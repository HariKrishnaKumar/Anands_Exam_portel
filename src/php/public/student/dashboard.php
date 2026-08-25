<?php
$pageTitle = 'Student Dashboard';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/icons.php';
startSession();
requireStudent();

$pdo = getDB();
$studentId = $_SESSION['student_id'];

// Get student info with college details
$stmt = $pdo->prepare("SELECT s.*, b.name AS batch_name, c.name AS course_name, cl.name AS college_name, cl.logo AS college_logo FROM students s JOIN batches b ON b.id = s.batch_id JOIN courses c ON c.id = b.course_id JOIN colleges cl ON cl.id = c.college_id WHERE s.id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

// Get tests for this student's batch
$tests = getStudentTests($studentId);

// Stats
$totalTests = count($tests);
$completedTests = count(array_filter($tests, fn($t) => $t['submission_status'] === 'evaluated'));
$pendingTests = count(array_filter($tests, fn($t) => $t['submission_status'] === 'in_progress' || ($t['status'] === 'active' && !$t['submission_status'])));

// Date helpers
$today = new DateTime();
$dayName = $today->format('l');
$formattedDate = $today->format('F j, Y');
$firstName = explode(' ', $student['name'])[0];
$currentPage = 'dashboard';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> | Test Platform</title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/student.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20,300,0,0">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
<?= iconSprite() ?>
<?php include __DIR__ . '/../../includes/student_header.php'; ?>
                <!-- Welcome Section -->
                <div class="welcome-section">
                    <div class="welcome-text">
                        <h1 class="welcome-heading">Welcome back, <?= h($firstName) ?></h1>
                        <p class="welcome-subtitle"><?= h($student['course_name']) ?></p>
                        <p class="welcome-batch">Batch <?= h($student['batch_name']) ?><?= !empty($student['section']) ? ' — Section ' . h($student['section']) : '' ?></p>
                    </div>
                    <!-- Date Card -->
                    <div class="date-card">
                        <div class="date-card-icon">
                            <?= icon('calendar.circle.fill', 24) ?>
                        </div>
                        <div class="date-card-info">
                            <div class="date-card-date"><?= h($formattedDate) ?></div>
                            <div class="date-card-day"><?= h($dayName) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Stat Cards -->
                <!-- KPI Cards — Apple SF Symbols naming, Lucide rendered, Material fallback -->
                <div class="stats-row">
                    <div class="stat-card-gradient stat-card-total">
                        <div class="stat-card-icon">
                            <?= icon('doc.text.fill', 24) ?>
                        </div>
                        <div class="stat-card-value"><?= $totalTests ?></div>
                        <div class="stat-card-label">Total Tests</div>
                        <div class="stat-card-desc">All assigned assessments</div>
                        <div class="stat-card-arrow"><?= icon('arrow.right.circle.fill', 14) ?></div>
                    </div>
                    <div class="stat-card-gradient stat-card-completed">
                        <div class="stat-card-icon">
                            <?= icon('checkmark.circle.fill', 24) ?>
                        </div>
                        <div class="stat-card-value"><?= $completedTests ?></div>
                        <div class="stat-card-label">Completed</div>
                        <div class="stat-card-desc">Evaluated submissions</div>
                        <div class="stat-card-arrow"><?= icon('arrow.right.circle.fill', 14) ?></div>
                    </div>
                    <div class="stat-card-gradient stat-card-pending">
                        <div class="stat-card-icon">
                            <?= icon('clock.badge.exclamationmark.fill', 24) ?>
                        </div>
                        <div class="stat-card-value"><?= $pendingTests ?></div>
                        <div class="stat-card-label">Pending / Active</div>
                        <div class="stat-card-desc">In progress or not started</div>
                        <div class="stat-card-arrow"><?= icon('arrow.right.circle.fill', 14) ?></div>
                    </div>
                </div>

                <?= flashMessage() ?>

                <!-- Your Tests Section -->
                <div class="tests-section">
                    <div class="tests-section-header">
                        <h2 class="tests-section-title">Your Tests</h2>
                        <div class="tests-view-toggle">
                            <button class="toggle-btn active" data-view="table" onclick="setView('table')">
                                <?= icon('list', 16) ?>
                                Table View
                            </button>
                            <button class="toggle-btn" data-view="card" onclick="setView('card')">
                                <?= icon('grid', 16) ?>
                                Card View
                            </button>
                        </div>
                    </div>

                    <?php if (empty($tests)): ?>
                        <div class="card-flat">
                            <div class="card-body">
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <?= icon('tray.fill', 56) ?>
                                    </div>
                                    <h3>No Tests Yet</h3>
                                    <p>No additional assessments available.</p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Table View -->
                        <div class="tests-table-view" id="tableView">
                            <div class="table-wrapper">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>
                                                <div class="th-icon"><?= icon('doc.text.fill', 14) ?></div>
                                                Assessment
                                            </th>
                                            <th>
                                                <div class="th-icon"><?= icon('timer', 14) ?></div>
                                                Duration
                                            </th>
                                            <th>
                                                <div class="th-icon"><?= icon('circle.fill', 14) ?></div>
                                                Status
                                            </th>
                                            <th>
                                                <div class="th-icon"><?= icon('progress.indicator', 14) ?></div>
                                                Your Status
                                            </th>
                                            <th class="actions">
                                                <div class="th-icon"><?= icon('arrow.right.circle.fill', 14) ?></div>
                                                Action
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tests as $t): ?>
                                        <?php
                                            $now = time();
                                            $start = $t['start_time'] ? strtotime($t['start_time']) : null;
                                            $end = $t['end_time'] ? strtotime($t['end_time']) : null;

                                            // Determine if test is joinable based on status and time windows
                                            $canStart = false;

                                            if ($t['submission_status'] === 'in_progress') {
                                                $canStart = true; // Already started
                                            } elseif ($t['status'] === 'active') {
                                                $startOk = $start === null || $now >= $start;
                                                $endOk = $end === null || $now <= $end;
                                                $canStart = $startOk && $endOk;
                                            }

                                            if ($t['submission_status'] === 'evaluated') {
                                                $actionBtn = '<a href="test.php?test_id=' . $t['id'] . '" class="btn btn-sm btn-ghost">' . icon('chart', 14) . ' View Result</a>';
                                            } elseif ($t['submission_status'] === 'submitted') {
                                                $isPendingReview = ($t['evaluation_status'] ?? '') === 'pending_manual_review';
                                                $actionBtn = $isPendingReview
                                                    ? '<span class="badge badge-pending">' . icon('clock', 12) . ' Under Evaluation</span>'
                                                    : '<span class="badge badge-pending">' . icon('clock', 12) . ' Submitted</span>';
                                            } elseif ($t['submission_status'] === 'in_progress' && $t['status'] !== 'completed') {
                                                $actionBtn = '<a href="test.php?test_id=' . $t['id'] . '" class="btn btn-sm btn-primary">' . icon('play', 14) . ' ' . ($t['status'] === 'paused' ? 'Resume (Paused)' : 'Resume') . '</a>';
                                            } elseif ($t['status'] === 'active' && $canStart) {
                                                $actionBtn = '<a href="test.php?test_id=' . $t['id'] . '" class="btn btn-sm btn-primary">' . icon('play', 14) . ' Start Test</a>';
                                            } elseif ($t['status'] === 'paused') {
                                                $actionBtn = '<span class="badge badge-pending">Paused by Admin</span>';
                                            } elseif ($t['status'] === 'upcoming') {
                                                $actionBtn = '<span class="badge badge-pending">Upcoming</span>';
                                            } elseif ($t['status'] === 'scheduled') {
                                                if ($start !== null && $now < $start) {
                                                    $mins = ceil(($start - $now) / 60);
                                                    $hours = floor($mins / 60);
                                                    $remainMins = $mins % 60;
                                                    $timeStr = $hours > 0 ? "{$hours}h {$remainMins}m" : "{$remainMins} min";
                                                    $actionBtn = '<span class="badge badge-info">' . icon('calendar', 12) . ' Starts in ' . $timeStr . '</span>';
                                                } else {
                                                    $actionBtn = '<span class="badge badge-pending">Scheduled</span>';
                                                }
                                            } elseif ($t['status'] === 'active' && !$canStart) {
                                                if ($start !== null && $now < $start) {
                                                    $mins = ceil(($start - $now) / 60);
                                                    $actionBtn = '<span class="badge badge-pending">Starts in ' . $mins . ' min</span>';
                                                } else {
                                                    $actionBtn = '<span class="badge badge-danger">Expired</span>';
                                                }
                                            } else {
                                                $actionBtn = '<span class="badge badge-pending">Missed</span>';
                                            }

            $statusClass = 'badge-info';
            if ($t['status'] === 'active') $statusClass = 'badge-active';
            elseif ($t['status'] === 'paused') $statusClass = 'badge-pending';
            elseif ($t['status'] === 'completed') $statusClass = 'badge-success';
            elseif ($t['status'] === 'scheduled') $statusClass = 'badge-info';

                                            $submissionLabel = '';
                                            if ($t['submission_status'] === 'evaluated') {
                                                $submissionLabel = 'badge-success';
                                                $submissionText = $t['total_marks_obtained'] !== null && $t['total_marks'] > 0
                                                    ? round(($t['total_marks_obtained'] / $t['total_marks']) * 100) . '%'
                                                    : 'Evaluated';
                                            } elseif ($t['submission_status'] === 'submitted') {
                                                $submissionLabel = 'badge-pending';
                                                $submissionText = ($t['evaluation_status'] ?? '') === 'pending_manual_review' ? 'Under Evaluation' : 'Submitted';
                                            } elseif ($t['submission_status'] === 'in_progress') {
                                                $submissionLabel = 'badge-active';
                                                $submissionText = 'In Progress';
                                            } else {
                                                $submissionLabel = 'badge-info';
                                                $submissionText = 'Not Started';
                                            }
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="test-name-cell">
                                                    <div class="test-icon"><?= icon('doc.text.fill', 18) ?></div>
                                                    <div>
                                                        <div class="test-name"><?= h($t['title']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="duration-cell"><?= $t['duration_minutes'] ?> min</td>
                                            <td>
                                                <span class="badge <?= $statusClass ?>"><?= ucfirst($t['status']) ?></span>
                                            </td>
                                            <td>
                                                <span class="badge <?= $submissionLabel ?>"><?= $submissionText ?></span>
                                            </td>
                                            <td class="actions">
                                                <div class="action-cell">
                                                    <?= $actionBtn ?>
                                                    <div class="overflow-menu" onclick="toggleOverflow(this)">
                                                        <button class="btn btn-sm btn-ghost overflow-trigger" aria-label="More actions">
                                                            <?= icon('more-vertical', 14) ?>
                                                        </button>
                                                        <div class="overflow-dropdown">
                                                            <a href="test.php?test_id=<?= $t['id'] ?>" class="overflow-item"><?= icon('eye', 14) ?> View Details</a>
                                                            <a href="#" class="overflow-item"><?= icon('clock', 14) ?> View Schedule</a>
                                                            <?php if ($t['submission_status'] === 'evaluated'): ?>
                                                                <a href="#" class="overflow-item"><?= icon('download', 14) ?> Download Result</a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Card View -->
                        <div class="tests-card-view hidden" id="cardView">
                            <div class="card-grid">
                                <?php foreach ($tests as $t):
                                    $now = time();
                                    $start = $t['start_time'] ? strtotime($t['start_time']) : null;
                                    $end = $t['end_time'] ? strtotime($t['end_time']) : null;
                                    $canStart = false;
                                    if ($t['submission_status'] === 'in_progress') {
                                        $canStart = true;
                                    } elseif ($t['status'] === 'active') {
                                        $startOk = $start === null || $now >= $start;
                                        $endOk = $end === null || $now <= $end;
                                        $canStart = $startOk && $endOk;
                                    }
                                    if ($t['submission_status'] === 'evaluated') {
                                        $actionBtn = '<a href="test.php?test_id=' . $t['id'] . '" class="btn btn-sm btn-ghost">' . icon('chart', 14) . ' View Result</a>';
                                    } elseif ($t['submission_status'] === 'submitted') {
                                        $isPendingReview = ($t['evaluation_status'] ?? '') === 'pending_manual_review';
                                        $actionBtn = $isPendingReview
                                            ? '<span class="badge badge-pending">' . icon('clock', 12) . ' Under Evaluation</span>'
                                            : '<span class="badge badge-pending">' . icon('clock', 12) . ' Submitted</span>';
                                    } elseif ($t['submission_status'] === 'in_progress' && $t['status'] !== 'completed') {
                                        $actionBtn = '<a href="test.php?test_id=' . $t['id'] . '" class="btn btn-sm btn-primary">' . icon('play', 14) . ' ' . ($t['status'] === 'paused' ? 'Resume (Paused)' : 'Resume') . '</a>';
                                    } elseif ($t['status'] === 'active' && $canStart) {
                                        $actionBtn = '<a href="test.php?test_id=' . $t['id'] . '" class="btn btn-sm btn-primary">' . icon('play', 14) . ' Start Test</a>';
                                    } elseif ($t['status'] === 'paused') {
                                        $actionBtn = '<span class="badge badge-pending">Paused by Admin</span>';
                                    } elseif ($t['status'] === 'upcoming') {
                                        $actionBtn = '<span class="badge badge-pending">Upcoming</span>';
                                    } elseif ($t['status'] === 'scheduled') {
                                        if ($start !== null && $now < $start) {
                                            $mins = ceil(($start - $now) / 60);
                                            $hours = floor($mins / 60);
                                            $remainMins = $mins % 60;
                                            $timeStr = $hours > 0 ? "{$hours}h {$remainMins}m" : "{$remainMins} min";
                                            $actionBtn = '<span class="badge badge-info">' . icon('calendar', 12) . ' Starts in ' . $timeStr . '</span>';
                                        } else {
                                            $actionBtn = '<span class="badge badge-pending">Scheduled</span>';
                                        }
                                    } elseif ($t['status'] === 'active' && !$canStart) {
                                        if ($start !== null && $now < $start) {
                                            $mins = ceil(($start - $now) / 60);
                                            $actionBtn = '<span class="badge badge-pending">Starts in ' . $mins . ' min</span>';
                                        } else {
                                            $actionBtn = '<span class="badge badge-danger">Expired</span>';
                                        }
                                    } else {
                                        $actionBtn = '<span class="badge badge-pending">Missed</span>';
                                    }
                                    $statusClass = 'badge-info';
                                    if ($t['status'] === 'active') $statusClass = 'badge-active';
                                    elseif ($t['status'] === 'paused') $statusClass = 'badge-pending';
                                    elseif ($t['status'] === 'completed') $statusClass = 'badge-success';
                                    elseif ($t['status'] === 'scheduled') $statusClass = 'badge-info';
                                ?>
                                <div class="test-card">
                                    <div class="test-card-top">
                                        <div class="test-card-icon">
                                            <?= icon('doc.text.fill', 24) ?>
                                        </div>
                                        <span class="badge <?= $statusClass ?>"><?= ucfirst($t['status']) ?></span>
                                    </div>
                                    <h3 class="test-card-title"><?= h($t['title']) ?></h3>
                                    <div class="test-card-meta">
                                        <span><?= icon('clock', 13) ?> <?= $t['duration_minutes'] ?> min</span>
                                    </div>
                                    <div class="test-card-footer">
                                        <?= $actionBtn ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Bottom Information Card -->
                <div class="info-card">
                    <div class="info-card-icon">
                        <?= icon('info', 20) ?>
                    </div>
                    <div class="info-card-text">
                        You can review completed assessments or wait for newly published assessments.
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include __DIR__ . '/../../includes/student_footer.php'; ?>

<script>
// Toggle sidebar on mobile
function toggleSidebar(forceState) {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const isOpen = forceState !== undefined ? forceState : !sidebar.classList.contains('open');
    sidebar.classList.toggle('open', isOpen);
    overlay.classList.toggle('show', isOpen);
    document.body.classList.toggle('sidebar-open', isOpen);
    // ARIA
    sidebar.setAttribute('aria-hidden', !isOpen);
}
function closeSidebar() { toggleSidebar(false); }

// Close sidebar on Escape / overlay click
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeSidebar();
});

// Focus trap when sidebar open
document.addEventListener('keydown', function(e) {
    if (e.key !== 'Tab') return;
    const sidebar = document.getElementById('sidebar');
    if (!sidebar || !sidebar.classList.contains('open')) return;
    const f = sidebar.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])');
    if (!f.length) return;
    if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length-1].focus(); }
    else if (!e.shiftKey && document.activeElement === f[f.length-1]) { e.preventDefault(); f[0].focus(); }
});

// View toggle
function setView(view) {
    document.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
    document.querySelector(`.toggle-btn[data-view="${view}"]`).classList.add('active');
    if (view === 'table') {
        document.getElementById('tableView').classList.remove('hidden');
        document.getElementById('cardView').classList.add('hidden');
    } else {
        document.getElementById('tableView').classList.add('hidden');
        document.getElementById('cardView').classList.remove('hidden');
    }
}

// Overflow menu toggle
function toggleOverflow(el) {
    const wasOpen = el.classList.contains('open');
    document.querySelectorAll('.overflow-menu.open').forEach(m => m.classList.remove('open'));
    if (!wasOpen) el.classList.add('open');
}

// Close overflow on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('.overflow-menu')) {
        document.querySelectorAll('.overflow-menu.open').forEach(m => m.classList.remove('open'));
    }
});

// Theme switcher
function toggleTheme() {
    const html = document.documentElement;
    const isDark = html.getAttribute('data-theme') === 'dark';
    const newTheme = isDark ? 'light' : 'dark';
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    updateThemeUI(newTheme);
}
function updateThemeUI(theme) {
    const label = document.getElementById('themeLabel');
    if (label) label.textContent = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
    document.querySelectorAll('.theme-icon').forEach(el => {
        el.textContent = theme === 'dark' ? 'light_mode' : 'dark_mode';
    });
}
// Restore saved theme
(function() {
    const saved = localStorage.getItem('theme');
    if (saved) {
        document.documentElement.setAttribute('data-theme', saved);
        updateThemeUI(saved);
    }
})();

// Initialize Lucide icons (script loaded synchronously in <head>)
lucide.createIcons();
</script>
</body>
</html>
