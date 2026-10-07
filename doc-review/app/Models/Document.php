<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'original_name',
        'file_path',
        'pdf_preview_path',
        'mime_type',
        'size',
        'extracted_text',
        'status',
        'flag_count',
        'flagged_pages',
        'rendered_pages',
        'error',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'size' => 'integer',
        'flag_count' => 'integer',
        'flagged_pages' => 'array',
        'rendered_pages' => 'array',
    ];

    /**
     * Delete the underlying private-disk file when a document is removed.
     */
    protected static function booted(): void
    {
        static::deleting(function (Document $document) {
            if ($document->file_path) {
                Storage::disk('local')->delete($document->file_path);
            }
            if ($document->pdf_preview_path) {
                Storage::disk('local')->delete($document->pdf_preview_path);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function flags(): HasMany
    {
        return $this->hasMany(DocumentFlag::class);
    }
}
