<?php
/**
 * Local development router for the UHPH app-hub IIS layout.
 *
 * Mimics the production IIS /apps mount:
 *   /apps/flipbook/*      -> flipbook/ (physical app, served as-is)
 *   /apps/grant-review/*  -> grant-review/public/ (Laravel public dir)
 *   /apps/doc-review/*    -> doc-review/public/ (Laravel public dir)
 *   /apps/<laravel-dir>/* -> <dir>/public/ (any dir with public/index.php)
 *   /apps/<other-dir>/*   -> <other-dir>/ (physical app, served as-is)
 *   /apps/<non-physical>  -> app-hub/public/index.php (App Hub front controller)
 *   /                     -> root index.php (placeholder)
 *
 * Usage:
 *   php -S localhost:8000 server.php
 *
 * The /apps prefix is stripped before routing to App Hub so Laravel sees
 * the same paths it does in production (e.g. /login, /dashboard).
 */

$root = __DIR__;
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');

// --- Root-level requests (not under /apps) ---
if ($uri !== '/' && strpos($uri, '/apps') !== 0) {
    // Uploaded flipbook files are only served through authorized api/*.php
    // endpoints — never directly, under any app path.
    $uriSegments = array_map('strtolower', array_values(array_filter(explode('/', $uri))));
    if (in_array('uploads', $uriSegments, true)) {
        http_response_code(404);
        return true;
    }
    // App source directories (flipbook/, doc-review/, app-hub/, ...) are only
    // reachable through /apps/* routing — never as raw static paths, so
    // /flipbook/.env etc. cannot leak. Root-level files stay servable.
    $firstSegment = explode('/', ltrim($uri, '/'))[0] ?? '';
    if ($firstSegment !== '' && is_dir($root . DIRECTORY_SEPARATOR . $firstSegment)) {
        http_response_code(404);
        return true;
    }
    $file = safe_file_path($root, $uri);
    if ($file !== null && !is_dir($file) && strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php') {
        serve_static_file($file);
        return true;
    }
    http_response_code(404);
    return true;
}

if ($uri === '/' || $uri === '/apps') {
    // Redirect bare /apps to /apps/ so relative URLs resolve correctly.
    if ($uri === '/apps') {
        header('Location: /apps/', true, 301);
        return true;
    }
    // App Hub root — route to Laravel
    return route_to_hub($root);
}

if ($uri === '/apps/') {
    // App Hub root — route to Laravel
    return route_to_hub($root);
}

// --- Everything under /apps/* ---
$rel = substr($uri, 5); // strip "/apps"
$rel = '/' . ltrim($rel, '/');
if (in_array($rel, ['/favicon.png', '/favicon.ico'], true)) {
    serve_static_file($root . $rel);
    return true;
}
$parts = explode('/', trim($rel, '/'));
$first = $parts[0] ?? '';

if ($first !== '') {
    if ($first === 'app-hub') {
        return route_to_hub($root);
    }

    $physDir = $root . '/' . $first;

    if (is_dir($physDir)) {
        // Laravel apps (grant-review, doc-review, …) are served only from
        // their public/ directory — source directories are never exposed.
        if (file_exists($physDir . '/public/index.php')) {
            return serve_laravel_public($physDir . '/public', $rel, '/apps/' . $first);
        }
        // Other physical apps (flipbook, etc.)
        return serve_physical_app($physDir, $rel, '/apps/' . $first);
    }
}

// Not a physical directory — route to App Hub
return route_to_hub($root);

// ─── Helpers ───────────────────────────────────────────────────────────

function safe_file_path(string $root, string $path): ?string
{
    $root = realpath($root);
    $file = realpath($root . '/' . ltrim($path, '/'));

    return $root !== false
        && $file !== false
        && ($file === $root || str_starts_with($file, $root . DIRECTORY_SEPARATOR))
            ? $file
            : null;
}

function serve_static_file(string $file): void
{
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $types = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'mjs' => 'application/javascript',
        'json' => 'application/json',
        'html' => 'text/html',
        'htm' => 'text/html',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'webp' => 'image/webp',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
        'otf' => 'font/otf',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
        'map' => 'application/json',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'ogg' => 'audio/ogg',
        'wav' => 'audio/wav',
        'zip' => 'application/zip',
    ];
    if (! isset($types[$ext])) {
        http_response_code(404);
        return;
    }
    header('Content-Type: ' . $types[$ext]);
    header('Content-Length: ' . filesize($file));
    header('X-Content-Type-Options: nosniff');
    readfile($file);
}

function route_to_hub(string $root): bool
{
    $publicDir = $root . '/app-hub/public';
    // Tell Laravel the app is mounted at /apps so it generates correct URLs.
    $_SERVER['SCRIPT_NAME'] = '/apps/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $publicDir . '/index.php';
    chdir($publicDir);
    require $publicDir . '/index.php';
    return true;
}

function serve_laravel_public(string $publicDir, string $rel, string $basePath): bool
{
    $_SERVER['UHPH_LOCAL_DEV'] = '1';
    // Strip the app's mount segment (e.g. /grant-review, /doc-review).
    $mount = '/' . basename($basePath);
    $subPath = substr($rel, strlen($mount));
    $subPath = '/' . ltrim($subPath, '/');

    // Serve static files that exist
    if ($subPath !== '/' && $subPath !== '') {
        $file = safe_file_path($publicDir, $subPath);
        if ($file !== null && !is_dir($file)) {
            if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'php' && basename($file) !== 'index.php') {
                http_response_code(404);
                return true;
            }
            if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php') {
                serve_static_file($file);
                return true;
            }
        }
    }

    // Route everything else to Laravel's front controller
    $_SERVER['SCRIPT_NAME'] = rtrim($basePath, '/') . '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $publicDir . '/index.php';
    chdir($publicDir);
    require $publicDir . '/index.php';
    return true;
}

function serve_physical_app(string $appDir, string $rel, string $basePath): bool
{
    $subPath = substr($rel, strlen('/' . basename($appDir)));
    $subPath = '/' . ltrim($subPath, '/');
    $segments = array_map('strtolower', array_values(array_filter(explode('/', $subPath))));
    $blocked = ['config.php', 'includes', '.windsurf', '.devin', '.git', '.env', '.playwright-mcp', 'tests', 'sql', 'scripts', 'uploads'];
    if (array_intersect($segments, $blocked) !== []) {
        http_response_code(404);
        return true;
    }

    if ($subPath !== '/' && $subPath !== '') {
        $file = safe_file_path($appDir, $subPath);
        if ($file !== null && !is_dir($file)) {
            if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'php') {
                execute_php_file($file, rtrim($basePath, '/') . $subPath);
            } else {
                serve_static_file($file);
            }
            return true;
        }
        if ($file !== null && is_dir($file)) {
            $idx = $file . '/index.php';
            if (file_exists($idx)) {
                execute_php_file($idx, rtrim($basePath, '/') . rtrim($subPath, '/') . '/index.php');
                return true;
            }
            $idxHtml = $file . '/index.html';
            if (file_exists($idxHtml)) {
                serve_static_file($idxHtml);
                return true;
            }
        }
    }

    $indexPhp = $appDir . '/index.php';
    if (file_exists($indexPhp)) {
        execute_php_file($indexPhp, rtrim($basePath, '/') . '/index.php');
        return true;
    }
    $indexHtml = $appDir . '/index.html';
    if (file_exists($indexHtml)) {
        serve_static_file($indexHtml);
        return true;
    }
    http_response_code(404);
    return true;
}

function execute_php_file(string $file, string $scriptName): void
{
    $_SERVER['SCRIPT_NAME'] = $scriptName;
    $_SERVER['PHP_SELF'] = $scriptName;
    $_SERVER['SCRIPT_FILENAME'] = $file;
    chdir(dirname($file));
    require $file;
}
