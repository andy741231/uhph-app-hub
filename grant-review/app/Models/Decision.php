<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Decision extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'submission_id',
        'outcome',
        'amount_awarded',
        'decided_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_awarded' => 'decimal:2',
            'decided_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
