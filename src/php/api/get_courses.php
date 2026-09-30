<?php
/**
 * AJAX: Get courses for a college.
 */
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

// Read-only endpoint: only GET is meaningful here, so reject anything else
// instead of silently returning data for a mutating method.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$collegeId = (int)($_GET['college_id'] ?? 0);
if ($collegeId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid college ID']);
    exit;
}

$pdo = getDB();
if (isset($_GET['active']) && (int)$_GET['active'] === 1) {
    $stmt = $pdo->prepare("SELECT id, name FROM courses WHERE college_id = ? AND status = 'active' ORDER BY name");
} else {
    $stmt = $pdo->prepare("SELECT id, name FROM courses WHERE college_id = ? ORDER BY name");
}
$stmt->execute([$collegeId]);
$courses = $stmt->fetchAll();

echo json_encode($courses);
