<?php
/**
 * Utility / helper functions.
 */

// ─── PHP 8 Polyfills for PHP 7.x compatibility ─────────────
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}
// ────────────────────────────────────────────────────────────

/**
 * Redirect and exit.
 * Automatically strips hardcoded XAMPP prefix and prepends BASE_URL.
 */
function redirect(string $url): void {
    // Strip any hardcoded XAMPP prefix (keeps URLs portable)
    $prefix = '/test-platform/src/php/public';
    if (str_starts_with($url, $prefix)) {
        $url = substr($url, strlen($prefix));
    }
    // Prepend BASE_URL for absolute paths
    if (str_starts_with($url, '/')) {
        $url = BASE_URL . $url;
    }
    if (!headers_sent()) {
        header('Location: ' . $url);
    } else {
        // Headers already sent — use JS redirect as fallback
        echo '<script>window.location.href=' . json_encode($url) . ';</script>';
    }
    exit;
}

/**
 * Flash message (set or get).
 */
function flash(string $key, ?string $value = null): ?string {
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
    $val = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $val;
}

/**
 * Flash message HTML helper — Fluent 2 alert with SVG icons.
 */
function flashMessage(): string {
    $icons = [
        'success' => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>',
        'error'   => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16zm0 1a7 7 0 1 0 0 14 7 7 0 0 0 0-14zm0 9.5a.75.75 0 1 1 0 1.5.75.75 0 0 1 0-1.5zM10 6a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-1 0v-4A.5.5 0 0 1 10 6z"/></svg>',
        'warning' => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16zm0 1a7 7 0 1 0 0 14 7 7 0 0 0 0-14zm0 9.5a.75.75 0 1 1 0 1.5.75.75 0 0 1 0-1.5zM10 6a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-1 0v-4A.5.5 0 0 1 10 6z"/></svg>',
        'info'    => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16zm0 1a7 7 0 1 0 0 14 7 7 0 0 0 0-14zm-.5 3.5a.5.5 0 0 1 .5-.5h.01a.5.5 0 0 1 0 1H10a.5.5 0 0 1-.5-.5zM9 9a1 1 0 0 1 1-1h.25a1 1 0 0 1 1 1v2.5h.25a.5.5 0 0 1 0 1h-1.5a.5.5 0 0 1 0-1h.25V9.5H10a.5.5 0 0 1-.5.5V9z"/></svg>',
    ];
    $html = '';
    foreach ($icons as $key => $svg) {
        $msg = flash($key);
        if ($msg) {
            $clean = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
            $html .= '<div class="alert alert-' . $key . '">';
            $html .= $svg;
            $html .= '<span>' . $clean . '</span>';
            $html .= '</div>';
        }
    }
    return $html;
}

/**
 * Sanitize output for HTML.
 */
function h(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a secure random token (for guest links, QR codes).
 */
function generateToken(int $length = 32): string {
    return bin2hex(random_bytes($length));
}

/**
 * Get date/time for display.
 */
function formatDateTime(?string $datetime): string {
    if (!$datetime) return '—';
    return date('d M Y, h:i A', strtotime($datetime));
}

/**
 * Get time ago string.
 */
function timeAgo(?string $datetime): string {
    if (!$datetime) return '—';
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    return floor($diff / 86400) . ' days ago';
}

/**
 * Parse the request body uniformly across async endpoints.
 *
 * - JSON payloads (Content-Type: application/json) are decoded from php://input.
 * - Standard form bodies fall back to $_POST.
 * - On malformed JSON an empty array is returned so callers can 400 out.
 */
function parseRequestBody(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
    return $_POST;
}

/**
 * Call Python analysis API (PCI / charts microservice).
 *
 * Robust client with:
 *  - GET support (query-string params) and POST support (JSON body)
 *  - connect/read timeout
 *  - automatic retries with small backoff
 *  - structured result: ['ok' => bool, 'data' => ?array, 'status' => int, 'error' => ?string]
 */
function pythonApiRequest(string $endpoint, array $payload = [], string $method = 'GET', int $timeout = 3, int $retries = 2): array {
    if (!defined('PYTHON_API_URL')) {
        return ['ok' => false, 'data' => null, 'status' => 0, 'error' => 'PYTHON_API_URL not configured'];
    }

    $body = null;
    $headers = [];
    $url = rtrim(PYTHON_API_URL, '/') . $endpoint;

    if (strtoupper($method) === 'POST') {
        $body = json_encode($payload);
        $headers = ['Content-Type: application/json'];
    } elseif (!empty($payload)) {
        $url .= '?' . http_build_query($payload);
    }

    for ($attempt = 0; $attempt <= $retries; $attempt++) {
        $ctx = stream_context_create([
            'http' => [
                'method'        => strtoupper($method),
                'header'        => $headers,
                'content'       => $body,
                'timeout'       => $timeout,
                'ignore_errors' => true, // surface HTTP error statuses instead of failing the read
            ],
        ]);

        $result = @file_get_contents($url, false, $ctx);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) {
            $status = (int)$m[1];
        }

        if ($result !== false && $status >= 200 && $status < 300) {
            $decoded = json_decode($result, true);
            return ['ok' => true, 'data' => is_array($decoded) ? $decoded : null, 'status' => $status, 'error' => null];
        }

        if ($attempt < $retries) {
            usleep(200000 * ($attempt + 1)); // 200ms, 400ms backoff
        }
    }

    return ['ok' => false, 'data' => null, 'status' => $status, 'error' => 'Python API request failed'];
}

/**
 * Recalculate & persist PCI for one submission.
 *
 * Coordination contract:
 *  1. Caller MUST have committed the grading/answer writes before invoking this.
 *  2. Tries the Python analytics service first (with retries / error boundary).
 *  3. Falls back to an equivalent PHP-side calculation when the service is
 *     unavailable, so pci_records stays consistent and reports never break.
 *
 * Returns ['ok' => bool, 'source' => 'python'|'php'|null, 'error' => ?string].
 */
function recalculatePciForSubmission(int $submissionId): array {
    if ($submissionId <= 0) return ['ok' => false, 'source' => null, 'error' => 'Invalid submission id'];

    $pdo = getDB();

    // 1) Preferred path: Python analytics service (committed rows are visible to it)
    $res = pythonApiRequest('/api/pci/calculate', ['submission_id' => $submissionId], 'POST', 3, 2);
    if ($res['ok'] && !empty($res['data'])) {
        return ['ok' => true, 'source' => 'python', 'error' => null];
    }
    error_log('PCI: Python API unavailable (' . ($res['error'] ?? 'unknown') . '), using PHP fallback for submission ' . $submissionId);

    // 2) Fallback: PHP-side calculation (mirrors reports.php weights)
    try {
        $stmt = $pdo->prepare("
            SELECT s.student_id, s.test_id
            FROM submissions s
            WHERE s.id = ?
        ");
        $stmt->execute([$submissionId]);
        $sub = $stmt->fetch();
        if (!$sub) return ['ok' => false, 'source' => null, 'error' => 'Submission not found'];

        $stmt = $pdo->prepare("
            SELECT q.type,
                   SUM(sa.marks_obtained) AS obtained,
                   SUM(q.marks) AS total
            FROM student_answers sa
            JOIN questions q ON q.id = sa.question_id
            WHERE sa.submission_id = ?
            GROUP BY q.type
        ");
        $stmt->execute([$submissionId]);
        $rows = $stmt->fetchAll();

        $scores = ['mcq' => [0, 0], 'coding' => [0, 0], 'explanation' => [0, 0]];
        foreach ($rows as $r) {
            $scores[$r['type']] = [(float)($r['obtained'] ?? 0), (float)($r['total'] ?? 0)];
        }

        $pct = function (array $s): float {
            return $s[1] > 0 ? round(($s[0] / $s[1]) * 100, 2) : 0.0;
        };

        $mcqPct = $pct($scores['mcq']);
        $codingPct = $pct($scores['coding']);
        $explPct = $pct($scores['explanation']);
        $pciScore = round(($mcqPct * 0.40) + ($codingPct * 0.30) + ($explPct * 0.30), 2);

        $stmt = $pdo->prepare("
            INSERT INTO pci_records (student_id, test_id, pci_score, mcq_score, coding_score, explanation_score,
                                     mcq_weight, coding_weight, explanation_weight, generated_at)
            VALUES (?, ?, ?, ?, ?, ?, 40.00, 30.00, 30.00, NOW())
            ON DUPLICATE KEY UPDATE
                pci_score = VALUES(pci_score),
                mcq_score = VALUES(mcq_score),
                coding_score = VALUES(coding_score),
                explanation_score = VALUES(explanation_score),
                generated_at = NOW()
        ");
        $stmt->execute([(int)$sub['student_id'], (int)$sub['test_id'], $pciScore, $mcqPct, $codingPct, $explPct]);

        return ['ok' => true, 'source' => 'php', 'error' => null];
    } catch (Exception $e) {
        error_log('PCI fallback failed for submission ' . $submissionId . ': ' . $e->getMessage());
        return ['ok' => false, 'source' => null, 'error' => 'PCI fallback failed'];
    }
}

/**
 * Get eligible tests for a student (based on batch).
 */
function getStudentTests(int $studentId): array {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT t.*,
               s.id AS submission_id,
               s.status AS submission_status,
               s.evaluation_status,
               s.started_at,
               s.submitted_at,
               s.timer_extended_minutes,
               s.total_marks_obtained,
               s.total_marks,
               (SELECT COUNT(*) FROM questions q WHERE q.test_id = t.id) AS total_questions
        FROM tests t
        JOIN students st ON st.id = ?
        LEFT JOIN submissions s ON s.test_id = t.id AND s.student_id = st.id
        WHERE EXISTS (
            SELECT 1 FROM test_sections ts WHERE ts.test_id = t.id AND ts.batch_id = st.batch_id
        )
        OR (
            NOT EXISTS (SELECT 1 FROM test_sections ts WHERE ts.test_id = t.id)
            AND t.batch_id = st.batch_id
        )
        ORDER BY t.start_time DESC
    ");
    $stmt->execute([$studentId]);
    return $stmt->fetchAll();
}

/**
 * Get remaining time in seconds for a submission.
 */
function getRemainingSeconds(array $submission, int $testDuration): int {
    $started = strtotime($submission['started_at']);
    $extended = ($submission['timer_extended_minutes'] ?? 0) * 60;
    $elapsed = time() - $started;
    $total = ($testDuration * 60) + $extended;
    return max(0, $total - $elapsed);
}

// ─── ADMIN NOTIFICATIONS (bell icon feed) ────────────────────

/**
 * Create an admin notification. Never throws — failures are logged silently
 * so the caller flow (signup, login, guest, test) is never interrupted.
 *
 * Types: student_account | guest_link | qr | test | system
 */
function notifyAdmin(string $type, string $title, string $message, ?string $link = null): void {
    try {
        static $pdo = null;
        $pdo = $pdo ?? getDB();
        $stmt = $pdo->prepare("INSERT INTO admin_notifications (type, title, message, link) VALUES (?, ?, ?, ?)");
        $stmt->execute([$type, $title, mb_substr($message, 0, 500), $link]);
    } catch (Throwable $e) {
        error_log('notifyAdmin failed: ' . $e->getMessage());
    }
}

/**
 * Render notification items HTML for the bell panel (shared by the
 * server-rendered header and the JSON poll endpoint).
 */
function renderNotificationItems(array $items): string {
    $typeIcon = [
        'student_account' => 'student',      // graduation cap
        'guest_link'      => 'external-link',
        'qr'              => 'external-link',
        'test'            => 'test',
        'system'          => 'warning',
    ];
    $typeColor = [
        'student_account' => 'amber',
        'guest_link'      => 'blue',
        'qr'              => 'blue',
        'test'            => 'red',
        'system'          => 'red',
    ];

    if (empty($items)) {
        $icon = function_exists('icon') ? icon('notifications', 32) : '';
        return '<div class="notif-empty">' . $icon . '<p>No notifications</p></div>';
    }

    $html = '';
    foreach ($items as $n) {
        $cls   = $typeColor[$n['type']] ?? 'amber';
        $ic    = $typeIcon[$n['type']] ?? 'warning';
        $title = h($n['title'] ?? 'Notification');
        $msg   = h($n['message'] ?? '');
        $time  = timeAgo($n['created_at']);
        $dot   = empty($n['is_read']) ? '<span class="notif-unread-dot"></span>' : '';
        $readCls = empty($n['is_read']) ? '' : ' is-read';
        $tag      = !empty($n['link']) ? 'a' : 'div';
        $hrefAttr = !empty($n['link']) ? ' href="' . h($n['link']) . '"' : '';
        $iconSvg  = function_exists('icon') ? icon($ic, 16) : '';

        $html .= '<' . $tag . $hrefAttr . ' class="notif-item' . $readCls . '" data-id="' . (int)$n['id'] . '">'
               . '<span class="notif-icon ' . $cls . '">' . $iconSvg . '</span>'
               . '<span class="notif-content">'
               . '<span class="notif-text"><strong>' . $title . '</strong>' . ($msg ? ' — ' . $msg : '') . '</span>'
               . '<span class="notif-time">' . $time . $dot . '</span>'
               . '</span>'
               . '</' . $tag . '>';
    }
    return $html;
}
