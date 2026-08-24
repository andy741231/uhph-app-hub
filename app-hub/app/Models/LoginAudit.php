<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginAudit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'email',
        'succeeded',
        'failure_reason',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'succeeded' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
