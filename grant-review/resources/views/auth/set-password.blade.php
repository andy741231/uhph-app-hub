@extends('layouts.guest')
@section('title', 'Set Your Password')

@section('content')
<div class="w-full max-w-md">
    <div class="card p-8">
        <div class="mb-6">
            <h1 class="text-xl font-bold text-uh-fg">Welcome to the UH Grants Portal</h1>
            <p class="text-sm text-gray-500 mt-2">Complete your profile and choose a password to get started.</p>
        </div>

        @if ($errors->any())
            <div role="alert" class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                <ul class="list-disc pl-5 text-sm space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('password.set') }}" method="POST" class="space-y-4" id="setPasswordForm" onsubmit="return validateSetPasswordForm(event)">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">

            {{-- Email (read-only) --}}
            <div>
                <label class="label">Email</label>
                <input type="email" value="{{ $email }}" disabled
                    class="input bg-uh-muted cursor-not-allowed">
            </div>

            {{-- Profile section --}}
            <div class="pt-2">
                <h2 class="text-sm font-bold uppercase tracking-wider text-uh-slate pb-2 border-b border-uh-border">Profile Information</h2>
            </div>

            {{-- Shared profile fields: Phone, Department, Title, PeopleSoft ID, Investigator Type --}}
            <x-users.partials.profile-fields required :hideInvestigatorFields="$hideInvestigatorFields ?? false" />

            {{-- Password section --}}
            <div class="pt-2">
                <h2 class="text-sm font-bold uppercase tracking-wider text-uh-slate pb-2 border-b border-uh-border">Set Password</h2>
            </div>

            <div>
                <label for="password" class="label">Password <span class="req">*</span></label>
                <input type="password" id="password" name="password" required
                    class="input" autocomplete="new-password" minlength="8">
                <p class="text-xs text-gray-500 mt-1">Minimum 8 characters.</p>
                @error('password')
                    <p class="text-sm text-uh-red mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="label">Confirm Password <span class="req">*</span></label>
                <input type="password" id="password_confirmation" name="password_confirmation" required
                    class="input" autocomplete="new-password">
                <p id="matchError" class="text-sm text-uh-red mt-1" style="display: none;">
                    Passwords do not match.
                </p>
                @error('password_confirmation')
                    <p class="text-sm text-uh-red mt-1">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="btn-primary w-full mt-2">
                Complete Setup & Sign In
            </button>
        </form>
    </div>
    <p class="text-center text-xs text-gray-400 mt-4">
        University of Houston — Grants Portal
    </p>
</div>

<script>
    function validateSetPasswordForm(e) {
        var password = document.getElementById('password');
        var confirmation = document.getElementById('password_confirmation');
        var matchError = document.getElementById('matchError');

        // Check required + minlength via native validity
        if (!password.value || password.value.length < 8) {
            password.focus();
            return false;
        }
        if (!confirmation.value) {
            confirmation.focus();
            return false;
        }

        // Check password match
        if (password.value !== confirmation.value) {
            matchError.style.display = 'block';
            confirmation.focus();
            return false;
        }

        matchError.style.display = 'none';
        return true;
    }

    // Real-time match feedback
    (function() {
        var password = document.getElementById('password');
        var confirmation = document.getElementById('password_confirmation');
        var matchError = document.getElementById('matchError');

        function checkMatch() {
            if (!confirmation.value) {
                matchError.style.display = 'none';
                return;
            }
            matchError.style.display = (password.value === confirmation.value) ? 'none' : 'block';
        }

        if (password) password.addEventListener('input', checkMatch);
        if (confirmation) confirmation.addEventListener('input', checkMatch);
    })();
</script>
@endsection
