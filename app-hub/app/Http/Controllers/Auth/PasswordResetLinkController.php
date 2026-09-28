<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return response()
            ->view('auth.forgot-password')
            ->header('Cache-Control', 'no-store');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        try {
            Password::sendResetLink([
                'email' => $data['email'],
                'status' => User::STATUS_ACTIVE,
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return back()
            ->withInput($request->only('email'))
            ->with('status', 'If an active UHPH App Hub account exists for that email, a password setup link has been sent.');
    }
}
