@extends('layouts.app')

@section('title', 'Sign in')

@section('content')
<div class="card login-card">
    <p class="eyebrow">Secure application access</p>
    <h1>Sign in to App Hub</h1>
    <p class="lede">Use your administrator-provided account to access your assigned applications.</p>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error" role="alert">We could not sign you in. Check your details and try again.</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="field">
            <label class="label" for="email">Email address</label>
            <input class="input" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" inputmode="email" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
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
        </div>

        <button class="button button-primary" type="submit">Sign in</button>
    </form>
</div>
<p class="support">Need an account or cannot sign in? Contact your App Hub administrator.</p>
@endsection
