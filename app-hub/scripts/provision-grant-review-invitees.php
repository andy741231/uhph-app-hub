<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__.'/../vendor/autoload.php';

function requireInviteeState(bool $condition, string $reason): void
{
    if (! $condition) {
        throw new RuntimeException($reason);
    }
}

function inviteeRows(PDO $pdo, string $sql, array $bindings = []): array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($bindings);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

$pdo = null;
$committed = false;
try {
    $options = getopt('', ['scope:', 'checkpoint:', 'live']);
    requireInviteeState(isset($options['scope'], $options['checkpoint']), 'Explicit scope and checkpoint required.');
    $scope = json_decode(file_get_contents($options['scope']), true, 512, JSON_THROW_ON_ERROR);
    requireInviteeState(count($scope['accounts']) === 3, 'Approved batch must contain exactly three invitees.');
    $live = isset($options['live']);
    $checkpoint = $options['checkpoint'];
    requireInviteeState(is_dir(dirname($checkpoint)), 'Private checkpoint directory must exist.');
    $app = require __DIR__.'/../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $connection = DB::connection();
    $hubConfig = $connection->getConfig();
    $grantRoot = dirname(__DIR__, 2).'/grant-review';
    if (is_file($grantRoot.'/bootstrap/cache/config.php')) {
        $grantConfig = require $grantRoot.'/bootstrap/cache/config.php';
        $grantDriver = $grantConfig['database']['default'];
        $grantDatabase = $grantConfig['database']['connections'][$grantDriver];
    } else {
        $env = Dotenv\Dotenv::createArrayBacked($grantRoot)->load();
        $grantDriver = $env['DB_CONNECTION'] ?? 'sqlite';
        $grantDatabase = [
            'host' => $env['DB_HOST'] ?? '127.0.0.1',
            'port' => $env['DB_PORT'] ?? '3306',
            'database' => $env['DB_DATABASE'] ?? null,
            'url' => $env['DB_URL'] ?? null,
            'prefix' => '',
        ];
    }
    requireInviteeState(in_array($grantDriver, ['mysql', 'mariadb'], true)
        && in_array($hubConfig['driver'], ['mysql', 'mariadb'], true)
        && empty($grantDatabase['url']) && empty($hubConfig['url'])
        && empty($grantDatabase['prefix']) && empty($hubConfig['prefix'])
        && (string) $grantDatabase['host'] === (string) $hubConfig['host']
        && (string) $grantDatabase['port'] === (string) $hubConfig['port'],
        'Unsupported database topology.');
    requireInviteeState($grantDatabase['database'] === $scope['grant_schema']
        && $hubConfig['database'] === $scope['hub_schema'], 'Configured schemas do not match approval.');
    foreach ([$scope['grant_schema'], $scope['hub_schema']] as $schema) {
        requireInviteeState(is_string($schema) && preg_match('/^[A-Za-z0-9_-]+$/', $schema) === 1, 'Unsafe schema name.');
    }
    requireInviteeState($scope['grant_schema'] !== $scope['hub_schema'], 'Schemas must be distinct.');
    $grant = '`'.$scope['grant_schema'].'`';
    $hub = '`'.$scope['hub_schema'].'`';
    $pdo = $connection->getPdo();
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
    if ($live) {
        $pdo->beginTransaction();
    } else {
        $pdo->exec('START TRANSACTION READ ONLY');
    }
    $lock = $live ? ' FOR UPDATE' : '';
    $applications = inviteeRows($pdo,
        "SELECT id, enabled, roles FROM {$hub}.applications WHERE `key` = 'grant-review'".$lock);
    requireInviteeState(count($applications) === 1 && (bool) $applications[0]['enabled']
        && in_array('submitter', json_decode($applications[0]['roles'], true, 512, JSON_THROW_ON_ERROR), true),
        'Grant Review submitter registration unavailable.');
    $applicationId = (int) $applications[0]['id'];
    $actors = inviteeRows($pdo,
        "SELECT id FROM {$hub}.users WHERE id = ? AND status = 'active' AND is_admin = 1".$lock,
        [$scope['granting_admin_id']]);
    requireInviteeState(count($actors) === 1, 'Approved granting administrator unavailable.');
    $sources = [];
    $seenEmails = [];
    $seenIds = [];
    foreach ($scope['accounts'] as $item) {
        requireInviteeState(! isset($seenEmails[$item['email']]) && ! isset($seenIds[$item['grant_id']]), 'Duplicate scope.');
        $seenEmails[$item['email']] = true;
        $seenIds[$item['grant_id']] = true;
        $rows = inviteeRows($pdo,
            "SELECT id, email, first_name, last_name, role, status,
                (password_hash IS NULL OR password_hash = '') AS password_missing,
                (sso_sub IS NULL OR sso_sub = '') AS subject_missing
             FROM {$grant}.users WHERE LOWER(TRIM(email)) = ?".$lock, [$item['email']]);
        requireInviteeState(count($rows) === 1, 'Source is missing or ambiguous.');
        $source = $rows[0];
        requireInviteeState((int) $source['id'] === $item['grant_id'] && $source['role'] === 'submitter'
            && $source['status'] === 'invited' && (bool) $source['password_missing']
            && (bool) $source['subject_missing'], 'Invitee state changed; review before provisioning.');
        $targets = inviteeRows($pdo,
            "SELECT id FROM {$hub}.users WHERE LOWER(TRIM(email)) = ?".$lock, [$item['email']]);
        requireInviteeState($targets === [], 'Hub account already exists; no automatic overwrite or resend.');
        $sources[] = ['scope' => $item, 'source' => $source];
    }
    $state = ['scope' => $scope, 'application_id' => $applicationId, 'sources' => $sources];
    if (! $live) {
        $handle = fopen($checkpoint, 'x');
        requireInviteeState($handle !== false, 'Cannot exclusively create checkpoint.');
        try {
            $json = json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            requireInviteeState(fwrite($handle, $json) === strlen($json) && fflush($handle), 'Checkpoint write failed.');
        } finally {
            fclose($handle);
        }
        $pdo->exec('ROLLBACK');
        $pdo = null;
        echo "DRY RUN: three new Hub identities with null passwords and Grant Review submitter access. No writes or emails.\n";
        exit(0);
    }
    requireInviteeState(json_decode(file_get_contents($checkpoint), true, 512, JSON_THROW_ON_ERROR) === $state,
        'State changed since dry run; no changes committed.');
    $created = [];
    foreach ($sources as $record) {
        $source = $record['source'];
        $email = $record['scope']['email'];
        $publicId = (string) Str::uuid();
        $name = trim($source['first_name'].' '.$source['last_name']) ?: $email;
        $insert = $pdo->prepare(
            "INSERT INTO {$hub}.users
             (public_id, name, email, password, status, is_admin, email_verified_at, created_at, updated_at)
             VALUES (?, ?, ?, NULL, 'active', 0, NULL, NOW(), NOW())"
        );
        $insert->execute([$publicId, $name, $email]);
        $hubId = (int) $pdo->lastInsertId();
        $assign = $pdo->prepare(
            "INSERT INTO {$hub}.application_user
             (application_id, user_id, role, granted_by, granted_at, created_at, updated_at)
             VALUES (?, ?, 'submitter', ?, NOW(), NOW(), NOW())"
        );
        $assign->execute([$applicationId, $hubId, $scope['granting_admin_id']]);
        $verified = inviteeRows($pdo,
            "SELECT u.id, u.public_id, u.email, u.status, u.is_admin, u.password,
                au.application_id, au.role FROM {$hub}.users u
             JOIN {$hub}.application_user au ON au.user_id = u.id WHERE u.id = ?", [$hubId]);
        requireInviteeState(count($verified) === 1 && $verified[0]['email'] === $email
            && $verified[0]['public_id'] === $publicId && $verified[0]['status'] === 'active'
            && ! $verified[0]['is_admin'] && $verified[0]['password'] === null
            && (int) $verified[0]['application_id'] === $applicationId && $verified[0]['role'] === 'submitter',
            'Post-write account or assignment verification failed.');
        $created[] = ['hub_id' => $hubId, 'email' => $email];
    }
    $pdo->commit();
    $committed = true;
    echo json_encode([
        'committed' => true,
        'completed_at_utc' => gmdate('c'),
        'created' => $created,
        'emails_sent' => 0,
        'grant_review_changed' => false,
        'sso_setting_changed' => false,
        'next_step' => 'Verify integrated sign-in before sending the approved Hub invitations.',
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $exception) {
    if (! $committed && $pdo !== null && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $reason = get_class($exception) === RuntimeException::class ? $exception->getMessage() : get_class($exception);
    fwrite(STDERR, ($committed ? 'COMMITTED; reporting failed: ' : 'Provisioning stopped; transaction not committed: ').$reason.PHP_EOL);
    exit(1);
}
