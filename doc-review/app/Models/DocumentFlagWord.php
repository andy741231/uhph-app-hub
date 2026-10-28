<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentFlagWord extends Model
{
    use HasFactory;

    /**
     * whole_word: a single word matches only at full word boundaries
     * ("ai" matches "AI"/"(AI)" but not "aim"/"gain"/"training").
     * word_root: legacy stem/prefix matching — a single word matches at a
     * leading boundary only ("divers" matches "diverse"/"diversity").
     * Multi-word phrases match literally in both modes.
     */
    public const MATCH_WHOLE_WORD = 'whole_word';

    public const MATCH_WORD_ROOT = 'word_root';

    public const MATCH_TYPES = [
        self::MATCH_WHOLE_WORD,
        self::MATCH_WORD_ROOT,
    ];

    // New rows created through Eloquent default to the safer whole-word
    // mode. The database column default stays word_root so pre-existing
    // rows and raw legacy inserts keep their original matching semantics.
    protected $attributes = [
        'match_type' => self::MATCH_WHOLE_WORD,
    ];

    protected $fillable = [
        'word',
        'suggested_replacement',
        'match_type',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documentFlags(): HasMany
    {
        return $this->hasMany(DocumentFlag::class);
    }
}
