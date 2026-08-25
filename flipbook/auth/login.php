<?php

require_once __DIR__ . '/../includes/auth.php';

if (!FLIPBOOK_HUB_SSO_ENABLED) {
    header('Location: ' . BASE_PATH . '/index.php', true, 302);
    exit;
}

if ((!flipbook_is_https() && !flipbook_is_local_development()) || !flipbook_hub_is_configured()) {
    http_response_code(503);
    echo 'Flipbook Hub authentication is not configured.';
    exit;
}

if (flipbook_is_admin()) {
    header('Location: ' . BASE_PATH . '/index.php', true, 302);
    exit;
}

flipbook_auth_start_session();
$state = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
$_SESSION['flipbook_hub_state_hash'] = hash('sha256', $state);
$query = http_build_query([
    'client_id' => FLIPBOOK_HUB_CLIENT_ID,
    'redirect_uri' => FLIPBOOK_HUB_CALLBACK_URI,
    'state' => $state,
], '', '&', PHP_QUERY_RFC3986);

header('Location: ' . FLIPBOOK_HUB_AUTHORIZE_URL . '?' . $query, true, 302);
exit;
