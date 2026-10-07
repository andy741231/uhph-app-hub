<?php

namespace App\Services;

class FlagScanner
{
    /**
     * Scan text for occurrences of the given flag words.
     *
     * Matching is case-insensitive. Single-word flag words use a leading Unicode
     * word boundary (\b) with the "u" pattern modifier, allowing stem/prefix
     * matching (e.g. "divers" matches "diverse", "diversity").
     *
     * @param  array<string>  $flagWords  List of raw flag words
     * @return array{
     *     total:int,
     *     words:array<string,int>,
     *     words_list:array<int,array{word:string,occurrences:int}>
     * }
     */
    public function scan(string $text, array $flagWords): array
    {
        $words = [];
        $total = 0;

        // De-duplicate and ignore empty entries
        $unique = array_values(array_unique(array_filter(array_map('trim', $flagWords), fn ($w) => $w !== '')));

        foreach ($unique as $word) {
            $count = $this->countOccurrences($text, $word);
            if ($count > 0) {
                $words[$word] = $count;
                $total += $count;
            }
        }

        // Build a stable, sorted list for UI consumption
        $wordsList = [];
        foreach ($words as $word => $occurrences) {
            $wordsList[] = ['word' => $word, 'occurrences' => $occurrences];
        }
        usort($wordsList, fn ($a, $b) => $b['occurrences'] <=> $a['occurrences'] ?: strcmp($a['word'], $b['word']));

        return [
            'total' => $total,
            'words' => $words,
            'words_list' => $wordsList,
        ];
    }

    /**
     * Count case-insensitive occurrences of $needle in $haystack.
     *
     * Single-word needles use a leading Unicode word boundary but NO trailing
     * boundary, so stem/prefix flag words (e.g. "divers", "equit", "minorit")
     * match their derivatives ("diverse", "diversity", "equity", "minority").
     * Multi-word phrases are matched as-is (no word boundaries).
     */
    protected function countOccurrences(string $haystack, string $needle): int
    {
        if ($needle === '') {
            return 0;
        }

        // Escape regex meta-characters in the needle
        $pattern = '/'.preg_quote($needle, '/').'/ui';

        // Add a leading Unicode word boundary when the needle starts with a word
        // character. The trailing boundary is intentionally omitted so that stem
        // words match their derivatives (e.g. "divers" → "diverse", "diversity").
        if (preg_match('/^\p{L}[\p{L}\p{N}_]*$/u', $needle)) {
            $pattern = '/\b'.preg_quote($needle, '/').'/ui';
        }

        return preg_match_all($pattern, $haystack);
    }

    /**
     * Build a regex alternation pattern that matches any of the flag words (case-insensitive).
     *
     * Single-word flag words get a leading Unicode word boundary (but no trailing
     * boundary) so stem words match their derivatives, consistent with countOccurrences.
     * Returns null when no flag words are provided.
     */
    public function buildHighlightPattern(array $flagWords): ?string
    {
        $unique = array_values(array_unique(array_filter(array_map('trim', $flagWords), fn ($w) => $w !== '')));
        if (empty($unique)) {
            return null;
        }

        // Sort longest-first so longer phrases take precedence over their substrings
        usort($unique, fn ($a, $b) => strlen($b) <=> strlen($a));

        $parts = array_map(function ($w) {
            $quoted = preg_quote($w, '/');
            // Add a leading word boundary for single-word needles (stem/prefix matching)
            if (preg_match('/^\p{L}[\p{L}\p{N}_]*$/u', $w)) {
                return '\b'.$quoted;
            }

            return $quoted;
        }, $unique);

        return '/('.implode('|', $parts).')/ui';
    }

    /**
     * Scan text per page and return per-page flag word results.
     *
     * @param  array<int, string>  $pages  Array of text per page (0-indexed)
     * @param  array<string>  $flagWords
     * @return array{
     *     total:int,
     *     pages:array<int,array{page:int,words:array<int,array{word:string,occurrences:int}>}>
     * }
     */
    public function scanPerPage(array $pages, array $flagWords): array
    {
        $total = 0;
        $pagesResult = [];

        foreach ($pages as $index => $pageText) {
            $scan = $this->scan($pageText, $flagWords);
            if ($scan['total'] > 0) {
                $total += $scan['total'];
                $pagesResult[] = [
                    'page' => $index + 1,
                    'words' => $scan['words_list'],
                ];
            }
        }

        return [
            'total' => $total,
            'pages' => $pagesResult,
        ];
    }
}
