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
// Any signed-in identity carries the signed Hub logout URL — not just
// admins. Using current_admin() here would strand user-role accounts on
// the login page, which silently signs them straight back in.
$user = flipbook_current_user();
$logoutUrl = is_array($user) && is_string($user['logout_url'] ?? null) ? $user['logout_url'] : '';
flipbook_destroy_session();
$destination = FLIPBOOK_HUB_SSO_ENABLED && flipbook_is_safe_hub_logout_url($logoutUrl)
    ? $logoutUrl
    : BASE_PATH . '/auth/login.php';
header('Location: ' . $destination, true, 302);
exit;
