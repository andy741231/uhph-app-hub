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
        'reviews_released_at',
        'reviews_released_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_requested' => 'decimal:2',
            'submitted_at' => 'datetime',
            'reviews_released_at' => 'datetime',
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

    public function reviewsReleasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviews_released_by');
    }

    public function reviewsComplete(): bool
    {
        $assignments = $this->relationLoaded('reviewAssignments')
            ? $this->reviewAssignments
            : $this->reviewAssignments()->with('review')->get();

        return $assignments->isNotEmpty()
            && $assignments->every(fn (ReviewAssignment $assignment): bool => $assignment->review?->submitted_at !== null);
    }

    public function reviewsReleased(): bool
    {
        return $this->reviews_released_at !== null;
    }

    public function conflictOfInterestEntries(): HasMany
    {
        return $this->hasMany(ConflictOfInterestEntry::class);
    }
}
