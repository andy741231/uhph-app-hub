<?php

require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (!flipbook_csrf_is_valid($_POST['csrf_token'] ?? null)) {
    http_response_code(419);
    exit;
}
flipbook_destroy_session();
header('Location: ' . (FLIPBOOK_HUB_SSO_ENABLED ? FLIPBOOK_HUB_BASE_URL : BASE_PATH . '/index.php'), true, 302);
exit;
