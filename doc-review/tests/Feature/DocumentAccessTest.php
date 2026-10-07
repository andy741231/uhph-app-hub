<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function makeDocument(User $owner, array $overrides = []): Document
    {
        return $owner->documents()->create([
            'original_name' => 'report.txt',
            'file_path' => 'documents/report.txt',
            'mime_type' => 'text/plain',
            'size' => 42,
            'status' => 'scanned',
            'flag_count' => 0,
            ...$overrides,
        ]);
    }

    public function test_guests_are_redirected_to_login_for_all_endpoints(): void
    {
        $owner = User::factory()->create();
        $document = $this->makeDocument($owner);

        $this->get('/')->assertRedirect('/login');
        $this->get('/create')->assertRedirect('/login');
        $this->post('/')->assertRedirect('/login');
        $this->get('/'.$document->id)->assertRedirect('/login');
        $this->delete('/'.$document->id)->assertRedirect('/login');
        $this->post('/'.$document->id.'/rescan')->assertRedirect('/login');
        $this->get('/'.$document->id.'/download')->assertRedirect('/login');
        $this->get('/'.$document->id.'/pdf-preview')->assertRedirect('/login');
        $this->get('/flag-words')->assertRedirect('/login');
        $this->post('/flag-words')->assertRedirect('/login');
    }

    public function test_disabled_user_session_is_terminated(): void
    {
        $user = User::factory()->disabled()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_owner_can_view_download_preview_and_rescan_own_document(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('documents/report.txt', 'plain text body');
        Storage::disk('local')->put('documents/previews/prev.pdf', '%PDF-1.4 fake');

        $owner = User::factory()->create();
        $document = $this->makeDocument($owner, [
            'extracted_text' => 'plain text body',
            'pdf_preview_path' => 'documents/previews/prev.pdf',
        ]);

        $this->actingAs($owner)->get('/'.$document->id)->assertOk();

        $download = $this->actingAs($owner)->get('/'.$document->id.'/download');
        $download->assertOk();
        $this->assertSame('plain text body', $download->streamedContent());

        $preview = $this->actingAs($owner)->get('/'.$document->id.'/pdf-preview');
        $preview->assertOk();
        $this->assertSame('application/pdf', $preview->headers->get('Content-Type'));

        $this->actingAs($owner)
            ->post('/'.$document->id.'/rescan')
            ->assertRedirect(route('docs.show', $document));
    }

    public function test_other_users_are_denied_owner_endpoints(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $document = $this->makeDocument($owner);

        $this->actingAs($other)->get('/'.$document->id)->assertForbidden();
        $this->actingAs($other)->get('/'.$document->id.'/download')->assertForbidden();
        $this->actingAs($other)->get('/'.$document->id.'/pdf-preview')->assertForbidden();
        $this->actingAs($other)->post('/'.$document->id.'/rescan')->assertForbidden();
        $this->actingAs($other)->delete('/'.$document->id)->assertForbidden();
    }

    public function test_other_users_only_see_their_own_documents_in_index(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->makeDocument($owner);
        $this->makeDocument($other, ['original_name' => 'mine.txt']);

        $response = $this->actingAs($other)->get('/');
        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Docs/Index')
                ->has('documents.data', 1)
                ->where('documents.data.0.original_name', 'mine.txt')
                ->where('canManage', false)
        );
    }

    public function test_admin_can_access_other_users_documents(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('documents/report.txt', 'admin readable');

        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $document = $this->makeDocument($owner);

        $this->actingAs($admin)->get('/'.$document->id)->assertOk();
        $this->actingAs($admin)->get('/'.$document->id.'/download')->assertOk();
        $this->actingAs($admin)
            ->delete('/'.$document->id)
            ->assertRedirect(route('docs.index'));
        $this->assertModelMissing($document);
    }

    public function test_admin_index_lists_all_documents(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $this->makeDocument($owner);

        $this->actingAs($admin)
            ->get('/')
            ->assertInertia(
                fn ($page) => $page
                    ->component('Docs/Index')
                    ->has('documents.data', 1)
                    ->where('canManage', true)
            );
    }

    public function test_delete_removes_document_and_its_stored_files(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('documents/report.txt', 'content');
        Storage::disk('local')->put('documents/previews/prev.pdf', '%PDF-1.4 fake');

        $owner = User::factory()->create();
        $document = $this->makeDocument($owner, [
            'pdf_preview_path' => 'documents/previews/prev.pdf',
        ]);

        $this->actingAs($owner)->delete('/'.$document->id)
            ->assertRedirect(route('docs.index'));

        $this->assertModelMissing($document);
        Storage::disk('local')->assertMissing('documents/report.txt');
        Storage::disk('local')->assertMissing('documents/previews/prev.pdf');
    }
}
