<?php

namespace Tests\Feature;

use App\Http\Controllers\DocumentController;
use App\Models\Document;
use App\Models\DocumentFlag;
use App\Models\DocumentFlagWord;
use App\Models\User;
use App\Services\DocumentTextExtractor;
use App\Services\DocxToPdfConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_txt_upload_extracts_and_scans_with_real_flag_scanner(): void
    {
        $user = User::factory()->create();
        DocumentFlagWord::create([
            'word' => 'confidential',
            'suggested_replacement' => 'internal',
            'created_by' => $user->id,
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'report.txt',
            "This report is confidential.\nMarked confidential twice: confidential."
        );

        $response = $this->actingAs($user)->post('/', ['file' => $file]);

        $document = Document::first();
        $this->assertNotNull($document);

        $response->assertRedirect(route('docs.show', $document));

        $this->assertSame($user->id, $document->user_id);
        $this->assertSame('report.txt', $document->original_name);
        $this->assertSame('scanned', $document->status);
        $this->assertSame(3, $document->flag_count);
        $this->assertStringContainsString('confidential', $document->extracted_text);
        $this->assertSame(
            [['page' => 1, 'words' => [['word' => 'confidential', 'occurrences' => 3]]]],
            $document->flagged_pages
        );

        Storage::disk('local')->assertExists($document->file_path);

        $this->assertDatabaseHas('document_flags', [
            'document_id' => $document->id,
            'occurrences' => 3,
        ]);
    }

    public function test_upload_rejects_files_over_10mb(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('huge.txt', 10241);

        $this->actingAs($user)
            ->from('/create')
            ->post('/', ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Document::count());
        $this->assertSame([], Storage::disk('local')->allFiles('documents'));
    }

    public function test_upload_rejects_unsupported_extension(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream');

        $this->actingAs($user)
            ->from('/create')
            ->post('/', ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Document::count());
    }

    public function test_upload_requires_a_file(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/create')
            ->post('/', [])
            ->assertSessionHasErrors('file');
    }

    public function test_failed_extraction_marks_document_failed_without_leaking_paths(): void
    {
        $user = User::factory()->create();

        // A file with a .pdf extension but no parseable PDF structure makes
        // the extractor throw; the user must see a generic error.
        $file = UploadedFile::fake()->createWithContent('broken.pdf', 'not a real pdf');

        $response = $this->actingAs($user)->post('/', ['file' => $file]);

        $document = Document::first();
        $this->assertNotNull($document);
        $response->assertRedirect(route('docs.show', $document));

        $this->assertSame('failed', $document->status);
        $this->assertNotNull($document->error);
        $this->assertStringNotContainsString(storage_path(), (string) $document->error);
        $this->assertStringNotContainsString('/', (string) $document->error);
    }

    public function test_rescan_replaces_flag_counts_and_keeps_preview_when_conversion_fails(): void
    {
        $user = User::factory()->create();

        // A Word document whose previous conversion produced a preview.
        Storage::disk('local')->put('documents/memo.docx', 'docx-bytes');
        Storage::disk('local')->put('documents/previews/old.pdf', '%PDF-1.4 old');

        $document = Document::create([
            'user_id' => $user->id,
            'original_name' => 'memo.docx',
            'file_path' => 'documents/memo.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size' => 100,
            'status' => 'scanned',
            'flag_count' => 0,
            'pdf_preview_path' => 'documents/previews/old.pdf',
            'extracted_text' => 'old text',
        ]);

        // Simulate a failed Word->PDF conversion so the existing preview
        // must be retained rather than deleted prematurely.
        $this->mock(DocxToPdfConverter::class)
            ->shouldReceive('convert')->andReturnNull();

        $this->actingAs($user)->post('/'.$document->id.'/rescan');

        $document->refresh();
        $this->assertSame('documents/previews/old.pdf', $document->pdf_preview_path);
        Storage::disk('local')->assertExists('documents/previews/old.pdf');
    }

    public function test_rescan_failure_keeps_old_preview_flags_and_removes_orphan(): void
    {
        $user = User::factory()->create();
        $word = DocumentFlagWord::create(['word' => 'alpha', 'created_by' => $user->id]);

        Storage::disk('local')->put('documents/memo.docx', 'docx-bytes');
        Storage::disk('local')->put('documents/previews/old.pdf', '%PDF-1.4 old');
        Storage::disk('local')->put('documents/previews/staged.pdf', '%PDF-1.4 staged');

        $document = Document::create([
            'user_id' => $user->id,
            'original_name' => 'memo.docx',
            'file_path' => 'documents/memo.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size' => 100,
            'status' => 'scanned',
            'flag_count' => 1,
            'pdf_preview_path' => 'documents/previews/old.pdf',
            'extracted_text' => 'alpha old text',
        ]);
        DocumentFlag::create([
            'document_id' => $document->id,
            'flag_word_id' => $word->id,
            'occurrences' => 1,
        ]);

        // Conversion "succeeds" producing a staged preview, then the parse
        // throws. The staged file must be orphaned-removed, the old preview
        // and the previous scan results must remain untouched.
        $this->mock(DocxToPdfConverter::class)
            ->shouldReceive('convert')->andReturn('documents/previews/staged.pdf');
        $this->mock(DocumentTextExtractor::class)
            ->shouldReceive('extractPdfPerPageFromDisk')
            ->andThrow(new \RuntimeException('simulated parse failure'));

        $this->actingAs($user)->post('/'.$document->id.'/rescan');

        $document->refresh();
        $this->assertSame('failed', $document->status);
        $this->assertSame('documents/previews/old.pdf', $document->pdf_preview_path);
        $this->assertSame(1, $document->flag_count);
        $this->assertSame(1, $document->flags()->count());
        $this->assertSame('alpha old text', $document->extracted_text);
        Storage::disk('local')->assertExists('documents/previews/old.pdf');
        Storage::disk('local')->assertMissing('documents/previews/staged.pdf');
    }

    public function test_rescan_flag_persist_failure_rolls_back_and_removes_orphan(): void
    {
        $user = User::factory()->create();
        $word = DocumentFlagWord::create(['word' => 'alpha', 'created_by' => $user->id]);

        Storage::disk('local')->put('documents/memo.docx', 'docx-bytes');
        Storage::disk('local')->put('documents/previews/old.pdf', '%PDF-1.4 old');
        Storage::disk('local')->put('documents/previews/staged.pdf', '%PDF-1.4 staged');

        $document = Document::create([
            'user_id' => $user->id,
            'original_name' => 'memo.docx',
            'file_path' => 'documents/memo.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size' => 100,
            'status' => 'scanned',
            'flag_count' => 1,
            'pdf_preview_path' => 'documents/previews/old.pdf',
            'extracted_text' => 'alpha old text',
        ]);
        DocumentFlag::create([
            'document_id' => $document->id,
            'flag_word_id' => $word->id,
            'occurrences' => 1,
        ]);

        // Conversion and extraction succeed, then flag persistence throws
        // inside the transaction — the DB update rolls back and the staged
        // preview (now equal to the in-memory pointer) must still be removed.
        $this->mock(DocxToPdfConverter::class)
            ->shouldReceive('convert')->andReturn('documents/previews/staged.pdf');
        $this->mock(DocumentTextExtractor::class)
            ->shouldReceive('extractPdfPerPageFromDisk')
            ->andReturn(['new alpha page']);

        $controller = $this->partialMock(DocumentController::class);
        $controller->shouldAllowMockingProtectedMethods()
            ->shouldReceive('persistFlags')
            ->andThrow(new \RuntimeException('simulated flag persist failure'));

        $this->actingAs($user)->post('/'.$document->id.'/rescan');

        $document->refresh();
        $this->assertSame('failed', $document->status);
        // Rolled-back values must be restored — not the post-update in-memory state.
        $this->assertSame('documents/previews/old.pdf', $document->pdf_preview_path);
        $this->assertSame(1, $document->flag_count);
        $this->assertSame(1, $document->flags()->count());
        $this->assertSame('alpha old text', $document->extracted_text);
        Storage::disk('local')->assertExists('documents/previews/old.pdf');
        Storage::disk('local')->assertMissing('documents/previews/staged.pdf');
    }

    public function test_rescan_success_replaces_preview_and_flags_after_commit(): void
    {
        $user = User::factory()->create();
        $word = DocumentFlagWord::create(['word' => 'gamma', 'created_by' => $user->id]);

        Storage::disk('local')->put('documents/memo.docx', 'docx-bytes');
        Storage::disk('local')->put('documents/previews/old.pdf', '%PDF-1.4 old');
        Storage::disk('local')->put('documents/previews/staged.pdf', '%PDF-1.4 staged');

        $document = Document::create([
            'user_id' => $user->id,
            'original_name' => 'memo.docx',
            'file_path' => 'documents/memo.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size' => 100,
            'status' => 'scanned',
            'flag_count' => 0,
            'pdf_preview_path' => 'documents/previews/old.pdf',
            'extracted_text' => 'old text',
        ]);

        $this->mock(DocxToPdfConverter::class)
            ->shouldReceive('convert')->andReturn('documents/previews/staged.pdf');
        $this->mock(DocumentTextExtractor::class)
            ->shouldReceive('extractPdfPerPageFromDisk')
            ->with('local', 'documents/previews/staged.pdf')
            ->andReturn(['fresh page with gamma']);

        $this->actingAs($user)->post('/'.$document->id.'/rescan')
            ->assertRedirect(route('docs.show', $document));

        $document->refresh();
        $this->assertSame('scanned', $document->status);
        $this->assertSame('documents/previews/staged.pdf', $document->pdf_preview_path);
        $this->assertSame('fresh page with gamma', $document->extracted_text);
        $this->assertSame(1, $document->flag_count);
        $this->assertSame(1, $document->flags()->count());
        // Old preview deleted only after the update committed.
        Storage::disk('local')->assertMissing('documents/previews/old.pdf');
        Storage::disk('local')->assertExists('documents/previews/staged.pdf');
    }

    public function test_upload_store_failure_creates_no_document(): void
    {
        $user = User::factory()->create();

        // Force the store() contract to return false; no row must be created.
        $file = \Mockery::mock(UploadedFile::fake()->create('report.txt', 10))->makePartial();
        $file->shouldReceive('store')->andReturn(false);

        $this->actingAs($user)
            ->from('/create')
            ->post('/', ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Document::count());
    }

    public function test_rescan_updates_flags_from_current_word_list(): void
    {
        $user = User::factory()->create();
        Storage::disk('local')->put('documents/note.txt', 'alpha beta gamma');

        $document = Document::create([
            'user_id' => $user->id,
            'original_name' => 'note.txt',
            'file_path' => 'documents/note.txt',
            'mime_type' => 'text/plain',
            'size' => 16,
            'status' => 'scanned',
            'flag_count' => 0,
            'extracted_text' => 'alpha beta gamma',
        ]);

        DocumentFlagWord::create(['word' => 'alpha', 'created_by' => $user->id]);
        DocumentFlagWord::create(['word' => 'gamma', 'created_by' => $user->id]);

        $this->actingAs($user)->post('/'.$document->id.'/rescan')
            ->assertRedirect(route('docs.show', $document));

        $document->refresh();
        $this->assertSame('scanned', $document->status);
        $this->assertSame(2, $document->flag_count);
        $this->assertSame(2, $document->flags()->count());
    }
}
