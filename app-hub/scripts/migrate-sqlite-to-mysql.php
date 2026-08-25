<?php

/**
 * One-shot migration: copies operational data from the legacy SQLite database
 * (database/database.sqlite) into the configured MySQL database.
 *
 * Run from the app-hub root:
 *   php scripts/migrate-sqlite-to-mysql.php [--dry-run]
 *
 * What is migrated:
 *   - applications  (replaces seeded rows — preserves the original client_id /
 *                    client_secret_hash so SSO credentials in app .env files
 *                    remain valid)
 *   - users         (replaces any rows created by hub:create-admin — preserves
 *                    the original password hashes, public_id, timestamps)
 *   - application_user (role assignments)
 *   - login_audits
 *   - application_launch_audits
 *
 * What is NOT migrated (ephemeral / expired / empty):
 *   - authorization_codes  (single-use, 60s expiry — all stale)
 *   - sessions, cache, cache_locks
 *   - jobs, job_batches, failed_jobs
 *   - password_reset_tokens
 *   - migrations (already tracked by the fresh MySQL run)
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$dryRun = in_array('--dry-run', $argv, true);

$sqlitePath = database_path('database.sqlite');
if (! file_exists($sqlitePath)) {
    fwrite(STDERR, "SQLite file not found: $sqlitePath\n");
    exit(1);
}

$src = new PDO('sqlite:' . $sqlitePath);
$src->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$dst = DB::connection()->getPdo();
$dst->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo 'Source: SQLite  ' . $sqlitePath . PHP_EOL;
echo 'Dest:   ' . DB::connection()->getDriverName() . '  ' . DB::connection()->getDatabaseName() . PHP_EOL;
echo $dryRun ? "Mode:   DRY RUN (no writes)\n" : "Mode:   LIVE\n";
echo str_repeat('-', 60) . PHP_EOL;

/**
 * Read all rows from a SQLite table.
 */
function allRows(PDO $src, string $table): array {
    $cols = [];
    foreach ($src->query("PRAGMA table_info(\"$table\")")->fetchAll(PDO::FETCH_OBJ) as $c) {
        $cols[] = $c->name;
    }
    $quotedCols = implode(', ', array_map(fn($c) => "\"$c\"", $cols));
    $rows = $src->query("SELECT $quotedCols FROM \"$table\"")->fetchAll(PDO::FETCH_ASSOC);
    return [$cols, $rows];
}

/**
 * Insert rows into a MySQL table, preserving column names.
 */
function insertRows(PDO $dst, string $table, array $cols, array $rows, bool $dryRun): int {
    if (empty($rows)) {
        echo "  $table: 0 rows (skip)\n";
        return 0;
    }
    $colList = implode(', ', array_map(fn($c) => "`$c`", $cols));
    $placeholders = implode(', ', array_fill(0, count($cols), '?'));
    $sql = "INSERT INTO `$table` ($colList) VALUES ($placeholders)";
    $stmt = $dryRun ? null : $dst->prepare($sql);
    $count = 0;
    foreach ($rows as $row) {
        if ($dryRun) {
            echo "    would insert: " . json_encode(array_combine($cols, array_values($row))) . PHP_EOL;
        } else {
            $stmt->execute(array_values($row));
        }
        $count++;
    }
    echo "  $table: $count row(s) " . ($dryRun ? '(dry-run)' : 'inserted') . "\n";
    return $count;
}

// Tables to migrate, in dependency order.
$migrate = [
    'applications',
    'users',
    'application_user',
    'login_audits',
    'application_launch_audits',
];

if (! $dryRun) {
    // Wipe destination tables (children first, then parents) so the import is idempotent.
    $truncate = [
        'application_launch_audits',
        'application_user',
        'login_audits',
        'authorization_codes', // clear any seeded-era codes too
        'users',
        'applications',
    ];
    $dst->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($truncate as $t) {
        $dst->exec("TRUNCATE TABLE `$t`");
        echo "  truncated $t\n";
    }
    $dst->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo str_repeat('-', 60) . PHP_EOL;
}

foreach ($migrate as $table) {
    [$cols, $rows] = allRows($src, $table);
    insertRows($dst, $table, $cols, $rows, $dryRun);
}

echo str_repeat('-', 60) . PHP_EOL;
echo "Done.\n";
