<?php
/**
 * Idempotent runner for sql/migrations/2026-10-07-ownership-visibility.sql.
 *
 *   php scripts/migrate-ownership.php            # dry run (default)
 *   php scripts/migrate-ownership.php --apply    # apply to the configured DB
 *
 * CLI only. The ALTER runs only when flipbooks.owner_subject is absent
 * (checked via INFORMATION_SCHEMA); the guarded backfill UPDATE always runs.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

require_once __DIR__ . '/../config.php';

$apply = in_array('--apply', $argv ?? [], true);
$dryRun = !$apply;

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

// Extract the two statements (ALTER, UPDATE) from the authored SQL file.
$sqlFile = __DIR__ . '/../sql/migrations/2026-10-07-ownership-visibility.sql';
$raw = file_get_contents($sqlFile);
if ($raw === false) {
    fwrite(STDERR, "Cannot read migration file: {$sqlFile}\n");
    exit(1);
}
$lines = array_filter(
    array_map('trim', explode("\n", $raw)),
    fn (string $l) => $l !== '' && !str_starts_with($l, '--')
);
$statements = array_values(array_filter(
    array_map('trim', explode(';', implode(' ', $lines))),
    fn (string $s) => $s !== ''
));
if (count($statements) !== 2) {
    fwrite(STDERR, "Unexpected statement count in migration file.\n");
    exit(1);
}
[$alter, $backfill] = $statements;

// Check whether the column already exists.
$colStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'flipbooks' AND COLUMN_NAME = 'owner_subject'"
);
$colStmt->execute([DB_NAME]);
$hasOwnerColumn = (int)$colStmt->fetchColumn() > 0;

echo $dryRun ? "== DRY RUN (pass --apply to execute) ==\n" : "== APPLY ==\n";
echo 'flipbooks.owner_subject present: ' . ($hasOwnerColumn ? 'yes' : 'no') . "\n";
echo 'ALTER TABLE needed: ' . ($hasOwnerColumn ? 'no (skipped)' : 'yes') . "\n";

if ($hasOwnerColumn) {
    $pending = (int)$pdo->query("SELECT COUNT(*) FROM flipbooks WHERE owner_subject IS NULL")->fetchColumn();
    echo "Rows pending backfill: {$pending}\n";
    echo "Current rows:\n";
    foreach ($pdo->query('SELECT id, title, owner_subject, visibility FROM flipbooks') as $row) {
        echo '  ' . json_encode($row, JSON_UNESCAPED_SLASHES) . "\n";
    }
} else {
    $total = (int)$pdo->query('SELECT COUNT(*) FROM flipbooks')->fetchColumn();
    echo "Rows to be backfilled after ALTER: {$total}\n";
}

if ($dryRun) {
    echo "Dry run complete — no changes made.\n";
    exit(0);
}

if (!$hasOwnerColumn) {
    $pdo->exec($alter);
    echo "ALTER TABLE applied.\n";
}
$affected = $pdo->exec($backfill);
echo "Backfill complete; rows updated: {$affected}.\n";
