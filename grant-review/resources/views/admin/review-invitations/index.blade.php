@extends('layouts.admin')
@section('title', 'COI Invitations')

@section('content')
<div class="flex items-start justify-between gap-4 mb-6 flex-wrap">
    <div>
        <p class="text-sm font-semibold uppercase tracking-wider text-uh-red">Review oversight</p>
        <h1 class="text-2xl font-bold text-uh-fg mt-1">COI invitations</h1>
        <p class="text-sm text-gray-500 mt-1">Invite reviewers to declare conflicts of interest for a round before assigning proposals.</p>
    </div>
    <a href="{{ route('admin.conflicts.index', $roundId ? ['round_id' => $roundId] : []) }}" class="btn-secondary">
        <x-heroicon-o-clipboard-document-check class="w-4 h-4 mr-1.5" />
        View declarations
    </a>
</div>

{{-- Invite form --}}
<div class="card p-5 mb-6">
    <h2 class="text-sm font-bold uppercase tracking-wider text-uh-fg mb-4">Send COI invitation</h2>
    <form action="{{ route('admin.review-invitations.store') }}" method="POST" data-invite-form data-round-id="{{ $roundId }}">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-[16rem_1fr] gap-3 items-end mb-4">
            <div>
                <label for="invite-round" class="label">Round</label>
                <select id="invite-round" name="round_id" class="input mt-1.5" required data-round-select>
                    <option value="">Select a round…</option>
                    @foreach ($rounds as $round)
                        <option value="{{ $round->id }}" @selected($roundId === $round->id)>{{ $round->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2 md:justify-end">
                <button type="button" class="btn-secondary text-xs" data-select-all>Select all</button>
                <button type="button" class="btn-secondary text-xs" data-clear-selection>Clear</button>
            </div>
        </div>
        <fieldset>
            <legend class="label">Reviewers</legend>
            @if ($eligibleReviewers->isEmpty())
                <p class="text-sm text-gray-500 mt-2">No active reviewers available.</p>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 mt-2">
                    @foreach ($eligibleReviewers as $reviewer)
                        <label class="flex items-start gap-3 rounded-md border border-uh-border px-3 py-2.5 transition-colors duration-150 hover:bg-uh-muted/60 cursor-pointer" data-reviewer-row="{{ $reviewer->id }}">
                            <input type="checkbox" name="reviewer_ids[]" value="{{ $reviewer->id }}"
                                class="mt-0.5 rounded border-uh-border text-uh-red focus:ring-uh-red" data-reviewer-checkbox>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium truncate">{{ $reviewer->full_name }}</span>
                                <span class="block text-xs text-gray-500 truncate">{{ $reviewer->email }}</span>
                                <span class="block mt-1" data-invitation-badge></span>
                            </span>
                        </label>
                    @endforeach
                </div>
            @endif
        </fieldset>
        <div class="mt-4 flex items-center justify-between gap-3">
            <p class="text-xs text-gray-500">Reviewers with an active invitation for the round will receive the email again.</p>
            <button type="submit" class="btn-primary shrink-0" @disabled($eligibleReviewers->isEmpty() || $rounds->isEmpty())>
                <x-heroicon-o-paper-airplane class="w-4 h-4 mr-1.5" />
                <span data-send-label>Send invitation</span>
            </button>
        </div>
    </form>
</div>

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="card p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Invitations</p>
        <p class="text-2xl font-bold text-uh-fg mt-1">{{ $stats['invitations'] }}</p>
    </div>
    <a href="{{ route('admin.review-invitations.index', array_filter(['status' => 'awaiting', 'round_id' => $roundId])) }}"
       class="card p-4 border-amber-200 bg-amber-50/40 block hover:border-amber-400 transition-colors">
        <p class="text-xs font-semibold uppercase tracking-wider text-amber-700">Awaiting declaration</p>
        <p class="text-2xl font-bold text-amber-900 mt-1">{{ $stats['pending'] }}</p>
    </a>
    <a href="{{ route('admin.review-invitations.index', array_filter(['status' => 'declared', 'round_id' => $roundId])) }}"
       class="card p-4 block hover:border-uh-border transition-colors">
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Declared</p>
        <p class="text-2xl font-bold text-uh-fg mt-1">{{ $stats['declared'] }}</p>
    </a>
    <a href="{{ route('admin.review-invitations.index', array_filter(['status' => 'revoked', 'round_id' => $roundId])) }}"
       class="card p-4 block hover:border-uh-border transition-colors">
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Revoked</p>
        <p class="text-2xl font-bold text-gray-500 mt-1">{{ $stats['revoked'] }}</p>
    </a>
</div>

{{-- Invitations table --}}
<div class="card overflow-hidden">
    <form method="GET" action="{{ route('admin.review-invitations.index') }}" class="px-4 py-3 border-b border-uh-border grid grid-cols-1 md:grid-cols-[14rem_14rem_auto] gap-3 items-end bg-uh-muted/40">
        <div>
            <label for="invitation-round" class="label">Round</label>
            <select id="invitation-round" name="round_id" class="input mt-1" onchange="this.form.submit()">
                <option value="">All rounds</option>
                @foreach ($rounds as $round)
                    <option value="{{ $round->id }}" @selected($roundId === $round->id)>{{ $round->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="invitation-status" class="label">Status</label>
            <select id="invitation-status" name="status" class="input mt-1" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <option value="awaiting" @selected($status === 'awaiting')>Awaiting declaration</option>
                <option value="update_required" @selected($status === 'update_required')>Update required</option>
                <option value="declared" @selected($status === 'declared')>Declared</option>
                <option value="revoked" @selected($status === 'revoked')>Revoked</option>
            </select>
        </div>
        <div>
            @if ($roundId || $status)
                <a href="{{ route('admin.review-invitations.index') }}" class="btn-secondary">Clear</a>
            @endif
        </div>
    </form>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Reviewer</th>
                    <th>Round</th>
                    <th>Invited</th>
                    <th>Notified</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invitations as $invitation)
                    @php
                        $declaration = $invitation->currentDeclaration;
                    @endphp
                    <tr class="{{ $invitation->isActive() ? '' : 'opacity-60' }}">
                        <td>
                            <p class="font-semibold text-uh-fg">{{ $invitation->reviewer->full_name }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $invitation->reviewer->email }}</p>
                        </td>
                        <td class="text-gray-700">{{ $invitation->round->name }}</td>
                        <td class="text-gray-600 whitespace-nowrap">{{ $invitation->invited_at->format('M j, Y') }}</td>
                        <td class="text-gray-600 whitespace-nowrap">
                            @if ($invitation->notification_sent_at)
                                {{ $invitation->notification_sent_at->format('M j, Y g:i A') }}
                            @else
                                <span class="text-gray-400">Not sent</span>
                            @endif
                        </td>
                        <td>
                            @if (! $invitation->isActive())
                                <span class="badge-gray">Revoked</span>
                            @elseif ($declaration === null)
                                <span class="badge-yellow">Awaiting declaration</span>
                            @elseif ($declaration->isStale())
                                <span class="badge-yellow">Update required</span>
                            @else
                                <span class="badge-green">Declared</span>
                            @endif
                            @if ($declaration && $declaration->hasConflicts())
                                <span class="inline-flex items-center rounded-full border border-amber-300 bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900 ml-1">
                                    {{ $declaration->responses->where('status', 'potential_conflict')->count() }} conflict{{ $declaration->responses->where('status', 'potential_conflict')->count() === 1 ? '' : 's' }}
                                </span>
                            @endif
                        </td>
                        <td class="text-right whitespace-nowrap">
                            @if ($invitation->isActive())
                                <form action="{{ route('admin.review-invitations.resend', $invitation) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-uh-red hover:underline">Resend</button>
                                </form>
                                <span class="text-gray-300 mx-1">·</span>
                                <form action="{{ route('admin.review-invitations.revoke', $invitation) }}" method="POST" class="inline"
                                    onsubmit="return confirm('Revoke the COI invitation for {{ $invitation->reviewer->full_name }}? Existing assignments and reviews are preserved.');">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-gray-500 hover:text-red-600 hover:underline">Revoke</button>
                                </form>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-12 text-center text-gray-500">
                            <p class="font-medium text-gray-700">No COI invitations match these filters</p>
                            <p class="text-sm mt-1">Invite reviewers above so they can declare conflicts of interest before you assign proposals.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@php
    $invitationStatus = [];
    foreach ($invitations as $invitation) {
        $declaration = $invitation->currentDeclaration;
        $invitationStatus[$invitation->round_id.'-'.$invitation->reviewer_id] = [
            'active' => $invitation->isActive(),
            'status' => $declaration === null ? 'awaiting' : ($declaration->isStale() ? 'update' : 'declared'),
            'conflicts' => $declaration?->hasConflicts() ? $declaration->responses->where('status', 'potential_conflict')->count() : 0,
        ];
    }
@endphp
<script>
    const invitationStatus = @json($invitationStatus);

    document.addEventListener('DOMContentLoaded', () => {
        const form = document.querySelector('form[data-invite-form]');
        if (! form) return;

        const roundSelect = form.querySelector('[data-round-select]');
        const checkboxes = [...form.querySelectorAll('[data-reviewer-checkbox]')];
        const sendLabel = form.querySelector('[data-send-label]');

        const badgeHtml = (entry) => {
            if (! entry) return '';
            if (! entry.active) return '<span class="inline-flex items-center rounded-full border border-gray-200 bg-white px-2 py-0.5 text-[10px] font-semibold text-gray-400">Revoked</span>';
            if (entry.status === 'awaiting') return '<span class="inline-flex items-center rounded-full border border-amber-300 bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-900">Invited — awaiting declaration</span>';
            if (entry.status === 'update') return '<span class="inline-flex items-center rounded-full border border-amber-300 bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-900">Update required</span>';
            const conflicts = entry.conflicts > 0 ? ' · ' + entry.conflicts + ' conflict' + (entry.conflicts === 1 ? '' : 's') : '';
            return '<span class="inline-flex items-center rounded-full border border-green-300 bg-green-100 px-2 py-0.5 text-[10px] font-semibold text-green-800">Declared' + conflicts + '</span>';
        };

        const refresh = () => {
            const roundId = roundSelect.value;
            checkboxes.forEach((checkbox) => {
                const badge = checkbox.closest('[data-reviewer-row]')?.querySelector('[data-invitation-badge]');
                if (badge) {
                    badge.innerHTML = roundId ? (badgeHtml(invitationStatus[roundId + '-' + checkbox.value]) || '') : '';
                }
            });

            const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
            sendLabel.textContent = selected === 0
                ? 'Send invitation'
                : 'Send ' + selected + ' invitation' + (selected === 1 ? '' : 's');
        };

        form.querySelector('[data-select-all]').addEventListener('click', () => {
            checkboxes.forEach((checkbox) => { checkbox.checked = true; });
            refresh();
        });

        form.querySelector('[data-clear-selection]').addEventListener('click', () => {
            checkboxes.forEach((checkbox) => { checkbox.checked = false; });
            refresh();
        });

        checkboxes.forEach((checkbox) => checkbox.addEventListener('change', refresh));
        roundSelect.addEventListener('change', refresh);

        form.addEventListener('submit', (event) => {
            const selected = checkboxes.filter((checkbox) => checkbox.checked);
            if (selected.length === 0) {
                event.preventDefault();
                alert('Select at least one reviewer to invite.');
                return;
            }

            const roundId = roundSelect.value;
            const alreadyInvited = selected.filter((checkbox) => {
                const entry = invitationStatus[roundId + '-' + checkbox.value];
                return entry && entry.active;
            });

            if (alreadyInvited.length > 0 && ! window.confirm(
                alreadyInvited.length + ' of the selected reviewer(s) already have an active COI invitation for this round. ' +
                'Sending will re-send the invitation email. Continue?'
            )) {
                event.preventDefault();
            }
        });

        refresh();
    });
</script>
@endsection
