@php
    $layout = match($user->role) {
        'admin' => 'layouts.admin',
        'reviewer' => 'layouts.reviewer',
        'submitter' => 'layouts.submitter',
        default => 'layouts.app',
    };
@endphp
@extends($layout)
@section('title', 'Complete Your Profile')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <p class="text-sm font-semibold uppercase tracking-wider text-uh-red">One final step</p>
        <h1 class="text-2xl sm:text-3xl font-bold text-uh-fg mt-1">Complete your Grants Portal profile</h1>
        <p class="text-gray-600 mt-2">Provide the information below before continuing to Grants Portal.</p>
    </div>

    <div class="card p-6 shadow-xs">
        @if ($errors->any())
            <div role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800">
                <p class="text-sm font-semibold">Please correct the highlighted fields.</p>
            </div>
        @endif

        <div class="mb-5 rounded-lg border border-uh-border bg-uh-muted px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Signed in as</p>
            <p class="text-sm font-medium text-uh-fg mt-1">{{ $user->full_name }} · {{ $user->email }}</p>
        </div>

        <form method="POST" action="{{ route('profile.complete.update') }}" class="space-y-5">
            @csrf
            @method('PATCH')

            <x-users.partials.profile-fields :user="$user" required :hideInvestigatorFields="$user->role === 'reviewer'" />

            <div class="pt-5 border-t border-uh-border">
                <button type="submit" class="btn-primary w-full sm:w-auto">Save and Continue</button>
                <p class="text-xs text-gray-500 mt-3">You can update this information later from My Profile.</p>
            </div>
        </form>
    </div>
</div>
@endsection
