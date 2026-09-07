<?php

namespace App\Console\Commands;

use App\Models\ConflictOfInterestResponse;
use App\Models\ReviewerRoundInvitation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillReviewerScreening extends Command
{
    protected $signature = 'reviewer-screening:backfill {--dry-run : Show what would change without writing}';

    protected $description = 'Backfill COI responses from legacy conflict entries and create grandfathered screening invitations for existing review assignments';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $declarationCount = $this->backfillResponses($dryRun);
        $invitationCount = $this->backfillInvitations($dryRun);

        if ($dryRun) {
            $this->info("DRY RUN — nothing was written. Would backfill {$declarationCount} declaration(s) with responses and create {$invitationCount} invitation(s).");
        } else {
            $this->info("Backfill complete: {$declarationCount} declaration(s) received responses, {$invitationCount} invitation(s) created.");
        }

        return self::SUCCESS;
    }

    /**
     * Convert legacy conflict entries into explicit potential_conflict
     * responses on the same declaration. Idempotent: declarations that
     * already have responses are skipped.
     */
    private function backfillResponses(bool $dryRun): int
    {
        $declarations = DB::table('conflict_of_interest_declarations')
            ->whereNotExists(fn ($query) => $query
                ->selectRaw(1)
                ->from('conflict_of_interest_responses as r')
                ->whereColumn('r.declaration_id', 'conflict_of_interest_declarations.id'))
            ->get(['id']);

        $entries = DB::table('conflict_of_interest_entries')
            ->whereIn('declaration_id', $declarations->pluck('id'))
            ->get();

        if ($dryRun || $declarations->isEmpty()) {
            return $declarations->count();
        }

        foreach ($entries as $entry) {
            ConflictOfInterestResponse::create([
                'declaration_id' => $entry->declaration_id,
                'submission_id' => $entry->submission_id,
                'status' => ConflictOfInterestResponse::STATUS_CONFLICT,
                'description' => $entry->description,
            ]);
        }

        return $declarations->count();
    }

    /**
     * Grandfather existing reviewer+round assignment pairs as screening
     * invitations so current assignments remain manageable under the new
     * assignment guard. No historical emails are sent. Idempotent.
     */
    private function backfillInvitations(bool $dryRun): int
    {
        $pairs = DB::table('review_assignments')
            ->join('submissions', 'submissions.id', '=', 'review_assignments.submission_id')
            ->selectRaw('submissions.round_id, review_assignments.reviewer_id, MIN(review_assignments.assigned_at) as first_assigned_at')
            ->whereNotExists(fn ($query) => $query
                ->selectRaw(1)
                ->from('reviewer_round_invitations as i')
                ->whereColumn('i.round_id', 'submissions.round_id')
                ->whereColumn('i.reviewer_id', 'review_assignments.reviewer_id'))
            ->groupBy('submissions.round_id', 'review_assignments.reviewer_id')
            ->get();

        if ($dryRun || $pairs->isEmpty()) {
            return $pairs->count();
        }

        foreach ($pairs as $pair) {
            ReviewerRoundInvitation::create([
                'round_id' => $pair->round_id,
                'reviewer_id' => $pair->reviewer_id,
                'invited_at' => $pair->first_assigned_at ?? now(),
            ]);
        }

        return $pairs->count();
    }
}
