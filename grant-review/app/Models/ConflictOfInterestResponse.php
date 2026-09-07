<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConflictOfInterestResponse extends Model
{
    public const STATUS_CLEAR = 'clear';

    public const STATUS_CONFLICT = 'potential_conflict';

    protected $fillable = [
        'declaration_id',
        'submission_id',
        'status',
        'description',
    ];

    public function declaration(): BelongsTo
    {
        return $this->belongsTo(ConflictOfInterestDeclaration::class, 'declaration_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function isConflict(): bool
    {
        return $this->status === self::STATUS_CONFLICT;
    }
}
