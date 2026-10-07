<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ImportFlagWords extends Command
{
    protected $signature = 'flag-words:import {file : JSON export from the legacy app} {--apply : Commit the import; otherwise dry run}';

    protected $description = 'Import only flag words and suggested replacements, without legacy users or documents';

    public function handle(): int
    {
        try {
            $path = $this->argument('file');
            if (! is_file($path) || ! is_readable($path)) {
                throw new \RuntimeException('The export file is not readable.');
            }

            $payload = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $validator = Validator::make(['payload' => $payload], [
                'payload' => ['required', 'array:version,flag_words'],
                'payload.version' => ['required', 'integer', 'in:1'],
                'payload.flag_words' => ['present', 'array', 'max:100000'],
                'payload.flag_words.*' => ['required', 'array:word,suggested_replacement'],
                'payload.flag_words.*.word' => ['required', 'string', 'max:255'],
                'payload.flag_words.*.suggested_replacement' => ['present', 'nullable', 'string', 'max:255'],
            ]);
            if ($validator->fails()) {
                throw new \RuntimeException($validator->errors()->first());
            }

            $rows = $payload['flag_words'];
            $seen = [];
            foreach ($rows as &$row) {
                $row['word'] = trim($row['word']);
                $key = mb_strtolower($row['word']);
                if ($key === '' || isset($seen[$key])) {
                    throw new \RuntimeException('Export contains an empty or duplicate case-insensitive flag word.');
                }
                $seen[$key] = true;
            }
            unset($row);

            // Validate the whole export before touching the target; dry runs never write.
            $counts = DB::transaction(function () use ($rows): array {
                $created = 0;
                $updated = 0;
                $unchanged = 0;
                // Normalize in PHP: SQLite LOWER() does not fold Unicode like MySQL.
                $targetWords = DB::table('document_flag_words')
                    ->lockForUpdate()
                    ->get(['id', 'word', 'suggested_replacement'])
                    ->groupBy(fn ($row) => mb_strtolower($row->word));
                foreach ($rows as $row) {
                    $matches = $targetWords->get(mb_strtolower($row['word']), collect());
                    if ($matches->count() > 1) {
                        throw new \RuntimeException('Target contains duplicate case-insensitive flag words.');
                    }
                    $existing = $matches->first();
                    if (! $existing) {
                        $created++;
                        if ($this->option('apply')) {
                            DB::table('document_flag_words')->insert($row + [
                                'created_by' => null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    } elseif ($existing->word !== $row['word']
                        || $existing->suggested_replacement !== $row['suggested_replacement']) {
                        $updated++;
                        if ($this->option('apply')) {
                            DB::table('document_flag_words')->where('id', $existing->id)
                                ->update($row + ['updated_at' => now()]);
                        }
                    } else {
                        $unchanged++;
                    }
                }

                return compact('created', 'updated', 'unchanged');
            });

            $this->info(($this->option('apply') ? 'Applied' : 'Dry run (no changes saved)')
                .": {$counts['created']} new, {$counts['updated']} updated, {$counts['unchanged']} unchanged.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
