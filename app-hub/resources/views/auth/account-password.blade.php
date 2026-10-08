@extends('layouts.app')

@section('title', 'Password')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">Account</p>
        <h1>Password</h1>
        <p>Your local UHPH App Hub password. It is not synchronized with CougarNet.</p>
    </div>
</div>

@include('partials.messages')

@if ($hasPassword)
    <form class="card panel" method="POST" action="{{ route('account.password.update') }}">
        @csrf
        @method('PUT')
        <h2>Change password</h2>
        <div class="field">
            <label class="label" for="current_password">Current password</label>
            <input class="input" id="current_password" name="current_password" type="password" required autocomplete="current-password" @error('current_password') aria-invalid="true" aria-describedby="current-password-error" @enderror>
            @error('current_password')<p class="field-error" id="current-password-error">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label class="label" for="password">New password</label>
            <input class="input" id="password" name="password" type="password" minlength="8" required autocomplete="new-password" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
            <p class="hint">At least 8 characters containing letters and numbers.</p>
            @error('password')<p class="field-error" id="password-error">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label class="label" for="password_confirmation">Confirm password</label>
            <input class="input" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
        </div>
        <div class="actions"><button class="button button-secondary" type="submit">Update password</button></div>
    </form>
@else
    <div class="card panel">
        <h2>No local password set</h2>
        <p class="hint">Your account does not have a local UHPH App Hub password yet. Sign out, then use "Set up or reset password" on the Hub sign-in page.</p>
        <form class="actions" style="margin-top: 22px;" method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="button button-secondary" type="submit">Sign out</button>
        </form>
    </div>
@endif
@endsection
