<?php

require_once __DIR__ . '/../includes/auth.php';

if (!FLIPBOOK_HUB_SSO_ENABLED) {
    http_response_code(404);
    exit;
}

if (!flipbook_is_https() || !flipbook_hub_is_configured()) {
    http_response_code(503);
    exit;
}

$code = isset($_GET['code']) && is_string($_GET['code']) ? $_GET['code'] : '';
$state = isset($_GET['state']) && is_string($_GET['state']) ? $_GET['state'] : '';
if ($code === '' || $state === '' || strlen($code) > 512 || strlen($state) > 512) {
    http_response_code(400);
    exit;
}

flipbook_auth_start_session();
$expectedState = $_SESSION['flipbook_hub_state_hash'] ?? '';
unset($_SESSION['flipbook_hub_state_hash']);
if ($expectedState === '' || !hash_equals($expectedState, hash('sha256', $state))) {
    http_response_code(400);
    exit;
}

$curl = curl_init(FLIPBOOK_HUB_TOKEN_URL);
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    CURLOPT_USERPWD => FLIPBOOK_HUB_CLIENT_ID . ':' . FLIPBOOK_HUB_CLIENT_SECRET,
    CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_POSTFIELDS => http_build_query([
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => FLIPBOOK_HUB_CALLBACK_URI,
    ], '', '&', PHP_QUERY_RFC3986),
    CURLOPT_SSL_VERIFYPEER => FLIPBOOK_HUB_VERIFY_TLS,
    CURLOPT_SSL_VERIFYHOST => FLIPBOOK_HUB_VERIFY_TLS ? 2 : 0,
]);
$body = curl_exec($curl);
$status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
$error = curl_error($curl);
curl_close($curl);

if ($body === false || $error !== '' || $status !== 200) {
    http_response_code(502);
    exit;
}

$identity = json_decode($body, true);
if (!is_array($identity)
    || ($identity['token_type'] ?? null) !== 'hub_identity'
    || ($identity['application'] ?? null) !== 'flipbook'
    || ($identity['role'] ?? null) !== 'admin'
    || !isset($identity['subject'], $identity['email'], $identity['name'])
    || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', (string)$identity['subject']) !== 1
    || filter_var($identity['email'], FILTER_VALIDATE_EMAIL) === false
    || !is_string($identity['name'])
    || trim($identity['name']) === '') {
    http_response_code(502);
    exit;
}

session_regenerate_id(true);
$_SESSION['flipbook_admin'] = [
    'subject' => $identity['subject'],
    'email' => strtolower(trim($identity['email'])),
    'name' => trim($identity['name']),
    'role' => 'admin',
    'authenticated_at' => time(),
];
flipbook_csrf_token();
$returnTo = $_SESSION['flipbook_return_to'] ?? (BASE_PATH . '/index.php');
unset($_SESSION['flipbook_return_to']);
if (!flipbook_is_safe_app_path($returnTo)) {
    $returnTo = BASE_PATH . '/index.php';
}

header('Location: ' . $returnTo, true, 302);
exit;
