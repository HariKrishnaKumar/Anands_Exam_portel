<?php
$pageTitle = 'College Dashboard';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
startSession();
requireAdmin();

$collegeId = (int)($_GET['id'] ?? 0);
if (!$collegeId) {
    redirect('/admin/colleges.php');
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT id FROM colleges WHERE id = ?");
$stmt->execute([$collegeId]);
if (!$stmt->fetch()) {
    flash('error', 'College not found.');
    redirect('/admin/colleges.php');
}

require_once __DIR__ . '/../../includes/admin_header.php';

$pdo = getDB();
$stmt = $pdo->prepare("
    SELECT c.*,
           (SELECT COUNT(*) FROM college_streams WHERE college_id = c.id) AS stream_count,
           (SELECT COUNT(*) FROM college_batches WHERE college_id = c.id) AS batch_count
    FROM colleges c WHERE c.id = ?
");
$stmt->execute([$collegeId]);
$college = $stmt->fetch();

if (!$college) {
    flash('error', 'College not found.');
    redirect('/admin/colleges.php');
}

// Fetch streams
$streams = $pdo->prepare("SELECT * FROM college_streams WHERE college_id = ? ORDER BY stream_name");
$streams->execute([$collegeId]);
$streams = $streams->fetchAll();

// Fetch batches
$batches = $pdo->prepare("
    SELECT cb.*, cs.stream_name
    FROM college_batches cb
    JOIN college_streams cs ON cs.id = cb.stream_id
    WHERE cb.college_id = ?
    ORDER BY cs.stream_name, cb.joining_year
");
$batches->execute([$collegeId]);
$batches = $batches->fetchAll();

$flashMsg = flashMessage();
?>

<div class="dashboard-header" style="margin-bottom:var(--space-4);">
    <div class="dashboard-header-left">
        <h1><?= h($college['name']) ?></h1>
        <div class="dashboard-subtitle">
            College Code: <?= h($college['college_code']) ?> &middot;
            Nick Name: <?= h($college['nick_name']) ?>
        </div>
    </div>
    <div class="dashboard-header-right">
        <a href="<?= BASE_URL ?>/admin/colleges.php" class="btn btn-ghost btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to Colleges
        </a>
    </div>
</div>

<?= $flashMsg ?>

<div class="analytics-grid" style="margin-bottom:var(--space-6);">
    <div class="analytics-card">
        <div class="analytics-card-header">
            <h3>College Information</h3>
        </div>
        <div class="analytics-card-body" style="padding:var(--space-4);">
            <table class="data-table" style="margin:0;">
                <tbody>
                    <tr><td style="font-weight:600;width:140px;">College Code</td><td><?= h($college['college_code']) ?></td></tr>
                    <tr><td style="font-weight:600;">Full Name</td><td><?= h($college['name']) ?></td></tr>
                    <tr><td style="font-weight:600;">Nick Name</td><td><?= h($college['nick_name']) ?></td></tr>
                    <tr><td style="font-weight:600;">Established</td><td><?= $college['established_year'] ?: '—' ?></td></tr>
                    <tr><td style="font-weight:600;">Website</td><td><?= $college['website'] ? '<a href="' . h($college['website']) . '" target="_blank">' . h($college['website']) . '</a>' : '—' ?></td></tr>
                    <tr><td style="font-weight:600;">Email</td><td><?= h($college['email'] ?: '—') ?></td></tr>
                    <tr><td style="font-weight:600;">Phone</td><td><?= h($college['phone'] ?: '—') ?></td></tr>
                    <tr><td style="font-weight:600;">Address</td><td><?= nl2br(h($college['address'] ?: '—')) ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="analytics-card">
        <div class="analytics-card-header">
            <h3>Academic Information</h3>
        </div>
        <div class="analytics-card-body" style="padding:var(--space-4);">
            <table class="data-table" style="margin:0;">
                <tbody>
                    <tr><td style="font-weight:600;width:140px;">University</td><td><?= h($college['recognized_university'] ?: '—') ?></td></tr>
                    <tr><td style="font-weight:600;">Affiliated</td><td><?= h($college['affiliated_university'] ?: '—') ?></td></tr>
                    <tr><td style="font-weight:600;">Autonomous</td><td><?= h($college['autonomous'] ?: '—') ?></td></tr>
                    <tr><td style="font-weight:600;">NAAC</td><td><?= $college['accreditation_naac'] ? ($college['naac_grade'] ? 'Grade ' . h($college['naac_grade']) : 'Yes') : 'No' ?></td></tr>
                    <tr><td style="font-weight:600;">NBA</td><td><?= $college['accreditation_nba'] ? 'Yes' : 'No' ?></td></tr>
                    <tr><td style="font-weight:600;">AICTE</td><td><?= $college['accreditation_aicte'] ? 'Yes' : 'No' ?></td></tr>
                    <tr><td style="font-weight:600;">UGC</td><td><?= $college['accreditation_ugc'] ? 'Yes' : 'No' ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="tables-grid" style="margin-bottom:var(--space-6);">
    <div class="table-card">
        <div class="table-card-header">
            <h3>Streams (<?= count($streams) ?>)</h3>
        </div>
        <div class="table-card-body">
            <table class="data-table">
                <thead>
                    <tr><th>#</th><th>Stream Name</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($streams)): ?>
                        <tr><td colspan="2" style="text-align:center;padding:var(--space-6);color:var(--gray-50);">No streams added.</td></tr>
                    <?php else: ?>
                        <?php foreach ($streams as $i => $s): ?>
                        <tr>
                            <td class="text-muted"><?= $i + 1 ?></td>
                            <td><?= h($s['stream_name']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="table-card">
        <div class="table-card-header">
            <h3>Batches (<?= count($batches) ?>)</h3>
        </div>
        <div class="table-card-body">
            <table class="data-table">
                <thead>
                    <tr><th>Batch Nick Name</th><th>Stream</th><th>Academic Year</th><th>Duration</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($batches)): ?>
                        <tr><td colspan="5" style="text-align:center;padding:var(--space-6);color:var(--gray-50);">No batches added.</td></tr>
                    <?php else: ?>
                        <?php foreach ($batches as $b): ?>
                        <tr>
                            <td><strong><?= h($b['batch_nick_name']) ?></strong></td>
                            <td><?= h($b['stream_name']) ?></td>
                            <td class="text-sm"><?= h($b['academic_year']) ?></td>
                            <td class="text-sm"><?= (int)$b['course_duration'] ?> yrs</td>
                            <td><span class="badge badge-<?= $b['status'] === 'active' ? 'active' : ($b['status'] === 'upcoming' ? 'pending' : 'success') ?>"><?= ucfirst(h($b['status'])) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>
