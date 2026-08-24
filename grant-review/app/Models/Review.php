<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    /** @use HasFactory<\Database\Factories\ReviewFactory> */
    use HasFactory;

    protected $fillable = [
        'review_assignment_id',
        'score',
        'comments',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'submitted_at' => 'datetime',
        ];
    }

    public function reviewAssignment(): BelongsTo
    {
        return $this->belongsTo(ReviewAssignment::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ReviewRevision::class)->latest('submitted_at');
    }
}
