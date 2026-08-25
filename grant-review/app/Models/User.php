<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'email',
        'password_hash',
        'first_name',
        'last_name',
        'department',
        'phone',
        'title',
        'peoplesoft_id',
        'investigator_type',
        'early_stage_investigator',
        'new_investigator',
        'role',
        'status',
        'invite_token_hash',
        'invite_expires_at',
        'sso_sub',
    ];

    protected $hidden = [
        'password_hash',
        'invite_token_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'invite_expires_at' => 'datetime',
            'early_stage_investigator' => 'boolean',
            'new_investigator' => 'boolean',
        ];
    }

    public function hasCompleteProfile(): bool
    {
        return filled($this->phone)
            && filled($this->department)
            && filled($this->title)
            && preg_match('/^\d{7,20}$/', (string) $this->peoplesoft_id) === 1
            && in_array($this->investigator_type, ['pi', 'other'], true);
    }

    public function roundsInvitedTo(): BelongsToMany
    {
        return $this->belongsToMany(Round::class, 'round_invitations')
            ->withPivot('invited_at');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'submitter_id');
    }

    public function reviewAssignments(): HasMany
    {
        return $this->hasMany(ReviewAssignment::class, 'reviewer_id');
    }

    public function decisionsMade(): HasMany
    {
        return $this->hasMany(Decision::class, 'decided_by');
    }

    public function conflictOfInterestDeclarations(): HasMany
    {
        return $this->hasMany(ConflictOfInterestDeclaration::class, 'reviewer_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSubmitter(): bool
    {
        return $this->role === 'submitter';
    }

    public function isReviewer(): bool
    {
        return $this->role === 'reviewer';
    }

    /**
     * Override the default password column to use password_hash.
     */
    public function getAuthPassword(): ?string
    {
        return $this->password_hash;
    }

    /**
     * Get the user's full name (first + last).
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => Str::lower(trim($value)),
        );
    }

    /**
     * Send the password reset notification using our branded template.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
