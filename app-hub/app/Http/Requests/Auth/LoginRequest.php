<?php

namespace App\Http\Requests\Auth;

use App\Models\LoginAudit;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $email = $this->string('email')->toString();
        $credentials = [
            'email' => $email,
            'password' => $this->string('password')->toString(),
            'status' => User::STATUS_ACTIVE,
        ];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            $user = User::where('email', $email)->first();
            $reason = $user && ! $user->isActive() && Hash::check($credentials['password'], $user->password)
                ? 'disabled'
                : 'invalid_credentials';

            $this->audit($user, false, $reason);
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $user = Auth::user();
        $user->forceFill(['last_login_at' => now()])->save();
        $this->audit($user, true);
        RateLimiter::clear($this->throttleKey());
    }

    private function audit(?User $user, bool $succeeded, ?string $reason = null): void
    {
        LoginAudit::create([
            'user_id' => $user?->id,
            'email' => $this->string('email')->toString(),
            'succeeded' => $succeeded,
            'failure_reason' => $reason,
            'ip_address' => $this->ip(),
            'user_agent' => $this->userAgent(),
        ]);
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));
        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate($this->string('email')->lower().'|'.$this->ip());
    }
}
