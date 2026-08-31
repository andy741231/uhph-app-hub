<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Models\AuthorizationCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;

class TokenController extends Controller
{
    public function __invoke(
        Request $request,
        ApplicationClientAuthenticator $clients,
        ApplicationActorToken $actorTokens,
    ): JsonResponse {

        if (app()->isProduction() && ! $request->secure()) {
            return $this->error('invalid_request', 'HTTPS is required.', 400);
        }

        $application = $clients->authenticate($request);

        if (! $application) {
            return $this->error('invalid_client', 'Client authentication failed.', 401)
                ->header('WWW-Authenticate', 'Basic realm="UHPH App Hub SSO"');
        }

        $validator = Validator::make($request->all(), [
            'grant_type' => ['required', 'in:authorization_code'],
            'code' => ['required', 'string', 'max:512'],
            'redirect_uri' => ['required', 'string', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return $this->error('invalid_request', 'The token request is incomplete or invalid.', 400);
        }

        $input = $validator->validated();
        $identity = DB::transaction(function () use ($application, $input, $actorTokens): ?array {
            $authorizationCode = AuthorizationCode::query()
                ->where('token_hash', hash('sha256', $input['code']))
                ->lockForUpdate()
                ->first();

            if (! $authorizationCode
                || $authorizationCode->application_id !== $application->id
                || $authorizationCode->consumed_at
                || $authorizationCode->expires_at->isPast()
                || ! hash_equals($authorizationCode->redirect_uri, $input['redirect_uri'])
                || ! hash_equals((string) $application->callback_url, $input['redirect_uri'])) {
                return null;
            }

            $user = $authorizationCode->user;

            if (! $user?->isActive()) {
                return null;
            }

            $assignments = $user->applications()
                ->where('enabled', true)
                ->get();
            $assignment = $assignments->firstWhere('id', $application->id);

            if (! $assignment) {
                return null;
            }

            $authorizationCode->forceFill(['consumed_at' => now()])->save();

            return [
                'token_type' => 'hub_identity',
                'subject' => $user->public_id,
                'email' => $user->email,
                'name' => $user->name,
                'application' => $application->key,
                'role' => $assignment->pivot->role,
                'application_count' => $assignments->count(),
                'logout_url' => URL::signedRoute('sso.logout', ['application' => $application->key]),
                'actor_token' => $actorTokens->issue($user, $application),
            ];
        });

        if (! $identity) {
            return $this->error('invalid_grant', 'The authorization code is invalid, expired, or already used.', 400);
        }

        return response()->json($identity)
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }

    private function error(string $error, string $description, int $status): JsonResponse
    {
        return response()->json([
            'error' => $error,
            'error_description' => $description,
        ], $status)->header('Cache-Control', 'no-store, private');
    }
}
