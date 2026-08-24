<?php
// inventory/router.php

$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

// Serve static files if they exist
if (file_exists(__DIR__ . $path) && !is_dir(__DIR__ . $path)) {
    return false; // Let PHP serve the file
}

// Route API requests
if (strpos($path, '/api/') === 0) {
    require __DIR__ . '/api/index.php';
    return;
}

// Serve index.html for root or other non-file routes (SPA fallback)
require __DIR__ . '/index.html';
