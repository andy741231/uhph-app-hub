<?php

$baseUrl = rtrim((string) env('HUB_URL', 'https://localhost/apps'), '/');

return [
    'enabled' => env('HUB_SSO_ENABLED', false),
    'base_url' => $baseUrl,
    'authorize_url' => $baseUrl.'/sso/authorize',
    'token_url' => $baseUrl.'/sso/token',
    'logout_continue_url' => $baseUrl.'/sso/logout/continue',
    'managed_users_url' => $baseUrl.'/sso/managed-users',
    'client_id' => env('HUB_CLIENT_ID'),
    'client_secret' => env('HUB_CLIENT_SECRET'),
    'callback_uri' => env('HUB_CALLBACK_URI', '/apps/grant-review/auth/hub/callback'),
    'application_key' => 'grant-review',
    'roles' => ['admin', 'submitter', 'reviewer'],
    'verify_tls' => env('HUB_VERIFY_TLS', true),
    'session_revalidation_minutes' => (int) env('HUB_SESSION_REVALIDATION_MINUTES', 15),
    'emergency_login' => [
        'enabled' => env('EMERGENCY_LOGIN_ENABLED', false),
        'allowed_ips' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('EMERGENCY_LOGIN_ALLOWED_IPS', '')),
        ))),
    ],
];
