<?php

require_once __DIR__ . '/../includes/auth.php';

if (!FLIPBOOK_HUB_SSO_ENABLED || (!flipbook_is_https() && !flipbook_is_local_development())) {
    http_response_code(404);
    exit;
}

$token = isset($_GET['logout_token']) && is_string($_GET['logout_token']) ? $_GET['logout_token'] : '';
if (preg_match('/^[A-Za-z0-9]{64}$/', $token) !== 1) {
    http_response_code(400);
    exit;
}

$curl = curl_init(FLIPBOOK_HUB_LOGOUT_CONTINUE_URL);
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_POSTFIELDS => http_build_query([
        'application' => 'flipbook',
        'logout_token' => $token,
    ], '', '&', PHP_QUERY_RFC3986),
    CURLOPT_SSL_VERIFYPEER => FLIPBOOK_HUB_VERIFY_TLS,
    CURLOPT_SSL_VERIFYHOST => FLIPBOOK_HUB_VERIFY_TLS ? 2 : 0,
]);
$body = curl_exec($curl);
$status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
$error = curl_error($curl);
curl_close($curl);
$payload = is_string($body) ? json_decode($body, true) : null;
$nextUrl = is_array($payload) && is_string($payload['next_url'] ?? null) ? $payload['next_url'] : '';

if ($body === false || $error !== '' || $status !== 200 || !flipbook_is_safe_hub_navigation_url($nextUrl)) {
    http_response_code(502);
    exit;
}

flipbook_destroy_session();
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');
header('Location: ' . $nextUrl, true, 302);
exit;
