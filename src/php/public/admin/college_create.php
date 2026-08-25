<?php
/**
 * College Registration Wizard - 4-Step Creation Flow
 * 
 * Only Super Admin and Platform Admin can access.
 * Uses session-based draft storage with server-side validation.
 * Final submission uses a single DB transaction.
 */

$pageTitle = 'Create College';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
startSession();
requireAdmin();

// ─── Access Control ─────────────────────────────────────────
$adminRole = $_SESSION['admin_role'] ?? 'admin';
if (!in_array($adminRole, ['super_admin', 'platform_admin'], true)) {
    flash('error', 'You do not have permission to create colleges.');
    redirect('/admin/colleges.php');
}

require_once __DIR__ . '/../../includes/admin_header.php';

$pdo = getDB();

// ─── Access Control ─────────────────────────────────────────
$adminRole = $_SESSION['admin_role'] ?? 'admin';
if (!in_array($adminRole, ['super_admin', 'platform_admin'], true)) {
    flash('error', 'You do not have permission to create colleges.');
    redirect('/admin/colleges.php');
}

// ─── Constants ──────────────────────────────────────────────
/**
 * Canonical degree / stream catalogue, grouped by academic level.
 * Used for the Step 2 "Streams" picker and server-side validation.
 */
$DEGREE_OPTIONS = [
    'UGC' => [
        'Bachelor of Arts (BA)',
        'Bachelor of Science (BSc)',
        'Bachelor of Commerce (BCom)',
        'Bachelor of Technology (BTech)',
        'Bachelor of Engineering (BE)',
        'Bachelor of Business Administration (BBA)',
        'Bachelor of Computer Applications (BCA)',
        'Bachelor of Pharmacy (BPharm)',
        'Bachelor of Medicine, Bachelor of Surgery (MBBS)',
        'Bachelor of Dental Surgery (BDS)',
        'Bachelor of Laws (LLB)',
        'Bachelor of Education (BEd)',
        'Bachelor of Architecture (BArch)',
        'Bachelor of Design (BDes)',
        'Bachelor of Fine Arts (BFA)',
        'Bachelor of Hotel Management (BHM)',
        'Bachelor of Social Work (BSW)',
        'Bachelor of Physiotherapy (BPT)',
        'Bachelor of Nursing (BSc Nursing)',
        'Bachelor of Ayurvedic Medicine and Surgery (BAMS)',
        'Bachelor of Homeopathic Medicine and Surgery (BHMS)',
        'Bachelor of Veterinary Science and Animal Husbandry (BVSc & AH)',
        'Bachelor of Journalism and Mass Communication (BJMC)',
        'Bachelor of Library and Information Science (BLIS)',
        'Bachelor of Agricultural Science (BSc Agriculture)',
    ],
    'PGC' => [
        'Master of Arts (MA)',
        'Master of Science (MSc)',
        'Master of Commerce (MCom)',
        'Master of Technology (MTech)',
        'Master of Engineering (ME)',
        'Master of Business Administration (MBA)',
        'Master of Computer Applications (MCA)',
        'Master of Pharmacy (MPharm)',
        'Master of Laws (LLM)',
        'Master of Education (MEd)',
        'Master of Architecture (MArch)',
        'Master of Design (MDes)',
        'Master of Fine Arts (MFA)',
        'Master of Social Work (MSW)',
        'Master of Public Health (MPH)',
        'Master of Journalism and Mass Communication (MJMC)',
        'Master of Library and Information Science (MLIS)',
        'Master of Engineering Management (MEM)',
        'Master of Public Administration (MPA)',
        'Master of Social Sciences (MSS)',
        'Master of Philosophy (MPhil)',
        'Master of Veterinary Science (MVSc)',
        'Master of Agricultural Science (MSc Agriculture)',
        'Master of Physiotherapy (MPT)',
    ],
];

// Flat canonical list + reverse lookup: degree name → category
$DEGREE_FLAT = array_merge($DEGREE_OPTIONS['UGC'], $DEGREE_OPTIONS['PGC']);
$DEGREE_CATEGORY = [];
foreach ($DEGREE_OPTIONS as $cat => $names) {
    foreach ($names as $name) {
        $DEGREE_CATEGORY[$name] = $cat;
    }
}

$NAAC_GRADES = ['None', 'A++', 'A+', 'A', 'B+', 'B', 'C'];

// ─── Draft Session Init ─────────────────────────────────────
if (!isset($_SESSION['college_draft'])) {
    $_SESSION['college_draft'] = [
        'step' => 1,
        'step1' => [],
        'step2' => [],
        'step3' => [],
    ];
}
$draft = &$_SESSION['college_draft'];
$currentStep = (int)($draft['step'] ?? 1);

// ─── Helper: Generate Unique College Code ───────────────────
/**
 * Always returns a code that is NOT already present in the colleges table.
 * Starts from the next sequential id, then walks upward / falls back to a
 * random suffix so stale drafts can never collide with an existing code
 * (e.g. a draft that carried the seed college's "COL000001").
 */
function generateCollegeCode(PDO $pdo): string {
    $nextId = 0;
    $stmt = $pdo->query("SELECT id FROM colleges ORDER BY id DESC LIMIT 1");
    $row = $stmt->fetchColumn();
    if ($row !== false) $nextId = ((int)$row) + 1;

    $attempts = 0;
    while ($attempts < 10000) {
        $candidate = 'COL' . str_pad((string)$nextId, 6, '0', STR_PAD_LEFT);
        $chk = $pdo->prepare("SELECT COUNT(*) FROM colleges WHERE college_code = ?");
        $chk->execute([$candidate]);
        if ((int)$chk->fetchColumn() === 0) {
            return $candidate;
        }
        $nextId++;
        $attempts++;
    }
    // Extremely unlikely fallback: random 6-digit suffix
    return 'COL' . str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
}

// ─── Helper: Generate Batch Nick Name ───────────────────────
function generateBatchNickName(string $collegeNick, string $streamName, int $joiningYear, int $joiningMonth, PDO $pdo): string {
    $collegePart = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', substr($collegeNick, 0, 5)));
    $streamPart = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', substr($streamName, 0, 4)));
    if (empty($collegePart)) $collegePart = 'COL';
    if (empty($streamPart)) $streamPart = 'STR';
    $base = $collegePart . '_' . $streamPart . '_' . $joiningYear . str_pad((string)$joiningMonth, 2, '0', STR_PAD_LEFT);

    // Ensure uniqueness
    $check = $pdo->prepare("SELECT COUNT(*) FROM college_batches WHERE batch_nick_name = ?");
    $check->execute([$base]);
    if ($check->fetchColumn() == 0) return $base;

    $suffix = 1;
    while (true) {
        $candidate = $base . '_' . str_pad((string)$suffix, 2, '0', STR_PAD_LEFT);
        $check->execute([$candidate]);
        if ($check->fetchColumn() == 0) return $candidate;
        $suffix++;
    }
}

// ─── Helper: Compute ending year ───────────────────────────
function computeEndingYear(int $joiningYear, int $duration): int {
    return $joiningYear + $duration;
}

// ─── Helper: Generate academic year string ─────────────────
function academicYearString(int $joiningYear, int $duration): string {
    return $joiningYear . '-' . ($joiningYear + $duration);
}

// ─── Handle POST ───────────────────────────────────────────
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['wizard_action'] ?? '';
    $step = (int)($_POST['step'] ?? 1);

    // ── Handle Goto (Edit from Review) ────────────────────
    // Navigate to a step without validating current step data
    if (strpos($action, 'goto_') === 0) {
        $targetStep = (int)substr($action, 5);
        if ($targetStep >= 1 && $targetStep <= 4) {
            $draft['step'] = $targetStep;
            $_SESSION['college_draft'] = $draft;
            redirect('/admin/college_create.php');
        }
    }

    // ── Handle Save Draft (no step change) ────────────────
    if ($action === 'save_draft') {
        // Process current step data but stay on same step
        $processAndStay = true;
    } else {
        $processAndStay = false;
    }

    // ── Process based on step ─────────────────────────────
    if ($step === 1) {
        $data = [
            'name'              => trim($_POST['name'] ?? ''),
            'college_code'      => trim($_POST['college_code'] ?? ''),
            'nick_name'         => trim($_POST['nick_name'] ?? ''),
            'established_year'  => trim($_POST['established_year'] ?? ''),
            'website'           => trim($_POST['website'] ?? ''),
            'email'             => trim($_POST['email'] ?? ''),
            'phone'             => trim($_POST['phone'] ?? ''),
            'address'           => trim($_POST['address'] ?? ''),
            'country'           => trim($_POST['country'] ?? ''),
            'state'             => trim($_POST['state'] ?? ''),
            'district'          => trim($_POST['district'] ?? ''),
            'city'              => trim($_POST['city'] ?? ''),
            'pincode'           => trim($_POST['pincode'] ?? ''),
            'description'       => trim($_POST['description'] ?? ''),
        ];

        // Validate required
        if (empty($data['name'])) $errors[] = 'College Name is required.';
        if (empty($data['nick_name'])) $errors[] = 'College Nick Name is required.';

        // Validate unique nick_name (if changed)
        if (!empty($data['nick_name'])) {
            $existing = $draft['step1']['nick_name'] ?? '';
            if ($data['nick_name'] !== $existing) {
                $stmt = $pdo->prepare("SELECT id FROM colleges WHERE nick_name = ?");
                $stmt->execute([$data['nick_name']]);
                if ($stmt->fetch()) {
                    $errors[] = 'College Nick Name "' . h($data['nick_name']) . '" is already taken.';
                }
            }
        }

        // Validate website format
        if (!empty($data['website']) && !filter_var($data['website'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Website URL is not valid.';
        }

        // Validate email format
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email address is not valid.';
        }

        // Auto-generate the college code if it is missing OR if the draft
        // carried a code that is already taken (e.g. a stale draft that
        // reused the seed college's "COL000001"). Regenerating silently
        // guarantees the "Duplicate entry '...' for key 'uq_college_code'"
        // failure can never block creation.
        if (empty($data['college_code'])) {
            $data['college_code'] = generateCollegeCode($pdo);
        } else {
            $exists = $pdo->prepare("SELECT id FROM colleges WHERE college_code = ?");
            $exists->execute([$data['college_code']]);
            if ($exists->fetch()) {
                $data['college_code'] = generateCollegeCode($pdo);
            }
        }

        if (empty($errors)) {
            $draft['step1'] = $data;
            if (!$processAndStay) {
                $draft['step'] = $action === 'next' ? 2 : 1;
            }
        }
    } 
    elseif ($step === 2) {
        $selectedStreams = $_POST['streams'] ?? [];
        $customStream = trim($_POST['custom_stream'] ?? '');
        $customCategory = ($_POST['custom_stream_category'] ?? 'UGC') === 'PGC' ? 'PGC' : 'UGC';

        // Build stream list from the canonical catalogue only.
        // Unknown/injected keys are silently dropped (whitelist validation).
        $streams = [];
        $streamCategoryMap = [];
        foreach ((array)$selectedStreams as $s) {
            $s = trim($s);
            if (isset($DEGREE_CATEGORY[$s])) {
                if (!in_array($s, $streams, true)) {
                    $streams[] = $s;
                    $streamCategoryMap[$s] = $DEGREE_CATEGORY[$s];
                }
            }
        }
        if (!empty($customStream)) {
            // Split custom stream by comma for multiple entries
            $customs = array_map('trim', explode(',', $customStream));
            foreach ($customs as $cs) {
                if (empty($cs)) continue;
                if (in_array($cs, $streams, true)) continue;
                if (count($streams) >= 60) break; // sanity cap
                $streams[] = $cs;
                $streamCategoryMap[$cs] = $customCategory;
            }
        }

        $data = [
            'recognized_university' => trim($_POST['recognized_university'] ?? ''),
            'affiliated_university' => trim($_POST['affiliated_university'] ?? ''),
            'autonomous'            => trim($_POST['autonomous'] ?? ''),
            'accreditation_naac'    => !empty($_POST['accreditation_naac']) ? 1 : 0,
            'naac_grade'            => trim($_POST['naac_grade'] ?? ''),
            'accreditation_nba'     => !empty($_POST['accreditation_nba']) ? 1 : 0,
            'accreditation_aicte'   => !empty($_POST['accreditation_aicte']) ? 1 : 0,
            'accreditation_ugc'     => !empty($_POST['accreditation_ugc']) ? 1 : 0,
            'streams'               => $streams,
            'stream_category_map'   => $streamCategoryMap,
            'custom_stream_value'   => $customStream,
        ];

        // Validate at least one stream
        if (empty($streams)) {
            $errors[] = 'Select at least one stream.';
        }

        if (empty($errors)) {
            $draft['step2'] = $data;
            if (!$processAndStay) {
                $draft['step'] = $action === 'next' ? 3 : 1;
            }
        }
    }
    elseif ($step === 3) {
        $batches = [];
        $batchNames = $_POST['batch_stream_id'] ?? [];
        $joiningYears = $_POST['batch_joining_year'] ?? [];
        $joiningMonths = $_POST['batch_joining_month'] ?? [];
        $durations = $_POST['batch_duration'] ?? [];
        $statuses = $_POST['batch_status'] ?? [];
        $removals = $_POST['batch_remove'] ?? [];

        $collegeNick = $draft['step1']['nick_name'] ?? 'COL';
        $streamNames = $draft['step2']['streams'] ?? [];

        $valid = true;
        $batchCount = count($batchNames);

        for ($i = 0; $i < $batchCount; $i++) {
            // Skip if marked for removal
            if (in_array((string)$i, $removals, true)) continue;

            $streamId = (int)($batchNames[$i] ?? 0);
            $joiningYear = (int)($joiningYears[$i] ?? 0);
            $joiningMonth = (int)($joiningMonths[$i] ?? 0);
            $duration = (int)($durations[$i] ?? 0);
            $status = $statuses[$i] ?? 'active';

            if ($streamId <= 0 || $joiningYear <= 0 || $joiningMonth < 1 || $joiningMonth > 12 || $duration < 1) {
                $errors[] = 'Batch #' . ($i + 1) . ' has invalid data.';
                $valid = false;
                continue;
            }

            // Resolve stream name
            $streamName = '';
            foreach ($draft['step2']['streams'] ?? [] as $idx => $sn) {
                // Use index as stream identifier (1-based)
                if (($idx + 1) === $streamId) {
                    $streamName = $sn;
                    break;
                }
            }
            // Also check stored stream_id mapping from previous drafts
            if (empty($streamName)) {
                $streamMap = $draft['step3']['stream_map'] ?? [];
                $streamName = $streamMap[$streamId] ?? ('Stream ' . $streamId);
            }

            $endingYear = computeEndingYear($joiningYear, $duration);
            $batchNickName = generateBatchNickName($collegeNick, $streamName, $joiningYear, $joiningMonth, $pdo);

            $batches[] = [
                'stream_id'       => $streamId,
                'stream_name'     => $streamName,
                'academic_year'   => academicYearString($joiningYear, $duration),
                'joining_year'    => $joiningYear,
                'joining_month'   => $joiningMonth,
                'course_duration' => $duration,
                'ending_year'     => $endingYear,
                'batch_nick_name' => $batchNickName,
                'status'          => $status,
            ];
        }

        // Build stream_id → stream_name mapping for the view
        $streamMap = [];
        foreach ($draft['step2']['streams'] ?? [] as $idx => $sn) {
            $streamMap[$idx + 1] = $sn;
        }

        if ($valid) {
            $draft['step3'] = [
                'batches'    => $batches,
                'stream_map' => $streamMap,
            ];
            if (!$processAndStay) {
                $draft['step'] = $action === 'next' ? 4 : 2;
            }
        }
    }
    elseif ($step === 4) {
        // ─── FINAL SUBMISSION: Create College ─────────────
        if ($action === 'create') {
            $step1 = $draft['step1'] ?? [];
            $step2 = $draft['step2'] ?? [];
            $step3 = $draft['step3'] ?? [];

            // Validate all required data exists
            $missing = [];
            if (empty($step1['name'])) $missing[] = 'Step 1: College Name';
            if (empty($step1['nick_name'])) $missing[] = 'Step 1: College Nick Name';
            if (empty($step2['streams'])) $missing[] = 'Step 2: At least one stream';

            if (!empty($missing)) {
                $errors[] = 'Cannot create college. Missing: ' . implode(', ', $missing);
            } else {
                try {
                    $pdo->beginTransaction();

                    // 1. Insert college
                    // Belt-and-braces: guarantee a unique code inside the
                    // transaction even if the draft somehow still holds a
                    // code that was taken between Step 1 and now.
                    $collegeCode = trim((string)($step1['college_code'] ?? ''));
                    if ($collegeCode === '') {
                        $collegeCode = generateCollegeCode($pdo);
                    } else {
                        $dup = $pdo->prepare("SELECT COUNT(*) FROM colleges WHERE college_code = ?");
                        $dup->execute([$collegeCode]);
                        if ((int)$dup->fetchColumn() > 0) {
                            $collegeCode = generateCollegeCode($pdo);
                        }
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO colleges (
                            college_code, name, nick_name, established_year,
                            website, email, phone, address, country, state,
                            district, city, pincode, logo, description,
                            recognized_university, affiliated_university, autonomous,
                            accreditation_naac, naac_grade, accreditation_nba,
                            accreditation_aicte, accreditation_ugc, status
                        ) VALUES (
                            :college_code, :name, :nick_name, :established_year,
                            :website, :email, :phone, :address, :country, :state,
                            :district, :city, :pincode, :logo, :description,
                            :recognized_university, :affiliated_university, :autonomous,
                            :accreditation_naac, :naac_grade, :accreditation_nba,
                            :accreditation_aicte, :accreditation_ugc, 'active'
                        )
                    ");
                    $stmt->execute([
                        'college_code'          => $collegeCode,
                        'name'                  => $step1['name'],
                        'nick_name'             => $step1['nick_name'],
                        'established_year'      => !empty($step1['established_year']) ? (int)$step1['established_year'] : null,
                        'website'               => $step1['website'] ?? null,
                        'email'                 => $step1['email'] ?? null,
                        'phone'                 => $step1['phone'] ?? null,
                        'address'               => $step1['address'] ?? null,
                        'country'               => $step1['country'] ?? null,
                        'state'                 => $step1['state'] ?? null,
                        'district'              => $step1['district'] ?? null,
                        'city'                  => $step1['city'] ?? null,
                        'pincode'               => $step1['pincode'] ?? null,
                        'logo'                  => $step1['logo'] ?? null,
                        'description'           => $step1['description'] ?? null,
                        'recognized_university' => $step2['recognized_university'] ?? null,
                        'affiliated_university' => $step2['affiliated_university'] ?? null,
                        'autonomous'            => $step2['autonomous'] ?? null,
                        'accreditation_naac'    => $step2['accreditation_naac'] ?? 0,
                        'naac_grade'            => !empty($step2['accreditation_naac']) ? ($step2['naac_grade'] ?? null) : null,
                        'accreditation_nba'     => $step2['accreditation_nba'] ?? 0,
                        'accreditation_aicte'   => $step2['accreditation_aicte'] ?? 0,
                        'accreditation_ugc'     => $step2['accreditation_ugc'] ?? 0,
                    ]);

                    $collegeId = (int)$pdo->lastInsertId();

                    // 2. Insert streams (with UGC/PGC category from the canonical catalogue)
                    $streamInsert = $pdo->prepare("INSERT INTO college_streams (college_id, stream_name, category) VALUES (?, ?, ?)");
                    $streamIdMap = []; // old idx → new stream id
                    foreach ($step2['streams'] as $idx => $streamName) {
                        $category = $step2['stream_category_map'][$streamName] ?? 'UGC';
                        $streamInsert->execute([$collegeId, $streamName, $category]);
                        $newStreamId = (int)$pdo->lastInsertId();
                        $streamIdMap[$idx + 1] = $newStreamId; // 1-based index
                    }

                    // 3. Insert batches
                    if (!empty($step3['batches'])) {
                        $batchInsert = $pdo->prepare("
                            INSERT INTO college_batches (
                                college_id, stream_id, academic_year,
                                joining_year, joining_month, course_duration,
                                ending_year, batch_nick_name, status
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        foreach ($step3['batches'] as $batch) {
                            // Map the wizard stream index to actual DB stream ID
                            $dbStreamId = $streamIdMap[$batch['stream_id']] ?? null;
                            if (!$dbStreamId) {
                                throw new Exception('Stream mapping failed for batch.');
                            }
                            // Regenerate nick against live DB so intra-draft
                            // duplicates get suffixed (_01, _02 …) instead of
                            // colliding on the UNIQUE constraint.
                            $streamNameForNick = $step2['streams'][($batch['stream_id'] - 1)] ?? 'Stream';
                            $liveNick = generateBatchNickName(
                                $step1['nick_name'], $streamNameForNick,
                                (int)$batch['joining_year'], (int)$batch['joining_month'], $pdo
                            );
                            $batchInsert->execute([
                                $collegeId,
                                $dbStreamId,
                                $batch['academic_year'],
                                $batch['joining_year'],
                                $batch['joining_month'],
                                $batch['course_duration'],
                                $batch['ending_year'],
                                $liveNick,
                                $batch['status'],
                            ]);
                        }
                    }

                    // 4. Auto-sync: streams → courses, batches → legacy batches
                    //    So signup/test assignment works even if admin forgets
                    //    to add courses manually via the courses page.
                    $legacyCourseIns = $pdo->prepare(
                        "INSERT INTO courses (college_id, name, status) VALUES (?, ?, 'active')"
                    );
                    $legacyBatchIns = $pdo->prepare(
                        "INSERT INTO batches (course_id, name, section, status) VALUES (?, ?, NULL, 'active')"
                    );

                    foreach ($streamIdMap as $wizardIdx => $dbStreamId) {
                        $streamName = $step2['streams'][($wizardIdx - 1)] ?? 'Unknown';
                        $legacyCourseIns->execute([$collegeId, $streamName]);
                        $courseId = (int)$pdo->lastInsertId();

                        // Find all college_batches for this stream → legacy batches
                        $cbStmt = $pdo->prepare(
                            "SELECT batch_nick_name FROM college_batches WHERE stream_id = ?"
                        );
                        $cbStmt->execute([$dbStreamId]);
                        foreach ($cbStmt->fetchAll() as $cb) {
                            $legacyBatchIns->execute([$courseId, $cb['batch_nick_name']]);
                        }
                    }

                    $pdo->commit();

                    // Clear draft
                    unset($_SESSION['college_draft']);

                    flash('success', 'College "' . h($step1['name']) . '" created successfully!');
                    redirect('/admin/college_dashboard.php?id=' . $collegeId);

                } catch (Exception $e) {
                    $pdo->rollBack();
                    $errors[] = 'Failed to create college: ' . $e->getMessage();
                    error_log('College creation failed: ' . $e->getMessage());
                }
            }
        } elseif ($action === 'prev') {
            // Going back to step 3
            $draft['step'] = 3;
        }
    }

    // If errors occurred, keep current step but show errors
    if (!empty($errors)) {
        $draft['step'] = $step;
    }

    // Update session
    $_SESSION['college_draft'] = $draft;

    // Redirect to refresh (PRG pattern)
    if (!$success) {
        // Store errors in session flash for display after redirect
        $_SESSION['_wizard_errors'] = $errors;
        redirect('/admin/college_create.php');
    }
}

// ─── Read errors from session flash ─────────────────────────
$displayErrors = $_SESSION['_wizard_errors'] ?? [];
unset($_SESSION['_wizard_errors']);

// Re-read draft after potential POST
$draft = $_SESSION['college_draft'] ?? [];
$currentStep = (int)($draft['step'] ?? 1);
$step1 = $draft['step1'] ?? [];
$step2 = $draft['step2'] ?? [];
$step3 = $draft['step3'] ?? [];
?>

<div class="dashboard-header" style="margin-bottom:var(--space-4);">
    <div class="dashboard-header-left">
        <h1>Create College</h1>
        <div class="dashboard-subtitle">4-Step Registration Wizard</div>
    </div>
    <div class="dashboard-header-right">
        <a href="<?= BASE_URL ?>/admin/colleges.php" class="btn btn-ghost btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to Colleges
        </a>
    </div>
</div>

<?php if (!empty($displayErrors)): ?>
    <div class="alert alert-error" style="margin-bottom:var(--space-4);">
        <svg viewBox="0 0 20 20" fill="currentColor" style="width:18px;height:18px;flex-shrink:0;"><path d="M10 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16zm0 1a7 7 0 1 0 0 14 7 7 0 0 0 0-14zm0 9.5a.75.75 0 1 1 0 1.5.75.75 0 0 1 0-1.5zM10 6a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-1 0v-4A.5.5 0 0 1 10 6z"/></svg>
        <div>
            <?php foreach ($displayErrors as $e): ?>
                <div><?= h($e) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php if (flash('success')): ?>
    <div class="alert alert-success" style="margin-bottom:var(--space-4);">
        <svg viewBox="0 0 20 20" fill="currentColor" style="width:18px;height:18px;flex-shrink:0;"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
        <span><?= h(flash('success')) ?></span>
    </div>
<?php endif; ?>

<!-- ── STEPPER ────────────────────────────────────────────── -->
<div class="wizard-stepper">
    <div class="ws-step <?= $currentStep >= 1 ? 'ws-completed' : '' ?> <?= $currentStep === 1 ? 'ws-active' : '' ?>">
        <div class="ws-circle"><?= $currentStep > 1 ? '&#10003;' : '1' ?></div>
        <div class="ws-label">Basic Information</div>
    </div>
    <div class="ws-connector <?= $currentStep > 1 ? 'ws-active' : '' ?>"></div>
    <div class="ws-step <?= $currentStep >= 2 ? 'ws-completed' : '' ?> <?= $currentStep === 2 ? 'ws-active' : '' ?>">
        <div class="ws-circle"><?= $currentStep > 2 ? '&#10003;' : '2' ?></div>
        <div class="ws-label">Academic Information</div>
    </div>
    <div class="ws-connector <?= $currentStep > 2 ? 'ws-active' : '' ?>"></div>
    <div class="ws-step <?= $currentStep >= 3 ? 'ws-completed' : '' ?> <?= $currentStep === 3 ? 'ws-active' : '' ?>">
        <div class="ws-circle"><?= $currentStep > 3 ? '&#10003;' : '3' ?></div>
        <div class="ws-label">Batch Creation</div>
    </div>
    <div class="ws-connector <?= $currentStep > 3 ? 'ws-active' : '' ?>"></div>
    <div class="ws-step <?= $currentStep >= 4 ? 'ws-completed' : '' ?> <?= $currentStep === 4 ? 'ws-active' : '' ?>">
        <div class="ws-circle"><?= $currentStep > 4 ? '&#10003;' : '4' ?></div>
        <div class="ws-label">Review &amp; Confirm</div>
    </div>
</div>

<!-- ── STEP CONTENT ───────────────────────────────────────── -->
<form method="POST" enctype="multipart/form-data" id="wizardForm" class="wizard-form">
    <?= csrfField() ?>
    <input type="hidden" name="step" value="<?= $currentStep ?>">
    <input type="hidden" name="wizard_action" id="wizardAction" value="next">

    <?php if ($currentStep === 1): ?>
    <!-- ═══ STEP 1: Basic Information ═══ -->
    <div class="ws-content ws-step-1">
        <div class="card-flat" style="margin-bottom:var(--space-5);">
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group" style="flex:2;">
                        <label class="form-label" for="name">College Name <span class="text-warning">*</span></label>
                        <input class="form-input" type="text" id="name" name="name" required
                               value="<?= h($step1['name'] ?? '') ?>"
                               placeholder="e.g. Indian Institute of Technology"
                               maxlength="255">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label class="form-label" for="college_code">College ID</label>
                        <input class="form-input" type="text" id="college_code" name="college_code" readonly
                               value="<?= h($step1['college_code'] ?? generateCollegeCode($pdo)) ?>"
                               style="background:var(--gray-5);color:var(--gray-60);cursor:not-allowed;">
                        <div class="form-hint">Auto-generated, unique identifier</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex:1;">
                        <label class="form-label" for="nick_name">College Nick Name <span class="text-warning">*</span></label>
                        <input class="form-input" type="text" id="nick_name" name="nick_name" required
                               value="<?= h($step1['nick_name'] ?? '') ?>"
                               placeholder="e.g. IIT Madras"
                               maxlength="255"
                               oninput="document.getElementById('nick_preview').textContent = this.value.toUpperCase().replace(/[^A-Z0-9]/g,'').substring(0,5)">
                        <div class="form-hint">Unique short name. Used for batch codes. Preview: <span id="nick_preview" style="font-weight:600;color:var(--accent);"><?= h(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', substr($step1['nick_name'] ?? '', 0, 5)))) ?></span></div>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label class="form-label" for="established_year">Established Year</label>
                        <input class="form-input" type="number" id="established_year" name="established_year"
                               value="<?= h($step1['established_year'] ?? '') ?>"
                               min="1800" max="<?= date('Y') ?>" placeholder="e.g. 2005">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex:1;">
                        <label class="form-label" for="website">Website</label>
                        <input class="form-input" type="url" id="website" name="website"
                               value="<?= h($step1['website'] ?? '') ?>"
                               placeholder="https://www.example.edu">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-input" type="email" id="email" name="email"
                               value="<?= h($step1['email'] ?? '') ?>"
                               placeholder="admin@college.edu">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label class="form-label" for="phone">Phone</label>
                        <input class="form-input" type="tel" id="phone" name="phone"
                               value="<?= h($step1['phone'] ?? '') ?>"
                               placeholder="+91-XXXXXXXXXX">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="address">Address</label>
                    <textarea class="form-textarea" id="address" name="address" rows="2"
                              placeholder="Street, building, area"><?= h($step1['address'] ?? '') ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex:1;">
                        <label class="form-label" for="country">Country</label>
                        <input class="form-input" type="text" id="country" name="country"
                               value="<?= h($step1['country'] ?? 'India') ?>"
                               placeholder="India">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label class="form-label" for="state">State</label>
                        <input class="form-input" type="text" id="state" name="state"
                               value="<?= h($step1['state'] ?? '') ?>"
                               placeholder="e.g. Karnataka">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label class="form-label" for="district">District</label>
                        <input class="form-input" type="text" id="district" name="district"
                               value="<?= h($step1['district'] ?? '') ?>"
                               placeholder="e.g. Bangalore Urban">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex:1;">
                        <label class="form-label" for="city">City</label>
                        <input class="form-input" type="text" id="city" name="city"
                               value="<?= h($step1['city'] ?? '') ?>"
                               placeholder="e.g. Bangalore">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label class="form-label" for="pincode">Pincode</label>
                        <input class="form-input" type="text" id="pincode" name="pincode"
                               value="<?= h($step1['pincode'] ?? '') ?>"
                               placeholder="e.g. 560001" maxlength="10">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label class="form-label" for="logo">Logo URL</label>
                        <input class="form-input" type="text" id="logo" name="logo"
                               value="<?= h($step1['logo'] ?? '') ?>"
                               placeholder="URL to college logo image">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Description</label>
                    <textarea class="form-textarea" id="description" name="description" rows="3"
                              placeholder="Brief description about the college"><?= h($step1['description'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="ws-actions">
            <button type="button" class="btn btn-secondary" onclick="window.location.href='<?= BASE_URL ?>/admin/colleges.php'">Cancel</button>
            <button type="button" class="btn btn-ghost" onclick="saveDraft()">Save Draft</button>
            <button type="submit" class="btn btn-primary" onclick="document.getElementById('wizardAction').value='next'">Next &rarr;</button>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($currentStep === 2): ?>
    <!-- ═══ STEP 2: Academic Information ═══ -->
    <div class="ws-content ws-step-2">
        <div class="card-flat" style="margin-bottom:var(--space-5);">
            <div class="card-header"><h3>University Details</h3></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group" style="flex:1;">
                        <label class="form-label" for="recognized_university">Recognized University</label>
                        <input class="form-input" type="text" id="recognized_university" name="recognized_university"
                               value="<?= h($step2['recognized_university'] ?? '') ?>"
                               placeholder="e.g. Visvesvaraya Technological University">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label class="form-label" for="affiliated_university">Affiliated University</label>
                        <input class="form-input" type="text" id="affiliated_university" name="affiliated_university"
                               value="<?= h($step2['affiliated_university'] ?? '') ?>"
                               placeholder="e.g. Bangalore University">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="autonomous">Autonomous Status</label>
                    <input class="form-input" type="text" id="autonomous" name="autonomous"
                           value="<?= h($step2['autonomous'] ?? '') ?>"
                           placeholder="e.g. Autonomous college under UGC Act">
                </div>
            </div>
        </div>

        <div class="card-flat" style="margin-bottom:var(--space-5);">
            <div class="card-header"><h3>Accreditation</h3></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group" style="flex:0 0 auto;">
                        <label class="form-checkbox">
                            <input type="checkbox" name="accreditation_naac" value="1" <?= !empty($step2['accreditation_naac']) ? 'checked' : '' ?> onchange="document.getElementById('naac_grade_group').style.display=this.checked?'block':'none'">
                            <span>NAAC Accredited</span>
                        </label>
                    </div>
                    <div class="form-group" id="naac_grade_group" style="flex:1;display:<?= !empty($step2['accreditation_naac']) ? 'block' : 'none' ?>;">
                        <select class="form-select" name="naac_grade">
                            <option value="">Select NAAC Grade</option>
                            <?php foreach ($NAAC_GRADES as $g): ?>
                            <option value="<?= $g ?>" <?= ($step2['naac_grade'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row" style="margin-top:var(--space-3);">
                    <label class="form-checkbox">
                        <input type="checkbox" name="accreditation_nba" value="1" <?= !empty($step2['accreditation_nba']) ? 'checked' : '' ?>>
                        <span>NBA Accredited</span>
                    </label>
                    <label class="form-checkbox">
                        <input type="checkbox" name="accreditation_aicte" value="1" <?= !empty($step2['accreditation_aicte']) ? 'checked' : '' ?>>
                        <span>AICTE Approved</span>
                    </label>
                    <label class="form-checkbox">
                        <input type="checkbox" name="accreditation_ugc" value="1" <?= !empty($step2['accreditation_ugc']) ? 'checked' : '' ?>>
                        <span>UGC Recognized</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="card-flat" style="margin-bottom:var(--space-5);">
            <div class="card-header">
                <h3>Streams / Courses <span class="text-warning">*</span></h3>
            </div>
            <div class="card-body">
                <div class="form-hint" style="margin-bottom:var(--space-3);">
                    Select all undergraduate (UGC) and postgraduate (PGC) courses offered by the college.
                </div>

                <?php
                $selectedStreams = $step2['streams'] ?? [];
                $selectedCatMap = $step2['stream_category_map'] ?? [];
                $summaryCount = count($selectedStreams);
                ?>

                <!-- Live selection summary -->
                <div id="streamSummary" class="text-sm" style="margin-bottom:var(--space-3);">
                    <strong><span id="streamCount"><?= (int)$summaryCount ?></span></strong> selected
                    (<span id="streamUgCount">0</span> UGC · <span id="streamPgCount">0</span> PGC)
                </div>

                <div class="degree-categories">
                    <?php foreach (['UGC', 'PGC'] as $cat): ?>
                    <?php $catLabel = $cat === 'UGC' ? 'Undergraduate Courses (UGC)' : 'Postgraduate Courses (PGC)'; ?>
                    <div class="degree-category degree-category-<?= strtolower($cat) ?>" data-category="<?= $cat ?>">
                        <div class="degree-category-header">
                            <strong><?= $catLabel ?></strong>
                            <span class="text-sm text-muted"><span class="degree-cat-count">0</span>/<?= count($DEGREE_OPTIONS[$cat]) ?> selected</span>
                            <button type="button" class="btn btn-sm btn-ghost select-all-btn" data-category="<?= $cat ?>">Select All <?= $cat ?></button>
                        </div>
                        <div class="degree-pills" role="group" aria-label="<?= h($catLabel) ?>">
                            <?php foreach ($DEGREE_OPTIONS[$cat] as $degree): ?>
                            <label class="degree-pill">
                                <input type="checkbox" class="degree-check-input" name="streams[]" value="<?= h($degree) ?>"
                                       data-category="<?= $cat ?>"
                                       <?= in_array($degree, $selectedStreams) ? 'checked' : '' ?>>
                                <span class="degree-check" aria-hidden="true">
                                    <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="2 6.2 4.8 9 10 3.4"></polyline></svg>
                                </span>
                                <span class="degree-pill-text"><?= h($degree) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-group" style="margin-top:var(--space-3);">
                    <label class="form-label" for="custom_stream">Custom Course(s)</label>
                    <div class="form-row" style="align-items:center;">
                        <div class="form-group" style="flex:2;margin-bottom:0;">
                            <input class="form-input" type="text" id="custom_stream" name="custom_stream"
                                   value="<?= h($draft['step2']['custom_stream_value'] ?? '') ?>"
                                   placeholder="e.g. Data Science, Artificial Intelligence (comma separated)">
                        </div>
                        <div class="form-group degree-custom-category" style="flex:0 0 auto;margin-bottom:0;">
                            <label>
                                <input type="radio" name="custom_stream_category" value="UGC" checked> UGC
                            </label>
                            <label>
                                <input type="radio" name="custom_stream_category" value="PGC"> PGC
                            </label>
                        </div>
                    </div>
                    <div class="form-hint">Add courses not listed above, choosing their level (UGC/PGC). Separate multiple with commas.</div>
                </div>
            </div>
        </div>

        <style>
        /* Degree picker styles live in assets/css/admin.css (section:
           "College Wizard — UGC/PGC Degree Picker") for theme consistency. */
        </style>

        <script>
        (function () {
            'use strict';
            var countEl  = document.getElementById('streamCount');
            var ugEl     = document.getElementById('streamUgCount');
            var pgEl     = document.getElementById('streamPgCount');
            var catCounts = { UGC: [], PGC: [] };
            document.querySelectorAll('.degree-category').forEach(function (box) {
                var cat = box.getAttribute('data-category');
                box.querySelectorAll('.degree-check-input').forEach(function (cb) {
                    cb.addEventListener('change', refreshSummary);
                    catCounts[cat].push(cb);
                });
            });
            function refreshSummary() {
                var total = 0, ug = 0, pg = 0;
                Object.keys(catCounts).forEach(function (cat) {
                    catCounts[cat].forEach(function (cb) {
                        if (cb.checked) { total++; if (cat === 'UGC') ug++; else pg++; }
                    });
                });
                if (countEl) countEl.textContent = total;
                if (ugEl) ugEl.textContent = ug;
                if (pgEl) pgEl.textContent = pg;
                document.querySelectorAll('.degree-category').forEach(function (box) {
                    var label = box.querySelector('.degree-cat-count');
                    if (label) label.textContent = box.querySelectorAll('.degree-check-input:checked').length;
                });
            }
            refreshSummary();

            // Select All / Clear toggles per category
            document.querySelectorAll('.select-all-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var cat = btn.getAttribute('data-category');
                    var boxes = catCounts[cat];
                    var allOn = boxes.every(function (cb) { return cb.checked; });
                    boxes.forEach(function (cb) { cb.checked = !allOn; });
                    btn.textContent = allOn ? 'Select All ' + cat : 'Clear ' + cat;
                    refreshSummary();
                });
            });
        })();
        </script>

        <div class="ws-actions">
            <button type="submit" class="btn btn-secondary" onclick="document.getElementById('wizardAction').value='prev'">&larr; Previous</button>
            <button type="button" class="btn btn-ghost" onclick="saveDraft()">Save Draft</button>
            <button type="submit" class="btn btn-primary" onclick="document.getElementById('wizardAction').value='next'">Next &rarr;</button>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($currentStep === 3): ?>
    <!-- ═══ STEP 3: Batch Creation ═══ -->
    <div class="ws-content ws-step-3">
        <div class="card-flat" style="margin-bottom:var(--space-5);">
            <div class="card-header">
                <h3>Create Batches</h3>
                <button type="button" class="btn btn-sm btn-ghost" onclick="addBatchRow()">+ Add Batch</button>
            </div>
            <div class="card-body">
                <div class="form-hint" style="margin-bottom:var(--space-3);">
                    Create batches for each stream. Each batch gets a unique auto-generated nick name.
                </div>

                <?php $batches = $step3['batches'] ?? []; ?>
                <?php $streamNames = $step2['streams'] ?? []; ?>
                <?php if (empty($streamNames)): ?>
                    <div class="alert alert-warning" style="margin:0;">
                        <span>No streams selected. Please go back to Step 2 and select at least one stream.</span>
                    </div>
                <?php else: ?>
                <div id="batchesContainer">
                    <?php if (empty($batches)): ?>
                    <!-- Default first row -->
                    <div class="batch-row" data-index="0">
                        <div class="form-row" style="align-items:end;">
                            <div class="form-group" style="flex:1.5;">
                                <label class="form-label">Stream</label>
                                <select class="form-select" name="batch_stream_id[]" required>
                                    <option value="">Select Stream</option>
                                    <?php foreach ($streamNames as $idx => $sn): ?>
                                    <option value="<?= $idx + 1 ?>"><?= h($sn) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label class="form-label">Joining Year</label>
                                <select class="form-select" name="batch_joining_year[]" required onchange="updateBatchPreview(this)">
                                    <option value="">Select Year</option>
                                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 5; $y++): ?>
                                    <option value="<?= $y ?>"><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label class="form-label">Joining Month</label>
                                <select class="form-select" name="batch_joining_month[]" required onchange="updateBatchPreview(this)">
                                    <option value="">Select</option>
                                    <?php foreach (['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'] as $m => $mName): ?>
                                    <option value="<?= $m + 1 ?>"><?= $mName ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="flex:0.8;">
                                <label class="form-label">Duration (yrs)</label>
                                <select class="form-select" name="batch_duration[]" required onchange="updateBatchPreview(this)">
                                    <option value="">Select</option>
                                    <?php for ($d = 1; $d <= 6; $d++): ?>
                                    <option value="<?= $d ?>"><?= $d ?> yr<?= $d > 1 ? 's' : '' ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group" style="flex:0.8;">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="batch_status[]">
                                    <option value="active">Active</option>
                                    <option value="upcoming">Upcoming</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                            <div class="form-group" style="flex:0 0 auto;">
                                <label class="form-label">&nbsp;</label>
                                <button type="button" class="btn btn-sm btn-danger" onclick="removeBatchRow(this)" style="display:none;" disabled>&times;</button>
                            </div>
                        </div>
                        <div class="batch-preview" style="font-size:var(--fs-12);color:var(--gray-50);margin-top:var(--space-1);padding-left:2px;">
                            Nick Name: <span class="batch-nick-preview" style="color:var(--accent);font-weight:600;">—</span>
                        </div>
                    </div>
                    <?php else: ?>
                    <?php foreach ($batches as $bi => $batch): ?>
                    <div class="batch-row" data-index="<?= $bi ?>">
                        <input type="hidden" name="batch_remove[]" value="" class="batch-remove-flag">
                        <div class="form-row" style="align-items:end;">
                            <div class="form-group" style="flex:1.5;">
                                <label class="form-label">Stream</label>
                                <select class="form-select" name="batch_stream_id[]" required>
                                    <?php foreach ($streamNames as $idx => $sn): ?>
                                    <option value="<?= $idx + 1 ?>" <?= ($batch['stream_id'] ?? '') == ($idx + 1) ? 'selected' : '' ?>><?= h($sn) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label class="form-label">Joining Year</label>
                                <select class="form-select" name="batch_joining_year[]" required onchange="updateBatchPreview(this)">
                                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 5; $y++): ?>
                                    <option value="<?= $y ?>" <?= ($batch['joining_year'] ?? '') == $y ? 'selected' : '' ?>><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label class="form-label">Joining Month</label>
                                <select class="form-select" name="batch_joining_month[]" required onchange="updateBatchPreview(this)">
                                    <?php foreach (['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'] as $m => $mName): ?>
                                    <option value="<?= $m + 1 ?>" <?= ($batch['joining_month'] ?? '') == ($m + 1) ? 'selected' : '' ?>><?= $mName ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="flex:0.8;">
                                <label class="form-label">Duration (yrs)</label>
                                <select class="form-select" name="batch_duration[]" required onchange="updateBatchPreview(this)">
                                    <?php for ($d = 1; $d <= 6; $d++): ?>
                                    <option value="<?= $d ?>" <?= ($batch['course_duration'] ?? '') == $d ? 'selected' : '' ?>><?= $d ?> yr<?= $d > 1 ? 's' : '' ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group" style="flex:0.8;">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="batch_status[]">
                                    <option value="active" <?= ($batch['status'] ?? '') == 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="upcoming" <?= ($batch['status'] ?? '') == 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                                    <option value="completed" <?= ($batch['status'] ?? '') == 'completed' ? 'selected' : '' ?>>Completed</option>
                                </select>
                            </div>
                            <div class="form-group" style="flex:0 0 auto;">
                                <label class="form-label">&nbsp;</label>
                                <button type="button" class="btn btn-sm btn-danger" onclick="removeBatchRow(this)">&times;</button>
                            </div>
                        </div>
                        <div class="batch-preview" style="font-size:var(--fs-12);color:var(--gray-50);margin-top:var(--space-1);padding-left:2px;">
                            Nick Name: <span class="batch-nick-preview" style="color:var(--accent);font-weight:600;"><?= h($batch['batch_nick_name']) ?></span>
                            &middot; Academic Year: <span class="batch-academic-preview"><?= h($batch['academic_year']) ?></span>
                            &middot; Ending Year: <span class="batch-ending-preview"><?= h($batch['ending_year']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="ws-actions">
            <button type="submit" class="btn btn-secondary" onclick="document.getElementById('wizardAction').value='prev'">&larr; Previous</button>
            <button type="button" class="btn btn-ghost" onclick="saveDraft()">Save Draft</button>
            <button type="submit" class="btn btn-primary" onclick="document.getElementById('wizardAction').value='next'">Next &rarr;</button>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($currentStep === 4): ?>
    <!-- ═══ STEP 4: Review & Confirm ═══ -->
    <div class="ws-content ws-step-4">
        <div class="card-flat" style="margin-bottom:var(--space-5);">
            <div class="card-header">
                <h3>College Information</h3>
                <button type="submit" class="btn btn-sm btn-ghost" onclick="document.getElementById('wizardAction').value='prev_step1'" style="display:none;" id="editStep1Btn">Edit</button>
                <a href="#" class="btn btn-sm btn-ghost" onclick="goToStep(1);return false;">Edit</a>
            </div>
            <div class="card-body">
                <table class="data-table" style="margin:0;">
                    <tbody>
                        <tr><td style="font-weight:600;width:140px;">College Code</td><td><?= h($step1['college_code'] ?? '—') ?></td></tr>
                        <tr><td style="font-weight:600;">College Name</td><td><?= h($step1['name'] ?? '—') ?></td></tr>
                        <tr><td style="font-weight:600;">Nick Name</td><td><?= h($step1['nick_name'] ?? '—') ?></td></tr>
                        <tr><td style="font-weight:600;">Established</td><td><?= h($step1['established_year'] ?? '—') ?></td></tr>
                        <tr><td style="font-weight:600;">Website</td><td><?= h($step1['website'] ?? '—') ?></td></tr>
                        <tr><td style="font-weight:600;">Email</td><td><?= h($step1['email'] ?? '—') ?></td></tr>
                        <tr><td style="font-weight:600;">Phone</td><td><?= h($step1['phone'] ?? '—') ?></td></tr>
                        <tr><td style="font-weight:600;">Address</td><td><?= nl2br(h($step1['address'] ?? '—')) ?></td></tr>
                        <tr><td style="font-weight:600;">City</td><td><?= h($step1['city'] ?? '—') ?></td></tr>
                        <tr><td style="font-weight:600;">State</td><td><?= h($step1['state'] ?? '—') ?></td></tr>
                        <tr><td style="font-weight:600;">Pincode</td><td><?= h($step1['pincode'] ?? '—') ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-flat" style="margin-bottom:var(--space-5);">
            <div class="card-header">
                <h3>Academic Information</h3>
                <a href="#" class="btn btn-sm btn-ghost" onclick="goToStep(2);return false;">Edit</a>
            </div>
            <div class="card-body">
                <table class="data-table" style="margin:0;">
                    <tbody>
                        <tr><td style="font-weight:600;width:140px;">University</td><td><?= h($step2['recognized_university'] ?? '—') ?></td></tr>
                        <tr><td style="font-weight:600;">Affiliated</td><td><?= h($step2['affiliated_university'] ?? '—') ?></td></tr>
                        <tr><td style="font-weight:600;">Autonomous</td><td><?= h($step2['autonomous'] ?? '—') ?></td></tr>
                        <tr><td style="font-weight:600;">NAAC</td><td><?= !empty($step2['accreditation_naac']) ? 'Yes' . (!empty($step2['naac_grade']) ? ' (Grade ' . h($step2['naac_grade']) . ')' : '') : 'No' ?></td></tr>
                        <tr><td style="font-weight:600;">NBA</td><td><?= !empty($step2['accreditation_nba']) ? 'Yes' : 'No' ?></td></tr>
                        <tr><td style="font-weight:600;">AICTE</td><td><?= !empty($step2['accreditation_aicte']) ? 'Yes' : 'No' ?></td></tr>
                        <tr><td style="font-weight:600;">UGC</td><td><?= !empty($step2['accreditation_ugc']) ? 'Yes' : 'No' ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-flat" style="margin-bottom:var(--space-5);">
            <div class="card-header">
                <h3>Streams (<?= count($step2['streams'] ?? []) ?>)</h3>
                <a href="#" class="btn btn-sm btn-ghost" onclick="goToStep(2);return false;">Edit</a>
            </div>
            <div class="card-body">
                <?php $streams = $step2['streams'] ?? []; ?>
                <?php $streamCatMap = $step2['stream_category_map'] ?? []; ?>
                <?php if (empty($streams)): ?>
                    <div style="color:var(--gray-50);">No streams selected.</div>
                <?php else: ?>
                    <?php foreach (['UGC', 'PGC'] as $cat): ?>
                    <?php $catStreams = array_values(array_filter($streams, function ($s) use ($cat, $streamCatMap) {
                            return ($streamCatMap[$s] ?? 'UGC') === $cat;
                        })); ?>
                    <?php if (!empty($catStreams)): ?>
                    <div style="margin-bottom:var(--space-3);">
                        <div style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--accent);margin-bottom:var(--space-2);">
                            <?= $cat === 'UGC' ? 'Undergraduate (UGC)' : 'Postgraduate (PGC)' ?> — <?= count($catStreams) ?>
                        </div>
                        <div style="display:flex;flex-wrap:wrap;gap:var(--space-2);">
                            <?php foreach ($catStreams as $s): ?>
                            <span class="badge <?= $cat === 'UGC' ? 'badge-active' : 'badge-pending' ?>"><?= h($s) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-flat" style="margin-bottom:var(--space-5);">
            <div class="card-header">
                <h3>Batches (<?= count($step3['batches'] ?? []) ?>)</h3>
                <a href="#" class="btn btn-sm btn-ghost" onclick="goToStep(3);return false;">Edit</a>
            </div>
            <div class="card-body">
                <?php $batches = $step3['batches'] ?? []; ?>
                <?php if (empty($batches)): ?>
                    <div style="color:var(--gray-50);">No batches created.</div>
                <?php else: ?>
                    <table class="data-table" style="margin:0;">
                        <thead>
                            <tr>
                                <th>Batch Nick Name</th>
                                <th>Stream</th>
                                <th>Academic Year</th>
                                <th>Duration</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($batches as $batch): ?>
                            <tr>
                                <td><strong><?= h($batch['batch_nick_name']) ?></strong></td>
                                <td><?= h($batch['stream_name']) ?></td>
                                <td class="text-sm"><?= h($batch['academic_year']) ?></td>
                                <td class="text-sm"><?= (int)$batch['course_duration'] ?> yrs</td>
                                <td>
                                    <span class="badge badge-<?= $batch['status'] === 'active' ? 'active' : ($batch['status'] === 'upcoming' ? 'pending' : 'success') ?>">
                                        <?= ucfirst(h($batch['status'])) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="ws-actions">
            <button type="submit" class="btn btn-secondary" onclick="document.getElementById('wizardAction').value='prev'">&larr; Previous</button>
            <button type="button" class="btn btn-ghost" onclick="saveDraft()">Save Draft</button>
            <button type="submit" class="btn btn-success" onclick="document.getElementById('wizardAction').value='create'">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                Create College
            </button>
        </div>
    </div>
    <?php endif; ?>

</form>

<script>
// ─── Wizard Navigation ─────────────────────────────────────
function saveDraft() {
    document.getElementById('wizardAction').value = 'save_draft';
    document.getElementById('wizardForm').submit();
}

function goToStep(step) {
    document.getElementById('wizardAction').value = 'goto_' + step;
    document.getElementById('wizardForm').querySelector('input[name="step"]').value = step;
    document.getElementById('wizardForm').submit();
}

// ─── Batch Management ──────────────────────────────────────
let batchRowIndex = <?= max(count($step3['batches'] ?? []), 1) ?>;

function addBatchRow() {
    const container = document.getElementById('batchesContainer');
    if (!container) return;
    const idx = batchRowIndex++;
    const streamOptions = document.querySelector('.batch-row:first-child select[name="batch_stream_id[]"]')?.innerHTML || '<option value="">No streams</option>';
    const yearOptions = document.querySelector('.batch-row:first-child select[name="batch_joining_year[]"]')?.innerHTML || '';
    const monthOptions = document.querySelector('.batch-row:first-child select[name="batch_joining_month[]"]')?.innerHTML || '';
    const durationOptions = document.querySelector('.batch-row:first-child select[name="batch_duration[]"]')?.innerHTML || '';

    const div = document.createElement('div');
    div.className = 'batch-row';
    div.dataset.index = idx;
    div.innerHTML = `
        <input type="hidden" name="batch_remove[]" value="" class="batch-remove-flag">
        <div class="form-row" style="align-items:end;">
            <div class="form-group" style="flex:1.5;">
                <label class="form-label">Stream</label>
                <select class="form-select" name="batch_stream_id[]" required>${streamOptions}</select>
            </div>
            <div class="form-group" style="flex:1;">
                <label class="form-label">Joining Year</label>
                <select class="form-select" name="batch_joining_year[]" required onchange="updateBatchPreview(this)">${yearOptions}</select>
            </div>
            <div class="form-group" style="flex:1;">
                <label class="form-label">Joining Month</label>
                <select class="form-select" name="batch_joining_month[]" required onchange="updateBatchPreview(this)">${monthOptions}</select>
            </div>
            <div class="form-group" style="flex:0.8;">
                <label class="form-label">Duration (yrs)</label>
                <select class="form-select" name="batch_duration[]" required onchange="updateBatchPreview(this)">${durationOptions}</select>
            </div>
            <div class="form-group" style="flex:0.8;">
                <label class="form-label">Status</label>
                <select class="form-select" name="batch_status[]">
                    <option value="active">Active</option>
                    <option value="upcoming">Upcoming</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
            <div class="form-group" style="flex:0 0 auto;">
                <label class="form-label">&nbsp;</label>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeBatchRow(this)">&times;</button>
            </div>
        </div>
        <div class="batch-preview" style="font-size:var(--fs-12);color:var(--gray-50);margin-top:var(--space-1);padding-left:2px;">
            Nick Name: <span class="batch-nick-preview" style="color:var(--accent);font-weight:600;">—</span>
        </div>
    `;
    container.appendChild(div);
}

function removeBatchRow(btn) {
    const row = btn.closest('.batch-row');
    if (!row) return;
    const flag = row.querySelector('.batch-remove-flag');
    if (flag) flag.value = '1';
    row.style.display = 'none';
}

function updateBatchPreview(el) {
    const row = el.closest('.batch-row');
    if (!row) return;
    const streamSel = row.querySelector('select[name="batch_stream_id[]"]');
    const yearSel = row.querySelector('select[name="batch_joining_year[]"]');
    const monthSel = row.querySelector('select[name="batch_joining_month[]"]');
    const durationSel = row.querySelector('select[name="batch_duration[]"]');
    const nickPreview = row.querySelector('.batch-nick-preview');
    const academicPreview = row.querySelector('.batch-academic-preview');
    const endingPreview = row.querySelector('.batch-ending-preview');

    const nickName = '<?= h(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', substr($step1['nick_name'] ?? 'COL', 0, 5)))) ?>';
    let streamName = '';
    if (streamSel) {
        const selOpt = streamSel.options[streamSel.selectedIndex];
        if (selOpt) streamName = selOpt.text.toUpperCase().replace(/[^A-Z0-9]/g, '').substring(0, 4);
    }
    const year = yearSel ? yearSel.value : '';
    const month = monthSel ? monthSel.value.padStart(2, '0') : '';
    const duration = durationSel ? parseInt(durationSel.value) : 0;

    if (nickPreview && year && month) {
        nickPreview.textContent = nickName + '_' + (streamName || 'STR') + '_' + year + month;
    }
    if (academicPreview && year && duration) {
        academicPreview.textContent = year + '-' + (parseInt(year) + duration);
    }
    if (endingPreview && year && duration) {
        endingPreview.textContent = parseInt(year) + duration;
    }
}

// ─── Keyboard shortcut: Enter on batch fields shouldn't submit ──
document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && e.target.closest('.batch-row')) {
        // Don't submit, just move to next field
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>
