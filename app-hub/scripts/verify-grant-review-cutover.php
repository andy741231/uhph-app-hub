<?php

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__.'/../vendor/autoload.php';

function requireCutoverCheck(bool $condition, string $reason): void
{
    if (! $condition) {
        throw new RuntimeException($reason);
    }
}

function cutoverDestination(string $location, string $origin): string
{
    $url = str_starts_with($location, '/') ? $origin.$location : $location;
    $parsed = parse_url($url);
    requireCutoverCheck(is_array($parsed) && ($parsed['scheme'] ?? '') === 'https'
        && ($parsed['host'] ?? '') === 'uhph.uh.edu'
        && ! isset($parsed['user']) && ! isset($parsed['pass'])
        && ! isset($parsed['port'])
        && str_starts_with($parsed['path'] ?? '', '/apps/'), 'Untrusted live redirect.');

    return $url;
}

try {
    $app = require __DIR__.'/../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    requireCutoverCheck(app()->isProduction() && config('app.url') === 'https://uhph.uh.edu/apps'
        && config('hub.login_mode') === 'local', 'Unexpected Hub deployment.');
    $grantRoot = dirname(__DIR__, 2).'/grant-review';
    $env = Dotenv\Dotenv::createArrayBacked($grantRoot)->load();
    requireCutoverCheck(filter_var($env['HUB_SSO_ENABLED'] ?? false, FILTER_VALIDATE_BOOL)
        && ($env['HUB_URL'] ?? '') === 'https://uhph.uh.edu/apps', 'Grant Review integration not enabled.');
    $origin = 'https://uhph.uh.edu';
    $cookies = new CookieJar;
    $client = Http::withOptions(['allow_redirects' => false, 'cookies' => $cookies, 'verify' => true])
        ->connectTimeout(10)->timeout(30);
    $checks = [];
    $login = $client->get($origin.'/apps/grant-review/login');
    $authorizeUrl = cutoverDestination((string) $login->header('Location'), $origin);
    requireCutoverCheck($login->status() === 302
        && parse_url($authorizeUrl, PHP_URL_PATH) === '/apps/sso/authorize', 'Grant Review login did not delegate to Hub.');
    $authorize = $client->get($authorizeUrl);
    $hubLogin = cutoverDestination((string) $authorize->header('Location'), $origin);
    requireCutoverCheck($authorize->status() === 302
        && parse_url($hubLogin, PHP_URL_PATH) === '/apps/login', 'Guest authorization did not reach Hub login.');
    $page = $client->get($hubLogin);
    requireCutoverCheck($page->status() === 200, 'Contextual Hub login unavailable.');
    $checks['guest_login'] = ['grant_status' => 302, 'authorize_status' => 302, 'hub_login_status' => 200];
    $callback = $client->get($origin.'/apps/grant-review/auth/hub/callback');
    requireCutoverCheck($callback->status() === 400, 'Callback did not safely reject missing state/code.');
    $checks['invalid_callback_status'] = $callback->status();
    $invalidLogout = $client->get($origin.'/apps/grant-review/auth/hub/logout', ['logout_token' => 'invalid']);
    requireCutoverCheck($invalidLogout->status() === 400, 'Logout handler unavailable or invalid-token validation failed.');
    $checks['invalid_logout_status'] = $invalidLogout->status();
    $token = $client->asForm()->acceptJson()
        ->withBasicAuth($env['HUB_CLIENT_ID'], $env['HUB_CLIENT_SECRET'])
        ->post($origin.'/apps/sso/token', [
            'grant_type' => 'authorization_code',
            'code' => 'cutover-negative-check-'.bin2hex(random_bytes(16)),
            'redirect_uri' => $env['HUB_CALLBACK_URI'],
        ]);
    requireCutoverCheck($token->status() === 400 && $token->json('error') === 'invalid_grant',
        'Authenticated token transport failed its negative check.');
    $checks['token_transport'] = ['status' => 400, 'error' => 'invalid_grant'];

    // Exercise the real logout chain using fresh anonymous cookies, never a user's session.
    $cookies = new CookieJar;
    $logoutClient = Http::withOptions(['allow_redirects' => false, 'cookies' => $cookies, 'verify' => true])
        ->connectTimeout(10)->timeout(30);
    $url = URL::signedRoute('sso.logout', ['application' => 'grant-review']);
    $hops = [];
    $finished = false;
    for ($hop = 0; $hop < 8; $hop++) {
        $url = cutoverDestination($url, $origin);
        $response = $logoutClient->get($url);
        $path = parse_url($url, PHP_URL_PATH);
        $hops[] = ['path' => $path, 'status' => $response->status()];
        if ($path === '/apps/login' && $response->status() === 200) {
            $finished = true;
            break;
        }
        requireCutoverCheck(in_array($response->status(), [301, 302, 303], true),
            'Global logout chain stopped before Hub login.');
        $url = cutoverDestination((string) $response->header('Location'), $origin);
    }
    requireCutoverCheck($finished, 'Global logout chain exceeded expected hop limit.');
    requireCutoverCheck(in_array('/apps/grant-review/auth/hub/logout', array_column($hops, 'path'), true),
        'Grant Review missing from global logout chain.');
    $checks['anonymous_global_logout'] = $hops;
    $checks['scope'] = 'Guest live routing, real token transport with invalid code, anonymous signed logout chain. No authenticated user credentials or sessions used.';
    echo json_encode(['passed' => true, 'checked_at_utc' => gmdate('c'), 'checks' => $checks],
        JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $exception) {
    // Do not print request URLs, Basic credentials, logout tokens, or exception traces.
    $reason = get_class($exception) === RuntimeException::class ? $exception->getMessage() : get_class($exception);
    fwrite(STDERR, 'Live cutover check stopped: '.$reason.PHP_EOL);
    exit(1);
}
