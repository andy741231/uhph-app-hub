@php
    $role = auth()->user()->role;
    $layout = match($role) {
        'admin' => 'layouts.admin',
        'reviewer' => 'layouts.reviewer',
        'submitter' => 'layouts.submitter',
        default => 'layouts.app',
    };
@endphp
@extends($layout)
@section('title', 'My Profile')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl sm:text-3xl font-bold text-uh-fg">My Profile</h1>
    <p class="text-gray-600 mt-1">Update your account information and password.</p>
</div>

<div class="max-w-2xl space-y-6">

    {{-- Success message --}}
    @if (session('status') === 'profile-updated')
        <div role="alert" class="flex items-start gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            <x-heroicon-o-check-circle class="w-5 h-5 flex-shrink-0 mt-0.5" />
            <span class="text-sm font-medium">Profile updated successfully.</span>
        </div>
    @endif
    @if (session('status') === 'password-updated')
        <div role="alert" class="flex items-start gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            <x-heroicon-o-check-circle class="w-5 h-5 flex-shrink-0 mt-0.5" />
            <span class="text-sm font-medium">Password updated successfully.</span>
        </div>
    @endif

    {{-- Profile Information --}}
    <div class="card p-6 shadow-xs">
        <div class="pb-4 border-b border-uh-border mb-5">
            <h2 class="text-lg font-bold text-uh-fg">Profile Information</h2>
            <p class="text-xs text-gray-500 mt-0.5">Update your name, email, and department.</p>
        </div>

        <form method="POST" action="{{ route('profile.update') }}" class="space-y-5">
            @csrf
            @method('PATCH')

            {{-- First Name + Last Name --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="first_name" class="label">First Name <span class="req">*</span></label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $user->first_name) }}" required
                        autocomplete="given-name" class="input mt-1.5">
                    @error('first_name')
                        <p class="text-sm text-uh-red mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="last_name" class="label">Last Name <span class="req">*</span></label>
                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $user->last_name) }}" required
                        autocomplete="family-name" class="input mt-1.5">
                    @error('last_name')
                        <p class="text-sm text-uh-red mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Email --}}
            <div>
                <label for="email" class="label">Email <span class="req">*</span></label>
                <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required
                    autocomplete="username" class="input mt-1.5">
                <p class="text-xs text-gray-400 mt-1.5">
                    Only @uh.edu, @central.uh.edu, or @cougarnet.uh.edu addresses are accepted.
                </p>
                @error('email')
                    <p class="text-sm text-uh-red mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Shared profile fields: Department, Phone, Title, PeopleSoft ID, Investigator Type --}}
            <x-users.partials.profile-fields :user="$user" />

            {{-- Role (read-only) --}}
            <div>
                <label class="label">Role</label>
                <div class="mt-1.5 flex items-center gap-2">
                    <span class="px-3 py-1.5 text-xs font-semibold rounded-full
                        {{ $user->role === 'admin' ? 'bg-uh-red/10 text-uh-red border border-uh-red/20' :
                           ($user->role === 'reviewer' ? 'bg-blue-50 text-blue-700 border border-blue-200' :
                           ($user->role === 'submitter' ? 'bg-green-50 text-green-700 border border-green-200' :
                           'bg-gray-100 text-gray-600 border border-gray-200')) }}">
                        {{ ucfirst($user->role) }}
                    </span>
                    <span class="text-xs text-gray-400">Assigned by administrator — cannot be changed.</span>
                </div>
            </div>

            <div class="pt-4 border-t border-uh-border">
                <button type="submit" class="btn-primary">
                    <x-heroicon-o-check class="w-4 h-4 mr-1.5" />
                    Save Changes
                </button>
            </div>
        </form>
    </div>

    {{-- Update Password --}}
    <div class="card p-6 shadow-xs">
        <div class="pb-4 border-b border-uh-border mb-5">
            <h2 class="text-lg font-bold text-uh-fg">Update Password</h2>
            <p class="text-xs text-gray-500 mt-0.5">Ensure your account uses a strong, secure password.</p>
        </div>

        <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label for="current_password" class="label">Current Password <span class="req">*</span></label>
                <input type="password" id="current_password" name="current_password" required
                    autocomplete="current-password" class="input mt-1.5">
                @error('current_password', 'updatePassword')
                    <p class="text-sm text-uh-red mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="label">New Password <span class="req">*</span></label>
                <input type="password" id="password" name="password" required
                    autocomplete="new-password" class="input mt-1.5">
                @error('password', 'updatePassword')
                    <p class="text-sm text-uh-red mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="label">Confirm New Password <span class="req">*</span></label>
                <input type="password" id="password_confirmation" name="password_confirmation" required
                    autocomplete="new-password" class="input mt-1.5">
                @error('password_confirmation', 'updatePassword')
                    <p class="text-sm text-uh-red mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="pt-4 border-t border-uh-border">
                <button type="submit" class="btn-primary">
                    <x-heroicon-o-key class="w-4 h-4 mr-1.5" />
                    Update Password
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
