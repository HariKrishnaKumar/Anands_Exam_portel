<?php
$pageTitle = 'Pending Verifications';
require_once __DIR__ . '/../../includes/admin_header.php';

$pdo = getDB();

// Handle delete stale entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCsrf();
    if ($_POST['action'] === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM unverified_students WHERE id = ?")->execute([$id]);
            $message = 'Pending registration removed.';
        }
    } elseif ($_POST['action'] === 'delete_all') {
        $pdo->exec("DELETE FROM unverified_students");
        $message = 'All pending registrations removed.';
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$total = (int)$pdo->query("SELECT COUNT(*) FROM unverified_students")->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$students = $pdo->prepare("
    SELECT * FROM unverified_students
    ORDER BY created_at DESC
    LIMIT $perPage OFFSET $offset
");
$students->execute();
$rows = $students->fetchAll();
?>

<div class="panel">
    <div class="panel-header">
        <h2>Pending Verifications</h2>
        <?php if ($total > 0): ?>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete all pending registrations?')">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete_all">
                <button type="submit" class="btn btn-sm btn-danger"><?= icon('trash', 14) ?> Delete All</button>
            </form>
        <?php endif; ?>
    </div>

    <div style="padding:var(--space-3) var(--space-5);border-bottom:1px solid var(--gray-10);">
        <span class="text-sm text-muted"><strong><?= $total ?></strong> student(s) waiting for email verification</span>
    </div>

    <?php if (isset($message)): ?>
        <div class="alert alert-success" style="margin:var(--space-4) var(--space-5) 0;">
            <?= icon('check-circle', 18, 'var(--green)') ?>
            <span><?= h($message) ?></span>
        </div>
    <?php endif; ?>

    <?php if (count($rows) === 0): ?>
        <div class="panel-body">
            <div class="empty-state">
                <div class="empty-icon">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                </div>
                <h3>No Pending Verifications</h3>
                <p>All signups have completed email verification.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>College</th>
                        <th>Course</th>
                        <th>Section</th>
                        <th>OTP Status</th>
                        <th>Registered</th>
                        <th class="actions">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $s): 
                        $otpStatus = '';
                        if (empty($s['otp_hash'])) {
                            $otpStatus = '<span class="badge badge-pending">No OTP</span>';
                        } elseif (strtotime($s['otp_expires_at']) < time()) {
                            $otpStatus = '<span class="badge badge-danger">Expired</span>';
                        } else {
                            $otpStatus = '<span class="badge badge-success">Active</span>';
                        }
                    ?>
                        <tr>
                            <td class="text-muted"><?= $s['id'] ?></td>
                            <td><?= h($s['name']) ?></td>
                            <td class="text-sm"><?= h($s['email']) ?></td>
                            <td class="text-sm"><?= h($s['college_name']) ?></td>
                            <td class="text-sm"><?= h($s['course_name']) ?></td>
                            <td class="text-sm"><?= !empty($s['section']) ? h($s['section']) : '<span class="text-muted">—</span>' ?></td>
                            <td><?= $otpStatus ?></td>
                            <td class="text-sm text-muted"><?= formatDateTime($s['created_at']) ?></td>
                            <td class="actions">
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this pending registration?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><?= icon('trash', 14) ?> Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>" class="<?= $i === $page ? 'current' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>
