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
expect(flipbook_is_safe_hub_navigation_url('https://hub.test/apps/grant-review/auth/hub/logout?logout_token=test'), 'Same-origin Hub logout navigation should be accepted.');
expect(! flipbook_is_safe_hub_navigation_url('https://attacker.example/apps/login'), 'Cross-origin Hub navigation must be rejected.');

flipbook_auth_start_session();
$_SESSION['flipbook_csrf_token'] = 'known-csrf-token';
expect(flipbook_csrf_token() === 'known-csrf-token', 'Existing CSRF tokens should be stable.');
expect(flipbook_csrf_is_valid('known-csrf-token'), 'Matching CSRF tokens should be valid.');
expect(! flipbook_csrf_is_valid('wrong-token'), 'Mismatched CSRF tokens must be rejected.');

$root = dirname(__DIR__);

// Page-level gates: index is public, upload/editor require any signed-in user,
// editor additionally enforces owner-or-admin on the specific flipbook.
$index = file_get_contents($root.'/index.php');
expect(!str_contains($index, 'flipbook_require_admin'), 'index.php must remain reachable anonymously (public gallery).');
$upload = file_get_contents($root.'/upload.php');
expect(str_contains($upload, 'flipbook_require_user();'), 'upload.php must require a signed-in session.');
$editor = file_get_contents($root.'/editor.php');
expect(str_contains($editor, 'flipbook_require_user();'), 'editor.php must require a signed-in session.');
expect(str_contains($editor, 'flipbook_can_manage('), 'editor.php must enforce owner-or-admin per flipbook.');

$mutationApis = ['api/upload.php', 'api/flipbooks.php', 'api/videos.php', 'api/links.php', 'api/text.php'];
foreach ($mutationApis as $file) {
    $source = file_get_contents($root.'/'.$file);
    expect(str_contains($source, 'flipbook_require_api_user();'), "$file must require a signed-in session for mutations.");
    expect(str_contains($source, 'flipbook_require_csrf();'), "$file must enforce CSRF protection for mutations.");
}
// api/upload.php creates the parent row, so it has nothing to authorize against;
// the child/mutation APIs must check the parent flipbook explicitly.
foreach (['api/flipbooks.php', 'api/videos.php', 'api/links.php', 'api/text.php'] as $file) {
    $source = file_get_contents($root.'/'.$file);
    expect(str_contains($source, 'flipbook_authorize_'), "$file must authorize against the parent flipbook.");
}

foreach (['api/pdf.php', 'api/download.php', 'api/cover.php'] as $file) {
    $source = file_get_contents($root.'/'.$file);
    expect(str_contains($source, 'flipbook_authorize_view('), "$file must enforce the visibility rule.");
}

// Direct access to uploads/ must be denied on every serving layer, since
// uploaded PDFs live under the web root and are only authorized via api/.
$webConfig = file_get_contents($root.'/web.config');
expect(str_contains($webConfig, '<add segment="uploads"'), 'web.config must hide the uploads segment on IIS.');
$htaccess = file_get_contents($root.'/.htaccess');
expect(preg_match('#RewriteRule\s+\^uploads/#', $htaccess) === 1, '.htaccess must deny uploads/ on Apache.');
expect(str_contains($htaccess, 'REQUEST_URI} =~ m#/uploads#'), '.htaccess must deny uploads/ even without mod_rewrite.');
expect(preg_match('#FilesMatch\s+"\^\\\\\.env"#', $htaccess) === 1, '.htaccess must deny .env files on Apache.');
$router = file_get_contents(dirname($root).'/server.php');
expect(str_contains($router, "'uploads'"), 'server.php must block uploads/ in local development.');
expect(str_contains($router, 'uriSegments'), 'server.php must also block root-level /<app>/uploads requests.');
expect(str_contains($router, 'firstSegment'), 'server.php must deny raw paths into app source directories.');
$cover = file_get_contents($root.'/api/cover.php');
expect(str_contains($cover, 'basename('), 'api/cover.php must restrict thumbnails to basename-only paths.');
expect(str_contains($cover, 'no-store'), 'api/cover.php must mark every response no-store (visibility is mutable).');
expect(!str_contains($cover, 'max-age='), 'api/cover.php must not emit any cache lifetime.');
$pdfApi = file_get_contents($root.'/api/pdf.php');
expect(str_contains($pdfApi, 'no-store'), 'api/pdf.php must mark every response no-store.');
expect(!str_contains($pdfApi, '31536000'), 'api/pdf.php must not emit a far-future immutable lifetime.');
expect(!str_contains($pdfApi, "header('ETag"), 'api/pdf.php must not emit ETag headers.');
expect(!str_contains($pdfApi, 'IF_NONE_MATCH'), 'api/pdf.php must not answer conditional 304 requests.');
expect(str_contains(file_get_contents($root.'/api/flipbooks.php'), 'no-store'), 'api/flipbooks.php must mark gallery JSON no-store.');

$header = file_get_contents($root.'/includes/header.php');
$callback = file_get_contents($root.'/auth/callback.php');
$logout = file_get_contents($root.'/auth/logout.php');
$globalLogout = file_get_contents($root.'/auth/hub-logout.php');
expect(str_contains($header, 'All Applications'), 'The authenticated header should offer the app launcher to multi-app users.');
expect(str_contains($header, 'fa-right-to-bracket'), 'The anonymous header should offer a Sign in control.');
expect(str_contains($callback, "'application_count'"), 'The callback must retain the assigned application count.');
expect(str_contains($callback, "'logout_url'"), 'The callback must retain the signed Hub logout URL.');
expect(str_contains($logout, 'flipbook_is_safe_hub_logout_url'), 'Logout must only redirect to a trusted Hub logout URL.');
expect(str_contains($globalLogout, 'FLIPBOOK_HUB_LOGOUT_CONTINUE_URL'), 'Global logout must validate its token with the Hub.');
expect(str_contains($globalLogout, 'flipbook_destroy_session'), 'Global logout must destroy the Flipbook session.');

$viewer = file_get_contents($root.'/viewer.php');
$viewerJs = file_get_contents($root.'/assets/js/viewer.js');
expect(str_contains($viewer, 'FLIPBOOK_CAN_EDIT'), 'The public viewer must expose an authenticated edit capability flag.');
expect(str_contains($viewer, 'flipbook_can_view('), 'The viewer must apply the visibility rule server-side.');
expect(
    strpos($viewer, 'if ($isEmbed && ($viewerFlipbook') !== false
    && strpos($viewer, 'if ($isEmbed && ($viewerFlipbook') < strpos($viewer, 'if (!flipbook_can_view('),
    'The private-embed denial must preempt can_view so owner/admin cannot embed private flipbooks.'
);
expect(str_contains($viewerJs, 'FLIPBOOK_CAN_EDIT'), 'Viewer JavaScript must honor the edit capability flag.');
expect(preg_match('/if \(\$canEdit\):\s*\?>\s*<button[^>]*id="btnEditToc"/s', $viewer) === 1, 'btnEditToc must only render for owner/admin.');
expect(preg_match('/if \(\$canEdit\):\s*\?>\s*<!-- Edit Mode Panel/', $viewer) === 1, 'The edit panel must only render for owner/admin.');
expect(preg_match('/if \(\$canEdit\):\s*\?>\s*<!-- Add Link Modal/', $viewer) === 1, 'Edit modals must only render for owner/admin.');

$index = file_get_contents($root.'/index.php');
expect(str_contains($index, 'role="link"'), 'Gallery cards must expose role=link for keyboard/assistive access.');
expect(str_contains($index, 'tabindex="0"'), 'Gallery cards must be focusable (tabindex=0).');
expect(str_contains($index, 'aria-label="Open'), 'Gallery cards must carry an accessible label.');
expect(str_contains($index, "addEventListener('keydown'"), 'Gallery must open cards on keyboard activation.');
expect(str_contains(file_get_contents($root.'/assets/css/app.css'), '.flipbook-card:focus-visible'), 'Cards must show a visible focus outline.');
expect(str_contains($viewerJs, "'X-CSRF-Token': FLIPBOOK_CSRF_TOKEN"), 'Viewer mutations must send the CSRF token.');

// ---------------------------------------------------------------------------
// can_view / can_manage matrix: anonymous, owner, other user, admin
//                              x public / unlisted / private
// ---------------------------------------------------------------------------

$mkIdentity = static fn (string $role, string $subject): array => [
    'subject' => $subject,
    'email' => $role.'@example.edu',
    'name' => ucfirst($role),
    'role' => $role,
    'application_count' => 1,
    'logout_url' => 'https://hub.test/apps/sso/logout?application=flipbook&signature=x',
    'authenticated_at' => time(),
];

$asAnon = static function (): void { unset($_SESSION['flipbook_admin']); };
$asUser = static function (array $identity): void { $_SESSION['flipbook_admin'] = $identity; };

$ownerSubject = '11111111-1111-4111-8111-111111111111';
$otherSubject = '22222222-2222-4222-8222-222222222222';
$adminSubject = '33333333-3333-4333-8333-333333333333';

foreach (['public', 'unlisted', 'private'] as $vis) {
    $row = ['visibility' => $vis, 'owner_subject' => $ownerSubject];
    foreach (
        [
            'anonymous' => $asAnon,
            'owner'     => fn () => $asUser($mkIdentity('user', $ownerSubject)),
            'other'     => fn () => $asUser($mkIdentity('user', $otherSubject)),
            'admin'     => fn () => $asUser($mkIdentity('admin', $adminSubject)),
        ] as $who => $login
    ) {
        $login();
        $expectView = $vis !== 'private' || in_array($who, ['owner', 'admin'], true);
        $expectManage = in_array($who, ['owner', 'admin'], true);
        expect(
            flipbook_can_view($row) === $expectView,
            "can_view mismatch: {$who} x {$vis} (expected ".var_export($expectView, true).').'
        );
        expect(
            flipbook_can_manage($row) === $expectManage,
            "can_manage mismatch: {$who} x {$vis} (expected ".var_export($expectManage, true).').'
        );
    }
}

$asAnon();
expect(flipbook_current_user() === null, 'Anonymous visitors have no identity.');
$asUser($mkIdentity('user', $otherSubject));
expect(flipbook_current_user()['role'] === 'user', 'Signed-in user role should be returned.');
expect(!flipbook_is_admin(), 'A user-role session must not be treated as admin.');
$asUser($mkIdentity('admin', $adminSubject));
expect(flipbook_is_admin(), 'An admin-role session must be treated as admin.');
$asUser(['subject' => $otherSubject, 'email' => 'stale@example.edu', 'name' => 'Stale', 'role' => 'user', 'authenticated_at' => time() - FLIPBOOK_HUB_SESSION_REVALIDATION_SECONDS - 1]);
expect(flipbook_current_user() === null, 'Stale sessions must be revalidated.');
$asAnon();

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures).PHP_EOL);
    exit(1);
}

echo 'Flipbook authentication tests passed.'.PHP_EOL;
