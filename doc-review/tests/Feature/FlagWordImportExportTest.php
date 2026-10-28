<?php

namespace Tests\Feature;

use App\Models\DocumentFlagWord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class FlagWordImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function csv(string ...$lines): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('flags.csv', implode("\n", $lines)."\n");
    }

    public function test_non_admin_cannot_import_or_export(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/flag-words/export')->assertForbidden();
        $this->actingAs($user)->get('/flag-words/import/template')->assertForbidden();
        $this->actingAs($user)->post('/flag-words/import', [
            'file' => $this->csv('word', 'alpha'),
        ])->assertForbidden();
    }

    public function test_export_downloads_all_flag_words_as_csv(): void
    {
        DocumentFlagWord::create(['word' => 'beta', 'suggested_replacement' => 'two', 'created_by' => $this->admin()->id]);
        DocumentFlagWord::create(['word' => 'alpha', 'created_by' => $this->admin()->id]);

        $response = $this->actingAs($this->admin())->get('/flag-words/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment; filename=flag-words-', $response->headers->get('content-disposition'));

        $lines = array_values(array_filter(explode("\n", $response->streamedContent())));
        $this->assertSame('word,suggested_replacement,match_type', $lines[0]);
        $this->assertSame('alpha,,whole_word', $lines[1]);
        $this->assertSame('beta,two,whole_word', $lines[2]);
    }

    public function test_template_downloads_csv_with_header_and_example(): void
    {
        $response = $this->actingAs($this->admin())->get('/flag-words/import/template');

        $response->assertOk();
        $this->assertStringContainsString('flag-words-template.csv', $response->headers->get('content-disposition'));

        $lines = array_values(array_filter(explode("\n", $response->streamedContent())));
        $this->assertSame('word,suggested_replacement,match_type', $lines[0]);
        $this->assertCount(3, $lines);
        $this->assertSame('ai,,whole_word', $lines[1]);
        $this->assertSame('divers,"inclusive language",word_root', $lines[2]);
    }

    public function test_import_creates_new_words_and_reports_result(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/flag-words/import', [
                'file' => $this->csv(
                    'word,suggested_replacement',
                    'alpha,one',
                    'beta',
                ),
            ])
            ->assertRedirect(route('docs.flag-words.index'))
            ->assertSessionHas('import_result', fn ($result) => $result['imported'] === 2 && $result['skipped'] === []);

        $this->assertDatabaseHas('document_flag_words', ['word' => 'alpha', 'suggested_replacement' => 'one', 'created_by' => $admin->id]);
        $this->assertDatabaseHas('document_flag_words', ['word' => 'beta', 'suggested_replacement' => null]);
    }

    public function test_import_skips_existing_words_case_insensitively(): void
    {
        DocumentFlagWord::create(['word' => 'Alpha', 'created_by' => $this->admin()->id]);

        $this->actingAs($this->admin())
            ->post('/flag-words/import', [
                'file' => $this->csv('word,suggested_replacement', 'ALPHA,ignored', 'beta'),
            ])
            ->assertSessionHas('import_result', function ($result) {
                return $result['imported'] === 1
                    && count($result['skipped']) === 1
                    && $result['skipped'][0]['word'] === 'ALPHA'
                    && $result['skipped'][0]['reason'] === 'exists';
            });

        // Existing word is untouched, not updated.
        $this->assertSame('Alpha', DocumentFlagWord::where('word', 'Alpha')->first()->word);
        $this->assertSame(2, DocumentFlagWord::count());
    }

    public function test_import_skips_words_covered_by_an_existing_stem(): void
    {
        DocumentFlagWord::create([
            'word' => 'divers',
            'match_type' => DocumentFlagWord::MATCH_WORD_ROOT,
            'created_by' => $this->admin()->id,
        ]);

        $this->actingAs($this->admin())
            ->post('/flag-words/import', [
                'file' => $this->csv(
                    'word,suggested_replacement',
                    'diverse',
                    'Diversity',
                    'advance diversity,a phrase stays',
                ),
            ])
            ->assertSessionHas('import_result', function ($result) {
                return $result['imported'] === 1
                    && count($result['skipped']) === 2
                    && $result['skipped'][0]['reason'] === 'covered'
                    && $result['skipped'][0]['covered_by'] === 'divers'
                    && $result['skipped'][1]['reason'] === 'covered';
            });

        $this->assertDatabaseMissing('document_flag_words', ['word' => 'diverse']);
        $this->assertDatabaseMissing('document_flag_words', ['word' => 'Diversity']);
        $this->assertDatabaseHas('document_flag_words', ['word' => 'advance diversity', 'suggested_replacement' => 'a phrase stays']);
    }

    public function test_import_root_precedence_applies_within_the_file_regardless_of_order(): void
    {
        $this->actingAs($this->admin())
            ->post('/flag-words/import', [
                'file' => $this->csv(
                    'word,suggested_replacement,match_type',
                    'diversity,,whole_word',
                    'divers,,word_root',
                    'diversify,,whole_word',
                ),
            ])
            ->assertSessionHas('import_result', function ($result) {
                return $result['imported'] === 1
                    && count($result['skipped']) === 2
                    && $result['skipped'][0]['word'] === 'diversity'
                    && $result['skipped'][0]['reason'] === 'covered'
                    && $result['skipped'][0]['covered_by'] === 'divers'
                    && $result['skipped'][1]['reason'] === 'covered';
            });

        $this->assertSame(['divers'], DocumentFlagWord::pluck('word')->all());
    }

    public function test_importing_a_shorter_root_is_allowed_when_longer_word_exists(): void
    {
        DocumentFlagWord::create(['word' => 'diversity', 'created_by' => $this->admin()->id]);

        $this->actingAs($this->admin())
            ->post('/flag-words/import', [
                'file' => $this->csv('word', 'divers'),
            ])
            ->assertSessionHas('import_result', fn ($result) => $result['imported'] === 1 && $result['skipped'] === []);

        $this->assertDatabaseHas('document_flag_words', ['word' => 'divers']);
        $this->assertDatabaseHas('document_flag_words', ['word' => 'diversity']);
    }

    public function test_import_skips_duplicate_and_invalid_rows_in_the_file(): void
    {
        $long = str_repeat('a', 256);

        $this->actingAs($this->admin())
            ->post('/flag-words/import', [
                'file' => $this->csv('alpha', 'alpha', $long, ''),
            ])
            ->assertSessionHas('import_result', function ($result) {
                return $result['imported'] === 1
                    && count($result['skipped']) === 2
                    && $result['skipped'][0]['reason'] === 'exists'
                    && $result['skipped'][1]['reason'] === 'invalid';
            });

        $this->assertSame(1, DocumentFlagWord::count());
    }

    public function test_import_validates_the_upload(): void
    {
        $this->actingAs($this->admin())
            ->from('/flag-words')
            ->post('/flag-words/import', [])
            ->assertSessionHasErrors('file');

        $this->actingAs($this->admin())
            ->from('/flag-words')
            ->post('/flag-words/import', [
                'file' => UploadedFile::fake()->create('flags.pdf', 10, 'application/pdf'),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_import_whole_word_and_root_coexist_without_suppressing(): void
    {
        // "ai" whole_word never covers "aim"; both import.
        $this->actingAs($this->admin())
            ->post('/flag-words/import', [
                'file' => $this->csv(
                    'word,suggested_replacement,match_type',
                    'ai,,whole_word',
                    'aim,,whole_word',
                ),
            ])
            ->assertSessionHas('import_result', fn ($result) => $result['imported'] === 2 && $result['skipped'] === []);

        $this->assertDatabaseHas('document_flag_words', ['word' => 'ai', 'match_type' => 'whole_word']);
        $this->assertDatabaseHas('document_flag_words', ['word' => 'aim', 'match_type' => 'whole_word']);
    }

    public function test_import_word_root_covers_derived_word(): void
    {
        $this->actingAs($this->admin())
            ->post('/flag-words/import', [
                'file' => $this->csv(
                    'word,suggested_replacement,match_type',
                    'divers,,word_root',
                    'diverse,,whole_word',
                ),
            ])
            ->assertSessionHas('import_result', function ($result) {
                return $result['imported'] === 1
                    && count($result['skipped']) === 1
                    && $result['skipped'][0]['reason'] === 'covered'
                    && $result['skipped'][0]['covered_by'] === 'divers';
            });
    }

    public function test_import_db_whole_word_entry_never_covers_file_candidates(): void
    {
        DocumentFlagWord::create([
            'word' => 'ai',
            'match_type' => DocumentFlagWord::MATCH_WHOLE_WORD,
            'created_by' => $this->admin()->id,
        ]);

        // File's word_root "ai" skips as "exists" and must NOT become a
        // root that suppresses the separate "aim" row.
        $this->actingAs($this->admin())
            ->post('/flag-words/import', [
                'file' => $this->csv(
                    'word,suggested_replacement,match_type',
                    'ai,,word_root',
                    'aim,,whole_word',
                ),
            ])
            ->assertSessionHas('import_result', function ($result) {
                return $result['imported'] === 1
                    && count($result['skipped']) === 1
                    && $result['skipped'][0]['reason'] === 'exists';
            });

        $this->assertDatabaseHas('document_flag_words', ['word' => 'aim']);
    }

    public function test_import_first_valid_whole_row_blocks_later_root_duplicate(): void
    {
        // First VALID "ai" row is whole_word; the later word_root duplicate
        // must not retroactively cover "aim".
        $this->actingAs($this->admin())
            ->post('/flag-words/import', [
                'file' => $this->csv(
                    'word,suggested_replacement,match_type',
                    'ai,,whole_word',
                    'ai,,word_root',
                    'aim,,whole_word',
                ),
            ])
            ->assertSessionHas('import_result', function ($result) {
                return $result['imported'] === 2
                    && count($result['skipped']) === 1
                    && $result['skipped'][0]['reason'] === 'exists';
            });

        $this->assertDatabaseHas('document_flag_words', ['word' => 'aim']);
    }

    public function test_import_invalid_row_does_not_suppress_valid_word(): void
    {
        // The "ai" candidate is invalid (overlong replacement) so it cannot
        // act as a word_root covering "aim".
        $long = str_repeat('x', 256);

        $this->actingAs($this->admin())
            ->post('/flag-words/import', [
                'file' => $this->csv(
                    'word,suggested_replacement,match_type',
                    'ai,'.$long.',word_root',
                    'aim,,whole_word',
                ),
            ])
            ->assertSessionHas('import_result', function ($result) {
                return $result['imported'] === 1
                    && count($result['skipped']) === 1
                    && $result['skipped'][0]['reason'] === 'invalid';
            });

        $this->assertDatabaseHas('document_flag_words', ['word' => 'aim']);
    }

    public function test_import_rejects_unknown_match_type_and_normalizes_case(): void
    {
        $this->actingAs($this->admin())
            ->post('/flag-words/import', [
                'file' => $this->csv(
                    'word,suggested_replacement,match_type',
                    'alpha,,bogus',
                    'beta,, WHOLE_WORD ',
                    'gamma,,Word_Root',
                    'delta,',
                ),
            ])
            ->assertSessionHas('import_result', function ($result) {
                return $result['imported'] === 3
                    && count($result['skipped']) === 1
                    && $result['skipped'][0]['word'] === 'alpha'
                    && $result['skipped'][0]['reason'] === 'invalid';
            });

        $this->assertDatabaseHas('document_flag_words', ['word' => 'beta', 'match_type' => 'whole_word']);
        $this->assertDatabaseHas('document_flag_words', ['word' => 'gamma', 'match_type' => 'word_root']);
        // Two-column rows default to whole_word.
        $this->assertDatabaseHas('document_flag_words', ['word' => 'delta', 'match_type' => 'whole_word']);
    }

    public function test_export_import_roundtrip_preserves_match_types(): void
    {
        $admin = $this->admin();
        DocumentFlagWord::create(['word' => 'ai', 'match_type' => 'whole_word', 'created_by' => $admin->id]);
        DocumentFlagWord::create(['word' => 'divers', 'match_type' => 'word_root', 'created_by' => $admin->id]);

        $exported = $this->actingAs($admin)->get('/flag-words/export')->streamedContent();

        DocumentFlagWord::query()->delete();

        $this->actingAs($admin)
            ->post('/flag-words/import', [
                'file' => UploadedFile::fake()->createWithContent('rt.csv', $exported),
            ])
            ->assertSessionHas('import_result', fn ($result) => $result['imported'] === 2 && $result['skipped'] === []);

        $this->assertDatabaseHas('document_flag_words', ['word' => 'ai', 'match_type' => 'whole_word']);
        $this->assertDatabaseHas('document_flag_words', ['word' => 'divers', 'match_type' => 'word_root']);
    }
}
