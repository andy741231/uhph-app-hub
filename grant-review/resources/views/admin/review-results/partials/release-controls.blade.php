{{--
    Segmented release control: one toggle segment per audience.

    - Off (eye icon, neutral): click releases the completed reviews to that
      audience and emails them.
    - On (check icon, UH red): reviews are released to that audience; click
      un-releases for that audience only.
    - Decided: renders disabled so the final release state stays visible.

    Renders nothing until the submission has reviews worth releasing
    (all assigned reviews submitted) or has already been released.
--}}
@php
    $decided = $submission->status === 'decided';
    $interactive = ! $decided && ($submission->reviewsComplete() || $submission->reviewsReleased());
    $static = $decided && $submission->reviewsReleased();
    $audiences = [
        'reviewers' => 'Reviewers',
        'submitter' => 'Submitter',
    ];
@endphp

@if ($interactive || $static)
    <div class="inline-flex items-stretch divide-x divide-uh-border overflow-hidden rounded-md border border-uh-border bg-white shadow-xs"
         role="group" aria-label="Release reviews by audience">
        @foreach ($audiences as $audience => $audienceLabel)
            @php
                $released = $audience === 'reviewers'
                    ? $submission->reviewsReleasedToReviewers()
                    : $submission->reviewsReleasedToSubmitter();
                $confirm = $released
                    ? ($audience === 'reviewers'
                        ? "Un-release these reviews for {$audienceLabel}? They lose access to the released peer feedback and their reviews become editable again. Email notifications already sent cannot be withdrawn."
                        : "Un-release these reviews for the submitter? They lose access to the released feedback and their proposal becomes editable again — even after the round deadline. Email notifications already sent cannot be withdrawn.")
                    : ($audience === 'reviewers'
                        ? "Release the completed reviews to {$audienceLabel}? They will see the anonymized peer feedback, their reviews become locked, and they will be notified by email."
                        : "Release the completed reviews to the submitter? They will see the feedback, their proposal becomes locked, and they will be notified by email.");
            @endphp
            <form method="POST"
                  action="{{ $released
                      ? route('admin.review-results.unrelease', [$submission, $audience])
                      : route('admin.review-results.release', [$submission, $audience]) }}"
                  class="flex">
                @csrf
                <button type="submit"
                        @disabled($decided)
                        aria-pressed="{{ $released ? 'true' : 'false' }}"
                        title="{{ $released ? 'Un-release for '.$audienceLabel : 'Release to '.$audienceLabel }}"
                        @if (! $decided) onclick="return confirm('{{ $confirm }}')" @endif
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold transition-colors duration-150 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-uh-red focus-visible:ring-offset-0
                            {{ $released
                                ? 'bg-uh-red text-white hover:bg-uh-brick'
                                : 'bg-white text-uh-slate hover:bg-uh-muted hover:text-uh-fg' }}
                            {{ $decided ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer' }}">
                    @if ($released)
                        <x-heroicon-o-check-circle class="w-4 h-4" />
                    @else
                        <x-heroicon-o-eye class="w-4 h-4" />
                    @endif
                    {{ $audienceLabel }}
                </button>
            </form>
        @endforeach
    </div>
@endif
