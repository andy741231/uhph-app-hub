<?php

namespace App\Models;

use Database\Factories\ReviewAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReviewAssignment extends Model
{
    /** @use HasFactory<ReviewAssignmentFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'submission_id',
        'reviewer_id',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }
}
