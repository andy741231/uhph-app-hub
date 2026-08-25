<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewRevision extends Model
{
    protected $fillable = [
        'review_id',
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

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }
}
