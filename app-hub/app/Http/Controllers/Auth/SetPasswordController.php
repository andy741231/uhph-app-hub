<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class SetPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.set-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);
        $resetUser = null;
        $status = Password::reset($credentials, function (User $user, string $password) use (&$resetUser): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();
            $resetUser = $user;
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET || ! $resetUser) {
            return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
        }

        Auth::login($resetUser);
        $request->session()->regenerate();
        $applications = $resetUser->applications()->where('enabled', true)->get();

        return $applications->count() === 1
            ? redirect()->route('applications.launch', $applications->first())
            : redirect()->route('dashboard');
    }
}
