<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'path',
        'callback_url',
        'frontchannel_logout_path',
        'client_id',
        'client_secret_hash',
        'roles',
        'enabled',
        'sort_order',
    ];

    protected $hidden = [
        'client_secret_hash',
    ];

    protected function casts(): array
    {
        return [
            'roles' => 'array',
            'enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'granted_by', 'granted_at'])
            ->withTimestamps();
    }

    public function launchAudits(): HasMany
    {
        return $this->hasMany(ApplicationLaunchAudit::class);
    }

    public function authorizationCodes(): HasMany
    {
        return $this->hasMany(AuthorizationCode::class);
    }

    public function launchUrl(): string
    {
        return $this->hasSsoCredentials() && $this->hasSafePath()
            ? $this->path
            : route('applications.launch', $this);
    }

    public function hasSsoCredentials(): bool
    {
        return filled($this->client_id) && filled($this->client_secret_hash) && filled($this->callback_url);
    }

    public function hasSafePath(): bool
    {
        return preg_match('#^/apps/[A-Za-z0-9_~-]+(?:/[A-Za-z0-9._~-]+)*$#', $this->path) === 1
            && preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $this->path) !== 1;
    }

    public function hasSafeFrontchannelLogoutPath(): bool
    {
        return is_string($this->frontchannel_logout_path)
            && preg_match('#^/apps/[A-Za-z0-9_~-]+(?:/[A-Za-z0-9._~-]+)*$#', $this->frontchannel_logout_path) === 1
            && preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $this->frontchannel_logout_path) !== 1;
    }

    public function iconUrl(): string
    {
        return rtrim($this->path, '/').'/favicon.ico';
    }

    public function iconInitial(): string
    {
        return collect(preg_split('/[\s-]+/', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    public function iconColorClass(): string
    {
        return 'icon-'.(crc32($this->key) % 8);
    }
}
