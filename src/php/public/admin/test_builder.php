<?php
/**
 * Test Builder — 3-Step Wizard
 * Step 1: Test Configuration & Batch Targeting
 * Step 2: Question Import (Manual + CSV) + Preview
 * Step 3: Publishing & IST Scheduling
 */
$pageTitle = 'Test Builder';
require_once __DIR__ . '/../../includes/admin_header.php';

$pdo = getDB();
$message = '';

// Wizard routing
$editTestId = (int)($_GET['edit_test'] ?? 0);
$wizardStep = (int)($_GET['step'] ?? 1);
if ($wizardStep < 1 || $wizardStep > 3) $wizardStep = 1;
if ($editTestId <= 0 && $wizardStep > 1) $wizardStep = 1;

// Load existing test
$editTest = null;
if ($editTestId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM tests WHERE id = ?");
    $stmt->execute([$editTestId]);
    $editTest = $stmt->fetch();
    if (!$editTest) { $editTestId = 0; $wizardStep = 1; }
}

// ─── POST Handler ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_test') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $duration = max(1, (int)($_POST['duration_minutes'] ?? 30));
        $totalMarks = max(0, (int)($_POST['total_marks'] ?? 0));
        $passingMarks = max(0, (int)($_POST['passing_marks'] ?? 0));
        $testType = trim($_POST['test_type'] ?? 'general');
        $shuffle = !empty($_POST['shuffle_questions']) ? 1 : 0;
        $instructions = trim($_POST['instructions'] ?? '');
        $negMarking = (float)($_POST['negative_marking'] ?? 0);
        $batchIds = array_filter(array_map('intval', $_POST['batch_ids'] ?? []));

        if (empty($title)) {
            $message = 'Test title is required.';
        } elseif (empty($batchIds)) {
            $message = 'Please select at least one batch.';
        } else {
            try {
                $pdo->beginTransaction();
                if ($editTestId > 0) {
                    $stmt = $pdo->prepare("UPDATE tests SET title=?, description=?, duration_minutes=?, total_marks=?, passing_marks=?, test_type=?, shuffle_questions=?, instructions=?, negative_marking=? WHERE id=?");
                    $stmt->execute([$title, $description, $duration, $totalMarks, $passingMarks, $testType, $shuffle, $instructions, $negMarking, $editTestId]);
                } else {
                    $primaryBatchId = reset($batchIds);
                    $stmt = $pdo->prepare("INSERT INTO tests (batch_id, title, description, duration_minutes, total_marks, passing_marks, test_type, shuffle_questions, instructions, negative_marking, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'upcoming', ?)");
                    $stmt->execute([$primaryBatchId, $title, $description, $duration, $totalMarks, $passingMarks, $testType, $shuffle, $instructions, $negMarking, $_SESSION['admin_id']]);
                    $editTestId = (int)$pdo->lastInsertId();
                }
                $pdo->prepare("DELETE FROM test_sections WHERE test_id = ?")->execute([$editTestId]);
                $ins = $pdo->prepare("INSERT INTO test_sections (test_id, batch_id) VALUES (?, ?)");
                foreach ($batchIds as $bid) { if ($bid > 0) $ins->execute([$editTestId, $bid]); }
                $pdo->commit();

                $stmt = $pdo->prepare("SELECT * FROM tests WHERE id = ?");
                $stmt->execute([$editTestId]);
                $editTest = $stmt->fetch();
                $message = 'Test saved. Now add questions.';
                $wizardStep = 2;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $message = 'Error: ' . h($e->getMessage());
            }
        }
    }

    elseif ($action === 'add_question' && !empty($_POST['test_id']) && !empty($_POST['question_text'])) {
        $testId = (int)$_POST['test_id'];
        $editTestId = $testId;
        $type = $_POST['question_type'] ?? 'mcq';
        $optionsJson = null; $correctAnswer = null;
        if ($type === 'mcq') {
            $rawOptions = $_POST['options'] ?? '';
            $options = [];
            if (is_string($rawOptions) && str_starts_with(trim($rawOptions), '[')) {
                $decoded = json_decode($rawOptions, true);
                if (is_array($decoded)) $options = $decoded;
            } elseif (is_string($rawOptions) && trim($rawOptions)) {
                foreach (explode("\n", $rawOptions) as $i => $line) {
                    $line = trim($line);
                    if ($line) $options[] = ['key' => chr(65 + $i), 'text' => $line];
                }
            }
            if (!empty($options)) { $optionsJson = json_encode($options); $correctAnswer = $_POST['correct_answer'] ?? ''; }
        }
        $pdo->prepare("INSERT INTO questions (test_id, type, question_text, options_json, correct_answer, marks, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$testId, $type, trim($_POST['question_text']), $optionsJson, $correctAnswer, max(1, (int)($_POST['marks'] ?? 1)), max(0, (int)($_POST['sort_order'] ?? 0))]);
        recalcTotalMarks($pdo, $testId);
        $message = 'Question added.';
        $wizardStep = 2;
    }

    elseif ($action === 'delete_question' && !empty($_POST['id'])) {
        $qid = (int)$_POST['id'];
        $qRow = $pdo->prepare("SELECT test_id FROM questions WHERE id = ?");
        $qRow->execute([$qid]); $qRow = $qRow->fetch();
        $pdo->prepare("DELETE FROM questions WHERE id = ?")->execute([$qid]);
        if ($qRow) recalcTotalMarks($pdo, (int)$qRow['test_id']);
        $message = 'Question deleted.';
        $editTestId = $qRow ? (int)$qRow['test_id'] : $editTestId;
        $wizardStep = 2;
    }

    elseif ($action === 'import_csv' && !empty($_POST['test_id']) && !empty($_FILES['csv_file']['tmp_name'])) {
        $testId = (int)$_POST['test_id'];
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if ($handle) {
            $imported = 0; $errors = []; $lineNum = 0;
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO questions (test_id, type, question_text, options_json, correct_answer, marks, sort_order) VALUES (?, 'mcq', ?, ?, ?, ?, ?)");
                while (($row = fgetcsv($handle)) !== false) {
                    $lineNum++;
                    if ($lineNum === 1 && preg_match('/question/i', $row[0] ?? '')) continue;
                    $qText = trim($row[0] ?? ''); $optA = trim($row[1] ?? ''); $optB = trim($row[2] ?? '');
                    $optC = trim($row[3] ?? ''); $optD = trim($row[4] ?? '');
                    $answer = strtoupper(trim($row[5] ?? '')); $marks = max(1, (int)($row[6] ?? 1));
                    if (empty($qText)) { $errors[] = "Line $lineNum: empty question"; continue; }
                    if (empty($optA) || empty($optB)) { $errors[] = "Line $lineNum: need >=2 options"; continue; }
                    if (!in_array($answer, ['A','B','C','D'])) { $errors[] = "Line $lineNum: invalid answer"; continue; }
                    $options = [];
                    if ($optA) $options[] = ['key' => 'A', 'text' => $optA];
                    if ($optB) $options[] = ['key' => 'B', 'text' => $optB];
                    if ($optC) $options[] = ['key' => 'C', 'text' => $optC];
                    if ($optD) $options[] = ['key' => 'D', 'text' => $optD];
                    $stmt->execute([$testId, $qText, json_encode($options), $answer, $marks, $lineNum]);
                    $imported++;
                }
                $pdo->commit();
                recalcTotalMarks($pdo, $testId);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Rolled back: ' . $e->getMessage(); $imported = 0;
            }
            fclose($handle);
            $msg = "Imported $imported question(s).";
            if (!empty($errors)) $msg .= ' Warnings: ' . implode('; ', array_slice($errors, 0, 5));
            $message = $msg; $editTestId = $testId; $wizardStep = 2;
        }
    }

    elseif ($action === 'publish_now' && !empty($_POST['id'])) {
        $pubId = (int)$_POST['id'];
        $dRow = $pdo->prepare("SELECT duration_minutes, title FROM tests WHERE id = ?");
        $dRow->execute([$pubId]); $dRow = $dRow->fetch();
        $duration = $dRow ? (int)$dRow['duration_minutes'] : 30;
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE tests SET status = 'active', start_time = NOW(), end_time = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ? AND (status = 'upcoming' OR status = 'scheduled')")->execute([$duration, $pubId]);
            $pdo->commit();
        } catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); }
        redirect('/admin/assessment_management.php?tab=live&toast=published&title=' . urlencode($dRow['title'] ?? ''));
    }

    elseif ($action === 'schedule_publish' && !empty($_POST['id'])) {
        $schedId = (int)$_POST['id'];
        $startTime = $_POST['start_time'] ?? ''; $endTime = $_POST['end_time'] ?? '';
        if (empty($startTime) || empty($endTime)) {
            $message = 'Start and end times are required.'; $wizardStep = 3;
        } else {
            try {
                $tz = new DateTimeZone('Asia/Kolkata');
                $startDT = new DateTime($startTime, $tz);
                $endDT = new DateTime($endTime, $tz);
                $now = new DateTime('now', $tz);
                if ($startDT < $now) { $message = 'Start time must be in the future (IST).'; $wizardStep = 3; }
                elseif ($endDT <= $startDT) { $message = 'End time must be after start time.'; $wizardStep = 3; }
                else {
                    $pdo->prepare("UPDATE tests SET status = 'scheduled', start_time = ?, end_time = ? WHERE id = ? AND (status = 'upcoming' OR status = 'scheduled')")
                         ->execute([$startDT->format('Y-m-d H:i:s'), $endDT->format('Y-m-d H:i:s'), $schedId]);
                    $sRow = $pdo->prepare("SELECT title FROM tests WHERE id = ?");
                    $sRow->execute([$schedId]); $sRow = $sRow->fetch();
                    redirect('/admin/assessment_management.php?tab=scheduled&toast=scheduled&title=' . urlencode($sRow['title'] ?? ''));
                }
            } catch (Exception $e) { $message = 'Invalid date format.'; $wizardStep = 3; }
        }
    }

    elseif ($action === 'reorder_question' && !empty($_POST['question_id']) && !empty($_POST['direction'])) {
        $qid = (int)$_POST['question_id']; $dir = $_POST['direction'] === 'up' ? 'up' : 'down';
        $tIdRe = (int)($_POST['test_id'] ?? 0);
        $cur = $pdo->prepare("SELECT id, sort_order FROM questions WHERE id = ?");
        $cur->execute([$qid]); $cur = $cur->fetch();
        if ($cur && $tIdRe > 0) {
            if ($dir === 'up') {
                $adj = $pdo->prepare("SELECT id, sort_order FROM questions WHERE test_id = ? AND (sort_order < ? OR (sort_order = ? AND id < ?)) ORDER BY sort_order DESC, id DESC LIMIT 1");
                $adj->execute([$tIdRe, $cur['sort_order'], $cur['sort_order'], $cur['id']]);
            } else {
                $adj = $pdo->prepare("SELECT id, sort_order FROM questions WHERE test_id = ? AND (sort_order > ? OR (sort_order = ? AND id > ?)) ORDER BY sort_order ASC, id ASC LIMIT 1");
                $adj->execute([$tIdRe, $cur['sort_order'], $cur['sort_order'], $cur['id']]);
            }
            $adj = $adj->fetch();
            if ($adj) {
                $pdo->prepare("UPDATE questions SET sort_order = ? WHERE id = ?")->execute([(int)$adj['sort_order'], $qid]);
                $pdo->prepare("UPDATE questions SET sort_order = ? WHERE id = ?")->execute([(int)$cur['sort_order'], $adj['id']]);
                $message = 'Question order updated.';
            }
        }
        $wizardStep = 2;
    }

    elseif ($action === 'extend_timer' && !empty($_POST['submission_id'])) {
        $ext = (int)($_POST['extend_minutes'] ?? 5);
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE submissions SET timer_extended_minutes = timer_extended_minutes + ? WHERE id = ?")->execute([$ext, (int)$_POST['submission_id']]);
            $pdo->prepare("INSERT INTO tab_switch_logs (submission_id, switch_count, type, metadata) VALUES (?, 0, 'timer_extend', ?)")->execute([(int)$_POST['submission_id'], json_encode(['extended_by' => $ext])]);
            $pdo->commit();
            $message = "Timer extended by {$ext} minutes.";
        } catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $message = 'Failed.'; }
    }

    elseif ($action === 'adjust_end_time' && !empty($_POST['id'])) {
        $adjId = (int)$_POST['id']; $adjMins = (int)($_POST['adjust_minutes'] ?? 0);
        if ($adjMins != 0) {
            $pdo->prepare("UPDATE tests SET end_time = DATE_ADD(COALESCE(end_time, NOW()), INTERVAL ? MINUTE) WHERE id = ?")->execute([$adjMins, $adjId]);
            $message = ($adjMins > 0 ? "Extended by {$adjMins} min" : "Shrunk by " . abs($adjMins) . " min") . '. Timers sync on next poll.';
        }
    }

    elseif ($action === 'delete_test' && !empty($_POST['id'])) {
        $delId = (int)$_POST['id'];
        $hasSub = $pdo->prepare("SELECT COUNT(*) FROM submissions WHERE test_id = ?");
        $hasSub->execute([$delId]); $hasSub = (int)$hasSub->fetchColumn();
        if ($hasSub > 0) {
            $message = "Cannot delete: $hasSub submission(s) exist. Force end first.";
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM questions WHERE test_id = ?")->execute([$delId]);
                $pdo->prepare("DELETE FROM test_sections WHERE test_id = ?")->execute([$delId]);
                $pdo->prepare("DELETE FROM tests WHERE id = ?")->execute([$delId]);
                $pdo->commit();
                $message = 'Test deleted.';
                if ($editTestId === $delId) { $editTestId = 0; $editTest = null; $wizardStep = 1; }
            } catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $message = 'Delete failed.'; }
        }
    }
}

// Re-fetch editTest if POST handler set $editTestId (GET was empty on POST)
if ($editTestId > 0 && $editTest === null) {
    $stmt = $pdo->prepare("SELECT * FROM tests WHERE id = ?");
    $stmt->execute([$editTestId]);
    $editTest = $stmt->fetch();
    if (!$editTest) { $editTestId = 0; $wizardStep = 1; }
}

// ─── Helpers ───────────────────────────────────────────────
function recalcTotalMarks(PDO $pdo, int $testId): void {
    $sum = $pdo->prepare("SELECT COALESCE(SUM(marks), 0) FROM questions WHERE test_id = ?");
    $sum->execute([$testId]);
    $pdo->prepare("UPDATE tests SET total_marks = ? WHERE id = ?")->execute([(int)$sum->fetchColumn(), $testId]);
}

// ─── Data Loading ──────────────────────────────────────────
$colleges = $pdo->query("SELECT id, name FROM colleges ORDER BY name")->fetchAll();

$tests = $pdo->query("
    SELECT t.*, b.name AS batch_name, c.name AS course_name, cl.name AS college_name,
           (SELECT COUNT(*) FROM questions WHERE test_id = t.id) AS question_count,
           (SELECT COUNT(*) FROM submissions WHERE test_id = t.id AND status = 'submitted') AS submitted_count,
           (SELECT GROUP_CONCAT(DISTINCT b2.name ORDER BY b2.name SEPARATOR ', ')
            FROM test_sections ts2 JOIN batches b2 ON b2.id = ts2.batch_id WHERE ts2.test_id = t.id) AS assigned_batches
    FROM tests t JOIN batches b ON b.id = t.batch_id JOIN courses c ON c.id = b.course_id JOIN colleges cl ON cl.id = c.college_id
    ORDER BY t.created_at DESC
")->fetchAll();

$questions = [];
$assignedBatchIds = [];
$runningSubmissions = [];

if ($editTestId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE test_id = ? ORDER BY sort_order, id");
    $stmt->execute([$editTestId]); $questions = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT batch_id FROM test_sections WHERE test_id = ?");
    $stmt->execute([$editTestId]); $assignedBatchIds = array_column($stmt->fetchAll(), 'batch_id');

    $stmt = $pdo->prepare("SELECT s.id, s.started_at, s.timer_extended_minutes, st.name AS student_name, st.email, t.duration_minutes FROM submissions s JOIN students st ON st.id = s.student_id JOIN tests t ON t.id = s.test_id WHERE s.test_id = ? AND s.status = 'in_progress' ORDER BY s.started_at");
    $stmt->execute([$editTestId]); $runningSubmissions = $stmt->fetchAll();

    if ($editTest && (int)$editTest['total_marks'] === 0 && !empty($questions)) {
        recalcTotalMarks($pdo, $editTestId);
        $editTest['total_marks'] = array_sum(array_column($questions, 'marks'));
    }
}

// ─── HTML Template: Opening + Flash + Stepper ──────────────
?>
<style>
.wizard-stepper{display:flex;gap:0;margin:0 0 2rem;list-style:none;padding:0;counter-reset:step}
.wizard-stepper li{flex:1;text-align:center;padding:1rem .5rem;position:relative;background:var(--surface);border:1px solid var(--border-light);counter-increment:step;transition:all .2s}
.wizard-stepper li:first-child{border-radius:var(--radius-lg) 0 0 var(--radius-lg)}
.wizard-stepper li:last-child{border-radius:0 var(--radius-lg) var(--radius-lg) 0}
.wizard-stepper li.active{background:var(--accent);color:#fff;border-color:var(--accent)}
.wizard-stepper li.completed{background:var(--success);color:#fff;border-color:var(--success)}
.wizard-stepper li::before{content:counter(step);display:inline-flex;width:28px;height:28px;border-radius:50%;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;margin-bottom:.4rem;background:rgba(0,0,0,.08)}
.wizard-stepper li.active::before,.wizard-stepper li.completed::before{background:rgba(255,255,255,.25)}
.wizard-stepper li span{display:block;font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.5px}
.wizard-stepper li .step-subtitle{font-size:.65rem;font-weight:400;text-transform:none;letter-spacing:0;margin-top:.15rem;opacity:.8}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
@media(max-width:768px){.form-grid{grid-template-columns:1fr}}
.form-group{margin-bottom:1rem}
.form-group label{display:block;font-weight:600;font-size:.85rem;margin-bottom:.35rem;color:var(--text)}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:.6rem .8rem;border:1px solid var(--border-light);border-radius:var(--radius-md);font-size:.9rem;background:var(--surface);color:var(--text);transition:border .2s}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow)}
.form-group textarea{min-height:80px;resize:vertical}
.form-group .hint{font-size:.75rem;color:var(--text-muted);margin-top:.2rem}
.btn-group{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1.5rem}
.csv-preview-box{background:var(--surface-alt);border:1px solid var(--border-light);border-radius:var(--radius-lg);padding:1rem;max-height:400px;overflow:auto}
.csv-preview-box table{width:100%;border-collapse:collapse;font-size:.8rem}
.csv-preview-box th,.csv-preview-box td{padding:.4rem .6rem;border-bottom:1px solid var(--border-light);text-align:left}
.csv-preview-box th{font-weight:700;background:var(--surface);position:sticky;top:0}
.csv-preview-box tr[data-valid="false"]{background:var(--danger-light)}
.q-card{background:var(--surface);border:1px solid var(--border-light);border-radius:var(--radius-lg);padding:1rem;margin-bottom:.75rem;display:flex;gap:1rem;align-items:flex-start;transition:box-shadow .2s}
.q-card:hover{box-shadow:0 2px 8px rgba(0,0,0,.06)}
.q-card .q-num{font-weight:700;font-size:1.1rem;color:var(--accent);min-width:28px}
.q-card .q-body{flex:1}
.q-card .q-meta{font-size:.75rem;color:var(--text-muted);margin-top:.3rem}
.q-card .q-actions{display:flex;flex-direction:column;gap:.3rem}
.reorder-arrow{width:28px;height:28px;display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--border-light);border-radius:var(--radius-sm);background:var(--surface);cursor:pointer;font-size:.8rem;transition:all .15s}
.reorder-arrow:hover{background:var(--accent);color:#fff;border-color:var(--accent)}
.empty-state{text-align:center;padding:3rem 1rem;color:var(--text-muted)}
.empty-state svg{width:48px;height:48px;margin-bottom:1rem;opacity:.4}
.batch-check{display:flex;align-items:center;gap:.5rem;padding:.4rem .6rem;border-radius:var(--radius-sm);border:1px solid var(--border-light);margin:.2rem 0;cursor:pointer;transition:all .15s}
.batch-check:hover{border-color:var(--accent)}
.batch-check.selected{border-color:var(--accent);background:var(--accent-glow)}
.batch-check input{accent-color:var(--accent)}
.section-chips{display:flex;flex-wrap:wrap;gap:.3rem;margin-top:.3rem}
.section-chip{font-size:.65rem;padding:.15rem .45rem;border-radius:99px;background:var(--accent-glow);color:var(--accent);font-weight:600}
.running-badge{display:inline-flex;align-items:center;gap:.3rem;font-size:.75rem;padding:.2rem .6rem;border-radius:99px;background:var(--success-light);color:var(--success);font-weight:600}
.running-badge::before{content:'';width:6px;height:6px;border-radius:50%;background:currentColor;animation:pulse-dot 1.5s infinite}
@keyframes pulse-dot{0%,100%{opacity:1}50%{opacity:.3}}
.sched-time{font-family:var(--font-mono);font-size:.85rem;color:var(--accent)}
</style>

<?php if ($message): ?>
<div style="padding:.8rem 1.2rem;border-radius:var(--radius-md);margin-bottom:1.5rem;font-size:.9rem;font-weight:500;background:<?= str_contains(strtolower($message), 'error') || str_contains(strtolower($message), 'cannot') ? 'var(--danger-light);color:var(--danger)' : 'var(--success-light);color:var(--success)' ?>;">
    <?= h($message) ?>
</div>
<?php endif; ?>

<ul class="wizard-stepper">
    <li class="<?= $wizardStep === 1 ? 'active' : ($wizardStep > 1 ? 'completed' : '') ?>">
        <span>Configure</span>
        <div class="step-subtitle">Title, batches & settings</div>
    </li>
    <li class="<?= $wizardStep === 2 ? 'active' : ($wizardStep > 2 ? 'completed' : '') ?>">
        <span>Questions</span>
        <div class="step-subtitle">Add, import & preview</div>
    </li>
    <li class="<?= $wizardStep === 3 ? 'active' : '' ?>">
        <span>Publish</span>
        <div class="step-subtitle">Schedule or go live</div>
    </li>
</ul>

<!-- ════════════════════════════════════════════════════════════ -->
<!-- STEP 1: Test Configuration                                  -->
<!-- ════════════════════════════════════════════════════════════ -->
<?php if ($wizardStep === 1): ?>
<div class="card">
    <h2 style="margin:0 0 .3rem;font-size:1.3rem"><?= $editTestId > 0 ? 'Edit Test' : 'Create New Test' ?></h2>
    <p style="color:var(--text-muted);font-size:.85rem;margin:0 0 1.5rem"><?= $editTestId > 0 ? 'Update configuration and batch assignments' : 'Configure your test details and target batches' ?></p>

    <form method="POST" id="step1Form">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save_test">

        <div class="form-grid">
            <div class="form-group" style="grid-column:1/-1">
                <label>Test Title *</label>
                <input type="text" name="title" value="<?= h($editTest['title'] ?? '') ?>" placeholder="e.g. Mid-term Mathematics Exam" required>
            </div>

            <div class="form-group" style="grid-column:1/-1">
                <label>Description</label>
                <textarea name="description" placeholder="Optional description for students"><?= h($editTest['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label>College *</label>
                <select id="collegeSelect" required>
                    <option value="">— Select College —</option>
                    <?php foreach ($colleges as $cl): ?>
                    <option value="<?= $cl['id'] ?>" <?= ($editTest && (int)($tests[array_search($editTestId, array_column($tests,'id'))]['college_id'] ?? 0) == $cl['id']) ? 'selected' : '' ?>><?= h($cl['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Course *</label>
                <select id="courseSelect" required disabled>
                    <option value="">— Select Course —</option>
                </select>
            </div>

            <div class="form-group">
                <label>Target Batches *</label>
                <div id="batchList" style="border:1px solid var(--border-light);border-radius:var(--radius-md);padding:.5rem;max-height:200px;overflow:auto;min-height:44px">
                    <div class="empty-state" style="padding:1rem;font-size:.8rem">Select a college and course first</div>
                </div>
                <p class="hint">Select one or more batches this test is assigned to</p>
            </div>

            <div class="form-group">
                <label>Test Type</label>
                <select name="test_type">
                    <?php foreach (['general' => 'General', 'mock' => 'Mock Test', 'practice' => 'Practice', 'placement' => 'Placement'] as $val => $lbl): ?>
                    <option value="<?= $val ?>" <?= ($editTest['test_type'] ?? 'general') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Duration (minutes) *</label>
                <input type="number" name="duration_minutes" value="<?= $editTest['duration_minutes'] ?? 60 ?>" min="1" max="300" required>
            </div>

            <div class="form-group">
                <label>Total Marks (auto-calculated)</label>
                <input type="number" name="total_marks" value="<?= $editTest['total_marks'] ?? 0 ?>" readonly style="background:var(--surface-alt)">
                <p class="hint">Recalculated when questions are added/removed</p>
            </div>

            <div class="form-group">
                <label>Passing Marks</label>
                <input type="number" name="passing_marks" value="<?= $editTest['passing_marks'] ?? 0 ?>" min="0">
            </div>

            <div class="form-group">
                <label>Negative Marking</label>
                <input type="number" name="negative_marking" value="<?= $editTest['negative_marking'] ?? 0 ?>" min="0" max="10" step="0.25">
                <p class="hint">Marks deducted per wrong answer (0 = off)</p>
            </div>

            <div class="form-group">
                <label style="display:flex;align-items:center;gap:.5rem">
                    <input type="checkbox" name="shuffle_questions" value="1" <?= ($editTest['shuffle_questions'] ?? 0) ? 'checked' : '' ?> style="width:auto">
                    Shuffle question order for each student
                </label>
            </div>

            <div class="form-group" style="grid-column:1/-1">
                <label>Instructions</label>
                <textarea name="instructions" placeholder="Optional instructions shown before test starts"><?= h($editTest['instructions'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn btn-primary" style="padding:.7rem 2rem">
                <?= $editTestId > 0 ? 'Save & Continue' : 'Create Test' ?> →
            </button>
            <?php if ($editTestId > 0): ?>
            <a href="?step=1" class="btn btn-secondary">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
(function(){
    const collegeSel = document.getElementById('collegeSelect');
    const courseSel = document.getElementById('courseSelect');
    const batchList = document.getElementById('batchList');
    const assignedIds = <?= json_encode(array_map('intval', $assignedBatchIds)) ?>;

    async function loadCourses(collegeId) {
        courseSel.innerHTML = '<option value="">Loading…</option>';
        courseSel.disabled = true;
        batchList.innerHTML = '<div class="empty-state" style="padding:1rem;font-size:.8rem">Loading…</div>';
        if (!collegeId) { courseSel.innerHTML = '<option value="">— Select Course —</option>'; batchList.innerHTML = '<div class="empty-state" style="padding:1rem;font-size:.8rem">Select a college first</div>'; return; }
        try {
            const r = await fetch('/api/get_courses.php?college_id=' + collegeId);
            const data = await r.json();
            // API returns a bare array (signup.php consumes it that way too)
            const courses = Array.isArray(data) ? data : (data.courses || []);
            courseSel.innerHTML = '<option value="">— Select Course —</option>';
            courses.forEach(c => { courseSel.innerHTML += '<option value="'+c.id+'">'+c.name+'</option>'; });
            courseSel.disabled = false;
        } catch(e) { courseSel.innerHTML = '<option value="">Error loading</option>'; }
    }

    async function loadBatches(courseId) {
        batchList.innerHTML = '<div class="empty-state" style="padding:1rem;font-size:.8rem">Loading…</div>';
        if (!courseId) { batchList.innerHTML = '<div class="empty-state" style="padding:1rem;font-size:.8rem">Select a course first</div>'; return; }
        try {
            const r = await fetch('/api/get_batches.php?course_id=' + courseId);
            const data = await r.json();
            // API returns a bare array — normalize before use
            const batches = Array.isArray(data) ? data : (data.batches || []);
            if (!batches.length) { batchList.innerHTML = '<div class="empty-state" style="padding:1rem;font-size:.8rem">No batches for this course</div>'; return; }
            batchList.innerHTML = '';
            batches.forEach(b => {
                const checked = assignedIds.includes(parseInt(b.id)) ? 'checked' : '';
                const sec = b.section_name ? '<span class="section-chip">'+b.section_name+'</span>' : '';
                batchList.innerHTML += '<label class="batch-check '+checked.replace('checked','selected')+'"><input type="checkbox" name="batch_ids[]" value="'+b.id+'" '+checked+'><span>'+b.name+'</span>'+sec+'</label>';
            });
            batchList.querySelectorAll('input[type=checkbox]').forEach(cb => {
                cb.addEventListener('change', function(){ this.closest('.batch-check').classList.toggle('selected', this.checked); });
            });
        } catch(e) { batchList.innerHTML = '<div class="empty-state" style="padding:1rem;font-size:.8rem">Error loading batches</div>'; }
    }

    collegeSel.addEventListener('change', function(){ loadCourses(this.value); loadBatches(''); });
    courseSel.addEventListener('change', function(){ loadBatches(this.value); });

    // On load: if college already selected, load courses
    if (collegeSel.value) { loadCourses(collegeSel.value); }
})();
</script>
<?php endif; ?>

<!-- ════════════════════════════════════════════════════════════ -->
<!-- STEP 2: Questions                                           -->
<!-- ════════════════════════════════════════════════════════════ -->
<?php if ($wizardStep === 2 && $editTestId > 0): ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem">
    <div>
        <h2 style="margin:0;font-size:1.3rem">Questions (<?= count($questions) ?>)</h2>
        <p style="color:var(--text-muted);font-size:.8rem;margin:.2rem 0 0">Total: <?= $editTest['total_marks'] ?? 0 ?> marks</p>
    </div>
    <div class="btn-group" style="margin:0">
        <a href="?edit_test=<?= $editTestId ?>&step=1" class="btn btn-secondary btn-sm">← Back to Config</a>
        <a href="?edit_test=<?= $editTestId ?>&step=2&view=reorder" class="btn btn-secondary btn-sm">↕ Reorder</a>
        <a href="?edit_test=<?= $editTestId ?>&step=3" class="btn btn-primary btn-sm">Publish →</a>
    </div>
</div>

<?php if (!empty($runningSubmissions)): ?>
<div style="background:var(--warning-light);border:1px solid var(--warning);border-radius:var(--radius-md);padding:.8rem 1rem;margin-bottom:1rem">
    <strong style="color:var(--warning)">⚠ <?= count($runningSubmissions) ?> student(s) currently taking this test</strong>
    <p style="margin:.3rem 0 0;font-size:.8rem">Changes to questions won't affect running sessions.</p>
</div>
<?php endif; ?>

<!-- Reorder View -->
<?php if (($_GET['view'] ?? '') === 'reorder'): ?>
<div class="card">
    <h3 style="margin:0 0 1rem">Reorder Questions</h3>
    <?php if (empty($questions)): ?>
    <p style="color:var(--text-muted)">No questions to reorder.</p>
    <?php else: ?>
    <?php foreach ($questions as $i => $q): ?>
    <div class="q-card">
        <div class="q-num"><?= $i + 1 ?></div>
        <div class="q-body">
            <div style="font-weight:600"><?= h(mb_substr($q['question_text'], 0, 120)) ?><?= mb_strlen($q['question_text']) > 120 ? '…' : '' ?></div>
            <div class="q-meta"><?= strtoupper($q['type']) ?> · <?= $q['marks'] ?> mark<?= $q['marks'] != 1 ? 's' : '' ?></div>
        </div>
        <div class="q-actions">
            <?php if ($i > 0): ?>
            <form method="POST" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="reorder_question">
                <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                <input type="hidden" name="test_id" value="<?= $editTestId ?>">
                <input type="hidden" name="direction" value="up">
                <button type="submit" class="reorder-arrow" title="Move up">▲</button>
            </form>
            <?php endif; ?>
            <?php if ($i < count($questions) - 1): ?>
            <form method="POST" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="reorder_question">
                <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                <input type="hidden" name="test_id" value="<?= $editTestId ?>">
                <input type="hidden" name="direction" value="down">
                <button type="submit" class="reorder-arrow" title="Move down">▼</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    <div class="btn-group">
        <a href="?edit_test=<?= $editTestId ?>&step=2" class="btn btn-primary">← Back to Questions</a>
    </div>
</div>

<!-- Normal Question List + Add Form -->
<?php else: ?>

<!-- Question List -->
<?php if (empty($questions)): ?>
<div class="empty-state card">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <p style="font-size:1rem;font-weight:600;margin:0 0 .3rem">No questions yet</p>
    <p style="font-size:.85rem">Add questions manually below or import a CSV file.</p>
</div>
<?php else: ?>
<div style="margin-bottom:1.5rem">
    <?php foreach ($questions as $i => $q): ?>
    <div class="q-card">
        <div class="q-num"><?= $i + 1 ?></div>
        <div class="q-body">
            <div style="font-weight:600"><?= h(mb_substr($q['question_text'], 0, 150)) ?><?= mb_strlen($q['question_text']) > 150 ? '…' : '' ?></div>
            <div class="q-meta">
                <?= strtoupper($q['type']) ?> · <?= $q['marks'] ?> mark<?= $q['marks'] != 1 ? 's' : '' ?>
                <?php if ($q['options_json']): $opts = json_decode($q['options_json'], true) ?: []; ?>
                 · <?= count($opts) ?> option<?= count($opts) != 1 ? 's' : '' ?>
                 · Answer: <?= h($q['correct_answer'] ?? '—') ?>
                <?php endif; ?>
            </div>
        </div>
        <form method="POST" onsubmit="return confirm('Delete this question?')">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete_question">
            <input type="hidden" name="id" value="<?= $q['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm" title="Delete">✕</button>
        </form>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Manual Add Question -->
<div class="card" style="margin-bottom:1.5rem">
    <h3 style="margin:0 0 1rem">Add Question Manually</h3>
    <form method="POST" id="addQuestionForm">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add_question">
        <input type="hidden" name="test_id" value="<?= $editTestId ?>">

        <div class="form-group">
            <label>Question Text *</label>
            <textarea name="question_text" placeholder="Enter the question" required style="min-height:60px"></textarea>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label>Question Type</label>
                <select name="question_type" id="qType">
                    <option value="mcq">Multiple Choice (MCQ)</option>
                    <option value="true_false">True / False</option>
                    <option value="short_answer">Short Answer</option>
                </select>
            </div>
            <div class="form-group">
                <label>Marks</label>
                <input type="number" name="marks" value="1" min="1" max="100">
            </div>
        </div>

        <div id="mcqOptions">
            <div class="form-group">
                <label>Options (one per line)</label>
                <textarea name="options" placeholder="Option A&#10;Option B&#10;Option C&#10;Option D" rows="4"></textarea>
                <p class="hint">One option per line. Lines are auto-labeled A, B, C, D…</p>
            </div>
            <div class="form-group">
                <label>Correct Answer</label>
                <select name="correct_answer">
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                    <option value="D">D</option>
                </select>
            </div>
        </div>

        <input type="hidden" name="sort_order" value="<?= count($questions) + 1 ?>">
        <button type="submit" class="btn btn-primary">Add Question</button>
    </form>
</div>

<!-- CSV Import -->
<div class="card">
    <h3 style="margin:0 0 .3rem">Import from CSV</h3>
    <p style="color:var(--text-muted);font-size:.8rem;margin:0 0 1rem">
        CSV columns: <code>Question, Option A, Option B, Option C, Option D, Answer (A/B/C/D), Marks</code>
    </p>
    <form method="POST" enctype="multipart/form-data" id="csvForm">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="import_csv">
        <input type="hidden" name="test_id" value="<?= $editTestId ?>">
        <div class="form-group">
            <input type="file" name="csv_file" accept=".csv" id="csvInput" required>
        </div>
        <div id="csvPreviewArea" style="display:none;margin-bottom:1rem"></div>
        <div class="btn-group">
            <button type="submit" class="btn btn-primary" id="csvSubmitBtn" disabled>Import Questions</button>
        </div>
    </form>
</div>

<script>
(function(){
    const qType = document.getElementById('qType');
    const mcqOpts = document.getElementById('mcqOptions');
    qType.addEventListener('change', function(){
        mcqOpts.style.display = this.value === 'mcq' ? 'block' : 'none';
    });
})();
</script>
<?php endif; ?>
<?php endif; ?>

<!-- ════════════════════════════════════════════════════════════ -->
<!-- STEP 3: Publishing & Scheduling                             -->
<!-- ════════════════════════════════════════════════════════════ -->
<?php if ($wizardStep === 3 && $editTestId > 0): ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem">
    <div>
        <h2 style="margin:0;font-size:1.3rem">Publish Test</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin:.2rem 0 0">Go live immediately or schedule for later (IST)</p>
    </div>
    <a href="?edit_test=<?= $editTestId ?>&step=2" class="btn btn-secondary btn-sm">← Back to Questions</a>
</div>

<!-- Test Summary -->
<div class="card" style="margin-bottom:1.5rem">
    <div class="form-grid" style="gap:.8rem 2rem">
        <div>
            <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;font-weight:600">Title</div>
            <div style="font-size:1.05rem;font-weight:600"><?= h($editTest['title']) ?></div>
        </div>
        <div>
            <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;font-weight:600">Status</div>
            <span class="badge badge-<?= ($editTest['status'] === 'active' ? 'live' : ($editTest['status'] === 'scheduled' ? 'upcoming' : 'upcoming')) ?>"><?= ucfirst(h($editTest['status'])) ?></span>
        </div>
        <div>
            <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;font-weight:600">Questions</div>
            <div style="font-weight:600"><?= count($questions) ?></div>
        </div>
        <div>
            <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;font-weight:600">Total Marks</div>
            <div style="font-weight:600"><?= $editTest['total_marks'] ?? 0 ?></div>
        </div>
        <div>
            <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;font-weight:600">Duration</div>
            <div style="font-weight:600"><?= $editTest['duration_minutes'] ?> min</div>
        </div>
        <div>
            <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;font-weight:600">Passing</div>
            <div style="font-weight:600"><?= $editTest['passing_marks'] ?? 0 ?> marks</div>
        </div>
    </div>
    <div style="margin-top:.8rem">
        <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;font-weight:600">Assigned Batches</div>
        <div class="section-chips" style="margin-top:.2rem">
            <?php
            $bStmt = $pdo->prepare("SELECT b.name, b2.name AS section_name FROM test_sections ts JOIN batches b ON b.id = ts.batch_id LEFT JOIN batch_sections b2 ON b2.id = b.section_id WHERE ts.test_id = ?");
            $bStmt->execute([$editTestId]);
            foreach ($bStmt->fetchAll() as $b): ?>
            <span class="section-chip"><?= h($b['name']) ?><?= $b['section_name'] ? ' (' . h($b['section_name']) . ')' : '' ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!empty($questions)): ?>
    <details style="margin-top:1rem">
        <summary style="cursor:pointer;font-weight:600;font-size:.85rem;color:var(--accent)">Preview Questions (<?= count($questions) ?>)</summary>
        <div style="margin-top:.8rem">
            <?php foreach ($questions as $i => $q): ?>
            <div style="padding:.5rem 0;border-bottom:1px solid var(--border-light)">
                <strong><?= $i+1 ?>.</strong> <?= h(mb_substr($q['question_text'], 0, 200)) ?>
                <?php if ($q['options_json']): $opts = json_decode($q['options_json'], true) ?: []; ?>
                <div style="display:flex;gap:1rem;margin-top:.3rem;flex-wrap:wrap">
                    <?php foreach ($opts as $o): ?>
                    <span style="font-size:.8rem;color:<?= ($o['key'] ?? '') === ($q['correct_answer'] ?? '') ? 'var(--success)' : 'var(--text-muted)' ?>"><?= h($o['key'] ?? '') ?>. <?= h($o['text'] ?? '') ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </details>
    <?php endif; ?>
</div>

<?php if (count($questions) === 0): ?>
<div class="card" style="text-align:center;padding:2rem">
    <p style="color:var(--warning);font-weight:600;margin:0 0 .3rem">⚠ No questions added yet</p>
    <p style="color:var(--text-muted);font-size:.85rem">You need at least 1 question to publish. <a href="?edit_test=<?= $editTestId ?>&step=2">Add questions →</a></p>
</div>
<?php else: ?>

<!-- Publish Immediately -->
<div class="card" style="margin-bottom:1.5rem">
    <h3 style="margin:0 0 .8rem">⚡ Publish Immediately</h3>
    <p style="color:var(--text-muted);font-size:.85rem;margin:0 0 1rem">Test goes live now. Students can start answering immediately.</p>
    <form method="POST" onsubmit="return confirm('Publish this test now? Students will be able to start immediately.')">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="publish_now">
        <input type="hidden" name="id" value="<?= $editTestId ?>">
        <button type="submit" class="btn btn-primary" style="padding:.7rem 2rem">Publish Now</button>
    </form>
</div>

<!-- Schedule -->
<div class="card" style="margin-bottom:1.5rem">
    <h3 style="margin:0 0 .3rem">📅 Schedule for Later</h3>
    <p style="color:var(--text-muted);font-size:.85rem;margin:0 0 1rem">Set start and end times in IST (Indian Standard Time, UTC+5:30)</p>
    <form method="POST" id="scheduleForm">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="schedule_publish">
        <input type="hidden" name="id" value="<?= $editTestId ?>">

        <div class="form-grid">
            <div class="form-group">
                <label>Start Time (IST) *</label>
                <input type="datetime-local" name="start_time" id="startTime" required>
                <p class="hint">IST = UTC + 5:30</p>
            </div>
            <div class="form-group">
                <label>End Time (IST) *</label>
                <input type="datetime-local" name="end_time" id="endTime" required>
                <p class="hint">Must be after start time</p>
            </div>
        </div>

        <div style="background:var(--surface-alt);border-radius:var(--radius-md);padding:.8rem 1rem;margin-bottom:1rem">
            <div style="font-size:.8rem;color:var(--text-muted)">Quick Presets</div>
            <div class="btn-group" style="margin-top:.4rem">
                <button type="button" class="btn btn-secondary btn-sm" onclick="setSchedulePreset(1)">+1 Hour</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="setSchedulePreset(2)">+2 Hours</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="setSchedulePreset(6)">+6 Hours</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="setSchedulePreset(24)">+1 Day</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="setScheduleDuration(<?= $editTest['duration_minutes'] ?? 60 ?>)">Use Test Duration</button>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="padding:.7rem 2rem">Schedule Test</button>
    </form>
</div>

<!-- Running Submissions -->
<?php if (!empty($runningSubmissions)): ?>
<div class="card">
    <h3 style="margin:0 0 1rem">Running Sessions (<?= count($runningSubmissions) ?>)</h3>
    <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:.85rem">
        <thead><tr>
            <th style="text-align:left;padding:.5rem;border-bottom:2px solid var(--border-light)">Student</th>
            <th style="text-align:left;padding:.5rem;border-bottom:2px solid var(--border-light)">Started</th>
            <th style="text-align:left;padding:.5rem;border-bottom:2px solid var(--border-light)">Elapsed</th>
            <th style="text-align:left;padding:.5rem;border-bottom:2px solid var(--border-light)">Extra Time</th>
            <th style="text-align:left;padding:.5rem;border-bottom:2px solid var(--border-light)">Action</th>
        </tr></thead>
        <tbody>
        <?php foreach ($runningSubmissions as $sub): ?>
        <?php
            $tz = new DateTimeZone('Asia/Kolkata');
            $started = new DateTime($sub['started_at'], $tz);
            $now = new DateTime('now', $tz);
            $elapsed = $now->getTimestamp() - $started->getTimestamp();
            $elapsedMins = floor($elapsed / 60);
            $elapsedSecs = $elapsed % 60;
        ?>
        <tr>
            <td style="padding:.5rem;border-bottom:1px solid var(--border-light)">
                <div style="font-weight:600"><?= h($sub['student_name']) ?></div>
                <div style="font-size:.75rem;color:var(--text-muted)"><?= h($sub['email']) ?></div>
            </td>
            <td style="padding:.5rem;border-bottom:1px solid var(--border-light)"><span class="sched-time"><?= $started->format('H:i:s') ?></span></td>
            <td style="padding:.5rem;border-bottom:1px solid var(--border-light)"><span class="running-badge"><?= $elapsedMins ?>m <?= str_pad($elapsedSecs, 2, '0', STR_PAD_LEFT) ?>s</span></td>
            <td style="padding:.5rem;border-bottom:1px solid var(--border-light)"><?= $sub['timer_extended_minutes'] > 0 ? '+' . $sub['timer_extended_minutes'] . ' min' : '—' ?></td>
            <td style="padding:.5rem;border-bottom:1px solid var(--border-light)">
                <form method="POST" style="display:flex;gap:.3rem;align-items:center">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="extend_timer">
                    <input type="hidden" name="submission_id" value="<?= $sub['id'] ?>">
                    <input type="number" name="extend_minutes" value="5" min="1" max="60" style="width:60px;padding:.3rem .5rem;border:1px solid var(--border-light);border-radius:var(--radius-sm);font-size:.8rem">
                    <button type="submit" class="btn btn-secondary btn-sm">+ Extend</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>
<?php endif; ?>

<!-- ════════════════════════════════════════════════════════════ -->
<!-- Shared JavaScript: CSV Preview + IST Helpers                -->
<!-- ════════════════════════════════════════════════════════════ -->
<script>
// ─── CSV Client-Side Preview ──────────────────────────────────
(function(){
    const csvInput = document.getElementById('csvInput');
    const csvPreview = document.getElementById('csvPreviewArea');
    const csvSubmit = document.getElementById('csvSubmitBtn');
    if (!csvInput) return;

    csvInput.addEventListener('change', function(){
        const file = this.files[0];
        if (!file) { csvPreview.style.display='none'; csvSubmit.disabled=true; return; }
        const reader = new FileReader();
        reader.onload = function(e){
            const lines = e.target.result.split(/\r?\n/).filter(l=>l.trim());
            if (!lines.length) { csvPreview.style.display='none'; csvSubmit.disabled=true; return; }
            const isHeader = /question/i.test(lines[0]);
            const startIdx = isHeader ? 1 : 0;
            let html = '<div class="csv-preview-box"><table><thead><tr><th>#</th><th>Question</th><th>A</th><th>B</th><th>C</th><th>D</th><th>Answer</th><th>Marks</th><th>Valid?</th></tr></thead><tbody>';
            let validCount = 0;
            for (let i = startIdx; i < lines.length; i++) {
                const cols = lines[i].split(',').map(c=>c.trim());
                const q = cols[0]||''; const a = cols[1]||''; const b = cols[2]||''; const c = cols[3]||''; const d = cols[4]||'';
                const ans = (cols[5]||'').toUpperCase(); const marks = parseInt(cols[6])||1;
                const valid = q && a && b && /^[ABCD]$/.test(ans);
                if (valid) validCount++;
                html += '<tr data-valid="'+valid+'"><td>'+(i-startIdx+1)+'</td><td>'+q.substring(0,60)+(q.length>60?'…':'')+'</td><td>'+a+'</td><td>'+b+'</td><td>'+c+'</td><td>'+d+'</td><td><strong>'+ans+'</strong></td><td>'+marks+'</td><td>'+(valid?'✓':'✗')+'</td></tr>';
            }
            html += '</tbody></table><p style="font-size:.8rem;color:var(--text-muted);margin:.5rem 0 0">'+validCount+'/'+(lines.length-startIdx)+' valid rows. Invalid rows will be skipped.</p></div>';
            csvPreview.innerHTML = html;
            csvPreview.style.display = 'block';
            csvSubmit.disabled = validCount === 0;
        };
        reader.readAsText(file);
    });
})();

// ─── IST Schedule Helpers ─────────────────────────────────────
function toIST(date) {
    return new Date(date.toLocaleString('en-US', {timeZone:'Asia/Kolkata'}));
}

function toLocalInput(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth()+1).padStart(2,'0');
    const d = String(date.getDate()).padStart(2,'0');
    const h = String(date.getHours()).padStart(2,'0');
    const mi = String(date.getMinutes()).padStart(2,'0');
    return y+'-'+m+'-'+d+'T'+h+':'+mi;
}

function setSchedulePreset(hoursFromNow) {
    const now = new Date();
    const ist = toIST(now);
    ist.setMinutes(ist.getMinutes() + hoursFromNow * 60);
    document.getElementById('startTime').value = toLocalInput(ist);
    const end = new Date(ist.getTime() + 60*60*1000);
    document.getElementById('endTime').value = toLocalInput(end);
}

function setScheduleDuration(durationMinutes) {
    const startEl = document.getElementById('startTime');
    if (!startEl.value) { alert('Set start time first.'); return; }
    const start = new Date(startEl.value);
    const end = new Date(start.getTime() + durationMinutes * 60 * 1000);
    document.getElementById('endTime').value = toLocalInput(end);
}
</script>

<?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>
