<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationLaunchAudit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'application_id',
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

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
