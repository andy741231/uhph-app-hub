<?php

namespace Tests\Feature;

use App\Models\Round;
use App\Models\RoundInvitation;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmitterWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitter_role_methods_and_attributes(): void
    {
        $submitter = User::factory()->create([
            'role' => 'submitter',
        ]);

        $this->assertTrue($submitter->isSubmitter());
        $this->assertFalse($submitter->isAdmin());
        $this->assertFalse($submitter->isReviewer());
    }

    public function test_submitter_is_redirected_to_submitter_submissions_on_dashboard(): void
    {
        $submitter = User::factory()->create([
            'role' => 'submitter',
        ]);

        $response = $this->actingAs($submitter)->get('/dashboard');

        $response->assertRedirect(route('submitter.submissions.index'));
    }

    public function test_submitter_can_create_draft_and_submit(): void
    {
        Storage::fake('local');

        $submitter = User::factory()->create([
            'role' => 'submitter',
        ]);

        $round = Round::create([
            'name' => 'Spring 2027 Grants',
            'opens_at' => now()->subDay(),
            'deadline_at' => now()->addDays(10),
            'status' => 'open',
        ]);

        RoundInvitation::create([
            'round_id' => $round->id,
            'user_id' => $submitter->id,
        ]);

        $file = UploadedFile::fake()->create('proposal.pdf', 500, 'application/pdf');

        $response = $this->actingAs($submitter)->post(route('submitter.submissions.store'), [
            'round_id' => $round->id,
            'title' => 'My Novel Project',
            'abstract' => 'This is the project abstract.',
            'amount_requested' => 25000.00,
            'pdf' => $file,
        ]);

        $response->assertRedirect(route('submitter.submissions.index'));
        $this->assertDatabaseHas('submissions', [
            'round_id' => $round->id,
            'submitter_id' => $submitter->id,
            'title' => 'My Novel Project',
            'status' => 'draft',
        ]);

        $submission = Submission::where('submitter_id', $submitter->id)->first();
        $this->assertNotNull($submission);
        $this->assertEquals($submitter->id, $submission->submitter->id);

        // Submit the draft
        $submitResponse = $this->actingAs($submitter)->post(route('submitter.submissions.submit', $submission));
        $submitResponse->assertRedirect(route('submitter.submissions.show', $submission));

        $this->assertEquals('submitted', $submission->fresh()->status);
        $this->assertNotNull($submission->fresh()->submitted_at);
    }

    public function test_admin_can_create_submitter(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'first_name' => 'Jane',
            'last_name' => 'Submitter',
            'email' => 'jane.submitter@uh.edu',
            'role' => 'submitter',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'jane.submitter@uh.edu',
            'role' => 'submitter',
            'first_name' => 'Jane',
            'last_name' => 'Submitter',
        ]);
    }

    public function test_admin_can_bulk_import_submitters_via_csv(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $round = Round::create([
            'name' => 'Fall 2027 Grants',
            'opens_at' => now(),
            'deadline_at' => now()->addDays(30),
            'status' => 'open',
        ]);

        $csvContent = "email,name,department\nsubmitter1@uh.edu,Submitter One,Engineering\nsubmitter2@uh.edu,Submitter Two,Physics";
        $csvFile = UploadedFile::fake()->createWithContent('submitters.csv', $csvContent);

        $response = $this->actingAs($admin)->post(route('admin.users.import'), [
            'round_id' => $round->id,
            'csv' => $csvFile,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'submitter1@uh.edu',
            'role' => 'submitter',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'submitter2@uh.edu',
            'role' => 'submitter',
        ]);
    }

    public function test_admin_review_results_displays_submitter_name(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $submitter = User::factory()->create([
            'first_name' => 'Alice',
            'last_name' => 'Submitter',
            'role' => 'submitter',
        ]);

        $round = Round::create([
            'name' => 'Spring 2027 Grants',
            'opens_at' => now()->subDay(),
            'deadline_at' => now()->addDays(10),
            'status' => 'open',
        ]);

        $submission = Submission::create([
            'round_id' => $round->id,
            'submitter_id' => $submitter->id,
            'title' => 'Quantum AI Proposal',
            'abstract' => 'Proposal description',
            'amount_requested' => 50000,
            'pdf_path' => 'submissions/sample.pdf',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.review-results.index'));
        $response->assertOk();
        $response->assertSee('Alice Submitter');
        $response->assertSee('Submitter');
    }
}
