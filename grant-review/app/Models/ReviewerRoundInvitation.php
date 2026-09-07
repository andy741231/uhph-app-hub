<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReviewerRoundInvitation extends Model
{
    protected $fillable = [
        'round_id',
        'reviewer_id',
        'invited_by',
        'invited_at',
        'notification_sent_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
            'notification_sent_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function declarations(): HasMany
    {
        return $this->hasMany(ConflictOfInterestDeclaration::class, 'reviewer_round_invitation_id');
    }

    public function latestDeclaration(): HasOne
    {
        return $this->hasOne(ConflictOfInterestDeclaration::class, 'reviewer_round_invitation_id')->latestOfMany('declared_at');
    }

    /**
     * The reviewer's current (non-superseded) declaration for this round,
     * matched by reviewer+round so legacy declarations — which predate
     * the invitation linkage — are found as well.
     */
    public function currentDeclaration(): ?ConflictOfInterestDeclaration
    {
        return ConflictOfInterestDeclaration::query()
            ->where('reviewer_id', $this->reviewer_id)
            ->where('round_id', $this->round_id)
            ->current()
            ->first();
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    /**
     * Lifecycle label derived from invitation + declaration state.
     */
    public function statusLabel(): string
    {
        if ($this->revoked_at !== null) {
            return 'Revoked';
        }

        $declaration = $this->currentDeclaration();

        if ($declaration === null) {
            return 'Awaiting declaration';
        }

        return $declaration->isStale() ? 'Update required' : 'Declared';
    }
}
