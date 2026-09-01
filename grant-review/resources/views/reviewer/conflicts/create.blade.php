@extends('layouts.reviewer')
@section('title', 'Conflict of Interest — ' . $round->name)

@section('content')
<div class="mb-6">
    <a href="{{ route('reviewer.dashboard') }}"
       class="inline-flex items-center gap-2 text-sm font-semibold text-uh-slate hover:text-uh-red transition-colors group mb-4">
        <span class="w-7 h-7 rounded-full bg-white border border-uh-border flex items-center justify-center text-gray-500 group-hover:border-uh-red group-hover:text-uh-red transition-all shadow-xs" aria-hidden="true">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
            </svg>
        </span>
        Back to My Reviews
    </a>

    {{-- Title Card --}}
    <div class="bg-white rounded-xl border border-uh-border p-5 shadow-xs">
        <div class="flex items-center gap-2 text-xs font-semibold tracking-wider text-uh-red uppercase mb-1">
            <span>Required declaration</span>
            <span>·</span>
            <span>{{ $round->name }}</span>
        </div>
        <h1 class="text-xl sm:text-2xl font-bold text-uh-fg leading-tight">Conflict of Interest Declaration</h1>
        <p class="text-sm text-gray-600 mt-2 max-w-3xl leading-relaxed">
            Before reviewing any proposal in this round, you must declare any conflicts of interest.
            Review each submitter and proposal below and check the box for any where you have a conflict
            — personal, professional, financial, or otherwise. When a conflict is checked, please briefly
            describe its nature. Your declaration is recorded on your profile and reported to the grants administrator.
        </p>
    </div>
</div>

@if ($existing)
    <div class="mb-6 bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-lg flex items-start gap-3" role="status">
        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
        </svg>
        <div class="text-sm">
            <p class="font-semibold">You already submitted a declaration for this round on {{ $existing->declared_at->format('M j, Y g:i A') }}.</p>
            <p class="mt-0.5">You may update it below — submitting again will replace your previous declaration and notify the administrator again.</p>
        </div>
    </div>
@endif

<form action="{{ route('reviewer.conflicts.store', $round) }}" method="POST" id="coiForm">
    @csrf
    @if ($returnTo)
        <input type="hidden" name="return_to" value="{{ $returnTo }}">
    @endif

    {{-- Empty submissions state --}}
    @if ($submissions->isEmpty())
        <div class="card p-10 text-center">
            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
            <p class="font-medium text-gray-700">No submitted proposals yet</p>
            <p class="text-sm text-gray-500 mt-1 max-w-md mx-auto">There are no submitted proposals in this round to declare conflicts for. You can submit an empty declaration now, or return later once proposals are available.</p>
        </div>
    @else
        <div class="card shadow-xs overflow-hidden">
            {{-- Header --}}
            <div class="px-5 py-4 border-b border-uh-border bg-uh-muted">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-uh-fg">Proposals in this round</h2>
                        <p class="text-sm text-gray-500 mt-0.5">
                            {{ $submissions->count() }} submitted proposal{{ $submissions->count() === 1 ? '' : 's' }}
                        </p>
                    </div>
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Check all that apply</span>
                </div>
            </div>

            {{-- Proposal list --}}
            <div class="divide-y divide-uh-border">
                @foreach ($submissions as $submission)
                    @php
                        $existingEntry = $existingConflicts->get($submission->id);
                        $isChecked = $existingEntry !== null;
                        $rowId = 'coi-' . $submission->id;
                    @endphp
                    <div class="px-5 py-4" data-coi-row>
                        <div class="flex items-start gap-4">
                            {{-- Checkbox --}}
                            <div class="flex items-center h-6 shrink-0 pt-0.5">
                                <input type="hidden" name="conflicts[{{ $submission->id }}][has_conflict]" value="0">
                                <input type="checkbox"
                                       id="{{ $rowId }}"
                                       name="conflicts[{{ $submission->id }}][has_conflict]"
                                       value="1"
                                       class="w-5 h-5 rounded border-gray-300 text-uh-red focus:ring-uh-red cursor-pointer coi-checkbox"
                                       data-coi-toggle="{{ $submission->id }}"
                                       {{ $isChecked ? 'checked' : '' }}>
                            </div>

                            {{-- Submitter + title --}}
                            <label for="{{ $rowId }}" class="flex-1 min-w-0 cursor-pointer">
                                <div class="flex items-start gap-3">
                                    <div class="w-9 h-9 rounded-full bg-uh-red/10 flex items-center justify-center text-uh-red font-bold shrink-0 text-sm" aria-hidden="true">
                                        {{ strtoupper(substr($submission->submitter->first_name ?? '?', 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-uh-fg text-sm leading-snug">{{ $submission->title }}</p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            <span class="font-medium text-gray-700">{{ $submission->submitter->full_name }}</span>
                                            @if ($submission->submitter->department)
                                                <span class="text-gray-400">·</span>
                                                <span>{{ $submission->submitter->department }}</span>
                                            @endif
                                        </p>
                                        @if (filled($submission->submitter->key_personnel))
                                            <div class="mt-2">
                                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Key Personnel</p>
                                                <ul class="space-y-0.5">
                                                    @foreach ($submission->submitter->key_personnel as $person)
                                                        @if (filled($person['name'] ?? null))
                                                            <li class="text-xs text-gray-600 flex items-center gap-1.5">
                                                                <span class="w-1 h-1 rounded-full bg-gray-400 shrink-0" aria-hidden="true"></span>
                                                                @if (filled($person['title'] ?? null))
                                                                    <span class="font-medium text-gray-500">{{ $person['title'] }}:</span>
                                                                @endif
                                                                <span class="font-medium text-gray-700">{{ $person['name'] }}</span>
                                                            </li>
                                                        @endif
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </label>
                        </div>

                        {{-- Description (revealed when checked) --}}
                        <div class="mt-3 ml-9 coi-description hidden" data-coi-description="{{ $submission->id }}">
                            <input type="hidden" name="conflicts[{{ $submission->id }}][submission_id]" value="{{ $submission->id }}">
                            <label for="{{ $rowId }}-desc" class="block text-xs font-semibold text-uh-fg mb-1.5">
                                Please briefly describe the conflict of interest
                            </label>
                            <textarea id="{{ $rowId }}-desc"
                                      name="conflicts[{{ $submission->id }}][description]"
                                      rows="3"
                                      maxlength="2000"
                                      class="input text-sm leading-relaxed"
                                      placeholder="e.g. Co-author on a recent publication; departmental colleague; family member...">{{ old("conflicts.{$submission->id}.description", $existingEntry?->description) }}</textarea>
                            <p class="text-xs text-gray-400 mt-1">Optional but recommended. Max 2,000 characters.</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Actions --}}
    <div class="mt-6 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <a href="{{ route('reviewer.dashboard') }}" class="btn-secondary text-sm font-semibold py-2.5 px-4 justify-center text-center">
            Cancel
        </a>
        <button type="submit" class="btn-primary text-sm font-bold py-2.5 px-5 justify-center shadow-xs">
            <svg class="w-4 h-4 mr-1.5 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
            </svg>
            Submit declaration
        </button>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.coi-checkbox').forEach((checkbox) => {
            const toggleRow = () => {
                const id = checkbox.dataset.coiToggle;
                const desc = document.querySelector(`[data-coi-description="${id}"]`);
                if (!desc) return;
                if (checkbox.checked) {
                    desc.classList.remove('hidden');
                    const ta = desc.querySelector('textarea');
                    if (ta) ta.focus({ preventScroll: true });
                } else {
                    desc.classList.add('hidden');
                }
            };
            checkbox.addEventListener('change', toggleRow);
            toggleRow(); // initialize for pre-checked rows
        });
    });
</script>
@endsection
