@extends('layouts.admin')
@section('title', 'Rounds')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-uh-fg">Funding Rounds</h1>
        <p class="text-sm text-gray-500 mt-1">Manage grant funding cycles</p>
    </div>
    <a href="{{ route('admin.rounds.create') }}" class="btn-primary">
        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
        </svg>
        New Round
    </a>
</div>

<div class="card">
    @if ($rounds->isEmpty())
        <div class="p-8 text-center">
            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
            </svg>
            <p class="text-gray-500 text-sm mb-4">No funding rounds yet.</p>
            <a href="{{ route('admin.rounds.create') }}" class="btn-primary">Create First Round</a>
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
                        <th class="text-right">Actions</th>
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
                            <td class="text-gray-600">{{ $round->opens_at->format('M j, Y g:i A') }}</td>
                            <td class="text-gray-600">{{ $round->deadline_at->format('M j, Y g:i A') }}</td>
                            <td>
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('admin.rounds.edit', $round) }}"
                                       class="text-uh-red hover:underline text-sm inline-flex items-center gap-1 font-medium"
                                       aria-label="Edit {{ $round->name }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/>
                                        </svg>
                                        Edit
                                    </a>
                                    <form action="{{ route('admin.rounds.destroy', $round) }}" method="POST" onsubmit="return confirm('Delete round &quot;{{ $round->name }}&quot;? This will also delete all submissions and reviews in this round.')" class="inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-uh-brick hover:underline text-sm inline-flex items-center gap-1 font-medium" aria-label="Delete {{ $round->name }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                            </svg>
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
