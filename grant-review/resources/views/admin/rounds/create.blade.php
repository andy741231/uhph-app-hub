@extends('layouts.admin')
@section('title', 'New Round')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-uh-fg">Create Funding Round</h1>
    <p class="text-sm text-gray-500 mt-1">Set up a new grant funding cycle</p>
</div>

<div class="card max-w-2xl p-6">
    <form action="{{ route('admin.rounds.store') }}" method="POST">
        @csrf
        <div class="space-y-5">
            <div>
                <label for="name" class="label">Round Name</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required
                    class="input" placeholder="e.g. Spring 2027">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="opens_at" class="label">Opens At</label>
                    <input type="datetime-local" id="opens_at" name="opens_at" value="{{ old('opens_at') }}" required
                        class="input">
                </div>
                <div>
                    <label for="deadline_at" class="label">Deadline</label>
                    <input type="datetime-local" id="deadline_at" name="deadline_at" value="{{ old('deadline_at') }}" required
                        class="input">
                </div>
            </div>

            <div>
                <label for="status" class="label">Status</label>
                <select id="status" name="status" class="input">
                    <option value="draft" {{ old('status') === 'draft' ? 'selected' : '' }}>Draft (hidden from submitters)</option>
                    <option value="open" {{ old('status') === 'open' ? 'selected' : '' }}>Open (accepting submissions)</option>
                    <option value="closed" {{ old('status') === 'closed' ? 'selected' : '' }}>Closed (no new submissions)</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Start as draft while you set up invitations, then switch to open when ready.</p>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="btn-primary">Create Round</button>
                <a href="{{ route('admin.rounds.index') }}" class="btn-ghost">Cancel</a>
            </div>
        </div>
    </form>
</div>
@endsection
