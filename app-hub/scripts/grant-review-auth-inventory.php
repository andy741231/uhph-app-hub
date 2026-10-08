<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// CLI-only, read-only audit. No hashes, credentials, or tokens are printed.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__.'/../vendor/autoload.php';

try {
    $app = require __DIR__.'/../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $grantRoot = dirname(__DIR__, 2).'/grant-review';
    $grantCache = $grantRoot.'/bootstrap/cache/config.php';
    if (is_file($grantCache)) {
        $grantConfig = require $grantCache;
        $grantDriver = $grantConfig['database']['default'];
        $grantDatabase = $grantConfig['database']['connections'][$grantDriver];
        $grantHub = $grantConfig['hub'];
    } else {
        $grantEnv = Dotenv\Dotenv::createArrayBacked($grantRoot)->load();
        $grantDriver = $grantEnv['DB_CONNECTION'] ?? 'sqlite';
        $grantDatabase = [
            'host' => $grantEnv['DB_HOST'] ?? '127.0.0.1',
            'port' => $grantEnv['DB_PORT'] ?? '3306',
            'database' => $grantEnv['DB_DATABASE'] ?? null,
            'url' => $grantEnv['DB_URL'] ?? null,
            'prefix' => '',
        ];
        $grantHub = [
            'enabled' => filter_var($grantEnv['HUB_SSO_ENABLED'] ?? false, FILTER_VALIDATE_BOOL),
            'client_id' => $grantEnv['HUB_CLIENT_ID'] ?? '',
            'client_secret' => $grantEnv['HUB_CLIENT_SECRET'] ?? '',
            'callback_uri' => $grantEnv['HUB_CALLBACK_URI'] ?? '/apps/grant-review/auth/hub/callback',
        ];
    }

    $connection = DB::connection();
    $hubDatabase = $connection->getConfig();
    if (! in_array($grantDriver, ['mysql', 'mariadb'], true)
        || ! in_array($hubDatabase['driver'], ['mysql', 'mariadb'], true)
        || ! empty($grantDatabase['url'])
        || ! empty($hubDatabase['url'])
        || ! empty($grantDatabase['prefix'])
        || ! empty($hubDatabase['prefix'])
        || (string) $grantDatabase['host'] !== (string) $hubDatabase['host']
        || (string) $grantDatabase['port'] !== (string) $hubDatabase['port']) {
        throw new RuntimeException('Audit requires unprefixed MySQL schemas on the same configured server, without DB_URL overrides.');
    }
    $grantSchema = $grantDatabase['database'];
    $hubSchema = $hubDatabase['database'];
    foreach ([$grantSchema, $hubSchema] as $schema) {
        if (! is_string($schema) || preg_match('/^[A-Za-z0-9_-]+$/', $schema) !== 1) {
            throw new RuntimeException('Invalid configured schema name.');
        }
    }
    if ($grantSchema === $hubSchema) {
        throw new RuntimeException('Grant Review and Hub must have distinct schemas.');
    }

    $pdo = $connection->getPdo();
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->exec('START TRANSACTION READ ONLY');
    try {
        $statement = $pdo->prepare(
            "SELECT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME, DATA_TYPE, IS_NULLABLE
             FROM information_schema.COLUMNS
             WHERE (TABLE_SCHEMA = :grant_schema AND TABLE_NAME = 'users')
                OR (TABLE_SCHEMA = :hub_schema AND TABLE_NAME IN ('users', 'applications', 'application_user'))
             ORDER BY TABLE_SCHEMA, TABLE_NAME, ORDINAL_POSITION"
        );
        $statement->execute(['grant_schema' => $grantSchema, 'hub_schema' => $hubSchema]);
        $schemaRows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $output = [
            'read_only' => true,
            'grant_schema' => $grantSchema,
            'hub_schema' => $hubSchema,
            'columns' => $schemaRows,
        ];
        if (in_array('--accounts', $argv, true)) {
            $required = [
                $grantSchema.'.users' => ['id', 'email', 'password_hash', 'role', 'status', 'sso_sub'],
                $hubSchema.'.users' => ['id', 'email', 'password', 'public_id', 'status'],
                $hubSchema.'.applications' => ['id', 'key', 'enabled', 'callback_url', 'frontchannel_logout_path', 'client_id', 'client_secret_hash'],
                $hubSchema.'.application_user' => ['id', 'user_id', 'application_id', 'role'],
            ];
            foreach ($schemaRows as $column) {
                $table = $column['TABLE_SCHEMA'].'.'.$column['TABLE_NAME'];
                if (isset($required[$table])) {
                    $required[$table] = array_diff($required[$table], [$column['COLUMN_NAME']]);
                }
            }
            if (array_filter($required) !== []) {
                throw new RuntimeException('Schema does not match reviewed inventory queries.');
            }
            $grant = '`'.$grantSchema.'`';
            $hub = '`'.$hubSchema.'`';
            $registration = $pdo->prepare(
                "SELECT id, enabled, callback_url, frontchannel_logout_path,
                    (BINARY client_id = BINARY :client_id
                        AND BINARY client_secret_hash = BINARY :secret_hash) AS credentials_match
                 FROM {$hub}.applications WHERE `key` = 'grant-review'"
            );
            $registration->execute([
                'client_id' => $grantHub['client_id'],
                'secret_hash' => hash('sha256', (string) $grantHub['client_secret']),
            ]);
            $applications = $registration->fetchAll(PDO::FETCH_ASSOC);
            if (count($applications) !== 1) {
                throw new RuntimeException('Expected exactly one Grant Review application registration.');
            }
            $application = $applications[0];
            $query = $pdo->prepare(
                "SELECT gr.id AS grant_id, LOWER(TRIM(gr.email)) AS email,
                    gr.role AS grant_role, gr.status AS grant_status,
                    h.id AS hub_id, h.status AS hub_status,
                    (gr.password_hash IS NOT NULL AND gr.password_hash <> '') AS grant_has_password,
                    (COALESCE(gr.password_hash, '') REGEXP :bcrypt_pattern) AS grant_bcrypt_format,
                    (h.password IS NOT NULL AND h.password <> '') AS hub_has_password,
                    (BINARY gr.password_hash = BINARY h.password) AS identical_hash,
                    (SELECT COUNT(*) FROM {$grant}.users g2
                        WHERE LOWER(TRIM(g2.email)) = LOWER(TRIM(gr.email))) AS grant_email_matches,
                    (SELECT COUNT(*) FROM {$hub}.users h2
                        WHERE LOWER(TRIM(h2.email)) = LOWER(TRIM(gr.email))) AS hub_email_matches,
                    (SELECT COUNT(*) FROM {$hub}.users hs
                        WHERE BINARY hs.public_id = BINARY gr.sso_sub) AS subject_matches,
                    (gr.sso_sub IS NOT NULL AND gr.sso_sub <> ''
                        AND (h.id IS NULL OR BINARY gr.sso_sub <> BINARY h.public_id)) AS subject_conflict,
                    au.id AS assignment_id, au.role AS hub_role
                 FROM {$grant}.users gr
                 LEFT JOIN {$hub}.users h ON LOWER(TRIM(h.email)) = LOWER(TRIM(gr.email))
                 LEFT JOIN {$hub}.application_user au ON au.user_id = h.id
                    AND au.application_id = :application_id
                 ORDER BY LOWER(TRIM(gr.email)), gr.id, h.id"
            );
            $query->execute([
                'bcrypt_pattern' => '^[$]2[aby][$](0[4-9]|[12][0-9]|3[01])[$][./A-Za-z0-9]{53}$',
                'application_id' => $application['id'],
            ]);
            $rows = $query->fetchAll(PDO::FETCH_ASSOC);
            $sourceCount = (int) $pdo->query("SELECT COUNT(*) FROM {$grant}.users")->fetchColumn();
            if (count($rows) !== $sourceCount) {
                throw new RuntimeException('Ambiguous join: normalized emails or assignments are duplicated.');
            }
            $totals = [];
            foreach ($rows as &$row) {
                $issues = [];
                if ((int) $row['grant_email_matches'] !== 1 || (int) $row['hub_email_matches'] > 1
                    || (int) $row['subject_matches'] > 1 || (bool) $row['subject_conflict']) {
                    $issues[] = 'identity_conflict';
                }
                if ($row['grant_status'] !== 'active' || ($row['hub_id'] !== null && $row['hub_status'] !== 'active')) {
                    $issues[] = 'inactive_account_hold';
                }
                if ($row['assignment_id'] === null) {
                    $issues[] = 'missing_assignment_review_required';
                } elseif ($row['grant_role'] !== $row['hub_role']) {
                    $issues[] = 'role_mismatch';
                }
                $category = match (true) {
                    in_array('identity_conflict', $issues, true) => 'identity_conflict',
                    $row['hub_id'] === null => $row['grant_bcrypt_format'] ? 'missing_hub_identity_with_legacy_hash' : 'missing_hub_identity_without_compatible_hash',
                    (bool) $row['hub_has_password'] && (bool) $row['grant_has_password'] => $row['identical_hash'] ? 'identical_hashes' : 'both_hashes_present_equivalence_unknown',
                    (bool) $row['hub_has_password'] => 'hub_password_only',
                    (bool) $row['grant_bcrypt_format'] => 'hub_password_missing_legacy_hash_available',
                    (bool) $row['grant_has_password'] => 'legacy_hash_format_unsupported',
                    default => 'no_password_in_either_store',
                };
                $row['category'] = $category;
                $row['issues'] = $issues;
                $totals[$category] = ($totals[$category] ?? 0) + 1;
            }
            unset($row);
            $assignmentQuery = $pdo->prepare(
                "SELECT h.id AS hub_id, LOWER(TRIM(h.email)) AS email, h.status, au.role,
                    (SELECT COUNT(*) FROM {$grant}.users gr
                        WHERE LOWER(TRIM(gr.email)) = LOWER(TRIM(h.email))) AS profiles_by_email,
                    (SELECT COUNT(*) FROM {$grant}.users gr
                        WHERE BINARY gr.sso_sub = BINARY h.public_id) AS profiles_by_subject
                 FROM {$hub}.application_user au
                 JOIN {$hub}.users h ON h.id = au.user_id
                 WHERE au.application_id = :application_id ORDER BY h.email"
            );
            $assignmentQuery->execute(['application_id' => $application['id']]);
            $output = [
                'read_only' => true,
                'captured_at_utc' => gmdate('c'),
                'grant_schema' => $grantSchema,
                'hub_schema' => $hubSchema,
                'hub_login_mode' => config('hub.login_mode'),
                'grant_hub_integration_enabled' => (bool) $grantHub['enabled'],
                'registration' => $application,
                'configured_callback_matches' => $grantHub['callback_uri'] === $application['callback_url'],
                'grant_user_count' => $sourceCount,
                'category_counts' => $totals,
                'accounts' => $rows,
                'hub_grant_review_assignments' => $assignmentQuery->fetchAll(PDO::FETCH_ASSOC),
                'note' => 'Only flags are returned. Different salted hashes do not prove different passwords. Bcrypt format eligibility is not a plaintext-password verification or migration approval.',
            ];
        }
        if (in_array('--subject-review', $argv, true)) {
            $grant = '`'.$grantSchema.'`';
            $hub = '`'.$hubSchema.'`';
            $review = $pdo->query(
                "SELECT gr.id AS grant_id, LOWER(TRIM(gr.email)) AS grant_email,
                    h.id AS hub_id, LOWER(TRIM(h.email)) AS hub_email,
                    gr.status AS grant_status, h.status AS hub_status,
                    (h.password IS NOT NULL AND h.password <> '') AS hub_has_password,
                    (BINARY gr.password_hash = BINARY h.password) AS identical_hash,
                    gr.role AS grant_role, au.role AS hub_role
                 FROM {$grant}.users gr
                 JOIN {$hub}.users h ON BINARY h.public_id = BINARY gr.sso_sub
                 LEFT JOIN {$hub}.applications a ON a.`key` = 'grant-review'
                 LEFT JOIN {$hub}.application_user au ON au.application_id = a.id AND au.user_id = h.id
                 WHERE LOWER(TRIM(gr.email)) <> LOWER(TRIM(h.email))
                 ORDER BY gr.id, h.id"
            );
            $output = [
                'read_only' => true,
                'captured_at_utc' => gmdate('c'),
                'subject_email_mismatches' => $review->fetchAll(PDO::FETCH_ASSOC),
            ];
        }
        echo json_encode($output, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
    } finally {
        $pdo->exec('ROLLBACK');
    }
} catch (Throwable $exception) {
    // Connection exceptions can contain credentials: never print their message.
    fwrite(STDERR, 'Read-only inventory stopped ('.get_class($exception).'). No database writes were requested.'.PHP_EOL);
    exit(1);
}
