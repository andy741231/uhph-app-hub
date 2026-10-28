<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public const ACTION_LOGIN = 'login';

    public const ACTION_DOCUMENT_UPLOADED = 'document_uploaded';

    public const ACTION_DOCUMENT_DELETED = 'document_deleted';

    public const ACTIONS = [
        self::ACTION_LOGIN,
        self::ACTION_DOCUMENT_UPLOADED,
        self::ACTION_DOCUMENT_DELETED,
    ];

    // Audit rows are append-only: created_at is set explicitly at write time.
    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'actor_name',
        'actor_email',
        'action',
        'document_id',
        'document_name',
        'created_at',
    ];

    protected $casts = [
        'document_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
