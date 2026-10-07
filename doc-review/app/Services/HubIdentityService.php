<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Uh\AppHub\Contracts\MapsHubIdentity;

class HubIdentityService implements MapsHubIdentity
{
    /**
     * Resolve a Hub identity payload to a local user.
     *
     * The Hub subject is authoritative. On first sign-in a pre-provisioned
     * local profile may be linked by exact email match, but an email already
     * bound to a different subject is rejected rather than rebound. The Hub
     * application role (admin|user) is mirrored verbatim — it never confers
     * any global Hub privileges.
     */
    public function resolve(array $identity): User
    {
        return DB::transaction(function () use ($identity): User {
            $email = strtolower(trim($identity['email']));
            $users = User::query()
                ->where('hub_subject', $identity['subject'])
                ->orWhere('email', $email)
                ->lockForUpdate()
                ->get();
            $bySubject = $users->firstWhere('hub_subject', $identity['subject']);
            $byEmail = $users->firstWhere('email', $email);

            if ($bySubject && $byEmail && ! $bySubject->is($byEmail)) {
                throw new ConflictHttpException('The Hub identity conflicts with an existing Document Reviewer account.');
            }

            if (! $bySubject
                && $byEmail?->hub_subject
                && ! hash_equals($byEmail->hub_subject, $identity['subject'])) {
                throw new ConflictHttpException('The email address is linked to a different Hub identity.');
            }

            $user = $bySubject ?? $byEmail ?? new User;
            $user->name = $identity['name'];
            $user->email = $email;
            $user->hub_subject = $identity['subject'];
            $user->role = $identity['role'];
            $user->status = 'active';

            if (! $user->exists || $user->isDirty()) {
                $user->save();
            }

            return $user;
        });
    }
}
