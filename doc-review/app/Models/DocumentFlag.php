<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentFlag extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'flag_word_id',
        'occurrences',
    ];

    protected $casts = [
        'occurrences' => 'integer',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function flagWord(): BelongsTo
    {
        return $this->belongsTo(DocumentFlagWord::class, 'flag_word_id');
    }
}
