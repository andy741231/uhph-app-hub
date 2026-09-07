<?php

namespace Tests\Feature;

use App\Mail\ReviewerAssigned;
use App\Mail\ReviewerScreeningInvited;
use App\Models\ConflictOfInterestDeclaration;
use App\Models\ConflictOfInterestResponse;
use App\Models\Review;
use App\Models\ReviewAssignment;
use App\Models\ReviewerRoundInvitation;
use App\Models\Round;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReviewInvitationTest extends TestCase
{
    use RefreshDatabase;

    private function createRoundWithSubmission(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $submitter = User::factory()->create(['role' => 'submitter']);

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
            'abstract' => 'desc',
            'amount_requested' => 50000,
            'pdf_path' => 'submissions/sample.pdf',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return [$admin, $round, $submission];
    }

    public function test_admin_can_send_screening_invitation_and_email_is_delivered(): void
    {
        Mail::fake();
        [$admin, $round] = $this->createRoundWithSubmission();
        $reviewer = User::factory()->create(['role' => 'reviewer', 'status' => 'active']);

        $response = $this->actingAs($admin)->post(route('admin.review-invitations.store'), [
            'round_id' => $round->id,
            'reviewer_ids' => [$reviewer->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $invitation = ReviewerRoundInvitation::where('round_id', $round->id)
            ->where('reviewer_id', $reviewer->id)
            ->first();

        $this->assertNotNull($invitation);
        $this->assertSame($admin->id, $invitation->invited_by);

        Mail::assertSent(ReviewerScreeningInvited::class, fn ($mail) => $mail->hasTo($reviewer->email));
        $this->assertNotNull($invitation->fresh()->notification_sent_at);
    }

    public function test_invitation_is_idempotent_per_round_and_reviewer(): void
    {
        Mail::fake();
        [$admin, $round] = $this->createRoundWithSubmission();
        $reviewer = User::factory()->create(['role' => 'reviewer', 'status' => 'active']);

        $this->actingAs($admin)->post(route('admin.review-invitations.store'), [
            'round_id' => $round->id,
            'reviewer_ids' => [$reviewer->id],
        ]);

        $this->actingAs($admin)->post(route('admin.review-invitations.store'), [
            'round_id' => $round->id,
            'reviewer_ids' => [$reviewer->id],
        ]);

        $this->assertSame(1, ReviewerRoundInvitation::where('round_id', $round->id)->where('reviewer_id', $reviewer->id)->count());
    }

    public function test_invitation_rejects_non_reviewer_accounts(): void
    {
        Mail::fake();
        [$admin, $round] = $this->createRoundWithSubmission();
        $submitter = User::factory()->create(['role' => 'submitter', 'status' => 'active']);

        $response = $this->actingAs($admin)->from(route('admin.review-invitations.index'))->post(route('admin.review-invitations.store'), [
            'round_id' => $round->id,
            'reviewer_ids' => [$submitter->id],
        ]);

        $response->assertSessionHasErrors('reviewer_ids');
        $this->assertSame(0, ReviewerRoundInvitation::count());
        Mail::assertNothingSent();
    }

    public function test_invited_reviewer_can_access_coi_form_without_assignment(): void
    {
        [$admin, $round] = $this->createRoundWithSubmission();
        $reviewer = User::factory()->create(['role' => 'reviewer', 'status' => 'active']);

        ReviewerRoundInvitation::create([
            'round_id' => $round->id,
            'reviewer_id' => $reviewer->id,
            'invited_at' => now(),
        ]);

        $this->actingAs($reviewer)
            ->get(route('reviewer.conflicts.create', $round))
            ->assertOk()
            ->assertSee('Quantum AI Proposal');
    }

    public function test_uninvited_reviewer_without_assignment_cannot_access_coi_form(): void
    {
        [$admin, $round] = $this->createRoundWithSubmission();
        $reviewer = User::factory()->create(['role' => 'reviewer', 'status' => 'active']);

        $this->actingAs($reviewer)
            ->get(route('reviewer.conflicts.create', $round))
            ->assertForbidden();
    }

    public function test_revoked_invitation_blocks_coi_access(): void
    {
        [$admin, $round] = $this->createRoundWithSubmission();
        $reviewer = User::factory()->create(['role' => 'reviewer', 'status' => 'active']);

        ReviewerRoundInvitation::create([
            'round_id' => $round->id,
            'reviewer_id' => $reviewer->id,
            'invited_at' => now(),
            'revoked_at' => now(),
        ]);

        $this->actingAs($reviewer)
            ->get(route('reviewer.conflicts.create', $round))
            ->assertForbidden();
    }

    public function test_invited_reviewer_can_declare_without_assignment(): void
    {
        Mail::fake();
        [$admin, $round, $submission] = $this->createRoundWithSubmission();
        $reviewer = User::factory()->create(['role' => 'reviewer', 'status' => 'active']);

        ReviewerRoundInvitation::create([
            'round_id' => $round->id,
            'reviewer_id' => $reviewer->id,
            'invited_at' => now(),
        ]);

        $this->actingAs($reviewer)
            ->post(route('reviewer.conflicts.store', $round), [
                'conflicts' => [
                    $submission->id => ['submission_id' => $submission->id, 'has_conflict' => false, 'description' => ''],
                ],
            ])
            ->assertRedirect(route('reviewer.dashboard'));

        $declaration = ConflictOfInterestDeclaration::where('reviewer_id', $reviewer->id)
            ->where('round_id', $round->id)
            ->current()
            ->first();

        $this->assertNotNull($declaration);
        $this->assertCount(1, $declaration->responses);
        $this->assertSame(ConflictOfInterestResponse::STATUS_CLEAR, $declaration->responses->first()->status);
    }

    public function test_assignment_rejects_reviewer_without_invitation(): void
    {
        [$admin, $round, $submission] = $this->createRoundWithSubmission();
        $reviewer = User::factory()->create(['role' => 'reviewer', 'status' => 'active']);

        $response = $this->actingAs($admin)
            ->from(route('admin.review-assignments.index'))
            ->put(route('admin.review-assignments.update', $submission), [
                'reviewer_ids' => [$reviewer->id],
            ]);

        $response->assertSessionHasErrors('reviewer_ids');
        $this->assertSame(0, ReviewAssignment::where('submission_id', $submission->id)->count());
    }

    public function test_assignment_allows_invited_reviewer_with_clear_declaration(): void
    {
        Mail::fake();
        [$admin, $round, $submission] = $this->createRoundWithSubmission();
        $reviewer = User::factory()->create(['role' => 'reviewer', 'status' => 'active']);

        $invitation = ReviewerRoundInvitation::create([
            'round_id' => $round->id,
            'reviewer_id' => $reviewer->id,
            'invited_at' => now(),
        ]);

        $declaration = ConflictOfInterestDeclaration::create([
            'reviewer_id' => $reviewer->id,
            'round_id' => $round->id,
            'reviewer_round_invitation_id' => $invitation->id,
            'declared_at' => now(),
        ]);
        ConflictOfInterestResponse::create([
            'declaration_id' => $declaration->id,
            'submission_id' => $submission->id,
            'status' => ConflictOfInterestResponse::STATUS_CLEAR,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.review-assignments.update', $submission), [
                'reviewer_ids' => [$reviewer->id],
            ])
            ->assertRedirect();

        $this->assertNotNull(ReviewAssignment::where('submission_id', $submission->id)->where('reviewer_id', $reviewer->id)->first());
        Mail::assertSent(ReviewerAssigned::class);
    }

    public function test_assignment_allows_reviewer_with_reported_conflict(): void
    {
        Mail::fake();
        [$admin, $round, $submission] = $this->createRoundWithSubmission();
        $reviewer = User::factory()->create(['role' => 'reviewer', 'status' => 'active']);

        $invitation = ReviewerRoundInvitation::create([
            'round_id' => $round->id,
            'reviewer_id' => $reviewer->id,
            'invited_at' => now(),
        ]);

        $declaration = ConflictOfInterestDeclaration::create([
            'reviewer_id' => $reviewer->id,
            'round_id' => $round->id,
            'reviewer_round_invitation_id' => $invitation->id,
            'declared_at' => now(),
        ]);
        ConflictOfInterestResponse::create([
            'declaration_id' => $declaration->id,
            'submission_id' => $submission->id,
            'status' => ConflictOfInterestResponse::STATUS_CONFLICT,
            'description' => 'Departmental colleague.',
        ]);

        // Reported conflicts are advisory: the admin decides.
        $this->actingAs($admin)
            ->put(route('admin.review-assignments.update', $submission), [
                'reviewer_ids' => [$reviewer->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertNotNull(ReviewAssignment::where('submission_id', $submission->id)->where('reviewer_id', $reviewer->id)->first());
    }

    public function test_assignment_page_shows_screening_status_badges(): void
    {
        [$admin, $round, $submission] = $this->createRoundWithSubmission();
        $invitedClear = User::factory()->create(['role' => 'reviewer', 'status' => 'active', 'first_name' => 'Clear', 'last_name' => 'Reviewer']);
        $invitedConflict = User::factory()->create(['role' => 'reviewer', 'status' => 'active', 'first_name' => 'Conflict', 'last_name' => 'Reviewer']);
        $awaiting = User::factory()->create(['role' => 'reviewer', 'status' => 'active', 'first_name' => 'Awaiting', 'last_name' => 'Reviewer']);

        foreach ([$invitedClear, $invitedConflict] as $reviewer) {
            $invitation = ReviewerRoundInvitation::create([
                'round_id' => $round->id,
                'reviewer_id' => $reviewer->id,
                'invited_at' => now(),
            ]);

            $declaration = ConflictOfInterestDeclaration::create([
                'reviewer_id' => $reviewer->id,
                'round_id' => $round->id,
                'reviewer_round_invitation_id' => $invitation->id,
                'declared_at' => now(),
            ]);
            ConflictOfInterestResponse::create([
                'declaration_id' => $declaration->id,
                'submission_id' => $submission->id,
                'status' => $reviewer->is($invitedConflict)
                    ? ConflictOfInterestResponse::STATUS_CONFLICT
                    : ConflictOfInterestResponse::STATUS_CLEAR,
                'description' => $reviewer->is($invitedConflict) ? 'Co-author.' : null,
            ]);
        }

        ReviewerRoundInvitation::create([
            'round_id' => $round->id,
            'reviewer_id' => $awaiting->id,
            'invited_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.review-assignments.index', ['round_id' => $round->id]))
            ->assertOk()
            ->assertSee('No conflicts')
            ->assertSee('Potential conflict reported')
            ->assertSee('Co-author.')
            ->assertSee('Awaiting declaration');
    }

    public function test_conflicts_page_shows_pending_invitations(): void
    {
        [$admin, $round] = $this->createRoundWithSubmission();
        $reviewer = User::factory()->create(['role' => 'reviewer', 'status' => 'active', 'first_name' => 'Pending', 'last_name' => 'Reviewer']);

        ReviewerRoundInvitation::create([
            'round_id' => $round->id,
            'reviewer_id' => $reviewer->id,
            'invited_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.conflicts.index'))
            ->assertOk()
            ->assertSee('Pending Reviewer')
            ->assertSee('Awaiting declaration')
            ->assertSee('Assign reviews');
    }

    public function test_revoke_preserves_existing_assignments(): void
    {
        [$admin, $round, $submission] = $this->createRoundWithSubmission();
        $reviewer = User::factory()->create(['role' => 'reviewer', 'status' => 'active']);

        $invitation = ReviewerRoundInvitation::create([
            'round_id' => $round->id,
            'reviewer_id' => $reviewer->id,
            'invited_at' => now(),
        ]);

        $assignment = ReviewAssignment::create([
            'submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
        ]);
        Review::create(['review_assignment_id' => $assignment->id]);

        $this->actingAs($admin)
            ->post(route('admin.review-invitations.revoke', $invitation))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertNotNull($invitation->fresh()->revoked_at);
        $this->assertNotNull(ReviewAssignment::find($assignment->id));
    }
}
