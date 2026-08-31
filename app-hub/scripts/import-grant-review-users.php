<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$live = in_array('--live', $argv, true);
$forcedPasswordEmail = 'mchan2@uh.edu';
$forcedPassword = null;

if ($live) {
    if (stream_isatty(STDIN)) {
        fwrite(STDOUT, "Forced password for {$forcedPasswordEmail}: ");
        shell_exec('stty -echo');
        $forcedPassword = trim((string) fgets(STDIN));
        shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);
    } else {
        $forcedPassword = trim((string) fgets(STDIN));
    }
}

$sourceEnv = parse_ini_file(dirname(__DIR__, 2).'/grant-review/.env', false, INI_SCANNER_RAW);
$sourceDatabase = $sourceEnv['DB_DATABASE'] ?? null;

if (! is_string($sourceDatabase) || preg_match('/^[A-Za-z0-9_-]+$/', $sourceDatabase) !== 1) {
    fwrite(STDERR, "Grant Review DB_DATABASE is missing or invalid.\n");
    exit(1);
}

$source = '`'.str_replace('`', '``', $sourceDatabase).'`';
$connection = DB::connection();
$targetDatabase = $connection->getDatabaseName();

if (preg_match('/^[A-Za-z0-9_-]+$/', $targetDatabase) !== 1) {
    fwrite(STDERR, "UHPH App Hub database name is invalid.\n");
    exit(1);
}

$target = '`'.str_replace('`', '``', $targetDatabase).'`';
$application = $connection->table('applications')->where('key', 'grant-review')->first();
$grantingAdmin = $connection->table('users')->where('is_admin', true)->orderBy('id')->first();

if (! $application || ! $grantingAdmin) {
    fwrite(STDERR, "Grant Review application or Hub granting administrator is missing.\n");
    exit(1);
}

$sourceUsers = $connection->select("SELECT
        LOWER(TRIM(gr.email)) AS email,
        COALESCE(
            NULLIF(TRIM(CONCAT_WS(' ', NULLIF(TRIM(gr.first_name), ''), NULLIF(TRIM(gr.last_name), ''))), ''),
            NULLIF(TRIM(gr.name), ''),
            LOWER(TRIM(gr.email))
        ) AS display_name,
        gr.password_hash,
        gr.sso_sub,
        gr.role,
        gr.status,
        hub.id AS hub_user_id
    FROM {$source}.users gr
    LEFT JOIN {$target}.users hub ON hub.email = LOWER(TRIM(gr.email))
    ORDER BY gr.email");

$missing = collect($sourceUsers)->whereNull('hub_user_id')->values();
$invalid = $missing->filter(function (object $user) use ($forcedPasswordEmail): bool {
    $hasCompatibleHash = is_string($user->password_hash)
        && password_get_info($user->password_hash)['algoName'] === 'bcrypt';

    return ! in_array($user->role, ['admin', 'submitter', 'reviewer'], true)
        || ! in_array($user->status, ['active', 'disabled'], true)
        || ($user->sso_sub !== null && ! \Illuminate\Support\Str::isUuid($user->sso_sub))
        || (! $hasCompatibleHash && $user->email !== $forcedPasswordEmail);
});

if ($invalid->isNotEmpty()) {
    fwrite(STDERR, "Import aborted: one or more missing users lack an active/disabled status, supported role, or compatible bcrypt password.\n");
    foreach ($invalid as $user) {
        fwrite(STDERR, "  {$user->email}\n");
    }
    exit(1);
}

echo 'Source users: '.count($sourceUsers).PHP_EOL;
echo 'Existing Hub users: '.(count($sourceUsers) - $missing->count()).PHP_EOL;
echo 'Users to import: '.$missing->count().PHP_EOL;
foreach ($missing as $user) {
    echo "  {$user->email} | {$user->role} | {$user->status}\n";
}

if (! $live) {
    echo "DRY RUN: no database changes were made. Re-run with --live and pipe the forced password on stdin to import.\n";
    exit(0);
}

if ($missing->contains('email', $forcedPasswordEmail) && blank($forcedPassword)) {
    fwrite(STDERR, "Import aborted: the forced password for {$forcedPasswordEmail} must be supplied on stdin.\n");
    exit(1);
}

$forcedPasswordHash = $forcedPassword === null ? null : Hash::make($forcedPassword);
$connection->transaction(function () use ($connection, $source, $target, $application, $grantingAdmin, $forcedPasswordEmail, $forcedPasswordHash): void {
    $connection->statement("INSERT INTO {$target}.users (
            public_id, name, email, external_subject, email_verified_at, password,
            status, is_admin, last_login_at, remember_token, created_at, updated_at
        )
        SELECT
            COALESCE(gr.sso_sub, UUID()),
            COALESCE(
                NULLIF(TRIM(CONCAT_WS(' ', NULLIF(TRIM(gr.first_name), ''), NULLIF(TRIM(gr.last_name), ''))), ''),
                NULLIF(TRIM(gr.name), ''),
                LOWER(TRIM(gr.email))
            ),
            LOWER(TRIM(gr.email)),
            NULL,
            NOW(),
            CASE WHEN LOWER(TRIM(gr.email)) = ? THEN ? ELSE gr.password_hash END,
            CASE WHEN gr.status = 'disabled' THEN 'disabled' ELSE 'active' END,
            0,
            NULL,
            NULL,
            NOW(),
            NOW()
        FROM {$source}.users gr
        LEFT JOIN {$target}.users hub ON hub.email = LOWER(TRIM(gr.email))
        WHERE hub.id IS NULL", [
        $forcedPasswordEmail,
        $forcedPasswordHash,
    ]);

    $connection->statement("INSERT INTO {$target}.application_user (
            application_id, user_id, role, granted_by, granted_at, created_at, updated_at
        )
        SELECT ?, hub.id, gr.role, ?, NOW(), NOW(), NOW()
        FROM {$source}.users gr
        JOIN {$target}.users hub ON hub.email = LOWER(TRIM(gr.email))
        LEFT JOIN {$target}.application_user assignment
            ON assignment.application_id = ? AND assignment.user_id = hub.id
        WHERE assignment.id IS NULL", [
        $application->id,
        $grantingAdmin->id,
        $application->id,
    ]);
});

$imported = $connection->table('users')
    ->join('application_user', 'application_user.user_id', '=', 'users.id')
    ->where('application_user.application_id', $application->id)
    ->whereIn('users.email', $missing->pluck('email')->all())
    ->count();

if ($imported !== $missing->count()) {
    fwrite(STDERR, "Post-import verification failed: expected {$missing->count()} assignments, found {$imported}.\n");
    exit(1);
}

echo "LIVE IMPORT COMPLETE: {$imported} user(s) and Grant Review assignment(s) imported. No emails were sent.\n";
