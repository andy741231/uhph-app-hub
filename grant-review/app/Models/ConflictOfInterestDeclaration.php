<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConflictOfInterestDeclaration extends Model
{
    protected $fillable = [
        'reviewer_id',
        'round_id',
        'declared_at',
    ];

    protected $casts = [
        'declared_at' => 'datetime',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ConflictOfInterestEntry::class, 'declaration_id');
    }

    /**
     * Whether the reviewer declared any conflicts in this round.
     */
    public function hasConflicts(): bool
    {
        return $this->entries()->exists();
    }
}
