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

if ($isStopped) {
    echo '<!DOCTYPE html><html><head><title>Test Stopped</title><link rel="stylesheet" href="' . ASSETS_URL . '/css/student.css"></head><body>';
    echo '<div class="container" style="max-width:600px;margin:80px auto;text-align:center;">';
    echo '<div style="margin-bottom:16px;"><svg width="48" height="48" viewBox="0 0 20 20" fill="#BC2F32"><path d="M4 4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4zm2 1v10h8V5H6z"/></svg></div>';
    echo '<h1 style="font-size:1.5rem;margin-bottom:8px;">Test Stopped</h1>';
    echo '<p class="text-muted">This test has been ended by the admin. Please contact your instructor.</p>';
    echo '<a href="dashboard.php" class="btn btn-primary" style="margin-top:16px;">Back to Dashboard</a>';
    echo '</div></body></html>';
    exit;
}

if ($isPaused && !$hasProgress) {
    echo '<!DOCTYPE html><html><head><title>Test Paused</title><link rel="stylesheet" href="' . ASSETS_URL . '/css/student.css"></head><body>';
    echo '<div class="container" style="max-width:600px;margin:80px auto;text-align:center;">';
    echo '<div style="margin-bottom:16px;"><svg width="48" height="48" viewBox="0 0 20 20" fill="#826A00"><path d="M5 3a1 1 0 0 0-1 1v12a1 1 0 0 0 2 0V4a1 1 0 0 0-1-1zm10 0a1 1 0 0 0-1 1v12a1 1 0 0 0 2 0V4a1 1 0 0 0-1-1z"/></svg></div>';
    echo '<h1 style="font-size:1.5rem;margin-bottom:8px;">Test Paused</h1>';
    echo '<p class="text-muted">This test has been paused by the admin. It will be available again once resumed.</p>';
    echo '<a href="dashboard.php" class="btn btn-primary" style="margin-top:16px;">Back to Dashboard</a>';
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
    echo '<a href="dashboard.php" class="btn btn-primary" style="margin-top:20px;">Back to Dashboard</a>';
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
        echo '<a href="dashboard.php" class="btn btn-primary" style="margin-top:20px;">Back to Dashboard</a>';
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
    echo '<a href="dashboard.php" class="btn btn-primary" style="margin-top:20px;">Back</a>';
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
        .question-card { transition: border-color 0.2s; }
        .question-card.answered { border-left: 4px solid var(--accent); }
        .nav-dots { display: flex; flex-wrap: wrap; gap: 6px; margin: 12px 0; }
        .nav-dot {
            width: 32px; height: 32px; border-radius: 50%;
            border: 1px solid var(--gray-30);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.75rem; cursor: pointer; background: var(--white);
            transition: all 0.15s;
        }
        .nav-dot.answered { background: var(--accent); color: white; border-color: var(--accent); }
        .nav-dot.current { border-color: var(--gray-90); border-width: 2px; }
        .tab-switch-warning {
            position: fixed; top: 0; left: 0; right: 0;
            background: #FDE7E9; color: #BC2F32; text-align: center;
            padding: 8px; font-size: 0.8125rem; z-index: 999;
            transform: translateY(-100%); transition: transform 0.3s;
        }
        .tab-switch-warning.show { transform: translateY(0); }
    </style>
</head>
<body>
    <!-- Tab Switch Warning Banner -->
    <div class="tab-switch-warning" id="tabWarning"><?= icon('warning', 16, 'var(--red)') ?> Tab switch detected. This is being recorded.</div>

    <!-- Timer Bar with Icon -->
    <div class="timer-bar" id="timerBar">
        <div class="flex-center"><strong><?= h($test['title']) ?></strong></div>
        <div class="flex-center">
            <?= icon('timer', 16) ?>
            <span id="timerDisplay" class="timer-display <?= $remaining < 300 ? ($remaining < 60 ? 'danger' : 'warning') : '' ?>"
                  data-remaining="<?= $remaining ?>">
                <?= gmdate('H:i:s', $remaining) ?>
            </span>
            <span class="text-muted text-sm" style="margin-left:4px;">remaining</span>
        </div>
        <div class="flex-center" style="gap:4px;">
            <?= icon('file-text', 14) ?>
            <span style="font-size:0.8125rem;color:var(--gray-50);"><?= count($questions) ?> questions</span>
        </div>
    </div>

    <div class="test-container" style="margin-top:64px;">
        <!-- Question Navigator -->
        <div class="card mb-4">
            <div class="flex-center" style="gap:var(--space-2);font-size:0.8125rem;font-weight:500;color:var(--gray-60);margin-bottom:8px;">
                <?= icon('grid-3x3', 14) ?> Question Navigator
            </div>
            <div class="nav-dots" id="navDots">
                <?php foreach ($questions as $i => $q):
                    $answered = isset($savedAnswers[$q['id']]);
                ?>
                    <a href="#q<?= $q['id'] ?>" class="nav-dot <?= $answered ? 'answered' : '' ?>" data-qid="<?= $q['id'] ?>">
                        <?= $i + 1 ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php $apiUrl = BASE_URL . '/api'; ?>
        <form id="testForm" method="POST" action="<?= $apiUrl ?>/submit_answer.php">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
            <input type="hidden" name="test_id" value="<?= $testId ?>">
            <input type="hidden" name="submission_id" value="<?= h($submission['id']) ?>">
            <!-- Ask the API to redirect browsers back to the result screen after final submit.
                 Auto-save (fetch) requests ignore this — they keep getting JSON. -->
            <input type="hidden" name="redirect" value="1">

            <?php foreach ($questions as $index => $q):
                $qType = $q['type'];
                $options = json_decode((string)$q['options_json'], true) ?? [];
                $selected = $savedAnswers[$q['id']]['selected'] ?? '';
            ?>
            <div class="question-card <?= $selected ? 'answered' : '' ?>" id="q<?= $q['id'] ?>">
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
                        <?php foreach ($options as $opt):
                            $optKey = $opt['key'] ?? '';
                            $optText = $opt['text'] ?? $opt['value'] ?? '';
                            $isSelected = ($selected === $optKey);
                        ?>
                        <label class="option-label <?= $isSelected ? 'selected' : '' ?>">
                            <input type="radio" name="answer[<?= $q['id'] ?>]" value="<?= h($optKey) ?>"
                                   <?= $isSelected ? 'checked' : '' ?>
                                   onchange="this.closest('.option-label').classList.add('selected'); updateNavDot(<?= $q['id'] ?>)">
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

            <div style="text-align:center;padding:24px 0 48px;">
                <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                    <?= icon('check-circle', 18) ?> Submit Test
                </button>
            </div>
        </form>
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

    // ─── Question Navigator ─────────────────────────────────
    function updateNavDot(qId) {
        const dots = document.querySelectorAll('.nav-dot');
        dots.forEach(d => {
            if (parseInt(d.dataset.qid) === qId) {
                d.classList.add('answered');
            }
        });
        // Also update card border
        const card = document.getElementById('q' + qId);
        if (card) card.classList.add('answered');
    }

    // Highlight current question on scroll
    const questions = document.querySelectorAll('.question-card');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const qid = entry.target.id.replace('q', '');
                document.querySelectorAll('.nav-dot').forEach(d => {
                    d.classList.toggle('current', d.dataset.qid === qid);
                });
            }
        });
    }, { rootMargin: '-100px 0px -50% 0px' });
    questions.forEach(q => observer.observe(q));

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
