@extends('layouts.admin')
@section('title', 'Users')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-uh-fg">Users</h1>
        <p class="text-sm text-gray-500 mt-1">Manage submitters, reviewers, and admins</p>
    </div>
    <div class="flex items-center gap-3">
        @if(config('hub.enabled'))
            <a href="{{ route('admin.users.index', ['archived' => $showArchived ? null : 1]) }}" class="btn-secondary">
                {{ $showArchived ? 'Active Users' : 'Archived Users' }}
            </a>
        @endif
        <a href="{{ route('admin.users.create') }}" class="btn-primary">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            Add User
        </a>
    </div>
</div>

@if(config('hub.enabled'))
    <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
        UHPH App Hub manages identities and access. This page synchronizes Grant Review assignments from UHPH App Hub; revoked or deleted identities are archived locally so submissions, reviews, and decision history remain intact.
    </div>
@endif

{{-- Search & filter bar --}}
<div class="card p-4 mb-4">
    <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
        @if($showArchived)<input type="hidden" name="archived" value="1">@endif
        <div class="flex-1 w-full sm:w-auto">
            <div class="relative">
                <svg class="w-4 h-4 absolute left-3 top-2.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                </svg>
                <input type="text" name="search" value="{{ $search }}"
                    class="input pl-9" placeholder="Search name, email, or department...">
            </div>
        </div>
        <div>
            <select name="role" class="input">
                <option value="">All Roles</option>
                <option value="admin" {{ $role === 'admin' ? 'selected' : '' }}>Admin</option>
                <option value="submitter" {{ $role === 'submitter' ? 'selected' : '' }}>Submitter</option>
                <option value="reviewer" {{ $role === 'reviewer' ? 'selected' : '' }}>Reviewer</option>
            </select>
        </div>
        <button type="submit" class="btn-secondary">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z"/>
            </svg>
            Filter
        </button>
        @if ($search || $role)
            <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-500 hover:text-uh-red hover:underline">Clear</a>
        @endif
    </form>
</div>

<div class="card">
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    @php
                        $sortCols = ['first_name' => 'Name', 'email' => 'Email', 'role' => 'Role', 'department' => 'Department', 'status' => 'Status'];
                    @endphp
                    @foreach ($sortCols as $col => $label)
                        <th>
                            @php
                                $isSorted = $sort === $col;
                                $nextDir = $isSorted && $direction === 'asc' ? 'desc' : 'asc';
                            @endphp
                            <a href="{{ route('admin.users.index', array_filter(['archived' => $showArchived ? 1 : null, 'search' => $search, 'role' => $role, 'sort' => $col, 'direction' => $nextDir])) }}"
                               class="inline-flex items-center gap-1 hover:text-uh-red {{ $isSorted ? 'text-uh-red' : '' }}">
                                {{ $label }}
                                @if ($isSorted)
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                        @if ($direction === 'asc')
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5"/>
                                        @else
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                                        @endif
                                    </svg>
                                @endif
                            </a>
                        </th>
                    @endforeach
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td class="font-medium text-uh-fg">
                            <a href="{{ route('admin.users.show', $user) }}" class="hover:text-uh-red hover:underline">{{ $user->full_name }}</a>
                        </td>
                        <td class="text-gray-600">{{ $user->email }}</td>
                        <td>
                            @if($user->role === 'admin')
                                <span class="badge-blue">Admin</span>
                            @elseif($user->role === 'submitter')
                                <span class="badge-gray">Submitter</span>
                            @else
                                <span class="badge-green">Reviewer</span>
                            @endif
                        </td>
                        <td class="text-gray-600">{{ $user->department ?: '—' }}</td>
                        <td>
                            @if($user->status === 'active')
                                <span class="badge-green">Active</span>
                            @elseif($user->status === 'invited')
                                <span class="badge-yellow">Invited</span>
                            @else
                                <span class="badge-gray">Disabled</span>
                            @endif
                        </td>
                        <td>
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.users.edit', $user) }}"
                                   class="text-uh-red hover:underline text-sm inline-flex items-center gap-1 font-medium"
                                   aria-label="Edit {{ $user->full_name }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
                                    </svg>
                                    Edit
                                </a>
                                @if (config('hub.enabled') && ! $showArchived && $user->id !== auth()->id())
                                    <form action="{{ route('admin.users.revoke', $user) }}" method="POST"
                                          onsubmit="return confirm('Revoke Grant Review access for {{ $user->full_name }}? Historical records will be preserved.');">
                                        @csrf
                                        <button type="submit"
                                                class="text-uh-brick hover:underline text-sm inline-flex items-center gap-1 font-medium"
                                                aria-label="Revoke access for {{ $user->full_name }}">
                                            Revoke Access
                                        </button>
                                    </form>
                                @endif
                                @if (config('hub.enabled') && $showArchived && is_string($user->sso_sub))
                                    <form action="{{ route('admin.users.restore', $user) }}" method="POST"
                                          onsubmit="return confirm('Restore Grant Review access for {{ $user->full_name }}? They will be re-assigned with their previous role ({{ ucfirst($user->role) }}).');">
                                        @csrf
                                        <button type="submit"
                                                class="text-uh-red hover:underline text-sm inline-flex items-center gap-1 font-medium"
                                                aria-label="Restore access for {{ $user->full_name }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                                            </svg>
                                            Restore Access
                                        </button>
                                    </form>
                                @endif
                                @if (! config('hub.enabled') && $user->id !== auth()->id())
                                    <form action="{{ route('admin.users.destroy', $user) }}" method="POST"
                                          onsubmit="return confirm('Delete user {{ $user->full_name }}? This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="text-uh-brick hover:underline text-sm inline-flex items-center gap-1 font-medium"
                                                aria-label="Delete {{ $user->full_name }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                            </svg>
                                            Delete
                                        </button>
                                    </form>
                                @endif
                                @if (! config('hub.enabled') && $user->status === 'invited')
                                    <form action="{{ route('admin.users.resend-invite', $user) }}" method="POST">
                                        @csrf
                                        <button type="submit"
                                                class="text-uh-slate hover:text-uh-red text-sm inline-flex items-center gap-1 font-medium"
                                                aria-label="Resend invite to {{ $user->email }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                                            </svg>
                                            Resend
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-8 text-gray-500">
                            @if ($search || $role)
                                No users match your filters. <a href="{{ route('admin.users.index') }}" class="text-uh-red hover:underline">Clear filters</a>.
                            @else
                                No users found.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($users->hasPages())
        <div class="px-4 py-3 border-t border-uh-border">
            {{ $users->links() }}
        </div>
    @endif
</div>
@endsection
