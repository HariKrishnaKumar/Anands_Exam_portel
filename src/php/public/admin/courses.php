<?php
$pageTitle = 'Manage Courses';
require_once __DIR__ . '/../../includes/admin_header.php';

$pdo = getDB();
$message = '';
$error = '';
$adminRole = $_SESSION['admin_role'] ?? 'admin';
$canManage = in_array($adminRole, ['super_admin', 'platform_admin'], true);

// Handle Add / Edit / Archive / Restore
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add' && !empty($_POST['name']) && !empty($_POST['college_id'])) {
            if (!$canManage) { $error = 'Permission denied.'; }
            else {
                $stmt = $pdo->prepare("INSERT INTO courses (college_id, name) VALUES (?, ?)");
                $stmt->execute([(int)$_POST['college_id'], trim($_POST['name'])]);
                $message = 'Course added successfully.';
            }
        } elseif ($action === 'edit' && !empty($_POST['id']) && !empty($_POST['name'])) {
            if (!$canManage) { $error = 'Permission denied.'; }
            else {
                $stmt = $pdo->prepare("UPDATE courses SET name = ?, college_id = ? WHERE id = ?");
                $stmt->execute([trim($_POST['name']), (int)$_POST['college_id'], (int)$_POST['id']]);
                $message = 'Course updated successfully.';
            }
        } elseif ($action === 'delete' && !empty($_POST['id'])) {
            // Archive instead of hard delete — batches and students are retained.
            if (!$canManage) { $error = 'Permission denied.'; }
            else {
                $stmt = $pdo->prepare("UPDATE courses SET status = 'archived' WHERE id = ?");
                $stmt->execute([(int)$_POST['id']]);
                $message = 'Course archived. Its batches and students are retained.';
            }
        } elseif ($action === 'restore' && !empty($_POST['id'])) {
            if (!$canManage) { $error = 'Permission denied.'; }
            else {
                $stmt = $pdo->prepare("UPDATE courses SET status = 'active' WHERE id = ?");
                $stmt->execute([(int)$_POST['id']]);
                $message = 'Course restored successfully.';
            }
        }
    } catch (Exception $e) {
        error_log('Course save failed: ' . $e->getMessage());
        $error = 'Failed to save course: ' . $e->getMessage();
    }
}

$showArchived = (($_GET['show'] ?? '') === 'archived');
$archivedCount = (int)$pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'archived'")->fetchColumn();

// Active colleges only for the Add modal dropdown (editing keeps all colleges
// so courses of archived colleges can still be managed)
$collegesActive = $pdo->query("SELECT id, name FROM colleges WHERE status = 'active' ORDER BY name")->fetchAll();
$collegesAll = $pdo->query("SELECT id, name FROM colleges ORDER BY name")->fetchAll();

$stmt = $pdo->prepare("
    SELECT c.*, cl.name AS college_name,
           (SELECT COUNT(*) FROM batches WHERE course_id = c.id) AS batch_count,
           (SELECT COUNT(*) FROM students s JOIN batches b ON b.id = s.batch_id WHERE b.course_id = c.id) AS student_count
    FROM courses c
    JOIN colleges cl ON cl.id = c.college_id
    WHERE c.status = ?
    ORDER BY cl.name, c.name
");
$stmt->execute([$showArchived ? 'archived' : 'active']);
$courses = $stmt->fetchAll();
?>

<div class="dashboard-header" style="margin-bottom:var(--space-4);">
    <div class="dashboard-header-left">
        <h1>Courses</h1>
        <p class="dashboard-subtitle">Manage all courses across institutions</p>
    </div>
    <div class="dashboard-header-right">
        <button class="btn btn-primary btn-sm" onclick="openModal('addModal')">+ Add Course</button>
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
            <span style="font-size:var(--fs-13);">Archiving a course keeps all of its batches and students intact. You can restore archived courses any time.</span>
        </div>
    <?php endif; ?>

    <div class="filter-bar">
        <?php if ($showArchived): ?>
            <a href="courses.php" class="btn btn-sm btn-ghost">Show Active</a>
        <?php elseif ($archivedCount > 0): ?>
            <a href="courses.php?show=archived" class="btn btn-sm btn-ghost">Show Archived (<?= $archivedCount ?>)</a>
        <?php endif; ?>
    </div>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Course Name</th>
                    <th>College</th>
                    <th>Batches</th>
                    <th>Students</th>
                    <th>Created</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($courses)): ?>
                    <tr><td colspan="7" class="text-center" style="padding:32px;color:var(--gray-50);">
                        <?= $showArchived ? 'No archived courses.' : 'No courses yet. <a href="colleges.php">Add a college first</a>.' ?>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($courses as $c): ?>
                    <tr>
                        <td class="text-muted"><?= $c['id'] ?></td>
                        <td>
                            <strong><?= h($c['name']) ?></strong>
                            <?php if ($c['status'] === 'archived'): ?><span class="badge badge-warning" style="margin-left:6px;">Archived</span><?php endif; ?>
                        </td>
                        <td><?= h($c['college_name']) ?></td>
                        <td><span class="badge badge-active"><?= $c['batch_count'] ?></span></td>
                        <td><span class="badge badge-active"><?= $c['student_count'] ?></span></td>
                        <td class="text-sm text-muted"><?= formatDateTime($c['created_at']) ?></td>
                        <td class="actions">
                            <button class="btn btn-sm btn-ghost" onclick="editCourse(<?= $c['id'] ?>, <?= $c['college_id'] ?>, '<?= h(addslashes($c['name'])) ?>')">Edit</button>
                            <?php if ($c['status'] === 'archived'): ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Restore this course to active status?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="restore">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-ghost">Restore</button>
                            </form>
                            <?php else: ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Archive this course? Its batches and students will be retained (not deleted).')">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
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
                <h3>Add Course</h3>
                <button type="button" class="modal-close" onclick="closeModal('addModal')">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M4.09 4.09a.5.5 0 0 1 .7 0L10 9.29l5.2-5.2a.5.5 0 0 1 .7.7L10.7 10l5.2 5.2a.5.5 0 0 1-.7.7L10 10.7l-5.2 5.2a.5.5 0 0 1-.7-.7L9.29 10 4.09 4.8a.5.5 0 0 1 0-.7z"/></svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="add_college_id">College *</label>
                    <select class="form-select" id="add_college_id" name="college_id" required>
                        <option value="">Select College</option>
                        <?php foreach ($collegesActive as $cl): ?>
                            <option value="<?= $cl['id'] ?>"><?= h($cl['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="add_name">Course Name *</label>
                    <input class="form-input" type="text" id="add_name" name="name" required placeholder="e.g. B.Tech, MCA, BCA">
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
                <h3>Edit Course</h3>
                <button type="button" class="modal-close" onclick="closeModal('editModal')">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M4.09 4.09a.5.5 0 0 1 .7 0L10 9.29l5.2-5.2a.5.5 0 0 1 .7.7L10.7 10l5.2 5.2a.5.5 0 0 1-.7.7L10 10.7l-5.2 5.2a.5.5 0 0 1-.7-.7L9.29 10 4.09 4.8a.5.5 0 0 1 0-.7z"/></svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="edit_college_id">College *</label>
                    <select class="form-select" id="edit_college_id" name="college_id" required>
                        <?php foreach ($collegesAll as $cl): ?>
                            <option value="<?= $cl['id'] ?>"><?= h($cl['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_name">Course Name *</label>
                    <input class="form-input" type="text" id="edit_name" name="name" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { var el = document.getElementById(id); if (!el) return; el.style.display = 'flex'; el.classList.add('open'); }
function closeModal(id) { var el = document.getElementById(id); if (!el) return; el.classList.remove('open'); el.style.display = 'none'; }
function editCourse(id, collegeId, name) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_college_id').value = collegeId;
    openModal('editModal');
}
document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>