<?php
$pageTitle = 'Question Library';
require_once __DIR__ . '/../../includes/admin_header.php';

$pdo = getDB();

// ─── Fetch all questions across all tests ───────────────────
$search = trim($_GET['q'] ?? '');
$typeFilter = $_GET['type'] ?? '';

$sql = "
    SELECT q.*, t.title AS test_title, b.name AS batch_name,
           c.name AS course_name, cl.name AS college_name
    FROM questions q
    JOIN tests t ON t.id = q.test_id
    JOIN batches b ON b.id = t.batch_id
    JOIN courses c ON c.id = b.course_id
    JOIN colleges cl ON cl.id = c.college_id
";
$where = [];
$params = [];

if ($search) {
    $where[] = "q.question_text LIKE ?";
    $params[] = "%$search%";
}
if ($typeFilter && in_array($typeFilter, ['mcq', 'coding', 'explanation'])) {
    $where[] = "q.type = ?";
    $params[] = $typeFilter;
}
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY q.created_at DESC LIMIT 100";

$questions = $pdo->prepare($sql);
$questions->execute($params);
$questions = $questions->fetchAll();

// Counts
$totalMcq = $pdo->query("SELECT COUNT(*) FROM questions WHERE type='mcq'")->fetchColumn();
$totalCoding = $pdo->query("SELECT COUNT(*) FROM questions WHERE type='coding'")->fetchColumn();
$totalExplanation = $pdo->query("SELECT COUNT(*) FROM questions WHERE type='explanation'")->fetchColumn();
$totalQuestions = $pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn();
?>

<div class="dashboard-header">
    <div class="dashboard-header-left">
        <h1>Question Library</h1>
        <p class="dashboard-subtitle">Browse all questions across every assessment</p>
    </div>
    <div class="dashboard-header-right">
        <a href="assessment_studio.php" class="btn btn-primary btn-sm">+ New Question</a>
    </div>
</div>

<!-- Stats -->
<div class="stats-row">
    <div class="stat-box">
        <div class="stat-num"><?= $totalQuestions ?></div>
        <div class="stat-label">Total Questions</div>
    </div>
    <div class="stat-box">
        <div class="stat-num"><?= $totalMcq ?></div>
        <div class="stat-label">MCQ</div>
    </div>
    <div class="stat-box">
        <div class="stat-num"><?= $totalCoding ?></div>
        <div class="stat-label">Coding</div>
    </div>
    <div class="stat-box">
        <div class="stat-num"><?= $totalExplanation ?></div>
        <div class="stat-label">Explanation</div>
    </div>
</div>

<!-- Search & Filter -->
<div class="card-flat" style="margin-bottom:var(--space-4);">
    <form method="GET" class="form-inline" style="display:flex;gap:var(--space-3);align-items:end;flex-wrap:wrap;">
        <div class="form-group" style="flex:1;min-width:200px;">
            <label class="form-label">Search Questions</label>
            <input class="form-input" type="text" name="q" value="<?= h($search) ?>" placeholder="Search by keyword...">
        </div>
        <div class="form-group">
            <label class="form-label">Type</label>
            <select class="form-select" name="type">
                <option value="">All Types</option>
                <option value="mcq" <?= $typeFilter === 'mcq' ? 'selected' : '' ?>>MCQ</option>
                <option value="coding" <?= $typeFilter === 'coding' ? 'selected' : '' ?>>Coding</option>
                <option value="explanation" <?= $typeFilter === 'explanation' ? 'selected' : '' ?>>Explanation</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Filter</button>
        <?php if ($search || $typeFilter): ?>
            <a href="question_library.php" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Questions Table -->
<div class="card-flat">
    <?php if (empty($questions)): ?>
        <div class="empty-state" style="padding:var(--space-8);text-align:center;">
            <div class="empty-icon">
                <svg viewBox="0 0 20 20" fill="currentColor" width="48" height="48"><path d="M9 3a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-1v1a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1V3zm0 1H4a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-1H9a2 2 0 0 1-2-2V4zm2-1v5a1 1 0 0 0 1 1h5V4a1 1 0 0 0-1-1h-4z"/></svg>
            </div>
            <h3>No Questions Found</h3>
            <p><?= $search ? 'No questions match your search criteria.' : 'The question library is empty. Create an assessment and add questions to get started.' ?></p>
            <?php if (!$search): ?>
                <a href="assessment_studio.php" class="btn btn-primary" style="margin-top:var(--space-3);">Create Assessment</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Question</th>
                        <th>Type</th>
                        <th>Marks</th>
                        <th>Assessment</th>
                        <th>Batch</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($questions as $q): ?>
                    <tr>
                        <td style="max-width:300px;">
                            <div class="text-sm" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                <?= h(mb_substr($q['question_text'], 0, 100)) ?><?= mb_strlen($q['question_text']) > 100 ? '...' : '' ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge <?= $q['type'] === 'mcq' ? 'badge-active' : ($q['type'] === 'coding' ? 'badge-pending' : 'badge-success') ?>">
                                <?= ucfirst($q['type']) ?>
                            </span>
                        </td>
                        <td class="text-muted"><?= (int)$q['marks'] ?></td>
                        <td class="text-sm"><?= h($q['test_title']) ?></td>
                        <td class="text-sm text-muted"><?= h($q['batch_name']) ?></td>
                        <td class="text-sm text-muted"><?= date('d M Y', strtotime($q['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>
