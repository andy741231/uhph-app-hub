@extends('layouts.submitter')
@section('title', 'Edit Submission')

@section('content')
<div class="mb-6">
    <a href="{{ route('submitter.submissions.show', $submission) }}" class="text-sm text-gray-500 hover:underline inline-flex items-center gap-1 mb-3">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
        </svg>
        Back to submission
    </a>
    <p class="text-sm font-semibold text-uh-red mb-1">Submitter workspace</p>
    <h1 class="text-2xl sm:text-3xl font-bold text-uh-fg">Edit submission</h1>
    <p class="text-gray-600 mt-1 max-w-2xl">Update your proposal details or replace the PDF. Changes can be made until the round deadline.</p>
</div>

<div class="max-w-2xl">
    <div class="card p-6">
        {{-- Display the round (read-only) --}}
        <div class="mb-5 pb-4 border-b border-uh-border">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Funding round</p>
            <p class="text-lg font-semibold text-uh-fg mt-1">{{ $submission->round->name }}</p>
            <p class="text-sm text-gray-500 mt-0.5">
                Deadline: {{ $submission->round->deadline_at->format('M j, Y g:i A') }}
                @if (now()->gt($submission->round->deadline_at))
                    <span class="text-red-600 font-medium">· Passed</span>
                @endif
            </p>
        </div>

        <p class="text-sm text-gray-500 mb-5">Fields marked with <span class="req">*</span> are required.</p>
        <form action="{{ route('submitter.submissions.update', $submission) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf @method('PUT')

            <div>
                <label for="title" class="label">Proposal title <span class="req">*</span></label>
                <input id="title" type="text" name="title" value="{{ old('title', $submission->title) }}" required maxlength="500" class="input" placeholder="Enter a clear proposal title">
            </div>

            <div>
                <label for="abstract" class="label">Abstract <span class="req">*</span></label>
                <textarea id="abstract" name="abstract" rows="5" required class="input" placeholder="Briefly describe your proposed work">{{ old('abstract', $submission->abstract) }}</textarea>
            </div>

            <div>
                <label for="amount_requested" class="label">Amount requested <span class="req">*</span></label>
                <div class="relative">
                    <span class="absolute left-3 top-2.5 text-gray-500" aria-hidden="true">$</span>
                    <input id="amount_requested" type="number" name="amount_requested" value="{{ old('amount_requested', $submission->amount_requested) }}" min="0" step="0.01" required class="input pl-7" placeholder="0.00">
                </div>
            </div>

            <div>
                <label for="pdf" class="label">Replace PDF</label>
                <input id="pdf" type="file" name="pdf" accept="application/pdf,.pdf" class="input cursor-pointer file:mr-3 file:py-2 file:px-4 file:rounded file:border-0 file:bg-uh-red file:text-white file:cursor-pointer file:hover:bg-uh-brick">
                <p class="text-xs text-gray-500 mt-1">Leave blank to keep the current PDF. Maximum file size: 20 MB.</p>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="btn-primary">
                    Save changes
                </button>
                <a href="{{ route('submitter.submissions.show', $submission) }}" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
