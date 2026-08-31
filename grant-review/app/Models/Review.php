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

    /**
     * The numeric score fields used to compute an average across criteria.
     * Factor 3 is a boolean sufficiency flag, not a 1–9 score, so it's excluded.
     */
    public static function numericScoreFields(): array
    {
        return ['score', 'factor1_score', 'factor2_score'];
    }

    /**
     * Average of all numeric scores (Overall Impact, Factor 1, Factor 2) for this review.
     * Returns null if no scores are present.
     */
    public function averageScore(): ?float
    {
        $values = collect(self::numericScoreFields())
            ->map(fn ($field) => $this->$field)
            ->filter(fn ($value) => $value !== null);

        return $values->isNotEmpty() ? round((float) $values->avg(), 2) : null;
    }
}
