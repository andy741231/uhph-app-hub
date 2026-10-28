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
     * Expected columns: "word" (required), "suggested_replacement"
     * (optional) and "match_type" (optional). A header row is detected
     * when the first cell is "word"; otherwise columns 1-3 are used.
     * A missing/blank match_type means whole_word.
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
     *  - a single stem word is covered by a shorter word_root flag word that
     *    prefixes it (e.g. "diverse" is covered when "divers" exists with
     *    word_root) → "covered"; whole_word entries never cover anything;
     *  - the word or replacement exceeds 255 characters, or the match_type
     *    is not blank/whole_word/word_root → "invalid".
     *
     * Multi-word phrases are never covered: "advance diversity" imports even
     * when "divers" exists. Coverage is evaluated against database words plus
     * stem words in the file itself, so precedence does not depend on row
     * order — "divers" beats "diversity" wherever it appears.
     *
     * @param  array<int, array{word:mixed, suggested_replacement:mixed, match_type?:mixed}>  $rows
     * @return array{imported:int, skipped:array<int,array{word:string,reason:string,covered_by:?string}>}
     */
    public function importRows(array $rows, ?int $userId): array
    {
        return DB::transaction(function () use ($rows, $userId) {
            $known = [];
            $rootsByFirstChar = [];

            foreach (DocumentFlagWord::query()->lockForUpdate()->get(['word', 'match_type']) as $existing) {
                $known[mb_strtolower($existing->word)] = true;
                // Only word_root single words cover other single words —
                // a whole_word entry like "ai" never covers "aim".
                if ($existing->match_type === DocumentFlagWord::MATCH_WORD_ROOT) {
                    $this->indexRoot($existing->word, $rootsByFirstChar);
                }
            }

            // The first VALID row for each exact word decides its mode:
            // invalid rows (bad type, overlong fields) establish nothing,
            // and words already in the DB can never become file roots.
            $fileCandidates = [];
            foreach ($rows as $row) {
                $word = trim((string) ($row['word'] ?? ''));
                if ($word === '') {
                    continue;
                }
                $lower = mb_strtolower($word);
                if (array_key_exists($lower, $fileCandidates) || isset($known[$lower])) {
                    continue;
                }
                $matchType = $this->normalizeMatchType($row['match_type'] ?? null);
                $replacement = trim((string) ($row['suggested_replacement'] ?? '')) ?: null;
                if ($matchType === null
                    || mb_strlen($word) > 255
                    || ($replacement !== null && mb_strlen($replacement) > 255)
                    || ! FlagScanner::isStemWord($word)) {
                    continue;
                }
                if ($matchType === DocumentFlagWord::MATCH_WORD_ROOT) {
                    $fileCandidates[$lower] = $word;
                } else {
                    // A first-valid whole_word row blocks a later word_root
                    // row for the same word from phantom-covering others.
                    $fileCandidates[$lower] = null;
                }
            }
            // Shortest first: a candidate survives only when no shorter root
            // covers it.
            uasort($fileCandidates, fn ($a, $b) => mb_strlen((string) $a) <=> mb_strlen((string) $b));
            foreach ($fileCandidates as $lower => $word) {
                if ($word !== null && $this->coveringRoot($lower, $rootsByFirstChar) === null) {
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
                $matchType = $this->normalizeMatchType($row['match_type'] ?? null);

                if (mb_strlen($word) > 255 || ($replacement !== null && mb_strlen($replacement) > 255) || $matchType === null) {
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
                    'match_type' => $matchType,
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
        $matchTypeIdx = 2;
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
                    $matchTypeIdx = array_search('match_type', $map, true);
                    if ($matchTypeIdx === false) {
                        $matchTypeIdx = array_search('type', $map, true);
                    }

                    continue;
                }
            }

            $rows[] = [
                'word' => $cells[$wordIdx] ?? '',
                'suggested_replacement' => $replacementIdx === false ? null : ($cells[$replacementIdx] ?? ''),
                'match_type' => $matchTypeIdx === false ? null : ($cells[$matchTypeIdx] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * Normalize a CSV match_type value: blank means whole_word (the safer
     * new-entry default); valid machine names are returned lowercased; any
     * other value is invalid (null).
     */
    protected function normalizeMatchType($raw): ?string
    {
        $value = mb_strtolower(trim((string) ($raw ?? '')));

        if ($value === '') {
            return DocumentFlagWord::MATCH_WHOLE_WORD;
        }

        return in_array($value, DocumentFlagWord::MATCH_TYPES, true) ? $value : null;
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
