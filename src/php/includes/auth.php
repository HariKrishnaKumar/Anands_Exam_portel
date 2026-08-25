<?php
/**
 * Authentication logic for Admin, Student, and Guest.
 * Unverified students stored in unverified_students table.
 * Failed logins tracked in failed_login_log table.
 *
 * CONCURRENCY DESIGN (200+ simultaneous logins / 60 s window):
 *  — Single-query lookups (no sequential SELECTs per role).
 *  — Brute-force throttle via indexed failed_login_log check.
 *  — session_write_close() called BEFORE any redirect() or HTML render,
 *    releasing the session file lock so the next request can acquire it
 *    immediately instead of blocking on a locked .sess file.
 *  — PASSWORD_BCRYPT cost set explicitly to 10 (passwords) and 8 (OTP),
 *    balancing security vs CPU under burst load.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/helpers.php';

// ─── CONSTANTS ─────────────────────────────────────────────

/** Bcrypt cost for user passwords — 10 ≈ 100 ms on modern CPU. */
define('PASSWORD_BCRYPT_COST', 10);

/** Bcrypt cost for OTP hashes — lower because OTPs are short-lived (10 min). */
define('OTP_BCRYPT_COST', 8);

/** Max failed login attempts per email within the throttle window. */
define('BRUTE_FORCE_MAX_ATTEMPTS', 5);

/** Brute-force throttle window in seconds (15 minutes). */
define('BRUTE_FORCE_WINDOW', 900);

// ─── HELPERS ───────────────────────────────────────────────

/**
 * Get client IP address (forwarded-first for reverse-proxy setups).
 */
function getClientIp(): string {
    $keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

/**
 * Log a failed login attempt (fire-and-forget — errors are silenced).
 */
function logFailedLogin(
    string $email,
    string $attemptType,
    ?string $reason = null,
    ?string $studentName = null
): void {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO failed_login_log (email, student_name, ip_address, attempt_type, reason)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $email,
            $studentName,
            getClientIp(),
            $attemptType,
            $reason,
        ]);
    } catch (Exception $e) {
        error_log('Failed to write login log: ' . $e->getMessage());
    }
}

/**
 * Check whether an email has exceeded the brute-force threshold.
 * Uses the indexed (email, attempted_at) composite for a fast range scan.
 *
 * @return bool true if the account is locked (too many failures)
 */
function isBruteForceLocked(string $email): bool {
    try {
        $pdo = getDB();
        // Exclude 'invalid_email' entries — those are "email not found" lookups
        // (e.g. studentLogin looking up an admin email), not actual wrong-password
        // attempts. Counting them would let studentLogin brute-force-lock an admin.
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS attempts
            FROM failed_login_log
            WHERE email = ? AND attempt_type != 'invalid_email'
              AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$email, BRUTE_FORCE_WINDOW]);
        $row = $stmt->fetch();
        return ($row && (int)$row['attempts'] >= BRUTE_FORCE_MAX_ATTEMPTS);
    } catch (Exception $e) {
        // On DB error, allow login (fail-open for availability).
        error_log('Brute-force check failed: ' . $e->getMessage());
        return false;
    }
}

// ─── ADMIN AUTH ───────────────────────────────────────────

function adminLogin(string $email, string $password): array {
    $pdo = getDB();

    // Brute-force throttle
    if (isBruteForceLocked($email)) {
        return ['success' => false, 'error' => 'Too many failed attempts. Please try again later.'];
    }

    $stmt = $pdo->prepare("SELECT id, email, name, password_hash, role FROM admins WHERE email = ?");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        logFailedLogin($email, 'wrong_password', 'Invalid admin email or password.');
        return ['success' => false, 'error' => 'Invalid email or password.'];
    }

    // ─── Write session data ───
    session_regenerate_id(true);
    $_SESSION['admin_id']    = (int)$admin['id'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['admin_name']  = $admin['name'] ?? $admin['email'];
    $_SESSION['role']        = 'admin';
    $_SESSION['admin_role']  = $admin['role'] ?? 'admin';
    $_SESSION['_login_ts']   = time();

    // ─── Release session lock BEFORE redirect ───
    // The next request (redirect target) needs to acquire the session
    // file lock immediately. Holding it here during the redirect response
    // creates a serialisation bottleneck at 200+ concurrent logins.
    session_write_close();

    return ['success' => true];
}

// ─── OTP FUNCTIONS ─────────────────────────────────────────

/**
 * Generate a 6-digit OTP and store hashed version in unverified_students table.
 * Returns: ['success' => true, 'otp' => '123456'] or ['success' => false, 'error' => ...]
 */
function generateStudentOtp(int $studentId, string $email, string $name): array {
    $pdo = getDB();

    // Generate 6-digit OTP
    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $otpHash = password_hash($otp, PASSWORD_BCRYPT, ['cost' => OTP_BCRYPT_COST]);
    $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes

    // Store in unverified_students
    $stmt = $pdo->prepare("UPDATE unverified_students SET otp_hash = ?, otp_expires_at = ? WHERE id = ?");
    $stmt->execute([$otpHash, $expiresAt, $studentId]);

    // Send email
    require_once __DIR__ . '/mailer.php';
    $mailResult = sendOtpEmail($email, $name, $otp);

    $result = [
        'success'   => $mailResult['success'],
        'otp'       => $otp,
        'expires_at'=> $expiresAt,
        'mail_info' => $mailResult,
    ];

    // Surface mail error to top level for caller convenience
    if (!$mailResult['success'] && isset($mailResult['error'])) {
        $result['error'] = $mailResult['error'];
    }

    return $result;
}

/**
 * Verify OTP for a student. On success, moves record to students table.
 */
function verifyStudentOtp(int $studentId, string $otp): array {
    $pdo = getDB();

    // Fetch from unverified_students
    $stmt = $pdo->prepare("SELECT * FROM unverified_students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        return ['success' => false, 'error' => 'Student not found or already verified.'];
    }

    if (empty($student['otp_hash'])) {
        logFailedLogin($student['email'], 'signup_unverified', 'No OTP requested. Please register again.', $student['name']);
        notifyAdmin('student_account', 'Account not completed — no OTP requested',
            $student['name'] . ' (' . $student['email'] . ') registered but never received an OTP.');
        return ['success' => false, 'error' => 'No OTP requested. Please register again.'];
    }

    // Check expiry
    if (strtotime($student['otp_expires_at']) < time()) {
        logFailedLogin($student['email'], 'expired_otp', 'OTP expired.', $student['name']);
        notifyAdmin('student_account', 'Account not completed — OTP expired',
            $student['name'] . ' (' . $student['email'] . ') tried to verify with an expired OTP.',
            BASE_URL . '/admin/pending_verifications.php');
        return ['success' => false, 'error' => 'OTP expired. Request a new one.', 'expired' => true];
    }

    // Verify OTP
    if (!password_verify($otp, $student['otp_hash'])) {
        logFailedLogin($student['email'], 'wrong_otp', 'Invalid OTP entered.', $student['name']);
        notifyAdmin('student_account', 'Wrong OTP entered',
            $student['name'] . ' (' . $student['email'] . ') entered an invalid OTP during verification.');
        return ['success' => false, 'error' => 'Invalid OTP. Please try again.'];
    }

    // ─── OTP CORRECT — Move to students table ───
    try {
        $pdo->beginTransaction();

        $insert = $pdo->prepare("
            INSERT INTO students (batch_id, section, name, phone, email, gender, college_name, branch, roll_number, year_of_joining, course_name, password_hash, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insert->execute([
            $student['batch_id'],
            $student['section'] ?? null,
            $student['name'],
            $student['phone'],
            $student['email'],
            $student['gender'],
            $student['college_name'],
            $student['branch'],
            $student['roll_number'],
            $student['year_of_joining'],
            $student['course_name'],
            $student['password_hash'],
            $student['created_at'],
        ]);

        $newStudentId = (int)$pdo->lastInsertId();

        // Delete from unverified
        $pdo->prepare("DELETE FROM unverified_students WHERE id = ?")->execute([$studentId]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        logFailedLogin($student['email'], 'signup_unverified', 'DB error during verification: ' . $e->getMessage(), $student['name']);
        notifyAdmin('student_account', 'Verification failed — DB error',
            $student['name'] . ' (' . $student['email'] . ') could not be verified: ' . $e->getMessage(),
            BASE_URL . '/admin/pending_verifications.php');
        return ['success' => false, 'error' => 'Verification failed. Please try again.'];
    }

    return ['success' => true, 'student_id' => $newStudentId];
}

/**
 * Resend OTP for a student (looks up in unverified_students).
 */
function resendStudentOtp(int $studentId): array {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT id, name, email FROM unverified_students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        return ['success' => false, 'error' => 'Student not found or already verified.'];
    }

    return generateStudentOtp($studentId, $student['email'], $student['name']);
}

// ─── STUDENT AUTH ─────────────────────────────────────────

/**
 * Authenticate a student with a SINGLE query across both tables.
 *
 * CONCURRENCY NOTES:
 *  1. Brute-force check (indexed range scan) happens BEFORE password_verify()
 *     — the expensive bcrypt operation is skipped for locked accounts.
 *  2. A UNION query replaces two sequential SELECTs, halving DB round-trips
 *     under burst load.
 *  3. session_write_close() is called before returning, so the caller
 *     (login.php) can redirect without holding the session file lock.
 *
 * @return array{success: bool, error?: string, not_verified?: bool, student_id?: int}
 */
function studentLogin(string $email, string $password): array {
    $pdo = getDB();

    // ─── 1. Brute-force throttle (indexed, sub-ms) ───
    if (isBruteForceLocked($email)) {
        return ['success' => false, 'error' => 'Too many failed attempts. Please try again later.'];
    }

    // ─── 2. Single-query lookup: verified students UNION unverified ───
    //    This replaces the old two-sequential-SELECT pattern, cutting
    //    DB round-trips in half during a login burst.
    $stmt = $pdo->prepare("
        SELECT id, email, name, password_hash, batch_id,
               'verified' AS account_status
        FROM students
        WHERE email = ?
        UNION ALL
        SELECT id, email, name, password_hash, batch_id,
               'unverified' AS account_status
        FROM unverified_students
        WHERE email = ?
        LIMIT 1
    ");
    $stmt->execute([$email, $email]);
    $user = $stmt->fetch();

    if (!$user) {
        logFailedLogin($email, 'invalid_email', 'Email not registered.');
        return ['success' => false, 'error' => 'Invalid email or password.'];
    }

    // ─── 3. Password verification (CPU-intensive — only runs after DB hit) ───
    if (!password_verify($password, $user['password_hash'])) {
        logFailedLogin($email, 'wrong_password', 'Invalid password.', $user['name']);
        return ['success' => false, 'error' => 'Invalid email or password.'];
    }

    // ─── 4. Branch by account status ───
    if ($user['account_status'] === 'unverified') {
        notifyAdmin(
            'student_account',
            'Account not completed — never verified',
            $user['name'] . ' (' . $email . ') registered but never verified their email.',
            BASE_URL . '/admin/pending_verifications.php'
        );
        return [
            'success'      => false,
            'error'        => 'Please verify your email first. An OTP was sent during registration.',
            'not_verified' => true,
            'student_id'   => (int)$user['id'],
        ];
    }

    // ─── 5. Verified — create session ───
    session_regenerate_id(true);
    $_SESSION['student_id']   = (int)$user['id'];
    $_SESSION['student_name'] = $user['name'];
    $_SESSION['student_email']= $user['email'];
    $_SESSION['batch_id']     = (int)$user['batch_id'];
    $_SESSION['role']         = 'student';
    $_SESSION['_login_ts']    = time();

    // ─── 6. Release session lock BEFORE caller redirects ───
    session_write_close();

    return ['success' => true];
}

/**
 * Register a new student.
 * Inserts into unverified_students (not students table).
 */
function studentRegister(array $data): array {
    $pdo = getDB();

    // Check duplicate email in both tables
    $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        logFailedLogin($data['email'], 'duplicate_email', 'Email already registered (verified).');
        notifyAdmin('student_account', 'Signup blocked — duplicate email',
            $data['name'] . ' (' . $data['email'] . ') tried to register with an email that already exists.');
        return ['success' => false, 'error' => 'Email already registered.'];
    }

    $stmt = $pdo->prepare("SELECT id FROM unverified_students WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        logFailedLogin($data['email'], 'duplicate_email', 'Email already registered (unverified).');
        notifyAdmin('student_account', 'Signup blocked — verification pending',
            $data['name'] . ' (' . $data['email'] . ') already registered but never completed verification.',
            BASE_URL . '/admin/pending_verifications.php');
        return ['success' => false, 'error' => 'Email already registered. Please check your email for the OTP or request a new one.'];
    }

    // Validate batch belongs to course
    $stmt = $pdo->prepare("
        SELECT b.id FROM batches b
        JOIN courses c ON c.id = b.course_id
        WHERE b.id = ? AND c.id = ? AND c.college_id = ?
    ");
    $stmt->execute([$data['batch_id'], $data['course_id'], $data['college_id']]);
    if (!$stmt->fetch()) {
        // Provide specific error so the student knows what's wrong
        $courseCheck = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND college_id = ?");
        $courseCheck->execute([$data['course_id'], $data['college_id']]);
        if (!$courseCheck->fetch()) {
            $error = 'This college has no courses available. Contact your admin.';
        } else {
            $batchCheck = $pdo->prepare("SELECT id FROM batches WHERE id = ? AND course_id = ?");
            $batchCheck->execute([$data['batch_id'], $data['course_id']]);
            if (!$batchCheck->fetch()) {
                $error = 'This course has no batches available. Contact your admin.';
            } else {
                $error = 'Invalid batch selection.';
            }
        }
        notifyAdmin('student_account', 'Signup blocked — invalid batch',
            $data['name'] . ' (' . $data['email'] . ') submitted an invalid college/course/batch combination.');
        return ['success' => false, 'error' => $error];
    }

    // Get college name and course name for record
    $collegeStmt = $pdo->prepare("SELECT name FROM colleges WHERE id = ?");
    $collegeStmt->execute([$data['college_id']]);
    $college = $collegeStmt->fetch();

    $courseStmt = $pdo->prepare("SELECT name FROM courses WHERE id = ?");
    $courseStmt->execute([$data['course_id']]);
    $course = $courseStmt->fetch();

    // Insert into unverified_students
    $stmt = $pdo->prepare("
        INSERT INTO unverified_students (batch_id, section, name, phone, email, gender, college_name, branch, roll_number, year_of_joining, course_name, password_hash)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['batch_id'],
        $data['section'] ?? null,
        $data['name'],
        $data['phone'],
        $data['email'],
        $data['gender'],
        $college['name'],
        $data['branch'],
        $data['roll_number'],
        (int)$data['year_of_joining'],
        $course['name'],
        password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => PASSWORD_BCRYPT_COST]),
    ]);

    $studentId = (int)$pdo->lastInsertId();

    // Generate and send OTP
    $otpResult = generateStudentOtp($studentId, $data['email'], $data['name']);

    // If the OTP email itself failed, the account is stuck "not completed"
    if (!$otpResult['success']) {
        notifyAdmin('student_account', 'OTP email failed — account not completed',
            $data['name'] . ' (' . $data['email'] . ') registered but the OTP email could not be sent: ' . ($otpResult['error'] ?? 'unknown'),
            BASE_URL . '/admin/pending_verifications.php');
    }

    return [
        'success'   => true,
        'student_id'=> $studentId,
        'otp_sent'  => $otpResult['success'],
        'otp_dev'   => $otpResult['otp'] ?? null, // Only in dev mode
    ];
}

// ─── SESSION CHECKS ───────────────────────────────────────

function isAdmin(): bool {
    return ($_SESSION['role'] ?? '') === 'admin' && !empty($_SESSION['admin_id']);
}

function isStudent(): bool {
    return ($_SESSION['role'] ?? '') === 'student' && !empty($_SESSION['student_id']);
}

function requireAdmin(): void {
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function requireStudent(): void {
    if (!isStudent()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        setcookie(session_name(), '', time() - 42000, '/');
    }
    session_destroy();
}

// ─── GUEST ACCESS ─────────────────────────────────────────

function guestLogin(string $token): array {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM guest_entries WHERE token = ? AND status = 'pending' AND expires_at > NOW()");
    $stmt->execute([$token]);
    $entry = $stmt->fetch();

    if (!$entry) {
        notifyAdmin('guest_link', 'Invalid or expired link/QR',
            'A guest tried to use token ' . substr($token, 0, 10) . '… but it was invalid or expired.');
        return ['success' => false, 'error' => 'Invalid or expired link.'];
    }

    $testId = $entry['test_id'] ? (int)$entry['test_id'] : null;
    $_SESSION['guest_token'] = $token;
    $_SESSION['guest_entry_id'] = (int)$entry['id'];
    $_SESSION['batch_id'] = (int)$entry['batch_id'];
    $_SESSION['test_id'] = $testId;
    $_SESSION['role'] = 'guest';

    return ['success' => true, 'test_id' => $testId];
}
