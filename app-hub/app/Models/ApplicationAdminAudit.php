<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationAdminAudit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'application_id',
        'actor_user_id',
        'target_user_id',
        'action',
        'details',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }
}
