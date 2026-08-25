<?php
/**
 * Notifications — full feed + JSON endpoints.
 * ?action=poll        → JSON {count, html} for the bell panel
 * ?action=mark_all    → POST (CSRF) mark everything read → JSON
 * ?action=mark_one    → POST (CSRF) mark one read → JSON
 * default             → full admin page listing all notifications
 */
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
startSession();
requireAdmin();

$pdo = getDB();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'poll') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM admin_notifications WHERE is_read = 0")->fetchColumn();
        $items = $pdo->query("SELECT * FROM admin_notifications ORDER BY created_at DESC LIMIT 8")->fetchAll();
        echo json_encode(['count' => $count, 'html' => renderNotificationItems($items)]);
    } catch (Throwable $e) {
        echo json_encode(['count' => 0, 'html' => renderNotificationItems([])]);
    }
    exit;
}

if ($action === 'mark_all' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $pdo->query("UPDATE admin_notifications SET is_read = 1 WHERE is_read = 0");
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'count' => 0]);
    exit;
}

if ($action === 'mark_one' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("UPDATE admin_notifications SET is_read = 1 WHERE id = ?")->execute([$id]);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
    exit;
}

// ─── Full page ───────────────────────────────────────────
$pageTitle = 'Notifications';
require_once __DIR__ . '/../../includes/admin_header.php';

$all = $pdo->query("SELECT * FROM admin_notifications ORDER BY created_at DESC LIMIT 100")->fetchAll();
$unread = (int)$pdo->query("SELECT COUNT(*) FROM admin_notifications WHERE is_read = 0")->fetchColumn();

$typeBadge = [
    'student_account' => ['label' => 'Account',    'class' => 'badge-warning'],
    'guest_link'      => ['label' => 'Guest Link', 'class' => 'badge-info'],
    'qr'              => ['label' => 'QR',         'class' => 'badge-info'],
    'test'            => ['label' => 'Test',       'class' => 'badge-danger'],
    'system'          => ['label' => 'System',     'class' => 'badge-danger'],
];
?>

<div class="dashboard-header" style="margin-bottom:var(--space-4);">
    <div class="dashboard-header-left">
        <h1>Notifications</h1>
        <p class="dashboard-subtitle">Alerts: failed registrations, broken links/QRs, test failures &amp; system errors</p>
    </div>
    <div class="dashboard-header-right">
        <?php if ($unread > 0): ?>
        <form method="POST" action="notifications.php" style="display:inline;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="mark_all">
            <button type="submit" class="btn btn-sm btn-secondary">Mark all read (<?= $unread ?>)</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="card-flat">
    <table class="data-table">
        <thead>
            <tr>
                <th>Type</th>
                <th>Message</th>
                <th>Link</th>
                <th>Time</th>
                <th>Status</th>
                <th class="actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($all)): ?>
                <tr><td colspan="6" class="text-center" style="padding:32px;color:var(--gray-50);">No notifications yet.</td></tr>
            <?php else: foreach ($all as $n): ?>
                <?php $tb = $typeBadge[$n['type']] ?? ['label' => $n['type'], 'class' => 'badge']; ?>
                <tr<?= empty($n['is_read']) ? ' style="background:var(--gray-5);"' : '' ?>>
                    <td><span class="badge <?= $tb['class'] ?>"><?= h($tb['label']) ?></span></td>
                    <td>
                        <strong><?= h($n['title']) ?></strong>
                        <?php if (!empty($n['message'])): ?>
                            <div class="text-sm text-muted" style="max-width:480px;"><?= h($n['message']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-sm">
                        <?php if (!empty($n['link'])): ?>
                            <a href="<?= h($n['link']) ?>" class="text-accent" style="text-decoration:underline;">View</a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="text-sm text-muted"><?= formatDateTime($n['created_at']) ?></td>
                    <td><?= empty($n['is_read']) ? '<span class="badge badge-active">Unread</span>' : '<span class="text-muted text-sm">Read</span>' ?></td>
                    <td class="actions">
                        <?php if (empty($n['is_read'])): ?>
                        <form method="POST" action="notifications.php" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="mark_one">
                            <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-ghost">Mark read</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>