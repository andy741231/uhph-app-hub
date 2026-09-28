@extends('layouts.app')

@section('title', 'Set up or reset password')

@section('content')
<div class="card login-card">
    <p class="eyebrow">Local sign-in</p>
    <h1>Set up or reset password</h1>
    <p class="lede">Enter your Hub email address and we will send a password link. A local password is optional; you can continue signing in with CougarNet.</p>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error" role="alert">Enter a valid email address and try again.</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="field">
            <label class="label" for="email">Email address</label>
            <input class="input" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" inputmode="email" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email')<p class="field-error" id="email-error">{{ $message }}</p>@enderror
        </div>
        <button class="button button-primary" type="submit">Email password link</button>
    </form>
</div>
<p class="support"><a href="{{ route('login') }}">Back to sign in</a></p>
@endsection
