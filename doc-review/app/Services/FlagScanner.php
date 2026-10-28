<?php

namespace App\Services;

use App\Models\DocumentFlagWord;

class FlagScanner
{
    /**
     * Scan text for occurrences of the given flag words.
     *
     * Matching is case-insensitive. Each flag word's match type comes from
     * $matchTypes (raw word => 'whole_word'|'word_root'); a missing entry
     * means 'word_root' to preserve the standalone API's legacy semantics.
     * whole_word adds a trailing Unicode word boundary; word_root keeps the
     * leading boundary only, allowing stem/prefix matches ("divers" matches
     * "diverse", "diversity"). Multi-word phrases match literally either way.
     *
     * @param  array<string>  $flagWords  List of raw flag words
     * @param  array<string, string>  $matchTypes  Raw word => match type
     * @return array{
     *     total:int,
     *     words:array<string,int>,
     *     words_list:array<int,array{word:string,occurrences:int}>
     * }
     */
    public function scan(string $text, array $flagWords, array $matchTypes = []): array
    {
        $words = [];
        $total = 0;

        // De-duplicate and ignore empty entries
        $unique = array_values(array_unique(array_filter(array_map('trim', $flagWords), fn ($w) => $w !== '')));

        foreach ($unique as $word) {
            $count = $this->countOccurrences($text, $word, $matchTypes[$word] ?? null);
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
     * Whether the flag word is a single "stem" word eligible for prefix
     * matching: letters/numbers/underscore only, starting with a letter.
     * Anything else (phrases, punctuation) is matched literally.
     */
    public static function isStemWord(string $word): bool
    {
        return (bool) preg_match('/^\p{L}[\p{L}\p{N}_]*$/u', $word);
    }

    /**
     * Count case-insensitive occurrences of $needle in $haystack under the
     * given match type (null/missing means word_root).
     */
    protected function countOccurrences(string $haystack, string $needle, ?string $matchType): int
    {
        if ($needle === '') {
            return 0;
        }

        $pattern = '/'.$this->patternPart($needle, $matchType).'/ui';

        return preg_match_all($pattern, $haystack);
    }

    /**
     * Regex fragment for one flag word — shared by scanning and the
     * highlight pattern so both use identical boundary semantics.
     *
     * Only single stem words get boundaries: whole_word adds a leading AND
     * trailing \b; word_root keeps the legacy leading-only \b. Phrases and
     * punctuation-containing words match literally in both modes.
     */
    protected function patternPart(string $word, ?string $matchType): string
    {
        $quoted = preg_quote($word, '/');

        if (! self::isStemWord($word)) {
            return $quoted;
        }

        return $matchType === DocumentFlagWord::MATCH_WHOLE_WORD
            ? '\b'.$quoted.'\b'
            : '\b'.$quoted;
    }

    /**
     * Build a regex alternation pattern that matches any of the flag words
     * (case-insensitive) using the same per-word match types as scan().
     * Returns null when no flag words are provided.
     */
    public function buildHighlightPattern(array $flagWords, array $matchTypes = []): ?string
    {
        $unique = array_values(array_unique(array_filter(array_map('trim', $flagWords), fn ($w) => $w !== '')));
        if (empty($unique)) {
            return null;
        }

        // Sort longest-first so longer phrases take precedence over their substrings
        usort($unique, fn ($a, $b) => strlen($b) <=> strlen($a));

        $parts = array_map(
            fn ($w) => $this->patternPart($w, $matchTypes[$w] ?? null),
            $unique
        );

        return '/('.implode('|', $parts).')/ui';
    }

    /**
     * Scan text per page and return per-page flag word results.
     *
     * @param  array<int, string>  $pages  Array of text per page (0-indexed)
     * @param  array<string>  $flagWords
     * @param  array<string, string>  $matchTypes  Raw word => match type
     * @return array{
     *     total:int,
     *     pages:array<int,array{page:int,words:array<int,array{word:string,occurrences:int}>}>
     * }
     */
    public function scanPerPage(array $pages, array $flagWords, array $matchTypes = []): array
    {
        $total = 0;
        $pagesResult = [];

        foreach ($pages as $index => $pageText) {
            $scan = $this->scan($pageText, $flagWords, $matchTypes);
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
