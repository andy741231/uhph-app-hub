<?php

$baseUrl = rtrim((string) env('HUB_URL', 'https://localhost/apps'), '/');

return [
    'enabled' => env('HUB_SSO_ENABLED', false),
    'base_url' => $baseUrl,
    'authorize_url' => $baseUrl.'/sso/authorize',
    'token_url' => $baseUrl.'/sso/token',
    'client_id' => env('HUB_CLIENT_ID'),
    'client_secret' => env('HUB_CLIENT_SECRET'),
    'callback_uri' => env('HUB_CALLBACK_URI'),
    'application_key' => env('HUB_APPLICATION_KEY'),
    'roles' => [],
    'verify_tls' => env('HUB_VERIFY_TLS', true),
    'request_timeout_seconds' => 10,
    'session_revalidation_minutes' => (int) env('HUB_SESSION_REVALIDATION_MINUTES', 15),
    'guard' => 'web',
    'login_route' => 'login',
    'state_session_key' => 'hub_sso_state_hash',
    'authenticated_at_session_key' => 'hub_authenticated_at',
    'emergency_authenticated_session_key' => 'emergency_authenticated',
];
