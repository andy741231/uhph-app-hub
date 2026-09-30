<?php

namespace App\Support;

/**
 * Canonical source for the Conflict of Interest Policy & Guidelines
 * shown to reviewers on the COI declaration page. The same sections
 * render the policy modal and produce the plain-text snapshot stored
 * on each declaration record, so the recorded wording can never
 * drift from what the reviewer actually saw.
 *
 * Bump VERSION (and update the text) whenever the source document or
 * the linked NIH policy URL changes — declaration forms rendered
 * before the change carry the old digest and are rejected on submit,
 * forcing the reviewer to reload and re-read the current policy.
 *
 * Paragraphs are plain strings or lists of inline segments; a segment
 * may be a string, ['strong' => ...] for a bold inline phrase, or
 * ['link' => label, 'url' => href] for an external link.
 */
final class ConflictOfInterestPolicyDocument
{
    public const VERSION = '2026-09-30';

    public const TITLE = 'Conflict of Interest Policy & Guidelines';

    public const NIH_POLICY_URL = 'https://grants.nih.gov/policy-and-compliance/policy-topics/peer-review/coi';

    public static function sections(): array
    {
        return [
            [
                'heading' => 'Conflict of Interest Overview & Policy',
                'paragraphs' => [
                    [
                        'Peer review for the RCMI Pilot Grant Program adheres to ',
                        ['link' => 'National Institutes of Health (NIH) Conflict of Interest guidelines', 'url' => self::NIH_POLICY_URL],
                        '.',
                    ],
                    'Before evaluating any assigned proposal, reviewers must review the project details and declare any actual, apparent, or potential conflicts of interest.',
                    [
                        ['strong' => 'Note:'],
                        ' Unlike traditional NIH study sections, departmental affiliation at the University of Houston is NOT an automatic disqualifying conflict. Due to specialized content expertise within specific academic units, reviewers MAY review proposals submitted by faculty or staff within their own department or college, provided no other disqualifying conflicts exist.',
                    ],
                ],
                'items' => [],
            ],
            [
                'heading' => 'What Constitutes a Disqualifying Conflict of Interest?',
                'paragraphs' => [
                    'A disqualifying COI exists if any of the following apply to you regarding the Principal Investigator (PI) or Key Personnel on a proposal:',
                ],
                'items' => [
                    ['label' => 'Role / Involvement:', 'text' => 'You are named as Key Personnel, a formal Collaborator, Mentor, or letter of support author on the proposal.'],
                    ['label' => 'Recent Professional Relationship:', 'text' => 'You have collaborated on a research project or co-authored a publication with the PI or Key Personnel within the past three years.'],
                    ['label' => 'Financial Interest:', 'text' => 'You, your spouse, or your dependent children would benefit financially from the funding or execution of the project.'],
                    ['label' => 'Institutional / Personal Role:', 'text' => 'You have a close personal relationship (family member, partner) or supervisory/mentoring role with the PI that compromises your scientific objectivity.'],
                    ['label' => 'Other Circumstances:', 'text' => 'You feel there is any other relationship or circumstance that could reasonably affect, or appear to affect, your impartiality.'],
                ],
            ],
            [
                'heading' => null,
                'paragraphs' => [
                    'If you are unsure whether a circumstance constitutes a conflict, please connect with us and we will be glad to discuss further.',
                ],
                'items' => [],
            ],
        ];
    }

    /**
     * Flatten the document to the plain-text snapshot stored on each
     * declaration record.
     */
    public static function text(): string
    {
        $lines = [self::TITLE, ''];

        foreach (self::sections() as $section) {
            if ($section['heading'] !== null) {
                $lines[] = $section['heading'];
            }
            foreach ($section['paragraphs'] as $paragraph) {
                $lines[] = self::paragraphText($paragraph);
            }
            foreach ($section['items'] as $item) {
                $lines[] = '- '.$item['label'].' '.$item['text'];
            }
            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    /**
     * Render one paragraph segment as HTML for the policy modal:
     * plain strings are escaped, ['link'] segments keep the
     * established inline link style, ['strong'] segments render bold.
     */
    public static function segmentHtml(string|array $segment): string
    {
        if (is_string($segment)) {
            return e($segment);
        }

        if (isset($segment['link'])) {
            return '<a style="color: #003366; text-decoration: underline;" href="'.e($segment['url']).'" target="_blank" rel="noopener noreferrer">'.e($segment['link']).'</a>';
        }

        if (isset($segment['strong'])) {
            return '<span class="font-semibold">'.e($segment['strong']).'</span>';
        }

        return '';
    }

    /**
     * Integrity token embedded in the declaration form. The store
     * request accepts only the current digest, so a form opened
     * before a wording or version change cannot be submitted without
     * reloading and re-reading the policy.
     */
    public static function digest(): string
    {
        return hash('sha256', self::VERSION."\n".self::text());
    }

    private static function paragraphText(string|array $paragraph): string
    {
        $text = '';

        foreach ((array) $paragraph as $segment) {
            if (is_string($segment)) {
                $text .= $segment;
            } elseif (isset($segment['link'])) {
                $text .= $segment['link'].' ('.$segment['url'].')';
            } elseif (isset($segment['strong'])) {
                $text .= $segment['strong'];
            }
        }

        return $text;
    }
}
