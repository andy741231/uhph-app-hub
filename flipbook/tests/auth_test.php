<?php

declare(strict_types=1);

putenv('FLIPBOOK_LOCAL_DEV=true');
putenv('FLIPBOOK_HUB_SSO_ENABLED=true');
putenv('FLIPBOOK_HUB_URL=https://hub.test/apps');
putenv('FLIPBOOK_HUB_CLIENT_ID=hub_flipbook');
putenv('FLIPBOOK_HUB_CLIENT_SECRET=test-client-secret');
putenv('FLIPBOOK_HUB_CALLBACK_URI=/apps/flipbook/auth/callback.php');
putenv('BASE_PATH_OVERRIDE=/apps/flipbook');
$_SERVER['HTTPS'] = 'on';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/apps/flipbook/tests/auth_test.php';
$_SERVER['REQUEST_URI'] = '/apps/flipbook/index.php';

require_once dirname(__DIR__).'/includes/auth.php';

$failures = [];

function expect(bool $condition, string $message): void
{
    global $failures;

    if (! $condition) {
        $failures[] = $message;
    }
}

expect(FLIPBOOK_HUB_SSO_ENABLED === true, 'Hub SSO should be enabled from the environment.');
expect(FLIPBOOK_HUB_BASE_URL === 'https://hub.test/apps', 'Hub URL should be normalized.');
expect(FLIPBOOK_HUB_CALLBACK_URI === '/apps/flipbook/auth/callback.php', 'Callback should match the registered path.');
expect(flipbook_is_local_development(), 'The explicit local development flag should be recognized.');
expect(flipbook_hub_is_configured(), 'Hub SSO should be configured.');
expect(flipbook_is_safe_app_path('/apps/flipbook/editor.php?id=1'), 'An internal Flipbook return path should be accepted.');
expect(! flipbook_is_safe_app_path('/apps/../phpmyadmin'), 'Traversal paths must be rejected.');
expect(! flipbook_is_safe_app_path('https://example.com'), 'Absolute external URLs must be rejected.');
expect(flipbook_is_safe_hub_logout_url('https://hub.test/apps/sso/logout?application=flipbook&signature=test'), 'Signed Hub logout URLs should be accepted.');
expect(! flipbook_is_safe_hub_logout_url('https://attacker.example/apps/sso/logout?signature=test'), 'Cross-origin logout URLs must be rejected.');
expect(! flipbook_is_safe_hub_logout_url('https://hub.test/apps/dashboard'), 'Non-logout Hub URLs must be rejected.');

flipbook_auth_start_session();
$_SESSION['flipbook_csrf_token'] = 'known-csrf-token';
expect(flipbook_csrf_token() === 'known-csrf-token', 'Existing CSRF tokens should be stable.');
expect(flipbook_csrf_is_valid('known-csrf-token'), 'Matching CSRF tokens should be valid.');
expect(! flipbook_csrf_is_valid('wrong-token'), 'Mismatched CSRF tokens must be rejected.');

$root = dirname(__DIR__);
$adminPages = ['index.php', 'upload.php', 'editor.php'];
foreach ($adminPages as $file) {
    $source = file_get_contents($root.'/'.$file);
    expect(str_contains($source, 'flipbook_require_admin();'), "$file must require an administrator session.");
}

$mutationApis = ['api/upload.php', 'api/flipbooks.php', 'api/videos.php', 'api/links.php', 'api/text.php'];
foreach ($mutationApis as $file) {
    $source = file_get_contents($root.'/'.$file);
    expect(str_contains($source, 'flipbook_require_api_admin();'), "$file must enforce administrator access for mutations.");
    expect(str_contains($source, 'flipbook_require_csrf();'), "$file must enforce CSRF protection for mutations.");
}

$header = file_get_contents($root.'/includes/header.php');
$callback = file_get_contents($root.'/auth/callback.php');
$logout = file_get_contents($root.'/auth/logout.php');
expect(str_contains($header, 'All applications'), 'The authenticated header should offer the app launcher to multi-app users.');
expect(str_contains($callback, "'application_count'"), 'The callback must retain the assigned application count.');
expect(str_contains($callback, "'logout_url'"), 'The callback must retain the signed Hub logout URL.');
expect(str_contains($logout, 'flipbook_is_safe_hub_logout_url'), 'Logout must only redirect to a trusted Hub logout URL.');

$viewer = file_get_contents($root.'/viewer.php');
$viewerJs = file_get_contents($root.'/assets/js/viewer.js');
expect(str_contains($viewer, 'FLIPBOOK_CAN_EDIT'), 'The public viewer must expose an authenticated edit capability flag.');
expect(str_contains($viewerJs, 'FLIPBOOK_CAN_EDIT'), 'Viewer JavaScript must honor the edit capability flag.');
expect(str_contains($viewerJs, "'X-CSRF-Token': FLIPBOOK_CSRF_TOKEN"), 'Viewer mutations must send the CSRF token.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures).PHP_EOL);
    exit(1);
}

echo 'Flipbook authentication tests passed.'.PHP_EOL;
