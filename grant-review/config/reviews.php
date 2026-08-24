<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Blind Review
    |--------------------------------------------------------------------------
    |
    | When true, reviewers cannot see the submitter's name, department, or
    | email when viewing a submission or their review queue — only the
    | submission's own fields (title, abstract, amount, PDF) are visible.
    |
    | This hides identity at the *database field* level only. It cannot
    | redact identifying content (names, affiliations, acknowledgments)
    | that may appear inside the PDF itself — that is a document-content
    | concern outside this system's scope. Flag this limitation to program
    | staff if true double-blind review is required.
    |
    | Admins always see full submitter identity regardless of this flag —
    | blinding applies to the reviewer-facing views only.
    |
    */

    'blind_review' => env('REVIEWS_BLIND_REVIEW', true),

];
