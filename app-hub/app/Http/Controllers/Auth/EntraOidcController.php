<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginAudit;
use App\Models\User;
use App\Services\EntraOidcClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class EntraOidcController extends Controller
{
    public function redirect(Request $request, EntraOidcClient $entra): RedirectResponse
    {
        return $entra->authorizationRedirect($request);
    }

    public function callback(Request $request, EntraOidcClient $entra): RedirectResponse
    {
        if (is_string($request->query('error'))) {
            return redirect()->route('login')
                ->with('error', 'Sign-in with CougarNet was cancelled or could not be completed. Please try again.');
        }

        $claims = $entra->claims($request);
        $email = Str::lower(trim((string) ($claims['email'] ?? '')));

        if ($email === '') {
            $fallback = Str::lower(trim((string) ($claims['preferred_username'] ?? '')));
            $email = filter_var($fallback, FILTER_VALIDATE_EMAIL) ? $fallback : '';
        }

        $user = User::where('external_subject', $claims['sub'])->first();

        if ($user === null) {
            $user = $email !== '' ? User::where('email', $email)->first() : null;

            if ($user !== null && $user->external_subject !== null) {
                $this->audit($request, $user, $email, false, 'subject_conflict', $claims['sub']);

                return redirect()->route('login')
                    ->with('error', 'This UH account is linked to a different account. Contact your administrator.');
            }

            if ($user === null) {
                $this->audit($request, null, $email !== '' ? $email : 'unknown', false, 'not_provisioned', $claims['sub']);

                return redirect()->route('login')
                    ->with('error', 'No account exists for this UH account. Contact your administrator for an invitation.');
            }
        }

        if (! $user->isActive()) {
            $this->audit($request, $user, $email, false, 'disabled', $claims['sub']);

            return redirect()->route('login')
                ->with('error', 'Your account is not active. Contact an administrator.');
        }

        $user->forceFill([
            'external_subject' => $claims['sub'],
            'name' => filled($claims['name'] ?? null) ? $claims['name'] : $user->name,
            'last_login_at' => now(),
        ])->save();

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put(config('hub.login_method_session_key', 'hub_login_method'), 'sso');
        $this->audit($request, $user, $email, true, null, $claims['sub']);

        return redirect()->intended(route('dashboard'));
    }

    private function audit(Request $request, ?User $user, string $email, bool $succeeded, ?string $reason = null, ?string $sub = null): void
    {
        LoginAudit::create([
            'user_id' => $user?->id,
            'email' => $email !== '' ? $email : 'unknown',
            'external_subject' => $sub,
            'method' => 'sso',
            'succeeded' => $succeeded,
            'failure_reason' => $reason,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
