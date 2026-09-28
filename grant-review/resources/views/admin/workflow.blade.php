@extends('layouts.admin')
@section('title', 'Workflow Chart')

@section('content')
@php
$hubLoginMode = config('hub.enabled')
    ? session(config('hub.login_mode_session_key', 'hub_login_mode'), 'hybrid')
    : 'grant-review-local';
$invitationStep = match ($hubLoginMode) {
    'sso' => ['actor' => 'System', 'text' => 'Invitees receive one app-aware UHPH App Hub email naming their assigned application(s). It offers CougarNet sign-in and may include optional per-app custom text configured in the Hub.', 'emails' => [['key' => 'invitation-hub', 'label' => 'email sent — SSO only']]],
    'local' => ['actor' => 'System', 'text' => 'Invitees receive one app-aware UHPH App Hub email naming their assigned application(s). It links to a one-time Hub set-password page and may include optional per-app custom text configured in the Hub.', 'emails' => [['key' => 'invitation-hub', 'label' => 'email sent — Local only']]],
    'hybrid' => ['actor' => 'System', 'text' => 'Invitees receive one app-aware UHPH App Hub email naming their assigned application(s). It offers CougarNet sign-in plus an optional link to set up a Hub-local password, and may include optional per-app custom text configured in the Hub.', 'emails' => [['key' => 'invitation-hub', 'label' => 'email sent — Hybrid']]],
    default => ['actor' => 'System', 'text' => 'Invitees receive a Pilot Central set-password email with a link to create their local account password.', 'emails' => [['key' => 'invitation-hub', 'label' => 'email sent — Grant Review local']]],
};
$loginModeLabel = match ($hubLoginMode) {
    'sso' => 'Login mode: SSO only',
    'local' => 'Login mode: Local only',
    'hybrid' => 'Login mode: Hybrid',
    default => 'Login mode: Grant Review local',
};

$phases = [
    [
        'title' => 'Onboarding',
        'icon' => 'users',
        'summary' => 'Invite people in, get their profiles complete.',
        'steps' => [
            ['actor' => 'Admin', 'text' => 'Invite reviewers and submitters from Users — individually, or by bulk CSV import (submitters). Bulk multi-role onboarding is also available in the UHPH App Hub.'],
            $invitationStep,
            ['actor' => 'Reviewer/Submitter', 'text' => 'Sign in and complete their profile — phone, department, title, PeopleSoft ID, investigator type. New users stay on the complete-profile page until this is done.'],
            ['actor' => 'System', 'text' => 'Admins are emailed on each profile completion. Dashboards then show role-appropriate status — reviewers see their COI/assignment state, submitters see the submission action.', 'emails' => [['key' => 'profile-completed', 'label' => 'emails admins']]],
        ],
    ],
    [
        'title' => 'Submission',
        'icon' => 'document-arrow-up',
        'summary' => 'Submitter files a proposal against an open cycle.',
        'steps' => [
            ['actor' => 'Submitter', 'text' => 'Draft and submit a proposal for an open cycle.'],
            ['actor' => 'System', 'text' => 'Admins are emailed about the new proposal and the submitter receives a confirmation. The submitter dashboard then tracks its review status.', 'emails' => [['key' => 'proposal-submitted', 'label' => 'emails admins'], ['key' => 'submission-confirmation', 'label' => 'emails submitter']]],
        ],
    ],
    [
        'title' => 'COI screening',
        'icon' => 'envelope-open',
        'summary' => 'Collect conflict declarations before anyone is assigned.',
        'steps' => [
            ['actor' => 'Admin', 'text' => 'Send COI invitations for the cycle from COI invitations.', 'emails' => [['key' => 'coi-invitation', 'label' => 'emails reviewers']]],
            ['actor' => 'Reviewer', 'text' => 'Agree to the Confidentiality Statement & Code of Conduct and the COI Policy (each document opens in a modal), then declare a per-proposal conflict-of-interest response — clear or potential conflict. Resubmitting a declaration supersedes the previous version.'],
            ['actor' => 'System', 'text' => 'Admins are notified on each declaration; per-proposal coverage is tracked on Conflicts of interest. Each reviewer\'s confidentiality acceptance is recorded with the document version, timestamp, and agreed text on their user profile.', 'emails' => [['key' => 'coi-declared', 'label' => 'emails admins']]],
        ],
    ],
    [
        'title' => 'Assignment',
        'icon' => 'user-plus',
        'summary' => 'Match reviewers to proposals.',
        'steps' => [
            ['actor' => 'Admin', 'text' => 'Assign reviewers on Assign reviewers. The server rejects reviewers without a completed declaration, and assigning a reviewer who reported a conflict asks for confirmation first.'],
            ['actor' => 'System', 'text' => 'Each newly assigned reviewer receives an assignment email.', 'emails' => [['key' => 'reviewer-assigned', 'label' => 'emails reviewers']]],
        ],
    ],
    [
        'title' => 'Review',
        'icon' => 'pencil-square',
        'summary' => 'Assigned reviewers score and comment.',
        'steps' => [
            ['actor' => 'System', 'text' => 'Opening a review requires a completed COI declaration for the cycle — reviewers without one are redirected to declare first. Peer reviews appear on the review page only after an admin releases them to reviewers; releasing also locks each reviewer\'s own review.'],
            ['actor' => 'Reviewer', 'text' => 'Draft, then submit the review. Admins are notified on each submission and again when all reviews for a proposal are complete.', 'emails' => [['key' => 'review-submitted', 'label' => 'emails admins'], ['key' => 'all-reviews-complete', 'label' => 'emails admins']]],
            ['actor' => 'Committee', 'text' => 'Review-committee deliberation and score evaluation happen outside this application.', 'external' => true],
            ['actor' => 'Reviewer', 'text' => 'Reviews may be revised and resubmitted — every submission is preserved in the revision timeline.'],
        ],
    ],
    [
        'title' => 'Decision & release',
        'icon' => 'check-badge',
        'summary' => 'Close the loop and publish the outcome.',
        'steps' => [
            ['actor' => 'Admin', 'text' => 'Record the decision, then release reviews per audience from Review results — reviewers and the submitter are released independently.', 'emails' => [['key' => 'decision-recorded', 'label' => 'emails submitter']]],
            ['actor' => 'System', 'text' => 'Each released audience receives a "Reviews available" email. A release can be withdrawn with un-release until the decision guard locks it.', 'emails' => [['key' => 'reviews-available', 'label' => 'emails sent']]],
        ],
    ],
];

$actorStyles = [
    'Admin' => 'bg-uh-red/10 text-uh-red border-uh-red/25',
    'Reviewer' => 'bg-uh-slate/10 text-uh-slate border-uh-slate/25',
    'Reviewer/Submitter' => 'bg-uh-slate/10 text-uh-slate border-uh-slate/25',
    'Submitter' => 'bg-uh-green/10 text-uh-green border-uh-green/25',
    'System' => 'bg-gray-100 text-gray-600 border-gray-300',
    'Committee' => 'bg-[#F6BE00]/15 text-[#8F6000] border-[#F6BE00]/40',
];

$stepNumber = 0;
@endphp

<div class="mb-6">
    <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-bold text-uh-fg">Workflow Chart</h1>
        <span class="inline-block text-[11px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full border bg-gray-100 text-gray-600 border-gray-300">{{ $loginModeLabel }}</span>
    </div>
    <p class="text-sm text-gray-500 mt-1">How a pilot proposal moves through Pilot Central, from invitation to released reviews.</p>
</div>

{{-- Phase pipeline (sticky + scrollspy) --}}
<nav id="workflow-nav" class="sticky top-0 z-20 -mx-2 px-2 py-3 mb-8 bg-uh-bg/95 backdrop-blur-sm border-b border-transparent transition-colors duration-150 overflow-x-auto" aria-label="Workflow phases">
    <ol class="flex items-stretch gap-2 min-w-max pb-1">
        @foreach ($phases as $i => $phase)
            <li class="flex items-center gap-2">
                <a href="#phase-{{ $i + 1 }}"
                   data-spy-link="phase-{{ $i + 1 }}"
                   class="phase-link group flex items-center gap-2.5 px-3.5 py-2 rounded-lg bg-white border border-uh-border shadow-sm hover:border-uh-red/40 hover:shadow transition-all duration-150">
                    <span class="phase-num w-6 h-6 rounded-full bg-uh-red text-white text-[11px] font-bold flex items-center justify-center transition-colors">{{ $i + 1 }}</span>
                    <span class="phase-title text-sm font-semibold text-uh-fg group-hover:text-uh-red transition-colors">{{ $phase['title'] }}</span>
                </a>
                @unless ($loop->last)
                    <x-heroicon-o-chevron-right class="w-4 h-4 text-gray-300 flex-shrink-0" />
                @endunless
            </li>
        @endforeach
    </ol>
</nav>

<div class="space-y-8">
    @foreach ($phases as $i => $phase)
        <section id="phase-{{ $i + 1 }}" class="card overflow-hidden scroll-mt-24">
            <header class="px-6 py-4 border-b border-uh-border bg-uh-muted/60 flex items-center gap-4">
                <span class="w-10 h-10 rounded-xl bg-uh-red text-white flex items-center justify-center flex-shrink-0">
                    <x-dynamic-component :component="'heroicon-o-'.$phase['icon']" class="w-5 h-5" />
                </span>
                <div>
                    <div class="flex items-center gap-2.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-uh-red">Phase {{ $i + 1 }}</span>
                        <h2 class="text-lg font-bold text-uh-fg leading-tight">{{ $phase['title'] }}</h2>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $phase['summary'] }}</p>
                </div>
            </header>
            <ol class="px-6 py-5 relative ml-5 border-l-2 border-uh-border space-y-6">
                @foreach ($phase['steps'] as $step)
                    @php($stepNumber++)
                    <li class="relative pl-8">
                        <span class="absolute -left-[15px] top-0 w-7 h-7 rounded-full {{ ($step['external'] ?? false) ? 'bg-white border-2 border-dashed border-gray-300 text-gray-400' : 'bg-uh-red text-white' }} text-xs font-bold flex items-center justify-center">{{ $stepNumber }}</span>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-block text-[11px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full border {{ $actorStyles[$step['actor']] ?? 'bg-gray-100 text-gray-700 border-gray-300' }}">{{ $step['actor'] }}</span>
                            @foreach ($step['emails'] ?? [] as $mail)
                                <a href="{{ route('admin.email-preview', $mail['key']) }}" target="_blank" rel="noopener"
                                   title="Preview the email"
                                   class="inline-flex items-center gap-1 text-[11px] text-gray-400 hover:text-uh-red transition-colors">
                                    <x-heroicon-o-envelope class="w-3.5 h-3.5" />
                                    {{ $mail['label'] }}
                                </a>
                            @endforeach
                            @if ($step['external'] ?? false)
                                <span class="inline-block text-[11px] font-semibold uppercase tracking-wider text-gray-400">Outside the app</span>
                            @endif
                        </div>
                        <p class="mt-1.5 text-sm text-gray-700 leading-relaxed max-w-3xl">{{ $step['text'] }}</p>
                    </li>
                @endforeach
            </ol>
        </section>
    @endforeach
</div>

<style>
    #workflow-nav .phase-link.is-active {
        border-color: rgb(200 16 46 / .45);
        background-color: rgb(200 16 46 / .05);
    }
    #workflow-nav .phase-link.is-active .phase-title {
        color: #C8102E;
    }
    #workflow-nav .phase-link.is-active .phase-num {
        background-color: #960C22;
    }
    #workflow-nav.is-stuck {
        border-bottom-color: var(--color-border);
    }
</style>

<script>
    (function () {
        var nav = document.getElementById('workflow-nav');
        var links = Array.prototype.slice.call(nav.querySelectorAll('.phase-link'));
        var sections = links.map(function (link) {
            return document.getElementById(link.getAttribute('data-spy-link'));
        });
        var navDocTop = nav.getBoundingClientRect().top + window.scrollY;

        function spy() {
            var marker = window.scrollY + window.innerHeight * 0.3;
            var active = 0;
            sections.forEach(function (section, i) {
                if (section && section.offsetTop <= marker) active = i;
            });
            if (window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 4) {
                active = sections.length - 1;
            }
            links.forEach(function (link, i) {
                link.classList.toggle('is-active', i === active);
            });
            nav.classList.toggle('is-stuck', window.scrollY > navDocTop);
        }

        window.addEventListener('scroll', spy, { passive: true });
        window.addEventListener('resize', spy);
        spy();

        if (! window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            document.documentElement.style.scrollBehavior = 'smooth';
        }
    })();
</script>
@endsection
