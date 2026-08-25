<?php
/**
 * AJAX: Get batches for a course.
 */
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$courseId = (int)($_GET['course_id'] ?? 0);
if ($courseId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid course ID']);
    exit;
}

$pdo = getDB();
// Soft-delete awareness: ?active=1 excludes archived batches (used by creation
// flows). By default ALL batches are returned so retained/archived records stay
// manageable in edit flows and student management screens.
if (isset($_GET['active']) && (int)$_GET['active'] === 1) {
    $stmt = $pdo->prepare("SELECT id, name, section FROM batches WHERE course_id = ? AND status = 'active' ORDER BY name, section");
} else {
    $stmt = $pdo->prepare("SELECT id, name, section FROM batches WHERE course_id = ? ORDER BY name, section");
}
$stmt->execute([$courseId]);
$batches = $stmt->fetchAll();

// Add display_name for each batch
foreach ($batches as &$b) {
    $b['display_name'] = $b['name'] . ($b['section'] ? ' - Section ' . $b['section'] : '');
}
unset($b);

echo json_encode($batches);
