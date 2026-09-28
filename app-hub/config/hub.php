<?php

$legacyEntraEnabled = filter_var(env('ENTRA_SSO_ENABLED', false), FILTER_VALIDATE_BOOL);

return [
    'login_mode' => strtolower((string) env('HUB_LOGIN_MODE', $legacyEntraEnabled ? 'hybrid' : 'local')),
    'login_method_session_key' => 'hub_login_method',
    'authorization_code_ttl' => (int) env('HUB_AUTHORIZATION_CODE_TTL', 60),
    'application_admin_token_ttl' => (int) env('HUB_APPLICATION_ADMIN_TOKEN_TTL', 20),
    'local_client' => [
        'application_keys' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('HUB_LOCAL_APPLICATION_KEYS', '')),
        ))),
        'secret' => env('HUB_LOCAL_CLIENT_SECRET'),
    ],
];
