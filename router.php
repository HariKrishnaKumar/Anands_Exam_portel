<?php
/**
 * PHP built-in development server router.
 *
 * Usage: php -S localhost:8000 router.php
 *
 * This router:
 * 1. Serves static assets (css/js/images/fonts) directly from the project root.
 * 2. Routes PHP requests to src/php/public/.
 * 3. Rewrites /assets/* to the real assets/ directory.
 * 4. Restricts dangerous HTTP methods (H-10 fix).
 * 5. Validates file paths with realpath() to prevent traversal (M-11/L-10 fix).
 */

// ─── HTTP Method Restriction (H-10) ───
// Only allow GET and POST. Reject DELETE/PUT/PATCH with 405.
$allowedMethods = ['GET', 'POST', 'HEAD', 'OPTIONS'];
if (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods, true)) {
    http_response_code(405);
    header('Allow: GET, POST, HEAD, OPTIONS');
    echo '405 Method Not Allowed';
    exit;
}

// Serve static assets directly
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Strip /test-platform prefix if present (for XAMPP URL portability)
if (str_starts_with($requestUri, '/test-platform')) {
    $requestUri = substr($requestUri, strlen('/test-platform'));
}

// ─── Map /assets/ to real filesystem path ───────────────────
if (str_starts_with($requestUri, '/assets/')) {
    $filePath = __DIR__ . $requestUri;
    // M-11: Validate realpath to prevent directory traversal
    $realPath = realpath($filePath);
    $realBase = realpath(__DIR__);
    if ($realPath && $realBase && str_starts_with($realPath, $realBase) && is_file($filePath)) {
        // Set MIME type based on extension
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'css'  => 'text/css',
            'js'   => 'application/javascript',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            'webp' => 'image/webp',
            'woff' => 'font/woff',
            'woff2'=> 'font/woff2',
            'ttf'  => 'font/ttf',
            'ico'  => 'image/x-icon',
            'json' => 'application/json',
        ];
        if (isset($mimeTypes[$ext])) {
            header('Content-Type: ' . $mimeTypes[$ext]);
        }
        readfile($filePath);
        return true;
    }
    // If asset not found, return 404
    http_response_code(404);
    echo "Asset not found";
    return true;
}

// ─── Serve well-known root static files (favicon, robots.txt) ──
if (preg_match('#^/(favicon\.ico|robots\.txt)$#', $requestUri, $m)) {
    $filePath = __DIR__ . '/' . $m[1];
    if (file_exists($filePath)) {
        header('Content-Type: ' . ($m[1] === 'robots.txt' ? 'text/plain' : 'image/x-icon'));
        readfile($filePath);
        return true;
    }
}

// ─── Map /api/ and /src/php/api/ to src/php/api/ ────────────
if (str_starts_with($requestUri, '/api/') || str_starts_with($requestUri, '/src/php/api/')) {
    $apiDir = __DIR__ . '/src/php';
    $filePath = $apiDir . $requestUri;
    // Remove /src/php prefix if present (legacy XAMPP paths)
    if (str_starts_with($requestUri, '/src/php/api/')) {
        $filePath = $apiDir . substr($requestUri, strlen('/src/php'));
    }
    // M-11: Validate realpath
    $realPath = realpath($filePath);
    $realBase = realpath($apiDir);
    if ($realPath && $realBase && str_starts_with($realPath, $realBase)
        && is_file($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === 'php') {
        require $filePath;
        return true;
    }
    http_response_code(404);
    echo "API endpoint not found";
    return true;
}

// ─── Strip /src/php/public prefix (legacy XAMPP paths) ────
if (str_starts_with($requestUri, '/src/php/public/') || $requestUri === '/src/php/public') {
    $requestUri = substr($requestUri, strlen('/src/php/public')) ?: '/';
}

// ─── Clean URL: /admin/colleges/{id} → college_dashboard.php?id={id} ──
$publicDir = __DIR__ . '/src/php/public';
if (preg_match('#^/admin/colleges/(\d+)$#', $requestUri, $m)) {
    $filePath = $publicDir . '/admin/college_dashboard.php';
    if (file_exists($filePath)) {
        $_GET['id'] = (int)$m[1];
        $_REQUEST['id'] = $_GET['id'];
        require $filePath;
        return true;
    }
}

// ─── Map / to src/php/public/ ──────────────────────────────
$filePath = $publicDir . $requestUri;

// If requesting a directory, try index.php
if (is_dir($filePath)) {
    $indexPath = rtrim($filePath, '/') . '/index.php';
    if (file_exists($indexPath)) {
        require $indexPath;
        return true;
    }
    // Directory listing not allowed
    http_response_code(404);
    echo "404 Not Found";
    return true;
}

// Serve the PHP file or static file from public/
if (file_exists($filePath) && !is_dir($filePath)) {
    if (pathinfo($filePath, PATHINFO_EXTENSION) === 'php') {
        require $filePath;
        return true;
    }
    // Serve non-PHP static files within public/
    return false; // Let PHP built-in server handle it
}

// 404 fallback
http_response_code(404);
echo "404 Not Found";
return true;
