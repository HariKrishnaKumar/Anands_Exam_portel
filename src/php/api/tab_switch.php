<?php
/**
 * Log tab switch events from the student test interface.
 * POST: JSON body or form data with test_id, submission_id, switch_count, csrf_token
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

// Accept JSON bodies AND standard form posts uniformly
$input = parseRequestBody();
if (empty($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or empty request body']);
    exit;
}

if (!isset($input['csrf_token']) || !validateCsrfToken($input['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$testId = (int)($input['test_id'] ?? 0);
$submissionId = $input['submission_id'] ?? '';
$switchCount = (int)($input['switch_count'] ?? 1);

if ($testId <= 0 || empty($submissionId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing test or submission info']);
    exit;
}

// For guest mode, just acknowledge (we don't persist guest tab switches)
if (strpos($submissionId, 'guest_') === 0) {
    echo json_encode(['success' => true, 'message' => 'Guest tab switch noted']);
    exit;
}

$pdo = getDB();

// Check submission exists and is NOT submitted (block late logs)
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
    echo json_encode(['error' => 'Test already submitted — tab switch rejected', 'rejected' => true]);
    exit;
}

// Log the tab switch with millisecond timestamp
$stmt = $pdo->prepare("
    INSERT INTO tab_switch_logs (submission_id, switch_count, type, timestamp)
    VALUES (?, ?, 'switch', NOW(3))
");
$stmt->execute([$submissionId, $switchCount]);

// Note: running count is derived from the logs table (tab_switch_count column does not exist in schema)

echo json_encode(['success' => true]);
