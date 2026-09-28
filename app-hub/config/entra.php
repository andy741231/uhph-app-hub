<?php

$tenant = (string) env('ENTRA_TENANT_ID', '');
$legacyEntraEnabled = filter_var(env('ENTRA_SSO_ENABLED', false), FILTER_VALIDATE_BOOL);
$loginMode = strtolower((string) env('HUB_LOGIN_MODE', $legacyEntraEnabled ? 'hybrid' : 'local'));

return [
    'enabled' => in_array($loginMode, ['sso', 'hybrid'], true),
    'tenant_id' => $tenant,
    'client_id' => env('ENTRA_CLIENT_ID'),
    'client_secret' => env('ENTRA_CLIENT_SECRET'),
    'scope' => env('ENTRA_SCOPE', 'openid email profile'),
    'verify_tls' => (bool) env('ENTRA_VERIFY_TLS', true),
    'state_ttl' => (int) env('ENTRA_STATE_TTL', 300),
    'jwks_cache_ttl' => (int) env('ENTRA_JWKS_CACHE_TTL', 3600),
    'request_timeout_seconds' => (int) env('ENTRA_REQUEST_TIMEOUT_SECONDS', 10),
    'authorize_url' => "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/authorize",
    'token_url' => "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token",
    'jwks_uri' => "https://login.microsoftonline.com/{$tenant}/discovery/v2.0/keys",
    'issuer' => "https://login.microsoftonline.com/{$tenant}/v2.0",
];
