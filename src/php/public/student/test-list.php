<?php
/**
 * Student Test List — iframe content page.
 * Loaded inside a modal on the dashboard when a stat card is clicked.
 * Shows filtered test lists: all, completed, or pending (live + ended).
 *
 * Security: requires student authentication via session.
 * The student_id is ALWAYS derived from the session — never from URL params.
 */
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/icons.php';
startSession();
requireStudent();

$pdo = getDB();
$studentId = $_SESSION['student_id'];

// Whitelist filter parameter
$filter = $_GET['filter'] ?? 'all';
$allowedFilters = ['all', 'completed', 'pending', 'in_progress', 'missed', 'submitted'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

// Reuse the exact same helper the dashboard uses
$tests = getStudentTests($studentId);
$now = time();

// ─── Filter logic ────────────────────────────────────────────
// Definitions MUST match dashboard.php count logic exactly:
//   totalTests   = count($tests)
//   completed    = submission_status === 'evaluated'
//   pending      = submission_status === 'in_progress' OR (status === 'active' AND no submission)

$displayTests = [];
$liveTests = [];
$endedTests = [];

if ($filter === 'all') {
    $displayTests = $tests;
} elseif ($filter === 'completed') {
    $displayTests = array_values(array_filter($tests, fn($t) => $t['submission_status'] === 'evaluated'));
} elseif ($filter === 'pending') {
    // Pending = everything that is NOT completed (matches dashboard $pendingTests)
    foreach ($tests as $t) {
        $sub = $t['submission_status'] ?? null;
        $start = $t['start_time'] ? strtotime($t['start_time']) : null;
        $end = $t['end_time'] ? strtotime($t['end_time']) : null;

        // Already completed — skip
        if ($sub === 'evaluated') continue;

        // Live: in_progress submission OR active test within window with no submission yet
        if ($sub === 'in_progress') {
            $liveTests[] = $t;
        } elseif ($t['status'] === 'active') {
            $startOk = $start === null || $now >= $start;
            $endOk = $end === null || $now <= $end;
            if ($startOk && $endOk && !$sub) {
                $liveTests[] = $t;
            } elseif ($end !== null && $now > $end && !$sub) {
                $endedTests[] = $t;
            } elseif ($start !== null && $now < $start && !$sub) {
                // Test is active but hasn't started yet — treat as upcoming, show in live
                $liveTests[] = $t;
            }
        } elseif ($t['status'] === 'completed') {
            if (!$sub) {
                $endedTests[] = $t;
            }
        } elseif ($t['status'] === 'paused') {
            // Paused with no submission and end time passed → ended
            if (!$sub && $end !== null && $now > $end) {
                $endedTests[] = $t;
            } elseif (!$sub) {
                // Paused but still within window or no end time — show as live (waiting)
                $liveTests[] = $t;
            }
        } elseif ($t['status'] === 'upcoming' || $t['status'] === 'scheduled') {
            // Not yet available — skip from pending view
            continue;
        } else {
            // Any other status with no submission → ended
            if (!$sub) {
                $endedTests[] = $t;
            }
        }
    }
    $liveTests = array_values($liveTests);
    $endedTests = array_values($endedTests);
} elseif ($filter === 'in_progress') {
    $displayTests = array_values(array_filter($tests, fn($t) => $t['submission_status'] === 'in_progress'));
} elseif ($filter === 'submitted') {
    $displayTests = array_values(array_filter($tests, fn($t) => $t['submission_status'] === 'submitted'));
} elseif ($filter === 'missed') {
    // Missed = no submission AND test window has ended or test was stopped by admin
    foreach ($tests as $t) {
        $sub = $t['submission_status'] ?? null;
        if ($sub) continue; // Has any submission — not missed
        if ($t['status'] === 'completed') {
            $displayTests[] = $t;
        } elseif ($t['status'] === 'active') {
            $end = $t['end_time'] ? strtotime($t['end_time']) : null;
            if ($end !== null && $now > $end) {
                $displayTests[] = $t;
            }
        } elseif ($t['status'] === 'paused') {
            $end = $t['end_time'] ? strtotime($t['end_time']) : null;
            if ($end !== null && $now > $end) {
                $displayTests[] = $t;
            }
        }
    }
    $displayTests = array_values($displayTests);
}

// Page titles
$titles = [
    'all'         => 'All Tests',
    'completed'   => 'Completed Tests',
    'pending'     => 'Pending / Active Tests',
    'in_progress' => 'In Progress Tests',
    'submitted'   => 'Submitted Tests',
    'missed'      => 'Missed / Ended Tests',
];
$pageTitle = $titles[$filter] ?? 'Tests';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/student.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20,300,0,0">
    <script src="https://unpkg.com/lucide@0.460.0/dist/umd/lucide.min.js"></script>
    <style>
        /* Minimal overrides for iframe context — no sidebar, no dashboard shell */
        body {
            margin: 0;
            padding: 20px 24px;
            background: transparent;
            font-family: var(--font, 'Inter', sans-serif);
        }
        .tl-page-title {
            font-size: var(--fs-18, 18px);
            font-weight: 700;
            color: var(--gray-90, #1a1d23);
            margin: 0 0 16px 0;
        }
        .tl-section-title {
            font-size: var(--fs-14, 14px);
            font-weight: 700;
            color: var(--gray-70, #374151);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin: 24px 0 12px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tl-section-title .tl-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        .tl-dot-live { background: #22c55e; }
        .tl-dot-ended { background: #ef4444; }
        .tl-table-wrap {
            background: var(--surface-card, #fff);
            border: 1px solid var(--glass-border, rgba(0,0,0,0.06));
            border-radius: var(--radius-lg, 12px);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .tl-table {
            width: 100%;
            border-collapse: collapse;
        }
        .tl-table th {
            text-align: left;
            padding: 10px 16px;
            font-size: var(--fs-12, 12px);
            font-weight: 600;
            color: var(--gray-50, #6b7280);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            background: var(--gray-5, #f9fafb);
            border-bottom: 1px solid var(--glass-border, rgba(0,0,0,0.06));
        }
        .tl-table td {
            padding: 12px 16px;
            font-size: var(--fs-13, 13px);
            color: var(--gray-70, #374151);
            border-bottom: 1px solid var(--gray-10, #f3f4f6);
            vertical-align: middle;
        }
        .tl-table tr:last-child td {
            border-bottom: none;
        }
        .tl-table tr:hover td {
            background: var(--gray-5, #f9fafb);
        }
        .tl-test-name {
            font-weight: 600;
            color: var(--gray-90, #1a1d23);
        }
        .tl-empty {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray-50, #6b7280);
        }
        .tl-empty-icon {
            margin-bottom: 12px;
            color: var(--gray-30, #d1d5db);
        }
        .tl-empty h4 {
            font-size: var(--fs-14, 14px);
            font-weight: 600;
            color: var(--gray-60, #4b5563);
            margin: 0 0 4px 0;
        }
        .tl-empty p {
            font-size: var(--fs-13, 13px);
            color: var(--gray-50, #6b7280);
            margin: 0;
        }
        .tl-score {
            font-weight: 700;
        }
        .tl-score.good { color: #16a34a; }
        .tl-score.ok { color: #d97706; }
        .tl-score.bad { color: #dc2626; }
        .tl-time-range {
            font-size: var(--fs-12, 12px);
            color: var(--gray-50, #6b7280);
        }
        .tl-action-link {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: var(--radius-sm, 6px);
            font-size: var(--fs-12, 12px);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .tl-action-link.primary {
            background: var(--accent, #4f8cff);
            color: #fff;
        }
        .tl-action-link.primary:hover {
            background: #3b7aed;
        }
        .tl-action-link.ghost {
            background: var(--gray-5, #f9fafb);
            color: var(--gray-70, #374151);
            border: 1px solid var(--gray-15, #e5e7eb);
        }
        .tl-action-link.ghost:hover {
            background: var(--gray-10, #f3f4f6);
        }
        @media (max-width: 640px) {
            body { padding: 12px; }
            .tl-table th:nth-child(4),
            .tl-table td:nth-child(4),
            .tl-table th:nth-child(5),
            .tl-table td:nth-child(5) {
                display: none;
            }
        }
    </style>
</head>
<body>
<?= iconSprite() ?>

<?php if ($filter === 'pending'): ?>
    <!-- ─── LIVE Section ────────────────────────────── -->
    <div class="tl-section-title">
        <span class="tl-dot tl-dot-live"></span>
        Live (<?= count($liveTests) ?>)
    </div>
    <?php if (empty($liveTests)): ?>
        <div class="tl-empty">
            <div class="tl-empty-icon"><?= icon('play.circle', 40) ?></div>
            <h4>No Live Tests</h4>
            <p>No tests are currently live or in progress.</p>
        </div>
    <?php else: ?>
        <div class="tl-table-wrap">
            <table class="tl-table">
                <thead>
                    <tr>
                        <th>Test</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Window</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($liveTests as $t):
                        $start = $t['start_time'] ? strtotime($t['start_time']) : null;
                        $end = $t['end_time'] ? strtotime($t['end_time']) : null;
                    ?>
                    <tr>
                        <td><span class="tl-test-name"><?= h($t['title']) ?></span></td>
                        <td><?= (int)$t['duration_minutes'] ?> min</td>
                        <td>
                            <?php if ($t['submission_status'] === 'in_progress'): ?>
                                <span class="badge badge-active">In Progress</span>
                            <?php elseif ($t['status'] === 'active'): ?>
                                <span class="badge badge-active">Live</span>
                            <?php elseif ($t['status'] === 'paused'): ?>
                                <span class="badge badge-pending">Paused</span>
                            <?php else: ?>
                                <span class="badge badge-info">Scheduled</span>
                            <?php endif; ?>
                        </td>
                        <td class="tl-time-range">
                            <?php if ($start && $end): ?>
                                <?= date('M j, g:i A', $start) ?> —<br><?= date('M j, g:i A', $end) ?>
                            <?php elseif ($start): ?>
                                From <?= date('M j, g:i A', $start) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="test.php?test_id=<?= (int)$t['id'] ?>" class="tl-action-link primary">
                                <?= icon('play', 12) ?>
                                <?= $t['submission_status'] === 'in_progress' ? 'Resume' : 'Start' ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- ─── ENDED Section ──────────────────────────── -->
    <div class="tl-section-title">
        <span class="tl-dot tl-dot-ended"></span>
        Ended / Missed (<?= count($endedTests) ?>)
    </div>
    <?php if (empty($endedTests)): ?>
        <div class="tl-empty">
            <div class="tl-empty-icon"><?= icon('checkmark.circle', 40) ?></div>
            <h4>No Missed or Ended Tests</h4>
            <p>You haven't missed any tests.</p>
        </div>
    <?php else: ?>
        <div class="tl-table-wrap">
            <table class="tl-table">
                <thead>
                    <tr>
                        <th>Test</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Ended</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($endedTests as $t):
                        $end = $t['end_time'] ? strtotime($t['end_time']) : null;
                    ?>
                    <tr>
                        <td><span class="tl-test-name"><?= h($t['title']) ?></span></td>
                        <td><?= (int)$t['duration_minutes'] ?> min</td>
                        <td><span class="badge badge-danger">Missed</span></td>
                        <td class="tl-time-range">
                            <?= $end ? date('M j, g:i A', $end) : '—' ?>
                        </td>
                        <td>
                            <span style="font-size:var(--fs-12,12px);color:var(--gray-40,#9ca3af);">Window closed</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

<?php else: ?>
    <!-- ─── ALL / COMPLETED / IN_PROGRESS / SUBMITTED / MISSED ── -->
    <div class="tl-page-title"><?= h($pageTitle) ?></div>

    <?php if (empty($displayTests)): ?>
        <div class="tl-empty">
            <div class="tl-empty-icon"><?= icon('tray', 48) ?></div>
            <h4>
                <?php if ($filter === 'all'): ?>
                    No Tests Assigned
                <?php elseif ($filter === 'completed'): ?>
                    No Completed Tests
                <?php elseif ($filter === 'in_progress'): ?>
                    No In Progress Tests
                <?php elseif ($filter === 'submitted'): ?>
                    No Submitted Tests
                <?php elseif ($filter === 'missed'): ?>
                    No Missed or Ended Tests
                <?php else: ?>
                    No Tests Found
                <?php endif; ?>
            </h4>
            <p>
                <?php if ($filter === 'all'): ?>
                    No tests are assigned to you.
                <?php elseif ($filter === 'completed'): ?>
                    No completed tests yet.
                <?php elseif ($filter === 'in_progress'): ?>
                    No tests are currently in progress.
                <?php elseif ($filter === 'submitted'): ?>
                    No tests have been submitted yet.
                <?php elseif ($filter === 'missed'): ?>
                    No missed or ended tests.
                <?php else: ?>
                    No tests found.
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <div class="tl-table-wrap">
            <table class="tl-table">
                <thead>
                    <tr>
                        <th>Test</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Your Status</th>
                        <th>Score</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($displayTests as $t):
                        $sub = $t['submission_status'] ?? null;
                    ?>
                    <tr>
                        <td><span class="tl-test-name"><?= h($t['title']) ?></span></td>
                        <td><?= (int)$t['duration_minutes'] ?> min</td>
                        <td>
                            <?php
                            $statusClass = 'badge-info';
                            if ($t['status'] === 'active') $statusClass = 'badge-active';
                            elseif ($t['status'] === 'paused') $statusClass = 'badge-pending';
                            elseif ($t['status'] === 'completed') $statusClass = 'badge-success';
                            elseif ($t['status'] === 'scheduled') $statusClass = 'badge-info';
                            ?>
                            <span class="badge <?= $statusClass ?>"><?= ucfirst(h($t['status'])) ?></span>
                        </td>
                        <td>
                            <?php if ($sub === 'evaluated'): ?>
                                <span class="badge badge-success">Evaluated</span>
                            <?php elseif ($sub === 'submitted'): ?>
                                <span class="badge badge-pending">Submitted</span>
                            <?php elseif ($sub === 'in_progress'): ?>
                                <span class="badge badge-active">In Progress</span>
                            <?php else: ?>
                                <span class="badge badge-info">Not Started</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($sub === 'evaluated' && $t['total_marks'] > 0): ?>
                                <?php
                                $score = $t['total_score'] ?? $t['total_marks_obtained'];
                                $pct = round(($score / $t['total_marks']) * 100);
                                $scoreClass = $pct >= 70 ? 'good' : ($pct >= 40 ? 'ok' : 'bad');
                                ?>
                                <span class="tl-score <?= $scoreClass ?>"><?= $pct ?>%</span>
                            <?php elseif ($sub === 'submitted'): ?>
                                <span style="color:var(--gray-40,#9ca3af);font-size:var(--fs-12,12px);">Pending</span>
                            <?php else: ?>
                                <span style="color:var(--gray-40,#9ca3af);">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($filter === 'missed' && !$sub): ?>
                                <span style="font-size:var(--fs-12,12px);color:var(--gray-40,#9ca3af);">Window closed</span>
                            <?php elseif ($sub === 'evaluated'): ?>
                                <a href="test.php?test_id=<?= (int)$t['id'] ?>" class="tl-action-link ghost">
                                    <?= icon('chart', 12) ?> View Result
                                </a>
                            <?php elseif ($sub === 'in_progress'): ?>
                                <a href="test.php?test_id=<?= (int)$t['id'] ?>" class="tl-action-link primary">
                                    <?= icon('play', 12) ?> Resume
                                </a>
                            <?php elseif ($sub === 'submitted'): ?>
                                <span style="font-size:var(--fs-12,12px);color:var(--gray-40,#9ca3af);">Awaiting evaluation</span>
                            <?php elseif ($t['status'] === 'active'): ?>
                                <?php
                                $start = $t['start_time'] ? strtotime($t['start_time']) : null;
                                $end = $t['end_time'] ? strtotime($t['end_time']) : null;
                                $canStart = ($start === null || $now >= $start) && ($end === null || $now <= $end);
                                ?>
                                <?php if ($canStart): ?>
                                    <a href="test.php?test_id=<?= (int)$t['id'] ?>" class="tl-action-link primary">
                                        <?= icon('play', 12) ?> Start Test
                                    </a>
                                <?php else: ?>
                                    <span style="font-size:var(--fs-12,12px);color:var(--gray-40,#9ca3af);">Not available</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="font-size:var(--fs-12,12px);color:var(--gray-40,#9ca3af);">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script>
lucide.createIcons();
</script>
</body>
</html>
