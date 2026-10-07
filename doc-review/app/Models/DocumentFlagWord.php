<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentFlagWord extends Model
{
    use HasFactory;

    protected $fillable = [
        'word',
        'suggested_replacement',
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
