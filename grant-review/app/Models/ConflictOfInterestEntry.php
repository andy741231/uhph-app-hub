<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConflictOfInterestEntry extends Model
{
    protected $fillable = [
        'declaration_id',
        'submission_id',
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
}
