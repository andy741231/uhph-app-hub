@extends('layouts.reviewer')
@section('title', 'Conflict of Interest — ' . $round->name)

@section('content')
<form action="{{ route('reviewer.conflicts.store', $round) }}" method="POST" id="coiForm">
    @csrf
    @if ($returnTo)
        <input type="hidden" name="return_to" value="{{ $returnTo }}">
    @endif

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
                Before reviewing any proposal in this cycle, you must declare any conflicts of interest.
                Review each submitter and proposal below and check the box for any situation in which you have a conflict
                — personal, professional, financial, or otherwise. When a conflict is identified, briefly describe
                its nature. Your declaration will be recorded on your profile and reported to the administrators.
            </p>
            <p class="text-sm text-gray-600 mt-2 max-w-3xl leading-relaxed">
                Departmental relationships at the University of Houston are not considered disqualifying conflicts
                for scientific review of Pilot Grant Program proposals. Given the program's institutional structure,
                reviewers may review proposals submitted by faculty or staff within their department or other UH
                departments. Reviewers should nevertheless disclose any other relationship or circumstance that could
                reasonably affect, or appear to affect, their impartiality.
            </p>
            <div class="mt-4 pt-4 border-t border-uh-border space-y-5">
                <div>
                    <button type="button" x-data x-on:click="$dispatch('open-modal', 'confidentiality-conduct')"
                            class="inline-flex items-center gap-1.5 text-sm font-semibold text-uh-red hover:underline">
                        <x-heroicon-o-document-text class="w-4 h-4" />
                        View Confidentiality &amp; Code of Conduct
                    </button>
                    <label for="confidentiality_acknowledged" class="mt-3 flex items-center gap-2.5 cursor-pointer">
                        <input type="checkbox" id="confidentiality_acknowledged" name="confidentiality_acknowledged" value="1" required
                               class="w-5 h-5 rounded border-gray-300 text-uh-red focus:ring-uh-red cursor-pointer"
                               {{ old('confidentiality_acknowledged') ? 'checked' : '' }}>
                        <span class="text-sm text-gray-700">I have read and agree to the Confidentiality Statement &amp; Code of Conduct</span>
                    </label>
                    @error('confidentiality_acknowledged')
                        <p class="text-sm text-uh-red mt-1.5">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <button type="button" x-data x-on:click="$dispatch('open-modal', 'coi-policy')"
                            class="inline-flex items-center gap-1.5 text-sm font-semibold text-uh-red hover:underline">
                        <x-heroicon-o-document-text class="w-4 h-4" />
                        View COI Policy &amp; Guidelines
                    </button>
                    <label for="coi_policy_acknowledged" class="mt-3 flex items-center gap-2.5 cursor-pointer">
                        <input type="checkbox" id="coi_policy_acknowledged" name="coi_policy_acknowledged" value="1" required
                               class="w-5 h-5 rounded border-gray-300 text-uh-red focus:ring-uh-red cursor-pointer"
                               {{ old('coi_policy_acknowledged') ? 'checked' : '' }}>
                        <span class="text-sm text-gray-700">I have read and agree to the COI Policy &amp; Guidelines</span>
                    </label>
                    @error('coi_policy_acknowledged')
                        <p class="text-sm text-uh-red mt-1.5">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    @if ($existing)
        <div class="mb-6 bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-lg flex items-start gap-3" role="status">
            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
            </svg>
            <div class="text-sm">
                <p class="font-semibold">You already submitted a declaration for this cycle on {{ $existing->declared_at->format('M j, Y g:i A') }}.</p>
                <p class="mt-0.5">You may update it below — submitting again will record a new version of your declaration and notify the administrator again. Your previous declarations are preserved.</p>
            </div>
        </div>
    @endif

    {{-- Empty submissions state --}}
    @if ($submissions->isEmpty())
        <div class="card p-10 text-center">
            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
            <p class="font-medium text-gray-700">No submitted proposals yet</p>
            <p class="text-sm text-gray-500 mt-1 max-w-md mx-auto">There are no submitted proposals in this cycle to declare conflicts for. You can submit an empty declaration now, or return later once proposals are available.</p>
        </div>
    @else
        <div class="card shadow-xs overflow-hidden">
            {{-- Header --}}
            <div class="px-5 py-4 border-b border-uh-border bg-uh-muted">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-uh-fg">Proposals in this cycle</h2>
                        <p class="text-sm text-gray-500 mt-0.5">
                            {{ $submissions->count() }} submitted proposal{{ $submissions->count() === 1 ? '' : 's' }}
                        </p>
                    </div>
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Select one per proposal</span>
                </div>
            </div>

            {{-- Proposal list --}}
            <div class="divide-y divide-uh-border">
                @foreach ($submissions as $submission)
                    @php
                        $existingResponse = $existingResponses->get($submission->id);
                        $selected = old(
                            "conflicts.{$submission->id}.has_conflict",
                            $existingResponse === null ? null : ($existingResponse->isConflict() ? '1' : '0')
                        );
                        $isConflict = $selected === '1' || $selected === 1 || $selected === true;
                        $isClear = $selected === '0' || $selected === 0 || $selected === false;
                        $addedAfterDeclaration = $existing !== null && $submission->submitted_at !== null && $submission->submitted_at->gt($existing->declared_at);
                        $rowId = 'coi-' . $submission->id;
                    @endphp
                    <div class="px-5 py-4" data-coi-row>
                        <div class="flex items-start gap-4">
                            {{-- Conflict / No conflict choice --}}
                            <div class="shrink-0 pt-0.5">
                                <input type="hidden" name="conflicts[{{ $submission->id }}][submission_id]" value="{{ $submission->id }}">
                                <div class="flex flex-col gap-1.5" role="group" aria-label="Conflict choice for {{ $submission->title }}">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox"
                                               id="{{ $rowId }}-conflict"
                                               name="conflicts[{{ $submission->id }}][has_conflict]"
                                               value="1"
                                               class="w-5 h-5 rounded border-gray-300 text-uh-red focus:ring-uh-red cursor-pointer coi-choice"
                                               data-coi-choice="{{ $submission->id }}"
                                               data-coi-value="1"
                                               {{ $isConflict ? 'checked' : '' }}>
                                        <span class="text-sm font-semibold text-uh-fg">Conflict</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox"
                                               id="{{ $rowId }}-clear"
                                               name="conflicts[{{ $submission->id }}][has_conflict]"
                                               value="0"
                                               class="w-5 h-5 rounded border-gray-300 text-uh-red focus:ring-uh-red cursor-pointer coi-choice"
                                               data-coi-choice="{{ $submission->id }}"
                                               data-coi-value="0"
                                               {{ $isClear ? 'checked' : '' }}>
                                        <span class="text-sm text-gray-600">No conflict</span>
                                    </label>
                                </div>
                                @error("conflicts.{$submission->id}.has_conflict")
                                    <p class="text-xs text-uh-red mt-1.5">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Submitter + title --}}
                            <label for="{{ $rowId }}-conflict" class="flex-1 min-w-0 cursor-pointer">
                                <div class="flex items-start gap-3">
                                    <div class="w-9 h-9 rounded-full bg-uh-red/10 flex items-center justify-center text-uh-red font-bold shrink-0 text-sm" aria-hidden="true">
                                        {{ strtoupper(substr($submission->submitter->first_name ?? '?', 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-uh-fg text-sm leading-snug">{{ $submission->title }}
                                            @if ($addedAfterDeclaration)
                                                <span class="inline-flex items-center rounded-full border border-uh-border bg-uh-muted px-2 py-0.5 text-[10px] font-semibold text-gray-600 align-middle ml-1">Added after your last declaration</span>
                                            @endif
                                        </p>
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

                        {{-- Description (revealed when Conflict is selected; required) --}}
                        <div class="mt-3 ml-9 coi-description {{ $isConflict ? '' : 'hidden' }}" data-coi-description="{{ $submission->id }}">
                            <label for="{{ $rowId }}-desc" class="block text-xs font-semibold text-uh-fg mb-1.5">
                                Please briefly describe the conflict of interest <span class="text-uh-red">*</span>
                            </label>
                            <textarea id="{{ $rowId }}-desc"
                                      name="conflicts[{{ $submission->id }}][description]"
                                      rows="3"
                                      maxlength="2000"
                                      {{ $isConflict ? 'required' : '' }}
                                      class="input text-sm leading-relaxed"
                                      placeholder="e.g. Co-author on a recent publication; departmental colleague; family member...">{{ old("conflicts.{$submission->id}.description", $existingResponse?->description) }}</textarea>
                            <p class="text-xs text-gray-400 mt-1">Required when a conflict is selected. Max 2,000 characters.</p>
                            @error("conflicts.{$submission->id}.description")
                                <p class="text-xs text-uh-red mt-1">{{ $message }}</p>
                            @enderror
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

<x-modal name="coi-policy" maxWidth="2xl">
    <div class="p-6">
        <div class="flex items-start justify-between gap-4">
            <h2 class="text-lg font-bold text-uh-fg">Conflict of Interest Policy &amp; Guidelines</h2>
            <button type="button" x-on:click="show = false" class="text-gray-400 hover:text-gray-600" aria-label="Close">
                <x-heroicon-o-x-mark class="w-6 h-6" />
            </button>
        </div>
        <div class="mt-4 space-y-4 text-sm text-gray-700 leading-relaxed max-h-[70vh] overflow-y-auto pr-2">
            <div>
                <h3 class="font-semibold text-uh-fg">Conflict of Interest Overview &amp; Policy</h3>
                <p class="mt-1.5">Peer review for the RCMI Pilot Grant Program adheres to <a style="color: #003366; text-decoration: underline;" href="https://www.nih.gov/sites/default/files/peer-review-conflict-interest-policy.pdf" target="_blank">National Institutes of Health (NIH) Conflict of Interest guidelines</a>.</p>
                <p class="mt-2">Before evaluating any assigned proposal, reviewers must review the project details and declare any actual, apparent, or potential conflicts of interest.</p>
                <p class="mt-2"><span class="font-semibold">Note:</span> Unlike traditional NIH study sections, departmental affiliation at the University of Houston is NOT an automatic disqualifying conflict. Due to specialized content expertise within specific academic units, reviewers MAY review proposals submitted by faculty or staff within their own department or college, provided no other disqualifying conflicts exist.</p>
            </div>
            <div>
                <h3 class="font-semibold text-uh-fg">What Constitutes a Disqualifying Conflict of Interest?</h3>
                <p class="mt-1.5">A disqualifying COI exists if any of the following apply to you regarding the Principal Investigator (PI) or Key Personnel on a proposal:</p>
                <ul class="mt-2 list-disc pl-5 space-y-1.5">
                    <li><span class="font-medium">Role / Involvement:</span> You are named as Key Personnel, a formal Collaborator, Mentor, or letter of support author on the proposal.</li>
                    <li><span class="font-medium">Recent Professional Relationship:</span> You have collaborated on a research project or co-authored a publication with the PI or Key Personnel within the past three years.</li>
                    <li><span class="font-medium">Financial Interest:</span> You, your spouse, or your dependent children would benefit financially from the funding or execution of the project.</li>
                    <li><span class="font-medium">Institutional / Personal Role:</span> You have a close personal relationship (family member, partner) or supervisory/mentoring role with the PI that compromises your scientific objectivity.</li>
                    <li><span class="font-medium">Other Circumstances:</span> You feel there is any other relationship or circumstance that could reasonably affect, or appear to affect, your impartiality.</li>
                </ul>
            </div>
            <p>If you are unsure whether a circumstance constitutes a conflict, please connect with us and we will be glad to discuss further.</p>
        </div>
        <div class="mt-6 text-right">
            <button type="button" class="btn-secondary" x-on:click="show = false">Close</button>
        </div>
    </div>
</x-modal>

<x-modal name="confidentiality-conduct" maxWidth="2xl">
    <div class="p-6">
        <div class="flex items-start justify-between gap-4">
            <h2 class="text-lg font-bold text-uh-fg">{{ \App\Support\ConfidentialityAgreementDocument::TITLE }}</h2>
            <button type="button" x-on:click="show = false" class="text-gray-400 hover:text-gray-600" aria-label="Close">
                <x-heroicon-o-x-mark class="w-6 h-6" />
            </button>
        </div>
        <div class="mt-4 space-y-4 text-sm text-gray-700 leading-relaxed max-h-[70vh] overflow-y-auto pr-2">
            @foreach (\App\Support\ConfidentialityAgreementDocument::sections() as $section)
                <div>
                    @if ($section['heading'] !== null)
                        <h3 class="font-semibold text-uh-fg">{{ $section['heading'] }}</h3>
                    @endif
                    @foreach ($section['paragraphs'] as $paragraph)
                        <p class="mt-1.5">{{ $paragraph }}</p>
                    @endforeach
                    @if (! empty($section['items']))
                        <ul class="mt-2 list-disc pl-5 space-y-1.5">
                            @foreach ($section['items'] as $item)
                                <li><span class="font-medium">{{ $item['label'] }}</span> {{ $item['text'] }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="mt-6 text-right">
            <button type="button" class="btn-secondary" x-on:click="show = false">Close</button>
        </div>
    </div>
</x-modal>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const updateRow = (id, focus = false) => {
            const conflictBox = document.querySelector(`[data-coi-choice="${id}"][data-coi-value="1"]`);
            const desc = document.querySelector(`[data-coi-description="${id}"]`);
            if (!conflictBox || !desc) return;
            const show = conflictBox.checked;
            desc.classList.toggle('hidden', !show);
            const textarea = desc.querySelector('textarea');
            if (textarea) {
                textarea.required = show;
                if (show && focus) textarea.focus({ preventScroll: true });
            }
        };

        document.querySelectorAll('.coi-choice').forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                const id = checkbox.dataset.coiChoice;
                if (checkbox.checked) {
                    document.querySelectorAll(`[data-coi-choice="${id}"]`).forEach((other) => {
                        if (other !== checkbox) other.checked = false;
                    });
                }
                const row = checkbox.closest('[data-coi-row]');
                if (row) row.classList.remove('ring-2', 'ring-uh-red/60', 'rounded-lg');
                updateRow(id, true);
            });
            updateRow(checkbox.dataset.coiChoice);
        });

        // An explicit Conflict / No conflict choice is required for every proposal.
        const form = document.getElementById('coiForm');
        form?.addEventListener('submit', (event) => {
            let firstMissing = null;
            document.querySelectorAll('[data-coi-row]').forEach((row) => {
                const chosen = row.querySelector('.coi-choice:checked');
                row.classList.toggle('ring-2', !chosen);
                row.classList.toggle('ring-uh-red/60', !chosen);
                row.classList.toggle('rounded-lg', !chosen);
                if (!chosen && !firstMissing) firstMissing = row;
            });
            if (firstMissing) {
                event.preventDefault();
                firstMissing.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    });
</script>
@endsection
