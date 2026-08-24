<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationLaunchAudit;
use App\Models\AuthorizationCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthorizationController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_if(app()->isProduction() && ! $request->secure(), 400);

        $validator = Validator::make($request->query(), [
            'client_id' => ['required', 'string', 'max:255'],
            'redirect_uri' => ['required', 'string', 'max:2048'],
            'state' => ['required', 'string', 'min:16', 'max:512'],
        ]);

        abort_if($validator->fails(), 400);
        $input = $validator->validated();
        $application = Application::where('client_id', $input['client_id'])->first();

        abort_unless(
            $application
            && filled($application->client_secret_hash)
            && filled($application->callback_url)
            && hash_equals($application->callback_url, $input['redirect_uri'])
            && $this->isSafeCallback($application->callback_url),
            400,
        );

        $assignment = $request->user()
            ->applications()
            ->where('applications.id', $application->id)
            ->first();
        $reason = match (true) {
            ! $application->enabled => 'disabled',
            ! $assignment => 'not_assigned',
            default => null,
        };
        $this->audit($request, $application, $reason === null, $reason);

        if ($reason !== null) {
            abort(403);
        }

        $plainCode = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        AuthorizationCode::create([
            'token_hash' => hash('sha256', $plainCode),
            'application_id' => $application->id,
            'user_id' => $request->user()->id,
            'redirect_uri' => $application->callback_url,
            'role' => $assignment->pivot->role,
            'expires_at' => now()->addSeconds(min(300, max(30, config('hub.authorization_code_ttl')))),
        ]);
        $query = http_build_query([
            'code' => $plainCode,
            'state' => $input['state'],
        ], '', '&', PHP_QUERY_RFC3986);

        return redirect()->away($application->callback_url.'?'.$query);
    }

    private function isSafeCallback(string $callback): bool
    {
        return preg_match('#^/apps/[A-Za-z0-9_~-]+(?:/[A-Za-z0-9._~-]+)*$#', $callback) === 1
            && preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $callback) !== 1;
    }

    private function audit(Request $request, Application $application, bool $succeeded, ?string $reason): void
    {
        ApplicationLaunchAudit::create([
            'user_id' => $request->user()->id,
            'application_id' => $application->id,
            'succeeded' => $succeeded,
            'failure_reason' => $reason,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
