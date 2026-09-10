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
    ];

    protected function casts(): array
    {
        return [
            'amount_requested' => 'decimal:2',
            'submitted_at' => 'datetime',
            'reviews_released_to_reviewers_at' => 'datetime',
            'reviews_released_to_submitter_at' => 'datetime',
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
     * Whether reviews are visible to at least one audience. Also the
     * edit-lock condition: once feedback is released to anyone, the
     * underlying reviews must not change underneath that audience.
     */
    public function reviewsReleased(): bool
    {
        return $this->reviewsReleasedToReviewers() || $this->reviewsReleasedToSubmitter();
    }

    public function conflictOfInterestEntries(): HasMany
    {
        return $this->hasMany(ConflictOfInterestEntry::class);
    }
}
