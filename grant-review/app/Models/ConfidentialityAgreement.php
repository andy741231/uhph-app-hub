<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfidentialityAgreement extends Model
{
    protected $fillable = [
        'user_id',
        'round_id',
        'version',
        'content',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }
}
