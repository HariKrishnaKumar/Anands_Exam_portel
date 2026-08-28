<?php
$pageTitle = 'My Tests';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/icons.php';
startSession();
requireStudent();

$pdo = getDB();
$studentId = $_SESSION['student_id'];

$stmt = $pdo->prepare("SELECT s.*, b.name AS batch_name, c.name AS course_name, cl.name AS college_name, cl.logo AS college_logo FROM students s JOIN batches b ON b.id = s.batch_id JOIN courses c ON c.id = b.course_id JOIN colleges cl ON cl.id = c.college_id WHERE s.id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

$firstName = explode(' ', $student['name'])[0];
$currentPage = 'my_tests';

$tests = getStudentTests($studentId);

$switchStmt = $pdo->prepare("SELECT s.test_id, COUNT(*) AS cnt FROM tab_switch_logs tsl JOIN submissions s ON s.id = tsl.submission_id WHERE s.student_id = ? AND tsl.type = 'switch' GROUP BY s.test_id");
$switchStmt->execute([$studentId]);
$switchRows = $switchStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$tabSwitchCounts = array_map('intval', $switchRows);

$activeTests = [];
$upcomingTests = [];
$completedTests = [];
$missedTests = [];

$now = time();
foreach ($tests as $t) {
    $start = $t['start_time'] ? strtotime($t['start_time']) : null;
    $end = $t['end_time'] ? strtotime($t['end_time']) : null;
    $sub = $t['submission_status'] ?? null;

    if ($sub === 'evaluated' || $sub === 'submitted') {
        $completedTests[] = $t;
    } elseif ($sub === 'in_progress') {
        $activeTests[] = $t;
    } elseif ($t['status'] === 'active') {
        $startOk = $start === null || $now >= $start;
        $endOk = $end === null || $now <= $end;
        if ($startOk && $endOk) {
            $activeTests[] = $t;
        } elseif ($end !== null && $now > $end && !$sub) {
            $missedTests[] = $t;
        } else {
            $upcomingTests[] = $t;
        }
    } elseif ($t['status'] === 'completed') {
        if ($sub) {
            $completedTests[] = $t;
        } else {
            $missedTests[] = $t;
        }
    } elseif ($t['status'] === 'upcoming' || $t['status'] === 'scheduled') {
        $upcomingTests[] = $t;
    } else {
        $missedTests[] = $t;
    }
}

$filter = $_GET['filter'] ?? 'all';
$q = trim($_GET['q'] ?? '');

$displayTests = $tests;
if ($filter === 'active') $displayTests = $activeTests;
elseif ($filter === 'upcoming') $displayTests = $upcomingTests;
elseif ($filter === 'completed') $displayTests = $completedTests;
elseif ($filter === 'missed') $displayTests = $missedTests;

if ($q !== '') {
    $displayTests = array_filter($displayTests, function ($t) use ($q) {
        return stripos($t['title'], $q) !== false;
    });
}
$displayTests = array_values($displayTests);

$counts = [
    'all' => count($tests),
    'active' => count($activeTests),
    'upcoming' => count($upcomingTests),
    'completed' => count($completedTests),
    'missed' => count($missedTests),
];
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
    <script src="https://unpkg.com/lucide@0.460.0/dist/umd/lucide.min.js"></script>
    <style>
        .mt-page-header {
            margin-bottom: var(--space-6);
        }
        .mt-page-title {
            font-size: var(--fs-24);
            font-weight: 700;
            color: var(--gray-100);
            margin: 0;
            line-height: 1.2;
        }
        .mt-page-subtitle {
            font-size: var(--fs-14);
            color: var(--gray-50);
            margin: var(--space-1) 0 0;
        }

        .mt-filter-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--space-4);
            margin-bottom: var(--space-6);
            flex-wrap: wrap;
        }
        .mt-filter-tabs {
            display: flex;
            gap: var(--space-1);
            background: var(--surface);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            padding: 4px;
            box-shadow: var(--shadow-sm);
            overflow-x: auto;
        }
        .mt-filter-tab {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            font-size: var(--fs-13);
            font-weight: 500;
            color: var(--gray-50);
            text-decoration: none;
            border-radius: var(--radius-md);
            transition: all var(--duration-fast) var(--ease-standard);
            white-space: nowrap;
        }
        .mt-filter-tab:hover {
            color: var(--gray-80);
            background: var(--gray-5);
        }
        .mt-filter-tab.active {
            color: var(--accent);
            background: var(--accent-bg);
            font-weight: 600;
        }
        .mt-filter-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            font-size: var(--fs-11);
            font-weight: 600;
            border-radius: var(--radius-full);
            background: var(--gray-10);
            color: var(--gray-60);
        }
        .mt-filter-tab.active .mt-filter-count {
            background: rgba(79, 140, 255, 0.15);
            color: var(--accent);
        }

        .mt-search-box {
            position: relative;
            flex-shrink: 0;
        }
        .mt-search-box input {
            width: 240px;
            padding: 9px 14px 9px 36px;
            font-size: var(--fs-13);
            font-family: var(--font);
            color: var(--gray-80);
            background: var(--surface);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-md);
            outline: none;
            transition: border-color var(--duration-fast), box-shadow var(--duration-fast);
            box-shadow: var(--shadow-sm);
        }
        .mt-search-box input::placeholder {
            color: var(--gray-40);
        }
        .mt-search-box input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }
        .mt-search-icon {
            position: absolute;
            left: 11px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-40);
            pointer-events: none;
        }
        .mt-search-icon svg {
            width: 16px;
            height: 16px;
        }

        .mt-test-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: var(--space-5);
        }

        .mt-test-card {
            background: var(--surface);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            padding: var(--space-5);
            box-shadow: var(--shadow-sm);
            transition: box-shadow var(--duration-normal), transform var(--duration-normal);
            display: flex;
            flex-direction: column;
            gap: var(--space-4);
            position: relative;
            overflow: hidden;
        }
        .mt-test-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            border-radius: var(--radius-lg) 0 0 var(--radius-lg);
        }
        .mt-test-card.status-active::before { background: var(--green); }
        .mt-test-card.status-upcoming::before { background: var(--accent); }
        .mt-test-card.status-completed::before { background: var(--gray-30); }
        .mt-test-card.status-missed::before { background: var(--red); }

        .mt-test-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }

        .mt-card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: var(--space-3);
        }
        .mt-card-title {
            font-size: var(--fs-16);
            font-weight: 650;
            color: var(--gray-100);
            margin: 0;
            line-height: 1.3;
            flex: 1;
            min-width: 0;
        }
        .mt-card-title a {
            color: inherit;
            text-decoration: none;
        }
        .mt-card-title a:hover {
            color: var(--accent);
        }

        .mt-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            font-size: var(--fs-11);
            font-weight: 600;
            border-radius: var(--radius-full);
            white-space: nowrap;
            flex-shrink: 0;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .mt-status-badge.active {
            background: var(--green-bg);
            color: #16a34a;
            border: 1px solid var(--green-border);
        }
        .mt-status-badge.upcoming {
            background: var(--accent-bg);
            color: var(--accent);
            border: 1px solid rgba(79, 140, 255, 0.18);
        }
        .mt-status-badge.completed {
            background: var(--gray-5);
            color: var(--gray-60);
            border: 1px solid var(--gray-15);
        }
        .mt-status-badge.missed {
            background: var(--red-bg);
            color: var(--red);
            border: 1px solid var(--red-border);
        }

        .mt-card-details {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-3) var(--space-5);
        }
        .mt-card-detail {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: var(--fs-13);
            color: var(--gray-50);
        }
        .mt-card-detail svg {
            width: 14px;
            height: 14px;
            color: var(--gray-40);
            flex-shrink: 0;
        }

        .mt-card-warnings {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2);
        }
        .mt-warning-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            font-size: var(--fs-11);
            font-weight: 600;
            border-radius: var(--radius-full);
            background: var(--red-bg);
            color: var(--red);
            border: 1px solid var(--red-border);
        }
        .mt-warning-badge svg {
            width: 12px;
            height: 12px;
        }
        .mt-score-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            font-size: var(--fs-11);
            font-weight: 600;
            border-radius: var(--radius-full);
            background: var(--green-bg);
            color: #16a34a;
            border: 1px solid var(--green-border);
        }
        .mt-score-badge svg {
            width: 12px;
            height: 12px;
        }

        .mt-card-footer {
            margin-top: auto;
            padding-top: var(--space-3);
            border-top: 1px solid var(--gray-10);
        }
        .mt-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            font-size: var(--fs-13);
            font-weight: 600;
            border-radius: var(--radius-md);
            text-decoration: none;
            transition: all var(--duration-fast);
            cursor: pointer;
            border: none;
            font-family: var(--font);
        }
        .mt-action-btn svg {
            width: 14px;
            height: 14px;
        }
        .mt-action-btn.primary {
            background: var(--accent);
            color: var(--white);
        }
        .mt-action-btn.primary:hover {
            background: var(--accent-hover);
            box-shadow: 0 2px 8px rgba(79, 140, 255, 0.3);
        }
        .mt-action-btn.ghost {
            background: var(--gray-5);
            color: var(--gray-70);
            border: 1px solid var(--gray-15);
        }
        .mt-action-btn.ghost:hover {
            background: var(--gray-10);
            color: var(--gray-90);
        }
        .mt-action-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            font-size: var(--fs-12);
            font-weight: 500;
            border-radius: var(--radius-md);
            background: var(--gray-5);
            color: var(--gray-50);
            border: 1px solid var(--gray-10);
        }
        .mt-action-badge svg {
            width: 13px;
            height: 13px;
        }
        .mt-action-badge.warning {
            background: var(--yellow-bg);
            color: #b45309;
            border-color: var(--yellow-border);
        }
        .mt-action-badge.danger {
            background: var(--red-bg);
            color: var(--red);
            border-color: var(--red-border);
        }
        .mt-action-badge.info {
            background: var(--info-bg);
            color: #0891b2;
            border-color: var(--info-border);
        }

        .mt-empty-state {
            text-align: center;
            padding: var(--space-16) var(--space-6);
            background: var(--surface);
            border: 1px dashed var(--gray-20);
            border-radius: var(--radius-lg);
        }
        .mt-empty-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 72px;
            height: 72px;
            border-radius: var(--radius-xl);
            background: var(--gray-5);
            margin-bottom: var(--space-5);
        }
        .mt-empty-icon svg {
            width: 36px;
            height: 36px;
            color: var(--gray-30);
        }
        .mt-empty-state h3 {
            font-size: var(--fs-18);
            font-weight: 650;
            color: var(--gray-80);
            margin: 0 0 var(--space-2);
        }
        .mt-empty-state p {
            font-size: var(--fs-14);
            color: var(--gray-50);
            margin: 0;
            max-width: 360px;
            margin-left: auto;
            margin-right: auto;
        }

        @media (max-width: 768px) {
            .mt-filter-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .mt-filter-tabs {
                overflow-x: auto;
            }
            .mt-search-box input {
                width: 100%;
            }
            .mt-test-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?= iconSprite() ?>
<?php include __DIR__ . '/../../includes/student_header.php'; ?>

<div class="mt-page-header">
    <h1 class="mt-page-title">My Tests</h1>
    <p class="mt-page-subtitle">Browse and manage your assigned assessments</p>
</div>

<?= flashMessage() ?>

<div class="mt-filter-bar">
    <div class="mt-filter-tabs">
        <a href="my_tests.php?filter=all<?= $q !== '' ? '&q=' . urlencode($q) : '' ?>" class="mt-filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
            All <span class="mt-filter-count"><?= $counts['all'] ?></span>
        </a>
        <a href="my_tests.php?filter=active<?= $q !== '' ? '&q=' . urlencode($q) : '' ?>" class="mt-filter-tab <?= $filter === 'active' ? 'active' : '' ?>">
            Active <span class="mt-filter-count"><?= $counts['active'] ?></span>
        </a>
        <a href="my_tests.php?filter=upcoming<?= $q !== '' ? '&q=' . urlencode($q) : '' ?>" class="mt-filter-tab <?= $filter === 'upcoming' ? 'active' : '' ?>">
            Upcoming <span class="mt-filter-count"><?= $counts['upcoming'] ?></span>
        </a>
        <a href="my_tests.php?filter=completed<?= $q !== '' ? '&q=' . urlencode($q) : '' ?>" class="mt-filter-tab <?= $filter === 'completed' ? 'active' : '' ?>">
            Completed <span class="mt-filter-count"><?= $counts['completed'] ?></span>
        </a>
        <a href="my_tests.php?filter=missed<?= $q !== '' ? '&q=' . urlencode($q) : '' ?>" class="mt-filter-tab <?= $filter === 'missed' ? 'active' : '' ?>">
            Missed <span class="mt-filter-count"><?= $counts['missed'] ?></span>
        </a>
    </div>
    <form class="mt-search-box" method="get" action="my_tests.php">
        <input type="hidden" name="filter" value="<?= h($filter) ?>">
        <span class="mt-search-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></span>
        <input type="text" name="q" placeholder="Search tests..." value="<?= h($q) ?>">
    </form>
</div>

<?php if (empty($displayTests)): ?>
    <div class="mt-empty-state">
        <div class="mt-empty-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
        </div>
        <h3>No tests found</h3>
        <p><?= $q !== '' ? 'No tests match your search. Try a different keyword.' : 'There are no tests in this category right now.' ?></p>
    </div>
<?php else: ?>
    <div class="mt-test-grid">
        <?php foreach ($displayTests as $t):
            $start = $t['start_time'] ? strtotime($t['start_time']) : null;
            $end = $t['end_time'] ? strtotime($t['end_time']) : null;
            $sub = $t['submission_status'] ?? null;
            $tabSwitches = $tabSwitchCounts[$t['id']] ?? 0;

            $canStart = false;
            if ($sub === 'in_progress') {
                $canStart = true;
            } elseif ($t['status'] === 'active') {
                $startOk = $start === null || $now >= $start;
                $endOk = $end === null || $now <= $end;
                $canStart = $startOk && $endOk;
            }

            $cardStatus = 'completed';
            if (in_array($t['id'], array_column($activeTests, 'id'))) $cardStatus = 'active';
            elseif (in_array($t['id'], array_column($upcomingTests, 'id'))) $cardStatus = 'upcoming';
            elseif (in_array($t['id'], array_column($missedTests, 'id'))) $cardStatus = 'missed';

            if ($sub === 'evaluated') {
                $scorePct = $t['total_marks'] > 0 ? round(($t['total_marks_obtained'] / $t['total_marks']) * 100) : 0;
                $actionHtml = '<a href="test.php?test_id=' . $t['id'] . '" class="mt-action-btn ghost">' . icon('chart', 14) . ' View Result</a>';
            } elseif ($sub === 'submitted') {
                $actionHtml = '<span class="mt-action-badge">' . icon('clock', 13) . ' Submitted</span>';
            } elseif ($sub === 'in_progress' && $t['status'] !== 'completed') {
                $label = $t['status'] === 'paused' ? 'Resume (Paused)' : 'Resume';
                $actionHtml = '<a href="test.php?test_id=' . $t['id'] . '" class="mt-action-btn primary">' . icon('play', 14) . ' ' . $label . '</a>';
            } elseif ($t['status'] === 'active' && $canStart) {
                $actionHtml = '<a href="test.php?test_id=' . $t['id'] . '" class="mt-action-btn primary">' . icon('play', 14) . ' Start Test</a>';
            } elseif ($t['status'] === 'paused') {
                $actionHtml = '<span class="mt-action-badge warning">Paused by Admin</span>';
            } elseif ($t['status'] === 'upcoming') {
                $actionHtml = '<span class="mt-action-badge info">Upcoming</span>';
            } elseif ($t['status'] === 'scheduled') {
                if ($start !== null && $now < $start) {
                    $mins = ceil(($start - $now) / 60);
                    $hours = floor($mins / 60);
                    $remainMins = $mins % 60;
                    $timeStr = $hours > 0 ? "{$hours}h {$remainMins}m" : "{$remainMins} min";
                    $actionHtml = '<span class="mt-action-badge info">' . icon('calendar', 13) . ' Starts in ' . $timeStr . '</span>';
                } else {
                    $actionHtml = '<span class="mt-action-badge">Scheduled</span>';
                }
            } elseif ($t['status'] === 'active' && !$canStart) {
                if ($start !== null && $now < $start) {
                    $mins = ceil(($start - $now) / 60);
                    $actionHtml = '<span class="mt-action-badge info">Starts in ' . $mins . ' min</span>';
                } else {
                    $actionHtml = '<span class="mt-action-badge danger">Expired</span>';
                }
            } else {
                $actionHtml = '<span class="mt-action-badge danger">Missed</span>';
            }

            $statusBadge = 'completed';
            if ($t['status'] === 'active') $statusBadge = 'active';
            elseif ($t['status'] === 'upcoming' || $t['status'] === 'scheduled') $statusBadge = 'upcoming';

            $startStr = $start ? date('M j, g:i A', $start) : '—';
            $endStr = $end ? date('M j, g:i A', $end) : '—';
        ?>
        <div class="mt-test-card status-<?= h($cardStatus) ?>">
            <div class="mt-card-top">
                <h3 class="mt-card-title">
                    <a href="test.php?test_id=<?= $t['id'] ?>"><?= h($t['title']) ?></a>
                </h3>
                <span class="mt-status-badge <?= h($statusBadge) ?>"><?= h(ucfirst($t['status'])) ?></span>
            </div>

            <div class="mt-card-details">
                <div class="mt-card-detail">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <?= $t['duration_minutes'] ?> min
                </div>
                <div class="mt-card-detail">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <?= $t['question_count'] ?? '—' ?> questions
                </div>
                <div class="mt-card-detail">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                    <?= $startStr ?> — <?= $endStr ?>
                </div>
            </div>

            <div class="mt-card-warnings">
                <?php if ($tabSwitches > 0): ?>
                    <span class="mt-warning-badge">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                        <?= $tabSwitches ?> tab switch<?= $tabSwitches !== 1 ? 'es' : '' ?>
                    </span>
                <?php endif; ?>
                <?php if ($sub === 'evaluated' && isset($scorePct)): ?>
                    <span class="mt-score-badge">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
                        <?= $scorePct ?>%
                    </span>
                <?php endif; ?>
            </div>

            <div class="mt-card-footer">
                <?= $actionHtml ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

</div>
</main>
</div>
</div>
<?php include __DIR__ . '/../../includes/student_footer.php'; ?>

<script>
function toggleSidebar(forceState) {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const isOpen = forceState !== undefined ? forceState : !sidebar.classList.contains('open');
    sidebar.classList.toggle('open', isOpen);
    overlay.classList.toggle('show', isOpen);
    document.body.classList.toggle('sidebar-open', isOpen);
    sidebar.setAttribute('aria-hidden', !isOpen);
}
function closeSidebar() { toggleSidebar(false); }

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeSidebar();
});

document.addEventListener('keydown', function(e) {
    if (e.key !== 'Tab') return;
    const sidebar = document.getElementById('sidebar');
    if (!sidebar || !sidebar.classList.contains('open')) return;
    const f = sidebar.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])');
    if (!f.length) return;
    if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length-1].focus(); }
    else if (!e.shiftKey && document.activeElement === f[f.length-1]) { e.preventDefault(); f[0].focus(); }
});

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
(function() {
    const saved = localStorage.getItem('theme');
    if (saved) {
        document.documentElement.setAttribute('data-theme', saved);
        updateThemeUI(saved);
    }
})();

document.querySelector('.mt-search-box input[type="text"]').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        this.closest('form').submit();
    }
});

lucide.createIcons();
</script>
</body>
</html>
