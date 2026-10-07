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
    'callback_uri' => env('HUB_CALLBACK_URI', '/apps/doc-review/auth/hub/callback'),
    'application_key' => 'doc-review',
    'roles' => ['admin', 'user'],
    'verify_tls' => env('HUB_VERIFY_TLS', true),
    'session_revalidation_minutes' => (int) env('HUB_SESSION_REVALIDATION_MINUTES', 15),
    'login_mode_session_key' => 'hub_login_mode',
];
