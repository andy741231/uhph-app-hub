<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Uh\AppHub\Contracts\MapsHubIdentity;

class HubIdentityService implements MapsHubIdentity
{
    public function resolve(array $identity): User
    {
        return DB::transaction(function () use ($identity): User {
            $email = strtolower(trim($identity['email']));
            $bySubject = User::where('sso_sub', $identity['subject'])->lockForUpdate()->first();
            $byEmail = User::where('email', $email)->lockForUpdate()->first();

            if ($bySubject && $byEmail && ! $bySubject->is($byEmail)) {
                throw new ConflictHttpException('The Hub identity conflicts with an existing Grant Review account.');
            }

            if (! $bySubject && $byEmail?->sso_sub && ! hash_equals($byEmail->sso_sub, $identity['subject'])) {
                throw new ConflictHttpException('The email address is linked to a different Hub identity.');
            }

            $user = $bySubject ?? $byEmail;
            [$firstName, $lastName] = $this->splitName($identity['name']);

            if (! $user) {
                $user = new User([
                    'email' => $email,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                ]);
            }

            $user->email = $email;
            $user->first_name = $user->first_name ?: $firstName;
            $user->last_name = $user->last_name ?: $lastName;
            $user->sso_sub = $identity['subject'];
            $user->role = $identity['role'];
            $user->status = 'active';
            $user->invite_token_hash = null;
            $user->invite_expires_at = null;

            if ($identity['role'] !== 'admin') {
                $user->password_hash = null;
            }

            $user->save();

            return $user;
        });
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [];

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }
}
