<?php

namespace App\Support;

/**
 * Canonical source for the Confidentiality Statement & Code of
 * Conduct shown to reviewers on the COI declaration page. The same
 * sections render the policy modal and produce the plain-text
 * snapshot stored on each agreement record, so the recorded wording
 * can never drift from what the reviewer actually saw.
 *
 * Bump VERSION (and update the text) whenever the source document
 * changes — a new version creates a fresh agreement record the next
 * time a reviewer declares for a cycle.
 */
final class ConfidentialityAgreementDocument
{
    public const VERSION = '2026-09-17';

    public const TITLE = 'Confidentiality Statement & Code of Conduct for Proposal Reviewers';

    public static function sections(): array
    {
        return [
            [
                'heading' => null,
                'paragraphs' => [
                    'The consortium of Research Centers in Minority Institutions (RCMI) grantee institutions agrees to adhere to common rules governing scientific peer review of pilot project proposals across RCMI U54 Centers. As part of this agreement, all assigned reviewers must review and agree to the following Confidentiality Statement prior to evaluating proposals submitted to the RCMI Pilot Grant Program. The consortium has adopted National Institutes of Health (NIH) guidelines regarding reviewer confidentiality, conflict of interest, and professional code of conduct.',
                ],
                'items' => [],
            ],
            [
                'heading' => 'Rules of Confidentiality',
                'paragraphs' => [],
                'items' => [
                    ['label' => 'Strict Non-Disclosure:', 'text' => 'Reviewers will not share, distribute, or discuss pilot project proposals, supporting materials, or any correspondence related to the review process with anyone outside the authorized review leadership.'],
                    ['label' => 'Confidential Evaluation:', 'text' => 'Reviewers will not discuss the contents of pilot project proposals, panel deliberations, or scoring results with non-committee members or applicants.'],
                    ['label' => 'Controlled Communication:', 'text' => 'Reviewers will not share reviewer critiques, scores, or summary feedback with anyone other than the RCMI Principal Investigator(s) and/or the Investigator Development Core (IDC) Director.'],
                    ['label' => 'Restricted Information Use:', 'text' => 'Reviewers will not use any information, data, methodology, or preliminary findings contained within submitted proposals for any purpose other than conducting their formal scientific review.'],
                ],
            ],
            [
                'heading' => 'Reviewer Code of Conduct',
                'paragraphs' => [],
                'items' => [
                    ['label' => 'Disclosure of Influence Attempts:', 'text' => 'Reviewers must immediately report any attempts by applicants or third parties to influence their evaluation or scoring to the Investigator Development Core Director.'],
                    ['label' => 'Independent Review:', 'text' => 'Reviewers will conduct all evaluations independently and will not delegate any portion of the proposal review, scoring, or critique writing to students, postdocs, colleagues, or staff members.'],
                    ['label' => 'Authorized Contact Points:', 'text' => 'Reviewers will direct all questions regarding proposals, review logistics, or evaluation guidelines solely to the Investigator Development Core Director (sgorniak@central.uh.edu) or RCMI team (UHrcmi@uh.edu). Reviewers must not contact applicants directly.'],
                    ['label' => 'Protection of Intellectual Property:', 'text' => 'Reviewers will strictly respect and safeguard all proprietary ideas, scientific hypotheses, specific aims, experimental designs, and future funding strategies disclosed within the proposals.'],
                ],
            ],
            [
                'heading' => 'Reviewer Attestation & Certification',
                'paragraphs' => [
                    'By checking the box below, I certify and attest that I have read, understand, and agree to abide by the Rules of Confidentiality and Code of Conduct governing the peer review process for the RCMI at University of Houston Pilot Grant Program.',
                ],
                'items' => [],
            ],
        ];
    }

    /**
     * Flatten the document to the plain-text snapshot stored on each
     * agreement record.
     */
    public static function text(): string
    {
        $lines = [self::TITLE, ''];

        foreach (self::sections() as $section) {
            if ($section['heading'] !== null) {
                $lines[] = $section['heading'];
            }
            foreach ($section['paragraphs'] as $paragraph) {
                $lines[] = $paragraph;
            }
            foreach ($section['items'] as $item) {
                $lines[] = '- '.$item['label'].' '.$item['text'];
            }
            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }
}
