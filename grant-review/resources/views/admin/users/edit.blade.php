@extends('layouts.admin')
@section('title', 'Edit User')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-uh-fg">Edit User</h1>
    <p class="text-sm text-gray-500 mt-1">{{ config('hub.enabled') ? 'Update profile and round invitations. Role and access are managed in App Hub.' : 'Update profile, role, status, and round invitations' }}</p>
</div>

<div class="card p-6 max-w-2xl">
    <form action="{{ route('admin.users.update', $user) }}" method="POST" class="space-y-4">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="first_name" class="label">First Name</label>
                <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $user->first_name) }}" required class="input">
                @error('first_name')
                    <p class="text-sm text-uh-red mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="last_name" class="label">Last Name</label>
                <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $user->last_name) }}" required class="input">
                @error('last_name')
                    <p class="text-sm text-uh-red mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="email" class="label">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required class="input">
            @error('email')
                <p class="text-sm text-uh-red mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Shared profile fields: Department, Phone, Title, PeopleSoft ID, Investigator Type --}}
        <x-users.partials.profile-fields :user="$user" />

        @if(config('hub.enabled'))
            <div class="grid grid-cols-2 gap-4 rounded-md border border-uh-border bg-gray-50 p-4">
                <div><span class="label">Role</span><span class="badge-gray">{{ ucfirst($user->role) }}</span></div>
                <div><span class="label">Status</span><span class="badge-gray">{{ ucfirst($user->status) }}</span></div>
            </div>
        @else
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="role" class="label">Role</label>
                    <select id="role" name="role" class="input">
                        <option value="submitter" {{ old('role', $user->role) === 'submitter' ? 'selected' : '' }}>Submitter</option>
                        <option value="reviewer" {{ old('role', $user->role) === 'reviewer' ? 'selected' : '' }}>Reviewer</option>
                        <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Admin</option>
                    </select>
                </div>
                <div>
                    <label for="status" class="label">Status</label>
                    <select id="status" name="status" class="input">
                        <option value="invited" {{ old('status', $user->status) === 'invited' ? 'selected' : '' }}>Invited</option>
                        <option value="active" {{ old('status', $user->status) === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="disabled" {{ old('status', $user->status) === 'disabled' ? 'selected' : '' }}>Disabled</option>
                    </select>
                </div>
            </div>
        @endif

        <div>
            <span class="label">Round Invitations</span>
            <div class="max-h-40 overflow-y-auto border border-uh-border rounded-md p-3 space-y-2">
                @forelse ($rounds as $round)
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="round_ids[]" value="{{ $round->id }}"
                            class="rounded border-uh-border text-uh-red focus:ring-uh-red"
                            {{ in_array($round->id, old('round_ids', $invitedRoundIds)) ? 'checked' : '' }}>
                        <span>{{ $round->name }}</span>
                        @if($round->status === 'open')
                            <span class="badge-green text-[10px]">open</span>
                        @endif
                    </label>
                @empty
                    <p class="text-sm text-gray-400">No rounds available.</p>
                @endforelse
            </div>
        </div>

        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-500 hover:underline">Cancel</a>
            <button type="submit" class="btn-primary">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
                </svg>
                Save Changes
            </button>
        </div>
    </form>
</div>
@endsection
