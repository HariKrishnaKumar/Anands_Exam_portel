<?php
/**
 * Permanent Delete — archived college (hard delete with full cascade).
 *
 * Endpoint:  POST /api/delete_college.php
 * Payload:   JSON { "college_id": 123, "csrf_token": "..." }
 *            (form-encoded POST also accepted)
 *
 * Safety contract:
 *  - Admin session required (401 otherwise).
 *  - Super/platform admin roles only (403 otherwise).
 *  - Valid CSRF token required (403 otherwise).
 *  - The college must currently be ARCHIVED (soft-deleted). Active colleges
 *    are rejected so an accidental request can never wipe live data.
 *  - A strict InnoDB transaction deletes every downstream record in
 *    dependency order (children before parents) so no FK constraint can
 *    ever abort the cascade half-way. The archived-status guard is
 *    re-checked inside the transaction under a row lock, then the colleges
 *    row itself is deleted only after every child is gone.
 *  - Response is always JSON: { success, message } (or error details).
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

startSession();

header('Content-Type: application/json');

// 1) Admin session required
if (!isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required. Please log in again.']);
    exit;
}

// 2) Role gate — mirrors colleges.php $canManage
$adminRole = $_SESSION['admin_role'] ?? 'admin';
if (!in_array($adminRole, ['super_admin', 'platform_admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied. Only super/platform admins can permanently delete a college.']);
    exit;
}

// 3) Uniform body parsing (JSON or form) + CSRF check
$body = parseRequestBody();
if (isset($body['csrf_token'])) {
    // JSON bodies don't populate $_POST — bridge the token so the shared
    // requireCsrf() helper (standard form check) stays the single source of truth.
    $_POST['csrf_token'] = (string)$body['csrf_token'];
}
requireCsrf();

$collegeId = (int)($body['college_id'] ?? 0);
if ($collegeId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid college_id.']);
    exit;
}

$pdo = getDB();

try {
    $pdo->beginTransaction();

    // Lock the college row inside the transaction so a concurrent
    // archive/restore cannot race the cascade.
    $stmt = $pdo->prepare("SELECT id, name, status FROM colleges WHERE id = ? FOR UPDATE");
    $stmt->execute([$collegeId]);
    $college = $stmt->fetch();

    if (!$college) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'College not found.']);
        exit;
    }

    if ($college['status'] !== 'archived') {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Only archived colleges can be permanently deleted. Archive the college first, then retry.',
        ]);
        exit;
    }

    // Belt-and-braces: re-assert the guard inside the same transaction with
    // a dedicated row count before a single child row is modified. Even if
    // a stale/racy compile ever re-entered this path, children are never
    // deleted while the college is still active.
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM colleges WHERE id = ? AND status = 'archived'");
    $stmt->execute([$collegeId]);
    if ((int)$stmt->fetchColumn() !== 1) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Only archived colleges can be permanently deleted. Archive the college first, then retry.',
        ]);
        exit;
    }

    // ── Cascade deletes, dependency order (children → parents).
    // Each statement uses exactly one bound parameter.
    // Subqueries always read from a DIFFERENT table than the one being
    // deleted, so MySQL/MariaDB 1093 self-reference errors never occur.
    // Manual cleanup is used on purpose: the schema uses RESTRICT FKs
    // and tables without FK constraints (guest_entries, pci_records,
    // tab_switch_logs, etc.) must be cleared explicitly.
    $deleteStatements = [
        // 1) Student answers / attempts / results
        "DELETE FROM student_answers
         WHERE submission_id IN (
             SELECT s.id FROM submissions s
             WHERE s.test_id IN (
                 SELECT t.id FROM tests t
                 WHERE t.batch_id IN (
                     SELECT b.id FROM batches b
                     WHERE b.course_id IN (
                         SELECT co.id FROM courses co WHERE co.college_id = ?
                     )
                 )
             )
         )",

        // 2) Tab-switch logs (attempt metadata)
        "DELETE FROM tab_switch_logs
         WHERE submission_id IN (
             SELECT s.id FROM submissions s
             WHERE s.test_id IN (
                 SELECT t.id FROM tests t
                 WHERE t.batch_id IN (
                     SELECT b.id FROM batches b
                     WHERE b.course_id IN (
                         SELECT co.id FROM courses co WHERE co.college_id = ?
                     )
                 )
             )
         )",

        // 3) PCI analytics records
        "DELETE FROM pci_records
         WHERE test_id IN (
             SELECT t.id FROM tests t
             WHERE t.batch_id IN (
                 SELECT b.id FROM batches b
                 WHERE b.course_id IN (
                     SELECT co.id FROM courses co WHERE co.college_id = ?
                 )
             )
         )",
        "DELETE FROM pci_records
         WHERE student_id IN (
             SELECT s.id FROM students s
             WHERE s.batch_id IN (
                 SELECT b.id FROM batches b
                 WHERE b.course_id IN (
                     SELECT co.id FROM courses co WHERE co.college_id = ?
                 )
             )
         )",

        // 4) Submissions (ordered by test)
        "DELETE FROM submissions
         WHERE test_id IN (
             SELECT t.id FROM tests t
             WHERE t.batch_id IN (
                 SELECT b.id FROM batches b
                 WHERE b.course_id IN (
                     SELECT co.id FROM courses co WHERE co.college_id = ?
                 )
             )
         )",
        // 4b) Submissions (ordered by student)
        "DELETE FROM submissions
         WHERE student_id IN (
             SELECT s.id FROM students s
             WHERE s.batch_id IN (
                 SELECT b.id FROM batches b
                 WHERE b.course_id IN (
                     SELECT co.id FROM courses co WHERE co.college_id = ?
                 )
             )
         )",

        // 5) Guest / QR access entries
        "DELETE FROM guest_entries
         WHERE batch_id IN (
             SELECT b.id FROM batches b
             WHERE b.course_id IN (
                 SELECT co.id FROM courses co WHERE co.college_id = ?
             )
         )",
        "DELETE FROM guest_entries
         WHERE student_id IN (
             SELECT s.id FROM students s
             WHERE s.batch_id IN (
                 SELECT b.id FROM batches b
                 WHERE b.course_id IN (
                     SELECT co.id FROM courses co WHERE co.college_id = ?
                 )
             )
         )",

        // 6) Test questions
        "DELETE FROM questions
         WHERE test_id IN (
             SELECT t.id FROM tests t
             WHERE t.batch_id IN (
                 SELECT b.id FROM batches b
                 WHERE b.course_id IN (
                     SELECT co.id FROM courses co WHERE co.college_id = ?
                 )
             )
         )",

        // 7) Tests (assignments/mappings live against batches)
        "DELETE FROM tests
         WHERE batch_id IN (
             SELECT b.id FROM batches b
             WHERE b.course_id IN (
                 SELECT co.id FROM courses co WHERE co.college_id = ?
             )
         )",

        // 8) Pending / unverified student registrations
        "DELETE FROM unverified_students
         WHERE batch_id IN (
             SELECT b.id FROM batches b
             WHERE b.course_id IN (
                 SELECT co.id FROM courses co WHERE co.college_id = ?
             )
         )",

        // 9) Students belonging to the college's batches
        "DELETE FROM students
         WHERE batch_id IN (
             SELECT b.id FROM batches b
             WHERE b.course_id IN (
                 SELECT co.id FROM courses co WHERE co.college_id = ?
             )
         )",

        // 10) Batch–stream junction rows for the college
        "DELETE FROM college_batches WHERE college_id = ?",

        // 11) Batches (via the college's courses)
        "DELETE FROM batches
         WHERE course_id IN (
             SELECT co.id FROM courses co WHERE co.college_id = ?
         )",

        // 12) Stream definitions
        "DELETE FROM college_streams WHERE college_id = ?",

        // 13) Courses
        "DELETE FROM courses WHERE college_id = ?",
    ];

    $counts = [];
    foreach ($deleteStatements as $sql) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$collegeId]);
        $counts[] = $stmt->rowCount();
    }

    // 14) The primary row — guarded by the archived-status check that was
    //     validated under the row lock above.
    $stmt = $pdo->prepare("DELETE FROM colleges WHERE id = ? AND status = 'archived'");
    $stmt->execute([$collegeId]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('College status changed mid-operation — transaction aborted.');
    }

    // Audit trail: written BEST-EFFORT inside the transaction, enriched with
    // full caller context so any unexpected invocation is fully traceable.
    $requestContext = [
        'method'    => $_SERVER['REQUEST_METHOD'] ?? '',
        'uri'       => $_SERVER['REQUEST_URI'] ?? '',
        'referer'   => $_SERVER['HTTP_REFERER'] ?? '',
        'user_agent'=> $_SERVER['HTTP_USER_AGENT'] ?? '',
        'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'session_id'=> session_id(),
    ];
    try {
        $audit = $pdo->prepare(
            "INSERT INTO audit_log (user_id, user_email, user_role, action, entity_type, entity_id, details, ip_address)
             VALUES (?, ?, ?, 'permanent_delete', 'college', ?, ?, ?)"
        );
        $audit->execute([
            (int)($_SESSION['admin_id'] ?? 0),
            $_SESSION['admin_email'] ?? '',
            $adminRole,
            $collegeId,
            json_encode([
                'college_name'  => $college['name'],
                'deleted'       => array_sum($counts) + 1,
                'breakdown'     => [
                    'student_answers'    => $counts[0],
                    'tab_switch_logs'    => $counts[1],
                    'pci_records'        => $counts[2] + $counts[3],
                    'submissions'        => $counts[4] + $counts[5],
                    'guest_entries'      => $counts[6] + $counts[7],
                    'questions'          => $counts[8],
                    'tests'              => $counts[9],
                    'unverified_students'=> $counts[10],
                    'students'           => $counts[11],
                    'college_batches'    => $counts[12],
                    'batches'            => $counts[13],
                    'college_streams'    => $counts[14],
                    'courses'            => $counts[15],
                ],
                'request'       => $requestContext,
            ]),
            $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    } catch (Throwable $e) {
        error_log('delete_college: audit log write skipped: ' . $e->getMessage());
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'College and all associated data permanently deleted.',
        'deleted' => [
            'college_id'  => $collegeId,
            'name'        => $college['name'],
            'rows_removed'=> array_sum($counts) + 1,
        ],
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('delete_college failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Permanent deletion failed. No data was changed.']);
    exit;
}