<?php
/**
 * Submit / Auto-save Answers.
 * POST only. Stores answers in real-time.
 *
 * Payload:
 *  - Accepts standard form data (auto-save / no-JS fallback)
 *  - Accepts JSON bodies: {"test_id":..,"submission_id":..,"auto_save":false,"answer":{..},"question_ids":[...]}
 *
 * Integrity guarantees:
 *  - All answer upserts + final submission transition run inside ONE transaction.
 *  - The submit transition is conditional (WHERE status = 'in_progress') so rapid
 *    double-clicks can never flip the submission twice or corrupt scores.
 *  - student_answers carries UNIQUE KEY (submission_id, question_id) and uses
 *    ON DUPLICATE KEY UPDATE (submission_id maps 1:1 to (student_id, test_id)),
 *    giving database-level idempotency per (test, student, question).
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = parseRequestBody();

if (!isset($input['csrf_token']) || !validateCsrfToken($input['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$testId = (int)($input['test_id'] ?? 0);
$submissionId = $input['submission_id'] ?? '';
$autoSave = !empty($input['auto_save']);
$action = $input['action'] ?? '';
$answers = $input['answer'] ?? [];
$questionIds = $input['question_ids'] ?? [];

if (!is_array($answers)) $answers = [];
if (!is_array($questionIds)) $questionIds = [];

if ($testId <= 0 || empty($submissionId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing test or submission info']);
    exit;
}

$pdo = getDB();

// Handle guest answers (stored in guest_entries.temp_data)
if (strpos($submissionId, 'guest_') === 0) {
    $guestEntryId = (int)str_replace('guest_', '', $submissionId);

    if (strpos($action, 'submit') !== false || !$autoSave) {
        // For guests, store answers in guest_entries.temp_data JSON
        $stmt = $pdo->prepare("SELECT temp_data FROM guest_entries WHERE id = ?");
        $stmt->execute([$guestEntryId]);
        $entry = $stmt->fetch();
        if (!$entry) {
            http_response_code(404);
            echo json_encode(['error' => 'Guest entry not found']);
            exit;
        }
        $tempData = json_decode($entry['temp_data'] ?? '{}', true) ?: [];
        $tempData['answers'] = $answers;
        $tempData['submitted_at'] = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("UPDATE guest_entries SET temp_data = ? WHERE id = ?");
        $stmt->execute([json_encode($tempData), $guestEntryId]);
    }

    echo json_encode(['success' => true, 'message' => 'Guest answers saved']);
    exit;
}

// Logged-in student path
// Check submission exists and is not already submitted
$stmt = $pdo->prepare("SELECT id, status FROM submissions WHERE id = ? AND test_id = ?");
$stmt->execute([$submissionId, $testId]);
$submission = $stmt->fetch();

if (!$submission) {
    http_response_code(404);
    echo json_encode(['error' => 'Submission not found']);
    exit;
}

// CRITICAL: Block if already submitted
if ($submission['status'] === 'submitted' || $submission['status'] === 'evaluated') {
    http_response_code(400);
    echo json_encode(['error' => 'Test already submitted', 'status' => $submission['status']]);
    exit;
}

// Resolve question types in ONE query (avoids per-answer round trips)
$questionTypes = [];
if (!empty($answers)) {
    $ids = array_keys($answers);
    $ids = array_filter($ids, fn($q) => (int)$q > 0);
    if (!empty($ids)) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $qStmt = $pdo->prepare("SELECT id, type FROM questions WHERE id IN ($ph)");
        $qStmt->execute(array_map('intval', $ids));
        foreach ($qStmt->fetchAll() as $qr) {
            $questionTypes[(int)$qr['id']] = $qr['type'];
        }
    }
}

// All-or-nothing: answer upserts + (for final submit) status transition in one transaction
$pdo->beginTransaction();
try {
    $insertStmt = $pdo->prepare("
        INSERT INTO student_answers (submission_id, question_id, answer_json, submitted_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            answer_json = VALUES(answer_json),
            submitted_at = NOW()
    ");

    foreach ($answers as $qId => $answer) {
        $qId = (int)$qId;
        if ($qId <= 0) continue;

        // Format answer as JSON depending on question type
        $answerData = [];
        $qType = $questionTypes[$qId] ?? null;
        if ($qType === 'mcq') {
            $answerData = ['selected' => $answer];
        } elseif ($qType === 'coding') {
            $answerData = ['code' => is_array($answer) ? ($answer['code'] ?? '') : $answer];
        } elseif ($qType === 'explanation') {
            $answerData = ['text' => is_array($answer) ? ($answer['text'] ?? '') : $answer];
        } elseif ($qType === null) {
            // Question no longer exists — keep payload as-is instead of crashing the batch
            $answerData = is_array($answer) ? $answer : ['value' => $answer];
        }

        $insertStmt->execute([$submissionId, $qId, json_encode($answerData)]);
    }

    $submittedFlag = false;
    // If final submission (not auto-save)
    if (!$autoSave) {
        // ─── Hybrid evaluation pipeline (all inside this transaction) ───
        // 1) Detect test composition: pure MCQ vs hybrid (has coding/explanation)
        $compStmt = $pdo->prepare("
            SELECT COALESCE(SUM(CASE WHEN type <> 'mcq' THEN 1 ELSE 0 END), 0) AS subjective_count,
                   COALESCE(SUM(marks), 0) AS max_total
            FROM questions WHERE test_id = ?
        ");
        $compStmt->execute([$testId]);
        $composition = $compStmt->fetch();
        $isPureMcq = ((int)$composition['subjective_count'] === 0);

        // 2) Instant auto-grading of every MCQ answer in this submission.
        //    NULL-safe compare handles blank selections (NULL <=> 'A' → false → 0 marks).
        $gradeStmt = $pdo->prepare("
            UPDATE student_answers sa
            JOIN questions q ON q.id = sa.question_id
            SET sa.marks_obtained = CASE
                    WHEN JSON_UNQUOTE(JSON_EXTRACT(sa.answer_json, '$.selected')) <=> q.correct_answer
                        THEN q.marks ELSE 0 END,
                sa.is_auto_graded = 1,
                sa.evaluated_at = NOW()
            WHERE sa.submission_id = ? AND q.type = 'mcq'
        ");
        $gradeStmt->execute([$submissionId]);

        // 3) Sum MCQ points actually earned
        $autoStmt = $pdo->prepare("
            SELECT COALESCE(SUM(sa.marks_obtained), 0)
            FROM student_answers sa
            JOIN questions q ON q.id = sa.question_id
            WHERE sa.submission_id = ? AND q.type = 'mcq'
        ");
        $autoStmt->execute([$submissionId]);
        $autoScore = (float)$autoStmt->fetchColumn();

        // Calculate total marks for the test
        $marksStmt = $pdo->prepare("SELECT COALESCE(SUM(marks), 0) FROM questions WHERE test_id = ?");
        $marksStmt->execute([$testId]);
        $totalMarks = (float)$marksStmt->fetchColumn();

        // Idempotent transition: only flips from in_progress → submitted.
        // A concurrent/duplicate request finds rowCount() === 0 and is a no-op.
        $stmt = $pdo->prepare("
            UPDATE submissions
            SET status = 'submitted', submitted_at = NOW(), total_marks = ?
            WHERE id = ? AND status = 'in_progress'
        ");
        $stmt->execute([$totalMarks, $submissionId]);
        $submittedFlag = $stmt->rowCount() > 0;

        // 4) State transition — only for the request that won the flip above,
        //    so duplicate submits can never double-grade or overwrite scores.
        if ($submittedFlag) {
            if ($isPureMcq) {
                // 100% MCQ: fully evaluated the moment the student clicks Submit
                $stmt = $pdo->prepare("
                    UPDATE submissions
                    SET status = 'evaluated',
                        evaluation_status = 'evaluated',
                        auto_score = ?,
                        manual_score = NULL,
                        total_score = ?,
                        total_marks_obtained = ?,
                        evaluated_at = NOW(),
                        evaluator_id = NULL
                    WHERE id = ?
                ");
                $stmt->execute([$autoScore, $autoScore, $autoScore, $submissionId]);
            } else {
                // Hybrid: keep objective points, park subjective part for admin review
                $stmt = $pdo->prepare("
                    UPDATE submissions
                    SET status = 'submitted',
                        evaluation_status = 'pending_manual_review',
                        auto_score = ?,
                        manual_score = NULL,
                        total_score = NULL,
                        total_marks_obtained = NULL
                    WHERE id = ?
                ");
                $stmt->execute([$autoScore, $submissionId]);
            }
        }
    }

    $pdo->commit();

    if (!$autoSave) {
        // Pure-MCQ submissions just became fully evaluated → refresh PCI analytics.
        // Hybrid submissions are refreshed by grading.php when the admin finalizes.
        if ($submittedFlag && $isPureMcq) {
            recalculatePciForSubmission((int)$submissionId);
        }

        // Browser form posts (native submit from test.php) get redirected to the
        // result screen so students never land on raw JSON. fetch callers and
        // guests still get JSON.
        if (!empty($input['redirect']) && strpos((string)$submissionId, 'guest_') !== 0) {
            $base = defined('BASE_URL') ? BASE_URL : '';
            header('Location: ' . $base . '/student/test.php?test_id=' . $testId . '&submitted=1');
            exit;
        }

        echo json_encode([
            'success' => true,
            'submitted' => true,
            'duplicate' => !$submittedFlag,
            'evaluation_status' => (!$submittedFlag || $isPureMcq) ? 'evaluated' : 'pending_manual_review',
            'message' => $submittedFlag
                ? ($isPureMcq ? 'Test submitted and evaluated' : 'Test submitted — pending manual review')
                : 'Test was already submitted',
        ]);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Answers saved']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('submit_answer failed for submission ' . $submissionId . ': ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save answers. Please try again.']);
    exit;
}