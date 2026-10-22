<?php

namespace App\Services;

use App\Models\DocumentFlagWord;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FlagWordImporter
{
    /**
     * Parse a CSV file and import its rows.
     *
     * Expected columns: "word" (required) and "suggested_replacement"
     * (optional). A header row is detected when the first cell is "word";
     * otherwise the first two columns are used.
     *
     * @return array{imported:int, skipped:array<int,array{word:string,reason:string,covered_by:?string}>}
     */
    public function importCsv(string $path, ?int $userId): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('The uploaded file could not be read.');
        }

        try {
            $offset = 0;
            if (fread($handle, 3) === "\xEF\xBB\xBF") {
                $offset = 3; // skip a UTF-8 BOM
            }

            // Sniff the delimiter: Excel exports use ';' in some locales.
            $firstLine = fgets($handle);
            $delimiter = is_string($firstLine)
                && substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            fseek($handle, $offset);

            $rows = $this->parseRows($handle, $delimiter);
        } finally {
            fclose($handle);
        }

        return $this->importRows($rows, $userId);
    }

    /**
     * Import rows of ['word' => ..., 'suggested_replacement' => ...].
     *
     * Skip rules:
     *  - the same word (case-insensitive) already exists, in the database or
     *    earlier in the file → "exists";
     *  - a single stem word is covered by a shorter flag word that prefixes it
     *    (e.g. "diverse" is covered when "divers" exists) → "covered";
     *  - the word or replacement exceeds 255 characters → "invalid".
     *
     * Multi-word phrases are never covered: "advance diversity" imports even
     * when "divers" exists. Coverage is evaluated against database words plus
     * stem words in the file itself, so precedence does not depend on row
     * order — "divers" beats "diversity" wherever it appears.
     *
     * @param  array<int, array{word:mixed, suggested_replacement:mixed}>  $rows
     * @return array{imported:int, skipped:array<int,array{word:string,reason:string,covered_by:?string}>}
     */
    public function importRows(array $rows, ?int $userId): array
    {
        return DB::transaction(function () use ($rows, $userId) {
            $known = [];
            $rootsByFirstChar = [];

            foreach (DocumentFlagWord::query()->lockForUpdate()->pluck('word') as $existing) {
                $this->indexWord($existing, $known, $rootsByFirstChar);
            }

            // Stem words in the file that are not themselves covered act as
            // roots too, regardless of where they appear relative to the words
            // they cover.
            $fileCandidates = [];
            foreach ($rows as $row) {
                $word = trim((string) ($row['word'] ?? ''));
                if ($word === '' || mb_strlen($word) > 255 || ! FlagScanner::isStemWord($word)) {
                    continue;
                }
                $fileCandidates[mb_strtolower($word)] ??= $word;
            }
            // Shortest first: a candidate survives only when no shorter root
            // covers it.
            uasort($fileCandidates, fn ($a, $b) => mb_strlen($a) <=> mb_strlen($b));
            foreach ($fileCandidates as $lower => $word) {
                if ($this->coveringRoot($lower, $rootsByFirstChar) === null) {
                    $this->indexRoot($word, $rootsByFirstChar);
                }
            }

            $imported = 0;
            $skipped = [];

            foreach ($rows as $row) {
                $word = trim((string) ($row['word'] ?? ''));
                if ($word === '') {
                    continue;
                }

                $replacement = trim((string) ($row['suggested_replacement'] ?? '')) ?: null;

                if (mb_strlen($word) > 255 || ($replacement !== null && mb_strlen($replacement) > 255)) {
                    $skipped[] = ['word' => $word, 'reason' => 'invalid', 'covered_by' => null];

                    continue;
                }

                $lower = mb_strtolower($word);

                if (isset($known[$lower])) {
                    $skipped[] = ['word' => $word, 'reason' => 'exists', 'covered_by' => null];

                    continue;
                }

                if (FlagScanner::isStemWord($word)
                    && ($root = $this->coveringRoot($lower, $rootsByFirstChar)) !== null) {
                    $skipped[] = ['word' => $word, 'reason' => 'covered', 'covered_by' => $root];

                    continue;
                }

                DocumentFlagWord::create([
                    'word' => $word,
                    'suggested_replacement' => $replacement,
                    'created_by' => $userId,
                ]);
                $known[$lower] = true;
                $imported++;
            }

            return ['imported' => $imported, 'skipped' => $skipped];
        });
    }

    /**
     * Read normalized ['word', 'suggested_replacement'] rows from an open CSV
     * handle. The first non-empty row is treated as a header when its first
     * cell is "word".
     *
     * @param  resource  $handle
     * @return array<int, array{word:string, suggested_replacement:string|null}>
     */
    protected function parseRows($handle, string $delimiter): array
    {
        $rows = [];
        $wordIdx = 0;
        $replacementIdx = 1;
        $headerChecked = false;

        while (($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $cells = array_map(fn ($c) => (string) $c, $cells);
            if (count(array_filter($cells, fn ($c) => trim($c) !== '')) === 0) {
                continue;
            }

            if (! $headerChecked) {
                $headerChecked = true;
                $map = array_map(fn ($c) => mb_strtolower(trim($c)), $cells);
                if (($map[0] ?? '') === 'word') {
                    $wordIdx = (int) array_search('word', $map, true);
                    $replacementIdx = array_search('suggested_replacement', $map, true);
                    if ($replacementIdx === false) {
                        $replacementIdx = array_search('replacement', $map, true);
                    }

                    continue;
                }
            }

            $rows[] = [
                'word' => $cells[$wordIdx] ?? '',
                'suggested_replacement' => $replacementIdx === false ? null : ($cells[$replacementIdx] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * Register a word in the exact-match set and, when it is a stem word, in
     * the root index (grouped by first lowercase character).
     */
    protected function indexWord(string $word, array &$known, array &$rootsByFirstChar): void
    {
        $known[mb_strtolower($word)] = true;
        $this->indexRoot($word, $rootsByFirstChar);
    }

    protected function indexRoot(string $word, array &$rootsByFirstChar): void
    {
        if (! FlagScanner::isStemWord($word)) {
            return;
        }

        $lower = mb_strtolower($word);
        $rootsByFirstChar[mb_substr($lower, 0, 1)][] = [$lower, $word];
    }

    /**
     * Return the original casing of the longest root that is a strict prefix
     * of the given lowercase word, or null when no root covers it.
     */
    protected function coveringRoot(string $lowerWord, array $rootsByFirstChar): ?string
    {
        $best = null;

        foreach ($rootsByFirstChar[mb_substr($lowerWord, 0, 1)] ?? [] as [$rootLower, $rootOriginal]) {
            if (mb_strlen($rootLower) >= mb_strlen($lowerWord) || ! str_starts_with($lowerWord, $rootLower)) {
                continue;
            }
            if ($best === null || mb_strlen($rootLower) > mb_strlen($best[0])) {
                $best = [$rootLower, $rootOriginal];
            }
        }

        return $best[1] ?? null;
    }
}
