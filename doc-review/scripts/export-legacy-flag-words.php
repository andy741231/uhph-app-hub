<?php

// Run on the legacy server. This only reads two columns and writes JSON to stdout.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

if ($argc !== 2 || ! is_file(rtrim($argv[1], '/\\').'/artisan')) {
    fwrite(STDERR, "Usage: php export-legacy-flag-words.php <legacy-laravel-root>\n");
    exit(1);
}

$root = realpath($argv[1]);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $rows = DB::table('document_flag_words')
        ->orderBy('id')
        ->get(['word', 'suggested_replacement'])
        ->map(fn ($row) => [
            'word' => $row->word,
            'suggested_replacement' => $row->suggested_replacement,
        ])->all();
    echo json_encode(['version' => 1, 'flag_words' => $rows], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Export failed; no source data was changed.\n");
    exit(1);
}
