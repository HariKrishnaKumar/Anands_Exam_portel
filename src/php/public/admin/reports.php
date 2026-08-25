<?php
$pageTitle = 'PCI Reports';
require_once __DIR__ . '/../../includes/admin_header.php';

$pdo = getDB();
$message = '';

$currentTestId = (int)($_GET['test_id'] ?? 0);
$currentStudentId = (int)($_GET['student_id'] ?? 0);

// ─── Generate PCI (call Python API or calculate locally) ────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_pci'])) {
    requireCsrf();
    $testId = (int)$_POST['test_id'];

    // Get all evaluated submissions for this test
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_id, s.total_marks_obtained, s.total_marks,
               st.name AS student_name
        FROM submissions s
        JOIN students st ON st.id = s.student_id
        WHERE s.test_id = ? AND s.status = 'evaluated'
    ");
    $stmt->execute([$testId]);
    $evaluated = $stmt->fetchAll();

    if (empty($evaluated)) {
        $message = 'No evaluated submissions found. Grade submissions first.';
    } else {
        $pdo->beginTransaction();
        try {
            foreach ($evaluated as $sub) {
                // Get per-category scores
                $stmt = $pdo->prepare("
                    SELECT q.type,
                           SUM(sa.marks_obtained) AS obtained,
                           SUM(q.marks) AS total
                    FROM student_answers sa
                    JOIN questions q ON q.id = sa.question_id
                    WHERE sa.submission_id = ?
                    GROUP BY q.type
                ");
                $stmt->execute([$sub['id']]);
                $categoryScores = $stmt->fetchAll();

                $scores = ['mcq' => [0, 0], 'coding' => [0, 0], 'explanation' => [0, 0]];
                foreach ($categoryScores as $cs) {
                    $scores[$cs['type']] = [(float)$cs['obtained'], (float)$cs['total']];
                }

                // Calculate PCI
                // MCQ weight 40%, Coding weight 30%, Explanation weight 30%
                $mcqPct = $scores['mcq'][1] > 0 ? ($scores['mcq'][0] / $scores['mcq'][1]) * 100 : 0;
                $codingPct = $scores['coding'][1] > 0 ? ($scores['coding'][0] / $scores['coding'][1]) * 100 : 0;
                $explanationPct = $scores['explanation'][1] > 0 ? ($scores['explanation'][0] / $scores['explanation'][1]) * 100 : 0;

                $pciScore = ($mcqPct * 0.40) + ($codingPct * 0.30) + ($explanationPct * 0.30);

                // Upsert PCI record
                $stmt = $pdo->prepare("
                    INSERT INTO pci_records (student_id, test_id, pci_score, mcq_score, coding_score, explanation_score,
                                             mcq_weight, coding_weight, explanation_weight)
                    VALUES (?, ?, ?, ?, ?, ?, 40.00, 30.00, 30.00)
                    ON DUPLICATE KEY UPDATE
                        pci_score = VALUES(pci_score),
                        mcq_score = VALUES(mcq_score),
                        coding_score = VALUES(coding_score),
                        explanation_score = VALUES(explanation_score),
                        generated_at = NOW()
                ");
                $stmt->execute([
                    $sub['student_id'], $testId,
                    round($pciScore, 2),
                    round($mcqPct, 2),
                    round($codingPct, 2),
                    round($explanationPct, 2),
                ]);
            }
            $pdo->commit();
            $message = 'PCI scores generated for ' . count($evaluated) . ' students.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Error: ' . h($e->getMessage());
        }
    }
}

// ─── Tests list ──────────────────────────────────────────────
$tests = $pdo->query("
    SELECT t.id, t.title, b.name AS batch_name, c.name AS course_name, cl.name AS college_name,
           t.status,
           (SELECT COUNT(*) FROM pci_records pr WHERE pr.test_id = t.id) AS pci_count,
           (SELECT COUNT(*) FROM submissions s WHERE s.test_id = t.id AND s.status = 'evaluated') AS evaluated_count
    FROM tests t
    JOIN batches b ON b.id = t.batch_id
    JOIN courses c ON c.id = b.course_id
    JOIN colleges cl ON cl.id = c.college_id
    ORDER BY t.created_at DESC
")->fetchAll();

// ─── PCI data for selected test ──────────────────────────────
$pciData = [];
$chartLabels = [];
$chartPci = [];
$chartMcq = [];
$chartCoding = [];
$chartExplanation = [];

if ($currentTestId > 0) {
    if ($currentStudentId > 0) {
        // Single student detail
        $stmt = $pdo->prepare("
            SELECT pr.*, st.name AS student_name, st.email, st.roll_number,
                   t.title AS test_title
            FROM pci_records pr
            JOIN students st ON st.id = pr.student_id
            JOIN tests t ON t.id = pr.test_id
            WHERE pr.test_id = ? AND pr.student_id = ?
        ");
        $stmt->execute([$currentTestId, $currentStudentId]);
        $pciData = $stmt->fetchAll();
    } else {
        // All students for this test
        $stmt = $pdo->prepare("
            SELECT pr.*, st.name AS student_name, st.email, st.roll_number
            FROM pci_records pr
            JOIN students st ON st.id = pr.student_id
            WHERE pr.test_id = ?
            ORDER BY pr.pci_score DESC
        ");
        $stmt->execute([$currentTestId]);
        $pciData = $stmt->fetchAll();

        // Build chart data
        foreach ($pciData as $row) {
            $chartLabels[] = $row['student_name'];
            $chartPci[] = (float)$row['pci_score'];
            $chartMcq[] = (float)$row['mcq_score'];
            $chartCoding[] = (float)$row['coding_score'];
            $chartExplanation[] = (float)$row['explanation_score'];
        }
    }
}

// Get students with PCI for selected test
$pciStudents = [];
if ($currentTestId > 0) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT st.id, st.name, st.email, st.roll_number, pr.pci_score
        FROM pci_records pr
        JOIN students st ON st.id = pr.student_id
        WHERE pr.test_id = ?
        ORDER BY pr.pci_score DESC
    ");
    $stmt->execute([$currentTestId]);
    $pciStudents = $stmt->fetchAll();
}
?>

<?php if ($message): ?>
    <div class="alert alert-success">
        <?= icon('check-circle', 18, 'var(--green)') ?>
        <span><?= h($message) ?></span>
    </div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-4);margin-bottom:16px;">
    <!-- Test Selector -->
    <div class="panel">
        <div class="panel-header">
            <h2>Select Test</h2>
        </div>
        <div class="panel-body">
            <form method="GET" action="reports.php">
                <div class="form-group">
                    <label>Test</label>
                    <select class="form-select" name="test_id" onchange="this.form.submit()">
                        <option value="">— Select Test —</option>
                        <?php foreach ($tests as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $currentTestId === (int)$t['id'] ? 'selected' : '' ?>>
                                <?= h($t['title']) ?> — <?= h($t['batch_name']) ?> (<?= $t['pci_count'] ?>/<?= $t['evaluated_count'] ?> PCI)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($currentStudentId > 0): ?>
                    <input type="hidden" name="student_id" value="<?= $currentStudentId ?>">
                <?php endif; ?>
            </form>

            <?php if ($currentTestId > 0 && !empty($pciStudents)): ?>
            <form method="GET" action="reports.php" style="margin-top:var(--space-3);">
                <input type="hidden" name="test_id" value="<?= $currentTestId ?>">
                <div class="form-group">
                    <label>Drill Down — Student</label>
                    <select class="form-select" name="student_id" onchange="this.form.submit()">
                        <option value="">— All Students —</option>
                        <?php foreach ($pciStudents as $ps): ?>
                            <option value="<?= $ps['id'] ?>" <?= $currentStudentId === (int)$ps['id'] ? 'selected' : '' ?>>
                                <?= h($ps['name']) ?> (PCI: <?= $ps['pci_score'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Generate PCI -->
    <div class="panel">
        <div class="panel-header">
            <h2>Generate PCI Scores</h2>
        </div>
        <div class="panel-body">
            <p class="text-sm text-muted mb-2">
                Calculate PCI (Performance Competency Index) for all evaluated submissions in a test.
                PCI = MCQ×40% + Coding×30% + Explanation×30%.
            </p>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="generate_pci" value="1">
                <div class="form-group">
                    <select class="form-select" name="test_id" required>
                        <option value="">— Select Test —</option>
                        <?php foreach ($tests as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $currentTestId === (int)$t['id'] ? 'selected' : '' ?>>
                                <?= h($t['title']) ?> — <?= h($t['batch_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Generate PCI</button>
            </form>
        </div>
    </div>
</div>

<?php if ($currentTestId > 0 && !empty($pciData) && empty($currentStudentId)): ?>
    <!-- PCI Overview Charts -->
    <!-- .charts-grid children carry min-width/min-height:0 (CSS grid blowout guard);
         each canvas sits in a fixed-height .chart-wrapper so Chart.js can never
         enter its parent-height resize feedback loop. -->
    <div class="charts-grid">
        <div class="chart-container">
            <h3>Overall PCI Score Distribution</h3>
            <div class="chart-wrapper">
                <canvas id="pciBarChart"></canvas>
            </div>
        </div>
        <div class="chart-container">
            <h3>Category Average</h3>
            <div class="chart-wrapper">
                <canvas id="pciCategoryChart"></canvas>
            </div>
        </div>
    </div>

    <div class="charts-grid">
        <div class="chart-container">
            <h3>Score Breakdown by Student</h3>
            <div class="chart-wrapper">
                <canvas id="pciStackedChart"></canvas>
            </div>
        </div>
        <div class="chart-container">
            <h3>PCI Distribution (Histogram)</h3>
            <div class="chart-wrapper">
                <canvas id="pciHistogramChart"></canvas>
            </div>
        </div>
    </div>

    <!-- PCI Table -->
    <div class="panel">
        <div class="panel-header">
            <h2>PCI Scores — All Students</h2>
            <span class="text-muted text-sm"><?= count($pciData) ?> students</span>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Student</th>
                        <th>Roll #</th>
                        <th>PCI Score</th>
                        <th>MCQ (40%)</th>
                        <th>Coding (30%)</th>
                        <th>Explanation (30%)</th>
                        <th class="actions">Drill Down</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pciData as $i => $pr): ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td><strong><?= h($pr['student_name']) ?></strong><br><span class="text-sm text-muted"><?= h($pr['email']) ?></span></td>
                        <td class="text-sm"><?= h($pr['roll_number']) ?></td>
                        <td>
                            <span style="font-weight:700;font-size:1rem;color:<?= (float)$pr['pci_score'] >= 75 ? 'var(--green)' : ((float)$pr['pci_score'] >= 50 ? 'var(--yellow)' : 'var(--red)') ?>">
                                <?= (float)$pr['pci_score'] ?>%
                            </span>
                        </td>
                        <td class="text-sm"><?= (float)$pr['mcq_score'] ?>%</td>
                        <td class="text-sm"><?= (float)$pr['coding_score'] ?>%</td>
                        <td class="text-sm"><?= (float)$pr['explanation_score'] ?>%</td>
                        <td class="actions">
                            <a href="reports.php?test_id=<?= $currentTestId ?>&student_id=<?= $pr['student_id'] ?>" class="btn btn-sm btn-ghost">Detail</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
    (function() {
        const labels = <?= json_encode($chartLabels) ?>;
        const pciScores = <?= json_encode($chartPci) ?>;
        const mcqScores = <?= json_encode($chartMcq) ?>;
        const codingScores = <?= json_encode($chartCoding) ?>;
        const explanationScores = <?= json_encode($chartExplanation) ?>;

        const colors = {
            pci: '#0078D4',
            mcq: '#0B6A0B',
            coding: '#C85A00',
            explanation: '#8A6D00',
        };

        // ─── Safe mount: destroy any existing instance first ───
        // Re-renders (tab switches, AJAX refreshes) must never stack
        // duplicate Chart instances fighting over the same canvas.
        function mountChart(canvasId, config) {
            const el = document.getElementById(canvasId);
            if (!el) return null;
            const existing = Chart.getChart(el);
            if (existing) existing.destroy();
            return new Chart(el, config);
        }

        // Shared defaults: bounded by .chart-wrapper (fixed 350px box).
        // maintainAspectRatio:false + resizeDelay stops the aspect-ratio
        // recalculation loop that caused infinite canvas/page growth.
        const baseOptions = {
            responsive: true,
            maintainAspectRatio: false,
            resizeDelay: 200,
        };

        // ─── Bar Chart ────────────────────────────────────
        window.pciBarChart = mountChart('pciBarChart', {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'PCI Score (%)',
                    data: pciScores,
                    backgroundColor: pciScores.map(v => v >= 75 ? '#0B6A0B88' : v >= 50 ? '#8A6D0088' : '#BC2F3288'),
                    borderColor: pciScores.map(v => v >= 75 ? '#0B6A0B' : v >= 50 ? '#8A6D00' : '#BC2F32'),
                    borderWidth: 1,
                }]
            },
            options: { ...baseOptions,
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    y: { beginAtZero: true, max: 100, title: { display: true, text: 'PCI Score (%)' } },
                    x: { ticks: { maxRotation: 45, font: { size: 10 } } }
                }
            }
        });

        // ─── Category Average ─────────────────────────────
        const avgMcq = mcqScores.reduce((a, b) => a + b, 0) / mcqScores.length || 0;
        const avgCoding = codingScores.reduce((a, b) => a + b, 0) / codingScores.length || 0;
        const avgExpl = explanationScores.reduce((a, b) => a + b, 0) / explanationScores.length || 0;

        window.pciCategoryChart = mountChart('pciCategoryChart', {
            type: 'doughnut',
            data: {
                labels: ['MCQ (40%)', 'Coding (30%)', 'Explanation (30%)'],
                datasets: [{
                    data: [avgMcq, avgCoding, avgExpl],
                    backgroundColor: [colors.mcq, colors.coding, colors.explanation],
                    borderWidth: 2,
                }]
            },
            options: { ...baseOptions,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ctx.label + ': ' + ctx.parsed.toFixed(1) + '%';
                            }
                        }
                    }
                }
            }
        });

        // ─── Stacked Bar ──────────────────────────────────
        window.pciStackedChart = mountChart('pciStackedChart', {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { label: 'MCQ', data: mcqScores, backgroundColor: colors.mcq + 'CC' },
                    { label: 'Coding', data: codingScores, backgroundColor: colors.coding + 'CC' },
                    { label: 'Explanation', data: explanationScores, backgroundColor: colors.explanation + 'CC' },
                ]
            },
            options: { ...baseOptions,
                scales: {
                    x: { stacked: true, ticks: { maxRotation: 45, font: { size: 10 } } },
                    y: { stacked: true, max: 100, title: { display: true, text: 'Score (%)' } }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            footer: function(items) {
                                let total = 0;
                                items.forEach(i => total += i.parsed);
                                return 'Weighted: ' + total.toFixed(1) + '%';
                            }
                        }
                    }
                }
            }
        });

        // ─── Histogram ────────────────────────────────────
        const bins = [0, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100];
        const histData = bins.slice(0, -1).map((bin, i) => {
            return pciScores.filter(v => v >= bin && v < bins[i + 1]).length;
        });

        window.pciHistogramChart = mountChart('pciHistogramChart', {
            type: 'bar',
            data: {
                labels: bins.slice(0, -1).map((b, i) => b + '-' + bins[i + 1]),
                datasets: [{
                    label: 'Students',
                    data: histData,
                    backgroundColor: '#0078D488',
                    borderColor: '#0078D4',
                    borderWidth: 1,
                }]
            },
            options: { ...baseOptions,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Number of Students' } },
                    x: { title: { display: true, text: 'PCI Score Range (%)' } }
                }
            }
        });
    })();
    </script>

<?php elseif ($currentStudentId > 0 && !empty($pciData)): ?>
    <!-- Single Student PCI Detail -->
    <?php $pr = $pciData[0]; ?>
    <div class="panel">
        <div class="panel-header">
            <h2>Student Detail: <?= h($pr['student_name']) ?></h2>
            <a href="reports.php?test_id=<?= $currentTestId ?>" class="btn btn-sm btn-ghost">← Back to all students</a>
        </div>
        <div class="panel-body">
            <div class="stats-row">
                <div class="stat-box">
                    <div class="stat-num" style="color:<?= (float)$pr['pci_score'] >= 75 ? 'var(--green)' : ((float)$pr['pci_score'] >= 50 ? 'var(--yellow)' : 'var(--red)') ?>">
                        <?= (float)$pr['pci_score'] ?>%
                    </div>
                    <div class="stat-label">PCI Score</div>
                </div>
                <div class="stat-box">
                    <div class="stat-num" style="color:var(--green);"><?= (float)$pr['mcq_score'] ?>%</div>
                    <div class="stat-label">MCQ (40% weight)</div>
                </div>
                <div class="stat-box">
                    <div class="stat-num" style="color:var(--orange);"><?= (float)$pr['coding_score'] ?>%</div>
                    <div class="stat-label">Coding (30% weight)</div>
                </div>
                <div class="stat-box">
                    <div class="stat-num" style="color:var(--yellow);"><?= (float)$pr['explanation_score'] ?>%</div>
                    <div class="stat-label">Explanation (30% weight)</div>
                </div>
            </div>

            <div class="chart-container" style="max-width:400px;margin:var(--space-4) auto;">
                <h3 style="text-align:center;">PCI Breakdown</h3>
                <div class="chart-wrapper" style="height:320px;max-height:320px;">
                    <canvas id="studentRadarChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
    (function() {
        const el = document.getElementById('studentRadarChart');
        if (!el) return;
        // Destroy-before-create: never stack duplicate instances
        const existing = Chart.getChart(el);
        if (existing) existing.destroy();

        window.studentRadarChart = new Chart(el, {
            type: 'radar',
            data: {
                labels: ['MCQ', 'Coding', 'Explanation'],
                datasets: [{
                    label: 'Score (%)',
                    data: [<?= (float)$pr['mcq_score'] ?>, <?= (float)$pr['coding_score'] ?>, <?= (float)$pr['explanation_score'] ?>],
                    backgroundColor: 'rgba(0,120,212,0.2)',
                    borderColor: '#0078D4',
                    borderWidth: 2,
                    pointBackgroundColor: '#0078D4',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                resizeDelay: 200,
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 100,
                        ticks: { stepSize: 20 }
                    }
                }
            }
        });
    })();
    </script>

<?php elseif ($currentTestId > 0): ?>
    <div class="panel">
        <div class="panel-header">
            <h2>No PCI Data</h2>
        </div>
        <div class="panel-body">
            <p class="text-muted">
                No PCI records found for this test.
                <?php if (empty($pciStudents)): ?>
                    Have you graded all submissions and generated PCI scores?
                <?php endif; ?>
            </p>
            <form method="POST" style="margin-top:var(--space-3);">
                <?= csrfField() ?>
                <input type="hidden" name="generate_pci" value="1">
                <input type="hidden" name="test_id" value="<?= $currentTestId ?>">
                <button type="submit" class="btn btn-primary">Generate PCI Now</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>
