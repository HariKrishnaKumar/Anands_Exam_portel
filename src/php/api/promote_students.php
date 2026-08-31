<?php
/**
 * API: Promote Students (IN-PLACE)
 *
 * POST only. Updates the batch's semester_order and name directly.
 * NO new batches are created. NO students are moved between batches.
 * Admin session required.
 */

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/promote_helper.php';

startSession();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['admin_id'])) {
    error_log('promote_students: Unauthorized | session_id=' . session_id()
        . ' | status=' . session_status()
        . ' | keys=' . implode(',', array_keys($_SESSION))
        . ' | method=' . ($_SERVER['REQUEST_METHOD'] ?? ''));
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$batchId = isset($input['batch_id']) ? (int)$input['batch_id'] : 0;

if ($batchId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid batch ID']);
    exit;
}

$pdo = getDB();

// Get preview info
$preview = getPromotionPreview($pdo, $batchId);

if (!$preview) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Batch not found']);
    exit;
}

// Validate semester is assigned
if ($preview['current_semester'] === null) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'This batch has no semester number assigned. Edit the batch to set a semester order before promoting.',
    ]);
    exit;
}

// Validate not exceeding max semesters
if (!$preview['can_promote']) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => "Cannot promote beyond semester {$preview['max_semesters']}. Course \"{$preview['course_name']}\" is {$preview['duration_years']} year(s) ({$preview['max_semesters']} semesters max).",
    ]);
    exit;
}

// Validate no duplicate conflict
if ($preview['duplicate_conflict']) {
    $conflict = $preview['duplicate_conflict'];
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => "Cannot promote: Batch \"{$conflict['name']}\" (ID #{$conflict['id']}) already exists for semester {$preview['next_semester']} in this course. Please archive or rename it first.",
    ]);
    exit;
}

// Preview mode — return info without promoting
$isPreview = !empty($input['preview']);
if ($isPreview) {
    echo json_encode([
        'status' => 'ok',
        'from' => $preview['name'],
        'to' => $preview['target_name'],
        'batch_id' => (int)$preview['id'],
        'student_count' => (int)$preview['student_count'],
        'course_name' => $preview['course_name'],
        'duration_years' => (int)$preview['duration_years'],
        'max_semesters' => (int)$preview['max_semesters'],
        'current_semester' => (int)$preview['current_semester'],
        'next_semester' => (int)$preview['next_semester'],
    ]);
    exit;
}

// ─── Execute in-place promotion ───
try {
    $pdo->beginTransaction();

    $ok = promoteBatchInPlace($pdo, $batchId, $preview['next_semester'], $preview['target_name']);

    $pdo->commit();

    if ($ok) {
        echo json_encode([
            'status' => 'ok',
            'batch_id' => (int)$batchId,
            'from' => $preview['name'],
            'to' => $preview['target_name'],
            'student_count' => (int)$preview['student_count'],
            'message' => "Batch promoted from \"{$preview['name']}\" to \"{$preview['target_name']}\" ({$preview['student_count']} students stay in batch)",
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Promotion returned no rows. The batch may not have changed.',
        ]);
    }
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Promotion failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Promotion failed. Please try again.',
    ]);
}
