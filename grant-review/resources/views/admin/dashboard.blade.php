@extends('layouts.admin')
@section('title', 'Dashboard')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-uh-fg">Dashboard</h1>
    <p class="text-sm text-gray-500 mt-1">Overview of the UH Grants Portal</p>
</div>

{{-- Stat cards --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="card p-5">
        <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-semibold text-uh-slate">Total Users</span>
            <x-heroicon-o-users class="w-5 h-5 text-uh-red" />
        </div>
        <div class="text-3xl font-bold text-uh-fg">{{ $userCounts['total'] }}</div>
    </div>

    <div class="card p-5">
        <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-semibold text-uh-slate">Submitters</span>
            <x-heroicon-o-user-group class="w-5 h-5 text-uh-red" />
        </div>
        <div class="text-3xl font-bold text-uh-red">{{ $userCounts['submitters'] }}</div>
    </div>

    <div class="card p-5">
        <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-semibold text-uh-slate">Reviewers</span>
            <x-heroicon-o-check-circle class="w-5 h-5 text-uh-green" />
        </div>
        <div class="text-3xl font-bold text-uh-green">{{ $userCounts['reviewers'] }}</div>
    </div>

    <div class="card p-5">
        <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-semibold text-uh-slate">Pending Invites</span>
            <x-heroicon-o-envelope class="w-5 h-5 text-[#B97800]" />
        </div>
        <div class="text-3xl font-bold text-[#B97800]">{{ $userCounts['invited'] }}</div>
    </div>
</div>

{{-- Recent rounds --}}
<div class="flex items-center justify-between mb-3">
    <h2 class="text-lg font-bold text-uh-fg">Recent Rounds</h2>
    <a href="{{ route('admin.rounds.index') }}" class="text-sm text-uh-red font-medium hover:underline">View all →</a>
</div>

<div class="card">
    @if ($rounds->isEmpty())
        <div class="p-8 text-center">
            <x-heroicon-o-calendar class="w-12 h-12 mx-auto text-gray-300 mb-3" />
            <p class="text-gray-500 text-sm">No rounds created yet.</p>
            <a href="{{ route('admin.rounds.create') }}" class="btn-primary mt-4">Create First Round</a>
        </div>
    @else
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Opens</th>
                        <th>Deadline</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rounds as $round)
                        <tr>
                            <td class="font-medium text-uh-fg">{{ $round->name }}</td>
                            <td>
                                @if($round->status === 'open')
                                    <span class="badge-green">Open</span>
                                @elseif($round->status === 'closed')
                                    <span class="badge-gray">Closed</span>
                                @else
                                    <span class="badge-yellow">Draft</span>
                                @endif
                            </td>
                            <td class="text-gray-600">{{ $round->opens_at->format('M j, Y') }}</td>
                            <td class="text-gray-600">{{ $round->deadline_at->format('M j, Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
