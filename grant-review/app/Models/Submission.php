<?php

namespace App\Models;

use Database\Factories\SubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Submission extends Model
{
    /** @use HasFactory<SubmissionFactory> */
    use HasFactory;

    protected $fillable = [
        'round_id',
        'submitter_id',
        'title',
        'abstract',
        'amount_requested',
        'pdf_path',
        'status',
        'submitted_at',
        'reviews_released_to_reviewers_at',
        'reviews_released_to_reviewers_by',
        'reviews_released_to_submitter_at',
        'reviews_released_to_submitter_by',
        'submission_edit_unlocked_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_requested' => 'decimal:2',
            'submitted_at' => 'datetime',
            'reviews_released_to_reviewers_at' => 'datetime',
            'reviews_released_to_submitter_at' => 'datetime',
            'submission_edit_unlocked_at' => 'datetime',
        ];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitter_id');
    }

    public function reviewAssignments(): HasMany
    {
        return $this->hasMany(ReviewAssignment::class);
    }

    public function decision(): HasOne
    {
        return $this->hasOne(Decision::class);
    }

    public function reviewsReleasedToReviewersBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviews_released_to_reviewers_by');
    }

    public function reviewsReleasedToSubmitterBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviews_released_to_submitter_by');
    }

    public function reviewsComplete(): bool
    {
        $assignments = $this->relationLoaded('reviewAssignments')
            ? $this->reviewAssignments
            : $this->reviewAssignments()->with('review')->get();

        return $assignments->isNotEmpty()
            && $assignments->every(fn (ReviewAssignment $assignment): bool => $assignment->review?->submitted_at !== null);
    }

    public function reviewsReleasedToReviewers(): bool
    {
        return $this->reviews_released_to_reviewers_at !== null;
    }

    public function reviewsReleasedToSubmitter(): bool
    {
        return $this->reviews_released_to_submitter_at !== null;
    }

    /**
     * Whether reviews are visible to at least one audience.
     */
    public function reviewsReleased(): bool
    {
        return $this->reviewsReleasedToReviewers() || $this->reviewsReleasedToSubmitter();
    }

    /**
     * Whether the submitter may edit the proposal right now.
     *
     * The Submitter release button is king: releasing locks the proposal
     * immediately (even before the deadline), and un-releasing reopens it —
     * even after the deadline, since the un-release is recorded in
     * submission_edit_unlocked_at. The round deadline only locks proposals
     * whose reviews were never released to the submitter.
     */
    public function submitterEditAllowed(): bool
    {
        if ($this->reviewsReleasedToSubmitter()) {
            return false;
        }

        return $this->submission_edit_unlocked_at !== null
            || ($this->round !== null && ! now()->gt($this->round->deadline_at));
    }

    public function conflictOfInterestEntries(): HasMany
    {
        return $this->hasMany(ConflictOfInterestEntry::class);
    }
}
