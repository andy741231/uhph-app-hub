@extends('layouts.app')

@section('title', 'Sign in')

@section('content')
<div class="card login-card">
    <h1>{{ $loginApplication?->name ?? 'UHPH App Hub' }}</h1>

    @if (session('error'))
        <div class="alert alert-error" role="alert">{{ session('error') }}</div>
    @endif

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error" role="alert">We could not sign you in. Check your details and try again.</div>
    @endif

    @if ($ssoEnabled)
        <a class="button button-primary" href="{{ route('oidc.redirect') }}">Sign in with CougarNet</a>
        @if ($localEnabled)
            <div class="auth-divider" aria-hidden="true"><span>or</span></div>
            <h2 class="local-login-heading">Sign in with a local password</h2>
            <p class="local-login-copy">Local sign-in is optional and available after you set up a Hub password.</p>
        @endif
    @endif

    @if ($localEnabled)
    <form method="POST" action="{{ route('login', $loginApplication ? ['application' => $loginApplication->key] : []) }}" @if ($ssoEnabled) class="local-login-form" @endif>
        @csrf
        <div class="field">
            <label class="label" for="email">Email address</label>
            <input class="input" id="email" name="email" type="email" value="{{ old('email') }}" required @if (! $ssoEnabled) autofocus @endif autocomplete="username" inputmode="email" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email')
                <p class="field-error" id="email-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label class="label" for="password">Password</label>
            <input class="input" id="password" name="password" type="password" required autocomplete="current-password" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
            @error('password')
                <p class="field-error" id="password-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="form-row">
            <label class="check" for="remember">
                <input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))>
                <span>Remember me</span>
            </label>
            <a class="password-help" href="{{ route('password.request') }}">Set up or reset password</a>
        </div>

        <button class="button {{ $ssoEnabled ? 'button-secondary local-login-button' : 'button-primary' }}" type="submit">Sign in</button>
    </form>
    @endif
</div>
<p class="support">Need an account or cannot sign in? Contact your UHPH App Hub administrator.</p>
@endsection
