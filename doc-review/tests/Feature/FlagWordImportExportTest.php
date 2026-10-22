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
        $this->assertSame('word,suggested_replacement', $lines[0]);
        $this->assertSame('alpha,', $lines[1]);
        $this->assertSame('beta,two', $lines[2]);
    }

    public function test_template_downloads_csv_with_header_and_example(): void
    {
        $response = $this->actingAs($this->admin())->get('/flag-words/import/template');

        $response->assertOk();
        $this->assertStringContainsString('flag-words-template.csv', $response->headers->get('content-disposition'));

        $lines = array_values(array_filter(explode("\n", $response->streamedContent())));
        $this->assertSame('word,suggested_replacement', $lines[0]);
        $this->assertCount(2, $lines);
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
        DocumentFlagWord::create(['word' => 'divers', 'created_by' => $this->admin()->id]);

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
                'file' => $this->csv('word', 'diversity', 'divers', 'diversify'),
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
}
