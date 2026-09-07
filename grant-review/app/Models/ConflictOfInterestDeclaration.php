<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConflictOfInterestDeclaration extends Model
{
    protected $fillable = [
        'reviewer_id',
        'round_id',
        'reviewer_round_invitation_id',
        'declared_at',
        'admin_notified_at',
        'superseded_at',
    ];

    protected $casts = [
        'declared_at' => 'datetime',
        'admin_notified_at' => 'datetime',
        'superseded_at' => 'datetime',
    ];

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('superseded_at');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(ReviewerRoundInvitation::class, 'reviewer_round_invitation_id');
    }

    /**
     * Explicit per-proposal screening responses (clear / potential_conflict).
     */
    public function responses(): HasMany
    {
        return $this->hasMany(ConflictOfInterestResponse::class, 'declaration_id');
    }

    /**
     * Legacy conflict-only rows. Retained during the transition release;
     * new declarations record full coverage via responses().
     */
    public function entries(): HasMany
    {
        return $this->hasMany(ConflictOfInterestEntry::class, 'declaration_id');
    }

    /**
     * Whether the reviewer reported any potential conflicts in this
     * declaration (responses first, legacy entries as fallback).
     */
    public function hasConflicts(): bool
    {
        if ($this->responses()->exists()) {
            return $this->responses()->where('status', ConflictOfInterestResponse::STATUS_CONFLICT)->exists();
        }

        return $this->entries()->exists();
    }

    /**
     * Whether this declaration predates at least one currently eligible
     * proposal in its round, i.e. coverage is incomplete for the round
     * as it stands now.
     */
    public function isStale(): bool
    {
        $eligibleCount = $this->round
            ->submissions()
            ->whereIn('status', ['submitted', 'under_review', 'decided'])
            ->count();

        if ($eligibleCount === 0) {
            return false;
        }

        $coveredCount = $this->responses()->count();

        if ($coveredCount === 0) {
            // Legacy declaration without explicit coverage.
            return true;
        }

        return $coveredCount < $eligibleCount;
    }
}
