<?php
// community-partners/router.php  — used for local development (php -S localhost:8002 -t . router.php)

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Serve static files that actually exist
if ($path !== '/' && file_exists(__DIR__ . $path) && !is_dir(__DIR__ . $path)) {
    return false;
}

// Route all API requests to the PHP backend
if (strpos($path, '/api/') === 0 || $path === '/api') {
    require __DIR__ . '/api/index.php';
    return;
}

// SPA fallback — serve the requested HTML page if it exists, otherwise index.html
if (preg_match('/\.html$/', $path) && file_exists(__DIR__ . $path)) {
    include __DIR__ . $path;
    return;
}

require __DIR__ . '/index.html';
