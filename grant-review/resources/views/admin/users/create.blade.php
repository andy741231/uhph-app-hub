@extends('layouts.admin')
@section('title', 'Add User')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-uh-fg">Add User</h1>
    <p class="text-sm text-gray-500 mt-1">Invite a single user or bulk import submitters via CSV</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Single user form --}}
    <div class="card p-6">
        <h2 class="text-lg font-bold text-uh-fg mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-uh-red" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
            </svg>
            Single User
        </h2>

        <form action="{{ route('admin.users.store') }}" method="POST" class="space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="first_name" class="label">First Name</label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" required class="input">
                    @error('first_name')
                        <p class="text-sm text-uh-red mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="last_name" class="label">Last Name</label>
                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" required class="input">
                    @error('last_name')
                        <p class="text-sm text-uh-red mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            <div>
                <label for="email" class="label">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required class="input">
                <p class="text-xs text-gray-400 mt-1">Only @uh.edu, @central.uh.edu, or @cougarnet.uh.edu addresses.</p>
                @error('email')
                    <p class="text-sm text-uh-red mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="role" class="label">Role</label>
                <select id="role" name="role" class="input">
                    <option value="submitter" {{ old('role') === 'submitter' ? 'selected' : '' }}>Submitter</option>
                    <option value="reviewer" {{ old('role') === 'reviewer' ? 'selected' : '' }}>Reviewer</option>
                    <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                </select>
            </div>
            <div>
                <span class="label">Invite to Rounds <span class="text-gray-400 font-normal">(optional)</span></span>
                <div class="max-h-40 overflow-y-auto border border-uh-border rounded-md p-3 space-y-2">
                    @forelse ($rounds as $round)
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" name="round_ids[]" value="{{ $round->id }}"
                                class="rounded border-uh-border text-uh-red focus:ring-uh-red"
                                {{ in_array($round->id, old('round_ids', [])) ? 'checked' : '' }}>
                            <span>{{ $round->name }}</span>
                            @if($round->status === 'open')
                                <span class="badge-green text-[10px]">open</span>
                            @endif
                        </label>
                    @empty
                        <p class="text-sm text-gray-400">No non-closed rounds available.</p>
                    @endforelse
                </div>
            </div>
            <div class="pt-2">
                <button type="submit" class="btn-primary w-full">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>
                    </svg>
                    Add & Send Invite
                </button>
            </div>
        </form>
    </div>

    {{-- CSV bulk import --}}
    <div class="card p-6">
        <h2 class="text-lg font-bold text-uh-fg mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-uh-red" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
            </svg>
            CSV Bulk Import
        </h2>

        <div class="bg-uh-muted rounded-md p-3 mb-4">
            <p class="text-xs text-gray-600 mb-2">CSV must have a header row with these columns:</p>
            <code class="text-xs font-mono text-uh-red font-semibold">email, first_name, last_name</code>
            <p class="text-xs text-gray-500 mt-2">All imported users get role <strong>submitter</strong> and are invited to the selected round.</p>
        </div>

        <form action="{{ route('admin.users.import') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label for="round_id" class="label">Target Round</label>
                <select id="round_id" name="round_id" required class="input">
                    <option value="">Select round...</option>
                    @foreach ($rounds as $round)
                        <option value="{{ $round->id }}" {{ old('round_id') == $round->id ? 'selected' : '' }}>
                            {{ $round->name }} ({{ $round->status }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="csv" class="label">CSV File</label>
                <input type="file" id="csv" name="csv" accept=".csv,.txt" required
                    class="input cursor-pointer file:mr-3 file:py-2 file:px-4 file:rounded file:border-0 file:bg-uh-red file:text-white file:cursor-pointer file:hover:bg-uh-brick">
                <p class="text-xs text-gray-500 mt-1">Max 2MB. Accepted: .csv, .txt</p>
            </div>
            <div class="pt-2">
                <button type="submit" class="btn-secondary w-full">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                    </svg>
                    Import Submitters
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
