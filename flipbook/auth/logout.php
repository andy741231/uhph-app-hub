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
$admin = flipbook_current_admin();
$logoutUrl = is_array($admin) && is_string($admin['logout_url'] ?? null) ? $admin['logout_url'] : '';
flipbook_destroy_session();
$destination = FLIPBOOK_HUB_SSO_ENABLED && flipbook_is_safe_hub_logout_url($logoutUrl)
    ? $logoutUrl
    : BASE_PATH . '/auth/login.php';
header('Location: ' . $destination, true, 302);
exit;
