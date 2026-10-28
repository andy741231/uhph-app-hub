<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

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

    protected function makeLog(User $actor, string $action, array $overrides = []): ActivityLog
    {
        return ActivityLog::create([
            'actor_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_email' => $actor->email,
            'action' => $action,
            'created_at' => now(),
            ...$overrides,
        ]);
    }

    public function test_guests_are_redirected_to_login_for_logs(): void
    {
        $this->get('/logs')->assertRedirect('/login');
    }

    public function test_regular_users_cannot_view_logs(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/logs')->assertForbidden();
    }

    public function test_disabled_admin_cannot_view_logs(): void
    {
        $admin = User::factory()->admin()->disabled()->create();

        $response = $this->actingAs($admin)->get('/logs');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_admin_sees_all_actors_ordered_by_timestamp_then_id(): void
    {
        $admin = User::factory()->admin()->create();
        $alice = User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $bob = User::factory()->create(['name' => 'Bob', 'email' => 'bob@example.com']);

        $now = now();
        $oldest = $this->makeLog($bob, ActivityLog::ACTION_LOGIN, ['created_at' => $now->copy()->subMinutes(2)]);
        $middle = $this->makeLog($alice, ActivityLog::ACTION_LOGIN, ['created_at' => $now->copy()->subMinute()]);
        // Same timestamp — insertion order must be reversed by id desc.
        $sameA = $this->makeLog($alice, ActivityLog::ACTION_DOCUMENT_UPLOADED, [
            'created_at' => $now, 'document_id' => 10, 'document_name' => 'a.txt',
        ]);
        $sameB = $this->makeLog($bob, ActivityLog::ACTION_DOCUMENT_DELETED, [
            'created_at' => $now, 'document_id' => 10, 'document_name' => 'a.txt',
        ]);

        $response = $this->actingAs($admin)->get('/logs');

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertInertia(fn ($page) => $page
            ->component('Docs/Logs')
            ->has('logs.data', 4)
            ->where('logs.data.0.id', $sameB->id)
            ->where('logs.data.1.id', $sameA->id)
            ->where('logs.data.2.id', $middle->id)
            ->where('logs.data.3.id', $oldest->id)
            ->where('logs.data.0.actor_email', 'bob@example.com')
            ->where('logs.data.3.actor_name', 'Bob')
            ->where('logs.data.0.document_name', 'a.txt')
        );
    }

    public function test_logs_paginate_50_per_page(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        foreach (range(1, 55) as $i) {
            $this->makeLog($user, ActivityLog::ACTION_LOGIN);
        }

        $this->actingAs($admin)
            ->get('/logs')
            ->assertInertia(fn ($page) => $page
                ->component('Docs/Logs')
                ->has('logs.data', 50)
                ->where('logs.total', 55)
                ->where('logs.current_page', 1)
                ->where('logs.last_page', 2)
            );

        $this->actingAs($admin)
            ->get('/logs?page=2')
            ->assertInertia(fn ($page) => $page
                ->has('logs.data', 5)
                ->where('logs.current_page', 2)
            );
    }

    public function test_action_filter_limits_results_and_invalid_action_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->makeLog($user, ActivityLog::ACTION_LOGIN);
        $this->makeLog($user, ActivityLog::ACTION_DOCUMENT_UPLOADED, ['document_name' => 'up.txt']);
        $this->makeLog($user, ActivityLog::ACTION_DOCUMENT_DELETED, ['document_name' => 'gone.txt']);

        $this->actingAs($admin)
            ->get('/logs?action=login')
            ->assertInertia(fn ($page) => $page
                ->has('logs.data', 1)
                ->where('logs.data.0.action', 'login')
                ->where('filters.action', 'login')
            );

        $this->actingAs($admin)
            ->get('/logs?action=document_deleted')
            ->assertInertia(fn ($page) => $page
                ->has('logs.data', 1)
                ->where('logs.data.0.action', 'document_deleted')
                ->where('logs.data.0.document_name', 'gone.txt')
            );

        $this->actingAs($admin)
            ->get('/logs?action=not-an-action')
            ->assertSessionHasErrors('action');
    }

    public function test_can_view_logs_capability_is_shared_for_admin_and_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->get('/')
            ->assertInertia(fn ($page) => $page->where('auth.user.canViewLogs', true));

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn ($page) => $page->where('auth.user.canViewLogs', false));
    }

    public function test_direct_fixture_document_create_writes_no_audit_row(): void
    {
        $user = User::factory()->create();

        $this->makeDocument($user);

        $this->assertSame(0, ActivityLog::count());
    }

    public function test_successful_upload_writes_one_audit_row(): void
    {
        $this->freezeTime();

        $user = User::factory()->create(['name' => 'Uploader', 'email' => 'up@example.com']);
        $file = UploadedFile::fake()->createWithContent('report.txt', 'plain text body');

        $this->actingAs($user)->post('/', ['file' => $file]);

        $document = Document::sole();
        $log = ActivityLog::sole();

        $this->assertSame(ActivityLog::ACTION_DOCUMENT_UPLOADED, $log->action);
        $this->assertSame($user->id, $log->actor_id);
        $this->assertSame('Uploader', $log->actor_name);
        $this->assertSame('up@example.com', $log->actor_email);
        $this->assertSame($document->id, $log->document_id);
        $this->assertSame('report.txt', $log->document_name);
        $this->assertSame(now()->format('Y-m-d H:i:s'), $log->created_at->format('Y-m-d H:i:s'));
    }

    public function test_failed_extraction_still_logs_the_retained_upload(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('broken.pdf', 'not a real pdf');

        $this->actingAs($user)->post('/', ['file' => $file]);

        $document = Document::sole();
        $this->assertSame('failed', $document->status);

        $log = ActivityLog::sole();
        $this->assertSame(ActivityLog::ACTION_DOCUMENT_UPLOADED, $log->action);
        $this->assertSame($document->id, $log->document_id);
        $this->assertSame('broken.pdf', $log->document_name);
    }

    public function test_rejected_upload_writes_no_audit_row(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream');

        $this->actingAs($user)
            ->from('/create')
            ->post('/', ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, ActivityLog::count());
    }

    public function test_unstored_upload_writes_no_audit_row(): void
    {
        $user = User::factory()->create();

        // Force the store() contract to return false, as DocumentUploadTest does.
        $file = \Mockery::mock(UploadedFile::fake()->create('report.txt', 10))->makePartial();
        $file->shouldReceive('store')->andReturn(false);

        $this->actingAs($user)
            ->from('/create')
            ->post('/', ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Document::count());
        $this->assertSame(0, ActivityLog::count());
    }

    public function test_owner_delete_preserves_upload_and_deletion_rows(): void
    {
        $owner = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('report.txt', 'plain text body');

        $this->actingAs($owner)->post('/', ['file' => $file]);
        $document = Document::sole();

        $this->actingAs($owner)
            ->delete('/'.$document->id)
            ->assertRedirect(route('docs.index'));

        $logs = ActivityLog::orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertSame(ActivityLog::ACTION_DOCUMENT_UPLOADED, $logs[0]->action);
        $this->assertSame(ActivityLog::ACTION_DOCUMENT_DELETED, $logs[1]->action);

        // Rows outlive the document; the id and filename snapshots survive.
        $this->assertSame(0, Document::count());
        foreach ($logs as $log) {
            $this->assertSame($document->id, $log->document_id);
            $this->assertSame('report.txt', $log->document_name);
            $this->assertSame($owner->id, $log->actor_id);
        }
    }

    public function test_admin_delete_attributes_actor_to_admin_not_owner(): void
    {
        $owner = User::factory()->create(['name' => 'Owner', 'email' => 'owner@example.com']);
        $admin = User::factory()->admin()->create(['name' => 'Admin', 'email' => 'admin@example.com']);
        $document = $this->makeDocument($owner);

        $this->actingAs($admin)
            ->delete('/'.$document->id)
            ->assertRedirect(route('docs.index'));

        $log = ActivityLog::sole();
        $this->assertSame(ActivityLog::ACTION_DOCUMENT_DELETED, $log->action);
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertSame('Admin', $log->actor_name);
        $this->assertSame('admin@example.com', $log->actor_email);
        $this->assertSame($document->id, $log->document_id);
        $this->assertSame('report.txt', $log->document_name);
    }

    public function test_denied_delete_writes_no_audit_row(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $document = $this->makeDocument($owner);

        $this->actingAs($other)->delete('/'.$document->id)->assertForbidden();

        $this->assertSame(1, Document::count());
        $this->assertSame(0, ActivityLog::count());
    }

    public function test_actor_snapshots_survive_rename_and_delete(): void
    {
        $actor = User::factory()->create(['name' => 'Original', 'email' => 'orig@example.com']);
        $file = UploadedFile::fake()->createWithContent('report.txt', 'plain text body');

        $this->actingAs($actor)->post('/', ['file' => $file]);
        $document = Document::sole();

        $actor->update(['name' => 'Renamed', 'email' => 'renamed@example.com']);
        $actor->delete();

        // Deleting the user cascades their documents; the audit rows keep
        // the actor and document snapshots regardless.
        $this->assertSame(0, User::count());
        $this->assertSame(0, Document::count());

        $log = ActivityLog::sole();
        $this->assertNull($log->actor_id);
        $this->assertSame('Original', $log->actor_name);
        $this->assertSame('orig@example.com', $log->actor_email);
        $this->assertSame($document->id, $log->document_id);
        $this->assertSame('report.txt', $log->document_name);
    }

    public function test_audit_failure_rolls_back_upload_and_removes_stored_file(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('report.txt', 'plain text body');

        $logger = \Mockery::mock(ActivityLogger::class);
        $logger->shouldReceive('record')->andThrow(new \RuntimeException('audit write failed'));
        $this->app->instance(ActivityLogger::class, $logger);

        $response = $this->actingAs($user)->post('/', ['file' => $file]);

        $response->assertServerError();
        // The document row rolled back and the private upload was removed —
        // a failed audit write can never orphan a file or log an upload
        // that does not exist.
        $this->assertSame(0, Document::count());
        $this->assertSame(0, ActivityLog::count());
        $this->assertSame([], Storage::disk('local')->allFiles('documents'));
    }

    public function test_audit_failure_on_delete_keeps_document_and_files(): void
    {
        $user = User::factory()->create();
        Storage::disk('local')->put('documents/report.txt', 'content');
        $document = $this->makeDocument($user);

        $logger = \Mockery::mock(ActivityLogger::class);
        $logger->shouldReceive('record')->andThrow(new \RuntimeException('audit write failed'));
        $this->app->instance(ActivityLogger::class, $logger);

        $response = $this->actingAs($user)->delete('/'.$document->id);

        $response->assertServerError();
        // The log write runs before the delete inside the transaction, so a
        // failure leaves document and file untouched.
        $this->assertSame(1, Document::count());
        $this->assertSame(0, ActivityLog::count());
        Storage::disk('local')->assertExists('documents/report.txt');
    }
}
