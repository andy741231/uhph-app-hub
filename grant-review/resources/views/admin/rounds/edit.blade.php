@extends('layouts.admin')
@section('title', 'Edit Round')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-uh-fg">Edit Round</h1>
    <p class="text-sm text-gray-500 mt-1">{{ $round->name }}</p>
</div>

<div class="card max-w-2xl p-6">
    <form action="{{ route('admin.rounds.update', $round) }}" method="POST">
        @csrf @method('PUT')
        <div class="space-y-5">
            <div>
                <label for="name" class="label">Round Name</label>
                <input type="text" id="name" name="name" value="{{ old('name', $round->name) }}" required
                    class="input">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="opens_at" class="label">Opens At</label>
                    <input type="datetime-local" id="opens_at" name="opens_at"
                        value="{{ old('opens_at', $round->opens_at->format('Y-m-d\TH:i')) }}" required
                        class="input">
                </div>
                <div>
                    <label for="deadline_at" class="label">Deadline</label>
                    <input type="datetime-local" id="deadline_at" name="deadline_at"
                        value="{{ old('deadline_at', $round->deadline_at->format('Y-m-d\TH:i')) }}" required
                        class="input">
                </div>
            </div>

            <div>
                <label for="status" class="label">Status</label>
                <select id="status" name="status" class="input">
                    @foreach(['draft', 'open', 'closed'] as $s)
                        <option value="{{ $s }}" {{ old('status', $round->status) === $s ? 'selected' : '' }}>
                            @if($s === 'draft') Draft (hidden from submitters)
                            @elseif($s === 'open') Open (accepting submissions)
                            @else Closed (no new submissions)
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="btn-primary">Save Changes</button>
                <a href="{{ route('admin.rounds.index') }}" class="btn-ghost">Cancel</a>
            </div>
        </div>
    </form>
</div>
@endsection
