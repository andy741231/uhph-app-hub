<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AuthorizationCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;

class TokenController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (app()->isProduction() && ! $request->secure()) {
            return $this->error('invalid_request', 'HTTPS is required.', 400);
        }

        [$clientId, $clientSecret] = $this->basicCredentials($request);
        $application = $clientId ? Application::where('client_id', $clientId)->first() : null;
        $providedHash = hash('sha256', (string) $clientSecret);
        $expectedHash = $application?->client_secret_hash ?? str_repeat('0', 64);

        if (! $clientSecret || ! hash_equals($expectedHash, $providedHash) || ! $application?->enabled) {
            return $this->error('invalid_client', 'Client authentication failed.', 401)
                ->header('WWW-Authenticate', 'Basic realm="App Hub SSO"');
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
        $identity = DB::transaction(function () use ($application, $input): ?array {
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
            ];
        });

        if (! $identity) {
            return $this->error('invalid_grant', 'The authorization code is invalid, expired, or already used.', 400);
        }

        return response()->json($identity)
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }

    private function basicCredentials(Request $request): array
    {
        $authorization = $request->header('Authorization', '');

        if (! str_starts_with($authorization, 'Basic ')) {
            return [null, null];
        }

        $decoded = base64_decode(substr($authorization, 6), true);

        if ($decoded === false || ! str_contains($decoded, ':')) {
            return [null, null];
        }

        return explode(':', $decoded, 2);
    }

    private function error(string $error, string $description, int $status): JsonResponse
    {
        return response()->json([
            'error' => $error,
            'error_description' => $description,
        ], $status)->header('Cache-Control', 'no-store, private');
    }
}
