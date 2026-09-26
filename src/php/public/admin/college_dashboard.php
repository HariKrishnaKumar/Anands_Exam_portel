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

// ─── FACULTY CREDENTIAL ASSIGNMENT (admin-only, no self-registration) ───
// Handled BEFORE admin_header.php because that include emits HTML; a POST
// must be able to redirect (PRG) instead of rendering a second copy of the page.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_faculty') {
    if (!validateCsrfToken()) {
        flash('error', 'Invalid form submission. Please refresh and try again.');
    } else {
        $facultyEmail = trim($_POST['faculty_email'] ?? '');
        $facultyName  = trim($_POST['faculty_name'] ?? '');
        $facultyPwd   = (string)($_POST['faculty_password'] ?? '');
        $facultyActive = isset($_POST['faculty_active']) ? 1 : 0;

        $existStmt = $pdo->prepare("SELECT id, email, password_hash FROM faculty WHERE college_id = ?");
        $existStmt->execute([$collegeId]);
        $existing = $existStmt->fetch();

        if (!filter_var($facultyEmail, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Enter a valid faculty email address.');
        } elseif ($facultyPwd !== '' && strlen($facultyPwd) < 8) {
            flash('error', 'Password must be at least 8 characters.');
        } elseif (!$existing && $facultyPwd === '') {
            flash('error', 'Set a password for this faculty credential.');
        } else {
            // Keep the existing hash when the password field is left blank.
            $hash = $facultyPwd !== ''
                ? password_hash($facultyPwd, PASSWORD_BCRYPT, ['cost' => PASSWORD_BCRYPT_COST])
                : $existing['password_hash'];
            $displayName = $facultyName !== '' ? $facultyName : 'Faculty';

            try {
                if ($existing) {
                    $upd = $pdo->prepare("
                        UPDATE faculty
                        SET email = ?, name = ?, password_hash = ?, is_active = ?
                        WHERE college_id = ?
                    ");
                    $upd->execute([$facultyEmail, $displayName, $hash, $facultyActive, $collegeId]);
                } else {
                    $ins = $pdo->prepare("
                        INSERT INTO faculty (college_id, email, name, password_hash, is_active, created_by)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $ins->execute([$collegeId, $facultyEmail, $displayName, $hash, $facultyActive, $_SESSION['admin_id']]);
                }
                flash('success', 'Faculty credentials saved for ' . $displayName . '.');
            } catch (PDOException $e) {
                // 23000 = duplicate key (email already assigned to another college)
                flash('error', $e->getCode() === '23000'
                    ? 'That faculty email is already assigned to another college.'
                    : 'Could not save faculty credentials.');
            }
        }
    }
    redirect('/admin/colleges/' . $collegeId);
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

<div class="table-card" style="margin-bottom:var(--space-6);">
    <div class="table-card-header">
        <h3>Faculty Login Credentials</h3>
    </div>
    <div class="table-card-body">
        <?php
        // Read the current credential for THIS college (one set per college).
        $fcStmt = $pdo->prepare("SELECT email, name, is_active, updated_at FROM faculty WHERE college_id = ?");
        $fcStmt->execute([$collegeId]);
        $fc = $fcStmt->fetch();
        ?>
        <p class="text-muted" style="margin-top:0;">
            Faculty sign in at
            <a href="<?= BASE_URL ?>/faculty-login.php" target="_blank" rel="noopener"><?= BASE_URL ?>/faculty-login.php</a>.
            Credentials are <strong>assigned here</strong> — faculty cannot self-register.
        </p>

        <form method="POST" class="analytics-grid" style="gap:var(--space-3);align-items:end;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="assign_faculty">

            <div class="form-group" style="margin:0;">
                <label for="faculty_name">Display name</label>
                <input class="form-input" type="text" id="faculty_name" name="faculty_name"
                       value="<?= h($fc['name'] ?? '') ?>" placeholder="e.g. Prof. Rao" maxlength="255">
            </div>

            <div class="form-group" style="margin:0;">
                <label for="faculty_email">Email (login ID)</label>
                <input class="form-input" type="email" id="faculty_email" name="faculty_email"
                       value="<?= h($fc['email'] ?? '') ?>" placeholder="faculty@college.edu" required>
            </div>

            <div class="form-group" style="margin:0;">
                <label for="faculty_password">Password</label>
                <input class="form-input" type="password" id="faculty_password" name="faculty_password"
                       placeholder="<?= $fc ? 'Leave blank to keep current password' : 'At least 8 characters' ?>"
                       autocomplete="new-password" <?= $fc ? '' : 'required' ?>>
            </div>

            <div class="form-group" style="margin:0;">
                <label for="faculty_active">&nbsp;</label>
                <label style="display:flex;align-items:center;gap:6px;font-weight:500;">
                    <input type="checkbox" id="faculty_active" name="faculty_active" value="1"
                        <?= (!$fc || (int)$fc['is_active'] === 1) ? 'checked' : '' ?>>
                    Active
                </label>
            </div>

            <div class="form-group" style="margin:0;">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary"><?= $fc ? 'Update credential' : 'Create credential' ?></button>
            </div>
        </form>

        <?php if ($fc): ?>
            <div style="margin-top:var(--space-3);font-size:0.875rem;" class="text-muted">
                Current: <strong><?= h($fc['email']) ?></strong>
                &middot; <?= (int)$fc['is_active'] === 1
                    ? '<span class="badge badge-active">Active</span>'
                    : '<span class="badge badge-pending">Inactive</span>' ?>
                &middot; updated <?= h($fc['updated_at']) ?>
            </div>
        <?php else: ?>
            <div style="margin-top:var(--space-3);font-size:0.875rem;" class="text-muted">
                No faculty credential exists for this college yet.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ─── Faculty login activity — WHEN + WHERE they signed in ── -->
<?php
$logStmt = $pdo->prepare("
    SELECT l.created_at, l.email, l.ip_address, l.user_agent,
           l.latitude, l.longitude, l.accuracy_m, f.name AS faculty_name
    FROM faculty_login_log l
    LEFT JOIN faculty f ON f.id = l.faculty_id
    WHERE l.college_id = ?
    ORDER BY l.created_at DESC, l.id DESC
    LIMIT 20
");
$logStmt->execute([$collegeId]);
$loginLog = $logStmt->fetchAll();
?>
<div class="table-card" style="margin-bottom:var(--space-6);">
    <div class="table-card-header">
        <h3>Faculty Login Activity (<?= count($loginLog) ?>)</h3>
    </div>
    <div class="table-card-body">
        <p class="text-muted" style="margin-top:0;">
            Every sign-in by this college's faculty, with the time and the machine's
            coordinates when the browser shared its location.
        </p>
        <table class="data-table" id="facultyLoginLog">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Faculty</th>
                    <th>Location (lat, long)</th>
                    <th>Accuracy</th>
                    <th>IP address</th>
                    <th>Device</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($loginLog)): ?>
                    <tr><td colspan="6" style="text-align:center;padding:var(--space-6);color:var(--gray-50);">No faculty sign-ins recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($loginLog as $l): ?>
                        <tr>
                            <td class="text-sm"><?= h($l['created_at']) ?></td>
                            <td class="text-sm">
                                <strong><?= h($l['faculty_name'] !== null ? $l['faculty_name'] : '—') ?></strong><br>
                                <span class="text-muted"><?= h($l['email']) ?></span>
                            </td>
                            <td class="text-sm">
                                <?php if ($l['latitude'] !== null && $l['longitude'] !== null): ?>
                                    <?= h($l['latitude']) ?>, <?= h($l['longitude']) ?>
                                <?php else: ?>
                                    <span class="text-muted">Not shared</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-sm"><?= $l['accuracy_m'] !== null ? (int)$l['accuracy_m'] . ' m' : '—' ?></td>
                            <td class="text-sm"><?= h($l['ip_address']) ?></td>
                            <td class="text-sm"><?= h(mb_substr((string)$l['user_agent'], 0, 60)) ?><?= strlen((string)$l['user_agent']) > 60 ? '…' : '' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
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
