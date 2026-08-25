<?php
/**
 * AJAX: Get sections for a batch.
 * Returns unique sections within a given batch.
 */
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$batchId = (int)($_GET['batch_id'] ?? 0);
if ($batchId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid batch ID']);
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT DISTINCT section FROM batches WHERE id = ? AND section IS NOT NULL AND section != ''");
$stmt->execute([$batchId]);
$rows = $stmt->fetchAll();

$sections = array_map(fn($r) => $r['section'], $rows);
sort($sections);

echo json_encode(['sections' => $sections]);
