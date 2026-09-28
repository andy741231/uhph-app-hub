<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AllReviewsComplete;
use App\Mail\ConflictOfInterestDeclared;
use App\Mail\DecisionRecorded;
use App\Mail\InviteUser;
use App\Mail\ProfileCompleted;
use App\Mail\ProposalSubmitted;
use App\Mail\ReviewerAssigned;
use App\Mail\ReviewerCoiUpdateRequested;
use App\Mail\ReviewerScreeningInvited;
use App\Mail\ReviewsAvailable;
use App\Mail\ReviewSubmitted;
use App\Mail\SubmissionConfirmation;
use App\Models\ConflictOfInterestDeclaration;
use App\Models\ConflictOfInterestResponse;
use App\Models\Decision;
use App\Models\Review;
use App\Models\ReviewAssignment;
use App\Models\ReviewerRoundInvitation;
use App\Models\Round;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\View\View;

class EmailPreviewController extends Controller
{
    public function show(Request $request, string $key): View
    {
        [$label, $mailable] = $this->template($key, $request);

        return view('admin.email-preview', [
            'key' => $key,
            'label' => $label,
            'subject' => $mailable instanceof Mailable ? $mailable->envelope()->subject : $mailable->subject,
        ]);
    }

    public function raw(Request $request, string $key): Response
    {
        [, $mailable] = $this->template($key, $request);

        return response($mailable->render());
    }

    private function loginMode(Request $request): string
    {
        if (! config('hub.enabled')) {
            return 'grant-review-local';
        }

        $mode = $request->session()->get(config('hub.login_mode_session_key', 'hub_login_mode'));

        return in_array($mode, ['sso', 'local', 'hybrid'], true) ? $mode : 'hybrid';
    }

    private function template(string $key, Request $request): array
    {
        $loginMode = $this->loginMode($request);
        $key = $key === 'invitation-hub'
            ? match ($loginMode) {
                'sso' => 'invitation-hub-sso',
                'local' => 'invitation-hub-password',
                'hybrid' => 'invitation-hub-hybrid',
                default => 'invitation',
            }
        : $key;

        $round = Round::factory()->make(['name' => 'Spring 2026 Pilot', 'status' => 'open']);
        $round->id = 1;

        $submitter = User::factory()->make([
            'first_name' => 'Jane', 'last_name' => 'Doe',
            'email' => 'jane.doe@uh.edu', 'role' => 'submitter',
        ]);
        $reviewer = User::factory()->make([
            'first_name' => 'John', 'last_name' => 'Smith',
            'email' => 'john.smith@uh.edu', 'role' => 'reviewer',
            'department' => 'Internal Medicine',
        ]);

        $submission = Submission::factory()->make([
            'title' => 'Novel Biomarkers for Early Detection of Pancreatic Cancer',
            'amount_requested' => 47500,
            'submitted_at' => now()->subDays(4),
        ]);
        $submission->id = 1;
        $submission->setRelation('round', $round);
        $submission->setRelation('submitter', $submitter);

        $invitation = new ReviewerRoundInvitation(['status' => 'pending']);
        $invitation->id = 1;
        $invitation->setRelation('round', $round);
        $invitation->setRelation('user', $reviewer);

        $conflictResponse = new ConflictOfInterestResponse([
            'status' => ConflictOfInterestResponse::STATUS_CONFLICT,
            'description' => 'Recent research collaborator on a related proposal.',
        ]);
        $conflictResponse->setRelation('submission', $submission);
        $clearResponse = new ConflictOfInterestResponse(['status' => ConflictOfInterestResponse::STATUS_CLEAR]);
        $clearResponse->setRelation('submission', $submission);

        $declaration = new ConflictOfInterestDeclaration(['declared_at' => now()]);
        $declaration->id = 1;
        $declaration->setRelation('round', $round);
        $declaration->setRelation('responses', collect([$conflictResponse, $clearResponse]));
        $declaration->setRelation('entries', collect());

        $review = new Review(['score' => 8]);
        $review->id = 1;
        $reviewA = new Review(['score' => 9]);
        $reviewA->id = 2;
        $reviewB = new Review(['score' => 7]);
        $reviewB->id = 3;
        $assignmentA = new ReviewAssignment;
        $assignmentA->setRelation('review', $reviewA);
        $assignmentB = new ReviewAssignment;
        $assignmentB->setRelation('review', $reviewB);

        $completedSubmission = clone $submission;
        $completedSubmission->setRelation('reviewAssignments', collect([$assignmentA, $assignmentB]));

        $decision = new Decision(['outcome' => 'funded', 'amount_awarded' => 42000]);

        $hubLoginUrl = config('hub.base_url').'/login?application=grant-review';
        $pilotCentralUrl = config('hub.base_url').'/grant-review';
        $bookmarkLine = "Please bookmark the Pilot Central page for future sign-ins: {$pilotCentralUrl}";
        $hubSsoInvite = (new MailMessage)
            ->subject('Pilot Central — your account is ready')
            ->greeting('Hello Jane Doe,')
            ->line('You have been granted access to Pilot Central through UHPH App Hub.')
            ->action('Sign in with CougarNet', $hubLoginUrl)
            ->line("Use your UH CougarNet credentials to sign in at {$hubLoginUrl}.")
            ->line($bookmarkLine)
            ->line('If you were not expecting this invitation, please ignore this message.');

        $hubHybridInvite = (new MailMessage)
            ->subject('Pilot Central — your account is ready')
            ->greeting('Hello Jane Doe,')
            ->line('You have been granted access to Pilot Central through UHPH App Hub.')
            ->action('Sign in with CougarNet', $hubLoginUrl)
            ->line("Use your UH CougarNet credentials to sign in at {$hubLoginUrl}.")
            ->line('Prefer local sign-in? [Set up an optional UHPH App Hub password]('.config('hub.base_url').'/set-password/preview-token?email=jane.doe%40uh.edu).')
            ->line('The optional password setup link expires in 7 days.')
            ->line($bookmarkLine)
            ->line('If you were not expecting this invitation, please ignore this message.');

        $hubSetPassword = (new MailMessage)
            ->subject('Pilot Central — set your UHPH App Hub password')
            ->greeting('Hello Jane Doe,')
            ->line('You have been granted access to Pilot Central through UHPH App Hub.')
            ->action('Set password', config('hub.base_url').'/set-password/preview-token?email=jane.doe%40uh.edu')
            ->line('This link expires in 7 days.')
            ->line($bookmarkLine)
            ->line('If you were not expecting this invitation, contact your UHPH App Hub administrator.');

        $templates = [
            'invitation' => ['Sign-in invitation — sent by Pilot Central when Hub SSO is off',
                fn (): Mailable => new InviteUser(url('/set-password?token=preview&email=jane.doe%40uh.edu'), 'Jane Doe')],
            'invitation-hub-sso' => ['Sign-in invitation — SSO-only app-aware message sent by UHPH App Hub; offers CougarNet sign-in and may include per-app custom text',
                fn (): Renderable => $hubSsoInvite],
            'invitation-hub-hybrid' => ['Sign-in invitation — hybrid app-aware message sent by UHPH App Hub; offers CougarNet sign-in plus optional local-password setup and may include per-app custom text',
                fn (): Renderable => $hubHybridInvite],
            'invitation-hub-password' => ['Set-password invitation — local-only app-aware message sent by UHPH App Hub; may include optional per-app custom text',
                fn (): Renderable => $hubSetPassword],
            'profile-completed' => ['Profile completed — sent to admins',
                fn (): Mailable => new ProfileCompleted($reviewer)],
            'proposal-submitted' => ['New proposal — sent to admins',
                fn (): Mailable => new ProposalSubmitted($submission)],
            'submission-confirmation' => ['Submission confirmation — sent to submitter',
                fn (): Mailable => new SubmissionConfirmation($submission)],
            'coi-invitation' => ['COI screening invitation — sent to reviewer',
                fn (): Mailable => new ReviewerScreeningInvited($invitation)],
            'coi-update-requested' => ['COI re-declaration request — sent to reviewer',
                fn (): Mailable => new ReviewerCoiUpdateRequested($invitation)],
            'coi-declared' => ['COI declared — sent to admins',
                fn (): Mailable => new ConflictOfInterestDeclared($reviewer, $declaration)],
            'reviewer-assigned' => ['Assignment notice — sent to reviewer',
                fn (): Mailable => new ReviewerAssigned($reviewer, $submission)],
            'review-submitted' => ['Review submitted — sent to admins',
                fn (): Mailable => new ReviewSubmitted($reviewer, $submission, $review)],
            'all-reviews-complete' => ['All reviews complete — sent to admins',
                fn (): Mailable => new AllReviewsComplete($completedSubmission)],
            'decision-recorded' => ['Decision recorded — sent to submitter',
                fn (): Mailable => new DecisionRecorded($submission, $decision)],
            'reviews-available' => ['Reviews available — sent to released audience',
                fn (): Mailable => new ReviewsAvailable($submitter, $submission, url('/submissions/1'))],
        ];

        abort_unless(isset($templates[$key]), 404);

        return [$templates[$key][0], $templates[$key][1]()];
    }
}
