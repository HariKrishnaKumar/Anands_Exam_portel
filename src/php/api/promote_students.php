<?php
/**
 * API: Promote Students (IN-PLACE)
 *
 * POST only. Advances the batch's semester_order and every student's semester
 * in that batch, in one transaction. The batch is NOT renamed, NO new batches
 * are created, and NO students move between batches.
 * Admin session required (super_admin / platform_admin).
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

$pdo = getDB();

// Promotion is a management action — same gate batches.php applies to
// Add / Edit / Archive. Checked AFTER the admin_id test so a student session
// still gets 401, and BEFORE input validation so role failures are not masked.
if (!isset($_SESSION['admin_role'])) {
    try {
        $stmt = $pdo->prepare("SELECT role FROM admins WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $row = $stmt->fetch();
        $_SESSION['admin_role'] = $row['role'] ?? 'admin';
    } catch (Exception $e) {
        $_SESSION['admin_role'] = 'admin';
    }
}
$adminRole = $_SESSION['admin_role'] ?? 'admin';
if (!in_array($adminRole, ['super_admin', 'platform_admin'], true)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$batchId = isset($input['batch_id']) ? (int)$input['batch_id'] : 0;

if ($batchId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid batch ID']);
    exit;
}

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
        'to' => $preview['name'],
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

    $promotedStudents = promoteCohort(
        $pdo,
        $batchId,
        (int)$preview['next_semester'],
        (int)$preview['max_semesters']
    );

    $pdo->commit();

    echo json_encode([
        'status' => 'ok',
        'batch_id' => (int)$batchId,
        'from' => $preview['name'],
        'to' => $preview['name'],
        'from_semester' => (int)$preview['current_semester'],
        'next_semester' => (int)$preview['next_semester'],
        'student_count' => (int)$preview['student_count'],
        'promoted_students' => (int)$promotedStudents,
        'message' => "Promoted to semester {$preview['next_semester']} — {$promotedStudents} student(s) moved to the next semester. Batch name unchanged.",
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Promotion failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Promotion failed. Please try again.',
    ]);
}
