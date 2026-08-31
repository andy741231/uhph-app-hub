<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Models\ApplicationAdminAudit;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManagedUserController extends Controller
{
    private const ALLOWED_EMAIL_DOMAINS = ['uh.edu', 'central.uh.edu', 'cougarnet.uh.edu'];

    public function index(
        Request $request,
        ApplicationClientAuthenticator $clients,
        ApplicationActorToken $tokens,
    ): JsonResponse {
        [$application] = $this->context($request, $clients, $tokens);
        $users = $application->users()
            ->where('users.status', User::STATUS_ACTIVE)
            ->orderBy('users.name')
            ->get()
            ->map(fn (User $user): array => [
                'subject' => $user->public_id,
                'email' => $user->email,
                'name' => $user->name,
                'role' => $user->pivot->role,
                'status' => $user->status,
            ])
            ->values();

        return response()->json(['application' => $application->key, 'users' => $users])
            ->header('Cache-Control', 'no-store, private');
    }

    public function update(
        Request $request,
        ApplicationClientAuthenticator $clients,
        ApplicationActorToken $tokens,
    ): JsonResponse {
        [$application, $actor] = $this->context($request, $clients, $tokens);
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'role' => Str::lower(trim((string) $request->input('role'))),
        ]);
        $input = $request->validate([
            'subject' => ['nullable', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in($application->roles ?? [])],
        ]);
        $domain = Str::afterLast($input['email'], '@');
        $result = DB::transaction(function () use ($application, $actor, $input, $domain, $request): array {
            $target = isset($input['subject'])
                ? User::where('public_id', $input['subject'])->lockForUpdate()->first()
                : User::where('email', $input['email'])->lockForUpdate()->first();

            if (isset($input['subject']) && (! $target || ! $target->applications()->whereKey($application->id)->exists())) {
                abort(404);
            }
            if ($target?->status === User::STATUS_DISABLED) {
                abort(409, 'The UHPH App Hub identity is disabled.');
            }
            if ($target?->is($actor) && $input['role'] !== 'admin') {
                throw ValidationException::withMessages(['role' => 'You cannot remove your own application administrator access.']);
            }

            $created = false;
            if (! $target) {
                if (! in_array($domain, self::ALLOWED_EMAIL_DOMAINS, true)) {
                    throw ValidationException::withMessages([
                        'email' => 'Use an @uh.edu, @central.uh.edu, or @cougarnet.uh.edu address.',
                    ]);
                }
                $target = User::create([
                    'name' => trim($input['name']),
                    'email' => $input['email'],
                    'password' => Str::random(64),
                    'email_verified_at' => now(),
                    'status' => User::STATUS_ACTIVE,
                    'is_admin' => false,
                ]);
                $created = true;
            }

            $target->applications()->syncWithoutDetaching([
                $application->id => [
                    'role' => $input['role'],
                    'granted_by' => $actor->id,
                    'granted_at' => now(),
                ],
            ]);
            ApplicationAdminAudit::create([
                'application_id' => $application->id,
                'actor_user_id' => $actor->id,
                'target_user_id' => $target->id,
                'action' => $created ? 'user_created_and_assigned' : 'role_assigned',
                'details' => ['role' => $input['role']],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return compact('target', 'created');
        });

        $invitationSent = false;
        if ($result['created']) {
            try {
                $invitationSent = Password::sendResetLink(['email' => $result['target']->email]) === Password::RESET_LINK_SENT;
            } catch (\Throwable) {
                $invitationSent = false;
            }
        }
        $target = $result['target'];

        return response()->json([
            'subject' => $target->public_id,
            'email' => $target->email,
            'name' => $target->name,
            'application' => $application->key,
            'role' => $input['role'],
            'status' => $target->status,
            'created' => $result['created'],
            'invitation_sent' => $invitationSent,
        ], $result['created'] ? 201 : 200)->header('Cache-Control', 'no-store, private');
    }

    public function destroy(
        Request $request,
        string $subject,
        ApplicationClientAuthenticator $clients,
        ApplicationActorToken $tokens,
    ): JsonResponse {
        [$application, $actor] = $this->context($request, $clients, $tokens);
        $target = User::where('public_id', $subject)->firstOrFail();
        abort_if($target->is($actor), 422, 'You cannot revoke your own application administrator access.');
        abort_unless($target->applications()->whereKey($application->id)->exists(), 404);

        DB::transaction(function () use ($application, $actor, $target, $request): void {
            $target->applications()->detach($application->id);
            ApplicationAdminAudit::create([
                'application_id' => $application->id,
                'actor_user_id' => $actor->id,
                'target_user_id' => $target->id,
                'action' => 'access_revoked',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return response()->json([
            'subject' => $target->public_id,
            'application' => $application->key,
            'revoked' => true,
        ])->header('Cache-Control', 'no-store, private');
    }

    private function context(
        Request $request,
        ApplicationClientAuthenticator $clients,
        ApplicationActorToken $tokens,
    ): array {
        abort_if(app()->isProduction() && ! $request->secure(), 400, 'HTTPS is required.');
        $application = $clients->authenticate($request);
        abort_unless($application, 401, 'Client authentication failed.');
        $actor = $tokens->resolveAdmin($request->header('X-Hub-Actor-Token', ''), $application);
        abort_unless($actor, 403, 'Application administrator access is required.');

        return [$application, $actor];
    }
}
