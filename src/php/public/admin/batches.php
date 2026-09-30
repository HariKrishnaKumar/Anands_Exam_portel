<?php
$pageTitle = 'Manage Batches';
require_once __DIR__ . '/../../includes/admin_header.php';
require_once __DIR__ . '/../../includes/promote_helper.php';

$pdo = getDB();
$message = '';
$error = '';
$adminRole = $_SESSION['admin_role'] ?? 'admin';
$canManage = in_array($adminRole, ['super_admin', 'platform_admin'], true);

// Set after a successful promotion redirect (the API is JSON-only, so the
// result is handed back through the query string as a flag, never as data).
if (isset($_GET['promoted'])) {
    $message = 'Batch promoted to the next semester. All of its students moved up with it.';
}

// Read an optional semester (1..12), blank = NULL. Mirrors admin/students.php.
function batchSemesterInput(): ?int
{
    $raw = trim((string)($_POST['semester'] ?? ''));
    if ($raw === '') {
        return null;
    }
    if (!is_numeric($raw)) {
        throw new InvalidArgumentException('Semester must be a number.');
    }
    $val = (int)$raw;
    if ($val < 1 || $val > 12) {
        throw new InvalidArgumentException('Semester must be between 1 and 12.');
    }
    return $val;
}

// Handle Add / Edit / Archive / Restore
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add' && !empty($_POST['name']) && !empty($_POST['course_id'])) {
            if (!$canManage) { $error = 'Permission denied.'; }
            else {
                $section = trim($_POST['section'] ?? '') ?: null;
                $semester = batchSemesterInput();
                $stmt = $pdo->prepare("INSERT INTO batches (course_id, name, section, semester_order) VALUES (?, ?, ?, ?)");
                $stmt->execute([(int)$_POST['course_id'], trim($_POST['name']), $section, $semester]);
                $message = 'Batch added successfully.';
            }
        } elseif ($action === 'edit' && !empty($_POST['id']) && !empty($_POST['name'])) {
            if (!$canManage) { $error = 'Permission denied.'; }
            else {
                $section = trim($_POST['section'] ?? '') ?: null;
                $semester = batchSemesterInput();
                $stmt = $pdo->prepare("UPDATE batches SET name = ?, course_id = ?, section = ?, semester_order = ? WHERE id = ?");
                $stmt->execute([trim($_POST['name']), (int)$_POST['course_id'], $section, $semester, (int)$_POST['id']]);
                $message = 'Batch updated successfully.';
            }
        } elseif ($action === 'delete' && !empty($_POST['id'])) {
            // Archive instead of hard delete — students are retained.
            if (!$canManage) { $error = 'Permission denied.'; }
            else {
                $stmt = $pdo->prepare("UPDATE batches SET status = 'archived' WHERE id = ?");
                $stmt->execute([(int)$_POST['id']]);
                $message = 'Batch archived. Its students are retained.';
            }
        } elseif ($action === 'restore' && !empty($_POST['id'])) {
            if (!$canManage) { $error = 'Permission denied.'; }
            else {
                $stmt = $pdo->prepare("UPDATE batches SET status = 'active' WHERE id = ?");
                $stmt->execute([(int)$_POST['id']]);
                $message = 'Batch restored successfully.';
            }
        }
    } catch (Exception $e) {
        error_log('Batch save failed: ' . $e->getMessage());
        $error = 'Failed to save batch: ' . $e->getMessage();
    }
}

$showArchived = (($_GET['show'] ?? '') === 'archived');
$archivedCount = (int)$pdo->query("SELECT COUNT(*) FROM batches WHERE status = 'archived'")->fetchColumn();

// Active colleges for the Add modal + filter; all colleges for the Edit modal
// (so batches of archived colleges can still be managed)
$collegesActive = $pdo->query("SELECT id, name FROM colleges WHERE status = 'active' ORDER BY name")->fetchAll();
$collegesAll = $pdo->query("SELECT id, name FROM colleges ORDER BY name")->fetchAll();

// Get selected filter
$filterCollege = (int)($_GET['college_id'] ?? 0);
$filterCourse = (int)($_GET['course_id'] ?? 0);

// Build query
$sql = "
    SELECT b.*, c.name AS course_name, cl.name AS college_name,
           (SELECT COUNT(*) FROM students WHERE batch_id = b.id) AS student_count
    FROM batches b
    JOIN courses c ON c.id = b.course_id
    JOIN colleges cl ON cl.id = c.college_id
";
$params = [];
$where = ["b.status = ?"];
$params[] = $showArchived ? 'archived' : 'active';
if ($filterCourse > 0) {
    $where[] = "b.course_id = ?";
    $params[] = $filterCourse;
} elseif ($filterCollege > 0) {
    $where[] = "c.college_id = ?";
    $params[] = $filterCollege;
}
if (!empty($where)) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY cl.name, c.name, b.name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$batches = $stmt->fetchAll();
?>

<div class="dashboard-header" style="margin-bottom:var(--space-4);">
    <div class="dashboard-header-left">
        <h1>Batches</h1>
        <p class="dashboard-subtitle">Manage student groups by course</p>
    </div>
    <div class="dashboard-header-right">
        <button class="btn btn-primary btn-sm" onclick="openModal('addModal')">+ Add Batch</button>
    </div>
</div>

<div class="card-flat">

    <?php if ($error): ?>
        <div class="alert alert-error" style="margin:0 var(--space-5) var(--space-4);">
            <svg viewBox="0 0 20 20" fill="currentColor" style="width:18px;height:18px;flex-shrink:0;"><path d="M10 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16zm0 1a7 7 0 1 0 0 14 7 7 0 0 0 0-14zm0 9.5a.75.75 0 1 1 0 1.5.75.75 0 0 1 0-1.5zM10 6a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-1 0v-4A.5.5 0 0 1 10 6z"/></svg>
            <span><?= h($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="alert alert-success" style="margin:0 var(--space-5) var(--space-4);">
            <svg viewBox="0 0 20 20" fill="currentColor" style="width:18px;height:18px;flex-shrink:0;"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
            <span><?= h($message) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($showArchived): ?>
        <div class="alert" style="margin:0 var(--space-5) var(--space-4);display:flex;gap:var(--space-2);align-items:center;">
            <svg viewBox="0 0 20 20" fill="currentColor" style="width:18px;height:18px;flex-shrink:0;"><path d="M3 4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V4zm1.5 4.5h11l-.7 8.1a1.5 1.5 0 0 1-1.5 1.4H6.7a1.5 1.5 0 0 1-1.5-1.4l-.7-8.1z"/></svg>
            <span style="font-size:var(--fs-13);">Archiving a batch keeps all of its students intact. You can restore archived batches any time.</span>
        </div>
    <?php endif; ?>

    <!-- Filter -->
    <div class="filter-bar">
        <form method="GET" class="form-inline">
            <select class="form-select" name="college_id" onchange="this.form.submit()">
                <option value="">All Colleges</option>
                <?php foreach ($collegesActive as $cl): ?>
                    <option value="<?= $cl['id'] ?>" <?= $filterCollege === $cl['id'] ? 'selected' : '' ?>><?= h($cl['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($filterCollege > 0): ?>
                <select class="form-select" name="course_id" onchange="this.form.submit()">
                    <option value="">All Courses</option>
                    <?php
                    $cStmt = $pdo->prepare("SELECT id, name FROM courses WHERE college_id = ? AND status = 'active' ORDER BY name");
                    $cStmt->execute([$filterCollege]);
                    foreach ($cStmt->fetchAll() as $co):
                    ?>
                        <option value="<?= $co['id'] ?>" <?= $filterCourse === $co['id'] ? 'selected' : '' ?>><?= h($co['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <?php if ($filterCollege > 0 || $filterCourse > 0): ?>
                <a href="batches.php" class="btn btn-sm btn-secondary">Clear</a>
            <?php endif; ?>
            <?php if ($showArchived): ?>
                <a href="batches.php" class="btn btn-sm btn-ghost">Show Active</a>
            <?php elseif ($archivedCount > 0): ?>
                <a href="batches.php?show=archived" class="btn btn-sm btn-ghost">Show Archived (<?= $archivedCount ?>)</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Batch Name</th>
                    <th>Semester</th>
                    <th>Section</th>
                    <th>Course</th>
                    <th>College</th>
                    <th>Students</th>
                    <th>Created</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($batches)): ?>
                    <tr><td colspan="9" class="text-center" style="padding:32px;color:var(--gray-50);">
                        <?= $showArchived ? 'No archived batches.' : 'No batches found.' ?>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($batches as $b): ?>
                    <?php
                        // Promotion eligibility is decided by the same helper the API
                        // uses, so the button can never appear for a batch the API
                        // would then reject. No semester / at ceiling / duplicate
                        // conflict / archived / not a manager => no button.
                        $promo = null;
                        if ($canManage && $b['status'] === 'active') {
                            $p = getPromotionPreview($pdo, (int)$b['id']);
                            if ($p && $p['current_semester'] !== null && $p['can_promote'] && !$p['duplicate_conflict']) {
                                $promo = $p;
                            }
                        }
                    ?>
                    <tr>
                        <td class="text-muted"><?= $b['id'] ?></td>
                        <td>
                            <strong><?= h($b['name']) ?></strong>
                            <?php if ($b['status'] === 'archived'): ?><span class="badge badge-warning" style="margin-left:6px;">Archived</span><?php endif; ?>
                        </td>
                        <td class="text-sm"><?= $b['semester_order'] !== null ? 'Sem ' . (int)$b['semester_order'] : '<span class="text-muted">—</span>' ?></td>
                        <td class="text-sm"><?= $b['section'] ? '<span class="badge badge-active">' . h($b['section']) . '</span>' : '<span class="text-muted">—</span>' ?></td>
                        <td><?= h($b['course_name']) ?></td>
                        <td class="text-sm text-muted"><?= h($b['college_name']) ?></td>
                        <td><span class="badge badge-active"><?= $b['student_count'] ?></span></td>
                        <td class="text-sm text-muted"><?= formatDateTime($b['created_at']) ?></td>
                        <td class="actions">
                            <button class="btn btn-sm btn-ghost"
                                onclick="editBatch(<?= $b['id'] ?>, <?= $b['course_id'] ?>, '<?= h(addslashes($b['name'])) ?>', '<?= h(addslashes($b['section'] ?? '')) ?>', <?= $b['semester_order'] !== null ? (int)$b['semester_order'] : 'null' ?>)">Edit</button>
                            <?php if ($promo): ?>
                            <button type="button" class="btn btn-sm btn-success"
                                onclick="openPromote(<?= (int)$b['id'] ?>)">Promote</button>
                            <?php endif; ?>
                            <?php if ($b['status'] === 'archived'): ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Restore this batch to active status?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="restore">
                                <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-ghost">Restore</button>
                            </form>
                            <?php else: ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Archive this batch? Its students will be retained (not deleted).')">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Archive</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal" style="display:none;">
    <div class="modal">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add">
            <div class="modal-header">
                <h3>Add Batch</h3>
                <button type="button" class="modal-close" onclick="closeModal('addModal')">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M4.09 4.09a.5.5 0 0 1 .7 0L10 9.29l5.2-5.2a.5.5 0 0 1 .7.7L10.7 10l5.2 5.2a.5.5 0 0 1-.7.7L10 10.7l-5.2 5.2a.5.5 0 0 1-.7-.7L9.29 10 4.09 4.8a.5.5 0 0 1 0-.7z"/></svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>College *</label>
                    <select class="form-select" id="add_college" onchange="loadAddCourses()" required>
                        <option value="">Select College</option>
                        <?php foreach ($collegesActive as $cl): ?>
                            <option value="<?= $cl['id'] ?>"><?= h($cl['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="add_course_id">Course *</label>
                    <select class="form-select" id="add_course_id" name="course_id" required disabled>
                        <option value="">Select College first</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="add_name">Batch Name *</label>
                    <input class="form-input" type="text" id="add_name" name="name" required placeholder="e.g. 2024 Batch">
                </div>
                <div class="form-group">
                    <label for="add_section">Section / Division</label>
                    <input class="form-input" type="text" id="add_section" name="section" placeholder="e.g. A, B, Morning, Evening" maxlength="10">
                    <div class="form-hint">Optional. Leave blank if the batch has no sections.</div>
                </div>
                <div class="form-group">
                    <label for="add_semester">Semester</label>
                    <select class="form-select" id="add_semester" name="semester">
                        <option value="">Not set</option>
                        <?php for ($sm = 1; $sm <= 12; $sm++): ?>
                            <option value="<?= $sm ?>">Semester <?= $sm ?></option>
                        <?php endfor; ?>
                    </select>
                    <div class="form-hint">Optional. A batch needs a semester before its students can be promoted.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal" style="display:none;">
    <div class="modal">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header">
                <h3>Edit Batch</h3>
                <button type="button" class="modal-close" onclick="closeModal('editModal')">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M4.09 4.09a.5.5 0 0 1 .7 0L10 9.29l5.2-5.2a.5.5 0 0 1 .7.7L10.7 10l5.2 5.2a.5.5 0 0 1-.7.7L10 10.7l-5.2 5.2a.5.5 0 0 1-.7-.7L9.29 10 4.09 4.8a.5.5 0 0 1 0-.7z"/></svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>College *</label>
                    <select class="form-select" id="edit_college" onchange="loadEditCourses()" required>
                        <?php foreach ($collegesAll as $cl): ?>
                            <option value="<?= $cl['id'] ?>"><?= h($cl['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_course_id">Course *</label>
                    <select class="form-select" id="edit_course_id" name="course_id" required>
                        <option value="">Select</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_name">Batch Name *</label>
                    <input class="form-input" type="text" id="edit_name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="edit_section">Section / Division</label>
                    <input class="form-input" type="text" id="edit_section" name="section" placeholder="e.g. A, B, Morning, Evening" maxlength="10">
                    <div class="form-hint">Optional. Leave blank if the batch has no sections.</div>
                </div>
                <div class="form-group">
                    <label for="edit_semester">Semester</label>
                    <select class="form-select" id="edit_semester" name="semester">
                        <option value="">Not set</option>
                        <?php for ($sm = 1; $sm <= 12; $sm++): ?>
                            <option value="<?= $sm ?>">Semester <?= $sm ?></option>
                        <?php endfor; ?>
                    </select>
                    <div class="form-hint">Optional. A batch needs a semester before its students can be promoted.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>

<!-- Promote Modal -->
<div class="modal-overlay" id="promoteModal" style="display:none;">
    <div class="modal">
        <div class="modal-header">
            <h3>Promote Batch</h3>
            <button type="button" class="modal-close" onclick="closeModal('promoteModal')">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M4.09 4.09a.5.5 0 0 1 .7 0L10 9.29l5.2-5.2a.5.5 0 0 1 .7.7L10.7 10l5.2 5.2a.5.5 0 0 1-.7.7L10 10.7l-5.2 5.2a.5.5 0 0 1-.7-.7L9.29 10 4.09 4.8a.5.5 0 0 1 0-.7z"/></svg>
            </button>
        </div>
        <div class="modal-body" id="promoteModalBody">
            <p>Loading promotion details…</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('promoteModal')">Cancel</button>
            <button type="button" class="btn btn-success" id="promoteConfirmBtn" disabled>Promote</button>
        </div>
    </div>
</div>

<script>
function openModal(id) { var el = document.getElementById(id); if (!el) return; el.style.display = 'flex'; el.classList.add('open'); }
function closeModal(id) { var el = document.getElementById(id); if (!el) return; el.classList.remove('open'); el.style.display = 'none'; }

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

// ─── Promotion ────────────────────────────────────────────
// The batch is advanced in place: semester_order +1, every student +1,
// the batch keeps its name and no second batch row is ever created.
var promoteBatchId = null;
var PROMOTE_API = '/test-platform/src/php/api/promote_students.php';

function openPromote(batchId) {
    promoteBatchId = batchId;
    var body = document.getElementById('promoteModalBody');
    var confirmBtn = document.getElementById('promoteConfirmBtn');
    body.innerHTML = '<p>Loading promotion details…</p>';
    confirmBtn.disabled = true;
    openModal('promoteModal');
    loadPromotePreview(batchId);
}

function promotePost(payload) {
    return fetch(PROMOTE_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    }).then(function (r) {
        return r.json().then(function (j) { return { ok: r.ok, data: j }; });
    });
}

function loadPromotePreview(batchId) {
    var body = document.getElementById('promoteModalBody');
    var confirmBtn = document.getElementById('promoteConfirmBtn');

    promotePost({ batch_id: batchId, preview: true })
        .then(function (res) {
            if (!res.ok) {
                body.innerHTML = '<div class="alert alert-error"><span>' +
                    escapeHtml(res.data.message || 'Unable to load promotion details.') + '</span></div>';
                confirmBtn.disabled = false;
                return;
            }
            var d = res.data;
            body.innerHTML =
                '<p style="margin-bottom:var(--space-3);font-size:var(--fs-16);">' +
                    '<strong>Semester: ' + d.current_semester + ' → ' + d.next_semester + '</strong>' +
                '</p>' +
                '<ul style="margin:0 0 var(--space-3) var(--space-5);font-size:var(--fs-13);line-height:1.9;color:var(--gray-70);">' +
                    '<li><strong>Will become:</strong> Semester ' + d.next_semester + ' <span class="text-muted">— name stays "' + escapeHtml(d.from) + '"</span></li>' +
                    '<li><strong>Course:</strong> ' + escapeHtml(d.course_name) + ' — ' + d.duration_years + ' year(s), ' + d.max_semesters + ' semesters max</li>' +
                    '<li><strong>Students in this batch:</strong> ' + d.student_count + '</li>' +
                '</ul>' +
                '<p style="font-size:var(--fs-13);color:var(--gray-60);">' +
                    'No new batch is created — this is an in-place promotion, so every student in the batch moves up one semester.' +
                '</p>';
            confirmBtn.disabled = false;
        })
        .catch(function () {
            body.innerHTML = '<div class="alert alert-error"><span>Unable to load promotion details.</span></div>';
            confirmBtn.disabled = false;
        });
}

document.getElementById('promoteConfirmBtn').addEventListener('click', function () {
    var body = document.getElementById('promoteModalBody');
    var confirmBtn = this;
    confirmBtn.disabled = true;

    promotePost({ batch_id: promoteBatchId })
        .then(function (res) {
            if (!res.ok) {
                body.innerHTML = '<div class="alert alert-error"><span>' +
                    escapeHtml(res.data.message || 'Promotion failed. Please try again.') + '</span></div>';
                confirmBtn.disabled = false;
                return;
            }
            window.location.href = 'batches.php?promoted=1';
        })
        .catch(function () {
            body.innerHTML = '<div class="alert alert-error"><span>Promotion failed. Please try again.</span></div>';
            confirmBtn.disabled = false;
        });
});

// Escape closes whichever modal is open.
document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var open = document.querySelector('.modal-overlay.open');
    if (open) closeModal(open.id);
});

function loadAddCourses() {
    const collegeId = document.getElementById('add_college').value;
    const select = document.getElementById('add_course_id');
    select.innerHTML = '<option value="">Loading...</option>';
    select.disabled = true;
    if (!collegeId) { select.innerHTML = '<option value="">Select College first</option>'; return; }
    fetch('/test-platform/src/php/api/get_courses.php?college_id=' + collegeId + '&active=1')
        .then(r => r.json())
        .then(data => {
            select.innerHTML = '<option value="">Select Course</option>';
            data.forEach(c => { select.innerHTML += '<option value="' + c.id + '">' + c.name + '</option>'; });
            select.disabled = false;
        })
        .catch(() => { select.innerHTML = '<option value="">Error</option>'; });
}

function loadEditCourses() {
    const collegeId = document.getElementById('edit_college').value;
    const select = document.getElementById('edit_course_id');
    select.innerHTML = '<option value="">Loading...</option>';
    select.disabled = true;
    if (!collegeId) { select.innerHTML = '<option value="">Select College first</option>'; return; }
    fetch('/test-platform/src/php/api/get_courses.php?college_id=' + collegeId)
        .then(r => r.json())
        .then(data => {
            select.innerHTML = '<option value="">Select Course</option>';
            data.forEach(c => { select.innerHTML += '<option value="' + c.id + '">' + c.name + '</option>'; });
            select.disabled = false;
        })
        .catch(() => { select.innerHTML = '<option value="">Error</option>'; });
}

function editBatch(id, courseId, name, section, semester) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_section').value = section || '';
    document.getElementById('edit_semester').value =
        (semester === null || semester === undefined) ? '' : String(semester);
    // Load the college and course for this batch
    fetch('/test-platform/src/php/api/get_course_college.php?course_id=' + courseId)
        .then(r => r.json())
        .then(data => {
            document.getElementById('edit_college').value = data.college_id;
            loadEditCourses();
            setTimeout(() => { document.getElementById('edit_course_id').value = courseId; }, 300);
        });
    openModal('editModal');
}

document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>