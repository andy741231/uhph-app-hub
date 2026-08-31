<?php

namespace App\Http\Controllers\Sso;

use App\Models\Application;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class ApplicationActorToken
{
    public function issue(User $user, Application $application): string
    {
        return Crypt::encryptString(json_encode([
            'subject' => $user->public_id,
            'application' => $application->key,
            'expires_at' => now()->addMinutes((int) config('hub.application_admin_token_ttl', 20))->timestamp,
        ], JSON_THROW_ON_ERROR));
    }

    public function resolveAdmin(string $token, Application $application): ?User
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return null;
        }

        if (! is_array($payload)
            || ! is_string($payload['subject'] ?? null)
            || ! is_string($payload['application'] ?? null)
            || ! is_int($payload['expires_at'] ?? null)
            || ! hash_equals($application->key, $payload['application'])
            || $payload['expires_at'] < now()->timestamp) {
            return null;
        }

        return User::query()
            ->where('public_id', $payload['subject'])
            ->where('status', User::STATUS_ACTIVE)
            ->whereHas('applications', fn ($query) => $query
                ->whereKey($application->id)
                ->where('application_user.role', 'admin'))
            ->first();
    }
}
