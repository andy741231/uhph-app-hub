@php
    $layout = match(auth()->user()->role) {
        'admin' => 'layouts.admin',
        'reviewer' => 'layouts.reviewer',
        'submitter' => 'layouts.submitter',
        default => 'layouts.app',
    };
@endphp
@extends($layout)
@section('title', 'Settings')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-uh-fg">Settings</h1>
    <p class="text-sm text-gray-500 mt-1">Manage your preferences and application settings.</p>
</div>

<div class="max-w-2xl space-y-6">

    {{-- Email Notification Preferences --}}
    <div class="card p-6 shadow-xs">
        <div class="pb-4 border-b border-uh-border mb-5">
            <h2 class="text-lg font-bold text-uh-fg">Email Notifications</h2>
            <p class="text-xs text-gray-500 mt-0.5">Choose which email notifications you want to receive.</p>
        </div>

        <form method="POST" action="{{ route('settings.update') }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                @php
                    $notifications = [
                        'notify_profile_completed' => [
                            'label' => 'Profile completed',
                            'desc' => 'When a new user completes their profile onboarding.',
                            'roles' => ['admin'],
                        ],
                        'notify_review_submitted' => [
                            'label' => 'Review submitted',
                            'desc' => 'When a reviewer submits their review for a submission.',
                            'roles' => ['admin'],
                        ],
                        'notify_proposal_submitted' => [
                            'label' => 'Proposal submitted',
                            'desc' => 'When a submitter submits a proposal for review.',
                            'roles' => ['admin'],
                        ],
                        'notify_all_reviews_complete' => [
                            'label' => 'All reviews complete',
                            'desc' => 'When all assigned reviewers have submitted their reviews for a submission.',
                            'roles' => ['admin'],
                        ],
                        'notify_decision_recorded' => [
                            'label' => 'Decision recorded',
                            'desc' => 'When an admin records a funding decision on your proposal.',
                            'roles' => ['submitter'],
                        ],
                        'notify_reviewer_assigned' => [
                            'label' => 'Reviewer assigned',
                            'desc' => 'When you are assigned to review a submission.',
                            'roles' => ['reviewer'],
                        ],
                        'notify_submission_confirmation' => [
                            'label' => 'Submission confirmation',
                            'desc' => 'Receive a confirmation email when you submit a proposal.',
                            'roles' => ['submitter'],
                        ],
                        'notify_reviews_available' => [
                            'label' => 'Reviews available',
                            'desc' => 'When completed reviews are approved and available to view.',
                            'roles' => ['submitter', 'reviewer'],
                        ],
                    ];
                    $userRole = auth()->user()->role;
                @endphp

                @foreach ($notifications as $key => $info)
                    @if (in_array($userRole, $info['roles']))
                        <div class="flex items-start gap-3">
                            <input type="hidden" name="email_preferences[{{ $key }}]" value="0">
                            <input type="checkbox" id="{{ $key }}" name="email_preferences[{{ $key }}]" value="1"
                                class="mt-1 w-4 h-4 rounded border-gray-300 text-uh-red focus:ring-uh-red"
                                @checked($emailPreferences[$key] ?? true)>
                            <div>
                                <label for="{{ $key }}" class="text-sm font-medium text-uh-fg cursor-pointer">{{ $info['label'] }}</label>
                                <p class="text-xs text-gray-500 mt-0.5">{{ $info['desc'] }}</p>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="pt-4 border-t border-uh-border">
                <button type="submit" class="btn-primary">
                    <x-heroicon-o-check class="w-4 h-4 mr-1.5" />
                    Save Preferences
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
