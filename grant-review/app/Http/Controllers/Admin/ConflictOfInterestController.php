<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConflictOfInterestDeclaration;
use App\Models\ReviewerRoundInvitation;
use App\Models\Round;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConflictOfInterestController extends Controller
{
    /**
     * Unified COI screening oversight: pending invitations (awaiting
     * declaration) and submitted declarations (current versions), with
     * per-row status and a shortcut into the assignment workflow.
     */
    public function index(Request $request): View
    {
        $roundId = $request->integer('round_id') ?: null;
        $status = in_array($request->query('status'), ['pending', 'conflicts', 'clear', 'update_required'], true)
            ? $request->query('status')
            : null;
        $search = trim((string) $request->query('q', ''));

        // Submitted declarations (latest version per reviewer+round).
        $declarationQuery = ConflictOfInterestDeclaration::query()
            ->with(['reviewer', 'round', 'responses.submission.submitter'])
            ->current()
            ->when($roundId, fn ($query) => $query->where('round_id', $roundId))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->whereHas('reviewer', function ($query) use ($search): void {
                        $query->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })->orWhereHas('responses.submission', fn ($query) => $query->where('title', 'like', "%{$search}%"));
                });
            })
            ->latest('declared_at')
            ->get();

        // Invitations without a declaration yet. Matching is by
        // reviewer+round so legacy declarations count as coverage.
        $pendingInvitations = ReviewerRoundInvitation::query()
            ->with(['reviewer', 'round'])
            ->whereNotExists(function ($query): void {
                $query->selectRaw(1)
                    ->from('conflict_of_interest_declarations as d')
                    ->whereColumn('d.reviewer_id', 'reviewer_round_invitations.reviewer_id')
                    ->whereColumn('d.round_id', 'reviewer_round_invitations.round_id')
                    ->whereNull('d.superseded_at');
            })
            ->when($roundId, fn ($query) => $query->where('round_id', $roundId))
            ->when($search !== '', fn ($query) => $query->whereHas('reviewer', function ($query) use ($search): void {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('invited_at', 'desc')
            ->get();

        // Build uniform rows: pending invitations + declarations.
        $rows = collect();

        foreach ($pendingInvitations as $invitation) {
            if (! $invitation->isActive()) {
                continue;
            }

            $rows->push([
                'type' => 'pending',
                'invitation' => $invitation,
                'declaration' => null,
                'sort' => $invitation->invited_at,
            ]);
        }

        // Active invitations for the declared reviewer+round pairs, so
        // stale rows can offer a resend action.
        $invitationsByPair = ReviewerRoundInvitation::query()
            ->whereNull('revoked_at')
            ->when($declarationQuery->isNotEmpty(), function ($query) use ($declarationQuery): void {
                $query->where(function ($query) use ($declarationQuery): void {
                    foreach ($declarationQuery as $declaration) {
                        $query->orWhere(function ($query) use ($declaration): void {
                            $query->where('round_id', $declaration->round_id)
                                ->where('reviewer_id', $declaration->reviewer_id);
                        });
                    }
                });
            })
            ->get()
            ->keyBy(fn (ReviewerRoundInvitation $invitation) => $invitation->round_id.'-'.$invitation->reviewer_id);

        foreach ($declarationQuery as $declaration) {
            $rows->push([
                'type' => 'declaration',
                'invitation' => $invitationsByPair->get($declaration->round_id.'-'.$declaration->reviewer_id),
                'declaration' => $declaration,
                'sort' => $declaration->declared_at,
            ]);
        }

        $rows = $rows->sortByDesc('sort')->values();

        // Apply the status filter over the unified rows.
        $rows = $rows->filter(function (array $row) use ($status): bool {
            if ($status === null) {
                return true;
            }

            if ($status === 'pending') {
                return $row['type'] === 'pending';
            }

            $declaration = $row['declaration'];

            if ($declaration === null) {
                return false;
            }

            return match ($status) {
                'conflicts' => $declaration->hasConflicts(),
                'clear' => ! $declaration->hasConflicts() && ! $declaration->isStale(),
                'update_required' => $declaration->isStale(),
                default => true,
            };
        })->values();

        $rounds = Round::query()->latest('opens_at')->get(['id', 'name']);

        $conflictCount = fn ($declaration) => $declaration
            ? $declaration->responses->where('status', 'potential_conflict')->count()
            : 0;

        $stats = [
            'pending' => $pendingInvitations->whereNotNull('round_id')->filter(fn ($invitation) => $invitation->isActive())->count(),
            'declarations' => $declarationQuery->count(),
            'with_conflicts' => $declarationQuery->filter(fn (ConflictOfInterestDeclaration $declaration) => $declaration->hasConflicts())->count(),
            'conflicts' => $declarationQuery->sum(fn (ConflictOfInterestDeclaration $declaration) => $conflictCount($declaration)),
        ];

        return view('admin.conflicts.index', [
            'rows' => $rows,
            'rounds' => $rounds,
            'roundId' => $roundId,
            'status' => $status,
            'search' => $search,
            'stats' => $stats,
        ]);
    }
}
