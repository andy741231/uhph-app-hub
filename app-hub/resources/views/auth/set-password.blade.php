@extends('layouts.app')

@section('title', 'Set password')

@section('content')
<div class="card login-card">
    <p class="eyebrow">Account invitation</p>
    <h1>Set your password</h1>
    <p class="lede">Create a password for your App Hub account.</p>

    @if ($errors->any())
        <div class="alert alert-error" role="alert">We could not set your password. Check the details below and try again.</div>
    @endif

    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="field">
            <label class="label" for="email">Email address</label>
            <input class="input" id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="username" @error('email') aria-invalid="true" @enderror>
            @error('email')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label class="label" for="password">New password</label>
            <input class="input" id="password" name="password" type="password" minlength="8" required autocomplete="new-password" @error('password') aria-invalid="true" @enderror>
            <p class="hint">At least 8 characters containing letters and numbers.</p>
            @error('password')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label class="label" for="password_confirmation">Confirm password</label>
            <input class="input" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
        </div>
        <button class="button button-primary" type="submit">Set password</button>
    </form>
</div>
@endsection
