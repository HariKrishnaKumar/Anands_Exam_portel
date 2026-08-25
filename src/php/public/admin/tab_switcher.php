<?php
$pageTitle = 'Tab Switcher Monitor';
require_once __DIR__ . '/../../includes/admin_header.php';

$pdo = getDB();
$message = '';
$currentTestId = (int)($_GET['test_id'] ?? 0);

// ─── Timer extension ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    if ($_POST['action'] === 'extend_timer' && !empty($_POST['submission_id'])) {
        $extendMinutes = (int)($_POST['extend_minutes'] ?? 5);
        $stmt = $pdo->prepare("UPDATE submissions SET timer_extended_minutes = timer_extended_minutes + ? WHERE id = ?");
        $stmt->execute([$extendMinutes, (int)$_POST['submission_id']]);

        $stmt = $pdo->prepare("INSERT INTO tab_switch_logs (submission_id, switch_count, type, metadata) VALUES (?, ?, 'timer_extend', ?)");
        $stmt->execute([(int)$_POST['submission_id'], 0, json_encode(['extended_by' => $extendMinutes])]);

        $message = 'Timer extended by ' . $extendMinutes . ' minutes for submission #' . (int)$_POST['submission_id'];
    }
}

// ─── Tests list ──────────────────────────────────────────────
$tests = $pdo->query("
    SELECT t.id, t.title, b.name AS batch_name, c.name AS course_name, cl.name AS college_name,
           t.duration_minutes, t.status,
           (SELECT COUNT(*) FROM submissions s WHERE s.test_id = t.id) AS total_submissions
    FROM tests t
    JOIN batches b ON b.id = t.batch_id
    JOIN courses c ON c.id = b.course_id
    JOIN colleges cl ON cl.id = c.college_id
    ORDER BY t.created_at DESC
")->fetchAll();

// ─── Tab switch logs for selected test ───────────────────────
$logs = [];
$summary = [];
if ($currentTestId > 0) {
    // Summary per student
    $stmt = $pdo->prepare("
        SELECT s.id AS submission_id, st.name AS student_name, st.email, st.roll_number,
               s.started_at, s.submitted_at, s.status, s.timer_extended_minutes,
               COALESCE(switch_stats.switch_count, 0) AS switch_count,
               COALESCE(switch_stats.first_switch, '—') AS first_switch,
               COALESCE(switch_stats.last_switch, '—') AS last_switch
        FROM submissions s
        JOIN students st ON st.id = s.student_id
        LEFT JOIN (
            SELECT submission_id,
                   COUNT(*) AS switch_count,
                   MIN(timestamp) AS first_switch,
                   MAX(timestamp) AS last_switch
            FROM tab_switch_logs
            WHERE type = 'switch'
            GROUP BY submission_id
        ) switch_stats ON switch_stats.submission_id = s.id
        WHERE s.test_id = ?
        ORDER BY switch_stats.switch_count DESC, st.name
    ");
    $stmt->execute([$currentTestId]);
    $summary = $stmt->fetchAll();

    // Detailed logs if viewing a specific submission
    $viewSubmissionId = (int)($_GET['submission_id'] ?? 0);
    if ($viewSubmissionId > 0) {
        $stmt = $pdo->prepare("
            SELECT tsl.*
            FROM tab_switch_logs tsl
            WHERE tsl.submission_id = ? AND tsl.type = 'switch'
            ORDER BY tsl.timestamp
        ");
        $stmt->execute([$viewSubmissionId]);
        $logs = $stmt->fetchAll();
    }
}
?>

<?php if ($message): ?>
    <div class="alert alert-success">
        <?= icon('check-circle', 18, 'var(--green)') ?>
        <span><?= h($message) ?></span>
    </div>
<?php endif; ?>

<!-- Test Selector -->
<div class="panel">
    <div class="panel-header">
        <h2>Select Test</h2>
    </div>
    <div class="panel-body">
        <form method="GET" action="tab_switcher.php">
            <div class="form-row">
                <div class="form-group">
                    <label>Test</label>
                    <select class="form-select" name="test_id" onchange="this.form.submit()">
                        <option value="">— Select Test —</option>
                        <?php foreach ($tests as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $currentTestId === (int)$t['id'] ? 'selected' : '' ?>>
                                <?= h($t['title']) ?> — <?= h($t['batch_name']) ?> (<?= $t['total_submissions'] ?> submissions)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($currentTestId > 0): ?>
<!-- Tab Switch Summary -->
<div class="panel mt-4">
    <div class="panel-header">
        <h2>Tab Switch Summary</h2>
        <span class="text-muted text-sm">
            <?= count(array_filter($summary, fn($s) => (int)$s['switch_count'] > 0)) ?> students with switches
        </span>
    </div>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Roll #</th>
                    <th>Switches</th>
                    <th>First Switch</th>
                    <th>Last Switch</th>
                    <th>Extended</th>
                    <th>Status</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($summary)): ?>
                    <tr><td colspan="8" style="text-align:center;padding:32px;color:var(--gray-50);">No submissions found for this test.</td></tr>
                <?php else: ?>
                    <?php foreach ($summary as $s): ?>
                    <tr>
                        <td><strong><?= h($s['student_name']) ?></strong><br><span class="text-sm text-muted"><?= h($s['email']) ?></span></td>
                        <td class="text-sm"><?= h($s['roll_number']) ?></td>
                        <td>
                            <?php $cnt = (int)$s['switch_count']; ?>
                            <span class="badge <?= $cnt === 0 ? 'badge-success' : ($cnt <= 3 ? 'badge-pending' : 'badge-danger') ?>">
                                <?= $cnt ?>
                            </span>
                        </td>
                        <td class="text-sm"><?= $s['first_switch'] !== '—' ? formatDateTime($s['first_switch']) : '—' ?></td>
                        <td class="text-sm"><?= $s['last_switch'] !== '—' ? formatDateTime($s['last_switch']) : '—' ?></td>
                        <td class="text-sm">+<?= (int)$s['timer_extended_minutes'] ?> min</td>
                        <td>
                            <span class="badge <?= $s['status'] === 'evaluated' ? 'badge-success' : ($s['status'] === 'submitted' ? 'badge-active' : 'badge-pending') ?>">
                                <?= ucfirst($s['status']) ?>
                            </span>
                        </td>
                        <td class="actions">
                            <a href="tab_switcher.php?test_id=<?= $currentTestId ?>&submission_id=<?= $s['submission_id'] ?>"
                               class="btn btn-sm btn-ghost">Details</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Detailed Logs -->
<?php if (!empty($logs)): ?>
<div class="panel mt-4">
    <div class="panel-header">
        <h2>Detailed Tab Switch Log — Submission #<?= (int)$_GET['submission_id'] ?></h2>
        <a href="tab_switcher.php?test_id=<?= $currentTestId ?>" class="btn btn-sm btn-ghost">← Back to summary</a>
    </div>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Timestamp</th>
                    <th>Type</th>
                    <th>Metadata</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $i => $log): ?>
                <tr>
                    <td class="text-muted"><?= $i + 1 ?></td>
                    <td class="text-sm"><?= formatDateTime($log['timestamp']) ?></td>
                    <td>
                        <span class="badge <?= $log['type'] === 'switch' ? 'badge-danger' : 'badge-active' ?>">
                            <?= h($log['type']) ?>
                        </span>
                    </td>
                    <td class="text-sm text-muted"><?= h($log['metadata'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php elseif (isset($_GET['submission_id']) && (int)$_GET['submission_id'] > 0): ?>
<div class="panel mt-4">
    <div class="panel-header"><h2>No Logs</h2></div>
    <div class="panel-body">
        <p class="text-muted">No tab switch logs found for this submission.</p>
        <a href="tab_switcher.php?test_id=<?= $currentTestId ?>" class="btn btn-secondary mt-2">Back</a>
    </div>
</div>
<?php endif; ?>

<!-- Bulk Timer Extension -->
<div class="panel mt-4">
    <div class="panel-header">
        <h2>Timer Extension</h2>
    </div>
    <div class="panel-body">
        <p class="text-sm text-muted mb-2">Extend time for individual students who had tab switches (e.g., due to technical issues).</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:var(--space-3);">
            <?php foreach ($summary as $s): ?>
                <?php if ((int)$s['switch_count'] > 0): ?>
                <form method="POST" style="border:1px solid var(--gray-20);border-radius:var(--radius-md);padding:var(--space-3);display:flex;gap:var(--space-2);align-items:center;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="extend_timer">
                    <input type="hidden" name="submission_id" value="<?= $s['submission_id'] ?>">
                    <div style="flex:1;">
                        <strong style="font-size:0.75rem;"><?= h($s['student_name']) ?></strong>
                        <span class="text-sm text-muted" style="display:block;"><?= $s['switch_count'] ?> switches</span>
                    </div>
                    <select class="form-select" name="extend_minutes" style="width:65px;">
                        <option value="5">+5</option>
                        <option value="10">+10</option>
                        <option value="15">+15</option>
                        <option value="30">+30</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary">Go</button>
                </form>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (empty(array_filter($summary, fn($s) => (int)$s['switch_count'] > 0))): ?>
                <p class="text-sm text-muted">No students with tab switches.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>
