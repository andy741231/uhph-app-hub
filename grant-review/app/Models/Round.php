<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Round extends Model
{
    /** @use HasFactory<\Database\Factories\RoundFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'opens_at',
        'deadline_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'deadline_at' => 'datetime',
        ];
    }

    public function invitedSubmitters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'round_invitations')
            ->withPivot('invited_at');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
