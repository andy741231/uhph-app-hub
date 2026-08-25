<?php

namespace App\Models;

use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    protected $fillable = [
        'review_assignment_id',
        'score',
        'comments',
        'factor1_score',
        'factor1_comments',
        'factor2_score',
        'factor2_comments',
        'factor3_sufficient',
        'factor3_comments',
        'additional_human_subjects',
        'additional_human_subjects_comments',
        'additional_vertebrate_animals',
        'additional_vertebrate_animals_comments',
        'additional_biohazards',
        'additional_biohazards_comments',
        'additional_resubmission',
        'additional_resubmission_comments',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'factor1_score' => 'integer',
            'factor2_score' => 'integer',
            'factor3_sufficient' => 'boolean',
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
