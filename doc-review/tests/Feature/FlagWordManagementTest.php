<?php

namespace Tests\Feature;

use App\Models\DocumentFlagWord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
