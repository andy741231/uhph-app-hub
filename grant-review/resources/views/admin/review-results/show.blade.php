@extends('layouts.admin')
@section('title', 'Review Details — ' . $submission->title)

@section('content')
{{-- Navigation --}}
<div class="mb-6">
    <a href="{{ route('admin.review-results.index') }}"
       class="inline-flex items-center gap-2 text-sm font-semibold text-uh-slate hover:text-uh-red transition-colors group mb-4">
        <span class="w-7 h-7 rounded-full bg-white border border-uh-border flex items-center justify-center text-gray-500 group-hover:border-uh-red group-hover:text-uh-red transition-all shadow-xs" aria-hidden="true">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
            </svg>
        </span>
        Back to Review Results
    </a>

    {{-- Title Card --}}
    <div class="bg-white rounded-xl border border-uh-border p-5 shadow-xs">
        <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 text-xs font-semibold tracking-wider text-uh-red uppercase mb-1">
                    <span>Proposal Review</span>
                    <span>·</span>
                    <span>ID #{{ $submission->id }}</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-bold text-uh-fg leading-tight">{{ $submission->title }}</h1>
                <div class="flex flex-wrap items-center gap-y-1 gap-x-4 mt-2 text-sm text-gray-600">
                    @php $submitter = $submission->submitter; @endphp
                    <span class="inline-flex items-center gap-1 font-medium text-uh-fg">
                        <svg class="w-4 h-4 text-uh-slate" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                        </svg>
                        {{ $submitter->full_name }}
                        @if ($submitter->department)
                            <span class="text-gray-400 font-normal">({{ $submitter->department }})</span>
                        @endif
                    </span>
                    <span class="text-gray-400">·</span>
                    <span class="inline-flex items-center gap-1">
                        <svg class="w-4 h-4 text-uh-slate" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                        </svg>
                        {{ $submission->round->name }}
                    </span>
                    @if ($submission->submitted_at)
                        <span class="text-gray-400">·</span>
                        <span>Submitted {{ $submission->submitted_at->format('M j, Y') }}</span>
                    @endif
                </div>
            </div>

            {{-- Status badge + PDF --}}
            <div class="flex items-center gap-3 shrink-0">
                @if ($submission->status === 'under_review')
                    <span class="badge-yellow px-3 py-1.5">Under Review</span>
                @elseif ($submission->status === 'decided')
                    <span class="badge-green px-3 py-1.5">Decided</span>
                @else
                    <span class="badge-blue px-3 py-1.5">Submitted</span>
                @endif
                <a href="{{ route('submissions.pdf', $submission) }}" target="_blank" rel="noopener"
                   class="btn-secondary text-xs py-2 px-3 inline-flex items-center gap-1.5 shadow-xs">
                    <svg class="w-4 h-4 text-uh-red" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V8.25c0-.621-.504-1.125-1.125-1.125H10.5V2.25Z"/>
                    </svg>
                    View PDF
                </a>
            </div>
        </div>
    </div>
</div>

{{-- Stats Row --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="card p-5 shadow-xs">
        <div class="flex items-center justify-between">
            <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Assigned</p>
            <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a9.127 9.127 0 0 1 5.185-9.166 9.127 9.127 0 0 1 5.185 9.166l-.001.107a9.127 9.127 0 0 1-5.185 9.166 9.127 9.127 0 0 1-5.185-9.166Z"/>
            </svg>
        </div>
        <p class="text-3xl font-black text-uh-fg mt-2">{{ $stats['assigned'] }}</p>
    </div>
    <div class="card p-5 shadow-xs">
        <div class="flex items-center justify-between">
            <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Completed</p>
            <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
            </svg>
        </div>
        <p class="text-3xl font-black text-uh-fg mt-2">{{ $stats['completed'] }}</p>
        @if ($stats['assigned'] > 0)
            <p class="text-xs text-gray-400 mt-1">{{ round(($stats['completed'] / $stats['assigned']) * 100) }}% complete</p>
        @endif
    </div>
    <div class="card p-5 shadow-xs border-l-4 border-l-uh-red">
        <div class="flex items-center justify-between">
            <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Average</p>
            <svg class="w-5 h-5 text-uh-red/40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125 9 9l4.5 3.375L21 6m0 0v4.5M21 6h-4.5M3 19.5h18"/>
            </svg>
        </div>
        <p class="text-3xl font-black text-uh-red mt-2">
            {{ $stats['average'] !== null ? number_format($stats['average'], 2) : '—' }}
        </p>
    </div>
    <div class="card p-5 shadow-xs">
        <div class="flex items-center justify-between">
            <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Range</p>
            <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 7.5 3m0 0L12 7.5M7.5 3v18m13.5-4.5L16.5 21m0 0L12 16.5m4.5 4.5v-18"/>
            </svg>
        </div>
        <p class="text-3xl font-black text-uh-fg mt-2">
            @if ($stats['min'] !== null)
                {{ number_format($stats['min'], 2) }}<span class="text-gray-400 text-xl">–</span>{{ number_format($stats['max'], 2) }}
            @else
                —
            @endif
        </p>
    </div>
</div>

{{-- Two-column layout: proposal info + reviews --}}
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

    {{-- LEFT: Proposal Details --}}
    <div class="lg:col-span-5 space-y-6 lg:sticky lg:top-6">

        {{-- Proposal Info Card --}}
        <div class="card p-5 shadow-xs">
            <h2 class="text-sm font-bold uppercase tracking-wider text-uh-fg mb-4 flex items-center gap-1.5">
                <svg class="w-4 h-4 text-uh-red" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V8.25c0-.621-.504-1.125-1.125-1.125H10.5V2.25Z"/>
                </svg>
                Proposal Details
            </h2>
            <div class="space-y-3 text-sm">
                <div class="flex items-center justify-between py-2 border-b border-uh-border">
                    <span class="text-gray-500">Amount Requested</span>
                    <span class="font-bold text-uh-fg">
                        {{ $submission->amount_requested !== null ? '$' . number_format((float) $submission->amount_requested, 2) : '—' }}
                    </span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-uh-border">
                    <span class="text-gray-500">Submitter</span>
                    <span class="font-medium text-uh-fg">{{ $submitter->full_name }}</span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-uh-border">
                    <span class="text-gray-500">Department</span>
                    <span class="font-medium text-uh-fg">{{ $submitter->department ?? '—' }}</span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-uh-border">
                    <span class="text-gray-500">Round</span>
                    <span class="font-medium text-uh-fg">{{ $submission->round->name }}</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-gray-500">Submitted</span>
                    <span class="font-medium text-uh-fg">{{ $submission->submitted_at?->format('M j, Y g:i A') ?? '—' }}</span>
                </div>
            </div>

            @if ($submission->abstract)
                <div class="mt-4 pt-4 border-t border-uh-border">
                    <p class="text-xs font-bold text-uh-fg uppercase tracking-wider mb-2">Abstract</p>
                    <div class="bg-uh-muted rounded-lg p-4 text-sm text-gray-700 leading-relaxed max-h-48 overflow-y-auto border border-uh-border">
                        {{ $submission->abstract }}
                    </div>
                </div>
            @endif
        </div>

        {{-- Decision Card (if any) --}}
        @if ($submission->decision)
            <div class="card p-5 shadow-xs border-l-4 {{ $submission->decision->outcome === 'funded' ? 'border-l-uh-green' : 'border-l-gray-400' }}">
                <h2 class="text-sm font-bold uppercase tracking-wider text-uh-fg mb-4 flex items-center gap-1.5">
                    <svg class="w-4 h-4 {{ $submission->decision->outcome === 'funded' ? 'text-uh-green' : 'text-gray-400' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                    Decision
                </h2>
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between py-2 border-b border-uh-border">
                        <span class="text-gray-500">Outcome</span>
                        <span class="font-bold {{ $submission->decision->outcome === 'funded' ? 'text-uh-green' : 'text-gray-600' }}">
                            {{ $submission->decision->outcome === 'funded' ? 'Funded' : 'Not Funded' }}
                        </span>
                    </div>
                    @if ($submission->decision->amount_awarded !== null)
                        <div class="flex items-center justify-between py-2 border-b border-uh-border">
                            <span class="text-gray-500">Amount Awarded</span>
                            <span class="font-bold text-uh-fg">${{ number_format((float) $submission->decision->amount_awarded, 2) }}</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between py-2 border-b border-uh-border">
                        <span class="text-gray-500">Decided By</span>
                        <span class="font-medium text-uh-fg">{{ $submission->decision->decidedBy?->full_name ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-gray-500">Decided At</span>
                        <span class="font-medium text-uh-fg">{{ $submission->decision->decided_at?->format('M j, Y g:i A') ?? '—' }}</span>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- RIGHT: Individual Reviews --}}
    <div class="lg:col-span-7">
        <div class="card shadow-xs overflow-hidden">
            {{-- Header --}}
            <div class="px-5 py-4 border-b border-uh-border bg-uh-muted">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-uh-fg">Individual Reviews</h2>
                        <p class="text-sm text-gray-500 mt-0.5">
                            {{ $stats['completed'] }} of {{ $stats['assigned'] }} submitted
                        </p>
                    </div>
                    @if ($stats['assigned'] > 0 && $stats['completed'] > 0)
                        <div class="flex items-center gap-2">
                            <div class="w-24 h-2 bg-gray-200 rounded-full overflow-hidden">
                                <div class="h-full bg-uh-red rounded-full transition-all" style="width: {{ round(($stats['completed'] / $stats['assigned']) * 100) }}%"></div>
                            </div>
                            <span class="text-xs font-bold text-uh-fg">{{ round(($stats['completed'] / $stats['assigned']) * 100) }}%</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Reviews list --}}
            <div class="divide-y divide-uh-border">
                @forelse ($assignments as $assignment)
                    @php $review = $assignment->review; @endphp
                    <div class="px-5 py-4 hover:bg-gray-50/50 transition-colors">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-3 min-w-0">
                                {{-- Reviewer avatar --}}
                                <div class="w-10 h-10 rounded-full bg-uh-red/10 flex items-center justify-center text-uh-red font-bold shrink-0">
                                    {{ strtoupper(substr($assignment->reviewer->first_name, 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-semibold text-uh-fg text-sm">{{ $assignment->reviewer->full_name }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        Assigned {{ $assignment->assigned_at?->format('M j, Y') }}
                                        @if ($review?->submitted_at)
                                            <span class="text-gray-400">·</span>
                                            Submitted {{ $review->submitted_at->format('M j, Y g:i A') }}
                                        @endif
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 shrink-0">
                                @if ($review?->submitted_at)
                                    <span class="badge-green text-xs">Submitted</span>
                                @elseif ($review && ($review->score !== null || $review->comments))
                                    <span class="badge-yellow text-xs">Draft</span>
                                @else
                                    <span class="badge-gray text-xs">Not started</span>
                                @endif
                                @if ($review?->score !== null)
                                    <span class="text-2xl font-black text-uh-red leading-none">{{ number_format((float) $review->score, 2) }}</span>
                                @endif
                            </div>
                        </div>

                        @if ($review?->comments)
                            <div class="mt-3 ml-13 bg-uh-muted rounded-lg p-3.5 border border-uh-border">
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Comments</p>
                                <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ $review->comments }}</p>
                            </div>
                        @endif

                        @if ($review && $review->revisions()->exists())
                            <div class="mt-3 ml-13">
                                <a href="{{ route('admin.review-results.timeline', [$submission, $review]) }}"
                                   class="text-xs text-uh-red hover:underline font-semibold inline-flex items-center gap-1 cursor-pointer"
                                   title="View submission history">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                    </svg>
                                    View timeline ({{ $review->revisions()->count() }})
                                </a>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="px-5 py-12 text-center">
                        <svg class="w-12 h-12 mx-auto text-gray-200 mb-3" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a9.127 9.127 0 0 1 5.185-9.166 9.127 9.127 0 0 1 5.185 9.166l-.001.107a9.127 9.127 0 0 1-5.185 9.166 9.127 9.127 0 0 1-5.185-9.166Z"/>
                        </svg>
                        <p class="font-medium text-gray-700">No reviewers assigned</p>
                        <p class="text-sm text-gray-500 mt-1">Assign reviewers from the Assign Reviewers page.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
