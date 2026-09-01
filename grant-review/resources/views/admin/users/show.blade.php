@extends('layouts.admin')
@section('title', $user->full_name)

@section('content')

{{-- Hero header --}}
<div class="relative overflow-hidden rounded-xl bg-gradient-to-r from-uh-red to-uh-brick mb-6 shadow-lg">
    <div class="absolute inset-0 opacity-10">
        <x-heroicon-o-user-circle class="w-full h-full" />
    </div>
    <div class="relative px-6 py-8 sm:px-8">
        {{-- Breadcrumb --}}
        <nav class="flex items-center gap-2 text-xs text-white/70 mb-4" aria-label="Breadcrumb">
            <a href="{{ route('admin.dashboard') }}" class="hover:text-white transition-colors">Dashboard</a>
            <x-heroicon-o-chevron-right class="w-3 h-3" />
            <a href="{{ route('admin.users.index') }}" class="hover:text-white transition-colors">Users</a>
            <x-heroicon-o-chevron-right class="w-3 h-3" />
            <span class="text-white font-medium">{{ $user->full_name }}</span>
        </nav>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                {{-- Avatar --}}
                <div class="w-16 h-16 rounded-2xl bg-white/20 backdrop-blur flex items-center justify-center text-white font-bold text-2xl shrink-0 ring-2 ring-white/30">
                    {{ strtoupper(substr($user->first_name, 0, 1)) }}
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-white">{{ $user->full_name }}</h1>
                    <p class="text-sm text-white/80 mt-0.5">{{ $user->email }}</p>
                    <div class="flex items-center gap-2 mt-2">
                        <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-white/20 text-white backdrop-blur">
                            {{ ucfirst($user->role) }}
                        </span>
                        <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full
                            {{ $user->status === 'active' ? 'bg-green-400/30 text-green-50' :
                               ($user->status === 'invited' ? 'bg-yellow-400/30 text-yellow-50' :
                               'bg-gray-400/30 text-gray-100') }}">
                            {{ ucfirst($user->status) }}
                        </span>
                    </div>
                </div>
            </div>
            <a href="{{ route('admin.users.edit', $user) }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-white text-uh-red font-semibold text-sm rounded-lg hover:bg-white/90 transition-colors shadow-sm shrink-0">
                <x-heroicon-o-pencil-square class="w-4 h-4" />
                Edit User
            </a>
        </div>
    </div>
</div>

{{-- Stat cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="card p-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-uh-red/10 flex items-center justify-center text-uh-red shrink-0">
                <x-heroicon-o-clipboard-document-check class="w-5 h-5" />
            </div>
            <div>
                <p class="text-2xl font-bold text-uh-fg">{{ $user->role === 'submitter' ? $submissions->count() : $reviewAssignments->count() }}</p>
                <p class="text-xs text-gray-500">{{ $user->role === 'submitter' ? 'Submissions' : ($user->role === 'reviewer' ? 'Reviews' : 'Account') }}</p>
            </div>
        </div>
    </div>
    <div class="card p-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center text-blue-600 shrink-0">
                <x-heroicon-o-academic-cap class="w-5 h-5" />
            </div>
            <div>
                <p class="text-2xl font-bold text-uh-fg">{{ $rounds->count() }}</p>
                <p class="text-xs text-gray-500">Round Invitations</p>
            </div>
        </div>
    </div>
    <div class="card p-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center text-green-600 shrink-0">
                <x-heroicon-o-check-badge class="w-5 h-5" />
            </div>
            <div>
                <p class="text-2xl font-bold text-uh-fg">{{ ucfirst($user->status) }}</p>
                <p class="text-xs text-gray-500">Account Status</p>
            </div>
        </div>
    </div>
    <div class="card p-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-yellow-50 flex items-center justify-center text-yellow-600 shrink-0">
                <x-heroicon-o-sparkles class="w-5 h-5" />
            </div>
            <div>
                <p class="text-2xl font-bold text-uh-fg">{{ $user->investigator_type === 'pi' ? 'Principal Investigator' : ($user->investigator_type === 'other' ? 'Other' : '—') }}</p>
                <p class="text-xs text-gray-500">Investigator Type</p>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Profile details --}}
    <div class="card p-6 lg:col-span-2">
        <h2 class="text-lg font-bold text-uh-fg mb-5 flex items-center gap-2">
            <x-heroicon-o-user class="w-5 h-5 text-uh-red" />
            Profile Information
        </h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-px bg-uh-border rounded-lg overflow-hidden">
            @php
                $profileFields = [
                    ['label' => 'First Name', 'value' => $user->first_name, 'icon' => 'o-user'],
                    ['label' => 'Last Name', 'value' => $user->last_name, 'icon' => 'o-user'],
                    ['label' => 'Email', 'value' => $user->email, 'icon' => 'o-envelope'],
                    ['label' => 'Phone', 'value' => $user->phone, 'icon' => 'o-phone'],
                    ['label' => 'Department', 'value' => $user->department, 'icon' => 'o-building-office-2'],
                    ['label' => 'Title', 'value' => $user->title, 'icon' => 'o-briefcase'],
                    ['label' => 'PeopleSoft ID', 'value' => $user->peoplesoft_id, 'icon' => 'o-identification'],
                    ['label' => 'Investigator Type', 'value' => $user->investigator_type === 'pi' ? 'Principal Investigator' : ($user->investigator_type === 'other' ? 'Other' : null), 'icon' => 'o-sparkles'],
                    ['label' => 'Early-Stage Investigator', 'value' => $user->early_stage_investigator ? 'Yes' : 'No', 'icon' => 'o-sparkles'],
                    ['label' => 'New Investigator', 'value' => $user->new_investigator ? 'Yes' : 'No', 'icon' => 'o-sparkles'],
                ];
            @endphp
            @foreach ($profileFields as $field)
                <div class="bg-white px-4 py-3.5 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-uh-muted flex items-center justify-center text-uh-slate shrink-0">
                        @switch($field['icon'])
                            @case('o-user') <x-heroicon-o-user class="w-4 h-4" /> @break
                            @case('o-envelope') <x-heroicon-o-envelope class="w-4 h-4" /> @break
                            @case('o-phone') <x-heroicon-o-phone class="w-4 h-4" /> @break
                            @case('o-building-office-2') <x-heroicon-o-building-office-2 class="w-4 h-4" /> @break
                            @case('o-briefcase') <x-heroicon-o-briefcase class="w-4 h-4" /> @break
                            @case('o-identification') <x-heroicon-o-identification class="w-4 h-4" /> @break
                            @case('o-sparkles') <x-heroicon-o-sparkles class="w-4 h-4" /> @break
                        @endswitch
                    </div>
                    <div class="min-w-0">
                        <dt class="text-xs text-gray-500 uppercase tracking-wider">{{ $field['label'] }}</dt>
                        <dd class="text-sm font-medium text-uh-fg mt-0.5 truncate">{{ $field['value'] ?: '—' }}</dd>
                    </div>
                </div>
            @endforeach
        </dl>

        {{-- Key Personnel --}}
        @if (filled($user->key_personnel))
            <div class="mt-5 pt-5 border-t border-uh-border">
                <h3 class="text-sm font-bold text-uh-fg mb-3 flex items-center gap-2">
                    <x-heroicon-o-users class="w-4 h-4 text-uh-red" />
                    Key Personnel
                </h3>
                <div class="space-y-2">
                    @foreach ($user->key_personnel as $person)
                        @if (filled($person['title'] ?? null) || filled($person['name'] ?? null))
                            <div class="flex items-center gap-3 rounded-lg border border-uh-border bg-uh-muted/50 px-4 py-2.5">
                                <div class="w-8 h-8 rounded-lg bg-uh-red/10 flex items-center justify-center text-uh-red shrink-0">
                                    <x-heroicon-o-user class="w-4 h-4" />
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-uh-fg">{{ $person['name'] ?? '—' }}</p>
                                    <p class="text-xs text-gray-500">{{ $person['title'] ?? '—' }}</p>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Sidebar --}}
    <div class="space-y-6">
        {{-- Account card --}}
        <div class="card p-6">
            <h2 class="text-lg font-bold text-uh-fg mb-5 flex items-center gap-2">
                <x-heroicon-o-cog-6-tooth class="w-5 h-5 text-uh-red" />
                Account
            </h2>
            <div class="space-y-4">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider mb-1.5">Role</p>
                    <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full
                        {{ $user->role === 'admin' ? 'bg-uh-red/10 text-uh-red border border-uh-red/20' :
                           ($user->role === 'reviewer' ? 'bg-blue-50 text-blue-700 border border-blue-200' :
                           'bg-green-50 text-green-700 border border-green-200') }}">
                        {{ ucfirst($user->role) }}
                    </span>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider mb-1.5">Status</p>
                    <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full
                        {{ $user->status === 'active' ? 'bg-green-50 text-green-700 border border-green-200' :
                           ($user->status === 'invited' ? 'bg-yellow-50 text-yellow-700 border border-yellow-200' :
                           'bg-gray-100 text-gray-600 border border-gray-200') }}">
                        {{ ucfirst($user->status) }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Round invitations --}}
        <div class="card p-6">
            <h2 class="text-lg font-bold text-uh-fg mb-5 flex items-center gap-2">
                <x-heroicon-o-academic-cap class="w-5 h-5 text-uh-red" />
                Round Invitations
            </h2>
            @forelse ($rounds as $round)
                <div class="flex items-center justify-between py-2.5 {{ !$loop->last ? 'border-b border-uh-border' : '' }}">
                    <span class="text-sm text-uh-fg font-medium">{{ $round->name }}</span>
                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full
                        {{ $round->status === 'open' ? 'bg-green-50 text-green-700' :
                           ($round->status === 'closed' ? 'bg-gray-100 text-gray-500' : 'bg-yellow-50 text-yellow-700') }}">
                        {{ ucfirst($round->status) }}
                    </span>
                </div>
            @empty
                <div class="text-center py-6">
                    <x-heroicon-o-academic-cap class="w-10 h-10 text-gray-300 mx-auto mb-2" />
                    <p class="text-sm text-gray-400">Not invited to any rounds.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

{{-- Submissions (if submitter) --}}
@if ($user->role === 'submitter' && $submissions->isNotEmpty())
<div class="card p-6 mt-6">
    <h2 class="text-lg font-bold text-uh-fg mb-5 flex items-center gap-2">
        <x-heroicon-o-document-text class="w-5 h-5 text-uh-red" />
        Submissions
        <span class="ml-auto text-sm font-normal text-gray-400">{{ $submissions->count() }} total</span>
    </h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-gray-500 uppercase tracking-wider border-b border-uh-border">
                    <th class="pb-3 pr-4 font-semibold">Round</th>
                    <th class="pb-3 pr-4 font-semibold">Title</th>
                    <th class="pb-3 pr-4 font-semibold">Status</th>
                    <th class="pb-3 pr-4 font-semibold">Amount</th>
                    <th class="pb-3 font-semibold">Submitted</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-uh-border">
                @foreach ($submissions as $submission)
                    <tr class="hover:bg-uh-muted/50 transition-colors">
                        <td class="py-3 pr-4 text-gray-600">{{ $submission->round->name }}</td>
                        <td class="py-3 pr-4 font-medium text-uh-fg">{{ $submission->title }}</td>
                        <td class="py-3 pr-4"><span class="badge-gray">{{ ucfirst($submission->status) }}</span></td>
                        <td class="py-3 pr-4 text-gray-600">{{ $submission->amount_requested ? '$' . number_format($submission->amount_requested, 2) : '—' }}</td>
                        <td class="py-3 text-gray-500">{{ $submission->created_at->format('M j, Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Review assignments (if reviewer) --}}
@if ($user->role === 'reviewer' && $reviewAssignments->isNotEmpty())
<div class="card p-6 mt-6">
    <h2 class="text-lg font-bold text-uh-fg mb-5 flex items-center gap-2">
        <x-heroicon-o-clipboard-document-check class="w-5 h-5 text-uh-red" />
        Review Assignments
        <span class="ml-auto text-sm font-normal text-gray-400">{{ $reviewAssignments->count() }} total</span>
    </h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-gray-500 uppercase tracking-wider border-b border-uh-border">
                    <th class="pb-3 pr-4 font-semibold">Round</th>
                    <th class="pb-3 pr-4 font-semibold">Submission</th>
                    <th class="pb-3 pr-4 font-semibold">Status</th>
                    <th class="pb-3 font-semibold">Assigned</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-uh-border">
                @foreach ($reviewAssignments as $assignment)
                    <tr class="hover:bg-uh-muted/50 transition-colors">
                        <td class="py-3 pr-4 text-gray-600">{{ $assignment->submission->round->name }}</td>
                        <td class="py-3 pr-4 font-medium text-uh-fg">{{ $assignment->submission->title }}</td>
                        <td class="py-3 pr-4">
                            @if ($assignment->completed_at)
                                <span class="badge-green">Completed</span>
                            @else
                                <span class="badge-gray">Pending</span>
                            @endif
                        </td>
                        <td class="py-3 text-gray-500">{{ $assignment->assigned_at?->format('M j, Y') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
