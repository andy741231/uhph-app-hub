@extends('layouts.guest')
@section('title', 'Emergency Administrator Sign In')

@section('content')
<div class="w-full max-w-md">
    <div class="card p-8">
        <h1 class="text-xl font-bold text-uh-fg mb-1">Emergency Administrator Sign In</h1>
        <p class="text-sm text-gray-500 mb-6">Restricted fallback access for designated Grant Review administrators.</p>

        @if ($errors->any())
            <div role="alert" class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-md text-sm">
                Sign in failed. Verify your credentials and try again.
            </div>
        @endif

        <form action="{{ route('emergency-login.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="label">Email Address</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="input">
                @error('email')<p class="text-sm text-uh-red mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password" class="label">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password" class="input">
                @error('password')<p class="text-sm text-uh-red mt-1">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn-primary w-full">Sign In</button>
        </form>
    </div>
</div>
@endsection
