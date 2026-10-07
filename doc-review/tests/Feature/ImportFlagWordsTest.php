<?php

namespace Tests\Feature;

use App\Models\DocumentFlagWord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportFlagWordsTest extends TestCase
{
    use RefreshDatabase;

    protected string $exportPath;

    protected function writeExport(array $payload): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fw-import-').'.json';
        file_put_contents($path, json_encode($payload));

        return $path;
    }

    protected function payload(array $rows): array
    {
        return ['version' => 1, 'flag_words' => $rows];
    }

    protected function row(string $word, ?string $replacement = null): array
    {
        return ['word' => $word, 'suggested_replacement' => $replacement];
    }

    public function test_empty_valid_export_is_accepted(): void
    {
        $this->artisan('flag-words:import', ['file' => $this->writeExport($this->payload([]))])
            ->assertExitCode(0);

        $this->assertSame(0, DocumentFlagWord::count());
    }

    public function test_dry_run_makes_no_writes(): void
    {
        $path = $this->writeExport($this->payload([$this->row('confidential', 'internal')]));

        $this->artisan('flag-words:import', ['file' => $path])->assertExitCode(0);

        $this->assertSame(0, DocumentFlagWord::count());
    }

    public function test_apply_inserts_words_with_null_creator(): void
    {
        $path = $this->writeExport($this->payload([
            $this->row('confidential', 'internal'),
            $this->row('privileged'),
        ]));

        $this->artisan('flag-words:import', ['file' => $path, '--apply' => true])
            ->assertExitCode(0);

        $this->assertSame(2, DocumentFlagWord::count());
        $word = DocumentFlagWord::where('word', 'confidential')->first();
        $this->assertSame('internal', $word->suggested_replacement);
        $this->assertNull($word->created_by);
    }

    public function test_repeated_import_creates_no_duplicates(): void
    {
        $path = $this->writeExport($this->payload([$this->row('confidential')]));

        $this->artisan('flag-words:import', ['file' => $path, '--apply' => true])->assertExitCode(0);
        $this->artisan('flag-words:import', ['file' => $path, '--apply' => true])->assertExitCode(0);

        $this->assertSame(1, DocumentFlagWord::count());
    }

    public function test_unicode_case_folding_is_idempotent_on_sqlite(): void
    {
        $upper = mb_chr(0x00C9).'quity';
        $lower = mb_chr(0x00E9).'quity';
        $path = $this->writeExport($this->payload([$this->row($upper, 'fairness')]));
        $this->artisan('flag-words:import', ['file' => $path, '--apply' => true])->assertExitCode(0);
        $this->artisan('flag-words:import', ['file' => $path, '--apply' => true])->assertExitCode(0);
        $path = $this->writeExport($this->payload([$this->row($lower, 'equal opportunity')]));
        $this->artisan('flag-words:import', ['file' => $path, '--apply' => true])->assertExitCode(0);

        $this->assertSame(1, DocumentFlagWord::count());
        $this->assertDatabaseHas('document_flag_words', [
            'word' => $lower,
            'suggested_replacement' => 'equal opportunity',
        ]);
        $this->assertSame(0, User::count());
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_import_updates_replacement_and_preserves_creator(): void
    {
        $admin = User::factory()->admin()->create();
        $existing = DocumentFlagWord::create([
            'word' => 'Confidential',
            'suggested_replacement' => 'internal',
            'created_by' => $admin->id,
        ]);

        $path = $this->writeExport($this->payload([
            $this->row('confidential', 'privileged information'),
        ]));

        $this->artisan('flag-words:import', ['file' => $path, '--apply' => true])
            ->assertExitCode(0);

        $this->assertSame(1, DocumentFlagWord::count());
        $existing->refresh();
        $this->assertSame('privileged information', $existing->suggested_replacement);
        $this->assertSame($admin->id, $existing->created_by);
    }

    public function test_malformed_export_aborts_without_writes(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'fw-bad-').'.json';
        file_put_contents($path, '{"version": 9, "flag_words": "nope"}');

        $this->artisan('flag-words:import', ['file' => $path, '--apply' => true])
            ->assertExitCode(1);

        $this->assertSame(0, DocumentFlagWord::count());
    }

    public function test_export_with_duplicate_case_insensitive_words_aborts(): void
    {
        $path = $this->writeExport($this->payload([
            $this->row('Alpha'),
            $this->row('ALPHA'),
        ]));

        $this->artisan('flag-words:import', ['file' => $path, '--apply' => true])
            ->assertExitCode(1);

        $this->assertSame(0, DocumentFlagWord::count());
    }

    public function test_export_with_empty_word_aborts(): void
    {
        $path = $this->writeExport($this->payload([$this->row('   ')]));

        $this->artisan('flag-words:import', ['file' => $path, '--apply' => true])
            ->assertExitCode(1);

        $this->assertSame(0, DocumentFlagWord::count());
    }

    public function test_duplicate_case_insensitive_target_rows_abort_and_roll_back(): void
    {
        // Simulate a dirty target where the same word exists twice in
        // different cases (direct inserts bypass app-level validation).
        DB::table('document_flag_words')->insert([
            ['word' => 'Alpha', 'created_by' => null, 'created_at' => now(), 'updated_at' => now()],
            ['word' => 'ALPHA', 'created_by' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $path = $this->writeExport($this->payload([
            $this->row('new-word', 'replacement'),
            $this->row('alpha', 'changed'),
        ]));

        $this->artisan('flag-words:import', ['file' => $path, '--apply' => true])
            ->assertExitCode(1);

        // Nothing was written or updated — the transaction rolled back.
        $this->assertSame(2, DocumentFlagWord::count());
        $this->assertDatabaseMissing('document_flag_words', ['word' => 'new-word']);
        $this->assertNull(
            DocumentFlagWord::where('word', 'Alpha')->first()->suggested_replacement
        );
    }

    public function test_missing_file_fails(): void
    {
        $this->artisan('flag-words:import', ['file' => '/nonexistent/export.json'])
            ->assertExitCode(1);
    }
}
