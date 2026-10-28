<?php

namespace Tests\Feature;

use App\Models\DocumentFlagWord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FlagWordManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_non_admin_cannot_manage_flag_words(): void
    {
        $user = User::factory()->create();
        $word = DocumentFlagWord::create(['word' => 'confidential', 'created_by' => $this->admin()->id]);

        $this->actingAs($user)->get('/flag-words')->assertForbidden();
        $this->actingAs($user)->post('/flag-words', ['word' => 'new'])->assertForbidden();
        $this->actingAs($user)->put('/flag-words/'.$word->id, ['word' => 'x'])->assertForbidden();
        $this->actingAs($user)->delete('/flag-words/'.$word->id)->assertForbidden();
        $this->actingAs($user)->delete('/flag-words/bulk', ['ids' => [$word->id]])->assertForbidden();
    }

    public function test_admin_can_list_flag_words(): void
    {
        DocumentFlagWord::create(['word' => 'confidential', 'suggested_replacement' => 'internal', 'created_by' => $this->admin()->id]);

        $this->actingAs($this->admin())
            ->get('/flag-words')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Docs/FlagWords')
                    ->has('flagWords.data', 1)
                    ->where('flagWords.data.0.word', 'confidential')
                    ->where('flagWords.data.0.suggested_replacement', 'internal')
            );
    }

    public function test_admin_can_create_flag_word_with_suggested_replacement(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/flag-words', [
                'word' => 'confidential',
                'suggested_replacement' => 'internal use only',
            ])
            ->assertRedirect(route('docs.flag-words.index'));

        $word = DocumentFlagWord::where('word', 'confidential')->first();
        $this->assertNotNull($word);
        $this->assertSame('internal use only', $word->suggested_replacement);
        $this->assertSame($admin->id, $word->created_by);
    }

    public function test_flag_word_create_is_case_insensitively_unique(): void
    {
        DocumentFlagWord::create(['word' => 'Confidential', 'created_by' => $this->admin()->id]);

        $this->actingAs($this->admin())
            ->from('/flag-words')
            ->post('/flag-words', ['word' => 'CONFIDENTIAL'])
            ->assertSessionHasErrors('word');

        $this->assertSame(1, DocumentFlagWord::count());
    }

    public function test_flag_word_update_is_case_insensitively_unique(): void
    {
        $admin = $this->admin();
        DocumentFlagWord::create(['word' => 'alpha', 'created_by' => $admin->id]);
        $second = DocumentFlagWord::create(['word' => 'beta', 'created_by' => $admin->id]);

        $this->actingAs($admin)
            ->from('/flag-words')
            ->put('/flag-words/'.$second->id, ['word' => 'ALPHA'])
            ->assertSessionHasErrors('word');

        $this->assertSame('beta', $second->refresh()->word);
    }

    public function test_admin_can_update_word_and_replacement(): void
    {
        $admin = $this->admin();
        $word = DocumentFlagWord::create(['word' => 'beta', 'created_by' => $admin->id]);

        $this->actingAs($admin)
            ->put('/flag-words/'.$word->id, [
                'word' => 'gamma',
                'suggested_replacement' => 'delta',
            ])
            ->assertRedirect(route('docs.flag-words.index'));

        $word->refresh();
        $this->assertSame('gamma', $word->word);
        $this->assertSame('delta', $word->suggested_replacement);
    }

    public function test_admin_can_delete_single_flag_word(): void
    {
        $word = DocumentFlagWord::create(['word' => 'alpha', 'created_by' => $this->admin()->id]);

        $this->actingAs($this->admin())
            ->delete('/flag-words/'.$word->id)
            ->assertRedirect(route('docs.flag-words.index'));

        $this->assertDatabaseMissing('document_flag_words', ['id' => $word->id]);
    }

    public function test_admin_can_bulk_delete_flag_words(): void
    {
        $admin = $this->admin();
        $a = DocumentFlagWord::create(['word' => 'alpha', 'created_by' => $admin->id]);
        $b = DocumentFlagWord::create(['word' => 'beta', 'created_by' => $admin->id]);
        $keep = DocumentFlagWord::create(['word' => 'gamma', 'created_by' => $admin->id]);

        $this->actingAs($admin)
            ->delete('/flag-words/bulk', ['ids' => [$a->id, $b->id]])
            ->assertRedirect(route('docs.flag-words.index'));

        $this->assertDatabaseMissing('document_flag_words', ['id' => $a->id]);
        $this->assertDatabaseMissing('document_flag_words', ['id' => $b->id]);
        $this->assertDatabaseHas('document_flag_words', ['id' => $keep->id]);
    }

    public function test_bulk_delete_validates_ids(): void
    {
        $this->actingAs($this->admin())
            ->from('/flag-words')
            ->delete('/flag-words/bulk', ['ids' => [99999]])
            ->assertSessionHasErrors('ids.0');
    }

    public function test_flag_word_word_is_required(): void
    {
        $this->actingAs($this->admin())
            ->from('/flag-words')
            ->post('/flag-words', ['word' => ''])
            ->assertSessionHasErrors('word');
    }

    public function test_create_defaults_to_whole_word_and_accepts_word_root(): void
    {
        $this->actingAs($this->admin())
            ->post('/flag-words', ['word' => 'alpha'])
            ->assertRedirect(route('docs.flag-words.index'));

        $this->assertDatabaseHas('document_flag_words', ['word' => 'alpha', 'match_type' => 'whole_word']);

        $this->actingAs($this->admin())
            ->post('/flag-words', ['word' => 'divers', 'match_type' => 'word_root']);

        $this->assertDatabaseHas('document_flag_words', ['word' => 'divers', 'match_type' => 'word_root']);
    }

    public function test_update_omitted_match_type_preserves_existing_mode(): void
    {
        $admin = $this->admin();
        $fw = DocumentFlagWord::create([
            'word' => 'divers',
            'match_type' => DocumentFlagWord::MATCH_WORD_ROOT,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->put('/flag-words/'.$fw->id, ['word' => 'divers', 'suggested_replacement' => 'new'])
            ->assertRedirect(route('docs.flag-words.index'));

        $this->assertSame('word_root', $fw->refresh()->match_type);

        $this->actingAs($admin)
            ->put('/flag-words/'.$fw->id, ['word' => 'divers', 'match_type' => 'whole_word']);

        $this->assertSame('whole_word', $fw->refresh()->match_type);
    }

    public function test_match_type_validation_rejects_unknown_values(): void
    {
        $admin = $this->admin();
        $fw = DocumentFlagWord::create(['word' => 'alpha', 'created_by' => $admin->id]);

        $this->actingAs($admin)
            ->from('/flag-words')
            ->post('/flag-words', ['word' => 'beta', 'match_type' => 'stem'])
            ->assertSessionHasErrors('match_type');

        $this->actingAs($admin)
            ->from('/flag-words')
            ->put('/flag-words/'.$fw->id, ['word' => 'alpha', 'match_type' => 'stem'])
            ->assertSessionHasErrors('match_type');
    }

    public function test_list_page_includes_match_type(): void
    {
        $admin = $this->admin();
        DocumentFlagWord::create([
            'word' => 'divers',
            'match_type' => DocumentFlagWord::MATCH_WORD_ROOT,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get('/flag-words')
            ->assertInertia(fn ($page) => $page
                ->where('flagWords.data.0.match_type', 'word_root')
            );
    }

    public function test_raw_db_inserts_keep_word_root_default_while_eloquent_defaults_whole(): void
    {
        // Rows written outside Eloquent (legacy/raw inserts) keep the column
        // default; Eloquent rows default to whole_word.
        DB::table('document_flag_words')->insert([
            'word' => 'legacy',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DocumentFlagWord::create(['word' => 'fresh', 'created_by' => $this->admin()->id]);

        $this->assertSame('word_root', DocumentFlagWord::where('word', 'legacy')->value('match_type'));
        $this->assertSame('whole_word', DocumentFlagWord::where('word', 'fresh')->value('match_type'));
    }

    public function test_match_type_migration_preserves_existing_rows_as_word_root(): void
    {
        $migration = require database_path('migrations/2026_10_28_000002_add_match_type_to_document_flag_words_table.php');

        $migration->down();
        DB::table('document_flag_words')->insert([
            'word' => 'divers',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertFalse(Schema::hasColumn('document_flag_words', 'match_type'));

        $migration->up();

        $this->assertSame('word_root', DocumentFlagWord::where('word', 'divers')->value('match_type'));
    }
}
