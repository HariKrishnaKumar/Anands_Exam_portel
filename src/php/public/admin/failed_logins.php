<?php
$pageTitle = 'Failed Login Log';
require_once __DIR__ . '/../../includes/admin_header.php';

$pdo = getDB();

// Filters
$searchEmail = trim($_GET['email'] ?? '');
$filterType  = $_GET['type'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Build query
$where = [];
$params = [];

if ($searchEmail !== '') {
    $where[] = "l.email LIKE ?";
    $params[] = '%' . $searchEmail . '%';
}
if ($filterType !== '') {
    $where[] = "l.attempt_type = ?";
    $params[] = $filterType;
}

$whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM failed_login_log l $whereClause");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

// Fetch page
$stmt = $pdo->prepare("
    SELECT l.* FROM failed_login_log l
    $whereClause
    ORDER BY l.attempted_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Available types for filter
$types = $pdo->query("SELECT DISTINCT attempt_type FROM failed_login_log ORDER BY attempt_type")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="panel">
    <div class="panel-header">
        <h2>Failed Login Log</h2>
        <span class="text-sm text-muted">Total: <strong><?= $total ?></strong> failed attempts</span>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" class="form-inline">
            <input class="form-input" type="text" name="email" placeholder="Search by email..." value="<?= h($searchEmail) ?>" style="max-width:240px;">
            <select class="form-select" name="type">
                <option value="">All types</option>
                <?php foreach ($types as $t): ?>
                    <option value="<?= h($t) ?>" <?= $filterType === $t ? 'selected' : '' ?>><?= h(str_replace('_', ' ', ucfirst($t))) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-secondary"><?= icon('filter', 14) ?> Filter</button>
            <?php if ($searchEmail || $filterType): ?>
                <a href="failed_logins.php" class="btn btn-sm btn-ghost">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (count($logs) === 0): ?>
        <div class="panel-body">
            <div class="empty-state">
                <div class="empty-icon">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16zm0 1a7 7 0 1 0 0 14 7 7 0 0 0 0-14zm0 9.5a.75.75 0 1 1 0 1.5.75.75 0 0 1 0-1.5zM10 6a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-1 0v-4A.5.5 0 0 1 10 6z"/></svg>
                </div>
                <h3>No Failed Login Attempts</h3>
                <p>All signups and logins have been successful.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Email</th>
                        <th>Name</th>
                        <th>IP Address</th>
                        <th>Type</th>
                        <th>Reason</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="text-muted"><?= $log['id'] ?></td>
                            <td><?= h($log['email']) ?></td>
                            <td><?= h($log['student_name'] ?? '-') ?></td>
                            <td style="font-family:var(--mono);font-size:var(--fs-12);"><?= h($log['ip_address']) ?></td>
                            <td>
                                <?php
                                    $attemptClass = 'neutral';
                                    if ($log['attempt_type'] === 'wrong_password' || $log['attempt_type'] === 'invalid_email' || $log['attempt_type'] === 'wrong_otp') $attemptClass = 'danger';
                                    elseif ($log['attempt_type'] === 'expired_otp') $attemptClass = 'pending';
                                    elseif ($log['attempt_type'] === 'duplicate_email' || $log['attempt_type'] === 'signup_unverified') $attemptClass = 'info';
                                ?>
                                <span class="badge badge-<?= $attemptClass ?>">
                                    <?= h(str_replace('_', ' ', ucfirst($log['attempt_type']))) ?>
                                </span>
                            </td>
                            <td class="text-sm" style="max-width:300px;overflow:hidden;text-overflow:ellipsis;"><?= h($log['reason'] ?? '-') ?></td>
                            <td class="text-sm text-muted"><?= formatDateTime($log['attempted_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>&email=<?= urlencode($searchEmail) ?>&type=<?= urlencode($filterType) ?>"
                       class="<?= $i === $page ? 'current' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Legend -->
<div class="panel" style="margin-top:var(--space-4);">
    <div class="panel-header">
        <h2>Attempt Types</h2>
    </div>
    <div class="panel-body">
        <div style="display:flex;flex-wrap:wrap;gap:var(--space-4);font-size:var(--fs-13);">
            <div><span class="badge badge-danger">wrong_password</span> Incorrect password entered</div>
            <div><span class="badge badge-danger">invalid_email</span> Email not found in any table</div>
            <div><span class="badge badge-danger">wrong_otp</span> Invalid OTP entered during email verification</div>
            <div><span class="badge badge-pending">expired_otp</span> OTP expired before verification</div>
            <div><span class="badge badge-info">duplicate_email</span> Attempted signup with existing email</div>
            <div><span class="badge badge-info">signup_unverified</span> Signed up but hasn't verified email yet</div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>
