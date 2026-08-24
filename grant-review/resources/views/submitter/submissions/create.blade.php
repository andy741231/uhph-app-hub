@extends('layouts.submitter')
@section('title', 'Start a Submission')

@section('content')
<div class="mb-6">
    <a href="{{ route('submitter.submissions.index') }}" class="text-sm text-gray-500 hover:underline inline-flex items-center gap-1 mb-3">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
        </svg>
        Back to my submissions
    </a>
    <p class="text-sm font-semibold text-uh-red mb-1">Submitter workspace</p>
    <h1 class="text-2xl sm:text-3xl font-bold text-uh-fg">Start a submission</h1>
    <p class="text-gray-600 mt-1 max-w-2xl">Submit your proposal for an open funding round. You can edit your submission until the round deadline.</p>
</div>

<div class="max-w-2xl">
    @if ($openRounds->isEmpty())
        <div class="card p-8 text-center">
            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
            </svg>
            <p class="font-medium text-gray-700">No open rounds available</p>
            <p class="text-sm text-gray-500 mt-1">There are no open rounds available for your account right now. Contact the grants administrator if you believe you should be eligible for a round.</p>
        </div>
    @elseif (! $round)
        <div class="card p-8 text-center">
            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
            </svg>
            <p class="font-medium text-gray-700">Select a funding round</p>
            <p class="text-sm text-gray-500 mt-1 mb-4">You're invited to multiple open rounds. Choose one to begin your submission.</p>
            <div class="space-y-2 max-w-sm mx-auto">
                @foreach ($openRounds as $r)
                    <a href="{{ route('submitter.submissions.create', ['round' => $r->id]) }}"
                       class="block card p-4 text-left hover:border-uh-red transition-colors cursor-pointer">
                        <p class="font-semibold text-uh-fg">{{ $r->name }}</p>
                        <p class="text-xs text-gray-500 mt-1">Deadline: {{ $r->deadline_at->format('M j, Y g:i A') }}</p>
                    </a>
                @endforeach
            </div>
        </div>
    @else
        <div class="card p-6">
            {{-- Display the pre-selected round (read-only) --}}
            <div class="mb-5 pb-4 border-b border-uh-border">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Funding round</p>
                <p class="text-lg font-semibold text-uh-fg mt-1">{{ $round->name }}</p>
                <p class="text-sm text-gray-500 mt-0.5">Deadline: {{ $round->deadline_at->format('M j, Y g:i A') }}</p>
            </div>

            <p class="text-sm text-gray-500 mb-5">PDF files only, up to 20 MB. Fields marked with <span class="req">*</span> are required.</p>
            <form id="createForm" action="{{ route('submitter.submissions.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <input type="hidden" name="round_id" value="{{ $round->id }}">
                <input type="hidden" name="submit_now" value="1">

                <div>
                    <label for="title" class="label">Proposal title <span class="req">*</span></label>
                    <input id="title" type="text" name="title" value="{{ old('title') }}" required maxlength="500" class="input" placeholder="Enter a clear proposal title">
                </div>

                <div>
                    <label for="abstract" class="label">Abstract <span class="req">*</span></label>
                    <textarea id="abstract" name="abstract" rows="5" required class="input" placeholder="Briefly describe your proposed work">{{ old('abstract') }}</textarea>
                </div>

                <div>
                    <label for="amount_requested" class="label">Amount requested <span class="req">*</span></label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-gray-500" aria-hidden="true">$</span>
                        <input id="amount_requested" type="number" name="amount_requested" value="{{ old('amount_requested') }}" min="0" step="0.01" required class="input pl-7" placeholder="0.00">
                    </div>
                </div>

                <div>
                    <label for="pdf" class="label">Proposal PDF <span class="req">*</span></label>
                    <input id="pdf" type="file" name="pdf" accept="application/pdf,.pdf" required class="input cursor-pointer file:mr-3 file:py-2 file:px-4 file:rounded file:border-0 file:bg-uh-red file:text-white file:cursor-pointer file:hover:bg-uh-brick">
                    <p class="text-xs text-gray-500 mt-1">Maximum file size: 20 MB. Accepted format: PDF.</p>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" class="btn-accent">Submit</button>
                    <a href="{{ route('submitter.submissions.index') }}" class="btn-secondary">Cancel</a>
                </div>
                <p class="text-xs text-center text-gray-500">You can edit your submission until the round deadline.</p>
            </form>
            <script>
                document.getElementById('createForm').addEventListener('submit', function(e) {
                    if (!this.checkValidity()) return;
                    if (!confirm('Submit this proposal now? You can still edit it until the round deadline.')) {
                        e.preventDefault();
                    }
                });
            </script>
        </div>
    @endif
</div>
@endsection
