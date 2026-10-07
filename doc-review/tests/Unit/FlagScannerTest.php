<?php

namespace Tests\Unit;

use App\Services\FlagScanner;
use Tests\TestCase;

class FlagScannerTest extends TestCase
{
    protected FlagScanner $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanner = new FlagScanner;
    }

    public function test_single_word_matches_with_leading_boundary_only(): void
    {
        // "test" at a word start matches; inside another word it does not.
        $result = $this->scanner->scan('test retest testing', ['test']);

        $this->assertSame(2, $result['total']);
        $this->assertSame(['test' => 2], $result['words']);
    }

    public function test_stem_prefix_matching_is_preserved(): void
    {
        // Deliberate legacy semantics: a flag word matches its derivatives.
        $result = $this->scanner->scan('diverse diversity diversification', ['divers']);

        $this->assertSame(3, $result['total']);
        $this->assertSame(['divers' => 3], $result['words']);
    }

    public function test_mid_word_occurrence_does_not_match(): void
    {
        $result = $this->scanner->scan('rediverse', ['divers']);

        $this->assertSame(0, $result['total']);
    }

    public function test_multiword_phrases_match_as_is(): void
    {
        $result = $this->scanner->scan(
            'This contains social security and social security numbers',
            ['social security']
        );

        $this->assertSame(2, $result['total']);
        $this->assertSame(['social security' => 2], $result['words']);
    }

    public function test_matching_is_case_insensitive(): void
    {
        $result = $this->scanner->scan('CONFIDENTIAL Confidential cOnFiDeNtIaL', ['confidential']);

        $this->assertSame(3, $result['total']);
    }

    public function test_flag_word_with_punctuation_matches_literally(): void
    {
        $result = $this->scanner->scan('see the Q&A section and the Q&A followup', ['Q&A']);

        $this->assertSame(2, $result['total']);
    }

    public function test_empty_and_duplicate_flag_words_are_ignored(): void
    {
        $result = $this->scanner->scan('alpha alpha', ['alpha', '', '  ', 'alpha']);

        $this->assertSame(2, $result['total']);
        $this->assertSame(['alpha' => 2], $result['words']);
    }

    public function test_word_list_is_sorted_by_occurrences_then_alphabetically(): void
    {
        $result = $this->scanner->scan(
            'gamma alpha alpha beta beta gamma gamma gamma',
            ['alpha', 'beta', 'gamma']
        );

        $words = array_column($result['words_list'], 'word');
        $this->assertSame(['gamma', 'alpha', 'beta'], $words);
        $this->assertSame(
            [
                ['word' => 'gamma', 'occurrences' => 4],
                ['word' => 'alpha', 'occurrences' => 2],
                ['word' => 'beta', 'occurrences' => 2],
            ],
            $result['words_list']
        );
    }

    public function test_per_page_scan_reports_counts_per_page(): void
    {
        $pages = [
            'alpha page one alpha',
            'nothing here',
            'beta and alpha',
        ];

        $result = $this->scanner->scanPerPage($pages, ['alpha', 'beta']);

        $this->assertSame(4, $result['total']);
        $this->assertSame(
            [
                ['page' => 1, 'words' => [['word' => 'alpha', 'occurrences' => 2]]],
                ['page' => 3, 'words' => [
                    ['word' => 'alpha', 'occurrences' => 1],
                    ['word' => 'beta', 'occurrences' => 1],
                ]],
            ],
            $result['pages']
        );
    }

    public function test_scan_with_no_flag_words_returns_zero(): void
    {
        $this->assertSame(0, $this->scanner->scan('anything', [])['total']);
        $this->assertNull($this->scanner->buildHighlightPattern([]));
    }

    public function test_highlight_pattern_mirrors_scan_semantics(): void
    {
        $pattern = $this->scanner->buildHighlightPattern(['divers', 'social security']);

        $this->assertNotNull($pattern);
        // Longest phrase first, leading boundary for single words only.
        $this->assertSame('/(social security|\bdivers)/ui', $pattern);
    }
}
