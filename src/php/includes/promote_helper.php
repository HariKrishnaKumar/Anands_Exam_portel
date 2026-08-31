<?php
/**
 * Student Promotion Helper (IN-PLACE)
 *
 * Promotion updates the batch's semester_order and name directly.
 * NO new batches are created. NO students are moved between batches.
 * This prevents duplicate/orphan batches entirely.
 */

/**
 * Get the maximum number of semesters for a course.
 *
 * @param PDO $pdo Database connection
 * @param int $courseId Course ID
 * @return int Maximum semesters (duration_years * 2)
 */
function getMaxSemesters(PDO $pdo, int $courseId): int
{
    $stmt = $pdo->prepare("SELECT duration_years FROM courses WHERE id = ?");
    $stmt->execute([$courseId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $years = $row ? (int)$row['duration_years'] : 4;
    return $years * 2;
}

/**
 * Update a batch's semester order in-place.
 * Renames the batch to reflect the new semester number.
 *
 * @param PDO $pdo Database connection
 * @param int $batchId Batch ID to promote
 * @param int $nextSem New semester number
 * @param string $newName New batch name
 * @return bool true on success
 */
function promoteBatchInPlace(PDO $pdo, int $batchId, int $nextSem, string $newName): bool
{
    $stmt = $pdo->prepare("UPDATE batches SET semester_order = ?, name = ? WHERE id = ?");
    $stmt->execute([$nextSem, $newName, $batchId]);
    return $stmt->rowCount() > 0 || $stmt->errorCode() === '00000';
}

/**
 * Check if promoting this batch would create a duplicate.
 * Returns true if another active batch already has the target semester for this course.
 *
 * @param PDO $pdo Database connection
 * @param int $courseId Course ID
 * @param int $nextSem Target semester number
 * @param int $currentBatchId The batch being promoted (to exclude from check)
 * @return array|null ['id' => ..., 'name' => ...] of conflicting batch, or null if safe
 */
function checkPromoteDuplicate(PDO $pdo, int $courseId, int $nextSem, int $currentBatchId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT id, name FROM batches WHERE course_id = ? AND semester_order = ? AND id != ? AND status = 'active' LIMIT 1"
    );
    $stmt->execute([$courseId, $nextSem, $currentBatchId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? ['id' => (int)$row['id'], 'name' => $row['name']] : null;
}

/**
 * Build the promoted batch name.
 * If batch name already contains a semester pattern, replace it.
 * Otherwise, append " - Sem N" to the name.
 *
 * @param string $currentName Current batch name
 * @param int $nextSem Next semester number
 * @return string Updated batch name
 */
function buildPromotedBatchName(string $currentName, int $nextSem): string
{
    // Try to replace existing "Sem X" / "Semester X" pattern
    if (preg_match('/\b(?:sem|semester)\s*\d+\b/i', $currentName)) {
        return preg_replace('/\b(?:sem|semester)\s*\d+\b/i', 'Sem ' . $nextSem, $currentName);
    }
    // No semester pattern found — append it
    return $currentName . ' - Sem ' . $nextSem;
}

/**
 * Get batch promotion preview info.
 *
 * @param PDO $pdo Database connection
 * @param int $batchId Batch ID
 * @return array|null Batch info with student count, course duration, etc.
 */
function getPromotionPreview(PDO $pdo, int $batchId): ?array
{
    $stmt = $pdo->prepare("
        SELECT b.id, b.name, b.course_id, b.semester_order,
               c.name AS course_name, c.duration_years,
               (SELECT COUNT(*) FROM students WHERE batch_id = b.id) AS student_count
        FROM batches b
        JOIN courses c ON c.id = b.course_id
        WHERE b.id = ?
    ");
    $stmt->execute([$batchId]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$batch) {
        return null;
    }

    $batch['max_semesters'] = (int)$batch['duration_years'] * 2;
    $batch['current_semester'] = $batch['semester_order'] !== null ? (int)$batch['semester_order'] : null;

    if ($batch['current_semester'] !== null) {
        $batch['next_semester'] = $batch['current_semester'] + 1;
        $batch['can_promote'] = $batch['next_semester'] <= $batch['max_semesters'];
        $batch['target_name'] = buildPromotedBatchName($batch['name'], $batch['next_semester']);

        // Check for duplicate
        $conflict = checkPromoteDuplicate($pdo, $batch['course_id'], $batch['next_semester'], $batchId);
        $batch['duplicate_conflict'] = $conflict;
    } else {
        $batch['next_semester'] = null;
        $batch['can_promote'] = false;
        $batch['target_name'] = null;
        $batch['duplicate_conflict'] = null;
    }

    return $batch;
}
