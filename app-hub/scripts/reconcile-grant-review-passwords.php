<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__.'/../vendor/autoload.php';

function ensureCutover(bool $condition, string $reason): void
{
    if (! $condition) {
        throw new RuntimeException($reason);
    }
}

function cutoverRows(PDO $pdo, string $sql, array $bindings = []): array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($bindings);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function saveCutoverFile(string $path, array $data): void
{
    $handle = fopen($path, 'x');
    ensureCutover($handle !== false, 'Cannot create exclusive checkpoint file.');
    try {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        ensureCutover(fwrite($handle, $json) === strlen($json), 'Incomplete checkpoint write.');
        ensureCutover(fflush($handle), 'Cannot flush checkpoint.');
    } finally {
        fclose($handle);
    }
}

$pdo = null;
$committed = false;
try {
    $options = getopt('', ['scope:', 'checkpoint:', 'live', 'approve-admin-password-replacement']);
    ensureCutover(isset($options['scope'], $options['checkpoint']), 'Scope and checkpoint paths required.');
    $scope = json_decode(file_get_contents($options['scope']), true, 512, JSON_THROW_ON_ERROR);
    $checkpointPath = $options['checkpoint'];
    ensureCutover(is_dir(dirname($checkpointPath)), 'Private checkpoint directory must already exist.');
    $live = isset($options['live']);
    ensureCutover(! $live || isset($options['approve-admin-password-replacement']), 'Admin replacement approval flag required.');
    ensureCutover(count($scope['accounts']) === 8, 'This approved batch must contain exactly eight accounts.');
    $app = require __DIR__.'/../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    ensureCutover(config('hashing.driver') === 'bcrypt', 'Hub hash driver must support imported bcrypt hashes.');
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
    ensureCutover(
        in_array($grantDriver, ['mysql', 'mariadb'], true)
        && in_array($hubConfig['driver'], ['mysql', 'mariadb'], true)
        && empty($grantDatabase['url']) && empty($hubConfig['url'])
        && empty($grantDatabase['prefix']) && empty($hubConfig['prefix'])
        && (string) $grantDatabase['host'] === (string) $hubConfig['host']
        && (string) $grantDatabase['port'] === (string) $hubConfig['port'],
        'Unsupported source/target connection topology.'
    );
    ensureCutover($grantDatabase['database'] === $scope['grant_schema']
        && $hubConfig['database'] === $scope['hub_schema'], 'Configured schemas differ from approved scope.');
    foreach ([$scope['grant_schema'], $scope['hub_schema']] as $schema) {
        ensureCutover(is_string($schema) && preg_match('/^[A-Za-z0-9_-]+$/', $schema) === 1, 'Unsafe schema.');
    }
    ensureCutover($scope['grant_schema'] !== $scope['hub_schema'], 'Schemas must be distinct.');
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
    $applications = cutoverRows($pdo, "SELECT id, enabled FROM {$hub}.applications WHERE `key` = 'grant-review'".$lock);
    ensureCutover(count($applications) === 1 && (bool) $applications[0]['enabled'], 'Grant Review registration unavailable.');
    $applicationId = (int) $applications[0]['id'];
    $actors = cutoverRows($pdo, "SELECT id FROM {$hub}.users WHERE id = ? AND status = 'active' AND is_admin = 1".$lock, [$scope['granting_admin_id']]);
    ensureCutover(count($actors) === 1, 'Approved granting administrator unavailable.');
    $prepared = [];
    $seenEmails = [];
    $seenGrantIds = [];
    $seenHubIds = [];
    foreach ($scope['accounts'] as $item) {
        ensureCutover(! isset($seenEmails[$item['email']]) && ! isset($seenGrantIds[$item['grant_id']]), 'Duplicate approved scope.');
        $seenEmails[$item['email']] = true;
        $seenGrantIds[$item['grant_id']] = true;
        $sources = cutoverRows($pdo,
            "SELECT id, email, first_name, last_name, password_hash, sso_sub, role, status
             FROM {$grant}.users WHERE LOWER(TRIM(email)) = ?".$lock, [$item['email']]);
        ensureCutover(count($sources) === 1, 'Source identity is missing or ambiguous.');
        $source = $sources[0];
        ensureCutover((int) $source['id'] === $item['grant_id'] && $source['status'] === 'active'
            && $source['role'] === $item['role'], 'Source scope changed.');
        ensureCutover(password_get_info((string) $source['password_hash'])['algoName'] === 'bcrypt'
            && preg_match('/^\$2[aby]\$(0[4-9]|[12][0-9]|3[01])\$[.\/A-Za-z0-9]{53}$/D', (string) $source['password_hash']) === 1,
            'Legacy hash is not compatible bcrypt.');
        $targets = cutoverRows($pdo,
            "SELECT id, public_id, email, password, status, is_admin, remember_token, email_verified_at, updated_at
             FROM {$hub}.users WHERE LOWER(TRIM(email)) = ? OR id = ?".$lock,
            [$item['email'], $item['hub_id'] ?? 0]);
        $target = null;
        if ($item['hub_id'] === null) {
            ensureCutover($targets === [] && empty($source['sso_sub']), 'New identity now exists or is already linked.');
        } else {
            ensureCutover(count($targets) === 1, 'Existing target ambiguous or missing.');
            $target = $targets[0];
            ensureCutover((int) $target['id'] === $item['hub_id'] && $target['email'] === $item['old_hub_email']
                && $target['status'] === 'active' && filled($source['sso_sub'])
                && hash_equals($target['public_id'], $source['sso_sub']), 'Existing Hub subject/email changed.');
            ensureCutover(! isset($seenHubIds[$target['id']]), 'Duplicate Hub target.');
            $seenHubIds[$target['id']] = true;
        }
        $assignments = $target === null ? [] : cutoverRows($pdo,
            "SELECT id, application_id, user_id, role, granted_by, granted_at, created_at, updated_at
             FROM {$hub}.application_user WHERE user_id = ? ORDER BY application_id".$lock, [$target['id']]);
        if ($target !== null) {
            $grantAssignments = array_filter($assignments, fn (array $assignment): bool => (int) $assignment['application_id'] === $applicationId);
            ensureCutover(count($grantAssignments) === 1 && reset($grantAssignments)['role'] === 'admin', 'Admin assignment changed.');
        }
        $resetTokens = cutoverRows($pdo,
            "SELECT email, token, created_at FROM {$hub}.password_reset_tokens
             WHERE LOWER(TRIM(email)) = ? OR LOWER(TRIM(email)) = ? ORDER BY email".$lock,
            [$item['email'], $item['old_hub_email'] ?? $item['email']]);
        $prepared[] = [
            'scope' => $item,
            'source' => $source,
            'target' => $target,
            'assignments' => $assignments,
            'reset_tokens' => $resetTokens,
        ];
    }
    $state = ['scope' => $scope, 'application_id' => $applicationId, 'prepared' => $prepared];
    if (! $live) {
        saveCutoverFile($checkpointPath, $state);
        $pdo->exec('ROLLBACK');
        $pdo = null;
        echo "DRY RUN: five new submitters, three admin password replacements, one email correction. No database writes.\n";
        exit(0);
    }
    $expected = json_decode(file_get_contents($checkpointPath), true, 512, JSON_THROW_ON_ERROR);
    ensureCutover($expected === $state, 'Data changed since checkpoint; stop and review a new dry run.');
    $result = ['imported' => [], 'updated_admins' => [], 'email_corrected' => []];
    foreach ($prepared as $record) {
        $item = $record['scope'];
        $source = $record['source'];
        $target = $record['target'];
        if ($target === null) {
            $publicId = (string) Str::uuid();
            $name = trim($source['first_name'].' '.$source['last_name']) ?: $item['email'];
            $insert = $pdo->prepare(
                "INSERT INTO {$hub}.users
                 (public_id, name, email, password, status, is_admin, email_verified_at, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 'active', 0, NULL, NOW(), NOW())"
            );
            $insert->execute([$publicId, $name, $item['email'], $source['password_hash']]);
            $hubId = (int) $pdo->lastInsertId();
            $assignment = $pdo->prepare(
                "INSERT INTO {$hub}.application_user
                 (application_id, user_id, role, granted_by, granted_at, created_at, updated_at)
                 VALUES (?, ?, 'submitter', ?, NOW(), NOW(), NOW())"
            );
            $assignment->execute([$applicationId, $hubId, $scope['granting_admin_id']]);
            $result['imported'][] = $item['email'];
        } else {
            $hubId = (int) $target['id'];
            $publicId = $target['public_id'];
            $renamed = $target['email'] !== $item['email'];
            $update = $pdo->prepare(
                "UPDATE {$hub}.users
                 SET email = ?, password = ?, remember_token = ?, email_verified_at = ?, updated_at = NOW()
                 WHERE id = ? AND BINARY public_id = BINARY ?"
            );
            $update->execute([
                $item['email'], $source['password_hash'], Str::random(60),
                $renamed ? null : $target['email_verified_at'], $hubId, $publicId,
            ]);
            ensureCutover($update->rowCount() === 1, 'Admin update affected an unexpected number of rows.');
            $result['updated_admins'][] = $item['email'];
            if ($renamed) {
                $result['email_corrected'][] = $item['email'];
            }
        }
        // Retire outstanding reset links without deleting account/history rows.
        $retire = $pdo->prepare(
            "UPDATE {$hub}.password_reset_tokens SET token = ?
             WHERE LOWER(TRIM(email)) = ? OR LOWER(TRIM(email)) = ?"
        );
        $retire->execute([
            hash('sha256', Str::random(64)), $item['email'], $item['old_hub_email'] ?? $item['email'],
        ]);
        $verified = cutoverRows($pdo,
            "SELECT id, public_id, email, password, status, is_admin
             FROM {$hub}.users WHERE id = ?", [$hubId]);
        ensureCutover(count($verified) === 1
            && $verified[0]['email'] === $item['email']
            && $verified[0]['public_id'] === $publicId
            && $verified[0]['status'] === 'active'
            && hash_equals($source['password_hash'], $verified[0]['password'])
            && (bool) $verified[0]['is_admin'] === (bool) ($target['is_admin'] ?? false),
            'Post-write identity/password verification failed.');
        $verifiedAssignments = cutoverRows($pdo,
            "SELECT id, application_id, user_id, role, granted_by, granted_at, created_at, updated_at
             FROM {$hub}.application_user WHERE user_id = ? ORDER BY application_id", [$hubId]);
        if ($target === null) {
            ensureCutover(count($verifiedAssignments) === 1
                && (int) $verifiedAssignments[0]['application_id'] === $applicationId
                && $verifiedAssignments[0]['role'] === 'submitter', 'Imported assignment verification failed.');
        } else {
            ensureCutover($verifiedAssignments === $record['assignments'], 'Existing assignments were changed.');
        }
        $unchangedSource = cutoverRows($pdo,
            "SELECT id, email, first_name, last_name, password_hash, sso_sub, role, status
             FROM {$grant}.users WHERE id = ?", [$source['id']]);
        ensureCutover(count($unchangedSource) === 1 && $unchangedSource[0] === $source, 'Grant Review source was changed.');
    }
    ensureCutover(count($result['imported']) === 5 && count($result['updated_admins']) === 3
        && count($result['email_corrected']) === 1, 'Batch scope totals do not match approval.');
    $pdo->commit();
    $committed = true;
    echo json_encode([
        'committed' => true,
        'completed_at_utc' => gmdate('c'),
        'results' => $result,
        'verification' => 'All eight Hub hashes match their Grant Review source exactly; existing identities and assignments preserved.',
        'grant_review_changed' => false,
        'emails_sent' => 0,
        'sso_setting_changed' => false,
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $exception) {
    if (! $committed && $pdo !== null && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $reason = get_class($exception) === RuntimeException::class ? $exception->getMessage() : get_class($exception);
    fwrite(STDERR, ($committed ? 'COMMITTED; reporting failed: ' : 'Cutover stopped; transaction not committed: ').$reason.PHP_EOL);
    exit(1);
}
