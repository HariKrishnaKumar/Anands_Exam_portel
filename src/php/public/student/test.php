<?php
/**
 * Student Test Taking Interface.
 * Supports: logged-in students, guest access via session.
 */
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/icons.php';
startSession();

// Must be either student or guest
if (!isStudent() && !isset($_SESSION['guest_token'])) {
    redirect(BASE_URL . '/login.php');
}

$pdo = getDB();
$testId = (int)($_GET['test_id'] ?? ($_SESSION['test_id'] ?? 0));

if ($testId <= 0) {
    redirect(BASE_URL . '/student/dashboard.php');
}

// Get test info
$stmt = $pdo->prepare("
    SELECT t.*, b.name AS batch_name
    FROM tests t
    JOIN batches b ON b.id = t.batch_id
    WHERE t.id = ?
");
$stmt->execute([$testId]);
$test = $stmt->fetch();

if (!$test) {
    die('Test not found.');
}

// ─── ACCESS CONTROL ───────────────────────────────────────
// Paused tests: block new access but allow resume for in-progress
// Completed tests: block entirely
$isPaused = ($test['status'] === 'paused');
$isStopped = ($test['status'] === 'completed');

// Check if student has an existing in-progress submission
$hasProgress = false;
if (isStudent()) {
    $checkSt = $pdo->prepare("SELECT status FROM submissions WHERE student_id = ? AND test_id = ?");
    $checkSt->execute([$_SESSION['student_id'], $testId]);
    $existing = $checkSt->fetch();
    $hasProgress = ($existing && $existing['status'] === 'in_progress');
}

$backBtnJs = '<script>function backToDashboard(){if(window.self!==window.top){window.parent.postMessage({type:"closeTestListModal"},window.location.origin);return;}window.location.href="dashboard.php";}</script>';

if ($isStopped) {
    echo '<!DOCTYPE html><html><head><title>Test Stopped</title><link rel="stylesheet" href="' . ASSETS_URL . '/css/student.css"></head><body>';
    echo '<div class="container" style="max-width:600px;margin:80px auto;text-align:center;">';
    echo '<div style="margin-bottom:16px;"><svg width="48" height="48" viewBox="0 0 20 20" fill="#BC2F32"><path d="M4 4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4zm2 1v10h8V5H6z"/></svg></div>';
    echo '<h1 style="font-size:1.5rem;margin-bottom:8px;">Test Stopped</h1>';
    echo '<p class="text-muted">This test has been ended by the admin. Please contact your instructor.</p>';
    echo '<a href="javascript:void(0)" onclick="backToDashboard()" class="btn btn-primary" style="margin-top:16px;">Back to Dashboard</a>';
    echo $backBtnJs;
    echo '</div></body></html>';
    exit;
}

if ($isPaused && !$hasProgress) {
    echo '<!DOCTYPE html><html><head><title>Test Paused</title><link rel="stylesheet" href="' . ASSETS_URL . '/css/student.css"></head><body>';
    echo '<div class="container" style="max-width:600px;margin:80px auto;text-align:center;">';
    echo '<div style="margin-bottom:16px;"><svg width="48" height="48" viewBox="0 0 20 20" fill="#826A00"><path d="M5 3a1 1 0 0 0-1 1v12a1 1 0 0 0 2 0V4a1 1 0 0 0-1-1zm10 0a1 1 0 0 0-1 1v12a1 1 0 0 0 2 0V4a1 1 0 0 0-1-1z"/></svg></div>';
    echo '<h1 style="font-size:1.5rem;margin-bottom:8px;">Test Paused</h1>';
    echo '<p class="text-muted">This test has been paused by the admin. It will be available again once resumed.</p>';
    echo '<a href="javascript:void(0)" onclick="backToDashboard()" class="btn btn-primary" style="margin-top:16px;">Back to Dashboard</a>';
    echo $backBtnJs;
    echo '</div></body></html>';
    exit;
}

// Get or create submission
if (isStudent()) {
    $studentId = $_SESSION['student_id'];

    // Find existing submission
    $stmt = $pdo->prepare("SELECT * FROM submissions WHERE student_id = ? AND test_id = ?");
    $stmt->execute([$studentId, $testId]);
    $submission = $stmt->fetch();

    if (!$submission) {
        // Create new submission
        $stmt = $pdo->prepare("INSERT INTO submissions (student_id, test_id, status) VALUES (?, ?, 'in_progress')");
        $stmt->execute([$studentId, $testId]);
        $submissionId = (int)$pdo->lastInsertId();
        $submission = [
            'id' => $submissionId,
            'student_id' => $studentId,
            'test_id' => $testId,
            'status' => 'in_progress',
            'started_at' => date('Y-m-d H:i:s'),
            'timer_extended_minutes' => 0,
        ];
    } else {
        $submissionId = $submission['id'];
    }
} else {
    // Guest mode — use temporary storage
    $guestEntryId = $_SESSION['guest_entry_id'];
    $submissionId = 'guest_' . $guestEntryId;
    $submission = [
        'id' => $submissionId,
        'status' => 'in_progress',
        'started_at' => date('Y-m-d H:i:s'),
        'timer_extended_minutes' => 0,
    ];
}

// If already submitted/evaluated, show result (with score if evaluated)
if (($submission['status'] ?? '') === 'submitted' || ($submission['status'] ?? '') === 'evaluated') {
    $evalStatus = $submission['evaluation_status'] ?? (($submission['status'] === 'evaluated') ? 'evaluated' : 'pending_manual_review');
    $isEval = ($evalStatus === 'evaluated');
    $score = $submission['total_score'] ?? $submission['total_marks_obtained'] ?? null;
    $total = $submission['total_marks'] ?? null;
    $pct = ($isEval && $score !== null && $total > 0) ? round(($score / $total) * 100, 1) : null;

    echo '<!DOCTYPE html><html><head><title>Test Result</title><link rel="stylesheet" href="' . ASSETS_URL . '/css/student.css"></head><body>';
    echo '<div class="container" style="max-width:600px;margin:80px auto;text-align:center;">';
    if ($isEval && $pct !== null) {
        $iconColor = $pct >= 40 ? '#0B6A0B' : '#BC2F32';
        echo '<div style="margin-bottom:16px;"><svg width="48" height="48" viewBox="0 0 20 20" fill="' . $iconColor . '"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg></div>';
        echo '<h1 style="font-size:1.5rem;margin-bottom:8px;">Test Completed</h1>';
        echo '<div style="font-size:2rem;font-weight:700;color:' . $iconColor . ';margin:16px 0;">' . $pct . '%</div>';
        echo '<p class="text-muted">' . h($score) . ' / ' . h($total) . ' marks</p>';
    } elseif (!$isEval) {
        echo '<div style="margin-bottom:16px;"><svg width="48" height="48" viewBox="0 0 20 20" fill="#826A00"><path d="M10 2a1 1 0 0 1 .9.55l7 13A1 1 0 0 1 17 17H3a1 1 0 0 1-.9-1.45l7-13A1 1 0 0 1 10 2zm0 5a1 1 0 0 0-1 1v2a1 1 0 1 0 2 0V8a1 1 0 0 0-1-1zm0 7.2a1.1 1.1 0 1 0 0-2.2 1.1 1.1 0 0 0 0 2.2z"/></svg></div>';
        echo '<h1 style="font-size:1.5rem;margin-bottom:8px;">Result Not Yet Announced</h1>';
        echo '<span class="badge badge-pending">Under Evaluation</span>';
        echo '<p class="text-muted" style="margin-top:12px;">Your test was submitted successfully. This assessment includes written/coding answers that are being reviewed — your full result will appear here once the evaluator finishes grading.</p>';
    } else {
        echo '<div style="margin-bottom:16px;"><svg width="48" height="48" viewBox="0 0 20 20" fill="var(--accent)"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg></div>';
        echo '<h1 style="font-size:1.5rem;margin-bottom:8px;">Test Submitted</h1>';
        echo '<p class="text-muted">Your test has been submitted. Results will be available once evaluated.</p>';
    }
    echo '<a href="javascript:void(0)" onclick="backToDashboard()" class="btn btn-primary" style="margin-top:20px;">Back to Dashboard</a>';
    echo $backBtnJs;
    echo '</div></body></html>';
    exit;
}

// Check if test is active (or guest — always allow)
if (!isset($_SESSION['guest_token'])) {
    $now = time();
    $start = $test['start_time'] ? strtotime($test['start_time']) : 0;
    $end = $test['end_time'] ? strtotime($test['end_time']) : 0;

    if ($test['status'] !== 'active' || ($start > 0 && $now < $start) || ($end > 0 && $now > $end)) {
        echo '<!DOCTYPE html><html><head><title>Test Not Available</title><link rel="stylesheet" href="' . ASSETS_URL . '/css/student.css"></head><body>';
        echo '<div class="container" style="max-width:600px;margin:80px auto;text-align:center;">';
        echo '<div style="margin-bottom:16px;"><svg width="48" height="48" viewBox="0 0 20 20" fill="var(--yellow)"><path d="M10 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16zm0 1a7 7 0 1 0 0 14 7 7 0 0 0 0-14zm.5 2.5a.5.5 0 0 0-1 0V10a.5.5 0 0 0 .22.42l3 2a.5.5 0 1 0 .56-.84L10.5 9.57V5.5z"/></svg></div>';
        echo '<h1 style="font-size:1.5rem;margin-bottom:8px;">Test is not available right now</h1>';
        echo '<p class="text-muted">This test may have ended or hasn\'t started yet.</p>';
        echo '<a href="javascript:void(0)" onclick="backToDashboard()" class="btn btn-primary" style="margin-top:20px;">Back to Dashboard</a>';
        echo $backBtnJs;
        echo '</div></body></html>';
        exit;
    }
}

// Get questions
$stmt = $pdo->prepare("SELECT * FROM questions WHERE test_id = ? ORDER BY sort_order, id");
$stmt->execute([$testId]);
$questions = $stmt->fetchAll();

if (empty($questions)) {
    echo '<!DOCTYPE html><html><head><title>No Questions</title><link rel="stylesheet" href="' . ASSETS_URL . '/css/student.css"></head><body>';
    echo '<div class="container" style="max-width:600px;margin:80px auto;text-align:center;">';
    echo '<h1 style="font-size:1.5rem;">No questions in this test yet.</h1>';
    echo '<a href="javascript:void(0)" onclick="backToDashboard()" class="btn btn-primary" style="margin-top:20px;">Back</a>';
    echo $backBtnJs;
    echo '</div></body></html>';
    exit;
}

// Get saved answers for in-progress test
$savedAnswers = [];
if (!isset($_SESSION['guest_token'])) {
    $stmt = $pdo->prepare("SELECT question_id, answer_json FROM student_answers WHERE submission_id = ?");
    $stmt->execute([$submissionId]);
    foreach ($stmt->fetchAll() as $a) {
        $savedAnswers[$a['question_id']] = json_decode($a['answer_json'], true);
    }
}

// Top-bar identity (guest sessions fall back to a neutral label)
$meName = 'Guest';
if (isStudent()) {
    $meStmt = $pdo->prepare("SELECT name FROM students WHERE id = ?");
    $meStmt->execute([$_SESSION['student_id']]);
    $meRow = $meStmt->fetch();
    if ($meRow && trim((string)$meRow['name']) !== '') {
        $meName = trim($meRow['name']);
    }
}
$meInitials = '';
foreach (preg_split('/\s+/', $meName) as $mePart) {
    if ($mePart === '') {
        continue;
    }
    $meInitials .= strtoupper(substr($mePart, 0, 1));
    if (strlen($meInitials) >= 2) {
        break;
    }
}
if ($meInitials === '') {
    $meInitials = 'G';
}

// Calculate remaining time
$elapsed = time() - strtotime($submission['started_at']);
$totalSeconds = ($test['duration_minutes'] * 60) + ($submission['timer_extended_minutes'] * 60);
$remaining = max(0, $totalSeconds - $elapsed);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($test['title']) ?> | Test</title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/student.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20,300,0,0">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* ── Card + paging ───────────────────────────────────────── */
        .question-card { transition: border-color 0.2s; }
        .question-card.answered { border-left: 4px solid var(--accent); }
        .question-card.is-hidden { display: none; }

        /* ── Top bar ──────────────────────────────────────────────
           #1A2130 / #101828 are written as literals on purpose: the
           [data-theme="dark"] block redefines --gray-95/--gray-100 to
           light values, which would flip the bar to a light strip. */
        .exam-topbar {
            position: fixed; top: 0; left: 0; right: 0;
            z-index: var(--z-timer);
            height: 60px; padding: 0 20px;
            box-sizing: border-box;
            display: flex; align-items: center; gap: 24px;
            background: #1A2130;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            color: #fff;
            font-size: var(--fs-14);
        }
        .tb-brand { display: flex; align-items: center; gap: 10px; min-width: 170px; }
        .tb-logo {
            width: 36px; height: 36px; border-radius: 9px;
            background: var(--accent); color: #fff;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .tb-name { font-weight: 700; font-size: var(--fs-16); white-space: nowrap; }
        .tb-test { display: flex; align-items: center; gap: 10px; flex: 1; justify-content: center; min-width: 0; }
        .tb-test svg { flex-shrink: 0; }
        .tb-title {
            font-size: var(--fs-18); font-weight: 700;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .tb-timer { display: flex; align-items: baseline; gap: 8px; white-space: nowrap; }
        .tb-timer-label { font-size: 11px; font-weight: 600; letter-spacing: 0.14em; color: #98A2B3; }
        .exam-topbar .timer-display {
            font-family: var(--mono); font-size: var(--fs-20); font-weight: 700;
            color: #fff; letter-spacing: 0.04em;
            transition: color var(--ease-normal);
        }
        .exam-topbar .timer-display.warning { color: var(--orange); }
        .exam-topbar .timer-display.danger {
            color: var(--red);
            animation: timerPulse 1s var(--ease-standard) infinite;
        }
        .tb-user { display: flex; align-items: center; gap: 10px; min-width: 130px; justify-content: flex-end; }
        .tb-avatar {
            width: 34px; height: 34px; border-radius: 50%;
            background: var(--accent2); color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700; flex-shrink: 0;
            text-transform: uppercase;
        }
        .tb-username { font-weight: 600; font-size: var(--fs-14); white-space: nowrap; }

        /* ── Shell ──────────────────────────────────────────────── */
        .exam-shell { display: flex; align-items: flex-start; margin-top: 60px; }

        .exam-sidebar {
            width: 300px; flex-shrink: 0;
            position: sticky; top: 60px;
            height: calc(100vh - 60px);
            box-sizing: border-box;
            background: #101828;
            padding: 20px;
            overflow-y: auto;
            display: flex; flex-direction: column; gap: 18px;
            color: #fff;
        }
        .sb-test {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-radius: var(--radius-lg);
            padding: 14px;
        }
        .sb-test-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px; }
        .sb-test-title { display: flex; align-items: center; gap: 8px; min-width: 0; font-size: var(--fs-14); font-weight: 600; }
        .sb-test-title span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sb-count { font-size: var(--fs-13); font-weight: 700; color: var(--accent); flex-shrink: 0; }
        .sb-progress { height: 6px; border-radius: var(--radius-full); background: rgba(255, 255, 255, 0.14); overflow: hidden; }
        .sb-progress i {
            display: block; height: 100%; width: 0%;
            background: var(--accent); border-radius: var(--radius-full);
            transition: width 0.25s var(--ease-normal);
        }
        .sb-label { font-size: 11px; font-weight: 700; letter-spacing: 0.14em; color: #8A94A6; }
        .exam-sidebar .nav-dots { margin: 0; }

        .sb-legend { display: flex; flex-direction: column; gap: 9px; font-size: var(--fs-13); color: #C3CAD6; }
        .sb-legend .lg { display: flex; align-items: center; gap: 9px; }
        .sb-legend .lg i { width: 11px; height: 11px; border-radius: 50%; flex-shrink: 0; }
        .sb-legend .lg i.attended { background: var(--green); }
        .sb-legend .lg i.not-attended { background: var(--red); }
        .sb-legend .lg i.not-visited { background: var(--accent2); }

        .sb-submit {
            margin-top: auto;
            width: 100%; padding: 13px;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            background: transparent;
            border: 1px solid var(--red);
            border-radius: var(--radius-md);
            color: var(--red);
            font-size: var(--fs-14); font-weight: 600;
            cursor: pointer;
            transition: background var(--ease-fast);
        }
        .sb-submit:hover:not(:disabled) { background: rgba(239, 68, 68, 0.12); }
        .sb-submit:disabled { opacity: 0.5; cursor: not-allowed; }

        /* ── Main column ────────────────────────────────────────── */
        .exam-main {
            flex: 1; min-width: 0;
            min-height: calc(100vh - 60px);
            box-sizing: border-box;
            background: var(--bg-primary);
            padding: 24px 28px 0;
            display: flex; flex-direction: column;
        }

        /* The header row above the card owns the question number; the in-card
           copy only comes back in the no-JS fallback (see <noscript> in head). */
        .exam-main .question-number { display: none; }

        .exam-head { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-bottom: 18px; }
        .eh-count { font-size: var(--fs-18); font-weight: 700; color: var(--gray-90); }
        .eh-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--accent); color: #fff;
            padding: 7px 14px; border-radius: var(--radius-md);
            font-size: var(--fs-13); font-weight: 600;
            max-width: 320px;
        }
        .eh-badge span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .eh-meta { display: flex; align-items: center; gap: 8px; margin-left: auto; }
        .eh-marks { font-size: var(--fs-13); color: var(--gray-60); font-weight: 500; white-space: nowrap; }
        .eh-type {
            font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
            padding: 5px 10px; border-radius: var(--radius-sm);
            background: var(--gray-5); color: var(--gray-70);
            border: 1px solid var(--gray-20);
            white-space: nowrap;
        }

        /* ── Options as reference rows ──────────────────────────── */
        .exam-main .option-label {
            display: flex; align-items: center; gap: 14px;
            padding: 16px 18px;
            border: none; border-bottom: 1px solid var(--gray-15);
            border-radius: 0;
            background: transparent;
        }
        .exam-main .option-label:first-child { border-top: 1px solid var(--gray-15); }
        .exam-main .option-label:hover { background: var(--gray-5); border-color: var(--gray-15); }
        .exam-main .option-label input[type="radio"] {
            margin: 0; width: 18px; height: 18px;
            accent-color: var(--accent); flex-shrink: 0; cursor: pointer;
        }
        .exam-main .option-label .opt-key {
            width: 30px; height: 30px; flex-shrink: 0;
            border-radius: 7px;
            background: #1A2130; color: #fff;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: var(--fs-13); font-weight: 700;
            line-height: 1;
        }
        .exam-main .option-label.selected { background: var(--accent-light); box-shadow: inset 3px 0 0 var(--accent); }
        .exam-main .option-label.selected .opt-key { color: #fff; font-weight: 700; }
        .exam-main .option-label span { font-size: var(--fs-16); color: var(--gray-80); line-height: 1.5; }

        /* The form must stretch so the sticky bottom bar can sit at its foot */
        #testForm { display: flex; flex-direction: column; flex: 1; min-width: 0; }

        /* ── Sticky bottom bar ──────────────────────────────────── */
        .exam-bottom {
            position: sticky; bottom: 0;
            margin-top: auto;
            padding: 16px 0;
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
            background: var(--bg-primary);
            border-top: 1px solid var(--gray-15);
        }
        .nav-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 12px 22px; border-radius: var(--radius-md);
            font-size: var(--fs-14); font-weight: 600;
            cursor: pointer; transition: all var(--ease-fast);
        }
        .nav-prev { background: var(--surface-card); border: 1px solid var(--gray-20); color: var(--gray-70); }
        .nav-prev:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); }
        .nav-next { background: var(--accent); border: 1px solid var(--accent); color: #fff; }
        .nav-next:hover:not(:disabled) { filter: brightness(1.06); }
        .nav-btn:disabled { opacity: 0.45; cursor: not-allowed; }

        .exam-page-dots { display: flex; align-items: center; gap: 7px; }
        .exam-page-dots i {
            width: 8px; height: 8px; border-radius: var(--radius-full);
            background: var(--gray-20);
            transition: all var(--ease-fast);
        }
        .exam-page-dots i.is-done { background: var(--green); }
        .exam-page-dots i.is-current { width: 22px; background: var(--accent); }

        /* ── Tab switch warning ─────────────────────────────────── */
        .tab-switch-warning {
            position: fixed; top: 0; left: 0; right: 0;
            background: #FDE7E9; color: #BC2F32; text-align: center;
            padding: 8px; font-size: 0.8125rem; z-index: 999;
            transform: translateY(-100%); transition: transform 0.3s;
        }
        .tab-switch-warning.show { transform: translateY(0); }

        /* Phone: stack the sidebar under the main column so the page stays usable */
        @media (max-width: 1024px) {
            .exam-shell { flex-direction: column; }
            .exam-sidebar { position: static; width: 100%; height: auto; }
        }
    </style>
    <noscript>
        <style>
            /* No JS: reveal every question and the native submit button */
            .exam-main .question-card.is-hidden { display: block; }
            .exam-main .question-number { display: block; }
            .exam-main .exam-head,
            .exam-main .exam-bottom { display: none; }
        </style>
    </noscript>
</head>
<body>
    <!-- Tab Switch Warning Banner -->
    <div class="tab-switch-warning" id="tabWarning"><?= icon('warning', 16, 'var(--red)') ?> Tab switch detected. This is being recorded.</div>

    <!-- Top bar: brand | test title | timer | signed-in student -->
    <div class="exam-topbar" id="timerBar">
        <div class="tb-brand">
            <span class="tb-logo"><?= icon('graduation-cap', 18) ?></span>
            <span class="tb-name">BGS Group</span>
        </div>
        <div class="tb-test">
            <?= icon('file-text', 16) ?>
            <span class="tb-title"><?= h($test['title']) ?></span>
        </div>
        <div class="tb-timer">
            <span class="tb-timer-label">TIME LEFT</span>
            <span id="timerDisplay" class="timer-display <?= $remaining < 300 ? ($remaining < 60 ? 'danger' : 'warning') : '' ?>"
                  data-remaining="<?= $remaining ?>"><?= gmdate('H:i:s', $remaining) ?></span>
        </div>
        <div class="tb-user">
            <span class="tb-username"><?= h($meName) ?></span>
            <span class="tb-avatar"><?= h($meInitials) ?></span>
        </div>
    </div>

    <div class="exam-shell">
        <aside class="exam-sidebar">
            <div class="sb-test">
                <div class="sb-test-head">
                    <div class="sb-test-title">
                        <?= icon('book-open', 15) ?>
                        <span><?= h($test['title']) ?></span>
                    </div>
                    <div class="sb-count" id="sbCount">0 / <?= count($questions) ?></div>
                </div>
                <div class="sb-progress"><i id="sbBar"></i></div>
            </div>

            <div class="sb-label">QUESTION NAVIGATOR</div>
            <div class="nav-dots" id="navDots">
                <?php foreach ($questions as $i => $q):
                    $answered = isset($savedAnswers[$q['id']]);
                    $navState = $answered ? 'answered' : ($i === 0 ? 'not-attended' : 'not-visited');
                ?>
                    <a href="#q<?= $q['id'] ?>" class="nav-dot <?= $navState ?> <?= $i === 0 ? 'current' : '' ?>"
                       data-qid="<?= $q['id'] ?>" data-index="<?= $i ?>"><?= $i + 1 ?></a>
                <?php endforeach; ?>
            </div>

            <div class="sb-legend">
                <span class="lg"><i class="attended"></i> Attended</span>
                <span class="lg"><i class="not-attended"></i> Not attended</span>
                <span class="lg"><i class="not-visited"></i> Not visited</span>
            </div>

            <button type="button" class="sb-submit" id="submitBtn">
                <?= icon('check-circle', 16) ?> Submit Test
            </button>
        </aside>

        <main class="exam-main">
        <?php $apiUrl = BASE_URL . '/api'; ?>
        <form id="testForm" method="POST" action="<?= $apiUrl ?>/submit_answer.php">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
            <input type="hidden" name="test_id" value="<?= $testId ?>">
            <input type="hidden" name="submission_id" value="<?= h($submission['id']) ?>">
            <!-- Ask the API to redirect browsers back to the result screen after final submit.
                 Auto-save (fetch) requests ignore this — they keep getting JSON. -->
            <input type="hidden" name="redirect" value="1">

            <!-- Header row above the current question (JS keeps it in sync) -->
            <div class="exam-head" id="examHead">
                <span class="eh-count" id="ehCount">Question 1 of <?= count($questions) ?></span>
                <span class="eh-badge"><?= icon('book-open', 15) ?><span><?= h($test['title']) ?></span></span>
                <span class="eh-meta">
                    <span class="eh-marks" id="ehMarks"><?= $questions[0]['marks'] ?> mark<?= $questions[0]['marks'] > 1 ? 's' : '' ?></span>
                    <span class="eh-type" id="ehType"><?= $questions[0]['type'] === 'mcq' ? 'MCQ' : ($questions[0]['type'] === 'coding' ? 'Coding' : 'Explanation') ?></span>
                </span>
            </div>

            <?php foreach ($questions as $index => $q):
                $qType = $q['type'];
                $options = json_decode((string)$q['options_json'], true) ?? [];
                $selected = $savedAnswers[$q['id']]['selected'] ?? '';
            ?>
            <div class="question-card <?= $selected ? 'answered' : '' ?> <?= $index === 0 ? '' : 'is-hidden' ?>"
                 id="q<?= $q['id'] ?>"
                 data-index="<?= $index ?>"
                 data-marks="<?= (int)$q['marks'] ?>"
                 data-type="<?= $qType === 'mcq' ? 'MCQ' : ($qType === 'coding' ? 'Coding' : 'Explanation') ?>">
                <div class="question-number">
                    Question <?= $index + 1 ?> of <?= count($questions) ?>
                    <?php if ($qType === 'coding'): ?>
                        <span class="badge badge-pending" style="margin-left:8px;"><?= icon('code', 12) ?> Coding</span>
                    <?php elseif ($qType === 'explanation'): ?>
                        <span class="badge badge-pending" style="margin-left:8px;"><?= icon('file-text', 12) ?> Explanation</span>
                    <?php endif; ?>
                    <span class="text-muted text-sm" style="margin-left:8px;">(<?= $q['marks'] ?> mark<?= $q['marks'] > 1 ? 's' : '' ?>)</span>
                </div>
                <div class="question-text"><?= nl2br(h($q['question_text'])) ?></div>

                <?php if ($qType === 'mcq'): ?>
                    <div class="options">
                        <?php foreach ($options as $optIndex => $opt):
                            $optKey = $opt['key'] ?? '';
                            $optText = $opt['text'] ?? $opt['value'] ?? '';
                            $isSelected = ($selected === $optKey);
                        ?>
                        <label class="option-label <?= $isSelected ? 'selected' : '' ?>">
                            <input type="radio" name="answer[<?= $q['id'] ?>]" value="<?= h($optKey) ?>"
                                   <?= $isSelected ? 'checked' : '' ?>
                                   onchange="this.closest('.option-label').classList.add('selected'); updateNavDot(<?= $q['id'] ?>)">
                            <span class="opt-key" aria-hidden="true"><?= h($optKey !== '' ? $optKey : chr(65 + $optIndex)) ?></span>
                            <span><?= h($optText) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ($qType === 'coding'): ?>
                    <textarea class="form-textarea code-editor"
                              name="answer[<?= $q['id'] ?>]"
                              placeholder="Write your code here..."
                              onchange="updateNavDot(<?= $q['id'] ?>)"
                              style="min-height:150px;font-family:var(--mono);font-size:0.875rem;"><?= h($savedAnswers[$q['id']]['code'] ?? '') ?></textarea>

                <?php elseif ($qType === 'explanation'): ?>
                    <textarea class="form-textarea"
                              name="answer[<?= $q['id'] ?>]"
                              placeholder="Write your explanation here..."
                              onchange="updateNavDot(<?= $q['id'] ?>)"
                              style="min-height:120px;"><?= h($savedAnswers[$q['id']]['text'] ?? '') ?></textarea>
                <?php endif; ?>

                <!-- Hidden input for null answer (to differentiate unanswered vs unchecked) -->
                <input type="hidden" name="question_ids[]" value="<?= $q['id'] ?>">
            </div>
            <?php endforeach; ?>

            <noscript>
                <div style="text-align:center;padding:24px 0;">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <?= icon('check-circle', 18) ?> Submit Test
                    </button>
                </div>
            </noscript>

            <div class="exam-bottom" id="examBottom">
                <button type="button" class="nav-btn nav-prev" id="prevBtn" disabled>
                    <?= icon('arrow-left', 16) ?> Previous
                </button>
                <div class="exam-page-dots" id="pageDots"></div>
                <button type="button" class="nav-btn nav-next" id="nextBtn">
                    Next <?= icon('arrow-right', 16) ?>
                </button>
            </div>
        </form>
        </main>
    </div>

    <script>
    // ─── Idempotent submission guard ─────────────────────────
    // One submit per interaction session: double-clicks, timer auto-submit
    // and stray auto-saves can never flip the submission twice. The server
    // additionally enforces idempotency with a conditional status transition
    // and UNIQUE KEY upserts on student_answers.
    let isSubmitting = false;
    const submitBtn = document.getElementById('submitBtn');
    const testForm = document.getElementById('testForm');

    function lockSubmissionUI() {
        isSubmitting = true;
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting…';
        }
    }

    function requestSubmission(askConfirm) {
        if (isSubmitting) return;
        if (askConfirm && !confirm('Are you sure you want to submit? This action cannot be undone.')) return;
        lockSubmissionUI();
        testForm.submit(); // native submit (runs the idempotent server path)
    }

    // Guard the browser-native submit path (Enter key / non-JS fallback)
    testForm.addEventListener('submit', function(e) {
        if (isSubmitting) {
            e.preventDefault();
            return false;
        }
        if (!confirm('Are you sure you want to submit? This action cannot be undone.')) {
            e.preventDefault();
            return false;
        }
        lockSubmissionUI();
    });

    if (submitBtn) submitBtn.addEventListener('click', function() { requestSubmission(true); });

    // ─── Timer ──────────────────────────────────────────────
    let remainingSeconds = <?= $remaining ?>;
    const timerDisplay = document.getElementById('timerDisplay');

    function updateTimer() {
        remainingSeconds--;
        if (remainingSeconds <= 0) {
            timerDisplay.textContent = '00:00:00';
            requestSubmission(false); // guarded — no confirm on timeout
            return;
        }
        const h = Math.floor(remainingSeconds / 3600);
        const m = Math.floor((remainingSeconds % 3600) / 60);
        const s = remainingSeconds % 60;
        timerDisplay.textContent =
            String(h).padStart(2, '0') + ':' +
            String(m).padStart(2, '0') + ':' +
            String(s).padStart(2, '0');

        // Warning classes
        timerDisplay.classList.toggle('warning', remainingSeconds < 300 && remainingSeconds >= 60);
        timerDisplay.classList.toggle('danger', remainingSeconds < 60);
    }

    <?php if ($test['duration_minutes'] > 0): ?>
    setInterval(updateTimer, 1000);
    <?php endif; ?>

    // ─── One question at a time: paging + navigator state ───────
    const cards = Array.from(document.querySelectorAll('.question-card'));
    const navDots = Array.from(document.querySelectorAll('.nav-dot'));
    const ehCount = document.getElementById('ehCount');
    const ehMarks = document.getElementById('ehMarks');
    const ehType = document.getElementById('ehType');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const pageDots = document.getElementById('pageDots');
    const sbCount = document.getElementById('sbCount');
    const sbBar = document.getElementById('sbBar');
    const POS_KEY = 'test-pos-<?= (int)$testId ?>';

    let currentIndex = 0;
    const visited = new Set([0]); // loading the page lands on question 1

    navDots.forEach(() => pageDots.appendChild(document.createElement('i')));

    function isAnswered(card) {
        if (card.querySelector('input[type="radio"]:checked')) return true;
        const field = card.querySelector('textarea');
        return !!field && field.value.trim() !== '';
    }

    function recomputeStats() {
        let answered = 0;
        cards.forEach((card, i) => {
            const done = isAnswered(card);
            card.classList.toggle('answered', done);
            if (done) visited.add(i);
            if (done) answered++;

            const dot = navDots[i];
            if (!dot) return;
            dot.classList.toggle('answered', done);
            dot.classList.toggle('not-attended', !done && visited.has(i));
            dot.classList.toggle('not-visited', !done && !visited.has(i));
        });

        if (sbCount) sbCount.textContent = answered + ' / ' + cards.length;
        if (sbBar) sbBar.style.width = (cards.length ? (answered / cards.length) * 100 : 0) + '%';

        Array.from(pageDots.children).forEach((tick, i) => {
            tick.classList.toggle('is-done', !!cards[i] && isAnswered(cards[i]));
            tick.classList.toggle('is-current', i === currentIndex);
        });
    }

    function updateNavDot(qId) {
        const index = cards.findIndex(card => card.id === 'q' + qId);
        if (index >= 0) visited.add(index);
        recomputeStats();
    }

    function showQuestion(index) {
        if (!cards.length) return;
        currentIndex = Math.max(0, Math.min(cards.length - 1, index));
        visited.add(currentIndex);

        cards.forEach((card, i) => card.classList.toggle('is-hidden', i !== currentIndex));
        navDots.forEach((dot, i) => dot.classList.toggle('current', i === currentIndex));

        const card = cards[currentIndex];
        if (ehCount) ehCount.textContent = 'Question ' + (currentIndex + 1) + ' of ' + cards.length;
        if (ehMarks) ehMarks.textContent = card.dataset.marks + ' mark' + (card.dataset.marks === '1' ? '' : 's');
        if (ehType) ehType.textContent = card.dataset.type;
        if (prevBtn) prevBtn.disabled = currentIndex === 0;
        if (nextBtn) nextBtn.disabled = currentIndex === cards.length - 1;

        recomputeStats();
        try { sessionStorage.setItem(POS_KEY, String(currentIndex)); } catch (e) {}
        if (window.scrollY > 0) window.scrollTo(0, 0);
    }

    navDots.forEach((dot, i) => {
        dot.addEventListener('click', function(e) {
            e.preventDefault();
            showQuestion(i);
        });
    });
    if (prevBtn) prevBtn.addEventListener('click', () => showQuestion(currentIndex - 1));
    if (nextBtn) nextBtn.addEventListener('click', () => showQuestion(currentIndex + 1));

    // Pick up where this test was left off in this tab
    let startIndex = 0;
    try {
        const savedPos = parseInt(sessionStorage.getItem(POS_KEY), 10);
        if (savedPos >= 0 && savedPos < cards.length) startIndex = savedPos;
    } catch (e) {}
    showQuestion(startIndex);

    // ─── Auto-save on answer change ─────────────────────────
    let autoSaveTimer;
    let autoSaveInFlight = false;
    document.querySelectorAll('input[name^="answer["], textarea[name^="answer["]').forEach(el => {
        el.addEventListener('change', function() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(autoSave, 2000);
        });
    });

    function autoSave() {
        // Never auto-save after submission started, and never overlap requests
        if (isSubmitting || autoSaveInFlight) return;
        autoSaveInFlight = true;
        const form = document.getElementById('testForm');
        const formData = new FormData(form);
        formData.append('auto_save', '1');

        fetch('<?= $apiUrl ?>/submit_answer.php', {
            method: 'POST',
            body: formData
        })
        .catch(() => {})
        .finally(() => { autoSaveInFlight = false; });
    }

    // ─── Tab Switch Detection ───────────────────────────────
    let tabSwitchCount = 0;
    const tabWarning = document.getElementById('tabWarning');

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            // Tab switched away
            tabSwitchCount++;
            tabWarning.classList.add('show');
            setTimeout(() => tabWarning.classList.remove('show'), 3000);

            // Log to server
            fetch('<?= $apiUrl ?>/tab_switch.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf_token: '<?= getCsrfToken() ?>',
                    test_id: <?= $testId ?>,
                    submission_id: '<?= h($submission['id']) ?>',
                    switch_count: 1
                })
            }).catch(() => {});
        }
    });

    lucide.createIcons();
    </script>
</body>
</html>
