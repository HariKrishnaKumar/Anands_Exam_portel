<?php
/**
 * AJAX: Get college_id for a course (for batch edit cascading).
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
$stmt = $pdo->prepare("SELECT id, college_id, name FROM courses WHERE id = ?");
$stmt->execute([$courseId]);
$course = $stmt->fetch();

if (!$course) {
    http_response_code(404);
    echo json_encode(['error' => 'Course not found']);
    exit;
}

echo json_encode($course);
