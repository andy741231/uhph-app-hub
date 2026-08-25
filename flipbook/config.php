<?php
/**
 * Flipbook App Configuration
 */

$environmentFile = __DIR__ . '/.env';
if (is_file($environmentFile)) {
    foreach (parse_ini_file($environmentFile, false, INI_SCANNER_RAW) ?: [] as $name => $value) {
        if (getenv($name) === false) {
            putenv($name . '=' . $value);
        }
    }
}

$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
if ($host === 'uhph.uh.edu') {
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['SERVER_PORT'] = '443';
}

// Auto-detect base path from the document root and script location.
// Works on both local dev (localhost:8000) and production IIS (/apps/flipbook).
// You can manually override by setting BASE_PATH_OVERRIDE environment variable.
(function() {
    // Manual override for production (uncomment if auto-detection fails on IIS)
    // $base = '/apps/flipbook';
    $base = getenv('BASE_PATH_OVERRIDE') ?: '';
    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $appSegment = '/' . basename(__DIR__);
    $segmentPosition = strpos($scriptPath, $appSegment . '/');
    if (empty($base) && $segmentPosition !== false) {
        $base = substr($scriptPath, 0, $segmentPosition + strlen($appSegment));
    }
    
    // Auto-detect production vs local
    if (empty($base)) {
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        // If on localhost, use empty base path
        if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
            $base = '';
        }
        // If on production domain, use production path
        elseif (strpos($host, 'uhph.uh.edu') !== false || strpos($host, 'cougarnet.uh.edu') !== false) {
            $base = '/apps/flipbook';
        }
    }
    
    // Auto-detect if not manually set
    if (empty($base)) {
        // Try using PHP_SELF or SCRIPT_NAME for IIS compatibility
        $scriptPath = $_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME'] ?? '';
        if ($scriptPath) {
            // Extract directory from script path
            $scriptDir = dirname($scriptPath);
            // Remove trailing slash
            $base = rtrim($scriptDir, '/\\');
        }
        
        // Fallback: Try DOCUMENT_ROOT method for Apache
        if (empty($base) || $base === '.') {
            $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));
            $docRoot   = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));
            if ($docRoot && strpos($scriptDir, $docRoot) === 0) {
                $base = substr($scriptDir, strlen($docRoot));
            }
        }
        
        // Walk up from the current script to the app root (config.php is at app root)
        $appRoot = str_replace('\\', '/', dirname(__FILE__));
        $docRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));
        if ($docRoot && strpos($appRoot, $docRoot) === 0) {
            $base = substr($appRoot, strlen($docRoot));
        }
    }
    
    define('BASE_PATH', rtrim($base, '/'));
})();

// Database configuration
define('DB_HOST', 'uhph-server1.cougarnet.uh.edu');
define('DB_PORT', 3306);
define('DB_NAME', 'flipbook');
define('DB_USER', 'web_app');
define('DB_PASS', 'UHPH@2025_again');

// Upload settings
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('MAX_UPLOAD_SIZE', 100 * 1024 * 1024); // 100MB
define('ALLOWED_EXTENSIONS', ['pdf']);

// App settings
define('APP_NAME', 'Flipbook');
define('APP_VERSION', '1.0.0');

define('FLIPBOOK_LOCAL_DEV', filter_var(getenv('FLIPBOOK_LOCAL_DEV') ?: 'false', FILTER_VALIDATE_BOOLEAN));
define('FLIPBOOK_HUB_SSO_ENABLED', filter_var(getenv('FLIPBOOK_HUB_SSO_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN));
define('FLIPBOOK_HUB_BASE_URL', rtrim(getenv('FLIPBOOK_HUB_URL') ?: 'https://localhost/apps', '/'));
define('FLIPBOOK_HUB_AUTHORIZE_URL', FLIPBOOK_HUB_BASE_URL . '/sso/authorize');
define('FLIPBOOK_HUB_TOKEN_URL', FLIPBOOK_HUB_BASE_URL . '/sso/token');
define('FLIPBOOK_HUB_CLIENT_ID', getenv('FLIPBOOK_HUB_CLIENT_ID') ?: '');
define('FLIPBOOK_HUB_CLIENT_SECRET', getenv('FLIPBOOK_HUB_CLIENT_SECRET') ?: '');
define('FLIPBOOK_HUB_CALLBACK_URI', getenv('FLIPBOOK_HUB_CALLBACK_URI') ?: '/apps/flipbook/auth/callback.php');
define('FLIPBOOK_HUB_VERIFY_TLS', filter_var(getenv('FLIPBOOK_HUB_VERIFY_TLS') ?: 'true', FILTER_VALIDATE_BOOLEAN));
define('FLIPBOOK_HUB_SESSION_REVALIDATION_SECONDS', max(60, ((int)(getenv('FLIPBOOK_HUB_SESSION_REVALIDATION_MINUTES') ?: 15)) * 60));
