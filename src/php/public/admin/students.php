<?php
$pageTitle = 'Manage Students';
require_once __DIR__ . '/../../includes/admin_header.php';

$pdo = getDB();
$message = '';

// Handle guest link generation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'generate_guest' || $action === 'generate_qr') {
        $batchId = (int)($_POST['batch_id'] ?? 0);
        $testId = !empty($_POST['test_id']) ? (int)$_POST['test_id'] : null;

        if ($batchId > 0) {
            $token = generateToken();
            $type = ($action === 'generate_qr') ? 'qr' : 'guest';
            $expires = date('Y-m-d H:i:s', strtotime('+30 days')); // Default expiry

            // If a test is selected, use its end_time — but ONLY when that
            // date is still in the future. Otherwise the link would be
            // born already expired (silent bug: stale test dates killed
            // every generated QR/guest link).
            if ($testId) {
                $tStmt = $pdo->prepare("SELECT end_time FROM tests WHERE id = ?");
                $tStmt->execute([$testId]);
                $test = $tStmt->fetch();
                if ($test && $test['end_time'] && strtotime($test['end_time']) > time()) {
                    $expires = $test['end_time'];
                }
            }

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO guest_entries (batch_id, test_id, token, type, expires_at)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$batchId, $testId, $token, $type, $expires]);
            } catch (Throwable $e) {
                $message = 'Failed to generate ' . ($type === 'qr' ? 'QR code' : 'guest link') . ': ' . $e->getMessage();
                notifyAdmin($type === 'qr' ? 'qr' : 'guest_link',
                    ($type === 'qr' ? 'QR generation failed' : 'Guest link generation failed'),
                    $e->getMessage() . ' (batch #' . $batchId . ')',
                    BASE_URL . '/admin/students.php');
                $generationFailed = true;
                goto render_page; // skip further action handling
            }

            $fullUrl = BASE_URL . '/guest.php?token=' . $token;
            $message = ($type === 'qr' ? 'QR code' : 'Guest link') . ' generated successfully!';
            $generatedUrl = $fullUrl;
            $generatedToken = $token;
        }
    } elseif ($action === 'delete' && !empty($_POST['id'])) {
        $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
        $stmt->execute([(int)$_POST['id']]);
        $message = 'Student removed.';
    } elseif ($action === 'update' && !empty($_POST['id'])) {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $gender = $_POST['gender'] ?? 'other';
        $branch = trim($_POST['branch'] ?? '');
        $rollNumber = trim($_POST['roll_number'] ?? '');
        $yearOfJoining = (int)($_POST['year_of_joining'] ?? 0);
        $batchId = (int)($_POST['batch_id'] ?? 0);
        $newPassword = (string)($_POST['password'] ?? '');

        if ($name === '' || $email === '') {
            $message = 'Name and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
        } elseif ($newPassword !== '' && strlen($newPassword) < 6) {
            $message = 'New password must be at least 6 characters.';
        } else {
            // Duplicate email check (excluding this student)
            $dup = $pdo->prepare("SELECT id FROM students WHERE email = ? AND id <> ?");
            $dup->execute([$email, $id]);
            if ($dup->fetch()) {
                $message = 'Another student already uses that email.';
            } else {
                // Validate the batch and derive its college/course names
                $bStmt = $pdo->prepare("
                    SELECT b.id, c.name AS course_name, cl.name AS college_name
                    FROM batches b
                    JOIN courses c ON c.id = b.course_id
                    JOIN colleges cl ON cl.id = c.college_id
                    WHERE b.id = ?
                ");
                $bStmt->execute([$batchId]);
                $batchRow = $bStmt->fetch();
                if (!$batchRow) {
                    $message = 'Invalid batch selection.';
                } else {
                    $setFields = "batch_id = ?, name = ?, email = ?, phone = ?, gender = ?, branch = ?, roll_number = ?, year_of_joining = ?, college_name = ?, course_name = ?";
                    $params = [$batchId, $name, $email, $phone, $gender, $branch, $rollNumber, $yearOfJoining, $batchRow['college_name'], $batchRow['course_name']];
                    if ($newPassword !== '') {
                        $setFields .= ", password_hash = ?";
                        $params[] = password_hash($newPassword, PASSWORD_BCRYPT);
                    }
                    $params[] = $id;
                    $stmt = $pdo->prepare("UPDATE students SET " . $setFields . " WHERE id = ?");
                    $stmt->execute($params);
                    $message = 'Student updated successfully.';
                }
            }
        }
    }
}

render_page:

// Filters
$filterCollege = (int)($_GET['college_id'] ?? 0);
$filterCourse = (int)($_GET['course_id'] ?? 0);
$filterBatch = (int)($_GET['batch_id'] ?? 0);
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'latest';

// Sort whitelist: latest / date modified / old
switch ($sort) {
    case 'modified': $orderBy = "COALESCE(s.updated_at, s.created_at) DESC"; break;
    case 'old':      $orderBy = "s.created_at ASC"; break;
    default:         $sort = 'latest'; $orderBy = "s.created_at DESC"; break;
}

// Build query
$sql = "
    SELECT s.*, b.name AS batch_name, b.section AS batch_section, b.course_id, c.name AS course_name, c.college_id, cl.name AS college_name
    FROM students s
    JOIN batches b ON b.id = s.batch_id
    JOIN courses c ON c.id = b.course_id
    JOIN colleges cl ON cl.id = c.college_id
";
$params = [];
$where = [];
if ($filterBatch > 0) {
    $where[] = "s.batch_id = ?"; $params[] = $filterBatch;
} elseif ($filterCourse > 0) {
    $where[] = "b.course_id = ?"; $params[] = $filterCourse;
} elseif ($filterCollege > 0) {
    $where[] = "c.college_id = ?"; $params[] = $filterCollege;
}
if ($search) {
    $where[] = "(s.name LIKE ? OR s.email LIKE ? OR s.roll_number LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if (!empty($where)) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY " . $orderBy;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// For filters
$colleges = $pdo->query("SELECT id, name FROM colleges ORDER BY name")->fetchAll();

// Guest entries for linking
$guestStmt = $pdo->query("
    SELECT g.*, b.name AS batch_name, b.section AS batch_section, t.title AS test_title
    FROM guest_entries g
    LEFT JOIN batches b ON b.id = g.batch_id
    LEFT JOIN tests t ON t.id = g.test_id
    WHERE g.status = 'pending'
    ORDER BY g.created_at DESC LIMIT 20
");
$guestEntries = $guestStmt->fetchAll();

// Tests for guest link generation
$tests = $pdo->query("SELECT id, title FROM tests ORDER BY created_at DESC LIMIT 50")->fetchAll();
?>

<div class="stats-row">
    <div class="stat-box">
        <div class="stat-num"><?= count($students) ?></div>
        <div class="stat-label">Filtered Students</div>
    </div>
    <div class="stat-box">
        <div class="stat-num"><?= count($guestEntries) ?></div>
        <div class="stat-label">Pending Guest Links</div>
    </div>
</div>

<div class="dashboard-header" style="margin-bottom:var(--space-4);">
    <div class="dashboard-header-left">
        <h1>Students</h1>
        <p class="dashboard-subtitle">Manage student accounts and guest access</p>
    </div>
    <div class="dashboard-header-right">
        <button class="btn btn-primary btn-sm" onclick="openModal('guestModal')">+ Generate Guest Link</button>
    </div>
</div>

<div class="card-flat">

    <?php if ($message): ?>
        <div class="alert alert-success" style="margin:0 var(--space-5) var(--space-4);">
            <svg viewBox="0 0 20 20" fill="currentColor" style="width:18px;height:18px;flex-shrink:0;"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
            <span>
                <?= h($message) ?>
                <?php if (isset($generatedUrl)): ?>
                    <div style="margin-top:var(--space-3);padding:var(--space-4);background:var(--accent-light);border-radius:var(--radius-lg);word-break:break-all;">
                        <div style="display:flex;gap:var(--space-4);align-items:start;flex-wrap:wrap;">
                            <div style="flex:1;min-width:200px;">
                                <div style="font-size:var(--fs-12);font-weight:600;color:var(--gray-60);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:var(--space-2);">Guest Link</div>
                                <a href="<?= h($generatedUrl) ?>" target="_blank" style="font-size:var(--fs-13);"><?= h($generatedUrl) ?></a>
                                <div style="margin-top:var(--space-2);font-size:var(--fs-12);color:var(--gray-50);">
                                    Token: <code style="font-size:var(--fs-11);"><?= h($generatedToken) ?></code>
                                </div>
                                <button class="btn btn-sm btn-secondary mt-2" onclick="copyToClipboard('<?= h($generatedUrl) ?>')">
                                    <svg viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;"><path d="M6 4V3a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-1v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1zm0 1H5a1 1 0 0 0-1 1v9a1 1 0 0 0 1 1h7a1 1 0 0 0 1-1v-1H8a2 2 0 0 1-2-2V5zm9-1H8a1 1 0 0 0-1 1v9a1 1 0 0 0 1 1h7a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1z"/></svg>
                                    Copy Link
                                </button>
                            </div>
                            <?php if (($_POST['action'] ?? '') === 'generate_qr'): ?>
                            <div style="text-align:center;padding:var(--space-3);background:var(--white);border-radius:var(--radius-lg);border:1px solid var(--gray-15);">
                                <div style="font-size:var(--fs-12);font-weight:600;color:var(--gray-60);margin-bottom:var(--space-2);">QR Code</div>
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=<?= urlencode($generatedUrl) ?>"
                                     alt="QR Code" style="border-radius:var(--radius-md);display:block;" width="140" height="140">
                                <a href="<?= h($generatedUrl) ?>" target="_blank" style="font-size:var(--fs-12);display:inline-block;margin-top:var(--space-2);">Open Link <?= icon('external-link', 12) ?></a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </span>
        </div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" class="form-inline">
            <select class="form-select" name="college_id" onchange="this.form.submit()">
                <option value="">All Colleges</option>
                <?php foreach ($colleges as $cl): ?>
                    <option value="<?= $cl['id'] ?>" <?= $filterCollege === $cl['id'] ? 'selected' : '' ?>><?= h($cl['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <?php if ($filterCollege > 0): ?>
            <select class="form-select" name="course_id" onchange="this.form.submit()">
                <option value="">All Courses</option>
                <?php
                $cStmt = $pdo->prepare("SELECT id, name FROM courses WHERE college_id = ? ORDER BY name");
                $cStmt->execute([$filterCollege]);
                foreach ($cStmt->fetchAll() as $co):
                ?>
                    <option value="<?= $co['id'] ?>" <?= $filterCourse === $co['id'] ? 'selected' : '' ?>><?= h($co['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <?php if ($filterCourse > 0): ?>
            <select class="form-select" name="batch_id" onchange="this.form.submit()">
                <option value="">All Batches</option>
                <?php
                $bStmt = $pdo->prepare("SELECT id, name, section FROM batches WHERE course_id = ? ORDER BY name, section");
                $bStmt->execute([$filterCourse]);
                foreach ($bStmt->fetchAll() as $ba):
                ?>
                    <option value="<?= $ba['id'] ?>" <?= $filterBatch === $ba['id'] ? 'selected' : '' ?>><?= h($ba['name']) ?><?= $ba['section'] ? ' — ' . h($ba['section']) : '' ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <input class="form-input" type="text" name="search" placeholder="Search name, email, roll..." value="<?= h($search) ?>" style="max-width:200px;">
            <label class="form-label" style="margin:0;">Sort:</label>
            <select class="form-select" name="sort" onchange="this.form.submit()" style="width:auto;min-width:150px;">
                <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>Latest</option>
                <option value="modified" <?= $sort === 'modified' ? 'selected' : '' ?>>Date Modified</option>
                <option value="old" <?= $sort === 'old' ? 'selected' : '' ?>>Old</option>
            </select>
            <button class="btn btn-sm btn-secondary" type="submit">Search</button>
            <?php if ($filterCollege || $filterCourse || $filterBatch || $search || $sort !== 'latest'): ?>
                <a href="students.php" class="btn btn-sm btn-ghost">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email / Phone</th>
                    <th>College</th>
                    <th>Course / Batch</th>
                    <th>Roll</th>
                    <th>Year</th>
                    <th>Joined</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr><td colspan="8" class="text-center" style="padding:32px;color:var(--gray-50);">No students found.</td></tr>
                <?php else: ?>
                    <?php foreach ($students as $s): ?>
                    <tr>
                        <td><strong><?= h($s['name']) ?></strong></td>
                        <td>
                            <div><?= h($s['email']) ?></div>
                            <div class="text-sm text-muted"><?= h($s['phone']) ?></div>
                        </td>
                        <td class="text-sm"><?= h($s['college_name']) ?></td>
                        <td>
                            <div><?= h($s['course_name']) ?></div>
                            <div class="text-sm text-muted"><?= h($s['batch_name']) ?><?= $s['batch_section'] ? ' — ' . h($s['batch_section']) : '' ?></div>
                        </td>
                        <td class="text-sm"><?= h($s['roll_number']) ?></td>
                        <td class="text-sm"><?= h($s['year_of_joining']) ?></td>
                        <td class="text-sm text-muted"><?= timeAgo($s['created_at']) ?></td>
                        <td class="actions" style="white-space:nowrap;">
                            <button class="btn btn-sm btn-ghost" onclick="editStudent(<?= $s['id'] ?>,
                                '<?= h(addslashes($s['name'])) ?>',
                                '<?= h(addslashes($s['email'])) ?>',
                                '<?= h(addslashes($s['phone'])) ?>',
                                '<?= h(addslashes($s['gender'])) ?>',
                                '<?= h(addslashes($s['branch'])) ?>',
                                '<?= h(addslashes($s['roll_number'])) ?>',
                                <?= (int)$s['year_of_joining'] ?>,
                                <?= (int)$s['college_id'] ?>,
                                <?= (int)$s['course_id'] ?>,
                                <?= (int)$s['batch_id'] ?>)"><?= icon('edit', 14) ?> Edit</button>
                            <a href="reports.php?student_id=<?= $s['id'] ?>" class="btn btn-sm btn-ghost"><?= icon('chart', 14) ?> Reports</a>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Remove this student? Their submissions will be preserved.')">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Guest Link Generation Modal -->
<div class="modal-overlay" id="guestModal" style="display:none;">
    <div class="modal">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" id="guestAction" value="generate_guest">
            <div class="modal-header">
                <h3 id="guestModalTitle">Generate Guest Link</h3>
                <button type="button" class="modal-close" onclick="closeModal('guestModal')">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M4.09 4.09a.5.5 0 0 1 .7 0L10 9.29l5.2-5.2a.5.5 0 0 1 .7.7L10.7 10l5.2 5.2a.5.5 0 0 1-.7.7L10 10.7l-5.2 5.2a.5.5 0 0 1-.7-.7L9.29 10 4.09 4.8a.5.5 0 0 1 0-.7z"/></svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Type</label>
                    <div class="flex gap-4" style="margin-top:4px;">
                        <label class="flex-center" style="cursor:pointer;gap:6px;">
                            <input type="radio" name="link_type" value="guest" checked onchange="setGuestType('guest')"> Guest Link
                        </label>
                        <label class="flex-center" style="cursor:pointer;gap:6px;">
                            <input type="radio" name="link_type" value="qr" onchange="setGuestType('qr')"> QR Code
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="guest_college">College</label>
                    <select class="form-select" id="guest_college" onchange="loadGuestCourses()">
                        <option value="">Select College</option>
                        <?php foreach ($colleges as $cl): ?>
                            <option value="<?= $cl['id'] ?>"><?= h($cl['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="guest_course">Course</label>
                    <select class="form-select" id="guest_course" onchange="loadGuestBatches()" disabled>
                        <option value="">Select College first</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="guest_batch_id">Batch *</label>
                    <select class="form-select" id="guest_batch_id" name="batch_id" required disabled>
                        <option value="">Select Course first</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="guest_test_id">Link to Test (optional)</label>
                    <select class="form-select" id="guest_test_id" name="test_id">
                        <option value="">No specific test</option>
                        <?php foreach ($tests as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= h($t['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint">If selected, the link expires when the test ends.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('guestModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Generate</button>
            </div>
        </form>
    </div>
</div>

<script>
// API URL — works on both dev server and XAMPP
const API_URL = <?= json_encode(php_sapi_name() === 'cli-server' ? '/api' : '/test-platform/src/php/api') ?>;

function openModal(id) { var el = document.getElementById(id); if (!el) return; el.style.display = 'flex'; el.classList.add('open'); }
function closeModal(id) { var el = document.getElementById(id); if (!el) return; el.classList.remove('open'); el.style.display = 'none'; }

function setGuestType(type) {
    document.getElementById('guestAction').value = type === 'qr' ? 'generate_qr' : 'generate_guest';
    document.getElementById('guestModalTitle').textContent = type === 'qr' ? 'Generate QR Code' : 'Generate Guest Link';
}

function loadGuestCourses() {
    const collegeId = document.getElementById('guest_college').value;
    const select = document.getElementById('guest_course');
    select.innerHTML = '<option value="">Loading...</option>'; select.disabled = true;
    document.getElementById('guest_batch_id').innerHTML = '<option value="">Select Course first</option>'; document.getElementById('guest_batch_id').disabled = true;
    if (!collegeId) { select.innerHTML = '<option value="">Select College</option>'; return; }
    fetch(API_URL + '/get_courses.php?college_id=' + collegeId + '&active=1')
        .then(r => r.json()).then(data => {
            select.innerHTML = '<option value="">Select Course</option>';
            data.forEach(c => { select.innerHTML += '<option value="' + c.id + '">' + c.name + '</option>'; });
            select.disabled = false;
        });
}

function loadGuestBatches() {
    const courseId = document.getElementById('guest_course').value;
    const select = document.getElementById('guest_batch_id');
    select.innerHTML = '<option value="">Loading...</option>'; select.disabled = true;
    if (!courseId) { select.innerHTML = '<option value="">Select Course first</option>'; return; }
    fetch(API_URL + '/get_batches.php?course_id=' + courseId + '&active=1')
        .then(r => r.json()).then(data => {
            select.innerHTML = '<option value="">Select Batch</option>';
            data.forEach(b => { select.innerHTML += '<option value="' + b.id + '">' + b.name + '</option>'; });
            select.disabled = false;
        });
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('Copied to clipboard!');
    });
}

document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});
</script>

<!-- Edit Student Modal -->
<div class="modal-overlay" id="editStudentModal" style="display:none;">
    <div class="modal">
        <form method="POST" id="editStudentForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header">
                <h3>Edit Student</h3>
                <button type="button" class="modal-close" onclick="closeModal('editStudentModal')">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M4.09 4.09a.5.5 0 0 1 .7 0L10 9.29l5.2-5.2a.5.5 0 0 1 .7.7L10.7 10l5.2 5.2a.5.5 0 0 1-.7.7L10 10.7l-5.2 5.2a.5.5 0 0 1-.7-.7L9.29 10 4.09 4.8a.5.5 0 0 1 0-.7z"/></svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_college">College *</label>
                        <select class="form-select" id="edit_college" onchange="loadEditCourses()" required>
                            <option value="">Select College</option>
                            <?php foreach ($colleges as $cl): ?>
                                <option value="<?= $cl['id'] ?>"><?= h($cl['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_course">Course *</label>
                        <select class="form-select" id="edit_course" onchange="loadEditBatches()" required disabled>
                            <option value="">Select College first</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_batch">Batch *</label>
                        <select class="form-select" id="edit_batch" name="batch_id" required disabled>
                            <option value="">Select Course first</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_year">Year of Joining</label>
                        <select class="form-select" id="edit_year" name="year_of_joining">
                            <?php for ($y = date('Y'); $y >= 2018; $y--): ?>
                                <option value="<?= $y ?>"><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_name">Full Name *</label>
                        <input class="form-input" type="text" id="edit_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_gender">Gender</label>
                        <select class="form-select" id="edit_gender" name="gender">
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_email">Email *</label>
                        <input class="form-input" type="email" id="edit_email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_phone">Phone</label>
                        <input class="form-input" type="tel" id="edit_phone" name="phone">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_branch">Branch</label>
                        <input class="form-input" type="text" id="edit_branch" name="branch">
                    </div>
                    <div class="form-group">
                        <label for="edit_roll">Roll Number</label>
                        <input class="form-input" type="text" id="edit_roll" name="roll_number">
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_password">Reset Password <span class="form-hint">(leave blank to keep current password)</span></label>
                    <input class="form-input" type="password" id="edit_password" name="password" placeholder="New password (min 6 characters)" autocomplete="new-password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editStudentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
// ─── Complete Edit Student ──────────────────────────────────
function editStudent(id, name, email, phone, gender, branch, roll, year, collegeId, courseId, batchId) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_phone').value = phone || '';
    document.getElementById('edit_gender').value = ['male','female','other'].includes(gender) ? gender : 'other';
    document.getElementById('edit_branch').value = branch || '';
    document.getElementById('edit_roll').value = roll || '';
    document.getElementById('edit_password').value = '';
    var yearSel = document.getElementById('edit_year');
    yearSel.value = String(year);
    if (!yearSel.value) yearSel.options[0].selected = true;

    // College / course / batch cascade with pre-selection
    document.getElementById('edit_college').value = String(collegeId || '');
    loadEditCourses(true, courseId, batchId);
    openModal('editStudentModal');
}

function loadEditCourses(preselect, courseId, batchId) {
    var collegeId = document.getElementById('edit_college').value;
    var select = document.getElementById('edit_course');
    var batch = document.getElementById('edit_batch');
    select.innerHTML = '<option value="">Loading...</option>';
    select.disabled = true;
    batch.innerHTML = '<option value="">Select Course first</option>';
    batch.disabled = true;
    if (!collegeId) { select.innerHTML = '<option value="">Select College first</option>'; return; }
    fetch(API_URL + '/get_courses.php?college_id=' + collegeId)
        .then(r => r.json()).then(data => {
            select.innerHTML = '<option value="">Select Course</option>';
            data.forEach(c => {
                var sel = preselect && c.id == courseId ? ' selected' : '';
                select.innerHTML += '<option value="' + c.id + '"' + sel + '>' + c.name + '</option>';
            });
            select.disabled = false;
            if (preselect && courseId) loadEditBatches(true, batchId);
        })
        .catch(() => { select.innerHTML = '<option value="">Error loading courses</option>'; });
}

function loadEditBatches(preselect, batchId) {
    var courseId = document.getElementById('edit_course').value;
    var select = document.getElementById('edit_batch');
    select.innerHTML = '<option value="">Loading...</option>';
    select.disabled = true;
    if (!courseId) { select.innerHTML = '<option value="">Select Course first</option>'; return; }
    fetch(API_URL + '/get_batches.php?course_id=' + courseId)
        .then(r => r.json()).then(data => {
            select.innerHTML = '<option value="">Select Batch</option>';
            data.forEach(b => {
                var sel = preselect && b.id == batchId ? ' selected' : '';
                select.innerHTML += '<option value="' + b.id + '"' + sel + '>' + b.name + '</option>';
            });
            select.disabled = false;
        })
        .catch(() => { select.innerHTML = '<option value="">Error loading batches</option>'; });
}
</script>

<?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>
