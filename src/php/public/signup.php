<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();

// If already logged in, redirect
if (isStudent()) { redirect('/student/dashboard.php'); }

$error = '';
$success = '';
$formData = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $error = 'Invalid form submission. Please refresh and try again.';
    } else {
        $required = ['name','phone','email','gender','college_id','course_id','batch_id','branch','roll_number','year_of_joining','password','confirm_password'];
        $missing = [];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            $labels = [
                'name' => 'Full Name', 'phone' => 'Phone Number', 'email' => 'Email',
                'gender' => 'Gender', 'college_id' => 'College', 'course_id' => 'Course',
                'batch_id' => 'Batch', 'branch' => 'Branch', 'roll_number' => 'Roll Number',
                'year_of_joining' => 'Year of Joining', 'password' => 'Password',
                'confirm_password' => 'Confirm Password',
            ];
            $missingLabels = array_map(fn($f) => $labels[$f] ?? $f, $missing);
            $error = 'Missing: ' . implode(', ', $missingLabels) . '.';
        } elseif ($_POST['password'] !== $_POST['confirm_password']) {
            $error = 'Passwords do not match.';
        } elseif (strlen($_POST['password']) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $result = studentRegister([
                'college_id'     => (int)$_POST['college_id'],
                'course_id'      => (int)$_POST['course_id'],
                'batch_id'       => (int)$_POST['batch_id'],
                'section'        => trim($_POST['section'] ?? '') ?: null,
                'name'           => trim($_POST['name']),
                'phone'          => trim($_POST['phone']),
                'email'          => trim($_POST['email']),
                'gender'         => $_POST['gender'],
                'branch'         => trim($_POST['branch']),
                'roll_number'    => trim($_POST['roll_number']),
                'year_of_joining'=> (int)$_POST['year_of_joining'],
                'password'       => $_POST['password'],
            ]);

            if ($result['success']) {
                // Redirect to OTP verification
                $query = http_build_query([
                    'student_id' => $result['student_id'],
                    'email'      => trim($_POST['email']),
                    'otp_dev'    => $result['otp_dev'] ?? '',
                ]);
                header('Location: ' . BASE_URL . '/verify-otp.php?' . $query);
                exit;
            } else {
                $error = $result['error'];
            }
        }
    }
}

// Fetch colleges for dropdown (gracefully handle missing DB)
$colleges = [];
try {
    $pdo = getDB();
    $colleges = $pdo->query("SELECT id, name FROM colleges WHERE status = 'active' ORDER BY name")->fetchAll();
} catch (Exception $e) {
    // DB not available — page still renders with empty dropdown
    $colleges = [];
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Test Platform</title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/student.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="auth-page auth-page-top">

    <!-- Hero Section (hidden mobile, visible tablet+) -->
    <div class="auth-hero">
        <div class="hero-logo">T</div>
        <div class="hero-text">
            <strong>Create Your Account</strong>
            <span>Join the platform to access tests, track your performance, and excel.</span>
        </div>
        <div class="hero-features">
            <div class="hero-feature">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                Free registration
            </div>
            <div class="hero-feature">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                Instant test access
            </div>
            <div class="hero-feature">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                Detailed progress tracking
            </div>
        </div>
    </div>

    <!-- Auth Card -->
    <div class="auth-card auth-card-wide">
        <div class="logo-mark">T</div>
        <h1>Create Account</h1>
        <p class="subtitle">Register to access your tests</p>

        <?php if ($error): ?>
            <div class="auth-alert error">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16zm0 1a7 7 0 1 0 0 14 7 7 0 0 0 0-14zm0 9.5a.75.75 0 1 1 0 1.5.75.75 0 0 1 0-1.5zM10 6a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-1 0v-4A.5.5 0 0 1 10 6z"/></svg>
                <span><?= h($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="auth-alert success">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                <span><?= h($success) ?><br><a href="login.php" class="text-accent" style="text-decoration:underline;margin-top:4px;display:inline-block;">Sign in</a></span>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" id="signupForm">
            <?= csrfField() ?>

            <!-- College / Course / Batch -->
            <div class="form-row">
                <div class="form-group">
                    <label for="college_id">College *</label>
                    <select class="form-select" id="college_id" name="college_id" required>
                        <option value="">Select College</option>
                        <?php foreach ($colleges as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($formData['college_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                <?= h($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($colleges)): ?>
                        <div class="form-error">No colleges available. Contact admin.</div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="course_id">Course *</label>
                    <select class="form-select" id="course_id" name="course_id" required disabled>
                        <option value="">Select College first</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="batch_id">Batch *</label>
                    <select class="form-select" id="batch_id" name="batch_id" required disabled>
                        <option value="">Select Course first</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="section">Section / Division</label>
                    <select class="form-select" id="section" name="section" disabled>
                        <option value="">Select Batch first</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="year_of_joining">Year of Joining *</label>
                    <select class="form-select" id="year_of_joining" name="year_of_joining" required>
                        <option value="">Select Year</option>
                        <?php for ($y = date('Y'); $y >= 2018; $y--): ?>
                            <option value="<?= $y ?>" <?= ($formData['year_of_joining'] ?? '') == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <!-- Personal Info -->
            <div class="form-row">
                <div class="form-group">
                    <label for="name">Full Name *</label>
                    <input class="form-input" type="text" id="name" name="name"
                           value="<?= h($formData['name'] ?? '') ?>" placeholder="John Doe" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number *</label>
                    <input class="form-input" type="tel" id="phone" name="phone"
                           value="<?= h($formData['phone'] ?? '') ?>" placeholder="+91 9876543210" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input class="form-input" type="email" id="email" name="email"
                           value="<?= h($formData['email'] ?? '') ?>" placeholder="your@email.com" required>
                </div>
                <div class="form-group">
                    <label for="gender">Gender *</label>
                    <select class="form-select" id="gender" name="gender" required>
                        <option value="">Select</option>
                        <option value="male" <?= ($formData['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= ($formData['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                        <option value="other" <?= ($formData['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="branch">Branch *</label>
                    <input class="form-input" type="text" id="branch" name="branch"
                           value="<?= h($formData['branch'] ?? '') ?>" placeholder="Computer Science" required>
                </div>
                <div class="form-group">
                    <label for="roll_number">Roll Number *</label>
                    <input class="form-input" type="text" id="roll_number" name="roll_number"
                           value="<?= h($formData['roll_number'] ?? '') ?>" placeholder="CS2024001" required>
                </div>
            </div>

            <!-- Password -->
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input class="form-input" type="password" id="password" name="password"
                           placeholder="Min 6 characters" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password *</label>
                    <input class="form-input" type="password" id="confirm_password" name="confirm_password"
                           placeholder="Re-enter password" required minlength="6">
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-full mt-2">
                Create Account
            </button>
        </form>

        <div class="auth-footer">
            Already have an account? <a href="login.php">Sign in</a>
        </div>
        <?php endif; ?>
    </div>

    <script>
    // API base URL: works on both dev server (router rewrites /api/) and XAMPP
    var API_BASE = '<?= BASE_URL ?>/api';

    document.addEventListener('DOMContentLoaded', function() {
        const collegeSelect = document.getElementById('college_id');
        const courseSelect = document.getElementById('course_id');
        const batchSelect = document.getElementById('batch_id');
        const sectionSelect = document.getElementById('section');
        // Preserve selections if form was submitted
        const selectedCourse = '<?= h($formData['course_id'] ?? '') ?>';
        const selectedBatch = '<?= h($formData['batch_id'] ?? '') ?>';
        const selectedSection = '<?= h($formData['section'] ?? '') ?>';

        // Load courses when college changes
        collegeSelect.addEventListener('change', function() {
            const collegeId = this.value;
            courseSelect.innerHTML = '<option value="">Loading...</option>';
            courseSelect.disabled = true;
            batchSelect.innerHTML = '<option value="">Select Course first</option>';
            batchSelect.disabled = true;
            sectionSelect.innerHTML = '<option value="">Select Batch first</option>';
            sectionSelect.disabled = true;

            if (!collegeId) {
                courseSelect.innerHTML = '<option value="">Select College first</option>';
                return;
            }

            fetch(API_BASE + '/get_courses.php?college_id=' + collegeId + '&active=1')
                .then(r => r.json())
                .then(data => {
                    if (data.error) {
                        courseSelect.innerHTML = '<option value="">' + data.error + '</option>';
                        return;
                    }
                    if (data.length === 0) {
                        courseSelect.innerHTML = '<option value="">No courses available for this college</option>';
                        courseSelect.disabled = true;
                    } else {
                        courseSelect.innerHTML = '<option value="">Select Course</option>';
                        data.forEach(c => {
                            const sel = c.id == selectedCourse ? 'selected' : '';
                            courseSelect.innerHTML += '<option value="' + c.id + '" ' + sel + '>' + c.name + '</option>';
                        });
                        courseSelect.disabled = false;
                        if (selectedCourse) courseSelect.dispatchEvent(new Event('change'));
                    }
                })
                .catch(() => {
                    courseSelect.innerHTML = '<option value="">Error loading courses</option>';
                });
        });

        // Load batches when course changes
        courseSelect.addEventListener('change', function() {
            const courseId = this.value;
            batchSelect.innerHTML = '<option value="">Loading...</option>';
            batchSelect.disabled = true;
            sectionSelect.innerHTML = '<option value="">Select Batch first</option>';
            sectionSelect.disabled = true;

            if (!courseId) {
                batchSelect.innerHTML = '<option value="">Select Course first</option>';
                return;
            }

            fetch(API_BASE + '/get_batches.php?course_id=' + courseId + '&active=1')
                .then(r => r.json())
                .then(data => {
                    if (data.error) {
                        batchSelect.innerHTML = '<option value="">' + data.error + '</option>';
                        return;
                    }
                    if (data.length === 0) {
                        batchSelect.innerHTML = '<option value="">No batches available for this course</option>';
                        batchSelect.disabled = true;
                    } else {
                        batchSelect.innerHTML = '<option value="">Select Batch</option>';
                        data.forEach(b => {
                            const sel = b.id == selectedBatch ? 'selected' : '';
                            batchSelect.innerHTML += '<option value="' + b.id + '" ' + sel + '>' + (b.display_name || b.name) + '</option>';
                        });
                        batchSelect.disabled = false;
                        // Trigger section load if batch was pre-selected
                        if (selectedBatch) batchSelect.dispatchEvent(new Event('change'));
                    }
                })
                .catch(() => {
                    batchSelect.innerHTML = '<option value="">Error loading batches</option>';
                });
        });

        // Load sections when batch changes
        batchSelect.addEventListener('change', function() {
            const batchId = this.value;
            sectionSelect.innerHTML = '<option value="">Loading...</option>';
            sectionSelect.disabled = true;

            if (!batchId) {
                sectionSelect.innerHTML = '<option value="">Select Batch first</option>';
                return;
            }

            // Find the selected batch to get its section
            fetch(API_BASE + '/get_batches.php?course_id=' + courseSelect.value + '&active=1')
                .then(r => r.json())
                .then(batches => {
                    const batch = batches.find(b => b.id == batchId);
                    if (batch && batch.section) {
                        // This batch has a section — show it
                        sectionSelect.innerHTML = '<option value="">Select Section</option>';
                        sectionSelect.innerHTML += '<option value="' + batch.section + '" selected>' + batch.section + '</option>';
                        sectionSelect.disabled = true; // Auto-selected, single section per batch
                    } else if (batch) {
                        // No section — check if other batches for this course have sections
                        const courseBatches = batches.filter(b => b.section);
                        if (courseBatches.length > 0) {
                            const sections = [...new Set(courseBatches.map(b => b.section))].sort();
                            sectionSelect.innerHTML = '<option value="">Select Section</option>';
                            sections.forEach(s => {
                                const sel = s == selectedSection ? 'selected' : '';
                                sectionSelect.innerHTML += '<option value="' + s + '" ' + sel + '>' + s + '</option>';
                            });
                            sectionSelect.disabled = false;
                        } else {
                            sectionSelect.innerHTML = '<option value="">No sections</option>';
                            sectionSelect.disabled = true;
                        }
                    }
                })
                .catch(() => {
                    sectionSelect.innerHTML = '<option value="">No sections</option>';
                    sectionSelect.disabled = true;
                });
        });

        // Trigger initial load if college was pre-selected (after form error)
        if (collegeSelect.value) {
            collegeSelect.dispatchEvent(new Event('change'));
        }
    });
    </script>
</body>
</html>
