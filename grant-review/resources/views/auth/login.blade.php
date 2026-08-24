@extends('layouts.guest')
@section('title', 'Sign In')

@section('content')
<div class="w-full max-w-md">
    <div class="card p-8">
        <h1 class="text-xl font-bold text-uh-fg mb-1">Sign In</h1>
        <p class="text-sm text-gray-500 mb-6">Enter your credentials to access the grants portal.</p>

        @if (session('status'))
            <div role="alert" class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-md text-sm">
                {{ session('status') }}
            </div>
        @endif

        <form action="{{ route('login') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="label">Email Address</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required
                    autofocus autocomplete="username" class="input"
                    placeholder="you@example.com">
                @error('email')
                    <p class="text-sm text-uh-red mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="label">Password</label>
                <input type="password" id="password" name="password" required
                    autocomplete="current-password" class="input">
                @error('password')
                    <p class="text-sm text-uh-red mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                    <input type="checkbox" name="remember"
                        class="rounded border-uh-border text-uh-red focus:ring-uh-red">
                    Remember me
                </label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-sm text-uh-red hover:underline font-medium">
                        Forgot password?
                    </a>
                @endif
            </div>

            <button type="submit" class="btn-primary w-full">
                Sign In
            </button>
        </form>
    </div>
    <p class="text-center text-xs text-gray-400 mt-4">
        Don't have an account? Contact the grants administrator for an invitation.
    </p>
</div>
@endsection
