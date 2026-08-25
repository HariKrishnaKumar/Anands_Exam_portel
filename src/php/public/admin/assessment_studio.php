<?php
$pageTitle = 'Assessment Studio';
require_once __DIR__ . '/../../includes/admin_header.php';

$pdo = getDB();
$message = '';
$currentStep = isset($_GET['step']) ? min(3, max(1, (int)$_GET['step'])) : 1;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    // ─── Create Draft Assessment ────────────────────────────────
    if ($action === 'create_draft') {
        $stmt = $pdo->prepare("
            INSERT INTO tests (batch_id, title, description, duration_minutes, status, shuffle_questions, created_by,
                               passing_marks, negative_marking, instructions)
            VALUES (?, ?, ?, ?, 'upcoming', ?, ?, ?, ?, ?)
        ");
        $shuffle = !empty($_POST['shuffle_questions']) ? 1 : 0;
        $stmt->execute([
            (int)$_POST['batch_id'],
            trim($_POST['title']),
            trim($_POST['description'] ?? ''),
            (int)$_POST['duration_minutes'],
            $shuffle,
            $_SESSION['admin_id'],
            (int)($_POST['passing_marks'] ?? 0),
            (float)($_POST['negative_marking'] ?? 0),
            trim($_POST['instructions'] ?? ''),
        ]);
        $testId = $pdo->lastInsertId();
        $_SESSION['last_test_id'] = $testId;
        $message = 'Draft assessment created successfully.';
        // Redirect to continue editing
        redirect('/admin/assessment_studio.php?edit_test=' . $testId);
    }

    // ─── Update Draft ───────────────────────────────────────────
    elseif ($action === 'update_draft' && !empty($_POST['id'])) {
        $stmt = $pdo->prepare("
            UPDATE tests SET batch_id=?, title=?, description=?, duration_minutes=?,
                             shuffle_questions=?, passing_marks=?, negative_marking=?, instructions=?
            WHERE id=? AND status='upcoming'
        ");
        $stmt->execute([
            (int)$_POST['batch_id'], trim($_POST['title']), trim($_POST['description'] ?? ''),
            (int)$_POST['duration_minutes'], !empty($_POST['shuffle_questions']) ? 1 : 0,
            (int)($_POST['passing_marks'] ?? 0), (float)($_POST['negative_marking'] ?? 0),
            trim($_POST['instructions'] ?? ''),
            (int)$_POST['id'],
        ]);
        $message = 'Draft updated.';
    }

    // ─── Add Question ───────────────────────────────────────────
    elseif ($action === 'add_question' && !empty($_POST['test_id']) && !empty($_POST['question_text'])) {
        $testId = (int)$_POST['test_id'];
        $type = $_POST['question_type'] ?? 'mcq';
        $optionsJson = null;
        $correctAnswer = null;

        if ($type === 'mcq') {
            $options = [];
            $rawOptions = $_POST['options'] ?? '';
            if (is_string($rawOptions) && str_starts_with(trim($rawOptions), '[')) {
                $decoded = json_decode($rawOptions, true);
                if (is_array($decoded)) $options = $decoded;
            } elseif (is_string($rawOptions) && trim($rawOptions)) {
                $lines = explode("\n", $rawOptions);
                foreach ($lines as $i => $line) {
                    $line = trim($line);
                    if ($line) {
                        $options[] = ['key' => chr(65 + $i), 'text' => $line];
                    }
                }
            }
            if (!empty($options)) {
                $optionsJson = json_encode($options);
                $correctAnswer = $_POST['correct_answer'] ?? '';
            }
        }

        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $marks = (int)($_POST['marks'] ?? 1);

        $stmt = $pdo->prepare("
            INSERT INTO questions (test_id, type, question_text, options_json, correct_answer, marks, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$testId, $type, trim($_POST['question_text']), $optionsJson, $correctAnswer, $marks, $sortOrder]);
        $message = 'Question added successfully.';
    }

    // ─── Delete Question ────────────────────────────────────────
    elseif ($action === 'delete_question' && !empty($_POST['id'])) {
        $pdo->prepare("DELETE FROM questions WHERE id = ?")->execute([(int)$_POST['id']]);
        $message = 'Question deleted.';
    }

    // ─── Reorder Question ───────────────────────────────────────
    elseif ($action === 'reorder_question' && !empty($_POST['question_id']) && !empty($_POST['direction'])) {
        $qid = (int)$_POST['question_id'];
        $dir = $_POST['direction'] === 'up' ? 'up' : 'down';
        $testIdForReorder = (int)($_POST['test_id'] ?? 0);

        // Get the current question
        $cur = $pdo->prepare("SELECT id, sort_order FROM questions WHERE id = ?");
        $cur->execute([$qid]);
        $cur = $cur->fetch();
        if ($cur && $testIdForReorder > 0) {
            if ($dir === 'up') {
                // Find the question directly above (lower sort_order, or same sort_order but lower id)
                $adj = $pdo->prepare("SELECT id, sort_order FROM questions WHERE test_id = ? AND (sort_order < ? OR (sort_order = ? AND id < ?)) ORDER BY sort_order DESC, id DESC LIMIT 1");
                $adj->execute([$testIdForReorder, $cur['sort_order'], $cur['sort_order'], $cur['id']]);
            } else {
                // Find the question directly below
                $adj = $pdo->prepare("SELECT id, sort_order FROM questions WHERE test_id = ? AND (sort_order > ? OR (sort_order = ? AND id > ?)) ORDER BY sort_order ASC, id ASC LIMIT 1");
                $adj->execute([$testIdForReorder, $cur['sort_order'], $cur['sort_order'], $cur['id']]);
            }
            $adj = $adj->fetch();
            if ($adj) {
                // Swap sort_order values
                $pdo->prepare("UPDATE questions SET sort_order = ? WHERE id = ?")->execute([(int)$adj['sort_order'], $qid]);
                $pdo->prepare("UPDATE questions SET sort_order = ? WHERE id = ?")->execute([(int)$cur['sort_order'], $adj['id']]);
                $message = 'Question order updated.';
            }
        }
        redirect('/admin/assessment_studio.php?edit_test=' . $testIdForReorder . '&step=2&view=reorder');
    }

    // ─── CSV Import ─────────────────────────────────────────────
    elseif ($action === 'import_csv' && !empty($_POST['test_id']) && !empty($_FILES['csv_file']['tmp_name'])) {
        $testId = (int)$_POST['test_id'];
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');
        if ($handle) {
            $imported = 0; $errors = []; $lineNum = 0;
            while (($row = fgetcsv($handle)) !== false) {
                $lineNum++;
                if ($lineNum === 1 && preg_match('/question/i', $row[0] ?? '')) continue;
                $qText = trim($row[0] ?? '');
                $optA  = trim($row[1] ?? ''); $optB = trim($row[2] ?? '');
                $optC  = trim($row[3] ?? ''); $optD = trim($row[4] ?? '');
                $answer = strtoupper(trim($row[5] ?? ''));
                $marks = max(1, (int)($row[6] ?? 1));
                if (empty($qText)) { $errors[] = "Line $lineNum: empty question text"; continue; }
                if (empty($optA) || empty($optB)) { $errors[] = "Line $lineNum: need >=2 options"; continue; }
                if (!in_array($answer, ['A','B','C','D'])) { $errors[] = "Line $lineNum: invalid answer '$answer'"; continue; }
                $options = [];
                if ($optA) $options[] = ['key' => 'A', 'text' => $optA];
                if ($optB) $options[] = ['key' => 'B', 'text' => $optB];
                if ($optC) $options[] = ['key' => 'C', 'text' => $optC];
                if ($optD) $options[] = ['key' => 'D', 'text' => $optD];
                $pdo->prepare("INSERT INTO questions (test_id, type, question_text, options_json, correct_answer, marks, sort_order) VALUES (?, 'mcq', ?, ?, ?, ?, ?)")
                    ->execute([$testId, $qText, json_encode($options), $answer, $marks, $lineNum]);
                $imported++;
            }
            fclose($handle);
            $msg = "Imported $imported questions successfully.";
            if (!empty($errors)) $msg .= ' Warnings: ' . implode('; ', array_slice($errors, 0, 3));
            $message = $msg;
        } else {
            $message = 'Failed to open uploaded file.';
        }
    }

    // ─── Publish Assessment ─────────────────────────────────────
    elseif ($action === 'publish_now' && !empty($_POST['id'])) {
        $testIdPub = (int)$_POST['id'];
        // Handle both upcoming and scheduled tests
        $dRow = $pdo->prepare("SELECT duration_minutes, title FROM tests WHERE id = ?");
        $dRow->execute([$testIdPub]);
        $dRow = $dRow->fetch();
        $duration = $dRow ? (int)$dRow['duration_minutes'] : 30;
        $testTitle = $dRow ? $dRow['title'] : '';
        $pdo->prepare("UPDATE tests SET status = 'active', start_time = NOW(), end_time = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ? AND (status = 'upcoming' OR status = 'scheduled')")->execute([$duration, $testIdPub]);
        redirect('/admin/assessment_management.php?tab=live&toast=published&title=' . urlencode($testTitle));
    }
    elseif ($action === 'schedule_publish' && !empty($_POST['id'])) {
        $testIdSched = (int)$_POST['id'];
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? '';
        if (empty($startTime) || empty($endTime)) {
            $message = 'Start and end times are required.';
        } else {
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
                    $stmt = $pdo->prepare("UPDATE tests SET status = 'scheduled', start_time = ?, end_time = ? WHERE id = ? AND (status = 'upcoming' OR status = 'scheduled')");
                    $stmt->execute([
                        $startDT->format('Y-m-d H:i:s'),
                        $endDT->format('Y-m-d H:i:s'),
                        $testIdSched,
                    ]);
                    $testTitle = '';
                    $tRow = $pdo->prepare("SELECT title FROM tests WHERE id = ?");
                    $tRow->execute([$testIdSched]);
                    $tRow = $tRow->fetch();
                    if ($tRow) $testTitle = $tRow['title'];
                    redirect('/admin/assessment_management.php?tab=scheduled&toast=scheduled&title=' . urlencode($testTitle));
                }
            } catch (Exception $e) {
                $message = 'Invalid date format.';
            }
        }
    }

    // ─── Delete Draft ───────────────────────────────────────────
    elseif ($action === 'delete_draft' && !empty($_POST['id'])) {
        $pdo->prepare("DELETE FROM tests WHERE id = ? AND status = 'upcoming'")->execute([(int)$_POST['id']]);
        $message = 'Draft deleted.';
    }
}

// ─── Get data ──────────────────────────────────────────────────
$batches = $pdo->query("
    SELECT b.id, b.name AS batch_name, c.name AS course_name, cl.name AS college_name
    FROM batches b
    JOIN courses c ON c.id = b.course_id
    JOIN colleges cl ON cl.id = c.college_id
    ORDER BY cl.name, c.name, b.name
")->fetchAll();

// Editing a draft?
$editTestId = (int)($_GET['edit_test'] ?? 0);
$editTest = null;
if ($editTestId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM tests WHERE id = ? AND status = 'upcoming'");
    $stmt->execute([$editTestId]);
    $editTest = $stmt->fetch();
    if ($editTest) $currentStep = 2;
}

// Questions for editing
$questions = [];
if ($editTestId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE test_id = ? ORDER BY sort_order, id");
    $stmt->execute([$editTestId]);
    $questions = $stmt->fetchAll();
}

// Get draft assessments list
$drafts = $pdo->query("
    SELECT t.*, b.name AS batch_name, c.name AS course_name, cl.name AS college_name,
           (SELECT COUNT(*) FROM questions WHERE test_id = t.id) AS question_count
    FROM tests t
    JOIN batches b ON b.id = t.batch_id
    JOIN courses c ON c.id = b.course_id
    JOIN colleges cl ON cl.id = c.college_id
    WHERE t.status = 'upcoming' AND t.start_time IS NULL AND t.end_time IS NULL
    ORDER BY t.created_at DESC
")->fetchAll();

$showDrafts = isset($_GET['tab']) && $_GET['tab'] === 'drafts';
$isReorderView = isset($_GET['view']) && $_GET['view'] === 'reorder' && $editTestId > 0 && $editTest;
?>

<div class="studio-page">

    <?php if ($message): ?>
        <div class="studio-alert studio-alert-success">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
            <span><?= h($message) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($showDrafts): ?>
        <!-- ═══════════════ DRAFTS LIST ═══════════════ -->
        <div class="studio-page-header">
            <h1>Draft Assessments</h1>
            <p>Continue editing, preview, or publish your drafts.</p>
        </div>

        <?php if (empty($drafts)): ?>
            <div class="studio-card">
                <div class="studio-card-body">
                    <div class="studio-empty">
                        <div class="studio-empty-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 3a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-1v1a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1V3zm0 1H4a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-1H9a2 2 0 0 1-2-2V4zm2-1v5a1 1 0 0 0 1 1h5V4a1 1 0 0 0-1-1h-4z"/></svg></div>
                        <h3>No Draft Assessments</h3>
                        <p>Create a new assessment to get started.</p>
                        <a href="assessment_studio.php" class="studio-btn studio-btn-primary">Create Assessment</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="studio-card">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Batch</th>
                                <th>Questions</th>
                                <th>Duration</th>
                                <th>Created</th>
                                <th class="actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($drafts as $d): ?>
                            <tr>
                                <td><strong><?= h($d['title']) ?></strong></td>
                                <td class="text-sm"><?= h($d['batch_name']) ?></td>
                                <td><span class="badge badge-active"><?= $d['question_count'] ?></span></td>
                                <td class="text-sm"><?= $d['duration_minutes'] ?> min</td>
                                <td class="text-sm text-muted"><?= date('d M Y', strtotime($d['created_at'])) ?></td>
                                <td class="actions">
                                    <a href="assessment_studio.php?edit_test=<?= $d['id'] ?>" class="studio-btn studio-btn-sm studio-btn-ghost">
                                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M13.5 2.5a1.5 1.5 0 0 1 2.12 0l1.88 1.88a1.5 1.5 0 0 1 0 2.12l-9.5 9.5a1.5 1.5 0 0 1-.7.4l-4.4 1.1a.5.5 0 0 1-.6-.6l1.1-4.4a1.5 1.5 0 0 1 .4-.7l9.5-9.5z"/></svg>
                                        Edit
                                    </a>
                                    <button class="studio-btn studio-btn-sm studio-btn-primary" onclick="openPublishModal(<?= $d['id'] ?>, '<?= h($d['title']) ?>')">
                                        Publish
                                    </button>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this draft?')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete_draft">
                                        <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                        <button type="submit" class="studio-btn studio-btn-sm studio-btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    <?php elseif ($editTestId > 0 && $editTest && !(isset($_GET['step']) && $_GET['step'] == 3)): ?>
        <!-- ═══════════════ EDITING DRAFT — STEP 2 ═══════════════ -->

        <a href="assessment_studio.php" class="studio-back">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M8.65 3.15a.5.5 0 0 0-.7-.7l-6.5 6.5a.5.5 0 0 0 0 .7l6.5 6.5a.5.5 0 0 0 .7-.7L3.2 10H17.5a.5.5 0 0 0 0-1H3.2l5.45-5.85z"/></svg>
            Back to Studio
        </a>

        <div class="studio-card">
            <div class="studio-stepper">
                <div class="studio-step completed">
                    <span class="studio-step-number"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg></span>
                    <span class="studio-step-label">Basic Information</span>
                </div>
                <div class="studio-step active">
                    <span class="studio-step-number">2</span>
                    <span class="studio-step-label">Question Builder</span>
                </div>
                <div class="studio-step">
                    <span class="studio-step-number">3</span>
                    <span class="studio-step-label">Review & Publish</span>
                </div>
            </div>

            <div class="studio-card-body">
                <!-- Step 2: Question Builder -->
                <div>
                    <div class="studio-section-header">
                        <div>
                            <div class="studio-section-title">Question Builder</div>
                            <div class="studio-section-subtitle">Add questions manually, import from CSV, or both.</div>
                        </div>
                        <span class="studio-section-badge"><?= count($questions) ?> questions</span>
                    </div>

                        <!-- Manual Question Form -->
                        <div class="studio-section">
                            <div class="studio-section-header">
                                <div class="studio-section-title">Add Question Manually</div>
                            </div>
                            <form method="POST">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="add_question">
                                <input type="hidden" name="test_id" value="<?= $editTestId ?>">

                                <div class="studio-field-row">
                                    <div class="studio-field">
                                        <label class="studio-label">Question Type <span class="required-badge">Required</span></label>
                                        <select class="studio-select" name="question_type" onchange="toggleQuestionType(this)">
                                            <option value="mcq">MCQ (Multiple Choice)</option>
                                            <option value="coding">Coding</option>
                                            <option value="explanation">Explanation</option>
                                        </select>
                                    </div>
                                    <div class="studio-field">
                                        <label class="studio-label">Marks <span class="required-badge">Required</span></label>
                                        <input class="studio-input" type="number" name="marks" value="1" min="1" max="100">
                                    </div>
                                </div>

                                <div class="studio-field">
                                    <label class="studio-label">Question Text <span class="required-badge">Required</span></label>
                                    <textarea class="studio-textarea" name="question_text" required placeholder="Type your question here..." style="min-height:80px;"></textarea>
                                </div>

                                <div id="mcqFields">
                                    <div class="studio-field">
                                        <label class="studio-label">Options <span class="optional-badge">One per line</span></label>
                                        <textarea class="studio-textarea" name="options_plain" id="optionsPlain" placeholder="Option A&#10;Option B&#10;Option C&#10;Option D" style="min-height:80px;"></textarea>
                                        <input type="hidden" name="options" id="optionsHidden">
                                        <div class="studio-helper">Enter each option on a new line. The first line = A, second = B, etc.</div>
                                    </div>
                                    <div class="studio-field">
                                        <label class="studio-label">Correct Answer <span class="required-badge">Required</span></label>
                                        <select class="studio-select" name="correct_answer" id="correctAnswer">
                                            <option value="">Select correct answer</option>
                                            <option value="A">A</option>
                                            <option value="B">B</option>
                                            <option value="C">C</option>
                                            <option value="D">D</option>
                                        </select>
                                    </div>
                                </div>

                                <input type="hidden" name="sort_order" value="<?= count($questions) + 1 ?>">

                                <button type="submit" class="studio-btn studio-btn-primary">
                                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 3a.5.5 0 0 1 .5.5v6h6a.5.5 0 0 1 0 1h-6v6a.5.5 0 0 1-1 0v-6h-6a.5.5 0 0 1 0-1h6v-6A.5.5 0 0 1 10 3z"/></svg>
                                    Add Question
                                </button>
                            </form>
                        </div>

                        <!-- CSV Import -->
                        <div class="studio-section">
                            <div class="studio-section-header">
                                <div class="studio-section-title">Bulk Import from CSV</div>
                            </div>
                            <div class="studio-helper" style="margin-bottom:var(--space-4);">
                                CSV columns: <code>question_text, option_a, option_b, option_c, option_d, correct_answer (A-D), marks</code>
                            </div>
                            <form method="POST" enctype="multipart/form-data">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="import_csv">
                                <input type="hidden" name="test_id" value="<?= $editTestId ?>">
                                <div class="studio-csv-zone">
                                    <input type="file" name="csv_file" accept=".csv,.txt" required>
                                    <button type="submit" class="studio-btn studio-btn-secondary">Import CSV</button>
                                </div>
                            </form>
                        </div>

                        <!-- Settings -->
                        <div class="studio-section">
                            <div class="studio-section-header">
                                <div class="studio-section-title">Question Settings</div>
                            </div>
                            <form method="POST">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="update_draft">
                                <input type="hidden" name="id" value="<?= $editTestId ?>">
                                <input type="hidden" name="batch_id" value="<?= $editTest['batch_id'] ?>">
                                <input type="hidden" name="title" value="<?= h($editTest['title']) ?>">
                                <input type="hidden" name="description" value="<?= h($editTest['description'] ?? '') ?>">
                                <input type="hidden" name="duration_minutes" value="<?= $editTest['duration_minutes'] ?>">
                                <input type="hidden" name="passing_marks" value="<?= $editTest['passing_marks'] ?? 0 ?>">
                                <input type="hidden" name="negative_marking" value="<?= $editTest['negative_marking'] ?? 0 ?>">
                                <input type="hidden" name="instructions" value="<?= h($editTest['instructions'] ?? '') ?>">
                                <label class="studio-checkbox">
                                    <input class="studio-checkbox-input" type="checkbox" name="shuffle_questions" value="1" <?= !empty($editTest['shuffle_questions']) ? 'checked' : '' ?>>
                                    <span class="studio-checkbox-mark">
                                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                                    </span>
                                    <span class="studio-checkbox-label">Shuffle questions for students</span>
                                </label>
                                <div class="studio-helper">When enabled, question order will be randomized for each student.</div>
                                <button type="submit" class="studio-btn studio-btn-secondary" style="margin-top:var(--space-5);">Save Settings</button>
                            </form>
                        </div>

                        <!-- Questions List -->
                        <div class="studio-section">
                            <div class="studio-section-header">
                                <div class="studio-section-title">Questions (<?= count($questions) ?>)</div>
                                <?php if (!empty($questions)): ?>
                                <div style="display:flex;gap:var(--space-2);align-items:center;">
                                    <?php if ($isReorderView): ?>
                                        <a href="assessment_studio.php?edit_test=<?= $editTestId ?>&step=2" class="studio-btn studio-btn-sm studio-btn-secondary">
                                            <svg viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                                            Done Reordering
                                        </a>
                                    <?php else: ?>
                                        <a href="assessment_studio.php?edit_test=<?= $editTestId ?>&step=2&view=reorder" class="studio-btn studio-btn-sm studio-btn-ghost">
                                            <svg viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;"><path d="M3 4a1 1 0 0 1 1-1h1a1 1 0 0 1 0 2H4a1 1 0 0 1-1-1zm4 0a1 1 0 0 1 1-1h9a1 1 0 0 1 0 2H8a1 1 0 0 1-1-1zm-4 4a1 1 0 0 1 1-1h1a1 1 0 0 1 0 2H4a1 1 0 0 1-1-1zm4 0a1 1 0 0 1 1-1h9a1 1 0 0 1 0 2H8a1 1 0 0 1-1-1zm-4 4a1 1 0 0 1 1-1h1a1 1 0 0 1 0 2H4a1 1 0 0 1-1-1zm4 0a1 1 0 0 1 1-1h9a1 1 0 0 1 0 2H8a1 1 0 0 1-1-1zm-4 4a1 1 0 0 1 1-1h1a1 1 0 0 1 0 2H4a1 1 0 0 1-1-1zm4 0a1 1 0 0 1 1-1h9a1 1 0 0 1 0 2H8a1 1 0 0 1-1-1z"/></svg>
                                            Reorder
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php if (empty($questions)): ?>
                                <div class="studio-empty">
                                    <div class="studio-empty-icon"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 3a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-1v1a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1V3zm0 1H4a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-1H9a2 2 0 0 1-2-2V4zm2-1v5a1 1 0 0 0 1 1h5V4a1 1 0 0 0-1-1h-4z"/></svg></div>
                                    <h3>No Questions Yet</h3>
                                    <p>Add questions manually or import from CSV.</p>
                                </div>
                            <?php else: ?>
                                <div>
                                    <?php foreach ($questions as $i => $q): ?>
                                    <div class="studio-question-item">
                                        <?php if ($isReorderView): ?>
                                        <div class="studio-question-meta" style="gap:2px;margin-right:var(--space-2);">
                                            <?php if ($i > 0): ?>
                                            <form method="POST" style="display:inline">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="reorder_question">
                                                <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                                <input type="hidden" name="direction" value="up">
                                                <input type="hidden" name="test_id" value="<?= $editTestId ?>">
                                                <button type="submit" class="studio-question-delete" data-tooltip="Move Up" style="color:var(--accent);">
                                                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 4.5a.5.5 0 0 1 .5.5v8.793l3.15-3.15a.5.5 0 0 1 .7.7l-4 4a.5.5 0 0 1-.7 0l-4-4a.5.5 0 0 1 .7-.7L9.5 13.793V5a.5.5 0 0 1 .5-.5z"/></svg>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            <?php if ($i < count($questions) - 1): ?>
                                            <form method="POST" style="display:inline">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="reorder_question">
                                                <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                                <input type="hidden" name="direction" value="down">
                                                <input type="hidden" name="test_id" value="<?= $editTestId ?>">
                                                <button type="submit" class="studio-question-delete" data-tooltip="Move Down" style="color:var(--accent);">
                                                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 15.5a.5.5 0 0 1-.5-.5V6.207l-3.15 3.15a.5.5 0 0 1-.7-.7l4-4a.5.5 0 0 1 .7 0l4 4a.5.5 0 0 1-.7.7L10.5 6.207V15a.5.5 0 0 1-.5.5z"/></svg>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                        <div class="studio-question-number"><?= $i + 1 ?></div>
                                        <div class="studio-question-text"><?= h(mb_substr($q['question_text'], 0, 120)) ?><?= mb_strlen($q['question_text']) > 120 ? '...' : '' ?></div>
                                        <div class="studio-question-meta">
                                            <span class="badge <?= $q['type'] === 'mcq' ? 'badge-active' : ($q['type'] === 'coding' ? 'badge-pending' : 'badge-success') ?>">
                                                <?= ucfirst($q['type']) ?>
                                            </span>
                                            <span class="badge badge-neutral"><?= $q['marks'] ?> pts</span>
                                            <?php if (!$isReorderView): ?>
                                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this question?')">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="delete_question">
                                                <input type="hidden" name="id" value="<?= $q['id'] ?>">
                                                <button type="submit" class="studio-question-delete" data-tooltip="Delete">
                                                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M8.5 2.5a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1V3h3.5a.5.5 0 0 1 0 1h-.55l-.77 11.57A2 2 0 0 1 11.7 17H8.3a2 2 0 0 1-2-1.93L5.55 4H5a.5.5 0 0 1 0-1h3.5V2.5z"/></svg>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($isReorderView && !empty($questions)): ?>
                        <!-- Live Preview (inline) -->
                        <div class="studio-section" style="margin-top:var(--space-5);">
                            <div class="studio-section-header">
                                <div class="studio-section-title">Preview — Student View</div>
                                <div class="studio-section-subtitle">How students will see the questions in this order.</div>
                            </div>
                            <div style="background:var(--surface);border:1px solid var(--glass-border);border-radius:var(--radius-lg);padding:var(--space-5);">
                                <?php foreach ($questions as $pi => $pq): ?>
                                <div style="padding:var(--space-4);<?= $pi > 0 ? 'border-top:1px solid var(--gray-10);' : '' ?>">
                                    <div style="display:flex;align-items:flex-start;gap:var(--space-3);">
                                        <div style="min-width:28px;height:28px;border-radius:50%;background:var(--accent-soft);color:var(--accent);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:var(--fs-12);"><?= $pi + 1 ?></div>
                                        <div style="flex:1;">
                                            <div style="font-weight:500;color:var(--gray-80);line-height:1.5;"><?= h($pq['question_text']) ?></div>
                                            <?php if ($pq['type'] === 'mcq' && !empty($pq['options_json'])): ?>
                                            <div style="margin-top:var(--space-2);display:flex;flex-direction:column;gap:var(--space-1);">
                                                <?php foreach (json_decode($pq['options_json'], true) as $opt): ?>
                                                <div style="display:flex;align-items:center;gap:var(--space-2);padding:var(--space-1) var(--space-2);background:var(--gray-05);border-radius:var(--radius-sm);font-size:var(--fs-13);color:var(--gray-60);">
                                                    <span style="font-weight:600;color:var(--gray-70);"><?= h($opt['key']) ?>.</span> <?= h($opt['text']) ?>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php elseif ($pq['type'] === 'coding'): ?>
                                            <div style="margin-top:var(--space-2);padding:var(--space-2) var(--space-3);background:var(--gray-05);border-radius:var(--radius-sm);font-size:var(--fs-12);color:var(--gray-50);font-family:var(--font-mono);">Code editor will appear here</div>
                                            <?php elseif ($pq['type'] === 'explanation'): ?>
                                            <div style="margin-top:var(--space-2);padding:var(--space-2) var(--space-3);background:var(--gray-05);border-radius:var(--radius-sm);font-size:var(--fs-12);color:var(--gray-50);">Text area for explanation</div>
                                            <?php endif; ?>
                                        </div>
                                        <span class="badge badge-neutral" style="flex-shrink:0;"><?= $pq['marks'] ?> pts</span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="studio-card-footer" style="border-top: 1px solid var(--glass-border); margin-top: var(--space-6); padding: var(--space-5) 0 0;">
                            <a href="assessment_studio.php?edit_test=<?= $editTestId ?>&step=1" class="studio-btn studio-btn-secondary">
                                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M8.65 3.15a.5.5 0 0 0-.7-.7l-6.5 6.5a.5.5 0 0 0 0 .7l6.5 6.5a.5.5 0 0 0 .7-.7L3.2 10H17.5a.5.5 0 0 0 0-1H3.2l5.45-5.85z"/></svg>
                                Back
                            </a>
                            <a href="assessment_studio.php?edit_test=<?= $editTestId ?>&step=3" class="studio-btn studio-btn-primary">
                                Review Assessment
                                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M11.35 3.15a.5.5 0 0 1 .7-.7l6.5 6.5a.5.5 0 0 1 0 .7l-6.5 6.5a.5.5 0 0 1-.7-.7L16.8 10H2.5a.5.5 0 0 1 0-1h14.3l-5.45-5.85z"/></svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

    <?php elseif ($editTestId > 0 && isset($_GET['step']) && $_GET['step'] == 3 && $editTest): ?>
        <!-- ═══════════════ STEP 3: REVIEW ═══════════════ -->
        <a href="assessment_studio.php?edit_test=<?= $editTestId ?>" class="studio-back">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M8.65 3.15a.5.5 0 0 0-.7-.7l-6.5 6.5a.5.5 0 0 0 0 .7l6.5 6.5a.5.5 0 0 0 .7-.7L3.2 10H17.5a.5.5 0 0 0 0-1H3.2l5.45-5.85z"/></svg>
            Back to Questions
        </a>

        <div class="studio-card">
            <div class="studio-stepper">
                <div class="studio-step completed">
                    <span class="studio-step-number"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg></span>
                    <span class="studio-step-label">Basic Information</span>
                </div>
                <div class="studio-step completed">
                    <span class="studio-step-number"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg></span>
                    <span class="studio-step-label">Question Builder</span>
                </div>
                <div class="studio-step active">
                    <span class="studio-step-number">3</span>
                    <span class="studio-step-label">Review & Publish</span>
                </div>
            </div>

            <div class="studio-card-body">
                <div class="studio-section-header">
                    <div class="studio-section-title">Review Assessment</div>
                </div>

                <div class="studio-review-grid">
                    <div class="studio-review-card">
                        <div class="studio-review-card-header">
                            <svg viewBox="0 0 20 20" fill="currentColor" style="width:16px;height:16px;"><path d="M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16zm0 1a7 7 0 1 1 0 14 7 7 0 0 1 0-14zm.5 3a.5.5 0 0 0-.5.5v4a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 0-1H11V6.5a.5.5 0 0 0-.5-.5z"/></svg>
                            Assessment Details
                        </div>
                        <div class="studio-review-card-body">
                            <div class="studio-review-item">
                                <span class="studio-review-label">Title</span>
                                <span class="studio-review-value"><?= h($editTest['title']) ?></span>
                            </div>
                            <div class="studio-review-item">
                                <span class="studio-review-label">Duration</span>
                                <span class="studio-review-value"><?= $editTest['duration_minutes'] ?> minutes</span>
                            </div>
                            <div class="studio-review-item">
                                <span class="studio-review-label">Question Count</span>
                                <span class="studio-review-value"><?= count($questions) ?></span>
                            </div>
                            <div class="studio-review-item">
                                <span class="studio-review-label">MCQ Questions</span>
                                <span class="studio-review-value"><?= count(array_filter($questions, fn($q) => $q['type'] === 'mcq')) ?></span>
                            </div>
                            <div class="studio-review-item">
                                <span class="studio-review-label">Coding Questions</span>
                                <span class="studio-review-value"><?= count(array_filter($questions, fn($q) => $q['type'] === 'coding')) ?></span>
                            </div>
                            <div class="studio-review-item">
                                <span class="studio-review-label">Explanation Questions</span>
                                <span class="studio-review-value"><?= count(array_filter($questions, fn($q) => $q['type'] === 'explanation')) ?></span>
                            </div>
                            <div class="studio-review-item">
                                <span class="studio-review-label">Shuffle Enabled</span>
                                <span class="studio-review-value"><?= !empty($editTest['shuffle_questions']) ? 'Yes' : 'No' ?></span>
                            </div>
                            <div class="studio-review-item">
                                <span class="studio-review-label">Passing Marks</span>
                                <span class="studio-review-value"><?= (int)($editTest['passing_marks'] ?? 0) > 0 ? (int)$editTest['passing_marks'] : 'Not set' ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="studio-action-panel">
                        <div>
                            <div style="font-size:var(--fs-14);font-weight:600;color:var(--gray-90);margin-bottom:var(--space-2);">Actions</div>
                            <p>This assessment is currently a draft. Students cannot see it until published.</p>
                        </div>
                        <button class="studio-btn studio-btn-primary studio-btn-lg studio-btn-block" onclick="openPublishModal(<?= $editTestId ?>, '<?= h($editTest['title']) ?>')">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M4 3.5a.5.5 0 0 1 .75-.43l12.5 7a.5.5 0 0 1 0 .86l-12.5 7A.5.5 0 0 1 4 17.5v-14z"/></svg>
                            Publish Assessment
                        </button>
                        <a href="assessment_studio.php?edit_test=<?= $editTestId ?>" class="studio-btn studio-btn-secondary studio-btn-block">Continue Editing</a>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- ═══════════════ STEP 1: BASIC INFORMATION ═══════════════ -->
        <div class="studio-page-header">
            <h1>Create Assessment</h1>
            <p>Set up your assessment details. You can add questions in the next step.</p>
        </div>

        <div class="studio-card">
            <div class="studio-stepper">
                <div class="studio-step active">
                    <span class="studio-step-number">1</span>
                    <span class="studio-step-label">Basic Information</span>
                </div>
                <div class="studio-step">
                    <span class="studio-step-number">2</span>
                    <span class="studio-step-label">Question Builder</span>
                </div>
                <div class="studio-step">
                    <span class="studio-step-number">3</span>
                    <span class="studio-step-label">Review & Publish</span>
                </div>
            </div>

            <div class="studio-card-body">
                <form method="POST" id="createAssessmentForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="create_draft">

                    <!-- Assessment Information Section -->
                    <div class="studio-section">
                        <div class="studio-section-header">
                            <div class="studio-section-title">Assessment Information</div>
                        </div>

                        <div class="studio-field">
                            <label class="studio-label">Assessment Title <span class="required-badge">Required</span></label>
                            <input class="studio-input" type="text" name="title" required placeholder="e.g. Midterm Examination 2024">
                        </div>

                        <div class="studio-field">
                            <label class="studio-label">Target Batch <span class="required-badge">Required</span></label>
                            <select class="studio-select" name="batch_id" required>
                                <option value="">Select Batch</option>
                                <?php foreach ($batches as $b): ?>
                                    <option value="<?= $b['id'] ?>">
                                        <?= h($b['college_name']) ?> → <?= h($b['course_name']) ?> → <?= h($b['batch_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Assessment Rules Section -->
                    <div class="studio-section">
                        <div class="studio-section-header">
                            <div class="studio-section-title">Assessment Rules</div>
                        </div>

                        <div class="studio-field-row">
                            <div class="studio-field">
                                <label class="studio-label">Duration <span class="required-badge">Required</span></label>
                                <input class="studio-input" type="number" name="duration_minutes" required min="1" max="480" value="30">
                                <div class="studio-helper">Time limit in minutes (1–480)</div>
                            </div>
                            <div class="studio-field">
                                <label class="studio-label">Passing Marks <span class="optional-badge">Optional</span></label>
                                <input class="studio-input" type="number" name="passing_marks" min="0" value="0" placeholder="0 = not set">
                                <div class="studio-helper">Minimum marks required to pass</div>
                            </div>
                        </div>

                        <div class="studio-field-row">
                            <div class="studio-field">
                                <label class="studio-label">Negative Marking <span class="optional-badge">Optional</span></label>
                                <input class="studio-input" type="number" name="negative_marking" min="0" max="10" step="0.5" value="0" placeholder="0 = none">
                                <div class="studio-helper">Marks deducted for incorrect answers per question.</div>
                            </div>
                            <div class="studio-field" style="justify-content:flex-end;">
                                <label class="studio-label">Shuffle Questions</label>
                                <label class="studio-checkbox">
                                    <input class="studio-checkbox-input" type="checkbox" name="shuffle_questions" value="1">
                                    <span class="studio-checkbox-mark">
                                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                                    </span>
                                    <span class="studio-checkbox-label">Randomize question order for students</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Description & Instructions Section -->
                    <div class="studio-section">
                        <div class="studio-section-header">
                            <div class="studio-section-title">Description & Instructions</div>
                        </div>

                        <div class="studio-field">
                            <label class="studio-label">Description <span class="optional-badge">Optional</span></label>
                            <textarea class="studio-textarea" name="description" placeholder="Brief description of the assessment" style="min-height:60px;"></textarea>
                        </div>

                        <div class="studio-field">
                            <label class="studio-label">Detailed Instructions <span class="optional-badge">Optional</span></label>
                            <textarea class="studio-textarea" name="instructions" placeholder="Detailed instructions shown to students before starting the assessment" style="min-height:80px;"></textarea>
                            <div class="studio-helper">These instructions will be displayed on the assessment start screen.</div>
                        </div>
                    </div>

                    <div class="studio-card-footer" style="border-top:1px solid var(--glass-border);margin-top:var(--space-6);padding:var(--space-5) 0 0;">
                        <div></div>
                        <button type="submit" class="studio-btn studio-btn-primary studio-btn-lg">
                            Next — Question Builder
                            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M11.35 3.15a.5.5 0 0 1 .7-.7l6.5 6.5a.5.5 0 0 1 0 .7l-6.5 6.5a.5.5 0 0 1-.7-.7L16.8 10H2.5a.5.5 0 0 1 0-1h14.3l-5.45-5.85z"/></svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Quick link to drafts -->
        <div style="text-align:center;margin-top:var(--space-5);">
            <a href="assessment_studio.php?tab=drafts" class="studio-btn studio-btn-ghost">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 3a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-1v1a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1V3z"/></svg>
                View Draft Assessments
            </a>
        </div>

    <?php endif; ?>
</div>

<!-- Publish Modal -->
<div class="modal-overlay" id="publishModal" style="display:none;">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h3>Publish Assessment</h3>
            <button type="button" class="modal-close" onclick="closePublishModal()">
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
                    <button type="submit" class="studio-btn studio-btn-primary studio-btn-lg studio-btn-block">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M4 3.5a.5.5 0 0 1 .75-.43l12.5 7a.5.5 0 0 1 0 .86l-12.5 7A.5.5 0 0 1 4 17.5v-14z"/></svg>
                        Publish Now — Make Live
                    </button>
                </form>
                <div style="position:relative;text-align:center;">
                    <span style="background:var(--surface);padding:0 var(--space-3);color:var(--gray-40);font-size:var(--fs-11);position:relative;z-index:1;">or</span>
                    <hr style="border:none;border-top:1px solid var(--gray-15);margin:-9px auto 0;width:100%;">
                </div>
                <button class="studio-btn studio-btn-secondary studio-btn-block" onclick="showScheduleForm()">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M5.5 2a.5.5 0 0 1 .5.5V3h8v-.5a.5.5 0 0 1 1 0V3h1a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h1v-.5a.5.5 0 0 1 .5-.5zM4 4a1 1 0 0 0-1 1v1h14V5a1 1 0 0 0-1-1H4z"/></svg>
                    Schedule for Later
                </button>
            </div>

            <form method="POST" id="scheduleForm" style="display:none;margin-top:var(--space-4);padding-top:var(--space-4);border-top:1px solid var(--gray-10);">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="schedule_publish">
                <input type="hidden" name="id" id="scheduleTestId" value="">
                <div class="studio-field">
                    <label class="studio-label">Start Date & Time</label>
                    <input class="studio-input" type="datetime-local" name="start_time" required>
                </div>
                <div class="studio-field">
                    <label class="studio-label">End Date & Time</label>
                    <input class="studio-input" type="datetime-local" name="end_time" required>
                </div>
                <button type="submit" class="studio-btn studio-btn-primary studio-btn-block">Schedule Publication</button>
            </form>
        </div>
    </div>
</div>

<script>
function toggleQuestionType(sel) {
    document.getElementById('mcqFields').style.display = sel.value === 'mcq' ? 'block' : 'none';
}

// Convert plain text options to JSON array before form submit
document.querySelector('form[action*="add_question"]')?.addEventListener('submit', function() {
    const plain = document.getElementById('optionsPlain');
    if (plain) {
        const lines = plain.value.split('\n').filter(l => l.trim());
        const options = lines.map((text, i) => ({
            key: String.fromCharCode(65 + i),
            text: text.trim()
        }));
        document.getElementById('optionsHidden').value = JSON.stringify(options);
        const sel = document.getElementById('correctAnswer');
        const currentVal = sel.value;
        sel.innerHTML = '<option value="">Select correct answer</option>';
        options.forEach(o => {
            sel.innerHTML += '<option value="' + o.key + '" ' + (currentVal === o.key ? 'selected' : '') + '>' + o.key + '</option>';
        });
    }
});

function openPublishModal(id, title) {
    document.getElementById('publishTestId').value = id;
    document.getElementById('scheduleTestId').value = id;
    document.getElementById('publishTitle').textContent = 'Publish "' + title + '" — students will immediately be able to see and start this assessment.';
    var el = document.getElementById('publishModal');
    el.style.display = 'flex';
    el.classList.add('open');
    document.getElementById('scheduleForm').style.display = 'none';
}

function closePublishModal() {
    var el = document.getElementById('publishModal');
    el.classList.remove('open');
    el.style.display = 'none';
}

function showScheduleForm() {
    document.getElementById('scheduleForm').style.display = 'block';
}

// Close modal on overlay click
document.getElementById('publishModal')?.addEventListener('click', function(e) {
    if (e.target === this) closePublishModal();
});
</script>

<?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>