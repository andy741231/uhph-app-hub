<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = '443';

// IIS fix: when the app root is accessed without a trailing slash
// (e.g. /apps/grant-review), IIS sets REQUEST_URI (and UNENCODED_URL) to
// /apps/grant-review (no trailing slash). Symfony's Request::prepareBaseUrl()
// then fails to compute the correct baseUrl, resulting in pathInfo =
// /apps/grant-review instead of "/" — which matches no route and returns 404.
//
// Fix: if REQUEST_URI or UNENCODED_URL matches SCRIPT_NAME's directory (the
// app base) without a trailing slash, append one. This makes Symfony compute
// the correct baseUrl and pathInfo. We must fix BOTH variables because
// Symfony's prepareRequestUri() prefers UNENCODED_URL when IIS URL Rewrite
// is active (IIS_WasUrlRewritten=1).
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$appBase = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
if ($appBase !== '' && $appBase !== '/') {
    foreach (['REQUEST_URI', 'UNENCODED_URL'] as $var) {
        $val = $_SERVER[$var] ?? '';
        if ($val === $appBase) {
            $_SERVER[$var] = $val.'/';
        }
    }
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
