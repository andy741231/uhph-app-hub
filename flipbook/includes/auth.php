<?php

require_once __DIR__ . '/../config.php';

function flipbook_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function flipbook_auth_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    session_name('flipbook_admin_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => BASE_PATH ?: '/',
        'secure' => FLIPBOOK_HUB_SSO_ENABLED || flipbook_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function flipbook_is_safe_app_path(string $url): bool
{
    $parts = parse_url($url);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
        return false;
    }

    $path = $parts['path'] ?? '';
    $base = BASE_PATH ?: '';

    return $path !== ''
        && ($base === '' || $path === $base || str_starts_with($path, $base . '/'))
        && preg_match('#^/[A-Za-z0-9_~.-]+(?:/[A-Za-z0-9_~.-]+)*$#', $path) === 1
        && preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $path) !== 1;
}

function flipbook_hub_is_configured(): bool
{
    return FLIPBOOK_HUB_CLIENT_ID !== ''
        && FLIPBOOK_HUB_CLIENT_SECRET !== ''
        && FLIPBOOK_HUB_CALLBACK_URI !== ''
        && str_starts_with(FLIPBOOK_HUB_BASE_URL, 'https://');
}

function flipbook_is_admin(): bool
{
    if (!FLIPBOOK_HUB_SSO_ENABLED) {
        return true;
    }

    flipbook_auth_start_session();
    $identity = $_SESSION['flipbook_admin'] ?? null;
    if (!is_array($identity) || ($identity['role'] ?? null) !== 'admin') {
        return false;
    }

    $authenticatedAt = (int)($identity['authenticated_at'] ?? 0);
    if ($authenticatedAt <= 0 || time() - $authenticatedAt >= FLIPBOOK_HUB_SESSION_REVALIDATION_SECONDS) {
        unset($_SESSION['flipbook_admin']);
        return false;
    }

    return true;
}

function flipbook_current_admin(): ?array
{
    return flipbook_is_admin() && FLIPBOOK_HUB_SSO_ENABLED
        ? $_SESSION['flipbook_admin']
        : null;
}

function flipbook_require_admin(): void
{
    if (!FLIPBOOK_HUB_SSO_ENABLED || flipbook_is_admin()) {
        return;
    }

    flipbook_auth_start_session();
    $returnTo = $_SERVER['REQUEST_URI'] ?? (BASE_PATH . '/index.php');
    if (flipbook_is_safe_app_path($returnTo)) {
        $_SESSION['flipbook_return_to'] = $returnTo;
    }

    header('Location: ' . BASE_PATH . '/auth/login.php', true, 302);
    exit;
}

function flipbook_require_api_admin(): void
{
    if (!FLIPBOOK_HUB_SSO_ENABLED || flipbook_is_admin()) {
        return;
    }

    flipbook_json_error('Authentication required.', 401);
}

function flipbook_csrf_token(): string
{
    flipbook_auth_start_session();
    if (empty($_SESSION['flipbook_csrf_token'])) {
        $_SESSION['flipbook_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['flipbook_csrf_token'];
}

function flipbook_csrf_is_valid(?string $token): bool
{
    return is_string($token)
        && $token !== ''
        && hash_equals(flipbook_csrf_token(), $token);
}

function flipbook_require_csrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!flipbook_csrf_is_valid($token)) {
        flipbook_json_error('Invalid CSRF token.', 419);
    }
}

function flipbook_json_error(string $message, int $status): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode(['error' => $message]);
    exit;
}

function flipbook_destroy_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        flipbook_auth_start_session();
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
